<div class="container">
    <div class="login-page">
        <h1>Forgot Password</h1>
        <p>Enter your email address and we'll send you a link to reset your password.</p>
        <form method="post" action="/forgot-password" class="form-login">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required autofocus autocomplete="email" class="form-input">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
        </form>
        <p class="form-footer-link"><a href="/login">&larr; Back to Login</a></p>
    </div>
</div>
