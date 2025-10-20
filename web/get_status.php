<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

// Inicializar respuesta
$response = [
    'active_campaign_name' => '--',
    'available_agents' => 0,
    'dialing_calls' => 0,
    'connected_calls' => 0,
    'pending_leads' => 0
];

try {
    // Obtener campaña activa
    $stmt = $pdo->prepare("SELECT id, name, available_agents FROM campaigns WHERE status = 'active' LIMIT 1");
    $stmt->execute();
    $active_campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($active_campaign) {
        $campaign_id = (int)$active_campaign['id'];
        $response['active_campaign_name'] = $active_campaign['name'];
        $response['available_agents'] = (int)$active_campaign['available_agents'];

        // Llamadas timbrando
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM leads WHERE campaign_id = ? AND status = 'dialing'");
        $stmt->execute([$campaign_id]);
        $response['dialing_calls'] = (int)$stmt->fetchColumn();

        // Llamadas conectadas
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM leads WHERE campaign_id = ? AND status = 'connected'");
        $stmt->execute([$campaign_id]);
        $response['connected_calls'] = (int)$stmt->fetchColumn();

        // Leads pendientes
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM leads WHERE campaign_id = ? AND status = 'pending'");
        $stmt->execute([$campaign_id]);
        $response['pending_leads'] = (int)$stmt->fetchColumn();
    }

    echo json_encode($response);
} catch (Throwable $e) {
    error_log('get_status.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

?>

