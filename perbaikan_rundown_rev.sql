-- =====================================================================
-- PERBAIKAN WAKTU RUNDOWN (revisi dokumen rundown 24 & 25 November 2026)
-- Untuk database yang SUDAH terpasang (v3/v3.1). Instalasi baru cukup schema.sql v3.2.
-- Impor: phpMyAdmin > database wbv_lapangan > Import. Aman diimpor ulang.
--
-- 24 November (dan salinan simulasi 21):
--   * 10.21–10.41 Menanjak 4 km/jam & menurun: keterangan menjadi "5× naik-turun"
--   * Siang 13.46–13.48 (2′) menjadi 13.46–13.49 (3′)
--   Hanya mengubah teks baris; isian log yang sudah ada tetap aman.
-- 25 November (jadwal bergeser):
--   D2 10.25–11.05 (40′) · unduh 11.05–11.10 · D3 11.10–11.50 · unduh 11.50–11.55
--   Evaluasi 11.55–12.20 · persiapan Ronde 2 13.20–13.40 · D4 13.40–15.10
--   Unduh 15.10–15.20 · kejut tunggal 15.20–15.50 · closing 15.50–15.55
--   Kunjungan template 25 yang BELUM berisi log disusun ulang memakai rundown baru.
--   Kunjungan template 25 yang SUDAH berisi log tetap memakai rundown lama
--   (dipindah ke template "25L") sehingga datanya tidak hilang.
-- =====================================================================
SET NAMES utf8mb4;
USE wbv_lapangan;
START TRANSACTION;

-- ---------- 24 November (+ simulasi 21) ----------
UPDATE rundown SET keterangan='5× naik-turun'
 WHERE hari IN ('24','21') AND sesi='pagi' AND urut=6;
UPDATE rundown SET waktu='13.46–13.49', menit=3, kegiatan='Jeda ke kecepatan selanjutnya'
 WHERE hari IN ('24','21') AND sesi='siang' AND urut=4;

-- ---------- 25 November ----------
-- 1) Rundown lama -> 25L (bila revisi belum pernah dijalankan)
SET @sudah := (SELECT COUNT(*) FROM rundown WHERE hari='25' AND waktu='11.05–11.10');
UPDATE rundown SET hari='25L' WHERE hari='25' AND @sudah=0;

-- 2) Kunjungan 25 yang sudah berisi log tetap memakai rundown lama
UPDATE kunjungan k SET k.template_hari='25L'
 WHERE k.template_hari='25' AND @sudah=0 AND EXISTS (
   SELECT 1 FROM log_baris l WHERE l.kunjungan_id=k.id
     AND (l.jam_mulai IS NOT NULL OR l.jam_selesai IS NOT NULL OR l.kss IS NOT NULL OR l.v_aktual IS NOT NULL OR l.catatan IS NOT NULL));

