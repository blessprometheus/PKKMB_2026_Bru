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

**Fase saat ini: Fase 3 — Lookup & unduhan maba** 🟡 sebagian selesai (22 September 2026)
**Berikutnya: Fase 4 — Presensi**

| Fase | Nama | Status | Exit Criteria |
|---|---|---|---|
| 0 | Dokumentasi & persiapan | ✅ Selesai | `docs/` + `AGENTS.md` lengkap dan disetujui user |
| 1 | Fondasi teknis | 🟡 Sebagian | Repo git jadi; Laravel + Next jalan lokal; PostgreSQL tersambung; migrasi tabel jalan; VPS terpasang Nginx/PHP/Node/Postgres |
| 2 | Data & admin | 🟡 Sebagian | Login admin jalan; impor Excel berhasil dengan file PMB asli; CRUD mahasiswa jalan |
| 3 | Lookup & unduhan maba | 🟡 Sebagian | Cari NIM → tampil data → unduh nametag PDF & QR PNG; rate limit aktif |
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

### Catatan hasil Fase 2 (22 September 2026)

**Backend selesai dan terbukti lewat 32 test otomatis (121 assertion, seluruhnya lolos):**

- **Autentikasi:** login/logout/me dengan Sanctum cookie SPA. Terbukti: pesan gagal seragam antara
  email terdaftar & tidak terdaftar, akun nonaktif ditolak, rate limit 5/menit per IP, penguncian akun
  setelah 8 kegagalan beruntun.
- **Pembatasan peran:** operator mendapat **403** saat membuka daftar mahasiswa dan impor — diuji,
  bukan sekadar disembunyikan di UI.
- **CRUD mahasiswa:** tambah/ubah/hapus, pencarian nama & NIM (`ILIKE`, tidak peduli huruf besar-kecil),
  filter fakultas/kelompok/kehadiran, paginasi dibatasi 100.
- **Impor Excel/CSV** (PhpSpreadsheet 5.10). Terbukti: kolom wajib hilang → **seluruh impor dibatalkan**
  dengan pesan menyebut apa yang dicari & apa yang ditemukan; baris gagal dilaporkan dengan **nomor baris
  Excel** sementara baris lain tetap masuk; NIM ganda dalam satu berkas ditolak; **impor ulang tidak
  mengubah `attendance_token`** sehingga nametag yang sudah dicetak tetap sah; NIM berawalan nol tidak
  kehilangan nolnya; berkas bukan Excel yang dinamai `.xlsx` ditolak.
- **Jejak audit:** perubahan/penghapusan tercatat, dan **hanya nama kolom** yang berubah — bukan nilainya,
  karena nilai lama/baru adalah data pribadi.
- Pesan validasi Bahasa Indonesia (`lang/id/`).

**Dua bug ditemukan oleh test dan sudah diperbaiki:**

1. Hash boneka untuk menyamakan waktu respons login bukan bcrypt yang sah → `Hash::check` melempar
   dan login berakhir **500**. Diganti hash bcrypt sungguhan.
2. Permintaan dari domain di luar `SANCTUM_STATEFUL_DOMAINS` berakhir **500 "Session store not set"**.
   Sekarang mengembalikan **400 dengan pesan yang menyebut sebabnya** — agar salah konfigurasi ketahuan
   saat deploy, bukan saat hari-H.

**Antarmuka admin (Next.js) — sudah jalan dan diuji end-to-end:**

- `/admin/login`, `/admin` (dasbor), `/admin/mahasiswa` (tabel + pencarian *debounce* 400 ms +
  paginasi), `/admin/mahasiswa/impor` (unggah + laporan baris gagal).
- **Same-origin juga di lokal.** `next.config.ts` me-*rewrite* `/api/*` dan `/sanctum/*` ke Laravel,
  sehingga browser hanya berbicara ke satu origin — persis seperti produksi di balik Nginx, dan
  **CORS tidak perlu dilonggarkan sama sekali** ([`04-security.md`](04-security.md) §7).
- Penjaga rute memakai **`proxy.ts`** (Next.js 16; `middleware.ts` sudah tidak berlaku).
  Ia hanya memeriksa keberadaan cookie sesi — pengamanan sebenarnya tetap di Laravel.
- Token warna UNINUS terpasang sebagai token Tailwind 4 di `globals.css`.

**Diuji dengan menjalankan sungguhan (22 September 2026), bukan hanya lewat test:**
`/sanctum/csrf-cookie` → 204 · login kata sandi salah → 422 dengan pesan seragam · login benar → 200 ·
`/auth/me` dengan cookie sesi → 200 · unggah `.xlsx` berisi 3 baris (1 sengaja rusak) → 2 masuk,
1 dilaporkan sebagai **baris 4, kolom `name`** · stempel waktu `+07:00` (Asia/Jakarta) benar.

