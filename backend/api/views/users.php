<?php
declare(strict_types=1);

$filtersData = is_array($filters ?? null) ? $filters : [];
$filterNombre = (string) ($filtersData['nombre'] ?? '');
$filterApellidos = (string) ($filtersData['apellidos'] ?? '');
$filterMovil = (string) ($filtersData['movil'] ?? '');
?>
<section class="card">
    <h1>Usuarios y Permisos por Modulo</h1>
    <p>
        Gestiona altas, bajas, bloqueos y permisos por modulo de forma separada del analisis de uso.
        Pulsa en un usuario para abrir su ficha completa en otra ventana de gestion.
    </p>
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
</section>

<section class="card">
    <h2>Buscar Usuarios</h2>
    <form method="get" action="/dashboard/users/altas-bajas" class="form-grid">
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
            <a href="/dashboard/users/altas-bajas" class="button button-secondary users-clear-link">Limpiar</a>
        </div>
    </form>
</section>

<section class="card">
    <h2>Crear Usuario</h2>
    <p class="small-note users-note-dark">
        Campos obligatorios: nombre, apellidos, movil, direccion y email.
        Si no conoces Google ID, dejalo vacio y se creara un ID provisional.
    </p>
    <form method="post" action="/dashboard/users/create" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">

        <label>
            Nombre
            <input type="text" name="first_name" maxlength="80" required>
        </label>

        <label>
            Apellidos
            <input type="text" name="last_name" maxlength="120" required>
        </label>

        <label>
            Movil
            <input type="text" name="phone" maxlength="30" required placeholder="+34 ...">
        </label>

        <label>
            Direccion
            <input type="text" name="address" maxlength="220" required>
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

<section class="card">
    <h2>Listado de Usuarios</h2>
    <?php if (count($users) === 0): ?>
        <p>No hay usuarios para los filtros aplicados.</p>
    <?php else: ?>
        <div class="users-list-grid">
            <?php foreach ($users as $user): ?>
                <?php
                $fullName = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
                $status = (string) ($user['status'] ?? 'pending');
                ?>
                <article class="users-list-card">
                    <div>
                        <h3><?= Security::e($fullName === '' ? 'Sin nombre' : $fullName); ?></h3>
                        <p><strong>Movil:</strong> <?= Security::e((string) ($user['phone'] ?? '')); ?></p>
                        <span class="tag <?= $status === 'pending' ? 'tag-pending' : ''; ?>"><?= Security::e($status); ?></span>
                    </div>
                    <a class="button" href="/dashboard/users/altas-bajas/<?= (int) $user['id']; ?>">Abrir ficha</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
