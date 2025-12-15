<?php
// ============================================================
// ENTERTAINMENT TADKA MEGA BOT v5.0 - COMPLETE FINAL VERSION
// ============================================================
// Total Lines: ~1200 lines (Complete Implementation)
// ============================================================

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Kolkata');
error_reporting(E_ALL);

// ==================== BOT CONFIGURATION ====================
define('BOT_TOKEN', '8315381064:AAGk0FGVGmB8j5SjpBvW3rD3_kQHe_hyOWU'); // @BotFather se lo
define('ADMIN_ID', '1080317415'); // Apna Telegram ID
define('CHANNEL_ID', '-1003181705395'); // Apna channel ID
define('MAIN_CHANNEL', '@EntertainmentTadka786');
define('REQUEST_CHANNEL', '@EntertainmentTadka7860');
define('BACKUP_CHANNEL', '@ETBackup');
define('DELETE_AFTER_MINUTES', 15);
define('ITEMS_PER_PAGE', 5);
define('DAILY_REQUEST_LIMIT', 5);

// ==================== FILE PATHS ====================
define('CSV_FILE', 'movies.csv');
define('USERS_FILE', 'users.json');
define('STATS_FILE', 'stats.json');
define('REQUEST_FILE', 'requests.json');
define('ANALYTICS_DB', 'analytics.db');
define('BACKUP_DIR', 'backups/');
define('LOG_FILE', 'bot_log.txt');

// ==================== MAINTENANCE MODE ====================
$MAINTENANCE_MODE = false;
$MAINTENANCE_MSG = "🛠️ <b>Bot Under Maintenance</b>\n\nWe're updating the system.\nWill be back soon!\n\nThanks for patience 🙏";

// ==================== SYSTEM INITIALIZATION ====================
function initialize_system() {
    // 1. CSV file create karo (7 columns)
    if (!file_exists(CSV_FILE)) {
        $handle = fopen(CSV_FILE, "w");
        fputcsv($handle, ['movie_name','message_id','date','video_path','quality','size','language']);
        fclose($handle);
        log_message("SYSTEM", "New CSV file created");
    } else {
        auto_fix_csv();
    }
    
    // 2. JSON files create karo
    $files = [
        USERS_FILE => ['users' => [], 'last_update' => date('Y-m-d H:i:s')],
        STATS_FILE => [
            'total_movies' => 0, 'total_users' => 0, 'total_searches' => 0,
            'total_downloads' => 0, 'today_uploads' => 0, 'last_update' => date('Y-m-d H:i:s')
        ],
        REQUEST_FILE => [
            'pending' => [], 'completed' => [], 'rejected' => [],
            'daily_counts' => []
        ]
    ];
    
    foreach ($files as $file => $data) {
        if (!file_exists($file)) {
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        }
    }
    
    // 3. SQLite database create karo
    $db = new SQLite3(ANALYTICS_DB);
    $db->exec("CREATE TABLE IF NOT EXISTS uploads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        movie_name TEXT,
        message_id INTEGER,
        upload_date DATE,
        quality TEXT,
        size TEXT,
        language TEXT,
        category TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS deletions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_name TEXT,
        chat_id INTEGER,
        message_id INTEGER,
        delete_time DATETIME,
        status TEXT DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS user_activity (
        user_id INTEGER,
        action TEXT,
        details TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->close();
    
    // 4. Directories create karo
    $dirs = [BACKUP_DIR, 'temp/', 'cache/'];
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    
    log_message("SYSTEM", "All systems initialized");
}

// ==================== CSV AUTO-FIX SYSTEM ====================
function auto_fix_csv() {
    if (!file_exists(CSV_FILE)) return;
    
    $temp_file = 'temp/movies_fixed.csv';
    $input = fopen(CSV_FILE, 'r');
    $output = fopen($temp_file, 'w');
    
    if (!$input || !$output) return;
    
    // Check current format
    $header = fgetcsv($input);
    $col_count = count($header);
    
    if ($col_count == 7 && $header[0] == 'movie_name') {
        // Already correct format
        fclose($input);
        fclose($output);
        @unlink($temp_file);
        return;
    }
    
    // Write correct header
    fputcsv($output, ['movie_name','message_id','date','video_path','quality','size','language']);
    
    // Process all rows
    $fixed_count = 0;
    rewind($input);
    fgetcsv($input); // Skip old header
    
    while (($row = fgetcsv($input)) !== false) {
        if (count($row) >= 3) {
            $movie_name = trim($row[0]);
            $message_id = isset($row[1]) ? trim($row[1]) : '';
            $date = isset($row[2]) ? trim($row[2]) : date('d-m-Y');
            
            // Auto-detect quality
            $quality = '720p';
            $quality_map = [
                '4k' => '4K', '2160p' => '4K', 'uhd' => '4K',
                '1080p' => '1080p', 'fhd' => '1080p',
                '720p' => '720p', 'hd' => 'HD',
                '480p' => '480p', '360p' => '360p'
            ];
            
            $name_lower = strtolower($movie_name);
            foreach ($quality_map as $key => $value) {
                if (strpos($name_lower, $key) !== false) {
                    $quality = $value;
                    break;
                }
            }
            
            // Auto-detect language
            $language = 'Hindi';
            $language_map = [
                'telugu' => 'Telugu', 'tel' => 'Telugu',
                'tamil' => 'Tamil', 'tam' => 'Tamil',
                'kannada' => 'Kannada', 'kan' => 'Kannada',
                'malayalam' => 'Malayalam', 'mal' => 'Malayalam',
                'english' => 'English', 'eng' => 'English',
                'hindi' => 'Hindi', 'hin' => 'Hindi',
                'bengali' => 'Bengali', 'beng' => 'Bengali',
                'punjabi' => 'Punjabi', 'punj' => 'Punjabi'
            ];
            
            foreach ($language_map as $key => $value) {
                if (strpos($name_lower, $key) !== false) {
                    $language = $value;
                    break;
                }
            }
            
            // Auto-detect size based on quality
            $size = '1.5GB';
            $size_map = [
                '4K' => '4.5GB', '2160p' => '4.5GB',
                '1080p' => '2.1GB', 'FHD' => '2.1GB',
                'HD' => '1.8GB', '720p' => '1.5GB',
                '480p' => '800MB', '360p' => '400MB'
            ];
            $size = $size_map[$quality] ?? '1.5GB';
            
            // Auto-detect category
            $category = 'Movie';
            if (strpos($name_lower, 'season') !== false || 
                strpos($name_lower, 's0') !== false ||
                strpos($name_lower, ' episode') !== false) {
                $category = 'Series';
            }
            
            // New row with 7 columns
            $new_row = [
                $movie_name,    // movie_name
                $message_id,    // message_id
                $date,          // date
                '',             // video_path (empty)
                $quality,       // quality
                $size,          // size
                $language       // language
            ];
            
            fputcsv($output, $new_row);
            $fixed_count++;
            
            // Sync to analytics
            sync_to_analytics($movie_name, $message_id, $date, $quality, $size, $language, $category);
        }
    }
    
    fclose($input);
    fclose($output);
    
    // Replace old file
    copy($temp_file, CSV_FILE);
    @unlink($temp_file);
    
    log_message("CSV_FIX", "Fixed $fixed_count movies to 7-column format");
    return $fixed_count;
}

// ==================== ANALYTICS SYNC SYSTEM ====================
function sync_to_analytics($movie_name, $message_id, $date, $quality, $size, $language, $category) {
    $db = new SQLite3(ANALYTICS_DB);
    
    // Check if already exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM uploads WHERE movie_name = ? AND message_id = ?");
    $stmt->bindValue(1, $movie_name, SQLITE3_TEXT);
    $stmt->bindValue(2, $message_id, SQLITE3_INTEGER);
    $exists = $stmt->execute()->fetchArray()[0];
    
    if (!$exists && !empty($movie_name)) {
        // Convert date format
        $date_parts = explode('-', $date);
        if (count($date_parts) == 3) {
            $sql_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
        } else {
            $sql_date = date('Y-m-d');
        }
        
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
        
        $stmt->execute();
        
        // Update stats
        $stats = json_decode(file_get_contents(STATS_FILE), true);
        $stats['total_movies'] = ($stats['total_movies'] ?? 0) + 1;
        $stats['last_update'] = date('Y-m-d H:i:s');
        file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
    }
    
    $db->close();
}

function sync_all_analytics() {
    if (!file_exists(CSV_FILE)) return 0;
    
    $db = new SQLite3(ANALYTICS_DB);
    $synced = 0;
    
    $handle = fopen(CSV_FILE, "r");
    fgetcsv($handle); // Skip header
    
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) >= 7) {
            $movie_name = trim($row[0]);
            $message_id = trim($row[1]);
            $date = trim($row[2]);
            $quality = trim($row[4]);
            $size = trim($row[5]);
            $language = trim($row[6]);
            
            if (empty($movie_name)) continue;
            
            // Auto-detect category
            $category = 'Movie';
            if (stripos($movie_name, 'season') !== false || 
                stripos($movie_name, 's0') !== false) {
                $category = 'Series';
            }
            
            // Check if exists
            $stmt = $db->prepare("SELECT COUNT(*) FROM uploads WHERE movie_name = ?");
            $stmt->bindValue(1, $movie_name, SQLITE3_TEXT);
            $exists = $stmt->execute()->fetchArray()[0];
            
            if (!$exists) {
                // Convert date
                $date_parts = explode('-', $date);
                if (count($date_parts) == 3) {
                    $sql_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
                } else {
                    $sql_date = date('Y-m-d');
                }
                
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
            }
        }
    }
    
    fclose($handle);
    $db->close();
    
    log_message("ANALYTICS", "Synced $synced movies to analytics");
    return $synced;
}

