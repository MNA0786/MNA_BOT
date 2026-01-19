<?php
// ==============================
// ENABLE ERROR REPORTING
// ==============================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==============================
// BOT & TELEGRAM CONFIGURATION
// ==============================
define('BOT_TOKEN', '8315381064:AAGk0FGVGmB8j5SjpBvW3rD3_kQHe_hyOWU');
define('BOT_USERNAME', '@EntertainmentTadkaBot');
define('BOT_ID', '8315381064');
define('OWNER_ID', '1080317415');
define('DEVELOPER', '@EntertainmentTadkaBot');
define('API_ID', '21944581');
define('API_HASH', '7b1c174a5cd3466e25a976c39a791737');

// ==============================
// CHANNELS CONFIGURATION
// ==============================
define('MAIN_CHANNEL', '@EntertainmentTadka786');
define('MAIN_CHANNEL_ID', '-1003181705395');
define('MAIN_CHANNEL_TITLE', 'Movies and Webseries');

define('THEATER_PRINTS_CHANNEL', '@threater_print_movies');
define('THEATER_PRINTS_ID', '-1002831605258');
define('THEATER_PRINTS_TITLE', 'Threater_Print_Movies');

define('ET_BACKUP_CHANNEL', '@ETBackup');
define('ET_BACKUP_ID', '-1002964109368');
define('ET_BACKUP_TITLE', 'Backup Channel of Movies And Webseries');

define('REQUEST_GROUP', '@EntertainmentTadka7860');
define('REQUEST_GROUP_ID', '-1003083386043');
define('REQUEST_GROUP_TITLE', 'Movies & Webseries 𝐑𝐞𝐪𝐮𝐞𝐬𝐭 𝐆𝐫𝐨𝐮𝐩 🎥 ❤️');

define('PRIVATE_CHANNEL_1', '-1003251791991');
define('PRIVATE_CHANNEL_TITLE', 'Private Channel Of Movies and Webseries');

define('PRIVATE_CHANNEL_2', '-1002337293281');
define('PRIVATE_CHANNEL_2_TITLE', 'Backup Channel of Movies And Webseries 2');

define('FORWARDED_CHANNEL', '-1003614546520');
define('FORWARDED_CHANNEL_TITLE', 'Forwarded From Any Channel');

define('DEFAULT_UPLOAD_CHANNEL', MAIN_CHANNEL);
define('DEFAULT_UPLOAD_CHANNEL_ID', MAIN_CHANNEL_ID);
define('DEFAULT_GROUP_CHANNEL', REQUEST_GROUP);
define('DEFAULT_GROUP_ID', REQUEST_GROUP_ID);

// ==============================
// URL LINKS
// ==============================
define('MAIN_CHANNEL_URL', 'https://t.me/EntertainmentTadka786');
define('THEATER_PRINTS_URL', 'https://t.me/threater_print_movies');
define('ET_BACKUP_URL', 'https://t.me/ETBackup');
define('REQUEST_GROUP_URL', 'https://t.me/EntertainmentTadka7860');
define('BOT_URL', 'https://t.me/EntertainmentTadkaBot');

// ==============================
// FILE CONFIGURATION
// ==============================
define('CSV_FILE', 'movies.csv');
define('USERS_FILE', 'users.json');
define('STATS_FILE', 'bot_stats.json');
define('ADMIN_LOG_FILE', 'admin_log.json');
define('NOTIFICATION_LOG_FILE', 'notification_log.json');
define('USER_PREFERENCES_FILE', 'user_preferences.json');
define('BACKUP_DIR', 'backups/');
define('CACHE_EXPIRY', 300);

// ==============================
// SYSTEM CONFIGURATION
// ==============================
define('MAX_MOVIES_PER_PAGE', 20);
define('MOVIES_PER_QUICKADD', 10);
define('MAX_NOTIFICATIONS_PER_DAY', 2);
define('NOTIFICATION_HOUR', '18');
define('NOTIFICATION_DAY', '1');

// ==============================
// CHANNELS MAPPING
// ==============================
$CHANNEL_INFO = [
    MAIN_CHANNEL => [
        'id' => MAIN_CHANNEL_ID,
        'title' => MAIN_CHANNEL_TITLE,
        'type' => 'main',
        'url' => MAIN_CHANNEL_URL,
        'icon' => '🍿'
    ],
    THEATER_PRINTS_CHANNEL => [
        'id' => THEATER_PRINTS_ID,
        'title' => THEATER_PRINTS_TITLE,
        'type' => 'theater',
        'url' => THEATER_PRINTS_URL,
        'icon' => '🎭'
    ],
    ET_BACKUP_CHANNEL => [
        'id' => ET_BACKUP_ID,
        'title' => ET_BACKUP_TITLE,
        'type' => 'backup',
        'url' => ET_BACKUP_URL,
        'icon' => '🛡️'
    ],
    REQUEST_GROUP => [
        'id' => REQUEST_GROUP_ID,
        'title' => REQUEST_GROUP_TITLE,
        'type' => 'group',
        'url' => REQUEST_GROUP_URL,
        'icon' => '📥'
    ],
    PRIVATE_CHANNEL_1 => [
        'title' => PRIVATE_CHANNEL_TITLE,
        'type' => 'private',
        'icon' => '🔒'
    ],
    PRIVATE_CHANNEL_2 => [
        'title' => PRIVATE_CHANNEL_2_TITLE,
        'type' => 'backup',
        'icon' => '💾'
    ],
    FORWARDED_CHANNEL => [
        'title' => FORWARDED_CHANNEL_TITLE,
        'type' => 'forwarded',
        'icon' => '🔄'
    ]
];

$CHANNEL_CATEGORIES = [
    'movies' => [
        'name' => '🎬 Movies Channels',
        'description' => 'Latest movies in all languages',
        'channels' => [MAIN_CHANNEL, THEATER_PRINTS_CHANNEL, PRIVATE_CHANNEL_1]
    ],
    'webseries' => [
        'name' => '📺 Web Series Channels',
        'description' => 'Complete web series collections',
        'channels' => [MAIN_CHANNEL, PRIVATE_CHANNEL_1]
    ],
    'backup' => [
        'name' => '💾 Backup Channels',
        'description' => 'Backup for all content',
        'channels' => [ET_BACKUP_CHANNEL, PRIVATE_CHANNEL_2]
    ],
    'theater' => [
        'name' => '🎭 Theater Prints',
        'description' => 'High quality theater prints',
        'channels' => [THEATER_PRINTS_CHANNEL]
    ],
    'forwarded' => [
        'name' => '🔄 Forwarded Content',
        'description' => 'Content from various sources',
        'channels' => [FORWARDED_CHANNEL]
    ],
    'requests' => [
        'name' => '📥 Request Channels',
        'description' => 'Request movies and get support',
        'channels' => [REQUEST_GROUP]
    ]
];

$CHANNEL_PRIORITY = [
    MAIN_CHANNEL,
    THEATER_PRINTS_CHANNEL,
    PRIVATE_CHANNEL_1,
    ET_BACKUP_CHANNEL,
    PRIVATE_CHANNEL_2,
    FORWARDED_CHANNEL,
    REQUEST_GROUP
];

// ==============================
// GLOBAL VARIABLES
// ==============================
$movie_messages = array();
$movie_cache = array();
$waiting_users = array();

// ==============================
// FILE SETUP
// ==============================
if (!file_exists(CSV_FILE)) {
    $csv_header = "movie_name,message_id,channel,date,quality,language,added_by,added_at\n";
    file_put_contents(CSV_FILE, $csv_header);
    chmod(CSV_FILE, 0666);
    
    $examples = [
        ['Squid Game 2021 S01', '251', '@EntertainmentTadka786', date('d-m-Y'), '1080p', 'Korean', 'system', date('Y-m-d H:i:s')],
        ['The Raja Saab 2026', '36', '@threater_print_movies', date('d-m-Y'), '720p', 'Hindi', 'system', date('Y-m-d H:i:s')],
        ['Sample Private Movie', '1234', '-1002337293281', date('d-m-Y'), '1080p', 'English', 'system', date('Y-m-d H:i:s')]
    ];
    
    $handle = fopen(CSV_FILE, 'a');
    foreach ($examples as $example) {
        fputcsv($handle, $example);
    }
    fclose($handle);
    error_log("✅ Created CSV file with new format");
}

if (!file_exists(USERS_FILE)) {
    $initial_users = [
        'users' => [],
        'total_requests' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents(USERS_FILE, json_encode($initial_users, JSON_PRETTY_PRINT));
    chmod(USERS_FILE, 0666);
}

if (!file_exists(STATS_FILE)) {
    $initial_stats = [
        'total_movies' => 0,
        'total_users' => 0,
        'total_searches' => 0,
        'total_downloads' => 0,
        'last_updated' => date('Y-m-d H:i:s'),
        'channels' => array_fill_keys($CHANNEL_PRIORITY, 0),
        'created_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents(STATS_FILE, json_encode($initial_stats, JSON_PRETTY_PRINT));
    chmod(STATS_FILE, 0666);
}

if (!file_exists(ADMIN_LOG_FILE)) {
    file_put_contents(ADMIN_LOG_FILE, json_encode([], JSON_PRETTY_PRINT));
    chmod(ADMIN_LOG_FILE, 0666);
}

if (!file_exists(NOTIFICATION_LOG_FILE)) {
    file_put_contents(NOTIFICATION_LOG_FILE, json_encode([], JSON_PRETTY_PRINT));
    chmod(NOTIFICATION_LOG_FILE, 0666);
}

if (!file_exists(USER_PREFERENCES_FILE)) {
    file_put_contents(USER_PREFERENCES_FILE, json_encode([], JSON_PRETTY_PRINT));
    chmod(USER_PREFERENCES_FILE, 0666);
}

if (!file_exists(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0777, true);
    chmod(BACKUP_DIR, 0777);
}

// ==============================
// TELEGRAM API FUNCTIONS
// ==============================
function apiRequest($method, $params = array()) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    $options = array(
        'http' => array(
            'method' => 'POST',
            'content' => http_build_query($params),
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
        )
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return $result ? json_decode($result, true) : false;
}

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = null) {
    if (strlen($text) > 10 && $parse_mode != 'HTML') {
        sendChatAction($chat_id, 'typing');
        usleep(300000);
    }
    
    $data = ['chat_id' => $chat_id, 'text' => $text, 'disable_web_page_preview' => true];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    return apiRequest('sendMessage', $data);
}

function sendChatAction($chat_id, $action = 'typing') {
    return apiRequest('sendChatAction', ['chat_id' => $chat_id, 'action' => $action]);
}

function sendMessageWithDelay($chat_id, $text, $reply_markup = null, $parse_mode = null, $delay_ms = 1000) {
    sendChatAction($chat_id, 'typing');
    $typing_delay = calculate_typing_delay($text, $delay_ms);
    if ($typing_delay > 0) usleep($typing_delay * 1000);
    return sendMessage($chat_id, $text, $reply_markup, $parse_mode);
}

function editMessage($chat_id, $message_id, $text, $reply_markup = null, $parse_mode = null) {
    $data = ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $text, 'disable_web_page_preview' => true];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    return apiRequest('editMessageText', $data);
}

function editMessageWithDelay($chat_id, $message_id, $new_text, $reply_markup = null, $delay_ms = 500) {
    sendChatAction($chat_id, 'typing');
    $delay = min(2000, $delay_ms + (strlen($new_text) * 8));
    if ($delay > 0) usleep($delay * 1000);
    return editMessage($chat_id, $message_id, $new_text, $reply_markup);
}

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    return apiRequest('forwardMessage', ['chat_id' => $chat_id, 'from_chat_id' => $from_chat_id, 'message_id' => $message_id]);
}

function answerCallbackQuery($callback_query_id, $text = null) {
    $data = ['callback_query_id' => $callback_query_id];
    if ($text) $data['text'] = $text;
    return apiRequest('answerCallbackQuery', $data);
}

function calculate_typing_delay($text, $base_delay = 1000) {
    $length = strlen($text);
    if ($length <= 20) return $base_delay;
    elseif ($length <= 100) return $base_delay + 500;
    elseif ($length <= 300) return $base_delay + 1000;
    else return min(4000, $base_delay + ($length * 8));
}

function showTypingProgress($chat_id, $steps, $message_prefix = "Processing") {
    $progress_message = null;
    for ($i = 1; $i <= $steps; $i++) {
        sendChatAction($chat_id, 'typing');
        $progress_text = "{$message_prefix}... [" . str_repeat("█", $i) . str_repeat("░", $steps - $i) . "] {$i}/{$steps}";
        if ($i == 1) {
            $msg = sendMessageWithDelay($chat_id, $progress_text, null, null, 300);
            if ($msg && isset($msg['result']['message_id'])) {
                $progress_message = $msg['result'];
            }
        } elseif ($progress_message) {
            editMessageWithDelay($chat_id, $progress_message['message_id'], $progress_text, null, 200);
        }
        usleep(300000);
    }
    if ($progress_message) {
        editMessageWithDelay($chat_id, $progress_message['message_id'], "✅ {$message_prefix} Complete!", null, 300);
    }
}

// ==============================
// STATS MANAGEMENT
// ==============================
function update_stats($field, $increment = 1) {
    if (!file_exists(STATS_FILE)) return;
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats[$field] = ($stats[$field] ?? 0) + $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}

function get_stats() {
    if (!file_exists(STATS_FILE)) return [];
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    if (!isset($stats['channels'])) {
        $stats['channels'] = array_fill_keys($GLOBALS['CHANNEL_PRIORITY'], 0);
    }
    return $stats;
}

function update_channel_stats($channel_id, $increment = 1) {
    if (!file_exists(STATS_FILE)) return;
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    if (!isset($stats['channels'])) $stats['channels'] = [];
    if (!isset($stats['channels'][$channel_id])) $stats['channels'][$channel_id] = 0;
    $stats['channels'][$channel_id] += $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}

// ==============================
// CSV FUNCTIONS
// ==============================
function get_channel_identifier($channel_input) {
    $channel_input = trim($channel_input);
    if (strpos($channel_input, '@') === 0) return $channel_input;
    if (is_numeric($channel_input) || (strpos($channel_input, '-100') === 0 && is_numeric(substr($channel_input, 1)))) return $channel_input;
    global $CHANNEL_INFO;
    foreach ($CHANNEL_INFO as $identifier => $info) {
        if (strcasecmp($info['title'], $channel_input) === 0) return $identifier;
    }
    return MAIN_CHANNEL;
}

