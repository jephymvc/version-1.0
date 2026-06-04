<?php
/**
 * COMPLETE Secure Image Thumbnailer with Full Filename Control
 * 
 * Features:
 * - Full control over thumbnail filenames
 * - Comprehensive security validation
 * - EXIF metadata stripping
 * - Pattern-based naming system
 * - Database integration ready
 * 
 * Requirements: PHP 8.0+, GD extension, fileinfo extension
 */

declare(strict_types=1);

namespace App\Image;

use InvalidArgumentException;
use RuntimeException;
use finfo;

/**
 * Main Thumbnailer Class
 */
class SecureImageThumbnailer
{
    // Allowed image types and their extensions
    private const ALLOWED_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/gif'  => ['gif'],
        'image/webp' => ['webp'],
        'image/bmp'  => ['bmp']
    ];

    // Security limits
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const MAX_DIMENSION = 10000; // Max width/height
    private const MAX_FILENAME_LENGTH = 255;

    // Default quality settings
    private const DEFAULT_QUALITY = [
        'jpg'  => 85,
        'jpeg' => 85,
        'png'  => 6,
        'webp' => 80,
        'gif'  => null,
        'bmp'  => null
    ];

    // Directory properties
    private string $uploadsDir;
    private string $thumbsDir;
    private bool $stripMetadata;
    private bool $validateUploads;

    /**
     * Constructor
     */
    public function __construct(
        string $uploadsDir = 'secure_uploads',
        string $thumbsDir = 'secure_thumbs',
        bool $stripMetadata = true,
        bool $validateUploads = true
    ) {
        $this->setUploadsDir($uploadsDir);
        $this->setThumbsDir($thumbsDir);
        $this->stripMetadata = $stripMetadata;
        $this->validateUploads = $validateUploads;
        
        $this->ensureSecureDirectories();
    }

    // =============================================
    // PUBLIC API METHODS
    // =============================================

    /**
     * Method 1: Create thumbnail with exact filename
     */
    public function createThumbnail(
        string $sourcePath,
        string $destPath,  // REQUIRED - you have full control
        int $maxWidth = 200,
        int $maxHeight = 200,
        bool $crop = false,
        ?int $quality = null
    ): string {
        // Validate inputs
        $this->validateSourcePath($sourcePath);
        $this->validateDestPath($destPath);
        $this->validateDimensions($maxWidth, $maxHeight);

        // Get image info
        $imageInfo = $this->getSecureImageInfo($sourcePath);
        [$origWidth, $origHeight, $imageType, $mimeType] = $imageInfo;

        // Calculate dimensions
        if ($crop) {
            [$newWidth, $newHeight, $srcX, $srcY, $srcWidth, $srcHeight] = 
                $this->calculateCropDimensions($origWidth, $origHeight, $maxWidth, $maxHeight);
        } else {
            [$newWidth, $newHeight, $srcX, $srcY, $srcWidth, $srcHeight] = 
                $this->calculateScaleDimensions($origWidth, $origHeight, $maxWidth, $maxHeight);
        }

        // Ensure directory exists
        $this->ensureDirectorySecure(dirname($destPath));

        // Create image resources
        $sourceImage = $this->createImageResource($sourcePath, $imageType);
        $thumbImage = imagecreatetruecolor($newWidth, $newHeight);

        // Handle transparency
        $this->preserveTransparency($thumbImage, $imageType);

        // Resample image
        imagecopyresampled(
            $thumbImage, $sourceImage,
            0, 0, $srcX, $srcY,
            $newWidth, $newHeight, $srcWidth, $srcHeight
        );

        // Save image
        $this->saveImage($thumbImage, $destPath, $imageType, $mimeType, $quality);

        // Cleanup
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);

        return $destPath;
    }
	
	
	/**
	 * Create thumbnail from any source (bypasses upload directory restriction)
	 * Use with caution - only for trusted sources
	 */
	public function createThumbnailFromAnySource(
		string $sourcePath,
		string $destPath,
		int $maxWidth = 200,
		int $maxHeight = 200,
		bool $crop = false,
		?int $quality = null
	): string {
		// Validate source exists and is readable
		if (!file_exists($sourcePath)) {
			throw new InvalidArgumentException("Source file not found: $sourcePath");
		}
		
		if (!is_readable($sourcePath)) {
			throw new InvalidArgumentException("Source file not readable: $sourcePath");
		}
		
		// Validate destination path (keeps thumbnails in secure location)
		$this->validateDestPath($destPath);
		$this->validateDimensions($maxWidth, $maxHeight);
		
		// Get image info with security checks
		$imageInfo = $this->getSecureImageInfo($sourcePath);
		[$origWidth, $origHeight, $imageType, $mimeType] = $imageInfo;
		
		// Calculate dimensions
		if ($crop) {
			[$newWidth, $newHeight, $srcX, $srcY, $srcWidth, $srcHeight] = 
				$this->calculateCropDimensions($origWidth, $origHeight, $maxWidth, $maxHeight);
		} else {
			[$newWidth, $newHeight, $srcX, $srcY, $srcWidth, $srcHeight] = 
				$this->calculateScaleDimensions($origWidth, $origHeight, $maxWidth, $maxHeight);
		}
		
		// Ensure destination directory exists
		$this->ensureDirectorySecure(dirname($destPath));
		
		// Create image resources
		$sourceImage = $this->createImageResource($sourcePath, $imageType);
		$thumbImage = imagecreatetruecolor($newWidth, $newHeight);
		
		// Handle transparency
		$this->preserveTransparency($thumbImage, $imageType);
		
		// Resample image
		imagecopyresampled(
			$thumbImage, $sourceImage,
			0, 0, $srcX, $srcY,
			$newWidth, $newHeight, $srcWidth, $srcHeight
		);
		
		// Save image
		$this->saveImage($thumbImage, $destPath, $imageType, $mimeType, $quality);
		
		// Cleanup
		imagedestroy($sourceImage);
		imagedestroy($thumbImage);
		
		return $destPath;
	}

    /**
     * Method 2: Process upload with full naming control
     */
    public function processUploadWithNaming(
        array $file,
        array $thumbConfigs,
        ?string $customOriginalName = null,
        ?string $originalDestPath = null
    ): UploadResult {
        $result = new UploadResult();

        try {
            // Validate upload
            $this->validateUpload($file);

            // Handle original file
            if ($originalDestPath) {
                // Use exact path provided
                $originalPath = $originalDestPath;
                $this->ensureDirectorySecure(dirname($originalPath));
            } else {
                // Generate path with optional custom name
                $originalPath = $this->generateOriginalPath(
                    $file['name'],
                    $customOriginalName
                );
            }

            // Move uploaded file
            $this->secureMoveUpload($file['tmp_name'], $originalPath);

            // Strip metadata if enabled
            if ($this->stripMetadata) {
                $this->stripImageMetadata($originalPath);
            }

            // Validate image content
            $this->validateImageContent($originalPath);

            // Create thumbnails
            $thumbnails = [];
            foreach ($thumbConfigs as $config) {
                $thumbPath = $this->createThumbnail(
                    $originalPath,
                    $config['destPath'],  // Your controlled filename
                    $config['width'] ?? 200,
                    $config['height'] ?? 200,
                    $config['crop'] ?? false,
                    $config['quality'] ?? null
                );
                
                $thumbnails[] = $thumbPath;
            }

            $result->setSuccess(true)
                   ->setOriginalPath($originalPath)
                   ->setThumbnails($thumbnails);

        } catch (InvalidArgumentException $e) {
            $result->addError('Validation Error: ' . $e->getMessage());
        } catch (RuntimeException $e) {
            $result->addError('Processing Error: ' . $e->getMessage());
            
            // Cleanup on failure
            if (isset($originalPath) && file_exists($originalPath)) {
                unlink($originalPath);
            }
        }

        return $result;
    }

    /**
     * Method 3: Create multiple thumbnails with pattern
     */
    public function createThumbnailsWithPattern(
        string $sourcePath,
        string $pattern,
        array $sizes,
        ?array $customData = null
    ): array {
        $results = [];
        $info = pathinfo($sourcePath);
        
        foreach ($sizes as $sizeName => $sizeConfig) {
            // Replace placeholders in pattern
            $destPath = $this->replacePatternPlaceholders(
                $pattern,
                $info,
                $sizeConfig,
                $sizeName,
                $customData
            );
            
            // Validate destination path
            $this->validateDestPath($destPath);
            
            // Create thumbnail
            try {
                $thumbPath = $this->createThumbnail(
                    $sourcePath,
                    $destPath,
                    $sizeConfig['width'],
                    $sizeConfig['height'],
                    $sizeConfig['crop'] ?? false,
                    $sizeConfig['quality'] ?? null
                );
                
                $results[$sizeName] = [
                    'path' => $thumbPath,
                    'width' => $sizeConfig['width'],
                    'height' => $sizeConfig['height']
                ];
            } catch (\Exception $e) {
                $results[$sizeName] = [
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Method 4: Delete image and all related thumbnails
     */
    public function deleteImageAndThumbnails(string $originalPath): bool
    {
        if (!file_exists($originalPath)) {
            return false;
        }

        $deleted = false;
        
        try {
            // Delete original
            $deleted = unlink($originalPath);
            
            // Try to find and delete thumbnails (optional)
            // This assumes thumbnails follow a naming pattern
            $this->cleanupThumbnails($originalPath);
            
        } catch (\Exception $e) {
            error_log("Delete failed: " . $e->getMessage());
        }

        return $deleted;
    }

    // =============================================
    // SECURITY VALIDATION METHODS
    // =============================================

    /**
     * Validate uploaded file
     */
    private function validateUpload(array $file): void
    {
        // Check upload error
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(
                $this->getUploadErrorMessage($file['error'] ?? -1)
            );
        }

        // Security check
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('Invalid upload - possible attack');
        }

        // Check file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException(sprintf(
                'File too large. Maximum: %dMB',
                self::MAX_FILE_SIZE / 1024 / 1024
            ));
        }

        // Validate MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!$this->isAllowedMimeType($mimeType)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid file type: %s',
                $mimeType
            ));
        }

        // Validate filename
        $this->validateFilename($file['name']);
    }

    /**
     * Validate source file path
     */
    private function validateSourcePath(string $path): void
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException("Source file not found");
        }

        if (!is_readable($path)) {
            throw new InvalidArgumentException("Source file not readable");
        }

        // Prevent directory traversal
        $realPath = realpath($path);
        $allowedDir = realpath($this->uploadsDir);

        if (strpos($realPath, $allowedDir) !== 0) {
            throw new InvalidArgumentException("Invalid source path");
        }
    }

    /**
     * Validate destination path
     */
    private function validateDestPath(string $path): void
    {
        // Check for null bytes
        if (strpos($path, "\0") !== false) {
            throw new InvalidArgumentException('Invalid path: null byte detected');
        }

        // Check for directory traversal
        if (preg_match('/\.\.(\/|\\\\)/', $path)) {
            throw new InvalidArgumentException('Invalid path: directory traversal');
        }

        // Check length
        if (strlen($path) > self::MAX_FILENAME_LENGTH) {
            throw new InvalidArgumentException('Path too long');
        }

        // Ensure path is within thumbs directory
        $realDir = realpath(dirname($path));
        $allowedDir = realpath($this->thumbsDir);

        if ($realDir === false || strpos($realDir, $allowedDir) !== 0) {
            throw new InvalidArgumentException('Destination outside allowed directory');
        }

        // Validate extension
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        
        if (!in_array($extension, $allowedExtensions)) {
            throw new InvalidArgumentException('Invalid file extension: ' . $extension);
        }
    }

    /**
     * Validate dimensions
     */
    private function validateDimensions(int $width, int $height): void
    {
        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('Dimensions must be positive');
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new InvalidArgumentException('Dimensions exceed maximum allowed');
        }
    }

    /**
     * Validate filename
     */
    private function validateFilename(string $filename): void
    {
        if (strpos($filename, "\0") !== false) {
            throw new InvalidArgumentException('Invalid filename: null byte');
        }

        if (preg_match('/\.\.(\/|\\\\)/', $filename)) {
            throw new InvalidArgumentException('Invalid filename: directory traversal');
        }

        if (strlen($filename) > 255) {
            throw new InvalidArgumentException('Filename too long');
        }
    }

    /**
     * Validate image content
     */
    private function validateImageContent(string $path): void
    {
        $imageInfo = @getimagesize($path);
        if (!$imageInfo) {
            throw new InvalidArgumentException('Invalid image content');
        }

        if ($imageInfo[0] > self::MAX_DIMENSION || $imageInfo[1] > self::MAX_DIMENSION) {
            throw new InvalidArgumentException('Image dimensions too large');
        }

        // Verify MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($path);
        
        if (!$this->isAllowedMimeType($detectedMime)) {
            throw new InvalidArgumentException('MIME type mismatch');
        }
    }

    // =============================================
    // IMAGE PROCESSING METHODS
    // =============================================

    /**
     * Get secure image information
     */
    private function getSecureImageInfo(string $path): array
    {
        $imageInfo = @getimagesize($path);
        if (!$imageInfo) {
            throw new InvalidArgumentException("Invalid or corrupted image");
        }

        list($width, $height, $type) = $imageInfo;

        // Verify with finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        if (!$this->isAllowedMimeType($mimeType)) {
            throw new InvalidArgumentException("Disallowed MIME type");
        }

        return [$width, $height, $type, $mimeType];
    }

    /**
     * Calculate dimensions for scaling
     */
    private function calculateScaleDimensions(
        int $origWidth,
        int $origHeight,
        int $maxWidth,
        int $maxHeight
    ): array {
        $ratio = $origWidth / $origHeight;

        if ($maxWidth / $maxHeight > $ratio) {
            $newWidth = $maxHeight * $ratio;
            $newHeight = $maxHeight;
        } else {
            $newWidth = $maxWidth;
            $newHeight = $maxWidth / $ratio;
        }

        return [
            (int)round($newWidth),
            (int)round($newHeight),
            0, 0,
            $origWidth, $origHeight
        ];
    }

    /**
     * Calculate dimensions for cropping
     */
    private function calculateCropDimensions(
        int $origWidth,
        int $origHeight,
        int $maxWidth,
        int $maxHeight
    ): array {
        $sourceRatio = $origWidth / $origHeight;
        $thumbRatio = $maxWidth / $maxHeight;

        if ($sourceRatio >= $thumbRatio) {
            $srcWidth = (int)round($origHeight * $thumbRatio);
            $srcHeight = $origHeight;
            $srcX = (int)round(($origWidth - $srcWidth) / 2);
            $srcY = 0;
        } else {
            $srcWidth = $origWidth;
            $srcHeight = (int)round($origWidth / $thumbRatio);
            $srcX = 0;
            $srcY = (int)round(($origHeight - $srcHeight) / 2);
        }

        return [
            $maxWidth,
            $maxHeight,
            $srcX, $srcY,
            $srcWidth, $srcHeight
        ];
    }

    /**
     * Create image resource
     */
    private function createImageResource(string $path, int $type)
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                $resource = @imagecreatefromjpeg($path);
                break;
            case IMAGETYPE_PNG:
                $resource = @imagecreatefrompng($path);
                break;
            case IMAGETYPE_GIF:
                $resource = @imagecreatefromgif($path);
                break;
            case IMAGETYPE_WEBP:
                $resource = @imagecreatefromwebp($path);
                break;
            case IMAGETYPE_BMP:
                $resource = @imagecreatefrombmp($path);
                break;
            default:
                throw new InvalidArgumentException("Unsupported image type");
        }

        if (!$resource) {
            throw new RuntimeException("Failed to create image resource");
        }

        return $resource;
    }

    /**
     * Preserve transparency for PNG/GIF
     */
    private function preserveTransparency($image, int $type): void
    {
        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            
            $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
            imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
        }
    }

    /**
     * Save image with appropriate settings
     */
    private function saveImage(
        $image,
        string $path,
        int $type,
        string $mimeType,
        ?int $quality = null
    ): void {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Determine quality
        if ($quality === null) {
            $quality = $this->getDefaultQuality($extension);
        }

        // Save based on type
        switch ($mimeType) {
            case 'image/jpeg':
                $quality = max(0, min(100, $quality));
                $result = imagejpeg($image, $path, $quality);
                break;

            case 'image/png':
                $pngQuality = max(0, min(9, 9 - (int)($quality / 10)));
                $result = imagepng($image, $path, $pngQuality);
                break;

            case 'image/webp':
                $result = imagewebp($image, $path, $quality);
                break;

            case 'image/gif':
                $result = imagegif($image, $path);
                break;

            case 'image/bmp':
                $result = imagebmp($image, $path);
                break;

            default:
                throw new InvalidArgumentException("Unsupported MIME type");
        }

        if (!$result) {
            throw new RuntimeException("Failed to save image");
        }

        // Set secure permissions
        chmod($path, 0644);
    }

    /**
     * Strip EXIF metadata
     */
    private function stripImageMetadata(string $imagePath): void
    {
        $imageInfo = $this->getSecureImageInfo($imagePath);
        $imageType = $imageInfo[2];
        $mimeType = $imageInfo[3];

        if ($mimeType === 'image/jpeg') {
            $image = $this->createImageResource($imagePath, $imageType);
            imagejpeg($image, $imagePath, self::DEFAULT_QUALITY['jpg']);
            imagedestroy($image);
        }
    }

    // =============================================
    // PATH & FILENAME HANDLING METHODS
    // =============================================

    /**
     * Generate original file path
     */
    private function generateOriginalPath(
        string $originalName,
        ?string $customName = null
    ): string {
        if ($customName) {
            $safeName = $this->sanitizeFilename($customName);
            $extension = $this->getSafeExtension($originalName);
        } else {
            $safeName = $this->generateSecureName($originalName);
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        }

        $datePath = date('Y/m/d');
        $fullPath = $this->uploadsDir . '/' . $datePath . '/' . 
                   $safeName . '.' . $extension;

        $this->ensureDirectorySecure(dirname($fullPath));
        return $fullPath;
    }

    /**
     * Sanitize filename
     */
    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^\w\s\-\.]/', '_', $filename);
        $filename = substr($filename, 0, 100);
        
        if (empty($filename)) {
            $filename = 'image_' . bin2hex(random_bytes(4));
        }
        
        return $filename;
    }

    /**
     * Generate secure name
     */
    private function generateSecureName(string $originalName): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9\-\._]/', '_', $originalName);
        $safeName = substr($safeName, 0, 100);
        
        $uniqueId = bin2hex(random_bytes(8));
        return $uniqueId . '_' . $safeName;
    }

    /**
     * Get safe extension
     */
    private function getSafeExtension(string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        foreach (self::ALLOWED_TYPES as $mime => $exts) {
            if (in_array($extension, $exts)) {
                return $extension;
            }
        }
        
        return 'jpg';
    }

    /**
     * Replace pattern placeholders
     */
    private function replacePatternPlaceholders(
        string $pattern,
        array $fileInfo,
        array $sizeConfig,
        string $sizeName,
        ?array $customData = null
    ): string {
        $replacements = [
            '{name}'       => $fileInfo['filename'],
            '{width}'      => $sizeConfig['width'],
            '{height}'     => $sizeConfig['height'],
            '{size}'       => "{$sizeConfig['width']}x{$sizeConfig['height']}",
            '{size_name}'  => $sizeName,
            '{ext}'        => $fileInfo['extension'] ?? 'jpg',
            '{timestamp}'  => time(),
            '{date}'       => date('Y-m-d'),
            '{date_ymd}'   => date('Ymd'),
            '{date_ym}'    => date('Y-m'),
            '{time}'       => date('H-i-s'),
            '{time_hms}'   => date('His'),
            '{hash}'       => substr(md5($fileInfo['filename']), 0, 8),
            '{rand}'       => bin2hex(random_bytes(4)),
            '{uniqid}'     => uniqid()
        ];

        // Add custom data replacements
        if ($customData) {
            foreach ($customData as $key => $value) {
                $replacements["{{$key}}"] = $value;
            }
        }

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $pattern
        );
    }

    // =============================================
    // FILE OPERATION METHODS
    // =============================================

    /**
     * Secure file move
     */
    private function secureMoveUpload(string $tmpPath, string $destination): void
    {
        $destination = realpath(dirname($destination)) . '/' . basename($destination);

        // Check for existing file
        if (file_exists($destination)) {
            $info = pathinfo($destination);
            $destination = $info['dirname'] . '/' . 
                         $info['filename'] . '_' . 
                         bin2hex(random_bytes(4)) . '.' . 
                         $info['extension'];
        }

        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new RuntimeException('Failed to move uploaded file');
        }

        chmod($destination, 0644);
    }

    /**
     * Cleanup thumbnails
     */
    private function cleanupThumbnails(string $originalPath): void
    {
        $pattern = preg_replace('/\.[^\.]+$/', '_*.*', $originalPath);
        $thumbnails = glob($pattern);

        foreach ($thumbnails as $thumb) {
            if (file_exists($thumb) && is_file($thumb)) {
                unlink($thumb);
            }
        }
    }

    // =============================================
    // DIRECTORY & SECURITY METHODS
    // =============================================

    /**
     * Ensure secure directories
     */
    private function ensureSecureDirectories(): void
    {
        $this->ensureDirectorySecure($this->uploadsDir);
        $this->ensureDirectorySecure($this->thumbsDir);
    }

    /**
     * Ensure directory is secure
     */
    private function ensureDirectorySecure(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Add .htaccess to prevent execution
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }

        // Add index file to prevent listing
        $index = $dir . '/index.html';
        if (!file_exists($index)) {
            file_put_contents($index, '<!DOCTYPE html><html><body></body></html>');
        }
    }

    /**
     * Set uploads directory
     */
    private function setUploadsDir(string $dir): void
    {
        $this->uploadsDir = rtrim($dir, '/\\');
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0755, true);
        }
    }

    /**
     * Set thumbs directory
     */
    private function setThumbsDir(string $dir): void
    {
        $this->thumbsDir = rtrim($dir, '/\\');
        if (!is_dir($this->thumbsDir)) {
            mkdir($this->thumbsDir, 0755, true);
        }
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    /**
     * Check if MIME type is allowed
     */
    private function isAllowedMimeType(string $mimeType): bool
    {
        return isset(self::ALLOWED_TYPES[$mimeType]);
    }

    /**
     * Get default quality for extension
     */
    private function getDefaultQuality(string $extension): ?int
    {
        return self::DEFAULT_QUALITY[$extension] ?? null;
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL    => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            UPLOAD_ERR_EXTENSION  => 'Upload stopped by extension',
        ];

        return $errors[$errorCode] ?? "Unknown error ($errorCode)";
    }
}

