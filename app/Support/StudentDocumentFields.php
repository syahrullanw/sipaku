<?php

namespace App\Support;

class StudentDocumentFields
{
    /**
     * @return array<string, array{label: string, column: string, input: string}>
     */
    public static function all(): array
    {
        return [
            'ijazah' => [
                'label' => 'Ijazah SMP/MTs Asal',
                'column' => 'scan_ijazah_path',
                'input' => 'scan_ijazah',
            ],
            'rapor' => [
                'label' => 'Raport SMP/MTs Asal',
                'column' => 'scan_rapor_path',
                'input' => 'scan_rapor',
            ],
            'kartu-keluarga' => [
                'label' => 'Kartu Keluarga',
                'column' => 'scan_kartu_keluarga_path',
                'input' => 'scan_kartu_keluarga',
            ],
            'akta' => [
                'label' => 'Akte Kelahiran',
                'column' => 'scan_akta_lahir_path',
                'input' => 'scan_akta_lahir',
            ],
            'ktp-ayah' => [
                'label' => 'KTP Orang Tua (Ayah)',
                'column' => 'scan_ktp_ayah_path',
                'input' => 'scan_ktp_ayah',
            ],
            'ktp-ibu' => [
                'label' => 'KTP Orang Tua (Ibu)',
                'column' => 'scan_ktp_ibu_path',
                'input' => 'scan_ktp_ibu',
            ],
        ];
    }
}
