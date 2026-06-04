<?php
namespace App\Core;
class ImageThumbnailer
{
    private array $allowedTypes = [
        IMAGETYPE_JPEG, IMAGETYPE_PNG, 
        IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_BMP
    ];
    
    private int $defaultQuality = 85;
    private string $thumbDir = 'thumbs';
    
    public function __construct(
        private ?string $outputFormat = null,
        private bool $autoCreateDir = true
    ) {}
    
    /**
     * Create multiple thumbnail sizes
     */
    public function createThumbnails(string $sourcePath, array $sizes): array
    {
        $results = [];
        
        foreach ($sizes as $sizeName => $config) {
            $width = $config['width'] ?? 200;
            $height = $config['height'] ?? 200;
            $crop = $config['crop'] ?? false;
            $quality = $config['quality'] ?? $this->defaultQuality;
            
            try {
                $thumbPath = $this->createThumbnail(
                    $sourcePath,
                    $this->generateOutputPath($sourcePath, $sizeName, $width, $height),
                    $width,
                    $height,
                    $crop,
                    $quality
                );
                
                $results[$sizeName] = [
                    'path' => $thumbPath,
                    'width' => $width,
                    'height' => $height,
                    'size' => filesize($thumbPath)
                ];
            } catch (Exception $e) {
                $results[$sizeName] = [
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    public function createThumbnail(
		string $sourcePath,
		?string $destPath = null,
		int $maxWidth = 200,
		int $maxHeight = 200,
		bool $crop = false,
		int $quality = null
	): string {
		// Validate source file exists
		if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
			throw new \InvalidArgumentException("Source file does not exist or is not readable: {$sourcePath}");
		}

		// Get image info
		$imageInfo = @getimagesize($sourcePath);
		if (!$imageInfo) {
			throw new \RuntimeException("Unable to read image or unsupported image format: {$sourcePath}");
		}

		// Determine destination path
		if ($destPath === null) {
			$pathInfo = pathinfo($sourcePath);
			$destPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . 
					   '_thumb.' . $pathInfo['extension'];
		}

		// Create destination directory if it doesn't exist
		$destDir = dirname($destPath);
		if (!is_dir($destDir)) {
			if (!mkdir($destDir, 0755, true)) {
				throw new \RuntimeException("Failed to create directory: {$destDir}");
			}
		}

		// Determine image type and create image resource
		[$originalWidth, $originalHeight, $imageType] = $imageInfo;
		
		switch ($imageType) {
			case IMAGETYPE_JPEG:
				$sourceImage = imagecreatefromjpeg($sourcePath);
				break;
			case IMAGETYPE_PNG:
				$sourceImage = imagecreatefrompng($sourcePath);
				break;
			case IMAGETYPE_GIF:
				$sourceImage = imagecreatefromgif($sourcePath);
				break;
			case IMAGETYPE_WEBP:
				$sourceImage = imagecreatefromwebp($sourcePath);
				break;
			case IMAGETYPE_BMP:
				$sourceImage = imagecreatefrombmp($sourcePath);
				break;
			default:
				throw new \RuntimeException("Unsupported image type");
		}

		if (!$sourceImage) {
			throw new \RuntimeException("Failed to create image resource from: {$sourcePath}");
		}

		// Correct EXIF orientation for JPEG images
		if ($imageType === IMAGETYPE_JPEG) {
			$this->correctImageOrientation($sourceImage, $sourcePath);
		}

		// Calculate thumbnail dimensions
		if ($crop) {
			// Calculate cropping dimensions for a center crop
			$sourceAspect = $originalWidth / $originalHeight;
			$thumbAspect = $maxWidth / $maxHeight;
			
			if ($sourceAspect >= $thumbAspect) {
				// Source is wider than thumbnail aspect ratio
				$cropWidth = (int)($originalHeight * $thumbAspect);
				$cropHeight = $originalHeight;
				$cropX = (int)(($originalWidth - $cropWidth) / 2);
				$cropY = 0;
			} else {
				// Source is taller than thumbnail aspect ratio
				$cropWidth = $originalWidth;
				$cropHeight = (int)($originalWidth / $thumbAspect);
				$cropX = 0;
				$cropY = (int)(($originalHeight - $cropHeight) / 2);
			}
			
			// Create cropped image
			$croppedImage = imagecreatetruecolor($cropWidth, $cropHeight);
			
			// Preserve transparency for PNG/GIF
			if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
				imagealphablending($croppedImage, false);
				imagesavealpha($croppedImage, true);
				$transparent = imagecolorallocatealpha($croppedImage, 0, 0, 0, 127);
				imagefill($croppedImage, 0, 0, $transparent);
			}
			
			imagecopy($croppedImage, $sourceImage, 0, 0, $cropX, $cropY, $cropWidth, $cropHeight);
			
			// Resize the cropped image
			$thumbImage = imagecreatetruecolor($maxWidth, $maxHeight);
			
			// Preserve transparency for PNG/GIF
			if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
				imagealphablending($thumbImage, false);
				imagesavealpha($thumbImage, true);
				$transparent = imagecolorallocatealpha($thumbImage, 0, 0, 0, 127);
				imagefill($thumbImage, 0, 0, $transparent);
			}
			
			imagecopyresampled(
				$thumbImage, $croppedImage,
				0, 0, 0, 0,
				$maxWidth, $maxHeight,
				$cropWidth, $cropHeight
			);
			
			imagedestroy($croppedImage);
		} else {
			// Calculate dimensions for fit-to-container resize (no cropping)
			$ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
			$thumbWidth = (int)($originalWidth * $ratio);
			$thumbHeight = (int)($originalHeight * $ratio);
			
			// Create thumbnail image
			$thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
			
			// Preserve transparency for PNG/GIF
			if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
				imagealphablending($thumbImage, false);
				imagesavealpha($thumbImage, true);
				$transparent = imagecolorallocatealpha($thumbImage, 0, 0, 0, 127);
				imagefill($thumbImage, 0, 0, $transparent);
			}
			
			imagecopyresampled(
				$thumbImage, $sourceImage,
				0, 0, 0, 0,
				$thumbWidth, $thumbHeight,
				$originalWidth, $originalHeight
			);
		}

		// Save the thumbnail
		$success = false;
		
		// Determine quality if not provided
		if ($quality === null) {
			$quality = $imageType === IMAGETYPE_JPEG ? 85 : 9; // JPEG: 0-100, PNG: 0-9 (compression)
		}
		
		switch ($imageType) {
			case IMAGETYPE_JPEG:
				$success = imagejpeg($thumbImage, $destPath, $quality);
				break;
			case IMAGETYPE_PNG:
				// For PNG, quality is compression level (0-9)
				$compression = min(9, max(0, $quality));
				$success = imagepng($thumbImage, $destPath, $compression);
				break;
			case IMAGETYPE_GIF:
				$success = imagegif($thumbImage, $destPath);
				break;
			case IMAGETYPE_WEBP:
				$success = imagewebp($thumbImage, $destPath, $quality);
				break;
			case IMAGETYPE_BMP:
				$success = imagebmp($thumbImage, $destPath);
				break;
		}

		// Clean up
		imagedestroy($sourceImage);
		imagedestroy($thumbImage);

		if (!$success) {
			throw new \RuntimeException("Failed to save thumbnail to: {$destPath}");
		}

		// Set appropriate permissions
		chmod($destPath, 0644);

		return $destPath;
	}

