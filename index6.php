<?php
// ==============================
// SECURITY HEADERS & BASIC SETUP
// ==============================

// Security headers PHP mein set karo - XSS aur security attacks se bachne ke liye
header("X-Content-Type-Options: nosniff");  // MIME type sniffing block karega
header("X-Frame-Options: DENY");  // Clickjacking se bachayega
header("X-XSS-Protection: 1; mode=block");  // XSS attacks block karega
header("Referrer-Policy: strict-origin-when-cross-origin");  // Referrer info secure rakhega

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==============================
// RENDER.COM SPECIFIC CONFIGURATION
// ==============================

// Render.com provides PORT environment variable
$port = getenv('PORT') ?: '80';  // Port detect karta hai, default 80

// Webhook URL automatically set karo
$webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// Security - All credentials environment variables se lo
if (!getenv('BOT_TOKEN')) {
    die("❌ BOT_TOKEN environment variable set nahi hai. Render.com dashboard mein set karo.");
}

// ==============================
// ENVIRONMENT VARIABLES CONFIGURATION
// ==============================
// Yeh sab variables Render.com ke dashboard mein set karne hain
define('BOT_TOKEN', getenv('BOT_TOKEN'));  // Telegram bot token

// ALL CHANNELS DEFINED (6 Channels + 1 Group)
define('CHANNEL_1_ID', '-1003181705395');  // @EntertainmentTadka786 (Main Channel)
define('CHANNEL_2_ID', '-1002964109368');  // @ETBackup (Backup Channel 1)
define('CHANNEL_3_ID', '-1002831605258');  // @threater_print_movies (Theater Channel)
define('CHANNEL_4_ID', '-1002337293281');  // Backup Channel 2
define('CHANNEL_5_ID', '-1003251791991');  // Private Channel
define('CHANNEL_6_ID', '-1003614546520');  // Forwarded From Any Channel
define('GROUP_ID', '-1003083386043');      // @EntertainmentTadka7860 (Request Group)

// Usernames for display
define('CHANNEL_1_USERNAME', '@EntertainmentTadka786');
define('CHANNEL_2_USERNAME', '@ETBackup');
define('CHANNEL_3_USERNAME', '@threater_print_movies');
define('CHANNEL_4_USERNAME', '@BackupChannel2');
define('CHANNEL_5_USERNAME', '@PrivateChannel');
define('CHANNEL_6_USERNAME', '@AnyForwardedChannel');
define('GROUP_USERNAME', '@EntertainmentTadka7860');

// Default main channel (for backward compatibility)
define('CHANNEL_ID', CHANNEL_1_ID);
define('MAIN_CHANNEL', CHANNEL_1_USERNAME);
define('BACKUP_CHANNEL_ID', CHANNEL_2_ID);
define('BACKUP_CHANNEL_USERNAME', CHANNEL_2_USERNAME);
define('THEATER_CHANNEL_ID', CHANNEL_3_ID);
define('THEATER_CHANNEL', CHANNEL_3_USERNAME);
define('REQUEST_CHANNEL', GROUP_USERNAME);

// Admin ID
define('ADMIN_ID', (int)getenv('ADMIN_ID'));  // Admin user ID

// Validate essential environment variables
if (!BOT_TOKEN || !ADMIN_ID) {
    die("❌ Essential environment variables set nahi hain. Render.com dashboard mein set karo.");
}

// File paths - Yeh sab files bot ke saath create hongi
define('CSV_FILE', 'movies.csv');  // Movies database - NEW FORMAT
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

// ==============================
// ENHANCED PAGINATION CONSTANTS
// ==============================
define('MAX_PAGES_TO_SHOW', 7);          // Max page buttons to display
define('PAGINATION_CACHE_TIMEOUT', 60);  // Cache timeout in seconds
define('PREVIEW_ITEMS', 3);              // Number of items to preview
define('BATCH_SIZE', 5);                 // Batch download size

// ==============================
// DELAY TYPING FEATURE
// ==============================
define('TYPING_DELAY_ENABLED', true);    // Typing delay enable/disable
define('TYPING_DELAY_MIN', 2);           // Minimum delay in seconds - TESTING
define('TYPING_DELAY_MAX', 4);           // Maximum delay in seconds - TESTING

// ==============================
// CHANNEL MAPPING ARRAY
// ==============================
$CHANNEL_MAP = [
    CHANNEL_1_ID => CHANNEL_1_USERNAME,
    CHANNEL_2_ID => CHANNEL_2_USERNAME,
    CHANNEL_3_ID => CHANNEL_3_USERNAME,
    CHANNEL_4_ID => CHANNEL_4_USERNAME,
    CHANNEL_5_ID => CHANNEL_5_USERNAME,
    CHANNEL_6_ID => CHANNEL_6_USERNAME,
    GROUP_ID => GROUP_USERNAME
];

// Channel ID to Type mapping
$CHANNEL_TYPE_MAP = [
    CHANNEL_1_ID => 'main',
    CHANNEL_2_ID => 'backup',
    CHANNEL_3_ID => 'theater',
    CHANNEL_4_ID => 'backup2',
    CHANNEL_5_ID => 'private',
    CHANNEL_6_ID => 'any',
    GROUP_ID => 'group'
];

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
$update = null;  // Initialize update variable

// ==============================
// DELAY TYPING FUNCTION
// ==============================
function sendTypingAction($chat_id) {
    // "Typing..." action show karta hai
    if (TYPING_DELAY_ENABLED) {
        $delay = rand(TYPING_DELAY_MIN, TYPING_DELAY_MAX);
        
        // Logging for debugging
        bot_log("Typing delay: {$delay}s for chat: $chat_id");
        
        // Send typing action
        apiRequest('sendChatAction', [
            'chat_id' => $chat_id,
            'action' => 'typing'
        ]);
        
        return $delay;
    }
    return 0;
}

// ==============================
// HELPER FUNCTION FOR DIRECT LINKS
// ==============================
function get_direct_channel_link($message_id, $channel_id = CHANNEL_1_ID) {
    // Telegram direct link generate karta hai
    $channel_id_clean = str_replace('-100', '', $channel_id);
    return "https://t.me/c/" . $channel_id_clean . "/" . $message_id;
}

function get_channel_username_link($channel_id) {
    // Channel ID se username link generate karta hai
    global $CHANNEL_MAP;
    $username = $CHANNEL_MAP[$channel_id] ?? '';
    if ($username && strpos($username, '@') === 0) {
        return "https://t.me/" . substr($username, 1);
    }
    return "https://t.me/c/" . str_replace('-100', '', $channel_id);
}

