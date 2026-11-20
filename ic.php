<?php
// Lembar Informasi + Persetujuan Setelah Penjelasan (Informed Consent)
// Alur: setiap bagian informasi dibaca/dijelaskan dan diceklist -> baru formulir persetujuan dapat diisi.
require __DIR__ . '/inc.php';
require_editor();               // memuat nama subjek: hanya Admin, Ketua, Peneliti
csrf_check();

$sid = (int)($_GET['s'] ?? 0);
$s = row("SELECT * FROM subjek WHERE id=?", [$sid]);
if (!$s) { http_response_code(404); exit('Subjek tidak ditemukan.'); }
$T = require __DIR__ . '/ic_teks.php';
$versi = sha1(serialize($T));
$nBag = count($T['bagian']);
$ic = row("SELECT * FROM informed_consent WHERE subjek_id=?", [$sid]);
const SAKSI_NAMA = 'Giva Andriana Mutiara, S.T., M.T., Ph.D';
const SAKSI_JAB = 'Ketua STAS-RG';

function ttd_valid(?string $d): bool {
    return is_string($d) && strlen($d) < 400000 && preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $d) && strlen($d) > 1500;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    if ($aksi === 'tarik' && $ic && !$ic['ditarik_at']) {
        q("UPDATE informed_consent SET ditarik_at=NOW(), ditarik_catatan=?, ditarik_hapus_data=? WHERE id=?", [post('catatan_tarik'), post('hapus_data') ? 1 : 0, $ic['id']]);
        audit('IC: persetujuan ditarik', $s['kode'] . (post('hapus_data') ? ' — minta data dihapus' : ''));
        flash('Penarikan persetujuan dicatat.' . (post('hapus_data') ? ' Subjek meminta datanya dihapus: hapus berkas rekaman dan isian terkait.' : ''));
        redirect("ic.php?s=$sid");
    }
    if ($aksi === 'simpan' && !$ic) {
        $err = [];
        $baca = array_map('intval', array_keys((array)($_POST['baca'] ?? [])));
        if (count(array_unique(array_intersect($baca, range(0, $nBag - 1)))) !== $nBag) $err[] = 'semua bagian lembar informasi harus diceklist sudah dibaca/dijelaskan';
        $p = (array)($_POST['paham'] ?? []);
        if (count(array_intersect(array_keys($p), [0, 1, 2])) !== 3) $err[] = 'pernyataan 1–3 harus dicentang';
        $tglP = post('tgl_penjelasan');
        $tsP = $tglP ? strtotime($tglP) : false;
        if (!$tsP) $err[] = 'tanggal & jam penjelasan wajib diisi';
        elseif (time() - $tsP < 24 * 3600) $err[] = 'masa pertimbangan minimal 24 jam sejak penjelasan belum terpenuhi (protokol etik)';
        if (!trim((string)post('nama', ''))) $err[] = 'nama subjek wajib diisi';
        if (!in_array(post('video'), ['bersedia', 'tidak'], true)) $err[] = 'pilihan perekaman video wajib dipilih';
        if (!in_array(post('dihubungi'), ['bersedia', 'tidak'], true)) $err[] = 'pilihan dihubungi kembali wajib dipilih';
        if (!in_array(post('setuju'), ['1', '0'], true)) $err[] = 'pernyataan SETUJU/TIDAK SETUJU wajib dipilih';
        if (!trim((string)post('saksi_nama', '')) || !trim((string)post('saksi_jabatan', ''))) $err[] = 'nama dan jabatan saksi wajib diisi';
        foreach (['ttd_subjek' => 'subjek', 'ttd_peneliti' => 'peneliti', 'ttd_saksi' => 'saksi'] as $f => $l) if (!ttd_valid($_POST[$f] ?? null)) $err[] = "tanda tangan $l belum dibuat";
        if ($err) {
            $_SESSION['ic_draft'] = array_diff_key($_POST, array_flip(['csrf', 'ttd_subjek', 'ttd_peneliti', 'ttd_saksi']));
            flash('Belum dapat disimpan: ' . implode('; ', $err) . '.', 'err');
            redirect("ic.php?s=$sid");
        }
        $u = row("SELECT nama, jabatan FROM users WHERE id=?", [uid()]);
        q("INSERT INTO informed_consent (subjek_id, versi_teks, bagian_dibaca, tgl_penjelasan, nama, usia, alamat, hp, video, dihubungi, setuju, tempat,
             ttd_subjek, peneliti_user_id, peneliti_nama, peneliti_jabatan, ttd_peneliti, saksi_nama, saksi_jabatan, ttd_saksi, ditandatangani_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
          [$sid, $versi, implode(',', range(1, $nBag)), date('Y-m-d H:i:s', $tsP), trim((string)post('nama')), intOrNull(post('usia')), post('alamat'), post('hp'),
           post('video'), post('dihubungi'), (int)post('setuju'), trim((string)post('tempat', 'Bandung')),
           $_POST['ttd_subjek'], uid(), $u['nama'], $u['jabatan'], $_POST['ttd_peneliti'], trim((string)post('saksi_nama')), trim((string)post('saksi_jabatan')), $_POST['ttd_saksi']]);
        if (post('setuju') === '1') q("UPDATE subjek SET ic_tanggal=CURDATE(), updated_by=? WHERE id=?", [uid(), $sid]);
        unset($_SESSION['ic_draft']);
        audit('IC: ' . (post('setuju') === '1' ? 'subjek SETUJU' : 'subjek TIDAK SETUJU'), $s['kode']);
        flash(post('setuju') === '1' ? 'Persetujuan tersimpan. Cetak rangkap dua: satu untuk subjek, satu untuk peneliti.' : 'Tercatat: subjek TIDAK SETUJU. Subjek tidak diikutkan dalam pengambilan data.');
        redirect("ic.php?s=$sid");
    }
    redirect("ic.php?s=$sid");
}

