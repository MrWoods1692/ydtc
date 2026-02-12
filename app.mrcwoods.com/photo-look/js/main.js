// 全局变量
let scale = 1;
let rotation = 0; // 旋转角度
let isDragging = false;
let startX, startY, offsetX = 0, offsetY = 0;
let repairTimer = null;
let recognizeTimer = null;
let extractTimer = null;
let translateTimer = null; // 新增：翻译定时器
// Lottie实例存储
let lottieInstances = {
    repair: null,
    recognize: null,
    extract: null
};

// DOM元素
const elements = {
    // 模态框
    modalInfo: document.getElementById('modal-info'),
    modalRename: document.getElementById('modal-rename'),
    modalRemark: document.getElementById('modal-remark'),
    modalAiRepair: document.getElementById('modal-ai-repair'),
    modalAiRecognize: document.getElementById('modal-ai-recognize'),
    modalExtractText: document.getElementById('modal-extract-text'),
    
    // 按钮
    btnInfo: document.getElementById('btn-info'),
    btnRename: document.getElementById('btn-rename'),
    btnRemark: document.getElementById('btn-remark'),
    btnCopy: document.getElementById('btn-copy'),
    btnZoomIn: document.getElementById('btn-zoom-in'),
    btnZoomOut: document.getElementById('btn-zoom-out'),
    btnRotateLeft: document.getElementById('btn-rotate-left'),
    btnRotateRight: document.getElementById('btn-rotate-right'),
    btnReset: document.getElementById('btn-reset'),
    btnPrint: document.getElementById('btn-print'), // 新增打印按钮
    btnDownload: document.getElementById('btn-download'),
    btnAiRepair: document.getElementById('btn-ai-repair'),
    btnAiRecognize: document.getElementById('btn-ai-recognize'),
    btnExtractText: document.getElementById('btn-extract-text'),
    
    // 新增：AI识图/提取文字手动触发按钮
    startRecognize: document.getElementById('startRecognize'),
    startExtract: document.getElementById('startExtract'),
    
    // 新增：翻译功能元素
    targetLanguage: document.getElementById('targetLanguage'),
    translateText: document.getElementById('translateText'),
    translateArea: document.getElementById('translateArea'),
    translateResult: document.getElementById('translateResult'),
    
    // 其他
    mainPhoto: document.getElementById('mainPhoto'),
    photoContainer: document.getElementById('photoContainer'),
    toast: document.getElementById('toast'),
    
    // AI修图相关
    repairPrompt: document.getElementById('repairPrompt'),
    repairProgressContainer: document.getElementById('repairProgressContainer'),
    repairProgressBar: document.getElementById('repairProgressBar'),
    repairProgressText: document.getElementById('repairProgressText'),
    repairLoading: document.getElementById('repairLoading'),
    repairResult: document.getElementById('repairResult'),
    confirmRepair: document.getElementById('confirmRepair'),
    cancelRepair: document.getElementById('cancelRepair'),
    
    // AI识图相关
    recognizeProgressContainer: document.getElementById('recognizeProgressContainer'),
    recognizeProgressBar: document.getElementById('recognizeProgressBar'),
    recognizeProgressText: document.getElementById('recognizeProgressText'),
    recognizeLoading: document.getElementById('recognizeLoading'),
    recognizeResult: document.getElementById('recognizeResult'),
    refreshRecognize: document.getElementById('refreshRecognize'),
    
    // 提取文字相关
    extractProgressContainer: document.getElementById('extractProgressContainer'),
    extractProgressBar: document.getElementById('extractProgressBar'),
    extractProgressText: document.getElementById('extractProgressText'),
    extractLoading: document.getElementById('extractLoading'),
    extractResult: document.getElementById('extractResult'),
    copyExtractText: document.getElementById('copyExtractText'),
    
    // 重命名/备注
    newNameInput: document.getElementById('newNameInput'),
    newRemarkInput: document.getElementById('newRemarkInput'),
    confirmRename: document.getElementById('confirmRename'),
    confirmRemark: document.getElementById('confirmRemark'),
    cancelBtns: document.querySelectorAll('.cancel-btn'),
    
    // Lottie容器
    repairLottie: document.getElementById('repairLottie'),
    recognizeLottie: document.getElementById('recognizeLottie'),
    extractLottie: document.getElementById('extractLottie')
};

