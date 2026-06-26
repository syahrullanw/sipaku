<?php

namespace Modules\Finance\Controllers\Bendahara;

use App\Models\StudentAttendanceSession;
use App\Models\Teacher;
use App\Support\AttendanceStatus;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class TeacherAttendanceRecapController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();
        $hasActiveYear = $schoolYearId !== null;

        $startDateInput = trim((string) $request->query('start_date', ''));
        $endDateInput = trim((string) $request->query('end_date', ''));
        $teacherId = (int) $request->query('teacher_id', 0);

        if ($startDateInput === '') {
            $startDateInput = date('Y-m-01');
        }

        if ($endDateInput === '') {
            $endDateInput = date('Y-m-d');
        }

        $startDate = date_create_from_format('Y-m-d', $startDateInput);
        $endDate = date_create_from_format('Y-m-d', $endDateInput);

        if ($startDate === false || $endDate === false || $startDate > $endDate) {
            Session::flash('error', 'Rentang tanggal tidak valid. Menggunakan bulan berjalan.');
            $startDate = date_create_from_format('Y-m-d', date('Y-m-01'));
            $endDate = date_create_from_format('Y-m-d', date('Y-m-d'));
        }

        $startDateValue = $startDate?->format('Y-m-d') ?? date('Y-m-01');
        $endDateValue = $endDate?->format('Y-m-d') ?? date('Y-m-d');

        $teacherOptions = Teacher::options(true, $teacherId > 0 ? $teacherId : null);

        if ($teacherId > 0 && !isset($teacherOptions[$teacherId])) {
            $teacherId = 0;
        }

        $sessions = [];
        if ($hasActiveYear) {
            $sessions = StudentAttendanceSession::recapForTeachers(
                $schoolYearId,
                $teacherId > 0 ? $teacherId : null,
                $startDateValue,
                $endDateValue,
                null,
                null
            );
        }

        $statusKeys = ['hadir', 'izin', 'sakit', 'bolos', 'alpa'];
        $statusLabels = AttendanceStatus::options();

        $teacherSummaries = [];
        $globalTotals = [
            'sessions' => 0,
            'replacement_sessions' => 0,
            'hours' => 0.0,
            'statuses' => array_fill_keys($statusKeys, 0),
        ];

        foreach ($sessions as $session) {
            $currentTeacherId = (int) ($session['guru_id'] ?? 0);
            if (!isset($teacherSummaries[$currentTeacherId])) {
                $teacherSummaries[$currentTeacherId] = [
                    'teacher_id' => $currentTeacherId,
                    'name' => (string) ($session['guru_nama'] ?? 'Guru'),
                    'nip' => (string) ($session['guru_nip'] ?? ''),
                    'sessions' => 0,
                    'replacement_sessions' => 0,
                    'total_hours' => 0.0,
                    'attendance' => array_fill_keys($statusKeys, 0),
                    'subjects' => [],
                    'classes' => [],
                ];
            }

            $summary = &$teacherSummaries[$currentTeacherId];
            $summary['sessions']++;
            $isReplacement = (string) ($session['tipe_sesi'] ?? 'jadwal') === 'pengganti';
            if ($isReplacement) {
                $summary['replacement_sessions']++;
                $globalTotals['replacement_sessions']++;
            }
            $plannedHours = isset($session['jumlah_jam']) ? (float) $session['jumlah_jam'] : 0.0;
            if ($plannedHours < 0) {
                $plannedHours = 0.0;
            }
            $summary['total_hours'] += $plannedHours;
            $globalTotals['sessions']++;
            $globalTotals['hours'] += $plannedHours;

            foreach ($statusKeys as $statusKey) {
                $value = (int) ($session['total_' . $statusKey] ?? 0);
                $summary['attendance'][$statusKey] += $value;
                $globalTotals['statuses'][$statusKey] += $value;
            }

            $subjectId = (int) ($session['mata_pelajaran_id'] ?? 0);
            if ($subjectId > 0 && !isset($summary['subjects'][$subjectId])) {
                $summary['subjects'][$subjectId] = [
                    'id' => $subjectId,
                    'name' => (string) ($session['mata_pelajaran_nama'] ?? 'Mata Pelajaran'),
                    'code' => (string) ($session['mata_pelajaran_kode'] ?? ''),
                ];
            }

            $classId = (int) ($session['kelas_id'] ?? 0);
            if ($classId > 0 && !isset($summary['classes'][$classId])) {
                $classLabel = sprintf(
                    'Kelas %s %s',
                    (string) ($session['kelas_tingkat'] ?? ''),
                    (string) ($session['kelas_nama'] ?? '')
                );

                if (!empty($session['jurusan_nama'])) {
                    $classLabel .= sprintf(' (%s)', $session['jurusan_nama']);
                }

                $summary['classes'][$classId] = $classLabel;
            }
            unset($summary);
        }

        $teacherSummaries = array_map(static function (array $summary): array {
            $summary['subjects'] = array_values($summary['subjects']);
            $summary['classes'] = array_values($summary['classes']);

            return $summary;
        }, array_values($teacherSummaries));

        return $this->render('finance/bendahara/teacher-attendance/index', [
            'title' => 'Rekap Presensi Guru',
            'pageTitle' => 'Rekap Presensi Guru',
            'activeMenu' => 'finance-bendahara-teacher-attendance',
            'hasActiveYear' => $hasActiveYear,
            'startDate' => $startDateValue,
            'endDate' => $endDateValue,
            'teacherOptions' => $teacherOptions,
            'selectedTeacherId' => $teacherId > 0 ? $teacherId : null,
            'sessions' => $sessions,
            'teacherSummaries' => $teacherSummaries,
            'statusKeys' => $statusKeys,
            'statusLabels' => $statusLabels,
            'globalTotals' => $globalTotals,
        ], 'admin');
    }
}
