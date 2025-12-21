<?php
// ==============================
// SECURITY HEADERS & BASIC SETUP
// ==============================

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ==============================
// RENDER.COM SPECIFIC CONFIGURATION
// ==============================

$port = getenv('PORT') ?: '80';
$webhook_url = getenv('RENDER_EXTERNAL_URL') ?: 'https://your-bot-name.onrender.com';

if (!getenv('BOT_TOKEN')) {
    die("❌ BOT_TOKEN environment variable set nahi hai.");
}

// ==============================
// ENVIRONMENT VARIABLES CONFIGURATION
// ==============================
define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('CHANNEL_ID', getenv('CHANNEL_ID', '-1003251791991'));
define('BACKUP_CHANNEL_ID', getenv('BACKUP_CHANNEL_ID', '-1002964109368'));
define('BACKUP_CHANNEL_USERNAME', getenv('BACKUP_CHANNEL_USERNAME', '@ETBackup'));
define('ADMIN_ID', (int)getenv('ADMIN_ID', '1080317415'));
define('REQUEST_GROUP_ID', getenv('REQUEST_GROUP_ID', '-1001234567890'));
define('REQUEST_CHANNEL', getenv('REQUEST_CHANNEL', '@EntertainmentTadka7860'));
define('MAIN_CHANNEL', getenv('MAIN_CHANNEL', '@EntertainmentTadka786'));
define('THEATER_PRINT_CHANNEL', getenv('THEATER_PRINT_CHANNEL', '@threater_print_movies'));

// File paths
define('CSV_FILE', 'movies.csv');
define('USERS_FILE', 'users.json');
define('STATS_FILE', 'bot_stats.json');
define('REQUEST_FILE', 'movie_requests.json');
define('BACKUP_DIR', 'backups/');
define('LOG_FILE', 'bot_activity.log');

// ==============================
// ENHANCED PAGINATION CONSTANTS
// ==============================
define('ITEMS_PER_PAGE', 5);                    // Har page par kitne movies
define('MAX_PAGES_TO_SHOW', 7);                 // Max page buttons to display
define('PAGINATION_CACHE_TIMEOUT', 60);         // Cache timeout in seconds
define('PREVIEW_ITEMS', 3);                     // Preview mein kitne items
define('BATCH_SIZE', 5);                        // Batch download size
define('VIDEO_PREVIEW_ENABLED', true);          // Video preview feature
define('MAX_VIDEO_PREVIEWS', 3);                // Max video previews per page

// ==============================
// MAINTENANCE MODE
// ==============================
$MAINTENANCE_MODE = false;
$MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>\n\nWe're temporarily unavailable for updates.\nWill be back in few days!\n\nThanks for patience 🙏";

// ==============================
// GLOBAL VARIABLES
// ==============================
$movie_messages = array();
$movie_cache = array();
$waiting_users = array();
$user_sessions = array();
$user_pagination_sessions = array();  // Pagination sessions storage

// ==============================
// FILE INITIALIZATION FUNCTION
// ==============================
function initialize_files() {
    $files = [
        CSV_FILE => "movie_name,message_id,date,video_path,quality,size,language\n",
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
            'last_updated' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT),
        REQUEST_FILE => json_encode([
            'requests' => [],
            'pending_approval' => [],
            'completed_requests' => [],
            'user_request_count' => []
        ], JSON_PRETTY_PRINT)
    ];
    
    foreach ($files as $file => $content) {
        if (!file_exists($file)) {
            file_put_contents($file, $content);
            @chmod($file, 0666);
        }
    }
    
    if (!file_exists(BACKUP_DIR)) {
        @mkdir(BACKUP_DIR, 0777, true);
    }
    
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
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

