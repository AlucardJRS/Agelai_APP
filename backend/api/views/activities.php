<?php
declare(strict_types=1);
?>
<section class="card">
    <h1>Actividades y Cupos</h1>
    <p>
        Crea clases/citas indicando modulo, horario y cupo.
        El sistema bloquea plazas segun reservas activas.
    </p>
</section>

<section class="card">
    <h2>Nueva Actividad</h2>
    <form method="post" action="/dashboard/activities/create" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">

        <label>
            Titulo
            <input type="text" name="title" maxlength="120" required placeholder="Ej: Zumba tarde">
        </label>

        <label>
            Modulo
            <select name="module_code" required>
                <option value="">Selecciona</option>
                <?php foreach ($modules as $module): ?>
                    <option value="<?= Security::e((string) $module['code']); ?>">
                        <?= Security::e((string) $module['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Inicio
            <input type="datetime-local" name="starts_at" required>
        </label>

        <label>
            Fin
            <input type="datetime-local" name="ends_at" required>
        </label>

        <label>
            Cupo
            <input type="number" name="capacity" min="1" max="500" required>
        </label>

        <label>
            Sede
            <select name="location" required>
                <?php foreach ($locations as $locationCode => $locationMeta): ?>
                    <?php
                    $locationName = is_array($locationMeta) && isset($locationMeta['name']) ? (string) $locationMeta['name'] : (string) $locationCode;
                    $isPrimary = is_array($locationMeta) && !empty($locationMeta['is_primary']);
                    ?>
                    <option value="<?= Security::e($locationName); ?>">
                        <?= Security::e($locationName); ?><?= $isPrimary ? ' (Principal)' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="col-span-2">
            Notas
            <textarea name="notes" rows="3" maxlength="500" placeholder="Material, observaciones..."></textarea>
        </label>

        <button type="submit" class="button col-span-2">Crear actividad</button>
    </form>
</section>

<section class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Actividad</th>
            <th>Modulo</th>
            <th>Horario</th>
            <th>Cupo</th>
            <th>Estado</th>
            <th>Accion</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($activities as $activity): ?>
            <?php
            $capacity = (int) $activity['capacity'];
            $occupied = (int) $activity['occupied_slots'];
            $remaining = max(0, $capacity - $occupied);
            $status = (string) $activity['status'];
            ?>
            <tr>
                <td><?= (int) $activity['id']; ?></td>
                <td>
                    <strong><?= Security::e((string) $activity['title']); ?></strong><br>
                    <small><?= Security::e((string) $activity['location']); ?></small>
                </td>
                <td><?= Security::e((string) $activity['module_code']); ?></td>
                <td>
                    <small>Inicio:</small> <?= Security::e((string) $activity['starts_at']); ?><br>
                    <small>Fin:</small> <?= Security::e((string) $activity['ends_at']); ?>
                </td>
                <td>
                    <?= $occupied; ?> / <?= $capacity; ?><br>
                    <small>Libres: <?= $remaining; ?></small>
                </td>
                <td><?= Security::e($status); ?></td>
                <td>
                    <form method="post" action="/dashboard/activities/toggle" class="inline-form">
                        <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
                        <input type="hidden" name="activity_id" value="<?= (int) $activity['id']; ?>">
                        <input type="hidden" name="status" value="<?= $status === 'active' ? 'inactive' : 'active'; ?>">
                        <button type="submit" class="button button-small <?= $status === 'active' ? 'button-danger' : 'button-secondary'; ?>">
                            <?= $status === 'active' ? 'Desactivar' : 'Activar'; ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
