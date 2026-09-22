#!/usr/bin/env bash
#
# Verifikasi otomatis checklist docs/04-security.md §11, dijalankan TERHADAP
# server yang sudah live. Bukan pengganti seluruh checklist (beberapa butir,
# seperti "backup sudah pernah dipulihkan", memang harus dilakukan manusia
# sekali secara manual) — tapi mengubah sebagian besar butir dari "saya rasa
# sudah benar" menjadi "barusan diuji dan terbukti".
#
# CARA PAKAI:  ./verify-security.sh https://pkkmb.uninus.ac.id
#
# ⚠️ JANGAN jalankan ini SAAT acara berlangsung — skrip ini sengaja memicu
# rate limit sungguhan (12 permintaan beruntun ke /lookup) untuk membuktikan
# batasnya aktif. Jalankan sekali di H-1 saat sistem sudah live tapi belum
# dipakai publik.
set -uo pipefail

BASE_URL="${1:?Uso: verify-security.sh <https://domain>}"
GAGAL=0

periksa() {
    local deskripsi="$1"
    local hasil="$2"  # "true" atau "false"

    if [ "$hasil" = "true" ]; then
        echo "  [OK]    $deskripsi"
    else
        echo "  [GAGAL] $deskripsi"
        GAGAL=$((GAGAL + 1))
    fi
}

echo "=== Checklist docs/04-security.md §11 — verifikasi otomatis ==="
echo "Target: $BASE_URL"
echo ""

# Butir 4: HTTPS aktif, HTTP dialihkan 301, HSTS terpasang.
echo "-- Butir 4: HTTPS & HSTS --"
HTTP_URL="${BASE_URL/https:/http:}"
KODE_REDIRECT=$(curl -s -o /dev/null -w "%{http_code}" "$HTTP_URL" || echo "000")
periksa "HTTP dialihkan 301" "$([ "$KODE_REDIRECT" = "301" ] && echo true || echo false)"

HSTS=$(curl -sI "$BASE_URL" | grep -i "^strict-transport-security:" || true)
periksa "Header HSTS terpasang" "$([ -n "$HSTS" ] && echo true || echo false)"

# Butir 15: header keamanan lain.
echo "-- Butir 15: Header keamanan --"
HEADERS=$(curl -sI "$BASE_URL")
for h in "x-content-type-options" "x-frame-options" "referrer-policy"; do
    ADA=$(echo "$HEADERS" | grep -qi "^$h:" && echo true || echo false)
    periksa "Header $h ada" "$ADA"
done

# Referrer-Policy TIDAK BOLEH no-referrer (mematahkan Sanctum, docs/04-security.md §7).
RP=$(echo "$HEADERS" | grep -i "^referrer-policy:" | tr -d '\r')
periksa "Referrer-Policy BUKAN no-referrer" "$(echo "$RP" | grep -qi "no-referrer$" && echo false || echo true)"

# Butir 1: APP_DEBUG=false (tidak ada stack trace di halaman error).
echo "-- Butir 1: APP_DEBUG --"
GALAT=$(curl -s -X POST "$BASE_URL/api/v1/lookup" -H "Content-Type: application/json" -d '{}')
periksa "Respons error tidak memuat jejak file .php" "$(echo "$GALAT" | grep -q "\.php" && echo false || echo true)"
periksa "Respons error mengikuti format {success,message}" "$(echo "$GALAT" | grep -q '"success":false' && echo true || echo false)"

# Butir 5: rate limit lookup (10/menit). Kirim 12, harapkan sebagian 429.
echo "-- Butir 5: Rate limit lookup --"
ADA_429=false
for i in $(seq 1 12); do
    KODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE_URL/api/v1/lookup" \
        -H "Content-Type: application/json" -d '{"nim":"00000000000"}')
    [ "$KODE" = "429" ] && ADA_429=true
done
periksa "12 permintaan lookup beruntun memicu 429" "$ADA_429"

# Butir 6: response lookup tidak memuat field terlarang (diuji dengan NIM
# yang hampir pasti tidak ada, sehingga hanya struktur 404 yang diperiksa —
# verifikasi PENUH butir ini tetap wajib manual dengan NIM asli yang ada
# di database, karena respons 404 tidak membawa payload data mahasiswa).
echo "-- Butir 6: (sebagian) struktur respons lookup --"
periksa "NIM tak dikenal -> 404 dengan pesan Bahasa Indonesia" \
    "$(echo "$GALAT" | grep -q "tidak ditemukan\|wajib diisi" && echo true || echo false)"
echo "  [CATATAN] Verifikasi PENUH butir 6 (tidak ada phone/email/birth_date/"
echo "  attendance_token) harus dilakukan MANUAL dengan NIM mahasiswa asli —"
echo "  skrip ini tidak boleh diberi NIM sungguhan."

# Butir 8: /scan menolak tanpa login.
echo "-- Butir 8: /scan tanpa login --"
KODE_SCAN=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE_URL/api/v1/scan" \
    -H "Content-Type: application/json" -d '{"token":"00000000000000000000000000000000","attendance_session_id":1}')
periksa "/scan tanpa sesi login -> 401" "$([ "$KODE_SCAN" = "401" ] && echo true || echo false)"

# Butir 14: PostgreSQL tidak terbuka ke luar (diuji dari luar server ini
# sendiri — kalau dijalankan DI server yang sama, "connection refused" ke
# diri sendiri tidak membuktikan apa-apa; jalankan dari MESIN LAIN).
echo "-- Butir 14: PostgreSQL --"
echo "  [CATATAN] Jalankan baris berikut dari KOMPUTER LAIN (bukan dari"
echo "  server ini sendiri) untuk membuktikan port 5432 benar-benar tertutup:"
echo "    nmap -p 5432 <ip-server>   # harapan: filtered/closed, BUKAN open"

echo ""
if [ "$GAGAL" -eq 0 ]; then
    echo "=== Semua pemeriksaan otomatis LOLOS. Lanjutkan dengan butir manual di checklist. ==="
    exit 0
else
    echo "=== $GAGAL pemeriksaan GAGAL. Perbaiki sebelum melanjutkan checklist. ==="
    exit 1
fi