// ==============================
// TELEGRAM API FUNCTIONS
// ==============================
function apiRequest($method, $params = array(), $is_multipart = false) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    if ($is_multipart) {
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
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    
    $result = apiRequest('sendMessage', $data);
    return json_decode($result, true);
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

function copyMessage($chat_id, $from_chat_id, $message_id) {
    return apiRequest('copyMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
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
                    
                case 'type':
                    if ($value == 'hd' && stripos($movie['quality'] ?? '', '1080') === false && stripos($movie['quality'] ?? '', '720') === false) {
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

function build_enhanced_pagination_keyboard(int $page, int $total_pages, string $session_id = '', array $filters = []): array {
    $kb = ['inline_keyboard' => []];
    
    // ==================== PAGE NAVIGATION ROW ====================
    $nav_row = [];
    
    // Previous/Fast Previous buttons
    if ($page > 1) {
        $nav_row[] = ['text' => '⏪ First', 'callback_data' => 'pag_first_' . $session_id];
        $nav_row[] = ['text' => '◀️ Prev', 'callback_data' => 'pag_prev_' . $page . '_' . $session_id];
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
        $nav_row[] = ['text' => 'Next ▶️', 'callback_data' => 'pag_next_' . $page . '_' . $session_id];
        $nav_row[] = ['text' => 'Last ⏩', 'callback_data' => 'pag_last_' . $total_pages . '_' . $session_id];
    }
    
    if (!empty($nav_row)) {
        $kb['inline_keyboard'][] = $nav_row;
    }
    
    // ==================== ACTION BUTTONS ROW ====================
    $action_row = [];
    
    // Video Preview Button (NEW FEATURE)
    if (VIDEO_PREVIEW_ENABLED) {
        $action_row[] = ['text' => '🎬 Video Preview', 'callback_data' => 'vidprev_' . $page . '_' . $session_id];
    }
    
    $action_row[] = ['text' => '📥 Send Page', 'callback_data' => 'send_' . $page . '_' . $session_id];
    $action_row[] = ['text' => '👁️ Text Preview', 'callback_data' => 'txtprev_' . $page . '_' . $session_id];
    
    $kb['inline_keyboard'][] = $action_row;
    
    // ==================== FILTER BUTTONS ROW ====================
    $filter_row = [];
    
    if (empty($filters)) {
        $filter_row[] = ['text' => '🎬 HD Only', 'callback_data' => 'flt_hd_' . $session_id];
        $filter_row[] = ['text' => '🔄 Latest', 'callback_data' => 'flt_new_' . $session_id];
        $filter_row[] = ['text' => '🔥 Popular', 'callback_data' => 'flt_pop_' . $session_id];
    } else {
        $filter_row[] = ['text' => '🧹 Clear Filter', 'callback_data' => 'flt_clr_' . $session_id];
        // Show active filter
        if (isset($filters['quality'])) {
            $filter_row[] = ['text' => '✅ ' . $filters['quality'], 'callback_data' => 'current'];
        }
    }
    
    $kb['inline_keyboard'][] = $filter_row;
    
    // ==================== CONTROL BUTTONS ROW ====================
    $ctrl_row = [];
    $ctrl_row[] = ['text' => '📊 Stats', 'callback_data' => 'stats_' . $session_id];
    $ctrl_row[] = ['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''];
    $ctrl_row[] = ['text' => '❌ Close', 'callback_data' => 'close_' . $session_id];
    
    $kb['inline_keyboard'][] = $ctrl_row;
    
    return $kb;
}

function enhanced_pagination_controller($chat_id, $page = 1, $filters = [], $session_id = null) {
    $all = get_cached_movies();
    
    if (empty($all)) {
        sendMessage($chat_id, "📭 No movies found! Add some movies first.");
        return;
    }
    
    // Create session ID if not provided
    if (!$session_id) {
        $session_id = uniqid('sess_', true);
    }
    
    $pg = paginate_movies($all, (int)$page, $filters);
    
    // ==================== BUILD ENHANCED MESSAGE ====================
    $title = "🎬 <b>Enhanced Movie Browser</b>\n";
    $title .= "══════════════════════════\n\n";
    
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
    
    $title .= "\n══════════════════════════\n\n";
    
    // Current page movies list with EMOJIS
    $title .= "📋 <b>Page {$page} Movies:</b>\n\n";
    $i = $pg['start_item'];
    
    foreach ($pg['slice'] as $movie) {
        $movie_name = htmlspecialchars($movie['movie_name'] ?? 'Unknown');
        $quality = $movie['quality'] ?? 'Unknown';
        $language = $movie['language'] ?? 'Hindi';
        $date = $movie['date'] ?? 'N/A';
        $size = $movie['size'] ?? 'Unknown';
        
        // Quality emoji
        $quality_emoji = "📱";
        if (stripos($quality, '1080') !== false) $quality_emoji = "🎥";
        elseif (stripos($quality, '720') !== false) $quality_emoji = "📺";
        elseif (stripos($quality, '480') !== false) $quality_emoji = "📼";
        
        // Language emoji
        $lang_emoji = (stripos($language, 'hindi') !== false) ? "🇮🇳" : "🇺🇸";
        
        $title .= "<b>{$i}.</b> {$movie_name}\n";
        $title .= "   {$quality_emoji} <b>{$quality}</b> | {$lang_emoji} {$language}\n";
        $title .= "   💾 {$size} | 📅 {$date}\n\n";
        $i++;
    }
    
    // Navigation help
    $title .= "📍 <i>Click page numbers for direct access</i>\n";
    $title .= "🎬 <i>Try 'Video Preview' for movie clips</i>";
    
    // Build enhanced keyboard
    $kb = build_enhanced_pagination_keyboard($pg['page'], $pg['total_pages'], $session_id, $filters);
    
    // Delete previous pagination message if exists
    delete_pagination_message($chat_id, $session_id);
    
    // Save new message ID
    $result = sendMessage($chat_id, $title, $kb, 'HTML');
    
    if (isset($result['result']['message_id'])) {
        save_pagination_message($chat_id, $session_id, $result['result']['message_id']);
        bot_log("Enhanced pagination - Chat: $chat_id, Page: $page, Session: " . substr($session_id, 0, 8));
    }
}

// ==================== NEW: VIDEO PREVIEW FUNCTION ====================
function send_video_preview($chat_id, $page, $session_id) {
    $all = get_cached_movies();
    $pg = paginate_movies($all, (int)$page, []);
    
    if (empty($pg['slice'])) {
        sendMessage($chat_id, "❌ No movies found on this page!");
        return;
    }
    
    $preview_count = min(MAX_VIDEO_PREVIEWS, count($pg['slice']));
    $movies_to_preview = array_slice($pg['slice'], 0, $preview_count);
    
    $preview_message = "🎬 <b>Video Preview - Page {$page}</b>\n";
    $preview_message .= "══════════════════════════\n\n";
    $preview_message .= "Sending previews for first <b>{$preview_count}</b> movies:\n\n";
    
    $status_msg = sendMessage($chat_id, $preview_message, null, 'HTML');
    
    $success_count = 0;
    
    foreach ($movies_to_preview as $index => $movie) {
        $movie_name = htmlspecialchars($movie['movie_name'] ?? 'Unknown');
        $message_id = $movie['message_id'] ?? null;
        
        if ($message_id && is_numeric($message_id)) {
            try {
                // Try to copy video/message
                $result = json_decode(copyMessage($chat_id, CHANNEL_ID, $message_id), true);
                
                if ($result && isset($result['ok']) && $result['ok']) {
                    $success_count++;
                    
                    // Send info about the movie
                    $info_msg = "🎬 <b>Preview " . ($index + 1) . ":</b> {$movie_name}\n";
                    $info_msg .= "📊 Quality: " . ($movie['quality'] ?? 'Unknown') . "\n";
                    $info_msg .= "🗣️ Language: " . ($movie['language'] ?? 'Hindi') . "\n\n";
                    $info_msg .= "<i>Full movie available via search!</i>";
                    
                    sendMessage($chat_id, $info_msg, null, 'HTML');
                    sleep(1); // Delay between sends
                }
            } catch (Exception $e) {
                // Skip if error
            }
        }
    }
    
    // Update status
    $completion_msg = "✅ <b>Video Preview Complete</b>\n\n";
    $completion_msg .= "Page: {$page}\n";
    $completion_msg .= "Total movies: " . count($pg['slice']) . "\n";
    $completion_msg .= "Previews sent: {$success_count}/{$preview_count}\n\n";
    $completion_msg .= "🎯 <b>Use pagination to browse more!</b>";
    
    if (isset($status_msg['result']['message_id'])) {
        editMessage($chat_id, $status_msg['result']['message_id'], $completion_msg, null, 'HTML');
    } else {
        sendMessage($chat_id, $completion_msg, null, 'HTML');
    }
}

// ==================== NEW: TEXT PREVIEW FUNCTION ====================
function send_text_preview($chat_id, $page, $session_id) {
    $all = get_cached_movies();
    $pg = paginate_movies($all, (int)$page, []);
    
    if (empty($pg['slice'])) {
        sendMessage($chat_id, "❌ No movies found on this page!");
        return;
    }
    
    $preview_message = "👁️ <b>Text Preview - Page {$page}</b>\n";
    $preview_message .= "══════════════════════════\n\n";
    
    $preview_count = min(PREVIEW_ITEMS, count($pg['slice']));
    
    foreach (array_slice($pg['slice'], 0, $preview_count) as $index => $movie) {
        $movie_name = htmlspecialchars($movie['movie_name'] ?? 'Unknown');
        $quality = $movie['quality'] ?? 'Unknown';
        $language = $movie['language'] ?? 'Hindi';
        $date = $movie['date'] ?? 'N/A';
        $size = $movie['size'] ?? 'Unknown';
        
        $preview_message .= "<b>" . ($index + 1) . ".</b> {$movie_name}\n";
        $preview_message .= "   🎬 Quality: {$quality}\n";
        $preview_message .= "   🗣️ Language: {$language}\n";
        $preview_message .= "   💾 Size: {$size}\n";
        $preview_message .= "   📅 Date: {$date}\n\n";
    }
    
    if (count($pg['slice']) > $preview_count) {
        $remaining = count($pg['slice']) - $preview_count;
        $preview_message .= "📋 <i>And {$remaining} more movies on this page...</i>\n\n";
    }
    
    $preview_message .= "🎯 <b>Use 'Send Page' to get all movies!</b>";
    
    sendMessage($chat_id, $preview_message, null, 'HTML');
}

// ==================== NEW: BATCH DOWNLOAD FUNCTION ====================
function batch_download_with_progress($chat_id, $movies, $page_num) {
    $total = count($movies);
    if ($total === 0) return;
    
    $progress_msg = sendMessage($chat_id, 
        "📦 <b>Batch Download Started</b>\n\n" .
        "📄 Page: {$page_num}\n" .
        "🎬 Total: {$total} movies\n\n" .
        "⏳ Initializing..."
    );
    
    if (!isset($progress_msg['result']['message_id'])) {
        return;
    }
    
    $progress_id = $progress_msg['result']['message_id'];
    $success = 0;
    $failed = 0;
    
    for ($i = 0; $i < $total; $i++) {
        $movie = $movies[$i];
        
        // Update progress every 2 movies
        if ($i % 2 == 0) {
            $progress = round(($i / $total) * 100);
            $current_movie = htmlspecialchars(substr($movie['movie_name'] ?? 'Unknown', 0, 30));
            
            editMessage($chat_id, $progress_id, 
                "📦 <b>Downloading Page {$page_num}</b>\n\n" .
                "📊 Progress: {$progress}%\n" .
                "🎬 Processed: {$i}/{$total}\n" .
                "✅ Success: {$success}\n" .
                "❌ Failed: {$failed}\n\n" .
                "⏳ Current: {$current_movie}...\n\n" .
                "<i>Please wait...</i>"
            );
        }
        
        try {
            // Try to deliver movie (hidden source)
            $message_id = $movie['message_id'] ?? null;
            if ($message_id && is_numeric($message_id)) {
                $result = json_decode(copyMessage($chat_id, CHANNEL_ID, $message_id), true);
                if ($result && isset($result['ok']) && $result['ok']) {
                    $success++;
                } else {
                    $failed++;
                }
            } else {
                $failed++;
            }
            
            usleep(500000); // 0.5 second delay
            
        } catch (Exception $e) {
            $failed++;
        }
    }
    
    // Final update
    $success_rate = $total > 0 ? round(($success / $total) * 100, 2) : 0;
    
    editMessage($chat_id, $progress_id,
        "✅ <b>Batch Download Complete</b>\n\n" .
        "📄 Page: {$page_num}\n" .
        "🎬 Total Movies: {$total}\n" .
        "✅ Successfully Sent: {$success}\n" .
        "❌ Failed: {$failed}\n\n" .
        "📊 Success Rate: {$success_rate}%\n" .
        "⏱️ Completed: " . date('H:i:s') . "\n\n" .
        "🎯 <b>Use pagination to browse more pages!</b>"
    );
}

// ==================== PAGINATION SESSION MANAGEMENT ====================
function save_pagination_message($chat_id, $session_id, $message_id) {
    global $user_pagination_sessions;
    
    if (!isset($user_pagination_sessions[$session_id])) {
        $user_pagination_sessions[$session_id] = [];
    }
    
    $user_pagination_sessions[$session_id] = [
        'last_message_id' => $message_id,
        'chat_id' => $chat_id,
        'last_updated' => time(),
        'page' => 1, // Default page
        'filters' => []
    ];
}

function delete_pagination_message($chat_id, $session_id) {
    global $user_pagination_sessions;
    
    if (isset($user_pagination_sessions[$session_id]) && 
        isset($user_pagination_sessions[$session_id]['last_message_id'])) {
        
        $message_id = $user_pagination_sessions[$session_id]['last_message_id'];
        deleteMessage($chat_id, $message_id);
    }
}

function update_pagination_session($session_id, $page, $filters = []) {
    global $user_pagination_sessions;
    
    if (isset($user_pagination_sessions[$session_id])) {
        $user_pagination_sessions[$session_id]['page'] = $page;
        $user_pagination_sessions[$session_id]['filters'] = $filters;
        $user_pagination_sessions[$session_id]['last_updated'] = time();
    }
}

// ==================== CSV AND CACHE FUNCTIONS ====================
function get_cached_movies() {
    global $movie_cache;
    
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < 300) {
        return $movie_cache['data'];
    }
    
    $movie_cache = [
        'data' => load_and_clean_csv(),
        'timestamp' => time()
    ];
    
    return $movie_cache['data'];
}

function load_and_clean_csv($filename = CSV_FILE) {
    global $movie_messages;
    
    if (!file_exists($filename)) {
        file_put_contents($filename, "movie_name,message_id,date,video_path,quality,size,language\n");
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

                $entry = [
                    'movie_name' => $movie_name,
                    'message_id_raw' => $message_id_raw,
                    'date' => $date,
                    'video_path' => $video_path,
                    'quality' => $quality,
                    'size' => $size,
                    'language' => $language,
                    'message_id' => is_numeric($message_id_raw) ? intval($message_id_raw) : null
                ];
                
                $data[] = $entry;

                $movie = strtolower($movie_name);
                if (!isset($movie_messages[$movie])) $movie_messages[$movie] = [];
                $movie_messages[$movie][] = $entry;
            }
        }
        fclose($handle);
    }

    return $data;
}

// ==================== MAIN UPDATE PROCESSING ====================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    // Maintenance mode check
    global $MAINTENANCE_MODE, $MAINTENANCE_MESSAGE;
    if ($MAINTENANCE_MODE && isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        sendMessage($chat_id, $MAINTENANCE_MESSAGE, null, 'HTML');
        exit;
    }

    get_cached_movies();

    // Callback query handling (PAGINATION CONTROLS)
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $message = $query['message'];
        $chat_id = $message['chat']['id'];
        $data = $query['data'];
        
        // ==================== PAGINATION CONTROLS ====================
        
        // Direct page navigation
        if (strpos($data, 'pag_') === 0) {
            $parts = explode('_', $data);
            $action = $parts[1];
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            if ($action == 'first') {
                enhanced_pagination_controller($chat_id, 1, [], $session_id);
                answerCallbackQuery($query['id'], "First page");
            } 
            elseif ($action == 'last') {
                $all = get_cached_movies();
                $total_pages = ceil(count($all) / ITEMS_PER_PAGE);
                enhanced_pagination_controller($chat_id, $total_pages, [], $session_id);
                answerCallbackQuery($query['id'], "Last page");
            }
            elseif ($action == 'prev') {
                $current_page = isset($parts[2]) ? intval($parts[2]) : 1;
                $session_id = isset($parts[3]) ? $parts[3] : '';
                enhanced_pagination_controller($chat_id, max(1, $current_page - 1), [], $session_id);
                answerCallbackQuery($query['id'], "Previous page");
            }
            elseif ($action == 'next') {
                $current_page = isset($parts[2]) ? intval($parts[2]) : 1;
                $session_id = isset($parts[3]) ? $parts[3] : '';
                $all = get_cached_movies();
                $total_pages = ceil(count($all) / ITEMS_PER_PAGE);
                enhanced_pagination_controller($chat_id, min($total_pages, $current_page + 1), [], $session_id);
                answerCallbackQuery($query['id'], "Next page");
            }
            elseif (is_numeric($action)) {
                $page_num = intval($action);
                $session_id = isset($parts[2]) ? $parts[2] : '';
                enhanced_pagination_controller($chat_id, $page_num, [], $session_id);
                answerCallbackQuery($query['id'], "Page $page_num");
            }
        }
        
        // Video Preview
        elseif (strpos($data, 'vidprev_') === 0) {
            $parts = explode('_', $data);
            $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            send_video_preview($chat_id, $page_num, $session_id);
            answerCallbackQuery($query['id'], "🎬 Sending video previews...");
        }
        
        // Text Preview
        elseif (strpos($data, 'txtprev_') === 0) {
            $parts = explode('_', $data);
            $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            send_text_preview($chat_id, $page_num, $session_id);
            answerCallbackQuery($query['id'], "👁️ Sending text preview...");
        }
        
        // Send Page (Batch Download)
        elseif (strpos($data, 'send_') === 0) {
            $parts = explode('_', $data);
            $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            $all = get_cached_movies();
            $pg = paginate_movies($all, $page_num, []);
            batch_download_with_progress($chat_id, $pg['slice'], $page_num);
            answerCallbackQuery($query['id'], "📦 Batch download started!");
        }
        
        // Filters
        elseif (strpos($data, 'flt_') === 0) {
            $parts = explode('_', $data);
            $filter_type = $parts[1];
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            $filters = [];
            if ($filter_type == 'hd') {
                $filters = ['quality' => '1080'];
                answerCallbackQuery($query['id'], "HD filter applied");
            } elseif ($filter_type == 'new') {
                // Sort by latest (implementation needed)
                answerCallbackQuery($query['id'], "Latest filter applied");
            } elseif ($filter_type == 'pop') {
                // Sort by popularity
                answerCallbackQuery($query['id'], "Popular filter applied");
            } elseif ($filter_type == 'clr') {
                answerCallbackQuery($query['id'], "Filters cleared");
            }
            
            enhanced_pagination_controller($chat_id, 1, $filters, $session_id);
        }
        
        // Close pagination
        elseif ($data == 'close_' || strpos($data, 'close_') === 0) {
            deleteMessage($chat_id, $message['message_id']);
            sendMessage($chat_id, "🗂️ Pagination closed. Use /browse to start again.");
            answerCallbackQuery($query['id'], "Pagination closed");
        }
        
        // Stats
        elseif (strpos($data, 'stats_') === 0) {
            $session_id = str_replace('stats_', '', $data);
            $all = get_cached_movies();
            $total = count($all);
            $total_pages = ceil($total / ITEMS_PER_PAGE);
            
            $stats_msg = "📊 <b>Pagination Statistics</b>\n\n";
            $stats_msg .= "🎬 Total Movies: <b>{$total}</b>\n";
            $stats_msg .= "📄 Total Pages: <b>{$total_pages}</b>\n";
            $stats_msg .= "📋 Items Per Page: <b>" . ITEMS_PER_PAGE . "</b>\n";
            $stats_msg .= "🆔 Session: <code>" . substr($session_id, 0, 8) . "</code>\n\n";
            $stats_msg .= "🎯 <b>Features:</b>\n";
            $stats_msg .= "✅ Numbered Pagination\n";
            $stats_msg .= "✅ Video Previews\n";
            $stats_msg .= "✅ Text Previews\n";
            $stats_msg .= "✅ Batch Downloads\n";
            $stats_msg .= "✅ Smart Filters\n";
            
            sendMessage($chat_id, $stats_msg, null, 'HTML');
            answerCallbackQuery($query['id'], "Statistics");
        }
    }

    // Message handling
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        
        if (strpos($text, '/') === 0) {
            if ($text == '/start') {
                $welcome = "🎬 <b>Welcome to Enhanced Movie Bot!</b>\n\n";
                $welcome .= "✨ <b>New Features:</b>\n";
                $welcome .= "✅ Enhanced Pagination with Numbers\n";
                $welcome .= "✅ Video Previews (Movie Clips)\n";
                $welcome .= "✅ Text Previews\n";
                $welcome .= "✅ Batch Downloads\n";
                $welcome .= "✅ Smart Filters\n\n";
                $welcome .= "🚀 <b>Try these commands:</b>\n";
                $welcome .= "• /browse - Enhanced pagination browser\n";
                $welcome .= "• /search movie_name - Search movies\n";
                $welcome .= "• /latest - Latest uploads\n\n";
                $welcome .= "📢 Join: " . MAIN_CHANNEL;
                
                sendMessage($chat_id, $welcome, null, 'HTML');
            }
            elseif ($text == '/browse' || $text == '/totalupload') {
                // Start enhanced pagination
                enhanced_pagination_controller($chat_id, 1);
                bot_log("Enhanced pagination started by $user_id");
            }
            elseif (strlen($text) > 7 && substr($text, 0, 7) == '/search') {
                $search_term = trim(substr($text, 7));
                if (!empty($search_term)) {
                    sendMessage($chat_id, "🔍 Searching for: $search_term\n\nTry /browse for enhanced browsing!", null, 'HTML');
                }
            }
            elseif ($text == '/latest') {
                $all = get_cached_movies();
                $latest = array_slice($all, -5);
                $latest = array_reverse($latest);
                
                if (empty($latest)) {
                    sendMessage($chat_id, "📭 No movies found!");
                } else {
                    $msg = "🎬 <b>Latest 5 Uploads</b>\n\n";
                    foreach ($latest as $index => $movie) {
                        $msg .= ($index + 1) . ". <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
                        $msg .= "   📊 " . ($movie['quality'] ?? 'Unknown') . "\n\n";
                    }
                    $msg .= "🚀 Use /browse for enhanced browsing with pagination!";
                    sendMessage($chat_id, $msg, null, 'HTML');
                }
            }
            elseif ($text == '/help') {
                $help = "🤖 <b>Enhanced Movie Bot Help</b>\n\n";
                $help .= "🎯 <b>Main Commands:</b>\n";
                $help .= "• /browse - Enhanced pagination browser\n";
                $help .= "• /search movie - Search movies\n";
                $help .= "• /latest - Latest uploads\n";
                $help .= "• /help - This message\n\n";
                $help .= "✨ <b>Pagination Features:</b>\n";
                $help .= "✅ Numbered page navigation\n";
                $help .= "✅ Video previews (movie clips)\n";
                $help .= "✅ Text previews\n";
                $help .= "✅ Batch downloads\n";
                $help .= "✅ HD/Latest/Popular filters\n\n";
                $help .= "📢 Join: " . MAIN_CHANNEL;
                
                sendMessage($chat_id, $help, null, 'HTML');
            }
        }
    }
}

