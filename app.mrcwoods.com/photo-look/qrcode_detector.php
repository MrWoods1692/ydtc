<?php
/**
 * QR码检测器
 * 真实QR码识别实现，使用可用的系统工具，带超时保护
 */

class QRCodeDetector {
    private $image;
    private $width;
    private $height;
    
    public function __construct($imageResource, $width, $height) {
        $this->image = $imageResource;
        $this->width = $width;
        $this->height = $height;
    }
    
    /**
     * 检测并解码二维码
     */
    public function detectAndDecode() {
        // 设置执行时间限制，避免超时
        $maxExecutionTime = 15; // 15秒超时
        $start = microtime(true);
        
        // 保存图像到临时文件
        $tempPath = tempnam(sys_get_temp_dir(), 'qr_');
        if (!imagepng($this->image, $tempPath)) {
            @unlink($tempPath);
            return null;
        }
        
        try {
            // 尝试使用各种系统工具解码
            $result = $this->tryDecodeWithSystemTools($tempPath, $start, $maxExecutionTime);
        } catch (Exception $e) {
            $result = null;
        }
        
        // 清理临时文件
        @unlink($tempPath);
        
        return $result;
    }
    
    /**
     * 尝试使用系统工具解码，带超时保护
     */
    private function tryDecodeWithSystemTools($imagePath, $startTime, $maxExecutionTime) {
        // 检查执行时间
        if ((microtime(true) - $startTime) > $maxExecutionTime) {
            return null;
        }
        
        // 首先检查zbar是否可用
        if ($this->isZbarAvailable()) {
            // 检查执行时间
            if ((microtime(true) - $startTime) > $maxExecutionTime) {
                return null;
            }
            
            $zbarResult = $this->tryZbar($imagePath);
            if ($zbarResult !== false) {
                return $zbarResult;
            }
        }
        
        return null;
    }
    
    /**
     * 检查zbar是否可用
     */
    private function isZbarAvailable() {
        static $zbarChecked = false;
        static $zbarAvailable = false;
        
        if (!$zbarChecked) {
            $checkCmd = 'command -v zbarimg >/dev/null 2>&1';
            $zbarAvailable = (shell_exec($checkCmd) !== null);
            $zbarChecked = true;
        }
        
        return $zbarAvailable;
    }
    
    /**
     * 尝试使用 zbarimg 解码，带超时保护
     */
    private function tryZbar($imagePath) {
        // 设置较短的超时时间
        $cmd = 'timeout 10s zbarimg --quiet --raw ' . escapeshellarg($imagePath) . ' 2>/dev/null';
        $output = shell_exec($cmd);
        
        if ($output !== null && trim($output) !== '') {
            return trim($output);
        }
        
        return false;
    }
}

/**
 * 二维码识别主函数 - 处理图像资源
 */
function detectQRCode($imageResource, $width, $height) {
    try {
        $detector = new QRCodeDetector($imageResource, $width, $height);
        $result = $detector->detectAndDecode();
        
        if ($result === false || $result === null) {
            return null;
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("QR码检测错误: " . $e->getMessage());
        return null;
    }
}

/**
 * 检查zbar是否已安装
 */
function isZbarInstalled() {
    static $checked = false;
    static $installed = false;
    
    if (!$checked) {
        $checkCmd = 'command -v zbarimg >/dev/null 2>&1 && echo "1" || echo "0"';
        $result = trim(shell_exec($checkCmd));
        $installed = ($result === '1');
        $checked = true;
    }
    
    return $installed;
}

/**
 * 检测远程图片的二维码 - 下载并检测
 */
function detectQRCodeFromContent($imageContent) {
    try {
        // 创建图像资源
        $image = @imagecreatefromstring($imageContent);
        if ($image === false) {
            error_log("无法创建图像资源");
            return null;
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        $result = detectQRCode($image, $width, $height);
        
        // 释放资源
        imagedestroy($image);
        
        return $result;
    } catch (Exception $e) {
        error_log("QR码检测错误: " . $e->getMessage());
        return null;
    }
}

/**
 * 检测URL图片的二维码
 */
function detectQRCodeFromURL($imageUrl) {
    try {
        // 检查URL是否是有效的图像URL
        $headers = @get_headers($imageUrl);
        if ($headers === false) {
            // 如果无法获取headers，使用file_get_contents方法
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: Mozilla/5.0 (compatible; QR-Code-Reader/1.0)',
                        'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
                    ],
                    'timeout' => 10 // 限制下载时间
                ]
            ]);
            
            $imageContent = @file_get_contents($imageUrl, false, $context);
        } else {
            // 简单下载方法
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: Mozilla/5.0 (compatible; QR-Code-Reader/1.0)',
                        'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
                    ],
                    'timeout' => 10
                ]
            ]);
            
            $imageContent = @file_get_contents($imageUrl, false, $context);
        }
        
        if ($imageContent === false) {
            error_log("无法下载图片: " . $imageUrl);
            return null;
        }
        
        return detectQRCodeFromContent($imageContent);
    } catch (Exception $e) {
        error_log("QR码检测错误: " . $e->getMessage());
        return null;
    }
}