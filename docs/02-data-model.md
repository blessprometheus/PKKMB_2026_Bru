# 02 — Model Data (PostgreSQL)

**Versi dokumen:** 1.0.0 · 22 September 2026

> ⚠️ **Skema `students` di §2 adalah rancangan, bukan final.** Kolomnya diturunkan dari kebutuhan
> nametag dan rekap, **bukan** dari file Excel PMB yang sebenarnya. Sebelum menulis importer,
> **buka file Excel aslinya** (D1 di [`01-prd.md`](01-prd.md) §8) dan cocokkan. Kalau meleset,
> **perbarui dokumen ini** — jangan diam-diam menyimpang. Lihat [`../AGENTS.md`](../AGENTS.md) §4.

---

## 1. Catatan PostgreSQL

- Proyek memakai **PostgreSQL**, bukan MySQL. Konsekuensi yang sering terlewat:
  - Pencarian tidak sensitif huruf besar/kecil pakai `ILIKE`, bukan `LIKE`.
  - `enum` MySQL tidak ada. Pakai kolom `varchar` + `CHECK`, atau tipe enum PostgreSQL.
    **Keputusan: pakai `varchar` + `CHECK`** — lebih mudah diubah lewat migrasi Laravel.
  - `jsonb` (bukan `json`) untuk kolom metadata, supaya bisa diindeks.
- **Nama tabel `sessions` sudah dipakai Laravel** untuk sesi HTTP. Tabel sesi presensi kita bernama
  **`attendance_sessions`**. Jangan tertukar.
- Semua kolom waktu pakai `timestamptz` dan aplikasi diset `Asia/Jakarta`
  (`APP_TIMEZONE`). Rekap kehadiran salah jam = rekap yang tidak dipercaya panitia.

## 2. Tabel

### 2.1 `admins` — akun panitia

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigserial PK | |
| `name` | varchar(120) | nama panitia |
| `email` | varchar(160) UNIQUE | dipakai untuk login |
| `password` | varchar(255) | **hash** bcrypt/argon2, tidak pernah polos |
| `role` | varchar(20) CHECK IN ('admin','operator') | `admin` = penuh, `operator` = hanya halaman scan |
| `is_active` | boolean default true | menonaktifkan tanpa menghapus |
| `last_login_at` | timestamptz NULL | |
| `remember_token` | varchar(100) NULL | bawaan Laravel |
| `created_at`, `updated_at` | timestamptz | |

Peran sengaja hanya dua. Petugas presensi **tidak boleh** melihat daftar lengkap mahasiswa —
lihat [`04-security.md`](04-security.md) §1.

### 2.2 `students` — mahasiswa baru

| Kolom | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `id` | bigserial PK | ✔ | |
| `nim` | varchar(30) UNIQUE | ✔ | kunci pencarian publik |
| `name` | varchar(160) | ✔ | nama lengkap, tampil besar di nametag |
| `faculty` | varchar(120) | ✔ | fakultas |
| `study_program` | varchar(160) | ✔ | program studi |
| `group_name` | varchar(80) NULL | | gugus/kelompok PKKMB — `TODO:` konfirmasi D6 |
| `gender` | varchar(1) CHECK IN ('L','P') NULL | | kalau ada di data PMB |
| `birth_date` | date NULL | | **jangan ditampilkan** di hasil lookup publik |
| `phone` | varchar(25) NULL | | **jangan ditampilkan** di hasil lookup publik |
| `email` | varchar(160) NULL | | **jangan ditampilkan** di hasil lookup publik |
| `photo_path` | varchar(255) NULL | | kalau PMB menyediakan pas foto; dipakai di nametag |
| `attendance_token` | varchar(32) UNIQUE | ✔ | isi QR. Acak, **bukan** turunan NIM. Lihat §4 |
| `import_batch_id` | bigint FK NULL | | asal baris data |
| `created_at`, `updated_at` | timestamptz | | |

**Indeks:**
```sql
CREATE UNIQUE INDEX students_nim_unique    ON students (nim);
CREATE UNIQUE INDEX students_token_unique  ON students (attendance_token);
CREATE INDEX        students_name_search   ON students USING gin (to_tsvector('simple', name));
CREATE INDEX        students_faculty_idx   ON students (faculty);
```

