<?php
header('Content-Type: application/json');

// 开启错误提示（临时用于调试）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 确保 APIs 目录存在
if (!file_exists('./apis')) {
    mkdir('./apis', 0755, true);
}

// 获取 POST 数据
$rawPostData = file_get_contents('php://input');
$postData = json_decode($rawPostData, true);

// 调试：输出接收到的数据（生产环境删除）
error_log('接收到的原始数据：' . $rawPostData);

// 容错：如果 JSON 解析失败
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => '数据格式错误：' . json_last_error_msg()
    ]);
    exit;
}

$apiData = $postData['apiData'] ?? [];
$isEdit = $postData['isEdit'] ?? false;

// 严格校验 API ID（增加调试输出）
$apiId = trim($apiData['id'] ?? '');
error_log('解析到的 API ID：' . $apiId); // 调试输出

if (empty($apiId)) {
    echo json_encode([
        'success' => false,
        'message' => 'API ID 不能为空（服务器校验）'
    ]);
    exit;
}

// 后续逻辑保持不变
$apiListFile = 'apis.json';
$apisList = [];
if (file_exists($apiListFile)) {
    $apisList = json_decode(file_get_contents($apiListFile), true);
    if (!is_array($apisList)) $apisList = [];
}

$basicInfo = [
    'id' => $apiId,
    'name' => trim($apiData['name'] ?? ''),
    'method' => trim($apiData['method'] ?? 'GET'),
    'description' => trim($apiData['description'] ?? '')
];

$foundIndex = -1;
foreach ($apisList as $index => $api) {
    if ($api['id'] === $apiId) {
        $foundIndex = $index;
        break;
    }
}

if ($foundIndex >= 0) {
    $apisList[$foundIndex] = $basicInfo;
} else {
    $apisList[] = $basicInfo;
}

file_put_contents($apiListFile, json_encode($apisList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// 🔥 核心修复：保留 example/response 的原始类型（文本/JSON）
$detailData = [
    'id' => $apiId,
    'name' => trim($apiData['name'] ?? ''),
    'method' => trim($apiData['method'] ?? 'GET'),
    'url' => trim($apiData['url'] ?? ''),
    'description' => trim($apiData['description'] ?? ''),
    'responseFormat' => trim($apiData['responseFormat'] ?? ''),
    'params' => $apiData['params'] ?? [],
    'responseParams' => $apiData['responseParams'] ?? [],
    'statusCodes' => $apiData['statusCodes'] ?? [],
    'example' => $apiData['example'] ?? '', // 空值时保存为空字符串，而非空数组
    'response' => $apiData['response'] ?? '', // 同上
    'exampleLang' => trim($apiData['exampleLang'] ?? 'json')
];

$detailFile = './apis/' . $apiId . '.json';
file_put_contents($detailFile, json_encode($detailData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo json_encode([
    'success' => true,
    'message' => $isEdit ? 'API 更新成功' : 'API 创建成功'
]);
?>