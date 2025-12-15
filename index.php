<?php
// ============================================
// ENTERTAINMENT TADKA MEGA BOT v7.0
// 6 Channels | 6 CSV Files | Auto-Forward | Complete System
// ============================================

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Kolkata');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ================= SECURITY HEADERS =================
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// ================= BOT CONFIGURATION =================
define('BOT_TOKEN', '8315381064:AAGk0FGVGmB8j5SjpBvW3rD3_kQHe_hyOWU');
define('ADMIN_ID', '1080317415');
define('DELETE_AFTER_MINUTES', 15);
define('MAX_SEARCH_RESULTS', 50);
define('MAX_FORWARD_USERS', 100); // Max users to forward per movie
define('FORWARD_DELAY_MS', 100000); // 100ms delay between forwards

// ================= ALL 6 CHANNELS CONFIGURATION =================
$ALL_CHANNELS = [
    'main' => [
        'id' => -1003181705395,
        'username' => '@EntertainmentTadka786',
        'name' => 'Movies and Webseries',
        'csv_file' => 'movies_main.csv',
        'type' => 'movies',
        'auto_forward' => true
    ],
    'request' => [
        'id' => -1003083386043,
        'username' => '@EntertainmentTadka7860',
        'name' => 'Movies Request Group',
        'csv_file' => 'movies_requests.csv',
        'type' => 'requests',
        'auto_forward' => false
    ],
    'backup' => [
        'id' => -1002964109368,
        'username' => '@ETBackup',
        'name' => 'Backup Channel',
        'csv_file' => 'movies_backup.csv',
        'type' => 'backup',
        'auto_forward' => true
    ],
    'backup2' => [
        'id' => -1002337293281,
        'username' => '',
        'name' => 'Backup Channel 2',
        'csv_file' => 'movies_backup2.csv',
        'type' => 'backup',
        'auto_forward' => true
    ],
    'private' => [
        'id' => -1003251791991,
        'username' => '',
        'name' => 'Private Channel',
        'csv_file' => 'movies_private.csv',
        'type' => 'private',
        'auto_forward' => true
    ],
    'theater' => [
        'id' => -1002831605258,
        'username' => '@threater_print_movies',
        'name' => 'Theater Print Movies',
        'csv_file' => 'movies_theater.csv',
        'type' => 'theater',
        'auto_forward' => true
    ]
];

// ================= LOGGING SYSTEM =================
function bot_log($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    file_put_contents('bot.log', $log_entry, FILE_APPEND);
    return $log_entry;
}

// ================= TELEGRAM API FUNCTIONS =================
function bot_api($method, $params = []) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($params),
            'timeout' => 30
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        bot_log("API Call Failed: $method", 'ERROR');
        return false;
    }
    
    return json_decode($result, true);
}

