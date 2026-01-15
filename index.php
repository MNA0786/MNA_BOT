<?php
// ==============================
// ULTIMATE MOVIE BOT v3.0
// FEATURES: DUPLICATE PROTECTION, SCHEDULING, CACHE, ADMIN DASHBOARD
// ==============================

// ==============================
// SECURITY HEADERS & BASIC SETUP
// ==============================
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// ==============================
// RENDER.COM SPECIFIC CONFIGURATION
// ==============================
$port = getenv('PORT') ?: '80';
$webhook_url = getenv('RENDER_EXTERNAL_URL') ?: 'https://your-bot-name.onrender.com';

if (!getenv('BOT_TOKEN')) {
    die("❌ BOT_TOKEN environment variable set nahi hai. Render.com dashboard mein set karo.");
}

// ==============================
// ENVIRONMENT VARIABLES CONFIGURATION
// ==============================
define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('CHANNEL_ID', getenv('CHANNEL_ID', '-1003181705395'));
define('BACKUP_CHANNEL_ID', getenv('BACKUP_CHANNEL_ID', '-1002964109368'));
define('BACKUP_CHANNEL_USERNAME', getenv('BACKUP_CHANNEL_USERNAME', '@ETBackup'));
define('ADMIN_ID', (int)getenv('ADMIN_ID', '1080317415'));
define('REQUEST_CHANNEL', getenv('REQUEST_CHANNEL', '@EntertainmentTadka7860'));
define('MAIN_CHANNEL', getenv('MAIN_CHANNEL', '@EntertainmentTadka786'));
define('THEATER_CHANNEL', getenv('THEATER_CHANNEL', '@threater_print_movies'));

// ==============================
// NEW CONSTANTS FOR ENHANCED FEATURES
// ==============================
define('CACHE_FILE', 'cache.json');
define('PREFIX_INDEX_FILE', 'prefix_index.json');
define('SCHEDULE_FILE', 'schedules.json');
define('DUPLICATE_LOG_FILE', 'duplicate_log.json');
define('DELETED_MOVIES_FILE', 'deleted_movies.json');
define('USER_REQUESTS_FILE', 'user_requests.json');
define('RATE_LIMIT_FILE', 'rate_limit.json');
define('BOT_STATUS_FILE', 'bot_status.json');
define('TRAILERS_FILE', 'trailers.csv');
define('AUDIO_TRACKS_FILE', 'audio_tracks.csv');
define('SETTINGS_FILE', 'bot_settings.json');

// Existing file paths
define('CSV_FILE', 'movies.csv');
define('USERS_FILE', 'users.json');
define('STATS_FILE', 'bot_stats.json');
define('REQUEST_FILE', 'movie_requests.json');
define('BACKUP_DIR', 'backups/');
define('LOG_FILE', 'bot_activity.log');

// Enhanced Constants
define('CACHE_EXPIRY', 300);
define('ITEMS_PER_PAGE', 5);
define('MAX_SEARCH_RESULTS', 20);
define('DAILY_REQUEST_LIMIT', 5);
define('AUTO_BACKUP_HOUR', '03');
define('MAX_DUPLICATE_CHECK', 3); // Maximum duplicate check attempts
define('AUTO_CLEAN_INTERVAL', 86400); // 24 hours in seconds
define('SCHEDULE_CHECK_INTERVAL', 60); // 60 seconds
define('TYPING_DELAY_MIN', 100000); // 0.1 seconds
define('TYPING_DELAY_MAX', 500000); // 0.5 seconds
define('RATE_LIMIT_SEARCH', 30); // 30 searches per minute
define('RATE_LIMIT_DOWNLOAD', 10); // 10 downloads per minute
define('RATE_LIMIT_REQUEST', 5); // 5 requests per hour

// ==============================
// ENHANCED PAGINATION CONSTANTS
// ==============================
define('MAX_PAGES_TO_SHOW', 7);
define('PAGINATION_CACHE_TIMEOUT', 60);
define('PREVIEW_ITEMS', 3);
define('BATCH_SIZE', 5);

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
$prefix_index = array();
$waiting_users = array();
$user_sessions = array();
$user_pagination_sessions = array();
$scheduled_posts = array();
$rate_limit_data = array();
$bot_online_status = true;

// ==============================
// BOT STATUS SYSTEM
// ==============================
function update_bot_status($status = true) {
    global $bot_online_status;
    $bot_online_status = $status;
    
    $status_data = [
        'online' => $status,
        'last_checked' => date('Y-m-d H:i:s'),
        'uptime' => get_bot_uptime(),
        'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB',
        'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg()[0] : 'N/A'
    ];
    
    file_put_contents(BOT_STATUS_FILE, json_encode($status_data, JSON_PRETTY_PRINT));
    bot_log("Bot status updated: " . ($status ? "Online" : "Offline"));
}

function get_bot_uptime() {
    if (!file_exists(BOT_STATUS_FILE)) {
        return "0 hours";
    }
    
    $status_data = json_decode(file_get_contents(BOT_STATUS_FILE), true);
    if (isset($status_data['start_time'])) {
        $start = strtotime($status_data['start_time']);
        $diff = time() - $start;
        
        $hours = floor($diff / 3600);
        $minutes = floor(($diff % 3600) / 60);
        
        return "$hours hours $minutes minutes";
    }
    
    return "Unknown";
}

function check_bot_health() {
    $health = [
        'status' => 'healthy',
        'checks' => []
    ];
    
    // Check essential files
    $essential_files = [CSV_FILE, USERS_FILE, STATS_FILE, CACHE_FILE];
    foreach ($essential_files as $file) {
        $health['checks'][$file] = file_exists($file) ? 'exists' : 'missing';
        if (!file_exists($file)) $health['status'] = 'degraded';
    }
    
    // Check file permissions
    foreach ($essential_files as $file) {
        if (file_exists($file)) {
            $health['checks'][$file . '_writable'] = is_writable($file) ? 'writable' : 'read-only';
            if (!is_writable($file)) $health['status'] = 'degraded';
        }
    }
    
    // Check memory usage
    $memory_usage = memory_get_usage(true) / 1024 / 1024;
    $health['checks']['memory'] = $memory_usage < 50 ? 'normal' : 'high';
    if ($memory_usage > 100) $health['status'] = 'critical';
    
    // Check CSV integrity
    if (file_exists(CSV_FILE)) {
        $csv_size = filesize(CSV_FILE);
        $health['checks']['csv_size'] = $csv_size . ' bytes';
        if ($csv_size > 100 * 1024 * 1024) { // 100MB
            $health['status'] = 'warning';
        }
    }
    
    return $health;
}

// ==============================
// INITIALIZE ALL SYSTEMS
// ==============================
function initialize_all_systems() {
    // Initialize core files
    initialize_files();
    
    // Initialize cache system
    initialize_cache_system();
    
    // Initialize prefix index
    initialize_prefix_index();
    
    // Initialize schedule system
    initialize_schedule_system();
    
    // Initialize rate limiting
    initialize_rate_limiting();
    
    // Initialize bot status
    if (!file_exists(BOT_STATUS_FILE)) {
        $status_data = [
            'start_time' => date('Y-m-d H:i:s'),
            'online' => true,
            'restart_count' => 0,
            'last_restart' => null
        ];
        file_put_contents(BOT_STATUS_FILE, json_encode($status_data, JSON_PRETTY_PRINT));
    }
    
    // Initialize settings
    if (!file_exists(SETTINGS_FILE)) {
        $settings = [
            'auto_clean' => true,
            'auto_backup' => true,
            'auto_notify' => true,
            'delete_bot_replies' => true,
            'typing_indicator' => true,
            'spell_correction' => true,
            'duplicate_check' => true,
            'rate_limiting' => true,
            'maintenance_mode' => false,
            'timezone' => 'Asia/Kolkata',
            'language' => 'hindi',
            'max_upload_size' => '2000',
            'allowed_formats' => ['mp4', 'mkv', 'avi', 'mov'],
            'admin_ids' => [ADMIN_ID]
        ];
        file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT));
    }
    
    bot_log("All systems initialized successfully");
}

// ==============================
// CACHE SYSTEM (ULTRA FAST)
// ==============================
function initialize_cache_system() {
    if (!file_exists(CACHE_FILE)) {
        $initial_cache = [
            'movies' => [],
            'timestamp' => 0,
            'hits' => 0,
            'misses' => 0,
            'size' => 0
        ];
        file_put_contents(CACHE_FILE, json_encode($initial_cache, JSON_PRETTY_PRINT));
    }
}

function get_cached_movies() {
    global $movie_cache;
    
    // Check memory cache first (fastest)
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < CACHE_EXPIRY) {
        return $movie_cache['data'];
    }
    
    // Check file cache
    $file_cache = json_decode(file_get_contents(CACHE_FILE), true);
    if (!empty($file_cache['movies']) && (time() - $file_cache['timestamp']) < CACHE_EXPIRY) {
        $movie_cache = [
            'data' => $file_cache['movies'],
            'timestamp' => $file_cache['timestamp']
        ];
        update_cache_stats('hits');
        return $file_cache['movies'];
    }
    
    // Cache miss - load from CSV
    $movies = load_and_clean_csv();
    
    // Update both caches
    $movie_cache = [
        'data' => $movies,
        'timestamp' => time()
    ];
    
    $file_cache = [
        'movies' => $movies,
        'timestamp' => time(),
        'hits' => $file_cache['hits'] ?? 0,
        'misses' => ($file_cache['misses'] ?? 0) + 1,
        'size' => count($movies)
    ];
    
    file_put_contents(CACHE_FILE, json_encode($file_cache, JSON_PRETTY_PRINT));
    update_cache_stats('misses');
    
    bot_log("Cache refreshed - " . count($movies) . " movies");
    return $movies;
}

function update_cache_stats($type) {
    if (!file_exists(CACHE_FILE)) return;
    
    $cache = json_decode(file_get_contents(CACHE_FILE), true);
    if ($type == 'hits') {
        $cache['hits'] = ($cache['hits'] ?? 0) + 1;
    } elseif ($type == 'misses') {
        $cache['misses'] = ($cache['misses'] ?? 0) + 1;
    }
    
    file_put_contents(CACHE_FILE, json_encode($cache, JSON_PRETTY_PRINT));
}

function clear_cache() {
    global $movie_cache, $prefix_index;
    $movie_cache = [];
    $prefix_index = [];
    
    if (file_exists(CACHE_FILE)) {
        unlink(CACHE_FILE);
    }
    
    if (file_exists(PREFIX_INDEX_FILE)) {
        unlink(PREFIX_INDEX_FILE);
    }
    
    initialize_cache_system();
    initialize_prefix_index();
    
    bot_log("Cache cleared completely");
}

// ==============================
// PREFIX INDEX (SMART SEARCH)
// ==============================
function initialize_prefix_index() {
    global $prefix_index;
    
    if (!file_exists(PREFIX_INDEX_FILE)) {
        $prefix_index = [];
        file_put_contents(PREFIX_INDEX_FILE, json_encode($prefix_index, JSON_PRETTY_PRINT));
        return;
    }
    
    $prefix_index = json_decode(file_get_contents(PREFIX_INDEX_FILE), true);
    
    // If index is empty or old, rebuild it
    if (empty($prefix_index) || (time() - ($prefix_index['timestamp'] ?? 0)) > 3600) {
        rebuild_prefix_index();
    }
}

function rebuild_prefix_index() {
    global $prefix_index;
    $movies = get_cached_movies();
    $index = ['timestamp' => time()];
    
    foreach ($movies as $movie) {
        $name = strtolower(trim($movie['movie_name']));
        $words = preg_split('/\s+/', $name);
        
        foreach ($words as $word) {
            if (strlen($word) < 2) continue;
            
            // Add all prefixes for fuzzy matching
            for ($i = 1; $i <= strlen($word); $i++) {
                $prefix = substr($word, 0, $i);
                if (!isset($index[$prefix])) {
                    $index[$prefix] = [];
                }
                if (!in_array($movie['movie_name'], $index[$prefix])) {
                    $index[$prefix][] = $movie['movie_name'];
                }
            }
        }
    }
    
    $prefix_index = $index;
    file_put_contents(PREFIX_INDEX_FILE, json_encode($index, JSON_PRETTY_PRINT));
    bot_log("Prefix index rebuilt with " . (count($index) - 1) . " prefixes");
}

function smart_prefix_search($query) {
    global $prefix_index;
    $query = strtolower(trim($query));
    $results = [];
    
    if (empty($prefix_index) || !isset($prefix_index['timestamp'])) {
        rebuild_prefix_index();
    }
    
    $query_words = preg_split('/\s+/', $query);
    
    foreach ($query_words as $word) {
        if (strlen($word) < 2) continue;
        
        // Find matching prefixes
        $prefix = substr($word, 0, 2);
        if (isset($prefix_index[$prefix])) {
            foreach ($prefix_index[$prefix] as $movie_name) {
                if (stripos($movie_name, $word) !== false) {
                    if (!isset($results[$movie_name])) {
                        $results[$movie_name] = 0;
                    }
                    $results[$movie_name] += 10;
                }
            }
        }
        
        // Check for exact prefix matches
        foreach ($prefix_index as $prefix => $movies) {
            if ($prefix == 'timestamp') continue;
            
            if (strpos($prefix, $word) === 0 || strpos($word, $prefix) === 0) {
                foreach ($movies as $movie_name) {
                    if (!isset($results[$movie_name])) {
                        $results[$movie_name] = 0;
                    }
                    $results[$movie_name] += 5;
                }
            }
        }
    }
    
    arsort($results);
    return array_slice(array_keys($results), 0, MAX_SEARCH_RESULTS);
}

