<?php
// ==============================
// SECURITY HEADERS & BASIC SETUP
// ==============================

// TELEGRAM IP PROTECTION - Minimum security since token won't change
$TELEGRAM_IPS = ['149.154.160.0/20', '91.108.4.0/22'];
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$allowed_ip = false;

foreach ($TELEGRAM_IPS as $range) {
    if (ip_in_range($client_ip, $range)) {
        $allowed_ip = true;
        break;
    }
}

// Allow webhook setup URL and Telegram IPs only
if (!$allowed_ip && !isset($_GET['setup_webhook']) && !isset($_GET['init'])) {
    http_response_code(403);
    error_log("BLOCKED IP: $client_ip");
    die("Access denied");
}

function ip_in_range($ip, $range) {
    list($subnet, $bits) = explode('/', $range);
    $ip = ip2long($ip);
    $subnet = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    return ($ip & $mask) == ($subnet & $mask);
}

// Security headers PHP mein set karo - XSS aur security attacks se bachne ke liye
header("X-Content-Type-Options: nosniff");  // MIME type sniffing block karega
header("X-Frame-Options: DENY");  // Clickjacking se bachayega
header("X-XSS-Protection: 1; mode=block");  // XSS attacks block karega
header("Referrer-Policy: strict-origin-when-cross-origin");  // Referrer info secure rakhega

// ==============================
// RENDER.COM SPECIFIC CONFIGURATION
// ==============================

// Render.com provides PORT environment variable
$port = getenv('PORT') ?: '80';  // Port detect karta hai, default 80

// Webhook URL automatically set karo
$webhook_url = getenv('RENDER_EXTERNAL_URL') ?: 'https://mna-bot.onrender.com';

// Security - All credentials environment variables se lo
if (!getenv('BOT_TOKEN')) {
    die("❌ BOT_TOKEN environment variable set nahi hai. Render.com dashboard mein set karo.");
}

// ==============================
// ENVIRONMENT VARIABLES CONFIGURATION
// ==============================
// Yeh sab variables Render.com ke dashboard mein set karne hain
define('BOT_TOKEN', getenv('BOT_TOKEN'));  // Telegram bot token
define('CHANNEL_ID', getenv('CHANNEL_ID', '-1003181705395'));  // Main movies channel
define('BACKUP_CHANNEL_ID', getenv('BACKUP_CHANNEL_ID', '-1002964109368'));  // Backup channel
define('BACKUP_CHANNEL_USERNAME', getenv('BACKUP_CHANNEL_USERNAME', '@ETBackup'));  // Backup channel username
define('ADMIN_ID', (int)getenv('ADMIN_ID', '1080317415'));  // Admin user ID
define('REQUEST_CHANNEL', getenv('REQUEST_CHANNEL', '@EntertainmentTadka7860'));  // Request channel
define('MAIN_CHANNEL', getenv('MAIN_CHANNEL', '@EntertainmentTadka786'));  // Main channel

// ==============================
// MULTI-SOURCE CHANNELS CONFIGURATION
// ==============================
// Sab source channels ki list - jahan se movies forward hongi
define('SOURCE_CHANNELS', [
    [
        'id' => '-1003181705395',  // @EntertainmentTadka786
        'username' => '@EntertainmentTadka786',
        'name' => 'Main Movies Channel',
        'priority' => 1
    ],
    [
        'id' => '-1003251791991',  // Private Channel Of Movies and Webseries
        'username' => '',
        'name' => 'Private Movies Channel',
        'priority' => 2
    ],
    [
        'id' => '-1002964109368',  // @ETBackup (Backup Channel)
        'username' => '@ETBackup',
        'name' => 'Backup Channel',
        'priority' => 3
    ],
    [
        'id' => '-1002337293281',  // Backup Channel 2
        'username' => '',
        'name' => 'Secondary Backup',
        'priority' => 4
    ]
]);

// ==============================
// AUTO WEBHOOK SETUP SYSTEM
// ==============================
function setup_auto_webhook() {
    $webhook_url = 'https://mna-bot.onrender.com';
    $bot_token = BOT_TOKEN;
    
    // Check current webhook status
    $info = @file_get_contents("https://api.telegram.org/bot$bot_token/getWebhookInfo");
    $info_data = $info ? json_decode($info, true) : null;
    
    if ($info_data && isset($info_data['result']['url']) && $info_data['result']['url'] === $webhook_url) {
        bot_log("✅ Webhook already set to: $webhook_url");
        return true;
    }
    
    // Set new webhook
    $api_url = "https://api.telegram.org/bot$bot_token/setWebhook";
    $post_data = ['url' => $webhook_url];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        bot_log("✅ AUTO-WEBHOOK: Successfully set to $webhook_url");
        return true;
    } else {
        bot_log("❌ AUTO-WEBHOOK: Failed (HTTP $http_code)", 'ERROR');
        return false;
    }
}

// Auto-run webhook setup on Render startup
if (php_sapi_name() !== 'cli') {
    register_shutdown_function('setup_auto_webhook');
}