// 初始化Lottie动画 - 修正路径为JSON
function initLottie() {
    // 检查lottie是否加载
    if (typeof lottie === 'undefined') {
        console.error('Lottie库未加载');
        return;
    }
    
    // 初始化AI修图Lottie - 修正路径为JSON
    lottieInstances.repair = lottie.loadAnimation({
        container: elements.repairLottie,
        renderer: 'svg',
        loop: true,
        autoplay: false,
        path: '../lottie/下载加载.json'
    });
    
    // 初始化AI识图Lottie - 修正路径为JSON
    lottieInstances.recognize = lottie.loadAnimation({
        container: elements.recognizeLottie,
        renderer: 'svg',
        loop: true,
        autoplay: false,
        path: '../lottie/下载加载.json'
    });
    
    // 初始化文字提取Lottie - 修正路径为JSON
    lottieInstances.extract = lottie.loadAnimation({
        container: elements.extractLottie,
        renderer: 'svg',
        loop: true,
        autoplay: false,
        path: '../lottie/下载加载.json'
    });
}

// 初始化
function init() {
    // 初始化Lottie
    initLottie();
    // 绑定事件
    bindEvents();
    // 初始化图片拖拽（优化版）
    initDrag();
    // 绑定快捷键
    bindShortcuts();
    // 绑定滚轮缩放
    bindWheelZoom();
}

// 事件绑定 - 新增打印按钮绑定
function bindEvents() {
    // 模态框关闭按钮
    document.querySelectorAll('.close-btn').forEach(btn => {
        btn.addEventListener('click', closeAllModals);
    });
    
    // 取消按钮
    elements.cancelBtns.forEach(btn => {
        btn.addEventListener('click', closeAllModals);
    });
    
    // 点击模态框外部关闭
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeAllModals();
        }
    });
    
    // 功能按钮点击事件
    elements.btnInfo.addEventListener('click', () => openModal(elements.modalInfo));
    elements.btnRename.addEventListener('click', () => openModal(elements.modalRename));
    elements.btnRemark.addEventListener('click', () => openModal(elements.modalRemark));
    elements.btnCopy.addEventListener('click', copyPhotoUrl);
    elements.btnZoomIn.addEventListener('click', zoomIn);
    elements.btnZoomOut.addEventListener('click', zoomOut);
    elements.btnRotateLeft.addEventListener('click', rotateLeft);
    elements.btnRotateRight.addEventListener('click', rotateRight);
    elements.btnReset.addEventListener('click', resetImage);
    elements.btnPrint.addEventListener('click', printPhoto); // 绑定打印功能
    elements.btnDownload.addEventListener('click', downloadPhoto);
    elements.btnAiRepair.addEventListener('click', () => openModal(elements.modalAiRepair));
    
    // 修改：AI识图仅打开模态框，不自动识别
    elements.btnAiRecognize.addEventListener('click', () => {
        openModal(elements.modalAiRecognize);
    });
    
    // 修改：提取文字仅打开模态框，不自动提取
    elements.btnExtractText.addEventListener('click', () => {
        openModal(elements.modalExtractText);
    });
    
    // 新增：绑定AI识图手动触发按钮
    elements.startRecognize.addEventListener('click', aiRecognize);
    elements.refreshRecognize.addEventListener('click', aiRecognize);
    
    // 新增：绑定提取文字手动触发按钮
    elements.startExtract.addEventListener('click', extractText);
    
    // 新增：绑定翻译按钮事件
    elements.translateText.addEventListener('click', translateExtractText);
    
    // 重命名确认
    elements.confirmRename.addEventListener('click', updateName);
    
    // 备注确认
    elements.confirmRemark.addEventListener('click', updateRemark);
    
    // AI修图确认/取消
    elements.confirmRepair.addEventListener('click', startAiRepair);
    elements.cancelRepair.addEventListener('click', cancelAiRepair);
    
    // 复制提取文字
    elements.copyExtractText.addEventListener('click', copyExtractText);
}

