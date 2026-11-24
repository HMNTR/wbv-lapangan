<?php
// Cetak dokumen A4 terisi: F-3, F-4, F-6 (pagi/siang), F-7, F-8
require __DIR__ . '/inc.php';
require_login();

$kid = intOrNull($_GET['k'] ?? null);
$sid = intOrNull($_GET['s'] ?? null);
$f = (string)($_GET['f'] ?? 'all');
$k = $kid ? kunjungan_or_404($kid) : null;
if ($k) $sid = (int)$k['subjek_id'];
$s = $sid ? row("SELECT * FROM subjek WHERE id=?", [$sid]) : null;
if (!$s) { http_response_code(404); exit('Data tidak ditemukan.'); }
audit('cetak', ($k ? "kunjungan {$k['ke']} " : '') . $f, $kid);

$B = batas();
$v = fn($x, $alt = '') => ($x === null || $x === '') ? ($alt !== '' ? $alt : '<span class="kosong"></span>') : h($x);
$ya = fn($x, $y = 'Ya', $t = 'Tidak') => $x === null || $x === '' ? '<span class="kosong"></span>' : ((string)$x === '1' ? "☒ $y ☐ $t" : "☐ $y ☒ $t");
$cek = fn($on, $lbl) => ($on ? '☒' : '☐') . ' ' . h($lbl);

function kop(string $kode, string $judul, string $sub, ?array $k, array $s): void {
    echo "<section class='hal'><div class='kop'><div><b>ALAT PENGUMPULAN DATA — UJI WBV " . h(strtoupper((string)setting('kendaraan'))) . ", PT PINDAD</b><br>Layak etik KEPK UNPRI No. " . h(setting('no_etik')) . "</div><div class='kode'>" . h($kode) . "</div></div>";
    echo "<h2>" . h($judul) . "</h2><p class='sub'>" . h($sub) . "</p>";
    echo "<table class='id'><tr><th>Kode subjek</th><td><b>" . h($s['kode']) . "</b></td><th>Kendaraan</th><td>" . h(setting('kendaraan')) . ($k && $k['no_kendaraan'] ? ' No. ' . h($k['no_kendaraan']) : '') . "</td></tr>";
    if ($k) echo "<tr><th>Tanggal</th><td>" . h(fmt_tgl($k['tanggal'])) . "</td><th>Kunjungan ke- / skenario</th><td>" . (int)$k['ke'] . " · " . h($k['skenario']) . "</td></tr>";
    echo "</table>";
}
function ttd(array $kolom): void {
    echo "<table class='ttd'><tr>";
    foreach ($kolom as $c) {
        [$peran, $nama] = $c; $jab = $c[2] ?? ''; $el = $c[3] ?? '';
        echo "<td>" . h($peran) . ($el ? "<div class='ruang el'>✔ Disetujui elektronik<br>" . h($el) . "</div>" : "<div class='ruang'></div>")
           . "(" . ($nama ? h($nama) : str_repeat('.', 32)) . ")" . ($jab ? "<br><span class='jab'>" . h($jab) . "</span>" : '') . "</td>";
    }
    echo "</tr></table>";
}
// kolom tanda tangan dari persetujuan elektronik (bila ada) + kolom tambahan di depan
function ttd_form(int $kid, string $form, array $depan = []): void {
    $ok = persetujuan($kid, $form); $ubah = form_diubah($kid, $form);
    $kol = $depan;
    foreach (FORM_SLOT[$form] as $slot) {
        $a = $ok[$slot] ?? null;
        $kol[] = [PERAN[$slot] . ',', $a['nama'] ?? '', $a['jabatan'] ?? '', $a ? date('d-m-Y H:i', strtotime($a['waktu'])) . (($ubah && $ubah > $a['waktu']) ? ' (data berubah sesudahnya)' : '') : ''];
    }
    ttd($kol);
}
function kaki(): void {
    echo "<div class='kaki'>Dicetak dari WBV Lapangan oleh " . h(user()['nama']) . " · " . date('d-m-Y H:i') . "</div></section>";
}

