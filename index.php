<?php
// ==============================
// ENTERTAINMENT TADKA BOT v3.0.0
// ADVANCED MULTI-CHANNEL MOVIE BOT
// COMPLETE PRODUCTION READY CODE
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
header("Cache-Control: no-cache, must-revalidate");

// ==============================
// 2. ERROR REPORTING
// ==============================
if (getenv('ENVIRONMENT') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// Set time limits
set_time_limit(300);
ini_set('max_execution_time', 300);
ini_set('memory_limit', getenv('PHP_MEMORY_LIMIT') ?: '256M');

// ==============================
// 3. TIMEZONE
// ==============================
date_default_timezone_set('Asia/Kolkata');

// ==============================
// 4. CONSTANTS & CONFIGURATION
// ==============================

// Bot Configuration
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '');
define('BOT_USERNAME', '@EntertainmentTadkaBot');
define('BOT_VERSION', '3.0.0');
define('ADMIN_ID', (int)(getenv('ADMIN_ID') ?: '0'));

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
    ],
    'serial' => [
        'id' => '-1003614546520',
        'username' => '@Entertainment_Tadka_Serial_786',
        'type' => 'public',
        'name' => 'Serial Channel',
        'emoji' => '📺',
        'header' => true
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
define('SERIAL_CHANNEL_ID', $CHANNELS['serial']['id']);

// Channel Usernames
define('MAIN_CHANNEL', $CHANNELS['main']['username']);
define('THEATER_CHANNEL', $CHANNELS['theater']['username']);
define('BACKUP_CHANNEL_USERNAME', $CHANNELS['backup']['username']);
define('REQUEST_CHANNEL', $CHANNELS['request']['username']);
define('SERIAL_CHANNEL', $CHANNELS['serial']['username']);

// API Credentials
define('API_ID', '21944581');
define('API_HASH', '7b1c174a5cd3466e25a976c39a791737');

// File Paths
define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR);
define('CSV_FILE', BASE_PATH . 'movies.csv');
define('USERS_FILE', BASE_PATH . 'users.json');
define('STATS_FILE', BASE_PATH . 'bot_stats.json');
define('REQUEST_FILE', BASE_PATH . 'movie_requests.json');
define('BACKUP_DIR', BASE_PATH . 'backups' . DIRECTORY_SEPARATOR);
define('LOG_FILE', BASE_PATH . 'logs' . DIRECTORY_SEPARATOR . 'bot_activity.log');
define('DB_FILE', BASE_PATH . 'database' . DIRECTORY_SEPARATOR . 'movies.db');
define('CACHE_DIR', BASE_PATH . 'cache' . DIRECTORY_SEPARATOR);
define('LEARNING_FILE', BASE_PATH . 'learning.json');
define('STORAGE_DIR', BASE_PATH . 'storage' . DIRECTORY_SEPARATOR);

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
define('SESSION_TIMEOUT', 3600);             // Session timeout (1 hour)
define('MAX_WAITING_USERS', 100);             // Max waiting users per movie
define('POINTS_PER_SEARCH', 1);               // Points for searching
define('POINTS_PER_DOWNLOAD', 3);             // Points for downloading
define('POINTS_PER_REQUEST', 2);               // Points for requesting
define('POINTS_PER_FOUND', 5);                 // Points for found movie
define('POINTS_PER_LOGIN', 10);                // Daily login points
define('WEBHOOK_URL', getenv('WEBHOOK_URL') ?: '');

