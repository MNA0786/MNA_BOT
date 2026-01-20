<?php
// ============================================
// 🎬 ENTERTAINMENT TADKA BOT - MULTI CHANNEL
// Version: 3.0 (Beautiful UI + 7 Channels)
// Author: @EntertainmentTadkaBot
// Deploy: Render.com Ready
// ============================================

// ============================================
// 🔒 SECURITY HEADERS
// ============================================

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// CORS headers for web access
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================
// 🌐 RENDER.COM SPECIFIC CONFIGURATION
// ============================================

// Render.com provides PORT environment variable
$port = getenv('PORT') ?: '80';

// Webhook URL automatically set
$webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// Security - All credentials environment variables se lo
$bot_token = getenv('BOT_TOKEN') ?: '8315381064:AAGk0FGVGmB8j5SjpBvW3rD3_kQHe_hyOWU';
define('BOT_TOKEN', $bot_token);

// Validate BOT_TOKEN
if (empty(BOT_TOKEN) || BOT_TOKEN == '8315381064:AAGk0FGVGmB8j5SjpBvW3rD3_kQHe_hyOWU') {
    die("❌ BOT_TOKEN environment variable set nahi hai. Render.com dashboard mein set karo.");
}

// ============================================
// 📋 CONFIGURATION SECTION
// ============================================

// Bot information
define('BOT_USERNAME', '@EntertainmentTadkaBot');
define('BOT_ID', '8315381064');
define('OWNER_ID', '1080317415');

// ORIGINAL CHANNEL (Legacy support)
define('MAIN_CHANNEL_ID', '-1002831038104');
define('GROUP_CHANNEL_ID', '-1002831038104');

// ============================================
// 🎯 MULTI-CHANNEL CONFIGURATION (7 CHANNELS)
// ============================================

// ALL 7 CHANNELS WITH EMOJIS AND INFO
$ALL_CHANNELS = [
    // Format: 'channel_id' => ['name' => '', 'emoji' => '', 'username' => '']
    '-1003181705395' => [
        'name' => 'Movies and Webseries',
        'emoji' => '🎬',
        'username' => '@EntertainmentTadka786',
        'type' => 'movie'
    ],
    '-1003251791991' => [
        'name' => 'Private Channel',
        'emoji' => '🔒',
        'username' => '',
        'type' => 'movie'
    ],
    '-1002337293281' => [
        'name' => 'Backup Channel 2',
        'emoji' => '💾',
        'username' => '',
        'type' => 'movie'
    ],
    '-1003614546520' => [
        'name' => 'Forwarded Channel',
        'emoji' => '🔄',
        'username' => '',
        'type' => 'movie'
    ],
    '-1002831605258' => [
        'name' => 'Threater Print',
        'emoji' => '🎭',
        'username' => '@threater_print_movies',
        'type' => 'movie'
    ],
    '-1002964109368' => [
        'name' => 'ET Backup',
        'emoji' => '📦',
        'username' => '@ETBackup',
        'type' => 'movie'
    ],
    '-1003083386043' => [
        'name' => 'Request Group',
        'emoji' => '💬',
        'username' => '@EntertainmentTadka7860',
        'type' => 'request'
    ]
];

// Special channel IDs
define('REQUEST_GROUP_ID', '-1003083386043');

// ============================================
// 📁 FILE CONFIGURATION (RENDER.COM COMPATIBLE)
// ============================================

// Use absolute paths for Render.com
$base_dir = __DIR__;
define('CSV_FILE', $base_dir . '/movies.csv');
define('USERS_FILE', $base_dir . '/users.json');
define('STATS_FILE', $base_dir . '/bot_stats.json');
define('CHANNELS_FILE', $base_dir . '/channels_tracker.json');
define('BACKUP_DIR', $base_dir . '/backups/');
define('CACHE_EXPIRY', 300); // 5 minutes
define('ITEMS_PER_PAGE', 5);

// ============================================
// 🎨 UI DESIGN CONSTANTS
// ============================================

// Main emojis
define('MAIN_EMOJI', '🎬');
define('SEARCH_EMOJI', '🔍');
define('STATS_EMOJI', '📊');
define('CHANNEL_EMOJI', '📢');
define('MOVIE_EMOJI', '🎥');
define('SUCCESS_EMOJI', '✅');
define('ERROR_EMOJI', '❌');
define('HELP_EMOJI', '❓');
define('BACKUP_EMOJI', '💾');
define('USER_EMOJI', '👤');
define('CALENDAR_EMOJI', '📅');
define('UPLOAD_EMOJI', '📤');
define('DOWNLOAD_EMOJI', '📥');
define('SETTINGS_EMOJI', '⚙️');
define('INFO_EMOJI', 'ℹ️');

// ============================================
// ⏱️ DELAY SETTINGS
// ============================================

define('TYPING_DELAY', 1); // seconds
define('MESSAGE_DELAY', 0.5); // seconds between messages
define('SEARCH_DELAY', 2); // seconds for search simulation
define('MOVIE_DELAY', 0.3); // seconds between movie forwarding

// ============================================
// 📦 FILE INITIALIZATION
// ============================================

// Initialize users file
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, json_encode([
        'users' => [], 
        'total_requests' => 0, 
        'message_logs' => [],
        'created' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT));
    @chmod(USERS_FILE, 0666);
}

// Initialize CSV file with headers
if (!file_exists(CSV_FILE)) {
    $headers = "movie_name,message_id,date,channel_id,channel_name,channel_emoji,added_timestamp\n";
    file_put_contents(CSV_FILE, $headers);
    @chmod(CSV_FILE, 0666);
}

// Initialize stats file
if (!file_exists(STATS_FILE)) {
    $initial_stats = [
        'total_movies' => 0, 
        'total_users' => 0, 
        'total_searches' => 0,
        'channels_stats' => [],
        'last_updated' => date('Y-m-d H:i:s'),
        'created' => date('Y-m-d H:i:s')
    ];
    file_put_contents(STATS_FILE, json_encode($initial_stats, JSON_PRETTY_PRINT));
    @chmod(STATS_FILE, 0666);
}

// Initialize channels tracker
if (!file_exists(CHANNELS_FILE)) {
    $channels_data = [];
    foreach ($ALL_CHANNELS as $channel_id => $channel_info) {
        $channels_data[$channel_id] = [
            'name' => $channel_info['name'],
            'emoji' => $channel_info['emoji'],
            'username' => $channel_info['username'],
            'type' => $channel_info['type'],
            'total_movies' => 0,
            'last_movie' => null,
            'last_updated' => date('Y-m-d H:i:s'),
            'created' => date('Y-m-d H:i:s')
        ];
    }
    file_put_contents(CHANNELS_FILE, json_encode($channels_data, JSON_PRETTY_PRINT));
    @chmod(CHANNELS_FILE, 0666);
}

// Create backup directory
if (!file_exists(BACKUP_DIR)) {
    @mkdir(BACKUP_DIR, 0777, true);
}

// ============================================
// 🧠 MEMORY CACHES
// ============================================

$movie_messages = array();
$movie_cache = array();
$waiting_users = array();
$user_sessions = array();

// ============================================
// 🎨 UI DESIGN FUNCTIONS
// ============================================

/**
 * Create beautiful header with title
 */
function create_header($title, $emoji = MAIN_EMOJI) {
    $border = str_repeat("═", 35);
    return "$emoji <b>" . strtoupper($title) . "</b> $emoji\n$border\n\n";
}

/**
 * Create section divider
 */
function create_section($title, $emoji = "📌") {
    $border = str_repeat("─", 30);
    return "\n$emoji <b>$title</b>\n$border\n";
}

/**
 * Create formatted list item
 */
function create_list_item($index, $text, $emoji = "•") {
    $index_str = str_pad($index, 2, '0', STR_PAD_LEFT);
    return "$emoji <code>$index_str.</code> $text\n";
}

/**
 * Create button with styling
 */
function create_button($text, $callback_data, $emoji = "", $type = "default") {
    $button_text = $text;
    
    // Add emoji based on type
    $type_emojis = [
        'primary' => '👉',
        'success' => '✅',
        'danger' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️',
        'movie' => '🎬',
        'channel' => '📢',
        'search' => '🔍',
        'back' => '🔙',
        'next' => '➡️',
        'prev' => '⬅️',
        'home' => '🏠'
    ];
    
    if ($emoji) {
        $button_text = "$emoji $text";
    } elseif (isset($type_emojis[$type])) {
        $button_text = $type_emojis[$type] . " $text";
    }
    
    return [
        'text' => $button_text,
        'callback_data' => $callback_data
    ];
}

/**
 * Create multiple button rows
 */
function create_multi_button_row($buttons_array) {
    $rows = [];
    foreach ($buttons_array as $row) {
        $formatted_row = [];
        foreach ($row as $button) {
            $formatted_row[] = create_button(
                $button['text'], 
                $button['callback_data'],
                $button['emoji'] ?? '',
                $button['type'] ?? 'default'
            );
        }
        $rows[] = $formatted_row;
    }
    return ['inline_keyboard' => $rows];
}

/**
 * Create movie selection button
 */
function create_movie_button($movie_name, $callback_data = null) {
    if ($callback_data === null) {
        $callback_data = 'movie_' . urlencode(strtolower($movie_name));
    }
    
    // Truncate long names
    $display_name = strlen($movie_name) > 25 ? substr($movie_name, 0, 22) . '...' : $movie_name;
    
    return create_button($display_name, $callback_data, MOVIE_EMOJI, 'movie');
}

/**
 * Create channel selection button
 */
function create_channel_button($channel_name, $channel_id, $movie_query = '') {
    global $ALL_CHANNELS;
    $channel_info = $ALL_CHANNELS[$channel_id] ?? ['emoji' => CHANNEL_EMOJI];
    $emoji = $channel_info['emoji'] ?? CHANNEL_EMOJI;
    
    // Shorten name for button
    $short_name = strlen($channel_name) > 20 ? substr($channel_name, 0, 17) . '...' : $channel_name;
    
    return create_button(
        $short_name, 
        "channel_select_{$channel_id}_" . urlencode($movie_query),
        $emoji,
        'channel'
    );
}

/**
 * Create pagination buttons
 */
function create_pagination_buttons($current_page, $total_pages, $prefix = 'page') {
    $buttons = [];
    
    if ($total_pages <= 1) {
        return $buttons;
    }
    
    // Previous button
    if ($current_page > 1) {
        $buttons[] = create_button('Prev', "{$prefix}_prev_" . ($current_page - 1), '⬅️', 'prev');
    }
    
    // Page indicator
    $buttons[] = create_button("$current_page/$total_pages", "page_info", '', 'info');
    
    // Next button
    if ($current_page < $total_pages) {
        $buttons[] = create_button('Next', "{$prefix}_next_" . ($current_page + 1), '➡️', 'next');
    }
    
    return $buttons;
}

/**
 * Format movie list with channel info
 */
function format_movie_list($movies, $start_index = 1, $show_channel = true) {
    $list = "";
    $index = $start_index;
    
    foreach ($movies as $movie) {
        $movie_name = htmlspecialchars($movie['movie_name'] ?? 'Unknown');
        $channel_emoji = $movie['channel_emoji'] ?? CHANNEL_EMOJI;
        $channel_name = $movie['channel_name'] ?? 'Unknown';
        $date = $movie['date'] ?? 'N/A';
        
        $list .= create_list_item($index, "<b>$movie_name</b>");
        if ($show_channel) {
            $list .= "    $channel_emoji <i>$channel_name</i>\n";
            $list .= "    📅 <code>$date</code>\n\n";
        } else {
            $list .= "    📅 <code>$date</code>\n\n";
        }
        
        $index++;
    }
    
    return $list;
}