// ==============================
// FILE INITIALIZATION FUNCTION
// ==============================
function initialize_files() {
    // Sab required files create karta hai agar nahi hain toh
    $files = [
        CSV_FILE => "movie_name,message_id,channel_info\n",  // NEW CSV FORMAT
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
            @chmod($file, 0644);  // Read/write permissions - FIXED SECURITY
        }
    }
    
    // Backup directory create karo
    if (!file_exists(BACKUP_DIR)) {
        @mkdir(BACKUP_DIR, 0755, true);  // Secure permissions
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
// CSV MANAGEMENT FUNCTIONS - NEW FORMAT
// ==============================
function load_and_clean_csv($filename = CSV_FILE) {
    global $movie_messages;
    
    // CSV file check karo, agar nahi hai toh create karo
    if (!file_exists($filename)) {
        file_put_contents($filename, "movie_name,message_id,channel_info\n");
        return [];
    }

    $data = [];
    $handle = fopen($filename, "r");
    if ($handle !== FALSE) {
        $header = fgetcsv($handle);  // Header read karo
        
        // Har row ko process karo
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && (!empty(trim($row[0])))) {
                $movie_name = trim($row[0]);
                $message_id_raw = isset($row[1]) ? trim($row[1]) : '';
                $channel_info = isset($row[2]) ? trim($row[2]) : '';
                
                // Parse channel info (could be username or channel_id)
                $channel_id = '';
                $channel_username = '';
                
                if (strpos($channel_info, '@') === 0) {
                    // It's a username
                    $channel_username = $channel_info;
                    // Find channel ID from username
                    $channel_id = get_channel_id_from_username($channel_info);
                } elseif (is_numeric($channel_info) || strpos($channel_info, '-100') === 0) {
                    // It's a channel ID
                    $channel_id = $channel_info;
                    $channel_username = get_username_from_channel_id($channel_info);
                }
                
                // Movie entry create karo
                $entry = [
                    'movie_name' => $movie_name,
                    'message_id_raw' => $message_id_raw,
                    'channel_info' => $channel_info,
                    'channel_id' => $channel_id,
                    'channel_username' => $channel_username,
                    'channel_type' => get_channel_type($channel_id)
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

    // CSV clean karo aur rewrite karo
    $handle = fopen($filename, "w");
    fputcsv($handle, array('movie_name', 'message_id', 'channel_info'));
    foreach ($data as $row) {
        fputcsv($handle, [
            $row['movie_name'], 
            $row['message_id_raw'], 
            $row['channel_info']
        ]);
    }
    fclose($handle);

    bot_log("CSV cleaned and reloaded - " . count($data) . " entries");
    return $data;
}

function get_channel_id_from_username($username) {
    // Username se channel ID find karo
    global $CHANNEL_MAP;
    foreach ($CHANNEL_MAP as $id => $uname) {
        if ($uname == $username) {
            return $id;
        }
    }
    return CHANNEL_1_ID; // Default
}

function get_username_from_channel_id($channel_id) {
    // Channel ID se username find karo
    global $CHANNEL_MAP;
    return $CHANNEL_MAP[$channel_id] ?? '';
}

function get_channel_type($channel_id) {
    // Channel ID se channel type find karo
    global $CHANNEL_TYPE_MAP;
    return $CHANNEL_TYPE_MAP[$channel_id] ?? 'unknown';
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
    
    // Typing delay add karo
    $delay = sendTypingAction($chat_id);
    if ($delay > 0) {
        bot_log("Sleeping for {$delay} seconds before sending message");
        sleep($delay);  // ✅ ACTUAL DELAY HAI YEH
    }
    
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
    // Message forward karta hai (FORWARD HEADER DIKHEGA)
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
// MOVIE DELIVERY SYSTEM - WITH FORWARD HEADERS FROM ALL CHANNELS
// ==============================
function deliver_item_to_chat($chat_id, $item, $prefer_forward = true) {
    // Movie user ko deliver karta hai - WITH FORWARD HEADERS
    
    // Determine source channel
    $channel_id = $item['channel_id'] ?? CHANNEL_1_ID;
    $channel_username = $item['channel_username'] ?? '';
    
    // Agar valid message ID hai toh FORWARD KARO (forward header dikhayega)
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        if ($prefer_forward) {
            // FORWARD MESSAGE use karo - yeh forward header dikhayega
            $result = json_decode(forwardMessage($chat_id, $channel_id, $item['message_id']), true);
            
            if ($result && $result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("Movie FORWARDED from $channel_id: {$item['movie_name']} to $chat_id");
                return true;
            } else {
                // Forward nahi ho paya toh copy try karo as fallback
                $fallback_result = json_decode(copyMessage($chat_id, $channel_id, $item['message_id']), true);
                
                if ($fallback_result && $fallback_result['ok']) {
                    update_stats('total_downloads', 1);
                    bot_log("Movie COPIED (fallback) from $channel_id: {$item['movie_name']} to $chat_id");
                    return true;
                }
            }
        } else {
            // Direct copy try karo (no forward header)
            $result = json_decode(copyMessage($chat_id, $channel_id, $item['message_id']), true);
            
            if ($result && $result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("Movie COPIED from $channel_id: {$item['movie_name']} to $chat_id");
                return true;
            } else {
                // Copy nahi ho paya toh forward try karo
                $fallback_result = json_decode(forwardMessage($chat_id, $channel_id, $item['message_id']), true);
                
                if ($fallback_result && $fallback_result['ok']) {
                    update_stats('total_downloads', 1);
                    bot_log("Movie FORWARDED (fallback) from $channel_id: {$item['movie_name']} to $chat_id");
                    return true;
                }
            }
        }
    }
    
    // Agar message ID nahi hai ya numeric nahi hai
    if (!empty($item['message_id_raw'])) {
        // Raw message ID se try karo
        $message_id_clean = preg_replace('/[^0-9]/', '', $item['message_id_raw']);
        if (is_numeric($message_id_clean) && $message_id_clean > 0) {
            // Pehle forward try karo
            $result = json_decode(forwardMessage($chat_id, $channel_id, $message_id_clean), true);
            
            if ($result && $result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("Movie FORWARDED (raw ID) from $channel_id: {$item['movie_name']} to $chat_id");
                return true;
            } else {
                // Fallback to copy
                $fallback_result = json_decode(copyMessage($chat_id, $channel_id, $message_id_clean), true);
                
                if ($fallback_result && $fallback_result['ok']) {
                    update_stats('total_downloads', 1);
                    bot_log("Movie COPIED (raw ID) from $channel_id: {$item['movie_name']} to $chat_id");
                    return true;
                }
            }
        }
    }

    // Agar koi bhi method kaam na kare toh text info bhejo
    $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n";
    $text .= "📎 Reference: " . htmlspecialchars($item['message_id_raw'] ?? 'N/A') . "\n";
    $text .= "📢 Channel: " . htmlspecialchars($channel_username ?: $channel_id) . "\n\n";
    
    // Direct link provide karo
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        $channel_id_clean = str_replace('-100', '', $channel_id);
        $text .= "🔗 Direct Link: https://t.me/c/" . $channel_id_clean . "/{$item['message_id']}\n\n";
    }
    
    $text .= "⚠️ Join channel to access content: " . $channel_username;
    
    sendMessage($chat_id, $text, null, 'HTML');
    update_stats('total_downloads', 1);
    return false;
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
    
    // Channel search detection
    $search_channels = [];
    $channel_keywords = [
        'main' => ['main', 'entertainment', 'tadka'],
        'theater' => ['theater', 'theatre', 'print', 'hdcam', 'camrip'],
        'backup' => ['backup', 'etbackup'],
        'private' => ['private'],
        'any' => ['any', 'forwarded']
    ];
    
    // Remove channel keywords from query
    foreach ($channel_keywords as $type => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($query_lower, $keyword) !== false) {
                $search_channels[] = $type;
                $query_lower = str_replace($keyword, '', $query_lower);
            }
        }
    }
    $query_lower = trim($query_lower);
    
    // Har movie ke against query match karo
    foreach ($movie_messages as $movie => $entries) {
        $score = 0;
        
        // Channel type matching
        foreach ($entries as $entry) {
            $entry_type = $entry['channel_type'] ?? 'main';
            if (!empty($search_channels) && in_array($entry_type, $search_channels)) {
                $score += 20;  // Channel match bonus
            }
        }
        
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
        
        if ($score > 0) {
            $results[$movie] = [
                'score' => $score,
                'count' => count($entries),
                'latest_entry' => end($entries),
                'channels' => array_unique(array_column($entries, 'channel_type')),
                'has_main' => in_array('main', array_column($entries, 'channel_type')),
                'has_theater' => in_array('theater', array_column($entries, 'channel_type')),
                'has_backup' => in_array('backup', array_column($entries, 'channel_type')),
                'has_private' => in_array('private', array_column($entries, 'channel_type'))
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
            'found' => "✅ Mil gayi! Movie info bhej raha hoon...",
            'not_found' => "😔 Yeh movie abhi available nahi hai!\n\n📝 Aap ise request kar sakte hain: " . REQUEST_CHANNEL . "\n\n🔔 Jab bhi yeh add hogi, main automatically bhej dunga!",
            'searching' => "🔍 Dhoondh raha hoon... Zara wait karo",
            'multiple_found' => "🎯 Kai versions mili hain! Aap konsi chahte hain?",
            'request_success' => "✅ Request receive ho gayi! Hum jald hi add karenge.",
            'request_limit' => "❌ Aaj ke liye aap maximum " . DAILY_REQUEST_LIMIT . " requests hi kar sakte hain."
        ],
        'english' => [
            'welcome' => "🎬 Boss, which movie are you looking for?",
            'found' => "✅ Found it! Sending movie info...",
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
            $channel_info = "";
            if ($data['has_main']) $channel_info .= "🍿 ";
            if ($data['has_theater']) $channel_info .= "🎭 ";
            if ($data['has_backup']) $channel_info .= "💾 ";
            if ($data['has_private']) $channel_info .= "🔒 ";
            
            $msg .= "$i. $movie ($channel_info" . $data['count'] . " versions)\n";
            $i++;
            if ($i > 10) break;
        }
        
        sendMessage($chat_id, $msg);
        
        // Inline keyboard banayega top matches ke liye
        $keyboard = ['inline_keyboard' => []];
        $top_movies = array_slice(array_keys($found), 0, 5);
        
        foreach ($top_movies as $movie) {
            $movie_data = $found[$movie];
            $channel_icon = '🎬';
            if ($movie_data['has_theater']) $channel_icon = '🎭';
            elseif ($movie_data['has_main']) $channel_icon = '🍿';
            elseif ($movie_data['has_backup']) $channel_icon = '💾';
            elseif ($movie_data['has_private']) $channel_icon = '🔒';
            
            $keyboard['inline_keyboard'][] = [[ 
                'text' => $channel_icon . ucwords($movie), 
                'callback_data' => $movie 
            ]];
        }
        
        // Request button add karo
        $keyboard['inline_keyboard'][] = [[
            'text' => "📝 Request Different Movie", 
            'callback_data' => 'request_movie'
        ]];
        
        sendMessage($chat_id, "🚀 Top matches (click for info):", $keyboard);
        
        // ==================== SMART SUGGESTION CODE ====================
        if(count($found) > 0) {
            $buttons = [];
            foreach(array_slice(array_keys($found), 0, 5) as $movie){
                $buttons[] = [['text'=>$movie, 'callback_data'=>'movie_'.$movie]];
            }

            $reply_markup = ['inline_keyboard'=>$buttons];
            sendMessage($chat_id, "🎬 Did you mean:", $reply_markup);
        }
        // ==================== END SMART SUGGESTION ====================
        
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
// ENHANCED PAGINATION SYSTEM
// ==============================

function paginate_movies(array $all, int $page, array $filters = []): array {
    // Apply filters if any
    if (!empty($filters)) {
        $all = apply_movie_filters($all, $filters);
    }
    
    $total = count($all);
    if ($total === 0) {
        return [
            'total' => 0,
            'total_pages' => 1, 
            'page' => 1,
            'slice' => [],
            'filters' => $filters,
            'has_next' => false,
            'has_prev' => false,
            'start_item' => 0,
            'end_item' => 0
        ];
    }
    
    $total_pages = (int)ceil($total / ITEMS_PER_PAGE);
    $page = max(1, min($page, $total_pages));
    $start = ($page - 1) * ITEMS_PER_PAGE;
    
    return [
        'total' => $total,
        'total_pages' => $total_pages,
        'page' => $page,
        'slice' => array_slice($all, $start, ITEMS_PER_PAGE),
        'filters' => $filters,
        'has_next' => $page < $total_pages,
        'has_prev' => $page > 1,
        'start_item' => $start + 1,
        'end_item' => min($start + ITEMS_PER_PAGE, $total)
    ];
}

function build_totalupload_keyboard(int $page, int $total_pages, string $session_id = '', array $filters = []): array {
    $kb = ['inline_keyboard' => []];
    
    // Enhanced navigation with page numbers
    $nav_row = [];
    
    // Previous/Fast Previous buttons
    if ($page > 1) {
        $nav_row[] = ['text' => '⏪', 'callback_data' => 'pag_first_' . $session_id];
        $nav_row[] = ['text' => '◀️', 'callback_data' => 'pag_prev_' . $page . '_' . $session_id];
    }
    
    // Smart page number display (max 7 pages)
    $start_page = max(1, $page - 3);
    $end_page = min($total_pages, $start_page + 6);
    
    if ($end_page - $start_page < 6) {
        $start_page = max(1, $end_page - 6);
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $page) {
            $nav_row[] = ['text' => "【{$i}】", 'callback_data' => 'current'];
        } else {
            $nav_row[] = ['text' => "{$i}", 'callback_data' => 'pag_' . $i . '_' . $session_id];
        }
    }
    
    // Next/Fast Next buttons
    if ($page < $total_pages) {
        $nav_row[] = ['text' => '▶️', 'callback_data' => 'pag_next_' . $page . '_' . $session_id];
        $nav_row[] = ['text' => '⏩', 'callback_data' => 'pag_last_' . $total_pages . '_' . $session_id];
    }
    
    if (!empty($nav_row)) {
        $kb['inline_keyboard'][] = $nav_row;
    }
    
    // Action buttons row
    $action_row = [];
    $action_row[] = ['text' => '📥 Send Page', 'callback_data' => 'send_' . $page . '_' . $session_id];
    $action_row[] = ['text' => '👁️ Preview', 'callback_data' => 'prev_' . $page . '_' . $session_id];
    $action_row[] = ['text' => '📊 Stats', 'callback_data' => 'stats_' . $session_id];
    
    $kb['inline_keyboard'][] = $action_row;
    
    // Filter buttons row
    if (empty($filters)) {
        $filter_row = [];
        $filter_row[] = ['text' => '🎬 HD Only', 'callback_data' => 'flt_hd_' . $session_id];
        $filter_row[] = ['text' => '🎭 Theater Only', 'callback_data' => 'flt_theater_' . $session_id];
        $filter_row[] = ['text' => '🔥 Popular', 'callback_data' => 'flt_pop_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
    } else {
        $filter_row = [];
        $filter_row[] = ['text' => '🧹 Clear Filter', 'callback_data' => 'flt_clr_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
    }
    
    // Control buttons row
    $ctrl_row = [];
    $ctrl_row[] = ['text' => '💾 Save', 'callback_data' => 'save_' . $session_id];
    $ctrl_row[] = ['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''];
    $ctrl_row[] = ['text' => '❌ Close', 'callback_data' => 'close_' . $session_id];
    
    $kb['inline_keyboard'][] = $ctrl_row;
    
    return $kb;
}

function totalupload_controller($chat_id, $page = 1, $filters = [], $session_id = null) {
    $all = get_all_movies_list();
    if (empty($all)) {
        sendMessage($chat_id, "📭 Koi movies nahi mili! Pehle kuch movies add karo.");
        return;
    }
    
    // Create session ID if not provided
    if (!$session_id) {
        $session_id = uniqid('sess_', true);
    }
    
    $pg = paginate_movies($all, (int)$page, $filters);
    
    // Send preview for first page
    if ($page == 1 && PREVIEW_ITEMS > 0 && count($pg['slice']) > 0) {
        $preview_msg = "👁️ <b>Quick Preview (First " . PREVIEW_ITEMS . "):</b>\n\n";
        $preview_count = min(PREVIEW_ITEMS, count($pg['slice']));
        
        for ($i = 0; $i < $preview_count; $i++) {
            $movie = $pg['slice'][$i];
            $channel_icon = get_channel_icon($movie['channel_type'] ?? 'main');
            $preview_msg .= ($i + 1) . ". $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
            $preview_msg .= "   📢 " . ($movie['channel_username'] ?? 'Unknown') . "\n\n";
        }
        
        sendMessage($chat_id, $preview_msg, null, 'HTML');
    }
    
    // Build enhanced message
    $title = "🎬 <b>Enhanced Movie Browser</b>\n\n";
    
    // Session info
    $title .= "🆔 <b>Session:</b> <code>" . substr($session_id, 0, 8) . "</code>\n";
    
    // Statistics
    $title .= "📊 <b>Statistics:</b>\n";
    $title .= "• Total Movies: <b>{$pg['total']}</b>\n";
    $title .= "• Page: <b>{$pg['page']}/{$pg['total_pages']}</b>\n";
    $title .= "• Items: <b>{$pg['start_item']}-{$pg['end_item']}</b>\n";
    
    // Filter info
    if (!empty($filters)) {
        $title .= "• Filters: <b>" . count($filters) . " active</b>\n";
    }
    
    $title .= "\n";
    
    // Current page movies list
    $title .= "📋 <b>Page {$page} Movies:</b>\n\n";
    $i = $pg['start_item'];
    foreach ($pg['slice'] as $movie) {
        $movie_name = htmlspecialchars($movie['movie_name'] ?? 'Unknown');
        $channel_username = $movie['channel_username'] ?? 'Unknown';
        $channel_type = $movie['channel_type'] ?? 'main';
        $message_id = $movie['message_id_raw'] ?? 'N/A';
        $channel_icon = get_channel_icon($channel_type);
        
        $title .= "<b>{$i}.</b> $channel_icon {$movie_name}\n";
        $title .= "   📢 {$channel_username} | 🆔 {$message_id}\n";
        $title .= "   🏷️ " . ucfirst($channel_type) . " Channel\n\n";
        $i++;
    }
    
    // Navigation help
    $title .= "📍 <i>Use number buttons for direct page access</i>\n";
    $title .= "🔧 <i>Apply filters using buttons below</i>";
    
    // Build enhanced keyboard
    $kb = build_totalupload_keyboard($pg['page'], $pg['total_pages'], $session_id, $filters);
    
    // Delete previous pagination message if exists
    delete_pagination_message($chat_id, $session_id);
    
    // Save new message ID
    $result = sendMessage($chat_id, $title, $kb, 'HTML');
    save_pagination_message($chat_id, $session_id, $result['result']['message_id']);
    
    bot_log("Enhanced pagination - Chat: $chat_id, Page: $page, Session: " . substr($session_id, 0, 8));
}

function get_channel_icon($channel_type) {
    // Channel type se icon return karo
    switch($channel_type) {
        case 'main': return '🍿';
        case 'theater': return '🎭';
        case 'backup': return '💾';
        case 'backup2': return '📁';
        case 'private': return '🔒';
        case 'any': return '🔀';
        case 'group': return '👥';
        default: return '🎬';
    }
}

// ==============================
// PAGINATION HELPER FUNCTIONS
// ==============================

function apply_movie_filters($movies, $filters) {
    if (empty($filters)) return $movies;
    
    $filtered = [];
    foreach ($movies as $movie) {
        $pass = true;
        
        foreach ($filters as $key => $value) {
            switch ($key) {
                case 'channel':
                    if (($movie['channel_type'] ?? 'main') != $value) {
                        $pass = false;
                    }
                    break;
                    
                case 'channel_id':
                    if ($movie['channel_id'] != $value) {
                        $pass = false;
                    }
                    break;
                    
                case 'has_message_id':
                    if (empty($movie['message_id'])) {
                        $pass = false;
                    }
                    break;
            }
            
            if (!$pass) break;
        }
        
        if ($pass) {
            $filtered[] = $movie;
        }
    }
    
    return $filtered;
}

function save_pagination_message($chat_id, $session_id, $message_id) {
    global $user_pagination_sessions;
    
    if (!isset($user_pagination_sessions[$session_id])) {
        $user_pagination_sessions[$session_id] = [];
    }
    
    $user_pagination_sessions[$session_id]['last_message_id'] = $message_id;
    $user_pagination_sessions[$session_id]['chat_id'] = $chat_id;
    $user_pagination_sessions[$session_id]['last_updated'] = time();
}

function delete_pagination_message($chat_id, $session_id) {
    global $user_pagination_sessions;
    
    if (isset($user_pagination_sessions[$session_id]) && 
        isset($user_pagination_sessions[$session_id]['last_message_id'])) {
        
        $message_id = $user_pagination_sessions[$session_id]['last_message_id'];
        deleteMessage($chat_id, $message_id);
    }
}

function batch_download_with_progress($chat_id, $movies, $page_num) {
    $total = count($movies);
    if ($total === 0) return;
    
    $progress_msg = sendMessage($chat_id, "📦 <b>Batch Info Started</b>\n\nPage: {$page_num}\nTotal: {$total} movies\n\n⏳ Initializing...");
    $progress_id = $progress_msg['result']['message_id'];
    
    $success = 0;
    $failed = 0;
    
    for ($i = 0; $i < $total; $i++) {
        $movie = $movies[$i];
        
        // Update progress every 2 movies
        if ($i % 2 == 0) {
            $progress = round(($i / $total) * 100);
            editMessage($chat_id, $progress_id, 
                "📦 <b>Sending Page {$page_num} Info</b>\n\n" .
                "Progress: {$progress}%\n" .
                "Processed: {$i}/{$total}\n" .
                "✅ Success: {$success}\n" .
                "❌ Failed: {$failed}\n\n" .
                "⏳ Please wait..."
            );
        }
        
        try {
            // Default: FORWARD with header
            $result = deliver_item_to_chat($chat_id, $movie, true);
            if ($result) {
                $success++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
        }
        
        usleep(500000); // 0.5 second delay
    }
    
    // Final update
    editMessage($chat_id, $progress_id,
        "✅ <b>Batch Info Complete</b>\n\n" .
        "📄 Page: {$page_num}\n" .
        "🎬 Total: {$total} movies\n" .
        "✅ Successfully sent: {$success}\n" .
        "❌ Failed: {$failed}\n\n" .
        "📊 Success rate: " . round(($success / $total) * 100, 2) . "%\n" .
        "⏱️ Time: " . date('H:i:s') . "\n\n" .
        "🔗 Join channel to download: " . MAIN_CHANNEL
    );
}

// ==============================
// GET ALL MOVIES LIST FUNCTION
// ==============================
function get_all_movies_list() {
    // All movies list return karta hai
    return get_cached_movies();
}

// ==============================
// ADD MOVIE COMMAND FUNCTION - FIXED VERSION
// ==============================
function add_movie_command($chat_id, $user_id, $params) {
    // /addmovie command handler - FIXED VERSION
    
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    bot_log("/addmovie called with params: " . json_encode($params));
    
    // Format: /addmovie "Movie Name" message_id channel_info
    if (count($params) < 3) {
        $help_msg = "📝 <b>/addmovie Usage:</b>\n\n";
        $help_msg .= "<code>/addmovie \"Movie Name\" message_id channel_info</code>\n\n";
        $help_msg .= "📌 <b>Examples:</b>\n";
        $help_msg .= "<code>/addmovie \"Avengers Endgame\" 1234 @EntertainmentTadka786</code>\n";
        $help_msg .= "<code>/addmovie \"KGF 2\" 5678 -1003181705395</code>\n";
        $help_msg .= "<code>/addmovie \"Pushpa\" 91011 theater</code>\n\n";
        $help_msg .= "📢 <b>Available Channels:</b>\n";
        $help_msg .= "• @EntertainmentTadka786 (main)\n";
        $help_msg .= "• @ETBackup (backup)\n";
        $help_msg .= "• @threater_print_movies (theater)\n";
        $help_msg .= "• -1002337293281 (backup2)\n";
        $help_msg .= "• -1003251791991 (private)\n";
        $help_msg .= "• -1003614546520 (any)\n";
        $help_msg .= "• @EntertainmentTadka7860 (group)\n\n";
        $help_msg .= "💡 <b>Tip:</b> Use channel username or channel ID";
        
        sendMessage($chat_id, $help_msg, null, 'HTML');
        return;
    }
    
    // Parse parameters
    $movie_name = $params[0];
    $message_id = $params[1];
    $channel_info = $params[2];
    
    // Remove quotes if present
    if (strpos($movie_name, '"') === 0 && strrpos($movie_name, '"') === strlen($movie_name) - 1) {
        $movie_name = trim($movie_name, '"');
    }
    
    // Validate message ID
    if (!is_numeric($message_id)) {
        sendMessage($chat_id, "❌ Invalid message ID. Must be numeric.");
        return;
    }
    
    // Process channel info
    $channel_id = '';
    $channel_username = '';
    
    if (strpos($channel_info, '@') === 0) {
        // It's a username
        $channel_username = $channel_info;
        $channel_id = get_channel_id_from_username($channel_info);
        
        if (!$channel_id) {
            sendMessage($chat_id, "❌ Unknown channel username. Use one from the list.");
            return;
        }
    } elseif (is_numeric($channel_info) || strpos($channel_info, '-100') === 0) {
        // It's a channel ID
        $channel_id = $channel_info;
        $channel_username = get_username_from_channel_id($channel_info);
        
        if (!$channel_username) {
            // If no username found, use the ID itself
            $channel_username = $channel_id;
        }
    } else {
        // Try to map from type
        $type_map = [
            'main' => CHANNEL_1_ID,
            'theater' => CHANNEL_3_ID,
            'backup' => CHANNEL_2_ID,
            'backup2' => CHANNEL_4_ID,
            'private' => CHANNEL_5_ID,
            'any' => CHANNEL_6_ID,
            'group' => GROUP_ID
        ];
        
        if (isset($type_map[strtolower($channel_info)])) {
            $channel_id = $type_map[strtolower($channel_info)];
            $channel_username = get_username_from_channel_id($channel_id);
        } else {
            sendMessage($chat_id, "❌ Invalid channel info. Use username, channel ID, or type (main/theater/backup/etc.)");
            return;
        }
    }
    
    // Add to CSV
    $entry = [$movie_name, $message_id, $channel_username];
    $handle = fopen(CSV_FILE, "a");
    if ($handle !== FALSE) {
        fputcsv($handle, $entry);
        fclose($handle);
        
        // Clear cache
        global $movie_cache, $movie_messages;
        $movie_cache = [];
        
        // Add to movie_messages array
        $movie_lower = strtolower($movie_name);
        if (!isset($movie_messages[$movie_lower])) {
            $movie_messages[$movie_lower] = [];
        }
        
        $movie_messages[$movie_lower][] = [
            'movie_name' => $movie_name,
            'message_id_raw' => $message_id,
            'message_id' => intval($message_id),
            'channel_info' => $channel_username,
            'channel_id' => $channel_id,
            'channel_username' => $channel_username,
            'channel_type' => get_channel_type($channel_id)
        ];
        
        // Update stats
        update_stats('total_movies', 1);
        
        $success_msg = "✅ <b>Movie Added Successfully!</b>\n\n";
        $success_msg .= "🎬 <b>Movie:</b> $movie_name\n";
        $success_msg .= "🆔 <b>Message ID:</b> $message_id\n";
        $success_msg .= "📢 <b>Channel:</b> $channel_username\n";
        $success_msg .= "🔗 <b>Channel ID:</b> $channel_id\n";
        $success_msg .= "🏷️ <b>Type:</b> " . get_channel_type($channel_id) . "\n\n";
        $success_msg .= "📊 Total movies now: " . (get_stats()['total_movies'] ?? 0);
        
        sendMessage($chat_id, $success_msg, null, 'HTML');
        bot_log("Movie manually added by admin: $movie_name ($message_id) to $channel_username");
        
    } else {
        sendMessage($chat_id, "❌ Error: Could not open CSV file for writing.");
    }
}

// ==============================
// BULK ADD MOVIES FUNCTION
// ==============================
function bulk_add_movies($chat_id, $text) {
    // Bulk movies add karta hai (one per line)
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $lines = explode("\n", $text);
    $added_count = 0;
    $error_count = 0;
    
    $progress_msg = sendMessage($chat_id, "📦 Processing bulk add...\n\n0/" . count($lines) . " lines");
    $progress_id = $progress_msg['result']['message_id'];
    
    $handle = fopen(CSV_FILE, "a");
    if ($handle === FALSE) {
        editMessage($chat_id, $progress_id, "❌ Error: Could not open CSV file.");
        return;
    }
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;
        
        // Format: "Movie Name",message_id,channel_info
        $parts = explode(',', $line);
        if (count($parts) < 3) {
            $error_count++;
            continue;
        }
        
        $movie_name = trim($parts[0], ' "\'');
        $message_id = trim($parts[1]);
        $channel_info = trim($parts[2]);
        
        // Validate
        if (empty($movie_name) || !is_numeric($message_id) || empty($channel_info)) {
            $error_count++;
            continue;
        }
        
        // Add to CSV
        fputcsv($handle, [$movie_name, $message_id, $channel_info]);
        $added_count++;
        
        // Update progress every 5 lines
        if ($i % 5 == 0) {
            $progress = round(($i / count($lines)) * 100);
            editMessage($chat_id, $progress_id, 
                "📦 Processing bulk add...\n\n" .
                "Progress: {$progress}%\n" .
                "Processed: {$i}/" . count($lines) . " lines\n" .
                "✅ Added: {$added_count}\n" .
                "❌ Errors: {$error_count}"
            );
        }
    }
    
    fclose($handle);
    
    // Clear cache
    global $movie_cache;
    $movie_cache = [];
    
    // Reload movies
    load_and_clean_csv();
    
    editMessage($chat_id, $progress_id,
        "✅ <b>Bulk Add Complete!</b>\n\n" .
        "📊 <b>Results:</b>\n" .
        "• Total lines: " . count($lines) . "\n" .
        "✅ Successfully added: {$added_count}\n" .
        "❌ Errors/Skipped: {$error_count}\n\n" .
        "🎬 Total movies now: " . (get_stats()['total_movies'] ?? 0) . "\n" .
        "🔄 Cache cleared and reloaded"
    );
    
    bot_log("Bulk add completed: $added_count movies added, $error_count errors");
}

// ==============================
// BACKUP SYSTEM - COMPLETE IMPLEMENTATION
// ==============================
function auto_backup() {
    // Automatic backup process
    bot_log("Starting auto-backup process...");
    
    $backup_files = [CSV_FILE, USERS_FILE, STATS_FILE, REQUEST_FILE, LOG_FILE];
    $backup_dir = BACKUP_DIR . date('Y-m-d_H-i-s');
    $backup_success = true;
    
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    // 1. Local file backup
    foreach ($backup_files as $file) {
        if (file_exists($file)) {
            $backup_path = $backup_dir . '/' . basename($file) . '.bak';
            if (!copy($file, $backup_path)) {
                bot_log("Failed to backup: $file", 'ERROR');
                $backup_success = false;
            } else {
                bot_log("Backed up: $file");
            }
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
        } else {
            bot_log("Failed to upload backup to channel", 'WARNING');
        }
    }
    
    // 4. Clean old backups
    clean_old_backups();
    
    // 5. Send backup report to admin
    send_backup_report($backup_success, $summary);
    
    bot_log("Auto-backup process completed");
    return $backup_success;
}

function create_backup_summary() {
    // Backup summary create karta hai
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    
    $summary = "📊 BACKUP SUMMARY\n";
    $summary .= "================\n\n";
    
    $summary .= "📅 Backup Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "🤖 Bot: Entertainment Tadka\n\n";
    
    $summary .= "📈 STATISTICS:\n";
    $summary .= "• Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $summary .= "• Total Users: " . count($users_data['users'] ?? []) . "\n";
    $summary .= "• Total Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $summary .= "• Total Downloads: " . ($stats['total_downloads'] ?? 0) . "\n";
    $summary .= "• Pending Requests: " . count($requests_data['requests'] ?? []) . "\n\n";
    
    $summary .= "💾 FILES BACKED UP:\n";
    $summary .= "• " . CSV_FILE . " (" . (file_exists(CSV_FILE) ? filesize(CSV_FILE) : 0) . " bytes)\n";
    $summary .= "• " . USERS_FILE . " (" . (file_exists(USERS_FILE) ? filesize(USERS_FILE) : 0) . " bytes)\n";
    $summary .= "• " . STATS_FILE . " (" . (file_exists(STATS_FILE) ? filesize(STATS_FILE) : 0) . " bytes)\n";
    $summary .= "• " . REQUEST_FILE . " (" . (file_exists(REQUEST_FILE) ? filesize(REQUEST_FILE) : 0) . " bytes)\n";
    $summary .= "• " . LOG_FILE . " (" . (file_exists(LOG_FILE) ? filesize(LOG_FILE) : 0) . " bytes)\n\n";
    
    $summary .= "🔄 Backup Type: Automated Daily Backup\n";
    $summary .= "📍 Stored In: " . BACKUP_DIR . "\n";
    $summary .= "📡 Channel: " . BACKUP_CHANNEL_USERNAME . "\n";
    
    return $summary;
}

function upload_backup_to_channel($backup_dir, $summary) {
    // Backup Telegram channel pe upload karta hai
    try {
        // 1. Backup summary message send karo
        $summary_message = "🔄 <b>Daily Auto-Backup Report</b>\n\n";
        $summary_message .= "📅 " . date('Y-m-d H:i:s') . "\n\n";
        
        $stats = get_stats();
        $users_data = json_decode(file_get_contents(USERS_FILE), true);
        
        $summary_message .= "📊 <b>Current Stats:</b>\n";
        $summary_message .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
        $summary_message .= "• 👥 Users: " . count($users_data['users'] ?? []) . "\n";
        $summary_message .= "• 🔍 Searches: " . ($stats['total_searches'] ?? 0) . "\n";
        $summary_message .= "• 📥 Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
        
        $summary_message .= "✅ <b>Backup Status:</b> Successful\n";
        $summary_message .= "📁 <b>Location:</b> " . $backup_dir . "\n";
        $summary_message .= "💾 <b>Files:</b> 5 data files\n";
        $summary_message .= "📡 <b>Channel:</b> " . BACKUP_CHANNEL_USERNAME . "\n\n";
        
        $summary_message .= "🔗 <a href=\"https://t.me/ETBackup\">Visit Backup Channel</a>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📡 Visit ' . BACKUP_CHANNEL_USERNAME, 'url' => 'https://t.me/ETBackup']
                ]
            ]
        ];
        
        $message_result = sendMessage(CHANNEL_2_ID, $summary_message, $keyboard, 'HTML');
        
        if (!$message_result || !isset($message_result['ok']) || !$message_result['ok']) {
            bot_log("Failed to send backup summary to channel", 'ERROR');
            return false;
        }
        
        // 2. Critical files as documents upload karo
        $critical_files = [
            CSV_FILE => "🎬 Movies Database",
            USERS_FILE => "👥 Users Data", 
            STATS_FILE => "📊 Bot Statistics",
            REQUEST_FILE => "📝 Movie Requests"
        ];
        
        foreach ($critical_files as $file => $description) {
            if (file_exists($file)) {
                $upload_success = upload_file_to_channel($file, $backup_dir, $description);
                if (!$upload_success) {
                    bot_log("Failed to upload $file to channel", 'WARNING');
                }
                sleep(2); // Rate limiting
            }
        }
        
        // 3. Zip archive create karo aur upload karo
        $zip_success = create_and_upload_zip($backup_dir);
        
        // 4. Completion message send karo
        $completion_message = "✅ <b>Backup Process Completed</b>\n\n";
        $completion_message .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $completion_message .= "💾 All files backed up successfully\n";
        $completion_message .= "📦 Zip archive created\n";
        $completion_message .= "📡 Uploaded to: " . BACKUP_CHANNEL_USERNAME . "\n\n";
        $completion_message .= "🛡️ <i>Your data is now securely backed up!</i>";
        
        sendMessage(CHANNEL_2_ID, $completion_message, null, 'HTML');
        
        return true;
        
    } catch (Exception $e) {
        bot_log("Channel backup failed: " . $e->getMessage(), 'ERROR');
        
        // Error report send karo backup channel pe
        $error_message = "❌ <b>Backup Process Failed</b>\n\n";
        $error_message .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $error_message .= "🚨 Error: " . $e->getMessage() . "\n\n";
        $error_message .= "⚠️ Please check server logs immediately!";
        
        sendMessage(CHANNEL_2_ID, $error_message, null, 'HTML');
        
        return false;
    }
}

function upload_file_to_channel($file_path, $backup_dir, $description = "") {
    // Individual file channel pe upload karta hai
    if (!file_exists($file_path)) {
        return false;
    }
    
    $file_name = basename($file_path);
    $backup_file_path = $backup_dir . '/' . $file_name . '.bak';
    
    if (!file_exists($backup_file_path)) {
        return false;
    }
    
    $file_size = filesize($backup_file_path);
    $file_size_mb = round($file_size / (1024 * 1024), 2);
    $backup_time = date('Y-m-d H:i:s');
    
    $caption = "💾 " . $description . "\n";
    $caption .= "📅 " . $backup_time . "\n";
    $caption .= "📊 Size: " . $file_size_mb . " MB\n";
    $caption .= "🔄 Auto-backup\n";
    $caption .= "📡 " . BACKUP_CHANNEL_USERNAME;
    
    // Large files ke liye (Telegram limit 50MB)
    if ($file_size > 45 * 1024 * 1024) { // 45MB limit
        bot_log("File too large for Telegram: $file_name ($file_size_mb MB)", 'WARNING');
        
        // Large CSV files ko split karo
        if ($file_name == 'movies.csv') {
            return split_and_upload_large_csv($backup_file_path, $backup_dir, $description);
        }
        return false;
    }
    
    $post_fields = [
        'chat_id' => CHANNEL_2_ID,
        'document' => new CURLFile($backup_file_path),
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result_data = json_decode($result, true);
    $success = ($http_code == 200 && $result_data && $result_data['ok']);
    
    if ($success) {
        bot_log("Uploaded to channel: $file_name");
        
        // Large files ke liye confirmation message
        if ($file_size > 10 * 1024 * 1024) {
            $confirmation = "✅ <b>Large File Uploaded</b>\n\n";
            $confirmation .= "📁 File: " . $description . "\n";
            $confirmation .= "💾 Size: " . $file_size_mb . " MB\n";
            $confirmation .= "✅ Status: Successfully uploaded to " . BACKUP_CHANNEL_USERNAME;
            sendMessage(CHANNEL_2_ID, $confirmation, null, 'HTML');
        }
    } else {
        bot_log("Failed to upload to channel: $file_name", 'ERROR');
    }
    
    return $success;
}

function split_and_upload_large_csv($csv_file_path, $backup_dir, $description) {
    // Large CSV files ko split karke upload karta hai
    if (!file_exists($csv_file_path)) {
        return false;
    }
    
    $file_size = filesize($csv_file_path);
    $file_size_mb = round($file_size / (1024 * 1024), 2);
    
    bot_log("Splitting large CSV file: $file_size_mb MB", 'INFO');
    
    // CSV file read karo
    $rows = [];
    $handle = fopen($csv_file_path, 'r');
    if ($handle !== FALSE) {
        $header = fgetcsv($handle); // Header read karo
        while (($row = fgetcsv($handle)) !== FALSE) {
            $rows[] = $row;
        }
        fclose($handle);
    }
    
    $total_rows = count($rows);
    $rows_per_file = ceil($total_rows / 3); // 3 parts mein split karo
    
    $upload_success = true;
    
    for ($i = 0; $i < 3; $i++) {
        $start = $i * $rows_per_file;
        $end = min($start + $rows_per_file, $total_rows);
        $part_rows = array_slice($rows, $start, $end - $start);
        
        // Part file create karo
        $part_file = $backup_dir . '/movies_part_' . ($i + 1) . '.csv';
        $part_handle = fopen($part_file, 'w');
        fputcsv($part_handle, $header);
        foreach ($part_rows as $row) {
            fputcsv($part_handle, $row);
        }
        fclose($part_handle);
        
        // Part file upload karo
        $part_caption = "💾 " . $description . " (Part " . ($i + 1) . "/3)\n";
        $part_caption .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $part_caption .= "📊 Rows: " . count($part_rows) . "\n";
        $part_caption .= "🔄 Split backup\n";
        $part_caption .= "📡 " . BACKUP_CHANNEL_USERNAME;
        
        $post_fields = [
            'chat_id' => CHANNEL_2_ID,
            'document' => new CURLFile($part_file),
            'caption' => $part_caption,
            'parse_mode' => 'HTML'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Part file clean up karo
        @unlink($part_file);
        
        if ($http_code != 200) {
            $upload_success = false;
            bot_log("Failed to upload CSV part " . ($i + 1), 'ERROR');
        } else {
            bot_log("Uploaded CSV part " . ($i + 1));
        }
        
        sleep(2); // Rate limiting
    }
    
    // Split completion message send karo
    if ($upload_success) {
        $split_message = "📦 <b>Large CSV Split Successfully</b>\n\n";
        $split_message .= "📁 File: " . $description . "\n";
        $split_message .= "💾 Original Size: " . $file_size_mb . " MB\n";
        $split_message .= "📊 Total Rows: " . $total_rows . "\n";
        $split_message .= "🔀 Split into: 3 parts\n";
        $split_message .= "✅ All parts uploaded to " . BACKUP_CHANNEL_USERNAME;
        
        sendMessage(CHANNEL_2_ID, $split_message, null, 'HTML');
    }
    
    return $upload_success;
}

function create_and_upload_zip($backup_dir) {
    // Zip archive create aur upload karta hai
    $zip_file = $backup_dir . '/complete_backup.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
        bot_log("Cannot open zip file: $zip_file", 'ERROR');
        return false;
    }
    
    // Files zip mein add karo
    $files = glob($backup_dir . '/*.bak');
    foreach ($files as $file) {
        $zip->addFile($file, basename($file));
    }
    
    // Summary file add karo
    if (file_exists($backup_dir . '/backup_summary.txt')) {
        $zip->addFile($backup_dir . '/backup_summary.txt', 'backup_summary.txt');
    }
    
    $zip->close();
    
    $zip_size = filesize($zip_file);
    $zip_size_mb = round($zip_size / (1024 * 1024), 2);
    
    // Zip file upload karo
    $caption = "📦 Complete Backup Archive\n";
    $caption .= "📅 " . date('Y-m-d H:i:s') . "\n";
    $caption .= "💾 Size: " . $zip_size_mb . " MB\n";
    $caption .= "📁 Contains all data files\n";
    $caption .= "🔄 Auto-generated backup\n";
    $caption .= "📡 " . BACKUP_CHANNEL_USERNAME;
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔗 ' . BACKUP_CHANNEL_USERNAME, 'url' => 'https://t.me/ETBackup']
            ]
        ]
    ];
    
    $post_fields = [
        'chat_id' => CHANNEL_2_ID,
        'document' => new CURLFile($zip_file),
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Zip file clean up karo
    @unlink($zip_file);
    
    $success = ($http_code == 200);
    
    if ($success) {
        bot_log("Zip backup uploaded to channel successfully");
        
        // Zip upload confirmation send karo
        $zip_confirmation = "✅ <b>Zip Archive Uploaded</b>\n\n";
        $zip_confirmation .= "📦 File: Complete Backup Archive\n";
        $zip_confirmation .= "💾 Size: " . $zip_size_mb . " MB\n";
        $zip_confirmation .= "✅ Status: Successfully uploaded\n";
        $zip_confirmation .= "📡 Channel: " . BACKUP_CHANNEL_USERNAME . "\n\n";
        $zip_confirmation .= "🛡️ <i>All data securely backed up!</i>";
        
        sendMessage(CHANNEL_2_ID, $zip_confirmation, $keyboard, 'HTML');
    } else {
        bot_log("Failed to upload zip backup to channel", 'WARNING');
    }
    
    return $success;
}

function clean_old_backups() {
    // Purane backups delete karta hai (last 7 rakhta hai)
    $old = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    if (count($old) > 7) {
        usort($old, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $deleted_count = 0;
        foreach (array_slice($old, 0, count($old) - 7) as $d) {
            $files = glob($d . '/*');
            foreach ($files as $ff) @unlink($ff);
            if (@rmdir($d)) {
                $deleted_count++;
                bot_log("Deleted old backup: $d");
            }
        }
        
        bot_log("Cleaned $deleted_count old backups");
    }
}

function send_backup_report($success, $summary) {
    // Admin ko backup report send karta hai
    $report_message = "🔄 <b>Backup Completion Report</b>\n\n";
    
    if ($success) {
        $report_message .= "✅ <b>Status:</b> SUCCESS\n";
        $report_message .= "📅 <b>Time:</b> " . date('Y-m-d H:i:s') . "\n";
        $report_message .= "📡 <b>Channel:</b> " . BACKUP_CHANNEL_USERNAME . "\n\n";
    } else {
        $report_message .= "❌ <b>Status:</b> FAILED\n";
        $report_message .= "📅 <b>Time:</b> " . date('Y-m-d H:i:s') . "\n";
        $report_message .= "📡 <b>Channel:</b> " . BACKUP_CHANNEL_USERNAME . "\n\n";
        $report_message .= "⚠️ Some backup operations may have failed. Check logs for details.\n\n";
    }
    
    // Summary stats add karo
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $report_message .= "📊 <b>Current System Status:</b>\n";
    $report_message .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $report_message .= "• 👥 Users: " . count($users_data['users'] ?? []) . "\n";
    $report_message .= "• 🔍 Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $report_message .= "• 📥 Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
    
    $report_message .= "💾 <b>Backup Locations:</b>\n";
    $report_message .= "• Local: " . BACKUP_DIR . "\n";
    $report_message .= "• Channel: " . BACKUP_CHANNEL_USERNAME . "\n\n";
    
    $report_message .= "🕒 <b>Next Backup:</b> " . AUTO_BACKUP_HOUR . ":00 daily";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📡 Visit Backup Channel', 'url' => 'https://t.me/ETBackup'],
                ['text' => '📊 Backup Status', 'callback_data' => 'backup_status']
            ]
        ]
    ];
    
    sendMessage(ADMIN_ID, $report_message, $keyboard, 'HTML');
}

