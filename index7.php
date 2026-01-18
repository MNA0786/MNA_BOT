<?php
// ==============================
// SECURITY HEADERS & BASIC SETUP
// ==============================

// Security headers PHP mein set karo
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==============================
// RENDER.COM CONFIGURATION
// ==============================

$port = getenv('PORT') ?: '80';
$webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

if (!getenv('BOT_TOKEN')) {
    die("❌ BOT_TOKEN environment variable set nahi hai.");
}

// ==============================
// ENVIRONMENT VARIABLES
// ==============================
define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('ADMIN_ID', (int)getenv('ADMIN_ID'));

if (!BOT_TOKEN || !ADMIN_ID) {
    die("❌ Essential environment variables set nahi hain.");
}

// ALL CHANNELS DEFINED (6 Channels + 1 Group)
define('CHANNEL_1_ID', '-1003181705395');  // Main Channel
define('CHANNEL_2_ID', '-1002964109368');  // Backup Channel
define('CHANNEL_3_ID', '-1002831605258');  // Theater Channel
define('CHANNEL_4_ID', '-1002337293281');  // Backup Channel 2
define('CHANNEL_5_ID', '-1003251791991');  // Private Channel
define('CHANNEL_6_ID', '-1003614546520');  // Any Forwarded Channel
define('GROUP_ID', '-1003083386043');      // Request Group

// Usernames
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

// Bot settings
define('CACHE_EXPIRY', 300);
define('ITEMS_PER_PAGE', 5);
define('MAX_SEARCH_RESULTS', 15);
define('DAILY_REQUEST_LIMIT', 5);
define('AUTO_BACKUP_HOUR', '03');

// ==============================
// DELAY TYPING FEATURE
// ==============================
define('TYPING_DELAY_ENABLED', true);
define('TYPING_DELAY_MIN', 0.5);
define('TYPING_DELAY_MAX', 1.5);

// ==============================
// CHANNEL MAPPING ARRAYS
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

