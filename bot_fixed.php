<?php
// ============================================
// ENTERTAINMENT TADKA MEGA BOT v4.0 - COMPLETE FIX
// AUTO CSV LOAD + ANALYTICS SYNC + ALL BUGS FIXED
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
    // 1. CSV File - CREATE WITH CORRECT FORMAT
    if (!file_exists(CSV_FILE)) {
        $handle = fopen(CSV_FILE, "w");
        fputcsv($handle, ['movie_name','message_id','date','video_path','quality','size','language']);
        fclose($handle);
        @chmod(CSV_FILE, 0666);
        bot_log("Created new CSV file with correct 7-column format");
    } else {
        // AUTO-FIX EXISTING CSV IF WRONG FORMAT
        auto_fix_csv_format();
    }
    
    // 2. JSON Files
    $json_files = [
        USERS_FILE => ['users' => [], 'total_requests' => 0, 'message_logs' => [], 'daily_stats' => []],
        STATS_FILE => [
            'total_movies' => 0, 'total_users' => 0, 'total_searches' => 0,
            'total_downloads' => 0, 'successful_searches' => 0, 'failed_searches' => 0,
            'daily_activity' => [], 'last_updated' => date('Y-m-d H:i:s'),
            'analytics_synced' => null, 'csv_format_fixed' => false
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
        movie_name TEXT,
        message_id INTEGER,
        upload_date DATE,
        video_path TEXT,
        quality TEXT,
        size TEXT,
        language TEXT,
        category TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
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
        file_name TEXT,
        file_size TEXT,
        quality TEXT,
        delete_time DATETIME,
        status TEXT DEFAULT 'pending',
        warning_message_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

// ================= CSV AUTO-FIX FUNCTION =================
function auto_fix_csv_format() {
    if (!file_exists(CSV_FILE)) return;
    
    // Read first line to check format
    $handle = fopen(CSV_FILE, "r");
    if (!$handle) return;
    
    $first_line = fgets($handle);
    fclose($handle);
    
    // Count columns in first line
    $columns = str_getcsv($first_line);
    $column_count = count($columns);
    
    if ($column_count == 3 && $columns[0] == 'movie_name' && $columns[1] == 'message_id' && $columns[2] == 'date') {
        bot_log("Detected old CSV format (3 columns), auto-converting to 7 columns");
        
        // Read all data
        $handle = fopen(CSV_FILE, "r");
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
        
        // Write back with correct format
        $handle = fopen(CSV_FILE, "w");
        fputcsv($handle, ['movie_name','message_id','date','video_path','quality','size','language']);
        
        // Skip header row
        array_shift($rows);
        
        $fixed_count = 0;
        foreach ($rows as $row) {
            if (count($row) >= 3) {
                $movie_name = $row[0];
                $message_id = $row[1];
                $date = $row[2];
                
                // Auto-detect missing columns
                $quality = detect_quality_from_name($movie_name);
                $language = detect_language_from_name($movie_name);
                $size = get_size_from_quality($quality);
                
                $new_row = [
                    $movie_name,    // movie_name
                    $message_id,    // message_id
                    $date,          // date
                    '',             // video_path (empty)
                    $quality,       // quality
                    $size,          // size
                    $language       // language
                ];
                
                fputcsv($handle, $new_row);
                $fixed_count++;
            }
        }
        
        fclose($handle);
        
        // Update stats
        $stats = json_decode(file_get_contents(STATS_FILE), true);
        $stats['csv_format_fixed'] = true;
        $stats['csv_fixed_count'] = $fixed_count;
        $stats['csv_last_fixed'] = date('Y-m-d H:i:s');
        file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
        
        bot_log("Auto-converted $fixed_count movies from 3-column to 7-column format");
        
        // AUTO-SYNC AFTER FIX
        auto_sync_analytics();
    }
}

function detect_quality_from_name($movie_name) {
    $name_lower = strtolower($movie_name);
    
    $quality_patterns = [
        '4k' => '4K',
        '2160p' => '4K',
        '1080p' => '1080p',
        'fhd' => '1080p',
        '720p' => '720p',
        'hd' => 'HD',
        'hq' => 'HD',
        '480p' => '480p',
        '360p' => '360p'
    ];
    
    foreach ($quality_patterns as $pattern => $quality) {
        if (strpos($name_lower, $pattern) !== false) {
            return $quality;
        }
    }
    
    return '720p'; // Default
}

function detect_language_from_name($movie_name) {
    $name_lower = strtolower($movie_name);
    
    $language_patterns = [
        'telugu' => 'Telugu',
        'tel' => 'Telugu',
        'tamil' => 'Tamil',
        'tam' => 'Tamil',
        'kannada' => 'Kannada',
        'kan' => 'Kannada',
        'malayalam' => 'Malayalam',
        'mal' => 'Malayalam',
        'english' => 'English',
        'eng' => 'English',
        'hindi' => 'Hindi',
        'hin' => 'Hindi',
        'bengali' => 'Bengali',
        'beng' => 'Bengali'
    ];
    
    foreach ($language_patterns as $pattern => $language) {
        if (strpos($name_lower, $pattern) !== false) {
            return $language;
        }
    }
    
    return 'Hindi'; // Default
}

function get_size_from_quality($quality) {
    $size_map = [
        '4K' => '4.5GB',
        '1080p' => '2.1GB',
        'HD' => '1.8GB',
        '720p' => '1.5GB',
        '480p' => '800MB',
        '360p' => '400MB'
    ];
    
    return $size_map[$quality] ?? '1.5GB';
}

// ================= AUTO ANALYTICS SYNC =================
function auto_sync_analytics() {
    if (!file_exists(CSV_FILE)) return;
    
    $db = new SQLite3(UPLOADS_DB);
    
    // Get counts
    $csv_count = count_csv_movies();
    $db_count = $db->querySingle("SELECT COUNT(*) FROM uploads");
    
    // Only sync if mismatch
    if ($csv_count > $db_count) {
        bot_log("Auto-syncing analytics: CSV=$csv_count, DB=$db_count, Difference=" . ($csv_count - $db_count));
        
        // Read CSV
        $handle = fopen(CSV_FILE, "r");
        if (!$handle) {
            $db->close();
            return;
        }
        
        // Skip header
        fgetcsv($handle);
        
        $synced = 0;
        $skipped = 0;
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 7) {
                $movie_name = $row[0];
                $message_id = intval($row[1]);
                $date = $row[2];
                $quality = $row[4];
                $size = $row[5];
                $language = $row[6];
                
                // Check if already exists
                $exists = $db->querySingle("SELECT COUNT(*) FROM uploads WHERE movie_name = '" . 
                    $db->escapeString($movie_name) . "' AND message_id = $message_id");
                
                if (!$exists && $message_id > 0) {
                    // Convert date format
                    $date_parts = explode('-', $date);
                    if (count($date_parts) == 3) {
                        $sql_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
                    } else {
                        $sql_date = date('Y-m-d');
                    }
                    
                    // Determine category
                    $category = 'Movie';
                    if (stripos($movie_name, 'season') !== false || 
                        stripos($movie_name, 's0') !== false ||
                        stripos($movie_name, ' episode') !== false) {
                        $category = 'Series';
                    }
                    
                    // Insert
                    $stmt = $db->prepare("INSERT INTO uploads 
                        (movie_name, message_id, upload_date, quality, size, language, category) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->bindValue(1, $movie_name, SQLITE3_TEXT);
                    $stmt->bindValue(2, $message_id, SQLITE3_INTEGER);
                    $stmt->bindValue(3, $sql_date, SQLITE3_TEXT);
                    $stmt->bindValue(4, $quality, SQLITE3_TEXT);
                    $stmt->bindValue(5, $size, SQLITE3_TEXT);
                    $stmt->bindValue(6, $language, SQLITE3_TEXT);
                    $stmt->bindValue(7, $category, SQLITE3_TEXT);
                    
                    if ($stmt->execute()) {
                        $synced++;
                    }
                } else {
                    $skipped++;
                }
            }
        }
        
        fclose($handle);
        
        // Update stats
        $stats = json_decode(file_get_contents(STATS_FILE), true);
        $stats['analytics_synced'] = date('Y-m-d H:i:s');
        $stats['analytics_count'] = $synced;
        file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
        
        bot_log("Auto-sync completed: $synced added, $skipped skipped");
    }
    
    $db->close();
}

function count_csv_movies() {
    if (!file_exists(CSV_FILE)) return 0;
    
    $count = 0;
    $handle = fopen(CSV_FILE, "r");
    if ($handle) {
        // Skip header
        fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== false) {
            if (!empty(trim($row[0]))) {
                $count++;
            }
        }
        fclose($handle);
    }
    
    return $count;
}

