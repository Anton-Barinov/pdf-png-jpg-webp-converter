<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../private/lib/Converter.php';
require_once __DIR__ . '/../private/lib/Cleanup.php';

use App\Converter;
use App\Cleanup;

// Configuration
$config = require_once __DIR__ . '/config.php';
$baseStorageDir = $config['storage_dir'];
if (!is_dir($baseStorageDir)) {
    @mkdir($baseStorageDir, 0750, true);
}

// Background auto-purge expired sessions on each API invocation
Cleanup::purgeExpired($baseStorageDir, (int)$config['ttl_seconds']);

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'upload':
            handleUpload($baseStorageDir, $config);
            break;

        case 'convert':
            handleConvert($baseStorageDir, $config);
            break;

        case 'download':
            handleDownload($baseStorageDir);
            break;

        case 'delete':
            handleDelete($baseStorageDir);
            break;

        case 'status':
            handleStatus($baseStorageDir);
            break;

        default:
            jsonResponse(['ok' => false, 'error' => 'Неизвестное действие']);
    }
} catch (\Throwable $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
}

/**
 * Handle Multi-file Upload
 */
function handleUpload(string $baseStorageDir, array $config = []): void
{
    if (empty($_FILES['files'])) {
        jsonResponse(['ok' => false, 'error' => 'Файлы не выбраны']);
    }

    $ttlSeconds = (int)($config['ttl_seconds'] ?? 300);
    $maxFileSize = ((int)($config['max_file_size_mb'] ?? 100)) * 1024 * 1024;
    $maxFilesCount = (int)($config['max_session_files'] ?? 50);

    $sessionId = $_POST['session_id'] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $sessionId)) {
        $sessionId = bin2hex(random_bytes(16));
    }

    $sessionDir = $baseStorageDir . '/' . $sessionId;
    $uploadsDir = $sessionDir . '/uploads';
    $outputDir  = $sessionDir . '/output';

    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0750, true);
    }
    if (!is_dir($outputDir)) {
        @mkdir($outputDir, 0750, true);
    }

    $metaFile = $sessionDir . '/meta.json';
    $meta = [
        'created_at' => time(),
        'expires_at' => time() + $ttlSeconds,
        'files' => []
    ];
    if (file_exists($metaFile)) {
        $existing = json_decode((string)file_get_contents($metaFile), true);
        if (is_array($existing)) {
            $meta = $existing;
        }
    }

    $uploadedFiles = [];
    $rawFiles = $_FILES['files'];

    // Normalize multiple file arrays
    $count = is_array($rawFiles['name']) ? count($rawFiles['name']) : 1;

    for ($i = 0; $i < $count; $i++) {
        $name     = is_array($rawFiles['name']) ? $rawFiles['name'][$i] : $rawFiles['name'];
        $tmpName  = is_array($rawFiles['tmp_name']) ? $rawFiles['tmp_name'][$i] : $rawFiles['tmp_name'];
        $error    = is_array($rawFiles['error']) ? $rawFiles['error'][$i] : $rawFiles['error'];
        $size     = is_array($rawFiles['size']) ? $rawFiles['size'][$i] : $rawFiles['size'];

        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmpName)) {
            continue;
        }

        // Limit per file & max files per session
        if ($size > $maxFileSize || count($meta['files']) >= $maxFilesCount) {
            @unlink($tmpName);
            continue;
        }

        // Sanitize filename & extension whitelist
        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name);
        $fileId   = bin2hex(random_bytes(8));
        $ext      = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        $allowedExts = ['png', 'jpg', 'webp', 'pdf'];
        if (!in_array($ext, $allowedExts, true)) {
            continue;
        }

        $storageName = $fileId . '_' . $safeName;
        $destPath = $uploadsDir . '/' . $storageName;

        if (move_uploaded_file($tmpName, $destPath)) {
            $realMime = Converter::detectMime($destPath);
            $allowedMimes = ['image/png', 'image/jpeg', 'image/webp', 'application/pdf'];
            if (!in_array($realMime, $allowedMimes, true)) {
                @unlink($destPath);
                continue;
            }

            $typeCategory = 'other';
            if (str_starts_with($realMime, 'image/')) {
                $typeCategory = 'image';
            } elseif ($realMime === 'application/pdf') {
                $typeCategory = 'pdf';
            }

            $fileInfo = [
                'id'           => $fileId,
                'original_name'=> $name,
                'safe_name'    => $safeName,
                'storage_name' => $storageName,
                'size'         => $size,
                'mime'         => $realMime,
                'ext'          => $ext,
                'category'     => $typeCategory,
            ];

            $uploadedFiles[] = $fileInfo;
            $meta['files'][$fileId] = $fileInfo;
        }
    }

    file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    jsonResponse([
        'ok'            => true,
        'session_id'    => $sessionId,
        'created_at'    => $meta['created_at'],
        'expires_at'    => $meta['expires_at'],
        'ttl_remaining' => max(0, $meta['expires_at'] - time()),
        'files'         => array_values($meta['files']),
    ]);
}

