<?php
// ==============================
// SIMPLE MOVIE FORWARD BOT v1.0
// ==============================

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==============================
// CONFIGURATION
// ==============================
$config = [
    // Bot Token (Get from @BotFather)
    'BOT_TOKEN' => getenv('BOT_TOKEN') ?: 'YOUR_BOT_TOKEN_HERE',
    
    // Your Telegram User ID (Admin)
    'ADMIN_ID' => getenv('ADMIN_ID') ?: '123456789',
    
    // Source Channels (5 channels)
    'SOURCE_CHANNELS' => [
        [
            'id' => getenv('CHANNEL_1_ID') ?: '-1001234567890',
            'name' => '🎬 Main Movies',
            'username' => '@MovieChannel1'
        ],
        [
            'id' => getenv('CHANNEL_2_ID') ?: '-1001234567891',
            'name' => '🍿 Hollywood',
            'username' => '@HollywoodMovies'
        ],
        [
            'id' => getenv('CHANNEL_3_ID') ?: '-1001234567892',
            'name' => '🇮🇳 Bollywood',
            'username' => '@BollywoodMovies'
        ],
        [
            'id' => getenv('CHANNEL_4_ID') ?: '-1001234567893',
            'name' => '📺 Web Series',
            'username' => '@WebSeriesHub'
        ],
        [
            'id' => getenv('CHANNEL_5_ID') ?: '-1001234567894',
            'name' => '🎭 Theater Prints',
            'username' => '@TheaterPrints'
        ]
    ],
    
    // Request Channel (Where users send requests)
    'REQUEST_CHANNEL_ID' => getenv('REQUEST_CHANNEL_ID') ?: '-1001234567895',
    'REQUEST_CHANNEL_USERNAME' => getenv('REQUEST_CHANNEL_USERNAME') ?: '@MovieRequests',
    
    // Daily request limit per user
    'DAILY_REQUEST_LIMIT' => 3,
    
    // Files
    'MOVIES_FILE' => __DIR__ . '/movies.json',
    'USERS_FILE' => __DIR__ . '/users.json',
    'REQUESTS_FILE' => __DIR__ . '/requests.json'
];

// ==============================
// HELPER FUNCTIONS
// ==============================
function botLog($message) {
    $log = date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
    file_put_contents(__DIR__ . '/bot.log', $log, FILE_APPEND);
    echo $log; // For Render.com logs
}

function callApi($method, $params = []) {
    global $config;
    $url = "https://api.telegram.org/bot{$config['BOT_TOKEN']}/{$method}";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: multipart/form-data']
    ]);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($result, true);
}

function sendMessage($chat_id, $text, $reply_markup = null) {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    if ($reply_markup) {
        $params['reply_markup'] = json_encode($reply_markup);
    }
    
    return callApi('sendMessage', $params);
}

