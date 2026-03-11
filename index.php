<?php
// ==============================
// ENTERTAINMENT TADKA BOT v3.0.0
// ADVANCED MULTI-CHANNEL MOVIE BOT
// ==============================

// ==============================
// 1. SECURITY HEADERS
// ==============================
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'none'; object-src 'none'");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// ==============================
// 2. ERROR REPORTING
// ==============================
if (getenv('ENVIRONMENT') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ==============================
// 3. TIMEZONE
// ==============================
date_default_timezone_set('Asia/Kolkata');

// ==============================
// 4. CONSTANTS & CONFIGURATION
// ==============================

// Bot Configuration
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '8315381064:AAGk0FGVGmB8j5SjpBvW3rD3_kQHe_hyOWU');
define('BOT_USERNAME', '@EntertainmentTadkaBot');
define('BOT_VERSION', '3.0.0');
define('ADMIN_ID', (int)(getenv('ADMIN_ID') ?: '1080317415'));

// Channel Configuration - Single Source of Truth
$CHANNELS = [
    'main' => [
        'id' => '-1003181705395',
        'username' => '@EntertainmentTadka786',
        'type' => 'public',
        'name' => 'Main Channel',
        'emoji' => '🎬',
        'header' => true
    ],
    'theater' => [
        'id' => '-1002831605258',
        'username' => '@threater_print_movies',
        'type' => 'public',
        'name' => 'Theater Prints',
        'emoji' => '🎭',
        'header' => true
    ],
    'backup' => [
        'id' => '-1002964109368',
        'username' => '@ETBackup',
        'type' => 'public',
        'name' => 'Backup Channel',
        'emoji' => '💾',
        'header' => true
    ],
    'request' => [
        'id' => '-1003083386043',
        'username' => '@EntertainmentTadka7860',
        'type' => 'public',
        'name' => 'Request Group',
        'emoji' => '📝',
        'header' => true
    ],
    'private1' => [
        'id' => '-1003251791991',
        'username' => null,
        'type' => 'private',
        'name' => 'Private Channel 1',
        'emoji' => '🔐',
        'header' => false
    ],
    'private2' => [
        'id' => '-1002337293281',
        'username' => null,
        'type' => 'private',
        'name' => 'Private Channel 2',
        'emoji' => '🔒',
        'header' => false
    ],
    'any' => [
        'id' => '-1003614546520',
        'username' => null,
        'type' => 'private',
        'name' => 'Any Channel',
        'emoji' => '📡',
        'header' => false
    ]
];

// Channel IDs for quick access
define('MAIN_CHANNEL_ID', $CHANNELS['main']['id']);
define('THEATER_CHANNEL_ID', $CHANNELS['theater']['id']);
define('BACKUP_CHANNEL_ID', $CHANNELS['backup']['id']);
define('REQUEST_CHANNEL_ID', $CHANNELS['request']['id']);
define('PRIVATE_CHANNEL_1', $CHANNELS['private1']['id']);
define('PRIVATE_CHANNEL_2', $CHANNELS['private2']['id']);
define('ANY_CHANNEL_ID', $CHANNELS['any']['id']);

// Channel Usernames
define('MAIN_CHANNEL', $CHANNELS['main']['username']);
define('THEATER_CHANNEL', $CHANNELS['theater']['username']);
define('BACKUP_CHANNEL_USERNAME', $CHANNELS['backup']['username']);
define('REQUEST_CHANNEL', $CHANNELS['request']['username']);

// File Paths
define('CSV_FILE', 'movies.csv');
define('USERS_FILE', 'users.json');
define('STATS_FILE', 'bot_stats.json');
define('REQUEST_FILE', 'movie_requests.json');
define('BACKUP_DIR', 'backups/');
define('LOG_FILE', 'bot_activity.log');
define('DB_FILE', 'database/movies.db');
define('CACHE_DIR', 'cache/');

// Performance Settings
define('CACHE_EXPIRY', 300);              // 5 minutes
define('ITEMS_PER_PAGE', 5);               // Pagination limit
define('MAX_SEARCH_RESULTS', 50);           // Max search results
define('SEARCH_COOLDOWN', 5);               // Seconds between searches
define('DAILY_REQUEST_LIMIT', 5);           // Max requests per day
define('AUTO_BACKUP_HOUR', '03');           // Backup at 3 AM
define('TEXT_DELETE_SECONDS', 10);          // Auto-delete text
define('SEARCH_DELETE_SECONDS', 60);        // Auto-delete search
define('FILE_DELETE_SECONDS', 120);         // Auto-delete files
define('MAX_PAGES_TO_SHOW', 7);              // Pagination buttons
define('PREVIEW_ITEMS', 3);                  // Preview count
define('BATCH_SIZE', 5);                      // Batch download size

// Feature Flags
define('ENABLE_CACHE', true);
define('ENABLE_REDIS', class_exists('Redis'));
define('ENABLE_ANALYTICS', true);
define('ENABLE_AUTO_RESPONSE', true);
define('ENABLE_LEARNING', true);

// Quick Add Format (LOCKED - 3 COLUMNS ONLY)
define('QUICKADD_FORMAT', "🎬 <b>Quick Add Format (LOCKED):</b>\n\n" .
    "<code>/add movie_name,message_id,channel_id</code>\n\n" .
    "<b>Examples:</b>\n" .
    "• <code>/add KGF 2,12345,-1003181705395</code>\n" .
    "• <code>/add Pushpa,67890,-1002831605258</code>\n" .
    "• <code>/add Animal,54321,-1002337293281</code>\n\n" .
    "<b>Supported Channels:</b>\n" .
    "• -1003181705395 (Main)\n" .
    "• -1002831605258 (Theater)\n" .
    "• -1002964109368 (Backup)\n" .
    "• -1003251791991 (Private 1)\n" .
    "• -1002337293281 (Private 2)\n" .
    "• -1003614546520 (Any)\n" .
    "• -1003083386043 (Request)\n\n" .
    "<b>⚠️ FORMAT LOCKED - 3 COLUMNS ONLY!</b>");

// ==============================
// 5. GLOBAL VARIABLES
// ==============================
$GLOBALS = [
    'movie_cache' => [],
    'user_cache' => [],
    'search_cache' => [],
    'delete_queue' => [],
    'user_last_search' => [],
    'waiting_users' => [],
    'user_sessions' => [],
    'pagination_sessions' => [],
    'maintenance_mode' => false,
    'start_time' => microtime(true),
    'db_connections' => []
];

// Maintenance Message
$MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>\n\n" .
    "We're temporarily unavailable for updates.\n" .
    "Will be back in few days!\n\n" .
    "Thanks for patience 🙏";

// ==============================
// 6. HELPER FUNCTIONS
// ==============================

/**
 * Log bot activities
 */
function bot_log($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
    
    if (ENABLE_ANALYTICS && $type === 'ERROR') {
        // Send critical errors to admin
        sendMessage(ADMIN_ID, "🚨 <b>Bot Error:</b>\n<code>$message</code>", null, 'HTML');
    }
}

/**
 * Initialize all required files
 */
function initialize_files() {
    $files = [
        CSV_FILE => "movie_name,message_id,channel_id\n",
        USERS_FILE => json_encode([
            'users' => [],
            'total_requests' => 0,
            'message_logs' => [],
            'daily_stats' => [],
            'version' => BOT_VERSION,
            'last_cleanup' => null
        ], JSON_PRETTY_PRINT),
        STATS_FILE => json_encode([
            'total_movies' => 0,
            'total_users' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
            'successful_searches' => 0,
            'failed_searches' => 0,
            'daily_activity' => [],
            'last_updated' => date('Y-m-d H:i:s'),
            'version' => BOT_VERSION
        ], JSON_PRETTY_PRINT),
        REQUEST_FILE => json_encode([
            'requests' => [],
            'pending_approval' => [],
            'completed_requests' => [],
            'user_request_count' => [],
            'total_requests' => 0
        ], JSON_PRETTY_PRINT)
    ];
    
    foreach ($files as $file => $content) {
        if (!file_exists($file)) {
            file_put_contents($file, $content);
            chmod($file, 0666);
        }
    }
    
    // Create directories
    foreach ([BACKUP_DIR, CACHE_DIR, 'database/', 'logs/'] as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    
    // Initialize SQLite database
    init_database();
    
    bot_log("System initialized");
}

/**
 * Initialize SQLite database with FTS5
 */
function init_database() {
    $db = new SQLite3(DB_FILE);
    
    // Enable foreign keys
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('PRAGMA cache_size = 10000');
    $db->exec('PRAGMA temp_store = MEMORY');
    
    // Create FTS5 virtual table for fast search
    $db->exec("CREATE VIRTUAL TABLE IF NOT EXISTS movies_fts 
               USING fts5(
                   movie_name, 
                   message_id, 
                   channel_id, 
                   quality, 
                   size, 
                   language, 
                   year,
                   content='movies',
                   tokenize='porter unicode61'
               )");
    
    // Create main movies table
    $db->exec("CREATE TABLE IF NOT EXISTS movies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        movie_name TEXT NOT NULL,
        message_id INTEGER NOT NULL,
        channel_id TEXT NOT NULL,
        quality TEXT DEFAULT 'HD',
        size TEXT DEFAULT 'Unknown',
        language TEXT DEFAULT 'Hindi',
        year INTEGER,
        added_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        downloads INTEGER DEFAULT 0,
        searches INTEGER DEFAULT 0,
        UNIQUE(message_id, channel_id)
    )");
    
    // Create indexes for performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_movie_name ON movies(movie_name)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_channel_id ON movies(channel_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_added_date ON movies(added_date)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_downloads ON movies(downloads)");
    
    // Create users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY,
        first_name TEXT,
        last_name TEXT,
        username TEXT,
        joined DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_active DATETIME,
        points INTEGER DEFAULT 0,
        searches INTEGER DEFAULT 0,
        downloads INTEGER DEFAULT 0,
        requests INTEGER DEFAULT 0,
        language TEXT DEFAULT 'hinglish',
        settings TEXT DEFAULT '{}'
    )");
    
    // Create requests table
    $db->exec("CREATE TABLE IF NOT EXISTS requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        movie_name TEXT NOT NULL,
        language TEXT DEFAULT 'hindi',
        status TEXT DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME,
        votes INTEGER DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )");
    
    // Create learning table for auto-responses
    $db->exec("CREATE TABLE IF NOT EXISTS learning (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        query TEXT UNIQUE,
        response TEXT,
        count INTEGER DEFAULT 1,
        last_used DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create stats table
    $db->exec("CREATE TABLE IF NOT EXISTS stats (
        date TEXT PRIMARY KEY,
        searches INTEGER DEFAULT 0,
        downloads INTEGER DEFAULT 0,
        users INTEGER DEFAULT 0,
        requests INTEGER DEFAULT 0
    )");
    
    // Create cache table
    $db->exec("CREATE TABLE IF NOT EXISTS cache (
        key TEXT PRIMARY KEY,
        value TEXT,
        expires INTEGER
    )");
    
    bot_log("Database initialized");
    
    return $db;
}

/**
 * Get database connection (singleton pattern)
 */
function get_db() {
    static $db = null;
    if ($db === null) {
        $db = init_database();
    }
    return $db;
}

/**
 * Get channel info by ID
 */
function get_channel($channel_id) {
    global $CHANNELS;
    
    foreach ($CHANNELS as $channel) {
        if ($channel['id'] == $channel_id) {
            return $channel;
        }
    }
    
    return [
        'id' => $channel_id,
        'username' => null,
        'type' => 'unknown',
        'name' => 'Unknown Channel',
        'emoji' => '📢',
        'header' => false
    ];
}

/**
 * Check if channel should show header
 */
function channel_has_header($channel_id) {
    $channel = get_channel($channel_id);
    return $channel['header'] ?? false;
}

/**
 * Get channel display name with emoji
 */
function format_channel($channel_id) {
    $channel = get_channel($channel_id);
    return "{$channel['emoji']} {$channel['name']}";
}

/**
 * Get channel link
 */
function get_channel_link($channel_id, $message_id) {
    $channel_id_clean = str_replace('-100', '', $channel_id);
    return "https://t.me/c/{$channel_id_clean}/{$message_id}";
}

// ==============================
// 7. CACHE FUNCTIONS
// ==============================

/**
 * Get from cache
 */
function cache_get($key) {
    if (!ENABLE_CACHE) return null;
    
    // Try Redis first
    if (ENABLE_REDIS) {
        static $redis = null;
        if ($redis === null) {
            try {
                $redis = new Redis();
                $redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', getenv('REDIS_PORT') ?: 6379);
            } catch (Exception $e) {
                bot_log("Redis connection failed: " . $e->getMessage(), 'WARNING');
            }
        }
        
        if ($redis) {
            $value = $redis->get($key);
            if ($value !== false) {
                return unserialize($value);
            }
        }
    }
    
    // Fallback to SQLite cache
    $db = get_db();
    $stmt = $db->prepare("SELECT value FROM cache WHERE key = :key AND (expires IS NULL OR expires > :now)");
    $stmt->bindValue(':key', $key);
    $stmt->bindValue(':now', time());
    $result = $stmt->execute();
    
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        return unserialize($row['value']);
    }
    
    return null;
}

/**
 * Set cache
 */
function cache_set($key, $value, $ttl = CACHE_EXPIRY) {
    if (!ENABLE_CACHE) return;
    
    $serialized = serialize($value);
    $expires = $ttl ? time() + $ttl : null;
    
    // Try Redis
    if (ENABLE_REDIS && isset($redis)) {
        try {
            $redis->setex($key, $ttl, $serialized);
            return;
        } catch (Exception $e) {
            // Fall through to SQLite
        }
    }
    
    // SQLite cache
    $db = get_db();
    $stmt = $db->prepare("INSERT OR REPLACE INTO cache (key, value, expires) VALUES (:key, :value, :expires)");
    $stmt->bindValue(':key', $key);
    $stmt->bindValue(':value', $serialized);
    $stmt->bindValue(':expires', $expires);
    $stmt->execute();
}

/**
 * Clear cache
 */
function cache_clear($pattern = null) {
    if ($pattern === null) {
        // Clear all cache
        if (ENABLE_REDIS && isset($redis)) {
            $redis->flushAll();
        }
        $db = get_db();
        $db->exec("DELETE FROM cache");
    } else {
        // Clear by pattern (SQL LIKE)
        $db = get_db();
        $stmt = $db->prepare("DELETE FROM cache WHERE key LIKE :pattern");
        $stmt->bindValue(':pattern', $pattern);
        $stmt->execute();
    }
}

// ==============================
// 8. CSV FUNCTIONS (3-COLUMN LOCKED)
// ==============================

/**
 * Load movies from CSV
 */
function load_movies() {
    global $GLOBALS;
    
    // Check cache first
    if (!empty($GLOBALS['movie_cache']) && 
        (time() - $GLOBALS['movie_cache']['timestamp']) < CACHE_EXPIRY) {
        return $GLOBALS['movie_cache']['data'];
    }
    
    $movies = [];
    
    if (!file_exists(CSV_FILE)) {
        file_put_contents(CSV_FILE, "movie_name,message_id,channel_id\n");
        return $movies;
    }
    
    $handle = fopen(CSV_FILE, 'r');
    if ($handle) {
        // Skip header
        fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3) {
                $movie_name = trim($row[0]);
                $message_id = trim($row[1]);
                $channel_id = trim($row[2]);
                
                if (!empty($movie_name) && is_numeric($message_id)) {
                    $channel = get_channel($channel_id);
                    
                    $movies[] = [
                        'movie_name' => $movie_name,
                        'message_id' => (int)$message_id,
                        'channel_id' => $channel_id,
                        'channel_type' => $channel['type'],
                        'channel_name' => $channel['name'],
                        'channel_emoji' => $channel['emoji'],
                        'quality' => detect_quality($movie_name),
                        'language' => detect_language_text($movie_name),
                        'year' => extract_year($movie_name),
                        'added_date' => date('Y-m-d', filemtime(CSV_FILE))
                    ];
                }
            }
        }
        fclose($handle);
    }
    
    // Update cache
    $GLOBALS['movie_cache'] = [
        'data' => $movies,
        'timestamp' => time()
    ];
    
    // Update stats
    update_stat('total_movies', count($movies));
    
    return $movies;
}