	/**
	 * Correct image orientation based on EXIF data
	 * This method should be called before any image operations
	 */
	private function correctImageOrientation(&$image, string $sourcePath): void
	{
		if (!function_exists('exif_read_data')) {
			return; // EXIF extension not available
		}
		
		try {
			$exif = @exif_read_data($sourcePath);
			if ($exif === false || !isset($exif['Orientation'])) {
				return;
			}
			
			$orientation = $exif['Orientation'];
			
			switch ($orientation) {
				case 2:
					// Horizontal flip
					imageflip($image, IMG_FLIP_HORIZONTAL);
					break;
				case 3:
					// Rotate 180 degrees
					$image = imagerotate($image, 180, 0);
					break;
				case 4:
					// Vertical flip
					imageflip($image, IMG_FLIP_VERTICAL);
					break;
				case 5:
					// Rotate 90 degrees and flip vertically
					$image = imagerotate($image, -90, 0);
					imageflip($image, IMG_FLIP_VERTICAL);
					break;
				case 6:
					// Rotate 90 degrees
					$image = imagerotate($image, -90, 0);
					break;
				case 7:
					// Rotate 90 degrees and flip horizontally
					$image = imagerotate($image, 90, 0);
					imageflip($image, IMG_FLIP_HORIZONTAL);
					break;
				case 8:
					// Rotate 270 degrees
					$image = imagerotate($image, 90, 0);
					break;
			}
		} catch (\Exception $e) {
			// Silently fail on EXIF errors, proceed without orientation correction
			error_log("EXIF orientation correction failed: " . $e->getMessage());
		}
	}
	
