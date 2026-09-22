#!/usr/bin/env bash
#
# Backup harian penuh database PostgreSQL. Lihat docs/06-deployment.md §6.
#
# CARA PAKAI:
#   1. Salin ke /opt/pkkmb26/backup-db.sh di server, chmod +x
#   2. Buat berkas kredensial di /root/.pgpass (format: host:port:db:user:password),
#      chmod 600 -- JANGAN taruh password di skrip ini atau di crontab.
#   3. Daftarkan lewat cron (lihat deploy/backup/crontab.example).
#
# Skrip berhenti pada kegagalan pertama (`set -e`) — backup yang gagal diam-diam
# lebih berbahaya daripada backup yang gagal ribut, karena kegagalan diam-diam
# baru ketahuan saat backup itu justru dibutuhkan.
set -euo pipefail

DB_NAME="${PKKMB_DB_NAME:-pkkmb26}"
DB_USER="${PKKMB_DB_USER:-pkkmb26_app}"
DB_HOST="${PKKMB_DB_HOST:-127.0.0.1}"
BACKUP_DIR="${PKKMB_BACKUP_DIR:-/var/backups/pkkmb26}"
SIMPAN_HARI=14

mkdir -p "$BACKUP_DIR"

STEMPEL=$(date +%Y%m%d-%H%M%S)
BERKAS="$BACKUP_DIR/pkkmb26-penuh-$STEMPEL.sql.gz"

echo "[$(date -Iseconds)] Memulai backup penuh -> $BERKAS"

# Kredensial diambil dari ~/.pgpass, BUKAN dari argumen baris perintah —
# argumen CLI terlihat oleh siapa pun yang menjalankan `ps aux` di server yang sama.
pg_dump --host="$DB_HOST" --username="$DB_USER" --dbname="$DB_NAME" \
  --format=plain --no-owner --no-privileges \
  | gzip > "$BERKAS"

chmod 600 "$BERKAS"

UKURAN=$(du -h "$BERKAS" | cut -f1)
echo "[$(date -Iseconds)] Backup selesai: $BERKAS ($UKURAN)"

# Bersihkan backup yang lebih tua dari $SIMPAN_HARI hari.
find "$BACKUP_DIR" -name 'pkkmb26-penuh-*.sql.gz' -mtime "+$SIMPAN_HARI" -delete

echo "[$(date -Iseconds)] Backup lebih tua dari $SIMPAN_HARI hari dibersihkan."
