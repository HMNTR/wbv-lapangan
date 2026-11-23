<?php
require __DIR__ . '/inc.php';
require_editor();
csrf_check();

$subjek = rows("SELECT id, kode FROM subjek ORDER BY kode");
$LABEL = ['24' => 'Skenario tim manekin (U1–U7)', '25' => 'Skenario WBV (D1–D4)', '21' => 'Simulasi — salinan rundown 24 (tim manekin)', '25L' => 'Skenario WBV versi lama (sebelum revisi rundown)'];
$TEMPLATE = [];
foreach (rows("SELECT DISTINCT hari FROM rundown ORDER BY hari") as $r) $TEMPLATE[$r['hari']] = $LABEL[$r['hari']] ?? ('Template ' . $r['hari']);

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
      <select name="template_hari"><?php foreach ($TEMPLATE as $h => $l) echo "<option value='" . h($h) . "'" . ($h === '24' ? ' selected' : '') . ">" . h($h) . " — " . h($l) . "</option>"; ?></select></div>
    <div><label class="f">No. kendaraan MV3 Komando</label><input type="text" name="no_kendaraan"></div>
  </div>
  <p><button class="btn">Buat kunjungan</button></p>
</form>
<?php page_foot();
