<?php
/**
 * Remove Comments from Multiple File Types
 * 
 * This script recursively removes comments from various file types:
 * PHP, JavaScript, CSS, HTML, and other common web development files
 */

function removeCommentsFromPhpFile($content) {
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
    
    return $newContent;
}

function removeCommentsFromJsFile($content) {
    // Remove single-line comments (//)
    $content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content); // Multi-line comments
    $content = preg_replace('!//.*!', '', $content); // Single-line comments
    
    return $content;
}

function removeCommentsFromCssFile($content) {
    // Remove CSS comments (/* */)
    $content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
    
    return $content;
}

function removeCommentsFromHtmlFile($content) {
    // Remove HTML comments (<!-- -->)
    $content = preg_replace('/<!--.*?-->/s', '', $content);
    
    return $content;
}

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

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    switch ($extension) {
        case 'php':
            $newContent = removeCommentsFromPhpFile($content);
            break;
        case 'js':
            $newContent = removeCommentsFromJsFile($content);
            break;
        case 'css':
            $newContent = removeCommentsFromCssFile($content);
            break;
        case 'html':
        case 'htm':
            $newContent = removeCommentsFromHtmlFile($content);
            break;
        default:
            // For other file types, don't modify
            $newContent = $content;
            break;
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
    
    $commentableFiles = new RegexIterator(
        $iterator, 
        '/\.(php|js|css|html|htm)$/i', 
        RecursiveRegexIterator::GET_MATCH
    );
    
    foreach ($commentableFiles as $files) {
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
echo "This will remove comments from all PHP, JS, CSS, HTML files in the directory and its subdirectories.\n";
echo "File extensions that will be processed: PHP, JS, CSS, HTML, HTM\n";
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
echo "Completed! Processed $processedCount files.\n";