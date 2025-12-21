<?php
// test.php file create karo
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Script</h1>";

// Check BOT_TOKEN
$bot_token = getenv('BOT_TOKEN');
if (!$bot_token) {
    die("❌ BOT_TOKEN environment variable nahi mila!");
}

echo "✅ BOT_TOKEN found: " . substr($bot_token, 0, 10) . "...<br>";

// Test API
$url = "https://api.telegram.org/bot" . $bot_token . "/getMe";
$result = @file_get_contents($url);

if ($result === false) {
    echo "❌ Telegram API connection failed<br>";
} else {
    $data = json_decode($result, true);
    if ($data['ok']) {
        echo "✅ Bot connected: @" . $data['result']['username'] . "<br>";
    } else {
        echo "❌ Bot connection error: " . $data['description'] . "<br>";
    }
}

// Check file permissions
$files = ['movies.csv', 'users.json', 'bot_stats.json', 'bot_activity.log'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists (Permission: " . substr(sprintf('%o', fileperms($file)), -4) . ")<br>";
    } else {
        echo "⚠️ $file not found<br>";
    }
}
?>
