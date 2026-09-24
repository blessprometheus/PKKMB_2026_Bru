# Deploy ke VPS aaPanel — Langkah demi Langkah

## ⚠️ Kondisi nyata server (diperiksa 24 September 2026) — baca ini dulu

Langkah di bawah ditulis **sebelum** server bisa diperiksa. Pemeriksaan lewat SSH menemukan hal yang
mengubah caranya; yang **benar-benar dipasang** adalah:

| Hal | Rancangan di bawah | Yang terpasang & alasannya |
|---|---|---|
| Sifat server | server khusus | **Server produksi bersama** (uninus.ac.id, SSO, keuangan, HR, dst). Hanya milik PKKMB yang boleh disentuh |
| PHP | 8.3 | **8.4.23** (satu-satunya yang ada). 69 test lolos di PHP ini, di server |
| PostgreSQL | paket OS, port 5432 | **Container Docker `pkkmb26-postgres`** (postgres:17-alpine), `127.0.0.1:5433`, `scram-sha-256`. PostgreSQL aaPanel di 5432 memakai `trust` untuk semua koneksi lokal — proses mana pun dari situs lain bisa masuk sebagai superuser tanpa kata sandi. Rahasia DB: `/root/pkkmb26/db.env` (600) |
| Domain & SSL | Let's Encrypt lewat panel | **Diproksi Cloudflare.** Memakai Cloudflare Origin Certificate `*.uninus.ac.id` yang sudah ada di `/www/server/panel/vhost/cert/uninus.ac.id/` |
| IP pengunjung | — | `real_ip_header CF-Connecting-IP` dari rentang Cloudflare — tanpa ini rate limit per IP salah sasaran |
| Vhost | dibuat lewat panel | Berkas manual [`nginx/pkkmb26.aapanel.conf`](nginx/pkkmb26.aapanel.conf) → `/www/server/panel/vhost/nginx/pkkmb.uninus.ac.id.conf` |
| `disable_functions` | diubah | **Tidak diubah** — berlaku global. CLI tanpa batasan, FPM tidak butuh |
| Backup | `pg_dump` di PATH | `PKKMB_DB_PORT=5433 PKKMB_PG_DUMP=/www/server/pgsql/bin/pg_dump` |

Server: **103.74.5.229**, dikelola lewat **aaPanel**. Repo publik:
`https://github.com/blessprometheus/PKKMB_2026_Bru`.

Panduan ini menggantikan langkah Ubuntu-polos di [`../docs/06-deployment.md`](../docs/06-deployment.md) §5
untuk server ber-aaPanel. aaPanel mengelola Nginx, PHP, dan SSL sendiri; konfigurasi yang diubah di luar
panel bisa tertimpa saat panel menyimpan pengaturan. Karena itu **Nginx, PHP, dan SSL diurus lewat panel**,
sisanya lewat Terminal.

> **Ganti `pkkmb.uninus.ac.id` di seluruh panduan ini** kalau domain finalnya berbeda (D12).
>
> **Jalankan perintah sebagai `root`** — di aaPanel: menu **Terminal**.
>
> **Jangan impor data mahasiswa asli** sebelum langkah 11 (verifikasi) lolos.
>
> ⚠️ Beberapa lokasi berkas di bawah adalah **bawaan aaPanel yang umum**, bukan hasil pemeriksaan server
> ini. Setiap langkah punya perintah cek — kalau hasilnya berbeda, berhenti dan laporkan dulu.

---

## 0. Sebelum mulai

**a. DNS.** Minta Tim IT UNINUS membuat record **A**: `pkkmb.uninus.ac.id → 103.74.5.229`.
SSL (langkah 8) baru bisa setelah ini aktif. Cek dari laptop:

```bash
nslookup pkkmb.uninus.ac.id
```

**b. Periksa server.** Jalankan lalu **simpan hasilnya** (kirim ke Claude kalau ada yang janggal):

```bash
cat /etc/os-release | head -3
nproc; free -h; df -h /
nginx -v 2>&1; php -v | head -1; node -v; composer -V; psql --version
ss -tlnp
ls /www/server/panel/vhost/nginx/
```

`ss -tlnp` menunjukkan layanan apa saja yang sudah jalan. **Kalau di server ini sudah ada situs lain,**
jangan mengubah pengaturan global Nginx/PHP selain yang disebut panduan ini.

Panduan ini ditulis untuk **Ubuntu/Debian**. Kalau OS-nya CentOS/AlmaLinux, perintah `apt` di langkah 3–4
berbeda — berhenti dan tanyakan dulu.