// Feature Flags
define('ENABLE_CACHE', filter_var(getenv('ENABLE_CACHE') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('ENABLE_REDIS', class_exists('Redis') && filter_var(getenv('ENABLE_REDIS') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('ENABLE_ANALYTICS', filter_var(getenv('ENABLE_ANALYTICS') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('ENABLE_AUTO_RESPONSE', filter_var(getenv('ENABLE_AUTO_RESPONSE') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('ENABLE_BROADCAST', filter_var(getenv('ENABLE_BROADCAST') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('ENABLE_BACKUP', filter_var(getenv('ENABLE_BACKUP') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('ENABLE_STATS', filter_var(getenv('ENABLE_STATS') ?: 'true', FILTER_VALIDATE_BOOLEAN));

// Quick Add Format
define('QUICKADD_FORMAT', "🎬 <b>Quick Add Format:</b>\n\n" .
    "<code>/add movie_name,message_id,channel_id</code>\n\n" .
    "<b>Examples:</b>\n" .
    "• <code>/add KGF 2,12345,-1003181705395</code>\n" .
    "• <code>/add Pushpa,67890,-1002831605258</code>\n\n" .
    "<b>Supported Channels:</b>\n" .
    "• -1003181705395 (Main)\n" .
    "• -1002831605258 (Theater)\n" .
    "• -1002964109368 (Backup)\n" .
    "• -1003251791991 (Private 1)\n" .
    "• -1002337293281 (Private 2)\n" .
    "• -1003614546520 (Any/Serial)\n" .
    "• -1003083386043 (Request)\n\n" .
    "<b>⚠️ 3 COLUMNS ONLY!</b>");

// ==============================
// 5. GLOBAL VARIABLES
// ==============================
$GLOBALS = [
    'movie_cache' => ['data' => [], 'timestamp' => 0],
    'user_cache' => [],
    'search_cache' => [],
    'delete_queue' => [],
    'user_last_search' => [],
    'waiting_users' => [],
    'user_sessions' => [],
    'pagination_sessions' => [],
    'maintenance_mode' => filter_var(getenv('MAINTENANCE_MODE') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'start_time' => microtime(true),
    'db_connections' => [],
    'api_calls' => 0,
    'errors' => [],
    'warnings' => [],
    'user_data' => []
];

// Maintenance Message
$MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>\n\n" .
    "We're temporarily unavailable for updates.\n" .
    "Will be back soon!\n\n" .
    "Thanks for patience 🙏";

// ==============================
// 6. HELPER FUNCTIONS
// ==============================

/**
 * Log bot activities
 */
function bot_log($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message" . PHP_EOL;
    
    // Create log directory if not exists
    $log_dir = dirname(LOG_FILE);
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    // Write to file
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Keep in memory for last 100 errors
    if ($type === 'ERROR') {
        global $GLOBALS;
        $GLOBALS['errors'][] = $log_entry;
        if (count($GLOBALS['errors']) > 100) {
            array_shift($GLOBALS['errors']);
        }
        
        // Send critical errors to admin
        if (defined('ADMIN_ID') && ADMIN_ID > 0) {
            sendMessage(ADMIN_ID, "🚨 <b>Bot Error:</b>\n<code>" . htmlspecialchars($message) . "</code>", null, 'HTML');
        }
    }
    
    if ($type === 'WARNING') {
        global $GLOBALS;
        $GLOBALS['warnings'][] = $log_entry;
        if (count($GLOBALS['warnings']) > 100) {
            array_shift($GLOBALS['warnings']);
        }
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
            'last_cleanup' => null,
            'total_searches' => 0,
            'total_downloads' => 0
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        STATS_FILE => json_encode([
            'total_movies' => 0,
            'total_users' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
            'successful_searches' => 0,
            'failed_searches' => 0,
            'daily_activity' => [],
            'last_updated' => date('Y-m-d H:i:s'),
            'version' => BOT_VERSION,
            'uptime' => 0,
            'commands_used' => []
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        REQUEST_FILE => json_encode([
            'requests' => [],
            'pending_approval' => [],
            'completed_requests' => [],
            'user_request_count' => [],
            'total_requests' => 0,
            'last_request_id' => 0
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LEARNING_FILE => json_encode([
            'patterns' => [],
            'responses' => [],
            'stats' => []
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ];
    
    foreach ($files as $file => $content) {
        $dir = dirname($file);
        if (!file_exists($dir) && $dir !== '.') {
            mkdir($dir, 0777, true);
        }
        
        if (!file_exists($file)) {
            file_put_contents($file, $content, LOCK_EX);
            chmod($file, 0666);
        }
    }
    
    // Create directories
    foreach ([BACKUP_DIR, CACHE_DIR, STORAGE_DIR, dirname(DB_FILE), dirname(LOG_FILE)] as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    
    // Initialize SQLite database
    init_database();
    
    bot_log("System initialized - v" . BOT_VERSION);
}

/**
 * Initialize SQLite database with FTS5
 */
function init_database() {
    $db_path = DB_FILE;
    $db_dir = dirname($db_path);
    
    if (!file_exists($db_dir)) {
        mkdir($db_dir, 0777, true);
    }
    
    $db = new SQLite3($db_path);
    $db->busyTimeout(5000);
    
    // Enable performance optimizations
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('PRAGMA cache_size = 10000');
    $db->exec('PRAGMA temp_store = MEMORY');
    $db->exec('PRAGMA mmap_size = 30000000000');
    $db->exec('PRAGMA page_size = 4096');
    
    // Create FTS5 virtual table for lightning fast search
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
                   tokenize='porter unicode61 remove_diacritics 2'
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
        last_accessed DATETIME,
        UNIQUE(message_id, channel_id)
    )");
    
    // Create indexes for performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_movie_name ON movies(movie_name)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_channel_id ON movies(channel_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_added_date ON movies(added_date)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_downloads ON movies(downloads)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_language ON movies(language)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_quality ON movies(quality)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_year ON movies(year)");
    
    // Create users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY,
        first_name TEXT,
        last_name TEXT,
        username TEXT,
        joined DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_active DATETIME,
        points INTEGER DEFAULT 10,
        searches INTEGER DEFAULT 0,
        downloads INTEGER DEFAULT 0,
        requests INTEGER DEFAULT 0,
        language TEXT DEFAULT 'hinglish',
        settings TEXT DEFAULT '{}',
        achievements TEXT DEFAULT '[]',
        banned INTEGER DEFAULT 0,
        notes TEXT
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
        notes TEXT,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )");
    
    // Create request votes table
    $db->exec("CREATE TABLE IF NOT EXISTS request_votes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        request_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, request_id),
        FOREIGN KEY (user_id) REFERENCES users(user_id),
        FOREIGN KEY (request_id) REFERENCES requests(id)
    )");
    
    // Create learning table for auto-responses
    $db->exec("CREATE TABLE IF NOT EXISTS learning (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pattern TEXT UNIQUE,
        response TEXT,
        category TEXT DEFAULT 'general',
        count INTEGER DEFAULT 1,
        last_used DATETIME DEFAULT CURRENT_TIMESTAMP,
        confidence REAL DEFAULT 1.0
    )");
    
    // Create stats table
    $db->exec("CREATE TABLE IF NOT EXISTS stats (
        date TEXT PRIMARY KEY,
        searches INTEGER DEFAULT 0,
        downloads INTEGER DEFAULT 0,
        users INTEGER DEFAULT 0,
        requests INTEGER DEFAULT 0,
        messages INTEGER DEFAULT 0,
        commands INTEGER DEFAULT 0
    )");
    
    // Create cache table
    $db->exec("CREATE TABLE IF NOT EXISTS cache (
        key TEXT PRIMARY KEY,
        value TEXT,
        expires INTEGER,
        created DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create sessions table
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        session_id TEXT PRIMARY KEY,
        user_id INTEGER,
        data TEXT,
        created DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_accessed DATETIME,
        expires INTEGER
    )");
    
    // Create backups table
    $db->exec("CREATE TABLE IF NOT EXISTS backups (
        id TEXT PRIMARY KEY,
        type TEXT,
        size INTEGER,
        files INTEGER,
        created DATETIME DEFAULT CURRENT_TIMESTAMP,
        status TEXT DEFAULT 'completed'
    )");
    
    // Create download logs table
    $db->exec("CREATE TABLE IF NOT EXISTS download_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        movie_id INTEGER NOT NULL,
        movie_name TEXT NOT NULL,
        method TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id),
        FOREIGN KEY (movie_id) REFERENCES movies(id)
    )");
    
    // Create search logs table
    $db->exec("CREATE TABLE IF NOT EXISTS search_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        query TEXT NOT NULL,
        results INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )");
    
    // Create user activity table
    $db->exec("CREATE TABLE IF NOT EXISTS user_activity (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        action TEXT NOT NULL,
        movie_id INTEGER,
        details TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id),
        FOREIGN KEY (movie_id) REFERENCES movies(id)
    )");
    
    bot_log("Database initialized with FTS5");
    
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
 * Format bytes to human readable
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Get channel info by ID
 */
function get_channel($channel_id) {
    global $CHANNELS;
    
    foreach ($CHANNELS as $key => $channel) {
        if ($channel['id'] == $channel_id) {
            return $channel;
        }
    }
    
    // Check if it's a negative ID (Telegram channel)
    if (strpos($channel_id, '-100') === 0) {
        return [
            'id' => $channel_id,
            'username' => null,
            'type' => 'private',
            'name' => 'Channel ' . substr($channel_id, -6),
            'emoji' => '📢',
            'header' => false,
            'key' => 'unknown'
        ];
    }
    
    return [
        'id' => $channel_id,
        'username' => null,
        'type' => 'unknown',
        'name' => 'Unknown Channel',
        'emoji' => '❓',
        'header' => false,
        'key' => 'unknown'
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

/**
 * Get channel username link
 */
function get_channel_username_link($username) {
    if ($username && strpos($username, '@') === 0) {
        return "https://t.me/" . ltrim($username, '@');
    }
    return null;
}

/**
 * Validate channel ID format
 */
function is_valid_channel_id($channel_id) {
    return (strpos($channel_id, '-100') === 0 && is_numeric(str_replace('-100', '', $channel_id)));
}

/**
 * Get all channels list
 */
function get_all_channels() {
    global $CHANNELS;
    return $CHANNELS;
}

/**
 * Get public channels
 */
function get_public_channels() {
    global $CHANNELS;
    return array_filter($CHANNELS, function($c) { return $c['type'] === 'public'; });
}

/**
 * Get private channels
 */
function get_private_channels() {
    global $CHANNELS;
    return array_filter($CHANNELS, function($c) { return $c['type'] === 'private'; });
}

// ==============================
// 7. CACHE FUNCTIONS
// ==============================

/**
 * Get from cache
 */
function cache_get($key) {
    if (!ENABLE_CACHE) return null;
    
    // Try memory cache first
    global $GLOBALS;
    if (isset($GLOBALS['search_cache'][$key]) && 
        (time() - $GLOBALS['search_cache'][$key]['time']) < CACHE_EXPIRY) {
        return $GLOBALS['search_cache'][$key]['data'];
    }
    
    // Try Redis next
    if (ENABLE_REDIS) {
        static $redis = null;
        if ($redis === null) {
            try {
                $redis = new Redis();
                $redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379));
                $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            } catch (Exception $e) {
                bot_log("Redis connection failed: " . $e->getMessage(), 'WARNING');
            }
        }
        
        if ($redis) {
            try {
                $value = $redis->get($key);
                if ($value !== false) {
                    // Store in memory cache too
                    $GLOBALS['search_cache'][$key] = [
                        'data' => $value,
                        'time' => time()
                    ];
                    return $value;
                }
            } catch (Exception $e) {
                bot_log("Redis get failed: " . $e->getMessage(), 'WARNING');
            }
        }
    }
    
    // Fallback to SQLite cache
    $db = get_db();
    $stmt = $db->prepare("SELECT value FROM cache WHERE key = :key AND (expires IS NULL OR expires > :now)");
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $stmt->bindValue(':now', time(), SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $value = unserialize($row['value']);
        // Store in memory cache
        $GLOBALS['search_cache'][$key] = [
            'data' => $value,
            'time' => time()
        ];
        return $value;
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
    
    // Store in memory cache
    global $GLOBALS;
    $GLOBALS['search_cache'][$key] = [
        'data' => $value,
        'time' => time()
    ];
    
    // Try Redis
    if (ENABLE_REDIS) {
        static $redis = null;
        if ($redis === null) {
            try {
                $redis = new Redis();
                $redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379));
                $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            } catch (Exception $e) {
                // Fall through to SQLite
            }
        }
        
        if ($redis) {
            try {
                $redis->setex($key, $ttl, $value);
                return;
            } catch (Exception $e) {
                // Fall through to SQLite
            }
        }
    }
    
    // SQLite cache
    $db = get_db();
    $stmt = $db->prepare("INSERT OR REPLACE INTO cache (key, value, expires) VALUES (:key, :value, :expires)");
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $stmt->bindValue(':value', $serialized, SQLITE3_TEXT);
    $stmt->bindValue(':expires', $expires, $expires ? SQLITE3_INTEGER : SQLITE3_NULL);
    $stmt->execute();
}

/**
 * Clear cache
 */
function cache_clear($pattern = null) {
    global $GLOBALS;
    
    if ($pattern === null) {
        // Clear all cache
        $GLOBALS['search_cache'] = [];
        
        if (ENABLE_REDIS) {
            try {
                $redis = new Redis();
                $redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379));
                $redis->flushAll();
            } catch (Exception $e) {}
        }
        
        $db = get_db();
        $db->exec("DELETE FROM cache");
        
        bot_log("All cache cleared");
    } else {
        // Clear by pattern
        $db = get_db();
        $stmt = $db->prepare("DELETE FROM cache WHERE key LIKE :pattern");
        $stmt->bindValue(':pattern', $pattern, SQLITE3_TEXT);
        $stmt->execute();
        
        // Clear from memory too
        foreach (array_keys($GLOBALS['search_cache']) as $key) {
            if (strpos($key, str_replace('%', '', $pattern)) !== false) {
                unset($GLOBALS['search_cache'][$key]);
            }
        }
    }
}

/**
 * Get cache stats
 */
function cache_stats() {
    $db = get_db();
    $count = $db->querySingle("SELECT COUNT(*) FROM cache");
    $size = $db->querySingle("SELECT SUM(LENGTH(value)) FROM cache");
    
    global $GLOBALS;
    
    return [
        'memory_entries' => count($GLOBALS['search_cache']),
        'db_entries' => $count,
        'db_size' => $size ? round($size / 1024, 2) . ' KB' : '0 KB',
        'redis' => ENABLE_REDIS ? 'available' : 'not available'
    ];
}

// ==============================
// 8. CSV FUNCTIONS
// ==============================

/**
 * Load movies from CSV
 */
function load_movies() {
    global $GLOBALS;
    
    // Check cache first
    if (!empty($GLOBALS['movie_cache']['data']) && 
        (time() - $GLOBALS['movie_cache']['timestamp']) < CACHE_EXPIRY) {
        return $GLOBALS['movie_cache']['data'];
    }
    
    $movies = [];
    
    if (!file_exists(CSV_FILE)) {
        file_put_contents(CSV_FILE, "movie_name,message_id,channel_id\n", LOCK_EX);
        return $movies;
    }
    
    $handle = fopen(CSV_FILE, 'r');
    if ($handle) {
        // Skip header
        $header = fgetcsv($handle);
        
        // Validate header (must be 3 columns)
        if (count($header) != 3) {
            bot_log("CSV header invalid: " . implode(',', $header), 'WARNING');
            // Force correct header
            file_put_contents(CSV_FILE, "movie_name,message_id,channel_id\n", LOCK_EX);
            fclose($handle);
            return [];
        }
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3) {
                $movie_name = trim($row[0]);
                $message_id = trim($row[1]);
                $channel_id = trim($row[2]);
                
                if (!empty($movie_name) && is_numeric($message_id) && !empty($channel_id)) {
                    $channel = get_channel($channel_id);
                    
                    $movie = [
                        'id' => count($movies) + 1,
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
                    
                    $movies[] = $movie;
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
    
    bot_log("Loaded " . count($movies) . " movies from CSV");
    
    return $movies;
}

/**
 * Add movie to CSV (3 columns only)
 */
function add_movie($movie_name, $message_id, $channel_id) {
    // Validate
    if (empty($movie_name) || !is_numeric($message_id) || empty($channel_id)) {
        bot_log("Invalid movie data: $movie_name, $message_id, $channel_id", 'WARNING');
        return false;
    }
    
    // Validate channel ID format
    if (!is_valid_channel_id($channel_id)) {
        bot_log("Invalid channel ID format: $channel_id", 'WARNING');
        return false;
    }
    
    // Check if already exists
    $existing = search_movies_exact($movie_name, $message_id, $channel_id);
    if ($existing) {
        bot_log("Movie already exists: $movie_name");
        return false;
    }
    
    // Write to CSV
    $handle = fopen(CSV_FILE, 'a');
    if ($handle) {
        fputcsv($handle, [$movie_name, $message_id, $channel_id]);
        fclose($handle);
        
        // Clear cache
        cache_clear('search_%');
        global $GLOBALS;
        $GLOBALS['movie_cache'] = ['data' => [], 'timestamp' => 0];
        
        // Add to SQLite
        add_movie_to_db($movie_name, $message_id, $channel_id);
        
        // Check for waiting users
        notify_waiting_users($movie_name, $channel_id);
        
        bot_log("Movie added: $movie_name ($message_id) to " . get_channel($channel_id)['name']);
        
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
    
    $stmt->bindValue(':name', $movie_name, SQLITE3_TEXT);
    $stmt->bindValue(':msg', $message_id, SQLITE3_INTEGER);
    $stmt->bindValue(':channel', $channel_id, SQLITE3_TEXT);
    $stmt->bindValue(':quality', $quality, SQLITE3_TEXT);
    $stmt->bindValue(':lang', $language, SQLITE3_TEXT);
    $stmt->bindValue(':year', $year, $year ? SQLITE3_INTEGER : SQLITE3_NULL);
    
    $stmt->execute();
    
    $movie_id = $db->lastInsertRowID();
    
    // Update FTS index
    if ($movie_id) {
        $db->exec("INSERT INTO movies_fts(rowid, movie_name, message_id, channel_id, quality, language, year)
                   SELECT id, movie_name, message_id, channel_id, quality, language, year
                   FROM movies WHERE id = $movie_id");
        
        bot_log("Added to FTS index: $movie_name");
    }
}

/**
 * Detect quality from movie name
 */
function detect_quality($name) {
    $name = strtolower($name);
    
    $patterns = [
        '4K' => ['2160p', '4k', 'uhd', '4k hdr', '2160p hdr'],
        '1080p' => ['1080p', '1080', 'fhd', 'full hd', 'fullhd', '1920x1080'],
        '720p' => ['720p', '720', 'hd', 'hdtv', '1280x720'],
        '480p' => ['480p', '480', 'sd', 'dvdrip', 'dvd', '854x480'],
        '360p' => ['360p', '360', '640x360'],
        'Theater' => ['theater', 'theatre', 'print', 'cam', 'hdcam', 'hdts', 'ts', 'hd cam'],
        'BluRay' => ['bluray', 'blu-ray', 'brrip', 'bdrip', 'blu ray', 'bd'],
        'WebRip' => ['webrip', 'web-dl', 'webdl', 'web', 'web dl']
    ];
    
    foreach ($patterns as $quality => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($name, $keyword) !== false) {
                return $quality;
            }
        }
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
        'Hindi' => ['hindi', 'hind', 'hd', 'hin', 'हिंदी'],
        'English' => ['english', 'eng', 'en', 'eng', 'english'],
        'Tamil' => ['tamil', 'tam', 'ta', 'தமிழ்'],
        'Telugu' => ['telugu', 'tel', 'te', 'తెలుగు'],
        'Malayalam' => ['malayalam', 'mal', 'ml', 'മലയാളം'],
        'Kannada' => ['kannada', 'kan', 'kn', 'ಕನ್ನಡ'],
        'Bengali' => ['bengali', 'ben', 'bn', 'বাংলা'],
        'Punjabi' => ['punjabi', 'pun', 'pa', 'ਪੰਜਾਬੀ'],
        'Gujarati' => ['gujarati', 'guj', 'gu', 'ગુજરાતી'],
        'Marathi' => ['marathi', 'mar', 'mr', 'मराठी'],
        'Bhojpuri' => ['bhojpuri', 'bho', 'bh', 'भोजपुरी'],
        'Odia' => ['odia', 'oriya', 'or', 'ଓଡ଼ିଆ'],
        'Assamese' => ['assamese', 'asm', 'as', 'অসমীয়া'],
        'Urdu' => ['urdu', 'urd', 'ur', 'اردو']
    ];
    
    foreach ($langs as $lang => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($name, $pattern) !== false) {
                return $lang;
            }
        }
    }
    
    // Check for multiple languages
    if (strpos($name, 'dual') !== false || strpos($name, 'multi') !== false) {
        return 'Multi Audio';
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
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':msg', $message_id, SQLITE3_INTEGER);
    $stmt->bindValue(':channel', $channel_id, SQLITE3_TEXT);
    
    $result = $stmt->execute();
    return $result->fetchArray() !== false;
}

/**
 * Count total movies
 */
function count_movies() {
    $db = get_db();
    return $db->querySingle("SELECT COUNT(*) FROM movies");
}

/**
 * Get movies by channel
 */
function get_movies_by_channel($channel_id) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM movies WHERE channel_id = :channel ORDER BY added_date DESC");
    $stmt->bindValue(':channel', $channel_id, SQLITE3_TEXT);
    
    $result = $stmt->execute();
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
 * Get movies by quality
 */
function get_movies_by_quality($quality) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM movies WHERE quality = :quality ORDER BY added_date DESC");
    $stmt->bindValue(':quality', $quality, SQLITE3_TEXT);
    
    $result = $stmt->execute();
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
 * Get movies by language
 */
function get_movies_by_language($language) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM movies WHERE language = :lang ORDER BY added_date DESC");
    $stmt->bindValue(':lang', $language, SQLITE3_TEXT);
    
    $result = $stmt->execute();
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
 * Get movies by year
 */
function get_movies_by_year($year) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM movies WHERE year = :year ORDER BY added_date DESC");
    $stmt->bindValue(':year', $year, SQLITE3_INTEGER);
    
    $result = $stmt->execute();
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
// 9. TELEGRAM API FUNCTIONS
// ==============================

/**
 * Make API request to Telegram
 */
function apiRequest($method, $params = []) {
    if (empty(BOT_TOKEN)) {
        bot_log("BOT_TOKEN not set", 'ERROR');
        return null;
    }
    
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($params),
            'timeout' => 30,
            'ignore_errors' => true,
            'user_agent' => 'EntertainmentTadkaBot/3.0'
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    global $GLOBALS;
    $GLOBALS['api_calls']++;
    
    if ($result === false) {
        $error = error_get_last();
        bot_log("API request failed: $method - " . ($error['message'] ?? 'Unknown error'), 'ERROR');
        return null;
    }
    
    $response = json_decode($result, true);
    
    if (!$response || !isset($response['ok'])) {
        bot_log("Invalid API response: $method", 'ERROR');
        return null;
    }
    
    return $response;
}

/**
 * Send message with optional delay
 */
function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = 'HTML', $delay = 0) {
    if (empty($chat_id) || empty($text)) {
        return null;
    }
    
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
        $params['reply_markup'] = json_encode($reply_markup, JSON_UNESCAPED_UNICODE);
    }
    
    $result = apiRequest('sendMessage', $params);
    
    // Schedule auto-delete if needed
    if ($result && isset($result['result']['message_id'])) {
        $msg_id = $result['result']['message_id'];
        
        // Check if it's a command response (keep longer)
        if (strpos($text, '/') === 0) {
            schedule_delete($chat_id, $msg_id, 60);
        } 
        // Normal text messages
        elseif (!$reply_markup) {
            schedule_delete($chat_id, $msg_id, TEXT_DELETE_SECONDS);
        }
        // Search results
        elseif ($reply_markup && isset($reply_markup['inline_keyboard'])) {
            schedule_delete($chat_id, $msg_id, SEARCH_DELETE_SECONDS);
        }
    }
    
    return $result;
}

/**
 * Edit message
 */
function editMessage($chat_id, $message_id, $text, $reply_markup = null) {
    if (empty($chat_id) || empty($message_id) || empty($text)) {
        return null;
    }
    
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'disable_web_page_preview' => true,
        'parse_mode' => 'HTML'
    ];
    
    if ($reply_markup) {
        $params['reply_markup'] = json_encode($reply_markup, JSON_UNESCAPED_UNICODE);
    }
    
    return apiRequest('editMessageText', $params);
}

/**
 * Delete message
 */
function deleteMessage($chat_id, $message_id) {
    if (!$chat_id || !$message_id) return false;
    
    return apiRequest('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

/**
 * Answer callback query
 */
function answerCallbackQuery($callback_query_id, $text = null, $alert = false) {
    if (empty($callback_query_id)) return false;
    
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
    if (empty($chat_id) || empty($from_chat_id) || empty($message_id)) {
        return null;
    }
    
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
    if (empty($chat_id) || empty($from_chat_id) || empty($message_id)) {
        return null;
    }
    
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
    if (!$message_id || $seconds <= 0) return;
    
    global $GLOBALS;
    
    $GLOBALS['delete_queue'][] = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'delete_at' => time() + $seconds
    ];
}

/**
 * Process delete queue
 */
function process_delete_queue() {
    global $GLOBALS;
    
    $now = time();
    $remaining = [];
    $deleted = 0;
    
    foreach ($GLOBALS['delete_queue'] as $item) {
        if ($now >= $item['delete_at']) {
            if (deleteMessage($item['chat_id'], $item['message_id'])) {
                $deleted++;
            }
        } else {
            $remaining[] = $item;
        }
    }
    
    if ($deleted > 0) {
        bot_log("Deleted $deleted expired messages");
    }
    
    $GLOBALS['delete_queue'] = $remaining;
}

/**
 * Create progress bar
 */
function progress_bar($current, $total, $width = 10) {
    if ($total == 0) return str_repeat('░', $width) . " 0%";
    
    $percentage = ($current / $total) * 100;
    $filled = round($percentage / 10);
    $filled = min($filled, $width);
    $empty = $width - $filled;
    
    $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
    return $bar . " " . round($percentage) . '%';
}

/**
 * Get user display name
 */
function get_user_display($user) {
    if (is_array($user)) {
        if (!empty($user['username'])) {
            return "@{$user['username']}";
        }
        if (!empty($user['first_name'])) {
            return $user['first_name'] . ' ' . ($user['last_name'] ?? '');
        }
    }
    return "User#" . ($user['user_id'] ?? 'Unknown');
}

/**
 * Format time ago
 */
function time_ago($timestamp) {
    if (empty($timestamp)) return 'never';
    
    $time = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 0) return 'in future';
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
    return floor($diff / 31536000) . ' years ago';
}

/**
 * Truncate text
 */
function truncate($text, $length = 50, $suffix = '...') {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
}

/**
 * Generate random string
 */
function random_string($length = 8) {
    return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
}

/**
 * Check if string is JSON
 */
function is_json($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

/**
 * Safe JSON encode
 */
function safe_json_encode($data) {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
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
        bot_log("Cache hit for: $query");
        return $cached;
    }
    
    $db = get_db();
    
    // Prepare search terms for FTS5
    $terms = prepare_search_terms($query);
    
    if (empty($terms)) {
        return [];
    }
    
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
        
        if (isset($filters['type']) && $filters['type'] === 'theater') {
            $sql .= " AND (m.quality = 'Theater' OR m.quality LIKE '%CAM%' OR m.quality LIKE '%Print%')";
        }
        
        if (isset($filters['min_year'])) {
            $sql .= " AND m.year >= :min_year";
            $params[':min_year'] = $filters['min_year'];
        }
        
        if (isset($filters['max_year'])) {
            $sql .= " AND m.year <= :max_year";
            $params[':max_year'] = $filters['max_year'];
        }
    }
    
    // Order by relevance and downloads
    $sql .= " ORDER BY rank DESC, m.downloads DESC LIMIT " . MAX_SEARCH_RESULTS;
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    }
    
    $results = [];
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $channel = get_channel($row['channel_id']);
        $row['channel_name'] = $channel['name'];
        $row['channel_emoji'] = $channel['emoji'];
        $row['channel_username'] = $channel['username'];
        $row['has_header'] = $channel['header'];
        $results[] = $row;
    }
    
    // Cache results
    if (!empty($results)) {
        cache_set($cache_key, $results, 300); // 5 minutes
        bot_log("Found " . count($results) . " results for: $query");
    } else {
        bot_log("No results for: $query");
    }
    
    return $results;
}

/**
 * Prepare search terms for FTS5
 */
function prepare_search_terms($query) {
    $query = trim($query);
    
    // Remove special characters
    $query = preg_replace('/[^\p{L}\p{N}\s\-\.]/u', ' ', $query);
    
    // Split into words
    $words = array_filter(explode(' ', $query));
    
    if (empty($words)) {
        return '';
    }
    
    // Prepare FTS5 query with NEAR and prefix matching
    $terms = [];
    $exact_terms = [];
    
    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) > 1) {
            // Check if it's a year
            if (preg_match('/^\d{4}$/', $word)) {
                $terms[] = 'year:' . $word;
            } else {
                $terms[] = $word . '*'; // Prefix matching
                $exact_terms[] = $word;
            }
        }
    }
    
    // If we have multiple words, try phrase matching
    if (count($exact_terms) > 1) {
        $phrase = '"' . implode(' ', $exact_terms) . '"';
        return $phrase . ' OR ' . implode(' ', $terms);
    }
    
    return implode(' ', $terms);
}

/**
 * Advanced search with fuzzy matching
 */
function fuzzy_search($query, $threshold = 60) {
    $all_movies = load_movies();
    $results = [];
    $query_lower = strtolower($query);
    
    foreach ($all_movies as $movie) {
        $name_lower = strtolower($movie['movie_name']);
        
        // Exact match
        if ($name_lower === $query_lower) {
            $movie['score'] = 100;
            $results[] = $movie;
            continue;
        }
        
        // Contains match
        if (strpos($name_lower, $query_lower) !== false) {
            $movie['score'] = 90;
            $results[] = $movie;
            continue;
        }
        
        // Fuzzy match
        similar_text($name_lower, $query_lower, $similarity);
        if ($similarity >= $threshold) {
            $movie['score'] = $similarity;
            $results[] = $movie;
        }
    }
    
    // Sort by score
    usort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
}

/**
 * Get trending movies (based on downloads)
 */
function get_trending_movies($limit = 10) {
    $db = get_db();
    
    $result = $db->query("SELECT m.*, 
                          COUNT(dl.id) as download_count
                          FROM movies m
                          LEFT JOIN download_logs dl ON m.id = dl.movie_id AND dl.created_at >= date('now', '-7 days')
                          GROUP BY m.id
                          ORDER BY download_count DESC, m.downloads DESC, m.added_date DESC
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

/**
 * Get most downloaded movies
 */
function get_most_downloaded($limit = 10) {
    $db = get_db();
    
    $result = $db->query("SELECT * FROM movies 
                          WHERE downloads > 0 
                          ORDER BY downloads DESC, added_date DESC 
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
 * Get random movies
 */
function get_random_movies($limit = 5) {
    $db = get_db();
    
    $result = $db->query("SELECT * FROM movies 
                          ORDER BY RANDOM() 
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
 * Get movies by year range
 */
function get_movies_by_year_range($start_year, $end_year, $limit = 50) {
    $db = get_db();
    
    $stmt = $db->prepare("SELECT * FROM movies 
                          WHERE year >= :start AND year <= :end 
                          ORDER BY year DESC, added_date DESC 
                          LIMIT $limit");
    $stmt->bindValue(':start', $start_year, SQLITE3_INTEGER);
    $stmt->bindValue(':end', $end_year, SQLITE3_INTEGER);
    
    $result = $stmt->execute();
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
    
    if (!$user_id) return null;
    
    // Check cache first
    if (isset($GLOBALS['user_cache'][$user_id])) {
        $user = $GLOBALS['user_cache'][$user_id];
    } else {
        // Load from database
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :id");
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $user = $row;
            $user['settings'] = json_decode($user['settings'] ?? '{}', true);
            $user['achievements'] = json_decode($user['achievements'] ?? '[]', true);
        } else {
            // New user
            $default_settings = get_default_settings();
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
                'settings' => $default_settings,
                'achievements' => [],
                'banned' => 0,
                'notes' => ''
            ];
            
            // Insert new user
            $stmt = $db->prepare("INSERT INTO users 
                (user_id, first_name, last_name, username, joined, last_active, points, language, settings, achievements)
                VALUES (:id, :fname, :lname, :uname, :joined, :last, :points, :lang, :settings, :achievements)");
            
            $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(':fname', $user['first_name'], SQLITE3_TEXT);
            $stmt->bindValue(':lname', $user['last_name'], SQLITE3_TEXT);
            $stmt->bindValue(':uname', $user['username'], SQLITE3_TEXT);
            $stmt->bindValue(':joined', $user['joined'], SQLITE3_TEXT);
            $stmt->bindValue(':last', $user['last_active'], SQLITE3_TEXT);
            $stmt->bindValue(':points', $user['points'], SQLITE3_INTEGER);
            $stmt->bindValue(':lang', $user['language'], SQLITE3_TEXT);
            $stmt->bindValue(':settings', json_encode($user['settings']), SQLITE3_TEXT);
            $stmt->bindValue(':achievements', json_encode($user['achievements']), SQLITE3_TEXT);
            $stmt->execute();
            
            // Update daily stats
            update_daily_stat('users', 1);
            
            bot_log("New user registered: $user_id (" . get_user_display($user) . ")");
            
            // Check for welcome achievement
            check_achievements($user_id, 'register');
        }
        
        $GLOBALS['user_cache'][$user_id] = $user;
    }
    
    // Update activity
    if ($action) {
        $points_map = [
            'search' => POINTS_PER_SEARCH,
            'download' => POINTS_PER_DOWNLOAD,
            'request' => POINTS_PER_REQUEST,
            'found' => POINTS_PER_FOUND,
            'login' => POINTS_PER_LOGIN
        ];
        
        $user['last_active'] = date('Y-m-d H:i:s');
        $user[$action . 's'] = ($user[$action . 's'] ?? 0) + 1;
        $user['points'] += $points_map[$action] ?? 1;
        
        // Check for daily login bonus
        if ($action === 'login') {
            $last_login = strtotime($user['last_active'] ?? '2000-01-01');
            if (date('Y-m-d', $last_login) !== date('Y-m-d')) {
                $user['points'] += POINTS_PER_LOGIN;
                bot_log("Daily login bonus for $user_id");
            }
        }
        
        // Batch update every 5 actions
        $user['_pending'] = ($user['_pending'] ?? 0) + 1;
        if ($user['_pending'] >= 5) {
            save_user($user);
            $user['_pending'] = 0;
        }
        
        // Check achievements
        check_achievements($user_id, $action);
        
        $GLOBALS['user_cache'][$user_id] = $user;
    }
    
    return $user;
}

/**
 * Save user to database
 */
function save_user($user) {
    if (!$user || !isset($user['user_id'])) return false;
    
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
        settings = :settings,
        achievements = :achievements,
        banned = :banned,
        notes = :notes
        WHERE user_id = :id");
    
    $stmt->bindValue(':id', $user['user_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':fname', $user['first_name'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':lname', $user['last_name'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':uname', $user['username'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':last', $user['last_active'] ?? date('Y-m-d H:i:s'), SQLITE3_TEXT);
    $stmt->bindValue(':points', $user['points'] ?? 0, SQLITE3_INTEGER);
    $stmt->bindValue(':searches', $user['searches'] ?? 0, SQLITE3_INTEGER);
    $stmt->bindValue(':downloads', $user['downloads'] ?? 0, SQLITE3_INTEGER);
    $stmt->bindValue(':requests', $user['requests'] ?? 0, SQLITE3_INTEGER);
    $stmt->bindValue(':lang', $user['language'] ?? 'hinglish', SQLITE3_TEXT);
    $stmt->bindValue(':settings', json_encode($user['settings'] ?? []), SQLITE3_TEXT);
    $stmt->bindValue(':achievements', json_encode($user['achievements'] ?? []), SQLITE3_TEXT);
    $stmt->bindValue(':banned', $user['banned'] ?? 0, SQLITE3_INTEGER);
    $stmt->bindValue(':notes', $user['notes'] ?? '', SQLITE3_TEXT);
    
    return $stmt->execute();
}

/**
 * Get user by ID
 */
function get_user($user_id) {
    $user = track_user($user_id);
    return $user;
}

/**
 * Get user settings
 */
function get_user_settings($user_id) {
    $user = track_user($user_id);
    return $user['settings'] ?? get_default_settings();
}

/**
 * Update user settings
 */
function update_user_settings($user_id, $settings) {
    $current = get_user_settings($user_id);
    $updated = array_merge($current, $settings);
    
    $db = get_db();
    $stmt = $db->prepare("UPDATE users SET settings = :settings WHERE user_id = :id");
    $stmt->bindValue(':settings', json_encode($updated), SQLITE3_TEXT);
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $stmt->execute();
    
    // Clear cache
    global $GLOBALS;
    unset($GLOBALS['user_cache'][$user_id]);
    
    bot_log("User $user_id updated settings");
    
    return $updated;
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
        'delete_seconds' => 120,
        'notifications' => true,
        'theme' => 'dark',
        'language' => 'hinglish'
    ];
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
 * Get user rank based on points
 */
function get_user_rank($points) {
    if ($points >= 5000) return '👑 Legend';
    if ($points >= 2000) return '💎 Diamond';
    if ($points >= 1000) return '🥇 Gold';
    if ($points >= 500) return '🥈 Silver';
    if ($points >= 200) return '🥉 Bronze';
    if ($points >= 100) return '⭐ Pro';
    if ($points >= 50) return '🚀 Advanced';
    if ($points >= 20) return '📈 Intermediate';
    if ($points >= 10) return '🌱 Beginner';
    return '🆕 Newbie';
}

/**
 * Get user level based on points
 */
function get_user_level($points) {
    return floor($points / 100) + 1;
}

/**
 * Get next level points
 */
function get_next_level_points($points) {
    $current_level = get_user_level($points);
    return ($current_level * 100) - $points;
}

/**
 * Get leaderboard
 */
function get_leaderboard($limit = 10, $type = 'points') {
    $db = get_db();
    
    $order_by = match($type) {
        'points' => 'points DESC',
        'searches' => 'searches DESC',
        'downloads' => 'downloads DESC',
        'requests' => 'requests DESC',
        default => 'points DESC'
    };
    
    $result = $db->query("SELECT user_id, username, first_name, last_name, 
                          points, searches, downloads, requests, last_active
                          FROM users 
                          WHERE banned = 0 AND points > 0 
                          ORDER BY $order_by 
                          LIMIT $limit");
    
    $users = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['rank'] = get_user_rank($row['points']);
        $row['level'] = get_user_level($row['points']);
        $row['display_name'] = get_user_display($row);
        $users[] = $row;
    }
    
    return $users;
}

/**
 * Check and award achievements
 */
function check_achievements($user_id, $action) {
    $user = get_user($user_id);
    $achievements = $user['achievements'] ?? [];
    $new_achievements = [];
    
    $achievement_defs = [
        'first_search' => [
            'name' => '🔍 Explorer',
            'description' => 'First search',
            'condition' => ($user['searches'] ?? 0) >= 1,
            'points' => 10
        ],
        'search_100' => [
            'name' => '🎯 Master Searcher',
            'description' => '100 searches',
            'condition' => ($user['searches'] ?? 0) >= 100,
            'points' => 100
        ],
        'first_download' => [
            'name' => '📥 Collector',
            'description' => 'First download',
            'condition' => ($user['downloads'] ?? 0) >= 1,
            'points' => 20
        ],
        'download_50' => [
            'name' => '💾 Power User',
            'description' => '50 downloads',
            'condition' => ($user['downloads'] ?? 0) >= 50,
            'points' => 200
        ],
        'download_500' => [
            'name' => '⚡ Download Machine',
            'description' => '500 downloads',
            'condition' => ($user['downloads'] ?? 0) >= 500,
            'points' => 1000
        ],
        'first_request' => [
            'name' => '📝 Requester',
            'description' => 'First request',
            'condition' => ($user['requests'] ?? 0) >= 1,
            'points' => 15
        ],
        'request_10' => [
            'name' => '🎬 Movie Critic',
            'description' => '10 requests',
            'condition' => ($user['requests'] ?? 0) >= 10,
            'points' => 150
        ],
        'points_100' => [
            'name' => '⭐ Rising Star',
            'description' => '100 points',
            'condition' => ($user['points'] ?? 0) >= 100,
            'points' => 50
        ],
        'points_1000' => [
            'name' => '👑 Elite Member',
            'description' => '1000 points',
            'condition' => ($user['points'] ?? 0) >= 1000,
            'points' => 500
        ]
    ];
    
    foreach ($achievement_defs as $key => $def) {
        if (!in_array($key, $achievements) && $def['condition']) {
            $achievements[] = $key;
            $new_achievements[] = $def;
            
            // Award bonus points
            $user['points'] += $def['points'];
            
            bot_log("User $user_id earned achievement: {$def['name']}");
        }
    }
    
    if (!empty($new_achievements)) {
        $db = get_db();
        $stmt = $db->prepare("UPDATE users SET achievements = :ach, points = :points WHERE user_id = :id");
        $stmt->bindValue(':ach', json_encode($achievements), SQLITE3_TEXT);
        $stmt->bindValue(':points', $user['points'], SQLITE3_INTEGER);
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Clear cache
        global $GLOBALS;
        unset($GLOBALS['user_cache'][$user_id]);
    }
    
    return $new_achievements;
}

// ==============================
// 12. REQUEST SYSTEM
// ==============================

/**
 * Add movie request
 */
function add_request($user_id, $movie_name) {
    $user = track_user($user_id);
    
    // Check if user is banned
    if ($user['banned']) {
        return ['success' => false, 'message' => 'banned'];
    }
    
    // Check if movie already exists
    if (movie_exists($movie_name)) {
        return ['success' => false, 'message' => 'exists'];
    }
    
    // Check daily limit
    $db = get_db();
    
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM requests 
                          WHERE user_id = :uid AND date(created_at) = :today");
    $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':today', $today, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $count = $row ? $row['count'] : 0;
    
    if ($count >= DAILY_REQUEST_LIMIT) {
        return ['success' => false, 'message' => 'daily_limit', 'count' => $count];
    }
    
    // Check if already requested
    $stmt = $db->prepare("SELECT id FROM requests 
                          WHERE user_id = :uid AND movie_name LIKE :name AND status = 'pending'");
    $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':name', '%' . $movie_name . '%', SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if ($result->fetchArray()) {
        return ['success' => false, 'message' => 'already_requested'];
    }
    
    // Add request
    $stmt = $db->prepare("INSERT INTO requests (user_id, movie_name, language) 
                          VALUES (:uid, :name, :lang)");
    $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':name', $movie_name, SQLITE3_TEXT);
    $stmt->bindValue(':lang', detect_language_text($movie_name), SQLITE3_TEXT);
    $stmt->execute();
    
    $request_id = $db->lastInsertRowID();
    
    // Update user stats
    track_user($user_id, [], 'request');
    
    // Update daily stat
    update_daily_stat('requests', 1);
    
    // Log activity
    log_user_activity($user_id, 'request', null, ['movie' => $movie_name, 'request_id' => $request_id]);
    
    // Notify admin
    if (defined('ADMIN_ID') && ADMIN_ID > 0) {
        $admin_msg = "📝 <b>New Movie Request #$request_id</b>\n\n";
        $admin_msg .= "🎬 <b>Movie:</b> $movie_name\n";
        $admin_msg .= "👤 <b>User:</b> " . get_user_display($user) . "\n";
        $admin_msg .= "🆔 <b>User ID:</b> <code>$user_id</code>\n";
        $admin_msg .= "📊 <b>Today:</b> " . ($count + 1) . "/" . DAILY_REQUEST_LIMIT . "\n";
        $admin_msg .= "⭐ <b>Points:</b> {$user['points']}\n";
        $admin_msg .= "🕐 <b>Time:</b> " . date('H:i:s');
        
        sendMessage(ADMIN_ID, $admin_msg, null, 'HTML');
    }
    
    bot_log("Request #$request_id added: $movie_name by $user_id");
    
    return [
        'success' => true, 
        'id' => $request_id,
        'count' => $count + 1,
        'remaining' => DAILY_REQUEST_LIMIT - ($count + 1)
    ];
}

/**
 * Get user requests
 */
function get_user_requests($user_id, $limit = 10, $status = null) {
    $db = get_db();
    
    $sql = "SELECT * FROM requests WHERE user_id = :uid";
    if ($status) {
        $sql .= " AND status = :status";
    }
    $sql .= " ORDER BY created_at DESC LIMIT $limit";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
    if ($status) {
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    }
    
    $result = $stmt->execute();
    $requests = [];
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['time_ago'] = time_ago($row['created_at']);
        $requests[] = $row;
    }
    
    return $requests;
}

/**
 * Get all pending requests
 */
function get_pending_requests($limit = 50) {
    $db = get_db();
    
    $result = $db->query("SELECT r.*, u.username, u.first_name, u.last_name, u.user_id
                          FROM requests r
                          JOIN users u ON r.user_id = u.user_id
                          WHERE r.status = 'pending'
                          ORDER BY r.votes DESC, r.created_at ASC
                          LIMIT $limit");
    
    $requests = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['user_display'] = get_user_display($row);
        $row['time_ago'] = time_ago($row['created_at']);
        $requests[] = $row;
    }
    
    return $requests;
}

/**
 * Complete a request
 */
function complete_request($request_id, $movie_name = null, $message_id = null, $channel_id = null) {
    $db = get_db();
    
    // Get request details
    $stmt = $db->prepare("SELECT * FROM requests WHERE id = :id");
    $stmt->bindValue(':id', $request_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $request = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$request) {
        return false;
    }
    
    // Update request status
    $stmt = $db->prepare("UPDATE requests SET 
                          status = 'completed', 
                          completed_at = CURRENT_TIMESTAMP 
                          WHERE id = :id");
    $stmt->bindValue(':id', $request_id, SQLITE3_INTEGER);
    $stmt->execute();
    
    // Notify user
    $user = get_user($request['user_id']);
    $movie = $movie_name ?: $request['movie_name'];
    
    $msg = "✅ <b>Good News!</b>\n\n";
    $msg .= "Your requested movie <b>$movie</b> has been added!\n\n";
    
    if ($message_id && $channel_id) {
        $link = get_channel_link($channel_id, $message_id);
        $msg .= "🔗 <b>Direct Link:</b>\n$link\n\n";
    }
    
    $msg .= "🎬 Enjoy watching!";
    
    sendMessage($request['user_id'], $msg, null, 'HTML');
    
    // Award bonus points
    $db->exec("UPDATE users SET points = points + 50 WHERE user_id = {$request['user_id']}");
    
    bot_log("Request #$request_id completed for $movie");
    
    return true;
}

/**
 * Vote for a request
 */
function vote_request($user_id, $request_id) {
    $db = get_db();
    
    // Check if already voted
    $stmt = $db->prepare("SELECT id FROM request_votes WHERE user_id = :uid AND request_id = :rid");
    $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':rid', $request_id, SQLITE3_INTEGER);
    
    if ($stmt->execute()->fetchArray()) {
        return false;
    }
    
    // Add vote
    $stmt = $db->prepare("INSERT INTO request_votes (user_id, request_id) VALUES (:uid, :rid)");
    $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':rid', $request_id, SQLITE3_INTEGER);
    $stmt->execute();
    
    // Update vote count
    $db->exec("UPDATE requests SET votes = votes + 1 WHERE id = $request_id");
    
    return true;
}

/**
 * Check if movie exists in database
 */
function movie_exists($movie_name) {
    $db = get_db();
    
    $stmt = $db->prepare("SELECT id FROM movies WHERE movie_name LIKE :name LIMIT 1");
    $stmt->bindValue(':name', '%' . $movie_name . '%', SQLITE3_TEXT);
    $result = $stmt->execute();
    
    return $result->fetchArray() !== false;
}

/**
 * Notify waiting users when movie is added
 */
function notify_waiting_users($movie_name, $channel_id) {
    global $GLOBALS;
    
    $key = strtolower(trim($movie_name));
    
    if (isset($GLOBALS['waiting_users'][$key]) && !empty($GLOBALS['waiting_users'][$key])) {
        $channel = get_channel($channel_id);
        $link = $channel['username'] ? get_channel_username_link($channel['username']) : null;
        $notified = 0;
        
        foreach ($GLOBALS['waiting_users'][$key] as $user_id) {
            // Don't notify if user is banned
            $user = get_user($user_id);
            if ($user['banned']) continue;
            
            $msg = "🎉 <b>Good News!</b>\n\n";
            $msg .= "Your requested movie <b>$movie_name</b> has been added!\n\n";
            
            if ($link) {
                $msg .= "📢 <b>Join:</b> {$channel['username']}\n";
                $msg .= "🔗 <b>Link:</b> $link\n\n";
            } else {
                $msg .= "📢 <b>Channel:</b> {$channel['name']}\n";
            }
            
            $msg .= "🔍 Use /search $movie_name to get it!";
            
            if (sendMessage($user_id, $msg, null, 'HTML')) {
                $notified++;
            }
        }
        
        unset($GLOBALS['waiting_users'][$key]);
        bot_log("Notified $notified users for $movie_name");
    }
}

/**
 * Add user to waiting list
 */
function add_to_waiting($user_id, $movie_name) {
    global $GLOBALS;
    
    $key = strtolower(trim($movie_name));
    
    if (!isset($GLOBALS['waiting_users'][$key])) {
        $GLOBALS['waiting_users'][$key] = [];
    }
    
    // Limit waiting users per movie
    if (count($GLOBALS['waiting_users'][$key]) < MAX_WAITING_USERS) {
        if (!in_array($user_id, $GLOBALS['waiting_users'][$key])) {
            $GLOBALS['waiting_users'][$key][] = $user_id;
            return true;
        }
    }
    
    return false;
}

/**
 * Log user activity
 */
function log_user_activity($user_id, $action, $movie_id = null, $details = null) {
    $db = get_db();
    
    $stmt = $db->prepare("INSERT INTO user_activity (user_id, action, movie_id, details, created_at) 
                          VALUES (:uid, :action, :mid, :details, CURRENT_TIMESTAMP)");
    $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':action', $action, SQLITE3_TEXT);
    $stmt->bindValue(':mid', $movie_id, $movie_id ? SQLITE3_INTEGER : SQLITE3_NULL);
    $stmt->bindValue(':details', $details ? json_encode($details) : null, SQLITE3_TEXT);
    $stmt->execute();
}

// ==============================
// 13. DELIVERY SYSTEM
// ==============================

/**
 * Deliver movie to user
 */
function deliver_movie($chat_id, $movie, $user_id = null) {
    if (empty($movie) || empty($chat_id)) return false;
    
    $channel_id = $movie['channel_id'];
    $message_id = $movie['message_id'];
    $has_header = channel_has_header($channel_id);
    
    // Check if message ID is valid
    if (!$message_id || $message_id <= 0) {
        sendMessage($chat_id, "❌ Invalid message ID for this movie");
        return false;
    }
    
    // Add attribution if user provided
    $caption = null;
    if ($user_id) {
        $user = track_user($user_id);
        $username = get_user_display($user);
        
        $caption = "📥 <b>Requested by:</b> $username\n";
        $caption .= "🎬 <b>Movie:</b> {$movie['movie_name']}\n";
        $caption .= "📊 <b>Quality:</b> {$movie['quality']}\n";
        $caption .= "🗣️ <b>Language:</b> {$movie['language']}\n";
        if ($movie['year']) {
            $caption .= "📅 <b>Year:</b> {$movie['year']}\n";
        }
        $caption .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $caption .= "⚡ @EntertainmentTadkaBot";
    }
    
    // Get channel info for logging
    $channel = get_channel($channel_id);
    
    // Deliver based on channel type
    if ($has_header) {
        // Public channel - forward (shows header)
        bot_log("Forwarding from public channel: {$channel['name']}");
        $result = forwardMessage($chat_id, $channel_id, $message_id);
        $method = 'forward';
    } else {
        // Private channel - copy (no header)
        bot_log("Copying from private channel: {$channel['name']}");
        $result = copyMessage($chat_id, $channel_id, $message_id, $caption);
        $method = 'copy';
    }
    
    // Update stats
    if ($result && isset($result['ok']) && $result['ok']) {
        update_stat('total_downloads', 1);
        update_daily_stat('downloads', 1);
        
        if ($user_id) {
            track_user($user_id, [], 'download');
            
            // Update movie download count
            $db = get_db();
            $stmt = $db->prepare("UPDATE movies SET downloads = downloads + 1, last_accessed = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->bindValue(':id', $movie['id'], SQLITE3_INTEGER);
            $stmt->execute();
            
            // Log download
            $db->exec("INSERT INTO download_logs (user_id, movie_id, movie_name, method) 
                      VALUES ($user_id, {$movie['id']}, '{$movie['movie_name']}', '$method')");
            
            log_user_activity($user_id, 'download', $movie['id'], ['method' => $method]);
        }
        
        // Get message ID for auto-delete
        $msg_id = null;
        if ($method === 'forward') {
            $msg_id = $result['result']['message_id'] ?? null;
        } else {
            $msg_id = $result['result']['id'] ?? null;
        }
        
        // Schedule auto-delete based on user settings
        if ($user_id) {
            $settings = get_user_settings($user_id);
            if ($settings['auto_delete'] ?? true) {
                $seconds = $settings['delete_seconds'] ?? FILE_DELETE_SECONDS;
                schedule_delete($chat_id, $msg_id, $seconds);
            }
        } else {
            schedule_delete($chat_id, $msg_id, FILE_DELETE_SECONDS);
        }
        
        return true;
    }
    
    // Fallback to link
    bot_log("Delivery failed for {$movie['movie_name']}, sending link", 'WARNING');
    $link = get_channel_link($channel_id, $message_id);
    $msg = "🔗 <b>Direct Link:</b>\n$link\n\n";
    $msg .= "⚠️ Join the channel to access this content.";
    sendMessage($chat_id, $msg, null, 'HTML');
    
    return false;
}

/**
 * Batch deliver movies with progress
 */
function batch_deliver($chat_id, $movies, $user_id = null, $batch_name = 'movies') {
    $total = count($movies);
    if ($total === 0) {
        sendMessage($chat_id, "📭 No movies to send!");
        return;
    }
    
    $sent = 0;
    $failed = 0;
    
    // Progress message
    $progress_msg = sendMessage($chat_id, 
        "📦 <b>Preparing to send $total $batch_name...</b>\n\n" . 
        progress_bar(0, $total), null, 'HTML');
    
    $progress_id = $progress_msg['result']['message_id'] ?? null;
    
    foreach ($movies as $i => $movie) {
        if (deliver_movie($chat_id, $movie, $user_id)) {
            $sent++;
        } else {
            $failed++;
        }
        
        // Update progress every 2 movies
        if (($i % 2 == 0 || $i == $total - 1) && $progress_id) {
            $percent = round(($i + 1) / $total * 100);
            editMessage($chat_id, $progress_id,
                "📦 <b>Sending $batch_name...</b>\n\n" .
                "✅ Sent: $sent\n" .
                "❌ Failed: $failed\n" .
                "📊 Progress: $percent%\n\n" .
                progress_bar($i + 1, $total)
            );
        }
        
        // Small delay to avoid rate limiting
        usleep(300000); // 0.3 sec
    }
    
    // Final message
    if ($progress_id) {
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
    
    bot_log("Batch delivered: $sent/$total to $chat_id");
}

// ==============================
// 14. PAGINATION SYSTEM
// ==============================

/**
 * Paginate items
 */
function paginate($items, $page = 1, $per_page = ITEMS_PER_PAGE) {
    $total = count($items);
    $total_pages = max(1, ceil($total / $per_page));
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
        'end' => min($offset + $per_page, $total),
        'per_page' => $per_page
    ];
}

/**
 * Build pagination keyboard
 */
function pagination_keyboard($page, $total_pages, $session_id, $filters = [], $extra_buttons = []) {
    $keyboard = ['inline_keyboard' => []];
    
    // Navigation row
    $nav_row = [];
    
    if ($page > 1) {
        $nav_row[] = ['text' => '⏮️ First', 'callback_data' => "page_1_{$session_id}"];
        $nav_row[] = ['text' => '◀️ Prev', 'callback_data' => "page_" . ($page - 1) . "_{$session_id}"];
    }
    
    $nav_row[] = ['text' => "📌 {$page}/{$total_pages}", 'callback_data' => 'current_page'];
    
    if ($page < $total_pages) {
        $nav_row[] = ['text' => 'Next ▶️', 'callback_data' => "page_" . ($page + 1) . "_{$session_id}"];
        $nav_row[] = ['text' => 'Last ⏭️', 'callback_data' => "page_{$total_pages}_{$session_id}"];
    }
    
    $keyboard['inline_keyboard'][] = $nav_row;
    
    // Page number row (for quick jump)
    if ($total_pages > 1) {
        $page_row = [];
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $page) {
                $page_row[] = ['text' => "▪️{$i}▪️", 'callback_data' => 'current_page'];
            } else {
                $page_row[] = ['text' => "$i", 'callback_data' => "page_{$i}_{$session_id}"];
            }
        }
        
        $keyboard['inline_keyboard'][] = $page_row;
    }
    
    // Action row
    $action_row = [
        ['text' => '📦 Send All', 'callback_data' => "sendall_page_{$page}_{$session_id}"],
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
        
        $filter_row2 = [
            ['text' => '📅 2024', 'callback_data' => "filter_year_2024_{$session_id}"],
            ['text' => '📅 2023', 'callback_data' => "filter_year_2023_{$session_id}"],
            ['text' => '📅 2022', 'callback_data' => "filter_year_2022_{$session_id}"]
        ];
        $keyboard['inline_keyboard'][] = $filter_row2;
    } else {
        $filter_row = [
            ['text' => '🧹 Clear Filters', 'callback_data' => "filter_clear_{$session_id}"]
        ];
        $keyboard['inline_keyboard'][] = $filter_row;
    }
    
    // Extra buttons if provided
    if (!empty($extra_buttons)) {
        $keyboard['inline_keyboard'][] = $extra_buttons;
    }
    
    return $keyboard;
}

/**
 * Show movie browser
 */
function show_browser($chat_id, $page = 1, $filters = [], $session_id = null) {
    global $GLOBALS;
    
    $movies = load_movies();
    
    if (empty($movies)) {
        sendMessage($chat_id, "📭 No movies yet! Be the first to add.");
        return;
    }
    
    // Apply filters
    if (!empty($filters)) {
        $filtered_movies = [];
        foreach ($movies as $movie) {
            $include = true;
            
            foreach ($filters as $key => $value) {
                if ($key == 'hd') {
                    if (!in_array($movie['quality'], ['1080p', '4K', 'BluRay'])) {
                        $include = false;
                        break;
                    }
                } elseif ($key == 'theater') {
                    if ($movie['quality'] != 'Theater' && strpos($movie['quality'], 'CAM') === false) {
                        $include = false;
                        break;
                    }
                } elseif ($key == 'hindi') {
                    if ($movie['language'] != 'Hindi') {
                        $include = false;
                        break;
                    }
                } elseif ($key == 'year') {
                    if (($movie['year'] ?? 0) != $value) {
                        $include = false;
                        break;
                    }
                }
            }
            
            if ($include) {
                $filtered_movies[] = $movie;
            }
        }
        $movies = $filtered_movies;
    }
    
    // Generate session if not provided
    if (!$session_id) {
        $session_id = uniqid('browse_', true);
        $GLOBALS['pagination_sessions'][$session_id] = [
            'filters' => $filters,
            'created' => time(),
            'user_id' => $chat_id
        ];
    }
    
    $p = paginate($movies, $page);
    
    // Build message
    $msg = "🎬 <b>Movie Browser</b>\n\n";
    $msg .= "📊 <b>Total:</b> {$p['total']} movies\n";
    $msg .= "📄 <b>Page:</b> {$p['page']}/{$p['total_pages']}\n";
    $msg .= "📋 <b>Items:</b> {$p['start']}-{$p['end']}\n\n";
    
    if (!empty($filters)) {
        $filter_names = array_keys($filters);
        $msg .= "🔍 <b>Active Filters:</b> " . implode(', ', $filter_names) . "\n\n";
    }
    
    foreach ($p['items'] as $i => $movie) {
        $num = $p['start'] + $i;
        $quality_emoji = match($movie['quality']) {
            '4K' => '🔥',
            '1080p' => '💎',
            '720p' => '⭐',
            'Theater' => '🎭',
            default => '📀'
        };
        
        $msg .= "{$num}. {$movie['channel_emoji']} <b>{$movie['movie_name']}</b>\n";
        $msg .= "   {$quality_emoji} {$movie['quality']} | 🗣️ {$movie['language']}";
        if ($movie['year']) {
            $msg .= " | 📅 {$movie['year']}";
        }
        $msg .= "\n   📥 {$movie['downloads']} downloads\n\n";
    }
    
    $keyboard = pagination_keyboard($p['page'], $p['total_pages'], $session_id, $filters);
    
    // Delete old message if exists
    if (isset($GLOBALS['pagination_sessions'][$session_id]['last_msg'])) {
        deleteMessage($chat_id, $GLOBALS['pagination_sessions'][$session_id]['last_msg']);
    }
    
    $result = sendMessage($chat_id, $msg, $keyboard, 'HTML');
    
    if ($result && isset($result['result']['message_id'])) {
        $GLOBALS['pagination_sessions'][$session_id]['last_msg'] = $result['result']['message_id'];
    }
}

/**
 * Show filtered browser
 */
function show_filtered_browser($chat_id, $filter_type, $filter_value, $page = 1) {
    $filters = [$filter_type => $filter_value];
    $session_id = uniqid('filter_', true);
    show_browser($chat_id, $page, $filters, $session_id);
}

/**
 * Get browser session
 */
function get_browser_session($session_id) {
    global $GLOBALS;
    
    if (isset($GLOBALS['pagination_sessions'][$session_id])) {
        $session = $GLOBALS['pagination_sessions'][$session_id];
        
        // Check if session expired
        if (time() - $session['created'] < SESSION_TIMEOUT) {
            return $session;
        } else {
            unset($GLOBALS['pagination_sessions'][$session_id]);
        }
    }
    
    return null;
}

// ==============================
// 15. STATISTICS SYSTEM
// ==============================

/**
 * Update global statistics
 */
function update_stat($key, $increment = 1) {
    $stats_file = STATS_FILE;
    
    if (!file_exists($stats_file)) {
        $stats = [
            'total_movies' => 0,
            'total_users' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
            'successful_searches' => 0,
            'failed_searches' => 0,
            'daily_activity' => [],
            'last_updated' => date('Y-m-d H:i:s'),
            'version' => BOT_VERSION
        ];
    } else {
        $stats = json_decode(file_get_contents($stats_file), true);
        if (!is_array($stats)) $stats = [];
    }
    
    $stats[$key] = ($stats[$key] ?? 0) + $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    
    file_put_contents($stats_file, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Update daily statistics
 */
function update_daily_stat($key, $increment = 1) {
    $db = get_db();
    $today = date('Y-m-d');
    
    $stmt = $db->prepare("INSERT INTO stats (date, searches, downloads, users, requests, messages, commands)
                          VALUES (:date, 0, 0, 0, 0, 0, 0)
                          ON CONFLICT(date) DO UPDATE SET 
                          $key = $key + :inc");
    $stmt->bindValue(':date', $today, SQLITE3_TEXT);
    $stmt->bindValue(':inc', $increment, SQLITE3_INTEGER);
    $stmt->execute();
}

/**
 * Get system statistics
 */
function get_system_stats() {
    $stats = [];
    if (file_exists(STATS_FILE)) {
        $stats = json_decode(file_get_contents(STATS_FILE), true);
    }
    
    if (!is_array($stats)) $stats = [];
    
    $db = get_db();
    
    // Get today's stats
    $today = date('Y-m-d');
    $result = $db->query("SELECT * FROM stats WHERE date = '$today'");
    $today_stats = $result->fetchArray(SQLITE3_ASSOC) ?: [];
    
    // Get user count
    $result = $db->query("SELECT COUNT(*) as count FROM users WHERE banned = 0");
    $user_count = $result->fetchArray(SQLITE3_ASSOC)['count'] ?? 0;
    
    // Get active users (last hour)
    $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $result = $db->query("SELECT COUNT(*) as count FROM users WHERE last_active > '$hour_ago'");
    $active_now = $result->fetchArray(SQLITE3_ASSOC)['count'] ?? 0;
    
    // Get active users (last 24 hours)
    $day_ago = date('Y-m-d H:i:s', strtotime('-1 day'));
    $result = $db->query("SELECT COUNT(*) as count FROM users WHERE last_active > '$day_ago'");
    $active_today = $result->fetchArray(SQLITE3_ASSOC)['count'] ?? 0;
    
    // Get request counts
    $result = $db->query("SELECT COUNT(*) as count FROM requests WHERE status = 'pending'");
    $pending_requests = $result->fetchArray(SQLITE3_ASSOC)['count'] ?? 0;
    
    $result = $db->query("SELECT COUNT(*) as count FROM requests WHERE status = 'completed'");
    $completed_requests = $result->fetchArray(SQLITE3_ASSOC)['count'] ?? 0;
    
    $result = $db->query("SELECT COUNT(*) as count FROM requests");
    $total_requests = $result->fetchArray(SQLITE3_ASSOC)['count'] ?? 0;
    
    // Get database size
    $db_size = file_exists(DB_FILE) ? filesize(DB_FILE) : 0;
    
    // Get cache stats
    $cache_stats = cache_stats();
    
    global $GLOBALS;
    
    return [
        'total_movies' => $stats['total_movies'] ?? count_movies(),
        'total_users' => $user_count,
        'total_searches' => $stats['total_searches'] ?? 0,
        'total_downloads' => $stats['total_downloads'] ?? 0,
        'success_rate' => calculate_success_rate($stats),
        'active_now' => $active_now,
        'active_today' => $active_today,
        'today_searches' => $today_stats['searches'] ?? 0,
        'today_downloads' => $today_stats['downloads'] ?? 0,
        'today_users' => $today_stats['users'] ?? 0,
        'today_requests' => $today_stats['requests'] ?? 0,
        'pending_requests' => $pending_requests,
        'completed_requests' => $completed_requests,
        'total_requests' => $total_requests,
        'uptime' => format_uptime(),
        'memory_usage' => memory_get_usage(true) / 1024 / 1024,
        'peak_memory' => memory_get_peak_usage(true) / 1024 / 1024,
        'db_size' => format_bytes($db_size),
        'cache_stats' => $cache_stats,
        'api_calls' => $GLOBALS['api_calls'] ?? 0,
        'errors' => count($GLOBALS['errors'] ?? []),
        'warnings' => count($GLOBALS['warnings'] ?? []),
        'delete_queue' => count($GLOBALS['delete_queue'] ?? []),
        'waiting_users' => count($GLOBALS['waiting_users'] ?? []),
        'version' => BOT_VERSION
    ];
}

/**
 * Calculate search success rate
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
    $seconds = time() - ($GLOBALS['start_time'] ?? time());
    
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    if ($days > 0) {
        return "{$days}d {$hours}h {$minutes}m";
    } elseif ($hours > 0) {
        return "{$hours}h {$minutes}m {$secs}s";
    } elseif ($minutes > 0) {
        return "{$minutes}m {$secs}s";
    } else {
        return "{$secs}s";
    }
}

/**
 * Get statistics chart (ASCII)
 */
function get_stats_chart($days = 7) {
    $db = get_db();
    $end = date('Y-m-d');
    $start = date('Y-m-d', strtotime("-{$days} days"));
    
    $result = $db->query("SELECT * FROM stats WHERE date BETWEEN '$start' AND '$end' ORDER BY date");
    
    $data = [];
    $max_value = 0;
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data[$row['date']] = $row;
        $max_value = max($max_value, $row['searches'] ?? 0, $row['downloads'] ?? 0);
    }
    
    if (empty($data)) {
        return "No data available";
    }
    
    $chart = "📊 <b>Last {$days} Days Activity</b>\n\n";
    $scale = $max_value > 50 ? ceil($max_value / 20) : 1;
    
    foreach ($data as $date => $stats) {
        $short_date = substr($date, 5); // MM-DD
        $searches = $stats['searches'] ?? 0;
        $downloads = $stats['downloads'] ?? 0;
        
        $search_bar = str_repeat('█', min(20, ceil($searches / $scale)));
        $download_bar = str_repeat('▓', min(20, ceil($downloads / $scale)));
        
        $chart .= "📅 {$short_date}\n";
        $chart .= "🔍 {$search_bar} {$searches}\n";
        $chart .= "📥 {$download_bar} {$downloads}\n\n";
    }
    
    $chart .= "Scale: 1 bar = {$scale} actions";
    
    return $chart;
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
            'english' => "🎯 Multiple versions found! Which one?"
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
        ],
        'settings' => [
            'hindi' => "⚙️ सेटिंग्स:",
            'hinglish' => "⚙️ Settings:",
            'english' => "⚙️ Settings:"
        ],
        'join' => [
            'hindi' => "📢 हमारे चैनल ज्वाइन करो:",
            'hinglish' => "📢 Hamare channels join karo:",
            'english' => "📢 Join our channels:"
        ],
        'language' => [
            'hindi' => "🌐 भाषा चुनें:",
            'hinglish' => "🌐 Bhasha chunen:",
            'english' => "🌐 Choose language:"
        ],
        'thanks' => [
            'hindi' => "🙏 धन्यवाद! और मूवी चाहिए?",
            'hinglish' => "🙏 Dhanyavaad! Aur movie chahiye?",
            'english' => "🙏 Thank you! Another movie?"
        ],
        'bye' => [
            'hindi' => "👋 फिर मिलेंगे! कभी भी आ जाना",
            'hinglish' => "👋 Phir milenge! Kabhi bhi aa jana",
            'english' => "👋 See you! Come back anytime"
        ],
        'invalid' => [
            'hindi' => "❌ गलत इनपुट। सही मूवी नाम लिखो",
            'hinglish' => "❌ Galat input. Sahi movie naam likho",
            'english' => "❌ Invalid input. Write correct movie name"
        ]
    ];
    
    private $hindi_keywords = [
        'है', 'हैं', 'का', 'की', 'के', 'में', 'से', 'को', 'नहीं', 'बहुत',
        'अच्छा', 'बुरा', 'क्या', 'क्यों', 'कैसे', 'कहाँ', 'कब', 'कौन',
        'मैं', 'तुम', 'हम', 'आप', 'यह', 'वह', 'ये', 'वे', 'मेरा', 'तेरा',
        'हमारा', 'आपका', 'कोई', 'कुछ', 'सब', 'थोड़ा', 'ज़रा', 'बस'
    ];
    
    private $hinglish_keywords = [
        'hai', 'hain', 'ka', 'ki', 'ke', 'mein', 'se', 'ko', 'nahi', 'bahut',
        'acha', 'bura', 'kya', 'kyon', 'kaise', 'kahan', 'kab', 'kaun',
        'main', 'tum', 'hum', 'aap', 'yeh', 'woh', 'ye', 'wo', 'mera', 'tera',
        'hamara', 'aapka', 'koi', 'kuch', 'sab', 'thoda', 'zara', 'bas',
        'bhai', 'boss', 'yaar', 'dost', 'mere', 'tere', 'humein', 'tumhe'
    ];
    
    /**
     * Detect language mode from text
     */
    function detect_mode($text, $user_id = null) {
        // Check user preference first
        if ($user_id) {
            $settings = get_user_settings($user_id);
            if (isset($settings['language']) && $settings['language'] !== 'auto') {
                return $settings['language'];
            }
        }
        
        // Check for Hindi script
        if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) {
            return 'hindi';
        }
        
        $text = strtolower($text);
        $hinglish_score = 0;
        $english_score = 0;
        
        // Count Hinglish keywords
        foreach ($this->hinglish_keywords as $word) {
            if (strpos($text, $word) !== false) {
                $hinglish_score += 2;
            }
        }
        
        // Simple English words
        $english_words = ['the', 'is', 'are', 'was', 'were', 'have', 'has', 
                          'will', 'shall', 'can', 'could', 'would', 'should'];
        foreach ($english_words as $word) {
            if (preg_match('/\b' . $word . '\b/', $text)) {
                $english_score++;
            }
        }
        
        // Check for mixed script
        if ($hinglish_score > 0 && $english_score > 0) {
            return 'hinglish';
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
        
        // Ensure mode is valid
        if (!in_array($mode, ['hindi', 'hinglish', 'english'])) {
            $mode = 'hinglish';
        }
        
        $response = $this->responses[$key][$mode] ?? $this->responses[$key]['hinglish'] ?? '';
        
        // Replace parameters
        if (!empty($params) && $response) {
            $response = vsprintf($response, $params);
        }
        
        return $response;
    }
    
    /**
     * Translate text to Hinglish (basic transliteration)
     */
    function translate($text, $target = 'hinglish') {
        if ($target == 'hinglish' && $this->is_hindi_script($text)) {
            // Very basic Hindi to Hinglish transliteration
            $map = [
                'का' => 'ka', 'की' => 'ki', 'के' => 'ke',
                'में' => 'mein', 'से' => 'se', 'को' => 'ko',
                'है' => 'hai', 'हैं' => 'hain', 'नहीं' => 'nahi',
                'और' => 'aur', 'मैं' => 'main', 'तुम' => 'tum',
                'हम' => 'hum', 'आप' => 'aap', 'यह' => 'yeh',
                'वह' => 'woh', 'ये' => 'ye', 'वे' => 'wo',
                'मेरा' => 'mera', 'तेरा' => 'tera', 'हमारा' => 'hamara',
                'आपका' => 'aapka', 'कोई' => 'koi', 'कुछ' => 'kuch',
                'सब' => 'sab', 'थोड़ा' => 'thoda', 'बस' => 'bas',
                'अच्छा' => 'acha', 'बुरा' => 'bura', 'बहुत' => 'bahut'
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
    
    /**
     * Get all available responses
     */
    function get_all_responses() {
        return $this->responses;
    }
    
    /**
     * Add custom response
     */
    function add_response($key, $responses) {
        $this->responses[$key] = $responses;
    }
}

// Initialize Hinglish processor
$hinglish = new HinglishProcessor();

// ==============================
// 17. AUTO-RESPONSE SYSTEM
// ==============================

/**
 * AutoResponse Engine Class
 */
class AutoResponseEngine {
    private $patterns = [
        // Greetings
        '/^(hi|hello|hey|hii|heyy|hlo|hola|namaste|namaskar)/i' => [
            'response' => 'greeting',
            'priority' => 10,
            'category' => 'greeting'
        ],
        '/^(good morning|gm|suprabhat|good afternoon|good evening)/i' => [
            'response' => 'time_greeting',
            'priority' => 9,
            'category' => 'greeting'
        ],
        '/^(good night|gn|shubh ratri|sweet dreams)/i' => [
            'response' => 'night_greeting',
            'priority' => 9,
            'category' => 'greeting'
        ],
        
        // Thanks
        '/thank(s| you| u| you so much| thanks a lot|dhanyavaad|shukriya)/i' => [
            'response' => 'thanks',
            'priority' => 8,
            'category' => 'thanks'
        ],
        
        // Farewell
        '/bye|byee|tata|allahafiz|khuda hafiz|see you|cya|phir milenge|alvida/i' => [
            'response' => 'bye',
            'priority' => 8,
            'category' => 'farewell'
        ],
        
        // Bot status
        '/how are you|kaise ho|kya haal|how r u|how doin|kaisa hai/i' => [
            'response' => 'status',
            'priority' => 7,
            'category' => 'status'
        ],
        
        // Movie related
        '/(movie|film|picture|film|picture) (hai|hain|kahan|kaise|kyun|dikhhao|batao)/i' => [
            'response' => 'movie_help',
            'priority' => 6,
            'category' => 'movie'
        ],
        '/(download|send|bhej|de do|do|provide) (karo|kar do)/i' => [
            'response' => 'download_help',
            'priority' => 6,
            'category' => 'movie'
        ],
        '/(search|dhoondh|find|track)/i' => [
            'response' => 'search_help',
            'priority' => 6,
            'category' => 'movie'
        ],
        
        // Help
        '/(help|madad|sahayata|guide|how to use|kaise use kare)/i' => [
            'response' => 'help',
            'priority' => 5,
            'category' => 'help'
        ],
        
        // Commands
        '/(commands|command|options|menu|list)/i' => [
            'response' => 'commands',
            'priority' => 5,
            'category' => 'help'
        ],
        
        // Love/Appreciation
        '/(love you|i love you|love u|❤️|♥️|great bot|awesome bot|best bot)/i' => [
            'response' => 'love',
            'priority' => 4,
            'category' => 'appreciation'
        ],
        
        // Channels
        '/(channel|group|join|add|invite)/i' => [
            'response' => 'channels',
            'priority' => 4,
            'category' => 'channel'
        ],
        
        // Abuse/Swear (ignore or warn)
        '/(fuck|bc|mc|randi|bhosd|gand|madarchod|behenchod|chutiya|lauda)/i' => [
            'response' => 'abuse',
            'priority' => 0,
            'category' => 'abuse'
        ],
        
        // Default
        'default' => [
            'response' => 'default',
            'priority' => 1,
            'category' => 'default'
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
        'search_help' => [
            'hindi' => "🔍 जो मूवी चाहिए, बस नाम लिखो!",
            'hinglish' => "🔍 Jo movie chahiye, bas naam likho!",
            'english' => "🔍 Just type the movie name you want!"
        ],
        'help' => [
            'hindi' => "🆘 /help दबाओ, सारी कमांड्स मिल जाएंगी",
            'hinglish' => "🆘 /help dabao, saari commands mil jayengi",
            'english' => "🆘 Press /help for all commands"
        ],
        'commands' => [
            'hindi' => "📋 सारी कमांड्स के लिए /help इस्तेमाल करो",
            'hinglish' => "📋 Saari commands ke liye /help istemal karo",
            'english' => "📋 Use /help for all commands"
        ],
        'love' => [
            'hindi' => "❤️ धन्यवाद! ऐसे ही सपोर्ट करते रहो",
            'hinglish' => "❤️ Dhanyavaad! Aise hi support karte raho",
            'english' => "❤️ Thank you! Keep supporting"
        ],
        'channels' => [
            'hindi' => "📢 हमारे चैनल ज्वाइन करो:\n" . MAIN_CHANNEL . "\n" . THEATER_CHANNEL . "\n" . BACKUP_CHANNEL_USERNAME . "\n" . REQUEST_CHANNEL,
            'hinglish' => "📢 Hamare channels join karo:\n" . MAIN_CHANNEL . "\n" . THEATER_CHANNEL . "\n" . BACKUP_CHANNEL_USERNAME . "\n" . REQUEST_CHANNEL,
            'english' => "📢 Join our channels:\n" . MAIN_CHANNEL . "\n" . THEATER_CHANNEL . "\n" . BACKUP_CHANNEL_USERNAME . "\n" . REQUEST_CHANNEL
        ],
        'abuse' => [
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
    private $db;
    
    function __construct() {
        $this->db = get_db();
        $this->load_learned_responses();
    }
    
    /**
     * Load learned responses from database
     */
    function load_learned_responses() {
        $result = $this->db->query("SELECT pattern, response, count FROM learning ORDER BY count DESC, last_used DESC LIMIT 100");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $this->learned_responses[$row['pattern']] = [
                'response' => $row['response'],
                'count' => $row['count']
            ];
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
        foreach ($this->learned_responses as $pattern => $data) {
            if (strpos($text, $pattern) !== false) {
                $this->update_learning($pattern);
                return $data['response'];
            }
        }
        
        // Check patterns
        $matched = null;
        $highest_priority = 0;
        
        foreach ($this->patterns as $pattern => $data) {
            if ($pattern !== 'default' && preg_match($pattern, $text)) {
                if ($data['priority'] > $highest_priority) {
                    $matched = $data['response'];
                    $highest_priority = $data['priority'];
                }
            }
        }
        
        if ($matched && $matched != 'abuse') {
            $response = $this->responses[$matched][$mode] ?? $this->responses[$matched]['hinglish'];
            
            // Learn this response if it's a good match
            if ($highest_priority >= 5 && strlen($text) < 50) {
                $this->learn($text, $response);
            }
            
            return $response;
        }
        
        // Check for abuse
        if ($matched == 'abuse') {
            return $this->responses['abuse'][$mode] ?? $this->responses['abuse']['hinglish'];
        }
        
        // Default response for very short messages
        if (strlen($text) < 3) {
            return $this->responses['default'][$mode] ?? $this->responses['default']['hinglish'];
        }
        
        return null;
    }
    
    /**
     * Learn a new response
     */
    function learn($pattern, $response) {
        $stmt = $this->db->prepare("INSERT INTO learning (pattern, response, count) 
                                    VALUES (:p, :r, 1)
                                    ON CONFLICT(pattern) DO UPDATE SET 
                                    count = count + 1,
                                    last_used = CURRENT_TIMESTAMP");
        $stmt->bindValue(':p', $pattern, SQLITE3_TEXT);
        $stmt->bindValue(':r', $response, SQLITE3_TEXT);
        $stmt->execute();
        
        // Update cache
        $this->learned_responses[$pattern] = [
            'response' => $response,
            'count' => 1
        ];
        
        bot_log("Learned new response: '$pattern'");
    }
    
    /**
     * Update learning count
     */
    function update_learning($pattern) {
        $this->db->exec("UPDATE learning SET count = count + 1, last_used = CURRENT_TIMESTAMP WHERE pattern = '$pattern'");
        
        if (isset($this->learned_responses[$pattern])) {
            $this->learned_responses[$pattern]['count']++;
        }
    }
    
    /**
     * Get learning stats
     */
    function get_stats() {
        $result = $this->db->query("SELECT COUNT(*) as total, SUM(count) as total_responses FROM learning");
        $stats = $result->fetchArray(SQLITE3_ASSOC);
        
        $result = $this->db->query("SELECT pattern, count, last_used FROM learning ORDER BY count DESC LIMIT 10");
        $top = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $top[] = $row;
        }
        
        return [
            'total_patterns' => $stats['total'] ?? 0,
            'total_responses' => $stats['total_responses'] ?? 0,
            'top_patterns' => $top
        ];
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
    
    $backup_dir = BACKUP_DIR . $timestamp . '_' . $backup_id . DIRECTORY_SEPARATOR;
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $files = ($type == 'full') 
        ? [CSV_FILE, USERS_FILE, STATS_FILE, REQUEST_FILE, LOG_FILE, DB_FILE, LEARNING_FILE]
        : [CSV_FILE, USERS_FILE, DB_FILE];
    
    $backed_up = [];
    $total_size = 0;
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            $dest = $backup_dir . basename($file);
            if (copy($file, $dest)) {
                $backed_up[] = basename($file);
                $total_size += filesize($file);
                bot_log("Backed up: " . basename($file));
            }
        }
    }
    
    // Create summary
    $summary = "Backup ID: $backup_id\n";
    $summary .= "Type: $type\n";
    $summary .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "Files: " . implode(', ', $backed_up) . "\n";
    $summary .= "Size: " . format_bytes($total_size) . "\n";
    $summary .= "Status: Completed\n";
    
    file_put_contents($backup_dir . 'summary.txt', $summary);
    
    // Create zip archive
    $zip_file = $backup_dir . 'backup.zip';
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE) === true) {
            foreach (glob($backup_dir . '*') as $file) {
                if (is_file($file) && basename($file) != 'backup.zip') {
                    $zip->addFile($file, basename($file));
                }
            }
            $zip->close();
            $backed_up[] = 'backup.zip';
        }
    }
    
    // Upload to backup channel
    if (ENABLE_BACKUP && defined('BACKUP_CHANNEL_ID') && BACKUP_CHANNEL_ID) {
        upload_backup_to_channel($backup_dir, $backup_id, $summary, $total_size);
    }
    
    // Record in database
    $db = get_db();
    $stmt = $db->prepare("INSERT INTO backups (id, type, size, files, status) 
                          VALUES (:id, :type, :size, :files, 'completed')");
    $stmt->bindValue(':id', $backup_id, SQLITE3_TEXT);
    $stmt->bindValue(':type', $type, SQLITE3_TEXT);
    $stmt->bindValue(':size', $total_size, SQLITE3_INTEGER);
    $stmt->bindValue(':files', count($backed_up), SQLITE3_INTEGER);
    $stmt->execute();
    
    // Clean old backups
    if ($type == 'full') {
        clean_old_backups(7);
    }
    
    bot_log("Backup completed: $backup_id (" . format_bytes($total_size) . ")");
    
    return [
        'id' => $backup_id,
        'path' => $backup_dir,
        'files' => $backed_up,
        'size' => $total_size,
        'size_formatted' => format_bytes($total_size),
        'count' => count($backed_up)
    ];
}

/**
 * Upload backup to channel
 */
function upload_backup_to_channel($backup_dir, $backup_id, $summary, $total_size) {
    $channel_id = BACKUP_CHANNEL_ID;
    
    if (!$channel_id) {
        bot_log("Backup channel not configured", 'WARNING');
        return false;
    }
    
    // Send summary first
    $summary_msg = "💾 <b>Backup Created</b>\n\n";
    $summary_msg .= "🆔 <b>ID:</b> <code>$backup_id</code>\n";
    $summary_msg .= "📅 <b>Time:</b> " . date('Y-m-d H:i:s') . "\n";
    $summary_msg .= "📦 <b>Type:</b> " . ucfirst(str_replace('_', ' ', $summary['type'] ?? 'full')) . "\n";
    $summary_msg .= "📊 <b>Size:</b> " . format_bytes($total_size) . "\n\n";
    $summary_msg .= "📋 <b>Files:</b>\n• " . str_replace(', ', "\n• ", $summary['files'] ?? 'None');
    
    sendMessage($channel_id, $summary_msg, null, 'HTML');
    
    // Upload files
    $files = glob($backup_dir . '*');
    foreach ($files as $file) {
        if (is_file($file) && filesize($file) < 50 * 1024 * 1024) { // 50MB limit
            $filename = basename($file);
            $caption = "💾 <b>Backup File:</b> $filename\n";
            $caption .= "🆔 <b>ID:</b> <code>$backup_id</code>\n";
            $caption .= "📅 <b>Date:</b> " . date('Y-m-d H:i:s');
            
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
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_exec($ch);
            curl_close($ch);
            
            sleep(2); // Rate limiting
            bot_log("Uploaded $filename to backup channel");
        }
    }
    
    return true;
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
        $deleted = 0;
        
        foreach ($to_delete as $backup) {
            if (delete_directory($backup)) {
                $deleted++;
                bot_log("Deleted old backup: " . basename($backup));
            }
        }
        
        bot_log("Cleaned $deleted old backups");
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
 * Get backup list
 */
function get_backup_list($limit = 10) {
    $db = get_db();
    
    $result = $db->query("SELECT * FROM backups ORDER BY created DESC LIMIT $limit");
    
    $backups = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['created_formatted'] = date('d-m-Y H:i', strtotime($row['created']));
        $row['size_formatted'] = format_bytes($row['size']);
        $backups[] = $row;
    }
    
    return $backups;
}

/**
 * Restore from backup
 */
function restore_backup($backup_id) {
    // Find backup directory
    $dirs = glob(BACKUP_DIR . '*' . $backup_id . '*', GLOB_ONLYDIR);
    
    if (empty($dirs)) {
        return ['success' => false, 'message' => 'Backup not found'];
    }
    
    $backup_dir = $dirs[0];
    $restored = [];
    
    // Check for zip file first
    if (file_exists($backup_dir . 'backup.zip')) {
        $zip = new ZipArchive();
        if ($zip->open($backup_dir . 'backup.zip') === true) {
            $zip->extractTo(BACKUP_DIR . 'restore_' . time() . DIRECTORY_SEPARATOR);
            $zip->close();
        }
    }
    
    // Restore files
    $files = glob($backup_dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $filename = basename($file);
            if ($filename != 'backup.zip' && $filename != 'summary.txt') {
                $dest = BASE_PATH . $filename;
                if (copy($file, $dest)) {
                    $restored[] = $filename;
                    bot_log("Restored: $filename");
                }
            }
        }
    }
    
    return [
        'success' => true,
        'restored' => $restored,
        'count' => count($restored)
    ];
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
    $cache_stats = cache_stats();
    
    $msg = "👑 <b>ENTERTAINMENT TADKA ADMIN v" . BOT_VERSION . "</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $msg .= "📊 <b>SYSTEM STATS</b>\n";
    $msg .= "🎬 Movies: <b>{$stats['total_movies']}</b>\n";
    $msg .= "👥 Users: <b>{$stats['total_users']}</b>\n";
    $msg .= "🔍 Searches: <b>{$stats['total_searches']}</b>\n";
    $msg .= "📥 Downloads: <b>{$stats['total_downloads']}</b>\n";
    $msg .= "✅ Success Rate: <b>{$stats['success_rate']}%</b>\n\n";
    
    $msg .= "📈 <b>TODAY</b>\n";
    $msg .= "👥 Active Now: <b>{$stats['active_now']}</b>\n";
    $msg .= "👥 Active Today: <b>{$stats['active_today']}</b>\n";
    $msg .= "🔍 Searches: <b>{$stats['today_searches']}</b>\n";
    $msg .= "📥 Downloads: <b>{$stats['today_downloads']}</b>\n";
    $msg .= "📝 Requests: <b>{$stats['today_requests']}</b>\n";
    $msg .= "⏳ Pending: <b>{$stats['pending_requests']}</b>\n\n";
    
    $msg .= "🖥️ <b>SYSTEM</b>\n";
    $msg .= "⏱️ Uptime: <b>{$stats['uptime']}</b>\n";
    $msg .= "💾 Memory: <b>" . round($stats['memory_usage'], 1) . " MB</b>\n";
    $msg .= "⚡ Peak: <b>" . round($stats['peak_memory'], 1) . " MB</b>\n";
    $msg .= "📁 DB Size: <b>{$stats['db_size']}</b>\n";
    $msg .= "📦 Cache: <b>{$cache_stats['memory_entries']} mem / {$cache_stats['db_entries']} db</b>\n";
    $msg .= "📞 API Calls: <b>{$stats['api_calls']}</b>\n";
    $msg .= "⚠️ Errors: <b>{$stats['errors']}</b> | Warnings: <b>{$stats['warnings']}</b>\n";
    $msg .= "🗑️ Delete Queue: <b>{$stats['delete_queue']}</b>\n";
    $msg .= "👥 Waiting: <b>{$stats['waiting_users']}</b>\n\n";
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '📊 Full Stats', 'callback_data' => 'admin_stats'],
             ['text' => '👥 Users', 'callback_data' => 'admin_users']],
            [['text' => '🎬 Movies', 'callback_data' => 'admin_movies'],
             ['text' => '📝 Requests', 'callback_data' => 'admin_requests']],
            [['text' => '💾 Backup', 'callback_data' => 'admin_backup'],
             ['text' => '📢 Broadcast', 'callback_data' => 'admin_broadcast']],
            [['text' => '⚙️ Config', 'callback_data' => 'admin_config'],
             ['text' => '📋 Logs', 'callback_data' => 'admin_logs']],
            [['text' => '🧹 Cleanup', 'callback_data' => 'admin_cleanup'],
             ['text' => '🔄 Maintenance', 'callback_data' => 'admin_maintenance']],
            [['text' => '📈 Analytics', 'callback_data' => 'admin_analytics'],
             ['text' => '🔐 Logout', 'callback_data' => 'admin_logout']]
        ]
    ];
    
    sendMessage($chat_id, $msg, $keyboard, 'HTML');
}

/**
 * Show full statistics (admin)
 */
function admin_full_stats($chat_id) {
    if ($chat_id != ADMIN_ID) return;
    
    $stats = get_system_stats();
    $db = get_db();
    
    // Get database stats
    $tables = ['movies', 'users', 'requests', 'learning', 'stats'];
    $table_stats = [];
    
    foreach ($tables as $table) {
        $count = $db->querySingle("SELECT COUNT(*) FROM $table");
        $table_stats[$table] = $count;
    }
    
    // Get top movies
    $top_movies = $db->query("SELECT movie_name, downloads FROM movies ORDER BY downloads DESC LIMIT 5");
    $top_movies_list = [];
    while ($row = $top_movies->fetchArray(SQLITE3_ASSOC)) {
        $top_movies_list[] = $row;
    }
    
    // Get top users
    $top_users = $db->query("SELECT username, first_name, points FROM users ORDER BY points DESC LIMIT 5");
    $top_users_list = [];
    while ($row = $top_users->fetchArray(SQLITE3_ASSOC)) {
        $top_users_list[] = $row;
    }
    
    $msg = "📊 <b>FULL STATISTICS</b>\n\n";
    
    $msg .= "📁 <b>Database Tables</b>\n";
    foreach ($table_stats as $table => $count) {
        $msg .= "• $table: <b>$count</b>\n";
    }
    $msg .= "\n";
    
    $msg .= "🎬 <b>Top 5 Movies</b>\n";
    foreach ($top_movies_list as $i => $movie) {
        $msg .= ($i+1) . ". {$movie['movie_name']} - <b>{$movie['downloads']}</b> downloads\n";
    }
    $msg .= "\n";
    
    $msg .= "👑 <b>Top 5 Users</b>\n";
    foreach ($top_users_list as $i => $user) {
        $name = $user['username'] ? "@{$user['username']}" : $user['first_name'];
        $msg .= ($i+1) . ". $name - <b>{$user['points']}</b> points\n";
    }
    $msg .= "\n";
    
    $msg .= "📊 <b>Performance</b>\n";
    $msg .= "• Avg Response: <b>" . round(($stats['api_calls'] ? (microtime(true) - $GLOBALS['start_time']) / $stats['api_calls'] * 1000 : 0), 2) . "ms</b>\n";
    $msg .= "• Cache Hit Rate: <b>" . round(($cache_stats['memory_entries'] / max(1, $stats['total_searches'])) * 100, 1) . "%</b>\n";
    $msg .= "• Error Rate: <b>" . round(($stats['errors'] / max(1, $stats['api_calls'])) * 100, 2) . "%</b>\n";
    
    sendMessage($chat_id, $msg, null, 'HTML');
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
    $count = 0;
    
    while ($user = $result->fetchArray(SQLITE3_ASSOC)) {
        $count++;
        $last = date('H:i d-m', strtotime($user['last_active']));
        $status = (time() - strtotime($user['last_active']) < 3600) ? '🟢' : '⚫';
        
        $msg .= "{$status} <b>" . get_user_display($user) . "</b>\n";
        $msg .= "   🆔 <code>{$user['user_id']}</code>\n";
        $msg .= "   ⭐ {$user['points']} pts | 🔍 {$user['searches']} | 📥 {$user['downloads']} | 📝 {$user['requests']}\n";
        $msg .= "   🕐 Last: $last\n\n";
    }
    
    if ($count == 0) {
        $msg .= "No users found.";
    }
    
    // Count total
    $total = $db->querySingle("SELECT COUNT(*) FROM users");
    $pages = ceil($total / $per_page);
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '⬅️ Prev', 'callback_data' => 'admin_users_' . ($page - 1)],
             ['text' => "$page/$pages", 'callback_data' => 'current_page'],
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
    
    $result = $db->query("SELECT * FROM movies ORDER BY added_date DESC, downloads DESC LIMIT $per_page OFFSET $offset");
    
    $msg = "🎬 <b>MOVIE LIST (Page $page)</b>\n\n";
    $count = 0;
    
    while ($movie = $result->fetchArray(SQLITE3_ASSOC)) {
        $count++;
        $channel = get_channel($movie['channel_id']);
        $added = date('d-m-Y', strtotime($movie['added_date']));
        
        $msg .= "{$count}. <b>{$movie['movie_name']}</b>\n";
        $msg .= "   {$channel['emoji']} {$channel['name']} | 🆔 {$movie['message_id']}\n";
        $msg .= "   🏷️ {$movie['quality']} | 🗣️ {$movie['language']}";
        if ($movie['year']) $msg .= " | 📅 {$movie['year']}";
        $msg .= "\n   📥 {$movie['downloads']} downloads | 📅 $added\n\n";
    }
    
    if ($count == 0) {
        $msg .= "No movies found.";
    }
    
    // Count total
    $total = $db->querySingle("SELECT COUNT(*) FROM movies");
    $pages = ceil($total / $per_page);
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '⬅️ Prev', 'callback_data' => 'admin_movies_' . ($page - 1)],
             ['text' => "$page/$pages", 'callback_data' => 'current_page'],
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
    
    $requests = get_pending_requests(20);
    
    $msg = "📝 <b>PENDING REQUESTS</b>\n\n";
    
    if (empty($requests)) {
        $msg .= "No pending requests!";
    } else {
        foreach ($requests as $i => $req) {
            $status_emoji = $req['status'] == 'pending' ? '⏳' : '✅';
            $votes = $req['votes'] ?? 0;
            $vote_display = $votes > 0 ? " | 🗳️ $votes votes" : '';
            
            $msg .= "{$status_emoji} <b>{$req['movie_name']}</b>\n";
            $msg .= "   👤 {$req['user_display']} | 🆔 {$req['id']}{$vote_display}\n";
            $msg .= "   📅 " . date('d-m', strtotime($req['created_at'])) . " | ⏰ {$req['time_ago']}\n\n";
            
            if ($i >= 9) {
                $msg .= "... and " . (count($requests) - 10) . " more";
                break;
            }
        }
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
    
    if (empty($message)) {
        sendMessage($chat_id, "❌ Please provide a message to broadcast.");
        return;
    }
    
    $db = get_db();
    
    // Get target users
    if ($target == 'all') {
        $users = $db->query("SELECT user_id FROM users WHERE banned = 0");
        $target_name = "ALL USERS";
    } elseif ($target == 'active') {
        $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $users = $db->query("SELECT user_id FROM users WHERE last_active > '$hour_ago' AND banned = 0");
        $target_name = "ACTIVE USERS";
    } elseif ($target == 'inactive') {
        $week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
        $users = $db->query("SELECT user_id FROM users WHERE last_active < '$week_ago' AND banned = 0");
        $target_name = "INACTIVE USERS";
    } elseif ($target == 'premium') {
        $users = $db->query("SELECT user_id FROM users WHERE points >= 1000 AND banned = 0");
        $target_name = "PREMIUM USERS";
    } else {
        $users = $db->query("SELECT user_id FROM users WHERE banned = 0");
        $target_name = "ALL USERS";
    }
    
    // Count total
    $user_ids = [];
    while ($user = $users->fetchArray(SQLITE3_ASSOC)) {
        $user_ids[] = $user['user_id'];
    }
    $total = count($user_ids);
    
    if ($total == 0) {
        sendMessage($chat_id, "❌ No users found in target group.");
        return;
    }
    
    // Confirm broadcast
    $confirm_msg = "📢 <b>Broadcast Confirmation</b>\n\n";
    $confirm_msg .= "🎯 Target: <b>$target_name</b>\n";
    $confirm_msg .= "👥 Users: <b>$total</b>\n\n";
    $confirm_msg .= "📝 Message:\n<code>" . htmlspecialchars(substr($message, 0, 200)) . "</code>\n";
    if (strlen($message) > 200) $confirm_msg .= "...\n\n";
    $confirm_msg .= "⚠️ This action cannot be undone!";
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '✅ Confirm Broadcast', 'callback_data' => 'broadcast_confirm_' . base64_encode($message) . '_' . $target]],
            [['text' => '❌ Cancel', 'callback_data' => 'admin_back']]
        ]
    ];
    
    sendMessage($chat_id, $confirm_msg, $keyboard, 'HTML');
}

/**
 * Execute broadcast
 */
function execute_broadcast($chat_id, $message, $target) {
    if ($chat_id != ADMIN_ID) return;
    
    $db = get_db();
    
    // Get target users
    if ($target == 'all') {
        $users = $db->query("SELECT user_id FROM users WHERE banned = 0");
    } elseif ($target == 'active') {
        $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $users = $db->query("SELECT user_id FROM users WHERE last_active > '$hour_ago' AND banned = 0");
    } elseif ($target == 'inactive') {
        $week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
        $users = $db->query("SELECT user_id FROM users WHERE last_active < '$week_ago' AND banned = 0");
    } elseif ($target == 'premium') {
        $users = $db->query("SELECT user_id FROM users WHERE points >= 1000 AND banned = 0");
    } else {
        $users = $db->query("SELECT user_id FROM users WHERE banned = 0");
    }
    
    $user_ids = [];
    while ($user = $users->fetchArray(SQLITE3_ASSOC)) {
        $user_ids[] = $user['user_id'];
    }
    $total = count($user_ids);
    
    $sent = 0;
    $failed = 0;
    
    $progress = sendMessage($chat_id, "📢 Broadcasting to $total users...\n\n" . progress_bar(0, $total));
    $progress_id = $progress['result']['message_id'] ?? null;
    
    for ($i = 0; $i < $total; $i++) {
        $user_id = $user_ids[$i];
        
        $result = sendMessage($user_id, "📢 <b>Announcement</b>\n\n$message", null, 'HTML');
        
        if ($result && isset($result['ok']) && $result['ok']) {
            $sent++;
        } else {
            $failed++;
        }
        
        if ($i % 10 == 0 && $progress_id) {
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
    
    if ($progress_id) {
        editMessage($chat_id, $progress_id,
            "✅ <b>Broadcast Complete!</b>\n\n" .
            "📊 Total: $total\n" .
            "✅ Sent: $sent\n" .
            "❌ Failed: $failed"
        );
    }
    
    bot_log("Broadcast completed: $sent/$total to $target");
}

/**
 * Show system logs (admin)
 */
function admin_logs($chat_id, $lines = 50) {
    if ($chat_id != ADMIN_ID) return;
    
    if (!file_exists(LOG_FILE)) {
        sendMessage($chat_id, "📋 No logs found.");
        return;
    }
    
    $logs = file(LOG_FILE);
    $total = count($logs);
    $last = array_slice($logs, -$lines);
    
    $msg = "📋 <b>SYSTEM LOGS (Last $lines of $total)</b>\n\n";
    $msg .= "<pre>";
    foreach ($last as $log) {
        $msg .= htmlspecialchars($log);
    }
    $msg .= "</pre>";
    
    if (strlen($msg) > 4096) {
        $msg = substr($msg, 0, 4000) . "...\n</pre>\n(Log truncated)";
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🔄 Refresh', 'callback_data' => 'admin_logs'],
             ['text' => '🧹 Clear', 'callback_data' => 'admin_clear_logs'],
             ['text' => '🔙 Back', 'callback_data' => 'admin_back']]
        ]
    ];
    
    sendMessage($chat_id, $msg, $keyboard, 'HTML');
}

/**
 * Clear system logs (admin)
 */
function admin_clear_logs($chat_id) {
    if ($chat_id != ADMIN_ID) return;
    
    $log_dir = dirname(LOG_FILE);
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] INFO: Logs cleared by admin\n", LOCK_EX);
    sendMessage($chat_id, "✅ Logs cleared!");
    bot_log("Logs cleared by admin");
}

/**
 * Show system config (admin)
 */
function admin_config($chat_id) {
    if ($chat_id != ADMIN_ID) return;
    
    $constants = [
        'BOT_VERSION', 'ADMIN_ID', 'MAIN_CHANNEL', 'THEATER_CHANNEL', 
        'BACKUP_CHANNEL_USERNAME', 'REQUEST_CHANNEL', 'CACHE_EXPIRY',
        'ITEMS_PER_PAGE', 'MAX_SEARCH_RESULTS', 'SEARCH_COOLDOWN',
        'DAILY_REQUEST_LIMIT', 'AUTO_BACKUP_HOUR', 'TEXT_DELETE_SECONDS',
        'FILE_DELETE_SECONDS', 'MAX_PAGES_TO_SHOW', 'ENABLE_CACHE',
        'ENABLE_REDIS', 'ENABLE_ANALYTICS', 'ENABLE_AUTO_RESPONSE'
    ];
    
    $msg = "⚙️ <b>SYSTEM CONFIGURATION</b>\n\n";
    
    foreach ($constants as $const) {
        if (defined($const)) {
            $value = constant($const);
            if (is_bool($value)) {
                $value = $value ? '✅ true' : '❌ false';
            }
            $msg .= "• <b>$const</b> = <code>$value</code>\n";
        }
    }
    
    $msg .= "\n📁 <b>File Paths</b>\n";
    $msg .= "• CSV: " . (file_exists(CSV_FILE) ? '✅' : '❌') . " " . basename(CSV_FILE) . "\n";
    $msg .= "• DB: " . (file_exists(DB_FILE) ? '✅' : '❌') . " " . basename(DB_FILE) . "\n";
    $msg .= "• Logs: " . (file_exists(LOG_FILE) ? '✅' : '❌') . " " . basename(LOG_FILE) . "\n";
    
    sendMessage($chat_id, $msg, null, 'HTML');
}

/**
 * System cleanup (admin)
 */
function admin_cleanup($chat_id) {
    if ($chat_id != ADMIN_ID) return;
    
    $progress = sendMessage($chat_id, "🧹 Starting system cleanup...");
    $progress_id = $progress['result']['message_id'] ?? null;
    
    if (!$progress_id) return;
    
    // Clear cache
    cache_clear();
    editMessage($chat_id, $progress_id, "🧹 Cache cleared...");
    
    // Clean old backups
    clean_old_backups(7);
    editMessage($chat_id, $progress_id, "🧹 Old backups cleaned...");
    
    // Vacuum database
    $db = get_db();
    $db->exec("VACUUM");
    editMessage($chat_id, $progress_id, "🧹 Database optimized...");
    
    // Clear delete queue
    global $GLOBALS;
    $queue_count = count($GLOBALS['delete_queue']);
    $GLOBALS['delete_queue'] = [];
    editMessage($chat_id, $progress_id, "🧹 Delete queue cleared ($queue_count items)...");
    
    // Reset error logs
    $GLOBALS['errors'] = [];
    $GLOBALS['warnings'] = [];
    
    // Final message
    editMessage($chat_id, $progress_id,
        "✅ <b>Cleanup Complete!</b>\n\n" .
        "🧹 Cache cleared\n" .
        "🧹 Old backups removed\n" .
        "🧹 Database optimized\n" .
        "🧹 Delete queue cleared\n" .
        "🧹 Error logs reset"
    );
    
    bot_log("System cleanup performed by admin");
}

/**
 * Toggle maintenance mode (admin)
 */
function admin_toggle_maintenance($chat_id) {
    if ($chat_id != ADMIN_ID) return;
    
    global $GLOBALS;
    $GLOBALS['maintenance_mode'] = !($GLOBALS['maintenance_mode'] ?? false);
    $status = $GLOBALS['maintenance_mode'] ? 'ENABLED' : 'DISABLED';
    
    sendMessage($chat_id, "🔧 Maintenance mode: <b>$status</b>");
    bot_log("Maintenance mode $status by admin");
}

/**
 * Show analytics (admin)
 */
function admin_analytics($chat_id) {
    if ($chat_id != ADMIN_ID) return;
    
    $stats = get_system_stats();
    $chart = get_stats_chart(7);
    
    $msg = "📈 <b>ANALYTICS DASHBOARD</b>\n\n";
    $msg .= $chart . "\n\n";
    
    $msg .= "📊 <b>Summary</b>\n";
    $msg .= "• Total Users: {$stats['total_users']}\n";
    $msg .= "• Active Today: {$stats['active_today']}\n";
    $msg .= "• Retention Rate: " . round(($stats['active_today'] / max(1, $stats['total_users'])) * 100, 1) . "%\n";
    $msg .= "• Avg Searches/User: " . round($stats['total_searches'] / max(1, $stats['total_users']), 1) . "\n";
    $msg .= "• Avg Downloads/User: " . round($stats['total_downloads'] / max(1, $stats['total_users']), 1) . "\n";
    
    sendMessage($chat_id, $msg, null, 'HTML');
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
    
    // Update command stats
    update_daily_stat('commands', 1);
    
    switch ($command) {
        // ===== USER COMMANDS =====
        case '/start':
            $msg = $hinglish->get_response('welcome', $mode) . "\n\n";
            $msg .= "📢 <b>Join Our Channels:</b>\n";
            $msg .= "🎬 " . MAIN_CHANNEL . " - Main Channel\n";
            $msg .= "🎭 " . THEATER_CHANNEL . " - Theater Prints\n";
            $msg .= "💾 " . BACKUP_CHANNEL_USERNAME . " - Backup\n";
            $msg .= "📝 " . REQUEST_CHANNEL . " - Requests\n\n";
            $msg .= "🔍 <b>Examples:</b>\n";
            $msg .= "• <code>kgf 2</code>\n";
            $msg .= "• <code>pushpa theater</code>\n";
            $msg .= "• <code>avengers 2019</code>\n\n";
            $msg .= "❓ /help for all commands";
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''],
                     ['text' => '📢 Channels', 'callback_data' => 'channels']],
                    [['text' => '❓ Help', 'callback_data' => 'help'],
                     ['text' => '📊 Stats', 'callback_data' => 'my_stats']]
                ]
            ];
            
            sendMessage($chat_id, $msg, $keyboard, 'HTML', 1000);
            track_user($user_id, [], 'login');
            break;
            
        case '/help':
            $msg = "🤖 <b>Entertainment Tadka Bot v" . BOT_VERSION . "</b>\n\n";
            $msg .= "<b>User Commands:</b>\n";
            $msg .= "/start - Welcome message\n";
            $msg .= "/help - This help menu\n";
            $msg .= "/search [movie] - Search movies\n";
            $msg .= "/browse - Browse all movies\n";
            $msg .= "/latest - Latest movies\n";
            $msg .= "/trending - Trending movies\n";
            $msg .= "/theater - Theater prints only\n";
            $msg .= "/request [movie] - Request a movie\n";
            $msg .= "/stats - Your statistics\n";
            $msg .= "/top - Leaderboard\n";
            $msg .= "/settings - User preferences\n";
            $msg .= "/channels - Join our channels\n\n";
            
            if ($chat_id == ADMIN_ID) {
                $msg .= "<b>Admin Commands:</b>\n";
                $msg .= "/admin - Admin dashboard\n";
                $msg .= "/backup - Create backup\n";
                $msg .= "/add - Add movie (quick add)\n";
                $msg .= "/broadcast [msg] - Mass message\n";
                $msg .= "/maintenance - Toggle maintenance\n";
                $msg .= "/cleanup - System cleanup\n";
                $msg .= "/logs - View system logs\n";
                $msg .= "/config - View configuration\n";
            }
            
            sendMessage($chat_id, $msg, null, 'HTML');
            break;
            
        case '/search':
        case '/s':
        case '/find':
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
            update_daily_stat('searches', 1);
            
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
                add_to_waiting($user_id, $query);
                
            } else {
                $msg = $hinglish->get_response('found', $mode) . "\n\n";
                $msg .= "Found <b>" . count($results) . "</b> results:\n\n";
                
                // Build results keyboard
                $keyboard = ['inline_keyboard' => []];
                $row = [];
                
                foreach (array_slice($results, 0, 8) as $movie) {
                    $display = "{$movie['channel_emoji']} " . truncate($movie['movie_name'], 20);
                    $row[] = [
                        'text' => $display,
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
                
                sendMessage($chat_id, $msg, $keyboard, 'HTML');
                
                update_stat('successful_searches', 1);
                update_stat('total_searches', 1);
                track_user($user_id, [], 'search');
                track_user($user_id, [], 'found');
            }
            break;
            
        case '/browse':
        case '/all':
        case '/movies':
            $page = isset($params[0]) ? (int)$params[0] : 1;
            show_browser($chat_id, $page);
            break;
            
        case '/latest':
        case '/new':
        case '/recent':
            $movies = get_latest_movies(10);
            if (empty($movies)) {
                sendMessage($chat_id, "📭 No movies yet!");
                return;
            }
            
            $msg = "🎬 <b>Latest Movies</b>\n\n";
            foreach ($movies as $i => $movie) {
                $msg .= ($i+1) . ". {$movie['channel_emoji']} <b>{$movie['movie_name']}</b>\n";
                $msg .= "   🏷️ {$movie['quality']} | 🗣️ {$movie['language']}";
                if ($movie['year']) $msg .= " | 📅 {$movie['year']}";
                $msg .= "\n\n";
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
        case '/hot':
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
        case '/theaterprints':
            $results = search_movies('', ['type' => 'theater']);
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
        case '/ask':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: /request [movie name]\nExample: /request KGF 2");
                return;
            }
            
            // Check if already exists
            if (movie_exists($movie_name)) {
                sendMessage($chat_id, "✅ This movie already exists! Try /search");
                return;
            }
            
            $result = add_request($user_id, $movie_name);
            
            if ($result['success']) {
                $msg = $hinglish->get_response('request_sent', $mode);
                $msg .= "\n\n📊 Today: {$result['count']}/" . DAILY_REQUEST_LIMIT;
                $msg .= "\n⏳ Remaining: {$result['remaining']}";
                sendMessage($chat_id, $msg, null, 'HTML');
            } else {
                if ($result['message'] == 'daily_limit') {
                    sendMessage($chat_id, $hinglish->get_response('limit_reached', $mode));
                } elseif ($result['message'] == 'exists') {
                    sendMessage($chat_id, "✅ Movie already exists! Try /search");
                } elseif ($result['message'] == 'already_requested') {
                    sendMessage($chat_id, "⏳ You already requested this movie!");
                } else {
                    sendMessage($chat_id, $hinglish->get_response('error', $mode));
                }
            }
            break;
            
        case '/stats':
        case '/me':
        case '/profile':
            $user = track_user($user_id);
            $rank = get_user_rank($user['points']);
            $level = get_user_level($user['points']);
            $next_level = get_next_level_points($user['points']);
            
            $msg = "👤 <b>Your Statistics</b>\n\n";
            $msg .= "🆔 ID: <code>$user_id</code>\n";
            $msg .= "📛 Name: " . get_user_display($user) . "\n";
            $msg .= "⭐ Points: <b>{$user['points']}</b> ($rank)\n";
            $msg .= "📊 Level: <b>$level</b> ($next_level pts to next)\n";
            $msg .= "🔍 Searches: <b>{$user['searches']}</b>\n";
            $msg .= "📥 Downloads: <b>{$user['downloads']}</b>\n";
            $msg .= "📝 Requests: <b>{$user['requests']}</b>\n";
            $msg .= "🕐 Joined: " . date('d-m-Y', strtotime($user['joined'])) . "\n";
            $msg .= "⚡ Last Active: " . time_ago($user['last_active']);
            
            sendMessage($chat_id, $msg, null, 'HTML');
            break;
            
        case '/top':
        case '/leaderboard':
        case '/ranking':
            $users = get_leaderboard(10);
            
            if (empty($users)) {
                sendMessage($chat_id, "📭 No users yet!");
                return;
            }
            
            $msg = "🏆 <b>Top Users Leaderboard</b>\n\n";
            foreach ($users as $i => $user) {
                $medal = $i == 0 ? '🥇' : ($i == 1 ? '🥈' : ($i == 2 ? '🥉' : '🔹'));
                $msg .= "$medal {$i+1}. <b>{$user['display_name']}</b>\n";
                $msg .= "   ⭐ {$user['points']} pts | 🔍 {$user['searches']} | 📥 {$user['downloads']}\n";
                $msg .= "   🏅 {$user['rank']} | Level {$user['level']}\n\n";
            }
            
            sendMessage($chat_id, $msg, null, 'HTML');
            break;
            
        case '/settings':
        case '/preferences':
        case '/prefs':
            $settings = get_user_settings($user_id);
            
            $msg = "⚙️ <b>User Settings</b>\n\n";
            $msg .= "🔍 Top Results: <b>{$settings['top_results']}</b>\n";
            $msg .= "📊 Priority: <b>{$settings['priority_sort']}</b>\n";
            $msg .= "🎬 Layout: <b>{$settings['result_layout']}</b>\n";
            $msg .= "🗣️ Language: <b>{$settings['language_preference']}</b>\n";
            $msg .= "🎯 Quality: <b>{$settings['quality_preference']}</b>\n";
            $msg .= "🗑️ Auto Delete: <b>" . ($settings['auto_delete'] ? '✅ ON' : '❌ OFF') . "</b>\n";
            $msg .= "⏱️ Delete After: <b>{$settings['delete_seconds']}s</b>\n";
            $msg .= "🔔 Notifications: <b>" . ($settings['notifications'] ? '✅ ON' : '❌ OFF') . "</b>\n";
            $msg .= "🎨 Theme: <b>{$settings['theme']}</b>\n";
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🔍 Search Settings', 'callback_data' => 'set_search']],
                    [['text' => '📊 Priority', 'callback_data' => 'set_priority']],
                    [['text' => '🎬 Layout', 'callback_data' => 'set_layout']],
                    [['text' => '🗣️ Language', 'callback_data' => 'set_lang']],
                    [['text' => '🎯 Quality', 'callback_data' => 'set_quality']],
                    [['text' => '🗑️ Auto Delete', 'callback_data' => 'set_autodelete']],
                    [['text' => '🔄 Reset to Default', 'callback_data' => 'reset_settings']]
                ]
            ];
            
            sendMessage($chat_id, $msg, $keyboard, 'HTML');
            break;
            
        case '/channels':
        case '/join':
        case '/channel':
            $msg = "📢 <b>Join Our Channels</b>\n\n";
            $msg .= "🎬 <b>Main Channel:</b>\n" . MAIN_CHANNEL . "\n";
            $msg .= "Latest movies & updates\n\n";
            $msg .= "🎭 <b>Theater Prints:</b>\n" . THEATER_CHANNEL . "\n";
            $msg .= "HD theater quality prints\n\n";
            $msg .= "💾 <b>Backup Channel:</b>\n" . BACKUP_CHANNEL_USERNAME . "\n";
            $msg .= "Data & archives\n\n";
            $msg .= "📝 <b>Request Group:</b>\n" . REQUEST_CHANNEL . "\n";
            $msg .= "Request movies & get help\n\n";
            $msg .= "📺 <b>Serial Channel:</b>\n" . SERIAL_CHANNEL . "\n";
            $msg .= "Web series & TV shows";
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🎬 Main', 'url' => 'https://t.me/' . ltrim(MAIN_CHANNEL, '@')],
                     ['text' => '🎭 Theater', 'url' => 'https://t.me/' . ltrim(THEATER_CHANNEL, '@')]],
                    [['text' => '💾 Backup', 'url' => 'https://t.me/' . ltrim(BACKUP_CHANNEL_USERNAME, '@')],
                     ['text' => '📝 Request', 'url' => 'https://t.me/' . ltrim(REQUEST_CHANNEL, '@')]],
                    [['text' => '📺 Serial', 'url' => 'https://t.me/' . ltrim(SERIAL_CHANNEL, '@')]]
                ]
            ];
            
            sendMessage($chat_id, $msg, $keyboard, 'HTML');
            break;
            
        // ===== ADMIN COMMANDS =====
        case '/admin':
        case '/dashboard':
            if ($chat_id == ADMIN_ID) {
                admin_dashboard($chat_id);
            }
            break;
            
        case '/backup':
            if ($chat_id == ADMIN_ID) {
                sendMessage($chat_id, "💾 Creating backup...", null, 'HTML', 1000);
                $backup = create_backup('full');
                sendMessage($chat_id, "✅ Backup complete!\n\nSize: {$backup['size_formatted']}\nFiles: {$backup['count']}");
            }
            break;
            
        case '/add':
        case '/quickadd':
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
                
                if (!is_valid_channel_id($channel_id)) {
                    sendMessage($chat_id, "❌ Invalid channel ID format");
                    return;
                }
                
                if (add_movie($movie_name, $message_id, $channel_id)) {
                    $channel = get_channel($channel_id);
                    $msg = "✅ <b>Movie Added!</b>\n\n";
                    $msg .= "🎬 <b>{$movie_name}</b>\n";
                    $msg .= "🆔 <b>Message ID:</b> <code>$message_id</code>\n";
                    $msg .= "📢 <b>Channel:</b> {$channel['emoji']} {$channel['name']}\n";
                    $msg .= "🔗 <b>Link:</b> " . get_channel_link($channel_id, $message_id);
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
                admin_toggle_maintenance($chat_id);
            }
            break;
            
        case '/cleanup':
            if ($chat_id == ADMIN_ID) {
                admin_cleanup($chat_id);
            }
            break;
            
        case '/logs':
            if ($chat_id == ADMIN_ID) {
                $lines = isset($params[0]) ? (int)$params[0] : 50;
                admin_logs($chat_id, $lines);
            }
            break;
            
        case '/config':
            if ($chat_id == ADMIN_ID) {
                admin_config($chat_id);
            }
            break;
            
        case '/analytics':
            if ($chat_id == ADMIN_ID) {
                admin_analytics($chat_id);
            }
            break;
            
        default:
            // Unknown command
            sendMessage($chat_id, "❌ Unknown command. Use /help");
    }
}

