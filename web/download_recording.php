<?php
require_once 'db.php';

// Adjust this path if recordings are stored in a custom directory
const ASTERISK_MONITOR_DIR = '/var/spool/asterisk/monitor';
$uniqueid = $_GET['uniqueid'] ?? '';

if (!$uniqueid || !preg_match('/^[A-Za-z0-9_\-.]+$/', $uniqueid)) {
    http_response_code(400);
    echo 'Solicitud inválida';
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, uniqueid, call_end_time FROM call_logs WHERE uniqueid = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$uniqueid]);
    $call = $stmt->fetch();
} catch (PDOException $e) {
    error_log('download_recording.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo 'No se puede acceder a la base de datos';
    exit;
}

if (!$call) {
    http_response_code(404);
    echo 'Grabación no encontrada';
    exit;
}

$pathsToTry = [];
$endTime = null;
if (!empty($call['call_end_time'])) {
    try {
        $endTime = new DateTime($call['call_end_time']);
    } catch (Exception $e) {
        $endTime = null;
    }
}

if ($endTime) {
    $patterns = [
        $endTime->format('Y/m/d'),
        $endTime->format('Y/m'),
        $endTime->format('Ymd'),
        $endTime->format('Y-m-d')
    ];
    foreach ($patterns as $pattern) {
        $pathsToTry[] = rtrim(ASTERISK_MONITOR_DIR, '/'). '/' . $pattern . '/' . $uniqueid;
    }
}

$pathsToTry[] = rtrim(ASTERISK_MONITOR_DIR, '/') . '/' . $uniqueid;

$extensions = ['.wav', '.WAV', '.mp3', '.MP3', '.gsm', '.ogg', '.ulaw', '.alaw'];
$recordingPath = null;

foreach ($pathsToTry as $basePath) {
    foreach ($extensions as $ext) {
        $candidate = $basePath . $ext;
        if (is_file($candidate)) {
            $recordingPath = $candidate;
            break 2;
        }
    }

    $dirName = dirname($basePath);
    if (is_dir($dirName)) {
        $pattern = basename($basePath);
        foreach ($extensions as $ext) {
            foreach (glob($dirName . '/*' . $pattern . '*' . $ext) as $match) {
                if (is_file($match)) {
                    $recordingPath = $match;
                    break 3;
                }
            }
        }
    }
}

if (!$recordingPath) {
    http_response_code(404);
    echo 'Grabación no disponible';
    exit;
}

$filename = basename($recordingPath);
header('Content-Description: File Transfer');
header('Content-Type: audio/*');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($recordingPath));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($recordingPath);
exit;

