<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= htmlspecialchars($title ?? 'MVC Application') ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="<?= BASE_URL ?>/plugins/fontawesome-free/css/all.min.css">

    <!-- Ionicons -->
    <link rel="stylesheet"
          href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">

    <!-- Theme style -->
    <link rel="stylesheet"
          href="<?= BASE_URL ?>/dist/css/adminlte.min.css">
    <?php
    $cafeThemePath = PUBLIC_PATH . '/css/cafe-theme.css';
    $cafeThemeVersion = is_file($cafeThemePath) ? filemtime($cafeThemePath) : 1;
    ?>
    <link rel="stylesheet"
          href="<?= BASE_URL ?>/css/cafe-theme.css?v=<?= $cafeThemeVersion ?>">
</head>

<body class="kg-landing-page<?= ($title ?? '') === 'Login' ? ' kg-auth-page' : '' ?>">

    <?= $content ?>

    <?php if (($title ?? '') === 'Login'): ?>
        <script src="<?= BASE_URL ?>/plugins/jquery/jquery.min.js"></script>
        <script src="<?= BASE_URL ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="<?= BASE_URL ?>/js/Auth.js?v=<?= filemtime(PUBLIC_PATH . '/js/Auth.js') ?>"></script>
    <?php endif; ?>

</body>

</html>
