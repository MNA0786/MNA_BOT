<?php
// ==============================
// SECURITY HEADERS & BASIC SETUP
// ==============================

// Security headers PHP mein set karo - XSS aur security attacks se bachne ke liye
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ==============================
// DELAY TYPING FUNCTIONALITY - SLOW MODE ACTIVE
// ==============================

// Typing delay configuration
define('TYPING_DELAY_MS', 800);  // 0.8 seconds typing delay
define('MESSAGE_DELAY_MS', 1200); // 1.2 seconds between messages
define('EDIT_DELAY_MS', 500);    // 0.5 seconds for edits

function sendMessageWithDelay($chat_id, $text, $reply_markup = null, $parse_mode = null, $delay_ms = null) {
    // Message send karta hai with typing delay
    $delay_ms = $delay_ms ?? TYPING_DELAY_MS;
    
    // "typing..." action bhejo pehle
    $typing_data = [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ];
    apiRequest('sendChatAction', $typing_data);
    
    // Delay karo - Slow Mode Active
    usleep($delay_ms * 1000);
    
    // Phir actual message bhejo
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    
    $result = apiRequest('sendMessage', $data);
    bot_log("Message sent to $chat_id with $delay_ms ms delay");
    return json_decode($result, true);
}

function editMessageWithDelay($chat_id, $message_id, $new_text, $reply_markup = null, $delay_ms = null) {
    // Message edit karta hai with delay
    $delay_ms = $delay_ms ?? EDIT_DELAY_MS;
    
    // "typing..." action bhejo pehle
    $typing_data = [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ];
    apiRequest('sendChatAction', $typing_data);
    
    // Delay karo - Slow Mode Active
    usleep($delay_ms * 1000);
    
    // Phir actual message edit karo
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $new_text,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    
    apiRequest('editMessageText', $data);
    bot_log("Message edited in $chat_id with $delay_ms ms delay");
}

function sendTypingAction($chat_id, $duration_ms = 1000) {
    // Typing action bhejta hai for specific duration
    $typing_data = [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ];
    apiRequest('sendChatAction', $typing_data);
    usleep($duration_ms * 1000);
}

// ==============================
// DEPLOYMENT CONFIGURATION
// ==============================

// Render.com provides PORT environment variable
$port = getenv('PORT') ?: '80';

// Webhook URL automatically set karo
$webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// Security - All credentials environment variables se lo
if (!getenv('BOT_TOKEN')) {
    die("❌ BOT_TOKEN environment variable set nahi hai. Render.com dashboard mein set karo.");
}

// ==============================
// ENVIRONMENT VARIABLES CONFIGURATION
// ==============================
define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('CHANNEL_ID', getenv('CHANNEL_ID'));
define('BACKUP_CHANNEL_ID', getenv('BACKUP_CHANNEL_ID'));
define('BACKUP_CHANNEL_USERNAME', getenv('BACKUP_CHANNEL_USERNAME'));
define('ADMIN_ID', (int)getenv('ADMIN_ID'));
define('REQUEST_CHANNEL', getenv('REQUEST_CHANNEL'));
define('MAIN_CHANNEL', getenv('MAIN_CHANNEL'));
define('THEATER_CHANNEL', getenv('THEATER_CHANNEL'));
define('THEATER_CHANNEL_ID', getenv('THEATER_CHANNEL_ID'));

// Validate essential environment variables
if (!CHANNEL_ID || !THEATER_CHANNEL_ID) {
    die("❌ Essential channel IDs environment variables set nahi hain.");
}

// File paths
define('CSV_FILE', 'movies.csv');
define('USERS_FILE', 'users.json');
define('STATS_FILE', 'bot_stats.json');
define('REQUEST_FILE', 'movie_requests.json');
define('BACKUP_DIR', 'backups/');
define('LOG_FILE', 'bot_activity.log');

// Constants - Bot ke settings
define('CACHE_EXPIRY', 300);  // 5 minutes cache
define('ITEMS_PER_PAGE', 5);
define('MAX_SEARCH_RESULTS', 15);
define('DAILY_REQUEST_LIMIT', 5);
define('AUTO_BACKUP_HOUR', '03');

// ==============================
// ENHANCED PAGINATION CONSTANTS
// ==============================
define('MAX_PAGES_TO_SHOW', 7);
define('PAGINATION_CACHE_TIMEOUT', 60);
define('PREVIEW_ITEMS', 3);
define('BATCH_SIZE', 5);

// ==============================
// GLOBAL VARIABLES
// ==============================
$movie_messages = array();
$movie_cache = array();
$waiting_users = array();
$user_sessions = array();
$user_pagination_sessions = array();

// ==============================
// HELPER FUNCTION FOR DIRECT LINKS
// ==============================
function get_direct_channel_link($message_id, $channel_id = CHANNEL_ID) {
    $channel_id_clean = str_replace('-100', '', $channel_id);
    return "https://t.me/c/" . $channel_id_clean . "/" . $message_id;
}

function get_channel_username_link() {
    return "https://t.me/" . ltrim(MAIN_CHANNEL, '@');
}