/**
 * Add movie to CSV (3 columns only - LOCKED)
 */
function add_movie($movie_name, $message_id, $channel_id) {
    // Validate
    if (empty($movie_name) || !is_numeric($message_id) || empty($channel_id)) {
        return false;
    }
    
    // Check if already exists
    $existing = search_movies_exact($movie_name, $message_id, $channel_id);
    if ($existing) {
        return false; // Already exists
    }
    
    // Write to CSV (3 columns ONLY)
    $handle = fopen(CSV_FILE, 'a');
    if ($handle) {
        fputcsv($handle, [$movie_name, $message_id, $channel_id]);
        fclose($handle);
        
        // Clear cache
        cache_clear('search_%');
        global $GLOBALS;
        unset($GLOBALS['movie_cache']);
        
        // Add to SQLite
        add_movie_to_db($movie_name, $message_id, $channel_id);
        
        // Check for waiting users
        notify_waiting_users($movie_name, $channel_id);
        
        bot_log("Movie added: $movie_name ($message_id) to $channel_id");
        
        return true;
    }
    
    return false;
}

/**
 * Add movie to SQLite database
 */
function add_movie_to_db($movie_name, $message_id, $channel_id) {
    $db = get_db();
    
    $quality = detect_quality($movie_name);
    $language = detect_language_text($movie_name);
    $year = extract_year($movie_name);
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO movies 
        (movie_name, message_id, channel_id, quality, language, year) 
        VALUES (:name, :msg, :channel, :quality, :lang, :year)");
    
    $stmt->bindValue(':name', $movie_name);
    $stmt->bindValue(':msg', $message_id);
    $stmt->bindValue(':channel', $channel_id);
    $stmt->bindValue(':quality', $quality);
    $stmt->bindValue(':lang', $language);
    $stmt->bindValue(':year', $year);
    
    $stmt->execute();
    
    // Update FTS index
    $db->exec("INSERT INTO movies_fts(rowid, movie_name, message_id, channel_id, quality, language, year)
               SELECT id, movie_name, message_id, channel_id, quality, language, year
               FROM movies WHERE message_id = $message_id AND channel_id = '$channel_id'");
}

/**
 * Detect quality from movie name
 */
function detect_quality($name) {
    $name = strtolower($name);
    
    if (strpos($name, '1080') !== false || strpos($name, 'full hd') !== false) {
        return '1080p';
    } elseif (strpos($name, '720') !== false || strpos($name, 'hd') !== false) {
        return '720p';
    } elseif (strpos($name, '480') !== false || strpos($name, 'sd') !== false) {
        return '480p';
    } elseif (strpos($name, 'theater') !== false || strpos($name, 'print') !== false) {
        return 'Theater';
    } elseif (strpos($name, 'cam') !== false) {
        return 'CAM';
    }
    
    return 'HD';
}

/**
 * Extract year from movie name
 */
function extract_year($name) {
    if (preg_match('/\b(19|20)\d{2}\b/', $name, $matches)) {
        return (int)$matches[0];
    }
    return null;
}

/**
 * Detect language from movie name
 */
function detect_language_text($name) {
    $name = strtolower($name);
    
    $langs = [
        'hindi' => ['hindi', 'hind', 'hd'],
        'english' => ['english', 'eng', 'en'],
        'tamil' => ['tamil', 'tam'],
        'telugu' => ['telugu', 'tel'],
        'malayalam' => ['malayalam', 'mal'],
        'kannada' => ['kannada', 'kan'],
        'bengali' => ['bengali', 'ben'],
        'punjabi' => ['punjabi', 'pun']
    ];
    
    foreach ($langs as $lang => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($name, $pattern) !== false) {
                return ucfirst($lang);
            }
        }
    }
    
    return 'Hindi'; // Default
}

/**
 * Search for exact movie
 */
function search_movies_exact($name, $message_id, $channel_id) {
    $db = get_db();
    $stmt = $db->prepare("SELECT id FROM movies 
                          WHERE movie_name = :name 
                          AND message_id = :msg 
                          AND channel_id = :channel");
    $stmt->bindValue(':name', $name);
    $stmt->bindValue(':msg', $message_id);
    $stmt->bindValue(':channel', $channel_id);
    
    $result = $stmt->execute();
    return $result->fetchArray() !== false;
}

// ==============================
// 9. TELEGRAM API FUNCTIONS
// ==============================

/**
 * Make API request to Telegram
 */
function apiRequest($method, $params = []) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($params),
            'timeout' => 30,
            'ignore_errors' => true
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        bot_log("API request failed: $method", 'ERROR');
        return null;
    }
    
    return json_decode($result, true);
}

/**
 * Send message with optional delay
 */
function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = 'HTML', $delay = 0) {
    if ($delay > 0) {
        // Show typing action
        apiRequest('sendChatAction', [
            'chat_id' => $chat_id,
            'action' => 'typing'
        ]);
        usleep($delay * 1000);
    }
    
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true,
        'parse_mode' => $parse_mode
    ];
    
    if ($reply_markup) {
        $params['reply_markup'] = json_encode($reply_markup);
    }
    
    $result = apiRequest('sendMessage', $params);
    
    // Schedule auto-delete if needed
    if (strpos($text, '/') !== 0 && !$reply_markup) {
        schedule_delete($chat_id, $result['result']['message_id'] ?? 0, TEXT_DELETE_SECONDS);
    }
    
    return $result;
}

/**
 * Edit message
 */
function editMessage($chat_id, $message_id, $text, $reply_markup = null) {
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'disable_web_page_preview' => true,
        'parse_mode' => 'HTML'
    ];
    
    if ($reply_markup) {
        $params['reply_markup'] = json_encode($reply_markup);
    }
    
    return apiRequest('editMessageText', $params);
}

/**
 * Delete message
 */
