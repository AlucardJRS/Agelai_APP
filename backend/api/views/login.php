<?php
declare(strict_types=1);
?>
<section class="card">
    <div class="login-brand">
        <img src="/assets/brand/agelai-logo.png" alt="Logo Club Agelai" class="login-logo">
    </div>
    <h1>Acceso Administrador</h1>
    <p>Login local para gestionar usuarios, accesos, actividades y reservas.</p>
    <p class="small-note">Credenciales iniciales: <code>admin</code> / <code>Admin12345!</code></p>

    <form method="post" action="/dashboard/login" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">

        <label>
            Usuario
            <input type="text" name="username" maxlength="60" required autocomplete="username">
        </label>

        <label>
            Password
            <input type="password" name="password" maxlength="120" required autocomplete="current-password">
        </label>

        <button type="submit" class="button">Entrar</button>
    </form>
</section>

