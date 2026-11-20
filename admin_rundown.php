<?php
// Admin: kelola template rundown (24 = tim manekin, 25 = skenario WBV, atau template baru)
require __DIR__ . '/admin_inc.php';

$hari = preg_replace('/[^0-9A-Za-z]/', '', (string)($_GET['hari'] ?? '24')) ?: '24';
$sesi = ($_GET['sesi'] ?? 'pagi') === 'siang' ? 'siang' : 'pagi';
$back = "admin_rundown.php?hari=$hari&sesi=$sesi";
const TIPE = ['d' => 'Mengemudi', 'j' => 'Jeda / KSS', 'r' => 'Rekam diam', 'x' => 'Lain-lain'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    if ($aksi === 'simpan') {
        $u = db()->prepare("UPDATE rundown SET urut=?, waktu=?, menit=?, kegiatan=?, keterangan=?, tipe=?, id_rekaman=?, jalur_v=? WHERE id=? AND hari=? AND sesi=?");
        $n = 0;
        foreach (($_POST['r'] ?? []) as $id => $r) {
            if (!isset(TIPE[$r['tipe'] ?? ''])) continue;
            $u->execute([(int)$r['urut'], trim((string)$r['waktu']), intOrNull($r['menit'] ?? null), trim((string)$r['kegiatan']), ($r['keterangan'] ?? '') ?: null,
                         $r['tipe'], ($r['id_rekaman'] ?? '') ?: null, ($r['jalur_v'] ?? '') ?: null, (int)$id, $hari, $sesi]);
            $n += $u->rowCount();
        }
        audit('admin: ubah rundown', "$hari $sesi ($n baris)");
        flash("Rundown tersimpan ($n baris diubah).");
    } elseif ($aksi === 'tambah') {
        $urut = (int)(row("SELECT COALESCE(MAX(urut),0)+1 n FROM rundown WHERE hari=? AND sesi=?", [$hari, $sesi])['n']);
        q("INSERT INTO rundown (hari, sesi, urut, waktu, menit, kegiatan, tipe) VALUES (?,?,?,?,?,?,?)",
          [$hari, $sesi, $urut, trim((string)post('waktu', '')), intOrNull(post('menit')), trim((string)post('kegiatan', '(baru)')), isset(TIPE[post('tipe')]) ? post('tipe') : 'd']);
        audit('admin: tambah baris rundown', "$hari $sesi");
        flash('Baris ditambahkan. Gunakan "Sinkronkan" agar baris ini muncul di kunjungan yang sudah dibuat.');
    } elseif ($aksi === 'hapus') {
        $id = (int)post('id');
        $isi = (int)row("SELECT COUNT(*) n FROM log_baris WHERE rundown_id=? AND (jam_mulai IS NOT NULL OR jam_selesai IS NOT NULL OR kss IS NOT NULL OR catatan IS NOT NULL OR v_aktual IS NOT NULL)", [$id])['n'];
        if ($isi) flash("Baris tidak dapat dihapus karena sudah berisi data log pada $isi kunjungan.", 'err');
        else {
            q("DELETE FROM log_baris WHERE rundown_id=?", [$id]);
            q("DELETE FROM rundown WHERE id=? AND hari=?", [$id, $hari]);
            audit('admin: hapus baris rundown', "$hari $sesi id $id");
            flash('Baris dihapus.');
        }
    } elseif ($aksi === 'sinkron') {
        $n = q("INSERT INTO log_baris (kunjungan_id, rundown_id)
                SELECT k.id, r.id FROM kunjungan k JOIN rundown r ON r.hari=k.template_hari
                LEFT JOIN log_baris l ON l.kunjungan_id=k.id AND l.rundown_id=r.id
                WHERE k.template_hari=? AND l.id IS NULL", [$hari])->rowCount();
        audit('admin: sinkron rundown', "$hari ($n baris)");
        flash("$n baris log baru ditambahkan ke kunjungan bertemplate $hari.");
    }
    redirect($back);
}

$templates = rows("SELECT hari, COUNT(*) n, (SELECT COUNT(*) FROM kunjungan k WHERE k.template_hari=r.hari) nk FROM rundown r GROUP BY hari ORDER BY hari");
$rows = rows("SELECT r.*, (SELECT COUNT(*) FROM log_baris l WHERE l.rundown_id=r.id AND (l.jam_mulai IS NOT NULL OR l.kss IS NOT NULL)) dipakai
              FROM rundown r WHERE hari=? AND sesi=? ORDER BY urut", [$hari, $sesi]);
page_head('Admin — Rundown');
admin_nav();
?>
<div class="card">
  <p>Template: <?php foreach ($templates as $t) echo "<a class='btn sm " . ($t['hari'] === $hari ? '' : 'sec') . "' href='admin_rundown.php?hari={$t['hari']}&sesi=$sesi'>" . h($t['hari']) . " · {$t['n']} baris · {$t['nk']} kunjungan</a> "; ?></p>
  <p>Sesi: <a class="btn sm <?= $sesi === 'pagi' ? '' : 'sec' ?>" href="admin_rundown.php?hari=<?= h($hari) ?>&sesi=pagi">Pagi</a> <a class="btn sm <?= $sesi === 'siang' ? '' : 'sec' ?>" href="admin_rundown.php?hari=<?= h($hari) ?>&sesi=siang">Siang</a></p>
  <p class="muted">Perubahan teks/jam langsung berlaku di kunjungan yang memakai template ini. <code>{SUBJ}</code> pada ID rekaman diganti kode subjek. Untuk template baru, tambahkan baris dengan kode hari baru lewat alamat <code>admin_rundown.php?hari=26</code>.</p>
</div>

<form method="post" data-track>
  <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan">
  <div class="tbl-wrap"><table class="log">
    <tr><th>Urut</th><th>Waktu</th><th>Menit</th><th>Kegiatan</th><th>Keterangan</th><th>Tipe</th><th>ID rekaman</th><th>Jalur · v</th><th></th></tr>
    <?php foreach ($rows as $r): $n = "r[{$r['id']}]"; ?>
    <tr class="t-<?= h($r['tipe']) ?>">
      <td><input type="number" name="<?= $n ?>[urut]" value="<?= (int)$r['urut'] ?>" style="width:4rem"></td>
      <td><input type="text" name="<?= $n ?>[waktu]" value="<?= h($r['waktu']) ?>" style="width:8rem"></td>
      <td><input type="number" name="<?= $n ?>[menit]" value="<?= h($r['menit']) ?>" style="width:4.5rem"></td>
      <td><input type="text" name="<?= $n ?>[kegiatan]" value="<?= h($r['kegiatan']) ?>" style="min-width:16rem"></td>
      <td><input type="text" name="<?= $n ?>[keterangan]" value="<?= h($r['keterangan']) ?>"></td>
      <td><select name="<?= $n ?>[tipe]"><?php foreach (TIPE as $k => $l) echo "<option value='$k'" . ($r['tipe'] === $k ? ' selected' : '') . ">$l</option>"; ?></select></td>
      <td><input type="text" name="<?= $n ?>[id_rekaman]" value="<?= h($r['id_rekaman']) ?>" style="width:11rem"></td>
      <td><input type="text" name="<?= $n ?>[jalur_v]" value="<?= h($r['jalur_v']) ?>" style="width:6rem"></td>
      <td><?= $r['dipakai'] ? "<span class='pill' title='sudah berisi data log'>terpakai</span>" : '' ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <div class="sticky"><button class="btn">Simpan rundown</button></div>
</form>

<div class="grid" style="margin-top:1rem">
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="aksi" value="tambah">
    <h2 style="margin-top:0">Tambah baris (<?= h($hari) ?> · <?= h($sesi) ?>)</h2>
    <label class="f">Waktu</label><input type="text" name="waktu" placeholder="15.45–15.50" required>
    <label class="f">Menit</label><input type="number" name="menit">
    <label class="f">Kegiatan</label><input type="text" name="kegiatan" required>
    <label class="f">Tipe</label><select name="tipe"><?php foreach (TIPE as $k => $l) echo "<option value='$k'>$l</option>"; ?></select>
    <p><button class="btn sec">Tambah</button></p>
  </form>
  <div class="card"><h2 style="margin-top:0">Hapus / sinkronkan</h2>
    <form method="post" style="display:flex;gap:.4rem"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus">
      <select name="id"><?php foreach ($rows as $r) if (!$r['dipakai']) echo "<option value='{$r['id']}'>" . h($r['urut'] . '. ' . $r['waktu'] . ' ' . mb_substr($r['kegiatan'], 0, 40)) . "</option>"; ?></select>
      <button class="btn sm bad" onclick="return confirm('Hapus baris rundown ini?')">Hapus</button></form>
    <p class="muted">Hanya baris yang belum berisi data log yang dapat dihapus.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="sinkron"><button class="btn sm sec">Sinkronkan baris baru ke kunjungan bertemplate <?= h($hari) ?></button></form>
  </div>
</div>
<?php page_foot();
