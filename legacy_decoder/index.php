<?php

declare(strict_types=1);

/**
 * Google News RSS URL Decoder (Bridge)
 * 
 * This PHP script acts as a bridge to the Node.js/Playwright engine
 * which is required to resolve modern Google News signed IDs.
 */

echo "--- Google News Decoder ---
";

// Check if Node.js is installed
$nodeVersion = @shell_exec('node -v');
if (!$nodeVersion) {
    die("Error: Node.js is required to resolve these links. Please install Node.js.\n");
}

// Check if dependencies are installed
if (!file_exists('node_modules')) {
    echo "Installing dependencies (Playwright, Axios)...
";
    shell_exec('npm install playwright axios xml2js');
    shell_exec('npx playwright install chromium');
}

echo "Starting resolution engine...
";

// Execute the Node.js worker
// Using passthru to stream output to terminal
passthru('node index.js');

echo "\nProcess complete.\n";

