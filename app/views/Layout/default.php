<?php
    $title = $title ?? 'Dashboard';
    $loggedInUser = $_SESSION['user'] ?? [];
    $accountRole = $loggedInUser['role'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?? 'MVC Application') ?></title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/plugins/fontawesome-free/css/all.min.css">
  <!-- Ionicons -->
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  <!-- Tempusdominus Bootstrap 4 -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
  <!-- iCheck -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
   <!-- SweetAlert2 -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
  <!-- Toastr -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/plugins/toastr/toastr.min.css">
  <!-- Daterange picker -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/plugins/daterangepicker/daterangepicker.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

  <!-- Theme style -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/dist/css/adminlte.min.css">
  <?php
  $cafeThemePath = PUBLIC_PATH . '/css/cafe-theme.css';
  $cafeThemeVersion = is_file($cafeThemePath) ? filemtime($cafeThemePath) : 1;
  ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/css/cafe-theme.css?v=<?= $cafeThemeVersion ?>">

</head>

<body class="hold-transition sidebar-mini layout-fixed"
      data-manage-products="<?= \Core\Auth::canAccess('Products', 'add', 'Api') ? 'true' : 'false' ?>"
      data-manage-inventory="<?= \Core\Auth::canAccess('Inventory', 'add', 'Api') ? 'true' : 'false' ?>"
      data-restock-inventory="<?= \Core\Auth::canAccess('Inventory', 'restock', 'Api') ? 'true' : 'false' ?>">
<div class="wrapper">

      <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <span class="nav-link" aria-label="Logged in account">
                    <?= htmlspecialchars($loggedInUser['username'] ?? 'Guest', ENT_QUOTES, 'UTF-8') ?>
                    <span class="badge badge-info ml-1">
                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $loggedInUser['role'] ?? 'guest')), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </span>
            </li>
            <li class="nav-item">
                <?php if ($loggedInUser): ?>
                <form action="<?= BASE_URL ?>/Auth/logout" method="POST">
                    <button class="btn btn-link nav-link" type="submit">Logout</button>
                </form>
                <?php else: ?>
                <a class="nav-link" href="<?= BASE_URL ?>/Auth">Sign In</a>
                <?php endif; ?>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="<?= BASE_URL ?>/" class="brand-link">
            <img src="<?= BASE_URL ?>/dist/img/logo.jpg" alt="" class="cafe-brand-logo">
            <span class="brand-text font-weight-light">Klasik Grounds Cafe</span>
        </a>

        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <?php if ($accountRole === 'owner'): ?>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/" class="nav-link <?= $title === 'Dashboard' ? 'active' : '' ?>"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/Products" class="nav-link <?= $title === 'Products' ? 'active' : '' ?>"><i class="nav-icon fas fa-pizza-slice"></i><p>Products</p></a></li>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/Inventory" class="nav-link <?= $title === 'Inventory' ? 'active' : '' ?>"><i class="nav-icon fas fa-warehouse"></i><p>Inventory</p></a></li>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/Categories" class="nav-link <?= $title === 'Categories' ? 'active' : '' ?>"><i class="nav-icon fas fa-th-large"></i><p>Categories</p></a></li>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/DailyExpenses" class="nav-link <?= $title === 'DailyExpenses' ? 'active' : '' ?>"><i class="nav-icon fas fa-money-bill"></i><p>Daily Expenses</p></a></li>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/POS" class="nav-link <?= $title === 'POS' ? 'active' : '' ?>"><i class="nav-icon fas fa-file-invoice-dollar"></i><p>POS</p></a></li>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/Users" class="nav-link <?= $title === 'Users' ? 'active' : '' ?>"><i class="nav-icon fas fa-users"></i><p>Users</p></a></li>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/Reports" class="nav-link <?= $title === 'Reports' ? 'active' : '' ?>"><i class="nav-icon fas fa-chart-bar"></i><p>Reports</p></a></li>
                    <?php elseif ($accountRole === 'admin'): ?>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/Users" class="nav-link <?= $title === 'Users' ? 'active' : '' ?>"><i class="nav-icon fas fa-users"></i><p>Users</p></a></li>
                    <?php elseif ($accountRole === 'cashier'): ?>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/POS" class="nav-link <?= $title === 'POS' ? 'active' : '' ?>"><i class="nav-icon fas fa-file-invoice-dollar"></i><p>POS</p></a></li>
                    <?php elseif ($accountRole === 'staff'): ?>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/Inventory" class="nav-link <?= $title === 'Inventory' ? 'active' : '' ?>"><i class="nav-icon fas fa-warehouse"></i><p>Inventory</p></a></li>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/Products" class="nav-link <?= $title === 'Products' ? 'active' : '' ?>"><i class="nav-icon fas fa-pizza-slice"></i><p>Products</p></a></li>
                    <?php elseif (in_array($accountRole, ['user', 'loyal_customer'], true) || !$loggedInUser): ?>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/Kiosk" class="nav-link <?= $title === 'Kiosk' ? 'active' : '' ?>"><i class="nav-icon fas fa-coffee"></i><p>Order</p></a></li>
                    <?php if ($accountRole === 'loyal_customer'): ?>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/Loyalty" class="nav-link <?= $title === 'Loyalty' ? 'active' : '' ?>"><i class="nav-icon fas fa-star"></i><p>Loyalty Rewards</p></a></li>
                    <?php elseif (!$loggedInUser): ?>
                    <li class="nav-item"><a href="<?= BASE_URL ?>/Auth" class="nav-link"><i class="nav-icon fas fa-sign-in-alt"></i><p>Sign In</p></a></li>
                    <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1><?= htmlspecialchars($title ?? '') ?></h1>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <?= $content ?>
            </div>
        </section>
    </div>

    <footer class="main-footer text-sm">
        <strong>Cafe Management System</strong>
    </footer>
