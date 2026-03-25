<?php
/** @var string $csrfToken */
/** @var array $errors */
/** @var array $old — previous input values for repopulation */
$errors = $errors ?? [];
$old = $old ?? [];
?>

<section aria-labelledby="create-title">
    <h1 id="create-title">Event-Galerie anlegen</h1>
    <p class="text-muted">Erstelle eine neue Galerie für ein Event. Das Jahresverzeichnis wird automatisch erzeugt.</p>

    <?php if (!empty($errors)): ?>
        <div class="flash flash--error" role="alert">
            <span><?= htmlspecialchars(implode(' ', $errors)) ?></span>
        </div>
    <?php endif; ?>

    <form method="post" action="/gallery/create" style="max-width: 480px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="form-group">
            <label for="event_date" class="form-label">Datum</label>
            <input type="date" id="event_date" name="event_date" class="form-input"
                   required value="<?= htmlspecialchars($old['event_date'] ?? date('Y-m-d')) ?>">
        </div>

        <div class="form-group">
            <label for="event_name" class="form-label">Eventname</label>
            <input type="text" id="event_name" name="event_name" class="form-input"
                   required maxlength="100"
                   placeholder="z.B. Geburtstag, Wanderung, Konzert"
                   value="<?= htmlspecialchars($old['event_name'] ?? '') ?>">
        </div>

        <button type="submit" class="btn btn--primary">Galerie anlegen</button>
        <a href="/" class="btn btn--secondary">Abbrechen</a>
    </form>
</section>
