<?php
// Ekspor CSV (dibuka di Excel) — untuk analisis dan pencocokan dengan berkas logger
require __DIR__ . '/inc.php';
require_editor();

$jenis = $_GET['j'] ?? '';
$SQL = [
    'log' => ["log_mengemudi", "SELECT s.kode, k.ke kunjungan, k.tanggal, r.sesi, r.urut, r.waktu waktu_rundown, r.menit, r.kegiatan, r.keterangan, r.jalur_v, r.tipe,
                 REPLACE(r.id_rekaman,'{SUBJ}',s.kode) id_rekaman, l.jadwal_mulai, l.jadwal_selesai, l.menit_jadwal, l.jam_mulai, l.jam_selesai, l.kss, l.v_aktual, l.pass, l.kode_obs, l.catatan
              FROM log_baris l JOIN rundown r ON r.id=l.rundown_id JOIN kunjungan k ON k.id=l.kunjungan_id JOIN subjek s ON s.id=k.subjek_id
              ORDER BY s.kode, k.ke, r.sesi='siang', r.urut"],
    'kss' => ["kss_timeline", "SELECT s.kode, k.tanggal, r.sesi, r.waktu waktu_rundown, l.jam_mulai jam_aktual, l.kss, REPLACE(r.id_rekaman,'{SUBJ}',s.kode) id_rekaman
              FROM log_baris l JOIN rundown r ON r.id=l.rundown_id JOIN kunjungan k ON k.id=l.kunjungan_id JOIN subjek s ON s.id=k.subjek_id
              WHERE l.kss IS NOT NULL ORDER BY s.kode, k.tanggal, r.sesi='siang', r.urut"],
    'kelaikan' => ["kelaikan", "SELECT s.kode, k.ke kunjungan, k.tanggal, kl.* FROM kelaikan kl JOIN kunjungan k ON k.id=kl.kunjungan_id JOIN subjek s ON s.id=k.subjek_id ORDER BY s.kode, k.ke"],
    'pasca' => ["pasca_sesi", "SELECT s.kode, k.ke kunjungan, k.tanggal, k.status, k.kompensasi_diterima, p.* FROM pasca p JOIN kunjungan k ON k.id=p.kunjungan_id JOIN subjek s ON s.id=k.subjek_id ORDER BY s.kode, k.ke"],
    'henti' => ["penghentian", "SELECT s.kode, k.tanggal, h.jam, h.alasan, h.tindakan, h.catatan FROM henti h JOIN kunjungan k ON k.id=h.kunjungan_id JOIN subjek s ON s.id=k.subjek_id ORDER BY k.tanggal, h.jam"],
    'subjek' => ["subjek_demografis", "SELECT * FROM subjek ORDER BY kode"],
];

jw_pastikan_kolom();
if (isset($SQL[$jenis])) {
    [$nama, $sql] = $SQL[$jenis];
    audit('ekspor CSV', $nama);
    $data = rows($sql);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nama . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
    if ($data) {
        fputcsv($out, array_keys($data[0]), ';');
        foreach ($data as $r) fputcsv($out, array_values($r), ';');
    }
    exit;
}

page_head('Ekspor Data (CSV)');
?>
<div class="card">
  <p>Berkas CSV memakai pemisah titik koma (;) agar langsung terbaca Excel berlokal Indonesia.</p>
  <ul>
    <li><a href="?j=log">Log mengemudi lengkap (F-6)</a> — untuk memotong data WBV per segmen berdasarkan jam dan ID rekaman</li>
    <li><a href="?j=kss">Linimasa KSS</a> — label kantuk untuk pelatihan model</li>
    <li><a href="?j=kelaikan">Kelaikan pra-sesi (F-4)</a></li>
    <li><a href="?j=pasca">Pasca-sesi (F-7)</a></li>
    <li><a href="?j=henti">Penghentian lebih awal</a></li>
    <li><a href="?j=subjek">Data demografis (F-3)</a></li>
  </ul>
  <p class="muted">Data berisi kode subjek saja. Simpan berkas ekspor di media terenkripsi sesuai protokol etik.</p>
</div>
<?php page_foot();