// ==============================
// DUPLICATE PROTECTION SYSTEM
// ==============================
function check_duplicate_movie($movie_name, $message_id = null, $channel_id = null) {
    $movies = get_cached_movies();
    $duplicates = [];
    
    $clean_name = normalize_movie_name($movie_name);
    
    foreach ($movies as $movie) {
        $existing_name = normalize_movie_name($movie['movie_name']);
        
        // Check by normalized name
        if (similar_text($clean_name, $existing_name, $similarity) && $similarity > 90) {
            $duplicates[] = [
                'movie' => $movie['movie_name'],
                'message_id' => $movie['message_id_raw'],
                'date' => $movie['date'],
                'similarity' => $similarity
            ];
        }
        
        // Check by exact message_id + channel combo
        if ($message_id && $channel_id && 
            $movie['message_id_raw'] == $message_id) {
            return [
                'type' => 'exact_duplicate',
                'existing' => $movie,
                'match' => 'message_id + channel'
            ];
        }
    }
    
    if (!empty($duplicates)) {
        usort($duplicates, function($a, $b) {
            return $b['similarity'] - $a['similarity'];
        });
        
        return [
            'type' => 'similar_duplicate',
            'duplicates' => $duplicates,
            'count' => count($duplicates)
        ];
    }
    
    return false;
}

function normalize_movie_name($name) {
    // Remove year, quality, language info for better comparison
    $name = strtolower(trim($name));
    $name = preg_replace('/\([0-9]{4}\)/', '', $name); // Remove (2023)
    $name = preg_replace('/[0-9]{4}/', '', $name); // Remove 2023
    $name = preg_replace('/\b(1080p|720p|480p|hd|fullhd|webrip|bluray|dvdrip|x264|x265|hevc)\b/i', '', $name);
    $name = preg_replace('/\b(hindi|english|tamil|telugu|malayalam|punjabi|gujarati|marathi)\b/i', '', $name);
    $name = preg_replace('/[^a-z0-9\s]/', ' ', $name); // Remove special chars
    $name = preg_replace('/\s+/', ' ', $name); // Multiple spaces to single
    return trim($name);
}

function log_duplicate_attempt($movie_name, $duplicate_info, $action = 'blocked') {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'movie_name' => $movie_name,
        'duplicate_info' => $duplicate_info,
        'action' => $action
    ];
    
    $log_data = [];
    if (file_exists(DUPLICATE_LOG_FILE)) {
        $log_data = json_decode(file_get_contents(DUPLICATE_LOG_FILE), true);
    }
    
    $log_data[] = $log_entry;
    
    // Keep only last 100 entries
    if (count($log_data) > 100) {
        $log_data = array_slice($log_data, -100);
    }
    
    file_put_contents(DUPLICATE_LOG_FILE, json_encode($log_data, JSON_PRETTY_PRINT));
    bot_log("Duplicate $action: $movie_name - " . json_encode($duplicate_info));
}

function group_movie_parts($movie_name) {
    $movies = get_cached_movies();
    $base_name = normalize_movie_name($movie_name);
    $parts = [];
    
    foreach ($movies as $movie) {
        $current_base = normalize_movie_name($movie['movie_name']);
        
        if (similar_text($base_name, $current_base, $similarity) && $similarity > 85) {
            // Check for part indicators
            if (preg_match('/\b(part\s*[0-9]|pt\s*[0-9]|cd\s*[0-9]|disc\s*[0-9])\b/i', $movie['movie_name'])) {
                $parts[] = [
                    'movie' => $movie['movie_name'],
                    'message_id' => $movie['message_id_raw'],
                    'quality' => $movie['quality'],
                    'size' => $movie['size'],
                    'part_number' => extract_part_number($movie['movie_name'])
                ];
            }
        }
    }
    
    // Sort by part number
    usort($parts, function($a, $b) {
        return ($a['part_number'] ?? 999) <=> ($b['part_number'] ?? 999);
    });
    
    return $parts;
}

function extract_part_number($movie_name) {
    if (preg_match('/\bpart\s*([0-9]+)\b/i', $movie_name, $matches)) {
        return intval($matches[1]);
    }
    if (preg_match('/\bpt\s*([0-9]+)\b/i', $movie_name, $matches)) {
        return intval($matches[1]);
    }
    if (preg_match('/\bcd\s*([0-9]+)\b/i', $movie_name, $matches)) {
        return intval($matches[1]);
    }
    if (preg_match('/\bdisc\s*([0-9]+)\b/i', $movie_name, $matches)) {
        return intval($matches[1]);
    }
    return 1;
}

// ==============================
// CSV AUTO-CLEANER & AUTO-REPAIR
// ==============================
function csv_auto_cleaner() {
    if (!file_exists(CSV_FILE)) return;
    
    $last_clean = file_exists(SETTINGS_FILE) ? 
        (json_decode(file_get_contents(SETTINGS_FILE), true)['last_clean'] ?? 0) : 0;
    
    // Clean every 24 hours
    if (time() - $last_clean > AUTO_CLEAN_INTERVAL) {
        bot_log("Starting CSV auto-cleaner");
        
        $cleaned_count = 0;
        $fixed_count = 0;
        $removed_count = 0;
        
        $movies = load_and_clean_csv();
        $unique_movies = [];
        $seen_combos = [];
        
        foreach ($movies as $movie) {
            $key = $movie['movie_name'] . '|' . $movie['message_id_raw'] . '|' . $movie['quality'] . '|' . $movie['language'];
            
            // Remove duplicates
            if (isset($seen_combos[$key])) {
                $removed_count++;
                continue;
            }
            
            // Fix malformed entries
            $movie = fix_movie_entry($movie);
            if ($movie['fixed']) {
                $fixed_count++;
            }
            
            $unique_movies[] = $movie['data'];
            $seen_combos[$key] = true;
        }
        
        // Rewrite CSV with cleaned data
        $handle = fopen(CSV_FILE, 'w');
        fputcsv($handle, ['movie_name','message_id','date','video_path','quality','size','language']);
        
        foreach ($unique_movies as $movie) {
            fputcsv($handle, [
                $movie['movie_name'],
                $movie['message_id_raw'],
                $movie['date'],
                $movie['video_path'],
                $movie['quality'],
                $movie['size'],
                $movie['language']
            ]);
        }
        fclose($handle);
        
        // Update settings
        $settings = json_decode(file_get_contents(SETTINGS_FILE), true);
        $settings['last_clean'] = time();
        $settings['clean_stats'] = [
            'last_cleaned' => date('Y-m-d H:i:s'),
            'removed_duplicates' => $removed_count,
            'fixed_entries' => $fixed_count,
            'total_after_clean' => count($unique_movies)
        ];
        file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT));
        
        // Clear cache to force refresh
        clear_cache();
        
        bot_log("CSV auto-clean completed: Removed $removed_count duplicates, Fixed $fixed_count entries");
        
        return [
            'removed_duplicates' => $removed_count,
            'fixed_entries' => $fixed_count,
            'total_movies' => count($unique_movies)
        ];
    }
    
    return false;
}

function fix_movie_entry($movie) {
    $fixed = false;
    
    // Fix missing quality
    if (empty($movie['quality']) || $movie['quality'] == 'Unknown') {
        if (stripos($movie['movie_name'], '1080') !== false) {
            $movie['quality'] = '1080p';
            $fixed = true;
        } elseif (stripos($movie['movie_name'], '720') !== false) {
            $movie['quality'] = '720p';
            $fixed = true;
        } elseif (stripos($movie['movie_name'], '480') !== false) {
            $movie['quality'] = '480p';
            $fixed = true;
        } else {
            $movie['quality'] = 'HD';
        }
    }
    
    // Fix missing language
    if (empty($movie['language']) || $movie['language'] == 'Unknown') {
        if (stripos($movie['movie_name'], 'hindi') !== false) {
            $movie['language'] = 'Hindi';
            $fixed = true;
        } elseif (stripos($movie['movie_name'], 'english') !== false) {
            $movie['language'] = 'English';
            $fixed = true;
        } else {
            $movie['language'] = 'Hindi';
        }
    }
    
    // Fix date format
    if (!empty($movie['date']) && !preg_match('/^\d{2}-\d{2}-\d{4}$/', $movie['date'])) {
        try {
            $date = DateTime::createFromFormat('Y-m-d', $movie['date']);
            if ($date) {
                $movie['date'] = $date->format('d-m-Y');
                $fixed = true;
            } else {
                $movie['date'] = date('d-m-Y');
                $fixed = true;
            }
        } catch (Exception $e) {
            $movie['date'] = date('d-m-Y');
            $fixed = true;
        }
    }
    
    // Fix message_id format
    if (!empty($movie['message_id_raw']) && !is_numeric($movie['message_id_raw'])) {
        $numeric_id = preg_replace('/[^0-9]/', '', $movie['message_id_raw']);
        if (is_numeric($numeric_id)) {
            $movie['message_id_raw'] = $numeric_id;
            $movie['message_id'] = intval($numeric_id);
            $fixed = true;
        }
    }
    
    return ['data' => $movie, 'fixed' => $fixed];
}

// ==============================
// BOT BEHAVIOR ENHANCEMENTS
// ==============================
function send_typing_indicator($chat_id) {
    global $bot_online_status;
    
    if (!$bot_online_status) return;
    
    $settings = json_decode(file_get_contents(SETTINGS_FILE), true);
    if (!$settings['typing_indicator']) return;
    
    apiRequest('sendChatAction', [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ]);
    
    // Random delay for human-like behavior
    usleep(rand(TYPING_DELAY_MIN, TYPING_DELAY_MAX));
}

function auto_delete_bot_replies($chat_id, $message_id, $delay_seconds = 30) {
    $settings = json_decode(file_get_contents(SETTINGS_FILE), true);
    if (!$settings['delete_bot_replies']) return;
    
    // Schedule deletion
    $schedules = file_exists(SCHEDULE_FILE) ? json_decode(file_get_contents(SCHEDULE_FILE), true) : [];
    $schedule_id = 'delete_' . uniqid();
    
    $schedules[] = [
        'id' => $schedule_id,
        'type' => 'delete_message',
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'execute_at' => time() + $delay_seconds,
        'status' => 'pending'
    ];
    
    file_put_contents(SCHEDULE_FILE, json_encode($schedules, JSON_PRETTY_PRINT));
}

function smart_spelling_correction($query) {
    $settings = json_decode(file_get_contents(SETTINGS_FILE), true);
    if (!$settings['spell_correction']) return $query;
    
    $common_mistakes = [
        'avangers' => 'avengers',
        'avanger' => 'avenger',
        'kgf2' => 'kgf 2',
        'kgf1' => 'kgf',
        'bahubaly' => 'bahubali',
        'bahuballi' => 'bahubali',
        'pushpa2' => 'pushpa 2',
        'animal2' => 'animal park',
        'dhoom2' => 'dhoom 2',
        'dhoom3' => 'dhoom 3',
        'dhoom4' => 'dhoom 4',
        'krish3' => 'krish 3',
        'krishh' => 'krish',
        'tiger3' => 'tiger 3',
        'war2' => 'war 2',
    ];
    
    $corrected = strtolower($query);
    
    foreach ($common_mistakes as $wrong => $correct) {
        if (strpos($corrected, $wrong) !== false) {
            $corrected = str_replace($wrong, $correct, $corrected);
            bot_log("Spelling corrected: $wrong -> $correct in query: $query");
        }
    }
    
    return $corrected !== strtolower($query) ? $corrected : $query;
}

// ==============================
// SCHEDULING SYSTEM (BIG ONE 🔥)
// ==============================
function initialize_schedule_system() {
    if (!file_exists(SCHEDULE_FILE)) {
        $initial_schedules = [
            'schedules' => [],
            'last_checked' => time(),
            'total_executed' => 0,
            'total_failed' => 0
        ];
        file_put_contents(SCHEDULE_FILE, json_encode($initial_schedules, JSON_PRETTY_PRINT));
    }
}

function add_scheduled_post($data) {
    $schedules = json_decode(file_get_contents(SCHEDULE_FILE), true);
    
    $schedule_id = 'sch_' . uniqid();
    
    $schedule = [
        'id' => $schedule_id,
        'type' => 'post_movie',
        'data' => $data,
        'execute_at' => strtotime($data['schedule_time']),
        'status' => 'pending',
        'created_at' => time(),
        'created_by' => $data['admin_id'] ?? ADMIN_ID,
        'timezone' => $data['timezone'] ?? 'Asia/Kolkata'
    ];
    
    $schedules['schedules'][] = $schedule;
    file_put_contents(SCHEDULE_FILE, json_encode($schedules, JSON_PRETTY_PRINT));
    
    bot_log("Scheduled post added: {$data['movie_name']} at {$data['schedule_time']}");
    
    return $schedule_id;
}

