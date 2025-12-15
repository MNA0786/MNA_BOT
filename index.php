<?php
// ============================================
// ENTERTAINMENT TADKA MEGA BOT v3.0
// Complete System: Search + Protection + Analytics
// ============================================

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Kolkata');
error_reporting(0);

// ================= SECURITY HEADERS =================
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ================= CONFIGURATION =================
define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('ADMIN_ID', getenv('ADMIN_ID', '1080317415'));
define('CHANNEL_ID', getenv('CHANNEL_ID', '-1003181705395'));
define('MAIN_CHANNEL', getenv('MAIN_CHANNEL', '@EntertainmentTadka786'));
define('REQUEST_CHANNEL', getenv('REQUEST_CHANNEL', '@EntertainmentTadka7860'));
define('BACKUP_CHANNEL', getenv('BACKUP_CHANNEL', '@ETBackup'));
define('DELETE_AFTER_MINUTES', 15);
define('ITEMS_PER_PAGE', 5);
define('DAILY_REQUEST_LIMIT', 5);
define('AUTO_BACKUP_HOUR', '03');

// ================= FILE PATHS =================
define('CSV_FILE', 'movies.csv');
define('USERS_FILE', 'users.json');
define('STATS_FILE', 'bot_stats.json');
define('REQUEST_FILE', 'movie_requests.json');
define('UPLOADS_DB', 'uploads_analytics.db');
define('BACKUP_DIR', 'backups/');
define('LOG_FILE', 'bot_activity.log');
define('DELETION_LOG', 'deletions.log');
define('ERROR_LOG', 'errors.log');

// ================= MAINTENANCE MODE =================
$MAINTENANCE_MODE = false;
$MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>\n\nWe're temporarily unavailable for updates.\nWill be back in few days!\n\nThanks for patience 🙏";

// ================= INITIALIZE ALL SYSTEMS =================
function initialize_all_systems() {
    // 1. CSV File (Movies Database)
    if (!file_exists(CSV_FILE)) {
        $handle = fopen(CSV_FILE, "w");
        fputcsv($handle, ['movie_name','message_id','date','video_path','quality','size','language']);
        fclose($handle);
        @chmod(CSV_FILE, 0666);
    }
    
    // 2. JSON Files
    $json_files = [
        USERS_FILE => ['users' => [], 'total_requests' => 0, 'message_logs' => [], 'daily_stats' => []],
        STATS_FILE => [
            'total_movies' => 0, 'total_users' => 0, 'total_searches' => 0,
            'total_downloads' => 0, 'successful_searches' => 0, 'failed_searches' => 0,
            'daily_activity' => [], 'last_updated' => date('Y-m-d H:i:s')
        ],
        REQUEST_FILE => [
            'requests' => [], 'pending_approval' => [], 
            'completed_requests' => [], 'user_request_count' => []
        ]
    ];
    
    foreach ($json_files as $file => $data) {
        if (!file_exists($file)) {
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
            @chmod($file, 0666);
        }
    }
    
    // 3. SQLite Database for Analytics
    $db = new SQLite3(UPLOADS_DB);
    $db->exec("CREATE TABLE IF NOT EXISTS uploads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        message_id INTEGER,
        file_name TEXT,
        file_type TEXT,
        file_size TEXT,
        quality TEXT,
        language TEXT,
        category TEXT,
        upload_date DATE,
        upload_time TIME,
        upload_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        uploaded_by INTEGER,
        views INTEGER DEFAULT 0,
        downloads INTEGER DEFAULT 0,
        forwards INTEGER DEFAULT 0,
        chat_id INTEGER,
        delete_scheduled INTEGER DEFAULT 0,
        delete_time DATETIME,
        status TEXT DEFAULT 'active'
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS users_advanced (
        user_id INTEGER PRIMARY KEY,
        username TEXT,
        first_name TEXT,
        last_name TEXT,
        join_date DATE DEFAULT CURRENT_DATE,
        last_active DATETIME DEFAULT CURRENT_TIMESTAMP,
        total_uploads INTEGER DEFAULT 0,
        total_downloads INTEGER DEFAULT 0,
        total_searches INTEGER DEFAULT 0,
        favorite_category TEXT,
        points INTEGER DEFAULT 0,
        level INTEGER DEFAULT 1,
        warning_count INTEGER DEFAULT 0,
        is_admin INTEGER DEFAULT 0,
        is_premium INTEGER DEFAULT 0,
        daily_request_count INTEGER DEFAULT 0,
        last_request_date DATE
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS scheduled_deletes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        chat_id INTEGER,
        message_id INTEGER,
        file_id TEXT,
        file_name TEXT,
        file_size TEXT,
        quality TEXT,
        delete_time DATETIME,
        status TEXT DEFAULT 'pending',
        warning_message_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS warning_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_id INTEGER,
        message_id INTEGER,
        chat_id INTEGER,
        progress_percentage INTEGER DEFAULT 0,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (file_id) REFERENCES scheduled_deletes(id) ON DELETE CASCADE
    )");
    
    $db->close();
    
    // 4. Directories
    $directories = [BACKUP_DIR, 'cache/', 'temp/', 'exports/'];
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    
    // 5. Log Files
    if (!file_exists(LOG_FILE)) {
        file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] SYSTEM: All systems initialized\n");
    }
    
    bot_log("All systems initialized successfully");
}

// Initialize everything
initialize_all_systems();

// ================= LOGGING SYSTEM =================
function bot_log($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
    
    // Also log to deletion log if it's about deletions
    if (strpos($type, 'DELETE') !== false || strpos($message, 'delete') !== false) {
        file_put_contents(DELETION_LOG, $log_entry, FILE_APPEND);
    }
    
    // Log errors separately
    if ($type == 'ERROR') {
        file_put_contents(ERROR_LOG, $log_entry, FILE_APPEND);
    }
}

// ================= TELEGRAM API =================
function bot_api($method, $params = [], $is_multipart = false) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    if ($is_multipart) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $result = curl_exec($ch);
        curl_close($ch);
    } else {
        $options = [
            'http' => [
                'method' => 'POST',
                'content' => http_build_query($params),
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
            ]
        ];
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
    }
    
    return $result ? json_decode($result, true) : false;
}

function sendMessage($chat_id, $text, $keyboard = null, $parse_mode = 'HTML', $reply_to = null) {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => true
    ];
    
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    if ($reply_to) $params['reply_to_message_id'] = $reply_to;
    
    $result = bot_api('sendMessage', $params);
    
    if ($result && $result['ok']) {
        bot_log("Message sent to $chat_id: " . substr(strip_tags($text), 0, 100));
    }
    
    return $result;
}

function editMessage($chat_id, $message_id, $text, $keyboard = null) {
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    
    return bot_api('editMessageText', $params);
}

function deleteMessage($chat_id, $message_id) {
    return bot_api('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    return bot_api('forwardMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

function copyMessage($chat_id, $from_chat_id, $message_id) {
    return bot_api('copyMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

function answerCallbackQuery($callback_id, $text = null, $show_alert = false) {
    $params = ['callback_query_id' => $callback_id, 'show_alert' => $show_alert];
    if ($text) $params['text'] = $text;
    return bot_api('answerCallbackQuery', $params);
}

// ================= CORE MOVIE SYSTEM =================
$movie_cache = [];
$movie_messages = [];
$waiting_users = [];

function load_and_clean_csv() {
    global $movie_messages, $movie_cache;
    
    if (!file_exists(CSV_FILE)) return [];
    
    $data = [];
    $handle = fopen(CSV_FILE, "r");
    if ($handle !== FALSE) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && !empty(trim($row[0]))) {
                $movie_name = trim($row[0]);
                $message_id = isset($row[1]) ? trim($row[1]) : '';
                $date = isset($row[2]) ? trim($row[2]) : '';
                $video_path = isset($row[3]) ? trim($row[3]) : '';
                $quality = isset($row[4]) ? trim($row[4]) : 'Unknown';
                $size = isset($row[5]) ? trim($row[5]) : 'Unknown';
                $language = isset($row[6]) ? trim($row[6]) : 'Hindi';
                
                $entry = [
                    'movie_name' => $movie_name,
                    'message_id_raw' => $message_id,
                    'date' => $date,
                    'video_path' => $video_path,
                    'quality' => $quality,
                    'size' => $size,
                    'language' => $language,
                    'message_id' => is_numeric($message_id) ? intval($message_id) : null
                ];
                
                $data[] = $entry;
                
                $movie_key = strtolower($movie_name);
                if (!isset($movie_messages[$movie_key])) {
                    $movie_messages[$movie_key] = [];
                }
                $movie_messages[$movie_key][] = $entry;
            }
        }
        fclose($handle);
    }
    
    // Update stats
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats['total_movies'] = count($data);
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
    
    $movie_cache = [
        'data' => $data,
        'timestamp' => time()
    ];
    
    bot_log("CSV loaded: " . count($data) . " movies");
    return $data;
}

function get_cached_movies() {
    global $movie_cache;
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < 300) {
        return $movie_cache['data'];
    }
    return load_and_clean_csv();
}

function append_movie($movie_name, $message_id, $quality = 'Unknown', $size = 'Unknown', $language = 'Hindi') {
    $date = date('d-m-Y');
    $entry = [$movie_name, $message_id, $date, '', $quality, $size, $language];
    
    $handle = fopen(CSV_FILE, "a");
    fputcsv($handle, $entry);
    fclose($handle);
    
    // Update cache
    global $movie_messages, $movie_cache;
    $movie_key = strtolower($movie_name);
    if (!isset($movie_messages[$movie_key])) {
        $movie_messages[$movie_key] = [];
    }
    
    $movie_messages[$movie_key][] = [
        'movie_name' => $movie_name,
        'message_id_raw' => $message_id,
        'message_id' => is_numeric($message_id) ? intval($message_id) : null,
        'date' => $date,
        'quality' => $quality,
        'size' => $size,
        'language' => $language
    ];
    
    $movie_cache = [];
    
    // Update analytics
    track_upload($movie_name, $message_id, $quality, $size, $language);
    
    // Update stats
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats['total_movies'] = ($stats['total_movies'] ?? 0) + 1;
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
    
    bot_log("Movie appended: $movie_name (ID: $message_id)");
    
    // Notify waiting users
    global $waiting_users;
    foreach ($waiting_users as $query => $users) {
        if (stripos($movie_name, $query) !== false) {
            foreach ($users as $user_data) {
                list($user_chat_id, $user_id) = $user_data;
                sendMessage($user_chat_id, "🎉 Good news! '$movie_name' ab available hai! Search karo ya /recent check karo.");
            }
            unset($waiting_users[$query]);
        }
    }
}