ob_start();
// ======================= F-3 =======================
if (!$k && in_array($f, ['f3', 'all'], true)) {
    kop('F-3', 'DATA DEMOGRAFIS DAN PENGALAMAN MENGEMUDI', 'Diisi peneliti melalui wawancara, satu kali', null, $s);
    $kac = ['tidak' => 'Tidak', 'kacamata' => 'Kacamata', 'lensa' => 'Lensa kontak'];
    echo "<table class='isi'><tr><th style='width:6%'>No</th><th>Butir</th><th style='width:45%'>Isian</th></tr>";
    $r = [['Usia', $s['usia'] ? $s['usia'] . ' tahun' : null], ['Jenis kelamin', ['L' => 'Laki-laki', 'P' => 'Perempuan'][$s['jenis_kelamin']] ?? null],
          ['Tinggi badan / berat badan', $s['tinggi_cm'] ? "{$s['tinggi_cm']} cm / {$s['berat_kg']} kg" : null],
          ['Pengalaman mengemudikan MV3/Maung', $s['pengalaman_mv3_bulan'] !== null ? $s['pengalaman_mv3_bulan'] . ' bulan' : null],
          ['Total pengalaman mengemudi', $s['pengalaman_total_tahun'] !== null ? $s['pengalaman_total_tahun'] . ' tahun' : null],
          ['Rata-rata jam mengemudi uji per minggu', $s['jam_mengemudi_minggu'] !== null ? $s['jam_mengemudi_minggu'] . ' jam' : null],
          ['Rata-rata lama tidur malam', $s['tidur_rata_jam'] !== null ? $s['tidur_rata_jam'] . ' jam' : null],
          ['Kacamata/lensa kontak saat mengemudi', $kac[$s['kacamata']] ?? null],
          ['Merokok', $s['rokok_batang_hari'] === null ? null : ((int)$s['rokok_batang_hari'] ? $s['rokok_batang_hari'] . ' batang/hari' : 'Tidak')],
          ['Minum kopi', $s['kopi_gelas_hari'] === null ? null : ((int)$s['kopi_gelas_hari'] ? $s['kopi_gelas_hari'] . ' gelas/hari' : 'Tidak')],
          ['Pemeriksaan kesehatan berkala terakhir', $s['mcu_terakhir']], ['Tanggal informed consent ditandatangani', fmt_tgl($s['ic_tanggal'])]];
    foreach ($r as $i => [$a, $b]) echo "<tr><td>" . ($i + 1) . "</td><td>" . h($a) . "</td><td>" . $v($b) . "</td></tr>";
    echo "</table>";
    if ($s['catatan']) echo "<p><b>Catatan:</b> " . h($s['catatan']) . "</p>";
    ttd([['Peneliti,', setting('peneliti')]]);
    kaki();
}