function check_scheduled_posts() {
    if (!file_exists(SCHEDULE_FILE)) return;
    
    $schedules = json_decode(file_get_contents(SCHEDULE_FILE), true);
    $current_time = time();
    $executed = 0;
    
    foreach ($schedules['schedules'] as &$schedule) {
        if ($schedule['status'] == 'pending' && $schedule['execute_at'] <= $current_time) {
            try {
                execute_scheduled_post($schedule);
                $schedule['status'] = 'executed';
                $schedule['executed_at'] = $current_time;
                $executed++;
            } catch (Exception $e) {
                $schedule['status'] = 'failed';
                $schedule['error'] = $e->getMessage();
                $schedules['total_failed']++;
            }
        }
    }
    
    if ($executed > 0) {
        $schedules['last_checked'] = $current_time;
        $schedules['total_executed'] += $executed;
        file_put_contents(SCHEDULE_FILE, json_encode($schedules, JSON_PRETTY_PRINT));
        bot_log("Executed $executed scheduled posts");
    }
}

function execute_scheduled_post($schedule) {
    switch ($schedule['type']) {
        case 'post_movie':
            $data = $schedule['data'];
            
            // Post to channel
            if (isset($data['media'])) {
                // Handle media post
                $result = post_media_to_channel(
                    $data['channel_id'],
                    $data['media'],
                    $data['caption'] ?? '',
                    $data['buttons'] ?? []
                );
            } else {
                // Handle text post
                $result = sendMessage(
                    $data['channel_id'],
                    $data['text'] ?? $data['movie_name'],
                    $data['buttons'] ?? null,
                    'HTML'
                );
            }
            
            // Add to CSV if movie
            if (isset($data['movie_name'])) {
                append_movie(
                    $data['movie_name'],
                    $result['result']['message_id'] ?? '',
                    date('d-m-Y'),
                    $data['video_path'] ?? '',
                    $data['quality'] ?? 'HD',
                    $data['size'] ?? 'Unknown',
                    $data['language'] ?? 'Hindi'
                );
            }
            
            // Notify admin
            sendMessage(
                $schedule['created_by'],
                "✅ Scheduled post executed!\n\n" .
                "🎬 Movie: {$data['movie_name']}\n" .
                "📅 Scheduled: " . date('Y-m-d H:i:s', $schedule['execute_at']) . "\n" .
                "⏰ Executed: " . date('Y-m-d H:i:s') . "\n" .
                "🆔 Schedule ID: {$schedule['id']}"
            );
            break;
            
        case 'delete_message':
            deleteMessage($schedule['chat_id'], $schedule['message_id']);
            break;
    }
}

function list_scheduled_posts($chat_id, $filter = 'all') {
    if (!file_exists(SCHEDULE_FILE)) {
        sendMessage($chat_id, "📭 No scheduled posts found.");
        return;
    }
    
    $schedules = json_decode(file_get_contents(SCHEDULE_FILE), true);
    $filtered = [];
    
    foreach ($schedules['schedules'] as $schedule) {
        if ($filter == 'all' || $schedule['status'] == $filter) {
            $filtered[] = $schedule;
        }
    }
    
    if (empty($filtered)) {
        sendMessage($chat_id, "📭 No $filter scheduled posts found.");
        return;
    }
    
    $message = "📅 <b>Scheduled Posts ($filter)</b>\n\n";
    $message .= "📊 Total: " . count($filtered) . "\n\n";
    
    foreach (array_slice($filtered, 0, 10) as $schedule) {
        $time_left = $schedule['execute_at'] - time();
        $hours = floor($time_left / 3600);
        $minutes = floor(($time_left % 3600) / 60);
        
        $message .= "🆔 <code>{$schedule['id']}</code>\n";
        $message .= "🎬 " . ($schedule['data']['movie_name'] ?? 'Text Post') . "\n";
        $message .= "⏰ " . date('Y-m-d H:i:s', $schedule['execute_at']) . "\n";
        $message .= "⏳ In: $hours hours $minutes minutes\n";
        $message .= "📊 Status: " . ucfirst($schedule['status']) . "\n\n";
    }
    
    if (count($filtered) > 10) {
        $message .= "... and " . (count($filtered) - 10) . " more\n\n";
    }
    
    $message .= "📋 Commands:\n";
    $message .= "<code>/cancelschedule ID</code> - Cancel schedule\n";
    $message .= "<code>/listschedule pending</code> - Pending only\n";
    $message .= "<code>/listschedule executed</code> - Executed only";
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function cancel_scheduled_post($chat_id, $schedule_id) {
    if (!file_exists(SCHEDULE_FILE)) {
        sendMessage($chat_id, "❌ Schedule file not found.");
        return;
    }
    
    $schedules = json_decode(file_get_contents(SCHEDULE_FILE), true);
    $found = false;
    
    foreach ($schedules['schedules'] as $key => $schedule) {
        if ($schedule['id'] == $schedule_id) {
            if ($schedule['status'] == 'pending') {
                $schedules['schedules'][$key]['status'] = 'cancelled';
                $schedules['schedules'][$key]['cancelled_at'] = time();
                $schedules['schedules'][$key]['cancelled_by'] = $chat_id;
                $found = true;
                break;
            } else {
                sendMessage($chat_id, "❌ Cannot cancel already {$schedule['status']} schedule.");
                return;
            }
        }
    }
    
    if ($found) {
        file_put_contents(SCHEDULE_FILE, json_encode($schedules, JSON_PRETTY_PRINT));
        sendMessage($chat_id, "✅ Schedule cancelled: $schedule_id");
        bot_log("Schedule cancelled: $schedule_id by $chat_id");
    } else {
        sendMessage($chat_id, "❌ Schedule not found: $schedule_id");
    }
}

// ==============================
// RATE LIMITING SYSTEM
// ==============================
function initialize_rate_limiting() {
    if (!file_exists(RATE_LIMIT_FILE)) {
        $initial_data = [
            'users' => [],
            'global' => [
                'search' => ['count' => 0, 'reset' => time() + 60],
                'download' => ['count' => 0, 'reset' => time() + 60],
                'request' => ['count' => 0, 'reset' => time() + 3600]
            ],
            'settings' => [
                'search_limit' => RATE_LIMIT_SEARCH,
                'download_limit' => RATE_LIMIT_DOWNLOAD,
                'request_limit' => RATE_LIMIT_REQUEST
            ]
        ];
        file_put_contents(RATE_LIMIT_FILE, json_encode($initial_data, JSON_PRETTY_PRINT));
    }
}

function check_rate_limit($user_id, $action) {
    $settings = json_decode(file_get_contents(SETTINGS_FILE), true);
    if (!$settings['rate_limiting']) return true;
    
    $rate_data = json_decode(file_get_contents(RATE_LIMIT_FILE), true);
    $current_time = time();
    
    // Check global rate limit
    if (isset($rate_data['global'][$action])) {
        if ($current_time > $rate_data['global'][$action]['reset']) {
            $rate_data['global'][$action] = ['count' => 0, 'reset' => $current_time + get_reset_time($action)];
        }
        
        $limit = $rate_data['settings'][$action . '_limit'] ?? get_default_limit($action);
        if ($rate_data['global'][$action]['count'] >= $limit) {
            return false;
        }
    }
    
    // Check user-specific rate limit
    if (!isset($rate_data['users'][$user_id])) {
        $rate_data['users'][$user_id] = [];
    }
    
    if (!isset($rate_data['users'][$user_id][$action])) {
        $rate_data['users'][$user_id][$action] = ['count' => 0, 'reset' => $current_time + get_reset_time($action)];
    }
    
    if ($current_time > $rate_data['users'][$user_id][$action]['reset']) {
        $rate_data['users'][$user_id][$action] = ['count' => 0, 'reset' => $current_time + get_reset_time($action)];
    }
    
    $user_limit = get_user_limit($user_id, $action);
    if ($rate_data['users'][$user_id][$action]['count'] >= $user_limit) {
        return false;
    }
    
    // Increment counters
    $rate_data['global'][$action]['count']++;
    $rate_data['users'][$user_id][$action]['count']++;
    
    file_put_contents(RATE_LIMIT_FILE, json_encode($rate_data, JSON_PRETTY_PRINT));
    return true;
}

function get_reset_time($action) {
    switch ($action) {
        case 'search': return 60; // 1 minute
        case 'download': return 60; // 1 minute
        case 'request': return 3600; // 1 hour
        default: return 60;
    }
}

function get_default_limit($action) {
    switch ($action) {
        case 'search': return RATE_LIMIT_SEARCH;
        case 'download': return RATE_LIMIT_DOWNLOAD;
        case 'request': return RATE_LIMIT_REQUEST;
        default: return 10;
    }
}

function get_user_limit($user_id, $action) {
    // Premium users can have higher limits
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user = $users_data['users'][$user_id] ?? [];
    
    if (($user['points'] ?? 0) > 500) {
        return get_default_limit($action) * 2; // Double limit for premium users
    }
    
    return get_default_limit($action);
}

function get_rate_limit_status($user_id) {
    if (!file_exists(RATE_LIMIT_FILE)) return [];
    
    $rate_data = json_decode(file_get_contents(RATE_LIMIT_FILE), true);
    $status = [];
    
    $actions = ['search', 'download', 'request'];
    foreach ($actions as $action) {
        if (isset($rate_data['users'][$user_id][$action])) {
            $user_data = $rate_data['users'][$user_id][$action];
            $limit = get_user_limit($user_id, $action);
            $remaining = max(0, $limit - $user_data['count']);
            $reset_in = max(0, $user_data['reset'] - time());
            
            $status[$action] = [
                'used' => $user_data['count'],
                'limit' => $limit,
                'remaining' => $remaining,
                'reset_in' => $reset_in,
                'reset_at' => date('H:i:s', $user_data['reset'])
            ];
        }
    }
    
    return $status;
}

// ==============================
// INLINE SEARCH SUGGESTIONS
// ==============================
function handle_inline_query($inline_query) {
    $query_id = $inline_query['id'];
    $query = $inline_query['query'];
    $user_id = $inline_query['from']['id'];
    
    if (empty($query)) {
        // Show trending movies when query is empty
        $results = get_trending_movies_for_inline(10);
    } else {
        // Search movies
        $results = search_movies_for_inline($query);
    }
    
    if (empty($results)) {
        $articles = [[
            'type' => 'article',
            'id' => 'not_found',
            'title' => '❌ Movie Not Found',
            'input_message_content' => [
                'message_text' => "😔 Movie not found: $query\n\nUse /request to request this movie.",
                'parse_mode' => 'HTML'
            ],
            'description' => 'Click to request this movie',
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => '📝 Request Movie', 'callback_data' => 'request_' . base64_encode($query)]
                ]]
            ]
        ]];
    } else {
        $articles = [];
        $counter = 1;
        
        foreach ($results as $movie) {
            $articles[] = [
                'type' => 'article',
                'id' => $movie['id'] ?? uniqid(),
                'title' => ($counter++) . '. ' . $movie['title'],
                'input_message_content' => [
                    'message_text' => "🎬 <b>{$movie['title']}</b>\n\n" .
                                    "📊 Quality: {$movie['quality']}\n" .
                                    "🗣️ Language: {$movie['language']}\n" .
                                    "📅 Date: {$movie['date']}\n\n" .
                                    "🔗 Join channel to download: " . MAIN_CHANNEL,
                    'parse_mode' => 'HTML'
                ],
                'description' => "{$movie['quality']} | {$movie['language']} | {$movie['date']}",
                'reply_markup' => [
                    'inline_keyboard' => [[
                        ['text' => '📥 Get Movie Info', 'callback_data' => 'inline_get_' . $movie['id']]
                    ]]
                ]
            ];
            
            if (count($articles) >= 50) break; // Telegram limit
        }
    }
    
    apiRequest('answerInlineQuery', [
        'inline_query_id' => $query_id,
        'results' => json_encode($articles),
        'cache_time' => 300,
        'is_personal' => true
    ]);
    
    bot_log("Inline query handled: $query by $user_id - " . count($articles) . " results");
}

function search_movies_for_inline($query, $limit = 20) {
    $movies = get_cached_movies();
    $query_lower = strtolower(trim($query));
    $results = [];
    
    foreach ($movies as $movie) {
        $score = 0;
        $movie_lower = strtolower($movie['movie_name']);
        
        // Exact match
        if ($movie_lower == $query_lower) {
            $score = 100;
        }
        // Partial match
        elseif (strpos($movie_lower, $query_lower) !== false) {
            $score = 80;
        }
        // Fuzzy match using prefix index
        else {
            $prefix_results = smart_prefix_search($query);
            if (in_array($movie['movie_name'], $prefix_results)) {
                $score = 60;
            }
        }
        
        if ($score > 0) {
            $results[] = [
                'id' => md5($movie['movie_name'] . $movie['message_id_raw']),
                'title' => $movie['movie_name'],
                'quality' => $movie['quality'],
                'language' => $movie['language'],
                'date' => $movie['date'],
                'message_id' => $movie['message_id_raw'],
                'score' => $score
            ];
        }
    }
    
    // Sort by score
    usort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return array_slice($results, 0, $limit);
}

