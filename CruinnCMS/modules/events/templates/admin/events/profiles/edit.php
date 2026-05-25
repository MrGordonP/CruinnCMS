<?php \Cruinn\Template::requireCss('admin-site-builder.css'); ?>
<?php $eventNav = 'profiles'; ?>
<?php include dirname(__DIR__) . '/_nav.php'; ?>

<?php
$profile = $profile ?? [];
$errors = $errors ?? [];
$isEdit = !empty($profile['id']);
$action = $isEdit
    ? '/admin/events/profiles/' . (int) $profile['id']
    : '/admin/events/profiles';
?>

<div class="admin-article-edit">
    <h1><?= $isEdit ? 'Edit Events Profile' : 'New Events Profile' ?></h1>

    <form method="post" action="<?= e($action) ?>" class="form-article-meta">
        <?= csrf_field() ?>

        <div class="form-grid">
            <section class="form-section">
                <h3>Identity</h3>

                <div class="form-group">
                    <label for="event-profile-name">Profile Name</label>
                    <input type="text" id="event-profile-name" name="name" class="form-input" value="<?= e($profile['name'] ?? '') ?>" required>
                    <?php if (!empty($errors['name'])): ?><small style="color:var(--color-danger, #b91c1c);"><?= e($errors['name']) ?></small><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="event-profile-slug">Slug</label>
                    <input type="text" id="event-profile-slug" name="slug" class="form-input" value="<?= e($profile['slug'] ?? '') ?>">
                    <?php if (!empty($errors['slug'])): ?><small style="color:var(--color-danger, #b91c1c);"><?= e($errors['slug']) ?></small><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="event-profile-description">Description</label>
                    <textarea id="event-profile-description" name="description" class="form-input" rows="5"><?= e($profile['description'] ?? '') ?></textarea>
                    <small>Internal note to explain where this profile is meant to be used.</small>
                </div>
            </section>

            <section class="form-section">
                <h3>Rendering Defaults</h3>

                <div class="form-group">
                    <label for="event-profile-display-mode">Display Mode</label>
                    <select id="event-profile-display-mode" name="display_mode" class="form-input">
                        <option value="both"<?= ($profile['display_mode'] ?? 'both') === 'both' ? ' selected' : '' ?>>List and detail</option>
                        <option value="list"<?= ($profile['display_mode'] ?? '') === 'list' ? ' selected' : '' ?>>List only</option>
                        <option value="detail"<?= ($profile['display_mode'] ?? '') === 'detail' ? ' selected' : '' ?>>Detail only</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="event-profile-count">Events Per Page</label>
                    <input type="number" id="event-profile-count" name="events_per_page" min="1" max="100" class="form-input" value="<?= (int) ($profile['events_per_page'] ?? 10) ?>">
                </div>

                <div class="form-group">
                    <label for="event-profile-filter">Default Filter</label>
                    <select id="event-profile-filter" name="default_filter" class="form-input">
                        <option value="upcoming"<?= ($profile['default_filter'] ?? 'upcoming') === 'upcoming' ? ' selected' : '' ?>>Upcoming</option>
                        <option value="past"<?= ($profile['default_filter'] ?? '') === 'past' ? ' selected' : '' ?>>Past</option>
                    </select>
                </div>

                <label class="form-checkbox">
                    <input type="hidden" name="show_return_to_list" value="0">
                    <input type="checkbox" name="show_return_to_list" value="1"<?= !empty($profile['show_return_to_list']) ? ' checked' : '' ?>>
                    Show “Return to list” by default
                </label>

                <label class="form-checkbox">
                    <input type="hidden" name="show_event_navigation" value="0">
                    <input type="checkbox" name="show_event_navigation" value="1"<?= !empty($profile['show_event_navigation']) ? ' checked' : '' ?>>
                    Show previous / next navigation by default
                </label>
            </section>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Profile' : 'Create Profile' ?></button>
            <a href="/admin/events/profiles" class="btn btn-outline">Back to Profiles</a>
        </div>
    </form>
</div>