// =============================================
// UPLOAD RESULT CLASS
// =============================================

class UploadResult
{
    
	private bool $success 			= false;
    private string $originalPath 	= '';
    private array $thumbnails 		= [];
    private array $errors 			= [];

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getOriginalPath(): string
    {
        return $this->originalPath;
    }

    public function getThumbnails(): array
    {
        return $this->thumbnails;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;
        return $this;
    }

    public function setOriginalPath(string $path): self
    {
        $this->originalPath = $path;
        return $this;
    }

    public function setThumbnails(array $thumbnails): self
    {
        $this->thumbnails = $thumbnails;
        return $this;
    }

    public function addError(string $error): self
    {
        $this->errors[] = $error;
        return $this;
    }
	
}

#	// =============================================
#	// USAGE EXAMPLES - COMPLETE
#	// =============================================
#	
#	/**
#	 * EXAMPLE 1: Basic usage with exact filenames
#	 */
#	function exampleBasicUsage(): void
#	{
#	    $thumbnailer = new SecureImageThumbnailer();
#	    
#	    // Create thumbnail with exact filename
#	    $thumbPath = $thumbnailer->createThumbnail(
#	        sourcePath: 'secure_uploads/2024/01/15/image.jpg',
#	        destPath: 'secure_thumbs/exact_filename_i_want.jpg',
#	        maxWidth: 300,
#	        maxHeight: 200,
#	        crop: true,
#	        quality: 90
#	    );
#	    
#	    echo "Thumbnail created: $thumbPath\n";
#	}
#	
#	/**
#	 * EXAMPLE 2: Handle upload with custom naming
#	 */
#	function exampleUploadWithNaming(): void
#	{
#	    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
#	        $thumbnailer = new SecureImageThumbnailer();
#	        
#	        // Define thumbnail configurations with exact paths
#	        $thumbConfigs = [
#	            [
#	                'destPath' => 'secure_thumbs/products/coffee_mug_small.jpg',
#	                'width' => 150,
#	                'height' => 150,
#	                'crop' => true
#	            ],
#	            [
#	                'destPath' => 'secure_thumbs/products/coffee_mug_medium.jpg',
#	                'width' => 400,
#	                'height' => 300,
#	                'crop' => false
#	            ],
#	            [
#	                'destPath' => 'secure_thumbs/products/coffee_mug_large.jpg',
#	                'width' => 800,
#	                'height' => 600,
#	                'crop' => false,
#	                'quality' => 95
#	            ]
#	        ];
#	        
#	        // Process upload
#	        $result = $thumbnailer->processUploadWithNaming(
#	            file: $_FILES['image'],
#	            thumbConfigs: $thumbConfigs,
#	            customOriginalName: 'coffee_mug_original'
#	        );
#	        
#	        if ($result->isSuccess()) {
#	            echo "Upload successful!\n";
#	            echo "Original: {$result->getOriginalPath()}\n";
#	            foreach ($result->getThumbnails() as $thumb) {
#	                echo "Thumbnail: $thumb\n";
#	            }
#	        } else {
#	            echo "Errors:\n";
#	            foreach ($result->getErrors() as $error) {
#	                echo "- $error\n";
#	            }
#	        }
#	    }
#	}

