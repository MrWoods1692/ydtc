<?php
session_start();

if (!isset($_SESSION['chat_context']) || !is_array($_SESSION['chat_context'])) {
    $_SESSION['chat_context'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_start();
    
    if ($_POST['action'] === 'regenerate') {
        $index = intval($_POST['index'] ?? -1);
        if ($index < 0 || !isset($_SESSION['chat_context'][$index])) {
            echo json_encode(['status' => 'error', 'msg' => '无效索引'], JSON_UNESCAPED_UNICODE);
            ob_end_flush();
            exit;
        }
        
        $user_input = $_SESSION['chat_context'][$index]['user'];
        $file_content_str = $_SESSION['chat_context'][$index]['file'] ?? '';
        $final_question = $file_content_str . "用户提问：{$user_input}";
        $final_question = trim($final_question);
        
        $context_str = '';
        for ($i = 0; $i < $index; $i++) {
            $item = $_SESSION['chat_context'][$i];
            if (!empty($item['user']) && !empty($item['ai'])) {
                $context_str .= "用户：{$item['user']}\nAI：{$item['ai']}\n";
            }
        }
        
        $api_question = $context_str . "用户：{$final_question}";
        $api_question_encoded = urlencode($api_question);
        $api_url = "https://api.jkyai.top/vip/gpt5?question={$api_question_encoded}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: PHP-Server-Request/1.0',
            'Accept: text/plain'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("AI接口错误：".$error);
            echo json_encode(['status' => 'error', 'msg' => '服务器繁忙'], JSON_UNESCAPED_UNICODE);
        } else {
            if ($http_code == 200) {
                $_SESSION['chat_context'][$index]['ai'] = $response;
                echo json_encode([
                    'status' => 'success',
                    'index' => $index,
                    'ai_reply' => htmlspecialchars($response, ENT_QUOTES, 'UTF-8')
                ], JSON_UNESCAPED_UNICODE);
            } else {
                error_log("AI接口状态码：".$http_code);
                echo json_encode(['status' => 'error', 'msg' => '服务器繁忙'], JSON_UNESCAPED_UNICODE);
            }
        }
        ob_end_flush();
        exit;
    }

    if ($_POST['action'] === 'chat') {
        $user_input = trim($_POST['question'] ?? '');
        $file_content_str = '';
        $file_name = '';
        $has_content = false;

        if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['chat_file']['name'];
            $file_tmp = $_FILES['chat_file']['tmp_name'];
            $file_type = mime_content_type($file_tmp);
            $allowed_types = ['text/plain', 'text/html', 'application/octet-stream', 'text/markdown'];
            if (in_array($file_type, $allowed_types)) {
                $file_content = file_get_contents($file_tmp);
                $file_content = htmlspecialchars($file_content, ENT_QUOT, 'UTF-8');
                $file_content_str = "[文件：{$file_name}]\n{$file_content}\n\n";
                $has_content = true;
            }
        }

        if (!empty($user_input)) $has_content = true;
        if (!$has_content) {
            echo json_encode(['status' => 'error', 'msg' => '请输入内容'], JSON_UNESCAPED_UNICODE);
            ob_end_flush();
            exit;
        }

        $editIndex = intval($_POST['edit_index'] ?? -1);
        if ($editIndex >= 0) {
            $_SESSION['chat_context'] = array_slice($_SESSION['chat_context'], 0, $editIndex);
        }

        $final_question = $file_content_str . "用户提问：{$user_input}";
        $final_question = trim($final_question);
        $context_str = '';
        foreach ($_SESSION['chat_context'] as $item) {
            if (!empty($item['user']) && !empty($item['ai'])) {
                $context_str .= "用户：{$item['user']}\nAI：{$item['ai']}\n";
            }
        }

        $api_question = $context_str . "用户：{$final_question}";
        $api_question_encoded = urlencode($api_question);
        $api_url = "https://api.jkyai.top/vip/gpt5/?system=你是ChatGPT，由GPT-5架构训练，是智能助手。&question={$api_question_encoded}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: PHP-Server-Request/1.0',
            'Accept: text/plain'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("对话接口错误：".$error);
            $ai_reply = "";
        } else {
            $ai_reply = $http_code == 200 ? $response : "";
        }

        $time = date('Y-m-d H:i:s');
        $_SESSION['chat_context'][] = [
            'user' => $user_input,
            'ai' => $ai_reply,
            'time' => $time,
            'file' => $file_content_str,
            'file_name' => $file_name
        ];
        if (count($_SESSION['chat_context']) > 10) array_shift($_SESSION['chat_context']);

        echo json_encode([
            'status' => 'success',
            'user_input' => $user_input,
            'ai_reply' => htmlspecialchars($ai_reply, ENT_QUOTES, 'UTF-8'),
            'time' => $time,
            'file_name' => $file_name,
            'new_index' => count($_SESSION['chat_context']) - 1
        ], JSON_UNESCAPED_UNICODE);

        ob_end_flush();
        exit;
    }

    if ($_POST['action'] === 'clear') {
        $_SESSION['chat_context'] = [];
        echo json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE);
        ob_end_flush();
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        html, body {
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            background: #f0f2f5;
        }
        .app-container {
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 10px;
        }
        .chat-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f0f2f5;
        }
        .message-item {
            display: flex;
            margin-bottom: 20px;
        }
        .ai-message {
            justify-content: flex-start;
        }
        .user-message {
            justify-content: flex-end;
        }
        .avatar-container {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            margin: 0 10px;
        }
        .avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: #e8f4ff;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .avatar img {
            width: 24px;
            height: 24px;
            object-fit: contain;
        }
        .message-content-wrapper {
            max-width: 65%;
            display: flex;
            flex-direction: column;
        }
        .message-time {
            font-size: 12px;
            color: #999;
            margin-bottom: 4px;
        }
        .ai-message .message-time {
            padding-left: 6px;
        }
        .user-message .message-time {
            text-align: right;
            padding-right: 6px;
        }
        .message-bubble {
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 15px;
            line-height: 1.5;
            word-break: break-all;
        }
        .ai-message .message-bubble {
            background: #fff;
            border-top-left-radius: 4px;
        }
        .user-message .message-bubble {
            background: #67c23a;
            color: #fff;
            border-top-right-radius: 4px;
        }
        .message-actions {
            display: flex;
            gap: 6px;
            margin-top: 6px;
        }
        .ai-message .message-actions {
            padding-left: 6px;
        }
        .action-btn {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: none;
            background: rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .action-btn img {
            width: 14px;
            height: 14px;
        }
        .action-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .loading-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 0;
        }
        .thinking-text {
            font-size: 15px;
            color: #666;
        }
        .loading-container {
            width: 30px;
            height: 20px;
        }

        /* 输入区域 - 完全透明 */
        .input-area {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            padding: 8px;
            background: transparent !important;
            box-shadow: none !important;
            border: none !important;
            justify-content: center;
        }
        .clear-btn {
            height: 32px;
            padding: 0 14px;
            border-radius: 16px;
            border: none;
            background: #f56c6c;
            color: #fff;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }
        .clear-btn img {
            width: 12px;
            height: 12px;
            filter: invert(1);
        }
        .input-wrapper {
            flex: 0 1 700px;
            max-width: 700px;
            position: relative;
            background: transparent !important;
        }
        .uploaded-file-container {
            position: absolute;
            left: 0;
            bottom: 36px;
            width: 100%;
            padding: 4px 10px;
            background: rgba(255,255,255,0.9);
            border: 1px solid #eee;
            border-radius: 10px 10px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            color: #333;
        }
        .remove-file-btn {
            width: 22px;
            height: 22px;
            border: none;
            background: transparent;
            font-size: 16px;
            color: #999;
            cursor: pointer;
        }
        #question {
            width: 100%;
            padding: 8px 40px;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            font-size: 15px;
            resize: none;
            height: 36px;
            min-height: 36px;
            max-height: 120px;
            background: #f9fafb;
            outline: none;
        }
        #question:focus {
            border-color: #007aff;
            background: #fff;
        }
        .input-btn {
            position: absolute;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            bottom: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .upload-btn {
            left: 4px;
            background: transparent;
        }
        .send-btn {
            right: 4px;
            background: #007aff;
        }
        .input-btn img {
            width: 14px;
            height: 14px;
        }
        .send-btn img {
            filter: invert(1);
        }
        #file-upload {
            display: none;
        }

        /* 苹果风格提示 */
        .apple-toast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 8px 16px;
            background: rgba(0,0,0,0.5);
            color: #fff;
            border-radius: 12px;
            font-size: 14px;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        .apple-toast.show {
            opacity: 1;
        }
    </style>
    <script src="../main/js/lottie.min.js"></script>