// ============================================
// 🔧 UTILITY FUNCTIONS
// ============================================

/**
 * Get channel information
 */
function get_channel_info($channel_id) {
    global $ALL_CHANNELS;
    if (isset($ALL_CHANNELS[$channel_id])) {
        return $ALL_CHANNELS[$channel_id];
    }
    return [
        'name' => 'Unknown Channel',
        'emoji' => CHANNEL_EMOJI,
        'username' => '',
        'type' => 'unknown'
    ];
}

/**
 * Check if channel is valid
 */
function is_valid_channel($channel_id) {
    global $ALL_CHANNELS;
    return array_key_exists($channel_id, $ALL_CHANNELS) || $channel_id == MAIN_CHANNEL_ID;
}

/**
 * Check if channel is movie channel (not request group)
 */
function is_movie_channel($channel_id) {
    $info = get_channel_info($channel_id);
    return $info['type'] === 'movie';
}

// ============================================
// 🤖 TELEGRAM API FUNCTIONS
// ============================================

/**
 * Send typing action
 */
function sendTypingAction($chat_id) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendChatAction";
    $data = [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

/**
 * Send upload video action
 */
function sendUploadVideoAction($chat_id) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendChatAction";
    $data = [
        'chat_id' => $chat_id,
        'action' => 'upload_video'
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

/**
 * Simulate typing delay
 */
function simulateTyping($chat_id, $duration = TYPING_DELAY) {
    sendTypingAction($chat_id);
    if ($duration > 0) {
        usleep($duration * 1000000);
    }
}

/**
 * Make API request
 */
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
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        error_log("❌ API Request failed for method: $method");
        return json_encode(['ok' => false, 'error' => 'Request failed']);
    }
    
    return $result;
}

/**
 * Send message with formatting
 */
function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = 'HTML') {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => true
    ];
    
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    
    return apiRequest('sendMessage', $data);
}

/**
 * Copy message (hide sender)
 */
function copyMessage($chat_id, $from_chat_id, $message_id) {
    return apiRequest('copyMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
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
    if ($text) $data['text'] = $text;
    return apiRequest('answerCallbackQuery', $data);
}

/**
 * Send styled message with delay
 */
function sendStyledMessage($chat_id, $content, $buttons = null, $parse_mode = 'HTML', $delay = MESSAGE_DELAY) {
    simulateTyping($chat_id, $delay);
    return sendMessage($chat_id, $content, $buttons, $parse_mode);
}

/**
 * Send success message
 */
function sendSuccessMessage($chat_id, $message, $buttons = null, $delay = 0.5) {
    $content = SUCCESS_EMOJI . " <b>SUCCESS</b> " . SUCCESS_EMOJI . "\n" . 
               str_repeat("─", 30) . "\n\n" . $message;
    return sendStyledMessage($chat_id, $content, $buttons, 'HTML', $delay);
}

/**
 * Send error message
 */
function sendErrorMessage($chat_id, $message, $buttons = null, $delay = 0.5) {
    $content = ERROR_EMOJI . " <b>ERROR</b> " . ERROR_EMOJI . "\n" . 
               str_repeat("─", 30) . "\n\n" . $message;
    return sendStyledMessage($chat_id, $content, $buttons, 'HTML', $delay);
}

/**
 * Send info message
 */
function sendInfoMessage($chat_id, $title, $message, $buttons = null, $delay = 0.5) {
    $content = INFO_EMOJI . " <b>$title</b> " . INFO_EMOJI . "\n" . 
               str_repeat("─", 30) . "\n\n" . $message;
    return sendStyledMessage($chat_id, $content, $buttons, 'HTML', $delay);
}

// ============================================
// 📊 STATISTICS FUNCTIONS
// ============================================

/**
 * Update channel statistics
 */
function update_channel_stats($channel_id, $increment = 1, $movie_name = null) {
    if (!file_exists(STATS_FILE)) return;
    
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    
    // Initialize channels_stats
    if (!isset($stats['channels_stats'])) {
        $stats['channels_stats'] = [];
    }
    
    $channel_info = get_channel_info($channel_id);
    
    // Initialize this channel's stats
    if (!isset($stats['channels_stats'][$channel_id])) {
        $stats['channels_stats'][$channel_id] = [
            'name' => $channel_info['name'],
            'emoji' => $channel_info['emoji'],
            'total_movies' => 0,
            'last_movie' => null,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    // Update stats
    $stats['channels_stats'][$channel_id]['total_movies'] += $increment;
    if ($movie_name) {
        $stats['channels_stats'][$channel_id]['last_movie'] = [
            'name' => $movie_name,
            'time' => date('Y-m-d H:i:s')
        ];
    }
    $stats['channels_stats'][$channel_id]['last_updated'] = date('Y-m-d H:i:s');
    
    // Update overall stats
    $stats['last_updated'] = date('Y-m-d H:i:s');
    
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}

/**
 * Update main statistics
 */
function update_stats($field, $increment = 1, $channel_id = null) {
    if (!file_exists(STATS_FILE)) return;
    
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats[$field] = ($stats[$field] ?? 0) + $increment;
    
    // Update channel stats if channel_id provided
    if ($channel_id && $field == 'total_movies') {
        update_channel_stats($channel_id, $increment);
    }
    
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}

/**
 * Get statistics
 */
function get_stats() {
    if (!file_exists(STATS_FILE)) return [];
    return json_decode(file_get_contents(STATS_FILE), true);
}

// ============================================
// 💾 CSV & CACHE FUNCTIONS
// ============================================

/**
 * Load and clean CSV data
 */
function load_and_clean_csv($filename = CSV_FILE) {
    global $movie_messages;
    
    if (!file_exists($filename)) {
        $headers = "movie_name,message_id,date,channel_id,channel_name,channel_emoji,added_timestamp\n";
        file_put_contents($filename, $headers);
        return [];
    }

    $data = [];
    $handle = fopen($filename, "r");
    
    if ($handle !== FALSE) {
        // Skip header
        fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && !empty(trim($row[0]))) {
                $movie_name = trim($row[0]);
                $message_id_raw = isset($row[1]) ? trim($row[1]) : '';
                $date = isset($row[2]) ? trim($row[2]) : date('d-m-Y');
                $channel_id = isset($row[3]) ? trim($row[3]) : MAIN_CHANNEL_ID;
                $channel_name = isset($row[4]) ? trim($row[4]) : get_channel_info($channel_id)['name'];
                $channel_emoji = isset($row[5]) ? trim($row[5]) : get_channel_info($channel_id)['emoji'];
                $added_timestamp = isset($row[6]) ? trim($row[6]) : date('Y-m-d H:i:s');

                $entry = [
                    'movie_name' => $movie_name,
                    'message_id_raw' => $message_id_raw,
                    'date' => $date,
                    'channel_id' => $channel_id,
                    'channel_name' => $channel_name,
                    'channel_emoji' => $channel_emoji,
                    'added_timestamp' => $added_timestamp
                ];
                
                // Convert message_id to integer if numeric
                if (is_numeric($message_id_raw)) {
                    $entry['message_id'] = intval($message_id_raw);
                } else {
                    $entry['message_id'] = null;
                }

                $data[] = $entry;

                // Cache in memory
                $movie_key = strtolower($movie_name);
                if (!isset($movie_messages[$movie_key])) {
                    $movie_messages[$movie_key] = [];
                }
                $movie_messages[$movie_key][] = $entry;
            }
        }
        fclose($handle);
    }

    // Update total movies count
    $stats = get_stats();
    $stats['total_movies'] = count($data);
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));

    // Re-write CSV with proper structure
    $handle = fopen($filename, "w");
    fputcsv($handle, ['movie_name', 'message_id', 'date', 'channel_id', 'channel_name', 'channel_emoji', 'added_timestamp']);
    
    foreach ($data as $row) {
        fputcsv($handle, [
            $row['movie_name'],
            $row['message_id_raw'],
            $row['date'],
            $row['channel_id'],
            $row['channel_name'],
            $row['channel_emoji'],
            $row['added_timestamp']
        ]);
    }
    fclose($handle);

    return $data;
}

/**
 * Get cached movies
 */
function get_cached_movies() {
    global $movie_cache;
    
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < CACHE_EXPIRY) {
        return $movie_cache['data'];
    }
    
    $movie_cache = [
        'data' => load_and_clean_csv(),
        'timestamp' => time()
    ];
    
    return $movie_cache['data'];
}

// ============================================
// 🎬 MOVIE DELIVERY FUNCTIONS
// ============================================

/**
 * Deliver movie to user
 */
function deliver_item_to_chat($chat_id, $item) {
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        $channel_id = $item['channel_id'] ?? MAIN_CHANNEL_ID;
        
        // Show upload action
        sendUploadVideoAction($chat_id);
        usleep(MOVIE_DELAY * 1000000);
        
        // Copy message (hides sender)
        return copyMessage($chat_id, $channel_id, $item['message_id']);
    }

    // Fallback: Send movie info as text
    $text = MOVIE_EMOJI . " <b>" . ($item['movie_name'] ?? 'Unknown') . "</b>\n";
    $text .= ($item['channel_emoji'] ?? CHANNEL_EMOJI) . " " . ($item['channel_name'] ?? 'Unknown') . "\n";
    $text .= "🆔 Ref: <code>" . ($item['message_id_raw'] ?? 'N/A') . "</code>\n";
    $text .= "📅 Date: " . ($item['date'] ?? 'N/A');
    
    sendStyledMessage($chat_id, $text, null, 'HTML', 0.3);
    return true;
}

/**
 * Deliver movies from specific channel
 */
function deliver_from_channel($chat_id, $movie_query, $channel_id) {
    global $movie_messages;
    
    $found_movies = [];
    $movie_query_lower = strtolower($movie_query);
    
    // Search for movies
    foreach ($movie_messages as $movie_key => $entries) {
        if (strpos($movie_key, $movie_query_lower) !== false) {
            foreach ($entries as $entry) {
                if ($entry['channel_id'] == $channel_id) {
                    $found_movies[] = $entry;
                }
            }
        }
    }
    
    // Deliver found movies
    if (!empty($found_movies)) {
        $count = 0;
        foreach ($found_movies as $movie) {
            deliver_item_to_chat($chat_id, $movie);
            usleep(500000); // 0.5 second delay
            $count++;
        }
        return $count;
    }
    
    return 0;
}

// ============================================
// 📄 PAGINATION FUNCTIONS
// ============================================

/**
 * Paginate movies array
 */
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

/**
 * Forward page movies to user
 */
function forward_page_movies($chat_id, array $page_movies) {
    $i = 1;
    foreach ($page_movies as $movie) {
        $num = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        deliver_item_to_chat($chat_id, $movie);
        usleep(500000); // 0.5 second delay
        $i++;
    }
}

// ============================================
// ➕ ADD MOVIE FUNCTION
// ============================================

/**
 * Append movie to CSV
 */