// ==============================
// FILE INITIALIZATION FUNCTION
// ==============================
function initialize_files() {
    $files = [
        CSV_FILE => "movie_name,message_id,channel_id (FORMAT LOCKED)\n",
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
// CACHING SYSTEM
// ==============================
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

// ==============================
// CSV MANAGEMENT FUNCTIONS - UPDATED FORMAT
// ==============================
function load_and_clean_csv($filename = CSV_FILE) {
    global $movie_messages;
    
    if (!file_exists($filename)) {
        file_put_contents($filename, "movie_name,message_id,channel_id (FORMAT LOCKED)\n");
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
                $channel_id = isset($row[2]) ? trim($row[2]) : '';
                
                // Auto-detect quality from movie name
                $quality = 'Unknown';
                $movie_name_lower = strtolower($movie_name);
                
                if (strpos($movie_name_lower, '1080') !== false || 
                    strpos($movie_name_lower, 'full hd') !== false || 
                    strpos($movie_name_lower, 'fhd') !== false) {
                    $quality = '1080p';
                } elseif (strpos($movie_name_lower, '720') !== false || 
                         strpos($movie_name_lower, 'hd') !== false) {
                    $quality = '720p';
                } elseif (strpos($movie_name_lower, '480') !== false || 
                         strpos($movie_name_lower, 'sd') !== false) {
                    $quality = '480p';
                } elseif (strpos($movie_name_lower, 'theater') !== false || 
                         strpos($movie_name_lower, 'theatre') !== false ||
                         strpos($movie_name_lower, 'print') !== false ||
                         strpos($movie_name_lower, 'hdcam') !== false ||
                         strpos($movie_name_lower, 'camrip') !== false) {
                    $quality = 'theater';
                }
                
                // Auto-detect language
                $language = 'Hindi';
                if (strpos($movie_name_lower, 'english') !== false) {
                    $language = 'English';
                }
                
                // Determine channel type from channel_id
                $channel_type = 'main';
                $channel_username = MAIN_CHANNEL;
                
                if ($channel_id == THEATER_CHANNEL_ID) {
                    $channel_type = 'theater';
                    $channel_username = THEATER_CHANNEL;
                } elseif ($channel_id == CHANNEL_ID) {
                    $channel_type = 'main';
                    $channel_username = MAIN_CHANNEL;
                }
                
                // Movie entry create karo
                $entry = [
                    'movie_name' => $movie_name,
                    'message_id_raw' => $message_id_raw,
                    'channel_id' => $channel_id,
                    'date' => date('d-m-Y'),
                    'video_path' => '',
                    'quality' => $quality,
                    'size' => 'Unknown',
                    'language' => $language,
                    'channel_type' => $channel_type,
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

    // Statistics update karo
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats['total_movies'] = count($data);
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));

    // CSV clean karo aur rewrite karo (updated format)
    $handle = fopen($filename, "w");
    fputcsv($handle, array('movie_name','message_id','channel_id (FORMAT LOCKED)'));
    foreach ($data as $row) {
        fputcsv($handle, [$row['movie_name'], $row['message_id_raw'], $row['channel_id']]);
    }
    fclose($handle);

    bot_log("CSV cleaned and reloaded - " . count($data) . " entries");
    return $data;
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
    bot_log("Message sent to $chat_id: " . substr($text, 0, 50) . "...");
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

// ==============================
// MOVIE DELIVERY SYSTEM - UPDATED FOR CHANNEL_ID
// ==============================
function deliver_item_to_chat($chat_id, $item, $source_type = null) {
    // Determine source channel DIRECTLY from item's channel_id
    $source_channel = $item['channel_id'] ?? CHANNEL_ID;
    
    // Send typing action
    sendTypingAction($chat_id, 800);
    
    // Agar valid message ID hai toh COPY KARO
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        sendTypingAction($chat_id, 500);
        $result = json_decode(copyMessage($chat_id, $source_channel, $item['message_id']), true);
        
        if ($result && $result['ok']) {
            update_stats('total_downloads', 1);
            bot_log("Movie COPIED from channel $source_channel: {$item['movie_name']} to $chat_id");
            return true;
        } else {
            $fallback_result = json_decode(forwardMessage($chat_id, $source_channel, $item['message_id']), true);
            
            if ($fallback_result && $fallback_result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("Movie FORWARDED from channel $source_channel: {$item['movie_name']} to $chat_id");
                return true;
            }
        }
    }
    
    // Agar raw message ID hai
    if (!empty($item['message_id_raw'])) {
        $message_id_clean = preg_replace('/[^0-9]/', '', $item['message_id_raw']);
        if (is_numeric($message_id_clean) && $message_id_clean > 0) {
            sendTypingAction($chat_id, 500);
            $result = json_decode(copyMessage($chat_id, $source_channel, $message_id_clean), true);
            
            if ($result && $result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("Movie COPIED (raw ID) from channel $source_channel: {$item['movie_name']} to $chat_id");
                return true;
            } else {
                $fallback_result = json_decode(forwardMessage($chat_id, $source_channel, $message_id_clean), true);
                
                if ($fallback_result && $fallback_result['ok']) {
                    update_stats('total_downloads', 1);
                    bot_log("Movie FORWARDED (raw ID) from channel $source_channel: {$item['movie_name']} to $chat_id");
                    return true;
                }
            }
        }
    }

    // Agar koi bhi method kaam na kare toh text info bhejo
    $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n";
    $text .= "📊 Quality: " . htmlspecialchars($item['quality'] ?? 'Unknown') . "\n";
    $text .= "💾 Size: " . htmlspecialchars($item['size'] ?? 'Unknown') . "\n";
    $text .= "🗣️ Language: " . htmlspecialchars($item['language'] ?? 'Hindi') . "\n";
    $text .= "🎭 Channel: " . ($item['channel_type'] ?? 'main') . "\n";
    $text .= "📅 Date: " . htmlspecialchars($item['date'] ?? 'N/A') . "\n";
    $text .= "📎 Reference: " . htmlspecialchars($item['message_id_raw'] ?? 'N/A') . "\n\n";
    
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        $channel_id_clean = str_replace('-100', '', $source_channel);
        $text .= "🔗 Direct Link: https://t.me/c/" . $channel_id_clean . "/{$item['message_id']}\n\n";
    }
    
    $text .= "⚠️ Join channel to access content: " . ($item['channel_username'] ?? MAIN_CHANNEL);
    
    sendMessageWithDelay($chat_id, $text, null, 'HTML', 800);
    update_stats('total_downloads', 1);
    return false;
}

// ==============================
// STATISTICS SYSTEM
// ==============================
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

// ==============================
// USER MANAGEMENT
// ==============================
function update_user_data($user_id, $user_info = []) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id] = [
            'first_name' => $user_info['first_name'] ?? '',
            'last_name' => $user_info['last_name'] ?? '',
            'username' => $user_info['username'] ?? '',
            'joined' => date('Y-m-d H:i:s'),
            'last_active' => date('Y-m-d H:i:s'),
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
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (isset($users_data['users'][$user_id])) {
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
    
    foreach ($movie_messages as $movie => $entries) {
        $score = 0;
        
        foreach ($entries as $entry) {
            if ($is_theater_search && isset($entry['channel_type']) && $entry['channel_type'] == 'theater') {
                $score += 20;
            } elseif (!$is_theater_search && isset($entry['channel_type']) && $entry['channel_type'] == 'main') {
                $score += 10;
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
            $results[$movie] = [
                'score' => $score,
                'count' => count($entries),
                'latest_entry' => end($entries),
                'qualities' => array_unique(array_column($entries, 'quality')),
                'has_theater' => in_array('theater', array_column($entries, 'channel_type')),
                'has_main' => in_array('main', array_column($entries, 'channel_type'))
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
    
    sendMessageWithDelay($chat_id, $responses[$language][$message_type], null, null, 500);
}

function advanced_search($chat_id, $query, $user_id = null) {
    global $movie_messages, $waiting_users;
    $q = strtolower(trim($query));
    
    if (strlen($q) < 2) {
        sendMessageWithDelay($chat_id, "❌ Please enter at least 2 characters for search", null, null, 500);
        return;
    }
    
    $invalid_keywords = [
        'vlc', 'audio', 'track', 'change', 'open', 'kar', 'me', 'hai',
        'how', 'what', 'problem', 'issue', 'help', 'solution', 'fix',
        'error', 'not working', 'download', 'play', 'video', 'sound'
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
        $help_msg .= "🔍 Examples:\n";
        $help_msg .= "• kgf\n• pushpa\n• avengers\n• hindi movie\n• spider-man\n\n";
        $help_msg .= "❌ Technical queries like 'vlc', 'audio track', etc. are not movie names.\n\n";
        $help_msg .= "📢 Join: " . MAIN_CHANNEL . "\n";
        $help_msg .= "💬 Help: " . REQUEST_CHANNEL;
        sendMessageWithDelay($chat_id, $help_msg, null, 'HTML', 800);
        return;
    }
    
    $movie_pattern = '/^[a-zA-Z0-9\s\-\.\,\&\+\(\)\:\'\"]+$/';
    if (!preg_match($movie_pattern, $query)) {
        sendMessageWithDelay($chat_id, "❌ Invalid movie name format. Only letters, numbers, and basic punctuation allowed.", null, null, 500);
        return;
    }
    
    // Show typing action before searching
    sendTypingAction($chat_id, 800);
    
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
            $msg .= "$i. $movie ($channel_info" . $data['count'] . " versions, $quality_info)\n";
            $i++;
            if ($i > 10) break;
        }
        
        sendMessageWithDelay($chat_id, $msg, null, null, 600);
        
        $keyboard = ['inline_keyboard' => []];
        $top_movies = array_slice(array_keys($found), 0, 5);
        
        foreach ($top_movies as $movie) {
            $movie_data = $found[$movie];
            $channel_icon = $movie_data['has_theater'] ? '🎭 ' : '🍿 ';
            $keyboard['inline_keyboard'][] = [[ 
                'text' => $channel_icon . ucwords($movie), 
                'callback_data' => $movie 
            ]];
        }
        
        $keyboard['inline_keyboard'][] = [[
            'text' => "📝 Request Different Movie", 
            'callback_data' => 'request_movie'
        ]];
        
        sendMessageWithDelay($chat_id, "🚀 Top matches (click for info):", $keyboard, null, 500);
        
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
        
        sendMessageWithDelay($chat_id, "💡 Click below to automatically request this movie:", $request_keyboard, null, 500);
        
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
    
    // Admin ko notify karo with delay
    sendTypingAction(ADMIN_ID, 500);
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
    
    $kb['inline_keyboard'][] = $action_row;
    
    if (empty($filters)) {
        $filter_row = [];
        $filter_row[] = ['text' => '🎬 HD Only', 'callback_data' => 'flt_hd_' . $session_id];
        $filter_row[] = ['text' => '🎭 Theater Only', 'callback_data' => 'flt_theater_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
    } else {
        $filter_row = [];
        $filter_row[] = ['text' => '🧹 Clear Filter', 'callback_data' => 'flt_clr_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
    }
    
    $ctrl_row = [];
    $ctrl_row[] = ['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''];
    $ctrl_row[] = ['text' => '❌ Close', 'callback_data' => 'close_' . $session_id];
    
    $kb['inline_keyboard'][] = $ctrl_row;
    
    return $kb;
}

function totalupload_controller($chat_id, $page = 1, $filters = [], $session_id = null) {
    $all = get_all_movies_list();
    if (empty($all)) {
        sendMessageWithDelay($chat_id, "📭 Koi movies nahi mili! Pehle kuch movies add karo.", null, null, 500);
        return;
    }
    
    if (!$session_id) {
        $session_id = uniqid('sess_', true);
    }
    
    $pg = paginate_movies($all, (int)$page, $filters);
    
    // Send preview for first page
    if ($page == 1 && PREVIEW_ITEMS > 0 && count($pg['slice']) > 0) {
        sendTypingAction($chat_id, 600);
        $preview_msg = "👁️ <b>Quick Preview (First " . PREVIEW_ITEMS . "):</b>\n\n";
        $preview_count = min(PREVIEW_ITEMS, count($pg['slice']));
        
        for ($i = 0; $i < $preview_count; $i++) {
            $movie = $pg['slice'][$i];
            $channel_icon = (isset($movie['channel_type']) && $movie['channel_type'] == 'theater') ? '🎭' : '🍿';
            $preview_msg .= ($i + 1) . ". $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
            $preview_msg .= "   ⭐ " . ($movie['quality'] ?? 'Unknown') . " | ";
            $preview_msg .= "🗣️ " . ($movie['language'] ?? 'Hindi') . "\n\n";
        }
        
        sendMessageWithDelay($chat_id, $preview_msg, null, 'HTML', 700);
    }
    
    sendTypingAction($chat_id, 800);
    
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
        $channel_icon = (isset($movie['channel_type']) && $movie['channel_type'] == 'theater') ? '🎭' : '🍿';
        
        $title .= "<b>{$i}.</b> $channel_icon {$movie_name}\n";
        $title .= "   🏷️ {$quality} | 🗣️ {$language}\n";
        $title .= "   💾 {$size} | 📅 {$date}\n\n";
        $i++;
    }
    
    $title .= "📍 <i>Use number buttons for direct page access</i>\n";
    $title .= "🔧 <i>Apply filters using buttons below</i>";
    
    $kb = build_totalupload_keyboard($pg['page'], $pg['total_pages'], $session_id, $filters);
    
    delete_pagination_message($chat_id, $session_id);
    
    $result = sendMessageWithDelay($chat_id, $title, $kb, 'HTML', 600);
    save_pagination_message($chat_id, $session_id, $result['result']['message_id']);
    
    bot_log("Enhanced pagination - Chat: $chat_id, Page: $page, Session: " . substr($session_id, 0, 8));
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
    
    sendTypingAction($chat_id, 800);
    $progress_msg = sendMessage($chat_id, "📦 <b>Batch Info Started</b>\n\nPage: {$page_num}\nTotal: {$total} movies\n\n⏳ Initializing...");
    $progress_id = $progress_msg['result']['message_id'];
    
    $success = 0;
    $failed = 0;
    
    for ($i = 0; $i < $total; $i++) {
        $movie = $movies[$i];
        
        if ($i % 2 == 0) {
            $progress = round(($i / $total) * 100);
            editMessageWithDelay($chat_id, $progress_id, 
                "📦 <b>Sending Page {$page_num} Info</b>\n\n" .
                "Progress: {$progress}%\n" .
                "Processed: {$i}/{$total}\n" .
                "✅ Success: {$success}\n" .
                "❌ Failed: {$failed}\n\n" .
                "⏳ Please wait...", null, 400
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
    
    editMessageWithDelay($chat_id, $progress_id,
        "✅ <b>Batch Info Complete</b>\n\n" .
        "📄 Page: {$page_num}\n" .
        "🎬 Total: {$total} movies\n" .
        "✅ Successfully sent: {$success}\n" .
        "❌ Failed: {$failed}\n\n" .
        "📊 Success rate: " . round(($success / $total) * 100, 2) . "%\n" .
        "⏱️ Time: " . date('H:i:s') . "\n\n" .
        "🔗 Join channel to download: " . MAIN_CHANNEL,
        null, 500
    );
}

// ==============================
// GET ALL MOVIES LIST FUNCTION
// ==============================
function get_all_movies_list() {
    return get_cached_movies();
}

// ==============================
// MOVIE APPEND FUNCTION - UPDATED FOR NEW FORMAT
// ==============================
function append_movie($movie_name, $message_id_raw, $channel_id, $date = null, $video_path = '', $quality = 'Unknown', $size = 'Unknown', $language = 'Hindi') {
    global $movie_messages, $movie_cache, $waiting_users;
    
    if (empty(trim($movie_name))) return;
    
    if ($date === null) $date = date('d-m-Y');
    
    // Updated CSV format with channel_id
    $entry = [$movie_name, $message_id_raw, $channel_id];
    
    $handle = fopen(CSV_FILE, "a");
    fputcsv($handle, $entry);
    fclose($handle);

    $movie = strtolower(trim($movie_name));
    
    // Determine channel type from channel_id
    $channel_type = 'main';
    $channel_username = MAIN_CHANNEL;
    
    if ($channel_id == THEATER_CHANNEL_ID) {
        $channel_type = 'theater';
        $channel_username = THEATER_CHANNEL;
    } elseif ($channel_id == CHANNEL_ID) {
        $channel_type = 'main';
        $channel_username = MAIN_CHANNEL;
    }
    
    $item = [
        'movie_name' => $movie_name,
        'message_id_raw' => $message_id_raw,
        'channel_id' => $channel_id,
        'date' => $date,
        'video_path' => $video_path,
        'quality' => $quality,
        'size' => $size,
        'language' => $language,
        'channel_type' => $channel_type,
        'channel_username' => $channel_username,
        'message_id' => is_numeric($message_id_raw) ? intval($message_id_raw) : null,
        'source_channel' => $channel_id
    ];
    
    if (!isset($movie_messages[$movie])) $movie_messages[$movie] = [];
    $movie_messages[$movie][] = $item;
    $movie_cache = [];

    update_stats('total_movies', 1);
    bot_log("Movie appended: $movie_name with ID $message_id_raw to channel $channel_id");
}

// ==============================
// BACKUP SYSTEM - COMPLETE IMPLEMENTATION
// ==============================
function auto_backup() {
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
    try {
        sendTypingAction(BACKUP_CHANNEL_ID, 800);
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
        
        $message_result = sendMessageWithDelay(BACKUP_CHANNEL_ID, $summary_message, $keyboard, 'HTML', 600);
        
        if (!$message_result || !isset($message_result['ok']) || !$message_result['ok']) {
            bot_log("Failed to send backup summary to channel", 'ERROR');
            return false;
        }
        
        $critical_files = [
            CSV_FILE => "🎬 Movies Database",
            USERS_FILE => "👥 Users Data", 
            STATS_FILE => "📊 Bot Statistics",
            REQUEST_FILE => "📝 Movie Requests"
        ];
        
        foreach ($critical_files as $file => $description) {
            if (file_exists($file)) {
                upload_file_to_channel($file, $backup_dir, $description);
                sleep(2);
            }
        }
        
        $zip_success = create_and_upload_zip($backup_dir);
        
        $completion_message = "✅ <b>Backup Process Completed</b>\n\n";
        $completion_message .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $completion_message .= "💾 All files backed up successfully\n";
        $completion_message .= "📦 Zip archive created\n";
        $completion_message .= "📡 Uploaded to: " . BACKUP_CHANNEL_USERNAME . "\n\n";
        $completion_message .= "🛡️ <i>Your data is now securely backed up!</i>";
        
        sendMessageWithDelay(BACKUP_CHANNEL_ID, $completion_message, null, 'HTML', 500);
        
        return true;
        
    } catch (Exception $e) {
        bot_log("Channel backup failed: " . $e->getMessage(), 'ERROR');
        
        $error_message = "❌ <b>Backup Process Failed</b>\n\n";
        $error_message .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $error_message .= "🚨 Error: " . $e->getMessage() . "\n\n";
        $error_message .= "⚠️ Please check server logs immediately!";
        
        sendMessageWithDelay(BACKUP_CHANNEL_ID, $error_message, null, 'HTML', 500);
        
        return false;
    }
}

function upload_file_to_channel($file_path, $backup_dir, $description = "") {
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
    
    if ($file_size > 45 * 1024 * 1024) {
        bot_log("File too large for Telegram: $file_name ($file_size_mb MB)", 'WARNING');
        return false;
    }
    
    $post_fields = [
        'chat_id' => BACKUP_CHANNEL_ID,
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
    } else {
        bot_log("Failed to upload to channel: $file_name", 'ERROR');
    }
    
    return $success;
}

function create_and_upload_zip($backup_dir) {
    $zip_file = $backup_dir . '/complete_backup.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
        bot_log("Cannot open zip file: $zip_file", 'ERROR');
        return false;
    }
    
    $files = glob($backup_dir . '/*.bak');
    foreach ($files as $file) {
        $zip->addFile($file, basename($file));
    }
    
    if (file_exists($backup_dir . '/backup_summary.txt')) {
        $zip->addFile($backup_dir . '/backup_summary.txt', 'backup_summary.txt');
    }
    
    $zip->close();
    
    $zip_size = filesize($zip_file);
    $zip_size_mb = round($zip_size / (1024 * 1024), 2);
    
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
        'chat_id' => BACKUP_CHANNEL_ID,
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
    
    @unlink($zip_file);
    
    $success = ($http_code == 200);
    
    if ($success) {
        bot_log("Zip backup uploaded to channel successfully");
        
        $zip_confirmation = "✅ <b>Zip Archive Uploaded</b>\n\n";
        $zip_confirmation .= "📦 File: Complete Backup Archive\n";
        $zip_confirmation .= "💾 Size: " . $zip_size_mb . " MB\n";
        $zip_confirmation .= "✅ Status: Successfully uploaded\n";
        $zip_confirmation .= "📡 Channel: " . BACKUP_CHANNEL_USERNAME . "\n\n";
        $zip_confirmation .= "🛡️ <i>All data securely backed up!</i>";
        
        sendMessageWithDelay(BACKUP_CHANNEL_ID, $zip_confirmation, $keyboard, 'HTML', 500);
    } else {
        bot_log("Failed to upload zip backup to channel", 'WARNING');
    }
    
    return $success;
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

function send_backup_report($success, $summary) {
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
    
    sendMessageWithDelay(ADMIN_ID, $report_message, $keyboard, 'HTML', 600);
}

// ==============================
// MANUAL BACKUP COMMANDS
// ==============================
function manual_backup($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessageWithDelay($chat_id, "❌ Access denied. Admin only command.", null, null, 500);
        return;
    }
    
    $progress_msg = sendMessage($chat_id, "🔄 Starting manual backup...");
    
    try {
        $success = auto_backup();
        
        if ($success) {
            editMessageWithDelay($chat_id, $progress_msg['result']['message_id'], 
                "✅ Manual backup completed successfully!\n\n📊 Backup has been saved locally and uploaded to backup channel.",
                null, 500
            );
        } else {
            editMessageWithDelay($chat_id, $progress_msg['result']['message_id'], 
                "⚠️ Backup completed with some warnings.\n\nSome files may not have been backed up properly. Check logs for details.",
                null, 500
            );
        }
        
    } catch (Exception $e) {
        editMessageWithDelay($chat_id, $progress_msg['result']['message_id'], 
            "❌ Backup failed!\n\nError: " . $e->getMessage(),
            null, 500
        );
        bot_log("Manual backup failed: " . $e->getMessage(), 'ERROR');
    }
}

function backup_status($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessageWithDelay($chat_id, "❌ Access denied. Admin only command.", null, null, 500);
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
    $status_message .= "• Channel ID: " . BACKUP_CHANNEL_ID . "\n\n";
    
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
    
    sendMessageWithDelay($chat_id, $status_message, $keyboard, 'HTML', 600);
}

// ==============================
// CHANNEL MANAGEMENT FUNCTIONS
// ==============================
function show_channel_info($chat_id) {
    $message = "📢 <b>Join Our Channels</b>\n\n";
    
    $message .= "🍿 <b>Main Channel:</b> " . MAIN_CHANNEL . "\n";
    $message .= "• Latest movie updates\n";
    $message .= "• Daily new additions\n\n";
    
    $message .= "📥 <b>Requests Channel:</b> " . REQUEST_CHANNEL . "\n";
    $message .= "• Movie requests\n";
    $message .= "• Support & help\n\n";
    
    $message .= "🎭 <b>Theater Prints:</b> " . THEATER_CHANNEL . "\n";
    $message .= "• Theater quality prints\n";
    $message .= "• HD screen recordings\n\n";
    
    $message .= "🔔 <b>Don't forget to join all channels!</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🍿 ' . MAIN_CHANNEL, 'url' => 'https://t.me/EntertainmentTadka786'],
                ['text' => '📥 ' . REQUEST_CHANNEL, 'url' => 'https://t.me/EntertainmentTadka7860']
            ],
            [
                ['text' => '🎭 ' . THEATER_CHANNEL, 'url' => 'https://t.me/threater_print_movies']
            ]
        ]
    ];
    
    sendMessageWithDelay($chat_id, $message, $keyboard, 'HTML', 600);
}

// ==============================
// BROWSE COMMANDS
// ==============================
function show_latest_movies($chat_id, $limit = 10) {
    sendTypingAction($chat_id, 800);
    
    $all_movies = get_all_movies_list();
    $latest_movies = array_slice($all_movies, -$limit);
    $latest_movies = array_reverse($latest_movies);
    
    if (empty($latest_movies)) {
        sendMessageWithDelay($chat_id, "📭 Koi movies nahi mili!", null, null, 500);
        return;
    }
    
    $message = "🎬 <b>Latest $limit Movies</b>\n\n";
    $i = 1;
    
    foreach ($latest_movies as $movie) {
        $channel_icon = (isset($movie['channel_type']) && $movie['channel_type'] == 'theater') ? '🎭' : '🍿';
        $message .= "$i. $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
        $message .= "   📊 " . ($movie['quality'] ?? 'Unknown') . " | 🗣️ " . ($movie['language'] ?? 'Hindi') . "\n";
        $message .= "   📅 " . ($movie['date'] ?? 'N/A') . "\n\n";
        $i++;
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Get All Latest Info', 'callback_data' => 'download_latest'],
                ['text' => '📊 Browse All', 'callback_data' => 'browse_all']
            ]
        ]
    ];
    
    sendMessageWithDelay($chat_id, $message, $keyboard, 'HTML', 700);
}

function show_trending_movies($chat_id) {
    sendTypingAction($chat_id, 800);
    
    $all_movies = get_all_movies_list();
    $trending_movies = array_slice($all_movies, -15);
    
    if (empty($trending_movies)) {
        sendMessageWithDelay($chat_id, "📭 Koi trending movies nahi mili!", null, null, 500);
        return;
    }
    
    $message = "🔥 <b>Trending Movies</b>\n\n";
    $i = 1;
    
    foreach (array_slice($trending_movies, 0, 10) as $movie) {
        $channel_icon = (isset($movie['channel_type']) && $movie['channel_type'] == 'theater') ? '🎭' : '🍿';
        $message .= "$i. $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
        $message .= "   ⭐ " . ($movie['quality'] ?? 'HD') . " | 🗣️ " . ($movie['language'] ?? 'Hindi') . "\n\n";
        $i++;
    }
    
    $message .= "💡 <i>Based on recent popularity and downloads</i>";
    
    sendMessageWithDelay($chat_id, $message, null, 'HTML', 700);
}

// ==============================
// REQUEST MANAGEMENT
// ==============================
function show_user_requests($chat_id, $user_id) {
    sendTypingAction($chat_id, 600);
    
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $user_requests = [];
    
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id) {
            $user_requests[] = $request;
        }
    }
    
    if (empty($user_requests)) {
        sendMessageWithDelay($chat_id, "📭 Aapne abhi tak koi movie request nahi ki hai!", null, null, 500);
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
    
    sendMessageWithDelay($chat_id, $message, null, 'HTML', 600);
}

// ==============================
// ADMIN COMMANDS
// ==============================
function admin_stats($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessageWithDelay($chat_id, "❌ Access denied. Admin only command.", null, null, 500);
        return;
    }
    
    sendTypingAction($chat_id, 800);
    
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $total_users = count($users_data['users'] ?? []);
    
    $msg = "📊 <b>Bot Statistics</b>\n\n";
    $msg .= "🎬 Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $msg .= "👥 Total Users: " . $total_users . "\n";
    $msg .= "🔍 Total Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $msg .= "✅ Successful Searches: " . ($stats['successful_searches'] ?? 0) . "\n";
    $msg .= "❌ Failed Searches: " . ($stats['failed_searches'] ?? 0) . "\n";
    $msg .= "📥 Total Downloads: " . ($stats['total_downloads'] ?? 0) . "\n";
    $msg .= "🕒 Last Updated: " . ($stats['last_updated'] ?? 'N/A') . "\n\n";
    
    $today = date('Y-m-d');
    if (isset($stats['daily_activity'][$today])) {
        $today_stats = $stats['daily_activity'][$today];
        $msg .= "📈 <b>Today's Activity:</b>\n";
        $msg .= "• Searches: " . ($today_stats['searches'] ?? 0) . "\n";
        $msg .= "• Downloads: " . ($today_stats['downloads'] ?? 0) . "\n";
    }
    
    sendMessageWithDelay($chat_id, $msg, null, 'HTML', 600);
    bot_log("Admin stats viewed by $chat_id");
}

// ==============================
// UTILITY FUNCTIONS
// ==============================
function check_date($chat_id) {
    sendTypingAction($chat_id, 600);
    
    if (!file_exists(CSV_FILE)) {
        sendMessageWithDelay($chat_id, "⚠️ Abhi tak koi data save nahi hua.", null, null, 500);
        return;
    }
    
    $date_counts = [];
    $h = fopen(CSV_FILE, 'r');
    
    if ($h !== FALSE) {
        fgetcsv($h);
        while (($r = fgetcsv($h)) !== FALSE) {
            if (count($r) >= 3) {
                $d = $r[2];
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
    
    sendMessageWithDelay($chat_id, $msg, null, 'HTML', 600);
}

function show_bot_info($chat_id) {
    sendTypingAction($chat_id, 800);
    
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $message = "🤖 <b>Entertainment Tadka Bot</b>\n\n";
    $message .= "📱 <b>Version:</b> 2.0.0\n";
    $message .= "🆙 <b>Last Updated:</b> " . date('Y-m-d') . "\n";
    $message .= "👨‍💻 <b>Developer:</b> @EntertainmentTadka0786\n\n";
    
    $message .= "📊 <b>Bot Statistics:</b>\n";
    $message .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $message .= "• 👥 Users: " . count($users_data['users'] ?? []) . "\n";
    $message .= "• 🔍 Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $message .= "• 📥 Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
    
    $message .= "🎯 <b>Features:</b>\n";
    $message .= "• Smart movie search\n";
    $message .= "• Multi-language support\n";
    $message .= "• Quality filtering\n";
    $message .= "• Movie requests\n";
    $message .= "• Enhanced pagination\n\n";
    
    $message .= "📢 <b>Channels:</b>\n";
    $message .= "• Main: " . MAIN_CHANNEL . "\n";
    $message .= "• Support: " . REQUEST_CHANNEL . "\n";
    $message .= "• Theater: " . THEATER_CHANNEL;
    
    sendMessageWithDelay($chat_id, $message, null, 'HTML', 700);
}

// ==============================
// COMPLETE COMMAND HANDLER
// ==============================
function handle_command($chat_id, $user_id, $command, $params = []) {
    switch ($command) {
        case '/start':
            sendTypingAction($chat_id, 800);
            
            $welcome = "🎬 Welcome to Entertainment Tadka!\n\n";
            
            $welcome .= "📢 <b>How to use this bot:</b>\n";
            $welcome .= "• Simply type any movie name\n";
            $welcome .= "• Use English or Hindi\n";
            $welcome .= "• Add 'theater' for theater prints\n";
            $welcome .= "• Partial names also work\n\n";
            
            $welcome .= "🔍 <b>Examples:</b>\n";
            $welcome .= "• Animal\n";
            $welcome .= "• KGF 2\n";
            $welcome .= "• Avengers Endgame\n";
            $welcome .= "• hindi movie\n";
            $welcome .= "• kgf theater print\n\n";
            
            $welcome .= "📢 <b>Join Our Channels:</b>\n";
            $welcome .= "🍿 Main: @EntertainmentTadka786\n";
            $welcome .= "📥 Requests: @EntertainmentTadka7860\n";
            $welcome .= "🎭 Theater Prints: @threater_print_movies\n\n";
            
            $welcome .= "💬 <b>Need help?</b> Use /help for all commands";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => ''],
                        ['text' => '🍿 Main Channel', 'url' => 'https://t.me/EntertainmentTadka786']
                    ],
                    [
                        ['text' => '📥 Requests', 'url' => 'https://t.me/EntertainmentTadka7860'],
                        ['text' => '🎭 Theater Prints', 'url' => 'https://t.me/threater_print_movies']
                    ],
                    [
                        ['text' => '❓ Help', 'callback_data' => 'help_command']
                    ]
                ]
            ];
            
            sendMessageWithDelay($chat_id, $welcome, $keyboard, 'HTML', 600);
            update_user_activity($user_id, 'daily_login');
            break;

        case '/help':
        case '/commands':
            sendTypingAction($chat_id, 800);
            
            $help = "🤖 <b>Entertainment Tadka Bot - Complete Guide</b>\n\n";
            
            $help .= "📢 <b>Our Channels:</b>\n";
            $help .= "🍿 Main: " . MAIN_CHANNEL . " - Latest movies\n";
            $help .= "📥 Requests: " . REQUEST_CHANNEL . " - Support & requests\n";
            $help .= "🎭 Theater: " . THEATER_CHANNEL . " - HD prints\n\n";
            
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
            
            $help .= "🔗 <b>Channel Commands:</b>\n";
            $help .= "• <code>/channel</code> - All channels\n";
            $help .= "• <code>/checkdate</code> - Upload statistics\n\n";
            
            $help .= "💡 <b>Pro Tips:</b>\n";
            $help .= "• Use partial names (e.g., 'aveng')\n";
            $help .= "• Add 'theater' for theater prints\n";
            $help .= "• Join all channels for updates\n";
            $help .= "• Request movies you can't find\n";
            $help .= "• Check spelling before reporting";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🍿 ' . MAIN_CHANNEL, 'url' => 'https://t.me/EntertainmentTadka786'],
                        ['text' => '📥 ' . REQUEST_CHANNEL, 'url' => 'https://t.me/EntertainmentTadka7860']
                    ],
                    [
                        ['text' => '🎭 ' . THEATER_CHANNEL, 'url' => 'https://t.me/threater_print_movies']
                    ],
                    [
                        ['text' => '🎬 Search Movies', 'switch_inline_query_current_chat' => '']
                    ]
                ]
            ];
            
            sendMessageWithDelay($chat_id, $help, $keyboard, 'HTML', 700);
            break;

        case '/search':
        case '/s':
        case '/find':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessageWithDelay($chat_id, "❌ Usage: <code>/search movie_name</code>\nExample: <code>/search kgf 2</code>", null, 'HTML', 500);
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

        case '/latest':
        case '/recent':
        case '/new':
            show_latest_movies($chat_id, isset($params[0]) ? intval($params[0]) : 10);
            break;

        case '/trending':
        case '/popular':
            show_trending_movies($chat_id);
            break;

        case '/theater':
        case '/theatermovies':
        case '/theateronly':
            sendTypingAction($chat_id, 600);
            $message = "🎭 <b>Theater Prints Search</b>\n\n";
            $message .= "Type any movie name followed by 'theater' to search for theater prints!\n\n";
            $message .= "🔍 <b>Examples:</b>\n";
            $message .= "• <code>kgf 2 theater</code>\n";
            $message .= "• <code>avengers endgame print</code>\n";
            $message .= "• <code>hindi movie theater</code>\n\n";
            $message .= "📢 Join: " . THEATER_CHANNEL;
            sendMessageWithDelay($chat_id, $message, null, 'HTML', 600);
            break;

        case '/request':
        case '/req':
        case '/requestmovie':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessageWithDelay($chat_id, "❌ Usage: <code>/request movie_name</code>\nExample: <code>/request Animal Park</code>", null, 'HTML', 500);
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

        case '/channel':
        case '/channels':
        case '/join':
            show_channel_info($chat_id);
            break;

        case '/checkdate':
        case '/datestats':
        case '/uploadstats':
            check_date($chat_id);
            break;

        case '/stats':
        case '/statistics':
        case '/botstats':
            if ($user_id == ADMIN_ID) {
                admin_stats($chat_id);
            } else {
                sendMessageWithDelay($chat_id, "❌ Access denied. Admin only command.", null, null, 500);
            }
            break;

        case '/backup':
            if ($user_id == ADMIN_ID) {
                manual_backup($chat_id);
            } else {
                sendMessageWithDelay($chat_id, "❌ Access denied. Admin only command.", null, null, 500);
            }
            break;

        case '/backupstatus':
        case '/backupinfo':
            if ($user_id == ADMIN_ID) {
                backup_status($chat_id);
            } else {
                sendMessageWithDelay($chat_id, "❌ Access denied. Admin only command.", null, null, 500);
            }
            break;

        case '/info':
        case '/about':
        case '/botinfo':
            show_bot_info($chat_id);
            break;

        case '/ping':
        case '/status':
            sendMessageWithDelay($chat_id, "🏓 <b>Bot Status:</b> ✅ Online\n⏰ <b>Server Time:</b> " . date('Y-m-d H:i:s'), null, 'HTML', 500);
            break;

        default:
            sendMessageWithDelay($chat_id, "❌ Unknown command. Use <code>/help</code> to see all available commands.", null, 'HTML', 500);
    }
}

