#!/bin/bash
# E2E skenario uji wajib S1-S5 via API Gateway (localhost:8080).
# Ulangi aman: tiap skenario diawali reset data transaksi (tanpa mematikan stack).
# Exit 0 bila semua PASS, 1 bila ada FAIL.
cd "$(dirname "$0")/.."
GW="${GW:-http://localhost:8080}"
FIN_DIRECT="${FIN_DIRECT:-http://localhost:8003}"
POS_DIRECT="${POS_DIRECT:-http://localhost:8002}"
KEY="${INTERNAL_API_KEY:-dev-internal-key-change-me}"
DBPW="${MYSQL_ROOT_PASSWORD:-root}"
PASS="Password123!"

PASS_N=0
FAIL_N=0
HTTP_CODE=0
BODY=""

api() { # method path token [json-body]
  local method="$1" path="$2" token="$3" data="${4:-}" out
  if [ -n "$data" ]; then
    out=$(curl -s -w "\n%{http_code}" -X "$method" "$GW$path" \
      -H "Authorization: Bearer $token" -H "Content-Type: application/json" -d "$data")
  elif [ -n "$token" ]; then
    out=$(curl -s -w "\n%{http_code}" -X "$method" "$GW$path" -H "Authorization: Bearer $token")
  else
    out=$(curl -s -w "\n%{http_code}" -X "$method" "$GW$path")
  fi
  HTTP_CODE=$(echo "$out" | tail -n 1)
  BODY=$(echo "$out" | sed '$d')
}

login() { # email -> echo token (gagal bila null)
  local t
  t=$(curl -s -X POST "$GW/api/auth/login" -H "Content-Type: application/json" \
    -d "{\"email\":\"$1\",\"password\":\"$PASS\"}" | jq -r ".data.access_token // empty")
  if [ -z "$t" ] || [ "$t" = "null" ]; then
    FAIL_N=$((FAIL_N+1)); echo "FAIL: login $1 (token kosong)"
  fi
  echo "$t"
}

require_id() { # label value
  if [ -z "$1" ] || [ "$1" = "null" ]; then
    FAIL_N=$((FAIL_N+1)); echo "FAIL: $2 (id kosong)"
  fi
}

assert_eq() { # label expected actual
  if [ "$2" = "$3" ]; then PASS_N=$((PASS_N+1)); echo "PASS: $1";
  else FAIL_N=$((FAIL_N+1)); echo "FAIL: $1 (harap $2, dapat $3)"; fi
}

assert_code() { # label expected-code
  assert_eq "$1 (HTTP)" "$2" "$HTTP_CODE"
}

reset_day() {
  docker compose exec -T mysql-pos mysql -uroot -p"$DBPW" \
    -e "DELETE FROM db_pos.transaction_items; DELETE FROM db_pos.transactions; DELETE FROM db_pos.trx_sequences; DELETE FROM db_pos.shifts;" 2>/dev/null
  docker compose exec -T mysql-finance mysql -uroot -p"$DBPW" \
    -e "DELETE FROM db_finance.journal_lines; DELETE FROM db_finance.postings; DELETE FROM db_finance.eod_reconciliations;" 2>/dev/null
  echo "-- reset data transaksi OK"
}

wait_up() { # url label
  for _ in $(seq 1 40); do
    if curl -sf -o /dev/null "$1" 2>/dev/null; then echo "-- $2 up"; return 0; fi
    sleep 3
  done
  echo "FAIL: $2 tidak kunjung up"; FAIL_N=$((FAIL_N+1)); return 1
}

wait_down() { # url label
  for _ in $(seq 1 20); do
    if ! curl -sf -o /dev/null "$1" 2>/dev/null; then echo "-- $2 down"; return 0; fi
    sleep 2
  done
  echo "FAIL: $2 masih up"; FAIL_N=$((FAIL_N+1)); return 1
}

product_id() { # sku token -> id
  curl -s "$GW/api/pos/products?search=$1" -H "Authorization: Bearer $2" | jq -r ".data[0].id"
}

TODAY=$(date +%F)

if [ "${E2E_NO_UP:-}" != "1" ]; then
  docker compose up -d >/dev/null
fi
wait_up "$GW/api/auth/health" "gateway"