/**
 * Handle Conversion Execution
 */
function handleConvert(string $baseStorageDir): void
{
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $sessionId = $input['session_id'] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $sessionId)) {
        jsonResponse(['ok' => false, 'error' => 'Некорректный идентификатор сессии']);
    }

    $sessionDir = $baseStorageDir . '/' . $sessionId;
    $metaFile   = $sessionDir . '/meta.json';
    if (!file_exists($metaFile)) {
        jsonResponse(['ok' => false, 'error' => 'Сессия не найдена или истекло время (5 мин)']);
    }

    $meta = json_decode((string)file_get_contents($metaFile), true);
    $uploadsDir = $sessionDir . '/uploads';
    $outputDir  = $sessionDir . '/output';

    $actionType = $input['action_type'] ?? 'single'; // single, batch_format, merge_pdf, images_to_pdf, optimize_all, split_pdf, optimize_pdf
    $targetFormat = strtolower($input['target_format'] ?? '');
    $selectedIds  = $input['file_ids'] ?? [];
    $options      = $input['options'] ?? [];

    $results = [];
    $usedNames = [];

    // Filter files
    $filesToProcess = [];
    foreach ($meta['files'] as $fId => $fInfo) {
        if (empty($selectedIds) || in_array($fId, $selectedIds, true)) {
            $filesToProcess[$fId] = $fInfo;
        }
    }

    if (empty($filesToProcess)) {
        jsonResponse(['ok' => false, 'error' => 'Нет файлов для обработки']);
    }

    // 0. Custom batch / Mixed per-file target conversion
    if ($actionType === 'custom_batch' || !empty($options['format_per_file'])) {
        $quality = (int)($options['quality'] ?? 90);
        foreach ($filesToProcess as $fId => $fInfo) {
            $tgtFmt = $options['format_per_file'][$fId] ?? $targetFormat ?: 'webp';
            $srcPath = $uploadsDir . '/' . $fInfo['storage_name'];
            $baseName = pathinfo($fInfo['original_name'], PATHINFO_FILENAME);

            if ($fInfo['category'] === 'image') {
                if ($tgtFmt === 'opt' || $tgtFmt === 'optimize') {
                    $ext = $fInfo['ext'];
                    $outName = $baseName . '_opt.' . $ext;
                    if (isset($usedNames[$outName])) $outName = $baseName . '_' . $fId . '_opt.' . $ext;
                    $usedNames[$outName] = true;
                    $outPath = $outputDir . '/' . $outName;

                    if (Converter::optimizeImage($srcPath, $outPath)) {
                        $origSize = (int)$fInfo['size'];
                        $newSize = filesize($outPath);
                        $results[] = [
                            'source_id' => $fId,
                            'name' => $outName,
                            'orig_size' => $origSize,
                            'size' => $newSize,
                            'saved_pct' => $origSize > 0 ? round((($origSize - $newSize) / $origSize) * 100, 1) : 0,
                            'download_url' => 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($outName),
                            'type' => $fInfo['mime'],
                        ];
                    }
                } else {
                    $outExt = $tgtFmt === 'jpeg' ? 'jpg' : $tgtFmt;
                    $outName = $baseName . '.' . $outExt;
                    if (isset($usedNames[$outName])) $outName = $baseName . '_' . $fId . '.' . $outExt;
                    $usedNames[$outName] = true;
                    $outPath = $outputDir . '/' . $outName;

                    if (Converter::convertImage($srcPath, $outPath, $tgtFmt, $quality)) {
                        $results[] = [
                            'source_id' => $fId,
                            'name' => $outName,
                            'orig_size' => (int)$fInfo['size'],
                            'size' => filesize($outPath),
                            'download_url' => 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($outName),
                            'type' => 'image/' . ($outExt === 'jpg' ? 'jpeg' : $outExt),
                        ];
                    }
                }
            } elseif ($fInfo['category'] === 'pdf') {
                if ($tgtFmt === 'pdf_opt' || $tgtFmt === 'optimize') {
                    $outName = $baseName . '_optimized.pdf';
                    if (isset($usedNames[$outName])) $outName = $baseName . '_' . $fId . '_opt.pdf';
                    $usedNames[$outName] = true;
                    $outPath = $outputDir . '/' . $outName;

                    if (Converter::optimizePdf($srcPath, $outPath, $options['preset'] ?? 'ebook')) {
                        $origSize = (int)$fInfo['size'];
                        $newSize = filesize($outPath);
                        $results[] = [
                            'source_id' => $fId,
                            'name' => $outName,
                            'orig_size' => $origSize,
                            'size' => $newSize,
                            'saved_pct' => $origSize > 0 ? round((($origSize - $newSize) / $origSize) * 100, 1) : 0,
                            'download_url' => 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($outName),
                            'type' => 'application/pdf',
                        ];
                    }
                } elseif ($tgtFmt === 'pdf_split') {
                    $splitSubDir = $outputDir . '/split_' . $fId;
                    @mkdir($splitSubDir, 0750, true);
                    $pages = Converter::splitPdf($srcPath, $splitSubDir);
                    if (!empty($pages)) {
                        $zipName = $baseName . '_pages.zip';
                        $zipPath = $outputDir . '/' . $zipName;
                        $zip = new \ZipArchive();
                        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                            foreach ($pages as $p) $zip->addFile($p, basename($p));
                            $zip->close();
                        }
                        $results[] = [
                            'source_id' => $fId,
                            'name' => $zipName,
                            'page_count' => count($pages),
                            'size' => file_exists($zipPath) ? filesize($zipPath) : 0,
                            'download_url' => 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($zipName),
                            'type' => 'application/zip',
                        ];
                    }
                } elseif ($tgtFmt === 'pdf_png' || $tgtFmt === 'pdf_jpg') {
                    $imgFormat = $tgtFmt === 'pdf_jpg' ? 'jpg' : 'png';
                    $pdfImgDir = $outputDir . '/pdf_img_' . $fId;
                    @mkdir($pdfImgDir, 0750, true);
                    $imgFiles = Converter::pdfToImages($srcPath, $pdfImgDir, $imgFormat, 150);
                    if (!empty($imgFiles)) {
                        if (count($imgFiles) === 1) {
                            $outName = $baseName . '.' . ($imgFormat === 'jpg' ? 'jpg' : 'png');
                            copy($imgFiles[0], $outputDir . '/' . $outName);
                            $results[] = [
                                'source_id' => $fId,
                                'name' => $outName,
                                'size' => filesize($outputDir . '/' . $outName),
                                'download_url' => 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($outName),
                                'type' => 'image/' . ($imgFormat === 'jpg' ? 'jpeg' : 'png'),
                            ];
                        } else {
                            $zipName = $baseName . '_images.zip';
                            $zipPath = $outputDir . '/' . $zipName;
                            $zip = new \ZipArchive();
                            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                                foreach ($imgFiles as $img) $zip->addFile($img, basename($img));
                                $zip->close();
                            }
                            $results[] = [
                                'source_id' => $fId,
                                'name' => $zipName,
                                'page_count' => count($imgFiles),
                                'size' => file_exists($zipPath) ? filesize($zipPath) : 0,
                                'download_url' => 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($zipName),
                                'type' => 'application/zip',
                            ];
                        }
                    }
                }
            }
        }
    }
    // 1. Merge images into a single PDF
    elseif ($actionType === 'images_to_pdf') {
        $imagePaths = [];
        foreach ($filesToProcess as $fInfo) {
            $path = $uploadsDir . '/' . $fInfo['storage_name'];
            if (file_exists($path)) {
                $imagePaths[] = $path;
            }
        }
        $outName = 'merged_images_' . date('Ymd_His') . '.pdf';
        $outPath = $outputDir . '/' . $outName;

        if (Converter::imagesToPdf($imagePaths, $outPath)) {
            $results[] = [
                'name'         => $outName,
                'size'         => filesize($outPath),
                'download_url' => 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($outName),
                'type'         => 'application/pdf',
            ];
        } else {
            jsonResponse(['ok' => false, 'error' => 'Ошибка объединения изображений в PDF']);
        }
    }
    // 2. Merge multiple PDFs into one
    elseif ($actionType === 'merge_pdf') {
        $pdfPaths = [];
        foreach ($filesToProcess as $fInfo) {
            $path = $uploadsDir . '/' . $fInfo['storage_name'];
            if (file_exists($path) && $fInfo['category'] === 'pdf') {
                $pdfPaths[] = $path;
            }
        }
        if (count($pdfPaths) < 2) {
            jsonResponse(['ok' => false, 'error' => 'Для объединения необходимо минимум 2 PDF документа']);
        }
        $outName = 'merged_document_' . date('Ymd_His') . '.pdf';
        $outPath = $outputDir . '/' . $outName;

        if (Converter::mergePdfs($pdfPaths, $outPath)) {
            $results[] = [
                'name'         => $outName,
                'size'         => filesize($outPath),
                'download_url' => 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($outName),
                'type'         => 'application/pdf',
            ];
        } else {
            jsonResponse(['ok' => false, 'error' => 'Ошибка склейки PDF документов']);
        }
    }
    // 3. Optimize PDF
    elseif ($actionType === 'optimize_pdf') {
        $preset = $options['preset'] ?? 'ebook';
        foreach ($filesToProcess as $fId => $fInfo) {
            if ($fInfo['category'] !== 'pdf') continue;
            $srcPath = $uploadsDir . '/' . $fInfo['storage_name'];
            $baseName = pathinfo($fInfo['original_name'], PATHINFO_FILENAME);
            $outName  = $baseName . '_optimized.pdf';
            $outPath  = $outputDir . '/' . $outName;

            if (Converter::optimizePdf($srcPath, $outPath, $preset)) {
                $origSize = (int)$fInfo['size'];
                $newSize  = filesize($outPath);
                $savedPct = $origSize > 0 ? round((($origSize - $newSize) / $origSize) * 100, 1) : 0;

                $results[] = [
                    'source_id'    => $fId,
                    'name'         => $outName,
                    'orig_size'    => $origSize,
                    'size'         => $newSize,
                    'saved_pct'    => $savedPct,
                    'download_url' => 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($outName),
                    'type'         => 'application/pdf',
                ];
            }
        }
    }
    // 4. Split PDF into separate pages
    elseif ($actionType === 'split_pdf') {
        foreach ($filesToProcess as $fId => $fInfo) {
            if ($fInfo['category'] !== 'pdf') continue;
            $srcPath = $uploadsDir . '/' . $fInfo['storage_name'];
            $baseName = pathinfo($fInfo['original_name'], PATHINFO_FILENAME);
            $splitSubDir = $outputDir . '/split_' . $fId;
            @mkdir($splitSubDir, 0750, true);

            $pages = Converter::splitPdf($srcPath, $splitSubDir);
            if (!empty($pages)) {
                // Pack into zip
                $zipName = $baseName . '_pages.zip';
                $zipPath = $outputDir . '/' . $zipName;
                $zip = new \ZipArchive();
                if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                    foreach ($pages as $p) {
                        $zip->addFile($p, basename($p));
                    }
                    $zip->close();
                }

                $results[] = [
                    'source_id'    => $fId,
                    'name'         => $zipName,
                    'page_count'   => count($pages),
                    'size'         => file_exists($zipPath) ? filesize($zipPath) : 0,
                    'download_url' => 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($zipName),
                    'type'         => 'application/zip',
                ];
            }
        }
    }
    // 5. PDF to Images (PNG / JPG)
    elseif ($actionType === 'pdf_to_images') {
        $imgFormat = $targetFormat ?: 'png';
        foreach ($filesToProcess as $fId => $fInfo) {
            if ($fInfo['category'] !== 'pdf') continue;
            $srcPath = $uploadsDir . '/' . $fInfo['storage_name'];
            $baseName = pathinfo($fInfo['original_name'], PATHINFO_FILENAME);
            $pdfImgDir = $outputDir . '/pdf_img_' . $fId;
            @mkdir($pdfImgDir, 0750, true);

            $imgFiles = Converter::pdfToImages($srcPath, $pdfImgDir, $imgFormat, 150);
            if (!empty($imgFiles)) {
                if (count($imgFiles) === 1) {
                    $singleImg = $imgFiles[0];
                    $outName = $baseName . '.' . ($imgFormat === 'jpg' ? 'jpg' : 'png');
                    copy($singleImg, $outputDir . '/' . $outName);
                    $results[] = [
                        'source_id'    => $fId,
                        'name'         => $outName,
                        'size'         => filesize($outputDir . '/' . $outName),
                        'download_url' => 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($outName),
                        'type'         => 'image/' . ($imgFormat === 'jpg' ? 'jpeg' : 'png'),
                    ];
                } else {
                    $zipName = $baseName . '_images.zip';
                    $zipPath = $outputDir . '/' . $zipName;
                    $zip = new \ZipArchive();
                    if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                        foreach ($imgFiles as $img) {
                            $zip->addFile($img, basename($img));
                        }
                        $zip->close();
                    }
                    $results[] = [
                        'source_id'    => $fId,
                        'name'         => $zipName,
                        'page_count'   => count($imgFiles),
                        'size'         => file_exists($zipPath) ? filesize($zipPath) : 0,
                        'download_url' => 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($zipName),
                        'type'         => 'application/zip',
                    ];
                }
            }
        }
    }
    // 6. Lossless Image Optimization
    elseif ($actionType === 'optimize_image' || $actionType === 'optimize_all') {
        foreach ($filesToProcess as $fId => $fInfo) {
            if ($fInfo['category'] !== 'image') continue;
            $srcPath = $uploadsDir . '/' . $fInfo['storage_name'];
            $baseName = pathinfo($fInfo['original_name'], PATHINFO_FILENAME);
            $ext      = $fInfo['ext'];
            $outName  = $baseName . '_opt.' . $ext;
            if (isset($usedNames[$outName])) {
                $outName = $baseName . '_' . $fInfo['ext'] . '_opt.' . $ext;
            }
            $usedNames[$outName] = true;
            $outPath  = $outputDir . '/' . $outName;

            if (Converter::optimizeImage($srcPath, $outPath)) {
                $origSize = (int)$fInfo['size'];
                $newSize  = filesize($outPath);
                $savedPct = $origSize > 0 ? round((($origSize - $newSize) / $origSize) * 100, 1) : 0;

                $results[] = [
                    'source_id'    => $fId,
                    'name'         => $outName,
                    'orig_size'    => $origSize,
                    'size'         => $newSize,
                    'saved_pct'    => $savedPct,
                    'download_url' => 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($outName),
                    'type'         => $fInfo['mime'],
                ];
            }
        }
    }
    // 7. Raster Image Conversion (PNG <-> JPG <-> WebP)
    else {
        // actionType = 'convert_format' or default
        $quality = (int)($options['quality'] ?? 90);
        foreach ($filesToProcess as $fId => $fInfo) {
            if ($fInfo['category'] !== 'image') continue;

            $tgtFmt = $targetFormat ?: ($options['format_per_file'][$fId] ?? 'webp');
            $srcPath = $uploadsDir . '/' . $fInfo['storage_name'];
            $baseName = pathinfo($fInfo['original_name'], PATHINFO_FILENAME);
            $outExt   = $tgtFmt === 'jpeg' ? 'jpg' : $tgtFmt;
            $outName  = $baseName . '.' . $outExt;
            if (isset($usedNames[$outName])) {
                $outName = $baseName . '_' . $fInfo['ext'] . '.' . $outExt;
            }
            $usedNames[$outName] = true;
            $outPath  = $outputDir . '/' . $outName;

            if (Converter::convertImage($srcPath, $outPath, $tgtFmt, $quality)) {
                $results[] = [
                    'source_id'    => $fId,
                    'name'         => $outName,
                    'orig_size'    => (int)$fInfo['size'],
                    'size'         => filesize($outPath),
                    'download_url' => 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($outName),
                    'type'         => 'image/' . ($outExt === 'jpg' ? 'jpeg' : $outExt),
                ];
            }
        }
    }

    if (empty($results)) {
        jsonResponse(['ok' => false, 'error' => 'Не удалось обработать файлы. Проверьте выбранные форматы.']);
    }

    // If multiple results, prepare an all-in-one ZIP archive
    $zipAllUrl = null;
    if (count($results) > 1) {
        $zipAllName = 'all_converted_' . date('His') . '.zip';
        $zipAllPath = $outputDir . '/' . $zipAllName;
        $zip = new \ZipArchive();
        if ($zip->open($zipAllPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($results as $res) {
                $filePath = $outputDir . '/' . $res['name'];
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, $res['name']);
                }
            }
            $zip->close();
            $zipAllUrl = 'api.php?action=download&session_id=' . $sessionId . '&file=' . urlencode($zipAllName);
        }
    }

    $meta['results'] = $results;
    $meta['zip_all_url'] = $zipAllUrl;
    file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    jsonResponse([
        'ok'            => true,
        'session_id'    => $sessionId,
        'expires_at'    => $meta['expires_at'],
        'ttl_remaining' => max(0, $meta['expires_at'] - time()),
        'results'       => $results,
        'zip_all_url'   => $zipAllUrl,
    ]);
}