#	/**
#	 * EXAMPLE 3: Pattern-based naming with custom data
#	 */
#	function examplePatternNaming(): void
#	{
#	    $thumbnailer = new SecureImageThumbnailer();
#	    
#	    $sizes = [
#	        'square' => ['width' => 150, 'height' => 150, 'crop' => true],
#	        'preview' => ['width' => 400, 'height' => 300, 'crop' => false],
#	        'gallery' => ['width' => 800, 'height' => 600, 'crop' => false]
#	    ];
#	    
#	    $customData = [
#	        'product_id' => 123,
#	        'slug' => 'premium-coffee-mug',
#	        'category' => 'kitchen'
#	    ];
#	    
#	    $results = $thumbnailer->createThumbnailsWithPattern(
#	        sourcePath: 'secure_uploads/products/123.jpg',
#	        pattern: 'secure_thumbs/{category}/{slug}_{size_name}_{width}x{height}_{product_id}.{ext}',
#	        sizes: $sizes,
#	        customData: $customData
#	    );
#	    
#	    foreach ($results as $sizeName => $result) {
#	        if (isset($result['path'])) {
#	            echo "$sizeName: {$result['path']}\n";
#	        } else {
#	            echo "$sizeName Error: {$result['error']}\n";
#	        }
#	    }
#	}

