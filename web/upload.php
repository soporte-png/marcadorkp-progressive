<?php
/**
 * Procesador de carga de leads desde CSV
 * Incluye validación robusta, sanitización y límites de seguridad
 */
require_once 'db.php';

// Configuración de límites
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('MAX_LEADS_PER_CAMPAIGN', 100000);

/**
 * Sanitiza y valida un número de teléfono
 */
function sanitize_phone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', trim($phone));
    return (strlen($phone) >= 7 && strlen($phone) <= 20) ? $phone : null;
}

/**
 * Sanitiza texto general
 */
function sanitize_text($text, $max_length = 100) {
    return mb_substr(trim($text), 0, $max_length);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    
    // Validar campos obligatorios
    $campaignName = sanitize_text($_POST['campaign_name'] ?? '', 100);
    $queueName = sanitize_text($_POST['queue_name'] ?? '', 50);
    
    if (empty($campaignName) || empty($queueName)) {
        die("Error: El nombre de la campaña y la cola son obligatorios.");
    }

    // Validar archivo subido
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("Error al subir el archivo. Código: " . $file['error']);
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        die("Error: El archivo es demasiado grande. Máximo: " . (MAX_FILE_SIZE / 1024 / 1024) . " MB");
    }

    $mime_type = mime_content_type($file['tmp_name']);
    if (!in_array($mime_type, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'])) {
        die("Error: El archivo debe ser un CSV válido.");
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // 1. Crear la campaña
        $stmt = $pdo->prepare("INSERT INTO campaigns (name, queue_name, status) VALUES (?, ?, 'paused')");
        $stmt->execute([$campaignName, $queueName]);
        $campaignId = $pdo->lastInsertId();

        // 2. Procesar el archivo CSV
        $handle = fopen($file['tmp_name'], "r");
        if (!$handle) {
            throw new Exception("No se pudo abrir el archivo CSV.");
        }

        $header = fgetcsv($handle, 10000, ",");
        if (!$header) {
            throw new Exception("El archivo CSV está vacío o es inválido.");
        }

        // Normalizar headers (trim y lowercase)
        $header = array_map(function($h) { return strtolower(trim($h)); }, $header);

        // Mapeo de columnas
        $phone_col = array_search('phone_number', $header);
        $fname_col = array_search('first_name', $header);
        $lname_col = array_search('last_name', $header);

        if ($phone_col === false) {
            throw new Exception("La columna 'phone_number' no se encontró en el CSV.");
        }

        $insertStmt = $pdo->prepare(
            "INSERT INTO leads (campaign_id, phone_number, first_name, last_name, status) VALUES (?, ?, ?, ?, 'pending')"
        );

        $lead_count = 0;
        $skipped = 0;
        $line_number = 1; // Header es línea 1

        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            $line_number++;
            
            if ($lead_count >= MAX_LEADS_PER_CAMPAIGN) {
                throw new Exception("Se alcanzó el límite máximo de leads por campaña (" . MAX_LEADS_PER_CAMPAIGN . ")");
            }

            $phone = sanitize_phone($data[$phone_col] ?? '');
            $fname = ($fname_col !== false) ? sanitize_text($data[$fname_col] ?? '', 50) : null;
            $lname = ($lname_col !== false) ? sanitize_text($data[$lname_col] ?? '', 50) : null;
            
            if ($phone) {
                try {
                    $insertStmt->execute([$campaignId, $phone, $fname, $lname]);
                    $lead_count++;
                } catch (PDOException $e) {
                    // Si hay error de duplicado u otro, lo registramos pero continuamos
                    error_log("Error insertando lead en línea $line_number: " . $e->getMessage());
                    $skipped++;
                }
            } else {
                $skipped++;
            }
        }
        fclose($handle);

        if ($lead_count === 0) {
            throw new Exception("No se pudo importar ningún lead válido del archivo.");
        }

        // Confirmar transacción
        $pdo->commit();
        
        // Redirigir con mensaje de éxito
        $message = "Campaña creada. Leads importados: $lead_count" . ($skipped > 0 ? " (omitidos: $skipped)" : "");
        header("Location: index.php?success=" . urlencode($message));
        exit();
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $pdo->rollBack();
        error_log("Error en upload.php: " . $e->getMessage());
        die("Error al procesar el archivo: " . htmlspecialchars($e->getMessage()));
    }
}

// Si no es POST o no hay archivo, redirigir
header('Location: index.php');
exit();
?>
