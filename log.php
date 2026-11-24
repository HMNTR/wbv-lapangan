<?php
// F-6 Log mengemudi — satu baris per baris rundown; jam rundown persis seperti dokumen
require __DIR__ . '/inc.php';
require_login();
csrf_check();

$kid = (int)($_GET['k'] ?? 0);
$sesi = ($_GET['sesi'] ?? 'pagi') === 'siang' ? 'siang' : 'pagi';
$k = kunjungan_or_404($kid);
const OBS = ['Y' => 'menguap', 'K' => 'kedip lama/mata terpejam', 'A' => 'kepala terangguk', 'L' => 'penyimpangan lajur', 'S' => 'gangguan sensor', 'T' => 'keluhan tubuh'];
const ALASAN = ['KSS ≥ 7', 'tanda microsleep', 'permintaan subjek', 'instruksi petugas keselamatan', 'VDV mendekati batas', 'cuaca/lintasan', 'perangkat'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can_edit()) { http_response_code(403); exit('Tidak berhak mengubah log.'); }
    if (post('aksi') === 'henti') {
        $al = $_POST['alasan'] ?? [];
        $al = array_values(array_intersect(ALASAN, is_array($al) ? $al : []));
        if (!$al || !post('jam')) { flash('Isi jam dan minimal satu alasan penghentian.', 'err'); redirect("log.php?k=$kid&sesi=$sesi"); }
        q("INSERT INTO henti (kunjungan_id, jam, alasan, tindakan, catatan, created_by) VALUES (?,?,?,?,?,?)",
          [$kid, post('jam'), implode(', ', $al), post('tindakan') === 'sesi_diakhiri' ? 'sesi_diakhiri' : 'istirahat_lanjut', post('catatan_henti'), uid()]);
        audit('catat penghentian', implode(', ', $al), $kid);
        if (post('tindakan') === 'sesi_diakhiri') q("UPDATE kunjungan SET status='berhenti' WHERE id=?", [$kid]);
        flash('Penghentian dicatat.');
        redirect("log.php?k=$kid&sesi=$sesi");
    }
    $upd = db()->prepare("UPDATE log_baris SET updated_by=IF(
        NOT (jam_mulai <=> ?) OR NOT (jam_selesai <=> ?) OR NOT (kss <=> ?) OR NOT (v_aktual <=> ?) OR NOT (pass <=> ?) OR NOT (kode_obs <=> ?) OR NOT (catatan <=> ?), ?, updated_by),
        jam_mulai=?, jam_selesai=?, kss=?, v_aktual=?, pass=?, kode_obs=?, catatan=?
        WHERE id=? AND kunjungan_id=?");
    $n = 0;
    foreach (($_POST['b'] ?? []) as $lid => $b) {
        $obs = isset($b['obs']) && is_array($b['obs']) ? implode('', array_intersect(array_keys(OBS), $b['obs'])) : null;
        $kss = intOrNull($b['kss'] ?? null);
        if ($kss !== null && ($kss < 1 || $kss > 9)) $kss = null;
        $vals = [($b['mulai'] ?? '') ? $b['mulai'] . (strlen($b['mulai']) === 5 ? ':00' : '') : null, ($b['selesai'] ?? '') ? $b['selesai'] . (strlen($b['selesai']) === 5 ? ':00' : '') : null,
                 $kss, ($b['v'] ?? '') ?: null, ($b['pass'] ?? '') ?: null, $obs ?: null, ($b['cat'] ?? '') ?: null];
        $upd->execute([...$vals, uid(), ...$vals, (int)$lid, $kid]);
        $n += $upd->rowCount();
    }
    if ($n) audit('simpan F-6 ' . $sesi, "$n baris", $kid);
    flash("Log tersimpan ($n baris diperbarui).");
    redirect("log.php?k=$kid&sesi=$sesi");
}

