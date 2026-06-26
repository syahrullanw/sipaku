<?php

namespace App\Controllers;

use App\Models\StudentAttendanceSession;
use App\Models\SchoolYear;
use App\Services\AttendanceRecapExporter;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class TeacherAttendanceRecapController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh guru.');

            return $this->redirect('dashboard');
        }

        $teacherId = (int) $user['teacher_id'];

        $startDateRaw = trim((string) $request->query('start_date', ''));
        $endDateRaw = trim((string) $request->query('end_date', ''));
        $classId = (int) $request->query('kelas_id', 0);
        $subjectId = (int) $request->query('mapel_id', 0);

        if ($startDateRaw === '') {
            $startDateRaw = date('Y-m-d', strtotime('monday this week'));
        }

        if ($endDateRaw === '') {
            $endDateRaw = date('Y-m-d');
        }

        $startDate = date_create_from_format('Y-m-d', $startDateRaw) ?: false;
        $endDate = date_create_from_format('Y-m-d', $endDateRaw) ?: false;

        if ($startDate === false || $endDate === false || $startDate > $endDate) {
            Session::flash('error', 'Rentang tanggal tidak valid. Menggunakan rentang minggu ini.');
            $startDate = date_create_from_format('Y-m-d', date('Y-m-d', strtotime('monday this week')));
            $endDate = date_create_from_format('Y-m-d', date('Y-m-d'));
        }

        $startDateValue = $startDate?->format('Y-m-d');
        $endDateValue = $endDate?->format('Y-m-d');

        $allSessions = StudentAttendanceSession::recapForTeacher($teacherId, null, null, null, null);

        $classOptions = [];
        $subjectOptions = [];

        foreach ($allSessions as $session) {
            $classKey = (int) ($session['kelas_id'] ?? 0);
            if ($classKey > 0 && !isset($classOptions[$classKey])) {
                $label = self::formatClassLabel($session);
                $classOptions[$classKey] = $label;
            }

            $parallelClassKey = (int) ($session['kelas_paralel_id'] ?? 0);
            if ($parallelClassKey > 0 && !isset($classOptions[$parallelClassKey])) {
                $label = self::formatClassLabelFromFields(
                    (string) ($session['kelas_paralel_tingkat'] ?? ''),
                    (string) ($session['kelas_paralel_nama'] ?? ''),
                    (string) ($session['jurusan_paralel_nama'] ?? '')
                );
                $classOptions[$parallelClassKey] = $label;
            }

            $subjectKey = (int) ($session['mata_pelajaran_id'] ?? 0);
            if ($subjectKey > 0 && !isset($subjectOptions[$subjectKey])) {
                $name = (string) ($session['mata_pelajaran_nama'] ?? 'Mata Pelajaran');
                $code = (string) ($session['mata_pelajaran_kode'] ?? '');
                $subjectOptions[$subjectKey] = trim($name . ($code !== '' ? ' (' . $code . ')' : ''));
            }
        }

        ksort($classOptions);
        ksort($subjectOptions);

        if ($classId > 0 && !isset($classOptions[$classId])) {
            $classId = 0;
        }

        if ($subjectId > 0 && !isset($subjectOptions[$subjectId])) {
            $subjectId = 0;
        }

        $sessions = StudentAttendanceSession::recapForTeacher(
            $teacherId,
            $startDateValue,
            $endDateValue,
            $classId > 0 ? $classId : null,
            $subjectId > 0 ? $subjectId : null
        );

        $totals = [
            'hadir' => 0,
            'izin' => 0,
            'sakit' => 0,
            'bolos' => 0,
            'alpa' => 0,
        ];

        foreach ($sessions as $session) {
            foreach ($totals as $key => $value) {
                $totals[$key] += (int) ($session['total_' . $key] ?? 0);
            }
        }

        $activeYear = SchoolYear::active();

        $filters = [
            'start_date' => $startDateValue,
            'end_date' => $endDateValue,
            'class_id' => $classId > 0 ? $classId : null,
            'subject_id' => $subjectId > 0 ? $subjectId : null,
        ];

        return $this->render('teacher/attendance/recap', [
            'title' => 'Rekap Presensi Siswa',
            'pageTitle' => 'Rekap Presensi Semua Mapel',
            'activeMenu' => 'teacher-attendance-recap',
            'sessions' => $sessions,
            'classOptions' => $classOptions,
            'subjectOptions' => $subjectOptions,
            'selectedClassId' => $classId > 0 ? $classId : null,
            'selectedSubjectId' => $subjectId > 0 ? $subjectId : null,
            'startDate' => $filters['start_date'],
            'endDate' => $filters['end_date'],
            'totals' => $totals,
            'activeYear' => $activeYear,
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh guru.');

            return $this->redirect('dashboard');
        }

        $teacherId = (int) $user['teacher_id'];

        $startDate = trim((string) $request->query('start_date', ''));
        $endDate = trim((string) $request->query('end_date', ''));
        $classId = (int) $request->query('kelas_id', 0);
        $subjectId = (int) $request->query('mapel_id', 0);

        $start = $startDate !== '' ? $startDate : null;
        $end = $endDate !== '' ? $endDate : null;

        $sessions = StudentAttendanceSession::recapForTeacher(
            $teacherId,
            $start,
            $end,
            $classId > 0 ? $classId : null,
            $subjectId > 0 ? $subjectId : null
        );

        if (empty($sessions)) {
            Session::flash('error', 'Tidak ada data presensi yang dapat diekspor.');

            return $this->redirect('guru/presensi/rekap');
        }

        $classLabel = null;
        $subjectLabel = null;

        if ($classId > 0) {
            foreach ($sessions as $session) {
                if ((int) ($session['kelas_id'] ?? 0) === $classId) {
                    $classLabel = self::formatClassLabel($session);
                    break;
                }
                if ((int) ($session['kelas_paralel_id'] ?? 0) === $classId) {
                    $classLabel = self::formatClassLabelFromFields(
                        (string) ($session['kelas_paralel_tingkat'] ?? ''),
                        (string) ($session['kelas_paralel_nama'] ?? ''),
                        (string) ($session['jurusan_paralel_nama'] ?? '')
                    );
                    break;
                }
            }
        }

        if ($subjectId > 0) {
            foreach ($sessions as $session) {
                if ((int) ($session['mata_pelajaran_id'] ?? 0) === $subjectId) {
                    $name = (string) ($session['mata_pelajaran_nama'] ?? 'Mata Pelajaran');
                    $code = (string) ($session['mata_pelajaran_kode'] ?? '');
                    $subjectLabel = trim($name . ($code !== '' ? ' (' . $code . ')' : ''));
                    break;
                }
            }
        }

        $totals = [
            'hadir' => 0,
            'izin' => 0,
            'sakit' => 0,
            'bolos' => 0,
            'alpa' => 0,
        ];

        foreach ($sessions as $session) {
            foreach ($totals as $key => $value) {
                $totals[$key] += (int) ($session['total_' . $key] ?? 0);
            }
        }

        $filters = [
            'start_date' => $start,
            'end_date' => $end,
            'class_label' => $classLabel,
            'subject_label' => $subjectLabel,
        ];

        $content = AttendanceRecapExporter::teacherPdf($user ?? [], $sessions, $filters, $totals);
        $filenameParts = ['rekap-presensi'];

        if ($classLabel !== null && $classLabel !== '') {
            $filenameParts[] = str_replace(' ', '-', strtolower($classLabel));
        }

        if ($subjectLabel !== null && $subjectLabel !== '') {
            $filenameParts[] = str_replace(' ', '-', strtolower($subjectLabel));
        }

        if ($start !== null && $end !== null) {
            $filenameParts[] = $start . '-' . $end;
        }

        $filename = implode('-', array_filter($filenameParts)) . '.pdf';

        return Response::make($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param array<string, mixed> $session
     */
    private static function formatClassLabel(array $session): string
    {
        return self::formatClassLabelFromFields(
            (string) ($session['kelas_tingkat'] ?? ''),
            (string) ($session['kelas_nama'] ?? ''),
            (string) ($session['jurusan_nama'] ?? '')
        );
    }

    private static function formatClassLabelFromFields(string $grade, string $name, string $major): string
    {
        $grade = trim($grade);
        $name = trim($name);
        $label = trim($grade . ' ' . $name);
        $major = trim($major);

        if ($major !== '') {
            $label = trim($label . ' (' . $major . ')');
        }

        return $label !== '' ? $label : 'Kelas';
    }
}