function normalize_channel_identifier($channel) {
    $channel = trim($channel);
    if (preg_match('/^[a-zA-Z0-9_]{5,}$/', $channel) && strpos($channel, '@') !== 0) return '@' . $channel;
    return $channel;
}

function load_and_clean_csv() {
    global $movie_messages;
    if (!file_exists(CSV_FILE)) return [];
    
    $data = [];
    $handle = fopen(CSV_FILE, "r");
    if ($handle !== FALSE) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && !empty(trim($row[0])) && is_numeric(trim($row[1]))) {
                $movie_name = trim($row[0]);
                $message_id = intval(trim($row[1]));
                $channel = isset($row[2]) ? normalize_channel_identifier(trim($row[2])) : DEFAULT_UPLOAD_CHANNEL;
                
                $movie_entry = [
                    'movie_name' => $movie_name,
                    'message_id' => $message_id,
                    'channel' => $channel,
                    'date' => isset($row[3]) ? trim($row[3]) : date('d-m-Y'),
                    'quality' => isset($row[4]) ? trim($row[4]) : '',
                    'language' => isset($row[5]) ? trim($row[5]) : '',
                    'added_by' => isset($row[6]) ? trim($row[6]) : 'unknown',
                    'added_at' => isset($row[7]) ? trim($row[7]) : date('Y-m-d H:i:s')
                ];
                
                $data[] = $movie_entry;
                $movie_lower = strtolower($movie_name);
                if (!isset($movie_messages[$movie_lower])) $movie_messages[$movie_lower] = [];
                $movie_messages[$movie_lower][] = $message_id;
                update_channel_stats($channel, 1);
            }
        }
        fclose($handle);
    }
    update_stats('total_movies', count($data));
    return $data;
}

function append_movie($movie_name, $message_id, $channel = null) {
    if (empty(trim($movie_name))) {
        error_log("❌ Empty movie_name skipped");
        return;
    }
    if (!is_numeric($message_id)) {
        error_log("❌ Non-numeric message_id skipped: " . $message_id);
        return;
    }
    if ($channel === null) $channel = DEFAULT_UPLOAD_CHANNEL;
    $channel = normalize_channel_identifier($channel);
    
    $data = [
        'movie_name' => trim($movie_name),
        'message_id' => intval($message_id),
        'channel' => $channel,
        'date' => date('d-m-Y'),
        'quality' => '',
        'language' => '',
        'added_by' => 'auto',
        'added_at' => date('Y-m-d H:i:s')
    ];
    
    $handle = fopen(CSV_FILE, "a");
    if ($handle !== false) {
        fputcsv($handle, $data);
        fclose($handle);
    } else {
        error_log("❌ Failed to open CSV file");
        return;
    }
    
    global $movie_messages;
    $movie = strtolower(trim($movie_name));
    if (!isset($movie_messages[$movie])) $movie_messages[$movie] = [];
    $movie_messages[$movie][] = intval($message_id);
    update_channel_stats($channel, 1);
    update_stats('total_movies', 1);
    
    global $waiting_users;
    foreach ($waiting_users as $query => $users) {
        if (strpos($movie, strtolower($query)) !== false) {
            foreach ($users as $user_data) {
                list($user_chat_id, $user_id) = $user_data;
                $channel_info = get_channel_info($channel);
                $forward_msg = "🎬 <b>New Movie Added!</b>\n\n✅ '" . htmlspecialchars($movie_name) . "' has been added!\n📁 Source: " . $channel_info['icon'] . " " . $channel_info['title'] . "\n\nClick below to download:";
                $keyboard = [['inline_keyboard' => [[['text' => '📥 Download Now', 'url' => get_message_url($channel, $message_id)]]]]];
                sendMessage($user_chat_id, $forward_msg, $keyboard, 'HTML');
            }
            unset($waiting_users[$query]);
        }
    }
    error_log("🎬 '" . $movie_name . "' saved to CSV (Channel: " . $channel . ", ID: " . $message_id . ")");
}

function get_message_url($channel, $message_id) {
    if (strpos($channel, '@') === 0) return "https://t.me/" . substr($channel, 1) . "/" . $message_id;
    if (is_numeric($channel)) return "https://t.me/c/" . substr($channel, 4) . "/" . $message_id;
    return "https://t.me/" . str_replace('@', '', DEFAULT_UPLOAD_CHANNEL) . "/" . $message_id;
}

function get_all_movies_from_csv() {
    $movies = [];
    if (!file_exists(CSV_FILE)) return $movies;
    $handle = fopen(CSV_FILE, "r");
    if ($handle !== FALSE) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3) {
                $movies[] = [
                    'movie_name' => $row[0] ?? '',
                    'message_id' => $row[1] ?? '',
                    'channel' => $row[2] ?? CHANNEL_ID,
                    'date' => $row[3] ?? date('d-m-Y'),
                    'quality' => $row[4] ?? '',
                    'language' => $row[5] ?? '',
                    'added_by' => $row[6] ?? 'bot',
                    'added_at' => $row[7] ?? date('Y-m-d H:i:s')
                ];
            }
        }
        fclose($handle);
    }
    return $movies;
}

function save_movies_to_csv($movies) {
    $header = ['movie_name', 'message_id', 'channel', 'date', 'quality', 'language', 'added_by', 'added_at'];
    $handle = fopen(CSV_FILE, 'w');
    if ($handle === false) return false;
    fputcsv($handle, $header);
    foreach ($movies as $movie) {
        $row = [
            $movie['movie_name'] ?? '',
            $movie['message_id'] ?? '',
            $movie['channel'] ?? DEFAULT_UPLOAD_CHANNEL,
            $movie['date'] ?? date('d-m-Y'),
            $movie['quality'] ?? '',
            $movie['language'] ?? '',
            $movie['added_by'] ?? 'bot',
            $movie['added_at'] ?? date('Y-m-d H:i:s')
        ];
        fputcsv($handle, $row);
    }
    fclose($handle);
    return true;
}

// ==============================
// CHANNEL HELPER FUNCTIONS
// ==============================
function get_channel_display_name($channel_id) {
    global $CHANNEL_INFO;
    if (isset($CHANNEL_INFO[$channel_id])) {
        return $CHANNEL_INFO[$channel_id]['icon'] . " " . $CHANNEL_INFO[$channel_id]['title'];
    }
    foreach ($CHANNEL_INFO as $channel => $info) {
        if (isset($info['id']) && $info['id'] == $channel_id) {
            return $info['icon'] . " " . $info['title'];
        }
    }
    return $channel_id;
}

function get_channel_info($channel_id) {
    global $CHANNEL_INFO;
    foreach ($CHANNEL_INFO as $channel => $info) {
        if ($channel == $channel_id || (isset($info['id']) && $info['id'] == $channel_id)) {
            return $info;
        }
    }
    return ['id' => $channel_id, 'title' => 'Unknown Channel', 'type' => 'unknown', 'icon' => '📁'];
}

function get_channel_url($channel_id) {
    global $CHANNEL_INFO;
    if (isset($CHANNEL_INFO[$channel_id]['url'])) return $CHANNEL_INFO[$channel_id]['url'];
    return "https://t.me/" . str_replace('@', '', BOT_USERNAME);
}

function validate_channel_id($channel_id) {
    global $CHANNEL_INFO;
    if (isset($CHANNEL_INFO[$channel_id])) return true;
    foreach ($CHANNEL_INFO as $channel => $info) {
        if (isset($info['id']) && $info['id'] == $channel_id) return true;
    }
    return false;
}

// ==============================
// CACHE SYSTEM
// ==============================
function get_cached_movies() {
    global $movie_cache;
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < CACHE_EXPIRY) {
        return $movie_cache['data'];
    }
    $movie_cache = ['data' => load_and_clean_csv(), 'timestamp' => time()];
    return $movie_cache['data'];
}

// ==============================
// AI-POWERED SMART SEARCH
// ==============================
function smart_search($query) {
    global $movie_messages;
    $query_lower = strtolower(trim($query));
    $results = array();
    
    foreach ($movie_messages as $movie => $msg_ids) {
        $score = 0;
        if ($movie == $query_lower) {
            $score = 100;
        } elseif (strpos($movie, $query_lower) !== false) {
            $score = 80 - (strlen($movie) - strlen($query_lower));
        } else {
            similar_text($movie, $query_lower, $similarity);
            if ($similarity > 60) $score = $similarity;
        }
        
        if ($score > 0) {
            $results[$movie] = ['score' => $score, 'count' => count($msg_ids)];
        }
    }
    
    uasort($results, function($a, $b) { return $b['score'] - $a['score']; });
    return array_slice($results, 0, 10);
}

// ==============================
// MULTI-LANGUAGE SUPPORT
// ==============================
function detect_language($text) {
    $hindi_keywords = ['फिल्म', 'मूवी', 'डाउनलोड', 'हिंदी'];
    $english_keywords = ['movie', 'download', 'watch', 'print'];
    $hindi_count = 0; $english_count = 0;
    foreach ($hindi_keywords as $keyword) { if (strpos($text, $keyword) !== false) $hindi_count++; }
    foreach ($english_keywords as $keyword) { if (stripos($text, $keyword) !== false) $english_count++; }
    return $hindi_count > $english_count ? 'hindi' : 'english';
}

function send_multilingual_response($chat_id, $message_type, $language) {
    $responses = [
        'hindi' => [
            'welcome' => "🎬 स्वागत है! कौन सी मूवी चाहिए?",
            'found' => "✅ मूवी मिल गई!",
            'not_found' => "❌ अभी यह मूवी उपलब्ध नहीं है",
            'searching' => "🔍 आपकी मूवी ढूंढ रहे हैं..."
        ],
        'english' => [
            'welcome' => "🎬 Welcome! Which movie do you want?",
            'found' => "✅ Movie found!",
            'not_found' => "❌ Movie not available yet",
            'searching' => "🔍 Searching for your movie..."
        ]
    ];
    if ($message_type == 'searching') {
        sendChatAction($chat_id, 'typing');
        usleep(800000);
    }
    return sendMessageWithDelay($chat_id, $responses[$language][$message_type], null, null, 500);
}

// ==============================
// ADVANCED SEARCH FUNCTION
// ==============================
function advanced_search($chat_id, $query) {
    global $movie_messages, $waiting_users;
    $query_lower = strtolower(trim($query));
    if (strlen($query_lower) < 2) {
        sendMessageWithDelay($chat_id, "❌ Please enter at least 2 characters for search", null, null, 500);
        return;
    }
    
    sendChatAction($chat_id, 'typing');
    $search_steps = ["🔍 Searching...", "🔎 Checking database...", "📊 Analyzing results..."];
    foreach ($search_steps as $step) {
        sendChatAction($chat_id, 'typing');
        usleep(400000);
    }
    
    $found_movies = smart_search($query_lower);
    
    if (!empty($found_movies)) {
        sendChatAction($chat_id, 'typing');
        usleep(600000);
        $msg = "🔍 Found " . count($found_movies) . " movies for '$query':\n\n";
        $count = 1;
        foreach ($found_movies as $movie => $data) {
            $msg .= "$count. $movie (" . $data['count'] . " messages)\n";
            $count++;
            if ($count > 15) break;
        }
        sendMessageWithDelay($chat_id, $msg, null, null, 800);
        usleep(300000);
        
        $top_matches = array_slice(array_keys($found_movies), 0, 5);
        $keyboard = array('inline_keyboard' => array());
        foreach ($top_matches as $movie) {
            $keyboard['inline_keyboard'][] = array(['text' => "🎬 " . ucwords($movie), 'callback_data' => $movie]);
        }
        sendMessageWithDelay($chat_id, "🚀 Top matches:", $keyboard, null, 400);
        update_user_points($chat_id, 'found_movie');
    } else {
        $language = detect_language($query);
        sendChatAction($chat_id, 'typing');
        usleep(1000000);
        send_multilingual_response($chat_id, 'not_found', $language);
        if (!isset($waiting_users[$query_lower])) $waiting_users[$query_lower] = array();
        $waiting_users[$query_lower][] = array($chat_id, $user_id);
    }
    
    update_stats('total_searches', 1);
    update_user_points($chat_id, 'search');
}

// ==============================
// AUTO-BACKUP SYSTEM
// ==============================
function auto_backup() {
    $backup_files = [CSV_FILE, USERS_FILE, STATS_FILE];
    $backup_dir = BACKUP_DIR . date('Y-m-d');
    if (!file_exists($backup_dir)) mkdir($backup_dir, 0777, true);
    foreach ($backup_files as $file) {
        if (file_exists($file)) copy($file, $backup_dir . '/' . basename($file) . '.bak');
    }
    $old_backups = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    if (count($old_backups) > 7) array_map('rmdir', array_slice($old_backups, 0, -7));
}

// ==============================
// DAILY DIGEST FEATURE
// ==============================
function send_daily_digest() {
    $yesterday = date('d-m-Y', strtotime('-1 day'));
    $yesterday_movies = array();
    $handle = fopen(CSV_FILE, "r");
    if ($handle !== FALSE) {
        fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && $row[2] == $yesterday) $yesterday_movies[] = $row[0];
        }
        fclose($handle);
    }
    
    if (!empty($yesterday_movies)) {
        $users_data = json_decode(file_get_contents(USERS_FILE), true);
        foreach ($users_data['users'] as $user_id => $user_data) {
            $msg = "📅 <b>Daily Movie Digest</b>\n\n📢 Join our channel: @EntertainmentTadka786\n\n🎬 Yesterday's Uploads (" . $yesterday . "):\n";
            foreach (array_slice($yesterday_movies, 0, 10) as $movie) $msg .= "• " . $movie . "\n";
            if (count($yesterday_movies) > 10) $msg .= "• ... and " . (count($yesterday_movies) - 10) . " more\n";
            $msg .= "\n🔥 Total: " . count($yesterday_movies) . " movies";
            sendMessage($user_id, $msg, null, 'HTML');
        }
    }
}