	/**
	 * Create a square thumbnail (convenience method)
	 */
	public function createSquareThumbnail(
		string $sourcePath,
		?string $destPath = null,
		int $size = 200,
		int $quality = null
	): string {
		return $this->createThumbnail($sourcePath, $destPath, $size, $size, true, $quality);
	}
		
    /**
     * Fix image orientation based on EXIF data
     */
    private function correctImageOrientationAlt(string $imagePath): void
    {
        if (function_exists('exif_read_data') && exif_imagetype($imagePath) === IMAGETYPE_JPEG) {
            $exif = @exif_read_data($imagePath);
            if ($exif && isset($exif['Orientation'])) {
                $image = imagecreatefromjpeg($imagePath);
                
                switch ($exif['Orientation']) {
                    case 3:
                        $image = imagerotate($image, 180, 0);
                        break;
                    case 6:
                        $image = imagerotate($image, -90, 0);
                        break;
                    case 8:
                        $image = imagerotate($image, 90, 0);
                        break;
                }
                
                imagejpeg($image, $imagePath);
                imagedestroy($image);
            }
        }
    }
    
    private function generateOutputPath(
        string $sourcePath, 
        string $sizeName, 
        int $width, 
        int $height
    ): string {
        $info = pathinfo($sourcePath);
        $ext = $this->outputFormat ?? $info['extension'] ?? 'jpg';
        
        return sprintf(
            '%s/%s/%s_%s_%dx%d.%s',
            $info['dirname'],
            $this->thumbDir,
            $info['filename'],
            $sizeName,
            $width,
            $height,
            $ext
        );
    }
}

#	// Usage
#	$thumbnailer = new ImageThumbnailer('jpg');
#	$sizes = [
#	    'square' => ['width' => 150, 'height' => 150, 'crop' => true],
#	    'medium' => ['width' => 400, 'height' => 300, 'crop' => false],
#	    'large' => ['width' => 1024, 'height' => 768, 'crop' => false, 'quality' => 90]
#	];
#	
#	$results = $thumbnailer->createThumbnails('/path/to/image.jpg', $sizes);

#	// Basic usage
#	$thumbPath = $imageProcessor->createThumbnail(
#	    '/path/to/image.jpg',
#	    '/path/to/thumbnail.jpg',
#	    200,
#	    200,
#	    false,
#	    85
#	);
#	
#	// Create square cropped thumbnail
#	$squareThumb = $imageProcessor->createSquareThumbnail(
#	    '/path/to/image.jpg',
#	    '/path/to/square-thumb.jpg',
#	    150
#	);
#	
#	// Create multiple sizes
#	$thumbnails = $imageProcessor->createThumbnails('/path/to/image.jpg', [
#	    ['width' => 100, 'height' => 100, 'crop' => true],
#	    ['width' => 300, 'height' => 200, 'crop' => false, 'quality' => 90],
#	    ['width' => 600, 'height' => 400, 'crop' => false],
#	]);
#	
#	// With custom suffix
#	$thumbnails = $imageProcessor->createThumbnails(
#	    '/path/to/image.jpg',
#	    [['width' => 200, 'height' => 200]],
#	    null,
#	    '_custom'
#	);
#	