function send_message($chat_id, $text, $reply_to = null, $keyboard = null) {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    if ($reply_to) {
        $params['reply_to_message_id'] = $reply_to;
    }
    
    if ($keyboard) {
        $params['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
    }
    
    $result = bot_api('sendMessage', $params);
    
    if ($result && $result['ok']) {
        bot_log("Message sent to $chat_id");
    } else {
        $error = isset($result['description']) ? $result['description'] : 'Unknown error';
        bot_log("Failed to send to $chat_id: $error", 'ERROR');
    }
    
    return $result;
}

function forward_message($chat_id, $from_chat_id, $message_id) {
    $params = [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ];
    
    $result = bot_api('forwardMessage', $params);
    
    if ($result && $result['ok']) {
        bot_log("Forwarded message $message_id to $chat_id", 'FORWARD');
    } else {
        bot_log("Failed to forward to $chat_id", 'ERROR');
    }
    
    return $result;
}

function copy_message($chat_id, $from_chat_id, $message_id, $caption = null) {
    $params = [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ];
    
    if ($caption) {
        $params['caption'] = $caption;
    }
    
    return bot_api('copyMessage', $params);
}

// ================= SYSTEM INITIALIZATION =================
function initialize_system() {
    global $ALL_CHANNELS;
    
    bot_log("=== SYSTEM INITIALIZATION STARTED ===", 'SYSTEM');
    
    $created_files = 0;
    
    // Create all 6 CSV files with correct headers
    foreach ($ALL_CHANNELS as $channel_key => $channel) {
        $csv_file = $channel['csv_file'];
        
        if (!file_exists($csv_file)) {
            $handle = fopen($csv_file, "w");
            if ($handle) {
                // CORRECT CSV HEADER - 7 COLUMNS
                fputcsv($handle, ['movie_name', 'message_id', 'date', 'quality', 'size', 'language', 'channel_name']);
                fclose($handle);
                @chmod($csv_file, 0666);
                
                bot_log("Created CSV: $csv_file for {$channel['name']}", 'SYSTEM');
                $created_files++;
            } else {
                bot_log("Failed to create CSV: $csv_file", 'ERROR');
            }
        } else {
            // Verify existing CSV has correct structure
            verify_csv_structure($csv_file, $channel['name']);
        }
    }
    
    // Create JSON files
    $json_files = [
        'users.json' => [
            'total_users' => 0,
            'users' => [],
            'last_updated' => date('Y-m-d H:i:s')
        ],
        'stats.json' => [
            'total_movies' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
            'total_forwards' => 0,
            'channel_stats' => [],
            'last_updated' => date('Y-m-d H:i:s')
        ],
        'requests.json' => [
            'pending_requests' => [],
            'completed_requests' => [],
            'last_updated' => date('Y-m-d H:i:s')
        ]
    ];
    
    foreach ($json_files as $file => $data) {
        if (!file_exists($file)) {
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            bot_log("Created JSON: $file", 'SYSTEM');
        }
    }
    
    bot_log("System initialized. Created $created_files new CSV files.", 'SYSTEM');
    return true;
}

function verify_csv_structure($csv_file, $channel_name) {
    if (!file_exists($csv_file)) return false;
    
    $handle = fopen($csv_file, "r");
    if (!$handle) return false;
    
    $header = fgetcsv($handle);
    fclose($handle);
    
    // Check if header has at least 3 columns
    if (count($header) < 3) {
        bot_log("Invalid CSV structure in $csv_file - Recreating", 'WARNING');
        
        // Backup old file
        $backup_file = $csv_file . '.backup_' . date('Ymd_His');
        copy($csv_file, $backup_file);
        
        // Recreate with correct structure
        $new_data = [];
        $old_handle = fopen($backup_file, "r");
        
        if ($old_handle) {
            while (($row = fgetcsv($old_handle)) !== FALSE) {
                if (count($row) >= 3) {
                    $new_row = [
                        'movie_name' => $row[0] ?? '',
                        'message_id' => $row[1] ?? '',
                        'date' => $row[2] ?? date('d-m-Y'),
                        'quality' => $row[3] ?? 'Unknown',
                        'size' => $row[4] ?? 'Unknown',
                        'language' => $row[5] ?? 'Hindi',
                        'channel_name' => $row[6] ?? $channel_name
                    ];
                    $new_data[] = array_values($new_row);
                }
            }
            fclose($old_handle);
        }
        
        // Write new CSV
        $new_handle = fopen($csv_file, "w");
        fputcsv($new_handle, ['movie_name', 'message_id', 'date', 'quality', 'size', 'language', 'channel_name']);
        
        foreach ($new_data as $row) {
            fputcsv($new_handle, $row);
        }
        
        fclose($new_handle);
        bot_log("Fixed CSV structure for $csv_file", 'SYSTEM');
    }
    
    return true;
}

// ================= USER MANAGEMENT SYSTEM =================
function track_user_activity($user_id, $username = '', $first_name = '', $last_name = '') {
    $users_file = 'users.json';
    
    if (!file_exists($users_file)) {
        $users_data = [
            'total_users' => 0,
            'users' => [],
            'last_updated' => date('Y-m-d H:i:s')
        ];
    } else {
        $users_data = json_decode(file_get_contents($users_file), true);
    }
    
    $is_new_user = false;
    
    if (!isset($users_data['users'][$user_id])) {
        // New user
        $users_data['users'][$user_id] = [
            'username' => $username,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'join_date' => date('Y-m-d H:i:s'),
            'last_active' => date('Y-m-d H:i:s'),
            'total_searches' => 0,
            'total_downloads' => 0,
            'total_movies_received' => 0,
            'is_active' => true,
            'wants_forward' => true, // Default: user wants movie forwards
            'last_forward' => null
        ];
        
        $users_data['total_users'] = count($users_data['users']);
        $is_new_user = true;
        bot_log("New user registered: $user_id ($username)", 'USER');
    } else {
        // Update existing user
        $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
        
        if (!empty($username)) {
            $users_data['users'][$user_id]['username'] = $username;
        }
        
        if (!empty($first_name)) {
            $users_data['users'][$user_id]['first_name'] = $first_name;
        }
        
        if (!empty($last_name)) {
            $users_data['users'][$user_id]['last_name'] = $last_name;
        }
        
        // Ensure wants_forward key exists
        if (!isset($users_data['users'][$user_id]['wants_forward'])) {
            $users_data['users'][$user_id]['wants_forward'] = true;
        }
    }
    
    $users_data['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents($users_file, json_encode($users_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    return $is_new_user;
}

function get_active_users($limit = null) {
    $users_file = 'users.json';
    
    if (!file_exists($users_file)) {
        return [];
    }
    
    $users_data = json_decode(file_get_contents($users_file), true);
    $active_users = [];
    
    foreach ($users_data['users'] ?? [] as $user_id => $user) {
        // Check if user is active and wants forwards
        if (($user['is_active'] ?? true) && ($user['wants_forward'] ?? true)) {
            $active_users[$user_id] = $user;
        }
    }
    
    if ($limit && count($active_users) > $limit) {
        $active_users = array_slice($active_users, 0, $limit, true);
    }
    
    return $active_users;
}

function update_user_forward_count($user_id) {
    $users_file = 'users.json';
    
    if (!file_exists($users_file)) return false;
    
    $users_data = json_decode(file_get_contents($users_file), true);
    
    if (isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id]['total_movies_received'] = 
            ($users_data['users'][$user_id]['total_movies_received'] ?? 0) + 1;
        
        $users_data['users'][$user_id]['last_forward'] = date('Y-m-d H:i:s');
        
        file_put_contents($users_file, json_encode($users_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return true;
    }
    
    return false;
}

// ================= CSV OPERATIONS =================
function get_channel_by_id($channel_id) {
    global $ALL_CHANNELS;
    
    foreach ($ALL_CHANNELS as $channel) {
        if ($channel['id'] == $channel_id) {
            return $channel;
        }
    }
    
    return null;
}

function add_movie_to_csv($channel_id, $movie_name, $message_id) {
    $channel = get_channel_by_id($channel_id);
    if (!$channel) {
        bot_log("Channel not found: $channel_id", 'ERROR');
        return false;
    }
    
    $csv_file = $channel['csv_file'];
    $date = date('d-m-Y');
    $channel_name = $channel['name'];
    $auto_forward = $channel['auto_forward'] ?? true;
    
    // Extract quality from movie name
    $quality = 'Unknown';
    $quality_patterns = [
        '/(\d{3,4}p)/i' => '$1',
        '/(4k|uhd)/i' => '4K',
        '/(1080p|fhd)/i' => '1080p',
        '/(720p|hd)/i' => '720p',
        '/(480p|sd)/i' => '480p',
        '/(cam|ts|tele.?sync)/i' => 'CAM',
        '/(dvd.?rip|blu.?ray|web.?dl)/i' => 'HD'
    ];
    
    foreach ($quality_patterns as $pattern => $value) {
        if (preg_match($pattern, $movie_name, $matches)) {
            $quality = ($value == '$1') ? strtoupper($matches[1]) : strtoupper($value);
            break;
        }
    }
    
    // Extract language
    $language = 'Hindi';
    $language_patterns = [
        '/english|eng\b/i' => 'English',
        '/tamil|tam\b/i' => 'Tamil',
        '/telugu|tel\b/i' => 'Telugu',
        '/malayalam|mal\b/i' => 'Malayalam',
        '/kannada|kan\b/i' => 'Kannada',
        '/bengali|ben\b/i' => 'Bengali',
        '/punjabi|pun\b/i' => 'Punjabi',
        '/multi\s?audio|dual/i' => 'Dual Audio'
    ];
    
    foreach ($language_patterns as $pattern => $lang) {
        if (preg_match($pattern, $movie_name)) {
            $language = $lang;
            break;
        }
    }
    
    // Extract size (if mentioned)
    $size = 'Unknown';
    if (preg_match('/(\d+(?:\.\d+)?)\s*(GB|MB)/i', $movie_name, $matches)) {
        $size = strtoupper($matches[1] . $matches[2]);
    }
    
    // Prepare CSV row
    $row = [
        'movie_name' => trim($movie_name),
        'message_id' => $message_id,
        'date' => $date,
        'quality' => $quality,
        'size' => $size,
        'language' => $language,
        'channel_name' => $channel_name
    ];
    
    // Add to CSV
    $handle = fopen($csv_file, "a");
    if ($handle) {
        fputcsv($handle, array_values($row));
        fclose($handle);
        
        // Update statistics
        update_movie_stats($channel['csv_file']);
        
        bot_log("Added to {$channel['csv_file']}: {$row['movie_name']} (Quality: {$row['quality']}, Language: {$row['language']})", 'CSV');
        
        // ✅ AUTO-FORWARD TO ACTIVE USERS
        if ($auto_forward) {
            $forwarded_count = auto_forward_to_users($channel_id, $message_id, $row);
            
            // Update forward statistics
            if ($forwarded_count > 0) {
                update_forward_stats($forwarded_count);
            }
        } else {
            bot_log("Auto-forward disabled for channel: {$channel['name']}", 'FORWARD');
        }
        
        // Notify admin
        notify_admin_new_movie($row, $channel, $forwarded_count ?? 0);
        
        return true;
    }
    
    bot_log("Failed to write to CSV: $csv_file", 'ERROR');
    return false;
}

// ================= AUTO-FORWARD SYSTEM =================
function auto_forward_to_users($channel_id, $message_id, $movie_data) {
    // Get active users who want forwards
    $active_users = get_active_users(MAX_FORWARD_USERS);
    
    if (empty($active_users)) {
        bot_log("No active users to forward movie: {$movie_data['movie_name']}", 'FORWARD');
        return 0;
    }
    
    $movie_name = $movie_data['movie_name'];
    $quality = $movie_data['quality'];
    $language = $movie_data['language'];
    $channel = get_channel_by_id($channel_id);
    $channel_name = $channel ? $channel['name'] : 'Unknown';
    
    $forwarded_count = 0;
    $failed_count = 0;
    $total_users = count($active_users);
    
    bot_log("Starting auto-forward for: $movie_name to $total_users active users", 'FORWARD');
    
    // Send starting notification to admin
    $admin_start_msg = "🚀 <b>AUTO-FORWARD STARTED</b>\n\n";
    $admin_start_msg .= "🎬 <b>Movie:</b> $movie_name\n";
    $admin_start_msg .= "📢 <b>Channel:</b> $channel_name\n";
    $admin_start_msg .= "👥 <b>Users:</b> $total_users active users\n";
    $admin_start_msg .= "⏰ <b>Started:</b> " . date('h:i:s A');
    
    send_message(ADMIN_ID, $admin_start_msg);
    
    // Forward to each user
    $user_count = 0;
    foreach ($active_users as $user_id => $user_data) {
        $user_count++;
        
        try {
            // First send notification to user
            $notification = "🎬 <b>NEW MOVIE ALERT!</b>\n\n";
            $notification .= "📢 <b>From:</b> $channel_name\n";
            $notification .= "🎥 <b>Movie:</b> $movie_name\n";
            
            if ($quality != 'Unknown') {
                $notification .= "📊 <b>Quality:</b> $quality\n";
            }
            
            if ($language != 'Hindi') {
                $notification .= "🗣️ <b>Language:</b> $language\n";
            }
            
            $notification .= "📅 <b>Uploaded:</b> " . date('h:i A') . "\n\n";
            $notification .= "⬇️ <b>Movie is being forwarded...</b>";
            
            send_message($user_id, $notification);
            
            // Then forward the actual movie
            $forward_result = forward_message($user_id, $channel_id, $message_id);
            
            if ($forward_result && $forward_result['ok']) {
                $forwarded_count++;
                
                // Update user forward count
                update_user_forward_count($user_id);
                
                bot_log("Forwarded to user $user_id: $movie_name ($forwarded_count/$total_users)", 'FORWARD');
                
                // Add delay to avoid rate limiting
                usleep(FORWARD_DELAY_MS);
                
                // Progress update every 10 users
                if ($forwarded_count % 10 == 0) {
                    $progress_msg = "📤 <b>FORWARD PROGRESS</b>\n\n";
                    $progress_msg .= "✅ <b>Forwarded:</b> $forwarded_count users\n";
                    $progress_msg .= "📊 <b>Progress:</b> " . round(($forwarded_count/$total_users)*100) . "%\n";
                    $progress_msg .= "⏰ <b>Time:</b> " . date('h:i:s A');
                    
                    send_message(ADMIN_ID, $progress_msg);
                }
                
            } else {
                $failed_count++;
                bot_log("Failed to forward to user $user_id", 'ERROR');
            }
            
        } catch (Exception $e) {
            $failed_count++;
            bot_log("Exception forwarding to user $user_id: " . $e->getMessage(), 'ERROR');
        }
        
        // Safety break
        if ($user_count >= MAX_FORWARD_USERS) {
            break;
        }
    }
    
    // Send final summary to admin
    $summary = "📤 <b>AUTO-FORWARD COMPLETED</b>\n\n";
    $summary .= "🎬 <b>Movie:</b> $movie_name\n";
    $summary .= "📢 <b>Channel:</b> $channel_name\n";
    $summary .= "👥 <b>Total Users:</b> $total_users\n";
    $summary .= "✅ <b>Successfully forwarded:</b> $forwarded_count users\n";
    
    if ($failed_count > 0) {
        $summary .= "❌ <b>Failed:</b> $failed_count users\n";
    }
    
    $summary .= "📊 <b>Success Rate:</b> " . round(($forwarded_count/$total_users)*100) . "%\n";
    $summary .= "⏰ <b>Started:</b> " . date('h:i:s A') . "\n";
    $summary .= "🏁 <b>Completed:</b> " . date('h:i:s A') . "\n";
    $summary .= "📅 <b>Date:</b> " . date('d M Y');
    
    send_message(ADMIN_ID, $summary);
    
    bot_log("Auto-forward completed: $forwarded_count successful, $failed_count failed", 'FORWARD');
    
    return $forwarded_count;
}

function update_movie_stats($csv_file) {
    if (!file_exists('stats.json')) return;
    
    $stats = json_decode(file_get_contents('stats.json'), true);
    $stats['total_movies'] = ($stats['total_movies'] ?? 0) + 1;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    
    file_put_contents('stats.json', json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function update_forward_stats($count) {
    if (!file_exists('stats.json')) return;
    
    $stats = json_decode(file_get_contents('stats.json'), true);
    $stats['total_forwards'] = ($stats['total_forwards'] ?? 0) + $count;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    
    file_put_contents('stats.json', json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function notify_admin_new_movie($movie_data, $channel, $forwarded_count = 0) {
    $message = "✅ <b>NEW MOVIE ADDED TO CSV</b>\n\n";
    $message .= "🎬 <b>Movie:</b> {$movie_data['movie_name']}\n";
    $message .= "📁 <b>Channel:</b> {$channel['name']}\n";
    $message .= "📄 <b>CSV File:</b> {$channel['csv_file']}\n";
    $message .= "📊 <b>Quality:</b> {$movie_data['quality']}\n";
    $message .= "🗣️ <b>Language:</b> {$movie_data['language']}\n";
    $message .= "💾 <b>Size:</b> {$movie_data['size']}\n";
    $message .= "🔢 <b>Message ID:</b> {$movie_data['message_id']}\n";
    $message .= "📅 <b>Date:</b> {$movie_data['date']}\n";
    
    if ($forwarded_count > 0) {
        $message .= "📤 <b>Auto-forwarded to:</b> $forwarded_count users\n";
    }
    
    $message .= "⏰ <b>Time:</b> " . date('h:i A');
    
    send_message(ADMIN_ID, $message);
}

// ================= ADVANCED SEARCH FUNCTIONS =================
function search_in_csv($csv_file, $query, $channel_name = '') {
    if (!file_exists($csv_file)) {
        return [];
    }
    
    $results = [];
    $handle = fopen($csv_file, "r");
    
    if (!$handle) {
        return [];
    }
    
    // Read header
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return [];
    }
    
    // Map column indices
    $col_movie = array_search('movie_name', $header);
    $col_msg_id = array_search('message_id', $header);
    $col_date = array_search('date', $header);
    $col_quality = array_search('quality', $header);
    $col_size = array_search('size', $header);
    $col_language = array_search('language', $header);
    $col_channel = array_search('channel_name', $header);
    
    // Set defaults if columns not found
    if ($col_movie === false) $col_movie = 0;
    if ($col_msg_id === false) $col_msg_id = 1;
    if ($col_date === false) $col_date = 2;
    if ($col_quality === false) $col_quality = 3;
    if ($col_size === false) $col_size = 4;
    if ($col_language === false) $col_language = 5;
    if ($col_channel === false) $col_channel = 6;
    
    $query_lower = strtolower(trim($query));
    $found_count = 0;
    
    while (($row = fgetcsv($handle)) !== FALSE && $found_count < MAX_SEARCH_RESULTS) {
        if (count($row) > $col_movie) {
            $movie_name = trim($row[$col_movie] ?? '');
            $movie_lower = strtolower($movie_name);
            
            // Multiple search strategies
            $match_score = 0;
            
            // Exact match
            if ($movie_lower == $query_lower) {
                $match_score = 100;
            }
            // Contains query
            elseif (strpos($movie_lower, $query_lower) !== false) {
                $match_score = 80;
            }
            // Word boundary match
            elseif (preg_match('/\b' . preg_quote($query_lower, '/') . '\b/i', $movie_name)) {
                $match_score = 90;
            }
            // Partial word match
            elseif (preg_match('/' . preg_quote($query_lower, '/') . '/i', $movie_name)) {
                $match_score = 70;
            }
            
            if ($match_score > 0) {
                $result = [
                    'name' => $movie_name,
                    'message_id' => isset($row[$col_msg_id]) ? trim($row[$col_msg_id]) : '',
                    'date' => isset($row[$col_date]) ? trim($row[$col_date]) : '',
                    'quality' => isset($row[$col_quality]) ? trim($row[$col_quality]) : 'Unknown',
                    'size' => isset($row[$col_size]) ? trim($row[$col_size]) : 'Unknown',
                    'language' => isset($row[$col_language]) ? trim($row[$col_language]) : 'Hindi',
                    'channel' => $channel_name ?: (isset($row[$col_channel]) ? trim($row[$col_channel]) : ''),
                    'match_score' => $match_score,
                    'csv_file' => $csv_file
                ];
                
                // Clean up data
                $result['quality'] = clean_quality($result['quality']);
                $result['language'] = clean_language($result['language']);
                $result['size'] = clean_size($result['size']);
                
                $results[] = $result;
                $found_count++;
            }
        }
    }
    
    fclose($handle);
    
    // Sort by match score (highest first)
    usort($results, function($a, $b) {
        return $b['match_score'] - $a['match_score'];
    });
    
    return $results;
}

function clean_quality($quality) {
    if (empty($quality) || $quality == '1.5GB' || $quality == 'Unknown') {
        return 'Unknown';
    }
    
    $quality = strtoupper(trim($quality));
    
    // Standardize quality formats
    $quality_map = [
        '1080P' => '1080p',
        '720P' => '720p',
        '480P' => '480p',
        '4K' => '4K',
        'UHD' => '4K',
        'HD' => 'HD',
        'FULL HD' => '1080p',
        'CAM' => 'CAM',
        'TS' => 'CAM',
        'TELESYNC' => 'CAM',
        'DVD' => 'DVD',
        'BLURAY' => 'BluRay',
        'WEB-DL' => 'WEB-DL'
    ];
    
    foreach ($quality_map as $key => $value) {
        if (strpos($quality, $key) !== false) {
            return $value;
        }
    }
    
    return $quality;
}

function clean_language($language) {
    if (empty($language) || $language == '1.5GB') {
        return 'Hindi';
    }
    
    $language = ucwords(strtolower(trim($language)));
    
    $language_map = [
        'Eng' => 'English',
        'Tam' => 'Tamil',
        'Tel' => 'Telugu',
        'Mal' => 'Malayalam',
        'Kan' => 'Kannada',
        'Ben' => 'Bengali',
        'Pun' => 'Punjabi',
        'Hin' => 'Hindi'
    ];
    
    if (isset($language_map[$language])) {
        return $language_map[$language];
    }
    
    return $language;
}

function clean_size($size) {
    if (empty($size) || $size == 'Unknown') {
        return 'Unknown';
    }
    
    $size = strtoupper(trim($size));
    
    // Format size properly
    if (preg_match('/(\d+(?:\.\d+)?)\s*(GB|MB|KB)/i', $size, $matches)) {
        $size = $matches[1] . $matches[2];
    }
    
    return $size;
}

function search_all_channels($query) {
    global $ALL_CHANNELS;
    
    $all_results = [];
    $total_found = 0;
    
    foreach ($ALL_CHANNELS as $channel_key => $channel) {
        $csv_file = $channel['csv_file'];
        $channel_name = $channel['name'];
        
        $results = search_in_csv($csv_file, $query, $channel_name);
        
        if (!empty($results)) {
            $all_results[$channel_name] = [
                'results' => $results,
                'count' => count($results),
                'csv_file' => $csv_file,
                'channel_key' => $channel_key
            ];
            $total_found += count($results);
        }
    }
    
    // Sort channels by number of results (most first)
    uasort($all_results, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    return [
        'all_results' => $all_results,
        'total_found' => $total_found,
        'channels_found' => count($all_results),
        'query' => $query
    ];
}

// ================= STATISTICS FUNCTIONS =================
function get_csv_stats() {
    global $ALL_CHANNELS;
    
    $stats = [];
    $total_movies = 0;
    
    foreach ($ALL_CHANNELS as $channel_key => $channel) {
        $csv_file = $channel['csv_file'];
        $movie_count = 0;
        
        if (file_exists($csv_file)) {
            $handle = fopen($csv_file, "r");
            if ($handle) {
                // Skip header
                fgetcsv($handle);
                
                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (count($row) >= 3 && !empty(trim($row[0]))) {
                        $movie_count++;
                    }
                }
                fclose($handle);
            }
        }
        
        $total_movies += $movie_count;
        
        $stats[$channel_key] = [
            'name' => $channel['name'],
            'csv_file' => $csv_file,
            'movies' => $movie_count,
            'id' => $channel['id'],
            'username' => $channel['username'],
            'type' => $channel['type'],
            'auto_forward' => $channel['auto_forward'] ?? true
        ];
    }
    
    return [
        'channel_stats' => $stats,
        'total_movies' => $total_movies,
        'total_channels' => count($ALL_CHANNELS)
    ];
}

function update_search_stats() {
    if (!file_exists('stats.json')) return;
    
    $stats = json_decode(file_get_contents('stats.json'), true);
    $stats['total_searches'] = ($stats['total_searches'] ?? 0) + 1;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    
    file_put_contents('stats.json', json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ================= DELETE PROTECTION SYSTEM =================
function send_delete_warning($chat_id, $message_id, $file_name) {
    $delete_minutes = DELETE_AFTER_MINUTES;
    $delete_time = date('H:i', time() + ($delete_minutes * 60));
    
    $message = "⚠️ <b>DELETE WARNING</b>\n\n";
    $message .= "🎬 <b>File:</b> " . htmlspecialchars($file_name) . "\n";
    $message .= "⏰ <b>Auto-delete in:</b> $delete_minutes minutes\n";
    $message .= "🕒 <b>Delete at:</b> $delete_time\n\n";
    
    $message .= "✅ <b>ACT NOW:</b>\n";
    $message .= "1. 📤 Forward this file immediately\n";
    $message .= "2. 💾 Download and save it\n";
    $message .= "3. 📱 Save to your device\n";
    $message .= "4. 🚫 Don't wait for last minute\n\n";
    
    $message .= "📢 <b>Our Channels:</b>\n";
    $message .= "• @EntertainmentTadka786\n";
    $message .= "• @EntertainmentTadka7860\n";
    $message .= "• @ETBackup\n";
    $message .= "• @threater_print_movies";
    
    // Add countdown keyboard
    $keyboard = [
        [
            ['text' => '⏰ Check Time Left', 'callback_data' => 'check_time'],
            ['text' => '✅ I Saved It', 'callback_data' => 'saved_file']
        ],
        [
            ['text' => '📢 Join Channel', 'url' => 'https://t.me/EntertainmentTadka786'],
            ['text' => '📥 Request Group', 'url' => 'https://t.me/EntertainmentTadka7860']
        ]
    ];
    
    send_message($chat_id, $message, $message_id, $keyboard);
    bot_log("Delete warning sent for: $file_name to $chat_id", 'PROTECTION');
}

// ================= COMMAND HANDLERS =================
function handle_start($chat_id, $user_id, $username, $first_name) {
    $message = "🎬 <b>ENTERTAINMENT TADKA MEGA BOT v7.0</b>\n\n";
    
    $message .= "👋 Welcome, <b>$first_name</b>!\n\n";
    
    $message .= "📊 <b>MAIN FEATURES:</b>\n";
    $message .= "✅ 6 Channels Connected\n";
    $message .= "✅ 6 Separate CSV Files\n";
    $message .= "✅ Auto-Forward New Movies\n";
    $message .= "✅ Advanced Movie Search\n";
    $message .= "✅ Auto-Delete Protection\n";
    $message .= "✅ Quality & Language Detection\n\n";
    
    $message .= "🔍 <b>HOW TO SEARCH:</b>\n";
    $message .= "• Just type movie name\n";
    $message .= "• Example: <code>Animal</code>\n";
    $message .= "• Or: <code>Mandala Murders</code>\n\n";
    
    $message .= "🎬 <b>AUTO-FORWARD:</b>\n";
    $message .= "• New movies auto-forwarded to you\n";
    $message .= "• Receive alerts for new uploads\n";
    $message .= "• Control: /forwardsettings\n\n";
    
    $message .= "📱 <b>QUICK COMMANDS:</b>\n";
    $message .= "/help - Show all commands\n";
    $message .= "/stats - Bot statistics\n";
    $message .= "/channels - List all channels\n";
    $message .= "/searchall - Search all channels\n";
    $message .= "/request - Request movie\n";
    $message .= "/users - User statistics\n\n";
    
    $message .= "🛡️ <b>Auto-delete:</b> " . DELETE_AFTER_MINUTES . " minutes\n";
    $message .= "📅 <b>Date:</b> " . date('d M Y');
    
    $keyboard = [
        [
            ['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => ''],
            ['text' => '📊 View Stats', 'callback_data' => 'view_stats']
        ],
        [
            ['text' => '🎬 Get Latest Movies', 'callback_data' => 'get_latest'],
            ['text' => '⚙️ Settings', 'callback_data' => 'settings']
        ]
    ];
    
    send_message($chat_id, $message, null, $keyboard);
    bot_log("User $user_id ($username) started bot", 'USER');
}

function handle_help($chat_id) {
    $message = "🤖 <b>COMMAND GUIDE - v7.0</b>\n\n";
    
    $message .= "🔍 <b>SEARCH COMMANDS:</b>\n";
    $message .= "• Type movie name directly\n";
    $message .= "• <code>Animal</code>\n";
    $message .= "• <code>Mandala Murders</code>\n";
    $message .= "• <code>Seesaw</code>\n";
    $message .= "• <code>/searchall movie_name</code>\n\n";
    
    $message .= "📊 <b>INFO COMMANDS:</b>\n";
    $message .= "• /stats - Bot statistics\n";
    $message .= "• /channels - All channels list\n";
    $message .= "• /users - User statistics\n";
    $message .= "• /help - This help message\n\n";
    
    $message .= "🎬 <b>MOVIE COMMANDS:</b>\n";
    $message .= "• /request movie_name - Request movie\n";
    $message .= "• /latest - Get latest movies\n";
    $message .= "• /forwardsettings - Auto-forward settings\n\n";
    
    $message .= "🛡️ <b>PROTECTION:</b>\n";
    $message .= "• Files auto-delete in " . DELETE_AFTER_MINUTES . " minutes\n";
    $message .= "• Forward immediately to save\n\n";
    
    $message .= "⚙️ <b>SETTINGS:</b>\n";
    $message .= "• /forward on/off - Toggle auto-forward\n";
    $message .= "• /notify on/off - Toggle notifications\n\n";
    
    $message .= "📢 <b>CHANNELS:</b>\n";
    $message .= "• @EntertainmentTadka786\n";
    $message .= "• @EntertainmentTadka7860\n";
    $message .= "• @ETBackup\n";
    $message .= "• @threater_print_movies";
    
    send_message($chat_id, $message);
}

function handle_stats($chat_id) {
    $csv_stats = get_csv_stats();
    $json_stats = file_exists('stats.json') ? json_decode(file_get_contents('stats.json'), true) : [];
    $users_data = file_exists('users.json') ? json_decode(file_get_contents('users.json'), true) : [];
    
    $message = "📊 <b>BOT STATISTICS - v7.0</b>\n\n";
    
    $message .= "🎬 <b>Total Movies:</b> {$csv_stats['total_movies']}\n";
    $message .= "🔍 <b>Total Searches:</b> " . ($json_stats['total_searches'] ?? 0) . "\n";
    $message .= "📤 <b>Total Forwards:</b> " . ($json_stats['total_forwards'] ?? 0) . "\n";
    $message .= "👥 <b>Total Users:</b> " . ($users_data['total_users'] ?? 0) . "\n";
    $message .= "📁 <b>Total Channels:</b> {$csv_stats['total_channels']}\n";
    $message .= "📄 <b>CSV Files:</b> {$csv_stats['total_channels']}\n";
    $message .= "🛡️ <b>Delete Time:</b> " . DELETE_AFTER_MINUTES . " minutes\n\n";
    
    $message .= "📈 <b>CHANNEL STATISTICS:</b>\n";
    foreach ($csv_stats['channel_stats'] as $channel) {
        $auto_forward = $channel['auto_forward'] ? '✅' : '❌';
        $message .= "• <b>{$channel['name']}</b>\n";
        $message .= "  Movies: {$channel['movies']} | Auto-forward: $auto_forward\n";
    }
    
    // Active users count
    $active_users = get_active_users();
    $active_count = count($active_users);
    $message .= "\n👥 <b>Active Users:</b> $active_count (want forwards)\n";
    
    $message .= "\n⏰ <b>Last Updated:</b> " . date('h:i A');
    $message .= "\n📅 <b>Date:</b> " . date('d M Y');
    
    send_message($chat_id, $message);
}

function handle_channels($chat_id) {
    global $ALL_CHANNELS;
    
    $message = "📢 <b>ALL 6 CHANNELS - AUTO-FORWARD STATUS</b>\n\n";
    $message .= "Each channel has separate CSV file\n\n";
    
    $csv_stats = get_csv_stats();
    
    foreach ($ALL_CHANNELS as $channel_key => $channel) {
        $movie_count = $csv_stats['channel_stats'][$channel_key]['movies'] ?? 0;
        $auto_forward = $channel['auto_forward'] ? '✅ ON' : '❌ OFF';
        
        $message .= "🎬 <b>{$channel['name']}</b>\n";
        $message .= "   📄 CSV: <code>{$channel['csv_file']}</code>\n";
        $message .= "   🎥 Movies: $movie_count\n";
        $message .= "   🚀 Auto-forward: $auto_forward\n";
        $message .= "   🔢 ID: <code>{$channel['id']}</code>\n";
        $message .= "   📊 Type: {$channel['type']}\n";
        
        if ($channel['username']) {
            $message .= "   🔗 {$channel['username']}\n";
        }
        
        $message .= "\n";
    }
    
    $message .= "✅ <b>Auto-add enabled for all channels</b>\n";
    $message .= "🚀 <b>Auto-forward:</b> New movies sent to active users";
    
    send_message($chat_id, $message);
}

function handle_searchall($chat_id, $query, $user_id) {
    if (strlen($query) < 2) {
        send_message($chat_id, "❌ Minimum 2 characters required\nExample: <code>ma</code>");
        return;
    }
    
    // Show searching message
    send_message($chat_id, "🔍 <b>Searching for '$query'...</b>\nPlease wait...");
    
    $search_results = search_all_channels($query);
    
    if ($search_results['total_found'] > 0) {
        $message = "✅ <b>Found {$search_results['total_found']} results for '$query'</b>\n";
        $message .= "📁 In {$search_results['channels_found']} channels\n\n";
        
        $display_limit = 3; // Show only top 3 channels
        $channel_count = 0;
        
        foreach ($search_results['all_results'] as $channel_name => $data) {
            $channel_count++;
            if ($channel_count > $display_limit) break;
            
            $message .= "🎬 <b>$channel_name</b> ({$data['count']} movies):\n";
            
            $movie_display_limit = 3; // Show only top 3 movies per channel
            $movie_count = 0;
            
            foreach ($data['results'] as $result) {
                $movie_count++;
                if ($movie_count > $movie_display_limit) break;
                
                $short_name = strlen($result['name']) > 40 ? 
                             substr($result['name'], 0, 40) . "..." : $result['name'];
                
                $message .= "   • <b>$short_name</b>\n";
                
                // Show details
                $details = [];
                if ($result['quality'] != 'Unknown') {
                    $details[] = "📊 {$result['quality']}";
                }
                if ($result['language'] != 'Hindi') {
                    $details[] = "🗣️ {$result['language']}";
                }
                if ($result['size'] != 'Unknown') {
                    $details[] = "💾 {$result['size']}";
                }
                
                if (!empty($details)) {
                    $message .= "     " . implode(' | ', $details) . "\n";
                }
                
                if (!empty($result['date'])) {
                    $message .= "     📅 {$result['date']}\n";
                }
            }
            
            if ($data['count'] > $movie_display_limit) {
                $remaining = $data['count'] - $movie_display_limit;
                $message .= "     ... and $remaining more\n";
            }
            
            $message .= "\n";
        }
        
        if ($search_results['channels_found'] > $display_limit) {
            $remaining_channels = $search_results['channels_found'] - $display_limit;
            $message .= "📄 <b>And $remaining_channels more channels with results...</b>\n";
        }
        
        // Add download options
        $keyboard = [];
        $channel_count = 0;
        
        foreach ($search_results['all_results'] as $channel_name => $data) {
            if ($channel_count >= 3) break;
            
            if (!empty($data['results'])) {
                $first_movie = $data['results'][0]['name'];
                $keyboard[] = [
                    ['text' => "📥 Download from $channel_name", 'callback_data' => 'download_' . urlencode($first_movie)]
                ];
                $channel_count++;
            }
        }
        
        if (!empty($keyboard)) {
            $keyboard[] = [
                ['text' => '🔍 Search Again', 'callback_data' => 'search_again'],
                ['text' => '📝 Request Movie', 'callback_data' => 'request_movie']
            ];
        }
        
        // Update search statistics
        update_search_stats();
        
        // Log search
        bot_log("User $user_id searched: '$query' - Found {$search_results['total_found']} results", 'SEARCH');
        
        send_message($chat_id, $message, null, $keyboard);
        
    } else {
        $message = "❌ <b>No matches found for '$query'</b>\n\n";
        $message .= "💡 <b>Suggestions:</b>\n";
        $message .= "• Check spelling\n";
        $message .= "• Try shorter name\n";
        $message .= "• Search with English/Hindi\n";
        $message .= "• Use partial name\n\n";
        
        $message .= "📝 <b>Request this movie:</b>\n";
        $message .= "<code>/request $query</code>\n\n";
        
        $message .= "🔗 <b>Request Group:</b> @EntertainmentTadka7860";
        
        $keyboard = [
            [
                ['text' => '📝 Request This Movie', 'callback_data' => 'request_' . urlencode($query)],
                ['text' => '🔍 New Search', 'callback_data' => 'new_search']
            ]
        ];
        
        send_message($chat_id, $message, null, $keyboard);
        bot_log("User $user_id searched: '$query' - No results found", 'SEARCH');
    }
}

function handle_request($chat_id, $user_id, $movie_name) {
    if (empty($movie_name)) {
        send_message($chat_id, "❌ Usage: /request movie_name\nExample: /request Animal 2023");
        return;
    }
    
    $message = "✅ <b>REQUEST RECEIVED</b>\n\n";
    $message .= "🎬 <b>Movie:</b> $movie_name\n";
    $message .= "👤 <b>User ID:</b> $user_id\n";
    $message .= "📝 <b>Status:</b> Added to request list\n";
    $message .= "📄 <b>Will be added to:</b> movies_requests.csv\n";
    $message .= "⏰ <b>Time:</b> " . date('h:i A') . "\n";
    $message .= "📅 <b>Date:</b> " . date('d M Y') . "\n\n";
    
    $message .= "🔗 <b>Request Group:</b> @EntertainmentTadka7860\n";
    $message .= "📢 <b>Main Channel:</b> @EntertainmentTadka786";
    
    send_message($chat_id, $message);
    
    // Notify admin
    $admin_msg = "📝 <b>NEW MOVIE REQUEST</b>\n\n";
    $admin_msg .= "🎬 <b>Movie:</b> $movie_name\n";
    $admin_msg .= "👤 <b>User ID:</b> $user_id\n";
    $admin_msg .= "⏰ <b>Time:</b> " . date('h:i A') . "\n";
    $admin_msg .= "📅 <b>Date:</b> " . date('d M Y');
    
    send_message(ADMIN_ID, $admin_msg);
    
    // Save to requests file
    $requests_file = 'requests.json';
    $requests = file_exists($requests_file) ? json_decode(file_get_contents($requests_file), true) : ['pending_requests' => []];
    
    $new_request = [
        'id' => uniqid(),
        'movie_name' => $movie_name,
        'user_id' => $user_id,
        'timestamp' => time(),
        'date' => date('Y-m-d H:i:s'),
        'status' => 'pending'
    ];
    
    $requests['pending_requests'][] = $new_request;
    $requests['last_updated'] = date('Y-m-d H:i:s');
    
    file_put_contents($requests_file, json_encode($requests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    bot_log("Movie requested: '$movie_name' by user $user_id", 'REQUEST');
}

function handle_users($chat_id) {
    $users_file = 'users.json';
    
    if (!file_exists($users_file)) {
        send_message($chat_id, "📭 <b>No users data found</b>\n\nUsers will be tracked automatically when they interact with bot.");
        return;
    }
    
    $users_data = json_decode(file_get_contents($users_file), true);
    $total_users = $users_data['total_users'] ?? 0;
    $active_users = get_active_users();
    $active_count = count($active_users);
    
    $message = "👥 <b>USER STATISTICS</b>\n\n";
    $message .= "📊 <b>Total Users:</b> $total_users\n";
    $message .= "✅ <b>Active Users:</b> $active_count (want forwards)\n";
    $message .= "📅 <b>Last Updated:</b> " . date('h:i A') . "\n\n";
    
    // Show last 5 active users
    $message .= "🆕 <b>Recent Active Users:</b>\n";
    
    $recent_users = array_slice($active_users, -5, 5, true);
    $count = 1;
    
    foreach ($recent_users as $uid => $user) {
        $name = $user['first_name'] ?? 'User';
        $uname = !empty($user['username']) ? "@{$user['username']}" : "No username";
        $movies_received = $user['total_movies_received'] ?? 0;
        
        $message .= "$count. <b>$name</b> ($uname)\n";
        $message .= "   🎬 Movies received: $movies_received\n";
        $count++;
    }
    
    if ($total_users > 5) {
        $remaining = $total_users - 5;
        $message .= "\n📄 <b>And $remaining more users...</b>\n";
    }
    
    $message .= "\n⚙️ <b>User tracking is automatic</b>\n";
    $message .= "Users are added when they first interact with bot.";
    
    send_message($chat_id, $message);
}

function handle_forward_settings($chat_id, $user_id, $action = null) {
    $users_file = 'users.json';
    
    if (!file_exists($users_file)) {
        send_message($chat_id, "❌ Users data not initialized yet.");
        return;
    }
    
    $users_data = json_decode(file_get_contents($users_file), true);
    
    if (!isset($users_data['users'][$user_id])) {
        send_message($chat_id, "❌ User not found in database. Send /start first.");
        return;
    }
    
    if ($action === 'on' || $action === 'off') {
        // Update user preference
        $users_data['users'][$user_id]['wants_forward'] = ($action === 'on');
        $users_data['last_updated'] = date('Y-m-d H:i:s');
        
        file_put_contents($users_file, json_encode($users_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $status = $action === 'on' ? '✅ ENABLED' : '❌ DISABLED';
        $message = "⚙️ <b>AUTO-FORWARD SETTINGS UPDATED</b>\n\n";
        $message .= "🚀 <b>Status:</b> $status\n\n";
        
        if ($action === 'on') {
            $message .= "✅ You will receive new movies automatically\n";
            $message .= "🎬 Latest uploads will be forwarded to you\n";
            $message .= "🔔 You'll get notifications for new movies";
        } else {
            $message .= "❌ You will NOT receive new movies automatically\n";
            $message .= "📭 You can still search for movies manually\n";
            $message .= "🔍 Use search to find specific movies";
        }
        
        bot_log("User $user_id set auto-forward to: $action", 'SETTINGS');
        
    } else {
        // Show current settings
        $current_setting = $users_data['users'][$user_id]['wants_forward'] ?? true;
        $status = $current_setting ? '✅ ENABLED' : '❌ DISABLED';
        
        $message = "⚙️ <b>YOUR AUTO-FORWARD SETTINGS</b>\n\n";
        $message .= "🚀 <b>Current Status:</b> $status\n\n";
        
        if ($current_setting) {
            $message .= "✅ You are receiving new movies automatically\n";
            $message .= "🎬 Latest uploads are forwarded to you\n";
            $message .= "🔔 You get notifications for new movies\n\n";
            $message .= "To disable: <code>/forward off</code>";
        } else {
            $message .= "❌ You are NOT receiving new movies automatically\n";
            $message .= "📭 You can still search for movies manually\n";
            $message .= "🔍 Use search to find specific movies\n\n";
            $message .= "To enable: <code>/forward on</code>";
        }
        
        $message .= "\n\n📊 <b>Movies Received:</b> " . ($users_data['users'][$user_id]['total_movies_received'] ?? 0);
    }
    
    send_message($chat_id, $message);
}

// ================= MAIN UPDATE HANDLER =================
function handle_update($update) {
    // Handle channel posts (auto-add movies to CSV)
    if (isset($update['channel_post'])) {
        $post = $update['channel_post'];
        $channel_id = $post['chat']['id'];
        $message_id = $post['message_id'];
        
        $channel = get_channel_by_id($channel_id);
        
        if ($channel) {
            $file_name = '';
            
            // Get movie name from caption or text
            if (isset($post['caption'])) {
                $file_name = $post['caption'];
            } elseif (isset($post['text'])) {
                $file_name = $post['text'];
            }
            
            // Clean up movie name
            $file_name = trim($file_name);
            
            if (!empty($file_name)) {
                // Remove common unwanted prefixes/suffixes
                $file_name = preg_replace('/^(🎬|📽️|🍿|▶️|📥|⬇️|🔽|📁|📄)\s*/', '', $file_name);
                $file_name = preg_replace('/\s*(👉|✅|🌟|🔥|💯|⭐|✨|👍|❤️|💖|💕|😍|🙏)$/', '', $file_name);
                
                // Add to CSV
                $result = add_movie_to_csv($channel_id, $file_name, $message_id);
                
                if ($result) {
                    bot_log("Auto-added to {$channel['csv_file']}: $file_name", 'AUTO-ADD');
                } else {
                    bot_log("Failed to auto-add: $file_name", 'ERROR');
                }
            }
        }
    }
    
    // Handle private messages
    if (isset($update['message'])) {
        $msg = $update['message'];
        $chat_id = $msg['chat']['id'];
        $user_id = $msg['from']['id'];
        $username = $msg['from']['username'] ?? '';
        $first_name = $msg['from']['first_name'] ?? '';
        $last_name = $msg['from']['last_name'] ?? '';
        $text = $msg['text'] ?? '';
        
        // Track user activity
        track_user_activity($user_id, $username, $first_name, $last_name);
        
        // Handle file uploads (delete protection)
        if (isset($msg['video']) || isset($msg['document']) || isset($msg['audio'])) {
            $file_name = '';
            
            if (isset($msg['video'])) {
                $file_name = $msg['video']['file_name'] ?? 'Video_' . time() . '.mp4';
            } elseif (isset($msg['document'])) {
                $file_name = $msg['document']['file_name'];
            } elseif (isset($msg['audio'])) {
                $file_name = $msg['audio']['file_name'] ?? 'Audio_' . time() . '.mp3';
            }
            
            if (isset($msg['caption'])) {
                $file_name = $msg['caption'] . ' - ' . $file_name;
            }
            
            if (!empty($file_name)) {
                send_delete_warning($chat_id, $msg['message_id'], $file_name);
                bot_log("Delete warning for file: $file_name to user $user_id", 'PROTECTION');
            }
        }
        
        // Handle commands and text messages
        if (!empty($text)) {
            $parts = explode(' ', $text, 2);
            $command = strtolower($parts[0]);
            $parameter = isset($parts[1]) ? trim($parts[1]) : '';
            
            switch ($command) {
                case '/start':
                    handle_start($chat_id, $user_id, $username, $first_name);
                    break;
                    
                case '/help':
                    handle_help($chat_id);
                    break;
                    
                case '/stats':
                    handle_stats($chat_id);
                    break;
                    
                case '/channels':
                    handle_channels($chat_id);
                    break;
                    
                case '/searchall':
                    handle_searchall($chat_id, $parameter, $user_id);
                    break;
                    
                case '/request':
                    handle_request($chat_id, $user_id, $parameter);
                    break;
                    
                case '/users':
                    handle_users($chat_id);
                    break;
                    
                case '/forward':
                    handle_forward_settings($chat_id, $user_id, $parameter);
                    break;
                    
                case '/forwardsettings':
                    handle_forward_settings($chat_id, $user_id);
                    break;
                    
                case '/testforward':
                    // Test forward to admin only
                    $test_channel_id = -1003181705395;
                    $test_message_id = 180;
                    $test_result = forward_message(ADMIN_ID, $test_channel_id, $test_message_id);
                    if ($test_result && $test_result['ok']) {
                        send_message($chat_id, "✅ Test forward successful to Admin!");
                    } else {
                        send_message($chat_id, "❌ Test forward failed!");
                    }
                    break;
                    
                default:
                    // Regular text - treat as search query
                    if (strlen($text) > 1 && !str_starts_with($text, '/')) {
                        handle_searchall($chat_id, $text, $user_id);
                    }
                    break;
            }
        }
    }
    
    // Handle callback queries
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $data = $query['data'];
        $chat_id = $query['message']['chat']['id'];
        $user_id = $query['from']['id'];
        $message_id = $query['message']['message_id'];
        
        // Answer callback query first
        bot_api('answerCallbackQuery', ['callback_query_id' => $query['id']]);
        
        // Handle different callback actions
        if (strpos($data, 'download_') === 0) {
            $movie_name = urldecode(substr($data, 9));
            send_message($chat_id, "📥 <b>Download Feature Coming Soon!</b>\n\nMovie: $movie_name\n\nUse search to find movies!");
            
        } elseif (strpos($data, 'request_') === 0) {
            $movie_name = urldecode(substr($data, 8));
            handle_request($chat_id, $user_id, $movie_name);
            
        } elseif ($data == 'search_again') {
            send_message($chat_id, "🔍 Type movie name to search again!");
            
        } elseif ($data == 'request_movie') {
            send_message($chat_id, "📝 Use: /request movie_name\nExample: /request Animal 2023");
            
        } elseif ($data == 'new_search') {
            send_message($chat_id, "🔍 Type movie name to search!");
            
        } elseif ($data == 'check_time') {
            send_message($chat_id, "⏰ Delete time: " . DELETE_AFTER_MINUTES . " minutes\nForward file immediately to save!");
            
        } elseif ($data == 'saved_file') {
            send_message($chat_id, "✅ Great! File saved successfully.");
            
        } elseif ($data == 'view_stats') {
            handle_stats($chat_id);
            
        } elseif ($data == 'get_latest') {
            send_message($chat_id, "🎬 Getting latest movies...\n\nNew movies are auto-forwarded to you!\nCheck /forwardsettings to control.");
            
        } elseif ($data == 'settings') {
            handle_forward_settings($chat_id, $user_id);
        }
    }
}

// ================= DIRECT ACCESS PAGE =================
if (!isset($_POST) && php_sapi_name() != 'cli' && empty(file_get_contents('php://input'))) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>🎬 Entertainment Tadka Mega Bot v7.0</title>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
                color: #333;
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
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid #eee;
            }
            
            .header h1 {
                color: #667eea;
                font-size: 2.5em;
                margin-bottom: 10px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            
            .status {
                display: inline-block;
                padding: 8px 20px;
                background: #28a745;
                color: white;
                border-radius: 50px;
                font-weight: bold;
                margin: 10px 0;
            }
            
            .feature-badge {
                display: inline-block;
                padding: 5px 15px;
                background: #007bff;
                color: white;
                border-radius: 20px;
                font-size: 0.9em;
                margin: 2px;
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            
            .stat-card {
                background: white;
                padding: 20px;
                border-radius: 15px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                border-left: 5px solid #667eea;
            }
            
            .stat-card h3 {
                color: #667eea;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .stat-number {
                font-size: 2em;
                font-weight: bold;
                color: #764ba2;
                margin: 10px 0;
            }
            
            .channels-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 15px;
                margin: 30px 0;
            }
            
            .channel-card {
                background: white;
                padding: 15px;
                border-radius: 10px;
                box-shadow: 0 3px 10px rgba(0,0,0,0.1);
                border-top: 4px solid #667eea;
            }
            
            .channel-card.forward-enabled {
                border-top: 4px solid #28a745;
            }
            
            .channel-card.forward-disabled {
                border-top: 4px solid #dc3545;
            }
            
            .channel-card h4 {
                color: #667eea;
                margin-bottom: 10px;
            }
            
            .forward-status {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 15px;
                font-size: 0.8em;
                font-weight: bold;
                margin-left: 10px;
            }
            
            .forward-enabled .forward-status {
                background: #28a745;
                color: white;
            }
            
            .forward-disabled .forward-status {
                background: #dc3545;
                color: white;
            }
            
            .button-group {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                margin: 30px 0;
                justify-content: center;
            }
            
            .button {
                padding: 12px 25px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 50px;
                font-weight: bold;
                transition: all 0.3s ease;
                border: none;
                cursor: pointer;
                font-size: 1em;
            }
            
            .button:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
            }
            
            .button.test {
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            }
            
            .button.danger {
                background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            }
            
            .log-panel {
                background: #2d2d2d;
                color: #fff;
                padding: 20px;
                border-radius: 10px;
                margin-top: 30px;
                max-height: 300px;
                overflow-y: auto;
                font-family: monospace;
                font-size: 0.9em;
            }
            
            .log-entry {
                padding: 5px 0;
                border-bottom: 1px solid #444;
            }
            
            .log-entry.forward {
                color: #4CAF50;
            }
            
            .log-entry.error {
                color: #f44336;
            }
            
            .log-entry.user {
                color: #2196F3;
            }
            
            @media (max-width: 768px) {
                .container {
                    padding: 15px;
                }
                
                .header h1 {
                    font-size: 1.8em;
                }
                
                .button-group {
                    flex-direction: column;
                }
                
                .button {
                    width: 100%;
                    text-align: center;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎬 Entertainment Tadka Mega Bot v7.0</h1>
                <p>Complete System: 6 Channels | 6 CSV Files | Auto-Forward | Auto-Delete</p>
                <div class='status'>✅ BOT IS RUNNING</div>
                <div>
                    <span class='feature-badge'>🚀 Auto-Forward</span>
                    <span class='feature-badge'>📁 6 CSV Files</span>
                    <span class='feature-badge'>🛡️ Auto-Delete</span>
                    <span class='feature-badge'>🔍 Smart Search</span>
                </div>
            </div>";
    
    // Initialize system on first access
    initialize_system();
    
    // Get statistics
    $csv_stats = get_csv_stats();
    $json_stats = file_exists('stats.json') ? json_decode(file_get_contents('stats.json'), true) : [];
    $users_data = file_exists('users.json') ? json_decode(file_get_contents('users.json'), true) : [];
    $active_users = get_active_users();
    
    echo "<div class='stats-grid'>
            <div class='stat-card'>
                <h3>📊 Total Movies</h3>
                <div class='stat-number'>{$csv_stats['total_movies']}</div>
                <p>Across 6 channels</p>
            </div>
            
            <div class='stat-card'>
                <h3>👥 Total Users</h3>
                <div class='stat-number'>" . ($users_data['total_users'] ?? 0) . "</div>
                <p>" . count($active_users) . " active</p>
            </div>
            
            <div class='stat-card'>
                <h3>📤 Total Forwards</h3>
                <div class='stat-number'>" . ($json_stats['total_forwards'] ?? 0) . "</div>
                <p>Auto-forwarded movies</p>
            </div>
            
            <div class='stat-card'>
                <h3>🛡️ Delete Time</h3>
                <div class='stat-number'>" . DELETE_AFTER_MINUTES . " min</div>
                <p>Auto-delete protection</p>
            </div>
        </div>";
    
    echo "<h2 style='margin: 30px 0 15px 0; color: #667eea;'>📁 6 CSV Files with Auto-Forward Status</h2>
          <div class='channels-grid'>";
    
    foreach ($csv_stats['channel_stats'] as $channel_key => $channel) {
        $forward_class = $channel['auto_forward'] ? 'forward-enabled' : 'forward-disabled';
        $forward_text = $channel['auto_forward'] ? 'AUTO-FORWARD ON' : 'AUTO-FORWARD OFF';
        
        echo "<div class='channel-card $forward_class'>
                <h4>{$channel['name']} <span class='forward-status'>$forward_text</span></h4>
                <p><strong>CSV File:</strong> {$channel['csv_file']}</p>
                <p><strong>Movies:</strong> {$channel['movies']}</p>
                <p><strong>Channel ID:</strong> <code>{$channel['id']}</code></p>";
        
        if ($channel['username']) {
            echo "<p><strong>Username:</strong> {$channel['username']}</p>";
        }
        
        echo "<p><strong>Type:</strong> {$channel['type']}</p>
              </div>";
    }
    
    echo "</div>";
    
    echo "<div class='button-group'>
            <a href='?setup=1' class='button'>🔄 Re-initialize System</a>
            <a href='?webhook=1' class='button'>🔗 Set Webhook</a>
            <a href='?testforward=1' class='button test'>🚀 Test Forward</a>
            <a href='?debug=1' class='button'>🐛 Debug CSV</a>
            <a href='?users=1' class='button'>👥 View Users</a>
            <a href='https://t.me/EntertainmentTadkaBot' class='button' target='_blank'>🤖 Open Bot</a>
            <a href='https://t.me/EntertainmentTadka786' class='button' target='_blank'>📢 Main Channel</a>
          </div>";
    
    // Handle direct actions
    if (isset($_GET['setup'])) {
        echo "<div class='stat-card'>
                <h3>🔄 System Re-initialization</h3>";
        initialize_system();
        echo "<p>✅ System re-initialized successfully!</p>
              </div>";
    }
    
    if (isset($_GET['webhook'])) {
        $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                      "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        
        $result = bot_api('setWebhook', ['url' => $webhook_url]);
        
        echo "<div class='stat-card'>
                <h3>🔗 Webhook Setup</h3>
                <pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto;'>" . 
                json_encode($result, JSON_PRETTY_PRINT) . "</pre>
              </div>";
    }
    
    if (isset($_GET['testforward'])) {
        echo "<div class='stat-card'>
                <h3>🚀 Test Auto-Forward System</h3>";
        
        // Test forward to admin
        $test_channel_id = -1003181705395;
        $test_message_id = 180; // Change this to actual message ID
        
        $test_result = forward_message(ADMIN_ID, $test_channel_id, $test_message_id);
        
        if ($test_result && $test_result['ok']) {
            echo "<p style='color: green;'>✅ Test forward successful!</p>";
            echo "<p>Forwarded message to Admin</p>";
            
            // Send test notification
            send_message(ADMIN_ID, 
                "🚀 <b>TEST FORWARD SUCCESSFUL</b>\n\n" .
                "✅ Auto-forward system is working\n" .
                "🤖 Entertainment Tadka Bot v7.0\n" .
                "⏰ " . date('h:i:s A') . "\n" .
                "📅 " . date('d M Y')
            );
        } else {
            echo "<p style='color: red;'>❌ Test forward failed!</p>";
            echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>" . 
                 json_encode($test_result, JSON_PRETTY_PRINT) . "</pre>";
        }
        
        echo "</div>";
    }
    
    if (isset($_GET['debug'])) {
        echo "<div class='stat-card'>
                <h3>🐛 CSV File Debug</h3>";
        
        foreach ($csv_stats['channel_stats'] as $channel) {
            $csv_file = $channel['csv_file'];
            echo "<h4>📄 $csv_file</h4>";
            
            if (file_exists($csv_file)) {
                $handle = fopen($csv_file, "r");
                if ($handle) {
                    $header = fgetcsv($handle);
                    echo "<p><strong>Header:</strong> " . implode(', ', $header) . "</p>";
                    
                    $sample_rows = [];
                    $row_count = 0;
                    
                    while (($row = fgetcsv($handle)) !== FALSE && $row_count < 3) {
                        $sample_rows[] = $row;
                        $row_count++;
                    }
                    
                    fclose($handle);
                    
                    if (!empty($sample_rows)) {
                        echo "<p><strong>Sample Data:</strong></p>";
                        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
                        foreach ($sample_rows as $row) {
                            echo htmlspecialchars(implode(' | ', $row)) . "\n";
                        }
                        echo "</pre>";
                    }
                }
            }
            echo "<hr>";
        }
        
        echo "</div>";
    }
    
    if (isset($_GET['users'])) {
        echo "<div class='stat-card'>
                <h3>👥 User Statistics</h3>";
        
        if (file_exists('users.json')) {
            $users_data = json_decode(file_get_contents('users.json'), true);
            $active_users = get_active_users();
            
            echo "<p><strong>Total Users:</strong> " . ($users_data['total_users'] ?? 0) . "</p>";
            echo "<p><strong>Active Users:</strong> " . count($active_users) . " (want forwards)</p>";
            echo "<p><strong>Last Updated:</strong> " . ($users_data['last_updated'] ?? 'Never') . "</p>";
            
            echo "<h4>Recent Users:</h4>";
            $recent_users = array_slice($users_data['users'] ?? [], -10, 10, true);
            
            echo "<table style='width: 100%; border-collapse: collapse;'>
                    <tr style='background: #f5f5f5;'>
                        <th style='padding: 8px; border: 1px solid #ddd;'>ID</th>
                        <th style='padding: 8px; border: 1px solid #ddd;'>Name</th>
                        <th style='padding: 8px; border: 1px solid #ddd;'>Username</th>
                        <th style='padding: 8px; border: 1px solid #ddd;'>Join Date</th>
                        <th style='padding: 8px; border: 1px solid #ddd;'>Movies</th>
                    </tr>";
            
            foreach ($recent_users as $uid => $user) {
                $name = htmlspecialchars($user['first_name'] ?? 'User');
                $uname = !empty($user['username']) ? '@' . htmlspecialchars($user['username']) : '-';
                $join_date = $user['join_date'] ?? 'Unknown';
                $movies = $user['total_movies_received'] ?? 0;
                $wants_forward = $user['wants_forward'] ?? true;
                $forward_status = $wants_forward ? '✅' : '❌';
                
                echo "<tr>
                        <td style='padding: 8px; border: 1px solid #ddd;'><code>$uid</code></td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>$name $forward_status</td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>$uname</td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>$join_date</td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>$movies</td>
                      </tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p>No users data found</p>";
        }
        
        echo "</div>";
    }
    
    // Show recent logs with color coding
    echo "<h2 style='margin: 30px 0 15px 0; color: #667eea;'>📝 Recent Activity Logs</h2>
          <div class='log-panel'>";
    
    if (file_exists('bot.log')) {
        $logs = array_slice(file('bot.log'), -50);
        foreach ($logs as $log) {
            $log_class = 'log-entry';
            
            if (strpos($log, 'FORWARD') !== false) {
                $log_class .= ' forward';
            } elseif (strpos($log, 'ERROR') !== false) {
                $log_class .= ' error';
            } elseif (strpos($log, 'USER') !== false) {
                $log_class .= ' user';
            }
            
            echo "<div class='$log_class'>" . htmlspecialchars($log) . "</div>";
        }
    } else {
        echo "No logs yet. Bot is ready!";
    }
    
    echo "</div>";
    
    echo "<div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666;'>
            <p>🤖 <strong>Entertainment Tadka Mega Bot v7.0</strong> | 🚀 <strong>Auto-Forward System</strong></p>
            <p>📱 <strong>Bot:</strong> @EntertainmentTadkaBot | 📢 <strong>Channel:</strong> @EntertainmentTadka786</p>
            <p>⚡ <strong>Features:</strong> 6 Channels | 6 CSV Files | Auto-Forward | Auto-Delete</p>
            <p>📅 <strong>Last Updated:</strong> " . date('d M Y, h:i A') . "</p>
          </div>
        </div>
    </body>
    </html>";
    
    exit;
}

// ================= WEBHOOK PROCESSING =================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    bot_log("=== INCOMING WEBHOOK UPDATE ===", 'WEBHOOK');
    handle_update($update);
    bot_log("=== WEBHOOK PROCESSING COMPLETE ===", 'WEBHOOK');
}

// If no update (cron job or direct call)
if (empty($update)) {
    // Auto-initialize on first run
    if (!file_exists('movies_main.csv')) {
        initialize_system();
    }
    
    // Simple response for health checks
    echo "🎬 Entertainment Tadka Mega Bot v7.0 is running!";
    bot_log("Health check passed", 'SYSTEM');
}
?>
