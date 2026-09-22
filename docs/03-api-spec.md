# 03 — Spesifikasi API (Laravel)

**Versi dokumen:** 1.0.0 · 22 September 2026
**Base path:** `/api/v1`

---

## 1. Aturan Umum

### 1.1 Format response

Semua response mengikuti satu pola:

```json
{ "success": true,  "data": { } }
{ "success": false, "message": "Pesan dalam Bahasa Indonesia", "errors": { "nim": ["NIM wajib diisi."] } }
```

- `message` **selalu Bahasa Indonesia** — ini tampil langsung ke maba dan petugas.
- Kunci JSON pakai `snake_case`, sama dengan kolom database.
- Jangan pernah mengembalikan stack trace ke klien di produksi (`APP_DEBUG=false`).

### 1.2 Kode status

| Kode | Dipakai untuk |
|---|---|
| 200 | Sukses |
| 201 | Sumber daya dibuat |
| 401 | Belum login |
| 403 | Login tapi tidak berhak (mis. operator membuka daftar mahasiswa) |
| 404 | Tidak ditemukan |
| 422 | Validasi gagal |
| 429 | Kena rate limit |

### 1.3 Autentikasi

- **Laravel Sanctum, mode cookie SPA same-origin.** Frontend dan API berada di domain yang sama
  (Nginx meneruskan `/api/*` ke Laravel), jadi tidak perlu token di `localStorage` — cookie
  `HttpOnly` + `SameSite=Lax` lebih aman. Lihat [`06-deployment.md`](06-deployment.md) §3.
- Endpoint yang mengubah state wajib menyertakan CSRF token (`/sanctum/csrf-cookie` dulu).
- Peran: `admin` (penuh) dan `operator` (hanya `/scan` dan endpoint terkait).

### 1.4 Rate limit

| Endpoint | Batas |
|---|---|
| `POST /lookup` | 10 / menit / IP **dan** 40 / jam / IP |
| `GET` unduhan bertanda tangan | 20 / menit / IP |
| `POST /auth/login` | 5 / menit / IP, kunci akun 15 menit setelah 8 kegagalan berturut-turut |
| `POST /scan` | 180 / menit / akun petugas (3 scanner, cukup longgar untuk antrean cepat) |
| Endpoint admin lain | 120 / menit / akun |

Response 429 mengembalikan `message` Bahasa Indonesia dan header `Retry-After`.

---

## 2. Endpoint Publik (tanpa login)

### 2.1 `POST /api/v1/lookup`

Mencari data maba berdasarkan NIM.

**Kenapa POST, bukan GET:** NIM adalah data pribadi. `GET /lookup?nim=...` akan tercatat di log akses
Nginx, riwayat browser, dan header `Referer` — lihat [`../AGENTS.md`](../AGENTS.md) §5.

Request:
```json
{ "nim": "20260012345" }
```

Response 200:
```json
{
  "success": true,
  "data": {
    "nim": "20260012345",
    "name": "Nama Mahasiswa",
    "faculty": "Fakultas Teknik",
    "study_program": "Teknik Informatika",
    "group_name": "Gugus 3",
    "attendance": [
      { "session_name": "Hari 1 — Pembukaan", "status": "hadir", "scanned_at": "2026-09-29T08:14:02+07:00" },
      { "session_name": "Hari 2 — Materi",    "status": "belum", "scanned_at": null }
    ],
    "downloads": {
      "nametag_url": "https://.../api/v1/download/nametag/<signed>",
      "qr_url":      "https://.../api/v1/download/qr/<signed>"
    }
  }
}
```

**Field yang TIDAK BOLEH dikembalikan:** `birth_date`, `phone`, `email`, `attendance_token`,
`id`, `import_batch_id`. Endpoint ini terbuka untuk siapa saja — kembalikan seminimal mungkin.
Token QR **tidak pernah** dikirim sebagai teks; hanya tertanam di gambar QR hasil unduhan.

Response 404:
```json
{ "success": false, "message": "NIM tidak ditemukan. Pastikan NIM sesuai yang diberikan bagian akademik." }
```

Pesan 404 sengaja tidak membedakan "NIM tidak ada" dari "NIM ada tapi bermasalah" — keduanya sama saja
bagi maba, dan pembedaannya hanya berguna bagi orang yang sedang menyapu data.

### 2.2 `GET /api/v1/download/nametag/{signed}`

Mengembalikan **PDF** nametag siap cetak (QR menyatu di dalamnya).

- URL dibuat dengan `URL::temporarySignedRoute`, **berlaku 15 menit**.
- Tidak menerima `nim` atau `id` sebagai parameter biasa — hanya tanda tangan yang sah.
- Header: `Content-Disposition: attachment; filename="nametag-<nim>.pdf"`.
- Kalau tanda tangan kedaluwarsa → 403 dengan pesan "Tautan unduhan sudah kedaluwarsa. Silakan cari NIM Anda lagi."

