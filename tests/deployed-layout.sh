#!/usr/bin/env bash
# Runs the archive exactly as it is deployed — public_html next to agora-private —
# against real PHP and a real database, and walks the notification setup a phone
# goes through. The development tree cannot catch a path that only exists there.
set -euo pipefail
ARCHIVE=${1:?archive}
PORT=${PORT:-8093}
ROOT=$(mktemp -d)
trap 'kill $SERVER 2>/dev/null || true; rm -rf "$ROOT"' EXIT
unzip -q "$ARCHIVE" -d "$ROOT"
[ -d "$ROOT/agora-private/src" ] && [ ! -d "$ROOT/src" ] || { echo "FAIL the archive is not laid out as deployed"; exit 1; }
mkdir -p "$ROOT/agora-private/var"
php -r 'file_put_contents($argv[1],base64_encode(random_bytes(32)));' "$ROOT/agora-private/var/integration.key"
export APP_ENV=development APP_ORIGIN="http://localhost:$PORT" APP_KEY_FILE="$ROOT/agora-private/var/integration.key" \
       WEBPUSH_KEY_FILE="$ROOT/agora-private/var/webpush.json" GHL_LOCATION_ID=agora-workspace
php -S 127.0.0.1:$PORT -t "$ROOT/public_html" "$ROOT/public_html/router.php" >"$ROOT/server.log" 2>&1 &
SERVER=$!
sleep 2
EMAIL="deployed-$RANDOM@example.test";PASS="clave-$RANDOM-$RANDOM"
php -r '
require $argv[1]."/agora-private/src/bootstrap.php";
query("INSERT INTO tenants(name,location_id) VALUES(?,?)",["Deployed",uniqid()]);$t=db()->lastInsertId();
query("INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,\"administrator\")",[$t,$argv[2],password_hash($argv[3],PASSWORD_DEFAULT)]);' "$ROOT" "$EMAIL" "$PASS"
J=$ROOT/cookies
CSRF=$(curl -s -c "$J" -H 'Content-Type: application/json' -H "Origin: http://localhost:$PORT" -d "{\"email\":\"$EMAIL\",\"password\":\"$PASS\"}" http://127.0.0.1:$PORT/api/login | php -r 'echo json_decode(stream_get_contents(STDIN),true)["csrf"]??"";')
[ -n "$CSRF" ] || { echo "FAIL could not sign in to the deployed tree"; tail -5 "$ROOT/server.log"; exit 1; }
KEY=$(curl -s -b "$J" http://127.0.0.1:$PORT/api/push-key)
echo "$KEY" | grep -q '"ready":true' || { echo "FAIL the deployed tree cannot prepare notification keys: $KEY"; tail -5 "$ROOT/server.log"; exit 1; }
echo "PASS the deployed tree prepares its notification keys"
[ -f "$ROOT/agora-private/var/webpush.json" ] || { echo "FAIL the key file was not written where configured"; exit 1; }
echo "PASS and writes them in the private folder, outside public_html"
SUB='{"token":"https://fcm.googleapis.com/fcm/send/deployed-layout-0123456789abcdef","platform":"web","label":"Android","keys":{"p256dh":"'$(printf 'B%.0s' $(seq 87))'","auth":"bXktYXV0aC1zZWNyZXQ"}}'
REG=$(curl -s -b "$J" -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" -H "Origin: http://localhost:$PORT" -d "$SUB" http://127.0.0.1:$PORT/api/devices)
echo "$REG" | grep -q '"registered":true' || { echo "FAIL a phone cannot register on the deployed tree: $REG"; tail -5 "$ROOT/server.log"; exit 1; }
echo "PASS a phone registers itself on the deployed tree"
LIST=$(curl -s -b "$J" http://127.0.0.1:$PORT/api/devices)
echo "$LIST" | grep -q '"browser":true' || { echo "FAIL the device list does not see the notification keys: $LIST"; exit 1; }
echo "PASS and the owner sees it ready in the device list"
TEST=$(curl -s -b "$J" -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" -H "Origin: http://localhost:$PORT" http://127.0.0.1:$PORT/api/devices/test)
echo "$TEST" | grep -q '"results":\[{' || { echo "FAIL the test notice does not run on the deployed tree: $TEST"; tail -5 "$ROOT/server.log"; exit 1; }
echo "PASS the test notice runs on the deployed tree and reports per device"
# Both crons, run from where the server keeps them, must leave their trace.
php "$ROOT/agora-private/bin/sync-inbox.php" >/dev/null 2>&1 || true
php "$ROOT/agora-private/bin/send-push.php" >/dev/null 2>&1 || true
CHAIN=$(curl -s -b "$J" http://127.0.0.1:$PORT/api/devices)
echo "$CHAIN" | grep -q '"discovery":{"at"' || { echo "FAIL the message check left no trace: $CHAIN"; exit 1; }
echo "$CHAIN" | grep -q '"delivery":{' || { echo "FAIL the delivery pass left no trace: $CHAIN"; exit 1; }
echo "PASS both crons run from the deployed tree and the owner can see when"
php -r 'require $argv[1]."/agora-private/src/bootstrap.php";query("DELETE FROM push_devices WHERE token LIKE ?",["%deployed-layout%"]);query("DELETE FROM users WHERE email=?",[$argv[2]]);' "$ROOT" "$EMAIL"
echo "PASS deployed layout checks"
