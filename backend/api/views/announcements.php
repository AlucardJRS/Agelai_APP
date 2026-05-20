<?php
declare(strict_types=1);
?>
<section class="card">
    <h1>Anuncios, Promociones y Horarios</h1>
    <p>
        Publica novedades generales o por modulo (gym, dietas, artes marciales, clases dirigidas).
    </p>
</section>

<section class="card">
    <h2>Nuevo Anuncio</h2>
    <form method="post" action="/dashboard/announcements/create" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken); ?>">

        <label>
            Modulo (vacío = general)
            <select name="module_code">
                <option value="">General</option>
                <?php foreach ($modules as $code => $name): ?>
                    <option value="<?= Security::e((string) $code); ?>"><?= Security::e((string) $name); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Titulo
            <input type="text" name="title" maxlength="120" required>
        </label>

        <label>
            Inicio vigencia
            <input type="datetime-local" name="starts_at" required>
        </label>

        <label>
            Fin vigencia
            <input type="datetime-local" name="ends_at" required>
        </label>

        <label class="col-span-2">
            Contenido
            <textarea name="body" rows="4" maxlength="3000" required></textarea>
        </label>

        <button type="submit" class="button col-span-2">Publicar anuncio</button>
    </form>
</section>

<section class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Titulo</th>
            <th>Modulo</th>
            <th>Vigencia</th>
            <th>Contenido</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($announcements as $announcement): ?>
            <tr>
                <td><?= (int) $announcement['id']; ?></td>
                <td><?= Security::e((string) $announcement['title']); ?></td>
                <td><?= Security::e((string) ($announcement['module_code'] ?? 'general')); ?></td>
                <td>
                    <small>Desde:</small> <?= Security::e((string) $announcement['starts_at']); ?><br>
                    <small>Hasta:</small> <?= Security::e((string) $announcement['ends_at']); ?>
                </td>
                <td><?= nl2br(Security::e((string) $announcement['body'])); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

