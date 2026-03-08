<?php
header('Content-Type: application/json');

// 获取 API ID
$apiId = isset($_GET['apiId']) ? trim($_GET['apiId']) : '';

if (empty($apiId)) {
    echo json_encode([
        'success' => false,
        'message' => 'API ID 不能为空'
    ]);
    exit;
}

// 检查详情文件
$apiDetailFile = './apis/' . $apiId . '.json';
if (!file_exists($apiDetailFile)) {
    // 尝试从列表文件获取基础信息
    $apisListFile = 'apis.json';
    $apisList = json_decode(file_get_contents($apisListFile), true);
    
    $apiBasic = null;
    foreach ($apisList as $api) {
        if ($api['id'] === $apiId) {
            $apiBasic = $api;
            break;
        }
    }
    
    if ($apiBasic) {
        echo json_encode([
            'success' => true,
            'data' => $apiBasic
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'API 不存在'
        ]);
    }
    exit;
}

// 读取详情文件
$apiData = json_decode(file_get_contents($apiDetailFile), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'API 数据格式错误'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => $apiData
]);
?>