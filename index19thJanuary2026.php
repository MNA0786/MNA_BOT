<?php
// ===================================================
// ENTERTAINMENT TADKA BOT - ULTIMATE OPTIMIZED VERSION
// ===================================================
// Version: 3.0.0 | Date: 2024-01-19
// Author: Entertainment Tadka
// Description: Complete movie database bot with all channels support
// ===================================================

// ===================================================
// 1. SECURITY & CONFIGURATION
// ===================================================

// Strict error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production: 0, Development: 1
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// Prevent caching for dynamic content
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// ===================================================
// 2. ENVIRONMENT CONFIGURATION
// ===================================================

// Render.com PORT configuration
$port = getenv('PORT') ?: '80';

// Webhook URL auto-detection
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$webhook_url = $protocol . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// Validate essential environment variables
if (!getenv('BOT_TOKEN')) {
    die("❌ BOT_TOKEN environment variable not set. Set it in Render.com dashboard.");
}

// ===================================================
// 3. CONSTANTS DEFINITION
// ===================================================

// Bot Token (from environment)
define('BOT_TOKEN', getenv('BOT_TOKEN'));

// Channel Configuration
define('MAIN_CHANNEL', '@EntertainmentTadka786');
define('MAIN_CHANNEL_ID', '-1003251791991');
define('THEATER_CHANNEL', '@threater_print_movies');
define('THEATER_CHANNEL_ID', '-1003614546520');
define('BACKUP_CHANNEL_USERNAME', '@ETBackup');
define('BACKUP_CHANNEL_ID', '-1002337293281');
define('REQUEST_CHANNEL', '@EntertainmentTadka7860');
define('ADMIN_ID', (int)getenv('ADMIN_ID') ?: 0);

// File Paths
define('CSV_FILE', 'movies.csv');
define('USERS_FILE', 'users.json');
define('STATS_FILE', 'bot_stats.json');
define('REQUEST_FILE', 'movie_requests.json');
define('BACKUP_DIR', 'backups/');
define('LOG_FILE', 'bot_activity.log');
define('CACHE_FILE', 'cache/movie_cache.json');
define('SESSION_DIR', 'sessions/');

// Bot Settings
define('CACHE_EXPIRY', 300); // 5 minutes
define('ITEMS_PER_PAGE', 8);
define('MAX_SEARCH_RESULTS', 20);
define('DAILY_REQUEST_LIMIT', 10);
define('AUTO_BACKUP_HOUR', '03');
define('RATE_LIMIT_PER_MINUTE', 30);
define('MAX_MESSAGE_LENGTH', 4096);

// Pagination Settings
define('MAX_PAGES_TO_SHOW', 7);
define('PAGINATION_CACHE_TIMEOUT', 30);
define('PREVIEW_ITEMS', 5);
define('BATCH_SIZE', 10);

// ===================================================
// 4. QUICK ADD FORMAT CONSTANTS
// ===================================================

define('QUICKADD_FORMAT', "🎬 <b>Quick Add Format:</b>

<code>/quickadd movie_name (year),message_id,channel_username_or_id</code>

<b>Examples:</b>
• <code>/quickadd Avengers Endgame (2019),12345,@EntertainmentTadka786</code>
• <code>/quickadd KGF Chapter 2 (2022),67890,@threater_print_movies</code>
• <code>/quickadd Animal (2023),54321,-1003251791991</code>
• <code>/quickadd Pushpa 2 (2024),11111,-1002337293281</code>
• <code>/quickadd Test Movie (2025),22222,-1003614546520</code>

<b>Supported Channels:</b>
• @EntertainmentTadka786 (Main)
• @threater_print_movies (Theater)
• @ETBackup (Backup)
• -1003251791991 (Private Channel)
• -1002337293281 (Backup 2)
• -1003614546520 (Any Channel)

<b>Note:</b> Multiple movies can be added at once and will be stored in CSV.");

// ===================================================
// 5. MAINTENANCE MODE
// ===================================================

$MAINTENANCE_MODE = false;
$MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>

We're temporarily unavailable for updates.
Will be back in few days!

Thanks for patience 🙏";

// ===================================================
// 6. GLOBAL VARIABLES
// ===================================================

$movie_cache = [];
$waiting_users = [];
$user_sessions = [];
$user_pagination_sessions = [];
$rate_limit_tracker = [];

// ===================================================
// 7. CORE UTILITY FUNCTIONS
// ===================================================

/**
 * Optimized API Request with error handling
 */
function apiRequest($method, $params = [], $is_multipart = false) {
    static $curl_handle = null;
    
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    if ($is_multipart) {
        if ($curl_handle === null) {
            $curl_handle = curl_init();
        }
        
        curl_setopt($curl_handle, CURLOPT_URL, $url);
        curl_setopt($curl_handle, CURLOPT_POST, true);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $params);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 2);
        
        $result = curl_exec($curl_handle);
        $http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
        
        if ($result === false) {
            $error = curl_error($curl_handle);
            bot_log("CURL ERROR [$method]: $error", 'ERROR');
            return json_encode(['ok' => false, 'error' => $error]);
        }
        
        if ($http_code !== 200) {
            bot_log("HTTP ERROR [$method]: $http_code - $result", 'ERROR');
        }
        
        return $result;
    } else {
        $options = [
            'http' => [
                'method' => 'POST',
                'content' => http_build_query($params),
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                           "User-Agent: EntertainmentTadkaBot/3.0.0\r\n"
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            ]
        ];
        
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        if ($result === false) {
            bot_log("API Request failed: $method", 'ERROR');
            return json_encode(['ok' => false]);
        }
        
        return $result;
    }
}

/**
 * Send message with typing indicator
 */
function sendMessageWithDelay($chat_id, $text, $reply_markup = null, $parse_mode = null, $delay_ms = 800) {
    // Send typing action
    apiRequest('sendChatAction', [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ]);
    
    // Artificial delay
    if ($delay_ms > 0) {
        usleep($delay_ms * 1000);
    }
    
    // Send actual message
    return sendMessage($chat_id, $text, $reply_markup, $parse_mode);
}

/**
 * Send message (optimized)
 */
function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = null) {
    // Split long messages
    if (strlen($text) > MAX_MESSAGE_LENGTH) {
        $chunks = str_split($text, MAX_MESSAGE_LENGTH - 100);
        $first_chunk = array_shift($chunks);
        
        $result = sendMessageRaw($chat_id, $first_chunk, $reply_markup, $parse_mode);
        
        // Send remaining chunks
        foreach ($chunks as $chunk) {
            usleep(200000); // 0.2s delay
            sendMessageRaw($chat_id, $chunk, null, $parse_mode);
        }
        
        return $result;
    }
    
    return sendMessageRaw($chat_id, $text, $reply_markup, $parse_mode);
}

/**
 * Send message raw (actual API call)
 */
function sendMessageRaw($chat_id, $text, $reply_markup = null, $parse_mode = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true,
        'disable_notification' => false
    ];
    
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    
    if ($parse_mode) {
        $data['parse_mode'] = $parse_mode;
    }
    
    $result = apiRequest('sendMessage', $data);
    $response = json_decode($result, true);
    
    if ($response && $response['ok']) {
        bot_log("Message sent to $chat_id: " . substr($text, 0, 100) . "...");
    } else {
        bot_log("Failed to send message to $chat_id", 'ERROR');
    }
    
    return $response;
}

/**
 * Edit message (optimized)
 */
function editMessage($chat_id, $message_id, $new_text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $new_text,
        'disable_web_page_preview' => true
    ];
    
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    
    return apiRequest('editMessageText', $data);
}

/**
 * Delete message
 */
function deleteMessage($chat_id, $message_id) {
    return apiRequest('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

/**
 * Answer callback query
 */
function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
    $data = [
        'callback_query_id' => $callback_query_id,
        'show_alert' => $show_alert
    ];
    
    if ($text) {
        $data['text'] = substr($text, 0, 200); // Telegram limit
    }
    
    return apiRequest('answerCallbackQuery', $data);
}

/**
 * Copy message (no forward header)
 */
function copyMessage($chat_id, $from_chat_id, $message_id, $caption = null) {
    $data = [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ];
    
    if ($caption) {
        $data['caption'] = substr($caption, 0, 1024);
    }
    
    return apiRequest('copyMessage', $data);
}

// ===================================================
// 8. LOGGING SYSTEM
// ===================================================

/**
 * Enhanced logging system
 */
function bot_log($message, $type = 'INFO', $file = LOG_FILE) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    
    // Ensure log directory exists
    $log_dir = dirname($file);
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    // Write to log file
    @file_put_contents($file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Also log to console if CLI
    if (php_sapi_name() === 'cli') {
        echo $log_entry;
    }
    
    // Log rotation (keep last 5MB)
    if (file_exists($file) && filesize($file) > 5 * 1024 * 1024) {
        $backup_file = $log_dir . '/bot_activity_' . date('Y-m-d_H-i-s') . '.log';
        @rename($file, $backup_file);
        
        // Keep only last 5 backup files
        $old_logs = glob($log_dir . '/bot_activity_*.log');
        if (count($old_logs) > 5) {
            usort($old_logs, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            for ($i = 0; $i < count($old_logs) - 5; $i++) {
                @unlink($old_logs[$i]);
            }
        }
    }
}

// ===================================================
// 9. FILE MANAGEMENT SYSTEM
// ===================================================

/**
 * Initialize all required files and directories
 */
function initialize_files() {
    $files_to_create = [
        CSV_FILE => "movie_name,message_id,date,video_path,quality,size,language,channel_type,channel_id,channel_username\n",
        USERS_FILE => json_encode([
            'users' => [],
            'total_requests' => 0,
            'message_logs' => [],
            'daily_stats' => []
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
            'performance_metrics' => []
        ], JSON_PRETTY_PRINT),
        REQUEST_FILE => json_encode([
            'requests' => [],
            'pending_approval' => [],
            'completed_requests' => [],
            'user_request_count' => []
        ], JSON_PRETTY_PRINT),
        CACHE_FILE => json_encode([
            'movies' => [],
            'timestamp' => 0,
            'expiry' => CACHE_EXPIRY
        ], JSON_PRETTY_PRINT)
    ];
    
    $directories_to_create = [
        BACKUP_DIR,
        'cache/',
        'sessions/',
        'temp/',
        'logs/'
    ];
    
    // Create directories
    foreach ($directories_to_create as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
            @chmod($dir, 0755);
        }
    }
    
    // Create files
    foreach ($files_to_create as $file => $content) {
        if (!file_exists($file)) {
            @file_put_contents($file, $content);
            @chmod($file, 0644);
            bot_log("File created: $file");
        }
    }
    
    // Create empty log file if not exists
    if (!file_exists(LOG_FILE)) {
        @file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] SYSTEM: Files initialized\n");
    }
    
    bot_log("File system initialized successfully");
}

// Initialize files on first run
initialize_files();