// ======================= Lembar Informasi + IC =======================
if (!$k && in_array($f, ['ic'], true)) {
    if (!can_edit()) { echo "<section class='hal'><p>Dokumen ini memuat identitas subjek dan hanya dapat dicetak Admin/Ketua/Peneliti.</p></section>"; }
    else {
        $T = require __DIR__ . '/ic_teks.php';
        $ic = row("SELECT * FROM informed_consent WHERE subjek_id=?", [$sid]);
        echo "<section class='hal ic'><div class='kop'><div><b>LEMBAR INFORMASI DAN PERSETUJUAN SETELAH PENJELASAN</b><br>Layak etik KEPK UNPRI No. " . h(setting('no_etik')) . "</div><div class='kode'>IC</div></div>";
        echo "<h2>" . h($T['judul']) . "</h2>";
        foreach ($T['bagian'] as $i => $b) {
            echo $i ? "<h3>" . h($b['judul']) . "</h3>" : '';
            $inL = false;
            foreach ($b['isi'] as [$t, $x]) { if ($t === 'li' && !$inL) { echo '<ul>'; $inL = true; } if ($t !== 'li' && $inL) { echo '</ul>'; $inL = false; } echo $t === 'li' ? '<li>' . h($x) . '</li>' : '<p>' . h($x) . '</p>'; }
            if ($inL) echo '</ul>';
            if ($ic) echo "<div class='cek'>☒ Sudah dibaca/dijelaskan</div>";
        }
        echo "<h2 style='page-break-before:always;margin-top:0'>PERSETUJUAN SETELAH PENJELASAN (INFORMED CONSENT)</h2>";
        echo "<p>Kode subjek (diisi peneliti): <b>" . h($s['kode']) . "</b></p><p>Saya yang bertanda tangan di bawah ini,</p>";
        echo "<table class='id'><tr><th>Nama</th><td>" . $v($ic['nama'] ?? null) . "</td></tr><tr><th>Usia</th><td>" . $v($ic['usia'] ?? null) . "</td></tr><tr><th>Alamat</th><td>" . $v($ic['alamat'] ?? null) . "</td></tr><tr><th>No. HP</th><td>" . $v($ic['hp'] ?? null) . "</td></tr></table>";
        echo "<p>" . h($T['pengantar_persetujuan']) . "</p><ol>";
        foreach (array_slice($T['pernyataan'], 0, 3) as $x) echo "<li>" . h($x) . ($ic ? " ☒" : '') . "</li>";
        $pil = fn($val) => $ic ? ($val === 'bersedia' ? '<b>bersedia</b> / <s>tidak bersedia</s>' : '<s>bersedia</s> / <b>tidak bersedia</b>') : 'bersedia / tidak bersedia <i>(coret salah satu)</i>';
        echo "<li>saya " . $pil($ic['video'] ?? null) . " direkam video wajah, dengan potongan rekaman hanya diperlihatkan kepada dokter penilai yang terikat kerahasiaan;</li>";
        echo "<li>saya " . $pil($ic['dihubungi'] ?? null) . " dihubungi kembali untuk menerima ringkasan hasil pengukuran diri saya.</li></ol>";
        echo "<p>Dengan ini saya menyatakan <b>" . ($ic ? ($ic['setuju'] ? 'SETUJU' : 'TIDAK SETUJU') : 'SETUJU') . "</b> untuk ikut serta dalam penelitian ini secara sadar dan tanpa paksaan.</p>";
        echo "<p>" . h($ic['tempat'] ?? 'Bandung') . ", " . ($ic ? h(fmt_tgl($ic['ditandatangani_at'])) : str_repeat('.', 30)) . "</p>";
        $im = fn($d) => $d ? "<img class='ttd' src='" . h($d) . "' alt=''>" : "<div class='ruang'></div>";
        echo "<table class='ttd'><tr><td>Subjek Penelitian," . $im($ic['ttd_subjek'] ?? null) . "(" . ($ic ? h($ic['nama']) : str_repeat('.', 32)) . ")</td>"
           . "<td>Peneliti," . $im($ic['ttd_peneliti'] ?? null) . "(" . h($ic['peneliti_nama'] ?? setting('peneliti')) . ")<br><span class='jab'>" . h($ic['peneliti_jabatan'] ?? '') . "</span></td>"
           . "<td>Saksi (di luar rantai komando)," . $im($ic['ttd_saksi'] ?? null) . "(" . h($ic['saksi_nama'] ?? str_repeat('.', 32)) . ")<br><span class='jab'>Jabatan/instansi: " . h($ic['saksi_jabatan'] ?? '') . "</span></td></tr></table>";
        if ($ic) echo "<p class='kecil'>Penjelasan diberikan: " . h($ic['tgl_penjelasan']) . " · ditandatangani elektronik: " . h($ic['ditandatangani_at']) . " · versi teks " . h(substr($ic['versi_teks'], 0, 10)) . ($ic['ditarik_at'] ? " · <b>DITARIK " . h($ic['ditarik_at']) . "</b>" : '') . "</p>";
        echo "<p class='kecil'><i>Lembar ini dibuat rangkap dua: satu untuk subjek dan satu untuk peneliti.</i></p>";
        kaki();
    }
}

