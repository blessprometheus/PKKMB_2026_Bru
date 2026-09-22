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
| Alat presensi | Scanner gun di kampus → halaman scan milik kita | ⚠️ User sebutkan **3**, dokumen panitia sebut **4** meja/komputer/scanner. Scanner berperilaku seperti keyboard. Belum dikonfirmasi ulang |
| Sumber data maba | **Impor file Excel/CSV dari bagian PMB** | Nama kolom **wajib diverifikasi dari file asli** |
| Jumlah peserta | ± 502–550 mahasiswa | |
| Titik presensi | 3 titik paralel | |
| Tim | 1 orang (user) + AI | |
| Bahasa | Bahasa Indonesia (`lang="id-ID"`) | |
| **Hari pelaksanaan** | **Selasa, 29 September 2026** (hari pertama) | Dari dokumen panitia, lihat [`../bahan/README.md`](../bahan/README.md) §4. Hari ke-2 dst. belum ada dokumennya |
| **Registrasi daftar hadir** | **06:30 – 07:15 WIB** — hanya 45 menit | ±550 mahasiswa, 4 titik scan → rata-rata 1 pemindaian per ~20 detik per titik |
| **Lokasi** | Aula UNINUS, Gedung Pascasarjana Lt. 3 | |
| **Tema resmi** | *Berakar pada Nilai, Bertumbuh dalam Ilmu dan Bergerak Membawa Dampak* | |
| Bahan dari panitia | Logo UNINUS, logo & maskot PKKMB, susunan acara hari 1 | Tersimpan di [`../bahan/`](../bahan/README.md) |

---

## Status Proyek

**Fase saat ini: Fase 6 — Hardening & go-live** 🟡 sebagian selesai (22 September 2026)
**Berikutnya: akses VPS dari user, lalu deploy sungguhan**

| Fase | Nama | Status | Exit Criteria |
|---|---|---|---|
| 0 | Dokumentasi & persiapan | ✅ Selesai | `docs/` + `AGENTS.md` lengkap dan disetujui user |
| 1 | Fondasi teknis | 🟡 Sebagian | Repo git jadi; Laravel + Next jalan lokal; PostgreSQL tersambung; migrasi tabel jalan; VPS terpasang Nginx/PHP/Node/Postgres |
| 2 | Data & admin | 🟡 Sebagian | Login admin jalan; impor Excel berhasil dengan file PMB asli; CRUD mahasiswa jalan |
| 3 | Lookup & unduhan maba | 🟡 Sebagian | Cari NIM → tampil data → unduh nametag PDF & QR PNG; rate limit aktif |
| 4 | Presensi | 🟡 Sebagian | Halaman scan jalan dengan scanner gun nyata; kehadiran tercatat; duplikat ditolak; laporan & ekspor jalan |
| 5 | Landing page | 🟡 Sebagian | Halaman informasi tampil benar & responsif; SEO/OG terpasang |
| 6 | Hardening & go-live | 🟡 Sebagian | `security-review` + `ship-gate` lolos; uji end-to-end dengan 3 scanner; backup & rencana cadangan siap; deploy produksi |

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
- ✅ **Logo UNINUS & PKKMB di nametag** — diselesaikan 22 Sep 2026 setelah D5 diterima. Lihat
  [`../bahan/README.md`](../bahan/README.md) §3.
- ⬜ Pas foto di nametag (mitigasi L2 titipan absen) — bergantung ketersediaan foto dari PMB.

### Catatan hasil Fase 4 (22 September 2026)

**Total test naik jadi 63 (251 assertion, seluruhnya lolos).**

- **`POST /api/v1/scan`** dan **`POST /api/v1/scan/manual`** — inti presensi. Diuji: pemindaian
  pertama → `recorded`; pemindaian kedua orang yang sama → `duplicate` dengan jam pemindaian
  sebelumnya; **10 tembakan beruntun untuk orang yang sama tetap menghasilkan satu baris** (meniru
  petugas panik menembak berkali-kali karena layar terlambat berubah); token tidak dikenal →
  `unknown_token`; sesi di luar jendela waktu → `session_closed` dengan pesan yang membedakan
  "belum dibuka" dari "sudah ditutup".
- **Duplikat dicegah oleh `INSERT ... ON CONFLICT DO NOTHING` di level database**, bukan
  `if`/`exists()` di PHP — dibuktikan lewat 10 pemindaian beruntun yang tetap menghasilkan
  satu baris, mensimulasikan beberapa scanner menembak nyaris bersamaan.