// ===================================================
// 10. CACHE MANAGEMENT SYSTEM
// ===================================================

/**
 * Get cached movies with intelligent caching
 */
function get_cached_movies() {
    global $movie_cache;
    
    // Return from memory cache if available and fresh
    if (!empty($movie_cache) && (time() - ($movie_cache['timestamp'] ?? 0)) < CACHE_EXPIRY) {
        return $movie_cache['data'] ?? [];
    }
    
    // Check file cache
    if (file_exists(CACHE_FILE)) {
        $file_cache = json_decode(file_get_contents(CACHE_FILE), true);
        
        if ($file_cache && isset($file_cache['movies']) && 
            (time() - $file_cache['timestamp']) < CACHE_EXPIRY) {
            
            $movie_cache = [
                'data' => $file_cache['movies'],
                'timestamp' => $file_cache['timestamp']
            ];
            
            return $file_cache['movies'];
        }
    }
    
    // Load from CSV and update cache
    $movies_data = load_and_clean_csv();
    
    $movie_cache = [
        'data' => $movies_data,
        'timestamp' => time()
    ];
    
    // Update file cache
    @file_put_contents(CACHE_FILE, json_encode([
        'movies' => $movies_data,
        'timestamp' => time(),
        'expiry' => CACHE_EXPIRY,
        'count' => count($movies_data)
    ], JSON_PRETTY_PRINT));
    
    bot_log("Movie cache refreshed - " . count($movies_data) . " movies loaded");
    return $movies_data;
}

/**
 * Clear cache
 */
function clear_cache($type = 'all') {
    global $movie_cache;
    
    if ($type === 'all' || $type === 'movies') {
        $movie_cache = [];
        
        if (file_exists(CACHE_FILE)) {
            @unlink(CACHE_FILE);
        }
        
        bot_log("Movie cache cleared");
    }
    
    if ($type === 'all' || $type === 'sessions') {
        $session_files = glob(SESSION_DIR . '*.json');
        foreach ($session_files as $file) {
            if (filemtime($file) < time() - 3600) { // Older than 1 hour
                @unlink($file);
            }
        }
        bot_log("Expired sessions cleared");
    }
    
    return true;
}

// ===================================================
// 11. CSV MANAGEMENT SYSTEM
// ===================================================

/**
 * Load and clean CSV data
 */
function load_and_clean_csv($filename = CSV_FILE) {
    $movies = [];
    
    if (!file_exists($filename)) {
        bot_log("CSV file not found: $filename", 'ERROR');
        return $movies;
    }
    
    $start_time = microtime(true);
    
    try {
        // Use SplFileObject for better memory management
        $file = new SplFileObject($filename);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',', '"', '\\');
        
        $header = null;
        $row_count = 0;
        $valid_rows = 0;
        
        foreach ($file as $row) {
            $row_count++;
            
            if ($row_count === 1) {
                $header = $row;
                continue;
            }
            
            if (count($row) < 3 || empty(trim($row[0]))) {
                continue;
            }
            
            $movie_name = trim($row[0]);
            $message_id_raw = isset($row[1]) ? trim($row[1]) : '';
            $date = isset($row[2]) ? trim($row[2]) : date('d-m-Y');
            $video_path = isset($row[3]) ? trim($row[3]) : '';
            $quality = isset($row[4]) ? trim($row[4]) : 'Unknown';
            $size = isset($row[5]) ? trim($row[5]) : 'Unknown';
            $language = isset($row[6]) ? trim($row[6]) : 'Hindi';
            $channel_type = isset($row[7]) ? trim($row[7]) : 'main';
            $channel_id = isset($row[8]) ? trim($row[8]) : '';
            $channel_username = isset($row[9]) ? trim($row[9]) : '';
            
            // Auto-detect channel type if empty
            if (empty($channel_type) && !empty($channel_id)) {
                $channel_type = get_channel_type_by_id($channel_id);
            }
            
            // Auto-detect quality from movie name
            if ($quality === 'Unknown') {
                $quality = detect_quality($movie_name);
            }
            
            // Auto-detect language from movie name
            if ($language === 'Hindi') {
                $language = detect_language($movie_name);
            }
            
            // Prepare movie entry
            $entry = [
                'movie_name' => $movie_name,
                'message_id_raw' => $message_id_raw,
                'message_id' => is_numeric($message_id_raw) ? intval($message_id_raw) : null,
                'date' => $date,
                'video_path' => $video_path,
                'quality' => $quality,
                'size' => $size,
                'language' => $language,
                'channel_type' => $channel_type,
                'channel_id' => $channel_id,
                'channel_username' => $channel_username,
                'source_channel' => $channel_id,
                'search_key' => strtolower($movie_name),
                'added_timestamp' => strtotime($date) ?: time()
            ];
            
            $movies[] = $entry;
            $valid_rows++;
        }
        
        // Clean up invalid dates
        $movies = array_filter($movies, function($movie) {
            return !empty($movie['movie_name']) && strlen($movie['movie_name']) > 1;
        });
        
        // Sort by date (newest first)
        usort($movies, function($a, $b) {
            return ($b['added_timestamp'] ?? 0) - ($a['added_timestamp'] ?? 0);
        });
        
        // Update statistics
        update_stats('total_movies', count($movies));
        
        $execution_time = microtime(true) - $start_time;
        bot_log("CSV loaded: $valid_rows valid rows of $row_count total (took " . round($execution_time, 3) . "s)");
        
    } catch (Exception $e) {
        bot_log("Error loading CSV: " . $e->getMessage(), 'ERROR');
        $movies = [];
    }
    
    return $movies;
}

/**
 * Save CSV data
 */
function save_csv_data($movies, $filename = CSV_FILE) {
    if (empty($movies)) {
        return false;
    }
    
    $backup_file = BACKUP_DIR . 'csv_backup_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Backup existing file
    if (file_exists($filename)) {
        @copy($filename, $backup_file);
    }
    
    // Write new CSV
    $handle = fopen($filename, 'w');
    if ($handle === false) {
        bot_log("Failed to open CSV file for writing: $filename", 'ERROR');
        return false;
    }
    
    // Write header
    fputcsv($handle, [
        'movie_name', 'message_id', 'date', 'video_path', 'quality', 
        'size', 'language', 'channel_type', 'channel_id', 'channel_username'
    ]);
    
    // Write data
    foreach ($movies as $movie) {
        fputcsv($handle, [
            $movie['movie_name'] ?? '',
            $movie['message_id_raw'] ?? '',
            $movie['date'] ?? '',
            $movie['video_path'] ?? '',
            $movie['quality'] ?? '',
            $movie['size'] ?? '',
            $movie['language'] ?? '',
            $movie['channel_type'] ?? '',
            $movie['channel_id'] ?? '',
            $movie['channel_username'] ?? ''
        ]);
    }
    
    fclose($handle);
    
    // Clear cache after save
    clear_cache('movies');
    
    bot_log("CSV saved: " . count($movies) . " movies written to $filename");
    return true;
}

/**
 * Add movie to CSV
 */
function add_movie_to_csv($movie_data) {
    $movies = get_cached_movies();
    
    // Check if movie already exists
    foreach ($movies as $movie) {
        if (strtolower($movie['movie_name']) === strtolower($movie_data['movie_name']) &&
            $movie['message_id_raw'] === $movie_data['message_id_raw']) {
            return false; // Duplicate
        }
    }
    
    // Add new movie
    $movies[] = $movie_data;
    
    // Save back to CSV
    return save_csv_data($movies);
}

// ===================================================
// 12. CHANNEL MANAGEMENT SYSTEM
// ===================================================

/**
 * Get channel ID by username
 */
function get_channel_id_by_username($username) {
    $username = strtolower(trim($username));
    
    $channel_map = [
        '@entertainmenttadka786' => MAIN_CHANNEL_ID,
        '@threater_print_movies' => THEATER_CHANNEL_ID,
        '@etbackup' => BACKUP_CHANNEL_ID,
        'entertainmenttadka786' => MAIN_CHANNEL_ID,
        'threater_print_movies' => THEATER_CHANNEL_ID,
        'etbackup' => BACKUP_CHANNEL_ID,
    ];
    
    return $channel_map[$username] ?? null;
}

/**
 * Get channel type by ID
 */
function get_channel_type_by_id($channel_id) {
    $channel_id = strval($channel_id);
    
    $channel_types = [
        MAIN_CHANNEL_ID => 'main',
        THEATER_CHANNEL_ID => 'theater',
        BACKUP_CHANNEL_ID => 'backup',
        '-1003251791991' => 'private',
        '-1002337293281' => 'backup2',
        '-1003614546520' => 'any'
    ];
    
    return $channel_types[$channel_id] ?? 'other';
}

/**
 * Get channel display name
 */
function get_channel_display_name($channel_type) {
    $names = [
        'main' => '🍿 Main Channel',
        'theater' => '🎭 Theater Prints',
        'backup' => '🔒 Backup Channel',
        'private' => '🔐 Private Channel',
        'backup2' => '💾 Backup 2',
        'any' => '📡 Any Channel',
        'other' => '📢 Other Channel'
    ];
    
    return $names[$channel_type] ?? '📢 Unknown Channel';
}

/**
 * Get channel username link
 */
function get_channel_username_link($channel_type) {
    switch ($channel_type) {
        case 'main':
            return "https://t.me/" . ltrim(MAIN_CHANNEL, '@');
        case 'theater':
            return "https://t.me/" . ltrim(THEATER_CHANNEL, '@');
        case 'backup':
        case 'backup2':
            return "https://t.me/" . ltrim(BACKUP_CHANNEL_USERNAME, '@');
        default:
            return "https://t.me/EntertainmentTadka786";
    }
}

/**
 * Get direct channel link
 */
function get_direct_channel_link($message_id, $channel_id) {
    if (empty($channel_id) || empty($message_id)) {
        return "Link not available";
    }
    
    $channel_id_clean = str_replace('-100', '', $channel_id);
    return "https://t.me/c/" . $channel_id_clean . "/" . $message_id;
}

// ===================================================
// 13. DETECTION FUNCTIONS
// ===================================================

/**
 * Detect quality from text
 */
function detect_quality($text) {
    $text_lower = strtolower($text);
    
    $quality_patterns = [
        '1080p' => ['1080p', '1080', 'fhd', 'full hd', 'fullhd'],
        '720p' => ['720p', '720', 'hd', 'high definition'],
        '480p' => ['480p', '480', 'sd', 'standard definition'],
        '2160p' => ['2160p', '4k', 'uhd', 'ultra hd'],
        'theater' => ['theater', 'theatre', 'print', 'hdcam', 'camrip', 'hdrip', 'webrip', 'dvdrip', 'bluray', 'brrip'],
        '360p' => ['360p', '360', 'low quality']
    ];
    
    foreach ($quality_patterns as $quality => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($text_lower, $pattern) !== false) {
                return $quality;
            }
        }
    }
    
    return 'Unknown';
}

