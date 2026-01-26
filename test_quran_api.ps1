# PowerShell script for testing Quran API
# Base URL
$baseUrl = "https://bots.pardisania.ir"

Write-Host "=== Testing Quran API Endpoints ===" -ForegroundColor Yellow
Write-Host ""

# Test 1: Get Languages
Write-Host "Test 1: GET /api/v1/quran/languages" -ForegroundColor Green
$response = Invoke-RestMethod -Uri "$baseUrl/api/v1/quran/languages" -Method Get -ContentType "application/json"
$response | ConvertTo-Json -Depth 10
Write-Host ""

# Test 2: Get Translations for a language
Write-Host "Test 2: GET /api/v1/quran/translations?language=fa" -ForegroundColor Green
$response = Invoke-RestMethod -Uri "$baseUrl/api/v1/quran/translations?language=fa" -Method Get -ContentType "application/json"
$response | ConvertTo-Json -Depth 10
Write-Host ""

# Test 3: Get Surahs
Write-Host "Test 3: GET /api/v1/quran/surahs" -ForegroundColor Green
$response = Invoke-RestMethod -Uri "$baseUrl/api/v1/quran/surahs" -Method Get -ContentType "application/json"
$response | ConvertTo-Json -Depth 10 | Select-Object -First 50
Write-Host ""

# Test 4: Get a specific Ayah
Write-Host "Test 4: GET /api/v1/quran/surahs/1/ayahs/1" -ForegroundColor Green
$response = Invoke-RestMethod -Uri "$baseUrl/api/v1/quran/surahs/1/ayahs/1?language=fa&translator=ansarian" -Method Get -ContentType "application/json"
$response | ConvertTo-Json -Depth 10
Write-Host ""

# Test 5: Get Word by ID
Write-Host "Test 5: GET /api/v1/quran/words/1" -ForegroundColor Green
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/api/v1/quran/words/1" -Method Get -ContentType "application/json"
    $response | ConvertTo-Json -Depth 10
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
}
Write-Host ""

# Test 6: Get Juz List
Write-Host "Test 6: GET /api/v1/quran/juz" -ForegroundColor Green
$response = Invoke-RestMethod -Uri "$baseUrl/api/v1/quran/juz" -Method Get -ContentType "application/json"
$response | ConvertTo-Json -Depth 10
Write-Host ""

# Test 7: Get Juz Content
Write-Host "Test 7: GET /api/v1/quran/juz/1" -ForegroundColor Green
$response = Invoke-RestMethod -Uri "$baseUrl/api/v1/quran/juz/1" -Method Get -ContentType "application/json"
$response | ConvertTo-Json -Depth 10
Write-Host ""

# Test 8: Search
Write-Host "Test 8: GET /api/v1/quran/search?query=الرحمن" -ForegroundColor Green
$response = Invoke-RestMethod -Uri "$baseUrl/api/v1/quran/search?query=الرحمن&page=1&per_page=5" -Method Get -ContentType "application/json"
$response | ConvertTo-Json -Depth 10
Write-Host ""

# Test 9: Trending (Day)
Write-Host "Test 9: GET /api/v1/quran/trending/day" -ForegroundColor Green
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/api/v1/quran/trending/day?limit=10" -Method Get -ContentType "application/json"
    $response | ConvertTo-Json -Depth 10
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
}
Write-Host ""

# Test 10: Feed
Write-Host "Test 10: GET /api/v1/quran/feed" -ForegroundColor Green
$response = Invoke-RestMethod -Uri "$baseUrl/api/v1/quran/feed?page=1&per_page=5&language=fa" -Method Get -ContentType "application/json"
$response | ConvertTo-Json -Depth 10
Write-Host ""

# Test 11: Audio URL
Write-Host "Test 11: GET /api/v1/quran/audio/1/1" -ForegroundColor Green
$response = Invoke-RestMethod -Uri "$baseUrl/api/v1/quran/audio/1/1?reciter=parhizgar" -Method Get -ContentType "application/json"
$response | ConvertTo-Json -Depth 10
Write-Host ""

# Test 12: Get User Settings (requires token)
Write-Host "Test 12: GET /api/v1/quran/user/settings (requires authentication)" -ForegroundColor Yellow
Write-Host "Note: Replace YOUR_TOKEN with actual token" -ForegroundColor Yellow
try {
    $headers = @{
        "X-API-Token" = "YOUR_TOKEN"
    }
    $response = Invoke-RestMethod -Uri "$baseUrl/api/v1/quran/user/settings?chat_id=123456789" -Method Get -Headers $headers -ContentType "application/json"
    $response | ConvertTo-Json -Depth 10
} catch {
    Write-Host "Error (expected without valid token): $_" -ForegroundColor Red
}
Write-Host ""

# Test 13: Update User Settings (requires token)
Write-Host "Test 13: POST /api/v1/quran/user/settings (requires authentication)" -ForegroundColor Yellow
Write-Host "Note: Replace YOUR_TOKEN with actual token" -ForegroundColor Yellow
try {
    $headers = @{
        "X-API-Token" = "YOUR_TOKEN"
    }
    $body = @{
        chat_id = "123456789"
        mp3_enable = $true
        mp3_reciter = "parhizgar"
        quran_translation_language = "fa"
        quran_translation_translator = "ansarian"
    } | ConvertTo-Json
    
    $response = Invoke-RestMethod -Uri "$baseUrl/api/v1/quran/user/settings" -Method Post -Headers $headers -Body $body -ContentType "application/json"
    $response | ConvertTo-Json -Depth 10
} catch {
    Write-Host "Error (expected without valid token): $_" -ForegroundColor Red
}
Write-Host ""

# Test 14: Update Translation (requires token)
Write-Host "Test 14: POST /api/v1/quran/user/settings/translation (requires authentication)" -ForegroundColor Yellow
Write-Host "Note: Replace YOUR_TOKEN with actual token" -ForegroundColor Yellow
try {
    $headers = @{
        "X-API-Token" = "YOUR_TOKEN"
    }
    $body = @{
        chat_id = "123456789"
        language = "fa"
        translator = "ansarian"
    } | ConvertTo-Json
    
    $response = Invoke-RestMethod -Uri "$baseUrl/api/v1/quran/user/settings/translation" -Method Post -Headers $headers -Body $body -ContentType "application/json"
    $response | ConvertTo-Json -Depth 10
} catch {
    Write-Host "Error (expected without valid token): $_" -ForegroundColor Red
}
Write-Host ""

Write-Host "=== All Tests Completed ===" -ForegroundColor Green