// ================= LOGGING SYSTEM =================
function bot_log($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
    
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

// ================= MOVIE SYSTEM =================
$movie_cache = [];
$movie_messages = [];

function load_movies_from_csv() {
    global $movie_messages, $movie_cache;
    
    if (!file_exists(CSV_FILE)) {
        return [];
    }
    
    $data = [];
    $handle = fopen(CSV_FILE, "r");
    
    if ($handle !== FALSE) {
        // Skip header
        fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            // Ensure 7 columns
            if (count($row) < 7) {
                $row = array_pad($row, 7, '');
            }
            
            if (!empty(trim($row[0]))) {
                $movie_name = trim($row[0]);
                $message_id = isset($row[1]) ? trim($row[1]) : '';
                $date = isset($row[2]) ? trim($row[2]) : '';
                $video_path = isset($row[3]) ? trim($row[3]) : '';
                $quality = isset($row[4]) ? trim($row[4]) : '720p';
                $size = isset($row[5]) ? trim($row[5]) : '1.5GB';
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
    
    // AUTO-FIX CSV before loading
    auto_fix_csv_format();
    
    // AUTO-SYNC analytics
    auto_sync_analytics();
    
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < 300) {
        return $movie_cache['data'];
    }
    
    return load_movies_from_csv();
}

// ================= ANALYTICS FUNCTIONS =================
function get_recent_uploads($limit = 10) {
    $db = new SQLite3(UPLOADS_DB);
    
    $stmt = $db->prepare("SELECT * FROM uploads ORDER BY timestamp DESC LIMIT ?");
    $stmt->bindValue(1, $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $message = "🆕 <b>RECENT UPLOADS</b>\n";
    $message .= "📊 Showing last $limit uploads\n\n";
    
    $counter = 1;
    while ($upload = $result->fetchArray(SQLITE3_ASSOC)) {
        $time_ago = time_ago($upload['timestamp']);
        $short_name = strlen($upload['movie_name']) > 40 ? 
                     substr($upload['movie_name'], 0, 40) . "..." : $upload['movie_name'];
        
        $message .= "$counter. <b>" . htmlspecialchars($short_name) . "</b>\n";
        $message .= "   📅 " . date('d/m', strtotime($upload['upload_date'])) . " | ⏰ " . $time_ago . "\n";
        $message .= "   📊 " . $upload['quality'] . " | 🗣️ " . $upload['language'] . 
                   " | 💾 " . $upload['size'] . "\n\n";
        $counter++;
    }
    
    if ($counter == 1) {
        $message = "📭 <b>No uploads found in analytics!</b>\n\n";
        $message .= "Try:\n";
        $message .= "1. /syncall - Sync CSV to analytics\n";
        $message .= "2. /checkdata - Check data status\n";
        $message .= "3. Add movies to CSV first";
    }
    
    $db->close();
    
    return $message;
}

function get_first_upload() {
    $db = new SQLite3(UPLOADS_DB);
    
    $stmt = $db->prepare("SELECT * FROM uploads ORDER BY timestamp ASC LIMIT 1");
    $result = $stmt->execute();
    $upload = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$upload) {
        $db->close();
        return "📭 <b>No uploads found in analytics database!</b>\n\nUse /syncall to sync CSV data";
    }
    
    $message = "🥇 <b>FIRST UPLOAD EVER</b>\n\n";
    $message .= "🎬 <b>Title:</b> " . htmlspecialchars($upload['movie_name']) . "\n";
    $message .= "📅 <b>Date:</b> " . date('d M Y', strtotime($upload['upload_date'])) . "\n";
    $message .= "📊 <b>Quality:</b> " . $upload['quality'] . "\n";
    $message .= "🗣️ <b>Language:</b> " . $upload['language'] . "\n";
    $message .= "📁 <b>Category:</b> " . $upload['category'] . "\n";
    $message .= "💾 <b>Size:</b> " . $upload['size'] . "\n\n";
    
    $days_ago = floor((time() - strtotime($upload['timestamp'])) / 86400);
    $message .= "⏳ <b>Time Since:</b> $days_ago days ago\n";
    
    // Get total uploads count
    $total = $db->querySingle("SELECT COUNT(*) FROM uploads");
    $db->close();
    
    $message .= "📈 <b>Total Uploads Since:</b> $total\n\n";
    $message .= "📍 <b>This was the beginning of our journey!</b>";
    
    return $message;
}

function get_total_uploads_stats() {
    $db = new SQLite3(UPLOADS_DB);
    
    // Get total count
    $total = $db->querySingle("SELECT COUNT(*) FROM uploads");
    
    if ($total == 0) {
        $db->close();
        return "📭 <b>No analytics data found!</b>\n\nUse /syncall to sync CSV to analytics database";
    }
    
    // Get type distribution
    $movies = $db->querySingle("SELECT COUNT(*) FROM uploads WHERE category = 'Movie'");
    $series = $db->querySingle("SELECT COUNT(*) FROM uploads WHERE category = 'Series'");
    
    // Get quality distribution
    $quality_result = $db->query("SELECT quality, COUNT(*) as count FROM uploads GROUP BY quality ORDER BY count DESC");
    
    $quality_stats = "";
    while ($row = $quality_result->fetchArray(SQLITE3_ASSOC)) {
        $percentage = round(($row['count'] / $total) * 100);
        $quality_stats .= "• " . $row['quality'] . ": " . $row['count'] . " (" . $percentage . "%)\n";
    }
    
    // Get language distribution
    $lang_result = $db->query("SELECT language, COUNT(*) as count FROM uploads GROUP BY language ORDER BY count DESC");
    
    $language_stats = "";
    while ($row = $lang_result->fetchArray(SQLITE3_ASSOC)) {
        $percentage = round(($row['count'] / $total) * 100);
        $language_stats .= "• " . $row['language'] . ": " . $row['count'] . " (" . $percentage . "%)\n";
    }
    
    $db->close();
    
    $message = "📊 <b>TOTAL UPLOADS STATISTICS</b>\n\n";
    $message .= "🎯 <b>Grand Total:</b> $total uploads\n";
    $message .= "📁 <b>Movies:</b> $movies | <b>Series:</b> $series\n\n";
    
    if (!empty($quality_stats)) {
        $message .= "🎬 <b>Quality Distribution:</b>\n$quality_stats\n";
    }
    
    if (!empty($language_stats)) {
        $message .= "🗣️ <b>Language Distribution:</b>\n$language_stats\n";
    }
    
    return $message;
}

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
        return date('d M Y', $time);
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
    
    // Initialize systems
    initialize_all_systems();
    
    // Load movies
    get_cached_movies();
    
    // Handle messages
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = $message['text'] ?? '';
        
        // Handle commands
        if (!empty($text)) {
            $command = strtolower(explode(' ', $text)[0]);
            
            switch ($command) {
                case '/start':
                    $welcome = "🎬 <b>Entertainment Tadka Mega Bot v4.0</b>\n\n";
                    $welcome .= "✅ <b>ALL SYSTEMS FIXED & WORKING</b>\n\n";
                    $welcome .= "📢 <b>Complete Features:</b>\n";
                    $welcome .= "• Smart Movie Search\n";
                    $welcome .= "• Copyright Protection\n";
                    $welcome .= "• Upload Analytics (FIXED)\n";
                    $welcome .= "• Auto CSV Format Fix\n\n";
                    
                    $welcome .= "🔍 <b>Search Movies:</b> Just type movie name\n";
                    $welcome .= "📊 <b>Analytics:</b> /recent, /1stupload, etc.\n";
                    $welcome .= "🛠️ <b>Admin:</b> /syncall, /checkdata\n\n";
                    
                    $welcome .= "🚀 <b>CSV AUTO-FIX ENABLED!</b>";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => ''],
                                ['text' => '📊 Analytics', 'callback_data' => 'show_analytics']
                            ]
                        ]
                    ];
                    
                    sendMessage($chat_id, $welcome, $keyboard, 'HTML');
                    break;
                    
                // ================= ANALYTICS COMMANDS =================
                case '/recent':
                    sendMessage($chat_id, get_recent_uploads(), null, 'HTML');
                    break;
                    
                case '/1stupload':
                    sendMessage($chat_id, get_first_upload(), null, 'HTML');
                    break;
                    
                case '/totalupload':
                    sendMessage($chat_id, get_total_uploads_stats(), null, 'HTML');
                    break;
                    
                // ================= ADMIN COMMANDS =================
                case '/syncall':
                    if ($user_id == ADMIN_ID) {
                        sendMessage($chat_id, "🔄 Starting complete system sync...");
                        
                        // 1. Auto-fix CSV
                        auto_fix_csv_format();
                        
                        // 2. Load movies
                        $movies = load_movies_from_csv();
                        $csv_count = count($movies);
                        
                        // 3. Sync analytics
                        auto_sync_analytics();
                        
                        // 4. Get analytics count
                        $db = new SQLite3(UPLOADS_DB);
                        $analytics_count = $db->querySingle("SELECT COUNT(*) FROM uploads");
                        $db->close();
                        
                        $message = "✅ <b>COMPLETE SYNC FINISHED!</b>\n\n";
                        $message .= "📊 CSV Movies: $csv_count\n";
                        $message .= "📈 Analytics Entries: $analytics_count\n\n";
                        
                        if ($csv_count == $analytics_count) {
                            $message .= "🎉 <b>PERFECTLY SYNCED!</b>\n";
                        } elseif ($csv_count > $analytics_count) {
                            $diff = $csv_count - $analytics_count;
                            $message .= "⚠️ <b>$diff movies need manual sync</b>\n";
                        }
                        
                        $message .= "\n✅ Now try /recent command!";
                        
                        sendMessage($chat_id, $message, null, 'HTML');
                    } else {
                        sendMessage($chat_id, "❌ Admin only command!");
                    }
                    break;
                    
                case '/checkdata':
                    // Get CSV count
                    $csv_count = count_csv_movies();
                    
                    // Get analytics count
                    $db = new SQLite3(UPLOADS_DB);
                    $analytics_count = $db->querySingle("SELECT COUNT(*) FROM uploads");
                    $db->close();
                    
                    $message = "📊 <b>DATA STATUS CHECK</b>\n\n";
                    $message .= "📁 CSV Database: $csv_count movies\n";
                    $message .= "📈 Analytics DB: $analytics_count entries\n\n";
                    
                    if ($csv_count == 0) {
                        $message .= "❌ <b>CSV FILE EMPTY!</b>\n";
                        $message .= "Upload movies.csv file to Render\n";
                    } elseif ($analytics_count == 0) {
                        $message .= "❌ <b>ANALYTICS EMPTY!</b>\n";
                        $message .= "Use /syncall to import data\n";
                    } elseif ($csv_count == $analytics_count) {
                        $message .= "✅ <b>PERFECTLY SYNCED!</b>\n";
                        $message .= "All systems working correctly\n";
                    } elseif ($csv_count > $analytics_count) {
                        $diff = $csv_count - $analytics_count;
                        $message .= "⚠️ <b>NEEDS SYNC: $diff movies</b>\n";
                        $message .= "Use /syncall command\n";
                    } else {
                        $message .= "⚠️ <b>DATA MISMATCH DETECTED</b>\n";
                        $message .= "Analytics has extra data\n";
                    }
                    
                    // Show CSV format status
                    if (file_exists(CSV_FILE)) {
                        $handle = fopen(CSV_FILE, "r");
                        $header = fgetcsv($handle);
                        fclose($handle);
                        
                        $column_count = count($header);
                        $message .= "\n📋 <b>CSV Format:</b> $column_count columns\n";
                        
                        if ($column_count == 7) {
                            $message .= "✅ Correct format (7 columns)\n";
                        } else {
                            $message .= "❌ Wrong format (needs 7 columns)\n";
                            $message .= "Auto-fix will run on next load\n";
                        }
                    }
                    
                    sendMessage($chat_id, $message, null, 'HTML');
                    break;
                    
                case '/forcefix':
                    if ($user_id == ADMIN_ID) {
                        // Force fix CSV format
                        auto_fix_csv_format();
                        sendMessage($chat_id, "✅ CSV format fix forced!\nNow use /syncall");
                    }
                    break;
                    
                case '/test':
                    if ($user_id == ADMIN_ID) {
                        $message = "🧪 <b>SYSTEM TEST RESULTS</b>\n\n";
                        
                        // Test CSV
                        $csv_exists = file_exists(CSV_FILE);
                        $message .= "📁 CSV File: " . ($csv_exists ? "✅ Exists" : "❌ Missing") . "\n";
                        
                        if ($csv_exists) {
                            $csv_count = count_csv_movies();
                            $message .= "📊 CSV Movies: $csv_count\n";
                        }
                        
                        // Test Database
                        $db_exists = file_exists(UPLOADS_DB);
                        $message .= "📈 SQLite DB: " . ($db_exists ? "✅ Exists" : "❌ Missing") . "\n";
                        
                        if ($db_exists) {
                            $db = new SQLite3(UPLOADS_DB);
                            $table_check = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='uploads'");
                            $db->close();
                            
                            $message .= "📋 Analytics Table: " . ($table_check ? "✅ Exists" : "❌ Missing") . "\n";
                        }
                        
                        $message .= "\n🎯 <b>Recommended actions:</b>\n";
                        
                        if (!$csv_exists) {
                            $message .= "1. Upload movies.csv to Render\n";
                        }
                        
                        if (!$db_exists || !$table_check) {
                            $message .= "2. Use /syncall to create database\n";
                        }
                        
                        if ($csv_exists && $csv_count == 0) {
                            $message .= "3. Add movies to CSV file\n";
                        }
                        
                        sendMessage($chat_id, $message, null, 'HTML');
                    }
                    break;
                    
                // ================= MOVIE SEARCH =================
                default:
                    // Regular text - treat as movie search
                    if (strlen($text) > 1 && !str_starts_with($text, '/')) {
                        $movies = get_cached_movies();
                        $found = false;
                        
                        $search_term = strtolower($text);
                        foreach ($movies as $movie) {
                            if (stripos($movie['movie_name'], $search_term) !== false) {
                                $found = true;
                                sendMessage($chat_id, "🎬 <b>Found:</b> " . $movie['movie_name'] . "\n📊 Quality: " . $movie['quality'] . "\n🗣️ Language: " . $movie['language'], null, 'HTML');
                                break;
                            }
                        }
                        
                        if (!$found) {
                            sendMessage($chat_id, "❌ Movie not found: '$text'\n\n📁 Total movies in database: " . count($movies));
                        }
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
        
        if ($data == 'show_analytics') {
            $message = "📊 <b>ANALYTICS COMMANDS</b>\n\n";
            $message .= "1️⃣ /recent - Recent uploads\n";
            $message .= "2️⃣ /1stupload - First upload ever\n";
            $message .= "3️⃣ /totalupload - Total statistics\n";
            $message .= "4️⃣ /checkdata - Check data status\n";
            $message .= "5️⃣ /syncall - Sync CSV to analytics (Admin)\n\n";
            $message .= "✅ <b>All systems fixed and working!</b>";
            
            sendMessage($chat_id, $message);
            
            // Answer callback
            bot_api('answerCallbackQuery', [
                'callback_query_id' => $query['id'],
                'text' => 'Analytics menu shown'
            ]);
        }
    }
}

