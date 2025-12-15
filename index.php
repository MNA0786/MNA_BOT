<?php
// ============================================
// ENTERTAINMENT TADKA MEGA BOT v5.0
// 6 Channels - 6 CSV Files - Complete in One File
// ============================================

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Kolkata');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ================= SECURITY HEADERS =================
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// ================= CONFIGURATION =================
define('BOT_TOKEN', '8315381064:AAGk0FGVGmB8j5SjpBvW3rD3_kQHe_hyOWU');
define('ADMIN_ID', '1080317415');
define('DELETE_AFTER_MINUTES', 15);

// ================= ALL 6 CHANNELS CONFIG =================
$ALL_CHANNELS = [
    'main' => [
        'id' => -1003181705395,
        'username' => '@EntertainmentTadka786',
        'name' => 'Movies and Webseries',
        'csv_file' => 'movies_main.csv'
    ],
    'request' => [
        'id' => -1003083386043,
        'username' => '@EntertainmentTadka7860',
        'name' => 'Movies Request Group',
        'csv_file' => 'movies_requests.csv'
    ],
    'backup' => [
        'id' => -1002964109368,
        'username' => '@ETBackup',
        'name' => 'Backup Channel',
        'csv_file' => 'movies_backup.csv'
    ],
    'backup2' => [
        'id' => -1002337293281,
        'username' => '',
        'name' => 'Backup Channel 2',
        'csv_file' => 'movies_backup2.csv'
    ],
    'private' => [
        'id' => -1003251791991,
        'username' => '',
        'name' => 'Private Channel',
        'csv_file' => 'movies_private.csv'
    ],
    'theater' => [
        'id' => -1002831605258,
        'username' => '@threater_print_movies',
        'name' => 'Theater Print Movies',
        'csv_file' => 'movies_theater.csv'
    ]
];

// ================= LOGGING =================
function bot_log($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    file_put_contents('bot.log', $log, FILE_APPEND);
}

// ================= INITIALIZE ALL 6 CSV FILES =================
function initialize_system() {
    global $ALL_CHANNELS;
    
    bot_log("=== SYSTEM START ===");
    
    // Create all 6 CSV files
    foreach ($ALL_CHANNELS as $channel) {
        $csv_file = $channel['csv_file'];
        
        if (!file_exists($csv_file)) {
            $handle = fopen($csv_file, "w");
            fputcsv($handle, ['movie_name','message_id','date','quality','size','language','channel_name']);
            fclose($handle);
            @chmod($csv_file, 0666);
            
            bot_log("Created CSV: $csv_file for {$channel['name']}");
            echo "<p>✅ Created: $csv_file</p>";
        } else {
            $lines = count(file($csv_file));
            $movie_count = max(0, $lines - 1);
            bot_log("Exists: $csv_file with $movie_count movies");
            echo "<p>📊 Exists: $csv_file ($movie_count movies)</p>";
        }
    }
    
    // Create other files
    if (!file_exists('users.json')) {
        file_put_contents('users.json', json_encode(['users' => [], 'last_updated' => date('Y-m-d H:i:s')], JSON_PRETTY_PRINT));
    }
    
    if (!file_exists('stats.json')) {
        file_put_contents('stats.json', json_encode([
            'total_movies' => 0,
            'total_searches' => 0,
            'total_channels' => 6,
            'last_updated' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT));
    }
    
    bot_log("System initialized with 6 CSV files");
    echo "<h3>✅ System Ready with 6 CSV Files!</h3>";
}

// ================= TELEGRAM API FUNCTIONS =================
function bot_api($method, $params = []) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($params)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    return $result ? json_decode($result, true) : false;
}

