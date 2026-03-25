<?php
/** @var string $csrfToken */
/** @var array $dirs — flat list of all directories */
/** @var array $errors */
/** @var array $successes */
/** @var int|null $selectedDir */
$errors = $errors ?? [];
$successes = $successes ?? [];
$selectedDir = $selectedDir ?? null;
?>

<section aria-labelledby="upload-title">
    <h1 id="upload-title">Bilder hochladen</h1>
    <p class="text-muted">Lade Bilder in eine Event-Galerie hoch. Thumbnails werden automatisch erstellt.</p>

    <?php foreach ($successes as $msg): ?>
        <div class="flash flash--success" role="alert">
            <span><?= htmlspecialchars($msg) ?></span>
        </div>
    <?php endforeach; ?>

    <?php foreach ($errors as $msg): ?>
        <div class="flash flash--error" role="alert">
            <span><?= htmlspecialchars($msg) ?></span>
        </div>
    <?php endforeach; ?>

    <?php if (empty($dirs)): ?>
        <p class="text-muted">
            Noch keine Verzeichnisse vorhanden.
            <a href="/gallery/create">Erstelle zuerst eine Event-Galerie.</a>
        </p>
    <?php else: ?>
        <form method="post" action="/upload" enctype="multipart/form-data" style="max-width: 480px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label for="dir_id" class="form-label">Zielverzeichnis</label>
                <select id="dir_id" name="dir_id" class="form-select" required>
                    <option value="">Verzeichnis wählen</option>
                    <?php foreach ($dirs as $dir): ?>
                        <option value="<?= (int) $dir['id'] ?>"
                            <?= ($selectedDir === (int) $dir['id']) ? 'selected' : '' ?>
                        ><?= htmlspecialchars($dir['dirname']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="images" class="form-label">Bilder (JPEG, PNG, WebP, GIF, max. 20 MB)</label>
                <input type="file" id="images" name="images[]" class="form-input"
                       accept="image/jpeg,image/png,image/webp,image/gif"
                       multiple required>
            </div>

            <button type="submit" class="btn btn--primary">Hochladen</button>
            <a href="/" class="btn btn--secondary">Abbrechen</a>
        </form>
    <?php endif; ?>
</section>
