# Artefak Deploy — PKKMB UNINUS 2026

Berkas siap pakai untuk Fase 6 ([`../docs/06-deployment.md`](../docs/06-deployment.md)).
Semuanya sudah **divalidasi sebatas yang bisa dilakukan tanpa akses VPS sungguhan** — lihat
"Yang belum bisa diverifikasi" di bawah sebelum menganggap ini siap pakai membabi buta.

## Isi

| Berkas | Kegunaan |
|---|---|
| [`AAPANEL.md`](AAPANEL.md) | **Mulai dari sini** — langkah deploy untuk server sungguhan (103.74.5.229, aaPanel) |
| [`nginx/pkkmb26.conf`](nginx/pkkmb26.conf) | Konfigurasi Nginx lengkap: redirect HTTPS, header keamanan, split rute API/Next.js |
| [`pm2/ecosystem.config.js`](pm2/ecosystem.config.js) | Menjaga proses Next.js tetap hidup |
| [`backup/backup-db.sh`](backup/backup-db.sh) | Backup harian penuh PostgreSQL |
| [`backup/backup-attendance.sh`](backup/backup-attendance.sh) | Backup cepat tabel kehadiran, tiap 15 menit di hari-H |
| [`backup/crontab.example`](backup/crontab.example) | Jadwal cron untuk kedua skrip backup |
| [`scripts/deploy.sh`](scripts/deploy.sh) | Otomasi langkah deploy dari [`docs/06-deployment.md`](../docs/06-deployment.md) §5 |
| [`scripts/verify-security.sh`](scripts/verify-security.sh) | Menjalankan sebagian checklist [`docs/04-security.md`](../docs/04-security.md) §11 terhadap server live |

## Cara Verifikasi yang Sudah Dilakukan (tanpa VPS)

Karena sesi ini tidak punya kredensial SSH ke VPS Anda, berkas di atas **tidak pernah dijalankan
sungguhan di server produksi**. Yang sudah dilakukan sebagai gantinya:

- **`pkkmb26.conf`**: diurai dengan `crossplane` (pengurai konfigurasi Nginx resmi NGINX Inc,
  Python, tanpa perlu binary Nginx) di dalam konteks `http{}` tiruan yang meniru persis cara Nginx
  Ubuntu menyertakan berkas dari `sites-enabled/`. Sintaksnya **valid** dan struktur `location`
  bersarangnya terbukti benar. WSL tersedia di mesin ini tapi `sudo` butuh kata sandi interaktif
  yang tidak bisa dipenuhi sesi ini, jadi `nginx -t` sungguhan **belum pernah dijalankan** — itu
  wajib jadi langkah pertama begitu Anda punya akses server.
- Dua bug nyata ditemukan **saat menulis** berkas ini (bukan ditebak lalu dibiarkan):
  1. `proxy_set_header Connection "upgrade"` di-hardcode untuk semua permintaan — salah, itu untuk
     WebSocket yang tidak kita pakai, dan justru merusak *connection pooling* ke Next.js. Dihapus.
  2. `proxy_cache_valid` ditulis tanpa zona `proxy_cache` yang aktif — directive itu tanpa efek
     tanpa `proxy_cache_path`. Dihapus; caching statis Next.js mengandalkan header `Cache-Control`
     ke **browser**, cukup untuk skala acara ini.
  3. Kode mati: `deny all;` diikuti `return 404;` pada blok `/storage/` — baris kedua tidak pernah
     tercapai. Disederhanakan jadi `return 404;` saja.
- **`backup-db.sh`**, **`backup-attendance.sh`**, **`deploy.sh`**, **`verify-security.sh`**: diperiksa
  `bash -n` (validasi sintaks). **`verify-security.sh` juga dijalankan sungguhan** terhadap server
  dev lokal (`http://localhost:3000`) — lewat itu ditemukan **bug keamanan nyata** di aplikasi
  (bukan di skrip): lihat §"Temuan dari verify-security.sh" di bawah.
- **`ecosystem.config.js`**: diperiksa `node -c` (sintaks JS valid) dan dibaca ulang terhadap
  dokumentasi PM2 resmi untuk nama opsi (`max_memory_restart`, `min_uptime`, dst).