# ============ S1 ============
echo "===== S1 happy path ====="
reset_day
KAS=$(login kasir.bdg@demo.test)
SPV=$(login supervisor.bdg@demo.test)
STF=$(login staff.finance@demo.test)
P100=$(product_id PRD-002 "$KAS"); P50=$(product_id PRD-001 "$KAS"); P200=$(product_id PRD-003 "$KAS")

api POST /api/pos/shifts/open "$KAS" '{"opening_cash":200000}'
assert_code "S1 open shift" 201
SID=$(echo "$BODY" | jq -r ".data.id")

api POST /api/pos/transactions "$KAS" "{\"items\":[{\"product_id\":$P100,\"quantity\":1}]}"
T1=$(echo "$BODY" | jq -r ".data.id")
assert_eq "S1 T1 grand_total" "111000.00" "$(echo "$BODY" | jq -r ".data.grand_total")"
api POST /api/pos/transactions/$T1/pay "$KAS" '{"payment_method":"cash","paid_amount":150000}'
assert_eq "S1 T1 change" "39000.00" "$(echo "$BODY" | jq -r ".data.change_amount")"
assert_eq "S1 T1 sync" "synced" "$(echo "$BODY" | jq -r ".data.finance_sync_status")"

api POST /api/pos/transactions "$KAS" "{\"items\":[{\"product_id\":$P50,\"quantity\":1}]}"
T2=$(echo "$BODY" | jq -r ".data.id")
api POST /api/pos/transactions/$T2/pay "$KAS" '{"payment_method":"qris","paid_amount":55500}'
assert_eq "S1 T2 grand_total" "55500.00" "$(echo "$BODY" | jq -r ".data.grand_total")"

api POST /api/pos/transactions "$KAS" "{\"items\":[{\"product_id\":$P200,\"quantity\":1}]}"
T3=$(echo "$BODY" | jq -r ".data.id")
api POST /api/pos/transactions/$T3/pay "$KAS" '{"payment_method":"debit","paid_amount":222000}' >/dev/null
api POST /api/pos/transactions/$T3/void "$SPV" '{"reason":"salah input"}'
assert_eq "S1 T3 void" "void" "$(echo "$BODY" | jq -r ".data.status")"

api POST /api/pos/shifts/$SID/close "$KAS" '{"actual_cash":311000}'
assert_eq "S1 expected_cash" "311000.00" "$(echo "$BODY" | jq -r ".data.shift.expected_cash")"
assert_eq "S1 variance" "0.00" "$(echo "$BODY" | jq -r ".data.shift.cash_variance")"
assert_eq "S1 unsynced" "0" "$(echo "$BODY" | jq -r ".data.unsynced_count")"

api POST /api/finance/reconciliations/run "$STF" "{\"outlet_id\":1,\"business_date\":\"$TODAY\"}"
assert_eq "S1 recon matched" "matched" "$(echo "$BODY" | jq -r ".data.reconciliation.status")"
assert_eq "S1 net cash" "111000.00" "$(echo "$BODY" | jq -r ".data.reconciliation.pos_cash")"
assert_eq "S1 net qris" "55500.00" "$(echo "$BODY" | jq -r ".data.reconciliation.pos_qris")"
assert_eq "S1 net debit" "0.00" "$(echo "$BODY" | jq -r ".data.reconciliation.pos_debit")"
assert_eq "S1 trx count" "2" "$(echo "$BODY" | jq -r ".data.reconciliation.pos_trx_count")"

T1NUM=$(curl -s "$GW/api/pos/transactions/$T1" -H "Authorization: Bearer $KAS" | jq -r ".data.trx_number")
PID=$(curl -s "$GW/api/finance/postings?outlet_id=1&business_date=$TODAY&entry_type=sale" \
  -H "Authorization: Bearer $STF" | jq -r ".data[] | select(.trx_number==\"$T1NUM\") | .id")
JURNAL=$(curl -s "$GW/api/finance/postings/$PID" -H "Authorization: Bearer $STF" | jq -c ".data.journal_lines")
echo "S1 jurnal T1: $JURNAL"
D1101=$(echo "$JURNAL" | jq -r ".[] | select(.account_code==\"1101\") | .debit")
K4101=$(echo "$JURNAL" | jq -r ".[] | select(.account_code==\"4101\") | .credit")
K2101=$(echo "$JURNAL" | jq -r ".[] | select(.account_code==\"2101\") | .credit")
assert_eq "S1 jurnal D1101" "111000.00" "$D1101"
assert_eq "S1 jurnal K4101" "100000.00" "$K4101"
assert_eq "S1 jurnal K2101" "11000.00" "$K2101"