function deleteMessage($chat_id, $message_id) {
    return apiRequest('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

/**
 * Answer callback query
 */
function answerCallbackQuery($callback_query_id, $text = null, $alert = false) {
    $params = [
        'callback_query_id' => $callback_query_id,
        'show_alert' => $alert
    ];
    
    if ($text) {
        $params['text'] = $text;
    }
    
    return apiRequest('answerCallbackQuery', $params);
}

/**
 * Forward message (for public channels)
 */
function forwardMessage($chat_id, $from_chat_id, $message_id) {
    return apiRequest('forwardMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

/**
 * Copy message (for private channels - no header)
 */
function copyMessage($chat_id, $from_chat_id, $message_id, $caption = null) {
    $params = [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ];
    
    if ($caption) {
        $params['caption'] = $caption;
        $params['parse_mode'] = 'HTML';
    }
    
    return apiRequest('copyMessage', $params);
}

/**
 * Schedule message deletion
 */
function schedule_delete($chat_id, $message_id, $seconds) {
    global $GLOBALS;
    
    if ($message_id && $seconds > 0) {
        $GLOBALS['delete_queue'][] = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'delete_at' => time() + $seconds
        ];
    }
}

/**
 * Process delete queue
 */
function process_delete_queue() {
    global $GLOBALS;
    
    $now = time();
    $remaining = [];
    
    foreach ($GLOBALS['delete_queue'] as $item) {
        if ($now >= $item['delete_at']) {
            deleteMessage($item['chat_id'], $item['message_id']);
        } else {
            $remaining[] = $item;
        }
    }
    
    $GLOBALS['delete_queue'] = $remaining;
}

/**
 * Create progress bar
 */
function progress_bar($current, $total, $width = 10) {
    $percentage = $total > 0 ? ($current / $total) * 100 : 0;
    $filled = round($percentage / 10);
    $empty = $width - $filled;
    
    return '▓' . str_repeat('▓', $filled) . str_repeat('░', $empty) . " {$percentage}%";
}

// ==============================
// 10. SEARCH SYSTEM (FTS5 POWERED)
// ==============================

/**
 * Search movies using FTS5
 */
function search_movies($query, $filters = []) {
    $cache_key = 'search_' . md5($query . serialize($filters));
    
    // Check cache first
    $cached = cache_get($cache_key);
    if ($cached !== null) {
        return $cached;
    }
    
    $db = get_db();
    
    // Prepare search terms for FTS5
    $terms = prepare_search_terms($query);
    
    // Build query
    $sql = "SELECT m.*, rank FROM movies m
            INNER JOIN movies_fts f ON m.id = f.rowid
            WHERE movies_fts MATCH :query";
    
    $params = [':query' => $terms];
    
    // Apply filters
    if (!empty($filters)) {
        if (isset($filters['quality']) && $filters['quality'] !== 'all') {
            $sql .= " AND m.quality = :quality";
            $params[':quality'] = $filters['quality'];
        }
        
        if (isset($filters['language']) && $filters['language'] !== 'all') {
            $sql .= " AND m.language = :language";
            $params[':language'] = ucfirst($filters['language']);
        }
        
        if (isset($filters['year'])) {
            $sql .= " AND m.year = :year";
            $params[':year'] = $filters['year'];
        }
        
        if (isset($filters['channel'])) {
            $sql .= " AND m.channel_id = :channel";
            $params[':channel'] = $filters['channel'];
        }
    }
    
    // Order by relevance
    $sql .= " ORDER BY rank LIMIT " . MAX_SEARCH_RESULTS;
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $results = [];
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $channel = get_channel($row['channel_id']);
        $row['channel_name'] = $channel['name'];
        $row['channel_emoji'] = $channel['emoji'];
        $results[] = $row;
    }
    
    // Cache results
    cache_set($cache_key, $results, 300); // 5 minutes
    
    return $results;
}

/**
 * Prepare search terms for FTS5
 */
function prepare_search_terms($query) {
    $query = trim($query);
    
    // Remove special characters
    $query = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query);
    
    // Split into words
    $words = array_filter(explode(' ', $query));
    
    // Prepare FTS5 query with NEAR and prefix matching
    $terms = [];
    foreach ($words as $word) {
        if (strlen($word) > 2) {
            $terms[] = $word . '*'; // Prefix matching
        }
    }
    
    if (count($terms) > 1) {
        // Use NEAR for phrase matching
        return '"' . implode(' ', $words) . '"';
    } else {
        return implode(' ', $terms);
    }
}

/**
 * Get trending movies
 */
function get_trending_movies($limit = 10) {
    $db = get_db();
    
    $result = $db->query("SELECT m.*, COUNT(*) as download_count 
                          FROM movies m
                          LEFT JOIN stats s ON m.id = s.movie_id
                          WHERE s.date >= date('now', '-7 days')
                          GROUP BY m.id
                          ORDER BY download_count DESC, m.downloads DESC
                          LIMIT $limit");
    
    $movies = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $channel = get_channel($row['channel_id']);
        $row['channel_name'] = $channel['name'];
        $row['channel_emoji'] = $channel['emoji'];
        $movies[] = $row;
    }
    
    return $movies;
}

/**
 * Get latest movies
 */
function get_latest_movies($limit = 10) {
    $db = get_db();
    
    $result = $db->query("SELECT * FROM movies 
                          ORDER BY added_date DESC, id DESC 
                          LIMIT $limit");
    
    $movies = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $channel = get_channel($row['channel_id']);
        $row['channel_name'] = $channel['name'];
        $row['channel_emoji'] = $channel['emoji'];
        $movies[] = $row;
    }
    
    return $movies;
}

// ==============================
// 11. USER MANAGEMENT
// ==============================

/**
 * Track user activity
 */
function track_user($user_id, $user_data = [], $action = null) {
    global $GLOBALS;
    
    // Check cache first
    if (isset($GLOBALS['user_cache'][$user_id])) {
        $user = $GLOBALS['user_cache'][$user_id];
    } else {
        // Load from database
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :id");
        $stmt->bindValue(':id', $user_id);
        $result = $stmt->execute();
        
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $user = $row;
        } else {
            // New user
            $user = [
                'user_id' => $user_id,
                'first_name' => $user_data['first_name'] ?? '',
                'last_name' => $user_data['last_name'] ?? '',
                'username' => $user_data['username'] ?? '',
                'joined' => date('Y-m-d H:i:s'),
                'last_active' => date('Y-m-d H:i:s'),
                'points' => 10, // Welcome points
                'searches' => 0,
                'downloads' => 0,
                'requests' => 0,
                'language' => 'hinglish',
                'settings' => json_encode(get_default_settings())
            ];
            
            // Insert new user
            $stmt = $db->prepare("INSERT INTO users 
                (user_id, first_name, last_name, username, joined, last_active, points, language, settings)
                VALUES (:id, :fname, :lname, :uname, :joined, :last, :points, :lang, :settings)");
            
            $stmt->bindValue(':id', $user_id);
            $stmt->bindValue(':fname', $user['first_name']);
            $stmt->bindValue(':lname', $user['last_name']);
            $stmt->bindValue(':uname', $user['username']);
            $stmt->bindValue(':joined', $user['joined']);
            $stmt->bindValue(':last', $user['last_active']);
            $stmt->bindValue(':points', $user['points']);
            $stmt->bindValue(':lang', $user['language']);
            $stmt->bindValue(':settings', $user['settings']);
            $stmt->execute();
            
            bot_log("New user: $user_id");
        }
        
        $GLOBALS['user_cache'][$user_id] = $user;
    }
    
    // Update activity
    if ($action) {
        $points_map = [
            'search' => 1,
            'download' => 3,
            'request' => 2,
            'found' => 5,
            'login' => 10
        ];
        
        $user['last_active'] = date('Y-m-d H:i:s');
        $user[$action . 's'] = ($user[$action . 's'] ?? 0) + 1;
        $user['points'] += $points_map[$action] ?? 1;
        
        // Batch update every 5 actions
        $user['_pending'] = ($user['_pending'] ?? 0) + 1;
        if ($user['_pending'] >= 5) {
            save_user($user);
            $user['_pending'] = 0;
        }
        
        $GLOBALS['user_cache'][$user_id] = $user;
    }
    
    return $user;
}

/**
 * Save user to database
 */
function save_user($user) {
    $db = get_db();
    
    $stmt = $db->prepare("UPDATE users SET 
        first_name = :fname,
        last_name = :lname,
        username = :uname,
        last_active = :last,
        points = :points,
        searches = :searches,
        downloads = :downloads,
        requests = :requests,
        language = :lang,
        settings = :settings
        WHERE user_id = :id");
    
    $stmt->bindValue(':id', $user['user_id']);
    $stmt->bindValue(':fname', $user['first_name']);
    $stmt->bindValue(':lname', $user['last_name']);
    $stmt->bindValue(':uname', $user['username']);
    $stmt->bindValue(':last', $user['last_active']);
    $stmt->bindValue(':points', $user['points']);
    $stmt->bindValue(':searches', $user['searches']);
    $stmt->bindValue(':downloads', $user['downloads']);
    $stmt->bindValue(':requests', $user['requests']);
    $stmt->bindValue(':lang', $user['language']);
    $stmt->bindValue(':settings', $user['settings']);
    
    return $stmt->execute();
}

/**
 * Get default user settings
 */
function get_default_settings() {
    return [
        'auto_scan' => true,
        'spoiler_mode' => false,
        'top_results' => 10,
        'priority_sort' => 'quality',
        'result_layout' => 'buttons',
        'language_preference' => 'all',
        'quality_preference' => 'all',
        'auto_delete' => true,
        'delete_seconds' => 120
    ];
}

/**
 * Get user settings
 */
function get_user_settings($user_id) {
    $user = track_user($user_id);
    return json_decode($user['settings'] ?? '{}', true) + get_default_settings();
}

/**
 * Update user settings
 */
function update_user_settings($user_id, $settings) {
    $current = get_user_settings($user_id);
    $updated = array_merge($current, $settings);
    
    $db = get_db();
    $stmt = $db->prepare("UPDATE users SET settings = :settings WHERE user_id = :id");
    $stmt->bindValue(':settings', json_encode($updated));
    $stmt->bindValue(':id', $user_id);
    $stmt->execute();
    
    // Clear cache
    unset($GLOBALS['user_cache'][$user_id]);
    
    return $updated;
}

/**
 * Check search cooldown
 */
function check_cooldown($user_id) {
    global $GLOBALS;
    
    $last = $GLOBALS['user_last_search'][$user_id] ?? 0;
    $elapsed = time() - $last;
    
    if ($elapsed < SEARCH_COOLDOWN) {
        $wait = SEARCH_COOLDOWN - $elapsed;
        return "⏳ Please wait {$wait} seconds!";
    }
    
    $GLOBALS['user_last_search'][$user_id] = time();
    return true;
}

/**
 * Get user rank
 */
function get_user_rank($points) {
    if ($points >= 1000) return '👑 Elite';
    if ($points >= 500) return '⭐ Pro';
    if ($points >= 250) return '🚀 Advanced';
    if ($points >= 100) return '📈 Intermediate';
    if ($points >= 50) return '🌱 Beginner';
    return '🆕 Newbie';
}

/**
 * Get leaderboard
 */
function get_leaderboard($limit = 10) {
    $db = get_db();
    
    $result = $db->query("SELECT user_id, username, first_name, points, searches, downloads 
                          FROM users 
                          WHERE points > 0 
                          ORDER BY points DESC 
                          LIMIT $limit");
    
    $users = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    
    return $users;
}

// ==============================
// 12. REQUEST SYSTEM
// ==============================

/**
 * Add movie request
 */
function add_request($user_id, $movie_name) {
    $user = track_user($user_id);
    
    // Check daily limit
    $db = get_db();
    
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM requests 
                          WHERE user_id = :uid AND date(created_at) = :today");
    $stmt->bindValue(':uid', $user_id);
    $stmt->bindValue(':today', $today);
    $result = $stmt->execute();
    $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    if ($count >= DAILY_REQUEST_LIMIT) {
        return ['success' => false, 'message' => 'daily_limit'];
    }
    
    // Add request
    $stmt = $db->prepare("INSERT INTO requests (user_id, movie_name, language) 
                          VALUES (:uid, :name, :lang)");
    $stmt->bindValue(':uid', $user_id);
    $stmt->bindValue(':name', $movie_name);
    $stmt->bindValue(':lang', detect_language_text($movie_name));
    $stmt->execute();
    
    $request_id = $db->lastInsertRowID();
    
    // Update user stats
    track_user($user_id, [], 'request');
    
    // Notify admin
    $admin_msg = "📝 <b>New Movie Request</b>\n\n";
    $admin_msg .= "Movie: $movie_name\n";
    $admin_msg .= "User: " . ($user['username'] ? "@{$user['username']}" : "ID: $user_id") . "\n";
    $admin_msg .= "Today: " . ($count + 1) . "/" . DAILY_REQUEST_LIMIT . "\n";
    $admin_msg .= "ID: $request_id";
    
    sendMessage(ADMIN_ID, $admin_msg, null, 'HTML');
    
    bot_log("Request added: $movie_name by $user_id");
    
    return ['success' => true, 'id' => $request_id];
}

/**
 * Get user requests
 */
function get_user_requests($user_id, $limit = 10) {
    $db = get_db();
    
    $stmt = $db->prepare("SELECT * FROM requests 
                          WHERE user_id = :uid 
                          ORDER BY created_at DESC 
                          LIMIT $limit");
    $stmt->bindValue(':uid', $user_id);
    
    $result = $stmt->execute();
    $requests = [];
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $requests[] = $row;
    }
    
    return $requests;
}

/**
 * Check if movie exists
 */
function movie_exists($movie_name) {
    $db = get_db();
    
    $stmt = $db->prepare("SELECT id FROM movies WHERE movie_name LIKE :name LIMIT 1");
    $stmt->bindValue(':name', '%' . $movie_name . '%');
    $result = $stmt->execute();
    
    return $result->fetchArray() !== false;
}

/**
 * Notify waiting users
 */
function notify_waiting_users($movie_name, $channel_id) {
    global $GLOBALS;
    
    $key = strtolower(trim($movie_name));
    
    if (isset($GLOBALS['waiting_users'][$key])) {
        $channel = get_channel($channel_id);
        $link = $channel['username'] ?: "channel";
        
        foreach ($GLOBALS['waiting_users'][$key] as $user_id) {
            $msg = "🎉 <b>Good News!</b>\n\n";
            $msg .= "Your requested movie <b>$movie_name</b> has been added!\n\n";
            $msg .= "📢 Join: " . ($channel['username'] ?? $channel['name']);
            
            sendMessage($user_id, $msg, null, 'HTML');
        }
        
        unset($GLOBALS['waiting_users'][$key]);
        bot_log("Notified " . count($GLOBALS['waiting_users'][$key]) . " users for $movie_name");
    }
}

// ==============================
// 13. DELIVERY SYSTEM
// ==============================

/**
 * Deliver movie to user
 */
function deliver_movie($chat_id, $movie, $user_id = null) {
    $channel_id = $movie['channel_id'];
    $message_id = $movie['message_id'];
    $has_header = channel_has_header($channel_id);
    
    // Add attribution if user provided
    $caption = null;
    if ($user_id) {
        $user = track_user($user_id);
        $username = $user['username'] ? "@{$user['username']}" : "User#$user_id";
        
        $caption = "📥 <b>Requested by:</b> $username\n";
        $caption .= "🎬 <b>Movie:</b> {$movie['movie_name']}\n";
        $caption .= "📊 <b>Quality:</b> {$movie['quality']}\n";
        $caption .= "🗣️ <b>Language:</b> {$movie['language']}\n";
        if ($movie['year']) {
            $caption .= "📅 <b>Year:</b> {$movie['year']}\n";
        }
    }
    
    // Deliver based on channel type
    if ($has_header) {
        // Public channel - forward (shows header)
        $result = forwardMessage($chat_id, $channel_id, $message_id);
    } else {
        // Private channel - copy (no header)
        $result = copyMessage($chat_id, $channel_id, $message_id, $caption);
    }
    
    // Update stats
    if ($result && isset($result['ok']) && $result['ok']) {
        update_stat('total_downloads', 1);
        if ($user_id) {
            track_user($user_id, [], 'download');
            
            // Update movie download count
            $db = get_db();
            $stmt = $db->prepare("UPDATE movies SET downloads = downloads + 1 WHERE id = :id");
            $stmt->bindValue(':id', $movie['id']);
            $stmt->execute();
        }
        
        // Schedule auto-delete
        if (get_user_settings($user_id)['auto_delete'] ?? true) {
            $msg_id = $result['result']['message_id'] ?? $result['result']['id'] ?? null;
            if ($msg_id) {
                schedule_delete($chat_id, $msg_id, FILE_DELETE_SECONDS);
            }
        }
        
        return true;
    }
    
    // Fallback to link
    $link = get_channel_link($channel_id, $message_id);
    sendMessage($chat_id, "🔗 <b>Direct Link:</b>\n$link", null, 'HTML');
    
    return false;
}

/**
 * Batch deliver movies
 */
function batch_deliver($chat_id, $movies, $user_id = null, $page = null) {
    $total = count($movies);
    if ($total === 0) return;
    
    $sent = 0;
    $failed = 0;
    
    // Progress message
    $progress_msg = sendMessage($chat_id, 
        "📦 <b>Preparing to send $total movies...</b>\n\n" . 
        progress_bar(0, $total), null, 'HTML');
    
    $progress_id = $progress_msg['result']['message_id'];
    
    foreach ($movies as $i => $movie) {
        if (deliver_movie($chat_id, $movie, $user_id)) {
            $sent++;
        } else {
            $failed++;
        }
        
        // Update progress every 2 movies
        if ($i % 2 == 0 || $i == $total - 1) {
            $percent = round(($i + 1) / $total * 100);
            editMessage($chat_id, $progress_id,
                "📦 <b>Sending movies...</b>\n\n" .
                "✅ Sent: $sent\n" .
                "❌ Failed: $failed\n" .
                "📊 Progress: $percent%\n\n" .
                progress_bar($i + 1, $total)
            );
        }
        
        usleep(300000); // 0.3 sec delay
    }
    
    // Final message
    editMessage($chat_id, $progress_id,
        "✅ <b>Batch Complete!</b>\n\n" .
        "📦 Total: $total\n" .
        "✅ Sent: $sent\n" .
        "❌ Failed: $failed\n" .
        "⏱️ Time: " . date('H:i:s')
    );
    
    // Schedule progress deletion
    schedule_delete($chat_id, $progress_id, 60);
}

// ==============================
// 14. PAGINATION SYSTEM
// ==============================

/**
 * Paginate movies
 */
function paginate($items, $page = 1, $per_page = ITEMS_PER_PAGE) {
    $total = count($items);
    $total_pages = ceil($total / $per_page);
    $page = max(1, min($page, $total_pages));
    
    $offset = ($page - 1) * $per_page;
    $slice = array_slice($items, $offset, $per_page);
    
    return [
        'items' => $slice,
        'total' => $total,
        'page' => $page,
        'total_pages' => $total_pages,
        'has_prev' => $page > 1,
        'has_next' => $page < $total_pages,
        'start' => $offset + 1,
        'end' => min($offset + $per_page, $total)
    ];
}

/**
 * Build pagination keyboard
 */
function pagination_keyboard($page, $total_pages, $session_id, $filters = []) {
    $keyboard = ['inline_keyboard' => []];
    
    // Navigation row
    $nav_row = [];
    
    if ($page > 1) {
        $nav_row[] = ['text' => '⏮️ First', 'callback_data' => "page_1_{$session_id}"];
        $nav_row[] = ['text' => '◀️ Prev', 'callback_data' => "page_" . ($page - 1) . "_{$session_id}"];
    }
    
    $nav_row[] = ['text' => "📌 {$page}/{$total_pages}", 'callback_data' => 'current'];
    
    if ($page < $total_pages) {
        $nav_row[] = ['text' => 'Next ▶️', 'callback_data' => "page_" . ($page + 1) . "_{$session_id}"];
        $nav_row[] = ['text' => 'Last ⏭️', 'callback_data' => "page_{$total_pages}_{$session_id}"];
    }
    
    $keyboard['inline_keyboard'][] = $nav_row;
    
    // Action row
    $action_row = [
        ['text' => '📦 Send All', 'callback_data' => "sendall_{$page}_{$session_id}"],
        ['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''],
        ['text' => '❌ Close', 'callback_data' => "close_{$session_id}"]
    ];
    
    $keyboard['inline_keyboard'][] = $action_row;
    
    // Filter row (if no filters active)
    if (empty($filters)) {
        $filter_row = [
            ['text' => '🎬 HD Only', 'callback_data' => "filter_hd_{$session_id}"],
            ['text' => '🎭 Theater', 'callback_data' => "filter_theater_{$session_id}"],
            ['text' => '🗣️ Hindi', 'callback_data' => "filter_hindi_{$session_id}"]
        ];
        $keyboard['inline_keyboard'][] = $filter_row;
    } else {
        $filter_row = [
            ['text' => '🧹 Clear Filter', 'callback_data' => "filter_clear_{$session_id}"]
        ];
        $keyboard['inline_keyboard'][] = $filter_row;
    }
    
    return $keyboard;
}

/**
 * Show movie browser
 */
function show_browser($chat_id, $page = 1, $filters = [], $session_id = null) {
    $movies = load_movies();
    
    if (empty($movies)) {
        sendMessage($chat_id, "📭 No movies yet!");
        return;
    }
    
    // Apply filters
    if (!empty($filters)) {
        $movies = array_filter($movies, function($m) use ($filters) {
            foreach ($filters as $key => $value) {
                if ($key == 'hd' && strpos($m['quality'], '1080') === false) return false;
                if ($key == 'theater' && $m['quality'] != 'Theater') return false;
                if ($key == 'hindi' && $m['language'] != 'Hindi') return false;
            }
            return true;
        });
    }
    
    // Generate session
    if (!$session_id) {
        $session_id = uniqid('browse_', true);
        global $GLOBALS;
        $GLOBALS['pagination_sessions'][$session_id] = [
            'filters' => $filters,
            'created' => time()
        ];
    }
    
    $p = paginate($movies, $page);
    
    // Build message
    $msg = "🎬 <b>Movie Browser</b>\n\n";
    $msg .= "📊 Total: {$p['total']} movies\n";
    $msg .= "📄 Page: {$p['page']}/{$p['total_pages']}\n";
    $msg .= "📋 Items: {$p['start']}-{$p['end']}\n\n";
    
    if (!empty($filters)) {
        $msg .= "🔍 <b>Active Filters:</b> " . implode(', ', array_keys($filters)) . "\n\n";
    }
    
    foreach ($p['items'] as $i => $movie) {
        $num = $p['start'] + $i;
        $msg .= "{$num}. {$movie['channel_emoji']} <b>{$movie['movie_name']}</b>\n";
        $msg .= "   🏷️ {$movie['quality']} | 🗣️ {$movie['language']}";
        if ($movie['year']) {
            $msg .= " | 📅 {$movie['year']}";
        }
        $msg .= "\n\n";
    }
    
    $keyboard = pagination_keyboard($p['page'], $p['total_pages'], $session_id, $filters);
    
    // Delete old message if exists
    if (isset($GLOBALS['pagination_sessions'][$session_id]['last_msg'])) {
        deleteMessage($chat_id, $GLOBALS['pagination_sessions'][$session_id]['last_msg']);
    }
    
    $result = sendMessage($chat_id, $msg, $keyboard, 'HTML');
    $GLOBALS['pagination_sessions'][$session_id]['last_msg'] = $result['result']['message_id'] ?? 0;
}

// ==============================
// 15. STATISTICS SYSTEM
// ==============================

/**
 * Update statistics
 */
function update_stat($key, $increment = 1) {
    $db = get_db();
    
    // Update main stats
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats[$key] = ($stats[$key] ?? 0) + $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
    
    // Update daily stats
    $today = date('Y-m-d');
    $stmt = $db->prepare("INSERT INTO stats (date, searches, downloads, users, requests)
                          VALUES (:date, 0, 0, 0, 0)
                          ON CONFLICT(date) DO UPDATE SET 
                          $key = $key + :inc");
    $stmt->bindValue(':date', $today);
    $stmt->bindValue(':inc', $increment);
    $stmt->execute();
}

/**
 * Get system stats
 */
function get_system_stats() {
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $db = get_db();
    
    // Get today's stats
    $today = date('Y-m-d');
    $result = $db->query("SELECT * FROM stats WHERE date = '$today'");
    $today_stats = $result->fetchArray(SQLITE3_ASSOC) ?: [];
    
    // Get user count
    $result = $db->query("SELECT COUNT(*) as count FROM users");
    $user_count = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // Get active users (last hour)
    $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $result = $db->query("SELECT COUNT(*) as count FROM users WHERE last_active > '$hour_ago'");
    $active_now = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // Get request counts
    $result = $db->query("SELECT COUNT(*) as count FROM requests WHERE status = 'pending'");
    $pending_requests = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    return [
        'total_movies' => $stats['total_movies'] ?? 0,
        'total_users' => $user_count,
        'total_searches' => $stats['total_searches'] ?? 0,
        'total_downloads' => $stats['total_downloads'] ?? 0,
        'success_rate' => calculate_success_rate($stats),
        'active_now' => $active_now,
        'today_searches' => $today_stats['searches'] ?? 0,
        'today_downloads' => $today_stats['downloads'] ?? 0,
        'today_users' => $today_stats['users'] ?? 0,
        'pending_requests' => $pending_requests,
        'uptime' => format_uptime(),
        'memory_usage' => memory_get_usage(true) / 1024 / 1024,
        'peak_memory' => memory_get_peak_usage(true) / 1024 / 1024
    ];
}

/**
 * Calculate success rate
 */
function calculate_success_rate($stats) {
    $total = ($stats['successful_searches'] ?? 0) + ($stats['failed_searches'] ?? 0);
    if ($total == 0) return 100;
    return round(($stats['successful_searches'] ?? 0) / $total * 100, 1);
}

/**
 * Format uptime
 */
function format_uptime() {
    global $GLOBALS;
    $seconds = time() - $GLOBALS['start_time'];
    
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    return "{$days}d {$hours}h {$minutes}m";
}

// ==============================
// 16. HINGLISH LANGUAGE SYSTEM
// ==============================

/**
 * Hinglish Processor Class
 */
class HinglishProcessor {
    private $responses = [
        'welcome' => [
            'hindi' => "🎬 वेलकम बॉस! कौन सी मूवी देखनी है?",
            'hinglish' => "🎬 Welcome boss! Kaun si movie dekhni hai?",
            'english' => "🎬 Welcome! Which movie would you like?"
        ],
        'searching' => [
            'hindi' => "🔍 ढूंढ रहा हूं... जरा रुको",
            'hinglish' => "🔍 Dhoondh raha hoon... Zara ruko",
            'english' => "🔍 Searching... Please wait"
        ],
        'found' => [
            'hindi' => "✅ मिल गई! भेज रहा हूं",
            'hinglish' => "✅ Mil gayi! Bhej raha hoon",
            'english' => "✅ Found it! Sending now"
        ],
        'not_found' => [
            'hindi' => "😔 ये मूवी अभी नहीं है। रिक्वेस्ट कर सकते हो",
            'hinglish' => "😔 Yeh movie abhi nahi hai. Request kar sakte ho",
            'english' => "😔 Movie not available. You can request it"
        ],
        'multiple' => [
            'hindi' => "🎯 कई वर्जन मिले! कौन सा चाहिए?",
            'hinglish' => "🎯 Kai versions mile! Kaun sa chahiye?",
            'english' => "🎯 Multiple versions! Which one?"
        ],
        'request_sent' => [
            'hindi' => "✅ रिक्वेस्ट मिल गई! जल्दी डालेंगे",
            'hinglish' => "✅ Request mil gayi! Jaldi daalenge",
            'english' => "✅ Request received! Will add soon"
        ],
        'limit_reached' => [
            'hindi' => "❌ आज के लिए बस " . DAILY_REQUEST_LIMIT . " रिक्वेस्ट कर सकते हो",
            'hinglish' => "❌ Aaj ke liye bas " . DAILY_REQUEST_LIMIT . " request kar sakte ho",
            'english' => "❌ Daily limit of " . DAILY_REQUEST_LIMIT . " reached"
        ],
        'cooldown' => [
            'hindi' => "⏳ थोड़ा रुको... %d सेकंड में फिर सर्च करो",
            'hinglish' => "⏳ Thoda ruko... %d second mein phir search karo",
            'english' => "⏳ Please wait %d seconds..."
        ],
        'error' => [
            'hindi' => "❌ कुछ गड़बड़ हो गई। फिर से कोशिश करो",
            'hinglish' => "❌ Kuch gadbad ho gayi. Phir se koshish karo",
            'english' => "❌ Something went wrong. Try again"
        ],
        'help' => [
            'hindi' => "🆘 मदद चाहिए? /help दबाओ",
            'hinglish' => "🆘 Madad chahiye? /help dabao",
            'english' => "🆘 Need help? Press /help"
        ],
        'stats' => [
            'hindi' => "📊 तुम्हारे आंकड़े:",
            'hinglish' => "📊 Tumhare stats:",
            'english' => "📊 Your stats:"
        ]
    ];
    
    private $hindi_keywords = [
        'है', 'हैं', 'का', 'की', 'के', 'में', 'से', 'को', 'नहीं', 'बहुत',
        'अच्छा', 'बुरा', 'क्या', 'क्यों', 'कैसे', 'कहाँ', 'कब', 'कौन',
        'मैं', 'तुम', 'हम', 'आप', 'यह', 'वह', 'ये', 'वे'
    ];
    
    private $hinglish_keywords = [
        'hai', 'hain', 'ka', 'ki', 'ke', 'mein', 'se', 'ko', 'nahi', 'bahut',
        'acha', 'bura', 'kya', 'kyon', 'kaise', 'kahan', 'kab', 'kaun',
        'main', 'tum', 'hum', 'aap', 'yeh', 'woh', 'ye', 'wo'
    ];
    
    /**
     * Detect language mode
     */
    function detect_mode($text, $user_id = null) {
        // Check user preference first
        if ($user_id) {
            $settings = get_user_settings($user_id);
            if (isset($settings['language'])) {
                return $settings['language'];
            }
        }
        
        // Check for Hindi script
        if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) {
            return 'hindi';
        }
        
        // Count Hinglish words
        $text = strtolower($text);
        $hinglish_score = 0;
        $english_score = 0;
        
        foreach ($this->hinglish_keywords as $word) {
            if (strpos($text, $word) !== false) {
                $hinglish_score++;
            }
        }
        
        // Simple English words
        $english_words = ['the', 'is', 'are', 'was', 'were', 'have', 'has'];
        foreach ($english_words as $word) {
            if (strpos($text, ' ' . $word . ' ') !== false) {
                $english_score++;
            }
        }
        
        if ($hinglish_score > $english_score) {
            return 'hinglish';
        } elseif ($english_score > $hinglish_score) {
            return 'english';
        }
        
        return 'hinglish'; // Default
    }
    
    /**
     * Get response in appropriate language
     */
    function get_response($key, $mode = 'auto', $user_id = null, $params = []) {
        if ($mode == 'auto') {
            $mode = $this->detect_mode('', $user_id);
        }
        
        $response = $this->responses[$key][$mode] ?? $this->responses[$key]['hinglish'];
        
        // Replace parameters
        if (!empty($params)) {
            $response = vsprintf($response, $params);
        }
        
        return $response;
    }
    
    /**
     * Translate text to Hinglish (basic)
     */
    function translate($text, $target = 'hinglish') {
        if ($target == 'hinglish' && $this->is_hindi_script($text)) {
            // Very basic Hindi to Hinglish transliteration
            $map = [
                'का' => 'ka',
                'की' => 'ki',
                'के' => 'ke',
                'में' => 'mein',
                'से' => 'se',
                'को' => 'ko',
                'है' => 'hai',
                'हैं' => 'hain',
                'नहीं' => 'nahi',
                'और' => 'aur',
                'मैं' => 'main',
                'तुम' => 'tum',
                'हम' => 'hum',
                'आप' => 'aap',
                'यह' => 'yeh',
                'वह' => 'woh'
            ];
            
            return str_replace(array_keys($map), array_values($map), $text);
        }
        
        return $text;
    }
    
    /**
     * Check if text contains Hindi script
     */
    function is_hindi_script($text) {
        return preg_match('/[\x{0900}-\x{097F}]/u', $text);
    }
}

// Initialize Hinglish processor
$hinglish = new HinglishProcessor();

// ==============================
// 17. AUTO-RESPONSE SYSTEM (AI-POWERED)
// ==============================

/**
 * AutoResponse Engine Class
 */
class AutoResponseEngine {
    private $patterns = [
        // Greetings
        '/^(hi|hello|hey|hii|heyy|hlo|hola)/i' => [
            'response' => 'greeting',
            'priority' => 10
        ],
        '/^(good morning|gm|suprabhat|good afternoon|good evening)/i' => [
            'response' => 'time_greeting',
            'priority' => 9
        ],
        '/^(good night|gn|shubh ratri|sweet dreams)/i' => [
            'response' => 'night_greeting',
            'priority' => 9
        ],
        
        // Thanks
        '/thank(s| you| u| you so much| thanks a lot)/i' => [
            'response' => 'thanks',
            'priority' => 8
        ],
        
        // Farewell
        '/bye|byee|tata|allahafiz|khuda hafiz|see you|cya/i' => [
            'response' => 'bye',
            'priority' => 8
        ],
        
        // Bot status
        '/how are you|kaise ho|kya haal|how r u|how doin/i' => [
            'response' => 'status',
            'priority' => 7
        ],
        
        // Movie related
        '/(movie|film|picture|film) (hai|hain|kahan|kaise|kyun)/i' => [
            'response' => 'movie_help',
            'priority' => 6
        ],
        '/(download|send|bhej|de do|do) (karo|kar do)/i' => [
            'response' => 'download_help',
            'priority' => 6
        ],
        
        // Help
        '/(help|madad|sahayata|guide)/i' => [
            'response' => 'help',
            'priority' => 5
        ],
        
        // Love/Appreciation
        '/(love you|i love you|love u|❤️|♥️|great bot|awesome bot)/i' => [
            'response' => 'love',
            'priority' => 4
        ],
        
        // Abuse/Swear (ignore)
        '/(fuck|bc|mc|randi|bhosd|gand|madarchod|behenchod)/i' => [
            'response' => 'ignore',
            'priority' => 0
        ]
    ];
    
    private $responses = [
        'greeting' => [
            'hindi' => "👋 नमस्ते! कौन सी मूवी देखनी है?",
            'hinglish' => "👋 Namaste! Kaun si movie dekhni hai?",
            'english' => "👋 Hello! Which movie would you like?"
        ],
        'time_greeting' => [
            'hindi' => "🌅 नमस्ते! आज का दिन शुभ हो। मूवी का नाम बताओ!",
            'hinglish' => "🌅 Namaste! Aaj ka din shubh ho. Movie ka naam batao!",
            'english' => "🌅 Good day! Tell me the movie name!"
        ],
        'night_greeting' => [
            'hindi' => "🌙 शुभ रात्रि! सोने से पहले मूवी देखोगे?",
            'hinglish' => "🌙 Shubh ratri! Sone se pehle movie dekhoge?",
            'english' => "🌙 Good night! Movie before sleep?"
        ],
        'thanks' => [
            'hindi' => "🙏 आपका स्वागत है! और मूवी चाहिए?",
            'hinglish' => "🙏 Aapka swagat hai! Aur movie chahiye?",
            'english' => "🙏 You're welcome! Another movie?"
        ],
        'bye' => [
            'hindi' => "👋 फिर मिलेंगे! कभी भी मूवी चाहिए तो आ जाना",
            'hinglish' => "👋 Phir milenge! Kabhi bhi movie chahiye to aa jana",
            'english' => "👋 See you! Come back anytime for movies"
        ],
        'status' => [
            'hindi' => "🤖 मैं मस्त हूं! आप बताओ? मूवी का नाम लिखो",
            'hinglish' => "🤖 Main mast hoon! Aap batao? Movie ka naam likho",
            'english' => "🤖 I'm great! You tell me the movie name?"
        ],
        'movie_help' => [
            'hindi' => "🎬 बस मूवी का नाम लिखो, मैं ढूंढ दूंगा!",
            'hinglish' => "🎬 Bas movie ka naam likho, main dhoond dunga!",
            'english' => "🎬 Just type the movie name, I'll find it!"
        ],
        'download_help' => [
            'hindi' => "📥 पहले मूवी का नाम बताओ, फिर डाउनलोड करो",
            'hinglish' => "📥 Pehle movie ka naam batao, phir download karo",
            'english' => "📥 First tell the movie name, then download"
        ],
        'help' => [
            'hindi' => "🆘 /help दबाओ, सारी कमांड्स मिल जाएंगी",
            'hinglish' => "🆘 /help dabao, saari commands mil jayengi",
            'english' => "🆘 Press /help for all commands"
        ],
        'love' => [
            'hindi' => "❤️ धन्यवाद! ऐसे ही सपोर्ट करते रहो",
            'hinglish' => "❤️ Dhanyavaad! Aise hi support karte raho",
            'english' => "❤️ Thank you! Keep supporting"
        ],
        'ignore' => [
            'hindi' => "🙏 कृपया सभ्य भाषा का प्रयोग करें",
            'hinglish' => "🙏 Please sabhya bhasha ka prayog karein",
            'english' => "🙏 Please use polite language"
        ],
        'default' => [
            'hindi' => "🎬 मूवी का नाम लिखिए...",
            'hinglish' => "🎬 Movie ka naam likhiye...",
            'english' => "🎬 Write the movie name..."
        ]
    ];
    
    private $learned_responses = [];
    
    function __construct() {
        $this->load_learned_responses();
    }
    
    /**
     * Load learned responses from database
     */
    function load_learned_responses() {
        $db = get_db();
        $result = $db->query("SELECT query, response FROM learning ORDER BY count DESC, last_used DESC LIMIT 100");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $this->learned_responses[$row['query']] = $row['response'];
        }
    }
    
    /**
     * Process message and get auto-response
     */
    function process($text, $user_id, $mode = 'hinglish') {
        $text = trim($text);
        
        // Skip commands
        if (strpos($text, '/') === 0) {
            return null;
        }
        
        // Skip if too short
        if (strlen($text) < 2) {
            return null;
        }
        
        // Check learned responses first
        foreach ($this->learned_responses as $query => $response) {
            if (strpos($text, $query) !== false) {
                $this->update_learning($query);
                return $response;
            }
        }
        
        // Check patterns
        $matched = null;
        $highest_priority = 0;
        
        foreach ($this->patterns as $pattern => $data) {
            if (preg_match($pattern, $text)) {
                if ($data['priority'] > $highest_priority) {
                    $matched = $data['response'];
                    $highest_priority = $data['priority'];
                }
            }
        }
        
        if ($matched && $matched != 'ignore') {
            $response = $this->responses[$matched][$mode] ?? $this->responses[$matched]['hinglish'];
            
            // Learn this response
            $this->learn($text, $response);
            
            return $response;
        }
        
        return null;
    }
    
    /**
     * Learn a new response
     */
    function learn($query, $response) {
        $db = get_db();
        
        $stmt = $db->prepare("INSERT INTO learning (query, response, count) 
                              VALUES (:q, :r, 1)
                              ON CONFLICT(query) DO UPDATE SET 
                              count = count + 1,
                              last_used = CURRENT_TIMESTAMP");
        $stmt->bindValue(':q', $query);
        $stmt->bindValue(':r', $response);
        $stmt->execute();
        
        // Update cache
        $this->learned_responses[$query] = $response;
    }
    
    /**
     * Update learning count
     */
    function update_learning($query) {
        $db = get_db();
        $db->exec("UPDATE learning SET count = count + 1, last_used = CURRENT_TIMESTAMP WHERE query = '$query'");
    }
}

// Initialize AutoResponse
$autoResponder = new AutoResponseEngine();

// ==============================
// 18. BACKUP SYSTEM
// ==============================

/**
 * Create backup
 */
function create_backup($type = 'full') {
    $timestamp = date('Y-m-d_H-i-s');
    $backup_id = uniqid('backup_');
    
    bot_log("Starting $type backup: $backup_id");
    
    $backup_dir = BACKUP_DIR . $timestamp . '_' . $backup_id . '/';
    mkdir($backup_dir, 0777, true);
    
    $files = ($type == 'full') 
        ? [CSV_FILE, USERS_FILE, STATS_FILE, REQUEST_FILE, LOG_FILE, DB_FILE]
        : [CSV_FILE, USERS_FILE, DB_FILE];
    
    $backed_up = [];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            $dest = $backup_dir . basename($file);
            if (copy($file, $dest)) {
                $backed_up[] = $file;
                bot_log("Backed up: $file");
            }
        }
    }
    
    // Create summary
    $summary = "Backup ID: $backup_id\n";
    $summary .= "Type: $type\n";
    $summary .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "Files: " . implode(', ', $backed_up) . "\n";
    $summary .= "Size: " . format_bytes(get_dir_size($backup_dir));
    
    file_put_contents($backup_dir . 'summary.txt', $summary);
    
    // Upload to backup channel
    upload_backup_to_channel($backup_dir, $backup_id, $summary);
    
    // Clean old backups
    if ($type == 'full') {
        clean_old_backups(7);
    }
    
    bot_log("Backup completed: $backup_id");
    
    return [
        'id' => $backup_id,
        'path' => $backup_dir,
        'files' => $backed_up,
        'size' => get_dir_size($backup_dir)
    ];
}

/**
 * Upload backup to channel
 */
function upload_backup_to_channel($backup_dir, $backup_id, $summary) {
    $channel_id = BACKUP_CHANNEL_ID;
    
    // Send summary first
    sendMessage($channel_id, 
        "💾 <b>Backup Created</b>\n\n" .
        "🆔 ID: <code>$backup_id</code>\n" .
        "📅 Time: " . date('Y-m-d H:i:s') . "\n" .
        "📊 " . str_replace("\n", "\n", $summary),
        null, 'HTML'
    );
    
    // Upload files
    $files = glob($backup_dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $filename = basename($file);
            $caption = "💾 Backup File: $filename\n";
            $caption .= "🆔 ID: $backup_id\n";
            $caption .= "📅 " . date('Y-m-d H:i:s');
            
            // Use CURL for file upload
            $post_fields = [
                'chat_id' => $channel_id,
                'document' => new CURLFile($file),
                'caption' => $caption,
                'parse_mode' => 'HTML'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_exec($ch);
            curl_close($ch);
            
            sleep(1); // Rate limiting
        }
    }
}

/**
 * Clean old backups
 */
function clean_old_backups($keep = 7) {
    $backups = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    
    if (count($backups) > $keep) {
        // Sort by date (oldest first)
        usort($backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $to_delete = array_slice($backups, 0, count($backups) - $keep);
        
        foreach ($to_delete as $backup) {
            delete_directory($backup);
            bot_log("Deleted old backup: $backup");
        }
    }
}

/**
 * Delete directory recursively
 */
function delete_directory($dir) {
    if (!file_exists($dir)) return true;
    
    if (!is_dir($dir)) return unlink($dir);
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!delete_directory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    
    return rmdir($dir);
}

/**
 * Get directory size
 */
function get_dir_size($dir) {
    $size = 0;
    foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $file) {
        $size += is_file($file) ? filesize($file) : get_dir_size($file);
    }
    return $size;
}

/**
 * Format bytes
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// ==============================
// 19. ADMIN FUNCTIONS
// ==============================

/**
 * Show admin dashboard
 */
function admin_dashboard($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only.");
        return;
    }
    
    $stats = get_system_stats();
    
    $msg = "👑 <b>ENTERTAINMENT TADKA ADMIN</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $msg .= "📊 <b>SYSTEM STATS</b>\n";
    $msg .= "🎬 Movies: {$stats['total_movies']}\n";
    $msg .= "👥 Users: {$stats['total_users']}\n";
    $msg .= "🔍 Searches: {$stats['total_searches']}\n";
    $msg .= "📥 Downloads: {$stats['total_downloads']}\n";
    $msg .= "✅ Success Rate: {$stats['success_rate']}%\n\n";
    
    $msg .= "📈 <b>TODAY</b>\n";
    $msg .= "👥 Active: {$stats['active_now']}\n";
    $msg .= "🔍 Searches: {$stats['today_searches']}\n";
    $msg .= "📥 Downloads: {$stats['today_downloads']}\n";
    $msg .= "📝 Pending: {$stats['pending_requests']}\n\n";
    
    $msg .= "🖥️ <b>SYSTEM</b>\n";
    $msg .= "⏱️ Uptime: {$stats['uptime']}\n";
    $msg .= "💾 Memory: " . round($stats['memory_usage'], 1) . " MB\n";
    $msg .= "⚡ Peak: " . round($stats['peak_memory'], 1) . " MB\n";
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '📊 Full Stats', 'callback_data' => 'admin_stats'],
             ['text' => '👥 Users', 'callback_data' => 'admin_users']],
            [['text' => '🎬 Movies', 'callback_data' => 'admin_movies'],
             ['text' => '📝 Requests', 'callback_data' => 'admin_requests']],
            [['text' => '💾 Backup', 'callback_data' => 'admin_backup'],
             ['text' => '📢 Broadcast', 'callback_data' => 'admin_broadcast']],
            [['text' => '⚙️ Config', 'callback_data' => 'admin_config'],
             ['text' => '📋 Logs', 'callback_data' => 'admin_logs']]
        ]
    ];
    
    sendMessage($chat_id, $msg, $keyboard, 'HTML');
}