// Channel type to ID mapping (NEW - for commands)
$CHANNEL_TYPE_TO_ID = [
    'main' => CHANNEL_1_ID,
    'theater' => CHANNEL_3_ID,
    'backup' => CHANNEL_2_ID,
    'backup2' => CHANNEL_4_ID,
    'private' => CHANNEL_5_ID,
    'any' => CHANNEL_6_ID,
    'group' => GROUP_ID
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
// SYSTEM INITIALIZATION
// ==============================
initialize_system();

// ==============================
// CORE FUNCTIONS
// ==============================

function initialize_system() {
    // Create directories
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
        
        // Enable WAL mode for better performance
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

function parse_channel_input($channel_input) {
    global $CHANNEL_MAP, $CHANNEL_TYPE_TO_ID;
    
    $channel_input = strtolower(trim($channel_input));
    
    // Check if it's a channel ID
    if (is_numeric($channel_input) || strpos($channel_input, '-100') === 0) {
        foreach ($CHANNEL_MAP as $id => $username) {
            if ($id == $channel_input) {
                return [
                    'channel_id' => $id,
                    'channel_username' => $username,
                    'channel_type' => get_channel_type($id)
                ];
            }
        }
    }
    
    // Check if it's a username
    if (strpos($channel_input, '@') === 0) {
        foreach ($CHANNEL_MAP as $id => $username) {
            if (strtolower($username) == $channel_input) {
                return [
                    'channel_id' => $id,
                    'channel_username' => $username,
                    'channel_type' => get_channel_type($id)
                ];
            }
        }
    }
    
    // Check if it's a channel type
    if (isset($CHANNEL_TYPE_TO_ID[$channel_input])) {
        $channel_id = $CHANNEL_TYPE_TO_ID[$channel_input];
        return [
            'channel_id' => $channel_id,
            'channel_username' => get_username_from_channel_id($channel_id),
            'channel_type' => $channel_input
        ];
    }
    
    // Check channel names
    $channel_aliases = [
        'entertainmenttadka' => 'main',
        'mainchannel' => 'main',
        'etbackup' => 'backup',
        'backupchannel' => 'backup',
        'theater' => 'theater',
        'theatre' => 'theater',
        'print' => 'theater',
        'threater' => 'theater',
        'private' => 'private',
        'any' => 'any',
        'group' => 'group',
        'requestgroup' => 'group'
    ];
    
    if (isset($channel_aliases[$channel_input])) {
        $channel_type = $channel_aliases[$channel_input];
        $channel_id = $CHANNEL_TYPE_TO_ID[$channel_type];
        return [
            'channel_id' => $channel_id,
            'channel_username' => get_username_from_channel_id($channel_id),
            'channel_type' => $channel_type
        ];
    }
    
    return null;
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
// ADMIN MOVIE COMMANDS - ENHANCED WITH CHANNEL SELECTION
// ==============================
function handle_admin_movie_commands($chat_id, $user_id, $command, $params) {
    global $admin_commands_state, $db;
    
    if ($user_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    switch($command) {
        case '/quickadd':
            if (count($params) < 3) {
                sendMessage($chat_id, 
                    "📝 <b>Quick Add Format:</b>\n\n" .
                    "<code>/quickadd MovieName MessageID Channel</code>\n\n" .
                    "📌 <b>Examples:</b>\n" .
                    "<code>/quickadd AvengersEndgame 251 main</code>\n" .
                    "<code>/quickadd KGF2 5678 theater</code>\n" .
                    "<code>/quickadd \"Squid Game\" 999 -1003181705395</code>\n" .
                    "<code>/quickadd TestMovie 123 @ETBackup</code>\n\n" .
                    "🎯 <b>Channel Options:</b>\n" .
                    "• <code>main</code> - Main channel (" . CHANNEL_1_USERNAME . ")\n" .
                    "• <code>theater</code> - Theater prints (" . CHANNEL_3_USERNAME . ")\n" .
                    "• <code>backup</code> - Backup channel (" . CHANNEL_2_USERNAME . ")\n" .
                    "• <code>backup2</code> - Backup 2 (" . CHANNEL_4_USERNAME . ")\n" .
                    "• <code>private</code> - Private channel (" . CHANNEL_5_USERNAME . ")\n" .
                    "• <code>any</code> - Any forwarded (" . CHANNEL_6_USERNAME . ")\n" .
                    "• <code>group</code> - Request group (" . GROUP_USERNAME . ")\n" .
                    "• <b>OR</b> Channel ID like <code>-1003181705395</code>\n" .
                    "• <b>OR</b> Username like <code>@EntertainmentTadka786</code>\n\n" .
                    "💡 <b>Tip:</b> Use quotes for multi-word movie names",
                    null, 'HTML'
                );
                return;
            }
            
            $movie_name = $params[0];
            $message_id = $params[1];
            $channel_input = $params[2];
            
            // Remove quotes from movie name
            if ((strpos($movie_name, '"') === 0 && substr($movie_name, -1) === '"') ||
                (strpos($movie_name, "'") === 0 && substr($movie_name, -1) === "'")) {
                $movie_name = trim($movie_name, '"\'');
            }
            
            // Validate message ID
            if (!is_numeric($message_id)) {
                sendMessage($chat_id, "❌ Message ID must be numeric.");
                return;
            }
            
            // Parse channel input
            $channel_info = parse_channel_input($channel_input);
            
            if ($channel_info === null) {
                sendMessage($chat_id, 
                    "❌ Invalid channel! Available channels:\n\n" .
                    "• main (" . CHANNEL_1_USERNAME . ")\n" .
                    "• theater (" . CHANNEL_3_USERNAME . ")\n" .
                    "• backup (" . CHANNEL_2_USERNAME . ")\n" .
                    "• backup2 (" . CHANNEL_4_USERNAME . ")\n" .
                    "• private (" . CHANNEL_5_USERNAME . ")\n" .
                    "• any (" . CHANNEL_6_USERNAME . ")\n" .
                    "• group (" . GROUP_USERNAME . ")\n\n" .
                    "📌 Use channel name, ID, or username"
                );
                return;
            }
            
            $channel_id = $channel_info['channel_id'];
            $channel_username = $channel_info['channel_username'];
            $channel_type = $channel_info['channel_type'];
            
            // Check if movie already exists with same message ID in same channel
            $stmt = $db->prepare("SELECT COUNT(*) FROM movies WHERE message_id = ? AND channel_id = ?");
            $stmt->bindValue(1, intval($message_id), SQLITE3_INTEGER);
            $stmt->bindValue(2, $channel_id, SQLITE3_TEXT);
            $exists = $stmt->execute()->fetchArray(SQLITE3_NUM)[0];
            
            if ($exists > 0) {
                sendMessage($chat_id, 
                    "❌ Movie with Message ID <b>$message_id</b> already exists in <b>$channel_username</b>!\n\n" .
                    "💡 Use a different Message ID or check existing movies with /findmovie",
                    null, 'HTML'
                );
                return;
            }
            
            // Add to database
            if (db_insert_movie($movie_name, $message_id, $channel_id, $channel_username, $channel_type)) {
                $total_movies = $db->querySingle("SELECT COUNT(*) FROM movies");
                
                $response = "✅ <b>Movie Added Successfully!</b>\n\n";
                $response .= "🎬 <b>Movie:</b> $movie_name\n";
                $response .= "🆔 <b>Message ID:</b> $message_id\n";
                $response .= "📢 <b>Channel:</b> $channel_username\n";
                $response .= "🏷️ <b>Type:</b> " . ucfirst($channel_type) . "\n\n";
                
                // Create direct link
                $channel_id_clean = str_replace('-100', '', $channel_id);
                $direct_link = "https://t.me/c/$channel_id_clean/$message_id";
                $response .= "🔗 <b>Direct Link:</b> $direct_link\n\n";
                
                $response .= "📊 <b>Total Movies:</b> $total_movies\n\n";
                $response .= "🔍 <b>Test Search:</b> Send \"$movie_name\" to bot";
                
                // Add inline buttons for quick actions
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔗 Open in Channel', 'url' => $direct_link],
                            ['text' => '🔍 Search Movie', 'callback_data' => 'test_search_' . urlencode($movie_name)]
                        ],
                        [
                            ['text' => '📋 View All Movies', 'callback_data' => 'list_page_1'],
                            ['text' => '➕ Add Another', 'callback_data' => 'show_add_menu']
                        ]
                    ]
                ];
                
                sendMessage($chat_id, $response, $keyboard, 'HTML');
                bot_log("Quick add by admin $user_id: $movie_name ($message_id) to $channel_username");
            } else {
                sendMessage($chat_id, "❌ Error adding movie. Check logs for details.");
            }
            break;
            
        case '/addtomultiple':
            if (count($params) < 3) {
                sendMessage($chat_id,
                    "🔄 <b>Add to Multiple Channels Format:</b>\n\n" .
                    "<code>/addtomultiple MovieName MessageID channels</code>\n\n" .
                    "📌 <b>Examples:</b>\n" .
                    "<code>/addtomulti AvengersEndgame 251 main,theater</code>\n" .
                    "<code>/addtomulti KGF2 5678 main,theater,backup</code>\n\n" .
                    "🎯 <b>Available Channels:</b> main, theater, backup, backup2, private, any, group\n\n" .
                    "💡 <b>Note:</b> Adds same movie to multiple channels",
                    null, 'HTML'
                );
                return;
            }
            
            $movie_name = $params[0];
            $message_id = $params[1];
            $channels_str = $params[2];
            
            // Remove quotes
            if ((strpos($movie_name, '"') === 0 && substr($movie_name, -1) === '"') ||
                (strpos($movie_name, "'") === 0 && substr($movie_name, -1) === "'")) {
                $movie_name = trim($movie_name, '"\'');
            }
            
            if (!is_numeric($message_id)) {
                sendMessage($chat_id, "❌ Message ID must be numeric.");
                return;
            }
            
            $channels = explode(',', $channels_str);
            $success_count = 0;
            $failed_count = 0;
            $results = [];
            
            foreach ($channels as $channel_input) {
                $channel_input = trim($channel_input);
                $channel_info = parse_channel_input($channel_input);
                
                if ($channel_info === null) {
                    $failed_count++;
                    $results[] = "❌ $channel_input: Invalid channel";
                    continue;
                }
                
                $channel_id = $channel_info['channel_id'];
                $channel_username = $channel_info['channel_username'];
                
                // Check if exists
                $stmt = $db->prepare("SELECT COUNT(*) FROM movies WHERE message_id = ? AND channel_id = ?");
                $stmt->bindValue(1, intval($message_id), SQLITE3_INTEGER);
                $stmt->bindValue(2, $channel_id, SQLITE3_TEXT);
                $exists = $stmt->execute()->fetchArray(SQLITE3_NUM)[0];
                
                if ($exists > 0) {
                    $results[] = "⚠️ $channel_username: Already exists";
                    continue;
                }
                
                // Add to database
                if (db_insert_movie($movie_name, $message_id, $channel_id, $channel_username, $channel_info['channel_type'])) {
                    $success_count++;
                    $channel_id_clean = str_replace('-100', '', $channel_id);
                    $results[] = "✅ $channel_username: Added (ID: $message_id)";
                } else {
                    $failed_count++;
                    $results[] = "❌ $channel_username: Failed";
                }
            }
            
            $response = "🔄 <b>Multi-Channel Add Results</b>\n\n";
            $response .= "🎬 <b>Movie:</b> $movie_name\n";
            $response .= "🆔 <b>Message ID:</b> $message_id\n\n";
            $response .= "📊 <b>Summary:</b>\n";
            $response .= "✅ Success: $success_count\n";
            $response .= "❌ Failed: $failed_count\n\n";
            
            if (!empty($results)) {
                $response .= "📋 <b>Details:</b>\n";
                foreach ($results as $result) {
                    $response .= "• $result\n";
                }
            }
            
            sendMessage($chat_id, $response, null, 'HTML');
            bot_log("Multi-add by admin: $movie_name to " . count($channels) . " channels");
            break;
            
        case '/listmovies':
            $page = isset($params[0]) ? intval($params[0]) : 1;
            $channel_filter = isset($params[1]) ? $params[1] : null;
            $limit = 10;
            $offset = ($page - 1) * $limit;
            
            $where_clause = "";
            $query_params = [];
            
            if ($channel_filter) {
                $channel_info = parse_channel_input($channel_filter);
                if ($channel_info) {
                    $where_clause = "WHERE channel_id = ?";
                    $query_params[] = $channel_info['channel_id'];
                }
            }
            
            $query = "
                SELECT * FROM movies 
                $where_clause
                ORDER BY added_date DESC 
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $db->prepare($query);
            
            $param_index = 1;
            foreach ($query_params as $param) {
                $stmt->bindValue($param_index, $param, SQLITE3_TEXT);
                $param_index++;
            }
            
            $stmt->bindValue($param_index++, $limit, SQLITE3_INTEGER);
            $stmt->bindValue($param_index, $offset, SQLITE3_INTEGER);
            
            $result = $stmt->execute();
            $movies = [];
            
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $movies[] = $row;
            }
            
            // Get total count
            $count_query = "SELECT COUNT(*) FROM movies";
            if ($channel_filter && $channel_info) {
                $count_query .= " WHERE channel_id = '{$channel_info['channel_id']}'";
            }
            $total = $db->querySingle($count_query);
            $total_pages = ceil($total / $limit);
            
            if (empty($movies)) {
                $msg = "📭 No movies found";
                if ($channel_filter) {
                    $msg .= " in channel: $channel_filter";
                }
                sendMessage($chat_id, $msg);
                return;
            }
            
            $message = "📋 <b>Movies List (Page $page of $total_pages)</b>\n\n";
            
            if ($channel_filter && $channel_info) {
                $message .= "📢 <b>Channel:</b> {$channel_info['channel_username']}\n";
            }
            
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
                $prev_callback = 'list_page_' . ($page-1);
                if ($channel_filter) {
                    $prev_callback .= '_' . $channel_filter;
                }
                $buttons_row[] = ['text' => '⬅️ Previous', 'callback_data' => $prev_callback];
            }
            
            if ($page < $total_pages) {
                $next_callback = 'list_page_' . ($page+1);
                if ($channel_filter) {
                    $next_callback .= '_' . $channel_filter;
                }
                $buttons_row[] = ['text' => 'Next ➡️', 'callback_data' => $next_callback];
            }
            
            if (!empty($buttons_row)) {
                $keyboard['inline_keyboard'][] = $buttons_row;
            }
            
            // Channel filter buttons
            if (!$channel_filter) {
                $filter_row = [];
                $channels = ['main', 'theater', 'backup', 'private', 'any'];
                foreach ($channels as $channel) {
                    $filter_row[] = ['text' => get_channel_icon($channel), 'callback_data' => 'filter_channel_' . $channel];
                }
                $keyboard['inline_keyboard'][] = $filter_row;
            } else {
                $keyboard['inline_keyboard'][] = [
                    ['text' => '🧹 Clear Filter', 'callback_data' => 'list_page_1']
                ];
            }
            
            $action_row = [];
            $action_row[] = ['text' => '🔍 Search', 'callback_data' => 'admin_search_menu'];
            $action_row[] = ['text' => '➕ Add', 'callback_data' => 'show_add_menu'];
            $action_row[] = ['text' => '📊 Stats', 'callback_data' => 'admin_stats'];
            
            $keyboard['inline_keyboard'][] = $action_row;
            
            sendMessage($chat_id, $message, $keyboard, 'HTML');
            break;
            
        case '/findmovie':
        case '/searchmovie':
            if (empty($params)) {
                sendMessage($chat_id, 
                    "🔍 <b>Find Movie Format:</b>\n\n" .
                    "<code>/findmovie movie_name [channel]</code>\n\n" .
                    "📌 <b>Examples:</b>\n" .
                    "<code>/findmovie avengers</code>\n" .
                    "<code>/findmovie \"squid game\" theater</code>\n" .
                    "<code>/findmovie kgf backup</code>\n\n" .
                    "💡 <b>Tip:</b> Add channel name to filter results",
                    null, 'HTML'
                );
                return;
            }
            
            $search_query = $params[0];
            $channel_filter = isset($params[1]) ? $params[1] : null;
            
            // Build query
            $where_clause = "WHERE LOWER(movie_name) LIKE LOWER(?)";
            $query_params = ["%$search_query%"];
            
            if ($channel_filter) {
                $channel_info = parse_channel_input($channel_filter);
                if ($channel_info) {
                    $where_clause .= " AND channel_id = ?";
                    $query_params[] = $channel_info['channel_id'];
                }
            }
            
            $query = "
                SELECT * FROM movies 
                $where_clause
                ORDER BY added_date DESC 
                LIMIT 15
            ";
            
            $stmt = $db->prepare($query);
            
            foreach ($query_params as $index => $param) {
                $stmt->bindValue($index + 1, $param, is_numeric($param) ? SQLITE3_INTEGER : SQLITE3_TEXT);
            }
            
            $result = $stmt->execute();
            $movies = [];
            
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $movies[] = $row;
            }
            
            if (empty($movies)) {
                $msg = "❌ No movies found for <b>'$search_query'</b>";
                if ($channel_filter) {
                    $msg .= " in channel <b>'$channel_filter'</b>";
                }
                $msg .= "\n\n💡 Try:\n• Different spelling\n• Partial name\n• Without special characters";
                sendMessage($chat_id, $msg, null, 'HTML');
                return;
            }
            
            $message = "🔍 <b>Search Results for '$search_query'</b>\n\n";
            if ($channel_filter && $channel_info) {
                $message .= "📢 <b>Channel:</b> {$channel_info['channel_username']}\n";
            }
            $message .= "📊 Found: <b>" . count($movies) . " movies</b>\n\n";
            
            $i = 1;
            foreach ($movies as $movie) {
                $channel_icon = get_channel_icon($movie['channel_type']);
                $date = date('d-m-Y', strtotime($movie['added_date']));
                
                $message .= "<b>$i.</b> $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
                $message .= "   🆔 {$movie['message_id']} | 📢 {$movie['channel_username']}\n";
                $message .= "   📅 $date\n\n";
                
                $i++;
                if ($i > 10) break;
            }
            
            if (count($movies) > 10) {
                $message .= "... and " . (count($movies) - 10) . " more results\n\n";
            }
            
            $keyboard = ['inline_keyboard' => []];
            
            // Show first 5 movies as buttons
            foreach (array_slice($movies, 0, 5) as $movie) {
                $channel_icon = get_channel_icon($movie['channel_type']);
                $keyboard['inline_keyboard'][] = [[
                    'text' => "$channel_icon " . htmlspecialchars($movie['movie_name']),
                    'callback_data' => 'admin_view_' . $movie['id']
                ]];
            }
            
            // If only one result, show delete option
            if (count($movies) == 1) {
                $movie = $movies[0];
                $keyboard['inline_keyboard'][] = [[
                    'text' => '🗑️ Delete This Movie',
                    'callback_data' => 'admin_delete_' . $movie['id']
                ]];
            }
            
            // Channel filter buttons
            if (!$channel_filter) {
                $filter_row = [];
                $channels = ['main', 'theater', 'backup', 'private'];
                foreach ($channels as $channel) {
                    $filter_row[] = ['text' => get_channel_icon($channel), 'callback_data' => 'search_filter_' . $channel . '_' . urlencode($search_query)];
                }
                $keyboard['inline_keyboard'][] = $filter_row;
            }
            
            sendMessage($chat_id, $message, $keyboard, 'HTML');
            break;
            
        case '/deletemovie':
        case '/removemovie':
            if (empty($params)) {
                sendMessage($chat_id, 
                    "🗑️ <b>Delete Movie Format:</b>\n\n" .
                    "<code>/deletemovie movie_name [channel]</code>\n\n" .
                    "📌 <b>Examples:</b>\n" .
                    "<code>/deletemovie avengers</code>\n" .
                    "<code>/deletemovie \"squid game\" theater</code>\n" .
                    "<code>/deletemovie test backup</code>\n\n" .
                    "💡 <b>Tip:</b> Add channel name to delete from specific channel\n" .
                    "⚠️ <b>Warning:</b> This action cannot be undone!",
                    null, 'HTML'
                );
                return;
            }
            
            $movie_name = $params[0];
            $channel_filter = isset($params[1]) ? $params[1] : null;
            
            // Build query
            $where_clause = "WHERE LOWER(movie_name) LIKE LOWER(?)";
            $query_params = ["%$movie_name%"];
            
            if ($channel_filter) {
                $channel_info = parse_channel_input($channel_filter);
                if ($channel_info) {
                    $where_clause .= " AND channel_id = ?";
                    $query_params[] = $channel_info['channel_id'];
                }
            }
            
            $query = "
                SELECT * FROM movies 
                $where_clause
                ORDER BY added_date DESC 
                LIMIT 10
            ";
            
            $stmt = $db->prepare($query);
            
            foreach ($query_params as $index => $param) {
                $stmt->bindValue($index + 1, $param, is_numeric($param) ? SQLITE3_INTEGER : SQLITE3_TEXT);
            }
            
            $result = $stmt->execute();
            $matches = [];
            
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $matches[] = $row;
            }
            
            if (empty($matches)) {
                $msg = "❌ No movies found matching '$movie_name'";
                if ($channel_filter) {
                    $msg .= " in channel '$channel_filter'";
                }
                sendMessage($chat_id, $msg);
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
                $message .= "🏷️ <b>Type:</b> " . ucfirst($movie['channel_type']) . "\n";
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
            
            // Add channel-specific stats
            $message .= "\n\n🎯 <b>Channel Stats:</b>\n";
            $channel_stats = $db->query("
                SELECT channel_type, channel_username, COUNT(*) as count 
                FROM movies 
                GROUP BY channel_id 
                ORDER BY count DESC
            ");
            
            while ($row = $channel_stats->fetchArray(SQLITE3_ASSOC)) {
                $icon = get_channel_icon($row['channel_type']);
                $message .= "• $icon {$row['channel_username']}: {$row['count']} movies\n";
            }
            
            sendMessage($chat_id, $message, null, 'HTML');
            break;
            
        case '/adminhelp':
        case '/moviehelp':
            $help = "🛠️ <b>Admin Movie Management Commands</b>\n\n";
            
            $help .= "➕ <b>Add Movies:</b>\n";
            $help .= "<code>/quickadd MovieName MessageID Channel</code>\n";
            $help .= "Example: <code>/quickadd AvengersEndgame 251 main</code>\n\n";
            
            $help .= "🔄 <b>Add to Multiple Channels:</b>\n";
            $help .= "<code>/addtomultiple MovieName MessageID channels</code>\n";
            $help .= "Example: <code>/addtomulti KGF2 5678 main,theater</code>\n\n";
            
            $help .= "📋 <b>View Movies:</b>\n";
            $help .= "<code>/listmovies</code> - All movies\n";
            $help .= "<code>/listmovies 2</code> - Page 2\n";
            $help .= "<code>/listmovies 1 theater</code> - Theater movies only\n\n";
            
            $help .= "🔍 <b>Search Movies:</b>\n";
            $help .= "<code>/findmovie name</code>\n";
            $help .= "<code>/findmovie name theater</code> - Filter by channel\n\n";
            
            $help .= "🗑️ <b>Delete Movies:</b>\n";
            $help .= "<code>/deletemovie name</code>\n";
            $help .= "<code>/deletemovie name backup</code> - Delete from specific channel\n\n";
            
            $help .= "📊 <b>Statistics:</b>\n";
            $help .= "<code>/adminstats</code> - Detailed bot statistics\n\n";
            
            $help .= "🎯 <b>Channel Options:</b>\n";
            $help .= "• <code>main</code> - " . CHANNEL_1_USERNAME . "\n";
            $help .= "• <code>theater</code> - " . CHANNEL_3_USERNAME . "\n";
            $help .= "• <code>backup</code> - " . CHANNEL_2_USERNAME . "\n";
            $help .= "• <code>backup2</code> - " . CHANNEL_4_USERNAME . "\n";
            $help .= "• <code>private</code> - " . CHANNEL_5_USERNAME . "\n";
            $help .= "• <code>any</code> - " . CHANNEL_6_USERNAME . "\n";
            $help .= "• <code>group</code> - " . GROUP_USERNAME . "\n\n";
            
            $help .= "💡 <b>Pro Tips:</b>\n";
            $help .= "• Use quotes for multi-word names\n";
            $help .= "• Message ID must be numeric\n";
            $help .= "• You can use channel ID or username instead of type";
            
            sendMessage($chat_id, $help, null, 'HTML');
            break;
            
        case '/channelslist':
        case '/listchannels':
            $message = "📢 <b>Available Channels for Movie Upload</b>\n\n";
            
            $channels = [
                ['main', CHANNEL_1_ID, CHANNEL_1_USERNAME],
                ['theater', CHANNEL_3_ID, CHANNEL_3_USERNAME],
                ['backup', CHANNEL_2_ID, CHANNEL_2_USERNAME],
                ['backup2', CHANNEL_4_ID, CHANNEL_4_USERNAME],
                ['private', CHANNEL_5_ID, CHANNEL_5_USERNAME],
                ['any', CHANNEL_6_ID, CHANNEL_6_USERNAME],
                ['group', GROUP_ID, GROUP_USERNAME]
            ];
            
            foreach ($channels as $channel) {
                $type = $channel[0];
                $id = $channel[1];
                $username = $channel[2];
                $icon = get_channel_icon($type);
                
                $message .= "$icon <b>" . ucfirst($type) . " Channel:</b>\n";
                $message .= "• ID: <code>$id</code>\n";
                $message .= "• Username: $username\n";
                
                // Count movies in this channel
                $count = $db->querySingle("SELECT COUNT(*) FROM movies WHERE channel_id = '$id'");
                $message .= "• Movies: $count\n\n";
            }
            
            $message .= "💡 <b>Usage Examples:</b>\n";
            $message .= "<code>/quickadd MovieName 123 main</code>\n";
            $message .= "<code>/quickadd MovieName 456 @EntertainmentTadka786</code>\n";
            $message .= "<code>/quickadd MovieName 789 -1003181705395</code>";
            
            sendMessage($chat_id, $message, null, 'HTML');
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
            
            bot_log("Movie deleted by admin $user_id: {$movie['movie_name']} ({$movie['message_id']}) from {$movie['channel_username']}");
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
        $parts = explode('_', $data);
        $page = intval($parts[2]);
        $channel_filter = isset($parts[3]) ? $parts[3] : null;
        
        $params = [$page];
        if ($channel_filter) {
            $params[] = $channel_filter;
        }
        
        handle_admin_movie_commands($chat_id, $user_id, '/listmovies', $params);
        answerCallbackQuery($callback_id, "Page $page");
        return;
    }
    
    if (strpos($data, 'filter_channel_') === 0) {
        $channel = str_replace('filter_channel_', '', $data);
        $params = [1, $channel];
        handle_admin_movie_commands($chat_id, $user_id, '/listmovies', $params);
        answerCallbackQuery($callback_id, ucfirst($channel) . " channel");
        return;
    }
    
    if (strpos($data, 'search_filter_') === 0) {
        $parts = explode('_', $data);
        $channel = $parts[2];
        $search_query = urldecode($parts[3]);
        $params = [$search_query, $channel];
        handle_admin_movie_commands($chat_id, $user_id, '/findmovie', $params);
        answerCallbackQuery($callback_id, "Filtered by " . $channel);
        return;
    }
    
    if (strpos($data, 'test_search_') === 0) {
        $movie_name = urldecode(str_replace('test_search_', '', $data));
        advanced_search($chat_id, $movie_name, $user_id);
        answerCallbackQuery($callback_id, "Searching...");
        return;
    }
    
    if (strpos($data, 'show_add_menu') === 0) {
        $help = "➕ <b>Add Movie Quick Menu</b>\n\n";
        $help .= "📝 <b>Format:</b>\n";
        $help .= "<code>/quickadd MovieName MessageID Channel</code>\n\n";
        
        $help .= "🎯 <b>Channel Shortcuts:</b>\n";
        $help .= "• 🍿 Main: <code>main</code> or <code>" . CHANNEL_1_USERNAME . "</code>\n";
        $help .= "• 🎭 Theater: <code>theater</code> or <code>" . CHANNEL_3_USERNAME . "</code>\n";
        $help .= "• 💾 Backup: <code>backup</code> or <code>" . CHANNEL_2_USERNAME . "</code>\n";
        $help .= "• 🔒 Private: <code>private</code> or <code>" . CHANNEL_5_USERNAME . "</code>\n\n";
        
        $help .= "💡 <b>Example:</b>\n";
        $help .= "<code>/quickadd \"Avengers Endgame\" 251 main</code>";
        
        sendMessage($chat_id, $help, null, 'HTML');
        answerCallbackQuery($callback_id, "Add menu");
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
            $confirm_message .= "📢 <b>Channel:</b> {$movie['channel_username']}\n";
            $confirm_message .= "🏷️ <b>Type:</b> " . ucfirst($movie['channel_type']) . "\n\n";
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
    
    if ($data == 'admin_search_menu') {
        sendMessage($chat_id, 
            "🔍 <b>Search Movies</b>\n\n" .
            "Type: <code>/findmovie movie_name [channel]</code>\n\n" .
            "📌 <b>Examples:</b>\n" .
            "<code>/findmovie avengers</code>\n" .
            "<code>/findmovie \"squid game\" theater</code>\n" .
            "<code>/findmovie kgf backup</code>\n\n" .
            "💡 Add channel name to filter results",
            null, 'HTML'
        );
        answerCallbackQuery($callback_id, "Search menu");
        return;
    }
    
    if ($data == 'admin_stats') {
        handle_admin_movie_commands($chat_id, $user_id, '/adminstats', []);
        answerCallbackQuery($callback_id, "Statistics");
        return;
    }
}

// ==============================
// REGULAR USER FUNCTIONS
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
    
    // Extract channel filter from query
    $channel_filter = null;
    $channel_keywords = [
        'theater' => 'theater',
        'print' => 'theater',
        'theatre' => 'theater',
        'threater' => 'theater',
        'main' => 'main',
        'entertainment' => 'main',
        'backup' => 'backup',
        'etbackup' => 'backup',
        'private' => 'private',
        'any' => 'any',
        'group' => 'group'
    ];
    
    foreach ($channel_keywords as $keyword => $channel_type) {
        if (strpos($q, ' ' . $keyword) !== false || strpos($q, $keyword . ' ') !== false) {
            $channel_filter = $channel_type;
            $q = str_replace($keyword, '', $q);
            $q = trim($q);
            break;
        }
    }
    
    $results = db_search_movies($q, MAX_SEARCH_RESULTS);
    
    // Apply channel filter if specified
    if ($channel_filter) {
        $filtered_results = [];
        foreach ($results as $movie) {
            if ($movie['channel_type'] == $channel_filter) {
                $filtered_results[] = $movie;
            }
        }
        $results = $filtered_results;
    }
    
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
// CHANNEL AUTO-ADD FUNCTIONALITY
// ==============================
function handle_channel_post($post) {
    global $db;
    
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
        
        // Send notification to admin
        $channel_username = get_username_from_channel_id($channel_id);
        $admin_msg = "✅ <b>Auto-Added Movie</b>\n\n";
        $admin_msg .= "🎬 <b>Movie:</b> $movie_name\n";
        $admin_msg .= "🆔 <b>Message ID:</b> $message_id\n";
        $admin_msg .= "📢 <b>Channel:</b> $channel_username\n";
        $admin_msg .= "⏰ <b>Time:</b> " . date('H:i:s');
        
        sendMessage(ADMIN_ID, $admin_msg, null, 'HTML');
    }
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
                '/quickadd', '/addtomultiple', '/addtomulti', '/listmovies', 
                '/findmovie', '/searchmovie', '/deletemovie', '/removemovie', 
                '/adminstats', '/botstats', '/adminhelp', '/moviehelp',
                '/channelslist', '/listchannels'
            ];
            
            if (in_array($command, $admin_commands)) {
                handle_admin_movie_commands($chat_id, $user_id, $command, $params);
                exit;
            }
            
            // Handle admin text responses
            if (handle_admin_text_response($chat_id, $user_id, $text)) {
                exit;
            }
            
            // Regular commands
            switch($command) {
                case '/start':
                    $welcome = "🎬 Welcome to Entertainment Tadka!\n\n";
                    $welcome .= "🔍 <b>Search Movies:</b> Just type movie name\n";
                    $welcome .= "💡 <b>Add channel name:</b> 'movie theater' for theater prints\n";
                    $welcome .= "📢 <b>Channels:</b> /channels\n";
                    $welcome .= "❓ <b>Help:</b> /help\n\n";
                    $welcome .= "💬 Type any movie name to search!";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => ''],
                                ['text' => '📢 Join Channel', 'url' => 'https://t.me/EntertainmentTadka786']
                            ]
                        ]
                    ];
                    
                    sendMessage($chat_id, $welcome, $keyboard, 'HTML');
                    break;
                    
                case '/help':
                    $help = "🤖 <b>Entertainment Tadka Bot Commands</b>\n\n";
                    $help .= "🔍 <b>Search:</b> Type movie name\n";
                    $help .= "🎯 <b>Channel Search:</b> Add 'theater', 'backup', etc.\n";
                    $help .= "📢 <b>Channels:</b> /channels\n";
                    $help .= "📝 <b>Request:</b> /request moviename\n";
                    $help .= "📊 <b>Stats:</b> /mystats\n\n";
                    $help .= "💡 <b>Pro Tips:</b>\n";
                    $help .= "• 'avengers theater' for theater prints\n";
                    $help .= "• 'kgf backup' for backup channel\n";
                    $help .= "• Partial names work (aveng for avengers)\n";
                    $help .= "• Join all channels for updates";
                    
                    sendMessage($chat_id, $help, null, 'HTML');
                    break;
                    
                case '/channels':
                    $channels = "📢 <b>Our Channels</b>\n\n";
                    $channels .= "🍿 Main: " . CHANNEL_1_USERNAME . "\n";
                    $channels .= "🎭 Theater: " . CHANNEL_3_USERNAME . "\n";
                    $channels .= "💾 Backup: " . CHANNEL_2_USERNAME . "\n";
                    $channels .= "👥 Request Group: " . GROUP_USERNAME . "\n\n";
                    $channels .= "🔔 Join all for latest updates!\n\n";
                    $channels .= "💡 <b>Search Tips:</b>\n";
                    $channels .= "• Add 'theater' for theater prints\n";
                    $channels .= "• Add 'backup' for backup channel\n";
                    $channels .= "• Example: 'kgf theater'";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '🍿 Main Channel', 'url' => 'https://t.me/EntertainmentTadka786'],
                                ['text' => '🎭 Theater', 'url' => 'https://t.me/threater_print_movies']
                            ],
                            [
                                ['text' => '💾 Backup', 'url' => 'https://t.me/ETBackup'],
                                ['text' => '👥 Group', 'url' => 'https://t.me/EntertainmentTadka7860']
                            ]
                        ]
                    ];
                    
                    sendMessage($chat_id, $channels, $keyboard, 'HTML');
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
        if (strpos($data, 'admin_') === 0 || 
            strpos($data, 'list_page_') === 0 || 
            strpos($data, 'filter_') === 0 ||
            strpos($data, 'show_') === 0 ||
            strpos($data, 'test_') === 0) {
            handle_admin_callback_query($callback_query);
        } else {
            // Handle regular callbacks
            $message = $callback_query['message'];
            $chat_id = $message['chat']['id'];
            $user_id = $callback_query['from']['id'];
            $callback_id = $callback_query['id'];
            
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
                    $db->exec("UPDATE users SET total_downloads = total_downloads + 1 WHERE user_id = ?");
                    
                    sendMessage($chat_id, "✅ Movie sent! Enjoy watching! 🎬");
                    answerCallbackQuery($callback_id, "Movie sent!");
                } else {
                    answerCallbackQuery($callback_id, "Movie not found", true);
                }
            }
            elseif (strpos($data, 'auto_request_') === 0) {
                $movie_name = base64_decode(str_replace('auto_request_', '', $data));
                sendMessage($chat_id, 
                    "📝 <b>Movie Request</b>\n\n" .
                    "Movie: <b>$movie_name</b>\n\n" .
                    "To request this movie, join our request group:\n" .
                    GROUP_USERNAME . "\n\n" .
                    "Or use: <code>/request $movie_name</code>",
                    null, 'HTML'
                );
                answerCallbackQuery($callback_id, "Request info");
            }
            elseif ($data == 'request_movie') {
                sendMessage($chat_id, 
                    "📝 <b>Request a Movie</b>\n\n" .
                    "To request a movie:\n\n" .
                    "1. Join our request group: " . GROUP_USERNAME . "\n" .
                    "2. Post your movie request there\n\n" .
                    "OR\n\n" .
                    "Use: <code>/request movie_name</code>\n\n" .
                    "Example: <code>/request Avengers Endgame</code>",
                    null, 'HTML'
                );
                answerCallbackQuery($callback_id, "Request instructions");
            }
        }
    }
    
    // Handle channel posts (auto-add movies)
    if (isset($update['channel_post'])) {
        handle_channel_post($update['channel_post']);
    }
}

