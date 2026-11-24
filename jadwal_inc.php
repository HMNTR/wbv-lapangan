<?php
// Jadwal dinamis per kunjungan: jam dihitung ulang dari jam mulai + durasi.
// Rundown (template) tidak diubah; hasil disimpan di log_baris (jadwal_mulai/jadwal_selesai/menit_jadwal).

// Tambah kolom otomatis bila database belum diperbarui (sekali per sesi login).
function jw_pastikan_kolom(): void {
    if (!empty($_SESSION['jw_kolom_ok'])) return;
    try {
        if (!row("SHOW COLUMNS FROM log_baris LIKE 'jadwal_mulai'")) {
            db()->exec("ALTER TABLE log_baris ADD COLUMN jadwal_mulai TIME NULL, ADD COLUMN jadwal_selesai TIME NULL, ADD COLUMN menit_jadwal SMALLINT NULL");
        }
        $_SESSION['jw_kolom_ok'] = 1;
    } catch (PDOException $e) { /* tanpa hak ALTER: jalankan migrasi_v3_ke_v3_3.sql */ }
}

function jw_min(string $hm): int { [$h, $m] = array_map('intval', preg_split('/[.:]/', trim($hm))); return $h * 60 + $m; }
function jw_fmt(?int $m): string { if ($m === null) return ''; $m = (($m % 1440) + 1440) % 1440; return sprintf('%02d.%02d', intdiv($m, 60), $m % 60); }
function jw_time(?int $m): ?string { return $m === null ? null : sprintf('%02d:%02d:00', intdiv($m, 60) % 24, $m % 60); }
function jw_dbmin(?string $t): ?int { return $t ? (int)substr($t, 0, 2) * 60 + (int)substr($t, 3, 2) : null; }

// "09.30–09.40" | "09.40 — mulai" | "09.50" | "10.20 — selesai"
function jw_parse(string $w): array {
    $w = str_replace('¹', '', $w);
    $tag = str_contains($w, 'mulai') ? 'mulai' : (str_contains($w, 'selesai') ? 'selesai' : null);
    preg_match_all('/(\d{1,2})[.:](\d{2})/', $w, $m, PREG_SET_ORDER);
    $a = isset($m[0]) ? $m[0][1] * 60 + $m[0][2] : null;
    $b = isset($m[1]) ? $m[1][1] * 60 + $m[1][2] : null;
    return ['a' => $a, 'b' => $b, 'tag' => $tag];
}

// jenis baris: 'span' (punya rentang/durasi), 'blok' (awal blok 10-menitan), 'titik' (titik KSS di dalam blok)
function jw_jenis(array $r): string {
    $p = jw_parse($r['waktu']);
    if ($p['tag'] === 'mulai') return 'blok';
    if ($p['b'] === null && $r['menit'] === null) return 'titik';
    return 'span';
}
function jw_durasi(array $r): int {
    if ($r['menit_jadwal'] !== null && $r['menit_jadwal'] !== '') return (int)$r['menit_jadwal'];
    if ($r['menit'] !== null) return (int)$r['menit'];
    $p = jw_parse($r['waktu']);
    return ($p['a'] !== null && $p['b'] !== null) ? $p['b'] - $p['a'] : 0;
}
function jw_mulai_sekarang(array $r): ?int { return jw_dbmin($r['jadwal_mulai'] ?? null) ?? jw_parse($r['waktu'])['a']; }

// Label waktu untuk tampilan/cetak (jadwal baru bila ada, selain itu rundown asli)
function jw_label(array $r): string {
    if (empty($r['jadwal_mulai'])) return (string)$r['waktu'];
    $a = jw_dbmin($r['jadwal_mulai']); $b = jw_dbmin($r['jadwal_selesai'] ?? null);
    $p = jw_parse($r['waktu']);
    if ($p['tag'] === 'mulai') return jw_fmt($a) . ' — mulai';
    if ($p['tag'] === 'selesai') return jw_fmt($a) . ' — selesai';
    return $b !== null ? jw_fmt($a) . '–' . jw_fmt($b) : jw_fmt($a);
}
function jw_berubah(array $r): bool { return !empty($r['jadwal_mulai']) && jw_label($r) !== str_replace('¹', '', (string)$r['waktu']); }

/**
 * Hitung ulang jadwal satu sesi.
 * $rows: baris sesi urut naik (berisi id, waktu, menit, menit_jadwal, jadwal_mulai, jadwal_selesai)
 * $anchorId: id log_baris tempat jam baru dimulai; $anchorMin: jam mulai baru (menit sejak 00.00)
 * $durasi: [id => menit] durasi baru (opsional)
 * Mengembalikan [id => ['m' => mulai, 's' => selesai|null, 'd' => durasi|null, 'luar' => bool]]
 */
function jw_hitung(array $rows, int $anchorId, int $anchorMin, array $durasi = []): array {
    $out = []; $aktif = false; $cur = null; $blok = null;
    foreach ($rows as $r) {
        if (isset($durasi[$r['id']]) && $durasi[$r['id']] !== '') $r['menit_jadwal'] = (int)$durasi[$r['id']];
        $j = jw_jenis($r);
        if (!$aktif && (int)$r['id'] === $anchorId) { $aktif = true; $cur = $anchorMin; }
        if (!$aktif) {   // sebelum titik geser: pertahankan jadwal yang berlaku
            $p = jw_parse($r['waktu']);
            $m = jw_mulai_sekarang($r);
            $s = jw_dbmin($r['jadwal_selesai'] ?? null) ?? ($p['b'] ?? ($m !== null && $j !== 'titik' ? $m + jw_durasi($r) : null));
            $out[$r['id']] = ['m' => $m, 's' => $j === 'titik' ? null : $s, 'd' => $j === 'titik' ? null : jw_durasi($r), 'luar' => false];
            if ($j === 'blok') $blok = ['a' => $m, 'b' => $m + jw_durasi($r), 'k' => 0];
            continue;
        }
        if ($j === 'blok') {
            $d = jw_durasi($r);
            $blok = ['a' => $cur, 'b' => $cur + $d, 'k' => 0];
            $out[$r['id']] = ['m' => $cur, 's' => $cur + $d, 'd' => $d, 'luar' => false];
            $cur += $d;
        } elseif ($j === 'titik') {
            if ($blok === null) { $out[$r['id']] = ['m' => $cur, 's' => null, 'd' => null, 'luar' => false]; continue; }
            $p = jw_parse($r['waktu']);
            $blok['k']++;
            $t = $p['tag'] === 'selesai' ? $blok['b'] : $blok['a'] + 10 * $blok['k'];
            $out[$r['id']] = ['m' => $t, 's' => null, 'd' => null, 'luar' => $p['tag'] !== 'selesai' && $t >= $blok['b']];
        } else {
            $d = jw_durasi($r);
            $blok = null;
            $out[$r['id']] = ['m' => $cur, 's' => $cur + $d, 'd' => $d, 'luar' => false];
            $cur += $d;
        }
    }
    return $out;
}
