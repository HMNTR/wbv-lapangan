<?php
// Inti aplikasi: koneksi DB, login per pengguna & peran, pengaturan, jejak aktivitas, CSRF, helper tampilan.
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Jakarta');

$CFG = require __DIR__ . '/config.php';
const APP_VERSI = '3.0';
const PERAN = ['admin' => 'Admin', 'ketua' => 'Ketua Penelitian', 'peneliti' => 'Peneliti', 'bangprod' => 'Pendamping Pindad (Bangprod)', 'qa' => 'Pendamping QA'];
const EDITOR = ['admin', 'ketua', 'peneliti'];          // boleh mengisi/mengubah formulir
// Slot persetujuan elektronik per formulir (urutan = urutan kolom tanda tangan saat dicetak)
const FORM_SLOT = [
    'f4'       => ['ketua', 'peneliti', 'bangprod'],
    'f6_pagi'  => ['peneliti', 'bangprod', 'qa'],
    'f6_siang' => ['peneliti', 'bangprod', 'qa'],
    'f7'       => ['ketua', 'peneliti', 'bangprod'],
];
const FORM_LABEL = ['f4' => 'F-4 Kelaikan', 'f6_pagi' => 'F-6 Log pagi', 'f6_siang' => 'F-6 Log siang', 'f7' => 'F-7 Pasca-sesi'];

function db(): PDO {
    static $pdo = null;
    global $CFG;
    if ($pdo === null) {
        $dsn = "mysql:host={$CFG['db_host']};port={$CFG['db_port']};dbname={$CFG['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $CFG['db_user'], $CFG['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET time_zone = '+07:00'");
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }

function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function post(string $k, $def = null) { $v = $_POST[$k] ?? $def; return ($v === '' ? null : $v); }
function intOrNull($v): ?int { return ($v === null || $v === '') ? null : (int)$v; }
function redirect(string $url): void { header("Location: $url"); exit; }
function flash(?string $msg = null, string $type = 'ok') {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}

// ---- pengaturan (tabel `pengaturan`, cadangan dari config.php) ----
function setting(string $k) {
    global $CFG;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try { foreach (rows("SELECT kunci, nilai FROM pengaturan") as $r) $cache[$r['kunci']] = $r['nilai']; } catch (PDOException $e) {}
    }
    return $cache[$k] ?? ($CFG['batas'][$k] ?? ($CFG[$k] ?? null));
}
function batas(): array {
    $o = [];
    foreach (['td_sistol_max', 'td_diastol_max', 'nadi_min', 'nadi_max', 'tidur_min_jam', 'kss_tunda'] as $k) $o[$k] = (float)setting($k);
    return $o;
}
function nilai_kompensasi(): int { return (int)preg_replace('/\D/', '', (string)setting('kompensasi')); }

// ---- pengguna & peran ----
function user(): ?array { return $_SESSION['user'] ?? null; }
function uid(): ?int { return isset($_SESSION['user']) ? (int)$_SESSION['user']['id'] : null; }
function is_role(string ...$r): bool { $u = user(); return $u && in_array($u['peran'], $r, true); }
function require_login(): void {
    if (!user()) redirect('login.php');
    if (!empty($_SESSION['user']['wajib_ganti']) && basename($_SERVER['SCRIPT_NAME']) !== 'akun.php') {
        flash('Silakan ganti kata sandi bawaan terlebih dahulu.', 'err');
        redirect('akun.php');
    }
}
function can_edit(): bool { return is_role(...EDITOR); }
function require_editor(): void { require_role(...EDITOR); }
function require_role(string ...$r): void {
    require_login();
    if (!is_role(...$r)) { http_response_code(403); page_head('Akses ditolak'); echo "<div class='alert bad'>Halaman ini hanya untuk peran: " . h(implode(', ', array_map(fn($x) => PERAN[$x], $r))) . ".</div>"; page_foot(); exit; }
}
function audit(string $aksi, string $objek = '', ?int $kid = null): void {
    try { q("INSERT INTO aktivitas (user_id, aksi, objek, kunjungan_id, ip) VALUES (?,?,?,?,?)", [uid(), $aksi, mb_substr($objek, 0, 250), $kid, $_SERVER['REMOTE_ADDR'] ?? null]); } catch (PDOException $e) {}
}

// ---- CSRF ----
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('Token formulir tidak valid. Muat ulang halaman.');
    }
}