// ==============================
// MAIN UPDATE PROCESSING
// ==============================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    get_cached_movies();

    // Channel post handling - UPDATED FOR CHANNEL_ID
    if (isset($update['channel_post'])) {
        $message = $update['channel_post'];
        $message_id = $message['message_id'];
        $chat_id = $message['chat']['id'];  // This is the channel_id

        $channel_type = 'main';
        $source_channel = $chat_id;  // Use actual channel ID

        // Determine if it's theater or main channel
        if ($chat_id == THEATER_CHANNEL_ID) {
            $channel_type = 'theater';
        } elseif ($chat_id == CHANNEL_ID) {
            $channel_type = 'main';
        } else {
            // Not our channel, ignore
            exit;
        }

        $text = '';
        $quality = 'Unknown';
        $size = 'Unknown';
        $language = 'Hindi';

        if (isset($message['caption'])) {
            $text = $message['caption'];
            if (stripos($text, '1080') !== false) $quality = '1080p';
            elseif (stripos($text, '720') !== false) $quality = '720p';
            elseif (stripos($text, '480') !== false) $quality = '480p';
            
            if (stripos($text, 'english') !== false) $language = 'English';
            if (stripos($text, 'hindi') !== false) $language = 'Hindi';
        }
        elseif (isset($message['text'])) {
            $text = $message['text'];
        }
        elseif (isset($message['document'])) {
            $text = $message['document']['file_name'];
            $size = round($message['document']['file_size'] / (1024 * 1024), 2) . ' MB';
        }
        else {
            $text = 'Uploaded Media - ' . date('d-m-Y H:i');
        }

        if (!empty(trim($text))) {
            // Pass channel_id as parameter
            append_movie($text, $message_id, $chat_id, date('d-m-Y'), '', $quality, $size, $language);
        }
    }

    // Message handling
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        $chat_type = $message['chat']['type'] ?? 'private';

        $user_info = [
            'first_name' => $message['from']['first_name'] ?? '',
            'last_name' => $message['from']['last_name'] ?? '',
            'username' => $message['from']['username'] ?? ''
        ];
        update_user_data($user_id, $user_info);

        if ($chat_type !== 'private') {
            if (strpos($text, '/') === 0) {
            } else {
                if (!is_valid_movie_query($text)) {
                    bot_log("Invalid group message blocked from $chat_id: $text");
                    return;
                }
            }
        }

        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $params = array_slice($parts, 1);
            
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
        
        $movie_lower = strtolower($data);
        if (isset($movie_messages[$movie_lower])) {
            $entries = $movie_messages[$movie_lower];
            $cnt = 0;
            
            foreach ($entries as $entry) {
                deliver_item_to_chat($chat_id, $entry);
                usleep(200000);
                $cnt++;
            }
            
            sendMessageWithDelay($chat_id, "✅ '$data' ke $cnt items ka info mil gaya!\n\n📢 Join our channel to download: " . MAIN_CHANNEL, null, null, 500);
            answerCallbackQuery($query['id'], "🎬 $cnt items ka info sent!");
            update_user_activity($user_id, 'download');
        }
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
        elseif (strpos($data, 'send_') === 0) {
            $parts = explode('_', $data);
            $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page_num, []);
            batch_download_with_progress($chat_id, $pg['slice'], $page_num);
            answerCallbackQuery($query['id'], "📦 Batch info started!");
        }
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
                $channel_icon = (isset($movie['channel_type']) && $movie['channel_type'] == 'theater') ? '🎭' : '🍿';
                $preview_msg .= ($i + 1) . ". $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
                $preview_msg .= "   ⭐ " . ($movie['quality'] ?? 'Unknown') . "\n\n";
            }
            
            sendMessageWithDelay($chat_id, $preview_msg, null, 'HTML', 500);
            answerCallbackQuery($query['id'], "Preview sent");
        }
        elseif (strpos($data, 'flt_') === 0) {
            $parts = explode('_', $data);
            $filter_type = $parts[1];
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            $filters = [];
            if ($filter_type == 'hd') {
                $filters = ['quality' => '1080'];
                answerCallbackQuery($query['id'], "HD filter applied");
            } elseif ($filter_type == 'theater') {
                $filters = ['channel_type' => 'theater'];
                answerCallbackQuery($query['id'], "Theater filter applied");
            } elseif ($filter_type == 'clr') {
                answerCallbackQuery($query['id'], "Filters cleared");
            }
            
            totalupload_controller($chat_id, 1, $filters, $session_id);
        }
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
            sendMessageWithDelay($chat_id, "📝 To request a movie, use:\n<code>/request movie_name</code>\n\nExample: <code>/request Avengers Endgame</code>", null, 'HTML', 500);
            answerCallbackQuery($query['id'], "Request instructions sent");
        }
        elseif ($data === 'help_command') {
            $command = '/help';
            $params = [];
            handle_command($chat_id, $user_id, $command, $params);
            answerCallbackQuery($query['id'], "Help menu");
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
        elseif ($data === 'close_' || strpos($data, 'close_') === 0) {
            deleteMessage($chat_id, $message['message_id']);
            sendMessageWithDelay($chat_id, "🗂️ Pagination closed. Use /totalupload to browse again.", null, null, 500);
            answerCallbackQuery($query['id'], "Pagination closed");
        }
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
        else {
            sendMessageWithDelay($chat_id, "❌ Movie not found: " . $data . "\n\nTry searching with exact name!", null, null, 500);
            answerCallbackQuery($query['id'], "❌ Movie not available");
        }
    }

    // Scheduled tasks
    $current_hour = date('H');
    $current_minute = date('i');

    if ($current_hour == AUTO_BACKUP_HOUR && $current_minute == '00') {
        auto_backup();
        bot_log("Daily auto-backup completed");
    }

    if ($current_minute == '30') {
        global $movie_cache;
        $movie_cache = [];
        bot_log("Hourly cache cleanup");
    }
}

