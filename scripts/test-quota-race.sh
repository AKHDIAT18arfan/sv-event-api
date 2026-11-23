#!/usr/bin/env bash
# Uji rebutan kuota: banyak akun mendaftar (sebagai Umum) ke satu sesi hampir bersamaan.
# Prasyarat: curl dan jq. Event harus target SEMUA dan berstatus dibuka.
# Pemakaian:  BASE_URL=http://localhost:8000 SESSION_ID=1 N=30 bash scripts/test-quota-race.sh
# Agar bermakna, kuota sesi harus LEBIH KECIL dari N (misal kuota 10, N=30).
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8000}"
SESSION_ID="${SESSION_ID:-1}"
N="${N:-30}"
RUN_ID="$(date +%s)"
TMP="$(mktemp -d)"

echo "Membuat $N akun uji (registrasi dibatasi 10 per menit, jadi skrip menunggu bila ditolak)..."
for i in $(seq 1 "$N"); do
  while true; do
    token="$(curl -s -X POST "$BASE_URL/api/auth/register" \
      -H 'Accept: application/json' -H 'Content-Type: application/json' \
      -d "{\"name\":\"Uji $RUN_ID-$i\",\"email\":\"uji-$RUN_ID-$i@test.id\",\"phone\":\"0812000$i\",\"password\":\"Password#123\",\"password_confirmation\":\"Password#123\"}" \
      | jq -r '.data.token // empty')"
    if [ -n "$token" ]; then echo "$token" > "$TMP/token-$i.txt"; break; fi
    echo "  akun $i ditolak (kemungkinan batas 10/menit), menunggu 15 detik..."
    sleep 15
  done
done

echo "Mendaftar bersamaan..."
export BASE_URL SESSION_ID TMP
seq 1 "$N" | xargs -P "$N" -I{} sh -c \
  'curl -s -o "$TMP/res-{}.json" -X POST "$BASE_URL/api/sessions/$SESSION_ID/registrations" -H "Accept: application/json" -H "Content-Type: application/json" -d "{\"participant_type\":\"UMUM\"}" -H "Authorization: Bearer $(cat $TMP/token-{}.txt)"'

echo "Hasil:"
jq -r '.error.code // "BERHASIL"' "$TMP"/res-*.json | sort | uniq -c

echo "ID tiket yang terbit (harus berurutan, tanpa kembar):"
jq -r '.data.ticket_no // empty' "$TMP"/res-*.json | sort -n | tr '\n' ' '
echo

rm -rf "$TMP"
