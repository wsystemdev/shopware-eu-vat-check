#!/usr/bin/env bash
# E2E on a real Shopware install (CI): registers business customers through the Store API
# and checks the plugin rejects a VAT ID that VIES reports invalid and accepts a valid one.
# Makes 2 real calls to api.wsystem.dev (free tier).
set -euo pipefail
DB="mysql -uroot -h127.0.0.1 shopware -N -e"
KEY=$($DB "SELECT access_key FROM sales_channel WHERE LOWER(HEX(type_id))='8a243080f92e4c719546314b577cf82b' LIMIT 1")
URL=$($DB "SELECT d.url FROM sales_channel_domain d JOIN sales_channel s ON s.id=d.sales_channel_id WHERE s.access_key='$KEY' LIMIT 1")
DE=$($DB "SELECT LOWER(HEX(id)) FROM country WHERE iso='DE'")
SAL=$($DB "SELECT LOWER(HEX(id)) FROM salutation LIMIT 1")
BASE=${BASE:-http://127.0.0.1:8000}
echo "sales channel url=$URL"
reg() { # $1 email $2 vat
  curl -s -o /tmp/resp.json -w '%{http_code}' -X POST "$BASE/store-api/account/register" \
    -H "sw-access-key: $KEY" -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d "{\"accountType\":\"business\",\"company\":\"E2E GmbH\",\"vatIds\":[\"$2\"],\"salutationId\":\"$SAL\",\"firstName\":\"E\",\"lastName\":\"Test\",\"email\":\"$1\",\"password\":\"e2e-Passw0rd!\",\"storefrontUrl\":\"$URL\",\"acceptedDataProtection\":true,\"billingAddress\":{\"street\":\"Teststr. 1\",\"zipcode\":\"10115\",\"city\":\"Berlin\",\"countryId\":\"$DE\"}}"
}
code=$(reg "invalid-$RANDOM@example.com" DE000000000); cat /tmp/resp.json; echo
[ "$code" = 400 ] && grep -q WSYSTEM_VAT_INVALID /tmp/resp.json || { echo "FAIL: invalid VAT ID not rejected ($code)"; exit 1; }
code=$(reg "valid-$RANDOM@example.com" DE811569869); head -c 300 /tmp/resp.json; echo
[ "$code" = 200 ] || { echo "FAIL: valid VAT ID rejected ($code)"; exit 1; }
code=$(reg "country-$RANDOM@example.com" FR40303265045); head -c 300 /tmp/resp.json; echo
[ "$code" = 400 ] && grep -q WSYSTEM_VAT_COUNTRY_MISMATCH /tmp/resp.json || { echo "FAIL: country mismatch not rejected ($code)"; exit 1; }
echo "E2E OK"
