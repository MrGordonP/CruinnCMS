<div class="container">
    <div class="profile-page">
        <h1>Delete My Account</h1>

        <div class="detail-card detail-card-danger">
            <h2>This action is permanent</h2>
            <p>Deleting your account will:</p>
            <ul>
                <li>Remove all your personal details (name, email, address, phone)</li>
                <li>Unlink any social login accounts (Google, Facebook, X)</li>
                <li>Cancel any event registrations</li>
                <li>Remove your notification preferences</li>
                <li>Anonymise your forum posts (the content stays, but your name is removed)</li>
                <li>Delete your activity history</li>
            </ul>
            <p><strong>This cannot be undone.</strong> If you want to keep a copy of your data, please <a href="/members/data-export">download your data</a> first.</p>
        </div>

        <form method="post" action="/members/delete-account" class="form-delete-account">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="confirmation">Type <strong>DELETE</strong> to confirm:</label>
                <input type="text" id="confirmation" name="confirmation" required
                       class="form-input" autocomplete="off"
                       pattern="DELETE" title="Type DELETE in capitals to confirm">
            </div>
            <div class="form-actions">
                <a href="/members/profile" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-danger">Permanently Delete My Account</button>
            </div>
        </form>
    </div>
</div>