#	/**
#	 * EXAMPLE 4: Database-integrated usage
#	 */
#	class ProductImageManager
#	{
#	    private SecureImageThumbnailer $thumbnailer;
#	    private PDO $db;
#	    
#	    public function __construct(PDO $db)
#	    {
#	        $this->thumbnailer = new SecureImageThumbnailer();
#	        $this->db = $db;
#	    }
#	    
#	    public function uploadProductImage(int $productId, array $file): bool
#	    {
#	        // Get product info from database
#	        $stmt = $this->db->prepare("SELECT slug, category FROM products WHERE id = ?");
#	        $stmt->execute([$productId]);
#	        $product = $stmt->fetch();
#	        
#	        if (!$product) {
#	            return false;
#	        }
#	        
#	        // Define thumbnail paths based on product data
#	        $thumbConfigs = [
#	            [
#	                'destPath' => "secure_thumbs/products/{$product['category']}/{$product['slug']}_thumbnail.jpg",
#	                'width' => 300,
#	                'height' => 300,
#	                'crop' => true
#	            ],
#	            [
#	                'destPath' => "secure_thumbs/products/{$product['category']}/{$product['slug']}_gallery.jpg",
#	                'width' => 800,
#	                'height' => 600,
#	                'crop' => false
#	            ]
#	        ];
#	        
#	        // Process upload
#	        $result = $this->thumbnailer->processUploadWithNaming(
#	            file: $file,
#	            thumbConfigs: $thumbConfigs,
#	            customOriginalName: "product_{$productId}_original"
#	        );
#	        
#	        if ($result->isSuccess()) {
#	            // Save to database
#	            $stmt = $this->db->prepare("
#	                UPDATE products 
#	                SET image_path = ?, 
#	                    thumbnail_path = ?,
#	                    updated_at = NOW()
#	                WHERE id = ?
#	            ");
#	            
#	            $stmt->execute([
#	                $result->getOriginalPath(),
#	                $thumbConfigs[0]['destPath'],
#	                $productId
#	            ]);
#	            
#	            return true;
#	        }
#	        
#	        return false;
#	    }
#	}
#	
#	

