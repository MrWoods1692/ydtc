<?php
session_start();
define('API_KEY', 'sk-seqjsnqdbhiyzjqiqxwwbtxyaqbnpfkdtztpgljpjxbfinlk');
define('API_URL', 'https://api.siliconflow.cn/v1/chat/completions');

ini_set('error_log', 'ai_chat_silicon_error.log');
ini_set('log_errors', 1);

function execute_tool($tool_name)
{
    switch ($tool_name) {
        case 'get_website_seo':
            return "网站SEO信息：
    安全稳定的云端图片储存服务，支持原图备份、分类、多端同步。免费起步，TB级空间任选，一键分享外链，摄影师与设计师的首选云相册。
    全球CDN加速的云端图片储存，秒开预览不卡顿。自动压缩省流量，支持多种格式托管，外链直传论坛与电商，新用户送2GB空间。
    端到端加密的私密云端图库，本地密钥掌控数据主权。防误删回收站、异地容灾备份，家庭照片与企业素材的安全保险箱。
    摄影师专属云端图片仓库，EXIF信息完整保留，AI智能体找图快。
    免费好用的云端图片储存，储存空间免费送。API接口丰富，5分钟接入网站图床，支持防盗链。";

        case 'get_website_readme':
            return "README.md：
# 云端图片储存

一个功能完整的云端图片储存与管理平台，支持 Web 端和多平台客户端，提供图片上传、相册管理、AI 绘图、开放 API 等能力。


## 在线使用

- 主站：[https://app.mrcwoods.com](https://app.mrcwoods.com)


## 功能特性

- **图片云存储** - 支持原图备份、分类管理、多端同步
- **相册管理** - 创建相册、浏览相册、照片查看器
- **AI 绘图** - 集成 AI 绘图功能，支持图片生成与上传
- **用户系统** - 邮箱验证码登录、Token 鉴权、会话管理
- **开放平台** - 提供 API 接口文档，支持第三方接入
- **工具箱** - 内置实用工具集合
- **公告系统** - 站点公告发布与管理
- **管理后台** - 后台管理面板，支持内容与用户管理
- **多端下载** - 提供 Windows / macOS / Linux / Android 客户端


### 🚀 创新亮点

#### 🎨 AI 智能化集成
- **AI 绘画引擎**：集成先进的 AI 绘画功能，支持文本生成图片
- **AI 图片修复**：智能修复老照片、模糊图片，提升画质
- **智能分类**：AI 自动识别图片内容并分类管理

#### 🎭 仿生交互设计
- **灵动岛导航**：移动端首创仿 iOS 灵动岛交互，动态显示当前页面
- **macOS 窗口风格**：桌面端完美复刻 macOS 窗口控制按钮和界面风格
- **粒子特效系统**：鼠标点击触发粒子动画，增强交互体验
- **动态字体渲染**：自定义字体 + 字体跨域优化，确保视觉一致性

#### 🔒 增强安全机制
- **多域名 Cookie 策略**：动态匹配域名的 Cookie 配置，支持多域名部署
- **智能验证码系统**：防刷机制 + 失败次数限制 + 邮箱域名白名单
- **端到端加密**：本地密钥管理，数据主权完全掌控
- **智能登录状态**：自动续期 + 多设备同步 + 异地登录检测

#### 🌐 跨平台优化
- **全平台适配**：从 320px 到 4K 屏幕完美适配
- **字体跨域解决方案**：自定义字体 + CORS 配置，解决字体加载问题
- **智能响应式**：根据屏幕尺寸自动切换布局和交互模式
- **离线缓存**：智能缓存策略，提升加载速度

### 🖼️ 图片管理
- **图床上传**：支持多种格式图片上传
- **相册管理**：分类管理图片资源
- **AI 绘画**：集成 AI 绘画功能

### 🔐 安全认证
- **邮箱验证码登录**：基于 PHPMailer 的邮件验证系统
- **Token 认证**：JWT 风格的 Token 认证机制
- **Cookie 安全**：支持 HTTPS、HttpOnly、SameSite 等安全配置
- **跨域支持**：完整的 CORS 配置

### 📱 多端适配
- **响应式设计**：适配桌面和移动端
- **灵动岛导航**：移动端仿 iOS 灵动岛交互
- **macOS 风格**：桌面端仿 macOS 窗口界面

### 🛠️ 开发特性
- **模块化架构**：清晰的目录结构和模块划分
- **环境配置**：支持 `.env` 环境变量配置
- **日志系统**：完整的操作日志记录
- **API 接口**：RESTful API 设计

## 技术栈

- **后端**：PHP 8.1+
- **数据库**：MySQL
- **前端**：HTML5 + CSS3 + JavaScript
- **邮件服务**：PHPMailer
- **包管理**：Composer

## 项目结构

```
app.mrcwoods.com/
├── index.php                 # 主入口
├── in/                       # 登录页面
├── main/                     # 主应用
│   └── index.php             # 主应用入口
├── home/                     # 主页
├── open-platform/            # 开放平台 (API 文档)
├── album/                    # 相册管理
├── aidraw/                   # AI 绘画
├── tc/                       # 图床功能
├── app/                      # 客户端应用
├── images/                   # 系统静态资源
├── svg/                      # SVG 图标
├── font/                     # 字体文件
├── about/                    # 关于页面
├── admin/                    # 网站后台
├── album-look/               # 查看相册页面
├── announcement/             # 公告页面
├── photo-look/               # 查看图片页面
├── set/                      # 设置页面
├── tools/                    # 工具页面
├── trask/                    # 回收站页面
└── log/                      # 用户日志
```

## 贡献

欢迎提交 Issue 和 Pull Request 来改进项目。


## 开源协议

[Apache License 2.0](LICENSE)


## 联系方式

- 作者：Mr.C.Woods
- QQ：1692138502
- 邮箱：mail@mrcwoods.com


## 创作思想

> **背景**：当前互联网中可供用户使用的图片存储平台虽然数量较多，但同时具备免费、高速、稳定以及良好用户界面体验的平台相对较少。部分平台存在访问速度慢、稳定性不足、界面设计粗糙或功能受限等问题，难以满足用户长期、便捷的图片存储与管理需求。

> **目的**：本项目旨在构建一个免费、快速、稳定且界面简洁美观的云端图片存储平台，为用户提供便捷的图片上传、存储与访问服务。同时，通过合理的系统架构与技术选型，提升平台的访问效率与可靠性。

> **意义**：项目以开源精神为理念，通过公开技术实现与设计思路，促进开发者之间的学习与交流，推动开源技术生态的发展。同时也为个人开发者和小型项目提供一个可参考、可扩展的图片存储解决方案。


## 创作过程

> 本项目在开发过程中综合运用了多种技术与开发工具，完成系统设计与功能实现。主要包括：
- **后端技术**： 使用 **PHP** 进行核心业务逻辑开发。
- **数据库**： 使用 **MySQL** 和 **SQLite**和**JSON**进行数据管理，存储图片信息及用户相关数据。
- **服务器环境**： 采用 **Nginx** 作为 Web 服务器，提高并发处理能力与访问效率。
- **前端实现**： 使用 **HTML、CSS、JavaScript** 构建用户界面，并结合现代前端设计理念优化用户体验。
- **动画与交互**： 使用 **Lottie** 动画 提升界面的动态效果与视觉体验。
- **多平台支持**： 开发了 **macOS 、 Windows、Linux、Android** 客户端，以及浏览器扩展插件，进一步提升用户使用的便利性。
> 在创作过程中，重点关注以下方面：
- 界面设计的简洁与美观
- 系统结构的稳定与高效
- 管理的优化
- 扩展的功能
这些方面构成了本项目较为突出的技术亮点。
详细技术实现与项目结构说明可参见项目的 **README.md** 文档。

## 原创部分

> 本项目大部分内容为原创开发，主要包括：
- 平台整体架构设计
- 核心逻辑代码
- 图片上传与管理系统
- 用户界面设计与实现
> 以下部分为非原创或使用了现有资源：
- /main 页面中的部分 CSS 样式为开源社区开发者贡献
- macOS、Windows 客户端程序为开源社区开发者帮忙编译
- 所有 SVG 矢量图标
- Lottie 动画资源
- 字体（Font）资源
- Live2D资源
- vendor 目录中的依赖库

## 参考资源

在项目开发过程中，使用了以下资源：
- 云智 API
- 失控图床
- 阿里巴巴矢量图标库（Iconfont）
这些资源为项目开发提供了接口与素材支持。
在项目开发过程中，参考了一下平台的文章：
- CSDN
- LINUXDO
- 博客园
这些平台为项目开发提供了技术参考。

## 制作用软件及运行环境
> **制作软件**：
- Visual Studio Code
- Breezell
> **运行环境**：
- PHP 8.1.32
- Node.js v22.13.1
- MySQL 5.7.44
- Nginx 1.28.1
其中 PHP 需要安装以下扩展：
- mailparse
- fileinfo
- apcu
- Zend
- OPcache

## 其他说明
项目在线地址：
[https://app.mrcwoods.com](https://app.mrcwoods.com)


";
        case 'get_website_info':
            return "基础信息：
1. 网站名称：云端图片储存
2. 创立时间：2026年2月3日
";
        case 'get_user_count':
            $user_dir = realpath(__DIR__ . '/../users/');
            if (!$user_dir || !is_dir($user_dir)) return "网站用户数量：0（用户数据库未创建）";
            $dir_count = count(array_filter(scandir($user_dir), function ($f) use ($user_dir) {
                return $f !== '.' && $f !== '..' && is_dir($user_dir . '/' . $f);
            }));
            return "网站用户数量：{$dir_count} 位";
        case 'get_website_create_time':
            return "网站成立时间：2026年2月3日（正式上线运营）";
        case 'get_shanghai_time':
            date_default_timezone_set('Asia/Shanghai');
            return "当前时间：" . date('Y年m月d日 H:i:s') . "";
        case 'get_author_info':
            return "作者信息：
1. 名称：Mr.C.Woods
2. 领域：设计策划前端后端开发测试运维经理
3. 理念：无他，唯手熟耳。";
        case 'simple_calculator':
            return "简易计算器使用说明：请输入具体计算表达式，例如「计算10*5+20/4」「20/(4+1)等于多少」，我会为你精准计算！";
        default:
            return "";
    }
}

function match_tool($message)
{
    $message = strtolower(trim($message));
    if (strpos($message, 'seo') !== false || strpos($message, '关键词') !== false || strpos($message, '标题') !== false) {
        return 'get_website_seo';
    } elseif (strpos($message, 'readme') !== false) {
        return 'get_website_readme';
    } elseif (strpos($message, '网站信息') !== false || strpos($message, '基础信息') !== false) {
        return 'get_website_info';
    } elseif (strpos($message, '用户数量') !== false || strpos($message, '用户数') !== false || strpos($message, '统计用户') !== false) {
        return 'get_user_count';
    } elseif (strpos($message, '成立时间') !== false || strpos($message, '上线时间') !== false) {
        return 'get_website_create_time';
    } elseif (strpos($message, '上海时间') !== false || strpos($message, '当前时间') !== false || strpos($message, '系统时间') !== false) {
        return 'get_shanghai_time';
    } elseif (strpos($message, '作者信息') !== false || strpos($message, '开发信息') !== false) {
        return 'get_author_info';
    } elseif (strpos($message, '计算') !== false || strpos($message, '等于') !== false || strpos($message, '加') !== false || strpos($message, '减') !== false || strpos($message, '乘') !== false || strpos($message, '除') !== false) {
        return 'simple_calculator';
    } else {
        return '';
    }
}

function calculate($message)
{
    $message = strtolower(trim($message));
    preg_match('/[0-9\+\-\*\/\.\(\)]+/', $message, $matches);
    if (empty($matches[0])) return "未识别到有效计算表达式，请输入例如「计算10*5+20/4」的格式！";
    $expression = $matches[0];
    try {
        $result = 0;
        @eval("\$result = {$expression};");
        if (is_nan($result) || is_infinite($result) || ($result === 0 && $expression !== '0')) {
            return "计算错误：表达式无效（如除数为0、括号不匹配），请检查格式！";
        }
        return "计算器结果：{$expression} = {$result}";
    } catch (Exception $e) {
        error_log("计算器错误：" . $e->getMessage());
        return "计算错误：" . $e->getMessage();
    }
}


function call_api($url, $api_key, $messages)
{
    $ch = curl_init($url);
    $data = [
        'model' => 'THUDM/GLM-4.1V-9B-Thinking',
        'messages' => $messages,
        'stream' => false,
        'temperature' => 1,
        'max_tokens' => 4096,
        'n' => 1
    ];
    $json_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
        'Accept: application/json'
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json_data,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_ENCODING => ''
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    if ($http_code != 200) error_log("服务器繁忙：{$http_code} | 响应：{$response} | 请求：{$json_data}");
    if ($curl_error) error_log("CURL错误：{$curl_error}");
    curl_close($ch);
    return ['response' => $response, 'http_code' => $http_code, 'error' => $curl_error];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'chat') {
    header('Content-Type: application/json;charset=utf-8');
    $message = trim($_POST['message'] ?? '');
    $conversation = json_decode($_POST['conversation'] ?? '[]', true);

    if (empty($message)) {
        echo json_encode(['error' => '消息不能为空'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!isset($_SESSION['conversation'])) {
        $_SESSION['conversation'] = [
            [
                'role' => 'system',
                'content' => '我是专业的云端图片储存平台客服助手，
                回答简洁专业，优先使用中文，无额外冗余内容，基于GPT-5.3模型，
                擅长解答网站管理相关问题。网站域名是https://app.mrcwoods.com，
                网站主页面是/main/页面，网站登录和注册页面是/in/页面，
                网站开放平台页面是/open-platform/页面，网站关于页面是/about/页面，
                网站快捷图床是/tc/页面。项目开源仓库https://github.com/MrWoods1692/ydtc。
                网站需要使用邮箱登录注册，未注册的自动注册，每次登录需要验证码。
                网站支持使用谷歌、Github、LINUXDO快捷登录。
                网站永久免费使用，没有广告，没有容量限制，没有流量限制。
                每日签到可以领取800到100积分，积分可以用于部分接口调用消耗，
                也可以用来AI绘画消耗积分，连续签到可以获得而外积分。
                本项目支持macOS、Windows、Linux、Android这些平台，支持网页端，有浏览器扩展插件、WordPress插件等。
                创作思想
背景：当前互联网中可供用户使用的图片存储平台虽然数量较多，但同时具备免费、高速、稳定以及良好用户界面体验的平台相对较少。部分平台存在访问速度慢、稳定性不足、界面设计粗糙或功能受限等问题，难以满足用户长期、便捷的图片存储与管理需求。
目的：本项目旨在构建一个免费、快速、稳定且界面简洁美观的云端图片存储平台，为用户提供便捷的图片上传、存储与访问服务。同时，通过合理的系统架构与技术选型，提升平台的访问效率与可靠性。
意义：项目以开源精神为理念，通过公开技术实现与设计思路，促进开发者之间的学习与交流，推动开源技术生态的发展。同时也为个人开发者和小型项目提供一个可参考、可扩展的图片存储解决方案。
作者：Mr.C.Woods
QQ：1692138502
邮箱：mail@mrcwoods.com
开源协议
Apache License 2.0
'
            ]
        ];
    }
    $conversation_history = $_SESSION['conversation'];
    $user_msg = ['role' => 'user', 'content' => $message];
    $conversation_history[] = $user_msg;

    $tool_name = match_tool($message);
    if (!empty($tool_name)) {
        if ($tool_name == 'simple_calculator') {
            $tool_result = calculate($message);
        } else {
            $tool_result = execute_tool($tool_name);
        }
        $ai_msg = ['role' => 'assistant', 'content' => $tool_result];
        $conversation_history[] = $ai_msg;
        $_SESSION['conversation'] = $conversation_history;
        echo json_encode([
            'message' => $tool_result,
            'conversation' => $conversation_history,
            'tool_used' => $tool_name
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $api_result = call_api(API_URL, API_KEY, $conversation_history);
    if ($api_result['http_code'] !== 200 || $api_result['error']) {
        echo json_encode([
            'error' => '失败',
            'msg' => $api_result['error'] ?: '状态码：' . $api_result['http_code'],
            'code' => $api_result['http_code']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = json_decode($api_result['response'], true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($result['choices']) || empty($result['choices'][0]['message'])) {
        echo json_encode(['error' => '模型返回无效数据'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ai_message = $result['choices'][0]['message'];
    $conversation_history[] = $ai_message;
    $_SESSION['conversation'] = $conversation_history;

    echo json_encode([
        'message' => $ai_message['content'],
        'conversation' => $conversation_history
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear') {
    session_destroy();
    session_start();
    header('Content-Type: application/json;charset=utf-8');
    echo json_encode(['success' => true, 'msg' => '对话历史已清空']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>AI智能客服助手</title>
    <style>
        /* 全局重置+全屏白色基础样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        html,
        body {
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            background: #FFFFFF;
            color: #333333;
        }

        /* 全屏容器 */
        .chat-container {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            padding: 0;
            margin: 0;
            border: 0;
            box-shadow: none;
        }

        /* 头部样式 - 简约白色 */
        .chat-header {
            height: 60px;
            line-height: 60px;
            background: #FFFFFF;
            border-bottom: 1px solid #F5F5F5;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }

        .chat-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #333333;
            margin: 0;
        }

        .clear-btn {
            background: #F8F8F8;
            border: 1px solid #ECECEC;
            color: #666666;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            height: 36px;
            line-height: 18px;
        }

        .clear-btn:hover {
            background: #F0F0F0;
            border-color: #E0E0E0;
        }

        /* 示例按钮栏 - 简约浅灰 */
        .examples {
            padding: 8px 20px;
            background: #FFFFFF;
            border-bottom: 1px solid #F5F5F5;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .example-btn {
            padding: 6px 12px;
            background: #F8F8F8;
            border: 1px solid #ECECEC;
            color: #666666;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .example-btn:hover {
            background: #F0F0F0;
            border-color: #409EFF;
            color: #409EFF;
        }

        /* 消息区域 - 全屏占比，白色背景 */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #FFFFFF;
            scrollbar-width: thin;
            scrollbar-color: #ECECEC #FFFFFF;
        }

        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #ECECEC;
            border-radius: 3px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: #FFFFFF;
        }

        /* 消息样式 - 简约气泡 */
        .message {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
            max-width: 100%;
        }

        .message.user {
            align-items: flex-end;
        }

        .message.assistant,
        .message.tool {
            align-items: flex-start;
        }

        .message-content {
            max-width: 80%;
            padding: 10px 14px;
            border-radius: 8px;
            word-wrap: break-word;
            white-space: pre-line;
            font-size: 14px;
            line-height: 1.5;
        }

        /* 用户消息 - 浅蓝主色 */
        .message.user .message-content {
            background: #E6F4FF;
            color: #409EFF;
        }

        /* 助手消息 - 浅灰背景 */
        .message.assistant .message-content {
            background: #F8F8F8;
            color: #333333;
            border: 1px solid #ECECEC;
        }

        /* 工具消息 - 浅青背景，区分显示 */
        .message.tool .message-content {
            background: #F0F9FF;
            color: #165DFF;
            border: 1px solid #E6F7FF;
        }

        /* 时间戳 - 浅灰小字 */
        .timestamp {
            font-size: 12px;
            margin-top: 4px;
            color: #999999;
        }

        .message.user .timestamp {
            text-align: right;
        }

        /* 输入区域 - 白色底，浅灰边框 */
        .chat-input-container {
            height: 60px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 20px;
            background: #FFFFFF;
            border-top: 1px solid #F5F5F5;
        }

        .chat-input {
            flex: 1;
            height: 40px;
            padding: 0 14px;
            border: 1px solid #ECECEC;
            border-radius: 4px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s ease;
            background: #FFFFFF;
        }

        .chat-input:focus {
            border-color: #409EFF;
            outline: none;
        }

        .chat-input::placeholder {
            color: #CCCCCC;
        }

        /* 发送按钮 - 简约蓝 */
        .send-btn {
            width: 80px;
            height: 40px;
            background: #409EFF;
            color: #FFFFFF;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .send-btn:hover {
            background: #3393FF;
        }

        .send-btn:disabled {
            background: #C6E2FF;
            cursor: not-allowed;
        }

        /* 正在输入动画 - 简约浅灰 */
        .typing-indicator {
            display: flex;
            padding: 10px 14px;
            background: #F8F8F8;
            border: 1px solid #ECECEC;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .typing-indicator span {
            width: 6px;
            height: 6px;
            background: #999999;
            border-radius: 50%;
            margin: 0 2px;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {

            0%,
            60%,
            100% {
                transform: translateY(0);
                opacity: 0.6;
            }

            30% {
                transform: translateY(-4px);
                opacity: 1;
            }
        }

        /* 响应式适配 - 手机端优化 */
        @media (max-width: 768px) {
            .message-content {
                max-width: 90%;
            }

            .chat-header h2 {
                font-size: 16px;
            }

            .example-btn {
                padding: 4px 8px;
                font-size: 12px;
            }

            .chat-input-container {
                padding: 0 10px;
                position: relative;
                /* Use env(safe-area-inset-bottom) to handle notches on mobile devices */
                padding-bottom: max(20px, env(safe-area-inset-bottom));
            }

            .chat-messages {
                padding: 10px;
            }
            
            /* Mobile-specific fix for virtual keyboard */
            body.keyboard-open {
                height: 100vh;
            }
            
            .chat-container {
                height: 100vh;
                min-height: -webkit-fill-available;
            }
        }
        
        /* Additional mobile optimization for keyboard visibility */
        @media screen and (max-height: 500px) {
            .chat-messages {
                flex: 1 1 auto;
                height: 70vh;
            }
            
            .chat-input-container {
                position: sticky;
                bottom: 0;
                background: white;
                z-index: 10;
            }
        }
    </style>
</head>

<body>
    <div class="chat-container">
        <div class="chat-header">
            <h2>AI智能客服助手</h2>
            <button class="clear-btn" onclick="clearConversation()">新对话</button>
        </div>
        <div class="examples">
            <button class="example-btn" onclick="setExample('获取网站SEO信息')">🔍 网站SEO</button>
            <button class="example-btn" onclick="setExample('获取网站用户数量')">👥 用户数量</button>
            <button class="example-btn" onclick="setExample('当前上海时间是多少')">🕙 当前时间</button>
            <button class="example-btn" onclick="setExample('计算10*5+20/4')">🧮 简易计算</button>
            <button class="example-btn" onclick="setExample('获取作者信息')">✍️ 作者信息</button>
            <button class="example-btn" onclick="setExample('获取网站README')">📄 README.md</button>
        </div>
        <div class="chat-messages" id="chatMessages"></div>
<div class="chat-input-container">
            <input type="text" class="chat-input" id="userInput" placeholder="请输入您的问题..." onkeypress="handleKeyPress(event)" onfocus="onInputFocus()" onblur="onInputBlur()">
            <button class="send-btn" id="sendBtn" onclick="sendMessage()">发送</button>
        </div>
    </div>
<script>
        // Mobile keyboard handling to fix input visibility
        let originalHeight = window.innerHeight;
        
        function onInputFocus() {
            document.body.classList.add('keyboard-open');
            // On mobile, ensure the input stays visible when keyboard appears
            setTimeout(() => {
                if (window.innerHeight < originalHeight * 0.8) {
                    // Keyboard is likely open, scroll to input
                    document.querySelector('.chat-input-container').scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            }, 300);
        }
        
        function onInputBlur() {
            document.body.classList.remove('keyboard-open');
        }
        
        // Listen for window resize events which typically happen when keyboard appears/disappears
        window.addEventListener('resize', function() {
            const currentHeight = window.innerHeight;
            // If window height decreased significantly, likely due to keyboard
            if (originalHeight - currentHeight > 150) {
                // Keyboard appeared
                document.body.classList.add('keyboard-open');
            } else {
                // Keyboard dismissed or orientation change
                document.body.classList.remove('keyboard-open');
            }
        });
        let isLoading = false;
        let conversation = [];
        document.addEventListener('DOMContentLoaded', loadConversation);

        // 加载对话历史（过滤system消息，避免初始加载泄露）
        function loadConversation() {
            const saved = sessionStorage.getItem('conversation');
            if (saved) {
                // 核心：解析后过滤掉所有system角色消息
                conversation = JSON.parse(saved).filter(msg => msg.role !== 'system');
                displayMessages();
            } else {
                const welcomeMsg = {
                    role: 'assistant',
                    content: '您好！我是云端图片储存平台专属AI客服助手，可解答平台使用、功能咨询、技术支持等问题，直接输入问题即可使用！',
                    timestamp: new Date().toLocaleTimeString()
                };
                conversation = [welcomeMsg];
                displayMessages();
            }
        }

        // 渲染消息（跳过system角色，不渲染到页面）
        function displayMessages() {
            const container = document.getElementById('chatMessages');
            container.innerHTML = '';
            conversation.forEach(msg => {
                // 核心：遇到system角色直接跳过，不生成页面元素
                if (msg.role === 'system') return;

                const messageDiv = document.createElement('div');
                const role = msg.tool_used ? 'tool' : msg.role;
                messageDiv.className = `message ${role}`;

                const contentDiv = document.createElement('div');
                contentDiv.className = 'message-content';
                contentDiv.textContent = msg.content;

                const timeDiv = document.createElement('div');
                timeDiv.className = 'timestamp';
                timeDiv.textContent = msg.timestamp || new Date().toLocaleTimeString();

                messageDiv.appendChild(contentDiv);
                messageDiv.appendChild(timeDiv);
                container.appendChild(messageDiv);
            });
            container.scrollTop = container.scrollHeight;
            // 存储时再次过滤system，避免本地缓存泄露
            sessionStorage.setItem('conversation', JSON.stringify(conversation.filter(msg => msg.role !== 'system')));
        }

        // 发送消息（全程过滤system消息）
        async function sendMessage() {
            const input = document.getElementById('userInput');
            const message = input.value.trim();
            if (!message || isLoading) return;

            // 添加用户消息（自动过滤system，此处无）
            const userMsg = {
                role: 'user',
                content: message,
                timestamp: new Date().toLocaleTimeString()
            };
            conversation.push(userMsg);
            displayMessages();
            input.value = '';

            showTypingIndicator();
            isLoading = true;
            document.getElementById('sendBtn').disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'chat');
                formData.append('message', message);
                // 传给后端的会话先过滤system，避免前端传递时泄露
                formData.append('conversation', JSON.stringify(conversation.filter(msg => msg.role !== 'system')));

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                removeTypingIndicator();

                if (data.error) {
                    alert('操作失败：' + (data.msg || data.error));
                    console.log('错误详情：', data);
                    return;
                }

                // 添加AI/工具结果消息（过滤可能存在的system）
                const aiMsg = {
                    role: 'assistant',
                    content: data.message,
                    timestamp: new Date().toLocaleTimeString(),
                    tool_used: data.tool_used || ''
                };
                conversation.push(aiMsg);
                // 过滤后再渲染
                conversation = conversation.filter(msg => msg.role !== 'system');
                displayMessages();

                // 更新会话时强制过滤system
                if (data.conversation) {
                    conversation = data.conversation.filter(msg => msg.role !== 'system');
                }

            } catch (error) {
                console.error('发送失败：', error);
                removeTypingIndicator();
                alert('发送失败，请查看服务器错误日志');
            } finally {
                isLoading = false;
                document.getElementById('sendBtn').disabled = false;
            }
        }

        // 以下函数无需修改，保持原样
        function showTypingIndicator() {
            const container = document.getElementById('chatMessages');
            const indicator = document.createElement('div');
            indicator.className = 'typing-indicator';
            indicator.id = 'typingIndicator';
            indicator.innerHTML = '<span></span><span></span><span></span>';
            container.appendChild(indicator);
            container.scrollTop = container.scrollHeight;
        }

        function removeTypingIndicator() {
            const indicator = document.getElementById('typingIndicator');
            if (indicator) indicator.remove();
        }

        async function clearConversation() {
            try {
                const formData = new FormData();
                formData.append('action', 'clear');
                await fetch('', {
                    method: 'POST',
                    body: formData
                });
                conversation = [];
                sessionStorage.removeItem('conversation');
                loadConversation();
            } catch (error) {
                console.error('清除对话失败：', error);
                alert('清除对话失败，请重试');
            }
        }

        function handleKeyPress(event) {
            if (event.key === 'Enter') sendMessage();
        }

        function setExample(text) {
            document.getElementById('userInput').value = text;
        }
    </script>
</body>

</html>