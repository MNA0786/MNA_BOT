<?php
// cron_delete_once.php - Har minute run hoga aur pending deletions handle karega

require_once __DIR__ . '/index.php';

// Database aur botAPI initialize karo
$db = new Database();
$bot = new BotAPI(BOT_TOKEN);

$now = time();

// 1. Jo deletions abhi due hain unhe delete karo
$due = $db->getPendingDeletions(50); // max 50 ek baar mein
foreach ($due as $del) {
    // Original message delete karo
    $bot->deleteMessage($del['chat_id'], $del['message_id']);
    
    // Agar progress message hai to use bhi delete karo
    if ($del['progress_message_id']) {
        $bot->deleteMessage($del['chat_id'], $del['progress_message_id']);
    }
    
    // Database mein status 'deleted' mark karo (ya record hata do)
    $db->updateScheduledDeletion($del['id'], ['status' => 'deleted']);
    
    Logger::info("Cron: Deleted message {$del['message_id']} in chat {$del['chat_id']}");
}

// 2. Un deletions ke liye jo abhi pending hain aur next 5 minute mein delete hone wali hain,
//    unke progress messages update karo (agar koi change ho to)
$upcoming = $db->getUpcomingDeletions(300); // 5 minute window
foreach ($upcoming as $del) {
    if ($del['progress_message_id'] && $del['status'] == 'pending') {
        $remaining = $del['delete_at'] - $now;
        if ($remaining > 0) {
            $total = FILE_DELETE_TIME; // original time
            $progress_text = "⚠️ File Delete in {$remaining}s\n" . Utils::getProgressBar($remaining, $total);
            $bot->editMessageText($del['chat_id'], $del['progress_message_id'], $progress_text);
            $db->updateScheduledDeletion($del['id'], ['last_edit_at' => $now]);
        }
    }
}

// Koi infinite loop nahi, script yahan khatam ho jayegi
?>