- **Input manual NIM** sebagai cadangan (M10) — diuji jalur berhasil maupun NIM tidak ditemukan.
- **Setiap hasil pemindaian, termasuk yang ditolak, tercatat di `scan_logs`** dengan **hash**
  input mentah, bukan token polos — dibuktikan lewat pengecekan langsung terhadap kolomnya.
- **Sesi presensi**: CRUD + endpoint `activate` yang menonaktifkan sesi lain secara otomatis,
  supaya hanya satu sesi yang bisa menerima pemindaian pada satu waktu.
- **Rekap & ekspor Excel**: menyertakan mahasiswa yang **belum hadir** juga. **Penetralan formula
  dibuktikan langsung**: nilai `=cmd|calc`, `+1+1`, `-SUM(A1)` diperiksa di berkas `.xlsx` hasil
  ekspor — seluruhnya tersimpan bertipe string dengan awalan petik tunggal, sehingga Excel tidak
  akan pernah menjalankannya sebagai formula di komputer panitia.
- **Statistik dasbor**: total/hadir/belum-hadir, sebaran per fakultas, dan laju kedatangan per
  10 menit — dibuat untuk jendela registrasi yang cuma 45 menit
  ([`../bahan/README.md`](../bahan/README.md) §4), supaya panitia bisa melihat kalau antrean menumpuk.
- **Halaman `/scan`**: input tersembunyi (`-webkit-text-security`) agar token tak terbaca dari
  layar yang menghadap antrean, fokus otomatis kembali tiap 700 ms, pengiriman terpicu jeda 80 ms
  tanpa ketikan baru (bukan hanya menunggu Enter), penolakan tembakan identik dalam 1,5 detik, bunyi
  berbeda untuk tiga hasil, indikator "Terhubung" yang berubah merah seketika saat gagal, dan mode
  input NIM manual. **Sengaja tanpa animasi, tanpa Lenis, tanpa GSAP.**
- **Halaman admin baru**: `/admin/sesi` (kelola sesi, prasetel dari jadwal panitia) dan
  `/admin/presensi` (statistik + rekap + tombol unduh Excel).

**Diuji dengan menjalankan sungguhan lewat rantai penuh** (bukan hanya test otomatis): login
operator → scan token pertama kali → `recorded` → scan token yang sama lagi → `duplicate` dengan jam
yang benar → scan manual NIM lain → `recorded` → token acak → `unknown_token`. Lalu login admin →
statistik dasbor cocok (2 hadir, sebaran per fakultas benar) → rekap menampilkan metode `qr` dan
`manual` dengan benar → unduh Excel → dibuka ulang dengan PhpSpreadsheet → berisi kolom BELUM HADIR
untuk mahasiswa ketiga yang sengaja belum dipindai.

**Dua bug serius ditemukan test dan sudah diperbaiki — keduanya soal zona waktu:**

1. `config/app.php` bawaan Laravel menulis `'timezone' => 'UTC'` **hardcoded** — `APP_TIMEZONE` di
   `.env` tidak pernah terbaca. Sekarang membaca `env('APP_TIMEZONE', 'UTC')`.
2. Setelah diperbaiki, muncul pergeseran **15 jam** yang justru lebih aneh. Penyebabnya:
   `SET TIME ZONE '+07:00'` di PostgreSQL memakai **konvensi POSIX yang tandanya terbalik** —
   `+07:00` berarti UTC−7, bukan UTC+7. Diganti ke nama zona IANA (`Asia/Jakarta`), yang tidak
   ambigu. Ditambahkan test regresi (`test_stempel_waktu_tidak_bergeser_antara_aplikasi_dan_database`)
   supaya ini tidak terulang secara diam-diam.
   Konsekuensi di hari-H kalau ini tidak ketahuan: sesi presensi akan salah dianggap
   ditutup/belum dibuka pada jam yang sebenarnya benar, dan **seluruh pemindaian ditolak**.
3. Bug kecil ketiga: dua pemindaian pada detik yang sama membuat urutan "10 pemindaian terakhir"
   tidak deterministik. Ditambahkan `orderByDesc('id')` sebagai pemecah seri.

**Belum dikerjakan di Fase 4:**

- ⬜ **Uji dengan scanner gun sungguhan.** Semua pengujian di atas memakai token yang dikirim
  langsung lewat API — *belum* diuji dengan alat fisik yang mengetik ke kolom input, termasuk
  perilaku sufiks Enter/tanpa Enter yang disebutkan di [`05-frontend-spec.md`](05-frontend-spec.md)
  §4.1. **Ini bukan opsional** — lihat peringatan di bawah tabel fase.
- ⬜ Antrean offline / ketahanan saat koneksi putus (S4).
- ⬜ Cetak daftar hadir kertas cadangan (bagian dari runbook H-1, bukan kode).
- ⬜ Endpoint kelola akun panitia (masih lewat `php artisan pkkmb:create-admin`).