// ==============================
// MANUAL TESTING
// ==============================
if (isset($_GET['test_save'])) {
    function manual_save_to_csv($movie_name, $message_id, $channel_id) {
        $entry = [$movie_name, $message_id, $channel_id];
        $handle = fopen(CSV_FILE, "a");
        if ($handle !== FALSE) {
            fputcsv($handle, $entry);
            fclose($handle);
            @chmod(CSV_FILE, 0666);
            return true;
        }
        return false;
    }
    
    // Example: Main channel movie
    manual_save_to_csv("Animal (2023) Hindi 1080p", 1927, CHANNEL_ID);
    // Example: Theater channel movie  
    manual_save_to_csv("KGF Chapter 2 Theater Print", 1929, THEATER_CHANNEL_ID);
    
    echo "✅ Movies manually save ho gayi!<br>";
    exit;
}

// Webhook setup
if (php_sapi_name() === 'cli' || isset($_GET['setwebhook'])) {
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
    
    echo "<h3>System Status</h3>";
    echo "<p>CSV File: " . (file_exists(CSV_FILE) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Users File: " . (file_exists(USERS_FILE) ? "✅ Exists" : "❌ Missing") . "</p>";
    
    exit;
}

// Default page display
if (!isset($update) || !$update) {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    echo "<h1>🎬 Entertainment Tadka Bot</h1>";
    echo "<p><strong>Status:</strong> ✅ Running (Slow Mode Active)</p>";
    echo "<p><strong>Typing Delay:</strong> " . TYPING_DELAY_MS . "ms</p>";
    echo "<p><strong>Total Movies:</strong> " . ($stats['total_movies'] ?? 0) . "</p>";
    echo "<p><strong>Total Users:</strong> " . count($users_data['users'] ?? []) . "</p>";
    
    echo "<h3>🚀 Features:</h3>";
    echo "<ul>";
    echo "<li>✅ Smart movie search with typing delay</li>";
    echo "<li>✅ Enhanced pagination with filters</li>";
    echo "<li>✅ Theater channel support</li>";
    echo "<li>✅ Movie request system</li>";
    echo "<li>✅ Auto-backup system</li>";
    echo "<li>✅ Slow Mode Active (Realistic typing)</li>";
    echo "<li>✅ <b>NEW CSV FORMAT:</b> movie_name,message_id,channel_id</li>";
    echo "</ul>";
    
    echo "<p><a href='?setwebhook=1'>Set Webhook Now</a></p>";
}
?>