// ---- komponen form ----
function yn(string $name, $val, string $ya = 'Ya', string $tidak = 'Tidak'): string {
    $o = '';
    foreach (['1' => $ya, '0' => $tidak] as $k => $lbl) {
        $c = ((string)$val === (string)$k && $val !== null) ? ' checked' : '';
        $o .= "<label class='chk'><input type='radio' name='$name' value='$k'$c> $lbl</label>";
    }
    return $o;
}
function opt(string $name, $val, array $opts): string {
    $o = '';
    foreach ($opts as $k => $lbl) {
        $c = ((string)$val === (string)$k) ? ' checked' : '';
        $o .= "<label class='chk'><input type='radio' name='$name' value='" . h($k) . "'$c> " . h($lbl) . "</label>";
    }
    return $o;
}
function kss_select(string $name, $val, string $extra = ''): string {
    $o = "<select name='$name' class='kss' $extra><option value=''>–</option>";
    for ($i = 1; $i <= 9; $i++) { $s = ((string)$val === (string)$i) ? ' selected' : ''; $o .= "<option$s>$i</option>"; }
    return $o . '</select>';
}
function vdv_zona($v): string {
    if ($v === null || $v === '') return '';
    $v = (float)$v;
    return $v < 8.5 ? '< 8,5 (di bawah zona kehati-hatian)' : ($v < 17 ? '8,5–17 (zona kehati-hatian ISO 2631-1)' : '≥ 17 (di atas zona kehati-hatian)');
}
function kunjungan_or_404(int $id): array {
    $k = row("SELECT k.*, s.kode FROM kunjungan k JOIN subjek s ON s.id=k.subjek_id WHERE k.id=?", [$id]);
    if (!$k) { http_response_code(404); exit('Kunjungan tidak ditemukan.'); }
    return $k;
}
function fmt_tgl(?string $d): string {
    if (!$d) return '';
    $b = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    [$y, $m, $dd] = explode('-', substr($d, 0, 10));
    return (int)$dd . ' ' . $b[(int)$m] . ' ' . $y;
}
function jam(?string $t): string { return $t ? substr($t, 0, 5) : ''; }
function nama_user(?int $id): string {
    static $c = [];
    if (!$id) return '';
    if (!isset($c[$id])) $c[$id] = (string)(row("SELECT nama FROM users WHERE id=?", [$id])['nama'] ?? '');
    return $c[$id];
}

// ---- layout ----
function page_head(string $title, ?array $k = null): void {
    $f = flash();
    $u = user();
    echo "<!doctype html><html lang='id'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
    echo "<title>" . h($title) . " · WBV Lapangan</title><link rel='stylesheet' href='style.css'></head><body>";
    echo "<header class='top'><a href='index.php' class='brand'>WBV Lapangan</a><span class='etik'>Etik KEPK UNPRI " . h(setting('no_etik')) . "</span>";
    if ($u) {
        echo "<nav><a href='index.php'>Dashboard</a><a href='kunjungan_list.php'>Kunjungan</a><a href='kss.php'>Kartu KSS</a>";
        if (can_edit()) echo "<a href='ekspor.php'>Ekspor</a>";
        if (is_role('admin')) echo "<a href='admin.php'>Admin</a>";
        echo "<a href='akun.php' class='who'>" . h($u['nama']) . " · " . h(PERAN[$u['peran']] ?? $u['peran']) . "</a><a href='logout.php'>Keluar</a></nav>";
    }
    echo "</header><main>";
    if ($k) {
        $id = (int)$k['id'];
        echo "<div class='crumb'><b>" . h($k['kode']) . "</b> · Kunjungan " . h($k['ke']) . " · " . h(fmt_tgl($k['tanggal'])) . " · " . h($k['skenario']) . "</div>";
        echo "<div class='tabs'><a href='kunjungan.php?id=$id'>Ringkasan</a><a href='kelaikan.php?k=$id'>F-4 Kelaikan</a>";
        echo "<a href='log.php?k=$id&sesi=pagi'>F-6 Pagi</a><a href='log.php?k=$id&sesi=siang'>F-6 Siang</a><a href='pasca.php?k=$id'>F-7 Pasca-sesi</a>";
        echo "<a href='cetak.php?k=$id' target='_blank'>🖨 Cetak</a></div>";
    }
    echo "<h1>" . h($title) . "</h1>";
    if ($f) echo "<div class='flash {$f[1]}'>" . h($f[0]) . "</div>";
}
function page_foot(): void { echo "</main><footer class='foot'>WBV Lapangan v" . APP_VERSI . "</footer><script src='app.js'></script></body></html>"; }