# ============ S2 ============
echo "===== S2 finance down ====="
reset_day
KAS=$(login kasir.bdg@demo.test)
SPV=$(login supervisor.bdg@demo.test)
STF=$(login staff.finance@demo.test)
P100=$(product_id PRD-002 "$KAS"); P50=$(product_id PRD-001 "$KAS"); P200=$(product_id PRD-003 "$KAS")
api POST /api/pos/shifts/open "$KAS" '{"opening_cash":200000}' >/dev/null
SID=$(curl -s "$GW/api/pos/shifts/current" -H "Authorization: Bearer $KAS" | jq -r ".data.id")

api POST /api/pos/transactions "$KAS" "{\"items\":[{\"product_id\":$P100,\"quantity\":1}]}" >/dev/null
T1=$(curl -s "$GW/api/pos/transactions?status=pending" -H "Authorization: Bearer $KAS" | jq -r ".data[0].id")
api POST /api/pos/transactions/$T1/pay "$KAS" '{"payment_method":"cash","paid_amount":150000}' >/dev/null

docker compose stop finance >/dev/null
wait_down "$FIN_DIRECT/health" "finance"

api POST /api/pos/transactions "$KAS" "{\"items\":[{\"product_id\":$P50,\"quantity\":1}]}" >/dev/null
T2=$(curl -s "$GW/api/pos/transactions?status=pending" -H "Authorization: Bearer $KAS" | jq -r ".data[0].id")
api POST /api/pos/transactions/$T2/pay "$KAS" '{"payment_method":"qris","paid_amount":55500}'
assert_eq "S2 T2 tetap paid" "paid" "$(echo "$BODY" | jq -r ".data.status")"
assert_eq "S2 T2 sync failed" "failed" "$(echo "$BODY" | jq -r ".data.finance_sync_status")"

docker compose start finance >/dev/null
wait_up "$FIN_DIRECT/health" "finance"

api POST /api/pos/transactions "$KAS" "{\"items\":[{\"product_id\":$P200,\"quantity\":1}]}" >/dev/null
T3=$(curl -s "$GW/api/pos/transactions?status=pending" -H "Authorization: Bearer $KAS" | jq -r ".data[0].id")
api POST /api/pos/transactions/$T3/pay "$KAS" '{"payment_method":"debit","paid_amount":222000}' >/dev/null
api POST /api/pos/transactions/$T3/void "$SPV" '{"reason":"salah"}' >/dev/null

docker compose stop finance >/dev/null
wait_down "$FIN_DIRECT/health" "finance"
api POST /api/pos/shifts/$SID/close "$KAS" '{"actual_cash":311000}'
assert_eq "S2 unsynced_count=1" "1" "$(echo "$BODY" | jq -r ".data.unsynced_count")"
docker compose start finance >/dev/null
wait_up "$FIN_DIRECT/health" "finance"
STF=$(login staff.finance@demo.test)

api POST /api/finance/reconciliations/run "$STF" "{\"outlet_id\":1,\"business_date\":\"$TODAY\"}"
assert_eq "S2 recon1 mismatch" "mismatch" "$(echo "$BODY" | jq -r ".data.reconciliation.status")"
RID=$(echo "$BODY" | jq -r ".data.reconciliation.id")
AMT=$(echo "$BODY" | jq -r ".data.reconciliation.mismatch_reasons[] | select(.type==\"AMOUNT_DIFF\") | \"\(.method):\(.pos)\"")
CNT=$(echo "$BODY" | jq -r ".data.reconciliation.mismatch_reasons[] | select(.type==\"COUNT_DIFF\") | \"\(.pos)-\(.finance)\"")
assert_eq "S2 AMOUNT_DIFF qris" "qris:55500.00" "$AMT"
assert_eq "S2 COUNT_DIFF" "2-1" "$CNT"

