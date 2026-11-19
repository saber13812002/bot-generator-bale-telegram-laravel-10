#!/usr/bin/env bash
#
# تست شراب بهشتی برای ایتا — پیش‌نمایش یا ارسال واقعی مثل جاب RSS
#
# استفاده:
#   cd ~/bots && bash scripts/sharabe-beheshti-eitaa-test.sh
#
# فقط پیش‌نمایش (پیش‌فرض):
#   bash scripts/sharabe-beheshti-eitaa-test.sh
#
# ارسال به کانال ایتا:
#   EITAA_TOKEN='bot1967:xxxxxxxx' CHAT_ID='-100xxxxxxxx' SEND=1 bash scripts/sharabe-beheshti-eitaa-test.sh
#
# متغیرها:
#   EITAA_TOKEN   توکن ربات (اگر در .env نباشد)
#   CHAT_ID       شناسه کانال/گروه ایتا (برای SEND=1 الزامی)
#   TARGET_ID     target_id پیش‌فرض rss_channel (default: 8419225)
#   MP3_ID        شناسه MP3 شراب بهشتی (default: 1)
#   SEND          1 = ارسال واقعی، 0 = فقط پیش‌نمایش (default: 0)
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

MP3_ID="${MP3_ID:-1}"
SEND="${SEND:-0}"
TARGET_ID="${TARGET_ID:-8419225}"
CHAT_ID="${CHAT_ID:-}"

read_env_var() {
  local key="$1"
  local line
  line="$(grep -E "^${key}=" .env 2>/dev/null | head -1 || true)"
  if [ -z "$line" ]; then
    return 1
  fi
  echo "${line#*=}" | sed -e 's/^["'\'' ]*//' -e 's/["'\'' ]*$//'
}

resolve_eitaa_token() {
  if [ -n "${EITAA_TOKEN:-}" ]; then
    echo "$EITAA_TOKEN"
    return 0
  fi
  local key val
  for key in BOT_EITAA_TOKEN_SABER EITAA_BOT_TOKEN BOT_EITAA_TOKEN; do
    if val="$(read_env_var "$key")"; then
      if [ -n "$val" ]; then
        echo "$val"
        return 0
      fi
    fi
  done
  return 1
}

echo "=== sharabe-beheshti-eitaa-test ==="
echo "project: $ROOT"
echo "mp3_id:  $MP3_ID | send: $SEND | target_id: $TARGET_ID"
echo ""

if ! TOKEN="$(resolve_eitaa_token)"; then
  echo "ERROR: توکن ایتا پیدا نشد."
  echo ""
  echo "یکی از این کارها را بکنید:"
  echo "  1) در .env اضافه کنید: BOT_EITAA_TOKEN_SABER=bot1967:xxxxxxxx"
  echo "  2) هنگام اجرا بدهید: EITAA_TOKEN='bot1967:xxxxxxxx' bash scripts/sharabe-beheshti-eitaa-test.sh"
  echo ""
  echo "کلیدهای موجود در .env (بدون مقدار):"
  grep -iE 'EITAA|eitaa' .env 2>/dev/null | cut -d= -f1 | sed 's/^/  /' || echo "  (هیچ کلید eitaa در .env نیست)"
  exit 1
fi

echo "token:   ${TOKEN:0:12}..."
echo ""

php artisan config:clear

echo "--- ensure eitaa rss channel ---"
php artisan app:ensure-eitaa-rss-channel \
  --token="$TOKEN" \
  --target-id="$TARGET_ID"

echo ""
echo "--- preview job output ---"
PREVIEW_ARGS=(
  php artisan app:preview-sharabe-beheshti-rss-job
  --id="$MP3_ID"
  --medium=eitaa
  --token="$TOKEN"
)

if [ "$SEND" = "1" ]; then
  if [ -z "$CHAT_ID" ]; then
    echo "ERROR: برای ارسال واقعی CHAT_ID لازم است."
    echo "مثال: CHAT_ID='-1001234567890' SEND=1 bash scripts/sharabe-beheshti-eitaa-test.sh"
    exit 1
  fi
  echo "--- sending audio to chat_id=$CHAT_ID ---"
  "${PREVIEW_ARGS[@]}" --send --chat-id="$CHAT_ID"
else
  "${PREVIEW_ARGS[@]}"
  echo ""
  echo "برای ارسال واقعی:"
  echo "  EITAA_TOKEN='...' CHAT_ID='شناسه_کانال' SEND=1 bash scripts/sharabe-beheshti-eitaa-test.sh"
fi

echo ""
echo "=== done ==="