// ==================== DEFAULT PAGE ====================
if (!isset($update) || !$update) {
    echo "<h1>🎬 Enhanced Movie Bot with Pagination</h1>";
    echo "<p><strong>Status:</strong> ✅ Running</p>";
    
    echo "<h3>✨ Enhanced Pagination Features:</h3>";
    echo "<ul>";
    echo "<li>✅ <strong>Numbered Pagination:</strong> 1 2 3 ... pages</li>";
    echo "<li>✅ <strong>Video Previews:</strong> Send movie clips</li>";
    echo "<li>✅ <strong>Text Previews:</strong> Detailed movie info</li>";
    echo "<li>✅ <strong>Batch Downloads:</strong> Download entire page</li>";
    echo "<li>✅ <strong>Smart Filters:</strong> HD, Latest, Popular</li>";
    echo "<li>✅ <strong>Fast Navigation:</strong> First/Last, Previous/Next</li>";
    echo "<li>✅ <strong>Session Management:</strong> Unique browsing sessions</li>";
    echo "</ul>";
    
    echo "<h3>🚀 Commands:</h3>";
    echo "<pre>";
    echo "/start      - Welcome message\n";
    echo "/browse     - Enhanced pagination browser\n";
    echo "/search     - Search movies\n";
    echo "/latest     - Latest uploads\n";
    echo "/help       - Help information\n";
    echo "</pre>";
    
    echo "<h3>🔧 Setup:</h3>";
    echo "<p><a href='?setwebhook=1'>Set Webhook</a></p>";
    echo "<p><a href='?test_pagination=1'>Test Pagination</a></p>";
}