function get_trending_movies_for_inline($limit = 10) {
    $movies = get_cached_movies();
    $trending = array_slice($movies, -$limit); // Latest movies
    $trending = array_reverse($trending);
    
    $results = [];
    foreach ($trending as $movie) {
        $results[] = [
            'id' => md5($movie['movie_name'] . $movie['message_id_raw']),
            'title' => $movie['movie_name'],
            'quality' => $movie['quality'],
            'language' => $movie['language'],
            'date' => $movie['date'],
            'message_id' => $movie['message_id_raw'],
            'score' => 100
        ];
    }
    
    return $results;
}

// ==============================
// TRAILER & AUDIO COMMANDS
// ==============================
function initialize_trailers_system() {
    if (!file_exists(TRAILERS_FILE)) {
        $header = "movie_name,trailer_url,trailer_id,duration,quality,views,upload_date\n";
        file_put_contents(TRAILERS_FILE, $header);
    }
    
    if (!file_exists(AUDIO_TRACKS_FILE)) {
        $header = "movie_name,audio_url,audio_id,format,bitrate,size,language\n";
        file_put_contents(AUDIO_TRACKS_FILE, $header);
    }
}

function get_movie_trailer($movie_name) {
    if (!file_exists(TRAILERS_FILE)) return null;
    
    $handle = fopen(TRAILERS_FILE, 'r');
    fgetcsv($handle); // Skip header
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) >= 2 && stripos($row[0], $movie_name) !== false) {
            fclose($handle);
            return [
                'movie_name' => $row[0],
                'trailer_url' => $row[1],
                'trailer_id' => $row[2] ?? '',
                'duration' => $row[3] ?? 'Unknown',
                'quality' => $row[4] ?? 'HD',
                'views' => $row[5] ?? '0',
                'upload_date' => $row[6] ?? date('Y-m-d')
            ];
        }
    }
    
    fclose($handle);
    return null;
}

function get_movie_audio($movie_name) {
    if (!file_exists(AUDIO_TRACKS_FILE)) return null;
    
    $handle = fopen(AUDIO_TRACKS_FILE, 'r');
    fgetcsv($handle); // Skip header
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) >= 2 && stripos($row[0], $movie_name) !== false) {
            fclose($handle);
            return [
                'movie_name' => $row[0],
                'audio_url' => $row[1],
                'audio_id' => $row[2] ?? '',
                'format' => $row[3] ?? 'mp3',
                'bitrate' => $row[4] ?? '320kbps',
                'size' => $row[5] ?? 'Unknown',
                'language' => $row[6] ?? 'Hindi'
            ];
        }
    }
    
    fclose($handle);
    return null;
}

function add_trailer($movie_name, $trailer_url, $admin_id) {
    if (!file_exists(TRAILERS_FILE)) initialize_trailers_system();
    
    $trailer_id = 'trl_' . uniqid();
    $entry = [
        $movie_name,
        $trailer_url,
        $trailer_id,
        'Unknown',
        'HD',
        '0',
        date('Y-m-d')
    ];
    
    $handle = fopen(TRAILERS_FILE, 'a');
    fputcsv($handle, $entry);
    fclose($handle);
    
    bot_log("Trailer added: $movie_name by admin $admin_id");
    return $trailer_id;
}

function add_audio_track($movie_name, $audio_url, $admin_id) {
    if (!file_exists(AUDIO_TRACKS_FILE)) initialize_trailers_system();
    
    $audio_id = 'aud_' . uniqid();
    $entry = [
        $movie_name,
        $audio_url,
        $audio_id,
        'mp3',
        '320kbps',
        'Unknown',
        'Hindi'
    ];
    
    $handle = fopen(AUDIO_TRACKS_FILE, 'a');
    fputcsv($handle, $entry);
    fclose($handle);
    
    bot_log("Audio track added: $movie_name by admin $admin_id");
    return $audio_id;
}

// ==============================
// ADMIN DASHBOARD SYSTEM
// ==============================
function show_admin_dashboard($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $settings = json_decode(file_get_contents(SETTINGS_FILE), true);
    $health = check_bot_health();
    
    $message = "👑 <b>Admin Dashboard</b>\n\n";
    
    // System Status
    $message .= "🖥️ <b>System Status:</b> " . strtoupper($health['status']) . "\n";
    $message .= "⏱️ Uptime: " . get_bot_uptime() . "\n";
    $message .= "💾 Memory: " . ($health['checks']['memory'] ?? 'N/A') . "\n\n";
    
    // Bot Statistics
    $message .= "📊 <b>Bot Statistics:</b>\n";
    $message .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $message .= "• 👥 Users: " . count($users_data['users'] ?? []) . "\n";
    $message .= "• 🔍 Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $message .= "• 📥 Downloads: " . ($stats['total_downloads'] ?? 0) . "\n";
    $message .= "• 📝 Requests: " . get_pending_requests_count() . " pending\n\n";
    
    // Today's Activity
    $today = date('Y-m-d');
    if (isset($stats['daily_activity'][$today])) {
        $today_stats = $stats['daily_activity'][$today];
        $message .= "📈 <b>Today's Activity:</b>\n";
        $message .= "• Searches: " . ($today_stats['searches'] ?? 0) . "\n";
        $message .= "• Downloads: " . ($today_stats['downloads'] ?? 0) . "\n";
        $message .= "• New Users: " . ($today_stats['users'] ?? 0) . "\n\n";
    }
    
    // Scheduled Posts
    $scheduled_count = get_scheduled_posts_count('pending');
    $message .= "📅 <b>Scheduled Posts:</b> $scheduled_count pending\n\n";
    
    // Cache Status
    $cache_data = file_exists(CACHE_FILE) ? json_decode(file_get_contents(CACHE_FILE), true) : [];
    $message .= "⚡ <b>Cache Status:</b>\n";
    $message .= "• Hits: " . ($cache_data['hits'] ?? 0) . "\n";
    $message .= "• Misses: " . ($cache_data['misses'] ?? 0) . "\n";
    $message .= "• Size: " . ($cache_data['size'] ?? 0) . " movies\n\n";
    
    // Settings Status
    $message .= "⚙️ <b>Active Settings:</b>\n";
    $message .= "• Auto-clean: " . ($settings['auto_clean'] ? '✅' : '❌') . "\n";
    $message .= "• Auto-backup: " . ($settings['auto_backup'] ? '✅' : '❌') . "\n";
    $message .= "• Rate limiting: " . ($settings['rate_limiting'] ? '✅' : '❌') . "\n";
    $message .= "• Duplicate check: " . ($settings['duplicate_check'] ? '✅' : '❌') . "\n";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📊 Detailed Stats', 'callback_data' => 'admin_stats_detailed'],
                ['text' => '⚙️ Settings', 'callback_data' => 'admin_settings']
            ],
            [
                ['text' => '📅 Schedules', 'callback_data' => 'admin_schedules'],
                ['text' => '🧹 Cleanup', 'callback_data' => 'admin_cleanup']
            ],
            [
                ['text' => '📤 Post Movie', 'callback_data' => 'admin_post_movie'],
                ['text' => '🚫 Ban User', 'callback_data' => 'admin_ban_user']
            ],
            [
                ['text' => '🔍 Search Logs', 'callback_data' => 'admin_search_logs'],
                ['text' => '📝 Requests', 'callback_data' => 'admin_requests']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_admin_settings_panel($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $settings = json_decode(file_get_contents(SETTINGS_FILE), true);
    
    $message = "⚙️ <b>Bot Settings Panel</b>\n\n";
    
    foreach ($settings as $key => $value) {
        if (is_array($value)) continue;
        
        $display_key = ucwords(str_replace('_', ' ', $key));
        $display_value = is_bool($value) ? ($value ? '✅ Enabled' : '❌ Disabled') : $value;
        
        $message .= "• <b>$display_key:</b> $display_value\n";
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔄 Toggle Auto-clean', 'callback_data' => 'toggle_setting_auto_clean'],
                ['text' => '🔄 Toggle Auto-backup', 'callback_data' => 'toggle_setting_auto_backup']
            ],
            [
                ['text' => '🔄 Toggle Rate Limit', 'callback_data' => 'toggle_setting_rate_limiting'],
                ['text' => '🔄 Toggle Duplicate Check', 'callback_data' => 'toggle_setting_duplicate_check']
            ],
            [
                ['text' => '🔄 Toggle Bot Replies', 'callback_data' => 'toggle_setting_delete_bot_replies'],
                ['text' => '🔄 Toggle Typing', 'callback_data' => 'toggle_setting_typing_indicator']
            ],
            [
                ['text' => '🏠 Dashboard', 'callback_data' => 'admin_dashboard'],
                ['text' => '✏️ Edit Settings', 'callback_data' => 'edit_settings']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function toggle_setting($chat_id, $setting_key) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $settings = json_decode(file_get_contents(SETTINGS_FILE), true);
    
    if (isset($settings[$setting_key])) {
        $settings[$setting_key] = !$settings[$setting_key];
        file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT));
        
        $status = $settings[$setting_key] ? 'enabled' : 'disabled';
        sendMessage($chat_id, "✅ Setting '$setting_key' $status.");
        bot_log("Setting toggled: $setting_key = $status by $chat_id");
    } else {
        sendMessage($chat_id, "❌ Setting '$setting_key' not found.");
    }
}

// ==============================
// CHANNEL STATS SYSTEM
// ==============================
function get_channel_stats($channel_id) {
    // Note: Telegram API doesn't provide detailed channel stats to bots
    // This is a placeholder for future implementation with proper API access
    
    return [
        'channel_id' => $channel_id,
        'total_posts' => get_channel_post_count($channel_id),
        'last_post' => get_channel_last_post_time($channel_id),
        'movie_count' => get_movies_from_channel($channel_id),
        'estimated_members' => 'N/A (Bot cannot access member count)'
    ];
}

function get_channel_post_count($channel_id) {
    $count = 0;
    
    if (file_exists(CSV_FILE)) {
        $handle = fopen(CSV_FILE, 'r');
        if ($handle !== FALSE) {
            fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) >= 2 && !empty($row[1])) {
                    $count++;
                }
            }
            fclose($handle);
        }
    }
    
    return $count;
}

function get_channel_last_post_time($channel_id) {
    $last_time = 'N/A';
    
    if (file_exists(CSV_FILE)) {
        $handle = fopen(CSV_FILE, 'r');
        if ($handle !== FALSE) {
            fgetcsv($handle);
            $latest_date = '';
            
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) >= 3 && !empty($row[2])) {
                    if ($row[2] > $latest_date) {
                        $latest_date = $row[2];
                    }
                }
            }
            fclose($handle);
            
            $last_time = $latest_date ?: 'N/A';
        }
    }
    
    return $last_time;
}

function get_movies_from_channel($channel_id) {
    // Count movies from specific channel (simplified - assuming all movies are from main channel)
    $movies = get_cached_movies();
    return count($movies);
}

// ==============================
// SOFT DELETE SYSTEM
// ==============================
function soft_delete_movie($movie_id, $deleted_by, $reason = '') {
    $movies = get_cached_movies();
    $deleted_movie = null;
    
    foreach ($movies as $key => $movie) {
        if ($movie['message_id_raw'] == $movie_id || 
            md5($movie['movie_name'] . $movie['message_id_raw']) == $movie_id) {
            $deleted_movie = $movie;
            
            // Move to deleted movies file
            $deleted_entry = [
                'original_data' => $movie,
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => $deleted_by,
                'reason' => $reason,
                'deleted_id' => 'del_' . uniqid()
            ];
            
            $deleted_data = [];
            if (file_exists(DELETED_MOVIES_FILE)) {
                $deleted_data = json_decode(file_get_contents(DELETED_MOVIES_FILE), true);
            }
            
            $deleted_data[] = $deleted_entry;
            file_put_contents(DELETED_MOVIES_FILE, json_encode($deleted_data, JSON_PRETTY_PRINT));
            
            // Remove from CSV
            remove_movie_from_csv($movie_id);
            break;
        }
    }
    
    if ($deleted_movie) {
        clear_cache();
        bot_log("Movie soft deleted: {$deleted_movie['movie_name']} by $deleted_by - Reason: $reason");
        return true;
    }
    
    return false;
}

function remove_movie_from_csv($movie_id) {
    if (!file_exists(CSV_FILE)) return false;
    
    $temp_file = CSV_FILE . '.tmp';
    $input = fopen(CSV_FILE, 'r');
    $output = fopen($temp_file, 'w');
    
    if ($input === FALSE || $output === FALSE) return false;
    
    // Copy header
    $header = fgetcsv($input);
    fputcsv($output, $header);
    
    $removed = false;
    while (($row = fgetcsv($input)) !== FALSE) {
        $current_id = $row[1] ?? '';
        $hash_id = md5(($row[0] ?? '') . $current_id);
        
        if ($current_id != $movie_id && $hash_id != $movie_id) {
            fputcsv($output, $row);
        } else {
            $removed = true;
        }
    }
    
    fclose($input);
    fclose($output);
    
    if ($removed) {
        rename($temp_file, CSV_FILE);
    } else {
        unlink($temp_file);
    }
    
    return $removed;
}

