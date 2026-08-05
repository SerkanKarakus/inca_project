<?php
/**
 * Secure File Upload Helper.
 * Handles server-side validation, MIME checks, unique renaming, and thumbnail creation.
 */

// Prevent direct access to config files if accessed via web server
if (basename($_SERVER['PHP_SELF']) === 'upload.php') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied");
}

// Limits
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5 Megabytes
define('MAX_PDF_SIZE', 10 * 1024 * 1024);   // 10 Megabytes

// Allowed MIME Mappings
const ALLOWED_IMAGES = [
    'image/jpeg' => 'jpg',
    'image/jpg'  => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

const ALLOWED_PDFS = [
    'application/pdf' => 'pdf'
];

/**
 * Validates and uploads a file to a target directory.
 * 
 * @param array $fileArray $_FILES['key'] array element
 * @param string $targetDir Absolute destination path on server
 * @param bool $isImage True to validate image MIME, false for PDF MIME
 * @return array ['success' => bool, 'filename' => string, 'error' => string]
 */
function handle_secure_upload(array $fileArray, string $targetDir, bool $isImage = true): array {
    if ($fileArray['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Dosya yükleme hatası oluştu. Kod: ' . $fileArray['error']];
    }
    
    // Check local filesystem temp exists
    if (!is_uploaded_file($fileArray['tmp_name'])) {
        return ['success' => false, 'error' => 'Geçersiz geçici dosya.'];
    }
    
    // Validate File Size
    $maxSize = $isImage ? MAX_IMAGE_SIZE : MAX_PDF_SIZE;
    if ($fileArray['size'] > $maxSize) {
        $sizeMB = $maxSize / (1024 * 1024);
        return ['success' => false, 'error' => "Dosya boyutu çok büyük. Maksimum limit: {$sizeMB}MB."];
    }
    
    // Secure MIME Type Validation (finfo)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileArray['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = $isImage ? ALLOWED_IMAGES : ALLOWED_PDFS;
    if (!array_key_exists($mimeType, $allowedMimes)) {
        $formats = $isImage ? 'JPG, PNG, WEBP' : 'PDF';
        return ['success' => false, 'error' => "Geçersiz dosya formatı. İzin verilen formatlar: {$formats}."];
    }
    
    // Determine Extension
    $extension = $allowedMimes[$mimeType];
    
    // Generate Cryptographically Secure Unique Filename
    $uniqueName = bin2hex(random_bytes(16)) . '.' . $extension;
    $destPath = rtrim($targetDir, '/') . '/' . $uniqueName;
    
    // Ensure destination directory is writeable
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // Move from Temp to Uploads
    if (move_uploaded_file($fileArray['tmp_name'], $destPath)) {
        // Thumbnail Generation (GD library fallback check included)
        if ($isImage && function_exists('gd_info')) {
            $thumbName = 'thumb_' . $uniqueName;
            $thumbPath = rtrim($targetDir, '/') . '/' . $thumbName;
            generate_thumbnail($destPath, $thumbPath, 320, 320);
        }
        
        return [
            'success'  => true,
            'filename' => $uniqueName,
            'path'     => $destPath
        ];
    }
    
    return ['success' => false, 'error' => 'Dosya sunucuya kaydedilemedi. Klasör izinlerini kontrol edin.'];
}

/**
 * Resizes an image preserving aspect ratio using GD.
 * 
 * @param string $sourcePath
 * @param string $destPath
 * @param int $thumbWidth
 * @param int $thumbHeight
 * @return bool
 */
function generate_thumbnail(string $sourcePath, string $destPath, int $thumbWidth = 320, int $thumbHeight = 320): bool {
    list($width, $height, $type) = getimagesize($sourcePath);
    if ($width === 0 || $height === 0) {
        return false;
    }
    
    // Calculate aspect ratio
    $ratio = $width / $height;
    if ($thumbWidth / $thumbHeight > $ratio) {
        $newWidth = (int)($thumbHeight * $ratio);
        $newHeight = $thumbHeight;
    } else {
        $newWidth = $thumbWidth;
        $newHeight = (int)($thumbWidth / $ratio);
    }
    
    // Load image based on extension type
    switch ($type) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            $sourceImage = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false; // Unsupported GD type
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Create new blank canvas
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparent backgrounds for PNG/WEBP
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
    }
    
    // Copy and resample with high quality interpolation
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Output image to file
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($newImage, $destPath, 85); // 85% Quality
            break;
        case IMAGETYPE_PNG:
            imagepng($newImage, $destPath, 6);   // Level 6 Compression
            break;
        case IMAGETYPE_WEBP:
            imagewebp($newImage, $destPath, 80); // 80% Quality
            break;
    }
    
    // Free memory
    imagedestroy($newImage);
    imagedestroy($sourceImage);
    
    return true;
}