$d = $_SESSION['ic_draft'] ?? []; unset($_SESSION['ic_draft']);
$dv = fn($k, $def = '') => h($d[$k] ?? $def);
page_head('Lembar Informasi & Informed Consent — ' . $s['kode']);

// ---------- sudah ada persetujuan: tampilkan ringkas ----------
if ($ic):
    $img = fn($x) => "<img class='ttdimg' alt='tanda tangan' src='" . h($x) . "'>";
?>
<?php if ($ic['ditarik_at']): ?><div class="alert bad">Persetujuan DITARIK pada <?= h($ic['ditarik_at']) ?><?= $ic['ditarik_hapus_data'] ? ' — subjek meminta datanya dihapus' : '' ?>. <?= h($ic['ditarik_catatan']) ?></div>
<?php elseif (!$ic['setuju']): ?><div class="alert bad">Subjek menyatakan TIDAK SETUJU. Subjek tidak diikutkan.</div>
<?php else: ?><div class="alert ok">Subjek menyatakan SETUJU pada <?= h($ic['ditandatangani_at']) ?>.</div><?php endif; ?>
<?php if ($ic['versi_teks'] !== $versi): ?><div class="alert warn">Teks lembar informasi di aplikasi telah berubah sejak persetujuan ini ditandatangani.</div><?php endif; ?>
<div class="card">
  <table>
    <tr><th>Kode subjek</th><td><?= h($s['kode']) ?></td><th>Nama</th><td><?= h($ic['nama']) ?></td></tr>
    <tr><th>Usia</th><td><?= h($ic['usia']) ?></td><th>No. HP</th><td><?= h($ic['hp']) ?></td></tr>
    <tr><th>Alamat</th><td colspan="3"><?= h($ic['alamat']) ?></td></tr>
    <tr><th>Penjelasan diberikan</th><td><?= h($ic['tgl_penjelasan']) ?></td><th>Ditandatangani</th><td><?= h($ic['tempat']) ?>, <?= h($ic['ditandatangani_at']) ?></td></tr>
    <tr><th>Bagian informasi dibaca</th><td><?= count(explode(',', $ic['bagian_dibaca'])) ?> dari <?= $nBag ?> ✓</td><th>Video wajah / dihubungi kembali</th><td><?= h($ic['video']) ?> / <?= h($ic['dihubungi']) ?></td></tr>
  </table>
  <div class="ttdgrid">
    <div>Subjek Penelitian<?= $img($ic['ttd_subjek']) ?><b><?= h($ic['nama']) ?></b></div>
    <div>Peneliti<?= $img($ic['ttd_peneliti']) ?><b><?= h($ic['peneliti_nama']) ?></b><br><span class="muted"><?= h($ic['peneliti_jabatan']) ?></span></div>
    <div>Saksi (di luar rantai komando)<?= $img($ic['ttd_saksi']) ?><b><?= h($ic['saksi_nama']) ?></b><br><span class="muted"><?= h($ic['saksi_jabatan']) ?></span></div>
  </div>
  <p><a class="btn" target="_blank" href="cetak.php?s=<?= $sid ?>&f=ic">🖨 Cetak Lembar Informasi & IC</a> <a class="btn sec" href="kunjungan_list.php">Kembali</a></p>