function append_movie($movie_name, $message_id_raw, $date = null, $channel_id = null, $channel_name = null) {
    if (empty(trim($movie_name))) return false;
    
    // Set defaults
    if ($date === null) $date = date('d-m-Y');
    if ($channel_id === null) $channel_id = MAIN_CHANNEL_ID;
    
    $channel_info = get_channel_info($channel_id);
    if ($channel_name === null) $channel_name = $channel_info['name'];
    $channel_emoji = $channel_info['emoji'];
    $added_timestamp = date('Y-m-d H:i:s');
    
    // Prepare entry
    $entry = [
        $movie_name,
        $message_id_raw,
        $date,
        $channel_id,
        $channel_name,
        $channel_emoji,
        $added_timestamp
    ];
    
    // Append to CSV
    $handle = fopen(CSV_FILE, "a");
    if ($handle !== FALSE) {
        fputcsv($handle, $entry);
        fclose($handle);
        
        // Update memory cache
        global $movie_messages, $movie_cache, $waiting_users;
        
        $movie_key = strtolower(trim($movie_name));
        $item = [
            'movie_name' => $movie_name,
            'message_id_raw' => $message_id_raw,
            'date' => $date,
            'channel_id' => $channel_id,
            'channel_name' => $channel_name,
            'channel_emoji' => $channel_emoji,
            'added_timestamp' => $added_timestamp,
            'message_id' => is_numeric($message_id_raw) ? intval($message_id_raw) : null
        ];
        
        // Add to memory cache
        if (!isset($movie_messages[$movie_key])) {
            $movie_messages[$movie_key] = [];
        }
        $movie_messages[$movie_key][] = $item;
        
        // Clear file cache
        $movie_cache = [];
        
        // Notify waiting users
        foreach ($waiting_users as $query => $users) {
            if (strpos($movie_key, strtolower($query)) !== false) {
                foreach ($users as $user_data) {
                    list($user_chat_id, $user_id) = $user_data;
                    deliver_item_to_chat($user_chat_id, $item);
                    
                    sendSuccessMessage($user_chat_id,
                        "Movie <b>'$query'</b> has been added!\n\n" .
                        "📢 Channel: $channel_emoji <b>$channel_name</b>\n" .
                        "📅 Added: $date\n\n" .
                        "The movie has been sent to you automatically.",
                        null, 1
                    );
                }
                unset($waiting_users[$query]);
            }
        }
        
        // Update statistics
        update_stats('total_movies', 1, $channel_id);
        
        error_log("✅ Movie added: '$movie_name' to '$channel_name'");
        return true;
    }
    
    error_log("❌ Failed to append movie: '$movie_name'");
    return false;
}

// ============================================
// 🔍 SEARCH FUNCTIONS
// ============================================

/**
 * Smart search algorithm
 */
function smart_search($query) {
    global $movie_messages;
    
    $query_lower = strtolower(trim($query));
    $results = [];
    
    foreach ($movie_messages as $movie_key => $entries) {
        $score = 0;
        
        // Exact match
        if ($movie_key == $query_lower) {
            $score = 100;
        }
        // Partial match
        elseif (strpos($movie_key, $query_lower) !== false) {
            $score = 80 - (strlen($movie_key) - strlen($query_lower));
        }
        // Similarity match
        else {
            similar_text($movie_key, $query_lower, $similarity);
            if ($similarity > 60) {
                $score = $similarity;
            }
        }
        
        if ($score > 0) {
            // Group by channel
            $channel_counts = [];
            foreach ($entries as $entry) {
                $channel_id = $entry['channel_id'];
                $channel_name = $entry['channel_name'];
                $channel_emoji = $entry['channel_emoji'];
                
                if (!isset($channel_counts[$channel_id])) {
                    $channel_counts[$channel_id] = [
                        'name' => $channel_name,
                        'emoji' => $channel_emoji,
                        'count' => 0
                    ];
                }
                $channel_counts[$channel_id]['count']++;
            }
            
            $results[$movie_key] = [
                'score' => $score,
                'total_count' => count($entries),
                'channels' => $channel_counts,
                'sample_entry' => $entries[0] // First entry for reference
            ];
        }
    }
    
    // Sort by score (highest first)
    uasort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    // Return top 10 results
    return array_slice($results, 0, 10);
}

/**
 * Detect language of text
 */
function detect_language($text) {
    $hindi_keywords = ['फिल्म', 'मूवी', 'डाउनलोड', 'हिंदी', 'भाषा'];
    $english_keywords = ['movie', 'download', 'watch', 'print', 'film'];
    
    $hindi_count = 0;
    $english_count = 0;
    
    foreach ($hindi_keywords as $keyword) {
        if (stripos($text, $keyword) !== false) $hindi_count++;
    }
    
    foreach ($english_keywords as $keyword) {
        if (stripos($text, $keyword) !== false) $english_count++;
    }
    
    return $hindi_count > $english_count ? 'hindi' : 'english';
}

/**
 * Update user points
 */
function update_user_points($user_id, $action) {
    $points_map = [
        'search' => 1,
        'found_movie' => 5,
        'daily_login' => 10,
        'request' => 2
    ];
    
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id])) {
        return;
    }
    
    if (!isset($users_data['users'][$user_id]['points'])) {
        $users_data['users'][$user_id]['points'] = 0;
    }
    
    $points_to_add = $points_map[$action] ?? 0;
    $users_data['users'][$user_id]['points'] += $points_to_add;
    $users_data['users'][$user_id]['last_activity'] = date('Y-m-d H:i:s');
    
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
}

/**
 * Advanced search with UI
 */
function advanced_search($chat_id, $query, $user_id = null) {
    global $movie_messages, $waiting_users;
    
    $query_original = trim($query);
    $query_lower = strtolower($query_original);
    
    // Show searching message
    $lang = detect_language($query);
    $searching_msg = $lang == 'hindi' ? 
        "🔍 <b>खोज रहा हूँ...</b>\nकृपया प्रतीक्षा करें ⏳" :
        "🔍 <b>Searching...</b>\nPlease wait ⏳";
    
    sendStyledMessage($chat_id, $searching_msg, null, 'HTML', 0.5);
    simulateTyping($chat_id, SEARCH_DELAY);
    
    // Validate query length
    if (strlen($query_lower) < 2) {
        sendErrorMessage($chat_id, 
            "Search query too short!\n\n" .
            "💡 Please enter at least <b>2 characters</b> for search.\n" .
            "Example: 'kg', 'av', 'pu'"
        );
        return;
    }
    
    // Filter invalid queries
    $invalid_keywords = [
        'vlc', 'audio', 'track', 'change', 'open', 'kar', 'me', 'hai',
        'how', 'what', 'problem', 'issue', 'help', 'solution', 'fix',
        'error', 'not working', 'play', 'video', 'sound', 'subtitle'
    ];
    
    $query_words = explode(' ', $query_lower);
    $invalid_count = 0;
    
    foreach ($query_words as $word) {
        if (in_array($word, $invalid_keywords)) {
            $invalid_count++;
        }
    }
    
    if ($invalid_count > 0 && ($invalid_count / count($query_words)) > 0.5) {
        $help_msg = create_header("SEARCH HELP");
        $help_msg .= "🎬 <b>How to search movies:</b>\n\n";
        $help_msg .= "✅ <b>DO:</b> Enter movie names only\n";
        $help_msg .= "❌ <b>DON'T:</b> Ask technical questions\n\n";
        
        $help_msg .= "📋 <b>Examples:</b>\n";
        $help_msg .= "• <code>kgf</code>\n";
        $help_msg .= "• <code>pushpa 2</code>\n";
        $help_msg .= "• <code>avengers endgame</code>\n";
        $help_msg .= "• <code>hindi movie</code>\n\n";
        
        $help_msg .= "📢 <b>Join:</b> @EntertainmentTadka786\n";
        $help_msg .= "💬 <b>Help:</b> @EntertainmentTadka0786";
        
        sendStyledMessage($chat_id, $help_msg, null, 'HTML');
        return;
    }
    
    // Perform search
    $found = smart_search($query_lower);
    
    if (!empty($found)) {
        // Build results message
        $header = create_header("SEARCH RESULTS", SEARCH_EMOJI);
        $content = $header;
        
        $content .= "🔍 Query: <b>'$query_original'</b>\n";
        $content .= "📊 Found: <b>" . count($found) . "</b> matching movies\n\n";
        
        $content .= create_section("TOP MATCHES", "🎯");
        
        $i = 1;
        $movie_buttons = [];
        $all_channels = [];
        
        foreach ($found as $movie_key => $data) {
            $movie_name = $data['sample_entry']['movie_name'];
            $total_entries = $data['total_count'];
            
            $content .= create_list_item($i, "<b>" . htmlspecialchars($movie_name) . "</b> ($total_entries entries)");
            
            // Show top 3 channels
            $channel_count = 0;
            foreach ($data['channels'] as $channel_id => $channel_info) {
                if ($channel_count < 3) {
                    $content .= "    " . $channel_info['emoji'] . " " . $channel_info['name'] . 
                               " (" . $channel_info['count'] . ")\n";
                    $channel_count++;
                }
                
                // Collect all channels for buttons
                if (!in_array($channel_id, $all_channels)) {
                    $all_channels[] = $channel_id;
                }
            }
            
            if (count($data['channels']) > 3) {
                $more = count($data['channels']) - 3;
                $content .= "    ... and $more more channels\n";
            }
            
            $content .= "\n";
            
            // Add movie button (first 4 movies only)
            if ($i <= 4) {
                $movie_buttons[] = create_movie_button($movie_name);
            }
            
            $i++;
            if ($i > 8) break; // Show max 8 results
        }
        
        // Create keyboard
        $keyboard = ['inline_keyboard' => []];
        
        // Add movie buttons in rows of 2
        for ($j = 0; $j < count($movie_buttons); $j += 2) {
            $row = array_slice($movie_buttons, $j, 2);
            if (!empty($row)) {
                $keyboard['inline_keyboard'][] = $row;
            }
        }
        
        // Add channel selection buttons if multiple channels
        if (count($all_channels) > 1 && count($all_channels) <= 4) {
            $channel_row = [];
            foreach ($all_channels as $channel_id) {
                $channel_info = get_channel_info($channel_id);
                $channel_row[] = create_channel_button(
                    $channel_info['name'],
                    $channel_id,
                    $query_original
                );
            }
            if (!empty($channel_row)) {
                $keyboard['inline_keyboard'][] = $channel_row;
            }
        }
        
        // Add action buttons
        $keyboard['inline_keyboard'][] = [
            create_button('New Search', 'new_search', SEARCH_EMOJI, 'search'),
            create_button('Main Menu', 'main_menu', '🏠', 'primary')
        ];
        
        sendStyledMessage($chat_id, $content, $keyboard, 'HTML');
        
        // Update user points
        if ($user_id) {
            update_user_points($user_id, 'found_movie');
        }
        
    } else {
        // No results found
        $not_found_msg = create_header("NOT FOUND", ERROR_EMOJI);
        $not_found_msg .= "😔 Movie <b>'$query_original'</b> not found in our database.\n\n";
        $not_found_msg .= "📝 <b>You can request it:</b>\n";
        $not_found_msg .= "• Join: @EntertainmentTadka0786\n";
        $not_found_msg .= "• Send movie name in request group\n\n";
        $not_found_msg .= "🔔 <b>I'll notify you</b> when it's added!\n";
        $not_found_msg .= "🎯 Meanwhile, try searching for similar movies.";
        
        $keyboard = create_multi_button_row([
            [
                ['text' => 'Request Movie', 'callback_data' => 'request_movie', 'emoji' => '📝', 'type' => 'primary'],
                ['text' => 'Try Again', 'callback_data' => 'new_search', 'emoji' => '🔄', 'type' => 'info']
            ],
            [
                ['text' => 'Popular Movies', 'callback_data' => 'popular_movies', 'emoji' => '🔥', 'type' => 'warning'],
                ['text' => 'Main Menu', 'callback_data' => 'main_menu', 'emoji' => '🏠', 'type' => 'primary']
            ]
        ]);
        
        sendStyledMessage($chat_id, $not_found_msg, $keyboard, 'HTML');
        
        // Add to waiting users list
        if (!isset($waiting_users[$query_lower])) {
            $waiting_users[$query_lower] = [];
        }
        $waiting_users[$query_lower][] = [$chat_id, $user_id ?? $chat_id];
    }
    
    // Update search statistics
    update_stats('total_searches', 1);
    
    // Update user points for search
    if ($user_id) {
        update_user_points($user_id, 'search');
    }
}

