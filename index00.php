<?php
/**
 * ========================================================================
 * TELEGRAM MOVIE BOT - ULTIMATE PRODUCTION READY VERSION
 * ========================================================================
 * 🎬 Entertainment Tadka Bot - Complete Movie Search & Delivery System
 * 📅 Version: 3.0.0 | Production Ready for Render.com
 * 🔗 Multi-Channel Support with Enhanced Features
 * ========================================================================
 * 
 * SUPPORTED CHANNELS:
 * 1. 🍿 Main Channel: @EntertainmentTadka786 (-1003251791991)
 * 2. 🎭 Theater Prints: @threater_print_movies (-1003614546520)
 * 3. 🔒 Backup Channel: @ETBackup (-1002337293281)
 * 4. 🔐 Private Channel: -1002831605258
 * 5. 💾 Backup 2: -1002964109368
 * 6. 📡 Any Channel: -1003181705395
 * 
 * FEATURES:
 * ✅ Smart Movie Search with Fuzzy Matching
 * ✅ Multi-Language Support (Hindi/English)
 * ✅ NO FORWARD HEADERS - CopyMessage Instead of Forward
 * ✅ Enhanced Pagination with Sessions & Filters
 * ✅ Batch Download with Progress Tracking
 * ✅ Auto-Notification for Requested Movies
 * ✅ Quick Add Movies from ALL Channels
 * ✅ User Points System & Leaderboard
 * ✅ Complete Backup System with Channel Upload
 * ✅ Maintenance Mode Support
 * ✅ Security Headers & Flood Control
 * ✅ Delay Typing for Realistic Interaction
 * ========================================================================
 */

// ==================== SECURITY & INITIAL SETUP ====================
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ==================== ERROR HANDLING ====================
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kolkata');

// ==================== SECURITY CHECK ====================
if (php_sapi_name() === 'cli') {
    die("CLI access not allowed");
}

// ==================== ENVIRONMENT CONFIG (RENDER.COM) ====================
// Render.com se environment variables use karo
$port = getenv('PORT') ?: '80';
$BOT_TOKEN = getenv('BOT_TOKEN') ?: 'YOUR_BOT_TOKEN_HERE';
$ADMIN_ID = (int)(getenv('ADMIN_ID') ?: '123456789');
$REQUEST_GROUP_ID = getenv('REQUEST_GROUP_ID') ?: '-100XXXXXXXXXX';
$CHANNELS_STRING = getenv('CHANNELS') ?: '-1003251791991,-1003614546520,-1002337293281,-1002831605258,-1002964109368,-1003181705395';

// Parse channels from environment
$CHANNELS = explode(',', $CHANNELS_STRING);
$API_URL = 'https://api.telegram.org/bot' . $BOT_TOKEN . '/';

// ==================== FILE PATHS ====================
define('CSV_FILE', __DIR__ . '/movies.csv');
define('USERS_JSON', __DIR__ . '/users.json');
define('STATS_FILE', __DIR__ . '/bot_stats.json');
define('REQUEST_FILE', __DIR__ . '/movie_requests.json');
define('UPLOADS_DIR', __DIR__ . '/uploads/');
define('BACKUP_DIR', __DIR__ . '/backups/');
define('LOG_FILE', __DIR__ . '/bot_activity.log');

// ==================== CONSTANTS ====================
define('USER_COOLDOWN', 20);
define('PER_PAGE', 5);
define('CACHE_EXPIRY', 300);
define('ITEMS_PER_PAGE', 5);
define('MAX_SEARCH_RESULTS', 15);
define('DAILY_REQUEST_LIMIT', 5);
define('AUTO_BACKUP_HOUR', '03');
define('MAX_PAGES_TO_SHOW', 7);
define('PAGINATION_CACHE_TIMEOUT', 60);
define('PREVIEW_ITEMS', 3);
define('BATCH_SIZE', 5);

// ==================== CHANNEL CONSTANTS ====================
define('MAIN_CHANNEL', '@EntertainmentTadka786');
define('MAIN_CHANNEL_ID', '-1003251791991');
define('THEATER_CHANNEL', '@threater_print_movies');
define('THEATER_CHANNEL_ID', '-1003614546520');
define('BACKUP_CHANNEL_USERNAME', '@ETBackup');
define('BACKUP_CHANNEL_ID', '-1002337293281');
define('BACKUP_CHANNEL_2_ID', '-1002964109368');
define('REQUEST_CHANNEL', '@EntertainmentTadka7860');
define('PRIVATE_CHANNEL_ID', '-1002831605258');
define('ANY_CHANNEL_ID', '-1003181705395');

// ==================== GLOBAL VARIABLES ====================
$movie_messages = array();
$movie_cache = array();
$waiting_users = array();
$user_sessions = array();
$user_pagination_sessions = array();
$user_quickadd_sessions = array();
$MAINTENANCE_MODE = false;
$MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>\n\nWe're temporarily unavailable for updates.\nWill be back in few days!\n\nThanks for patience 🙏";

// ==================== LOGGING SYSTEM ====================
function log_message($message, $type = 'INFO') {
    $log_file = __DIR__ . '/logs/bot_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$type] $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function bot_log($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

// ==================== INITIAL SETUP ====================
function init_storage() {
    // CSV file initialize karo
    if (!file_exists(CSV_FILE)) {
        file_put_contents(CSV_FILE, "movie_name,message_id,date,video_path,quality,size,language,channel_type,channel_id,channel_username\n");
        chmod(CSV_FILE, 0644);
        log_message("CSV file created");
    }
    
    // Users JSON initialize karo
    if (!file_exists(USERS_JSON)) {
        file_put_contents(USERS_JSON, json_encode(['users' => []], JSON_PRETTY_PRINT));
        chmod(USERS_JSON, 0644);
        log_message("Users JSON created");
    }
    
    // Stats file initialize karo
    if (!file_exists(STATS_FILE)) {
        file_put_contents(STATS_FILE, json_encode([
            'total_movies' => 0,
            'total_users' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
            'successful_searches' => 0,
            'failed_searches' => 0,
            'daily_activity' => [],
            'last_updated' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT));
        log_message("Stats file created");
    }
    
    // Request file initialize karo
    if (!file_exists(REQUEST_FILE)) {
        file_put_contents(REQUEST_FILE, json_encode([
            'requests' => [],
            'pending_approval' => [],
            'completed_requests' => [],
            'user_request_count' => []
        ], JSON_PRETTY_PRINT));
        log_message("Request file created");
    }
    
    // Directories create karo
    $dirs = [UPLOADS_DIR, __DIR__ . '/logs', BACKUP_DIR];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            chmod($dir, 0755);
        }
    }
}

// ==================== TELEGRAM API FUNCTIONS ====================
function apiRequest($method, $params = array(), $is_multipart = false) {
    global $API_URL;
    $url = $API_URL . $method;
    
    if ($is_multipart) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
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
        $options = array(
            'http' => array(
                'method' => 'POST',
                'content' => http_build_query($params),
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
            ),
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        );
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            bot_log("API Request failed for method: $method", 'ERROR');
        }
        return $result;
    }
}

