<?php
declare(strict_types=1);

$filtersData = is_array($filters ?? null) ? $filters : [];
$filterNombre = (string) ($filtersData['nombre'] ?? '');
$filterApellidos = (string) ($filtersData['apellidos'] ?? '');
$filterMovil = (string) ($filtersData['movil'] ?? '');

$summaryData = is_array($summary ?? null) ? $summary : [];
$usersAnalyzed = (int) ($summaryData['users_analyzed'] ?? 0);
$reservationsTotal = (int) ($summaryData['reservations_total'] ?? 0);
$reservationsConfirmed = (int) ($summaryData['reservations_confirmed'] ?? 0);
$reservationsPendingAdmin = (int) ($summaryData['reservations_pending_admin'] ?? 0);

$slotTotalsData = is_array($slot_totals ?? null) ? $slot_totals : [];
$topActivitiesData = is_array($top_activities ?? null) ? $top_activities : [];
$topProductsData = is_array($top_products ?? null) ? $top_products : [];
$peakHoursData = is_array($peak_hours ?? null) ? $peak_hours : [];
$usersMovementsData = is_array($users_movements ?? null) ? $users_movements : [];

$maxTopActivity = 1;
foreach ($topActivitiesData as $item) {
    $maxTopActivity = max($maxTopActivity, (int) ($item['total'] ?? 0));
}

$maxTopProduct = 1;
foreach ($topProductsData as $item) {
    $maxTopProduct = max($maxTopProduct, (int) ($item['total'] ?? 0));
}

$maxPeakHour = 1;
foreach ($peakHoursData as $item) {
    $maxPeakHour = max($maxPeakHour, (int) ($item['total'] ?? 0));
}
?>
<section class="card">
    <h1>Usuarios - Movimientos</h1>
    <p>
        Analitica de uso de instalaciones y actividades: productos/servicios consumidos,
        actividades preferidas y franjas horarias de mayor uso.
    </p>
    <div class="users-switch-grid">
        <a class="users-switch-card" href="/dashboard/users/altas-bajas">
            <span class="users-switch-icon" aria-hidden="true">👤</span>
            <span class="users-switch-copy">
                <strong>Altas-Bajas</strong>
                <small>Listado y ficha individual de usuarios</small>
            </span>
        </a>
        <a class="users-switch-card active" href="/dashboard/users/movimientos">
            <span class="users-switch-icon" aria-hidden="true">📈</span>
            <span class="users-switch-copy">
                <strong>Movimientos</strong>
                <small>Analitica de uso y comportamiento</small>
            </span>
        </a>
    </div>
</section>

<section class="card">
    <h2>Buscar Movimientos</h2>
    <form method="get" action="/dashboard/users/movimientos" class="form-grid">
        <label>
            Nombre
            <input type="text" name="nombre" maxlength="80" value="<?= Security::e($filterNombre); ?>" placeholder="Nombre">
        </label>
        <label>
            Apellidos
            <input type="text" name="apellidos" maxlength="120" value="<?= Security::e($filterApellidos); ?>" placeholder="Apellidos">
        </label>
        <label>
            Movil
            <input type="text" name="movil" maxlength="30" value="<?= Security::e($filterMovil); ?>" placeholder="Telefono">
        </label>
        <div class="users-search-actions">
            <button type="submit" class="button">Buscar</button>
            <a href="/dashboard/users/movimientos" class="button button-secondary users-clear-link">Limpiar</a>
        </div>
    </form>
</section>

<section class="stats-grid">
    <article class="stat-card">
        <h3>Usuarios Analizados</h3>
        <p><strong><?= $usersAnalyzed; ?></strong></p>
    </article>
    <article class="stat-card">
        <h3>Reservas Totales</h3>
        <p><strong><?= $reservationsTotal; ?></strong></p>
    </article>
    <article class="stat-card">
        <h3>Reservas Confirmadas</h3>
        <p><strong><?= $reservationsConfirmed; ?></strong></p>
    </article>
    <article class="stat-card">
        <h3>Pendientes de Validacion</h3>
        <p><strong><?= $reservationsPendingAdmin; ?></strong></p>
    </article>
</section>

