<?php
/*
===========================================================
🎬 ENTERTAINMENT TADKA TELEGRAM BOT
📱 Version: 3.0.0 | Complete Hinglish Implementation
👨‍💻 Developer: @EntertainmentTadka0786
🌐 Channel: @EntertainmentTadka786
📅 Last Updated: 2024
===========================================================
*/

// ======================================================
// SECURITY SETUP - PEHLA KAAM
// ======================================================
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'");
header("Strict-Transport-Security: max-age=31536000");

// Production mode mein errors hide karo
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Memory limit increase - Large files ke liye
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);

// ======================================================
// RENDER.COM SPECIFIC CONFIG - IMPORTANT FOR DEPLOYMENT
// ======================================================

// PORT environment variable se lo
$port = getenv('PORT') ?: '10000';

// Webhook URL automatically set
$webhook_url = getenv('RENDER_EXTERNAL_URL') ?: 'https://entertainment-tadka-bot.onrender.com';

// SECURITY CHECK - Sabse important
$bot_token = getenv('BOT_TOKEN');
if (!$bot_token) {
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>❌ Bot Setup Error</title>
        <style>
            body { font-family: Arial; padding: 20px; background: #f8f9fa; }
            .container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            .error { color: #dc3545; font-weight: bold; }
            .steps { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .step { margin: 10px 0; }
            code { background: #f1f1f1; padding: 2px 5px; border-radius: 3px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2 class='error'>❌ BOT_TOKEN SET NAHI HAI!</h2>
            <p>Render.com dashboard mein environment variable set karo:</p>
            <div class='steps'>
                <div class='step'>1. Render.com par apna service select karo</div>
                <div class='step'>2. "Environment" section mein jaao</div>
                <div class='step'>3. Add Variable: <code>BOT_TOKEN</code> = apna telegram bot token</div>
                <div class='step'>4. Deploy restart karo</div>
            </div>
            <p>📚 <a href='https://core.telegram.org/bots#how-do-i-create-a-bot' target='_blank'>Bot token kaise banta hai?</a></p>
        </div>
    </body>
    </html>
    ");
}

// ======================================================
// ENVIRONMENT VARIABLES CONFIGURATION
// ======================================================

// Bot credentials - Sab environment variables se
define('BOT_TOKEN', $bot_token);
define('CHANNEL_ID', getenv('CHANNEL_ID') ?: '-1003181705395');
define('BACKUP_CHANNEL_ID', getenv('BACKUP_CHANNEL_ID') ?: '-1002964109368');
define('BACKUP_CHANNEL_USERNAME', getenv('BACKUP_CHANNEL_USERNAME') ?: '@ETBackup');
define('ADMIN_ID', (int)(getenv('ADMIN_ID') ?: '1080317415'));
define('REQUEST_CHANNEL', getenv('REQUEST_CHANNEL') ?: '@EntertainmentTadka7860');
define('MAIN_CHANNEL', getenv('MAIN_CHANNEL') ?: '@EntertainmentTadka786');
define('NEW_PRIVATE_CHANNEL', getenv('NEW_PRIVATE_CHANNEL') ?: '-1003251791991');

// File paths
define('CSV_FILE', __DIR__ . '/movies.csv');
define('USERS_FILE', __DIR__ . '/users.json');
define('STATS_FILE', __DIR__ . '/bot_stats.json');
define('REQUEST_FILE', __DIR__ . '/movie_requests.json');
define('BACKUP_DIR', __DIR__ . '/backups/');
define('LOG_FILE', __DIR__ . '/bot_activity.log');
define('ERROR_LOG_FILE', __DIR__ . '/error_log.log');

// Constants
define('CACHE_EXPIRY', 300); // 5 minutes
define('ITEMS_PER_PAGE', 5);
define('MAX_SEARCH_RESULTS', 15);
define('DAILY_REQUEST_LIMIT', 5);
define('AUTO_BACKUP_HOUR', '03');
define('MAX_FILE_SIZE_UPLOAD', 45 * 1024 * 1024); // 45MB Telegram limit

// ======================================================
// SAFE CURLFILE FUNCTION - MAJOR FIX
// ======================================================
function safe_curl_file($file_path) {
    if (!file_exists($file_path)) {
        bot_log("File not found for CURL: $file_path", 'ERROR');
        return null;
    }
    
    // Security check - Allowed extensions only
    $allowed_extensions = ['csv', 'json', 'txt', 'log', 'bak', 'zip'];
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        bot_log("Invalid file extension for upload: $file_extension", 'ERROR');
        return null;
    }
    
    if (class_exists('CURLFile')) {
        return new CURLFile($file_path);
    } else {
        // Legacy method
        return '@' . realpath($file_path);
    }
}

// ======================================================
// ZIP ARCHIVE CHECK FUNCTION
// ======================================================
function can_create_zip() {
    return class_exists('ZipArchive');
}

// ======================================================
// MAINTENANCE MODE
// ======================================================
$MAINTENANCE_MODE = false;
$MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>\n\nWe're temporarily unavailable for updates.\nWill be back in few days!\n\nThanks for patience 🙏";

// ======================================================
// GLOBAL VARIABLES
// ======================================================
$movie_messages = array();
$movie_cache = array();
$waiting_users = array();
$user_sessions = array();

// ======================================================
// FILE INITIALIZATION SYSTEM
// ======================================================
function initialize_files() {
    // Ensure directories exist
    if (!file_exists(BACKUP_DIR)) {
        @mkdir(BACKUP_DIR, 0755, true);
        bot_log("Backup directory created");
    }
    
    $files = [
        CSV_FILE => "movie_name,message_id,date,video_path,quality,size,language,channel_id\n",
        USERS_FILE => json_encode([
            'users' => [], 
            'total_requests' => 0, 
            'message_logs' => [],
            'daily_stats' => [],
            'last_backup' => null
        ], JSON_PRETTY_PRINT),
        STATS_FILE => json_encode([
            'total_movies' => 0, 
            'total_users' => 0, 
            'total_searches' => 0,
            'total_downloads' => 0,
            'successful_searches' => 0,
            'failed_searches' => 0,
            'daily_activity' => [],
            'last_updated' => date('Y-m-d H:i:s'),
            'server_start_time' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT),
        REQUEST_FILE => json_encode([
            'requests' => [],
            'pending_approval' => [],
            'completed_requests' => [],
            'user_request_count' => []
        ], JSON_PRETTY_PRINT)
    ];
    
    foreach ($files as $file => $content) {
        if (!file_exists($file)) {
            file_put_contents($file, $content);
            @chmod($file, 0644); // Safe permission
            bot_log("File created: " . basename($file));
        }
    }
    
    // Initialize log files
    if (!file_exists(LOG_FILE)) {
        file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] SYSTEM: Files initialized\n");
        @chmod(LOG_FILE, 0644);
    }
    
    if (!file_exists(ERROR_LOG_FILE)) {
        file_put_contents(ERROR_LOG_FILE, "[" . date('Y-m-d H:i:s') . "] ERROR LOG STARTED\n");
        @chmod(ERROR_LOG_FILE, 0644);
    }
}

// Initialize files
initialize_files();

// ======================================================
// ENHANCED LOGGING SYSTEM
// ======================================================
function bot_log($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    
    // Main log
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
    
    // Error log for errors only
    if ($type == 'ERROR' || $type == 'CRITICAL') {
        file_put_contents(ERROR_LOG_FILE, $log_entry, FILE_APPEND);
    }
    
    // Also echo in development
    if (getenv('DEBUG_MODE')) {
        echo "LOG [$type]: $message\n";
    }
}

// ======================================================
// CACHING SYSTEM
// ======================================================
function get_cached_movies() {
    global $movie_cache;
    
    // Cache hit
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < CACHE_EXPIRY) {
        bot_log("Cache hit - " . count($movie_cache['data']) . " movies");
        return $movie_cache['data'];
    }
    
    // Cache miss - load fresh
    bot_log("Cache miss - loading fresh data");
    $movie_cache = [
        'data' => load_and_clean_csv(),
        'timestamp' => time()
    ];
    
    bot_log("Movie cache refreshed - " . count($movie_cache['data']) . " movies");
    return $movie_cache['data'];
}

function clear_movie_cache() {
    global $movie_cache;
    $movie_cache = array();
    bot_log("Movie cache cleared");
}

// ======================================================
// CSV MANAGEMENT - ENHANCED VERSION
// ======================================================
function load_and_clean_csv($filename = CSV_FILE) {
    global $movie_messages;
    
    if (!file_exists($filename)) {
        bot_log("CSV file not found: $filename", 'ERROR');
        file_put_contents($filename, "movie_name,message_id,date,video_path,quality,size,language,channel_id\n");
        return [];
    }

    $data = [];
    $handle = fopen($filename, "r");
    
    if ($handle === FALSE) {
        bot_log("Cannot open CSV file: $filename", 'ERROR');
        return [];
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return [];
    }

    $valid_entries = 0;
    $invalid_entries = 0;
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        // Minimum validation
        if (count($row) >= 3 && !empty(trim($row[0]))) {
            $movie_name = trim($row[0]);
            $message_id_raw = isset($row[1]) ? trim($row[1]) : '';
            $date = isset($row[2]) ? trim($row[2]) : date('d-m-Y');
            $video_path = isset($row[3]) ? trim($row[3]) : '';
            $quality = isset($row[4]) ? trim($row[4]) : 'Unknown';
            $size = isset($row[5]) ? trim($row[5]) : 'Unknown';
            $language = isset($row[6]) ? trim($row[6]) : 'Hindi';
            $channel_id = isset($row[7]) ? trim($row[7]) : CHANNEL_ID;

            $entry = [
                'movie_name' => $movie_name,
                'message_id_raw' => $message_id_raw,
                'date' => $date,
                'video_path' => $video_path,
                'quality' => $quality,
                'size' => $size,
                'language' => $language,
                'channel_id' => $channel_id
            ];
            
            // Try to get numeric message ID
            if (is_numeric($message_id_raw)) {
                $entry['message_id'] = intval($message_id_raw);
            } else {
                $entry['message_id'] = null;
            }

            $data[] = $entry;

            // Add to search index
            $movie_key = strtolower($movie_name);
            if (!isset($movie_messages[$movie_key])) {
                $movie_messages[$movie_key] = [];
            }
            $movie_messages[$movie_key][] = $entry;
            
            $valid_entries++;
        } else {
            $invalid_entries++;
        }
    }
    
    fclose($handle);
    
    // Update stats
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats['total_movies'] = count($data);
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));

    // Clean and rewrite CSV (remove duplicates, empty rows)
    if ($invalid_entries > 0) {
        $handle = fopen($filename, "w");
        fputcsv($handle, $header);
        
        // Remove duplicates based on movie_name + message_id
        $unique_entries = [];
        foreach ($data as $row) {
            $key = $row['movie_name'] . '|' . $row['message_id_raw'];
            if (!isset($unique_entries[$key])) {
                fputcsv($handle, [
                    $row['movie_name'], 
                    $row['message_id_raw'], 
                    $row['date'], 
                    $row['video_path'],
                    $row['quality'],
                    $row['size'],
                    $row['language'],
                    $row['channel_id']
                ]);
                $unique_entries[$key] = true;
            }
        }
        fclose($handle);
        
        bot_log("CSV cleaned: $valid_entries valid, $invalid_entries invalid entries, " . count($unique_entries) . " unique");
    } else {
        bot_log("CSV loaded: $valid_entries entries, no cleaning needed");
    }

    return $data;
}

// ======================================================
// TELEGRAM API FUNCTIONS - BULK IMPROVED
// ======================================================
function apiRequest($method, $params = array(), $is_multipart = false) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    if ($is_multipart) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        $res = curl_exec($ch);
        
        if ($res === false) {
            $error = curl_error($ch);
            bot_log("CURL ERROR ($method): $error", 'ERROR');
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        
        $result = json_decode($res, true);
        if (!$result || !isset($result['ok']) || !$result['ok']) {
            bot_log("API ERROR ($method): " . ($result['description'] ?? 'Unknown error'), 'ERROR');
            return false;
        }
        
        return $res;
        
    } else {
        $options = array(
            'http' => array(
                'method' => 'POST',
                'content' => http_build_query($params),
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'timeout' => 30
            )
        );
        
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        if ($result === false) {
            bot_log("API Request failed for method: $method", 'ERROR');
            return false;
        }
        
        return $result;
    }
}

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = 'HTML', $disable_preview = true) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => $disable_preview
    ];
    
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    
    $result = apiRequest('sendMessage', $data);
    
    if ($result) {
        $result_data = json_decode($result, true);
        if ($result_data && $result_data['ok']) {
            bot_log("Message sent to $chat_id: " . substr($text, 0, 100));
            return $result_data['result'];
        }
    }
    
    return false;
}

function editMessage($chat_id, $message_id, $new_text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $new_text,
        'disable_web_page_preview' => true
    ];
    
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    
    return apiRequest('editMessageText', $data);
}

function deleteMessage($chat_id, $message_id) {
    return apiRequest('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
    $data = [
        'callback_query_id' => $callback_query_id,
        'show_alert' => $show_alert
    ];
    
    if ($text) $data['text'] = $text;
    
    return apiRequest('answerCallbackQuery', $data);
}

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    $result = apiRequest('forwardMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
    
    if ($result) {
        $result_data = json_decode($result, true);
        return $result_data && $result_data['ok'];
    }
    
    return false;
}

function copyMessage($chat_id, $from_chat_id, $message_id) {
    $result = apiRequest('copyMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
    
    if ($result) {
        $result_data = json_decode($result, true);
        return $result_data && $result_data['ok'];
    }
    
    return false;
}

// ======================================================
// MOVIE DELIVERY SYSTEM - MULTI-CHANNEL SUPPORT
// ======================================================
function deliver_item_to_chat($chat_id, $item) {
    $channel_id = $item['channel_id'] ?? CHANNEL_ID;
    
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        // Try forward first
        $forwarded = forwardMessage($chat_id, $channel_id, $item['message_id']);
        
        if ($forwarded) {
            update_stats('total_downloads', 1);
            bot_log("Movie forwarded: {$item['movie_name']} to $chat_id from $channel_id");
            return true;
        } else {
            // Fallback to copy
            $copied = copyMessage($chat_id, $channel_id, $item['message_id']);
            
            if ($copied) {
                update_stats('total_downloads', 1);
                bot_log("Movie copied: {$item['movie_name']} to $chat_id from $channel_id");
                return true;
            }
        }
    }

    // Send as text if no message_id
    $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n";
    $text .= "📊 Quality: " . ($item['quality'] ?? 'Unknown') . "\n";
    $text .= "💾 Size: " . ($item['size'] ?? 'Unknown') . "\n";
    $text .= "🗣️ Language: " . ($item['language'] ?? 'Hindi') . "\n";
    $text .= "📅 Date: " . ($item['date'] ?? 'N/A') . "\n";
    $text .= "🔗 Ref: " . ($item['message_id_raw'] ?? 'N/A') . "\n";
    $text .= "📢 Channel: " . (($channel_id == NEW_PRIVATE_CHANNEL) ? "Private Channel" : "Main Channel");
    
    sendMessage($chat_id, $text, null, 'HTML');
    return false;
}

// ======================================================
// STATISTICS MANAGEMENT
// ======================================================
function update_stats($field, $increment = 1) {
    if (!file_exists(STATS_FILE)) {
        bot_log("Stats file not found", 'ERROR');
        return;
    }
    
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats[$field] = ($stats[$field] ?? 0) + $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    
    // Update daily activity
    $today = date('Y-m-d');
    if (!isset($stats['daily_activity'][$today])) {
        $stats['daily_activity'][$today] = [
            'searches' => 0,
            'downloads' => 0,
            'users' => 0,
            'requests' => 0
        ];
    }
    
    // Map field to daily activity
    $field_map = [
        'total_searches' => 'searches',
        'total_downloads' => 'downloads',
        'total_requests' => 'requests'
    ];
    
    if (isset($field_map[$field])) {
        $stats['daily_activity'][$today][$field_map[$field]] += $increment;
    }
    
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}

function get_stats() {
    if (!file_exists(STATS_FILE)) {
        return [
            'total_movies' => 0,
            'total_users' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    return json_decode(file_get_contents(STATS_FILE), true);
}

// ======================================================
// USER MANAGEMENT SYSTEM
// ======================================================
function update_user_data($user_id, $user_info = []) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id] = [
            'id' => $user_id,
            'first_name' => $user_info['first_name'] ?? '',
            'last_name' => $user_info['last_name'] ?? '',
            'username' => $user_info['username'] ?? '',
            'joined' => date('Y-m-d H:i:s'),
            'last_active' => date('Y-m-d H:i:s'),
            'points' => 100, // Welcome points
            'total_searches' => 0,
            'total_downloads' => 0,
            'request_count' => 0,
            'last_request_date' => null,
            'role' => 'user',
            'banned' => false
        ];
        
        $users_data['total_requests'] = ($users_data['total_requests'] ?? 0) + 1;
        update_stats('total_users', 1);
        
        bot_log("New user registered: $user_id ({$user_info['first_name']})");
        
        // Welcome message for new users
        sendMessage($user_id, 
            "👋 <b>Welcome to Entertainment Tadka!</b>\n\n" .
            "🎁 You received <b>100 welcome points</b>!\n" .
            "🔍 Start searching movies by typing any movie name\n" .
            "📢 Join: " . MAIN_CHANNEL . "\n" .
            "💬 Support: " . REQUEST_CHANNEL, 
            null, 'HTML'
        );
    }
    
    $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    
    return $users_data['users'][$user_id];
}

function update_user_activity($user_id, $action) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id])) {
        return;
    }
    
    $points_map = [
        'search' => 1,
        'found_movie' => 5,
        'daily_login' => 10,
        'movie_request' => 2,
        'download' => 3,
        'feedback' => 5,
        'bug_report' => 10
    ];
    
    $users_data['users'][$user_id]['points'] += ($points_map[$action] ?? 0);
    
    if ($action == 'search') $users_data['users'][$user_id]['total_searches']++;
    if ($action == 'download') $users_data['users'][$user_id]['total_downloads']++;
    if ($action == 'movie_request') $users_data['users'][$user_id]['request_count']++;
    
    $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    
    bot_log("User activity: $user_id - $action");
}

// ======================================================
// SMART SEARCH SYSTEM - ENHANCED
// ======================================================
function smart_search($query) {
    global $movie_messages;
    $query_lower = strtolower(trim($query));
    $results = array();
    
    if (empty($query_lower) || strlen($query_lower) < 2) {
        return $results;
    }
    
    foreach ($movie_messages as $movie => $entries) {
        $score = 0;
        
        // 1. Exact match (highest priority)
        if ($movie == $query_lower) {
            $score = 100;
        }
        // 2. Contains match
        elseif (strpos($movie, $query_lower) !== false) {
            $score = 80 - (strlen($movie) - strlen($query_lower));
        }
        // 3. Word boundary match
        elseif (preg_match('/\b' . preg_quote($query_lower, '/') . '\b/', $movie)) {
            $score = 85;
        }
        // 4. Similarity match
        else {
            similar_text($movie, $query_lower, $similarity);
            if ($similarity > 60) {
                $score = $similarity;
            }
        }
        
        // Quality and language bonuses
        if ($score > 0) {
            foreach ($entries as $entry) {
                if (stripos($entry['quality'] ?? '', '1080') !== false) $score += 5;
                if (stripos($entry['quality'] ?? '', '720') !== false) $score += 3;
                if (stripos($entry['quality'] ?? '', 'hindi') !== false) $score += 2;
                if (stripos($entry['language'] ?? '', 'dual') !== false) $score += 4;
            }
            
            $results[$movie] = [
                'score' => $score,
                'count' => count($entries),
                'latest_entry' => end($entries),
                'qualities' => array_unique(array_column($entries, 'quality')),
                'languages' => array_unique(array_column($entries, 'language')),
                'channels' => array_unique(array_column($entries, 'channel_id'))
            ];
        }
    }
    
    // Sort by score (highest first)
    uasort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
}