KAS=$(login kasir.bdg@demo.test)
api POST /api/pos/transactions/$T2/resync-finance "$KAS"
assert_eq "S2 resync synced" "synced" "$(echo "$BODY" | jq -r ".data.finance_sync_status")"
STF=$(login staff.finance@demo.test)
api POST /api/finance/reconciliations/run "$STF" "{\"outlet_id\":1,\"business_date\":\"$TODAY\"}"
assert_eq "S2 rerun matched" "matched" "$(echo "$BODY" | jq -r ".data.reconciliation.status")"
assert_eq "S2 baris sama" "$RID" "$(echo "$BODY" | jq -r ".data.reconciliation.id")"

# ============ S3 ============
echo "===== S3 selisih kas ====="
reset_day
KAS=$(login kasir.bdg@demo.test)
SPV=$(login supervisor.bdg@demo.test)
STF=$(login staff.finance@demo.test)
MGR=$(login manager.finance@demo.test)
P100=$(product_id PRD-002 "$KAS"); P50=$(product_id PRD-001 "$KAS"); P200=$(product_id PRD-003 "$KAS")
api POST /api/pos/shifts/open "$KAS" '{"opening_cash":200000}' >/dev/null
mkpay() { # product_id method amount -> pay ok
  local tid
  tid=$(curl -s -X POST "$GW/api/pos/transactions" -H "Authorization: Bearer $KAS" \
    -H "Content-Type: application/json" -d "{\"items\":[{\"product_id\":$1,\"quantity\":1}]}" | jq -r ".data.id")
  curl -s -X POST "$GW/api/pos/transactions/$tid/pay" -H "Authorization: Bearer $KAS" \
    -H "Content-Type: application/json" -d "{\"payment_method\":\"$2\",\"paid_amount\":$3}" >/dev/null
  echo "$tid"
}
T1=$(mkpay "$P100" cash 150000); T2=$(mkpay "$P50" qris 55500); T3=$(mkpay "$P200" debit 222000)
api POST /api/pos/transactions/$T3/void "$SPV" '{"reason":"salah"}' >/dev/null
SID=$(curl -s "$GW/api/pos/shifts/current" -H "Authorization: Bearer $KAS" | jq -r ".data.id")
api POST /api/pos/shifts/$SID/close "$KAS" '{"actual_cash":306000}'
assert_eq "S3 variance" "-5000.00" "$(echo "$BODY" | jq -r ".data.shift.cash_variance")"
api POST /api/finance/reconciliations/run "$STF" "{\"outlet_id\":1,\"business_date\":\"$TODAY\"}"
assert_eq "S3 mismatch" "mismatch" "$(echo "$BODY" | jq -r ".data.reconciliation.status")"
CV=$(echo "$BODY" | jq -r ".data.reconciliation.mismatch_reasons[] | select(.type==\"CASH_VARIANCE\") | .amount")
assert_eq "S3 CASH_VARIANCE" "-5000.00" "$CV"
RID=$(echo "$BODY" | jq -r ".data.reconciliation.id")
api PATCH /api/finance/reconciliations/$RID/resolve "$MGR" '{}'
assert_code "S3 resolve tanpa catatan 422" 422
api PATCH /api/finance/reconciliations/$RID/resolve "$MGR" '{"resolution_note":"selisih kas dicatat PR"}'
assert_eq "S3 resolved" "resolved" "$(echo "$BODY" | jq -r ".data.status")"
api POST /api/finance/reconciliations/run "$STF" "{\"outlet_id\":1,\"business_date\":\"$TODAY\"}"
assert_code "S3 run setelah resolved 409" 409

# ============ S4 ============
echo "===== S4 idempotensi ====="
S4PAY='{"idempotency_key":"TRX/S4/20260615/0001:sale","entry_type":"sale","trx_number":"TRX/S4/20260615/0001","outlet_id":9,"outlet_code":"S4X","shift_id":99,"business_date":"2026-06-15","payment_method":"cash","net_sales_amount":"100000.00","tax_amount":"11000.00","total_amount":"111000.00","occurred_at":"2026-06-15T10:00:00+07:00"}'
C1=$(curl -s -o /tmp/s4a.json -w "%{http_code}" -X POST "$FIN_DIRECT/internal/postings" \
  -H "X-Service-Key: $KEY" -H "Content-Type: application/json" -d "$S4PAY")
C2=$(curl -s -o /tmp/s4b.json -w "%{http_code}" -X POST "$FIN_DIRECT/internal/postings" \
  -H "X-Service-Key: $KEY" -H "Content-Type: application/json" -d "$S4PAY")