jw_pastikan_kolom();
$rows = rows("SELECT l.*, r.waktu, r.menit, r.kegiatan, r.keterangan, r.tipe, r.id_rekaman, r.jalur_v
              FROM log_baris l JOIN rundown r ON r.id=l.rundown_id
              WHERE l.kunjungan_id=? AND r.sesi=? ORDER BY r.urut", [$kid, $sesi]);
$henti = rows("SELECT * FROM henti WHERE kunjungan_id=? ORDER BY jam", [$kid]);
// template bergaya rundown tim manekin (kolom Keterangan) vs skenario WBV (kolom Jalur · v)
$is24 = (bool)row("SELECT 1 FROM rundown WHERE hari=? AND keterangan IS NOT NULL AND keterangan<>'' LIMIT 1", [$k['template_hari']]);
$kssHigh = array_filter($rows, fn($r) => $r['kss'] !== null && (int)$r['kss'] >= 7);

page_head('F-6 Log Mengemudi — ' . ($sesi === 'pagi' ? 'Pagi' : 'Siang'), $k);
?>
<p class="muted"><?= $is24 ? 'Jam sesuai rundown tim manekin 24 November. KSS ditanyakan lisan di setiap jeda (baris biru).' : 'Jam sesuai rundown skenario WBV 25 November. KSS lisan tiap 10 menit tanpa berhenti; baris biru = KSS saat jeda.' ?>
 Kode pengamatan: <?php foreach (OBS as $c => $l) echo "<b>$c</b> $l · "; ?></p>
<?php
// saran geser jadwal: baris terakhir yang jam mulai aktualnya meleset >= 3 menit dari jadwal
$saran = null;
foreach ($rows as $r) {
    if (!$r['jam_mulai'] || jw_jenis($r) === 'titik') continue;
    $jdw = jw_mulai_sekarang($r); $akt = jw_dbmin($r['jam_mulai']);
    if ($jdw !== null && abs($akt - $jdw) >= 3) $saran = [$r, $akt - $jdw, $akt]; elseif ($jdw !== null) $saran = null;
}
?>
<p><?php if (can_edit()): ?><a class="btn sm" href="jadwal.php?k=<?= $kid ?>&sesi=<?= $sesi ?>">⏱ Atur / geser jadwal</a><?php endif; ?>
<?php if (array_filter($rows, fn($r) => !empty($r['jadwal_mulai']))): ?> <span class="pill warn">jadwal sesi ini sudah digeser dari rundown asli</span><?php endif; ?></p>
<?php if ($saran && can_edit()): ?><div class="alert warn">Jam mulai aktual <b><?= h($saran[0]['kegiatan'] ?: $saran[0]['waktu']) ?></b> = <?= h(jw_fmt($saran[2])) ?>, selisih <?= ($saran[1] > 0 ? '+' : '') . $saran[1] ?> menit dari jadwal. <a class="btn sm" href="jadwal.php?k=<?= $kid ?>&sesi=<?= $sesi ?>&anchor=<?= (int)$saran[0]['id'] ?>&jam=<?= h(str_replace('.', ':', jw_fmt($saran[2]))) ?>">Geser jadwal berikutnya mengikuti jam ini</a></div><?php endif; ?>
<?php if ($kssHigh): ?><div class="alert bad">KSS ≥ 7 tercatat pada <?= count($kssHigh) ?> baris. Kendaraan harus dihentikan dan dicatat di "Penghentian lebih awal".</div><?php endif; ?>
<?php if (!$rows): ?><div class="alert warn">Tidak ada baris rundown untuk sesi ini.</div><?php endif; ?>

<form method="post" data-track>
  <?= csrf_field() ?>
  <fieldset <?= can_edit() ? '' : 'disabled' ?>>
  <div class="tbl-wrap"><table class="log">
    <tr><th>Waktu (jadwal)</th><th>Menit</th><th>Kegiatan</th><th><?= $is24 ? 'Keterangan' : 'Jalur · v' ?></th><th>ID rekaman WBV</th>
        <th>Jam mulai</th><th>Jam selesai</th><th>KSS</th><th><?= $is24 ? 'v aktual' : 'v aktual / pass' ?></th><th>Kode</th><th>Catatan</th></tr>
    <?php foreach ($rows as $r): $lid = (int)$r['id']; $nm = "b[$lid]";
      $idr = str_replace('{SUBJ}', $k['kode'], (string)$r['id_rekaman']);
      $cls = 't-' . $r['tipe'] . (($r['kss'] !== null && (int)$r['kss'] >= 7) ? ' kss-high' : ''); ?>
    <tr class="<?= $cls ?>">
      <td><?= jw_berubah($r) ? '<b>' . h(jw_label($r)) . '</b><br><s class="muted">' . h($r['waktu']) . '</s>' : h(jw_label($r)) ?></td><td><?= h($r['menit_jadwal'] ?? $r['menit']) ?></td><td><?= h($r['kegiatan']) ?></td>
      <td><?= h($is24 ? $r['keterangan'] : $r['jalur_v']) ?></td><td class="muted"><?= h($idr) ?></td>
      <td class="tm"><input type="time" name="<?= $nm ?>[mulai]" value="<?= h(substr((string)$r['jam_mulai'], 0, 5)) ?>"><button type="button" class="btn sm sec now" data-now="<?= $nm ?>[mulai]">⏱</button></td>
      <td class="tm"><input type="time" name="<?= $nm ?>[selesai]" value="<?= h(substr((string)$r['jam_selesai'], 0, 5)) ?>"><button type="button" class="btn sm sec now" data-now="<?= $nm ?>[selesai]">⏱</button></td>
      <td><?= kss_select("{$nm}[kss]", $r['kss']) ?></td>
      <td><input type="text" name="<?= $nm ?>[v]" value="<?= h($r['v_aktual']) ?>" size="6" placeholder="km/jam"><?php if (!$is24): ?><input type="text" name="<?= $nm ?>[pass]" value="<?= h($r['pass']) ?>" size="4" placeholder="pass"><?php endif; ?></td>
      <td class="obs"><div class="obsg"><?php foreach (array_keys(OBS) as $c) { $on = str_contains((string)$r['kode_obs'], $c) ? 'checked' : ''; echo "<label title='" . OBS[$c] . "'><input type='checkbox' name='{$nm}[obs][]' value='$c' $on>$c</label>"; } ?></div></td>
      <td><input type="text" name="<?= $nm ?>[cat]" value="<?= h($r['catatan']) ?>"></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php if (can_edit()): ?>  <div class="sticky"><button class="btn">Simpan log</button> <a class="btn sec" href="log.php?k=<?= $kid ?>&sesi=<?= $sesi === 'pagi' ? 'siang' : 'pagi' ?>">Ke sesi <?= $sesi === 'pagi' ? 'siang' : 'pagi' ?> →</a></div><?php endif; ?>
  </fieldset>
</form>

<?= panel_persetujuan($kid, "f6_$sesi") ?>
<form method="post" class="card" style="margin-top:1rem">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="henti">
  <fieldset <?= can_edit() ? '' : 'disabled' ?> style="border:0;padding:0;margin:0">
  <h2 style="margin-top:0">Penghentian lebih awal</h2>
  <p class="muted">KSS ≥ 7 atau tanda microsleep: kendaraan dihentikan dengan aman — berlaku juga pada skenario tim manekin.</p>
  <div class="grid">
    <div><label class="f">Jam</label><input type="time" name="jam"> <button type="button" class="btn sm sec now" data-now="jam">sekarang</button></div>
    <div><label class="f">Alasan</label><?php foreach (ALASAN as $a) echo "<label class='chk'><input type='checkbox' name='alasan[]' value='" . h($a) . "'> " . h($a) . "</label>"; ?></div>
    <div><label class="f">Tindakan</label><?= opt('tindakan', 'istirahat_lanjut', ['istirahat_lanjut' => 'Istirahat ≥ 20′, lanjut bila KSS ≤ 5', 'sesi_diakhiri' => 'Sesi diakhiri']) ?></div>
    <div><label class="f">Catatan</label><input type="text" name="catatan_henti"></div>
  </div>
  <?php if (can_edit()): ?><p><button class="btn bad">Catat penghentian</button></p><?php endif; ?>
  </fieldset>
  <?php if ($henti): ?><table><tr><th>Jam</th><th>Alasan</th><th>Tindakan</th><th>Catatan</th></tr>
  <?php foreach ($henti as $x) echo "<tr><td>" . h(substr($x['jam'], 0, 5)) . "</td><td>" . h($x['alasan']) . "</td><td>" . ($x['tindakan'] === 'sesi_diakhiri' ? 'Sesi diakhiri' : 'Istirahat, lanjut') . "</td><td>" . h($x['catatan']) . "</td></tr>"; ?></table><?php endif; ?>
</form>
<?php page_foot();
