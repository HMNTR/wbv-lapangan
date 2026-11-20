<?php
// F-3 Data demografis dan pengalaman mengemudi
require __DIR__ . '/inc.php';
require_editor();
csrf_check();

$id = intOrNull($_GET['id'] ?? null);
$s = $id ? row("SELECT * FROM subjek WHERE id=?", [$id]) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode = strtoupper(trim((string)post('kode', '')));
    if (!preg_match('/^[A-Z0-9\-]{2,10}$/', $kode)) { flash('Kode subjek tidak valid (contoh: S01). Jangan memakai nama.', 'err'); redirect($_SERVER['REQUEST_URI']); }
    $f = ['kode' => $kode, 'usia' => intOrNull(post('usia')), 'jenis_kelamin' => post('jenis_kelamin'),
        'tinggi_cm' => intOrNull(post('tinggi_cm')), 'berat_kg' => post('berat_kg'),
        'pengalaman_mv3_bulan' => intOrNull(post('pengalaman_mv3_bulan')), 'pengalaman_total_tahun' => intOrNull(post('pengalaman_total_tahun')),
        'jam_mengemudi_minggu' => intOrNull(post('jam_mengemudi_minggu')), 'tidur_rata_jam' => post('tidur_rata_jam'),
        'kacamata' => post('kacamata'), 'rokok_batang_hari' => intOrNull(post('rokok_batang_hari')), 'kopi_gelas_hari' => intOrNull(post('kopi_gelas_hari')),
        'mcu_terakhir' => post('mcu_terakhir'), 'ic_tanggal' => post('ic_tanggal'), 'catatan' => post('catatan'), 'updated_by' => uid()];
    try {
        if ($id) {
            $set = implode(',', array_map(fn($k) => "$k=:$k", array_keys($f)));
            q("UPDATE subjek SET $set WHERE id=:id", $f + ['id' => $id]);
        } else {
            q("INSERT INTO subjek (" . implode(',', array_keys($f)) . ") VALUES (:" . implode(',:', array_keys($f)) . ")", $f);
            $id = (int)db()->lastInsertId();
        }
        audit('simpan F-3', $kode);
        flash('Data subjek tersimpan.');
        redirect("subjek.php?id=$id");
    } catch (PDOException $e) {
        flash($e->getCode() === '23000' ? 'Kode subjek sudah dipakai.' : 'Gagal menyimpan: ' . $e->getMessage(), 'err');
        redirect($_SERVER['REQUEST_URI']);
    }
}

page_head($id ? 'F-3 Data Demografis — ' . ($s['kode'] ?? '') : 'Subjek Baru (F-3)');
$v = fn($k) => h($s[$k] ?? '');
?>
<form method="post" class="card" data-track>
  <?= csrf_field() ?>
  <div class="grid">
    <div><label class="f">Kode subjek (tanpa nama) *</label><input type="text" name="kode" value="<?= $v('kode') ?: 'S01' ?>" required></div>
    <div><label class="f">Tanggal informed consent ditandatangani</label><input type="date" name="ic_tanggal" value="<?= $v('ic_tanggal') ?>"></div>
    <div><label class="f">Usia (tahun)</label><input type="number" name="usia" min="18" max="70" value="<?= $v('usia') ?>"></div>
    <div><label class="f">Jenis kelamin</label><?= opt('jenis_kelamin', $s['jenis_kelamin'] ?? null, ['L' => 'Laki-laki', 'P' => 'Perempuan']) ?></div>
    <div><label class="f">Tinggi badan (cm)</label><input type="number" name="tinggi_cm" value="<?= $v('tinggi_cm') ?>"></div>
    <div><label class="f">Berat badan (kg)</label><input type="number" step="0.1" name="berat_kg" value="<?= $v('berat_kg') ?>"></div>
    <div><label class="f">Pengalaman mengemudikan MV3/Maung (bulan)</label><input type="number" name="pengalaman_mv3_bulan" value="<?= $v('pengalaman_mv3_bulan') ?>"></div>
    <div><label class="f">Total pengalaman mengemudi (tahun)</label><input type="number" name="pengalaman_total_tahun" value="<?= $v('pengalaman_total_tahun') ?>"></div>
    <div><label class="f">Rata-rata jam mengemudi uji per minggu</label><input type="number" name="jam_mengemudi_minggu" value="<?= $v('jam_mengemudi_minggu') ?>"></div>
    <div><label class="f">Rata-rata lama tidur malam (jam)</label><input type="number" step="0.5" name="tidur_rata_jam" value="<?= $v('tidur_rata_jam') ?>"></div>
    <div><label class="f">Kacamata/lensa kontak saat mengemudi</label><?= opt('kacamata', $s['kacamata'] ?? null, ['tidak' => 'Tidak', 'kacamata' => 'Kacamata', 'lensa' => 'Lensa kontak']) ?></div>
    <div><label class="f">Merokok (batang/hari, 0 = tidak)</label><input type="number" name="rokok_batang_hari" value="<?= $v('rokok_batang_hari') ?>"></div>
    <div><label class="f">Kopi (gelas/hari, 0 = tidak)</label><input type="number" name="kopi_gelas_hari" value="<?= $v('kopi_gelas_hari') ?>"></div>
    <div><label class="f">Pemeriksaan kesehatan berkala terakhir (bulan/tahun)</label><input type="text" name="mcu_terakhir" value="<?= $v('mcu_terakhir') ?>" placeholder="mis. 08/2026 — laik"></div>
  </div>
  <label class="f" style="margin-top:.8rem">Catatan</label><textarea name="catatan"><?= $v('catatan') ?></textarea>
  <p><button class="btn">Simpan</button> <a class="btn sec" href="index.php">Kembali</a></p>
</form>
<?php page_foot();