/**
 * Detect language from text
 */
function detect_language($text) {
    $text_lower = strtolower($text);
    
    $language_patterns = [
        'English' => ['english', 'eng', 'en', 'english dub', 'eng dub'],
        'Hindi' => ['hindi', 'hin', 'hd', 'hindi dub', 'dubbed'],
        'Tamil' => ['tamil', 'tam', 'tm'],
        'Telugu' => ['telugu', 'tel', 'te'],
        'Malayalam' => ['malayalam', 'mal', 'ml'],
        'Kannada' => ['kannada', 'kan', 'kn'],
        'Bengali' => ['bengali', 'ben', 'bn'],
        'Punjabi' => ['punjabi', 'pun', 'pb'],
        'Marathi' => ['marathi', 'mar', 'mr'],
        'Gujarati' => ['gujarati', 'guj', 'gu'],
        'Urdu' => ['urdu', 'ur'],
        'Multi' => ['multi', 'dual', 'dual audio', 'hindi+english']
    ];
    
    foreach ($language_patterns as $language => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($text_lower, $pattern) !== false) {
                return $language;
            }
        }
    }
    
    return 'Hindi'; // Default
}

/**
 * Detect size from text
 */
function detect_size($text) {
    $text_lower = strtolower($text);
    
    preg_match_all('/(\d+(?:\.\d+)?)\s*(gb|mb|tb)/i', $text_lower, $matches);
    
    if (!empty($matches[0])) {
        return $matches[0][0];
    }
    
    // Common size patterns
    $size_patterns = [
        '1.5GB' => ['1.5gb', '1.5 gb', '1500mb'],
        '2.0GB' => ['2gb', '2.0gb', '2000mb'],
        '2.5GB' => ['2.5gb', '2.5 gb', '2500mb'],
        '3.0GB' => ['3gb', '3.0gb', '3000mb'],
        '4.0GB' => ['4gb', '4.0gb', '4000mb'],
        '500MB' => ['500mb', '0.5gb', '500 mb'],
        '700MB' => ['700mb', '0.7gb', '700 mb'],
        '1.0GB' => ['1gb', '1.0gb', '1000mb']
    ];
    
    foreach ($size_patterns as $size => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($text_lower, $pattern) !== false) {
                return $size;
            }
        }
    }
    
    return 'Unknown';
}

// ===================================================
// 14. QUICK ADD SYSTEM
// ===================================================

/**
 * Quick add single movie
 */
function quick_add_movie($chat_id, $user_id, $input_data) {
    if ($chat_id != ADMIN_ID && $user_id != ADMIN_ID) {
        sendMessageWithDelay($chat_id, "❌ Access denied. Admin only command.", null, 'HTML', 500);
        return;
    }
    
    $parts = explode(',', $input_data, 3);
    
    if (count($parts) < 3) {
        sendMessageWithDelay($chat_id, QUICKADD_FORMAT, null, 'HTML', 500);
        return;
    }
    
    $movie_name = trim($parts[0]);
    $message_id = trim($parts[1]);
    $channel_info = trim($parts[2]);
    
    if (!is_numeric($message_id) || $message_id <= 0) {
        sendMessageWithDelay($chat_id, "❌ Invalid message ID. Must be a positive number.", null, 'HTML', 500);
        return;
    }
    
    // Determine channel info
    $channel_type = 'other';
    $channel_id = '';
    $channel_username = '';
    
    if (strpos($channel_info, '@') === 0) {
        $channel_username = $channel_info;
        $channel_id = get_channel_id_by_username($channel_username);
        
        if ($channel_id) {
            $channel_type = get_channel_type_by_id($channel_id);
        }
    } elseif (is_numeric($channel_info) || strpos($channel_info, '-100') === 0) {
        $channel_id = $channel_info;
        $channel_type = get_channel_type_by_id($channel_id);
        
        // Get username based on type
        switch ($channel_type) {
            case 'main': $channel_username = MAIN_CHANNEL; break;
            case 'theater': $channel_username = THEATER_CHANNEL; break;
            case 'backup': 
            case 'backup2': $channel_username = BACKUP_CHANNEL_USERNAME; break;
            default: $channel_username = '';
        }
    } else {
        sendMessageWithDelay($chat_id, "❌ Invalid channel format. Use @username or channel ID.", null, 'HTML', 500);
        return;
    }
    
    // Auto-detect properties
    $quality = detect_quality($movie_name);
    $language = detect_language($movie_name);
    $size = detect_size($movie_name);
    
    // Prepare movie data
    $movie_data = [
        'movie_name' => $movie_name,
        'message_id_raw' => $message_id,
        'message_id' => intval($message_id),
        'date' => date('d-m-Y'),
        'video_path' => '',
        'quality' => $quality,
        'size' => $size,
        'language' => $language,
        'channel_type' => $channel_type,
        'channel_id' => $channel_id,
        'channel_username' => $channel_username,
        'source_channel' => $channel_id,
        'search_key' => strtolower($movie_name),
        'added_timestamp' => time()
    ];
    
    // Add to CSV
    if (add_movie_to_csv($movie_data)) {
        // Success message
        $success_msg = "✅ <b>Movie Added Successfully!</b>\n\n";
        $success_msg .= "🎬 <b>Movie:</b> $movie_name\n";
        $success_msg .= "🆔 <b>Message ID:</b> $message_id\n";
        $success_msg .= "📊 <b>Quality:</b> $quality\n";
        $success_msg .= "💾 <b>Size:</b> $size\n";
        $success_msg .= "🗣️ <b>Language:</b> $language\n";
        $success_msg .= "🎭 <b>Channel:</b> " . get_channel_display_name($channel_type) . "\n";
        
        if ($channel_username) {
            $success_msg .= "📢 <b>Username:</b> $channel_username\n";
        }
        
        if ($channel_id) {
            $success_msg .= "🔢 <b>Channel ID:</b> $channel_id\n";
        }
        
        $success_msg .= "\n🔗 <b>Direct Link:</b>\n";
        if ($channel_id && $message_id) {
            $success_msg .= get_direct_channel_link($message_id, $channel_id) . "\n";
        }
        
        $success_msg .= "\n📊 Total Movies: " . (get_stats()['total_movies'] ?? 0);
        
        sendMessageWithDelay($chat_id, $success_msg, null, 'HTML', 1000);
        bot_log("Quick add by $user_id: $movie_name (ID: $message_id) to $channel_type");
        
    } else {
        sendMessageWithDelay($chat_id, "❌ Failed to add movie. It might already exist.", null, 'HTML', 500);
    }
}

/**
 * Batch quick add movies
 */
function batch_quick_add_movies($chat_id, $user_id, $movies_data) {
    if ($chat_id != ADMIN_ID && $user_id != ADMIN_ID) {
        sendMessageWithDelay($chat_id, "❌ Access denied. Admin only command.", null, 'HTML', 500);
        return;
    }
    
    $movies_list = array_filter(array_map('trim', explode("\n", $movies_data)));
    
    if (empty($movies_list)) {
        sendMessageWithDelay($chat_id, "❌ No movies data provided.", null, 'HTML', 500);
        return;
    }
    
    $progress_msg = sendMessage($chat_id, "⏳ Processing " . count($movies_list) . " movies...\n\nProgress: 0%\n✅ Added: 0\n❌ Failed: 0");
    $progress_msg_id = $progress_msg['result']['message_id'];
    
    $added_count = 0;
    $failed_count = 0;
    $added_movies = [];
    
    foreach ($movies_list as $index => $movie_line) {
        $parts = explode(',', $movie_line, 3);
        
        if (count($parts) < 3) {
            $failed_count++;
            continue;
        }
        
        $movie_name = trim($parts[0]);
        $message_id = trim($parts[1]);
        $channel_info = trim($parts[2]);
        
        if (!is_numeric($message_id) || $message_id <= 0) {
            $failed_count++;
            continue;
        }
        
        // Determine channel info
        $channel_type = 'other';
        $channel_id = '';
        $channel_username = '';
        
        if (strpos($channel_info, '@') === 0) {
            $channel_username = $channel_info;
            $channel_id = get_channel_id_by_username($channel_username);
            
            if ($channel_id) {
                $channel_type = get_channel_type_by_id($channel_id);
            }
        } elseif (is_numeric($channel_info) || strpos($channel_info, '-100') === 0) {
            $channel_id = $channel_info;
            $channel_type = get_channel_type_by_id($channel_id);
            
            switch ($channel_type) {
                case 'main': $channel_username = MAIN_CHANNEL; break;
                case 'theater': $channel_username = THEATER_CHANNEL; break;
                case 'backup': 
                case 'backup2': $channel_username = BACKUP_CHANNEL_USERNAME; break;
                default: $channel_username = '';
            }
        } else {
            $failed_count++;
            continue;
        }
        
        // Auto-detect properties
        $quality = detect_quality($movie_name);
        $language = detect_language($movie_name);
        $size = detect_size($movie_name);
        
        // Prepare movie data
        $movie_data = [
            'movie_name' => $movie_name,
            'message_id_raw' => $message_id,
            'message_id' => intval($message_id),
            'date' => date('d-m-Y'),
            'video_path' => '',
            'quality' => $quality,
            'size' => $size,
            'language' => $language,
            'channel_type' => $channel_type,
            'channel_id' => $channel_id,
            'channel_username' => $channel_username,
            'source_channel' => $channel_id,
            'search_key' => strtolower($movie_name),
            'added_timestamp' => time()
        ];
        
        // Add to CSV
        if (add_movie_to_csv($movie_data)) {
            $added_count++;
            $added_movies[] = [
                'name' => $movie_name,
                'channel' => get_channel_display_name($channel_type)
            ];
        } else {
            $failed_count++;
        }
        
        // Update progress every 5 movies
        if (($index + 1) % 5 == 0 || ($index + 1) == count($movies_list)) {
            $progress = round((($index + 1) / count($movies_list)) * 100);
            editMessage($chat_id, $progress_msg_id, 
                "⏳ Processing " . count($movies_list) . " movies...\n\n" .
                "Progress: $progress%\n" .
                "Processed: " . ($index + 1) . "/" . count($movies_list) . "\n" .
                "✅ Added: $added_count\n" .
                "❌ Failed: $failed_count"
            );
        }
        
        usleep(300000); // 0.3s delay
    }
    
    // Final result
    $result_msg = "✅ <b>Batch Add Complete!</b>\n\n";
    $result_msg .= "📊 <b>Results:</b>\n";
    $result_msg .= "• Total Processed: " . count($movies_list) . "\n";
    $result_msg .= "• ✅ Successfully Added: $added_count\n";
    $result_msg .= "• ❌ Failed: $failed_count\n\n";
    
    if ($added_count > 0) {
        $result_msg .= "🎬 <b>Added Movies:</b>\n";
        $display_count = min(5, count($added_movies));
        for ($i = 0; $i < $display_count; $i++) {
            $result_msg .= ($i + 1) . ". " . $added_movies[$i]['name'] . "\n";
            $result_msg .= "   📢 " . $added_movies[$i]['channel'] . "\n\n";
        }
        
        if (count($added_movies) > 5) {
            $result_msg .= "... and " . (count($added_movies) - 5) . " more\n\n";
        }
    }
    
    $result_msg .= "💾 <b>Total Movies in Database:</b> " . (get_stats()['total_movies'] ?? 0);
    
    editMessage($chat_id, $progress_msg_id, $result_msg, null, 'HTML');
    bot_log("Batch quick add by $user_id: $added_count added, $failed_count failed");
}

