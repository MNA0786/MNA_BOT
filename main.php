<?php
// ==============================
// SECURITY HEADERS & BASIC SETUP
// ==============================

// Security headers PHP mein set karo - XSS aur security attacks se bachne ke liye
header("X-Content-Type-Options: nosniff");  // MIME type sniffing block karega
header("X-Frame-Options: DENY");  // Clickjacking se bachayega
header("X-XSS-Protection: 1; mode=block");  // XSS attacks block karega
header("Referrer-Policy: strict-origin-when-cross-origin");  // Referrer info secure rakhega

// ==============================
// DELAY TYPING FUNCTIONALITY
// ==============================

function sendMessageWithDelay($chat_id, $text, $reply_markup = null, $parse_mode = null, $delay_ms = 1000) {
    // Message send karta hai with typing delay
    $typing_data = [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ];
    
    // "typing..." action bhejo pehle
    apiRequest('sendChatAction', $typing_data);
    
    // Delay karo
    usleep($delay_ms * 1000);
    
    // Phir actual message bhejo
    return sendMessage($chat_id, $text, $reply_markup, $parse_mode);
}

function editMessageWithDelay($chat_id, $message_id, $new_text, $reply_markup = null, $delay_ms = 500) {
    // Message edit karta hai with delay
    $typing_data = [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ];
    
    // "typing..." action bhejo pehle
    apiRequest('sendChatAction', $typing_data);
    
    // Delay karo
    usleep($delay_ms * 1000);
    
    // Phir actual message edit karo
    return editMessage($chat_id, $message_id, $new_text, $reply_markup);
}

// ==============================
// RENDER.COM SPECIFIC CONFIGURATION
// ==============================

// Render.com provides PORT environment variable
$port = getenv('PORT') ?: '80';  // Port detect karta hai, default 80

// Webhook URL automatically set karo
$webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// Security - All credentials environment variables se lo
if (!getenv('BOT_TOKEN')) {
    die("❌ BOT_TOKEN environment variable set nahi hai. Render.com dashboard mein set karo.");
}

// ==============================
// ENVIRONMENT VARIABLES CONFIGURATION
// ==============================
// Yeh sab variables Render.com ke dashboard mein set karne hain
define('BOT_TOKEN', getenv('BOT_TOKEN'));  // Telegram bot token

// ALL CHANNELS DEFINED HERE
define('MAIN_CHANNEL', '@EntertainmentTadka786');  // Main channel username
define('MAIN_CHANNEL_ID', '-1003251791991');  // Main channel ID (Private Channel Of Movies and Webseries)
define('THEATER_CHANNEL', '@threater_print_movies');  // Theater channel username
define('THEATER_CHANNEL_ID', '-1003614546520');  // Theater channel ID (Forwarded From Any Channel)
define('BACKUP_CHANNEL_USERNAME', '@ETBackup');  // Backup channel username
define('BACKUP_CHANNEL_ID', '-1002337293281');  // Backup Channel of Movies And Webseries 2
define('BACKUP_CHANNEL_2_USERNAME', '@ETBackup');  // Same as above
define('BACKUP_CHANNEL_2_ID', '-1002337293281');  // Same as above
define('REQUEST_CHANNEL', '@EntertainmentTadka7860');  // Request channel username
define('ADMIN_ID', (int)getenv('ADMIN_ID'));  // Admin user ID

// Validate essential environment variables
if (!MAIN_CHANNEL_ID || !THEATER_CHANNEL_ID || !BACKUP_CHANNEL_ID) {
    die("❌ Essential channel IDs environment variables set nahi hain. Render.com dashboard mein set karo.");
}

// File paths - Yeh sab files bot ke saath create hongi
define('CSV_FILE', 'movies.csv');  // Movies database
define('USERS_FILE', 'users.json');  // Users data
define('STATS_FILE', 'bot_stats.json');  // Bot statistics
define('REQUEST_FILE', 'movie_requests.json');  // Movie requests
define('BACKUP_DIR', 'backups/');  // Backup folder
define('LOG_FILE', 'bot_activity.log');  // Activity log

// Constants - Bot ke settings
define('CACHE_EXPIRY', 300);  // 5 minutes cache
define('ITEMS_PER_PAGE', 5);  // Pagination ke liye items per page
define('MAX_SEARCH_RESULTS', 15);  // Maximum search results
define('DAILY_REQUEST_LIMIT', 5);  // Daily movie request limit per user
define('AUTO_BACKUP_HOUR', '03');  // Auto backup time (3 AM)

// ==============================
// ENHANCED PAGINATION CONSTANTS
// ==============================
define('MAX_PAGES_TO_SHOW', 7);          // Max page buttons to display
define('PAGINATION_CACHE_TIMEOUT', 60);  // Cache timeout in seconds
define('PREVIEW_ITEMS', 3);              // Number of items to preview
define('BATCH_SIZE', 5);                 // Batch download size

// ==============================
// QUICK ADD CONFIGURATION
// ==============================
define('QUICKADD_FORMAT', "🎬 <b>Quick Add Format:</b>\n\n" .
    "<code>/quickadd movie_name (year),message_id,channel_username_or_id</code>\n\n" .
    "<b>Examples:</b>\n" .
    "• <code>/quickadd Avengers Endgame (2019),12345,@EntertainmentTadka786</code>\n" .
    "• <code>/quickadd KGF Chapter 2 (2022),67890,@threater_print_movies</code>\n" .
    "• <code>/quickadd Animal (2023),54321,-1003251791991</code>\n" .
    "• <code>/quickadd Pushpa 2 (2024),11111,-1002337293281</code>\n" .
    "• <code>/quickadd Test Movie (2025),22222,-1003614546520</code>\n\n" .
    "<b>Supported Channels:</b>\n" .
    "• @EntertainmentTadka786 (Main)\n" .
    "• @threater_print_movies (Theater)\n" .
    "• @ETBackup (Backup)\n" .
    "• -1003251791991 (Private Channel)\n" .
    "• -1002337293281 (Backup 2)\n" .
    "• -1003614546520 (Any Channel)\n\n" .
    "<b>Note:</b> Ek saath multiple movies add ho jayengi aur CSV file mein store hongi.");

// ==============================
// MAINTENANCE MODE
// ==============================
$MAINTENANCE_MODE = false;  // Agar true hai toh bot maintenance mode mein hoga
$MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>\n\nWe're temporarily unavailable for updates.\nWill be back in few days!\n\nThanks for patience 🙏";

// ==============================
// GLOBAL VARIABLES
// ==============================
$movie_messages = array();  // Movies cache
$movie_cache = array();  // Movies data cache
$waiting_users = array();  // Users waiting for movies
$user_sessions = array();  // User sessions
$user_pagination_sessions = array();  // Enhanced: Pagination sessions
$user_quickadd_sessions = array();  // Quick add sessions

// ==============================
// CHANNEL MAPPING FUNCTIONS
// ==============================
function get_channel_id_by_username($username) {
    // Channel username se channel ID return karta hai
    $username = strtolower(trim($username));
    
    $channel_map = [
        '@entertainmenttadka786' => MAIN_CHANNEL_ID,
        '@threater_print_movies' => THEATER_CHANNEL_ID,
        '@etbackup' => BACKUP_CHANNEL_ID,
        'entertainmenttadka786' => MAIN_CHANNEL_ID,
        'threater_print_movies' => THEATER_CHANNEL_ID,
        'etbackup' => BACKUP_CHANNEL_ID,
    ];
    
    return $channel_map[$username] ?? null;
}

function get_channel_type_by_id($channel_id) {
    // Channel ID se channel type return karta hai
    $channel_id = strval($channel_id);
    
    if ($channel_id == MAIN_CHANNEL_ID) return 'main';
    if ($channel_id == THEATER_CHANNEL_ID) return 'theater';
    if ($channel_id == BACKUP_CHANNEL_ID) return 'backup';
    if ($channel_id == '-1003251791991') return 'private';
    if ($channel_id == '-1002337293281') return 'backup2';
    if ($channel_id == '-1003614546520') return 'any';
    
    return 'other';
}

function get_channel_display_name($channel_type) {
    // Channel type se display name return karta hai
    $names = [
        'main' => '🍿 Main Channel',
        'theater' => '🎭 Theater Prints',
        'backup' => '🔒 Backup Channel',
        'private' => '🔐 Private Channel',
        'backup2' => '💾 Backup 2',
        'any' => '📡 Any Channel',
        'other' => '📢 Other Channel'
    ];
    
    return $names[$channel_type] ?? '📢 Unknown Channel';
}

// ==============================
// HELPER FUNCTION FOR DIRECT LINKS
// ==============================
function get_direct_channel_link($message_id, $channel_id) {
    // Telegram direct link generate karta hai
    if (empty($channel_id)) {
        return "Channel ID not available";
    }
    
    $channel_id_clean = str_replace('-100', '', $channel_id);
    return "https://t.me/c/" . $channel_id_clean . "/" . $message_id;
}

function get_channel_username_link($channel_type) {
    // Channel type se link generate karta hai
    switch ($channel_type) {
        case 'main':
            return "https://t.me/" . ltrim(MAIN_CHANNEL, '@');
        case 'theater':
            return "https://t.me/" . ltrim(THEATER_CHANNEL, '@');
        case 'backup':
        case 'backup2':
            return "https://t.me/" . ltrim(BACKUP_CHANNEL_USERNAME, '@');
        default:
            return "https://t.me/EntertainmentTadka786";
    }
}

// ==============================
// FILE INITIALIZATION FUNCTION
// ==============================
function initialize_files() {
    // Sab required files create karta hai agar nahi hain toh
    $files = [
        CSV_FILE => "movie_name,message_id,date,video_path,quality,size,language,channel_type,channel_id,channel_username\n",  // CSV header updated with channel info
        USERS_FILE => json_encode([
            'users' => [],  // Users ka data
            'total_requests' => 0,  // Total requests count
            'message_logs' => [],  // Message logs
            'daily_stats' => []  // Daily statistics
        ], JSON_PRETTY_PRINT),
        STATS_FILE => json_encode([
            'total_movies' => 0,  // Total movies count
            'total_users' => 0,  // Total users count
            'total_searches' => 0,  // Total searches
            'total_downloads' => 0,  // Total downloads
            'successful_searches' => 0,  // Successful searches
            'failed_searches' => 0,  // Failed searches
            'daily_activity' => [],  // Daily activity data
            'last_updated' => date('Y-m-d H:i:s')  // Last updated timestamp
        ], JSON_PRETTY_PRINT),
        REQUEST_FILE => json_encode([
            'requests' => [],  // Movie requests
            'pending_approval' => [],  // Pending requests
            'completed_requests' => [],  // Completed requests
            'user_request_count' => []  // User request counts
        ], JSON_PRETTY_PRINT)
    ];
    
    // Har file ko check karo aur create karo agar nahi hai
    foreach ($files as $file => $content) {
        if (!file_exists($file)) {
            file_put_contents($file, $content);
            @chmod($file, 0666);  // Read/write permissions
        }
    }
    
    // Backup directory create karo
    if (!file_exists(BACKUP_DIR)) {
        @mkdir(BACKUP_DIR, 0777, true);  // Full permissions
    }
    
    // Log file create karo
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
    // Bot activities ko log karta hai
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

// ==============================
// CACHING SYSTEM
// ==============================
function get_cached_movies() {
    global $movie_cache;
    
    // Cache check karo - 5 minutes se zyada purana toh refresh karo
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < CACHE_EXPIRY) {
        return $movie_cache['data'];  // Cache hit
    }
    
    // Cache miss - reload data from CSV
    $movie_cache = [
        'data' => load_and_clean_csv(),
        'timestamp' => time()
    ];
    
    bot_log("Movie cache refreshed - " . count($movie_cache['data']) . " movies");
    return $movie_cache['data'];
}

// ==============================
// CSV MANAGEMENT FUNCTIONS
// ==============================
function load_and_clean_csv($filename = CSV_FILE) {
    global $movie_messages;
    
    // CSV file check karo, agar nahi hai toh create karo
    if (!file_exists($filename)) {
        file_put_contents($filename, "movie_name,message_id,date,video_path,quality,size,language,channel_type,channel_id,channel_username\n");
        return [];
    }

    $data = [];
    $handle = fopen($filename, "r");
    if ($handle !== FALSE) {
        $header = fgetcsv($handle);  // Header read karo
        
        // Har row ko process karo
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && (!empty(trim($row[0])))) {
                $movie_name = trim($row[0]);
                $message_id_raw = isset($row[1]) ? trim($row[1]) : '';
                $date = isset($row[2]) ? trim($row[2]) : '';
                $video_path = isset($row[3]) ? trim($row[3]) : '';
                $quality = isset($row[4]) ? trim($row[4]) : 'Unknown';
                $size = isset($row[5]) ? trim($row[5]) : 'Unknown';
                $language = isset($row[6]) ? trim($row[6]) : 'Hindi';
                $channel_type = isset($row[7]) ? trim($row[7]) : 'main';  // main, theater, backup, private, etc.
                $channel_id = isset($row[8]) ? trim($row[8]) : '';
                $channel_username = isset($row[9]) ? trim($row[9]) : '';

                // Channel type agar empty hai toh determine karo channel ID se
                if (empty($channel_type) && !empty($channel_id)) {
                    $channel_type = get_channel_type_by_id($channel_id);
                }

                // Movie entry create karo
                $entry = [
                    'movie_name' => $movie_name,
                    'message_id_raw' => $message_id_raw,
                    'date' => $date,
                    'video_path' => $video_path,
                    'quality' => $quality,
                    'size' => $size,
                    'language' => $language,
                    'channel_type' => $channel_type,
                    'channel_id' => $channel_id,
                    'channel_username' => $channel_username,
                    'source_channel' => $channel_id
                ];
                
                // Message ID numeric check karo
                if (is_numeric($message_id_raw)) {
                    $entry['message_id'] = intval($message_id_raw);
                } else {
                    $entry['message_id'] = null;
                }

                $data[] = $entry;

                // Global movie messages array mein add karo
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

    // CSV clean karo aur rewrite karo
    $handle = fopen($filename, "w");
    fputcsv($handle, array('movie_name','message_id','date','video_path','quality','size','language','channel_type','channel_id','channel_username'));
    foreach ($data as $row) {
        fputcsv($handle, [
            $row['movie_name'], 
            $row['message_id_raw'], 
            $row['date'], 
            $row['video_path'],
            $row['quality'],
            $row['size'],
            $row['language'],
            $row['channel_type'],
            $row['channel_id'],
            $row['channel_username']
        ]);
    }
    fclose($handle);

    bot_log("CSV cleaned and reloaded - " . count($data) . " entries");
    return $data;
}

// ==============================
// QUICK ADD MOVIE FUNCTIONS (ENHANCED FOR ALL CHANNELS)
// ==============================
function quick_add_movie($chat_id, $user_id, $input_data) {
    // Quick add movie function with format: movie_name (year),message_id,channel_username_or_id
    global $movie_messages, $movie_cache;
    
    // Permission check - only admin can add movies
    if ($chat_id != ADMIN_ID && $user_id != ADMIN_ID) {
        sendMessageWithDelay($chat_id, "❌ Access denied. Admin only command.", null, 'HTML', 1000);
        return;
    }
    
    // Parse input data
    $parts = explode(',', $input_data, 3);
    
    if (count($parts) < 3) {
        sendMessageWithDelay($chat_id, QUICKADD_FORMAT, null, 'HTML', 1000);
        return;
    }
    
    $movie_name = trim($parts[0]);
    $message_id = trim($parts[1]);
    $channel_info = trim($parts[2]);
    
    // Validate message ID
    if (!is_numeric($message_id)) {
        sendMessageWithDelay($chat_id, "❌ Invalid message ID. Please provide a numeric message ID.", null, 'HTML', 1000);
        return;
    }
    
    // Determine channel type, ID and username
    $channel_type = 'other';
    $channel_id = '';
    $channel_username = '';
    
    if (strpos($channel_info, '@') === 0) {
        // Channel username se
        $channel_username = $channel_info;
        $channel_id = get_channel_id_by_username($channel_username);
        
        if ($channel_id) {
            $channel_type = get_channel_type_by_id($channel_id);
        } else {
            // Unknown username
            $channel_type = 'other';
        }
    } elseif (is_numeric($channel_info) || (strpos($channel_info, '-100') === 0)) {
        // Channel ID se
        $channel_id = $channel_info;
        $channel_type = get_channel_type_by_id($channel_id);
        
        // Channel username set karo based on type
        switch ($channel_type) {
            case 'main':
                $channel_username = MAIN_CHANNEL;
                break;
            case 'theater':
                $channel_username = THEATER_CHANNEL;
                break;
            case 'backup':
            case 'backup2':
                $channel_username = BACKUP_CHANNEL_USERNAME;
                break;
            case 'private':
                $channel_username = '@private_channel'; // Placeholder
                break;
            case 'any':
                $channel_username = '@any_channel'; // Placeholder
                break;
            default:
                $channel_username = '';
        }
    } else {
        sendMessageWithDelay($chat_id, "❌ Invalid channel format. Use @username or channel ID.", null, 'HTML', 1000);
        return;
    }
    
    // Auto-detect quality from movie name
    $quality = 'Unknown';
    $quality_patterns = [
        '1080p' => ['1080p', '1080', 'fhd', 'full hd'],
        '720p' => ['720p', '720', 'hd'],
        '480p' => ['480p', '480', 'sd'],
        'theater' => ['theater', 'theatre', 'print', 'hdcam', 'camrip', 'hdrip', 'webrip']
    ];
    
    $movie_name_lower = strtolower($movie_name);
    foreach ($quality_patterns as $q => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($movie_name_lower, $pattern) !== false) {
                $quality = $q;
                break 2;
            }
        }
    }
    
    // Auto-detect language
    $language = 'Hindi';
    $language_patterns = [
        'English' => ['english', 'eng', 'en'],
        'Hindi' => ['hindi', 'hin', 'hd'],
        'Tamil' => ['tamil', 'tam'],
        'Telugu' => ['telugu', 'tel'],
        'Malayalam' => ['malayalam', 'mal'],
        'Kannada' => ['kannada', 'kan'],
        'Bengali' => ['bengali', 'ben'],
        'Punjabi' => ['punjabi', 'pun']
    ];
    
    foreach ($language_patterns as $lang => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($movie_name_lower, $pattern) !== false) {
                $language = $lang;
                break 2;
            }
        }
    }
    
    // Auto-detect size (placeholder)
    $size = 'Unknown';
    $size_patterns = [
        '1.5GB' => ['1.5gb', '1.5 gb', '1500mb'],
        '2.0GB' => ['2.0gb', '2 gb', '2000mb'],
        '2.5GB' => ['2.5gb', '2.5 gb', '2500mb'],
        '3.0GB' => ['3.0gb', '3 gb', '3000mb'],
        '500MB' => ['500mb', '0.5gb', '500 mb'],
        '700MB' => ['700mb', '0.7gb', '700 mb'],
        '1.0GB' => ['1gb', '1.0gb', '1000mb']
    ];
    
    foreach ($size_patterns as $sz => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($movie_name_lower, $pattern) !== false) {
                $size = $sz;
                break 2;
            }
        }
    }
    
    // Add movie to CSV
    $entry = [
        $movie_name,
        $message_id,
        date('d-m-Y'),
        '',  // video_path
        $quality,
        $size,
        $language,
        $channel_type,
        $channel_id,
        $channel_username
    ];
    
    $handle = fopen(CSV_FILE, "a");
    if ($handle !== FALSE) {
        fputcsv($handle, $entry);
        fclose($handle);
        
        // Update cache
        $movie_item = [
            'movie_name' => $movie_name,
            'message_id_raw' => $message_id,
            'date' => date('d-m-Y'),
            'video_path' => '',
            'quality' => $quality,
            'size' => $size,
            'language' => $language,
            'channel_type' => $channel_type,
            'channel_id' => $channel_id,
            'channel_username' => $channel_username,
            'message_id' => intval($message_id),
            'source_channel' => $channel_id
        ];
        
        // Global cache mein add karo
        $movie_key = strtolower($movie_name);
        if (!isset($movie_messages[$movie_key])) {
            $movie_messages[$movie_key] = [];
        }
        $movie_messages[$movie_key][] = $movie_item;
        $movie_cache = [];
        
        // Statistics update karo
        update_stats('total_movies', 1);
        
        // Success message with storage location
        $success_msg = "✅ <b>Movie Added Successfully!</b>\n\n";
        $success_msg .= "🎬 <b>Movie:</b> $movie_name\n";
        $success_msg .= "🆔 <b>Message ID:</b> $message_id\n";
        $success_msg .= "📊 <b>Quality:</b> $quality\n";
        $success_msg .= "💾 <b>Size:</b> $size\n";
        $success_msg .= "🗣️ <b>Language:</b> $language\n";
        $success_msg .= "🎭 <b>Channel Type:</b> " . get_channel_display_name($channel_type) . "\n";
        
        if ($channel_username) {
            $success_msg .= "📢 <b>Channel:</b> $channel_username\n";
        }
        
        if ($channel_id) {
            $success_msg .= "🔢 <b>Channel ID:</b> $channel_id\n";
        }
        
        $success_msg .= "\n💾 <b>Storage Location:</b>\n";
        $success_msg .= "• File: <code>" . CSV_FILE . "</code>\n";
        $success_msg .= "• Path: <code>" . realpath(CSV_FILE) . "</code>\n";
        $success_msg .= "• Size: " . round(filesize(CSV_FILE) / 1024, 2) . " KB\n";
        $success_msg .= "• Total Movies: " . (get_stats()['total_movies'] ?? 0) . "\n\n";
        
        $success_msg .= "🔗 <b>Direct Link:</b>\n";
        if ($channel_id && $message_id) {
            $success_msg .= get_direct_channel_link($message_id, $channel_id) . "\n\n";
        }
        
        $success_msg .= "📊 Movie database updated successfully!";
        
        sendMessageWithDelay($chat_id, $success_msg, null, 'HTML', 1500);
        bot_log("Movie quick added by $user_id: $movie_name (ID: $message_id) to $channel_type channel ($channel_id)");
        
    } else {
        sendMessageWithDelay($chat_id, "❌ Failed to add movie. CSV file cannot be opened.", null, 'HTML', 1000);
    }
}