### Catatan hasil Fase 5 (22 September 2026)

**Landing page satu halaman jalan** di `/`, memakai seluruh fakta yang sudah terverifikasi dari
[`../bahan/README.md`](../bahan/README.md) §4 — tanpa satu pun data karangan.

- **Delapan section**: Hero (dengan hitung mundur ke registrasi), Informasi, Jadwal, Cari Data
  (embed komponen Fase 3), Tata Tertib, Video, FAQ, Kontak. Empat yang datanya belum diterima
  (Informasi, Tata Tertib, Video, Kontak — D9–D11) memakai `<TodoSection>`: kotak putus-putus
  bertuliskan apa yang masih ditunggu, **bukan konten karangan dan bukan disembunyikan**.
- **Jadwal** menampilkan tiga segmen publik dari dokumen panitia (registrasi 06:30–07:15, masuk
  ruang sidang, sidang terbuka 08:00–09:37). **Sengaja tidak memuat** koreografi lighting/audio/nama
  petugas dari dokumen aslinya — itu bertanda **CONFIDENTIAL** dan bukan konsumsi publik
  ([`../bahan/README.md`](../bahan/README.md) §2).
- **SEO**: `metadataBase`, OG/Twitter tags, dan **JSON-LD `schema.org/Event`** dengan `startDate`/
  `endDate`/lokasi yang seluruhnya diambil dari `src/lib/content.ts` — satu sumber kebenaran teks,
  bukan ditulis berulang di markup.
- **GSAP 3.15 + Lenis 1.3.26 akhirnya terpasang** (dependensinya sudah ada sejak Fase 1). Satu
  provider (`components/animation/LenisProvider.tsx`) + dua komponen pendukung (`Reveal.tsx`,
  `AnchorLink.tsx`) — sesuai janji §8.6: mencabut animasi = hapus folder itu, tanpa menyentuh
  section mana pun.
- Diverifikasi dari sumber: opsi bawaan Lenis 1.3.26 `respectReducedMotion` **tidak** mematikan
  pembajakan wheel/touch, hanya memengaruhi `scrollTo()` terprogram — jadi pengecekan
  `prefers-reduced-motion` manual di `LenisProvider` tetap wajib, sesuai yang sudah dirancang di
  [`05-frontend-spec.md`](05-frontend-spec.md) §8.2.

**Diuji dengan Playwright di browser sungguhan (headless Chromium), viewport 390px — bukan hanya
dibaca dari HTML statis:**

| Diperiksa | Gerak diizinkan | `prefers-reduced-motion: reduce` |
|---|---|---|
| Kelas `lenis` di `<html>` | ✅ ada | ✅ **tidak ada** — Lenis tidak diinisialisasi sama sekali |
| Hitung mundur terisi | ✅ `06 Hari 12 Jam …` | ✅ tetap terisi (bukan animasi, tak terpengaruh) |
| Opacity section FAQ sebelum digulir | `0` (tersembunyi, menunggu animasi) | `1` (**langsung tampil**, tanpa animasi) |
| Opacity setelah digulir ke section | `1` | `1` |
| Klik tautan navbar → gulir ke bagian | ✅ lewat `lenis.scrollTo()` | ✅ jatuh ke anchor bawaan browser |
| Accordion FAQ (`aria-expanded`) | ✅ berfungsi | ✅ berfungsi |
| Galat konsol/halaman | **nihil** | **nihil** |

Hasil ini membuktikan perilaku yang dirancang di [`05-frontend-spec.md`](05-frontend-spec.md) §8.2
benar-benar terjadi, bukan sekadar tertulis di kode: pengguna dengan gangguan vestibular melihat
halaman utuh tanpa gerak sama sekali, bukan konten yang macet dalam keadaan tersembunyi.

**Belum dikerjakan di Fase 5:**

- ⬜ Naskah Informasi PKKMB, Tata Tertib + PDF, tautan video, kontak panitia — menunggu D9–D11.
- ⬜ Banner OG resmi 1200×630 — sementara memakai logo PKKMB (200×200), ditandai `TODO:` di kode.
- ⬜ Domain produksi (D12) — `metadataBase` sementara `localhost:3000` lewat env, tinggal diisi
  `NEXT_PUBLIC_SITE_URL` saat domain final.
- ⬜ Uji Lighthouse (skor performa/aksesibilitas belum diukur formal) dan uji perangkat fisik.
- ⬜ Dark mode (S5) — dikorbankan sesuai batas potong scope, belum ada tanda presensi terlambat.

### Catatan hasil Fase 6 (22 September 2026)