// ==============================
// USER POINTS SYSTEM
// ==============================
function update_user_points($user_id, $action) {
    $points_map = ['search' => 1, 'found_movie' => 5, 'daily_login' => 10];
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    if (!isset($users_data['users'][$user_id]['points'])) $users_data['users'][$user_id]['points'] = 0;
    $users_data['users'][$user_id]['points'] += $points_map[$action];
    $users_data['users'][$user_id]['last_activity'] = date('Y-m-d H:i:s');
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
}

function update_user_activity($user_id, $activity_type) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    if (isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id]['last_activity'] = date('Y-m-d H:i:s');
        $users_data['users'][$user_id]['last_activity_type'] = $activity_type;
        $points_map = ['daily_login' => 5, 'movie_request' => 10, 'movie_found' => 2];
        if (isset($points_map[$activity_type])) {
            $users_data['users'][$user_id]['points'] = ($users_data['users'][$user_id]['points'] ?? 0) + $points_map[$activity_type];
        }
        file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    }
}

// ==============================
// HIDE HEADER FORWARDING SYSTEM
// ==============================
function copy_message_hidden($chat_id, $from_chat_id, $message_id) {
    return apiRequest('copyMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id,
        'disable_notification' => true
    ]);
}

function deliver_movie_hidden($chat_id, $movie_data) {
    $message_id = $movie_data['message_id'] ?? null;
    $movie_name = $movie_data['movie_name'] ?? 'Unknown Movie';
    
    if ($message_id && is_numeric($message_id)) {
        $result = copy_message_hidden($chat_id, $movie_data['channel'] ?? MAIN_CHANNEL, $message_id);
        if ($result && isset($result['ok']) && $result['ok']) {
            update_stats('total_downloads', 1);
            error_log("🎬 Movie delivered via copyMessage: $movie_name to $chat_id");
            return ['method' => 'copy', 'success' => true];
        }
    }
    
    if ($message_id && is_numeric($message_id)) {
        $result_json = forwardMessage($chat_id, $movie_data['channel'] ?? MAIN_CHANNEL, $message_id);
        $result = json_decode($result_json, true);
        if ($result && isset($result['ok']) && $result['ok']) {
            update_stats('total_downloads', 1);
            error_log("🎬 Movie delivered via forward: $movie_name to $chat_id");
            return ['method' => 'forward', 'success' => true];
        }
    }
    
    return send_movie_as_text($chat_id, $movie_data);
}

function send_movie_as_text($chat_id, $movie_data) {
    $movie_name = $movie_data['movie_name'] ?? 'Unknown Movie';
    $message_id = $movie_data['message_id'] ?? null;
    $date = $movie_data['date'] ?? date('d-m-Y');
    $channel = $movie_data['channel'] ?? MAIN_CHANNEL;
    
    $text = "🎬 *" . htmlspecialchars($movie_name) . "*\n\n";
    $text .= "📅 *Date:* {$date}\n";
    $text .= "📁 *Source:* " . get_channel_display_name($channel) . "\n\n";
    if ($message_id) $text .= "🔗 Message ID: `{$message_id}`\n";
    $text .= "\n⚠️ *Note:* Join our channel for direct access\n";
    $text .= "📢 " . get_channel_display_name($channel);
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '📥 Download Now', 'url' => get_message_url($channel, $message_id)]],
            [['text' => '🔍 Search More', 'callback_data' => 'search_more'], ['text' => '🎬 Join Channel', 'url' => get_channel_url($channel)]]
        ]
    ];
    
    sendMessage($chat_id, $text, $keyboard, 'Markdown');
    error_log("📄 Movie delivered as text: $movie_name to $chat_id");
    return ['method' => 'text', 'success' => true];
}

function batch_deliver_hidden($chat_id, $movies, $page_num = 1) {
    $total = count($movies);
    if ($total === 0) {
        sendMessageWithDelay($chat_id, "❌ No movies to deliver!", null, null, 400);
        return;
    }
    
    sendChatAction($chat_id, 'typing');
    usleep(500000);
    
    $progress_msg = sendMessage($chat_id, 
        "📦 *Batch Delivery Started*\n\n📄 Page: {$page_num}\n🎬 Total: {$total} movies\n\n⏱️ <i>Preparing batch download...</i>", 
        null, 'HTML'
    );
    
    $progress_data = json_decode($progress_msg, true);
    $progress_id = $progress_data['result']['message_id'] ?? null;
    $success = 0; $failed = 0; $methods_used = [];
    
    showTypingProgress($chat_id, 3, "Initializing batch");
    
    for ($i = 0; $i < $total; $i++) {
        $movie = $movies[$i];
        if ($i % 2 == 0 && $progress_id) {
            $progress = round(($i / $total) * 100);
            editMessage($chat_id, $progress_id, 
                "📦 *Batch Delivery - Page {$page_num}*\n\n📊 Progress: {$progress}%\n✅ Success: {$success}\n❌ Failed: {$failed}\n\n🔄 Processing: " . substr($movie['movie_name'], 0, 30) . "...", 
                null, 'Markdown'
            );
        }
        
        try {
            $result = deliver_movie_hidden($chat_id, $movie);
            if ($result['success']) {
                $success++;
                $method = $result['method'];
                $methods_used[$method] = ($methods_used[$method] ?? 0) + 1;
            } else $failed++;
            usleep(800000);
        } catch (Exception $e) {
            $failed++;
            error_log("❌ Batch delivery failed for {$movie['movie_name']}: " . $e->getMessage());
        }
    }
    
    $method_summary = "";
    foreach ($methods_used as $method => $count) $method_summary .= "• {$method}: {$count}\n";
    
    if ($progress_id) {
        editMessage($chat_id, $progress_id,
            "✅ *Batch Delivery Complete*\n\n📄 Page: {$page_num}\n🎬 Total: {$total} movies\n✅ Successfully delivered: {$success}\n❌ Failed: {$failed}\n📊 Success rate: " . round(($success / $total) * 100, 2) . "%\n\n🔧 *Methods used:*\n{$method_summary}\n⏱️ Time: " . date('H:i:s'), 
            null, 'Markdown'
        );
    }
    error_log("✅ Batch delivery completed: {$success}/{$total} successful");
    return ['success' => $success, 'failed' => $failed, 'methods' => $methods_used];
}

// ==============================
// USER PREFERENCES
// ==============================
function get_user_preferences($user_id) {
    if (!file_exists(USER_PREFERENCES_FILE)) {
        return ['hide_header' => true, 'silent_mode' => false, 'auto_download' => false, 'notifications' => true];
    }
    $preferences = json_decode(file_get_contents(USER_PREFERENCES_FILE), true);
    return $preferences[$user_id] ?? ['hide_header' => true, 'silent_mode' => false, 'auto_download' => false, 'notifications' => true];
}

function set_user_preference($user_id, $key, $value) {
    if (!file_exists(USER_PREFERENCES_FILE)) $preferences = [];
    else $preferences = json_decode(file_get_contents(USER_PREFERENCES_FILE), true);
    if (!isset($preferences[$user_id])) $preferences[$user_id] = [];
    $preferences[$user_id][$key] = $value;
    file_put_contents(USER_PREFERENCES_FILE, json_encode($preferences, JSON_PRETTY_PRINT));
    return true;
}

// ==============================
// NOTIFICATION SYSTEM
// ==============================
$notification_templates = [
    'new_movies' => [
        'hindi' => "🎬 <b>नए Movies आ गए हैं!</b>\n\n{count} नई movies add की गई हैं।\n📅 आज की date: {date}\n\n{latest_movies}\n\n🍿 <b>देखने के लिए:</b> {main_channel}\n📝 <b>Requests:</b> {request_channel}\n\n🎯 <b>Use our bot</b> /start",
        'english' => "🎬 <b>New Movies Added!</b>\n\n{count} new movies added today.\n📅 Date: {date}\n\n{latest_movies}\n\n🍿 <b>Watch now:</b> {main_channel}\n📝 <b>Requests:</b> {request_channel}\n\n🎯 <b>Use our bot</b> /start"
    ],
    'trending' => [
        'hindi' => "🔥 <b>आज की Trending Movies!</b>\n\n{trending_list}\n\n📊 <b>Top searches today:</b> {top_searches}\n👥 <b>Active users:</b> {active_users}\n\n📥 <b>Download now from our bot!</b>\n🎬 /start",
        'english' => "🔥 <b>Today's Trending Movies!</b>\n\n{trending_list}\n\n📊 <b>Top searches:</b> {top_searches}\n👥 <b>Active users:</b> {active_users}\n\n📥 <b>Download now from our bot!</b>\n🎬 /start"
    ],
    'weekly_digest' => [
        'hindi' => "📊 <b>Weekly Digest - {week_range}</b>\n\n🎬 <b>Total movies added:</b> {movies_added}\n👥 <b>New users joined:</b> {new_users}\n🔍 <b>Total searches:</b> {total_searches}\n\n🏆 <b>Top user:</b> {top_user}\n🎯 <b>Most requested:</b> {most_requested}\n\n🚀 <b>Keep using our bot!</b>\n🎬 /start",
        'english' => "📊 <b>Weekly Digest - {week_range}</b>\n\n🎬 <b>Movies added:</b> {movies_added}\n👥 <b>New users:</b> {new_users}\n🔍 <b>Searches:</b> {total_searches}\n\n🏆 <b>Top user:</b> {top_user}\n🎯 <b>Most requested:</b> {most_requested}\n\n🚀 <b>Keep using our bot!</b>\n🎬 /start"
    ],
    'special_announcement' => [
        'hindi' => "📢 <b>Special Announcement!</b>\n\n{message}\n\n📅 <b>Date:</b> {date}\n\n🍿 <b>Main Channel:</b> {main_channel}\n💬 <b>Request Group:</b> {request_channel}\n\n🎯 <b>Use our bot</b> /start",
        'english' => "📢 <b>Special Announcement!</b>\n\n{message}\n\n📅 <b>Date:</b> {date}\n\n🍿 <b>Main Channel:</b> {main_channel}\n💬 <b>Request Group:</b> {request_channel}\n\n🎯 <b>Use our bot</b> /start"
    ]
];

function schedule_notifications() {
    $current_hour = date('H');
    $current_day = date('N');
    $current_date = date('Y-m-d');
    
    $notification_log = load_notification_log();
    if (isset($notification_log[$current_date]) && count($notification_log[$current_date]) >= MAX_NOTIFICATIONS_PER_DAY) {
        error_log("⚠️ Max notifications reached for today");
        return;
    }
    
    if ($current_hour == NOTIFICATION_HOUR && $current_day == NOTIFICATION_DAY) {
        error_log("📅 Sending weekly digest...");
        send_weekly_digest();
        log_notification('weekly_digest', $current_date);
    } elseif ($current_hour == NOTIFICATION_HOUR) {
        $new_movies_count = get_new_movies_count($current_date);
        if ($new_movies_count > 0) {
            error_log("🎬 Sending new movies notification... ($new_movies_count movies)");
            send_new_movies_notification($new_movies_count);
            log_notification('new_movies', $current_date);
        } else {
            error_log("🔥 Sending trending notification...");
            send_trending_notification();
            log_notification('trending', $current_date);
        }
    } elseif ($current_hour == '12' && rand(0, 100) > 70) {
        error_log("🎲 Random trending notification...");
        send_trending_notification();
        log_notification('trending', $current_date);
    }
}

function load_notification_log() {
    if (!file_exists(NOTIFICATION_LOG_FILE)) return [];
    $log_data = json_decode(file_get_contents(NOTIFICATION_LOG_FILE), true);
    return $log_data ?: [];
}

function log_notification($type, $date) {
    $log_data = load_notification_log();
    if (!isset($log_data[$date])) $log_data[$date] = [];
    $log_data[$date][] = ['type' => $type, 'time' => date('H:i:s'), 'timestamp' => time()];
    $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
    foreach ($log_data as $log_date => $entries) {
        if ($log_date < $thirty_days_ago) unset($log_data[$log_date]);
    }
    file_put_contents(NOTIFICATION_LOG_FILE, json_encode($log_data, JSON_PRETTY_PRINT));
}

function get_new_movies_count($date) {
    $count = 0;
    if (file_exists(CSV_FILE)) {
        $handle = fopen(CSV_FILE, 'r');
        if ($handle !== FALSE) {
            fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) >= 3 && $row[2] == date('d-m-Y', strtotime($date))) $count++;
            }
            fclose($handle);
        }
    }
    return $count;
}

function get_latest_movies($limit = 5) {
    $all_movies = get_all_movies_from_csv();
    return array_slice($all_movies, -$limit);
}

function get_trending_movies($limit = 5) {
    $all_movies = get_all_movies_from_csv();
    if (empty($all_movies)) return [];
    $recent_movies = [];
    $three_days_ago = date('d-m-Y', strtotime('-3 days'));
    foreach ($all_movies as $movie) {
        if ($movie['date'] >= $three_days_ago) $recent_movies[] = $movie;
    }
    if (count($recent_movies) < $limit) {
        shuffle($all_movies);
        return array_slice($all_movies, 0, $limit);
    }
    shuffle($recent_movies);
    return array_slice($recent_movies, 0, $limit);
}

function send_new_movies_notification($new_movies_count) {
    global $notification_templates;
    $latest_movies = get_latest_movies(5);
    $latest_movies_list = "";
    foreach ($latest_movies as $index => $movie) $latest_movies_list .= ($index + 1) . ". {$movie['movie_name']}\n";
    $message = str_replace(['{count}', '{date}', '{latest_movies}', '{main_channel}', '{request_channel}'],
        [$new_movies_count, date('d-m-Y'), $latest_movies_list, MAIN_CHANNEL, REQUEST_GROUP],
        $notification_templates['new_movies']['hindi']);
    send_notification_to_all_users($message);
}

function send_trending_notification() {
    global $notification_templates;
    $trending_movies = get_trending_movies(5);
    $trending_list = "";
    foreach ($trending_movies as $index => $movie) $trending_list .= ($index + 1) . ". {$movie['movie_name']}\n";
    $top_searches = ['kgf', 'pushpa', 'animal', 'salaar', 'pathaan', 'jawan', 'leo'];
    shuffle($top_searches);
    $top_searches = array_slice($top_searches, 0, 3);
    $active_users = get_active_users_count();
    $message = str_replace(['{trending_list}', '{top_searches}', '{active_users}'],
        [$trending_list, implode(', ', $top_searches), $active_users],
        $notification_templates['trending']['hindi']);
    send_notification_to_all_users($message);
}

