<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <a href="<?= BASE_URL ?>/" class="h1"><b>Klasik Grounds Cafe</b> <br>Management System</a>
        </div>

        <div class="card-body">
            <p class="login-box-msg">Sign in to start your session</p>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                </div>

                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>

                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            <form id="login-form" method="post" action="<?= BASE_URL ?>/Auth/login">
                <div class="input-group mb-3">
                    <input name="username" class="form-control" placeholder="Username" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                    </div>
                </div>

                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </form>

            <div class="text-center mt-3">
                <a href="<?= BASE_URL ?>/Kiosk" class="btn btn-outline-primary btn-block">
                    <i class="fas fa-coffee mr-1"></i> Continue as Guest
                </a>
            </div>

            <p class="mb-0 mt-3 text-center">
                <a href="<?= BASE_URL ?>/Users/register">Register a new account</a>
            </p>
        </div>
    </div>
</div>