// ======================= F-8 =======================
if (!$k && in_array($f, ['f8', 'all'], true)) {
    kop('F-8', 'REKAP KEHADIRAN DAN KOMPENSASI', 'Satu lembar per subjek', null, $s);
    $list = rows("SELECT k.*, kl.kss_awal, p.kss_akhir, p.menit_pagi, p.menit_siang, p.ktd FROM kunjungan k
                  LEFT JOIN kelaikan kl ON kl.kunjungan_id=k.id LEFT JOIN pasca p ON p.kunjungan_id=k.id WHERE k.subjek_id=? ORDER BY k.ke", [$sid]);
    echo "<table class='isi'><tr><th>Kunjungan</th><th>Tanggal</th><th>Skenario</th><th>Status</th><th>KSS awal / akhir</th><th>Mengemudi (menit)</th><th>KTD</th><th>Kompensasi</th><th style='width:14%'>Paraf subjek</th></tr>";
    $tot = 0;
    foreach ($list as $x) {
        $tot += $x['kompensasi_diterima'] ? nilai_kompensasi() : 0;
        echo "<tr style='height:2.2em'><td>" . (int)$x['ke'] . "</td><td>" . h(fmt_tgl($x['tanggal'])) . "</td><td>" . h($x['skenario']) . "</td><td>" . h($x['status']) . "</td><td>" . h($x['kss_awal'] ?? '–') . " / " . h($x['kss_akhir'] ?? '–') . "</td><td>" . (((int)$x['menit_pagi'] + (int)$x['menit_siang']) ?: '–') . "</td><td>" . ($x['ktd'] ? 'Ya' : 'Tidak') . "</td><td>" . ($x['kompensasi_diterima'] ? h(setting('kompensasi')) : '–') . "</td><td></td></tr>";
    }
    echo "</table><p>Total kompensasi diterima: <b>Rp. " . number_format($tot, 0, ',', '.') . "</b></p>";
    ttd([['Peneliti,', setting('peneliti')]]);
    kaki();
}

if ($k) {
    $kel = row("SELECT * FROM kelaikan WHERE kunjungan_id=?", [$kid]) ?? [];
    // ======================= F-4 =======================
    if (in_array($f, ['f4', 'all'], true)) {
        kop('F-4', 'LEMBAR KELAIKAN PRA-SESI', 'Sebelum mobil keluar', $k, $s);
        echo "<table class='id'><tr><th>Jam pemeriksaan</th><td>" . $v(jam($kel['jam_periksa'] ?? null)) . "</td><th>Informed consent</th><td>" . ($s['ic_tanggal'] ? 'Ditandatangani ' . h(fmt_tgl($s['ic_tanggal'])) : '<span class="kosong"></span>') . "</td></tr></table>";
        $sl = ['tidak' => 'Tidak', 'ringan' => 'Ringan', 'berat' => 'Berat'];
        $it = [
            ['Subjek menyatakan ulang (lisan) bersedia ikut hari ini', $ya($kel['bersedia'] ?? null), 'Tidak → tidak ikut'],
            ['Jam tidur semalam / lama tidur', $v(trim(($kel['jam_tidur'] ?? '') . ' / ' . (($kel['lama_tidur'] ?? null) !== null ? $kel['lama_tidur'] . ' jam' : ''), ' /') ?: null), '< ' . $B['tidur_min_jam'] . ' jam'],
            ['Jam bangun pagi ini', $v(jam($kel['jam_bangun'] ?? null)), '–'],
            ['Alkohol dalam 24 jam terakhir', $ya($kel['alkohol'] ?? null), 'Ya'],
            ['Obat penyebab kantuk dalam 24 jam terakhir', $ya($kel['obat_kantuk'] ?? null), 'Ya'],
            ['Kafein dalam 3 jam terakhir (dicatat)', $ya($kel['kafein'] ?? null), '–'],
            ['Keluhan kesehatan saat ini', $v($kel['keluhan'] ?? null, isset($kel['id']) ? 'Tidak ada' : ''), 'Menurut Ketua Penelitian'],
        ];
        if ((int)$k['ke'] > 1) $it[] = ['Sisa lelah dari pengujian sebelumnya', $v($sl[$kel['sisa_lelah'] ?? ''] ?? null), 'Berat → dinilai Ketua Penelitian'];
        $it[] = ['Tekanan darah / nadi', $v(isset($kel['td_sistol']) && $kel['td_sistol'] ? "{$kel['td_sistol']}/{$kel['td_diastol']} mmHg · {$kel['nadi']} ×/menit" : null), "≥ {$B['td_sistol_max']}/{$B['td_diastol_max']} · nadi < {$B['nadi_min']} / > {$B['nadi_max']}"];
        $it[] = ['<b>KSS awal</b> (1–9)', $v($kel['kss_awal'] ?? null), '≥ ' . $B['kss_tunda']];
        echo "<table class='isi'><tr><th style='width:5%'>No</th><th>Pemeriksaan</th><th style='width:30%'>Hasil</th><th style='width:22%'>Tunda bila</th></tr>";
        foreach ($it as $i => [$a, $b, $c]) echo "<tr><td>" . ($i + 1) . "</td><td>$a</td><td>$b</td><td>" . h($c) . "</td></tr>";
        echo "</table>";
        $kp = $kel['keputusan'] ?? null;
        echo "<p><b>Keputusan:</b> " . $cek($kp === 'lanjut', 'Lanjut') . " &nbsp; " . $cek($kp === 'tunda', 'Ditunda') . ($kp === 'tunda' ? ' — alasan: ' . h($kel['alasan_tunda']) : '') . "</p>";
        $sk = $kel['siang_keputusan'] ?? null;
        echo "<p><b>Cek ulang sebelum sesi siang (setelah ISHOMA):</b> jam " . $v(jam($kel['siang_jam'] ?? null)) . " · KSS " . $v($kel['siang_kss'] ?? null) . " · keluhan " . $v($kel['siang_keluhan'] ?? null, isset($kel['siang_jam']) && $kel['siang_jam'] ? 'tidak ada' : '') . " → " . $cek($sk === 'lanjut', 'Lanjut') . " " . $cek($sk === 'berhenti', 'Berhenti') . "</p>";
        if (!empty($kel['nakes'])) echo "<p class='kecil'>Pemeriksa tekanan darah/nadi: " . h($kel['nakes']) . "</p>";
        ttd_form($kid, 'f4');
        if (!empty($kel['updated_by'])) echo "<p class='kecil'>Terakhir diisi di aplikasi oleh: " . h(nama_user((int)$kel['updated_by'])) . "</p>";
        kaki();
    }

    // ======================= F-6 =======================
    foreach (['pagi' => 'f6p', 'siang' => 'f6s'] as $sesi => $kode) {
        if (!in_array($f, [$kode, 'f6', 'all'], true)) continue;
        $is24 = (bool)row("SELECT 1 FROM rundown WHERE hari=? AND keterangan IS NOT NULL AND keterangan<>'' LIMIT 1", [$k['template_hari']]);
        jw_pastikan_kolom();
        $rows = rows("SELECT l.*, r.waktu, r.menit, r.kegiatan, r.keterangan, r.tipe, r.id_rekaman, r.jalur_v FROM log_baris l JOIN rundown r ON r.id=l.rundown_id
                      WHERE l.kunjungan_id=? AND r.sesi=? ORDER BY r.urut", [$kid, $sesi]);
        if (!$rows) continue;
        kop('F-6', 'LOG MENGEMUDI — ' . strtoupper($sesi), $is24 ? 'Jam sesuai rundown tim manekin; KSS ditanyakan di setiap jeda (baris abu biru)' : 'Jam sesuai rundown skenario WBV; KSS lisan tiap 10 menit', $k, $s);
        echo "<table class='id'><tr><th>Cuaca</th><td>" . $v($k['cuaca']) . "</td><th>Lintasan</th><td>" . $v($k['lintasan']) . "</td><th>Uji ketuk</th><td>" . ($k['uji_ketuk'] ? '☒ OK' : '☐') . "</td><th>Diam 30″ awal/akhir</th><td>" . $v(jam($k['diam_awal_jam'])) . " / " . $v(jam($k['diam_akhir_jam'])) . "</td></tr></table>";
        echo "<table class='isi log'><tr><th>Waktu (jadwal)</th><th>Mnt</th><th>Kegiatan</th><th>" . ($is24 ? 'Keterangan' : 'Jalur · v') . "</th><th>ID rekaman</th><th>Mulai</th><th>Selesai</th><th>KSS</th><th>v aktual" . ($is24 ? '' : ' / pass') . "</th><th>Kode</th><th>Catatan</th></tr>";
        foreach ($rows as $r) {
            $hi = $r['kss'] !== null && (int)$r['kss'] >= 7;
            echo "<tr class='t-{$r['tipe']}" . ($hi ? ' hi' : '') . "'><td>" . h(jw_label($r)) . "</td><td>" . h($r['menit_jadwal'] ?? $r['menit']) . "</td><td>" . h($r['kegiatan']) . "</td><td>" . h($is24 ? $r['keterangan'] : $r['jalur_v']) . "</td><td>" . h(str_replace('{SUBJ}', $s['kode'], (string)$r['id_rekaman'])) . "</td><td>" . h(jam($r['jam_mulai'])) . "</td><td>" . h(jam($r['jam_selesai'])) . "</td><td class='c'><b>" . h($r['kss']) . "</b></td><td>" . h(trim($r['v_aktual'] . ($r['pass'] ? ' / ' . $r['pass'] : ''))) . "</td><td>" . h($r['kode_obs']) . "</td><td>" . h($r['catatan']) . "</td></tr>";
        }
        echo "</table><p class='kecil'>Kode: Y menguap · K kedip lama/mata terpejam · A kepala terangguk · L penyimpangan lajur · S gangguan sensor · T keluhan tubuh. Baris merah = KSS ≥ 7.</p>";
        $henti = rows("SELECT * FROM henti WHERE kunjungan_id=? ORDER BY jam", [$kid]);
        echo "<p><b>Penghentian lebih awal:</b> " . ($henti ? '' : 'tidak ada') . "</p>";
        if ($henti) { echo "<table class='isi'><tr><th>Jam</th><th>Alasan</th><th>Tindakan</th><th>Catatan</th></tr>"; foreach ($henti as $x) echo "<tr><td>" . h(jam($x['jam'])) . "</td><td>" . h($x['alasan']) . "</td><td>" . ($x['tindakan'] === 'sesi_diakhiri' ? 'Sesi diakhiri' : 'Istirahat ≥ 20′, lanjut') . "</td><td>" . h($x['catatan']) . "</td></tr>"; echo "</table>"; }
        $pengisi = rows("SELECT DISTINCT l.updated_by FROM log_baris l JOIN rundown r ON r.id=l.rundown_id WHERE l.kunjungan_id=? AND r.sesi=? AND l.updated_by IS NOT NULL", [$kid, $sesi]);
        ttd_form($kid, "f6_$sesi");
        if ($pengisi) echo "<p class='kecil'>Diisi di aplikasi oleh: " . h(implode(', ', array_map(fn($x) => nama_user((int)$x['updated_by']), $pengisi))) . "</p>";
        kaki();
    }

    // ======================= F-7 =======================
    if (in_array($f, ['f7', 'all'], true)) {
        $p = row("SELECT * FROM pasca WHERE kunjungan_id=?", [$kid]) ?? [];
        kop('F-7', 'RINGKASAN PASCA-SESI', 'Setelah rekaman terakhir dan pelepasan sensor', $k, $s);
        $tm = (($p['menit_pagi'] ?? null) !== null || ($p['menit_siang'] ?? null) !== null) ? ((int)($p['menit_pagi'] ?? 0)) . ' + ' . ((int)($p['menit_siang'] ?? 0)) . ' = ' . ((int)($p['menit_pagi'] ?? 0) + (int)($p['menit_siang'] ?? 0)) . ' menit' : null;
        echo "<table class='id'><tr><th>Jam selesai</th><td>" . $v(jam($p['jam_selesai'] ?? null)) . "</td><th>Total mengemudi (pagi + siang)</th><td>" . $v($tm) . "</td></tr></table>";
        $it = [
            ['<b>KSS akhir hari ini</b>', $v($p['kss_akhir'] ?? null)],
            ['Seluruh segmen/skenario selesai?', $ya($p['semua_selesai'] ?? null) . (($p['tidak_selesai'] ?? '') ? ' — tidak selesai: ' . h($p['tidak_selesai']) : '')],
            ['VDV kumulatif harian seat pad, sumbu z (m/s^1,75)', $v($p['vdv_z'] ?? null) . (($p['vdv_z'] ?? null) !== null ? ' · ' . h(vdv_zona($p['vdv_z'])) : '')],
            ['Kehilangan cuplikan logger (sasaran < 0,5%)', $v(($p['loss_persen'] ?? null) !== null ? $p['loss_persen'] . ' %' : null)],
            ['Kondisi dada/kulit setelah tali dilepas', $v(isset($p['kulit']) ? ucfirst($p['kulit']) . (($p['kulit_tindakan'] ?? '') ? ' — ' . $p['kulit_tindakan'] : '') : null)],
            ['Keluhan subjek', $v($p['keluhan'] ?? null, isset($p['id']) ? 'Tidak ada' : '')],
            ['Kejadian Tidak Diinginkan (KTD)', $ya($p['ktd'] ?? null)],
            ['Kelengkapan berkas IMU-1/2/3 & backup', $ya($p['berkas_lengkap'] ?? null, 'Lengkap', 'Ada yang hilang') . (($p['berkas_hilang'] ?? '') ? ': ' . h($p['berkas_hilang']) : '')],
            ['Subjek tetap bersedia datanya digunakan', $ya($p['bersedia_data'] ?? null)],
        ];
        echo "<table class='isi'><tr><th style='width:5%'>No</th><th>Butir</th><th style='width:48%'>Isian</th></tr>";
        foreach ($it as $i => [$a, $b]) echo "<tr><td>" . ($i + 1) . "</td><td>$a</td><td>$b</td></tr>";
        echo "</table>";
        echo "<p><b>Catatan pelaksanaan terhadap protokol:</b> " . $v($p['deviasi'] ?? null) . "</p>";
        echo "<p><b>Uraian KTD/keluhan dan tindak lanjut:</b></p><div class='kotak'>" . nl2br(h($p['ktd_uraian'] ?? '')) . "</div>";
        echo "<p><b>Tanda terima kompensasi:</b> Saya telah menerima " . h(setting('kompensasi')) . ". " . ($k['kompensasi_diterima'] ? '☒ diterima' : '☐') . "</p>";
        ttd_form($kid, 'f7', [['Subjek (paraf tanda terima),', '']]);
        if (!empty($p['updated_by'])) echo "<p class='kecil'>Terakhir diisi di aplikasi oleh: " . h(nama_user((int)$p['updated_by'])) . "</p>";
        kaki();
    }
}
$isi = ob_get_clean();

$pilihan = $k ? ['all' => 'Semua', 'f4' => 'F-4', 'f6p' => 'F-6 Pagi', 'f6s' => 'F-6 Siang', 'f7' => 'F-7'] : (can_edit() ? ['all' => 'F-3 + F-8', 'f3' => 'F-3', 'f8' => 'F-8', 'ic' => 'Informasi & IC'] : ['all' => 'F-3 + F-8', 'f3' => 'F-3', 'f8' => 'F-8']);
$base = $k ? "cetak.php?k=$kid" : "cetak.php?s=$sid";
?><!doctype html><html lang="id"><head><meta charset="utf-8"><title>Cetak · <?= h($s['kode']) ?><?= $k ? ' · ' . h(fmt_tgl($k['tanggal'])) : '' ?></title>
<style>
@page{size:A4;margin:12mm 12mm 14mm}
*{box-sizing:border-box}
body{font:10.5pt/1.35 "Times New Roman",Times,serif;color:#000;margin:0;background:#e9e9e9}
.alat{position:sticky;top:0;background:#1d2433;color:#fff;padding:.5rem 1rem;font:14px system-ui,sans-serif;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;z-index:5}
.alat a,.alat button{color:#fff;background:#3a465e;border:0;border-radius:5px;padding:.3rem .7rem;text-decoration:none;font:inherit;cursor:pointer}
.alat a.on{background:#1f5fbf}.alat button{background:#1e7d4f}
.hal{background:#fff;width:210mm;min-height:297mm;margin:10px auto;padding:12mm;box-shadow:0 1px 4px rgba(0,0,0,.2);position:relative}
.kop{display:flex;justify-content:space-between;border-bottom:1.5px solid #000;padding-bottom:3px;font-size:8.5pt}
.kop .kode{font:bold 13pt Arial,sans-serif;align-self:center}
h2{text-align:center;font-size:13pt;margin:8px 0 0}.sub{text-align:center;font-style:italic;margin:0 0 8px;font-size:9.5pt}
table{border-collapse:collapse;width:100%;margin:4px 0}
th,td{border:1px solid #000;padding:2.5px 4px;vertical-align:top;text-align:left}
th{background:#d9e2f3;font-weight:bold}
table.id th{width:auto;font-weight:normal;background:#f2f2f2}
table.isi td{height:1.7em}
table.log{font-size:7.8pt;line-height:1.2}table.log td,table.log th{height:auto;padding:1px 3px}
tr.t-j td{background:#eaf1fb}tr.t-r td,tr.t-x td{background:#f2f2f2}tr.hi td{background:#f4cccc!important}
td.c{text-align:center}
.kosong{display:inline-block;min-width:3.5cm;border-bottom:1px dotted #555;height:1em}
.ttd{margin-top:8px;page-break-inside:avoid}.ttd td{border:0;text-align:center;vertical-align:bottom}.ttd .ruang{height:13mm}.ttd .ruang.el{height:auto;min-height:13mm;font-size:7.5pt;color:#0a3d91;border:1px solid #9db4dd;border-radius:3px;margin:3px 6px;padding:3px}.ttd .jab{font-size:8pt}
img.ttd{height:16mm;max-width:100%;object-fit:contain;display:block;margin:0 auto}
.ic h3{font-size:10.5pt;margin:6px 0 2px}.ic p,.ic li{margin:1px 0;text-align:justify}.ic ul{margin:1px 0 1px 16px;padding:0}.cek{font-size:8.5pt;color:#1e5d2f}
.kotak{border:1px solid #000;min-height:18mm;padding:4px}
.kecil{font-size:8.5pt;color:#333}
.kaki{position:absolute;bottom:6mm;left:12mm;right:12mm;font-size:7.5pt;color:#555;border-top:1px solid #ccc;padding-top:2px}
@media print{body{background:#fff}.alat{display:none}.hal{margin:0;box-shadow:none;width:auto;min-height:auto;padding:0;page-break-after:always}.hal:last-child{page-break-after:auto}.kaki{position:static;margin-top:8px}}
</style></head><body>
<div class="alat"><b>Cetak <?= h($s['kode']) ?><?= $k ? ' · kunjungan ' . (int)$k['ke'] : '' ?></b>
<?php foreach ($pilihan as $kk => $l) echo "<a class='" . ($kk === $f ? 'on' : '') . "' href='$base&f=$kk'>" . h($l) . "</a>"; ?>
<button onclick="window.print()">🖨 Cetak / Simpan PDF</button>
<a href="<?= $k ? 'kunjungan.php?id=' . $kid : 'rekap.php?s=' . $sid ?>">Kembali</a></div>
<?= $isi ?: "<section class='hal'><p>Tidak ada dokumen untuk dicetak.</p></section>" ?>
</body></html>