// 绑定滚轮缩放 - 新增核心功能
function bindWheelZoom() {
    elements.photoContainer.addEventListener('wheel', function(e) {
        // 阻止默认滚动行为
        e.preventDefault();
        
        // 获取鼠标位置
        const mouseX = e.clientX;
        const mouseY = e.clientY;
        
        // 获取图片当前位置和尺寸
        const rect = elements.mainPhoto.getBoundingClientRect();
        const imgX = rect.left + rect.width / 2;
        const imgY = rect.top + rect.height / 2;
        
        // 计算鼠标相对于图片中心的偏移
        const offsetFromImgX = mouseX - imgX;
        const offsetFromImgY = mouseY - imgY;
        
        // 计算缩放增量（滚轮向上为放大，向下为缩小）
        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        const newScale = Math.max(0.1, Math.min(scale + delta, 5));
        
        // 计算缩放比例
        const scaleRatio = newScale / scale;
        
        // 更新偏移量，使缩放围绕鼠标位置进行
        offsetX = (offsetX + offsetFromImgX) * scaleRatio - offsetFromImgX;
        offsetY = (offsetY + offsetFromImgY) * scaleRatio - offsetFromImgY;
        
        // 更新缩放比例
        scale = newScale;
        
        // 应用变换
        updateImageTransform();
        
        // 显示缩放提示
        showToast(`缩放：${(scale * 100).toFixed(0)}%`, 800);
    }, { passive: false }); // 必须设置passive: false才能阻止默认行为
}

// 绑定快捷键
function bindShortcuts() {
    document.addEventListener('keydown', function(e) {
        // 忽略输入框/文本域中的快捷键
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        
        switch(e.key) {
            case '+':
            case '=': // 兼容数字键盘
                zoomIn();
                e.preventDefault();
                break;
            case '-':
                zoomOut();
                e.preventDefault();
                break;
            case ' ':
                resetImage();
                e.preventDefault();
                break;
            case 'p': // 新增打印快捷键
                printPhoto();
                e.preventDefault();
                break;
        }
    });
}

// 初始化图片拖拽（优化版 - 更顺滑的拖动体验）
function initDrag() {
    // PC端拖拽
    elements.photoContainer.addEventListener('mousedown', startDrag);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', stopDrag);
    document.addEventListener('mouseleave', stopDrag);
    
    // 移动端拖拽 - 优化触摸体验
    elements.photoContainer.addEventListener('touchstart', (e) => {
        const touch = e.touches[0];
        startX = touch.clientX;
        startY = touch.clientY;
        isDragging = true;
        // 禁止浏览器默认行为
        e.preventDefault();
        // 暂停过渡，让拖动更跟手
        elements.mainPhoto.style.transition = 'none';
    });
    
    document.addEventListener('touchmove', (e) => {
        if (!isDragging) return;
        const touch = e.touches[0];
        const x = touch.clientX;
        const y = touch.clientY;
        const dx = x - startX;
        const dy = y - startY;
        
        updateImagePosition(dx, dy);
        e.preventDefault();
    });
    
    document.addEventListener('touchend', stopDrag);
    document.addEventListener('touchcancel', stopDrag);
}

// 开始拖拽 - 优化
function startDrag(e) {
    isDragging = true;
    startX = e.clientX;
    startY = e.clientY;
    
    // 获取当前偏移（优化：从transform中精准解析）
    const transform = window.getComputedStyle(elements.mainPhoto).transform;
    const matrix = new DOMMatrix(transform);
    offsetX = matrix.e;
    offsetY = matrix.f;
    
    elements.photoContainer.style.cursor = 'grabbing';
    // 暂停过渡，让拖动更跟手
    elements.mainPhoto.style.transition = 'none';
    // 提升图片层级，避免被遮挡
    elements.mainPhoto.style.zIndex = '10';
}

// 拖拽中 - 优化（增加平滑度）
function drag(e) {
    if (!isDragging) return;
    
    const x = e.clientX;
    const y = e.clientY;
    const dx = x - startX;
    const dy = y - startY;
    
    updateImagePosition(dx, dy);
}