// ============================================
// 📊 STATISTICS COMMANDS
// ============================================

/**
 * Show channel statistics
 */
function show_channels_stats($chat_id) {
    simulateTyping($chat_id, 1);
    
    $stats = get_stats();
    $channels_stats = $stats['channels_stats'] ?? [];
    
    if (empty($channels_stats)) {
        sendInfoMessage($chat_id, "Channel Statistics", "No channel statistics available yet.");
        return;
    }
    
    $header = create_header("CHANNEL STATISTICS", CHANNEL_EMOJI);
    $content = $header;
    
    // Sort by movie count (descending)
    uasort($channels_stats, function($a, $b) {
        return $b['total_movies'] - $a['total_movies'];
    });
    
    $total_all_channels = 0;
    $i = 1;
    
    foreach ($channels_stats as $channel_id => $data) {
        // Skip non-movie channels
        if (!is_movie_channel($channel_id)) continue;
        
        $channel_name = $data['name'] ?? 'Unknown';
        $channel_emoji = $data['emoji'] ?? CHANNEL_EMOJI;
        $movie_count = $data['total_movies'] ?? 0;
        $last_updated = $data['last_updated'] ?? 'N/A';
        
        $content .= create_list_item($i, "<b>$channel_emoji $channel_name</b>");
        $content .= "    📊 Movies: <b>$movie_count</b>\n";
        $content .= "    🕒 Updated: $last_updated\n\n";
        
        $total_all_channels += $movie_count;
        $i++;
    }
    
    $content .= create_section("SUMMARY", STATS_EMOJI);
    $content .= "• Total Channels: <b>" . ($i - 1) . "</b>\n";
    $content .= "• Total Movies: <b>$total_all_channels</b>\n";
    $content .= "• Average/Channel: <b>" . round($total_all_channels / max(1, ($i - 1)), 1) . "</b>\n\n";
    
    // Show top channel
    if (!empty($channels_stats)) {
        $top_channel = reset($channels_stats);
        $content .= "🏆 <b>Top Channel:</b> " . 
                   ($top_channel['emoji'] ?? '') . " " . 
                   ($top_channel['name'] ?? '') . 
                   " (" . ($top_channel['total_movies'] ?? 0) . " movies)";
    }
    
    $keyboard = create_multi_button_row([
        [
            ['text' => 'View Movies', 'callback_data' => 'total_uploads_1', 'emoji' => MOVIE_EMOJI, 'type' => 'movie'],
            ['text' => 'Date Stats', 'callback_data' => 'check_date', 'emoji' => CALENDAR_EMOJI, 'type' => 'info']
        ],
        [
            ['text' => 'Main Menu', 'callback_data' => 'main_menu', 'emoji' => '🏠', 'type' => 'primary']
        ]
    ]);
    
    sendStyledMessage($chat_id, $content, $keyboard, 'HTML');
}

/**
 * Show date statistics
 */
function check_date($chat_id) {
    simulateTyping($chat_id, 1);
    
    if (!file_exists(CSV_FILE)) { 
        sendErrorMessage($chat_id, "No data saved yet.");
        return; 
    }
    
    // Read CSV and count by date
    $date_counts = [];
    $handle = fopen(CSV_FILE, "r");
    
    if ($handle !== FALSE) {
        fgetcsv($handle); // Skip header
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3) { 
                $date = $row[2];
                if (!isset($date_counts[$date])) {
                    $date_counts[$date] = 0;
                }
                $date_counts[$date]++; 
            }
        }
        fclose($handle);
    }
    
    // Sort dates descending (newest first)
    krsort($date_counts);
    
    $header = create_header("DATE STATISTICS", CALENDAR_EMOJI);
    $content = $header;
    
    $total_days = 0;
    $total_movies = 0;
    
    foreach ($date_counts as $date => $count) { 
        $content .= "📅 <b>$date</b>: $count movies\n"; 
        $total_days++; 
        $total_movies += $count; 
    }
    
    $content .= "\n" . create_section("SUMMARY", STATS_EMOJI);
    $content .= "• Total Days: <b>$total_days</b>\n";
    $content .= "• Total Movies: <b>$total_movies</b>\n";
    $content .= "• Average/Day: <b>" . round($total_movies / max(1, $total_days), 2) . "</b>\n";
    $content .= "• Active Channels: <b>" . count($GLOBALS['ALL_CHANNELS']) . "</b>\n\n";
    
    // Top 3 days
    arsort($date_counts);
    $top_days = array_slice($date_counts, 0, 3, true);
    
    if (!empty($top_days)) {
        $content .= create_section("TOP DAYS", "🏆");
        $rank = 1;
        foreach ($top_days as $date => $count) {
            $medal = $rank == 1 ? "🥇" : ($rank == 2 ? "🥈" : "🥉");
            $content .= "$medal <b>$date</b>: $count movies\n";
            $rank++;
        }
    }
    
    $keyboard = create_multi_button_row([
        [
            ['text' => 'Total Uploads', 'callback_data' => 'total_uploads_1', 'emoji' => UPLOAD_EMOJI, 'type' => 'primary'],
            ['text' => 'Channel Stats', 'callback_data' => 'channels_stats', 'emoji' => CHANNEL_EMOJI, 'type' => 'channel']
        ],
        [
            ['text' => 'Main Menu', 'callback_data' => 'main_menu', 'emoji' => '🏠', 'type' => 'primary']
        ]
    ]);
    
    sendStyledMessage($chat_id, $content, $keyboard, 'HTML');
}

/**
 * Show admin statistics
 */
function admin_stats($chat_id) {
    simulateTyping($chat_id, 1);
    
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $total_users = count($users_data['users'] ?? []);
    
    $header = create_header("ADMIN STATISTICS", "👑");
    $content = $header;
    
    $content .= create_section("OVERVIEW", STATS_EMOJI);
    $content .= "🎬 Total Movies: <b>" . ($stats['total_movies'] ?? 0) . "</b>\n";
    $content .= "👥 Total Users: <b>$total_users</b>\n";
    $content .= "🔍 Total Searches: <b>" . ($stats['total_searches'] ?? 0) . "</b>\n";
    $content .= "🕒 Last Updated: " . ($stats['last_updated'] ?? 'N/A') . "\n";
    $content .= "📅 Created: " . ($stats['created'] ?? 'N/A') . "\n\n";
    
    // Channel stats
    if (!empty($stats['channels_stats'])) {
        $content .= create_section("CHANNEL STATS", CHANNEL_EMOJI);
        foreach ($stats['channels_stats'] as $channel_id => $channel_data) {
            if (is_movie_channel($channel_id)) {
                $content .= "• " . ($channel_data['emoji'] ?? CHANNEL_EMOJI) . " " . 
                           ($channel_data['name'] ?? 'Unknown') . ": " . 
                           "<b>" . ($channel_data['total_movies'] ?? 0) . "</b> movies\n";
            }
        }
        $content .= "\n";
    }
    
    // Recent uploads
    $csv_data = get_cached_movies();
    $recent = array_slice($csv_data, -5);
    
    if (!empty($recent)) {
        $content .= create_section("RECENT UPLOADS", UPLOAD_EMOJI);
        foreach ($recent as $movie) {
            $content .= "• " . ($movie['channel_emoji'] ?? CHANNEL_EMOJI) . " " . 
                       $movie['movie_name'] . "\n";
        }
    }
    
    // User statistics
    $active_today = 0;
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    foreach ($users_data['users'] ?? [] as $user) {
        $last_active = $user['last_active'] ?? '';
        if (strpos($last_active, date('Y-m-d')) === 0) {
            $active_today++;
        }
    }
    
    $content .= "\n" . create_section("USER STATS", USER_EMOJI);
    $content .= "• Active Today: <b>$active_today</b>\n";
    $content .= "• Total Requests: <b>" . ($users_data['total_requests'] ?? 0) . "</b>\n";
    
    sendStyledMessage($chat_id, $content, null, 'HTML');
}

// ============================================
// 📋 DATA VIEWING COMMANDS
// ============================================

/**
 * Show CSV data
 */
function show_csv_data($chat_id, $show_all = false) {
    simulateTyping($chat_id, 1);
    
    if (!file_exists(CSV_FILE)) {
        sendErrorMessage($chat_id, "CSV file not found.");
        return;
    }
    
    $handle = fopen(CSV_FILE, "r");
    if ($handle === FALSE) {
        sendErrorMessage($chat_id, "Error opening CSV file.");
        return;
    }
    
    // Skip header
    fgetcsv($handle);
    
    $movies = [];
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) >= 3) {
            $movies[] = $row;
        }
    }
    fclose($handle);
    
    if (empty($movies)) {
        sendInfoMessage($chat_id, "CSV Database", "CSV file is empty.");
        return;
    }
    
    // Reverse to show newest first
    $movies = array_reverse($movies);
    
    $limit = $show_all ? count($movies) : 10;
    $movies = array_slice($movies, 0, $limit);
    
    $header = create_header("CSV DATABASE", BACKUP_EMOJI);
    $content = $header;
    
    $content .= "📁 Total Movies: <b>" . count($movies) . "</b>\n";
    if (!$show_all) {
        $content .= "🔍 Showing latest <b>10</b> entries\n";
        $content .= "📋 Use <code>/checkcsv all</code> for full list\n\n";
    } else {
        $content .= "📋 Full database listing\n\n";
    }
    
    $i = 1;
    foreach ($movies as $movie) {
        $movie_name = $movie[0] ?? 'N/A';
        $message_id = $movie[1] ?? 'N/A';
        $date = $movie[2] ?? 'N/A';
        $channel_name = $movie[4] ?? 'Unknown';
        $channel_emoji = $movie[5] ?? CHANNEL_EMOJI;
        
        $content .= create_list_item($i, "<b>" . htmlspecialchars($movie_name) . "</b>");
        $content .= "    $channel_emoji Channel: $channel_name\n";
        $content .= "    🆔 ID: <code>$message_id</code>\n";
        $content .= "    📅 Date: $date\n\n";
        
        $i++;
        
        // Split long messages
        if (strlen($content) > 3000) {
            sendStyledMessage($chat_id, $content, null, 'HTML');
            $content = create_header("CONTINUED...", BACKUP_EMOJI) . "\n";
        }
    }
    
    $content .= str_repeat("═", 35) . "\n";
    $content .= "💾 File: <code>" . CSV_FILE . "</code>\n";
    $content .= "⏰ Last Updated: " . date('Y-m-d H:i:s', filemtime(CSV_FILE));
    
    $keyboard = create_multi_button_row([
        [
            ['text' => 'View All', 'callback_data' => 'checkcsv_all', 'emoji' => '📋', 'type' => 'info'],
            ['text' => 'Main Menu', 'callback_data' => 'main_menu', 'emoji' => '🏠', 'type' => 'primary']
        ]
    ]);
    
    sendStyledMessage($chat_id, $content, $keyboard, 'HTML');
}

/**
 * Show popular movies
 */
