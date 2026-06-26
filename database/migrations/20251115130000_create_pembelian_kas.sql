CREATE TABLE IF NOT EXISTS pembelian_kas (
    pembelian_id INT UNSIGNED PRIMARY KEY,
    saldo_masuk DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    saldo_keluar DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    saldo_akhir DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_pembelian_kas_pembelian FOREIGN KEY (pembelian_id) REFERENCES pembelian_perlengkapan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
