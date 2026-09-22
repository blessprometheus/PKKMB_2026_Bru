# 07 — Timeline & Prioritas

**Versi dokumen:** 1.0.0 · 22 September 2026
**Hari-H:** **Selasa, 29 September 2026**, registrasi 06:30–07:15 WIB — dikonfirmasi dari dokumen
panitia (bahan/README.md §4). Hari ke-2 dst. belum ada dokumennya.
**Sumber daya:** 1 orang (user) + AI. **Tujuh hari.**

---

## 1. Penilaian Jujur Soal Jadwal

Tujuh hari, satu orang, stack baru (Next + Laravel + PostgreSQL di VPS), tiga modul (data & admin,
lookup & unduhan, presensi), ditambah landing page yang dibangun ulang dari nol karena v1 diganti total.

**Ini di luar batas aman.** Bukan berarti tidak bisa — berarti **urutan kerja dan batas potong scope
adalah bagian terpenting dari dokumen ini**, bukan pelengkap.

Yang membuat acara gagal kalau tidak siap, berurutan:

1. **Presensi** — kalau ini tidak jalan, 550 orang mengantre tanpa cara mencatat kehadiran.
2. **Unduhan nametag** — kalau ini tidak jalan, tidak ada QR untuk dipindai.
3. **Impor data** — kalau ini tidak jalan, tidak ada data sama sekali.
4. **Landing page** — kalau ini tidak jalan, informasi bisa dibagikan lewat kanal lain seperti selama ini.

Karena itu urutan pengerjaan adalah kebalikan dari urutan yang terlihat di layar: **yang paling belakang
dilihat orang dikerjakan paling awal.**

---

## 2. Rencana Tujuh Hari

| Hari | Tanggal | Fokus | Keluaran yang harus jadi |
|---|---|---|---|
| **H-7** | Sen–Sel 22 Sep | **Fase 0–1** Dokumentasi + fondasi | `docs/` + `AGENTS.md` ✅ · repo git · Laravel + Next jalan lokal · PostgreSQL tersambung · **semua migrasi tabel jalan** · VPS terpasang Nginx/PHP/Node/Postgres |
| **H-6** | 23 Sep | **Fase 2a** Auth + CRUD | Login admin jalan (2 peran) · CRUD mahasiswa · daftar + pencarian |
| **H-5** | 24 Sep | **Fase 2b** Impor Excel | Importer jalan **dengan file PMB asli (D1)** · laporan baris gagal · `attendance_token` tergenerate |
| **H-4** | 25 Sep | **Fase 3** Lookup + unduhan | `POST /lookup` + rate limit · QR PNG · **nametag PDF** · halaman cari NIM |
| **H-3** | 26 Sep | **Fase 4** Presensi | Sesi presensi · `POST /scan` + batasan UNIQUE · halaman `/scan` · **uji dengan scanner asli** · rekap + ekspor |
| **H-2** | 27 Sep | **Fase 5** Landing + hardening | Landing page · dashboard statistik · `security-review` · perbaiki temuan |
| **H-1** | 28 Sep | **Fase 6** Go-live | Deploy produksi · `ship-gate` · checklist keamanan §11 · **uji end-to-end 3 scanner** · cetak cadangan kertas · briefing petugas · **bekukan kode** |
| **H** | 29 Sep | Hari pelaksanaan | Runbook [`06-deployment.md`](06-deployment.md) §7. **Tidak ada deploy.** |

> Landing page sengaja ditempatkan di **H-2**, bukan di awal. Itu bagian yang paling terlihat, dan
> karena itu paling menggoda dikerjakan duluan. Jangan. Ia juga bagian yang paling aman dipotong.

---

## 3. Blocker yang Harus Dibereskan Hari Ini

Tiga hal di bawah **memblokir hari berikutnya**, bukan hari terakhir. Kalau belum ada pada H-6,
jadwal mundur dan scope harus dipotong lebih awal.

| Blocker | Memblokir | Kenapa mendesak |
|---|---|---|
| **D1 — file Excel PMB asli** | H-5 (impor) | Importer tidak boleh ditulis berdasarkan tebakan nama kolom. Minta hari ini juga |
| **D2 — daftar sesi presensi resmi** | H-3 (presensi) | Jendela waktu sesi menentukan scan diterima atau ditolak |
| **D4 — merek/model scanner gun** | H-3 (uji scan) | Perilaku sufiks Enter berbeda antar alat; harus diuji dengan alat asli, bukan simulasi |

D3 (ketentuan nametag) dan D5 (logo) memblokir H-4. Kalau belum ada, buat nametag dengan tata letak
rancangan di [`05-frontend-spec.md`](05-frontend-spec.md) §6 dan **tandai `TODO:`** — jangan berhenti
menunggu, tapi jangan pula menganggapnya final.

