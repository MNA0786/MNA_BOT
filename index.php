<?php
// ==============================
// LOAD ENVIRONMENT VARIABLES
// ==============================
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = parse_ini_file(__DIR__ . '/.env');
    foreach ($dotenv as $key => $value) {
        putenv("$key=$value");
    }
}

define('BOT_TOKEN', getenv('BOT_TOKEN') ?: die('BOT_TOKEN not set'));
define('ADMIN_ID', (int)(getenv('ADMIN_ID') ?: 0));
define('REQUEST_GROUP_ID', (int)(getenv('REQUEST_GROUP_ID') ?: 0));
define('WEBHOOK_PASS', getenv('WEBHOOK_PASS') ?: 'your_secret_password');

$channel_ids_str = getenv('CHANNEL_IDS');
if (!$channel_ids_str) die('CHANNEL_IDS not set');
$CHANNEL_IDS = array_map('intval', explode(',', str_replace(' ', '', $channel_ids_str)));

$channel_usernames_str = getenv('CHANNEL_USERNAMES') ?: '';
$CHANNEL_USERNAMES = $channel_usernames_str ? explode(',', str_replace(' ', '', $channel_usernames_str)) : [];

define('ITEMS_PER_PAGE', 5);
define('SEARCH_PER_PAGE', 5);
define('DB_FILE', 'bot.sqlite');
define('RATE_LIMIT_MAX', 10);
define('RATE_LIMIT_WINDOW', 60);
define('CACHE_TTL', 300);
define('SCAN_SCRIPT', __DIR__ . '/scan_old_posts.php');

// Auto-delete timings (seconds)
define('TEXT_DELETE_TIME', 10);
define('SEARCH_DELETE_TIME', 60);
define('FILE_DELETE_TIME', 120);

// ==============================
// DATABASE CLASS
// ==============================
class Database {
    private $pdo;

    public function __construct() {
        $this->pdo = new SQLite3(DB_FILE);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
        $this->pdo->exec('PRAGMA journal_mode = WAL;');
        $this->migrate();
    }

    private function migrate() {
        // Movies table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS movies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                movie_name TEXT NOT NULL,
                channel_id INTEGER NOT NULL,
                message_id INTEGER,
                message_id_raw TEXT,
                date TEXT,
                video_path TEXT,
                caption TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Add caption column if not exists (for older DBs)
        $res = $this->pdo->query("PRAGMA table_info(movies)");
        $columns = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $row['name'];
        }
        if (!in_array('caption', $columns)) {
            $this->pdo->exec("ALTER TABLE movies ADD COLUMN caption TEXT DEFAULT ''");
        }
        
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_movie_name ON movies (movie_name COLLATE NOCASE)");
        