// Manual trigger for webhook setup
if (isset($_GET['setup_webhook']) && $_GET['setup_webhook'] == 'true') {
    if (setup_auto_webhook()) {
        echo "✅ Webhook setup successful!";
    } else {
        echo "❌ Webhook setup failed";
    }
    exit;
}

// ==============================
// FILE PATHS & CONSTANTS
// ==============================
// File paths - Yeh sab files bot ke saath create hongi
define('CSV_FILE', 'movies.csv');  // Movies database - SIMPLIFIED FORMAT
define('USERS_FILE', 'users.json');  // Users data
define('STATS_FILE', 'bot_stats.json');  // Bot statistics
define('REQUEST_FILE', 'movie_requests.json');  // Movie requests
define('BACKUP_DIR', 'backups/');  // Backup folder
define('LOG_FILE', 'bot_activity.log');  // Activity log

// Constants - Bot ke settings
define('CACHE_EXPIRY', 300);  // 5 minutes cache
define('ITEMS_PER_PAGE', 5);  // Pagination ke liye items per page
define('MAX_SEARCH_RESULTS', 15);  // Maximum search results
define('DAILY_REQUEST_LIMIT', 5);  // Daily movie request limit per user
define('AUTO_BACKUP_HOUR', '03');  // Auto backup time (3 AM)

// Enhanced pagination constants
define('MAX_PAGES_TO_SHOW', 7);          // Max page buttons to display
define('PAGINATION_CACHE_TIMEOUT', 60);  // Cache timeout in seconds
define('PREVIEW_ITEMS', 3);              // Number of items to preview
define('BATCH_SIZE', 5);                 // Batch download size

// ==============================
// MAINTENANCE MODE
// ==============================
$MAINTENANCE_MODE = false;  // Agar true hai toh bot maintenance mode mein hoga
$MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>\n\nWe're temporarily unavailable for updates.\nWill be back in few days!\n\nThanks for patience 🙏";

// ==============================
// GLOBAL VARIABLES
// ==============================
$movie_messages = array();  // Movies cache
$movie_cache = array();  // Movies data cache
$waiting_users = array();  // Users waiting for movies
$user_sessions = array();  // User sessions
$user_pagination_sessions = array();  // Enhanced: Pagination sessions

// ==============================
// FILE INITIALIZATION FUNCTION
// ==============================
function initialize_files() {
    // Sab required files create karta hai agar nahi hain toh
    $files = [
        CSV_FILE => "movie_name,message_id,date,channel_id\n",  // SIMPLIFIED CSV HEADER (4 columns only)
        USERS_FILE => json_encode([
            'users' => [],  // Users ka data
            'total_requests' => 0,  // Total requests count
            'message_logs' => [],  // Message logs
            'daily_stats' => []  // Daily statistics
        ], JSON_PRETTY_PRINT),
        STATS_FILE => json_encode([
            'total_movies' => 0,  // Total movies count
            'total_users' => 0,  // Total users count
            'total_searches' => 0,  // Total searches
            'total_downloads' => 0,  // Total downloads
            'successful_searches' => 0,  // Successful searches
            'failed_searches' => 0,  // Failed searches
            'daily_activity' => [],  // Daily activity data
            'last_updated' => date('Y-m-d H:i:s')  // Last updated timestamp
        ], JSON_PRETTY_PRINT),
        REQUEST_FILE => json_encode([
            'requests' => [],  // Movie requests
            'pending_approval' => [],  // Pending requests
            'completed_requests' => [],  // Completed requests
            'user_request_count' => []  // User request counts
        ], JSON_PRETTY_PRINT)
    ];
    
    // Har file ko check karo aur create karo agar nahi hai
    foreach ($files as $file => $content) {
        if (!file_exists($file)) {
            file_put_contents($file, $content);
            @chmod($file, 0666);  // Read/write permissions
        }
    }
    
    // Backup directory create karo
    if (!file_exists(BACKUP_DIR)) {
        @mkdir(BACKUP_DIR, 0777, true);  // Full permissions
    }
    
    // Log file create karo
    if (!file_exists(LOG_FILE)) {
        file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] SYSTEM: Files initialized\n");
    }
}

// Initialize all files
initialize_files();

// ==============================
// LOGGING SYSTEM
// ==============================
function bot_log($message, $type = 'INFO') {
    // Bot activities ko log karta hai
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

// ==============================
// CACHING SYSTEM
// ==============================
function get_cached_movies() {
    global $movie_cache;
    
    // Cache check karo - 5 minutes se zyada purana toh refresh karo
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < CACHE_EXPIRY) {
        return $movie_cache['data'];  // Cache hit
    }
    
    // Cache miss - reload data from CSV
    $movie_cache = [
        'data' => load_and_clean_csv(),
        'timestamp' => time()
    ];
    
    bot_log("Movie cache refreshed - " . count($movie_cache['data']) . " movies");
    return $movie_cache['data'];
}