// ==================== LOGGING SYSTEM ====================
function log_message($type, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

// ==================== TELEGRAM API FUNCTIONS ====================
function bot_api($method, $params = []) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result ? json_decode($result, true) : false;
}

function send_message($chat_id, $text, $keyboard = null, $parse_mode = 'HTML') {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => true
    ];
    
    if ($keyboard) {
        $params['reply_markup'] = json_encode($keyboard);
    }
    
    $result = bot_api('sendMessage', $params);
    
    if ($result && $result['ok']) {
        log_message("MESSAGE", "Sent to $chat_id: " . substr($text, 0, 50));
    }
    
    return $result;
}

function edit_message($chat_id, $message_id, $text, $keyboard = null) {
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($keyboard) {
        $params['reply_markup'] = json_encode($keyboard);
    }
    
    return bot_api('editMessageText', $params);
}

function delete_message($chat_id, $message_id) {
    return bot_api('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

function forward_message($chat_id, $from_chat_id, $message_id) {
    return bot_api('forwardMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

function answer_callback($callback_id, $text = '', $show_alert = false) {
    $params = ['callback_query_id' => $callback_id];
    if ($text) $params['text'] = $text;
    if ($show_alert) $params['show_alert'] = true;
    return bot_api('answerCallbackQuery', $params);
}

// ==================== MOVIE DATABASE SYSTEM ====================
$movie_cache = [];
$movie_cache_time = 0;

function load_movies() {
    global $movie_cache, $movie_cache_time;
    
    // Cache for 5 minutes
    if (!empty($movie_cache) && (time() - $movie_cache_time) < 300) {
        return $movie_cache;
    }
    
    if (!file_exists(CSV_FILE)) {
        return [];
    }
    
    $movies = [];
    $handle = fopen(CSV_FILE, "r");
    
    if ($handle) {
        fgetcsv($handle); // Skip header
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 7 && !empty(trim($row[0]))) {
                $movies[] = [
                    'name' => trim($row[0]),
                    'message_id' => trim($row[1]),
                    'date' => trim($row[2]),
                    'quality' => trim($row[4]),
                    'size' => trim($row[5]),
                    'language' => trim($row[6])
                ];
            }
        }
        
        fclose($handle);
    }
    
    $movie_cache = $movies;
    $movie_cache_time = time();
    
    // Update stats
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats['total_movies'] = count($movies);
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
    
    return $movies;
}

function search_movies($query, $limit = 10) {
    $movies = load_movies();
    $results = [];
    $query_lower = strtolower(trim($query));
    
    if (empty($query_lower)) return [];
    
    foreach ($movies as $movie) {
        $score = 0;
        $movie_lower = strtolower($movie['name']);
        
        // Exact match
        if ($movie_lower == $query_lower) {
            $score = 100;
        }
        // Starts with
        elseif (strpos($movie_lower, $query_lower) === 0) {
            $score = 90;
        }
        // Contains
        elseif (strpos($movie_lower, $query_lower) !== false) {
            $score = 80;
        }
        // Similar words
        else {
            $movie_words = explode(' ', $movie_lower);
            $query_words = explode(' ', $query_lower);
            $matching_words = 0;
            
            foreach ($query_words as $q_word) {
                foreach ($movie_words as $m_word) {
                    if (strpos($m_word, $q_word) !== false || strpos($q_word, $m_word) !== false) {
                        $matching_words++;
                        break;
                    }
                }
            }
            
            if ($matching_words > 0) {
                $score = ($matching_words / count($query_words)) * 70;
            }
        }
        
        if ($score > 50) {
            $results[] = [
                'movie' => $movie,
                'score' => $score
            ];
        }
    }
    
    // Sort by score
    usort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    // Limit results
    $results = array_slice($results, 0, $limit);
    
    // Update stats
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats['total_searches'] = ($stats['total_searches'] ?? 0) + 1;
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
    
    return $results;
}

function add_movie($movie_name, $message_id = '', $quality = '720p', $size = '1.5GB', $language = 'Hindi') {
    $date = date('d-m-Y');
    
    $handle = fopen(CSV_FILE, "a");
    fputcsv($handle, [$movie_name, $message_id, $date, '', $quality, $size, $language]);
    fclose($handle);
    
    // Clear cache
    global $movie_cache;
    $movie_cache = [];
    
    // Sync to analytics
    $category = 'Movie';
    if (stripos($movie_name, 'season') !== false) {
        $category = 'Series';
    }
    
    sync_to_analytics($movie_name, $message_id, $date, $quality, $size, $language, $category);
    
    log_message("ADD_MOVIE", "Added: $movie_name");
    return true;
}

// ==================== ANALYTICS COMMANDS ====================
function command_recent($limit = 10) {
    $db = new SQLite3(ANALYTICS_DB);
    
    $stmt = $db->prepare("SELECT * FROM uploads ORDER BY timestamp DESC LIMIT ?");
    $stmt->bindValue(1, $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $message = "🆕 <b>RECENT UPLOADS</b>\n";
    $message .= "📊 Last $limit uploads\n\n";
    
    $counter = 1;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $time_ago = time_ago($row['timestamp']);
        $short_name = (strlen($row['movie_name']) > 35) ? 
                     substr($row['movie_name'], 0, 35) . "..." : $row['movie_name'];
        
        $message .= "$counter. <b>" . htmlspecialchars($short_name) . "</b>\n";
        $message .= "   📅 " . date('d/m', strtotime($row['upload_date'])) . 
                   " | ⏰ " . $time_ago . "\n";
        $message .= "   📊 " . $row['quality'] . " | 🗣️ " . $row['language'] . 
                   " | 💾 " . $row['size'] . "\n\n";
        $counter++;
    }
    
    if ($counter == 1) {
        $message = "📭 <b>No uploads found!</b>\n\n";
        $message .= "Use /syncall to sync CSV to analytics";
    }
    
    $db->close();
    return $message;
}

function command_first_upload() {
    $db = new SQLite3(ANALYTICS_DB);
    
    $stmt = $db->prepare("SELECT * FROM uploads ORDER BY timestamp ASC LIMIT 1");
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$row) {
        $db->close();
        return "📭 <b>No uploads in analytics!</b>\n\nUse /syncall command";
    }
    
    $message = "🥇 <b>FIRST UPLOAD EVER</b>\n\n";
    $message .= "🎬 <b>Title:</b> " . htmlspecialchars($row['movie_name']) . "\n";
    $message .= "📅 <b>Date:</b> " . date('d M Y', strtotime($row['upload_date'])) . "\n";
    $message .= "📊 <b>Quality:</b> " . $row['quality'] . "\n";
    $message .= "🗣️ <b>Language:</b> " . $row['language'] . "\n";
    $message .= "📁 <b>Category:</b> " . $row['category'] . "\n";
    $message .= "💾 <b>Size:</b> " . $row['size'] . "\n\n";
    
    $days_ago = floor((time() - strtotime($row['timestamp'])) / 86400);
    $message .= "⏳ <b>Uploaded:</b> $days_ago days ago\n";
    
    // Total count
    $total = $db->querySingle("SELECT COUNT(*) FROM uploads");
    $message .= "📈 <b>Total Uploads Since:</b> $total\n\n";
    $message .= "📍 <b>Our journey started here!</b>";
    
    $db->close();
    return $message;
}

function command_total_stats() {
    $db = new SQLite3(ANALYTICS_DB);
    
    $total = $db->querySingle("SELECT COUNT(*) FROM uploads");
    
    if ($total == 0) {
        $db->close();
        return "📭 <b>No analytics data!</b>\n\nUse /syncall to import data";
    }
    
    // Category distribution
    $movies = $db->querySingle("SELECT COUNT(*) FROM uploads WHERE category = 'Movie'");
    $series = $db->querySingle("SELECT COUNT(*) FROM uploads WHERE category = 'Series'");
    
    // Quality distribution
    $quality_result = $db->query("SELECT quality, COUNT(*) as count FROM uploads 
                                 WHERE quality != '' GROUP BY quality ORDER BY count DESC");
    
    $quality_stats = "";
    while ($row = $quality_result->fetchArray(SQLITE3_ASSOC)) {
        $percentage = round(($row['count'] / $total) * 100);
        $quality_stats .= "• " . $row['quality'] . ": " . $row['count'] . " ($percentage%)\n";
    }
    
    // Language distribution
    $lang_result = $db->query("SELECT language, COUNT(*) as count FROM uploads 
                              WHERE language != '' GROUP BY language ORDER BY count DESC");
    
    $language_stats = "";
    while ($row = $lang_result->fetchArray(SQLITE3_ASSOC)) {
        $percentage = round(($row['count'] / $total) * 100);
        $language_stats .= "• " . $row['language'] . ": " . $row['count'] . " ($percentage%)\n";
    }
    
    // Date range
    $date_stmt = $db->prepare("SELECT MIN(upload_date) as first, MAX(upload_date) as last FROM uploads");
    $date_result = $date_stmt->execute();
    $date_row = $date_result->fetchArray(SQLITE3_ASSOC);
    
    $first_date = $date_row['first'] ? date('d M Y', strtotime($date_row['first'])) : 'Unknown';
    $last_date = $date_row['last'] ? date('d M Y', strtotime($date_row['last'])) : 'Unknown';
    
    $db->close();
    
    $message = "📊 <b>TOTAL UPLOADS STATISTICS</b>\n\n";
    $message .= "🎯 <b>Grand Total:</b> $total uploads\n";
    $message .= "📅 <b>First Upload:</b> $first_date\n";
    $message .= "📅 <b>Last Upload:</b> $last_date\n\n";
    
    $message .= "📁 <b>Category Distribution:</b>\n";
    $message .= "• Movies: $movies\n";
    $message .= "• Series: $series\n\n";
    
    if (!empty($quality_stats)) {
        $message .= "🎬 <b>Quality Distribution:</b>\n$quality_stats\n";
    }
    
    if (!empty($language_stats)) {
        $message .= "🗣️ <b>Language Distribution:</b>\n$language_stats\n";
    }
    
    return $message;
}

function command_today_stats() {
    $today = date('Y-m-d');
    $db = new SQLite3(ANALYTICS_DB);
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM uploads WHERE upload_date = ?");
    $stmt->bindValue(1, $today, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $today_count = $row['count'];
    
    // Yesterday count
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM uploads WHERE upload_date = ?");
    $stmt->bindValue(1, $yesterday, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $yesterday_count = $row['count'];
    
    // Today's uploads details
    $stmt = $db->prepare("SELECT * FROM uploads WHERE upload_date = ? ORDER BY timestamp DESC");
    $stmt->bindValue(1, $today, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $message = "📅 <b>TODAY'S UPLOADS - " . date('d M Y') . "</b>\n\n";
    $message .= "📊 <b>Total Today:</b> $today_count uploads\n";
    
    if ($yesterday_count > 0) {
        $change = $today_count - $yesterday_count;
        $change_text = ($change > 0) ? "📈 +$change" : (($change < 0) ? "📉 $change" : "📊 Same");
        $message .= "📈 <b>Vs Yesterday:</b> $change_text\n";
    }
    
    $message .= "\n📋 <b>Today's Uploads:</b>\n";
    
    $counter = 1;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $time = date('H:i', strtotime($row['timestamp']));
        $short_name = (strlen($row['movie_name']) > 30) ? 
                     substr($row['movie_name'], 0, 30) . "..." : $row['movie_name'];
        
        $message .= "$counter. <b>$time</b> - " . htmlspecialchars($short_name) . "\n";
        $counter++;
    }
    
    if ($counter == 1) {
        $message .= "No uploads today yet.\n";
    }
    
    $db->close();
    return $message;
}

function command_upload_calendar($month = null, $year = null) {
    if ($month === null) $month = date('n');
    if ($year === null) $year = date('Y');
    
    $db = new SQLite3(ANALYTICS_DB);
    
    $start_date = "$year-" . sprintf("%02d", $month) . "-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $stmt = $db->prepare("SELECT upload_date, COUNT(*) as count FROM uploads 
                         WHERE upload_date BETWEEN ? AND ? 
                         GROUP BY upload_date ORDER BY upload_date");
    $stmt->bindValue(1, $start_date, SQLITE3_TEXT);
    $stmt->bindValue(2, $end_date, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $daily_counts = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $day = date('j', strtotime($row['upload_date']));
        $daily_counts[$day] = $row['count'];
    }
    
    $month_name = date('F', strtotime($start_date));
    $first_day = date('w', strtotime($start_date));
    $days_in_month = date('t', strtotime($start_date));
    
    $message = "📅 <b>UPLOAD CALENDAR - $month_name $year</b>\n\n";
    
    // Week headers
    $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $message .= implode(" ", $weekdays) . "\n";
    
    $day_counter = 1;
    $week = "";
    
    // Empty spaces for first week
    for ($i = 0; $i < $first_day; $i++) {
        $week .= "   ";
    }
    
    // Calendar grid
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
    
    $message .= "\n📊 <b>Statistics:</b>\n";
    $message .= "• Total Uploads: $total_uploads\n";
    $message .= "• Active Days: $active_days\n";
    
    if ($active_days > 0) {
        $avg = round($total_uploads / $active_days, 2);
        $message .= "• Average per Day: $avg\n";
    }
    
    if (!empty($daily_counts)) {
        $max_day = array_keys($daily_counts, max($daily_counts))[0];
        $max_count = max($daily_counts);
        $message .= "• Busiest Day: $max_day ($max_count uploads)\n";
    }
    
    $message .= "\n📈 <b>Legend:</b>\n";
    $message .= "🔥 = 10+ uploads\n";
    $message .= "⚡ = 5-9 uploads\n";
    $message .= "📤 = 1-4 uploads\n";
    $message .= "⬜ = No uploads\n";
    
    $db->close();
    return $message;
}

// ==================== COPYRIGHT PROTECTION SYSTEM ====================
function schedule_deletion($chat_id, $message_id, $file_name, $file_size = '', $quality = '') {
    $db = new SQLite3(ANALYTICS_DB);
    
    $delete_time = date('Y-m-d H:i:s', time() + (DELETE_AFTER_MINUTES * 60));
    
    $stmt = $db->prepare("INSERT INTO deletions 
        (file_name, chat_id, message_id, delete_time) 
        VALUES (?, ?, ?, ?)");
    
    $stmt->bindValue(1, $file_name, SQLITE3_TEXT);
    $stmt->bindValue(2, $chat_id, SQLITE3_INTEGER);
    $stmt->bindValue(3, $message_id, SQLITE3_INTEGER);
    $stmt->bindValue(4, $delete_time, SQLITE3_TEXT);
    
    $stmt->execute();
    $schedule_id = $db->lastInsertRowID();
    
    $db->close();
    
    // Send warning message
    send_deletion_warning($chat_id, $message_id, $file_name, $file_size, $quality, $delete_time, $schedule_id);
    
    log_message("DELETION", "Scheduled: $file_name (ID: $schedule_id)");
    return $schedule_id;
}

function send_deletion_warning($chat_id, $message_id, $file_name, $file_size, $quality, $delete_time, $schedule_id) {
    $current_time = date("g:i A");
    $delete_formatted = date("g:i A", strtotime($delete_time));
    
    // Extract movie info
    $info = extract_file_info($file_name);
    $display_name = $info['title'] ?: $file_name;
    
    // Calculate progress
    $total_seconds = DELETE_AFTER_MINUTES * 60;
    $elapsed = time() - (strtotime($delete_time) - $total_seconds);
    $percentage = min(100, max(0, ($elapsed / $total_seconds) * 100));
    $progress_bar = get_progress_bar($percentage);
    $countdown = get_countdown_timer($delete_time);
    
    $message = "🎬 <b>" . htmlspecialchars($display_name) . "</b>";
    if ($quality) {
        $message .= " [$quality]";
    }
    if ($file_size) {
        $message .= "\n💾 " . htmlspecialchars($file_size);
    }
    
    $message .= "\n═══════════════════════════════\n";
    $message .= "🚨 <b>COPYRIGHT PROTECTION ALERT</b> 🚨\n";
    $message .= "═══════════════════════════════\n";
    $message .= "⚠️ <b>Auto-Delete:</b> " . DELETE_AFTER_MINUTES . " Minutes\n";
    $message .= "🛡️ <b>Protection:</b> Copyright Shield Active\n\n";
    
    $message .= "✅ <b>ACTION REQUIRED:</b>\n";
    $message .= "├─ 📤 Forward File Now\n";
    $message .= "├─ 💾 Save to Device/Cloud\n";
    $message .= "├─ ⬇️ Download Immediately\n";
    $message .= "└─ ⚠️ Avoid Auto-Deletion\n\n";
    
    $message .= "⏳ <b>Countdown:</b> $countdown\n";
    $message .= "$progress_bar " . round($percentage) . "%\n\n";
    
    $message .= "⏰ Uploaded: $current_time\n";
    $message .= "🗑️ Deletes at: $delete_formatted\n";
    $message .= "🔔 Channel: " . MAIN_CHANNEL;
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔗 JOIN CHANNEL', 'url' => 'https://t.me/' . str_replace('@', '', MAIN_CHANNEL)],
                ['text' => '⏰ COUNTDOWN', 'callback_data' => 'countdown_' . $schedule_id]
            ],
            [
                ['text' => '✅ I SAVED IT', 'callback_data' => 'saved_' . $schedule_id],
                ['text' => '❌ DELETE NOW', 'callback_data' => 'delete_now_' . $schedule_id]
            ]
        ]
    ];
    
    send_message($chat_id, $message, $keyboard, 'HTML');
}

function process_deletions() {
    $db = new SQLite3(ANALYTICS_DB);
    $now = date('Y-m-d H:i:s');
    
    $stmt = $db->prepare("SELECT * FROM deletions WHERE delete_time <= ? AND status = 'pending'");
    $stmt->bindValue(1, $now, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $deleted = 0;
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $delete_result = delete_message($row['chat_id'], $row['message_id']);
        
        if ($delete_result && $delete_result['ok']) {
            // Update status
            $update_stmt = $db->prepare("UPDATE deletions SET status = 'deleted' WHERE id = ?");
            $update_stmt->bindValue(1, $row['id'], SQLITE3_INTEGER);
            $update_stmt->execute();
            
            // Send notification
            $final_msg = "⏰ <b>TIME'S UP!</b>\n\n";
            $final_msg .= "🗑️ <b>" . htmlspecialchars($row['file_name']) . "</b>\n";
            $final_msg .= "has been automatically deleted.\n\n";
            $final_msg .= "⚠️ Always forward files immediately!\n";
            $final_msg .= "🔗 " . MAIN_CHANNEL;
            
            send_message($row['chat_id'], $final_msg, null, 'HTML');
            
            $deleted++;
            log_message("DELETE", "Deleted: {$row['file_name']}");
        }
    }
    
    $db->close();
    return $deleted;
}

// ==================== HELPER FUNCTIONS ====================
function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return $diff . " seconds ago";
    elseif ($diff < 3600) return floor($diff / 60) . " minutes ago";
    elseif ($diff < 86400) return floor($diff / 3600) . " hours ago";
    elseif ($diff < 604800) return floor($diff / 86400) . " days ago";
    else return date('d M Y', $time);
}

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

function extract_file_info($file_name) {
    $info = ['title' => '', 'year' => '', 'quality' => ''];
    
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

function get_user_stats($user_id) {
    $users = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users['users'][$user_id])) {
        $users['users'][$user_id] = [
            'join_date' => date('Y-m-d'),
            'last_active' => date('Y-m-d H:i:s'),
            'search_count' => 0,
            'request_count' => 0,
            'points' => 0
        ];
    }
    
    return $users['users'][$user_id];
}

function update_user_stats($user_id, $action) {
    $users = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users['users'][$user_id])) {
        $users['users'][$user_id] = [
            'join_date' => date('Y-m-d'),
            'last_active' => date('Y-m-d H:i:s'),
            'search_count' => 0,
            'request_count' => 0,
            'points' => 0
        ];
    }
    
    switch ($action) {
        case 'search':
            $users['users'][$user_id]['search_count']++;
            $users['users'][$user_id]['points'] += 1;
            break;
        case 'request':
            $users['users'][$user_id]['request_count']++;
            $users['users'][$user_id]['points'] += 2;
            break;
    }
    
    $users['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
}

