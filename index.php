<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==============================
// CONFIG - ENVIRONMENT VARIABLES
// ==============================
function loadConfig() {
    $config = [];
    
    // Bot Details
    $config['bot_token'] = getenv('BOT_TOKEN') ?: '8315381064:AAGk0FGVGmB8j5SjpBvW3rD3_kQHe_hyOWU';
    $config['api_id'] = getenv('API_ID') ?: '21944581';
    $config['api_hash'] = getenv('API_HASH') ?: '7b1c174a5cd3466e25a976c39a791737';
    $config['admin_id'] = intval(getenv('ADMIN_ID') ?: '1080317415');
    $config['bot_username'] = getenv('BOT_USERNAME') ?: '@EntertainmentTadkaBot';
    $config['bot_id'] = getenv('BOT_ID') ?: '8315381064';
    
    // Public Channels
    $config['public_channels'] = [
        [
            'name' => 'Main Channel',
            'username' => getenv('CHANNEL1_USERNAME') ?: '@EntertainmentTadka786',
            'id' => intval(getenv('CHANNEL1_ID') ?: '-1003181705395')
        ],
        [
            'name' => 'Serial Channel',
            'username' => getenv('CHANNEL2_USERNAME') ?: '@Entertainment_Tadka_Serial_786',
            'id' => intval(getenv('CHANNEL2_ID') ?: '-1003614546520')
        ],
        [
            'name' => 'Theater Print',
            'username' => getenv('CHANNEL3_USERNAME') ?: '@threater_print_movies',
            'id' => intval(getenv('CHANNEL3_ID') ?: '-1002831605258')
        ],
        [
            'name' => 'Backup Channel',
            'username' => getenv('CHANNEL4_USERNAME') ?: '@ETBackup',
            'id' => intval(getenv('CHANNEL4_ID') ?: '-1002964109368')
        ]
    ];
    
    // Private Channels
    $config['private_channels'] = [
        [
            'name' => 'Private Channel 1',
            'id' => intval(getenv('PRIVATE_CHANNEL1_ID') ?: '-1003251791991')
        ],
        [
            'name' => 'Private Channel 2',
            'id' => intval(getenv('PRIVATE_CHANNEL2_ID') ?: '-1002337293281')
        ]
    ];
    
    // Request Group
    $config['request_group'] = [
        'username' => getenv('REQUEST_GROUP_USERNAME') ?: '@EntertainmentTadka7860',
        'id' => intval(getenv('REQUEST_GROUP_ID') ?: '-1003083386043')
    ];
    
    return $config;
}

$CONFIG = loadConfig();
define('BOT_TOKEN', $CONFIG['bot_token']);
define('ADMIN_ID', $CONFIG['admin_id']);
define('BACKUP_DIR', 'backups/');
define('DEPLOY_FLAG', 'deploy_notification.txt');

// ==============================
// FILE INITIALIZATION
// ==============================
if (!file_exists('movies.json')) {
    file_put_contents('movies.json', json_encode([]));
}

if (!file_exists('requests.json')) {
    $default = ['pending' => [], 'approved' => []];
    file_put_contents('requests.json', json_encode($default, JSON_PRETTY_PRINT));
}

if (!file_exists(BACKUP_DIR)) {
    @mkdir(BACKUP_DIR, 0777, true);
}

// ==============================
// TELEGRAM API HELPERS
// ==============================
function apiRequest($method, $params = array(), $is_multipart = false) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    if ($is_multipart) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        if ($res === false) {
            error_log("CURL ERROR: " . curl_error($ch));
        }
        curl_close($ch);
        return $res;
    } else {
        $options = array(
            'http' => array(
                'method' => 'POST',
                'content' => http_build_query($params),
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false
            )
        );
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            error_log("apiRequest failed for method $method");
        }
        return $result;
    }
}

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    $result = apiRequest('sendMessage', $data);
    return json_decode($result, true);
}

function copyMessage($chat_id, $from_chat_id, $message_id) {
    return apiRequest('copyMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    return apiRequest('forwardMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
    $data = [
        'callback_query_id' => $callback_query_id,
        'show_alert' => $show_alert
    ];
    if ($text) $data['text'] = $text;
    return apiRequest('answerCallbackQuery', $data);
}

function editMessageText($chat_id, $message_id, $text, $reply_markup = null, $parse_mode = null) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    return apiRequest('editMessageText', $data);
}

