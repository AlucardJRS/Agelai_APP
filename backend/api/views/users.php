<?php
declare(strict_types=1);
?>
<section class="card">
    <h1>Usuarios y Permisos por Modulo</h1>
    <p>
        Regla de negocio: usuario nuevo entra sin acceso y estado pendiente.
        Desde aqui puedes crear usuarios, bloquearlos, darlos de baja, vincular su Google ID y gestionar permisos por modulo.
    </p>
</section>

<section class="card">
    <h2>Crear Usuario Desde Dashboard</h2>
    <p class="small-note">
        Puedes crear usuarios locales con username/password, usuarios Google o mixtos.
        Si dejas Google ID vacio, el sistema usara un ID provisional.
    </p>
    <form method="post" action="/dashboard/users/create" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">

        <label>
            Nombre completo
            <input type="text" name="full_name" maxlength="120" required>
        </label>

        <label>
            Email
            <input type="email" name="email" maxlength="180" required>
        </label>

        <label>
            Google ID (opcional)
            <input type="text" name="google_id" maxlength="128" placeholder="Opcional">
        </label>

        <label>
            Username local (opcional)
            <input type="text" name="username" maxlength="60" placeholder="Ejemplo: cliente01">
        </label>

        <label>
            Password local (opcional)
            <input type="password" name="password" minlength="8" maxlength="72" placeholder="Minimo 8 caracteres">
        </label>

        <label>
            Estado inicial
            <select name="status">
                <option value="pending" selected>pending</option>
                <option value="active">active</option>
                <option value="blocked">blocked</option>
            </select>
        </label>

        <fieldset class="module-fieldset col-span-2">
            <legend>Permisos de modulos</legend>
            <?php foreach ($modules as $module): ?>
                <label>
                    <input type="checkbox" name="module_ids[]" value="<?= (int) $module['id']; ?>">
                    <?= Security::e((string) $module['name']); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <div class="col-span-2">
            <button type="submit" class="button">Crear usuario</button>
        </div>
    </form>
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
                <th>Origen</th>
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
                $googleId = (string) $user['google_id'];
                $isManual = str_starts_with($googleId, 'manual_local_');
                $username = trim((string) ($user['username'] ?? ''));
                $authProvider = (string) ($user['auth_provider'] ?? 'google');
                $hasLocalPassword = (int) ($user['has_local_password'] ?? 0) === 1;
                ?>
                <tr>
                    <td>
                        <strong><?= Security::e((string) $user['full_name']); ?></strong><br>
                        <small><?= Security::e((string) $user['email']); ?></small><br>
                        <?php if ($username === ''): ?>
                            <small>Sin username local</small>
                        <?php else: ?>
                            <small>Username: <code><?= Security::e($username); ?></code></small>
                        <?php endif; ?>
                    </td>
                    <td><code><?= Security::e($googleId); ?></code></td>
                    <td>
                        <?php if ($authProvider === 'local'): ?>
                            <span class="tag">Local</span>
                        <?php elseif ($authProvider === 'hybrid'): ?>
                            <span class="tag">Google + Local</span>
                        <?php elseif ($isManual): ?>
                            <span class="tag tag-pending">Manual pendiente Google</span>
                        <?php else: ?>
                            <span class="tag">Google OAuth</span>
                        <?php endif; ?>
                        <?php if ($hasLocalPassword): ?>
                            <span class="tag">Password OK</span>
                        <?php endif; ?>
                    </td>
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
                                Nombre
                                <input type="text" name="full_name" value="<?= Security::e((string) $user['full_name']); ?>" maxlength="120" required>
                            </label>

                            <label class="inline-label">
                                Email
                                <input type="email" name="email" value="<?= Security::e((string) $user['email']); ?>" maxlength="180" required>
                            </label>

                            <label class="inline-label">
                                Google ID
                                <input type="text" name="google_id" value="<?= Security::e($googleId); ?>" maxlength="128">
                            </label>

                            <label class="inline-label">
                                Username
                                <input type="text" name="username" value="<?= Security::e($username); ?>" maxlength="60">
                            </label>

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
                        <?php if ((string) $user['status'] !== 'blocked'): ?>
                            <form method="post" action="/dashboard/users/block" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id']; ?>">
                                <button type="submit" class="button button-small button-danger">Bloquear ahora</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="/dashboard/users/deactivate" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
                            <input type="hidden" name="user_id" value="<?= (int) $user['id']; ?>">
                            <button type="submit" class="button button-small button-secondary">Dar de baja</button>
                        </form>
                        <form method="post" action="/dashboard/users/reset-password" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
                            <input type="hidden" name="user_id" value="<?= (int) $user['id']; ?>">
                            <label class="inline-label">
                                Nueva password
                                <input type="password" name="new_password" minlength="8" maxlength="72" placeholder="Reset password local">
                            </label>
                            <button type="submit" class="button button-small">Reset password</button>
                        </form>
                        <form method="post" action="/dashboard/users/delete" class="inline-form" onsubmit="return confirm('Se eliminara el usuario y sus reservas. ¿Continuar?');">
                            <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
                            <input type="hidden" name="user_id" value="<?= (int) $user['id']; ?>">
                            <button type="submit" class="button button-small button-danger">Eliminar usuario</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

