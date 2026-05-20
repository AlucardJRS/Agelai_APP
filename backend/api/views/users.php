<?php
declare(strict_types=1);
?>
<section class="card">
    <h1>Usuarios y Permisos por Modulo</h1>
    <p>
        Regla de negocio: usuario nuevo entra sin acceso y estado pendiente.
        Desde aqui asignas modulos y activas perfil.
    </p>
</section>

<?php if (count($users) === 0): ?>
    <section class="card">
        <p>Aun no hay usuarios registrados desde la app.</p>
    </section>
<?php else: ?>
    <section class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Usuario</th>
                <th>Google ID</th>
                <th>Estado</th>
                <th>Modulos</th>
                <th>Accion</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <?php
                $currentModuleIds = array_map(
                    static fn (array $module): int => (int) $module['id'],
                    $user['modules']
                );
                ?>
                <tr>
                    <td>
                        <strong><?= Security::e((string) $user['full_name']); ?></strong><br>
                        <small><?= Security::e((string) $user['email']); ?></small>
                    </td>
                    <td><code><?= Security::e((string) $user['google_id']); ?></code></td>
                    <td><?= Security::e((string) $user['status']); ?></td>
                    <td>
                        <?php if (count($user['modules']) === 0): ?>
                            <span class="tag tag-pending">Sin modulos</span>
                        <?php else: ?>
                            <?php foreach ($user['modules'] as $module): ?>
                                <span class="tag"><?= Security::e((string) $module['name']); ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="/dashboard/users/update" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
                            <input type="hidden" name="user_id" value="<?= (int) $user['id']; ?>">

                            <label class="inline-label">
                                Estado
                                <select name="status">
                                    <option value="pending" <?= (string) $user['status'] === 'pending' ? 'selected' : ''; ?>>pending</option>
                                    <option value="active" <?= (string) $user['status'] === 'active' ? 'selected' : ''; ?>>active</option>
                                    <option value="blocked" <?= (string) $user['status'] === 'blocked' ? 'selected' : ''; ?>>blocked</option>
                                </select>
                            </label>

                            <fieldset class="module-fieldset">
                                <legend>Modulos</legend>
                                <?php foreach ($modules as $module): ?>
                                    <?php $moduleId = (int) $module['id']; ?>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="module_ids[]"
                                            value="<?= $moduleId; ?>"
                                            <?= in_array($moduleId, $currentModuleIds, true) ? 'checked' : ''; ?>
                                        >
                                        <?= Security::e((string) $module['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>

                            <button type="submit" class="button button-small">Guardar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