function deleteMessage($chat_id, $message_id) {
    return apiRequest('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

function sendChatAction($chat_id, $action) {
    return apiRequest('sendChatAction', [
        'chat_id' => $chat_id,
        'action' => $action
    ]);
}

function typing($chat_id) {
    return sendChatAction($chat_id, 'typing');
}

function sendWithTyping($chat_id, $text, $keyboard = null, $parse_mode = null) {
    typing($chat_id);
    usleep(700000);
    return sendMessage($chat_id, $text, $keyboard, $parse_mode);
}

// ==============================
// MOVIE FUNCTIONS - JSON BASED
// ==============================
function cleanTitle($text) {
    $text = preg_replace('/\.(mkv|mp4|avi|mov|wmv|flv|mpeg|mpg|3gp|webm)$/i', '', $text);
    $text = preg_replace('/(480p|720p|1080p|4K|2160p|1440p|360p|240p)/i', '', $text);
    $text = preg_replace('/(HDRip|WEBRip|BRRip|BluRay|DVDRip|HDTV|PDTV|WEB-DL|WEB\sDL)/i', '', $text);
    $text = preg_replace('/(x264|x265|HEVC|AVC|H\.264|H\.265)/i', '', $text);
    $text = preg_replace('/[\[\]\(\)\{\}\.\-\_]/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function getAllMovies() {
    $file = 'movies.json';
    if (!file_exists($file)) return [];
    
    $content = file_get_contents($file);
    $movies = json_decode($content, true);
    
    return is_array($movies) ? $movies : [];
}

function saveMovieToJSON($name, $message_id, $channel_id, $channel_name) {
    $file = 'movies.json';
    $movies = getAllMovies();
    
    foreach ($movies as $movie) {
        if ($movie['message_id'] == $message_id && $movie['channel_id'] == $channel_id) {
            return false;
        }
    }
    
    $movies[] = [
        'name' => $name,
        'message_id' => $message_id,
        'channel_id' => $channel_id,
        'channel_name' => $channel_name,
        'date_added' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($file, json_encode($movies, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return true;
}

function isDuplicate($message_id, $channel_id) {
    $movies = getAllMovies();
    foreach ($movies as $movie) {
        if ($movie['message_id'] == $message_id && $movie['channel_id'] == $channel_id) {
            return true;
        }
    }
    return false;
}

function deliverMovie($chat_id, $message_id, $channel_id) {
    $result = json_decode(copyMessage($chat_id, $channel_id, $message_id), true);
    return isset($result['ok']) && $result['ok'] === true;
}

function searchMovies($query) {
    $movies = getAllMovies();
    $q = strtolower(trim($query));
    $results = [];
    
    foreach ($movies as $movie) {
        if (empty($movie['name'])) continue;
        
        if (strpos(strtolower($movie['name']), $q) !== false) {
            $results[] = $movie;
        }
    }
    
    return $results;
}

function simple_search($chat_id, $query) {
    $movies = getAllMovies();
    $q = strtolower(trim($query));
    
    if (strlen($q) < 2) {
        sendMessage($chat_id, "❌ Please enter at least 2 characters");
        return;
    }
    
    if (empty($movies)) {
        sendMessage($chat_id, "📭 No movies in database yet");
        return;
    }
    
    $found = [];
    foreach ($movies as $movie) {
        if (empty($movie['name'])) continue;
        
        if (strpos(strtolower($movie['name']), $q) !== false) {
            $found[$movie['name']] = [
                'message_id' => $movie['message_id'],
                'channel_id' => $movie['channel_id']
            ];
        }
    }
    
    if (!empty($found)) {
        $unique_names = array_unique(array_keys($found));
        $msg = "🔍 Found " . count($unique_names) . " movies:\n\n";
        $i = 1;
        foreach ($unique_names as $name) {
            $msg .= "$i. " . htmlspecialchars($name) . "\n";
            $i++; 
            if ($i > 15) break;
        }
        
        if (count($unique_names) > 15) {
            $msg .= "\n... and " . (count($unique_names) - 15) . " more";
        }
        
        sendMessage($chat_id, $msg, null, 'HTML');
        
        $buttons = [];
        $count = 0;
        foreach ($unique_names as $name) {
            if ($count >= 5) break;
            $buttons[] = [['text' => "🎬 " . htmlspecialchars($name), 'callback_data' => 'get_' . $name]];
            $count++;
        }
        
        if (!empty($buttons)) {
            sendMessage($chat_id, "👇 Select movie to download:", ['inline_keyboard' => $buttons], 'HTML');
        }
    } else {
        sendMessage($chat_id, "😔 No movies found for '" . htmlspecialchars($query) . "'");
    }
}

function getMovieFiles($movie_name) {
    $movies = getAllMovies();
    $results = [];
    $search = strtolower(trim($movie_name));
    
    foreach ($movies as $movie) {
        if (empty($movie['name'])) continue;
        
        if (strpos(strtolower($movie['name']), $search) !== false) {
            $results[] = $movie;
        }
    }
    
    return $results;
}

// ==============================
// REQUEST SYSTEM
// ==============================
function loadRequests() {
    $file = 'requests.json';
    if (!file_exists($file)) {
        $default = ['pending' => [], 'approved' => []];
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }
    return json_decode(file_get_contents($file), true);
}

function saveRequests($data) {
    file_put_contents('requests.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function addMovieRequest($user_id, $username, $movie_name) {
    global $CONFIG;
    
    $requests = loadRequests();
    
    foreach ($requests['pending'] as $req) {
        if ($req['user_id'] == $user_id && strtolower($req['movie']) == strtolower($movie_name)) {
            return false;
        }
    }
    
    $request = [
        'id' => uniqid('req_'),
        'user_id' => $user_id,
        'username' => $username ?: 'unknown',
        'movie' => $movie_name,
        'time' => date('Y-m-d H:i:s')
    ];
    
    $requests['pending'][] = $request;
    saveRequests($requests);
    
    $msg = "📝 <b>New Movie Request</b>\n\n";
    $msg .= "👤 User: @" . htmlspecialchars($username ?: 'unknown') . "\n";
    $msg .= "🎬 Movie: <b>" . htmlspecialchars($movie_name) . "</b>\n";
    $msg .= "🆔 Request ID: <code>" . $request['id'] . "</code>\n\n";
    $msg .= "✅ Use /approve " . $request['id'] . " to approve";
    
    sendMessage($CONFIG['request_group']['id'], $msg, null, 'HTML');
    
    return $request['id'];
}

function getUserRequests($user_id) {
    $requests = loadRequests();
    $user_reqs = [];
    foreach ($requests['pending'] as $req) {
        if ($req['user_id'] == $user_id) {
            $user_reqs[] = $req;
        }
    }
    return $user_reqs;
}

function getAllPendingRequests() {
    $requests = loadRequests();
    return $requests['pending'];
}

function approveRequest($request_id) {
    $requests = loadRequests();
    foreach ($requests['pending'] as $key => $req) {
        if ($req['id'] == $request_id) {
            $requests['approved'][] = $req;
            unset($requests['pending'][$key]);
            $requests['pending'] = array_values($requests['pending']);
            saveRequests($requests);
            
            $msg = "✅ <b>Request Approved!</b>\n\n";
            $msg .= "Your request for '" . htmlspecialchars($req['movie']) . "' has been received!\n";
            $msg .= "It will be added to the channel soon.\n\n";
            $msg .= "Thank you for using Entertainment Tadka! 🎬";
            
            sendMessage($req['user_id'], $msg, null, 'HTML');
            
            global $CONFIG;
            $group_msg = "✅ Request approved for: " . htmlspecialchars($req['movie']) . "\n";
            $group_msg .= "👤 User: @" . htmlspecialchars($req['username']);
            sendMessage($CONFIG['request_group']['id'], $group_msg);
            
            return true;
        }
    }
    return false;
}

// ==============================
// ADMIN FUNCTIONS
// ==============================
function getStats() {
    $movies = getAllMovies();
    $requests = loadRequests();
    
    $unique_names = [];
    foreach ($movies as $movie) {
        if (!empty($movie['name'])) {
            $unique_names[$movie['name']] = true;
        }
    }
    
    $stats = [
        'total_movies' => count($movies),
        'unique_movies' => count($unique_names),
        'total_pending' => count($requests['pending'] ?? []),
        'total_approved' => count($requests['approved'] ?? []),
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    return $stats;
}

// ==============================
// ADMIN PANEL - COMPLETE WITH ALL COMMANDS
// ==============================
function showAdminPanel($chat_id, $user_id) {
    global $CONFIG;
    
    if ($user_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Unauthorized! You are not admin.");
        return;
    }
    
    $stats = getStats();
    $pending = getAllPendingRequests();
    $movies = getAllMovies();
    
    $panel = "👑 <b>ADMIN CONTROL PANEL</b>\n";
    $panel .= "══════════════════════\n\n";
    
    $panel .= "👤 <b>Admin:</b> <code>" . $user_id . "</code>\n";
    $panel .= "📅 <b>Date:</b> " . date('d-m-Y H:i') . "\n\n";
    
    $panel .= "📊 <b>QUICK STATS</b>\n";
    $panel .= "├ 🎬 Total Movies: <b>" . count($movies) . "</b>\n";
    $panel .= "├ ⏳ Pending: <b>" . count($pending) . "</b>\n";
    $panel .= "└ ✅ Approved: <b>" . ($stats['total_approved'] ?? 0) . "</b>\n\n";
    
    $panel .= "⚡ <b>ADMIN COMMANDS</b>\n";
    $panel .= "══════════════════════\n\n";
    
    $buttons = [
        'inline_keyboard' => [
            [
                ['text' => "📋 Pending (" . count($pending) . ")", 'callback_data' => 'admin_pending'],
                ['text' => "✅ Approve", 'callback_data' => 'admin_approve_menu']
            ],
            [
                ['text' => "🎬 Total Uploads", 'callback_data' => 'admin_total_uploads'],
                ['text' => "📊 Statistics", 'callback_data' => 'admin_stats']
            ],
            [
                ['text' => "⚡ Bulk Approve", 'callback_data' => 'admin_bulk_menu'],
                ['text' => "🔄 Refresh", 'callback_data' => 'admin_refresh']
            ],
            [
                ['text' => "📢 Channels", 'callback_data' => 'admin_channels'],
                ['text' => "❌ Close", 'callback_data' => 'admin_close']
            ]
        ]
    ];
    
    sendMessage($chat_id, $panel, $buttons, 'HTML');
}

function handleAdminCallback($callback_data, $chat_id, $message_id, $query_id) {
    global $CONFIG;
    
    switch($callback_data) {
        
        case 'admin_pending':
            $pending = getAllPendingRequests();
            
            if (empty($pending)) {
                editMessageText($chat_id, $message_id, "📭 No pending requests! Everyone is happy. 🎉", null, 'HTML');
                answerCallbackQuery($query_id, "No pending requests");
                return;
            }
            
            $msg = "⏳ <b>PENDING REQUESTS (" . count($pending) . ")</b>\n";
            $msg .= "══════════════════════\n\n";
            
            $keyboard = ['inline_keyboard' => []];
            
            foreach ($pending as $index => $req) {
                $msg .= "<b>" . ($index + 1) . ". " . htmlspecialchars($req['movie']) . "</b>\n";
                $msg .= "👤 @" . htmlspecialchars($req['username'] ?: 'unknown') . "\n";
                $msg .= "🆔 <code>" . $req['id'] . "</code>\n";
                $msg .= "📅 " . $req['time'] . "\n\n";
                
                $keyboard['inline_keyboard'][] = [
                    ['text' => "✅ Approve", 'callback_data' => 'approve_' . $req['id']]
                ];
            }
            
            $keyboard['inline_keyboard'][] = [
                ['text' => "◀ Back to Admin", 'callback_data' => 'admin_back']
            ];
            
            editMessageText($chat_id, $message_id, $msg, $keyboard, 'HTML');
            answerCallbackQuery($query_id, "Loading pending requests...");
            break;
            
        case 'admin_approve_menu':
            $pending = getAllPendingRequests();
            
            if (empty($pending)) {
                answerCallbackQuery($query_id, "No pending requests", true);
                return;
            }
            
            $msg = "✅ <b>APPROVE REQUESTS</b>\n";
            $msg .= "══════════════════════\n\n";
            $msg .= "Select request to approve:\n\n";
            
            $keyboard = ['inline_keyboard' => []];
            $row = [];
            
            foreach ($pending as $index => $req) {
                $short_name = strlen($req['movie']) > 20 ? substr($req['movie'], 0, 18) . ".." : $req['movie'];
                $row[] = ['text' => ($index + 1) . ". ✓", 'callback_data' => 'approve_' . $req['id']];
                
                if (count($row) == 2) {
                    $keyboard['inline_keyboard'][] = $row;
                    $row = [];
                }
            }
            
            if (!empty($row)) {
                $keyboard['inline_keyboard'][] = $row;
            }
            
            $keyboard['inline_keyboard'][] = [
                ['text' => "⚡ Bulk Approve", 'callback_data' => 'admin_bulk_menu']
            ];
            
            $keyboard['inline_keyboard'][] = [
                ['text' => "◀ Back to Admin", 'callback_data' => 'admin_back']
            ];
            
            editMessageText($chat_id, $message_id, $msg, $keyboard, 'HTML');
            answerCallbackQuery($query_id);
            break;
            
        case 'admin_total_uploads':
            $movies = getAllMovies();
            $unique = [];
            $channel_count = [];
            
            foreach ($movies as $m) {
                $unique[$m['name']] = true;
                $channel = $m['channel_name'] ?? 'Unknown';
                $channel_count[$channel] = ($channel_count[$channel] ?? 0) + 1;
            }
            
            $msg = "🎬 <b>TOTAL UPLOADS</b>\n";
            $msg .= "══════════════════════\n\n";
            $msg .= "📊 <b>Statistics:</b>\n";
            $msg .= "├ Total Files: <b>" . count($movies) . "</b>\n";
            $msg .= "├ Unique Movies: <b>" . count($unique) . "</b>\n";
            $msg .= "└ Channels: <b>" . count($channel_count) . "</b>\n\n";
            
            $msg .= "📢 <b>Channel Breakdown:</b>\n";
            foreach ($channel_count as $channel => $count) {
                $msg .= "├ " . $channel . ": <b>" . $count . "</b>\n";
            }
            
            $recent = array_slice($movies, -5);
            $msg .= "\n🆕 <b>Recent Additions:</b>\n";
            foreach ($recent as $m) {
                $msg .= "├ " . htmlspecialchars($m['name']) . "\n";
            }
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => "◀ Back to Admin", 'callback_data' => 'admin_back']]
                ]
            ];
            
            editMessageText($chat_id, $message_id, $msg, $keyboard, 'HTML');
            answerCallbackQuery($query_id);
            break;
            
        case 'admin_stats':
            $movies = getAllMovies();
            $requests = loadRequests();
            $pending = count($requests['pending'] ?? []);
            $approved = count($requests['approved'] ?? []);
            
            $unique = [];
            foreach ($movies as $m) {
                $unique[$m['name']] = true;
            }
            
            $msg = "📊 <b>BOT STATISTICS</b>\n";
            $msg .= "══════════════════════\n\n";
            $msg .= "🎬 <b>Movies:</b>\n";
            $msg .= "├ Total Files: " . count($movies) . "\n";
            $msg .= "├ Unique Movies: " . count($unique) . "\n";
            $msg .= "└ Avg per Movie: " . round(count($movies) / max(1, count($unique)), 1) . "\n\n";
            
            $msg .= "📝 <b>Requests:</b>\n";
            $msg .= "├ Pending: " . $pending . "\n";
            $msg .= "├ Approved: " . $approved . "\n";
            $msg .= "└ Total: " . ($pending + $approved) . "\n\n";
            
            $msg .= "⏰ <b>System:</b>\n";
            $msg .= "├ Last Updated: " . date('d-m-Y H:i') . "\n";
            $msg .= "└ PHP Version: " . phpversion();
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => "◀ Back to Admin", 'callback_data' => 'admin_back']]
                ]
            ];
            
            editMessageText($chat_id, $message_id, $msg, $keyboard, 'HTML');
            answerCallbackQuery($query_id);
            break;
            
        case 'admin_bulk_menu':
            $pending = getAllPendingRequests();
            $count = count($pending);
            
            if ($count == 0) {
                answerCallbackQuery($query_id, "No pending requests", true);
                return;
            }
            
            $msg = "⚡ <b>BULK APPROVE</b>\n";
            $msg .= "══════════════════════\n\n";
            $msg .= "Total Pending: <b>" . $count . "</b>\n\n";
            $msg .= "Select how many to approve:\n";
            
            $keyboard = ['inline_keyboard' => []];
            
            $options = [5, 10, 25, 50, 100];
            $row = [];
            
            foreach ($options as $opt) {
                if ($opt <= $count) {
                    $row[] = ['text' => "✅ $opt", 'callback_data' => 'bulk_approve_' . $opt];
                }
                if (count($row) == 3) {
                    $keyboard['inline_keyboard'][] = $row;
                    $row = [];
                }
            }
            
            if (!empty($row)) {
                $keyboard['inline_keyboard'][] = $row;
            }
            
            $keyboard['inline_keyboard'][] = [
                ['text' => "✅ Approve All ($count)", 'callback_data' => 'approve_all']
            ];
            
            $keyboard['inline_keyboard'][] = [
                ['text' => "◀ Back to Admin", 'callback_data' => 'admin_back']
            ];
            
            editMessageText($chat_id, $message_id, $msg, $keyboard, 'HTML');
            answerCallbackQuery($query_id);
            break;
            
        case 'admin_channels':
            $msg = "📢 <b>CHANNELS</b>\n";
            $msg .= "══════════════════════\n\n";
            $msg .= "<b>Public Channels:</b>\n";
            
            foreach ($CONFIG['public_channels'] as $ch) {
                $msg .= "├ " . $ch['username'] . "\n";
                $msg .= "│ └ ID: <code>" . $ch['id'] . "</code>\n";
            }
            
            $msg .= "\n<b>Private Channels:</b>\n";
            foreach ($CONFIG['private_channels'] as $ch) {
                $msg .= "├ " . $ch['name'] . "\n";
                $msg .= "│ └ ID: <code>" . $ch['id'] . "</code>\n";
            }
            
            $msg .= "\n<b>Request Group:</b>\n";
            $msg .= "├ " . $CONFIG['request_group']['username'] . "\n";
            $msg .= "└ ID: <code>" . $CONFIG['request_group']['id'] . "</code>\n";
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => "◀ Back to Admin", 'callback_data' => 'admin_back']]
                ]
            ];
            
            editMessageText($chat_id, $message_id, $msg, $keyboard, 'HTML');
            answerCallbackQuery($query_id);
            break;
            
        case 'admin_refresh':
            $stats = getStats();
            $pending = getAllPendingRequests();
            $movies = getAllMovies();
            
            $panel = "👑 <b>ADMIN CONTROL PANEL</b>\n";
            $panel .= "══════════════════════\n\n";
            $panel .= "👤 <b>Admin:</b> <code>" . $chat_id . "</code>\n";
            $panel .= "📅 <b>Refreshed:</b> " . date('H:i:s') . "\n\n";
            $panel .= "📊 <b>Stats:</b>\n";
            $panel .= "├ Movies: " . count($movies) . "\n";
            $panel .= "├ Pending: " . count($pending) . "\n";
            $panel .= "└ Approved: " . ($stats['total_approved'] ?? 0) . "\n\n";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => "📋 Pending", 'callback_data' => 'admin_pending'],
                        ['text' => "✅ Approve", 'callback_data' => 'admin_approve_menu']
                    ],
                    [
                        ['text' => "🎬 Uploads", 'callback_data' => 'admin_total_uploads'],
                        ['text' => "📊 Stats", 'callback_data' => 'admin_stats']
                    ],
                    [
                        ['text' => "⚡ Bulk", 'callback_data' => 'admin_bulk_menu'],
                        ['text' => "📢 Channels", 'callback_data' => 'admin_channels']
                    ],
                    [
                        ['text' => "🔄 Refresh", 'callback_data' => 'admin_refresh'],
                        ['text' => "❌ Close", 'callback_data' => 'admin_close']
                    ]
                ]
            ];
            
            editMessageText($chat_id, $message_id, $panel, $keyboard, 'HTML');
            answerCallbackQuery($query_id, "Refreshed!");
            break;
            
        case 'admin_back':
            $stats = getStats();
            $pending = getAllPendingRequests();
            $movies = getAllMovies();
            
            $panel = "👑 <b>ADMIN CONTROL PANEL</b>\n";
            $panel .= "══════════════════════\n\n";
            $panel .= "👤 <b>Admin:</b> <code>" . $chat_id . "</code>\n";
            $panel .= "📅 <b>Date:</b> " . date('d-m-Y H:i') . "\n\n";
            $panel .= "📊 <b>Stats:</b>\n";
            $panel .= "├ Movies: " . count($movies) . "\n";
            $panel .= "├ Pending: " . count($pending) . "\n";
            $panel .= "└ Approved: " . ($stats['total_approved'] ?? 0) . "\n\n";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => "📋 Pending", 'callback_data' => 'admin_pending'],
                        ['text' => "✅ Approve", 'callback_data' => 'admin_approve_menu']
                    ],
                    [
                        ['text' => "🎬 Uploads", 'callback_data' => 'admin_total_uploads'],
                        ['text' => "📊 Stats", 'callback_data' => 'admin_stats']
                    ],
                    [
                        ['text' => "⚡ Bulk", 'callback_data' => 'admin_bulk_menu'],
                        ['text' => "📢 Channels", 'callback_data' => 'admin_channels']
                    ],
                    [
                        ['text' => "🔄 Refresh", 'callback_data' => 'admin_refresh'],
                        ['text' => "❌ Close", 'callback_data' => 'admin_close']
                    ]
                ]
            ];
            
            editMessageText($chat_id, $message_id, $panel, $keyboard, 'HTML');
            answerCallbackQuery($query_id);
            break;
            
        case 'admin_close':
            deleteMessage($chat_id, $message_id);
            answerCallbackQuery($query_id, "Admin panel closed");
            break;
    }
}

