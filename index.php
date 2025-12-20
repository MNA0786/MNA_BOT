<?php
// ==============================
// SECURITY HEADERS & BASIC SETUP
// ==============================
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ==============================
// RENDER.COM CONFIGURATION
// ==============================
$port = getenv('PORT') ?: '80';
$webhook_url = getenv('RENDER_EXTERNAL_URL') ?: 'https://your-bot-name.onrender.com';

if (!getenv('BOT_TOKEN')) {
    die("❌ BOT_TOKEN environment variable set nahi hai. Render.com dashboard mein set karo.");
}

// ==============================
// ESSENTIAL CONFIGURATION
// ==============================
define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('ADMIN_ID', (int)getenv('ADMIN_ID', '1080317415'));

// 5 CHANNELS CONFIGURATION - ALL MOVIES.CSV FILES
$channels = [
    'main' => [
        'username' => '@EntertainmentTadka786',
        'id' => '-1003181705395',
        'csv' => 'movies.csv'  // Main channel ka movies.csv
    ],
    'theater' => [
        'username' => '@threater_print_movies',
        'id' => '-1002831605258',
        'csv' => 'movies_theater.csv'  // Theater channel ka alag CSV
    ],
    'private' => [
        'username' => 'Private Channel',
        'id' => '-1003251791991',
        'csv' => 'movies_private.csv'  // Private channel ka alag CSV
    ],
    'backup1' => [
        'username' => 'Backup Channel 1',
        'id' => '-1002337293281',
        'csv' => 'movies_backup1.csv'  // Backup 1 ka alag CSV
    ],
    'backup2' => [
        'username' => '@ETBackup',
        'id' => '-1002964109368',
        'csv' => 'movies_backup2.csv'  // Backup 2 ka alag CSV
    ]
];

define('REQUEST_CHANNEL', '@EntertainmentTadka7860');

// FILE PATHS
define('USERS_FILE', 'users.json');
define('STATS_FILE', 'bot_stats.json');
define('REQUEST_FILE', 'movie_requests.json');
define('LOG_FILE', 'bot_activity.log');
define('BACKUP_DIR', 'backups/');

// SIMPLE CONSTANTS
define('ITEMS_PER_PAGE', 10);
define('MAX_SEARCH_RESULTS', 10);
define('DAILY_REQUEST_LIMIT', 5);

// ==============================
// GLOBAL VARIABLES
// ==============================
$search_sessions = [];  // Store search results temporarily

// ==============================
// FILE INITIALIZATION
// ==============================
function initialize_files() {
    global $channels;
    
    // Initialize ALL channel CSV files with SIMPLE 2-COLUMN FORMAT
    foreach ($channels as $channel) {
        if (!file_exists($channel['csv'])) {
            file_put_contents($channel['csv'], "movie_name,message_id\n");
            @chmod($channel['csv'], 0666);
            bot_log("Created CSV: {$channel['csv']}");
        }
    }
    
    // Initialize other files
    $files = [
        USERS_FILE => json_encode(['users' => []], JSON_PRETTY_PRINT),
        STATS_FILE => json_encode([
            'total_movies' => 0,
            'total_users' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
            'last_updated' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT),
        REQUEST_FILE => json_encode(['requests' => []], JSON_PRETTY_PRINT)
    ];
    
    foreach ($files as $file => $content) {
        if (!file_exists($file)) {
            file_put_contents($file, $content);
            @chmod($file, 0666);
        }
    }
    
    // Create backup directory
    if (!file_exists(BACKUP_DIR)) {
        @mkdir(BACKUP_DIR, 0777, true);
    }
    
    // Create log file
    if (!file_exists(LOG_FILE)) {
        file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] SYSTEM: Files initialized\n");
    }
}

initialize_files();

// ==============================
// LOGGING SYSTEM
// ==============================
function bot_log($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
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
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        bot_log("API Request failed: $method");
    }
    
    return $result;
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
    bot_log("Message sent to $chat_id");
    return json_decode($result, true);
}

