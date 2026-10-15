<div class="kg-landing">
    <header class="kg-topbar d-flex align-items-center justify-content-between">
        <a href="<?= BASE_URL ?>/Auth" class="kg-wordmark">Klasik Grounds</a>
        <nav class="kg-nav d-flex align-items-center" aria-label="Main navigation">
            <a href="<?= BASE_URL ?>/Kiosk" class="d-none d-sm-inline">Order</a>
            <a href="#login" class="kg-nav-button ml-2 ml-sm-3">Sign in</a>
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
                    <a href="#login" class="kg-button kg-button-light mb-2 mb-sm-0">Join rewards</a>
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

        <section id="login" class="kg-login-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-12 col-lg-6 mb-4 mb-lg-0">
                        <div class="kg-login-intro">
                            <p class="kg-eyebrow">Welcome back</p>
                            <h2>Sign in and keep earning.</h2>
                            <p>Use your Klasik Grounds account to order and track your rewards.</p>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="kg-login-card mx-auto">
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

                        </div>
                    </div>
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