/**
 * Handle File Download
 */
function handleDownload(string $baseStorageDir): void
{
    $sessionId = $_GET['session_id'] ?? '';
    $fileName  = $_GET['file'] ?? '';

    // Enforce strict alphanumeric + safe punctuation for session and file name
    if (!preg_match('/^[a-f0-9]{32}$/', $sessionId) || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $fileName)) {
        http_response_code(400);
        exit('Некорректный запрос');
    }

    // Prevent directory traversal using basename and strict realpath matching
    $safeFile = basename($fileName);
    $outputDir = realpath($baseStorageDir . '/' . $sessionId . '/output');
    if (!$outputDir || !is_dir($outputDir)) {
        http_response_code(404);
        exit('Сессия не найдена или срок хранения (5 минут) истек');
    }

    $filePath = realpath($outputDir . '/' . $safeFile);
    if (!$filePath || !file_exists($filePath) || !str_starts_with($filePath, $outputDir . DIRECTORY_SEPARATOR)) {
        http_response_code(404);
        exit('Файл не найден или срок его хранения (5 минут) истек');
    }

    $mime = Converter::detectMime($filePath) ?: 'application/octet-stream';
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . addslashes($safeFile) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

/**
 * Handle Instant Session Deletion
 */
function handleDelete(string $baseStorageDir): void
{
    $input = json_decode((string)file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? $_POST['session_id'] ?? '';

    if (!preg_match('/^[a-f0-9]{32}$/', $sessionId)) {
        jsonResponse(['ok' => false, 'error' => 'Некорректный идентификатор сессии']);
    }

    $deleted = Cleanup::deleteSession($baseStorageDir, $sessionId);
    jsonResponse(['ok' => true, 'deleted' => $deleted]);
}

/**
 * Handle Status & TTL Check
 */
function handleStatus(string $baseStorageDir): void
{
    $sessionId = $_GET['session_id'] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $sessionId)) {
        jsonResponse(['ok' => false, 'error' => 'Некорректный идентификатор сессии']);
    }

    $metaFile = $baseStorageDir . '/' . $sessionId . '/meta.json';
    if (!file_exists($metaFile)) {
        jsonResponse(['ok' => false, 'expired' => true]);
    }

    $meta = json_decode((string)file_get_contents($metaFile), true);
    $ttlRemaining = max(0, ($meta['expires_at'] ?? time()) - time());

    if ($ttlRemaining <= 0) {
        Cleanup::deleteSession($baseStorageDir, $sessionId);
        jsonResponse(['ok' => false, 'expired' => true]);
    }

    jsonResponse([
        'ok'            => true,
        'ttl_remaining' => $ttlRemaining,
        'created_at'    => $meta['created_at'] ?? 0,
        'expires_at'    => $meta['expires_at'] ?? 0,
        'files_count'   => count($meta['files'] ?? []),
    ]);
}

function jsonResponse(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
