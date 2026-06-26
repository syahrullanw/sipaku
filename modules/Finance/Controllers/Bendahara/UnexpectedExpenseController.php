<?php

namespace Modules\Finance\Controllers\Bendahara;

use App\Models\Teacher;
use App\Models\Student;
use App\Models\UnexpectedExpense;
use App\Services\Finance\GeneralCashService;
use App\Services\Finance\UnexpectedExpenseService;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class UnexpectedExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();
        $hasActiveYear = $schoolYearId !== null;

        $filterTeacherId = (int) ($request->query('teacher_id') ?? 0);
        if ($filterTeacherId < 0) {
            $filterTeacherId = 0;
        }

        $teacherOptions = Teacher::options(false, $filterTeacherId > 0 ? $filterTeacherId : null);
        $studentOptions = [];
        $expenses = [];
        $balance = 0.0;

        if ($hasActiveYear) {
            $balance = GeneralCashService::balance($schoolYearId);
            $expenses = UnexpectedExpense::recent($schoolYearId, 20, $filterTeacherId > 0 ? $filterTeacherId : null);
            $studentOptions = Student::options(null, $schoolYearId);
        }

        return $this->render('finance/bendahara/unexpected-expenses/index', [
            'title' => 'Pengeluaran Tak Terduga',
            'pageTitle' => 'Pengeluaran Tak Terduga',
            'activeMenu' => 'finance-bendahara-unexpected-expenses',
            'hasActiveYear' => $hasActiveYear,
            'teacherOptions' => $teacherOptions,
            'expenses' => $expenses,
            'filters' => [
                'teacher_id' => $filterTeacherId,
            ],
            'generalCashBalance' => $balance,
            'defaultRecordedAt' => date('Y-m-d\TH:i'),
            'studentOptions' => $studentOptions,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $redirect = 'keuangan/bendahara/pengeluaran-tak-terduga';
        $selectedTeacher = (int) ($request->input('teacher_id') ?? 0);

        if ($selectedTeacher > 0) {
            $redirect .= '?teacher_id=' . $selectedTeacher;
        }

        if ($response = $this->guardCsrfOrRedirect($request, $redirect)) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        if ($schoolYearId === null) {
            Session::flash('error', 'Tahun ajaran aktif belum ditetapkan. Tidak dapat mencatat pengeluaran.');

            return $this->redirect($redirect);
        }

        $requesterType = (string) ($request->input('requester_type') ?? 'guru');
        $teacherId = $requesterType === 'guru' ? (int) ($request->input('teacher_id') ?? 0) : 0;
        $studentId = $requesterType === 'siswa' ? (int) ($request->input('student_id') ?? 0) : 0;
        $customRequester = trim((string) ($request->input('requester_name') ?? ''));
        $rawAmount = (string) ($request->input('amount') ?? '0');
        $description = trim((string) ($request->input('description') ?? ''));
        $recordedAtInput = (string) ($request->input('recorded_at') ?? '');

        $amount = $this->normalizeAmount($rawAmount);

        if ($amount <= 0) {
            Session::flash('error', 'Nominal pengeluaran harus lebih dari nol.');

            return $this->redirect($redirect);
        }

        $requesterName = '';

        if ($requesterType === 'guru') {
            if ($teacherId <= 0) {
                Session::flash('error', 'Pilih guru pemohon sebelum mencatat pengeluaran.');

                return $this->redirect($redirect);
            }

            $teacher = Teacher::find($teacherId);

            if ($teacher === null) {
                Session::flash('error', 'Data guru tidak ditemukan.');

                return $this->redirect($redirect);
            }

            $requesterName = (string) ($teacher['nama'] ?? 'Guru');
        } elseif ($requesterType === 'siswa') {
            if ($studentId <= 0) {
                Session::flash('error', 'Pilih siswa pemohon sebelum mencatat pengeluaran.');

                return $this->redirect($redirect);
            }

            $student = Student::find($studentId);

            if ($student === null) {
                Session::flash('error', 'Data siswa tidak ditemukan.');

                return $this->redirect($redirect);
            }

            if (!Student::hasActiveStatus($student)) {
                Session::flash('error', 'Pengeluaran untuk siswa tidak dapat dicatat karena status siswa nonaktif.');

                return $this->redirect($redirect);
            }

            $requesterName = (string) ($student['nama'] ?? 'Siswa');
        } else {
            if ($customRequester === '') {
                Session::flash('error', 'Isi nama pihak pemohon pengeluaran.');

                return $this->redirect($redirect);
            }

            $requesterName = $customRequester;
            $teacherId = 0;
        }

        $recordedAt = date('Y-m-d H:i:s');

        if ($recordedAtInput !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d\TH:i', $recordedAtInput);
            if ($parsed instanceof \DateTimeInterface) {
                $recordedAt = $parsed->format('Y-m-d H:i:s');
            }
        }

        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;

        try {
            UnexpectedExpenseService::create($schoolYearId, [
                'tipe_pemohon' => $requesterType,
                'guru_id' => $teacherId > 0 ? $teacherId : null,
                'siswa_id' => $studentId > 0 ? $studentId : null,
                'pemohon_nama' => $requesterName,
                'deskripsi' => $description,
                'nominal' => $amount,
                'tanggal' => $recordedAt,
                'dicatat_oleh' => $userId,
            ]);

            Session::flash('success', 'Pengeluaran tak terduga berhasil dicatat.');
            $this->flashGeneralCashDeficitWarning($schoolYearId);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal mencatat pengeluaran: ' . $exception->getMessage());
        }

        return $this->redirect($redirect);
    }

    private function normalizeAmount(string $raw): float
    {
        $normalized = preg_replace('/[^0-9,.-]/', '', $raw);
        if ($normalized === null || $normalized === '') {
            return 0.0;
        }

        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        return (float) $normalized;
    }
}