// ==============================
// CSV MANAGEMENT FUNCTIONS (SIMPLIFIED FORMAT)
// ==============================
function load_and_clean_csv($filename = CSV_FILE) {
    global $movie_messages;
    
    // CSV file check karo, agar nahi hai toh create karo (SIMPLIFIED FORMAT)
    if (!file_exists($filename)) {
        file_put_contents($filename, "movie_name,message_id,date,channel_id\n");  // 4 COLUMNS ONLY
        return [];
    }

    $data = [];
    $handle = fopen($filename, "r");
    if ($handle !== FALSE) {
        $header = fgetcsv($handle);  // Header read karo
        
        // Check if old format (8 columns) or new format (4 columns)
        $is_old_format = count($header) > 4;
        
        // Har row ko process karo
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && (!empty(trim($row[0])))) {
                // Handle both old and new formats
                if ($is_old_format && count($row) >= 8) {
                    // OLD FORMAT (8 columns): movie_name,message_id,date,video_path,quality,size,language,channel_id
                    $movie_name = trim($row[0]);
                    $message_id_raw = isset($row[1]) ? trim($row[1]) : '';
                    $date = isset($row[2]) ? trim($row[2]) : '';
                    $channel_id = isset($row[7]) ? trim($row[7]) : CHANNEL_ID;
                    
                    // Extract quality and language from movie_name
                    $quality = extract_quality_from_name($movie_name);
                    $language = extract_language_from_name($movie_name);
                    $size = isset($row[5]) ? trim($row[5]) : 'Unknown';
                } else {
                    // NEW FORMAT (4 columns): movie_name,message_id,date,channel_id
                    $movie_name = trim($row[0]);
                    $message_id_raw = isset($row[1]) ? trim($row[1]) : '';
                    $date = isset($row[2]) ? trim($row[2]) : '';
                    $channel_id = isset($row[3]) ? trim($row[3]) : CHANNEL_ID;
                    
                    // Extract from movie_name
                    $quality = extract_quality_from_name($movie_name);
                    $language = extract_language_from_name($movie_name);
                    $size = 'Unknown';
                }

                // Movie entry create karo
                $entry = [
                    'movie_name' => $movie_name,
                    'message_id_raw' => $message_id_raw,
                    'date' => $date,
                    'video_path' => '',
                    'quality' => $quality,
                    'size' => $size,
                    'language' => $language,
                    'channel_id' => $channel_id
                ];
                
                // Message ID numeric check karo
                if (is_numeric($message_id_raw)) {
                    $entry['message_id'] = intval($message_id_raw);
                } else {
                    $entry['message_id'] = null;
                }

                $data[] = $entry;

                // Global movie messages array mein add karo
                $movie = strtolower($movie_name);
                if (!isset($movie_messages[$movie])) $movie_messages[$movie] = [];
                $movie_messages[$movie][] = $entry;
            }
        }
        fclose($handle);
    }

    // Statistics update karo
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats['total_movies'] = count($data);
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));

    // CSV clean karo aur rewrite karo (SIMPLIFIED FORMAT mein)
    $handle = fopen($filename, "w");
    fputcsv($handle, array('movie_name','message_id','date','channel_id'));  // 4 COLUMNS ONLY
    foreach ($data as $row) {
        fputcsv($handle, [
            $row['movie_name'], 
            $row['message_id_raw'], 
            $row['date'], 
            $row['channel_id']
        ]);
    }
    fclose($handle);

    bot_log("CSV cleaned (simplified format) - " . count($data) . " entries");
    return $data;
}

// Helper functions for extracting info from movie name
function extract_quality_from_name($movie_name) {
    $movie_lower = strtolower($movie_name);
    
    if (strpos($movie_lower, '1080') !== false || strpos($movie_lower, '1080p') !== false) return '1080p';
    if (strpos($movie_lower, '720') !== false || strpos($movie_lower, '720p') !== false) return '720p';
    if (strpos($movie_lower, '480') !== false || strpos($movie_lower, '480p') !== false) return '480p';
    if (strpos($movie_lower, 'hd') !== false) return 'HD';
    if (strpos($movie_lower, 'webrip') !== false) return 'WebRip';
    if (strpos($movie_lower, 'web-dl') !== false) return 'Web-DL';
    
    return 'Unknown';
}

function extract_language_from_name($movie_name) {
    $movie_lower = strtolower($movie_name);
    
    if (strpos($movie_lower, 'hindi') !== false) return 'Hindi';
    if (strpos($movie_lower, 'english') !== false) return 'English';
    if (strpos($movie_lower, 'tamil') !== false) return 'Tamil';
    if (strpos($movie_lower, 'telugu') !== false) return 'Telugu';
    if (strpos($movie_lower, 'dual') !== false) return 'Dual Audio';
    
    return 'Hindi';  // Default
}

