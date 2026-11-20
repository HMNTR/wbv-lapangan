<?php
// Bersama untuk halaman admin
require_once __DIR__ . '/inc.php';
require_role('admin');
csrf_check();
function admin_nav(): void {
    $cur = basename($_SERVER['SCRIPT_NAME']);
    $m = ['admin.php' => 'Pengguna', 'admin_rundown.php' => 'Rundown', 'admin_pengaturan.php' => 'Pengaturan', 'admin_aktivitas.php' => 'Jejak aktivitas', 'admin_backup.php' => 'Cadangan'];
    echo "<div class='admin-nav'>";
    foreach ($m as $f => $l) echo "<a class='btn sm " . ($f === $cur ? '' : 'sec') . "' href='$f'>$l</a>";
    echo "</div>";
}