/**
 * Show user list (admin)
 */
function admin_users($chat_id, $page = 1) {
    if ($chat_id != ADMIN_ID) return;
    
    $db = get_db();
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    $result = $db->query("SELECT * FROM users ORDER BY points DESC LIMIT $per_page OFFSET $offset");
    
    $msg = "👥 <b>USER LIST (Page $page)</b>\n\n";
    
    while ($user = $result->fetchArray(SQLITE3_ASSOC)) {
        $last = date('H:i', strtotime($user['last_active']));
        $msg .= "🆔 <code>{$user['user_id']}</code>\n";
        $msg .= "📛 {$user['first_name']} {$user['last_name']}\n";
        if ($user['username']) {
            $msg .= "📢 @{$user['username']}\n";
        }
        $msg .= "⭐ Points: {$user['points']} | 🔍 {$user['searches']} | 📥 {$user['downloads']}\n";
        $msg .= "🕐 Last: $last\n\n";
    }
    
    // Count total
    $total = $db->querySingle("SELECT COUNT(*) FROM users");
    $pages = ceil($total / $per_page);
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '⬅️ Prev', 'callback_data' => 'admin_users_' . ($page - 1)],
             ['text' => "$page/$pages", 'callback_data' => 'current'],
             ['text' => 'Next ➡️', 'callback_data' => 'admin_users_' . ($page + 1)]],
            [['text' => '🔙 Back', 'callback_data' => 'admin_back']]
        ]
    ];
    
    sendMessage($chat_id, $msg, $keyboard, 'HTML');
}

