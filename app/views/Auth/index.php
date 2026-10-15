<div class="kg-landing">
    <header class="kg-topbar">
        <a href="<?= BASE_URL ?>/Auth" class="kg-wordmark">Klasik Grounds</a>
        <nav class="kg-nav" aria-label="Main navigation">
            <a href="#how-it-works">How it works</a>
            <a href="<?= BASE_URL ?>/Kiosk">View menu</a>
            <a href="#login" class="kg-nav-button">Sign in</a>
        </nav>
    </header>

    <main>
        <section class="kg-hero">
            <div class="kg-hero-content">
                <img class="kg-hero-logo" src="<?= BASE_URL ?>/dist/img/logo.jpg" alt="Klasik Grounds Cafe logo">
                <p class="kg-eyebrow">Klasik Grounds Cafe</p>
                <h1>Every cup is a masterpiece.</h1>
                <p class="kg-hero-copy">Handcrafted drinks made with quality beans and fresh ingredients. Every cup, every time.</p>
                <div class="kg-hero-actions">
                    <a href="#login" class="kg-button kg-button-light">Join rewards</a>
                    <a href="<?= BASE_URL ?>/Kiosk" class="kg-button kg-button-outline">View menu</a>
                </div>
            </div>
        </section>

        <section class="kg-values" aria-label="Our values">
            <div><strong>Fresh</strong><span>Brewed to order</span></div>
            <div><strong>Daily</strong><span>Freshly roasted beans</span></div>
            <div><strong>Cozy</strong><span>Chill and relax vibes</span></div>
        </section>

        <section id="how-it-works" class="kg-section kg-how-it-works">
            <div class="kg-section-heading">
                <p class="kg-eyebrow">Klasik Grounds rewards</p>
                <h2>How it works</h2>
                <p>Start earning rewards in four easy steps.</p>
            </div>

            <div class="kg-steps">
                <article class="kg-step">
                    <strong>01</strong>
                    <h3>Get an Account</h3>
                    <p>Have the staff make you an account when you place your order.</p>
                </article>
                <article class="kg-step">
                    <strong>02</strong>
                    <h3>Login</h3>
                    <p>Enter the login credentials given to you by the staff.</p>
                </article>
                <article class="kg-step">
                    <strong>03</strong>
                    <h3>Buy</h3>
                    <p>Use your account to order and earn one point per day.</p>
                </article>
                <article class="kg-step">
                    <strong>04</strong>
                    <h3>Redeem</h3>
                    <p>Enjoy a free coffee every time you reach 10 points.</p>
                </article>
            </div>
        </section>

        <section id="login" class="kg-login-section">
            <div class="kg-login-intro">
                <p class="kg-eyebrow">Welcome back</p>
                <h2>Sign in and keep earning.</h2>
                <p>Use your Klasik Grounds account to order and track your rewards.</p>
            </div>

            <div class="kg-login-card">
                <h3>Sign in</h3>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success kg-alert">
                        <?= htmlspecialchars($_SESSION['success']) ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger kg-alert">
                        <?= htmlspecialchars($_SESSION['error']) ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <form id="login-form" method="post" action="<?= BASE_URL ?>/Auth/login">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" placeholder="Username" autocomplete="username" required>

                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" placeholder="Password" autocomplete="current-password" required>

                    <button type="submit" class="kg-button kg-button-light kg-submit">Sign in</button>
                </form>

                <a href="<?= BASE_URL ?>/Kiosk" class="kg-guest-link">Continue as guest</a>
                <p class="kg-register-link">Need an account? <a href="<?= BASE_URL ?>/Users/register">Register here</a></p>
            </div>
        </section>
    </main>

    <footer class="kg-footer">
        <img src="<?= BASE_URL ?>/dist/img/logo.jpg" alt="Klasik Grounds Cafe logo" class="kg-footer-logo">
        <div class="kg-footer-details">
            <p><strong>Address:</strong> #30 Rizal Avenue P3 Rosario, Santiago, Philippines, 3311</p>
            <p><strong>Instagram:</strong> <a href="https://www.instagram.com/kgcafe_/" target="_blank" rel="noopener noreferrer">@kgcafe_</a></p>
            <p><strong>Facebook:</strong> Klasik Grounds</p>
            <p><strong>Phone:</strong> <a href="tel:+639386067288">0938 606 7288</a></p>
        </div>
        <p class="kg-footer-copy">© <?= date('Y') ?> Klasik Grounds Cafe. Crafted with care.</p>
    </footer>
</div>
