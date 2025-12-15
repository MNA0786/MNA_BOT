<?php
// convert_csv.php
// Run this on your LOCAL computer to fix CSV format before uploading to Render

echo "<h2>🔄 Converting Your CSV to Correct Format</h2>";

// Input file (your old CSV)
$input_file = 'movies (1).csv';
// Output file (correct format)
$output_file = 'movies.csv';

if (!file_exists($input_file)) {
    die("❌ Error: '$input_file' not found!<br>
         Make sure you have the CSV file in the same folder as this script.");
}

// Read input file
$input = fopen($input_file, 'r');
$output = fopen($output_file, 'w');

// Write CORRECT headers (7 columns)
fputcsv($output, ['movie_name', 'message_id', 'date', 'video_path', 'quality', 'size', 'language']);

$total_rows = 0;
$converted_rows = 0;

// Process each row
while (($row = fgetcsv($input)) !== false) {
    $total_rows++;
    
    // Skip empty rows
    if (count($row) < 3 || empty(trim($row[0]))) {
        continue;
    }
    
    $movie_name = trim($row[0]);
    $message_id = isset($row[1]) ? trim($row[1]) : '';
    $date = isset($row[2]) ? trim($row[2]) : date('d-m-Y');
    
    // Auto-detect QUALITY from movie name
    $quality = '720p';
    $quality_patterns = [
        '4k' => '4K',
        '2160p' => '4K',
        '1080p' => '1080p',
        'fhd' => '1080p',
        '720p' => '720p',
        'hd' => 'HD',
        'hq' => 'HD',
        '480p' => '480p'
    ];
    
    $movie_lower = strtolower($movie_name);
    foreach ($quality_patterns as $pattern => $q) {
        if (strpos($movie_lower, $pattern) !== false) {
            $quality = $q;
            break;
        }
    }
    
    // Auto-detect LANGUAGE
    $language = 'Hindi';
    $language_patterns = [
        'telugu' => 'Telugu',
        'tel' => 'Telugu',
        'tamil' => 'Tamil',
        'tam' => 'Tamil',
        'kannada' => 'Kannada',
        'kan' => 'Kannada',
        'malayalam' => 'Malayalam',
        'mal' => 'Malayalam',
        'english' => 'English',
        'eng' => 'English',
        'hindi' => 'Hindi'
    ];
    
    foreach ($language_patterns as $pattern => $lang) {
        if (strpos($movie_lower, $pattern) !== false) {
            $language = $lang;
            break;
        }
    }
    
    // Auto-detect SIZE based on quality
    $size = '1.5GB';
    $size_map = [
        '4K' => '4.5GB',
        '1080p' => '2.1GB',
        'HD' => '1.8GB',
        '720p' => '1.5GB',
        '480p' => '800MB'
    ];
    $size = $size_map[$quality] ?? '1.5GB';
    
    // Create new row with 7 columns
    $new_row = [
        $movie_name,    // movie_name
        $message_id,    // message_id
        $date,          // date
        '',             // video_path (empty)
        $quality,       // quality
        $size,          // size
        $language       // language
    ];
    
    fputcsv($output, $new_row);
    $converted_rows++;
}

fclose($input);
fclose($output);

// Create summary
echo "✅ <b>CONVERSION COMPLETE!</b><br><br>";
echo "📊 <b>Statistics:</b><br>";
echo "• Total rows processed: $total_rows<br>";
echo "• Rows converted: $converted_rows<br>";
echo "• Output file: <b>$output_file</b> (READY FOR RENDER)<br><br>";

echo "📋 <b>First 5 converted rows:</b><br>";
echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr style='background: #f2f2f2;'><th>movie_name</th><th>message_id</th><th>date</th><th>quality</th><th>size</th><th>language</th></tr>";

$sample = fopen($output_file, 'r');
fgetcsv($sample); // Skip header

for ($i = 0; $i < 5 && ($row = fgetcsv($sample)) !== false; $i++) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row[0]) . "</td>";
    echo "<td>" . htmlspecialchars($row[1]) . "</td>";
    echo "<td>" . htmlspecialchars($row[2]) . "</td>";
    echo "<td>" . htmlspecialchars($row[4]) . "</td>";
    echo "<td>" . htmlspecialchars($row[5]) . "</td>";
    echo "<td>" . htmlspecialchars($row[6]) . "</td>";
    echo "</tr>";
}
fclose($sample);

echo "</table><br>";

echo "🚀 <b>NEXT STEPS:</b><br>";
echo "1. Upload <b>$output_file</b> to Render dashboard (rename to movies.csv if needed)<br>";
echo "2. Replace your bot.php with the fixed version above<br>";
echo "3. Visit your bot website to verify<br>";
echo "4. Use Telegram commands: /recent, /syncall<br><br>";

echo "🎯 <b>Your movies.csv now has CORRECT 7-column format!</b>";
?>
