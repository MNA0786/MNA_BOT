<?php
// ==============================
// SECURITY HEADERS & BASIC SETUP
// ==============================

// Security headers PHP mein set karo - XSS aur security attacks se bachne ke liye
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==============================
// RENDER.COM SPECIFIC CONFIGURATION
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
define('ADMIN_ID', (int)getenv('ADMIN_ID'));

// Validate essential environment variables
if (!BOT_TOKEN || !ADMIN_ID) {
    die("❌ Essential environment variables set nahi hain. Render.com dashboard mein set karo.");
}

// ALL CHANNELS DEFINED (6 Channels + 1 Group)
define('CHANNEL_1_ID', '-1003181705395');
define('CHANNEL_2_ID', '-1002964109368');
define('CHANNEL_3_ID', '-1002831605258');
define('CHANNEL_4_ID', '-1002337293281');
define('CHANNEL_5_ID', '-1003251791991');
define('CHANNEL_6_ID', '-1003614546520');
define('GROUP_ID', '-1003083386043');

// Usernames for display
define('CHANNEL_1_USERNAME', '@EntertainmentTadka786');
define('CHANNEL_2_USERNAME', '@ETBackup');
define('CHANNEL_3_USERNAME', '@threater_print_movies');
define('CHANNEL_4_USERNAME', '@BackupChannel2');
define('CHANNEL_5_USERNAME', '@PrivateChannel');
define('CHANNEL_6_USERNAME', '@AnyForwardedChannel');
define('GROUP_USERNAME', '@EntertainmentTadka7860');

// Default main channel
define('CHANNEL_ID', CHANNEL_1_ID);
define('MAIN_CHANNEL', CHANNEL_1_USERNAME);
define('BACKUP_CHANNEL_ID', CHANNEL_2_ID);
define('BACKUP_CHANNEL_USERNAME', CHANNEL_2_USERNAME);
define('THEATER_CHANNEL_ID', CHANNEL_3_ID);
define('THEATER_CHANNEL', CHANNEL_3_USERNAME);
define('REQUEST_CHANNEL', GROUP_USERNAME);

// ==============================
// DATABASE CONFIGURATION (SQLite)
// ==============================
define('DB_FILE', 'movies.db');
define('DB_BACKUP_DIR', 'db_backups/');
define('LOG_FILE', 'bot_activity.log');

// Constants - Bot ke settings
define('CACHE_EXPIRY', 300);
define('ITEMS_PER_PAGE', 5);
define('MAX_SEARCH_RESULTS', 15);
define('DAILY_REQUEST_LIMIT', 5);
define('AUTO_BACKUP_HOUR', '03');

// ==============================
// DELAY TYPING FEATURE - OPTIMIZED
// ==============================
define('TYPING_DELAY_ENABLED', true);
define('TYPING_DELAY_MIN', 0.5);
define('TYPING_DELAY_MAX', 1.5);

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
// GLOBAL VARIABLES
// ==============================
$movie_cache = [];
$waiting_users = [];
$user_sessions = [];
$user_pagination_sessions = [];
$admin_commands_state = [];
$update = null;
$db = null;

// ==============================
// INITIALIZE SYSTEM
// ==============================
initialize_system();

// ==============================
// CORE FUNCTIONS
// ==============================

function initialize_system() {
    // Create necessary directories
    if (!file_exists(DB_BACKUP_DIR)) {
        @mkdir(DB_BACKUP_DIR, 0755, true);
    }
    
    // Create log file
    if (!file_exists(LOG_FILE)) {
        file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] SYSTEM: Initialized\n");
    }
    
    // Initialize database
    init_database();
}

