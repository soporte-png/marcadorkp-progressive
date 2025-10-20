<?php
require_once 'db.php';

$action = null;
$campaignId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $campaignId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
} elseif (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $campaignId = (int)$_GET['id'];
}

if (!$action || $campaignId <= 0) {
    header('Location: index.php?error=' . urlencode('Solicitud inválida.'));
    exit();
}

$pdo->beginTransaction();

try {
    $message = '';

    if ($action === 'activate') {
        $pdo->exec("UPDATE campaigns SET status = 'paused' WHERE status = 'active'");
        $stmt = $pdo->prepare("UPDATE campaigns SET status = 'active' WHERE id = ?");
        $stmt->execute([$campaignId]);
        $message = 'Campaña activada.';
    } elseif ($action === 'pause') {
        $stmt = $pdo->prepare("UPDATE campaigns SET status = 'paused' WHERE id = ?");
        $stmt->execute([$campaignId]);
        $message = 'Campaña pausada.';
    } elseif ($action === 'complete') {
        $stmt = $pdo->prepare("UPDATE campaigns SET status = 'completed' WHERE id = ?");
        $stmt->execute([$campaignId]);
        $message = 'Campaña marcada como completada.';
    } elseif ($action === 'delete') {
        $stmtCampaign = $pdo->prepare("SELECT name, status FROM campaigns WHERE id = ?");
        $stmtCampaign->execute([$campaignId]);
        $campaign = $stmtCampaign->fetch(PDO::FETCH_ASSOC);

        if (!$campaign) {
            throw new RuntimeException('Campaña no encontrada.');
        }

        if ($campaign['status'] !== 'completed') {
            throw new RuntimeException('Solo se pueden eliminar campañas completadas.');
        }

        $pdo->prepare("DELETE FROM leads WHERE campaign_id = ?")->execute([$campaignId]);
        $pdo->prepare("DELETE FROM call_logs WHERE campaign_id = ?")->execute([$campaignId]);
        $pdo->prepare("DELETE FROM campaigns WHERE id = ?")->execute([$campaignId]);

        $message = sprintf("Campaña '%s' eliminada permanentemente.", $campaign['name']);
    } else {
        throw new RuntimeException('Acción no soportada.');
    }

    $pdo->commit();
    header('Location: index.php?success=' . urlencode($message));
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('campaign_control error: ' . $e->getMessage());
    header('Location: index.php?error=' . urlencode('No se pudo completar la acción solicitada.'));
}

exit();

