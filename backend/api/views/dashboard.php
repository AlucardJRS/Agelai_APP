<?php
declare(strict_types=1);
?>
<section class="app-hero-card">
    <h1>Resumen General</h1>
    <p>
        Control centralizado de Club Agelai para web y Android compartiendo la misma base de datos.
        Optimiza la gestion operativa de usuarios, actividades y reservas en un solo panel.
    </p>
    <div class="app-hero-actions">
        <a href="/dashboard/reservations" class="app-hero-primary">Ver Reservas</a>
        <a href="/dashboard/activities" class="app-hero-secondary">Configurar Actividades</a>
    </div>
</section>

<section class="app-kpi-grid">
    <article class="app-kpi-card">
        <h2>Usuarios Totales</h2>
        <strong><?= (int) $stats['users_total']; ?></strong>
    </article>
    <article class="app-kpi-card">
        <h2>Perfiles Pendientes</h2>
        <strong><?= (int) $stats['users_pending']; ?></strong>
    </article>
    <article class="app-kpi-card">
        <h2>Actividades Activas</h2>
        <strong><?= (int) $stats['activities_active']; ?></strong>
    </article>
    <article class="app-kpi-card">
        <h2>Reservas Confirmadas</h2>
        <strong><?= (int) $stats['reservations_confirmed']; ?></strong>
    </article>
    <article class="app-kpi-card">
        <h2>Pendiente Validacion Admin</h2>
        <strong><?= (int) $stats['reservations_pending_admin']; ?></strong>
    </article>
    <article class="app-kpi-card">
        <h2>Lista de Espera</h2>
        <strong><?= (int) $stats['reservations_waitlist']; ?></strong>
    </article>
</section>

<section class="app-bottom-grid">
    <article class="app-activity-card">
        <header>
            <h3>Actividad Reciente</h3>
            <a href="/dashboard/users">Ver todo</a>
        </header>
        <ul>
            <li>
                <span>Nuevo usuario registrado</span>
                <strong>Revision de permisos pendiente</strong>
            </li>
            <li>
                <span>Actualizacion de agenda</span>
                <strong>Revisa Horarios y Reservas</strong>
            </li>
            <li>
                <span>Control de comunicaciones</span>
                <strong>Publica novedades en Anuncios</strong>
            </li>
        </ul>
    </article>

    <article class="app-quick-card">
        <h3>Accesos Rapidos</h3>
        <div class="app-quick-grid">
            <a href="/dashboard/users">Inscribir</a>
            <a href="/dashboard/activities">Clases</a>
            <a href="/dashboard/announcements">Promos</a>
            <a href="/dashboard/schedule">Ayuda</a>
        </div>
    </article>
</section>
