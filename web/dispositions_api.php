<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        handle_list($pdo);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = $input['action'] ?? 'save';

    switch ($action) {
        case 'save':
            handle_save($pdo, $input);
            break;
        case 'toggle':
            handle_toggle($pdo, $input);
            break;
        case 'delete':
            handle_delete($pdo, $input);
            break;
        default:
            throw new InvalidArgumentException('Acción no soportada');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handle_list(PDO $pdo): void
{
    $scope = $_GET['scope'] ?? 'all';
    $query = 'SELECT id, label, description, is_active, sort_order, created_at, updated_at
              FROM disposition_catalog';
    $params = [];

    if ($scope === 'active') {
        $query .= ' WHERE is_active = 1';
    }

    $query .= ' ORDER BY sort_order ASC, label ASC';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $rows]);
}

function handle_save(PDO $pdo, array $input): void
{
    $id = isset($input['id']) ? (int)$input['id'] : null;
    $label = trim($input['label'] ?? '');
    $description = trim($input['description'] ?? '');
    $sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;
    $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;

    if ($label === '') {
        throw new InvalidArgumentException('El nombre de la disposición es obligatorio.');
    }

    if ($id) {
        $stmt = $pdo->prepare('UPDATE disposition_catalog
            SET label = ?, description = ?, sort_order = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?');
        $stmt->execute([$label, $description !== '' ? $description : null, $sortOrder, $isActive ? 1 : 0, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO disposition_catalog (label, description, sort_order, is_active)
            VALUES (?, ?, ?, ?)');
        $stmt->execute([$label, $description !== '' ? $description : null, $sortOrder, $isActive ? 1 : 0]);
        $id = (int)$pdo->lastInsertId();
    }

    echo json_encode(['success' => true, 'id' => $id]);
}

function handle_toggle(PDO $pdo, array $input): void
{
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) {
        throw new InvalidArgumentException('ID inválido');
    }

    $stmt = $pdo->prepare('UPDATE disposition_catalog SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW() WHERE id = ?');
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
}

function handle_delete(PDO $pdo, array $input): void
{
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) {
        throw new InvalidArgumentException('ID inválido');
    }

    $exists = $pdo->prepare('SELECT COUNT(*) FROM agent_dispositions WHERE disposition_id = ?');
    $exists->execute([$id]);
    if ($exists->fetchColumn() > 0) {
        throw new RuntimeException('No se puede eliminar porque existen registros asociados. Inactívela en su lugar.');
    }

    $stmt = $pdo->prepare('DELETE FROM disposition_catalog WHERE id = ?');
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
}
?>