function get_active_users_count() {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $active_count = 0;
    $one_day_ago = strtotime('-1 day');
    foreach ($users_data['users'] ?? [] as $user) {
        if (isset($user['last_active']) && strtotime($user['last_active']) >= $one_day_ago) $active_count++;
    }
    return $active_count;
}

function send_weekly_digest() {
    global $notification_templates;
    $week_start = date('Y-m-d', strtotime('-7 days'));
    $week_end = date('Y-m-d');
    $week_range = date('d M', strtotime($week_start)) . ' - ' . date('d M', strtotime($week_end));
    $weekly_stats = get_weekly_stats($week_start, $week_end);
    $message = str_replace(['{week_range}', '{movies_added}', '{new_users}', '{total_searches}', '{top_user}', '{most_requested}'],
        [$week_range, $weekly_stats['movies_added'] ?? 0, $weekly_stats['new_users'] ?? 0, $weekly_stats['total_searches'] ?? 0, $weekly_stats['top_user'] ?? 'N/A', $weekly_stats['most_requested'] ?? 'N/A'],
        $notification_templates['weekly_digest']['hindi']);
    send_notification_to_all_users($message);
}

function get_weekly_stats($start_date, $end_date) {
    $stats = ['movies_added' => 0, 'new_users' => 0, 'total_searches' => 0, 'top_user' => 'User#1234', 'most_requested' => 'Movie Name'];
    if (file_exists(CSV_FILE)) {
        $handle = fopen(CSV_FILE, 'r');
        if ($handle !== FALSE) {
            fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) >= 3) {
                    $movie_date = $row[2];
                    $date_obj = DateTime::createFromFormat('d-m-Y', $movie_date);
                    if ($date_obj && $date_obj >= date_create($start_date) && $date_obj <= date_create($end_date)) $stats['movies_added']++;
                }
            }
            fclose($handle);
        }
    }
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    foreach ($users_data['users'] ?? [] as $user) {
        if (isset($user['joined']) && $user['joined'] >= $start_date && $user['joined'] <= $end_date) $stats['new_users']++;
    }
    return $stats;
}

function send_notification_to_all_users($message) {
    $active_users = get_users_with_notifications_enabled();
    error_log("🔔 Sending notification to " . count($active_users) . " users...");
    $sent_count = 0; $failed_count = 0;
    foreach ($active_users as $user_id) {
        try {
            $result = sendMessage($user_id, $message, null, 'HTML');
            if ($result !== false) $sent_count++; else $failed_count++;
            usleep(50000);
        } catch (Exception $e) {
            $failed_count++;
            error_log("❌ Notification failed for user $user_id: " . $e->getMessage());
        }
    }
    error_log("✅ Notification sent to $sent_count users, failed: $failed_count");
    return ['sent' => $sent_count, 'failed' => $failed_count];
}

function get_users_with_notifications_enabled() {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $active_users = [];
    $one_month_ago = strtotime('-30 days');
    foreach ($users_data['users'] ?? [] as $user_id => $user) {
        if (isset($user['last_active']) && strtotime($user['last_active']) >= $one_month_ago) {
            $notifications_enabled = check_user_notification_preference($user_id);
            if ($notifications_enabled) $active_users[] = $user_id;
        }
    }
    return $active_users;
}

function check_user_notification_preference($user_id) {
    if (!file_exists(USER_PREFERENCES_FILE)) return true;
    $preferences = json_decode(file_get_contents(USER_PREFERENCES_FILE), true);
    return $preferences[$user_id]['notifications'] ?? true;
}

function send_special_announcement($announcement_text) {
    global $notification_templates;
    $message = str_replace(['{message}', '{date}', '{main_channel}', '{request_channel}'],
        [$announcement_text, date('d-m-Y'), MAIN_CHANNEL, REQUEST_GROUP],
        $notification_templates['special_announcement']['hindi']);
    send_notification_to_all_users($message);
}

// ==============================
// COMMAND FUNCTIONS
// ==============================
function check_date($chat_id) {
    if (!file_exists(CSV_FILE)) {
        sendMessage($chat_id, "⚠️ Abhi tak koi data save nahi hua.");
        return;
    }
    
    $date_counts = array();
    $handle = fopen(CSV_FILE, "r");
    if ($handle !== FALSE) {
        fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3) {
                $date = $row[2];
                if (!isset($date_counts[$date])) $date_counts[$date] = 0;
                $date_counts[$date]++;
            }
        }
        fclose($handle);
    }
    
    krsort($date_counts);
    $msg = "📅 <b>Movies Upload Record</b>\n\n";
    $total_days = 0; $total_movies = 0;
    
    foreach ($date_counts as $date => $count) {
        $msg .= "➡️ $date: $count movies\n";
        $total_days++; $total_movies += $count;
    }
    
    $msg .= "\n📊 <b>Summary:</b>\n• Total Days: $total_days\n• Total Movies: $total_movies\n• Average per day: " . round($total_movies / max(1, $total_days), 2);
    sendMessage($chat_id, $msg, null, 'HTML');
}

function total_uploads($chat_id, $page = 1) {
    if (!file_exists(CSV_FILE)) {
        sendMessage($chat_id, "⚠️ Abhi tak koi data save nahi hua.");
        return;
    }
    
    $items_per_page = 5;
    $total = 0; $today_str = date('d-m-Y'); $yesterday_str = date('d-m-Y', strtotime('-1 day'));
    $today_count = 0; $yesterday_count = 0; $weekly_total = 0; $all_movies = array();
    
    $handle = fopen(CSV_FILE, "r");
    if ($handle !== FALSE) {
        fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3) {
                $total++;
                $movie_name = $row[0]; $date = $row[2];
                $all_movies[] = ['name' => $movie_name, 'date' => $date, 'message_id' => $row[1]];
                if ($date == $today_str) $today_count++; elseif ($date == $yesterday_str) $yesterday_count++;
                $movie_date = DateTime::createFromFormat('d-m-Y', $date);
                if ($movie_date && $movie_date->diff(new DateTime())->days <= 7) $weekly_total++;
            }
        }
        fclose($handle);
    }
    
    $all_movies = array_reverse($all_movies);
    $total_pages = ceil(count($all_movies) / $items_per_page);
    $current_page = max(1, min($page, $total_pages));
    $start_index = ($current_page - 1) * $items_per_page;
    $paginated_movies = array_slice($all_movies, $start_index, $items_per_page);
    
    $msg = "📊 <b>Upload Statistics</b>\n\n• 🎬 Total: $total movies\n• 🚀 Today: $today_count movies\n• 📈 Yesterday: $yesterday_count movies\n• 📅 Last 7 days: $weekly_total movies\n• ⭐ Daily avg: " . round($total / max(1, count(array_unique(array_column($all_movies, 'date')))), 2) . " movies\n\n";
    $msg .= "🎬 <b>Movies List (Page $current_page/$total_pages):</b>\n\n";
    
    $index = 1;
    foreach ($paginated_movies as $movie) {
        $msg .= "<b>" . ($start_index + $index) . ".</b> " . $movie['name'] . "\n   📅: " . $movie['date'] . " | ID: " . $movie['message_id'] . "\n\n";
        $index++;
    }
    
    $keyboard = null;
    if ($total_pages > 1) {
        $keyboard = ['inline_keyboard' => []];
        $row_buttons = [];
        if ($current_page > 1) $row_buttons[] = ['text' => '⏮️ Previous', 'callback_data' => 'uploads_page_' . ($current_page - 1)];
        $row_buttons[] = ['text' => '📥 Download Page', 'callback_data' => 'batch_confirm_' . $current_page];
        if ($current_page < $total_pages) $row_buttons[] = ['text' => '⏭️ Next', 'callback_data' => 'uploads_page_' . ($current_page + 1)];
        if (!empty($row_buttons)) $keyboard['inline_keyboard'][] = $row_buttons;
    }
    
    sendMessage($chat_id, $msg, $keyboard, 'HTML');
}

function test_csv($chat_id) {
    if (!file_exists(CSV_FILE)) {
        sendMessage($chat_id, "⚠️ CSV file not found.");
        return;
    }
    
    $handle = fopen(CSV_FILE, "r");
    if ($handle !== FALSE) {
        fgetcsv($handle);
        $index = 1; $msg = "";
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3) {
                $line = "$index. {$row[0]} | ID: {$row[1]} | Channel: {$row[2]} | Date: {$row[3]}\n";
                if (strlen($msg) + strlen($line) > 4000) {
                    sendMessage($chat_id, $msg); $msg = "";
                }
                $msg .= $line; $index++;
            }
        }
        fclose($handle);
        if (!empty($msg)) sendMessage($chat_id, $msg);
    }
}

function admin_stats($chat_id) {
    sendChatAction($chat_id, 'typing');
    $processing_steps = ["📊 Fetching statistics...", "📈 Calculating totals...", "👥 Loading user data...", "🎬 Processing movie counts..."];
    foreach ($processing_steps as $step) {
        sendChatAction($chat_id, 'typing');
        usleep(300000);
    }
    
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $total_users = count($users_data['users'] ?? []);
    
    $msg = "📊 <b>Bot Statistics</b>\n\n🎬 Total Movies: " . ($stats['total_movies'] ?? 0) . "\n👥 Total Users: " . $total_users . "\n🔍 Total Searches: " . ($stats['total_searches'] ?? 0) . "\n🕒 Last Updated: " . ($stats['last_updated'] ?? 'N/A') . "\n\n";
    
    sendChatAction($chat_id, 'typing');
    usleep(400000);
    
    $csv_data = load_and_clean_csv();
    $recent_movies = array_slice($csv_data, -5);
    $msg .= "📈 <b>Recent Uploads:</b>\n";
    foreach ($recent_movies as $movie) $msg .= "• " . $movie['movie_name'] . " (" . $movie['date'] . ")\n";
    
    sendMessageWithDelay($chat_id, $msg, null, 'HTML', 1000);
}

// ==============================
// ADMIN MOVIE MANAGEMENT
// ==============================
function handle_admin_commands($chat_id, $user_id, $text) {
    if ($user_id != OWNER_ID) {
        sendMessage($chat_id, "❌ <b>Access Denied!</b>\nAdmin only command.", null, 'HTML');
        return;
    }
    
    $parts = explode(' ', $text, 2);
    $command = strtolower($parts[0]);
    $params = isset($parts[1]) ? $parts[1] : '';
    
    switch ($command) {
        case '/addmovie': handle_add_movie($chat_id, $params); break;
        case '/quickadd': handle_quick_add($chat_id, $params); break;
        case '/listmovies': 
            $page = isset(explode(' ', $params)[0]) ? intval(explode(' ', $params)[0]) : 1;
            list_movies($chat_id, $page); 
            break;
        case '/findmovie': find_movie($chat_id, $params); break;
        case '/deletemovie': delete_movie($chat_id, $params); break;
        case '/adminstats': show_admin_stats($chat_id); break;
        default:
            sendMessage($chat_id, "❌ <b>Unknown Admin Command</b>\n\n📋 <b>Available Admin Commands:</b>\n• /addmovie [details] - Add single movie\n• /quickadd [bulk] - Bulk add movies\n• /listmovies [page] - List all movies\n• /findmovie [name] - Search movie\n• /deletemovie [name/id] - Delete movie\n• /adminstats - Advanced statistics\n\n📝 <b>Format Examples:</b>\n<code>/addmovie Movie Name,123,@channel</code>\n<code>/quickadd Movie1,123,@chan;Movie2,456,-100...</code>", null, 'HTML');
    }
}

function handle_add_movie($chat_id, $input) {
    if (empty(trim($input))) {
        sendMessage($chat_id, "❌ <b>Usage:</b> <code>/addmovie Movie Name,MessageID,Channel</code>\n\n📝 <b>Examples:</b>\n<code>/addmovie Squid Game 2021 S01,251,@EntertainmentTadka786</code>\n<code>/addmovie The Raja Saab 2026,36,@threater_print_movies</code>\n<code>/addmovie Nalla Nilavulla Rathri,2023,6011,-1002337293281</code>\n\n📊 <b>Optional Parameters:</b>\n• Date: 01-01-2024 (default: today)\n• Quality: 720p, 1080p, etc.\n• Language: Hindi, English, etc.\n\n<code>/addmovie Movie,123,@chan,01-01-2024,1080p,Hindi</code>", null, 'HTML');
        return;
    }
    
    $parts = array_map('trim', explode(',', $input));
    if (count($parts) < 3) {
        sendMessage($chat_id, "❌ <b>Error:</b> Need at least: Movie Name, Message ID, Channel", null, 'HTML');
        return;
    }
    
    $movie_name = $parts[0]; $message_id = $parts[1]; $channel_input = $parts[2];
    $channel = get_channel_identifier($channel_input);
    $date = isset($parts[3]) ? $parts[3] : date('d-m-Y');
    $quality = isset($parts[4]) ? $parts[4] : ''; $language = isset($parts[5]) ? $parts[5] : '';
    
    if (!is_numeric($message_id)) {
        sendMessage($chat_id, "❌ <b>Error:</b> Message ID must be numeric", null, 'HTML');
        return;
    }
    
    if (!validate_date($date)) {
        $date = date('d-m-Y');
        sendMessage($chat_id, "⚠️ <b>Note:</b> Using today's date: $date", null, 'HTML');
    }
    
    sendChatAction($chat_id, 'typing');
    usleep(500000);
    
    $movie_data = [
        'movie_name' => $movie_name, 'message_id' => $message_id, 'channel' => $channel,
        'date' => $date, 'quality' => $quality, 'language' => $language,
        'added_by' => 'admin', 'added_at' => date('Y-m-d H:i:s')
    ];
    
    $result = add_movie_to_csv($movie_data);
    if ($result) {
        $channel_name = get_channel_display_name($channel);
        $success_msg = "✅ <b>Movie Added Successfully!</b>\n\n🎬 <b>Movie:</b> $movie_name\n🆔 <b>Message ID:</b> $message_id\n📁 <b>Channel:</b> $channel_name\n🔗 <b>Channel ID:</b> <code>$channel</code>\n📅 <b>Date:</b> $date\n";
        if (!empty($quality)) $success_msg .= "📊 <b>Quality:</b> $quality\n";
        if (!empty($language)) $success_msg .= "🗣️ <b>Language:</b> $language\n";
        $success_msg .= "\n💾 <b>CSV Format:</b> <code>$movie_name,$message_id,$channel</code>";
        
        $keyboard = null;
        $message_url = get_message_url($channel, $message_id);
        if ($message_url) $keyboard = ['inline_keyboard' => [[['text' => '🔗 Direct Link', 'url' => $message_url]]]];
        
        sendMessage($chat_id, $success_msg, $keyboard, 'HTML');
        update_stats('total_movies', 1);
        log_admin_action('admin', "add_movie", $movie_name);
    } else {
        sendMessage($chat_id, "❌ <b>Error:</b> Failed to add movie. Check file permissions.", null, 'HTML');
    }
}

