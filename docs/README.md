# Dokumentasi Proyek — PKKMB UNINUS 2026 (v2)

Folder ini adalah **sumber kebenaran** proyek. Setiap keputusan produk, data, API, keamanan, tampilan,
dan deployment dicatat di sini. Kode mengikuti dokumen — bukan sebaliknya.

Aturan kerja agen AI ada di [`../AGENTS.md`](../AGENTS.md).

---

## Indeks Dokumen

| # | Dokumen | Isi |
|---|---|---|
| 01 | [PRD & Ruang Lingkup](01-prd.md) | Tujuan, aktor, alur inti, scope MUST/SHOULD/WON'T, data yang ditunggu, risiko |
| 02 | [Model Data](02-data-model.md) | Skema PostgreSQL, relasi, indeks, aturan impor Excel |
| 03 | [Spesifikasi API](03-api-spec.md) | Endpoint Laravel, format response, validasi, rate limit |
| 04 | [Keamanan](04-security.md) | Auth admin, data pribadi publik, titipan absen, upload, unduhan, checklist go-live |
| 05 | [Frontend](05-frontend-spec.md) | Rute Next.js, design token, halaman scan, layout nametag, aksesibilitas |
| 06 | [Deployment](06-deployment.md) | Setup VPS, Nginx, PostgreSQL, PM2, SSL, backup, rollback, runbook hari-H |
| 07 | [Timeline & Prioritas](07-timeline.md) | Rencana 7 hari, batas potong scope, rencana cadangan manual |

---

## Ringkasan Keputusan (dikonfirmasi user, 22 September 2026)

| Aspek | Keputusan | Catatan |
|---|---|---|
| Nasib v1 (`Desktop/pkkmb`) | **Ganti total** — v1 tidak dikembangkan lagi | User menerima risikonya secara sadar, lihat [`01-prd.md`](01-prd.md) §2 |
| Frontend | Next.js (App Router) + TypeScript + Tailwind | |
| Animasi | **GSAP + ScrollTrigger** dan **Lenis** (smooth scroll) | Disetujui user 22 Sep 2026. Hanya di halaman publik — dilarang di `/scan` & `/admin`. Lihat [`05-frontend-spec.md`](05-frontend-spec.md) §8 |
| Backend | Laravel, API JSON | |
| Database | PostgreSQL | |
| Hosting | VPS sendiri, akses root SSH sudah ada | |
| Login | **Hanya admin & petugas.** Maba tanpa login | |
| Cara maba dapat data | Masukkan **NIM** di landing page | Lihat risiko di [`04-security.md`](04-security.md) §2 |
| Yang diunduh maba | Nametag + QR presensi | |
| Format barcode | **QR Code** | Scanner kampus jenis 2D/imager, sudah tersedia |
| Alat presensi | 3 scanner gun di kampus → halaman scan milik kita | Scanner berperilaku seperti keyboard |
| Sumber data maba | **Impor file Excel/CSV dari bagian PMB** | Nama kolom **wajib diverifikasi dari file asli** |
| Jumlah peserta | ± 502–550 mahasiswa | |
| Titik presensi | 3 titik paralel | |
| Tim | 1 orang (user) + AI | |
| Bahasa | Bahasa Indonesia (`lang="id-ID"`) | |
| Hari pelaksanaan | ± 29 September 2026 (seminggu dari 22 Sep) | ⚠️ `TODO:` tanggal & jam resmi belum dikonfirmasi |

---

## Status Proyek

**Fase saat ini: Fase 1 — Fondasi teknis** 🟡 sebagian selesai (22 September 2026)
**Berikutnya: selesaikan setup VPS, lalu Fase 2 — Data & admin**

| Fase | Nama | Status | Exit Criteria |
|---|---|---|---|
| 0 | Dokumentasi & persiapan | ✅ Selesai | `docs/` + `AGENTS.md` lengkap dan disetujui user |
| 1 | Fondasi teknis | 🟡 Sebagian | Repo git jadi; Laravel + Next jalan lokal; PostgreSQL tersambung; migrasi tabel jalan; VPS terpasang Nginx/PHP/Node/Postgres |
| 2 | Data & admin | ⬜ Belum | Login admin jalan; impor Excel berhasil dengan file PMB asli; CRUD mahasiswa jalan |
| 3 | Lookup & unduhan maba | ⬜ Belum | Cari NIM → tampil data → unduh nametag PDF & QR PNG; rate limit aktif |
| 4 | Presensi | ⬜ Belum | Halaman scan jalan dengan scanner gun nyata; kehadiran tercatat; duplikat ditolak; laporan & ekspor jalan |
| 5 | Landing page | ⬜ Belum | Halaman informasi tampil benar & responsif; SEO/OG terpasang |
| 6 | Hardening & go-live | ⬜ Belum | `security-review` + `ship-gate` lolos; uji end-to-end dengan 3 scanner; backup & rencana cadangan siap; deploy produksi |