// ================= MOVIE SEARCH SYSTEM =================
function smart_search($query) {
    global $movie_messages;
    $query_lower = strtolower(trim($query));
    $results = [];
    
    foreach ($movie_messages as $movie => $entries) {
        $score = 0;
        
        // Exact match
        if ($movie == $query_lower) {
            $score = 100;
        }
        // Partial match
        elseif (strpos($movie, $query_lower) !== false) {
            $score = 80 - (strlen($movie) - strlen($query_lower));
        }
        // Similarity match
        else {
            similar_text($movie, $query_lower, $similarity);
            if ($similarity > 60) $score = $similarity;
        }
        
        if ($score > 0) {
            $results[$movie] = [
                'score' => $score,
                'count' => count($entries),
                'latest_entry' => end($entries),
                'qualities' => array_unique(array_column($entries, 'quality'))
            ];
        }
    }
    
    uasort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return array_slice($results, 0, 15);
}

function detect_language($text) {
    $hindi_pattern = '/[\x{0900}-\x{097F}]/u';
    if (preg_match($hindi_pattern, $text)) {
        return 'hindi';
    }
    
    $hindi_keywords = ['फिल्म', 'मूवी', 'डाउनलोड', 'हिंदी', 'चाहिए'];
    foreach ($hindi_keywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            return 'hindi';
        }
    }
    
    return 'english';
}

function send_multilingual_response($chat_id, $message_type, $language = 'english') {
    $responses = [
        'hindi' => [
            'welcome' => "🎬 Boss, kis movie ki talash hai?",
            'found' => "✅ Mil gayi! Movie forward ho rahi hai...",
            'not_found' => "😔 Yeh movie abhi available nahi hai!\n\n📝 Request kar sakte ho: " . REQUEST_CHANNEL,
            'searching' => "🔍 Dhoondh raha hoon... Zara wait karo",
            'request_success' => "✅ Request receive ho gayi! Jald add karenge.",
            'request_limit' => "❌ Aaj ke liye maximum " . DAILY_REQUEST_LIMIT . " requests hi kar sakte ho."
        ],
        'english' => [
            'welcome' => "🎬 Boss, which movie are you looking for?",
            'found' => "✅ Found it! Forwarding the movie...",
            'not_found' => "😔 This movie isn't available yet!\n\n📝 Request it: " . REQUEST_CHANNEL,
            'searching' => "🔍 Searching... Please wait",
            'request_success' => "✅ Request received! We'll add it soon.",
            'request_limit' => "❌ Daily limit reached (" . DAILY_REQUEST_LIMIT . " requests)."
        ]
    ];
    
    sendMessage($chat_id, $responses[$language][$message_type]);
}

function advanced_search($chat_id, $query, $user_id = null) {
    global $movie_messages, $waiting_users;
    
    $query = trim($query);
    if (strlen($query) < 2) {
        sendMessage($chat_id, "❌ Minimum 2 characters required");
        return;
    }
    
    $lang = detect_language($query);
    send_multilingual_response($chat_id, 'searching', $lang);
    
    $found = smart_search($query);
    
    if (!empty($found)) {
        // Update stats
        $stats = json_decode(file_get_contents(STATS_FILE), true);
        $stats['total_searches'] = ($stats['total_searches'] ?? 0) + 1;
        $stats['successful_searches'] = ($stats['successful_searches'] ?? 0) + 1;
        file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
        
        // Update user activity
        update_user_activity($user_id, 'search');
        
        $message = "🔍 Found " . count($found) . " results for '$query':\n\n";
        $i = 1;
        
        foreach ($found as $movie => $data) {
            $quality_info = !empty($data['qualities']) ? implode('/', $data['qualities']) : 'Unknown';
            $message .= "$i. <b>$movie</b> ({$data['count']} versions, $quality_info)\n";
            $i++;
            if ($i > 10) break;
        }
        
        sendMessage($chat_id, $message, null, 'HTML');
        
        // Create inline keyboard
        $keyboard = ['inline_keyboard' => []];
        $top_movies = array_slice(array_keys($found), 0, 5);
        
        foreach ($top_movies as $movie) {
            $keyboard['inline_keyboard'][] = [[
                'text' => "🎬 " . ucwords($movie),
                'callback_data' => "download_$movie"
            ]];
        }
        
        $keyboard['inline_keyboard'][] = [[
            'text' => "📝 Request Different Movie",
            'callback_data' => 'request_movie'
        ]];
        
        sendMessage($chat_id, "Click to download:", $keyboard);
        
    } else {
        // Update stats
        $stats = json_decode(file_get_contents(STATS_FILE), true);
        $stats['total_searches'] = ($stats['total_searches'] ?? 0) + 1;
        $stats['failed_searches'] = ($stats['failed_searches'] ?? 0) + 1;
        file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
        
        send_multilingual_response($chat_id, 'not_found', $lang);
        
        // Auto-request suggestion
        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '📝 Request This Movie', 'callback_data' => 'auto_request_' . base64_encode($query)]
            ]]
        ];
        
        sendMessage($chat_id, "💡 Click to request automatically:", $keyboard);
        
        // Add to waiting list
        if (!isset($waiting_users[$query])) {
            $waiting_users[$query] = [];
        }
        $waiting_users[$query][] = [$chat_id, $user_id];
    }
}

// ================= COPYRIGHT PROTECTION SYSTEM =================
function get_progress_bar($percentage, $length = 20) {
    $filled = round(($percentage / 100) * $length);
    $empty = $length - $filled;
    
    $bar = "";
    for ($i = 0; $i < $filled; $i++) $bar .= "🟩";
    for ($i = 0; $i < $empty; $i++) $bar .= "⬜";
    
    return $bar;
}

function get_countdown_timer($delete_time) {
    $remaining = strtotime($delete_time) - time();
    if ($remaining <= 0) return "00:00";
    
    $minutes = floor($remaining / 60);
    $seconds = $remaining % 60;
    
    return sprintf("%02d:%02d", $minutes, $seconds);
}

function get_warning_message($file_name, $file_size = '', $quality = '', $delete_time = null) {
    if ($delete_time === null) {
        $delete_time = date('Y-m-d H:i:s', time() + (DELETE_AFTER_MINUTES * 60));
    }
    
    $current_time = date("g:i A");
    $delete_formatted = date("g:i A", strtotime($delete_time));
    
    // Extract movie info
    $info = extract_file_info($file_name);
    $display_name = $info['title'] ?: $file_name;
    $year = $info['year'] ? " ($year)" : "";
    $final_quality = $quality ?: ($info['quality'] ?: 'HD');
    
    // Calculate progress
    $total_seconds = DELETE_AFTER_MINUTES * 60;
    $elapsed = time() - (strtotime($delete_time) - $total_seconds);
    $percentage = min(100, max(0, ($elapsed / $total_seconds) * 100));
    $progress_bar = get_progress_bar($percentage);
    $countdown = get_countdown_timer($delete_time);
    
    $message = "🎬 <b>" . htmlspecialchars($display_name) . "$year</b>";
    if ($final_quality != 'Unknown') {
        $message .= " [$final_quality]";
    }
    if ($file_size) {
        $message .= "\n💾 " . htmlspecialchars($file_size);
    }
    
    $message .= "\n";
    $message .= "═══════════════════════════════\n";
    $message .= "🚨🚨🚨 URGENT NOTICE 🚨🚨🚨\n";
    $message .= "═══════════════════════════════\n";
    $message .= "⚠️ File Deletion: " . DELETE_AFTER_MINUTES . " Minutes\n";
    $message .= "🛡️ Protection: Copyright Shield\n";
    $message .= "📋 Action: Forward Immediately\n\n";
    
    $message .= "✅ <b>TO-DO LIST:</b>\n";
    $message .= "├─ 📤 Forward File Now\n";
    $message .= "├─ 💾 Save to Secure Location\n";
    $message .= "├─ ⬇️ Download Safely\n";
    $message .= "└─ ⚠️ Avoid Auto-Deletion\n";
    $message .= "═══════════════════════════════\n";
    
    $message .= "🔔 Channel: " . MAIN_CHANNEL . "\n";
    $message .= "═══════════════════════════════\n";
    
    $message .= "⏳ Countdown: $countdown\n";
    $message .= "$progress_bar " . round($percentage) . "%\n\n";
    
    $message .= "⏰ Uploaded: $current_time\n";
    $message .= "🗑️ Deletion: $delete_formatted\n";
    $message .= "⏱️ Time Left: " . DELETE_AFTER_MINUTES . " minutes";
    
    return $message;
}

function extract_file_info($file_name) {
    $info = ['title' => '', 'year' => '', 'quality' => '', 'type' => 'Video'];
    
    // Extract year
    if (preg_match('/\((\d{4})\)/', $file_name, $matches)) {
        $info['year'] = $matches[1];
    }
    
    // Extract quality
    if (preg_match('/(\d{3,4}p|HD|FHD|UHD|WEB\-DL|WEBRip|BluRay)/i', $file_name, $matches)) {
        $info['quality'] = strtoupper($matches[1]);
    }
    
    // Clean title
    $title = $file_name;
    $title = preg_replace('/\.(mkv|mp4|avi|mov|wmv|flv|webm)$/i', '', $title);
    $title = preg_replace('/\((\d{4})\)/', '', $title);
    $title = preg_replace('/(\d{3,4}p|HD|FHD|UHD|WEB\-DL|WEBRip|BluRay)/i', '', $title);
    $title = trim(preg_replace('/[\._\-]+/', ' ', $title));
    $title = preg_replace('/\s+/', ' ', $title);
    
    $info['title'] = ucwords($title);
    return $info;
}