/**
 * Show movie list (admin)
 */
function admin_movies($chat_id, $page = 1) {
    if ($chat_id != ADMIN_ID) return;
    
    $db = get_db();
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    $result = $db->query("SELECT * FROM movies ORDER BY added_date DESC LIMIT $per_page OFFSET $offset");
    
    $msg = "🎬 <b>MOVIE LIST (Page $page)</b>\n\n";
    
    while ($movie = $result->fetchArray(SQLITE3_ASSOC)) {
        $channel = get_channel($movie['channel_id']);
        $msg .= "📌 {$movie['id']}. <b>{$movie['movie_name']}</b>\n";
        $msg .= "   {$channel['emoji']} {$channel['name']}\n";
        $msg .= "   🆔 {$movie['message_id']} | 📥 {$movie['downloads']}\n";
        $msg .= "   📅 " . date('d-m-Y', strtotime($movie['added_date'])) . "\n\n";
    }
    
    // Count total
    $total = $db->querySingle("SELECT COUNT(*) FROM movies");
    $pages = ceil($total / $per_page);
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '⬅️ Prev', 'callback_data' => 'admin_movies_' . ($page - 1)],
             ['text' => "$page/$pages", 'callback_data' => 'current'],
             ['text' => 'Next ➡️', 'callback_data' => 'admin_movies_' . ($page + 1)]],
            [['text' => '➕ Add Movie', 'callback_data' => 'admin_add_movie'],
             ['text' => '🔙 Back', 'callback_data' => 'admin_back']]
        ]
    ];
    
    sendMessage($chat_id, $msg, $keyboard, 'HTML');
}