function add_movie_to_csv($movie_data) {
    $channel = normalize_channel_identifier($movie_data['channel'] ?? DEFAULT_UPLOAD_CHANNEL);
    $row_data = [
        'movie_name' => $movie_data['movie_name'] ?? '',
        'message_id' => $movie_data['message_id'] ?? '',
        'channel' => $channel,
        'date' => $movie_data['date'] ?? date('d-m-Y'),
        'quality' => $movie_data['quality'] ?? '',
        'language' => $movie_data['language'] ?? '',
        'added_by' => $movie_data['added_by'] ?? 'manual',
        'added_at' => $movie_data['added_at'] ?? date('Y-m-d H:i:s')
    ];
    
    $handle = fopen(CSV_FILE, 'a');
    if ($handle === false) {
        error_log("❌ Failed to open CSV file for writing: " . CSV_FILE);
        return false;
    }
    
    fputcsv($handle, $row_data);
    fclose($handle);
    
    global $movie_messages;
    $movie_lower = strtolower(trim($movie_data['movie_name']));
    if (!isset($movie_messages[$movie_lower])) $movie_messages[$movie_lower] = [];
    $movie_messages[$movie_lower][] = intval($movie_data['message_id']);
    update_channel_stats($channel, 1);
    error_log("✅ Movie added to CSV: " . $movie_data['movie_name'] . " (Channel: " . $channel . ", ID: " . $movie_data['message_id'] . ")");
    return true;
}

function validate_date($date) {
    $d = DateTime::createFromFormat('d-m-Y', $date);
    return $d && $d->format('d-m-Y') === $date;
}

function log_admin_action($user_id, $action, $details) {
    $log_file = 'admin_log.json';
    $log = [];
    if (file_exists($log_file)) $log = json_decode(file_get_contents($log_file), true) ?: [];
    $log_entry = [
        'user_id' => $user_id, 'action' => $action, 'details' => $details,
        'timestamp' => date('Y-m-d H:i:s'), 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
    ];
    $log[] = $log_entry;
    if (count($log) > 1000) $log = array_slice($log, -1000);
    file_put_contents($log_file, json_encode($log, JSON_PRETTY_PRINT));
}

function handle_quick_add($chat_id, $input) {
    if (empty(trim($input))) {
        sendMessage($chat_id, "❌ <b>Usage:</b> <code>/quickadd movies_list</code>\n\n📝 <b>Format:</b> Movie,ID,Channel;Movie,ID,Channel;...\n\n🎬 <b>Example:</b>\n<code>/quickadd Squid Game 2021 S01,251,@EntertainmentTadka786;The Raja Saab 2026,36,@threater_print_movies;Nalla Nilavulla Rathri,2023,6011,-1002337293281</code>\n\n💡 <b>Tip:</b> Separate movies with semicolon (;)", null, 'HTML');
        return;
    }
    
    sendChatAction($chat_id, 'typing');
    $movies_input = str_replace(["\n", "\r"], ';', $input);
    $movies_list = array_filter(explode(';', $movies_input), 'trim');
    
    if (empty($movies_list)) {
        sendMessage($chat_id, "❌ <b>Error:</b> No valid movies found in input", null, 'HTML');
        return;
    }
    
    $total_movies = count($movies_list);
    $success_count = 0; $failed_count = 0; $failed_movies = [];
    
    if ($total_movies > MOVIES_PER_QUICKADD) {
        sendMessage($chat_id, "⚠️ <b>Too many movies!</b>\nFound: $total_movies movies\nLimit: " . MOVIES_PER_QUICKADD . " per batch\nProcessing first " . MOVIES_PER_QUICKADD . " movies...", null, 'HTML');
        $movies_list = array_slice($movies_list, 0, MOVIES_PER_QUICKADD);
    }
    
    $progress_msg = sendMessage($chat_id, "📦 <b>Quick Add Started</b>\n\n🎬 Total movies: " . count($movies_list) . "\n🔄 Processing... (0/" . count($movies_list) . ")", null, 'HTML');
    $progress_id = $progress_msg['result']['message_id'] ?? null;
    
    foreach ($movies_list as $index => $movie_input) {
        $movie_input = trim($movie_input);
        if ($progress_id && ($index % 2 == 0 || $index == 0)) {
            editMessage($chat_id, $progress_id, "📦 <b>Quick Add Progress</b>\n\n🎬 Total: " . count($movies_list) . "\n✅ Success: $success_count\n❌ Failed: $failed_count\n🔄 Processing: " . ($index + 1) . "/" . count($movies_list) . "\n\n📝 Current: " . substr($movie_input, 0, 30) . "...", null, 'HTML');
        }
        
        $parts = array_map('trim', explode(',', $movie_input));
        if (count($parts) >= 3) {
            $movie_name = $parts[0]; $message_id = $parts[1]; $channel = $parts[2];
            $date = isset($parts[3]) ? $parts[3] : date('d-m-Y');
            if (is_numeric($message_id)) {
                $movie_data = [
                    'movie_name' => $movie_name, 'message_id' => $message_id, 'channel' => $channel,
                    'date' => $date, 'added_by' => 'quickadd', 'added_at' => date('Y-m-d H:i:s')
                ];
                if (add_movie_to_csv($movie_data)) $success_count++; else { $failed_count++; $failed_movies[] = $movie_name; }
            } else { $failed_count++; $failed_movies[] = $movie_name . " (Invalid ID)"; }
        } else { $failed_count++; $failed_movies[] = $movie_input . " (Invalid format)"; }
        usleep(100000);
    }
    
    $result_msg = "✅ <b>Quick Add Complete!</b>\n\n📊 <b>Results:</b>\n• Total processed: " . count($movies_list) . "\n• ✅ Successfully added: $success_count\n• ❌ Failed: $failed_count\n\n";
    if ($success_count > 0) {
        $result_msg .= "🎬 <b>Movies added to database!</b>\n";
        update_stats('total_movies', $success_count);
        log_admin_action('quickadd_batch', "add_batch", "Added $success_count movies");
    }
    
    if ($failed_count > 0) {
        $result_msg .= "⚠️ <b>Failed movies:</b>\n";
        $failed_list = array_slice($failed_movies, 0, 5);
        foreach ($failed_list as $failed) $result_msg .= "• $failed\n";
        if (count($failed_movies) > 5) $result_msg .= "• ... and " . (count($failed_movies) - 5) . " more\n";
    }
    
    if ($progress_id) editMessage($chat_id, $progress_id, $result_msg, null, 'HTML');
    else sendMessage($chat_id, $result_msg, null, 'HTML');
}

function list_movies($chat_id, $page = 1) {
    $all_movies = get_all_movies_from_csv();
    $total_movies = count($all_movies);
    
    if ($total_movies == 0) {
        sendMessage($chat_id, "📭 <b>No movies found in database.</b>", null, 'HTML');
        return;
    }
    
    $per_page = MAX_MOVIES_PER_PAGE;
    $total_pages = ceil($total_movies / $per_page);
    $page = max(1, min($page, $total_pages));
    
    $start = ($page - 1) * $per_page;
    $movies = array_slice($all_movies, $start, $per_page);
    
    $message = "📋 <b>Movie Database (New Format)</b>\n\n📊 <b>Statistics:</b>\n• Total movies: $total_movies\n• Total pages: $total_pages\n• Current page: $page/$total_pages\n• CSV format: <code>movie_name,message_id,channel</code>\n\n🎬 <b>Movies (Page $page):</b>\n\n";
    
    $counter = $start + 1;
    foreach ($movies as $movie) {
        $channel_display = get_channel_display_name($movie['channel'] ?? 'Unknown');
        $channel_raw = $movie['channel'] ?? 'N/A';
        $message .= "<b>$counter.</b> " . htmlspecialchars($movie['movie_name']) . "\n";
        $message .= "   🆔: " . ($movie['message_id'] ?? 'N/A') . " | 📁: $channel_display\n";
        $message .= "   📅: " . ($movie['date'] ?? 'N/A') . " | 🔗: <code>$channel_raw</code>\n\n";
        $counter++;
    }
    
    if ($page == 1) {
        $message .= "💾 <b>CSV Format Example:</b>\n<code>Squid Game 2021 S01,251,@EntertainmentTadka786</code>\n<code>The Raja Saab 2026,36,@threater_print_movies</code>\n<code>Private Movie,1234,-1002337293281</code>\n";
    }
    
    $keyboard = ['inline_keyboard' => []];
    if ($total_pages > 1) {
        $row = [];
        if ($page > 1) $row[] = ['text' => '⏮️ Previous', 'callback_data' => 'list_page_' . ($page - 1)];
        $row[] = ['text' => "📄 $page/$total_pages", 'callback_data' => 'current_page'];
        if ($page < $total_pages) $row[] = ['text' => '⏭️ Next', 'callback_data' => 'list_page_' . ($page + 1)];
        $keyboard['inline_keyboard'][] = $row;
    }
    
    $keyboard['inline_keyboard'][] = [
        ['text' => '📥 Export CSV', 'callback_data' => 'export_csv'],
        ['text' => '🔄 Refresh', 'callback_data' => 'refresh_list']
    ];
    
    $keyboard['inline_keyboard'][] = [
        ['text' => '📋 View CSV Format', 'callback_data' => 'show_csv_format'],
        ['text' => '📊 Channel Stats', 'callback_data' => 'channel_stats_detail']
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function find_movie($chat_id, $search_query) {
    if (empty(trim($search_query))) {
        sendMessage($chat_id, "❌ <b>Usage:</b> <code>/findmovie movie_name</code>\n\n📝 <b>Examples:</b>\n<code>/findmovie kgf</code>\n<code>/findmovie 2024</code>\n<code>/findmovie @EntertainmentTadka786</code>\n\n🔍 <b>Search options:</b>\n• Movie name\n• Year\n• Channel\n• Quality (720p, 1080p)\n• Language", null, 'HTML');
        return;
    }
    
    sendChatAction($chat_id, 'typing');
    usleep(800000);
    
    $all_movies = get_all_movies_from_csv();
    $results = [];
    $search_lower = strtolower($search_query);
    
    foreach ($all_movies as $movie) {
        $score = 0;
        if (stripos($movie['movie_name'], $search_query) !== false) $score += 100;
        if (strpos($movie['channel'] ?? '', $search_query) !== false) $score += 50;
        if (strpos($movie['date'] ?? '', $search_query) !== false) $score += 30;
        if ((isset($movie['quality']) && stripos($movie['quality'], $search_query) !== false) || (isset($movie['language']) && stripos($movie['language'], $search_query) !== false)) $score += 20;
        similar_text(strtolower($movie['movie_name']), $search_lower, $similarity);
        if ($similarity > 60) $score += $similarity;
        if ($score > 0) $results[] = ['movie' => $movie, 'score' => $score];
    }
    
    usort($results, function($a, $b) { return $b['score'] - $a['score']; });
    
    if (empty($results)) {
        sendMessage($chat_id, "🔍 <b>No results found for:</b> \"$search_query\"\n\n💡 <b>Suggestions:</b>\n• Check spelling\n• Try partial name\n• Use /listmovies to browse", null, 'HTML');
        return;
    }
    
    $total_results = count($results);
    $display_results = array_slice($results, 0, 10);
    
    $message = "🔍 <b>Search Results for:</b> \"$search_query\"\n\n📊 <b>Found:</b> $total_results movies\n\n";
    foreach ($display_results as $index => $result) {
        $movie = $result['movie'];
        $channel_name = get_channel_display_name($movie['channel'] ?? 'Unknown');
        $message .= "<b>" . ($index + 1) . ".</b> " . htmlspecialchars($movie['movie_name']) . "\n";
        $message .= "   🆔: " . ($movie['message_id'] ?? 'N/A') . " | 📁: $channel_name\n";
        $message .= "   📅: " . ($movie['date'] ?? 'N/A') . " | 🏆: " . round($result['score']) . " pts\n\n";
    }
    
    if ($total_results > 10) {
        $message .= "📄 <i>Showing 10 of $total_results results</i>\n";
        $message .= "💡 <i>Use more specific search for better results</i>\n";
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🎬 Download Top Result', 'callback_data' => 'download_' . urlencode($display_results[0]['movie']['movie_name'])],
                ['text' => '📋 View All', 'callback_data' => 'view_all_results']
            ],
            [
                ['text' => '🔍 Search Again', 'switch_inline_query_current_chat' => ''],
                ['text' => '📊 Stats', 'callback_data' => 'search_stats']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function delete_movie($chat_id, $identifier) {
    if (empty(trim($identifier))) {
        sendMessage($chat_id, "❌ <b>Usage:</b> <code>/deletemovie movie_name_or_id</code>\n\n📝 <b>Examples:</b>\n<code>/deletemovie testmovie</code>\n<code>/deletemovie 12345</code> (message ID)\n\n⚠️ <b>Warning:</b> This action cannot be undone!", null, 'HTML');
        return;
    }
    
    $all_movies = get_all_movies_from_csv();
    $deleted_movies = []; $remaining_movies = [];
    
    foreach ($all_movies as $movie) {
        if (strcasecmp(trim($movie['movie_name']), trim($identifier)) === 0 || (is_numeric($identifier) && $movie['message_id'] == $identifier)) {
            $deleted_movies[] = $movie;
        } else $remaining_movies[] = $movie;
    }
    
    if (empty($deleted_movies)) {
        sendMessage($chat_id, "❌ <b>Movie not found:</b> \"$identifier\"\n\n💡 <b>Suggestions:</b>\n• Use /findmovie to locate movie\n• Check exact spelling\n• Use message ID for exact match", null, 'HTML');
        return;
    }
    
    $confirm_msg = "⚠️ <b>Delete Confirmation</b>\n\n🎬 <b>Movies to delete:</b> " . count($deleted_movies) . "\n\n";
    foreach (array_slice($deleted_movies, 0, 3) as $movie) {
        $confirm_msg .= "• " . htmlspecialchars($movie['movie_name']) . "\n  🆔: " . $movie['message_id'] . " | 📅: " . $movie['date'] . "\n\n";
    }
    
    if (count($deleted_movies) > 3) $confirm_msg .= "• ... and " . (count($deleted_movies) - 3) . " more\n\n";
    $confirm_msg .= "📊 <b>Remaining movies:</b> " . count($remaining_movies) . "\n\n❓ <b>Are you sure?</b> This cannot be undone!";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '✅ Yes, Delete Now', 'callback_data' => 'delete_confirm_' . urlencode($identifier)],
                ['text' => '❌ Cancel', 'callback_data' => 'delete_cancel']
            ]
        ]
    ];
    
    sendMessage($chat_id, $confirm_msg, $keyboard, 'HTML');
}