-- 3) Rundown baru 25
INSERT INTO rundown (hari,sesi,urut,waktu,menit,kegiatan,keterangan,tipe,id_rekaman,jalur_v)
SELECT * FROM (
SELECT '25' AS hari,'pagi' AS sesi,1 AS urut,'09.20–09.30' AS waktu,10 AS menit,'Cek pra-uji + KSS awal (F-4)' AS kegiatan,'' AS keterangan,'j' AS tipe,NULL AS id_rekaman,NULL AS jalur_v
UNION ALL SELECT '25','pagi',2,'09.30–09.40',10,'Mobil nyala diam (data diam 10′, mesin menyala)','','r',NULL,NULL
UNION ALL SELECT '25','pagi',3,'09.40 — mulai',40,'D1 — Jalan datar U1, 20 & 40 km/jam (20′ per kecepatan)','','d','DIS-MV3-D1-{SUBJ}-R1','U1 · 20'
UNION ALL SELECT '25','pagi',4,'09.50',NULL,'','','d',NULL,'U1 · 20'
UNION ALL SELECT '25','pagi',5,'10.00',NULL,'','','d',NULL,'U1 · 40'
UNION ALL SELECT '25','pagi',6,'10.10',NULL,'','','d',NULL,'U1 · 40'
UNION ALL SELECT '25','pagi',7,'10.20 — selesai',NULL,'','','d',NULL,'U1 · 40'
UNION ALL SELECT '25','pagi',8,'10.20–10.25',5,'Unduh data D1 (jalan datar U1)','','j',NULL,NULL
UNION ALL SELECT '25','pagi',9,'10.25 — mulai',40,'D2 — Jalur datar U1 + balok sejajar U4, 20 km/jam (masing-masing 20′ tiap jalur)','','d','DIS-MV3-D2-{SUBJ}-R1','U1 · 20'
UNION ALL SELECT '25','pagi',10,'10.35',NULL,'','','d',NULL,'U1 · 20'
UNION ALL SELECT '25','pagi',11,'10.45',NULL,'','','d',NULL,'U4 · 20'
UNION ALL SELECT '25','pagi',12,'10.55',NULL,'','','d',NULL,'U4 · 20'
UNION ALL SELECT '25','pagi',13,'11.05 — selesai',NULL,'','','d',NULL,'U4 · 20'
UNION ALL SELECT '25','pagi',14,'11.05–11.10',5,'Unduh data D2 (U1 + U4)','','j',NULL,NULL
UNION ALL SELECT '25','pagi',15,'11.10 — mulai',40,'D3 — Kejut tunggal terkontrol U1 + U2 (miring 30°) + U4. v dicatat: U1 …… U2 …… U4 ……','','d','DIS-MV3-D3-{SUBJ}-R1',NULL
UNION ALL SELECT '25','pagi',16,'11.20',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','pagi',17,'11.30',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','pagi',18,'11.40',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','pagi',19,'11.50 — selesai',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','pagi',20,'11.50–11.55',5,'Unduh data D3 (kejut tunggal terkontrol)','','j',NULL,NULL
UNION ALL SELECT '25','pagi',21,'11.55–12.20',25,'Evaluasi dan persiapan ISHOMA','','x',NULL,NULL
UNION ALL SELECT '25','siang',1,'13.20–13.40',20,'Persiapan smart mannequin Ronde 2 · cek ulang KSS (F-4)','','j',NULL,NULL
UNION ALL SELECT '25','siang',2,'13.40 — mulai',90,'D4 — Paparan berkelanjutan U1 + U2 + U4 sampai ujung (90′). v dicatat: U1 …… U2 …… U4 ……','','d','DIS-MV3-D4-{SUBJ}-R1',NULL
UNION ALL SELECT '25','siang',3,'13.50',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','siang',4,'14.00',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','siang',5,'14.10',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','siang',6,'14.20',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','siang',7,'14.30',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','siang',8,'14.40',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','siang',9,'14.50',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','siang',10,'15.00',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','siang',11,'15.10 — selesai',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','siang',12,'15.10–15.20',10,'Unduh & backup data paparan berkelanjutan; pemulihan pengemudi','','j',NULL,NULL
UNION ALL SELECT '25','siang',13,'15.20 — mulai',30,'Kejut tunggal U1 + U2 + U4, 10 & 20 km/jam × 4 repetisi. Pembagian v: U1 …… U2 …… U4 ……','','d','DIS-MV3-D3b-{SUBJ}-R1',NULL
UNION ALL SELECT '25','siang',14,'15.30',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','siang',15,'15.40',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','siang',16,'15.50 — selesai',NULL,'','','d',NULL,NULL
UNION ALL SELECT '25','siang',17,'15.50–15.55',5,'Closing: backup data, cek kualitas data keempat skenario','','x',NULL,NULL
) x WHERE @sudah=0;

-- 4) Kunjungan 25 yang masih kosong: ganti baris log ke rundown baru
DELETE l FROM log_baris l JOIN kunjungan k ON k.id=l.kunjungan_id
 WHERE k.template_hari='25' AND @sudah=0;
INSERT INTO log_baris (kunjungan_id, rundown_id)
SELECT k.id, r.id FROM kunjungan k JOIN rundown r ON r.hari='25'
 WHERE k.template_hari='25' AND @sudah=0;

-- 5) Bersihkan rundown lama bila tidak dipakai kunjungan mana pun
DELETE FROM rundown WHERE hari='25L' AND id NOT IN (SELECT rundown_id FROM log_baris);

INSERT INTO aktivitas (user_id, aksi, objek) VALUES (NULL, 'perbaikan waktu rundown', IF(@sudah=0, 'rundown 24 & 25 direvisi', 'rundown 24 dicek; 25 sudah versi revisi'));
COMMIT;
