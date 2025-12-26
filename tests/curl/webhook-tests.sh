#!/bin/bash

# فایل curl برای تست webhook‌ها در حالت لوکال
# بر اساس تست‌های واقعی از Insomnia

BASE_URL="http://localhost:8000"
TOKEN="${BOT_TOKEN:-YOUR_TOKEN_HERE}"
BOT_MOTHER_ID="${BOT_MOTHER_ID:-1}"
LANGUAGE="${LANGUAGE:-fa}"

echo "=========================================="
echo "تست Webhook‌های ربات"
echo "=========================================="
echo ""

# رنگ‌ها برای خروجی
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# تست 1: Webhook با پیام "salam"
echo -e "${BLUE}تست 1: Webhook با پیام 'salam'${NC}"
curl -X POST "${BASE_URL}/api/webhook-bot-mother?origin=bale&token=${TOKEN}&bot_mother_id=${BOT_MOTHER_ID}&language=${LANGUAGE}" \
  -H "Content-Type: application/json" \
  -d '{
    "update_id": 1,
    "message": {
      "message_id": -1515335176,
      "from": {
        "id": 485750575,
        "first_name": "صابر طباطبایی یزدی",
        "username": "sabertaba",
        "is_bot": false
      },
      "date": 1680340954,
      "chat": {
        "id": 485750575,
        "type": "private",
        "username": "sabertaba",
        "first_name": "صابر طباطبایی یزدی"
      },
      "text": "salam"
    }
  }'
echo -e "\n${GREEN}✓ تست 1 انجام شد${NC}\n"

# تست 2: Webhook با دستور /start
echo -e "${BLUE}تست 2: Webhook با دستور /start${NC}"
curl -X POST "${BASE_URL}/api/webhook-bot-mother?origin=bale&token=${TOKEN}&bot_mother_id=${BOT_MOTHER_ID}&language=${LANGUAGE}" \
  -H "Content-Type: application/json" \
  -d '{
    "update_id": 2,
    "message": {
      "message_id": -687281639,
      "from": {
        "id": 485750575,
        "first_name": "صابر طباطبایی یزدی",
        "username": "sabertaba",
        "is_bot": false
      },
      "date": 1680438429,
      "chat": {
        "id": 485750575,
        "type": "private",
        "username": "sabertaba",
        "first_name": "صابر طباطبایی یزدی"
      },
      "text": "/start"
    }
  }'
echo -e "\n${GREEN}✓ تست 2 انجام شد${NC}\n"

# تست 3: Webhook با دستور /new_bot
echo -e "${BLUE}تست 3: Webhook با دستور /new_bot${NC}"
curl -X POST "${BASE_URL}/api/webhook-bot-mother?origin=bale&token=${TOKEN}&bot_mother_id=${BOT_MOTHER_ID}&language=${LANGUAGE}" \
  -H "Content-Type: application/json" \
  -d '{
    "update_id": 1,
    "message": {
      "message_id": -1515335176,
      "from": {
        "id": 485750575,
        "first_name": "صابر طباطبایی یزدی",
        "username": "sabertaba",
        "is_bot": false
      },
      "date": 1680340954,
      "chat": {
        "id": 485750575,
        "type": "private",
        "username": "sabertaba",
        "first_name": "صابر طباطبایی یزدی"
      },
      "text": "/new_bot"
    }
  }'
echo -e "\n${GREEN}✓ تست 3 انجام شد${NC}\n"

# تست 4: Webhook با ارسال Token
echo -e "${BLUE}تست 4: Webhook با ارسال Token${NC}"
curl -X POST "${BASE_URL}/api/webhook-bot-mother?origin=bale&token=${TOKEN}&bot_mother_id=${BOT_MOTHER_ID}&language=${LANGUAGE}" \
  -H "Content-Type: application/json" \
  -d '{
    "update_id": 2,
    "message": {
      "message_id": -687281639,
      "from": {
        "id": 485750575,
        "first_name": "صابر طباطبایی یزدی",
        "username": "sabertaba",
        "is_bot": false
      },
      "date": 1680438429,
      "chat": {
        "id": 485750575,
        "type": "private",
        "username": "sabertaba",
        "first_name": "صابر طباطبایی یزدی"
      },
      "text": "737102910:Kj1bsD3XeCEjnOPcVBOmrwlGNON7BYPTd171L8Qj"
    }
  }'
echo -e "\n${GREEN}✓ تست 4 انجام شد${NC}\n"

