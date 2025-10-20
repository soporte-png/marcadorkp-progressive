<?php 
require_once 'db.php';

// Paginación
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filtros
$campaign_filter = isset($_GET['campaign']) ? (int)$_GET['campaign'] : 0;
$disposition_filter = isset($_GET['disposition']) ? trim($_GET['disposition']) : '';

// Construir query con filtros
$where = [];
$params = [];

if ($campaign_filter > 0) {
    $where[] = "cl.campaign_id = ?";
    $params[] = $campaign_filter;
}

if (!empty($disposition_filter)) {
    $where[] = "(dc.label = ? OR cl.disposition = ?)";
    $params[] = $disposition_filter;
    $params[] = $disposition_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Exportar a CSV si se solicita (antes de producir salida HTML)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=reporte_llamadas_' . date('Y-m-d_H-i-s') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Campaña', 'Lead', 'Número', 'Agente', 'Inicio', 'Respuesta', 'Fin', 'Duración', 'Disposición', 'Notas', 'Tipificada en', 'UniqueID', 'Grabación']);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $scriptDir = '';
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        if ($dir !== '/' && $dir !== '.') {
            $scriptDir = $dir;
        }
    }
    $baseUrl = rtrim($scheme . '://' . $host . ($scriptDir ? $scriptDir : ''), '/') . '/';

    $export_query = "SELECT cl.*, c.name AS campaign_name, l.first_name, l.last_name, ad.notes AS disposition_notes, ad.created_at AS disposition_saved_at, dc.label AS agent_disposition_label FROM call_logs cl LEFT JOIN campaigns c ON c.id = cl.campaign_id LEFT JOIN leads l ON l.id = cl.lead_id LEFT JOIN agent_dispositions ad ON ad.call_uniqueid = cl.uniqueid LEFT JOIN disposition_catalog dc ON dc.id = ad.disposition_id $where_clause ORDER BY cl.id DESC";
    $stmt_export = $pdo->prepare($export_query);
    $stmt_export->execute($params);

    while ($row = $stmt_export->fetch()) {
        $leadFullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        if ($leadFullName === '') {
            $leadFullName = '--';
        }

        fputcsv($output, [
            $row['id'],
            $row['campaign_name']
                ? $row['campaign_name'] . ' (#' . $row['campaign_id'] . ')'
                : 'Campaña #' . $row['campaign_id'],
            $leadFullName,
            $row['phone_number'],
            $row['agent_extension'] ?: '--',
            $row['call_start_time'] ?: '--',
            $row['call_answer_time'] ?: '--',
            $row['call_end_time'] ?: '--',
            $row['call_duration_seconds'] ?: '--',
            $row['agent_disposition_label'] ?: $row['disposition'],
            $row['disposition_notes'] ? preg_replace("/\s+/", ' ', $row['disposition_notes']) : '--',
            $row['disposition_saved_at'] ?: '--',
            $row['uniqueid'] ?: '--',
            (!empty($row['uniqueid']) && !empty($row['call_end_time']))
                ? $baseUrl . 'download_recording.php?uniqueid=' . rawurlencode($row['uniqueid'])
                : '--'
        ]);
    }

    fclose($output);
    exit;
}

// Contar total de registros
$count_query = "SELECT COUNT(*) FROM call_logs cl LEFT JOIN agent_dispositions ad ON ad.call_uniqueid = cl.uniqueid LEFT JOIN disposition_catalog dc ON dc.id = ad.disposition_id $where_clause";
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Obtener registros con paginación
$query = "SELECT cl.*, c.name AS campaign_name, l.first_name, l.last_name, ad.notes AS disposition_notes, ad.created_at AS disposition_saved_at, dc.label AS agent_disposition_label FROM call_logs cl LEFT JOIN campaigns c ON c.id = cl.campaign_id LEFT JOIN leads l ON l.id = cl.lead_id LEFT JOIN agent_dispositions ad ON ad.call_uniqueid = cl.uniqueid LEFT JOIN disposition_catalog dc ON dc.id = ad.disposition_id $where_clause ORDER BY cl.id DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);

// Obtener lista de campañas para el filtro
$campaigns = $pdo->query("SELECT id, name FROM campaigns ORDER BY id DESC")->fetchAll();