/**
 * Show pending requests (admin)
 */
function admin_requests($chat_id) {
    if ($chat_id != ADMIN_ID) return;
    
    $db = get_db();
    $result = $db->query("SELECT r.*, u.username, u.first_name 
                          FROM requests r
                          JOIN users u ON r.user_id = u.user_id
                          WHERE r.status = 'pending'
                          ORDER BY r.votes DESC, r.created_at ASC");
    
    $msg = "📝 <b>PENDING REQUESTS</b>\n\n";
    $count = 0;
    
    while ($req = $result->fetchArray(SQLITE3_ASSOC)) {
        $count++;
        $user = $req['username'] ? "@{$req['username']}" : $req['first_name'];
        $msg .= "{$count}. <b>{$req['movie_name']}</b>\n";
        $msg .= "   👤 $user | 🗳️ {$req['votes']} votes\n";
        $msg .= "   🆔 {$req['id']} | 📅 " . date('d-m', strtotime($req['created_at'])) . "\n\n";
    }
    
    if ($count == 0) {
        $msg .= "No pending requests!";
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '✅ Mark Completed', 'callback_data' => 'admin_complete_request'],
             ['text' => '🔙 Back', 'callback_data' => 'admin_back']]
        ]
    ];
    
    sendMessage($chat_id, $msg, $keyboard, 'HTML');
}

/**
 * Broadcast message to users (admin)
 */
function admin_broadcast($chat_id, $message, $target = 'all') {
    if ($chat_id != ADMIN_ID) return;
    
    $db = get_db();
    
    // Get target users
    if ($target == 'all') {
        $users = $db->query("SELECT user_id FROM users");
    } elseif ($target == 'active') {
        $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $users = $db->query("SELECT user_id FROM users WHERE last_active > '$hour_ago'");
    } elseif ($target == 'inactive') {
        $week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
        $users = $db->query("SELECT user_id FROM users WHERE last_active < '$week_ago'");
    } else {
        $users = $db->query("SELECT user_id FROM users");
    }
    
    $sent = 0;
    $failed = 0;
    $total = 0;
    
    // Count first
    while ($users->fetchArray()) $total++;
    $users->reset();
    
    $progress = sendMessage($chat_id, "📢 Broadcasting to $total users...\n\n" . progress_bar(0, $total));
    $progress_id = $progress['result']['message_id'];
    
    $i = 0;
    while ($user = $users->fetchArray(SQLITE3_ASSOC)) {
        if (sendMessage($user['user_id'], $message, null, 'HTML')) {
            $sent++;
        } else {
            $failed++;
        }
        
        $i++;
        if ($i % 10 == 0) {
            editMessage($chat_id, $progress_id,
                "📢 Broadcasting...\n\n" .
                "✅ Sent: $sent\n" .
                "❌ Failed: $failed\n" .
                "📊 Progress: " . round($i / $total * 100) . "%\n\n" .
                progress_bar($i, $total)
            );
        }
        
        usleep(100000); // 0.1 sec delay
    }
    
    editMessage($chat_id, $progress_id,
        "✅ <b>Broadcast Complete!</b>\n\n" .
        "📊 Total: $total\n" .
        "✅ Sent: $sent\n" .
        "❌ Failed: $failed"
    );
}

// ==============================
// 20. COMMAND HANDLER
// ==============================

/**
 * Handle all commands
 */