function show_popular_movies($chat_id) {
    simulateTyping($chat_id, 1);
    
    $all_movies = get_cached_movies();
    
    // Count movie occurrences
    $movie_counts = [];
    foreach ($all_movies as $movie) {
        $name = $movie['movie_name'];
        if (!isset($movie_counts[$name])) {
            $movie_counts[$name] = [
                'count' => 0,
                'channels' => [],
                'data' => $movie
            ];
        }
        $movie_counts[$name]['count']++;
        
        $channel_name = $movie['channel_name'];
        if (!in_array($channel_name, $movie_counts[$name]['channels'])) {
            $movie_counts[$name]['channels'][] = $channel_name;
        }
    }
    
    // Sort by count (descending)
    uasort($movie_counts, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    // Get top 10
    $top_movies = array_slice($movie_counts, 0, 10, true);
    
    $header = create_header("POPULAR MOVIES", "🔥");
    $content = $header;
    
    if (empty($top_movies)) {
        $content .= "No movies found in database yet.\n";
        $content .= "Start by searching for movies!";
    } else {
        $content .= "🎯 <b>Top 10 Most Available Movies:</b>\n\n";
        
        $i = 1;
        $movie_buttons = [];
        
        foreach ($top_movies as $movie_name => $data) {
            $count = $data['count'];
            $channels_count = count($data['channels']);
            
            $medal = $i == 1 ? "🥇" : ($i == 2 ? "🥈" : ($i == 3 ? "🥉" : ""));
            $content .= "$medal <b>$i. $movie_name</b>\n";
            $content .= "   📊 Copies: <b>$count</b>\n";
            $content .= "   📢 Channels: <b>$channels_count</b>\n\n";
            
            // Add button for top 5 movies
            if ($i <= 5) {
                $movie_buttons[] = create_movie_button($movie_name);
            }
            
            $i++;
        }
        
        // Create keyboard
        $keyboard = ['inline_keyboard' => []];
        
        // Add movie buttons in rows of 2
        for ($j = 0; $j < count($movie_buttons); $j += 2) {
            $row = array_slice($movie_buttons, $j, 2);
            $keyboard['inline_keyboard'][] = $row;
        }
        
        // Add action buttons
        $keyboard['inline_keyboard'][] = [
            create_button('Search Movies', 'new_search', SEARCH_EMOJI, 'search'),
            create_button('Main Menu', 'main_menu', '🏠', 'primary')
        ];
        
        sendStyledMessage($chat_id, $content, $keyboard, 'HTML');
        return;
    }
    
    $keyboard = create_multi_button_row([
        [
            ['text' => 'Search Movies', 'callback_data' => 'new_search', 'emoji' => SEARCH_EMOJI, 'type' => 'search'],
            ['text' => 'Main Menu', 'callback_data' => 'main_menu', 'emoji' => '🏠', 'type' => 'primary']
        ]
    ]);
    
    sendStyledMessage($chat_id, $content, $keyboard, 'HTML');
}

// ============================================
// 📤 TOTAL UPLOADS SYSTEM
// ============================================

/**
 * Total uploads controller
 */
function total_uploads_controller($chat_id, $page = 1) {
    simulateTyping($chat_id, 1);
    
    $all = get_cached_movies();
    if (empty($all)) {
        sendErrorMessage($chat_id, "No movies found in database yet.");
        return;
    }
    
    $paginated = paginate_movies($all, (int)$page);
    
    // Forward movies from this page
    forward_page_movies($chat_id, $paginated['slice']);
    
    // Build message
    $header = create_header("TOTAL UPLOADS", UPLOAD_EMOJI);
    $content = $header;
    
    $content .= "📊 <b>Statistics:</b>\n";
    $content .= "• Page: <b>{$paginated['page']}/{$paginated['total_pages']}</b>\n";
    $content .= "• Showing: <b>" . count($paginated['slice']) . "</b> of <b>{$paginated['total']}</b> movies\n";
    $content .= "• Channels: <b>" . count($GLOBALS['ALL_CHANNELS']) . "</b> active\n\n";
    
    $content .= "🎬 <b>Movies on this page:</b>\n";
    $content .= format_movie_list($paginated['slice'], 1 + (($page-1) * ITEMS_PER_PAGE));
    
    $content .= "\n" . str_repeat("═", 35) . "\n";
    $content .= "Use buttons below to navigate 👇";
    
    // Create keyboard
    $keyboard = ['inline_keyboard' => []];
    
    // Pagination buttons
    $pagination_buttons = create_pagination_buttons($paginated['page'], $paginated['total_pages'], 'total_uploads');
    if (!empty($pagination_buttons)) {
        $keyboard['inline_keyboard'][] = $pagination_buttons;
    }
    
    // Action buttons
    $keyboard['inline_keyboard'][] = [
        create_button('View Movies', 'tu_view_' . $paginated['page'], MOVIE_EMOJI, 'movie'),
        create_button('Channel Stats', 'channels_stats', CHANNEL_EMOJI, 'channel')
    ];
    
    // Control buttons
    $keyboard['inline_keyboard'][] = [
        create_button('Stop', 'tu_stop', '🛑', 'danger'),
        create_button('Main Menu', 'main_menu', '🏠', 'primary')
    ];
    
    sendStyledMessage($chat_id, $content, $keyboard, 'HTML');
}

// ============================================
// 🏠 MAIN MENU SYSTEM
// ============================================

/**
 * Show main menu
 */
function show_main_menu($chat_id, $user_id = null) {
    simulateTyping($chat_id, 1);
    
    $header = create_header("ENTERTAINMENT TADKA", "🎬");
    $content = $header;
    
    $content .= "🤖 <b>Welcome to the Ultimate Movie Bot!</b>\n\n";
    $content .= "🎯 <b>Features:</b>\n";
    $content .= "• Search from <b>" . count($GLOBALS['ALL_CHANNELS']) . "</b> channels\n";
    $content .= "• Smart multi-channel search\n";
    $content .= "• Beautiful UI with buttons\n";
    $content .= "• Daily movie digest\n";
    $content .= "• Channel-wise filtering\n\n";
    
    // Statistics
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user_points = $users_data['users'][$user_id]['points'] ?? 0;
    
    $content .= create_section("STATISTICS", STATS_EMOJI);
    $content .= "🎬 Movies: <b>" . ($stats['total_movies'] ?? 0) . "</b>\n";
    $content .= "👥 Users: <b>" . count($users_data['users'] ?? []) . "</b>\n";
    $content .= "🔍 Searches: <b>" . ($stats['total_searches'] ?? 0) . "</b>\n";
    $content .= "⭐ Your Points: <b>$user_points</b>\n\n";
    
    // How to use
    $content .= "🎯 <b>How to use:</b>\n";
    $content .= "1. Type any movie name\n";
    $content .= "2. Select from results\n";
    $content .= "3. Choose specific channel\n";
    $content .= "4. Enjoy the movie!\n\n";
    
    $content .= str_repeat("═", 35) . "\n";
    $content .= "📢 <b>Channels:</b> @EntertainmentTadka786\n";
    $content .= "💬 <b>Requests:</b> @EntertainmentTadka0786\n";
    $content .= "🤖 <b>Bot:</b> @EntertainmentTadkaBot";
    
    // Create menu buttons
    $keyboard = create_multi_button_row([
        [
            ['text' => '🔍 Search Movie', 'callback_data' => 'new_search', 'type' => 'search'],
            ['text' => '📊 Total Uploads', 'callback_data' => 'total_uploads_1', 'type' => 'primary']
        ],
        [
            ['text' => '📢 Channel Stats', 'callback_data' => 'channels_stats', 'type' => 'channel'],
            ['text' => '📅 Date Stats', 'callback_data' => 'check_date', 'type' => 'info']
        ],
        [
            ['text' => '🎬 Popular Movies', 'callback_data' => 'popular_movies', 'type' => 'movie'],
            ['text' => '📋 CSV Data', 'callback_data' => 'checkcsv_10', 'type' => 'info']
        ],
        [
            ['text' => '❓ Help', 'callback_data' => 'show_help', 'type' => 'warning'],
            ['text' => '🤖 Bot Info', 'callback_data' => 'bot_info', 'type' => 'info']
        ]
    ]);
    
    // Add admin button for owner
    if ($user_id == OWNER_ID) {
        $keyboard['inline_keyboard'][] = [
            create_button('Admin Stats', 'admin_stats', '👑', 'warning')
        ];
    }
    
    sendStyledMessage($chat_id, $content, $keyboard, 'HTML');
    
    // Update user points for daily login
    if ($user_id) {
        update_user_points($user_id, 'daily_login');
    }
}

/**
 * Show help guide
 */
function show_help($chat_id) {
    simulateTyping($chat_id, 1);
    
    $header = create_header("HELP GUIDE", HELP_EMOJI);
    $content = $header;
    
    $content .= "🎬 <b>How to Search Movies:</b>\n\n";
    $content .= create_list_item(1, "Simply type any movie name");
    $content .= create_list_item(2, "Use partial names (e.g., 'push', 'ave')");
    $content .= create_list_item(3, "Select from search results");
    $content .= create_list_item(4, "Choose specific channel if available\n");
    
    $content .= create_section("AVAILABLE COMMANDS", "⌨️");
    $content .= "<code>/start</code> - Main menu\n";
    $content .= "<code>/menu</code> - Show menu\n";
    $content .= "<code>/channels</code> - List all channels\n";
    $content .= "<code>/checkdate</code> - Date-wise statistics\n";
    $content .= "<code>/totalupload</code> - All uploads (pagination)\n";
    $content .= "<code>/checkcsv</code> - View CSV data\n";
    $content .= "<code>/popular</code> - Popular movies\n";
    $content .= "<code>/help</code> - This help message\n";
    $content .= "<code>/info</code> - Bot information\n\n";
    
    $content .= create_section("TIPS", "💡");
    $content .= "• Use <b>English or Hindi</b> for search\n";
    $content .= "• <b>Buttons</b> provide quick access\n";
    $content .= "• Movies from <b>" . count($GLOBALS['ALL_CHANNELS']) . " channels</b>\n";
    $content .= "• Get <b>daily updates</b> automatically\n\n";
    
    $content .= create_section("CHANNELS", CHANNEL_EMOJI);
    foreach ($GLOBALS['ALL_CHANNELS'] as $channel_id => $info) {
        if ($info['type'] == 'movie') {
            $username = $info['username'] ? " ($info[username])" : "";
            $content .= "• " . $info['emoji'] . " " . $info['name'] . "$username\n";
        }
    }
    
    $content .= "\n" . str_repeat("═", 35) . "\n";
    $content .= "📢 Main Channel: @EntertainmentTadka786\n";
    $content .= "💬 Request Group: @EntertainmentTadka0786\n";
    $content .= "🤖 Bot: @EntertainmentTadkaBot\n\n";
    $content .= "⭐ <b>Start by typing a movie name!</b>";
    
    $keyboard = create_multi_button_row([
        [
            ['text' => 'Try Search', 'callback_data' => 'new_search', 'emoji' => SEARCH_EMOJI, 'type' => 'search'],
            ['text' => 'Main Menu', 'callback_data' => 'main_menu', 'emoji' => '🏠', 'type' => 'primary']
        ]
    ]);
    
    sendStyledMessage($chat_id, $content, $keyboard, 'HTML');
}

/**
 * Show bot information
 */
function show_bot_info($chat_id) {
    simulateTyping($chat_id, 1);
    
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $header = create_header("BOT INFORMATION", "🤖");
    $content = $header;
    
    $content .= "🎬 <b>Entertainment Tadka Bot</b>\n\n";
    
    $content .= create_section("STATISTICS", STATS_EMOJI);
    $content .= "• Version: <b>3.0</b> (Multi-Channel)\n";
    $content .= "• Uptime: <b>24/7</b>\n";
    $content .= "• Movies: <b>" . ($stats['total_movies'] ?? 0) . "</b>\n";
    $content .= "• Users: <b>" . count($users_data['users'] ?? []) . "</b>\n";
    $content .= "• Channels: <b>" . count($GLOBALS['ALL_CHANNELS']) . "</b>\n";
    $content .= "• Last Update: " . ($stats['last_updated'] ?? 'N/A') . "\n\n";
    
    $content .= create_section("FEATURES", "✨");
    $content .= "✅ Multi-channel support\n";
    $content .= "✅ Beautiful UI design\n";
    $content .= "✅ Smart search algorithm\n";
    $content .= "✅ Channel-wise filtering\n";
    $content .= "✅ Daily digest updates\n";
    $content .= "✅ User points system\n";
    $content .= "✅ Automatic backups\n\n";
    
    $content .= create_section("TECHNICAL", "⚙️");
    $content .= "• Platform: <b>PHP Telegram Bot</b>\n";
    $content .= "• Database: <b>CSV + JSON</b>\n";
    $content .= "• Channels: <b>" . count($GLOBALS['ALL_CHANNELS']) . "</b> integrated\n";
    $content .= "• Updates: <b>Real-time</b>\n\n";
    
    $content .= str_repeat("═", 35) . "\n";
    $content .= "👑 <b>Owner:</b> " . OWNER_ID . "\n";
    $content .= "🤖 <b>Bot:</b> @EntertainmentTadkaBot\n";
    $content .= "📢 <b>Channel:</b> @EntertainmentTadka786\n";
    $content .= "💬 <b>Support:</b> @EntertainmentTadka0786\n\n";
    $content .= "⭐ <b>Thank you for using our bot!</b>";
    
    $keyboard = create_multi_button_row([
        [
            ['text' => 'Main Menu', 'callback_data' => 'main_menu', 'emoji' => '🏠', 'type' => 'primary'],
            ['text' => 'Search Movies', 'callback_data' => 'new_search', 'emoji' => SEARCH_EMOJI, 'type' => 'search']
        ]
    ]);
    
    sendStyledMessage($chat_id, $content, $keyboard, 'HTML');
}

// ============================================
// 💾 BACKUP FUNCTIONS
// ============================================

/**
 * Auto backup system
 */
function auto_backup() {
    $backup_files = [CSV_FILE, USERS_FILE, STATS_FILE, CHANNELS_FILE];
    $backup_dir = BACKUP_DIR . date('Y-m-d');
    
    // Create directory with proper permissions
    if (!file_exists($backup_dir)) {
        if (!mkdir($backup_dir, 0777, true)) {
            error_log("❌ Failed to create backup directory: $backup_dir");
            return;
        }
    }
    
    foreach ($backup_files as $file) {
        if (file_exists($file)) {
            $backup_path = $backup_dir . '/' . basename($file) . '.bak';
            if (!copy($file, $backup_path)) {
                error_log("❌ Failed to backup: $file");
            }
        }
    }
    
    // Keep only last 7 days of backups
    $old_backups = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    if (is_array($old_backups) && count($old_backups) > 7) {
        usort($old_backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $to_delete = array_slice($old_backups, 0, count($old_backups) - 7);
        foreach ($to_delete as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) @unlink($file);
                }
                @rmdir($dir);
            }
        }
    }
    
    error_log("✅ Auto backup completed at " . date('Y-m-d H:i:s'));
}

