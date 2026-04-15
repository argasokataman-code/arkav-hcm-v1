<?php
/**
 * Quick API test for attendance selfie endpoint
 * Usage: php test-selfie-api.php
 */

// Test configuration
$apiUrl = 'http://localhost:8000/api/v1';

// Use existing seeded user token (from development seeder)
// Or: use any user token if you already have one
$testToken = null;

// Simple base64 test image (small 1x1 JPG)
$testImage = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8VAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=';

echo "\n=== Attendance Selfie API Test ===\n";
echo "API Base URL: {$apiUrl}\n\n";

// Step 1: List available test users
echo "[1] Available test users from seeder:\n";
echo "    - Email: dummyadmin@arcav.test / PWD: password\n";
echo "    - Email: employee1@arcav.test / PWD: password\n";
echo "    - Email: employee2@arcav.test / PWD: password\n";
echo "\nTo get token, login via UI or use:\n";
echo "    curl -X POST http://localhost:8000/api/v1/login \\\n";
echo "      -H 'Content-Type: application/json' \\\n";
echo "      -d '{\"email\":\"employee1@arcav.test\",\"password\":\"password\"}'\n\n";

// Step 2: Prompt for token
echo "[2] Enter Bearer token (from login response) [ENTER to skip]: ";
$testToken = trim(fgets(STDIN));

if (empty($testToken)) {
    echo "Skipping API test. Use manual testing instead.\n";
    exit(0);
}

// Step 3: Get current attendance status
echo "\n[3] Checking current attendance status...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "{$apiUrl}/attendance/me/today",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "Authorization: Bearer {$testToken}",
        'Accept: application/json',
        'X-Company-Id: 1',
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP {$httpCode}\n";
if ($httpCode == 200) {
    $data = json_decode($response, true);
    echo "Attendance found for today: " . ($data['data']['work_date'] ?? 'N/A') . "\n";
} else {
    echo "No attendance for today. Please punch in first.\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
}

// Step 4: Test selfie upload
echo "\n[4] Testing POST /api/v1/attendance/me/selfie...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "{$apiUrl}/attendance/me/selfie",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "Authorization: Bearer {$testToken}",
        'Accept: application/json',
        'X-Company-Id: 1',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'selfie_base64' => $testImage,
        'timestamp' => time(),
    ]),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: {$httpCode}\n";
$data = json_decode($response, true);

if ($httpCode == 200 && $data['success']) {
    echo "✓ Selfie uploaded successfully!\n";
    echo "  Path: " . $data['data']['selfie_path'] . "\n";
    echo "  Uploaded: " . $data['data']['uploaded_at'] . "\n";
    
    // Step 5: Verify hash was stored
    echo "\n[5] Checking database for hash...\n";
    echo "Query database:\n";
    echo "  SELECT selfie_path, selfie_encrypted_hash FROM attendance_records\n";
    echo "  WHERE user_id = YOUR_USER_ID AND work_date = '" . date('Y-m-d') . "';\n";
    echo "\nExpected: selfie_encrypted_hash should be 64 chars (SHA256)\n";
} else {
    echo "✗ Upload failed!\n";
    echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
}

// Step 6: Test status endpoint
echo "\n[6] Testing GET /api/v1/attendance/me/selfie/status...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "{$apiUrl}/attendance/me/selfie/status",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "Authorization: Bearer {$testToken}",
        'Accept: application/json',
        'X-Company-Id: 1',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: {$httpCode}\n";
$data = json_decode($response, true);
echo "Has Selfie: " . ($data['has_selfie'] ? 'YES' : 'NO') . "\n";
if ($data['has_selfie']) {
    echo "✓ Selfie status endpoint working!\n";
}

echo "\n=== Test Complete ===\n\n";
