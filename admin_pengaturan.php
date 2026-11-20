<?php
// Admin: pengaturan (nomor etik, kompensasi, batas aman F-4)
require __DIR__ . '/admin_inc.php';

$NUM = ['td_sistol_max', 'td_diastol_max', 'nadi_min', 'nadi_max', 'tidur_min_jam', 'kss_tunda'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ubah = [];
    foreach (rows("SELECT * FROM pengaturan") as $p) {
        if (!isset($_POST['s'][$p['kunci']])) continue;
        $v = trim((string)$_POST['s'][$p['kunci']]);
        if (in_array($p['kunci'], $NUM, true) && !is_numeric($v)) { flash("Nilai {$p['label']} harus angka.", 'err'); redirect('admin_pengaturan.php'); }
        if ($v !== $p['nilai']) { q("UPDATE pengaturan SET nilai=? WHERE kunci=?", [$v, $p['kunci']]); $ubah[] = "{$p['kunci']}: {$p['nilai']} → $v"; }
    }
    if ($ubah) audit('admin: ubah pengaturan', implode('; ', $ubah));
    flash($ubah ? 'Pengaturan disimpan.' : 'Tidak ada perubahan.');
    redirect('admin_pengaturan.php');
}
$set = rows("SELECT * FROM pengaturan ORDER BY FIELD(kunci,'no_etik','peneliti','lokasi','kendaraan','kompensasi'), kunci");
page_head('Admin — Pengaturan');
admin_nav();
?>
<form method="post" class="card" data-track>
  <?= csrf_field() ?>
  <div class="grid">
    <?php foreach ($set as $s): ?>
    <div><label class="f"><?= h($s['label']) ?></label><input type="text" name="s[<?= h($s['kunci']) ?>]" value="<?= h($s['nilai']) ?>"></div>
    <?php endforeach; ?>
  </div>
  <p class="muted">Batas aman tekanan darah dan nadi ditetapkan Ketua Penelitian sebelum pengujian. Nilai ini dipakai F-4 untuk menandai kriteria tunda secara otomatis dan dicetak pada formulir.</p>
  <p><button class="btn">Simpan pengaturan</button></p>
</form>
<?php page_foot();
