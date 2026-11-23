-- =====================================================================
-- SIMULASI 21 NOVEMBER 2026 — KOSONG, mengikuti skenario/rundown 24 November
-- Siap impor: phpMyAdmin > database wbv_lapangan > Import (aplikasi v3.2).
--   1. Menghapus semua kunjungan simulasi SIM01 (subjek nyata TIDAK disentuh).
--   2. Mengosongkan data demografis SIM01 (informed consent SIM01 tetap ada).
--   3. Menyusun ulang template rundown '21' = salinan rundown '24' terbaru.
--   4. Membuat 1 kunjungan SIM01 tanggal 21 November 2026 dengan log KOSONG.
-- Boleh diimpor ulang: setiap impor mengembalikan simulasi ke keadaan kosong.
-- =====================================================================
SET NAMES utf8mb4;
USE wbv_lapangan;
START TRANSACTION;

INSERT INTO subjek (kode, catatan)
SELECT 'SIM01', 'DATA SIMULASI — bukan subjek nyata' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM subjek WHERE kode='SIM01');
SET @sid := (SELECT id FROM subjek WHERE kode='SIM01');

DELETE FROM kunjungan WHERE subjek_id=@sid;

UPDATE subjek SET usia=NULL, jenis_kelamin=NULL, tinggi_cm=NULL, berat_kg=NULL, pengalaman_mv3_bulan=NULL,
       pengalaman_total_tahun=NULL, jam_mengemudi_minggu=NULL, tidur_rata_jam=NULL, kacamata=NULL,
       rokok_batang_hari=NULL, kopi_gelas_hari=NULL, mcu_terakhir=NULL,
       ic_tanggal=IF(EXISTS(SELECT 1 FROM informed_consent ic WHERE ic.subjek_id=@sid AND ic.setuju=1), ic_tanggal, NULL),
       catatan='DATA SIMULASI — bukan subjek nyata'
WHERE id=@sid;

-- template 21 disusun ulang dari rundown 24 terbaru (hanya baris yang tidak dipakai kunjungan lain)
DELETE FROM rundown WHERE hari='21' AND id NOT IN (SELECT rundown_id FROM log_baris);
INSERT INTO rundown (hari, sesi, urut, waktu, menit, kegiatan, keterangan, tipe, id_rekaman, jalur_v)
SELECT '21', r.sesi, r.urut, r.waktu, r.menit, r.kegiatan, r.keterangan, r.tipe, REPLACE(r.id_rekaman, 'DIS-MV3-24-', 'DIS-MV3-21-'), r.jalur_v
FROM rundown r
WHERE r.hari='24' AND (SELECT COUNT(*) FROM (SELECT id FROM rundown WHERE hari='21') x) = 0;

INSERT INTO kunjungan (subjek_id, ke, tanggal, template_hari, skenario, kendaraan, status)
VALUES (@sid, 1, '2026-11-21', '21', 'Simulasi — salinan rundown 24 (tim manekin)', 'MV3 Komando', 'rencana');
SET @kid := LAST_INSERT_ID();
INSERT INTO log_baris (kunjungan_id, rundown_id) SELECT @kid, id FROM rundown WHERE hari='21';

INSERT INTO aktivitas (user_id, aksi, objek, kunjungan_id) VALUES (NULL, 'impor simulasi kosong', 'SIM01 · 21 November 2026', @kid);
COMMIT;

-- HAPUS SIMULASI SEPENUHNYA (jalankan terpisah bila perlu):
-- DELETE FROM subjek WHERE kode='SIM01';
-- DELETE FROM rundown WHERE hari='21';