// ==============================
// 21. WEBHOOK SETUP FUNCTIONS
// ==============================

/**
 * Set webhook for Render.com
 */
function setup_render_webhook() {
    $webhook_url = getenv('WEBHOOK_URL');
    
    if (!$webhook_url) {
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $webhook_url = $protocol . "://{$_SERVER['HTTP_HOST']}";
        } else {
            $webhook_url = 'https://' . gethostname();
        }
    }
    
    // Ensure URL ends with index.php if needed
    if (substr($webhook_url, -1) !== '/') {
        $webhook_url .= '/';
    }
    
    $result = apiRequest('setWebhook', [
        'url' => $webhook_url,
        'max_connections' => 40,
        'allowed_updates' => json_encode(['message', 'callback_query', 'channel_post']),
        'drop_pending_updates' => true
    ]);
    
    if ($result && isset($result['ok']) && $result['ok']) {
        bot_log("✅ Webhook set to: $webhook_url");
        return true;
    }
    
    bot_log("❌ Failed to set webhook: " . json_encode($result), 'ERROR');
    return false;
}

/**
 * Remove webhook
 */
function remove_webhook() {
    $result = apiRequest('deleteWebhook', ['drop_pending_updates' => true]);
    
    if ($result && isset($result['ok']) && $result['ok']) {
        bot_log("✅ Webhook removed");
        return true;
    }
    
    bot_log("❌ Failed to remove webhook", 'ERROR');
    return false;
}

