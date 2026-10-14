<div class="register-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <a href="<?= BASE_URL ?>/" class="h1"><b>Simple</b> MVC</a>
        </div>

        <div class="card-body">
            <p class="login-box-msg">Create your account</p>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form id="register-form" method="post" action="<?= BASE_URL ?>/Users/register">
                <div class="input-group mb-3">
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-user"></span></div>
                    </div>
                </div>

                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control"
                           placeholder="Password" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                </div>

                <div class="input-group mb-3">
                    <input type="password" name="confirm_password" class="form-control"
                           placeholder="Confirm password" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Register</button>
            </form>

            <p class="mb-0 mt-3 text-center">
                <a href="<?= BASE_URL ?>/Auth">I already have an account</a>
            </p>
        </div>
    </div>
</div>
