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
NOTICE="conv-deployed-$RANDOM$RANDOM"
# One cron is enough: the message check also delivers what is waiting. A notice
# is queued by hand for a device of this owner, and only sync-inbox.php runs.
php -r '
require $argv[1]."/agora-private/src/bootstrap.php";
$u=query("SELECT id,tenant_id FROM users WHERE email=?",[$argv[2]])->fetch();
query("INSERT INTO ghl_connections(tenant_id,name,location_id,token_ciphertext,status) VALUES(?,?,?,?,\"untested\")",[$u["tenant_id"],"Prueba",uniqid("loc"),"x"]);
$c=db()->lastInsertId();
// A device with real keys at an address that cannot be reached: the attempt
// must be made and its reason kept, and the device must stay.
$k=openssl_pkey_new(["curve_name"=>"prime256v1","private_key_type"=>OPENSSL_KEYTYPE_EC]);$d=openssl_pkey_get_details($k);
$point="\x04".str_pad($d["ec"]["x"],32,"\0",STR_PAD_LEFT).str_pad($d["ec"]["y"],32,"\0",STR_PAD_LEFT);
$b64=fn($v)=>rtrim(strtr(base64_encode($v),"+/","-_"),"=");
query("DELETE FROM push_devices WHERE token=?",["https://push.invalid/deployed-layout-unreachable"]);
query("INSERT INTO push_devices(tenant_id,identity_type,actor_id,token,platform,p256dh,auth) VALUES(?,?,?,?,?,?,?)",[$u["tenant_id"],"native",$u["id"],"https://push.invalid/deployed-layout-unreachable","web",$b64($point),$b64(random_bytes(16))]);
query("INSERT INTO push_queue(connection_id,conversation_id,fingerprint,title,body) VALUES(?,?,?,?,?)",[$c,$argv[4],md5(uniqid()),"Raissa","Hola"]);
file_put_contents($argv[3],$c);' "$ROOT" "$EMAIL" "$ROOT/connection" "$NOTICE"
php "$ROOT/agora-private/bin/sync-inbox.php" --once >"$ROOT/cron.out" 2>&1 || true
grep -qE "Notices [1-9]" "$ROOT/cron.out" || { echo "FAIL the single cron did not pick up the waiting notice: $(cat "$ROOT/cron.out")"; exit 1; }
echo "PASS the message check alone picks up and tries to deliver a waiting notice"
STATE=$(php -r 'require $argv[1]."/agora-private/src/bootstrap.php";echo json_encode(query("SELECT state,attempts,last_error FROM push_queue WHERE conversation_id=?",[$argv[2]])->fetch());' "$ROOT" "$NOTICE")
echo "$STATE" | grep -q '"state":"pending","attempts":1,"last_error":"unavailable' || { echo "FAIL the attempt and its reason were not recorded: $STATE"; exit 1; }
KEPT=$(php -r 'require $argv[1]."/agora-private/src/bootstrap.php";echo query("SELECT COUNT(*) FROM push_devices WHERE token=?",["https://push.invalid/deployed-layout-unreachable"])->fetchColumn();' "$ROOT")
[ "$KEPT" = "1" ] || { echo "FAIL an unreachable service made the phone disappear"; exit 1; }
echo "PASS an unreachable service never makes the phone disappear"
echo "PASS and records the attempt with its reason: $STATE"
php "$ROOT/agora-private/bin/send-push.php" >/dev/null 2>&1 || true
CHAIN=$(curl -s -b "$J" http://127.0.0.1:$PORT/api/devices)
echo "$CHAIN" | grep -q '"discovery":{' || { echo "FAIL the message check left no trace: $CHAIN"; exit 1; }
echo "$CHAIN" | grep -q '"delivery":{' || { echo "FAIL the delivery pass left no trace: $CHAIN"; exit 1; }
echo "PASS both crons run from the deployed tree and the owner can see when"
# When the server cron cannot run, a cron service calls a secret address instead.
MADE=$(curl -s -b "$J" -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" -H "Origin: http://localhost:$PORT" http://127.0.0.1:$PORT/api/admin/cron-url)
CRON=$(echo "$MADE" | php -r '$u=json_decode(stream_get_contents(STDIN),true)["url"]??"";echo preg_match("~/api/cron/([a-f0-9]{64})$~",$u,$m)?$m[1]:"";')
[ -n "$CRON" ] || { echo "FAIL the owner cannot create the outside check address: $MADE"; exit 1; }
php -r 'require $argv[1]."/agora-private/src/bootstrap.php";query("DELETE FROM app_state WHERE name=?",["sweep_last_run"]);' "$ROOT"
RUN=$(curl -s http://127.0.0.1:$PORT/api/cron/$CRON)
echo "$RUN" | grep -q '"ok":true' || { echo "FAIL the outside check does not run on the deployed tree: $RUN"; tail -5 "$ROOT/server.log"; exit 1; }
VIA=$(curl -s -b "$J" http://127.0.0.1:$PORT/api/devices)
echo "$VIA" | grep -q '"via":"url"' || { echo "FAIL the outside check left no trace: $VIA"; exit 1; }
echo "PASS a cron service calling the secret address runs the check on the deployed tree"
curl -s -b "$J" -X DELETE -H "X-CSRF-Token: $CSRF" -H "Origin: http://localhost:$PORT" http://127.0.0.1:$PORT/api/admin/cron-url >/dev/null
php -r 'require $argv[1]."/agora-private/src/bootstrap.php";query("DELETE FROM push_devices WHERE token LIKE ?",["%deployed-layout%"]);query("DELETE FROM ghl_connections WHERE id=?",[(int)@file_get_contents($argv[3])]);query("DELETE FROM users WHERE email=?",[$argv[2]]);' "$ROOT" "$EMAIL" "$ROOT/connection"
echo "PASS deployed layout checks"
