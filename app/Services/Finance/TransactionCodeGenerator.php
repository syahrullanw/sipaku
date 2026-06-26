<?php

namespace App\Services\Finance;

use RuntimeException;

class TransactionCodeGenerator
{
    /**
     * @param callable(string):bool|null $existsChecker
     */
    public static function generate(string $prefix, ?callable $existsChecker = null): string
    {
        $prefix = trim($prefix);

        if ($prefix === '') {
            throw new RuntimeException('Prefix kode transaksi tidak boleh kosong.');
        }

        $attempts = 0;

        do {
            $attempts++;
            $serial = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $code = sprintf('%s/%s/%s', strtoupper($prefix), date('Ymd'), $serial);

            if ($existsChecker === null || $existsChecker($code) === false) {
                return $code;
            }
        } while ($attempts < 10);

        throw new RuntimeException('Gagal menghasilkan kode transaksi unik setelah beberapa percobaan.');
    }
}
