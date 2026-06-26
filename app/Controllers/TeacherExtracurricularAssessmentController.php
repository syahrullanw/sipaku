<?php

namespace App\Controllers;

use App\Models\Extracurricular;
use App\Models\SchoolYear;
use App\Models\StudentExtracurricular;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use Throwable;

class TeacherExtracurricularAssessmentController extends Controller
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

        if ($activeYear === null) {
            return $this->render('teacher/extracurricular/assessments', [
                'title' => 'Input Nilai Ekskul',
                'pageTitle' => 'Input Nilai Ekstrakurikuler',
                'activeMenu' => 'teacher-extracurricular-assessments',
                'activeYear' => null,
                'activityBundles' => [],
                'anchorActivityId' => null,
            ]);
        }

        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYearId <= 0) {
            Session::flash('error', 'Data semester aktif tidak valid.');

            return $this->redirect('dashboard');
        }

        $activities = Extracurricular::byMentor($teacherId, $activeYearId);
        $activityBundles = [];

        foreach ($activities as $activity) {
            $activityId = (int) ($activity['id'] ?? 0);
            if ($activityId <= 0) {
                continue;
            }

            $students = StudentExtracurricular::studentsByActivity($activityId, $activeYearId);

            $activityBundles[] = [
                'activity' => $activity,
                'students' => $students,
            ];
        }

        $anchorActivityId = $request->query('focus_activity') !== null
            ? (int) $request->query('focus_activity')
            : null;

        return $this->render('teacher/extracurricular/assessments', [
            'title' => 'Input Nilai Ekskul',
            'pageTitle' => 'Input Nilai Ekstrakurikuler',
            'activeMenu' => 'teacher-extracurricular-assessments',
            'activeYear' => $activeYear,
            'activityBundles' => $activityBundles,
            'anchorActivityId' => $anchorActivityId,
        ]);
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'guru/ekskul/nilai')) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh guru.');

            return $this->redirect('dashboard');
        }

        $teacherId = (int) $user['teacher_id'];
        $activityId = (int) $request->input('ekstrakurikuler_id', 0);

        if ($activityId <= 0) {
            Session::flash('error', 'Kegiatan ekstrakurikuler tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('guru/ekskul/nilai');
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null) {
            Session::flash('error', 'Belum ada semester aktif.');

            return $this->redirect('guru/ekskul/nilai');
        }

        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYearId <= 0) {
            Session::flash('error', 'Data semester aktif tidak valid.');

            return $this->redirect('guru/ekskul/nilai');
        }

        $mentoredActivities = Extracurricular::byMentor($teacherId, $activeYearId);
        $selectedActivity = null;

        foreach ($mentoredActivities as $activity) {
            if ((int) ($activity['id'] ?? 0) === $activityId) {
                $selectedActivity = $activity;
                break;
            }
        }

        if ($selectedActivity === null) {
            Session::flash('error', 'Anda tidak tercatat sebagai pembina untuk ekskul terpilih pada semester aktif.');

            return $this->redirect('guru/ekskul/nilai');
        }

        $students = StudentExtracurricular::studentsByActivity($activityId, $activeYearId);

        if (empty($students)) {
            Session::flash('error', 'Belum ada siswa yang terdaftar pada ekskul ini di semester aktif.');

            return $this->redirect('guru/ekskul/nilai?focus_activity=' . urlencode((string) $activityId));
        }

        $scoreInputs = $request->input('scores', []);

        if (!is_array($scoreInputs)) {
            $scoreInputs = [];
        }

        $preparedScores = [];
        $errors = [];

        foreach ($students as $student) {
            $studentId = (int) ($student['siswa_id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $input = $scoreInputs[$studentId] ?? null;

            if (!is_array($input)) {
                $errors[] = sprintf('Lengkapi nilai untuk %s.', $student['siswa_nama'] ?? 'siswa');
                continue;
            }

            $keaktifan = $this->normalizeScore($input['nilai_keaktifan'] ?? null);
            $kemampuan = $this->normalizeScore($input['nilai_kemampuan_teknis'] ?? null);
            $kehadiran = $this->normalizeScore($input['nilai_kehadiran'] ?? null);

            if ($keaktifan === null || $kemampuan === null || $kehadiran === null) {
                $errors[] = sprintf('Nilai pada %s harus berupa angka antara 0-100.', $student['siswa_nama'] ?? 'siswa');
                continue;
            }

            $finalScore = round(($keaktifan + $kemampuan + $kehadiran) / 3, 2);
            $predicate = $this->determinePredicate($finalScore);
            $description = $this->generateDescription($predicate);

            $preparedScores[$studentId] = [
                'nilai_keaktifan' => $keaktifan,
                'nilai_kemampuan_teknis' => $kemampuan,
                'nilai_kehadiran' => $kehadiran,
                'nilai_akhir' => $finalScore,
                'predikat' => $predicate,
                'deskripsi' => $description,
            ];
        }

        if (!empty($errors)) {
            Session::flashInput($request->all());
            Session::flash('error', implode(' ', $errors));

            return $this->redirect('guru/ekskul/nilai?focus_activity=' . urlencode((string) $activityId));
        }

        try {
            StudentExtracurricular::saveScores($activityId, $activeYearId, $teacherId, $preparedScores);
            Session::flash('success', 'Nilai ekstrakurikuler berhasil disimpan.');
        } catch (Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan nilai ekskul: ' . $exception->getMessage());
        }

        return $this->redirect('guru/ekskul/nilai?focus_activity=' . urlencode((string) $activityId) . '#ekskul-' . $activityId);
    }

    private function normalizeScore(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = str_replace([',', ' '], ['.', ''], $value);
        }

        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if ($number < 0 || $number > 100) {
            return null;
        }

        return round($number, 2);
    }

    private function determinePredicate(float $score): string
    {
        if ($score >= 86) {
            return 'Amat Baik';
        }

        if ($score >= 70) {
            return 'Baik';
        }

        return 'Kurang';
    }

    private function generateDescription(string $predicate): string
    {
        return match ($predicate) {
            'Amat Baik' => 'Menunjukkan keaktifan tinggi, kemampuan teknis matang, dan kehadiran sangat konsisten.',
            'Baik' => 'Menunjukkan keterlibatan baik, kemampuan teknis cukup kuat, dan kehadiran stabil.',
            default => 'Perlu meningkatkan keaktifan, kemampuan teknis, serta konsistensi kehadiran pada kegiatan.',
        };
    }
}