function send_message($chat_id, $text, $reply_to = null) {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    if ($reply_to) {
        $params['reply_to_message_id'] = $reply_to;
    }
    
    $result = bot_api('sendMessage', $params);
    
    if ($result && $result['ok']) {
        bot_log("Sent to $chat_id");
    } else {
        bot_log("Failed to send to $chat_id");
    }
    
    return $result;
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
        bot_log("Channel not found: $channel_id");
        return false;
    }
    
    $csv_file = $channel['csv_file'];
    $date = date('d-m-Y');
    $channel_name = $channel['name'];
    
    // Auto detect quality
    $quality = 'Unknown';
    if (stripos($movie_name, '1080') !== false) {
        $quality = '1080p';
    } elseif (stripos($movie_name, '720') !== false) {
        $quality = '720p';
    } elseif (stripos($movie_name, '4k') !== false) {
        $quality = '4K';
    } elseif (stripos($movie_name, 'cam') !== false || stripos($movie_name, 'ts') !== false) {
        $quality = 'CAM';
    } elseif (stripos($movie_name, 'hd') !== false) {
        $quality = 'HD';
    }
    
    // Auto detect language
    $language = 'Hindi';
    if (stripos($movie_name, 'english') !== false) {
        $language = 'English';
    } elseif (stripos($movie_name, 'tamil') !== false) {
        $language = 'Tamil';
    } elseif (stripos($movie_name, 'telugu') !== false) {
        $language = 'Telugu';
    }
    
    $size = 'Unknown';
    
    // Add to CSV
    $entry = [$movie_name, $message_id, $date, $quality, $size, $language, $channel_name];
    
    $handle = fopen($csv_file, "a");
    fputcsv($handle, $entry);
    fclose($handle);
    
    // Update stats
    $stats = json_decode(file_get_contents('stats.json'), true);
    $stats['total_movies'] = ($stats['total_movies'] ?? 0) + 1;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents('stats.json', json_encode($stats, JSON_PRETTY_PRINT));
    
    bot_log("Added to $csv_file: $movie_name (Channel: $channel_name)");
    
    return true;
}

function search_in_csv($csv_file, $query) {
    if (!file_exists($csv_file)) {
        return [];
    }
    
    $results = [];
    $handle = fopen($csv_file, "r");
    
    if ($handle !== FALSE) {
        // Skip header
        fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3) {
                $movie_name = trim($row[0]);
                $query_lower = strtolower($query);
                $movie_lower = strtolower($movie_name);
                
                if (strpos($movie_lower, $query_lower) !== false) {
                    $results[] = [
                        'name' => $movie_name,
                        'message_id' => $row[1] ?? '',
                        'date' => $row[2] ?? '',
                        'quality' => $row[3] ?? 'Unknown',
                        'language' => $row[5] ?? 'Hindi',
                        'channel' => $row[6] ?? 'Unknown'
                    ];
                }
            }
        }
        fclose($handle);
    }
    
    return $results;
}

function search_all_channels($query) {
    global $ALL_CHANNELS;
    
    $all_results = [];
    $total_found = 0;
    
    foreach ($ALL_CHANNELS as $channel) {
        $csv_file = $channel['csv_file'];
        $results = search_in_csv($csv_file, $query);
        
        if (!empty($results)) {
            $all_results[$channel['name']] = [
                'results' => $results,
                'count' => count($results),
                'csv_file' => $csv_file
            ];
            $total_found += count($results);
        }
    }
    
    return [
        'all_results' => $all_results,
        'total_found' => $total_found,
        'channels_found' => count($all_results)
    ];
}

function get_csv_stats() {
    global $ALL_CHANNELS;
    
    $stats = [];
    $total_movies = 0;
    
    foreach ($ALL_CHANNELS as $key => $channel) {
        $csv_file = $channel['csv_file'];
        $movie_count = 0;
        
        if (file_exists($csv_file)) {
            $lines = file($csv_file);
            $movie_count = max(0, count($lines) - 1);
            $total_movies += $movie_count;
        }
        
        $stats[$key] = [
            'name' => $channel['name'],
            'csv_file' => $csv_file,
            'movies' => $movie_count,
            'id' => $channel['id'],
            'username' => $channel['username']
        ];
    }
    
    return [
        'channel_stats' => $stats,
        'total_movies' => $total_movies
    ];
}