        // FTS5 virtual table
        $this->pdo->exec("
            CREATE VIRTUAL TABLE IF NOT EXISTS movies_fts USING fts5(
                movie_name,
                channel_id UNINDEXED,
                content='movies',
                content_rowid='id',
                tokenize='unicode61'
            )
        ");
        
        $res = $this->pdo->query("SELECT COUNT(*) as cnt FROM movies_fts");
        $row = $res->fetchArray(SQLITE3_ASSOC);
        if ($row['cnt'] == 0) {
            $this->pdo->exec("
                INSERT INTO movies_fts(rowid, movie_name, channel_id)
                SELECT id, movie_name, channel_id FROM movies
            ");
        }
        
        // Triggers
        $this->pdo->exec("
            CREATE TRIGGER IF NOT EXISTS movies_after_insert AFTER INSERT ON movies
            BEGIN
                INSERT INTO movies_fts(rowid, movie_name, channel_id) VALUES (NEW.id, NEW.movie_name, NEW.channel_id);
            END
        ");
        
        $this->pdo->exec("
            CREATE TRIGGER IF NOT EXISTS movies_after_update AFTER UPDATE ON movies
            BEGIN
                UPDATE movies_fts SET movie_name = NEW.movie_name, channel_id = NEW.channel_id WHERE rowid = NEW.id;
            END
        ");
        
        $this->pdo->exec("
            CREATE TRIGGER IF NOT EXISTS movies_after_delete AFTER DELETE ON movies
            BEGIN
                DELETE FROM movies_fts WHERE rowid = OLD.id;
            END
        ");
        
        // Users table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                user_id INTEGER PRIMARY KEY,
                first_name TEXT,
                last_name TEXT,
                username TEXT,
                joined DATETIME,
                last_active DATETIME,
                points INTEGER DEFAULT 0
            )
        ");
        
        // Waiting users table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS waiting_users (
                query_hash TEXT,
                query TEXT,
                chat_id INTEGER,
                user_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (query_hash, chat_id)
            )
        ");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_waiting_query ON waiting_users (query_hash)");
        
        // Stats table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS stats (
                key TEXT PRIMARY KEY,
                value INTEGER DEFAULT 0
            )
        ");
        
        // Requests table for rate limiting
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS requests (
                user_id INTEGER,
                timestamp INTEGER,
                PRIMARY KEY (user_id, timestamp)
            )
        ");
        
        // Cache table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS cache (
                key TEXT PRIMARY KEY,
                value TEXT,
                expires INTEGER
            )
        ");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_cache_expires ON cache (expires)");
        
        // User settings table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS user_settings (
                user_id INTEGER,
                setting_key TEXT,
                setting_value TEXT,
                PRIMARY KEY (user_id, setting_key)
            )
        ");
        
        // Scheduled deletions table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS scheduled_deletions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                chat_id INTEGER NOT NULL,
                message_id INTEGER NOT NULL,
                progress_message_id INTEGER,
                delete_at INTEGER NOT NULL,
                last_edit_at INTEGER,
                status TEXT DEFAULT 'pending'
            )
        ");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_scheduled_deletions_delete_at ON scheduled_deletions (delete_at)");
        
        $this->incrementStat('total_movies', 0);
        $this->incrementStat('searches', 0);
    }

    // ==================== MOVIE METHODS ====================
    
    public function getMoviesCount() {
        $res = $this->pdo->query("SELECT COUNT(*) as cnt FROM movies");
        return $res->fetchArray(SQLITE3_ASSOC)['cnt'];
    }

    public function getMoviesPaginated($page) {
        $offset = ($page - 1) * ITEMS_PER_PAGE;
        $stmt = $this->pdo->prepare("SELECT * FROM movies ORDER BY id DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, ITEMS_PER_PAGE, SQLITE3_INTEGER);
        $stmt->bindValue(2, $offset, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $movies = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $movies[] = $row;
        }
        return $movies;
    }

    public function searchMovies($query) {
        $cache_key = 'search_' . md5(strtolower(trim($query)));
        
        $cached = $this->cacheGet($cache_key);
        if ($cached !== null) {
            return $cached;
        }
        
        $original_query = trim($query);
        if (strlen($original_query) < 2) {
            return [];
        }
        
        $fts_query = str_replace(['"', "'", '*', '-'], ' ', $original_query);
        $fts_query = trim($fts_query);
        
        $words = explode(' ', $fts_query);
        $fts_conditions = [];
        foreach ($words as $word) {
            if (strlen($word) >= 2) {
                $fts_conditions[] = $word . '*';
            }
        }
        $fts_match = implode(' ', $fts_conditions);
        
        if (empty($fts_match)) {
            $fts_match = $fts_query;
        }
        
        $sql = "
            SELECT m.* 
            FROM movies m
            JOIN movies_fts f ON m.id = f.rowid
            WHERE movies_fts MATCH :match
            ORDER BY rank
            LIMIT 25
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':match', $fts_match, SQLITE3_TEXT);
        
        $res = $stmt->execute();
        $results = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $results[] = $row;
        }
        
        if (empty($results)) {
            $results = $this->fallbackSearch($original_query);
        }
        
        $this->cacheSet($cache_key, $results, CACHE_TTL);
        
        return $results;
    }

    private function fallbackSearch($query) {
        $clean_query = strtolower(trim($query));
        $words = array_filter(explode(' ', $clean_query));
        
        $sql = "SELECT * FROM movies WHERE ";
        $conditions = [];
        $params = [];
        
        $conditions[] = "movie_name COLLATE NOCASE LIKE :contains";
        $params[':contains'] = '%' . $clean_query . '%';
        
        foreach ($words as $index => $word) {
            if (strlen($word) >= 2) {
                $param = ':word' . $index;
                $conditions[] = "movie_name COLLATE NOCASE LIKE " . $param;
                $params[$param] = '%' . $word . '%';
            }
        }
        
        $sql .= implode(' OR ', $conditions);
        $sql .= " ORDER BY id DESC LIMIT 25";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, SQLITE3_TEXT);
        }
        
        $res = $stmt->execute();
        $results = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $results[] = $row;
        }
        return $results;
    }

    public function addMovie($movie_name, $channel_id, $message_id_raw, $date = null, $video_path = '', $caption = '') {
        // Check if already exists
        $stmt = $this->pdo->prepare("SELECT id FROM movies WHERE message_id_raw = ? AND channel_id = ?");
        $stmt->bindValue(1, $message_id_raw, SQLITE3_TEXT);
        $stmt->bindValue(2, $channel_id, SQLITE3_INTEGER);
        $res = $stmt->execute();
        if ($res->fetchArray()) {
            return false;
        }
        
        if (!$date) $date = date('d-m-Y');
        $msg_id = is_numeric($message_id_raw) ? (int)$message_id_raw : null;
        
        $stmt = $this->pdo->prepare("
            INSERT INTO movies (movie_name, channel_id, message_id_raw, date, video_path, message_id, caption) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bindValue(1, $movie_name, SQLITE3_TEXT);
        $stmt->bindValue(2, $channel_id, SQLITE3_INTEGER);
        $stmt->bindValue(3, $message_id_raw, SQLITE3_TEXT);
        $stmt->bindValue(4, $date, SQLITE3_TEXT);
        $stmt->bindValue(5, $video_path, SQLITE3_TEXT);
        $stmt->bindValue(6, $msg_id, SQLITE3_INTEGER);
        $stmt->bindValue(7, $caption, SQLITE3_TEXT);
        $stmt->execute();
        
        $this->incrementStat('total_movies');
        $this->clearSearchCache();
        
        return true;
    }

    public function updateMovie($movie_name, $channel_id, $message_id_raw, $date = null, $video_path = '', $caption = '') {
        $msg_id = is_numeric($message_id_raw) ? (int)$message_id_raw : null;
        
        $stmt = $this->pdo->prepare("
            UPDATE movies 
            SET movie_name = ?, date = ?, video_path = ?, message_id = ?, caption = ?
            WHERE message_id_raw = ? AND channel_id = ?
        ");
        $stmt->bindValue(1, $movie_name, SQLITE3_TEXT);
        $stmt->bindValue(2, $date ?? date('d-m-Y'), SQLITE3_TEXT);
        $stmt->bindValue(3, $video_path, SQLITE3_TEXT);
        $stmt->bindValue(4, $msg_id, SQLITE3_INTEGER);
        $stmt->bindValue(5, $caption, SQLITE3_TEXT);
        $stmt->bindValue(6, $message_id_raw, SQLITE3_TEXT);
        $stmt->bindValue(7, $channel_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        $this->clearSearchCache();
        return true;
    }

    public function deleteMovie($channel_id, $message_id_raw) {
        $stmt = $this->pdo->prepare("DELETE FROM movies WHERE channel_id = ? AND message_id_raw = ?");
        $stmt->bindValue(1, $channel_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $message_id_raw, SQLITE3_TEXT);
        $stmt->execute();
        
        $this->clearSearchCache();
        return true;
    }

    // ==================== USER METHODS ====================
    
    public function registerUser($user_id, $first_name, $last_name, $username) {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("
            INSERT OR REPLACE INTO users 
            (user_id, first_name, last_name, username, joined, last_active, points) 
            VALUES (?, ?, ?, ?, COALESCE((SELECT joined FROM users WHERE user_id=?), ?), ?, 
            COALESCE((SELECT points FROM users WHERE user_id=?), 0))
        ");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $first_name, SQLITE3_TEXT);
        $stmt->bindValue(3, $last_name, SQLITE3_TEXT);
        $stmt->bindValue(4, $username, SQLITE3_TEXT);
        $stmt->bindValue(5, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(6, $now, SQLITE3_TEXT);
        $stmt->bindValue(7, $now, SQLITE3_TEXT);
        $stmt->bindValue(8, $user_id, SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function updateUserActivity($user_id) {
        $stmt = $this->pdo->prepare("UPDATE users SET last_active = ? WHERE user_id = ?");
        $stmt->bindValue(1, date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function addUserPoints($user_id, $points) {
        $stmt = $this->pdo->prepare("UPDATE users SET points = points + ? WHERE user_id = ?");
        $stmt->bindValue(1, $points, SQLITE3_INTEGER);
        $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
        $stmt->execute();
    }

    // ==================== WAITING USERS ====================
    
    public function addWaitingUser($query, $chat_id, $user_id) {
        $query_hash = md5(strtolower(trim($query)));
        $stmt = $this->pdo->prepare("
            INSERT OR IGNORE INTO waiting_users (query_hash, query, chat_id, user_id) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bindValue(1, $query_hash, SQLITE3_TEXT);
        $stmt->bindValue(2, $query, SQLITE3_TEXT);
        $stmt->bindValue(3, $chat_id, SQLITE3_INTEGER);
        $stmt->bindValue(4, $user_id, SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function getWaitingUsersForQuery($query) {
        $query_hash = md5(strtolower(trim($query)));
        $stmt = $this->pdo->prepare("SELECT * FROM waiting_users WHERE query_hash = ?");
        $stmt->bindValue(1, $query_hash, SQLITE3_TEXT);
        $res = $stmt->execute();
        $users = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $users[] = $row;
        }
        return $users;
    }

    public function removeWaitingUsers($query) {
        $query_hash = md5(strtolower(trim($query)));
        $stmt = $this->pdo->prepare("DELETE FROM waiting_users WHERE query_hash = ?");
        $stmt->bindValue(1, $query_hash, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function getAllWaitingUsers() {
        $res = $this->pdo->query("SELECT * FROM waiting_users");
        $users = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $users[] = $row;
        }
        return $users;
    }

    public function removeWaitingUser($query, $chat_id) {
        $query_hash = md5(strtolower(trim($query)));
        $stmt = $this->pdo->prepare("DELETE FROM waiting_users WHERE query_hash = ? AND chat_id = ?");
        $stmt->bindValue(1, $query_hash, SQLITE3_TEXT);
        $stmt->bindValue(2, $chat_id, SQLITE3_INTEGER);
        $stmt->execute();
    }

    // ==================== STATISTICS ====================
    
    public function incrementStat($key, $by = 1) {
        $stmt = $this->pdo->prepare("
            INSERT INTO stats (key, value) VALUES (?, ?) 
            ON CONFLICT(key) DO UPDATE SET value = value + ?
        ");
        $stmt->bindValue(1, $key, SQLITE3_TEXT);
        $stmt->bindValue(2, $by, SQLITE3_INTEGER);
        $stmt->bindValue(3, $by, SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function getStats() {
        $res = $this->pdo->query("SELECT key, value FROM stats");
        $stats = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $stats[$row['key']] = $row['value'];
        }
        $stats['total_users'] = $this->pdo->querySingle("SELECT COUNT(*) FROM users");
        return $stats;
    }

    // ==================== CACHE METHODS ====================
    
    public function cacheGet($key) {
        $this->pdo->exec("DELETE FROM cache WHERE expires < " . time());
        
        $stmt = $this->pdo->prepare("SELECT value FROM cache WHERE key = ? AND expires > ?");
        $stmt->bindValue(1, $key, SQLITE3_TEXT);
        $stmt->bindValue(2, time(), SQLITE3_INTEGER);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        return $row ? json_decode($row['value'], true) : null;
    }

    public function cacheSet($key, $value, $ttl = 300) {
        $expires = time() + $ttl;
        $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO cache (key, value, expires) VALUES (?, ?, ?)");
        $stmt->bindValue(1, $key, SQLITE3_TEXT);
        $stmt->bindValue(2, json_encode($value), SQLITE3_TEXT);
        $stmt->bindValue(3, $expires, SQLITE3_INTEGER);
        $stmt->execute();
    }
    
    public function clearSearchCache() {
        $this->pdo->exec("DELETE FROM cache WHERE key LIKE 'search_%'");
    }

    // ==================== USER SETTINGS ====================
    
    public function getUserSetting($user_id, $key, $default = null) {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = ?");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $key, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        return $row ? $row['setting_value'] : $default;
    }

    public function setUserSetting($user_id, $key, $value) {
        $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?)");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $key, SQLITE3_TEXT);
        $stmt->bindValue(3, $value, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function getAllUserSettings($user_id) {
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $settings = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    // ==================== SCHEDULED DELETIONS ====================
    
    public function addScheduledDeletion($chat_id, $message_id, $delete_at, $progress_message_id = null) {
        $stmt = $this->pdo->prepare("INSERT INTO scheduled_deletions (chat_id, message_id, progress_message_id, delete_at) VALUES (?, ?, ?, ?)");
        $stmt->bindValue(1, $chat_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $message_id, SQLITE3_INTEGER);
        $stmt->bindValue(3, $progress_message_id, SQLITE3_INTEGER);
        $stmt->bindValue(4, $delete_at, SQLITE3_INTEGER);
        $stmt->execute();
        return $this->pdo->lastInsertRowID();
    }

    public function getPendingDeletions($limit = 100) {
        $now = time();
        $stmt = $this->pdo->prepare("SELECT * FROM scheduled_deletions WHERE delete_at <= ? AND status = 'pending' ORDER BY delete_at ASC LIMIT ?");
        $stmt->bindValue(1, $now, SQLITE3_INTEGER);
        $stmt->bindValue(2, $limit, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $rows = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function getUpcomingDeletions($window = 300) {
        $now = time();
        $later = $now + $window;
        $stmt = $this->pdo->prepare("SELECT * FROM scheduled_deletions WHERE delete_at BETWEEN ? AND ? AND status = 'pending' ORDER BY delete_at ASC");
        $stmt->bindValue(1, $now, SQLITE3_INTEGER);
        $stmt->bindValue(2, $later, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $rows = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function updateScheduledDeletion($id, $fields) {
        $set = [];
        foreach ($fields as $key => $value) {
            $set[] = "$key = :$key";
        }
        $sql = "UPDATE scheduled_deletions SET " . implode(', ', $set) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        foreach ($fields as $key => $value) {
            $stmt->bindValue(":$key", $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        }
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function deleteScheduledDeletion($id) {
        $stmt = $this->pdo->prepare("DELETE FROM scheduled_deletions WHERE id = ?");
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->execute();
    }
}

// ==============================
// RATE LIMITER
// ==============================
class RateLimiter {
    private $db;
    private $max;
    private $window;

    public function __construct($db, $max, $window) {
        $this->db = $db;
        $this->max = $max;
        $this->window = $window;
    }

    public function check($user_id) {
        $now = time();
        $cutoff = $now - $this->window;
        
        $stmt = $this->db->pdo->prepare("DELETE FROM requests WHERE user_id = ? AND timestamp < ?");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $cutoff, SQLITE3_INTEGER);
        $stmt->execute();
        
        $stmt = $this->db->pdo->prepare("SELECT COUNT(*) as cnt FROM requests WHERE user_id = ? AND timestamp >= ?");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $cutoff, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        
        if ($row['cnt'] >= $this->max) return false;
        
        $stmt = $this->db->pdo->prepare("INSERT INTO requests (user_id, timestamp) VALUES (?, ?)");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $now, SQLITE3_INTEGER);
        $stmt->execute();
        
        return true;
    }
}

// ==============================
// LOGGER
// ==============================
class Logger {
    private static $logPath = __DIR__;
    
    public static function error($msg) {
        error_log(date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, 3, self::$logPath . '/error.log');
    }
    
    public static function info($msg) {
        error_log(date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, 3, self::$logPath . '/info.log');
    }

    public static function warning($msg) {
        error_log(date('[Y-m-d H:i:s] ') . '[WARNING] ' . $msg . PHP_EOL, 3, self::$logPath . '/warning.log');
    }
}

// ==============================
// BOT API CLASS
// ==============================
class BotAPI {
    private $token;
    private $timeout = 30;

    public function __construct($token) {
        $this->token = $token;
    }

    private function request($method, $params = []) {
        $url = "https://api.telegram.org/bot{$this->token}/$method";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $res = curl_exec($ch);
        
        if ($res === false) {
            Logger::error('CURL Error: ' . curl_error($ch) . ' for method: ' . $method);
        }
        
        curl_close($ch);
        return $res;
    }

    public function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = 'HTML') {
        $params = ['chat_id' => $chat_id, 'text' => $text];
        if ($reply_markup) $params['reply_markup'] = json_encode($reply_markup);
        if ($parse_mode) $params['parse_mode'] = $parse_mode;
        return $this->request('sendMessage', $params);
    }

    public function forwardMessage($chat_id, $from_chat_id, $message_id) {
        return $this->request('forwardMessage', [
            'chat_id' => $chat_id,
            'from_chat_id' => $from_chat_id,
            'message_id' => $message_id
        ]);
    }

    public function copyMessage($chat_id, $from_chat_id, $message_id) {
        return $this->request('copyMessage', [
            'chat_id' => $chat_id,
            'from_chat_id' => $from_chat_id,
            'message_id' => $message_id
        ]);
    }

    public function answerCallbackQuery($callback_id, $text = null, $alert = false) {
        $params = ['callback_query_id' => $callback_id];
        if ($text) $params['text'] = $text;
        if ($alert) $params['show_alert'] = true;
        return $this->request('answerCallbackQuery', $params);
    }

    public function editMessageText($chat_id, $message_id, $text, $reply_markup = null) {
        $params = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text
        ];
        if ($reply_markup) $params['reply_markup'] = json_encode($reply_markup);
        return $this->request('editMessageText', $params);
    }

    public function editMessageCaption($chat_id, $message_id, $caption) {
        return $this->request('editMessageCaption', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'caption' => $caption
        ]);
    }

    public function deleteMessage($chat_id, $message_id) {
        return $this->request('deleteMessage', [
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ]);
    }
}

// ==============================
// UTILS
// ==============================
class Utils {
    public static function detectLanguage($text) {
        if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) {
            return 'hindi';
        }
        return 'english';
    }

    public static function getMultilingualResponse($lang, $key) {
        $responses = [
            'hindi' => [
                'welcome' => "🎬 Boss, kis movie ki talash hai?",
                'found' => "✅ Mil gayi! Movie forward ho rahi hai...",
                'not_found' => "😔 Yeh movie abhi available nahi hai!\n\n📝 Aap ise request kar sakte hain: @EntertainmentTadka7860\n\n🔔 Jab bhi yeh add hogi, main automatically bhej dunga!",
                'searching' => "🔍 Dhoondh raha hoon... Zara wait karo",
                'rate_limit' => "⏳ Thoda slow karo bhai! Ek minute mein 10 se zyada search nahi kar sakte.",
                'short_query' => "❌ Kam se kam 2 characters daalo bhai!",
                'results_found' => "🔍 Mil gaye {count} results for '{query}':"
            ],
            'english' => [
                'welcome' => "🎬 Boss, which movie are you looking for?",
                'found' => "✅ Found it! Forwarding the movie...",
                'not_found' => "😔 This movie isn't available yet!\n\n📝 You can request it here: @EntertainmentTadka7860\n\n🔔 I'll send it automatically once it's added!",
                'searching' => "🔍 Searching... Please wait",
                'rate_limit' => "⏳ Slow down please! Max 10 searches per minute.",
                'short_query' => "❌ Please enter at least 2 characters!",
                'results_found' => "🔍 Found {count} results for '{query}':"
            ]
        ];
        
        $response = $responses[$lang][$key] ?? $responses['english'][$key];
        return $response;
    }

    public static function getProgressBar($remaining, $total, $length = 10) {
        $percent = 1 - ($remaining / $total);
        $filled = round($percent * $length);
        $empty = $length - $filled;
        return str_repeat('▓', $filled) . str_repeat('░', $empty);
    }
}

// ==============================
// MOVIE DELIVERER (IMPROVED)
// ==============================
class MovieDeliverer {
    private $bot;
    private $db;

    public function __construct($bot, $db) {
        $this->bot = $bot;
        $this->db = $db;
    }

    /**
     * Deliver a single movie to user with attribution and auto-delete
     */
    public function deliverItem($chat_id, $movie, $user_info) {
        $channel_id = $movie['channel_id'];
        $msg_id = $movie['message_id'] ?? null;
        
        if (!$msg_id) {
            // Agar message_id missing hai to sirf text bhejo
            $text = "🎬 " . htmlspecialchars($movie['movie_name'] ?? 'Unknown') . "\n";
            $text .= "📝 Ref: " . htmlspecialchars($movie['message_id_raw']) . "\n";
            $text .= "📅 Date: " . htmlspecialchars($movie['date']);
            $this->bot->sendMessage($chat_id, $text);
            Logger::info("Delivered text-only for movie ID: {$movie['id']}");
            return false;
        }

        // Pehle copyMessage try karo
        $res = $this->bot->copyMessage($chat_id, $channel_id, $msg_id);
        $decoded = json_decode($res, true);
        
        if ($decoded && isset($decoded['ok']) && $decoded['ok']) {
            $new_msg_id = $decoded['result']['message_id'];
            
            // Edit caption to add attribution
            $attribution = "\n\nREQUESTED BY : @" . ($user_info['username'] ?? $user_info['first_name'] ?? 'User');
            $new_caption = ($movie['caption'] ?? '') . $attribution;
            $this->bot->editMessageCaption($chat_id, $new_msg_id, $new_caption);
            
            // Schedule deletion after FILE_DELETE_TIME seconds
            $delete_at = time() + FILE_DELETE_TIME;
            $delete_id = $this->db->addScheduledDeletion($chat_id, $new_msg_id, $delete_at);
            
            // Send progress message
            $progress_text = "⚠️ File Delete in " . FILE_DELETE_TIME . "s\n" . Utils::getProgressBar(FILE_DELETE_TIME, FILE_DELETE_TIME);
            $progress_res = $this->bot->sendMessage($chat_id, $progress_text);
            $progress_decoded = json_decode($progress_res, true);
            if ($progress_decoded && isset($progress_decoded['ok'])) {
                $progress_msg_id = $progress_decoded['result']['message_id'];
                // Link progress message to deletion record
                $this->db->updateScheduledDeletion($delete_id, ['progress_message_id' => $progress_msg_id]);
                // Also schedule deletion of progress message (same time)
                $this->db->addScheduledDeletion($chat_id, $progress_msg_id, $delete_at);
            }
            
            Logger::info("copyMessage successful for movie ID: {$movie['id']}");
            return true;
        }

        // Agar copy fail ho to forwardMessage try karo
        Logger::warning("copyMessage failed for movie ID: {$movie['id']}, trying forwardMessage...");
        $res = $this->bot->forwardMessage($chat_id, $channel_id, $msg_id);
        $decoded = json_decode($res, true);
        
        if ($decoded && isset($decoded['ok']) && $decoded['ok']) {
            // Forwarded message cannot be edited, so no attribution. Just schedule deletion.
            $new_msg_id = $decoded['result']['message_id'];
            $delete_at = time() + FILE_DELETE_TIME;
            $this->db->addScheduledDeletion($chat_id, $new_msg_id, $delete_at);
            Logger::info("forwardMessage successful for movie ID: {$movie['id']}");
            return true;
        }

        // Dono fail ho gaye
        Logger::error("Both copy and forward failed for movie ID: {$movie['id']}. Channel: $channel_id, Msg: $msg_id");
        
        // User ko batao ki file bhejne mein problem hui
        $this->bot->sendMessage($chat_id, "⚠️ Sorry, yeh movie abhi send nahi ho pa rahi. Baad mein try karein.");
        return false;
    }

    /**
     * Deliver multiple movies with progress
     */
    public function deliverPage($chat_id, $movies, $user_info, $progress_msg = null) {
        $total = count($movies);
        if ($total == 0) return;
        
        $success = 0;
        foreach ($movies as $i => $m) {
            if ($this->deliverItem($chat_id, $m, $user_info)) $success++;
            
            if ($progress_msg && ($i+1) % 3 == 0) {
                $this->bot->editMessageText(
                    $chat_id, 
                    $progress_msg['message_id'], 
                    "⏳ Forwarding... (" . ($i+1) . "/$total)"
                );
            }
            // Small delay to avoid flood
            usleep(500000); // 0.5 sec
        }
        
        if ($progress_msg) {
            $this->bot->editMessageText(
                $chat_id, 
                $progress_msg['message_id'], 
                "✅ Successfully forwarded $success/$total movies"
            );
            // Schedule deletion of this status message after TEXT_DELETE_TIME
            $delete_at = time() + TEXT_DELETE_TIME;
            $this->db->addScheduledDeletion($chat_id, $progress_msg['message_id'], $delete_at);
        }
    }
}

// ==============================
// MAIN BOT CLASS
// ==============================
class Bot {
    private $api;
    private $db;
    private $limiter;
    private $deliverer;
    private $update;
    private $channel_ids;
    private $request_group_id;

    public function __construct($channel_ids, $request_group_id) {
        $this->db = new Database();
        $this->api = new BotAPI(BOT_TOKEN);
        $this->limiter = new RateLimiter($this->db, RATE_LIMIT_MAX, RATE_LIMIT_WINDOW);
        $this->deliverer = new MovieDeliverer($this->api, $this->db);
        $this->channel_ids = $channel_ids;
        $this->request_group_id = $request_group_id;
    }

    public function handle($update) {
        $this->update = $update;
        
        try {
            if (isset($update['channel_post'])) {
                $post = $update['channel_post'];
                
                if (isset($post['delete_chat_channel']) || isset($post['delete_message'])) {
                    $this->handleDeletedChannelPost($post);
                } else {
                    $this->handleChannelPost($post);
                }
            } elseif (isset($update['message'])) {
                $this->handleMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->handleCallback($update['callback_query']);
            }
        } catch (Exception $e) {
            Logger::error('Handle error: ' . $e->getMessage());
        }
    }

    private function handleDeletedChannelPost($post) {
        $chat_id = $post['chat']['id'];
        if (!in_array($chat_id, $this->channel_ids)) return;
        
        $message_id = $post['message_id'];
        
        $this->db->deleteMovie($chat_id, $message_id);
        
        Logger::info("Deleted movie with ID: $message_id from channel: $chat_id");
    }

    private function handleChannelPost($post) {
        $chat_id = $post['chat']['id'];
        if (!in_array($chat_id, $this->channel_ids)) return;
        
        $is_edited = isset($post['edit_date']);
        
        $movie_name = $this->extractMovieName($post);
        $caption = $post['caption'] ?? '';
        $media_group_id = $post['media_group_id'] ?? null;
        
        if ($is_edited) {
            $this->db->updateMovie($movie_name, $chat_id, $post['message_id'], date('d-m-Y'), '', $caption);
            Logger::info("Updated movie: $movie_name (ID: {$post['message_id']})");
        } else {
            $added = $this->db->addMovie($movie_name, $chat_id, $post['message_id'], date('d-m-Y'), '', $caption);
            
            if ($added) {
                Logger::info("New movie added: $movie_name (ID: {$post['message_id']})");
                $this->notifyWaitingUsersFlexible($movie_name);
                
                if ($media_group_id) {
                    $this->handleMediaGroup($media_group_id, $movie_name, $chat_id);
                }
            }
        }
    }

    private function extractMovieName($post) {
        if (!empty($post['caption'])) {
            return $this->cleanMovieName($post['caption']);
        }
        if (!empty($post['text'])) {
            return $this->cleanMovieName($post['text']);
        }
        if (isset($post['document']['file_name'])) {
            return $this->cleanMovieName($post['document']['file_name']);
        }
        if (isset($post['media']['caption']) && !empty($post['media']['caption'])) {
            return $this->cleanMovieName($post['media']['caption']);
        }
        return 'Uploaded Media - ' . date('d-m-Y H:i');
    }

    private function cleanMovieName($name) {
        $name = preg_replace('/[^\w\s\-\.\(\)\[\]]/u', '', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));
        return $name;
    }

    private function handleMediaGroup($group_id, $movie_name, $chat_id) {
        Logger::info("Media group detected: $group_id for movie: $movie_name");
    }

    private function notifyWaitingUsersFlexible($movie_name) {
        $all_waiting = $this->db->getAllWaitingUsers();
        
        foreach ($all_waiting as $waiting) {
            $query = $waiting['query'];
            
            if (stripos($movie_name, $query) !== false) {
                $msg = "✅ '" . htmlspecialchars($movie_name) . "' ab channel mein add ho gaya hai!\n";
                $msg .= "Aapki request '$query' match ho gayi!";
                $this->api->sendMessage($waiting['chat_id'], $msg);
                
                $this->db->removeWaitingUser($waiting['query'], $waiting['chat_id']);
            }
        }
    }

    private function handleMessage($msg) {
        $chat_id = $msg['chat']['id'];
        $user_id = $msg['from']['id'];
        $text = trim($msg['text'] ?? '');
        $chat_type = $msg['chat']['type'] ?? 'private';

        $this->db->registerUser(
            $user_id,
            $msg['from']['first_name'] ?? '',
            $msg['from']['last_name'] ?? '',
            $msg['from']['username'] ?? ''
        );
        $this->db->updateUserActivity($user_id);

        if ($chat_id == $this->request_group_id) {
            if (!empty($text) && strpos($text, '/') !== 0) {
                $this->handleSearch($chat_id, $user_id, $text);
            }
            return;
        }

        if (strpos($text, '/') === 0) {
            $this->handleCommand($chat_id, $user_id, $text);
        } else {
            if (!$this->limiter->check($user_id)) {
                $lang = Utils::detectLanguage($text);
                $this->api->sendMessage($chat_id, Utils::getMultilingualResponse($lang, 'rate_limit'));
                return;
            }
            $this->handleSearch($chat_id, $user_id, $text);
        }
    }

    private function handleSearch($chat_id, $user_id, $query) {
        if (strlen($query) < 2) {
            $lang = Utils::detectLanguage($query);
            $this->api->sendMessage($chat_id, Utils::getMultilingualResponse($lang, 'short_query'));
            return;
        }

        $lang = Utils::detectLanguage($query);
        
        $search_msg = $this->api->sendMessage($chat_id, Utils::getMultilingualResponse($lang, 'searching'));
        $search_msg = json_decode($search_msg, true);

        $results = $this->db->searchMovies($query);

        if (empty($results)) {
            $not_found = Utils::getMultilingualResponse($lang, 'not_found');
            $this->api->sendMessage($chat_id, $not_found);
            
            $this->db->addWaitingUser($query, $chat_id, $user_id);
            
            $this->db->incrementStat('searches');
            $this->db->addUserPoints($user_id, 1);
            
            // Schedule deletion of the not_found message after TEXT_DELETE_TIME
            if ($search_msg && isset($search_msg['result']['message_id'])) {
                $delete_at = time() + TEXT_DELETE_TIME;
                $this->db->addScheduledDeletion($chat_id, $search_msg['result']['message_id'], $delete_at);
            }
            return;
        }

        $query_hash = md5(strtolower(trim($query)));
        $cache_data = [
            'query' => $query,
            'results' => $results
        ];
        $this->db->cacheSet('search_res_' . $query_hash, $cache_data, CACHE_TTL);

        $this->sendSearchPage($chat_id, $user_id, $query_hash, 1);

        $this->db->incrementStat('searches');
        $this->db->addUserPoints($user_id, 5);
        
        if ($search_msg && isset($search_msg['result']['message_id'])) {
            // Delete the searching message after TEXT_DELETE_TIME
            $delete_at = time() + TEXT_DELETE_TIME;
            $this->db->addScheduledDeletion($chat_id, $search_msg['result']['message_id'], $delete_at);
        }
    }

    private function sendSearchPage($chat_id, $user_id, $query_hash, $page) {
        $cache_key = 'search_res_' . $query_hash;
        $cache_data = $this->db->cacheGet($cache_key);
        if (!$cache_data) {
            $this->api->sendMessage($chat_id, "⏳ Search expired. Please search again.");
            return;
        }
        $query = $cache_data['query'];
        $results = $cache_data['results'];
        $total = count($results);
        $total_pages = (int)ceil($total / SEARCH_PER_PAGE);
        $page = max(1, min($page, $total_pages));
        $offset = ($page - 1) * SEARCH_PER_PAGE;
        $page_results = array_slice($results, $offset, SEARCH_PER_PAGE);
        
        $lang = Utils::detectLanguage($query);
        $found_text = str_replace(
            ['{count}', '{query}'],
            [$total, htmlspecialchars($query)],
            Utils::getMultilingualResponse($lang, 'results_found')
        );
        
        $text = $found_text . "\n\n";
        foreach ($page_results as $index => $r) {
            $num = $offset + $index + 1;
            $text .= $num . ". " . htmlspecialchars($r['movie_name']) . "\n";
        }
        
        // Get user settings for layout
        $layout = $this->db->getUserSetting($user_id, 'result_layout', 'buttons');
        
        $keyboard = ['inline_keyboard' => []];
        
        if ($layout == 'buttons') {
            // Each movie as button
            foreach ($page_results as $r) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => '🎬 ' . htmlspecialchars($r['movie_name']), 'callback_data' => 'movie_' . $r['id']]
                ];
            }
        } else {
            // Text mode: just list, no buttons for individual movies
            // We'll add a "Send All" button below
        }
        
        // Navigation row
        $nav_row = [];
        if ($page > 1) {
            $nav_row[] = ['text' => '⬅️ Prev', 'callback_data' => "s_{$query_hash}_" . ($page-1)];
        }
        $nav_row[] = ['text' => "📄 $page/$total_pages", 'callback_data' => "s_pageinfo_{$query_hash}_{$page}"];
        if ($page < $total_pages) {
            $nav_row[] = ['text' => 'Next ➡️', 'callback_data' => "s_{$query_hash}_" . ($page+1)];
        }
        $keyboard['inline_keyboard'][] = $nav_row;
        
        // Send All button for current page or all results?
        $keyboard['inline_keyboard'][] = [
            ['text' => '📦 Send All Movies', 'callback_data' => 'send_all_' . $query_hash]
        ];
        
        // Send the message
        $response = $this->api->sendMessage($chat_id, $text, $keyboard);
        $decoded = json_decode($response, true);
        if ($decoded && isset($decoded['ok'])) {
            $msg_id = $decoded['result']['message_id'];
            // Schedule deletion of this search results message after SEARCH_DELETE_TIME
            $delete_at = time() + SEARCH_DELETE_TIME;
            $this->db->addScheduledDeletion($chat_id, $msg_id, $delete_at);
        }
    }

    private function handleCommand($chat_id, $user_id, $text) {
        $parts = explode(' ', $text);
        $cmd = strtolower($parts[0]);
        
        switch ($cmd) {
            case '/start':
                $welcome = "🎬 Welcome to Entertainment Tadka Bot!\n\n";
                $welcome .= "📢 <b>How to use:</b>\n";
                $welcome .= "• Simply type any movie name\n";
                $welcome .= "• Use English or Hindi\n";
                $welcome .= "• Partial names also work\n\n";
                $welcome .= "🔍 <b>Examples:</b>\n";
                $welcome .= "• kgf\n• pushpa\n• avengers\n\n";
                $welcome .= "📢 <b>Join our channels:</b>\n";
                $welcome .= "@EntertainmentTadka786, @Entertainment_Tadka_Serial_786\n\n";
                $welcome .= "💬 <b>Request Group:</b> @EntertainmentTadka7860\n\n";
                $welcome .= "⚙️ <b>Settings:</b> Use /settings to customize your experience.";
                
                $this->api->sendMessage($chat_id, $welcome);
                $this->db->addUserPoints($user_id, 10);
                break;
                
            case '/help':
                $help = "🤖 <b>Entertainment Tadka Bot</b>\n\n";
                $help .= "📋 <b>Available Commands:</b>\n";
                $help .= "/start - Welcome message\n";
                $help .= "/help - This help\n";
                $help .= "/settings - Customize bot settings\n";
                $help .= "/stats - Bot statistics (admin only)\n";
                $help .= "/totalupload [page] - List all movies\n";
                $help .= "/checkdate - Movies by date\n";
                $help .= "/scanold - Scan old channel posts (admin only)\n\n";
                $help .= "🔍 <b>Just type any movie name to search!</b>";
                
                $this->api->sendMessage($chat_id, $help);
                break;
                
            case '/settings':
                $this->showSettingsMenu($chat_id, $user_id);
                break;
                
            case '/stats':
                if ($user_id == ADMIN_ID) {
                    $stats = $this->db->getStats();
                    $msg = "📊 <b>Bot Statistics</b>\n\n";
                    $msg .= "🎬 Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
                    $msg .= "👥 Total Users: " . ($stats['total_users'] ?? 0) . "\n";
                    $msg .= "🔍 Total Searches: " . ($stats['searches'] ?? 0) . "\n";
                    $msg .= "⏰ Last Updated: " . date('Y-m-d H:i:s');
                    
                    $this->api->sendMessage($chat_id, $msg);
                }
                break;
                
            case '/totalupload':
            case '/totaluploads':
                $page = isset($parts[1]) ? (int)$parts[1] : 1;
                $this->cmdTotalUpload($chat_id, $page);
                break;
                
            case '/checkdate':
                $this->cmdCheckDate($chat_id);
                break;

            case '/scanold':
                if ($user_id == ADMIN_ID) {
                    $this->cmdScanOldPosts($chat_id);
                } else {
                    $this->api->sendMessage($chat_id, "❌ Sirf admin yeh command use kar sakta hai.");
                }
                break;
                
            default:
                break;
        }
    }

    private function showSettingsMenu($chat_id, $user_id) {
        $text = "⚙️ <b>Settings Panel</b>\n\nChoose a category:";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔎 Search Settings', 'callback_data' => 'settings_category_search']],
                [['text' => '📥 Download Settings', 'callback_data' => 'settings_category_download']],
                [['text' => '🎬 Layout Settings', 'callback_data' => 'settings_category_layout']],
                [['text' => '⚙️ Bot Preferences', 'callback_data' => 'settings_category_prefs']],
                [['text' => '🔄 Reset to Default', 'callback_data' => 'settings_reset']]
            ]
        ];
        
        $this->api->sendMessage($chat_id, $text, $keyboard);
    }

    private function handleCallback($cb) {
        $data = $cb['data'];
        $msg = $cb['message'];
        $chat_id = $msg['chat']['id'];
        $user_id = $cb['from']['id'];
        $cb_id = $cb['id'];

        try {
            if (strpos($data, 'movie_') === 0) {
                $id = (int)substr($data, 6);
                
                $stmt = $this->db->pdo->prepare("SELECT * FROM movies WHERE id = ?");
                $stmt->bindValue(1, $id, SQLITE3_INTEGER);
                $res = $stmt->execute();
                $movie = $res->fetchArray(SQLITE3_ASSOC);
                
                if ($movie) {
                    $user_info = [
                        'username' => $cb['from']['username'] ?? '',
                        'first_name' => $cb['from']['first_name'] ?? ''
                    ];
                    $success = $this->deliverer->deliverItem($chat_id, $movie, $user_info);
                    if ($success) {
                        $this->api->answerCallbackQuery($cb_id, "✅ Movie bhej di gayi!", false);
                    } else {
                        $this->api->answerCallbackQuery($cb_id, "❌ Movie send fail hui. Admin ko inform kar diya gaya.", true);
                    }
                } else {
                    $this->api->answerCallbackQuery($cb_id, "❌ Yeh movie abhi available nahi", true);
                }
            }
            elseif (strpos($data, 's_') === 0) {
                if (strpos($data, 's_pageinfo_') === 0) {
                    $parts = explode('_', $data);
                    $hash = $parts[2] ?? '';
                    $page = $parts[3] ?? 1;
                    $cache_key = 'search_res_' . $hash;
                    $cache_data = $this->db->cacheGet($cache_key);
                    $total = $cache_data ? count($cache_data['results']) : 0;
                    $total_pages = ceil($total / SEARCH_PER_PAGE);
                    $this->api->answerCallbackQuery($cb_id, "Page $page of $total_pages", false);
                } else {
                    $parts = explode('_', $data);
                    if (count($parts) >= 3) {
                        $hash = $parts[1];
                        $page = (int)$parts[2];
                        $this->sendSearchPage($chat_id, $user_id, $hash, $page);
                        $this->api->answerCallbackQuery($cb_id, "Loading page $page...");
                    } else {
                        $this->api->answerCallbackQuery($cb_id, "Invalid data", true);
                    }
                }
            }
            elseif (strpos($data, 'send_all_') === 0) {
                $hash = substr($data, 9);
                $cache_key = 'search_res_' . $hash;
                $cache_data = $this->db->cacheGet($cache_key);
                if ($cache_data) {
                    $movies = $cache_data['results'];
                    $user_info = [
                        'username' => $cb['from']['username'] ?? '',
                        'first_name' => $cb['from']['first_name'] ?? ''
                    ];
                    $progress = $this->api->sendMessage($chat_id, "⏳ Sending all " . count($movies) . " movies...");
                    $progress_decoded = json_decode($progress, true);
                    $this->deliverer->deliverPage($chat_id, $movies, $user_info, $progress_decoded);
                    $this->api->answerCallbackQuery($cb_id, "✅ All movies sent!");
                } else {
                    $this->api->answerCallbackQuery($cb_id, "❌ Search expired, please search again.", true);
                }
            }
            elseif (strpos($data, 'settings_') === 0) {
                $this->handleSettingsCallback($chat_id, $user_id, $data, $cb_id);
            }
            elseif (strpos($data, 'tu_prev_') === 0) {
                $page = (int)substr($data, 8);
                $this->cmdTotalUpload($chat_id, $page);
                $this->api->answerCallbackQuery($cb_id, "Page $page");
            }
            elseif (strpos($data, 'tu_next_') === 0) {
                $page = (int)substr($data, 8);
                $this->cmdTotalUpload($chat_id, $page);
                $this->api->answerCallbackQuery($cb_id, "Page $page");
            }
            elseif (strpos($data, 'tu_view_') === 0) {
                $page = (int)substr($data, 8);
                $movies = $this->db->getMoviesPaginated($page);
                $user_info = [
                    'username' => $cb['from']['username'] ?? '',
                    'first_name' => $cb['from']['first_name'] ?? ''
                ];
                $progress = $this->api->sendMessage($chat_id, "⏳ Sending page $page movies...");
                $this->deliverer->deliverPage($chat_id, $movies, $user_info, json_decode($progress, true));
                $this->api->answerCallbackQuery($cb_id, "✅ Page resent");
            }
            elseif ($data === 'tu_stop') {
                $this->api->sendMessage($chat_id, "✅ Stopped. Type /totalupload to start again.");
                $this->api->answerCallbackQuery($cb_id, "Stopped");
            }
            elseif ($data === 'current_page') {
                $this->api->answerCallbackQuery($cb_id, "You are on this page");
            }
        } catch (Exception $e) {
            Logger::error('Callback error: ' . $e->getMessage());
            $this->api->answerCallbackQuery($cb_id, "⚠️ Error occurred", true);
        }
    }

    private function handleSettingsCallback($chat_id, $user_id, $data, $cb_id) {
        $parts = explode('_', $data);
        $action = $parts[1] ?? '';
        
        switch ($action) {
            case 'category':
                $category = $parts[2] ?? '';
                $this->showSettingsCategory($chat_id, $user_id, $category);
                $this->api->answerCallbackQuery($cb_id);
                break;
                
            case 'toggle':
                $setting = $parts[2] ?? '';
                $current = $this->db->getUserSetting($user_id, $setting, 'off');
                $new = ($current == 'on') ? 'off' : 'on';
                $this->db->setUserSetting($user_id, $setting, $new);
                $this->api->answerCallbackQuery($cb_id, "✅ $setting set to $new");
                // Refresh category menu
                $category = $parts[3] ?? '';
                if ($category) {
                    $this->showSettingsCategory($chat_id, $user_id, $category);
                }
                break;
                
            case 'select':
                $setting = $parts[2] ?? '';
                $value = $parts[3] ?? '';
                $this->db->setUserSetting($user_id, $setting, $value);
                $this->api->answerCallbackQuery($cb_id, "✅ $setting set to $value");
                $category = $parts[4] ?? '';
                if ($category) {
                    $this->showSettingsCategory($chat_id, $user_id, $category);
                }
                break;
                
            case 'reset':
                // Reset all settings for user
                $stmt = $this->db->pdo->prepare("DELETE FROM user_settings WHERE user_id = ?");
                $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
                $stmt->execute();
                $this->api->answerCallbackQuery($cb_id, "✅ All settings reset to default");
                $this->showSettingsMenu($chat_id, $user_id);
                break;
                
            default:
                $this->api->answerCallbackQuery($cb_id, "Unknown option");
        }
    }

    private function showSettingsCategory($chat_id, $user_id, $category) {
        $text = "⚙️ <b>" . ucfirst($category) . " Settings</b>\n\n";
        $keyboard = ['inline_keyboard' => []];
        
        switch ($category) {
            case 'search':
                $top_results = $this->db->getUserSetting($user_id, 'top_results', '5');
                $text .= "🔎 <b>Search Settings</b>\n";
                $text .= "• Top Results: $top_results\n";
                $keyboard['inline_keyboard'][] = [
                    ['text' => "🔢 Top Results: $top_results", 'callback_data' => 'settings_select_top_results_5_search']
                ];
                // We'll add more options like sorting later
                break;
                
            case 'download':
                $auto_scan = $this->db->getUserSetting($user_id, 'auto_scan', 'off');
                $spoiler = $this->db->getUserSetting($user_id, 'spoiler_mode', 'off');
                $text .= "📥 <b>Download Settings</b>\n";
                $text .= "• Auto Scan: $auto_scan\n";
                $text .= "• Spoiler Mode: $spoiler\n";
                $keyboard['inline_keyboard'][] = [
                    ['text' => "🔄 Auto Scan: $auto_scan", 'callback_data' => 'settings_toggle_auto_scan_download']
                ];
                $keyboard['inline_keyboard'][] = [
                    ['text' => "🎭 Spoiler Mode: $spoiler", 'callback_data' => 'settings_toggle_spoiler_mode_download']
                ];
                break;
                
            case 'layout':
                $result_layout = $this->db->getUserSetting($user_id, 'result_layout', 'buttons');
                $priority = $this->db->getUserSetting($user_id, 'priority_sort', 'relevance');
                $text .= "🎬 <b>Layout Settings</b>\n";
                $text .= "• Result Layout: $result_layout\n";
                $text .= "• Priority: $priority\n";
                $keyboard['inline_keyboard'][] = [
                    ['text' => "📋 Layout: $result_layout", 'callback_data' => 'settings_select_result_layout_buttons_layout'],
                    ['text' => "Text", 'callback_data' => 'settings_select_result_layout_text_layout']
                ];
                $keyboard['inline_keyboard'][] = [
                    ['text' => "🔽 Priority: $priority", 'callback_data' => 'settings_select_priority_sort_relevance_layout'],
                    ['text' => "Size", 'callback_data' => 'settings_select_priority_sort_size_layout'],
                    ['text' => "Quality", 'callback_data' => 'settings_select_priority_sort_quality_layout']
                ];
                break;
                
            case 'prefs':
                $personalize = $this->db->getUserSetting($user_id, 'personalize', 'off');
                $text .= "⚙️ <b>Bot Preferences</b>\n";
                $text .= "• Personalize Panel: $personalize\n";
                $keyboard['inline_keyboard'][] = [
                    ['text' => "🎨 Personalize: $personalize", 'callback_data' => 'settings_toggle_personalize_prefs']
                ];
                break;
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '🔙 Back to Menu', 'callback_data' => 'settings_back']
        ];
        
        $this->api->editMessageText($chat_id, $this->update['callback_query']['message']['message_id'], $text, $keyboard);
    }

    private function cmdCheckDate($chat_id) {
        $res = $this->db->pdo->query("SELECT date, COUNT(*) as cnt FROM movies GROUP BY date ORDER BY date DESC");
        $dates = [];
        $total = 0;
        
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $dates[] = $row;
            $total += $row['cnt'];
        }
        
        $msg = "📅 <b>Movies Upload Record</b>\n\n";
        foreach ($dates as $d) {
            $msg .= "➡️ " . htmlspecialchars($d['date']) . ": <b>" . $d['cnt'] . "</b> movies\n";
        }
        
        $days = count($dates);
        $avg = $days ? round($total / $days, 2) : 0;
        
        $msg .= "\n📊 <b>Summary:</b>\n";
        $msg .= "• Total Days: $days\n";
        $msg .= "• Total Movies: $total\n";
        $msg .= "• Avg per day: $avg";
        
        $this->api->sendMessage($chat_id, $msg);
    }

    private function cmdScanOldPosts($chat_id) {
        if (!file_exists(SCAN_SCRIPT)) {
            $this->api->sendMessage($chat_id, "❌ Scanner script not found. Please ensure scan_old_posts.php exists.");
            return;
        }

        $command = "php " . escapeshellarg(SCAN_SCRIPT) . " > /dev/null 2>&1 &";
        shell_exec($command);

        $this->api->sendMessage($chat_id, "✅ Channel scan started in background. It may take a while depending on number of messages.\n\nCheck logs for progress.");
    }

    private function cmdTotalUpload($chat_id, $page) {
        $total = $this->db->getMoviesCount();
        
        if ($total == 0) {
            $this->api->sendMessage($chat_id, "📭 No movies found! Add some movies first.");
            return;
        }
        
        $total_pages = (int)ceil($total / ITEMS_PER_PAGE);
        $page = max(1, min($page, $total_pages));
        $movies = $this->db->getMoviesPaginated($page);

        $progress = $this->api->sendMessage($chat_id, "⏳ Sending page $page movies...");
        $progress_decoded = json_decode($progress, true);
        // We need user info for attribution? For total upload, maybe not needed.
        $user_info = ['username' => 'System', 'first_name' => 'System'];
        $this->deliverer->deliverPage($chat_id, $movies, $user_info, $progress_decoded);

        $title = "🎬 <b>Total Uploads</b>\n\n";
        $title .= "📊 <b>Statistics:</b>\n";
        $title .= "• Total Movies: <b>$total</b>\n";
        $title .= "• Current Page: <b>$page/$total_pages</b>\n";
        $title .= "• Showing: <b>" . count($movies) . " movies</b>\n\n";
        $title .= "📋 <b>Current Page Movies:</b>\n";
        
        foreach ($movies as $i => $m) {
            $title .= ($i + 1) . ". " . htmlspecialchars($m['movie_name']) . "\n";
        }
        
        $title .= "\n📍 Use buttons to navigate or resend current page";

        $kb = $this->buildTotalUploadKeyboard($page, $total_pages);
        $this->api->sendMessage($chat_id, $title, $kb);
    }

    private function buildTotalUploadKeyboard($page, $total_pages) {
        $kb = ['inline_keyboard' => []];
        
        $nav = [];
        if ($page > 1) {
            $nav[] = ['text' => '⬅️ Previous', 'callback_data' => 'tu_prev_' . ($page - 1)];
        }
        $nav[] = ['text' => "📄 $page/$total_pages", 'callback_data' => 'current_page'];
        if ($page < $total_pages) {
            $nav[] = ['text' => 'Next ➡️', 'callback_data' => 'tu_next_' . ($page + 1)];
        }
        $kb['inline_keyboard'][] = $nav;
        
        $kb['inline_keyboard'][] = [
            ['text' => '🎬 Send This Page', 'callback_data' => 'tu_view_' . $page],
            ['text' => '🛑 Stop', 'callback_data' => 'tu_stop']
        ];
        
        if ($total_pages > 5) {
            $jump = [];
            if ($page > 1) {
                $jump[] = ['text' => '⏮️ First', 'callback_data' => 'tu_prev_1'];
            }
            if ($page < $total_pages) {
                $jump[] = ['text' => 'Last ⏭️', 'callback_data' => 'tu_next_' . $total_pages];
            }
            if (!empty($jump)) {
                $kb['inline_keyboard'][] = $jump;
            }
        }
        
        return $kb;
    }
}

// ==============================
// RESOLVE CHANNEL USERNAMES TO IDs
// ==============================
function resolveChannelUsernames($bot_token, $usernames_str) {
    if (empty($usernames_str)) return [];
    $api = new BotAPI($bot_token);
    $usernames = explode(',', str_replace(' ', '', $usernames_str));
    $resolved_ids = [];
    foreach ($usernames as $username) {
        $username = ltrim($username, '@');
        Logger::info("Resolving username: @$username");
        $response = $api->request('getChat', ['chat_id' => '@' . $username]);
        $data = json_decode($response, true);
        if ($data && isset($data['ok']) && $data['ok']) {
            $chat_id = $data['result']['id'];
            $resolved_ids[] = $chat_id;
            Logger::info("Resolved username @$username to ID $chat_id");
        } else {
            $error = $data['description'] ?? 'Unknown error';
            Logger::error("Failed to resolve username @$username: $error");
        }
        usleep(100000);
    }
    return $resolved_ids;
}

// ==============================
// MERGE CHANNEL IDs FROM NUMERIC AND USERNAMES
// ==============================
$resolved_from_usernames = resolveChannelUsernames(BOT_TOKEN, $channel_usernames_str);
$CHANNEL_IDS = array_merge($CHANNEL_IDS, $resolved_from_usernames);
$CHANNEL_IDS = array_unique($CHANNEL_IDS);

// ==============================
// WEBHOOK HANDLER
// ==============================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    try {
        $bot = new Bot($CHANNEL_IDS, REQUEST_GROUP_ID);
        $bot->handle($update);
    } catch (Exception $e) {
        Logger::error('Fatal error: ' . $e->getMessage());
        http_response_code(500);
    }
    exit;
}

// ==============================
// SET WEBHOOK (PROTECTED)
// ==============================
if (isset($_GET['setwebhook'])) {
    if (!isset($_GET['pass']) || $_GET['pass'] !== WEBHOOK_PASS) {
        die('Unauthorized');
    }
    
    $url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $url = strtok($url, '?');
    
    $api = new BotAPI(BOT_TOKEN);
    $result = $api->request('setWebhook', ['url' => $url]);
    
    echo "<pre>Webhook set result:\n";
    print_r(json_decode($result, true));
    echo "</pre>";
    exit;
}

// ==============================
// STATUS PAGE (PROTECTED)
// ==============================
$allowed_ips = ['127.0.0.1', '::1'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips) && (!isset($_GET['view']) || $_GET['view'] !== 'stats')) {
    http_response_code(404);
    exit;
}

$db = new Database();
$stats = $db->getStats();

echo "<!DOCTYPE html>";
echo "<html><head><title>Bot Status</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}</style>";
echo "</head><body>";
echo "<div style='max-width:600px;margin:0 auto;background:white;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);'>";
echo "<h1>🎬 Entertainment Tadka Bot</h1>";
echo "<p><strong>Status:</strong> <span style='color:green;font-weight:bold;'>✅ RUNNING</span></p>";
echo "<hr>";
echo "<p><strong>Total Movies:</strong> " . ($stats['total_movies'] ?? 0) . "</p>";
echo "<p><strong>Total Users:</strong> " . ($stats['total_users'] ?? 0) . "</p>";
echo "<p><strong>Total Searches:</strong> " . ($stats['searches'] ?? 0) . "</p>";
echo "<p><strong>Channels:</strong> " . count($CHANNEL_IDS) . " configured</p>";
echo "<p><strong>Database:</strong> " . DB_FILE . "</p>";
echo "<p><strong>Last Updated:</strong> " . date('Y-m-d H:i:s', filemtime(DB_FILE)) . "</p>";
echo "<hr>";
echo "<p><a href='?setwebhook&pass=" . htmlspecialchars(WEBHOOK_PASS) . "'>Set Webhook</a> (protected)</p>";
echo "</div>";
echo "</body></html>";
exit;

// ==============================
// CRON TRIGGER ENDPOINT (for cron-job.org)
// ==============================
if (isset($_GET['cron']) && $_GET['cron'] === 'delete') {
    // Password check
    $pass = $_GET['pass'] ?? '';
    if ($pass !== WEBHOOK_PASS) {
        http_response_code(403);
        die("Unauthorized");
    }
    
    // Run the deletion script
    include __DIR__ . '/cron_delete_once.php';
    
    // Optional: log that cron ran
    Logger::info("Cron triggered from cron-job.org");
    
    // Return success response
    echo "Cron job executed at " . date('Y-m-d H:i:s');
    exit;
}
?>