---

## 1. Nginx & PHP 8.3 (lewat aaPanel)

1. **App Store** → pasang **Nginx** (kalau belum) dan **PHP-8.3**.
   Laravel 13 butuh PHP ≥ 8.3 (`backend/composer.json`).
2. **App Store → PHP-8.3 → Setting → Install extensions** → pasang: `fileinfo`, `intl`, `opcache`,
   dan **`pgsql` / `pdo_pgsql`** kalau ada di daftar.
3. **JANGAN ubah "Disabled functions".** Pengaturan itu berlaku untuk **semua situs** di server
   (server ini menjalankan uninus.ac.id, SSO, keuangan, dll). Diperiksa 24 Sep 2026: PHP **CLI** tidak
   mematikan fungsi apa pun, jadi Composer & artisan tetap jalan; PHP-FPM tidak membutuhkan `putenv`/
   `proc_open` selama konfigurasi di-cache (`config:cache`, dijalankan `deploy.sh`).
4. Cek dari Terminal:

```bash
php -v | head -1                    # harus 8.3.x
php -m | grep -iE '^(pgsql|pdo_pgsql|mbstring|gd|zip|intl|fileinfo)$'
ls /tmp/php-cgi-83.sock             # socket PHP-FPM aaPanel
```

Harus muncul **tujuh** ekstensi. **Kalau `pgsql`/`pdo_pgsql` tidak ada, berhenti** — aplikasi tidak
bisa bicara ke PostgreSQL tanpanya. Laporkan, jangan dilewati.

Kalau `php -v` bukan 8.3: **Settings** aaPanel → **PHP CLI version** → pilih 8.3.

## 2. Composer

```bash
composer -V || {
  cd /tmp
  EXPECTED="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  ACTUAL="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
  if [ "$EXPECTED" = "$ACTUAL" ]; then
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
  else
    echo 'GAGAL: checksum installer Composer tidak cocok'
  fi
  rm -f composer-setup.php
}
```

## 3. Node.js + PM2

Next.js 16 butuh Node ≥ 20.9. Pakai versi LTS:

```bash
node -v || { curl -fsSL https://deb.nodesource.com/setup_24.x | bash - && apt install -y nodejs; }
node -v
npm install -g pm2
```

## 4. PostgreSQL

Dipasang dari paket OS (bukan plugin panel), supaya `pg_dump` untuk backup ada di PATH dan versinya
cocok dengan server database.

```bash
apt install -y postgresql
systemctl enable --now postgresql
psql --version
```

Buat user aplikasi **non-superuser** dan databasenya. Kata sandi **diketik saat diminta**, bukan
ditulis di baris perintah (supaya tidak tersimpan di riwayat shell):

```bash
openssl rand -base64 24          # salin hasilnya — ini DB_PASSWORD
sudo -u postgres createuser --pwprompt pkkmb26_app
sudo -u postgres createdb -O pkkmb26_app pkkmb26
```

Pastikan PostgreSQL **hanya** mendengarkan localhost:

```bash
ss -tlnp | grep 5432              # harus 127.0.0.1:5432, BUKAN 0.0.0.0:5432
```

## 5. Ambil kode

Lokasi `/var/www/pkkmb26` sengaja dipakai (bukan `/www/wwwroot/`) karena `deploy.sh`,
`ecosystem.config.js`, dan skrip backup sudah mengarah ke sana.

```bash
git clone https://github.com/blessprometheus/PKKMB_2026_Bru.git /var/www/pkkmb26
mkdir -p /var/log/pkkmb26
```

## 6. Konfigurasi `.env`

**Backend:**

```bash
cd /var/www/pkkmb26/backend
cp .env.example .env
chmod 640 .env && chown root:www .env
nano .env
```

Yang **wajib** diisi/diperiksa:

| Variabel | Nilai |
|---|---|
| `APP_URL` | `https://pkkmb.uninus.ac.id` |
| `APP_DEBUG` | `false` — **jangan pernah** `true` di server ini |
| `DB_PASSWORD` | hasil `openssl rand` di langkah 4 |
| `FRONTEND_URL` | `https://pkkmb.uninus.ac.id` |
| `SANCTUM_STATEFUL_DOMAINS` | `pkkmb.uninus.ac.id` (tanpa `https://`) |
| `APP_KEY` | **biarkan kosong** — diisi otomatis oleh `deploy.sh` |