function forwardMessage($to_chat_id, $from_chat_id, $message_id) {
    return callApi('forwardMessage', [
        'chat_id' => $to_chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

function copyMessage($to_chat_id, $from_chat_id, $message_id) {
    return callApi('copyMessage', [
        'chat_id' => $to_chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

function answerCallback($callback_id, $text = null, $show_alert = false) {
    $params = ['callback_query_id' => $callback_id];
    if ($text) $params['text'] = $text;
    if ($show_alert) $params['show_alert'] = $show_alert;
    return callApi('answerCallbackQuery', $params);
}

// ==============================
// MOVIE DATABASE
// ==============================
class MovieDB {
    private static $movies = [];
    
    public static function load() {
        global $config;
        if (file_exists($config['MOVIES_FILE'])) {
            self::$movies = json_decode(file_get_contents($config['MOVIES_FILE']), true) ?: [];
        }
        return self::$movies;
    }
    
    public static function save() {
        global $config;
        file_put_contents($config['MOVIES_FILE'], json_encode(self::$movies, JSON_PRETTY_PRINT));
    }
    
    public static function addMovie($name, $message_id, $channel_id, $channel_name) {
        self::load();
        
        $movie_key = strtolower(trim($name));
        
        if (!isset(self::$movies[$movie_key])) {
            self::$movies[$movie_key] = [];
        }
        
        self::$movies[$movie_key][] = [
            'name' => $name,
            'message_id' => $message_id,
            'channel_id' => $channel_id,
            'channel_name' => $channel_name,
            'added_at' => date('Y-m-d H:i:s')
        ];
        
        self::save();
        botLog("Movie added: {$name} from {$channel_name}");
        
        return true;
    }
    
    public static function searchMovies($query) {
        self::load();
        $query = strtolower(trim($query));
        
        if (strlen($query) < 2) {
            return [];
        }
        
        $results = [];
        
        foreach (self::$movies as $movie_key => $versions) {
            if (strpos($movie_key, $query) !== false) {
                $results = array_merge($results, $versions);
            }
        }
        
        // If no direct match, try partial word search
        if (empty($results)) {
            $words = explode(' ', $query);
            foreach (self::$movies as $movie_key => $versions) {
                foreach ($words as $word) {
                    if (strlen($word) >= 3 && strpos($movie_key, $word) !== false) {
                        $results = array_merge($results, $versions);
                        break;
                    }
                }
            }
        }
        
        return $results;
    }
    
    public static function getStats() {
        self::load();
        $stats = [
            'total_movies' => 0,
            'unique_movies' => 0,
            'by_channel' => []
        ];
        
        foreach (self::$movies as $movie_key => $versions) {
            $stats['unique_movies']++;
            $stats['total_movies'] += count($versions);
            
            foreach ($versions as $movie) {
                $channel = $movie['channel_name'];
                if (!isset($stats['by_channel'][$channel])) {
                    $stats['by_channel'][$channel] = 0;
                }
                $stats['by_channel'][$channel]++;
            }
        }
        
        return $stats;
    }
}

// ==============================
// USER MANAGEMENT
// ==============================
class UserManager {
    public static function getUser($user_id) {
        global $config;
        
        $users = [];
        if (file_exists($config['USERS_FILE'])) {
            $users = json_decode(file_get_contents($config['USERS_FILE']), true) ?: [];
        }
        
        if (!isset($users[$user_id])) {
            $users[$user_id] = [
                'id' => $user_id,
                'requests_today' => 0,
                'last_request_date' => date('Y-m-d'),
                'total_requests' => 0,
                'joined_at' => date('Y-m-d H:i:s')
            ];
            file_put_contents($config['USERS_FILE'], json_encode($users, JSON_PRETTY_PRINT));
        }
        
        return $users[$user_id];
    }
    
    public static function canRequest($user_id) {
        global $config;
        $user = self::getUser($user_id);
        
        $today = date('Y-m-d');
        if ($user['last_request_date'] != $today) {
            return true; // New day
        }
        
        return $user['requests_today'] < $config['DAILY_REQUEST_LIMIT'];
    }
    
    public static function recordRequest($user_id) {
        global $config;
        $users = [];
        
        if (file_exists($config['USERS_FILE'])) {
            $users = json_decode(file_get_contents($config['USERS_FILE']), true) ?: [];
        }
        
        $today = date('Y-m-d');
        
        if (!isset($users[$user_id])) {
            $users[$user_id] = [
                'id' => $user_id,
                'requests_today' => 1,
                'last_request_date' => $today,
                'total_requests' => 1,
                'joined_at' => date('Y-m-d H:i:s')
            ];
        } else {
            if ($users[$user_id]['last_request_date'] != $today) {
                $users[$user_id]['requests_today'] = 1;
                $users[$user_id]['last_request_date'] = $today;
            } else {
                $users[$user_id]['requests_today']++;
            }
            $users[$user_id]['total_requests']++;
        }
        
        file_put_contents($config['USERS_FILE'], json_encode($users, JSON_PRETTY_PRINT));
        return true;
    }
    
    public static function getRemainingRequests($user_id) {
        global $config;
        $user = self::getUser($user_id);
        
        $today = date('Y-m-d');
        if ($user['last_request_date'] != $today) {
            return $config['DAILY_REQUEST_LIMIT'];
        }
        
        return $config['DAILY_REQUEST_LIMIT'] - $user['requests_today'];
    }
}

// ==============================
// REQUEST SYSTEM
// ==============================
class RequestSystem {
    public static function saveRequest($user_id, $movie_name, $user_name = '') {
        global $config;
        
        $requests = [];
        if (file_exists($config['REQUESTS_FILE'])) {
            $requests = json_decode(file_get_contents($config['REQUESTS_FILE']), true) ?: [];
        }
        
        $request_id = uniqid();
        $requests[] = [
            'id' => $request_id,
            'user_id' => $user_id,
            'user_name' => $user_name,
            'movie_name' => $movie_name,
            'status' => 'pending',
            'date' => date('Y-m-d H:i:s')
        ];
        
        // Keep only last 1000 requests
        if (count($requests) > 1000) {
            $requests = array_slice($requests, -1000);
        }
        
        file_put_contents($config['REQUESTS_FILE'], json_encode($requests, JSON_PRETTY_PRINT));
        
        // Notify admin
        $admin_msg = "🎬 <b>New Movie Request</b>\n\n";
        $admin_msg .= "📝 Movie: <code>" . htmlspecialchars($movie_name) . "</code>\n";
        $admin_msg .= "👤 User: " . ($user_name ?: "ID: {$user_id}") . "\n";
        $admin_msg .= "🆔 Request ID: <code>{$request_id}</code>\n";
        $admin_msg .= "📅 Date: " . date('Y-m-d H:i:s');
        
        sendMessage($config['ADMIN_ID'], $admin_msg);
        
        // Forward to request channel
        $channel_msg = "🎯 New Request:\n";
        $channel_msg .= "Movie: {$movie_name}\n";
        $channel_msg .= "From: " . ($user_name ?: "User ID: {$user_id}");
        
        sendMessage($config['REQUEST_CHANNEL_ID'], $channel_msg);
        
        return $request_id;
    }
}

// ==============================
// MAIN BOT HANDLER
// ==============================
class SimpleMovieBot {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        
        // Create files if not exist
        $files = [$config['MOVIES_FILE'], $config['USERS_FILE'], $config['REQUESTS_FILE']];
        foreach ($files as $file) {
            if (!file_exists($file)) {
                file_put_contents($file, json_encode([]));
            }
        }
    }
    
    public function handleUpdate($update) {
        // Handle message
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
        
        // Handle callback queries
        elseif (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
        }
    }
    
    private function handleMessage($message) {
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = $message['text'] ?? '';
        $chat_type = $message['chat']['type'];
        
        // Get user info
        $user_name = $message['from']['first_name'] ?? '';
        if (isset($message['from']['last_name'])) {
            $user_name .= ' ' . $message['from']['last_name'];
        }
        $username = $message['from']['username'] ?? '';
        
        // Handle commands
        if (strpos($text, '/') === 0) {
            $this->handleCommand($text, $chat_id, $user_id, $user_name, $username);
            return;
        }
        
        // Handle regular text (movie search)
        if (!empty(trim($text))) {
            $this->handleSearch($text, $chat_id, $user_id);
            return;
        }
        
        // Handle forwarded messages from channels (auto-add to database)
        if (isset($message['forward_from_chat']) && 
            in_array($message['forward_from_chat']['id'], array_column($this->config['SOURCE_CHANNELS'], 'id'))) {
            
            $channel_id = $message['forward_from_chat']['id'];
            $channel_info = $this->getChannelInfo($channel_id);
            
            $movie_name = $this->extractMovieName($message);
            
            if ($movie_name) {
                MovieDB::addMovie($movie_name, $message['message_id'], $channel_id, $channel_info['name']);
                
                // Optional: Send confirmation to admin
                // sendMessage($this->config['ADMIN_ID'], "✅ Movie added: {$movie_name} from {$channel_info['name']}");
            }
        }
    }
    
    private function handleCommand($text, $chat_id, $user_id, $user_name, $username) {
        $parts = explode(' ', $text, 2);
        $command = strtolower(substr($parts[0], 1));
        $params = isset($parts[1]) ? $parts[1] : '';
        
        switch ($command) {
            case 'start':
                $this->sendWelcome($chat_id, $user_name);
                break;
                
            case 'help':
                $this->sendHelp($chat_id);
                break;
                
            case 'search':
                if (empty($params)) {
                    sendMessage($chat_id, "❌ Please specify movie name.\nExample: <code>/search avengers</code>");
                } else {
                    $this->handleSearch($params, $chat_id, $user_id);
                }
                break;
                
            case 'request':
                if (empty($params)) {
                    sendMessage($chat_id, "❌ Please specify movie name.\nExample: <code>/request Avengers Endgame</code>");
                } else {
                    $this->handleRequest($params, $chat_id, $user_id, $user_name);
                }
                break;
                
            case 'channels':
                $this->sendChannelList($chat_id);
                break;
                
            case 'stats':
                $this->sendStats($chat_id, $user_id);
                break;
                
            case 'myrequests':
                $this->sendMyRequests($chat_id, $user_id);
                break;
                
            default:
                sendMessage($chat_id, "❌ Unknown command. Use /help for available commands.");
        }
    }
    
    private function handleSearch($query, $chat_id, $user_id) {
        botLog("Search from {$user_id}: {$query}");
        
        sendMessage($chat_id, "🔍 Searching for <b>" . htmlspecialchars($query) . "</b>...");
        
        $results = MovieDB::searchMovies($query);
        
        if (empty($results)) {
            $keyboard = [
                'inline_keyboard' => [[
                    'text' => '📝 Request This Movie',
                    'callback_data' => 'request_' . urlencode($query)
                ]]
            ];
            
            sendMessage($chat_id, 
                "❌ No movies found for <b>" . htmlspecialchars($query) . "</b>\n\n" .
                "💡 Click below to request this movie",
                $keyboard
            );
            return;
        }
        
        // Group by movie name
        $grouped = [];
        foreach ($results as $movie) {
            $name = $movie['name'];
            if (!isset($grouped[$name])) {
                $grouped[$name] = [];
            }
            $grouped[$name][] = $movie;
        }
        
        // Show top 5 results
        $response = "✅ Found " . count($results) . " matches\n";
        $response .= "🎬 Showing top " . min(5, count($grouped)) . " movies:\n\n";
        
        $counter = 1;
        $keyboard = ['inline_keyboard' => []];
        
        foreach ($grouped as $movie_name => $versions) {
            if ($counter > 5) break;
            
            $response .= "{$counter}. <b>" . htmlspecialchars($movie_name) . "</b>\n";
            $response .= "   📁 Versions: " . count($versions) . "\n";
            $response .= "   📍 Channels: " . implode(', ', array_unique(array_column($versions, 'channel_name'))) . "\n\n";
            
            $keyboard['inline_keyboard'][] = [[
                'text' => "🎬 " . (strlen($movie_name) > 30 ? substr($movie_name, 0, 30) . "..." : $movie_name),
                'callback_data' => 'movie_' . urlencode($movie_name)
            ]];
            
            $counter++;
        }
        
        $keyboard['inline_keyboard'][] = [[
            'text' => '📝 Request New Movie',
            'callback_data' => 'request_new'
        ]];
        
        sendMessage($chat_id, $response, $keyboard);
    }
    
    private function handleRequest($movie_name, $chat_id, $user_id, $user_name) {
        // Check daily limit
        if (!UserManager::canRequest($user_id)) {
            $remaining = UserManager::getRemainingRequests($user_id);
            $reset_time = "tomorrow";
            
            sendMessage($chat_id, 
                "❌ Daily request limit reached!\n\n" .
                "📊 Limit: {$this->config['DAILY_REQUEST_LIMIT']} requests/day\n" .
                "⏰ Resets: {$reset_time}\n\n" .
                "Please try again tomorrow."
            );
            return;
        }
        
        // Save request
        $request_id = RequestSystem::saveRequest($user_id, $movie_name, $user_name);
        
        // Record user request
        UserManager::recordRequest($user_id);
        
        // Send confirmation
        $response = "✅ <b>Request Submitted Successfully!</b>\n\n";
        $response .= "🎬 Movie: <code>" . htmlspecialchars($movie_name) . "</code>\n";
        $response .= "🆔 Request ID: <code>{$request_id}</code>\n";
        $response .= "📅 Date: " . date('Y-m-d H:i:s') . "\n\n";
        $response .= "We'll add this movie within 24 hours!\n";
        $response .= "You'll get notified when it's available.\n\n";
        
        $remaining = UserManager::getRemainingRequests($user_id);
        $response .= "📊 Requests left today: {$remaining}/{$this->config['DAILY_REQUEST_LIMIT']}";
        
        sendMessage($chat_id, $response);
    }
    
    private function handleCallback($callback) {
        $chat_id = $callback['message']['chat']['id'];
        $user_id = $callback['from']['id'];
        $data = $callback['data'];
        $callback_id = $callback['id'];
        
        // Answer callback immediately
        answerCallback($callback_id);
        
        if (strpos($data, 'movie_') === 0) {
            $movie_name = urldecode(substr($data, 6));
            $this->showMovieVersions($movie_name, $chat_id);
        }
        elseif (strpos($data, 'request_') === 0) {
            $query = urldecode(substr($data, 8));
            $this->handleRequest($query, $chat_id, $user_id, $callback['from']['first_name'] ?? '');
        }
        elseif ($data == 'request_new') {
            sendMessage($chat_id, 
                "📝 To request a movie:\n" .
                "Use: <code>/request Movie Name</code>\n\n" .
                "Example: <code>/request Avengers Endgame 2024</code>"
            );
        }
        elseif (strpos($data, 'sendmovie_') === 0) {
            $parts = explode('_', $data);
            if (count($parts) >= 4) {
                $message_id = $parts[1];
                $channel_id = $parts[2];
                $this->sendMovieToUser($chat_id, $channel_id, $message_id);
            }
        }
    }
    
    private function showMovieVersions($movie_name, $chat_id) {
        $results = MovieDB::searchMovies($movie_name);
        
        if (empty($results)) {
            sendMessage($chat_id, "❌ Movie not found in database.");
            return;
        }
        
        $response = "🎬 <b>" . htmlspecialchars($movie_name) . "</b>\n\n";
        $response .= "📁 Available versions:\n\n";
        
        $keyboard = ['inline_keyboard' => []];
        
        foreach ($results as $index => $movie) {
            $response .= ($index + 1) . ". <b>{$movie['channel_name']}</b>\n";
            $response .= "   📅 Added: " . date('d M Y', strtotime($movie['added_at'])) . "\n\n";
            
            $keyboard['inline_keyboard'][] = [[
                'text' => "📥 Download from {$movie['channel_name']}",
                'callback_data' => "sendmovie_{$movie['message_id']}_{$movie['channel_id']}"
            ]];
        }
        
        sendMessage($chat_id, $response, $keyboard);
    }
    
    private function sendMovieToUser($chat_id, $channel_id, $message_id) {
        // Try forward first
        $result = forwardMessage($chat_id, $channel_id, $message_id);
        
        if (!$result || !isset($result['ok']) || !$result['ok']) {
            // Fallback to copy
            $result = copyMessage($chat_id, $channel_id, $message_id);
        }
        
        if ($result && isset($result['ok']) && $result['ok']) {
            sendMessage($chat_id, 
                "✅ Movie sent successfully!\n\n" .
                "💡 Search for more movies or request new ones."
            );
        } else {
            sendMessage($chat_id, 
                "❌ Could not send movie.\n" .
                "Please visit the channel directly."
            );
        }
    }
    
    private function sendWelcome($chat_id, $user_name) {
        $welcome = "🎬 <b>Welcome";
        if ($user_name) {
            $welcome .= ", " . htmlspecialchars($user_name);
        }
        $welcome .= "!</b>\n\n";
        
        $welcome .= "🍿 <b>Movie Forward Bot</b>\n\n";
        $welcome .= "📌 <b>Features:</b>\n";
        $welcome .= "• 🔍 Search movies from 5 channels\n";
        $welcome .= "• 📥 One-click download\n";
        $welcome .= "• 📝 Request missing movies\n\n";
        
        $welcome .= "🚀 <b>Quick Start:</b>\n";
        $welcome .= "1. Type movie name to search\n";
        $welcome .= "2. Click result to download\n";
        $welcome .= "3. Use /request for missing movies\n\n";
        
        $welcome .= "📋 <b>Commands:</b>\n";
        $welcome .= "/search - Search movies\n";
        $welcome .= "/request - Request movie\n";
        $welcome .= "/channels - Channel list\n";
        $welcome .= "/stats - Your statistics\n";
        $welcome .= "/help - Show all commands";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => '']],
                [['text' => '📝 Request Movie', 'callback_data' => 'request_new']],
                [['text' => '📢 Our Channels', 'callback_data' => 'show_channels']]
            ]
        ];
        
        sendMessage($chat_id, $welcome, $keyboard);
    }
    
    private function sendHelp($chat_id) {
        $help = "🤖 <b>Movie Bot Help</b>\n\n";
        
        $help .= "🔍 <b>How to Search:</b>\n";
        $help .= "• Just type movie name\n";
        $help .= "• Use /search command\n";
        $help .= "• Partial names work too\n\n";
        
        $help .= "📝 <b>How to Request:</b>\n";
        $help .= "• Use /request movie_name\n";
        $help .= "• Limit: {$this->config['DAILY_REQUEST_LIMIT']} per day\n";
        $help .= "• We add within 24 hours\n\n";
        
        $help .= "⚡ <b>Quick Commands:</b>\n";
        $help .= "/search <i>movie</i> - Search movie\n";
        $help .= "/request <i>movie</i> - Request movie\n";
        $help .= "/channels - Show channels\n";
        $help .= "/stats - Your stats\n";
        $help .= "/myrequests - Your requests\n";
        $help .= "/help - This message\n\n";
        
        $help .= "📢 <b>Request Channel:</b>\n";
        $help .= $this->config['REQUEST_CHANNEL_USERNAME'];
        
        sendMessage($chat_id, $help);
    }
    
    private function sendChannelList($chat_id) {
        $channels = "📢 <b>Our Movie Channels</b>\n\n";
        
        foreach ($this->config['SOURCE_CHANNELS'] as $channel) {
            $channels .= "{$channel['name']}\n";
            $channels .= "🔗 {$channel['username']}\n\n";
        }
        
        $channels .= "📥 <b>Request Channel:</b>\n";
        $channels .= $this->config['REQUEST_CHANNEL_USERNAME'] . "\n\n";
        
        $channels .= "💡 Join all channels for latest movies!";
        
        $keyboard = ['inline_keyboard' => []];
        foreach ($this->config['SOURCE_CHANNELS'] as $channel) {
            if (strpos($channel['username'], '@') === 0) {
                $url = 'https://t.me/' . substr($channel['username'], 1);
                $keyboard['inline_keyboard'][] = [[
                    'text' => $channel['name'],
                    'url' => $url
                ]];
            }
        }
        
        sendMessage($chat_id, $channels, $keyboard);
    }
    
    private function sendStats($chat_id, $user_id) {
        $user = UserManager::getUser($user_id);
        $stats = MovieDB::getStats();
        
        $response = "📊 <b>Your Statistics</b>\n\n";
        $response .= "👤 User ID: <code>{$user_id}</code>\n";
        $response .= "📅 Joined: " . date('d M Y', strtotime($user['joined_at'])) . "\n\n";
        
        $response .= "🎯 <b>Activity:</b>\n";
        $response .= "• 📝 Requests today: {$user['requests_today']}/{$this->config['DAILY_REQUEST_LIMIT']}\n";
        $response .= "• 📝 Total requests: {$user['total_requests']}\n\n";
        
        $response .= "📈 <b>Database Stats:</b>\n";
        $response .= "• 🎬 Unique movies: " . number_format($stats['unique_movies']) . "\n";
        $response .= "• 📁 Total versions: " . number_format($stats['total_movies']) . "\n\n";
        
        $response .= "🏆 Keep requesting movies!";
        
        sendMessage($chat_id, $response);
    }
    
    private function sendMyRequests($chat_id, $user_id) {
        // This would show user's recent requests
        // For simplicity, just show count
        $user = UserManager::getUser($user_id);
        
        sendMessage($chat_id, 
            "📝 <b>Your Requests</b>\n\n" .
            "📊 Today: {$user['requests_today']}/{$this->config['DAILY_REQUEST_LIMIT']}\n" .
            "📈 Total: {$user['total_requests']}\n\n" .
            "Keep requesting movies you want!"
        );
    }
    
    private function extractMovieName($message) {
        $text = '';
        
        if (isset($message['caption'])) {
            $text = $message['caption'];
        } elseif (isset($message['text'])) {
            $text = $message['text'];
        } elseif (isset($message['document'])) {
            $text = $message['document']['file_name'] ?? '';
        }
        
        if (empty($text)) {
            return 'Media - ' . date('d-m-Y');
        }
        
        // Clean up text
        $patterns = [
            '/🎬\s*/', '/📽️\s*/', '/📁\s*/', '/⬇️\s*/', '/📥\s*/',
            '/✅\s*/', '/【.*?】/', '/\[.*?\]/', '/\(.*?\)/', '/\n.*/'
        ];
        
        $clean = trim($text);
        foreach ($patterns as $pattern) {
            $clean = preg_replace($pattern, '', $clean);
        }
        
        $clean = trim($clean);
        
        return substr($clean, 0, 200);
    }
    
    private function getChannelInfo($channel_id) {
        foreach ($this->config['SOURCE_CHANNELS'] as $channel) {
            if ($channel['id'] == $channel_id) {
                return $channel;
            }
        }
        return ['name' => 'Unknown Channel', 'username' => ''];
    }
}

// ==============================
// WEBHOOK HANDLER
// ==============================
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if ($update) {
    // Initialize bot
    $bot = new SimpleMovieBot($config);
    
    // Process update
    $bot->handleUpdate($update);
    
    // Log successful processing
    botLog("Update processed successfully");
} else {
    // Show status page if accessed via browser
    header('Content-Type: text/html; charset=utf-8');
    
    $stats = MovieDB::getStats();
    $total_users = file_exists($config['USERS_FILE']) ? 
        count(json_decode(file_get_contents($config['USERS_FILE']), true)) : 0;
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>🎬 Movie Forward Bot</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
            .status { background: #4CAF50; color: white; padding: 10px; border-radius: 5px; }
            .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
            .stat-card { background: #f5f5f5; padding: 20px; border-radius: 10px; }
            .btn { display: inline-block; padding: 10px 20px; background: #2196F3; color: white; 
                   text-decoration: none; border-radius: 5px; margin: 5px; }
        </style>
    </head>
    <body>
        <h1>🎬 Movie Forward Bot</h1>
        <div class="status">🟢 Bot is running</div>
        
        <div class="stats">
            <div class="stat-card">
                <h3>🎬 Movies</h3>
                <p>Unique: ' . number_format($stats['unique_movies']) . '</p>
                <p>Total Versions: ' . number_format($stats['total_movies']) . '</p>
            </div>
            <div class="stat-card">
                <h3>👥 Users</h3>
                <p>Total: ' . number_format($total_users) . '</p>
            </div>
            <div class="stat-card">
                <h3>📝 Requests</h3>
                <p>Daily Limit: ' . $config['DAILY_REQUEST_LIMIT'] . '</p>
            </div>
        </div>
        
        <h2>📢 Channels</h2>
        <ul>';
        
    foreach ($config['SOURCE_CHANNELS'] as $channel) {
        echo '<li>' . htmlspecialchars($channel['name']) . ' - ' . htmlspecialchars($channel['username']) . '</li>';
    }
    
    echo '</ul>
        <p><strong>Request Channel:</strong> ' . htmlspecialchars($config['REQUEST_CHANNEL_USERNAME']) . '</p>
        
        <div style="margin-top: 30px;">
            <a href="https://t.me/' . substr($config['SOURCE_CHANNELS'][0]['username'], 1) . '" class="btn" target="_blank">
                🍿 Main Channel
            </a>
            <a href="?action=webhook" class="btn">🔄 Update Webhook</a>
        </div>
    </body>
    </html>';
    
    // Handle webhook update
    if (isset($_GET['action']) && $_GET['action'] == 'webhook') {
        $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                      "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        
        $result = callApi('setWebhook', ['url' => $webhook_url]);
        echo '<script>alert("Webhook updated: ' . ($result['ok'] ? 'Success' : 'Failed') . '");</script>';
    }
}
?>