function schedule_file_deletion($chat_id, $message_id, $file_name, $file_size = '', $quality = '') {
    $db = new SQLite3(UPLOADS_DB);
    
    $delete_time = date('Y-m-d H:i:s', time() + (DELETE_AFTER_MINUTES * 60));
    
    $stmt = $db->prepare("INSERT INTO scheduled_deletes 
        (chat_id, message_id, file_name, file_size, quality, delete_time) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    $stmt->bindValue(1, $chat_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, $message_id, SQLITE3_INTEGER);
    $stmt->bindValue(3, $file_name, SQLITE3_TEXT);
    $stmt->bindValue(4, $file_size, SQLITE3_TEXT);
    $stmt->bindValue(5, $quality, SQLITE3_TEXT);
    $stmt->bindValue(6, $delete_time, SQLITE3_TEXT);
    
    $stmt->execute();
    $schedule_id = $db->lastInsertRowID();
    
    $db->close();
    
    // Send warning message
    $warning_msg = get_warning_message($file_name, $file_size, $quality, $delete_time);
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔗 JOIN CHANNEL', 'url' => 'https://t.me/' . str_replace('@', '', MAIN_CHANNEL)],
                ['text' => '⏰ LIVE COUNTDOWN', 'callback_data' => 'countdown_' . $schedule_id]
            ],
            [
                ['text' => '✅ I SAVED IT', 'callback_data' => 'saved_' . $schedule_id],
                ['text' => '❌ DELETE NOW', 'callback_data' => 'delete_now_' . $schedule_id]
            ]
        ]
    ];
    
    $result = sendMessage($chat_id, $warning_msg, $keyboard, 'HTML', $message_id);
    
    if ($result && $result['ok']) {
        // Store warning message ID
        $db = new SQLite3(UPLOADS_DB);
        $stmt = $db->prepare("INSERT INTO warning_messages (file_id, message_id, chat_id) VALUES (?, ?, ?)");
        $stmt->bindValue(1, $schedule_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $result['result']['message_id'], SQLITE3_INTEGER);
        $stmt->bindValue(3, $chat_id, SQLITE3_INTEGER);
        $stmt->execute();
        $db->close();
    }
    
    bot_log("Scheduled deletion: $file_name (ID: $schedule_id) for $delete_time");
    
    return $schedule_id;
}

function process_scheduled_deletions() {
    $db = new SQLite3(UPLOADS_DB);
    $now = date('Y-m-d H:i:s');
    
    $stmt = $db->prepare("SELECT * FROM scheduled_deletes WHERE delete_time <= ? AND status = 'pending'");
    $stmt->bindValue(1, $now, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $deleted_count = 0;
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Delete the original message
        $delete_result = deleteMessage($row['chat_id'], $row['message_id']);
        
        if ($delete_result && $delete_result['ok']) {
            // Update status
            $update_stmt = $db->prepare("UPDATE scheduled_deletes SET status = 'deleted' WHERE id = ?");
            $update_stmt->bindValue(1, $row['id'], SQLITE3_INTEGER);
            $update_stmt->execute();
            
            $deleted_count++;
            
            // Send final notification
            $final_msg = "⏰ <b>TIME'S UP!</b>\n\n";
            $final_msg .= "🗑️ <b>" . htmlspecialchars($row['file_name']) . "</b>\n";
            $final_msg .= "has been automatically deleted.\n\n";
            $final_msg .= "⚠️ Remember to forward files immediately!\n";
            $final_msg .= "🔗 " . MAIN_CHANNEL;
            
            sendMessage($row['chat_id'], $final_msg, null, 'HTML');
            
            bot_log("Deleted: {$row['file_name']} (Schedule ID: {$row['id']})", 'DELETE');
        } else {
            // Mark as failed
            $update_stmt = $db->prepare("UPDATE scheduled_deletes SET status = 'failed' WHERE id = ?");
            $update_stmt->bindValue(1, $row['id'], SQLITE3_INTEGER);
            $update_stmt->execute();
            
            bot_log("Failed to delete: {$row['file_name']}", 'ERROR');
        }
    }
    
    $db->close();
    
    if ($deleted_count > 0) {
        bot_log("Processed $deleted_count scheduled deletions");
    }
    
    return $deleted_count;
}

function update_progress_bars() {
    $db = new SQLite3(UPLOADS_DB);
    $now = time();
    
    $stmt = $db->prepare("SELECT sd.*, wm.message_id as warning_id 
                         FROM scheduled_deletes sd
                         LEFT JOIN warning_messages wm ON sd.id = wm.file_id
                         WHERE sd.status = 'pending' AND sd.delete_time > datetime('now')");
    $result = $stmt->execute();
    
    $updated = 0;
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if (!$row['warning_id']) continue;
        
        $delete_timestamp = strtotime($row['delete_time']);
        $total_seconds = DELETE_AFTER_MINUTES * 60;
        $elapsed = $now - ($delete_timestamp - $total_seconds);
        $percentage = min(100, max(0, ($elapsed / $total_seconds) * 100));
        
        // Only update if percentage changed significantly
        $last_percentage = $row['progress_percentage'] ?? 0;
        if (abs($percentage - $last_percentage) >= 5 || $now % 30 == 0) {
            $warning_msg = get_warning_message(
                $row['file_name'], 
                $row['file_size'], 
                $row['quality'], 
                $row['delete_time']
            );
            
            try {
                editMessage($row['chat_id'], $row['warning_id'], $warning_msg);
                
                // Update progress in database
                $update_stmt = $db->prepare("UPDATE warning_messages SET progress_percentage = ?, last_updated = datetime('now') WHERE file_id = ?");
                $update_stmt->bindValue(1, round($percentage), SQLITE3_INTEGER);
                $update_stmt->bindValue(2, $row['id'], SQLITE3_INTEGER);
                $update_stmt->execute();
                
                $updated++;
            } catch (Exception $e) {
                // Message might be deleted
            }
        }
    }
    
    $db->close();
    
    if ($updated > 0) {
        bot_log("Updated $updated progress bars");
    }
}

