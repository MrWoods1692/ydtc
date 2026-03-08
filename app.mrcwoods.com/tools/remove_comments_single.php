<?php
/**
 * Remove Comments from a Single File
 * 
 * This script removes comments from a single specified file
 */

function removeCommentsFromFile($file) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

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

    switch ($extension) {
        case 'php':
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
            break;
            
        case 'js':
            // Remove multi-line comments first
            $newContent = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
            // Remove single-line comments
            $newContent = preg_replace('!//.*!', '', $newContent);
            break;
            
        case 'css':
            // Remove CSS comments (/* */)
            $newContent = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
            break;
            
        case 'html':
        case 'htm':
            // Remove HTML comments (<!-- -->)
            $newContent = preg_replace('/<!--.*?-->/s', '', $content);
            break;
            
        default:
            echo "Unsupported file type: $extension\n";
            return false;
    }

    // Only write if content has actually changed
    if ($newContent !== $originalContent) {
        // Ask for confirmation
        echo "This will remove comments from $file\n";
        echo "Original size: " . strlen($originalContent) . " chars\n";
        echo "New size: " . strlen($newContent) . " chars\n";
        echo "Enter 'yes' to confirm: ";

        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);

        if (strtolower($input) !== 'yes') {
            echo "Operation cancelled.\n";
            return false;
        }

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

// Main execution
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line interface (CLI).\n");
}

if (!isset($argv[1])) {
    echo "Usage: php remove_comments_single.php <file_path>\n";
    echo "Example: php remove_comments_single.php /path/to/your/file.php\n";
    exit(1);
}

$targetFile = $argv[1];

if (!file_exists($targetFile)) {
    die("File does not exist: $targetFile\n");
}

if (!is_writable($targetFile)) {
    die("File is not writable: $targetFile\n");
}

echo "Processing file: $targetFile\n";
removeCommentsFromFile($targetFile);