### 2.3 `attendance_sessions` — sesi presensi

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigserial PK | |
| `name` | varchar(120) | mis. "Hari 1 — Pembukaan" |
| `event_date` | date | |
| `starts_at` | timestamptz | |
| `ends_at` | timestamptz | |
| `is_active` | boolean default false | hanya sesi aktif yang bisa menerima scan |
| `created_at`, `updated_at` | timestamptz | |

`TODO:` jumlah hari & sesi menunggu D2. Rancangan awal: satu sesi per hari, jendela waktu selebar acara.

Scan di luar `starts_at`–`ends_at` **ditolak dengan pesan jelas**, bukan diam-diam diterima —
supaya tidak ada kehadiran yang tercatat pada sesi yang salah.

### 2.4 `attendances` — catatan kehadiran

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigserial PK | |
| `student_id` | bigint FK → students | ON DELETE CASCADE |
| `attendance_session_id` | bigint FK → attendance_sessions | ON DELETE CASCADE |
| `admin_id` | bigint FK → admins NULL | petugas yang memindai |
| `scanned_at` | timestamptz | |
| `method` | varchar(10) CHECK IN ('qr','manual') | `manual` = petugas mengetik NIM |
| `device_label` | varchar(60) NULL | mis. "Pintu A" — dipilih petugas saat login scan |
| `created_at`, `updated_at` | timestamptz | |

**Indeks & batasan:**
```sql
ALTER TABLE attendances
  ADD CONSTRAINT attendances_unique_per_session UNIQUE (student_id, attendance_session_id);
CREATE INDEX attendances_session_time_idx ON attendances (attendance_session_id, scanned_at);
```

Batasan UNIQUE itu **yang menjadikan duplikat mustahil**, bukan pengecekan di kode PHP. Tiga scanner
memindai bersamaan — pengecekan di aplikasi bisa kalah balapan, batasan database tidak.

### 2.5 `import_batches` — riwayat impor Excel

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigserial PK | |
| `admin_id` | bigint FK → admins | |
| `original_filename` | varchar(255) | |
| `stored_path` | varchar(255) | di luar webroot, lihat [`04-security.md`](04-security.md) §5 |
| `total_rows` | integer | |
| `inserted_count` | integer | |
| `updated_count` | integer | |
| `failed_count` | integer | |
| `error_report` | jsonb NULL | daftar `{row, column, message}` |
| `status` | varchar(20) CHECK IN ('processing','done','failed') | |
| `created_at`, `updated_at` | timestamptz | |

### 2.6 `scan_logs` — jejak pemindaian (termasuk yang ditolak)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigserial PK | |
| `admin_id` | bigint FK → admins NULL | |
| `attendance_session_id` | bigint FK NULL | |
| `student_id` | bigint FK NULL | null kalau token tidak dikenal |
| `result` | varchar(24) CHECK IN ('recorded','duplicate','unknown_token','session_closed','rate_limited') | |
| `raw_input_hash` | varchar(64) NULL | **hash** dari input mentah, bukan tokennya |
| `ip` | inet NULL | |
| `created_at` | timestamptz | |

Ini yang memungkinkan menjawab "kenapa si A merasa sudah absen tapi tidak tercatat" setelah acara.
`raw_input_hash` disimpan sebagai hash supaya log tidak menjadi daftar token yang bisa dipakai ulang.

### 2.7 `audit_logs` — jejak aksi admin

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigserial PK | |
| `admin_id` | bigint FK → admins NULL | |
| `action` | varchar(60) | mis. `student.updated`, `import.completed`, `attendance.deleted` |
| `target_type` | varchar(60) NULL | |
| `target_id` | bigint NULL | |
| `meta` | jsonb NULL | **tanpa data pribadi mentah** — simpan id, bukan nama & NIM |
| `ip` | inet NULL | |
| `user_agent` | varchar(255) NULL | |
| `created_at` | timestamptz | |

### 2.8 `lookup_attempts` — pantauan penyapuan NIM

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigserial PK | |
| `ip` | inet | |
| `nim_hash` | varchar(64) | hash NIM, **bukan** NIM polos |
| `found` | boolean | |
| `created_at` | timestamptz | |

