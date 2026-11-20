<?php
require __DIR__ . '/inc.php';
require_editor();
csrf_check();

$subjek = rows("SELECT id, kode FROM subjek ORDER BY kode");
$TEMPLATE = ['24' => 'Skenario tim manekin (U1–U7)', '25' => 'Skenario WBV (D1–D4)'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid = (int)post('subjek_id'); $tpl = (string)post('template_hari');
    if (!isset($TEMPLATE[$tpl]) || !$sid) { flash('Lengkapi subjek dan template rundown.', 'err'); redirect('kunjungan_baru.php'); }
    $ke = (int)(row("SELECT COALESCE(MAX(ke),0)+1 n FROM kunjungan WHERE subjek_id=?", [$sid])['n']);
    q("INSERT INTO kunjungan (subjek_id, ke, tanggal, template_hari, skenario, no_kendaraan) VALUES (?,?,?,?,?,?)",
      [$sid, $ke, post('tanggal'), $tpl, $TEMPLATE[$tpl], post('no_kendaraan')]);
    $kid = (int)db()->lastInsertId();
    // siapkan baris log dari template rundown
    q("INSERT INTO log_baris (kunjungan_id, rundown_id) SELECT ?, id FROM rundown WHERE hari=?", [$kid, $tpl]);
    audit('buat kunjungan', "kunjungan ke-$ke, template $tpl", $kid);
    flash("Kunjungan ke-$ke dibuat.");
    redirect("kunjungan.php?id=$kid");
}

page_head('Kunjungan Baru');
if (!$subjek) { echo "<div class='alert warn'>Belum ada subjek. <a href='subjek.php'>Tambah subjek</a> terlebih dahulu.</div>"; page_foot(); exit; }
?>
<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="grid">
    <div><label class="f">Subjek</label><select name="subjek_id"><?php foreach ($subjek as $s) echo "<option value='{$s['id']}'>" . h($s['kode']) . "</option>"; ?></select></div>
    <div><label class="f">Tanggal</label><input type="date" name="tanggal" value="2026-11-24" required></div>
    <div><label class="f">Template rundown</label>
      <select name="template_hari"><option value="24">24 Nov — skenario tim manekin</option><option value="25">25 Nov — skenario WBV (D1–D4)</option></select></div>
    <div><label class="f">No. kendaraan MV3 Komando</label><input type="text" name="no_kendaraan"></div>
  </div>
  <p><button class="btn">Buat kunjungan</button></p>
</form>
<?php page_foot();