// ==============================
// DEPLOY NOTIFICATION
// ==============================
function sendDeployNotification() {
    global $CONFIG;
    
    $stats = getStats();
    $movies = getAllMovies();
    
    $message = "🚀 <b>Bot Deployed Successfully!</b>\n\n";
    $message .= "📅 Time: " . date('Y-m-d H:i:s') . "\n";
    $message .= "🤖 Bot: " . $CONFIG['bot_username'] . "\n";
    $message .= "🌐 Platform: Render.com\n";
    $message .= "✅ Status: Online\n\n";
    
    $message .= "📊 <b>Statistics:</b>\n";
    $message .= "• Total Movies: " . count($movies) . "\n";
    $message .= "• Pending Requests: " . count(getAllPendingRequests()) . "\n\n";
    
    $message .= "📢 <b>Public Channels:</b>\n";
    foreach ($CONFIG['public_channels'] as $ch) {
        $message .= "• " . $ch['username'] . "\n";
    }
    
    $message .= "\n💬 <b>Request Group:</b>\n";
    $message .= "• " . $CONFIG['request_group']['username'] . "\n\n";
    
    $message .= "👑 Admin ID: " . ADMIN_ID;
    
    sendMessage(ADMIN_ID, $message, null, 'HTML');
}

// ==============================
// MAIN UPDATE PROCESSING
// ==============================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    
    // ==============================
    // CHANNEL POST HANDLING - AUTO INDEXING
    // ==============================
    if (isset($update['channel_post'])) {
        $post = $update['channel_post'];
        $chat_id = $post['chat']['id'];
        
        $is_public = false;
        $channel_name = '';
        foreach ($CONFIG['public_channels'] as $channel) {
            if ($channel['id'] == $chat_id) {
                $is_public = true;
                $channel_name = $channel['username'];
                break;
            }
        }
        
        if ($is_public) {
            $message_id = $post['message_id'];
            $caption = $post['caption'] ?? '';
            
            if (isset($post['document'])) {
                $file_name = $post['document']['file_name'] ?? 'Unknown';
                $clean_name = cleanTitle($caption);
                
                if (empty($clean_name)) {
                    $clean_name = cleanTitle($file_name);
                }
                
                if (!empty($clean_name) && !isDuplicate($message_id, $chat_id)) {
                    saveMovieToJSON($clean_name, $message_id, $chat_id, $channel_name);
                }
            }
            
            if (isset($post['video'])) {
                $clean_name = cleanTitle($caption);
                
                if (!empty($clean_name) && !isDuplicate($message_id, $chat_id)) {
                    saveMovieToJSON($clean_name, $message_id, $chat_id, $channel_name);
                }
            }
        }
    }
    
    // ==============================
    // MESSAGE HANDLING
    // ==============================
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $username = $message['from']['username'] ?? '';
        $text = isset($message['text']) ? trim($message['text']) : '';
        $chat_type = $message['chat']['type'] ?? 'private';
        
        if ($chat_type !== 'private') {
            if (strpos($text, '/') !== 0) {
                if (strlen($text) < 3 || strlen($text) > 100) {
                    return;
                }
            }
        }
        
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $params = array_slice($parts, 1);
            
            switch ($command) {
                
                case '/start':
                    $welcome = "🎬 <b>Welcome to Entertainment Tadka!</b>\n\n";
                    $welcome .= "📢 <b>How to use:</b>\n";
                    $welcome .= "• Simply type any movie name\n";
                    $welcome .= "• Examples: <code>kgf</code>, <code>pushpa</code>, <code>avengers</code>\n\n";
                    $welcome .= "📢 <b>Join our channels:</b>\n";
                    
                    foreach ($CONFIG['public_channels'] as $ch) {
                        $welcome .= "• " . $ch['username'] . "\n";
                    }
                    
                    $welcome .= "\n💬 <b>Request movies:</b> " . $CONFIG['request_group']['username'];
                    
                    sendMessage($chat_id, $welcome, null, 'HTML');
                    break;
                
                case '/search':
                case '/s':
                    $query = implode(' ', $params);
                    if (empty($query)) {
                        sendWithTyping($chat_id, "❌ Usage: /search [movie name]\nExample: /search kgf");
                    } else {
                        sendWithTyping($chat_id, "🔍 Searching for: " . htmlspecialchars($query));
                        simple_search($chat_id, $query);
                    }
                    break;
                
                case '/request':
                case '/req':
                    $movie = implode(' ', $params);
                    if (empty($movie)) {
                        sendWithTyping($chat_id, "❌ Usage: /request [movie name]\nExample: /request avatar");
                        break;
                    }
                    
                    $existing = getUserRequests($user_id);
                    $already = false;
                    foreach ($existing as $req) {
                        if (strtolower($req['movie']) == strtolower($movie)) {
                            $already = true;
                            break;
                        }
                    }
                    
                    if ($already) {
                        sendWithTyping($chat_id, "⚠️ You already requested this movie!\nUse /myrequests to check status.");
                    } else {
                        $request_id = addMovieRequest($user_id, $username, $movie);
                        if ($request_id) {
                            sendWithTyping($chat_id, "✅ Request submitted successfully!\n• Movie: " . htmlspecialchars($movie) . "\n• Request ID: <code>" . $request_id . "</code>\n• Status: Pending\n\nUse /myrequests to track your request.", null, 'HTML');
                        } else {
                            sendWithTyping($chat_id, "❌ Failed to submit request. Please try again.");
                        }
                    }
                    break;
                
                case '/myrequests':
                    $user_reqs = getUserRequests($user_id);
                    
                    if (empty($user_reqs)) {
                        $requests = loadRequests();
                        $approved = [];
                        foreach ($requests['approved'] as $req) {
                            if ($req['user_id'] == $user_id) {
                                $approved[] = $req;
                            }
                        }
                        
                        if (empty($approved)) {
                            sendWithTyping($chat_id, "📭 You have no requests.\nUse /request [movie] to request a movie!");
                        } else {
                            $msg = "✅ <b>Your Approved Requests:</b>\n\n";
                            foreach ($approved as $req) {
                                $msg .= "🎬 " . htmlspecialchars($req['movie']) . "\n";
                                $msg .= "📅 " . $req['time'] . "\n━━━━━━━━━━━\n";
                            }
                            sendWithTyping($chat_id, $msg, null, 'HTML');
                        }
                    } else {
                        $msg = "⏳ <b>Your Pending Requests (" . count($user_reqs) . ")</b>\n\n";
                        foreach ($user_reqs as $req) {
                            $msg .= "🎬 <b>" . htmlspecialchars($req['movie']) . "</b>\n";
                            $msg .= "🆔 <code>" . $req['id'] . "</code>\n";
                            $msg .= "📅 " . $req['time'] . "\n━━━━━━━━━━━\n";
                        }
                        sendWithTyping($chat_id, $msg, null, 'HTML');
                    }
                    break;
                
                case '/admin':
                case '/panel':
                    showAdminPanel($chat_id, $user_id);
                    break;
                
                case '/pending':
                case '/pending_request':
                    if ($user_id != ADMIN_ID) {
                        sendMessage($chat_id, "❌ Unauthorized! Admin only command.");
                        break;
                    }
                    
                    $pending = getAllPendingRequests();
                    if (empty($pending)) {
                        sendWithTyping($chat_id, "📭 No pending requests. Everyone is happy! 🎉");
                    } else {
                        $msg = "⏳ <b>Pending Requests (" . count($pending) . ")</b>\n\n";
                        $keyboard = ['inline_keyboard' => []];
                        
                        foreach ($pending as $index => $req) {
                            $msg .= "<b>" . ($index + 1) . ". " . htmlspecialchars($req['movie']) . "</b>\n";
                            $msg .= "👤 @" . htmlspecialchars($req['username'] ?: 'unknown') . "\n";
                            $msg .= "🆔 <code>" . $req['id'] . "</code>\n";
                            $msg .= "📅 " . $req['time'] . "\n\n";
                            
                            $keyboard['inline_keyboard'][] = [
                                ['text' => "✅ Approve", 'callback_data' => 'approve_' . $req['id']]
                            ];
                        }
                        
                        sendMessage($chat_id, $msg, $keyboard, 'HTML');
                    }
                    break;
                
                case '/approve':
                    if ($user_id != ADMIN_ID) {
                        sendMessage($chat_id, "❌ Unauthorized! Admin only command.");
                        break;
                    }
                    
                    $request_id = $params[0] ?? '';
                    if (empty($request_id)) {
                        sendWithTyping($chat_id, "❌ Usage: /approve [request_id]\nExample: /approve req_123abc");
                    } else if (approveRequest($request_id)) {
                        sendWithTyping($chat_id, "✅ Request approved successfully!");
                    } else {
                        sendWithTyping($chat_id, "❌ Request not found");
                    }
                    break;
                
                case '/bulk_approve':
                    if ($user_id != ADMIN_ID) {
                        sendMessage($chat_id, "❌ Unauthorized! Admin only command.");
                        break;
                    }
                    
                    $count = isset($params[0]) ? intval($params[0]) : 0;
                    $pending = getAllPendingRequests();
                    
                    if ($count <= 0) {
                        sendWithTyping($chat_id, "❌ Usage: /bulk_approve [number]\nExample: /bulk_approve 5");
                        break;
                    }
                    
                    if (empty($pending)) {
                        sendWithTyping($chat_id, "📭 No pending requests to approve.");
                        break;
                    }
                    
                    $to_approve = array_slice($pending, 0, $count);
                    $approved_count = 0;
                    
                    $progress_msg = sendMessage($chat_id, "⏳ Approving " . count($to_approve) . " requests...");
                    
                    foreach ($to_approve as $req) {
                        if (approveRequest($req['id'])) {
                            $approved_count++;
                        }
                        usleep(300000);
                    }
                    
                    deleteMessage($chat_id, $progress_msg['result']['message_id']);
                    
                    $result_msg = "✅ <b>Bulk Approve Complete!</b>\n\n";
                    $result_msg .= "• Total: " . count($to_approve) . "\n";
                    $result_msg .= "• ✅ Approved: " . $approved_count . "\n";
                    
                    sendMessage($chat_id, $result_msg, null, 'HTML');
                    break;
                
                case '/total_upload':
                case '/total_movies':
                    if ($user_id != ADMIN_ID) {
                        sendMessage($chat_id, "❌ Unauthorized! Admin only command.");
                        break;
                    }
                    
                    $movies = getAllMovies();
                    $unique_movies = [];
                    foreach ($movies as $movie) {
                        $unique_movies[$movie['name']] = true;
                    }
                    
                    $stats = [
                        'total_files' => count($movies),
                        'unique_movies' => count($unique_movies),
                        'channels' => []
                    ];
                    
                    foreach ($movies as $movie) {
                        $channel = $movie['channel_name'] ?? 'Unknown';
                        if (!isset($stats['channels'][$channel])) {
                            $stats['channels'][$channel] = 0;
                        }
                        $stats['channels'][$channel]++;
                    }
                    
                    $msg = "📊 <b>Total Upload Statistics</b>\n\n";
                    $msg .= "🎬 Total Files: <b>" . $stats['total_files'] . "</b>\n";
                    $msg .= "🎯 Unique Movies: <b>" . $stats['unique_movies'] . "</b>\n\n";
                    $msg .= "📢 <b>Channel-wise Breakdown:</b>\n";
                    
                    foreach ($stats['channels'] as $channel => $count) {
                        $msg .= "• " . $channel . ": " . $count . " files\n";
                    }
                    
                    $recent = array_slice($movies, -5);
                    $msg .= "\n🆕 <b>Recent Additions:</b>\n";
                    foreach ($recent as $movie) {
                        $msg .= "• " . htmlspecialchars($movie['name']) . "\n";
                    }
                    
                    sendMessage($chat_id, $msg, null, 'HTML');
                    break;
                
                case '/help':
                    $help = "🤖 <b>Entertainment Tadka Bot</b>\n\n";
                    $help .= "<b>Commands:</b>\n";
                    $help .= "• /start - Welcome message\n";
                    $help .= "• /search [movie] - Search movies\n";
                    $help .= "• /request [movie] - Request a movie\n";
                    $help .= "• /myrequests - Your pending requests\n";
                    $help .= "• /help - This message\n\n";
                    $help .= "📢 <b>Join:</b> @EntertainmentTadka786\n";
                    $help .= "💬 <b>Request:</b> @EntertainmentTadka7860";
                    
                    sendMessage($chat_id, $help, null, 'HTML');
                    break;
                
                default:
                    break;
            }
        } else if (!empty($text)) {
            if ($chat_type !== 'private' && strlen($text) < 3) {
                return;
            }
            
            sendWithTyping($chat_id, "🔍 Searching for: " . htmlspecialchars($text));
            simple_search($chat_id, $text);
        }
    }
    
    // ==============================
    // CALLBACK QUERY HANDLING
    // ==============================
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $chat_id = $query['message']['chat']['id'];
        $message_id = $query['message']['message_id'];
        $data = $query['data'];
        
        if (strpos($data, 'admin_') === 0) {
            handleAdminCallback($data, $chat_id, $message_id, $query['id']);
        }
        elseif (strpos($data, 'get_') === 0) {
            $movie_name = substr($data, 4);
            
            deleteMessage($chat_id, $message_id);
            sendWithTyping($chat_id, "🎬 Sending: " . htmlspecialchars($movie_name));
            
            $files = getMovieFiles($movie_name);
            
            if (!empty($files)) {
                $sent = 0;
                $total = count($files);
                
                $progress_msg = sendMessage($chat_id, "⏳ Sending $total files...");
                
                foreach ($files as $index => $file) {
                    if (deliverMovie($chat_id, $file['message_id'], $file['channel_id'])) {
                        $sent++;
                        
                        if (($index + 1) % 3 == 0 || $index + 1 == $total) {
                            editMessageText($chat_id, $progress_msg['result']['message_id'], 
                                "⏳ Sent $sent of $total files...");
                        }
                        
                        usleep(500000);
                    }
                }
                
                deleteMessage($chat_id, $progress_msg['result']['message_id']);
                sendMessage($chat_id, "✅ Sent $sent of $total files for: " . htmlspecialchars($movie_name));
            } else {
                sendMessage($chat_id, "❌ No files found for: " . htmlspecialchars($movie_name));
            }
            
            answerCallbackQuery($query['id'], "Sending...");
        }
        elseif (strpos($data, 'approve_') === 0) {
            $request_id = substr($data, 8);
            
            if (approveRequest($request_id)) {
                editMessageText($chat_id, $message_id, "✅ Request approved successfully!", null, 'HTML');
                answerCallbackQuery($query['id'], "Request approved!");
            } else {
                answerCallbackQuery($query['id'], "Failed to approve", true);
            }
        }
        elseif ($data == 'approve_all') {
            $pending = getAllPendingRequests();
            $count = count($pending);
            
            if ($count == 0) {
                answerCallbackQuery($query['id'], "No pending requests", true);
            } else {
                editMessageText($chat_id, $message_id, "⏳ Approving all $count requests...", null, 'HTML');
                
                $approved = 0;
                foreach ($pending as $req) {
                    if (approveRequest($req['id'])) {
                        $approved++;
                    }
                    usleep(200000);
                }
                
                editMessageText($chat_id, $message_id, "✅ Approved $approved of $count requests!", null, 'HTML');
                answerCallbackQuery($query['id'], "Bulk approve complete!");
            }
        }
        elseif (strpos($data, 'bulk_approve_') === 0) {
            $count = intval(substr($data, 13));
            $pending = getAllPendingRequests();
            $to_approve = array_slice($pending, 0, $count);
            
            editMessageText($chat_id, $message_id, "⏳ Approving $count requests...", null, 'HTML');
            
            $approved = 0;
            foreach ($to_approve as $req) {
                if (approveRequest($req['id'])) {
                    $approved++;
                }
                usleep(200000);
            }
            
            editMessageText($chat_id, $message_id, "✅ Approved $approved of $count requests!", null, 'HTML');
            answerCallbackQuery($query['id'], "Bulk approve complete!");
        }
    }
}

