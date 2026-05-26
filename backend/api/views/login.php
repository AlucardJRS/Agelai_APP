<?php
declare(strict_types=1);
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$showLocalCredentialsHint = str_starts_with($host, '127.0.0.1')
    || str_starts_with($host, 'localhost')
    || $remoteAddr === '127.0.0.1'
    || $remoteAddr === '::1';
?>
<section class="login-shell">
    <div class="login-glow login-glow-left" aria-hidden="true"></div>
    <div class="login-glow login-glow-right" aria-hidden="true"></div>

    <article class="login-glass-card">
        <div class="login-logo-wrap">
            <img src="/assets/brand/agelai-logo.png" alt="Logo Agelai Gym" class="login-logo-hero">
        </div>

        <h1 class="login-title">AGELAI <span>GYM</span></h1>
        <p class="login-subtitle">Accede a tu zona de administracion</p>

        <form method="post" action="/dashboard/login" class="login-form">
            <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">

            <label class="login-field">
                <input type="text" name="username" maxlength="60" required autocomplete="username" placeholder="Usuario">
            </label>

            <label class="login-field">
                <input type="password" name="password" maxlength="120" required autocomplete="current-password" placeholder="Password">
            </label>

            <div class="login-options">
                <label class="login-check-wrap">
                    <input type="checkbox" name="remember_login" value="1">
                    <span>Mantener sesion iniciada</span>
                </label>
                <a href="/dashboard/login#help" class="login-help-link">¿Olvido su contraseña?</a>
            </div>

            <button type="submit" class="login-submit">Entrar</button>
        </form>

        <?php if ($showLocalCredentialsHint): ?>
            <p class="login-credentials">
                Credenciales iniciales (solo entorno local): <code>admin</code> / <code>Admin12345!</code>
            </p>
        <?php endif; ?>
    </article>

    <footer class="login-footer">© 2026 Agelai Gym. Todos los derechos reservados.</footer>
</section>