</head>
<body>
<div class="app-container">
    <div class="chat-area" id="chat-messages">
        <?php foreach ($_SESSION['chat_context'] as $index => $msg): ?>
        <div class="message-item user-message" data-index="<?= $index ?>">
            <div class="message-content-wrapper">
                <div class="message-time"><?= $msg['time'] ?></div>
                <div class="message-bubble"><?= htmlspecialchars($msg['user']) ?></div>
                <div class="message-actions">
                    <button class="action-btn" onclick="editMessage(<?= $index ?>)">
                        <img src="../svg/编辑.svg">
                    </button>
                </div>
            </div>
            <div class="avatar-container">
                <div class="avatar">
                    <img src="../svg/用户.svg">
                </div>
            </div>
        </div>

        <div class="message-item ai-message" data-index="<?= $index ?>">
            <div class="avatar-container">
                <div class="avatar">
                    <img src="../svg/chatgpt.svg">
                </div>
            </div>
            <div class="message-content-wrapper">
                <div class="message-time"><?= $msg['time'] ?></div>
                <div class="message-bubble" id="ai-content-<?= $index ?>">
                    <?= htmlspecialchars($msg['ai']) ?>
                </div>
                <div class="message-actions">
                    <button class="action-btn voice-btn" onclick="playVoice(<?= $index ?>)">
                        <img src="../svg/语音播放.svg">
                    </button>
                    <button class="action-btn" onclick="copyContent(<?= $index ?>)">
                        <img src="../svg/复制.svg">
                    </button>
                    <button class="action-btn" onclick="regenerateReply(<?= $index ?>)">
                        <img src="../svg/刷新.svg">
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="input-area">
        <button class="clear-btn" id="clear-btn">
            <img src="../svg/删除.svg">
        </button>
        <div class="input-wrapper">
            <div class="uploaded-file-container" id="uploadedFileContainer" style="display:none">
                <span id="fileNameText"></span>
                <button class="remove-file-btn" id="removeFileBtn">×</button>
            </div>
            <textarea id="question" placeholder="请输入问题"></textarea>
            <label class="input-btn upload-btn" for="file-upload">
                <img src="../svg/上传.svg">
            </label>
            <input type="file" id="file-upload" accept=".txt,.md,.html">
            <button class="input-btn send-btn" id="send-btn">
                <img src="../svg/发送.svg">
            </button>
        </div>
    </div>