// ================= DELETE PROTECTION =================
function send_delete_warning($chat_id, $message_id, $file_name) {
    $delete_time = DELETE_AFTER_MINUTES;
    
    $message = "⚠️ <b>DELETE WARNING</b>\n\n";
    $message .= "🎬 <b>File:</b> " . htmlspecialchars($file_name) . "\n";
    $message .= "⏰ <b>Auto-delete in:</b> $delete_time minutes\n\n";
    $message .= "✅ <b>What to do:</b>\n";
    $message .= "1. Forward this file immediately\n";
    $message .= "2. Download and save it\n";
    $message .= "3. Don't wait for last minute\n\n";
    $message .= "📢 <b>Our Channels:</b>\n";
    $message .= "• @EntertainmentTadka786\n";
    $message .= "• @EntertainmentTadka7860\n";
    $message .= "• @ETBackup\n";
    $message .= "• @threater_print_movies";
    
    send_message($chat_id, $message, $message_id);
    bot_log("Delete warning sent for: $file_name");
}

// ================= COMMAND HANDLERS =================
function handle_start($chat_id) {
    $message = "🎬 <b>ENTERTAINMENT TADKA MEGA BOT</b>\n\n";
    $message .= "📢 <b>6 Channels Connected</b>\n";
    $message .= "📄 <b>6 CSV Files</b> (Each channel separate)\n";
    $message .= "🛡️ <b>Auto-delete:</b> " . DELETE_AFTER_MINUTES . " minutes\n\n";
    $message .= "🔍 <b>Search Movies:</b> Just type movie name\n";
    $message .= "🌐 <b>Search All:</b> /searchall movie_name\n";
    $message .= "📊 <b>Statistics:</b> /stats\n";
    $message .= "📁 <b>Channels List:</b> /channels\n";
    $message .= "📝 <b>Request Movie:</b> /request movie_name\n\n";
    $message .= "🚀 <b>Enjoy Unlimited Movies!</b>";
    
    send_message($chat_id, $message);
}

function handle_help($chat_id) {
    $message = "🤖 <b>BOT COMMANDS GUIDE</b>\n\n";
    $message .= "🔍 <b>BASIC SEARCH:</b>\n";
    $message .= "• Just type movie name\n";
    $message .= "• Example: <code>animal</code>\n\n";
    
    $message .= "🌐 <b>ADVANCED SEARCH:</b>\n";
    $message .= "• /searchall movie_name\n";
    $message .= "• Searches in all 6 channels\n\n";
    
    $message .= "📊 <b>STATISTICS:</b>\n";
    $message .= "• /stats - Bot statistics\n";
    $message .= "• /channels - All channels list\n\n";
    
    $message .= "📝 <b>REQUESTS:</b>\n";
    $message .= "• /request movie_name\n";
    $message .= "• We'll add it soon\n\n";
    
    $message .= "🛡️ <b>PROTECTION:</b>\n";
    $message .= "• Files auto-delete in " . DELETE_AFTER_MINUTES . " min\n";
    $message .= "• Forward immediately to save\n\n";
    
    $message .= "📢 <b>MAIN CHANNEL:</b> @EntertainmentTadka786";
    
    send_message($chat_id, $message);
}

function handle_stats($chat_id) {
    $stats_data = get_csv_stats();
    $json_stats = json_decode(file_get_contents('stats.json'), true);
    
    $message = "📊 <b>BOT STATISTICS</b>\n\n";
    $message .= "🎬 <b>Total Movies:</b> {$stats_data['total_movies']}\n";
    $message .= "🔍 <b>Total Searches:</b> " . ($json_stats['total_searches'] ?? 0) . "\n";
    $message .= "📁 <b>Total Channels:</b> 6\n";
    $message .= "📄 <b>Total CSV Files:</b> 6\n";
    $message .= "🛡️ <b>Delete Time:</b> " . DELETE_AFTER_MINUTES . " minutes\n\n";
    
    $message .= "📈 <b>CHANNEL-WISE STATS:</b>\n";
    foreach ($stats_data['channel_stats'] as $channel) {
        $message .= "• <b>{$channel['name']}</b>\n";
        $message .= "  Movies: {$channel['movies']} | CSV: {$channel['csv_file']}\n";
    }
    
    $message .= "\n⏰ <b>Last Updated:</b> " . date('h:i A');
    
    send_message($chat_id, $message);
}

