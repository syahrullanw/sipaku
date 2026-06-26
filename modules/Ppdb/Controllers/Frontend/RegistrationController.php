<?php

namespace Modules\Ppdb\Controllers\Frontend;

use App\Models\PpdbPeriod;
use App\Models\PpdbRegistrant;
use App\Services\PpdbRegistrationNotifier;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class RegistrationController extends Controller
{
    protected ?string $layout = 'app';

    public function show(Request $request, string $identifier): Response
    {
        $period = PpdbPeriod::findByIdentifier($identifier);

        if ($period === null || !PpdbPeriod::isStageEnabled($period, 'pendaftaran')) {
            Session::flash('error', 'Periode PPDB tidak ditemukan atau pendaftaran sudah ditutup.');

            return $this->redirect('login');
        }

        return $this->render('ppdb/public/registration', [
            'title' => 'Pendaftaran PPDB',
            'period' => $period,
            'successMessage' => session_flash('success'),
            'errorMessage' => session_flash('error'),
            'identifier' => $identifier,
        ], 'app');
    }

    public function submit(Request $request, string $identifier): Response
    {
        $period = PpdbPeriod::findByIdentifier($identifier);

        if ($period === null || !PpdbPeriod::isStageEnabled($period, 'pendaftaran')) {
            Session::flash('error', 'Pendaftaran tidak tersedia untuk periode ini.');

            return $this->redirect('ppdb/pendaftaran/' . $identifier);
        }

        if ($response = $this->guardCsrf($request, 'ppdb/pendaftaran/' . $identifier)) {
            return $response;
        }

        $payload = [
            'nama_lengkap' => trim((string) $request->input('nama_lengkap', '')),
            'jenis_kelamin' => (string) $request->input('jenis_kelamin', ''),
            'tempat_lahir' => trim((string) $request->input('tempat_lahir', '')),
            'tanggal_lahir' => (string) $request->input('tanggal_lahir', ''),
            'nik' => trim((string) $request->input('nik', '')),
            'nisn' => trim((string) $request->input('nisn', '')),
            'asal_sekolah' => trim((string) $request->input('asal_sekolah', '')),
            'alamat' => trim((string) $request->input('alamat', '')),
            'telepon' => trim((string) $request->input('telepon', '')),
            'email' => trim((string) $request->input('email', '')),
            'nama_wali' => trim((string) $request->input('nama_wali', '')),
            'telepon_wali' => trim((string) $request->input('telepon_wali', '')),
        ];

        if ($payload['nama_lengkap'] === '' || !in_array($payload['jenis_kelamin'], ['L', 'P'], true)) {
            Session::flash('error', 'Nama lengkap dan jenis kelamin wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/pendaftaran/' . $identifier);
        }

        if ($payload['tanggal_lahir'] !== '' && strtotime($payload['tanggal_lahir']) === false) {
            Session::flash('error', 'Tanggal lahir tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/pendaftaran/' . $identifier);
        }

        if ($payload['tanggal_lahir'] === '') {
            $payload['tanggal_lahir'] = null;
        }

        $payload['sumber'] = 'mandiri';

        $newId = PpdbRegistrant::createForPeriod((int) $period['id'], $payload);

        if ($newId === null) {
            Session::flash('error', 'Gagal menyimpan data pendaftaran. Silakan coba lagi.');
            Session::flashInput($request->all());

            return $this->redirect('ppdb/pendaftaran/' . $identifier);
        }

        $registrant = PpdbRegistrant::find($newId);
        $code = $registrant['kode_pendaftaran'] ?? 'TIDAK-DIKETAHUI';

        $successMessage = 'Pendaftaran berhasil! Simpan kode pendaftaran Anda: ' . $code;

        if ($registrant !== null) {
            $notifyResult = PpdbRegistrationNotifier::notifyRegistrant($registrant, $period ?? []);
            $successMessage .= $notifyResult['success']
                ? ' ' . $notifyResult['message']
                : ' Notifikasi WhatsApp belum terkirim: ' . $notifyResult['message'];
        }

        Session::flash('success', $successMessage);

        return $this->redirect('ppdb/pendaftaran/' . $identifier);
    }
}