// ==============================
// WEB INTERFACE FOR DEPLOYMENT
// ==============================
if (php_sapi_name() === 'cli' || isset($_GET['setwebhook']) || !$update) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>🎬 Entertainment Tadka Bot v3.0 - Complete System</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
            .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
            h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
            h2 { color: #555; margin-top: 30px; }
            .status { padding: 15px; border-radius: 5px; margin: 15px 0; }
            .success { background: #d4edda; color: #155724; }
            .error { background: #f8d7da; color: #721c24; }
            .info { background: #d1ecf1; color: #0c5460; }
            .feature { background: #e8f5e9; padding: 20px; border-radius: 5px; margin: 20px 0; }
            code { background: #333; color: white; padding: 10px; display: block; margin: 10px 0; border-radius: 5px; font-family: monospace; }
            .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
            .card { background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #dee2e6; }
            ul { padding-left: 20px; }
            li { margin: 5px 0; }
            a { color: #4CAF50; text-decoration: none; }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🎬 Entertainment Tadka Bot v3.0 - Complete System</h1>
            
            <div class='feature'>
                <h2>✅ Features Implemented</h2>
                
                <div class='grid'>
                    <div class='card'>
                        <h3>📊 Database System</h3>
                        <p>• SQLite with FTS search</p>
                        <p>• Auto-migrate from CSV</p>
                        <p>• Fast and reliable</p>
                    </div>
                    
                    <div class='card'>
                        <h3>🛠️ Admin Commands</h3>
                        <p>• Channel selection for /quickadd</p>
                        <p>• Multi-channel adding</p>
                        <p>• Search with filters</p>
                        <p>• Easy deletion</p>
                    </div>
                    
                    <div class='card'>
                        <h3>📢 Channel Support</h3>
                        <p>• 6 channels + 1 group</p>
                        <p>• Auto-add from channels</p>
                        <p>• Channel-specific search</p>
                        <p>• Forward headers preserved</p>
                    </div>
                </div>
            </div>
            
            <div class='feature'>
                <h2>🎯 Admin Commands with Channel Selection</h2>
                
                <h3>➕ Add Movies:</h3>
                <code>/quickadd MovieName MessageID Channel</code>
                <p><strong>Examples:</strong></p>
                <code>/quickadd AvengersEndgame 251 main</code>
                <code>/quickadd KGF2 5678 theater</code>
                <code>/quickadd \"Squid Game\" 999 -1003181705395</code>
                <code>/quickadd TestMovie 123 @ETBackup</code>
                
                <h3>🔄 Add to Multiple Channels:</h3>
                <code>/addtomultiple MovieName MessageID channels</code>
                <p><strong>Example:</strong></p>
                <code>/addtomultiple AvengersEndgame 251 main,theater,backup</code>
                
                <h3>📋 View with Filters:</h3>
                <code>/listmovies [page] [channel]</code>
                <p><strong>Examples:</strong></p>
                <code>/listmovies 1 theater</code>
                <code>/listmovies 2 backup</code>
                
                <h3>🔍 Search with Channel Filter:</h3>
                <code>/findmovie name [channel]</code>
                <p><strong>Examples:</strong></p>
                <code>/findmovie avengers theater</code>
                <code>/findmovie \"squid game\" backup</code>
            </div>";
    
    // Check database status
    if (file_exists(DB_FILE)) {
        try {
            $db_check = new SQLite3(DB_FILE);
            $movie_count = $db_check->querySingle("SELECT COUNT(*) FROM movies");
            $user_count = $db_check->querySingle("SELECT COUNT(*) FROM users");
            
            // Get channel distribution
            $channel_stats = $db_check->query("
                SELECT channel_type, channel_username, COUNT(*) as count 
                FROM movies 
                GROUP BY channel_id 
                ORDER BY count DESC
            ");
            
            echo "<div class='status success'>";
            echo "<h3>📊 Database Status</h3>";
            echo "<p>Total Movies: <strong>$movie_count</strong></p>";
            echo "<p>Total Users: <strong>$user_count</strong></p>";
            echo "<p>Database Size: " . round(filesize(DB_FILE) / 1024, 2) . " KB</p>";
            
            echo "<h4>📢 Channel Distribution:</h4>";
            echo "<ul>";
            while ($row = $channel_stats->fetchArray(SQLITE3_ASSOC)) {
                $icon = get_channel_icon($row['channel_type']);
                echo "<li>$icon {$row['channel_username']}: {$row['count']} movies</li>";
            }
            echo "</ul>";
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
                <h2>🎯 Available Channels for /quickadd</h2>
                <ul>
                    <li>🍿 <b>main</b> - " . CHANNEL_1_USERNAME . " (Main channel)</li>
                    <li>🎭 <b>theater</b> - " . CHANNEL_3_USERNAME . " (Theater prints)</li>
                    <li>💾 <b>backup</b> - " . CHANNEL_2_USERNAME . " (Backup channel)</li>
                    <li>📁 <b>backup2</b> - " . CHANNEL_4_USERNAME . " (Backup 2)</li>
                    <li>🔒 <b>private</b> - " . CHANNEL_5_USERNAME . " (Private channel)</li>
                    <li>🔀 <b>any</b> - " . CHANNEL_6_USERNAME . " (Any forwarded)</li>
                    <li>👥 <b>group</b> - " . GROUP_USERNAME . " (Request group)</li>
                </ul>
                <p><strong>Or use:</strong> Channel ID like <code>-1003181705395</code> or Username like <code>@EntertainmentTadka786</code></p>
            </div>
            
            <div class='feature'>
                <h2>🚀 Quick Setup</h2>
                <p><a href='?setwebhook=1'>✅ Set Webhook Now</a></p>
                <p><a href='?test=1'>🧪 Test System</a></p>
                <p><a href='?migrate=1'>📦 Migrate CSV to Database</a></p>
            </div>
            
            <div class='feature'>
                <h2>📞 Support & Channels</h2>
                <p><strong>Admin ID:</strong> " . ADMIN_ID . "</p>
                <p><strong>Main Channel:</strong> " . CHANNEL_1_USERNAME . "</p>
                <p><strong>Backup Channel:</strong> " . CHANNEL_2_USERNAME . "</p>
                <p><strong>Theater Channel:</strong> " . CHANNEL_3_USERNAME . "</p>
                <p><strong>Request Group:</strong> " . GROUP_USERNAME . "</p>
            </div>
            
            <div class='status info'>
                <h3>✅ System Ready</h3>
                <p>This bot now supports:</p>
                <ul>
                    <li>Complete movie management via Telegram</li>
                    <li>7-channel support with forward headers</li>
                    <li>Database-driven search with FTS</li>
                    <li>Admin commands with channel selection</li>
                    <li>Auto-add from channels</li>
                    <li>Delay typing for better UX</li>
                    <li>Statistics and tracking</li>
                </ul>
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