function init_database() {
    global $db;
    
    try {
        $db = new SQLite3(DB_FILE);
        $db->busyTimeout(5000);
        
        // Enable WAL mode
        $db->exec("PRAGMA journal_mode = WAL");
        $db->exec("PRAGMA synchronous = NORMAL");
        $db->exec("PRAGMA foreign_keys = ON");
        
        // Movies table
        $db->exec("CREATE TABLE IF NOT EXISTS movies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            movie_name TEXT NOT NULL,
            message_id INTEGER NOT NULL,
            channel_id TEXT NOT NULL,
            channel_username TEXT,
            channel_type TEXT,
            added_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(movie_name, message_id, channel_id)
        )");
        
        // Full-text search table
        $db->exec("CREATE VIRTUAL TABLE IF NOT EXISTS movies_fts USING fts5(
            movie_name,
            content='movies',
            content_rowid='id'
        )");
        
        // Triggers for FTS
        $db->exec("CREATE TRIGGER IF NOT EXISTS movies_ai AFTER INSERT ON movies BEGIN
            INSERT INTO movies_fts(rowid, movie_name) VALUES (new.id, new.movie_name);
        END");
        
        $db->exec("CREATE TRIGGER IF NOT EXISTS movies_ad AFTER DELETE ON movies BEGIN
            INSERT INTO movies_fts(movies_fts, rowid, movie_name) VALUES('delete', old.id, old.movie_name);
        END");
        
        $db->exec("CREATE TRIGGER IF NOT EXISTS movies_au AFTER UPDATE ON movies BEGIN
            INSERT INTO movies_fts(movies_fts, rowid, movie_name) VALUES('delete', old.id, old.movie_name);
            INSERT INTO movies_fts(rowid, movie_name) VALUES (new.id, new.movie_name);
        END");
        
        // Users table
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY,
            first_name TEXT,
            last_name TEXT,
            username TEXT,
            joined_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_active DATETIME DEFAULT CURRENT_TIMESTAMP,
            total_searches INTEGER DEFAULT 0,
            total_downloads INTEGER DEFAULT 0,
            points INTEGER DEFAULT 0,
            request_count INTEGER DEFAULT 0,
            last_request_date DATE
        )");
        
        // Movie requests table
        $db->exec("CREATE TABLE IF NOT EXISTS movie_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            movie_name TEXT NOT NULL,
            language TEXT DEFAULT 'hindi',
            request_date DATE DEFAULT CURRENT_DATE,
            request_time TIME DEFAULT CURRENT_TIME,
            status TEXT DEFAULT 'pending',
            FOREIGN KEY(user_id) REFERENCES users(user_id)
        )");
        
        // Statistics table
        $db->exec("CREATE TABLE IF NOT EXISTS statistics (
            stat_key TEXT PRIMARY KEY,
            stat_value INTEGER DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Initial statistics
        $db->exec("INSERT OR IGNORE INTO statistics (stat_key, stat_value) VALUES 
            ('total_movies', 0),
            ('total_users', 0),
            ('total_searches', 0),
            ('total_downloads', 0),
            ('successful_searches', 0),
            ('failed_searches', 0)");
        
        bot_log("Database initialized");
        
        // Migrate CSV if exists
        migrate_csv_to_db();
        
    } catch (Exception $e) {
        bot_log("Database init failed: " . $e->getMessage(), 'ERROR');
        die("❌ Database error: " . $e->getMessage());
    }
}

function migrate_csv_to_db() {
    global $db;
    
    $csv_file = 'movies.csv';
    if (!file_exists($csv_file)) {
        return;
    }
    
    $count = $db->querySingle("SELECT COUNT(*) FROM movies");
    if ($count > 0) {
        bot_log("Database already has data, skipping migration");
        return;
    }
    
    bot_log("Starting CSV migration...");
    
    $handle = fopen($csv_file, "r");
    if ($handle === FALSE) return;
    
    fgetcsv($handle); // Skip header
    
    $inserted = 0;
    $skipped = 0;
    $db->exec("BEGIN TRANSACTION");
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) < 3) {
            $skipped++;
            continue;
        }
        
        $movie_name = trim($row[0]);
        $message_id = trim($row[1]);
        $channel_info = trim($row[2]);
        
        if (empty($movie_name) || !is_numeric($message_id)) {
            $skipped++;
            continue;
        }
        
        $channel_id = '';
        $channel_username = '';
        
        if (strpos($channel_info, '@') === 0) {
            $channel_username = $channel_info;
            global $CHANNEL_MAP;
            foreach ($CHANNEL_MAP as $id => $uname) {
                if ($uname == $channel_username) {
                    $channel_id = $id;
                    break;
                }
            }
        } elseif (is_numeric($channel_info) || strpos($channel_info, '-100') === 0) {
            $channel_id = $channel_info;
            $channel_username = get_username_from_channel_id($channel_id);
        }
        
        if (empty($channel_id)) {
            $channel_id = CHANNEL_1_ID;
            $channel_username = CHANNEL_1_USERNAME;
        }
        
        $channel_type = get_channel_type($channel_id);
        
        try {
            $stmt = $db->prepare("INSERT OR IGNORE INTO movies 
                (movie_name, message_id, channel_id, channel_username, channel_type) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->bindValue(1, $movie_name, SQLITE3_TEXT);
            $stmt->bindValue(2, intval($message_id), SQLITE3_INTEGER);
            $stmt->bindValue(3, $channel_id, SQLITE3_TEXT);
            $stmt->bindValue(4, $channel_username, SQLITE3_TEXT);
            $stmt->bindValue(5, $channel_type, SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                $inserted++;
            } else {
                $skipped++;
            }
        } catch (Exception $e) {
            $skipped++;
        }
    }
    
    fclose($handle);
    $db->exec("COMMIT");
    
    // Update stats
    $db->exec("UPDATE statistics SET stat_value = (SELECT COUNT(*) FROM movies) WHERE stat_key = 'total_movies'");
    
    bot_log("Migration complete: $inserted inserted, $skipped skipped");
    
    // Backup CSV
    rename($csv_file, $csv_file . '.backup_' . date('Y-m-d'));
}

// ==============================
// DELAY TYPING FUNCTION
// ==============================
function sendTypingAction($chat_id, $action = 'typing') {
    if (TYPING_DELAY_ENABLED) {
        $delay = rand(TYPING_DELAY_MIN * 1000000, TYPING_DELAY_MAX * 1000000);
        
        // Async typing action
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/sendChatAction");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $chat_id,
            'action' => $action
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        
        curl_exec($ch);
        curl_close($ch);
        
        usleep($delay);
        return $delay;
    }
    return 0;
}

// ==============================
// LOGGING
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
    
    sendTypingAction($chat_id);
    
    $result = apiRequest('sendMessage', $data);
    bot_log("Message sent to $chat_id");
    return json_decode($result, true);
}

function editMessage($chat_id, $message_id, $text, $reply_markup = null, $parse_mode = null) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    
    $result = apiRequest('editMessageText', $data);
    return json_decode($result, true);
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
// HELPER FUNCTIONS
// ==============================
function get_username_from_channel_id($channel_id) {
    global $CHANNEL_MAP;
    return $CHANNEL_MAP[$channel_id] ?? '';
}

function get_channel_type($channel_id) {
    global $CHANNEL_TYPE_MAP;
    return $CHANNEL_TYPE_MAP[$channel_id] ?? 'main';
}

function get_channel_id_from_username($username) {
    global $CHANNEL_MAP;
    foreach ($CHANNEL_MAP as $id => $uname) {
        if ($uname == $username) {
            return $id;
        }
    }
    return CHANNEL_1_ID;
}

function get_channel_icon($channel_type) {
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
// DATABASE FUNCTIONS
// ==============================
function db_insert_movie($movie_name, $message_id, $channel_id, $channel_username = null, $channel_type = null) {
    global $db;
    
    if ($channel_username === null) {
        $channel_username = get_username_from_channel_id($channel_id);
    }
    
    if ($channel_type === null) {
        $channel_type = get_channel_type($channel_id);
    }
    
    try {
        $stmt = $db->prepare("INSERT OR IGNORE INTO movies 
            (movie_name, message_id, channel_id, channel_username, channel_type) 
            VALUES (?, ?, ?, ?, ?)");
        $stmt->bindValue(1, trim($movie_name), SQLITE3_TEXT);
        $stmt->bindValue(2, intval($message_id), SQLITE3_INTEGER);
        $stmt->bindValue(3, $channel_id, SQLITE3_TEXT);
        $stmt->bindValue(4, $channel_username, SQLITE3_TEXT);
        $stmt->bindValue(5, $channel_type, SQLITE3_TEXT);
        
        $result = $stmt->execute();
        
        if ($result) {
            $db->exec("UPDATE statistics SET stat_value = stat_value + 1 WHERE stat_key = 'total_movies'");
            return true;
        }
    } catch (Exception $e) {
        bot_log("Error inserting movie: " . $e->getMessage(), 'ERROR');
    }
    
    return false;
}

function db_search_movies($query, $limit = MAX_SEARCH_RESULTS) {
    global $db;
    
    $query = strtolower(trim($query));
    $results = [];
    
    // FTS search
    $stmt = $db->prepare("
        SELECT m.* 
        FROM movies_fts f 
        JOIN movies m ON f.rowid = m.id 
        WHERE movies_fts MATCH ? 
        ORDER BY rank 
        LIMIT ?
    ");
    $stmt->bindValue(1, $query . '*', SQLITE3_TEXT);
    $stmt->bindValue(2, $limit, SQLITE3_INTEGER);
    
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $results[] = $row;
    }
    
    // LIKE search if FTS returns nothing
    if (empty($results)) {
        $stmt = $db->prepare("
            SELECT * FROM movies 
            WHERE LOWER(movie_name) LIKE ? 
            LIMIT ?
        ");
        $stmt->bindValue(1, '%' . $query . '%', SQLITE3_TEXT);
        $stmt->bindValue(2, $limit, SQLITE3_INTEGER);
        
        $result = $stmt->execute();
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $results[] = $row;
        }
    }
    
    return $results;
}

function db_get_all_movies($page = 1, $limit = ITEMS_PER_PAGE) {
    global $db;
    
    $offset = ($page - 1) * $limit;
    
    $stmt = $db->prepare("
        SELECT * FROM movies 
        ORDER BY added_date DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, SQLITE3_INTEGER);
    $stmt->bindValue(2, $offset, SQLITE3_INTEGER);
    
    $result = $stmt->execute();
    $movies = [];
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $movies[] = $row;
    }
    
    $total = $db->querySingle("SELECT COUNT(*) FROM movies");
    
    return [
        'movies' => $movies,
        'total' => $total,
        'page' => $page,
        'total_pages' => ceil($total / $limit)
    ];
}

// ==============================
// ADMIN MOVIE COMMANDS
// ==============================
function handle_admin_movie_commands($chat_id, $user_id, $command, $params) {
    global $admin_commands_state, $db;
    
    if ($user_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    switch($command) {
        case '/quickadd':
            if (count($params) < 2) {
                sendMessage($chat_id, 
                    "📝 <b>Quick Add Format:</b>\n\n" .
                    "<code>/quickadd MovieName MessageID</code>\n\n" .
                    "📌 <b>Examples:</b>\n" .
                    "<code>/quickadd AvengersEndgame 251</code>\n" .
                    "<code>/quickadd KGF2 5678</code>\n" .
                    "<code>/quickadd \"Squid Game\" 999</code>\n\n" .
                    "💡 <b>Note:</b> Quotes for multi-word names",
                    null, 'HTML'
                );
                return;
            }
            
            $movie_name = $params[0];
            $message_id = $params[1];
            
            // Remove quotes
            if ((strpos($movie_name, '"') === 0 && substr($movie_name, -1) === '"') ||
                (strpos($movie_name, "'") === 0 && substr($movie_name, -1) === "'")) {
                $movie_name = trim($movie_name, '"\'');
            }
            
            if (!is_numeric($message_id)) {
                sendMessage($chat_id, "❌ Message ID must be numeric.");
                return;
            }
            
            // Check if exists
            $stmt = $db->prepare("SELECT COUNT(*) FROM movies WHERE message_id = ? AND channel_id = ?");
            $stmt->bindValue(1, intval($message_id), SQLITE3_INTEGER);
            $stmt->bindValue(2, CHANNEL_1_ID, SQLITE3_TEXT);
            $exists = $stmt->execute()->fetchArray(SQLITE3_NUM)[0];
            
            if ($exists > 0) {
                sendMessage($chat_id, "❌ Movie with this Message ID already exists!");
                return;
            }
            
            // Add to database
            if (db_insert_movie($movie_name, $message_id, CHANNEL_1_ID)) {
                $total_movies = $db->querySingle("SELECT COUNT(*) FROM movies");
                
                $response = "✅ <b>Movie Added Successfully!</b>\n\n";
                $response .= "🎬 <b>Movie:</b> $movie_name\n";
                $response .= "🆔 <b>Message ID:</b> $message_id\n";
                $response .= "📢 <b>Channel:</b> " . CHANNEL_1_USERNAME . "\n";
                $response .= "🔗 <b>Direct Link:</b> https://t.me/c/" . str_replace('-100', '', CHANNEL_1_ID) . "/$message_id\n\n";
                $response .= "📊 <b>Total Movies:</b> $total_movies\n\n";
                $response .= "🔍 <b>Test Search:</b> Send \"$movie_name\" to bot";
                
                sendMessage($chat_id, $response, null, 'HTML');
                bot_log("Quick add by admin $user_id: $movie_name ($message_id)");
            } else {
                sendMessage($chat_id, "❌ Error adding movie.");
            }
            break;
            
        case '/listmovies':
            $page = isset($params[0]) ? intval($params[0]) : 1;
            $limit = 10;
            $offset = ($page - 1) * $limit;
            
            $stmt = $db->prepare("
                SELECT * FROM movies 
                ORDER BY added_date DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->bindValue(1, $limit, SQLITE3_INTEGER);
            $stmt->bindValue(2, $offset, SQLITE3_INTEGER);
            
            $result = $stmt->execute();
            $movies = [];
            
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $movies[] = $row;
            }
            
            $total = $db->querySingle("SELECT COUNT(*) FROM movies");
            $total_pages = ceil($total / $limit);
            
            if (empty($movies)) {
                sendMessage($chat_id, "📭 No movies found in database.");
                return;
            }
            
            $message = "📋 <b>Movies List (Page $page of $total_pages)</b>\n\n";
            $message .= "📊 Total Movies: <b>$total</b>\n\n";
            
            $counter = $offset + 1;
            foreach ($movies as $movie) {
                $channel_icon = get_channel_icon($movie['channel_type']);
                $date = date('d-m-Y', strtotime($movie['added_date']));
                
                $message .= "<b>$counter.</b> $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
                $message .= "   🆔 {$movie['message_id']} | 📢 {$movie['channel_username']}\n";
                $message .= "   📅 $date | 🏷️ " . ucfirst($movie['channel_type']) . "\n\n";
                
                $counter++;
            }
            
            $keyboard = ['inline_keyboard' => []];
            $buttons_row = [];
            
            if ($page > 1) {
                $buttons_row[] = ['text' => '⬅️ Previous', 'callback_data' => 'list_page_' . ($page-1)];
            }
            
            if ($page < $total_pages) {
                $buttons_row[] = ['text' => 'Next ➡️', 'callback_data' => 'list_page_' . ($page+1)];
            }
            
            if (!empty($buttons_row)) {
                $keyboard['inline_keyboard'][] = $buttons_row;
            }
            
            $action_row = [];
            $action_row[] = ['text' => '🔍 Search', 'callback_data' => 'admin_search_menu'];
            $action_row[] = ['text' => '➕ Add More', 'callback_data' => 'admin_add_menu'];
            $action_row[] = ['text' => '📊 Stats', 'callback_data' => 'admin_stats'];
            
            $keyboard['inline_keyboard'][] = $action_row;
            
            sendMessage($chat_id, $message, $keyboard, 'HTML');
            break;
            
        case '/findmovie':
        case '/searchmovie':
            if (empty($params)) {
                sendMessage($chat_id, 
                    "🔍 <b>Find Movie Format:</b>\n\n" .
                    "<code>/findmovie movie_name</code>\n\n" .
                    "📌 <b>Examples:</b>\n" .
                    "<code>/findmovie avengers</code>\n" .
                    "<code>/findmovie \"squid game\"</code>\n" .
                    "<code>/findmovie kgf</code>",
                    null, 'HTML'
                );
                return;
            }
            
            $search_query = implode(' ', $params);
            $results = db_search_movies($search_query, 15);
            
            if (empty($results)) {
                sendMessage($chat_id, 
                    "❌ No movies found for <b>'$search_query'</b>\n\n" .
                    "💡 Try:\n" .
                    "• Different spelling\n" .
                    "• Partial name\n" .
                    "• Without special characters",
                    null, 'HTML'
                );
                return;
            }
            
            $message = "🔍 <b>Search Results for '$search_query'</b>\n\n";
            $message .= "📊 Found: <b>" . count($results) . " movies</b>\n\n";
            
            $i = 1;
            foreach ($results as $movie) {
                $channel_icon = get_channel_icon($movie['channel_type']);
                $date = date('d-m-Y', strtotime($movie['added_date']));
                
                $message .= "<b>$i.</b> $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
                $message .= "   🆔 {$movie['message_id']} | 📢 {$movie['channel_username']}\n";
                $message .= "   📅 $date\n\n";
                
                $i++;
                if ($i > 10) break;
            }
            
            if (count($results) > 10) {
                $message .= "... and " . (count($results) - 10) . " more results\n\n";
            }
            
            $keyboard = ['inline_keyboard' => []];
            
            foreach (array_slice($results, 0, 5) as $movie) {
                $channel_icon = get_channel_icon($movie['channel_type']);
                $keyboard['inline_keyboard'][] = [[
                    'text' => "$channel_icon " . htmlspecialchars($movie['movie_name']),
                    'callback_data' => 'admin_view_' . $movie['id']
                ]];
            }
            
            if (count($results) == 1) {
                $movie = $results[0];
                $keyboard['inline_keyboard'][] = [[
                    'text' => '🗑️ Delete This Movie',
                    'callback_data' => 'admin_delete_' . $movie['id']
                ]];
            }
            
            sendMessage($chat_id, $message, $keyboard, 'HTML');
            break;
            
        case '/deletemovie':
        case '/removemovie':
            if (empty($params)) {
                sendMessage($chat_id, 
                    "🗑️ <b>Delete Movie Format:</b>\n\n" .
                    "<code>/deletemovie movie_name</code>\n\n" .
                    "📌 <b>Examples:</b>\n" .
                    "<code>/deletemovie avengers</code>\n" .
                    "<code>/deletemovie \"squid game\"</code>\n\n" .
                    "⚠️ <b>Warning:</b> This action cannot be undone!",
                    null, 'HTML'
                );
                return;
            }
            
            $movie_name = implode(' ', $params);
            
            $stmt = $db->prepare("
                SELECT * FROM movies 
                WHERE LOWER(movie_name) LIKE LOWER(?)
                LIMIT 5
            ");
            $stmt->bindValue(1, "%$movie_name%", SQLITE3_TEXT);
            
            $result = $stmt->execute();
            $matches = [];
            
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $matches[] = $row;
            }
            
            if (empty($matches)) {
                sendMessage($chat_id, "❌ No movies found matching '$movie_name'");
                return;
            }
            
            if (count($matches) == 1) {
                $movie = $matches[0];
                $admin_commands_state[$user_id] = [
                    'action' => 'confirm_delete',
                    'movie_id' => $movie['id']
                ];
                
                $message = "⚠️ <b>Confirm Deletion</b>\n\n";
                $message .= "🎬 <b>Movie:</b> " . htmlspecialchars($movie['movie_name']) . "\n";
                $message .= "🆔 <b>Message ID:</b> {$movie['message_id']}\n";
                $message .= "📢 <b>Channel:</b> {$movie['channel_username']}\n";
                $message .= "📅 <b>Added:</b> " . date('d-m-Y', strtotime($movie['added_date'])) . "\n\n";
                $message .= "❌ <b>This action cannot be undone!</b>\n\n";
                $message .= "Type <code>YES</code> to confirm deletion:";
                
                sendMessage($chat_id, $message, null, 'HTML');
            } else {
                $message = "🔍 <b>Multiple Movies Found</b>\n\n";
                $message .= "Please select which movie to delete:\n\n";
                
                $keyboard = ['inline_keyboard' => []];
                
                foreach ($matches as $index => $movie) {
                    $channel_icon = get_channel_icon($movie['channel_type']);
                    $keyboard['inline_keyboard'][] = [[
                        'text' => ($index+1) . ". $channel_icon " . htmlspecialchars($movie['movie_name']),
                        'callback_data' => 'admin_delete_' . $movie['id']
                    ]];
                }
                
                $keyboard['inline_keyboard'][] = [[
                    'text' => '❌ Cancel',
                    'callback_data' => 'admin_cancel'
                ]];
                
                sendMessage($chat_id, $message, $keyboard, 'HTML');
            }
            break;
            
        case '/adminstats':
        case '/botstats':
            $total_movies = $db->querySingle("SELECT COUNT(*) FROM movies");
            $total_users = $db->querySingle("SELECT COUNT(*) FROM users");
            $total_searches = $db->querySingle("SELECT stat_value FROM statistics WHERE stat_key = 'total_searches'");
            $total_downloads = $db->querySingle("SELECT stat_value FROM statistics WHERE stat_key = 'total_downloads'");
            
            $recent_movies = $db->querySingle("
                SELECT COUNT(*) FROM movies 
                WHERE date(added_date) = date('now')
            ");
            
            $message = "📊 <b>Bot Statistics</b>\n\n";
            $message .= "🎬 <b>Total Movies:</b> $total_movies\n";
            $message .= "👥 <b>Total Users:</b> $total_users\n";
            $message .= "🔍 <b>Total Searches:</b> $total_searches\n";
            $message .= "📥 <b>Total Downloads:</b> $total_downloads\n\n";
            $message .= "📈 <b>Today's Activity:</b>\n";
            $message .= "• Movies Added: $recent_movies\n\n";
            
            $message .= "📢 <b>Channel Distribution:</b>\n";
            $channels = ['main', 'theater', 'backup', 'backup2', 'private', 'any', 'group'];
            
            foreach ($channels as $channel_type) {
                $count = $db->querySingle("
                    SELECT COUNT(*) FROM movies 
                    WHERE channel_type = '$channel_type'
                ");
                if ($count > 0) {
                    $icon = get_channel_icon($channel_type);
                    $message .= "• $icon " . ucfirst($channel_type) . ": $count movies\n";
                }
            }
            
            $db_size = file_exists('movies.db') ? round(filesize('movies.db') / 1024, 2) : 0;
            $message .= "\n💾 <b>Database Size:</b> $db_size KB";
            
            sendMessage($chat_id, $message, null, 'HTML');
            break;
            
        case '/adminhelp':
        case '/moviehelp':
            $help = "🛠️ <b>Movie Management Commands</b>\n\n";
            
            $help .= "➕ <b>Add Movies:</b>\n";
            $help .= "<code>/quickadd MovieName MessageID</code>\n";
            $help .= "Example: <code>/quickadd AvengersEndgame 251</code>\n\n";
            
            $help .= "📋 <b>View Movies:</b>\n";
            $help .= "<code>/listmovies</code> - All movies\n";
            $help .= "<code>/listmovies 2</code> - Page 2\n\n";
            
            $help .= "🔍 <b>Search Movies:</b>\n";
            $help .= "<code>/findmovie name</code>\n";
            $help .= "Example: <code>/findmovie avengers</code>\n\n";
            
            $help .= "🗑️ <b>Delete Movies:</b>\n";
            $help .= "<code>/deletemovie name</code>\n";
            $help .= "Example: <code>/deletemovie test</code>\n\n";
            
            $help .= "📊 <b>Statistics:</b>\n";
            $help .= "<code>/adminstats</code> - Bot statistics\n\n";
            
            $help .= "💡 <b>Pro Tips:</b>\n";
            $help .= "• Use quotes for multi-word names\n";
            $help .= "• Message ID must be numeric\n";
            $help .= "• All movies go to main channel";
            
            sendMessage($chat_id, $help, null, 'HTML');
            break;
    }
}

function handle_admin_text_response($chat_id, $user_id, $text) {
    global $admin_commands_state, $db;
    
    if (!isset($admin_commands_state[$user_id])) {
        return false;
    }
    
    $state = $admin_commands_state[$user_id];
    
    if ($state['action'] == 'confirm_delete' && strtoupper($text) == 'YES') {
        $movie_id = $state['movie_id'];
        
        $stmt = $db->prepare("SELECT * FROM movies WHERE id = ?");
        $stmt->bindValue(1, $movie_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $movie = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($movie) {
            $stmt = $db->prepare("DELETE FROM movies WHERE id = ?");
            $stmt->bindValue(1, $movie_id, SQLITE3_INTEGER);
            $stmt->execute();
            
            $message = "✅ <b>Movie Deleted Successfully!</b>\n\n";
            $message .= "🎬 <b>Movie:</b> " . htmlspecialchars($movie['movie_name']) . "\n";
            $message .= "🆔 <b>Message ID:</b> {$movie['message_id']}\n";
            $message .= "📢 <b>Channel:</b> {$movie['channel_username']}\n\n";
            $message .= "🗑️ Movie has been removed from database.";
            
            bot_log("Movie deleted by admin $user_id: {$movie['movie_name']} ({$movie['message_id']})");
        } else {
            $message = "❌ Movie not found or already deleted.";
        }
        
        unset($admin_commands_state[$user_id]);
        sendMessage($chat_id, $message, null, 'HTML');
        return true;
    }
    
    return false;
}

function handle_admin_callback_query($callback_query) {
    global $db;
    
    $message = $callback_query['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $callback_query['from']['id'];
    $data = $callback_query['data'];
    $callback_id = $callback_query['id'];
    
    if ($user_id != ADMIN_ID) {
        answerCallbackQuery($callback_id, "❌ Admin only feature!", true);
        return;
    }
    
    if (strpos($data, 'list_page_') === 0) {
        $page = intval(str_replace('list_page_', '', $data));
        $params = [$page];
        handle_admin_movie_commands($chat_id, $user_id, '/listmovies', $params);
        answerCallbackQuery($callback_id, "Page $page");
        return;
    }
    
    if (strpos($data, 'admin_view_') === 0) {
        $movie_id = intval(str_replace('admin_view_', '', $data));
        
        $stmt = $db->prepare("SELECT * FROM movies WHERE id = ?");
        $stmt->bindValue(1, $movie_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $movie = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($movie) {
            $message = "🎬 <b>Movie Details</b>\n\n";
            $message .= "📝 <b>Name:</b> " . htmlspecialchars($movie['movie_name']) . "\n";
            $message .= "🆔 <b>Message ID:</b> {$movie['message_id']}\n";
            $message .= "📢 <b>Channel:</b> {$movie['channel_username']}\n";
            $message .= "🏷️ <b>Type:</b> " . ucfirst($movie['channel_type']) . "\n";
            $message .= "📅 <b>Added:</b> " . date('d-m-Y H:i', strtotime($movie['added_date'])) . "\n\n";
            
            $channel_id_clean = str_replace('-100', '', $movie['channel_id']);
            $message .= "🔗 <b>Direct Link:</b>\n";
            $message .= "https://t.me/c/$channel_id_clean/{$movie['message_id']}";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🗑️ Delete Movie', 'callback_data' => 'admin_delete_' . $movie['id']],
                        ['text' => '✏️ Edit Movie', 'callback_data' => 'admin_edit_' . $movie['id']]
                    ],
                    [
                        ['text' => '📋 Back to List', 'callback_data' => 'list_page_1']
                    ]
                ]
            ];
            
            editMessage($chat_id, $message['message_id'], $message, $keyboard, 'HTML');
        }
        
        answerCallbackQuery($callback_id, "Movie details");
        return;
    }
    
    if (strpos($data, 'admin_delete_') === 0) {
        $movie_id = intval(str_replace('admin_delete_', '', $data));
        
        $stmt = $db->prepare("SELECT * FROM movies WHERE id = ?");
        $stmt->bindValue(1, $movie_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $movie = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($movie) {
            $confirm_message = "⚠️ <b>Confirm Deletion</b>\n\n";
            $confirm_message .= "🎬 <b>Movie:</b> " . htmlspecialchars($movie['movie_name']) . "\n";
            $confirm_message .= "🆔 <b>Message ID:</b> {$movie['message_id']}\n";
            $confirm_message .= "📢 <b>Channel:</b> {$movie['channel_username']}\n\n";
            $confirm_message .= "❌ <b>This action cannot be undone!</b>";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ YES, Delete', 'callback_data' => 'admin_confirm_delete_' . $movie_id],
                        ['text' => '❌ NO, Cancel', 'callback_data' => 'admin_cancel_delete']
                    ]
                ]
            ];
            
            editMessage($chat_id, $message['message_id'], $confirm_message, $keyboard, 'HTML');
        }
        
        answerCallbackQuery($callback_id, "Confirm deletion");
        return;
    }
    
    if (strpos($data, 'admin_confirm_delete_') === 0) {
        $movie_id = intval(str_replace('admin_confirm_delete_', '', $data));
        
        $stmt = $db->prepare("SELECT * FROM movies WHERE id = ?");
        $stmt->bindValue(1, $movie_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $movie = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($movie) {
            $stmt = $db->prepare("DELETE FROM movies WHERE id = ?");
            $stmt->bindValue(1, $movie_id, SQLITE3_INTEGER);
            $stmt->execute();
            
            $success_message = "✅ <b>Movie Deleted!</b>\n\n";
            $success_message .= "🎬 " . htmlspecialchars($movie['movie_name']) . "\n";
            $success_message .= "🆔 ID: {$movie['message_id']}\n";
            $success_message .= "📢 Channel: {$movie['channel_username']}\n\n";
            $success_message .= "🗑️ Removed from database.";
            
            bot_log("Movie deleted via callback by admin $user_id: {$movie['movie_name']}");
            
            editMessage($chat_id, $message['message_id'], $success_message);
        } else {
            editMessage($chat_id, $message['message_id'], "❌ Movie not found or already deleted.");
        }
        
        answerCallbackQuery($callback_id, "Movie deleted");
        return;
    }
    
    if ($data == 'admin_cancel' || $data == 'admin_cancel_delete') {
        editMessage($chat_id, $message['message_id'], "❌ Operation cancelled.");
        answerCallbackQuery($callback_id, "Cancelled");
        return;
    }
}

// ==============================
// SEARCH FUNCTION
// ==============================
function detect_language($text) {
    $hindi_chars = preg_match('/[\x{0900}-\x{097F}]/u', $text);
    return $hindi_chars ? 'hindi' : 'english';
}

function send_multilingual_response($chat_id, $message_type, $language) {
    $responses = [
        'hindi' => [
            'searching' => "🔍 Dhoondh raha hoon... Zara wait karo",
            'not_found' => "😔 Yeh movie abhi available nahi hai!\n\n📝 Aap ise request kar sakte hain!\n\n🔔 Jab bhi yeh add hogi, main automatically bhej dunga!"
        ],
        'english' => [
            'searching' => "🔍 Searching... Please wait",
            'not_found' => "😔 This movie isn't available yet!\n\n📝 You can request it!\n\n🔔 I'll send it automatically once it's added!"
        ]
    ];
    
    sendMessage($chat_id, $responses[$language][$message_type]);
}

function advanced_search($chat_id, $query, $user_id = null) {
    global $db;
    
    $q = strtolower(trim($query));
    
    if (strlen($q) < 2) {
        sendMessage($chat_id, "❌ Please enter at least 2 characters for search");
        return;
    }
    
    $results = db_search_movies($q, MAX_SEARCH_RESULTS);
    
    if (!empty($results)) {
        $db->exec("UPDATE statistics SET stat_value = stat_value + 1 WHERE stat_key = 'successful_searches'");
        $db->exec("UPDATE statistics SET stat_value = stat_value + 1 WHERE stat_key = 'total_searches'");
        
        $msg = "🔍 Found " . count($results) . " movies for '$query':\n\n";
        $i = 1;
        foreach ($results as $movie) {
            $channel_icon = get_channel_icon($movie['channel_type']);
            $msg .= "$i. {$movie['movie_name']} ($channel_icon)\n";
            $i++;
            if ($i > 10) break;
        }
        
        sendMessage($chat_id, $msg);
        
        $keyboard = ['inline_keyboard' => []];
        $top_movies = array_slice($results, 0, 5);
        
        foreach ($top_movies as $movie) {
            $channel_icon = get_channel_icon($movie['channel_type']);
            $keyboard['inline_keyboard'][] = [[ 
                'text' => $channel_icon . ' ' . $movie['movie_name'], 
                'callback_data' => 'movie_' . $movie['id']
            ]];
        }
        
        $keyboard['inline_keyboard'][] = [[
            'text' => "📝 Request Different Movie", 
            'callback_data' => 'request_movie'
        ]];
        
        sendMessage($chat_id, "🚀 Top matches (click to send):", $keyboard);
        
    } else {
        $db->exec("UPDATE statistics SET stat_value = stat_value + 1 WHERE stat_key = 'failed_searches'");
        $db->exec("UPDATE statistics SET stat_value = stat_value + 1 WHERE stat_key = 'total_searches'");
        
        $lang = detect_language($query);
        send_multilingual_response($chat_id, 'not_found', $lang);
        
        $request_keyboard = [
            'inline_keyboard' => [[
                ['text' => '📝 Request This Movie', 'callback_data' => 'auto_request_' . base64_encode($query)]
            ]]
        ];
        
        sendMessage($chat_id, "💡 Click below to automatically request this movie:", $request_keyboard);
    }
}

// ==============================
// USER MANAGEMENT
// ==============================
function update_user_data($user_id, $user_info = []) {
    global $db;
    
    $stmt = $db->prepare("
        INSERT OR REPLACE INTO users 
        (user_id, first_name, last_name, username, last_active) 
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, $user_info['first_name'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(3, $user_info['last_name'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(4, $user_info['username'] ?? '', SQLITE3_TEXT);
    $stmt->execute();
    
    // Update total users count
    $count = $db->querySingle("SELECT COUNT(*) FROM users");
    $db->exec("UPDATE statistics SET stat_value = $count WHERE stat_key = 'total_users'");
}

// ==============================
// MAIN UPDATE PROCESSING
// ==============================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    bot_log("Update received");
    
    // Message handling
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        $chat_type = $message['chat']['type'] ?? 'private';
        
        // Update user data
        $user_info = [
            'first_name' => $message['from']['first_name'] ?? '',
            'last_name' => $message['from']['last_name'] ?? '',
            'username' => $message['from']['username'] ?? ''
        ];
        update_user_data($user_id, $user_info);
        
        // Check if it's a command
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $params = array_slice($parts, 1);
            
            // Admin movie commands
            $admin_commands = [
                '/quickadd', '/listmovies', '/findmovie', '/searchmovie',
                '/deletemovie', '/removemovie', '/adminstats', '/botstats',
                '/adminhelp', '/moviehelp'
            ];
            
            if (in_array($command, $admin_commands)) {
                handle_admin_movie_commands($chat_id, $user_id, $command, $params);
                exit;
            }
            
            // Regular commands
            switch($command) {
                case '/start':
                    $welcome = "🎬 Welcome to Entertainment Tadka!\n\n";
                    $welcome .= "🔍 <b>Search Movies:</b> Just type movie name\n";
                    $welcome .= "📢 <b>Channels:</b> /channels\n";
                    $welcome .= "❓ <b>Help:</b> /help\n\n";
                    $welcome .= "💬 Type any movie name to search!";
                    
                    sendMessage($chat_id, $welcome, null, 'HTML');
                    break;
                    
                case '/help':
                    $help = "🤖 <b>Bot Commands</b>\n\n";
                    $help .= "🔍 <b>Search:</b> Type movie name\n";
                    $help .= "📢 <b>Channels:</b> /channels\n";
                    $help .= "📝 <b>Request:</b> /request moviename\n";
                    $help .= "📊 <b>Stats:</b> /mystats\n\n";
                    $help .= "💡 <b>Pro Tips:</b>\n";
                    $help .= "• Add 'theater' for theater prints\n";
                    $help .= "• Partial names work\n";
                    $help .= "• Join all channels for updates";
                    
                    sendMessage($chat_id, $help, null, 'HTML');
                    break;
                    
                case '/channels':
                    $channels = "📢 <b>Our Channels</b>\n\n";
                    $channels .= "🍿 Main: " . CHANNEL_1_USERNAME . "\n";
                    $channels .= "🎭 Theater: " . CHANNEL_3_USERNAME . "\n";
                    $channels .= "💾 Backup: " . CHANNEL_2_USERNAME . "\n";
                    $channels .= "👥 Group: " . GROUP_USERNAME . "\n\n";
                    $channels .= "🔔 Join all for latest updates!";
                    
                    sendMessage($chat_id, $channels);
                    break;
                    
                default:
                    // If not a known command, treat as search
                    $search_query = trim(str_replace('/', '', $text));
                    if (!empty($search_query)) {
                        $lang = detect_language($search_query);
                        send_multilingual_response($chat_id, 'searching', $lang);
                        advanced_search($chat_id, $search_query, $user_id);
                    }
            }
        } else if (!empty(trim($text))) {
            // Regular text message - treat as search
            $lang = detect_language($text);
            send_multilingual_response($chat_id, 'searching', $lang);
            advanced_search($chat_id, $text, $user_id);
        }
    }
    
    // Handle callback queries
    if (isset($update['callback_query'])) {
        $callback_query = $update['callback_query'];
        $data = $callback_query['data'];
        
        // First check if it's admin callback
        if (strpos($data, 'admin_') === 0 || strpos($data, 'list_page_') === 0) {
            handle_admin_callback_query($callback_query);
        } else {
            // Handle regular callbacks
            $message = $callback_query['message'];
            $chat_id = $message['chat']['id'];
            $user_id = $callback_query['from']['id'];
            
            if (strpos($data, 'movie_') === 0) {
                $movie_id = intval(str_replace('movie_', '', $data));
                
                global $db;
                $stmt = $db->prepare("SELECT * FROM movies WHERE id = ?");
                $stmt->bindValue(1, $movie_id, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $movie = $result->fetchArray(SQLITE3_ASSOC);
                
                if ($movie) {
                    // Forward the movie
                    forwardMessage($chat_id, $movie['channel_id'], $movie['message_id']);
                    
                    // Update statistics
                    $db->exec("UPDATE statistics SET stat_value = stat_value + 1 WHERE stat_key = 'total_downloads'");
                    
                    sendMessage($chat_id, "✅ Movie sent! Enjoy watching! 🎬");
                    answerCallbackQuery($callback_query['id'], "Movie sent!");
                } else {
                    answerCallbackQuery($callback_query['id'], "Movie not found", true);
                }
            }
        }
    }
    
    // Handle channel posts (auto-add movies)
    if (isset($update['channel_post'])) {
        $post = $update['channel_post'];
        $chat_id = $post['chat']['id'];
        $message_id = $post['message_id'];
        
        $text = '';
        if (isset($post['caption'])) {
            $text = $post['caption'];
        } elseif (isset($post['text'])) {
            $text = $post['text'];
        } elseif (isset($post['document'])) {
            $text = $post['document']['file_name'];
        }
        
        if (!empty(trim($text))) {
            $movie_name = $text;
            $channel_id = strval($chat_id);
            
            // Auto-add to database
            db_insert_movie($movie_name, $message_id, $channel_id);
            bot_log("Auto-added from channel: $movie_name ($message_id)");
        }
    }
}

// ==============================
// WEB INTERFACE FOR DEPLOYMENT
// ==============================
if (php_sapi_name() === 'cli' || isset($_GET['setwebhook']) || !$update) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>🎬 Entertainment Tadka Bot v3.0</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
            h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
            .status { padding: 10px; border-radius: 5px; margin: 10px 0; }
            .success { background: #d4edda; color: #155724; }
            .error { background: #f8d7da; color: #721c24; }
            .info { background: #d1ecf1; color: #0c5460; }
            .feature { background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 15px 0; }
            code { background: #333; color: white; padding: 10px; display: block; margin: 10px 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🎬 Entertainment Tadka Bot v3.0</h1>
            
            <div class='feature'>
                <h3>✅ Features Implemented</h3>
                <p>1. <strong>SQLite Database</strong> - Fast and reliable</p>
                <p>2. <strong>Admin Commands</strong> - Telegram se movie management</p>
                <p>3. <strong>Delay Typing</strong> - Optimized user experience</p>
                <p>4. <strong>Auto Channel Detection</strong> - Auto-add movies</p>
            </div>
            
            <div class='feature'>
                <h3>🛠️ Admin Commands</h3>
                <code>/quickadd MovieName MessageID</code>
                <code>/listmovies [page]</code>
                <code>/findmovie name</code>
                <code>/deletemovie name</code>
                <code>/adminstats</code>
            </div>";
    
    // Check database status
    if (file_exists(DB_FILE)) {
        try {
            $db_check = new SQLite3(DB_FILE);
            $movie_count = $db_check->querySingle("SELECT COUNT(*) FROM movies");
            $user_count = $db_check->querySingle("SELECT COUNT(*) FROM users");
            
            echo "<div class='status success'>";
            echo "<h3>📊 Database Status</h3>";
            echo "<p>Movies: <strong>$movie_count</strong></p>";
            echo "<p>Users: <strong>$user_count</strong></p>";
            echo "<p>Size: " . round(filesize(DB_FILE) / 1024, 2) . " KB</p>";
            echo "</div>";
            
            $db_check->close();
        } catch (Exception $e) {
            echo "<div class='status error'>Database Error: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='status error'>Database file not found</div>";
    }
    
    echo "
            <div class='feature'>
                <h3>🚀 Quick Setup</h3>
                <p><a href='?setwebhook=1'>Set Webhook Now</a></p>
                <p><a href='?test=1'>Test System</a></p>
                <p><a href='?migrate=1'>Migrate CSV to Database</a></p>
            </div>
            
            <div class='feature'>
                <h3>📞 Support</h3>
                <p>Admin ID: " . ADMIN_ID . "</p>
                <p>Main Channel: " . CHANNEL_1_USERNAME . "</p>
                <p>Backup Channel: " . CHANNEL_2_USERNAME . "</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Handle webhook setup
    if (isset($_GET['setwebhook'])) {
        $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $result = apiRequest('setWebhook', ['url' => $webhook_url]);
        echo "<div class='status info'>Webhook Result: " . htmlspecialchars($result) . "</div>";
    }
    
    exit;
}
?>
