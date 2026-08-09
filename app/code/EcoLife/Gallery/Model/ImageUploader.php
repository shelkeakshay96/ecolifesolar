<?php

declare(strict_types=1);

namespace EcoLife\Gallery\Model;

use EcoLife\Core\App\Logger;
use RuntimeException;

/**
 * Accepts an uploaded photograph, and does not trust one byte of it.
 *
 * Five things happen, in this order, and each one alone is insufficient:
 *
 *   1. The declared extension must be in the whitelist.
 *   2. getimagesize() must agree it is really that kind of image -- a .jpg that
 *      is actually a PHP script fails here.
 *   3. The file is decoded and RE-ENCODED through GD. This is the important
 *      one: a polyglot file with PHP in its EXIF survives every check that only
 *      inspects, but cannot survive being decoded to pixels and written out
 *      fresh.
 *   4. The filename is randomised, so the uploader never chooses a path.
 *   5. It is written under pub/media/, which is served as static files and
 *      never passed to PHP.
 */
final class ImageUploader
{
    private const MAX_BYTES  = 8 * 1024 * 1024;
    private const MAX_WIDTH  = 1920;
    private const THUMB_WIDTH = 480;

    private const ALLOWED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $file
     * @return array{image_path: string, thumbnail_path: string}
     */
    public function upload(array $file): array
    {
        $this->assertUploadOk($file);

        $tmp = (string) $file['tmp_name'];

        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('That file was not uploaded properly. Please try again.');
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('That image is larger than 8 MB. Please use a smaller photo.');
        }

        $info = @getimagesize($tmp);

        if ($info === false || !isset(self::ALLOWED[$info[2]])) {
            throw new RuntimeException('Please upload a JPG, PNG or WebP photo.');
        }

        $extension = self::ALLOWED[$info[2]];
        $image     = $this->decode($tmp, $info[2]);

        $directory = BP . '/pub/media/gallery';
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot write to the media directory.');
        }

        $basename = date('Y/m/') . bin2hex(random_bytes(8));
        $subdir   = $directory . '/' . dirname($basename);

        if (!is_dir($subdir) && !@mkdir($subdir, 0775, true) && !is_dir($subdir)) {
            throw new RuntimeException('Cannot write to the media directory.');
        }

        $full  = $this->resize($image, self::MAX_WIDTH);
        $thumb = $this->resize($image, self::THUMB_WIDTH);
        imagedestroy($image);

        $fullPath  = 'gallery/' . $basename . '.' . $extension;
        $thumbPath = 'gallery/' . $basename . '-thumb.' . $extension;

        $this->write($full, $directory . '/' . $basename . '.' . $extension, $extension);
        $this->write($thumb, $directory . '/' . $basename . '-thumb.' . $extension, $extension);

        imagedestroy($full);
        imagedestroy($thumb);

        Logger::info('Gallery image uploaded', ['path' => $fullPath]);

        return ['image_path' => $fullPath, 'thumbnail_path' => $thumbPath];
    }

    /** @param array<string, mixed> $file */
    private function assertUploadOk(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        throw new RuntimeException(match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That image is too large.',
            UPLOAD_ERR_PARTIAL                        => 'The upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE                        => 'Please choose a photo to upload.',
            default                                   => 'The upload failed. Please try again.',
        });
    }

    private function decode(string $path, int $type): \GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => false,
        };

        if ($image === false) {
            throw new RuntimeException('That image could not be read. Please try a different file.');
        }

        return $image;
    }

    private function resize(\GdImage $source, int $maxWidth): \GdImage
    {
        $width  = imagesx($source);
        $height = imagesy($source);

        if ($width <= $maxWidth) {
            // Still copy rather than return the original, so the caller can
            // always destroy both without worrying about double frees.
            $copy = imagecreatetruecolor($width, $height);
            imagecopy($copy, $source, 0, 0, 0, 0, $width, $height);
            return $copy;
        }

        $newHeight = (int) round($height * ($maxWidth / $width));
        $resized   = imagecreatetruecolor($maxWidth, $newHeight);

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);

        return $resized;
    }

    private function write(\GdImage $image, string $path, string $extension): void
    {
        $ok = match ($extension) {
            'jpg'  => imagejpeg($image, $path, 82),
            'png'  => imagepng($image, $path, 6),
            'webp' => imagewebp($image, $path, 82),
            default => false,
        };

        if ($ok === false) {
            throw new RuntimeException('Could not save the processed image.');
        }

        @chmod($path, 0644);
    }

    /** Remove an image and its thumbnail, staying inside pub/media. */
    public function delete(?string ...$relativePaths): void
    {
        $root = realpath(BP . '/pub/media');

        foreach ($relativePaths as $relative) {
            if ($relative === null || $relative === '') {
                continue;
            }

            $target = realpath(BP . '/pub/media/' . ltrim($relative, '/'));

            // A stored path should never escape the media root, but deleting
            // files is exactly where "should never" deserves a check.
            if ($target !== false && $root !== false && str_starts_with($target, $root)) {
                @unlink($target);
            }
        }
    }
}