function perform_delete_movie($chat_id, $identifier) {
    $all_movies = get_all_movies_from_csv();
    $deleted_movies = []; $remaining_movies = [];
    
    foreach ($all_movies as $movie) {
        if (strcasecmp(trim($movie['movie_name']), trim($identifier)) === 0 || (is_numeric($identifier) && $movie['message_id'] == $identifier)) {
            $deleted_movies[] = $movie;
        } else $remaining_movies[] = $movie;
    }
    
    save_movies_to_csv($remaining_movies);
    
    $result_msg = "✅ <b>Deletion Complete!</b>\n\n🗑️ <b>Deleted movies:</b> " . count($deleted_movies) . "\n📊 <b>Remaining movies:</b> " . count($remaining_movies) . "\n\n";
    
    if (!empty($deleted_movies)) {
        $result_msg .= "🎬 <b>Deleted items:</b>\n";
        foreach (array_slice($deleted_movies, 0, 5) as $movie) {
            $result_msg .= "• " . htmlspecialchars($movie['movie_name']) . " (ID: " . $movie['message_id'] . ")\n";
        }
        if (count($deleted_movies) > 5) $result_msg .= "• ... and " . (count($deleted_movies) - 5) . " more\n";
        update_stats('total_movies', -count($deleted_movies));
        log_admin_action('deletion', "delete_movies", "Deleted " . count($deleted_movies) . " movies");
    }
    
    sendMessage($chat_id, $result_msg, null, 'HTML');
}

function show_admin_stats($chat_id) {
    sendChatAction($chat_id, 'typing');
    usleep(1000000);
    
    $all_movies = get_all_movies_from_csv();
    $total_movies = count($all_movies);
    
    $channel_stats = []; $date_stats = []; $recent_movies = [];
    $today = date('d-m-Y'); $week_ago = date('d-m-Y', strtotime('-7 days'));
    
    foreach ($all_movies as $movie) {
        $channel = $movie['channel'] ?? 'Unknown';
        $channel_stats[$channel] = ($channel_stats[$channel] ?? 0) + 1;
        $date = $movie['date'] ?? 'Unknown';
        $date_stats[$date] = ($date_stats[$date] ?? 0) + 1;
        if ($date >= $week_ago) $recent_movies[] = $movie;
    }
    
    arsort($channel_stats);
    $top_channels = array_slice($channel_stats, 0, 5, true);
    krsort($date_stats);
    $recent_dates = array_slice($date_stats, 0, 7, true);
    
    $message = "📊 <b>Admin Statistics</b>\n\n🎬 <b>Overview:</b>\n• Total movies: $total_movies\n• Total channels: " . count($channel_stats) . "\n• Total dates: " . count($date_stats) . "\n• Last 7 days: " . count($recent_movies) . " movies\n\n";
    
    $message .= "📁 <b>Top Channels:</b>\n";
    foreach ($top_channels as $channel => $count) {
        $channel_name = get_channel_display_name($channel);
        $percentage = round(($count / $total_movies) * 100, 1);
        $message .= "• $channel_name: $count ($percentage%)\n";
    }
    
    $message .= "\n📅 <b>Recent Activity (Last 7 days):</b>\n";
    foreach ($recent_dates as $date => $count) $message .= "• $date: $count movies\n";
    
    $message .= "\n📈 <b>Daily Average:</b> " . round($total_movies / max(1, count($date_stats)), 2) . " movies/day\n";
    $message .= "🔥 <b>Peak Day:</b> " . max($date_stats) . " movies\n";
    $message .= "\n💾 <b>File Information:</b>\n• CSV size: " . format_bytes(filesize(CSV_FILE)) . "\n• Last modified: " . date('Y-m-d H:i:s', filemtime(CSV_FILE)) . "\n• Total lines: " . ($total_movies + 1) . "\n";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Export Data', 'callback_data' => 'export_admin_data'],
                ['text' => '🔄 Refresh', 'callback_data' => 'refresh_admin_stats']
            ],
            [
                ['text' => '📊 Channel Stats', 'callback_data' => 'channel_stats_detail'],
                ['text' => '📅 Date Stats', 'callback_data' => 'date_stats_detail']
            ],
            [
                ['text' => '🧹 Cleanup', 'callback_data' => 'admin_cleanup'],
                ['text' => '📋 Backups', 'callback_data' => 'view_backups']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function format_bytes($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    elseif ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    elseif ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    else return $bytes . ' bytes';
}

// ==============================
// HEADER COMMANDS HANDLER
// ==============================
function handle_header_commands($chat_id, $user_id, $text) {
    $parts = explode(' ', $text);
    $command = $parts[0];
    
    switch ($command) {
        case '/hideheader':
            set_user_preference($user_id, 'hide_header', true);
            sendMessage($chat_id, "✅ <b>Channel Header Hiding ENABLED</b>\n\nMovies will now be delivered without showing channel information.\nUses Telegram's copyMessage feature for best results.\n\n🔧 Status: ACTIVE\n\n💡 Use /showheader to disable", null, 'HTML');
            break;
            
        case '/showheader':
            set_user_preference($user_id, 'hide_header', false);
            sendMessage($chat_id, "🔗 <b>Channel Header SHOWING Enabled</b>\n\nMovies will now show original channel information when forwarded.\nThis is Telegram's default forwarding behavior.\n\n🔧 Status: DISABLED\n\n💡 Use /hideheader to enable again", null, 'HTML');
            break;
            
        case '/headerstatus':
            $prefs = get_user_preferences($user_id);
            $status = $prefs['hide_header'] ? "ENABLED ✅" : "DISABLED ❌";
            $message = "🔒 <b>Header Hiding Status</b>\n\n• Current status: <b>{$status}</b>\n• User ID: <code>{$user_id}</code>\n\n";
            if ($prefs['hide_header']) {
                $message .= "📋 <b>How it works:</b>\n1. Uses copyMessage API\n2. No channel info shown\n3. Silent delivery\n4. Works with all media types\n\n⚡ <b>Features:</b>\n• Batch downloads supported\n• Progress tracking\n• Auto-fallback if fails\n";
            } else {
                $message .= "📋 <b>Current mode:</b>\n• Shows channel header\n• Standard forwarding\n• Original message info visible\n\n💡 Use /hideheader to enable hidden mode";
            }
            $message .= "\n\n🔧 <b>Available Commands:</b>\n• /hideheader - Enable hiding\n• /showheader - Disable hiding\n• /silentmode - Toggle notifications\n• /preferences - All settings";
            sendMessage($chat_id, $message, null, 'HTML');
            break;
            
        case '/silentmode':
            $prefs = get_user_preferences($user_id);
            $new_value = !($prefs['silent_mode'] ?? false);
            set_user_preference($user_id, 'silent_mode', $new_value);
            $status = $new_value ? "ENABLED 🔕" : "DISABLED 🔊";
            sendMessage($chat_id, "🔕 <b>Silent Mode: {$status}</b>\n\nMovies will be delivered " . ($new_value ? "without" : "with") . " notification sound.\n\n⚙️ Works with both header modes.", null, 'HTML');
            break;
            
        case '/preferences':
            $prefs = get_user_preferences($user_id);
            $message = "⚙️ <b>Your Preferences</b>\n\n👤 User ID: <code>{$user_id}</code>\n📅 Last updated: " . date('Y-m-d H:i:s') . "\n\n";
            $message .= "🔒 <b>Header Hiding:</b> " . ($prefs['hide_header'] ? "✅ ENABLED" : "❌ DISABLED") . "\n";
            $message .= "🔕 <b>Silent Mode:</b> " . (($prefs['silent_mode'] ?? false) ? "✅ ENABLED" : "❌ DISABLED") . "\n";
            $message .= "🎬 <b>Auto-download:</b> " . (($prefs['auto_download'] ?? false) ? "✅ ENABLED" : "❌ DISABLED") . "\n";
            $message .= "🔔 <b>Notifications:</b> " . (($prefs['notifications'] ?? true) ? "✅ ENABLED" : "❌ DISABLED") . "\n\n";
            $message .= "📋 <b>Quick Actions:</b>\n• /hideheader - Toggle header hiding\n• /silentmode - Toggle sound\n• /togglenotifications - Toggle notifications\n• /resetpreferences - Reset to defaults\n• /headerhelp - Privacy help guide";
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => ($prefs['hide_header'] ? '❌ Show Headers' : '✅ Hide Headers'), 'callback_data' => 'toggle_header']],
                    [['text' => (($prefs['silent_mode'] ?? false) ? '🔔 Enable Sound' : '🔕 Silent Mode'), 'callback_data' => 'toggle_silent']],
                    [['text' => (($prefs['notifications'] ?? true) ? '🚫 Disable Notifs' : '🔔 Enable Notifs'), 'callback_data' => 'toggle_notifs']]
                ]
            ];
            
            sendMessage($chat_id, $message, $keyboard, 'HTML');
            break;
            
        case '/togglenotifications':
            $prefs = get_user_preferences($user_id);
            $new_value = !($prefs['notifications'] ?? true);
            set_user_preference($user_id, 'notifications', $new_value);
            $status = $new_value ? "ENABLED 🔔" : "DISABLED 🚫";
            sendMessage($chat_id, "🔔 <b>Notifications: {$status}</b>\n\nBot notifications are now " . ($new_value ? "enabled" : "disabled") . ".\nThis affects updates, alerts, and announcements.", null, 'HTML');
            break;
            
        case '/resetpreferences':
            $default_prefs = ['hide_header' => true, 'silent_mode' => false, 'auto_download' => false, 'notifications' => true];
            if (file_exists(USER_PREFERENCES_FILE)) {
                $preferences = json_decode(file_get_contents(USER_PREFERENCES_FILE), true);
                $preferences[$user_id] = $default_prefs;
                file_put_contents(USER_PREFERENCES_FILE, json_encode($preferences, JSON_PRETTY_PRINT));
            }
            sendMessage($chat_id, "🔄 <b>Preferences Reset to Defaults</b>\n\nAll your settings have been reset:\n• Header hiding: ✅ ENABLED\n• Silent mode: ❌ DISABLED\n• Notifications: ✅ ENABLED\n\nUse /preferences to customize again.", null, 'HTML');
            break;
            
        case '/headerhelp':
            $message = "📚 <b>Header Hiding Help</b>\n\n🔒 <b>What is header hiding?</b>\nHides the original channel info when forwarding movies.\n\n⚡ <b>Benefits:</b>\n• Cleaner appearance\n• No channel spam\n• Professional look\n• Better user experience\n\n🔧 <b>How it works:</b>\n1. Uses Telegram's copyMessage API\n2. Creates duplicate without source info\n3. Preserves all media quality\n4. Falls back if unavailable\n\n📋 <b>Available Commands:</b>\n• /hideheader - Enable hiding\n• /showheader - Disable hiding\n• /headerstatus - Check status\n• /silentmode - Toggle sound\n• /preferences - All settings\n• /headerhelp - This help\n\n💡 <b>Pro tip:</b> Works best when bot is admin in movie channel.";
            sendMessage($chat_id, $message, null, 'HTML');
            break;
    }
}

// ==============================
// NETWORK COMMANDS
// ==============================
function handle_network_command($chat_id, $command) {
    switch ($command) {
        case '/netinfo':
        case '/network':
        case '/channels':
            show_all_channels_info($chat_id);
            break;
            
        case '/channelstats':
        case '/chstats':
            channel_stats($chat_id);
            break;
            
        case '/botinfo':
            show_bot_info($chat_id);
            break;
            
        default:
            sendMessage($chat_id, "❌ <b>Unknown Network Command</b>\n\n📡 <b>Available Network Commands:</b>\n• /netinfo - Complete network details\n• /channelstats - Channel statistics\n• /botinfo - Bot information\n\n🎬 <b>Quick Links:</b>\n• Main: " . MAIN_CHANNEL . "\n• Theater: " . THEATER_PRINTS_CHANNEL . "\n• Requests: " . REQUEST_GROUP, null, 'HTML');
    }
}