function detect_language($text) {
    // Hindi character detection
    if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) {
        return 'hindi';
    }
    
    $hindi_keywords = ['फिल्म', 'मूवी', 'डाउनलोड', 'हिंदी', 'चाहिए', 'कहाँ', 'कैसे'];
    $english_keywords = ['movie', 'download', 'watch', 'search', 'find', 'looking'];
    
    $hindi_score = 0;
    $english_score = 0;
    
    $text_lower = strtolower($text);
    
    foreach ($hindi_keywords as $k) {
        if (strpos($text, $k) !== false) $hindi_score++;
    }
    
    foreach ($english_keywords as $k) {
        if (strpos($text_lower, $k) !== false) $english_score++;
    }
    
    return $hindi_score >= $english_score ? 'hindi' : 'english';
}

function send_multilingual_response($chat_id, $message_type, $language = 'english') {
    $responses = [
        'hindi' => [
            'welcome' => "🎬 Boss, kis movie ki talash hai?",
            'found' => "✅ Mil gayi! Movie forward ho rahi hai...",
            'not_found' => "😔 Yeh movie abhi available nahi hai!\n\n📝 Aap ise request kar sakte hain: " . REQUEST_CHANNEL . "\n\n🔔 Jab bhi yeh add hogi, main automatically bhej dunga!",
            'searching' => "🔍 Dhoondh raha hoon... Zara wait karo",
            'multiple_found' => "🎯 Kai versions mili hain! Aap konsi chahte hain?",
            'request_success' => "✅ Request receive ho gayi! Hum jald hi add karenge.",
            'request_limit' => "❌ Aaj ke liye aap maximum " . DAILY_REQUEST_LIMIT . " requests hi kar sakte hain.",
            'no_query' => "❌ Zara movie ka naam to batao!",
            'short_query' => "❌ Kam se kam 2 characters likho!"
        ],
        'english' => [
            'welcome' => "🎬 Boss, which movie are you looking for?",
            'found' => "✅ Found it! Forwarding the movie...",
            'not_found' => "😔 This movie isn't available yet!\n\n📝 You can request it here: " . REQUEST_CHANNEL . "\n\n🔔 I'll send it automatically once it's added!",
            'searching' => "🔍 Searching... Please wait",
            'multiple_found' => "🎯 Multiple versions found! Which one do you want?",
            'request_success' => "✅ Request received! We'll add it soon.",
            'request_limit' => "❌ You've reached the daily limit of " . DAILY_REQUEST_LIMIT . " requests.",
            'no_query' => "❌ Please enter a movie name!",
            'short_query' => "❌ Please enter at least 2 characters!"
        ]
    ];
    
    return sendMessage($chat_id, $responses[$language][$message_type] ?? $responses['english'][$message_type]);
}

// ======================================================
// ADVANCED SEARCH FUNCTION - COMPLETE
// ======================================================
function advanced_search($chat_id, $query, $user_id = null) {
    global $movie_messages, $waiting_users;
    
    // Trim and validate
    $q = trim($query);
    if (empty($q)) {
        $lang = detect_language($query);
        send_multilingual_response($chat_id, 'no_query', $lang);
        return;
    }
    
    if (strlen($q) < 2) {
        $lang = detect_language($query);
        send_multilingual_response($chat_id, 'short_query', $lang);
        return;
    }
    
    $q_lower = strtolower($q);
    
    // Enhanced invalid keyword filtering
    $invalid_keywords = [
        // Technical terms
        'vlc', 'audio', 'track', 'change', 'open', 'play', 'video', 'sound',
        'subtitle', 'player', 'codec', 'format', 'convert', 'error',
        'not working', 'problem', 'issue', 'help', 'solution', 'fix',
        
        // Common words (non-movie)
        'hi', 'hello', 'hey', 'good', 'morning', 'night', 'bye',
        'thanks', 'thank', 'ok', 'okay', 'yes', 'no', 'maybe',
        'who', 'when', 'where', 'why', 'how', 'can', 'should',
        'please', 'plz', 'pls', 'sorry', 'excuse', 'me',
        
        // Hindi common words
        'kaise', 'kya', 'kahan', 'kab', 'kyun', 'kon', 'kisne',
        'hai', 'hain', 'ho', 'raha', 'rah', 'tha', 'thi',
        'mere', 'apne', 'tumhare', 'hamare', 'sab', 'log', 'group'
    ];
    
    // Smart validation
    $query_words = explode(' ', $q_lower);
    $total_words = count($query_words);
    
    if ($total_words == 1 && in_array($query_words[0], $invalid_keywords)) {
        $help_msg = "🎬 <b>Please enter a movie name!</b>\n\n";
        $help_msg .= "🔍 <b>Examples of valid movie names:</b>\n";
        $help_msg .= "• kgf\n• pushpa\n• avengers\n• hindi movie\n• spider-man\n\n";
        $help_msg .= "❌ <b>Technical queries</b> like 'vlc', 'audio track', etc. are not movie names.\n\n";
        $help_msg .= "📢 Join: " . MAIN_CHANNEL . "\n";
        $help_msg .= "💬 Help: " . REQUEST_CHANNEL;
        sendMessage($chat_id, $help_msg, null, 'HTML');
        return;
    }
    
    // Perform search
    $found = smart_search($q);
    
    if (!empty($found)) {
        update_stats('successful_searches', 1);
        
        // Create response message
        $msg = "🔍 <b>Found " . count($found) . " results for '" . htmlspecialchars($query) . "'</b>\n\n";
        
        $i = 1;
        foreach ($found as $movie => $data) {
            $quality_info = !empty($data['qualities']) ? implode('/', $data['qualities']) : 'Unknown';
            $lang_info = !empty($data['languages']) ? implode('/', $data['languages']) : 'Hindi';
            
            $msg .= "<b>$i. " . ucwords($movie) . "</b>\n";
            $msg .= "   📊 " . $data['count'] . " versions | " . $quality_info . " | " . $lang_info . "\n\n";
            
            $i++;
            if ($i > 10) break;
        }
        
        if (count($found) > 10) {
            $msg .= "... and " . (count($found) - 10) . " more results\n\n";
        }
        
        // Create inline keyboard
        $keyboard = ['inline_keyboard' => []];
        $top_movies = array_slice(array_keys($found), 0, 5);
        
        foreach ($top_movies as $movie) {
            $keyboard['inline_keyboard'][] = [[ 
                'text' => "🎬 " . ucwords($movie), 
                'callback_data' => 'movie_' . base64_encode($movie)
            ]];
        }
        
        // Add action buttons
        $keyboard['inline_keyboard'][] = [
            ['text' => "📝 Request Different", 'callback_data' => 'request_movie'],
            ['text' => "📊 Browse All", 'callback_data' => 'browse_all']
        ];
        
        $keyboard['inline_keyboard'][] = [
            ['text' => "🔍 Search Again", 'switch_inline_query_current_chat' => ''],
            ['text' => "📢 Join Channel", 'url' => 'https://t.me/EntertainmentTadka786']
        ];
        
        sendMessage($chat_id, $msg, $keyboard, 'HTML');
        
        if ($user_id) {
            update_user_activity($user_id, 'found_movie');
            update_user_activity($user_id, 'search');
        }
        
    } else {
        update_stats('failed_searches', 1);
        
        $lang = detect_language($query);
        send_multilingual_response($chat_id, 'not_found', $lang);
        
        // Auto-suggest request
        $request_keyboard = [
            'inline_keyboard' => [[
                ['text' => '📝 Request This Movie', 'callback_data' => 'auto_request_' . base64_encode($query)],
                ['text' => '🔍 Search Again', 'switch_inline_query_current_chat' => '']
            ]]
        ];
        
        sendMessage($chat_id, "💡 Click below to automatically request this movie:", $request_keyboard);
        
        // Add to waiting list
        if (!isset($waiting_users[$q_lower])) {
            $waiting_users[$q_lower] = [];
        }
        $waiting_users[$q_lower][] = [$chat_id, $user_id ?? $chat_id];
    }
    
    update_stats('total_searches', 1);
    if ($user_id) {
        update_user_activity($user_id, 'search');
    }
    
    bot_log("Search performed: '$query' by $user_id - Results: " . count($found));
}

// ======================================================
// MOVIE REQUEST SYSTEM
// ======================================================
function can_user_request($user_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $today = date('Y-m-d');
    
    $user_requests_today = 0;
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id && $request['date'] == $today) {
            $user_requests_today++;
        }
    }
    
    return $user_requests_today < DAILY_REQUEST_LIMIT;
}

function add_movie_request($user_id, $movie_name, $language = 'hindi') {
    if (!can_user_request($user_id)) {
        return false;
    }
    
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    
    $request_id = uniqid('req_');
    $requests_data['requests'][] = [
        'id' => $request_id,
        'user_id' => $user_id,
        'movie_name' => $movie_name,
        'language' => $language,
        'date' => date('Y-m-d'),
        'time' => date('H:i:s'),
        'status' => 'pending',
        'priority' => 'normal'
    ];
    
    // Update user request count
    if (!isset($requests_data['user_request_count'][$user_id])) {
        $requests_data['user_request_count'][$user_id] = 0;
    }
    $requests_data['user_request_count'][$user_id]++;
    
    file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
    
    // Notify admin
    $admin_msg = "🎯 <b>New Movie Request</b>\n\n";
    $admin_msg .= "🎬 Movie: <code>" . htmlspecialchars($movie_name) . "</code>\n";
    $admin_msg .= "🗣️ Language: $language\n";
    $admin_msg .= "👤 User ID: <code>$user_id</code>\n";
    $admin_msg .= "📅 Date: " . date('Y-m-d H:i:s') . "\n";
    $admin_msg .= "🆔 Request ID: <code>$request_id</code>\n\n";
    $admin_msg .= "✅ Use: <code>/approve $request_id</code> to approve";
    
    $admin_keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '✅ Approve', 'callback_data' => 'approve_request_' . $request_id],
                ['text' => '❌ Reject', 'callback_data' => 'reject_request_' . $request_id]
            ]
        ]
    ];
    
    sendMessage(ADMIN_ID, $admin_msg, $admin_keyboard, 'HTML');
    
    update_stats('total_requests', 1);
    bot_log("Movie request added: $movie_name by $user_id (ID: $request_id)");
    
    return $request_id;
}

// ======================================================
// PAGINATION SYSTEM - COMPLETE
// ======================================================
function get_all_movies_list($filter_channel = null) {
    $all = get_cached_movies();
    
    if ($filter_channel) {
        $filtered = [];
        foreach ($all as $movie) {
            if ($movie['channel_id'] == $filter_channel) {
                $filtered[] = $movie;
            }
        }
        return $filtered;
    }
    
    return $all;
}

function paginate_movies(array $all, int $page): array {
    $total = count($all);
    if ($total === 0) {
        return [
            'total' => 0,
            'total_pages' => 1, 
            'page' => 1,
            'slice' => []
        ];
    }
    
    $total_pages = (int)ceil($total / ITEMS_PER_PAGE);
    $page = max(1, min($page, $total_pages));
    $start = ($page - 1) * ITEMS_PER_PAGE;
    
    return [
        'total' => $total,
        'total_pages' => $total_pages,
        'page' => $page,
        'slice' => array_slice($all, $start, ITEMS_PER_PAGE)
    ];
}

function build_totalupload_keyboard(int $page, int $total_pages, $filter_channel = null): array {
    $kb = ['inline_keyboard' => []];
    
    // Navigation buttons
    $nav_row = [];
    if ($page > 1) {
        $nav_row[] = ['text' => '⬅️ Previous', 'callback_data' => 'tu_prev_' . ($page - 1) . ($filter_channel ? '_' . $filter_channel : '')];
    }
    
    $nav_row[] = ['text' => "📄 $page/$total_pages", 'callback_data' => 'current_page'];
    
    if ($page < $total_pages) {
        $nav_row[] = ['text' => 'Next ➡️', 'callback_data' => 'tu_next_' . ($page + 1) . ($filter_channel ? '_' . $filter_channel : '')];
    }
    
    if (!empty($nav_row)) {
        $kb['inline_keyboard'][] = $nav_row;
    }
    
    // Channel filter buttons
    if (!$filter_channel) {
        $channel_row = [
            ['text' => '📢 Main Channel', 'callback_data' => 'filter_channel_' . CHANNEL_ID],
            ['text' => '🔒 Private Channel', 'callback_data' => 'filter_channel_' . NEW_PRIVATE_CHANNEL]
        ];
        $kb['inline_keyboard'][] = $channel_row;
    } else {
        $kb['inline_keyboard'][] = [
            ['text' => '🔙 All Channels', 'callback_data' => 'filter_channel_all']
        ];
    }
    
    // Action buttons
    $action_row = [
        ['text' => '🎬 Send This Page', 'callback_data' => 'tu_view_' . $page . ($filter_channel ? '_' . $filter_channel : '')],
        ['text' => '📊 Page Info', 'callback_data' => 'tu_info_' . $page . ($filter_channel ? '_' . $filter_channel : '')],
        ['text' => '🛑 Stop', 'callback_data' => 'tu_stop']
    ];
    $kb['inline_keyboard'][] = $action_row;
    
    // Quick jump buttons for many pages
    if ($total_pages > 5) {
        $jump_row = [];
        if ($page > 1) {
            $jump_row[] = ['text' => '⏮️ First', 'callback_data' => 'tu_prev_1' . ($filter_channel ? '_' . $filter_channel : '')];
        }
        if ($page < $total_pages) {
            $jump_row[] = ['text' => 'Last ⏭️', 'callback_data' => 'tu_next_' . $total_pages . ($filter_channel ? '_' . $filter_channel : '')];
        }
        if (!empty($jump_row)) {
            $kb['inline_keyboard'][] = $jump_row;
        }
    }
    
    return $kb;
}

function totalupload_controller($chat_id, $page = 1, $filter_channel = null) {
    $all = get_all_movies_list($filter_channel);
    
    if (empty($all)) {
        $msg = "📭 No movies found!";
        if ($filter_channel) {
            $msg .= "\n\nChannel: " . ($filter_channel == NEW_PRIVATE_CHANNEL ? "Private Channel" : "Main Channel");
        }
        sendMessage($chat_id, $msg);
        return;
    }
    
    $pg = paginate_movies($all, (int)$page);
    
    // Forward current page movies
    forward_page_movies($chat_id, $pg['slice']);
    
    // Build detailed message
    $channel_name = $filter_channel == NEW_PRIVATE_CHANNEL ? "Private Channel" : 
                   ($filter_channel == CHANNEL_ID ? "Main Channel" : "All Channels");
    
    $title = "🎬 <b>Total Uploads - $channel_name</b>\n\n";
    $title .= "📊 <b>Statistics:</b>\n";
    $title .= "• Total Movies: <b>{$pg['total']}</b>\n";
    $title .= "• Current Page: <b>{$pg['page']}/{$pg['total_pages']}</b>\n";
    $title .= "• Showing: <b>" . count($pg['slice']) . " movies</b>\n\n";
    
    // Current page movies list
    $title .= "📋 <b>Current Page Movies:</b>\n";
    $i = 1;
    foreach ($pg['slice'] as $movie) {
        $movie_name = htmlspecialchars($movie['movie_name'] ?? 'Unknown');
        $quality = $movie['quality'] ?? 'Unknown';
        $lang = $movie['language'] ?? 'Hindi';
        $title .= "$i. {$movie_name} [{$quality}, {$lang}]\n";
        $i++;
    }
    
    $title .= "\n📍 Use buttons to navigate or resend current page";
    
    $kb = build_totalupload_keyboard($pg['page'], $pg['total_pages'], $filter_channel);
    sendMessage($chat_id, $title, $kb, 'HTML');
    
    bot_log("Total uploads viewed by $chat_id - Page $page, Channel: $channel_name");
}

function forward_page_movies($chat_id, array $page_movies) {
    $total = count($page_movies);
    if ($total === 0) return;
    
    $progress_msg = sendMessage($chat_id, "⏳ Forwarding {$total} movies...");
    if (!$progress_msg) return;
    
    $progress_msg_id = $progress_msg['message_id'];
    $success_count = 0;
    
    foreach ($page_movies as $index => $movie) {
        $success = deliver_item_to_chat($chat_id, $movie);
        if ($success) $success_count++;
        
        // Update progress every 2 movies
        if (($index + 1) % 2 === 0) {
            editMessage($chat_id, $progress_msg_id, "⏳ Forwarding... (" . ($index + 1) . "/{$total})");
        }
        
        usleep(500000); // 0.5 second delay
    }
    
    // Final progress update
    editMessage($chat_id, $progress_msg_id, "✅ Successfully forwarded {$success_count}/{$total} movies");
    
    // Send summary
    if ($success_count < $total) {
        sendMessage($chat_id, "⚠️ Note: Some movies couldn't be forwarded. They might have been deleted from the channel.");
    }
}

// ======================================================
// ENHANCED BACKUP SYSTEM - COMPLETE FIX
// ======================================================
function auto_backup() {
    bot_log("Starting auto-backup process...");
    
    $backup_files = [CSV_FILE, USERS_FILE, STATS_FILE, REQUEST_FILE, LOG_FILE];
    $backup_timestamp = date('Y-m-d_H-i-s');
    $backup_dir = BACKUP_DIR . $backup_timestamp;
    $backup_success = true;
    
    // Create backup directory
    if (!file_exists($backup_dir)) {
        if (!@mkdir($backup_dir, 0755, true)) {
            bot_log("Failed to create backup directory: $backup_dir", 'ERROR');
            return false;
        }
    }
    
    // 1. Local file backup
    foreach ($backup_files as $file) {
        if (file_exists($file)) {
            $backup_path = $backup_dir . '/' . basename($file) . '.bak';
            if (!copy($file, $backup_path)) {
                bot_log("Failed to backup: $file", 'ERROR');
                $backup_success = false;
            } else {
                @chmod($backup_path, 0644);
                bot_log("Backed up: $file to $backup_path");
            }
        } else {
            bot_log("File not found for backup: $file", 'WARNING');
        }
    }
    
    // 2. Create backup summary
    $summary = create_backup_summary();
    file_put_contents($backup_dir . '/backup_summary.txt', $summary);
    
    // 3. Upload to backup channel
    if ($backup_success) {
        $channel_backup_success = upload_backup_to_channel($backup_dir, $summary);
        
        if ($channel_backup_success) {
            bot_log("Backup successfully uploaded to channel");
            
            // Update users data with backup info
            $users_data = json_decode(file_get_contents(USERS_FILE), true);
            $users_data['last_backup'] = [
                'timestamp' => $backup_timestamp,
                'success' => true,
                'files' => count($backup_files)
            ];
            file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
            
        } else {
            bot_log("Failed to upload backup to channel", 'WARNING');
            $backup_success = false;
        }
    }
    
    // 4. Clean old backups (keep last 7)
    clean_old_backups();
    
    // 5. Send backup report to admin
    send_backup_report($backup_success, $summary, $backup_timestamp);
    
    bot_log("Auto-backup process completed - Success: " . ($backup_success ? 'Yes' : 'No'));
    return $backup_success;
}