### 2.3 `GET /api/v1/download/qr/{signed}`

Sama seperti di atas, mengembalikan **PNG** QR Code saja (untuk yang menyimpan di galeri HP).
Ukuran minimal **600×600 px** agar tetap terbaca scanner setelah dikompres WhatsApp.

---

## 3. Endpoint Autentikasi

| Method | Path | Akses | Keterangan |
|---|---|---|---|
| GET | `/sanctum/csrf-cookie` | publik | wajib dipanggil sebelum login |
| POST | `/api/v1/auth/login` | publik | `{ email, password }` → set cookie sesi |
| POST | `/api/v1/auth/logout` | login | |
| GET | `/api/v1/auth/me` | login | `{ id, name, email, role }` |

Pesan gagal login **selalu sama** apa pun sebabnya: `"Email atau kata sandi salah."` — jangan
membocorkan email mana yang terdaftar. Waktu responsnya pun disamakan: saat email tidak terdaftar,
`Hash::check` tetap dijalankan terhadap hash boneka yang sah, supaya selisih waktu tidak ikut
membocorkan email mana yang ada.

**Status 400 — `SANCTUM_STATEFUL_DOMAINS` tidak cocok.** Login berbasis cookie butuh sesi, dan Sanctum
hanya menyalakan sesi bila `Origin`/`Referer` permintaan cocok dengan daftar domain stateful. Bila tidak
cocok, endpoint mengembalikan **400 dengan pesan yang menyebut `SANCTUM_STATEFUL_DOMAINS`** — bukan 500
"Session store not set on request" yang tidak menjelaskan apa pun. Ini ditemukan lewat test, dan
sengaja dipertahankan sebagai pesan diagnosis saat deploy.

---

## 4. Endpoint Admin (`role = admin`)

### 4.1 Mahasiswa

| Method | Path | Keterangan |
|---|---|---|
| GET | `/api/v1/admin/students` | paginasi; query: `q` (nama/NIM), `faculty`, `group_name`, `attendance_status`, `page`, `per_page` (maks 100) |
| POST | `/api/v1/admin/students` | tambah manual (pendaftar susulan) |
| GET | `/api/v1/admin/students/{id}` | |
| PUT | `/api/v1/admin/students/{id}` | koreksi data |
| DELETE | `/api/v1/admin/students/{id}` | tercatat di `audit_logs` |
| POST | `/api/v1/admin/students/{id}/rotate-token` | cabut QR lama, buat token baru |
| GET | `/api/v1/admin/students/export` | ekspor Excel seluruh peserta |

Pencarian `q` memakai `ILIKE` (PostgreSQL) — lihat [`02-data-model.md`](02-data-model.md) §1.

### 4.2 Impor Excel

| Method | Path | Keterangan |
|---|---|---|
| POST | `/api/v1/admin/students/import` | `multipart/form-data`, field `file` (.xlsx/.xls/.csv, maks 10 MB) |
| GET | `/api/v1/admin/import-batches` | riwayat impor |
| GET | `/api/v1/admin/import-batches/{id}` | detail + `error_report` |
| GET | `/api/v1/admin/import-batches/{id}/errors.xlsx` | unduh daftar baris gagal untuk diperbaiki |

Response impor:
```json
{
  "success": true,
  "data": {
    "batch_id": 3,
    "total_rows": 548,
    "inserted_count": 540,
    "updated_count": 5,
    "failed_count": 3,
    "errors": [
      { "row": 17, "column": "nim", "message": "NIM sudah dipakai di baris 12." },
      { "row": 92, "column": "study_program", "message": "Program studi wajib diisi." }
    ]
  }
}
```

Validasi berkas & aturan upsert ada di [`02-data-model.md`](02-data-model.md) §5 dan
[`04-security.md`](04-security.md) §5. **Impor dibatalkan seluruhnya** kalau header wajib tidak ditemukan.

### 4.3 Sesi presensi

| Method | Path | Keterangan |
|---|---|---|
| GET | `/api/v1/admin/attendance-sessions` | |
| POST | `/api/v1/admin/attendance-sessions` | `{ name, event_date, starts_at, ends_at }` |
| PUT | `/api/v1/admin/attendance-sessions/{id}` | |
| POST | `/api/v1/admin/attendance-sessions/{id}/activate` | menonaktifkan sesi lain kalau perlu |
| DELETE | `/api/v1/admin/attendance-sessions/{id}` | ditolak kalau sudah ada kehadiran tercatat |

### 4.4 Kehadiran & laporan

| Method | Path | Keterangan |
|---|---|---|
| GET | `/api/v1/admin/attendances?session_id=` | daftar hadir, paginasi |
| GET | `/api/v1/admin/attendances/export?session_id=` | ekspor Excel (hadir + **belum hadir**) |
| POST | `/api/v1/admin/attendances` | catat manual `{ student_id, attendance_session_id }` |
| DELETE | `/api/v1/admin/attendances/{id}` | koreksi salah scan; wajib tercatat di `audit_logs` |
| GET | `/api/v1/admin/dashboard/stats` | ringkasan untuk dashboard |

