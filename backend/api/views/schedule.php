<?php
declare(strict_types=1);
?>
<section class="card">
    <h1>Horarios Semanales</h1>
    <p>
        Tablon visual de clases y actividades por semana, dia y sede.
        Sedes activas: <strong><?= Security::e(($locationNames['cala_dor'] ?? "Cala d'Or")); ?></strong> (principal)
        y <strong><?= Security::e(($locationNames['cala_egos'] ?? 'Cala Egos')); ?></strong>.
    </p>
</section>

<?php if (count($weekBoard) === 0): ?>
    <section class="card">
        <p>No hay actividades activas para las proximas semanas.</p>
    </section>
<?php else: ?>
    <?php foreach ($weekBoard as $week): ?>
        <section class="card schedule-week">
            <h2><?= Security::e((string) $week['week_label']); ?></h2>
            <div class="schedule-grid">
                <?php foreach ($week['days'] as $day): ?>
                    <article class="schedule-day">
                        <header>
                            <h3><?= Security::e((string) $day['day_label']); ?></h3>
                            <small><?= Security::e((string) $day['date_label']); ?></small>
                        </header>
                        <?php if (count($day['items']) === 0): ?>
                            <p class="small-note">Sin actividades</p>
                        <?php else: ?>
                            <div class="schedule-items">
                                <?php foreach ($day['items'] as $item): ?>
                                    <?php
                                    $remaining = (int) ($item['remaining_slots'] ?? 0);
                                    $capacity = (int) ($item['capacity'] ?? 0);
                                    ?>
                                    <div class="schedule-item">
                                        <strong><?= Security::e((string) $item['title']); ?></strong>
                                        <div class="meta-row">
                                            <span class="tag"><?= Security::e((string) $item['module_code']); ?></span>
                                            <span class="tag"><?= Security::e((string) ($item['location_label'] ?? $item['location'] ?? '')); ?></span>
                                        </div>
                                        <small><?= Security::e((string) ($item['time_range'] ?? '')); ?></small>
                                        <small>Cupo: <?= $capacity - $remaining; ?>/<?= $capacity; ?> (libres: <?= $remaining; ?>)</small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