Dipakai untuk mendeteksi ada yang menyapu rentang NIM. Rate limit sendiri ditangani Laravel
RateLimiter; tabel ini untuk **melihat** serangannya, dan dibersihkan otomatis setelah 30 hari.

## 3. Relasi

```
admins 1 ─── n import_batches
admins 1 ─── n attendances        (petugas yang memindai)
admins 1 ─── n audit_logs
import_batches 1 ─── n students
students 1 ─── n attendances
attendance_sessions 1 ─── n attendances
students 1 ─── n scan_logs
```

## 4. Aturan `attendance_token` (isi QR)

- Dibuat **saat mahasiswa pertama kali masuk database** (impor atau tambah manual).
- **32 karakter acak** dari sumber acak kriptografis (`random_bytes(16)` → hex), bukan `rand()`,
  bukan turunan NIM, bukan UUID berurutan.
- **Alasan tidak memakai NIM sebagai isi QR:** NIM berurutan dan bisa ditebak. Kalau QR berisi NIM,
  siapa pun bisa mencetak QR seluruh angkatan tanpa menyentuh sistem kita. Dengan token acak, QR yang
  sah hanya bisa diperoleh lewat endpoint kita — sehingga bisa dibatasi, dicatat, dan dicabut.
- **Yang tidak diselesaikan token:** maba tetap bisa meminta QR temannya dengan NIM temannya.
  Mitigasinya bukan di sini, tapi di [`04-security.md`](04-security.md) §3.
- Token **boleh dicabut/diputar** per mahasiswa (`POST /admin/students/{id}/rotate-token`) kalau ada
  indikasi disalahgunakan. QR lama langsung tidak berlaku.

## 5. Aturan Impor Excel/CSV

1. **Pemetaan kolom eksplisit**, berdasarkan nama header — **tidak boleh** berdasarkan urutan kolom.
   Header dinormalkan dulu: huruf kecil, spasi/tanda baca → `_`.
   Peta sementara (wajib diverifikasi dengan D1):

   | Header Excel (dugaan) | Kolom DB |
   |---|---|
   | `nim` / `no_induk` / `nomor_induk_mahasiswa` | `nim` |
   | `nama` / `nama_lengkap` / `nama_mahasiswa` | `name` |
   | `fakultas` | `faculty` |
   | `prodi` / `program_studi` / `jurusan` | `study_program` |
   | `kelompok` / `gugus` | `group_name` |
   | `jk` / `jenis_kelamin` / `l_p` | `gender` |

   Kalau ada header wajib yang tidak ditemukan → **impor dibatalkan seluruhnya** dengan pesan yang
   menyebut header apa yang dicari dan header apa yang ditemukan. Jangan impor sebagian.

2. **Validasi per baris:** `nim` wajib ada dan belum dipakai baris lain di file yang sama; `name`,
   `faculty`, `study_program` wajib terisi. Baris gagal **tidak menggagalkan seluruh impor** — dicatat
   di `error_report` dengan nomor barisnya, sisanya tetap masuk.
3. **Upsert berdasarkan `nim`.** Impor ulang file yang sama tidak membuat duplikat dan
   **tidak mengubah `attendance_token`** mahasiswa yang sudah ada — QR yang sudah dicetak harus tetap sah.
4. Seluruh impor dibungkus **transaksi**, kecuali baris yang gagal validasi.
5. Nilai dibersihkan: `trim`, rapikan spasi ganda, buang karakter kontrol. NIM dibaca sebagai **teks**,
   bukan angka — Excel gemar membuang angka nol di depan.
6. Berkas yang diunggah disimpan di luar webroot dan **tidak pernah di-commit**.

## 6. Retensi Data

| Data | Simpan sampai | Alasan |
|---|---|---|
| `students` | Akhir tahun akademik, lalu diarsipkan/dihapus atas persetujuan panitia | Data pribadi tidak disimpan tanpa keperluan |
| `attendances` | Permanen (jadi bukti kehadiran resmi) | |
| `scan_logs`, `lookup_attempts` | 30 hari, dibersihkan terjadwal | Log berisi jejak perilaku, tidak perlu disimpan lama |
| File Excel unggahan | 30 hari setelah impor sukses | Sudah masuk database |