**Frontend** (variabel `NEXT_PUBLIC_*` ditanam saat build, jadi harus ada **sebelum** langkah 7):

```bash
cat > /var/www/pkkmb26/frontend/.env.production <<'EOF'
NEXT_PUBLIC_SESSION_COOKIE=pkkmb_session
NEXT_PUBLIC_SITE_URL=https://pkkmb.uninus.ac.id
EOF
```

## 7. Pasang aplikasi

```bash
cd /var/www/pkkmb26
export COMPOSER_ALLOW_SUPERUSER=1 PKKMB_WEB_USER=www
bash deploy/scripts/deploy.sh --pertama-kali
```

Skrip ini: `composer install`, membuat `APP_KEY`, migrasi database, cache konfigurasi, `npm ci` +
`npm run build`, menjalankan Next.js lewat PM2, dan menyerahkan `storage/` ke user `www`.
**Kalau berhenti dengan error, jangan diakali — salin pesan errornya.**

Supaya Next.js hidup lagi setelah server reboot:

```bash
pm2 startup        # jalankan perintah yang dicetaknya
pm2 save
```

Cek:

```bash
pm2 status                                     # pkkmb-web: online
ss -tlnp | grep 3000                           # harus 127.0.0.1:3000, BUKAN 0.0.0.0
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3000/    # 200
```

## 8. Situs di aaPanel + SSL

1. **Website → Add site**
   - Domain: `pkkmb.uninus.ac.id`
   - Root directory: `/var/www/pkkmb26/backend/public`
   - Database: **tidak usah** (kita pakai PostgreSQL, bukan MySQL panel)
   - PHP version: **PHP-8.3**
2. Buka situs itu → **Site directory** → **matikan "Anti-XSS attack (open_basedir)"**.
   Kalau tetap menyala, Laravel tidak bisa membaca `../vendor` dan semua `/api` berakhir 500.
3. **SSL → Let's Encrypt** → pilih domain → Apply. Setelah terbit, nyalakan **Force HTTPS**.
   **Jangan** nyalakan toggle HSTS panel dulu (lihat langkah 9 poin 4).

## 9. Konfigurasi Nginx situs

**Website → situs → Config file.** Sebelum mengubah, **salin seluruh isinya ke Notepad** sebagai cadangan.

**a. Hapus** dua blok bawaan aaPanel ini (kalau ada):

```nginx
location ~ .*\.(gif|jpg|jpeg|png|bmp|swf)$ { ... }
location ~ .*\.(js|css)?$ { ... }
```

Blok regex itu **mengalahkan** `location /` dan mencari berkas di `backend/public`. Akibatnya seluruh
JS/CSS/gambar Next.js (`/_next/static/...`, logo) menjadi 404 dan halaman tampil rusak.

**b. Jangan sentuh:** baris `#SSL-START … #SSL-END`, `include enable-php-83.conf;`, dan blok yang
menolak `.env`/`.git`/`.user.ini`.

**c. Tempel** blok berikut **di dalam** `server { … }`, tepat setelah baris `include enable-php-83.conf;`:

```nginx
    # ─── PKKMB26 — lihat deploy/nginx/pkkmb26.conf untuk alasan tiap baris ───
    client_max_body_size 12m;

    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    # JANGAN no-referrer — Sanctum butuh Referer (docs/04-security.md §7)
    add_header Referrer-Policy strict-origin-when-cross-origin always;
    # Report-Only: script-src 'self' ditegakkan akan mematikan hidrasi Next.js
    add_header Content-Security-Policy-Report-Only "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-src https://www.youtube-nocookie.com; object-src 'none'; base-uri 'self'" always;

    # API Laravel — diteruskan ke index.php, yang ditangani enable-php-83.conf
    location ^~ /api/     { try_files $uri /index.php?$query_string; }
    location ^~ /sanctum/ { try_files $uri /index.php?$query_string; }

    location ^~ /storage/ { return 404; }

    location ^~ /_next/static/ {
        proxy_pass http://127.0.0.1:3000;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
    # ─── akhir PKKMB26 ───
```

**d. Simpan.** aaPanel menjalankan `nginx -t` saat menyimpan. Kalau muncul
`duplicate location "/"`, buka **URL rewrite** situs itu dan kosongkan isinya, lalu simpan lagi.

**e. HSTS** — **setelah** HTTPS terbukti jalan (langkah 11), tambahkan ke blok tadi:

