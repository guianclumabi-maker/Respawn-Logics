<?php
$ch = curl_init('http://localhost/respawn-logics/get_csrf.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, 1);
$response = curl_exec($ch);

$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $header_size);
$body = substr($response, $header_size);

preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
$cookies = array();
foreach($matches[1] as $item) {
    parse_str($item, $cookie);
    $cookies = array_merge($cookies, $cookie);
}
$sessionId = $cookies['PHPSESSID'] ?? null;

$data = json_decode($body, true);
$csrfToken = $data['csrf_token'] ?? null;

echo "Obtained CSRF Token: $csrfToken\n";
echo "Obtained Session: $sessionId\n";

if (!$csrfToken || !$sessionId) {
    die("Failed to obtain CSRF or session.\n");
}

// Test update_roles.php
$payload = json_encode(['roles' => ['EMP-001' => 'admin', 'EMP-002' => 'SUPER_GOD_MODE']]);

$ch2 = curl_init('http://localhost/respawn-logics/update_roles.php');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "X-CSRF-Token: $csrfToken",
    "Cookie: PHPSESSID=$sessionId"
]);

$updateResponse = curl_exec($ch2);
$httpcode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
echo "Update Roles Response (Expected 500 or 400 because of SUPER_GOD_MODE): HTTP $httpcode\n";
echo $updateResponse . "\n";

// Test update_roles.php with VALID roles
$payloadValid = json_encode(['roles' => ['EMP-001' => 'admin']]);

$ch3 = curl_init('http://localhost/respawn-logics/update_roles.php');
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch3, CURLOPT_POST, true);
curl_setopt($ch3, CURLOPT_POSTFIELDS, $payloadValid);
curl_setopt($ch3, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "X-CSRF-Token: $csrfToken",
    "Cookie: PHPSESSID=$sessionId"
]);

$updateResponseValid = curl_exec($ch3);
$httpcodeValid = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
echo "Update Roles Response (Expected 200): HTTP $httpcodeValid\n";
echo $updateResponseValid . "\n";

// Test update_roles WITHOUT CSRF
$ch4 = curl_init('http://localhost/respawn-logics/update_roles.php');
curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch4, CURLOPT_POST, true);
curl_setopt($ch4, CURLOPT_POSTFIELDS, $payloadValid);
curl_setopt($ch4, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Cookie: PHPSESSID=$sessionId"
]);

$updateResponseNoCsrf = curl_exec($ch4);
$httpcodeNoCsrf = curl_getinfo($ch4, CURLINFO_HTTP_CODE);
echo "Update Roles Response WITHOUT CSRF (Expected 403): HTTP $httpcodeNoCsrf\n";
echo $updateResponseNoCsrf . "\n";