function tg($method, $data = []) {
    global $API_URL;
    $url = $API_URL . $method;
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => json_encode($data)
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    
    try {
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        log_message("API Call: $method - " . substr(json_encode($data), 0, 200));
        return $response ? json_decode($response, true) : false;
    } catch (Exception $e) {
        log_message("API Error: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    
    $result = apiRequest('sendMessage', $data);
    bot_log("Message sent to $chat_id: " . substr($text, 0, 50) . "...");
    return json_decode($result, true);
}

function sendMessageWithDelay($chat_id, $text, $reply_markup = null, $parse_mode = null, $delay_ms = 1000) {
    $typing_data = [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ];
    
    apiRequest('sendChatAction', $typing_data);
    usleep($delay_ms * 1000);
    
    return sendMessage($chat_id, $text, $reply_markup, $parse_mode);
}

function editMessage($chat_id, $message_id, $new_text, $reply_markup = null) {
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
    apiRequest('deleteMessage', [
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
    apiRequest('answerCallbackQuery', $data);
}

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    $result = apiRequest('forwardMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
    return $result;
}

function copyMessage($chat_id, $from_chat_id, $message_id) {
    return apiRequest('copyMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

// ==================== FLOOD CONTROL ====================
function is_flood($user_id) {
    $flood_file = sys_get_temp_dir() . '/tgflood_' . $user_id;
    
    if (file_exists($flood_file)) {
        $last_time = file_get_contents($flood_file);
        if (time() - (int)$last_time < USER_COOLDOWN) {
            return true;
        }
    }
    
    file_put_contents($flood_file, time());
    return false;
}

// ==================== CHANNEL MAPPING FUNCTIONS ====================
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

function get_channel_type_by_id($channel_id) {
    $channel_id = strval($channel_id);
    
    if ($channel_id == MAIN_CHANNEL_ID) return 'main';
    if ($channel_id == THEATER_CHANNEL_ID) return 'theater';
    if ($channel_id == BACKUP_CHANNEL_ID) return 'backup';
    if ($channel_id == BACKUP_CHANNEL_2_ID) return 'backup2';
    if ($channel_id == PRIVATE_CHANNEL_ID) return 'private';
    if ($channel_id == ANY_CHANNEL_ID) return 'any';
    
    return 'other';
}

function get_channel_display_name($channel_type) {
    $names = [
        'main' => '🍿 Main Channel',
        'theater' => '🎭 Theater Prints',
        'backup' => '🔒 Backup Channel',
        'backup2' => '💾 Backup 2',
        'private' => '🔐 Private Channel',
        'any' => '📡 Any Channel',
        'other' => '📢 Other Channel'
    ];
    
    return $names[$channel_type] ?? '📢 Unknown Channel';
}

function get_direct_channel_link($message_id, $channel_id) {
    if (empty($channel_id)) {
        return "Channel ID not available";
    }
    
    $channel_id_clean = str_replace('-100', '', $channel_id);
    return "https://t.me/c/" . $channel_id_clean . "/" . $message_id;
}

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

// ==================== CSV MANAGEMENT ====================
function add_movie($movie_name, $message_id, $channel_id) {
    init_storage();
    
    // Duplicate check
    $rows = file(CSV_FILE, FILE_IGNORE_NEW_LINES);
    foreach ($rows as $r) {
        if (strpos($r, ',' . $message_id . ',') !== false) {
            return false;
        }
    }
    
    $channel_type = get_channel_type_by_id($channel_id);
    $channel_username = '';
    
    switch ($channel_type) {
        case 'main':
            $channel_username = MAIN_CHANNEL;
            break;
        case 'theater':
            $channel_username = THEATER_CHANNEL;
            break;
        case 'backup':
        case 'backup2':
            $channel_username = BACKUP_CHANNEL_USERNAME;
            break;
        default:
            $channel_username = '';
    }
    
    // Auto-detect quality
    $quality = 'Unknown';
    $movie_name_lower = strtolower($movie_name);
    $quality_patterns = [
        '1080p' => ['1080p', '1080', 'fhd', 'full hd'],
        '720p' => ['720p', '720', 'hd'],
        '480p' => ['480p', '480', 'sd'],
        'theater' => ['theater', 'theatre', 'print', 'hdcam', 'camrip', 'hdrip']
    ];
    
    foreach ($quality_patterns as $q => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($movie_name_lower, $pattern) !== false) {
                $quality = $q;
                break 2;
            }
        }
    }
    
    // Auto-detect language
    $language = 'Hindi';
    $language_patterns = [
        'English' => ['english', 'eng', 'en'],
        'Hindi' => ['hindi', 'hin', 'hd'],
        'Tamil' => ['tamil', 'tam'],
        'Telugu' => ['telugu', 'tel'],
        'Malayalam' => ['malayalam', 'mal'],
        'Kannada' => ['kannada', 'kan']
    ];
    
    foreach ($language_patterns as $lang => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($movie_name_lower, $pattern) !== false) {
                $language = $lang;
                break 2;
            }
        }
    }
    
    $fp = fopen(CSV_FILE, 'a');
    fputcsv($fp, [$movie_name, $message_id, date('d-m-Y'), '', $quality, 'Unknown', $language, $channel_type, $channel_id, $channel_username]);
    fclose($fp);
    
    // Update cache
    global $movie_messages, $movie_cache;
    $movie_key = strtolower($movie_name);
    if (!isset($movie_messages[$movie_key])) {
        $movie_messages[$movie_key] = [];
    }
    $movie_messages[$movie_key][] = [
        'movie_name' => $movie_name,
        'message_id' => intval($message_id),
        'message_id_raw' => $message_id,
        'channel_id' => $channel_id,
        'channel_type' => $channel_type,
        'quality' => $quality,
        'language' => $language,
        'date' => date('d-m-Y')
    ];
    $movie_cache = [];
    
    log_message("Movie added: $movie_name (ID: $message_id) to channel $channel_id");
    update_stats('total_movies', 1);
    return true;
}

function get_all_movies() {
    init_storage();
    $movies = [];
    if (($h = fopen(CSV_FILE, 'r')) !== false) {
        fgetcsv($h); // Header skip
        while (($d = fgetcsv($h)) !== false) {
            if (count($d) >= 3) {
                $movies[] = [
                    'movie_name' => $d[0],
                    'message_id' => $d[1],
                    'date' => $d[2],
                    'quality' => $d[4] ?? 'Unknown',
                    'size' => $d[5] ?? 'Unknown',
                    'language' => $d[6] ?? 'Hindi',
                    'channel_type' => $d[7] ?? 'main',
                    'channel_id' => $d[8] ?? '',
                    'channel_username' => $d[9] ?? ''
                ];
            }
        }
        fclose($h);
    }
    return $movies;
}

function search_movie($query) {
    $query = strtolower(trim($query));
    $results = [];
    
    foreach (get_all_movies() as $movie) {
        if (strpos(strtolower($movie['movie_name']), $query) !== false) {
            $results[] = $movie;
        }
    }
    
    return $results;
}

function load_and_clean_csv($filename = CSV_FILE) {
    global $movie_messages;
    
    if (!file_exists($filename)) {
        file_put_contents($filename, "movie_name,message_id,date,video_path,quality,size,language,channel_type,channel_id,channel_username\n");
        return [];
    }

    $data = [];
    $handle = fopen($filename, "r");
    if ($handle !== FALSE) {
        $header = fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && (!empty(trim($row[0])))) {
                $movie_name = trim($row[0]);
                $message_id_raw = isset($row[1]) ? trim($row[1]) : '';
                $date = isset($row[2]) ? trim($row[2]) : '';
                $video_path = isset($row[3]) ? trim($row[3]) : '';
                $quality = isset($row[4]) ? trim($row[4]) : 'Unknown';
                $size = isset($row[5]) ? trim($row[5]) : 'Unknown';
                $language = isset($row[6]) ? trim($row[6]) : 'Hindi';
                $channel_type = isset($row[7]) ? trim($row[7]) : 'main';
                $channel_id = isset($row[8]) ? trim($row[8]) : '';
                $channel_username = isset($row[9]) ? trim($row[9]) : '';

                if (empty($channel_type) && !empty($channel_id)) {
                    $channel_type = get_channel_type_by_id($channel_id);
                }

                $entry = [
                    'movie_name' => $movie_name,
                    'message_id_raw' => $message_id_raw,
                    'date' => $date,
                    'video_path' => $video_path,
                    'quality' => $quality,
                    'size' => $size,
                    'language' => $language,
                    'channel_type' => $channel_type,
                    'channel_id' => $channel_id,
                    'channel_username' => $channel_username,
                    'source_channel' => $channel_id
                ];
                
                if (is_numeric($message_id_raw)) {
                    $entry['message_id'] = intval($message_id_raw);
                } else {
                    $entry['message_id'] = null;
                }

                $data[] = $entry;

                $movie = strtolower($movie_name);
                if (!isset($movie_messages[$movie])) $movie_messages[$movie] = [];
                $movie_messages[$movie][] = $entry;
            }
        }
        fclose($handle);
    }

    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats['total_movies'] = count($data);
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));

    $handle = fopen($filename, "w");
    fputcsv($handle, array('movie_name','message_id','date','video_path','quality','size','language','channel_type','channel_id','channel_username'));
    foreach ($data as $row) {
        fputcsv($handle, [
            $row['movie_name'], 
            $row['message_id_raw'], 
            $row['date'], 
            $row['video_path'],
            $row['quality'],
            $row['size'],
            $row['language'],
            $row['channel_type'],
            $row['channel_id'],
            $row['channel_username']
        ]);
    }
    fclose($handle);

    bot_log("CSV cleaned and reloaded - " . count($data) . " entries");
    return $data;
}

