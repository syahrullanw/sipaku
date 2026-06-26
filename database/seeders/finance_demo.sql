-- -----------------------------------------------------
-- Seeder contoh modul keuangan
-- -----------------------------------------------------

INSERT INTO kategori_tagihan (kode, nama, tipe, status, urutan, created_at, updated_at)
VALUES
    ('SPP', 'Iuran SPP Bulanan', 'rutin', 'aktif', 1, NOW(), NOW()),
    ('KOMITE', 'Iuran Komite Sekolah', 'rutin', 'aktif', 2, NOW(), NOW()),
    ('SERAGAM', 'Pembelian Seragam', 'insidental', 'aktif', 3, NOW(), NOW()),
    ('PEMBELIAN', 'Pembelian Perlengkapan', 'insidental', 'aktif', 4, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    nama = VALUES(nama),
    tipe = VALUES(tipe),
    status = VALUES(status),
    urutan = VALUES(urutan),
    updated_at = VALUES(updated_at);

INSERT INTO arus_kas (kode_transaksi, tipe, sumber, tanggal, nominal, saldo_setelah, keterangan, dicatat_oleh, created_at, updated_at)
VALUES
    ('CF/INIT/0001', 'masuk', 'penyesuaian', NOW(), 0.00, 0.00, 'Saldo awal kas', NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    updated_at = VALUES(updated_at);