/**
 * Send daily digest
 */
function send_daily_digest() {
    $yesterday = date('d-m-Y', strtotime('-1 day'));
    $yesterday_movies = [];
    
    // Read movies from yesterday
    $handle = fopen(CSV_FILE, "r");
    if ($handle !== FALSE) {
        fgetcsv($handle); // Skip header
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && $row[2] == $yesterday) {
                $yesterday_movies[] = [
                    'name' => $row[0],
                    'channel' => $row[4] ?? 'Unknown',
                    'emoji' => $row[5] ?? CHANNEL_EMOJI
                ];
            }
        }
        fclose($handle);
    }
    
    if (!empty($yesterday_movies)) {
        $users_data = json_decode(file_get_contents(USERS_FILE), true);
        
        foreach ($users_data['users'] as $user_id => $user_info) {
            simulateTyping($user_id, 1);
            
            $header = create_header("DAILY DIGEST", CALENDAR_EMOJI);
            $msg = $header;
            
            $msg .= "📅 Date: <b>$yesterday</b>\n";
            $msg .= "🎬 Total Movies: <b>" . count($yesterday_movies) . "</b>\n\n";
            
            $msg .= create_section("YESTERDAY'S UPLOADS", UPLOAD_EMOJI);
            
            // Group by channel
            $by_channel = [];
            foreach ($yesterday_movies as $movie) {
                $channel = $movie['channel'];
                if (!isset($by_channel[$channel])) {
                    $by_channel[$channel] = [
                        'emoji' => $movie['emoji'],
                        'movies' => []
                    ];
                }
                $by_channel[$channel]['movies'][] = $movie['name'];
            }
            
            foreach ($by_channel as $channel => $data) {
                $msg .= "\n" . $data['emoji'] . " <b>$channel</b>:\n";
                foreach (array_slice($data['movies'], 0, 5) as $movie_name) {
                    $msg .= "• $movie_name\n";
                }
                if (count($data['movies']) > 5) {
                    $msg .= "• ... and " . (count($data['movies'])-5) . " more\n";
                }
            }
            
            $msg .= "\n" . str_repeat("═", 35) . "\n";
            $msg .= "📢 Join: @EntertainmentTadka786\n";
            $msg .= "🔍 Search movies anytime!";
            
            $keyboard = create_multi_button_row([
                [
                    ['text' => 'Search Movies', 'callback_data' => 'new_search', 'emoji' => SEARCH_EMOJI, 'type' => 'search'],
                    ['text' => 'Channel Stats', 'callback_data' => 'channels_stats', 'emoji' => CHANNEL_EMOJI, 'type' => 'channel']
                ]
            ]);
            
            sendStyledMessage($user_id, $msg, $keyboard, 'HTML');
            sleep(1); // Delay between users
        }
        
        error_log("✅ Daily digest sent to " . count($users_data['users']) . " users");
    }
}

// ============================================
// 📨 PROCESS CHANNEL POSTS
// ============================================

/**
 * Process channel posts
 */
function process_channel_post($message, $chat_id, $message_id) {
    global $ALL_CHANNELS;
    
    if (is_valid_channel($chat_id)) {
        $text = '';
        $channel_info = get_channel_info($chat_id);
        $channel_name = $channel_info['name'];
        $channel_emoji = $channel_info['emoji'];
        
        // Extract text from different message types
        if (isset($message['caption'])) {
            $text = $message['caption'];
        }
        elseif (isset($message['text'])) {
            $text = $message['text'];
        }
        elseif (isset($message['document'])) {
            $text = $message['document']['file_name'] ?? 'Document';
        }
        elseif (isset($message['video'])) {
            $text = $message['video']['file_name'] ?? 'Video';
        }
        else {
            $text = 'Media - ' . date('d-m-Y H:i');
        }
        
        if (!empty(trim($text))) {
            // Clean the text
            $text = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $text);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);
            
            // Extract movie name (first 100 chars)
            $movie_name = substr($text, 0, 100);
            
            // Append to database
            append_movie($movie_name, $message_id, date('d-m-Y'), $chat_id, $channel_name);
            
            error_log("📥 Movie added: '$movie_name' from '$channel_name' $channel_emoji");
        }
    }
}

// ============================================
// 🎯 GROUP MESSAGE FILTER
// ============================================

/**
 * Check if message is valid movie query
 */
function is_valid_movie_query($text) {
    $text = strtolower(trim($text));
    
    // Allow commands
    if (strpos($text, '/') === 0) {
        return true;
    }
    
    // Skip very short messages
    if (strlen($text) < 3) {
        return false;
    }
    
    // Block common chat phrases
    $invalid_phrases = [
        'good morning', 'good night', 'hello', 'hi ', 'hey ', 'thank you', 'thanks',
        'welcome', 'bye', 'see you', 'ok ', 'okay', 'yes', 'no', 'maybe',
        'how are you', 'whats up', 'anyone', 'someone', 'everyone',
        'problem', 'issue', 'help', 'question', 'doubt', 'query',
        'please', 'sorry', 'excuse me', 'what happened'
    ];
    
    foreach ($invalid_phrases as $phrase) {
        if (strpos($text, $phrase) !== false) {
            return false;
        }
    }
    
    // Allow movie-related patterns
    $movie_patterns = [
        'movie', 'film', 'video', 'download', 'watch', 'hd', 'full', 'part',
        'series', 'episode', 'season', 'bollywood', 'hollywood', 'tamil',
        'telugu', 'malayalam', 'kannada', 'punjabi', 'marathi', 'bengali'
    ];
    
    foreach ($movie_patterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return true;
        }
    }
    
    // Allow movie-like text (3+ chars, alphanumeric with spaces and basic punctuation)
    if (preg_match('/^[a-zA-Z0-9\s\-\.\,]{3,}$/', $text)) {
        return true;
    }
    
    return false;
}

// ============================================
// 🚀 MAIN WEBHOOK PROCESSING
// ============================================

