<?php
/**
 * PHP实现APK文件下载功能
 * 注意：请确保文件路径正确且PHP有读取该文件的权限
 */

// 定义APK文件的路径（请根据你的实际路径修改）
$apkFilePath = './云端图床.apk'; // APK文件和此PHP文件同目录

// 检查文件是否存在
if (!file_exists($apkFilePath)) {
    http_response_code(404);
    echo "<script>alert('错误：文件不存在，请检查文件路径！');window.history.back();</script>";
    exit;
}

// 检查文件是否可读
if (!is_readable($apkFilePath)) {
    http_response_code(403);
    echo "<script>alert('错误：没有读取文件的权限！');window.history.back();</script>";
    exit;
}

// 获取文件基本信息
$fileName = basename($apkFilePath);
$fileSize = filesize($apkFilePath);

// 设置HTTP响应头
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . $fileSize);

// 清空缓冲区
ob_clean();
flush();

// 输出文件内容（大文件建议用分段读取）
$fp = fopen($apkFilePath, 'rb');
while (!feof($fp)) {
    echo fread($fp, 8192);
    flush();
}
fclose($fp);

exit;
?>