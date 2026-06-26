<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\LessonSchedule;
use App\Models\SchoolYear;
use App\Models\StudentAttendanceSession;
use App\Models\SubjectTeacher;
use App\Support\AttendanceStatus;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class StudentAttendanceRecapController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();
        $role = (string) ($user['role'] ?? '');
        $allowedRoles = ['admin', 'staff', 'guru'];

        if (!in_array($role, $allowedRoles, true)) {
            Session::flash('error', 'Anda tidak memiliki akses ke rekap presensi siswa.');

            return $this->redirect('dashboard');
        }

        $teacherId = (int) ($user['teacher_id'] ?? 0);
        $activeYear = SchoolYear::active();
        $activeYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : null;

        $classOptions = [];

        if ($role === 'guru') {
            $scheduleList = LessonSchedule::forTeacher($teacherId, $activeYearId);

            foreach ($scheduleList as $schedule) {
                if (!is_array($schedule)) {
                    continue;
                }

                $classId = (int) ($schedule['kelas_id'] ?? 0);
                if ($classId <= 0) {
                    continue;
                }

                if (!isset($classOptions[$classId])) {
                    $label = sprintf(
                        'Kelas %s %s',
                        $schedule['kelas_tingkat'] ?? '-',
                        $schedule['kelas_nama'] ?? '-'
                    );

                    if (!empty($schedule['jurusan_nama'])) {
                        $label .= sprintf(' (%s)', $schedule['jurusan_nama']);
                    }

                    $classOptions[$classId] = $label;
                }
            }

            ksort($classOptions);
        } else {
            $classOptions = Classroom::options($activeYearId);
        }

        $selectedClassId = (int) $request->query('kelas_id', 0);

        if ($selectedClassId <= 0 && !empty($classOptions)) {
            $selectedClassId = (int) array_key_first($classOptions);
        }

        if ($selectedClassId > 0 && !isset($classOptions[$selectedClassId]) && $role === 'guru') {
            $selectedClassId = (int) array_key_first($classOptions);
        }

        $rawStart = trim((string) $request->query('start_date', ''));
        $rawEnd = trim((string) $request->query('end_date', ''));

        if ($rawStart === '') {
            $rawStart = date('Y-m-d', strtotime('monday this week'));
        }

        if ($rawEnd === '') {
            $rawEnd = date('Y-m-d');
        }

        $startDate = date_create_from_format('Y-m-d', $rawStart);
        $endDate = date_create_from_format('Y-m-d', $rawEnd);

        if ($startDate === false || $endDate === false || $startDate > $endDate) {
            $startDate = date_create_from_format('Y-m-d', date('Y-m-d', strtotime('monday this week')));
            $endDate = date_create_from_format('Y-m-d', date('Y-m-d'));
            Session::flash('error', 'Rentang tanggal tidak valid. Menggunakan rentang minggu ini.');
        }

        $startDateValue = $startDate?->format('Y-m-d') ?? date('Y-m-d', strtotime('monday this week'));
        $endDateValue = $endDate?->format('Y-m-d') ?? date('Y-m-d');

        $assignmentOptions = ['0' => 'Semua Mata Pelajaran'];

        if ($selectedClassId > 0) {
            if ($role === 'guru') {
                $assignments = SubjectTeacher::byTeacher($teacherId, $activeYearId);

                foreach ($assignments as $assignment) {
                    if (!is_array($assignment)) {
                        continue;
                    }

                    $assignmentId = (int) ($assignment['id'] ?? 0);
                    if ($assignmentId <= 0) {
                        continue;
                    }

                    $classes = isset($assignment['classes']) && is_array($assignment['classes']) ? $assignment['classes'] : [];

                    foreach ($classes as $class) {
                        if ((int) ($class['id'] ?? 0) === $selectedClassId) {
                            $label = $assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran';
                            if (!empty($assignment['mata_pelajaran_kode'])) {
                                $label .= sprintf(' (%s)', $assignment['mata_pelajaran_kode']);
                            }
                            $assignmentOptions[$assignmentId] = $label;
                            break;
                        }
                    }
                }
            } else {
                $assignments = SubjectTeacher::bySchoolYearForClass(
                    $activeYearId ?? 0,
                    null,
                    $selectedClassId
                );

                foreach ($assignments as $assignment) {
                    $assignmentId = (int) ($assignment['id'] ?? 0);
                    if ($assignmentId <= 0) {
                        continue;
                    }
                    $label = $assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran';
                    if (!empty($assignment['mata_pelajaran_kode'])) {
                        $label .= sprintf(' (%s)', $assignment['mata_pelajaran_kode']);
                    }
                    if (!empty($assignment['guru_nama'])) {
                        $label .= sprintf(' • %s', $assignment['guru_nama']);
                    }
                    $assignmentOptions[$assignmentId] = $label;
                }
            }
        }

        $selectedAssignmentId = (int) $request->query('assignment_id', 0);

        if ($selectedAssignmentId > 0 && !isset($assignmentOptions[$selectedAssignmentId])) {
            $selectedAssignmentId = 0;
        }

        $sessions = [];

        if ($selectedClassId > 0) {
            $sessions = StudentAttendanceSession::recapForClass(
                $selectedClassId,
                $selectedAssignmentId > 0 ? $selectedAssignmentId : null,
                $startDateValue,
                $endDateValue
            );
        }

        $statusLabels = AttendanceStatus::options();
        $totals = [
            'hadir' => 0,
            'izin' => 0,
            'sakit' => 0,
            'bolos' => 0,
            'alpa' => 0,
        ];

        $subjectSummaries = [];

        foreach ($sessions as $session) {
            foreach ($totals as $statusKey => $value) {
                $totals[$statusKey] += (int) ($session['total_' . $statusKey] ?? 0);
            }

            $subjectKey = (int) ($session['guru_mata_pelajaran_id'] ?? 0);

            if (!isset($subjectSummaries[$subjectKey])) {
                $subjectSummaries[$subjectKey] = [
                    'mata_pelajaran' => $session['mata_pelajaran_nama'] ?? 'Mata Pelajaran',
                    'kode' => $session['mata_pelajaran_kode'] ?? '',
                    'guru' => $session['guru_nama'] ?? '',
                    'counts' => [
                        'hadir' => 0,
                        'izin' => 0,
                        'sakit' => 0,
                        'bolos' => 0,
                        'alpa' => 0,
                    ],
                    'sessions' => 0,
                ];
            }

            $subjectSummaries[$subjectKey]['sessions']++;

            foreach ($subjectSummaries[$subjectKey]['counts'] as $statusKey => $value) {
                $subjectSummaries[$subjectKey]['counts'][$statusKey] += (int) ($session['total_' . $statusKey] ?? 0);
            }
        }

        return $this->render('academic/attendance/recap', [
            'title' => 'Rekap Presensi Siswa',
            'pageTitle' => 'Rekap Presensi Siswa per Mapel',
            'activeMenu' => 'attendance-recap',
            'classOptions' => $classOptions,
            'selectedClassId' => $selectedClassId,
            'assignmentOptions' => $assignmentOptions,
            'selectedAssignmentId' => $selectedAssignmentId,
            'startDate' => $startDateValue,
            'endDate' => $endDateValue,
            'sessions' => $sessions,
            'statusLabels' => $statusLabels,
            'totals' => $totals,
            'subjectSummaries' => $subjectSummaries,
            'activeYear' => $activeYear,
        ]);
    }
}