/**
 * Get webhook info
 */
function get_webhook_info() {
    return apiRequest('getWebhookInfo');
}

/**
 * Health check endpoint
 */
function health_check() {
    $stats = get_system_stats();
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'healthy',
        'timestamp' => time(),
        'uptime' => $stats['uptime'],
        'memory' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true),
        'movies' => $stats['total_movies'],
        'users' => $stats['total_users'],
        'searches' => $stats['total_searches'],
        'downloads' => $stats['total_downloads'],
        'api_calls' => $stats['api_calls'],
        'version' => BOT_VERSION
    ], JSON_PRETTY_PRINT);
    exit;
}

// ==============================
// 22. INITIALIZATION
// ==============================

// Initialize files
initialize_files();

// Load movies into cache
load_movies();

// Set start time if not set
if (!isset($GLOBALS['start_time'])) {
    $GLOBALS['start_time'] = time();
}

// ==============================
// 23. WEBHOOK PROCESSING
// ==============================

// Handle health check
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/health') !== false) {
    health_check();
}

// Handle webhook setup
if (isset($_GET['setwebhook'])) {
    if (setup_render_webhook()) {
        echo "✅ Webhook set successfully!";
    } else {
        echo "❌ Failed to set webhook";
    }
    exit;
}

// Handle webhook removal
if (isset($_GET['removewebhook'])) {
    if (remove_webhook()) {
        echo "✅ Webhook removed successfully!";
    } else {
        echo "❌ Failed to remove webhook";
    }
    exit;
}