---

## 4. Definisi "Selesai" per Fase

Sebuah fase belum selesai sampai butirnya terbukti, bukan terlihat jalan.

| Fase | Selesai artinya |
|---|---|
| 1 Fondasi | `php artisan migrate` jalan bersih di PostgreSQL kosong; Next memanggil satu endpoint Laravel dan menerima JSON |
| 2 Data & admin | Impor **file PMB asli** berhasil, jumlah baris cocok, baris gagal terlaporkan dengan nomor barisnya |
| 3 Lookup & unduhan | Nametag hasil unduhan **dicetak di kertas sungguhan** dan QR-nya terbaca scanner |
| 4 Presensi | **Scanner asli (3 atau 4, konfirmasi ulang — lihat §3)** memindai bersamaan; orang yang sama dipindai dua kali → `duplicate`, bukan dua baris; ekspor Excel memuat yang belum hadir |
| 5 Landing | Tampil benar di 360 px tanpa gulir horizontal; informasi utama ada di HTML server-render |
| 6 Go-live | Seluruh checklist [`04-security.md`](04-security.md) §11 ✅; backup sudah pernah dipulihkan sekali |

> **Fase 4 tidak boleh ditandai selesai berdasarkan mengetik token dengan papan ketik.** Scanner gun
> punya perilaku sendiri (kecepatan ketik, sufiks, karakter tambahan) yang hanya ketahuan dengan alat asli.

---

## 5. Batas Potong Scope

Kalau pada **akhir H-3 (26 Sep)** presensi belum jalan end-to-end, potong dalam urutan ini:

1. **Potong pertama:** animasi (GSAP + Lenis), dark mode, dashboard statistik & grafik (C1, S2, S5).
   Animasi dirancang agar bisa dicabut dalam hitungan menit — lihat
   [`05-frontend-spec.md`](05-frontend-spec.md) §8.6.
2. **Potong kedua:** landing page penuh → ganti satu halaman ringkas berisi informasi penting +
   kotak cari NIM. Informasi lengkap dibagikan lewat kanal panitia seperti tahun sebelumnya.
3. **Potong ketiga:** cetak nametag massal (S3) → panitia mencetak dari ekspor Excel + template cetak surat.
4. **Potong keempat:** antrean offline (S4) → andalkan koneksi + cadangan kertas.

**Yang tidak boleh dipotong, apa pun yang terjadi:**
impor data · lookup NIM · unduh nametag + QR · halaman `/scan` · input NIM manual · ekspor rekap ·
login admin · rate limit · checklist keamanan §11.

Kalau pemotongan sampai tingkat 2, **beri tahu user hari itu juga** — jangan tunggu H-1.

---

## 6. Rencana Cadangan Manual (wajib disiapkan, bukan opsional)

Ini bukan tanda pesimis. Ini yang membedakan "sistem gagal" dari "acara gagal".

**Disiapkan H-1, dicetak, dibawa ke lokasi:**

- Daftar hadir kertas per fakultas/kelompok: kolom **NIM · Nama · Paraf**, diurut berdasarkan NIM.
- **Empat salinan** (satu per titik presensi — dokumen panitia menyebut 4 meja/scanner, lihat bahan/README.md §4; konfirmasikan ulang dengan user sebelum H-1).
- Pulpen. Papan jalan.

**Kapan dipakai:** koneksi putus > 2 menit, halaman `/scan` error berulang, atau antrean menumpuk
melebihi kemampuan alat.

**Setelah acara:** data kertas dimasukkan lewat `POST /scan/manual` dan tercatat sebagai
`method = manual`, sehingga rekap tetap satu sumber dan bisa diaudit.

Selain itu, **laptop petugas wajib punya tethering HP** sebagai koneksi cadangan.

---

## 7. Setelah Hari-H

| Kapan | Apa |
|---|---|
| H+1 | Ekspor rekap final, serahkan ke panitia. Masukkan data kertas kalau ada |
| H+1 | Backup final disimpan di luar server |
| H+3 | Catat pelajaran: apa yang lambat, apa yang gagal, berapa banyak input manual dipakai |
| H+7 | Bersihkan file Excel unggahan, tinjau `scan_logs` untuk pola titipan absen |
| Akhir tahun akademik | Arsipkan/hapus data pribadi sesuai [`02-data-model.md`](02-data-model.md) §6 |

Fitur yang dipotong (landing penuh, animasi, dashboard grafik) dikerjakan **setelah** acara, dengan
tenang, sebagai persiapan PKKMB berikutnya. Saat itu juga waktu yang tepat memutuskan apakah landing
v1 perlu dibawa masuk ke Next.js sepenuhnya.
