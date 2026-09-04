<?php
require_once __DIR__ . '/config/db.php';
if (!$db_connected) {
    include __DIR__ . '/db_setup.php';
    exit;
}

$app_role = 'both';
$configPath = __DIR__ . '/config/db_config.json';
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
    $app_role = $config['app_role'] ?? 'both';
}

require_once __DIR__ . '/config/auth_check.php';
$crx_token = get_api_secret();

require_once __DIR__ . '/views/layout/head.php';
?>
<body class="role-<?php echo htmlspecialchars($app_role); ?>">

<?php require_once __DIR__ . '/views/layout/sidebar.php'; ?>

<main class="app-main">
<?php require_once __DIR__ . '/views/layout/header.php'; ?>
<?php
$tabs = ['tab-builder', 'tab-patients', 'tab-queue', 'tab-billing',
         'tab-medicines', 'tab-history', 'tab-templates', 'tab-settings'];
foreach ($tabs as $tab) {
    require_once __DIR__ . "/views/tabs/{$tab}.php";
}
?>
</main>

<?php
$modals = ['modal-print', 'modal-patient', 'modal-medicine',
           'modal-patient-history', 'modal-pediatric-calc', 'modal-auto-update'];
foreach ($modals as $modal) {
    require_once __DIR__ . "/views/modals/{$modal}.php";
}
?>

<script>window.__crxToken = '<?php echo htmlspecialchars($crx_token, ENT_QUOTES); ?>';</script>
<script src="assets/js/i18n.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
