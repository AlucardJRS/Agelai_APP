<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Security::e($title); ?> - Club Agelai</title>
    <link rel="icon" type="image/png" href="/assets/brand/agelai-logo.png">
    <link rel="apple-touch-icon" href="/assets/brand/agelai-logo.png">
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<?php $isLoginPage = $currentPath === '/dashboard/login'; ?>
<body class="<?= $isLoginPage ? 'login-page' : 'app-page'; ?>">
<?php if ($isLoginPage): ?>
    <main class="container login-main-container">
        <?php if ($flash !== null && $flash['message'] !== ''): ?>
            <div class="flash flash-<?= Security::e($flash['type']); ?>">
                <?= Security::e($flash['message']); ?>
            </div>
        <?php endif; ?>
        <?php include $viewFile; ?>
    </main>
<?php else: ?>
    <div class="app-shell">
        <aside class="app-sidebar">
            <div class="app-sidebar-brand">
                <div class="app-sidebar-logo-wrap">
                    <img src="/assets/brand/agelai-logo.png" alt="Logo Club Agelai" class="app-sidebar-logo">
                </div>
                <div>
                    <h2>Admin Dashboard</h2>
                    <p>Elite Performance</p>
                </div>
            </div>

            <nav class="app-sidebar-nav" aria-label="Navegacion principal">
                <a class="<?= $currentPath === '/dashboard' ? 'active' : ''; ?>" href="/dashboard">Inicio</a>
                <a class="<?= str_starts_with($currentPath, '/dashboard/users') ? 'active' : ''; ?>" href="/dashboard/users">Usuarios</a>
                <a class="<?= $currentPath === '/dashboard/activities' ? 'active' : ''; ?>" href="/dashboard/activities">Actividades</a>
                <a class="<?= $currentPath === '/dashboard/schedule' ? 'active' : ''; ?>" href="/dashboard/schedule">Horarios</a>
                <a class="<?= $currentPath === '/dashboard/reservations' ? 'active' : ''; ?>" href="/dashboard/reservations">Reservas</a>
                <a class="<?= $currentPath === '/dashboard/announcements' ? 'active' : ''; ?>" href="/dashboard/announcements">Anuncios</a>
            </nav>

            <a href="/dashboard/activities" class="app-sidebar-cta">+ Nueva Actividad</a>

            <div class="app-sidebar-footer">
                <a href="/dashboard" class="app-sidebar-link">Configuracion</a>
                <form method="post" action="/dashboard/logout">
                    <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
                    <button type="submit" class="app-sidebar-link app-sidebar-logout">Cerrar Sesion</button>
                </form>
            </div>
        </aside>

        <div class="app-main">
            <header class="app-topbar" aria-label="Barra superior">
                <nav class="app-topbar-nav" aria-label="Navegacion superior">
                    <a class="<?= $currentPath === '/dashboard' ? 'active' : ''; ?>" href="/dashboard">Inicio</a>
                    <a class="<?= str_starts_with($currentPath, '/dashboard/users') ? 'active' : ''; ?>" href="/dashboard/users">Usuarios</a>
                    <a class="<?= $currentPath === '/dashboard/activities' ? 'active' : ''; ?>" href="/dashboard/activities">Actividades</a>
                    <a class="<?= $currentPath === '/dashboard/schedule' ? 'active' : ''; ?>" href="/dashboard/schedule">Horarios</a>
                    <a class="<?= $currentPath === '/dashboard/reservations' ? 'active' : ''; ?>" href="/dashboard/reservations">Reservas</a>
                    <a class="<?= $currentPath === '/dashboard/announcements' ? 'active' : ''; ?>" href="/dashboard/announcements">Anuncios</a>
                </nav>

                <div class="app-topbar-actions">
                    <form method="get" action="/dashboard/users" class="app-search-form" role="search">
                        <label for="app-dashboard-search" class="sr-only">Buscar</label>
                        <input id="app-dashboard-search" type="search" name="nombre" maxlength="80" placeholder="Buscar usuarios...">
                    </form>
                    <span class="app-admin-chip">Admin: <?= Security::e($adminUsername); ?></span>
                </div>
            </header>

            <main class="container app-content">
                <?php if ($flash !== null && $flash['message'] !== ''): ?>
                    <div class="flash flash-<?= Security::e($flash['type']); ?>">
                        <?= Security::e($flash['message']); ?>
                    </div>
                <?php endif; ?>
                <?php include $viewFile; ?>
            </main>

            <footer class="app-footer">
                <span>© 2026 Agelai Gym. Todos los derechos reservados.</span>
                <div>
                    <a href="/dashboard">Terminos</a>
                    <a href="/dashboard">Privacidad</a>
                </div>
            </footer>
        </div>
    </div>
<?php endif; ?>
</body>
</html>
