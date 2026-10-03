#!/bin/sh
set -e
echo "== gateway =="
curl -sf localhost:8080/api/auth/health && echo " auth OK"
curl -sf localhost:8080/api/pos/health && echo " pos OK"
curl -sf localhost:8080/api/finance/health && echo " finance OK"
echo "== internal block (expect 404) =="
code=$(curl -s -o /dev/null -w "%{http_code}" localhost:8080/api/pos/internal/eod-summary); echo "code=$code"; [ "$code" = "404" ] || exit 1
echo "PASS"