assert_eq "S4 kirim 1" "201" "$C1"
assert_eq "S4 kirim 2" "200" "$C2"
PCOUNT=$(docker compose exec -T mysql-finance mysql -uroot -p"$DBPW" -N \
  -e "SELECT COUNT(*) FROM db_finance.postings WHERE idempotency_key='TRX/S4/20260615/0001:sale';" 2>/dev/null | tr -d " ")
PID4=$(docker compose exec -T mysql-finance mysql -uroot -p"$DBPW" -N \
  -e "SELECT id FROM db_finance.postings WHERE idempotency_key='TRX/S4/20260615/0001:sale';" 2>/dev/null | tr -d " ")
JCOUNT=$(docker compose exec -T mysql-finance mysql -uroot -p"$DBPW" -N \
  -e "SELECT COUNT(*) FROM db_finance.journal_lines WHERE posting_id=$PID4;" 2>/dev/null | tr -d " ")
assert_eq "S4 satu posting" "1" "$PCOUNT"
assert_eq "S4 satu set jurnal" "3" "$JCOUNT"
docker compose exec -T mysql-finance mysql -uroot -p"$DBPW" \
  -e "DELETE FROM db_finance.journal_lines WHERE posting_id=$PID4; DELETE FROM db_finance.postings WHERE id=$PID4;" 2>/dev/null

# ============ S5 ============
echo "===== S5 pengaman ====="
reset_day
KAS=$(login kasir.bdg@demo.test)
SPV=$(login supervisor.bdg@demo.test)
STF=$(login staff.finance@demo.test)
P100=$(product_id PRD-002 "$KAS")
api POST /api/pos/shifts/open "$KAS" '{"opening_cash":100000}' >/dev/null
SID=$(curl -s "$GW/api/pos/shifts/current" -H "Authorization: Bearer $KAS" | jq -r ".data.id")
PTID=$(curl -s -X POST "$GW/api/pos/transactions" -H "Authorization: Bearer $KAS" \
  -H "Content-Type: application/json" -d "{\"items\":[{\"product_id\":$P100,\"quantity\":1}]}" | jq -r ".data.id")
api POST /api/pos/shifts/$SID/close "$KAS" '{"actual_cash":100000}'
assert_code "S5 close saat pending 409" 409
api POST /api/pos/transactions/$PTID/pay "$KAS" '{"payment_method":"cash","paid_amount":111000}' >/dev/null
api POST /api/pos/shifts/$SID/close "$KAS" '{"actual_cash":211000}' >/dev/null
api POST /api/pos/transactions/$PTID/void "$SPV" '{"reason":"x"}'
assert_code "S5 void setelah closed 409" 409
api POST /api/pos/shifts/open "$KAS" '{"opening_cash":50000}' >/dev/null
api POST /api/finance/reconciliations/run "$STF" "{\"outlet_id\":1,\"business_date\":\"$TODAY\"}"
assert_code "S5 recon saat shift open 409" 409
G1=$(curl -s -o /dev/null -w "%{http_code}" "$GW/api/pos/internal/eod-summary?outlet_id=1&business_date=$TODAY")
assert_eq "S5 gateway /internal 404" "404" "$G1"
G2=$(curl -s -o /dev/null -w "%{http_code}" "$GW/api/finance/internal/postings")
assert_eq "S5 gateway finance /internal 404" "404" "$G2"
D1=$(curl -s -o /dev/null -w "%{http_code}" "$POS_DIRECT/internal/eod-summary?outlet_id=1&business_date=$TODAY")
assert_eq "S5 direct tanpa key 403" "403" "$D1"
D2=$(curl -s -o /dev/null -w "%{http_code}" "$POS_DIRECT/internal/eod-summary?outlet_id=1&business_date=$TODAY" -H "X-Service-Key: salah")
assert_eq "S5 direct key salah 403" "403" "$D2"
D3=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$FIN_DIRECT/internal/postings" -H "Content-Type: application/json" -d '{}')
assert_eq "S5 finance tanpa key 403" "403" "$D3"

reset_day
echo "===== hasil: PASS=$PASS_N FAIL=$FAIL_N ====="
[ "$FAIL_N" -eq 0 ]