#	/**
#	 * EXAMPLE 5: Delete operation
#	 */
#	function exampleDeleteImage(): void
#	{
#	    $thumbnailer = new SecureImageThumbnailer();
#	    
#	    $deleted = $thumbnailer->deleteImageAndThumbnails(
#	        'secure_uploads/2024/01/15/product_123_original.jpg'
#	    );
#	    
#	    if ($deleted) {
#	        echo "Image and thumbnails deleted successfully\n";
#	    } else {
#	        echo "Delete failed\n";
#	    }
#	}
#	
#	/**
#	 * HTML Form for testing
#	 */
#	function renderUploadForm(): string
#	{
#	    return '
#	    <!DOCTYPE html>
#	    <html>
#	    <head>
#	        <title>Image Upload Test</title>
#	    </head>
#	    <body>
#	        <h1>Upload Image</h1>
#	        <form method="POST" enctype="multipart/form-data">
#	            <input type="file" name="image" accept="image/*" required>
#	            <br><br>
#	            <label>Custom Name (optional):</label>
#	            <input type="text" name="custom_name">
#	            <br><br>
#	            <button type="submit">Upload</button>
#	        </form>
#	    </body>
#	    </html>';
#	}
#	
#	// =============================================
#	// INITIALIZATION & CONFIGURATION
#	// =============================================
#	
#	/**
#	 * Configuration file (config/image.php)
#	 */
#	return [
#	    'uploads_dir' => 'secure_uploads',
#	    'thumbs_dir' => 'secure_thumbs',
#	    'max_file_size' => 10 * 1024 * 1024,
#	    'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
#	    'default_quality' => [
#	        'jpg' => 85,
#	        'png' => 6,
#	        'webp' => 80
#	    ],
#	    'thumbnail_sizes' => [
#	        'small' => ['width' => 150, 'height' => 150, 'crop' => true],
#	        'medium' => ['width' => 400, 'height' => 300, 'crop' => false],
#	        'large' => ['width' => 800, 'height' => 600, 'crop' => false]
#	    ]
#	];
#	
#	// =============================================
#	// MAIN EXECUTION (for testing)
#	// =============================================
#	
#	if (PHP_SAPI === 'cli') {
#	    // Command line testing
#	    echo "Testing SecureImageThumbnailer...\n";
#	    exampleBasicUsage();
#	} else {
#	    // Web execution
#	    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
#	        exampleUploadWithNaming();
#	    } else {
#	        echo renderUploadForm();
#	    }
#	}
#	
#	/**
#	 * ERROR HANDLING SETUP
#	 */
#	set_error_handler(function($errno, $errstr, $errfile, $errline) {
#	    error_log("Thumbnailer Error [$errno]: $errstr in $errfile:$errline");
#	    throw new RuntimeException("Image processing error");
#	});
#	
#	/**
#	 * SECURITY HEADERS (for web usage)
#	 */
#	if (!headers_sent()) {
#	    header('X-Content-Type-Options: nosniff');
#	    header('X-Frame-Options: DENY');
#	    header('X-XSS-Protection: 1; mode=block');
#	}
#	
#	// =============================================
#	// COMPLETE FILE END
#	// =============================================
?>