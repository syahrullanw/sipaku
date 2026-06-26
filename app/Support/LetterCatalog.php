<?php

namespace App\Support;

class LetterCatalog
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function outgoingTypes(): array
    {
        return [
            'sk' => ['code' => '01', 'name' => 'Surat Keputusan', 'abbr' => 'SK'],
            'su' => ['code' => '02', 'name' => 'Surat Undangan', 'abbr' => 'SU'],
            'spm' => ['code' => '03', 'name' => 'Surat Permohonan', 'abbr' => 'SPm'],
            'spb' => ['code' => '04', 'name' => 'Surat Pemberitahuan', 'abbr' => 'SPb'],
            'spp' => ['code' => '05', 'name' => 'Surat Peminjaman', 'abbr' => 'SPp'],
            'spn' => ['code' => '06', 'name' => 'Surat Pernyataan', 'abbr' => 'SPn'],
            'sm' => ['code' => '07', 'name' => 'Surat Mandat', 'abbr' => 'SM'],
            'st' => ['code' => '08', 'name' => 'Surat Tugas', 'abbr' => 'ST'],
            'sket' => ['code' => '09', 'name' => 'Surat Keterangan', 'abbr' => 'SKet'],
            'sr' => ['code' => '10', 'name' => 'Surat Rekomendasi', 'abbr' => 'SR'],
            'sb' => ['code' => '11', 'name' => 'Surat Balasan', 'abbr' => 'SB'],
            'sppd' => ['code' => '12', 'name' => 'Surat Perintah Perjalanan Dinas', 'abbr' => 'SPPD'],
            'srt' => ['code' => '13', 'name' => 'Sertifikat', 'abbr' => 'SRT'],
            'pk' => ['code' => '14', 'name' => 'Perjanjian Kerja', 'abbr' => 'PK'],
            'speng' => ['code' => '15', 'name' => 'Surat Pengantar', 'abbr' => 'SPeng'],
            'skab' => ['code' => '16', 'name' => 'Surat Keterangan Aktif Belajar', 'abbr' => 'SKAB'],
            'spsp' => ['code' => '17', 'name' => 'Surat Penerimaan Siswa Pindahan', 'abbr' => 'SPSP'],
            'sps' => ['code' => '18', 'name' => 'Surat Peringatan Siswa', 'abbr' => 'SPS'],
            'spk' => ['code' => '19', 'name' => 'Surat Pemberitahuan Kegiatan', 'abbr' => 'SPK'],
            'smut' => ['code' => '20', 'name' => 'Surat Mutasi / Pindah Siswa', 'abbr' => 'SMut'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function commonTemplates(): array
    {
        return [
            'aktif_belajar' => [
                'label' => 'Surat Keterangan Aktif Belajar',
                'type' => 'skab',
                'recipient' => 'Yang berkepentingan',
                'subject' => 'Keterangan Aktif Belajar',
                'attachment' => '-',
                'carbon_copy' => '',
                'body' => <<<'HTML'
<p>Yang bertanda tangan di bawah ini, Kepala Sekolah, menerangkan bahwa:</p>
<table>
    <tbody>
        <tr><td>Nama</td><td>: [Nama Siswa]</td></tr>
        <tr><td>NISN</td><td>: [NISN]</td></tr>
        <tr><td>Kelas</td><td>: [Kelas]</td></tr>
        <tr><td>Tempat, Tanggal Lahir</td><td>: [Tempat, Tanggal Lahir]</td></tr>
        <tr><td>Nama Orang Tua/Wali</td><td>: [Nama Orang Tua/Wali]</td></tr>
    </tbody>
</table>
<p>Benar siswa tersebut masih aktif belajar pada sekolah ini pada tahun ajaran [Tahun Ajaran].</p>
<p>Surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya.</p>
HTML,
            ],
            'penerimaan_siswa_pindahan' => [
                'label' => 'Surat Penerimaan Siswa Pindahan',
                'type' => 'spsp',
                'recipient' => 'Orang Tua/Wali Siswa',
                'subject' => 'Penerimaan Siswa Pindahan',
                'attachment' => '-',
                'carbon_copy' => '',
                'body' => <<<'HTML'
<p>Dengan hormat,</p>
<p>Berdasarkan permohonan pindah sekolah yang telah diterima dan hasil verifikasi administrasi, dengan ini kami menyatakan menerima siswa pindahan berikut:</p>
<table>
    <tbody>
        <tr><td>Nama</td><td>: [Nama Siswa]</td></tr>
        <tr><td>NISN</td><td>: [NISN]</td></tr>
        <tr><td>Asal Sekolah</td><td>: [Asal Sekolah]</td></tr>
        <tr><td>Diterima di Kelas</td><td>: [Kelas Tujuan]</td></tr>
        <tr><td>Tahun Ajaran</td><td>: [Tahun Ajaran]</td></tr>
    </tbody>
</table>
<p>Siswa tersebut dapat mulai mengikuti kegiatan pembelajaran terhitung sejak tanggal [Tanggal Mulai Masuk] setelah melengkapi persyaratan administrasi yang berlaku.</p>
<p>Demikian surat ini disampaikan untuk diketahui dan dipergunakan sebagaimana mestinya.</p>
HTML,
            ],
            'peringatan_siswa' => [
                'label' => 'Surat Peringatan Siswa',
                'type' => 'sps',
                'recipient' => 'Orang Tua/Wali Siswa',
                'subject' => 'Peringatan Siswa',
                'attachment' => '-',
                'carbon_copy' => 'Wali Kelas',
                'body' => <<<'HTML'
<p>Dengan hormat,</p>
<p>Berdasarkan hasil pembinaan dan catatan kedisiplinan, sekolah memberikan surat peringatan kepada siswa berikut:</p>
<table>
    <tbody>
        <tr><td>Nama</td><td>: [Nama Siswa]</td></tr>
        <tr><td>NISN</td><td>: [NISN]</td></tr>
        <tr><td>Kelas</td><td>: [Kelas]</td></tr>
        <tr><td>Pelanggaran</td><td>: [Uraian Pelanggaran]</td></tr>
    </tbody>
</table>
<p>Kami mengharapkan perhatian dan kerja sama Orang Tua/Wali agar siswa tersebut memperbaiki sikap, kedisiplinan, dan tanggung jawabnya selama mengikuti kegiatan sekolah.</p>
<p>Apabila pelanggaran serupa terulang kembali, sekolah akan memberikan pembinaan lanjutan sesuai tata tertib yang berlaku.</p>
<p>Demikian surat peringatan ini disampaikan untuk menjadi perhatian.</p>
HTML,
            ],
            'pemberitahuan_kegiatan' => [
                'label' => 'Surat Pemberitahuan Kegiatan',
                'type' => 'spk',
                'recipient' => 'Orang Tua/Wali Siswa',
                'subject' => 'Pemberitahuan Kegiatan',
                'attachment' => '-',
                'carbon_copy' => '',
                'body' => <<<'HTML'
<p>Dengan hormat,</p>
<p>Sehubungan dengan pelaksanaan kegiatan sekolah, kami memberitahukan bahwa kegiatan akan dilaksanakan dengan rincian sebagai berikut:</p>
<table>
    <tbody>
        <tr><td>Nama Kegiatan</td><td>: [Nama Kegiatan]</td></tr>
        <tr><td>Hari/Tanggal</td><td>: [Hari, Tanggal]</td></tr>
        <tr><td>Waktu</td><td>: [Waktu]</td></tr>
        <tr><td>Tempat</td><td>: [Tempat]</td></tr>
        <tr><td>Peserta</td><td>: [Peserta]</td></tr>
    </tbody>
</table>
<p>Berkenaan dengan hal tersebut, kami mengharapkan dukungan dan kerja sama Bapak/Ibu agar kegiatan dapat berjalan dengan tertib dan lancar.</p>
<p>Demikian pemberitahuan ini kami sampaikan. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.</p>
HTML,
            ],
            'mutasi_pindah_siswa' => [
                'label' => 'Surat Mutasi / Pindah Siswa',
                'type' => 'smut',
                'recipient' => 'Kepala Sekolah Tujuan',
                'subject' => 'Mutasi / Pindah Siswa',
                'attachment' => '-',
                'carbon_copy' => 'Orang Tua/Wali Siswa',
                'body' => <<<'HTML'
<p>Dengan hormat,</p>
<p>Yang bertanda tangan di bawah ini, Kepala Sekolah, menerangkan bahwa siswa berikut:</p>
<table>
    <tbody>
        <tr><td>Nama</td><td>: [Nama Siswa]</td></tr>
        <tr><td>NISN</td><td>: [NISN]</td></tr>
        <tr><td>Kelas</td><td>: [Kelas Terakhir]</td></tr>
        <tr><td>Tempat, Tanggal Lahir</td><td>: [Tempat, Tanggal Lahir]</td></tr>
        <tr><td>Nama Orang Tua/Wali</td><td>: [Nama Orang Tua/Wali]</td></tr>
    </tbody>
</table>
<p>Telah mengajukan mutasi/pindah sekolah ke [Nama Sekolah Tujuan] dengan alasan [Alasan Pindah].</p>
<p>Selama menjadi siswa di sekolah ini, yang bersangkutan tercatat sebagai siswa pada tahun ajaran [Tahun Ajaran] dan telah menyelesaikan kewajiban administrasi sesuai ketentuan sekolah.</p>
<p>Demikian surat mutasi/pindah siswa ini dibuat untuk dipergunakan sebagaimana mestinya.</p>
HTML,
            ],
        ];
    }

    public static function displayLabel(array $entry): string
    {
        $name = $entry['name'] ?? '';
        $abbr = $entry['abbr'] ?? '';

        if ($name === '') {
            return $abbr;
        }

        if ($abbr === '') {
            return $name;
        }

        return sprintf('%s (%s)', $name, $abbr);
    }

    public static function findByCode(string $code): ?array
    {
        $normalized = strtolower(trim($code));

        if ($normalized === '') {
            return null;
        }

        foreach (static::outgoingTypes() as $key => $entry) {
            if (strtolower($entry['code'] ?? '') === $normalized) {
                return $entry + ['key' => $key];
            }
        }

        return null;
    }

    public static function find(string $key): ?array
    {
        $types = static::outgoingTypes();
        $normalized = strtolower(trim($key));

        if (isset($types[$normalized])) {
            return $types[$normalized] + ['key' => $normalized];
        }

        return null;
    }
}