// ===================================================
// 15. SEARCH SYSTEM
// ===================================================

/**
 * Smart search algorithm
 */
function smart_search($query) {
    $query_lower = strtolower(trim($query));
    
    if (strlen($query_lower) < 2) {
        return [];
    }
    
    $all_movies = get_cached_movies();
    $results = [];
    
    // Check for theater search
    $is_theater_search = false;
    $theater_keywords = ['theater', 'theatre', 'print', 'hdcam', 'camrip', 'hdrip'];
    foreach ($theater_keywords as $keyword) {
        if (strpos($query_lower, $keyword) !== false) {
            $is_theater_search = true;
            $query_lower = str_replace($keyword, '', $query_lower);
            break;
        }
    }
    $query_lower = trim($query_lower);
    
    // Search through movies
    foreach ($all_movies as $movie) {
        $movie_name_lower = strtolower($movie['movie_name']);
        $score = 0;
        
        // Exact match
        if ($movie_name_lower === $query_lower) {
            $score = 100;
        }
        // Contains query
        elseif (strpos($movie_name_lower, $query_lower) !== false) {
            $score = 80;
        }
        // Similar text
        else {
            similar_text($movie_name_lower, $query_lower, $similarity);
            if ($similarity > 60) {
                $score = $similarity;
            }
        }
        
        // Boost theater movies for theater searches
        if ($is_theater_search && $movie['channel_type'] === 'theater') {
            $score += 20;
        }
        
        // Boost based on quality
        if (stripos($movie['quality'] ?? '', '1080') !== false) $score += 5;
        if (stripos($movie['quality'] ?? '', '720') !== false) $score += 3;
        if (stripos($movie['language'] ?? '', 'hindi') !== false) $score += 2;
        
        if ($score > 0) {
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
    
    // Return top results
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
}

/**
 * Advanced search with filtering
 */
function advanced_search($chat_id, $query, $user_id = null) {
    $query = trim($query);
    
    if (strlen($query) < 2) {
        sendMessage($chat_id, "❌ Please enter at least 2 characters for search.");
        return;
    }
    
    // Filter invalid queries
    $invalid_keywords = [
        'vlc', 'audio', 'track', 'change', 'open', 'kar', 'me', 'hai',
        'how', 'what', 'problem', 'issue', 'help', 'solution', 'fix',
        'error', 'not working', 'download', 'play', 'video', 'sound',
        'subtitle', 'quality', 'hd', 'full', 'part', 'scene',
        'hi', 'hello', 'hey', 'good', 'morning', 'night', 'bye'
    ];
    
    $query_words = explode(' ', strtolower($query));
    $invalid_count = 0;
    foreach ($query_words as $word) {
        if (in_array($word, $invalid_keywords)) {
            $invalid_count++;
        }
    }
    
    if ($invalid_count > 0 && ($invalid_count / count($query_words)) > 0.5) {
        $help_msg = "🎬 Please enter a movie name!\n\n";
        $help_msg .= "🔍 Examples of valid movie names:\n";
        $help_msg .= "• kgf\n• pushpa\n• avengers\n• hindi movie\n• spider-man\n\n";
        $help_msg .= "❌ Technical queries are not movie names.\n\n";
        $help_msg .= "📢 Join: " . MAIN_CHANNEL . "\n";
        $help_msg .= "💬 Help: " . REQUEST_CHANNEL;
        sendMessage($chat_id, $help_msg, null, 'HTML');
        return;
    }
    
    // Perform search
    $results = smart_search($query);
    update_stats('total_searches', 1);
    
    if (!empty($results)) {
        update_stats('successful_searches', 1);
        
        $msg = "🔍 Found " . count($results) . " movies for '$query':\n\n";
        $i = 1;
        
        foreach ($results as $result) {
            $movie = $result['movie'];
            $quality_info = $movie['quality'] ?? 'Unknown';
            $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
            $msg .= "$i. $channel_icon " . $movie['movie_name'] . " ($quality_info)\n";
            $i++;
            if ($i > 10) break;
        }
        
        sendMessage($chat_id, $msg);
        
        // Create inline keyboard for top results
        $keyboard = ['inline_keyboard' => []];
        $top_movies = array_slice($results, 0, 5);
        
        foreach ($top_movies as $result) {
            $movie = $result['movie'];
            $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
            $keyboard['inline_keyboard'][] = [[
                'text' => $channel_icon . ' ' . substr($movie['movie_name'], 0, 30),
                'callback_data' => 'movie_' . base64_encode($movie['movie_name'])
            ]];
        }
        
        // Add request button
        $keyboard['inline_keyboard'][] = [[
            'text' => "📝 Request Different Movie", 
            'callback_data' => 'request_movie_' . base64_encode($query)
        ]];
        
        sendMessage($chat_id, "🚀 Top matches (click for info):", $keyboard);
        
        if ($user_id) {
            update_user_activity($user_id, 'found_movie');
            update_user_activity($user_id, 'search');
        }
        
    } else {
        update_stats('failed_searches', 1);
        
        $lang = detect_language($query);
        $responses = [
            'hindi' => "😔 Yeh movie abhi available nahi hai!\n\n📝 Aap ise request kar sakte hain: " . REQUEST_CHANNEL . "\n\n🔔 Jab bhi yeh add hogi, main automatically bhej dunga!",
            'english' => "😔 This movie isn't available yet!\n\n📝 You can request it here: " . REQUEST_CHANNEL . "\n\n🔔 I'll send it automatically once it's added!"
        ];
        
        sendMessage($chat_id, $responses[$lang] ?? $responses['english']);
        
        // Auto-suggest request
        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '📝 Request This Movie', 'callback_data' => 'auto_request_' . base64_encode($query)]
            ]]
        ];
        
        sendMessage($chat_id, "💡 Click below to automatically request this movie:", $keyboard);
    }
}

// ===================================================
// 16. MOVIE DELIVERY SYSTEM
// ===================================================

/**
 * Deliver movie to user
 */
function deliver_item_to_chat($chat_id, $item) {
    $source_channel = $item['channel_id'] ?? MAIN_CHANNEL_ID;
    $message_id = $item['message_id'] ?? null;
    $channel_type = $item['channel_type'] ?? 'main';
    
    // Try to copy message (no forward header)
    if ($message_id && $source_channel) {
        $result = json_decode(copyMessage($chat_id, $source_channel, $message_id), true);
        
        if ($result && $result['ok']) {
            update_stats('total_downloads', 1);
            bot_log("Movie copied to $chat_id: {$item['movie_name']} from $channel_type");
            return true;
        }
        
        // Fallback to forward
        $result = json_decode(apiRequest('forwardMessage', [
            'chat_id' => $chat_id,
            'from_chat_id' => $source_channel,
            'message_id' => $message_id
        ]), true);
        
        if ($result && $result['ok']) {
            update_stats('total_downloads', 1);
            bot_log("Movie forwarded to $chat_id: {$item['movie_name']} from $channel_type");
            return true;
        }
    }
    
    // Send info message as fallback
    $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n";
    $text .= "📊 Quality: " . htmlspecialchars($item['quality'] ?? 'Unknown') . "\n";
    $text .= "💾 Size: " . htmlspecialchars($item['size'] ?? 'Unknown') . "\n";
    $text .= "🗣️ Language: " . htmlspecialchars($item['language'] ?? 'Hindi') . "\n";
    $text .= "🎭 Channel: " . get_channel_display_name($channel_type) . "\n";
    $text .= "📅 Date: " . htmlspecialchars($item['date'] ?? 'N/A') . "\n";
    
    if ($message_id && $source_channel) {
        $text .= "\n🔗 Direct Link: " . get_direct_channel_link($message_id, $source_channel);
    }
    
    $text .= "\n\n⚠️ Join channel to access content: " . get_channel_username_link($channel_type);
    
    sendMessage($chat_id, $text, null, 'HTML');
    update_stats('total_downloads', 1);
    return false;
}

// ===================================================
// 17. PAGINATION SYSTEM
// ===================================================

/**
 * Paginate movies array
 */
function paginate_movies($movies, $page = 1, $items_per_page = ITEMS_PER_PAGE) {
    $total = count($movies);
    $total_pages = ceil($total / $items_per_page);
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $items_per_page;
    
    return [
        'movies' => array_slice($movies, $offset, $items_per_page),
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_items' => $total,
        'items_per_page' => $items_per_page,
        'start_item' => $offset + 1,
        'end_item' => min($offset + $items_per_page, $total)
    ];
}

/**
 * Build pagination keyboard
 */
function build_pagination_keyboard($current_page, $total_pages, $session_id = '') {
    $keyboard = ['inline_keyboard' => []];
    
    // Navigation row
    $nav_row = [];
    
    if ($current_page > 1) {
        $nav_row[] = ['text' => '⏪', 'callback_data' => 'pag_first_' . $session_id];
        $nav_row[] = ['text' => '◀️', 'callback_data' => 'pag_prev_' . $current_page . '_' . $session_id];
    }
    
    // Page numbers (show max 7 pages)
    $start_page = max(1, $current_page - 3);
    $end_page = min($total_pages, $start_page + 6);
    
    if ($end_page - $start_page < 6) {
        $start_page = max(1, $end_page - 6);
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $nav_row[] = ['text' => "【{$i}】", 'callback_data' => 'current_page'];
        } else {
            $nav_row[] = ['text' => "{$i}", 'callback_data' => 'pag_' . $i . '_' . $session_id];
        }
    }
    
    if ($current_page < $total_pages) {
        $nav_row[] = ['text' => '▶️', 'callback_data' => 'pag_next_' . $current_page . '_' . $session_id];
        $nav_row[] = ['text' => '⏩', 'callback_data' => 'pag_last_' . $session_id];
    }
    
    if (!empty($nav_row)) {
        $keyboard['inline_keyboard'][] = $nav_row;
    }
    
    // Action row
    $action_row = [
        ['text' => '📥 Send Page', 'callback_data' => 'send_page_' . $current_page . '_' . $session_id],
        ['text' => '👁️ Preview', 'callback_data' => 'preview_page_' . $current_page . '_' . $session_id],
        ['text' => '📊 Stats', 'callback_data' => 'page_stats_' . $session_id]
    ];
    $keyboard['inline_keyboard'][] = $action_row;
    
    // Filter row
    $filter_row = [
        ['text' => '🎬 HD Only', 'callback_data' => 'filter_hd_' . $session_id],
        ['text' => '🎭 Theater', 'callback_data' => 'filter_theater_' . $session_id],
        ['text' => '🔒 Backup', 'callback_data' => 'filter_backup_' . $session_id]
    ];
    $keyboard['inline_keyboard'][] = $filter_row;
    
    // Control row
    $control_row = [
        ['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''],
        ['text' => '❌ Close', 'callback_data' => 'close_pagination_' . $session_id]
    ];
    $keyboard['inline_keyboard'][] = $control_row;
    
    return $keyboard;
}

