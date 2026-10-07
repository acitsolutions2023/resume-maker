<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Applicant photo helpers: every photo is center-cropped to a 2x2 ID picture
 * (600 x 600 px = 2 x 2 in at 300 dpi) and stored as JPEG.
 */
class Photo
{
    public const SIZE = 600;

    public const DIR = WRITEPATH . 'uploads/resumes/';

    public const MAX_BYTES = 8 * 1024 * 1024;

    public const MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Crops/resizes an uploaded image and saves it; returns the stored file name.
     */
    public static function store(string $source): string
    {
        if (! is_dir(self::DIR)) {
            mkdir(self::DIR, 0775, true);
        }
        $name = bin2hex(random_bytes(16)) . '.jpg';
        file_put_contents(self::DIR . $name, self::squareJpeg($source));

        return $name;
    }

    /**
     * Resolves a stored photo name to its path, or null when invalid/missing.
     */
    public static function path(?string $name): ?string
    {
        if ($name === null || $name === '' || ! preg_match('/^[\w.-]+\.(jpe?g|png|webp)$/i', $name)) {
            return null;
        }
        $path = self::DIR . basename($name);

        return is_file($path) ? $path : null;
    }

    public static function dataUri(?string $name): ?string
    {
        $path = self::path($name);

        return $path ? 'data:' . mime_content_type($path) . ';base64,' . base64_encode(file_get_contents($path)) : null;
    }

    /**
     * Returns the image center-cropped to a square, SIZE x SIZE, as JPEG bytes.
     */
    public static function squareJpeg(string $source): string
    {
        $info = @getimagesize($source);
        if (! $info || ! in_array($info['mime'], self::MIMES, true)) {
            throw new RuntimeException('Unsupported image.');
        }

        $src = match ($info['mime']) {
            'image/png'  => imagecreatefrompng($source),
            'image/webp' => imagecreatefromwebp($source),
            default      => imagecreatefromjpeg($source),
        };
        if (! $src) {
            throw new RuntimeException('Unable to read image.');
        }
        if ($info['mime'] === 'image/jpeg') {
            $src = self::applyExifOrientation($src, $source);
        }

        $w    = imagesx($src);
        $h    = imagesy($src);
        $side = min($w, $h);
        // Center horizontally; bias upward a little so faces in portrait shots are not cut.
        $sx = (int) (($w - $side) / 2);
        $sy = (int) (($h - $side) * ($h > $w ? 0.3 : 0.5));

        $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, self::SIZE, self::SIZE, $side, $side);
        imageresolution($dst, 300, 300);

        ob_start();
        imagejpeg($dst, null, 90);

        return (string) ob_get_clean();
    }

    public static function blankJpeg(): string
    {
        $img = imagecreatetruecolor(1, 1);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagejpeg($img);

        return (string) ob_get_clean();
    }

    /**
     * @param \GdImage $img
     *
     * @return \GdImage
     */
    private static function applyExifOrientation($img, string $source)
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($source);

        return match ((int) ($exif['Orientation'] ?? 1)) {
            3       => imagerotate($img, 180, 0),
            6       => imagerotate($img, -90, 0),
            8       => imagerotate($img, 90, 0),
            default => $img,
        };
    }
}
