<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

echo "<pre>";

// 1. Check current directory
echo "Current Directory: " . __DIR__ . "\n\n";

// 2. List all files in project root
echo "FILES IN CURRENT DIRECTORY:\n";
print_r(scandir(__DIR__));
echo "\n\n";

// 3. Check if debug_output.txt exists
$debugFile = __DIR__ . "/debug_output.txt";

echo "Looking for: $debugFile\n\n";

if (file_exists($debugFile)) {
    echo "--- debug_output.txt CONTENT ---\n\n";
    echo htmlspecialchars(file_get_contents($debugFile));
} else {
    echo "❌ debug_output.txt NOT FOUND\n";

    // Try to find ANY similar file
    echo "\nSearching for txt files...\n";
    $txtFiles = glob(__DIR__ . "/*.txt");
    print_r($txtFiles);
}

echo "</pre>";
?>
