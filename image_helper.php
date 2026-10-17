<?php
// image_helper.php - Unified Image Upload Handler for Localhost & Vercel Serverless

if (!function_exists('saveUploadedImage')) {
    /**
     * Universal Image Upload Handler (Supports Localhost Filesystem & Vercel Serverless Data URI)
     *
     * @param array $file The $_FILES['input_name'] array
     * @param string $subfolder Subdirectory under uploads/ (e.g. 'products', 'equipment', 'payments')
     * @param int $maxDimension Max width/height to resize for compact web rendering (default 900)
     * @param int $quality JPEG quality (default 82)
     * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    function saveUploadedImage($file, $subfolder = 'products', $maxDimension = 900, $quality = 82) {
        if (!isset($file) || !is_array($file)) {
            return ['success' => false, 'path' => null, 'error' => 'No file provided.'];
        }

        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'path' => null, 'error' => 'No file uploaded.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'path' => null, 'error' => 'Upload failed with error code ' . $file['error'] . '.'];
        }

        $tmpName = $file['tmp_name'] ?? '';
        if (empty($tmpName) || (!is_uploaded_file($tmpName) && !file_exists($tmpName))) {
            return ['success' => false, 'path' => null, 'error' => 'Uploaded temporary file not found.'];
        }

        // Validate MIME type
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif'
        ];

        $mimeType = 'image/jpeg';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($tmpName);
            if ($detected) $mimeType = strtolower($detected);
        } elseif (function_exists('mime_content_type')) {
            $detected = mime_content_type($tmpName);
            if ($detected) $mimeType = strtolower($detected);
        }

        if (!isset($allowedTypes[$mimeType])) {
            return ['success' => false, 'path' => null, 'error' => 'Only JPG, PNG, WEBP, and GIF images are allowed.'];
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            return ['success' => false, 'path' => null, 'error' => 'Image size must be 10MB or smaller.'];
        }

        $isVercel = (getenv('VERCEL') || strpos($_SERVER['HTTP_HOST'] ?? '', 'vercel.app') !== false);
        $cleanSubfolder = trim($subfolder, '/');
        $uploadDir = __DIR__ . '/uploads/' . $cleanSubfolder . '/';

        // 1. Try local physical save if writable and NOT on Vercel
        $canWriteLocal = false;
        if (!$isVercel) {
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            if (is_dir($uploadDir) && is_writable($uploadDir)) {
                $canWriteLocal = true;
            }
        }

        if ($canWriteLocal) {
            $ext = $allowedTypes[$mimeType] ?? 'jpg';
            $fileName = bin2hex(random_bytes(16)) . '.' . $ext;
            $destination = $uploadDir . $fileName;

            if (@move_uploaded_file($tmpName, $destination)) {
                return [
                    'success' => true,
                    'path' => 'uploads/' . $cleanSubfolder . '/' . $fileName,
                    'error' => null
                ];
            }
        }

        // 2. On Vercel Serverless (or read-only environments):
        // Read image bytes, optimize/compress via GD to ~30-50KB, and return as a Base64 Data URI!
        $rawBytes = @file_get_contents($tmpName);
        if ($rawBytes === false || strlen($rawBytes) === 0) {
            return ['success' => false, 'path' => null, 'error' => 'Failed to read uploaded image bytes.'];
        }

        // Optimize image with GD if available
        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($rawBytes);
            if ($img !== false) {
                $origW = imagesx($img);
                $origH = imagesy($img);

                if ($origW > $maxDimension || $origH > $maxDimension) {
                    $ratio = min($maxDimension / $origW, $maxDimension / $origH);
                    $newW = max(1, (int)($origW * $ratio));
                    $newH = max(1, (int)($origH * $ratio));

                    $newImg = imagecreatetruecolor($newW, $newH);
                    $white = imagecolorallocate($newImg, 255, 255, 255);
                    imagefill($newImg, 0, 0, $white);
                    imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

                    ob_start();
                    imagejpeg($newImg, null, $quality);
                    $compressed = ob_get_clean();
                    imagedestroy($newImg);
                    if ($compressed && strlen($compressed) > 0) {
                        $rawBytes = $compressed;
                        $mimeType = 'image/jpeg';
                    }
                } else {
                    ob_start();
                    imagejpeg($img, null, $quality);
                    $compressed = ob_get_clean();
                    if ($compressed && strlen($compressed) > 0) {
                        $rawBytes = $compressed;
                        $mimeType = 'image/jpeg';
                    }
                }
                imagedestroy($img);
            }
        }

        $base64DataUri = 'data:' . $mimeType . ';base64,' . base64_encode($rawBytes);

        return [
            'success' => true,
            'path' => $base64DataUri,
            'error' => null
        ];
    }
}