// Get incoming update
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    // Initialize cache
    get_cached_movies();
    
    // ============================================
    // 📨 PROCESS CHANNEL POSTS
    // ============================================
    if (isset($update['channel_post'])) {
        $message = $update['channel_post'];
        $message_id = $message['message_id'];
        $chat_id = $message['chat']['id'];
        
        process_channel_post($message, $chat_id, $message_id);
    }
    
    // ============================================
    // 👤 PROCESS USER MESSAGES
    // ============================================
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? trim($message['text']) : '';
        $chat_type = $message['chat']['type'] ?? 'private';
        
        // ============================================
        // 💬 REQUEST GROUP HANDLING
        // ============================================
        if ($chat_id == REQUEST_GROUP_ID) {
            if (!empty($text) && strpos($text, '/') !== 0) {
                $users_data = json_decode(file_get_contents(USERS_FILE), true);
                
                if (!isset($users_data['message_logs'])) {
                    $users_data['message_logs'] = [];
                }
                
                $users_data['message_logs'][] = [
                    'user_id' => $user_id,
                    'username' => $message['from']['username'] ?? '',
                    'first_name' => $message['from']['first_name'] ?? '',
                    'text' => $text,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'type' => 'request'
                ];
                
                file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
                
                // Log the request
                error_log("📝 Request from user $user_id: $text");
            }
        }
        
        // ============================================
        // 🛡️ GROUP MESSAGE FILTERING
        // ============================================
        if ($chat_type !== 'private' && $chat_id != REQUEST_GROUP_ID) {
            if (strpos($text, '/') === 0) {
                // Commands allowed
            } else {
                if (!is_valid_movie_query($text)) {
                    // Ignore invalid group messages
                    return;
                }
            }
        }
        
        // ============================================
        // 👤 USER MANAGEMENT
        // ============================================
        $users_data = json_decode(file_get_contents(USERS_FILE), true);
        
        if (!isset($users_data['users'][$user_id])) {
            $users_data['users'][$user_id] = [
                'first_name' => $message['from']['first_name'] ?? '',
                'last_name' => $message['from']['last_name'] ?? '',
                'username' => $message['from']['username'] ?? '',
                'language_code' => $message['from']['language_code'] ?? 'en',
                'joined' => date('Y-m-d H:i:s'),
                'last_active' => date('Y-m-d H:i:s'),
                'points' => 0,
                'total_searches' => 0
            ];
            
            $users_data['total_requests'] = ($users_data['total_requests'] ?? 0) + 1;
            file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
            
            update_stats('total_users', 1);
        }
        
        // Update last active time
        $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
        file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
        
        // ============================================
        // ⌨️ COMMAND PROCESSING
        // ============================================
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text, 2);
            $command = strtolower($parts[0]);
            $parameter = $parts[1] ?? '';
            
            switch ($command) {
                case '/start':
                case '/menu':
                    show_main_menu($chat_id, $user_id);
                    break;
                    
                case '/checkdate':
                    check_date($chat_id);
                    break;
                    
                case '/totalupload':
                case '/totaluploads':
                    $page = is_numeric($parameter) ? max(1, (int)$parameter) : 1;
                    total_uploads_controller($chat_id, $page);
                    break;
                    
                case '/checkcsv':
                    $show_all = (strtolower($parameter) == 'all');
                    show_csv_data($chat_id, $show_all);
                    break;
                    
                case '/channels':
                    simulateTyping($chat_id, 1);
                    $channels_list = create_header("OUR CHANNELS", CHANNEL_EMOJI);
                    
                    foreach ($ALL_CHANNELS as $id => $info) {
                        if ($info['type'] == 'movie') {
                            $username = $info['username'] ? " ($info[username])" : "";
                            $channels_list .= "• $info[emoji] <b>$info[name]</b>$username\n";
                        }
                    }
                    
                    $channels_list .= "\nTotal: <b>" . (count($ALL_CHANNELS) - 1) . "</b> movie channels";
                    
                    $keyboard = create_multi_button_row([
                        [
                            ['text' => 'Channel Stats', 'callback_data' => 'channels_stats', 'emoji' => CHANNEL_EMOJI, 'type' => 'channel'],
                            ['text' => 'Search Movies', 'callback_data' => 'new_search', 'emoji' => SEARCH_EMOJI, 'type' => 'search']
                        ]
                    ]);
                    
                    sendStyledMessage($chat_id, $channels_list, $keyboard, 'HTML');
                    break;
                    
                case '/stats':
                    if ($user_id == OWNER_ID) {
                        admin_stats($chat_id);
                    } else {
                        sendErrorMessage($chat_id, "This command is for admin only.");
                    }
                    break;
                    
                case '/help':
                    show_help($chat_id);
                    break;
                    
                case '/popular':
                    show_popular_movies($chat_id);
                    break;
                    
                case '/info':
                    show_bot_info($chat_id);
                    break;
                    
                default:
                    sendInfoMessage($chat_id, "Unknown Command",
                        "Command <code>$command</code> not recognized.\n\n" .
                        "Type /help to see available commands."
                    );
                    break;
            }
        }
        // ============================================
        // 🔍 SEARCH PROCESSING
        // ============================================
        elseif (!empty($text)) {
            advanced_search($chat_id, $text, $user_id);
        }
        // ============================================
        // 🎯 DEFAULT ACTION
        // ============================================
        else {
            show_main_menu($chat_id, $user_id);
        }
    }
    
    // ============================================
    // 🔘 CALLBACK QUERY PROCESSING
    // ============================================
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $message = $query['message'];
        $chat_id = $message['chat']['id'];
        $data = $query['data'];
        $user_id = $query['from']['id'] ?? null;
        
        global $movie_messages;
        
        simulateTyping($chat_id, 0.5);
        
        // ============================================
        // 🏠 MAIN MENU ACTIONS
        // ============================================
        if ($data == 'main_menu') {
            show_main_menu($chat_id, $user_id);
            answerCallbackQuery($query['id'], "Main menu loaded");
        }
        elseif ($data == 'new_search') {
            sendStyledMessage($chat_id, 
                SEARCH_EMOJI . " <b>Search Movies</b> " . SEARCH_EMOJI . "\n" .
                str_repeat("─", 30) . "\n\n" .
                "Type any movie name to search from all channels...\n\n" .
                "💡 <i>Examples: kgf, pushpa, avengers, hindi movie</i>",
                null, 'HTML'
            );
            answerCallbackQuery($query['id'], "Ready for search");
        }
        elseif ($data == 'show_help') {
            show_help($chat_id);
            answerCallbackQuery($query['id'], "Help guide");
        }
        elseif ($data == 'bot_info') {
            show_bot_info($chat_id);
            answerCallbackQuery($query['id'], "Bot information");
        }
        elseif ($data == 'popular_movies') {
            show_popular_movies($chat_id);
            answerCallbackQuery($query['id'], "Popular movies");
        }
        elseif ($data == 'channels_stats') {
            show_channels_stats($chat_id);
            answerCallbackQuery($query['id'], "Channel statistics");
        }
        elseif ($data == 'check_date') {
            check_date($chat_id);
            answerCallbackQuery($query['id'], "Date statistics");
        }
        elseif ($data == 'checkcsv_10') {
            show_csv_data($chat_id, false);
            answerCallbackQuery($query['id'], "CSV data (10 entries)");
        }
        elseif ($data == 'checkcsv_all') {
            show_csv_data($chat_id, true);
            answerCallbackQuery($query['id'], "CSV data (all entries)");
        }
        elseif ($data == 'admin_stats' && $user_id == OWNER_ID) {
            admin_stats($chat_id);
            answerCallbackQuery($query['id'], "Admin statistics");
        }
        elseif ($data == 'request_movie') {
            sendInfoMessage($chat_id, "Request Movie",
                "📝 <b>To request a movie:</b>\n\n" .
                "1. Join @EntertainmentTadka0786\n" .
                "2. Send the movie name\n" .
                "3. We'll add it asap!\n\n" .
                "🔔 You'll be notified automatically when it's available."
            );
            answerCallbackQuery($query['id'], "Request instructions");
        }
        // ============================================
        // 🎬 MOVIE SELECTION
        // ============================================
        elseif (strpos($data, 'movie_') === 0) {
            $movie_name = urldecode(substr($data, 6));
            $movie_key = strtolower($movie_name);
            
            if (isset($movie_messages[$movie_key])) {
                $entries = $movie_messages[$movie_key];
                $count = 0;
                
                foreach ($entries as $entry) {
                    deliver_item_to_chat($chat_id, $entry);
                    usleep(300000);
                    $count++;
                }
                
                sendSuccessMessage($chat_id,
                    "Movie <b>'$movie_name'</b> sent successfully!\n\n" .
                    "📊 Details:\n" .
                    "• Total Copies: <b>$count</b>\n" .
                    "• Channels: <b>" . count(array_unique(array_column($entries, 'channel_name'))) . "</b>\n\n" .
                    "⭐ Enjoy watching!",
                    create_multi_button_row([
                        [
                            ['text' => 'Search More', 'callback_data' => 'new_search', 'emoji' => SEARCH_EMOJI, 'type' => 'search'],
                            ['text' => 'Main Menu', 'callback_data' => 'main_menu', 'emoji' => '🏠', 'type' => 'primary']
                        ]
                    ])
                );
                answerCallbackQuery($query['id'], "✅ $count movies sent");
            } else {
                sendErrorMessage($chat_id, "Movie '$movie_name' not found.");
                answerCallbackQuery($query['id'], "❌ Movie not found");
            }
        }
        // ============================================
        // 📢 CHANNEL SELECTION
        // ============================================
        elseif (strpos($data, 'channel_select_') === 0) {
            $parts = explode('_', $data, 4);
            if (count($parts) >= 4) {
                $channel_id = $parts[2];
                $movie_query = urldecode($parts[3]);
                $channel_info = get_channel_info($channel_id);
                $channel_name = $channel_info['name'];
                $channel_emoji = $channel_info['emoji'];
                
                $count = deliver_from_channel($chat_id, $movie_query, $channel_id);
                
                if ($count > 0) {
                    sendSuccessMessage($chat_id,
                        "Movies from <b>$channel_emoji $channel_name</b> sent!\n\n" .
                        "🎬 Movie: <b>$movie_query</b>\n" .
                        "📊 Count: <b>$count</b> copies\n" .
                        "📢 Channel: $channel_name\n\n" .
                        "⭐ Enjoy your movies!",
                        create_multi_button_row([
                            [
                                ['text' => 'Search More', 'callback_data' => 'new_search', 'emoji' => SEARCH_EMOJI, 'type' => 'search'],
                                ['text' => 'Channel Stats', 'callback_data' => 'channels_stats', 'emoji' => CHANNEL_EMOJI, 'type' => 'channel']
                            ]
                        ])
                    );
                    answerCallbackQuery($query['id'], "✅ From $channel_name");
                } else {
                    sendErrorMessage($chat_id,
                        "No movies found in <b>$channel_emoji $channel_name</b> for <b>'$movie_query'</b>.\n\n" .
                        "💡 Try searching in other channels or request this movie."
                    );
                    answerCallbackQuery($query['id'], "❌ Not found in this channel");
                }
            }
        }
        // ============================================
        // 📄 PAGINATION HANDLING
        // ============================================
        elseif (strpos($data, 'total_uploads_') === 0) {
            $page = (int)str_replace('total_uploads_', '', $data);
            total_uploads_controller($chat_id, $page);
            answerCallbackQuery($query['id'], "Page $page");
        }
        elseif (strpos($data, 'page_prev_') === 0) {
            $page = (int)str_replace('page_prev_', '', $data);
            total_uploads_controller($chat_id, $page);
            answerCallbackQuery($query['id'], "Page $page");
        }
        elseif (strpos($data, 'page_next_') === 0) {
            $page = (int)str_replace('page_next_', '', $data);
            total_uploads_controller($chat_id, $page);
            answerCallbackQuery($query['id'], "Page $page");
        }
        elseif (strpos($data, 'tu_view_') === 0) {
            $page = (int)str_replace('tu_view_', '', $data);
            simulateTyping($chat_id, 1);
            
            $all = get_cached_movies();
            $paginated = paginate_movies($all, $page);
            
            forward_page_movies($chat_id, $paginated['slice']);
            
            sendSuccessMessage($chat_id,
                "Movies from page $page sent successfully!\n\n" .
                "📊 Sent: <b>" . count($paginated['slice']) . "</b> movies\n" .
                "📄 Page: $page/" . $paginated['total_pages'],
                create_multi_button_row([
                    [
                        ['text' => 'Continue Browsing', 'callback_data' => 'total_uploads_' . $page, 'emoji' => '📄', 'type' => 'info'],
                        ['text' => 'Main Menu', 'callback_data' => 'main_menu', 'emoji' => '🏠', 'type' => 'primary']
                    ]
                ])
            );
            answerCallbackQuery($query['id'], "✅ Page $page movies sent");
        }
        elseif ($data === 'tu_stop') {
            sendSuccessMessage($chat_id, "Pagination stopped successfully.", 
                create_multi_button_row([
                    [
                        ['text' => 'Main Menu', 'callback_data' => 'main_menu', 'emoji' => '🏠', 'type' => 'primary'],
                        ['text' => 'Search Movies', 'callback_data' => 'new_search', 'emoji' => SEARCH_EMOJI, 'type' => 'search']
                    ]
                ])
            );
            answerCallbackQuery($query['id'], "Stopped");
        }
        elseif ($data == 'page_info') {
            answerCallbackQuery($query['id'], "Current page", true);
        }
        // ============================================
        // ❓ UNKNOWN CALLBACK
        // ============================================
        else {
            sendErrorMessage($chat_id, "Invalid action or movie not found.");
            answerCallbackQuery($query['id'], "❌ Action failed");
        }
    }
    
    // ============================================
    // ⏰ SCHEDULED TASKS
    // ============================================
    $current_time = date('H:i');
    
    // Auto backup at midnight
    if ($current_time == '00:00') {
        auto_backup();
    }
    
    // Send daily digest at 8 AM
    if ($current_time == '08:00') {
        send_daily_digest();
    }
}

// ============================================
// 🛠️ MANUAL TESTING FUNCTIONS
// ============================================