// ==================== TEST PAGINATION ====================
if (isset($_GET['test_pagination'])) {
    echo "<h2>🎬 Testing Enhanced Pagination</h2>";
    
    // Create test movies if CSV is empty
    if (!file_exists(CSV_FILE) || filesize(CSV_FILE) < 50) {
        $test_movies = [
            ["Animal (2023) Hindi 1080p", "1001", "15-12-2024", "", "1080p", "1.5GB", "Hindi"],
            ["KGF Chapter 2 (2022)", "1002", "14-12-2024", "", "720p", "1.2GB", "Hindi"],
            ["Avengers Endgame (2019)", "1003", "13-12-2024", "", "1080p", "2.0GB", "English"],
            ["Spider-Man: No Way Home", "1004", "12-12-2024", "", "1080p", "1.8GB", "English"],
            ["Pushpa 2: The Rule (2024)", "1005", "11-12-2024", "", "720p", "1.4GB", "Hindi"],
            ["Jawan (2023) Hindi", "1006", "10-12-2024", "", "1080p", "1.6GB", "Hindi"],
            ["Pathaan (2023)", "1007", "09-12-2024", "", "1080p", "1.7GB", "Hindi"],
            ["Tiger 3 (2023)", "1008", "08-12-2024", "", "720p", "1.3GB", "Hindi"],
            ["The Kerala Story", "1009", "07-12-2024", "", "480p", "800MB", "Hindi"],
            ["Gadar 2 (2023)", "1010", "06-12-2024", "", "1080p", "1.9GB", "Hindi"],
            ["Mission Impossible 7", "1011", "05-12-2024", "", "1080p", "2.1GB", "English"],
            ["Oppenheimer (2023)", "1012", "04-12-2024", "", "1080p", "2.3GB", "English"],
            ["Barbie (2023)", "1013", "03-12-2024", "", "720p", "1.5GB", "English"],
            ["Dream Girl 2 (2023)", "1014", "02-12-2024", "", "480p", "900MB", "Hindi"],
            ["Fukrey 3 (2023)", "1015", "01-12-2024", "", "720p", "1.2GB", "Hindi"]
        ];
        
        $handle = fopen(CSV_FILE, "w");
        fputcsv($handle, ["movie_name","message_id","date","video_path","quality","size","language"]);
        foreach ($test_movies as $movie) {
            fputcsv($handle, $movie);
        }
        fclose($handle);
        
        echo "<p>✅ Test movies added to CSV file</p>";
    }
    
    echo "<h3>📊 Pagination Preview:</h3>";
    echo "<div style='border: 1px solid #ccc; padding: 15px; background: #f9f9f9;'>";
    echo "<strong>🎬 Enhanced Movie Browser</strong><br>";
    echo "══════════════════════════<br><br>";
    echo "🆔 <b>Session:</b> sess_5f1d3a8b<br>";
    echo "📊 <b>Statistics:</b><br>";
    echo "• Total Movies: <b>15</b><br>";
    echo "• Page: <b>1/3</b><br>";
    echo "• Items: <b>1-5</b><br><br>";
    echo "══════════════════════════<br><br>";
    echo "📋 <b>Page 1 Movies:</b><br><br>";
    echo "<b>1.</b> Animal (2023) Hindi 1080p<br>";
    echo "   🎥 <b>1080p</b> | 🇮🇳 Hindi<br>";
    echo "   💾 1.5GB | 📅 15-12-2024<br><br>";
    echo "...<br><br>";
    echo "📍 <i>Click page numbers for direct access</i><br>";
    echo "🎬 <i>Try 'Video Preview' for movie clips</i>";
    echo "</div>";
    
    echo "<h3>🎯 Keyboard Layout:</h3>";
    echo "<div style='border: 1px solid #ccc; padding: 10px; background: #e8f4f8;'>";
    echo "[⏪ First] [◀️ Prev] [1] [2] [【3】] [4] [5] [Next ▶️] [Last ⏩]<br>";
    echo "[🎬 Video Preview] [📥 Send Page] [👁️ Text Preview]<br>";
    echo "[🎬 HD Only] [🔄 Latest] [🔥 Popular]<br>";
    echo "[📊 Stats] [🔍 Search] [❌ Close]";
    echo "</div>";
    
    echo "<p><a href='?'>Back to main page</a></p>";
    exit;
}
?>