`dashboard/stats` mengembalikan:
```json
{
  "success": true,
  "data": {
    "total_students": 548,
    "active_session": { "id": 1, "name": "Hari 1 — Pembukaan" },
    "present_count": 431,
    "absent_count": 117,
    "by_faculty": [ { "faculty": "Fakultas Teknik", "total": 120, "present": 98 } ],
    "scans_per_10min": [ { "at": "08:00", "count": 42 } ]
  }
}
```

`scans_per_10min` dipakai untuk grafik laju kedatangan — pakai skill `dataviz` saat membuat grafiknya.

### 4.5 Akun panitia

| Method | Path | Keterangan |
|---|---|---|
| GET/POST | `/api/v1/admin/admins` | daftar & tambah akun |
| PUT | `/api/v1/admin/admins/{id}` | ubah nama/peran/aktif |
| POST | `/api/v1/admin/admins/{id}/reset-password` | admin menyetel ulang kata sandi petugas |

Ekspor Excel apa pun **wajib menetralkan formula** — lihat [`04-security.md`](04-security.md) §5.3.

---

## 5. Endpoint Presensi (`role = admin` atau `operator`)

### 5.1 `GET /api/v1/scan/context`

Dipanggil saat halaman scan dibuka. Mengembalikan sesi yang aktif + info petugas.

```json
{
  "success": true,
  "data": {
    "operator": { "name": "Petugas Pintu A" },
    "sessions": [ { "id": 1, "name": "Hari 1 — Pembukaan", "starts_at": "...", "ends_at": "...", "is_active": true } ]
  }
}
```

### 5.2 `POST /api/v1/scan`

Inti presensi. Dipanggil setiap kali scanner gun menembak QR.

Request:
```json
{ "token": "9f2c...", "attendance_session_id": 1, "device_label": "Pintu A" }
```

Response — **selalu 200**, hasilnya ada di `data.result`. Alasannya: halaman scan harus menampilkan
ketiga keadaan dengan cara yang sama cepatnya, dan 404/409 membuat penanganan di frontend berbelit
saat antrean sedang panjang.

```json
{
  "success": true,
  "data": {
    "result": "recorded",
    "message": "TERCATAT",
    "student": { "name": "Nama Mahasiswa", "nim": "20260012345", "study_program": "Teknik Informatika", "group_name": "Gugus 3" },
    "scanned_at": "2026-09-29T08:14:02+07:00"
  }
}
```

Nilai `result` yang mungkin:

| `result` | Arti | Tampilan di layar petugas |
|---|---|---|
| `recorded` | Kehadiran baru tercatat | 🟢 hijau besar + bunyi sukses |
| `duplicate` | Sudah absen di sesi ini | 🟡 kuning + jam absen sebelumnya |
| `unknown_token` | QR tidak dikenal | 🔴 merah "QR TIDAK DIKENAL" |
| `session_closed` | Di luar jendela waktu sesi | 🔴 merah "SESI BELUM/SUDAH TUTUP" |

Aturan penting:

1. Pencatatan memakai **`INSERT ... ON CONFLICT DO NOTHING`** pada batasan
   `(student_id, attendance_session_id)`. Kalau tidak ada baris baru → `duplicate`.
   Jangan cek-dulu-baru-insert; tiga scanner bisa menembak orang yang sama nyaris bersamaan.
2. Setiap hasil, termasuk yang ditolak, ditulis ke `scan_logs`.
3. Response **tidak memuat** `birth_date`, `phone`, `email`, atau `attendance_token`.

### 5.3 `POST /api/v1/scan/manual`

Cadangan kalau QR rusak atau maba tidak membawa nametag.

```json
{ "nim": "20260012345", "attendance_session_id": 1, "device_label": "Pintu A" }
```

Perilaku sama dengan §5.2, tapi `method` tercatat `manual` sehingga bisa diaudit setelah acara.
Hanya untuk akun yang sudah login — **bukan** endpoint publik.

### 5.4 `GET /api/v1/scan/recent?session_id=&limit=10`

Sepuluh pemindaian terakhir di sesi itu, untuk daftar di bawah layar petugas supaya mereka yakin
sistem masih hidup.

---

## 6. Yang Sengaja Tidak Dibuat

- ❌ Endpoint apa pun yang mengembalikan `attendance_token` sebagai teks.
- ❌ `GET /lookup?nim=` — lihat §2.1.
- ❌ Endpoint publik yang mengembalikan daftar mahasiswa (hanya pencarian satu per satu).
- ❌ Endpoint presensi tanpa autentikasi. Petugas **wajib** login sebelum memindai.