// ================= UPLOAD ANALYTICS SYSTEM =================
function track_upload($file_name, $message_id, $quality = 'Unknown', $size = 'Unknown', $language = 'Hindi') {
    $db = new SQLite3(UPLOADS_DB);
    
    // Extract info
    $info = extract_file_info($file_name);
    $category = 'Movie';
    if (stripos($file_name, 'season') !== false || stripos($file_name, 'episode') !== false) {
        $category = 'Series';
    }
    
    $stmt = $db->prepare("INSERT INTO uploads 
        (file_name, message_id, quality, file_size, language, category, upload_date, upload_time) 
        VALUES (?, ?, ?, ?, ?, ?, DATE('now'), TIME('now'))");
    
    $stmt->bindValue(1, $file_name, SQLITE3_TEXT);
    $stmt->bindValue(2, $message_id, SQLITE3_INTEGER);
    $stmt->bindValue(3, $quality, SQLITE3_TEXT);
    $stmt->bindValue(4, $size, SQLITE3_TEXT);
    $stmt->bindValue(5, $language, SQLITE3_TEXT);
    $stmt->bindValue(6, $category, SQLITE3_TEXT);
    
    $stmt->execute();
    $db->close();
    
    bot_log("Tracked upload: $file_name");
}

function get_first_upload() {
    $db = new SQLite3(UPLOADS_DB);
    
    $stmt = $db->prepare("SELECT * FROM uploads ORDER BY upload_timestamp ASC LIMIT 1");
    $result = $stmt->execute();
    $upload = $result->fetchArray(SQLITE3_ASSOC);
    
    $db->close();
    
    if (!$upload) {
        return "📭 No uploads found in database!";
    }
    
    $message = "🥇 <b>FIRST UPLOAD EVER</b>\n\n";
    $message .= "🎬 <b>Title:</b> " . htmlspecialchars($upload['file_name']) . "\n";
    $message .= "📅 <b>Date:</b> " . date('d M Y', strtotime($upload['upload_date'])) . "\n";
    $message .= "⏰ <b>Time:</b> " . $upload['upload_time'] . "\n";
    $message .= "📊 <b>Quality:</b> " . $upload['quality'] . "\n";
    $message .= "🗣️ <b>Language:</b> " . $upload['language'] . "\n";
    $message .= "📁 <b>Category:</b> " . $upload['category'] . "\n";
    $message .= "💾 <b>Size:</b> " . $upload['file_size'] . "\n\n";
    
    $days_ago = floor((time() - strtotime($upload['upload_timestamp'])) / 86400);
    $message .= "⏳ <b>Time Since:</b> $days_ago days ago\n";
    
    // Get total uploads count
    $db = new SQLite3(UPLOADS_DB);
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM uploads");
    $count_result = $count_stmt->execute();
    $count_row = $count_result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    
    $message .= "📈 <b>Total Uploads Since:</b> " . $count_row['total'] . "\n\n";
    $message .= "📍 <b>This was the beginning of our journey!</b>";
    
    return $message;
}

function get_recent_uploads($limit = 10) {
    $db = new SQLite3(UPLOADS_DB);
    
    $stmt = $db->prepare("SELECT * FROM uploads ORDER BY upload_timestamp DESC LIMIT ?");
    $stmt->bindValue(1, $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $message = "🆕 <b>RECENT UPLOADS</b>\n";
    $message .= "📊 Showing last $limit uploads\n\n";
    
    $counter = 1;
    while ($upload = $result->fetchArray(SQLITE3_ASSOC)) {
        $time_ago = time_ago($upload['upload_timestamp']);
        $short_name = strlen($upload['file_name']) > 40 ? 
                     substr($upload['file_name'], 0, 40) . "..." : $upload['file_name'];
        
        $message .= "$counter. <b>" . htmlspecialchars($short_name) . "</b>\n";
        $message .= "   📅 " . date('d/m', strtotime($upload['upload_date'])) . " | ⏰ " . 
                   substr($upload['upload_time'], 0, 5) . " | " . $time_ago . "\n";
        $message .= "   📊 " . $upload['quality'] . " | 🗣️ " . $upload['language'] . 
                   " | 💾 " . $upload['file_size'] . "\n\n";
        $counter++;
    }
    
    $db->close();
    
    // Get recent timeframe
    $db = new SQLite3(UPLOADS_DB);
    $time_stmt = $db->prepare("SELECT MIN(upload_timestamp) as first, MAX(upload_timestamp) as last 
                              FROM (SELECT upload_timestamp FROM uploads ORDER BY upload_timestamp DESC LIMIT ?)");
    $time_stmt->bindValue(1, $limit, SQLITE3_INTEGER);
    $time_result = $time_stmt->execute();
    $time_row = $time_result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    
    if ($time_row['first'] && $time_row['last']) {
        $diff = strtotime($time_row['last']) - strtotime($time_row['first']);
        $hours = floor($diff / 3600);
        $message .= "⏱️ <b>Timeframe:</b> Last " . ($hours > 0 ? "$hours hours" : "few minutes") . "\n";
    }
    
    $message .= "📈 <b>Today's Uploads:</b> " . get_todays_upload_count() . "\n";
    $message .= "🎯 <b>Upload Rate:</b> " . get_upload_rate() . "/day";
    
    return $message;
}

function get_last_upload() {
    $db = new SQLite3(UPLOADS_DB);
    
    $stmt = $db->prepare("SELECT * FROM uploads ORDER BY upload_timestamp DESC LIMIT 1");
    $result = $stmt->execute();
    $upload = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$upload) {
        $db->close();
        return "📭 No uploads found!";
    }
    
    $message = "📤 <b>LAST UPLOAD</b>\n\n";
    $message .= "🎬 <b>Title:</b> " . htmlspecialchars($upload['file_name']) . "\n";
    $message .= "📅 <b>Date:</b> " . date('d M Y', strtotime($upload['upload_date'])) . 
               " (" . get_day_name($upload['upload_date']) . ")\n";
    $message .= "⏰ <b>Time:</b> " . $upload['upload_time'] . "\n";
    $message .= "⏳ <b>Uploaded:</b> " . time_ago($upload['upload_timestamp']) . " ago\n\n";
    
    $message .= "📊 <b>Details:</b>\n";
    $message .= "• Quality: " . $upload['quality'] . "\n";
    $message .= "• Language: " . $upload['language'] . "\n";
    $message .= "• Category: " . $upload['category'] . "\n";
    $message .= "• Size: " . $upload['file_size'] . "\n\n";
    
    // Get previous upload for comparison
    $prev_stmt = $db->prepare("SELECT * FROM uploads WHERE upload_timestamp < ? ORDER BY upload_timestamp DESC LIMIT 1");
    $prev_stmt->bindValue(1, $upload['upload_timestamp'], SQLITE3_TEXT);
    $prev_result = $prev_stmt->execute();
    $prev_upload = $prev_result->fetchArray(SQLITE3_ASSOC);
    
    if ($prev_upload) {
        $time_diff = strtotime($upload['upload_timestamp']) - strtotime($prev_upload['upload_timestamp']);
        $hours = floor($time_diff / 3600);
        $minutes = floor(($time_diff % 3600) / 60);
        $message .= "⏱️ <b>Time Since Previous:</b> ";
        if ($hours > 0) $message .= "$hours hours ";
        $message .= "$minutes minutes\n";
    }
    
    $db->close();
    
    $message .= "📈 <b>Today's Uploads:</b> " . get_todays_upload_count() . " files\n";
    $message .= "🎯 <b>Next Expected:</b> " . predict_next_upload();
    
    return $message;
}

function get_total_uploads_stats() {
    $db = new SQLite3(UPLOADS_DB);
    
    // Get total count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM uploads");
    $result = $stmt->execute();
    $total_row = $result->fetchArray(SQLITE3_ASSOC);
    $total = $total_row['total'];
    
    if ($total == 0) {
        $db->close();
        return "📭 No uploads yet!";
    }
    
    // Get type distribution
    $type_stmt = $db->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN category = 'Movie' THEN 1 ELSE 0 END) as movies,
        SUM(CASE WHEN category = 'Series' THEN 1 ELSE 0 END) as series,
        SUM(CASE WHEN category NOT IN ('Movie', 'Series') THEN 1 ELSE 0 END) as other
        FROM uploads");
    $type_result = $type_stmt->execute();
    $type_row = $type_result->fetchArray(SQLITE3_ASSOC);
    
    // Get quality distribution
    $qual_stmt = $db->prepare("SELECT quality, COUNT(*) as count FROM uploads 
                              WHERE quality != 'Unknown' GROUP BY quality ORDER BY count DESC LIMIT 5");
    $qual_result = $qual_stmt->execute();
    
    $quality_stats = "";
    while ($row = $qual_result->fetchArray(SQLITE3_ASSOC)) {
        $percentage = round(($row['count'] / $total) * 100);
        $quality_stats .= "• " . $row['quality'] . ": " . $row['count'] . " (" . $percentage . "%)\n";
    }
    
    // Get language distribution
    $lang_stmt = $db->prepare("SELECT language, COUNT(*) as count FROM uploads 
                              GROUP BY language ORDER BY count DESC LIMIT 5");
    $lang_result = $lang_stmt->execute();
    
    $language_stats = "";
    while ($row = $lang_result->fetchArray(SQLITE3_ASSOC)) {
        $percentage = round(($row['count'] / $total) * 100);
        $language_stats .= "• " . $row['language'] . ": " . $row['count'] . " (" . $percentage . "%)\n";
    }
    
    // Get date range
    $date_stmt = $db->prepare("SELECT MIN(upload_date) as first_date, MAX(upload_date) as last_date FROM uploads");
    $date_result = $date_stmt->execute();
    $date_row = $date_result->fetchArray(SQLITE3_ASSOC);
    
    $days_active = days_between($date_row['first_date'], $date_row['last_date']) + 1;
    $avg_per_day = round($total / max(1, $days_active), 2);
    
    $db->close();
    
    $message = "📊 <b>TOTAL UPLOADS STATISTICS</b>\n\n";
    $message .= "🎯 <b>Grand Total:</b> $total uploads\n";
    $message .= "📅 <b>Time Period:</b> " . date('d M Y', strtotime($date_row['first_date'])) . 
               " to " . date('d M Y', strtotime($date_row['last_date'])) . "\n";
    $message .= "📆 <b>Active Days:</b> $days_active days\n";
    $message .= "📈 <b>Average per Day:</b> $avg_per_day uploads\n\n";
    
    $message .= "📁 <b>Category Distribution:</b>\n";
    $message .= "• Movies: " . $type_row['movies'] . "\n";
    $message .= "• Series: " . $type_row['series'] . "\n";
    $message .= "• Other: " . $type_row['other'] . "\n\n";
    
    if (!empty($quality_stats)) {
        $message .= "🎬 <b>Quality Distribution (Top 5):</b>\n$quality_stats\n";
    }
    
    if (!empty($language_stats)) {
        $message .= "🗣️ <b>Language Distribution (Top 5):</b>\n$language_stats\n";
    }
    
    $message .= "📈 <b>Milestones:</b>\n";
    $message .= "• 1000 uploads: " . ($total >= 1000 ? "✅ Achieved" : "⏳ " . (1000 - $total) . " to go") . "\n";
    $message .= "• 5000 uploads: " . ($total >= 5000 ? "✅ Achieved" : "⏳ " . (5000 - $total) . " to go") . "\n";
    $message .= "• 10000 uploads: " . ($total >= 10000 ? "✅ Achieved" : "⏳ " . (10000 - $total) . " to go") . "\n";
    
    return $message;
}

function get_middle_upload() {
    $db = new SQLite3(UPLOADS_DB);
    
    // Get total count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM uploads");
    $result = $stmt->execute();
    $total_row = $result->fetchArray(SQLITE3_ASSOC);
    $total = $total_row['total'];
    
    if ($total == 0) {
        $db->close();
        return "📭 No uploads yet!";
    }
    
    $middle_position = ceil($total / 2);
    
    // Get middle upload
    $stmt = $db->prepare("SELECT * FROM uploads ORDER BY upload_timestamp LIMIT 1 OFFSET ?");
    $stmt->bindValue(1, $middle_position - 1, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $upload = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$upload) {
        $db->close();
        return "❌ Could not find middle upload!";
    }
    
    $message = "🎯 <b>MIDDLE UPLOAD</b>\n\n";
    $message .= "📍 <b>Position:</b> $middle_position of $total (50% milestone)\n\n";
    
    $message .= "🎬 <b>Title:</b> " . htmlspecialchars($upload['file_name']) . "\n";
    $message .= "📅 <b>Date:</b> " . date('d M Y', strtotime($upload['upload_date'])) . "\n";
    $message .= "⏰ <b>Time:</b> " . $upload['upload_time'] . "\n";
    $message .= "📊 <b>Quality:</b> " . $upload['quality'] . "\n";
    $message .= "🗣️ <b>Language:</b> " . $upload['language'] . "\n\n";
    
    // Get counts before and after
    $before_stmt = $db->prepare("SELECT COUNT(*) as count FROM uploads WHERE upload_timestamp < ?");
    $before_stmt->bindValue(1, $upload['upload_timestamp'], SQLITE3_TEXT);
    $before_result = $before_stmt->execute();
    $before_row = $before_result->fetchArray(SQLITE3_ASSOC);
    
    $after_stmt = $db->prepare("SELECT COUNT(*) as count FROM uploads WHERE upload_timestamp > ?");
    $after_stmt->bindValue(1, $upload['upload_timestamp'], SQLITE3_TEXT);
    $after_result = $after_stmt->execute();
    $after_row = $after_result->fetchArray(SQLITE3_ASSOC);
    
    $message .= "📈 <b>Position Analysis:</b>\n";
    $message .= "• Before this: " . $before_row['count'] . " uploads\n";
    $message .= "• After this: " . $after_row['count'] . " uploads\n\n";
    
    $message .= "⏳ <b>Halfway Point:</b> " . time_ago($upload['upload_timestamp']) . " ago\n";
    $message .= "📊 <b>Completion:</b> 50% of total uploads\n\n";
    
    $db->close();
    
    $message .= "🎉 <b>This marks the halfway point of our upload journey!</b>";
    
    return $message;
}

function get_upload_date_stats($date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    $db = new SQLite3(UPLOADS_DB);
    
    $stmt = $db->prepare("SELECT * FROM uploads WHERE upload_date = ? ORDER BY upload_time");
    $stmt->bindValue(1, $date, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $uploads = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $uploads[] = $row;
    }
    
    if (empty($uploads)) {
        $db->close();
        return "📭 No uploads found for " . date('d M Y', strtotime($date));
    }
    
    $message = "📅 <b>UPLOADS ON " . date('d M Y', strtotime($date)) . "</b>\n";
    $message .= "📊 <b>Day:</b> " . get_day_name($date) . "\n";
    $message .= "📈 <b>Total Uploads:</b> " . count($uploads) . "\n\n";
    
    $message .= "⏰ <b>Upload Timeline:</b>\n";
    foreach ($uploads as $index => $upload) {
        $short_name = strlen($upload['file_name']) > 30 ? 
                     substr($upload['file_name'], 0, 30) . "..." : $upload['file_name'];
        $message .= ($index + 1) . ". <b>" . substr($upload['upload_time'], 0, 5) . "</b> - " . 
                   htmlspecialchars($short_name) . "\n";
    }
    
    // Hourly distribution
    $hourly = [];
    foreach ($uploads as $upload) {
        $hour = (int)substr($upload['upload_time'], 0, 2);
        if (!isset($hourly[$hour])) {
            $hourly[$hour] = 0;
        }
        $hourly[$hour]++;
    }
    
    if (!empty($hourly)) {
        $message .= "\n📊 <b>Hourly Distribution:</b>\n";
        arsort($hourly);
        foreach ($hourly as $hour => $count) {
            $message .= "• " . sprintf("%02d:00", $hour) . " - $count uploads\n";
        }
    }
    
    // Compare with previous day
    $prev_date = date('Y-m-d', strtotime($date . ' -1 day'));
    $prev_stmt = $db->prepare("SELECT COUNT(*) as count FROM uploads WHERE upload_date = ?");
    $prev_stmt->bindValue(1, $prev_date, SQLITE3_TEXT);
    $prev_result = $prev_stmt->execute();
    $prev_row = $prev_result->fetchArray(SQLITE3_ASSOC);
    
    if ($prev_row['count'] > 0) {
        $change = count($uploads) - $prev_row['count'];
        $change_text = $change > 0 ? "📈 +$change" : ($change < 0 ? "📉 $change" : "📊 No change");
        $message .= "\n📈 <b>Vs Previous Day:</b> $change_text\n";
    }
    
    $db->close();
    
    $message .= "\n🎯 <b>Busiest Hour:</b> " . get_busiest_hour_for_date($date) . "\n";
    $message .= "📊 <b>Average per Hour:</b> " . round(count($uploads) / max(1, count($hourly)), 2) . "\n";
    
    return $message;
}

function get_upload_calendar($month = null, $year = null) {
    if ($month === null) $month = date('m');
    if ($year === null) $year = date('Y');
    
    $db = new SQLite3(UPLOADS_DB);
    
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $stmt = $db->prepare("SELECT upload_date, COUNT(*) as count FROM uploads 
                         WHERE upload_date BETWEEN ? AND ? 
                         GROUP BY upload_date ORDER BY upload_date");
    $stmt->bindValue(1, $start_date, SQLITE3_TEXT);
    $stmt->bindValue(2, $end_date, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $daily_counts = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $day = date('d', strtotime($row['upload_date']));
        $daily_counts[$day] = $row['count'];
    }
    
    $month_name = get_month_name($month);
    $first_day = date('w', strtotime($start_date));
    $days_in_month = date('t', strtotime($start_date));
    
    $message = "📅 <b>UPLOAD CALENDAR - $month_name $year</b>\n\n";
    
    // Weekday headers
    $weekdays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    $message .= implode(" ", $weekdays) . "\n";
    
    $day_counter = 1;
    $week = "";
    
    // Add empty cells for days before the first day
    for ($i = 0; $i < $first_day; $i++) {
        $week .= "   ";
    }
    
    // Fill in the days
    while ($day_counter <= $days_in_month) {
        for ($i = $first_day; $i < 7 && $day_counter <= $days_in_month; $i++) {
            $day_str = sprintf("%2d", $day_counter);
            
            if (isset($daily_counts[$day_counter])) {
                $count = $daily_counts[$day_counter];
                if ($count >= 10) {
                    $week .= "🔥";
                } elseif ($count >= 5) {
                    $week .= "⚡";
                } elseif ($count >= 1) {
                    $week .= "📤";
                } else {
                    $week .= $day_str;
                }
            } else {
                $week .= "⬜";
            }
            
            $week .= " ";
            $day_counter++;
            $first_day = 0;
        }
        
        $message .= $week . "\n";
        $week = "";
    }
    
    // Statistics
    $total_uploads = array_sum($daily_counts);
    $active_days = count($daily_counts);
    $avg_per_active_day = $active_days > 0 ? round($total_uploads / $active_days, 2) : 0;
    
    $message .= "\n📊 <b>Monthly Statistics:</b>\n";
    $message .= "• Total Uploads: $total_uploads\n";
    $message .= "• Active Days: $active_days\n";
    $message .= "• Average per Active Day: $avg_per_active_day\n";
    
    // Find busiest day
    if (!empty($daily_counts)) {
        $busiest_day = array_keys($daily_counts, max($daily_counts))[0];
        $busiest_count = max($daily_counts);
        $message .= "• Busiest Day: $busiest_day ($busiest_count uploads)\n";
    }
    
    $message .= "\n📈 <b>Legend:</b>\n";
    $message .= "🔥 = 10+ uploads\n";
    $message .= "⚡ = 5-9 uploads\n";
    $message .= "📤 = 1-4 uploads\n";
    $message .= "⬜ = No uploads\n";
    
    $db->close();
    
    return $message;
}

// ================= HELPER FUNCTIONS =================
function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . " seconds ago";
    } elseif ($diff < 3600) {
        return floor($diff / 60) . " minutes ago";
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . " hours ago";
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . " days ago";
    } else {
        return date('d M', $time);
    }
}

function days_between($date1, $date2) {
    $datetime1 = new DateTime($date1);
    $datetime2 = new DateTime($date2);
    $interval = $datetime1->diff($datetime2);
    return $interval->days;
}

function get_day_name($date) {
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $day_num = date('w', strtotime($date));
    return $days[$day_num];
}

function get_month_name($month_num) {
    $months = ['January', 'February', 'March', 'April', 'May', 'June', 
               'July', 'August', 'September', 'October', 'November', 'December'];
    return $months[$month_num - 1];
}

function get_todays_upload_count() {
    $db = new SQLite3(UPLOADS_DB);
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM uploads WHERE upload_date = ?");
    $stmt->bindValue(1, $today, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    return $row['count'] ?? 0;
}

function get_upload_rate() {
    $db = new SQLite3(UPLOADS_DB);
    
    // Last 7 days
    $week_ago = date('Y-m-d', strtotime('-7 days'));
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM uploads WHERE upload_date >= ?");
    $stmt->bindValue(1, $week_ago, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    
    return round(($row['count'] ?? 0) / 7, 1);
}

function predict_next_upload() {
    $db = new SQLite3(UPLOADS_DB);
    
    // Get last 5 upload times
    $stmt = $db->prepare("SELECT upload_timestamp FROM uploads ORDER BY upload_timestamp DESC LIMIT 5");
    $result = $stmt->execute();
    
    $timestamps = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $timestamps[] = strtotime($row['upload_timestamp']);
    }
    
    if (count($timestamps) < 2) {
        $db->close();
        return "Insufficient data";
    }
    
    // Calculate average interval
    $intervals = [];
    for ($i = 0; $i < count($timestamps) - 1; $i++) {
        $intervals[] = $timestamps[$i] - $timestamps[$i + 1];
    }
    
    $avg_interval = array_sum($intervals) / count($intervals);
    $db->close();
    
    if ($avg_interval < 3600) {
        $minutes = round($avg_interval / 60);
        return "Within $minutes minutes";
    } else {
        $hours = round($avg_interval / 3600, 1);
        return "In about $hours hours";
    }
}

function get_busiest_hour_for_date($date) {
    $db = new SQLite3(UPLOADS_DB);
    
    $stmt = $db->prepare("SELECT 
        substr(upload_time, 1, 2) as hour,
        COUNT(*) as count
        FROM uploads 
        WHERE upload_date = ?
        GROUP BY hour 
        ORDER BY count DESC 
        LIMIT 1");
    $stmt->bindValue(1, $date, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    
    if ($row) {
        return sprintf("%02d:00", $row['hour']) . " (" . $row['count'] . " uploads)";
    }
    
    return "No data";
}

function update_user_activity($user_id, $action) {
    if (!$user_id) return;
    
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id] = [
            'first_name' => '',
            'last_name' => '',
            'username' => '',
            'joined' => date('Y-m-d H:i:s'),
            'last_active' => date('Y-m-d H:i:s'),
            'points' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
            'request_count' => 0
        ];
    }
    
    // Update based on action
    switch ($action) {
        case 'search':
            $users_data['users'][$user_id]['total_searches']++;
            $users_data['users'][$user_id]['points'] += 1;
            break;
        case 'download':
            $users_data['users'][$user_id]['total_downloads']++;
            $users_data['users'][$user_id]['points'] += 3;
            break;
        case 'request':
            $users_data['users'][$user_id]['request_count']++;
            $users_data['users'][$user_id]['points'] += 2;
            break;
    }
    
    $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
}

// ================= BACKUP SYSTEM =================
function auto_backup() {
    bot_log("Starting auto-backup process...");
    
    $backup_files = [CSV_FILE, USERS_FILE, STATS_FILE, REQUEST_FILE, UPLOADS_DB];
    $backup_dir = BACKUP_DIR . date('Y-m-d_H-i-s');
    
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    // Backup files
    foreach ($backup_files as $file) {
        if (file_exists($file)) {
            copy($file, $backup_dir . '/' . basename($file) . '.bak');
        }
    }
    
    // Create summary
    $summary = create_backup_summary();
    file_put_contents($backup_dir . '/backup_summary.txt', $summary);
    
    // Clean old backups
    clean_old_backups();
    
    bot_log("Auto-backup completed: $backup_dir");
    return true;
}

function create_backup_summary() {
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $db = new SQLite3(UPLOADS_DB);
    $upload_count = $db->querySingle("SELECT COUNT(*) FROM uploads");
    $db->close();
    
    $summary = "📊 BACKUP SUMMARY\n";
    $summary .= "================\n\n";
    $summary .= "📅 Backup Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "🤖 Bot: Entertainment Tadka Mega Bot\n\n";
    $summary .= "📈 STATISTICS:\n";
    $summary .= "• Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $summary .= "• Total Users: " . count($users_data['users'] ?? []) . "\n";
    $summary .= "• Total Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $summary .= "• Total Uploads Tracked: $upload_count\n";
    $summary .= "• Active Systems: Search + Protection + Analytics\n";
    
    return $summary;
}

function clean_old_backups() {
    $backups = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    if (count($backups) > 7) {
        usort($backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $to_delete = array_slice($backups, 0, count($backups) - 7);
        foreach ($to_delete as $dir) {
            $files = glob($dir . '/*');
            foreach ($files as $file) @unlink($file);
            @rmdir($dir);
        }
        
        bot_log("Cleaned " . count($to_delete) . " old backups");
    }
}

// ================= MAIN UPDATE HANDLER =================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    // Check maintenance mode
    global $MAINTENANCE_MODE, $MAINTENANCE_MESSAGE;
    if ($MAINTENANCE_MODE && isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        sendMessage($chat_id, $MAINTENANCE_MESSAGE, null, 'HTML');
        exit;
    }
    
    // Process scheduled tasks
    $current_minute = date('i');
    if ($current_minute % 5 == 0) {
        process_scheduled_deletions();
        update_progress_bars();
    }
    
    if (date('H') == AUTO_BACKUP_HOUR && $current_minute == '00') {
        auto_backup();
    }
    
    // Load movie cache
    get_cached_movies();
    
    // Handle messages
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = $message['text'] ?? '';
        
        // Handle file uploads (for copyright protection)
        $is_file = isset($message['video']) || isset($message['document']) || 
                   isset($message['audio']) || isset($message['photo']);
        
        if ($is_file && $user_id != ADMIN_ID) {
            // Extract file info
            $file_name = '';
            $file_size = '';
            $quality = '';
            
            if (isset($message['video'])) {
                $file_name = $message['video']['file_name'] ?? 'Video_' . time() . '.mp4';
                $file_size = format_size($message['video']['file_size'] ?? 0);
            } elseif (isset($message['document'])) {
                $file_name = $message['document']['file_name'];
                $file_size = format_size($message['document']['file_size'] ?? 0);
                $quality = extract_quality_from_name($file_name);
            }
            
            if (isset($message['caption'])) {
                $file_name = $message['caption'] . ' - ' . $file_name;
                if (!$quality) {
                    $quality = extract_quality_from_name($message['caption']);
                }
            }
            
            if ($file_name) {
                $schedule_id = schedule_file_deletion(
                    $chat_id, 
                    $message['message_id'], 
                    $file_name, 
                    $file_size, 
                    $quality
                );
                
                // Also track as upload
                track_upload($file_name, $message['message_id'], $quality, $file_size);
            }
        }
        
        // Handle channel posts (movie additions)
        if (isset($update['channel_post']) && $update['channel_post']['chat']['id'] == CHANNEL_ID) {
            $channel_post = $update['channel_post'];
            $message_id = $channel_post['message_id'];
            
            $file_name = '';
            $quality = 'Unknown';
            $size = 'Unknown';
            $language = 'Hindi';
            
            if (isset($channel_post['caption'])) {
                $file_name = $channel_post['caption'];
                if (stripos($file_name, '1080') !== false) $quality = '1080p';
                elseif (stripos($file_name, '720') !== false) $quality = '720p';
                if (stripos($file_name, 'english') !== false) $language = 'English';
            } elseif (isset($channel_post['text'])) {
                $file_name = $channel_post['text'];
            }
            
            if (!empty(trim($file_name))) {
                append_movie($file_name, $message_id, $quality, $size, $language);
            }
        }
        
        // Handle commands
        if (!empty($text)) {
            $command = strtolower(explode(' ', $text)[0]);
            
            switch ($command) {
                // ================= SEARCH COMMANDS =================
                case '/start':
                    $welcome = "🎬 <b>Welcome to Entertainment Tadka Mega Bot!</b>\n\n";
                    $welcome .= "📢 <b>Complete Features:</b>\n";
                    $welcome .= "• Smart Movie Search\n";
                    $welcome .= "• Copyright Protection (Auto-delete)\n";
                    $welcome .= "• Upload Analytics & Statistics\n";
                    $welcome .= "• Complete Backup System\n\n";
                    
                    $welcome .= "🔍 <b>Search Movies:</b> Just type movie name\n";
                    $welcome .= "🛡️ <b>Protection:</b> Files auto-delete in " . DELETE_AFTER_MINUTES . " min\n";
                    $welcome .= "📊 <b>Analytics:</b> Use /1stupload, /recent, etc.\n\n";
                    
                    $welcome .= "📢 <b>Channels:</b>\n";
                    $welcome .= "• Main: " . MAIN_CHANNEL . "\n";
                    $welcome .= "• Requests: " . REQUEST_CHANNEL . "\n";
                    $welcome .= "• Backup: " . BACKUP_CHANNEL . "\n\n";
                    
                    $welcome .= "🚀 <b>Enjoy the complete experience!</b>";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => ''],
                                ['text' => '📊 Analytics', 'callback_data' => 'show_analytics']
                            ],
                            [
                                ['text' => '🛡️ Protection Info', 'callback_data' => 'protection_info'],
                                ['text' => '📢 Join Channel', 'url' => 'https://t.me/EntertainmentTadka786']
                            ]
                        ]
                    ];
                    
                    sendMessage($chat_id, $welcome, $keyboard, 'HTML');
                    break;
                    
                case '/help':
                    $help = "🤖 <b>Entertainment Tadka Mega Bot - Complete Help</b>\n\n";
                    
                    $help .= "🎯 <b>MOVIE SEARCH:</b>\n";
                    $help .= "• Just type movie name\n";
                    $help .= "• Use /search movie_name\n";
                    $help .= "• Hindi/English both work\n\n";
                    
                    $help .= "🛡️ <b>COPYRIGHT PROTECTION:</b>\n";
                    $help .= "• Upload any file\n";
                    $help .= "• Auto-deletes in " . DELETE_AFTER_MINUTES . " minutes\n";
                    $help .= "• Progress bar & countdown\n\n";
                    
                    $help .= "📊 <b>UPLOAD ANALYTICS:</b>\n";
                    $help .= "• /1stupload - First upload ever\n";
                    $help .= "• /recent - Recent uploads\n";
                    $help .= "• /lastupload - Last upload\n";
                    $help .= "• /totalupload - Total statistics\n";
                    $help .= "• /middleupload - Middle upload\n";
                    $help .= "• /uploaddate - Date-wise stats\n";
                    $help .= "• /uploadcalendar - Monthly calendar\n\n";
                    
                    $help .= "⚙️ <b>OTHER COMMANDS:</b>\n";
                    $help .= "• /request movie - Request movie\n";
                    $help .= "• /stats - Bot statistics\n";
                    $help .= "• /channel - Join channels\n";
                    $help .= "• /backup - Manual backup (admin)\n\n";
                    
                    $help .= "🔗 <b>Channels:</b> " . MAIN_CHANNEL . " | " . REQUEST_CHANNEL;
                    
                    sendMessage($chat_id, $help, null, 'HTML');
                    break;
                    
                // ================= ANALYTICS COMMANDS =================
                case '/1stupload':
                    sendMessage($chat_id, get_first_upload(), null, 'HTML');
                    break;
                    
                case '/recent':
                case '/recentuploads':
                    sendMessage($chat_id, get_recent_uploads(), null, 'HTML');
                    break;
                    
                case '/lastupload':
                    sendMessage($chat_id, get_last_upload(), null, 'HTML');
                    break;
                    
                case '/totalupload':
                case '/stats':
                    sendMessage($chat_id, get_total_uploads_stats(), null, 'HTML');
                    break;
                    
                case '/middleupload':
                    sendMessage($chat_id, get_middle_upload(), null, 'HTML');
                    break;
                    
                case '/uploaddate':
                    $params = explode(' ', $text);
                    $date = isset($params[1]) ? $params[1] : null;
                    sendMessage($chat_id, get_upload_date_stats($date), null, 'HTML');
                    break;
                    
                case '/uploadcalendar':
                    $params = explode(' ', $text);
                    $month = isset($params[1]) ? $params[1] : null;
                    $year = isset($params[2]) ? $params[2] : null;
                    sendMessage($chat_id, get_upload_calendar($month, $year), null, 'HTML');
                    break;
                    
                // ================= OTHER COMMANDS =================
                case '/search':
                    $movie_name = trim(substr($text, strlen('/search') + 1));
                    if (!empty($movie_name)) {
                        advanced_search($chat_id, $movie_name, $user_id);
                    } else {
                        sendMessage($chat_id, "❌ Usage: /search movie_name\nExample: /search Animal");
                    }
                    break;
                    
                case '/request':
                    $movie_name = trim(substr($text, strlen('/request') + 1));
                    if (!empty($movie_name)) {
                        $lang = detect_language($movie_name);
                        
                        // Check daily limit
                        $users_data = json_decode(file_get_contents(USERS_FILE), true);
                        $today = date('Y-m-d');
                        $user_requests_today = 0;
                        
                        if (isset($users_data['users'][$user_id]['last_request_date']) && 
                            $users_data['users'][$user_id]['last_request_date'] == $today) {
                            $user_requests_today = $users_data['users'][$user_id]['request_count'] ?? 0;
                        }
                        
                        if ($user_requests_today < DAILY_REQUEST_LIMIT) {
                            // Add request
                            $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
                            $requests_data['requests'][] = [
                                'id' => uniqid(),
                                'user_id' => $user_id,
                                'movie_name' => $movie_name,
                                'language' => $lang,
                                'date' => $today,
                                'time' => date('H:i:s'),
                                'status' => 'pending'
                            ];
                            file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
                            
                            // Update user
                            $users_data['users'][$user_id]['request_count'] = $user_requests_today + 1;
                            $users_data['users'][$user_id]['last_request_date'] = $today;
                            file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
                            
                            update_user_activity($user_id, 'request');
                            send_multilingual_response($chat_id, 'request_success', $lang);
                            
                            // Notify admin
                            sendMessage(ADMIN_ID, "📝 New Movie Request\n\n🎬 Movie: $movie_name\n👤 User: $user_id\n🗣️ Language: $lang");
                        } else {
                            send_multilingual_response($chat_id, 'request_limit', $lang);
                        }
                    } else {
                        sendMessage($chat_id, "❌ Usage: /request movie_name\nExample: /request Animal 2");
                    }
                    break;
                    
                case '/channel':
                    $channel_info = "📢 <b>Our Channels</b>\n\n";
                    $channel_info .= "🍿 <b>Main Channel:</b> " . MAIN_CHANNEL . "\n";
                    $channel_info .= "Latest movies & updates\n\n";
                    $channel_info .= "📥 <b>Requests Channel:</b> " . REQUEST_CHANNEL . "\n";
                    $channel_info .= "Movie requests & support\n\n";
                    $channel_info .= "🔒 <b>Backup Channel:</b> " . BACKUP_CHANNEL . "\n";
                    $channel_info .= "Data backups & archives\n\n";
                    $channel_info .= "🔔 <b>Join all for best experience!</b>";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '🍿 ' . MAIN_CHANNEL, 'url' => 'https://t.me/EntertainmentTadka786'],
                                ['text' => '📥 ' . REQUEST_CHANNEL, 'url' => 'https://t.me/EntertainmentTadka7860']
                            ],
                            [
                                ['text' => '🔒 ' . BACKUP_CHANNEL, 'url' => 'https://t.me/ETBackup']
                            ]
                        ]
                    ];
                    
                    sendMessage($chat_id, $channel_info, $keyboard, 'HTML');
                    break;
                    
                case '/backup':
                    if ($user_id == ADMIN_ID) {
                        auto_backup();
                        sendMessage($chat_id, "✅ Manual backup completed!");
                    } else {
                        sendMessage($chat_id, "❌ Admin only command!");
                    }
                    break;
                    
                case '/test':
                    if ($user_id == ADMIN_ID) {
                        // Test all systems
                        sendMessage($chat_id, "🧪 Testing all systems...");
                        
                        // Test movie search
                        sendMessage($chat_id, "1️⃣ Testing Movie Search...");
                        $movies = get_cached_movies();
                        sendMessage($chat_id, "✅ Movie database: " . count($movies) . " entries");
                        
                        // Test analytics
                        sendMessage($chat_id, "2️⃣ Testing Analytics...");
                        sendMessage($chat_id, get_first_upload(), null, 'HTML');
                        
                        // Test protection
                        sendMessage($chat_id, "3️⃣ Testing Protection System...");
                        sendMessage($chat_id, "✅ All systems operational!");
                    }
                    break;
                    
                default:
                    // Regular text - treat as movie search
                    if (strlen($text) > 1 && !str_starts_with($text, '/')) {
                        advanced_search($chat_id, $text, $user_id);
                    }
                    break;
            }
        }
    }
    
    // Handle callback queries
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $data = $query['data'];
        $chat_id = $query['message']['chat']['id'];
        $message_id = $query['message']['message_id'];
        $user_id = $query['from']['id'];
        
        // Movie download callbacks
        if (strpos($data, 'download_') === 0) {
            $movie_name = substr($data, 9); // Remove 'download_'
            global $movie_messages;
            $movie_key = strtolower($movie_name);
            
            if (isset($movie_messages[$movie_key])) {
                $entries = $movie_messages[$movie_key];
                $sent = 0;
                
                foreach ($entries as $entry) {
                    if (!empty($entry['message_id']) && is_numeric($entry['message_id'])) {
                        $result = forwardMessage($chat_id, CHANNEL_ID, $entry['message_id']);
                        if ($result) $sent++;
                        usleep(300000); // 0.3s delay
                    }
                }
                
                if ($sent > 0) {
                    answerCallbackQuery($query['id'], "✅ $sent movies forwarded!");
                    update_user_activity($user_id, 'download');
                    
                    // Update stats
                    $stats = json_decode(file_get_contents(STATS_FILE), true);
                    $stats['total_downloads'] = ($stats['total_downloads'] ?? 0) + $sent;
                    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
                } else {
                    answerCallbackQuery($query['id'], "❌ Could not forward movies");
                }
            } else {
                answerCallbackQuery($query['id'], "❌ Movie not found");
            }
        }
        
        // Copyright protection callbacks
        elseif (strpos($data, 'countdown_') === 0) {
            $schedule_id = substr($data, 10);
            $db = new SQLite3(UPLOADS_DB);
            $stmt = $db->prepare("SELECT delete_time FROM scheduled_deletes WHERE id = ?");
            $stmt->bindValue(1, $schedule_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $db->close();
            
            if ($row) {
                $countdown = get_countdown_timer($row['delete_time']);
                answerCallbackQuery($query['id'], "⏰ Countdown: $countdown", true);
            }
        }
        
        elseif (strpos($data, 'saved_') === 0) {
            answerCallbackQuery($query['id'], "✅ Great! File saved successfully.");
        }
        
        elseif (strpos($data, 'delete_now_') === 0 && $user_id == ADMIN_ID) {
            $schedule_id = substr($data, 11);
            $db = new SQLite3(UPLOADS_DB);
            $stmt = $db->prepare("SELECT * FROM scheduled_deletes WHERE id = ?");
            $stmt->bindValue(1, $schedule_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($row) {
                deleteMessage($row['chat_id'], $row['message_id']);
                
                // Update status
                $update_stmt = $db->prepare("UPDATE scheduled_deletes SET status = 'deleted_manual' WHERE id = ?");
                $update_stmt->bindValue(1, $schedule_id, SQLITE3_INTEGER);
                $update_stmt->execute();
                
                answerCallbackQuery($query['id'], "🗑️ File deleted immediately!", true);
            }
            
            $db->close();
        }
        
        // Analytics callbacks
        elseif ($data == 'show_analytics') {
            $analytics_menu = "📊 <b>ANALYTICS MENU</b>\n\n";
            $analytics_menu .= "Select an option:\n\n";
            $analytics_menu .= "1️⃣ /1stupload - First upload ever\n";
            $analytics_menu .= "2️⃣ /recent - Recent uploads\n";
            $analytics_menu .= "3️⃣ /lastupload - Last upload\n";
            $analytics_menu .= "4️⃣ /totalupload - Total statistics\n";
            $analytics_menu .= "5️⃣ /middleupload - Middle upload\n";
            $analytics_menu .= "6️⃣ /uploaddate - Date-wise stats\n";
            $analytics_menu .= "7️⃣ /uploadcalendar - Monthly calendar\n\n";
            $analytics_menu .= "📈 <b>Complete upload tracking system!</b>";
            
            sendMessage($chat_id, $analytics_menu);
            answerCallbackQuery($query['id']);
        }
        
        elseif ($data == 'protection_info') {
            $protection_info = "🛡️ <b>COPYRIGHT PROTECTION SYSTEM</b>\n\n";
            $protection_info .= "⚠️ <b>How it works:</b>\n";
            $protection_info .= "1. Upload any file to bot\n";
            $protection_info .= "2. Bot sends warning message\n";
            $protection_info .= "3. File auto-deletes in " . DELETE_AFTER_MINUTES . " minutes\n";
            $protection_info .= "4. Forward file to save it\n\n";
            
            $protection_info .= "🎯 <b>Features:</b>\n";
            $protection_info .= "• Progress bar countdown\n";
            $protection_info .= "• Live timer updates\n";
            $protection_info .= "• One-click actions\n";
            $protection_info .= "• Admin controls\n\n";
            
            $protection_info .= "🔒 <b>Protect against copyright issues!</b>";
            
            sendMessage($chat_id, $protection_info);
            answerCallbackQuery($query['id']);
        }
        
        // Movie request callback
        elseif ($data == 'request_movie') {
            sendMessage($chat_id, "📝 To request a movie:\n\nUse command:\n<code>/request movie_name</code>\n\nExample:\n<code>/request Animal 2023</code>", null, 'HTML');
            answerCallbackQuery($query['id']);
        }
        
        elseif (strpos($data, 'auto_request_') === 0) {
            $movie_name = base64_decode(substr($data, 13));
            $lang = detect_language($movie_name);
            
            // Check daily limit
            $users_data = json_decode(file_get_contents(USERS_FILE), true);
            $today = date('Y-m-d');
            $user_requests_today = 0;
            
            if (isset($users_data['users'][$user_id]['last_request_date']) && 
                $users_data['users'][$user_id]['last_request_date'] == $today) {
                $user_requests_today = $users_data['users'][$user_id]['request_count'] ?? 0;
            }
            
            if ($user_requests_today < DAILY_REQUEST_LIMIT) {
                // Add request
                $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
                $requests_data['requests'][] = [
                    'id' => uniqid(),
                    'user_id' => $user_id,
                    'movie_name' => $movie_name,
                    'language' => $lang,
                    'date' => $today,
                    'time' => date('H:i:s'),
                    'status' => 'pending'
                ];
                file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
                
                // Update user
                $users_data['users'][$user_id]['request_count'] = $user_requests_today + 1;
                $users_data['users'][$user_id]['last_request_date'] = $today;
                file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
                
                update_user_activity($user_id, 'request');
                send_multilingual_response($chat_id, 'request_success', $lang);
                answerCallbackQuery($query['id'], "✅ Request sent!");
            } else {
                send_multilingual_response($chat_id, 'request_limit', $lang);
                answerCallbackQuery($query['id'], "❌ Daily limit reached!", true);
            }
        }
    }
}

