<?php

namespace Modules\Finance\Controllers\Guru;

use App\Models\Teacher;
use App\Models\TeacherSalaryComponent;
use App\Models\TeacherSalaryRecord;
use App\Services\Finance\TeacherSalarySlipExporter;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class SalaryController extends Controller
{
    public function slipPdf(Request $request, int $id): Response
    {
        if ($response = $this->guardRole('guru')) {
            return $response;
        }

        $user = $this->user();
        $teacherId = $user !== null ? (int) ($user['teacher_id'] ?? 0) : 0;

        if ($teacherId <= 0) {
            Session::flash('error', 'Akun guru tidak terhubung dengan data guru.');

            return $this->redirect('keuangan/guru');
        }

        $record = TeacherSalaryRecord::find($id);
        if ($record === null || (int) ($record['guru_id'] ?? 0) !== $teacherId) {
            Session::flash('error', 'Slip gaji tidak ditemukan.');

            return $this->redirect('keuangan/guru');
        }

        if ((string) ($record['status'] ?? 'draft') !== 'disbursed') {
            Session::flash('error', 'Slip gaji belum tersedia untuk periode ini.');

            return $this->redirect('keuangan/guru');
        }

        $teacher = Teacher::find($teacherId);
        $teacherName = (string) ($teacher['nama'] ?? 'Guru');
        $components = TeacherSalaryComponent::byRecord((int) $record['id']);

        try {
            $pdfBinary = TeacherSalarySlipExporter::generate($record, $teacher, $components);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal membuat PDF slip gaji: ' . $exception->getMessage());

            return $this->redirect('keuangan/guru');
        }

        $safeNameRaw = preg_replace('/[^A-Za-z0-9\-]/', '-', str_replace(['"', "'"], '', $teacherName));
        $safeName = $safeNameRaw !== null && $safeNameRaw !== '' ? $safeNameRaw : 'Guru';
        $period = (string) ($record['periode'] ?? 'Periode');
        $safePeriodRaw = preg_replace('/[^A-Za-z0-9\-]/', '-', $period !== '' ? $period : 'Periode');
        $safePeriod = $safePeriodRaw !== null && $safePeriodRaw !== '' ? $safePeriodRaw : 'Periode';
        $filename = 'Slip-Gaji-' . $safeName . '-' . $safePeriod . '.pdf';

        return Response::make($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($pdfBinary),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
