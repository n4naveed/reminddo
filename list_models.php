<?php
require __DIR__ . '/vendor/autoload.php';

$envContent = file_get_contents('.env');
preg_match('/GEMINI_API_KEY=(.*)/', $envContent, $matches);
$apiKey = trim($matches[1] ?? '');
$url = "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass SSL for local test

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (isset($data['models'])) {
    foreach ($data['models'] as $model) {
        if (in_array('generateContent', $model['supportedGenerationMethods'] ?? [])) {
            echo $model['name'] . "\n";
        }
    }
} else {
    echo "Error: " . $response;
}