// ==============================
// DEPLOY NOTIFICATION CHECK
// ==============================
if (!file_exists(DEPLOY_FLAG)) {
    sendDeployNotification();
    file_put_contents(DEPLOY_FLAG, date('Y-m-d H:i:s'));
}

// ==============================
// HEALTH CHECK (WEB VIEW)
// ==============================
if (!isset($update) || !$update) {
    $stats = getStats();
    $movies = getAllMovies();
    $pending = getAllPendingRequests();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Entertainment Tadka Bot</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                min-height: 100vh;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
                padding: 30px;
                border-radius: 20px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            }
            h1 {
                color: #ffd700;
                font-size: 2.5em;
                margin-bottom: 20px;
                text-align: center;
            }
            .status-badge {
                background: #00ff00;
                color: #000;
                padding: 8px 16px;
                border-radius: 50px;
                display: inline-block;
                font-weight: bold;
                margin-bottom: 20px;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            .stat-card {
                background: rgba(255, 255, 255, 0.15);
                padding: 20px;
                border-radius: 10px;
                text-align: center;
            }
            .stat-value {
                font-size: 2em;
                font-weight: bold;
                color: #ffd700;
            }
            .stat-label {
                margin-top: 10px;
                font-size: 0.9em;
                opacity: 0.9;
            }
            .channels-list {
                background: rgba(0, 0, 0, 0.2);
                padding: 20px;
                border-radius: 10px;
                margin: 20px 0;
            }
            .channel-item {
                padding: 10px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }
            .channel-item:last-child {
                border-bottom: none;
            }
            .footer {
                text-align: center;
                margin-top: 30px;
                opacity: 0.7;
                font-size: 0.9em;
            }
            a {
                color: #ffd700;
                text-decoration: none;
            }
            a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🎬 <?php echo htmlspecialchars($CONFIG['bot_username']); ?></h1>
            
            <div style="text-align: center;">
                <span class="status-badge">✅ ONLINE</span>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($movies); ?></div>
                    <div class="stat-label">Total Files</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['unique_movies'] ?? 0; ?></div>
                    <div class="stat-label">Unique Movies</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($pending); ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>
            </div>
            
            <div class="channels-list">
                <h3 style="margin-bottom: 15px; color: #ffd700;">📢 Public Channels</h3>
                <?php foreach ($CONFIG['public_channels'] as $ch): ?>
                <div class="channel-item">
                    <strong><?php echo htmlspecialchars($ch['name']); ?>:</strong> 
                    <a href="https://t.me/<?php echo substr($ch['username'], 1); ?>" target="_blank">
                        <?php echo htmlspecialchars($ch['username']); ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="channels-list">
                <h3 style="margin-bottom: 15px; color: #ffd700;">💬 Request Group</h3>
                <div class="channel-item">
                    <a href="https://t.me/<?php echo substr($CONFIG['request_group']['username'], 1); ?>" target="_blank">
                        <?php echo htmlspecialchars($CONFIG['request_group']['username']); ?>
                    </a>
                </div>
            </div>
            
            <div class="channels-list">
                <h3 style="margin-bottom: 15px; color: #ffd700;">🤖 Bot Info</h3>
                <div class="channel-item">
                    <strong>Admin ID:</strong> <?php echo ADMIN_ID; ?>
                </div>
                <div class="channel-item">
                    <strong>Last Deploy:</strong> <?php echo file_exists(DEPLOY_FLAG) ? file_get_contents(DEPLOY_FLAG) : 'Never'; ?>
                </div>
                <div class="channel-item">
                    <strong>PHP Version:</strong> <?php echo phpversion(); ?>
                </div>
            </div>
            
            <div class="footer">
                <p>Entertainment Tadka Bot © 2025 | Made with ❤️ for Movie Lovers</p>
                <p style="margin-top: 10px;">
                    <a href="https://t.me/EntertainmentTadka786" target="_blank">Main Channel</a> | 
                    <a href="https://t.me/EntertainmentTadka7860" target="_blank">Request Group</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
