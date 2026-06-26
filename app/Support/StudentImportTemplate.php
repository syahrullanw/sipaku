<?php

namespace App\Support;

class StudentImportTemplate
{
    private const REQUIRED_COLUMNS = [
        'nama',
        'nisn',
        'nik',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'ayah_nama',
        'ibu_nama',
        'hp',
    ];

    private const OPTIONAL_COLUMNS = [
        'agama',
        'alamat',
        'telepon',
        'email',
        'ayah_tahun_lahir',
        'ayah_pekerjaan',
        'ayah_penghasilan',
        'ibu_tahun_lahir',
        'ibu_pekerjaan',
        'ibu_penghasilan',
        'wali_nama',
        'wali_tahun_lahir',
        'wali_pekerjaan',
        'wali_penghasilan',
        'sekolah_asal',
    ];

    /**
     * @return array<int, string>
     */
    public static function headers(): array
    {
        return array_merge(self::REQUIRED_COLUMNS, self::OPTIONAL_COLUMNS);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function requiredHeaderAliases(): array
    {
        return [
            'nama' => ['nama', 'nama_lengkap'],
            'nisn' => ['nisn'],
            'nik' => ['nik'],
            'jenis_kelamin' => ['jenis_kelamin', 'jk', 'gender'],
            'tempat_lahir' => ['tempat_lahir'],
            'tanggal_lahir' => ['tanggal_lahir', 'tgl_lahir'],
            'ayah_nama' => ['ayah_nama', 'nama_ayah', 'nama_ayah_kandung'],
            'ibu_nama' => ['ibu_nama', 'nama_ibu', 'nama_ibu_kandung'],
            'hp' => [
                'hp',
                'no_hp',
                'nomor_hp',
                'no_hp_siswa',
                'nomor_hp_siswa',
                'handphone',
                'no_handphone',
                'nomor_handphone',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function nipdHeaderAliases(): array
    {
        return ['nipd', 'nomor_induk'];
    }

    public static function buildXlsx(): string
    {
        $rows = [self::headers()];
        $highlightCells = [];

        foreach (self::REQUIRED_COLUMNS as $index => $_column) {
            $highlightCells[] = self::columnLetter($index + 1) . '1';
        }

        return SimpleXlsxBuilder::buildSheets([
            [
                'name' => 'Data Siswa',
                'rows' => $rows,
                'options' => [
                    'highlight_cells' => $highlightCells,
                ],
            ],
            [
                'name' => 'Petunjuk',
                'rows' => self::instructionRows(),
                'options' => [
                    'highlight_cells' => ['A1', 'A4', 'B4', 'C4', 'D4', 'E4'],
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function isInstructionRow(array $row): bool
    {
        $name = trim((string) ($row['nama'] ?? ''));

        return str_starts_with($name, '#');
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function instructionRows(): array
    {
        return [
            [
                'PETUNJUK IMPORT SISWA',
                'Isi data hanya di sheet Data Siswa. Jangan ubah nama kolom header di baris pertama.',
            ],
            [],
            ['Status', 'Kolom', 'Format diterima sistem', 'Contoh', 'Keterangan'],
            ['Wajib', 'nama', 'Teks, wajib diisi', 'Aulia Rahman', 'Nama lengkap siswa.'],
            ['Otomatis', 'nipd', 'Tidak perlu diisi di template', '25261001', 'Sistem membuat NIPD otomatis: tahun ajaran + kode 1 siswa reguler + nomor urut. Kolom NIPD lama tetap dibaca hanya untuk mencocokkan data.'],
            ['Wajib', 'nisn', 'Tepat 10 digit angka, simpan sebagai teks agar nol di depan tidak hilang', '0034567891', 'Sistem menolak NISN yang bukan 10 digit.'],
            ['Wajib', 'nik', 'Tepat 16 digit angka, simpan sebagai teks', '3201010101100001', 'Sistem menolak NIK yang bukan 16 digit.'],
            ['Wajib', 'jenis_kelamin', 'L atau P. Alias diterima: laki, laki-laki, pria, male, boy, perempuan, wanita, female, girl', 'L', 'Gunakan L untuk laki-laki dan P untuk perempuan.'],
            ['Wajib', 'tempat_lahir', 'Teks, wajib diisi', 'Bandung', 'Isi nama kota/kabupaten sesuai dokumen.'],
            ['Wajib', 'tanggal_lahir', 'Tanggal Excel atau teks: YYYY-MM-DD, DD-MM-YYYY, DD/MM/YYYY, YYYY/MM/DD, DD.MM.YYYY', '2010-07-25', 'Format paling aman: YYYY-MM-DD.'],
            ['Wajib', 'ayah_nama', 'Teks, wajib diisi', 'Dedi Rahman', 'Gunakan nama ayah kandung jika tersedia.'],
            ['Wajib', 'ibu_nama', 'Teks, wajib diisi', 'Siti Aminah', 'Gunakan nama ibu kandung jika tersedia.'],
            ['Wajib', 'hp', 'Teks/angka, wajib diisi', '081234567890', 'Nomor HP siswa/orang tua. Tidak ada validasi pola khusus.'],
            ['Opsional', 'telepon', 'Teks/angka', '022123456', 'Boleh kosong.'],
            ['Opsional', 'email', 'Alamat email valid', 'aulia@example.com', 'Email tidak valid akan dikosongkan oleh sistem.'],
            ['Opsional', 'ayah_tahun_lahir, ibu_tahun_lahir, wali_tahun_lahir', '4 digit tahun', '1980', 'Nilai selain 4 digit akan dikosongkan.'],
            ['Opsional', 'agama, alamat, pekerjaan, penghasilan, wali_nama, sekolah_asal', 'Teks bebas', 'Islam', 'Boleh kosong.'],
            [],
            ['CONTOH DATA SHEET DATA SISWA'],
            self::headers(),
            ['Aulia Rahman', '0034567891', '3201010101100001', 'L', 'Bandung', '2010-07-25', 'Dedi Rahman', 'Siti Aminah', '081234567890'],
        ];
    }

    private static function columnLetter(int $columnNumber): string
    {
        $letter = '';

        while ($columnNumber > 0) {
            $remainder = ($columnNumber - 1) % 26;
            $letter = chr(65 + $remainder) . $letter;
            $columnNumber = (int) (($columnNumber - $remainder) / 26);
        }

        return $letter;
    }
}