</div>
<!-- jQuery -->
<script src="<?= BASE_URL ?>/plugins/jquery/jquery.min.js"></script>
<!-- jQuery UI 1.11.4 -->
<script src="<?= BASE_URL ?>/plugins/jquery-ui/jquery-ui.min.js"></script>
<!-- Bootstrap 4 -->
<script src="<?= BASE_URL ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="<?= BASE_URL ?>/plugins/sweetalert2/sweetalert2.min.js"></script>
<!-- Toastr -->
<script src="<?= BASE_URL ?>/plugins/toastr/toastr.min.js"></script>
<!-- daterangepicker -->
<script src="<?= BASE_URL ?>/plugins/moment/moment.min.js"></script>
<script src="<?= BASE_URL ?>/plugins/daterangepicker/daterangepicker.js"></script>
<!-- Tempusdominus Bootstrap 4 -->
<script src="<?= BASE_URL ?>/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<!-- DataTables  & Plugins -->
<script src="<?= BASE_URL ?>/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= BASE_URL ?>/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?= BASE_URL ?>/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?= BASE_URL ?>/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?= BASE_URL ?>/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?= BASE_URL ?>/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?= BASE_URL ?>/plugins/jszip/jszip.min.js"></script>
<script src="<?= BASE_URL ?>/plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?= BASE_URL ?>/plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?= BASE_URL ?>/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?= BASE_URL ?>/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?= BASE_URL ?>/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>

<script src="<?= BASE_URL ?>/dist/js/adminlte.js"></script>
<?php if (($title ?? '') === 'Dashboard'): ?>
<script src="<?= BASE_URL ?>/plugins/chart.js/Chart.min.js"></script>
<?php endif; ?>
<?php $appScriptVersion = filemtime(PUBLIC_PATH . '/js/app.js'); ?>
<script src="<?= BASE_URL ?>/js/app.js?v=<?= $appScriptVersion ?>"></script>
<?php
$pageScript = $title ?? 'Dashboard';
$pageScriptPath = PUBLIC_PATH . '/js/' . $pageScript . '.js';
$pageScriptVersion = is_file($pageScriptPath) ? filemtime($pageScriptPath) : 1;
?>
<script src="<?= BASE_URL ?>/js/<?= rawurlencode($pageScript) ?>.js?v=<?= $pageScriptVersion ?>"></script>

</body>
</html>
