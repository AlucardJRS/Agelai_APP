<?php
declare(strict_types=1);
?>
<section class="card">
    <h1>Resumen General</h1>
    <p>Control centralizado de Club Agelai para web y Android compartiendo la misma base de datos.</p>
</section>

<section class="stats-grid">
    <article class="stat-card">
        <h2>Usuarios Totales</h2>
        <strong><?= (int) $stats['users_total']; ?></strong>
    </article>
    <article class="stat-card">
        <h2>Perfiles Pendientes</h2>
        <strong><?= (int) $stats['users_pending']; ?></strong>
    </article>
    <article class="stat-card">
        <h2>Actividades Activas</h2>
        <strong><?= (int) $stats['activities_active']; ?></strong>
    </article>
    <article class="stat-card">
        <h2>Reservas Confirmadas</h2>
        <strong><?= (int) $stats['reservations_confirmed']; ?></strong>
    </article>
    <article class="stat-card">
        <h2>Pendiente Validacion Admin</h2>
        <strong><?= (int) $stats['reservations_pending_admin']; ?></strong>
    </article>
    <article class="stat-card">
        <h2>Lista de Espera</h2>
        <strong><?= (int) $stats['reservations_waitlist']; ?></strong>
    </article>
</section>

