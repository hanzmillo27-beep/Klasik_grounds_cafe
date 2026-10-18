<div class="kg-landing">
    <header class="kg-topbar d-flex align-items-center justify-content-between">
        <a href="<?= BASE_URL ?>/Auth" class="kg-wordmark">Klasik Grounds</a>
        <nav class="kg-nav d-flex align-items-center" aria-label="Main navigation">
            <a href="<?= BASE_URL ?>/Kiosk" class="d-none d-sm-inline">Order</a>
            <button type="button" class="kg-nav-button ml-2 ml-sm-3" data-login-open aria-haspopup="dialog" aria-controls="login-dialog">Sign in</button>
        </nav>
    </header>

    <main>
        <section class="kg-hero d-flex align-items-center justify-content-center">
            <div class="container">
                <div class="kg-hero-content mx-auto">
                <img class="kg-hero-logo" src="<?= BASE_URL ?>/dist/img/logo.jpg" alt="Klasik Grounds Cafe logo">
                <p class="kg-eyebrow">Klasik Grounds Cafe</p>
                <h1>Every cup is a masterpiece.</h1>
                <p class="kg-hero-copy">Handcrafted drinks made with quality beans and fresh ingredients. Every cup, every time.</p>
                <div class="kg-hero-actions d-flex flex-column flex-sm-row align-items-center justify-content-center">
                    <button type="button" class="kg-button kg-button-light mb-2 mb-sm-0" data-login-open aria-haspopup="dialog" aria-controls="login-dialog">Join rewards</button>
                    <a href="<?= BASE_URL ?>/Kiosk" class="kg-button kg-button-outline ml-sm-3">Order</a>
                </div>
                </div>
            </div>
        </section>

        <section class="kg-values" aria-label="Our values">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-12 col-md-4"><strong>Fresh</strong><span>Brewed to order</span></div>
                    <div class="col-12 col-md-4"><strong>Daily</strong><span>Freshly roasted beans</span></div>
                    <div class="col-12 col-md-4"><strong>Cozy</strong><span>Chill and relax vibes</span></div>
                </div>
            </div>
        </section>

    </main>

    <footer class="kg-footer">
        <div class="container">
            <img src="<?= BASE_URL ?>/dist/img/logo.jpg" alt="Klasik Grounds Cafe logo" class="kg-footer-logo">
        <div class="kg-footer-details">
            <p><strong>Address:</strong> #30 Rizal Avenue P3 Rosario, Santiago, Philippines, 3311</p>
            <p><strong>Instagram:</strong> <a href="https://www.instagram.com/kgcafe_/" target="_blank" rel="noopener noreferrer">@kgcafe_</a></p>
            <p><strong>Facebook:</strong> Klasik Grounds</p>
            <p><strong>Phone:</strong> <a href="tel:+639386067288">0938 606 7288</a></p>
        </div>
        <p class="kg-footer-copy">© <?= date('Y') ?> Klasik Grounds Cafe. Crafted with care.</p>
        </div>
    </footer>
</div>

<div id="login-dialog" class="modal fade kg-login-modal" tabindex="-1" role="dialog" aria-labelledby="login-title" aria-describedby="login-description" aria-hidden="true" data-auto-open="<?= isset($_SESSION['error']) || isset($_SESSION['success']) ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content kg-login-card kg-login-dialog">
    <button type="button" class="kg-dialog-close" data-dismiss="modal" aria-label="Close sign in">&times;</button>
    <p class="kg-eyebrow">Welcome back</p>
    <h2 id="login-title">Sign in</h2>
    <p id="login-description">Use your Klasik Grounds account to order and track your rewards.</p>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success kg-alert" role="status">
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div id="login-error" class="alert alert-danger kg-alert" role="alert" tabindex="-1">
            <?= htmlspecialchars((string) $_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form id="login-form" method="post" action="<?= BASE_URL ?>/Auth/login">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" placeholder="Username" autocomplete="username" required autofocus>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" placeholder="Password" autocomplete="current-password" required>

        <button type="submit" class="kg-button kg-button-light kg-submit">Sign in</button>
    </form>
    </div>
    </div>
</div>
