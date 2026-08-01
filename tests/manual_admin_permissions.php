<?php
define('BASEPATH', true);
require __DIR__ . '/../application/config/constants.php';
require __DIR__ . '/../application/helpers/permission_helper.php';

function mock_login($level) {
    return (object) array('level' => $level);
}

$admin = mock_login(ROLE_ADMIN);
$mod = mock_login(ROLE_MOD);

$checks = array(
    array('staff.manage', $admin, true),
    array('staff.manage', $mod, false),
    array('voucher.manage', $admin, true),
    array('voucher.manage', $mod, false),
    array('product.delete_single', $admin, true),
    array('product.delete_single', $mod, false),
    array('inventory.count', $admin, true),
    array('inventory.count', $mod, true),
    array('inventory.adjust', $admin, true),
    array('inventory.adjust', $mod, false),
    array('stock.receipt', $admin, true),
    array('stock.receipt', $mod, true),
    array('stock.manage', $mod, true),
);

$fail = 0;
foreach ($checks as $c) {
    $got = admin_can($c[0], $c[1]);
    if ($got !== $c[2]) {
        echo "FAIL {$c[0]} level={$c[1]->level} expected=" . ($c[2] ? 'true' : 'false') . " got=" . ($got ? 'true' : 'false') . "\n";
        $fail++;
    }
}

$sidebar_checks = array(
    array('voucher', $mod, false),
    array('admin', $mod, false),
    array('products', $mod, true),
    array('inventory-adjust', $mod, true),
    array('inventory', $mod, true),
);

foreach ($sidebar_checks as $c) {
    $got = admin_sidebar_allowed($c[0], $c[1]);
    if ($got !== $c[2]) {
        echo "SIDEBAR FAIL {$c[0]} level={$c[1]->level} expected=" . ($c[2] ? 'true' : 'false') . " got=" . ($got ? 'true' : 'false') . "\n";
        $fail++;
    }
}

echo $fail === 0 ? "ALL PASS\n" : "$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