```nginx
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

Sengaja belakangan: HSTS memaksa browser memakai HTTPS selama setahun. Kalau dipasang saat SSL masih
bermasalah, situs tidak bisa dibuka sampai sertifikatnya benar.

## 10. Akun admin pertama

```bash
cd /var/www/pkkmb26/backend
sudo -u www php artisan pkkmb:create-admin
```

Interaktif — kata sandi diketik, tidak pernah ditulis di berkas mana pun. Minimal 12 karakter untuk
`admin`, 10 untuk `operator` ([`../docs/04-security.md`](../docs/04-security.md) §1).

## 11. Firewall & verifikasi

**aaPanel → Security.** Yang boleh terbuka ke internet hanya: **22** (SSH), **80**, **443**, dan
**port panel (30382)**. Pastikan **5432** dan **3000** tidak ada di daftar. Tutup juga port bawaan
aaPanel yang tidak dipakai (mis. 21 FTP, 888 phpMyAdmin).

**Uji di browser** (`https://pkkmb.uninus.ac.id`):

- [ ] Landing tampil lengkap dengan logo & gaya (bukan HTML polos) — kalau polos, cek langkah 9a
- [ ] `/admin/login` → masuk dengan akun langkah 10 → dasbor tampil
- [ ] DevTools → Console: pesan `Content-Security-Policy-Report-Only` **wajar**; error lain **tidak**

**Uji otomatis dari laptop** (dari folder repo, pakai Git Bash):

```bash
bash deploy/scripts/verify-security.sh https://pkkmb.uninus.ac.id
```

Skrip ini sengaja memicu rate limit — jalankan sekarang, **jangan** saat acara.

**Uji dari komputer lain** (checklist butir 14):

```bash
nmap -p 3000,5432 103.74.5.229        # keduanya harus filtered/closed
```

## 12. Backup

```bash
mkdir -p /opt/pkkmb26 /var/backups/pkkmb26
cp /var/www/pkkmb26/deploy/backup/*.sh /opt/pkkmb26/ && chmod 700 /opt/pkkmb26/*.sh

# Kredensial untuk pg_dump — BUKAN di skrip/crontab
echo '127.0.0.1:5432:*:pkkmb26_app:ISI_DB_PASSWORD' > /root/.pgpass
chmod 600 /root/.pgpass && nano /root/.pgpass      # ganti ISI_DB_PASSWORD

/opt/pkkmb26/backup-db.sh                           # uji sekali, harus sukses
```

Jadwalkan: **aaPanel → Cron → Shell Script**, harian 02:00, isi
`/opt/pkkmb26/backup-db.sh >> /var/log/pkkmb26/backup.log 2>&1`.
Backup 15-menitan (`backup-attendance.sh`) **baru diaktifkan H-1**.

**Wajib sekali: buktikan backup bisa dipulihkan** (checklist butir 16):

```bash
sudo -u postgres createdb -O pkkmb26_app pkkmb26_uji
gunzip -c "$(ls -t /var/backups/pkkmb26/*penuh*.sql.gz | head -1)" \
  | psql -h 127.0.0.1 -U pkkmb26_app pkkmb26_uji
psql -h 127.0.0.1 -U pkkmb26_app pkkmb26_uji -c '\dt'   # 8+ tabel harus ada
sudo -u postgres dropdb pkkmb26_uji
```

---

## Deploy berikutnya

**Tidak boleh pada hari-H** (29 Sep). Pembekuan kode mulai H-1 sore.

```bash
cd /var/www/pkkmb26
export COMPOSER_ALLOW_SUPERUSER=1 PKKMB_WEB_USER=www
bash deploy/scripts/deploy.sh
```

## Kalau ada masalah

| Gejala | Periksa |
|---|---|
| Semua `/api/...` 500 | `tail -50 /var/www/pkkmb26/backend/storage/logs/laravel.log` · open_basedir (langkah 8.2) · kepemilikan `storage/` |
| Halaman tampil tanpa gaya / logo hilang | Blok regex bawaan aaPanel belum dihapus (langkah 9a) |
| Login selalu 400 "SANCTUM_STATEFUL_DOMAINS" | Nilai di `backend/.env` harus persis domain yang dibuka di browser. Setelah ubah `.env`: `php artisan config:cache` |
| Login sukses tapi langsung terlempar keluar | Situs dibuka lewat `http://` — cookie sesi `Secure` hanya terkirim lewat HTTPS |
| 502 Bad Gateway | `pm2 status` / `pm2 logs pkkmb-web --lines 50` |
| Log Nginx | `/www/wwwlogs/pkkmb.uninus.ac.id.log` dan `.error.log` |