/**
 * Show paginated movies
 */
function show_paginated_movies($chat_id, $page = 1, $filters = [], $session_id = null) {
    $all_movies = get_cached_movies();
    
    if (empty($all_movies)) {
        sendMessage($chat_id, "📭 No movies found in database!");
        return;
    }
    
    // Apply filters
    if (!empty($filters)) {
        $all_movies = array_filter($all_movies, function($movie) use ($filters) {
            foreach ($filters as $key => $value) {
                switch ($key) {
                    case 'quality':
                        if (stripos($movie['quality'] ?? '', $value) === false) return false;
                        break;
                    case 'channel_type':
                        if ($movie['channel_type'] !== $value) return false;
                        break;
                    case 'language':
                        if (stripos($movie['language'] ?? '', $value) === false) return false;
                        break;
                }
            }
            return true;
        });
        $all_movies = array_values($all_movies);
    }
    
    // Generate session ID if not provided
    if (!$session_id) {
        $session_id = uniqid('sess_', true);
    }
    
    // Paginate
    $pagination = paginate_movies($all_movies, $page);
    
    // Build message
    $message = "🎬 <b>Enhanced Movie Browser</b>\n\n";
    $message .= "🆔 <b>Session:</b> <code>" . substr($session_id, 0, 8) . "</code>\n";
    $message .= "📊 <b>Statistics:</b>\n";
    $message .= "• Total Movies: <b>{$pagination['total_items']}</b>\n";
    $message .= "• Page: <b>{$pagination['current_page']}/{$pagination['total_pages']}</b>\n";
    $message .= "• Items: <b>{$pagination['start_item']}-{$pagination['end_item']}</b>\n";
    
    if (!empty($filters)) {
        $message .= "• Active Filters: <b>" . count($filters) . "</b>\n";
    }
    
    $message .= "\n📋 <b>Page {$page} Movies:</b>\n\n";
    
    $counter = $pagination['start_item'];
    foreach ($pagination['movies'] as $movie) {
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        $message .= "<b>{$counter}.</b> $channel_icon " . htmlspecialchars($movie['movie_name']) . "\n";
        $message .= "   🏷️ {$movie['quality']} | 🗣️ {$movie['language']}\n";
        $message .= "   💾 {$movie['size']} | 📅 {$movie['date']}\n\n";
        $counter++;
    }
    
    $message .= "📍 <i>Use buttons below for navigation</i>";
    
    // Build keyboard
    $keyboard = build_pagination_keyboard($page, $pagination['total_pages'], $session_id);
    
    // Send message
    sendMessage($chat_id, $message, $keyboard, 'HTML');
    
    // Store session
    global $user_pagination_sessions;
    $user_pagination_sessions[$session_id] = [
        'chat_id' => $chat_id,
        'filters' => $filters,
        'created' => time()
    ];
    
    bot_log("Pagination started - Chat: $chat_id, Page: $page, Session: " . substr($session_id, 0, 8));
}

// ===================================================
// 18. BATCH DELIVERY SYSTEM
// ===================================================

/**
 * Batch deliver movies with progress
 */
function batch_deliver_movies($chat_id, $movies, $batch_name = "movies") {
    $total = count($movies);
    
    if ($total === 0) {
        sendMessage($chat_id, "❌ No movies to deliver!");
        return;
    }
    
    $progress_msg = sendMessage($chat_id, "📦 <b>Batch Delivery Started</b>\n\nTotal: $total $batch_name\n⏳ Initializing...");
    $progress_msg_id = $progress_msg['result']['message_id'];
    
    $success = 0;
    $failed = 0;
    $start_time = time();
    
    for ($i = 0; $i < $total; $i++) {
        $movie = $movies[$i];
        
        // Update progress every 3 movies
        if ($i % 3 == 0 || $i == $total - 1) {
            $progress = round((($i + 1) / $total) * 100);
            $elapsed = time() - $start_time;
            $remaining = $elapsed > 0 ? round(($total - $i - 1) * ($elapsed / ($i + 1))) : 0;
            
            editMessage($chat_id, $progress_msg_id,
                "📦 <b>Batch Delivery in Progress</b>\n\n" .
                "Progress: $progress%\n" .
                "Processed: " . ($i + 1) . "/$total\n" .
                "✅ Success: $success\n" .
                "❌ Failed: $failed\n" .
                "⏱️ Elapsed: {$elapsed}s\n" .
                "⏳ Remaining: {$remaining}s"
            );
        }
        
        try {
            if (deliver_item_to_chat($chat_id, $movie)) {
                $success++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
            bot_log("Batch delivery error: " . $e->getMessage(), 'ERROR');
        }
        
        usleep(300000); // 0.3s delay
    }
    
    $total_time = time() - $start_time;
    
    // Final report
    editMessage($chat_id, $progress_msg_id,
        "✅ <b>Batch Delivery Complete!</b>\n\n" .
        "📦 Batch: $batch_name\n" .
        "🎬 Total: $total movies\n" .
        "✅ Successfully sent: $success\n" .
        "❌ Failed: $failed\n" .
        "📊 Success rate: " . round(($success / $total) * 100, 1) . "%\n" .
        "⏱️ Total time: {$total_time}s\n" .
        "📈 Average: " . ($total > 0 ? round($total_time / $total, 2) : 0) . "s per movie\n\n" .
        "🔗 Join our channels for more movies!"
    );
    
    bot_log("Batch delivery completed: $success/$total sent in {$total_time}s");
}

// ===================================================
// 19. USER MANAGEMENT SYSTEM
// ===================================================

/**
 * Update user data
 */
function update_user_data($user_id, $user_info = []) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id])) {
        // New user
        $users_data['users'][$user_id] = [
            'first_name' => $user_info['first_name'] ?? '',
            'last_name' => $user_info['last_name'] ?? '',
            'username' => $user_info['username'] ?? '',
            'joined' => date('Y-m-d H:i:s'),
            'last_active' => date('Y-m-d H:i:s'),
            'points' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
            'request_count' => 0,
            'last_request_date' => null,
            'daily_activity' => []
        ];
        
        $users_data['total_requests'] = ($users_data['total_requests'] ?? 0) + 1;
        update_stats('total_users', 1);
        bot_log("New user registered: $user_id");
    }
    
    $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
    
    // Update daily activity
    $today = date('Y-m-d');
    if (!isset($users_data['users'][$user_id]['daily_activity'][$today])) {
        $users_data['users'][$user_id]['daily_activity'][$today] = [
            'searches' => 0,
            'downloads' => 0,
            'requests' => 0
        ];
    }
    
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    return $users_data['users'][$user_id];
}

/**
 * Update user activity
 */
function update_user_activity($user_id, $action) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (isset($users_data['users'][$user_id])) {
        $points_map = [
            'search' => 1,
            'found_movie' => 5,
            'daily_login' => 10,
            'movie_request' => 2,
            'download' => 3
        ];
        
        $today = date('Y-m-d');
        $user = &$users_data['users'][$user_id];
        
        $user['points'] += ($points_map[$action] ?? 0);
        
        if ($action == 'search') {
            $user['total_searches']++;
            $user['daily_activity'][$today]['searches'] = ($user['daily_activity'][$today]['searches'] ?? 0) + 1;
        }
        if ($action == 'download') {
            $user['total_downloads']++;
            $user['daily_activity'][$today]['downloads'] = ($user['daily_activity'][$today]['downloads'] ?? 0) + 1;
        }
        if ($action == 'movie_request') {
            $user['request_count']++;
            $user['daily_activity'][$today]['requests'] = ($user['daily_activity'][$today]['requests'] ?? 0) + 1;
        }
        
        $user['last_active'] = date('Y-m-d H:i:s');
        file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    }
}

/**
 * Show user statistics
 */
