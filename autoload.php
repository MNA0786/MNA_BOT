<?php
// autoload.php
// Composer autoloader ko include karo

require_once __DIR__ . '/vendor/autoload.php';

// Agar koi extra custom autoloading chahiye to yahan add karo
spl_autoload_register(function ($class) {
    // Custom classes ke liye (agar koi specific class missing ho)
    $prefix = 'Bot\\';
    $base_dir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Koi global initialization ho to yahan kar sakte ho
date_default_timezone_set('Asia/Kolkata');
?>
