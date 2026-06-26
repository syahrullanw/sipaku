ALTER TABLE pembelian_perlengkapan
    ADD COLUMN IF NOT EXISTS kode VARCHAR(60) NOT NULL AFTER id,
    MODIFY tagihan_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS nominal_terbayar DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER nominal,
    ADD COLUMN IF NOT EXISTS sisa_nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER nominal_terbayar,
    ADD COLUMN IF NOT EXISTS status ENUM('menunggu_pembayaran','cicilan_berjalan','lunas','dibatalkan') NOT NULL DEFAULT 'menunggu_pembayaran' AFTER sisa_nominal;

CREATE UNIQUE INDEX IF NOT EXISTS unique_pembelian_kode ON pembelian_perlengkapan (kode);
CREATE INDEX IF NOT EXISTS idx_pembelian_status ON pembelian_perlengkapan (status);

UPDATE pembelian_perlengkapan
SET kode = CONCAT('PB/', LPAD(id, 6, '0'))
WHERE kode IS NULL OR kode = '';

UPDATE pembelian_perlengkapan
SET nominal_terbayar = COALESCE(nominal_terbayar, 0.00),
    sisa_nominal = CASE
        WHEN (sisa_nominal IS NULL OR sisa_nominal = 0) AND COALESCE(nominal_terbayar, 0.00) = 0
            THEN nominal
        ELSE COALESCE(sisa_nominal, nominal - COALESCE(nominal_terbayar, 0.00))
    END;

UPDATE pembelian_perlengkapan
SET status = CASE
    WHEN sisa_nominal <= 0 THEN 'lunas'
    WHEN nominal_terbayar > 0 THEN 'cicilan_berjalan'
    ELSE 'menunggu_pembayaran'
END;

ALTER TABLE arus_kas
    MODIFY sumber ENUM('tagihan','tabungan','kasbon','kegiatan','honor','penyesuaian','kas_umum','tak_terduga','pembelian') NOT NULL;

ALTER TABLE kas_umum_transaksi
    MODIFY tujuan_tipe ENUM('kas_umum','tagihan','tabungan','kasbon','tak_terduga','honor','pembelian') NOT NULL;