// Handle webhook info
if (isset($_GET['webhookinfo'])) {
    $info = get_webhook_info();
    header('Content-Type: application/json');
    echo json_encode($info, JSON_PRETTY_PRINT);
    exit;
}

// Get the raw input
$content = file_get_contents('php://input');
if (empty($content)) {
    // Status page
    if (!isset($_GET['cron'])) {
        $stats = get_system_stats();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Entertainment Tadka Bot</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                *{margin:0;padding:0;box-sizing:border-box}
                body{font-family:'Segoe UI',Arial;background:#1a1a2e;color:#fff;padding:20px}
                .container{max-width:1200px;margin:0 auto}
                h1{color:#4ecca3;margin-bottom:20px}
                .card{background:#16213e;border-radius:15px;padding:25px;margin-bottom:25px}
                .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px}
                .stat-item{background:#0f3460;padding:20px;border-radius:12px;text-align:center}
                .stat-value{font-size:32px;font-weight:bold;color:#4ecca3}
                .stat-label{color:#aaa;font-size:14px}
                .badge{display:inline-block;padding:5px 12px;border-radius:20px;background:#4ecca3;color:#16213e;margin:5px}
                .button{background:#4ecca3;color:#16213e;padding:10px 20px;text-decoration:none;border-radius:8px;display:inline-block;margin:5px}
                .footer{margin-top:30px;text-align:center;color:#666}
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>🎬 Entertainment Tadka Bot v<?php echo BOT_VERSION; ?></h1>
                
                <div class='card'>
                    <h2>🤖 Bot Status</h2>
                    <p><span class='badge'>🟢 ONLINE</span> Uptime: <?php echo $stats['uptime']; ?></p>
                </div>
                
                <div class='stats-grid'>
                    <div class='stat-item'>
                        <div class='stat-value'><?php echo $stats['total_movies']; ?></div>
                        <div class='stat-label'>🎬 Movies</div>
                    </div>
                    <div class='stat-item'>
                        <div class='stat-value'><?php echo $stats['total_users']; ?></div>
                        <div class='stat-label'>👥 Users</div>
                    </div>
                    <div class='stat-item'>
                        <div class='stat-value'><?php echo $stats['total_searches']; ?></div>
                        <div class='stat-label'>🔍 Searches</div>
                    </div>
                    <div class='stat-item'>
                        <div class='stat-value'><?php echo $stats['total_downloads']; ?></div>
                        <div class='stat-label'>📥 Downloads</div>
                    </div>
                </div>
                
                <div class='card'>
                    <h2>🔧 Admin Actions</h2>
                    <a href='?setwebhook' class='button'>Set Webhook</a>
                    <a href='?removewebhook' class='button'>Remove Webhook</a>
                    <a href='?webhookinfo' class='button' target='_blank'>Webhook Info</a>
                    <a href='health' class='button' target='_blank'>Health Check</a>
                </div>
                
                <div class='footer'>
                    &copy; <?php echo date('Y'); ?> Entertainment Tadka Bot
                </div>
            </div>
        </body>
        </html>
        <?php
    }
    exit;
}

// Parse update
$update = json_decode($content, true);

if ($update) {
    // Maintenance mode check
    if (($GLOBALS['maintenance_mode'] ?? false) && isset($update['message'])) {
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
            if (strlen($text) < 3 || strlen($text) > 100) {
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
            update_daily_stat('searches', 1);
            
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
                add_to_waiting($user_id, $text);
                
            } else {
                $msg = $hinglish->get_response('found', $mode) . "\n\n";
                $msg .= "Found <b>" . count($results) . "</b> results:\n\n";
                
                // Build results keyboard
                $keyboard = ['inline_keyboard' => []];
                $row = [];
                
                foreach (array_slice($results, 0, 8) as $movie) {
                    $display = "{$movie['channel_emoji']} " . truncate($movie['movie_name'], 20);
                    $row[] = [
                        'text' => $display,
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
                
                sendMessage($chat_id, $msg, $keyboard, 'HTML');
                
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
            $stmt->bindValue(':id', $movie_id, SQLITE3_INTEGER);
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
            
            $session = get_browser_session($session_id);
            $filters = $session['filters'] ?? [];
            show_browser($chat_id, $page, $filters, $session_id);
        }
        
        // Filter
        elseif (strpos($data, 'filter_') === 0) {
            $parts = explode('_', $data);
            $filter = $parts[1];
            $session_id = $parts[2] ?? '';
            
            $session = get_browser_session($session_id);
            $filters = $session['filters'] ?? [];
            
            if ($filter == 'hd') {
                $filters['hd'] = true;
            } elseif ($filter == 'theater') {
                $filters['theater'] = true;
            } elseif ($filter == 'hindi') {
                $filters['hindi'] = true;
            } elseif ($filter == 'year') {
                $filters['year'] = (int)$parts[2];
                $session_id = $parts[3] ?? '';
            } elseif ($filter == 'clear') {
                $filters = [];
            }
            
            show_browser($chat_id, 1, $filters, $session_id);
        }
        
        // Send all
        elseif (strpos($data, 'sendall_') === 0) {
            $parts = explode('_', $data);
            $type = $parts[1];
            
            if ($type == 'page') {
                $page = (int)$parts[2];
                $session_id = $parts[3] ?? '';
                
                $session = get_browser_session($session_id);
                $movies = load_movies();
                $filters = $session['filters'] ?? [];
                
                if (!empty($filters)) {
                    $filtered = [];
                    foreach ($movies as $m) {
                        $include = true;
                        foreach ($filters as $key => $value) {
                            if ($key == 'hd' && !in_array($m['quality'], ['1080p', '4K'])) $include = false;
                            if ($key == 'theater' && $m['quality'] != 'Theater') $include = false;
                            if ($key == 'hindi' && $m['language'] != 'Hindi') $include = false;
                            if ($key == 'year' && ($m['year'] ?? 0) != $value) $include = false;
                        }
                        if ($include) $filtered[] = $m;
                    }
                    $movies = $filtered;
                }
                
                $p = paginate($movies, $page);
                batch_deliver($chat_id, $p['items'], $user_id, "Page $page");
                
            } elseif ($type == 'search') {
                $query = base64_decode($parts[2]);
                $results = search_movies($query);
                batch_deliver($chat_id, $results, $user_id, "'$query'");
                
            } elseif ($type == 'latest') {
                $movies = get_latest_movies(10);
                batch_deliver($chat_id, $movies, $user_id, "Latest");
                
            } elseif ($type == 'theater') {
                $results = search_movies('', ['type' => 'theater']);
                batch_deliver($chat_id, $results, $user_id, "Theater");
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
        
        // User stats
        elseif ($data == 'my_stats') {
            $user = track_user($user_id);
            $rank = get_user_rank($user['points']);
            
            $msg = "👤 <b>Your Stats</b>\n\n";
            $msg .= "🆔 ID: <code>$user_id</code>\n";
            $msg .= "📛 " . get_user_display($user) . "\n";
            $msg .= "⭐ Points: {$user['points']} ($rank)\n";
            $msg .= "🔍 Searches: {$user['searches']}\n";
            $msg .= "📥 Downloads: {$user['downloads']}\n";
            $msg .= "📝 Requests: {$user['requests']}\n";
            $msg .= "🕐 Last: " . time_ago($user['last_active']);
            
            sendMessage($chat_id, $msg, null, 'HTML');
        }
        
        // Channels
        elseif ($data == 'channels') {
            $msg = "📢 <b>Join Our Channels</b>\n\n";
            $msg .= "🎬 " . MAIN_CHANNEL . "\n";
            $msg .= "🎭 " . THEATER_CHANNEL . "\n";
            $msg .= "💾 " . BACKUP_CHANNEL_USERNAME . "\n";
            $msg .= "📝 " . REQUEST_CHANNEL . "\n";
            $msg .= "📺 " . SERIAL_CHANNEL;
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🎬 Main', 'url' => 'https://t.me/' . ltrim(MAIN_CHANNEL, '@')]],
                    [['text' => '🎭 Theater', 'url' => 'https://t.me/' . ltrim(THEATER_CHANNEL, '@')]],
                    [['text' => '💾 Backup', 'url' => 'https://t.me/' . ltrim(BACKUP_CHANNEL_USERNAME, '@')]],
                    [['text' => '📝 Request', 'url' => 'https://t.me/' . ltrim(REQUEST_CHANNEL, '@')]],
                    [['text' => '📺 Serial', 'url' => 'https://t.me/' . ltrim(SERIAL_CHANNEL, '@')]],
                    [['text' => '🔙 Back', 'callback_data' => 'back_main']]
                ]
            ];
            
            sendMessage($chat_id, $msg, $keyboard, 'HTML');
        }
        
        // Help
        elseif ($data == 'help') {
            $msg = "🤖 <b>Commands</b>\n\n";
            $msg .= "/search - Search movies\n";
            $msg .= "/browse - Browse all\n";
            $msg .= "/latest - New movies\n";
            $msg .= "/trending - Popular\n";
            $msg .= "/theater - Theater prints\n";
            $msg .= "/request - Request movie\n";
            $msg .= "/stats - Your stats\n";
            $msg .= "/top - Leaderboard\n";
            $msg .= "/settings - Preferences\n";
            $msg .= "/channels - Join channels";
            
            sendMessage($chat_id, $msg, null, 'HTML');
        }
        
        // Close message
        elseif (strpos($data, 'close_') === 0) {
            deleteMessage($chat_id, $msg_id);
        }
        
        // Back to main
        elseif ($data == 'back_main') {
            sendMessage($chat_id, "🎬 Back to main menu", null, 'HTML');
        }
        
        // Admin callbacks
        elseif ($chat_id == ADMIN_ID) {
            if ($data == 'admin_back') {
                admin_dashboard($chat_id);
            }
            elseif ($data == 'admin_stats') {
                admin_full_stats($chat_id);
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
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '✅ Full Backup', 'callback_data' => 'do_backup_full']],
                        [['text' => '⚡ Quick Backup', 'callback_data' => 'do_backup_quick']],
                        [['text' => '🔙 Back', 'callback_data' => 'admin_back']]
                    ]
                ];
                sendMessage($chat_id, "💾 Choose backup type:", $keyboard);
            }
            elseif ($data == 'do_backup_full') {
                sendMessage($chat_id, "💾 Creating full backup...");
                $backup = create_backup('full');
                sendMessage($chat_id, "✅ Backup complete!\n\nSize: {$backup['size_formatted']}");
            }
            elseif ($data == 'do_backup_quick') {
                sendMessage($chat_id, "⚡ Creating quick backup...");
                $backup = create_backup('quick');
                sendMessage($chat_id, "✅ Quick backup complete!");
            }
            elseif ($data == 'admin_broadcast') {
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '👥 All Users', 'callback_data' => 'broadcast_target_all']],
                        [['text' => '🟢 Active Now', 'callback_data' => 'broadcast_target_active']],
                        [['text' => '⚫ Inactive', 'callback_data' => 'broadcast_target_inactive']],
                        [['text' => '⭐ Premium', 'callback_data' => 'broadcast_target_premium']],
                        [['text' => '🔙 Back', 'callback_data' => 'admin_back']]
                    ]
                ];
                sendMessage($chat_id, "📢 Select target:", $keyboard);
            }
            elseif (strpos($data, 'broadcast_target_') === 0) {
                $target = substr($data, 16);
                $GLOBALS['broadcast_target'] = $target;
                sendMessage($chat_id, "📝 Enter message to broadcast:");
            }
            elseif (strpos($data, 'broadcast_confirm_') === 0) {
                $parts = explode('_', $data, 4);
                $message = base64_decode($parts[2]);
                $target = $parts[3];
                execute_broadcast($chat_id, $message, $target);
            }
            elseif ($data == 'admin_config') {
                admin_config($chat_id);
            }
            elseif ($data == 'admin_logs') {
                admin_logs($chat_id);
            }
            elseif ($data == 'admin_clear_logs') {
                admin_clear_logs($chat_id);
            }
            elseif ($data == 'admin_cleanup') {
                admin_cleanup($chat_id);
            }
            elseif ($data == 'admin_maintenance') {
                admin_toggle_maintenance($chat_id);
            }
            elseif ($data == 'admin_analytics') {
                admin_analytics($chat_id);
            }
            elseif ($data == 'admin_logout') {
                sendMessage($chat_id, "🔐 Logged out");
            }
        }
    }
    
    // Process delete queue periodically
    process_delete_queue();
}

// ==============================
// 24. CRON JOBS (called via web)
// ==============================

if (isset($_GET['cron'])) {
    $job = $_GET['cron'];
    $key = $_GET['key'] ?? '';
    
    // Simple security check
    $expected_key = md5(BOT_TOKEN . date('Y-m-d'));
    if ($key !== $expected_key) {
        http_response_code(403);
        die('Invalid key');
    }
    
    if ($job == 'backup' && date('H') == AUTO_BACKUP_HOUR) {
        create_backup('full');
        echo "✅ Backup created at " . date('Y-m-d H:i:s');
    }
    
    if ($job == 'cleanup') {
        clean_old_backups(7);
        cache_clear();
        $db = get_db();
        $db->exec("VACUUM");
        echo "✅ Cleanup done at " . date('Y-m-d H:i:s');
    }
    
    if ($job == 'stats') {
        $stats = get_system_stats();
        echo "✅ Stats updated at " . date('Y-m-d H:i:s');
    }
    
    exit;
}

// ==============================
// 25. END OF FILE
// ==============================
?>