**Temuan keamanan dari uji itu, sudah dicatat di [`04-security.md`](04-security.md) §7:**
`Referrer-Policy: no-referrer` **akan mematahkan autentikasi**. Browser tidak mengirim `Origin` pada
`GET` same-origin, jadi Sanctum bergantung pada `Referer`; tanpa keduanya, panitia yang sudah masuk
tetap mendapat 401.

**Belum dikerjakan di Fase 2:**

- ⬜ **Verifikasi peta kolom dengan file Excel PMB asli (D1).** Peta di `backend/config/pkkmb.php`
  masih rancangan. Ini blocker yang tersisa untuk menyatakan Fase 2 selesai.
  Per 22 September 2026 user memberi kabar: data mahasiswa sudah ada **tetapi belum bernomor NIM**,
  dan file ber-NIM masih ditunggu. Kolom `nim` **tetap wajib dan unik** — tidak dilonggarkan, karena
  data yang masuk tanpa NIM hanya bisa dicocokkan lewat nama, dan nama kembar di 550 mahasiswa
  hampir pasti ada.
- ⬜ Form tambah/ubah mahasiswa di UI (endpoint-nya sudah ada dan teruji).
- ⬜ `GET /admin/students/export` dan `GET /admin/import-batches/{id}/errors.xlsx`
  ([`03-api-spec.md`](03-api-spec.md) §4.1–4.2) — ekspor Excel, termasuk penetralan formula
  ([`04-security.md`](04-security.md) §5.3).
- ⬜ `POST /admin/admins` dkk. (kelola akun panitia) — sementara akun dibuat lewat
  `php artisan pkkmb:create-admin`.

### Catatan hasil Fase 3 (22 September 2026)

**Total test naik jadi 46 (189 assertion, seluruhnya lolos).**

- **`POST /api/v1/lookup`** — pencarian NIM tanpa login, dengan rate limit berlapis
  (10/menit **dan** 40/jam per IP), pencatatan percobaan ber-hash, dan pesan 404 seragam.
- **Minimisasi field terbukti lewat test**: `phone`, `email`, `birth_date`, dan `attendance_token`
  tidak muncul di respons publik — diperiksa terhadap isi respons mentah, bukan sekadar strukturnya.
- **Nametag PDF** (endroid/qr-code 6.0 + dompdf 3.1). Ukuran halaman diverifikasi
  **105,0 × 148,0 mm** (A6) dari `MediaBox` PDF hasil render. QR menyatu di dalamnya — mitigasi L1
  terhadap titipan absen ([`04-security.md`](04-security.md) §3.2). Nama panjang dikecilkan otomatis,
  tidak dipotong.
- **QR PNG 760 × 760 px**, error correction Quartile, quiet zone 80 px.
- **Unduhan bertanda tangan, kedaluwarsa 15 menit.** Diuji: tanpa tanda tangan → 403, tanda tangan
  diutak-atik → 403, lewat 16 menit → 403, dan **menukar id mahasiswa di URL → 403**.
- **Test paling menentukan:** QR hasil unduhan **didekode ulang** dan isinya terbukti persis sama
  dengan `attendance_token` di database — bukan NIM. Kalau isi QR salah, seluruh alur presensi
  hari-H gagal, dan itu baru ketahuan saat 550 orang sudah mengantre.
- **Halaman publik** `/` dengan kotak cari NIM, status kehadiran per sesi, dan dua tombol unduh.

**Bug yang ditemukan test dan sudah diperbaiki:** penangan error global menelan `HttpResponseException`
— kelas yang dipakai Laravel untuk membawa respons yang sudah jadi, termasuk dari rate limiter
bernama. Akibatnya 429 berubah menjadi **500 berpesan kosong**. Sekarang dilewatkan apa adanya.

**Temuan menguntungkan:** rewrite Next meneruskan header `Host`, sehingga URL bertanda tangan yang
dibuat Laravel ikut memakai origin frontend. Unduhan pun tetap same-origin, baik di lokal maupun
produksi.

**Belum dikerjakan di Fase 3:**

- ⬜ Cetak nametag massal 4-up A4 untuk panitia (S3, opsional).
- ⬜ Logo UNINUS di nametag — menunggu D5. **Jangan memasang lambang karangan** di dokumen resmi
  universitas; tata letaknya sudah menyediakan ruang.
- ⬜ Pas foto di nametag (mitigasi L2 titipan absen) — bergantung ketersediaan foto dari PMB.

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