function restore_deleted_movie($deleted_id, $restored_by) {
    if (!file_exists(DELETED_MOVIES_FILE)) return false;
    
    $deleted_data = json_decode(file_get_contents(DELETED_MOVIES_FILE), true);
    $restored_movie = null;
    
    foreach ($deleted_data as $key => $entry) {
        if ($entry['deleted_id'] == $deleted_id) {
            $restored_movie = $entry['original_data'];
            
            // Add back to CSV
            $handle = fopen(CSV_FILE, 'a');
            fputcsv($handle, [
                $restored_movie['movie_name'],
                $restored_movie['message_id_raw'],
                $restored_movie['date'],
                $restored_movie['video_path'],
                $restored_movie['quality'],
                $restored_movie['size'],
                $restored_movie['language']
            ]);
            fclose($handle);
            
            // Remove from deleted file
            unset($deleted_data[$key]);
            file_put_contents(DELETED_MOVIES_FILE, json_encode(array_values($deleted_data), JSON_PRETTY_PRINT));
            
            clear_cache();
            bot_log("Movie restored: {$restored_movie['movie_name']} by $restored_by");
            break;
        }
    }
    
    return $restored_movie;
}

// ==============================
// USER BAN SYSTEM
// ==============================
function ban_user($user_id, $banned_by, $reason = '', $duration_hours = 0) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id])) {
        return false;
    }
    
    $ban_data = [
        'banned_at' => date('Y-m-d H:i:s'),
        'banned_by' => $banned_by,
        'reason' => $reason,
        'duration_hours' => $duration_hours,
        'unban_at' => $duration_hours > 0 ? date('Y-m-d H:i:s', time() + ($duration_hours * 3600)) : null
    ];
    
    $users_data['users'][$user_id]['banned'] = $ban_data;
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    
    bot_log("User banned: $user_id by $banned_by - Reason: $reason - Duration: $duration_hours hours");
    return true;
}

function unban_user($user_id, $unbanned_by) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id]) || !isset($users_data['users'][$user_id]['banned'])) {
        return false;
    }
    
    unset($users_data['users'][$user_id]['banned']);
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    
    bot_log("User unbanned: $user_id by $unbanned_by");
    return true;
}

function is_user_banned($user_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id]) || !isset($users_data['users'][$user_id]['banned'])) {
        return false;
    }
    
    $ban_data = $users_data['users'][$user_id]['banned'];
    
    // Check if temporary ban has expired
    if ($ban_data['duration_hours'] > 0 && isset($ban_data['unban_at'])) {
        $unban_time = strtotime($ban_data['unban_at']);
        if (time() > $unban_time) {
            unban_user($user_id, 'system');
            return false;
        }
    }
    
    return $ban_data;
}

// ==============================
// REQUEST STATUS SYSTEM
// ==============================
function get_request_status($request_id) {
    if (!file_exists(REQUEST_FILE)) return null;
    
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['id'] == $request_id) {
            return $request;
        }
    }
    
    return null;
}

function update_request_status($request_id, $status, $notes = '') {
    if (!file_exists(REQUEST_FILE)) return false;
    
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $updated = false;
    
    foreach ($requests_data['requests'] as &$request) {
        if ($request['id'] == $request_id) {
            $request['status'] = $status;
            $request['updated_at'] = date('Y-m-d H:i:s');
            if (!empty($notes)) {
                $request['notes'] = $notes;
            }
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
        bot_log("Request status updated: $request_id -> $status");
    }
    
    return $updated;
}

function get_pending_requests_count() {
    if (!file_exists(REQUEST_FILE)) return 0;
    
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $count = 0;
    
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['status'] == 'pending') {
            $count++;
        }
    }
    
    return $count;
}

// ==============================
// AUTO-NOTIFY WAITING USERS
// ==============================
function notify_waiting_users($movie_name, $message_id) {
    global $waiting_users;
    
    $movie_lower = strtolower(trim($movie_name));
    $notified_count = 0;
    
    // Notify individual users
    if (!empty($waiting_users[$movie_lower])) {
        foreach ($waiting_users[$movie_lower] as $user_data) {
            list($user_chat_id, $user_id) = $user_data;
            
            try {
                $message = "🎉 <b>Good News!</b>\n\n";
                $message .= "Your requested movie <b>$movie_name</b> has been added!\n\n";
                $message .= "Join channel to download: " . MAIN_CHANNEL . "\n";
                $message .= "🔗 Direct link will be available soon.";
                
                sendMessage($user_chat_id, $message, null, 'HTML');
                $notified_count++;
                
                // Remove from waiting list
                unset($waiting_users[$movie_lower]);
                
                bot_log("Notified user $user_id about movie: $movie_name");
            } catch (Exception $e) {
                bot_log("Failed to notify user $user_id: " . $e->getMessage(), 'ERROR');
            }
        }
    }
    
    // Notify request channel
    try {
        $channel_message = "🔔 <b>Movie Added Notification</b>\n\n";
        $channel_message .= "🎬 <b>$movie_name</b> has been added to our collection!\n\n";
        $channel_message .= "📢 Join: " . MAIN_CHANNEL . " to download\n";
        $channel_message .= "👥 $notified_count users were waiting for this movie!\n\n";
        $channel_message .= "📅 Added: " . date('d-m-Y') . "\n";
        
        sendMessage('-1003181705395', $channel_message, null, 'HTML');
        bot_log("Notified request channel about movie: $movie_name");
    } catch (Exception $e) {
        bot_log("Failed to notify request channel: " . $e->getMessage(), 'ERROR');
    }
    
    return $notified_count;
}

// ==============================
// ENHANCED APPEND MOVIE WITH DUPLICATE PROTECTION
// ==============================
function append_movie_with_protection($movie_name, $message_id_raw, $date = null, $video_path = '', $quality = 'Unknown', $size = 'Unknown', $language = 'Hindi', $check_duplicate = true) {
    global $movie_messages, $movie_cache, $waiting_users;
    
    if (empty(trim($movie_name))) return false;
    
    $settings = json_decode(file_get_contents(SETTINGS_FILE), true);
    
    // Check for duplicates if enabled
    if ($check_duplicate && $settings['duplicate_check']) {
        $duplicate_check = check_duplicate_movie($movie_name, $message_id_raw, CHANNEL_ID);
        
        if ($duplicate_check) {
            if ($duplicate_check['type'] == 'exact_duplicate') {
                log_duplicate_attempt($movie_name, $duplicate_check, 'blocked_exact');
                return [
                    'status' => 'duplicate_blocked',
                    'type' => 'exact',
                    'existing' => $duplicate_check['existing']
                ];
            } elseif ($duplicate_check['type'] == 'similar_duplicate') {
                log_duplicate_attempt($movie_name, $duplicate_check, 'blocked_similar');
                return [
                    'status' => 'duplicate_blocked',
                    'type' => 'similar',
                    'duplicates' => $duplicate_check['duplicates'],
                    'count' => $duplicate_check['count']
                ];
            }
        }
    }
    
    if ($date === null) $date = date('d-m-Y');
    $entry = [$movie_name, $message_id_raw, $date, $video_path, $quality, $size, $language];
    
    $handle = fopen(CSV_FILE, "a");
    fputcsv($handle, $entry);
    fclose($handle);

    $movie = strtolower(trim($movie_name));
    $item = [
        'movie_name' => $movie_name,
        'message_id_raw' => $message_id_raw,
        'date' => $date,
        'video_path' => $video_path,
        'quality' => $quality,
        'size' => $size,
        'language' => $language,
        'message_id' => is_numeric($message_id_raw) ? intval($message_id_raw) : null
    ];
    
    if (!isset($movie_messages[$movie])) $movie_messages[$movie] = [];
    $movie_messages[$movie][] = $item;
    $movie_cache = [];

    // Auto-notify waiting users
    if ($settings['auto_notify']) {
        $notified_count = notify_waiting_users($movie_name, $message_id_raw);
        if ($notified_count > 0) {
            bot_log("Auto-notified $notified_count users for movie: $movie_name");
        }
    }

    update_stats('total_movies', 1);
    bot_log("Movie appended with protection: $movie_name with ID $message_id_raw");
    
    // Rebuild cache and index
    clear_cache();
    get_cached_movies();
    rebuild_prefix_index();
    
    return [
        'status' => 'added',
        'movie' => $item,
        'notified_users' => $notified_count ?? 0
    ];
}

// ==============================
// BACKGROUND AUTO TASKS
// ==============================
function run_background_tasks() {
    $last_run = file_exists(SETTINGS_FILE) ? 
        (json_decode(file_get_contents(SETTINGS_FILE), true)['last_background_run'] ?? 0) : 0;
    
    // Run every 5 minutes
    if (time() - $last_run > 300) {
        bot_log("Starting background tasks...");
        
        // 1. CSV auto-cleaner
        $clean_result = csv_auto_cleaner();
        
        // 2. Check scheduled posts
        check_scheduled_posts();
        
        // 3. Check for expired bans
        check_expired_bans();
        
        // 4. Auto-backup check (will run at scheduled time)
        $current_hour = date('H');
        if ($current_hour == AUTO_BACKUP_HOUR && date('i') < 5) {
            auto_backup();
        }
        
        // 5. Update bot status
        update_bot_status(true);
        
        // Update last run time
        $settings = json_decode(file_get_contents(SETTINGS_FILE), true);
        $settings['last_background_run'] = time();
        file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT));
        
        bot_log("Background tasks completed");
        
        return [
            'csv_cleaned' => $clean_result ? 'yes' : 'no',
            'schedules_checked' => 'yes',
            'bans_checked' => 'yes',
            'status_updated' => 'yes'
        ];
    }
    
    return false;
}

function check_expired_bans() {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $unbanned_count = 0;
    
    foreach ($users_data['users'] as $user_id => $user) {
        if (isset($user['banned']) && isset($user['banned']['unban_at'])) {
            $unban_time = strtotime($user['banned']['unban_at']);
            if (time() > $unban_time) {
                unban_user($user_id, 'system');
                $unbanned_count++;
            }
        }
    }
    
    if ($unbanned_count > 0) {
        bot_log("Auto-unbanned $unbanned_count users");
    }
    
    return $unbanned_count;
}

// ==============================
// CSV LOCKING SYSTEM (DATA SAFETY)
// ==============================
class CSV_Lock {
    private static $lock_file = 'csv.lock';
    private static $timeout = 30; // 30 seconds timeout
    
    public static function acquire() {
        $start_time = time();
        
        while (file_exists(self::$lock_file)) {
            if (time() - $start_time > self::$timeout) {
                throw new Exception("Could not acquire CSV lock after " . self::$timeout . " seconds");
            }
            usleep(100000); // 0.1 second
        }
        
        file_put_contents(self::$lock_file, time());
        return true;
    }
    
    public static function release() {
        if (file_exists(self::$lock_file)) {
            unlink(self::$lock_file);
        }
    }
    
    public static function is_locked() {
        return file_exists(self::$lock_file);
    }
}

// ==============================
// ENHANCED LOGGING SYSTEM
// ==============================
function bot_log($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    
    // Write to log file
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
    
    // Also log to admin if error
    if ($type == 'ERROR' || $type == 'CRITICAL') {
        try {
            $admin_msg = "🚨 <b>Bot $type</b>\n\n";
            $admin_msg .= "📅 $timestamp\n";
            $admin_msg .= "📝 $message\n\n";
            $admin_msg .= "🔧 Check logs for details.";
            
            sendMessage(ADMIN_ID, $admin_msg, null, 'HTML');
        } catch (Exception $e) {
            // Silently fail if can't notify admin
        }
    }
    
    // Keep log file manageable
    if (filesize(LOG_FILE) > 10 * 1024 * 1024) { // 10MB
        $lines = file(LOG_FILE);
        $lines = array_slice($lines, -10000); // Keep last 10,000 lines
        file_put_contents(LOG_FILE, implode('', $lines));
    }
}