</div>

<script>
    const chatMessages = document.getElementById('chat-messages');
    const questionInput = document.getElementById('question');
    const sendBtn = document.getElementById('send-btn');
    const clearBtn = document.getElementById('clear-btn');
    const fileUpload = document.getElementById('file-upload');
    const uploadedFileContainer = document.getElementById('uploadedFileContainer');
    const fileNameText = document.getElementById('fileNameText');
    const removeFileBtn = document.getElementById('removeFileBtn');

    let editIndex = -1;
    let loadingAnimation = null;
    let selectedFile = null;
    let typingIntervals = {};
    let audioPlayers = {};
    let audioCache = {};
    let isVoiceGenerating = false;

    // 苹果风格提示
    function showToast(text) {
        let toast = document.querySelector('.apple-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'apple-toast';
            document.body.appendChild(toast);
        }
        toast.textContent = text;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 1500);
    }

    // fetch 超时封装
    function fetchWithTimeout(url, options, timeout = 120) {
        return Promise.race([
            fetch(url, options),
            new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), timeout * 1000))
        ]);
    }

    // 输入框高度（仅换行变高）
    function autoResizeTextarea(el) {
        if (!el.value.includes('\n')) {
            el.style.height = '36px';
            return;
        }
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    }

    // 文件选择
    fileUpload.addEventListener('change', e => {
        const f = e.target.files[0];
        if (f) {
            selectedFile = f;
            fileNameText.textContent = `文件：${f.name}`;
            uploadedFileContainer.style.display = 'flex';
        }
    });
    removeFileBtn.onclick = () => {
        selectedFile = null;
        fileUpload.value = '';
        uploadedFileContainer.style.display = 'none';
    };

    // 思考动画
    function createLoadingAnimation(container) {
        container.innerHTML = `
            <div class="loading-wrapper">
                <span class="thinking-text">思考ing</span>
                <div class="loading-container"></div>
            </div>
        `;
        loadingAnimation = lottie.loadAnimation({
            container: container.querySelector('.loading-container'),
            renderer: 'svg',
            loop: true,
            autoplay: true,
            path: '../lottie/下载加载.json'
        });
    }
    function destroyLoadingAnimation() {
        if (loadingAnimation) {
            loadingAnimation.destroy();
            loadingAnimation = null;
        }
    }

    // 打字机
    function typeWriterEffect(text, el, index) {
        if (typingIntervals[index]) clearInterval(typingIntervals[index]);
        let i = 0;
        el.innerHTML = '';
        const speed = Math.floor(Math.random() * 134) + 66;
        typingIntervals[index] = setInterval(() => {
            if (i < text.length) {
                el.innerHTML += text.charAt(i);
                i++;
                chatMessages.scrollTop = chatMessages.scrollHeight;
            } else {
                clearInterval(typingIntervals[index]);
                const acts = el.closest('.message-content-wrapper').querySelector('.message-actions');
                if (acts) acts.style.display = 'flex';
            }
        }, speed);
    }

    // 语音播放（带缓存+全局锁）
    async function playVoice(index) {
        if (isVoiceGenerating) {
            showToast("服务器繁忙");
            return;
        }
        const el = document.getElementById(`ai-content-${index}`);
        const btn = el.closest('.message-content-wrapper').querySelector('.voice-btn');
        const text = el.textContent.trim();
        if (!text) return;

        // 正在播放则停止
        if (audioPlayers[index]) {
            audioPlayers[index].pause();
            audioPlayers[index] = null;
            return;
        }

        // 有缓存直接播
        if (audioCache[index]) {
            const audio = new Audio(audioCache[index]);
            audioPlayers[index] = audio;
            audio.play();
            audio.onended = () => audioPlayers[index] = null;
            return;
        }

        // 全局锁
        isVoiceGenerating = true;
        document.querySelectorAll('.voice-btn').forEach(b => b.disabled = true);
        showToast("正在生成语音");

        try {
            const t = text.replace(/\s+/g, ',');
            const res = await fetchWithTimeout(
                `https://yunzhiapi.cn/API/saiyysc.php?msg=${encodeURIComponent(t)}`,
                {},
                120
            );
            const data = await res.json();
            if (data?.data?.voice) {
                audioCache[index] = data.data.voice;
                const audio = new Audio(data.data.voice);
                audioPlayers[index] = audio;
                audio.play();
                audio.onended = () => audioPlayers[index] = null;
            } else {
                console.error("语音API返回异常", data);
                showToast("服务器繁忙");
            }
        } catch (err) {
            console.error("语音错误", err);
            showToast("服务器繁忙");
        } finally {
            isVoiceGenerating = false;
            document.querySelectorAll('.voice-btn').forEach(b => b.disabled = false);
        }
    }

    // 发送
    async function sendMessage() {
        const q = questionInput.value.trim();
        if (!q && !selectedFile) {
            showToast("请输入内容");
            return;
        }
        sendBtn.disabled = true;
        const time = new Date().toLocaleString('zh-CN').replace(/\//g, '-');

        if (editIndex >= 0) {
            const items = chatMessages.querySelectorAll('.message-item');
            for (let i = items.length - 1; i >= editIndex * 2; i--) {
                chatMessages.removeChild(items[i]);
            }
            editIndex = -1;
        }

        const newIndex = Math.floor(chatMessages.querySelectorAll('.message-item').length / 2);
        const fname = selectedFile ? selectedFile.name : '';

        // 用户消息
        const userHtml = `
            <div class="message-item user-message" data-index="${newIndex}">
                <div class="message-content-wrapper">
                    <div class="message-time">${time}</div>
                    <div class="message-bubble">${q}${fname ? '<br>文件：'+fname : ''}</div>
                    <div class="message-actions">
                        <button class="action-btn" onclick="editMessage(${newIndex})">
                            <img src="../svg/编辑.svg">
                        </button>
                    </div>
                </div>
                <div class="avatar-container">
                    <div class="avatar"><img src="../svg/用户.svg"></div>
                </div>
            </div>
        `;
        chatMessages.insertAdjacentHTML('beforeend', userHtml);

        // AI加载
        const aiHtml = `
            <div class="message-item ai-message" data-index="${newIndex}" id="ai-loading-${newIndex}">
                <div class="avatar-container">
                    <div class="avatar"><img src="../svg/chatgpt.svg"></div>
                </div>
                <div class="message-content-wrapper">
                    <div class="message-time">${time}</div>
                    <div class="message-bubble" id="ai-content-${newIndex}"></div>
                    <div class="message-actions" style="display:none">
                        <button class="action-btn voice-btn" onclick="playVoice(${newIndex})">
                            <img src="../svg/语音播放.svg">
                        </button>
                        <button class="action-btn" onclick="copyContent(${newIndex})">
                            <img src="../svg/复制.svg">
                        </button>
                        <button class="action-btn" onclick="regenerateReply(${newIndex})">
                            <img src="../svg/刷新.svg">
                        </button>
                    </div>
                </div>
            </div>
        `;
        chatMessages.insertAdjacentHTML('beforeend', aiHtml);
        const bubble = document.getElementById(`ai-content-${newIndex}`);
        createLoadingAnimation(bubble);

        questionInput.value = '';
        selectedFile = null;
        fileUpload.value = '';
        uploadedFileContainer.style.display = 'none';
        questionInput.style.height = '36px';
        chatMessages.scrollTop = chatMessages.scrollHeight;

        const fd = new FormData();
        fd.append('action', 'chat');
        fd.append('question', q);
        fd.append('edit_index', newIndex);
        if (selectedFile) fd.append('chat_file', selectedFile);

        try {
            const resp = await fetchWithTimeout('', { method: 'POST', body: fd }, 180);
            const data = await resp.json();
            destroyLoadingAnimation();
            if (data.status === 'success') {
                typeWriterEffect(data.ai_reply || '服务器繁忙', bubble, newIndex);
            } else {
                typeWriterEffect('服务器繁忙', bubble, newIndex);
            }
        } catch (err) {
            console.error("对话超时/错误", err);
            destroyLoadingAnimation();
            typeWriterEffect('服务器繁忙', bubble, newIndex);
        } finally {
            sendBtn.disabled = false;
        }
    }

    // 复制
    function copyContent(index) {
        const t = document.getElementById(`ai-content-${index}`).textContent;
        navigator.clipboard.writeText(t);
        showToast("已复制");
    }

    // 重新生成
    async function regenerateReply(index) {
        const btn = event.target.closest('.action-btn');
        btn.disabled = true;
        const bubble = document.getElementById(`ai-content-${index}`);
        createLoadingAnimation(bubble);
        bubble.closest('.message-content-wrapper').querySelector('.message-actions').style.display = 'none';

        try {
            const fd = new FormData();
            fd.append('action', 'regenerate');
            fd.append('index', index);
            const resp = await fetchWithTimeout('', { method: 'POST', body: fd }, 180);
            const data = await resp.json();
            destroyLoadingAnimation();
            if (data.status === 'success') {
                typeWriterEffect(data.ai_reply, bubble, index);
            } else {
                typeWriterEffect('服务器繁忙', bubble, index);
            }
        } catch (err) {
            console.error("重生成错误", err);
            destroyLoadingAnimation();
            typeWriterEffect('服务器繁忙', bubble, index);
        } finally {
            btn.disabled = false;
        }
    }

    // 编辑
    function editMessage(index) {
        const item = document.querySelector(`.user-message[data-index="${index}"]`);
        const t = item.querySelector('.message-bubble').textContent.replace(/文件：.+/, '').trim();
        questionInput.value = t;
        autoResizeTextarea(questionInput);
        editIndex = index;
        questionInput.focus();
    }

    // 清空
    clearBtn.onclick = async () => {
        if (!confirm("确定清空所有记录？")) return;
        for (let k in audioPlayers) if (audioPlayers[k]) audioPlayers[k].pause();
        audioPlayers = {};
        audioCache = {};
        for (let k in typingIntervals) clearInterval(typingIntervals[k]);
        typingIntervals = {};

        const fd = new FormData();
        fd.append('action', 'clear');
        await fetch('', { method: 'POST', body: fd });
        chatMessages.innerHTML = '';
        showToast("已清空");
    };

    sendBtn.onclick = sendMessage;
    questionInput.addEventListener('input', () => autoResizeTextarea(questionInput));
    questionInput.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
</script>
</body>
</html>