function handle_channels($chat_id) {
    global $ALL_CHANNELS;
    
    $message = "📢 <b>ALL 6 CHANNELS</b>\n\n";
    $message .= "Each channel has its own CSV file\n\n";
    
    foreach ($ALL_CHANNELS as $channel) {
        $movie_count = 0;
        $csv_file = $channel['csv_file'];
        
        if (file_exists($csv_file)) {
            $lines = file($csv_file);
            $movie_count = max(0, count($lines) - 1);
        }
        
        $message .= "🎬 <b>{$channel['name']}</b>\n";
        $message .= "   📄 CSV: <code>$csv_file</code>\n";
        $message .= "   🎥 Movies: $movie_count\n";
        $message .= "   🔢 ID: <code>{$channel['id']}</code>\n";
        
        if ($channel['username']) {
            $message .= "   🔗 {$channel['username']}\n";
        }
        
        $message .= "\n";
    }
    
    $message .= "✅ <b>Auto-add:</b> Movies auto-added to respective CSV";
    
    send_message($chat_id, $message);
}

function handle_searchall($chat_id, $query) {
    if (strlen($query) < 2) {
        send_message($chat_id, "❌ Minimum 2 characters required");
        return;
    }
    
    $search_results = search_all_channels($query);
    
    if ($search_results['total_found'] > 0) {
        $message = "✅ <b>Found {$search_results['total_found']} results for '$query'</b>\n";
        $message .= "📁 In {$search_results['channels_found']} channels\n\n";
        
        foreach ($search_results['all_results'] as $channel_name => $data) {
            $message .= "🎬 <b>$channel_name</b> ({$data['count']} movies):\n";
            
            foreach (array_slice($data['results'], 0, 3) as $result) {
                $short_name = strlen($result['name']) > 35 ? 
                             substr($result['name'], 0, 35) . "..." : $result['name'];
                $message .= "   • $short_name\n";
                $message .= "     📊 {$result['quality']} | 🗣️ {$result['language']}\n";
            }
            
            if ($data['count'] > 3) {
                $message .= "     ... and " . ($data['count'] - 3) . " more\n";
            }
            $message .= "\n";
        }
        
        // Update search stats
        $stats = json_decode(file_get_contents('stats.json'), true);
        $stats['total_searches'] = ($stats['total_searches'] ?? 0) + 1;
        file_put_contents('stats.json', json_encode($stats, JSON_PRETTY_PRINT));
        
    } else {
        $message = "❌ <b>No matches found for '$query'</b>\n\n";
        $message .= "💡 <b>Suggestions:</b>\n";
        $message .= "• Check spelling\n";
        $message .= "• Try shorter name\n";
        $message .= "• Request movie: /request $query\n\n";
        $message .= "🔗 <b>Request Group:</b> @EntertainmentTadka7860";
    }
    
    send_message($chat_id, $message);
}

function handle_request($chat_id, $user_id, $movie_name) {
    $message = "✅ <b>REQUEST RECEIVED</b>\n\n";
    $message .= "🎬 <b>Movie:</b> $movie_name\n";
    $message .= "👤 <b>User ID:</b> $user_id\n";
    $message .= "📝 <b>Status:</b> Added to request list\n";
    $message .= "⏰ <b>Time:</b> " . date('h:i A') . "\n\n";
    $message .= "🔗 <b>Request Group:</b> @EntertainmentTadka7860\n";
    $message .= "📄 <b>Will be added to:</b> movies_requests.csv";
    
    send_message($chat_id, $message);
    
    // Notify admin
    $admin_msg = "📝 <b>NEW MOVIE REQUEST</b>\n\n";
    $admin_msg .= "🎬 <b>Movie:</b> $movie_name\n";
    $admin_msg .= "👤 <b>User ID:</b> $user_id\n";
    $admin_msg .= "⏰ <b>Time:</b> " . date('h:i A');
    
    send_message(ADMIN_ID, $admin_msg);
    
    bot_log("Movie requested: $movie_name by user $user_id");
}