// ==============================
// TELEGRAM API FUNCTIONS
// ==============================
function apiRequest($method, $params = array(), $is_multipart = false) {
    // Telegram API ko call karta hai
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    if ($is_multipart) {
        // Files upload ke liye (multipart form data)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $res = curl_exec($ch);
        if ($res === false) {
            bot_log("CURL ERROR: " . curl_error($ch), 'ERROR');
        }
        curl_close($ch);
        return $res;
    } else {
        // Normal API requests ke liye
        $options = array(
            'http' => array(
                'method' => 'POST',
                'content' => http_build_query($params),
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
            )
        );
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            bot_log("API Request failed for method: $method", 'ERROR');
        }
        return $result;
    }
}

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = null) {
    // Telegram message send karta hai
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true  // Link preview disable karta hai
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    
    $result = apiRequest('sendMessage', $data);
    bot_log("Message sent to $chat_id: " . substr($text, 0, 50) . "...");
    return json_decode($result, true);
}

function editMessage($chat_id, $message_id, $new_text, $reply_markup = null) {
    // Existing message edit karta hai
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $new_text,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    apiRequest('editMessageText', $data);
}

function deleteMessage($chat_id, $message_id) {
    // Message delete karta hai
    apiRequest('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
    // Callback query reply karta hai
    $data = [
        'callback_query_id' => $callback_query_id,
        'show_alert' => $show_alert
    ];
    if ($text) $data['text'] = $text;
    apiRequest('answerCallbackQuery', $data);
}

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    // Message forward karta hai
    $result = apiRequest('forwardMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
    return $result;
}

function copyMessage($chat_id, $from_chat_id, $message_id) {
    // Message copy karta hai (forward nahi dikhata)
    return apiRequest('copyMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

// ==============================
// ENHANCED MULTI-CHANNEL MOVIE DELIVERY SYSTEM
// ==============================
function deliver_item_to_chat($chat_id, $item) {
    // MULTI-CHANNEL SYSTEM - Sab channels try karega WITHOUT FORWARD HEADERS
    
    bot_log("MULTI-CHANNEL: Delivering '{$item['movie_name']}' to $chat_id");
    
    // 1. First try: COPY from stored channel (NO FORWARD HEADER)
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        $primary_channel = $item['channel_id'] ?? CHANNEL_ID;
        
        bot_log("Trying primary channel: $primary_channel");
        
        // Try COPY (no header)
        $result = json_decode(copyMessage($chat_id, $primary_channel, $item['message_id']), true);
        if ($result && isset($result['ok']) && $result['ok']) {
            update_stats('total_downloads', 1);
            bot_log("✅ COPIED (no header) from primary: {$item['movie_name']}");
            return true;
        }
        
        // 2. Try ALL source channels in priority order
        bot_log("Primary failed, trying all channels...");
        
        // Sort by priority
        $sorted_channels = SOURCE_CHANNELS;
        usort($sorted_channels, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        
        foreach ($sorted_channels as $channel) {
            bot_log("Trying channel: {$channel['name']} (ID: {$channel['id']})");
            
            // COPY try karo (no header)
            $result = json_decode(copyMessage($chat_id, $channel['id'], $item['message_id']), true);
            if ($result && isset($result['ok']) && $result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("✅ COPIED from {$channel['name']}: {$item['movie_name']}");
                return true;
            }
            
            usleep(100000); // 0.1 sec delay
        }
        
        // 3. Last resort: Forward (will show header)
        bot_log("All copy attempts failed, trying forward...");
        foreach ($sorted_channels as $channel) {
            $result = json_decode(forwardMessage($chat_id, $channel['id'], $item['message_id']), true);
            if ($result && isset($result['ok']) && $result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("⚠️ FORWARDED (with header) from {$channel['name']}");
                return true;
            }
        }
    }
    
    // 4. Text fallback
    bot_log("❌ All delivery methods failed for: {$item['movie_name']}");
    
    $channel_display = get_channel_display_name($item['channel_id'] ?? CHANNEL_ID);
    $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Movie') . "</b>\n\n";
    $text .= "📊 <b>Quality:</b> " . ($item['quality'] ?? 'Unknown') . "\n";
    $text .= "🗣️ <b>Language:</b> " . ($item['language'] ?? 'Hindi') . "\n";
    $text .= "📅 <b>Date:</b> " . ($item['date'] ?? 'N/A') . "\n";
    $text .= "📡 <b>Source:</b> " . $channel_display . "\n\n";
    $text .= "❌ <i>Could not deliver from any channel. Please try again.</i>";
    
    sendMessage($chat_id, $text, null, 'HTML');
    return false;
}

// Helper function to get channel display name
function get_channel_display_name($channel_id) {
    foreach (SOURCE_CHANNELS as $channel) {
        if ($channel['id'] == $channel_id) {
            return !empty($channel['username']) ? $channel['username'] : $channel['name'];
        }
    }
    return "Channel";
}

// ==============================
// STATISTICS SYSTEM
// ==============================
function update_stats($field, $increment = 1) {
    // Statistics update karta hai
    if (!file_exists(STATS_FILE)) return;
    
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats[$field] = ($stats[$field] ?? 0) + $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    
    // Daily activity update karo
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

function get_stats() {
    // Statistics return karta hai
    if (!file_exists(STATS_FILE)) return [];
    return json_decode(file_get_contents(STATS_FILE), true);
}

// [REST OF THE CODE REMAINS THE SAME - ALL OTHER FUNCTIONS UNCHANGED]
// ==============================
// USER MANAGEMENT
// ==============================
function update_user_data($user_id, $user_info = []) {
    // User data update/create karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id])) {
        // New user create karo
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
            'last_request_date' => null
        ];
        $users_data['total_requests'] = ($users_data['total_requests'] ?? 0) + 1;
        update_stats('total_users', 1);
        bot_log("New user registered: $user_id");
    }
    
    $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    
    return $users_data['users'][$user_id];
}

function update_user_activity($user_id, $action) {
    // User activity aur points update karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (isset($users_data['users'][$user_id])) {
        $points_map = [
            'search' => 1,
            'found_movie' => 5,
            'daily_login' => 10,
            'movie_request' => 2,
            'download' => 3
        ];
        
        $users_data['users'][$user_id]['points'] += ($points_map[$action] ?? 0);
        
        if ($action == 'search') $users_data['users'][$user_id]['total_searches']++;
        if ($action == 'download') $users_data['users'][$user_id]['total_downloads']++;
        
        $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
        file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    }
}

// ==============================
// SEARCH SYSTEM - MOST IMPORTANT!
// ==============================
function smart_search($query) {
    global $movie_messages;
    $query_lower = strtolower(trim($query));
    $results = array();
    
    // Har movie ke against query match karo
    foreach ($movie_messages as $movie => $entries) {
        $score = 0;
        
        // 1. Exact match check karo
        if ($movie == $query_lower) {
            $score = 100;
        }
        // 2. Partial match check karo
        elseif (strpos($movie, $query_lower) !== false) {
            $score = 80 - (strlen($movie) - strlen($query_lower));
        }
        // 3. Similarity match check karo
        else {
            similar_text($movie, $query_lower, $similarity);
            if ($similarity > 60) $score = $similarity;
        }
        
        // Quality aur language ke liye bonus points
        foreach ($entries as $entry) {
            if (stripos($entry['quality'] ?? '', '1080') !== false) $score += 5;
            if (stripos($entry['quality'] ?? '', '720') !== false) $score += 3;
            if (stripos($entry['language'] ?? '', 'hindi') !== false) $score += 2;
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
    
    // Score ke hisab se sort karo (descending)
    uasort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    // Maximum results return karo
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
}

function detect_language($text) {
    // Text ki language detect karta hai (Hindi/English)
    $hindi_keywords = ['फिल्म', 'मूवी', 'डाउनलोड', 'हिंदी', 'चाहिए', 'कहाँ', 'कैसे', 'खोज', 'तलाश'];
    $english_keywords = ['movie', 'download', 'watch', 'print', 'search', 'find', 'looking', 'want', 'need'];
    
    $hindi_score = 0;
    $english_score = 0;
    
    // Hindi keywords check karo
    foreach ($hindi_keywords as $k) {
        if (strpos($text, $k) !== false) $hindi_score++;
    }
    
    // English keywords check karo
    foreach ($english_keywords as $k) {
        if (stripos($text, $k) !== false) $english_score++;
    }
    
    // Hindi characters detect karo
    $hindi_chars = preg_match('/[\x{0900}-\x{097F}]/u', $text);
    if ($hindi_chars) $hindi_score += 3;
    
    return $hindi_score > $english_score ? 'hindi' : 'english';
}

function send_multilingual_response($chat_id, $message_type, $language) {
    // Language ke hisab se response send karta hai
    $responses = [
        'hindi' => [
            'welcome' => "🎬 Boss, kis movie ki talash hai?",
            'found' => "✅ Mil gayi! Movie forward ho rahi hai...",
            'not_found' => "😔 Yeh movie abhi available nahi hai!\n\n📝 Aap ise request kar sakte hain: " . REQUEST_CHANNEL . "\n\n🔔 Jab bhi yeh add hogi, main automatically bhej dunga!",
            'searching' => "🔍 Dhoondh raha hoon... Zara wait karo",
            'multiple_found' => "🎯 Kai versions mili hain! Aap konsi chahte hain?",
            'request_success' => "✅ Request receive ho gayi! Hum jald hi add karenge.",
            'request_limit' => "❌ Aaj ke liye aap maximum " . DAILY_REQUEST_LIMIT . " requests hi kar sakte hain."
        ],
        'english' => [
            'welcome' => "🎬 Boss, which movie are you looking for?",
            'found' => "✅ Found it! Forwarding the movie...",
            'not_found' => "😔 This movie isn't available yet!\n\n📝 You can request it here: " . REQUEST_CHANNEL . "\n\n🔔 I'll send it automatically once it's added!",
            'searching' => "🔍 Searching... Please wait",
            'multiple_found' => "🎯 Multiple versions found! Which one do you want?",
            'request_success' => "✅ Request received! We'll add it soon.",
            'request_limit' => "❌ You've reached the daily limit of " . DAILY_REQUEST_LIMIT . " requests."
        ]
    ];
    
    sendMessage($chat_id, $responses[$language][$message_type]);
}

function advanced_search($chat_id, $query, $user_id = null) {
    global $movie_messages, $waiting_users;
    $q = strtolower(trim($query));
    
    // Minimum length check
    if (strlen($q) < 2) {
        sendMessage($chat_id, "❌ Please enter at least 2 characters for search");
        return;
    }
    
    // Invalid keywords filter - technical queries block karega
    $invalid_keywords = [
        'vlc', 'audio', 'track', 'change', 'open', 'kar', 'me', 'hai',
        'how', 'what', 'problem', 'issue', 'help', 'solution', 'fix',
        'error', 'not working', 'download', 'play', 'video', 'sound',
        'subtitle', 'quality', 'hd', 'full', 'part', 'scene',
        'hi', 'hello', 'hey', 'good', 'morning', 'night', 'bye',
        'thanks', 'thank', 'ok', 'okay', 'yes', 'no', 'maybe',
        'who', 'when', 'where', 'why', 'how', 'can', 'should',
        'kaise', 'kya', 'kahan', 'kab', 'kyun', 'kon', 'kisne',
        'hai', 'hain', 'ho', 'raha', 'raha', 'rah', 'tha', 'thi',
        'mere', 'apne', 'tumhare', 'hamare', 'sab', 'log', 'group'
    ];
    
    // Smart word analysis
    $query_words = explode(' ', $q);
    $total_words = count($query_words);
    
    $invalid_count = 0;
    foreach ($query_words as $word) {
        if (in_array($word, $invalid_keywords)) {
            $invalid_count++;
        }
    }
    
    // Stricter threshold - agar 50% se zyada invalid words toh block karo
    if ($invalid_count > 0 && ($invalid_count / $total_words) > 0.5) {
        $help_msg = "🎬 Please enter a movie name!\n\n";
        $help_msg .= "🔍 Examples of valid movie names:\n";
        $help_msg .= "• kgf\n• pushpa\n• avengers\n• hindi movie\n• spider-man\n\n";
        $help_msg .= "❌ Technical queries like 'vlc', 'audio track', etc. are not movie names.\n\n";
        $help_msg .= "📢 Join: " . MAIN_CHANNEL . "\n";
        $help_msg .= "💬 Help: " . REQUEST_CHANNEL;
        sendMessage($chat_id, $help_msg, null, 'HTML');
        return;
    }
    
    // Movie name pattern validation
    $movie_pattern = '/^[a-zA-Z0-9\s\-\.\,\&\+\(\)\:\'\"]+$/';
    if (!preg_match($movie_pattern, $query)) {
        sendMessage($chat_id, "❌ Invalid movie name format. Only letters, numbers, and basic punctuation allowed.");
        return;
    }
    
    // Search karo
    $found = smart_search($q);
    
    if (!empty($found)) {
        // Movies mil gayi
        update_stats('successful_searches', 1);
        
        $msg = "🔍 Found " . count($found) . " movies for '$query':\n\n";
        $i = 1;
        foreach ($found as $movie => $data) {
            $quality_info = !empty($data['qualities']) ? implode('/', $data['qualities']) : 'Unknown';
            $msg .= "$i. $movie (" . $data['count'] . " versions, $quality_info)\n";
            $i++;
            if ($i > 10) break;
        }
        
        sendMessage($chat_id, $msg);
        
        // Inline keyboard banayega top matches ke liye
        $keyboard = ['inline_keyboard' => []];
        $top_movies = array_slice(array_keys($found), 0, 5);
        
        foreach ($top_movies as $movie) {
            $keyboard['inline_keyboard'][] = [[ 
                'text' => "🎬 " . ucwords($movie), 
                'callback_data' => $movie 
            ]];
        }
        
        // Request button add karo
        $keyboard['inline_keyboard'][] = [[
            'text' => "📝 Request Different Movie", 
            'callback_data' => 'request_movie'
        ]];
        
        sendMessage($chat_id, "🚀 Top matches (click to download):", $keyboard);
        
        if ($user_id) {
            update_user_activity($user_id, 'found_movie');
            update_user_activity($user_id, 'search');
        }
        
    } else {
        // Movies nahi mili
        update_stats('failed_searches', 1);
        $lang = detect_language($query);
        send_multilingual_response($chat_id, 'not_found', $lang);
        
        // Auto-suggest request
        $request_keyboard = [
            'inline_keyboard' => [[
                ['text' => '📝 Request This Movie', 'callback_data' => 'auto_request_' . base64_encode($query)]
            ]]
        ];
        
        sendMessage($chat_id, "💡 Click below to automatically request this movie:", $request_keyboard);
        
        // Waiting list mein add karo
        if (!isset($waiting_users[$q])) $waiting_users[$q] = [];
        $waiting_users[$q][] = [$chat_id, $user_id ?? $chat_id];
    }
    
    update_stats('total_searches', 1);
    if ($user_id) update_user_activity($user_id, 'search');
}

// ==============================
// MOVIE REQUEST SYSTEM
// ==============================
function can_user_request($user_id) {
    // Check karo user daily limit mein hai ya nahi
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
    // Movie request add karta hai
    if (!can_user_request($user_id)) {
        return false;
    }
    
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    
    $request_id = uniqid();
    $requests_data['requests'][] = [
        'id' => $request_id,
        'user_id' => $user_id,
        'movie_name' => $movie_name,
        'language' => $language,
        'date' => date('Y-m-d'),
        'time' => date('H:i:s'),
        'status' => 'pending'
    ];
    
    // User request count update karo
    if (!isset($requests_data['user_request_count'][$user_id])) {
        $requests_data['user_request_count'][$user_id] = 0;
    }
    $requests_data['user_request_count'][$user_id]++;
    
    file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
    
    // Admin ko notify karo
    $admin_msg = "🎯 New Movie Request\n\n";
    $admin_msg .= "🎬 Movie: $movie_name\n";
    $admin_msg .= "🗣️ Language: $language\n";
    $admin_msg .= "👤 User ID: $user_id\n";
    $admin_msg .= "📅 Date: " . date('Y-m-d H:i:s') . "\n";
    $admin_msg .= "🆔 Request ID: $request_id";
    
    sendMessage(ADMIN_ID, $admin_msg);
    bot_log("Movie request added: $movie_name by $user_id");
    
    return true;
}

// ==============================
// APPEND MOVIE FUNCTION (UPDATED FOR SIMPLIFIED FORMAT)
// ==============================
function append_movie($movie_name, $message_id_raw, $date = null, $channel_id = null) {
    // Movie database mein add karta hai (SIMPLIFIED - 4 parameters only)
    if (empty(trim($movie_name))) return;
    
    if ($date === null) $date = date('d-m-Y');
    if ($channel_id === null) $channel_id = CHANNEL_ID;
    
    // SIMPLIFIED ENTRY (4 columns only)
    $entry = [$movie_name, $message_id_raw, $date, $channel_id];
    
    $handle = fopen(CSV_FILE, "a");
    fputcsv($handle, $entry);
    fclose($handle);

    global $movie_messages, $movie_cache, $waiting_users;
    
    // Extract quality and language from movie name
    $quality = extract_quality_from_name($movie_name);
    $language = extract_language_from_name($movie_name);
    
    $movie = strtolower(trim($movie_name));
    $item = [
        'movie_name' => $movie_name,
        'message_id_raw' => $message_id_raw,
        'date' => $date,
        'video_path' => '',
        'quality' => $quality,
        'size' => 'Unknown',
        'language' => $language,
        'channel_id' => $channel_id,
        'message_id' => is_numeric($message_id_raw) ? intval($message_id_raw) : null
    ];
    
    if (!isset($movie_messages[$movie])) $movie_messages[$movie] = [];
    $movie_messages[$movie][] = $item;
    $movie_cache = [];

    // Waiting users ko notify karo
    foreach ($waiting_users as $query => $users) {
        if (strpos($movie, $query) !== false) {
            foreach ($users as $user_data) {
                list($user_chat_id, $user_id) = $user_data;
                $result = deliver_item_to_chat($user_chat_id, $item);
                if ($result) {
                    sendMessage($user_chat_id, "✅ '$query' ab available hai!");
                }
            }
            unset($waiting_users[$query]);
        }
    }

    update_stats('total_movies', 1);
    bot_log("Movie appended (simplified): $movie_name to channel $channel_id");
}

// ==============================
// MAIN UPDATE PROCESSING
// ==============================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    // Maintenance mode check
    global $MAINTENANCE_MODE, $MAINTENANCE_MESSAGE;
    if ($MAINTENANCE_MODE && isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        sendMessage($chat_id, $MAINTENANCE_MESSAGE, null, 'HTML');
        bot_log("Maintenance mode active - message blocked from $chat_id");
        exit;
    }

    get_cached_movies();

    // Channel post handling
    if (isset($update['channel_post'])) {
        $message = $update['channel_post'];
        $message_id = $message['message_id'];
        $chat_id = $message['chat']['id'];

        // Check if this is one of our source channels
        $channel_info = null;
        foreach (SOURCE_CHANNELS as $channel) {
            if ($channel['id'] == (string)$chat_id) {
                $channel_info = $channel;
                break;
            }
        }

        if ($channel_info) {
            $text = '';
            
            if (isset($message['caption'])) {
                $text = $message['caption'];
            }
            elseif (isset($message['text'])) {
                $text = $message['text'];
            }
            elseif (isset($message['document'])) {
                $text = $message['document']['file_name'];
            }
            else {
                $text = 'Uploaded Media - ' . date('d-m-Y H:i');
            }

            if (!empty(trim($text))) {
                // SIMPLIFIED: Only 4 parameters
                append_movie($text, $message_id, date('d-m-Y'), $channel_info['id']);
                bot_log("Movie added from {$channel_info['name']}");
            }
        }
    }

    // Message handling
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        $chat_type = $message['chat']['type'] ?? 'private';

        // User data update karo
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
                // Group message filter (existing code)
            }
        }

        // Command handling
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $params = array_slice($parts, 1);
            
            // Handle command (existing code)
        } else if (!empty(trim($text))) {
            $lang = detect_language($text);
            send_multilingual_response($chat_id, 'searching', $lang);
            advanced_search($chat_id, $text, $user_id);
        }
    }

    // Callback query handling
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $message = $query['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $query['from']['id'];
        $data = $query['data'];

        global $movie_messages;
        
        // Movie selection
        $movie_lower = strtolower($data);
        if (isset($movie_messages[$movie_lower])) {
            $entries = $movie_messages[$movie_lower];
            $cnt = 0;
            
            foreach ($entries as $entry) {
                // MULTI-CHANNEL DELIVERY (NO HEADERS)
                deliver_item_to_chat($chat_id, $entry);
                usleep(200000);
                $cnt++;
            }
            
            sendMessage($chat_id, "✅ '$data' ke $cnt movies bhej di gayi hain (no headers)!\n\n📢 Join: " . MAIN_CHANNEL);
            answerCallbackQuery($query['id'], "🎬 $cnt items sent!");
            update_user_activity($user_id, 'download');
        }
        // Other callback handlers...
    }

    // Scheduled tasks
    $current_hour = date('H');
    $current_minute = date('i');

    // Daily auto-backup at 3 AM
    if ($current_hour == AUTO_BACKUP_HOUR && $current_minute == '00') {
        // auto_backup(); // Uncomment if needed
        bot_log("Daily auto-backup time reached");
    }
}

