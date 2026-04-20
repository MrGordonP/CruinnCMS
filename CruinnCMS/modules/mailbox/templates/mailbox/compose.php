<?php
/**
 * Compose / Reply / Forward form.
 *
 * @var array  $mailbox
 * @var array  $prefill   ['to', 'subject', 'body', 'in_reply_to', 'quote']
 * @var array  $errors
 * @var string $csrf_token
 */
$prefill = $prefill ?? [];
$errors  = $errors ?? [];
$action  = '/mail/' . (int) $mailbox['id'] . '/compose';
if (!empty($prefill['in_reply_to']) || isset($params['uid'])) {
    // Reply/forward action URL is set by the controller via its route redirect —
    // the form action is the POST URL for the current route which is already correct.
}
?>
<div class="mailbox-compose">
    <h1 class="page-title"><?= $this->escape($page_title ?? 'Compose') ?></h1>

    <?php if ($errors): ?>
        <div class="form-errors">
            <?php foreach ($errors as $e): ?>
                <p class="form-error"><?= $this->escape($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="compose-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= $this->escape($csrf_token) ?>">
        <?php if (!empty($prefill['in_reply_to'])): ?>
            <input type="hidden" name="in_reply_to" value="<?= $this->escape($prefill['in_reply_to']) ?>">
        <?php endif; ?>

        <div class="form-row">
            <label for="compose-to">To</label>
            <input class="form-input" id="compose-to" name="to" type="email"
                   value="<?= $this->escape($prefill['to'] ?? '') ?>" required autocomplete="email">
        </div>
        <div class="form-row">
            <label for="compose-cc">Cc</label>
            <input class="form-input" id="compose-cc" name="cc" type="email"
                   value="<?= $this->escape($prefill['cc'] ?? '') ?>" autocomplete="email">
        </div>
        <div class="form-row">
            <label for="compose-subject">Subject</label>
            <input class="form-input" id="compose-subject" name="subject" type="text"
                   value="<?= $this->escape($prefill['subject'] ?? '') ?>" required>
        </div>
        <div class="form-row form-row-body">
            <label for="compose-body">Message</label>
            <textarea class="form-input compose-textarea" id="compose-body" name="body" rows="16" required><?php
                echo $this->escape($prefill['body'] ?? '');
                if (!empty($prefill['quote'])): ?>

---
<?= $this->escape($prefill['quote']) ?><?php endif; ?>
</textarea>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Send</button>
            <a class="btn" href="javascript:history.back()">Cancel</a>
            <span class="compose-from">From: <strong><?= $this->escape($mailbox['email'] ?? $mailbox['position']) ?></strong></span>
        </div>
    </form>
</div>