> **Fase 4 tidak boleh dianggap selesai** tanpa uji memakai **scanner gun yang sebenarnya**, bukan
> simulasi ketik manual. Lihat [`07-timeline.md`](07-timeline.md) §4.

### Catatan hasil Fase 1 (22 September 2026)

**Sudah jalan dan diverifikasi langsung, bukan diasumsikan:**

- Repo git dibuat, commit dokumentasi Fase 0 masuk.
- Composer 2.10.3 dipasang dengan **verifikasi SHA-384** installer resminya.
- Laravel **13.32.0** berjalan (`HTTP 200`), `sanctum/csrf-cookie` menjawab `HTTP 204`.
- Next.js **16.3.5** + React 19.2.8 + Tailwind 4: `npm run build` **lolos**, TypeScript bersih.
- PostgreSQL **17.10** tersambung dari Laravel (`artisan db:show` menampilkan database `pkkmb26`).
- Database `pkkmb26` dengan owner `pkkmb26_app` — **bukan superuser**, sesuai
  [`04-security.md`](04-security.md) §8.
- **Seluruh 8 tabel** dari [`02-data-model.md`](02-data-model.md) termigrasi. Diperiksa langsung di
  database: batasan `UNIQUE (student_id, attendance_session_id)`, semua `CHECK` (role, method, result,
  gender), indeks GIN pencarian nama, dan kolom waktu bertipe `timestamptz`.
- Model `Admin` menggantikan `User` bawaan. Diuji: kata sandi ter-hash bcrypt, tidak ikut ter-serialisasi
  ke JSON, dan peran di luar daftar **ditolak database**.
- Perintah `php artisan pkkmb:create-admin` terdaftar — akun dibuat interaktif, tanpa seeder berisi
  kata sandi.
- GSAP **3.15.0** + Lenis **1.3.26** terpasang (keputusan user 22 Sep 2026).

**Belum dikerjakan:**

- ⬜ **Setup VPS** (Nginx, PHP-FPM, Node, PostgreSQL, SSL) — butuh akses SSH ke server dari user.
- ⬜ Provider animasi GSAP/Lenis belum ditulis; itu pekerjaan Fase 5 sesuai
  [`07-timeline.md`](07-timeline.md). Dependensinya sudah siap.

---

## Peringatan Jadwal

Proyek ini dikerjakan **satu orang dalam tujuh hari** dengan stack baru dan tiga modul sekaligus.
Ini di luar batas aman. [`07-timeline.md`](07-timeline.md) memuat urutan kerja, **batas potong scope**,
dan **rencana cadangan manual** kalau sistem belum siap di hari-H. Baca dokumen itu sebelum memutuskan
mengerjakan apa pun yang tidak ada di daftar MUST.

---

## Hubungan dengan Proyek Lain

- **`Desktop/pkkmb` (PKKMB v1):** pendahulu proyek ini, status *diganti*. Dokumennya masih berguna
  sebagai rujukan design system ([`03-design-system.md`](../../pkkmb/docs/03-design-system.md)) dan
  spesifikasi konten landing ([`02-content-spec.md`](../../pkkmb/docs/02-content-spec.md)).
  Token warna sudah disalin ke [`05-frontend-spec.md`](05-frontend-spec.md) §2 agar proyek ini mandiri.
- **Situs utama UNINUS (`Desktop/`, migrasi CMS PHP):** proyek terpisah. PKKMB **tidak** dibangun di
  atas CMS itu. Yang dipinjam hanya identitas brand.
- **`Desktop/05-UIUX.md`:** sumber palet warna & tipografi UNINUS (diverifikasi dari CSS variable
  uninus.ac.id pada 2 Juli 2026). Kalau brand utama berubah, dokumen itulah acuannya.