</div>
<?php if (!$ic['ditarik_at'] && $ic['setuju']): ?>
<form method="post" class="card" onsubmit="return confirm('Catat penarikan persetujuan subjek?')">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="tarik">
  <h2 style="margin-top:0">Penarikan persetujuan</h2>
  <p class="muted">Subjek berhak menarik diri kapan saja tanpa akibat apa pun (butir 19).</p>
  <label class="f">Catatan (alasan tidak wajib ditanyakan)</label><input type="text" name="catatan_tarik">
  <p><label class="chk"><input type="checkbox" name="hapus_data" value="1"> Subjek meminta datanya dihapus</label></p>
  <button class="btn bad sm">Catat penarikan</button>
</form>
<?php endif; ?>
<?php page_foot(); exit; endif; ?>

<!-- ---------- belum ada persetujuan: langkah 1 baca, langkah 2 setuju ---------- -->
<form method="post" id="icform" data-track>
  <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan">
  <div class="card">
    <h2 style="margin-top:0">Langkah 1 — Lembar Informasi</h2>
    <p class="muted">Bacakan/berikan setiap bagian kepada calon subjek, beri kesempatan bertanya, lalu centang <b>Sudah dibaca/dijelaskan</b>. Formulir persetujuan baru terbuka setelah semua bagian dicentang.</p>
    <div class="progress"><div class="bar"><span id="icbar" style="width:0%"></span></div><span id="icnum">0/<?= $nBag ?></span> bagian</div>
  </div>
  <?php foreach ($T['bagian'] as $i => $b): ?>
  <div class="card icsec" id="b<?= $i ?>">
    <h3><?= h($b['judul']) ?></h3>
    <?php $inList = false; foreach ($b['isi'] as [$t, $x]) {
        if ($t === 'li' && !$inList) { echo '<ul>'; $inList = true; }
        if ($t !== 'li' && $inList) { echo '</ul>'; $inList = false; }
        echo $t === 'li' ? '<li>' . h($x) . '</li>' : '<p>' . h($x) . '</p>';
      } if ($inList) echo '</ul>'; ?>
    <label class="chk baca"><input type="checkbox" class="bacacek" name="baca[<?= $i ?>]" value="1" <?= isset($d['baca'][$i]) ? 'checked' : '' ?>> Sudah dibaca/dijelaskan</label>
  </div>
  <?php endforeach; ?>

  <fieldset id="ickonsen" class="card" disabled>
    <h2 style="margin-top:0">Langkah 2 — Persetujuan Setelah Penjelasan (Informed Consent)</h2>
    <p id="ickunci" class="alert warn">Terkunci: centang semua bagian Lembar Informasi terlebih dahulu.</p>
    <div class="grid">
      <div><label class="f">Kode subjek</label><input type="text" value="<?= h($s['kode']) ?>" disabled></div>
      <div><label class="f">Tanggal & jam penjelasan diberikan (≥ 24 jam sebelum tanda tangan)</label><input type="datetime-local" name="tgl_penjelasan" value="<?= $dv('tgl_penjelasan') ?>" required></div>
      <div><label class="f">Nama</label><input type="text" name="nama" value="<?= $dv('nama') ?>" required></div>
      <div><label class="f">Usia</label><input type="number" name="usia" min="18" max="70" value="<?= $dv('usia') ?>"></div>
      <div><label class="f">Alamat</label><input type="text" name="alamat" value="<?= $dv('alamat') ?>"></div>
      <div><label class="f">No. HP</label><input type="text" name="hp" value="<?= $dv('hp') ?>"></div>
    </div>
    <p style="margin-top:1rem">Saya yang bertanda tangan di bawah ini <?= h($T['pengantar_persetujuan']) ?></p>
    <?php foreach (array_slice($T['pernyataan'], 0, 3) as $i => $x): ?>
      <p><label class="chk" style="white-space:normal;align-items:flex-start"><input type="checkbox" name="paham[<?= $i ?>]" value="1" <?= isset($d['paham'][$i]) ? 'checked' : '' ?>> <span><?= $i + 1 ?>. <?= h($x) ?></span></label></p>
    <?php endforeach; ?>
    <p>4. Saya <?= opt('video', $d['video'] ?? null, ['bersedia' => 'bersedia', 'tidak' => 'tidak bersedia']) ?> direkam video wajah, dengan potongan rekaman hanya diperlihatkan kepada dokter penilai yang terikat kerahasiaan.</p>
    <p>5. Saya <?= opt('dihubungi', $d['dihubungi'] ?? null, ['bersedia' => 'bersedia', 'tidak' => 'tidak bersedia']) ?> dihubungi kembali untuk menerima ringkasan hasil pengukuran diri saya.</p>
    <p><b>Dengan ini saya menyatakan</b> <?= opt('setuju', $d['setuju'] ?? null, ['1' => 'SETUJU', '0' => 'TIDAK SETUJU']) ?> <b>untuk ikut serta dalam penelitian ini secara sadar dan tanpa paksaan.</b></p>
    <div class="grid"><div><label class="f">Tempat</label><input type="text" name="tempat" value="<?= $dv('tempat', 'Bandung') ?>"></div><div><label class="f">Tanggal tanda tangan</label><input type="text" value="<?= h(fmt_tgl(date('Y-m-d'))) ?> (otomatis)" disabled></div></div>

    <h2>Tanda tangan</h2>
    <div class="ttdgrid">
      <div><b>Subjek Penelitian</b><canvas class="sig" data-target="ttd_subjek" width="520" height="170"></canvas><input type="hidden" name="ttd_subjek"><button type="button" class="btn sm sec sigclear">Hapus</button></div>
      <div><b>Peneliti</b> — <?= h(user()['nama']) ?><br><span class="muted"><?= h(user()['jabatan'] ?? '') ?></span><canvas class="sig" data-target="ttd_peneliti" width="520" height="170"></canvas><input type="hidden" name="ttd_peneliti"><button type="button" class="btn sm sec sigclear">Hapus</button></div>
      <div><b>Saksi (di luar rantai komando)</b>
        <input type="text" name="saksi_nama" value="<?= $dv('saksi_nama', SAKSI_NAMA) ?>" placeholder="nama saksi" required>
        <input type="text" name="saksi_jabatan" value="<?= $dv('saksi_jabatan', SAKSI_JAB) ?>" placeholder="jabatan/instansi saksi" required style="margin-top:.3rem">
        <canvas class="sig" data-target="ttd_saksi" width="520" height="170"></canvas><input type="hidden" name="ttd_saksi"><button type="button" class="btn sm sec sigclear">Hapus</button></div>
    </div>
    <p class="muted">Penandatanganan dilakukan di ruang tertutup tanpa kehadiran atasan subjek. Lembar dicetak rangkap dua: satu untuk subjek, satu untuk peneliti.</p>
    <div class="sticky"><button class="btn">Simpan persetujuan</button></div>
  </fieldset>
</form>
<?php page_foot();