function create_backup_summary() {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $csv_count = 0;
    
    // Count CSV entries
    if (file_exists(CSV_FILE)) {
        $handle = fopen(CSV_FILE, "r");
        if ($handle !== FALSE) {
            fgetcsv($handle); // Skip header
            while (fgetcsv($handle) !== FALSE) {
                $csv_count++;
            }
            fclose($handle);
        }
    }
    
    $summary = "========================================\n";
    $summary .= "        ENTERTAINMENT TADKA BACKUP\n";
    $summary .= "========================================\n\n";
    
    $summary .= "📅 Backup Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "🤖 Bot: Entertainment Tadka Bot v3.0\n";
    $summary .= "🌐 Server: " . (getenv('RENDER_EXTERNAL_URL') ?: 'Local') . "\n\n";
    
    $summary .= "📈 SYSTEM STATISTICS:\n";
    $summary .= "• Total Movies in CSV: $csv_count\n";
    $summary .= "• Total Users: " . count($users_data['users'] ?? []) . "\n";
    $summary .= "• Total Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $summary .= "• Total Downloads: " . ($stats['total_downloads'] ?? 0) . "\n";
    $summary .= "• Total Requests: " . ($stats['total_requests'] ?? 0) . "\n\n";
    
    $summary .= "💾 BACKUP DETAILS:\n";
    $summary .= "• CSV File: " . (file_exists(CSV_FILE) ? filesize(CSV_FILE) . " bytes" : "MISSING") . "\n";
    $summary .= "• Users File: " . (file_exists(USERS_FILE) ? filesize(USERS_FILE) . " bytes" : "MISSING") . "\n";
    $summary .= "• Stats File: " . (file_exists(STATS_FILE) ? filesize(STATS_FILE) . " bytes" : "MISSING") . "\n";
    $summary .= "• Requests File: " . (file_exists(REQUEST_FILE) ? filesize(REQUEST_FILE) . " bytes" : "MISSING") . "\n";
    $summary .= "• Log File: " . (file_exists(LOG_FILE) ? filesize(LOG_FILE) . " bytes" : "MISSING") . "\n\n";
    
    $summary .= "🔄 BACKUP TYPE: Automated Daily Backup\n";
    $summary .= "📍 LOCAL STORAGE: " . BACKUP_DIR . "\n";
    $summary .= "📡 TELEGRAM CHANNEL: " . BACKUP_CHANNEL_USERNAME . "\n";
    $summary .= "⏰ NEXT BACKUP: Daily at " . AUTO_BACKUP_HOUR . ":00\n";
    
    return $summary;
}

function upload_backup_to_channel($backup_dir, $summary) {
    try {
        // 1. Send backup summary as message
        $summary_message = "🔄 <b>Daily Auto-Backup Report</b>\n\n";
        $summary_message .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $summary_message .= "🤖 Bot: Entertainment Tadka v3.0\n\n";
        
        $stats = get_stats();
        $users_data = json_decode(file_get_contents(USERS_FILE), true);
        
        $summary_message .= "📊 <b>Current Stats:</b>\n";
        $summary_message .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
        $summary_message .= "• 👥 Users: " . count($users_data['users'] ?? []) . "\n";
        $summary_message .= "• 🔍 Searches: " . ($stats['total_searches'] ?? 0) . "\n";
        $summary_message .= "• 📥 Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
        
        $summary_message .= "✅ <b>Backup Status:</b> INITIATED\n";
        $summary_message .= "📁 <b>Files:</b> 5 data files\n";
        $summary_message .= "💾 <b>Size:</b> Calculating...\n";
        $summary_message .= "📡 <b>Channel:</b> " . BACKUP_CHANNEL_USERNAME;
        
        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '📡 ' . BACKUP_CHANNEL_USERNAME, 'url' => 'https://t.me/ETBackup']
            ]]
        ];
        
        $message_result = sendMessage(BACKUP_CHANNEL_ID, $summary_message, $keyboard, 'HTML');
        
        if (!$message_result) {
            bot_log("Failed to send backup summary to channel", 'ERROR');
            return false;
        }
        
        // 2. Upload individual files
        $critical_files = [
            CSV_FILE => "🎬 Movies Database",
            USERS_FILE => "👥 Users Data", 
            STATS_FILE => "📊 Bot Statistics",
            REQUEST_FILE => "📝 Movie Requests"
        ];
        
        $uploaded_count = 0;
        $total_size = 0;
        
        foreach ($critical_files as $file => $description) {
            if (file_exists($file)) {
                $file_size = filesize($file);
                $total_size += $file_size;
                
                // Convert to MB
                $file_size_mb = round($file_size / (1024 * 1024), 2);
                
                if ($file_size > MAX_FILE_SIZE_UPLOAD) {
                    bot_log("File too large for Telegram: $file ($file_size_mb MB)", 'WARNING');
                    continue;
                }
                
                $backup_file_path = $backup_dir . '/' . basename($file) . '.bak';
                
                if (!file_exists($backup_file_path)) {
                    bot_log("Backup file not found: $backup_file_path", 'WARNING');
                    continue;
                }
                
                $upload_success = upload_single_file_to_channel($backup_file_path, $description);
                
                if ($upload_success) {
                    $uploaded_count++;
                    bot_log("Uploaded to channel: $file");
                } else {
                    bot_log("Failed to upload: $file", 'WARNING');
                }
                
                sleep(2); // Rate limiting
            }
        }
        
        // 3. Create and upload zip archive if possible
        $zip_created = false;
        if (can_create_zip() && $uploaded_count > 0) {
            $zip_created = create_and_upload_zip($backup_dir, $total_size);
        }
        
        // 4. Send completion message
        $total_size_mb = round($total_size / (1024 * 1024), 2);
        
        $completion_message = "✅ <b>Backup Process Completed</b>\n\n";
        $completion_message .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $completion_message .= "📊 <b>Summary:</b>\n";
        $completion_message .= "• Files backed up: $uploaded_count/4\n";
        $completion_message .= "• Total size: $total_size_mb MB\n";
        $completion_message .= "• Zip archive: " . ($zip_created ? "✅ Created" : "❌ Skipped") . "\n";
        $completion_message .= "• Status: " . ($uploaded_count >= 2 ? "✅ Success" : "⚠️ Partial") . "\n\n";
        $completion_message .= "🛡️ <i>Your data is now securely backed up!</i>";
        
        sendMessage(BACKUP_CHANNEL_ID, $completion_message, $keyboard, 'HTML');
        
        return $uploaded_count >= 2; // At least 2 files uploaded
        
    } catch (Exception $e) {
        bot_log("Channel backup failed: " . $e->getMessage(), 'ERROR');
        
        // Send error report
        $error_message = "❌ <b>Backup Process Failed</b>\n\n";
        $error_message .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $error_message .= "🚨 Error: " . $e->getMessage() . "\n\n";
        $error_message .= "⚠️ Please check server logs immediately!";
        
        sendMessage(BACKUP_CHANNEL_ID, $error_message, null, 'HTML');
        
        return false;
    }
}

function upload_single_file_to_channel($file_path, $description) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    $file_size = filesize($file_path);
    $file_size_mb = round($file_size / (1024 * 1024), 2);
    $backup_time = date('Y-m-d H:i:s');
    $file_name = basename($file_path);
    
    $caption = "💾 " . $description . "\n";
    $caption .= "📅 " . $backup_time . "\n";
    $caption .= "📊 Size: " . $file_size_mb . " MB\n";
    $caption .= "🔄 Auto-backup\n";
    $caption .= "📡 " . BACKUP_CHANNEL_USERNAME;
    
    // Use safe CURLFile
    $curl_file = safe_curl_file($file_path);
    if (!$curl_file) {
        return false;
    }
    
    $post_fields = [
        'chat_id' => BACKUP_CHANNEL_ID,
        'document' => $curl_file,
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    $success = ($http_code == 200);
    
    if ($success) {
        bot_log("Uploaded to channel: $file_name ($file_size_mb MB)");
    } else {
        bot_log("Failed to upload to channel: $file_name - HTTP $http_code: $curl_error", 'ERROR');
    }
    
    return $success;
}

function create_and_upload_zip($backup_dir, $total_size) {
    if (!can_create_zip()) {
        bot_log("ZipArchive not available, skipping zip creation", 'WARNING');
        return false;
    }
    
    $zip_file = $backup_dir . '/entertainment_tadka_backup.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
        bot_log("Cannot create zip file: $zip_file", 'ERROR');
        return false;
    }
    
    // Add all backup files
    $backup_files = glob($backup_dir . '/*.bak');
    $added_count = 0;
    
    foreach ($backup_files as $file) {
        if (file_exists($file)) {
            $zip->addFile($file, basename($file));
            $added_count++;
        }
    }
    
    // Add summary file
    $summary_file = $backup_dir . '/backup_summary.txt';
    if (file_exists($summary_file)) {
        $zip->addFile($summary_file, 'backup_summary.txt');
        $added_count++;
    }
    
    $zip->close();
    
    if ($added_count === 0) {
        @unlink($zip_file);
        return false;
    }
    
    $zip_size = filesize($zip_file);
    $zip_size_mb = round($zip_size / (1024 * 1024), 2);
    
    // Upload zip file
    $caption = "📦 Complete Backup Archive\n";
    $caption .= "📅 " . date('Y-m-d H:i:s') . "\n";
    $caption .= "💾 Size: " . $zip_size_mb . " MB\n";
    $caption .= "📁 Contains: $added_count files\n";
    $caption .= "🔄 Auto-generated backup\n";
    $caption .= "📡 " . BACKUP_CHANNEL_USERNAME;
    
    $curl_file = safe_curl_file($zip_file);
    if (!$curl_file) {
        @unlink($zip_file);
        return false;
    }
    
    $post_fields = [
        'chat_id' => BACKUP_CHANNEL_ID,
        'document' => $curl_file,
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minutes for large files
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Clean up zip file
    @unlink($zip_file);
    
    $success = ($http_code == 200);
    
    if ($success) {
        bot_log("Zip backup uploaded to channel successfully ($zip_size_mb MB)");
        
        // Send confirmation
        $zip_confirmation = "✅ <b>Zip Archive Uploaded</b>\n\n";
        $zip_confirmation .= "📦 File: Complete Backup Archive\n";
        $zip_confirmation .= "💾 Size: " . $zip_size_mb . " MB\n";
        $zip_confirmation .= "📁 Files: $added_count included\n";
        $zip_confirmation .= "✅ Status: Successfully uploaded\n";
        $zip_confirmation .= "📡 Channel: " . BACKUP_CHANNEL_USERNAME;
        
        sendMessage(BACKUP_CHANNEL_ID, $zip_confirmation, null, 'HTML');
    } else {
        bot_log("Failed to upload zip backup to channel", 'WARNING');
    }
    
    return $success;
}

function clean_old_backups() {
    $backup_dirs = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    
    if (count($backup_dirs) <= 7) {
        return;
    }
    
    // Sort by creation time (oldest first)
    usort($backup_dirs, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    $delete_count = count($backup_dirs) - 7;
    $deleted_dirs = 0;
    
    for ($i = 0; $i < $delete_count; $i++) {
        $dir = $backup_dirs[$i];
        $files = glob($dir . '/*');
        
        foreach ($files as $file) {
            @unlink($file);
        }
        
        if (@rmdir($dir)) {
            $deleted_dirs++;
            bot_log("Deleted old backup: $dir");
        }
    }
    
    bot_log("Cleaned $deleted_dirs old backups (kept last 7)");
}

function send_backup_report($success, $summary, $backup_timestamp) {
    $report_message = "🔄 <b>Backup Completion Report</b>\n\n";
    
    if ($success) {
        $report_message .= "✅ <b>Status:</b> SUCCESS\n";
    } else {
        $report_message .= "⚠️ <b>Status:</b> PARTIAL/FAILED\n";
    }
    
    $report_message .= "📅 <b>Timestamp:</b> $backup_timestamp\n";
    $report_message .= "🕒 <b>Time:</b> " . date('Y-m-d H:i:s') . "\n";
    $report_message .= "📡 <b>Channel:</b> " . BACKUP_CHANNEL_USERNAME . "\n\n";
    
    // Add quick stats
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $report_message .= "📊 <b>Quick Stats:</b>\n";
    $report_message .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $report_message .= "• 👥 Users: " . count($users_data['users'] ?? []) . "\n";
    $report_message .= "• 🔍 Today's Searches: " . ($stats['daily_activity'][date('Y-m-d')]['searches'] ?? 0) . "\n";
    $report_message .= "• 📥 Today's Downloads: " . ($stats['daily_activity'][date('Y-m-d')]['downloads'] ?? 0) . "\n\n";
    
    $report_message .= "💾 <b>Backup Locations:</b>\n";
    $report_message .= "• Local: " . BACKUP_DIR . "\n";
    $report_message .= "• Telegram: " . BACKUP_CHANNEL_USERNAME . "\n\n";
    
    $report_message .= "🕒 <b>Next Backup:</b> " . AUTO_BACKUP_HOUR . ":00 daily";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📡 Visit Backup Channel', 'url' => 'https://t.me/ETBackup'],
                ['text' => '📊 Backup Status', 'callback_data' => 'backup_status']
            ],
            [
                ['text' => '🔄 Run Manual Backup', 'callback_data' => 'run_backup'],
                ['text' => '🗑️ Clean Old Backups', 'callback_data' => 'clean_backups']
            ]
        ]
    ];
    
    sendMessage(ADMIN_ID, $report_message, $keyboard, 'HTML');
}

// ======================================================
// MANUAL BACKUP COMMANDS
// ======================================================
function manual_backup($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $progress_msg = sendMessage($chat_id, "🔄 Starting manual backup...\n\n⏳ Please wait, this may take a few minutes.");
    
    if (!$progress_msg) {
        sendMessage($chat_id, "❌ Failed to start backup process.");
        return;
    }
    
    $progress_msg_id = $progress_msg['message_id'];
    
    try {
        editMessage($chat_id, $progress_msg_id, "🔄 Starting manual backup...\n\n📁 Step 1/5: Creating backup directory...");
        
        $success = auto_backup();
        
        if ($success) {
            editMessage($chat_id, $progress_msg_id, 
                "✅ Manual backup completed successfully!\n\n" .
                "📊 Backup has been:\n" .
                "• Saved locally in backup directory\n" .
                "• Uploaded to backup channel\n" .
                "• Summary sent to admin\n\n" .
                "🛡️ Your data is now securely backed up!"
            );
        } else {
            editMessage($chat_id, $progress_msg_id, 
                "⚠️ Backup completed with some warnings.\n\n" .
                "Some files may not have been backed up properly.\n" .
                "Check the backup channel and error logs for details."
            );
        }
        
    } catch (Exception $e) {
        editMessage($chat_id, $progress_msg_id, 
            "❌ Backup failed!\n\n" .
            "🚨 Error: " . $e->getMessage() . "\n\n" .
            "Please check the error logs and try again."
        );
        bot_log("Manual backup failed: " . $e->getMessage(), 'ERROR');
    }
}

function quick_backup($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $progress_msg = sendMessage($chat_id, "💾 Creating quick backup...\n\n⏳ Backing up essential files only.");
    
    if (!$progress_msg) return;
    
    $progress_msg_id = $progress_msg['message_id'];
    
    try {
        // Quick backup - only essential files
        $essential_files = [CSV_FILE, USERS_FILE];
        $backup_dir = BACKUP_DIR . 'quick_' . date('Y-m-d_H-i-s');
        
        if (!file_exists($backup_dir)) {
            @mkdir($backup_dir, 0755, true);
        }
        
        $backup_count = 0;
        foreach ($essential_files as $file) {
            if (file_exists($file)) {
                $backup_path = $backup_dir . '/' . basename($file) . '.bak';
                if (copy($file, $backup_path)) {
                    $backup_count++;
                    @chmod($backup_path, 0644);
                }
            }
        }
        
        // Upload to channel
        $upload_count = 0;
        foreach ($essential_files as $file) {
            $backup_file = $backup_dir . '/' . basename($file) . '.bak';
            if (file_exists($backup_file)) {
                if (upload_single_file_to_channel($file, "Quick Backup: " . basename($file))) {
                    $upload_count++;
                }
                sleep(1);
            }
        }
        
        editMessage($chat_id, $progress_msg_id, 
            "✅ Quick backup completed!\n\n" .
            "📊 Summary:\n" .
            "• Files backed up: $backup_count/2\n" .
            "• Uploaded to channel: $upload_count/2\n" .
            "• Location: $backup_dir\n\n" .
            "Essential files have been backed up."
        );
        
    } catch (Exception $e) {
        editMessage($chat_id, $progress_msg_id, 
            "❌ Quick backup failed!\n\n" .
            "Error: " . $e->getMessage()
        );
    }
}

