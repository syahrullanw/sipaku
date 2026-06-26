<?php

namespace App\Controllers;

use App\Models\SchoolYear;
use App\Models\SubjectAssessmentSetting;
use App\Models\SubjectTeacher;
use App\Services\AssessmentEvaluator;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class TeacherSubjectAssessmentController extends Controller
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
        $activeYear = SchoolYear::active();
        $activeYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : null;

        $assignments = SubjectTeacher::byTeacher($teacherId, $activeYearId);

        $bundles = [];

        foreach ($assignments as $assignment) {
            $assignmentId = (int) ($assignment['id'] ?? 0);
            if ($assignmentId <= 0) {
                continue;
            }

            $setting = SubjectAssessmentSetting::ensureDefault($assignmentId);

            $bundles[] = [
                'assignment' => $assignment,
                'setting' => $setting,
            ];
        }

        $focus = (int) $request->query('focus', 0);

        return $this->render('teacher/subjects/assessments', [
            'title' => 'Input Nilai Mapel',
            'pageTitle' => 'Input Nilai Pengetahuan & Keterampilan',
            'activeMenu' => 'teacher-subject-assessments',
            'assignmentBundles' => $bundles,
            'activeYear' => $activeYear,
            'focusAssignment' => $focus > 0 ? $focus : null,
        ]);
    }

    public function updateSettings(Request $request, int $assignmentId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'guru/nilai/' . $assignmentId . '/pengaturan')) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh guru.');

            return $this->redirect('dashboard');
        }

        $teacherId = (int) $user['teacher_id'];
        $assignment = SubjectTeacher::findForTeacher($assignmentId, $teacherId);

        if ($assignment === null) {
            Session::flash('error', 'Guru tidak memiliki akses ke mata pelajaran tersebut.');

            return $this->redirect('guru/nilai');
        }

        $redirectUrl = 'guru/nilai?focus=' . $assignmentId;

        $enableSkill = (int) $request->input('enable_keterampilan', 0) === 1 ? 1 : 0;
        $enableKkm = (int) $request->input('enable_kkm', 0) === 1 ? 1 : 0;
        $manualWeight = (int) $request->input('bobot_manual', 0) === 1 ? 1 : 0;

        $kkm = null;
        if ($enableKkm === 1) {
            $kkm = AssessmentEvaluator::normalizeScore($request->input('nilai_kkm', null));
            if ($kkm === null) {
                Session::flash('error', 'Nilai KKM harus berupa angka antara 0 - 100.');
                Session::flashInput($request->all());

                return $this->redirect($redirectUrl);
            }
        }

        $weightKd = (float) $request->input('bobot_kd', 25);
        $weightUts = (float) $request->input('bobot_uts', 35);
        $weightUas = (float) $request->input('bobot_uas', 40);

        if ($manualWeight === 1) {
            if ($weightKd < 0 || $weightUts < 0 || $weightUas < 0) {
                Session::flash('error', 'Bobot tidak boleh bernilai negatif.');
                Session::flashInput($request->all());

                return $this->redirect($redirectUrl);
            }

            $totalWeight = $weightKd + $weightUts + $weightUas;

            if (abs($totalWeight - 100) > 0.001) {
                Session::flash('error', 'Total bobot harus berjumlah 100%.');
                Session::flashInput($request->all());

                return $this->redirect($redirectUrl);
            }
        } else {
            $weightKd = 25;
            $weightUts = 35;
            $weightUas = 40;
        }

        $payload = [
            'enable_keterampilan' => $enableSkill,
            'enable_kkm' => $enableKkm,
            'nilai_kkm' => $kkm,
            'bobot_manual' => $manualWeight,
            'bobot_kd' => $weightKd,
            'bobot_uts' => $weightUts,
            'bobot_uas' => $weightUas,
        ];

        if (SubjectAssessmentSetting::upsertForAssignment($assignmentId, $payload)) {
            Session::flash('success', 'Pengaturan penilaian diperbarui.');
        } else {
            Session::flash('error', 'Pengaturan gagal disimpan. Silakan coba kembali.');
        }

        return $this->redirect($redirectUrl);
    }
}