// Obtener disposiciones únicas (catálogo y registros históricos)
$dispositions_stmt = $pdo->query("
    SELECT disp_label FROM (
        SELECT label AS disp_label
        FROM disposition_catalog
        WHERE is_active = 1
        UNION
        SELECT DISTINCT disposition AS disp_label
        FROM call_logs
        WHERE disposition IS NOT NULL AND disposition <> ''
    ) AS merged
    ORDER BY disp_label
");
$dispositions = $dispositions_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Llamadas</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .filters {
            background: var(--color-surface);
            padding: 24px;
            border-radius: var(--radius-base);
            margin-bottom: 24px;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--color-border);
        }
        .filters form {
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filters label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.9em;
            color: var(--color-muted);
        }
        .filters select,
        .filters button {
            padding: 10px 14px;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            background-color: #fdfdff;
            font-size: 0.95em;
        }
        .filters button {
            background-color: var(--color-primary);
            color: #fff;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            transition: background-color var(--transition-fast), transform var(--transition-fast);
        }
        .filters button:hover {
            background-color: var(--color-primary-dark);
            transform: translateY(-1px);
        }
        .filters a {
            padding: 10px 14px;
            background: #95a5a6;
            color: #fff;
            border-radius: 999px;
            text-decoration: none;
            transition: transform var(--transition-fast);
        }
        .filters a:hover {
            transform: translateY(-1px);
        }
        .recording-link {
            color: var(--color-primary);
            text-decoration: none;
            font-weight: 600;
        }
        .recording-link:hover {
            text-decoration: underline;
        }
        .pagination {
            text-align: center;
            margin: 24px 0;
        }
        .pagination a,
        .pagination span {
            display: inline-block;
            padding: 8px 14px;
            margin: 0 2px;
            border: 1px solid var(--color-border);
            border-radius: 999px;
            text-decoration: none;
            color: var(--color-muted);
            background: var(--color-surface);
        }
        .pagination a:hover {
            background: var(--color-primary);
            color: #fff;
        }
        .pagination .current {
            background: var(--color-primary);
            color: #fff;
            font-weight: 600;
            border-color: var(--color-primary);
        }
        .export-btn {
            background: var(--color-success);
            color: #fff;
            padding: 10px 18px;
            border-radius: 999px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 16px;
            transition: transform var(--transition-fast), background-color var(--transition-fast);
        }
        .export-btn:hover {
            background: #229652;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reporte de Llamadas</h1>
        <a href="index.php" class="btn-back">← Volver al Dashboard</a>
        <a href="?export=csv<?php echo $campaign_filter ? '&campaign='.$campaign_filter : ''; ?><?php echo $disposition_filter ? '&disposition='.$disposition_filter : ''; ?>" class="export-btn">📥 Exportar a CSV</a>
        
        <div class="filters">
            <form method="get">
                <div>
                    <label>Campaña:</label>
                    <select name="campaign">
                        <option value="0">Todas</option>
                        <?php foreach ($campaigns as $camp): ?>
                            <option value="<?= $camp['id'] ?>" <?= $campaign_filter == $camp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($camp['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Disposición:</label>
                    <select name="disposition">
                        <option value="">Todas</option>
                        <?php foreach ($dispositions as $disp): ?>
                            <option value="<?= htmlspecialchars($disp) ?>" <?= $disposition_filter == $disp ? 'selected' : '' ?>>
                                <?= htmlspecialchars($disp) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Filtrar</button>
                <a href="reports.php" style="padding: 8px 12px; background: #95a5a6; color: white; border-radius: 4px; text-decoration: none;">Limpiar</a>
            </form>
        </div>

        <p>Mostrando <?= min($offset + 1, $total_records) ?> - <?= min($offset + $per_page, $total_records) ?> de <?= $total_records ?> registros</p>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Campaña</th>
                    <th>Lead</th>
                    <th>Número</th>
                    <th>Agente</th>
                    <th>Inicio</th>
                    <th>Respuesta</th>
                    <th>Fin</th>
                    <th>Duración (s)</th>
                    <th>Disposición</th>
                    <th>Notas</th>
                    <th>Tipificada</th>
                    <th>UniqueID</th>
                    <th>Grabación</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $stmt->fetch()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['campaign_name']
                        ? $row['campaign_name'] . ' (#' . $row['campaign_id'] . ')'
                        : 'Campaña #' . $row['campaign_id']) ?></td>
                    <td><?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: '--') ?></td>
                    <td><?= htmlspecialchars($row['phone_number']) ?></td>
                    <td><?= htmlspecialchars($row['agent_extension'] ?: '--') ?></td>
                    <td><?= htmlspecialchars($row['call_start_time'] ?: '--') ?></td>
                    <td><?= htmlspecialchars($row['call_answer_time'] ?: '--') ?></td>
                    <td><?= htmlspecialchars($row['call_end_time'] ?: '--') ?></td>
                    <td><?= htmlspecialchars($row['call_duration_seconds'] ?: '--') ?></td>
                    <td><?= htmlspecialchars($row['agent_disposition_label'] ?: $row['disposition']) ?></td>
                    <td>
                        <?php if (!empty($row['disposition_notes'])): ?>
                            <?= nl2br(htmlspecialchars($row['disposition_notes'])) ?>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    <?php
                        $dispositionTimestamp = null;
                        if (!empty($row['disposition_saved_at'])) {
                            $ts = strtotime($row['disposition_saved_at']);
                            if ($ts !== false && $ts > 0) {
                                $dispositionTimestamp = date('Y-m-d H:i:s', $ts);
                            }
                        }
                    ?>
                    <td><?= htmlspecialchars($dispositionTimestamp ?: '--') ?></td>
                    <td><?= htmlspecialchars($row['uniqueid'] ?: '--') ?></td>
                    <td>
                        <?php if (!empty($row['uniqueid']) && !empty($row['call_end_time'])): ?>
                            <a class="recording-link" href="download_recording.php?uniqueid=<?= urlencode($row['uniqueid']) ?>">Descargar</a>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1<?= $campaign_filter ? '&campaign='.$campaign_filter : '' ?><?= $disposition_filter ? '&disposition='.$disposition_filter : '' ?>">« Primera</a>
                <a href="?page=<?= $page - 1 ?><?= $campaign_filter ? '&campaign='.$campaign_filter : '' ?><?= $disposition_filter ? '&disposition='.$disposition_filter : '' ?>">‹ Anterior</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?><?= $campaign_filter ? '&campaign='.$campaign_filter : '' ?><?= $disposition_filter ? '&disposition='.$disposition_filter : '' ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= $campaign_filter ? '&campaign='.$campaign_filter : '' ?><?= $disposition_filter ? '&disposition='.$disposition_filter : '' ?>">Siguiente ›</a>
                <a href="?page=<?= $total_pages ?><?= $campaign_filter ? '&campaign='.$campaign_filter : '' ?><?= $disposition_filter ? '&disposition='.$disposition_filter : '' ?>">Última »</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>