// 更新图片位置 - 精准计算
function updateImagePosition(dx, dy) {
    elements.mainPhoto.style.transform = `translate(${offsetX + dx}px, ${offsetY + dy}px) scale(${scale}) rotate(${rotation}deg)`;
}

// 停止拖拽 - 优化
function stopDrag() {
    if (!isDragging) return;
    
    isDragging = false;
    elements.photoContainer.style.cursor = 'move';
    // 恢复过渡动画
    elements.mainPhoto.style.transition = 'transform 0.2s ease';
    // 恢复层级
    elements.mainPhoto.style.zIndex = '1';
    
    // 更新全局偏移量（精准解析最终位置）
    const transform = window.getComputedStyle(elements.mainPhoto).transform;
    const matrix = new DOMMatrix(transform);
    offsetX = matrix.e;
    offsetY = matrix.f;
}

// 打开模态框
function openModal(modal) {
    closeAllModals();
    modal.style.display = 'flex';
    // 重置模态框内的进度和结果
    if (modal === elements.modalAiRecognize) {
        resetRecognizeState();
    }
    if (modal === elements.modalExtractText) {
        resetExtractState();
        // 隐藏翻译区域
        elements.translateArea.style.display = 'none';
        elements.translateResult.innerHTML = '';
    }
}

// 关闭所有模态框
function closeAllModals() {
    elements.modalInfo.style.display = 'none';
    elements.modalRename.style.display = 'none';
    elements.modalRemark.style.display = 'none';
    elements.modalAiRepair.style.display = 'none';
    elements.modalAiRecognize.style.display = 'none';
    elements.modalExtractText.style.display = 'none';
    
    // 停止所有Lottie动画
    Object.values(lottieInstances).forEach(instance => {
        if (instance) instance.stop();
    });
    
    // 重置所有进度状态
    cancelAiRepair();
    resetRecognizeState();
    resetExtractState();
    // 重置翻译状态
    resetTranslateState();
}

// 重置识图状态
function resetRecognizeState() {
    if (recognizeTimer) {
        clearInterval(recognizeTimer);
        recognizeTimer = null;
    }
    elements.recognizeProgressContainer.style.display = 'none';
    elements.recognizeLoading.style.display = 'none';
    elements.recognizeProgressBar.style.width = '0%';
    elements.recognizeProgressText.textContent = '0%';
    elements.recognizeResult.innerHTML = '';
    // 停止Lottie
    if (lottieInstances.recognize) lottieInstances.recognize.stop();
}

// 重置文字提取状态
function resetExtractState() {
    if (extractTimer) {
        clearInterval(extractTimer);
        extractTimer = null;
    }
    elements.extractProgressContainer.style.display = 'none';
    elements.extractLoading.style.display = 'none';
    elements.extractProgressBar.style.width = '0%';
    elements.extractProgressText.textContent = '0%';
    elements.extractResult.innerHTML = '';
    // 停止Lottie
    if (lottieInstances.extract) lottieInstances.extract.stop();
}

// 新增：重置翻译状态
function resetTranslateState() {
    if (translateTimer) {
        clearInterval(translateTimer);
        translateTimer = null;
    }
    elements.translateResult.innerHTML = '';
}

// 显示提示框
function showToast(msg, duration = 3000) {
    elements.toast.textContent = msg;
    elements.toast.classList.add('show');
    
    setTimeout(() => {
        elements.toast.classList.remove('show');
    }, duration);
}

// 解析接口错误
function parseApiError(responseText) {
    try {
        const errorObj = JSON.parse(responseText);
        // 判断是否是指定格式的错误
        if (errorObj.code && errorObj.message && errorObj.timestamp) {
            return true; // 是指定错误格式
        }
        return false;
    } catch (e) {
        return false; // 不是JSON格式
    }
}

// 复制图片链接
function copyPhotoUrl() {
    const input = document.createElement('input');
    input.value = PHOTO_URL;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    showToast('链接复制成功', 1000);
}

// 放大图片
function zoomIn() {
    scale = Math.min(scale + 0.1, 5); // 最大缩放5倍
    updateImageTransform();
    showToast(`缩放：${(scale * 100).toFixed(0)}%`, 1000);
}

