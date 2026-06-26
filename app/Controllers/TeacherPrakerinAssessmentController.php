<?php

namespace App\Controllers;

use App\Models\PrakerinAssessment;
use App\Models\PrakerinPlace;
use App\Models\SchoolYear;
use App\Models\StudentPrakerinPlacement;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use Throwable;

class TeacherPrakerinAssessmentController extends Controller
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
            return $this->render('teacher/prakerin/assessments', [
                'title' => 'Input Nilai Prakerin',
                'pageTitle' => 'Input Nilai Prakerin',
                'activeMenu' => 'teacher-prakerin-assessments',
                'activeYear' => null,
                'placeBundles' => [],
                'anchorPlaceId' => null,
            ]);
        }

        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYearId <= 0) {
            Session::flash('error', 'Data semester aktif tidak valid.');

            return $this->redirect('dashboard');
        }

        $places = PrakerinPlace::supervisedByTeacher($teacherId, $activeYearId);
        $placeBundles = [];

        foreach ($places as $place) {
            $placeId = (int) ($place['id'] ?? 0);
            if ($placeId <= 0) {
                continue;
            }

            $students = StudentPrakerinPlacement::studentsByPlace($placeId, $activeYearId);
            $assessments = PrakerinAssessment::byPlace($placeId, $activeYearId);

            $placeBundles[] = [
                'place' => $place,
                'students' => $students,
                'assessments' => $assessments,
            ];
        }

        $anchorPlaceId = $request->query('focus_place') !== null
            ? (int) $request->query('focus_place')
            : null;

        return $this->render('teacher/prakerin/assessments', [
            'title' => 'Input Nilai Prakerin',
            'pageTitle' => 'Input Nilai Prakerin',
            'activeMenu' => 'teacher-prakerin-assessments',
            'activeYear' => $activeYear,
            'placeBundles' => $placeBundles,
            'anchorPlaceId' => $anchorPlaceId,
        ]);
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'guru/prakerin/nilai')) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh guru.');

            return $this->redirect('dashboard');
        }

        $teacherId = (int) $user['teacher_id'];

        $placeId = (int) $request->input('tempat_prakerin_id', 0);

        if ($placeId <= 0) {
            Session::flash('error', 'Tempat prakerin tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('guru/prakerin/nilai');
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null) {
            Session::flash('error', 'Belum ada semester aktif.');

            return $this->redirect('guru/prakerin/nilai');
        }

        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYearId <= 0) {
            Session::flash('error', 'Data semester aktif tidak valid.');

            return $this->redirect('guru/prakerin/nilai');
        }

        $supervisedPlaces = PrakerinPlace::supervisedByTeacher($teacherId, $activeYearId);
        $selectedPlace = null;

        foreach ($supervisedPlaces as $place) {
            if ((int) ($place['id'] ?? 0) === $placeId) {
                $selectedPlace = $place;
                break;
            }
        }

        if ($selectedPlace === null) {
            Session::flash('error', 'Anda tidak tercatat sebagai pembina untuk tempat prakerin terpilih pada semester aktif.');

            return $this->redirect('guru/prakerin/nilai');
        }

        $students = StudentPrakerinPlacement::studentsByPlace($placeId, $activeYearId);

        if (empty($students)) {
            Session::flash('error', 'Belum ada siswa yang ditempatkan pada industri ini.');

            return $this->redirect('guru/prakerin/nilai?focus_place=' . urlencode((string) $placeId));
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
            $jurnal = $this->normalizeScore($input['nilai_jurnal'] ?? null);
            $laporan = $this->normalizeScore($input['nilai_laporan'] ?? null);

            if ($keaktifan === null || $jurnal === null || $laporan === null) {
                $errors[] = sprintf('Nilai pada %s harus berupa angka antara 0-100.', $student['siswa_nama'] ?? 'siswa');
                continue;
            }

            $finalScore = round(($keaktifan + $jurnal + $laporan) / 3, 2);
            $predicate = $this->determinePredicate($finalScore);

            $preparedScores[$studentId] = [
                'nilai_keaktifan' => $keaktifan,
                'nilai_jurnal' => $jurnal,
                'nilai_laporan' => $laporan,
                'nilai_akhir' => $finalScore,
                'predikat' => $predicate,
            ];
        }

        if (!empty($errors)) {
            Session::flashInput($request->all());
            Session::flash('error', implode(' ', $errors));

            return $this->redirect('guru/prakerin/nilai?focus_place=' . urlencode((string) $placeId));
        }

        try {
            PrakerinAssessment::saveMany($placeId, $activeYearId, $teacherId, $preparedScores);
            Session::flash('success', 'Nilai prakerin berhasil disimpan.');
        } catch (Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan nilai prakerin: ' . $exception->getMessage());
        }

        return $this->redirect('guru/prakerin/nilai?focus_place=' . urlencode((string) $placeId) . '#tempat-' . $placeId);
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
}