// ==============================
// MANUAL BACKUP COMMANDS
// ==============================
function manual_backup($chat_id) {
    // Manual backup command handler
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $progress_msg = sendMessage($chat_id, "🔄 Starting manual backup...");
    
    try {
        $success = auto_backup();
        
        if ($success) {
            editMessage($chat_id, $progress_msg['result']['message_id'], "✅ Manual backup completed successfully!\n\n📊 Backup has been saved locally and uploaded to backup channel.");
        } else {
            editMessage($chat_id, $progress_msg['result']['message_id'], "⚠️ Backup completed with some warnings.\n\nSome files may not have been backed up properly. Check logs for details.");
        }
        
    } catch (Exception $e) {
        editMessage($chat_id, $progress_msg['result']['message_id'], "❌ Backup failed!\n\nError: " . $e->getMessage());
        bot_log("Manual backup failed: " . $e->getMessage(), 'ERROR');
    }
}

function quick_backup($chat_id) {
    // Quick backup command handler
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $progress_msg = sendMessage($chat_id, "💾 Creating quick backup...");
    
    try {
        // Quick backup - only essential files
        $essential_files = [CSV_FILE, USERS_FILE];
        $backup_dir = BACKUP_DIR . 'quick_' . date('Y-m-d_H-i-s');
        
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        
        foreach ($essential_files as $file) {
            if (file_exists($file)) {
                copy($file, $backup_dir . '/' . basename($file) . '.bak');
            }
        }
        
        // Channel pe upload karo
        $summary = "🚀 Quick Backup\n" . date('Y-m-d H:i:s') . "\nEssential files only";
        file_put_contents($backup_dir . '/quick_backup_info.txt', $summary);
        
        foreach ($essential_files as $file) {
            $backup_file = $backup_dir . '/' . basename($file) . '.bak';
            if (file_exists($backup_file)) {
                upload_file_to_channel($file, $backup_dir);
                sleep(1);
            }
        }
        
        editMessage($chat_id, $progress_msg['result']['message_id'], "✅ Quick backup completed!\n\nEssential files backed up to channel.");
        
    } catch (Exception $e) {
        editMessage($chat_id, $progress_msg['result']['message_id'], "❌ Quick backup failed!\n\nError: " . $e->getMessage());
    }
}