// 缩小图片
function zoomOut() {
    scale = Math.max(scale - 0.1, 0.1); // 最小缩放0.1倍
    updateImageTransform();
    showToast(`缩放：${(scale * 100).toFixed(0)}%`, 1000);
}

// 逆时针旋转
function rotateLeft() {
    rotation -= 90;
    updateImageTransform();
    showToast('逆时针旋转90°', 1000);
}

// 顺时针旋转
function rotateRight() {
    rotation += 90;
    updateImageTransform();
    showToast('顺时针旋转90°', 1000);
}

// 重置图片
function resetImage() {
    scale = 1;
    rotation = 0;
    offsetX = 0;
    offsetY = 0;
    updateImageTransform();
    showToast('图片已重置', 1000);
}

// 更新图片变换 - 优化性能
function updateImageTransform() {
    elements.mainPhoto.style.transform = `translate(${offsetX}px, ${offsetY}px) scale(${scale}) rotate(${rotation}deg)`;
}

// 下载图片
function downloadPhoto() {
    const a = document.createElement('a');
    a.href = PHOTO_URL;
    a.download = PHOTO_URL.split('/').pop() || 'photo.jpg';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    showToast('开始下载图片', 1000);
}

// 打印图片
function printPhoto() {
    try {
        // 识别浏览器类型
        const browserInfo = getBrowserInfo();
        console.log('打印操作 - 浏览器信息：', browserInfo);
        
        // 重置图片位置和大小，确保打印效果
        const currentTransform = elements.mainPhoto.style.transform;
        elements.mainPhoto.style.transform = 'scale(1) rotate(0deg) translate(0, 0)';
        
        // 延迟执行打印，确保样式生效
        setTimeout(() => {
            // 调用浏览器打印功能
            window.print();
            
            // 恢复图片变换
            setTimeout(() => {
                elements.mainPhoto.style.transform = currentTransform;
            }, 100);
            
            showToast('打印预览已打开', 1500);
        }, 200);
        
    } catch (e) {
        showToast('打印功能调用失败：' + e.message, 2000);
        console.error('打印错误：', e);
    }
}

// 获取浏览器信息
function getBrowserInfo() {
    const userAgent = navigator.userAgent.toLowerCase();
    let browser = 'unknown';
    
    if (userAgent.includes('msie') || userAgent.includes('trident')) {
        browser = 'IE';
    } else if (userAgent.includes('edge')) {
        browser = 'Edge';
    } else if (userAgent.includes('chrome')) {
        browser = 'Chrome';
    } else if (userAgent.includes('firefox')) {
        browser = 'Firefox';
    } else if (userAgent.includes('safari')) {
        browser = 'Safari';
    } else if (userAgent.includes('opera') || userAgent.includes('opr')) {
        browser = 'Opera';
    }
    
    return {
        name: browser,
        version: getBrowserVersion(userAgent, browser),
        userAgent: userAgent
    };
}

// 获取浏览器版本
function getBrowserVersion(userAgent, browser) {
    let version = 'unknown';
    
    switch(browser) {
        case 'Chrome':
            version = userAgent.match(/chrome\/(\d+)/)[1] || 'unknown';
            break;
        case 'Firefox':
            version = userAgent.match(/firefox\/(\d+)/)[1] || 'unknown';
            break;
        case 'Safari':
            version = userAgent.match(/version\/(\d+)/)[1] || 'unknown';
            break;
        case 'Edge':
            version = userAgent.match(/edge\/(\d+)/)[1] || 'unknown';
            break;
        case 'IE':
            version = userAgent.match(/msie (\d+)/)[1] || userAgent.match(/rv:(\d+)/)[1] || 'unknown';
            break;
        case 'Opera':
            version = userAgent.match(/opera\/(\d+)/)[1] || userAgent.match(/opr\/(\d+)/)[1] || 'unknown';
            break;
    }
    
    return version;
}

// 更新图片名称
function updateName() {
    const newName = elements.newNameInput.value.trim();
    if (!newName) {
        showToast('文件名不能为空', 2000);
        return;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=rename&newName=${encodeURIComponent(newName)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('重命名成功', 1000);
            closeAllModals();
            // 刷新页面
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('重命名失败：' + data.msg, 2000);
        }
    })
    .catch(err => {
        showToast('请求失败：' + err.message, 2000);
    });
}

