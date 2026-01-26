#!/bin/bash

# Base URL
BASE_URL="https://bots.pardisania.ir"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== Testing Quran API Endpoints ===${NC}\n"

# Test 1: Get Languages
echo -e "${GREEN}Test 1: GET /api/v1/quran/languages${NC}"
curl -X GET "${BASE_URL}/api/v1/quran/languages" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo -e "\n"

# Test 2: Get Translations for a language
echo -e "${GREEN}Test 2: GET /api/v1/quran/translations?language=fa${NC}"
curl -X GET "${BASE_URL}/api/v1/quran/translations?language=fa" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo -e "\n"

# Test 3: Get Surahs
echo -e "${GREEN}Test 3: GET /api/v1/quran/surahs${NC}"
curl -X GET "${BASE_URL}/api/v1/quran/surahs" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' | head -50 || echo "Response received"
echo -e "\n"

# Test 4: Get a specific Ayah
echo -e "${GREEN}Test 4: GET /api/v1/quran/surahs/1/ayahs/1${NC}"
curl -X GET "${BASE_URL}/api/v1/quran/surahs/1/ayahs/1?language=fa&translator=ansarian" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo -e "\n"

# Test 5: Get Word by ID
echo -e "${GREEN}Test 5: GET /api/v1/quran/words/1${NC}"
curl -X GET "${BASE_URL}/api/v1/quran/words/1" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo -e "\n"

# Test 6: Get Juz List
echo -e "${GREEN}Test 6: GET /api/v1/quran/juz${NC}"
curl -X GET "${BASE_URL}/api/v1/quran/juz" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' | head -30 || echo "Response received"
echo -e "\n"

# Test 7: Get Juz Content
echo -e "${GREEN}Test 7: GET /api/v1/quran/juz/1${NC}"
curl -X GET "${BASE_URL}/api/v1/quran/juz/1" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo -e "\n"

# Test 8: Search
echo -e "${GREEN}Test 8: GET /api/v1/quran/search?query=الرحمن${NC}"
curl -X GET "${BASE_URL}/api/v1/quran/search?query=الرحمن&page=1&per_page=5" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo -e "\n"

# Test 9: Trending (Day)
echo -e "${GREEN}Test 9: GET /api/v1/quran/trending/day${NC}"
curl -X GET "${BASE_URL}/api/v1/quran/trending/day?limit=10" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo -e "\n"

# Test 10: Feed
echo -e "${GREEN}Test 10: GET /api/v1/quran/feed${NC}"
curl -X GET "${BASE_URL}/api/v1/quran/feed?page=1&per_page=5&language=fa" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo -e "\n"

# Test 11: Audio URL
echo -e "${GREEN}Test 11: GET /api/v1/quran/audio/1/1${NC}"
curl -X GET "${BASE_URL}/api/v1/quran/audio/1/1?reciter=parhizgar" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received"
echo -e "\n"

# Test 12: Get User Settings (requires token - will likely fail without auth)
echo -e "${YELLOW}Test 12: GET /api/v1/quran/user/settings (requires authentication)${NC}"
echo -e "${YELLOW}Note: This endpoint requires API token. Replace YOUR_TOKEN with actual token.${NC}"
curl -X GET "${BASE_URL}/api/v1/quran/user/settings?chat_id=123456789" \
  -H "Accept: application/json" \
  -H "X-API-Token: YOUR_TOKEN" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received (may fail without valid token)"
echo -e "\n"

# Test 13: Update User Settings (requires token - will likely fail without auth)
echo -e "${YELLOW}Test 13: POST /api/v1/quran/user/settings (requires authentication)${NC}"
echo -e "${YELLOW}Note: This endpoint requires API token. Replace YOUR_TOKEN with actual token.${NC}"
curl -X POST "${BASE_URL}/api/v1/quran/user/settings" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-API-Token: YOUR_TOKEN" \
  -d '{
    "chat_id": "123456789",
    "mp3_enable": true,
    "mp3_reciter": "parhizgar",
    "quran_translation_language": "fa",
    "quran_translation_translator": "ansarian"
  }' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received (may fail without valid token)"
echo -e "\n"

# Test 14: Update Translation (requires token - will likely fail without auth)
echo -e "${YELLOW}Test 14: POST /api/v1/quran/user/settings/translation (requires authentication)${NC}"
echo -e "${YELLOW}Note: This endpoint requires API token. Replace YOUR_TOKEN with actual token.${NC}"
curl -X POST "${BASE_URL}/api/v1/quran/user/settings/translation" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-API-Token: YOUR_TOKEN" \
  -d '{
    "chat_id": "123456789",
    "language": "fa",
    "translator": "ansarian"
  }' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' || echo "Response received (may fail without valid token)"
echo -e "\n"

echo -e "${GREEN}=== All Tests Completed ===${NC}"
