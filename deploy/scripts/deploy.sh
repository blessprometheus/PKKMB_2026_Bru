#!/usr/bin/env bash
#
# Deploy PKKMB UNINUS 2026 ke server. Lihat docs/06-deployment.md §5.
#
# CARA PAKAI:
#   Deploy pertama kali:  ./deploy.sh --pertama-kali
#   Deploy berikutnya:    ./deploy.sh
#
# Skrip ini TIDAK memasang paket sistem (Nginx/PHP/Node/PostgreSQL/certbot)
# atau membuat database — itu langkah satu kali yang punya konsekuensi besar
# kalau salah (lihat docs/06-deployment.md §5 langkah 1-2), jadi sengaja
# dibiarkan manual, bukan diotomasi diam-diam oleh skrip ini.
#
# ATURAN KERAS (docs/07-timeline.md §runbook): JANGAN jalankan skrip ini
# pada hari-H. Pembekuan kode dimulai H-1 sore.
set -euo pipefail

APP_DIR="${PKKMB_APP_DIR:-/var/www/pkkmb26}"
PERTAMA_KALI=false

for arg in "$@"; do
    case "$arg" in
        --pertama-kali) PERTAMA_KALI=true ;;
        *) echo "Argumen tidak dikenal: $arg" >&2; exit 1 ;;
    esac
done

cd "$APP_DIR"

echo "==> Menarik kode terbaru dari git"
git pull

echo "==> Memasang dependensi backend"
cd "$APP_DIR/backend"
composer install --no-dev --optimize-autoloader

if [ "$PERTAMA_KALI" = true ]; then
    if [ ! -f .env ]; then
        echo "GAGAL: backend/.env belum ada. Salin dari .env.example, isi nilai" >&2
        echo "sebenarnya (DB_PASSWORD, APP_URL, dll), lalu jalankan ulang." >&2
        exit 1
    fi

    echo "==> Membuat APP_KEY (hanya sekali)"
    php artisan key:generate --force
fi

# php artisan down HANYA untuk migrasi yang mengubah struktur tabel. Untuk
# migrasi yang cuma menambah tabel baru (seperti kebanyakan kasus proyek ini),
# downtime tidak perlu — tapi berjaga-jaga tetap lebih aman daripada permintaan
# menabrak skema yang sedang berubah.
echo "==> Mode perawatan aktif"
php artisan down --retry=15 || true

echo "==> Menjalankan migrasi database"
php artisan migrate --force

echo "==> Menyegarkan cache konfigurasi & rute"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Mode perawatan nonaktif"
php artisan up

echo "==> Memasang dependensi & build frontend"
cd "$APP_DIR/frontend"
npm ci
npm run build

echo "==> Memuat ulang proses Next.js (PM2)"
if [ "$PERTAMA_KALI" = true ]; then
    pm2 start "$APP_DIR/deploy/pm2/ecosystem.config.js"
    pm2 save
else
    pm2 reload pkkmb-web
fi

echo ""
echo "==> Selesai. Langkah manual yang TIDAK dilakukan skrip ini:"
if [ "$PERTAMA_KALI" = true ]; then
    echo "    - php artisan pkkmb:create-admin   (buat akun admin pertama)"
    echo "    - Pasang konfigurasi Nginx (deploy/nginx/pkkmb26.conf), nginx -t, reload"
    echo "    - certbot --nginx -d <domain>"
fi
echo "    - Jalankan checklist docs/04-security.md §11 sebelum dipakai data asli."
