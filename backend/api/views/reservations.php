<?php
declare(strict_types=1);
?>
<section class="card">
    <h1>Reservas y Validacion Final</h1>
    <p>
        Flujo previsto: usuario reserva, confirma su codigo y luego validas aqui.
        Al confirmar, la plaza queda definitiva y bloqueada.
    </p>
</section>

<?php if (count($reservations) === 0): ?>
    <section class="card">
        <p>No hay reservas registradas todavia.</p>
    </section>
<?php else: ?>
    <section class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Usuario</th>
                <th>Actividad</th>
                <th>Estado</th>
                <th>Pago</th>
                <th>Cupo</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($reservations as $reservation): ?>
                <?php
                $status = (string) $reservation['status'];
                ?>
                <tr>
                    <td>
                        <strong><?= Security::e((string) $reservation['full_name']); ?></strong><br>
                        <small><?= Security::e((string) $reservation['email']); ?></small>
                    </td>
                    <td>
                        <strong><?= Security::e((string) $reservation['title']); ?></strong><br>
                        <small><?= Security::e((string) $reservation['module_code']); ?></small><br>
                        <small><?= Security::e((string) $reservation['starts_at']); ?></small>
                    </td>
                    <td><span class="tag"><?= Security::e($status); ?></span></td>
                    <td><?= Security::e((string) $reservation['payment_status']); ?></td>
                    <td><?= (int) $reservation['occupied_slots']; ?> / <?= (int) $reservation['capacity']; ?></td>
                    <td><small><?= Security::e((string) $reservation['created_at']); ?></small></td>
                    <td>
                        <?php if ($status === 'pending_admin_approval'): ?>
                            <form method="post" action="/dashboard/reservations/approve" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
                                <input type="hidden" name="reservation_id" value="<?= (int) $reservation['id']; ?>">
                                <button type="submit" class="button button-small">Aprobar</button>
                            </form>
                            <form method="post" action="/dashboard/reservations/reject" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
                                <input type="hidden" name="reservation_id" value="<?= (int) $reservation['id']; ?>">
                                <button type="submit" class="button button-small button-danger">Rechazar</button>
                            </form>
                        <?php else: ?>
                            <span class="small-note">Sin accion</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

