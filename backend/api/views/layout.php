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
<body>
<header class="topbar">
    <div class="brand brand-lockup">
        <img src="/assets/brand/agelai-logo.png" alt="Logo Club Agelai" class="brand-logo">
        <div class="brand-copy">
            <strong>Club Agelai</strong>
            <span class="brand-subtitle">Dashboard</span>
        </div>
    </div>
    <?php if ($adminUsername !== ''): ?>
        <div class="admin-actions">
            <span class="admin-label">Admin: <?= Security::e($adminUsername); ?></span>
            <form method="post" action="/dashboard/logout">
                <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">
                <button type="submit" class="button button-danger">Salir</button>
            </form>
        </div>
    <?php endif; ?>
</header>

<?php if ($adminUsername !== ''): ?>
    <nav class="menu">
        <a class="<?= $currentPath === '/dashboard' ? 'active' : ''; ?>" href="/dashboard">Inicio</a>
        <a class="<?= $currentPath === '/dashboard/users' ? 'active' : ''; ?>" href="/dashboard/users">Usuarios</a>
        <a class="<?= $currentPath === '/dashboard/activities' ? 'active' : ''; ?>" href="/dashboard/activities">Actividades</a>
        <a class="<?= $currentPath === '/dashboard/schedule' ? 'active' : ''; ?>" href="/dashboard/schedule">Horarios</a>
        <a class="<?= $currentPath === '/dashboard/reservations' ? 'active' : ''; ?>" href="/dashboard/reservations">Reservas</a>
        <a class="<?= $currentPath === '/dashboard/announcements' ? 'active' : ''; ?>" href="/dashboard/announcements">Anuncios</a>
    </nav>
<?php endif; ?>

<main class="container">
    <?php if ($flash !== null && $flash['message'] !== ''): ?>
        <div class="flash flash-<?= Security::e($flash['type']); ?>">
            <?= Security::e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <?php include $viewFile; ?>
</main>
</body>
</html>
