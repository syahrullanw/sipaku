<?php

namespace Modules\Ppdb\Controllers\Guru;

use App\Models\PpdbPeriodResponsible;
use Modules\Ppdb\Controllers\Controller;
use Core\Request;
use Core\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->ensureResponsibleAccess()) {
            return $response;
        }

        $user = $this->user();
        $teacherId = (int) ($user['teacher_id'] ?? 0);

        $assignments = $teacherId > 0
            ? PpdbPeriodResponsible::teacherActiveAssignments($teacherId)
            : [];

        return $this->render('ppdb/teacher/dashboard', [
            'title' => 'PPDB',
            'pageTitle' => 'Dashboard PPDB',
            'activeMenu' => 'ppdb-teacher-dashboard',
            'assignments' => $assignments,
        ], 'admin');
    }
}