// Test save function
if (isset($_GET['test_save'])) {
    function manual_save_to_csv($movie_name, $message_id, $channel_id = null, $channel_name = null) {
        if ($channel_id === null) {
            $channel_ids = array_keys($GLOBALS['ALL_CHANNELS']);
            $channel_id = $channel_ids[0];
        }
        
        $channel_info = get_channel_info($channel_id);
        if ($channel_name === null) $channel_name = $channel_info['name'];
        
        return append_movie($movie_name, $message_id, date('d-m-Y'), $channel_id, $channel_name);
    }
    
    // Add test movies
    $channels = array_keys($GLOBALS['ALL_CHANNELS']);
    
    manual_save_to_csv("Metro In Dino (2025)", 1924, $channels[0], get_channel_info($channels[0])['name']);
    manual_save_to_csv("Pushpa 2: The Rule (2024)", 1925, $channels[1], get_channel_info($channels[1])['name']);
    manual_save_to_csv("Kalki 2898 AD (2024)", 1926, $channels[2], get_channel_info($channels[2])['name']);
    
    echo "<h1>✅ Test Data Added Successfully!</h1>";
    echo "<p>3 test movies added to different channels</p>";
    echo "<p><a href='?check_csv=1'>📊 Check CSV Data</a> | ";
    echo "<a href='?setwebhook=1'>🔄 Reset Webhook</a> | ";
    echo "<a href='./'>🏠 Go to Bot</a></p>";
    exit;
}

// Check CSV content
if (isset($_GET['check_csv'])) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>📊 CSV Data - Entertainment Tadka Bot</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: rgba(255,255,255,0.1);
                padding: 30px;
                border-radius: 15px;
                backdrop-filter: blur(10px);
            }
            h1 {
                color: white;
                text-align: center;
            }
            .csv-table {
                width: 100%;
                background: white;
                color: #333;
                border-collapse: collapse;
                margin: 20px 0;
                border-radius: 10px;
                overflow: hidden;
            }
            .csv-table th {
                background: #667eea;
                color: white;
                padding: 12px;
                text-align: left;
            }
            .csv-table td {
                padding: 10px;
                border-bottom: 1px solid #ddd;
            }
            .csv-table tr:nth-child(even) {
                background: #f8f9fa;
            }
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 5px;
            }
            .btn:hover {
                opacity: 0.9;
            }
            .stats {
                background: rgba(255,255,255,0.2);
                padding: 15px;
                border-radius: 10px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>📊 CSV Database - Entertainment Tadka Bot</h1>";
    
    if (file_exists(CSV_FILE)) {
        $lines = file(CSV_FILE);
        $total_movies = count($lines) - 1; // Exclude header
        
        echo "<div class='stats'>
                <h3>📈 Database Statistics</h3>
                <p>Total Movies: <strong>$total_movies</strong></p>
                <p>Channels: <strong>" . count($GLOBALS['ALL_CHANNELS']) . "</strong></p>
                <p>Last Updated: <strong>" . date('Y-m-d H:i:s', filemtime(CSV_FILE)) . "</strong></p>
              </div>";
        
        if ($total_movies > 0) {
            echo "<div style='overflow-x:auto;'>
                    <table class='csv-table'>
                    <tr>";
            
            // Header
            $headers = explode(',', $lines[0]);
            foreach ($headers as $header) {
                echo "<th>" . htmlspecialchars(trim($header, "\"\n")) . "</th>";
            }
            echo "</tr>";
            
            // Data rows (show max 50 rows)
            $max_rows = min(50, $total_movies);
            for ($i = 1; $i <= $max_rows; $i++) {
                $cells = explode(',', $lines[$i]);
                echo "<tr>";
                foreach ($cells as $cell) {
                    echo "<td>" . htmlspecialchars(trim($cell, "\"\n")) . "</td>";
                }
                echo "</tr>";
            }
            
            echo "</table></div>";
            
            if ($total_movies > 50) {
                echo "<p>Showing first 50 of $total_movies movies. Use the bot to see all.</p>";
            }
        } else {
            echo "<p style='background: #ff6b6b; padding: 15px; border-radius: 5px;'>No movies in database yet.</p>";
        }
    } else {
        echo "<p style='background: #ff6b6b; padding: 15px; border-radius: 5px;'>❌ CSV file not found!</p>";
    }
    
    echo "<div style='margin-top:30px;'>
            <a href='?test_save=1' class='btn'>➕ Add Test Data</a>
            <a href='?setwebhook=1' class='btn'>🔄 Set Webhook</a>
            <a href='./' class='btn'>🏠 Back to Bot</a>
          </div>
        </div>
    </body>
    </html>";
    exit;
}

// Set webhook
if (php_sapi_name() === 'cli' || isset($_GET['setwebhook'])) {
    $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $result = apiRequest('setWebhook', ['url' => $webhook_url]);
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>🤖 Bot Setup - Entertainment Tadka</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                background: rgba(255,255,255,0.1);
                padding: 30px;
                border-radius: 15px;
                backdrop-filter: blur(10px);
            }
            h1 {
                text-align: center;
            }
            .card {
                background: rgba(255,255,255,0.2);
                padding: 20px;
                border-radius: 10px;
                margin: 15px 0;
            }
            .success {
                background: rgba(76, 175, 80, 0.3);
            }
            .channel-list {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 10px;
                margin: 20px 0;
            }
            .channel-card {
                background: rgba(255,255,255,0.1);
                padding: 15px;
                border-radius: 8px;
            }
            .btn {
                display: inline-block;
                padding: 12px 25px;
                background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 10px 5px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🤖 Entertainment Tadka Bot Setup</h1>
            
            <div class='card success'>
                <h2>✅ Webhook Setup Result</h2>
                <p><strong>Status:</strong> " . (strpos($result, '"ok":true') ? 'SUCCESS' : 'FAILED') . "</p>
                <p><strong>Webhook URL:</strong> <code>$webhook_url</code></p>
            </div>
            
            <div class='card'>
                <h2>📢 Bot Information</h2>
                <p><strong>Bot:</strong> @EntertainmentTadkaBot</p>
                <p><strong>Owner ID:</strong> " . OWNER_ID . "</p>
                <p><strong>Total Channels:</strong> " . count($GLOBALS['ALL_CHANNELS']) . "</p>
            </div>
            
            <div class='card'>
                <h2>📢 Connected Channels</h2>
                <div class='channel-list'>";
    
    foreach ($GLOBALS['ALL_CHANNELS'] as $id => $info) {
        $type = $info['type'] == 'movie' ? '🎬 Movie Channel' : '💬 Request Group';
        echo "<div class='channel-card'>
                <strong>{$info['emoji']} {$info['name']}</strong><br>
                <small>ID: $id</small><br>
                <small>Type: $type</small>
              </div>";
    }
    
    echo "      </div>
            </div>
            
            <div style='text-align:center; margin-top:30px;'>
                <a href='./' class='btn'>🏠 Go to Bot</a>
                <a href='?test_save=1' class='btn'>➕ Add Test Data</a>
                <a href='?check_csv=1' class='btn'>📊 Check CSV</a>
            </div>
        </div>
    </body>
    </html>";
    exit;
}

// ============================================
// 🏠 DEFAULT PAGE (NO UPDATE)
// ============================================

if (!isset($update) || !$update) {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>🎬 Entertainment Tadka Bot</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                min-height: 100vh;
            }
            .container {
                max-width: 1000px;
                margin: 0 auto;
                background: rgba(255,255,255,0.1);
                padding: 30px;
                border-radius: 20px;
                backdrop-filter: blur(10px);
            }
            h1 {
                text-align: center;
                font-size: 2.5em;
                margin-bottom: 10px;
            }
            .tagline {
                text-align: center;
                font-size: 1.2em;
                opacity: 0.9;
                margin-bottom: 30px;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            .stat-card {
                background: rgba(255,255,255,0.15);
                padding: 20px;
                border-radius: 10px;
                text-align: center;
            }
            .stat-number {
                font-size: 2.5em;
                font-weight: bold;
                margin: 10px 0;
            }
            .channel-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 15px;
                margin: 30px 0;
            }
            .channel-card {
                background: rgba(255,255,255,0.1);
                padding: 15px;
                border-radius: 10px;
            }
            .commands {
                background: rgba(0,0,0,0.2);
                padding: 25px;
                border-radius: 15px;
                margin: 30px 0;
            }
            .command-list {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
                margin-top: 20px;
            }
            .command-item {
                background: rgba(255,255,255,0.1);
                padding: 15px;
                border-radius: 8px;
                font-family: 'Courier New', monospace;
            }
            .btn-group {
                text-align: center;
                margin-top: 30px;
            }
            .btn {
                display: inline-block;
                padding: 15px 30px;
                margin: 10px;
                background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
                color: white;
                text-decoration: none;
                border-radius: 50px;
                font-weight: bold;
            }
            .footer {
                text-align: center;
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid rgba(255,255,255,0.2);
                opacity: 0.8;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🎬 ENTERTAINMENT TADKA BOT</h1>
            <p class='tagline'>Multi-Channel Movie Bot with Beautiful UI</p>
            
            <div class='stats-grid'>
                <div class='stat-card'>
                    <div>Total Movies</div>
                    <div class='stat-number'>" . ($stats['total_movies'] ?? 0) . "</div>
                </div>
                <div class='stat-card'>
                    <div>Total Users</div>
                    <div class='stat-number'>" . count($users_data['users'] ?? []) . "</div>
                </div>
                <div class='stat-card'>
                    <div>Total Searches</div>
                    <div class='stat-number'>" . ($stats['total_searches'] ?? 0) . "</div>
                </div>
                <div class='stat-card'>
                    <div>Active Channels</div>
                    <div class='stat-number'>" . count($GLOBALS['ALL_CHANNELS']) . "</div>
                </div>
            </div>
            
            <h2>📢 Connected Channels</h2>
            <div class='channel-grid'>";
    
    foreach ($GLOBALS['ALL_CHANNELS'] as $id => $info) {
        $channel_stats = $stats['channels_stats'][$id] ?? ['total_movies' => 0];
        echo "<div class='channel-card'>
                <strong>{$info['emoji']} {$info['name']}</strong><br>
                <small>Movies: {$channel_stats['total_movies']}</small><br>
                <small>ID: $id</small>
              </div>";
    }
    
    echo "  </div>
            
            <div class='commands'>
                <h2>📋 Available Commands</h2>
                <div class='command-list'>
                    <div class='command-item'><code>/start</code> - Main menu</div>
                    <div class='command-item'><code>/menu</code> - Show menu</div>
                    <div class='command-item'><code>/channels</code> - Channel list</div>
                    <div class='command-item'><code>/popular</code> - Popular movies</div>
                    <div class='command-item'><code>/checkdate</code> - Date stats</div>
                    <div class='command-item'><code>/totalupload</code> - All uploads</div>
                    <div class='command-item'><code>/checkcsv</code> - CSV data</div>
                    <div class='command-item'><code>/help</code> - Help guide</div>
                    <div class='command-item'><code>/info</code> - Bot information</div>
                </div>
            </div>
            
            <div class='btn-group'>
                <a href='?setwebhook=1' class='btn'>🔄 Set Webhook</a>
                <a href='?test_save=1' class='btn'>➕ Add Test Data</a>
                <a href='?check_csv=1' class='btn'>📊 Check CSV</a>
            </div>
            
            <div class='footer'>
                <p>🤖 Bot: @EntertainmentTadkaBot | 📢 Channel: @EntertainmentTadka786</p>
                <p>💬 Requests: @EntertainmentTadka0786 | 👑 Owner: " . OWNER_ID . "</p>
                <p>✨ Version 3.0 - Multi-Channel with Beautiful UI</p>
            </div>
        </div>
    </body>
    </html>";
}

// ============================================
// 🎬 END OF FILE
// ============================================
?>
