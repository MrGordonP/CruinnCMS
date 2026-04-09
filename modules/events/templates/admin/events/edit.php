<?php \IGA\Template::requireCss('admin-events.css'); ?>
<div class="admin-event-edit">
    <h1><?= e($title) ?></h1>

    <form method="post" action="<?= $event ? '/admin/events/' . (int) $event['id'] : '/admin/events' ?>">
        <?= csrf_field() ?>

        <div class="form-grid">
            <!-- Left column: Core details -->
            <div class="form-section">
                <h3>Event Details</h3>

                <div class="form-group">
                    <label for="title">Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" value="<?= e($event['title'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="event_type">Event Type <span class="required">*</span></label>
                    <select id="event_type" name="event_type" required>
                        <option value="">— Select —</option>
                        <?php foreach (['fieldtrip' => 'Field Trip', 'lecture' => 'Lecture', 'conference' => 'Conference', 'workshop' => 'Workshop', 'social' => 'Social', 'other' => 'Other'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($event['event_type'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date_start">Start Date &amp; Time <span class="required">*</span></label>
                        <input type="datetime-local" id="date_start" name="date_start" value="<?= e($event ? date('Y-m-d\TH:i', strtotime($event['date_start'])) : '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="date_end">End Date &amp; Time</label>
                        <input type="datetime-local" id="date_end" name="date_end" value="<?= e($event && !empty($event['date_end']) ? date('Y-m-d\TH:i', strtotime($event['date_end'])) : '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" value="<?= e($event['location'] ?? '') ?>" placeholder="e.g. Trinity College Dublin, Room 2.04">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="location_lat">Latitude</label>
                        <input type="text" id="location_lat" name="location_lat" value="<?= e($event['location_lat'] ?? '') ?>" placeholder="53.3438">
                    </div>
                    <div class="form-group">
                        <label for="location_lng">Longitude</label>
                        <input type="text" id="location_lng" name="location_lng" value="<?= e($event['location_lng'] ?? '') ?>" placeholder="-6.2546">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="10"><?= e($event['description'] ?? '') ?></textarea>
                    <small class="help-text">HTML is allowed for formatting.</small>
                </div>
            </div>

            <!-- Right column: Settings -->
            <div class="form-section">
                <h3>Settings</h3>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($event['status'] ?? 'draft') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <h3>Registration &amp; Pricing</h3>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="registration_open" value="1" <?= ($event['registration_open'] ?? 1) ? 'checked' : '' ?>>
                        Registration open
                    </label>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Price (&euro;)</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" value="<?= e($event['price'] ?? '0.00') ?>">
                        <small class="help-text">0 = free event</small>
                    </div>
                    <div class="form-group">
                        <label for="currency">Currency</label>
                        <select id="currency" name="currency">
                            <option value="EUR" <?= ($event['currency'] ?? 'EUR') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                            <option value="GBP" <?= ($event['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP</option>
                            <option value="USD" <?= ($event['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="capacity">Capacity</label>
                    <input type="number" id="capacity" name="capacity" min="0" value="<?= e($event['capacity'] ?? '0') ?>">
                    <small class="help-text">0 = unlimited</small>
                </div>

                <div class="form-group">
                    <label for="reg_deadline">Registration Deadline</label>
                    <input type="datetime-local" id="reg_deadline" name="reg_deadline" value="<?= e($event && !empty($event['reg_deadline']) ? date('Y-m-d\TH:i', strtotime($event['reg_deadline'])) : '') ?>">
                    <small class="help-text">Leave blank for no deadline.</small>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $event ? 'Update Event' : 'Create Event' ?></button>
            <a href="<?= $event ? '/admin/events/' . (int) $event['id'] : '/admin/events' ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