// ==============================
// COMPLETE COMMAND HANDLER UPGRADE
// ==============================
function handle_command_v2($chat_id, $user_id, $command, $params = []) {
    // Check if user is banned
    $ban_info = is_user_banned($user_id);
    if ($ban_info) {
        $unban_time = isset($ban_info['unban_at']) ? "\n⏰ Unban at: " . $ban_info['unban_at'] : '';
        sendMessage($chat_id, "🚫 <b>You are banned!</b>\n\nReason: {$ban_info['reason']}$unban_time\n\nContact admin for appeal.", null, 'HTML');
        return;
    }
    
    // Check rate limiting for search/download/request commands
    $action = '';
    if (in_array($command, ['/search', '/s', '/find']) || (empty($command) && !empty($params))) {
        $action = 'search';
    } elseif (in_array($command, ['/request', '/req'])) {
        $action = 'request';
    } elseif (in_array($command, ['/totalupload', '/latest', '/trending'])) {
        $action = 'download';
    }
    
    if ($action && !check_rate_limit($user_id, $action)) {
        $status = get_rate_limit_status($user_id);
        $reset_in = $status[$action]['reset_in'] ?? 60;
        $minutes = ceil($reset_in / 60);
        
        sendMessage($chat_id, "⏳ <b>Rate Limit Exceeded!</b>\n\nYou've reached the limit for $action.\nPlease wait $minutes minutes before trying again.", null, 'HTML');
        return;
    }
    
    // Add typing indicator for better UX
    send_typing_indicator($chat_id);
    
    switch ($command) {
        // ==================== NEW START MESSAGE ====================
        case '/start':
            $welcome = generate_welcome_message($user_id);
            
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
                        ['text' => '📊 My Stats', 'callback_data' => 'my_stats'],
                        ['text' => '🏆 Leaderboard', 'callback_data' => 'show_leaderboard']
                    ],
                    [
                        ['text' => '❓ Help', 'callback_data' => 'help_command'],
                        ['text' => '⚙️ Settings', 'callback_data' => 'user_settings']
                    ]
                ]
            ];
            
            $message_result = sendMessage($chat_id, $welcome, $keyboard, 'HTML');
            
            // Auto-delete bot reply after 2 minutes if enabled
            auto_delete_bot_replies($chat_id, $message_result['result']['message_id'], 120);
            
            update_user_activity($user_id, 'daily_login');
            break;

        // ==================== ENHANCED HELP ====================
        case '/help':
            $help_message = generate_help_message($user_id == ADMIN_ID);
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔍 Search Commands', 'callback_data' => 'help_search'],
                        ['text' => '📁 Browse Commands', 'callback_data' => 'help_browse']
                    ],
                    [
                        ['text' => '📝 Request Commands', 'callback_data' => 'help_request'],
                        ['text' => '👤 User Commands', 'callback_data' => 'help_user']
                    ],
                    [
                        ['text' => '🎬 Extra Commands', 'callback_data' => 'help_extra'],
                        ['text' => '👑 Admin Commands', 'callback_data' => 'help_admin']
                    ],
                    [
                        ['text' => '🔙 Back to Start', 'callback_data' => 'back_to_start']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $help_message, $keyboard, 'HTML');
            break;

        // ==================== NEW RANDOM MOVIE COMMAND ====================
        case '/random':
            $movies = get_cached_movies();
            if (empty($movies)) {
                sendMessage($chat_id, "📭 Koi movies nahi mili!");
                return;
            }
            
            $random_movie = $movies[array_rand($movies)];
            $message = "🎲 <b>Random Movie Pick</b>\n\n";
            $message .= "🎬 <b>" . htmlspecialchars($random_movie['movie_name']) . "</b>\n";
            $message .= "📊 Quality: " . $random_movie['quality'] . "\n";
            $message .= "🗣️ Language: " . $random_movie['language'] . "\n";
            $message .= "📅 Date: " . $random_movie['date'] . "\n\n";
            $message .= "🔗 Join channel to download: " . MAIN_CHANNEL;
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📥 Get This Movie Info', 'callback_data' => 'random_get_' . md5($random_movie['movie_name'] . $random_movie['message_id_raw'])],
                        ['text' => '🎲 Another Random', 'callback_data' => 'another_random']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $message, $keyboard, 'HTML');
            break;

        // ==================== NEW TRAILER COMMAND ====================
        case '/trailer':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: <code>/trailer movie_name</code>\nExample: <code>/trailer Animal</code>", null, 'HTML');
                return;
            }
            
            $trailer = get_movie_trailer($movie_name);
            if ($trailer) {
                $message = "🎬 <b>Trailer for: {$trailer['movie_name']}</b>\n\n";
                $message .= "⏱️ Duration: {$trailer['duration']}\n";
                $message .= "📊 Quality: {$trailer['quality']}\n";
                $message .= "👁️ Views: {$trailer['views']}\n";
                $message .= "📅 Uploaded: {$trailer['upload_date']}\n\n";
                $message .= "🔗 Trailer URL: {$trailer['trailer_url']}";
                
                sendMessage($chat_id, $message, null, 'HTML');
            } else {
                sendMessage($chat_id, "❌ Trailer not found for: $movie_name\n\nYou can request it using /request command.", null, 'HTML');
            }
            break;

        // ==================== NEW AUDIO COMMAND ====================
        case '/audio':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: <code>/audio movie_name</code>\nExample: <code>/audio Animal songs</code>", null, 'HTML');
                return;
            }
            
            $audio = get_movie_audio($movie_name);
            if ($audio) {
                $message = "🎵 <b>Audio Tracks for: {$audio['movie_name']}</b>\n\n";
                $message .= "📁 Format: {$audio['format']}\n";
                $message .= "🔊 Bitrate: {$audio['bitrate']}\n";
                $message .= "💾 Size: {$audio['size']}\n";
                $message .= "🗣️ Language: {$audio['language']}\n\n";
                $message .= "🔗 Audio URL: {$audio['audio_url']}";
                
                sendMessage($chat_id, $message, null, 'HTML');
            } else {
                sendMessage($chat_id, "❌ Audio tracks not found for: $movie_name", null, 'HTML');
            }
            break;

        // ==================== NEW TOP COMMAND ====================
        case '/top':
            $limit = isset($params[0]) ? intval($params[0]) : 10;
            $limit = min($limit, 50);
            
            $movies = get_cached_movies();
            $recent_movies = array_slice($movies, -$limit);
            $recent_movies = array_reverse($recent_movies);
            
            $message = "🏆 <b>Top $limit Recent Movies</b>\n\n";
            $i = 1;
            
            foreach ($recent_movies as $movie) {
                $message .= "$i. <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
                $message .= "   📊 " . $movie['quality'] . " | 🗣️ " . $movie['language'] . "\n\n";
                $i++;
            }
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📥 Get All Info', 'callback_data' => 'download_top_' . $limit],
                        ['text' => '📊 Browse All', 'callback_data' => 'browse_all']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $message, $keyboard, 'HTML');
            break;

        // ==================== NEW MISSED COMMAND ====================
        case '/missed':
            // Shows movies added since user's last activity
            $users_data = json_decode(file_get_contents(USERS_FILE), true);
            $user = $users_data['users'][$user_id] ?? [];
            $last_active = strtotime($user['last_active'] ?? '2000-01-01');
            
            $movies = get_cached_movies();
            $new_movies = [];
            
            foreach ($movies as $movie) {
                $movie_date = strtotime($movie['date']);
                if ($movie_date > $last_active) {
                    $new_movies[] = $movie;
                }
            }
            
            if (empty($new_movies)) {
                sendMessage($chat_id, "🎉 You're up to date! No new movies since your last activity.", null, 'HTML');
                return;
            }
            
            $new_movies = array_slice($new_movies, -20); // Last 20 new movies
            $new_movies = array_reverse($new_movies);
            
            $message = "🆕 <b>Movies You Missed</b>\n\n";
            $message .= "📅 Since: " . date('Y-m-d H:i:s', $last_active) . "\n";
            $message .= "🎬 New Movies: " . count($new_movies) . "\n\n";
            
            $i = 1;
            foreach ($new_movies as $movie) {
                $message .= "$i. <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
                $message .= "   📅 " . $movie['date'] . "\n\n";
                $i++;
                if ($i > 10) break;
            }
            
            if (count($new_movies) > 10) {
                $message .= "... and " . (count($new_movies) - 10) . " more\n\n";
            }
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📥 Get All New Movies Info', 'callback_data' => 'download_missed'],
                        ['text' => '🔄 Mark as Seen', 'callback_data' => 'mark_missed_seen']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $message, $keyboard, 'HTML');
            break;

        // ==================== NEW SUGGEST COMMAND ====================
        case '/suggest':
            $movies = get_cached_movies();
            if (empty($movies)) {
                sendMessage($chat_id, "📭 Koi movies nahi mili!");
                return;
            }
            
            // Get user's download history
            $users_data = json_decode(file_get_contents(USERS_FILE), true);
            $user = $users_data['users'][$user_id] ?? [];
            
            // Simple recommendation based on quality preference
            $preferred_quality = '1080p';
            $preferred_language = 'Hindi';
            
            $suggestions = [];
            foreach ($movies as $movie) {
                $score = 0;
                
                if ($movie['quality'] == $preferred_quality) $score += 5;
                if ($movie['language'] == $preferred_language) $score += 3;
                
                // Recent movies get higher score
                $movie_date = strtotime($movie['date']);
                $days_ago = (time() - $movie_date) / 86400;
                if ($days_ago < 7) $score += 10; // Movies from last week
                elseif ($days_ago < 30) $score += 5; // Movies from last month
                
                if ($score > 0) {
                    $suggestions[] = [
                        'movie' => $movie,
                        'score' => $score
                    ];
                }
            }
            
            usort($suggestions, function($a, $b) {
                return $b['score'] - $a['score'];
            });
            
            $top_suggestions = array_slice($suggestions, 0, 5);
            
            $message = "💡 <b>Movie Suggestions For You</b>\n\n";
            $message .= "Based on your preferences:\n";
            $message .= "📊 Quality: $preferred_quality\n";
            $message .= "🗣️ Language: $preferred_language\n\n";
            
            $i = 1;
            foreach ($top_suggestions as $suggestion) {
                $movie = $suggestion['movie'];
                $message .= "$i. <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
                $message .= "   ⭐ " . $movie['quality'] . " | 🗣️ " . $movie['language'] . "\n\n";
                $i++;
            }
            
            $keyboard = [
                'inline_keyboard' => []
            ];
            
            foreach ($top_suggestions as $index => $suggestion) {
                $movie = $suggestion['movie'];
                $keyboard['inline_keyboard'][] = [[
                    'text' => ($index + 1) . '. ' . $movie['movie_name'],
                    'callback_data' => 'suggest_get_' . md5($movie['movie_name'] . $movie['message_id_raw'])
                ]];
            }
            
            sendMessage($chat_id, $message, $keyboard, 'HTML');
            break;

        // ==================== NEW ADMIN COMMANDS ====================
        case '/addmovie':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                return;
            }
            
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: <code>/addmovie \"Movie Name (2023)\" message_id quality language</code>\nExample: <code>/addmovie \"Animal (2023)\" 1234 1080p Hindi</code>", null, 'HTML');
                return;
            }
            
            // Parse parameters
            $message_id = isset($params[count($params)-4]) ? $params[count($params)-4] : '';
            $quality = isset($params[count($params)-3]) ? $params[count($params)-3] : '1080p';
            $language = isset($params[count($params)-2]) ? $params[count($params)-2] : 'Hindi';
            $size = isset($params[count($params)-1]) ? $params[count($params)-1] : 'Unknown';
            
            // Extract movie name (remove the last 4 parameters)
            $movie_parts = array_slice($params, 0, count($params) - 4);
            $movie_name = implode(' ', $movie_parts);
            
            $result = append_movie_with_protection($movie_name, $message_id, date('d-m-Y'), '', $quality, $size, $language);
            
            if ($result['status'] == 'added') {
                $message = "✅ <b>Movie Added Successfully</b>\n\n";
                $message .= "🎬 Movie: $movie_name\n";
                $message .= "📝 Message ID: $message_id\n";
                $message .= "📊 Quality: $quality\n";
                $message .= "🗣️ Language: $language\n";
                $message .= "👥 Notified: {$result['notified_users']} users\n\n";
                $message .= "📅 Added: " . date('d-m-Y H:i:s');
            } else {
                $message = "⚠️ <b>Duplicate Movie Detected</b>\n\n";
                $message .= "🎬 Movie: $movie_name\n";
                $message .= "📝 Type: {$result['type']} duplicate\n\n";
                
                if ($result['type'] == 'exact') {
                    $existing = $result['existing'];
                    $message .= "📌 Existing Entry:\n";
                    $message .= "• Message ID: {$existing['message_id_raw']}\n";
                    $message .= "• Date: {$existing['date']}\n";
                    $message .= "• Quality: {$existing['quality']}\n";
                } else {
                    $message .= "📌 Similar Entries Found: {$result['count']}\n";
                    foreach (array_slice($result['duplicates'], 0, 3) as $dup) {
                        $message .= "• {$dup['movie']} (ID: {$dup['message_id']})\n";
                    }
                }
                
                $message .= "\n💡 Use <code>/forceadd</code> to add anyway.";
            }
            
            sendMessage($chat_id, $message, null, 'HTML');
            break;

        case '/editmovie':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                return;
            }
            
            sendMessage($chat_id, "✏️ <b>Edit Movie Feature</b>\n\nComing soon! For now, use manual CSV editing or contact developer.", null, 'HTML');
            break;

        case '/deletemovie':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                return;
            }
            
            $movie_id = implode(' ', $params);
            if (empty($movie_id)) {
                sendMessage($chat_id, "❌ Usage: <code>/deletemovie movie_id_or_hash</code>\nExample: <code>/deletemovie 1234</code>", null, 'HTML');
                return;
            }
            
            $reason = "Deleted by admin $user_id via command";
            $success = soft_delete_movie($movie_id, $user_id, $reason);
            
            if ($success) {
                sendMessage($chat_id, "✅ <b>Movie Soft Deleted</b>\n\nMovie ID: $movie_id\nReason: $reason\n\nUse /restoremovie to restore if needed.", null, 'HTML');
            } else {
                sendMessage($chat_id, "❌ Movie not found with ID: $movie_id", null, 'HTML');
            }
            break;

        case '/enablemovie':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                return;
            }
            
            sendMessage($chat_id, "🔓 <b>Enable Movie Feature</b>\n\nComing soon! Currently all movies are enabled by default.", null, 'HTML');
            break;

        case '/clean':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                return;
            }
            
            $result = csv_auto_cleaner();
            if ($result) {
                $message = "🧹 <b>CSV Cleanup Completed</b>\n\n";
                $message .= "✅ Removed duplicates: {$result['removed_duplicates']}\n";
                $message .= "✅ Fixed entries: {$result['fixed_entries']}\n";
                $message .= "📊 Total movies now: {$result['total_movies']}\n\n";
                $message .= "⚡ Cache cleared and rebuilt.";
            } else {
                $message = "ℹ️ <b>Cleanup Not Needed</b>\n\nLast cleanup was less than 24 hours ago.";
            }
            
            sendMessage($chat_id, $message, null, 'HTML');
            break;

        case '/ban':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                return;
            }
            
            if (count($params) < 2) {
                sendMessage($chat_id, "❌ Usage: <code>/ban user_id hours \"reason\"</code>\nExample: <code>/ban 123456789 24 \"Spamming\"</code>", null, 'HTML');
                return;
            }
            
            $target_user = $params[0];
            $hours = intval($params[1]);
            $reason = isset($params[2]) ? implode(' ', array_slice($params, 2)) : 'No reason provided';
            
            $success = ban_user($target_user, $user_id, $reason, $hours);
            
            if ($success) {
                $duration = $hours > 0 ? "for $hours hours" : "permanently";
                $message = "🚫 <b>User Banned</b>\n\n";
                $message .= "👤 User ID: $target_user\n";
                $message .= "⏰ Duration: $duration\n";
                $message .= "📝 Reason: $reason\n";
                $message .= "👑 Banned by: You\n\n";
                $message .= "Use /unban to remove ban.";
                
                // Notify the banned user
                try {
                    $user_message = "🚫 <b>You have been banned!</b>\n\n";
                    $user_message .= "⏰ Duration: $duration\n";
                    $user_message .= "📝 Reason: $reason\n\n";
                    $user_message .= "Contact admin for appeal.";
                    sendMessage($target_user, $user_message, null, 'HTML');
                } catch (Exception $e) {
                    // Ignore if can't notify
                }
            } else {
                $message = "❌ User not found or already banned: $target_user";
            }
            
            sendMessage($chat_id, $message, null, 'HTML');
            break;

        case '/unban':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                return;
            }
            
            $target_user = implode(' ', $params);
            if (empty($target_user)) {
                sendMessage($chat_id, "❌ Usage: <code>/unban user_id</code>\nExample: <code>/unban 123456789</code>", null, 'HTML');
                return;
            }
            
            $success = unban_user($target_user, $user_id);
            
            if ($success) {
                $message = "✅ <b>User Unbanned</b>\n\nUser ID: $target_user\nUnbanned by: You";
                
                // Notify the unbanned user
                try {
                    sendMessage($target_user, "✅ <b>Your ban has been lifted!</b>\n\nYou can now use the bot again.", null, 'HTML');
                } catch (Exception $e) {
                    // Ignore if can't notify
                }
            } else {
                $message = "❌ User not found or not banned: $target_user";
            }
            
            sendMessage($chat_id, $message, null, 'HTML');
            break;

        case '/ratelimit':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                return;
            }
            
            $status = get_rate_limit_status($user_id);
            $message = "⏰ <b>Rate Limit Status</b>\n\n";
            
            foreach ($status as $action => $data) {
                $message .= "🔹 <b>" . ucfirst($action) . "</b>\n";
                $message .= "   Used: {$data['used']}/{$data['limit']}\n";
                $message .= "   Remaining: {$data['remaining']}\n";
                $message .= "   Resets in: " . gmdate("i:s", $data['reset_in']) . "\n\n";
            }
            
            sendMessage($chat_id, $message, null, 'HTML');
            break;

        case '/ping':
            $start_time = microtime(true);
            $health = check_bot_health();
            $end_time = microtime(true);
            $response_time = round(($end_time - $start_time) * 1000, 2);
            
            $message = "🏓 <b>Pong!</b>\n\n";
            $message .= "✅ Bot Status: Online\n";
            $message .= "⏱️ Response Time: {$response_time}ms\n";
            $message .= "⏰ Server Time: " . date('Y-m-d H:i:s') . "\n";
            $message .= "💾 Memory: " . $health['checks']['memory'] . "\n";
            $message .= "📊 Health: " . strtoupper($health['status']) . "\n";
            $message .= "🆙 Uptime: " . get_bot_uptime();
            
            sendMessage($chat_id, $message, null, 'HTML');
            break;

        case '/version':
            show_version_info($chat_id);
            break;

        case '/reload':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                return;
            }
            
            clear_cache();
            sendMessage($chat_id, "🔄 <b>Cache Reloaded</b>\n\nAll caches cleared and reloaded from CSV.\nPrefix index rebuilt.", null, 'HTML');
            break;

        case '/debug':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                return;
            }
            
            $debug_info = [
                'timestamp' => date('Y-m-d H:i:s'),
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB',
                'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024 . ' MB',
                'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : 'N/A',
                'php_version' => PHP_VERSION,
                'bot_status' => $bot_online_status ? 'online' : 'offline',
                'csv_size' => file_exists(CSV_FILE) ? filesize(CSV_FILE) : 0,
                'cache_size' => file_exists(CACHE_FILE) ? filesize(CACHE_FILE) : 0,
                'user_count' => count(json_decode(file_get_contents(USERS_FILE), true)['users'] ?? []),
                'movie_count' => count(get_cached_movies())
            ];
            
            $message = "🐛 <b>Debug Information</b>\n\n";
            foreach ($debug_info as $key => $value) {
                $message .= "• " . ucwords(str_replace('_', ' ', $key)) . ": $value\n";
            }
            
            sendMessage($chat_id, $message, null, 'HTML');
            break;

        case '/export':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                return;
            }
            
            // Create export file
            $export_data = [
                'movies' => get_cached_movies(),
                'users' => json_decode(file_get_contents(USERS_FILE), true)['users'] ?? [],
                'stats' => get_stats(),
                'export_date' => date('Y-m-d H:i:s'),
                'total_records' => [
                    'movies' => count(get_cached_movies()),
                    'users' => count(json_decode(file_get_contents(USERS_FILE), true)['users'] ?? [])
                ]
            ];
            
            $export_file = 'export_' . date('Ymd_His') . '.json';
            file_put_contents($export_file, json_encode($export_data, JSON_PRETTY_PRINT));
            
            // Send as document
            $caption = "📦 <b>Bot Data Export</b>\n\n" . date('Y-m-d H:i:s');
            $post_fields = [
                'chat_id' => $chat_id,
                'document' => new CURLFile($export_file),
                'caption' => $caption,
                'parse_mode' => 'HTML'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            curl_exec($ch);
            curl_close($ch);
            
            // Clean up
            unlink($export_file);
            
            bot_log("Data exported by admin $user_id");
            break;

        // ==================== EXISTING COMMANDS ====================
        default:
            // Handle existing commands from the original bot
            handle_command($chat_id, $user_id, $command, $params);
    }
}

