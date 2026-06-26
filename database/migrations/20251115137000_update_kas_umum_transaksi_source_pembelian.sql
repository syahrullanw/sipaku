ALTER TABLE kas_umum_transaksi
    MODIFY sumber_tipe ENUM('eksternal','tagihan','tabungan','kas_umum','pembelian') NOT NULL;
