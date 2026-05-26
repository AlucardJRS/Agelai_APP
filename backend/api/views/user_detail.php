<?php
declare(strict_types=1);

$userData = is_array($user ?? null) ? $user : [];
$fullName = trim((string) ($userData['first_name'] ?? '') . ' ' . (string) ($userData['last_name'] ?? ''));
$googleId = (string) ($userData['google_id'] ?? '');
$username = trim((string) ($userData['username'] ?? ''));
$authProvider = (string) ($userData['auth_provider'] ?? 'google');
$hasLocalPassword = (int) ($userData['has_local_password'] ?? 0) === 1;
$isManual = str_starts_with($googleId, 'manual_local_');
$currentModuleIds = array_map(
    static fn (array $module): int => (int) $module['id'],
    (array) ($userData['modules'] ?? [])
);
$returnTo = '/dashboard/users/altas-bajas/' . (int) ($userData['id'] ?? 0);
?>
<section class="card">
    <h1>Ficha de Usuario</h1>
    <p>Ventana de gestion completa. Puedes cerrar para volver al menu de usuarios.</p>
    <div class="users-switch-grid">
        <a class="users-switch-card active" href="/dashboard/users/altas-bajas">
            <span class="users-switch-icon" aria-hidden="true">👤</span>
            <span class="users-switch-copy">
                <strong>Altas-Bajas</strong>
                <small>Listado y ficha individual de usuarios</small>
            </span>
        </a>
        <a class="users-switch-card" href="/dashboard/users/movimientos">
            <span class="users-switch-icon" aria-hidden="true">📈</span>
            <span class="users-switch-copy">
                <strong>Movimientos</strong>
                <small>Analitica de uso y comportamiento</small>
            </span>
        </a>
    </div>
    <div class="user-detail-close-wrap">
        <a href="/dashboard/users/altas-bajas" class="button button-secondary">Cerrar ficha y volver</a>
    </div>
</section>

<section class="card">
    <h2><?= Security::e($fullName === '' ? 'Usuario' : $fullName); ?></h2>
    <div class="user-detail-meta">
        <span class="tag">ID: <?= (int) ($userData['id'] ?? 0); ?></span>
        <span class="tag <?= (string) ($userData['status'] ?? '') === 'pending' ? 'tag-pending' : ''; ?>">
            Estado: <?= Security::e((string) ($userData['status'] ?? 'pending')); ?>
        </span>
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
            <span class="tag">Password local activa</span>
        <?php endif; ?>
    </div>

    <form method="post" action="/dashboard/users/update" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
        <input type="hidden" name="user_id" value="<?= (int) $userData['id']; ?>">
        <input type="hidden" name="return_to" value="<?= Security::e($returnTo); ?>">

        <label>
            Nombre
            <input type="text" name="first_name" value="<?= Security::e((string) ($userData['first_name'] ?? '')); ?>" maxlength="80" required>
        </label>

        <label>
            Apellidos
            <input type="text" name="last_name" value="<?= Security::e((string) ($userData['last_name'] ?? '')); ?>" maxlength="120" required>
        </label>

        <label>
            Movil
            <input type="text" name="phone" value="<?= Security::e((string) ($userData['phone'] ?? '')); ?>" maxlength="30" required>
        </label>

        <label>
            Direccion
            <input type="text" name="address" value="<?= Security::e((string) ($userData['address'] ?? '')); ?>" maxlength="220" required>
        </label>

        <label>
            Email
            <input type="email" name="email" value="<?= Security::e((string) ($userData['email'] ?? '')); ?>" maxlength="180" required>
        </label>

        <label>
            Google ID
            <input type="text" name="google_id" value="<?= Security::e($googleId); ?>" maxlength="128">
        </label>

        <label>
            Username local
            <input type="text" name="username" value="<?= Security::e($username); ?>" maxlength="60">
        </label>

        <label>
            Estado
            <select name="status">
                <option value="pending" <?= (string) $userData['status'] === 'pending' ? 'selected' : ''; ?>>pending</option>
                <option value="active" <?= (string) $userData['status'] === 'active' ? 'selected' : ''; ?>>active</option>
                <option value="blocked" <?= (string) $userData['status'] === 'blocked' ? 'selected' : ''; ?>>blocked</option>
            </select>
        </label>

        <fieldset class="module-fieldset col-span-2">
            <legend>Permisos de modulos</legend>
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

        <div class="col-span-2 user-detail-actions">
            <button type="submit" class="button">Guardar cambios</button>
        </div>
    </form>

    <div class="user-detail-action-grid">
        <?php if ((string) $userData['status'] !== 'blocked'): ?>
            <form method="post" action="/dashboard/users/block" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
                <input type="hidden" name="user_id" value="<?= (int) $userData['id']; ?>">
                <input type="hidden" name="return_to" value="<?= Security::e($returnTo); ?>">
                <button type="submit" class="button button-danger">Bloquear ahora</button>
            </form>
        <?php endif; ?>

        <form method="post" action="/dashboard/users/deactivate" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
            <input type="hidden" name="user_id" value="<?= (int) $userData['id']; ?>">
            <input type="hidden" name="return_to" value="<?= Security::e($returnTo); ?>">
            <button type="submit" class="button button-secondary">Dar de baja</button>
        </form>

        <form method="post" action="/dashboard/users/reset-password" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
            <input type="hidden" name="user_id" value="<?= (int) $userData['id']; ?>">
            <input type="hidden" name="return_to" value="<?= Security::e($returnTo); ?>">
            <label class="inline-label">
                Nueva password local
                <input type="password" name="new_password" minlength="8" maxlength="72" placeholder="Reset password local">
            </label>
            <button type="submit" class="button">Reset password</button>
        </form>

        <form method="post" action="/dashboard/users/delete" class="inline-form" onsubmit="return confirm('Se eliminara el usuario y sus reservas. ¿Continuar?');">
            <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
            <input type="hidden" name="user_id" value="<?= (int) $userData['id']; ?>">
            <button type="submit" class="button button-danger">Eliminar usuario</button>
        </form>
    </div>
</section>