// ==============================
// BACKUP STATUS & INFO COMMANDS
// ==============================
function backup_status($chat_id) {
    // Backup status show karta hai
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
    
    $total_size_mb = round($total_size / (1024 * 1024), 2);
    
    $status_message = "💾 <b>Backup System Status</b>\n\n";
    
    $status_message .= "📊 <b>Storage Info:</b>\n";
    $status_message .= "• Total Backups: " . count($backup_dirs) . "\n";
    $status_message .= "• Storage Used: " . $total_size_mb . " MB\n";
    $status_message .= "• Backup Channel: " . BACKUP_CHANNEL_USERNAME . "\n";
    $status_message .= "• Channel ID: " . CHANNEL_2_ID . "\n\n";
    
    if ($latest_backup) {
        $latest_time = date('Y-m-d H:i:s', filemtime($latest_backup));
        $status_message .= "🕒 <b>Latest Backup:</b>\n";
        $status_message .= "• Time: " . $latest_time . "\n";
        $status_message .= "• Folder: " . basename($latest_backup) . "\n\n";
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
            ]
        ]
    ];
    
    sendMessage($chat_id, $status_message, $keyboard, 'HTML');
}

// ==============================
// CHANNEL MANAGEMENT FUNCTIONS
// ==============================
function show_channel_info($chat_id) {
    // All channels ka information show karta hai
    $message = "📢 <b>Join Our Channels</b>\n\n";
    
    $message .= "🍿 <b>Main Channel:</b> " . CHANNEL_1_USERNAME . "\n";
    $message .= "• Latest movie updates\n";
    $message .= "• Daily new additions\n";
    $message .= "• High quality prints\n";
    $message .= "• Direct downloads\n\n";
    
    $message .= "📥 <b>Requests Group:</b> " . GROUP_USERNAME . "\n";
    $message .= "• Movie requests\n";
    $message .= "• Bug reports\n";
    $message .= "• Feature suggestions\n";
    $message .= "• Support & help\n\n";
    
    $message .= "🎭 <b>Theater Prints:</b> " . CHANNEL_3_USERNAME . "\n";
    $message .= "• Theater quality prints\n";
    $message .= "• HD screen recordings\n";
    $message .= "• Latest theater prints\n\n";
    
    $message .= "🔒 <b>Backup Channel:</b> " . CHANNEL_2_USERNAME . "\n";
    $message .= "• Secure data backups\n";
    $message .= "• System archives\n";
    $message .= "• Database copies\n";
    $message .= "• Admin only access\n\n";
    
    $message .= "📁 <b>Backup Channel 2:</b> " . CHANNEL_4_USERNAME . "\n";
    $message .= "• Additional backups\n";
    $message .= "• Extra storage\n\n";
    
    $message .= "🔒 <b>Private Channel:</b> " . CHANNEL_5_USERNAME . "\n";
    $message .= "• Exclusive content\n";
    $message .= "• Private uploads\n\n";
    
    $message .= "🔀 <b>Any Forwarded Channel:</b> " . CHANNEL_6_USERNAME . "\n";
    $message .= "• Forwarded content\n";
    $message .= "• External sources\n\n";
    
    $message .= "🔔 <b>Don't forget to join all channels!</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🍿 ' . CHANNEL_1_USERNAME, 'url' => 'https://t.me/EntertainmentTadka786'],
                ['text' => '📥 ' . GROUP_USERNAME, 'url' => 'https://t.me/EntertainmentTadka7860']
            ],
            [
                ['text' => '🎭 ' . CHANNEL_3_USERNAME, 'url' => 'https://t.me/threater_print_movies'],
                ['text' => '🔒 ' . CHANNEL_2_USERNAME, 'url' => 'https://t.me/ETBackup']
            ],
            [
                ['text' => '📁 ' . CHANNEL_4_USERNAME, 'url' => 'https://t.me/c/2337293281'],
                ['text' => '🔒 ' . CHANNEL_5_USERNAME, 'url' => 'https://t.me/c/3251791991']
            ],
            [
                ['text' => '🔀 ' . CHANNEL_6_USERNAME, 'url' => 'https://t.me/c/3614546520']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_main_channel_info($chat_id) {
    // Main channel ka detailed information
    $message = "🍿 <b>Main Channel - " . CHANNEL_1_USERNAME . "</b>\n\n";
    
    $message .= "🎬 <b>What you get:</b>\n";
    $message .= "• Latest Bollywood & Hollywood movies\n";
    $message .= "• HD/1080p/720p quality prints\n";
    $message .= "• Daily new uploads\n";
    $message .= "• Multiple server links\n";
    $message .= "• Fast direct downloads\n";
    $message .= "• No ads, no spam\n\n";
    
    $message .= "📊 <b>Current Stats:</b>\n";
    $stats = get_stats();
    $message .= "• Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $message .= "• Active Users: " . get_active_users_count() . "\n";
    $message .= "• Daily Uploads: " . get_daily_uploads_count() . "\n\n";
    
    $message .= "🔔 <b>Join now for latest movies!</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🍿 Join Main Channel', 'url' => 'https://t.me/EntertainmentTadka786'],
                ['text' => '📥 Request Movies', 'url' => 'https://t.me/EntertainmentTadka7860']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_request_channel_info($chat_id) {
    // Request channel ka detailed information
    $message = "📥 <b>Requests Group - " . GROUP_USERNAME . "</b>\n\n";
    
    $message .= "🎯 <b>How to request movies:</b>\n";
    $message .= "1. Join this group first\n";
    $message .= "2. Use <code>/request movie_name</code> in bot\n";
    $message .= "3. Or post directly in group\n";
    $message .= "4. We'll add within 24 hours\n\n";
    
    $message .= "📝 <b>Also available:</b>\n";
    $message .= "• Bug reports & issues\n";
    $message .= "• Feature suggestions\n";
    $message .= "• General support\n";
    $message .= "• Bot help & guidance\n\n";
    
    $message .= "⚠️ <b>Please check these before requesting:</b>\n";
    $message .= "• Search in bot first\n";
    $message .= "• Check spelling\n";
    $message .= "• Use correct movie name\n";
    $message .= "• Be patient for uploads\n\n";
    
    $message .= "🔔 <b>Auto-notification:</b> You'll get notified when requested movies are added!";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Join Requests Group', 'url' => 'https://t.me/EntertainmentTadka7860'],
                ['text' => '🎬 Request via Bot', 'callback_data' => 'request_help']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_theater_channel_info($chat_id) {
    // Theater channel ka detailed information
    $message = "🎭 <b>Theater Prints - " . CHANNEL_3_USERNAME . "</b>\n\n";
    
    $message .= "🎥 <b>What you get:</b>\n";
    $message .= "• Latest theater prints\n";
    $message .= "• HD screen recordings\n";
    $message .= "• Best quality available\n";
    $message .= "• Fast uploads after release\n";
    $message .= "• Multiple quality options\n\n";
    
    $message .= "⭐ <b>Features:</b>\n";
    $message .= "• 1080p theater prints\n";
    $message .= "• Clear audio quality\n";
    $message .= "• No watermarks\n";
    $message .= "• Multiple languages\n\n";
    
    $message .= "📥 <b>How to access:</b>\n";
    $message .= "1. Join " . CHANNEL_3_USERNAME . "\n";
    $message .= "2. Search in bot\n";
    $message .= "3. Get message IDs\n";
    $message .= "4. Download from channel\n\n";
    
    $message .= "🎬 <b>For the best viewing experience!</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🎭 Join Theater Channel', 'url' => 'https://t.me/threater_print_movies'],
                ['text' => '🔍 Search Theater Movies', 'callback_data' => 'search_theater']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_backup_channel_info($chat_id) {
    // Backup channel ka detailed information
    $message = "🔒 <b>Backup Channel - " . CHANNEL_2_USERNAME . "</b>\n\n";
    
    $message .= "🛡️ <b>Purpose:</b>\n";
    $message .= "• Secure data backups\n";
    $message .= "• Database protection\n";
    $message .= "• System recovery\n";
    $message .= "• Disaster prevention\n\n";
    
    $message .= "💾 <b>What's backed up:</b>\n";
    $message .= "• Movies database (" . get_csv_count() . " movies)\n";
    $message .= "• Users data (" . get_users_count() . " users)\n";
    $message .= "• Bot statistics\n";
    $message .= "• Request history\n";
    $message .= "• Complete system archives\n\n";
    
    $message .= "⏰ <b>Backup Schedule:</b>\n";
    $message .= "• Automatic: Daily at " . AUTO_BACKUP_HOUR . ":00\n";
    $message .= "• Manual: On admin command\n";
    $message .= "• Retention: Last 7 backups\n\n";
    
    $message .= "🔐 <b>Note:</b> This is a private channel for admin use only.";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔒 ' . CHANNEL_2_USERNAME, 'url' => 'https://t.me/ETBackup'],
                ['text' => '📊 Backup Status', 'callback_data' => 'backup_status']
            ]
        ]
    ];
    
    if ($chat_id == ADMIN_ID) {
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    } else {
        sendMessage($chat_id, "🔒 <b>Backup Channel</b>\n\nThis is a private admin-only channel for data protection.", null, 'HTML');
    }
}

// ==============================
// HELPER FUNCTIONS FOR CHANNEL INFO
// ==============================
function get_active_users_count() {
    // Active users count karta hai (last 7 days)
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $active_count = 0;
    $one_week_ago = strtotime('-1 week');
    
    foreach ($users_data['users'] ?? [] as $user) {
        if (strtotime($user['last_active'] ?? '') >= $one_week_ago) {
            $active_count++;
        }
    }
    
    return $active_count;
}

function get_daily_uploads_count() {
    // Daily uploads count karta hai
    $today = date('d-m-Y');
    $count = 0;
    
    $handle = fopen(CSV_FILE, 'r');
    if ($handle !== FALSE) {
        fgetcsv($handle); // skip header
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 2 && !empty(trim($row[0]))) {
                $count++;
            }
        }
        fclose($handle);
    }
    
    return $count;
}

function get_csv_count() {
    // CSV mein total movies count karta hai
    $count = 0;
    
    $handle = fopen(CSV_FILE, 'r');
    if ($handle !== FALSE) {
        fgetcsv($handle); // skip header
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 2 && !empty(trim($row[0]))) {
                $count++;
            }
        }
        fclose($handle);
    }
    
    return $count;
}

function get_users_count() {
    // Total users count karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    return count($users_data['users'] ?? []);
}