// ==============================
// HELPER FUNCTIONS FOR NEW FEATURES
// ==============================
function generate_welcome_message($user_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user = $users_data['users'][$user_id] ?? [];
    $user_name = $user['first_name'] ?? 'User';
    
    $stats = get_stats();
    $total_movies = $stats['total_movies'] ?? 0;
    
    $message = "🎬 <b>Welcome back, $user_name!</b>\n\n";
    $message .= "🤖 <b>Entertainment Tadka Bot v3.0</b>\n";
    $message .= "📊 Movies in database: <b>$total_movies</b>\n\n";
    
    $message .= "⭐ <b>Your Stats:</b>\n";
    $message .= "• Points: " . ($user['points'] ?? 0) . "\n";
    $message .= "• Searches: " . ($user['total_searches'] ?? 0) . "\n";
    $message .= "• Downloads: " . ($user['total_downloads'] ?? 0) . "\n";
    $message .= "• Rank: " . calculate_user_rank($user['points'] ?? 0) . "\n\n";
    
    $message .= "🚀 <b>New Features:</b>\n";
    $message .= "• 🎲 /random - Random movie pick\n";
    $message .= "• 🎬 /trailer - Movie trailers\n";
    $message .= "• 🎵 /audio - Audio tracks\n";
    $message .= "• 🏆 /top - Top movies\n";
    $message .= "• 🆕 /missed - Missed movies\n";
    $message .= "• 💡 /suggest - Suggestions\n\n";
    
    $message .= "📢 <b>Channels:</b>\n";
    $message .= "🍿 Main: " . MAIN_CHANNEL . "\n";
    $message .= "📥 Requests: " . REQUEST_CHANNEL . "\n";
    $message .= "🎭 Theater: " . THEATER_CHANNEL . "\n\n";
    
    $message .= "💬 <b>Need help?</b> Use /help for all commands";
    
    return $message;
}

function generate_help_message($is_admin = false) {
    $message = "🤖 <b>Entertainment Tadka Bot - Complete Command Guide</b>\n\n";
    
    $message .= "🔍 <b>Search Commands:</b>\n";
    $message .= "• Type movie name - Smart search\n";
    $message .= "• <code>/search movie</code> - Direct search\n";
    $message .= "• <code>/s movie</code> - Quick search\n";
    $message .= "• <code>/find movie</code> - Find movie\n\n";
    
    $message .= "📁 <b>Browse Commands:</b>\n";
    $message .= "• <code>/totalupload</code> - All movies\n";
    $message .= "• <code>/latest</code> - New additions\n";
    $message .= "• <code>/trending</code> - Popular movies\n";
    $message .= "• <code>/top N</code> - Top N movies\n";
    $message .= "• <code>/missed</code> - Movies you missed\n";
    $message .= "• <code>/suggest</code> - Suggestions\n\n";
    
    $message .= "🎬 <b>Movie Commands:</b>\n";
    $message .= "• <code>/random</code> - Random movie\n";
    $message .= "• <code>/trailer movie</code> - Movie trailer\n";
    $message .= "• <code>/audio movie</code> - Audio tracks\n\n";
    
    $message .= "📝 <b>Request Commands:</b>\n";
    $message .= "• <code>/request movie</code> - Request movie\n";
    $message .= "• <code>/myrequests</code> - Request status\n";
    $message .= "• <code>/requestlimit</code> - Limit status\n\n";
    
    $message .= "👤 <b>User Commands:</b>\n";
    $message .= "• <code>/mystats</code> - Your statistics\n";
    $message .= "• <code>/leaderboard</code> - Top users\n";
    $message .= "• <code>/mypoints</code> - Points info\n";
    $message .= "• <code>/profile</code> - User profile\n\n";
    
    $message .= "📢 <b>Channel Commands:</b>\n";
    $message .= "• <code>/channel</code> - All channels\n";
    $message .= "• <code>/mainchannel</code> - Main channel\n";
    $message .= "• <code>/requestchannel</code> - Requests\n";
    $message .= "• <code>/theaterchannel</code> - Theater prints\n\n";
    
    $message .= "ℹ️ <b>Info Commands:</b>\n";
    $message .= "• <code>/checkdate</code> - Upload stats\n";
    $message .= "• <code>/info</code> - Bot information\n";
    $message .= "• <code>/version</code> - Version info\n";
    $message .= "• <code>/ping</code> - Bot status\n";
    $message .= "• <code>/feedback</code> - Send feedback\n";
    $message .= "• <code>/report</code> - Report bug\n\n";
    
    if ($is_admin) {
        $message .= "👑 <b>Admin Commands:</b>\n";
        $message .= "• <code>/addmovie</code> - Add movie\n";
        $message .= "• <code>/deletemovie</code> - Delete movie\n";
        $message .= "• <code>/clean</code> - Clean database\n";
        $message .= "• <code>/ban</code> - Ban user\n";
        $message .= "• <code>/unban</code> - Unban user\n";
        $message .= "• <code>/ratelimit</code> - Rate limit status\n";
        $message .= "• <code>/reload</code> - Reload cache\n";
        $message .= "• <code>/debug</code> - Debug info\n";
        $message .= "• <code>/export</code> - Export data\n";
        $message .= "• <code>/admin</code> - Admin dashboard\n";
        $message .= "• <code>/settings</code> - Bot settings\n";
        $message .= "• <code>/broadcast</code> - Send broadcast\n";
        $message .= "• <code>/backup</code> - Manual backup\n";
        $message .= "• <code>/maintenance</code> - Maintenance mode\n\n";
    }
    
    $message .= "💡 <b>Pro Tips:</b>\n";
    $message .= "• Use inline search (@botname movie)\n";
    $message .= "• Join all channels for updates\n";
    $message .= "• Check spelling before reporting\n";
    $message .= "• Use /help for specific categories";
    
    return $message;
}