function handle_command($chat_id, $user_id, $command, $params = []) {
    global $hinglish, $autoResponder;
    
    // Track user
    track_user($user_id, $GLOBALS['user_data'] ?? []);
    
    // Detect language mode
    $mode = $hinglish->detect_mode('', $user_id);
    
    switch ($command) {
        // ===== USER COMMANDS =====
        case '/start':
            $msg = $hinglish->get_response('welcome', $mode) . "\n\n";
            $msg .= "📢 <b>Join Our Channels:</b>\n";
            $msg .= "🎬 " . MAIN_CHANNEL . "\n";
            $msg .= "🎭 " . THEATER_CHANNEL . "\n";
            $msg .= "💾 " . BACKUP_CHANNEL_USERNAME . "\n";
            $msg .= "📝 " . REQUEST_CHANNEL . "\n\n";
            $msg .= "🔍 <b>Examples:</b>\n";
            $msg .= "• kgf 2\n";
            $msg .= "• pushpa theater\n";
            $msg .= "• avengers 2019\n\n";
            $msg .= "❓ /help for all commands";
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''],
                     ['text' => '📢 Channels', 'callback_data' => 'channels']],
                    [['text' => '❓ Help', 'callback_data' => 'help']]
                ]
            ];
            
            sendMessage($chat_id, $msg, $keyboard, 'HTML', 1000);
            track_user($user_id, [], 'login');
            break;
            
        case '/help':
            $msg = "🤖 <b>Entertainment Tadka Bot</b>\n\n";
            $msg .= "<b>User Commands:</b>\n";
            $msg .= "/start - Welcome\n";
            $msg .= "/help - This help\n";
            $msg .= "/search [movie] - Search movies\n";
            $msg .= "/browse - Browse all\n";
            $msg .= "/latest - Latest movies\n";
            $msg .= "/trending - Trending\n";
            $msg .= "/theater - Theater prints\n";
            $msg .= "/request [movie] - Request movie\n";
            $msg .= "/stats - Your stats\n";
            $msg .= "/top - Leaderboard\n";
            $msg .= "/settings - Preferences\n";
            $msg .= "/channels - Join channels\n\n";
            
            if ($chat_id == ADMIN_ID) {
                $msg .= "<b>Admin Commands:</b>\n";
                $msg .= "/admin - Dashboard\n";
                $msg .= "/backup - Create backup\n";
                $msg .= "/add - Add movie\n";
                $msg .= "/broadcast - Mass message\n";
                $msg .= "/maintenance - Toggle mode\n";
                $msg .= "/cleanup - Clean system\n";
            }
            
            sendMessage($chat_id, $msg, null, 'HTML');
            break;
            
        case '/search':
        case '/s':
            $query = implode(' ', $params);
            if (empty($query)) {
                sendMessage($chat_id, "❌ " . $hinglish->get_response('movie_help', $mode));
                return;
            }
            
            // Check cooldown
            $cooldown = check_cooldown($user_id);
            if (is_string($cooldown)) {
                sendMessage($chat_id, $cooldown);
                return;
            }
            
            sendMessage($chat_id, $hinglish->get_response('searching', $mode), null, 'HTML', 1000);
            
            // Search
            $results = search_movies($query);
            
            if (empty($results)) {
                sendMessage($chat_id, $hinglish->get_response('not_found', $mode));
                
                // Auto-suggest request
                $keyboard = [
                    'inline_keyboard' => [[
                        ['text' => '📝 Request This', 'callback_data' => 'req_' . base64_encode($query)]
                    ]]
                ];
                sendMessage($chat_id, "💡 Click to request:", $keyboard);
                
                update_stat('failed_searches', 1);
                track_user($user_id, [], 'search');
                
                // Add to waiting list
                $key = strtolower($query);
                $GLOBALS['waiting_users'][$key][] = $user_id;
                
            } else {
                $msg = $hinglish->get_response('found', $mode) . "\n\n";
                
                // Build results keyboard
                $keyboard = ['inline_keyboard' => []];
                $row = [];
                
                foreach (array_slice($results, 0, 8) as $movie) {
                    $row[] = [
                        'text' => "{$movie['channel_emoji']} " . substr($movie['movie_name'], 0, 20),
                        'callback_data' => "get_" . $movie['id']
                    ];
                    
                    if (count($row) == 2) {
                        $keyboard['inline_keyboard'][] = $row;
                        $row = [];
                    }
                }
                
                if (!empty($row)) {
                    $keyboard['inline_keyboard'][] = $row;
                }
                
                // Add action buttons
                $keyboard['inline_keyboard'][] = [
                    ['text' => '📦 Send All', 'callback_data' => 'sendall_search_' . base64_encode($query)],
                    ['text' => '🔍 New Search', 'switch_inline_query_current_chat' => '']
                ];
                
                sendMessage($chat_id, $msg . "Found " . count($results) . " results:", $keyboard, 'HTML');
                
                update_stat('successful_searches', 1);
                update_stat('total_searches', 1);
                track_user($user_id, [], 'search');
                track_user($user_id, [], 'found');
            }
            break;
            
        case '/browse':
        case '/all':
            $page = isset($params[0]) ? (int)$params[0] : 1;
            show_browser($chat_id, $page);
            break;
            
        case '/latest':
        case '/new':
            $movies = get_latest_movies(10);
            if (empty($movies)) {
                sendMessage($chat_id, "📭 No movies yet!");
                return;
            }
            
            $msg = "🎬 <b>Latest Movies</b>\n\n";
            foreach ($movies as $i => $movie) {
                $msg .= ($i+1) . ". {$movie['channel_emoji']} <b>{$movie['movie_name']}</b>\n";
                $msg .= "   🏷️ {$movie['quality']} | 🗣️ {$movie['language']}\n";
                if ($movie['year']) {
                    $msg .= "   📅 {$movie['year']}\n";
                }
                $msg .= "\n";
            }
            
            $keyboard = [
                'inline_keyboard' => [[
                    ['text' => '📦 Get All', 'callback_data' => 'sendall_latest'],
                    ['text' => '🔄 Browse All', 'callback_data' => 'browse_1']
                ]]
            ];
            
            sendMessage($chat_id, $msg, $keyboard, 'HTML');
            break;
            
        case '/trending':
        case '/popular':
            $movies = get_trending_movies(10);
            if (empty($movies)) {
                sendMessage($chat_id, "📭 No trending movies!");
                return;
            }
            
            $msg = "🔥 <b>Trending Movies</b>\n\n";
            foreach ($movies as $i => $movie) {
                $msg .= ($i+1) . ". {$movie['channel_emoji']} <b>{$movie['movie_name']}</b>\n";
                $msg .= "   📥 {$movie['downloads']} downloads\n\n";
            }
            
            sendMessage($chat_id, $msg, null, 'HTML');
            break;
            
        case '/theater':
        case '/prints':
            $results = search_movies('', ['quality' => 'Theater']);
            if (empty($results)) {
                sendMessage($chat_id, "🎭 No theater prints yet!");
                return;
            }
            
            $msg = "🎭 <b>Theater Prints</b>\n\n";
            foreach (array_slice($results, 0, 10) as $i => $movie) {
                $msg .= ($i+1) . ". <b>{$movie['movie_name']}</b>\n";
                $msg .= "   🏷️ {$movie['quality']} | 🗣️ {$movie['language']}\n\n";
            }
            
            $keyboard = [
                'inline_keyboard' => [[
                    ['text' => '📦 Get All', 'callback_data' => 'sendall_theater']
                ]]
            ];
            
            sendMessage($chat_id, $msg, $keyboard, 'HTML');
            break;
            
        case '/request':
        case '/req':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: /request [movie name]");
                return;
            }
            
            // Check if already exists
            if (movie_exists($movie_name)) {
                sendMessage($chat_id, "✅ This movie already exists! Try /search");
                return;
            }
            
            $result = add_request($user_id, $movie_name);
            
            if ($result['success']) {
                sendMessage($chat_id, $hinglish->get_response('request_sent', $mode));
            } else {
                sendMessage($chat_id, $hinglish->get_response('limit_reached', $mode));
            }
            break;
            
        case '/stats':
        case '/me':
            $user = track_user($user_id);
            $rank = get_user_rank($user['points']);
            
            $msg = "👤 <b>Your Stats</b>\n\n";
            $msg .= "🆔 ID: <code>$user_id</code>\n";
            if ($user['username']) {
                $msg .= "📢 @{$user['username']}\n";
            }
            $msg .= "⭐ Points: {$user['points']} ($rank)\n";
            $msg .= "🔍 Searches: {$user['searches']}\n";
            $msg .= "📥 Downloads: {$user['downloads']}\n";
            $msg .= "📝 Requests: {$user['requests']}\n";
            $msg .= "🕐 Joined: " . date('d-m-Y', strtotime($user['joined'])) . "\n";
            $msg .= "⚡ Last: " . date('H:i', strtotime($user['last_active']));
            
            sendMessage($chat_id, $msg, null, 'HTML');
            break;
            
        case '/top':
        case '/leaderboard':
            $users = get_leaderboard(10);
            
            if (empty($users)) {
                sendMessage($chat_id, "📭 No users yet!");
                return;
            }
            
            $msg = "🏆 <b>Top Users</b>\n\n";
            foreach ($users as $i => $user) {
                $medal = $i == 0 ? '🥇' : ($i == 1 ? '🥈' : ($i == 2 ? '🥉' : '🔹'));
                $name = $user['username'] ? "@{$user['username']}" : $user['first_name'];
                $msg .= "$medal {$i+1}. $name\n";
                $msg .= "   ⭐ {$user['points']} pts | 🔍 {$user['searches']} | 📥 {$user['downloads']}\n\n";
            }
            
            sendMessage($chat_id, $msg, null, 'HTML');
            break;
            
        case '/settings':
        case '/preferences':
            $settings = get_user_settings($user_id);
            
            $msg = "⚙️ <b>Settings</b>\n\n";
            $msg .= "🔍 Search Results: {$settings['top_results']}\n";
            $msg .= "📊 Priority: {$settings['priority_sort']}\n";
            $msg .= "🎬 Layout: {$settings['result_layout']}\n";
            $msg .= "🗣️ Language: {$settings['language_preference']}\n";
            $msg .= "🎯 Quality: {$settings['quality_preference']}\n";
            $msg .= "🗑️ Auto Delete: " . ($settings['auto_delete'] ? '✅' : '❌') . "\n";
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🔍 Search', 'callback_data' => 'set_search']],
                    [['text' => '📊 Priority', 'callback_data' => 'set_priority']],
                    [['text' => '🎬 Layout', 'callback_data' => 'set_layout']],
                    [['text' => '🗣️ Language', 'callback_data' => 'set_lang']],
                    [['text' => '🎯 Quality', 'callback_data' => 'set_quality']],
                    [['text' => '🔄 Reset', 'callback_data' => 'reset_settings']]
                ]
            ];
            
            sendMessage($chat_id, $msg, $keyboard, 'HTML');
            break;
            
        case '/channels':
        case '/join':
            $msg = "📢 <b>Join Our Channels</b>\n\n";
            $msg .= "🎬 <b>Main Channel:</b>\n" . MAIN_CHANNEL . "\n";
            $msg .= "Latest movies & updates\n\n";
            $msg .= "🎭 <b>Theater Prints:</b>\n" . THEATER_CHANNEL . "\n";
            $msg .= "HD theater quality prints\n\n";
            $msg .= "💾 <b>Backup Channel:</b>\n" . BACKUP_CHANNEL_USERNAME . "\n";
            $msg .= "Data & archives\n\n";
            $msg .= "📝 <b>Request Group:</b>\n" . REQUEST_CHANNEL . "\n";
            $msg .= "Request movies & get help";
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🎬 Main', 'url' => 'https://t.me/' . ltrim(MAIN_CHANNEL, '@')],
                     ['text' => '🎭 Theater', 'url' => 'https://t.me/' . ltrim(THEATER_CHANNEL, '@')]],
                    [['text' => '💾 Backup', 'url' => 'https://t.me/' . ltrim(BACKUP_CHANNEL_USERNAME, '@')],
                     ['text' => '📝 Request', 'url' => 'https://t.me/' . ltrim(REQUEST_CHANNEL, '@')]]
                ]
            ];
            
            sendMessage($chat_id, $msg, $keyboard, 'HTML');
            break;
            
        // ===== ADMIN COMMANDS =====
        case '/admin':
            if ($chat_id == ADMIN_ID) {
                admin_dashboard($chat_id);
            }
            break;
            
        case '/backup':
            if ($chat_id == ADMIN_ID) {
                sendMessage($chat_id, "💾 Creating backup...", null, 'HTML', 1000);
                $backup = create_backup('full');
                sendMessage($chat_id, "✅ Backup complete!\n\nSize: " . format_bytes($backup['size']));
            }
            break;
            
        case '/add':
            if ($chat_id == ADMIN_ID) {
                $input = implode(' ', $params);
                if (empty($input)) {
                    sendMessage($chat_id, QUICKADD_FORMAT, null, 'HTML');
                    return;
                }
                
                $parts = explode(',', $input, 3);
                if (count($parts) < 3) {
                    sendMessage($chat_id, "❌ Invalid format! Use: movie_name,message_id,channel_id");
                    return;
                }
                
                $movie_name = trim($parts[0]);
                $message_id = trim($parts[1]);
                $channel_id = trim($parts[2]);
                
                if (!is_numeric($message_id)) {
                    sendMessage($chat_id, "❌ Message ID must be numeric");
                    return;
                }
                
                if (add_movie($movie_name, $message_id, $channel_id)) {
                    $channel = get_channel($channel_id);
                    $msg = "✅ <b>Movie Added!</b>\n\n";
                    $msg .= "🎬 $movie_name\n";
                    $msg .= "🆔 $message_id\n";
                    $msg .= "📢 {$channel['emoji']} {$channel['name']}\n";
                    $msg .= "🔗 " . get_channel_link($channel_id, $message_id);
                    sendMessage($chat_id, $msg, null, 'HTML');
                } else {
                    sendMessage($chat_id, "❌ Failed to add movie (maybe duplicate?)");
                }
            }
            break;
            
        case '/broadcast':
            if ($chat_id == ADMIN_ID) {
                $message = implode(' ', $params);
                if (empty($message)) {
                    sendMessage($chat_id, "❌ Usage: /broadcast [message]");
                    return;
                }
                admin_broadcast($chat_id, $message);
            }
            break;
            
        case '/maintenance':
            if ($chat_id == ADMIN_ID) {
                global $GLOBALS;
                $GLOBALS['maintenance_mode'] = !$GLOBALS['maintenance_mode'];
                $status = $GLOBALS['maintenance_mode'] ? 'ENABLED' : 'DISABLED';
                sendMessage($chat_id, "🔧 Maintenance mode: $status");
            }
            break;
            
        case '/cleanup':
            if ($chat_id == ADMIN_ID) {
                sendMessage($chat_id, "🧹 Cleaning up...");
                
                // Clear cache
                cache_clear();
                
                // Clean old backups
                clean_old_backups(7);
                
                // Vacuum database
                $db = get_db();
                $db->exec("VACUUM");
                
                sendMessage($chat_id, "✅ Cleanup complete!");
            }
            break;
            
        default:
            // Unknown command
            sendMessage($chat_id, "❌ Unknown command. Use /help");
    }
}

// ==============================
// 21. INITIALIZATION
// ==============================

// Initialize files
initialize_files();

// Load movies into cache
load_movies();

// Set start time
$GLOBALS['start_time'] = time();

// ==============================
// 22. WEBHOOK PROCESSING
// ==============================

