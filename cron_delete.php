<?php
// ============================================================
// cron_delete_once.php - Auto-Delete Handler for Telegram Bot
// ============================================================
// This script is meant to be triggered by cron-job.org every 5 minutes.
// It processes pending file deletions and updates progress messages.
// ============================================================

// Prevent direct access if not via cron trigger (optional security)
if (php_sapi_name() !== 'cli' && (!isset($_GET['cron']) || $_GET['cron'] !== 'delete')) {
    http_response_code(403);
    die("Access denied. This script must be run via cron trigger.");
}

// Load the main bot file which contains all classes and constants.
// Note: Adjust the filename if your main bot file has a different name.
// In your case, the main file is "index.php" as per the screenshot.
require_once __DIR__ . '/index.php';

// ============================================================
// Initialize Database and BotAPI
// ============================================================
try {
    $db = new Database();
    $bot = new BotAPI(BOT_TOKEN);
} catch (Exception $e) {
    // Log error and exit if initialization fails
    error_log("CRON FATAL: Cannot initialize Database or BotAPI: " . $e->getMessage());
    http_response_code(500);
    die("Initialization failed.");
}

$now = time();
$processed = 0;
$errors = 0;

// ============================================================
// 1. DELETE DUE MESSAGES
// ============================================================
// Fetch all pending deletions where delete_at <= current time
$due = $db->getPendingDeletions(50); // Limit to 50 per run to avoid overload

foreach ($due as $del) {
    try {
        // Delete the original message (movie file or search result)
        $bot->deleteMessage($del['chat_id'], $del['message_id']);
        
        // If there's a progress message associated, delete it too
        if (!empty($del['progress_message_id'])) {
            $bot->deleteMessage($del['chat_id'], $del['progress_message_id']);
        }
        
        // Mark deletion as completed in database
        $db->updateScheduledDeletion($del['id'], ['status' => 'deleted']);
        
        $processed++;
        
        // Optional: Log success
        error_log("CRON: Deleted message {$del['message_id']} in chat {$del['chat_id']}");
        
    } catch (Exception $e) {
        $errors++;
        // Log error but continue with next deletion
        error_log("CRON ERROR: Failed to delete message ID {$del['message_id']}: " . $e->getMessage());
        
        // If message already missing (400/403), mark as deleted anyway to avoid retries
        if (strpos($e->getMessage(), '400') !== false || strpos($e->getMessage(), '403') !== false) {
            $db->updateScheduledDeletion($del['id'], ['status' => 'deleted']);
        }
    }
    
    // Small delay to avoid hitting rate limits
    usleep(200000); // 0.2 seconds
}

// ============================================================
// 2. UPDATE PROGRESS BARS FOR UPCOMING DELETIONS
// ============================================================
// Fetch deletions that will happen in the next 5 minutes (300 seconds)
$upcoming = $db->getUpcomingDeletions(300);

foreach ($upcoming as $del) {
    // Only update if still pending and has a progress message
    if ($del['status'] !== 'pending' || empty($del['progress_message_id'])) {
        continue;
    }
    
    $remaining = $del['delete_at'] - $now;
    
    // Only update if remaining time is positive and less than 5 minutes
    if ($remaining > 0 && $remaining <= 300) {
        try {
            // Generate progress bar
            $total = FILE_DELETE_TIME; // From constants (e.g., 120 seconds)
            $progress_text = "⚠️ File Delete in {$remaining}s\n" . Utils::getProgressBar($remaining, $total);
            
            // Edit the progress message
            $bot->editMessageText($del['chat_id'], $del['progress_message_id'], $progress_text);
            
            // Update last edit timestamp in database (optional, not strictly needed)
            $db->updateScheduledDeletion($del['id'], ['last_edit_at' => $now]);
            
        } catch (Exception $e) {
            // Log but don't stop; maybe message was already deleted
            error_log("CRON WARNING: Failed to update progress for deletion ID {$del['id']}: " . $e->getMessage());
        }
    }
}

// ============================================================
// 3. OPTIONAL: CLEANUP VERY OLD DELETION RECORDS (e.g., older than 1 day)
// ============================================================
// This keeps the database small. Adjust as needed.
$oneDayAgo = $now - 86400;
$stmt = $db->pdo->prepare("DELETE FROM scheduled_deletions WHERE delete_at < ? AND status = 'deleted'");
$stmt->bindValue(1, $oneDayAgo, SQLITE3_INTEGER);
$stmt->execute();
$cleaned = $db->pdo->changes();

// ============================================================
// 4. RETURN SUMMARY (for logging / debugging)
// ============================================================
$summary = sprintf(
    "[%s] Cron executed: %d processed, %d errors, %d cleaned up.",
    date('Y-m-d H:i:s'),
    $processed,
    $errors,
    $cleaned
);
error_log($summary);

// If this is an HTTP request (cron-job.org), output a simple response
if (php_sapi_name() !== 'cli') {
    echo "Cron job completed successfully at " . date('Y-m-d H:i:s') . "\n";
    echo "Processed: $processed, Errors: $errors, Cleaned: $cleaned";
}

// ============================================================
// End of script – it exits cleanly here.
// ============================================================
?>
