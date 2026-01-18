<?php
// ==============================
// SECURITY HEADERS & BASIC SETUP
// ==============================

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

// ALL CHANNELS CONFIGURATION (Updated with your channels)
define('CHANNEL_1_ID', getenv('CHANNEL_1_ID') ?: '-1003181705395');  // @EntertainmentTadka786
define('CHANNEL_2_ID', getenv('CHANNEL_2_ID') ?: '-1002964109368');  // @ETBackup
define('CHANNEL_3_ID', getenv('CHANNEL_3_ID') ?: '-1002831605258');  // @threater_print_movies
define('CHANNEL_4_ID', getenv('CHANNEL_4_ID') ?: '-1002337293281');  // Backup Channel 2
define('CHANNEL_5_ID', getenv('CHANNEL_5_ID') ?: '-1003251791991');  // Private Channel
define('GROUP_ID', getenv('GROUP_ID') ?: '-1003083386043');  // @EntertainmentTadka7860

// Usernames for display
define('CHANNEL_1_USERNAME', '@EntertainmentTadka786');
define('CHANNEL_2_USERNAME', '@ETBackup');
define('CHANNEL_3_USERNAME', '@threater_print_movies');
define('CHANNEL_4_USERNAME', '@BackupChannel2');
define('CHANNEL_5_USERNAME', '@PrivateChannel');
define('GROUP_USERNAME', '@EntertainmentTadka7860');

// Admin ID
define('ADMIN_ID', (int)getenv('ADMIN_ID'));  // Admin user ID

// Validate essential environment variables
if (!CHANNEL_1_ID || !CHANNEL_2_ID || !CHANNEL_3_ID) {
    die("❌ Essential channel IDs environment variables set nahi hain. Render.com dashboard mein set karo.");
}

// File paths - Yeh sab files bot ke saath create hongi
define('CSV_FILE', 'movies.csv');  // Movies database (NEW FORMAT: movie-name,message_id,channel_username)
define('USERS_FILE', 'users.json');  // Users data
define('STATS_FILE', 'bot_stats.json');  // Bot statistics
define('REQUEST_FILE', 'movie_requests.json');  // Movie requests
define('BACKUP_DIR', 'backups/');  // Backup folder
define('LOG_FILE', 'bot_activity.log');  // Activity log

// Constants - Bot ke settings
define('CACHE_EXPIRY', 300);  // 5 minutes cache
define('ITEMS_PER_PAGE', 5);  // Pagination ke liye items per page (MAX 5 as requested)
define('MAX_SEARCH_RESULTS', 15);  // Maximum search results
define('DAILY_REQUEST_LIMIT', 5);  // Daily movie request limit per user
define('AUTO_BACKUP_HOUR', '03');  // Auto backup time (3 AM)
define('TYPING_DELAY', 1);  // Typing delay in seconds

// ==============================
// ENHANCED PAGINATION CONSTANTS (SIMPLIFIED as requested)
// ==============================
define('MAX_PAGES_TO_SHOW', 5);          // Max page buttons to display
define('PAGINATION_CACHE_TIMEOUT', 60);  // Cache timeout in seconds
define('PREVIEW_ITEMS', 3);              // Number of items to preview

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
// CHANNEL MAPPING FUNCTIONS
// ==============================
function get_channel_id_by_username($username) {
    // Channel username se channel ID return karta hai
    switch(strtolower(trim($username))) {
        case '@entertainmenttadka786':
        case 'entertainmenttadka786':
            return CHANNEL_1_ID;
            
        case '@etbackup':
        case 'etbackup':
            return CHANNEL_2_ID;
            
        case '@threater_print_movies':
        case 'threater_print_movies':
            return CHANNEL_3_ID;
            
        case '@backupchannel2':
        case 'backupchannel2':
            return CHANNEL_4_ID;
            
        case '@privatechannel':
        case 'privatechannel':
            return CHANNEL_5_ID;
            
        case '@entertainmenttadka7860':
        case 'entertainmenttadka7860':
            return GROUP_ID;
            
        default:
            // Agar username channel ID hai (e.g., -100...)
            if (strpos($username, '-100') === 0) {
                return $username;
            }
            return CHANNEL_1_ID; // Default
    }
}

function get_channel_username_by_id($channel_id) {
    // Channel ID se username return karta hai
    switch($channel_id) {
        case CHANNEL_1_ID:
            return CHANNEL_1_USERNAME;
            
        case CHANNEL_2_ID:
            return CHANNEL_2_USERNAME;
            
        case CHANNEL_3_ID:
            return CHANNEL_3_USERNAME;
            
        case CHANNEL_4_ID:
            return CHANNEL_4_USERNAME;
            
        case CHANNEL_5_ID:
            return CHANNEL_5_USERNAME;
            
        case GROUP_ID:
            return GROUP_USERNAME;
            
        default:
            return $channel_id;
    }
}

// ==============================
// DELAY TYPING FUNCTION (NEW as requested)
// ==============================
function send_typing_action($chat_id) {
    // Typing action show karta hai (DELAY as requested)
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendChatAction";
    $data = [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ];
    
    $options = array(
        'http' => array(
            'method' => 'POST',
            'content' => http_build_query($data),
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
        )
    );
    
    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
    
    // Delay for TYPING_DELAY seconds
    sleep(TYPING_DELAY);
}

// ==============================
// HELPER FUNCTION FOR DIRECT LINKS
// ==============================
function get_direct_channel_link($message_id, $channel_id = CHANNEL_1_ID) {
    // Telegram direct link generate karta hai
    $channel_id_clean = str_replace('-100', '', $channel_id);
    return "https://t.me/c/" . $channel_id_clean . "/" . $message_id;
}

function get_channel_username_link($channel_username) {
    // Channel username se link generate karta hai
    $username = ltrim($channel_username, '@');
    return "https://t.me/" . $username;
}