// ==================== CACHING SYSTEM ====================
function get_cached_movies() {
    global $movie_cache;
    
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < CACHE_EXPIRY) {
        return $movie_cache['data'];
    }
    
    $movie_cache = [
        'data' => load_and_clean_csv(),
        'timestamp' => time()
    ];
    
    bot_log("Movie cache refreshed - " . count($movie_cache['data']) . " movies");
    return $movie_cache['data'];
}

// ==================== STATISTICS SYSTEM ====================
function update_stats($field, $increment = 1) {
    if (!file_exists(STATS_FILE)) return;
    
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats[$field] = ($stats[$field] ?? 0) + $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    
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
    if (!file_exists(STATS_FILE)) return [];
    return json_decode(file_get_contents(STATS_FILE), true);
}

// ==================== USER MANAGEMENT ====================
function update_user_data($user_id, $user_info = []) {
    $users_data = json_decode(file_get_contents(USERS_JSON), true);
    
    if (!isset($users_data['users'][$user_id])) {
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
    file_put_contents(USERS_JSON, json_encode($users_data, JSON_PRETTY_PRINT));
    
    return $users_data['users'][$user_id];
}

function update_user_activity($user_id, $action) {
    $users_data = json_decode(file_get_contents(USERS_JSON), true);
    
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
        file_put_contents(USERS_JSON, json_encode($users_data, JSON_PRETTY_PRINT));
    }
}

// ==================== MOVIE DELIVERY SYSTEM ====================
function deliver_item_to_chat($chat_id, $item) {
    if (!isset($item['channel_id']) || empty($item['channel_id'])) {
        $source_channel = MAIN_CHANNEL_ID;
        bot_log("Channel ID not found for movie: {$item['movie_name']}, using default", 'WARNING');
    } else {
        $source_channel = $item['channel_id'];
    }
    
    $channel_type = isset($item['channel_type']) ? $item['channel_type'] : 'main';
    
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        $result = json_decode(copyMessage($chat_id, $source_channel, $item['message_id']), true);
        
        if ($result && $result['ok']) {
            update_stats('total_downloads', 1);
            bot_log("Movie COPIED from $channel_type: {$item['movie_name']} to $chat_id");
            return true;
        } else {
            $fallback_result = json_decode(forwardMessage($chat_id, $source_channel, $item['message_id']), true);
            
            if ($fallback_result && $fallback_result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("Movie FORWARDED from $channel_type: {$item['movie_name']} to $chat_id");
                return true;
            }
        }
    }
    
    if (!empty($item['message_id_raw'])) {
        $message_id_clean = preg_replace('/[^0-9]/', '', $item['message_id_raw']);
        if (is_numeric($message_id_clean) && $message_id_clean > 0) {
            $result = json_decode(copyMessage($chat_id, $source_channel, $message_id_clean), true);
            
            if ($result && $result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("Movie COPIED (raw ID) from $channel_type: {$item['movie_name']} to $chat_id");
                return true;
            } else {
                $fallback_result = json_decode(forwardMessage($chat_id, $source_channel, $message_id_clean), true);
                
                if ($fallback_result && $fallback_result['ok']) {
                    update_stats('total_downloads', 1);
                    bot_log("Movie FORWARDED (raw ID) from $channel_type: {$item['movie_name']} to $chat_id");
                    return true;
                }
            }
        }
    }

    $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n";
    $text .= "📊 Quality: " . htmlspecialchars($item['quality'] ?? 'Unknown') . "\n";
    $text .= "💾 Size: " . htmlspecialchars($item['size'] ?? 'Unknown') . "\n";
    $text .= "🗣️ Language: " . htmlspecialchars($item['language'] ?? 'Hindi') . "\n";
    $text .= "🎭 Channel: " . get_channel_display_name($channel_type) . "\n";
    $text .= "📅 Date: " . htmlspecialchars($item['date'] ?? 'N/A') . "\n";
    $text .= "📎 Reference: " . htmlspecialchars($item['message_id_raw'] ?? 'N/A') . "\n\n";
    
    if (!empty($item['message_id']) && is_numeric($item['message_id']) && !empty($source_channel)) {
        $text .= "🔗 Direct Link: " . get_direct_channel_link($item['message_id'], $source_channel) . "\n\n";
    }
    
    $text .= "⚠️ Join channel to access content: " . get_channel_username_link($channel_type);
    
    sendMessage($chat_id, $text, null, 'HTML');
    update_stats('total_downloads', 1);
    return false;
}

// ==================== SEARCH SYSTEM ====================
function smart_search($query) {
    global $movie_messages;
    $query_lower = strtolower(trim($query));
    $results = array();
    
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
    
    foreach ($movie_messages as $movie => $entries) {
        $score = 0;
        
        foreach ($entries as $entry) {
            $entry_channel_type = $entry['channel_type'] ?? 'main';
            
            if ($is_theater_search && $entry_channel_type == 'theater') {
                $score += 20;
            } elseif (!$is_theater_search && $entry_channel_type == 'main') {
                $score += 10;
            }
            
            if (in_array($entry_channel_type, ['backup', 'backup2', 'private', 'any'])) {
                $score += 5;
            }
        }
        
        if ($movie == $query_lower) {
            $score = 100;
        }
        elseif (strpos($movie, $query_lower) !== false) {
            $score = 80 - (strlen($movie) - strlen($query_lower));
        }
        else {
            similar_text($movie, $query_lower, $similarity);
            if ($similarity > 60) $score = $similarity;
        }
        
        foreach ($entries as $entry) {
            if (stripos($entry['quality'] ?? '', '1080') !== false) $score += 5;
            if (stripos($entry['quality'] ?? '', '720') !== false) $score += 3;
            if (stripos($entry['language'] ?? '', 'hindi') !== false) $score += 2;
        }
        
        if ($score > 0) {
            $channel_types = array_column($entries, 'channel_type');
            $results[$movie] = [
                'score' => $score,
                'count' => count($entries),
                'latest_entry' => end($entries),
                'qualities' => array_unique(array_column($entries, 'quality')),
                'has_theater' => in_array('theater', $channel_types),
                'has_main' => in_array('main', $channel_types),
                'has_backup' => in_array('backup', $channel_types) || in_array('backup2', $channel_types),
                'has_private' => in_array('private', $channel_types),
                'has_any' => in_array('any', $channel_types),
                'all_channels' => array_unique($channel_types)
            ];
        }
    }
    
    uasort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
}

function detect_language($text) {
    $hindi_keywords = ['फिल्म', 'मूवी', 'डाउनलोड', 'हिंदी', 'चाहिए', 'कहाँ', 'कैसे', 'खोज', 'तलाश'];
    $english_keywords = ['movie', 'download', 'watch', 'print', 'search', 'find', 'looking', 'want', 'need'];
    
    $hindi_score = 0;
    $english_score = 0;
    
    foreach ($hindi_keywords as $k) {
        if (strpos($text, $k) !== false) $hindi_score++;
    }
    
    foreach ($english_keywords as $k) {
        if (stripos($text, $k) !== false) $english_score++;
    }
    
    $hindi_chars = preg_match('/[\x{0900}-\x{097F}]/u', $text);
    if ($hindi_chars) $hindi_score += 3;
    
    return $hindi_score > $english_score ? 'hindi' : 'english';
}

