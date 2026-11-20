<?php
// Admin: unduh cadangan database (.sql) langsung dari aplikasi
require __DIR__ . '/admin_inc.php';

if (($_GET['unduh'] ?? '') === '1') {
    audit('admin: unduh cadangan', '');
    $tabel = ['users', 'pengaturan', 'subjek', 'rundown', 'kunjungan', 'kelaikan', 'log_baris', 'henti', 'pasca', 'aktivitas'];
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="cadangan_wbv_' . date('Ymd_His') . '.sql"');
    echo "-- Cadangan WBV Lapangan v" . APP_VERSI . " · " . date('Y-m-d H:i:s') . "\n";
    echo "-- Pulihkan: phpMyAdmin > wbv_lapangan > Import berkas ini.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($tabel as $t) {
        $c = row("SHOW CREATE TABLE `$t`");
        echo "DROP TABLE IF EXISTS `$t`;\n" . $c['Create Table'] . ";\n\n";
        $st = db()->query("SELECT * FROM `$t`");
        while ($r = $st->fetch()) {
            $v = array_map(fn($x) => $x === null ? 'NULL' : db()->quote((string)$x), array_values($r));
            echo "INSERT INTO `$t` (`" . implode('`,`', array_keys($r)) . "`) VALUES (" . implode(',', $v) . ");\n";
        }
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}
$n = rows("SELECT 'subjek' t, COUNT(*) n FROM subjek UNION ALL SELECT 'kunjungan', COUNT(*) FROM kunjungan UNION ALL SELECT 'log_baris berisi', COUNT(*) FROM log_baris WHERE jam_mulai IS NOT NULL OR kss IS NOT NULL UNION ALL SELECT 'aktivitas', COUNT(*) FROM aktivitas");
page_head('Admin — Cadangan');
admin_nav();
?>
<div class="card">
  <p>Unduh seluruh isi database sebagai berkas <code>.sql</code>. Lakukan setiap selesai satu sesi, lalu simpan di media terenkripsi sesuai protokol etik.</p>
  <table style="max-width:400px"><?php foreach ($n as $x) echo "<tr><td>" . h($x['t']) . "</td><td>" . (int)$x['n'] . "</td></tr>"; ?></table>
  <p><a class="btn" href="admin_backup.php?unduh=1">⬇ Unduh cadangan sekarang</a></p>
  <p class="muted">Pemulihan: phpMyAdmin → database <code>wbv_lapangan</code> → Import berkas cadangan. Pemulihan menimpa seluruh data saat ini.</p>
</div>
<?php page_foot();
