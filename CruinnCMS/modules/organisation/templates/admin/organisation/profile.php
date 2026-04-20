<?php
/**
 * Organisation Admin — Profile
 */
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h1>Organisation Profile</h1>
        <div class="admin-section-header-actions">
            <a href="/admin/organisation/officers" class="btn btn-secondary btn-sm">Officers</a>
            <a href="/admin/organisation/meetings" class="btn btn-secondary btn-sm">Meetings</a>
        </div>
    </div>



    <form method="post" action="/admin/organisation/profile" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">

        <div class="form-row">
            <div class="form-group form-group-grow">
                <label for="name">Organisation Name</label>
                <input type="text" name="name" id="name" class="form-input"
                       value="<?= e($profile['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="short_name">Short Name / Acronym</label>
                <input type="text" name="short_name" id="short_name" class="form-input"
                       value="<?= e($profile['short_name'] ?? '') ?>" placeholder="e.g. IGA">
            </div>
        </div>

        <div class="form-group">
            <label for="tagline">Tagline</label>
            <input type="text" name="tagline" id="tagline" class="form-input"
                   value="<?= e($profile['tagline'] ?? '') ?>">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="founded_year">Year Founded</label>
                <input type="number" name="founded_year" id="founded_year" class="form-input input-narrow"
                       value="<?= e($profile['founded_year'] ?? '') ?>"
                       min="1800" max="<?= date('Y') ?>" placeholder="e.g. 1959">
            </div>
            <div class="form-group form-group-grow">
                <label for="registration_no">Registration Number</label>
                <input type="text" name="registration_no" id="registration_no" class="form-input"
                       value="<?= e($profile['registration_no'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="address">Address</label>
            <textarea name="address" id="address" class="form-input" rows="3"><?= e($profile['address'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" class="form-input"
                       value="<?= e($profile['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" name="phone" id="phone" class="form-input"
                       value="<?= e($profile['phone'] ?? '') ?>">
            </div>
            <div class="form-group form-group-grow">
                <label for="website">Website</label>
                <input type="url" name="website" id="website" class="form-input"
                       value="<?= e($profile['website'] ?? '') ?>" placeholder="https://…">
            </div>
        </div>

        <div class="form-group">
            <label for="bio">About the Organisation</label>
            <textarea name="bio" id="bio" class="form-input" rows="5"
                      placeholder="Public-facing description"><?= e($profile['bio'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Profile</button>
        </div>
    </form>
</div>

<style>
.form-group-grow { flex: 1; }
.input-narrow    { width: 100px; }
</style>