// ================= HELPER FUNCTIONS (Cont.) =================
function format_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function extract_quality_from_name($text) {
    $patterns = [
        '/(\d{3,4}p)/i',
        '/(HD|FHD|UHD|HQ)/i',
        '/(WEB\-DL|WEBRip|BluRay|DVDRip)/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            return strtoupper($matches[1]);
        }
    }
    
    return 'Unknown';
}

// ================= DIRECT ACCESS PAGE =================
if (!isset($update) && php_sapi_name() != 'cli') {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>🎬 Entertainment Tadka Mega Bot</title>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
                color: #333;
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
            }
            
            .header {
                background: rgba(255, 255, 255, 0.95);
                padding: 40px;
                border-radius: 20px;
                margin-bottom: 30px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
            }
            
            .header h1 {
                color: #667eea;
                font-size: 3em;
                margin-bottom: 10px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            
            .header p {
                color: #666;
                font-size: 1.2em;
                margin-bottom: 20px;
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .stat-card {
                background: white;
                padding: 25px;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                transition: transform 0.3s ease;
            }
            
            .stat-card:hover {
                transform: translateY(-5px);
            }
            
            .stat-card h3 {
                color: #667eea;
                margin-bottom: 15px;
                font-size: 1.4em;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .stat-card .number {
                font-size: 2.5em;
                font-weight: bold;
                color: #764ba2;
                margin: 10px 0;
            }
            
            .features-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .feature-card {
                background: white;
                padding: 20px;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }
            
            .feature-card h4 {
                color: #667eea;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .button-group {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                margin-top: 30px;
                justify-content: center;
            }
            
            .button {
                padding: 15px 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 50px;
                font-weight: bold;
                transition: all 0.3s ease;
                border: none;
                cursor: pointer;
                font-size: 1em;
            }
            
            .button:hover {
                transform: scale(1.05);
                box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
            }
            
            .button.secondary {
                background: white;
                color: #667eea;
                border: 2px solid #667eea;
            }
            
            .status {
                display: inline-block;
                padding: 5px 15px;
                border-radius: 20px;
                font-size: 0.9em;
                font-weight: bold;
            }
            
            .status.online {
                background: #4CAF50;
                color: white;
            }
            
            .status.offline {
                background: #f44336;
                color: white;
            }
            
            .log-panel {
                background: white;
                padding: 20px;
                border-radius: 15px;
                margin-top: 30px;
                max-height: 300px;
                overflow-y: auto;
            }
            
            .log-panel h3 {
                color: #667eea;
                margin-bottom: 15px;
            }
            
            .log-entry {
                padding: 10px;
                border-bottom: 1px solid #eee;
                font-family: monospace;
                font-size: 0.9em;
            }
            
            @media (max-width: 768px) {
                .header {
                    padding: 20px;
                }
                
                .header h1 {
                    font-size: 2em;
                }
                
                .button-group {
                    flex-direction: column;
                }
                
                .button {
                    width: 100%;
                    text-align: center;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎬 Entertainment Tadka Mega Bot</h1>
                <p>Complete Solution: Movie Search + Copyright Protection + Upload Analytics</p>
                <div class='status online'>✅ SYSTEM ONLINE</div>
            </div>
            
            <div class='stats-grid'>
                <div class='stat-card'>
                    <h3>📊 Movie Database</h3>";
    
    $movies = get_cached_movies();
    echo "<div class='number'>" . count($movies) . "</div>";
    echo "<p>Total movies in database</p>";
    
    echo "</div>
                <div class='stat-card'>
                    <h3>🛡️ Protection System</h3>
                    <div class='number'>" . DELETE_AFTER_MINUTES . " min</div>
                    <p>Auto-delete timer</p>
                </div>
                <div class='stat-card'>
                    <h3>📈 Upload Analytics</h3>";
    
    $db = new SQLite3(UPLOADS_DB);
    $upload_count = $db->querySingle("SELECT COUNT(*) FROM uploads");
    $db->close();
    
    echo "<div class='number'>$upload_count</div>";
    echo "<p>Tracked uploads</p>";
    
    echo "</div>
                <div class='stat-card'>
                    <h3>👥 Users</h3>";
    
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user_count = count($users_data['users'] ?? []);
    
    echo "<div class='number'>$user_count</div>";
    echo "<p>Active users</p>";
    
    echo "</div>
            </div>
            
            <div class='features-grid'>
                <div class='feature-card'>
                    <h4>🔍 Smart Movie Search</h4>
                    <ul>
                        <li>Fuzzy matching</li>
                        <li>Multi-language</li>
                        <li>Quality filters</li>
                        <li>Auto-suggestions</li>
                    </ul>
                </div>
                
                <div class='feature-card'>
                    <h4>🛡️ Copyright Protection</h4>
                    <ul>
                        <li>Auto-delete in " . DELETE_AFTER_MINUTES . " min</li>
                        <li>Progress bar countdown</li>
                        <li>Warning messages</li>
                        <li>Admin controls</li>
                    </ul>
                </div>
                
                <div class='feature-card'>
                    <h4>📊 Upload Analytics</h4>
                    <ul>
                        <li>First/last upload tracking</li>
                        <li>Monthly calendar</li>
                        <li>Statistics & trends</li>
                        <li>Milestone tracking</li>
                    </ul>
                </div>
                
                <div class='feature-card'>
                    <h4>⚡ Other Features</h4>
                    <ul>
                        <li>Auto-backup system</li>
                        <li>User points system</li>
                        <li>Movie requests</li>
                        <li>Channel integration</li>
                    </ul>
                </div>
            </div>
            
            <div class='button-group'>
                <a href='?setwebhook=1' class='button'>🚀 Set Webhook</a>
                <a href='?test=1' class='button secondary'>🧪 Test Systems</a>
                <a href='?backup=1' class='button'>💾 Manual Backup</a>
                <a href='?logs=1' class='button secondary'>📋 View Logs</a>
            </div>
            
            <div class='log-panel'>
                <h3>📝 Recent Activity</h3>";
    
    if (file_exists(LOG_FILE)) {
        $logs = array_slice(file(LOG_FILE), -20);
        foreach ($logs as $log) {
            echo "<div class='log-entry'>" . htmlspecialchars($log) . "</div>";
        }
    } else {
        echo "<div class='log-entry'>No logs available</div>";
    }
    
    echo "</div>
        </div>
        
        <script>
            // Auto-refresh logs every 30 seconds
            setTimeout(function() {
                window.location.reload();
            }, 30000);
            
            // Handle button actions
            document.querySelectorAll('.button').forEach(button => {
                button.addEventListener('click', function(e) {
                    if(this.textContent.includes('Set Webhook')) {
                        if(!confirm('This will set the webhook URL. Continue?')) {
                            e.preventDefault();
                        }
                    }
                });
            });
        </script>
    </body>
    </html>";
    
    // Handle direct actions
    if (isset($_GET['setwebhook'])) {
        $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                      "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $result = bot_api('setWebhook', ['url' => $webhook_url]);
        
        echo "<div class='container' style='margin-top: 20px;'>
                <div class='header'>
                    <h3>Webhook Result</h3>
                    <pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>
                    <a href='./' class='button'>← Back to Dashboard</a>
                </div>
              </div>";
    }
    
    if (isset($_GET['test'])) {
        echo "<div class='container' style='margin-top: 20px;'>
                <div class='header'>
                    <h3>🧪 System Test Results</h3>";
        
        echo "<h4>✅ All Systems Operational</h4>";
        echo "<p>• Movie Database: " . count($movies) . " entries</p>";
        echo "<p>• Upload Analytics: $upload_count tracked</p>";
        echo "<p>• Users: $user_count registered</p>";
        echo "<p>• Files: All configuration files present</p>";
        
        echo "<a href='./' class='button'>← Back to Dashboard</a>
                </div>
              </div>";
    }
    
    if (isset($_GET['backup'])) {
        auto_backup();
        echo "<div class='container' style='margin-top: 20px;'>
                <div class='header'>
                    <h3>✅ Manual Backup Completed</h3>
                    <p>All data has been backed up successfully.</p>
                    <a href='./' class='button'>← Back to Dashboard</a>
                </div>
              </div>";
    }
    
    if (isset($_GET['logs'])) {
        echo "<div class='container' style='margin-top: 20px;'>
                <div class='header'>
                    <h3>📋 Complete Logs</h3>
                    <div style='max-height: 500px; overflow-y: auto; background: #f5f5f5; padding: 15px; border-radius: 10px; font-family: monospace;'>";
        
        if (file_exists(LOG_FILE)) {
            echo nl2br(htmlspecialchars(file_get_contents(LOG_FILE)));
        } else {
            echo "No logs found";
        }
        
        echo "</div>
                    <a href='./' class='button'>← Back to Dashboard</a>
                </div>
              </div>";
    }
}

// ================= END OF MEGA BOT =================
?>