// ==============================
// WEB INTERFACE (HTML UI)
// ==============================
function generate_web_interface() {
    if (php_sapi_name() === 'cli') return;
    
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $health = check_bot_health();
    $cache_data = file_exists(CACHE_FILE) ? json_decode(file_get_contents(CACHE_FILE), true) : [];
    
    $html = '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>🎬 Entertainment Tadka Bot</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #333;
                min-height: 100vh;
                padding: 20px;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: rgba(255, 255, 255, 0.95);
                border-radius: 20px;
                padding: 30px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }
            .header {
                text-align: center;
                margin-bottom: 40px;
                padding-bottom: 20px;
                border-bottom: 3px solid #667eea;
            }
            .header h1 {
                font-size: 3em;
                color: #764ba2;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 15px;
            }
            .header p {
                color: #666;
                font-size: 1.2em;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 40px;
            }
            .stat-card {
                background: white;
                padding: 25px;
                border-radius: 15px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                border-left: 5px solid #667eea;
                transition: transform 0.3s;
            }
            .stat-card:hover {
                transform: translateY(-5px);
            }
            .stat-card h3 {
                color: #764ba2;
                margin-bottom: 10px;
                font-size: 1.1em;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .stat-card .value {
                font-size: 2.5em;
                font-weight: bold;
                color: #333;
                margin: 10px 0;
            }
            .stat-card .label {
                color: #666;
                font-size: 0.9em;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .health-status {
                background: white;
                padding: 25px;
                border-radius: 15px;
                margin-bottom: 40px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
            .health-status h2 {
                color: #764ba2;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .health-indicator {
                display: inline-block;
                padding: 5px 15px;
                border-radius: 20px;
                font-weight: bold;
                font-size: 0.9em;
                margin-left: 10px;
            }
            .health-good { background: #d4edda; color: #155724; }
            .health-warning { background: #fff3cd; color: #856404; }
            .health-critical { background: #f8d7da; color: #721c24; }
            .features-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin-bottom: 40px;
            }
            .feature-card {
                background: white;
                padding: 25px;
                border-radius: 15px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
            .feature-card h3 {
                color: #764ba2;
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .feature-card ul {
                list-style: none;
            }
            .feature-card li {
                padding: 8px 0;
                border-bottom: 1px solid #eee;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .feature-card li:before {
                content: "✓";
                color: #28a745;
                font-weight: bold;
            }
            .footer {
                text-align: center;
                margin-top: 40px;
                padding-top: 20px;
                border-top: 2px solid #eee;
                color: #666;
                font-size: 0.9em;
            }
            .btn {
                display: inline-block;
                padding: 12px 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 30px;
                font-weight: bold;
                margin: 10px;
                transition: transform 0.3s, box-shadow 0.3s;
                border: none;
                cursor: pointer;
            }
            .btn:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
            }
            .btn-secondary {
                background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            }
            .action-buttons {
                text-align: center;
                margin: 30px 0;
            }
            @media (max-width: 768px) {
                .container { padding: 15px; }
                .header h1 { font-size: 2em; }
                .stats-grid { grid-template-columns: 1fr; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🎬 Entertainment Tadka Bot</h1>
                <p>Advanced Movie Search & Delivery System</p>
            </div>
            
            <div class="action-buttons">
                <a href="https://t.me/' . ltrim(MAIN_CHANNEL, '@') . '" class="btn" target="_blank">🍿 Join Main Channel</a>
                <a href="https://t.me/' . ltrim(REQUEST_CHANNEL, '@') . '" class="btn btn-secondary" target="_blank">📥 Request Channel</a>
            </div>
            
            <div class="health-status">
                <h2>System Health 
                    <span class="health-indicator health-' . $health['status'] . '">' . strtoupper($health['status']) . '</span>
                </h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
    ';
    
    foreach ($health['checks'] as $check => $status) {
        $status_class = $status == 'exists' || $status == 'writable' || $status == 'normal' ? 'good' : 
                       ($status == 'read-only' || $status == 'high' ? 'warning' : 'critical');
        $html .= '<div style="background: #f8f9fa; padding: 10px; border-radius: 8px;">
                    <div style="font-weight: bold; color: #495057;">' . htmlspecialchars($check) . '</div>
                    <div class="health-' . $status_class . '" style="display: inline-block; padding: 3px 10px; border-radius: 15px; margin-top: 5px; font-size: 0.9em;">
                        ' . htmlspecialchars($status) . '
                    </div>
                </div>';
    }
    
    $html .= '
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>🎬 Total Movies</h3>
                    <div class="value">' . ($stats['total_movies'] ?? 0) . '</div>
                    <div class="label">In Database</div>
                </div>
                
                <div class="stat-card">
                    <h3>👥 Total Users</h3>
                    <div class="value">' . count($users_data['users'] ?? []) . '</div>
                    <div class="label">Registered Users</div>
                </div>
                
                <div class="stat-card">
                    <h3>🔍 Total Searches</h3>
                    <div class="value">' . ($stats['total_searches'] ?? 0) . '</div>
                    <div class="label">All Time</div>
                </div>
                
                <div class="stat-card">
                    <h3>📥 Total Downloads</h3>
                    <div class="value">' . ($stats['total_downloads'] ?? 0) . '</div>
                    <div class="label">Movie Info Sent</div>
                </div>
                
                <div class="stat-card">
                    <h3>⚡ Cache Hits</h3>
                    <div class="value">' . ($cache_data['hits'] ?? 0) . '</div>
                    <div class="label">Performance</div>
                </div>
                
                <div class="stat-card">
                    <h3>⏱️ Uptime</h3>
                    <div class="value">' . get_bot_uptime() . '</div>
                    <div class="label">Continuous Operation</div>
                </div>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <h3>🚀 Core Features</h3>
                    <ul>
                        <li>Smart Movie Search with Fuzzy Matching</li>
                        <li>Duplicate Protection System</li>
                        <li>Auto-Notification for Requested Movies</li>
                        <li>Advanced Pagination & Browsing</li>
                        <li>User Points & Leaderboard System</li>
                        <li>Scheduled Posts & Auto-backup</li>
                    </ul>
                </div>
                
                <div class="feature-card">
                    <h3>🛡️ Security & Safety</h3>
                    <ul>
                        <li>Rate Limiting & Anti-spam</li>
                        <li>User Ban System</li>
                        <li>Duplicate Movie Protection</li>
                        <li>Auto-clean & Data Repair</li>
                        <li>Secure Backup System</li>
                        <li>CSV Locking & Data Safety</li>
                    </ul>
                </div>
                
                <div class="feature-card">
                    <h3>📊 Advanced Systems</h3>
                    <ul>
                        <li>In-Memory Cache (Ultra Fast)</li>
                        <li>Prefix Index for Smart Search</li>
                        <li>Background Auto-tasks</li>
                        <li>Real-time Health Monitoring</li>
                        <li>Detailed Statistics & Logs</li>
                        <li>Admin Dashboard & Controls</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer">
                <p>🤖 <b>Entertainment Tadka Bot v3.0</b> | Running since ' . date('Y-m-d') . '</p>
                <p>📞 Support: ' . REQUEST_CHANNEL . ' | 🎭 Theater: ' . THEATER_CHANNEL . '</p>
                <p style="margin-top: 20px; font-size: 0.8em; color: #999;">
                    Total Code Lines: ~5,000+ | Last Updated: ' . date('Y-m-d H:i:s') . '
                </p>
            </div>
        </div>
        
        <script>
            // Auto-refresh stats every 60 seconds
            setTimeout(() => location.reload(), 60000);
            
            // Add animation to stat cards
            document.addEventListener("DOMContentLoaded", function() {
                const cards = document.querySelectorAll(".stat-card");
                cards.forEach((card, index) => {
                    card.style.animationDelay = (index * 0.1) + "s";
                    card.classList.add("animate__animated", "animate__fadeInUp");
                });
            });
        </script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    </body>
    </html>';
    
    return $html;
}

// ==============================
// MAIN EXECUTION
// ==============================
initialize_all_systems();
run_background_tasks();

$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    // Maintenance mode check
    global $MAINTENANCE_MODE, $MAINTENANCE_MESSAGE;
    if ($MAINTENANCE_MODE && isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        sendMessage($chat_id, $MAINTENANCE_MESSAGE, null, 'HTML');
        bot_log("Maintenance mode active - message blocked from $chat_id");
        exit;
    }

    get_cached_movies();

    // Handle inline queries
    if (isset($update['inline_query'])) {
        handle_inline_query($update['inline_query']);
        exit;
    }

    // Channel post handling
    if (isset($update['channel_post'])) {
        $message = $update['channel_post'];
        $message_id = $message['message_id'];
        $chat_id = $message['chat']['id'];

        if ($chat_id == CHANNEL_ID) {
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
                $result = append_movie_with_protection($text, $message_id, date('d-m-Y'), '', $quality, $size, $language);
                
                // Log duplicate if blocked
                if ($result['status'] == 'duplicate_blocked') {
                    bot_log("Channel post blocked as duplicate: $text");
                }
            }
        }
    }

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

        // Group message filtering
        if ($chat_type !== 'private') {
            if (strpos($text, '/') === 0) {
                // Commands allowed
            } else {
                if (!is_valid_movie_query($text)) {
                    bot_log("Invalid group message blocked from $chat_id: $text");
                    return;
                }
            }
        }

        // Handle commands
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $params = array_slice($parts, 1);
            
            handle_command_v2($chat_id, $user_id, $command, $params);
        } else if (!empty(trim($text))) {
            // Apply spelling correction
            $corrected_text = smart_spelling_correction($text);
            if ($corrected_text != $text) {
                bot_log("Spelling corrected from '$text' to '$corrected_text' for user $user_id");
                $text = $corrected_text;
            }
            
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

        // Handle new callback types
        if (strpos($data, 'random_get_') === 0) {
            $movie_hash = str_replace('random_get_', '', $data);
            deliver_movie_by_hash($chat_id, $movie_hash);
            answerCallbackQuery($query['id'], "Random movie info sent!");
        }
        elseif ($data == 'another_random') {
            $command = '/random';
            $params = [];
            handle_command_v2($chat_id, $user_id, $command, $params);
            answerCallbackQuery($query['id'], "Another random movie!");
        }
        elseif (strpos($data, 'suggest_get_') === 0) {
            $movie_hash = str_replace('suggest_get_', '', $data);
            deliver_movie_by_hash($chat_id, $movie_hash);
            answerCallbackQuery($query['id'], "Suggested movie info sent!");
        }
        elseif ($data == 'download_missed') {
            // Get missed movies and send them
            $users_data = json_decode(file_get_contents(USERS_FILE), true);
            $user = $users_data['users'][$user_id] ?? [];
            $last_active = strtotime($user['last_active'] ?? '2000-01-01');
            
            $movies = get_cached_movies();
            $new_movies = [];
            
            foreach ($movies as $movie) {
                $movie_date = strtotime($movie['date']);
                if ($movie_date > $last_active) {
                    $new_movies[] = $movie;
                }
            }
            
            if (!empty($new_movies)) {
                $new_movies = array_slice($new_movies, -20);
                batch_download_with_progress($chat_id, $new_movies, "missed");
                answerCallbackQuery($query['id'], "Missed movies info being sent!");
            } else {
                answerCallbackQuery($query['id'], "No missed movies found!", true);
            }
        }
        elseif ($data == 'mark_missed_seen') {
            // Update user's last active time
            $users_data = json_decode(file_get_contents(USERS_FILE), true);
            if (isset($users_data['users'][$user_id])) {
                $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
                file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
            }
            answerCallbackQuery($query['id'], "Marked as seen! You're up to date.");
        }
        elseif (strpos($data, 'toggle_setting_') === 0) {
            $setting_key = str_replace('toggle_setting_', '', $data);
            toggle_setting($chat_id, $setting_key);
            answerCallbackQuery($query['id'], "Setting toggled!");
        }
        elseif ($data == 'admin_dashboard') {
            show_admin_dashboard($chat_id);
            answerCallbackQuery($query['id'], "Admin dashboard");
        }
        elseif ($data == 'admin_settings') {
            show_admin_settings_panel($chat_id);
            answerCallbackQuery($query['id'], "Settings panel");
        }
        elseif ($data == 'user_settings') {
            sendMessage($chat_id, "⚙️ <b>User Settings</b>\n\nComing soon! User settings panel will be available in next update.", null, 'HTML');
            answerCallbackQuery($query['id'], "User settings");
        }
        else {
            // Handle existing callbacks
            // ... (existing callback handling code)
        }
    }

    // Check scheduled posts every minute
    if (time() % SCHEDULE_CHECK_INTERVAL == 0) {
        check_scheduled_posts();
    }
}

// Web interface for browser access
if (php_sapi_name() !== 'cli' && empty($update)) {
    if (isset($_GET['setwebhook'])) {
        // Webhook setup page
        $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $webhook_url = str_replace('?setwebhook=1', '', $webhook_url);
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
        
        echo '<p><a href="?" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">Back to Dashboard</a></p>';
    } else {
        // Show web interface
        echo generate_web_interface();
    }
}

// Helper function for delivering movie by hash
function deliver_movie_by_hash($chat_id, $movie_hash) {
    $movies = get_cached_movies();
    
    foreach ($movies as $movie) {
        $current_hash = md5($movie['movie_name'] . $movie['message_id_raw']);
        if ($current_hash == $movie_hash) {
            deliver_item_to_chat($chat_id, $movie);
            update_user_activity($chat_id, 'download');
            return true;
        }
    }
    
    return false;
}
?>
