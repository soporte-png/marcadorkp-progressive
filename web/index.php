<?php
require_once 'db.php';

$activeCampaign = $pdo->query("SELECT id, name FROM campaigns WHERE status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hasActiveCampaign = !empty($activeCampaign);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progressive Dialer Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="container">
        <header class="page-hero">
            <div class="brand-badge">
                <img src="https://ippbxasistenciausa.kiwano-plus.com/themes/tenantdark/images/kp2.png" alt="Kiwano Service Enabled" loading="lazy">
            </div>
            <div class="page-hero-copy">
                <h1>Progressive Dialer Dashboard</h1>
                <p class="tagline">Monitorea campañas, agentes y llamadas en tiempo real para tomar decisiones rápidas y mantener la experiencia del cliente consistente.</p>
            </div>
        </header>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                ✓ <?= htmlspecialchars($_GET['success']) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                ✗ <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <section id="insight-panel" class="card insight-panel<?php echo $hasActiveCampaign ? '' : ' hidden'; ?>">
            <div class="dashboard-metrics">
                <article class="metric">
                    <h2>Campaña Activa</h2>
                    <p id="active-campaign-name">--</p>
                </article>
                <article class="metric">
                    <h2>Agentes Libres</h2>
                    <p id="available-agents">0</p>
                </article>
                <article class="metric">
                    <h2>Llamadas Timbrando</h2>
                    <p id="dialing-calls">0</p>
                </article>
                <article class="metric">
                    <h2>Llamadas Activas</h2>
                    <p id="connected-calls">0</p>
                </article>
                <article class="metric">
                    <h2>Leads Pendientes</h2>
                    <p id="pending-leads">0</p>
                </article>
            </div>
        </section>

        <div id="campaign-locked-card" class="card card-note<?php echo $hasActiveCampaign ? '' : ' hidden'; ?>">
            <h2>Campaña en curso</h2>
            <p>
                <?php if ($hasActiveCampaign): ?>
                    Actualmente está activa la campaña <strong><?= htmlspecialchars($activeCampaign['name']) ?></strong>.
                <?php else: ?>
                    No hay campañas activas.
                <?php endif; ?>
                Pausa o finaliza la campaña activa para cargar otra.
            </p>
            <button id="unlock-create-form" type="button">Crear otra campaña</button>
        </div>

        <div id="create-campaign-card" class="card<?php echo $hasActiveCampaign ? ' hidden' : ''; ?>">
            <h2>Crear Nueva Campaña</h2>
            <form action="upload.php" method="post" enctype="multipart/form-data">
                <label for="campaign_name">Nombre de la Campaña:</label>
                <input type="text" id="campaign_name" name="campaign_name" required maxlength="100">

                <label for="queue_name">Cola de Asterisk:</label>
                <input type="text" id="queue_name" name="queue_name" placeholder="Ej: 500" required maxlength="50">

                <label for="csv_file">Archivo CSV (con columnas: phone_number, first_name, last_name):</label>
                <input type="file" id="csv_file" name="csv_file" accept=".csv,.txt" required>
                <small>Máximo 10 MB. Formato esperado: phone_number,first_name,last_name</small>

                <button type="submit">Crear y Cargar Leads</button>
            </form>
        </div>

    <div class="card<?php echo $hasActiveCampaign ? ' hidden' : ''; ?>" id="dispositions-card">
            <div class="card-header">
                <div>
                    <h2>Disposiciones de Agente</h2>
                    <p class="card-subtitle">Configura las tipificaciones disponibles y selecciona el orden en el que se mostrarán a los agentes.</p>
                </div>
                <button type="button" id="reset-disposition-form" class="btn-secondary">Limpiar</button>
            </div>

            <form id="disposition-form" class="disposition-form">
                <input type="hidden" id="disposition-id" name="id">
                <div class="form-row">
                    <label for="disposition-label">Nombre de la disposición</label>
                    <input type="text" id="disposition-label" name="label" required maxlength="120" placeholder="Ej: Contactado - Venta cerrada">
                </div>
                <div class="form-row">
                    <label for="disposition-description">Descripción (opcional)</label>
                    <input type="text" id="disposition-description" name="description" maxlength="255" placeholder="Detalle corto para los agentes">
                </div>
                <div class="form-grid">
                    <div class="form-row">
                        <label for="disposition-sort">Orden</label>
                        <input type="number" id="disposition-sort" name="sort_order" value="0" min="0">
                    </div>
                    <div class="form-row checkbox-row">
                        <input type="checkbox" id="disposition-active" name="is_active" value="1" checked>
                        <label for="disposition-active">Disponible para los agentes</label>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Guardar disposición</button>
                </div>
            </form>

            <div class="table-wrapper">
                <table class="data-table" id="dispositions-table">
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th>Actualizado</th>
                            <th class="actions-col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="dispositions-table-body">
                        <tr><td colspan="6" class="empty-row">Sin disposiciones configuradas.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card" id="manage-campaigns-card">
            <h2>Gestionar Campañas</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Cola</th>
                        <th>Estado</th>
                        <th>Creada</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT id, name, queue_name, status, created_at FROM campaigns ORDER BY id DESC");
                    while ($row = $stmt->fetch()):
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['queue_name']) ?></td>
                        <td><span class="status-<?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                        <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($row['created_at']))) ?></td>
                        <td class="actions">
                            <?php if ($row['status'] === 'paused'): ?>
                                <a href="campaign_control.php?action=activate&id=<?= $row['id'] ?>" class="btn-activate" onclick="return confirm('¿Activar esta campaña?')">▶ Activar</a>
                            <?php elseif ($row['status'] === 'active'): ?>
                                <a href="campaign_control.php?action=pause&id=<?= $row['id'] ?>" class="btn-pause">❚❚ Pausar</a>
                            <?php endif; ?>
                            <?php if ($row['status'] !== 'completed'): ?>
                                <a href="campaign_control.php?action=complete&id=<?= $row['id'] ?>" class="btn-complete" onclick="return confirm('¿Finalizar esta campaña?')">■ Finalizar</a>
                            <?php else: ?>
                                <form method="post" action="campaign_control.php" class="inline-form" onsubmit="return confirm('¿Eliminar de forma permanente la campaña <?= htmlspecialchars($row['name']) ?>? Esta acción no se puede deshacer.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn-delete">✖ Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
         <div class="card">
            <a href="reports.php" class="btn-report">Ver Reporte de Llamadas</a>
        </div>
    </div>

    <script src="app.js"></script>
</body>
</html>