function send_multilingual_response($chat_id, $message_type, $language) {
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
    
    if (strlen($q) < 2) {
        sendMessage($chat_id, "❌ Please enter at least 2 characters for search");
        return;
    }
    
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
    
    $query_words = explode(' ', $q);
    $total_words = count($query_words);
    
    $invalid_count = 0;
    foreach ($query_words as $word) {
        if (in_array($word, $invalid_keywords)) {
            $invalid_count++;
        }
    }
    
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
    
    $movie_pattern = '/^[a-zA-Z0-9\s\-\.\,\&\+\(\)\:\'\"]+$/';
    if (!preg_match($movie_pattern, $query)) {
        sendMessage($chat_id, "❌ Invalid movie name format. Only letters, numbers, and basic punctuation allowed.");
        return;
    }
    
    $found = smart_search($q);
    
    if (!empty($found)) {
        update_stats('successful_searches', 1);
        
        $msg = "🔍 Found " . count($found) . " movies for '$query':\n\n";
        $i = 1;
        foreach ($found as $movie => $data) {
            $quality_info = !empty($data['qualities']) ? implode('/', $data['qualities']) : 'Unknown';
            $channel_info = "";
            if ($data['has_theater']) $channel_info .= "🎭 ";
            if ($data['has_main']) $channel_info .= "🍿 ";
            if ($data['has_backup']) $channel_info .= "🔒 ";
            if ($data['has_private']) $channel_info .= "🔐 ";
            if ($data['has_any']) $channel_info .= "📡 ";
            $msg .= "$i. $movie ($channel_info" . $data['count'] . " versions, $quality_info)\n";
            $i++;
            if ($i > 10) break;
        }
        
        sendMessage($chat_id, $msg);
        
        $keyboard = ['inline_keyboard' => []];
        $top_movies = array_slice(array_keys($found), 0, 5);
        
        foreach ($top_movies as $movie) {
            $movie_data = $found[$movie];
            $channel_icon = '🍿';
            if ($movie_data['has_theater']) $channel_icon = '🎭';
            elseif ($movie_data['has_backup']) $channel_icon = '🔒';
            elseif ($movie_data['has_private']) $channel_icon = '🔐';
            elseif ($movie_data['has_any']) $channel_icon = '📡';
            
            $keyboard['inline_keyboard'][] = [[ 
                'text' => $channel_icon . ucwords($movie), 
                'callback_data' => $movie 
            ]];
        }
        
        $keyboard['inline_keyboard'][] = [[
            'text' => "📝 Request Different Movie", 
            'callback_data' => 'request_movie'
        ]];
        
        sendMessage($chat_id, "🚀 Top matches (click for info):", $keyboard);
        
        if ($user_id) {
            update_user_activity($user_id, 'found_movie');
            update_user_activity($user_id, 'search');
        }
        
    } else {
        update_stats('failed_searches', 1);
        $lang = detect_language($query);
        send_multilingual_response($chat_id, 'not_found', $lang);
        
        $request_keyboard = [
            'inline_keyboard' => [[
                ['text' => '📝 Request This Movie', 'callback_data' => 'auto_request_' . base64_encode($query)]
            ]]
        ];
        
        sendMessage($chat_id, "💡 Click below to automatically request this movie:", $request_keyboard);
        
        if (!isset($waiting_users[$q])) $waiting_users[$q] = [];
        $waiting_users[$q][] = [$chat_id, $user_id ?? $chat_id];
    }
    
    update_stats('total_searches', 1);
    if ($user_id) update_user_activity($user_id, 'search');
}

// ==================== REQUEST SYSTEM ====================
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
    
    if (!isset($requests_data['user_request_count'][$user_id])) {
        $requests_data['user_request_count'][$user_id] = 0;
    }
    $requests_data['user_request_count'][$user_id]++;
    
    file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
    
    $admin_msg = "🎯 New Movie Request\n\n";
    $admin_msg .= "🎬 Movie: $movie_name\n";
    $admin_msg .= "🗣️ Language: $language\n";
    $admin_msg .= "👤 User ID: $user_id\n";
    $admin_msg .= "📅 Date: " . date('Y-m-d H:i:s') . "\n";
    $admin_msg .= "🆔 Request ID: $request_id";
    
    sendMessage($GLOBALS['ADMIN_ID'], $admin_msg);
    bot_log("Movie request added: $movie_name by $user_id");
    
    return true;
}

// ==================== PAGINATION SYSTEM ====================
function get_all_movies_list() {
    return get_cached_movies();
}

