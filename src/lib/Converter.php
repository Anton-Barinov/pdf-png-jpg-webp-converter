<?php
declare(strict_types=1);

namespace App;

/**
 * Universal Offline File Converter & Optimizer
 * Operates strictly with local Linux CLI utilities.
 */
class Converter
{
    private const BIN_PDFTOPPM   = '/usr/bin/pdftoppm';
    private const BIN_PDFSEPARATE = '/usr/bin/pdfseparate';
    private const BIN_PDFUNITE   = '/usr/bin/pdfunite';
    private const BIN_OPTIPNG    = '/usr/bin/optipng';
    private const BIN_JPEGOPTIM  = '/usr/bin/jpegoptim';
    private const BIN_CWEBP      = '/usr/bin/cwebp';
    private const BIN_DWEBP      = '/usr/bin/dwebp';
    private const BIN_GS         = '/usr/bin/gs';
    private const BIN_CONVERT    = '/usr/bin/convert';
    private const BIN_QPDF       = '/usr/bin/qpdf';

    /**
     * Convert raster image between PNG, JPG, and WebP
     */
    public static function convertImage(string $src, string $dst, string $targetFormat, int $quality = 90): bool
    {
        $targetFormat = strtolower($targetFormat);
        if ($targetFormat === 'jpeg') {
            $targetFormat = 'jpg';
        }

        $srcMime = self::detectMime($src);

        // WebP -> PNG or JPG
        if ($srcMime === 'image/webp') {
            if ($targetFormat === 'png') {
                $cmd = sprintf('%s %s -o %s 2>&1', self::BIN_DWEBP, escapeshellarg($src), escapeshellarg($dst));
                return self::execCheck($cmd);
            }
            if ($targetFormat === 'jpg') {
                $tempPng = $dst . '.tmp.png';
                $cmd1 = sprintf('%s %s -o %s 2>&1', self::BIN_DWEBP, escapeshellarg($src), escapeshellarg($tempPng));
                if (self::execCheck($cmd1)) {
                    $cmd2 = sprintf('%s %s -background white -flatten -quality %d %s 2>&1',
                        self::BIN_CONVERT, escapeshellarg($tempPng), $quality, escapeshellarg($dst));
                    $res = self::execCheck($cmd2);
                    @unlink($tempPng);
                    return $res;
                }
                return false;
            }
        }

        // To WebP
        if ($targetFormat === 'webp') {
            $cmd = sprintf('%s -q %d %s -o %s 2>&1',
                self::BIN_CWEBP, $quality, escapeshellarg($src), escapeshellarg($dst));
            return self::execCheck($cmd);
        }

        // PNG <-> JPG
        if ($targetFormat === 'jpg') {
            // Flatten transparent background to white when converting PNG to JPG
            $cmd = sprintf('%s %s -background white -flatten -quality %d %s 2>&1',
                self::BIN_CONVERT, escapeshellarg($src), $quality, escapeshellarg($dst));
            return self::execCheck($cmd);
        }

        if ($targetFormat === 'png') {
            $cmd = sprintf('%s %s %s 2>&1',
                self::BIN_CONVERT, escapeshellarg($src), escapeshellarg($dst));
            return self::execCheck($cmd);
        }

        throw new \InvalidArgumentException("Неподдерживаемый формат: $targetFormat");
    }

    /**
     * Lossless image optimization
     */
    public static function optimizeImage(string $src, string $dst): bool
    {
        $mime = self::detectMime($src);
        copy($src, $dst);

        if ($mime === 'image/png') {
            $cmd = sprintf('%s -quiet -o2 -strip all %s 2>&1', self::BIN_OPTIPNG, escapeshellarg($dst));
            return self::execCheck($cmd);
        }

        if ($mime === 'image/jpeg') {
            $cmd = sprintf('%s --strip-all -q %s 2>&1', self::BIN_JPEGOPTIM, escapeshellarg($dst));
            return self::execCheck($cmd);
        }

        if ($mime === 'image/webp') {
            $cmd = sprintf('%s -lossless -q 100 %s -o %s 2>&1',
                self::BIN_CWEBP, escapeshellarg($src), escapeshellarg($dst));
            return self::execCheck($cmd);
        }

        return false;
    }