// ================= MAIN BOT HANDLER =================
function handle_update($update) {
    // Handle channel posts (auto-add movies to CSV)
    if (isset($update['channel_post'])) {
        $post = $update['channel_post'];
        $channel_id = $post['chat']['id'];
        $message_id = $post['message_id'];
        
        $channel = get_channel_by_id($channel_id);
        
        if ($channel) {
            $file_name = '';
            
            if (isset($post['caption'])) {
                $file_name = $post['caption'];
            } elseif (isset($post['text'])) {
                $file_name = $post['text'];
            }
            
            if (!empty(trim($file_name))) {
                add_movie_to_csv($channel_id, $file_name, $message_id);
                
                // Notify admin
                $admin_msg = "✅ <b>AUTO-ADDED TO CSV</b>\n\n";
                $admin_msg .= "🎬 <b>Movie:</b> $file_name\n";
                $admin_msg .= "📁 <b>Channel:</b> {$channel['name']}\n";
                $admin_msg .= "📄 <b>CSV File:</b> {$channel['csv_file']}\n";
                $admin_msg .= "🔢 <b>Message ID:</b> $message_id\n";
                $admin_msg .= "⏰ <b>Time:</b> " . date('h:i A');
                
                send_message(ADMIN_ID, $admin_msg);
                
                bot_log("Auto-added to {$channel['csv_file']}: $file_name");
            }
        }
    }
    
    // Handle private messages
    if (isset($update['message'])) {
        $msg = $update['message'];
        $chat_id = $msg['chat']['id'];
        $user_id = $msg['from']['id'];
        $text = $msg['text'] ?? '';
        
        // Handle file uploads (delete protection)
        if (isset($msg['video']) || isset($msg['document'])) {
            $file_name = '';
            
            if (isset($msg['video'])) {
                $file_name = $msg['video']['file_name'] ?? 'Video_' . time() . '.mp4';
            } elseif (isset($msg['document'])) {
                $file_name = $msg['document']['file_name'];
            }
            
            if (isset($msg['caption'])) {
                $file_name = $msg['caption'] . ' - ' . $file_name;
            }
            
            if ($file_name) {
                send_delete_warning($chat_id, $msg['message_id'], $file_name);
            }
        }
        
        // Handle commands
        if (!empty($text)) {
            $parts = explode(' ', $text, 2);
            $command = strtolower($parts[0]);
            $parameter = isset($parts[1]) ? trim($parts[1]) : '';
            
            switch ($command) {
                case '/start':
                    handle_start($chat_id);
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
                    handle_searchall($chat_id, $parameter);
                    break;
                    
                case '/request':
                    if (!empty($parameter)) {
                        handle_request($chat_id, $user_id, $parameter);
                    } else {
                        send_message($chat_id, "❌ Usage: /request movie_name\nExample: /request Animal 2023");
                    }
                    break;
                    
                default:
                    // Regular text - search in all channels
                    if (strlen($text) > 2 && !str_starts_with($text, '/')) {
                        handle_searchall($chat_id, $text);
                    }
                    break;
            }
        }
    }
}