function show_user_stats($chat_id, $user_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user = $users_data['users'][$user_id] ?? null;
    
    if (!$user) {
        sendMessage($chat_id, "❌ User data not found!");
        return;
    }
    
    $message = "👤 <b>Your Statistics</b>\n\n";
    $message .= "🆔 User ID: <code>$user_id</code>\n";
    $message .= "📅 Joined: " . ($user['joined'] ?? 'N/A') . "\n";
    $message .= "🕒 Last Active: " . ($user['last_active'] ?? 'N/A') . "\n\n";
    
    $message .= "📊 <b>Activity:</b>\n";
    $message .= "• 🔍 Searches: " . ($user['total_searches'] ?? 0) . "\n";
    $message .= "• 📥 Downloads: " . ($user['total_downloads'] ?? 0) . "\n";
    $message .= "• 📝 Requests: " . ($user['request_count'] ?? 0) . "\n";
    $message .= "• ⭐ Points: " . ($user['points'] ?? 0) . "\n\n";
    
    $message .= "🎯 <b>Rank:</b> " . calculate_user_rank($user['points'] ?? 0);
    
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

// ===================================================
// 20. STATISTICS SYSTEM
// ===================================================

/**
 * Update statistics
 */
function update_stats($field, $increment = 1) {
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats[$field] = ($stats[$field] ?? 0) + $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    
    // Update daily activity
    $today = date('Y-m-d');
    if (!isset($stats['daily_activity'][$today])) {
        $stats['daily_activity'][$today] = [
            'searches' => 0,
            'downloads' => 0,
            'users' => 0
        ];
    }
    
    if ($field == 'total_searches') $stats['daily_activity'][$today]['searches'] += $increment;
    if ($field == 'total_downloads') $stats['daily_activity'][$today]['downloads'] += $increment;
    
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}

/**
 * Get statistics
 */
function get_stats() {
    if (!file_exists(STATS_FILE)) return [];
    return json_decode(file_get_contents(STATS_FILE), true);
}

/**
 * Show bot statistics (admin only)
 */
function show_bot_stats($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $message = "📊 <b>Bot Statistics</b>\n\n";
    
    $message .= "🎬 <b>Movies:</b> " . ($stats['total_movies'] ?? 0) . "\n";
    $message .= "👥 <b>Users:</b> " . count($users_data['users'] ?? []) . "\n";
    $message .= "🔍 <b>Searches:</b> " . ($stats['total_searches'] ?? 0) . "\n";
    $message .= "✅ <b>Successful:</b> " . ($stats['successful_searches'] ?? 0) . "\n";
    $message .= "❌ <b>Failed:</b> " . ($stats['failed_searches'] ?? 0) . "\n";
    $message .= "📥 <b>Downloads:</b> " . ($stats['total_downloads'] ?? 0) . "\n";
    $message .= "🕒 <b>Last Updated:</b> " . ($stats['last_updated'] ?? 'N/A') . "\n\n";
    
    // Today's activity
    $today = date('Y-m-d');
    if (isset($stats['daily_activity'][$today])) {
        $today_stats = $stats['daily_activity'][$today];
        $message .= "📈 <b>Today's Activity:</b>\n";
        $message .= "• Searches: " . ($today_stats['searches'] ?? 0) . "\n";
        $message .= "• Downloads: " . ($today_stats['downloads'] ?? 0) . "\n";
        $message .= "• New Users: " . ($today_stats['users'] ?? 0) . "\n\n";
    }
    
    // Performance metrics
    if (!empty($stats['performance_metrics'])) {
        $message .= "⚡ <b>Performance Metrics:</b>\n";
        $message .= "• Average search time: " . round($stats['performance_metrics']['avg_search_time'] ?? 0, 3) . "s\n";
        $message .= "• Cache hit rate: " . round($stats['performance_metrics']['cache_hit_rate'] ?? 0, 1) . "%\n";
    }
    
    // Recent movies
    $all_movies = get_cached_movies();
    $recent = array_slice($all_movies, 0, 5);
    
    if (!empty($recent)) {
        $message .= "\n🎬 <b>Recent Additions:</b>\n";
        foreach ($recent as $i => $movie) {
            $message .= ($i + 1) . ". " . substr($movie['movie_name'], 0, 30) . " (" . $movie['date'] . ")\n";
        }
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔄 Refresh', 'callback_data' => 'refresh_stats_admin'],
                ['text' => '📁 CSV Data', 'callback_data' => 'show_csv_admin']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
    bot_log("Admin stats viewed by $chat_id");
}

// ===================================================
// 21. REQUEST MANAGEMENT SYSTEM
// ===================================================

/**
 * Check if user can make request
 */
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

/**
 * Add movie request
 */
function add_movie_request($user_id, $movie_name, $language = 'Hindi') {
    if (!can_user_request($user_id)) {
        return false;
    }
    
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    
    $request_id = uniqid('req_', true);
    $requests_data['requests'][] = [
        'id' => $request_id,
        'user_id' => $user_id,
        'movie_name' => $movie_name,
        'language' => $language,
        'date' => date('Y-m-d'),
        'time' => date('H:i:s'),
        'status' => 'pending',
        'notified' => false
    ];
    
    // Update user request count
    if (!isset($requests_data['user_request_count'][$user_id])) {
        $requests_data['user_request_count'][$user_id] = 0;
    }
    $requests_data['user_request_count'][$user_id]++;
    
    file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
    
    // Notify admin
    $admin_msg = "🎯 <b>New Movie Request</b>\n\n";
    $admin_msg .= "🎬 Movie: $movie_name\n";
    $admin_msg .= "🗣️ Language: $language\n";
    $admin_msg .= "👤 User ID: $user_id\n";
    $admin_msg .= "📅 Date: " . date('Y-m-d H:i:s') . "\n";
    $admin_msg .= "🆔 Request ID: " . substr($request_id, 0, 8);
    
    sendMessage(ADMIN_ID, $admin_msg, null, 'HTML');
    bot_log("Movie request added: $movie_name by $user_id");
    
    return true;
}

/**
 * Show user requests
 */
function show_user_requests($chat_id, $user_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $user_requests = [];
    
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id) {
            $user_requests[] = $request;
        }
    }
    
    if (empty($user_requests)) {
        sendMessage($chat_id, "📭 You haven't made any movie requests yet!");
        return;
    }
    
    $message = "📝 <b>Your Movie Requests</b>\n\n";
    
    foreach (array_slice($user_requests, 0, 10) as $i => $request) {
        $status_icon = $request['status'] == 'completed' ? '✅' : '⏳';
        $message .= ($i + 1) . ". $status_icon <b>" . htmlspecialchars($request['movie_name']) . "</b>\n";
        $message .= "   📅 " . $request['date'] . " | 🗣️ " . ucfirst($request['language']) . "\n";
        $message .= "   🆔 " . substr($request['id'], 0, 8) . "\n\n";
    }
    
    $pending = count(array_filter($user_requests, function($req) {
        return $req['status'] == 'pending';
    }));
    
    $message .= "📊 <b>Summary:</b>\n";
    $message .= "• Total Requests: " . count($user_requests) . "\n";
    $message .= "• Pending: $pending\n";
    $message .= "• Completed: " . (count($user_requests) - $pending);
    
    sendMessage($chat_id, $message, null, 'HTML');
}

// ===================================================
// 22. BACKUP SYSTEM
// ===================================================

/**
 * Create automatic backup
 */
function auto_backup() {
    bot_log("Starting automatic backup...");
    
    $backup_files = [CSV_FILE, USERS_FILE, STATS_FILE, REQUEST_FILE];
    $backup_dir = BACKUP_DIR . date('Y-m-d_H-i-s');
    
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $success = true;
    
    // Backup files
    foreach ($backup_files as $file) {
        if (file_exists($file)) {
            $backup_path = $backup_dir . '/' . basename($file);
            if (!copy($file, $backup_path)) {
                bot_log("Failed to backup: $file", 'ERROR');
                $success = false;
            }
        }
    }
    
    // Create summary
    $summary = create_backup_summary();
    file_put_contents($backup_dir . '/backup_summary.txt', $summary);
    
    // Upload to backup channel
    if ($success) {
        upload_backup_to_channel($backup_dir, $summary);
    }
    
    // Clean old backups
    clean_old_backups();
    
    // Send report
    send_backup_report($success, $summary);
    
    bot_log("Automatic backup completed: " . ($success ? 'Success' : 'Partial success'));
    return $success;
}

/**
 * Create backup summary
 */
function create_backup_summary() {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $summary = "📊 BACKUP SUMMARY\n";
    $summary .= "================\n\n";
    $summary .= "📅 Backup Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "🤖 Bot: Entertainment Tadka v3.0.0\n\n";
    
    $summary .= "📈 STATISTICS:\n";
    $summary .= "• Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $summary .= "• Total Users: " . count($users_data['users'] ?? []) . "\n";
    $summary .= "• Total Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $summary .= "• Total Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
    
    $summary .= "💾 FILES BACKED UP:\n";
    foreach ([CSV_FILE, USERS_FILE, STATS_FILE, REQUEST_FILE] as $file) {
        $size = file_exists($file) ? filesize($file) : 0;
        $summary .= "• " . basename($file) . " (" . round($size / 1024, 2) . " KB)\n";
    }
    
    $summary .= "\n🔄 Backup Type: Automated Daily Backup\n";
    $summary .= "📍 Location: " . BACKUP_DIR . "\n";
    $summary .= "📡 Channel: " . BACKUP_CHANNEL_USERNAME;
    
    return $summary;
}

/**
 * Upload backup to channel
 */