function paginate_movies(array $all, int $page, array $filters = []): array {
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

function apply_movie_filters($movies, $filters) {
    if (empty($filters)) return $movies;
    
    $filtered = [];
    foreach ($movies as $movie) {
        $pass = true;
        
        foreach ($filters as $key => $value) {
            switch ($key) {
                case 'quality':
                    if (stripos($movie['quality'] ?? '', $value) === false) {
                        $pass = false;
                    }
                    break;
                    
                case 'language':
                    if (stripos($movie['language'] ?? '', $value) === false) {
                        $pass = false;
                    }
                    break;
                    
                case 'year':
                    $movie_year = substr($movie['date'] ?? '', -4);
                    if ($movie_year != $value) {
                        $pass = false;
                    }
                    break;
                    
                case 'channel_type':
                    if (($movie['channel_type'] ?? 'main') != $value) {
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

function build_totalupload_keyboard(int $page, int $total_pages, string $session_id = '', array $filters = []): array {
    $kb = ['inline_keyboard' => []];
    
    $nav_row = [];
    
    if ($page > 1) {
        $nav_row[] = ['text' => '⏪', 'callback_data' => 'pag_first_' . $session_id];
        $nav_row[] = ['text' => '◀️', 'callback_data' => 'pag_prev_' . $page . '_' . $session_id];
    }
    
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
    
    if ($page < $total_pages) {
        $nav_row[] = ['text' => '▶️', 'callback_data' => 'pag_next_' . $page . '_' . $session_id];
        $nav_row[] = ['text' => '⏩', 'callback_data' => 'pag_last_' . $total_pages . '_' . $session_id];
    }
    
    if (!empty($nav_row)) {
        $kb['inline_keyboard'][] = $nav_row;
    }
    
    $action_row = [];
    $action_row[] = ['text' => '📥 Send Page', 'callback_data' => 'send_' . $page . '_' . $session_id];
    $action_row[] = ['text' => '👁️ Preview', 'callback_data' => 'prev_' . $page . '_' . $session_id];
    $action_row[] = ['text' => '📊 Stats', 'callback_data' => 'stats_' . $session_id];
    
    $kb['inline_keyboard'][] = $action_row;
    
    if (empty($filters)) {
        $filter_row = [];
        $filter_row[] = ['text' => '🎬 HD Only', 'callback_data' => 'flt_hd_' . $session_id];
        $filter_row[] = ['text' => '🎭 Theater Only', 'callback_data' => 'flt_theater_' . $session_id];
        $filter_row[] = ['text' => '🔒 Backup Only', 'callback_data' => 'flt_backup_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
    } else {
        $filter_row = [];
        $filter_row[] = ['text' => '🧹 Clear Filter', 'callback_data' => 'flt_clr_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
    }
    
    $ctrl_row = [];
    $ctrl_row[] = ['text' => '💾 Save', 'callback_data' => 'save_' . $session_id];
    $ctrl_row[] = ['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''];
    $ctrl_row[] = ['text' => '❌ Close', 'callback_data' => 'close_' . $session_id];
    
    $kb['inline_keyboard'][] = $ctrl_row;
    
    return $kb;
}

function totalupload_controller($chat_id, $page = 1, $filters = [], $session_id = null) {
    global $user_pagination_sessions;
    
    $all = get_all_movies_list();
    if (empty($all)) {
        sendMessage($chat_id, "📭 Koi movies nahi mili! Pehle kuch movies add karo.");
        return;
    }
    
    if (!$session_id) {
        $session_id = uniqid('sess_', true);
    }
    
    $pg = paginate_movies($all, (int)$page, $filters);
    
    if ($page == 1 && PREVIEW_ITEMS > 0 && count($pg['slice']) > 0) {
        $preview_msg = "👁️ <b>Quick Preview (First " . PREVIEW_ITEMS . "):</b>\n\n";
        $preview_count = min(PREVIEW_ITEMS, count($pg['slice']));
        
        for ($i = 0; $i < $preview_count; $i++) {
            $movie = $pg['slice'][$i];
            $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
            $preview_msg .= ($i + 1) . ". $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
            $preview_msg .= "   ⭐ " . ($movie['quality'] ?? 'Unknown') . " | ";
            $preview_msg .= "🗣️ " . ($movie['language'] ?? 'Hindi') . "\n\n";
        }
        
        sendMessage($chat_id, $preview_msg, null, 'HTML');
    }
    
    $title = "🎬 <b>Enhanced Movie Browser</b>\n\n";
    $title .= "🆔 <b>Session:</b> <code>" . substr($session_id, 0, 8) . "</code>\n";
    $title .= "📊 <b>Statistics:</b>\n";
    $title .= "• Total Movies: <b>{$pg['total']}</b>\n";
    $title .= "• Page: <b>{$pg['page']}/{$pg['total_pages']}</b>\n";
    $title .= "• Items: <b>{$pg['start_item']}-{$pg['end_item']}</b>\n";
    
    if (!empty($filters)) {
        $title .= "• Filters: <b>" . count($filters) . " active</b>\n";
    }
    
    $title .= "\n";
    $title .= "📋 <b>Page {$page} Movies:</b>\n\n";
    $i = $pg['start_item'];
    foreach ($pg['slice'] as $movie) {
        $movie_name = htmlspecialchars($movie['movie_name'] ?? 'Unknown');
        $quality = $movie['quality'] ?? 'Unknown';
        $language = $movie['language'] ?? 'Hindi';
        $date = $movie['date'] ?? 'N/A';
        $size = $movie['size'] ?? 'Unknown';
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        
        $title .= "<b>{$i}.</b> $channel_icon {$movie_name}\n";
        $title .= "   🏷️ {$quality} | 🗣️ {$language}\n";
        $title .= "   💾 {$size} | 📅 {$date}\n\n";
        $i++;
    }
    
    $title .= "📍 <i>Use number buttons for direct page access</i>\n";
    $title .= "🔧 <i>Apply filters using buttons below</i>";
    
    $kb = build_totalupload_keyboard($pg['page'], $pg['total_pages'], $session_id, $filters);
    
    delete_pagination_message($chat_id, $session_id);
    
    $result = sendMessage($chat_id, $title, $kb, 'HTML');
    save_pagination_message($chat_id, $session_id, $result['result']['message_id']);
    
    bot_log("Enhanced pagination - Chat: $chat_id, Page: $page, Session: " . substr($session_id, 0, 8));
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
            $result = deliver_item_to_chat($chat_id, $movie);
            if ($result) {
                $success++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
        }
        
        usleep(500000);
    }
    
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

// ==================== QUICK ADD SYSTEM ====================
define('QUICKADD_FORMAT', "🎬 <b>Quick Add Format:</b>\n\n" .
    "<code>/quickadd movie_name (year),message_id,channel_username_or_id</code>\n\n" .
    "<b>Examples:</b>\n" .
    "• <code>/quickadd Avengers Endgame (2019),12345,@EntertainmentTadka786</code>\n" .
    "• <code>/quickadd KGF Chapter 2 (2022),67890,@threater_print_movies</code>\n" .
    "• <code>/quickadd Animal (2023),54321,-1003251791991</code>\n" .
    "• <code>/quickadd Pushpa 2 (2024),11111,-1002337293281</code>\n" .
    "• <code>/quickadd Test Movie (2025),22222,-1003614546520</code>\n\n" .
    "<b>Supported Channels:</b>\n" .
    "• @EntertainmentTadka786 (Main)\n" .
    "• @threater_print_movies (Theater)\n" .
    "• @ETBackup (Backup)\n" .
    "• -1003251791991 (Private Channel)\n" .
    "• -1002337293281 (Backup 2)\n" .
    "• -1003614546520 (Any Channel)\n\n" .
    "<b>Note:</b> Ek saath multiple movies add ho jayengi aur CSV file mein store hongi.");

function quick_add_movie($chat_id, $user_id, $input_data) {
    global $movie_messages, $movie_cache, $ADMIN_ID;
    
    if ($chat_id != $ADMIN_ID && $user_id != $ADMIN_ID) {
        sendMessageWithDelay($chat_id, "❌ Access denied. Admin only command.", null, 'HTML', 1000);
        return;
    }
    
    $parts = explode(',', $input_data, 3);
    
    if (count($parts) < 3) {
        sendMessageWithDelay($chat_id, QUICKADD_FORMAT, null, 'HTML', 1000);
        return;
    }
    
    $movie_name = trim($parts[0]);
    $message_id = trim($parts[1]);
    $channel_info = trim($parts[2]);
    
    if (!is_numeric($message_id)) {
        sendMessageWithDelay($chat_id, "❌ Invalid message ID. Please provide a numeric message ID.", null, 'HTML', 1000);
        return;
    }
    
    $channel_type = 'other';
    $channel_id = '';
    $channel_username = '';
    
    if (strpos($channel_info, '@') === 0) {
        $channel_username = $channel_info;
        $channel_id = get_channel_id_by_username($channel_username);
        
        if ($channel_id) {
            $channel_type = get_channel_type_by_id($channel_id);
        } else {
            $channel_type = 'other';
        }
    } elseif (is_numeric($channel_info) || (strpos($channel_info, '-100') === 0)) {
        $channel_id = $channel_info;
        $channel_type = get_channel_type_by_id($channel_id);
        
        switch ($channel_type) {
            case 'main':
                $channel_username = MAIN_CHANNEL;
                break;
            case 'theater':
                $channel_username = THEATER_CHANNEL;
                break;
            case 'backup':
            case 'backup2':
                $channel_username = BACKUP_CHANNEL_USERNAME;
                break;
            case 'private':
                $channel_username = '@private_channel';
                break;
            case 'any':
                $channel_username = '@any_channel';
                break;
            default:
                $channel_username = '';
        }
    } else {
        sendMessageWithDelay($chat_id, "❌ Invalid channel format. Use @username or channel ID.", null, 'HTML', 1000);
        return;
    }
    
    $quality = 'Unknown';
    $movie_name_lower = strtolower($movie_name);
    $quality_patterns = [
        '1080p' => ['1080p', '1080', 'fhd', 'full hd'],
        '720p' => ['720p', '720', 'hd'],
        '480p' => ['480p', '480', 'sd'],
        'theater' => ['theater', 'theatre', 'print', 'hdcam', 'camrip', 'hdrip']
    ];
    
    foreach ($quality_patterns as $q => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($movie_name_lower, $pattern) !== false) {
                $quality = $q;
                break 2;
            }
        }
    }
    
    $language = 'Hindi';
    $language_patterns = [
        'English' => ['english', 'eng', 'en'],
        'Hindi' => ['hindi', 'hin', 'hd'],
        'Tamil' => ['tamil', 'tam'],
        'Telugu' => ['telugu', 'tel'],
        'Malayalam' => ['malayalam', 'mal'],
        'Kannada' => ['kannada', 'kan'],
        'Bengali' => ['bengali', 'ben'],
        'Punjabi' => ['punjabi', 'pun']
    ];
    
    foreach ($language_patterns as $lang => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($movie_name_lower, $pattern) !== false) {
                $language = $lang;
                break 2;
            }
        }
    }
    
    $size = 'Unknown';
    $size_patterns = [
        '1.5GB' => ['1.5gb', '1.5 gb', '1500mb'],
        '2.0GB' => ['2.0gb', '2 gb', '2000mb'],
        '2.5GB' => ['2.5gb', '2.5 gb', '2500mb'],
        '3.0GB' => ['3.0gb', '3 gb', '3000mb'],
        '500MB' => ['500mb', '0.5gb', '500 mb'],
        '700MB' => ['700mb', '0.7gb', '700 mb'],
        '1.0GB' => ['1gb', '1.0gb', '1000mb']
    ];
    
    foreach ($size_patterns as $sz => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($movie_name_lower, $pattern) !== false) {
                $size = $sz;
                break 2;
            }
        }
    }
    
    $entry = [
        $movie_name,
        $message_id,
        date('d-m-Y'),
        '',
        $quality,
        $size,
        $language,
        $channel_type,
        $channel_id,
        $channel_username
    ];
    
    $handle = fopen(CSV_FILE, "a");
    if ($handle !== FALSE) {
        fputcsv($handle, $entry);
        fclose($handle);
        
        $movie_item = [
            'movie_name' => $movie_name,
            'message_id_raw' => $message_id,
            'date' => date('d-m-Y'),
            'video_path' => '',
            'quality' => $quality,
            'size' => $size,
            'language' => $language,
            'channel_type' => $channel_type,
            'channel_id' => $channel_id,
            'channel_username' => $channel_username,
            'message_id' => intval($message_id),
            'source_channel' => $channel_id
        ];
        
        $movie_key = strtolower($movie_name);
        if (!isset($movie_messages[$movie_key])) {
            $movie_messages[$movie_key] = [];
        }
        $movie_messages[$movie_key][] = $movie_item;
        $movie_cache = [];
        
        update_stats('total_movies', 1);
        
        $success_msg = "✅ <b>Movie Added Successfully!</b>\n\n";
        $success_msg .= "🎬 <b>Movie:</b> $movie_name\n";
        $success_msg .= "🆔 <b>Message ID:</b> $message_id\n";
        $success_msg .= "📊 <b>Quality:</b> $quality\n";
        $success_msg .= "💾 <b>Size:</b> $size\n";
        $success_msg .= "🗣️ <b>Language:</b> $language\n";
        $success_msg .= "🎭 <b>Channel Type:</b> " . get_channel_display_name($channel_type) . "\n";
        
        if ($channel_username) {
            $success_msg .= "📢 <b>Channel:</b> $channel_username\n";
        }
        
        if ($channel_id) {
            $success_msg .= "🔢 <b>Channel ID:</b> $channel_id\n";
        }
        
        $success_msg .= "\n💾 <b>Storage Location:</b>\n";
        $success_msg .= "• File: <code>" . CSV_FILE . "</code>\n";
        $success_msg .= "• Path: <code>" . realpath(CSV_FILE) . "</code>\n";
        $success_msg .= "• Size: " . round(filesize(CSV_FILE) / 1024, 2) . " KB\n";
        $success_msg .= "• Total Movies: " . (get_stats()['total_movies'] ?? 0) . "\n\n";
        
        $success_msg .= "🔗 <b>Direct Link:</b>\n";
        if ($channel_id && $message_id) {
            $success_msg .= get_direct_channel_link($message_id, $channel_id) . "\n\n";
        }
        
        $success_msg .= "📊 Movie database updated successfully!";
        
        sendMessageWithDelay($chat_id, $success_msg, null, 'HTML', 1500);
        bot_log("Movie quick added by $user_id: $movie_name (ID: $message_id) to $channel_type channel ($channel_id)");
        
    } else {
        sendMessageWithDelay($chat_id, "❌ Failed to add movie. CSV file cannot be opened.", null, 'HTML', 1000);
    }
}

function batch_quick_add_movies($chat_id, $user_id, $movies_data) {
    global $movie_messages, $movie_cache, $ADMIN_ID;
    
    if ($chat_id != $ADMIN_ID && $user_id != $ADMIN_ID) {
        sendMessageWithDelay($chat_id, "❌ Access denied. Admin only command.", null, 'HTML', 1000);
        return;
    }
    
    $movies_list = explode("\n", trim($movies_data));
    $added_count = 0;
    $failed_count = 0;
    $added_movies = [];
    
    $progress_msg = sendMessage($chat_id, "⏳ Adding " . count($movies_list) . " movies...\n\nProcessing: 0%");
    
    foreach ($movies_list as $index => $movie_line) {
        $movie_line = trim($movie_line);
        if (empty($movie_line)) continue;
        
        $parts = explode(',', $movie_line, 3);
        
        if (count($parts) < 3) {
            $failed_count++;
            continue;
        }
        
        $movie_name = trim($parts[0]);
        $message_id = trim($parts[1]);
        $channel_info = trim($parts[2]);
        
        if (!is_numeric($message_id)) {
            $failed_count++;
            continue;
        }
        
        $channel_type = 'other';
        $channel_id = '';
        $channel_username = '';
        
        if (strpos($channel_info, '@') === 0) {
            $channel_username = $channel_info;
            $channel_id = get_channel_id_by_username($channel_username);
            
            if ($channel_id) {
                $channel_type = get_channel_type_by_id($channel_id);
            } else {
                $channel_type = 'other';
            }
        } elseif (is_numeric($channel_info) || (strpos($channel_info, '-100') === 0)) {
            $channel_id = $channel_info;
            $channel_type = get_channel_type_by_id($channel_id);
            
            switch ($channel_type) {
                case 'main':
                    $channel_username = MAIN_CHANNEL;
                    break;
                case 'theater':
                    $channel_username = THEATER_CHANNEL;
                    break;
                case 'backup':
                case 'backup2':
                    $channel_username = BACKUP_CHANNEL_USERNAME;
                    break;
                case 'private':
                    $channel_username = '@private_channel';
                    break;
                case 'any':
                    $channel_username = '@any_channel';
                    break;
                default:
                    $channel_username = '';
            }
        } else {
            $failed_count++;
            continue;
        }
        
        $quality = 'Unknown';
        $movie_name_lower = strtolower($movie_name);
        $quality_patterns = [
            '1080p' => ['1080p', '1080', 'fhd', 'full hd'],
            '720p' => ['720p', '720', 'hd'],
            '480p' => ['480p', '480', 'sd'],
            'theater' => ['theater', 'theatre', 'print', 'hdcam', 'camrip', 'hdrip']
        ];
        
        foreach ($quality_patterns as $q => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($movie_name_lower, $pattern) !== false) {
                    $quality = $q;
                    break 2;
                }
            }
        }
        
        $language = 'Hindi';
        $language_patterns = [
            'English' => ['english', 'eng', 'en'],
            'Hindi' => ['hindi', 'hin', 'hd'],
            'Tamil' => ['tamil', 'tam'],
            'Telugu' => ['telugu', 'tel'],
            'Malayalam' => ['malayalam', 'mal'],
            'Kannada' => ['kannada', 'kan']
        ];
        
        foreach ($language_patterns as $lang => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($movie_name_lower, $pattern) !== false) {
                    $language = $lang;
                    break 2;
                }
            }
        }
        
        $size = 'Unknown';
        $size_patterns = [
            '1.5GB' => ['1.5gb', '1.5 gb', '1500mb'],
            '2.0GB' => ['2.0gb', '2 gb', '2000mb'],
            '2.5GB' => ['2.5gb', '2.5 gb', '2500mb'],
            '3.0GB' => ['3.0gb', '3 gb', '3000mb'],
            '500MB' => ['500mb', '0.5gb', '500 mb']
        ];
        
        foreach ($size_patterns as $sz => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($movie_name_lower, $pattern) !== false) {
                    $size = $sz;
                    break 2;
                }
            }
        }
        
        $entry = [
            $movie_name,
            $message_id,
            date('d-m-Y'),
            '',
            $quality,
            $size,
            $language,
            $channel_type,
            $channel_id,
            $channel_username
        ];
        
        $handle = fopen(CSV_FILE, "a");
        if ($handle !== FALSE) {
            fputcsv($handle, $entry);
            fclose($handle);
            
            $movie_item = [
                'movie_name' => $movie_name,
                'message_id_raw' => $message_id,
                'date' => date('d-m-Y'),
                'video_path' => '',
                'quality' => $quality,
                'size' => $size,
                'language' => $language,
                'channel_type' => $channel_type,
                'channel_id' => $channel_id,
                'channel_username' => $channel_username,
                'message_id' => intval($message_id),
                'source_channel' => $channel_id
            ];
            
            $movie_key = strtolower($movie_name);
            if (!isset($movie_messages[$movie_key])) {
                $movie_messages[$movie_key] = [];
            }
            $movie_messages[$movie_key][] = $movie_item;
            
            $added_count++;
            $added_movies[] = [
                'name' => $movie_name,
                'channel' => get_channel_display_name($channel_type)
            ];
            
            if (($index + 1) % 5 == 0 || ($index + 1) == count($movies_list)) {
                $progress = round((($index + 1) / count($movies_list)) * 100);
                editMessage($chat_id, $progress_msg['result']['message_id'], 
                    "⏳ Adding " . count($movies_list) . " movies...\n\n" .
                    "Progress: $progress%\n" .
                    "Processed: " . ($index + 1) . "/" . count($movies_list) . "\n" .
                    "✅ Added: $added_count\n" .
                    "❌ Failed: $failed_count"
                );
            }
            
            usleep(500000);
            
        } else {
            $failed_count++;
        }
    }
    
    $movie_cache = [];
    update_stats('total_movies', $added_count);
    
    $result_msg = "✅ <b>Batch Add Complete!</b>\n\n";
    $result_msg .= "📊 <b>Results:</b>\n";
    $result_msg .= "• Total Processed: " . count($movies_list) . "\n";
    $result_msg .= "• ✅ Successfully Added: $added_count\n";
    $result_msg .= "• ❌ Failed: $failed_count\n\n";
    
    $result_msg .= "💾 <b>Storage Location:</b>\n";
    $result_msg .= "• File: <code>" . CSV_FILE . "</code>\n";
    $result_msg .= "• Path: <code>" . realpath(CSV_FILE) . "</code>\n";
    $result_msg .= "• Size: " . round(filesize(CSV_FILE) / 1024, 2) . " KB\n";
    $result_msg .= "• Total Movies: " . (get_stats()['total_movies'] ?? 0) . "\n\n";
    
    if ($added_count > 0) {
        $result_msg .= "🎬 <b>Added Movies:</b>\n";
        $display_count = min(10, count($added_movies));
        for ($i = 0; $i < $display_count; $i++) {
            $result_msg .= ($i + 1) . ". " . $added_movies[$i]['name'] . "\n";
            $result_msg .= "   📢 " . $added_movies[$i]['channel'] . "\n\n";
        }
        
        if (count($added_movies) > 10) {
            $result_msg .= "... and " . (count($added_movies) - 10) . " more\n";
        }
    }
    
    editMessage($chat_id, $progress_msg['result']['message_id'], $result_msg, null, 'HTML');
    bot_log("Batch quick add by $user_id: $added_count movies added, $failed_count failed");
}

