#!/usr/bin/env bash
#
# Backup cepat KHUSUS tabel kehadiran, dijalankan setiap 15 menit pada hari-H.
# Lihat docs/06-deployment.md §6 dan docs/07-timeline.md §6.
#
# Sengaja hanya dua tabel (bukan seluruh database) supaya backup ini cepat dan
# tidak membebani database saat sedang dipakai 4 titik memindai bersamaan.
set -euo pipefail

DB_NAME="${PKKMB_DB_NAME:-pkkmb26}"
DB_USER="${PKKMB_DB_USER:-pkkmb26_app}"
DB_HOST="${PKKMB_DB_HOST:-127.0.0.1}"
BACKUP_DIR="${PKKMB_BACKUP_DIR:-/var/backups/pkkmb26}/hari-h"
SIMPAN_JAM=72

mkdir -p "$BACKUP_DIR"

STEMPEL=$(date +%Y%m%d-%H%M%S)
BERKAS="$BACKUP_DIR/kehadiran-$STEMPEL.sql.gz"

pg_dump --host="$DB_HOST" --username="$DB_USER" --dbname="$DB_NAME" \
  --format=plain --no-owner --no-privileges \
  --table=attendances --table=scan_logs \
  | gzip > "$BERKAS"

chmod 600 "$BERKAS"

echo "[$(date -Iseconds)] Backup kehadiran: $BERKAS"

# Simpan 72 jam terakhir saja — ini backup jangka pendek berfrekuensi tinggi,
# bukan pengganti backup harian penuh.
find "$BACKUP_DIR" -name 'kehadiran-*.sql.gz' -mmin "+$((SIMPAN_JAM * 60))" -delete
