<?php
$apkFilePath = './mrcwoods-app';

// 检查文件
if (!file_exists($apkFilePath)) {
    http_response_code(404);
    echo "<script>alert('错误：文件不存在！');window.history.back();</script>";
    exit;
}

if (!is_readable($apkFilePath)) {
    http_response_code(403);
    echo "<script>alert('错误：没有读取权限！');window.history.back();</script>";
    exit;
}

$fileSize = filesize($apkFilePath);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="mrcwoods-app"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');

// 清空缓冲区
ob_clean();
flush();

// 输出文件
readfile($apkFilePath);
exit;
?>