## Temuan dari `verify-security.sh`: bug otentikasi nyata, sudah diperbaiki

Menjalankan skrip ini terhadap server dev lokal menemukan **bug produksi yang serius**: permintaan
ke endpoint mana pun yang butuh login (`/api/v1/scan`, `/api/v1/admin/*`) **tanpa header
`Accept: application/json`** — yaitu `curl` polos, Postman dengan pengaturan default, atau klien
pihak ketiga mana pun — mendapat **500 mentah** berisi `"Route [login] not defined."`, melewati
seluruh format error `{success, message}` yang sudah dirancang.

**Akar masalah:** `ApplicationBuilder::withMiddleware()` bawaan Laravel 13 **selalu**
mendaftarkan `redirectGuestsTo(fn () => route('login'))` sebelum callback kita di
`bootstrap/app.php` sempat berjalan. API ini murni JSON, tidak punya rute Blade bernama `login`,
sehingga `route('login')` gagal — dan kegagalannya terjadi **saat exception otentikasi sedang
dibentuk**, sebelum penanganan error kustom kita sempat menangkapnya.

**Perbaikan:** `$middleware->redirectGuestsTo(fn () => null);` di `bootstrap/app.php`, menimpa
default itu. Dibuktikan dengan permintaan nyata (dengan dan tanpa header `Accept`) dan dikunci
dengan dua test regresi baru (`AuthTest::test_endpoint_admin_menolak_tamu_tanpa_header_accept`,
`ScanTest::test_pemindaian_tanpa_login_ditolak_meski_tanpa_header_accept`) yang sengaja memakai
helper `get()`/`post()` polos — `getJson()`/`postJson()` yang dipakai 63 test lain **selalu**
menyertakan `Accept: application/json` otomatis, sehingga tidak akan pernah menangkap bug ini.

**Kenapa ini tidak pernah muncul sebelumnya:** frontend kita sendiri (`frontend/src/lib/api.ts`)
selalu mengirim header `Accept: application/json` secara eksplisit, jadi pengguna normal lewat
`/admin` atau `/scan` tidak pernah menabraknya. Bug ini baru muncul saat sistem diuji dari luar
jalur frontend — persis skenario yang dirancang `verify-security.sh` untuk ditemukan.

## Yang BELUM Bisa Diverifikasi Tanpa Akses VPS

Ini bukan daftar "TODO nanti" — ini **blocker nyata** untuk go-live, dan butuh sesuatu dari Anda:

| # | Yang dibutuhkan | Kenapa |
|---|---|---|
| 1 | **Kredensial SSH ke VPS** (host, user, kunci/kata sandi) | Tanpa ini tidak ada satu pun langkah di [`docs/06-deployment.md`](../docs/06-deployment.md) §5 yang bisa benar-benar dijalankan — baru sebatas disiapkan |
| 2 | `nginx -t` sungguhan di server (atau di lingkungan Linux dengan sudo) | Crossplane membuktikan sintaks terurai benar, tapi tidak mensimulasikan resolusi `location` yang sesungguhnya di runtime Nginx asli |
| 3 | Versi PHP-FPM & path socket sesungguhnya di server | `pkkmb26.conf` menebak `php8.3-fpm.sock` — **verifikasi, jangan asumsikan**, sesuai [`../AGENTS.md`](../AGENTS.md) §4 |
| 4 | Domain final (D12) | Seluruh placeholder `GANTI_DOMAIN` perlu diisi |
| 5 | Uji `verify-security.sh` terhadap domain HTTPS sungguhan | Lima pemeriksaan yang gagal di dev lokal (HSTS, header keamanan) HARUS lolos di server nyata sebelum go-live |
| 6 | Backup dipulihkan sekali secara manual | Checklist [`docs/04-security.md`](../docs/04-security.md) §11 butir 16 — tidak bisa diotomasi, harus dibuktikan manusia sekali |
| 7 | `nmap` port 5432 dari komputer LAIN | Membuktikan PostgreSQL benar-benar tidak terbuka ke internet (checklist butir 14) |

**Ringkasnya: begitu Anda memberi akses SSH, langkah berikutnya adalah menjalankan
`deploy.sh --pertama-kali` lalu `verify-security.sh` terhadap URL sungguhan** — bukan menulis
kode baru.