// 更新备注
function updateRemark() {
    const newRemark = elements.newRemarkInput.value.trim();
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_remark&newRemark=${encodeURIComponent(newRemark)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('备注修改成功', 1000);
            closeAllModals();
            // 刷新页面
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('备注修改失败：' + data.msg, 2000);
        }
    })
    .catch(err => {
        showToast('请求失败：' + err.message, 2000);
    });
}

// 开始AI修图
function startAiRepair() {
    const prompt = elements.repairPrompt.value.trim();
    if (!prompt) {
        showToast('请输入修图提示词', 2000);
        return;
    }
    
    // 重置状态
    elements.repairProgressContainer.style.display = 'block';
    elements.repairLoading.style.display = 'flex';
    elements.repairResult.style.display = 'none';
    elements.repairProgressBar.style.width = '0%';
    elements.repairProgressText.textContent = '0%';
    
    // 启动Lottie动画
    if (lottieInstances.repair) {
        lottieInstances.repair.play();
    }
    
    // 模拟进度
    let elapsed = 0;
    repairTimer = setInterval(() => {
        elapsed += 500;
        const progress = Math.min((elapsed / AI_REPAIR_PROGRESS_MAX) * 100, 100);
        elements.repairProgressBar.style.width = `${progress}%`;
        elements.repairProgressText.textContent = `${Math.round(progress)}%`;
        
        if (progress >= 100) {
            clearInterval(repairTimer);
        }
    }, 500);
    
    // 发送修图请求
    fetch('https://yunzhiapi.cn/API/nano-banana/pro.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `msg=${encodeURIComponent(prompt)}&url=${encodeURIComponent(PHOTO_URL)}`,
        timeout: AI_REPAIR_TIMEOUT
    })
    .then(res => {
        // 先获取文本内容，再判断是否是错误格式
        return res.text().then(text => {
            return {
                ok: res.ok,
                status: res.status,
                text: text
            };
        });
    })
    .then(data => {
        clearInterval(repairTimer);
        elements.repairProgressContainer.style.display = 'none';
        elements.repairLoading.style.display = 'none';
        elements.repairResult.style.display = 'block';
        // 停止Lottie动画
        if (lottieInstances.repair) lottieInstances.repair.stop();
        
        // 检查是否是指定错误格式
        if (parseApiError(data.text)) {
            // 显示友好提示
            elements.repairResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器繁忙，请稍后重试</p>`;
            showToast('服务器繁忙，请稍后重试', 2000);
            return;
        }
        
        // 正常解析JSON
        let responseObj;
        try {
            responseObj = JSON.parse(data.text);
        } catch (e) {
            elements.repairResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器返回数据格式错误</p>`;
            showToast('修图失败', 2000);
            return;
        }
        
        if (responseObj.status === 'success' && responseObj.image_url) {
            elements.repairResult.innerHTML = `
                <p style="color: #2ecc71; font-weight: 500; margin-bottom: 10px;">修图成功！</p>
                <img src="${responseObj.image_url}" alt="修图结果" style="max-width: 100%; margin: 10px 0; border-radius: 6px;">
                <a href="${responseObj.image_url}" download="ai_repair_${Date.now()}.jpg" class="modal-btn confirm-btn" style="margin-top: 10px;">下载修图结果</a>
            `;
            showToast('修图成功', 1500);
        } else {
            elements.repairResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">修图失败：${responseObj.msg || '未知错误'}</p>`;
            showToast('修图失败', 2000);
        }
    })
    .catch(err => {
        clearInterval(repairTimer);
        elements.repairProgressContainer.style.display = 'none';
        elements.repairLoading.style.display = 'none';
        elements.repairResult.style.display = 'block';
        // 停止Lottie动画
        if (lottieInstances.repair) lottieInstances.repair.stop();
        
        elements.repairResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器繁忙，请稍后重试</p>`;
        showToast('修图请求失败', 2000);
    });
}

// 取消AI修图
function cancelAiRepair() {
    if (repairTimer) {
        clearInterval(repairTimer);
        repairTimer = null;
    }
    
    elements.repairProgressContainer.style.display = 'none';
    elements.repairLoading.style.display = 'none';
    elements.repairResult.style.display = 'none';
    elements.repairProgressBar.style.width = '0%';
    // 停止Lottie动画
    if (lottieInstances.repair) lottieInstances.repair.stop();
}

// AI识图 - 优化：过滤<|beginofbox|>标识 + 手动触发
function aiRecognize() {
    resetRecognizeState();
    
    // 显示进度条和加载动画
    elements.recognizeProgressContainer.style.display = 'block';
    elements.recognizeLoading.style.display = 'flex';
    
    // 启动Lottie动画
    if (lottieInstances.recognize) {
        lottieInstances.recognize.play();
    }
    
    // 模拟进度
    let elapsed = 0;
    recognizeTimer = setInterval(() => {
        elapsed += 300;
        const progress = Math.min((elapsed / 180000) * 100, 100); // 180秒满进度
        elements.recognizeProgressBar.style.width = `${progress}%`;
        elements.recognizeProgressText.textContent = `${Math.round(progress)}%`;
    }, 300);
    
    // 构建请求URL
    const url = `https://yunzhiapi.cn/API/glm4.6-ocr.php?question=请识别图片内容并对图片进行详细描述&image=${encodeURIComponent(PHOTO_URL)}`;
    
    fetch(url)
    .then(res => {
        // 先获取文本内容，再判断是否是错误格式
        return res.text().then(text => {
            return {
                ok: res.ok,
                status: res.status,
                text: text
            };
        });
    })
    .then(data => {
        clearInterval(recognizeTimer);
        elements.recognizeProgressContainer.style.display = 'none';
        elements.recognizeLoading.style.display = 'none';
        // 停止Lottie动画
        if (lottieInstances.recognize) lottieInstances.recognize.stop();
        
        // 检查是否是指定错误格式
        if (parseApiError(data.text)) {
            // 显示友好提示
            elements.recognizeResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器繁忙，请稍后重试</p>`;
            showToast('服务器繁忙，请稍后重试', 2000);
            return;
        }
        
        // 优化：过滤<|beginofbox|>和<|endofbox|>标识
        let resultText = data.text.trim();
        resultText = resultText.replace(/<\|beginofbox\|>/g, '').replace(/<\|endofbox\|>/g, '').trim();
        
        if (!resultText) {
            resultText = '未识别到图片内容';
        }
        
        elements.recognizeResult.textContent = resultText;
        showToast('识图完成', 1500);
    })
    .catch(err => {
        clearInterval(recognizeTimer);
        elements.recognizeProgressContainer.style.display = 'none';
        elements.recognizeLoading.style.display = 'none';
        // 停止Lottie动画
        if (lottieInstances.recognize) lottieInstances.recognize.stop();
        
        elements.recognizeResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器繁忙，请稍后重试</p>`;
        showToast('识图失败', 2000);
    });
}

// 提取文字 - 优化：过滤<|beginofbox|>标识 + 手动触发 + 显示翻译区域
function extractText() {
    resetExtractState();
    
    // 显示进度条和加载动画
    elements.extractProgressContainer.style.display = 'block';
    elements.extractLoading.style.display = 'flex';
    
    // 启动Lottie动画
    if (lottieInstances.extract) {
        lottieInstances.extract.play();
    }
    
    // 模拟进度
    let elapsed = 0;
    extractTimer = setInterval(() => {
        elapsed += 300;
        const progress = Math.min((elapsed / 3000) * 100, 100); // 3秒满进度
        elements.extractProgressBar.style.width = `${progress}%`;
        elements.extractProgressText.textContent = `${Math.round(progress)}%`;
    }, 300);
    
    // 构建请求URL
    const url = `https://yunzhiapi.cn/API/glm4.6-ocr.php?question=请识别图片内容并提取图片文字&image=${encodeURIComponent(PHOTO_URL)}`;
    
    fetch(url)
    .then(res => {
        // 先获取文本内容，再判断是否是错误格式
        return res.text().then(text => {
            return {
                ok: res.ok,
                status: res.status,
                text: text
            };
        });
    })
    .then(data => {
        clearInterval(extractTimer);
        elements.extractProgressContainer.style.display = 'none';
        elements.extractLoading.style.display = 'none';
        // 停止Lottie动画
        if (lottieInstances.extract) lottieInstances.extract.stop();
        
        // 检查是否是指定错误格式
        if (parseApiError(data.text)) {
            // 显示友好提示
            elements.extractResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器繁忙，请稍后重试</p>`;
            showToast('服务器繁忙，请稍后重试', 2000);
            return;
        }
        
        // 优化：过滤<|beginofbox|>和<|endofbox|>标识
        let resultText = data.text.trim();
        resultText = resultText.replace(/<\|beginofbox\|>/g, '').replace(/<\|endofbox\|>/g, '').trim();
        
        if (!resultText) {
            resultText = '未提取到文字';
        }
        
        elements.extractResult.textContent = resultText;
        // 显示翻译区域（仅当提取到有效文字时）
        if (resultText !== '未提取到文字' && !resultText.includes('服务器繁忙')) {
            elements.translateArea.style.display = 'block';
        }
        showToast('文字提取完成', 1500);
    })
    .catch(err => {
        clearInterval(extractTimer);
        elements.extractProgressContainer.style.display = 'none';
        elements.extractLoading.style.display = 'none';
        // 停止Lottie动画
        if (lottieInstances.extract) lottieInstances.extract.stop();
        
        elements.extractResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器繁忙，请稍后重试</p>`;
        showToast('文字提取失败', 2000);
    });
}

// 新增：翻译提取的文字
function translateExtractText() {
    const text = elements.extractResult.textContent;
    const target = elements.targetLanguage.value;
    
    // 校验输入
    if (!text || text === '未提取到文字' || text.includes('服务器繁忙')) {
        showToast('无有效文字可翻译', 2000);
        return;
    }
    
    // 重置翻译状态
    resetTranslateState();
    elements.translateResult.innerHTML = `<div style="text-align: center; color: #666;">翻译中...</div>`;
    
    // 构建翻译请求URL
    const url = `https://yunzhiapi.cn/API/wnyyfy.php?msg=${encodeURIComponent(text)}&target=${encodeURIComponent(target)}`;
    
    fetch(url)
    .then(res => res.json())
    .then(data => {
        // 检查翻译接口错误
        if (data.status !== 'success') {
            elements.translateResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">翻译失败：${data.message || '未知错误'}</p>`;
            showToast('翻译失败', 2000);
            return;
        }
        
        // 显示翻译结果
        elements.translateResult.innerHTML = `
            <div style="margin-bottom: 10px;">
                <span style="color: #666; font-weight: 500;">原文：</span>
                <span>${data.data.original_text}</span>
            </div>
            <div>
                <span style="color: #666; font-weight: 500;">${data.data.target_language}：</span>
                <span style="color: #2ecc71; font-weight: 500;">${data.data.translated_text}</span>
            </div>
        `;
        showToast(`翻译为${data.data.target_language}成功`, 1500);
    })
    .catch(err => {
        // 处理接口返回指定错误格式
        if (parseApiError(err.message || err.toString())) {
            elements.translateResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器繁忙，请稍后重试</p>`;
        } else {
            elements.translateResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">翻译请求失败：${err.message || '网络错误'}</p>`;
        }
        showToast('翻译请求失败', 2000);
    });
}

// 复制提取的文字
function copyExtractText() {
    const text = elements.extractResult.textContent;
    if (!text || text === '未提取到文字' || text.includes('服务器繁忙')) {
        showToast('无文字可复制', 2000);
        return;
    }
    
    const input = document.createElement('textarea');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    showToast('文字复制成功', 1000);
}

// 页面卸载时清理Lottie
window.addEventListener('beforeunload', () => {
    Object.values(lottieInstances).forEach(instance => {
        if (instance) {
            instance.destroy();
        }
    });
    
    // 清理定时器
    if (repairTimer) clearInterval(repairTimer);
    if (recognizeTimer) clearInterval(recognizeTimer);
    if (extractTimer) clearInterval(extractTimer);
    if (translateTimer) clearInterval(translateTimer);
});

// 初始化
init();