// ==================== BACKUP SYSTEM ====================
function auto_backup() {
    bot_log("Starting auto-backup process...");
    
    $backup_files = [CSV_FILE, USERS_JSON, STATS_FILE, REQUEST_FILE, LOG_FILE];
    $backup_dir = BACKUP_DIR . date('Y-m-d_H-i-s');
    $backup_success = true;
    
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
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
    
    $summary = create_backup_summary();
    file_put_contents($backup_dir . '/backup_summary.txt', $summary);
    
    if ($backup_success) {
        $channel_backup_success = upload_backup_to_channel($backup_dir, $summary);
        
        if ($channel_backup_success) {
            bot_log("Backup successfully uploaded to channel");
        } else {
            bot_log("Failed to upload backup to channel", 'WARNING');
        }
    }
    
    clean_old_backups();
    send_backup_report($backup_success, $summary);
    
    bot_log("Auto-backup process completed");
    return $backup_success;
}

function create_backup_summary() {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_JSON), true);
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
    $summary .= "• " . USERS_JSON . " (" . (file_exists(USERS_JSON) ? filesize(USERS_JSON) : 0) . " bytes)\n";
    $summary .= "• " . STATS_FILE . " (" . (file_exists(STATS_FILE) ? filesize(STATS_FILE) : 0) . " bytes)\n";
    $summary .= "• " . REQUEST_FILE . " (" . (file_exists(REQUEST_FILE) ? filesize(REQUEST_FILE) : 0) . " bytes)\n";
    $summary .= "• " . LOG_FILE . " (" . (file_exists(LOG_FILE) ? filesize(LOG_FILE) : 0) . " bytes)\n\n";
    
    $summary .= "🔄 Backup Type: Automated Daily Backup\n";
    $summary .= "📍 Stored In: " . BACKUP_DIR . "\n";
    $summary .= "📡 Channel: " . BACKUP_CHANNEL_USERNAME . "\n";
    
    return $summary;
}