function show_all_channels_info($chat_id) {
    $message = "📡 <b>Entertainment Tadka Network</b>\n\n🤖 <b>Bot Information:</b>\n• Name: " . BOT_USERNAME . "\n• ID: " . BOT_ID . "\n• Developer: " . DEVELOPER . "\n• Owner ID: " . OWNER_ID . "\n\n🎬 <b>Channel Network:</b>\n\n";
    
    global $CHANNEL_CATEGORIES;
    foreach ($CHANNEL_CATEGORIES as $category => $cat_info) {
        $message .= "<b>" . $cat_info['name'] . "</b>\n<i>" . $cat_info['description'] . "</i>\n";
        foreach ($cat_info['channels'] as $channel) {
            $info = get_channel_info($channel);
            $message .= "  " . $info['icon'] . " <b>" . $info['title'] . "</b>\n";
            if (isset($info['id'])) $message .= "     ID: <code>" . $info['id'] . "</code>\n";
            if (strpos($channel, '@') === 0) {
                $message .= "     Username: " . $channel . "\n";
                if (isset($info['url'])) $message .= "     URL: " . $info['url'] . "\n";
            } else $message .= "     Channel ID: <code>" . $channel . "</code>\n";
            $message .= "\n";
        }
    }
    
    $stats = get_stats();
    if (isset($stats['channels'])) {
        $message .= "📊 <b>Channel Statistics:</b>\n";
        foreach ($stats['channels'] as $channel => $count) {
            if ($count > 0) {
                $channel_name = get_channel_display_name($channel);
                $message .= "• " . $channel_name . ": " . $count . " movies\n";
            }
        }
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🍿 Main Channel', 'url' => MAIN_CHANNEL_URL],
                ['text' => '🎭 Theater Prints', 'url' => THEATER_PRINTS_URL]
            ],
            [
                ['text' => '📥 Request Group', 'url' => REQUEST_GROUP_URL],
                ['text' => '🛡️ Backup Channel', 'url' => ET_BACKUP_URL]
            ],
            [
                ['text' => '🤖 Bot Link', 'url' => BOT_URL],
                ['text' => '📊 Network Stats', 'callback_data' => 'network_stats']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function channel_stats($chat_id) {
    $message = "📡 <b>Entertainment Tadka Network</b>\n\n";
    
    global $CHANNEL_PRIORITY;
    $message .= "🎯 <b>Channel Priority Order:</b>\n";
    $count = 1;
    foreach ($CHANNEL_PRIORITY as $channel) {
        $display_name = get_channel_display_name($channel);
        $message .= "$count. $display_name\n";
        $count++;
    }
    
    $message .= "\n📊 <b>Channel Statistics:</b>\n";
    $stats = get_stats();
    $total_movies = $stats['total_movies'] ?? 0;
    
    if (isset($stats['channels'])) {
        foreach ($stats['channels'] as $channel_id => $movie_count) {
            if ($movie_count > 0) {
                $display_name = get_channel_display_name($channel_id);
                $percentage = ($total_movies > 0) ? round(($movie_count / $total_movies) * 100, 1) : 0;
                $message .= "• $display_name: $movie_count movies ($percentage%)\n";
            }
        }
    }
    
    $message .= "\n🎬 <b>Total Movies in Database:</b> $total_movies\n";
    $message .= "🕒 <b>Last Updated:</b> " . ($stats['last_updated'] ?? 'N/A') . "\n";
    $message .= "🤖 <b>Bot Status:</b> ✅ Online\n\n";
    $message .= "🔗 <b>Quick Links:</b>\n• Main Channel: " . MAIN_CHANNEL . "\n• Theater Prints: " . THEATER_PRINTS_CHANNEL . "\n• Request Group: " . REQUEST_GROUP . "\n• Backup Channel: " . ET_BACKUP_CHANNEL . "\n\n";
    $message .= "💡 <b>Tip:</b> Use /netinfo for complete network details";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📡 Network Info', 'callback_data' => 'network_info'],
                ['text' => '📊 Detailed Stats', 'callback_data' => 'detailed_stats']
            ],
            [
                ['text' => '🍿 Main Channel', 'url' => MAIN_CHANNEL_URL],
                ['text' => '🎭 Theater Prints', 'url' => THEATER_PRINTS_URL]
            ],
            [
                ['text' => '📥 Request Group', 'url' => REQUEST_GROUP_URL],
                ['text' => '🛡️ Backup Channel', 'url' => ET_BACKUP_URL]
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_bot_info($chat_id) {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $total_users = count($users_data['users'] ?? []);
    
    $message = "🤖 <b>Entertainment Tadka Bot</b>\n\n📊 <b>Bot Information:</b>\n• Username: " . BOT_USERNAME . "\n• Bot ID: " . BOT_ID . "\n• Owner ID: " . OWNER_ID . "\n• Developer: " . DEVELOPER . "\n• Status: ✅ Online\n\n";
    
    $message .= "📈 <b>Statistics:</b>\n• Total Movies: " . ($stats['total_movies'] ?? 0) . "\n• Total Users: " . $total_users . "\n• Total Searches: " . ($stats['total_searches'] ?? 0) . "\n• Total Downloads: " . ($stats['total_downloads'] ?? 0) . "\n• Last Updated: " . ($stats['last_updated'] ?? 'N/A') . "\n\n";
    
    $message .= "⚙️ <b>System Information:</b>\n• PHP Version: " . PHP_VERSION . "\n• Server Time: " . date('Y-m-d H:i:s') . "\n• CSV File: " . filesize(CSV_FILE) . " bytes\n• Uptime: " . get_bot_uptime() . "\n\n";
    
    $message .= "🔗 <b>Bot Link:</b> " . BOT_URL . "\n📝 <b>Commands:</b> /help for all commands";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🤖 Bot Link', 'url' => BOT_URL],
                ['text' => '📡 Network Info', 'callback_data' => 'network_info']
            ],
            [
                ['text' => '📊 Bot Stats', 'callback_data' => 'refresh_bot_stats'],
                ['text' => '🔄 Refresh', 'callback_data' => 'refresh_info']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function get_bot_uptime() {
    if (!file_exists(STATS_FILE)) return "Unknown";
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $created = $stats['created_at'] ?? date('Y-m-d H:i:s');
    $created_time = strtotime($created);
    $current_time = time();
    $diff = $current_time - $created_time;
    $days = floor($diff / (60 * 60 * 24));
    $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
    $minutes = floor(($diff % (60 * 60)) / 60);
    if ($days > 0) return "$days days, $hours hours";
    elseif ($hours > 0) return "$hours hours, $minutes minutes";
    else return "$minutes minutes";
}

// ==============================
// NOTIFICATION COMMANDS HANDLER
// ==============================
function handle_notification_commands($chat_id, $user_id, $text) {
    if ($user_id != OWNER_ID) {
        sendMessage($chat_id, "❌ <b>Access denied.</b> Admin only command.", null, 'HTML');
        return;
    }
    
    $parts = explode(' ', $text, 2);
    $command = $parts[0];
    $params = isset($parts[1]) ? $parts[1] : '';
    
    switch ($command) {
        case '/sendnotification':
            if (empty($params)) {
                sendMessage($chat_id, "❌ <b>Usage:</b> <code>/sendnotification message_text</code>", null, 'HTML');
                return;
            }
            send_special_announcement($params);
            sendMessage($chat_id, "✅ <b>Notification sent to all active users!</b>", null, 'HTML');
            break;
            
        case '/testnotification':
            $test_message = "🔔 <b>Test Notification</b>\n\nThis is a test notification.\nTime: " . date('H:i:s');
            sendMessage(OWNER_ID, $test_message, null, 'HTML');
            sendMessage($chat_id, "✅ <b>Test notification sent to admin!</b>", null, 'HTML');
            break;
            
        case '/notificationstats':
            $log_data = load_notification_log();
            $today = date('Y-m-d');
            $stats_message = "📊 <b>Notification Statistics</b>\n\n📅 <b>Today's Notifications:</b> ($today)\n";
            if (isset($log_data[$today])) {
                foreach ($log_data[$today] as $notification) $stats_message .= "• {$notification['type']} at {$notification['time']}\n";
            } else $stats_message .= "• None sent today\n";
            
            $stats_message .= "\n📈 <b>Last 7 days:</b>\n";
            $seven_days = [];
            foreach ($log_data as $date => $notifications) {
                if (strtotime($date) >= strtotime('-7 days')) $seven_days[$date] = count($notifications);
            }
            if (empty($seven_days)) $stats_message .= "• No notifications in last 7 days\n";
            else foreach ($seven_days as $date => $count) $stats_message .= "• $date: <b>$count</b> notifications\n";
            sendMessage($chat_id, $stats_message, null, 'HTML');
            break;
    }
}

// ==============================
// MAIN COMMAND HANDLER
// ==============================
function handle_command($chat_id, $user_id, $text) {
    $parts = explode(' ', $text, 2);
    $command = strtolower($parts[0]);
    $params = isset($parts[1]) ? explode(' ', $parts[1]) : [];
    
    if ($command == '/start') {
        $welcome = "🎬 <b>Welcome to Entertainment Tadka!</b>\n\n📢 <b>How to use this bot:</b>\n• Simply type any movie name\n• Use English or Hindi\n• Partial names also work\n\n🔍 <b>Examples:</b>\n• kgf\n• pushpa\n• avengers\n• hindi movie\n• spider-man\n\n❌ <b>Don't type:</b>\n• Technical questions\n• Player instructions\n• Non-movie queries\n\n📢 <b>Join Our Channels:</b>\n🍿 Main Movies: " . MAIN_CHANNEL . "\n🎭 Theater Prints: " . THEATER_PRINTS_CHANNEL . "\n📥 Requests: " . REQUEST_GROUP . "\n🔒 Backup: " . ET_BACKUP_CHANNEL . "\n\n💬 <b>Need help?</b> Use /help for all commands";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => ''], ['text' => '🍿 Main Channel', 'url' => MAIN_CHANNEL_URL]],
                [['text' => '🎭 Theater Prints', 'url' => THEATER_PRINTS_URL], ['text' => '📥 Requests', 'url' => REQUEST_GROUP_URL]],
                [['text' => '🛡️ Backup', 'url' => ET_BACKUP_URL], ['text' => '📊 My Stats', 'callback_data' => 'my_stats']],
                [['text' => '📡 All Channels', 'callback_data' => 'network_info'], ['text' => '❓ Help', 'callback_data' => 'help_command']]
            ]
        ];
        
        sendMessage($chat_id, $welcome, $keyboard, 'HTML');
        update_user_activity($user_id, 'daily_login');
    }
    elseif (in_array($command, ['/help', '/commands'])) {
        $help = "🤖 <b>Entertainment Tadka Bot - Complete Guide</b>\n\n📢 <b>Our Channels:</b>\n🍿 Main: @EntertainmentTadka786 - Latest movies\n📥 Requests: @EntertainmentTadka7860 - Support & requests\n🔒 Backup: @ETBackup - Data protection\n\n🎯 <b>Search Commands:</b>\n• Just type movie name - Smart search\n• <code>/search movie</code> - Direct search\n• <code>/s movie</code> - Quick search\n\n📁 <b>Browse Commands:</b>\n• <code>/totaluploads</code> - All movies\n• <code>/checkdate</code> - Date-wise stats\n• <code>/testcsv</code> - View all movies\n\n🔒 <b>Privacy Commands:</b>\n• <code>/hideheader</code> - Hide channel info\n• <code>/showheader</code> - Show channel info\n• <code>/silentmode</code> - Toggle sound\n• <code>/preferences</code> - All settings\n\n👤 <b>User Commands:</b>\n• <code>/headerstatus</code> - Check privacy status\n• <code>/togglenotifications</code> - Toggle alerts\n• <code>/headerhelp</code> - Privacy help\n\n🛠️ <b>Admin Commands:</b>\n• <code>/stats</code> - Bot statistics\n• <code>/sendnotification</code> - Broadcast\n• <code>/notificationstats</code> - Notification stats\n\n📡 <b>Network Commands:</b>\n• <code>/netinfo</code> - Complete network details\n• <code>/channelstats</code> - Channel statistics\n• <code>/botinfo</code> - Bot information\n\n💡 <b>Pro Tips:</b>\n• Use partial names (e.g., 'aveng')\n• Enable hideheader for cleaner view\n• Check /checkdate for upload stats\n• Report issues with /reportbug";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🍿 Main Channel', 'url' => MAIN_CHANNEL_URL], ['text' => '📥 Request Group', 'url' => REQUEST_GROUP_URL]],
                [['text' => '🔒 Backup Channel', 'url' => ET_BACKUP_URL], ['text' => '🎬 Search Movies', 'switch_inline_query_current_chat' => '']]
            ]
        ];
        
        sendMessage($chat_id, $help, $keyboard, 'HTML');
    }
    elseif (in_array($command, ['/search', '/s', '/find'])) {
        $movie_name = isset($parts[1]) ? $parts[1] : '';
        if (empty($movie_name)) {
            sendMessage($chat_id, "❌ Usage: <code>/search movie_name</code>\nExample: <code>/search kgf 2</code>", null, 'HTML');
            return;
        }
        $lang = detect_language($movie_name);
        send_multilingual_response($chat_id, 'searching', $lang);
        advanced_search($chat_id, $movie_name);
    }
    elseif ($command == '/totaluploads') {
        $page = isset($params[0]) ? intval($params[0]) : 1;
        total_uploads($chat_id, $page);
    }
    elseif ($command == '/checkdate') {
        check_date($chat_id);
    }
    elseif ($command == '/testcsv') {
        test_csv($chat_id);
    }
    elseif (in_array($command, ['/netinfo', '/network', '/channels', '/channelstats', '/chstats', '/botinfo'])) {
        handle_network_command($chat_id, $command);
    }
    elseif (in_array($command, ['/addmovie', '/quickadd', '/listmovies', '/findmovie', '/deletemovie', '/adminstats'])) {
        handle_admin_commands($chat_id, $user_id, $text);
    }
    elseif (in_array($command, ['/hideheader', '/showheader', '/headerstatus', '/silentmode', '/preferences', '/togglenotifications', '/resetpreferences', '/headerhelp'])) {
        handle_header_commands($chat_id, $user_id, $text);
    }
    elseif (in_array($command, ['/sendnotification', '/testnotification', '/notificationstats'])) {
        handle_notification_commands($chat_id, $user_id, $text);
    }
    elseif (in_array($command, ['/ping', '/status'])) {
        sendMessage($chat_id, "🏓 <b>Bot Status:</b> ✅ Online\n⏰ <b>Server Time:</b> " . date('Y-m-d H:i:s'), null, 'HTML');
    }
    elseif (in_array($command, ['/report', '/reportbug'])) {
        $bug_report = isset($parts[1]) ? $parts[1] : '';
        if (empty($bug_report)) {
            sendMessage($chat_id, "❌ <b>Usage:</b> <code>/report bug_description</code>", null, 'HTML');
            return;
        }
        $report_message = "🐛 <b>Bug Report</b>\n\nUser ID: <code>{$user_id}</code>\nTime: " . date('Y-m-d H:i:s') . "\nReport: {$bug_report}\n\nThanks for reporting!";
        sendMessage($chat_id, $report_message, null, 'HTML');
        if (OWNER_ID) sendMessage(OWNER_ID, "⚠️ <b>New Bug Report</b>\n\nFrom User: <code>{$user_id}</code>\nReport: {$bug_report}", null, 'HTML');
    }
    elseif ($command == '/feedback') {
        $feedback = isset($parts[1]) ? $parts[1] : '';
        if (empty($feedback)) {
            sendMessage($chat_id, "❌ <b>Usage:</b> <code>/feedback your_feedback</code>", null, 'HTML');
            return;
        }
        $feedback_message = "💬 <b>Feedback Received</b>\n\nUser ID: <code>{$user_id}</code>\nTime: " . date('Y-m-d H:i:s') . "\nFeedback: {$feedback}\n\nThank you for your feedback!";
        sendMessage($chat_id, $feedback_message, null, 'HTML');
        if (OWNER_ID) sendMessage(OWNER_ID, "📝 <b>New Feedback</b>\n\nFrom User: <code>{$user_id}</code>\nFeedback: {$feedback}", null, 'HTML');
    }
    elseif ($command == '/stats' && $user_id == OWNER_ID) {
        admin_stats($chat_id);
    }
    else {
        sendMessage($chat_id, "❌ <b>Unknown command.</b>\n\nAvailable commands:\n• /start - Welcome message\n• /help - All commands\n• /search [movie] - Search movies\n• /totaluploads - View all movies\n• /checkdate - Upload stats\n\n🔒 <b>Privacy commands:</b>\n• /hideheader - Hide channel info\n• /showheader - Show channel info\n• /headerstatus - Check status\n\n📡 <b>Network commands:</b>\n• /netinfo - All channels info\n• /botinfo - Bot information", null, 'HTML');
    }
}

