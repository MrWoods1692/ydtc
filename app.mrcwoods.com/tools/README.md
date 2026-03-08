# Comments Removal Scripts

This directory contains scripts to remove comments from various file types in your project.

## Available Scripts

### 1. `remove_comments_multi.php`

Removes comments from multiple file types (PHP, JavaScript, CSS, HTML) recursively from a directory and its subdirectories.

**Usage:**

```bash
php remove_comments_multi.php [directory_path]
```

If no directory is specified, it will process the current directory.

**Supported file types:**

- PHP files (.php) - Uses PHP tokenizer to safely remove comments without affecting strings
- JavaScript files (.js) - Removes both multi-line (`/* */`) and single-line (`//`) comments
- CSS files (.css) - Removes multi-line comments (`/* */`)
- HTML files (.html, .htm) - Removes HTML comments (`<!-- -->`)

### 2. `remove_comments.php`

Removes comments from PHP files only, recursively from a directory and its subdirectories.

**Usage:**

```bash
php remove_comments.php [directory_path]
```

### 3. `remove_comments_single.php`

Removes comments from a single specified file.

**Usage:**

```bash
php remove_comments_single.php <file_path>
```

## How It Works

- **PHP files**: Uses PHP's built-in tokenizer to safely identify and remove comments without affecting strings that might contain comment-like syntax
- **JavaScript files**: Uses regular expressions to remove both multi-line and single-line comments
- **CSS files**: Removes CSS-style comments (`/* */`)
- **HTML files**: Removes HTML comments (`<!-- -->`)

## Precautions

1. **Always backup your code** before running these scripts
2. These scripts modify files in place and cannot be undone
3. The scripts will ask for confirmation before making changes
4. Only run on source files, not on minified or production files where comments might serve a purpose

## Example Usage

```bash
# Remove comments from all supported files in current directory
php remove_comments_multi.php .

# Remove comments from all PHP files in a specific directory
php remove_comments.php /path/to/your/project

# Remove comments from a single file
php remove_comments_single.php /path/to/your/file.php
```