function copyMessage($chat_id, $from_chat_id, $message_id) {
    $result = apiRequest('copyMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
    return json_decode($result, true);
}

function answerCallbackQuery($callback_query_id, $text = null) {
    $data = ['callback_query_id' => $callback_query_id];
    if ($text) $data['text'] = $text;
    apiRequest('answerCallbackQuery', $data);
}

function deleteMessage($chat_id, $message_id) {
    apiRequest('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

// ==============================
// MOVIES.CSV MANAGEMENT - SIMPLE 2-COLUMN FORMAT
// ==============================

/**
 * Load movies from a specific channel's CSV file
 * Format: movie_name,message_id (only 2 columns)
 */
function load_channel_csv($csv_file) {
    $movies = [];
    
    if (!file_exists($csv_file)) {
        // Create file with simple header
        file_put_contents($csv_file, "movie_name,message_id\n");
        return $movies;
    }
    
    $handle = fopen($csv_file, "r");
    if ($handle !== FALSE) {
        fgetcsv($handle); // Skip header
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 2 && !empty(trim($row[0]))) {
                $movies[] = [
                    'name' => trim($row[0]),
                    'message_id' => trim($row[1]),
                    'csv_file' => $csv_file
                ];
            }
        }
        fclose($handle);
    }
    
    return $movies;
}

/**
 * Append movie to a specific channel's CSV
 * Format: movie_name,message_id (only 2 columns)
 */
function append_movie_to_csv($csv_file, $movie_name, $message_id) {
    if (empty(trim($movie_name))) return;
    
    // Check if movie already exists in this CSV
    $existing_movies = load_channel_csv($csv_file);
    foreach ($existing_movies as $movie) {
        if ($movie['message_id'] == $message_id) {
            bot_log("Movie already exists in $csv_file: $movie_name");
            return; // Don't add duplicates
        }
    }
    
    // Open file in append mode
    $handle = fopen($csv_file, "a");
    if ($handle !== FALSE) {
        fputcsv($handle, [$movie_name, $message_id]);
        fclose($handle);
        
        // Update total movies count
        $stats = json_decode(file_get_contents(STATS_FILE), true);
        $stats['total_movies'] = (int)($stats['total_movies'] ?? 0) + 1;
        $stats['last_updated'] = date('Y-m-d H:i:s');
        file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
        
        bot_log("✓ Movie added to $csv_file: '$movie_name' (ID: $message_id)");
        return true;
    }
    
    return false;
}

/**
 * Get total movies count from all CSVs
 */
function get_total_movies_count() {
    global $channels;
    $total = 0;
    
    foreach ($channels as $channel) {
        $movies = load_channel_csv($channel['csv']);
        $total += count($movies);
    }
    
    return $total;
}

/**
 * Search movies across ALL channel CSV files
 */
function search_all_channels($query) {
    global $channels;
    $results = [];
    $query_lower = strtolower(trim($query));
    
    if (strlen($query_lower) < 2) {
        return $results;
    }
    
    foreach ($channels as $channel_type => $channel) {
        $movies = load_channel_csv($channel['csv']);
        
        foreach ($movies as $movie) {
            $movie_lower = strtolower($movie['name']);
            
            // Simple search: check if query is in movie name
            if (strpos($movie_lower, $query_lower) !== false) {
                $score = 0;
                
                // Exact match gets highest priority
                if ($movie_lower == $query_lower) {
                    $score = 100;
                }
                // Partial match
                elseif (strpos($movie_lower, $query_lower) !== false) {
                    $score = 80;
                }
                
                $results[] = [
                    'name' => $movie['name'],
                    'message_id' => $movie['message_id'],
                    'channel_id' => $channel['id'],
                    'channel_name' => $channel['username'],
                    'channel_type' => $channel_type,
                    'csv_file' => $channel['csv'],
                    'score' => $score
                ];
                
                // Limit total results
                if (count($results) >= MAX_SEARCH_RESULTS) {
                    break 2;
                }
            }
        }
    }
    
    // Sort by score (highest first)
    usort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return $results;
}

// ==============================
// MOVIE DELIVERY (NO HEADERS)
// ==============================

/**
 * Deliver movie without showing channel header
 * Uses copyMessage instead of forwardMessage
 */
function deliver_movie($chat_id, $movie_data) {
    if (empty($movie_data['message_id']) || !is_numeric($movie_data['message_id'])) {
        // If no valid message ID, send info message
        $message = "🎬 <b>" . htmlspecialchars($movie_data['name']) . "</b>\n";
        $message .= "📢 Channel: " . $movie_data['channel_name'] . "\n";
        $message .= "🔗 Message ID: " . $movie_data['message_id'] . "\n\n";
        $message .= "⚠️ Could not send movie directly.\n";
        $message .= "Please check the channel: " . $movie_data['channel_name'];
        
        sendMessage($chat_id, $message, null, 'HTML');
        return false;
    }
    
    // Try to copy message (no header shown)
    $result = copyMessage($chat_id, $movie_data['channel_id'], $movie_data['message_id']);
    
    if ($result && isset($result['ok']) && $result['ok']) {
        // Update download statistics
        $stats = json_decode(file_get_contents(STATS_FILE), true);
        $stats['total_downloads'] = (int)($stats['total_downloads'] ?? 0) + 1;
        file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
        
        // Update user download count
        update_user_download_count($chat_id);
        
        bot_log("✓ Movie delivered: '{$movie_data['name']}' to $chat_id (No header)");
        return true;
    } else {
        // If copy failed, send fallback message
        $message = "🎬 <b>" . htmlspecialchars($movie_data['name']) . "</b>\n";
        $message .= "📢 Channel: " . $movie_data['channel_name'] . "\n";
        $message .= "🔗 Message ID: " . $movie_data['message_id'] . "\n\n";
        $message .= "⚠️ Could not send movie directly.\n";
        $message .= "Visit: " . $movie_data['channel_name'];
        
        sendMessage($chat_id, $message, null, 'HTML');
        bot_log("✗ Copy failed for: '{$movie_data['name']}' to $chat_id");
        return false;
    }
}

// ==============================
// USER MANAGEMENT
// ==============================

function update_user($user_id, $user_info) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id] = [
            'first_name' => $user_info['first_name'] ?? '',
            'username' => $user_info['username'] ?? '',
            'joined' => date('Y-m-d H:i:s'),
            'last_active' => date('Y-m-d H:i:s'),
            'search_count' => 0,
            'download_count' => 0,
            'request_count' => 0
        ];
        
        // Update total users count
        $stats = json_decode(file_get_contents(STATS_FILE), true);
        $stats['total_users'] = (int)($stats['total_users'] ?? 0) + 1;
        file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
        
        bot_log("New user registered: $user_id ({$user_info['first_name']})");
    }
    
    $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    
    return $users_data['users'][$user_id];
}

function update_user_search_count($user_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id]['search_count'] = 
            (int)($users_data['users'][$user_id]['search_count'] ?? 0) + 1;
        
        file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    }
}

function update_user_download_count($user_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id]['download_count'] = 
            (int)($users_data['users'][$user_id]['download_count'] ?? 0) + 1;
        
        file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    }
}

// ==============================
// MOVIE REQUEST SYSTEM
// ==============================

function can_request_movie($user_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $today = date('Y-m-d');
    $count = 0;
    
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id && $request['date'] == $today) {
            $count++;
        }
    }
    
    return $count < DAILY_REQUEST_LIMIT;
}

