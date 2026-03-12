<?php
// cron_delete.php - Run this script every 10 seconds to handle auto-deletion and progress updates
// Usage: nohup php cron_delete.php > /dev/null 2>&1 &

require_once __DIR__ . '/index1.9(📥 File Delivery System).php'; // This will load all classes and constants

// We need a separate database connection because the main file expects webhook
$db = new Database();
$bot = new BotAPI(BOT_TOKEN); // For deleting messages

// How often to check (seconds)
$check_interval = 10;

while (true) {
    $now = time();
    
    // Get deletions that are due now
    $due = $db->getPendingDeletions(50);
    foreach ($due as $del) {
        // Delete the message
        $bot->deleteMessage($del['chat_id'], $del['message_id']);
        // If there's a progress message, delete it too (it will be in another record or same)
        if ($del['progress_message_id']) {
            $bot->deleteMessage($del['chat_id'], $del['progress_message_id']);
        }
        // Mark as deleted
        $db->updateScheduledDeletion($del['id'], ['status' => 'deleted']);
        Logger::info("Deleted message {$del['message_id']} in chat {$del['chat_id']}");
    }
    
    // Get upcoming deletions within next 5 minutes to update progress bars
    $upcoming = $db->getUpcomingDeletions(300);
    foreach ($upcoming as $del) {
        if ($del['progress_message_id'] && $del['status'] == 'pending') {
            $remaining = $del['delete_at'] - $now;
            if ($remaining > 0) {
                $total = FILE_DELETE_TIME; // We don't store original total, assume it's FILE_DELETE_TIME
                $progress_text = "⚠️ File Delete in {$remaining}s\n" . Utils::getProgressBar($remaining, $total);
                $bot->editMessageText($del['chat_id'], $del['progress_message_id'], $progress_text);
                $db->updateScheduledDeletion($del['id'], ['last_edit_at' => $now]);
            }
        }
    }
    
    sleep($check_interval);
}