**Belum bisa dianggap selesai — deploy sungguhan butuh akses SSH ke VPS yang belum diberikan.**
Yang dikerjakan adalah semua yang BISA diverifikasi tanpa server, secara jujur mendekati batasnya,
bukan menebak lalu berhenti. Rincian lengkap di [`../deploy/README.md`](../deploy/README.md).

**Ditemukan lewat audit — satu bug otentikasi nyata, sekarang diperbaiki dan dikunci test:**

Permintaan ke endpoint yang butuh login (`/scan`, `/admin/*`) **tanpa header
`Accept: application/json`** (curl polos, Postman default, klien pihak ketiga apa pun) berakhir
**500 mentah** `"Route [login] not defined."`, melewati seluruh format error kustom. Sebabnya:
Laravel 13 SELALU mendaftarkan `redirectGuestsTo(fn () => route('login'))` secara default, dan API
ini tidak punya rute Blade bernama `login`. Diperbaiki satu baris di `bootstrap/app.php`
(`redirectGuestsTo(fn () => null)`), dibuktikan dengan permintaan nyata, dan dikunci 2 test regresi
baru yang sengaja tidak memakai `getJson()`/`postJson()` (keduanya otomatis mengirim header itu,
sehingga 63 test sebelumnya tidak pernah bisa menemukan bug ini). **Total test sekarang 68, 304
assertion, seluruhnya lolos.**

Ditemukan bukan dengan menebak, tapi dengan menjalankan `deploy/scripts/verify-security.sh`
sungguhan terhadap server dev lokal — skrip yang sama juga dipakai nanti terhadap server produksi.

**Dua bug lagi ditemukan saat menulis (bukan menjalankan) `deploy/nginx/pkkmb26.conf`:**
`Connection: upgrade` yang di-hardcode untuk semua permintaan (seharusnya hanya untuk WebSocket,
yang tidak kita pakai — merusak *connection pooling*), dan `proxy_cache_valid` tanpa zona
`proxy_cache` aktif (directive tanpa efek). Keduanya diperbaiki sebelum sempat dipakai.

**Siap pakai, sudah divalidasi sebatas mungkin tanpa VPS:**

- `.env.example` (backend & frontend) ditulis ulang total — sebelumnya masih bawaan scaffold
  Laravel (`APP_DEBUG=true`, SQLite) yang berbahaya kalau dipakai sebagai dasar deploy.
- `deploy/nginx/pkkmb26.conf` — sintaks **divalidasi dengan `crossplane`** (pengurai Nginx resmi,
  Python) di dalam konteks `http{}` tiruan; struktur `location` bersarang terbukti benar.
  `nginx -t` sungguhan **belum** dijalankan (WSL tersedia tapi `sudo` butuh kata sandi interaktif).
- `deploy/scripts/deploy.sh`, `verify-security.sh`, `backup/*.sh` — `bash -n` lolos;
  `verify-security.sh` sudah **dijalankan sungguhan** (lihat temuan di atas).
- `deploy/pm2/ecosystem.config.js` — `node -c` lolos.
- Audit dependensi: `composer audit` dan `npm audit` — **nihil kerentanan CVE** di kedua sisi.
- Verifikasi git history: `.env` **tidak pernah** masuk commit; tidak ada kata sandi polos di
  history manapun.
- Dua celah checklist yang belum teruji ditemukan dan ditutup: batas **40/jam** pada lookup
  (sebelumnya hanya 10/menit yang diuji — dibuktikan independen dari batas per-menit lewat
  simulasi 4 jendela waktu dengan `travel()`), dan **ukuran berkas impor melebihi 10 MB** (sekarang
  diuji, termasuk batas tepatnya tidak off-by-one).
- Log aplikasi diperiksa manual — tidak ada NIM/nama yang tercatat.

**Blocker nyata untuk menyelesaikan Fase 6** (lihat tabel lengkap di
[`../deploy/README.md`](../deploy/README.md)):

1. **Kredensial SSH ke VPS** dari Anda — tanpa ini tidak ada satu pun langkah deploy sungguhan.
2. `nginx -t` di lingkungan Linux nyata.
3. Versi & path socket PHP-FPM sesungguhnya di server (`pkkmb26.conf` masih menebak `php8.3-fpm.sock`).
4. Domain final (D12).
5. `verify-security.sh` terhadap domain HTTPS sungguhan — 5 pemeriksaan yang gagal di dev lokal
   (HSTS, header keamanan) **harus** lolos di server nyata.
6. Backup dipulihkan sekali secara manual (checklist butir 16 — tidak bisa diotomasi).
7. `nmap` port 5432 dari komputer lain (checklist butir 14).

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
