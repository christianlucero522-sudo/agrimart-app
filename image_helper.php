<?php
// image_helper.php - Unified Image & Biometric Upload Handler for Localhost & Vercel Serverless

if (!function_exists('saveUploadedImage')) {
    /**
     * Universal Image & Document Upload Handler (Supports Localhost Filesystem & Vercel Serverless Data URI)
     *
     * @param array $file The $_FILES['input_name'] array
     * @param string $subfolder Subdirectory under uploads/ (e.g. 'products', 'equipment', 'payments', 'identifications', 'faces')
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
            'image/jpeg'      => 'jpg',
            'image/jpg'       => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'image/gif'       => 'gif',
            'application/pdf' => 'pdf'
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

        // Fallback detection from original file extension if finfo is generic octet-stream
        if ($mimeType === 'application/octet-stream' || !isset($allowedTypes[$mimeType])) {
            $origExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
            if ($origExt === 'jpg' || $origExt === 'jpeg') $mimeType = 'image/jpeg';
            elseif ($origExt === 'png') $mimeType = 'image/png';
            elseif ($origExt === 'webp') $mimeType = 'image/webp';
            elseif ($origExt === 'pdf') $mimeType = 'application/pdf';
        }

        if (!isset($allowedTypes[$mimeType])) {
            return ['success' => false, 'path' => null, 'error' => 'Only JPG, PNG, WEBP, GIF, and PDF files are allowed.'];
        }

        if ($file['size'] > 12 * 1024 * 1024) {
            return ['success' => false, 'path' => null, 'error' => 'File size must be 12MB or smaller.'];
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
        // Read bytes, optimize/compress via GD to ~30-50KB if image, and return as Base64 Data URI!
        $rawBytes = @file_get_contents($tmpName);
        if ($rawBytes === false || strlen($rawBytes) === 0) {
            return ['success' => false, 'path' => null, 'error' => 'Failed to read uploaded file bytes.'];
        }

        // Optimize image with GD if image format
        if (strpos($mimeType, 'image/') === 0 && function_exists('imagecreatefromstring')) {
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

if (!function_exists('saveBase64Image')) {
    /**
     * Universal Base64 Image Handler (For live webcam selfies and canvas captures)
     *
     * @param string $base64String
     * @param string $subfolder Subdirectory under uploads/ (default 'faces')
     * @param string $prefix Filename prefix for local saves (default 'face_')
     * @param int $maxDimension Max width/height to resize (default 700)
     * @param int $quality JPEG quality (default 80)
     * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    function saveBase64Image($base64String, $subfolder = 'faces', $prefix = 'face_', $maxDimension = 700, $quality = 80) {
        if (empty($base64String)) {
            return ['success' => false, 'path' => null, 'error' => 'No image data provided.'];
        }

        $data = $base64String;
        $mimeType = 'image/jpeg';

        if (preg_match('/^data:image\/([a-zA-Z0-9\+\.-]+);base64,(.+)$/', $base64String, $matches)) {
            $ext = strtolower($matches[1]);
            $mimeType = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
            $data = $matches[2];
        }

        $rawBytes = base64_decode($data);
        if ($rawBytes === false || strlen($rawBytes) === 0) {
            return ['success' => false, 'path' => null, 'error' => 'Invalid base64 image data.'];
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
            $fileName = $prefix . time() . '_' . bin2hex(random_bytes(6)) . '.jpg';
            $destination = $uploadDir . $fileName;

            // Compress and scale before saving locally
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
                        imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                        imagejpeg($newImg, $destination, $quality);
                        imagedestroy($newImg);
                        imagedestroy($img);
                        return [
                            'success' => true,
                            'path' => 'uploads/' . $cleanSubfolder . '/' . $fileName,
                            'error' => null
                        ];
                    }
                    imagejpeg($img, $destination, $quality);
                    imagedestroy($img);
                    return [
                        'success' => true,
                        'path' => 'uploads/' . $cleanSubfolder . '/' . $fileName,
                        'error' => null
                    ];
                }
            }

            if (@file_put_contents($destination, $rawBytes)) {
                return [
                    'success' => true,
                    'path' => 'uploads/' . $cleanSubfolder . '/' . $fileName,
                    'error' => null
                ];
            }
        }

        // 2. On Vercel Serverless (or read-only environments):
        // Scale and compress via GD so the resulting Base64 string is compact (~25KB)
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
                    imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

                    ob_start();
                    imagejpeg($newImg, null, $quality);
                    $compressed = ob_get_clean();
                    imagedestroy($newImg);
                    if ($compressed && strlen($compressed) > 0) {
                        $rawBytes = $compressed;
                    }
                } else {
                    ob_start();
                    imagejpeg($img, null, $quality);
                    $compressed = ob_get_clean();
                    if ($compressed && strlen($compressed) > 0) {
                        $rawBytes = $compressed;
                    }
                }
                imagedestroy($img);
            }
        }

        return [
            'success' => true,
            'path' => 'data:image/jpeg;base64,' . base64_encode($rawBytes),
            'error' => null
        ];
    }
}
