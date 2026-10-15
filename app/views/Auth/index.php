<main class="cafe-auth-shell">
    <section class="cafe-auth-showcase" aria-label="Klasik Grounds Cafe">
        <a href="<?= BASE_URL ?>/" class="cafe-auth-brand">
            <img src="<?= BASE_URL ?>/dist/img/logo.jpg" alt="Klasik Grounds Cafe logo">
            <span>Klasik Grounds<small>Cafe</small></span>
        </a>

        <div class="cafe-auth-message">
            <p class="cafe-auth-eyebrow">Good coffee. Good company.</p>
            <h1>Your daily cup,<br><em>made personal.</em></h1>
            <p>Sign in to manage the cafe, serve every order with care, and keep your day moving smoothly.</p>
        </div>

        <div class="cafe-auth-showcase-footer">
            <span class="cafe-auth-rule"></span>
            <span>Est. 2026 · Crafted with care</span>
        </div>
    </section>

    <section class="cafe-auth-form-panel">
        <div class="cafe-auth-form-wrap">
            <div class="cafe-auth-mobile-brand">
                <img src="<?= BASE_URL ?>/dist/img/logo.jpg" alt="Klasik Grounds Cafe logo">
            </div>

            <p class="cafe-auth-eyebrow">Welcome back</p>
            <h2>Sign in to your account</h2>
            <p class="cafe-auth-subtitle">Enter your details to continue to the cafe dashboard.</p>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success cafe-auth-alert">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger cafe-auth-alert">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form id="login-form" method="post" action="<?= BASE_URL ?>/Auth/login" class="cafe-auth-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="cafe-auth-input-wrap">
                        <i class="fas fa-user" aria-hidden="true"></i>
                        <input id="username" name="username" class="form-control" placeholder="Enter your username" autocomplete="username" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="cafe-auth-input-wrap">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <input id="password" type="password" name="password" class="form-control" placeholder="Enter your password" autocomplete="current-password" required>
                    </div>
                </div>

                <button type="submit" class="btn cafe-auth-submit btn-block">
                    Sign in <i class="fas fa-arrow-right ml-2" aria-hidden="true"></i>
                </button>
            </form>

            <div class="cafe-auth-divider"><span>or</span></div>

            <a href="<?= BASE_URL ?>/Kiosk" class="btn cafe-auth-guest btn-block">
                <i class="fas fa-mug-hot mr-2" aria-hidden="true"></i> Continue as guest
            </a>

            <p class="cafe-auth-register">
                New to Klasik Grounds?
                <a href="<?= BASE_URL ?>/Users/register">Create an account</a>
            </p>
        </div>
    </section>
</main>