$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    // Maintenance mode check
    if ($GLOBALS['maintenance_mode'] && isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        if ($chat_id != ADMIN_ID) {
            sendMessage($chat_id, $MAINTENANCE_MESSAGE);
            exit;
        }
    }
    
    // Process channel posts (auto-add)
    if (isset($update['channel_post'])) {
        $post = $update['channel_post'];
        $chat_id = $post['chat']['id'];
        $message_id = $post['message_id'];
        
        // Get text/caption
        $text = '';
        if (isset($post['caption'])) {
            $text = $post['caption'];
        } elseif (isset($post['text'])) {
            $text = $post['text'];
        } elseif (isset($post['document']['file_name'])) {
            $text = $post['document']['file_name'];
        }
        
        if (!empty($text)) {
            // Auto-add movie
            add_movie($text, $message_id, $chat_id);
        }
    }
    
    // Process messages
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = $message['text'] ?? '';
        $chat_type = $message['chat']['type'] ?? 'private';
        
        // Store user data
        $GLOBALS['user_data'] = [
            'first_name' => $message['from']['first_name'] ?? '',
            'last_name' => $message['from']['last_name'] ?? '',
            'username' => $message['from']['username'] ?? ''
        ];
        
        // Group message filtering
        if ($chat_type != 'private' && strpos($text, '/') !== 0) {
            // Only process movie names in groups
            if (strlen($text) < 3 || strlen($text) > 50) {
                return;
            }
        }
        
        // Check for auto-response first (non-commands)
        if (!empty($text) && strpos($text, '/') !== 0 && ENABLE_AUTO_RESPONSE) {
            $mode = $hinglish->detect_mode($text, $user_id);
            $response = $autoResponder->process($text, $user_id, $mode);
            
            if ($response) {
                sendMessage($chat_id, $response, null, 'HTML', 1000);
                
                // Don't process further for pure greetings
                if (preg_match('/^(hi|hello|hey|bye|thank)/i', $text)) {
                    return;
                }
            }
        }
        
        // Handle commands
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $params = array_slice($parts, 1);
            
            handle_command($chat_id, $user_id, $command, $params);
        } 
        // Handle movie search
        elseif (!empty($text) && strlen($text) >= 2) {
            // Check cooldown
            $cooldown = check_cooldown($user_id);
            if (is_string($cooldown)) {
                sendMessage($chat_id, $cooldown);
                return;
            }
            
            $mode = $hinglish->detect_mode($text, $user_id);
            sendMessage($chat_id, $hinglish->get_response('searching', $mode), null, 'HTML', 1000);
            
            // Search
            $results = search_movies($text);
            
            if (empty($results)) {
                sendMessage($chat_id, $hinglish->get_response('not_found', $mode));
                
                // Auto-suggest request
                $keyboard = [
                    'inline_keyboard' => [[
                        ['text' => '📝 Request This', 'callback_data' => 'req_' . base64_encode($text)]
                    ]]
                ];
                sendMessage($chat_id, "💡 Click to request:", $keyboard);
                
                update_stat('failed_searches', 1);
                track_user($user_id, [], 'search');
                
                // Add to waiting list
                $key = strtolower($text);
                $GLOBALS['waiting_users'][$key][] = $user_id;
                
            } else {
                $msg = $hinglish->get_response('found', $mode) . "\n\n";
                
                // Build results keyboard
                $keyboard = ['inline_keyboard' => []];
                $row = [];
                
                foreach (array_slice($results, 0, 8) as $movie) {
                    $row[] = [
                        'text' => "{$movie['channel_emoji']} " . substr($movie['movie_name'], 0, 20),
                        'callback_data' => "get_" . $movie['id']
                    ];
                    
                    if (count($row) == 2) {
                        $keyboard['inline_keyboard'][] = $row;
                        $row = [];
                    }
                }
                
                if (!empty($row)) {
                    $keyboard['inline_keyboard'][] = $row;
                }
                
                // Add action buttons
                $keyboard['inline_keyboard'][] = [
                    ['text' => '📦 Send All', 'callback_data' => 'sendall_search_' . base64_encode($text)],
                    ['text' => '🔍 New Search', 'switch_inline_query_current_chat' => '']
                ];
                
                sendMessage($chat_id, $msg . "Found " . count($results) . " results:", $keyboard, 'HTML');
                
                update_stat('successful_searches', 1);
                update_stat('total_searches', 1);
                track_user($user_id, [], 'search');
                track_user($user_id, [], 'found');
            }
        }
    }
    
    // Process callback queries
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $message = $query['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $query['from']['id'];
        $data = $query['data'];
        $msg_id = $message['message_id'];
        
        // Answer callback
        answerCallbackQuery($query['id']);
        
        // Get movie by ID
        if (strpos($data, 'get_') === 0) {
            $movie_id = (int)substr($data, 4);
            
            $db = get_db();
            $stmt = $db->prepare("SELECT * FROM movies WHERE id = :id");
            $stmt->bindValue(':id', $movie_id);
            $result = $stmt->execute();
            $movie = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($movie) {
                deliver_movie($chat_id, $movie, $user_id);
            } else {
                sendMessage($chat_id, "❌ Movie not found!");
            }
        }
        
        // Pagination
        elseif (strpos($data, 'page_') === 0) {
            $parts = explode('_', $data);
            $page = (int)$parts[1];
            $session_id = $parts[2] ?? '';
            
            show_browser($chat_id, $page, [], $session_id);
        }
        
        // Send all from page
        elseif (strpos($data, 'sendall_') === 0) {
            $parts = explode('_', $data);
            $type = $parts[1];
            
            if ($type == 'page') {
                $page = (int)$parts[2];
                $session_id = $parts[3] ?? '';
                
                // Get movies for this page
                $movies = load_movies();
                $p = paginate($movies, $page);
                batch_deliver($chat_id, $p['items'], $user_id, $page);
                
            } elseif ($type == 'search') {
                $query = base64_decode($parts[2]);
                $results = search_movies($query);
                batch_deliver($chat_id, $results, $user_id);
                
            } elseif ($type == 'latest') {
                $movies = get_latest_movies(10);
                batch_deliver($chat_id, $movies, $user_id);
                
            } elseif ($type == 'theater') {
                $results = search_movies('', ['quality' => 'Theater']);
                batch_deliver($chat_id, $results, $user_id);
            }
        }
        
        // Request movie
        elseif (strpos($data, 'req_') === 0) {
            $movie_name = base64_decode(substr($data, 4));
            
            if (movie_exists($movie_name)) {
                sendMessage($chat_id, "✅ Movie already exists! Try /search");
            } else {
                $result = add_request($user_id, $movie_name);
                $mode = $hinglish->detect_mode('', $user_id);
                
                if ($result['success']) {
                    sendMessage($chat_id, $hinglish->get_response('request_sent', $mode));
                } else {
                    sendMessage($chat_id, $hinglish->get_response('limit_reached', $mode));
                }
            }
        }
        
        // Filter actions
        elseif (strpos($data, 'filter_') === 0) {
            $parts = explode('_', $data);
            $filter = $parts[1];
            $session_id = $parts[2] ?? '';
            
            $filters = [];
            if ($filter == 'hd') {
                $filters = ['hd' => true];
            } elseif ($filter == 'theater') {
                $filters = ['theater' => true];
            } elseif ($filter == 'hindi') {
                $filters = ['hindi' => true];
            } elseif ($filter == 'clear') {
                $filters = [];
            }
            
            show_browser($chat_id, 1, $filters, $session_id);
        }
        
        // Close message
        elseif (strpos($data, 'close_') === 0) {
            deleteMessage($chat_id, $msg_id);
        }
        
        // Admin callbacks
        elseif ($chat_id == ADMIN_ID) {
            if ($data == 'admin_back') {
                admin_dashboard($chat_id);
            }
            elseif ($data == 'admin_stats') {
                $stats = get_system_stats();
                $msg = "📊 <b>Detailed Stats</b>\n\n";
                $msg .= "Total Movies: {$stats['total_movies']}\n";
                $msg .= "Total Users: {$stats['total_users']}\n";
                $msg .= "Total Searches: {$stats['total_searches']}\n";
                $msg .= "Total Downloads: {$stats['total_downloads']}\n";
                $msg .= "Success Rate: {$stats['success_rate']}%\n\n";
                $msg .= "Today Searches: {$stats['today_searches']}\n";
                $msg .= "Today Downloads: {$stats['today_downloads']}\n";
                $msg .= "Active Now: {$stats['active_now']}\n";
                $msg .= "Pending Requests: {$stats['pending_requests']}";
                
                sendMessage($chat_id, $msg, null, 'HTML');
            }
            elseif ($data == 'admin_users') {
                admin_users($chat_id);
            }
            elseif (strpos($data, 'admin_users_') === 0) {
                $page = (int)substr($data, 11);
                admin_users($chat_id, $page);
            }
            elseif ($data == 'admin_movies') {
                admin_movies($chat_id);
            }
            elseif (strpos($data, 'admin_movies_') === 0) {
                $page = (int)substr($data, 12);
                admin_movies($chat_id, $page);
            }
            elseif ($data == 'admin_requests') {
                admin_requests($chat_id);
            }
            elseif ($data == 'admin_backup') {
                sendMessage($chat_id, "💾 Create backup?");
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '✅ Full Backup', 'callback_data' => 'do_backup_full']],
                        [['text' => '⚡ Quick Backup', 'callback_data' => 'do_backup_quick']],
                        [['text' => '🔙 Back', 'callback_data' => 'admin_back']]
                    ]
                ];
                sendMessage($chat_id, "Choose backup type:", $keyboard);
            }
            elseif ($data == 'do_backup_full') {
                sendMessage($chat_id, "💾 Creating full backup...");
                $backup = create_backup('full');
                sendMessage($chat_id, "✅ Backup complete!\n\nSize: " . format_bytes($backup['size']));
            }
            elseif ($data == 'do_backup_quick') {
                sendMessage($chat_id, "⚡ Creating quick backup...");
                $backup = create_backup('quick');
                sendMessage($chat_id, "✅ Quick backup complete!");
            }
            elseif ($data == 'admin_broadcast') {
                sendMessage($chat_id, "📢 Send broadcast to:");
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '👥 All Users', 'callback_data' => 'broadcast_all']],
                        [['text' => '🟢 Active Now', 'callback_data' => 'broadcast_active']],
                        [['text' => '🔴 Inactive', 'callback_data' => 'broadcast_inactive']],
                        [['text' => '🔙 Back', 'callback_data' => 'admin_back']]
                    ]
                ];
                sendMessage($chat_id, "Select target:", $keyboard);
            }
            elseif ($data == 'admin_config') {
                $msg = "⚙️ <b>Bot Configuration</b>\n\n";
                $msg .= "BOT_TOKEN: " . substr(BOT_TOKEN, 0, 10) . "...\n";
                $msg .= "ADMIN_ID: " . ADMIN_ID . "\n";
                $msg .= "MAIN_CHANNEL: " . MAIN_CHANNEL . "\n";
                $msg .= "THEATER_CHANNEL: " . THEATER_CHANNEL . "\n";
                $msg .= "BACKUP_CHANNEL: " . BACKUP_CHANNEL_USERNAME . "\n";
                $msg .= "REQUEST_CHANNEL: " . REQUEST_CHANNEL . "\n\n";
                $msg .= "CACHE_EXPIRY: " . CACHE_EXPIRY . "s\n";
                $msg .= "ITEMS_PER_PAGE: " . ITEMS_PER_PAGE . "\n";
                $msg .= "SEARCH_COOLDOWN: " . SEARCH_COOLDOWN . "s\n";
                $msg .= "DAILY_REQUEST_LIMIT: " . DAILY_REQUEST_LIMIT . "\n";
                $msg .= "AUTO_BACKUP_HOUR: " . AUTO_BACKUP_HOUR . ":00\n";
                
                sendMessage($chat_id, $msg, null, 'HTML');
            }
            elseif ($data == 'admin_logs') {
                if (file_exists(LOG_FILE)) {
                    $logs = file(LOG_FILE);
                    $last = array_slice($logs, -20);
                    $msg = "📋 <b>Last 20 Logs</b>\n\n";
                    $msg .= "<pre>" . htmlspecialchars(implode('', $last)) . "</pre>";
                    sendMessage($chat_id, $msg, null, 'HTML');
                } else {
                    sendMessage($chat_id, "No logs found!");
                }
            }
        }
    }
    
    // Process delete queue periodically
    process_delete_queue();
}

// ==============================
// 23. CRON JOBS (called via web)
// ==============================

if (isset($_GET['cron'])) {
    $job = $_GET['cron'];
    
    if ($job == 'backup' && date('H') == AUTO_BACKUP_HOUR) {
        create_backup('full');
        echo "Backup created at " . date('Y-m-d H:i:s');
    }
    
    if ($job == 'cleanup') {
        clean_old_backups(7);
        cache_clear();
        $db = get_db();
        $db->exec("VACUUM");
        echo "Cleanup done at " . date('Y-m-d H:i:s');
    }
    
    exit;
}

// ==============================
// 24. WEB SETUP & STATUS PAGES
// ==============================

// Webhook setup
if (isset($_GET['setwebhook'])) {
    $url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    $url = str_replace('?setwebhook', '', $url);
    
    $result = apiRequest('setWebhook', ['url' => $url]);
    
    echo "<h1>✅ Webhook Set</h1>";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    
    $info = apiRequest('getWebhookInfo');
    echo "<h2>Webhook Info</h2>";
    echo "<pre>" . json_encode($info, JSON_PRETTY_PRINT) . "</pre>";
    
    exit;
}

// Status page
if (!isset($update) && !isset($_GET['cron'])) {
    $stats = get_system_stats();
    
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Entertainment Tadka Bot</title>";
    echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5}.card{background:white;padding:20px;border-radius:10px;box-shadow:0 2px 5px rgba(0,0,0,0.1);margin-bottom:20px}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px}.stat{background:#f8f9fa;padding:15px;border-radius:8px;text-align:center}.stat-value{font-size:24px;font-weight:bold;color:#2c3e50}.stat-label{color:#7f8c8d;margin-top:5px}.badge{background:#27ae60;color:white;padding:3px 10px;border-radius:15px;font-size:12px}.button{background:#3498db;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;margin:5px}</style>";
    echo "</head><body>";
    
    echo "<h1>🎬 Entertainment Tadka Bot</h1>";
    
    echo "<div class='card'>";
    echo "<h2>🤖 Bot Status</h2>";
    echo "<p><span class='badge'>🟢 ONLINE</span> v" . BOT_VERSION . "</p>";
    echo "<p>⏱️ Uptime: {$stats['uptime']}</p>";
    echo "</div>";
    
    echo "<div class='stats'>";
    echo "<div class='stat'><div class='stat-value'>{$stats['total_movies']}</div><div class='stat-label'>🎬 Movies</div></div>";
    echo "<div class='stat'><div class='stat-value'>{$stats['total_users']}</div><div class='stat-label'>👥 Users</div></div>";
    echo "<div class='stat'><div class='stat-value'>{$stats['total_searches']}</div><div class='stat-label'>🔍 Searches</div></div>";
    echo "<div class='stat'><div class='stat-value'>{$stats['total_downloads']}</div><div class='stat-label'>📥 Downloads</div></div>";
    echo "<div class='stat'><div class='stat-value'>{$stats['active_now']}</div><div class='stat-label'>🟢 Active Now</div></div>";
    echo "<div class='stat'><div class='stat-value'>{$stats['success_rate']}%</div><div class='stat-label'>✅ Success Rate</div></div>";
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>📊 Today's Stats</h2>";
    echo "<p>🔍 Searches: {$stats['today_searches']}</p>";
    echo "<p>📥 Downloads: {$stats['today_downloads']}</p>";
    echo "<p>📝 Pending Requests: {$stats['pending_requests']}</p>";
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>⚙️ System</h2>";
    echo "<p>💾 Memory: " . round($stats['memory_usage'], 1) . " MB (Peak: " . round($stats['peak_memory'], 1) . " MB)</p>";
    echo "<p>📁 Database: " . (file_exists(DB_FILE) ? format_bytes(filesize(DB_FILE)) : 'N/A') . "</p>";
    echo "<p>📄 CSV: " . (file_exists(CSV_FILE) ? format_bytes(filesize(CSV_FILE)) : 'N/A') . "</p>";
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🔧 Admin Actions</h2>";
    echo "<a href='?setwebhook' class='button'>Set Webhook</a> ";
    echo "<a href='?cron=backup' class='button'>Run Backup</a> ";
    echo "<a href='?cron=cleanup' class='button'>Cleanup</a>";
    echo "</div>";
    
    echo "</body></html>";
    
    exit;
}

// ==============================
// 25. END OF FILE
// ==============================
?>