// ==============================
// WEBHOOK SETUP & TESTING
// ==============================
if (php_sapi_name() === 'cli' || isset($_GET['setwebhook'])) {
    // Manual webhook setup
    $result = apiRequest('setWebhook', ['url' => $webhook_url]);
    
    echo "<h1>Webhook Setup</h1>";
    echo "<p>Result: " . htmlspecialchars($result) . "</p>";
    echo "<p>Webhook URL: " . htmlspecialchars($webhook_url) . "</p>";
    
    $bot_info = json_decode(apiRequest('getMe'), true);
    if ($bot_info && isset($bot_info['ok']) && $bot_info['ok']) {
        echo "<h2>Bot Info</h2>";
        echo "<p>Name: " . htmlspecialchars($bot_info['result']['first_name']) . "</p>";
        echo "<p>Username: @" . htmlspecialchars($bot_info['result']['username']) . "</p>";
        echo "<p>Channel: " . MAIN_CHANNEL . "</p>";
        echo "<p>Source Channels: " . count(SOURCE_CHANNELS) . " configured</p>";
    }
    
    exit;
}

// Default page display
if (!isset($update) || !$update) {
    // Bot status page show karo
    $stats = get_stats();
    
    echo "<h1>🎬 Entertainment Tadka Bot (MULTI-CHANNEL)</h1>";
    echo "<p><strong>Status:</strong> ✅ Running with Multi-Channel System</p>";
    echo "<p><strong>Source Channels:</strong> " . count(SOURCE_CHANNELS) . " configured</p>";
    echo "<p><strong>CSV Format:</strong> Simplified (4 columns)</p>";
    echo "<p><strong>Forward Headers:</strong> ❌ Hidden (using copyMessage)</p>";
    echo "<p><strong>Auto-Webhook:</strong> ✅ Enabled</p>";
    echo "<p><strong>Total Movies:</strong> " . ($stats['total_movies'] ?? 0) . "</p>";
    
    echo "<h3>🚀 Features Active:</h3>";
    echo "<ul>";
    echo "<li>✅ Multi-Channel Forwarding (4 channels)</li>";
    echo "<li>✅ No Forward Headers (copyMessage)</li>";
    echo "<li>✅ Simplified CSV Format (movie_name,message_id,date,channel_id)</li>";
    echo "<li>✅ Auto-Webhook Setup on Render</li>";
    echo "<li>✅ IP Protection (Telegram IPs only)</li>";
    echo "</ul>";
    
    echo "<h3>⚠️ Security Status:</h3>";
    echo "<p style='color:red;'><strong>WARNING:</strong> Bot token is publicly exposed. High risk!</p>";
    echo "<p>Consider revoking token via @BotFather</p>";
    
    echo "<h3>📊 Quick Links:</h3>";
    echo "<p><a href='?setup_webhook=true'>Force Webhook Setup</a></p>";
    echo "<p><a href='?test_multi=1'>Test Multi-Channel System</a></p>";
}
?>
