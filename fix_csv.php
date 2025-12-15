<?php
// fix_csv.php - CSV format correct karo
$input_file = 'movies (1).csv';  // Tumhara old file
$output_file = 'movies.csv';     // Correct format file

// 1. Purana file read karo
$input = fopen($input_file, 'r');
$output = fopen($output_file, 'w');

// 2. CORRECT HEADERS likho (EXACT 7 columns)
fputcsv($output, ['movie_name','message_id','date','video_path','quality','size','language']);

// 3. Har row ko correct format mein convert karo
$row_count = 0;
while(($row = fgetcsv($input)) !== false) {
    if(count($row) >= 3) {
        // Old: movie_name,message_id,date
        // New: movie_name,message_id,date,video_path,quality,size,language
        
        $movie_name = $row[0];
        $message_id = $row[1];
        $date = $row[2];
        
        // AUTO-DETECT QUALITY
        $quality = '720p';
        if(stripos($movie_name, '1080') !== false) $quality = '1080p';
        if(stripos($movie_name, 'hd') !== false) $quality = 'HD';
        if(stripos($movie_name, '4k') !== false) $quality = '4K';
        
        // AUTO-DETECT LANGUAGE
        $language = 'Hindi';
        $lang_keywords = [
            'Telugu' => ['telugu', 'tel'],
            'Tamil' => ['tamil', 'tam'],
            'Kannada' => ['kannada', 'kan'],
            'Malayalam' => ['malayalam', 'mal'],
            'English' => ['english', 'eng']
        ];
        
        foreach($lang_keywords as $lang => $keywords) {
            foreach($keywords as $keyword) {
                if(stripos($movie_name, $keyword) !== false) {
                    $language = $lang;
                    break 2;
                }
            }
        }
        
        // AUTO-DETECT SIZE BASED ON QUALITY
        $size_map = [
            '720p' => '1.5GB',
            '1080p' => '2.1GB',
            'HD' => '1.8GB',
            '4K' => '4.5GB'
        ];
        $size = $size_map[$quality] ?? '1.5GB';
        
        // New row with 7 columns
        $new_row = [
            $movie_name,      // 0: movie_name
            $message_id,      // 1: message_id
            $date,            // 2: date
            '',               // 3: video_path (empty)
            $quality,         // 4: quality
            $size,            // 5: size
            $language         // 6: language
        ];
        
        fputcsv($output, $new_row);
        $row_count++;
    }
}

fclose($input);
fclose($output);

echo "✅ CSV FORMAT FIXED!<br>";
echo "📊 Rows converted: $row_count<br>";
echo "📁 New file: $output_file<br>";
echo "🎯 Now upload this to Render and it will work automatically!";
?>