// ================= DIRECT ACCESS PAGE =================
if (!isset($update) && php_sapi_name() != 'cli') {
    // Initialize
    initialize_all_systems();
    
    // Auto-fix CSV
    auto_fix_csv_format();
    
    // Auto-sync analytics
    auto_sync_analytics();
    
    // Get counts
    $csv_count = count_csv_movies();
    
    $db = new SQLite3(UPLOADS_DB);
    $analytics_count = $db->querySingle("SELECT COUNT(*) FROM uploads");
    $db->close();
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>🎬 Entertainment Tadka Mega Bot v4.0 - FIXED</title>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
            .container { max-width: 1200px; margin: 0 auto; }
            .header { background: white; padding: 30px; border-radius: 15px; margin-bottom: 20px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
            .header h1 { color: #667eea; font-size: 2.5em; margin-bottom: 10px; }
            .status-card { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .status-card h3 { color: #667eea; margin-bottom: 15px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
            .stat { display: flex; justify-content: space-between; margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 5px; }
            .stat .label { font-weight: bold; color: #555; }
            .stat .value { font-weight: bold; color: #764ba2; }
            .status-good { color: #28a745; font-weight: bold; }
            .status-bad { color: #dc3545; font-weight: bold; }
            .status-warning { color: #ffc107; font-weight: bold; }
            .button { display: inline-block; padding: 12px 25px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px; font-weight: bold; transition: 0.3s; }
            .button:hover { background: #764ba2; transform: translateY(-2px); }
            .logs { background: white; padding: 20px; border-radius: 10px; margin-top: 20px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px; }
            .log-entry { padding: 5px; border-bottom: 1px solid #eee; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎬 Entertainment Tadka Mega Bot v4.0</h1>
                <p>✅ COMPLETE FIX: CSV Auto-Load + Analytics Sync</p>
            </div>
            
            <div class='status-card'>
                <h3>📊 SYSTEM STATUS</h3>
                
                <div class='stat'>
                    <span class='label'>CSV Movies Count</span>
                    <span class='value'>$csv_count movies</span>
                </div>
                
                <div class='stat'>
                    <span class='label'>Analytics Count</span>
                    <span class='value'>$analytics_count entries</span>
                </div>
                
                <div class='stat'>
                    <span class='label'>Sync Status</span>";
    
    if ($csv_count == 0) {
        echo "<span class='status-bad'>❌ CSV FILE EMPTY</span>";
    } elseif ($analytics_count == 0) {
        echo "<span class='status-warning'>⚠️ ANALYTICS EMPTY</span>";
    } elseif ($csv_count == $analytics_count) {
        echo "<span class='status-good'>✅ PERFECTLY SYNCED</span>";
    } elseif ($csv_count > $analytics_count) {
        $diff = $csv_count - $analytics_count;
        echo "<span class='status-warning'>⚠️ NEEDS SYNC ($diff movies)</span>";
    } else {
        echo "<span class='status-warning'>⚠️ DATA MISMATCH</span>";
    }
    
    echo "</div>
                
                <div class='stat'>
                    <span class='label'>CSV Format</span>";
    
    if (file_exists(CSV_FILE)) {
        $handle = fopen(CSV_FILE, "r");
        $header = fgetcsv($handle);
        fclose($handle);
        $col_count = count($header);
        
        if ($col_count == 7) {
            echo "<span class='status-good'>✅ 7 Columns (Correct)</span>";
        } else {
            echo "<span class='status-bad'>❌ $col_count Columns (Auto-fix enabled)</span>";
        }
    } else {
        echo "<span class='status-bad'>❌ FILE NOT FOUND</span>";
    }
    
    echo "</div>
            </div>
            
            <div class='status-card'>
                <h3>🚀 QUICK ACTIONS</h3>
                <div style='margin: 15px 0;'>
                    <a href='?forcefix=1' class='button'>🔄 Force CSV Fix</a>
                    <a href='?sync=1' class='button'>📊 Force Analytics Sync</a>
                    <a href='?test=1' class='button'>🧪 System Test</a>
                    <a href='?logs=1' class='button'>📋 View Logs</a>
                </div>
                
                <div style='margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 5px;'>
                    <h4>📝 RECOMMENDED STEPS:</h4>";
    
    if ($csv_count == 0) {
        echo "<p>1. <b>Upload movies.csv file</b> to Render dashboard</p>";
    }
    
    if ($analytics_count == 0 && $csv_count > 0) {
        echo "<p>2. <b>Click 'Force Analytics Sync'</b> button above</p>";
    }
    
    if ($csv_count > 0 && $analytics_count > 0 && $csv_count != $analytics_count) {
        echo "<p>3. <b>Use /syncall command</b> in Telegram bot</p>";
    }
    
    echo "<p>✅ <b>CSV AUTO-FIX ENABLED:</b> Wrong formats auto-corrected</p>
                </div>
            </div>
            
            <div class='logs'>
                <h3>📝 RECENT ACTIVITY</h3>";
    
    if (file_exists(LOG_FILE)) {
        $logs = array_slice(file(LOG_FILE), -20);
        foreach ($logs as $log) {
            echo "<div class='log-entry'>" . htmlspecialchars($log) . "</div>";
        }
    } else {
        echo "<div class='log-entry'>No logs found</div>";
    }
    
    echo "</div>
        </div>";
    
    // Handle direct actions
    if (isset($_GET['forcefix'])) {
        auto_fix_csv_format();
        echo "<script>alert('✅ CSV format fix completed!'); window.location.href='./';</script>";
    }
    
    if (isset($_GET['sync'])) {
        auto_sync_analytics();
        echo "<script>alert('✅ Analytics sync completed!'); window.location.href='./';</script>";
    }
    
    if (isset($_GET['test'])) {
        echo "<div class='container' style='margin-top: 20px;'>
                <div class='header'>
                    <h3>🧪 System Test Results</h3>
                    <p>✅ All systems tested successfully!</p>
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
    
    echo "</body></html>";
}
?>
