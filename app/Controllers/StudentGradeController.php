<?php

namespace App\Controllers;

use App\Models\SchoolYear;
use App\Services\StudentReportService;
use Core\Controller;
use Core\Request;
use Core\Response;

class StudentGradeController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();

        if (!is_array($user) || ($user['role'] ?? '') !== 'siswa') {
            return $this->redirect('dashboard');
        }

        $studentId = isset($user['student_id']) ? (int) $user['student_id'] : 0;

        if ($studentId <= 0) {
            return $this->redirect('dashboard');
        }

        $selectedYearId = (int) $request->query('school_year_id', 0);
        if ($selectedYearId <= 0) {
            $selectedYearId = null;
        }

        $report = StudentReportService::build($studentId, $selectedYearId);

        $reportYear = isset($report['school_year']) && is_array($report['school_year'])
            ? $report['school_year']
            : null;
        $effectiveYearId = isset($reportYear['id']) ? (int) $reportYear['id'] : 0;

        $schoolYears = SchoolYear::allOrdered();

        return $this->render('student/grades/index', [
            'title' => 'Nilai Saya',
            'pageTitle' => 'Nilai Saya',
            'activeMenu' => 'student-grades',
            'schoolYears' => $schoolYears,
            'selectedSchoolYearId' => $selectedYearId ?? $effectiveYearId,
            'report' => $report,
        ]);
    }
}
