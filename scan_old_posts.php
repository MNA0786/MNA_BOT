<?php
// scan_old_posts.php - Scan all old posts from configured channels using MadelineProto
// Run this via command line or web (protected)

require_once 'vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = parse_ini_file(__DIR__ . '/.env');
    foreach ($dotenv as $key => $value) {
        putenv("$key=$value");
    }
}

define('BOT_TOKEN', getenv('BOT_TOKEN') ?: die('BOT_TOKEN not set'));
define('DB_FILE', 'bot.sqlite');

$channel_ids_str = getenv('CHANNEL_IDS');
if (!$channel_ids_str) die('CHANNEL_IDS not set');
$CHANNEL_IDS = array_map('intval', explode(',', str_replace(' ', '', $channel_ids_str)));

$channel_usernames_str = getenv('CHANNEL_USERNAMES') ?: '';

// Simple logger for scan script
class ScanLogger {
    public static function info($msg) {
        echo "[" . date('Y-m-d H:i:s') . "] INFO: $msg\n";
    }
    public static function error($msg) {
        echo "[" . date('Y-m-d H:i:s') . "] ERROR: $msg\n";
    }
    public static function warning($msg) {
        echo "[" . date('Y-m-d H:i:s') . "] WARNING: $msg\n";
    }
}

class Database {
    private $pdo;
    public function __construct() {
        $this->pdo = new SQLite3(DB_FILE);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
        // Ensure caption column exists
        $res = $this->pdo->query("PRAGMA table_info(movies)");
        $columns = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $row['name'];
        }
        if (!in_array('caption', $columns)) {
            $this->pdo->exec("ALTER TABLE movies ADD COLUMN caption TEXT DEFAULT ''");
        }
    }
    public function addMovie($movie_name, $channel_id, $message_id_raw, $date = null, $video_path = '', $caption = '') {
        $stmt = $this->pdo->prepare("SELECT id FROM movies WHERE message_id_raw = ? AND channel_id = ?");
        $stmt->bindValue(1, $message_id_raw, SQLITE3_TEXT);
        $stmt->bindValue(2, $channel_id, SQLITE3_INTEGER);
        $res = $stmt->execute();
        if ($res->fetchArray()) return false;

        if (!$date) $date = date('d-m-Y');
        $msg_id = is_numeric($message_id_raw) ? (int)$message_id_raw : null;

        $stmt = $this->pdo->prepare("INSERT INTO movies (movie_name, channel_id, message_id_raw, date, video_path, message_id, caption) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindValue(1, $movie_name, SQLITE3_TEXT);
        $stmt->bindValue(2, $channel_id, SQLITE3_INTEGER);
        $stmt->bindValue(3, $message_id_raw, SQLITE3_TEXT);
        $stmt->bindValue(4, $date, SQLITE3_TEXT);
        $stmt->bindValue(5, $video_path, SQLITE3_TEXT);
        $stmt->bindValue(6, $msg_id, SQLITE3_INTEGER);
        $stmt->bindValue(7, $caption, SQLITE3_TEXT);
        $stmt->execute();
        return true;
    }
    public function clearSearchCache() {
        $this->pdo->exec("DELETE FROM cache WHERE key LIKE 'search_%'");
    }
}

use danog\MadelineProto\API;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Exception as MadelineException;

$api_id = getenv('API_ID');
$api_hash = getenv('API_HASH');

if (!$api_id || !$api_hash) {
    die("ERROR: API_ID and API_HASH must be set in .env file.\n");
}

$settings = (new AppInfo())
    ->setApiId((int)$api_id)
    ->setApiHash($api_hash);

$MadelineProto = new API('session.madeline', $settings);

ScanLogger::info("Starting channel scan...");

try {
    $MadelineProto->start();
    
    // Resolve usernames to IDs
    if (!empty($channel_usernames_str)) {
        $usernames = explode(',', str_replace(' ', '', $channel_usernames_str));
        foreach ($usernames as $username) {
            $username = ltrim($username, '@');
            try {
                $info = $MadelineProto->getInfo('@' . $username);
                $resolved_id = $info['id'];
                if (!in_array($resolved_id, $CHANNEL_IDS)) {
                    $CHANNEL_IDS[] = $resolved_id;
                    ScanLogger::info("Resolved @$username to ID $resolved_id");
                }
            } catch (MadelineException $e) {
                ScanLogger::error("Failed to resolve @$username: " . $e->getMessage());
            }
        }
    }
    
    $CHANNEL_IDS = array_unique($CHANNEL_IDS);
    
    $db = new Database();
    $total_added = 0;

    foreach ($CHANNEL_IDS as $channel_id) {
        ScanLogger::info("Scanning channel ID: $channel_id");
        
        $channel = $MadelineProto->getInfo($channel_id);
        $peer = $channel['peer'];

        $last_id = 0;
        while (true) {
            $messages = $MadelineProto->messages->getHistory([
                'peer' => $peer,
                'offset_id' => $last_id,
                'offset_date' => 0,
                'add_offset' => 0,
                'limit' => 100,
                'max_id' => 0,
                'min_id' => 0,
                'hash' => 0
            ]);
            
            if (empty($messages['messages'])) break;
            
            foreach ($messages['messages'] as $msg) {
                if ($msg['_'] === 'messageEmpty') continue;
                
                $msg_id = $msg['id'];
                $text = $msg['message'] ?? '';
                $caption = $msg['media']['caption'] ?? '';
                $media = $msg['media'] ?? null;
                
                $movie_name = '';
                if (!empty($caption)) {
                    $movie_name = $caption;
                } elseif (!empty($text)) {
                    $movie_name = $text;
                } elseif ($media && isset($msg['media']['document']['attributes'])) {
                    foreach ($msg['media']['document']['attributes'] as $attr) {
                        if ($attr['_'] === 'documentAttributeFilename') {
                            $movie_name = $attr['file_name'];
                            break;
                        }
                    }
                }
                
                if (empty($movie_name)) {
                    $movie_name = 'Uploaded Media - ' . date('d-m-Y', $msg['date']);
                }
                
                // Clean movie name
                $movie_name = preg_replace('/[^\w\s\-\.\(\)\[\]]/u', '', $movie_name);
                $movie_name = trim(preg_replace('/\s+/', ' ', $movie_name));
                
                $date = date('d-m-Y', $msg['date']);
                
                // Store caption (if any)
                $stored_caption = $caption ?: '';
                
                if ($db->addMovie($movie_name, $channel_id, $msg_id, $date, '', $stored_caption)) {
                    $total_added++;
                    ScanLogger::info("  Added: $movie_name (ID: $msg_id)");
                } else {
                    ScanLogger::info("  Skipped (duplicate): $movie_name (ID: $msg_id)");
                }
                
                $last_id = $msg_id;
                usleep(200000); // 0.2 sec delay
            }
            
            if (count($messages['messages']) < 100) break;
        }
    }
    
    $db->clearSearchCache();
    
    ScanLogger::info("Scan completed. Total new movies added: $total_added");
    
} catch (Exception $e) {
    ScanLogger::error("Fatal error: " . $e->getMessage());
    exit(1);
}
?>