# تست 5: Webhook Users با Query Params
echo -e "${BLUE}تست 5: Webhook Users با Query Params${NC}"
curl -X POST "${BASE_URL}/api/webhook-bot-children?bot_user_name=Testchannelbot&bot_token=737102910:Kj1bsD3XeCEjnOPcVBOmrwlGNON7BYPTd171L8Qj&origin=bale" \
  -H "Content-Type: application/json" \
  -d '{
    "update_id": 2,
    "message": {
      "message_id": -687281639,
      "from": {
        "id": 485750575,
        "first_name": "صابر طباطبایی یزدی",
        "username": "sabertaba",
        "is_bot": false
      },
      "date": 1680438429,
      "chat": {
        "id": 485750575,
        "type": "private",
        "username": "sabertaba",
        "first_name": "صابر طباطبایی یزدی"
      },
      "text": "/start"
    }
  }'
echo -e "\n${GREEN}✓ تست 5 انجام شد${NC}\n"

# تست 6: Webhook Quran Ayat
echo -e "${BLUE}تست 6: Webhook Quran Ayat${NC}"
curl -X POST "${BASE_URL}/api/webhook-quran-ayat?origin=bale&token=${TOKEN}&bot_mother_id=${BOT_MOTHER_ID}&language=${LANGUAGE}" \
  -H "Content-Type: application/json" \
  -d '{
    "update_id": 3,
    "message": {
      "message_id": -1515335176,
      "from": {
        "id": 485750575,
        "first_name": "صابر طباطبایی یزدی",
        "username": "sabertaba",
        "is_bot": false
      },
      "date": 1680340954,
      "chat": {
        "id": 485750575,
        "type": "private",
        "username": "sabertaba",
        "first_name": "صابر طباطبایی یزدی"
      },
      "text": "/1"
    }
  }'
echo -e "\n${GREEN}✓ تست 6 انجام شد${NC}\n"

# تست 7: Webhook Weather
echo -e "${BLUE}تست 7: Webhook Weather${NC}"
curl -X POST "${BASE_URL}/api/webhook-weather?origin=bale&token=${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "update_id": 4,
    "message": {
      "message_id": -1515335176,
      "from": {
        "id": 485750575,
        "first_name": "صابر طباطبایی یزدی",
        "username": "sabertaba",
        "is_bot": false
      },
      "date": 1680340954,
      "chat": {
        "id": 485750575,
        "type": "private",
        "username": "sabertaba",
        "first_name": "صابر طباطبایی یزدی"
      },
      "text": "/current"
    }
  }'
echo -e "\n${GREEN}✓ تست 7 انجام شد${NC}\n"

# تست 8: Webhook Hadith
echo -e "${BLUE}تست 8: Webhook Hadith${NC}"
curl -X POST "${BASE_URL}/api/webhook-hadith?origin=bale&token=${TOKEN}&bot_mother_id=${BOT_MOTHER_ID}&language=${LANGUAGE}" \
  -H "Content-Type: application/json" \
  -d '{
    "update_id": 5,
    "message": {
      "message_id": -1515335176,
      "from": {
        "id": 485750575,
        "first_name": "صابر طباطبایی یزدی",
        "username": "sabertaba",
        "is_bot": false
      },
      "date": 1680340954,
      "chat": {
        "id": 485750575,
        "type": "private",
        "username": "sabertaba",
        "first_name": "صابر طباطبایی یزدی"
      },
      "text": "/search صبر"
    }
  }'
echo -e "\n${GREEN}✓ تست 8 انجام شد${NC}\n"

# تست 9: Callback Query (کلیک روی دکمه)
echo -e "${BLUE}تست 9: Callback Query (کلیک روی دکمه)${NC}"
curl -X POST "${BASE_URL}/api/webhook-quran-word?origin=bale&token=${TOKEN}&bot_mother_id=${BOT_MOTHER_ID}&language=${LANGUAGE}" \
  -H "Content-Type: application/json" \
  -d '{
    "update_id": 6,
    "callback_query": {
      "id": "cq_123456789",
      "from": {
        "id": 485750575,
        "first_name": "صابر طباطبایی یزدی",
        "username": "sabertaba",
        "is_bot": false
      },
      "message": {
        "message_id": -1515335176,
        "chat": {
          "id": 485750575,
          "type": "private"
        }
      },
      "data": "/1"
    }
  }'
echo -e "\n${GREEN}✓ تست 9 انجام شد${NC}\n"

echo ""
echo "=========================================="
echo -e "${GREEN}تمام تست‌ها انجام شد!${NC}"
echo "=========================================="


