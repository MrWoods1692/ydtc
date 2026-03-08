<?php
header('Content-Type: application/json');

// 获取 POST 数据
$postData = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => '数据格式错误'
    ]);
    exit;
}

$apiId = $postData['apiId'] ?? '';

if (empty($apiId)) {
    echo json_encode([
        'success' => false,
        'message' => 'API ID 不能为空'
    ]);
    exit;
}

// 删除详情文件
$detailFile = './apis/' . $apiId . '.json';
if (file_exists($detailFile)) {
    unlink($detailFile);
}

// 更新列表文件
$apiListFile = 'apis.json';
if (file_exists($apiListFile)) {
    $apisList = json_decode(file_get_contents($apiListFile), true);
    
    if (is_array($apisList)) {
        // 过滤掉要删除的 API
        $newApisList = [];
        foreach ($apisList as $api) {
            if ($api['id'] !== $apiId) {
                $newApisList[] = $api;
            }
        }
        
        // 保存新列表
        file_put_contents($apiListFile, json_encode($newApisList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

echo json_encode([
    'success' => true,
    'message' => 'API 删除成功'
]);
?>