// ---- persetujuan elektronik formulir ----
function form_terisi(int $kid, string $form): bool {
    if ($form === 'f4') return (bool)row("SELECT 1 FROM kelaikan WHERE kunjungan_id=? AND keputusan IS NOT NULL", [$kid]);
    if ($form === 'f7') return (bool)row("SELECT 1 FROM pasca WHERE kunjungan_id=?", [$kid]);
    $sesi = substr($form, 3);
    return (bool)row("SELECT 1 FROM log_baris l JOIN rundown r ON r.id=l.rundown_id WHERE l.kunjungan_id=? AND r.sesi=? AND (l.jam_mulai IS NOT NULL OR l.kss IS NOT NULL) LIMIT 1", [$kid, $sesi]);
}
function form_diubah(int $kid, string $form): ?string {
    if ($form === 'f4') return row("SELECT updated_at t FROM kelaikan WHERE kunjungan_id=?", [$kid])['t'] ?? null;
    if ($form === 'f7') return row("SELECT updated_at t FROM pasca WHERE kunjungan_id=?", [$kid])['t'] ?? null;
    $sesi = substr($form, 3);
    return row("SELECT GREATEST(COALESCE((SELECT MAX(l.updated_at) FROM log_baris l JOIN rundown r ON r.id=l.rundown_id WHERE l.kunjungan_id=? AND r.sesi=? AND (l.jam_mulai IS NOT NULL OR l.kss IS NOT NULL OR l.catatan IS NOT NULL)), '2000-01-01'),
                COALESCE((SELECT MAX(created_at) FROM henti WHERE kunjungan_id=?), '2000-01-01')) t", [$kid, $sesi, $kid])['t'] ?? null;
}
function persetujuan(int $kid, string $form): array {
    $o = [];
    foreach (rows("SELECT * FROM persetujuan_form WHERE kunjungan_id=? AND form=?", [$kid, $form]) as $r) $o[$r['slot']] = $r;
    return $o;
}
function panel_persetujuan(int $kid, string $form): string {
    $ok = persetujuan($kid, $form); $ubah = form_diubah($kid, $form); $isi = form_terisi($kid, $form);
    $o = "<div class='card persetujuan'><h2 style='margin-top:0'>Persetujuan elektronik — " . h(FORM_LABEL[$form]) . "</h2>";
    if (!$isi) $o .= "<p class='muted'>Formulir belum diisi; persetujuan dapat diberikan setelah ada isian.</p>";
    $o .= "<div class='slots'>";
    foreach (FORM_SLOT[$form] as $slot) {
        $o .= "<div class='slot'><div class='muted'>" . h(PERAN[$slot]) . "</div>";
        if (isset($ok[$slot])) {
            $a = $ok[$slot];
            $o .= "<div class='pill ok'>✓ disetujui</div><div><b>" . h($a['nama']) . "</b><br><span class='muted'>" . h($a['jabatan']) . "<br>" . h(date('d-m-Y H:i', strtotime($a['waktu']))) . "</span></div>";
            if ($ubah && $ubah > $a['waktu']) $o .= "<div class='pill warn'>data berubah setelah disetujui</div>";
            if ((int)$a['user_id'] === uid() || is_role('admin'))
                $o .= "<form method='post' action='persetujuan.php'>" . csrf_field() . "<input type='hidden' name='aksi' value='batal'><input type='hidden' name='k' value='$kid'><input type='hidden' name='form' value='$form'><input type='hidden' name='slot' value='$slot'><input type='hidden' name='kembali' value='" . h($_SERVER['REQUEST_URI']) . "'><button class='btn sm sec' onclick=\"return confirm('Batalkan persetujuan ini?')\">Batalkan</button></form>";
        } elseif ($isi && is_role($slot)) {
            $o .= "<form method='post' action='persetujuan.php'>" . csrf_field() . "<input type='hidden' name='aksi' value='setujui'><input type='hidden' name='k' value='$kid'><input type='hidden' name='form' value='$form'><input type='hidden' name='slot' value='$slot'><input type='hidden' name='kembali' value='" . h($_SERVER['REQUEST_URI']) . "'>"
               . "<label class='chk' style='white-space:normal'><input type='checkbox' name='yakin' value='1' required> Saya telah memeriksa isian dan menyetujuinya</label><br><button class='btn sm'>Setujui sebagai " . h(PERAN[$slot]) . "</button></form>";
        } else $o .= "<div class='pill'>menunggu</div>";
        $o .= "</div>";
    }
    return $o . "</div></div>";
}