// ==============================
// USER STATS & LEADERBOARD FUNCTIONS
// ==============================
function show_user_stats($chat_id, $user_id) {
    // User ki statistics show karta hai
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

function show_user_points($chat_id, $user_id) {
    // User ke points show karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user = $users_data['users'][$user_id] ?? null;
    
    if (!$user) {
        sendMessage($chat_id, "❌ User data not found!");
        return;
    }
    
    $points = $user['points'] ?? 0;
    
    $message = "⭐ <b>Your Points</b>\n\n";
    $message .= "🎯 Total Points: <b>$points</b>\n\n";
    
    $message .= "📈 <b>How to earn points:</b>\n";
    $message .= "• 🔍 Daily search: +1 point\n";
    $message .= "• 📥 Movie download: +3 points\n";
    $message .= "• 📝 Movie request: +2 points\n";
    $message .= "• 🎯 Found movie: +5 points\n";
    $message .= "• 📅 Daily login: +10 points\n\n";
    
    $message .= "🏆 <b>Your Rank:</b> " . calculate_user_rank($points) . "\n";
    $message .= "📊 <b>Next Rank:</b> " . get_next_rank_info($points);
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_leaderboard($chat_id) {
    // Top users leaderboard show karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $users = $users_data['users'] ?? [];
    
    if (empty($users)) {
        sendMessage($chat_id, "📭 Koi user data nahi mila!");
        return;
    }
    
    // Points ke hisab se sort karo
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

function calculate_user_rank($points) {
    // Points ke hisab se user rank calculate karta hai
    if ($points >= 1000) return "🎖️ Elite";
    if ($points >= 500) return "🔥 Pro";
    if ($points >= 250) return "⭐ Advanced";
    if ($points >= 100) return "🚀 Intermediate";
    if ($points >= 50) return "👍 Beginner";
    return "🌱 Newbie";
}

function get_next_rank_info($points) {
    // Next rank ke liye required points batata hai
    if ($points < 50) return "Beginner (50 points needed)";
    if ($points < 100) return "Intermediate (100 points needed)";
    if ($points < 250) return "Advanced (250 points needed)";
    if ($points < 500) return "Pro (500 points needed)";
    if ($points < 1000) return "Elite (1000 points needed)";
    return "Max Rank Achieved! 🏆";
}

// ==============================
// BROWSE COMMANDS
// ==============================
function show_latest_movies($chat_id, $limit = 10) {
    global $update;
    $page = 1;
    
    // Callback handling
    if(isset($update['callback_query'])) {
        $callback = $update['callback_query']['data'];
        if(preg_match('/(next|prev)_(\d+)/', $callback, $matches)){
            $dir = $matches[1];
            $current = intval($matches[2]);
            $page = ($dir == 'next') ? $current + 1 : $current - 1;
            $chat_id = $update['callback_query']['message']['chat']['id'];
        }
    }

    if(!file_exists(CSV_FILE)){
        sendMessage($chat_id, "No movies uploaded yet.");
        return;
    }

    $csv = array_map('str_getcsv', file(CSV_FILE));
    $csv = array_reverse($csv); // Latest first
    $total = count($csv);
    $totalPages = ceil($total / ITEMS_PER_PAGE);
    if($page < 1) $page = 1;
    if($page > $totalPages) $page = $totalPages;

    $start = ($page-1) * ITEMS_PER_PAGE;
    $movies = array_slice($csv, $start, ITEMS_PER_PAGE);

    $text_msg = "<b>Latest Movies:</b>\n";
    foreach($movies as $i => $row){
        $text_msg .= ($start+$i+1).". <b>".htmlspecialchars($row[0])."</b> | Channel: ".htmlspecialchars($row[2] ?? 'N/A')."\n";
    }
    $text_msg .= "\nPage $page of $totalPages";

    // Inline buttons
    $buttons = [];
    $row_buttons = [];
    if($page > 1) $row_buttons[] = ['text'=>'⬅️ Prev', 'callback_data'=>'prev_'.$page];
    if($page < $totalPages) $row_buttons[] = ['text'=>'Next ➡️', 'callback_data'=>'next_'.$page];
    if(!empty($row_buttons)) $buttons[] = $row_buttons;

    $reply_markup = ['inline_keyboard'=>$buttons];
    sendMessage($chat_id, $text_msg, $reply_markup);
}

function show_trending_movies($chat_id) {
    // Trending movies show karta hai
    $all_movies = get_all_movies_list();
    
    // Simple trending logic (recent aur most downloaded)
    $trending_movies = array_slice($all_movies, -15); // Last 15 movies
    
    if (empty($trending_movies)) {
        sendMessage($chat_id, "📭 Koi trending movies nahi mili!");
        return;
    }
    
    $message = "🔥 <b>Trending Movies</b>\n\n";
    $i = 1;
    
    foreach (array_slice($trending_movies, 0, 10) as $movie) {
        $channel_icon = get_channel_icon($movie['channel_type'] ?? 'main');
        $message .= "$i. $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
        $message .= "   📢 " . ($movie['channel_username'] ?? 'Unknown') . "\n\n";
        $i++;
    }
    
    $message .= "💡 <i>Based on recent popularity and downloads</i>";
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_movies_by_channel($chat_id, $channel_type) {
    // Specific channel ki movies show karta hai
    $all_movies = get_all_movies_list();
    $filtered_movies = [];
    
    foreach ($all_movies as $movie) {
        if (($movie['channel_type'] ?? '') == $channel_type) {
            $filtered_movies[] = $movie;
        }
    }
    
    if (empty($filtered_movies)) {
        $channel_name = ucfirst($channel_type);
        sendMessage($chat_id, "❌ Koi $channel_name channel movies nahi mili!");
        return;
    }
    
    $message = "🎬 <b>" . ucfirst($channel_type) . " Channel Movies</b>\n\n";
    $message .= "📊 Total Found: " . count($filtered_movies) . "\n\n";
    
    $i = 1;
    foreach (array_slice($filtered_movies, 0, 10) as $movie) {
        $channel_icon = get_channel_icon($channel_type);
        $message .= "$i. $channel_icon " . htmlspecialchars($movie['movie_name']) . "\n";
        $i++;
    }
    
    if (count($filtered_movies) > 10) {
        $message .= "\n... and " . (count($filtered_movies) - 10) . " more";
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Get All Info', 'callback_data' => 'download_channel_' . $channel_type],
                ['text' => '🔄 Other Channels', 'callback_data' => 'show_channels']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

// ==============================
// REQUEST MANAGEMENT
// ==============================
function show_user_requests($chat_id, $user_id) {
    // User ke movie requests show karta hai
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $user_requests = [];
    
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id) {
            $user_requests[] = $request;
        }
    }
    
    if (empty($user_requests)) {
        sendMessage($chat_id, "📭 Aapne abhi tak koi movie request nahi ki hai!");
        return;
    }
    
    $message = "📝 <b>Your Movie Requests</b>\n\n";
    $i = 1;
    
    foreach (array_slice($user_requests, 0, 10) as $request) {
        $status_emoji = $request['status'] == 'completed' ? '✅' : '⏳';
        $message .= "$i. $status_emoji <b>" . htmlspecialchars($request['movie_name']) . "</b>\n";
        $message .= "   📅 " . $request['date'] . " | 🗣️ " . ucfirst($request['language']) . "\n";
        $message .= "   🆔 " . $request['id'] . "\n\n";
        $i++;
    }
    
    $pending_count = count(array_filter($user_requests, function($req) {
        return $req['status'] == 'pending';
    }));
    
    $message .= "📊 <b>Summary:</b>\n";
    $message .= "• Total Requests: " . count($user_requests) . "\n";
    $message .= "• Pending: $pending_count\n";
    $message .= "• Completed: " . (count($user_requests) - $pending_count);
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_request_limit($chat_id, $user_id) {
    // User ke request limit ka status show karta hai
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
        $message .= "💡 Use <code>/request movie_name</code> to request movies!";
    } else {
        $message .= "⏳ Limit resets at midnight!";
    }
    
    sendMessage($chat_id, $message, null, 'HTML');
}

// ==============================
// ADMIN COMMANDS
// ==============================
function admin_stats($chat_id) {
    // Complete bot statistics show karta hai
    
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }

    $total_users = 0;
    if(file_exists(USERS_FILE)){
        $users_data = json_decode(file_get_contents(USERS_FILE), true);
        $total_users = count($users_data['users'] ?? []);
    }

    $total_movies = 0;
    if(file_exists(CSV_FILE)){
        $csv = array_map('str_getcsv', file(CSV_FILE));
        $total_movies = count($csv) - 1; // Header exclude
    }

    $total_requests = 0;
    if(file_exists(REQUEST_FILE)){
        $req_data = json_decode(file_get_contents(REQUEST_FILE), true);
        $total_requests = count($req_data['requests'] ?? []);
    }

    $msg = "<b>📊 Bot Stats:</b>\n";
    $msg .= "Total Users: $total_users\n";
    $msg .= "Total Movies: $total_movies\n";
    $msg .= "Total Requests: $total_requests";

    sendMessage($chat_id, $msg);
    
    // Bot log
    bot_log("Admin stats viewed by $chat_id");
}

function show_csv_data($chat_id, $show_all = false) {
    // CSV data show karta hai
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
        $channel_info = $movie[2] ?? 'N/A';
        
        $message .= "$i. 🎬 " . htmlspecialchars($movie_name) . "\n";
        $message .= "   📝 ID: $message_id | 📢 $channel_info\n\n";
        
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
    // All users ko broadcast message send karta hai
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $total_users = count($users_data['users'] ?? []);
    $success_count = 0;
    
    $progress_msg = sendMessage($chat_id, "📢 Broadcasting to $total_users users...\n\nProgress: 0%");
    $progress_msg_id = $progress_msg['result']['message_id'];
    
    $i = 0;
    foreach ($users_data['users'] as $user_id => $user) {
        try {
            sendMessage($user_id, "📢 <b>Announcement from Admin:</b>\n\n$message", null, 'HTML');
            $success_count++;
            
            // Har 10 users ke baad progress update karo
            if ($i % 10 === 0) {
                $progress = round(($i / $total_users) * 100);
                editMessage($chat_id, $progress_msg_id, "📢 Broadcasting to $total_users users...\n\nProgress: $progress%");
            }
            
            usleep(100000); // 0.1 second delay
            $i++;
        } catch (Exception $e) {
            // Failed sends skip karo
        }
    }
    
    editMessage($chat_id, $progress_msg_id, "✅ Broadcast completed!\n\n📊 Sent to: $success_count/$total_users users");
    bot_log("Broadcast sent by $chat_id to $success_count users");
}

function toggle_maintenance_mode($chat_id, $mode) {
    // Maintenance mode toggle karta hai
    global $MAINTENANCE_MODE;
    
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    if ($mode == 'on') {
        $MAINTENANCE_MODE = true;
        sendMessage($chat_id, "🔧 Maintenance mode ENABLED\n\nBot is now in maintenance mode. Users will see maintenance message.");
        bot_log("Maintenance mode enabled by $chat_id");
    } elseif ($mode == 'off') {
        $MAINTENANCE_MODE = false;
        sendMessage($chat_id, "✅ Maintenance mode DISABLED\n\nBot is now operational.");
        bot_log("Maintenance mode disabled by $chat_id");
    } else {
        sendMessage($chat_id, "❌ Usage: <code>/maintenance on</code> or <code>/maintenance off</code>", null, 'HTML');
    }
}

function perform_cleanup($chat_id) {
    // System cleanup perform karta hai
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $stats_before = get_stats();
    
    // Purane backups clean karo
    $old = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    if (count($old) > 7) {
        usort($old, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $deleted_count = 0;
        foreach (array_slice($old, 0, count($old) - 7) as $d) {
            $files = glob($d . '/*');
            foreach ($files as $ff) @unlink($ff);
            if (@rmdir($d)) $deleted_count++;
        }
    }
    
    // Cache clean karo
    global $movie_cache;
    $movie_cache = [];
    
    sendMessage($chat_id, "🧹 Cleanup completed!\n\n• Old backups removed\n• Cache cleared\n• System optimized");
    bot_log("Cleanup performed by $chat_id");
}

function send_alert_to_all($chat_id, $alert_message) {
    // All users ko alert send karta hai
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $success_count = 0;
    
    foreach ($users_data['users'] as $user_id => $user) {
        try {
            sendMessage($user_id, "🚨 <b>Important Alert:</b>\n\n$alert_message", null, 'HTML');
            $success_count++;
            usleep(50000); // 0.05 second delay
        } catch (Exception $e) {
            // Failed sends skip karo
        }
    }
    
    sendMessage($chat_id, "✅ Alert sent to $success_count users!");
    bot_log("Alert sent by $chat_id: " . substr($alert_message, 0, 50));
}

// ==============================
// UTILITY FUNCTIONS
// ==============================
function check_date($chat_id) {
    // Movies upload dates ka record show karta hai
    if (!file_exists(CSV_FILE)) {
        sendMessage($chat_id, "⚠️ Abhi tak koi data save nahi hua.");
        return;
    }
    
    $date_counts = [];
    $h = fopen(CSV_FILE, 'r');
    
    if ($h !== FALSE) {
        fgetcsv($h);
        while (($r = fgetcsv($h)) !== FALSE) {
            if (count($r) >= 2) {
                $d = date('Y-m-d');
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
    // CSV testing ke liye raw data show karta hai
    if (!file_exists(CSV_FILE)) {
        sendMessage($chat_id, "⚠️ CSV file not found.");
        return;
    }
    
    $h = fopen(CSV_FILE, 'r');
    if ($h !== FALSE) {
        fgetcsv($h);
        $i = 1;
        $msg = "";
        
        while (($r = fgetcsv($h)) !== FALSE) {
            if (count($r) >= 3) {
                $channel_info = $r[2] ?? 'N/A';
                $line = "$i. 🎬 {$r[0]} | ID: {$r[1]} | Channel: {$channel_info}";
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
}

function show_bot_info($chat_id) {
    // Bot information show karta hai
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $message = "🤖 <b>Entertainment Tadka Bot</b>\n\n";
    $message .= "📱 <b>Version:</b> 3.0.0\n";
    $message .= "🆙 <b>Last Updated:</b> " . date('Y-m-d') . "\n";
    $message .= "👨‍💻 <b>Developer:</b> @EntertainmentTadka0786\n\n";
    
    $message .= "📊 <b>Bot Statistics:</b>\n";
    $message .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $message .= "• 👥 Users: " . count($users_data['users'] ?? []) . "\n";
    $message .= "• 🔍 Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $message .= "• 📥 Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
    
    $message .= "🎯 <b>Features:</b>\n";
    $message .= "• 6-channel forwarding support\n";
    $message .= "• Smart movie search\n";
    $message .= "• Multi-language support\n";
    $message .= "• Movie requests\n";
    $message .= "• User points system\n";
    $message .= "• Leaderboard\n";
    $message .= "• Delay typing feature\n";
    $message .= "• Admin movie adding\n\n";
    
    $message .= "📢 <b>Channels:</b>\n";
    $message .= "• Main: " . CHANNEL_1_USERNAME . "\n";
    $message .= "• Backup: " . CHANNEL_2_USERNAME . "\n";
    $message .= "• Theater: " . CHANNEL_3_USERNAME . "\n";
    $message .= "• Group: " . GROUP_USERNAME;
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_support_info($chat_id) {
    // Support information show karta hai
    $message = "🆘 <b>Support & Contact</b>\n\n";
    
    $message .= "📞 <b>Need Help?</b>\n";
    $message .= "• Movie not found?\n";
    $message .= "• Technical issues?\n";
    $message .= "• Feature requests?\n\n";
    
    $message .= "🎯 <b>Quick Solutions:</b>\n";
    $message .= "1. Use <code>/request movie_name</code> for new movies\n";
    $message .= "2. Check <code>/help</code> for all commands\n";
    $message .= "3. Join support group below\n\n";
    
    $message .= "📢 <b>Support Group:</b> " . GROUP_USERNAME . "\n";
    $message .= "👨‍💻 <b>Admin:</b> @EntertainmentTadka0786\n\n";
    
    $message .= "💡 <b>Pro Tip:</b> Always check spelling before reporting!";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📢 Support Group', 'url' => 'https://t.me/EntertainmentTadka0786'],
                ['text' => '🐛 Report Bug', 'callback_data' => 'report_bug']
            ],
            [
                ['text' => '💡 Suggest Feature', 'callback_data' => 'suggest_feature'],
                ['text' => '📝 Give Feedback', 'callback_data' => 'give_feedback']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_donate_info($chat_id) {
    // Donation information show karta hai
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
    
    $message .= "💌 <b>Contact for other methods:</b> " . GROUP_USERNAME;
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function submit_bug_report($chat_id, $user_id, $bug_report) {
    // Bug report submit karta hai
    $report_id = uniqid();
    
    $admin_message = "🐛 <b>New Bug Report</b>\n\n";
    $admin_message .= "🆔 Report ID: $report_id\n";
    $admin_message .= "👤 User ID: $user_id\n";
    $admin_message .= "📅 Time: " . date('Y-m-d H:i:s') . "\n\n";
    $admin_message .= "📝 <b>Bug Description:</b>\n$bug_report";
    
    sendMessage(ADMIN_ID, $admin_message, null, 'HTML');
    sendMessage($chat_id, "✅ Bug report submitted!\n\n🆔 Report ID: <code>$report_id</code>\n\nWe'll fix it soon! 🛠️", null, 'HTML');
    
    bot_log("Bug report submitted by $user_id: $report_id");
}

function submit_feedback($chat_id, $user_id, $feedback) {
    // User feedback submit karta hai
    $feedback_id = uniqid();
    
    $admin_message = "💡 <b>New User Feedback</b>\n\n";
    $admin_message .= "🆔 Feedback ID: $feedback_id\n";
    $admin_message .= "👤 User ID: $user_id\n";
    $admin_message .= "📅 Time: " . date('Y-m-d H:i:s') . "\n\n";
    $admin_message .= "📝 <b>Feedback:</b>\n$feedback";
    
    sendMessage(ADMIN_ID, $admin_message, null, 'HTML');
    sendMessage($chat_id, "✅ Feedback submitted!\n\n🆔 Feedback ID: <code>$feedback_id</code>\n\nThanks for your input! 🌟", null, 'HTML');
    
    bot_log("Feedback submitted by $user_id: $feedback_id");
}

function show_version_info($chat_id) {
    // Bot version information show karta hai
    $message = "🔄 <b>Bot Version Information</b>\n\n";
    
    $message .= "📱 <b>Current Version:</b> v3.0.0\n";
    $message .= "🆙 <b>Release Date:</b> " . date('Y-m-d') . "\n";
    $message .= "🐛 <b>Status:</b> Stable Release\n\n";
    
    $message .= "🎯 <b>What's New in v3.0.0:</b>\n";
    $message .= "• 6-channel forwarding support\n";
    $message .= "• New CSV format\n";
    $message .= "• /addmovie command\n";
    $message .= "• Delay typing feature\n";
    $message .= "• Enhanced search algorithm\n";
    $message .= "• Bug fixes & improvements\n\n";
    
    $message .= "📋 <b>Upcoming Features:</b>\n";
    $message .= "• Movie ratings & reviews\n";
    $message .= "• Watchlist feature\n";
    $message .= "• Advanced filters\n";
    $message .= "• User profiles\n";
    $message .= "• More coming soon...\n\n";
    
    $message .= "🐛 <b>Found a bug?</b> Use <code>/report</code>\n";
    $message .= "💡 <b>Suggestions?</b> Use <code>/feedback</code>";
    
    sendMessage($chat_id, $message, null, 'HTML');
}

// ==============================
// GROUP MESSAGE FILTER
// ==============================
function is_valid_movie_query($text) {
    // Group messages filter karta hai, valid movie queries hi allow karta hai
    $text = strtolower(trim($text));
    
    // Commands allow karo
    if (strpos($text, '/') === 0) {
        return true;
    }
    
    // Very short messages block karo
    if (strlen($text) < 3) {
        return false;
    }
    
    // Common group chat phrases block karo
    $invalid_phrases = [
        'good morning', 'good night', 'hello', 'hi ', 'hey ', 'thank you', 'thanks',
        'welcome', 'bye', 'see you', 'ok ', 'okay', 'yes', 'no', 'maybe',
        'how are you', 'whats up', 'anyone', 'someone', 'everyone',
        'problem', 'issue', 'help', 'question', 'doubt', 'query'
    ];
    
    foreach ($invalid_phrases as $phrase) {
        if (strpos($text, $phrase) !== false) {
            return false;
        }
    }
    
    // Movie-like patterns allow karo
    $movie_patterns = [
        'movie', 'film', 'video', 'download', 'watch', 'hd', 'full', 'part',
        'series', 'episode', 'season', 'bollywood', 'hollywood',
        'theater', 'theatre', 'print', 'hdcam', 'camrip'
    ];
    
    foreach ($movie_patterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return true;
        }
    }
    
    // Agar specific movie jaisa lagta hai
    if (preg_match('/^[a-zA-Z0-9\s\-\.\,]{3,}$/', $text)) {
        return true;
    }
    
    return false;
}

// ==============================
// MOVIE APPEND FUNCTION WITH AUTO-NOTIFICATION
// ==============================
function append_movie($movie_name, $message_id_raw, $channel_info, $channel_id = null) {
    // Movie database mein add karta hai - NEW FORMAT
    global $movie_messages, $movie_cache, $waiting_users;
    
    if (empty(trim($movie_name))) return;
    
    // Determine channel info
    if ($channel_id) {
        $channel_username = get_username_from_channel_id($channel_id);
        if (!$channel_username) {
            $channel_username = $channel_id;
        }
    } else {
        // Try to extract from channel_info
        if (strpos($channel_info, '@') === 0) {
            $channel_username = $channel_info;
            $channel_id = get_channel_id_from_username($channel_info);
        } elseif (is_numeric($channel_info) || strpos($channel_info, '-100') === 0) {
            $channel_id = $channel_info;
            $channel_username = get_username_from_channel_id($channel_info);
            if (!$channel_username) {
                $channel_username = $channel_id;
            }
        } else {
            // Default to main channel
            $channel_id = CHANNEL_1_ID;
            $channel_username = CHANNEL_1_USERNAME;
        }
    }
    
    $entry = [$movie_name, $message_id_raw, $channel_username];
    
    $handle = fopen(CSV_FILE, "a");
    fputcsv($handle, $entry);
    fclose($handle);

    $movie = strtolower(trim($movie_name));
    $item = [
        'movie_name' => $movie_name,
        'message_id_raw' => $message_id_raw,
        'channel_info' => $channel_username,
        'channel_id' => $channel_id,
        'channel_username' => $channel_username,
        'channel_type' => get_channel_type($channel_id),
        'message_id' => is_numeric($message_id_raw) ? intval($message_id_raw) : null
    ];
    
    if (!isset($movie_messages[$movie])) $movie_messages[$movie] = [];
    $movie_messages[$movie][] = $item;
    $movie_cache = [];

    // Auto-notification to waiting users
    $movie_lower = strtolower($movie_name);
    if (!empty($waiting_users[$movie_lower])) {
        $notification_msg = "🔔 <b>Movie Added!</b>\n\n";
        $notification_msg .= "🎬 <b>$movie_name</b> has been added to our collection!\n\n";
        $notification_msg .= "📢 Channel: $channel_username\n";
        $notification_msg .= "🔔 " . count($waiting_users[$movie_lower]) . " users were waiting for this movie!\n\n";
        $notification_msg .= "🆔 Message ID: $message_id_raw\n";
        $notification_msg .= "🏷️ Channel Type: " . get_channel_type($channel_id);
        
        sendMessage(CHANNEL_1_ID, $notification_msg, null, 'HTML');
        bot_log("Auto-notification sent for: $movie_name to " . count($waiting_users[$movie_lower]) . " users");
        
        // Waiting users ko notify karo
        foreach ($waiting_users[$movie_lower] as $user_data) {
            list($user_chat_id, $user_id) = $user_data;
            sendMessage($user_chat_id, "🎉 <b>Good News!</b>\n\nYour requested movie <b>$movie_name</b> has been added!\n\nChannel: $channel_username", null, 'HTML');
        }
        unset($waiting_users[$movie_lower]);
    }

    update_stats('total_movies', 1);
    bot_log("Movie appended: $movie_name with ID $message_id_raw to $channel_username");
}

// ==============================
// COMPLETE COMMAND HANDLER WITH UPDATED START MESSAGE
// ==============================
function handle_command($chat_id, $user_id, $command, $params = []) {
    // Sab commands handle karta hai
    
    // Global update variable access ke liye
    global $update;
    
    bot_log("Command received: $command with params: " . json_encode($params));
    
    switch ($command) {
        // ==================== CORE COMMANDS ====================
        case '/start':
            $welcome = "🎬 Welcome to Entertainment Tadka!\n\n";
            
            $welcome .= "📢 <b>How to use this bot:</b>\n";
            $welcome .= "• Simply type any movie name\n";
            $welcome .= "• Use English or Hindi\n";
            $welcome .= "• Add channel name for specific channel\n";
            $welcome .= "• Partial names also work\n\n";
            
            $welcome .= "🔍 <b>Examples:</b>\n";
            $welcome .= "• Avengers Endgame\n";
            $welcome .= "• KGF 2 theater\n";
            $welcome .= "• Hindi movie backup\n";
            $welcome .= "• Spider-man private\n";
            $welcome .= "• Any movie any\n\n";
            
            $welcome .= "📢 <b>Available Channels:</b>\n";
            $welcome .= "🍿 Main: @EntertainmentTadka786\n";
            $welcome .= "💾 Backup: @ETBackup\n";
            $welcome .= "🎭 Theater: @threater_print_movies\n";
            $welcome .= "📁 Backup2: -1002337293281\n";
            $welcome .= "🔒 Private: -1003251791991\n";
            $welcome .= "🔀 Any: -1003614546520\n";
            $welcome .= "👥 Group: @EntertainmentTadka7860\n\n";
            
            $welcome .= "🔔 <b>New Feature:</b> Forward headers from all channels!\n\n";
            
            $welcome .= "💬 <b>Need help?</b> Use /help for all commands";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => ''],
                        ['text' => '🍿 Main Channel', 'url' => 'https://t.me/EntertainmentTadka786']
                    ],
                    [
                        ['text' => '💾 Backup', 'url' => 'https://t.me/ETBackup'],
                        ['text' => '🎭 Theater', 'url' => 'https://t.me/threater_print_movies']
                    ],
                    [
                        ['text' => '👥 Group', 'url' => 'https://t.me/EntertainmentTadka7860'],
                        ['text' => '❓ Help', 'callback_data' => 'help_command']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $welcome, $keyboard, 'HTML');
            update_user_activity($user_id, 'daily_login');
            break;

        case '/help':
        case '/commands':
            $help = "🤖 <b>Entertainment Tadka Bot - Complete Guide</b>\n\n";
            
            $help .= "📢 <b>Our Channels:</b>\n";
            $help .= "🍿 Main: " . CHANNEL_1_USERNAME . " - Latest movies\n";
            $help .= "💾 Backup: " . CHANNEL_2_USERNAME . " - Data backups\n";
            $help .= "🎭 Theater: " . CHANNEL_3_USERNAME . " - HD prints\n";
            $help .= "📁 Backup2: " . CHANNEL_4_USERNAME . " - Extra storage\n";
            $help .= "🔒 Private: " . CHANNEL_5_USERNAME . " - Exclusive content\n";
            $help .= "🔀 Any: " . CHANNEL_6_USERNAME . " - Forwarded content\n";
            $help .= "👥 Group: " . GROUP_USERNAME . " - Support & requests\n\n";
            
            $help .= "🔔 <b>Auto-notification Feature:</b>\n";
            $help .= "• Request a movie in request group\n";
            $help .= "• We add it within 24 hours\n";
            $help .= "• Get auto-notification when added!\n";
            $help .= "• Join request group for updates\n\n";
            
            $help .= "🎯 <b>Search Commands:</b>\n";
            $help .= "• Just type movie name - Smart search\n";
            $help .= "• Add channel name for specific channel\n";
            $help .= "• <code>/search movie</code> - Direct search\n";
            $help .= "• <code>/s movie</code> - Quick search\n\n";
            
            $help .= "📁 <b>Browse Commands:</b>\n";
            $help .= "• <code>/totalupload</code> - All movies\n";
            $help .= "• <code>/latest</code> - New additions\n";
            $help .= "• <code>/trending</code> - Popular movies\n";
            $help .= "• <code>/theater</code> - Theater prints only\n";
            $help .= "• <code>/channel main</code> - Main channel movies\n";
            $help .= "• <code>/channel backup</code> - Backup channel movies\n\n";
            
            $help .= "📝 <b>Request Commands:</b>\n";
            $help .= "• <code>/request movie</code> - Request movie\n";
            $help .= "• <code>/myrequests</code> - Request status\n";
            $help .= "• Join " . GROUP_USERNAME . " for support\n\n";
            
            $help .= "👤 <b>User Commands:</b>\n";
            $help .= "• <code>/mystats</code> - Your statistics\n";
            $help .= "• <code>/leaderboard</code> - Top users\n";
            $help .= "• <code>/mypoints</code> - Points info\n\n";
            
            $help .= "🔗 <b>Channel Commands:</b>\n";
            $help .= "• <code>/channel</code> - All channels\n";
            $help .= "• <code>/mainchannel</code> - Main channel\n";
            $help .= "• <code>/requestchannel</code> - Requests\n";
            $help .= "• <code>/theaterchannel</code> - Theater prints\n";
            $help .= "• <code>/backupchannel</code> - Backup info\n\n";
            
            $help .= "🛠️ <b>Admin Commands:</b>\n";
            $help .= "• <code>/addmovie</code> - Add movie manually\n";
            $help .= "• <code>/stats</code> - Bot statistics\n";
            $help .= "• <code>/checkcsv</code> - View CSV data\n";
            $help .= "• <code>/backup</code> - Manual backup\n";
            $help .= "• <code>/broadcast</code> - Send to all users\n\n";
            
            $help .= "💡 <b>Pro Tips:</b>\n";
            $help .= "• Use partial names (e.g., 'aveng')\n";
            $help .= "• Add channel name for specific channel\n";
            $help .= "• Join all channels for updates\n";
            $help .= "• Request movies you can't find\n";
            $help .= "• Check spelling before reporting";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🍿 ' . CHANNEL_1_USERNAME, 'url' => 'https://t.me/EntertainmentTadka786'],
                        ['text' => '💾 ' . CHANNEL_2_USERNAME, 'url' => 'https://t.me/ETBackup']
                    ],
                    [
                        ['text' => '🎭 ' . CHANNEL_3_USERNAME, 'url' => 'https://t.me/threater_print_movies'],
                        ['text' => '👥 ' . GROUP_USERNAME, 'url' => 'https://t.me/EntertainmentTadka7860']
                    ],
                    [
                        ['text' => '🎬 Search Movies', 'switch_inline_query_current_chat' => '']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $help, $keyboard, 'HTML');
            break;

        // ==================== SEARCH COMMANDS ====================
        case '/search':
        case '/s':
        case '/find':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: <code>/search movie_name</code>\nExample: <code>/search kgf 2</code>", null, 'HTML');
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
            // ==================== YOUR TOTALUPLOADS CODE ====================
            $page = 1;

            // Callback pagination
            if(isset($update['callback_query'])) {
                $callback = $update['callback_query']['data'];
                if(preg_match('/(next|prev)_(\d+)/', $callback, $matches)){
                    $dir = $matches[1];
                    $current = intval($matches[2]);
                    $page = ($dir == 'next') ? $current + 1 : $current - 1;
                }
                $chat_id = $update['callback_query']['message']['chat']['id'];
            }

            // Read CSV
            if(!file_exists(CSV_FILE)){
                sendMessage($chat_id, "No movies uploaded yet.");
                break;
            }

            $csv = array_map('str_getcsv', file(CSV_FILE));
            $total = count($csv);
            $totalPages = ceil($total / ITEMS_PER_PAGE);
            if($page < 1) $page = 1;
            if($page > $totalPages) $page = $totalPages;

            $start = ($page-1) * ITEMS_PER_PAGE;
            $movies = array_slice($csv, $start, ITEMS_PER_PAGE);

            $text_msg = "";
            foreach($movies as $i => $row){
                $text_msg .= ($start+$i+1) . ". <b>".htmlspecialchars($row[0])."</b> | Channel: ".htmlspecialchars($row[2] ?? 'N/A')."\n";
            }
            $text_msg .= "\nPage $page of $totalPages";

            // Inline buttons
            $buttons = [];
            $row_buttons = [];
            if($page > 1) $row_buttons[] = ['text'=>'⬅️ Prev', 'callback_data'=>'prev_'.$page];
            if($page < $totalPages) $row_buttons[] = ['text'=>'Next ➡️', 'callback_data'=>'next_'.$page];
            if(!empty($row_buttons)) $buttons[] = $row_buttons;

            $reply_markup = ['inline_keyboard'=>$buttons];

            sendMessage($chat_id, $text_msg, $reply_markup);
            // ==================== END YOUR CODE ====================
            break;

        case '/latest':
        case '/recent':
        case '/new':
            // Call existing function that has your code integrated
            $limit = isset($params[0]) ? intval($params[0]) : 10;
            show_latest_movies($chat_id, $limit);
            break;

        case '/trending':
        case '/popular':
            show_trending_movies($chat_id);
            break;

        case '/channel':
            if (empty($params)) {
                show_channel_info($chat_id);
            } else {
                $channel_type = strtolower($params[0]);
                show_movies_by_channel($chat_id, $channel_type);
            }
            break;

        case '/theater':
        case '/theatermovies':
        case '/theateronly':
            show_movies_by_channel($chat_id, 'theater');
            break;

        case '/main':
        case '/mainmovies':
            show_movies_by_channel($chat_id, 'main');
            break;

        case '/backupmovies':
        case '/backup':
            show_movies_by_channel($chat_id, 'backup');
            break;

        // ==================== ADD MOVIE COMMAND - FIXED ====================
        case '/addmovie':
            bot_log("/addmovie command called by $user_id");
            add_movie_command($chat_id, $user_id, $params);
            break;

        case '/bulkadd':
            if ($chat_id == ADMIN_ID) {
                $text = isset($update['message']['text']) ? $update['message']['text'] : '';
                $text = str_replace('/bulkadd ', '', $text);
                bulk_add_movies($chat_id, $text);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        // ==================== CHANNEL COMMANDS ====================
        case '/theaterchannel':
        case '/theaterprints':
            show_theater_channel_info($chat_id);
            break;

        case '/backupchannel':
        case '/etbackup':
            show_backup_channel_info($chat_id);
            break;

        case '/mainchannel':
        case '/entertainmenttadka':
            show_main_channel_info($chat_id);
            break;

        case '/requestchannel':
        case '/requests':
            show_request_channel_info($chat_id);
            break;

        // ==================== REQUEST COMMANDS ====================
        case '/request':
        case '/req':
        case '/requestmovie':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: <code>/request movie_name</code>\nExample: <code>/request Animal Park</code>", null, 'HTML');
                return;
            }
            $lang = detect_language($movie_name);
            if (add_movie_request($user_id, $movie_name, $lang)) {
                send_multilingual_response($chat_id, 'request_success', $lang);
                update_user_activity($user_id, 'movie_request');
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
        case '/channels':
        case '/join':
            show_channel_info($chat_id);
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
            // ==================== YOUR STATS CODE ====================
            if($chat_id != ADMIN_ID){
                sendMessage($chat_id, "❌ You are not allowed to use this command.");
                break;
            }
            
            admin_stats($chat_id);
            break;

        case '/checkcsv':
        case '/csvdata':
        case '/database':
            $show_all = (isset($params[0]) && strtolower($params[0]) == 'all');
            show_csv_data($chat_id, $show_all);
            break;

        case '/testcsv':
        case '/rawdata':
        case '/export':
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
                    sendMessage($chat_id, "❌ Usage: <code>/broadcast your_message</code>", null, 'HTML');
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
            sendMessage($chat_id, "🏓 <b>Bot Status:</b> ✅ Online\n⏰ <b>Server Time:</b> " . date('Y-m-d H:i:s'), null, 'HTML');
            break;

        case '/donate':
        case '/supportus':
            show_donate_info($chat_id);
            break;

        case '/report':
        case '/reportbug':
            $bug_report = implode(' ', $params);
            if (empty($bug_report)) {
                sendMessage($chat_id, "❌ Usage: <code>/report bug_description</code>", null, 'HTML');
                return;
            }
            submit_bug_report($chat_id, $user_id, $bug_report);
            break;

        case '/feedback':
            $feedback = implode(' ', $params);
            if (empty($feedback)) {
                sendMessage($chat_id, "❌ Usage: <code>/feedback your_feedback</code>", null, 'HTML');
                return;
            }
            submit_feedback($chat_id, $user_id, $feedback);
            break;

        default:
            sendMessage($chat_id, "❌ Unknown command. Use <code>/help</code> to see all available commands.", null, 'HTML');
    }
}

// ==============================
// EMERGENCY /addmovie FIX - Added at top level
// ==============================
if (isset($update) && isset($update['message']['text'])) {
    $text = $update['message']['text'];
    
    // Emergency fix for /addmovie command
    if (strpos($text, '/addmovie') === 0 && strpos($text, '/addmovie') === 0) {
        $chat_id = $update['message']['chat']['id'];
        $user_id = $update['message']['from']['id'];
        
        bot_log("EMERGENCY FIX: /addmovie detected: $text");
        
        // Remove /addmovie from beginning
        $text = trim(substr($text, strlen('/addmovie')));
        
        // Parse with regex for quoted movie names
        $params = [];
        if (preg_match('/^"([^"]+)"\s+(\d+)\s+(.+)$/', $text, $matches)) {
            $params = [$matches[1], $matches[2], $matches[3]];
        } else {
            // Fallback to simple explode
            $params = explode(' ', $text, 3);
        }
        
        bot_log("EMERGENCY FIX: Parsed params: " . json_encode($params));
        
        if (count($params) >= 3) {
            add_movie_command($chat_id, $user_id, $params);
        } else {
            sendMessage($chat_id, "❌ Format: /addmovie \"Movie Name\" message_id channel_info\n\nExample: /addmovie \"Squid Game 2021 S01\" 251 @EntertainmentTadka786");
        }
        
        exit; // Stop further processing
    }
}

// ==============================
// MAIN UPDATE PROCESSING
// ==============================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    bot_log("Update received: " . json_encode($update));
    
    // Maintenance mode check
    global $MAINTENANCE_MODE, $MAINTENANCE_MESSAGE;
    if ($MAINTENANCE_MODE && isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        sendMessage($chat_id, $MAINTENANCE_MESSAGE, null, 'HTML');
        bot_log("Maintenance mode active - message blocked from $chat_id");
        exit;
    }

    get_cached_movies();

    // Channel post handling - ALL 6 CHANNELS
    if (isset($update['channel_post'])) {
        $message = $update['channel_post'];
        $message_id = $message['message_id'];
        $chat_id = $message['chat']['id'];

        // Check which channel it's from
        $channel_id = strval($chat_id);
        
        // Map channel ID to type
        $channel_type = 'unknown';
        $channel_username = '';
        
        if ($chat_id == CHANNEL_1_ID) {
            $channel_type = 'main';
            $channel_username = CHANNEL_1_USERNAME;
        } elseif ($chat_id == CHANNEL_2_ID) {
            $channel_type = 'backup';
            $channel_username = CHANNEL_2_USERNAME;
        } elseif ($chat_id == CHANNEL_3_ID) {
            $channel_type = 'theater';
            $channel_username = CHANNEL_3_USERNAME;
        } elseif ($chat_id == CHANNEL_4_ID) {
            $channel_type = 'backup2';
            $channel_username = CHANNEL_4_USERNAME;
        } elseif ($chat_id == CHANNEL_5_ID) {
            $channel_type = 'private';
            $channel_username = CHANNEL_5_USERNAME;
        } elseif ($chat_id == CHANNEL_6_ID) {
            $channel_type = 'any';
            $channel_username = CHANNEL_6_USERNAME;
        } elseif ($chat_id == GROUP_ID) {
            $channel_type = 'group';
            $channel_username = GROUP_USERNAME;
        } else {
            // Unknown channel, ignore
            exit;
        }

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
            // Use channel username for CSV entry
            append_movie($text, $message_id, $channel_username, $channel_id);
            bot_log("Auto-added from $channel_type channel: $text");
        }
    }

    // Message handling
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        $chat_type = $message['chat']['type'] ?? 'private';

        bot_log("Message from $user_id ($chat_type): " . substr($text, 0, 100));

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
                if (!is_valid_movie_query($text)) {
                    bot_log("Invalid group message blocked from $chat_id: $text");
                    return;
                }
            }
        }

        // Command handling
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $params = array_slice($parts, 1);
            
            // Special handling for /addmovie with quoted names
            if ($command === '/addmovie' && count($params) >= 3) {
                // Check if first param has quotes
                $first_param = $params[0];
                if (strpos($first_param, '"') === 0 && strrpos($first_param, '"') !== strlen($first_param) - 1) {
                    // Re-parse with custom logic
                    $text_without_command = substr($text, strlen('/addmovie'));
                    if (preg_match('/^ "([^"]+)" (\d+) (.+)$/', $text_without_command, $matches)) {
                        $params = [$matches[1], $matches[2], $matches[3]];
                    }
                }
            }
            
            handle_command($chat_id, $user_id, $command, $params);
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
        
        bot_log("Callback query: $data from $user_id");
        
        // Movie selection
        $movie_lower = strtolower($data);
        if (isset($movie_messages[$movie_lower])) {
            $entries = $movie_messages[$movie_lower];
            $cnt = 0;
            
            foreach ($entries as $entry) {
                // FORWARD with header (default)
                deliver_item_to_chat($chat_id, $entry, true);
                usleep(200000);
                $cnt++;
            }
            
            sendMessage($chat_id, "✅ '$data' ke $cnt items ka info mil gaya!\n\n📢 Join our channels to download!");
            answerCallbackQuery($query['id'], "🎬 $cnt items ka info sent!");
            update_user_activity($user_id, 'download');
        }
        // Pagination controls
        elseif (strpos($data, 'tu_prev_') === 0) {
            $page = (int)str_replace('tu_prev_', '', $data);
            totalupload_controller($chat_id, $page);
            answerCallbackQuery($query['id'], "Page $page");
        }
        elseif (strpos($data, 'tu_next_') === 0) {
            $page = (int)str_replace('tu_next_', '', $data);
            totalupload_controller($chat_id, $page);
            answerCallbackQuery($query['id'], "Page $page");
        }
        elseif (strpos($data, 'tu_view_') === 0) {
            $page = (int)str_replace('tu_view_', '', $data);
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page);
            batch_download_with_progress($chat_id, $pg['slice'], $page);
            answerCallbackQuery($query['id'], "Re-sent current page movies info");
        }
        elseif (strpos($data, 'tu_info_') === 0) {
            $page = (int)str_replace('tu_info_', '', $data);
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page);
            
            $info = "📊 <b>Page Information</b>\n\n";
            $info .= "📄 Page: $page/{$pg['total_pages']}\n";
            $info .= "🎬 Movies: " . count($pg['slice']) . "\n";
            $info .= "📁 Total: {$pg['total']} movies\n\n";
            
            foreach ($pg['slice'] as $index => $movie) {
                $channel_icon = get_channel_icon($movie['channel_type'] ?? 'main');
                $info .= ($index + 1) . ". $channel_icon {$movie['movie_name']} [{$movie['channel_username']}]\n";
            }
            
            sendMessage($chat_id, $info, null, 'HTML');
            answerCallbackQuery($query['id'], "Page $page info");
        }
        elseif ($data === 'tu_stop') {
            sendMessage($chat_id, "✅ Pagination stopped. Type /totalupload to start again.");
            answerCallbackQuery($query['id'], "Stopped");
        }
        elseif ($data === 'current_page') {
            answerCallbackQuery($query['id'], "You're on this page");
        }
        // Enhanced Pagination Controls
        elseif (strpos($data, 'pag_') === 0) {
            $parts = explode('_', $data);
            $action = $parts[1];
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            if ($action == 'first') {
                totalupload_controller($chat_id, 1, [], $session_id);
                answerCallbackQuery($query['id'], "First page");
            } 
            elseif ($action == 'last') {
                $all = get_all_movies_list();
                $total_pages = ceil(count($all) / ITEMS_PER_PAGE);
                totalupload_controller($chat_id, $total_pages, [], $session_id);
                answerCallbackQuery($query['id'], "Last page");
            }
            elseif ($action == 'prev') {
                $current_page = isset($parts[2]) ? intval($parts[2]) : 1;
                $session_id = isset($parts[3]) ? $parts[3] : '';
                totalupload_controller($chat_id, max(1, $current_page - 1), [], $session_id);
                answerCallbackQuery($query['id'], "Previous page");
            }
            elseif ($action == 'next') {
                $current_page = isset($parts[2]) ? intval($parts[2]) : 1;
                $session_id = isset($parts[3]) ? $parts[3] : '';
                $all = get_all_movies_list();
                $total_pages = ceil(count($all) / ITEMS_PER_PAGE);
                totalupload_controller($chat_id, min($total_pages, $current_page + 1), [], $session_id);
                answerCallbackQuery($query['id'], "Next page");
            }
            elseif (is_numeric($action)) {
                $page_num = intval($action);
                $session_id = isset($parts[2]) ? $parts[2] : '';
                totalupload_controller($chat_id, $page_num, [], $session_id);
                answerCallbackQuery($query['id'], "Page $page_num");
            }
        }
        // Send page batch info
        elseif (strpos($data, 'send_') === 0) {
            $parts = explode('_', $data);
            $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page_num, []);
            batch_download_with_progress($chat_id, $pg['slice'], $page_num);
            answerCallbackQuery($query['id'], "📦 Batch info started!");
        }
        // Preview page
        elseif (strpos($data, 'prev_') === 0) {
            $parts = explode('_', $data);
            $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page_num, []);
            
            $preview_msg = "👁️ <b>Page {$page_num} Preview</b>\n\n";
            $limit = min(5, count($pg['slice']));
            
            for ($i = 0; $i < $limit; $i++) {
                $movie = $pg['slice'][$i];
                $channel_icon = get_channel_icon($movie['channel_type'] ?? 'main');
                $preview_msg .= ($i + 1) . ". $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
                $preview_msg .= "   📢 " . ($movie['channel_username'] ?? 'Unknown') . "\n\n";
            }
            
            sendMessage($chat_id, $preview_msg, null, 'HTML');
            answerCallbackQuery($query['id'], "Preview sent");
        }
        // Filter controls
        elseif (strpos($data, 'flt_') === 0) {
            $parts = explode('_', $data);
            $filter_type = $parts[1];
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            $filters = [];
            if ($filter_type == 'hd') {
                answerCallbackQuery($query['id'], "HD filter applied");
            } elseif ($filter_type == 'theater') {
                $filters = ['channel' => 'theater'];
                answerCallbackQuery($query['id'], "Theater filter applied");
            } elseif ($filter_type == 'main') {
                $filters = ['channel' => 'main'];
                answerCallbackQuery($query['id'], "Main channel filter applied");
            } elseif ($filter_type == 'backup') {
                $filters = ['channel' => 'backup'];
                answerCallbackQuery($query['id'], "Backup filter applied");
            } elseif ($filter_type == 'clr') {
                answerCallbackQuery($query['id'], "Filters cleared");
            }
            
            totalupload_controller($chat_id, 1, $filters, $session_id);
        }
        
        // ==================== YOUR CALLBACK HANDLING CODE ====================
        // Movie selection from smart suggestion
        elseif(strpos($data, 'movie_') === 0){
            $movie_name = str_replace('movie_', '', $data);
            global $movie_messages;
            
            if (isset($movie_messages[strtolower($movie_name)])) {
                $entries = $movie_messages[strtolower($movie_name)];
                
                foreach ($entries as $entry) {
                    // FORWARD with header
                    deliver_item_to_chat($chat_id, $entry, true);
                    usleep(200000);
                }
                
                sendMessage($chat_id, "✅ Movie sent!");
                answerCallbackQuery($query['id'], "Movie sent!");
                update_user_activity($user_id, 'download');
            } else {
                answerCallbackQuery($query['id'], "Movie not found", true);
            }
        }
        // Pagination callbacks for /totaluploads and /latest
        elseif (preg_match('/(next|prev)_(\d+)/', $data, $matches)) {
            $dir = $matches[1];
            $current = intval($matches[2]);
            $new_page = ($dir == 'next') ? $current + 1 : $current - 1;
            
            // Check if it's for latest or totaluploads
            if(strpos($message['text'], "Latest Movies:") !== false) {
                show_latest_movies($chat_id, 10); // This will use new page
            } else {
                // Call totaluploads with new page
                $command = '/totalupload';
                $params = [$new_page];
                handle_command($chat_id, $user_id, $command, $params);
            }
            
            answerCallbackQuery($query['id'], "Page $new_page");
        }
        // ==================== END YOUR CALLBACK CODE ====================
        
        // Theater channel search
        elseif ($data == 'search_theater') {
            sendMessage($chat_id, "🎭 <b>Theater Prints Search</b>\n\nType any movie name to search for theater prints!\n\nExamples:\n<code>kgf 2 theater</code>\n<code>avengers endgame print</code>\n<code>hindi movie theater</code>", null, 'HTML');
            answerCallbackQuery($query['id'], "Search theater movies");
        }
        // Close pagination
        elseif ($data == 'close_' || strpos($data, 'close_') === 0) {
            deleteMessage($chat_id, $message['message_id']);
            sendMessage($chat_id, "🗂️ Pagination closed. Use /totalupload to browse again.");
            answerCallbackQuery($query['id'], "Pagination closed");
        }
        // Movie requests
        elseif (strpos($data, 'auto_request_') === 0) {
            $movie_name = base64_decode(str_replace('auto_request_', '', $data));
            $lang = detect_language($movie_name);
            
            if (add_movie_request($user_id, $movie_name, $lang)) {
                send_multilingual_response($chat_id, 'request_success', $lang);
                answerCallbackQuery($query['id'], "Request sent successfully!");
                update_user_activity($user_id, 'movie_request');
            } else {
                send_multilingual_response($chat_id, 'request_limit', $lang);
                answerCallbackQuery($query['id'], "Daily limit reached!", true);
            }
        }
        elseif ($data === 'request_movie') {
            sendMessage($chat_id, "📝 To request a movie, use:\n<code>/request movie_name</code>\n\nExample: <code>/request Avengers Endgame</code>", null, 'HTML');
            answerCallbackQuery($query['id'], "Request instructions sent");
        }
        elseif ($data === 'request_help') {
            show_request_channel_info($chat_id);
            answerCallbackQuery($query['id'], "Request channel info");
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
        // Backup commands
        elseif ($data === 'backup_status') {
            if ($chat_id == ADMIN_ID) {
                backup_status($chat_id);
                answerCallbackQuery($query['id'], "Backup status");
            } else {
                answerCallbackQuery($query['id'], "Admin only command!", true);
            }
        }
        elseif ($data === 'run_backup') {
            if ($chat_id == ADMIN_ID) {
                manual_backup($chat_id);
                answerCallbackQuery($query['id'], "Backup started");
            } else {
                answerCallbackQuery($query['id'], "Admin only command!", true);
            }
        }
        // Help command
        elseif ($data === 'help_command') {
            $command = '/help';
            $params = [];
            handle_command($chat_id, $user_id, $command, $params);
            answerCallbackQuery($query['id'], "Help menu");
        }
        // Channel download
        elseif (strpos($data, 'download_channel_') === 0) {
            $channel_type = str_replace('download_channel_', '', $data);
            $all = get_all_movies_list();
            $filtered = [];
            foreach ($all as $movie) {
                if (($movie['channel_type'] ?? '') == $channel_type) {
                    $filtered[] = $movie;
                }
            }
            batch_download_with_progress($chat_id, $filtered, $channel_type . " channel");
            answerCallbackQuery($query['id'], "$channel_type movies info sent");
        }
        // Other callbacks
        elseif ($data === 'refresh_stats') {
            show_user_stats($chat_id, $user_id);
            answerCallbackQuery($query['id'], "Refreshed");
        }
        elseif ($data === 'refresh_leaderboard') {
            show_leaderboard($chat_id);
            answerCallbackQuery($query['id'], "Refreshed");
        }
        elseif ($data === 'download_latest') {
            $all = get_all_movies_list();
            $latest = array_slice($all, -10);
            $latest = array_reverse($latest);
            batch_download_with_progress($chat_id, $latest, "latest");
            answerCallbackQuery($query['id'], "Latest movies info sent");
        }
        elseif ($data === 'browse_all') {
            totalupload_controller($chat_id, 1);
            answerCallbackQuery($query['id'], "Browse all movies");
        }
        else {
            sendMessage($chat_id, "❌ Movie not found: " . $data . "\n\nTry searching with exact name!");
            answerCallbackQuery($query['id'], "❌ Movie not available");
        }
    }

    // Scheduled tasks
    $current_hour = date('H');
    $current_minute = date('i');

    // Daily auto-backup at 3 AM
    if ($current_hour == AUTO_BACKUP_HOUR && $current_minute == '00') {
        auto_backup();
        bot_log("Daily auto-backup completed");
    }

    // Hourly cache cleanup
    if ($current_minute == '30') { // Every hour at 30 minutes
        global $movie_cache;
        $movie_cache = [];
        bot_log("Hourly cache cleanup");
    }
}

// ==============================
// DEBUGGING AND TESTING ENDPOINTS
// ==============================
if (isset($_GET['test'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<h2>🎬 Entertainment Tadka Bot - Debug Mode</h2>";
    
    echo "<h3>✅ System Status:</h3>";
    echo "<p>BOT_TOKEN: " . (defined('BOT_TOKEN') && BOT_TOKEN ? '✅ Set' : '❌ Missing') . "</p>";
    echo "<p>ADMIN_ID: " . (defined('ADMIN_ID') && ADMIN_ID ? '✅ ' . ADMIN_ID : '❌ Missing') . "</p>";
    echo "<p>CSV File: " . (file_exists(CSV_FILE) ? '✅ Exists' : '❌ Missing') . "</p>";
    echo "<p>Log File: " . (file_exists(LOG_FILE) ? '✅ Exists' : '❌ Missing') . "</p>";
    
    echo "<h3>⏱️ Delay Typing Test:</h3>";
    echo "<p>TYPING_DELAY_ENABLED: " . (TYPING_DELAY_ENABLED ? '✅ ON' : '❌ OFF') . "</p>";
    echo "<p>Min Delay: " . TYPING_DELAY_MIN . " seconds</p>";
    echo "<p>Max Delay: " . TYPING_DELAY_MAX . " seconds</p>";
    
    echo "<h3>📊 CSV Data Preview:</h3>";
    if (file_exists(CSV_FILE)) {
        $csv = array_map('str_getcsv', file(CSV_FILE));
        echo "<p>Total entries: " . count($csv) . "</p>";
        if (count($csv) > 1) {
            echo "<p>First 5 movies:</p>";
            echo "<ol>";
            for ($i = 1; $i <= min(5, count($csv)-1); $i++) {
                echo "<li>" . htmlspecialchars($csv[$i][0]) . " | ID: " . htmlspecialchars($csv[$i][1]) . " | Channel: " . htmlspecialchars($csv[$i][2] ?? 'N/A') . "</li>";
            }
            echo "</ol>";
        }
    }
    
    echo "<h3>🔧 Available Commands:</h3>";
    $commands = [
        '/start', '/help', '/search', '/totalupload', '/latest', '/trending',
        '/addmovie', '/request', '/mystats', '/leaderboard', '/channels',
        '/stats', '/backup', '/maintenance', '/ping'
    ];
    echo "<p>" . implode(', ', $commands) . "</p>";
    
    echo "<h3>📝 /addmovie Test Format:</h3>";
    echo "<code>/addmovie \"Movie Name\" 123 @EntertainmentTadka786</code><br>";
    echo "<code>/addmovie \"Movie Name\" 123 -1003181705395</code><br>";
    echo "<code>/addmovie \"Movie Name\" 123 theater</code>";
    
    exit;
}

if (isset($_GET['test_addmovie'])) {
    // Test the /addmovie function directly
    define('BOT_TOKEN', getenv('BOT_TOKEN'));
    define('ADMIN_ID', getenv('ADMIN_ID'));
    
    $test_cases = [
        ['"Squid Game 2021 S01"', '251', '@EntertainmentTadka786'],
        ['"Test Movie"', '999', '-1003181705395'],
        ['"Another Test"', '1000', 'theater']
    ];
    
    echo "<h2>Testing /addmovie Command</h2>";
    
    foreach ($test_cases as $test) {
        echo "<h3>Test: " . htmlspecialchars(implode(' ', $test)) . "</h3>";
        add_movie_command(ADMIN_ID, ADMIN_ID, $test);
        echo "<hr>";
    }
    
    exit;
}

// ==============================
// WEBHOOK SETUP AND STATUS PAGE
// ==============================
if (php_sapi_name() === 'cli' || isset($_GET['setwebhook'])) {
    // Webhook setup karo
    $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $result = apiRequest('setWebhook', ['url' => $webhook_url]);
    
    echo "<h1>🎬 Entertainment Tadka Bot v3.0.0</h1>";
    echo "<h2>Webhook Setup</h2>";
    echo "<p>Result: " . htmlspecialchars($result) . "</p>";
    echo "<p>Webhook URL: " . htmlspecialchars($webhook_url) . "</p>";
    
    $bot_info = json_decode(apiRequest('getMe'), true);
    if ($bot_info && isset($bot_info['ok']) && $bot_info['ok']) {
        echo "<h2>🤖 Bot Info</h2>";
        echo "<p>Name: " . htmlspecialchars($bot_info['result']['first_name']) . "</p>";
        echo "<p>Username: @" . htmlspecialchars($bot_info['result']['username']) . "</p>";
        echo "<p>Version: 3.0.0 (6-channel support)</p>";
    }
    
    echo "<h3>📢 Channels Configured:</h3>";
    echo "<p>1. Main: " . CHANNEL_1_USERNAME . " (" . CHANNEL_1_ID . ")</p>";
    echo "<p>2. Backup: " . CHANNEL_2_USERNAME . " (" . CHANNEL_2_ID . ")</p>";
    echo "<p>3. Theater: " . CHANNEL_3_USERNAME . " (" . CHANNEL_3_ID . ")</p>";
    echo "<p>4. Backup2: " . CHANNEL_4_USERNAME . " (" . CHANNEL_4_ID . ")</p>";
    echo "<p>5. Private: " . CHANNEL_5_USERNAME . " (" . CHANNEL_5_ID . ")</p>";
    echo "<p>6. Any: " . CHANNEL_6_USERNAME . " (" . CHANNEL_6_ID . ")</p>";
    echo "<p>7. Group: " . GROUP_USERNAME . " (" . GROUP_ID . ")</p>";
    
    echo "<h3>✅ System Status</h3>";
    echo "<p>CSV File: " . (file_exists(CSV_FILE) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>CSV Format: movie_name,message_id,channel_info</p>";
    echo "<p>Users File: " . (file_exists(USERS_FILE) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Stats File: " . (file_exists(STATS_FILE) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Backup Directory: " . (file_exists(BACKUP_DIR) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Delay Typing: " . (TYPING_DELAY_ENABLED ? "✅ Enabled" : "❌ Disabled") . "</p>";
    
    echo "<h3>🚀 Quick Setup</h3>";
    echo "<p><a href='?setwebhook=1'>Set Webhook Now</a></p>";
    echo "<p><a href='?test=1'>Test System</a></p>";
    echo "<p><a href='?test_addmovie=1'>Test /addmovie</a></p>";
    
    exit;
}

// ==============================
// DEFAULT STATUS PAGE
// ==============================
if (!isset($update) || !$update) {
    // Bot status page show karo
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>🎬 Entertainment Tadka Bot v3.0.0</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
            h2 { color: #555; margin-top: 20px; }
            .status { padding: 10px; border-radius: 5px; margin: 5px 0; }
            .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
            .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
            ul { padding-left: 20px; }
            li { margin: 5px 0; }
            a { color: #4CAF50; text-decoration: none; }
            a:hover { text-decoration: underline; }
            .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
            .card { background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #dee2e6; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🎬 Entertainment Tadka Bot v3.0.0</h1>
            <p><strong>Telegram Bot:</strong> Multi-channel movie forwarding system with 6-channel support</p>
            
            <div class='grid'>
                <div class='card'>
                    <h2>📊 System Status</h2>
                    <div class='status success'>✅ Running</div>
                    <p><strong>Total Movies:</strong> " . ($stats['total_movies'] ?? 0) . "</p>
                    <p><strong>Total Users:</strong> " . count($users_data['users'] ?? []) . "</p>
                    <p><strong>Total Searches:</strong> " . ($stats['total_searches'] ?? 0) . "</p>
                    <p><strong>Last Updated:</strong> " . ($stats['last_updated'] ?? 'N/A') . "</p>
                </div>
                
                <div class='card'>
                    <h2>⏱️ Delay Typing</h2>
                    <div class='status " . (TYPING_DELAY_ENABLED ? 'success' : 'warning') . "'>" . (TYPING_DELAY_ENABLED ? '✅ Enabled' : '⚠️ Disabled') . "</div>
                    <p><strong>Min Delay:</strong> " . TYPING_DELAY_MIN . " seconds</p>
                    <p><strong>Max Delay:</strong> " . TYPING_DELAY_MAX . " seconds</p>
                    <p><strong>Testing Mode:</strong> Active (2-4 seconds)</p>
                </div>
            </div>
            
            <h2>📢 Configured Channels (7)</h2>
            <ul>
                <li><strong>Main:</strong> " . CHANNEL_1_USERNAME . " (" . CHANNEL_1_ID . ")</li>
                <li><strong>Backup:</strong> " . CHANNEL_2_USERNAME . " (" . CHANNEL_2_ID . ")</li>
                <li><strong>Theater:</strong> " . CHANNEL_3_USERNAME . " (" . CHANNEL_3_ID . ")</li>
                <li><strong>Backup2:</strong> " . CHANNEL_4_USERNAME . " (" . CHANNEL_4_ID . ")</li>
                <li><strong>Private:</strong> " . CHANNEL_5_USERNAME . " (" . CHANNEL_5_ID . ")</li>
                <li><strong>Any:</strong> " . CHANNEL_6_USERNAME . " (" . CHANNEL_6_ID . ")</li>
                <li><strong>Group:</strong> " . GROUP_USERNAME . " (" . GROUP_ID . ")</li>
            </ul>
            
            <h2>🔧 Technical Features</h2>
            <ul>
                <li>✅ 6-channel forwarding with forward headers</li>
                <li>✅ New CSV format: movie_name,message_id,channel_info</li>
                <li>✅ <strong>/addmovie command working</strong></li>
                <li>✅ Delay typing feature (2-4 seconds)</li>
                <li>✅ Auto-backup system</li>
                <li>✅ User points & leaderboard</li>
                <li>✅ Paginated browsing</li>
                <li>✅ Smart search algorithm</li>
                <li>✅ Auto-notification for requested movies</li>
                <li>✅ Group message filtering</li>
                <li>✅ Maintenance mode</li>
            </ul>
            
            <h2>🎯 Main Commands</h2>
            <ul>
                <li><code>/start</code> - Welcome message</li>
                <li><code>/help</code> - All commands</li>
                <li><code>/addmovie "Movie Name" 123 @channel</code> - Add movie (admin)</li>
                <li><code>/search movie</code> - Search movies</li>
                <li><code>/totalupload</code> - Browse all movies</li>
                <li><code>/channel theater</code> - Theater prints only</li>
                <li><code>/request movie</code> - Request movie</li>
                <li><code>/mystats</code> - User statistics</li>
                <li><code>/leaderboard</code> - Top users</li>
                <li><code>/channels</code> - Join channels</li>
            </ul>
            
            <h2>🚀 Quick Setup</h2>
            <p><a href='?setwebhook=1'>Set Webhook Now</a></p>
            <p><a href='?test=1'>Test System</a></p>
            <p><a href='?test_addmovie=1'>Test /addmovie Command</a></p>
            
            <h2>📋 /addmovie Command Format</h2>
            <div class='card'>
                <p><strong>Correct Format:</strong> <code>/addmovie \"Movie Name\" message_id channel_info</code></p>
                <p><strong>Examples:</strong></p>
                <ul>
                    <li><code>/addmovie \"Squid Game 2021 S01\" 251 @EntertainmentTadka786</code></li>
                    <li><code>/addmovie \"Avengers Endgame\" 252 -1003181705395</code></li>
                    <li><code>/addmovie \"KGF 2\" 253 theater</code></li>
                </ul>
                <p><strong>Note:</strong> Use quotes for movie names with spaces!</p>
            </div>
            
            <h2>📊 Recent Activity</h2>";
    
    if (file_exists(LOG_FILE)) {
        $logs = array_slice(file(LOG_FILE), -20);
        echo "<div class='card'><pre style='max-height: 300px; overflow: auto;'>";
        foreach ($logs as $log) {
            echo htmlspecialchars($log);
        }
        echo "</pre></div>";
    }
    
    echo "
        </div>
    </body>
    </html>";
}
?>