// ==================== MAIN UPDATE HANDLER ====================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    // Initialize system
    initialize_system();
    
    // Check maintenance
    global $MAINTENANCE_MODE, $MAINTENANCE_MSG;
    if ($MAINTENANCE_MODE && isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        send_message($chat_id, $MAINTENANCE_MSG);
        exit;
    }
    
    // Process scheduled deletions every minute
    if (date('i') % 1 == 0) { // Every minute
        $deleted = process_deletions();
        if ($deleted > 0) {
            log_message("AUTO_DELETE", "Deleted $deleted files");
        }
    }
    
    // Handle messages
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = $message['text'] ?? '';
        
        // Handle file uploads for copyright protection
        $has_file = isset($message['video']) || isset($message['document']) || 
                    isset($message['audio']) || isset($message['photo']);
        
        if ($has_file && $user_id != ADMIN_ID) {
            $file_name = '';
            $file_size = '';
            $quality = '';
            
            if (isset($message['video'])) {
                $file_name = $message['video']['file_name'] ?? 'Video_' . time() . '.mp4';
                $file_size = format_size($message['video']['file_size'] ?? 0);
            } elseif (isset($message['document'])) {
                $file_name = $message['document']['file_name'];
                $file_size = format_size($message['document']['file_size'] ?? 0);
            }
            
            if (isset($message['caption'])) {
                $file_name = $message['caption'] . ' - ' . $file_name;
            }
            
            if ($file_name) {
                schedule_deletion($chat_id, $message['message_id'], $file_name, $file_size, $quality);
            }
        }
        
        // Handle commands
        if (!empty($text)) {
            $command = strtolower(trim(explode(' ', $text)[0]));
            
            switch ($command) {
                // ============ START & HELP ============
                case '/start':
                    $welcome = "🎬 <b>Entertainment Tadka Mega Bot v5.0</b>\n\n";
                    $welcome .= "✅ <b>COMPLETE FEATURES:</b>\n";
                    $welcome .= "• Smart Movie Search System\n";
                    $welcome .= "• Copyright Protection (Auto-delete)\n";
                    $welcome .= "• Upload Analytics & Statistics\n";
                    $welcome .= "• CSV Auto-Fix & Sync System\n\n";
                    
                    $welcome .= "🔍 <b>How to Search:</b> Just type movie name\n";
                    $welcome .= "🛡️ <b>Protection:</b> Files auto-delete in " . DELETE_AFTER_MINUTES . " min\n";
                    $welcome .= "📊 <b>Analytics:</b> /recent, /1stupload, /totalupload\n\n";
                    
                    $welcome .= "📢 <b>Channels:</b>\n";
                    $welcome .= "• Main: " . MAIN_CHANNEL . "\n";
                    $welcome .= "• Requests: " . REQUEST_CHANNEL . "\n";
                    $welcome .= "• Backup: " . BACKUP_CHANNEL . "\n\n";
                    
                    $welcome .= "🚀 <b>Enjoy Unlimited Entertainment!</b>";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => ''],
                                ['text' => '📊 Analytics', 'callback_data' => 'analytics_menu']
                            ],
                            [
                                ['text' => '🛡️ Protection Info', 'callback_data' => 'protection_info'],
                                ['text' => '📢 Join Channel', 'url' => 'https://t.me/EntertainmentTadka786']
                            ]
                        ]
                    ];
                    
                    send_message($chat_id, $welcome, $keyboard);
                    break;
                    
                case '/help':
                    $help = "🤖 <b>COMPLETE HELP GUIDE</b>\n\n";
                    
                    $help .= "🎯 <b>MOVIE SEARCH:</b>\n";
                    $help .= "• Type movie name directly\n";
                    $help .= "• Example: 'Animal 2023'\n";
                    $help .= "• Hindi/English both work\n\n";
                    
                    $help .= "🛡️ <b>COPYRIGHT PROTECTION:</b>\n";
                    $help .= "• Upload any file to bot\n";
                    $help .= "• Auto-deletes in " . DELETE_AFTER_MINUTES . " minutes\n";
                    $help .= "• Progress bar & countdown\n\n";
                    
                    $help .= "📊 <b>ANALYTICS COMMANDS:</b>\n";
                    $help .= "• /recent - Recent uploads\n";
                    $help .= "• /1stupload - First upload ever\n";
                    $help .= "• /totalupload - Total statistics\n";
                    $help .= "• /today - Today's uploads\n";
                    $help .= "• /calendar - Upload calendar\n\n";
                    
                    $help .= "⚙️ <b>ADMIN COMMANDS:</b>\n";
                    $help .= "• /syncall - Sync CSV to analytics\n";
                    $help .= "• /addmovie - Add new movie\n";
                    $help .= "• /backup - Create backup\n";
                    $help .= "• /stats - Bot statistics\n\n";
                    
                    $help .= "📝 <b>OTHER COMMANDS:</b>\n";
                    $help .= "• /request - Request movie\n";
                    $help .= "• /mystats - Your statistics\n";
                    $help .= "• /channel - Join channels\n";
                    $help .= "• /checkdata - Check data status";
                    
                    send_message($chat_id, $help);
                    break;
                    
                // ============ SEARCH COMMANDS ============
                case '/search':
                    $query = trim(substr($text, strlen('/search')));
                    if (empty($query)) {
                        send_message($chat_id, "❌ Usage: /search movie_name\nExample: /search Animal 2023");
                        break;
                    }
                    
                    $results = search_movies($query, 10);
                    update_user_stats($user_id, 'search');
                    
                    if (!empty($results)) {
                        $message = "🔍 <b>Search Results for '$query'</b>\n";
                        $message .= "📊 Found " . count($results) . " movies\n\n";
                        
                        foreach ($results as $index => $result) {
                            $movie = $result['movie'];
                            $message .= ($index + 1) . ". <b>" . htmlspecialchars($movie['name']) . "</b>\n";
                            $message .= "   📊 " . $movie['quality'] . " | 🗣️ " . $movie['language'] . 
                                       " | 💾 " . $movie['size'] . "\n\n";
                        }
                        
                        // Create download buttons
                        $keyboard = ['inline_keyboard' => []];
                        $top_results = array_slice($results, 0, 3);
                        
                        foreach ($top_results as $index => $result) {
                            $movie = $result['movie'];
                            $keyboard['inline_keyboard'][] = [[
                                'text' => "⬇️ " . ($index + 1) . ". " . shorten_text($movie['name'], 20),
                                'callback_data' => 'download_' . base64_encode($movie['name'])
                            ]];
                        }
                        
                        $keyboard['inline_keyboard'][] = [[
                            'text' => '📝 Request Different Movie',
                            'callback_data' => 'request_menu'
                        ]];
                        
                        send_message($chat_id, $message, $keyboard);
                    } else {
                        $total = count(load_movies());
                        $message = "❌ <b>No movies found for '$query'</b>\n\n";
                        $message .= "📁 Total movies in database: $total\n";
                        $message .= "📝 Request this movie: /request $query\n";
                        $message .= "🔗 Request Channel: " . REQUEST_CHANNEL;
                        
                        $keyboard = [
                            'inline_keyboard' => [[
                                ['text' => '📝 Request This Movie', 'callback_data' => 'auto_request_' . base64_encode($query)]
                            ]]
                        ];
                        
                        send_message($chat_id, $message, $keyboard);
                    }
                    break;
                    
                // ============ ANALYTICS COMMANDS ============
                case '/recent':
                    send_message($chat_id, command_recent());
                    break;
                    
                case '/1stupload':
                    send_message($chat_id, command_first_upload());
                    break;
                    
                case '/totalupload':
                    send_message($chat_id, command_total_stats());
                    break;
                    
                case '/today':
                    send_message($chat_id, command_today_stats());
                    break;
                    
                case '/calendar':
                    $params = explode(' ', $text);
                    $month = isset($params[1]) ? intval($params[1]) : null;
                    $year = isset($params[2]) ? intval($params[2]) : null;
                    send_message($chat_id, command_upload_calendar($month, $year));
                    break;
                    
                // ============ ADMIN COMMANDS ============
                case '/syncall':
                    if ($user_id == ADMIN_ID) {
                        send_message($chat_id, "🔄 Starting complete sync...");
                        
                        // Auto-fix CSV first
                        $fixed = auto_fix_csv();
                        
                        // Sync to analytics
                        $synced = sync_all_analytics();
                        
                        // Get counts
                        $movies = load_movies();
                        $db = new SQLite3(ANALYTICS_DB);
                        $analytics_count = $db->querySingle("SELECT COUNT(*) FROM uploads");
                        $db->close();
                        
                        $message = "✅ <b>SYNC COMPLETED!</b>\n\n";
                        $message .= "📊 CSV Movies: " . count($movies) . "\n";
                        $message .= "📈 Analytics DB: $analytics_count entries\n";
                        
                        if ($fixed > 0) {
                            $message .= "🔄 CSV Fixed: $fixed movies\n";
                        }
                        
                        if ($synced > 0) {
                            $message .= "🔄 Newly Synced: $synced movies\n";
                        }
                        
                        if (count($movies) == $analytics_count) {
                            $message .= "\n🎉 <b>PERFECTLY SYNCED!</b>";
                        } else {
                            $diff = abs(count($movies) - $analytics_count);
                            $message .= "\n⚠️ <b>Difference: $diff entries</b>";
                        }
                        
                        send_message($chat_id, $message);
                    } else {
                        send_message($chat_id, "❌ Admin only command!");
                    }
                    break;
                    
                case '/addmovie':
                    if ($user_id == ADMIN_ID) {
                        $parts = explode('|', substr($text, strlen('/addmovie ') + 1));
                        if (count($parts) >= 3) {
                            $movie_name = trim($parts[0]);
                            $quality = trim($parts[1]);
                            $language = trim($parts[2]);
                            $size = isset($parts[3]) ? trim($parts[3]) : '1.5GB';
                            
                            add_movie($movie_name, '', $quality, $size, $language);
                            send_message($chat_id, "✅ Movie added: $movie_name");
                        } else {
                            send_message($chat_id, "❌ Format: /addmovie Movie Name|Quality|Language|Size\nExample: /addmovie Animal 2023|1080p|Hindi|2.1GB");
                        }
                    } else {
                        send_message($chat_id, "❌ Admin only command!");
                    }
                    break;
                    
                case '/backup':
                    if ($user_id == ADMIN_ID) {
                        $backup_dir = BACKUP_DIR . date('Y-m-d_H-i-s');
                        mkdir($backup_dir, 0777, true);
                        
                        $files = [CSV_FILE, USERS_FILE, STATS_FILE, REQUEST_FILE, ANALYTICS_DB];
                        foreach ($files as $file) {
                            if (file_exists($file)) {
                                copy($file, $backup_dir . '/' . basename($file));
                            }
                        }
                        
                        send_message($chat_id, "✅ Backup created: $backup_dir");
                        log_message("BACKUP", "Manual backup created");
                    }
                    break;
                    
                case '/stats':
                    $stats = json_decode(file_get_contents(STATS_FILE), true);
                    $movies = load_movies();
                    
                    $message = "📊 <b>BOT STATISTICS</b>\n\n";
                    $message .= "🎬 Total Movies: " . count($movies) . "\n";
                    $message .= "👥 Total Users: " . ($stats['total_users'] ?? 0) . "\n";
                    $message .= "🔍 Total Searches: " . ($stats['total_searches'] ?? 0) . "\n";
                    $message .= "⬇️ Total Downloads: " . ($stats['total_downloads'] ?? 0) . "\n";
                    $message .= "📤 Today's Uploads: " . ($stats['today_uploads'] ?? 0) . "\n\n";
                    
                    $db = new SQLite3(ANALYTICS_DB);
                    $analytics_count = $db->querySingle("SELECT COUNT(*) FROM uploads");
                    $pending_deletions = $db->querySingle("SELECT COUNT(*) FROM deletions WHERE status = 'pending'");
                    $db->close();
                    
                    $message .= "📈 Analytics DB: $analytics_count entries\n";
                    $message .= "🛡️ Pending Deletions: $pending_deletions\n";
                    $message .= "📅 Last Update: " . ($stats['last_update'] ?? 'Never');
                    
                    send_message($chat_id, $message);
                    break;
                    
                case '/checkdata':
                    $movies = load_movies();
                    $db = new SQLite3(ANALYTICS_DB);
                    $analytics_count = $db->querySingle("SELECT COUNT(*) FROM uploads");
                    $db->close();
                    
                    $message = "📊 <b>DATA STATUS CHECK</b>\n\n";
                    $message .= "📁 CSV Database: " . count($movies) . " movies\n";
                    $message .= "📈 Analytics DB: $analytics_count entries\n\n";
                    
                    if (count($movies) == 0) {
                        $message .= "❌ <b>CSV FILE EMPTY!</b>\n";
                        $message .= "Upload movies.csv file\n";
                    } elseif ($analytics_count == 0) {
                        $message .= "❌ <b>ANALYTICS EMPTY!</b>\n";
                        $message .= "Use /syncall command\n";
                    } elseif (count($movies) == $analytics_count) {
                        $message .= "✅ <b>PERFECTLY SYNCED!</b>\n";
                    } else {
                        $diff = abs(count($movies) - $analytics_count);
                        $message .= "⚠️ <b>NEEDS SYNC: $diff difference</b>\n";
                        $message .= "Use /syncall command\n";
                    }
                    
                    // CSV format check
                    if (file_exists(CSV_FILE)) {
                        $handle = fopen(CSV_FILE, "r");
                        $header = fgetcsv($handle);
                        fclose($handle);
                        
                        $col_count = count($header);
                        $message .= "\n📋 CSV Format: $col_count columns\n";
                        
                        if ($col_count == 7) {
                            $message .= "✅ Correct 7-column format\n";
                        } else {
                            $message .= "❌ Wrong format (needs 7 columns)\n";
                            $message .= "Auto-fix enabled!\n";
                        }
                    }
                    
                    send_message($chat_id, $message);
                    break;
                    
                // ============ USER COMMANDS ============
                case '/request':
                    $movie_name = trim(substr($text, strlen('/request')));
                    if (empty($movie_name)) {
                        send_message($chat_id, "❌ Usage: /request movie_name\nExample: /request Animal 2 2024");
                        break;
                    }
                    
                    // Check daily limit
                    $requests = json_decode(file_get_contents(REQUEST_FILE), true);
                    $today = date('Y-m-d');
                    $user_today = 0;
                    
                    foreach ($requests['pending'] as $req) {
                        if ($req['user_id'] == $user_id && $req['date'] == $today) {
                            $user_today++;
                        }
                    }
                    
                    if ($user_today >= DAILY_REQUEST_LIMIT) {
                        send_message($chat_id, "❌ Daily limit reached! Maximum " . DAILY_REQUEST_LIMIT . " requests per day.");
                        break;
                    }
                    
                    // Add request
                    $new_request = [
                        'id' => uniqid(),
                        'user_id' => $user_id,
                        'movie_name' => $movie_name,
                        'date' => $today,
                        'time' => date('H:i:s'),
                        'status' => 'pending'
                    ];
                    
                    $requests['pending'][] = $new_request;
                    file_put_contents(REQUEST_FILE, json_encode($requests, JSON_PRETTY_PRINT));
                    
                    update_user_stats($user_id, 'request');
                    
                    $message = "✅ <b>REQUEST SUBMITTED!</b>\n\n";
                    $message .= "🎬 Movie: $movie_name\n";
                    $message .= "👤 User: You\n";
                    $message .= "📅 Date: $today\n";
                    $message .= "⏰ Time: " . date('H:i:s') . "\n\n";
                    $message .= "📊 Today's Requests: " . ($user_today + 1) . "/" . DAILY_REQUEST_LIMIT . "\n\n";
                    $message .= "We'll add it soon! Keep checking.";
                    
                    send_message($chat_id, $message);
                    
                    // Notify admin
                    $admin_msg = "📝 <b>NEW MOVIE REQUEST</b>\n\n";
                    $admin_msg .= "🎬 Movie: $movie_name\n";
                    $admin_msg .= "👤 User ID: $user_id\n";
                    $admin_msg .= "📅 Date: $today\n";
                    $admin_msg .= "📊 Total Pending: " . count($requests['pending']) . "\n\n";
                    $admin_msg .= "Use /addmovie to add it.";
                    
                    send_message(ADMIN_ID, $admin_msg);
                    break;
                    
                case '/mystats':
                    $user_stats = get_user_stats($user_id);
                    $movies = load_movies();
                    
                    $message = "👤 <b>YOUR STATISTICS</b>\n\n";
                    $message .= "📅 Join Date: " . $user_stats['join_date'] . "\n";
                    $message .= "⏰ Last Active: " . $user_stats['last_active'] . "\n";
                    $message .= "🔍 Searches: " . $user_stats['search_count'] . "\n";
                    $message .= "📝 Requests: " . $user_stats['request_count'] . "\n";
                    $message .= "⭐ Points: " . $user_stats['points'] . "\n\n";
                    
                    $message .= "📊 <b>BOT STATS:</b>\n";
                    $message .= "• Total Movies: " . count($movies) . "\n";
                    $message .= "• Daily Request Limit: " . DAILY_REQUEST_LIMIT . "\n";
                    $message .= "• Copyright Timer: " . DELETE_AFTER_MINUTES . " minutes\n\n";
                    
                    $message .= "🎯 <b>Keep searching and earning points!</b>";
                    
                    send_message($chat_id, $message);
                    break;
                    
                case '/channel':
                    $message = "📢 <b>OUR CHANNELS</b>\n\n";
                    $message .= "🍿 <b>Main Channel:</b> " . MAIN_CHANNEL . "\n";
                    $message .= "Latest movies & updates\n\n";
                    $message .= "📥 <b>Requests Channel:</b> " . REQUEST_CHANNEL . "\n";
                    $message .= "Movie requests & support\n\n";
                    $message .= "🔒 <b>Backup Channel:</b> " . BACKUP_CHANNEL . "\n";
                    $message .= "Data backups & archives\n\n";
                    $message .= "🔔 <b>Join all for complete experience!</b>";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '🍿 Main Channel', 'url' => 'https://t.me/EntertainmentTadka786'],
                                ['text' => '📥 Requests', 'url' => 'https://t.me/EntertainmentTadka7860']
                            ],
                            [
                                ['text' => '🔒 Backup', 'url' => 'https://t.me/ETBackup']
                            ]
                        ]
                    ];
                    
                    send_message($chat_id, $message, $keyboard);
                    break;
                    
                default:
                    // Regular text - treat as movie search
                    if (strlen($text) > 2 && !str_starts_with($text, '/')) {
                        $results = search_movies($text, 5);
                        update_user_stats($user_id, 'search');
                        
                        if (!empty($results)) {
                            $message = "🔍 <b>Found " . count($results) . " results for '$text':</b>\n\n";
                            
                            foreach ($results as $index => $result) {
                                $movie = $result['movie'];
                                $message .= ($index + 1) . ". <b>" . htmlspecialchars($movie['name']) . "</b>\n";
                                $message .= "   📊 " . $movie['quality'] . " | 🗣️ " . $movie['language'] . "\n\n";
                            }
                            
                            send_message($chat_id, $message);
                        } else {
                            $total = count(load_movies());
                            $message = "❌ <b>No movies found for '$text'</b>\n\n";
                            $message .= "📁 Total movies: $total\n";
                            $message .= "📝 Request it: /request $text\n";
                            $message .= "🔗 Channel: " . MAIN_CHANNEL;
                            
                            send_message($chat_id, $message);
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
        $user_id = $query['from']['id'];
        $message_id = $query['message']['message_id'];
        
        // Movie download callback
        if (strpos($data, 'download_') === 0) {
            $movie_name = base64_decode(substr($data, 9));
            $movies = load_movies();
            $found = false;
            
            foreach ($movies as $movie) {
                if (strcasecmp($movie['name'], $movie_name) === 0) {
                    if (!empty($movie['message_id']) && is_numeric($movie['message_id'])) {
                        $forward_result = forward_message($chat_id, CHANNEL_ID, $movie['message_id']);
                        
                        if ($forward_result && $forward_result['ok']) {
                            answer_callback($query['id'], "✅ Movie forwarded!");
                            
                            // Update stats
                            $stats = json_decode(file_get_contents(STATS_FILE), true);
                            $stats['total_downloads'] = ($stats['total_downloads'] ?? 0) + 1;
                            file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
                            
                            $found = true;
                            break;
                        }
                    }
                }
            }
            
            if (!$found) {
                answer_callback($query['id'], "❌ Could not forward movie. Try searching again.");
            }
        }
        
        // Analytics menu
        elseif ($data == 'analytics_menu') {
            $menu = "📊 <b>ANALYTICS MENU</b>\n\n";
            $menu .= "Select an option:\n\n";
            $menu .= "1️⃣ /recent - Recent uploads\n";
            $menu .= "2️⃣ /1stupload - First upload ever\n";
            $menu .= "3️⃣ /totalupload - Total statistics\n";
            $menu .= "4️⃣ /today - Today's uploads\n";
            $menu .= "5️⃣ /calendar - Monthly calendar\n";
            $menu .= "6️⃣ /stats - Bot statistics\n\n";
            $menu .= "📈 Complete upload tracking!";
            
            send_message($chat_id, $menu);
            answer_callback($query['id']);
        }
        
        // Protection info
        elseif ($data == 'protection_info') {
            $info = "🛡️ <b>COPYRIGHT PROTECTION SYSTEM</b>\n\n";
            $info .= "⚠️ <b>How it works:</b>\n";
            $info .= "1. Upload any file to bot\n";
            $info .= "2. Bot sends warning message\n";
            $info .= "3. File auto-deletes in " . DELETE_AFTER_MINUTES . " minutes\n";
            $info .= "4. Forward file to save it\n\n";
            
            $info .= "🎯 <b>Features:</b>\n";
            $info .= "• Progress bar countdown\n";
            $info .= "• Live timer updates\n";
            $info .= "• One-click actions\n";
            $info .= "• Admin controls\n\n";
            
            $info .= "🔒 <b>Protect against copyright!</b>";
            
            send_message($chat_id, $info);
            answer_callback($query['id']);
        }
        
        // Request menu
        elseif ($data == 'request_menu') {
            send_message($chat_id, "📝 To request a movie:\n\nUse command:\n<code>/request movie_name</code>\n\nExample:\n<code>/request Animal 2023</code>");
            answer_callback($query['id']);
        }
        
        // Auto request
        elseif (strpos($data, 'auto_request_') === 0) {
            $movie_name = base64_decode(substr($data, 13));
            
            // Check daily limit
            $requests = json_decode(file_get_contents(REQUEST_FILE), true);
            $today = date('Y-m-d');
            $user_today = 0;
            
            foreach ($requests['pending'] as $req) {
                if ($req['user_id'] == $user_id && $req['date'] == $today) {
                    $user_today++;
                }
            }
            
            if ($user_today >= DAILY_REQUEST_LIMIT) {
                answer_callback($query['id'], "❌ Daily limit reached!", true);
                send_message($chat_id, "❌ Daily limit reached! Maximum " . DAILY_REQUEST_LIMIT . " requests per day.");
            } else {
                // Add request
                $new_request = [
                    'id' => uniqid(),
                    'user_id' => $user_id,
                    'movie_name' => $movie_name,
                    'date' => $today,
                    'time' => date('H:i:s'),
                    'status' => 'pending'
                ];
                
                $requests['pending'][] = $new_request;
                file_put_contents(REQUEST_FILE, json_encode($requests, JSON_PRETTY_PRINT));
                
                update_user_stats($user_id, 'request');
                
                answer_callback($query['id'], "✅ Request sent!");
                send_message($chat_id, "✅ Request submitted for: $movie_name\n\nWe'll add it soon!");
                
                // Notify admin
                send_message(ADMIN_ID, "📝 Auto-request: $movie_name\nUser: $user_id");
            }
        }
        
        // Deletion callbacks
        elseif (strpos($data, 'countdown_') === 0) {
            $schedule_id = substr($data, 10);
            $db = new SQLite3(ANALYTICS_DB);
            $stmt = $db->prepare("SELECT delete_time FROM deletions WHERE id = ?");
            $stmt->bindValue(1, $schedule_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $db->close();
            
            if ($row) {
                $countdown = get_countdown_timer($row['delete_time']);
                answer_callback($query['id'], "⏰ Countdown: $countdown", true);
            }
        }
        
        elseif (strpos($data, 'saved_') === 0) {
            answer_callback($query['id'], "✅ Great! File saved successfully.");
        }
        
        elseif (strpos($data, 'delete_now_') === 0 && $user_id == ADMIN_ID) {
            $schedule_id = substr($data, 11);
            $db = new SQLite3(ANALYTICS_DB);
            $stmt = $db->prepare("SELECT * FROM deletions WHERE id = ? AND status = 'pending'");
            $stmt->bindValue(1, $schedule_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($row) {
                delete_message($row['chat_id'], $row['message_id']);
                
                // Update status
                $update_stmt = $db->prepare("UPDATE deletions SET status = 'deleted_manual' WHERE id = ?");
                $update_stmt->bindValue(1, $schedule_id, SQLITE3_INTEGER);
                $update_stmt->execute();
                
                answer_callback($query['id'], "🗑️ File deleted immediately!", true);
                send_message($row['chat_id'], "🗑️ Admin deleted this file immediately.");
            }
            
            $db->close();
        }
    }
}

// ==================== WEB INTERFACE ====================
if (!isset($update) && php_sapi_name() != 'cli') {
    initialize_system();
    
    // Auto-process deletions
    process_deletions();
    
    // Get stats
    $movies = load_movies();
    $db = new SQLite3(ANALYTICS_DB);
    $analytics_count = $db->querySingle("SELECT COUNT(*) FROM uploads");
    $pending_deletions = $db->querySingle("SELECT COUNT(*) FROM deletions WHERE status = 'pending'");
    $db->close();
    
    $users = json_decode(file_get_contents(USERS_FILE), true);
    $user_count = count($users['users']);
    
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>🎬 Entertainment Tadka Mega Bot v5.0</title>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
            .container { max-width: 1200px; margin: 0 auto; }
            .header { background: white; padding: 40px; border-radius: 20px; margin-bottom: 30px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
            .header h1 { color: #667eea; font-size: 3em; margin-bottom: 10px; }
            .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .stat-card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
            .stat-card h3 { color: #667eea; margin-bottom: 15px; font-size: 1.4em; display: flex; align-items: center; gap: 10px; }
            .stat-number { font-size: 2.5em; font-weight: bold; color: #764ba2; margin: 10px 0; }
            .features { background: white; padding: 25px; border-radius: 15px; margin-bottom: 30px; }
            .features h3 { color: #667eea; margin-bottom: 20px; }
            .feature-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
            .feature-item { background: #f8f9fa; padding: 15px; border-radius: 10px; }
            .buttons { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 30px; justify-content: center; }
            .btn { padding: 15px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 50px; font-weight: bold; transition: 0.3s; border: none; cursor: pointer; }
            .btn:hover { transform: scale(1.05); box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4); }
            .btn-secondary { background: white; color: #667eea; border: 2px solid #667eea; }
            .logs { background: white; padding: 20px; border-radius: 15px; margin-top: 30px; max-height: 300px; overflow-y: auto; }
            .log-entry { padding: 10px; border-bottom: 1px solid #eee; font-family: monospace; font-size: 0.9em; }
            .status { display: inline-block; padding: 5px 15px; background: #4CAF50; color: white; border-radius: 20px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎬 Entertainment Tadka Mega Bot v5.0</h1>
                <p>Complete Solution: Search + Protection + Analytics</p>
                <div class='status'>✅ ONLINE</div>
            </div>
            
            <div class='stats-grid'>
                <div class='stat-card'>
                    <h3>📊 Movie Database</h3>
                    <div class='stat-number'>" . count($movies) . "</div>
                    <p>Total movies in CSV</p>
                </div>
                
                <div class='stat-card'>
                    <h3>📈 Analytics DB</h3>
                    <div class='stat-number'>$analytics_count</div>
                    <p>Tracked uploads</p>
                </div>
                
                <div class='stat-card'>
                    <h3>👥 Users</h3>
                    <div class='stat-number'>$user_count</div>
                    <p>Active users</p>
                </div>
                
                <div class='stat-card'>
                    <h3>🛡️ Protection</h3>
                    <div class='stat-number'>$pending_deletions</div>
                    <p>Pending deletions</p>
                </div>
            </div>
            
            <div class='features'>
                <h3>🚀 Complete Features</h3>
                <div class='feature-list'>
                    <div class='feature-item'>
                        <h4>🔍 Smart Search</h4>
                        <p>Fuzzy matching, multi-language, quality filters</p>
                    </div>
                    <div class='feature-item'>
                        <h4>🛡️ Copyright Protection</h4>
                        <p>Auto-delete in " . DELETE_AFTER_MINUTES . " minutes with countdown</p>
                    </div>
                    <div class='feature-item'>
                        <h4>📊 Analytics</h4>
                        <p>Upload tracking, statistics, calendar view</p>
                    </div>
                    <div class='feature-item'>
                        <h4>⚡ Auto Systems</h4>
                        <p>CSV auto-fix, analytics sync, auto-backup</p>
                    </div>
                </div>
            </div>
            
            <div class='buttons'>
                <a href='?sync=1' class='btn'>🔄 Force Sync</a>
                <a href='?fixcsv=1' class='btn'>🔧 Fix CSV Format</a>
                <a href='?backup=1' class='btn'>💾 Create Backup</a>
                <a href='?test=1' class='btn btn-secondary'>🧪 Test Systems</a>
                <a href='?logs=1' class='btn btn-secondary'>📋 View Logs</a>
            </div>
            
            <div class='logs'>
                <h3>📝 Recent Activity</h3>";
    
    if (file_exists(LOG_FILE)) {
        $logs = array_slice(file(LOG_FILE), -20);
        foreach ($logs as $log) {
            echo "<div class='log-entry'>" . htmlspecialchars($log) . "</div>";
        }
    } else {
        echo "<div class='log-entry'>No logs yet</div>";
    }
    
    echo "</div>
        </div>
        
        <script>
            setTimeout(() => location.reload(), 30000);
        </script>
    </body>
    </html>";
    
    // Handle web actions
    if (isset($_GET['sync'])) {
        $fixed = auto_fix_csv();
        $synced = sync_all_analytics();
        echo "<script>alert('✅ Synced! Fixed: $fixed, Synced: $synced'); location.href='./';</script>";
    }
    
    if (isset($_GET['fixcsv'])) {
        $fixed = auto_fix_csv();
        echo "<script>alert('✅ CSV fixed: $fixed movies'); location.href='./';</script>";
    }
    
    if (isset($_GET['backup'])) {
        $backup_dir = BACKUP_DIR . date('Y-m-d_H-i-s');
        mkdir($backup_dir, 0777, true);
        
        $files = [CSV_FILE, USERS_FILE, STATS_FILE, REQUEST_FILE, ANALYTICS_DB];
        foreach ($files as $file) {
            if (file_exists($file)) {
                copy($file, $backup_dir . '/' . basename($file));
            }
        }
        
        echo "<script>alert('✅ Backup created: $backup_dir'); location.href='./';</script>";
    }
}

// ==================== HELPER FUNCTIONS ====================
function format_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 0) {
        return $bytes . ' bytes';
    } else {
        return '';
    }
}

function shorten_text($text, $length = 30) {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length - 3) . '...';
}

// ==================== END OF BOT ====================
?>