function add_movie_request($user_id, $movie_name) {
    if (!can_request_movie($user_id)) {
        return [
            'success' => false,
            'message' => "❌ Daily request limit reached (" . DAILY_REQUEST_LIMIT . " requests/day)"
        ];
    }
    
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    
    $request_id = uniqid();
    $requests_data['requests'][] = [
        'id' => $request_id,
        'user_id' => $user_id,
        'movie_name' => $movie_name,
        'date' => date('Y-m-d'),
        'time' => date('H:i:s'),
        'status' => 'pending'
    ];
    
    file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
    
    // Update user request count
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    if (isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id]['request_count'] = 
            (int)($users_data['users'][$user_id]['request_count'] ?? 0) + 1;
        file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    }
    
    // Notify admin
    $admin_message = "📝 <b>New Movie Request</b>\n\n";
    $admin_message .= "🎬 Movie: <code>$movie_name</code>\n";
    $admin_message .= "👤 User ID: <code>$user_id</code>\n";
    $admin_message .= "🆔 Request ID: <code>$request_id</code>\n";
    $admin_message .= "📅 Date: " . date('Y-m-d H:i:s');
    
    sendMessage(ADMIN_ID, $admin_message, null, 'HTML');
    
    bot_log("Movie request added: '$movie_name' by $user_id (ID: $request_id)");
    
    return [
        'success' => true,
        'message' => "✅ Request received: <b>$movie_name</b>\n\n🆔 Request ID: <code>$request_id</code>\n📅 We'll add it soon!",
        'request_id' => $request_id
    ];
}

// ==============================
// PAGINATION SYSTEM
// ==============================

