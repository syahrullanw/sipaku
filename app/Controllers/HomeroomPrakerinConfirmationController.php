<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\HomeroomPrakerinConfirmation;
use App\Models\SchoolYear;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class HomeroomPrakerinConfirmationController extends Controller
{
    protected ?string $layout = 'admin';

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/prakerin/konfirmasi')) {
            return $response;
        }

        $user = auth();
        $teacherId = is_array($user) ? (int) ($user['teacher_id'] ?? 0) : 0;

        if ($teacherId <= 0) {
            Session::flash('error', 'Akses tidak valid.');

            return $this->redirect('dashboard');
        }

        $classId = (int) $request->input('kelas_id', 0);
        $required = (int) $request->input('prakerin_required', 0) === 1;

        if ($classId <= 0) {
            Session::flash('error', 'Kelas tidak valid.');

            return $this->redirect('dashboard');
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        $classes = Classroom::homeroomClassesForTeacher(
            $teacherId,
            $activeYearId > 0 ? $activeYearId : null
        );

        if (empty($classes)) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId);
        }

        $classIds = array_map(static fn ($class) => (int) ($class['id'] ?? 0), $classes);

        if (!in_array($classId, $classIds, true)) {
            Session::flash('error', 'Kelas tidak berada di bawah binaan Anda.');

            return $this->redirect('dashboard');
        }

        if (HomeroomPrakerinConfirmation::upsertForTeacher($teacherId, $classId, $required)) {
            Session::flash('success', 'Preferensi prakerin tersimpan.');
        } else {
            Session::flash('error', 'Gagal menyimpan preferensi prakerin.');
        }

        return $this->redirect('dashboard');
    }
}
