-- Migrasi v3/v3.2 -> v3.3: jadwal dinamis per kunjungan (tidak menghapus data).
-- Opsional: aplikasi v3.3 juga menambahkan kolom ini otomatis saat halaman log dibuka.
SET NAMES utf8mb4;
USE wbv_lapangan;
ALTER TABLE log_baris ADD COLUMN IF NOT EXISTS jadwal_mulai TIME NULL;
ALTER TABLE log_baris ADD COLUMN IF NOT EXISTS jadwal_selesai TIME NULL;
ALTER TABLE log_baris ADD COLUMN IF NOT EXISTS menit_jadwal SMALLINT NULL;