<section class="movements-grid">
    <article class="card">
        <h2>Actividades Preferidas</h2>
        <?php if (count($topActivitiesData) === 0): ?>
            <p>Sin datos de actividad para los filtros aplicados.</p>
        <?php else: ?>
            <div class="bars-list">
                <?php foreach ($topActivitiesData as $row): ?>
                    <?php
                    $total = (int) ($row['total'] ?? 0);
                    $width = max(4, (int) round(($total / $maxTopActivity) * 100));
                    ?>
                    <div class="bar-item">
                        <div class="bar-head">
                            <span><?= Security::e((string) ($row['label'] ?? 'Actividad')); ?></span>
                            <strong><?= $total; ?></strong>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: <?= $width; ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <article class="card">
        <h2>Productos/Servicios Mas Comprados</h2>
        <?php if (count($topProductsData) === 0): ?>
            <p>Sin datos de compras/servicios para los filtros aplicados.</p>
        <?php else: ?>
            <div class="bars-list">
                <?php foreach ($topProductsData as $row): ?>
                    <?php
                    $total = (int) ($row['total'] ?? 0);
                    $width = max(4, (int) round(($total / $maxTopProduct) * 100));
                    ?>
                    <div class="bar-item">
                        <div class="bar-head">
                            <span><?= Security::e((string) ($row['label'] ?? 'Servicio')); ?></span>
                            <strong><?= $total; ?></strong>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill bar-fill-accent" style="width: <?= $width; ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>

<section class="movements-grid">
    <article class="card">
        <h2>Franjas Horarias Preferidas</h2>
        <div class="slot-pills">
            <?php foreach ($slotTotalsData as $slotLabel => $slotTotal): ?>
                <span class="tag movements-tag">
                    <?= Security::e((string) $slotLabel); ?>: <strong><?= (int) $slotTotal; ?></strong>
                </span>
            <?php endforeach; ?>
        </div>
    </article>
    <article class="card">
        <h2>Horas Punta</h2>
        <?php if (count($peakHoursData) === 0): ?>
            <p>Sin datos de horas punta para los filtros aplicados.</p>
        <?php else: ?>
            <div class="bars-list">
                <?php foreach ($peakHoursData as $row): ?>
                    <?php
                    $total = (int) ($row['total'] ?? 0);
                    $width = max(4, (int) round(($total / $maxPeakHour) * 100));
                    ?>
                    <div class="bar-item">
                        <div class="bar-head">
                            <span><?= Security::e((string) ($row['label'] ?? 'Hora')); ?></span>
                            <strong><?= $total; ?></strong>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill bar-fill-contrast" style="width: <?= $width; ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>

<section class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Usuario</th>
            <th>Contacto</th>
            <th>Uso Total</th>
            <th>Actividad Frecuente</th>
            <th>Horario Preferido</th>
            <th>Ultimo Movimiento</th>
        </tr>
        </thead>
        <tbody>
        <?php if (count($usersMovementsData) === 0): ?>
            <tr>
                <td colspan="6">No hay movimientos para los filtros aplicados.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($usersMovementsData as $row): ?>
                <tr>
                    <td>
                        <strong><?= Security::e((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')); ?></strong><br>
                        <small>Estado: <?= Security::e((string) ($row['status'] ?? '')); ?></small>
                    </td>
                    <td>
                        <small>Email: <?= Security::e((string) ($row['email'] ?? '')); ?></small><br>
                        <small>Movil: <?= Security::e((string) ($row['phone'] ?? '')); ?></small><br>
                        <small>Direccion: <?= Security::e((string) ($row['address'] ?? '')); ?></small>
                    </td>
                    <td>
                        <strong><?= (int) ($row['reservations_total'] ?? 0); ?></strong> reservas<br>
                        <small>Confirmadas: <?= (int) ($row['reservations_confirmed'] ?? 0); ?></small><br>
                        <small>Pend. admin: <?= (int) ($row['reservations_pending_admin'] ?? 0); ?></small>
                    </td>
                    <td><?= Security::e((string) ($row['favorite_activity'] ?? 'Sin datos')); ?></td>
                    <td><?= Security::e((string) ($row['preferred_slot'] ?? 'Sin datos')); ?></td>
                    <td>
                        <?php if ((string) ($row['last_movement_at'] ?? '') === ''): ?>
                            Sin movimiento
                        <?php else: ?>
                            <?= Security::e((string) $row['last_movement_at']); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