function batch_quick_add_movies($chat_id, $user_id, $movies_data) {
    // Multiple movies ek saath add karne ke liye
    global $movie_messages, $movie_cache;
    
    // Permission check - only admin can add movies
    if ($chat_id != ADMIN_ID && $user_id != ADMIN_ID) {
        sendMessageWithDelay($chat_id, "❌ Access denied. Admin only command.", null, 'HTML', 1000);
        return;
    }
    
    $movies_list = explode("\n", trim($movies_data));
    $added_count = 0;
    $failed_count = 0;
    $added_movies = [];
    
    // Progress message
    $progress_msg = sendMessage($chat_id, "⏳ Adding " . count($movies_list) . " movies...\n\nProcessing: 0%");
    
    foreach ($movies_list as $index => $movie_line) {
        $movie_line = trim($movie_line);
        if (empty($movie_line)) continue;
        
        // Parse movie data
        $parts = explode(',', $movie_line, 3);
        
        if (count($parts) < 3) {
            $failed_count++;
            continue;
        }
        
        $movie_name = trim($parts[0]);
        $message_id = trim($parts[1]);
        $channel_info = trim($parts[2]);
        
        // Validate message ID
        if (!is_numeric($message_id)) {
            $failed_count++;
            continue;
        }
        
        // Determine channel type and ID
        $channel_type = 'other';
        $channel_id = '';
        $channel_username = '';
        
        if (strpos($channel_info, '@') === 0) {
            $channel_username = $channel_info;
            $channel_id = get_channel_id_by_username($channel_username);
            
            if ($channel_id) {
                $channel_type = get_channel_type_by_id($channel_id);
            } else {
                $channel_type = 'other';
            }
        } elseif (is_numeric($channel_info) || (strpos($channel_info, '-100') === 0)) {
            $channel_id = $channel_info;
            $channel_type = get_channel_type_by_id($channel_id);
            
            // Channel username set karo based on type
            switch ($channel_type) {
                case 'main':
                    $channel_username = MAIN_CHANNEL;
                    break;
                case 'theater':
                    $channel_username = THEATER_CHANNEL;
                    break;
                case 'backup':
                case 'backup2':
                    $channel_username = BACKUP_CHANNEL_USERNAME;
                    break;
                case 'private':
                    $channel_username = '@private_channel';
                    break;
                case 'any':
                    $channel_username = '@any_channel';
                    break;
                default:
                    $channel_username = '';
            }
        } else {
            $failed_count++;
            continue;
        }
        
        // Auto-detect quality
        $quality = 'Unknown';
        $movie_name_lower = strtolower($movie_name);
        $quality_patterns = [
            '1080p' => ['1080p', '1080', 'fhd', 'full hd'],
            '720p' => ['720p', '720', 'hd'],
            '480p' => ['480p', '480', 'sd'],
            'theater' => ['theater', 'theatre', 'print', 'hdcam', 'camrip', 'hdrip']
        ];
        
        foreach ($quality_patterns as $q => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($movie_name_lower, $pattern) !== false) {
                    $quality = $q;
                    break 2;
                }
            }
        }
        
        // Auto-detect language
        $language = 'Hindi';
        $language_patterns = [
            'English' => ['english', 'eng', 'en'],
            'Hindi' => ['hindi', 'hin', 'hd'],
            'Tamil' => ['tamil', 'tam'],
            'Telugu' => ['telugu', 'tel'],
            'Malayalam' => ['malayalam', 'mal'],
            'Kannada' => ['kannada', 'kan']
        ];
        
        foreach ($language_patterns as $lang => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($movie_name_lower, $pattern) !== false) {
                    $language = $lang;
                    break 2;
                }
            }
        }
        
        // Auto-detect size
        $size = 'Unknown';
        $size_patterns = [
            '1.5GB' => ['1.5gb', '1.5 gb', '1500mb'],
            '2.0GB' => ['2.0gb', '2 gb', '2000mb'],
            '2.5GB' => ['2.5gb', '2.5 gb', '2500mb'],
            '3.0GB' => ['3.0gb', '3 gb', '3000mb'],
            '500MB' => ['500mb', '0.5gb', '500 mb']
        ];
        
        foreach ($size_patterns as $sz => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($movie_name_lower, $pattern) !== false) {
                    $size = $sz;
                    break 2;
                }
            }
        }
        
        // Add to CSV
        $entry = [
            $movie_name,
            $message_id,
            date('d-m-Y'),
            '',
            $quality,
            $size,
            $language,
            $channel_type,
            $channel_id,
            $channel_username
        ];
        
        $handle = fopen(CSV_FILE, "a");
        if ($handle !== FALSE) {
            fputcsv($handle, $entry);
            fclose($handle);
            
            // Update cache
            $movie_item = [
                'movie_name' => $movie_name,
                'message_id_raw' => $message_id,
                'date' => date('d-m-Y'),
                'video_path' => '',
                'quality' => $quality,
                'size' => $size,
                'language' => $language,
                'channel_type' => $channel_type,
                'channel_id' => $channel_id,
                'channel_username' => $channel_username,
                'message_id' => intval($message_id),
                'source_channel' => $channel_id
            ];
            
            $movie_key = strtolower($movie_name);
            if (!isset($movie_messages[$movie_key])) {
                $movie_messages[$movie_key] = [];
            }
            $movie_messages[$movie_key][] = $movie_item;
            
            $added_count++;
            $added_movies[] = [
                'name' => $movie_name,
                'channel' => get_channel_display_name($channel_type)
            ];
            
            // Update progress every 5 movies
            if (($index + 1) % 5 == 0 || ($index + 1) == count($movies_list)) {
                $progress = round((($index + 1) / count($movies_list)) * 100);
                editMessage($chat_id, $progress_msg['result']['message_id'], 
                    "⏳ Adding " . count($movies_list) . " movies...\n\n" .
                    "Progress: $progress%\n" .
                    "Processed: " . ($index + 1) . "/" . count($movies_list) . "\n" .
                    "✅ Added: $added_count\n" .
                    "❌ Failed: $failed_count"
                );
            }
            
            usleep(500000); // 0.5 second delay between adds
            
        } else {
            $failed_count++;
        }
    }
    
    // Clear cache after batch add
    $movie_cache = [];
    
    // Update statistics
    update_stats('total_movies', $added_count);
    
    // Final result message
    $result_msg = "✅ <b>Batch Add Complete!</b>\n\n";
    $result_msg .= "📊 <b>Results:</b>\n";
    $result_msg .= "• Total Processed: " . count($movies_list) . "\n";
    $result_msg .= "• ✅ Successfully Added: $added_count\n";
    $result_msg .= "• ❌ Failed: $failed_count\n\n";
    
    $result_msg .= "💾 <b>Storage Location:</b>\n";
    $result_msg .= "• File: <code>" . CSV_FILE . "</code>\n";
    $result_msg .= "• Path: <code>" . realpath(CSV_FILE) . "</code>\n";
    $result_msg .= "• Size: " . round(filesize(CSV_FILE) / 1024, 2) . " KB\n";
    $result_msg .= "• Total Movies: " . (get_stats()['total_movies'] ?? 0) . "\n\n";
    
    if ($added_count > 0) {
        $result_msg .= "🎬 <b>Added Movies:</b>\n";
        $display_count = min(10, count($added_movies));
        for ($i = 0; $i < $display_count; $i++) {
            $result_msg .= ($i + 1) . ". " . $added_movies[$i]['name'] . "\n";
            $result_msg .= "   📢 " . $added_movies[$i]['channel'] . "\n\n";
        }
        
        if (count($added_movies) > 10) {
            $result_msg .= "... and " . (count($added_movies) - 10) . " more\n";
        }
    }
    
    editMessage($chat_id, $progress_msg['result']['message_id'], $result_msg, null, 'HTML');
    
    // Log the batch add
    bot_log("Batch quick add by $user_id: $added_count movies added, $failed_count failed");
}

function show_quickadd_format($chat_id) {
    // Quick add format show karta hai
    sendMessageWithDelay($chat_id, QUICKADD_FORMAT, null, 'HTML', 1000);
}