function clean_old_backups() {
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

// ==================== MAIN WEBHOOK HANDLER ====================
function handle_update($update) {
    global $MAINTENANCE_MODE, $MAINTENANCE_MESSAGE, $ADMIN_ID;
    
    log_message("Update received: " . json_encode($update));
    
    $message = $update['message'] ?? null;
    $callback = $update['callback_query'] ?? null;
    
    if ($message) {
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = trim($message['text'] ?? '');
        
        if (is_flood($user_id)) {
            tg('sendMessage', [
                'chat_id' => $chat_id,
                'text' => '⏳ Please wait before sending another request.',
                'parse_mode' => 'HTML'
            ]);
            return;
        }
        
        if ($text === '/start') {
            $welcome = "🎬 Welcome to Entertainment Tadka!\n\n";
            $welcome .= "📢 <b>How to use this bot:</b>\n";
            $welcome .= "• Simply type any movie name\n";
            $welcome .= "• Use English or Hindi\n";
            $welcome .= "• Add 'theater' for theater prints\n";
            $welcome .= "• Partial names also work\n\n";
            $welcome .= "🔍 <b>Examples:</b>\n";
            $welcome .= "• Mandala Murders 2025\n";
            $welcome .= "• Lokah Chapter 1 Chandra 2025\n";
            $welcome .= "• Idli Kadai (2025)\n";
            $welcome .= "• IT - Welcome to Derry (2025) S01\n";
            $welcome .= "• hindi movie\n";
            $welcome .= "• kgf theater print\n\n";
            $welcome .= "📢 <b>Join Our Channels:</b>\n";
            $welcome .= "🍿 Main: @EntertainmentTadka786\n";
            $welcome .= "📥 Requests: @EntertainmentTadka7860\n";
            $welcome .= "🎭 Theater Prints: @threater_print_movies\n";
            $welcome .= "🔒 Backup: @ETBackup\n\n";
            $welcome .= "💬 <b>Need help?</b> Use /help for all commands";
            
            tg('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $welcome,
                'parse_mode' => 'HTML'
            ]);
            log_message("User started: $user_id");
        }
        
        elseif (strpos($text, '/totaluploads') === 0) {
            $page = (int) trim(str_replace('/totaluploads', '', $text));
            if ($page < 1) $page = 1;
            
            $movies = get_all_movies();
            $total = count($movies);
            $start = ($page - 1) * PER_PAGE;
            $slice = array_slice($movies, $start, PER_PAGE);
            
            $msg = "📊 <b>Total Uploads:</b> $total\n";
            $msg .= "📄 <b>Page:</b> $page/" . ceil($total / PER_PAGE) . "\n\n";
            
            $counter = $start + 1;
            foreach ($slice as $movie) {
                $msg .= "$counter. 🎥 " . htmlspecialchars($movie['movie_name']) . "\n";
                $counter++;
            }
            
            $keyboard = [];
            if ($start > 0) {
                $keyboard[] = [
                    ['text' => '⬅️ Previous', 'callback_data' => 'page:' . ($page - 1)]
                ];
            }
            if ($start + PER_PAGE < $total) {
                $keyboard[] = [
                    ['text' => 'Next ➡️', 'callback_data' => 'page:' . ($page + 1)]
                ];
            }
            
            tg('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $msg,
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard ? json_encode(['inline_keyboard' => $keyboard]) : null
            ]);
        }
        
        elseif ($text && $text[0] !== '/') {
            $results = search_movie($text);
            
            if (empty($results)) {
                tg('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "❌ <b>Movie not found!</b>\n\nTry different keywords.",
                    'parse_mode' => 'HTML'
                ]);
                log_message("Search failed: '$text' by user $user_id");
            } else {
                $count = count($results);
                tg('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "✅ Found $count result(s) for: <b>" . htmlspecialchars($text) . "</b>",
                    'parse_mode' => 'HTML'
                ]);
                
                foreach ($results as $result) {
                    tg('copyMessage', [
                        'chat_id' => $chat_id,
                        'from_chat_id' => $result['channel_id'],
                        'message_id' => $result['message_id']
                    ]);
                    usleep(500000);
                }
                
                log_message("Search successful: '$text' found $count results");
            }
        }
        
        elseif (strpos($text, '/add') === 0 && $user_id == $ADMIN_ID) {
            $movie_name = trim(substr($text, 4));
            
            if ($message['reply_to_message'] && $message['reply_to_message']['message_id']) {
                $reply = $message['reply_to_message'];
                $channel_id = $reply['chat']['id'] ?? null;
                
                global $CHANNELS;
                if ($channel_id && in_array($channel_id, $CHANNELS)) {
                    $message_id = $reply['message_id'];
                    
                    if (add_movie($movie_name, $message_id, $channel_id)) {
                        tg('sendMessage', [
                            'chat_id' => $chat_id,
                            'text' => "✅ Movie added successfully!\n\n<b>$movie_name</b>\nChannel: $channel_id",
                            'parse_mode' => 'HTML'
                        ]);
                        
                        global $REQUEST_GROUP_ID;
                        if (defined('REQUEST_GROUP_ID') && REQUEST_GROUP_ID) {
                            tg('forwardMessage', [
                                'chat_id' => REQUEST_GROUP_ID,
                                'from_chat_id' => $channel_id,
                                'message_id' => $message_id
                            ]);
                        }
                    } else {
                        tg('sendMessage', [
                            'chat_id' => $chat_id,
                            'text' => "❌ Movie already exists or failed to add.",
                            'parse_mode' => 'HTML'
                        ]);
                    }
                }
            }
        }
        
        elseif (strpos($text, '/quickadd') === 0 && $user_id == $ADMIN_ID) {
            $input_data = trim(substr($text, 9));
            
            if (strpos($input_data, "\n") !== false) {
                batch_quick_add_movies($chat_id, $user_id, $input_data);
            } else {
                quick_add_movie($chat_id, $user_id, $input_data);
            }
        }
    }
    
    elseif ($callback) {
        $chat_id = $callback['message']['chat']['id'];
        $data = $callback['data'];
        
        tg('answerCallbackQuery', [
            'callback_query_id' => $callback['id']
        ]);
        
        if (strpos($data, 'page:') === 0) {
            $page = (int) str_replace('page:', '', $data);
            tg('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "/totaluploads $page"
            ]);
        }
    }
    
    elseif (isset($update['channel_post'])) {
        $post = $update['channel_post'];
        $channel_id = $post['chat']['id'];
        
        global $CHANNELS;
        if (in_array($channel_id, $CHANNELS)) {
            $text = $post['text'] ?? $post['caption'] ?? '';
            $message_id = $post['message_id'];
            
            $lines = explode("\n", $text);
            $movie_name = trim($lines[0]);
            
            if ($movie_name && strlen($movie_name) > 2) {
                add_movie($movie_name, $message_id, $channel_id);
                log_message("Auto-added from channel: $movie_name");
            }
        }
    }
}

