<?php
// Expects $values (title, author, year_published, cover_public_id, bookID), $action, $submitLabel, $error.
?>
<?php form_error($error ?? null); ?>
<form class="form" action="<?= e($action) ?>" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field">
        <label for="title">Title</label>
        <input id="title" type="text" name="title" maxlength="255" value="<?= e($values['title']) ?>" required>
    </div>
    <div class="field">
        <label for="author">Author</label>
        <input id="author" type="text" name="author" maxlength="255" value="<?= e($values['author']) ?>" required>
    </div>
    <div class="field">
        <label for="year_published">Published year</label>
        <input id="year_published" type="number" inputmode="numeric" name="year_published" min="1" max="<?= date('Y') ?>" value="<?= e($values['year_published']) ?>" required>
    </div>
    <fieldset class="choices">
        <legend>How can people get this book?</legend>
        <?php foreach (HANDOVERS as $value => $label): ?>
            <label class="check"><input type="radio" name="handover" value="<?= $value ?>"<?= ($values['handover'] ?? 'meet') === $value ? ' checked' : '' ?>> <?= e($label) ?></label>
        <?php endforeach; ?>
        <p class="help">Meeting in person is simplest and free. With courier, the person receiving the book pays.</p>
    </fieldset>
    <?php if (cloudinary_enabled()): ?>
        <div class="field">
            <label for="cover">Cover photo <span class="optional">(optional)</span></label>
            <?php if (!empty($values['cover_public_id'])): ?>
                <div class="current-image"><?= book_cover($values, 120) ?></div>
                <label class="check"><input type="checkbox" name="remove_cover" value="1"> Remove this photo</label>
            <?php endif; ?>
            <img class="preview" data-preview="cover" alt="Selected cover preview" hidden>
            <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp" data-image-input="cover" aria-describedby="cover-help">
            <p class="help" id="cover-help">JPG, PNG or WebP, up to 5 MB.</p>
        </div>
    <?php endif; ?>
    <?php if (isset($shareOption)): ?>
        <label class="check"><input type="checkbox" name="share" value="1"<?= $shareOption ? ' checked' : '' ?>> Share now, so others can find and request it</label>
    <?php endif; ?>
    <div class="form__actions">
        <button type="submit" class="btn btn--primary"><?= e($submitLabel) ?></button>
        <a class="btn btn--ghost" href="/profile.php">Cancel</a>
    </div>
</form>
