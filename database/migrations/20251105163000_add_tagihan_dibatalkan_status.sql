ALTER TABLE tagihan
    MODIFY COLUMN status ENUM('draft','aktif','ditutup','dibatalkan') NOT NULL DEFAULT 'draft';

ALTER TABLE tagihan_item
    MODIFY COLUMN status ENUM('menunggu_pembayaran','menunggu_verifikasi','cicilan_berjalan','lunas','gagal','dibatalkan') NOT NULL DEFAULT 'menunggu_pembayaran';
