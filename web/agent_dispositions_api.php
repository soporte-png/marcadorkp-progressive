<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ami_client.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}


$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? null;
if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Acción requerida']);
    exit;
}

try {
    switch ($action) {
        case 'pause':
            handle_pause_resume($input, true);
            break;
        case 'resume':
            handle_pause_resume($input, false);
            break;
        case 'save':
            handle_save($pdo, $input);
            break;
        default:
            throw new InvalidArgumentException('Acción no soportada');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function sanitize_string(string $value, int $maxLength): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }
    if (strlen($trimmed) > $maxLength) {
        return substr($trimmed, 0, $maxLength);
    }
    return $trimmed;
}

function handle_pause_resume(array $input, bool $pause): void
{
    $queue = sanitize_string((string)($input['queue_name'] ?? $input['queue'] ?? ''), 64);
    $extension = sanitize_string((string)($input['agent_extension'] ?? $input['extension'] ?? ''), 20);
    $reason = sanitize_string((string)($input['reason'] ?? ''), 120);

    if ($queue === '') {
        throw new InvalidArgumentException('La cola es obligatoria.');
    }

    if ($extension === '') {
        throw new InvalidArgumentException('La extensión es obligatoria.');
    }

    if ($reason === '') {
        $reason = $pause ? 'Disposition capture' : 'Disposition resume';
    }

    $result = ami_queue_pause($queue, $extension, $pause, $reason);
    if (!($result['success'] ?? false)) {
        $message = $result['message'] ?? 'Acción no ejecutada';
        throw new RuntimeException($message);
    }

    echo json_encode([
        'success' => true,
        'interface' => $result['interface'] ?? null,
        'paused' => $pause,
    ]);
}

function handle_save(PDO $pdo, array $input): void
{
    $uniqueId = sanitize_string((string)($input['call_uniqueid'] ?? ''), 64);
    $extension = sanitize_string((string)($input['agent_extension'] ?? ''), 20);
    $dispositionId = isset($input['disposition_id']) ? (int)$input['disposition_id'] : 0;
    $queue = sanitize_string((string)($input['queue_name'] ?? $input['queue'] ?? ''), 64);
    $campaignId = isset($input['campaign_id']) && $input['campaign_id'] !== '' ? (int)$input['campaign_id'] : null;
    $leadId = isset($input['lead_id']) && $input['lead_id'] !== '' ? (int)$input['lead_id'] : null;
    $notesRaw = trim((string)($input['notes'] ?? ''));
    $notes = $notesRaw !== '' ? mb_substr($notesRaw, 0, 2000) : null;

    if ($uniqueId === '') {
        throw new InvalidArgumentException('El identificador de la llamada es obligatorio.');
    }

    if ($extension === '') {
        throw new InvalidArgumentException('La extensión del agente es obligatoria.');
    }

    if ($dispositionId <= 0) {
        throw new InvalidArgumentException('La disposición seleccionada es inválida.');
    }

    $exists = $pdo->prepare('SELECT id FROM disposition_catalog WHERE id = ?');
    $exists->execute([$dispositionId]);
    if (!$exists->fetchColumn()) {
        throw new InvalidArgumentException('La disposición indicada no existe.');
    }

    $stmt = $pdo->prepare('INSERT INTO agent_dispositions
        (call_uniqueid, agent_extension, disposition_id, queue_name, campaign_id, lead_id, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            agent_extension = VALUES(agent_extension),
            disposition_id = VALUES(disposition_id),
            queue_name = VALUES(queue_name),
            campaign_id = VALUES(campaign_id),
            lead_id = VALUES(lead_id),
            notes = VALUES(notes),
            updated_at = CURRENT_TIMESTAMP');

    $stmt->execute([
        $uniqueId,
        $extension,
        $dispositionId,
        $queue !== '' ? $queue : null,
        $campaignId,
        $leadId,
        $notes,
    ]);

    $id = (int)$pdo->lastInsertId();
    if ($id === 0) {
        $probe = $pdo->prepare('SELECT id FROM agent_dispositions WHERE call_uniqueid = ?');
        $probe->execute([$uniqueId]);
        $id = (int)$probe->fetchColumn();
    }

    echo json_encode([
        'success' => true,
        'id' => $id,
    ]);
}
?>