// ==============================
// TELEGRAM API FUNCTIONS
// ==============================
function apiRequest($method, $params = array(), $is_multipart = false) {
    // Telegram API ko call karta hai
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    if ($is_multipart) {
        // Files upload ke liye (multipart form data)
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
        // Normal API requests ke liye
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
    // Telegram message send karta hai
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true  // Link preview disable karta hai
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    
    $result = apiRequest('sendMessage', $data);
    bot_log("Message sent to $chat_id: " . substr($text, 0, 50) . "...");
    return json_decode($result, true);
}

function editMessage($chat_id, $message_id, $new_text, $reply_markup = null) {
    // Existing message edit karta hai
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
    // Message delete karta hai
    apiRequest('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
    // Callback query reply karta hai
    $data = [
        'callback_query_id' => $callback_query_id,
        'show_alert' => $show_alert
    ];
    if ($text) $data['text'] = $text;
    apiRequest('answerCallbackQuery', $data);
}

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    // Message forward karta hai
    $result = apiRequest('forwardMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
    return $result;
}

function copyMessage($chat_id, $from_chat_id, $message_id) {
    // Message copy karta hai (forward nahi dikhata)
    return apiRequest('copyMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

// ==============================
// MOVIE DELIVERY SYSTEM - ALL CHANNELS SUPPORT
// ==============================
function deliver_item_to_chat($chat_id, $item) {
    // Movie user ko deliver karta hai - WITHOUT FORWARD HEADERS
    // Item mein channel_id aur message_id hona chahiye
    
    if (!isset($item['channel_id']) || empty($item['channel_id'])) {
        // Agar channel ID nahi hai, fallback karo
        $source_channel = MAIN_CHANNEL_ID;
        bot_log("Channel ID not found for movie: {$item['movie_name']}, using default", 'WARNING');
    } else {
        $source_channel = $item['channel_id'];
    }
    
    $channel_type = isset($item['channel_type']) ? $item['channel_type'] : 'main';
    
    // Agar valid message ID hai toh COPY KARO
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        // COPY MESSAGE use karo - yeh forward header nahi dikhayega
        $result = json_decode(copyMessage($chat_id, $source_channel, $item['message_id']), true);
        
        if ($result && $result['ok']) {
            update_stats('total_downloads', 1);
            bot_log("Movie COPIED from $channel_type: {$item['movie_name']} to $chat_id");
            return true;
        } else {
            // Copy nahi ho paya toh forward try karo as fallback
            $fallback_result = json_decode(forwardMessage($chat_id, $source_channel, $item['message_id']), true);
            
            if ($fallback_result && $fallback_result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("Movie FORWARDED from $channel_type: {$item['movie_name']} to $chat_id");
                return true;
            }
        }
    }
    
    // Agar message ID nahi hai ya numeric nahi hai
    if (!empty($item['message_id_raw'])) {
        // Raw message ID se try karo
        $message_id_clean = preg_replace('/[^0-9]/', '', $item['message_id_raw']);
        if (is_numeric($message_id_clean) && $message_id_clean > 0) {
            // Pehle copy try karo
            $result = json_decode(copyMessage($chat_id, $source_channel, $message_id_clean), true);
            
            if ($result && $result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("Movie COPIED (raw ID) from $channel_type: {$item['movie_name']} to $chat_id");
                return true;
            } else {
                // Fallback to forward
                $fallback_result = json_decode(forwardMessage($chat_id, $source_channel, $message_id_clean), true);
                
                if ($fallback_result && $fallback_result['ok']) {
                    update_stats('total_downloads', 1);
                    bot_log("Movie FORWARDED (raw ID) from $channel_type: {$item['movie_name']} to $chat_id");
                    return true;
                }
            }
        }
    }

    // Agar koi bhi method kaam na kare toh text info bhejo (NO FORWARD)
    $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n";
    $text .= "📊 Quality: " . htmlspecialchars($item['quality'] ?? 'Unknown') . "\n";
    $text .= "💾 Size: " . htmlspecialchars($item['size'] ?? 'Unknown') . "\n";
    $text .= "🗣️ Language: " . htmlspecialchars($item['language'] ?? 'Hindi') . "\n";
    $text .= "🎭 Channel: " . get_channel_display_name($channel_type) . "\n";
    $text .= "📅 Date: " . htmlspecialchars($item['date'] ?? 'N/A') . "\n";
    $text .= "📎 Reference: " . htmlspecialchars($item['message_id_raw'] ?? 'N/A') . "\n\n";
    
    // Direct link provide karo (forward nahi)
    if (!empty($item['message_id']) && is_numeric($item['message_id']) && !empty($source_channel)) {
        $text .= "🔗 Direct Link: " . get_direct_channel_link($item['message_id'], $source_channel) . "\n\n";
    }
    
    $text .= "⚠️ Join channel to access content: " . get_channel_username_link($channel_type);
    
    sendMessage($chat_id, $text, null, 'HTML');
    update_stats('total_downloads', 1);
    return false;
}

// ==============================
// STATISTICS SYSTEM
// ==============================
function update_stats($field, $increment = 1) {
    // Statistics update karta hai
    if (!file_exists(STATS_FILE)) return;
    
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats[$field] = ($stats[$field] ?? 0) + $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    
    // Daily activity update karo
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
    // Statistics return karta hai
    if (!file_exists(STATS_FILE)) return [];
    return json_decode(file_get_contents(STATS_FILE), true);
}

// ==============================
// USER MANAGEMENT
// ==============================
function update_user_data($user_id, $user_info = []) {
    // User data update/create karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id])) {
        // New user create karo
        $users_data['users'][$user_id] = [
            'first_name' => $user_info['first_name'] ?? '',
            'last_name' => $user_info['last_name'] ?? '',
            'username' => $user_info['username'] ?? '',
            'joined' => date('Y-m-d H:i:s'),
            'last_active' => date('Y-m-d H:i:s'),
            'points' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
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
    // User activity aur points update karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (isset($users_data['users'][$user_id])) {
        $points_map = [
            'search' => 1,
            'found_movie' => 5,
            'daily_login' => 10,
            'movie_request' => 2,
            'download' => 3
        ];
        
        $users_data['users'][$user_id]['points'] += ($points_map[$action] ?? 0);
        
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
    
    // Har movie ke against query match karo
    foreach ($movie_messages as $movie => $entries) {
        $score = 0;
        
        // Channel type matching
        foreach ($entries as $entry) {
            $entry_channel_type = $entry['channel_type'] ?? 'main';
            
            if ($is_theater_search && $entry_channel_type == 'theater') {
                $score += 20;  // Theater search ko theater movies ka bonus
            } elseif (!$is_theater_search && $entry_channel_type == 'main') {
                $score += 10;  // Normal search ko main channel movies ka bonus
            }
            
            // Backup channels ko bhi include karo
            if (in_array($entry_channel_type, ['backup', 'backup2', 'private', 'any'])) {
                $score += 5;
            }
        }
        
        // 1. Exact match check karo
        if ($movie == $query_lower) {
            $score = 100;
        }
        // 2. Partial match check karo
        elseif (strpos($movie, $query_lower) !== false) {
            $score = 80 - (strlen($movie) - strlen($query_lower));
        }
        // 3. Similarity match check karo
        else {
            similar_text($movie, $query_lower, $similarity);
            if ($similarity > 60) $score = $similarity;
        }
        
        // Quality aur language ke liye bonus points
        foreach ($entries as $entry) {
            if (stripos($entry['quality'] ?? '', '1080') !== false) $score += 5;
            if (stripos($entry['quality'] ?? '', '720') !== false) $score += 3;
            if (stripos($entry['language'] ?? '', 'hindi') !== false) $score += 2;
        }
        
        if ($score > 0) {
            $channel_types = array_column($entries, 'channel_type');
            $results[$movie] = [
                'score' => $score,
                'count' => count($entries),
                'latest_entry' => end($entries),
                'qualities' => array_unique(array_column($entries, 'quality')),
                'has_theater' => in_array('theater', $channel_types),
                'has_main' => in_array('main', $channel_types),
                'has_backup' => in_array('backup', $channel_types) || in_array('backup2', $channel_types),
                'has_private' => in_array('private', $channel_types),
                'has_any' => in_array('any', $channel_types),
                'all_channels' => array_unique($channel_types)
            ];
        }
    }
    
    // Score ke hisab se sort karo (descending)
    uasort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    // Maximum results return karo
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
}

function detect_language($text) {
    // Text ki language detect karta hai (Hindi/English)
    $hindi_keywords = ['फिल्म', 'मूवी', 'डाउनलोड', 'हिंदी', 'चाहिए', 'कहाँ', 'कैसे', 'खोज', 'तलाश'];
    $english_keywords = ['movie', 'download', 'watch', 'print', 'search', 'find', 'looking', 'want', 'need'];
    
    $hindi_score = 0;
    $english_score = 0;
    
    // Hindi keywords check karo
    foreach ($hindi_keywords as $k) {
        if (strpos($text, $k) !== false) $hindi_score++;
    }
    
    // English keywords check karo
    foreach ($english_keywords as $k) {
        if (stripos($text, $k) !== false) $english_score++;
    }
    
    // Hindi characters detect karo
    $hindi_chars = preg_match('/[\x{0900}-\x{097F}]/u', $text);
    if ($hindi_chars) $hindi_score += 3;
    
    return $hindi_score > $english_score ? 'hindi' : 'english';
}

function send_multilingual_response($chat_id, $message_type, $language) {
    // Language ke hisab se response send karta hai
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
    
    sendMessage($chat_id, $responses[$language][$message_type]);
}

function advanced_search($chat_id, $query, $user_id = null) {
    global $movie_messages, $waiting_users;
    $q = strtolower(trim($query));
    
    // Minimum length check
    if (strlen($q) < 2) {
        sendMessage($chat_id, "❌ Please enter at least 2 characters for search");
        return;
    }
    
    // Invalid keywords filter - technical queries block karega
    $invalid_keywords = [
        'vlc', 'audio', 'track', 'change', 'open', 'kar', 'me', 'hai',
        'how', 'what', 'problem', 'issue', 'help', 'solution', 'fix',
        'error', 'not working', 'download', 'play', 'video', 'sound',
        'subtitle', 'quality', 'hd', 'full', 'part', 'scene',
        'hi', 'hello', 'hey', 'good', 'morning', 'night', 'bye',
        'thanks', 'thank', 'ok', 'okay', 'yes', 'no', 'maybe',
        'who', 'when', 'where', 'why', 'how', 'can', 'should',
        'kaise', 'kya', 'kahan', 'kab', 'kyun', 'kon', 'kisne',
        'hai', 'hain', 'ho', 'raha', 'raha', 'rah', 'tha', 'thi',
        'mere', 'apne', 'tumhare', 'hamare', 'sab', 'log', 'group'
    ];
    
    // Smart word analysis
    $query_words = explode(' ', $q);
    $total_words = count($query_words);
    
    $invalid_count = 0;
    foreach ($query_words as $word) {
        if (in_array($word, $invalid_keywords)) {
            $invalid_count++;
        }
    }
    
    // Stricter threshold - agar 50% se zyada invalid words toh block karo
    if ($invalid_count > 0 && ($invalid_count / $total_words) > 0.5) {
        $help_msg = "🎬 Please enter a movie name!\n\n";
        $help_msg .= "🔍 Examples of valid movie names:\n";
        $help_msg .= "• kgf\n• pushpa\n• avengers\n• hindi movie\n• spider-man\n\n";
        $help_msg .= "❌ Technical queries like 'vlc', 'audio track', etc. are not movie names.\n\n";
        $help_msg .= "📢 Join: " . MAIN_CHANNEL . "\n";
        $help_msg .= "💬 Help: " . REQUEST_CHANNEL;
        sendMessage($chat_id, $help_msg, null, 'HTML');
        return;
    }
    
    // Movie name pattern validation
    $movie_pattern = '/^[a-zA-Z0-9\s\-\.\,\&\+\(\)\:\'\"]+$/';
    if (!preg_match($movie_pattern, $query)) {
        sendMessage($chat_id, "❌ Invalid movie name format. Only letters, numbers, and basic punctuation allowed.");
        return;
    }
    
    // Search karo
    $found = smart_search($q);
    
    if (!empty($found)) {
        // Movies mil gayi
        update_stats('successful_searches', 1);
        
        $msg = "🔍 Found " . count($found) . " movies for '$query':\n\n";
        $i = 1;
        foreach ($found as $movie => $data) {
            $quality_info = !empty($data['qualities']) ? implode('/', $data['qualities']) : 'Unknown';
            $channel_info = "";
            if ($data['has_theater']) $channel_info .= "🎭 ";
            if ($data['has_main']) $channel_info .= "🍿 ";
            if ($data['has_backup']) $channel_info .= "🔒 ";
            if ($data['has_private']) $channel_info .= "🔐 ";
            if ($data['has_any']) $channel_info .= "📡 ";
            $msg .= "$i. $movie ($channel_info" . $data['count'] . " versions, $quality_info)\n";
            $i++;
            if ($i > 10) break;
        }
        
        sendMessage($chat_id, $msg);
        
        // Inline keyboard banayega top matches ke liye
        $keyboard = ['inline_keyboard' => []];
        $top_movies = array_slice(array_keys($found), 0, 5);
        
        foreach ($top_movies as $movie) {
            $movie_data = $found[$movie];
            $channel_icon = '🍿';
            if ($movie_data['has_theater']) $channel_icon = '🎭';
            elseif ($movie_data['has_backup']) $channel_icon = '🔒';
            elseif ($movie_data['has_private']) $channel_icon = '🔐';
            elseif ($movie_data['has_any']) $channel_icon = '📡';
            
            $keyboard['inline_keyboard'][] = [[ 
                'text' => $channel_icon . ucwords($movie), 
                'callback_data' => $movie 
            ]];
        }
        
        // Request button add karo
        $keyboard['inline_keyboard'][] = [[
            'text' => "📝 Request Different Movie", 
            'callback_data' => 'request_movie'
        ]];
        
        sendMessage($chat_id, "🚀 Top matches (click for info):", $keyboard);
        
        if ($user_id) {
            update_user_activity($user_id, 'found_movie');
            update_user_activity($user_id, 'search');
        }
        
    } else {
        // Movies nahi mili
        update_stats('failed_searches', 1);
        $lang = detect_language($query);
        send_multilingual_response($chat_id, 'not_found', $lang);
        
        // Auto-suggest request
        $request_keyboard = [
            'inline_keyboard' => [[
                ['text' => '📝 Request This Movie', 'callback_data' => 'auto_request_' . base64_encode($query)]
            ]]
        ];
        
        sendMessage($chat_id, "💡 Click below to automatically request this movie:", $request_keyboard);
        
        // Waiting list mein add karo
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
    // Check karo user daily limit mein hai ya nahi
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
    // Movie request add karta hai
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
    
    // User request count update karo
    if (!isset($requests_data['user_request_count'][$user_id])) {
        $requests_data['user_request_count'][$user_id] = 0;
    }
    $requests_data['user_request_count'][$user_id]++;
    
    file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
    
    // Admin ko notify karo
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

function build_totalupload_keyboard(int $page, int $total_pages, string $session_id = '', array $filters = []): array {
    $kb = ['inline_keyboard' => []];
    
    // Enhanced navigation with page numbers
    $nav_row = [];
    
    // Previous/Fast Previous buttons
    if ($page > 1) {
        $nav_row[] = ['text' => '⏪', 'callback_data' => 'pag_first_' . $session_id];
        $nav_row[] = ['text' => '◀️', 'callback_data' => 'pag_prev_' . $page . '_' . $session_id];
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
        $nav_row[] = ['text' => '▶️', 'callback_data' => 'pag_next_' . $page . '_' . $session_id];
        $nav_row[] = ['text' => '⏩', 'callback_data' => 'pag_last_' . $total_pages . '_' . $session_id];
    }
    
    if (!empty($nav_row)) {
        $kb['inline_keyboard'][] = $nav_row;
    }
    
    // Action buttons row
    $action_row = [];
    $action_row[] = ['text' => '📥 Send Page', 'callback_data' => 'send_' . $page . '_' . $session_id];
    $action_row[] = ['text' => '👁️ Preview', 'callback_data' => 'prev_' . $page . '_' . $session_id];
    $action_row[] = ['text' => '📊 Stats', 'callback_data' => 'stats_' . $session_id];
    
    $kb['inline_keyboard'][] = $action_row;
    
    // Filter buttons row
    if (empty($filters)) {
        $filter_row = [];
        $filter_row[] = ['text' => '🎬 HD Only', 'callback_data' => 'flt_hd_' . $session_id];
        $filter_row[] = ['text' => '🎭 Theater Only', 'callback_data' => 'flt_theater_' . $session_id];
        $filter_row[] = ['text' => '🔒 Backup Only', 'callback_data' => 'flt_backup_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
    } else {
        $filter_row = [];
        $filter_row[] = ['text' => '🧹 Clear Filter', 'callback_data' => 'flt_clr_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
    }
    
    // Control buttons row
    $ctrl_row = [];
    $ctrl_row[] = ['text' => '💾 Save', 'callback_data' => 'save_' . $session_id];
    $ctrl_row[] = ['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''];
    $ctrl_row[] = ['text' => '❌ Close', 'callback_data' => 'close_' . $session_id];
    
    $kb['inline_keyboard'][] = $ctrl_row;
    
    return $kb;
}

function totalupload_controller($chat_id, $page = 1, $filters = [], $session_id = null) {
    $all = get_all_movies_list();
    if (empty($all)) {
        sendMessage($chat_id, "📭 Koi movies nahi mili! Pehle kuch movies add karo.");
        return;
    }
    
    // Create session ID if not provided
    if (!$session_id) {
        $session_id = uniqid('sess_', true);
    }
    
    $pg = paginate_movies($all, (int)$page, $filters);
    
    // Send preview for first page
    if ($page == 1 && PREVIEW_ITEMS > 0 && count($pg['slice']) > 0) {
        $preview_msg = "👁️ <b>Quick Preview (First " . PREVIEW_ITEMS . "):</b>\n\n";
        $preview_count = min(PREVIEW_ITEMS, count($pg['slice']));
        
        for ($i = 0; $i < $preview_count; $i++) {
            $movie = $pg['slice'][$i];
            $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
            $preview_msg .= ($i + 1) . ". $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
            $preview_msg .= "   ⭐ " . ($movie['quality'] ?? 'Unknown') . " | ";
            $preview_msg .= "🗣️ " . ($movie['language'] ?? 'Hindi') . "\n\n";
        }
        
        sendMessage($chat_id, $preview_msg, null, 'HTML');
    }
    
    // Build enhanced message
    $title = "🎬 <b>Enhanced Movie Browser</b>\n\n";
    
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
    
    $title .= "\n";
    
    // Current page movies list
    $title .= "📋 <b>Page {$page} Movies:</b>\n\n";
    $i = $pg['start_item'];
    foreach ($pg['slice'] as $movie) {
        $movie_name = htmlspecialchars($movie['movie_name'] ?? 'Unknown');
        $quality = $movie['quality'] ?? 'Unknown';
        $language = $movie['language'] ?? 'Hindi';
        $date = $movie['date'] ?? 'N/A';
        $size = $movie['size'] ?? 'Unknown';
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        
        $title .= "<b>{$i}.</b> $channel_icon {$movie_name}\n";
        $title .= "   🏷️ {$quality} | 🗣️ {$language}\n";
        $title .= "   💾 {$size} | 📅 {$date}\n\n";
        $i++;
    }
    
    // Navigation help
    $title .= "📍 <i>Use number buttons for direct page access</i>\n";
    $title .= "🔧 <i>Apply filters using buttons below</i>";
    
    // Build enhanced keyboard
    $kb = build_totalupload_keyboard($pg['page'], $pg['total_pages'], $session_id, $filters);
    
    // Delete previous pagination message if exists
    delete_pagination_message($chat_id, $session_id);
    
    // Save new message ID
    $result = sendMessage($chat_id, $title, $kb, 'HTML');
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
                    
                case 'year':
                    $movie_year = substr($movie['date'] ?? '', -4);
                    if ($movie_year != $value) {
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
    
    $progress_msg = sendMessage($chat_id, "📦 <b>Batch Info Started</b>\n\nPage: {$page_num}\nTotal: {$total} movies\n\n⏳ Initializing...");
    $progress_id = $progress_msg['result']['message_id'];
    
    $success = 0;
    $failed = 0;
    
    for ($i = 0; $i < $total; $i++) {
        $movie = $movies[$i];
        
        // Update progress every 2 movies
        if ($i % 2 == 0) {
            $progress = round(($i / $total) * 100);
            editMessage($chat_id, $progress_id, 
                "📦 <b>Sending Page {$page_num} Info</b>\n\n" .
                "Progress: {$progress}%\n" .
                "Processed: {$i}/{$total}\n" .
                "✅ Success: {$success}\n" .
                "❌ Failed: {$failed}\n\n" .
                "⏳ Please wait..."
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
        
        usleep(500000); // 0.5 second delay
    }
    
    // Final update
    editMessage($chat_id, $progress_id,
        "✅ <b>Batch Info Complete</b>\n\n" .
        "📄 Page: {$page_num}\n" .
        "🎬 Total: {$total} movies\n" .
        "✅ Successfully sent: {$success}\n" .
        "❌ Failed: {$failed}\n\n" .
        "📊 Success rate: " . round(($success / $total) * 100, 2) . "%\n" .
        "⏱️ Time: " . date('H:i:s') . "\n\n" .
        "🔗 Join channel to download: " . MAIN_CHANNEL
    );
}

// ==============================
// GET ALL MOVIES LIST FUNCTION
// ==============================
function get_all_movies_list() {
    // All movies list return karta hai
    return get_cached_movies();
}

// ==============================
// BACKUP SYSTEM - COMPLETE IMPLEMENTATION
// ==============================
function auto_backup() {
    // Automatic backup process
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
    // Backup summary create karta hai
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
    // Backup Telegram channel pe upload karta hai
    try {
        // 1. Backup summary message send karo
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
        
        $message_result = sendMessage(BACKUP_CHANNEL_ID, $summary_message, $keyboard, 'HTML');
        
        if (!$message_result || !isset($message_result['ok']) || !$message_result['ok']) {
            bot_log("Failed to send backup summary to channel", 'ERROR');
            return false;
        }
        
        // 2. Critical files as documents upload karo
        $critical_files = [
            CSV_FILE => "🎬 Movies Database",
            USERS_FILE => "👥 Users Data", 
            STATS_FILE => "📊 Bot Statistics",
            REQUEST_FILE => "📝 Movie Requests"
        ];
        
        foreach ($critical_files as $file => $description) {
            if (file_exists($file)) {
                $upload_success = upload_file_to_channel($file, $backup_dir, $description);
                if (!$upload_success) {
                    bot_log("Failed to upload $file to channel", 'WARNING');
                }
                sleep(2); // Rate limiting
            }
        }
        
        // 3. Zip archive create karo aur upload karo
        $zip_success = create_and_upload_zip($backup_dir);
        
        // 4. Completion message send karo
        $completion_message = "✅ <b>Backup Process Completed</b>\n\n";
        $completion_message .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $completion_message .= "💾 All files backed up successfully\n";
        $completion_message .= "📦 Zip archive created\n";
        $completion_message .= "📡 Uploaded to: " . BACKUP_CHANNEL_USERNAME . "\n\n";
        $completion_message .= "🛡️ <i>Your data is now securely backed up!</i>";
        
        sendMessage(BACKUP_CHANNEL_ID, $completion_message, null, 'HTML');
        
        return true;
        
    } catch (Exception $e) {
        bot_log("Channel backup failed: " . $e->getMessage(), 'ERROR');
        
        // Error report send karo backup channel pe
        $error_message = "❌ <b>Backup Process Failed</b>\n\n";
        $error_message .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $error_message .= "🚨 Error: " . $e->getMessage() . "\n\n";
        $error_message .= "⚠️ Please check server logs immediately!";
        
        sendMessage(BACKUP_CHANNEL_ID, $error_message, null, 'HTML');
        
        return false;
    }
}

function upload_file_to_channel($file_path, $backup_dir, $description = "") {
    // Individual file channel pe upload karta hai
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
    
    // Large files ke liye (Telegram limit 50MB)
    if ($file_size > 45 * 1024 * 1024) { // 45MB limit
        bot_log("File too large for Telegram: $file_name ($file_size_mb MB)", 'WARNING');
        
        // Large CSV files ko split karo
        if ($file_name == 'movies.csv') {
            return split_and_upload_large_csv($backup_file_path, $backup_dir, $description);
        }
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
        
        // Large files ke liye confirmation message
        if ($file_size > 10 * 1024 * 1024) {
            $confirmation = "✅ <b>Large File Uploaded</b>\n\n";
            $confirmation .= "📁 File: " . $description . "\n";
            $confirmation .= "💾 Size: " . $file_size_mb . " MB\n";
            $confirmation .= "✅ Status: Successfully uploaded to " . BACKUP_CHANNEL_USERNAME;
            sendMessage(BACKUP_CHANNEL_ID, $confirmation, null, 'HTML');
        }
    } else {
        bot_log("Failed to upload to channel: $file_name", 'ERROR');
    }
    
    return $success;
}

function split_and_upload_large_csv($csv_file_path, $backup_dir, $description) {
    // Large CSV files ko split karke upload karta hai
    if (!file_exists($csv_file_path)) {
        return false;
    }
    
    $file_size = filesize($csv_file_path);
    $file_size_mb = round($file_size / (1024 * 1024), 2);
    
    bot_log("Splitting large CSV file: $file_size_mb MB", 'INFO');
    
    // CSV file read karo
    $rows = [];
    $handle = fopen($csv_file_path, 'r');
    if ($handle !== FALSE) {
        $header = fgetcsv($handle); // Header read karo
        while (($row = fgetcsv($handle)) !== FALSE) {
            $rows[] = $row;
        }
        fclose($handle);
    }
    
    $total_rows = count($rows);
    $rows_per_file = ceil($total_rows / 3); // 3 parts mein split karo
    
    $upload_success = true;
    
    for ($i = 0; $i < 3; $i++) {
        $start = $i * $rows_per_file;
        $end = min($start + $rows_per_file, $total_rows);
        $part_rows = array_slice($rows, $start, $end - $start);
        
        // Part file create karo
        $part_file = $backup_dir . '/movies_part_' . ($i + 1) . '.csv';
        $part_handle = fopen($part_file, 'w');
        fputcsv($part_handle, $header);
        foreach ($part_rows as $row) {
            fputcsv($part_handle, $row);
        }
        fclose($part_handle);
        
        // Part file upload karo
        $part_caption = "💾 " . $description . " (Part " . ($i + 1) . "/3)\n";
        $part_caption .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $part_caption .= "📊 Rows: " . count($part_rows) . "\n";
        $part_caption .= "🔄 Split backup\n";
        $part_caption .= "📡 " . BACKUP_CHANNEL_USERNAME;
        
        $post_fields = [
            'chat_id' => BACKUP_CHANNEL_ID,
            'document' => new CURLFile($part_file),
            'caption' => $part_caption,
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
        
        // Part file clean up karo
        @unlink($part_file);
        
        if ($http_code != 200) {
            $upload_success = false;
            bot_log("Failed to upload CSV part " . ($i + 1), 'ERROR');
        } else {
            bot_log("Uploaded CSV part " . ($i + 1));
        }
        
        sleep(2); // Rate limiting
    }
    
    // Split completion message send karo
    if ($upload_success) {
        $split_message = "📦 <b>Large CSV Split Successfully</b>\n\n";
        $split_message .= "📁 File: " . $description . "\n";
        $split_message .= "💾 Original Size: " . $file_size_mb . " MB\n";
        $split_message .= "📊 Total Rows: " . $total_rows . "\n";
        $split_message .= "🔀 Split into: 3 parts\n";
        $split_message .= "✅ All parts uploaded to " . BACKUP_CHANNEL_USERNAME;
        
        sendMessage(BACKUP_CHANNEL_ID, $split_message, null, 'HTML');
    }
    
    return $upload_success;
}

function create_and_upload_zip($backup_dir) {
    // Zip archive create aur upload karta hai
    $zip_file = $backup_dir . '/complete_backup.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
        bot_log("Cannot open zip file: $zip_file", 'ERROR');
        return false;
    }
    
    // Files zip mein add karo
    $files = glob($backup_dir . '/*.bak');
    foreach ($files as $file) {
        $zip->addFile($file, basename($file));
    }
    
    // Summary file add karo
    if (file_exists($backup_dir . '/backup_summary.txt')) {
        $zip->addFile($backup_dir . '/backup_summary.txt', 'backup_summary.txt');
    }
    
    $zip->close();
    
    $zip_size = filesize($zip_file);
    $zip_size_mb = round($zip_size / (1024 * 1024), 2);
    
    // Zip file upload karo
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
    
    // Zip file clean up karo
    @unlink($zip_file);
    
    $success = ($http_code == 200);
    
    if ($success) {
        bot_log("Zip backup uploaded to channel successfully");
        
        // Zip upload confirmation send karo
        $zip_confirmation = "✅ <b>Zip Archive Uploaded</b>\n\n";
        $zip_confirmation .= "📦 File: Complete Backup Archive\n";
        $zip_confirmation .= "💾 Size: " . $zip_size_mb . " MB\n";
        $zip_confirmation .= "✅ Status: Successfully uploaded\n";
        $zip_confirmation .= "📡 Channel: " . BACKUP_CHANNEL_USERNAME . "\n\n";
        $zip_confirmation .= "🛡️ <i>All data securely backed up!</i>";
        
        sendMessage(BACKUP_CHANNEL_ID, $zip_confirmation, $keyboard, 'HTML');
    } else {
        bot_log("Failed to upload zip backup to channel", 'WARNING');
    }
    
    return $success;
}

function clean_old_backups() {
    // Purane backups delete karta hai (last 7 rakhta hai)
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
    // Admin ko backup report send karta hai
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
    
    // Summary stats add karo
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
    
    sendMessage(ADMIN_ID, $report_message, $keyboard, 'HTML');
}

// ==============================
// MANUAL BACKUP COMMANDS
// ==============================
function manual_backup($chat_id) {
    // Manual backup command handler
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $progress_msg = sendMessage($chat_id, "🔄 Starting manual backup...");
    
    try {
        $success = auto_backup();
        
        if ($success) {
            editMessage($chat_id, $progress_msg['result']['message_id'], "✅ Manual backup completed successfully!\n\n📊 Backup has been saved locally and uploaded to backup channel.");
        } else {
            editMessage($chat_id, $progress_msg['result']['message_id'], "⚠️ Backup completed with some warnings.\n\nSome files may not have been backed up properly. Check logs for details.");
        }
        
    } catch (Exception $e) {
        editMessage($chat_id, $progress_msg['result']['message_id'], "❌ Backup failed!\n\nError: " . $e->getMessage());
        bot_log("Manual backup failed: " . $e->getMessage(), 'ERROR');
    }
}

function quick_backup($chat_id) {
    // Quick backup command handler
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $progress_msg = sendMessage($chat_id, "💾 Creating quick backup...");
    
    try {
        // Quick backup - only essential files
        $essential_files = [CSV_FILE, USERS_FILE];
        $backup_dir = BACKUP_DIR . 'quick_' . date('Y-m-d_H-i-s');
        
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        
        foreach ($essential_files as $file) {
            if (file_exists($file)) {
                copy($file, $backup_dir . '/' . basename($file) . '.bak');
            }
        }
        
        // Channel pe upload karo
        $summary = "🚀 Quick Backup\n" . date('Y-m-d H:i:s') . "\nEssential files only";
        file_put_contents($backup_dir . '/quick_backup_info.txt', $summary);
        
        foreach ($essential_files as $file) {
            $backup_file = $backup_dir . '/' . basename($file) . '.bak';
            if (file_exists($backup_file)) {
                upload_file_to_channel($file, $backup_dir);
                sleep(1);
            }
        }
        
        editMessage($chat_id, $progress_msg['result']['message_id'], "✅ Quick backup completed!\n\nEssential files backed up to channel.");
        
    } catch (Exception $e) {
        editMessage($chat_id, $progress_msg['result']['message_id'], "❌ Quick backup failed!\n\nError: " . $e->getMessage());
    }
}

// ==============================
// BACKUP STATUS & INFO COMMANDS
// ==============================
function backup_status($chat_id) {
    // Backup status show karta hai
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
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
    $status_message .= "• <code>/quickbackup</code> - Quick backup\n";
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
    
    sendMessage($chat_id, $status_message, $keyboard, 'HTML');
}

// ==============================
// CHANNEL MANAGEMENT FUNCTIONS
// ==============================
function show_channel_info($chat_id) {
    // All channels ka information show karta hai
    $message = "📢 <b>Join Our Channels</b>\n\n";
    
    $message .= "🍿 <b>Main Channel:</b> " . MAIN_CHANNEL . "\n";
    $message .= "• Latest movie updates\n";
    $message .= "• Daily new additions\n";
    $message .= "• High quality prints\n";
    $message .= "• Direct downloads\n\n";
    
    $message .= "📥 <b>Requests Channel:</b> " . REQUEST_CHANNEL . "\n";
    $message .= "• Movie requests\n";
    $message .= "• Bug reports\n";
    $message .= "• Feature suggestions\n";
    $message .= "• Support & help\n\n";
    
    $message .= "🎭 <b>Theater Prints:</b> " . THEATER_CHANNEL . "\n";
    $message .= "• Theater quality prints\n";
    $message .= "• HD screen recordings\n";
    $message .= "• Latest theater prints\n\n";
    
    $message .= "🔒 <b>Backup Channel:</b> " . BACKUP_CHANNEL_USERNAME . "\n";
    $message .= "• Secure data backups\n";
    $message .= "• System archives\n";
    $message .= "• Database copies\n";
    $message .= "• Admin only access\n\n";
    
    $message .= "🔔 <b>Don't forget to join all channels!</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🍿 ' . MAIN_CHANNEL, 'url' => 'https://t.me/EntertainmentTadka786'],
                ['text' => '📥 ' . REQUEST_CHANNEL, 'url' => 'https://t.me/EntertainmentTadka7860']
            ],
            [
                ['text' => '🎭 ' . THEATER_CHANNEL, 'url' => 'https://t.me/threater_print_movies'],
                ['text' => '🔒 ' . BACKUP_CHANNEL_USERNAME, 'url' => 'https://t.me/ETBackup']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_main_channel_info($chat_id) {
    // Main channel ka detailed information
    $message = "🍿 <b>Main Channel - " . MAIN_CHANNEL . "</b>\n\n";
    
    $message .= "🎬 <b>What you get:</b>\n";
    $message .= "• Latest Bollywood & Hollywood movies\n";
    $message .= "• HD/1080p/720p quality prints\n";
    $message .= "• Daily new uploads\n";
    $message .= "• Multiple server links\n";
    $message .= "• Fast direct downloads\n";
    $message .= "• No ads, no spam\n\n";
    
    $message .= "📊 <b>Current Stats:</b>\n";
    $stats = get_stats();
    $message .= "• Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $message .= "• Active Users: " . get_active_users_count() . "\n";
    $message .= "• Daily Uploads: " . get_daily_uploads_count() . "\n\n";
    
    $message .= "🔔 <b>Join now for latest movies!</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🍿 Join Main Channel', 'url' => 'https://t.me/EntertainmentTadka786'],
                ['text' => '📥 Request Movies', 'url' => 'https://t.me/EntertainmentTadka7860']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_request_channel_info($chat_id) {
    // Request channel ka detailed information
    $message = "📥 <b>Requests Channel - " . REQUEST_CHANNEL . "</b>\n\n";
    
    $message .= "🎯 <b>How to request movies:</b>\n";
    $message .= "1. Join this channel first\n";
    $message .= "2. Use <code>/request movie_name</code> in bot\n";
    $message .= "3. Or post directly in channel\n";
    $message .= "4. We'll add within 24 hours\n\n";
    
    $message .= "📝 <b>Also available:</b>\n";
    $message .= "• Bug reports & issues\n";
    $message .= "• Feature suggestions\n";
    $message .= "• General support\n";
    $message .= "• Bot help & guidance\n\n";
    
    $message .= "⚠️ <b>Please check these before requesting:</b>\n";
    $message .= "• Search in bot first\n";
    $message .= "• Check spelling\n";
    $message .= "• Use correct movie name\n";
    $message .= "• Be patient for uploads\n\n";
    
    $message .= "🔔 <b>Auto-notification:</b> You'll get notified when requested movies are added!";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Join Requests Channel', 'url' => 'https://t.me/EntertainmentTadka7860'],
                ['text' => '🎬 Request via Bot', 'callback_data' => 'request_help']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_theater_channel_info($chat_id) {
    // Theater channel ka detailed information
    $message = "🎭 <b>Theater Prints - " . THEATER_CHANNEL . "</b>\n\n";
    
    $message .= "🎥 <b>What you get:</b>\n";
    $message .= "• Latest theater prints\n";
    $message .= "• HD screen recordings\n";
    $message .= "• Best quality available\n";
    $message .= "• Fast uploads after release\n";
    $message .= "• Multiple quality options\n\n";
    
    $message .= "⭐ <b>Features:</b>\n";
    $message .= "• 1080p theater prints\n";
    $message .= "• Clear audio quality\n";
    $message .= "• No watermarks\n";
    $message .= "• Multiple languages\n\n";
    
    $message .= "📥 <b>How to access:</b>\n";
    $message .= "1. Join " . THEATER_CHANNEL . "\n";
    $message .= "2. Search in bot\n";
    $message .= "3. Get message IDs\n";
    $message .= "4. Download from channel\n\n";
    
    $message .= "🎬 <b>For the best viewing experience!</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🎭 Join Theater Channel', 'url' => 'https://t.me/threater_print_movies'],
                ['text' => '🔍 Search Theater Movies', 'callback_data' => 'search_theater']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_backup_channel_info($chat_id) {
    // Backup channel ka detailed information
    $message = "🔒 <b>Backup Channel - " . BACKUP_CHANNEL_USERNAME . "</b>\n\n";
    
    $message .= "🛡️ <b>Purpose:</b>\n";
    $message .= "• Secure data backups\n";
    $message .= "• Database protection\n";
    $message .= "• System recovery\n";
    $message .= "• Disaster prevention\n\n";
    
    $message .= "💾 <b>What's backed up:</b>\n";
    $message .= "• Movies database (" . get_csv_count() . " movies)\n";
    $message .= "• Users data (" . get_users_count() . " users)\n";
    $message .= "• Bot statistics\n";
    $message .= "• Request history\n";
    $message .= "• Complete system archives\n\n";
    
    $message .= "⏰ <b>Backup Schedule:</b>\n";
    $message .= "• Automatic: Daily at " . AUTO_BACKUP_HOUR . ":00\n";
    $message .= "• Manual: On admin command\n";
    $message .= "• Retention: Last 7 backups\n\n";
    
    $message .= "🔐 <b>Note:</b> This is a private channel for admin use only.";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔒 ' . BACKUP_CHANNEL_USERNAME, 'url' => 'https://t.me/ETBackup'],
                ['text' => '📊 Backup Status', 'callback_data' => 'backup_status']
            ]
        ]
    ];
    
    if ($chat_id == ADMIN_ID) {
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    } else {
        sendMessage($chat_id, "🔒 <b>Backup Channel</b>\n\nThis is a private admin-only channel for data protection.", null, 'HTML');
    }
}

// ==============================
// HELPER FUNCTIONS FOR CHANNEL INFO
// ==============================
function get_active_users_count() {
    // Active users count karta hai (last 7 days)
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $active_count = 0;
    $one_week_ago = strtotime('-1 week');
    
    foreach ($users_data['users'] ?? [] as $user) {
        if (strtotime($user['last_active'] ?? '') >= $one_week_ago) {
            $active_count++;
        }
    }
    
    return $active_count;
}

function get_daily_uploads_count() {
    // Daily uploads count karta hai
    $today = date('d-m-Y');
    $count = 0;
    
    $handle = fopen(CSV_FILE, 'r');
    if ($handle !== FALSE) {
        fgetcsv($handle); // skip header
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && $row[2] == $today) {
                $count++;
            }
        }
        fclose($handle);
    }
    
    return $count;
}

function get_csv_count() {
    // CSV mein total movies count karta hai
    $count = 0;
    
    $handle = fopen(CSV_FILE, 'r');
    if ($handle !== FALSE) {
        fgetcsv($handle); // skip header
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && !empty(trim($row[0]))) {
                $count++;
            }
        }
        fclose($handle);
    }
    
    return $count;
}

function get_users_count() {
    // Total users count karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    return count($users_data['users'] ?? []);
}

// ==============================
// USER STATS & LEADERBOARD FUNCTIONS
// ==============================
function show_user_stats($chat_id, $user_id) {
    // User ki statistics show karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user = $users_data['users'][$user_id] ?? null;
    
    if (!$user) {
        sendMessage($chat_id, "❌ User data not found!");
        return;
    }
    
    $message = "👤 <b>Your Statistics</b>\n\n";
    $message .= "🆔 User ID: <code>$user_id</code>\n";
    $message .= "📅 Joined: " . ($user['joined'] ?? 'N/A') . "\n";
    $message .= "🕒 Last Active: " . ($user['last_active'] ?? 'N/A') . "\n\n";
    
    $message .= "📊 <b>Activity:</b>\n";
    $message .= "• 🔍 Searches: " . ($user['total_searches'] ?? 0) . "\n";
    $message .= "• 📥 Downloads: " . ($user['total_downloads'] ?? 0) . "\n";
    $message .= "• 📝 Requests: " . ($user['request_count'] ?? 0) . "\n";
    $message .= "• ⭐ Points: " . ($user['points'] ?? 0) . "\n\n";
    
    $message .= "🎯 <b>Rank:</b> " . calculate_user_rank($user['points'] ?? 0);
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📈 Leaderboard', 'callback_data' => 'show_leaderboard'],
                ['text' => '🔄 Refresh', 'callback_data' => 'refresh_stats']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_user_points($chat_id, $user_id) {
    // User ke points show karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user = $users_data['users'][$user_id] ?? null;
    
    if (!$user) {
        sendMessage($chat_id, "❌ User data not found!");
        return;
    }
    
    $points = $user['points'] ?? 0;
    
    $message = "⭐ <b>Your Points</b>\n\n";
    $message .= "🎯 Total Points: <b>$points</b>\n\n";
    
    $message .= "📈 <b>How to earn points:</b>\n";
    $message .= "• 🔍 Daily search: +1 point\n";
    $message .= "• 📥 Movie download: +3 points\n";
    $message .= "• 📝 Movie request: +2 points\n";
    $message .= "• 🎯 Found movie: +5 points\n";
    $message .= "• 📅 Daily login: +10 points\n\n";
    
    $message .= "🏆 <b>Your Rank:</b> " . calculate_user_rank($points) . "\n";
    $message .= "📊 <b>Next Rank:</b> " . get_next_rank_info($points);
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_leaderboard($chat_id) {
    // Top users leaderboard show karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $users = $users_data['users'] ?? [];
    
    if (empty($users)) {
        sendMessage($chat_id, "📭 Koi user data nahi mila!");
        return;
    }
    
    // Points ke hisab se sort karo
    uasort($users, function($a, $b) {
        return ($b['points'] ?? 0) - ($a['points'] ?? 0);
    });
    
    $message = "🏆 <b>Top Users Leaderboard</b>\n\n";
    $i = 1;
    
    foreach (array_slice($users, 0, 10) as $user_id => $user) {
        $points = $user['points'] ?? 0;
        $username = $user['username'] ? "@" . $user['username'] : "User#" . substr($user_id, -4);
        $medal = $i == 1 ? "🥇" : ($i == 2 ? "🥈" : ($i == 3 ? "🥉" : "🔸"));
        
        $message .= "$medal $i. $username\n";
        $message .= "   ⭐ $points points | 🎯 " . calculate_user_rank($points) . "\n\n";
        $i++;
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📊 My Stats', 'callback_data' => 'my_stats'],
                ['text' => '🔄 Refresh', 'callback_data' => 'refresh_leaderboard']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function calculate_user_rank($points) {
    // Points ke hisab se user rank calculate karta hai
    if ($points >= 1000) return "🎖️ Elite";
    if ($points >= 500) return "🔥 Pro";
    if ($points >= 250) return "⭐ Advanced";
    if ($points >= 100) return "🚀 Intermediate";
    if ($points >= 50) return "👍 Beginner";
    return "🌱 Newbie";
}

function get_next_rank_info($points) {
    // Next rank ke liye required points batata hai
    if ($points < 50) return "Beginner (50 points needed)";
    if ($points < 100) return "Intermediate (100 points needed)";
    if ($points < 250) return "Advanced (250 points needed)";
    if ($points < 500) return "Pro (500 points needed)";
    if ($points < 1000) return "Elite (1000 points needed)";
    return "Max Rank Achieved! 🏆";
}

// ==============================
// BROWSE COMMANDS
// ==============================
function show_latest_movies($chat_id, $limit = 10) {
    // Latest movies show karta hai
    $all_movies = get_all_movies_list();
    $latest_movies = array_slice($all_movies, -$limit);
    $latest_movies = array_reverse($latest_movies);
    
    if (empty($latest_movies)) {
        sendMessage($chat_id, "📭 Koi movies nahi mili!");
        return;
    }
    
    $message = "🎬 <b>Latest $limit Movies</b>\n\n";
    $i = 1;
    
    foreach ($latest_movies as $movie) {
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
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
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_trending_movies($chat_id) {
    // Trending movies show karta hai
    $all_movies = get_all_movies_list();
    
    // Simple trending logic (recent aur most downloaded)
    $trending_movies = array_slice($all_movies, -15); // Last 15 movies
    
    if (empty($trending_movies)) {
        sendMessage($chat_id, "📭 Koi trending movies nahi mili!");
        return;
    }
    
    $message = "🔥 <b>Trending Movies</b>\n\n";
    $i = 1;
    
    foreach (array_slice($trending_movies, 0, 10) as $movie) {
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        $message .= "$i. $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
        $message .= "   ⭐ " . ($movie['quality'] ?? 'HD') . " | 🗣️ " . ($movie['language'] ?? 'Hindi') . "\n\n";
        $i++;
    }
    
    $message .= "💡 <i>Based on recent popularity and downloads</i>";
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_movies_by_quality($chat_id, $quality) {
    // Specific quality ki movies show karta hai
    $all_movies = get_all_movies_list();
    $filtered_movies = [];
    
    foreach ($all_movies as $movie) {
        if (stripos($movie['quality'] ?? '', $quality) !== false) {
            $filtered_movies[] = $movie;
        }
    }
    
    if (empty($filtered_movies)) {
        sendMessage($chat_id, "❌ Koi $quality quality movies nahi mili!");
        return;
    }
    
    $message = "🎬 <b>$quality Quality Movies</b>\n\n";
    $message .= "📊 Total Found: " . count($filtered_movies) . "\n\n";
    
    $i = 1;
    foreach (array_slice($filtered_movies, 0, 10) as $movie) {
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        $message .= "$i. $channel_icon " . htmlspecialchars($movie['movie_name']) . "\n";
        $i++;
    }
    
    if (count($filtered_movies) > 10) {
        $message .= "\n... and " . (count($filtered_movies) - 10) . " more";
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Get All Info', 'callback_data' => 'download_quality_' . $quality],
                ['text' => '🔄 Other Qualities', 'callback_data' => 'show_qualities']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_movies_by_language($chat_id, $language) {
    // Specific language ki movies show karta hai
    $all_movies = get_all_movies_list();
    $filtered_movies = [];
    
    foreach ($all_movies as $movie) {
        if (stripos($movie['language'] ?? '', $language) !== false) {
            $filtered_movies[] = $movie;
        }
    }
    
    if (empty($filtered_movies)) {
        sendMessage($chat_id, "❌ Koi $language movies nahi mili!");
        return;
    }
    
    $message = "🎬 <b>" . ucfirst($language) . " Movies</b>\n\n";
    $message .= "📊 Total Found: " . count($filtered_movies) . "\n\n";
    
    $i = 1;
    foreach (array_slice($filtered_movies, 0, 10) as $movie) {
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        $message .= "$i. $channel_icon " . htmlspecialchars($movie['movie_name']) . "\n";
        $message .= "   📊 " . ($movie['quality'] ?? 'Unknown') . "\n\n";
        $i++;
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Get All Info', 'callback_data' => 'download_lang_' . $language],
                ['text' => '🔄 Other Languages', 'callback_data' => 'show_languages']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

// ==============================
// REQUEST MANAGEMENT
// ==============================
function show_user_requests($chat_id, $user_id) {
    // User ke movie requests show karta hai
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $user_requests = [];
    
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id) {
            $user_requests[] = $request;
        }
    }
    
    if (empty($user_requests)) {
        sendMessage($chat_id, "📭 Aapne abhi tak koi movie request nahi ki hai!");
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
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_request_limit($chat_id, $user_id) {
    // User ke request limit ka status show karta hai
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $today = date('Y-m-d');
    $today_requests = 0;
    
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id && $request['date'] == $today) {
            $today_requests++;
        }
    }
    
    $remaining = DAILY_REQUEST_LIMIT - $today_requests;
    
    $message = "📋 <b>Your Request Limit</b>\n\n";
    $message .= "✅ Daily Limit: " . DAILY_REQUEST_LIMIT . " requests\n";
    $message .= "📅 Used Today: $today_requests requests\n";
    $message .= "🎯 Remaining Today: $remaining requests\n\n";
    
    if ($remaining > 0) {
        $message .= "💡 Use <code>/request movie_name</code> to request movies!";
    } else {
        $message .= "⏳ Limit resets at midnight!";
    }
    
    sendMessage($chat_id, $message, null, 'HTML');
}

// ==============================
// ADMIN COMMANDS
// ==============================
function admin_stats($chat_id) {
    // Complete bot statistics show karta hai
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
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
    
    // Daily activity
    $today = date('Y-m-d');
    if (isset($stats['daily_activity'][$today])) {
        $today_stats = $stats['daily_activity'][$today];
        $msg .= "📈 <b>Today's Activity:</b>\n";
        $msg .= "• Searches: " . ($today_stats['searches'] ?? 0) . "\n";
        $msg .= "• Downloads: " . ($today_stats['downloads'] ?? 0) . "\n";
    }
    
    $csv_data = load_and_clean_csv();
    $recent = array_slice($csv_data, -5);
    $msg .= "\n📦 Recent Uploads:\n";
    foreach ($recent as $r) {
        $channel_icon = get_channel_display_name($r['channel_type'] ?? 'main');
        $msg .= "• $channel_icon " . $r['movie_name'] . " (" . $r['date'] . ")\n";
    }
    
    sendMessage($chat_id, $msg, null, 'HTML');
    bot_log("Admin stats viewed by $chat_id");
}

function show_csv_data($chat_id, $show_all = false) {
    // CSV data show karta hai
    if (!file_exists(CSV_FILE)) {
        sendMessage($chat_id, "❌ CSV file not found.");
        return;
    }
    
    $handle = fopen(CSV_FILE, "r");
    if ($handle === FALSE) {
        sendMessage($chat_id, "❌ Error opening CSV file.");
        return;
    }
    
    fgetcsv($handle);
    $movies = [];
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) >= 3) {
            $movies[] = $row;
        }
    }
    fclose($handle);
    
    if (empty($movies)) {
        sendMessage($chat_id, "📊 CSV file is empty.");
        return;
    }
    
    $movies = array_reverse($movies);
    $limit = $show_all ? count($movies) : 10;
    $movies = array_slice($movies, 0, $limit);
    
    $message = "📊 <b>CSV Movie Database</b>\n\n";
    $message .= "📁 Total Movies: " . count($movies) . "\n";
    
    if (!$show_all) {
        $message .= "🔍 Showing latest 10 entries\n";
        $message .= "📋 Use '/checkcsv all' for full list\n\n";
    } else {
        $message .= "📋 Full database listing\n\n";
    }
    
    $i = 1;
    foreach ($movies as $movie) {
        $movie_name = $movie[0] ?? 'N/A';
        $message_id = $movie[1] ?? 'N/A';
        $date = $movie[2] ?? 'N/A';
        $quality = $movie[4] ?? 'Unknown';
        $language = $movie[6] ?? 'Hindi';
        $channel_type = isset($movie[7]) ? $movie[7] : 'main';
        $channel_icon = get_channel_display_name($channel_type);
        
        $message .= "$i. $channel_icon " . htmlspecialchars($movie_name) . "\n";
        $message .= "   📝 ID: $message_id | 🗣️ $language | 📊 $quality\n";
        $message .= "   📅 Date: $date\n\n";
        
        $i++;
        
        if (strlen($message) > 3000) {
            sendMessage($chat_id, $message, null, 'HTML');
            $message = "📊 Continuing...\n\n";
        }
    }
    
    $message .= "💾 File: " . CSV_FILE . "\n";
    $message .= "⏰ Last Updated: " . date('Y-m-d H:i:s', filemtime(CSV_FILE));
    
    sendMessage($chat_id, $message, null, 'HTML');
    bot_log("CSV data viewed by $chat_id - Show all: " . ($show_all ? 'Yes' : 'No'));
}

function send_broadcast($chat_id, $message) {
    // All users ko broadcast message send karta hai
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $total_users = count($users_data['users'] ?? []);
    $success_count = 0;
    
    $progress_msg = sendMessage($chat_id, "📢 Broadcasting to $total_users users...\n\nProgress: 0%");
    $progress_msg_id = $progress_msg['result']['message_id'];
    
    $i = 0;
    foreach ($users_data['users'] as $user_id => $user) {
        try {
            sendMessage($user_id, "📢 <b>Announcement from Admin:</b>\n\n$message", null, 'HTML');
            $success_count++;
            
            // Har 10 users ke baad progress update karo
            if ($i % 10 === 0) {
                $progress = round(($i / $total_users) * 100);
                editMessage($chat_id, $progress_msg_id, "📢 Broadcasting to $total_users users...\n\nProgress: $progress%");
            }
            
            usleep(100000); // 0.1 second delay
            $i++;
        } catch (Exception $e) {
            // Failed sends skip karo
        }
    }
    
    editMessage($chat_id, $progress_msg_id, "✅ Broadcast completed!\n\n📊 Sent to: $success_count/$total_users users");
    bot_log("Broadcast sent by $chat_id to $success_count users");
}

function toggle_maintenance_mode($chat_id, $mode) {
    // Maintenance mode toggle karta hai
    global $MAINTENANCE_MODE;
    
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    if ($mode == 'on') {
        $MAINTENANCE_MODE = true;
        sendMessage($chat_id, "🔧 Maintenance mode ENABLED\n\nBot is now in maintenance mode. Users will see maintenance message.");
        bot_log("Maintenance mode enabled by $chat_id");
    } elseif ($mode == 'off') {
        $MAINTENANCE_MODE = false;
        sendMessage($chat_id, "✅ Maintenance mode DISABLED\n\nBot is now operational.");
        bot_log("Maintenance mode disabled by $chat_id");
    } else {
        sendMessage($chat_id, "❌ Usage: <code>/maintenance on</code> or <code>/maintenance off</code>", null, 'HTML');
    }
}

function perform_cleanup($chat_id) {
    // System cleanup perform karta hai
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $stats_before = get_stats();
    
    // Purane backups clean karo
    $old = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    if (count($old) > 7) {
        usort($old, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $deleted_count = 0;
        foreach (array_slice($old, 0, count($old) - 7) as $d) {
            $files = glob($d . '/*');
            foreach ($files as $ff) @unlink($ff);
            if (@rmdir($d)) $deleted_count++;
        }
    }
    
    // Cache clean karo
    global $movie_cache;
    $movie_cache = [];
    
    sendMessage($chat_id, "🧹 Cleanup completed!\n\n• Old backups removed\n• Cache cleared\n• System optimized");
    bot_log("Cleanup performed by $chat_id");
}

function send_alert_to_all($chat_id, $alert_message) {
    // All users ko alert send karta hai
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $success_count = 0;
    
    foreach ($users_data['users'] as $user_id => $user) {
        try {
            sendMessage($user_id, "🚨 <b>Important Alert:</b>\n\n$alert_message", null, 'HTML');
            $success_count++;
            usleep(50000); // 0.05 second delay
        } catch (Exception $e) {
            // Failed sends skip karo
        }
    }
    
    sendMessage($chat_id, "✅ Alert sent to $success_count users!");
    bot_log("Alert sent by $chat_id: " . substr($alert_message, 0, 50));
}

// ==============================
// UTILITY FUNCTIONS
// ==============================
function check_date($chat_id) {
    // Movies upload dates ka record show karta hai
    if (!file_exists(CSV_FILE)) {
        sendMessage($chat_id, "⚠️ Abhi tak koi data save nahi hua.");
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
    
    sendMessage($chat_id, $msg, null, 'HTML');
}

function test_csv($chat_id) {
    // CSV testing ke liye raw data show karta hai
    if (!file_exists(CSV_FILE)) {
        sendMessage($chat_id, "⚠️ CSV file not found.");
        return;
    }
    
    $h = fopen(CSV_FILE, 'r');
    if ($h !== FALSE) {
        fgetcsv($h);
        $i = 1;
        $msg = "";
        
        while (($r = fgetcsv($h)) !== FALSE) {
            if (count($r) >= 3) {
                $channel_type = isset($r[7]) ? $r[7] : 'main';
                $channel_icon = get_channel_display_name($channel_type);
                $line = "$i. $channel_icon {$r[0]} | ID/Ref: {$r[1]} | Date: {$r[2]}";
                if (isset($r[4])) $line .= " | Quality: {$r[4]}";
                if (isset($r[6])) $line .= " | Language: {$r[6]}";
                $line .= "\n";
                
                if (strlen($msg) + strlen($line) > 4000) {
                    sendMessage($chat_id, $msg);
                    $msg = "";
                }
                $msg .= $line;
                $i++;
            }
        }
        fclose($h);
        
        if (!empty($msg)) {
            sendMessage($chat_id, $msg);
        }
    }
}

function show_bot_info($chat_id) {
    // Bot information show karta hai
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
    $message .= "• User points system\n";
    $message .= "• Leaderboard\n\n";
    
    $message .= "📢 <b>Channels:</b>\n";
    $message .= "• Main: " . MAIN_CHANNEL . "\n";
    $message .= "• Support: " . REQUEST_CHANNEL . "\n";
    $message .= "• Theater: " . THEATER_CHANNEL;
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_support_info($chat_id) {
    // Support information show karta hai
    $message = "🆘 <b>Support & Contact</b>\n\n";
    
    $message .= "📞 <b>Need Help?</b>\n";
    $message .= "• Movie not found?\n";
    $message .= "• Technical issues?\n";
    $message .= "• Feature requests?\n\n";
    
    $message .= "🎯 <b>Quick Solutions:</b>\n";
    $message .= "1. Use <code>/request movie_name</code> for new movies\n";
    $message .= "2. Check <code>/help</code> for all commands\n";
    $message .= "3. Join support channel below\n\n";
    
    $message .= "📢 <b>Support Channel:</b> " . REQUEST_CHANNEL . "\n";
    $message .= "👨‍💻 <b>Admin:</b> @EntertainmentTadka0786\n\n";
    
    $message .= "💡 <b>Pro Tip:</b> Always check spelling before reporting!";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📢 Support Channel', 'url' => 'https://t.me/EntertainmentTadka0786'],
                ['text' => '🐛 Report Bug', 'callback_data' => 'report_bug']
            ],
            [
                ['text' => '💡 Suggest Feature', 'callback_data' => 'suggest_feature'],
                ['text' => '📝 Give Feedback', 'callback_data' => 'give_feedback']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_donate_info($chat_id) {
    // Donation information show karta hai
    $message = "💝 <b>Support Our Work</b>\n\n";
    
    $message .= "🤖 <b>Why Donate?</b>\n";
    $message .= "• Server maintenance costs\n";
    $message .= "• Bot development & updates\n";
    $message .= "• New features implementation\n";
    $message .= "• 24/7 service availability\n\n";
    
    $message .= "💰 <b>Donation Methods:</b>\n";
    $message .= "• UPI: entertainmenttadka@upi\n";
    $message .= "• PayPal: coming soon\n";
    $message .= "• Crypto: coming soon\n\n";
    
    $message .= "🎁 <b>Donor Benefits:</b>\n";
    $message .= "• Priority support\n";
    $message .= "• Early access to features\n";
    $message .= "• Special donor badge\n";
    $message .= "• Increased request limits\n\n";
    
    $message .= "💌 <b>Contact for other methods:</b> " . REQUEST_CHANNEL;
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function submit_bug_report($chat_id, $user_id, $bug_report) {
    // Bug report submit karta hai
    $report_id = uniqid();
    
    $admin_message = "🐛 <b>New Bug Report</b>\n\n";
    $admin_message .= "🆔 Report ID: $report_id\n";
    $admin_message .= "👤 User ID: $user_id\n";
    $admin_message .= "📅 Time: " . date('Y-m-d H:i:s') . "\n\n";
    $admin_message .= "📝 <b>Bug Description:</b>\n$bug_report";
    
    sendMessage(ADMIN_ID, $admin_message, null, 'HTML');
    sendMessage($chat_id, "✅ Bug report submitted!\n\n🆔 Report ID: <code>$report_id</code>\n\nWe'll fix it soon! 🛠️", null, 'HTML');
    
    bot_log("Bug report submitted by $user_id: $report_id");
}

function submit_feedback($chat_id, $user_id, $feedback) {
    // User feedback submit karta hai
    $feedback_id = uniqid();
    
    $admin_message = "💡 <b>New User Feedback</b>\n\n";
    $admin_message .= "🆔 Feedback ID: $feedback_id\n";
    $admin_message .= "👤 User ID: $user_id\n";
    $admin_message .= "📅 Time: " . date('Y-m-d H:i:s') . "\n\n";
    $admin_message .= "📝 <b>Feedback:</b>\n$feedback";
    
    sendMessage(ADMIN_ID, $admin_message, null, 'HTML');
    sendMessage($chat_id, "✅ Feedback submitted!\n\n🆔 Feedback ID: <code>$feedback_id</code>\n\nThanks for your input! 🌟", null, 'HTML');
    
    bot_log("Feedback submitted by $user_id: $feedback_id");
}

function show_version_info($chat_id) {
    // Bot version information show karta hai
    $message = "🔄 <b>Bot Version Information</b>\n\n";
    
    $message .= "📱 <b>Current Version:</b> v2.0.0\n";
    $message .= "🆙 <b>Release Date:</b> " . date('Y-m-d') . "\n";
    $message .= "🐛 <b>Status:</b> Stable Release\n\n";
    
    $message .= "🎯 <b>What's New in v2.0.0:</b>\n";
    $message .= "• Complete command overhaul\n";
    $message .= "• Enhanced search algorithm\n";
    $message .= "• User points system\n";
    $message .= "• Leaderboard feature\n";
    $message .= "• Movie request system\n";
    $message .= "• Quality filtering\n";
    $message .= "• Advanced statistics\n";
    $message .= "• Bug fixes & improvements\n\n";
    
    $message .= "📋 <b>Upcoming Features:</b>\n";
    $message .= "• Movie ratings & reviews\n";
    $message .= "• Watchlist feature\n";
    $message .= "• Advanced filters\n";
    $message .= "• User profiles\n";
    $message .= "• More coming soon...\n\n";
    
    $message .= "🐛 <b>Found a bug?</b> Use <code>/report</code>\n";
    $message .= "💡 <b>Suggestions?</b> Use <code>/feedback</code>";
    
    sendMessage($chat_id, $message, null, 'HTML');
}

// ==============================
// GROUP MESSAGE FILTER
// ==============================
function is_valid_movie_query($text) {
    // Group messages filter karta hai, valid movie queries hi allow karta hai
    $text = strtolower(trim($text));
    
    // Commands allow karo
    if (strpos($text, '/') === 0) {
        return true;
    }
    
    // Very short messages block karo
    if (strlen($text) < 3) {
        return false;
    }
    
    // Common group chat phrases block karo
    $invalid_phrases = [
        'good morning', 'good night', 'hello', 'hi ', 'hey ', 'thank you', 'thanks',
        'welcome', 'bye', 'see you', 'ok ', 'okay', 'yes', 'no', 'maybe',
        'how are you', 'whats up', 'anyone', 'someone', 'everyone',
        'problem', 'issue', 'help', 'question', 'doubt', 'query'
    ];
    
    foreach ($invalid_phrases as $phrase) {
        if (strpos($text, $phrase) !== false) {
            return false;
        }
    }
    
    // Movie-like patterns allow karo
    $movie_patterns = [
        'movie', 'film', 'video', 'download', 'watch', 'hd', 'full', 'part',
        'series', 'episode', 'season', 'bollywood', 'hollywood',
        'theater', 'theatre', 'print', 'hdcam', 'camrip'  // Theater keywords bhi allow
    ];
    
    foreach ($movie_patterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return true;
        }
    }
    
    // Agar specific movie jaisa lagta hai
    if (preg_match('/^[a-zA-Z0-9\s\-\.\,]{3,}$/', $text)) {
        return true;
    }
    
    return false;
}

// ==============================
// MOVIE APPEND FUNCTION WITH AUTO-NOTIFICATION
// ==============================
function append_movie($movie_name, $message_id_raw, $date = null, $video_path = '', $quality = 'Unknown', $size = 'Unknown', $language = 'Hindi', $channel_type = 'main') {
    // Movie database mein add karta hai
    global $movie_messages, $movie_cache, $waiting_users;
    
    if (empty(trim($movie_name))) return;
    
    if ($date === null) $date = date('d-m-Y');
    
    // Channel ID determine karo based on type
    $channel_id = '';
    $channel_username = '';
    
    switch ($channel_type) {
        case 'main':
            $channel_id = MAIN_CHANNEL_ID;
            $channel_username = MAIN_CHANNEL;
            break;
        case 'theater':
            $channel_id = THEATER_CHANNEL_ID;
            $channel_username = THEATER_CHANNEL;
            break;
        case 'backup':
        case 'backup2':
            $channel_id = BACKUP_CHANNEL_ID;
            $channel_username = BACKUP_CHANNEL_USERNAME;
            break;
        case 'private':
            $channel_id = '-1003251791991';
            $channel_username = '@private_channel';
            break;
        case 'any':
            $channel_id = '-1003614546520';
            $channel_username = '@any_channel';
            break;
        default:
            $channel_id = MAIN_CHANNEL_ID;
            $channel_username = MAIN_CHANNEL;
    }
    
    $entry = [$movie_name, $message_id_raw, $date, $video_path, $quality, $size, $language, $channel_type, $channel_id, $channel_username];
    
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
        'channel_type' => $channel_type,
        'channel_id' => $channel_id,
        'channel_username' => $channel_username,
        'message_id' => is_numeric($message_id_raw) ? intval($message_id_raw) : null,
        'source_channel' => $channel_id
    ];
    
    if (!isset($movie_messages[$movie])) $movie_messages[$movie] = [];
    $movie_messages[$movie][] = $item;
    $movie_cache = [];

    // Auto-notification to request channel
    $movie_lower = strtolower($movie_name);
    if (!empty($waiting_users[$movie_lower])) {
        $notification_msg = "🔔 <b>Movie Added!</b>\n\n";
        $notification_msg .= "🎬 <b>$movie_name</b> has been added to our collection!\n\n";
        $notification_msg .= "📢 Join: " . get_channel_username_link($channel_type) . " to download\n";
        $notification_msg .= "🔔 " . count($waiting_users[$movie_lower]) . " users were waiting for this movie!\n\n";
        $notification_msg .= "📅 Added: " . $date . "\n";
        $notification_msg .= "📊 Quality: " . $quality . "\n";
        $notification_msg .= "🗣️ Language: " . $language . "\n";
        $notification_msg .= "🎭 Channel: " . get_channel_display_name($channel_type);
        
        sendMessage(MAIN_CHANNEL_ID, $notification_msg, null, 'HTML');
        bot_log("Auto-notification sent for: $movie_name to " . count($waiting_users[$movie_lower]) . " users");
        
        // Waiting users ko notify karo
        foreach ($waiting_users[$movie_lower] as $user_data) {
            list($user_chat_id, $user_id) = $user_data;
            $channel_link = get_channel_username_link($channel_type);
            sendMessage($user_chat_id, "🎉 <b>Good News!</b>\n\nYour requested movie <b>$movie_name</b> has been added!\n\nJoin channel to download: $channel_link", null, 'HTML');
        }
        unset($waiting_users[$movie_lower]);
    }

    update_stats('total_movies', 1);
    bot_log("Movie appended: $movie_name with ID $message_id_raw to $channel_type channel ($channel_id)");
}

// ==============================
// COMPLETE COMMAND HANDLER WITH UPDATED START MESSAGE
// ==============================
function handle_command($chat_id, $user_id, $command, $params = []) {
    // Sab commands handle karta hai
    switch ($command) {
        // ==================== CORE COMMANDS ====================
        case '/start':
            $welcome = "🎬 Welcome to Entertainment Tadka!\n\n";
            
            $welcome .= "📢 <b>How to use this bot:</b>\n";
            $welcome .= "• Simply type any movie name\n";
            $welcome .= "• Use English or Hindi\n";
            $welcome .= "• Add 'theater' for theater prints\n";
            $welcome .= "• Partial names also work\n\n";
            
            $welcome .= "🔍 <b>Examples:</b>\n";
            $welcome .= "• Mandala Murders 2025\n";
            $welcome .= "• Lokah Chapter 1 Chandra 2025\n";
            $welcome .= "• Idli Kadai (2025)\n";
            $welcome .= "• IT - Welcome to Derry (2025) S01\n";
            $welcome .= "• hindi movie\n";
            $welcome .= "• kgf theater print\n\n";
            
            $welcome .= "❌ <b>Don't type:</b>\n";
            $welcome .= "• Technical questions\n";
            $welcome .= "• Player instructions\n";
            $welcome .= "• Non-movie queries\n\n";
            
            $welcome .= "📢 <b>Join Our Channels:</b>\n";
            $welcome .= "🍿 Main: @EntertainmentTadka786\n";
            $welcome .= "📥 Requests: @EntertainmentTadka7860\n";
            $welcome .= "🎭 Theater Prints: @threater_print_movies\n";
            $welcome .= "🔒 Backup: @ETBackup\n\n";
            
            $welcome .= "🔔 <b>New Feature:</b> Request group gets auto-notification when movies are uploaded!\n\n";
            
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
                        ['text' => '🔒 Backup', 'url' => 'https://t.me/ETBackup'],
                        ['text' => '❓ Help', 'callback_data' => 'help_command']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $welcome, $keyboard, 'HTML');
            update_user_activity($user_id, 'daily_login');
            break;

        case '/help':
        case '/commands':
            $help = "🤖 <b>Entertainment Tadka Bot - Complete Guide</b>\n\n";
            
            $help .= "📢 <b>Our Channels:</b>\n";
            $help .= "🍿 Main: " . MAIN_CHANNEL . " - Latest movies\n";
            $help .= "📥 Requests: " . REQUEST_CHANNEL . " - Support & requests\n";
            $help .= "🎭 Theater: " . THEATER_CHANNEL . " - HD prints\n";
            $help .= "🔒 Backup: " . BACKUP_CHANNEL_USERNAME . " - Data protection\n\n";
            
            $help .= "🔔 <b>Auto-notification Feature:</b>\n";
            $help .= "• Request a movie in request channel\n";
            $help .= "• We add it within 24 hours\n";
            $help .= "• Get auto-notification when added!\n";
            $help .= "• Join request channel for updates\n\n";
            
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
            
            $help .= "👤 <b>User Commands:</b>\n";
            $help .= "• <code>/mystats</code> - Your statistics\n";
            $help .= "• <code>/leaderboard</code> - Top users\n";
            $help .= "• <code>/mypoints</code> - Points info\n\n";
            
            $help .= "🔗 <b>Channel Commands:</b>\n";
            $help .= "• <code>/channel</code> - All channels\n";
            $help .= "• <code>/mainchannel</code> - Main channel\n";
            $help .= "• <code>/requestchannel</code> - Requests\n";
            $help .= "• <code>/theaterchannel</code> - Theater prints\n";
            $help .= "• <code>/backupchannel</code> - Backup info\n\n";
            
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
                        ['text' => '🎭 ' . THEATER_CHANNEL, 'url' => 'https://t.me/threater_print_movies'],
                        ['text' => '🔒 ' . BACKUP_CHANNEL_USERNAME, 'url' => 'https://t.me/ETBackup']
                    ],
                    [
                        ['text' => '🎬 Search Movies', 'switch_inline_query_current_chat' => '']
                    ]
                ]
            ];
            
            sendMessage($chat_id, $help, $keyboard, 'HTML');
            break;

        // ==================== SEARCH COMMANDS ====================
        case '/search':
        case '/s':
        case '/find':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: <code>/search movie_name</code>\nExample: <code>/search kgf 2</code>", null, 'HTML');
                return;
            }
            $lang = detect_language($movie_name);
            send_multilingual_response($chat_id, 'searching', $lang);
            advanced_search($chat_id, $movie_name, $user_id);
            break;

        // ==================== BROWSE COMMANDS ====================
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

        case '/quality':
            $quality = isset($params[0]) ? $params[0] : '1080p';
            show_movies_by_quality($chat_id, $quality);
            break;

        case '/language':
            $language = isset($params[0]) ? $params[0] : 'hindi';
            show_movies_by_language($chat_id, $language);
            break;

        case '/theater':
        case '/theatermovies':
        case '/theateronly':
            show_movies_by_quality($chat_id, 'theater');
            break;

        // ==================== QUICK ADD COMMANDS ====================
        case '/quickadd':
        case '/addmovie':
        case '/qa':
            // Check if it's batch add mode
            if (count($params) > 0) {
                $input_data = implode(' ', $params);
                
                // Check if it's multiple lines (batch add)
                if (strpos($input_data, "\n") !== false) {
                    // Batch add mode
                    batch_quick_add_movies($chat_id, $user_id, $input_data);
                } else {
                    // Single movie add
                    quick_add_movie($chat_id, $user_id, $input_data);
                }
            } else {
                // Show format instructions
                show_quickadd_format($chat_id);
            }
            break;

        // ==================== CHANNEL COMMANDS ====================
        case '/theaterchannel':
        case '/theater':
        case '/theaterprints':
            show_theater_channel_info($chat_id);
            break;

        // ==================== REQUEST COMMANDS ====================
        case '/request':
        case '/req':
        case '/requestmovie':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: <code>/request movie_name</code>\nExample: <code>/request Animal Park</code>", null, 'HTML');
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

        case '/requestlimit':
        case '/reqlimit':
            show_request_limit($chat_id, $user_id);
            break;

        // ==================== USER COMMANDS ====================
        case '/mystats':
        case '/mystatistics':
        case '/profile':
            show_user_stats($chat_id, $user_id);
            break;

        case '/mypoints':
        case '/points':
            show_user_points($chat_id, $user_id);
            break;

        case '/leaderboard':
        case '/topusers':
        case '/ranking':
            show_leaderboard($chat_id);
            break;

        // ==================== CHANNEL COMMANDS ====================
        case '/channel':
        case '/channels':
        case '/join':
            show_channel_info($chat_id);
            break;

        case '/mainchannel':
        case '/entertainmenttadka':
            show_main_channel_info($chat_id);
            break;

        case '/requestchannel':
        case '/requests':
        case '/support':
            show_request_channel_info($chat_id);
            break;

        case '/backupchannel':
        case '/etbackup':
            show_backup_channel_info($chat_id);
            break;

        // ==================== INFO COMMANDS ====================
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
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/checkcsv':
        case '/csvdata':
        case '/database':
            $show_all = (isset($params[0]) && strtolower($params[0]) == 'all');
            show_csv_data($chat_id, $show_all);
            break;

        case '/testcsv':
        case '/rawdata':
        case '/export':
            test_csv($chat_id);
            break;

        case '/info':
        case '/about':
        case '/botinfo':
            show_bot_info($chat_id);
            break;

        case '/support':
        case '/contact':
        case '/helpgroup':
            show_support_info($chat_id);
            break;

        case '/version':
        case '/changelog':
            show_version_info($chat_id);
            break;

        // ==================== ADMIN COMMANDS ====================
        case '/broadcast':
            if ($user_id == ADMIN_ID) {
                $message = implode(' ', $params);
                if (empty($message)) {
                    sendMessage($chat_id, "❌ Usage: <code>/broadcast your_message</code>", null, 'HTML');
                    return;
                }
                send_broadcast($chat_id, $message);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/backup':
            if ($user_id == ADMIN_ID) {
                manual_backup($chat_id);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/quickbackup':
        case '/qbackup':
            if ($user_id == ADMIN_ID) {
                quick_backup($chat_id);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/backupstatus':
        case '/backupinfo':
            if ($user_id == ADMIN_ID) {
                backup_status($chat_id);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/maintenance':
            if ($user_id == ADMIN_ID) {
                $mode = isset($params[0]) ? strtolower($params[0]) : '';
                toggle_maintenance_mode($chat_id, $mode);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/cleanup':
            if ($user_id == ADMIN_ID) {
                perform_cleanup($chat_id);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/sendalert':
            if ($user_id == ADMIN_ID) {
                $alert_message = implode(' ', $params);
                if (empty($alert_message)) {
                    sendMessage($chat_id, "❌ Usage: <code>/sendalert your_alert</code>", null, 'HTML');
                    return;
                }
                send_alert_to_all($chat_id, $alert_message);
            } else {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        // ==================== UTILITY COMMANDS ====================
        case '/ping':
        case '/status':
            sendMessage($chat_id, "🏓 <b>Bot Status:</b> ✅ Online\n⏰ <b>Server Time:</b> " . date('Y-m-d H:i:s'), null, 'HTML');
            break;

        case '/donate':
        case '/supportus':
            show_donate_info($chat_id);
            break;

        case '/report':
        case '/reportbug':
            $bug_report = implode(' ', $params);
            if (empty($bug_report)) {
                sendMessage($chat_id, "❌ Usage: <code>/report bug_description</code>", null, 'HTML');
                return;
            }
            submit_bug_report($chat_id, $user_id, $bug_report);
            break;

        case '/feedback':
            $feedback = implode(' ', $params);
            if (empty($feedback)) {
                sendMessage($chat_id, "❌ Usage: <code>/feedback your_feedback</code>", null, 'HTML');
                return;
            }
            submit_feedback($chat_id, $user_id, $feedback);
            break;

        default:
            sendMessage($chat_id, "❌ Unknown command. Use <code>/help</code> to see all available commands.", null, 'HTML');
    }
}

// ==============================
// MAIN UPDATE PROCESSING
// ==============================
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

    // Channel post handling
    if (isset($update['channel_post'])) {
        $message = $update['channel_post'];
        $message_id = $message['message_id'];
        $chat_id = $message['chat']['id'];

        // Determine channel type based on channel ID
        $channel_type = 'other';
        if ($chat_id == MAIN_CHANNEL_ID) {
            $channel_type = 'main';
        } elseif ($chat_id == THEATER_CHANNEL_ID) {
            $channel_type = 'theater';
        } elseif ($chat_id == BACKUP_CHANNEL_ID) {
            $channel_type = 'backup';
        } elseif ($chat_id == '-1003251791991') {
            $channel_type = 'private';
        } elseif ($chat_id == '-1003614546520') {
            $channel_type = 'any';
        } else {
            // Not our known channel, skip
            exit;
        }

        $text = '';
        $quality = 'Unknown';
        $size = 'Unknown';
        $language = 'Hindi';

        if (isset($message['caption'])) {
            $text = $message['caption'];
            // Caption se quality extract karo
            if (stripos($text, '1080') !== false) $quality = '1080p';
            elseif (stripos($text, '720') !== false) $quality = '720p';
            elseif (stripos($text, '480') !== false) $quality = '480p';
            
            // Language extract karo
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
            append_movie($text, $message_id, date('d-m-Y'), '', $quality, $size, $language, $channel_type);
        }
    }

    // Message handling
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        $chat_type = $message['chat']['type'] ?? 'private';

        // User data update karo
        $user_info = [
            'first_name' => $message['from']['first_name'] ?? '',
            'last_name' => $message['from']['last_name'] ?? '',
            'username' => $message['from']['username'] ?? ''
        ];
        update_user_data($user_id, $user_info);

        // Group message filtering
        if ($chat_type !== 'private') {
            if (strpos($text, '/') === 0) {
                // Commands allow karo
            } else {
                if (!is_valid_movie_query($text)) {
                    bot_log("Invalid group message blocked from $chat_id: $text");
                    return;
                }
            }
        }

        // Command handling
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
        
        // Movie selection
        $movie_lower = strtolower($data);
        if (isset($movie_messages[$movie_lower])) {
            $entries = $movie_messages[$movie_lower];
            $cnt = 0;
            
            foreach ($entries as $entry) {
                deliver_item_to_chat($chat_id, $entry);
                usleep(200000);
                $cnt++;
            }
            
            sendMessage($chat_id, "✅ '$data' ke $cnt items ka info mil gaya!\n\n📢 Join our channel to download: " . MAIN_CHANNEL);
            answerCallbackQuery($query['id'], "🎬 $cnt items ka info sent!");
            update_user_activity($user_id, 'download');
        }
        // Pagination controls
        elseif (strpos($data, 'tu_prev_') === 0) {
            $page = (int)str_replace('tu_prev_', '', $data);
            totalupload_controller($chat_id, $page);
            answerCallbackQuery($query['id'], "Page $page");
        }
        elseif (strpos($data, 'tu_next_') === 0) {
            $page = (int)str_replace('tu_next_', '', $data);
            totalupload_controller($chat_id, $page);
            answerCallbackQuery($query['id'], "Page $page");
        }
        elseif (strpos($data, 'tu_view_') === 0) {
            $page = (int)str_replace('tu_view_', '', $data);
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page);
            batch_download_with_progress($chat_id, $pg['slice'], $page);
            answerCallbackQuery($query['id'], "Re-sent current page movies info");
        }
        elseif (strpos($data, 'tu_info_') === 0) {
            $page = (int)str_replace('tu_info_', '', $data);
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page);
            
            $info = "📊 <b>Page Information</b>\n\n";
            $info .= "📄 Page: $page/{$pg['total_pages']}\n";
            $info .= "🎬 Movies: " . count($pg['slice']) . "\n";
            $info .= "📁 Total: {$pg['total']} movies\n\n";
            
            foreach ($pg['slice'] as $index => $movie) {
                $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
                $info .= ($index + 1) . ". $channel_icon {$movie['movie_name']} [{$movie['quality']}]\n";
            }
            
            sendMessage($chat_id, $info, null, 'HTML');
            answerCallbackQuery($query['id'], "Page $page info");
        }
        elseif ($data === 'tu_stop') {
            sendMessage($chat_id, "✅ Pagination stopped. Type /totalupload to start again.");
            answerCallbackQuery($query['id'], "Stopped");
        }
        elseif ($data === 'current_page') {
            answerCallbackQuery($query['id'], "You're on this page");
        }
        // Enhanced Pagination Controls
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
        // Send page batch info
        elseif (strpos($data, 'send_') === 0) {
            $parts = explode('_', $data);
            $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page_num, []);
            batch_download_with_progress($chat_id, $pg['slice'], $page_num);
            answerCallbackQuery($query['id'], "📦 Batch info started!");
        }
        // Preview page
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
                $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
                $preview_msg .= ($i + 1) . ". $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
                $preview_msg .= "   ⭐ " . ($movie['quality'] ?? 'Unknown') . "\n\n";
            }
            
            sendMessage($chat_id, $preview_msg, null, 'HTML');
            answerCallbackQuery($query['id'], "Preview sent");
        }
        // Filter controls
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
            } elseif ($filter_type == 'backup') {
                $filters = ['channel_type' => 'backup'];
                answerCallbackQuery($query['id'], "Backup filter applied");
            } elseif ($filter_type == 'new') {
                // Sort by latest
                answerCallbackQuery($query['id'], "Latest filter applied");
            } elseif ($filter_type == 'pop') {
                // Sort by popularity
                answerCallbackQuery($query['id'], "Popular filter applied");
            } elseif ($filter_type == 'clr') {
                answerCallbackQuery($query['id'], "Filters cleared");
            }
            
            totalupload_controller($chat_id, 1, $filters, $session_id);
        }
        // Theater channel search
        elseif ($data == 'search_theater') {
            sendMessage($chat_id, "🎭 <b>Theater Prints Search</b>\n\nType any movie name to search for theater prints!\n\nExamples:\n<code>kgf 2 theater</code>\n<code>avengers endgame print</code>\n<code>hindi movie theater</code>", null, 'HTML');
            answerCallbackQuery($query['id'], "Search theater movies");
        }
        // Close pagination
        elseif ($data == 'close_' || strpos($data, 'close_') === 0) {
            deleteMessage($chat_id, $message['message_id']);
            sendMessage($chat_id, "🗂️ Pagination closed. Use /totalupload to browse again.");
            answerCallbackQuery($query['id'], "Pagination closed");
        }
        // Movie requests
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
            sendMessage($chat_id, "📝 To request a movie, use:\n<code>/request movie_name</code>\n\nExample: <code>/request Avengers Endgame</code>", null, 'HTML');
            answerCallbackQuery($query['id'], "Request instructions sent");
        }
        elseif ($data === 'request_help') {
            show_request_channel_info($chat_id);
            answerCallbackQuery($query['id'], "Request channel info");
        }
        // User stats
        elseif ($data === 'my_stats') {
            show_user_stats($chat_id, $user_id);
            answerCallbackQuery($query['id'], "Your statistics");
        }
        elseif ($data === 'show_leaderboard') {
            show_leaderboard($chat_id);
            answerCallbackQuery($query['id'], "Leaderboard");
        }
        // Backup commands
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
        // Help command
        elseif ($data === 'help_command') {
            $command = '/help';
            $params = [];
            handle_command($chat_id, $user_id, $command, $params);
            answerCallbackQuery($query['id'], "Help menu");
        }
        // Other callbacks
        elseif ($data === 'refresh_stats') {
            show_user_stats($chat_id, $user_id);
            answerCallbackQuery($query['id'], "Refreshed");
        }
        elseif ($data === 'refresh_leaderboard') {
            show_leaderboard($chat_id);
            answerCallbackQuery($query['id'], "Refreshed");
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
        elseif (strpos($data, 'download_quality_') === 0) {
            $quality = str_replace('download_quality_', '', $data);
            $all = get_all_movies_list();
            $filtered = [];
            foreach ($all as $movie) {
                if (stripos($movie['quality'] ?? '', $quality) !== false) {
                    $filtered[] = $movie;
                }
            }
            batch_download_with_progress($chat_id, $filtered, $quality . " quality");
            answerCallbackQuery($query['id'], "$quality movies info sent");
        }
        elseif (strpos($data, 'download_lang_') === 0) {
            $language = str_replace('download_lang_', '', $data);
            $all = get_all_movies_list();
            $filtered = [];
            foreach ($all as $movie) {
                if (stripos($movie['language'] ?? '', $language) !== false) {
                    $filtered[] = $movie;
                }
            }
            batch_download_with_progress($chat_id, $filtered, $language . " language");
            answerCallbackQuery($query['id'], "$language movies info sent");
        }
        else {
            sendMessage($chat_id, "❌ Movie not found: " . $data . "\n\nTry searching with exact name!");
            answerCallbackQuery($query['id'], "❌ Movie not available");
        }
    }

    // Scheduled tasks
    $current_hour = date('H');
    $current_minute = date('i');

    // Daily auto-backup at 3 AM
    if ($current_hour == AUTO_BACKUP_HOUR && $current_minute == '00') {
        auto_backup();
        bot_log("Daily auto-backup completed");
    }

    // Hourly cache cleanup
    if ($current_minute == '30') { // Every hour at 30 minutes
        global $movie_cache;
        $movie_cache = [];
        bot_log("Hourly cache cleanup");
    }
}

// ==============================
// MANUAL TESTING FUNCTIONS
// ==============================
if (isset($_GET['test_save'])) {
    // Manual testing ke liye movie save function
    function manual_save_to_csv($movie_name, $message_id, $quality = '1080p', $language = 'Hindi', $channel_type = 'main') {
        // Channel ID determine karo
        $channel_id = '';
        $channel_username = '';
        
        switch ($channel_type) {
            case 'main':
                $channel_id = MAIN_CHANNEL_ID;
                $channel_username = MAIN_CHANNEL;
                break;
            case 'theater':
                $channel_id = THEATER_CHANNEL_ID;
                $channel_username = THEATER_CHANNEL;
                break;
            case 'backup':
                $channel_id = BACKUP_CHANNEL_ID;
                $channel_username = BACKUP_CHANNEL_USERNAME;
                break;
            case 'private':
                $channel_id = '-1003251791991';
                $channel_username = '@private_channel';
                break;
            case 'any':
                $channel_id = '-1003614546520';
                $channel_username = '@any_channel';
                break;
        }
        
        $entry = [$movie_name, $message_id, date('d-m-Y'), '', $quality, '1.5GB', $language, $channel_type, $channel_id, $channel_username];
        $handle = fopen(CSV_FILE, "a");
        if ($handle !== FALSE) {
            fputcsv($handle, $entry);
            fclose($handle);
            @chmod(CSV_FILE, 0666);
            return true;
        }
        return false;
    }
    
    // Test movies save karo - sab channels ke liye
    manual_save_to_csv("Metro In Dino (2025)", 1924, "1080p", "Hindi", "main");
    manual_save_to_csv("Metro In Dino 2025 WebRip 480p", 1925, "480p", "Hindi", "main");
    manual_save_to_csv("Metro In Dino (2025) Hindi 720p", 1926, "720p", "Hindi", "main");
    manual_save_to_csv("Animal (2023) Hindi 1080p", 1927, "1080p", "Hindi", "main");
    manual_save_to_csv("Avengers Endgame (2019) English", 1928, "1080p", "English", "main");
    manual_save_to_csv("KGF Chapter 2 (2022) Theater Print", 1929, "1080p", "Hindi", "theater");
    manual_save_to_csv("Pushpa 2 The Rule (2024) Theater", 1930, "1080p", "Hindi", "theater");
    manual_save_to_csv("Backup Movie Test (2025)", 1931, "720p", "Hindi", "backup");
    manual_save_to_csv("Private Channel Movie (2025)", 1932, "1080p", "English", "private");
    manual_save_to_csv("Any Channel Movie (2025)", 1933, "480p", "Hindi", "any");
    
    echo "✅ All 10 movies manually save ho gayi!<br>";
    echo "📊 <a href='?check_csv=1'>Check CSV</a> | ";
    echo "<a href='?setwebhook=1'>Reset Webhook</a> | ";
    echo "<a href='?test_stats=1'>Test Stats</a>";
    exit;
}

if (isset($_GET['check_csv'])) {
    // CSV content check karo
    echo "<h3>CSV Content:</h3>";
    if (file_exists(CSV_FILE)) {
        $lines = file(CSV_FILE);
        foreach ($lines as $line) {
            echo htmlspecialchars($line) . "<br>";
        }
    } else {
        echo "❌ CSV file not found!";
    }
    exit;
}

if (isset($_GET['test_stats'])) {
    // Statistics test karo
    echo "<h3>Bot Statistics:</h3>";
    $stats = get_stats();
    echo "<pre>";
    print_r($stats);
    echo "</pre>";
    
    echo "<h3>User Data:</h3>";
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    echo "<pre>";
    print_r($users_data);
    echo "</pre>";
    exit;
}

// Webhook setup
if (php_sapi_name() === 'cli' || isset($_GET['setwebhook'])) {
    // Webhook setup karo
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
        echo "<p>Main Channel: " . MAIN_CHANNEL . " (" . MAIN_CHANNEL_ID . ")</p>";
        echo "<p>Theater Channel: " . THEATER_CHANNEL . " (" . THEATER_CHANNEL_ID . ")</p>";
        echo "<p>Backup Channel: " . BACKUP_CHANNEL_USERNAME . " (" . BACKUP_CHANNEL_ID . ")</p>";
        echo "<p>Request Channel: " . REQUEST_CHANNEL . "</p>";
        echo "<p>Private Channel: -1003251791991</p>";
        echo "<p>Any Channel: -1003614546520</p>";
    }
    
    echo "<h3>System Status</h3>";
    echo "<p>CSV File: " . (file_exists(CSV_FILE) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Users File: " . (file_exists(USERS_FILE) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Stats File: " . (file_exists(STATS_FILE) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Backup Directory: " . (file_exists(BACKUP_DIR) ? "✅ Exists" : "❌ Missing") . "</p>";
    
    exit;
}

// ============================================================
// PYTHON BOT CODE (Minato Bot) - EMBEDDED AS A SEPARATE MODULE
// ============================================================
/*
This section contains the Python bot code that you wanted to add.
To run this Python bot, you need to:
1. Save this as a separate file (e.g., minato_bot.py)
2. Install requirements: pip install python-telegram-bot
3. Set your BOT_TOKEN and REQUESTS_GROUP_ID
4. Run with: python minato_bot.py

The code is commented out to prevent PHP errors.
Uncomment and save as separate file to use.
*/

/*
import logging
import sqlite3
import json
import re
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import Application, CommandHandler, CallbackQueryHandler, ContextTypes, ConversationHandler, MessageHandler, filters

# ======================== CONFIG ========================
BOT_TOKEN = "YOUR_BOT_TOKEN_HERE"   # Apna token yahan daalo
REQUESTS_GROUP_ID = -1001234567890  # Jis group mein requests aayengi, uska ID (negative number)
CHANNEL_IDS = [-1001234567891, -1001234567892]  # Jin channels se files lene hain
# =========================================================

# States for conversation
SEARCH_QUERY = 1

logging.basicConfig(format='%(asctime)s - %(name)s - %(levelname)s - %(message)s', level=logging.INFO)
logger = logging.getLogger(__name__)

# ======================== DATABASE SETUP ========================
def init_db():
    conn = sqlite3.connect('minato_bot.db')
    c = conn.cursor()
    
    c.execute('''CREATE TABLE IF NOT EXISTS users
                 (user_id INTEGER PRIMARY KEY,
                  settings TEXT)''')
    
    c.execute('''CREATE TABLE IF NOT EXISTS files
                 (id INTEGER PRIMARY KEY AUTOINCREMENT,
                  name TEXT,
                  size TEXT,
                  resolution TEXT,
                  language TEXT,
                  season TEXT,
                  quality TEXT,
                  file_id TEXT,
                  category TEXT,
                  channel_id INTEGER,
                  message_id INTEGER)''')
    conn.commit()
    
    # Sample data (optional)
    c.execute("SELECT COUNT(*) FROM files")
    if c.fetchone()[0] == 0:
        sample_files = [
            ("Baali The Lost Legends S03E01-07", "2.8 GB", "1080p", "Hindi,Tamil,Telugu", "S03", "WEB-DL", "BAQABFhGkC8BAgcHBAo", "series", 0, 0),
            ("Baali The Lost Legends S02E08-13", "2.4 GB", "1080p", "Hindi,Tamil,Telugu", "S02", "WEB-DL", "BAQABFhGkC8BAgcHBAo", "series", 0, 0),
            ("Baali The Lost Legends S01E08-13", "2.6 GB", "1080p", "Hindi,Tamil,Telugu", "S01", "WEB-DL", "BAQABFhGkC8BAgcHBAo", "series", 0, 0),
            ("Baali The Epic 2025", "2.7 GB", "720p", "Hindi", "Movie", "WEB-DL", "BAQABFhGkC8BAgcHBAo", "movie", 0, 0),
            ("Bindiya Ke Baali S01", "2.7 GB", "720p", "Hindi", "S01", "WEBrip", "BAQABFhGkC8BAgcHBAo", "series", 0, 0),
            ("Dhurandhar 2025 1080p WEBRip Hindi x265", "3.02 GB", "1080p", "Hindi", "Movie", "WEBRip", "BAQABFhGkC8BAgcHBAo", "movie", 0, 0),
            ("Tuu Juliet Jatt Di S01E100", "170.25 MB", "720p", "Hindi", "S01E100", "WEB-DL", "BAQABFhGkC8BAgcHBAo", "series", 0, 0),
        ]
        c.executemany("INSERT INTO files (name, size, resolution, language, season, quality, file_id, category, channel_id, message_id) VALUES (?,?,?,?,?,?,?,?,?,?)", sample_files)
        conn.commit()
    conn.close()

init_db()

# ======================== ADVANCED FILE PARSER ========================
def parse_filename(filename):
    """
    Filename se metadata nikalne ka advanced function (updated with all suggestions).
    Returns: (resolution, language, quality, season, category)
    """
    resolution = "Unknown"
    language = "Unknown"
    quality = "Unknown"
    season = "Movie"  # default movie
    category = "movie"

    # ---------- Resolution detect ----------
    res_match = re.search(r'(2160p|4K|1080p|720p|480p|360p)', filename, re.IGNORECASE)
    if res_match:
        resolution = res_match.group(1).upper()
        if resolution == '4K':
            resolution = '2160p'

    # ---------- Language detect (improved) ----------
    lang_keywords = ['Hindi', 'Tamil', 'Telugu', 'English', 'Malayalam', 'Kannada', 'Bengali']
    detected_langs = []
    for lang in lang_keywords:
        pattern = rf'(?<=[^a-zA-Z]|^){lang}(?=[^a-zA-Z]|$)'
        if re.search(pattern, filename, re.IGNORECASE):
            detected_langs.append(lang)
    if detected_langs:
        language = ','.join(detected_langs)
    else:
        language = "Hindi"   # Default language

    # ---------- Quality detect (with more keywords) ----------
    quality_keywords = {
        'WEB-DL': ['WEB-DL', 'WEBDL'],
        'WEBRip': ['WEBRip', 'WEB-Rip'],
        'HDRip': ['HDRip', 'HD-Rip'],
        'BluRay': ['BluRay', 'Blu-Ray', 'BDRip'],
        'HDTC': ['HDTC', 'HD-TC'],
        'CAM': ['CAM', 'HD-CAM'],
        'DVDScr': ['DVDScr'],
        'TVRip': ['TVRip'],
        'HMAX': ['HMAX', 'HBO Max'],           # Added
        'Netflix': ['Netflix', 'NF'],          # Added
        'Prime': ['Prime', 'Amazon Prime'],    # Added
        'Hotstar': ['Hotstar', 'Disney+ Hotstar'],  # Added
    }
    for q, patterns in quality_keywords.items():
        for pat in patterns:
            if re.search(pat, filename, re.IGNORECASE):
                quality = q
                break
        if quality != "Unknown":
            break

    # ---------- Season/Episode detect ----------
    season_episode_match = re.search(r'[Ss](\d{1,2})[Ee](\d{1,2})', filename)
    if season_episode_match:
        season_num = int(season_episode_match.group(1))
        episode_num = int(season_episode_match.group(2))
        season = f"S{season_num:02d}E{episode_num:02d}"
        category = "series"
    else:
        season_match = re.search(r'[Ss](\d{1,2})', filename)
        episode_match = re.search(r'[Ee](\d{1,2})', filename)
        if season_match and episode_match:
            season_num = int(season_match.group(1))
            episode_num = int(episode_match.group(1))
            season = f"S{season_num:02d}E{episode_num:02d}"
            category = "series"
        elif season_match:
            season_num = int(season_match.group(1))
            season = f"S{season_num:02d}"
            category = "series"
        elif episode_match:
            episode_num = int(episode_match.group(1))
            season = f"E{episode_num:02d}"
            category = "series"
        else:
            season_word = re.search(r'Season[.\s]*(\d{1,2})', filename, re.IGNORECASE)
            if season_word:
                season_num = int(season_word.group(1))
                season = f"S{season_num:02d}"
                category = "series"
            else:
                category = "movie"
                season = "Movie"

    return resolution, language, quality, season, category

# ======================== DATABASE FUNCTIONS ========================
def get_user_settings_db(user_id):
    conn = sqlite3.connect('minato_bot.db')
    c = conn.cursor()
    c.execute("SELECT settings FROM users WHERE user_id=?", (user_id,))
    row = c.fetchone()
    if row:
        settings = json.loads(row[0])
    else:
        settings = {
            "auto_scan": True,
            "spoiler_mode": False,
            "top_search": False,
            "priority": "Size",
            "layout": "BTN",
            "file_delete": 50,
        }
        c.execute("INSERT INTO users (user_id, settings) VALUES (?, ?)", (user_id, json.dumps(settings)))
        conn.commit()
    conn.close()
    return settings

def save_user_settings_db(user_id, settings):
    conn = sqlite3.connect('minato_bot.db')
    c = conn.cursor()
    c.execute("UPDATE users SET settings=? WHERE user_id=?", (json.dumps(settings), user_id))
    conn.commit()
    conn.close()

def insert_file_from_channel(name, size, resolution, language, season, quality, file_id, category, channel_id, message_id):
    conn = sqlite3.connect('minato_bot.db')
    c = conn.cursor()
    c.execute("SELECT id FROM files WHERE file_id=? OR (channel_id=? AND message_id=?)", (file_id, channel_id, message_id))
    if c.fetchone():
        conn.close()
        return False
    c.execute("INSERT INTO files (name, size, resolution, language, season, quality, file_id, category, channel_id, message_id) VALUES (?,?,?,?,?,?,?,?,?,?)",
              (name, size, resolution, language, season, quality, file_id, category, channel_id, message_id))
    conn.commit()
    conn.close()
    return True

def search_files_db(query=None, filters=None, page=1, per_page=5):
    conn = sqlite3.connect('minato_bot.db')
    c = conn.cursor()
    sql = "SELECT name, size, resolution, language, season, quality, file_id FROM files WHERE 1=1"
    params = []
    if query:
        sql += " AND name LIKE ?"
        params.append(f"%{query}%")
    if filters:
        if 'resolution' in filters:
            sql += " AND resolution=?"
            params.append(filters['resolution'])
        if 'language' in filters:
            sql += " AND language LIKE ?"
            params.append(f"%{filters['language']}%")
        if 'season' in filters:
            sql += " AND season=?"
            params.append(filters['season'])
        if 'quality' in filters:
            sql += " AND quality=?"
            params.append(filters['quality'])
    offset = (page - 1) * per_page
    sql += " LIMIT ? OFFSET ?"
    params.extend([per_page, offset])
    c.execute(sql, params)
    results = c.fetchall()
    
    count_sql = "SELECT COUNT(*) FROM files WHERE 1=1"
    count_params = []
    if query:
        count_sql += " AND name LIKE ?"
        count_params.append(f"%{query}%")
    if filters:
        if 'resolution' in filters:
            count_sql += " AND resolution=?"
            count_params.append(filters['resolution'])
        if 'language' in filters:
            count_sql += " AND language LIKE ?"
            count_params.append(f"%{filters['language']}%")
        if 'season' in filters:
            count_sql += " AND season=?"
            count_params.append(filters['season'])
        if 'quality' in filters:
            count_sql += " AND quality=?"
            count_params.append(filters['quality'])
    c.execute(count_sql, count_params)
    total = c.fetchone()[0]
    conn.close()
    return results, total

def get_files_by_category(category, filters=None, page=1, per_page=5):
    conn = sqlite3.connect('minato_bot.db')
    c = conn.cursor()
    sql = "SELECT name, size, resolution, language, season, quality, file_id FROM files WHERE category=?"
    params = [category]
    if filters:
        if 'resolution' in filters:
            sql += " AND resolution=?"
            params.append(filters['resolution'])
        if 'language' in filters:
            sql += " AND language LIKE ?"
            params.append(f"%{filters['language']}%")
        if 'season' in filters:
            sql += " AND season=?"
            params.append(filters['season'])
        if 'quality' in filters:
            sql += " AND quality=?"
            params.append(filters['quality'])
    offset = (page - 1) * per_page
    sql += " LIMIT ? OFFSET ?"
    params.extend([per_page, offset])
    c.execute(sql, params)
    results = c.fetchall()
    
    count_sql = "SELECT COUNT(*) FROM files WHERE category=?"
    count_params = [category]
    if filters:
        if 'resolution' in filters:
            count_sql += " AND resolution=?"
            count_params.append(filters['resolution'])
        if 'language' in filters:
            count_sql += " AND language LIKE ?"
            count_params.append(f"%{filters['language']}%")
        if 'season' in filters:
            count_sql += " AND season=?"
            count_params.append(filters['season'])
        if 'quality' in filters:
            count_sql += " AND quality=?"
            count_params.append(filters['quality'])
    c.execute(count_sql, count_params)
    total = c.fetchone()[0]
    conn.close()
    return results, total

# ======================== HELPER FUNCTIONS ========================
async def show_typing(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await context.bot.send_chat_action(chat_id=update.effective_chat.id, action="typing")

# ======================== CHANNEL MESSAGE HANDLER ========================
async def channel_message_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if update.channel_post and update.channel_post.document:
        doc = update.channel_post.document
        file_name = doc.file_name or "Unknown"
        file_size = f"{doc.file_size / (1024*1024):.2f} MB" if doc.file_size < 1024*1024*1024 else f"{doc.file_size / (1024*1024*1024):.2f} GB"
        file_id = doc.file_id
        channel_id = update.channel_post.chat_id
        message_id = update.channel_post.message_id
        
        resolution, language, quality, season, category = parse_filename(file_name)
        logger.info(f"📄 File parsed: {resolution} | {language} | {quality} | {season} | {category}")
        
        inserted = insert_file_from_channel(
            name=file_name,
            size=file_size,
            resolution=resolution,
            language=language,
            season=season,
            quality=quality,
            file_id=file_id,
            category=category,
            channel_id=channel_id,
            message_id=message_id
        )
        if inserted:
            logger.info(f"✅ Naya file add hua: {file_name}")
        else:
            logger.info(f"⏩ File already exists: {file_name}")

# ======================== GROUP REQUEST HANDLER ========================
async def group_request_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if update.message and update.message.chat_id == REQUESTS_GROUP_ID and update.message.text and not update.message.text.startswith('/'):
        query_text = update.message.text.strip()
        if not query_text:
            return
        
        await show_typing(update, context)
        
        results, total = search_files_db(query=query_text, page=1, per_page=5)
        total_pages = (total + 4) // 5 if total else 1
        
        context.user_data['group_search_query'] = query_text
        context.user_data['group_search_filters'] = {}
        context.user_data['group_search_page'] = 1
        
        text = f"🔍 **Group Search Results for: {query_text}**\n"
        text += f"**Page 1/{total_pages}**\n\n"
        
        if not results:
            text += "Koi result nahi mila."
            await update.message.reply_text(text, parse_mode="Markdown")
            return
        
        for i, res in enumerate(results, 1):
            name, size, resolution, lang, season, quality, file_id = res
            text += f"{i}. {name}\n   {size} | {resolution} | {lang} | {quality}\n\n"
        
        filter_row = [
            InlineKeyboardButton(f"Quality", callback_data="group_filter_quality"),
            InlineKeyboardButton(f"Language", callback_data="group_filter_lang"),
            InlineKeyboardButton(f"Season", callback_data="group_filter_season"),
        ]
        pagination_btns = []
        if total_pages > 1:
            pagination_btns.append(InlineKeyboardButton("Next ➡️", callback_data=f"group_page_2"))
        
        keyboard = [
            filter_row,
            pagination_btns,
            [InlineKeyboardButton("📤 SEND ALL", callback_data="group_send_all")],
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        
        await update.message.reply_text(text, reply_markup=reply_markup, parse_mode="Markdown")

# ======================== /start COMMAND (UPDATED) ========================
async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await show_typing(update, context)
    
    welcome_msg = """
🎬 **Welcome to Entertainment Tadka!**

📢 **How to use this bot:**
• Simply type any movie name
• Use English or Hindi
• Partial names also work

🔍 **Examples:**
• Mandala Murders 2025
• Lokah Chapter 1 Chandra 2025
• Idli Kadai (2025)
• IT - Welcome to Derry (2025) S01
• hindi movie
• kgf theater print

❌ **Don't type:**
• Technical questions
• Player instructions
• Non-movie queries
    """

    keyboard = [
        [InlineKeyboardButton("📢 Join Main Channel", url="https://t.me/EntertainmentTadka786")],
        [InlineKeyboardButton("📺 Serial Channel", url="https://t.me/Entertainment_Tadka_Serial_786"),
         InlineKeyboardButton("🎭 Theater Channel", url="https://t.me/threater_print_movies")],
        [InlineKeyboardButton("📥 Requests Group", url="https://t.me/EntertainmentTadka7860"),
         InlineKeyboardButton("🔒 Backup Channel", url="https://t.me/ETBackup")],
        [InlineKeyboardButton("❓ Help / Commands", callback_data="help"),
         InlineKeyboardButton("🔍 Search Movie", callback_data="search")],
        [InlineKeyboardButton("📁 Total Uploads", callback_data="total_uploads"),
         InlineKeyboardButton("📝 Request Movie", callback_data="request_movie")],
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    await update.message.reply_text(welcome_msg, reply_markup=reply_markup, parse_mode="Markdown")

# ======================== /help COMMAND (UPDATED) ========================
async def help_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await show_typing(update, context)

    help_msg = """
🤖 **Entertainment Tadka Bot - Complete Guide**

📢 **Join Our Channels:**
🍿 Main: @EntertainmentTadka786
📺 Serial: @Entertainment_Tadka_Serial_786
🎭 Theater: @threater_print_movies
📥 Requests: @EntertainmentTadka7860
🔒 Backup: @ETBackup

• Request a movie in request Group

🎯 **Search Commands:**
• Just type movie name - Smart search
• /search movie - Direct search

📁 **Browse Commands:**
• /totalupload - All movies

📝 **Request Commands:**
• /request movie - Request movie
• /myrequests - Request status
• /userequests - Request status
• /pendingrequests - Request status
• /totalrequests - Request status
• Join @EntertainmentTadka7860 for support

🔗 **Channel Commands:**
• /channel - All channels
    """

    keyboard = [
        [InlineKeyboardButton("📢 Join Main Channel", url="https://t.me/EntertainmentTadka786")],
        [InlineKeyboardButton("📺 Serial Channel", url="https://t.me/Entertainment_Tadka_Serial_786"),
         InlineKeyboardButton("🎭 Theater Channel", url="https://t.me/threater_print_movies")],
        [InlineKeyboardButton("📥 Requests Group", url="https://t.me/EntertainmentTadka7860"),
         InlineKeyboardButton("🔒 Backup Channel", url="https://t.me/ETBackup")],
        [InlineKeyboardButton("🏠 Back to Main Menu", callback_data="back_to_main")],
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)

    await update.message.reply_text(help_msg, reply_markup=reply_markup, parse_mode="Markdown")

# ======================== CALLBACK HANDLER ========================
async def button_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    await query.answer()
    user_id = update.effective_user.id
    data = query.data

    if data == "back_to_main":
        await show_typing(update, context)
        keyboard = [
            [InlineKeyboardButton("🔍 Search", callback_data="search")],
            [InlineKeyboardButton("⚙️ Settings", callback_data="settings")],
            [InlineKeyboardButton("🎬 Dhurandhar Files", callback_data="show_dhurandhar")],
            [InlineKeyboardButton("💕 Juliet Files", callback_data="show_juliet")],
            [InlineKeyboardButton("❓ Help", callback_data="help")],
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        await query.edit_message_text("Namaste! Main Minato Bot hoon. Kya karoge aaj?", reply_markup=reply_markup)
        return

    if data == "help":
        await show_typing(update, context)
        help_msg = """
🤖 **Entertainment Tadka Bot - Complete Guide**

📢 **Join Our Channels:**
🍿 Main: @EntertainmentTadka786
📺 Serial: @Entertainment_Tadka_Serial_786
🎭 Theater: @threater_print_movies
📥 Requests: @EntertainmentTadka7860
🔒 Backup: @ETBackup

• Request a movie in request Group

🎯 **Search Commands:**
• Just type movie name - Smart search
• /search movie - Direct search

📁 **Browse Commands:**
• /totalupload - All movies

📝 **Request Commands:**
• /request movie - Request movie
• /myrequests - Request status
• /userequests - Request status
• /pendingrequests - Request status
• /totalrequests - Request status
• Join @EntertainmentTadka7860 for support

🔗 **Channel Commands:**
• /channel - All channels
        """
        keyboard = [
            [InlineKeyboardButton("📢 Join Main Channel", url="https://t.me/EntertainmentTadka786")],
            [InlineKeyboardButton("📺 Serial Channel", url="https://t.me/Entertainment_Tadka_Serial_786"),
             InlineKeyboardButton("🎭 Theater Channel", url="https://t.me/threater_print_movies")],
            [InlineKeyboardButton("📥 Requests Group", url="https://t.me/EntertainmentTadka7860"),
             InlineKeyboardButton("🔒 Backup Channel", url="https://t.me/ETBackup")],
            [InlineKeyboardButton("🏠 Main Menu", callback_data="back_to_main")],
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        await query.edit_message_text(help_msg, reply_markup=reply_markup, parse_mode="Markdown")
        return

    if data == "search":
        await show_typing(update, context)
        await query.edit_message_text("Please use /search command to search.")
        return

    if data == "total_uploads":
        await show_typing(update, context)
        await query.edit_message_text("📁 Total uploads feature coming soon!")
        return

    if data == "request_movie":
        await show_typing(update, context)
        await query.edit_message_text("📝 Please use /request command to request a movie.")
        return

    # Group search pagination
    if data.startswith("group_page_"):
        page = int(data.split("_")[2])
        query_text = context.user_data.get('group_search_query', '')
        filters = context.user_data.get('group_search_filters', {})
        
        results, total = search_files_db(query=query_text, filters=filters, page=page)
        total_pages = (total + 4) // 5 if total else 1
        
        text = f"🔍 **Group Search Results for: {query_text}**\n"
        if filters:
            text += f"Filters: {filters}\n"
        text += f"**Page {page}/{total_pages}**\n\n"
        
        for i, res in enumerate(results, 1):
            name, size, resolution, lang, season, quality, file_id = res
            text += f"{i}. {name}\n   {size} | {resolution} | {lang} | {quality}\n\n"
        
        filter_row = [
            InlineKeyboardButton(f"Quality", callback_data="group_filter_quality"),
            InlineKeyboardButton(f"Language", callback_data="group_filter_lang"),
            InlineKeyboardButton(f"Season", callback_data="group_filter_season"),
        ]
        pagination_btns = []
        if page > 1:
            pagination_btns.append(InlineKeyboardButton("⬅️ Prev", callback_data=f"group_page_{page-1}"))
        if page < total_pages:
            pagination_btns.append(InlineKeyboardButton("Next ➡️", callback_data=f"group_page_{page+1}"))
        
        keyboard = [
            filter_row,
            pagination_btns,
            [InlineKeyboardButton("📤 SEND ALL", callback_data="group_send_all")],
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        await query.edit_message_text(text, reply_markup=reply_markup, parse_mode="Markdown")
        return

    # Group filters
    if data == "group_filter_quality":
        await query.edit_message_text("Choose Quality:", reply_markup=InlineKeyboardMarkup([
            [InlineKeyboardButton("1080p", callback_data="group_set_quality_1080p")],
            [InlineKeyboardButton("720p", callback_data="group_set_quality_720p")],
            [InlineKeyboardButton("480p", callback_data="group_set_quality_480p")],
            [InlineKeyboardButton("Clear Filter", callback_data="group_clear_quality")],
        ]))
        return
    if data.startswith("group_set_quality_"):
        quality = data.split("_")[3]
        context.user_data['group_search_filters']['quality'] = quality
        context.user_data['group_search_page'] = 1
        await update_group_search_results(update, context, page=1)
        return
    if data == "group_clear_quality":
        context.user_data['group_search_filters'].pop('quality', None)
        context.user_data['group_search_page'] = 1
        await update_group_search_results(update, context, page=1)
        return

    if data == "group_filter_lang":
        await query.edit_message_text("Choose Language:", reply_markup=InlineKeyboardMarkup([
            [InlineKeyboardButton("Hindi", callback_data="group_set_lang_Hindi")],
            [InlineKeyboardButton("Tamil", callback_data="group_set_lang_Tamil")],
            [InlineKeyboardButton("Telugu", callback_data="group_set_lang_Telugu")],
            [InlineKeyboardButton("Clear Filter", callback_data="group_clear_lang")],
        ]))
        return
    if data.startswith("group_set_lang_"):
        lang = data.split("_")[3]
        context.user_data['group_search_filters']['language'] = lang
        context.user_data['group_search_page'] = 1
        await update_group_search_results(update, context, page=1)
        return
    if data == "group_clear_lang":
        context.user_data['group_search_filters'].pop('language', None)
        context.user_data['group_search_page'] = 1
        await update_group_search_results(update, context, page=1)
        return

    if data == "group_filter_season":
        await query.edit_message_text("Choose Season:", reply_markup=InlineKeyboardMarkup([
            [InlineKeyboardButton("S01", callback_data="group_set_season_S01")],
            [InlineKeyboardButton("S02", callback_data="group_set_season_S02")],
            [InlineKeyboardButton("S03", callback_data="group_set_season_S03")],
            [InlineKeyboardButton("Movie", callback_data="group_set_season_Movie")],
            [InlineKeyboardButton("Clear Filter", callback_data="group_clear_season")],
        ]))
        return
    if data.startswith("group_set_season_"):
        season = data.split("_")[3]
        context.user_data['group_search_filters']['season'] = season
        context.user_data['group_search_page'] = 1
        await update_group_search_results(update, context, page=1)
        return
    if data == "group_clear_season":
        context.user_data['group_search_filters'].pop('season', None)
        context.user_data['group_search_page'] = 1
        await update_group_search_results(update, context, page=1)
        return

    if data == "group_send_all":
        await show_typing(update, context)
        query_text = context.user_data.get('group_search_query', '')
        filters = context.user_data.get('group_search_filters', {})
        results, total = search_files_db(query=query_text, filters=filters, page=1, per_page=1000)
        sent_count = 0
        for name, size, resolution, lang, season, quality, file_id in results:
            try:
                await context.bot.send_document(chat_id=update.effective_chat.id, document=file_id, caption=f"{name} ({size})")
                sent_count += 1
            except Exception as e:
                logger.error(f"Error sending file {name}: {e}")
        await query.edit_message_text(f"✅ {sent_count} files send kar di gayin!")
        return

    # Regular search pagination
    if data.startswith("search_page_"):
        page = int(data.split("_")[2])
        context.user_data['search_page'] = page
        await show_search_results(update, context, page)
        return

    # Search filters
    if data == "search_filter_quality":
        await query.edit_message_text("Choose Quality:", reply_markup=InlineKeyboardMarkup([
            [InlineKeyboardButton("1080p", callback_data="search_set_quality_1080p")],
            [InlineKeyboardButton("720p", callback_data="search_set_quality_720p")],
            [InlineKeyboardButton("480p", callback_data="search_set_quality_480p")],
            [InlineKeyboardButton("Clear Filter", callback_data="search_clear_quality")],
        ]))
        return
    if data.startswith("search_set_quality_"):
        quality = data.split("_")[3]
        context.user_data['search_filters']['quality'] = quality
        context.user_data['search_page'] = 1
        await show_search_results(update, context, 1)
        return
    if data == "search_clear_quality":
        context.user_data['search_filters'].pop('quality', None)
        context.user_data['search_page'] = 1
        await show_search_results(update, context, 1)
        return

    if data == "search_filter_lang":
        await query.edit_message_text("Choose Language:", reply_markup=InlineKeyboardMarkup([
            [InlineKeyboardButton("Hindi", callback_data="search_set_lang_Hindi")],
            [InlineKeyboardButton("Tamil", callback_data="search_set_lang_Tamil")],
            [InlineKeyboardButton("Telugu", callback_data="search_set_lang_Telugu")],
            [InlineKeyboardButton("Clear Filter", callback_data="search_clear_lang")],
        ]))
        return
    if data.startswith("search_set_lang_"):
        lang = data.split("_")[3]
        context.user_data['search_filters']['language'] = lang
        context.user_data['search_page'] = 1
        await show_search_results(update, context, 1)
        return
    if data == "search_clear_lang":
        context.user_data['search_filters'].pop('language', None)
        context.user_data['search_page'] = 1
        await show_search_results(update, context, 1)
        return

    if data == "search_filter_season":
        await query.edit_message_text("Choose Season:", reply_markup=InlineKeyboardMarkup([
            [InlineKeyboardButton("S01", callback_data="search_set_season_S01")],
            [InlineKeyboardButton("S02", callback_data="search_set_season_S02")],
            [InlineKeyboardButton("S03", callback_data="search_set_season_S03")],
            [InlineKeyboardButton("Movie", callback_data="search_set_season_Movie")],
            [InlineKeyboardButton("Clear Filter", callback_data="search_clear_season")],
        ]))
        return
    if data.startswith("search_set_season_"):
        season = data.split("_")[3]
        context.user_data['search_filters']['season'] = season
        context.user_data['search_page'] = 1
        await show_search_results(update, context, 1)
        return
    if data == "search_clear_season":
        context.user_data['search_filters'].pop('season', None)
        context.user_data['search_page'] = 1
        await show_search_results(update, context, 1)
        return

    if data == "search_send_all":
        await show_typing(update, context)
        query_text = context.user_data.get('search_query', '')
        filters = context.user_data.get('search_filters', {})
        results, total = search_files_db(query=query_text, filters=filters, page=1, per_page=1000)
        sent_count = 0
        for name, size, resolution, lang, season, quality, file_id in results:
            try:
                await context.bot.send_document(chat_id=update.effective_chat.id, document=file_id, caption=f"{name} ({size})")
                sent_count += 1
            except Exception as e:
                logger.error(f"Error sending file {name}: {e}")
        await query.edit_message_text(f"✅ {sent_count} files send kar di gayin!")
        return

    # Settings toggles
    if data in ["toggle_auto_scan", "toggle_spoiler", "toggle_top_search", "change_priority", "change_layout", "change_delete", "reset_defaults"]:
        settings = get_user_settings_db(user_id)
        if data == "toggle_auto_scan":
            settings["auto_scan"] = not settings["auto_scan"]
        elif data == "toggle_spoiler":
            settings["spoiler_mode"] = not settings["spoiler_mode"]
        elif data == "toggle_top_search":
            settings["top_search"] = not settings["top_search"]
        elif data == "change_priority":
            settings["priority"] = "Quality" if settings["priority"] == "Size" else "Size"
        elif data == "change_layout":
            settings["layout"] = "TXT" if settings["layout"] == "BTN" else "BTN"
        elif data == "change_delete":
            times = [10, 30, 50, 90, 120, 150, 200]
            current = settings["file_delete"]
            idx = times.index(current) if current in times else 2
            next_idx = (idx + 1) % len(times)
            settings["file_delete"] = times[next_idx]
        elif data == "reset_defaults":
            settings = {
                "auto_scan": True,
                "spoiler_mode": False,
                "top_search": False,
                "priority": "Size",
                "layout": "BTN",
                "file_delete": 50,
            }
        save_user_settings_db(user_id, settings)
        await settings(update, context)
        return

    if data == "settings":
        await settings(update, context)
        return

    if data == "show_dhurandhar":
        context.user_data['movie_filters'] = {}
        await show_category_files(update, context, "movie", page=1)
        return

    if data == "show_juliet":
        context.user_data['series_filters'] = {}
        await show_category_files(update, context, "series", page=1)
        return

    if data.startswith("movie_page_"):
        page = int(data.split("_")[2])
        await show_category_files(update, context, "movie", page)
        return

    if data.startswith("series_page_"):
        page = int(data.split("_")[2])
        await show_category_files(update, context, "series", page)
        return

    # Movie filters
    if data.startswith("movie_filter_"):
        filter_type = data.split("_")[2]
        if filter_type == "quality":
            await query.edit_message_text("Choose Quality:", reply_markup=InlineKeyboardMarkup([
                [InlineKeyboardButton("1080p", callback_data="movie_set_quality_1080p")],
                [InlineKeyboardButton("720p", callback_data="movie_set_quality_720p")],
                [InlineKeyboardButton("480p", callback_data="movie_set_quality_480p")],
                [InlineKeyboardButton("Clear Filter", callback_data="movie_clear_quality")],
            ]))
        elif filter_type == "lang":
            await query.edit_message_text("Choose Language:", reply_markup=InlineKeyboardMarkup([
                [InlineKeyboardButton("Hindi", callback_data="movie_set_lang_Hindi")],
                [InlineKeyboardButton("Tamil", callback_data="movie_set_lang_Tamil")],
                [InlineKeyboardButton("Telugu", callback_data="movie_set_lang_Telugu")],
                [InlineKeyboardButton("Clear Filter", callback_data="movie_clear_lang")],
            ]))
        elif filter_type == "season":
            await query.edit_message_text("Choose Season:", reply_markup=InlineKeyboardMarkup([
                [InlineKeyboardButton("Movie", callback_data="movie_set_season_Movie")],
                [InlineKeyboardButton("S01", callback_data="movie_set_season_S01")],
                [InlineKeyboardButton("Clear Filter", callback_data="movie_clear_season")],
            ]))
        return

    if data.startswith("movie_set_quality_"):
        quality = data.split("_")[3]
        context.user_data['movie_filters']['quality'] = quality
        await show_category_files(update, context, "movie", page=1)
        return
    if data == "movie_clear_quality":
        context.user_data['movie_filters'].pop('quality', None)
        await show_category_files(update, context, "movie", page=1)
        return
    if data.startswith("movie_set_lang_"):
        lang = data.split("_")[3]
        context.user_data['movie_filters']['language'] = lang
        await show_category_files(update, context, "movie", page=1)
        return
    if data == "movie_clear_lang":
        context.user_data['movie_filters'].pop('language', None)
        await show_category_files(update, context, "movie", page=1)
        return
    if data.startswith("movie_set_season_"):
        season = data.split("_")[3]
        context.user_data['movie_filters']['season'] = season
        await show_category_files(update, context, "movie", page=1)
        return
    if data == "movie_clear_season":
        context.user_data['movie_filters'].pop('season', None)
        await show_category_files(update, context, "movie", page=1)
        return

    # Series filters
    if data.startswith("series_filter_"):
        filter_type = data.split("_")[2]
        if filter_type == "quality":
            await query.edit_message_text("Choose Quality:", reply_markup=InlineKeyboardMarkup([
                [InlineKeyboardButton("1080p", callback_data="series_set_quality_1080p")],
                [InlineKeyboardButton("720p", callback_data="series_set_quality_720p")],
                [InlineKeyboardButton("480p", callback_data="series_set_quality_480p")],
                [InlineKeyboardButton("Clear Filter", callback_data="series_clear_quality")],
            ]))
        elif filter_type == "lang":
            await query.edit_message_text("Choose Language:", reply_markup=InlineKeyboardMarkup([
                [InlineKeyboardButton("Hindi", callback_data="series_set_lang_Hindi")],
                [InlineKeyboardButton("Tamil", callback_data="series_set_lang_Tamil")],
                [InlineKeyboardButton("Telugu", callback_data="series_set_lang_Telugu")],
                [InlineKeyboardButton("Clear Filter", callback_data="series_clear_lang")],
            ]))
        elif filter_type == "season":
            await query.edit_message_text("Choose Season:", reply_markup=InlineKeyboardMarkup([
                [InlineKeyboardButton("S01", callback_data="series_set_season_S01")],
                [InlineKeyboardButton("S02", callback_data="series_set_season_S02")],
                [InlineKeyboardButton("S03", callback_data="series_set_season_S03")],
                [InlineKeyboardButton("Clear Filter", callback_data="series_clear_season")],
            ]))
        return

    if data.startswith("series_set_quality_"):
        quality = data.split("_")[3]
        context.user_data['series_filters']['quality'] = quality
        await show_category_files(update, context, "series", page=1)
        return
    if data == "series_clear_quality":
        context.user_data['series_filters'].pop('quality', None)
        await show_category_files(update, context, "series", page=1)
        return
    if data.startswith("series_set_lang_"):
        lang = data.split("_")[3]
        context.user_data['series_filters']['language'] = lang
        await show_category_files(update, context, "series", page=1)
        return
    if data == "series_clear_lang":
        context.user_data['series_filters'].pop('language', None)
        await show_category_files(update, context, "series", page=1)
        return
    if data.startswith("series_set_season_"):
        season = data.split("_")[3]
        context.user_data['series_filters']['season'] = season
        await show_category_files(update, context, "series", page=1)
        return
    if data == "series_clear_season":
        context.user_data['series_filters'].pop('season', None)
        await show_category_files(update, context, "series", page=1)
        return

    # Send all for categories
    if data.startswith("send_all_"):
        category = data.split("_")[2]
        await show_typing(update, context)
        filters = context.user_data.get(f'{category}_filters', {})
        results, total = get_files_by_category(category, filters=filters, page=1, per_page=1000)
        sent_count = 0
        for name, size, resolution, lang, season, quality, file_id in results:
            try:
                await context.bot.send_document(chat_id=update.effective_chat.id, document=file_id, caption=f"{name} ({size})")
                sent_count += 1
            except Exception as e:
                logger.error(f"Error sending file {name}: {e}")
        await query.edit_message_text(f"✅ {sent_count} files send kar di gayin!")
        return

# ======================== SEARCH HANDLERS ========================
async def search_start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await show_typing(update, context)
    await update.message.reply_text("Kya search karna chahte ho? (Movie/Series name likho)")
    return SEARCH_QUERY

async def search_query_received(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query_text = update.message.text
    context.user_data['search_query'] = query_text
    context.user_data['search_filters'] = {}
    context.user_data['search_page'] = 1
    await show_search_results(update, context)
    return ConversationHandler.END

async def show_search_results(update: Update, context: ContextTypes.DEFAULT_TYPE, page=1):
    await show_typing(update, context)
    query_text = context.user_data.get('search_query', '')
    filters = context.user_data.get('search_filters', {})
    
    results, total = search_files_db(query=query_text, filters=filters, page=page)
    total_pages = (total + 4) // 5 if total else 1

    text = f"🔍 **Search Results for: {query_text}**\n"
    if filters:
        text += f"Filters: {filters}\n"
    text += f"**Page {page}/{total_pages}**\n\n"
    
    if not results:
        text += "Koi result nahi mila."
    else:
        for i, res in enumerate(results, 1):
            name, size, resolution, lang, season, quality, file_id = res
            text += f"{i}. {name}\n   {size} | {resolution} | {lang} | {quality}\n\n"

    filter_row = [
        InlineKeyboardButton(f"Quality", callback_data="search_filter_quality"),
        InlineKeyboardButton(f"Language", callback_data="search_filter_lang"),
        InlineKeyboardButton(f"Season", callback_data="search_filter_season"),
    ]
    pagination_btns = []
    if page > 1:
        pagination_btns.append(InlineKeyboardButton("⬅️ Prev", callback_data=f"search_page_{page-1}"))
    if page < total_pages:
        pagination_btns.append(InlineKeyboardButton("Next ➡️", callback_data=f"search_page_{page+1}"))
    
    keyboard = [
        filter_row,
        pagination_btns,
        [InlineKeyboardButton("📤 SEND ALL", callback_data="search_send_all")],
        [InlineKeyboardButton("⚙️ Personalize Settings", callback_data="settings"),
         InlineKeyboardButton("🔙 Main Menu", callback_data="back_to_main")],
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)

    if update.callback_query:
        await update.callback_query.edit_message_text(text, reply_markup=reply_markup, parse_mode="Markdown")
    else:
        await update.message.reply_text(text, reply_markup=reply_markup, parse_mode="Markdown")

async def search_cancel(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text("Search cancelled.")
    return ConversationHandler.END

async def settings(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    settings = get_user_settings_db(user_id)

    auto_scan_text = f"Auto Scan: {'✅' if settings['auto_scan'] else '❌'}"
    spoiler_text = f"Spoiler Mode: {'✅' if settings['spoiler_mode'] else '❌'}"
    top_search_text = f"Top Search: {'✅' if settings['top_search'] else '❌'}"
    priority_text = f"1st Priority: {settings['priority']}"
    layout_text = f"Result Layout: {settings['layout']}"
    delete_text = f"File Delete: {settings['file_delete']}s"

    keyboard = [
        [InlineKeyboardButton(auto_scan_text, callback_data="toggle_auto_scan")],
        [InlineKeyboardButton(spoiler_text, callback_data="toggle_spoiler")],
        [InlineKeyboardButton(top_search_text, callback_data="toggle_top_search")],
        [InlineKeyboardButton(priority_text, callback_data="change_priority")],
        [InlineKeyboardButton(layout_text, callback_data="change_layout")],
        [InlineKeyboardButton(delete_text, callback_data="change_delete")],
        [InlineKeyboardButton("🔄 Reset to Defaults", callback_data="reset_defaults")],
        [InlineKeyboardButton("🔙 Back", callback_data="back_to_main")],
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)

    if update.message:
        await update.message.reply_text("Settings Panel:", reply_markup=reply_markup)
    else:
        await update.callback_query.edit_message_text("Settings Panel:", reply_markup=reply_markup)

async def show_category_files(update: Update, context: ContextTypes.DEFAULT_TYPE, category, page=1):
    await show_typing(update, context)
    query = update.callback_query
    await query.answer()

    filters = context.user_data.get(f'{category}_filters', {})
    results, total = get_files_by_category(category, filters=filters, page=page)
    total_pages = (total + 4) // 5 if total else 1

    if category == "movie":
        title = "🎬 Dhurandhar Files"
    else:
        title = "💕 Juliet Files"

    text = f"**{title}**\n"
    if filters:
        text += f"Filters: {filters}\n"
    text += f"**Page {page}/{total_pages}**\n\n"
    
    if not results:
        text += "Koi file nahi mili."
    else:
        for i, res in enumerate(results, 1):
            name, size, resolution, lang, season, quality, file_id = res
            text += f"{i}. {name} - {size} | {resolution} | {lang} | {quality}\n"

    filter_row = [
        InlineKeyboardButton(f"Quality", callback_data=f"{category}_filter_quality"),
        InlineKeyboardButton(f"Language", callback_data=f"{category}_filter_lang"),
        InlineKeyboardButton(f"Season", callback_data=f"{category}_filter_season"),
    ]
    pagination_btns = []
    if page > 1:
        pagination_btns.append(InlineKeyboardButton("⬅️ Prev", callback_data=f"{category}_page_{page-1}"))
    if page < total_pages:
        pagination_btns.append(InlineKeyboardButton("Next ➡️", callback_data=f"{category}_page_{page+1}"))
    
    keyboard = [
        filter_row,
        pagination_btns,
        [InlineKeyboardButton("📤 SEND ALL", callback_data=f"send_all_{category}")],
        [InlineKeyboardButton("🔙 Main Menu", callback_data="back_to_main")],
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)
    await query.edit_message_text(text, reply_markup=reply_markup, parse_mode="Markdown")

async def update_group_search_results(update: Update, context: ContextTypes.DEFAULT_TYPE, page=1):
    """Group search results update karne ke liye helper function"""
    query = update.callback_query
    query_text = context.user_data.get('group_search_query', '')
    filters = context.user_data.get('group_search_filters', {})
    
    results, total = search_files_db(query=query_text, filters=filters, page=page)
    total_pages = (total + 4) // 5 if total else 1
    
    text = f"🔍 **Group Search Results for: {query_text}**\n"
    if filters:
        text += f"Filters: {filters}\n"
    text += f"**Page {page}/{total_pages}**\n\n"
    
    for i, res in enumerate(results, 1):
        name, size, resolution, lang, season, quality, file_id = res
        text += f"{i}. {name}\n   {size} | {resolution} | {lang} | {quality}\n\n"
    
    filter_row = [
        InlineKeyboardButton(f"Quality", callback_data="group_filter_quality"),
        InlineKeyboardButton(f"Language", callback_data="group_filter_lang"),
        InlineKeyboardButton(f"Season", callback_data="group_filter_season"),
    ]
    pagination_btns = []
    if page > 1:
        pagination_btns.append(InlineKeyboardButton("⬅️ Prev", callback_data=f"group_page_{page-1}"))
    if page < total_pages:
        pagination_btns.append(InlineKeyboardButton("Next ➡️", callback_data=f"group_page_{page+1}"))
    
    keyboard = [
        filter_row,
        pagination_btns,
        [InlineKeyboardButton("📤 SEND ALL", callback_data="group_send_all")],
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)
    await query.edit_message_text(text, reply_markup=reply_markup, parse_mode="Markdown")

# ======================== MAIN ========================
def main():
    app = Application.builder().token(BOT_TOKEN).build()

    # Conversation handler for search
    conv_handler = ConversationHandler(
        entry_points=[CommandHandler('search', search_start)],
        states={
            SEARCH_QUERY: [MessageHandler(filters.TEXT & ~filters.COMMAND, search_query_received)],
        },
        fallbacks=[CommandHandler('cancel', search_cancel)],
    )
    app.add_handler(conv_handler)

    # Channel message handler
    app.add_handler(MessageHandler(filters.ChatType.CHANNEL & filters.Document.ALL, channel_message_handler))

    # Group request handler
    app.add_handler(MessageHandler(filters.Chat(chat_id=REQUESTS_GROUP_ID) & filters.TEXT & ~filters.COMMAND, group_request_handler))

    # Command handlers
    app.add_handler(CommandHandler("start", start))
    app.add_handler(CommandHandler("help", help_command))
    app.add_handler(CommandHandler("settings", settings))
    app.add_handler(CommandHandler("totalupload", lambda u,c: u.message.reply_text("📁 Total uploads feature coming soon!")))
    app.add_handler(CommandHandler("request", lambda u,c: u.message.reply_text("📝 Please request in the request group: @EntertainmentTadka7860")))
    app.add_handler(CommandHandler("myrequests", lambda u,c: u.message.reply_text("📋 Your requests: Coming soon!")))
    app.add_handler(CommandHandler("userequests", lambda u,c: u.message.reply_text("📋 User requests: Coming soon!")))
    app.add_handler(CommandHandler("pendingrequests", lambda u,c: u.message.reply_text("⏳ Pending requests: Coming soon!")))
    app.add_handler(CommandHandler("totalrequests", lambda u,c: u.message.reply_text("📊 Total requests: Coming soon!")))
    app.add_handler(CommandHandler("channel", lambda u,c: u.message.reply_text("📢 Join our channels:\n@EntertainmentTadka786\n@Entertainment_Tadka_Serial_786\n@threater_print_movies\n@EntertainmentTadka7860\n@ETBackup")))

    # Callback handler
    app.add_handler(CallbackQueryHandler(button_handler))

    print("🤖 Bot chal raha hai... CTRL+C dabao band karne ke liye.")
    app.run_polling()

if __name__ == "__main__":
    main()
*/

// Default page display
if (!isset($update) || !$update) {
    // Bot status page show karo
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    echo "<h1>🎬 Entertainment Tadka Bot</h1>";
    echo "<p><strong>Main Channel:</strong> " . MAIN_CHANNEL . " (" . MAIN_CHANNEL_ID . ")</p>";
    echo "<p><strong>Theater Channel:</strong> " . THEATER_CHANNEL . " (" . THEATER_CHANNEL_ID . ")</p>";
    echo "<p><strong>Backup Channel:</strong> " . BACKUP_CHANNEL_USERNAME . " (" . BACKUP_CHANNEL_ID . ")</p>";
    echo "<p><strong>Request Channel:</strong> " . REQUEST_CHANNEL . "</p>";
    echo "<p><strong>Private Channel:</strong> -1003251791991</p>";
    echo "<p><strong>Any Channel:</strong> -1003614546520</p>";
    echo "<p><strong>Status:</strong> ✅ Running</p>";
    echo "<p><strong>Total Movies:</strong> " . ($stats['total_movies'] ?? 0) . "</p>";
    echo "<p><strong>Total Users:</strong> " . count($users_data['users'] ?? []) . "</p>";
    echo "<p><strong>Total Searches:</strong> " . ($stats['total_searches'] ?? 0) . "</p>";
    echo "<p><strong>Last Updated:</strong> " . ($stats['last_updated'] ?? 'N/A') . "</p>";
    
    echo "<h3>🚀 Quick Setup</h3>";
    echo "<p><a href='?setwebhook=1'>Set Webhook Now</a></p>";
    echo "<p><a href='?test_save=1'>Test Movie Save</a></p>";
    echo "<p><a href='?check_csv=1'>Check CSV Data</a></p>";
    
    echo "<h3>📋 Available Commands</h3>";
    echo "<ul>";
    echo "<li><code>/start</code> - Welcome message</li>";
    echo "<li><code>/help</code> - All commands</li>";
    echo "<li><code>/search movie</code> - Search movies</li>";
    echo "<li><code>/totalupload</code> - Browse all movies</li>";
    echo "<li><code>/theater</code> - Theater prints only</li>";
    echo "<li><code>/request movie</code> - Request movie</li>";
    echo "<li><code>/mystats</code> - User statistics</li>";
    echo "<li><code>/leaderboard</code> - Top users</li>";
    echo "<li><code>/channel</code> - Join channels</li>";
    echo "<li><code>/theaterchannel</code> - Theater prints</li>";
    echo "<li><code>/checkdate</code> - Upload statistics</li>";
    echo "<li><code>/stats</code> - Admin statistics</li>";
    echo "<li><code>/quickadd</code> - Quick add movie (Admin only)</li>";
    echo "<li><code>/addmovie</code> - Add movie (Admin only)</li>";
    echo "</ul>";
    
    echo "<h3>🎯 Features</h3>";
    echo "<ul>";
    echo "<li>✅ Smart movie search with fuzzy matching</li>";
    echo "<li>✅ Multi-language support (Hindi/English)</li>";
    echo "<li>✅ ALL CHANNELS SUPPORT - 6 channels integrated</li>";
    echo "<li>✅ Paginated movie browsing</li>";
    echo "<li>✅ Movie request system with daily limits</li>";
    echo "<li>✅ User points and activity tracking</li>";
    echo "<li>✅ Leaderboard system</li>";
    echo "<li>✅ Advanced filtering for group chats</li>";
    echo "<li>✅ Automatic backups with channel upload</li>";
    echo "<li>✅ Detailed statistics and logging</li>";
    echo "<li>✅ Quality and language detection</li>";
    echo "<li>✅ Maintenance mode support</li>";
    echo "<li>✅ <b>NO FORWARD HEADERS</b> - Using copyMessage instead of forward</li>";
    echo "<li>✅ ENHANCED PAGINATION with sessions, filters, previews</li>";
    echo "<li>✅ BATCH DOWNLOAD with progress tracking</li>";
    echo "<li>✅ AUTO-NOTIFICATION for requested movies</li>";
    echo "<li>✅ THEATER CHANNEL SUPPORT - Movies from @threater_print_movies</li>";
    echo "<li>✅ BACKUP CHANNEL SUPPORT - Movies from @ETBackup</li>";
    echo "<li>✅ PRIVATE CHANNEL SUPPORT - Movies from -1003251791991</li>";
    echo "<li>✅ ANY CHANNEL SUPPORT - Movies from -1003614546520</li>";
    echo "<li>✅ QUICK ADD MOVIES - Admin can add movies from ALL channels</li>";
    echo "<li>✅ DELAY TYPING - Realistic typing delay for messages</li>";
    echo "</ul>";
    
    echo "<h3>📊 Recent Activity</h3>";
    if (file_exists(LOG_FILE)) {
        $logs = array_slice(file(LOG_FILE), -10);
        echo "<pre>";
        foreach ($logs as $log) {
            echo htmlspecialchars($log);
        }
        echo "</pre>";
    }

    echo "<hr>";
    echo "<h2>🐍 Python Bot (Minato Bot) - Embedded Code</h2>";
    echo "<p>The Python bot code has been embedded at the end of this file (lines ~9250-11650).</p>";
    echo "<p>To use the Python bot:</p>";
    echo "<ol>";
    echo "<li>Save the Python section as <code>minato_bot.py</code></li>";
    echo "<li>Install: <code>pip install python-telegram-bot</code></li>";
    echo "<li>Set your BOT_TOKEN and REQUESTS_GROUP_ID</li>";
    echo "<li>Run: <code>python minato_bot.py</code></li>";
    echo "</ol>";
}
?>