function show_movies_page($chat_id, $page = 1) {
    global $channels;
    $all_movies = [];
    
    // Load movies from ALL channels
    foreach ($channels as $channel_type => $channel) {
        $movies = load_channel_csv($channel['csv']);
        
        foreach ($movies as $movie) {
            $movie['channel_name'] = $channel['username'];
            $movie['channel_type'] = $channel_type;
            $all_movies[] = $movie;
        }
    }
    
    if (empty($all_movies)) {
        sendMessage($chat_id, "📭 No movies found in database.\n\nAdd movies to your channels first!");
        return;
    }
    
    // Sort by message_id (newest first - assuming higher ID = newer)
    usort($all_movies, function($a, $b) {
        $id_a = (int)$a['message_id'];
        $id_b = (int)$b['message_id'];
        return $id_b - $id_a;
    });
    
    $total_movies = count($all_movies);
    $total_pages = ceil($total_movies / ITEMS_PER_PAGE);
    $page = max(1, min($page, $total_pages));
    $start_index = ($page - 1) * ITEMS_PER_PAGE;
    $page_movies = array_slice($all_movies, $start_index, ITEMS_PER_PAGE);
    
    // Build the paginated message
    $message = "📁 <b>All Movies - Page $page/$total_pages</b>\n\n";
    $message .= "📊 Total Movies: $total_movies\n";
    $message .= "📢 Channels: " . count($channels) . "\n\n";
    
    $item_number = $start_index + 1;
    foreach ($page_movies as $movie) {
        $movie_name = htmlspecialchars($movie['name']);
        $channel_name = $movie['channel_name'];
        
        $message .= "<b>$item_number.</b> $movie_name\n";
        $message .= "   📢 $channel_name\n\n";
        $item_number++;
    }
    
    // Create pagination keyboard
    $keyboard = ['inline_keyboard' => []];
    $nav_buttons = [];
    
    // Previous button
    if ($page > 1) {
        $nav_buttons[] = ['text' => '◀️ Previous', 'callback_data' => 'page_' . ($page - 1)];
    }
    
    // Page indicator (clickable for current page)
    $nav_buttons[] = ['text' => "📄 $page", 'callback_data' => 'current_page'];
    
    // Next button
    if ($page < $total_pages) {
        $nav_buttons[] = ['text' => 'Next ▶️', 'callback_data' => 'page_' . ($page + 1)];
    }
    
    if (!empty($nav_buttons)) {
        $keyboard['inline_keyboard'][] = $nav_buttons;
    }
    
    // Add search button
    $keyboard['inline_keyboard'][] = [
        ['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => '']
    ];
    
    // Send the paginated message
    sendMessage($chat_id, $message, $keyboard, 'HTML');
    bot_log("Pagination page $page shown to $chat_id ($total_movies movies total)");
}

// ==============================
// BACKUP SYSTEM
// ==============================

function create_backup() {
    global $channels;
    $backup_dir = BACKUP_DIR . date('Y-m-d_H-i-s');
    
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $backup_files = [];
    
    // Backup all CSV files
    foreach ($channels as $channel) {
        if (file_exists($channel['csv'])) {
            $backup_path = $backup_dir . '/' . basename($channel['csv']) . '.bak';
            if (copy($channel['csv'], $backup_path)) {
                $backup_files[] = $channel['csv'];
                bot_log("Backed up: {$channel['csv']}");
            }
        }
    }
    
    // Backup other essential files
    $essential_files = [USERS_FILE, STATS_FILE, REQUEST_FILE, LOG_FILE];
    foreach ($essential_files as $file) {
        if (file_exists($file)) {
            $backup_path = $backup_dir . '/' . basename($file) . '.bak';
            if (copy($file, $backup_path)) {
                $backup_files[] = $file;
            }
        }
    }
    
    // Create backup summary
    $summary = "📊 Backup Summary\n";
    $summary .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "Files: " . count($backup_files) . "\n";
    $summary .= "Total Movies: " . get_total_movies_count() . "\n";
    $summary .= "Channels: " . count($channels) . "\n\n";
    
    foreach ($channels as $channel) {
        $movies = load_channel_csv($channel['csv']);
        $summary .= "• {$channel['username']}: " . count($movies) . " movies\n";
    }
    
    file_put_contents($backup_dir . '/backup_summary.txt', $summary);
    
    // Clean old backups (keep last 7 days)
    clean_old_backups();
    
    bot_log("Backup created: " . basename($backup_dir) . " with " . count($backup_files) . " files");
    
    return [
        'success' => true,
        'backup_dir' => $backup_dir,
        'file_count' => count($backup_files),
        'movie_count' => get_total_movies_count()
    ];
}

function clean_old_backups() {
    $backup_folders = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    
    if (count($backup_folders) > 7) {
        // Sort by creation time (oldest first)
        usort($backup_folders, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Delete oldest backups beyond 7
        $to_delete = count($backup_folders) - 7;
        $deleted = 0;
        
        for ($i = 0; $i < $to_delete; $i++) {
            $files = glob($backup_folders[$i] . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            if (@rmdir($backup_folders[$i])) {
                $deleted++;
                bot_log("Deleted old backup: " . basename($backup_folders[$i]));
            }
        }
        
        bot_log("Cleaned $deleted old backups");
    }
}

// ==============================
// COMMAND HANDLER
// ==============================

function handle_command($chat_id, $user_id, $command, $params = []) {
    global $channels, $search_sessions;
    
    // Update user activity
    update_user($user_id, [
        'first_name' => 'User',
        'username' => 'user_' . $user_id
    ]);
    
    switch ($command) {
        // ========== CORE COMMANDS ==========
        case '/start':
            $message = "🎬 <b>Welcome to Entertainment Tadka!</b>\n\n";
            $message .= "🤖 <b>I'm your movie bot with:</b>\n";
            $message .= "• 5 Separate Movie Channels\n";
            $message .= "• Header-less Movie Delivery\n";
            $message .= "• Smart Search System\n";
            $message .= "• Simple & Fast Interface\n\n";
            
            $message .= "🔍 <b>How to use:</b>\n";
            $message .= "1. Type any movie name to search\n";
            $message .= "2. Or use /search movie_name\n";
            $message .= "3. Browse all movies with /browse\n";
            $message .= "4. Request movies with /request\n\n";
            
            $message .= "📢 <b>Main Channels:</b>\n";
            $message .= "• " . $channels['main']['username'] . "\n";
            $message .= "• " . $channels['theater']['username'] . "\n";
            $message .= "• " . REQUEST_CHANNEL . " (Requests)\n\n";
            
            $message .= "💬 <b>Need help?</b> Use /help";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => ''],
                        ['text' => '📁 Browse All', 'callback_data' => 'page_1']
                    ],
                    [
                        ['text' => '🎬 Main Channel', 'url' => 'https://t.me/EntertainmentTadka786'],
                        ['text' => '🎭 Theater Prints', 'url' => 'https://t.me/threater_print_movies']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $message, $keyboard, 'HTML');
            bot_log("User $user_id started bot");
            break;
            
        case '/help':
            $help = "🤖 <b>Available Commands</b>\n\n";
            
            $help .= "🔍 <b>SEARCH MOVIES:</b>\n";
            $help .= "• Just type movie name (e.g., 'animal')\n";
            $help .= "• Or use /search movie_name\n";
            $help .= "• Searches across 5 channels\n\n";
            
            $help .= "📁 <b>BROWSE MOVIES:</b>\n";
            $help .= "• /browse - View all movies\n";
            $help .= "• /browse [page] - Go to page\n";
            $help .= "• Example: /browse 2\n\n";
            
            $help .= "📝 <b>REQUEST MOVIES:</b>\n";
            $help .= "• /request movie_name\n";
            $help .= "• Limit: " . DAILY_REQUEST_LIMIT . " per day\n";
            $help .= "• Example: /request Tiger 3\n\n";
            
            $help .= "📊 <b>BOT INFO:</b>\n";
            $help .= "• /stats - Statistics\n";
            $help .= "• /channels - Channel list\n\n";
            
            $help .= "🛠️ <b>ADMIN COMMANDS:</b>\n";
            $help .= "• /backup - Create backup\n";
            $help .= "• /broadcast - Send message to all\n\n";
            
            $help .= "🔗 <b>CHANNELS:</b>\n";
            $help .= "• " . $channels['main']['username'] . "\n";
            $help .= "• " . $channels['theater']['username'] . "\n";
            $help .= "• " . REQUEST_CHANNEL;
            
            sendMessage($chat_id, $help, null, 'HTML');
            break;
            
        // ========== SEARCH COMMANDS ==========
        case '/search':
            $query = implode(' ', $params);
            if (empty($query)) {
                sendMessage($chat_id, "🔍 <b>Search Usage:</b>\n\n<code>/search movie_name</code>\n\nExample:\n<code>/search Animal 2023</code>\n<code>/search Jawan</code>", null, 'HTML');
                return;
            }
            
            sendMessage($chat_id, "🔍 Searching for: <b>$query</b>", null, 'HTML');
            
            // Update search stats
            $stats = json_decode(file_get_contents(STATS_FILE), true);
            $stats['total_searches'] = (int)($stats['total_searches'] ?? 0) + 1;
            file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
            
            update_user_search_count($user_id);
            
            $results = search_all_channels($query);
            
            if (empty($results)) {
                $not_found_msg = "❌ No movies found for: <b>$query</b>\n\n";
                $not_found_msg .= "📝 <b>You can:</b>\n";
                $not_found_msg .= "1. Check spelling\n";
                $not_found_msg .= "2. Try different keywords\n";
                $not_found_msg .= "3. Request this movie:\n";
                $not_found_msg .= "   <code>/request $query</code>\n\n";
                $not_found_msg .= "📢 Join: " . REQUEST_CHANNEL . " for updates";
                
                sendMessage($chat_id, $not_found_msg, null, 'HTML');
                bot_log("Search failed: '$query' by $user_id");
                return;
            }
            
            $message = "✅ Found " . count($results) . " movies for '<b>$query</b>':\n\n";
            
            // Create inline keyboard with results
            $keyboard = ['inline_keyboard' => []];
            
            foreach ($results as $index => $movie) {
                $short_name = (strlen($movie['name']) > 35) 
                    ? substr($movie['name'], 0, 32) . '...' 
                    : $movie['name'];
                
                $message .= ($index + 1) . ". <b>" . htmlspecialchars($movie['name']) . "</b>\n";
                $message .= "   📢 " . $movie['channel_name'] . "\n\n";
                
                $keyboard['inline_keyboard'][] = [
                    ['text' => ($index + 1) . '. ' . $short_name, 'callback_data' => 'movie_' . $index]
                ];
            }
            
            // Store search results in session
            $session_id = uniqid();
            $search_sessions[$session_id] = [
                'results' => $results,
                'query' => $query,
                'timestamp' => time(),
                'user_id' => $user_id
            ];
            
            // Add session ID to each callback data
            foreach ($keyboard['inline_keyboard'] as &$row) {
                foreach ($row as &$button) {
                    if (isset($button['callback_data'])) {
                        $button['callback_data'] .= '_' . $session_id;
                    }
                }
            }
            
            // Add request button
            $keyboard['inline_keyboard'][] = [
                ['text' => '📝 Request Different Movie', 'callback_data' => 'request_movie_' . $session_id]
            ];
            
            sendMessage($chat_id, $message, $keyboard, 'HTML');
            bot_log("Search successful: '$query' found " . count($results) . " movies for $user_id");
            break;
            
        // ========== BROWSE COMMANDS ==========
        case '/browse':
            $page = isset($params[0]) ? intval($params[0]) : 1;
            
            if ($page < 1) {
                sendMessage($chat_id, "❌ Page number must be 1 or greater.");
                return;
            }
            
            show_movies_page($chat_id, $page);
            break;
            
        // ========== REQUEST COMMANDS ==========
        case '/request':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "📝 <b>Request Usage:</b>\n\n<code>/request movie_name</code>\n\nExample:\n<code>/request Animal 2023</code>\n<code>/request New Hindi Movie</code>\n\nLimit: " . DAILY_REQUEST_LIMIT . " requests per day", null, 'HTML');
                return;
            }
            
            $result = add_movie_request($user_id, $movie_name);
            sendMessage($chat_id, $result['message'], null, 'HTML');
            break;
            
        // ========== INFO COMMANDS ==========
        case '/stats':
            $stats = json_decode(file_get_contents(STATS_FILE), true);
            $users_data = json_decode(file_get_contents(USERS_FILE), true);
            $total_users = count($users_data['users'] ?? []);
            $total_movies = get_total_movies_count();
            
            $message = "📊 <b>Bot Statistics</b>\n\n";
            $message .= "🎬 <b>Movies:</b> $total_movies\n";
            $message .= "👥 <b>Users:</b> $total_users\n";
            $message .= "🔍 <b>Searches:</b> " . ($stats['total_searches'] ?? 0) . "\n";
            $message .= "📥 <b>Downloads:</b> " . ($stats['total_downloads'] ?? 0) . "\n";
            $message .= "📁 <b>Channels:</b> " . count($channels) . "\n";
            $message .= "🕒 <b>Last Updated:</b> " . ($stats['last_updated'] ?? 'N/A') . "\n\n";
            
            // Channel-wise movie counts
            $message .= "📢 <b>Movies per Channel:</b>\n";
            foreach ($channels as $type => $channel) {
                if ($type != 'backup1' && $type != 'backup2') {
                    $movies = load_channel_csv($channel['csv']);
                    $message .= "• " . $channel['username'] . ": " . count($movies) . "\n";
                }
            }
            
            sendMessage($chat_id, $message, null, 'HTML');
            break;
            
        case '/channels':
            $message = "📢 <b>Our Movie Channels</b>\n\n";
            
            foreach ($channels as $type => $channel) {
                if ($type != 'backup1' && $type != 'backup2') {
                    $movies = load_channel_csv($channel['csv']);
                    $movie_count = count($movies);
                    $message .= "🎬 <b>" . $channel['username'] . "</b>\n";
                    $message .= "   Movies: $movie_count\n";
                    $message .= "   ID: <code>" . $channel['id'] . "</code>\n\n";
                }
            }
            
            $message .= "📝 <b>Requests & Support:</b>\n";
            $message .= "• " . REQUEST_CHANNEL . "\n\n";
            
            $message .= "🔔 <b>Join all channels for best experience!</b>";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🎬 Main Channel', 'url' => 'https://t.me/EntertainmentTadka786'],
                        ['text' => '🎭 Theater Prints', 'url' => 'https://t.me/threater_print_movies']
                    ],
                    [
                        ['text' => '📥 Request Channel', 'url' => 'https://t.me/EntertainmentTadka7860']
                    ],
                    [
                        ['text' => '📁 Browse Movies', 'callback_data' => 'page_1']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $message, $keyboard, 'HTML');
            break;
            
        // ========== ADMIN COMMANDS ==========
        case '/backup':
            if ($chat_id == ADMIN_ID) {
                sendMessage($chat_id, "💾 Starting backup process...");
                
                $result = create_backup();
                
                if ($result['success']) {
                    $backup_msg = "✅ <b>Backup Completed</b>\n\n";
                    $backup_msg .= "📅 Date: " . date('Y-m-d H:i:s') . "\n";
                    $backup_msg .= "📁 Files: " . $result['file_count'] . "\n";
                    $backup_msg .= "🎬 Movies: " . $result['movie_count'] . "\n";
                    $backup_msg .= "📂 Location: " . basename($result['backup_dir']) . "\n\n";
                    $backup_msg .= "🛡️ Last 7 backups kept automatically.";
                    
                    sendMessage($chat_id, $backup_msg, null, 'HTML');
                    bot_log("Manual backup by admin");
                } else {
                    sendMessage($chat_id, "❌ Backup failed!");
                }
            } else {
                sendMessage($chat_id, "❌ Admin only command.");
            }
            break;
            
        case '/broadcast':
            if ($chat_id == ADMIN_ID) {
                $message = implode(' ', $params);
                if (empty($message)) {
                    sendMessage($chat_id, "📢 <b>Broadcast Usage:</b>\n\n<code>/broadcast your_message</code>\n\nExample:\n<code>/broadcast New movies added! Check /browse</code>", null, 'HTML');
                    return;
                }
                
                $users_data = json_decode(file_get_contents(USERS_FILE), true);
                $total_users = count($users_data['users'] ?? []);
                
                $progress_msg = sendMessage($chat_id, "📢 Broadcasting to $total_users users...\n\nProgress: 0%", null, 'HTML');
                $progress_msg_id = $progress_msg['result']['message_id'];
                
                $sent = 0;
                $failed = 0;
                $count = 0;
                
                foreach ($users_data['users'] as $uid => $user) {
                    try {
                        sendMessage($uid, "📢 <b>Announcement from Admin:</b>\n\n$message", null, 'HTML');
                        $sent++;
                    } catch (Exception $e) {
                        $failed++;
                    }
                    
                    $count++;
                    
                    // Update progress every 10 users
                    if ($count % 10 == 0) {
                        $progress = round(($count / $total_users) * 100);
                        editMessage($chat_id, $progress_msg_id, "📢 Broadcasting to $total_users users...\n\nProgress: $progress%\n✅ Sent: $sent\n❌ Failed: $failed");
                    }
                    
                    usleep(100000); // 0.1 second delay
                }
                
                editMessage($chat_id, $progress_msg_id, "✅ <b>Broadcast Complete</b>\n\n📤 Sent to: $sent/$total_users users\n❌ Failed: $failed\n\n⏱️ Time: " . date('H:i:s'), null, 'HTML');
                bot_log("Broadcast sent by admin to $sent users");
            } else {
                sendMessage($chat_id, "❌ Admin only command.");
            }
            break;
            
        default:
            // If not a command, treat as search query
            if (strlen($command) > 1 && $command[0] != '/') {
                handle_command($chat_id, $user_id, '/search', [$command]);
            } else {
                sendMessage($chat_id, "❌ Unknown command. Use /help to see all commands.");
            }
    }
}