// ======================================================
// COMPLETE COMMAND HANDLER - ALL COMMANDS
// ======================================================
function handle_command($chat_id, $user_id, $command, $params = []) {
    // Maintenance mode check
    global $MAINTENANCE_MODE;
    if ($MAINTENANCE_MODE && !in_array($command, ['/maintenance', '/ping']) && $user_id != ADMIN_ID) {
        global $MAINTENANCE_MESSAGE;
        sendMessage($chat_id, $MAINTENANCE_MESSAGE, null, 'HTML');
        return;
    }
    
    switch ($command) {
        // ==================== CORE COMMANDS ====================
        case '/start':
            $welcome_message = "🎬 <b>Welcome to Entertainment Tadka!</b>\n\n";
            $welcome_message .= "🍿 <b>Your Ultimate Movie Bot</b>\n\n";
            $welcome_message .= "🎯 <b>How to use:</b>\n";
            $welcome_message .= "• Simply type any movie name\n";
            $welcome_message .= "• Use /search for advanced search\n";
            $welcome_message .= "• Browse all movies with /totalupload\n\n";
            $welcome_message .= "📱 <b>Quick Commands:</b>\n";
            $welcome_message .= "• /help - All commands\n";
            $welcome_message .= "• /mystats - Your statistics\n";
            $welcome_message .= "• /channel - Join our channels\n";
            $welcome_message .= "• /request - Request movies\n\n";
            $welcome_message .= "⭐ <b>New Feature:</b> Multi-channel support!\n";
            $welcome_message .= "Now access movies from multiple channels.\n\n";
            $welcome_message .= "🔔 <b>Join Our Channels:</b>\n";
            $welcome_message .= "📢 Main: " . MAIN_CHANNEL . "\n";
            $welcome_message .= "💬 Support: " . REQUEST_CHANNEL . "\n";
            $welcome_message .= "🔒 Backup: " . BACKUP_CHANNEL_USERNAME . "\n";
            $welcome_message .= "🎬 Private: Private Movies Channel\n\n";
            $welcome_message .= "<i>Start by typing a movie name!</i>";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => ''],
                        ['text' => '🍿 Main Channel', 'url' => 'https://t.me/EntertainmentTadka786']
                    ],
                    [
                        ['text' => '📥 Request Movies', 'url' => 'https://t.me/EntertainmentTadka7860'],
                        ['text' => '📊 My Stats', 'callback_data' => 'my_stats']
                    ],
                    [
                        ['text' => '🎬 Private Channel', 'url' => 'https://t.me/+c6YctyoI9iA2M2Rl'],
                        ['text' => '❓ Help', 'callback_data' => 'help_command']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $welcome_message, $keyboard, 'HTML');
            update_user_activity($user_id, 'daily_login');
            break;

        case '/help':
        case '/commands':
            $help_text = "🤖 <b>Entertainment Tadka Bot - Complete Command Guide</b>\n\n";
            
            $help_text .= "🎯 <b>SEARCH COMMANDS:</b>\n";
            $help_text .= "• Just type movie name - Smart search\n";
            $help_text .= "• /search [movie] - Direct search\n";
            $help_text .= "• /s [movie] - Quick search\n\n";
            
            $help_text .= "📁 <b>BROWSE COMMANDS:</b>\n";
            $help_text .= "• /totalupload - All movies (paginated)\n";
            $help_text .= "• /latest - Latest additions\n";
            $help_text .= "• /trending - Popular movies\n";
            $help_text .= "• /quality [1080p/720p] - Filter by quality\n";
            $help_text .= "• /language [hindi/english] - Filter by language\n\n";
            
            $help_text .= "📝 <b>REQUEST SYSTEM:</b>\n";
            $help_text .= "• /request [movie] - Request new movie\n";
            $help_text .= "• /myrequests - Your request status\n";
            $help_text .= "• /requestlimit - Daily limit check\n\n";
            
            $help_text .= "👤 <b>USER COMMANDS:</b>\n";
            $help_text .= "• /mystats - Your statistics\n";
            $help_text .= "• /mypoints - Points information\n";
            $help_text .= "• /leaderboard - Top users\n\n";
            
            $help_text .= "📢 <b>CHANNEL COMMANDS:</b>\n";
            $help_text .= "• /channel - All channels info\n";
            $help_text .= "• /mainchannel - Main channel\n";
            $help_text .= "• /requestchannel - Request channel\n";
            $help_text .= "• /backupchannel - Backup channel\n";
            $help_text .= "• /privatechannel - Private movies channel\n\n";
            
            $help_text .= "ℹ️ <b>INFO COMMANDS:</b>\n";
            $help_text .= "• /info - Bot information\n";
            $help_text .= "• /stats - Statistics (admin)\n";
            $help_text .= "• /checkdate - Upload statistics\n";
            $help_text .= "• /checkcsv - Database view\n";
            $help_text .= "• /version - Version info\n\n";
            
            $help_text .= "🛠️ <b>UTILITY COMMANDS:</b>\n";
            $help_text .= "• /ping - Bot status\n";
            $help_text .= "• /report [bug] - Report bug\n";
            $help_text .= "• /feedback [text] - Send feedback\n";
            $help_text .= "• /support - Help & contact\n";
            $help_text .= "• /donate - Support us\n\n";
            
            $help_text .= "🔧 <b>ADMIN COMMANDS:</b> (Admin only)\n";
            $help_text .= "• /broadcast [msg] - Broadcast to all users\n";
            $help_text .= "• /backup - Manual backup\n";
            $help_text .= "• /quickbackup - Quick backup\n";
            $help_text .= "• /backupstatus - Backup info\n";
            $help_text .= "• /maintenance [on/off] - Maintenance mode\n";
            $help_text .= "• /cleanup - System cleanup\n";
            $help_text .= "• /sendalert [msg] - Send alert\n\n";
            
            $help_text .= "💡 <b>Pro Tips:</b>\n";
            $help_text .= "• Use partial names (e.g., 'aven' for Avengers)\n";
            $help_text .= "• Join all channels for updates\n";
            $help_text .= "• Earn points by using the bot\n";
            $help_text .= "• Request movies you can't find";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔍 Search Now', 'switch_inline_query_current_chat' => ''],
                        ['text' => '📁 Browse All', 'callback_data' => 'browse_all']
                    ],
                    [
                        ['text' => '🍿 Main Channel', 'url' => 'https://t.me/EntertainmentTadka786'],
                        ['text' => '📥 Request Channel', 'url' => 'https://t.me/EntertainmentTadka7860']
                    ],
                    [
                        ['text' => '🎬 Private Channel', 'url' => 'https://t.me/+c6YctyoI9iA2M2Rl'],
                        ['text' => '🔒 Backup Channel', 'url' => 'https://t.me/ETBackup']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $help_text, $keyboard, 'HTML');
            break;

        // ==================== SEARCH COMMANDS ====================
        case '/search':
        case '/s':
        case '/find':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, 
                    "❌ <b>Usage:</b> <code>/search movie_name</code>\n\n" .
                    "📝 <b>Examples:</b>\n" .
                    "• <code>/search kgf 2</code>\n" .
                    "• <code>/search Avengers Endgame</code>\n" .
                    "• <code>/search Hindi movie</code>\n\n" .
                    "💡 <b>Tip:</b> You can also just type the movie name without command!",
                    null, 'HTML'
                );
                return;
            }
            $lang = detect_language($movie_name);
            send_multilingual_response($chat_id, 'searching', $lang);
            advanced_search($chat_id, $movie_name, $user_id);
            break;

        // ==================== BROWSE COMMANDS ====================
        case '/totalupload':
        case '/totaluploads':
        case '/allmovies':
        case '/browse':
            $page = isset($params[0]) ? intval($params[0]) : 1;
            $filter_channel = isset($params[1]) ? $params[1] : null;
            totalupload_controller($chat_id, $page, $filter_channel);
            break;

        case '/latest':
        case '/recent':
        case '/new':
            $limit = isset($params[0]) ? intval($params[0]) : 10;
            show_latest_movies($chat_id, $limit);
            break;

        case '/trending':
        case '/popular':
            show_trending_movies($chat_id);
            break;

        case '/quality':
            $quality = isset($params[0]) ? strtolower($params[0]) : '1080p';
            if (!in_array($quality, ['1080p', '720p', '480p', 'hd', 'full hd'])) {
                sendMessage($chat_id, "❌ Invalid quality. Use: 1080p, 720p, 480p, hd, full hd");
                return;
            }
            show_movies_by_quality($chat_id, $quality);
            break;

        case '/language':
            $language = isset($params[0]) ? strtolower($params[0]) : 'hindi';
            if (!in_array($language, ['hindi', 'english', 'tamil', 'telugu', 'punjabi'])) {
                sendMessage($chat_id, "❌ Invalid language. Use: hindi, english, tamil, telugu, punjabi");
                return;
            }
            show_movies_by_language($chat_id, $language);
            break;

        // ==================== REQUEST COMMANDS ====================
        case '/request':
        case '/req':
        case '/requestmovie':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, 
                    "❌ <b>Usage:</b> <code>/request movie_name</code>\n\n" .
                    "📝 <b>Examples:</b>\n" .
                    "• <code>/request Animal Park</code>\n" .
                    "• <code>/request Spider-Man 4</code>\n" .
                    "• <code>/request New Hindi Movie 2024</code>\n\n" .
                    "📊 <b>Daily Limit:</b> " . DAILY_REQUEST_LIMIT . " requests per day",
                    null, 'HTML'
                );
                return;
            }
            
            $lang = detect_language($movie_name);
            $request_id = add_movie_request($user_id, $movie_name, $lang);
            
            if ($request_id) {
                send_multilingual_response($chat_id, 'request_success', $lang);
                update_user_activity($user_id, 'movie_request');
                
                // Send request confirmation with ID
                sendMessage($chat_id, 
                    "📝 <b>Request Details:</b>\n\n" .
                    "🎬 Movie: <code>" . htmlspecialchars($movie_name) . "</code>\n" .
                    "🆔 Request ID: <code>$request_id</code>\n" .
                    "📅 Date: " . date('Y-m-d H:i:s') . "\n" .
                    "🗣️ Language: " . ucfirst($lang) . "\n\n" .
                    "⏳ We'll notify you when it's added!",
                    null, 'HTML'
                );
            } else {
                send_multilingual_response($chat_id, 'request_limit', $lang);
            }
            break;

        case '/myrequests':
        case '/myreqs':
            show_user_requests($chat_id, $user_id);
            break;

        case '/requestlimit':
        case '/reqlimit':
            show_request_limit($chat_id, $user_id);
            break;

        // ==================== USER COMMANDS ====================
        case '/mystats':
        case '/mystatistics':
        case '/profile':
            show_user_stats($chat_id, $user_id);
            break;

        case '/mypoints':
        case '/points':
            show_user_points($chat_id, $user_id);
            break;

        case '/leaderboard':
        case '/topusers':
        case '/ranking':
            show_leaderboard($chat_id);
            break;

        // ==================== CHANNEL COMMANDS ====================
        case '/channel':
        case '/channels':
        case '/join':
            show_channel_info($chat_id);
            break;

        case '/mainchannel':
        case '/entertainmenttadka':
            show_main_channel_info($chat_id);
            break;

        case '/requestchannel':
        case '/requests':
        case '/support':
            show_request_channel_info($chat_id);
            break;

        case '/backupchannel':
        case '/etbackup':
            show_backup_channel_info($chat_id);
            break;

        case '/privatechannel':
        case '/privatemovies':
            show_private_channel_info($chat_id);
            break;

        // ==================== INFO COMMANDS ====================
        case '/checkdate':
        case '/datestats':
        case '/uploadstats':
            check_date($chat_id);
            break;

        case '/stats':
        case '/statistics':
        case '/botstats':
            if ($user_id == ADMIN_ID) {
                admin_stats($chat_id);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/checkcsv':
        case '/csvdata':
        case '/database':
            $show_all = (isset($params[0]) && strtolower($params[0]) == 'all');
            show_csv_data($chat_id, $show_all);
            break;

        case '/testcsv':
        case '/rawdata':
            test_csv($chat_id);
            break;

        case '/info':
        case '/about':
        case '/botinfo':
            show_bot_info($chat_id);
            break;

        case '/support':
        case '/contact':
        case '/helpgroup':
            show_support_info($chat_id);
            break;

        case '/version':
        case '/changelog':
            show_version_info($chat_id);
            break;

        // ==================== ADMIN COMMANDS ====================
        case '/broadcast':
            if ($user_id == ADMIN_ID) {
                $message = implode(' ', $params);
                if (empty($message)) {
                    sendMessage($chat_id, 
                        "❌ <b>Usage:</b> <code>/broadcast your_message</code>\n\n" .
                        "📝 <b>Example:</b>\n" .
                        "<code>/broadcast New movies added! Check /latest</code>",
                        null, 'HTML'
                    );
                    return;
                }
                send_broadcast($chat_id, $message);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/backup':
            if ($user_id == ADMIN_ID) {
                manual_backup($chat_id);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/quickbackup':
        case '/qbackup':
            if ($user_id == ADMIN_ID) {
                quick_backup($chat_id);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/backupstatus':
        case '/backupinfo':
            if ($user_id == ADMIN_ID) {
                backup_status($chat_id);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/maintenance':
            if ($user_id == ADMIN_ID) {
                $mode = isset($params[0]) ? strtolower($params[0]) : '';
                toggle_maintenance_mode($chat_id, $mode);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/cleanup':
            if ($user_id == ADMIN_ID) {
                perform_cleanup($chat_id);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/sendalert':
            if ($user_id == ADMIN_ID) {
                $alert_message = implode(' ', $params);
                if (empty($alert_message)) {
                    sendMessage($chat_id, "❌ Usage: <code>/sendalert your_alert</code>", null, 'HTML');
                    return;
                }
                send_alert_to_all($chat_id, $alert_message);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        // ==================== UTILITY COMMANDS ====================
        case '/ping':
        case '/status':
            $stats = get_stats();
            $uptime = "Unknown";
            if (isset($stats['server_start_time'])) {
                $start = strtotime($stats['server_start_time']);
                $now = time();
                $diff = $now - $start;
                
                $days = floor($diff / (60 * 60 * 24));
                $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
                $minutes = floor(($diff % (60 * 60)) / 60);
                
                $uptime = "$days days, $hours hours, $minutes minutes";
            }
            
            sendMessage($chat_id, 
                "🏓 <b>Bot Status:</b> ✅ Online\n" .
                "⏰ <b>Server Time:</b> " . date('Y-m-d H:i:s') . "\n" .
                "📈 <b>Uptime:</b> $uptime\n" .
                "💾 <b>Memory Usage:</b> " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n" .
                "🚀 <b>Version:</b> 3.0.0",
                null, 'HTML'
            );
            break;

        case '/donate':
        case '/supportus':
            show_donate_info($chat_id);
            break;

        case '/report':
        case '/reportbug':
            $bug_report = implode(' ', $params);
            if (empty($bug_report)) {
                sendMessage($chat_id, 
                    "❌ <b>Usage:</b> <code>/report bug_description</code>\n\n" .
                    "📝 <b>Example:</b>\n" .
                    "<code>/report Search not working for new movies</code>",
                    null, 'HTML'
                );
                return;
            }
            submit_bug_report($chat_id, $user_id, $bug_report);
            update_user_activity($user_id, 'bug_report');
            break;

        case '/feedback':
            $feedback = implode(' ', $params);
            if (empty($feedback)) {
                sendMessage($chat_id, 
                    "❌ <b>Usage:</b> <code>/feedback your_feedback</code>\n\n" .
                    "📝 <b>Example:</b>\n" .
                    "<code>/feedback Great bot! Add more regional movies please.</code>",
                    null, 'HTML'
                );
                return;
            }
            submit_feedback($chat_id, $user_id, $feedback);
            update_user_activity($user_id, 'feedback');
            break;

        default:
            sendMessage($chat_id, 
                "❌ Unknown command.\n\n" .
                "💡 Use <code>/help</code> to see all available commands.\n" .
                "🔍 Or just type a movie name to search!",
                null, 'HTML'
            );
    }
}

// ======================================================
// HELPER FUNCTIONS (Implement these)
// ======================================================
function show_latest_movies($chat_id, $limit = 10) {
    $all_movies = get_all_movies_list();
    $latest_movies = array_slice($all_movies, -$limit);
    $latest_movies = array_reverse($latest_movies);
    
    if (empty($latest_movies)) {
        sendMessage($chat_id, "📭 No movies found!");
        return;
    }
    
    $message = "🎬 <b>Latest $limit Movies</b>\n\n";
    $i = 1;
    
    foreach ($latest_movies as $movie) {
        $channel = ($movie['channel_id'] == NEW_PRIVATE_CHANNEL) ? "🔒" : "📢";
        $message .= "$i. $channel <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
        $message .= "   📊 " . ($movie['quality'] ?? 'Unknown') . " | 🗣️ " . ($movie['language'] ?? 'Hindi') . "\n";
        $message .= "   📅 " . ($movie['date'] ?? 'N/A') . "\n\n";
        $i++;
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Download Latest', 'callback_data' => 'download_latest'],
                ['text' => '📊 Browse All', 'callback_data' => 'browse_all']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_trending_movies($chat_id) {
    $all_movies = get_all_movies_list();
    $trending_movies = array_slice($all_movies, -15);
    
    if (empty($trending_movies)) {
        sendMessage($chat_id, "📭 No trending movies found!");
        return;
    }
    
    $message = "🔥 <b>Trending Movies (Last 15 Added)</b>\n\n";
    $i = 1;
    
    foreach (array_slice($trending_movies, 0, 10) as $movie) {
        $channel = ($movie['channel_id'] == NEW_PRIVATE_CHANNEL) ? "[Private]" : "[Main]";
        $message .= "$i. <b>" . htmlspecialchars($movie['movie_name']) . "</b> $channel\n";
        $message .= "   ⭐ " . ($movie['quality'] ?? 'HD') . " | 🗣️ " . ($movie['language'] ?? 'Hindi') . "\n\n";
        $i++;
    }
    
    $message .= "💡 <i>Based on recent additions</i>";
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_movies_by_quality($chat_id, $quality) {
    $all_movies = get_all_movies_list();
    $filtered_movies = [];
    
    foreach ($all_movies as $movie) {
        if (stripos($movie['quality'] ?? '', $quality) !== false) {
            $filtered_movies[] = $movie;
        }
    }
    
    if (empty($filtered_movies)) {
        sendMessage($chat_id, "❌ No $quality quality movies found!");
        return;
    }
    
    $message = "🎬 <b>" . strtoupper($quality) . " Quality Movies</b>\n\n";
    $message .= "📊 Total Found: " . count($filtered_movies) . "\n\n";
    
    $i = 1;
    foreach (array_slice($filtered_movies, 0, 10) as $movie) {
        $channel = ($movie['channel_id'] == NEW_PRIVATE_CHANNEL) ? "🔒" : "📢";
        $message .= "$i. $channel " . htmlspecialchars($movie['movie_name']) . "\n";
        $i++;
    }
    
    if (count($filtered_movies) > 10) {
        $message .= "\n... and " . (count($filtered_movies) - 10) . " more";
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Download All', 'callback_data' => 'download_quality_' . $quality],
                ['text' => '🔄 Other Qualities', 'callback_data' => 'show_qualities']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_movies_by_language($chat_id, $language) {
    $all_movies = get_all_movies_list();
    $filtered_movies = [];
    
    foreach ($all_movies as $movie) {
        if (stripos($movie['language'] ?? '', $language) !== false) {
            $filtered_movies[] = $movie;
        }
    }
    
    if (empty($filtered_movies)) {
        sendMessage($chat_id, "❌ No $language movies found!");
        return;
    }
    
    $message = "🎬 <b>" . ucfirst($language) . " Movies</b>\n\n";
    $message .= "📊 Total Found: " . count($filtered_movies) . "\n\n";
    
    $i = 1;
    foreach (array_slice($filtered_movies, 0, 10) as $movie) {
        $channel = ($movie['channel_id'] == NEW_PRIVATE_CHANNEL) ? "🔒" : "📢";
        $message .= "$i. $channel " . htmlspecialchars($movie['movie_name']) . "\n";
        $message .= "   📊 " . ($movie['quality'] ?? 'Unknown') . "\n\n";
        $i++;
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Download All', 'callback_data' => 'download_lang_' . $language],
                ['text' => '🔄 Other Languages', 'callback_data' => 'show_languages']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_channel_info($chat_id) {
    $message = "📢 <b>Join Our Channels</b>\n\n";
    
    $message .= "🍿 <b>Main Channel:</b> " . MAIN_CHANNEL . "\n";
    $message .= "• Latest movie updates\n";
    $message .= "• Daily new additions\n";
    $message .= "• High quality prints\n\n";
    
    $message .= "🎬 <b>Private Movies Channel:</b> (New!)\n";
    $message .= "• Exclusive content\n";
    $message .= "• Web series\n";
    $message .= "• Special collections\n";
    $message .= "• Join: https://t.me/+c6YctyoI9iA2M2Rl\n\n";
    
    $message .= "📥 <b>Requests Channel:</b> " . REQUEST_CHANNEL . "\n";
    $message .= "• Movie requests\n";
    $message .= "• Bug reports\n";
    $message .= "• Feature suggestions\n\n";
    
    $message .= "🔒 <b>Backup Channel:</b> " . BACKUP_CHANNEL_USERNAME . "\n";
    $message .= "• Secure data backups\n";
    $message .= "• System archives\n\n";
    
    $message .= "🔔 <b>Don't forget to join all channels!</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🍿 ' . MAIN_CHANNEL, 'url' => 'https://t.me/EntertainmentTadka786'],
                ['text' => '🎬 Private Channel', 'url' => 'https://t.me/+c6YctyoI9iA2M2Rl']
            ],
            [
                ['text' => '📥 ' . REQUEST_CHANNEL, 'url' => 'https://t.me/EntertainmentTadka7860'],
                ['text' => '🔒 ' . BACKUP_CHANNEL_USERNAME, 'url' => 'https://t.me/ETBackup']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_private_channel_info($chat_id) {
    $message = "🎬 <b>Private Movies Channel</b>\n\n";
    
    $message .= "🔒 <b>Exclusive Content:</b>\n";
    $message .= "• Special movie collections\n";
    $message .= "• Complete web series\n";
    $message .= "• Rare finds\n";
    $message .= "• Early access content\n\n";
    
    $message .= "📊 <b>Current Stats:</b>\n";
    $stats = get_stats();
    $private_movies = 0;
    
    $all_movies = get_all_movies_list();
    foreach ($all_movies as $movie) {
        if ($movie['channel_id'] == NEW_PRIVATE_CHANNEL) {
            $private_movies++;
        }
    }
    
    $message .= "• Private Movies: $private_movies\n";
    $message .= "• Total Movies: " . ($stats['total_movies'] ?? 0) . "\n\n";
    
    $message .= "🔗 <b>Join Link:</b>\n";
    $message .= "https://t.me/+c6YctyoI9iA2M2Rl\n\n";
    
    $message .= "⚠️ <b>Note:</b> This is an invite-only channel.";

    $keyboard = [
        'inline_keyboard' => [[
            ['text' => '🎬 Join Private Channel', 'url' => 'https://t.me/+c6YctyoI9iA2M2Rl'],
            ['text' => '🍿 Main Channel', 'url' => 'https://t.me/EntertainmentTadka786']
        ]]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_user_stats($chat_id, $user_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user = $users_data['users'][$user_id] ?? null;
    
    if (!$user) {
        sendMessage($chat_id, "❌ User data not found! Use /start first.");
        return;
    }
    
    $message = "👤 <b>Your Statistics</b>\n\n";
    $message .= "🆔 User ID: <code>$user_id</code>\n";
    
    if (!empty($user['username'])) {
        $message .= "👤 Username: @" . $user['username'] . "\n";
    }
    
    $message .= "📅 Joined: " . ($user['joined'] ?? 'N/A') . "\n";
    $message .= "🕒 Last Active: " . ($user['last_active'] ?? 'N/A') . "\n\n";
    
    $message .= "📊 <b>Activity:</b>\n";
    $message .= "• 🔍 Searches: " . ($user['total_searches'] ?? 0) . "\n";
    $message .= "• 📥 Downloads: " . ($user['total_downloads'] ?? 0) . "\n";
    $message .= "• 📝 Requests: " . ($user['request_count'] ?? 0) . "\n";
    $message .= "• ⭐ Points: <b>" . ($user['points'] ?? 0) . "</b>\n\n";
    
    $message .= "🎯 <b>Rank:</b> " . calculate_user_rank($user['points'] ?? 0) . "\n";
    $message .= "📈 <b>Next Rank:</b> " . get_next_rank_info($user['points'] ?? 0);
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📈 Leaderboard', 'callback_data' => 'show_leaderboard'],
                ['text' => '🔄 Refresh', 'callback_data' => 'refresh_stats']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_user_points($chat_id, $user_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user = $users_data['users'][$user_id] ?? null;
    
    if (!$user) {
        sendMessage($chat_id, "❌ User data not found!");
        return;
    }
    
    $points = $user['points'] ?? 0;
    
    $message = "⭐ <b>Your Points System</b>\n\n";
    $message .= "🎯 Total Points: <b>$points</b>\n\n";
    
    $message .= "📈 <b>How to earn points:</b>\n";
    $message .= "• 🔍 Daily search: +1 point\n";
    $message .= "• 📥 Movie download: +3 points\n";
    $message .= "• 📝 Movie request: +2 points\n";
    $message .= "• 🎯 Found movie: +5 points\n";
    $message .= "• 📅 Daily login: +10 points\n";
    $message .= "• 🐛 Bug report: +10 points\n";
    $message .= "• 💡 Feedback: +5 points\n\n";
    
    $message .= "🏆 <b>Your Rank:</b> " . calculate_user_rank($points) . "\n";
    $message .= "📊 <b>Next Rank:</b> " . get_next_rank_info($points);
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_leaderboard($chat_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $users = $users_data['users'] ?? [];
    
    if (empty($users)) {
        sendMessage($chat_id, "📭 No user data found!");
        return;
    }
    
    // Sort users by points
    uasort($users, function($a, $b) {
        return ($b['points'] ?? 0) - ($a['points'] ?? 0);
    });
    
    $message = "🏆 <b>Top Users Leaderboard</b>\n\n";
    $i = 1;
    
    foreach (array_slice($users, 0, 10) as $user_id => $user) {
        $points = $user['points'] ?? 0;
        $username = !empty($user['username']) ? "@" . $user['username'] : 
                   (!empty($user['first_name']) ? $user['first_name'] : "User#" . substr($user_id, -4));
        
        $medal = $i == 1 ? "🥇" : ($i == 2 ? "🥈" : ($i == 3 ? "🥉" : "🔸"));
        
        $message .= "$medal <b>$i. $username</b>\n";
        $message .= "   ⭐ $points points | 🎯 " . calculate_user_rank($points) . "\n\n";
        $i++;
    }
    
    // Add current user's position if not in top 10
    $current_user = $users_data['users'][$chat_id] ?? null;
    if ($current_user) {
        $all_users = array_keys($users);
        $position = array_search($chat_id, $all_users);
        
        if ($position !== false && $position >= 10) {
            $position++; // Convert to 1-based index
            $message .= "📊 <b>Your Position:</b> #$position\n";
            $message .= "⭐ Your Points: " . ($current_user['points'] ?? 0) . "\n";
        }
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📊 My Stats', 'callback_data' => 'my_stats'],
                ['text' => '🔄 Refresh', 'callback_data' => 'refresh_leaderboard']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function calculate_user_rank($points) {
    if ($points >= 1000) return "🎖️ Elite";
    if ($points >= 500) return "🔥 Pro";
    if ($points >= 250) return "⭐ Advanced";
    if ($points >= 100) return "🚀 Intermediate";
    if ($points >= 50) return "👍 Beginner";
    return "🌱 Newbie";
}

function get_next_rank_info($points) {
    if ($points < 50) return "Beginner (50 points needed)";
    if ($points < 100) return "Intermediate (100 points needed)";
    if ($points < 250) return "Advanced (250 points needed)";
    if ($points < 500) return "Pro (500 points needed)";
    if ($points < 1000) return "Elite (1000 points needed)";
    return "Max Rank Achieved! 🏆";
}

function show_user_requests($chat_id, $user_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $user_requests = [];
    
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id) {
            $user_requests[] = $request;
        }
    }
    
    if (empty($user_requests)) {
        sendMessage($chat_id, "📭 You haven't made any movie requests yet!\n\nUse /request movie_name to request movies.");
        return;
    }
    
    $message = "📝 <b>Your Movie Requests</b>\n\n";
    $i = 1;
    
    // Sort by date (newest first)
    usort($user_requests, function($a, $b) {
        return strtotime($b['date'] . ' ' . $b['time']) - strtotime($a['date'] . ' ' . $a['time']);
    });
    
    foreach (array_slice($user_requests, 0, 10) as $request) {
        $status_emoji = $request['status'] == 'completed' ? '✅' : 
                       ($request['status'] == 'rejected' ? '❌' : '⏳');
        
        $message .= "$i. $status_emoji <b>" . htmlspecialchars($request['movie_name']) . "</b>\n";
        $message .= "   📅 " . $request['date'] . " | 🗣️ " . ucfirst($request['language']) . "\n";
        $message .= "   🆔 " . $request['id'] . "\n\n";
        $i++;
    }
    
    $pending_count = count(array_filter($user_requests, function($req) {
        return $req['status'] == 'pending';
    }));
    
    $completed_count = count(array_filter($user_requests, function($req) {
        return $req['status'] == 'completed';
    }));
    
    $message .= "📊 <b>Summary:</b>\n";
    $message .= "• Total Requests: " . count($user_requests) . "\n";
    $message .= "• ⏳ Pending: $pending_count\n";
    $message .= "• ✅ Completed: $completed_count\n";
    $message .= "• ❌ Rejected: " . (count($user_requests) - $pending_count - $completed_count);
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_request_limit($chat_id, $user_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $today = date('Y-m-d');
    $today_requests = 0;
    
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id && $request['date'] == $today) {
            $today_requests++;
        }
    }
    
    $remaining = DAILY_REQUEST_LIMIT - $today_requests;
    
    $message = "📋 <b>Your Request Limit</b>\n\n";
    $message .= "✅ Daily Limit: " . DAILY_REQUEST_LIMIT . " requests\n";
    $message .= "📅 Used Today: $today_requests requests\n";
    $message .= "🎯 Remaining Today: $remaining requests\n\n";
    
    if ($remaining > 0) {
        $message .= "💡 Use <code>/request movie_name</code> to request movies!\n\n";
        $message .= "📝 <b>Examples:</b>\n";
        $message .= "• <code>/request Animal Park</code>\n";
        $message .= "• <code>/request Spider-Man 4</code>\n";
    } else {
        $message .= "⏳ Limit resets at midnight (12:00 AM)!\n\n";
        $message .= "💡 You can still search for existing movies.";
    }
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function check_date($chat_id) {
    if (!file_exists(CSV_FILE)) {
        sendMessage($chat_id, "⚠️ No data saved yet.");
        return;
    }
    
    $date_counts = [];
    $h = fopen(CSV_FILE, 'r');
    
    if ($h !== FALSE) {
        fgetcsv($h);
        while (($r = fgetcsv($h)) !== FALSE) {
            if (count($r) >= 3) {
                $d = $r[2];
                if (!isset($date_counts[$d])) $date_counts[$d] = 0;
                $date_counts[$d]++;
            }
        }
        fclose($h);
    }
    
    krsort($date_counts);
    $msg = "📅 <b>Movies Upload Record</b>\n\n";
    $total_days = 0;
    $total_movies = 0;
    
    foreach ($date_counts as $date => $count) {
        $msg .= "➡️ $date: $count movies\n";
        $total_days++;
        $total_movies += $count;
    }
    
    $msg .= "\n📊 <b>Summary:</b>\n";
    $msg .= "• Total Days: $total_days\n";
    $msg .= "• Total Movies: $total_movies\n";
    $msg .= "• Average per day: " . round($total_movies / max(1, $total_days), 2);
    
    sendMessage($chat_id, $msg, null, 'HTML');
}

function test_csv($chat_id) {
    if (!file_exists(CSV_FILE)) {
        sendMessage($chat_id, "⚠️ CSV file not found.");
        return;
    }
    
    $h = fopen(CSV_FILE, 'r');
    if ($h === FALSE) {
        sendMessage($chat_id, "❌ Error opening CSV file.");
        return;
    }
    
    fgetcsv($h);
    $i = 1;
    $msg = "";
    
    while (($r = fgetcsv($h)) !== FALSE) {
        if (count($r) >= 3) {
            $line = "$i. {$r[0]} | ID: {$r[1]} | Date: {$r[2]}";
            if (isset($r[4])) $line .= " | Quality: {$r[4]}";
            if (isset($r[6])) $line .= " | Language: {$r[6]}";
            $line .= "\n";
            
            if (strlen($msg) + strlen($line) > 4000) {
                sendMessage($chat_id, $msg);
                $msg = "";
            }
            $msg .= $line;
            $i++;
        }
    }
    fclose($h);
    
    if (!empty($msg)) {
        sendMessage($chat_id, $msg);
    }
}

function show_bot_info($chat_id) {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $message = "🤖 <b>Entertainment Tadka Bot</b>\n\n";
    $message .= "📱 <b>Version:</b> 3.0.0\n";
    $message .= "🆙 <b>Last Updated:</b> " . date('Y-m-d') . "\n";
    $message .= "👨‍💻 <b>Developer:</b> @EntertainmentTadka0786\n";
    $message .= "🌐 <b>Platform:</b> Telegram Bot API\n\n";
    
    $message .= "📊 <b>Bot Statistics:</b>\n";
    $message .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $message .= "• 👥 Users: " . count($users_data['users'] ?? []) . "\n";
    $message .= "• 🔍 Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $message .= "• 📥 Downloads: " . ($stats['total_downloads'] ?? 0) . "\n";
    $message .= "• 📝 Requests: " . ($stats['total_requests'] ?? 0) . "\n\n";
    
    $message .= "🎯 <b>Features:</b>\n";
    $message .= "• Smart movie search with fuzzy matching\n";
    $message .= "• Multi-language support (Hindi/English)\n";
    $message .= "• Multi-channel movie access\n";
    $message .= "• Movie request system\n";
    $message .= "• User points & leaderboard\n";
    $message .= "• Automatic backups\n";
    $message .= "• Advanced statistics\n\n";
    
    $message .= "📢 <b>Channels:</b>\n";
    $message .= "• Main: " . MAIN_CHANNEL . "\n";
    $message .= "• Support: " . REQUEST_CHANNEL . "\n";
    $message .= "• Backup: " . BACKUP_CHANNEL_USERNAME . "\n";
    $message .= "• Private: Private Movies Channel\n\n";
    
    $message .= "💡 <b>Built with ❤️ for movie lovers!</b>";

    sendMessage($chat_id, $message, null, 'HTML');
}

function show_support_info($chat_id) {
    $message = "🆘 <b>Support & Contact</b>\n\n";
    
    $message .= "📞 <b>Need Help?</b>\n";
    $message .= "• Movie not found?\n";
    $message .= "• Technical issues?\n";
    $message .= "• Feature requests?\n\n";
    
    $message .= "🎯 <b>Quick Solutions:</b>\n";
    $message .= "1. Use <code>/request movie_name</code> for new movies\n";
    $message .= "2. Check <code>/help</code> for all commands\n";
    $message .= "3. Join our support channel\n\n";
    
    $message .= "📢 <b>Support Channel:</b> " . REQUEST_CHANNEL . "\n";
    $message .= "👨‍💻 <b>Admin:</b> @EntertainmentTadka0786\n\n";
    
    $message .= "💡 <b>Pro Tip:</b> Always check spelling before reporting!\n";
    $message .= "🐛 <b>Found a bug?</b> Use <code>/report bug_description</code>\n";
    $message .= "💭 <b>Suggestions?</b> Use <code>/feedback your_idea</code>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📢 Support Channel', 'url' => 'https://t.me/EntertainmentTadka7860'],
                ['text' => '🐛 Report Bug', 'callback_data' => 'report_bug_ui']
            ],
            [
                ['text' => '💡 Suggest Feature', 'callback_data' => 'suggest_feature_ui'],
                ['text' => '📝 Give Feedback', 'callback_data' => 'give_feedback_ui']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_donate_info($chat_id) {
    $message = "💝 <b>Support Our Work</b>\n\n";
    
    $message .= "🤖 <b>Why Donate?</b>\n";
    $message .= "• Server maintenance costs\n";
    $message .= "• Bot development & updates\n";
    $message .= "• New features implementation\n";
    $message .= "• 24/7 service availability\n\n";
    
    $message .= "💰 <b>Donation Methods:</b>\n";
    $message .= "• UPI: entertainmenttadka@upi\n";
    $message .= "• PayPal: coming soon\n";
    $message .= "• Crypto: coming soon\n\n";
    
    $message .= "🎁 <b>Donor Benefits:</b>\n";
    $message .= "• Priority support\n";
    $message .= "• Early access to features\n";
    $message .= "• Special donor badge\n";
    $message .= "• Increased request limits\n\n";
    
    $message .= "💌 <b>Contact for other methods:</b> " . REQUEST_CHANNEL;

    sendMessage($chat_id, $message, null, 'HTML');
}

function submit_bug_report($chat_id, $user_id, $bug_report) {
    $report_id = uniqid('bug_');
    
    $admin_message = "🐛 <b>New Bug Report</b>\n\n";
    $admin_message .= "🆔 Report ID: <code>$report_id</code>\n";
    $admin_message .= "👤 User ID: <code>$user_id</code>\n";
    $admin_message .= "📅 Time: " . date('Y-m-d H:i:s') . "\n\n";
    $admin_message .= "📝 <b>Bug Description:</b>\n" . htmlspecialchars($bug_report) . "\n\n";
    $admin_message .= "🔧 <b>Actions:</b>\n";
    $admin_message .= "• Reply to user with solution\n";
    $admin_message .= "• Fix if it's a system bug\n";
    $admin_message .= "• Update bug fix log";

    $admin_keyboard = [
        'inline_keyboard' => [[
            ['text' => '👤 Contact User', 'url' => 'https://t.me/' . $user_id],
            ['text' => '✅ Mark Fixed', 'callback_data' => 'mark_fixed_' . $report_id]
        ]]
    ];
    
    sendMessage(ADMIN_ID, $admin_message, $admin_keyboard, 'HTML');
    
    // Confirm to user
    sendMessage($chat_id, 
        "✅ <b>Bug report submitted successfully!</b>\n\n" .
        "🆔 Report ID: <code>$report_id</code>\n" .
        "📅 Time: " . date('Y-m-d H:i:s') . "\n" .
        "📝 Issue: " . substr(htmlspecialchars($bug_report), 0, 100) . "...\n\n" .
        "👨‍💻 <b>Our team will look into it soon!</b>\n" .
        "📢 Updates will be posted in " . REQUEST_CHANNEL,
        null, 'HTML'
    );
    
    bot_log("Bug report submitted by $user_id: $report_id");
}

function submit_feedback($chat_id, $user_id, $feedback) {
    $feedback_id = uniqid('feedback_');
    
    $admin_message = "💡 <b>New User Feedback</b>\n\n";
    $admin_message .= "🆔 Feedback ID: <code>$feedback_id</code>\n";
    $admin_message .= "👤 User ID: <code>$user_id</code>\n";
    $admin_message .= "📅 Time: " . date('Y-m-d H:i:s') . "\n\n";
    $admin_message .= "📝 <b>Feedback:</b>\n" . htmlspecialchars($feedback) . "\n\n";
    $admin_message .= "⭐ <b>User earned 5 points for feedback!</b>";

    $admin_keyboard = [
        'inline_keyboard' => [[
            ['text' => '👤 Thank User', 'url' => 'https://t.me/' . $user_id],
            ['text' => '💡 Add to Roadmap', 'callback_data' => 'add_to_roadmap_' . $feedback_id]
        ]]
    ];
    
    sendMessage(ADMIN_ID, $admin_message, $admin_keyboard, 'HTML');
    
    // Thank user
    sendMessage($chat_id, 
        "✅ <b>Thank you for your feedback!</b>\n\n" .
        "🆔 Feedback ID: <code>$feedback_id</code>\n" .
        "📅 Time: " . date('Y-m-d H:i:s') . "\n" .
        "💭 Your input: " . substr(htmlspecialchars($feedback), 0, 100) . "...\n\n" .
        "⭐ <b>You earned 5 points for your feedback!</b>\n" .
        "🎯 Check your points with /mypoints\n\n" .
        "👨‍💻 <b>We appreciate your support!</b>",
        null, 'HTML'
    );
    
    bot_log("Feedback submitted by $user_id: $feedback_id");
}

function show_version_info($chat_id) {
    $message = "🔄 <b>Bot Version Information</b>\n\n";
    
    $message .= "📱 <b>Current Version:</b> v3.0.0\n";
    $message .= "🆙 <b>Release Date:</b> " . date('Y-m-d') . "\n";
    $message .= "🐛 <b>Status:</b> Stable Release\n";
    $message .= "⚡ <b>Performance:</b> Optimized\n\n";
    
    $message .= "🎯 <b>What's New in v3.0.0:</b>\n";
    $message .= "✅ Complete code overhaul\n";
    $message .= "✅ Multi-channel support (Main + Private)\n";
    $message .= "✅ Enhanced search algorithm\n";
    $message .= "✅ Improved backup system\n";
    $message .= "✅ Better error handling\n";
    $message .= "✅ User points system\n";
    $message .= "✅ Leaderboard feature\n";
    $message .= "✅ Bug fixes & optimizations\n\n";
    
    $message .= "📋 <b>Upcoming Features (v3.1.0):</b>\n";
    $message .= "• Movie ratings & reviews\n";
    $message .= "• Watchlist feature\n";
    $message .= "• Advanced filters\n";
    $message .= "• User profiles\n";
    $message .= "• More regional languages\n";
    $message .= "• Web dashboard\n\n";
    
    $message .= "🐛 <b>Found a bug?</b> Use <code>/report</code>\n";
    $message .= "💡 <b>Suggestions?</b> Use <code>/feedback</code>\n";
    $message .= "📢 <b>Updates Channel:</b> " . REQUEST_CHANNEL;

    sendMessage($chat_id, $message, null, 'HTML');
}

function admin_stats($chat_id) {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $total_users = count($users_data['users'] ?? []);
    
    // Count active users (last 7 days)
    $active_users = 0;
    $one_week_ago = strtotime('-7 days');
    foreach ($users_data['users'] ?? [] as $user) {
        if (strtotime($user['last_active'] ?? '') >= $one_week_ago) {
            $active_users++;
        }
    }
    
    // Count private channel movies
    $private_movies = 0;
    $all_movies = get_all_movies_list();
    foreach ($all_movies as $movie) {
        if ($movie['channel_id'] == NEW_PRIVATE_CHANNEL) {
            $private_movies++;
        }
    }
    
    $msg = "📊 <b>Bot Statistics (Admin)</b>\n\n";
    $msg .= "🎬 <b>Movies:</b>\n";
    $msg .= "• Total: " . ($stats['total_movies'] ?? 0) . "\n";
    $msg .= "• Main Channel: " . (($stats['total_movies'] ?? 0) - $private_movies) . "\n";
    $msg .= "• Private Channel: $private_movies\n\n";
    
    $msg .= "👥 <b>Users:</b>\n";
    $msg .= "• Total: $total_users\n";
    $msg .= "• Active (7 days): $active_users\n";
    $msg .= "• Inactive: " . ($total_users - $active_users) . "\n\n";
    
    $msg .= "📈 <b>Activity:</b>\n";
    $msg .= "• Total Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $msg .= "• Successful: " . ($stats['successful_searches'] ?? 0) . "\n";
    $msg .= "• Failed: " . ($stats['failed_searches'] ?? 0) . "\n";
    $msg .= "• Total Downloads: " . ($stats['total_downloads'] ?? 0) . "\n";
    $msg .= "• Total Requests: " . ($stats['total_requests'] ?? 0) . "\n";
    $msg .= "• Success Rate: " . 
            (($stats['total_searches'] ?? 1) > 0 ? 
             round((($stats['successful_searches'] ?? 0) / ($stats['total_searches'] ?? 1)) * 100, 2) : 0) . "%\n\n";
    
    $msg .= "🕒 <b>System:</b>\n";
    $msg .= "• Last Updated: " . ($stats['last_updated'] ?? 'N/A') . "\n";
    $msg .= "• Server Start: " . ($stats['server_start_time'] ?? 'N/A') . "\n";
    $msg .= "• Memory Usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n\n";
    
    // Today's activity
    $today = date('Y-m-d');
    if (isset($stats['daily_activity'][$today])) {
        $today_stats = $stats['daily_activity'][$today];
        $msg .= "📅 <b>Today's Activity:</b>\n";
        $msg .= "• Searches: " . ($today_stats['searches'] ?? 0) . "\n";
        $msg .= "• Downloads: " . ($today_stats['downloads'] ?? 0) . "\n";
        $msg .= "• Requests: " . ($today_stats['requests'] ?? 0) . "\n";
        $msg .= "• New Users: " . ($today_stats['users'] ?? 0) . "\n\n";
    }
    
    // Recent uploads
    $csv_data = load_and_clean_csv();
    $recent = array_slice($csv_data, -5);
    $msg .= "📦 <b>Recent Uploads:</b>\n";
    foreach ($recent as $r) {
        $channel = ($r['channel_id'] == NEW_PRIVATE_CHANNEL) ? "[Private]" : "[Main]";
        $msg .= "• " . $r['movie_name'] . " $channel (" . $r['date'] . ")\n";
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔄 Refresh Stats', 'callback_data' => 'refresh_admin_stats'],
                ['text' => '📊 CSV Data', 'callback_data' => 'show_csv_admin']
            ],
            [
                ['text' => '💾 Backup Now', 'callback_data' => 'run_backup'],
                ['text' => '🧹 Cleanup', 'callback_data' => 'run_cleanup']
            ]
        ]
    ];
    
    sendMessage($chat_id, $msg, $keyboard, 'HTML');
    bot_log("Admin stats viewed by $chat_id");
}

function show_csv_data($chat_id, $show_all = false) {
    if (!file_exists(CSV_FILE)) {
        sendMessage($chat_id, "❌ CSV file not found.");
        return;
    }
    
    $handle = fopen(CSV_FILE, "r");
    if ($handle === FALSE) {
        sendMessage($chat_id, "❌ Error opening CSV file.");
        return;
    }
    
    fgetcsv($handle);
    $movies = [];
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) >= 3) {
            $movies[] = $row;
        }
    }
    fclose($handle);
    
    if (empty($movies)) {
        sendMessage($chat_id, "📊 CSV file is empty.");
        return;
    }
    
    $movies = array_reverse($movies);
    $limit = $show_all ? count($movies) : 10;
    $movies = array_slice($movies, 0, $limit);
    
    $message = "📊 <b>CSV Movie Database</b>\n\n";
    $message .= "📁 Total Movies: " . count($movies) . "\n";
    
    if (!$show_all) {
        $message .= "🔍 Showing latest 10 entries\n";
        $message .= "📋 Use '/checkcsv all' for full list\n\n";
    } else {
        $message .= "📋 Full database listing\n\n";
    }
    
    $i = 1;
    foreach ($movies as $movie) {
        $movie_name = $movie[0] ?? 'N/A';
        $message_id = $movie[1] ?? 'N/A';
        $date = $movie[2] ?? 'N/A';
        $quality = $movie[4] ?? 'Unknown';
        $language = $movie[6] ?? 'Hindi';
        $channel = isset($movie[7]) ? ($movie[7] == NEW_PRIVATE_CHANNEL ? '🔒 Private' : '📢 Main') : 'Main';
        
        $message .= "$i. 🎬 " . htmlspecialchars($movie_name) . "\n";
        $message .= "   📝 ID: $message_id | 🗣️ $language | 📊 $quality | $channel\n";
        $message .= "   📅 Date: $date\n\n";
        
        $i++;
        
        if (strlen($message) > 3000) {
            sendMessage($chat_id, $message, null, 'HTML');
            $message = "📊 Continuing...\n\n";
        }
    }
    
    $message .= "💾 File: " . CSV_FILE . "\n";
    $message .= "⏰ Last Updated: " . date('Y-m-d H:i:s', filemtime(CSV_FILE));
    
    sendMessage($chat_id, $message, null, 'HTML');
    bot_log("CSV data viewed by $chat_id - Show all: " . ($show_all ? 'Yes' : 'No'));
}

function send_broadcast($chat_id, $message) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $total_users = count($users_data['users'] ?? []);
    
    if ($total_users == 0) {
        sendMessage($chat_id, "❌ No users to broadcast to!");
        return;
    }
    
    $progress_msg = sendMessage($chat_id, 
        "📢 <b>Broadcast Started</b>\n\n" .
        "👥 Total Users: $total_users\n" .
        "📝 Message Length: " . strlen($message) . " chars\n\n" .
        "⏳ Progress: 0% (0/$total_users)\n" .
        "🕒 Estimated time: " . ceil($total_users * 0.1) . " seconds"
    );
    
    if (!$progress_msg) {
        sendMessage($chat_id, "❌ Failed to start broadcast.");
        return;
    }
    
    $progress_msg_id = $progress_msg['message_id'];
    $success_count = 0;
    $fail_count = 0;
    
    $i = 0;
    foreach ($users_data['users'] as $user_id => $user) {
        // Skip banned users
        if (isset($user['banned']) && $user['banned']) {
            continue;
        }
        
        try {
            $broadcast_message = "📢 <b>Announcement from Entertainment Tadka:</b>\n\n" . $message . "\n\n";
            $broadcast_message .= "💬 Feedback? Use /feedback\n";
            $broadcast_message .= "🐛 Issues? Use /report\n\n";
            $broadcast_message .= "📢 Join: " . MAIN_CHANNEL;
            
            $sent = sendMessage($user_id, $broadcast_message, null, 'HTML');
            
            if ($sent) {
                $success_count++;
            } else {
                $fail_count++;
            }
            
            // Update progress every 10 users
            if ($i % 10 === 0) {
                $progress = round(($i / $total_users) * 100);
                $eta = ceil(($total_users - $i) * 0.1);
                
                editMessage($chat_id, $progress_msg_id, 
                    "📢 <b>Broadcast in Progress</b>\n\n" .
                    "👥 Total Users: $total_users\n" .
                    "✅ Sent: $success_count\n" .
                    "❌ Failed: $fail_count\n\n" .
                    "⏳ Progress: $progress% ($i/$total_users)\n" .
                    "🕒 ETA: $eta seconds"
                );
            }
            
            usleep(100000); // 0.1 second delay
            $i++;
            
        } catch (Exception $e) {
            $fail_count++;
            bot_log("Broadcast failed for $user_id: " . $e->getMessage(), 'ERROR');
        }
    }
    
    $final_message = "✅ <b>Broadcast Completed!</b>\n\n";
    $final_message .= "📊 <b>Summary:</b>\n";
    $final_message .= "• 👥 Total Users: $total_users\n";
    $final_message .= "• ✅ Successfully Sent: $success_count\n";
    $final_message .= "• ❌ Failed: $fail_count\n";
    $final_message .= "• 📈 Success Rate: " . round(($success_count / max(1, $total_users)) * 100, 2) . "%\n\n";
    $final_message .= "⏰ <b>Completed at:</b> " . date('Y-m-d H:i:s') . "\n";
    $final_message .= "📝 <b>Message:</b> \"" . substr(htmlspecialchars($message), 0, 50) . "...\"";
    
    editMessage($chat_id, $progress_msg_id, $final_message);
    
    // Also send to admin
    sendMessage(ADMIN_ID, 
        "📢 <b>Broadcast Report</b>\n\n" .
        "👤 Sent by: $chat_id\n" .
        "✅ Success: $success_count users\n" .
        "❌ Failed: $fail_count users\n" .
        "📅 Time: " . date('Y-m-d H:i:s') . "\n\n" .
        "📝 Message preview:\n" . substr(htmlspecialchars($message), 0, 200) . "...",
        null, 'HTML'
    );
    
    bot_log("Broadcast sent by $chat_id to $success_count users");
}

function toggle_maintenance_mode($chat_id, $mode) {
    global $MAINTENANCE_MODE;
    
    if ($mode == 'on') {
        $MAINTENANCE_MODE = true;
        sendMessage($chat_id, 
            "🔧 <b>Maintenance Mode ENABLED</b>\n\n" .
            "✅ Bot is now in maintenance mode.\n" .
            "👤 Regular users will see maintenance message.\n" .
            "👨‍💻 Admin commands still work.\n\n" .
            "⏰ Time: " . date('Y-m-d H:i:s') . "\n" .
            "🔄 Use <code>/maintenance off</code> to disable.",
            null, 'HTML'
        );
        bot_log("Maintenance mode enabled by $chat_id");
        
    } elseif ($mode == 'off') {
        $MAINTENANCE_MODE = false;
        sendMessage($chat_id, 
            "✅ <b>Maintenance Mode DISABLED</b>\n\n" .
            "🤖 Bot is now operational.\n" .
            "👤 All users can access the bot.\n" .
            "🎬 Movie search and download enabled.\n\n" .
            "⏰ Time: " . date('Y-m-d H:i:s'),
            null, 'HTML'
        );
        bot_log("Maintenance mode disabled by $chat_id");
        
    } else {
        sendMessage($chat_id, 
            "❌ <b>Usage:</b>\n\n" .
            "To enable: <code>/maintenance on</code>\n" .
            "To disable: <code>/maintenance off</code>\n\n" .
            "Current status: " . ($MAINTENANCE_MODE ? "🔧 ENABLED" : "✅ DISABLED"),
            null, 'HTML'
        );
    }
}

function perform_cleanup($chat_id) {
    $stats_before = get_stats();
    
    // 1. Clean up old backups
    $old_backups = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    $deleted_backups = 0;
    
    if (count($old_backups) > 7) {
        usort($old_backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $to_delete = count($old_backups) - 7;
        for ($i = 0; $i < $to_delete; $i++) {
            $dir = $old_backups[$i];
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            if (@rmdir($dir)) {
                $deleted_backups++;
            }
        }
    }
    
    // 2. Clear cache
    clear_movie_cache();
    
    // 3. Clean up temp files
    $temp_files = glob(__DIR__ . '/*.tmp');
    $deleted_temp = 0;
    foreach ($temp_files as $temp_file) {
        if (@unlink($temp_file)) {
            $deleted_temp++;
        }
    }
    
    // 4. Optimize CSV (remove duplicates)
    $csv_data = load_and_clean_csv();
    $unique_count = count($csv_data);
    
    sendMessage($chat_id, 
        "🧹 <b>Cleanup Completed!</b>\n\n" .
        "📊 <b>Results:</b>\n" .
        "• 📁 Old backups removed: $deleted_backups\n" .
        "• 💾 Cache cleared: Movie search cache\n" .
        "• 🗑️ Temp files deleted: $deleted_temp\n" .
        "• 📋 CSV optimized: $unique_count unique movies\n\n" .
        "⚡ <b>System optimized for better performance!</b>",
        null, 'HTML'
    );
    
    bot_log("Cleanup performed by $chat_id - Backups: $deleted_backups, Temp: $deleted_temp");
}

function send_alert_to_all($chat_id, $alert_message) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $success_count = 0;
    
    $progress_msg = sendMessage($chat_id, "🚨 Sending alert to all users...");
    if (!$progress_msg) return;
    
    $progress_msg_id = $progress_msg['message_id'];
    $total_users = count($users_data['users'] ?? []);
    
    $i = 0;
    foreach ($users_data['users'] as $user_id => $user) {
        try {
            sendMessage($user_id, 
                "🚨 <b>Important Alert from Entertainment Tadka:</b>\n\n" . 
                $alert_message . "\n\n" .
                "📢 For updates: " . REQUEST_CHANNEL,
                null, 'HTML'
            );
            $success_count++;
            
            // Update progress
            if ($i % 20 === 0) {
                editMessage($chat_id, $progress_msg_id, 
                    "🚨 Sending alert...\n" .
                    "✅ Sent: $success_count/$total_users"
                );
            }
            
            usleep(50000); // 0.05 second delay
            $i++;
            
        } catch (Exception $e) {
            // Skip failed sends
        }
    }
    
    editMessage($chat_id, $progress_msg_id, 
        "✅ <b>Alert Sent Successfully!</b>\n\n" .
        "📊 Sent to: $success_count users\n" .
        "📅 Time: " . date('Y-m-d H:i:s') . "\n\n" .
        "📝 Alert: \"" . substr(htmlspecialchars($alert_message), 0, 100) . "...\""
    );
    
    bot_log("Alert sent by $chat_id to $success_count users");
}

function backup_status($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $backup_dirs = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    $latest_backup = null;
    $total_size = 0;
    
    if (!empty($backup_dirs)) {
        usort($backup_dirs, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $latest_backup = $backup_dirs[0];
    }
    
    foreach ($backup_dirs as $dir) {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            $total_size += filesize($file);
        }
    }
    
    $total_size_mb = round($total_size / 1024 / 1024, 2);
    
    $status_message = "💾 <b>Backup System Status</b>\n\n";
    
    $status_message .= "📊 <b>Storage Info:</b>\n";
    $status_message .= "• Total Backups: " . count($backup_dirs) . "\n";
    $status_message .= "• Storage Used: " . $total_size_mb . " MB\n";
    $status_message .= "• Backup Channel: " . BACKUP_CHANNEL_USERNAME . "\n";
    $status_message .= "• Channel ID: " . BACKUP_CHANNEL_ID . "\n\n";
    
    if ($latest_backup) {
        $latest_time = date('Y-m-d H:i:s', filemtime($latest_backup));
        $latest_size = 0;
        $files = glob($latest_backup . '/*');
        foreach ($files as $file) {
            $latest_size += filesize($file);
        }
        $latest_size_mb = round($latest_size / 1024 / 1024, 2);
        
        $status_message .= "🕒 <b>Latest Backup:</b>\n";
        $status_message .= "• Time: $latest_time\n";
        $status_message .= "• Folder: " . basename($latest_backup) . "\n";
        $status_message .= "• Size: $latest_size_mb MB\n";
        $status_message .= "• Files: " . count($files) . "\n\n";
    } else {
        $status_message .= "❌ <b>No backups found!</b>\n\n";
    }
    
    $status_message .= "⏰ <b>Auto-backup Schedule:</b>\n";
    $status_message .= "• Daily at " . AUTO_BACKUP_HOUR . ":00\n";
    $status_message .= "• Keep last 7 backups\n";
    $status_message .= "• Upload to " . BACKUP_CHANNEL_USERNAME . "\n\n";
    
    $status_message .= "🛠️ <b>Manual Commands:</b>\n";
    $status_message .= "• <code>/backup</code> - Full backup\n";
    $status_message .= "• <code>/quickbackup</code> - Quick backup\n";
    $status_message .= "• <code>/backupstatus</code> - This info\n\n";
    
    $status_message .= "🔗 <b>Backup Channel:</b> " . BACKUP_CHANNEL_USERNAME;
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📡 Visit ' . BACKUP_CHANNEL_USERNAME, 'url' => 'https://t.me/ETBackup'],
                ['text' => '🔄 Run Backup', 'callback_data' => 'run_backup']
            ],
            [
                ['text' => '🧹 Clean Old Backups', 'callback_data' => 'clean_backups'],
                ['text' => '📊 System Info', 'callback_data' => 'system_info']
            ]
        ]
    ];
    
    sendMessage($chat_id, $status_message, $keyboard, 'HTML');
}

// ======================================================
// MOVIE APPEND FUNCTION (for channel posts)
// ======================================================
function append_movie($movie_name, $message_id_raw, $date = null, $video_path = '', $quality = 'Unknown', $size = 'Unknown', $language = 'Hindi', $channel_id = CHANNEL_ID) {
    if (empty(trim($movie_name))) {
        bot_log("Cannot append empty movie name", 'WARNING');
        return;
    }
    
    if ($date === null) $date = date('d-m-Y');
    
    // Sanitize inputs
    $movie_name = trim($movie_name);
    $message_id_raw = trim($message_id_raw);
    $quality = trim($quality);
    $size = trim($size);
    $language = trim($language);
    
    $entry = [$movie_name, $message_id_raw, $date, $video_path, $quality, $size, $language, $channel_id];
    
    $handle = fopen(CSV_FILE, "a");
    if ($handle === FALSE) {
        bot_log("Cannot open CSV file for appending", 'ERROR');
        return;
    }
    
    fputcsv($handle, $entry);
    fclose($handle);

    // Update cache
    global $movie_messages, $movie_cache, $waiting_users;
    
    $movie = strtolower($movie_name);
    $item = [
        'movie_name' => $movie_name,
        'message_id_raw' => $message_id_raw,
        'date' => $date,
        'video_path' => $video_path,
        'quality' => $quality,
        'size' => $size,
        'language' => $language,
        'channel_id' => $channel_id,
        'message_id' => is_numeric($message_id_raw) ? intval($message_id_raw) : null
    ];
    
    if (!isset($movie_messages[$movie])) {
        $movie_messages[$movie] = [];
    }
    $movie_messages[$movie][] = $item;
    
    // Clear cache to force reload
    $movie_cache = [];

    // Notify waiting users
    foreach ($waiting_users as $query => $users) {
        if (strpos($movie, $query) !== false) {
            foreach ($users as $user_data) {
                list($user_chat_id, $user_id) = $user_data;
                deliver_item_to_chat($user_chat_id, $item);
                sendMessage($user_chat_id, 
                    "✅ <b>Movie Added!</b>\n\n" .
                    "🎬 '" . htmlspecialchars($query) . "'\n" .
                    "has been added to the channel!\n\n" .
                    "📢 Check it out in " . ($channel_id == NEW_PRIVATE_CHANNEL ? "Private Channel" : "Main Channel"),
                    null, 'HTML'
                );
            }
            unset($waiting_users[$query]);
        }
    }

    update_stats('total_movies', 1);
    bot_log("Movie appended: $movie_name with ID $message_id_raw to channel $channel_id");
    
    // Notify admin about new addition
    if ($channel_id == NEW_PRIVATE_CHANNEL) {
        sendMessage(ADMIN_ID, 
            "🎬 <b>New Private Channel Addition</b>\n\n" .
            "📝 Movie: <code>" . htmlspecialchars($movie_name) . "</code>\n" .
            "🆔 Message ID: $message_id_raw\n" .
            "📊 Quality: $quality\n" .
            "🗣️ Language: $language\n" .
            "📅 Date: $date\n" .
            "🔗 Channel: Private Channel",
            null, 'HTML'
        );
    }
}

// ======================================================
// MAIN UPDATE PROCESSING
// ======================================================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    // Maintenance mode check
    global $MAINTENANCE_MODE, $MAINTENANCE_MESSAGE;
    if ($MAINTENANCE_MODE && isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        $user_id = $update['message']['from']['id'] ?? null;
        
        // Allow admin even in maintenance
        if ($user_id != ADMIN_ID) {
            sendMessage($chat_id, $MAINTENANCE_MESSAGE, null, 'HTML');
            bot_log("Maintenance mode active - message blocked from $chat_id");
            exit;
        }
    }

    // Load movies into cache
    get_cached_movies();

    // ==================== CHANNEL POST HANDLING ====================
    if (isset($update['channel_post'])) {
        $message = $update['channel_post'];
        $message_id = $message['message_id'];
        $chat_id = $message['chat']['id'];
        
        // Determine which channel
        $channel_id = (string)$chat_id;
        $is_private_channel = ($channel_id == NEW_PRIVATE_CHANNEL);
        $is_main_channel = ($channel_id == CHANNEL_ID);
        
        if ($is_main_channel || $is_private_channel) {
            $text = '';
            $quality = 'Unknown';
            $size = 'Unknown';
            $language = 'Hindi';
            
            // Extract information from message
            if (isset($message['caption'])) {
                $text = $message['caption'];
            }
            elseif (isset($message['text'])) {
                $text = $message['text'];
            }
            elseif (isset($message['document'])) {
                $text = $message['document']['file_name'];
                $size = round($message['document']['file_size'] / (1024 * 1024), 2) . ' MB';
            }
            else {
                $text = 'Uploaded Media - ' . date('d-m-Y H:i');
            }
            
            // Extract quality from text
            if (stripos($text, '1080') !== false) $quality = '1080p';
            elseif (stripos($text, '720') !== false) $quality = '720p';
            elseif (stripos($text, '480') !== false) $quality = '480p';
            elseif (stripos($text, 'hd') !== false) $quality = 'HD';
            
            // Extract language
            if (stripos($text, 'english') !== false) $language = 'English';
            if (stripos($text, 'hindi') !== false) $language = 'Hindi';
            if (stripos($text, 'tamil') !== false) $language = 'Tamil';
            if (stripos($text, 'telugu') !== false) $language = 'Telugu';
            if (stripos($text, 'punjabi') !== false) $language = 'Punjabi';
            if (stripos($text, 'dual') !== false) $language = 'Dual Audio';
            
            if (!empty(trim($text))) {
                append_movie($text, $message_id, date('d-m-Y'), '', $quality, $size, $language, $channel_id);
                
                // Log channel post
                $channel_name = $is_private_channel ? 'Private Channel' : 'Main Channel';
                bot_log("Channel post added: $text to $channel_name (ID: $message_id)");
            }
        }
    }

    // ==================== MESSAGE HANDLING ====================
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        $chat_type = $message['chat']['type'] ?? 'private';

        // Update user data
        $user_info = [
            'first_name' => $message['from']['first_name'] ?? '',
            'last_name' => $message['from']['last_name'] ?? '',
            'username' => $message['from']['username'] ?? ''
        ];
        update_user_data($user_id, $user_info);

        // Group message filtering
        if ($chat_type !== 'private') {
            if (strpos($text, '/') === 0) {
                // Commands allow karo
            } else {
                if (!is_valid_movie_query($text)) {
                    bot_log("Invalid group message blocked from $chat_id: " . substr($text, 0, 50));
                    return;
                }
            }
        }

        // Command handling
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $params = array_slice($parts, 1);
            
            handle_command($chat_id, $user_id, $command, $params);
            
        } else if (!empty(trim($text))) {
            // Regular text - treat as movie search
            $lang = detect_language($text);
            send_multilingual_response($chat_id, 'searching', $lang);
            advanced_search($chat_id, $text, $user_id);
        }
    }

    // ==================== CALLBACK QUERY HANDLING ====================
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $message = $query['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $query['from']['id'];
        $data = $query['data'];
        
        // Always answer callback query first
        answerCallbackQuery($query['id'], "Processing...", false);
        
        global $movie_messages;
        
        // Movie selection from search results
        if (strpos($data, 'movie_') === 0) {
            $movie_encoded = str_replace('movie_', '', $data);
            $movie_name = base64_decode($movie_encoded);
            $movie_lower = strtolower($movie_name);
            
            if (isset($movie_messages[$movie_lower])) {
                $entries = $movie_messages[$movie_lower];
                $cnt = 0;
                
                foreach ($entries as $entry) {
                    deliver_item_to_chat($chat_id, $entry);
                    usleep(200000); // 0.2 second delay
                    $cnt++;
                }
                
                sendMessage($chat_id, 
                    "✅ <b>$cnt items sent!</b>\n\n" .
                    "🎬 Movie: " . htmlspecialchars($movie_name) . "\n" .
                    "📢 Join our channels for more:\n" .
                    "• " . MAIN_CHANNEL . "\n" .
                    "• Private Movies Channel",
                    null, 'HTML'
                );
                
                update_user_activity($user_id, 'download');
                answerCallbackQuery($query['id'], "✅ $cnt items sent!");
                
            } else {
                sendMessage($chat_id, "❌ Movie not found: " . htmlspecialchars($movie_name));
                answerCallbackQuery($query['id'], "❌ Movie not available");
            }
        }
        
        // Pagination controls
        elseif (strpos($data, 'tu_prev_') === 0) {
            $parts = explode('_', $data);
            $page = (int)$parts[2];
            $filter_channel = isset($parts[3]) ? $parts[3] : null;
            totalupload_controller($chat_id, $page, $filter_channel);
            answerCallbackQuery($query['id'], "Page $page");
        }
        
        elseif (strpos($data, 'tu_next_') === 0) {
            $parts = explode('_', $data);
            $page = (int)$parts[2];
            $filter_channel = isset($parts[3]) ? $parts[3] : null;
            totalupload_controller($chat_id, $page, $filter_channel);
            answerCallbackQuery($query['id'], "Page $page");
        }
        
        elseif (strpos($data, 'tu_view_') === 0) {
            $parts = explode('_', $data);
            $page = (int)$parts[2];
            $filter_channel = isset($parts[3]) ? $parts[3] : null;
            
            $all = get_all_movies_list($filter_channel);
            $pg = paginate_movies($all, $page);
            forward_page_movies($chat_id, $pg['slice']);
            
            answerCallbackQuery($query['id'], "Re-sent current page movies");
        }
        
        elseif (strpos($data, 'tu_info_') === 0) {
            $parts = explode('_', $data);
            $page = (int)$parts[2];
            $filter_channel = isset($parts[3]) ? $parts[3] : null;
            
            $all = get_all_movies_list($filter_channel);
            $pg = paginate_movies($all, $page);
            
            $channel_name = $filter_channel == NEW_PRIVATE_CHANNEL ? "Private Channel" : 
                           ($filter_channel == CHANNEL_ID ? "Main Channel" : "All Channels");
            
            $info = "📊 <b>Page Information</b>\n\n";
            $info .= "📄 Page: $page/{$pg['total_pages']}\n";
            $info .= "🎬 Movies: " . count($pg['slice']) . "\n";
            $info .= "📁 Total: {$pg['total']} movies\n";
            $info .= "📢 Channel: $channel_name\n\n";
            
            foreach ($pg['slice'] as $index => $movie) {
                $info .= ($index + 1) . ". {$movie['movie_name']} [{$movie['quality']}]\n";
            }
            
            sendMessage($chat_id, $info, null, 'HTML');
            answerCallbackQuery($query['id'], "Page $page info");
        }
        
        elseif ($data === 'tu_stop') {
            sendMessage($chat_id, "✅ Pagination stopped.\n\nType /totalupload to start again.");
            answerCallbackQuery($query['id'], "Stopped");
        }
        
        elseif ($data === 'current_page') {
            answerCallbackQuery($query['id'], "You're on this page");
        }
        
        // Channel filtering
        elseif (strpos($data, 'filter_channel_') === 0) {
            $channel = str_replace('filter_channel_', '', $data);
            if ($channel == 'all') {
                totalupload_controller($chat_id, 1, null);
            } else {
                totalupload_controller($chat_id, 1, $channel);
            }
            answerCallbackQuery($query['id'], "Filter applied");
        }
        
        // Movie requests
        elseif (strpos($data, 'auto_request_') === 0) {
            $movie_name = base64_decode(str_replace('auto_request_', '', $data));
            $lang = detect_language($movie_name);
            
            $request_id = add_movie_request($user_id, $movie_name, $lang);
            
            if ($request_id) {
                send_multilingual_response($chat_id, 'request_success', $lang);
                update_user_activity($user_id, 'movie_request');
                
                // Show request details
                sendMessage($chat_id, 
                    "📝 <b>Request Details</b>\n\n" .
                    "🆔 Request ID: <code>$request_id</code>\n" .
                    "🎬 Movie: " . htmlspecialchars($movie_name) . "\n" .
                    "📅 Date: " . date('Y-m-d H:i:s') . "\n" .
                    "🗣️ Language: " . ucfirst($lang) . "\n\n" .
                    "⏳ We'll add it within 24 hours!",
                    null, 'HTML'
                );
                
                answerCallbackQuery($query['id'], "✅ Request sent!");
            } else {
                send_multilingual_response($chat_id, 'request_limit', $lang);
                answerCallbackQuery($query['id'], "❌ Daily limit reached!", true);
            }
        }
        
        elseif ($data === 'request_movie') {
            sendMessage($chat_id, 
                "📝 <b>Request a Movie</b>\n\n" .
                "To request a movie, use:\n" .
                "<code>/request movie_name</code>\n\n" .
                "📝 <b>Examples:</b>\n" .
                "• <code>/request Avengers Endgame</code>\n" .
                "• <code>/request New Hindi Movie 2024</code>\n\n" .
                "📊 <b>Daily Limit:</b> " . DAILY_REQUEST_LIMIT . " requests\n" .
                "💡 <b>Tip:</b> Check spelling before requesting!",
                null, 'HTML'
            );
            answerCallbackQuery($query['id'], "Request instructions");
        }
        
        // User stats
        elseif ($data === 'my_stats') {
            show_user_stats($chat_id, $user_id);
            answerCallbackQuery($query['id'], "Your statistics");
        }
        
        elseif ($data === 'show_leaderboard') {
            show_leaderboard($chat_id);
            answerCallbackQuery($query['id'], "Leaderboard");
        }
        
        elseif ($data === 'refresh_stats') {
            show_user_stats($chat_id, $user_id);
            answerCallbackQuery($query['id'], "Refreshed!");
        }
        
        elseif ($data === 'refresh_leaderboard') {
            show_leaderboard($chat_id);
            answerCallbackQuery($query['id'], "Leaderboard refreshed");
        }
        
        // Backup commands
        elseif ($data === 'backup_status') {
            if ($chat_id == ADMIN_ID) {
                backup_status($chat_id);
                answerCallbackQuery($query['id'], "Backup status");
            } else {
                answerCallbackQuery($query['id'], "❌ Admin only!", true);
            }
        }
        
        elseif ($data === 'run_backup') {
            if ($chat_id == ADMIN_ID) {
                manual_backup($chat_id);
                answerCallbackQuery($query['id'], "Backup started");
            } else {
                answerCallbackQuery($query['id'], "❌ Admin only!", true);
            }
        }
        
        elseif ($data === 'clean_backups') {
            if ($chat_id == ADMIN_ID) {
                perform_cleanup($chat_id);
                answerCallbackQuery($query['id'], "Cleanup started");
            } else {
                answerCallbackQuery($query['id'], "❌ Admin only!", true);
            }
        }
        
        // Help command
        elseif ($data === 'help_command') {
            handle_command($chat_id, $user_id, '/help', []);
            answerCallbackQuery($query['id'], "Help menu");
        }
        
        elseif ($data === 'browse_all') {
            totalupload_controller($chat_id, 1);
            answerCallbackQuery($query['id'], "Browse all movies");
        }
        
        elseif ($data === 'download_latest') {
            show_latest_movies($chat_id, 10);
            answerCallbackQuery($query['id'], "Latest movies");
        }
        
        // Quality and language filters
        elseif (strpos($data, 'download_quality_') === 0) {
            $quality = str_replace('download_quality_', '', $data);
            show_movies_by_quality($chat_id, $quality);
            answerCallbackQuery($query['id'], "$quality movies");
        }
        
        elseif ($data === 'show_qualities') {
            $message = "🎬 <b>Select Quality</b>\n\n";
            $message .= "Choose a quality to filter movies:\n\n";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📺 1080p', 'callback_data' => 'download_quality_1080p'],
                        ['text' => '📱 720p', 'callback_data' => 'download_quality_720p']
                    ],
                    [
                        ['text' => '💿 480p', 'callback_data' => 'download_quality_480p'],
                        ['text' => '⭐ HD', 'callback_data' => 'download_quality_hd']
                    ],
                    [
                        ['text' => '🔙 Back', 'callback_data' => 'browse_all']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $message, $keyboard, 'HTML');
            answerCallbackQuery($query['id'], "Select quality");
        }
        
        elseif (strpos($data, 'download_lang_') === 0) {
            $language = str_replace('download_lang_', '', $data);
            show_movies_by_language($chat_id, $language);
            answerCallbackQuery($query['id'], "$language movies");
        }
        
        elseif ($data === 'show_languages') {
            $message = "🎬 <b>Select Language</b>\n\n";
            $message .= "Choose a language to filter movies:\n\n";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🇮🇳 Hindi', 'callback_data' => 'download_lang_hindi'],
                        ['text' => '🇺🇸 English', 'callback_data' => 'download_lang_english']
                    ],
                    [
                        ['text' => '🇮🇳 Tamil', 'callback_data' => 'download_lang_tamil'],
                        ['text' => '🇮🇳 Telugu', 'callback_data' => 'download_lang_telugu']
                    ],
                    [
                        ['text' => '🇮🇳 Punjabi', 'callback_data' => 'download_lang_punjabi'],
                        ['text' => '🔙 Back', 'callback_data' => 'browse_all']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $message, $keyboard, 'HTML');
            answerCallbackQuery($query['id'], "Select language");
        }
        
        // Request approval (admin only)
        elseif (strpos($data, 'approve_request_') === 0) {
            if ($chat_id == ADMIN_ID) {
                $request_id = str_replace('approve_request_', '', $data);
                // Implement request approval logic here
                sendMessage($chat_id, 
                    "✅ <b>Request Approved</b>\n\n" .
                    "🆔 Request ID: <code>$request_id</code>\n" .
                    "📅 Time: " . date('Y-m-d H:i:s') . "\n\n" .
                    "👨‍💻 Notify the user and add the movie to CSV.",
                    null, 'HTML'
                );
                answerCallbackQuery($query['id'], "Request approved");
            } else {
                answerCallbackQuery($query['id'], "❌ Admin only!", true);
            }
        }
        
        elseif (strpos($data, 'reject_request_') === 0) {
            if ($chat_id == ADMIN_ID) {
                $request_id = str_replace('reject_request_', '', $data);
                sendMessage($chat_id, 
                    "❌ <b>Request Rejected</b>\n\n" .
                    "🆔 Request ID: <code>$request_id</code>\n" .
                    "📅 Time: " . date('Y-m-d H:i:s') . "\n\n" .
                    "💡 Notify the user with reason.",
                    null, 'HTML'
                );
                answerCallbackQuery($query['id'], "Request rejected");
            } else {
                answerCallbackQuery($query['id'], "❌ Admin only!", true);
            }
        }
        
        // Admin stats refresh
        elseif ($data === 'refresh_admin_stats') {
            if ($chat_id == ADMIN_ID) {
                admin_stats($chat_id);
                answerCallbackQuery($query['id'], "Stats refreshed");
            } else {
                answerCallbackQuery($query['id'], "❌ Admin only!", true);
            }
        }
        
        elseif ($data === 'show_csv_admin') {
            if ($chat_id == ADMIN_ID) {
                show_csv_data($chat_id, true);
                answerCallbackQuery($query['id'], "CSV data");
            } else {
                answerCallbackQuery($query['id'], "❌ Admin only!", true);
            }
        }
        
        elseif ($data === 'run_cleanup') {
            if ($chat_id == ADMIN_ID) {
                perform_cleanup($chat_id);
                answerCallbackQuery($query['id'], "Cleanup started");
            } else {
                answerCallbackQuery($query['id'], "❌ Admin only!", true);
            }
        }
        
        elseif ($data === 'system_info') {
            if ($chat_id == ADMIN_ID) {
                $stats = get_stats();
                $memory_usage = round(memory_get_usage() / 1024 / 1024, 2);
                $memory_peak = round(memory_get_peak_usage() / 1024 / 1024, 2);
                
                $system_info = "🖥️ <b>System Information</b>\n\n";
                $system_info .= "📱 <b>PHP Version:</b> " . PHP_VERSION . "\n";
                $system_info .= "💾 <b>Memory Usage:</b> $memory_usage MB\n";
                $system_info .= "📈 <b>Memory Peak:</b> $memory_peak MB\n";
                $system_info .= "⏰ <b>Server Time:</b> " . date('Y-m-d H:i:s') . "\n";
                $system_info .= "🌐 <b>Server IP:</b> " . ($_SERVER['SERVER_ADDR'] ?? 'N/A') . "\n";
                $system_info .= "📁 <b>Free Disk Space:</b> " . round(disk_free_space(__DIR__) / 1024 / 1024 / 1024, 2) . " GB\n";
                $system_info .= "🔧 <b>Bot Version:</b> 3.0.0\n\n";
                
                $system_info .= "📊 <b>Bot Stats:</b>\n";
                $system_info .= "• Uptime: " . ($stats['server_start_time'] ?? 'N/A') . "\n";
                $system_info .= "• Last Updated: " . ($stats['last_updated'] ?? 'N/A') . "\n";
                $system_info .= "• Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
                $system_info .= "• Total Users: " . (count(json_decode(file_get_contents(USERS_FILE), true)['users'] ?? [])) . "\n";
                
                sendMessage($chat_id, $system_info, null, 'HTML');
                answerCallbackQuery($query['id'], "System info");
            } else {
                answerCallbackQuery($query['id'], "❌ Admin only!", true);
            }
        }
        
        // Support and feedback UI
        elseif ($data === 'report_bug_ui') {
            sendMessage($chat_id, 
                "🐛 <b>Report a Bug</b>\n\n" .
                "To report a bug, use:\n" .
                "<code>/report bug_description</code>\n\n" .
                "📝 <b>Examples:</b>\n" .
                "• <code>/report Search not working for new movies</code>\n" .
                "• <code>/report Movie download failing</code>\n\n" .
                "💡 <b>Include:</b>\n" .
                "• What you were trying to do\n" .
                "• What happened instead\n" .
                "• Screenshot if possible\n\n" .
                "⭐ <b>You earn 10 points for bug reports!</b>",
                null, 'HTML'
            );
            answerCallbackQuery($query['id'], "Bug report instructions");
        }
        
        elseif ($data === 'suggest_feature_ui') {
            sendMessage($chat_id, 
                "💡 <b>Suggest a Feature</b>\n\n" .
                "To suggest a feature, use:\n" .
                "<code>/feedback your_feature_idea</code>\n\n" .
                "📝 <b>Examples:</b>\n" .
                "• <code>/feedback Add TV series category</code>\n" .
                "• <code>/feedback Add advanced search filters</code>\n\n" .
                "💡 <b>Good suggestions include:</b>\n" .
                "• What the feature should do\n" .
                "• Why it would be useful\n" .
                "• How users would benefit\n\n" .
                "⭐ <b>You earn 5 points for suggestions!</b>",
                null, 'HTML'
            );
            answerCallbackQuery($query['id'], "Feature suggestion instructions");
        }
        
        elseif ($data === 'give_feedback_ui') {
            sendMessage($chat_id, 
                "📝 <b>Give Feedback</b>\n\n" .
                "To give feedback, use:\n" .
                "<code>/feedback your_feedback</code>\n\n" .
                "📝 <b>Examples:</b>\n" .
                "• <code>/feedback Great bot! Very useful.</code>\n" .
                "• <code>/feedback Could improve search speed.</code>\n\n" .
                "💡 <b>We value:</b>\n" .
                "• Positive feedback\n" .
                "• Constructive criticism\n" .
                "• Improvement ideas\n" .
                "• Your experience\n\n" .
                "⭐ <b>You earn 5 points for feedback!</b>",
                null, 'HTML'
            );
            answerCallbackQuery($query['id'], "Feedback instructions");
        }
        
        else {
            sendMessage($chat_id, "❌ Unknown action: $data");
            answerCallbackQuery($query['id'], "❌ Unknown action");
        }
    }

    // ==================== SCHEDULED TASKS ====================
    $current_hour = date('H');
    $current_minute = date('i');

    // Daily auto-backup at 3 AM
    if ($current_hour == AUTO_BACKUP_HOUR && $current_minute == '00') {
        auto_backup();
        bot_log("Daily auto-backup completed at " . date('Y-m-d H:i:s'));
    }

    // Hourly cache cleanup at 30 minutes past the hour
    if ($current_minute == '30') {
        clear_movie_cache();
        bot_log("Hourly cache cleanup at " . date('Y-m-d H:i:s'));
    }
}

// ======================================================
// MANUAL TESTING AND WEBHOOK SETUP
// ======================================================

// Webhook setup endpoint
if (php_sapi_name() === 'cli' || isset($_GET['setwebhook'])) {
    $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                  "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    
    // Remove query parameters
    $webhook_url = strtok($webhook_url, '?');
    
    $result = apiRequest('setWebhook', ['url' => $webhook_url]);
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>🤖 Entertainment Tadka Bot Setup</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
            .container { max-width: 800px; margin: 50px auto; background: rgba(255,255,255,0.1); padding: 30px; border-radius: 15px; backdrop-filter: blur(10px); }
            h1 { color: #ffd700; text-align: center; }
            .success { color: #4CAF50; background: rgba(76, 175, 80, 0.2); padding: 10px; border-radius: 5px; }
            .error { color: #f44336; background: rgba(244, 67, 54, 0.2); padding: 10px; border-radius: 5px; }
            .info { background: rgba(33, 150, 243, 0.2); padding: 15px; border-radius: 5px; margin: 15px 0; }
            code { background: rgba(0,0,0,0.3); padding: 2px 5px; border-radius: 3px; }
            a { color: #ffd700; text-decoration: none; }
            a:hover { text-decoration: underline; }
            .buttons { display: flex; gap: 10px; margin: 20px 0; }
            .btn { padding: 10px 20px; background: #667eea; border: none; color: white; border-radius: 5px; cursor: pointer; }
            .btn:hover { background: #764ba2; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🎬 Entertainment Tadka Bot Setup</h1>
            
            <div class='info'>
                <h3>📡 Webhook Setup Result</h3>
                <pre>" . htmlspecialchars($result) . "</pre>
                <p><strong>Webhook URL:</strong> <code>" . htmlspecialchars($webhook_url) . "</code></p>
            </div>";
    
    $bot_info = json_decode(apiRequest('getMe'), true);
    if ($bot_info && isset($bot_info['ok']) && $bot_info['ok']) {
        echo "<div class='info'>
                <h3>🤖 Bot Information</h3>
                <p><strong>Name:</strong> " . htmlspecialchars($bot_info['result']['first_name']) . "</p>
                <p><strong>Username:</strong> @" . htmlspecialchars($bot_info['result']['username']) . "</p>
                <p><strong>Bot ID:</strong> " . htmlspecialchars($bot_info['result']['id']) . "</p>
            </div>";
    }
    
    echo "<div class='info'>
            <h3>🌐 Channel Information</h3>
            <p><strong>Main Channel:</strong> " . MAIN_CHANNEL . "</p>
            <p><strong>Request Channel:</strong> " . REQUEST_CHANNEL . "</p>
            <p><strong>Backup Channel:</strong> " . BACKUP_CHANNEL_USERNAME . "</p>
            <p><strong>Private Channel:</strong> " . NEW_PRIVATE_CHANNEL . "</p>
        </div>
        
        <div class='info'>
            <h3>✅ System Status</h3>
            <p>CSV File: " . (file_exists(CSV_FILE) ? "✅ Exists (" . filesize(CSV_FILE) . " bytes)" : "❌ Missing") . "</p>
            <p>Users File: " . (file_exists(USERS_FILE) ? "✅ Exists (" . filesize(USERS_FILE) . " bytes)" : "❌ Missing") . "</p>
            <p>Stats File: " . (file_exists(STATS_FILE) ? "✅ Exists (" . filesize(STATS_FILE) . " bytes)" : "❌ Missing") . "</p>
            <p>Backup Directory: " . (file_exists(BACKUP_DIR) ? "✅ Exists" : "❌ Missing") . "</p>
            <p>Bot Token: " . (defined('BOT_TOKEN') && BOT_TOKEN ? "✅ Set" : "❌ Missing") . "</p>
        </div>
        
        <div class='buttons'>
            <a href='?test_save=1' class='btn'>🧪 Test Movie Save</a>
            <a href='?check_csv=1' class='btn'>📊 Check CSV</a>
            <a href='?test_stats=1' class='btn'>📈 Test Stats</a>
            <a href='?setwebhook=1' class='btn'>🔄 Reset Webhook</a>
        </div>
        
        <p><strong>💡 Tip:</strong> After setup, message your bot on Telegram with <code>/start</code></p>
        </div>
    </body>
    </html>";
    exit;
}

// Test movie save
if (isset($_GET['test_save'])) {
    function manual_save_to_csv($movie_name, $message_id, $quality = '1080p', $language = 'Hindi', $channel_id = CHANNEL_ID) {
        $entry = [$movie_name, $message_id, date('d-m-Y'), '', $quality, '1.5GB', $language, $channel_id];
        $handle = fopen(CSV_FILE, "a");
        if ($handle !== FALSE) {
            fputcsv($handle, $entry);
            fclose($handle);
            @chmod(CSV_FILE, 0644);
            return true;
        }
        return false;
    }
    
    // Add sample movies to main channel
    manual_save_to_csv("Metro In Dino (2025)", 1924, "1080p", "Hindi", CHANNEL_ID);
    manual_save_to_csv("Metro In Dino 2025 WebRip 480p", 1925, "480p", "Hindi", CHANNEL_ID);
    manual_save_to_csv("Metro In Dino (2025) Hindi 720p", 1926, "720p", "Hindi", CHANNEL_ID);
    manual_save_to_csv("Animal (2023) Hindi 1080p", 1927, "1080p", "Hindi", CHANNEL_ID);
    manual_save_to_csv("Avengers Endgame (2019) English", 1928, "1080p", "English", CHANNEL_ID);
    
    // Add sample movies to private channel
    manual_save_to_csv("Exclusive Series S01 (2024)", 3001, "1080p", "Hindi", NEW_PRIVATE_CHANNEL);
    manual_save_to_csv("Private Movie Collection", 3002, "720p", "English", NEW_PRIVATE_CHANNEL);
    manual_save_to_csv("Special Web Series", 3003, "1080p", "Hindi", NEW_PRIVATE_CHANNEL);
    
    echo "<div style='padding: 20px; background: #4CAF50; color: white; border-radius: 5px;'>
            <h3>✅ All 8 movies manually save ho gayi!</h3>
            <p>• 5 movies in Main Channel</p>
            <p>• 3 movies in Private Channel</p>
            <p>📊 Total: 8 test movies added successfully!</p>
          </div>";
    echo "<div style='margin-top: 20px;'>
            <a href='?check_csv=1' style='background: #2196F3; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none;'>📊 Check CSV</a>
            <a href='?test_stats=1' style='background: #9C27B0; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none; margin-left: 10px;'>📈 Test Stats</a>
            <a href='?setwebhook=1' style='background: #FF9800; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none; margin-left: 10px;'>🔄 Reset Webhook</a>
          </div>";
    exit;
}

// Check CSV
if (isset($_GET['check_csv'])) {
    echo "<h3>📊 CSV Content Preview</h3>";
    if (file_exists(CSV_FILE)) {
        $lines = file(CSV_FILE);
        echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px; max-height: 400px; overflow-y: auto;'>";
        foreach ($lines as $line) {
            echo htmlspecialchars($line);
        }
        echo "</pre>";
        echo "<p><strong>Total Lines:</strong> " . count($lines) . "</p>";
        echo "<p><strong>File Size:</strong> " . filesize(CSV_FILE) . " bytes</p>";
    } else {
        echo "<div style='color: #f44336; background: #ffebee; padding: 10px; border-radius: 5px;'>❌ CSV file not found!</div>";
    }
    exit;
}

// Test stats
if (isset($_GET['test_stats'])) {
    echo "<h3>📈 Bot Statistics</h3>";
    $stats = get_stats();
    echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";
    print_r($stats);
    echo "</pre>";
    
    echo "<h3>👥 User Data</h3>";
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";
    print_r($users_data);
    echo "</pre>";
    
    echo "<h3>📝 Request Data</h3>";
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";
    print_r($requests_data);
    echo "</pre>";
    exit;
}

// ======================================================
// DEFAULT PAGE DISPLAY
// ======================================================
if (!isset($update) || !$update) {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    // Count private channel movies
    $private_movies = 0;
    $all_movies = get_all_movies_list();
    foreach ($all_movies as $movie) {
        if ($movie['channel_id'] == NEW_PRIVATE_CHANNEL) {
            $private_movies++;
        }
    }
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>🎬 Entertainment Tadka Bot</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                margin: 0; 
                padding: 0; 
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
            }
            .container { 
                max-width: 1200px; 
                margin: 0 auto; 
                padding: 20px;
            }
            header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 40px 0;
                text-align: center;
                border-radius: 0 0 20px 20px;
                margin-bottom: 30px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            h1 { 
                margin: 0; 
                font-size: 3em; 
                text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            }
            .tagline {
                font-size: 1.2em;
                opacity: 0.9;
                margin-top: 10px;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            .stat-card {
                background: white;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
                transition: transform 0.3s;
            }
            .stat-card:hover {
                transform: translateY(-5px);
            }
            .stat-number {
                font-size: 2.5em;
                font-weight: bold;
                color: #667eea;
                margin: 10px 0;
            }
            .stat-label {
                color: #666;
                font-size: 0.9em;
            }
            .channel-card {
                background: white;
                border-radius: 10px;
                padding: 20px;
                margin: 20px 0;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .btn {
                display: inline-block;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 12px 25px;
                border-radius: 25px;
                text-decoration: none;
                margin: 10px 5px;
                transition: all 0.3s;
                border: none;
                cursor: pointer;
                font-size: 1em;
            }
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            }
            .features {
                background: white;
                border-radius: 10px;
                padding: 30px;
                margin: 30px 0;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .feature-list {
                list-style: none;
                padding: 0;
            }
            .feature-list li {
                padding: 10px 0;
                border-bottom: 1px solid #eee;
                display: flex;
                align-items: center;
            }
            .feature-list li:before {
                content: '✅';
                margin-right: 10px;
                color: #4CAF50;
            }
            .setup-steps {
                background: #e3f2fd;
                border-radius: 10px;
                padding: 25px;
                margin: 30px 0;
            }
            .step {
                margin: 15px 0;
                padding-left: 20px;
                position: relative;
            }
            .step:before {
                content: '▶';
                position: absolute;
                left: 0;
                color: #667eea;
            }
            .alert {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 5px;
                padding: 15px;
                margin: 20px 0;
            }
            .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
            .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
            .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
            code {
                background: #f8f9fa;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: 'Courier New', monospace;
                color: #e83e8c;
            }
            .channel-badges {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin: 20px 0;
            }
            .channel-badge {
                background: #667eea;
                color: white;
                padding: 8px 15px;
                border-radius: 20px;
                font-size: 0.9em;
                display: inline-flex;
                align-items: center;
            }
            .channel-badge.private { background: #9C27B0; }
            .channel-badge.backup { background: #607D8B; }
            .channel-badge.request { background: #FF9800; }
        </style>
    </head>
    <body>
        <header>
            <div class='container'>
                <h1>🎬 Entertainment Tadka Bot</h1>
                <div class='tagline'>Your Ultimate Movie Search & Delivery Telegram Bot</div>
            </div>
        </header>
        
        <div class='container'>
            <div class='alert info'>
                <strong>📢 Note:</strong> This is the bot server interface. To use the bot, visit <a href='https://t.me/EntertainmentTadka0786' target='_blank'>@EntertainmentTadka0786</a> on Telegram.
            </div>
            
            <div class='stats-grid'>
                <div class='stat-card'>
                    <div class='stat-number'>" . ($stats['total_movies'] ?? 0) . "</div>
                    <div class='stat-label'>Total Movies</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-number'>" . count($users_data['users'] ?? []) . "</div>
                    <div class='stat-label'>Total Users</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-number'>" . ($stats['total_searches'] ?? 0) . "</div>
                    <div class='stat-label'>Total Searches</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-number'>" . ($stats['total_downloads'] ?? 0) . "</div>
                    <div class='stat-label'>Total Downloads</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-number'>$private_movies</div>
                    <div class='stat-label'>Private Channel Movies</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-number'>" . (($stats['total_movies'] ?? 0) - $private_movies) . "</div>
                    <div class='stat-label'>Main Channel Movies</div>
                </div>
            </div>
            
            <div class='channel-card'>
                <h2>📢 Our Channels</h2>
                <div class='channel-badges'>
                    <span class='channel-badge'>🍿 Main: " . MAIN_CHANNEL . "</span>
                    <span class='channel-badge private'>🎬 Private: Private Movies</span>
                    <span class='channel-badge request'>📥 Requests: " . REQUEST_CHANNEL . "</span>
                    <span class='channel-badge backup'>🔒 Backup: " . BACKUP_CHANNEL_USERNAME . "</span>
                </div>
            </div>
            
            <div class='features'>
                <h2>🚀 Bot Features</h2>
                <ul class='feature-list'>
                    <li>Smart movie search with fuzzy matching</li>
                    <li>Multi-channel support (Main + Private channels)</li>
                    <li>Multi-language support (Hindi/English)</li>
                    <li>Advanced filtering by quality and language</li>
                    <li>Movie request system with daily limits</li>
                    <li>User points system and leaderboard</li>
                    <li>Automatic daily backups to Telegram channel</li>
                    <li>Detailed statistics and activity tracking</li>
                    <li>Admin panel for management</li>
                    <li>Bug reporting and feedback system</li>
                    <li>Group chat filtering to prevent spam</li>
                    <li>Maintenance mode for updates</li>
                </ul>
            </div>
            
            <div class='setup-steps'>
                <h2>⚡ Quick Setup</h2>
                <div class='step'><strong>Step 1:</strong> <a href='?setwebhook=1' class='btn'>Set Webhook</a> - Configure bot webhook URL</div>
                <div class='step'><strong>Step 2:</strong> <a href='?test_save=1' class='btn'>Test Movie Save</a> - Add sample movies to database</div>
                <div class='step'><strong>Step 3:</strong> <a href='?check_csv=1' class='btn'>Check Database</a> - Verify CSV file structure</div>
                <div class='step'><strong>Step 4:</strong> Message your bot on Telegram with <code>/start</code></div>
            </div>
            
            <div class='alert success'>
                <strong>✅ Status:</strong> Bot is running
                <br><strong>📅 Last Updated:</strong> " . ($stats['last_updated'] ?? 'N/A') . "
                <br><strong>🔧 Version:</strong> 3.0.0
                <br><strong>👨‍💻 Developer:</strong> @EntertainmentTadka0786
            </div>
            
            <div style='text-align: center; margin: 40px 0;'>
                <a href='https://t.me/EntertainmentTadka0786' target='_blank' class='btn'>🤖 Use Bot on Telegram</a>
                <a href='https://t.me/EntertainmentTadka786' target='_blank' class='btn'>🍿 Join Main Channel</a>
                <a href='https://t.me/+c6YctyoI9iA2M2Rl' target='_blank' class='btn'>🎬 Join Private Channel</a>
                <a href='?setwebhook=1' class='btn'>⚡ Setup & Configuration</a>
            </div>
            
            <div class='alert warning'>
                <strong>⚠️ Important:</strong> Ensure BOT_TOKEN environment variable is set on Render.com dashboard. Without it, the bot won't work.
            </div>
        </div>
    </body>
    </html>";
}
?>