// ==============================
// MAIN UPDATE PROCESSING
// ==============================
$update = json_decode(file_get_contents('php://input'), true);
if ($update) {
    get_cached_movies();
    
    if (isset($update['channel_post'])) {
        $message = $update['channel_post'];
        $message_id = $message['message_id'];
        $text = isset($message['text']) ? $message['text'] : (isset($message['caption']) ? $message['caption'] : '');
        if (!empty(trim($text))) append_movie($text, $message_id);
    }
    
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        
        if (!empty(trim($text))) sendChatAction($chat_id, 'typing');
        
        $users_data = json_decode(file_get_contents(USERS_FILE), true);
        if (!isset($users_data['users'][$user_id])) {
            sendChatAction($chat_id, 'typing');
            usleep(1000000);
            
            $users_data['users'][$user_id] = [
                'first_name' => $message['from']['first_name'] ?? '',
                'last_name' => $message['from']['last_name'] ?? '',
                'username' => $message['from']['username'] ?? '',
                'joined' => date('Y-m-d H:i:s'),
                'last_active' => date('Y-m-d H:i:s'),
                'points' => 0,
                'search_count' => 0
            ];
            $users_data['total_requests'] = ($users_data['total_requests'] ?? 0) + 1;
            file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
            update_stats('total_users', 1);
        }
        
        $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
        file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
        
        if (strpos($text, '/') === 0) {
            $command = explode(' ', $text)[0];
            $delay_map = [
                '/start' => 800, '/help' => 600, '/totaluploads' => 1200, '/checkdate' => 1000,
                '/testcsv' => 1500, '/stats' => 800, '/hideheader' => 400, '/showheader' => 400,
                '/headerstatus' => 500, '/preferences' => 700, '/netinfo' => 900, '/botinfo' => 800
            ];
            $delay = $delay_map[$command] ?? 300;
            usleep($delay * 1000);
            
            if ($command == '/checkdate') check_date($chat_id);
            elseif ($command == '/totaluploads') total_uploads($chat_id);
            elseif ($command == '/testcsv') test_csv($chat_id);
            elseif ($command == '/stats' && $user_id == OWNER_ID) admin_stats($chat_id);
            else handle_command($chat_id, $user_id, $text);
        } elseif (!empty(trim($text))) {
            sendChatAction($chat_id, 'typing');
            usleep(600000);
            $language = detect_language($text);
            send_multilingual_response($chat_id, 'searching', $language);
            usleep(400000);
            advanced_search($chat_id, $text);
            if (isset($users_data['users'][$user_id])) {
                $users_data['users'][$user_id]['search_count'] = ($users_data['users'][$user_id]['search_count'] ?? 0) + 1;
                file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
            }
        }
    }
    
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $message = $query['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $query['from']['id'];
        $data = $query['data'];
        
        sendChatAction($chat_id, 'typing');
        usleep(300000);
        
        if (in_array($data, ['toggle_header', 'toggle_silent', 'toggle_notifs'])) {
            switch ($data) {
                case 'toggle_header':
                    $prefs = get_user_preferences($user_id);
                    $new_value = !$prefs['hide_header'];
                    set_user_preference($user_id, 'hide_header', $new_value);
                    answerCallbackQuery($query['id'], $new_value ? "✅ Headers will be hidden" : "🔗 Headers will show");
                    break;
                case 'toggle_silent':
                    $prefs = get_user_preferences($user_id);
                    $new_value = !($prefs['silent_mode'] ?? false);
                    set_user_preference($user_id, 'silent_mode', $new_value);
                    answerCallbackQuery($query['id'], $new_value ? "🔕 Silent mode enabled" : "🔔 Sound enabled");
                    break;
                case 'toggle_notifs':
                    $prefs = get_user_preferences($user_id);
                    $new_value = !($prefs['notifications'] ?? true);
                    set_user_preference($user_id, 'notifications', $new_value);
                    answerCallbackQuery($query['id'], $new_value ? "🔔 Notifications enabled" : "🚫 Notifications disabled");
                    break;
            }
            $command = '/preferences';
            handle_command($chat_id, $user_id, $command);
        }
        elseif (strpos($data, 'list_page_') === 0) {
            $page = intval(str_replace('list_page_', '', $data));
            list_movies($chat_id, $page);
            answerCallbackQuery($query['id'], "📄 Loading page $page");
        }
        elseif (strpos($data, 'delete_confirm_') === 0) {
            $identifier = urldecode(str_replace('delete_confirm_', '', $data));
            perform_delete_movie($chat_id, $identifier);
            answerCallbackQuery($query['id'], "🗑️ Movie deleted");
        }
        elseif ($data == 'delete_cancel') {
            sendMessage($chat_id, "❌ Deletion cancelled.", null, 'HTML');
            answerCallbackQuery($query['id'], "❌ Cancelled");
        }
        elseif ($data == 'show_stats' || $data == 'search_stats') {
            show_admin_stats($chat_id);
            answerCallbackQuery($query['id'], "📊 Loading stats");
        }
        elseif ($data == 'refresh_list' || $data == 'refresh_admin_stats' || $data == 'refresh_info') {
            sendChatAction($chat_id, 'typing');
            usleep(500000);
            if ($data == 'refresh_list') list_movies($chat_id, 1);
            elseif ($data == 'refresh_admin_stats') show_admin_stats($chat_id);
            elseif ($data == 'refresh_info') show_bot_info($chat_id);
            answerCallbackQuery($query['id'], "🔄 Refreshed");
        }
        elseif (in_array($data, ['show_csv_format', 'export_csv', 'refresh_list', 'channel_stats_detail'])) {
            handle_csv_callbacks($chat_id, $data);
            answerCallbackQuery($query['id'], "Processing...");
        }
        elseif ($data == 'network_info' || $data == 'network_stats') {
            show_all_channels_info($chat_id);
            answerCallbackQuery($query['id'], "📡 Loading network info...");
        }
        elseif ($data == 'detailed_stats') {
            channel_stats($chat_id);
            answerCallbackQuery($query['id'], "📊 Loading detailed stats...");
        }
        elseif ($data == 'refresh_bot_stats') {
            show_bot_info($chat_id);
            answerCallbackQuery($query['id'], "🔄 Refreshing bot info...");
        }
        elseif (strpos($data, 'uploads_page_') === 0) {
            $page = intval(str_replace('uploads_page_', '', $data));
            total_uploads($chat_id, $page);
            answerCallbackQuery($query['id'], "Page $page loaded");
        }
        elseif (strpos($data, 'batch_confirm_') === 0) {
            $page = intval(str_replace('batch_confirm_', '', $data));
            $items_per_page = 5;
            $all_movies = get_all_movies_from_csv();
            $all_movies = array_reverse($all_movies);
            $total_pages = ceil(count($all_movies) / $items_per_page);
            $page = max(1, min($page, $total_pages));
            $start_index = ($page - 1) * $items_per_page;
            $movies = array_slice($all_movies, $start_index, $items_per_page);
            answerCallbackQuery($query['id'], "📦 Starting batch download...");
            batch_deliver_hidden($chat_id, $movies, $page);
        }
        elseif ($data == 'batch_cancel') {
            answerCallbackQuery($query['id'], "❌ Batch download cancelled");
            sendMessage($chat_id, "❌ Batch download cancelled by user.", null, 'Markdown');
        }
        else {
            global $movie_messages;
            $movie_lower = strtolower($data);
            if (isset($movie_messages[$movie_lower])) {
                $message_count = count($movie_messages[$movie_lower]);
                foreach ($movie_messages[$movie_lower] as $msg_id) {
                    $movie_data = ['movie_name' => $data, 'message_id' => $msg_id, 'date' => date('d-m-Y')];
                    $user_prefs = get_user_preferences($chat_id);
                    $prefer_hidden = $user_prefs['hide_header'] ?? true;
                    if ($prefer_hidden) deliver_movie_hidden($chat_id, $movie_data);
                    else forwardMessage($chat_id, MAIN_CHANNEL, $msg_id);
                    usleep(500000);
                }
                $forward_msg = "✅ '$data' ke $message_count messages deliver ho gaye!\n\n📢 Join our channel: @EntertainmentTadka786";
                sendMessage($chat_id, $forward_msg);
                answerCallbackQuery($query['id'], "🎬 $message_count messages delivered!");
            } else {
                sendMessage($chat_id, "❌ Movie not found: " . $data);
                answerCallbackQuery($query['id'], "❌ Movie not available");
            }
        }
    }
    
    if (date('H:i') == '18:00' || date('H:i') == '12:00') schedule_notifications();
    if (date('H:i') == '00:00') auto_backup();
    if (date('H:i') == '08:00') send_daily_digest();
}

if (!$update) {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    echo "<h1>🎬 Entertainment Tadka Bot</h1>";
    echo "<p><strong>Telegram Channel:</strong> @EntertainmentTadka786</p>";
    echo "<p><strong>Status:</strong> ✅ Running</p>";
    echo "<p><strong>Total Movies:</strong> " . ($stats['total_movies'] ?? 0) . "</p>";
    echo "<p><strong>Total Users:</strong> " . count($users_data['users'] ?? []) . "</p>";
    echo "<p><strong>Total Searches:</strong> " . ($stats['total_searches'] ?? 0) . "</p>";
    echo "<p><strong>Channels:</strong> 7 channels configured</p>";
    
    echo "<h3>🚀 Quick Setup</h3>";
    echo "<p><a href='?setwebhook=1'>Set Webhook Now</a></p>";
    
    echo "<h3>📋 Available Commands</h3>";
    echo "<ul>";
    echo "<li><code>/start</code> - Welcome message with 4 channels</li>";
    echo "<li><code>/help</code> - Complete command guide</li>";
    echo "<li><code>/search movie</code> - Search movies</li>";
    echo "<li><code>/totaluploads</code> - All movies with pagination</li>";
    echo "<li><code>/checkdate</code> - Date-wise stats</li>";
    echo "<li><code>/testcsv</code> - View all movies</li>";
    echo "<li><code>/netinfo</code> - All 7 channels info</li>";
    echo "<li><code>/hideheader</code> - Hide channel info</li>";
    echo "<li><code>/showheader</code> - Show channel info</li>";
    echo "<li><code>/addmovie</code> - Admin: Add movie</li>";
    echo "<li><code>/quickadd</code> - Admin: Bulk add</li>";
    echo "<li><code>/listmovies</code> - Admin: List movies</li>";
    echo "<li><code>/findmovie</code> - Admin: Search movie</li>";
    echo "<li><code>/deletemovie</code> - Admin: Delete movie</li>";
    echo "<li><code>/adminstats</code> - Admin: Statistics</li>";
    echo "</ul>";
    
    echo "<h3>📡 Network Channels</h3>";
    echo "<ul>";
    echo "<li>🍿 Main: @EntertainmentTadka786</li>";
    echo "<li>🎭 Theater: @threater_print_movies</li>";
    echo "<li>📥 Requests: @EntertainmentTadka7860</li>";
    echo "<li>🛡️ Backup: @ETBackup</li>";
    echo "<li>🔒 Private: -1003251791991</li>";
    echo "<li>💾 Backup 2: -1002337293281</li>";
    echo "<li>🔄 Forwarded: -1003614546520</li>";
    echo "</ul>";
    
    echo "<h3>🌟 Special Features</h3>";
    echo "<ul>";
    echo "<li>🤖 AI-Powered Search</li>";
    echo "<li>🔔 Smart Notifications</li>";
    echo "<li>📊 Advanced Analytics</li>";
    echo "<li>🌐 Multi-Language Support</li>";
    echo "<li>⚡ Smart Caching</li>";
    echo "<li>🛡️ Auto-Backup System</li>";
    echo "<li>🎮 User Points System</li>";
    echo "<li>📅 Daily Digest</li>";
    echo "<li>🔒 Header Hiding System</li>";
    echo "<li>⏱️ Delay Typing Effects</li>";
    echo "<li>📡 Network Management</li>";
    echo "<li>🎬 Advanced Movie Management</li>";
    echo "</ul>";
    
    echo "<h3>📊 File Status</h3>";
    echo "<ul>";
    echo "<li>CSV File: " . (is_writable(CSV_FILE) ? "✅ Writable" : "❌ Not Writable") . "</li>";
    echo "<li>Users File: " . (is_writable(USERS_FILE) ? "✅ Writable" : "❌ Not Writable") . "</li>";
    echo "<li>Stats File: " . (is_writable(STATS_FILE) ? "✅ Writable" : "❌ Not Writable") . "</li>";
    echo "<li>CSV Format: movie_name,message_id,channel,date,quality,language,added_by,added_at</li>";
    echo "</ul>";
}
?>