    /**
     * Convert PDF to individual images (PNG or JPG)
     * @return string[] Array of generated image paths
     */
    public static function pdfToImages(string $srcPdf, string $outputDir, string $format = 'png', int $dpi = 150): array
    {
        $format = strtolower($format) === 'jpg' ? 'jpeg' : 'png';
        $prefix = rtrim($outputDir, '/') . '/page';
        $flag = $format === 'jpeg' ? '-jpeg' : '-png';

        $cmd = sprintf('%s %s -r %d %s %s 2>&1',
            self::BIN_PDFTOPPM, $flag, $dpi, escapeshellarg($srcPdf), escapeshellarg($prefix));

        if (!self::execCheck($cmd)) {
            throw new \RuntimeException("Ошибка конвертации PDF в изображения");
        }

        $files = glob($prefix . '-*.' . ($format === 'jpeg' ? 'jpg' : 'png'));
        if (!$files) {
            // pdftoppm might name with .jpeg
            $files = glob($prefix . '-*.*');
        }

        natsort($files);
        return array_values($files);
    }

    /**
     * Merge multiple images into a single PDF document
     */
    public static function imagesToPdf(array $imagePaths, string $outputPdf): bool
    {
        if (empty($imagePaths)) {
            throw new \InvalidArgumentException("Список изображений пуст");
        }

        $escaped = array_map('escapeshellarg', $imagePaths);
        $cmd = sprintf('%s %s %s 2>&1',
            self::BIN_CONVERT, implode(' ', $escaped), escapeshellarg($outputPdf));

        return self::execCheck($cmd);
    }

    /**
     * Optimize / compress PDF size
     * Presets: 'ebook' (150 DPI, ideal), 'screen' (72 DPI, smallest), 'printer' (300 DPI)
     */
    public static function optimizePdf(string $srcPdf, string $dstPdf, string $preset = 'ebook'): bool
    {
        $validPresets = ['screen', 'ebook', 'printer', 'prepress', 'default'];
        if (!in_array($preset, $validPresets, true)) {
            $preset = 'ebook';
        }

        $cmd = sprintf(
            '%s -dSAFER -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/%s -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s 2>&1',
            self::BIN_GS,
            $preset,
            escapeshellarg($dstPdf),
            escapeshellarg($srcPdf)
        );

        if (self::execCheck($cmd) && file_exists($dstPdf) && filesize($dstPdf) > 0) {
            // Check if the compressed version is actually smaller
            if (filesize($dstPdf) >= filesize($srcPdf)) {
                // If ghostscript did not make it smaller, try linearizing with qpdf
                $qpdfCmd = sprintf('%s --linearize %s %s 2>&1',
                    self::BIN_QPDF, escapeshellarg($srcPdf), escapeshellarg($dstPdf));
                @unlink($dstPdf);
                self::execCheck($qpdfCmd);
            }
            return true;
        }

        return false;
    }

    /**
     * Split PDF into separate pages
     * @return string[] Array of generated single-page PDF paths
     */
    public static function splitPdf(string $srcPdf, string $outputDir): array
    {
        $template = rtrim($outputDir, '/') . '/page-%d.pdf';
        $cmd = sprintf('%s %s %s 2>&1',
            self::BIN_PDFSEPARATE, escapeshellarg($srcPdf), escapeshellarg($template));

        if (!self::execCheck($cmd)) {
            throw new \RuntimeException("Ошибка разделения PDF");
        }

        $files = glob(rtrim($outputDir, '/') . '/page-*.pdf');
        natsort($files);
        return array_values($files);
    }

    /**
     * Merge multiple PDFs into one
     */
    public static function mergePdfs(array $pdfPaths, string $outputPdf): bool
    {
        if (count($pdfPaths) < 2) {
            throw new \InvalidArgumentException("Для объединения требуется минимум 2 PDF файла");
        }

        $escaped = array_map('escapeshellarg', $pdfPaths);
        $cmd = sprintf('%s %s %s 2>&1',
            self::BIN_PDFUNITE, implode(' ', $escaped), escapeshellarg($outputPdf));

        return self::execCheck($cmd);
    }

    /**
     * Detect real MIME type via magic bytes
     */
    public static function detectMime(string $filePath): string
    {
        if (!file_exists($filePath)) {
            return '';
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        return (string)$mime;
    }

    private static function execCheck(string $cmd): bool
    {
        $timedCmd = sprintf('/usr/bin/timeout 60s %s', $cmd);
        \exec($timedCmd, $output, $returnCode);
        return $returnCode === 0;
    }
}