// ==============================
// MAIN UPDATE PROCESSING
// ==============================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    // ========== CHANNEL POSTS ==========
    if (isset($update['channel_post'])) {
        $message = $update['channel_post'];
        $message_id = $message['message_id'];
        $chat_id = $message['chat']['id'];
        
        global $channels;
        $csv_file = null;
        $channel_info = null;
        
        // Find which channel this is
        foreach ($channels as $type => $channel) {
            if ($chat_id == $channel['id']) {
                $csv_file = $channel['csv'];
                $channel_info = $channel;
                break;
            }
        }
        
        if ($csv_file) {
            $text = '';
            $media_type = 'unknown';
            
            if (isset($message['caption'])) {
                $text = $message['caption'];
                $media_type = 'media_with_caption';
            } elseif (isset($message['text'])) {
                $text = $message['text'];
                $media_type = 'text';
            } elseif (isset($message['document'])) {
                $text = $message['document']['file_name'];
                $media_type = 'document';
            } elseif (isset($message['video'])) {
                $text = 'Video - ' . date('d-m-Y H:i');
                $media_type = 'video';
            } else {
                $text = 'Media - ' . date('d-m-Y H:i');
                $media_type = 'media';
            }
            
            if (!empty(trim($text))) {
                $success = append_movie_to_csv($csv_file, $text, $message_id);
                
                if ($success) {
                    // Notify admin about new addition
                    $admin_msg = "🎬 <b>New Movie Added</b>\n\n";
                    $admin_msg .= "📝 Title: <code>" . htmlspecialchars($text) . "</code>\n";
                    $admin_msg .= "📢 Channel: " . $channel_info['username'] . "\n";
                    $admin_msg .= "🆔 Message ID: <code>$message_id</code>\n";
                    $admin_msg .= "📁 CSV File: " . basename($csv_file) . "\n";
                    $admin_msg .= "📅 Time: " . date('Y-m-d H:i:s') . "\n";
                    $admin_msg .= "🎥 Type: $media_type";
                    
                    sendMessage(ADMIN_ID, $admin_msg, null, 'HTML');
                    
                    bot_log("Auto-added movie to {$channel_info['username']}: '$text' (ID: $message_id)");
                }
            }
        }
    }
    
    // ========== USER MESSAGES ==========
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        $chat_type = $message['chat']['type'] ?? 'private';
        
        // Update user with actual info
        update_user($user_id, [
            'first_name' => $message['from']['first_name'] ?? '',
            'username' => $message['from']['username'] ?? ''
        ]);
        
        // Handle command or search
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $params = array_slice($parts, 1);
            
            handle_command($chat_id, $user_id, $command, $params);
        } elseif (!empty(trim($text))) {
            // Treat as search query
            handle_command($chat_id, $user_id, '/search', [$text]);
        }
    }
    
    // ========== CALLBACK QUERIES ==========
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $message = $query['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $query['from']['id'];
        $data = $query['data'];
        
        global $search_sessions;
        
        // Movie selection from search results
        if (strpos($data, 'movie_') === 0) {
            $parts = explode('_', $data);
            $index = intval($parts[1]);
            $session_id = $parts[2] ?? '';
            
            if (isset($search_sessions[$session_id])) {
                $results = $search_sessions[$session_id]['results'];
                
                if (isset($results[$index])) {
                    $movie = $results[$index];
                    deliver_movie($chat_id, $movie);
                    answerCallbackQuery($query['id'], "✅ Movie sent!");
                    
                    // Clean up session after 10 minutes
                    if (time() - $search_sessions[$session_id]['timestamp'] > 600) {
                        unset($search_sessions[$session_id]);
                    }
                }
            }
        }
        
        // Pagination
        elseif (strpos($data, 'page_') === 0) {
            $page = intval(str_replace('page_', '', $data));
            show_movies_page($chat_id, $page);
            answerCallbackQuery($query['id'], "Page $page");
        }
        
        // Current page indicator (do nothing)
        elseif ($data == 'current_page') {
            answerCallbackQuery($query['id'], "You're on this page");
        }
        
        // Request movie button
        elseif (strpos($data, 'request_movie_') === 0) {
            sendMessage($chat_id, "📝 <b>To request a movie:</b>\n\n<code>/request movie_name</code>\n\nExample:\n<code>/request Animal Park 2024</code>\n\nLimit: " . DAILY_REQUEST_LIMIT . " requests per day", null, 'HTML');
            answerCallbackQuery($query['id'], "Request instructions");
        }
    }
    
    // ========== DAILY AUTO-BACKUP AT 3 AM ==========
    if (date('H') == '03' && date('i') == '00') {
        $result = create_backup();
        
        $backup_msg = "🔄 <b>Daily Auto-Backup</b>\n\n";
        $backup_msg .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $backup_msg .= "📁 Files: " . $result['file_count'] . "\n";
        $backup_msg .= "🎬 Movies: " . $result['movie_count'] . "\n";
        $backup_msg .= "✅ Status: Completed\n\n";
        $backup_msg .= "🛡️ Last 7 backups kept";
        
        sendMessage(ADMIN_ID, $backup_msg, null, 'HTML');
        bot_log("Daily auto-backup completed");
    }
    
    // ========== CLEAN OLD SESSIONS EVERY HOUR ==========
    if (date('i') == '30') {
        global $search_sessions;
        $cleaned = 0;
        
        foreach ($search_sessions as $session_id => $session) {
            if (time() - $session['timestamp'] > 600) { // 10 minutes
                unset($search_sessions[$session_id]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            bot_log("Cleaned $cleaned old search sessions");
        }
    }
}

// ==============================
// WEBHOOK SETUP PAGE
// ==============================
if (php_sapi_name() === 'cli' || isset($_GET['setwebhook'])) {
    $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $webhook_url = str_replace('?setwebhook=1', '', $webhook_url);
    
    $result = apiRequest('setWebhook', ['url' => $webhook_url]);
    $result_data = json_decode($result, true);
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Bot Setup - Entertainment Tadka</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
            h2 { color: #444; margin-top: 25px; }
            .success { color: #4CAF50; font-weight: bold; }
            .error { color: #f44336; font-weight: bold; }
            .info-box { background: #e8f5e9; padding: 15px; border-left: 4px solid #4CAF50; margin: 15px 0; }
            .channel-list { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0; }
            .channel-card { background: #f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #ddd; }
            .channel-name { font-weight: bold; color: #333; }
            .channel-id { color: #666; font-size: 12px; }
            .feature-list { list-style: none; padding: 0; }
            .feature-list li { padding: 8px 0; border-bottom: 1px solid #eee; }
            .feature-list li:before { content: '✓'; color: #4CAF50; margin-right: 10px; font-weight: bold; }
            .btn { display: inline-block; background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
            .btn:hover { background: #45a049; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🎬 Entertainment Tadka Bot Setup</h1>";
    
    if ($result_data && isset($result_data['ok']) && $result_data['ok']) {
        echo "<div class='info-box'>
                <p class='success'>✅ Webhook set successfully!</p>
                <p><strong>Webhook URL:</strong> <code>" . htmlspecialchars($webhook_url) . "</code></p>
              </div>";
    } else {
        echo "<div class='info-box'>
                <p class='error'>❌ Webhook setup failed!</p>
                <p><strong>Error:</strong> " . htmlspecialchars($result) . "</p>
              </div>";
    }
    
    $bot_info = json_decode(apiRequest('getMe'), true);
    if ($bot_info && isset($bot_info['ok']) && $bot_info['ok']) {
        echo "<h2>🤖 Bot Information</h2>
              <p><strong>Bot Name:</strong> " . htmlspecialchars($bot_info['result']['first_name']) . "</p>
              <p><strong>Username:</strong> @" . htmlspecialchars($bot_info['result']['username']) . "</p>
              <p><strong>Bot ID:</strong> " . htmlspecialchars($bot_info['result']['id']) . "</p>";
    }
    
    echo "<h2>📢 Channel Configuration</h2>
          <div class='channel-list'>";
    
    global $channels;
    foreach ($channels as $type => $channel) {
        echo "<div class='channel-card'>
                <div class='channel-name'>" . htmlspecialchars($channel['username']) . "</div>
                <div class='channel-id'>ID: " . htmlspecialchars($channel['id']) . "</div>
                <div class='channel-id'>CSV: " . htmlspecialchars($channel['csv']) . "</div>
              </div>";
    }
    
    echo "</div>
          <h2>🚀 Core Features</h2>
          <ul class='feature-list'>
            <li><strong>5 Separate CSV Files</strong> - One for each channel (movies.csv format)</li>
            <li><strong>Header-less Movie Delivery</strong> - Using copyMessage()</li>
            <li><strong>Smart Search</strong> - Across all 5 channels</li>
            <li><strong>Pagination System</strong> - /browse command with pages</li>
            <li><strong>Movie Requests</strong> - /request command with daily limits</li>
            <li><strong>Auto-Backup</strong> - Daily at 3 AM, keeps last 7 backups</li>
            <li><strong>Admin Controls</strong> - /backup and /broadcast commands</li>
            <li><strong>Real-time Stats</strong> - /stats command for monitoring</li>
            <li><strong>No Useless Features</strong> - Removed points, quality filters, etc.</li>
          </ul>
          
          <h2>📊 System Status</h2>";
    
    // Check files
    $files_to_check = [];
    foreach ($channels as $channel) {
        $files_to_check[] = $channel['csv'];
    }
    $files_to_check = array_merge($files_to_check, [USERS_FILE, STATS_FILE, REQUEST_FILE, LOG_FILE]);
    
    foreach ($files_to_check as $file) {
        if (file_exists($file)) {
            $size = filesize($file);
            echo "<p>✅ <strong>" . basename($file) . "</strong> - " . number_format($size) . " bytes</p>";
        } else {
            echo "<p>❌ <strong>" . basename($file) . "</strong> - Missing</p>";
        }
    }
    
    echo "<p><strong>Backup Directory:</strong> " . (file_exists(BACKUP_DIR) ? "✅ Exists" : "❌ Missing") . "</p>";
    
    echo "<h2>🔗 Quick Actions</h2>
          <a href='" . htmlspecialchars($webhook_url) . "?setwebhook=1' class='btn'>🔄 Reset Webhook</a>
          <a href='" . htmlspecialchars($webhook_url) . "' class='btn'>📱 Bot Status</a>
          <a href='https://t.me/" . ($bot_info['result']['username'] ?? '') . "' class='btn'>🤖 Open Bot</a>
          
          <h2>📝 Environment Variables Required</h2>
          <div class='info-box'>
            <p><strong>BOT_TOKEN</strong> = your_telegram_bot_token</p>
            <p><strong>ADMIN_ID</strong> = 1080317415</p>
            <p><strong>Note:</strong> Set these in Render.com dashboard</p>
          </div>
        </div>
    </body>
    </html>";
    
    exit;
}

// ==============================
// DEFAULT STATUS PAGE
// ==============================
if (!isset($update) || !$update) {
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $total_users = count($users_data['users'] ?? []);
    $total_movies = get_total_movies_count();
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Entertainment Tadka Bot</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
            .container { max-width: 1000px; margin: 0 auto; background: rgba(255,255,255,0.95); padding: 30px; border-radius: 15px; color: #333; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
            h1 { color: #764ba2; text-align: center; font-size: 2.5em; margin-bottom: 10px; }
            .tagline { text-align: center; color: #666; font-size: 1.2em; margin-bottom: 30px; }
            .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0; }
            .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 3px 10px rgba(0,0,0,0.1); border-top: 4px solid #667eea; }
            .stat-number { font-size: 2.5em; font-weight: bold; color: #764ba2; margin: 10px 0; }
            .stat-label { color: #666; font-size: 0.9em; }
            .channel-section { margin: 30px 0; }
            .channel-card { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #4CAF50; }
            .channel-name { font-weight: bold; color: #333; font-size: 1.1em; }
            .channel-info { color: #666; font-size: 0.9em; margin-top: 5px; }
            .btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 25px; text-decoration: none; border-radius: 25px; margin: 10px; font-weight: bold; transition: transform 0.3s; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
            .feature-list { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0; }
            .feature-item { background: #e8f5e9; padding: 15px; border-radius: 8px; }
            .status-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: #4CAF50; margin-right: 8px; animation: pulse 2s infinite; }
            @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🎬 Entertainment Tadka Bot</h1>
            <p class='tagline'>Smart Movie Bot with 5-Channel Support</p>
            
            <div style='text-align: center; margin: 20px 0;'>
                <span class='status-indicator'></span>
                <strong style='color: #4CAF50;'>✅ STATUS: RUNNING</strong>
            </div>
            
            <div class='stats-grid'>
                <div class='stat-card'>
                    <div class='stat-number'>" . $total_movies . "</div>
                    <div class='stat-label'>Total Movies</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-number'>" . $total_users . "</div>
                    <div class='stat-label'>Total Users</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-number'>" . ($stats['total_searches'] ?? 0) . "</div>
                    <div class='stat-label'>Total Searches</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-number'>" . ($stats['total_downloads'] ?? 0) . "</div>
                    <div class='stat-label'>Total Downloads</div>
                </div>
            </div>
            
            <div class='channel-section'>
                <h2 style='color: #764ba2; border-bottom: 2px solid #764ba2; padding-bottom: 10px;'>📢 Channel Status</h2>";
    
    global $channels;
    foreach ($channels as $type => $channel) {
        if ($type != 'backup1' && $type != 'backup2') {
            $movies = load_channel_csv($channel['csv']);
            $movie_count = count($movies);
            
            echo "<div class='channel-card'>
                    <div class='channel-name'>" . htmlspecialchars($channel['username']) . "</div>
                    <div class='channel-info'>
                        Movies: <strong>$movie_count</strong> | 
                        File: <code>" . basename($channel['csv']) . "</code>
                    </div>
                  </div>";
        }
    }
    
    echo "</div>
          <h2 style='color: #764ba2;'>✨ Core Features</h2>
          <div class='feature-list'>
            <div class='feature-item'>✅ 5 Separate movies.csv files</div>
            <div class='feature-item'>✅ Header-less movie delivery</div>
            <div class='feature-item'>✅ Smart cross-channel search</div>
            <div class='feature-item'>✅ Paginated browsing (/browse)</div>
            <div class='feature-item'>✅ Movie request system</div>
            <div class='feature-item'>✅ Daily auto-backup</div>
            <div class='feature-item'>✅ Admin controls</div>
            <div class='feature-item'>✅ Real-time statistics</div>
          </div>
          
          <div style='text-align: center; margin-top: 30px;'>
            <a href='?setwebhook=1' class='btn'>🔄 Set Webhook</a>
            <a href='https://t.me/EntertainmentTadka786' class='btn'>🎬 Main Channel</a>
            <a href='https://t.me/threater_print_movies' class='btn'>🎭 Theater Prints</a>
          </div>
          
          <div style='margin-top: 30px; padding: 20px; background: #f0f0f0; border-radius: 10px;'>
            <p style='margin: 0; color: #666; font-size: 0.9em;'>
              <strong>Last Updated:</strong> " . ($stats['last_updated'] ?? 'N/A') . " | 
              <strong>Server Time:</strong> " . date('Y-m-d H:i:s') . "
            </p>
          </div>
        </div>
    </body>
    </html>";
}
?>