// ==============================
// FILE INITIALIZATION FUNCTION
// ==============================
function initialize_files() {
    // Sab required files create karta hai agar nahi hain toh
    $files = [
        CSV_FILE => "movie_name,message_id,channel_username\n",  // CSV header updated (NEW FORMAT)
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
// CSV MANAGEMENT FUNCTIONS (UPDATED FOR NEW FORMAT)
// ==============================
function load_and_clean_csv($filename = CSV_FILE) {
    global $movie_messages;
    
    // CSV file check karo, agar nahi hai toh create karo
    if (!file_exists($filename)) {
        file_put_contents($filename, "movie_name,message_id,channel_username\n");
        return [];
    }

    $data = [];
    $handle = fopen($filename, "r");
    if ($handle !== FALSE) {
        $header = fgetcsv($handle);  // Header read karo
        
        // Har row ko process karo
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 2 && (!empty(trim($row[0])))) {
                $movie_name = trim($row[0]);
                $message_id_raw = isset($row[1]) ? trim($row[1]) : '';
                $channel_username = isset($row[2]) ? trim($row[2]) : CHANNEL_1_USERNAME;
                
                // Channel ID get karo
                $channel_id = get_channel_id_by_username($channel_username);

                // Movie entry create karo
                $entry = [
                    'movie_name' => $movie_name,
                    'message_id_raw' => $message_id_raw,
                    'channel_username' => $channel_username,
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

    // CSV clean karo aur rewrite karo
    $handle = fopen($filename, "w");
    fputcsv($handle, array('movie_name','message_id','channel_username'));
    foreach ($data as $row) {
        fputcsv($handle, [
            $row['movie_name'], 
            $row['message_id_raw'], 
            $row['channel_username']
        ]);
    }
    fclose($handle);

    bot_log("CSV cleaned and reloaded - " . count($data) . " entries");
    return $data;
}

// ==============================
// ADD MOVIE FUNCTION (NEW as requested)
// ==============================
function add_movie_to_csv($movie_name, $message_id, $channel_username = CHANNEL_1_USERNAME) {
    // CSV mein movie add karta hai (NEW FORMAT)
    if (empty(trim($movie_name)) || empty(trim($message_id))) {
        return false;
    }
    
    // CSV file open karo
    $handle = fopen(CSV_FILE, "a");
    if ($handle === FALSE) {
        return false;
    }
    
    // New entry add karo
    fputcsv($handle, [
        trim($movie_name),
        trim($message_id),
        trim($channel_username)
    ]);
    
    fclose($handle);
    
    // Cache refresh karo
    global $movie_cache, $movie_messages;
    $movie_cache = [];
    
    $movie_lower = strtolower(trim($movie_name));
    $entry = [
        'movie_name' => trim($movie_name),
        'message_id_raw' => trim($message_id),
        'message_id' => is_numeric($message_id) ? intval($message_id) : null,
        'channel_username' => trim($channel_username),
        'channel_id' => get_channel_id_by_username($channel_username)
    ];
    
    if (!isset($movie_messages[$movie_lower])) $movie_messages[$movie_lower] = [];
    $movie_messages[$movie_lower][] = $entry;
    
    // Statistics update
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats['total_movies'] = ($stats['total_movies'] ?? 0) + 1;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
    
    bot_log("Movie added via /addmovie: $movie_name (ID: $message_id, Channel: $channel_username)");
    return true;
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
    // Message forward karta hai (ALL CHANNELS SE FORWARDING)
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
// MOVIE DELIVERY SYSTEM - ALL CHANNELS SE FORWARDING
// ==============================
function deliver_item_to_chat($chat_id, $item, $use_copy = true) {
    // Movie user ko deliver karta hai - ALL CHANNELS se forwarding
    
    // Channel ID aur username determine karo
    $channel_id = $item['channel_id'] ?? CHANNEL_1_ID;
    $channel_username = $item['channel_username'] ?? CHANNEL_1_USERNAME;
    
    // Agar valid message ID hai toh FORWARD/COPY KARO
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        if ($use_copy) {
            // COPY MESSAGE use karo - yeh forward header nahi dikhayega
            $result = json_decode(copyMessage($chat_id, $channel_id, $item['message_id']), true);
            
            if ($result && $result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("Movie COPIED from $channel_username: {$item['movie_name']} to $chat_id");
                return true;
            }
        }
        
        // Agar copy nahi ho paya ya $use_copy false hai, toh forward try karo
        $forward_result = json_decode(forwardMessage($chat_id, $channel_id, $item['message_id']), true);
        
        if ($forward_result && $forward_result['ok']) {
            update_stats('total_downloads', 1);
            bot_log("Movie FORWARDED from $channel_username: {$item['movie_name']} to $chat_id");
            return true;
        }
    }
    
    // Agar message ID nahi hai ya numeric nahi hai
    if (!empty($item['message_id_raw'])) {
        // Raw message ID se try karo
        $message_id_clean = preg_replace('/[^0-9]/', '', $item['message_id_raw']);
        if (is_numeric($message_id_clean) && $message_id_clean > 0) {
            if ($use_copy) {
                // Pehle copy try karo
                $result = json_decode(copyMessage($chat_id, $channel_id, $message_id_clean), true);
                
                if ($result && $result['ok']) {
                    update_stats('total_downloads', 1);
                    bot_log("Movie COPIED (raw ID) from $channel_username: {$item['movie_name']} to $chat_id");
                    return true;
                }
            }
            
            // Fallback to forward
            $forward_result = json_decode(forwardMessage($chat_id, $channel_id, $message_id_clean), true);
            
            if ($forward_result && $forward_result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("Movie FORWARDED (raw ID) from $channel_username: {$item['movie_name']} to $chat_id");
                return true;
            }
        }
    }

    // Agar koi bhi method kaam na kare toh text info bhejo
    $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n";
    $text .= "📢 Channel: " . htmlspecialchars($channel_username) . "\n";
    $text .= "📎 Reference: " . htmlspecialchars($item['message_id_raw'] ?? 'N/A') . "\n\n";
    
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
    
    // Theater search detection
    $is_theater_search = false;
    $theater_keywords = ['theater', 'theatre', 'print', 'hdcam', 'camrip', 'hq', 'hdrip'];
    foreach ($theater_keywords as $keyword) {
        if (strpos($query_lower, $keyword) !== false) {
            $is_theater_search = true;
            $query_lower = str_replace($keyword, '', $query_lower);
            break;
        }
    }
    $query_lower = trim($query_lower);
    
    // Har movie ke against query match karo
    foreach ($movie_messages as $movie => $entries) {
        $score = 0;
        
        // Channel type matching
        foreach ($entries as $entry) {
            $channel_user = strtolower($entry['channel_username'] ?? '');
            if ($is_theater_search && strpos($channel_user, 'threater') !== false) {
                $score += 20;  // Theater search ko theater movies ka bonus
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
        
        // Bonus for channel
        foreach ($entries as $entry) {
            if (strpos(strtolower($entry['channel_username'] ?? ''), 'threater') !== false) $score += 5;
        }
        
        if ($score > 0) {
            $results[$movie] = [
                'score' => $score,
                'count' => count($entries),
                'latest_entry' => end($entries),
                'channels' => array_unique(array_column($entries, 'channel_username')),
                'has_theater' => in_array(CHANNEL_3_USERNAME, array_column($entries, 'channel_username'))
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
            'not_found' => "😔 Yeh movie abhi available nahi hai!\n\n📝 Aap request kar sakte hain!\n\n🔔 Jab bhi yeh add hogi, main bhej dunga!",
            'searching' => "🔍 Dhoondh raha hoon... Zara wait karo",
            'multiple_found' => "🎯 Kai versions mili hain! Aap konsi chahte hain?",
            'request_success' => "✅ Request receive ho gayi! Hum jald hi add karenge.",
            'request_limit' => "❌ Aaj ke liye aap maximum " . DAILY_REQUEST_LIMIT . " requests hi kar sakte hain."
        ],
        'english' => [
            'welcome' => "🎬 Boss, which movie are you looking for?",
            'found' => "✅ Found it! Sending movie info...",
            'not_found' => "😔 This movie isn't available yet!\n\n📝 You can request it!\n\n🔔 I'll send it automatically once it's added!",
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
    
    // Search karo
    $found = smart_search($q);
    
    if (!empty($found)) {
        // Movies mil gayi
        update_stats('successful_searches', 1);
        
        $msg = "🔍 Found " . count($found) . " movies for '$query':\n\n";
        $i = 1;
        foreach ($found as $movie => $data) {
            $channel_info = "";
            if ($data['has_theater']) $channel_info .= "🎭 ";
            $channel_info .= implode('/', array_slice($data['channels'], 0, 2));
            
            $msg .= "$i. $movie ($channel_info - " . $data['count'] . ")\n";
            $i++;
            if ($i > 10) break;
        }
        
        sendMessage($chat_id, $msg);
        
        // ==================== SIMPLE SMART SUGGESTION (as requested) ====================
        if(count($found) > 0) {
            $buttons = [];
            $top_movies = array_slice(array_keys($found), 0, 3); // Only top 3 suggestions
            
            foreach($top_movies as $movie){
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
// SIMPLE PAGINATION SYSTEM (as requested)
// ==============================

function simple_paginate_movies(array $all, int $page): array {
    $total = count($all);
    if ($total === 0) {
        return [
            'total' => 0,
            'total_pages' => 1, 
            'page' => 1,
            'slice' => [],
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
        'has_next' => $page < $total_pages,
        'has_prev' => $page > 1,
        'start_item' => $start + 1,
        'end_item' => min($start + ITEMS_PER_PAGE, $total)
    ];
}

function build_simple_keyboard(int $page, int $total_pages): array {
    $kb = ['inline_keyboard' => []];
    
    // Simple navigation buttons only
    $nav_row = [];
    
    if ($page > 1) {
        $nav_row[] = ['text' => '⬅️ Prev', 'callback_data' => 'pag_prev_' . $page];
    }
    
    $nav_row[] = ['text' => "$page/$total_pages", 'callback_data' => 'current'];
    
    if ($page < $total_pages) {
        $nav_row[] = ['text' => 'Next ➡️', 'callback_data' => 'pag_next_' . $page];
    }
    
    if (!empty($nav_row)) {
        $kb['inline_keyboard'][] = $nav_row;
    }
    
    // Download button for current page
    $kb['inline_keyboard'][] = [
        ['text' => '📥 Download This Page', 'callback_data' => 'send_page_' . $page]
    ];
    
    // Close button
    $kb['inline_keyboard'][] = [
        ['text' => '❌ Close', 'callback_data' => 'close_pagination']
    ];
    
    return $kb;
}

function totalupload_controller($chat_id, $page = 1) {
    // Typing delay add karo
    send_typing_action($chat_id);
    
    $all = get_all_movies_list();
    if (empty($all)) {
        sendMessage($chat_id, "📭 No movies found!");
        return;
    }
    
    // Simple pagination
    $pg = simple_paginate_movies($all, (int)$page);
    
    // Build message
    $title = "🎬 <b>All Movies</b>\n\n";
    $title .= "📊 Total: <b>{$pg['total']}</b> movies\n";
    $title .= "📄 Page: <b>{$pg['page']}/{$pg['total_pages']}</b>\n";
    $title .= "📋 Showing: <b>{$pg['start_item']}-{$pg['end_item']}</b>\n\n";
    
    // Current page movies list (MAX 5 as requested)
    $title .= "<b>Movies:</b>\n\n";
    $i = $pg['start_item'];
    foreach ($pg['slice'] as $movie) {
        $movie_name = htmlspecialchars($movie['movie_name'] ?? 'Unknown');
        $channel = htmlspecialchars($movie['channel_username'] ?? CHANNEL_1_USERNAME);
        
        // Shorten long movie names
        if (strlen($movie_name) > 40) {
            $movie_name = substr($movie_name, 0, 40) . "...";
        }
        
        $title .= "<b>{$i}.</b> {$movie_name}\n";
        $title .= "   📢 {$channel}\n\n";
        $i++;
    }
    
    // Build simple keyboard
    $kb = build_simple_keyboard($pg['page'], $pg['total_pages']);
    
    sendMessage($chat_id, $title, $kb, 'HTML');
    bot_log("Simple pagination - Chat: $chat_id, Page: $page");
}

// ==============================
// SIMPLE BATCH DOWNLOAD
// ==============================

function simple_batch_download($chat_id, $movies, $page_num) {
    // Typing delay
    send_typing_action($chat_id);
    
    $total = count($movies);
    if ($total === 0) return;
    
    $progress_msg = sendMessage($chat_id, "📦 <b>Sending Page {$page_num}</b>\n\nTotal: {$total} movies\n\n⏳ Please wait...", null, 'HTML');
    $progress_id = $progress_msg['result']['message_id'];
    
    $success = 0;
    $failed = 0;
    
    for ($i = 0; $i < $total; $i++) {
        $movie = $movies[$i];
        
        try {
            $result = deliver_item_to_chat($chat_id, $movie, true);
            if ($result) {
                $success++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
        }
        
        usleep(300000); // 0.3 second delay
    }
    
    // Final update
    editMessage($chat_id, $progress_id,
        "✅ <b>Page {$page_num} Sent</b>\n\n" .
        "🎬 Total: {$total} movies\n" .
        "✅ Sent: {$success}\n" .
        "❌ Failed: {$failed}\n\n" .
        "📊 Success rate: " . round(($success / $total) * 100, 2) . "%"
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
// BACKUP SYSTEM
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
    
    // 3. Clean old backups
    clean_old_backups();
    
    bot_log("Auto-backup process completed");
    return $backup_success;
}

function create_backup_summary() {
    // Backup summary create karta hai
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $summary = "📊 BACKUP SUMMARY\n";
    $summary .= "================\n\n";
    
    $summary .= "📅 Backup Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "🤖 Bot: Entertainment Tadka\n\n";
    
    $summary .= "📈 STATISTICS:\n";
    $summary .= "• Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $summary .= "• Total Users: " . count($users_data['users'] ?? []) . "\n";
    $summary .= "• Total Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $summary .= "• Total Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
    
    $summary .= "💾 FILES BACKED UP:\n";
    $summary .= "• " . CSV_FILE . " (" . (file_exists(CSV_FILE) ? filesize(CSV_FILE) : 0) . " bytes)\n";
    $summary .= "• " . USERS_FILE . " (" . (file_exists(USERS_FILE) ? filesize(USERS_FILE) : 0) . " bytes)\n";
    $summary .= "• " . STATS_FILE . " (" . (file_exists(STATS_FILE) ? filesize(STATS_FILE) : 0) . " bytes)\n";
    $summary .= "• " . REQUEST_FILE . " (" . (file_exists(REQUEST_FILE) ? filesize(REQUEST_FILE) : 0) . " bytes)\n";
    $summary .= "• " . LOG_FILE . " (" . (file_exists(LOG_FILE) ? filesize(LOG_FILE) : 0) . " bytes)\n\n";
    
    $summary .= "🔄 Backup Type: Automated Daily Backup\n";
    $summary .= "📍 Stored In: " . BACKUP_DIR . "\n";
    
    return $summary;
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
            editMessage($chat_id, $progress_msg['result']['message_id'], "✅ Manual backup completed successfully!");
        } else {
            editMessage($chat_id, $progress_msg['result']['message_id'], "⚠️ Backup completed with some warnings.");
        }
        
    } catch (Exception $e) {
        editMessage($chat_id, $progress_msg['result']['message_id'], "❌ Backup failed!\n\nError: " . $e->getMessage());
        bot_log("Manual backup failed: " . $e->getMessage(), 'ERROR');
    }
}

// ==============================
// CHANNEL MANAGEMENT FUNCTIONS
// ==============================
function show_channel_info($chat_id) {
    // All channels ka information show karta hai
    $message = "📢 <b>Join Our Channels</b>\n\n";
    
    $message .= "🍿 <b>Main Channel:</b> " . CHANNEL_1_USERNAME . "\n";
    $message .= "• Latest movie updates\n";
    $message .= "• Daily new additions\n\n";
    
    $message .= "🔒 <b>Backup Channels:</b>\n";
    $message .= "• " . CHANNEL_2_USERNAME . "\n";
    $message .= "• " . CHANNEL_4_USERNAME . "\n\n";
    
    $message .= "🎭 <b>Theater Prints:</b> " . CHANNEL_3_USERNAME . "\n";
    $message .= "• Theater quality prints\n";
    $message .= "• HD screen recordings\n\n";
    
    $message .= "💬 <b>Support Group:</b> " . GROUP_USERNAME . "\n";
    $message .= "• Movie requests\n";
    $message .= "• Support & help\n\n";
    
    $message .= "🔔 <b>Don't forget to join all channels!</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🍿 ' . CHANNEL_1_USERNAME, 'url' => get_channel_username_link(CHANNEL_1_USERNAME)],
                ['text' => '🎭 ' . CHANNEL_3_USERNAME, 'url' => get_channel_username_link(CHANNEL_3_USERNAME)]
            ],
            [
                ['text' => '💬 ' . GROUP_USERNAME, 'url' => get_channel_username_link(GROUP_USERNAME)],
                ['text' => '🔒 ' . CHANNEL_2_USERNAME, 'url' => get_channel_username_link(CHANNEL_2_USERNAME)]
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
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
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_leaderboard($chat_id) {
    // Top users leaderboard show karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $users = $users_data['users'] ?? [];
    
    if (empty($users)) {
        sendMessage($chat_id, "📭 No user data found!");
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
        $message .= "   ⭐ $points points\n\n";
        $i++;
    }
    
    sendMessage($chat_id, $message, null, 'HTML');
}

// ==============================
// BROWSE COMMANDS
// ==============================
function show_latest_movies($chat_id, $limit = 10) {
    // Latest movies show karta hai
    // Typing delay
    send_typing_action($chat_id);
    
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

    $text_msg = "<b>Latest Movies:</b>\n\n";
    foreach($movies as $i => $row){
        $text_msg .= ($start+$i+1).". <b>".htmlspecialchars($row[0])."</b>\n";
        $text_msg .= "   📢 " . htmlspecialchars($row[2] ?? CHANNEL_1_USERNAME) . "\n\n";
    }
    $text_msg .= "\nPage $page of $totalPages";

    // Simple inline buttons
    $buttons = [];
    $row_buttons = [];
    if($page > 1) $row_buttons[] = ['text'=>'⬅️ Prev', 'callback_data'=>'prev_'.$page];
    if($page < $totalPages) $row_buttons[] = ['text'=>'Next ➡️', 'callback_data'=>'next_'.$page];
    if(!empty($row_buttons)) $buttons[] = $row_buttons;
    
    // Download button
    $buttons[] = [['text'=>'📥 Download This Page', 'callback_data'=>'download_page_'.$page]];

    $reply_markup = ['inline_keyboard'=>$buttons];
    sendMessage($chat_id, $text_msg, $reply_markup);
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

    $msg = "<b>📊 Bot Stats:</b>\n\n";
    $msg .= "Total Users: $total_users\n";
    $msg .= "Total Movies: $total_movies\n";
    $msg .= "Total Requests: $total_requests\n\n";
    
    $msg .= "<b>📢 Channels:</b>\n";
    $msg .= "• " . CHANNEL_1_USERNAME . "\n";
    $msg .= "• " . CHANNEL_2_USERNAME . "\n";
    $msg .= "• " . CHANNEL_3_USERNAME . "\n";
    $msg .= "• " . CHANNEL_4_USERNAME . "\n";
    $msg .= "• " . CHANNEL_5_USERNAME . "\n";
    $msg .= "• " . GROUP_USERNAME . "\n";

    sendMessage($chat_id, $msg, null, 'HTML');
    
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
        if (count($row) >= 2) {
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
        $channel = $movie[2] ?? CHANNEL_1_USERNAME;
        
        $message .= "$i. " . htmlspecialchars($movie_name) . "\n";
        $message .= "   📝 ID: $message_id | 📢 $channel\n\n";
        
        $i++;
        
        if (strlen($message) > 3000) {
            sendMessage($chat_id, $message, null, 'HTML');
            $message = "📊 Continuing...\n\n";
        }
    }
    
    $message .= "💾 File: " . CSV_FILE . "\n";
    $message .= "⏰ Last Updated: " . date('Y-m-d H:i:s', filemtime(CSV_FILE));
    
    sendMessage($chat_id, $message, null, 'HTML');
    bot_log("CSV data viewed by $chat_id");
}

// ==============================
// ADD MOVIE COMMAND HANDLER (NEW)
// ==============================
function handle_addmovie($chat_id, $user_id, $params) {
    // /addmovie command handler
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    if (count($params) < 2) {
        $help = "📝 <b>/addmovie Usage:</b>\n\n";
        $help .= "Format: <code>/addmovie \"Movie Name\" message_id channel_username</code>\n\n";
        $help .= "📌 Examples:\n";
        $help .= "• <code>/addmovie \"Avengers Endgame\" 1234 @EntertainmentTadka786</code>\n";
        $help .= "• <code>/addmovie \"KGF 2\" 5678 @threater_print_movies</code>\n";
        $help .= "• <code>/addmovie \"Pushpa 2\" 91011 @ETBackup</code>\n\n";
        $help .= "📢 Available Channels:\n";
        $help .= "• " . CHANNEL_1_USERNAME . "\n";
        $help .= "• " . CHANNEL_2_USERNAME . "\n";
        $help .= "• " . CHANNEL_3_USERNAME . "\n";
        $help .= "• " . CHANNEL_4_USERNAME . "\n";
        $help .= "• " . CHANNEL_5_USERNAME . "\n";
        $help .= "• " . GROUP_USERNAME . "\n\n";
        $help .= "ℹ️ Channel optional (default: " . CHANNEL_1_USERNAME . ")";
        
        sendMessage($chat_id, $help, null, 'HTML');
        return;
    }
    
    // Parse parameters
    $movie_name = $params[0];
    $message_id = $params[1];
    $channel_username = isset($params[2]) ? $params[2] : CHANNEL_1_USERNAME;
    
    // Remove quotes if present
    $movie_name = trim($movie_name, '"\'');
    
    // Validate channel username
    $valid_channels = [
        CHANNEL_1_USERNAME, CHANNEL_2_USERNAME, CHANNEL_3_USERNAME,
        CHANNEL_4_USERNAME, CHANNEL_5_USERNAME, GROUP_USERNAME
    ];
    
    if (!in_array($channel_username, $valid_channels)) {
        $channel_username = CHANNEL_1_USERNAME; // Default
    }
    
    // Add movie to CSV
    if (add_movie_to_csv($movie_name, $message_id, $channel_username)) {
        $response = "✅ <b>Movie Added Successfully!</b>\n\n";
        $response .= "🎬 <b>Movie:</b> $movie_name\n";
        $response .= "📝 <b>Message ID:</b> $message_id\n";
        $response .= "📢 <b>Channel:</b> $channel_username\n\n";
        $response .= "🔄 Cache refreshed automatically";
        
        sendMessage($chat_id, $response, null, 'HTML');
    } else {
        sendMessage($chat_id, "❌ Failed to add movie. Please check parameters.", null, 'HTML');
    }
}

// ==============================
// COMPLETE COMMAND HANDLER WITH ADDED /ADDMOVIE
// ==============================
function handle_command($chat_id, $user_id, $command, $params = []) {
    // Sab commands handle karta hai
    
    // Global update variable access ke liye
    global $update;
    
    // Typing delay for all commands
    send_typing_action($chat_id);
    
    switch ($command) {
        // ==================== CORE COMMANDS ====================
        case '/start':
            $welcome = "🎬 Welcome to Entertainment Tadka!\n\n";
            
            $welcome .= "📢 <b>How to use this bot:</b>\n";
            $welcome .= "• Simply type any movie name\n";
            $welcome .= "• Use English or Hindi\n";
            $welcome .= "• Add 'theater' for theater prints\n";
            $welcome .= "• Partial names also work\n\n";
            
            $welcome .= "🔍 <b>Examples:</b>\n";
            $welcome .= "• kgf 2\n";
            $welcome .= "• avengers endgame\n";
            $welcome .= "• hindi movie\n";
            $welcome .= "• kgf theater print\n\n";
            
            $welcome .= "📢 <b>Join Our Channels:</b>\n";
            $welcome .= "🍿 Main: " . CHANNEL_1_USERNAME . "\n";
            $welcome .= "💬 Support: " . GROUP_USERNAME . "\n";
            $welcome .= "🎭 Theater: " . CHANNEL_3_USERNAME . "\n";
            $welcome .= "🔒 Backup: " . CHANNEL_2_USERNAME . "\n\n";
            
            $welcome .= "💬 <b>Need help?</b> Use /help for all commands";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => ''],
                        ['text' => '🍿 Main Channel', 'url' => get_channel_username_link(CHANNEL_1_USERNAME)]
                    ],
                    [
                        ['text' => '💬 Support', 'url' => get_channel_username_link(GROUP_USERNAME)],
                        ['text' => '🎭 Theater', 'url' => get_channel_username_link(CHANNEL_3_USERNAME)]
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
            $help .= "💬 Support: " . GROUP_USERNAME . " - Support & requests\n";
            $help .= "🎭 Theater: " . CHANNEL_3_USERNAME . " - HD prints\n";
            $help .= "🔒 Backup: " . CHANNEL_2_USERNAME . " - Data protection\n\n";
            
            $help .= "🎯 <b>Search Commands:</b>\n";
            $help .= "• Just type movie name - Smart search\n";
            $help .= "• Add 'theater' for theater prints\n";
            $help .= "• <code>/search movie</code> - Direct search\n";
            $help .= "• <code>/s movie</code> - Quick search\n\n";
            
            $help .= "📁 <b>Browse Commands:</b>\n";
            $help .= "• <code>/totalupload</code> - All movies (5 per page)\n";
            $help .= "• <code>/latest</code> - New additions\n";
            $help .= "• <code>/trending</code> - Popular movies\n";
            $help .= "• <code>/theater</code> - Theater prints only\n\n";
            
            $help .= "📝 <b>Request Commands:</b>\n";
            $help .= "• <code>/request movie</code> - Request movie\n";
            $help .= "• <code>/myrequests</code> - Request status\n\n";
            
            $help .= "👤 <b>User Commands:</b>\n";
            $help .= "• <code>/mystats</code> - Your statistics\n";
            $help .= "• <code>/leaderboard</code> - Top users\n\n";
            
            $help .= "🔗 <b>Channel Commands:</b>\n";
            $help .= "• <code>/channel</code> - All channels\n\n";
            
            $help .= "💡 <b>Pro Tips:</b>\n";
            $help .= "• Use partial names (e.g., 'aveng')\n";
            $help .= "• Add 'theater' for theater prints\n";
            $help .= "• Join all channels for updates\n";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🍿 ' . CHANNEL_1_USERNAME, 'url' => get_channel_username_link(CHANNEL_1_USERNAME)],
                        ['text' => '💬 ' . GROUP_USERNAME, 'url' => get_channel_username_link(GROUP_USERNAME)]
                    ],
                    [
                        ['text' => '🎭 ' . CHANNEL_3_USERNAME, 'url' => get_channel_username_link(CHANNEL_3_USERNAME)],
                        ['text' => '🔒 ' . CHANNEL_2_USERNAME, 'url' => get_channel_username_link(CHANNEL_2_USERNAME)]
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
            totalupload_controller($chat_id, isset($params[0]) ? intval($params[0]) : 1);
            break;

        case '/latest':
        case '/recent':
        case '/new':
            $limit = isset($params[0]) ? intval($params[0]) : 10;
            show_latest_movies($chat_id, $limit);
            break;

        case '/trending':
        case '/popular':
            // Typing delay
            send_typing_action($chat_id);
            sendMessage($chat_id, "🔥 Trending movies feature coming soon!");
            break;

        // ==================== ADD MOVIE COMMAND (NEW) ====================
        case '/addmovie':
            handle_addmovie($chat_id, $user_id, $params);
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

        // ==================== USER COMMANDS ====================
        case '/mystats':
        case '/mystatistics':
        case '/profile':
            show_user_stats($chat_id, $user_id);
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

        // ==================== INFO COMMANDS ====================
        case '/checkdate':
        case '/datestats':
            // Typing delay
            send_typing_action($chat_id);
            sendMessage($chat_id, "📅 Date stats feature coming soon!");
            break;

        case '/stats':
        case '/statistics':
        case '/botstats':
            admin_stats($chat_id);
            break;

        case '/checkcsv':
        case '/csvdata':
        case '/database':
            $show_all = (isset($params[0]) && strtolower($params[0]) == 'all');
            show_csv_data($chat_id, $show_all);
            break;

        case '/info':
        case '/about':
        case '/botinfo':
            // Typing delay
            send_typing_action($chat_id);
            $info = "🤖 <b>Entertainment Tadka Bot</b>\n\n";
            $info .= "📱 Version: 2.0\n";
            $info .= "👨‍💻 Developer: @EntertainmentTadka0786\n";
            $info .= "📢 Main Channel: " . CHANNEL_1_USERNAME . "\n";
            $info .= "💬 Support: " . GROUP_USERNAME . "\n\n";
            $info .= "🎯 Features:\n";
            $info .= "• Multi-channel support\n";
            $info .= "• Smart search\n";
            $info .= "• Simple pagination\n";
            $info .= "• Movie requests\n";
            sendMessage($chat_id, $info, null, 'HTML');
            break;

        // ==================== ADMIN COMMANDS ====================
        case '/broadcast':
            if ($user_id == ADMIN_ID) {
                $message = implode(' ', $params);
                if (empty($message)) {
                    sendMessage($chat_id, "❌ Usage: <code>/broadcast your_message</code>", null, 'HTML');
                    return;
                }
                // Simple broadcast message
                sendMessage($chat_id, "📢 Broadcast feature coming soon!");
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

        case '/maintenance':
            if ($user_id == ADMIN_ID) {
                $mode = isset($params[0]) ? strtolower($params[0]) : '';
                // Simple maintenance toggle
                if ($mode == 'on') {
                    sendMessage($chat_id, "🔧 Maintenance mode ON");
                } elseif ($mode == 'off') {
                    sendMessage($chat_id, "✅ Maintenance mode OFF");
                } else {
                    sendMessage($chat_id, "❌ Usage: <code>/maintenance on</code> or <code>/maintenance off</code>", null, 'HTML');
                }
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        // ==================== UTILITY COMMANDS ====================
        case '/ping':
        case '/status':
            sendMessage($chat_id, "🏓 <b>Bot Status:</b> ✅ Online\n⏰ <b>Server Time:</b> " . date('Y-m-d H:i:s'), null, 'HTML');
            break;

        default:
            sendMessage($chat_id, "❌ Unknown command. Use <code>/help</code> to see all available commands.", null, 'HTML');
    }
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

    // Channel post handling - ALL CHANNELS se automatically movies add karo
    if (isset($update['channel_post'])) {
        $message = $update['channel_post'];
        $message_id = $message['message_id'];
        $chat_id = $message['chat']['id'];
        
        // Check if it's from any of our channels
        $channel_username = '';
        
        if ($chat_id == CHANNEL_1_ID) {
            $channel_username = CHANNEL_1_USERNAME;
        } elseif ($chat_id == CHANNEL_2_ID) {
            $channel_username = CHANNEL_2_USERNAME;
        } elseif ($chat_id == CHANNEL_3_ID) {
            $channel_username = CHANNEL_3_USERNAME;
        } elseif ($chat_id == CHANNEL_4_ID) {
            $channel_username = CHANNEL_4_USERNAME;
        } elseif ($chat_id == CHANNEL_5_ID) {
            $channel_username = CHANNEL_5_USERNAME;
        } elseif ($chat_id == GROUP_ID) {
            $channel_username = GROUP_USERNAME;
        } else {
            // Not our channel, ignore
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
            // Add movie to CSV with new format
            add_movie_to_csv($text, $message_id, $channel_username);
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

        // Command handling
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $params = array_slice($parts, 1);
            
            handle_command($chat_id, $user_id, $command, $params);
        } else if (!empty(trim($text))) {
            // Typing delay before search
            send_typing_action($chat_id);
            
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
        
        // Typing delay for callbacks
        send_typing_action($chat_id);
        
        // Movie selection from smart suggestion
        if(strpos($data, 'movie_') === 0){
            $movie_name = str_replace('movie_', '', $data);
            
            if (isset($movie_messages[strtolower($movie_name)])) {
                $entries = $movie_messages[strtolower($movie_name)];
                
                foreach ($entries as $entry) {
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
        // Pagination callbacks for /totaluploads
        elseif (strpos($data, 'pag_') === 0) {
            $parts = explode('_', $data);
            $action = $parts[1];
            
            if ($action == 'prev') {
                $current_page = isset($parts[2]) ? intval($parts[2]) : 1;
                totalupload_controller($chat_id, max(1, $current_page - 1));
                answerCallbackQuery($query['id'], "Previous page");
            }
            elseif ($action == 'next') {
                $current_page = isset($parts[2]) ? intval($parts[2]) : 1;
                $all = get_all_movies_list();
                $total_pages = ceil(count($all) / ITEMS_PER_PAGE);
                totalupload_controller($chat_id, min($total_pages, $current_page + 1));
                answerCallbackQuery($query['id'], "Next page");
            }
        }
        // Send page batch download
        elseif (strpos($data, 'send_page_') === 0) {
            $page_num = intval(str_replace('send_page_', '', $data));
            $all = get_all_movies_list();
            $pg = simple_paginate_movies($all, $page_num);
            simple_batch_download($chat_id, $pg['slice'], $page_num);
            answerCallbackQuery($query['id'], "📦 Batch started!");
        }
        // Download page from /latest
        elseif (strpos($data, 'download_page_') === 0) {
            $page_num = intval(str_replace('download_page_', '', $data));
            
            if(!file_exists(CSV_FILE)){
                answerCallbackQuery($query['id'], "No movies found", true);
                break;
            }

            $csv = array_map('str_getcsv', file(CSV_FILE));
            $csv = array_reverse($csv);
            $total = count($csv);
            $totalPages = ceil($total / ITEMS_PER_PAGE);
            if($page_num < 1) $page_num = 1;
            if($page_num > $totalPages) $page_num = $totalPages;

            $start = ($page_num-1) * ITEMS_PER_PAGE;
            $movies_data = array_slice($csv, $start, ITEMS_PER_PAGE);
            
            // Convert to movie format
            $movies = [];
            foreach ($movies_data as $row) {
                if (count($row) >= 2) {
                    $movies[] = [
                        'movie_name' => $row[0],
                        'message_id_raw' => $row[1],
                        'message_id' => is_numeric($row[1]) ? intval($row[1]) : null,
                        'channel_username' => $row[2] ?? CHANNEL_1_USERNAME,
                        'channel_id' => get_channel_id_by_username($row[2] ?? CHANNEL_1_USERNAME)
                    ];
                }
            }
            
            simple_batch_download($chat_id, $movies, $page_num);
            answerCallbackQuery($query['id'], "Downloading page $page_num");
        }
        // Pagination for /latest
        elseif (preg_match('/(next|prev)_(\d+)/', $data, $matches)) {
            $dir = $matches[1];
            $current = intval($matches[2]);
            $new_page = ($dir == 'next') ? $current + 1 : $current - 1;
            
            show_latest_movies($chat_id, 10);
            answerCallbackQuery($query['id'], "Page $new_page");
        }
        // Close pagination
        elseif ($data == 'close_pagination' || $data == 'close') {
            deleteMessage($chat_id, $message['message_id']);
            sendMessage($chat_id, "🗂️ Pagination closed.");
            answerCallbackQuery($query['id'], "Closed");
        }
        // Movie requests
        elseif (strpos($data, 'auto_request_') === 0) {
            $movie_name = base64_decode(str_replace('auto_request_', '', $data));
            $lang = detect_language($movie_name);
            
            if (add_movie_request($user_id, $movie_name, $lang)) {
                send_multilingual_response($chat_id, 'request_success', $lang);
                answerCallbackQuery($query['id'], "Request sent!");
                update_user_activity($user_id, 'movie_request');
            } else {
                send_multilingual_response($chat_id, 'request_limit', $lang);
                answerCallbackQuery($query['id'], "Daily limit reached!", true);
            }
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
        // Other callbacks
        elseif ($data === 'refresh_stats') {
            show_user_stats($chat_id, $user_id);
            answerCallbackQuery($query['id'], "Refreshed");
        }
        elseif ($data === 'refresh_leaderboard') {
            show_leaderboard($chat_id);
            answerCallbackQuery($query['id'], "Refreshed");
        }
        else {
            sendMessage($chat_id, "❌ Action not found: " . $data);
            answerCallbackQuery($query['id'], "❌ Action not available");
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
// MANUAL TESTING FUNCTIONS
// ==============================
if (isset($_GET['test_save'])) {
    // Manual testing ke liye movie save function
    function manual_save_to_csv($movie_name, $message_id, $channel_username = CHANNEL_1_USERNAME) {
        $entry = [$movie_name, $message_id, $channel_username];
        $handle = fopen(CSV_FILE, "a");
        if ($handle !== FALSE) {
            fputcsv($handle, $entry);
            fclose($handle);
            @chmod(CSV_FILE, 0666);
            return true;
        }
        return false;
    }
    
    // Test movies save karo from different channels
    manual_save_to_csv("Metro In Dino (2025)", 1924, CHANNEL_1_USERNAME);
    manual_save_to_csv("Metro In Dino 2025 WebRip 480p", 1925, CHANNEL_2_USERNAME);
    manual_save_to_csv("Metro In Dino (2025) Hindi 720p", 1926, CHANNEL_1_USERNAME);
    manual_save_to_csv("Animal (2023) Hindi 1080p", 1927, CHANNEL_3_USERNAME);
    manual_save_to_csv("Avengers Endgame (2019) English", 1928, CHANNEL_1_USERNAME);
    manual_save_to_csv("KGF Chapter 2 (2022) Theater Print", 1929, CHANNEL_3_USERNAME);
    manual_save_to_csv("Pushpa 2 The Rule (2024) Theater", 1930, CHANNEL_3_USERNAME);
    
    echo "✅ All 7 movies manually save ho gayi from different channels!<br>";
    echo "📊 <a href='?check_csv=1'>Check CSV</a> | ";
    echo "<a href='?setwebhook=1'>Reset Webhook</a> | ";
    echo "<a href='?test_stats=1'>Test Stats</a>";
    exit;
}

if (isset($_GET['check_csv'])) {
    // CSV content check karo
    echo "<h3>CSV Content (New Format):</h3>";
    if (file_exists(CSV_FILE)) {
        $lines = file(CSV_FILE);
        foreach ($lines as $line) {
            echo htmlspecialchars($line) . "<br>";
        }
    } else {
        echo "❌ CSV file not found!";
    }
    exit;
}

if (isset($_GET['test_stats'])) {
    // Statistics test karo
    echo "<h3>Bot Statistics:</h3>";
    $stats = get_stats();
    echo "<pre>";
    print_r($stats);
    echo "</pre>";
    
    echo "<h3>User Data:</h3>";
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    echo "<pre>";
    print_r($users_data);
    echo "</pre>";
    exit;
}

// Webhook setup
if (php_sapi_name() === 'cli' || isset($_GET['setwebhook'])) {
    // Webhook setup karo
    $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $result = apiRequest('setWebhook', ['url' => $webhook_url]);
    
    echo "<h1>Webhook Setup</h1>";
    echo "<p>Result: " . htmlspecialchars($result) . "</p>";
    echo "<p>Webhook URL: " . htmlspecialchars($webhook_url) . "</p>";
    
    $bot_info = json_decode(apiRequest('getMe'), true);
    if ($bot_info && isset($bot_info['ok']) && $bot_info['ok']) {
        echo "<h2>Bot Info</h2>";
        echo "<p>Name: " . htmlspecialchars($bot_info['result']['first_name']) . "</p>";
        echo "<p>Username: @" . htmlspecialchars($bot_info['result']['username']) . "</p>";
    }
    
    echo "<h3>All Channels Configured:</h3>";
    echo "<p>1. " . CHANNEL_1_USERNAME . " (ID: " . CHANNEL_1_ID . ")</p>";
    echo "<p>2. " . CHANNEL_2_USERNAME . " (ID: " . CHANNEL_2_ID . ")</p>";
    echo "<p>3. " . CHANNEL_3_USERNAME . " (ID: " . CHANNEL_3_ID . ")</p>";
    echo "<p>4. " . CHANNEL_4_USERNAME . " (ID: " . CHANNEL_4_ID . ")</p>";
    echo "<p>5. " . CHANNEL_5_USERNAME . " (ID: " . CHANNEL_5_ID . ")</p>";
    echo "<p>6. " . GROUP_USERNAME . " (ID: " . GROUP_ID . ")</p>";
    
    echo "<h3>System Status</h3>";
    echo "<p>CSV File: " . (file_exists(CSV_FILE) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Users File: " . (file_exists(USERS_FILE) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Stats File: " . (file_exists(STATS_FILE) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Backup Directory: " . (file_exists(BACKUP_DIR) ? "✅ Exists" : "❌ Missing") . "</p>";
    
    exit;
}

// Default page display
if (!isset($update) || !$update) {
    // Bot status page show karo
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    echo "<h1>🎬 Entertainment Tadka Bot</h1>";
    echo "<p><strong>Status:</strong> ✅ Running</p>";
    echo "<p><strong>Total Movies:</strong> " . ($stats['total_movies'] ?? 0) . "</p>";
    echo "<p><strong>Total Users:</strong> " . count($users_data['users'] ?? []) . "</p>";
    echo "<p><strong>Total Searches:</strong> " . ($stats['total_searches'] ?? 0) . "</p>";
    echo "<p><strong>Last Updated:</strong> " . ($stats['last_updated'] ?? 'N/A') . "</p>";
    
    echo "<h3>📢 All Channels Configured:</h3>";
    echo "<ul>";
    echo "<li>" . CHANNEL_1_USERNAME . " - Main Channel</li>";
    echo "<li>" . CHANNEL_2_USERNAME . " - Backup</li>";
    echo "<li>" . CHANNEL_3_USERNAME . " - Theater Prints</li>";
    echo "<li>" . CHANNEL_4_USERNAME . " - Backup 2</li>";
    echo "<li>" . CHANNEL_5_USERNAME . " - Private Channel</li>";
    echo "<li>" . GROUP_USERNAME . " - Support Group</li>";
    echo "</ul>";
    
    echo "<h3>🚀 Quick Setup</h3>";
    echo "<p><a href='?setwebhook=1'>Set Webhook Now</a></p>";
    echo "<p><a href='?test_save=1'>Test Movie Save</a></p>";
    echo "<p><a href='?check_csv=1'>Check CSV Data</a></p>";
    
    echo "<h3>📋 Available Commands</h3>";
    echo "<ul>";
    echo "<li><code>/start</code> - Welcome message</li>";
    echo "<li><code>/help</code> - All commands</li>";
    echo "<li><code>/search movie</code> - Search movies</li>";
    echo "<li><code>/totalupload</code> - Browse all movies (5 per page)</li>";
    echo "<li><code>/addmovie</code> - Admin: Add movie (NEW)</li>";
    echo "<li><code>/latest</code> - Latest movies</li>";
    echo "<li><code>/request movie</code> - Request movie</li>";
    echo "<li><code>/mystats</code> - User statistics</li>";
    echo "<li><code>/leaderboard</code> - Top users</li>";
    echo "<li><code>/channel</code> - Join channels</li>";
    echo "<li><code>/checkcsv</code> - View CSV data</li>";
    echo "<li><code>/stats</code> - Admin statistics</li>";
    echo "</ul>";
    
    echo "<h3>🎯 New Features Added</h3>";
    echo "<ul>";
    echo "<li>✅ ALL 6 CHANNELS SUPPORT - Forwarding from all channels</li>";
    echo "<li>✅ NEW CSV FORMAT: movie-name,message_id,channel_username</li>";
    echo "<li>✅ /addmovie COMMAND - Admin can add movies manually</li>";
    echo "<li>✅ SIMPLE SMART SUGGESTION - Top 3 suggestions only</li>";
    echo "<li>✅ TOTALUPLOADS WITH MAX 5 POSTS PER PAGE</li>";
    echo "<li>✅ SIMPLE PAGINATION - Prev/Next buttons only</li>";
    echo "<li>✅ TYPING DELAY - 1 second delay added</li>";
    echo "<li>✅ AUTO-MOVIE ADDITION - From all configured channels</li>";
    echo "<li>✅ MULTI-CHANNEL FORWARDING - Movies from any channel</li>";
    echo "</ul>";
    
    echo "<h3>📊 Recent Activity</h3>";
    if (file_exists(LOG_FILE)) {
        $logs = array_slice(file(LOG_FILE), -10);
        echo "<pre>";
        foreach ($logs as $log) {
            echo htmlspecialchars($log);
        }
        echo "</pre>";
    }
}
?>