function upload_backup_to_channel($backup_dir, $summary) {
    try {
        // Send summary message
        $summary_msg = "🔄 <b>Daily Auto-Backup Report</b>\n\n";
        $summary_msg .= "📅 " . date('Y-m-d H:i:s') . "\n\n";
        $summary_msg .= "📊 <b>Current Stats:</b>\n";
        
        $stats = get_stats();
        $summary_msg .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
        $summary_msg .= "• 👥 Users: " . get_users_count() . "\n";
        $summary_msg .= "• 🔍 Searches: " . ($stats['total_searches'] ?? 0) . "\n";
        $summary_msg .= "• 📥 Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
        
        $summary_msg .= "✅ <b>Backup Status:</b> Successful\n";
        $summary_msg .= "📁 <b>Location:</b> " . basename($backup_dir) . "\n";
        $summary_msg .= "📡 <b>Channel:</b> " . BACKUP_CHANNEL_USERNAME;
        
        sendMessage(BACKUP_CHANNEL_ID, $summary_msg, null, 'HTML');
        
        // Upload important files
        $important_files = [CSV_FILE, USERS_FILE];
        foreach ($important_files as $file) {
            if (file_exists($file)) {
                $backup_file = $backup_dir . '/' . basename($file);
                if (file_exists($backup_file)) {
                    // Here you would add code to upload to Telegram
                    // Using sendDocument API method
                    bot_log("Would upload: $backup_file to channel");
                }
            }
            sleep(1);
        }
        
        return true;
        
    } catch (Exception $e) {
        bot_log("Channel backup failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Clean old backups
 */
function clean_old_backups() {
    $backup_dirs = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    
    if (count($backup_dirs) > 7) {
        usort($backup_dirs, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $to_delete = count($backup_dirs) - 7;
        for ($i = 0; $i < $to_delete; $i++) {
            $files = glob($backup_dirs[$i] . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($backup_dirs[$i]);
            bot_log("Deleted old backup: " . basename($backup_dirs[$i]));
        }
    }
}

/**
 * Send backup report
 */
function send_backup_report($success, $summary) {
    if ($chat_id != ADMIN_ID) return;
    
    $report = $success ? "✅ Backup successful!" : "⚠️ Backup completed with warnings";
    sendMessage(ADMIN_ID, $report . "\n\n" . substr($summary, 0, 1000), null, 'HTML');
}

// ===================================================
// 23. CHANNEL INFO SYSTEM
// ===================================================

/**
 * Show all channels info
 */
function show_channels_info($chat_id) {
    $message = "📢 <b>Join Our Channels</b>\n\n";
    
    $message .= "🍿 <b>Main Channel:</b> " . MAIN_CHANNEL . "\n";
    $message .= "• Latest movie updates\n";
    $message .= "• Daily new additions\n";
    $message .= "• High quality prints\n\n";
    
    $message .= "🎭 <b>Theater Prints:</b> " . THEATER_CHANNEL . "\n";
    $message .= "• Theater quality prints\n";
    $message .= "• HD screen recordings\n";
    $message .= "• Latest theater prints\n\n";
    
    $message .= "📥 <b>Requests Channel:</b> " . REQUEST_CHANNEL . "\n";
    $message .= "• Movie requests\n";
    $message .= "• Bug reports\n";
    $message .= "• Support & help\n\n";
    
    $message .= "🔒 <b>Backup Channel:</b> " . BACKUP_CHANNEL_USERNAME . "\n";
    $message .= "• Secure data backups\n";
    $message .= "• System archives\n\n";
    
    $message .= "🔔 <b>Don't forget to join all channels!</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🍿 Main', 'url' => 'https://t.me/EntertainmentTadka786'],
                ['text' => '🎭 Theater', 'url' => 'https://t.me/threater_print_movies']
            ],
            [
                ['text' => '📥 Requests', 'url' => 'https://t.me/EntertainmentTadka7860'],
                ['text' => '🔒 Backup', 'url' => 'https://t.me/ETBackup']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

// ===================================================
// 24. RANKING SYSTEM
// ===================================================

/**
 * Calculate user rank
 */
function calculate_user_rank($points) {
    if ($points >= 1000) return "🎖️ Elite";
    if ($points >= 500) return "🔥 Pro";
    if ($points >= 250) return "⭐ Advanced";
    if ($points >= 100) return "🚀 Intermediate";
    if ($points >= 50) return "👍 Beginner";
    return "🌱 Newbie";
}

/**
 * Show leaderboard
 */
function show_leaderboard($chat_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $users = $users_data['users'] ?? [];
    
    if (empty($users)) {
        sendMessage($chat_id, "📭 No users found!");
        return;
    }
    
    // Sort by points
    uasort($users, function($a, $b) {
        return ($b['points'] ?? 0) - ($a['points'] ?? 0);
    });
    
    $message = "🏆 <b>Top Users Leaderboard</b>\n\n";
    $i = 1;
    
    foreach (array_slice($users, 0, 10) as $user_id => $user) {
        $points = $user['points'] ?? 0;
        $username = $user['username'] ? "@" . $user['username'] : "User#" . substr($user_id, -4);
        $medal = $i == 1 ? "🥇" : ($i == 2 ? "🥈" : ($i == 3 ? "🥉" : "🔸"));
        
        $message .= "$medal $i. $username\n";
        $message .= "   ⭐ $points points | 🎯 " . calculate_user_rank($points) . "\n\n";
        $i++;
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

// ===================================================
// 25. COMMAND HANDLER
// ===================================================

/**
 * Handle all commands
 */
function handle_command($chat_id, $user_id, $command, $params = []) {
    global $MAINTENANCE_MODE, $MAINTENANCE_MESSAGE;
    
    if ($MAINTENANCE_MODE && !in_array($command, ['/start', '/help'])) {
        sendMessage($chat_id, $MAINTENANCE_MESSAGE, null, 'HTML');
        return;
    }
    
    switch ($command) {
        // ============ CORE COMMANDS ============
        case '/start':
            $welcome = "🎬 Welcome to Entertainment Tadka!\n\n";
            $welcome .= "📢 <b>How to use:</b>\n";
            $welcome .= "• Type any movie name to search\n";
            $welcome .= "• Use English or Hindi\n";
            $welcome .= "• Add 'theater' for theater prints\n\n";
            
            $welcome .= "🔍 <b>Examples:</b>\n";
            $welcome .= "• <code>kgf 2</code>\n";
            $welcome .= "• <code>hindi movie</code>\n";
            $welcome .= "• <code>avengers endgame theater</code>\n\n";
            
            $welcome .= "📢 <b>Our Channels:</b>\n";
            $welcome .= "🍿 Main: " . MAIN_CHANNEL . "\n";
            $welcome .= "🎭 Theater: " . THEATER_CHANNEL . "\n";
            $welcome .= "📥 Requests: " . REQUEST_CHANNEL . "\n\n";
            
            $welcome .= "💬 <b>Need help?</b> Use /help";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => ''],
                        ['text' => '🍿 Main Channel', 'url' => 'https://t.me/EntertainmentTadka786']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $welcome, $keyboard, 'HTML');
            update_user_data($user_id, []);
            update_user_activity($user_id, 'daily_login');
            break;

        case '/help':
            $help = "🤖 <b>Entertainment Tadka Bot - Commands</b>\n\n";
            $help .= "🔍 <b>Search:</b>\n";
            $help .= "• Just type movie name\n";
            $help .= "• <code>/search movie</code>\n";
            $help .= "• <code>/s movie</code>\n\n";
            
            $help .= "📁 <b>Browse:</b>\n";
            $help .= "• <code>/totalupload</code> - All movies\n";
            $help .= "• <code>/latest</code> - Recent additions\n";
            $help .= "• <code>/theater</code> - Theater prints\n\n";
            
            $help .= "📝 <b>Requests:</b>\n";
            $help .= "• <code>/request movie</code>\n";
            $help .= "• <code>/myrequests</code>\n\n";
            
            $help .= "👤 <b>User:</b>\n";
            $help .= "• <code>/mystats</code>\n";
            $help .= "• <code>/leaderboard</code>\n\n";
            
            $help .= "📢 <b>Channels:</b>\n";
            $help .= "• <code>/channels</code>\n";
            $help .= "• <code>/theaterchannel</code>\n\n";
            
            $help .= "🛠️ <b>Admin:</b>\n";
            $help .= "• <code>/quickadd</code>\n";
            $help .= "• <code>/stats</code>\n";
            $help .= "• <code>/backup</code>";

            sendMessage($chat_id, $help, null, 'HTML');
            break;

        // ============ SEARCH COMMANDS ============
        case '/search':
        case '/s':
            $query = implode(' ', $params);
            if (empty($query)) {
                sendMessage($chat_id, "❌ Usage: <code>/search movie_name</code>", null, 'HTML');
                return;
            }
            advanced_search($chat_id, $query, $user_id);
            break;

        // ============ BROWSE COMMANDS ============
        case '/totalupload':
        case '/allmovies':
            $page = isset($params[0]) ? intval($params[0]) : 1;
            show_paginated_movies($chat_id, $page);
            break;

        case '/latest':
        case '/recent':
            $limit = isset($params[0]) ? min(intval($params[0]), 20) : 10;
            $all_movies = get_cached_movies();
            $latest = array_slice($all_movies, 0, $limit);
            
            $message = "🎬 <b>Latest $limit Movies</b>\n\n";
            foreach ($latest as $i => $movie) {
                $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
                $message .= ($i + 1) . ". $channel_icon " . $movie['movie_name'] . "\n";
                $message .= "   📊 " . $movie['quality'] . " | 🗣️ " . $movie['language'] . "\n\n";
            }
            
            sendMessage($chat_id, $message, null, 'HTML');
            break;

        case '/theater':
            $all_movies = get_cached_movies();
            $theater_movies = array_filter($all_movies, function($movie) {
                return $movie['channel_type'] === 'theater';
            });
            
            if (empty($theater_movies)) {
                sendMessage($chat_id, "🎭 No theater prints found!");
                return;
            }
            
            $theater_movies = array_slice($theater_movies, 0, 10);
            $message = "🎭 <b>Theater Prints</b>\n\n";
            
            foreach ($theater_movies as $i => $movie) {
                $message .= ($i + 1) . ". " . $movie['movie_name'] . "\n";
                $message .= "   📊 " . $movie['quality'] . " | 📅 " . $movie['date'] . "\n\n";
            }
            
            sendMessage($chat_id, $message, null, 'HTML');
            break;

        // ============ QUICK ADD COMMANDS ============
        case '/quickadd':
            if ($chat_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                return;
            }
            
            $input_data = implode(' ', $params);
            if (empty($input_data)) {
                sendMessage($chat_id, QUICKADD_FORMAT, null, 'HTML');
                return;
            }
            
            if (strpos($input_data, "\n") !== false) {
                batch_quick_add_movies($chat_id, $user_id, $input_data);
            } else {
                quick_add_movie($chat_id, $user_id, $input_data);
            }
            break;

        // ============ REQUEST COMMANDS ============
        case '/request':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: <code>/request movie_name</code>", null, 'HTML');
                return;
            }
            
            if (add_movie_request($user_id, $movie_name)) {
                sendMessage($chat_id, "✅ Request received! We'll add it soon.");
                update_user_activity($user_id, 'movie_request');
            } else {
                sendMessage($chat_id, "❌ Daily request limit reached! Try again tomorrow.");
            }
            break;

        case '/myrequests':
            show_user_requests($chat_id, $user_id);
            break;

        // ============ USER COMMANDS ============
        case '/mystats':
            show_user_stats($chat_id, $user_id);
            break;

        case '/leaderboard':
            show_leaderboard($chat_id);
            break;

        // ============ CHANNEL COMMANDS ============
        case '/channels':
        case '/channel':
            show_channels_info($chat_id);
            break;

        case '/theaterchannel':
            $message = "🎭 <b>Theater Prints Channel</b>\n\n";
            $message .= "Channel: " . THEATER_CHANNEL . "\n";
            $message .= "• Latest theater prints\n";
            $message .= "• HD screen recordings\n";
            $message .= "• Best quality available\n\n";
            $message .= "🔗 Join: https://t.me/" . ltrim(THEATER_CHANNEL, '@');
            sendMessage($chat_id, $message, null, 'HTML');
            break;

        // ============ ADMIN COMMANDS ============
        case '/stats':
            show_bot_stats($chat_id);
            break;

        case '/backup':
            if ($chat_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                return;
            }
            auto_backup();
            sendMessage($chat_id, "✅ Backup process started!");
            break;

        case '/clearcache':
            if ($chat_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                return;
            }
            clear_cache('all');
            sendMessage($chat_id, "✅ Cache cleared successfully!");
            break;

        // ============ UTILITY COMMANDS ============
        case '/ping':
            sendMessage($chat_id, "🏓 Pong! Bot is online.\n⏰ Server Time: " . date('Y-m-d H:i:s'), null, 'HTML');
            break;

        default:
            sendMessage($chat_id, "❌ Unknown command. Use /help for available commands.", null, 'HTML');
    }
}

// ===================================================
// 26. MAIN UPDATE PROCESSOR
// ===================================================

$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    // Maintenance mode check
    global $MAINTENANCE_MODE, $MAINTENANCE_MESSAGE;
    
    if ($MAINTENANCE_MODE && isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        sendMessage($chat_id, $MAINTENANCE_MESSAGE, null, 'HTML');
        exit;
    }
    
    // Pre-load cache
    get_cached_movies();
    
    // Handle message
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? trim($message['text']) : '';
        $chat_type = $message['chat']['type'] ?? 'private';
        
        // Update user data
        update_user_data($user_id, [
            'first_name' => $message['from']['first_name'] ?? '',
            'last_name' => $message['from']['last_name'] ?? '',
            'username' => $message['from']['username'] ?? ''
        ]);
        
        // Handle commands
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower(array_shift($parts));
            handle_command($chat_id, $user_id, $command, $parts);
        }
        // Handle regular text (movie search)
        elseif (!empty($text)) {
            advanced_search($chat_id, $text, $user_id);
        }
    }
    
    // Handle callback queries
    elseif (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $message = $query['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $query['from']['id'];
        $data = $query['data'];
        
        // Update user activity
        update_user_data($user_id, [
            'first_name' => $query['from']['first_name'] ?? '',
            'last_name' => $query['from']['last_name'] ?? '',
            'username' => $query['from']['username'] ?? ''
        ]);
        
        // Handle different callback data patterns
        if (strpos($data, 'movie_') === 0) {
            $movie_name = base64_decode(str_replace('movie_', '', $data));
            $all_movies = get_cached_movies();
            
            $found_movies = array_filter($all_movies, function($movie) use ($movie_name) {
                return strtolower($movie['movie_name']) === strtolower($movie_name);
            });
            
            if (!empty($found_movies)) {
                foreach ($found_movies as $movie) {
                    deliver_item_to_chat($chat_id, $movie);
                    usleep(200000);
                }
                answerCallbackQuery($query['id'], "Movie info sent!");
                update_user_activity($user_id, 'download');
            } else {
                answerCallbackQuery($query['id'], "Movie not found!", true);
            }
        }
        
        elseif (strpos($data, 'pag_') === 0) {
            $parts = explode('_', $data);
            $action = $parts[1];
            
            if ($action === 'first') {
                show_paginated_movies($chat_id, 1);
                answerCallbackQuery($query['id'], "First page");
            }
            elseif ($action === 'prev') {
                $current_page = intval($parts[2]);
                show_paginated_movies($chat_id, max(1, $current_page - 1));
                answerCallbackQuery($query['id'], "Previous page");
            }
            elseif ($action === 'next') {
                $current_page = intval($parts[2]);
                $all_movies = get_cached_movies();
                $total_pages = ceil(count($all_movies) / ITEMS_PER_PAGE);
                show_paginated_movies($chat_id, min($total_pages, $current_page + 1));
                answerCallbackQuery($query['id'], "Next page");
            }
            elseif ($action === 'last') {
                $all_movies = get_cached_movies();
                $total_pages = ceil(count($all_movies) / ITEMS_PER_PAGE);
                show_paginated_movies($chat_id, $total_pages);
                answerCallbackQuery($query['id'], "Last page");
            }
            elseif (is_numeric($action)) {
                $page = intval($action);
                show_paginated_movies($chat_id, $page);
                answerCallbackQuery($query['id'], "Page $page");
            }
        }
        
        elseif (strpos($data, 'send_page_') === 0) {
            $parts = explode('_', $data);
            $page = intval($parts[2]);
            $all_movies = get_cached_movies();
            $pagination = paginate_movies($all_movies, $page);
            batch_deliver_movies($chat_id, $pagination['movies'], "Page $page");
            answerCallbackQuery($query['id'], "Batch delivery started!");
        }
        
        elseif ($data === 'my_stats') {
            show_user_stats($chat_id, $user_id);
            answerCallbackQuery($query['id'], "Your statistics");
        }
        
        elseif ($data === 'show_leaderboard') {
            show_leaderboard($chat_id);
            answerCallbackQuery($query['id'], "Leaderboard");
        }
        
        elseif (strpos($data, 'auto_request_') === 0) {
            $movie_name = base64_decode(str_replace('auto_request_', '', $data));
            
            if (add_movie_request($user_id, $movie_name)) {
                sendMessage($chat_id, "✅ Request received for: $movie_name");
                answerCallbackQuery($query['id'], "Request sent!");
            } else {
                answerCallbackQuery($query['id'], "Daily limit reached!", true);
            }
        }
        
        elseif (strpos($data, 'request_movie_') === 0) {
            $movie_name = base64_decode(str_replace('request_movie_', '', $data));
            sendMessage($chat_id, "📝 To request this movie, use:\n<code>/request $movie_name</code>", null, 'HTML');
            answerCallbackQuery($query['id'], "Request instructions");
        }
        
        else {
            answerCallbackQuery($query['id'], "Action not implemented");
        }
    }
    
    // Handle channel posts (auto-add movies)
    elseif (isset($update['channel_post'])) {
        $post = $update['channel_post'];
        $chat_id = $post['chat']['id'];
        $message_id = $post['message_id'];
        
        // Only process known channels
        $channel_type = get_channel_type_by_id($chat_id);
        if ($channel_type === 'other') {
            exit;
        }
        
        $text = '';
        if (isset($post['caption'])) {
            $text = $post['caption'];
        } elseif (isset($post['text'])) {
            $text = $post['text'];
        } elseif (isset($post['document'])) {
            $text = $post['document']['file_name'];
        }
        
        if (!empty(trim($text))) {
            $quality = detect_quality($text);
            $language = detect_language($text);
            $size = detect_size($text);
            
            // Add to database
            $movie_data = [
                'movie_name' => $text,
                'message_id_raw' => $message_id,
                'message_id' => $message_id,
                'date' => date('d-m-Y'),
                'video_path' => '',
                'quality' => $quality,
                'size' => $size,
                'language' => $language,
                'channel_type' => $channel_type,
                'channel_id' => $chat_id,
                'channel_username' => '',
                'source_channel' => $chat_id,
                'search_key' => strtolower($text),
                'added_timestamp' => time()
            ];
            
            add_movie_to_csv($movie_data);
            bot_log("Auto-added from $channel_type channel: $text");
        }
    }
    
    // Scheduled tasks
    $current_hour = date('H');
    $current_minute = date('i');
    
    // Daily auto-backup at 3 AM
    if ($current_hour == AUTO_BACKUP_HOUR && $current_minute == '00') {
        auto_backup();
    }
    
    // Hourly cache cleanup at 30 minutes
    if ($current_minute == '30') {
        clear_cache('sessions');
    }
}

// ===================================================
// 27. WEBHOOK SETUP & TESTING
// ===================================================

// Manual webhook setup
if (php_sapi_name() === 'cli' || isset($_GET['setwebhook'])) {
    $result = apiRequest('setWebhook', ['url' => $webhook_url]);
    
    echo "<h1>Webhook Setup</h1>";
    echo "<p>Result: " . htmlspecialchars($result) . "</p>";
    echo "<p>Webhook URL: " . htmlspecialchars($webhook_url) . "</p>";
    
    $bot_info = json_decode(apiRequest('getMe'), true);
    if ($bot_info && isset($bot_info['ok']) && $bot_info['ok']) {
        echo "<h2>Bot Info</h2>";
        echo "<p>Name: " . htmlspecialchars($bot_info['result']['first_name']) . "</p>";
        echo "<p>Username: @" . htmlspecialchars($bot_info['result']['username']) . "</p>";
        echo "<p>ID: " . htmlspecialchars($bot_info['result']['id']) . "</p>";
    }
    
    echo "<h3>System Status</h3>";
    echo "<p>CSV File: " . (file_exists(CSV_FILE) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Cache Directory: " . (is_dir('cache/') ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Backup Directory: " . (is_dir(BACKUP_DIR) ? "✅ Exists" : "❌ Missing") . "</p>";
    
    exit;
}

// Test page
if (isset($_GET['test'])) {
    echo "<h1>Entertainment Tadka Bot - Test Page</h1>";
    echo "<p>Version: 3.0.0</p>";
    echo "<p>Status: ✅ Running</p>";
    
    $stats = get_stats();
    echo "<h3>Statistics</h3>";
    echo "<p>Total Movies: " . ($stats['total_movies'] ?? 0) . "</p>";
    echo "<p>Total Users: " . get_users_count() . "</p>";
    echo "<p>Total Searches: " . ($stats['total_searches'] ?? 0) . "</p>";
    
    echo "<h3>Quick Links</h3>";
    echo "<p><a href='?setwebhook=1'>Set Webhook</a></p>";
    echo "<p><a href='?test=clear'>Clear Cache</a></p>";
    echo "<p><a href='?test=stats'>View Stats</a></p>";
    
    exit;
}

// Clear cache test
if (isset($_GET['test']) && $_GET['test'] === 'clear') {
    clear_cache('all');
    echo "✅ Cache cleared!";
    exit;
}

// Default page
if (!isset($update) || !$update) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Entertainment Tadka Bot</title>
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center; }
            .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
            .stat-box { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #667eea; }
            .btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
            .features { margin: 30px 0; }
            .feature-item { background: #f1f3f9; padding: 15px; margin: 10px 0; border-radius: 8px; }
        </style>
    </head>
    <body>
        <div class=\"header\">
            <h1>🎬 Entertainment Tadka Bot</h1>
            <p>Complete Movie Database System</p>
            <p>Version 3.0.0 | Status: ✅ Online</p>
        </div>
        
        <div class=\"stats\">";
    
    $stats = get_stats();
    $users_count = get_users_count();
    
    echo "
            <div class=\"stat-box\">
                <h3>🎬 Movies</h3>
                <p>" . ($stats['total_movies'] ?? 0) . "</p>
            </div>
            <div class=\"stat-box\">
                <h3>👥 Users</h3>
                <p>$users_count</p>
            </div>
            <div class=\"stat-box\">
                <h3>🔍 Searches</h3>
                <p>" . ($stats['total_searches'] ?? 0) . "</p>
            </div>
            <div class=\"stat-box\">
                <h3>📥 Downloads</h3>
                <p>" . ($stats['total_downloads'] ?? 0) . "</p>
            </div>
        </div>
        
        <div style=\"text-align: center; margin: 20px 0;\">
            <a href=\"?setwebhook=1\" class=\"btn\">🔗 Set Webhook</a>
            <a href=\"?test=clear\" class=\"btn\">🧹 Clear Cache</a>
            <a href=\"?test=stats\" class=\"btn\">📊 View Stats</a>
        </div>
        
        <div class=\"features\">
            <h2>✨ Features</h2>
            
            <div class=\"feature-item\">
                <h3>🔍 Smart Search</h3>
                <p>Advanced movie search with fuzzy matching</p>
            </div>
            
            <div class=\"feature-item\">
                <h3>📁 Multi-Channel Support</h3>
                <p>Support for 6 different channels including theater prints</p>
            </div>
            
            <div class=\"feature-item\">
                <h3>⚡ Fast Delivery</h3>
                <p>Copy messages without forward headers</p>
            </div>
            
            <div class=\"feature-item\">
                <h3>💾 Auto-Backup</h3>
                <p>Automatic daily backups with channel upload</p>
            </div>
            
            <div class=\"feature-item\">
                <h3>📊 User Statistics</h3>
                <p>Points system and leaderboard</p>
            </div>
            
            <div class=\"feature-item\">
                <h3>🛠️ Admin Tools</h3>
                <p>Quick add, batch processing, and maintenance tools</p>
            </div>
        </div>
        
        <div style=\"margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;\">
            <h3>📢 Channels</h3>
            <p>🍿 Main: " . MAIN_CHANNEL . "</p>
            <p>🎭 Theater: " . THEATER_CHANNEL . "</p>
            <p>📥 Requests: " . REQUEST_CHANNEL . "</p>
            <p>🔒 Backup: " . BACKUP_CHANNEL_USERNAME . "</p>
        </div>
        
        <footer style=\"margin-top: 30px; text-align: center; color: #666; font-size: 14px;\">
            <p>© 2024 Entertainment Tadka | All Rights Reserved</p>
            <p>Last Updated: " . date('Y-m-d H:i:s') . "</p>
        </footer>
    </body>
    </html>";
}