// ================= DIRECT ACCESS PAGE =================
if (!isset($_POST) && php_sapi_name() != 'cli') {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>🎬 Entertainment Tadka Mega Bot</title>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .header {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                padding: 30px;
                border-radius: 10px;
                text-align: center;
                margin-bottom: 20px;
            }
            .section {
                background: white;
                padding: 20px;
                border-radius: 10px;
                margin-bottom: 15px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            .channel-list {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
                margin-top: 15px;
            }
            .channel-card {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                border-left: 4px solid #667eea;
            }
            .status {
                display: inline-block;
                padding: 5px 10px;
                border-radius: 20px;
                font-size: 14px;
                font-weight: bold;
            }
            .status.online {
                background: #28a745;
                color: white;
            }
            .button {
                display: inline-block;
                padding: 12px 25px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 5px;
                font-weight: bold;
            }
            .button:hover {
                background: #764ba2;
            }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>🎬 Entertainment Tadka Mega Bot</h1>
            <p>6 Channels | 6 CSV Files | Auto-Delete Protection</p>
            <span class='status online'>✅ BOT ONLINE</span>
        </div>";
    
    // Initialize system on direct access
    initialize_system();
    
    // Show statistics
    $stats_data = get_csv_stats();
    
    echo "<div class='section'>
            <h2>📊 Current Statistics</h2>
            <p><b>Total Movies:</b> {$stats_data['total_movies']}</p>
            <p><b>Total Channels:</b> 6</p>
            <p><b>Delete Time:</b> " . DELETE_AFTER_MINUTES . " minutes</p>
            <p><b>Bot:</b> @EntertainmentTadkaBot</p>
          </div>";
    
    echo "<div class='section'>
            <h2>📁 All 6 CSV Files</h2>
            <div class='channel-list'>";
    
    foreach ($stats_data['channel_stats'] as $channel) {
        echo "<div class='channel-card'>
                <h3>{$channel['name']}</h3>
                <p><b>CSV File:</b> {$channel['csv_file']}</p>
                <p><b>Movies:</b> {$channel['movies']}</p>";
        
        if ($channel['username']) {
            echo "<p><b>Username:</b> {$channel['username']}</p>";
        }
        
        echo "<p><b>Channel ID:</b> <code>{$channel['id']}</code></p>
              </div>";
    }
    
    echo "</div></div>";
    
    echo "<div class='section'>
            <h2>🚀 Quick Actions</h2>
            <a href='?setup=1' class='button'>🔄 Re-initialize CSVs</a>
            <a href='?webhook=1' class='button'>🔗 Set Webhook</a>
            <a href='?test=1' class='button'>🧪 Test Bot</a>
            <a href='https://t.me/EntertainmentTadkaBot' class='button' target='_blank'>🤖 Open Bot</a>
          </div>";
    
    // Handle direct actions
    if (isset($_GET['setup'])) {
        echo "<div class='section'>
                <h2>🔄 Re-initializing CSVs</h2>";
        initialize_system();
        echo "</div>";
    }
    
    if (isset($_GET['webhook'])) {
        $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                      "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        
        $result = bot_api('setWebhook', ['url' => $webhook_url]);
        
        echo "<div class='section'>
                <h2>🔗 Webhook Setup</h2>
                <pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>
              </div>";
    }
    
    if (isset($_GET['test'])) {
        echo "<div class='section'>
                <h2>🧪 Bot Test</h2>
                <p>Sending test message to admin...</p>";
        
        $test_msg = send_message(ADMIN_ID, 
            "✅ <b>BOT TEST SUCCESSFUL</b>\n\n" .
            "🤖 Entertainment Tadka Bot\n" .
            "📅 " . date('d M Y, h:i A') . "\n" .
            "🌐 " . $_SERVER['HTTP_HOST'] . "\n" .
            "✅ 6 CSV Files Ready\n" .
            "✅ 6 Channels Connected"
        );
        
        echo "<p>Test message sent to Admin!</p>";
        echo "</div>";
    }
    
    echo "<div class='section'>
            <h2>📝 Recent Logs</h2>
            <pre style='background: #2d2d2d; color: #fff; padding: 15px; border-radius: 5px; max-height: 200px; overflow-y: auto;'>";
    
    if (file_exists('bot.log')) {
        $logs = array_slice(file('bot.log'), -20);
        foreach ($logs as $log) {
            echo htmlspecialchars($log);
        }
    } else {
        echo "No logs yet";
    }
    
    echo "</pre></div>";
    
    echo "<div class='section'>
            <p style='text-align: center; color: #666;'>
                🤖 <b>Entertainment Tadka Mega Bot</b><br>
                Version 5.0 | 6 Channels | 6 CSV Files
            </p>
          </div>
    </body>
    </html>";
    
    exit;
}

// ================= WEBHOOK PROCESSING =================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    bot_log("=== NEW UPDATE ===");
    handle_update($update);
    bot_log("=== UPDATE PROCESSED ===");
}

// If no update (cron job or direct call)
if (empty($update)) {
    // Auto-initialize on first run
    if (!file_exists('movies_main.csv')) {
        initialize_system();
    }
    
    // Simple response for health checks
    echo "🎬 Entertainment Tadka Bot is running!";
}
?>