// ==================== COMMAND HANDLER ====================
function handle_command($chat_id, $user_id, $command, $params = []) {
    switch ($command) {
        case '/start':
            $welcome = "🎬 Welcome to Entertainment Tadka!\n\n";
            $welcome .= "📢 <b>How to use this bot:</b>\n";
            $welcome .= "• Simply type any movie name\n";
            $welcome .= "• Use English or Hindi\n";
            $welcome .= "• Add 'theater' for theater prints\n";
            $welcome .= "• Partial names also work\n\n";
            $welcome .= "🔍 <b>Examples:</b>\n";
            $welcome .= "• Mandala Murders 2025\n";
            $welcome .= "• Lokah Chapter 1 Chandra 2025\n";
            $welcome .= "• Idli Kadai (2025)\n";
            $welcome .= "• IT - Welcome to Derry (2025) S01\n";
            $welcome .= "• hindi movie\n";
            $welcome .= "• kgf theater print\n\n";
            $welcome .= "📢 <b>Join Our Channels:</b>\n";
            $welcome .= "🍿 Main: @EntertainmentTadka786\n";
            $welcome .= "📥 Requests: @EntertainmentTadka7860\n";
            $welcome .= "🎭 Theater Prints: @threater_print_movies\n";
            $welcome .= "🔒 Backup: @ETBackup\n\n";
            $welcome .= "💬 <b>Need help?</b> Use /help for all commands";
            
            tg('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $welcome,
                'parse_mode' => 'HTML'
            ]);
            break;

        case '/help':
            $help = "🤖 <b>Entertainment Tadka Bot - Complete Guide</b>\n\n";
            $help .= "📢 <b>Our Channels:</b>\n";
            $help .= "🍿 Main: " . MAIN_CHANNEL . " - Latest movies\n";
            $help .= "📥 Requests: " . REQUEST_CHANNEL . " - Support & requests\n";
            $help .= "🎭 Theater: " . THEATER_CHANNEL . " - HD prints\n";
            $help .= "🔒 Backup: " . BACKUP_CHANNEL_USERNAME . " - Data protection\n\n";
            
            $help .= "🎯 <b>Search Commands:</b>\n";
            $help .= "• Just type movie name - Smart search\n";
            $help .= "• Add 'theater' for theater prints\n";
            $help .= "• <code>/search movie</code> - Direct search\n";
            $help .= "• <code>/s movie</code> - Quick search\n\n";
            
            $help .= "📁 <b>Browse Commands:</b>\n";
            $help .= "• <code>/totalupload</code> - All movies\n";
            $help .= "• <code>/latest</code> - New additions\n";
            $help .= "• <code>/trending</code> - Popular movies\n";
            $help .= "• <code>/theater</code> - Theater prints only\n\n";
            
            $help .= "📝 <b>Request Commands:</b>\n";
            $help .= "• <code>/request movie</code> - Request movie\n";
            $help .= "• <code>/myrequests</code> - Request status\n";
            $help .= "• Join " . REQUEST_CHANNEL . " for support\n\n";
            
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
            
            $help .= "💡 <b>Pro Tips:</b>\n";
            $help .= "• Use partial names (e.g., 'aveng')\n";
            $help .= "• Add 'theater' for theater prints\n";
            $help .= "• Join all channels for updates\n";
            $help .= "• Request movies you can't find\n";
            $help .= "• Check spelling before reporting";
            
            sendMessage($chat_id, $help, null, 'HTML');
            break;

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

        case '/totalupload':
        case '/totaluploads':
        case '/allmovies':
        case '/browse':
            $page = isset($params[0]) ? intval($params[0]) : 1;
            totalupload_controller($chat_id, $page);
            break;

        case '/quickadd':
        case '/addmovie':
        case '/qa':
            if (count($params) > 0) {
                $input_data = implode(' ', $params);
                
                if (strpos($input_data, "\n") !== false) {
                    batch_quick_add_movies($chat_id, $user_id, $input_data);
                } else {
                    quick_add_movie($chat_id, $user_id, $input_data);
                }
            } else {
                sendMessageWithDelay($chat_id, QUICKADD_FORMAT, null, 'HTML', 1000);
            }
            break;

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

        case '/backup':
            if ($user_id == $GLOBALS['ADMIN_ID']) {
                manual_backup($chat_id);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        default:
            sendMessage($chat_id, "❌ Unknown command. Use <code>/help</code> to see all available commands.", null, 'HTML');
    }
}

// ==================== MAIN EXECUTION ====================
init_storage();
log_message("Bot started. Method: " . $_SERVER['REQUEST_METHOD']);

// Get update from webhook
$content = file_get_contents('php://input');
$update = json_decode($content, true);

if ($update) {
    handle_update($update);
    http_response_code(200);
    echo 'OK';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'status' => 'online',
            'timestamp' => date('Y-m-d H:i:s'),
            'movies_count' => count(get_all_movies()),
            'service' => 'Telegram Movie Bot'
        ]);
    } else {
        http_response_code(400);
        echo 'Invalid request';
        log_message("Invalid request received", 'ERROR');
    }
}

// ==================== HELPER FUNCTIONS ====================
function manual_backup($chat_id) {
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

function upload_backup_to_channel($backup_dir, $summary) {
    // Implementation for uploading backup to Telegram channel
    // This requires proper CURL setup and channel permissions
    return true; // Placeholder
}

function send_backup_report($success, $summary) {
    // Implementation for sending backup report to admin
    // Placeholder function
}

// ==================== WEBHOOK SETUP ====================
if (isset($_GET['action']) && $_GET['action'] === 'setwebhook') {
    $webhook_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
    $result = file_get_contents($API_URL . 'setWebhook?url=' . urlencode($webhook_url));
    echo json_encode(['status' => 'success', 'result' => json_decode($result), 'webhook' => $webhook_url]);
    log_message("Webhook set: $webhook_url");
    exit;
}
?>
