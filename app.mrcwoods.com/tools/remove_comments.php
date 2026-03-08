<?php
/**
 * Remove Comments from PHP Files
 * 
 * This script recursively removes all comments from PHP files in a specified directory
 */

function removeCommentsFromFile($file) {
    if (!file_exists($file) || !is_readable($file)) {
        echo "File not accessible: $file\n";
        return false;
    }

    $content = file_get_contents($file);
    if ($content === false) {
        echo "Could not read file: $file\n";
        return false;
    }

    // Store original content for comparison
    $originalContent = $content;

    // Use PHP's tokenization to safely remove comments without affecting strings
    $tokens = token_get_all($content);
    $newContent = '';
    
    foreach ($tokens as $token) {
        if (is_array($token)) {
            // Skip comment tokens
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                continue;
            }
        }
        $newContent .= is_array($token) ? $token[1] : $token;
    }

    // Only write if content has actually changed
    if ($newContent !== $originalContent) {
        if (file_put_contents($file, $newContent) !== false) {
            echo "Comments removed from: $file\n";
            return true;
        } else {
            echo "Failed to write to file: $file\n";
            return false;
        }
    } else {
        echo "No comments to remove in: $file\n";
        return true;
    }
}

function processDirectory($directory) {
    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    $phpFiles = new RegexIterator($iterator, '/\.php$/i', RecursiveRegexIterator::GET_MATCH);
    
    foreach ($phpFiles as $files) {
        foreach ($files as $file) {
            if (removeCommentsFromFile($file)) {
                $count++;
            }
        }
    }
    
    return $count;
}

// Main execution
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line interface (CLI).\n");
}

// Default directory is the current directory, but can be overridden
$targetDirectory = isset($argv[1]) ? $argv[1] : getcwd();

if (!is_dir($targetDirectory)) {
    die("Directory does not exist: $targetDirectory\n");
}

echo "Processing directory: $targetDirectory\n";
echo "This will remove comments from all PHP files in the directory and its subdirectories.\n";
echo "Enter 'yes' to confirm: ";

$handle = fopen("php://stdin", "r");
$input = trim(fgets($handle));
fclose($handle);

if (strtolower($input) !== 'yes') {
    echo "Operation cancelled.\n";
    exit(0);
}

echo "Starting to remove comments...\n";
$processedCount = processDirectory($targetDirectory);
echo "Completed! Processed $processedCount PHP files.\n";