<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\CocurricularActivity;
use App\Models\CocurricularActivityElement;
use App\Models\CocurricularAssessment;
use App\Models\CocurricularSummary;
use App\Models\P5Dimension;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AssessmentEvaluator;
use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Throwable;

class HomeroomCocurricularController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $teacherId = (int) $user['teacher_id'];
        $activeYear = SchoolYear::active();
        $activeYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : 0;
        $activeSemester = $activeYear !== null ? (int) ($activeYear['semester_aktif'] ?? 1) : 1;

        $semester = (int) $request->query('semester', 0);
        if (!in_array($semester, [1, 2], true)) {
            $semester = in_array($activeSemester, [1, 2], true) ? $activeSemester : 1;
        }

        $classes = [];
        if ($activeYearId > 0) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId, $activeYearId);
        }

        if (empty($classes)) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId);
        }

        $selectedClassId = (int) $request->query('kelas_id', 0);
        $selectedClass = null;

        foreach ($classes as $class) {
            if ((int) ($class['id'] ?? 0) === $selectedClassId) {
                $selectedClass = $class;
                break;
            }
        }

        if ($selectedClass === null && !empty($classes)) {
            $selectedClass = $classes[0];
            $selectedClassId = (int) ($selectedClass['id'] ?? 0);
        }

        $activities = [];
        $elements = [];
        $students = [];
        $assessments = [];
        $summaries = [];
        $selectedActivityId = (int) $request->query('kegiatan_id', 0);
        $isActiveMismatch = false;
        $activityLimitReached = false;

        if ($selectedClass !== null) {
            $classYearId = (int) ($selectedClass['tahun_ajaran_id'] ?? 0);
            $classCurriculum = (string) ($selectedClass['kurikulum'] ?? 'k13');

            if ($activeYearId > 0 && $classYearId !== $activeYearId) {
                $isActiveMismatch = true;
            }

            if ($classCurriculum === 'kurmer') {
                $activities = CocurricularActivity::byClass($selectedClassId, $classYearId > 0 ? $classYearId : null, $semester);
                $activityLimitReached = CocurricularActivity::countByClassSemester($selectedClassId, $classYearId > 0 ? $classYearId : $activeYearId, $semester) >= 3;

                if (!empty($activities)) {
                    $activityIds = array_map(static fn ($item) => (int) ($item['id'] ?? 0), $activities);
                    if ($selectedActivityId <= 0 || !in_array($selectedActivityId, $activityIds, true)) {
                        $selectedActivityId = $activityIds[0] ?? 0;
                    }
                }

                if ($selectedActivityId > 0) {
                    $elements = CocurricularActivityElement::byActivity($selectedActivityId);
                    $students = Student::byClass($selectedClassId, $classYearId > 0 ? $classYearId : null);
                    $studentIds = array_values(array_filter(array_map(static fn ($s) => (int) ($s['id'] ?? 0), $students)));
                    if (!empty($studentIds)) {
                        $assessments = CocurricularAssessment::mapByActivity($selectedActivityId, $studentIds);
                        $summaries = CocurricularSummary::byActivity($selectedActivityId, $studentIds);
                    }
                }
            }
        }

        $dimensions = P5Dimension::allWithElements();
        $teacherOptions = Teacher::options(true, $teacherId);

        return $this->render('homeroom/cocurriculars/index', [
            'title' => 'Kokurikuler Kurmer',
            'pageTitle' => 'Penilaian Kokurikuler (Kurmer)',
            'activeMenu' => 'homeroom-cocurriculars',
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'activities' => $activities,
            'selectedActivityId' => $selectedActivityId > 0 ? $selectedActivityId : null,
            'elements' => $elements,
            'students' => $students,
            'assessments' => $assessments,
            'summaries' => $summaries,
            'dimensions' => $dimensions,
            'teacherOptions' => $teacherOptions,
            'activeYear' => $activeYear,
            'semester' => $semester,
            'isActiveMismatch' => $isActiveMismatch,
            'activityLimitReached' => $activityLimitReached,
        ]);
    }

    public function storeActivity(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/kokurikuler')) {
            return $response;
        }

        $user = auth();
        $teacherId = (int) ($user['teacher_id'] ?? 0);
        if (($user['role'] ?? '') !== 'guru' || $teacherId <= 0) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $classId = (int) $request->input('kelas_id', 0);
        $redirectUrl = 'walikelas/kokurikuler';
        if ($classId > 0) {
            $redirectUrl .= '?kelas_id=' . $classId;
        }

        $class = Classroom::find($classId);

        if ($class === null) {
            Session::flash('error', 'Kelas tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        $homeroomClasses = Classroom::homeroomClassesForTeacher($teacherId, (int) ($class['tahun_ajaran_id'] ?? null));
        $isHomeroom = array_reduce($homeroomClasses, static function (bool $carry, array $row) use ($classId): bool {
            return $carry || (int) ($row['id'] ?? 0) === $classId;
        }, false);

        if (!$isHomeroom) {
            Session::flash('error', 'Anda bukan wali kelas untuk kelas ini.');

            return $this->redirect($redirectUrl);
        }

        if (($class['kurikulum'] ?? 'k13') !== 'kurmer') {
            Session::flash('error', 'Kokurikuler hanya tersedia untuk kelas Kurikulum Merdeka.');

            return $this->redirect($redirectUrl);
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYearId <= 0 || (int) ($class['tahun_ajaran_id'] ?? 0) !== $activeYearId) {
            Session::flash('error', 'Kegiatan kokurikuler hanya dapat dibuat pada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        $semester = (int) ($activeYear['semester_aktif'] ?? 1);
        if (!in_array($semester, [1, 2], true)) {
            $semester = 1;
        }

        if (CocurricularActivity::countByClassSemester($classId, $activeYearId, $semester) >= 3) {
            Session::flash('error', 'Setiap kelas maksimal memiliki 3 kegiatan kokurikuler per semester.');

            return $this->redirect($redirectUrl);
        }

        $tema = trim((string) $request->input('tema', ''));
        $nama = trim((string) $request->input('nama', ''));
        $deskripsi = trim((string) $request->input('deskripsi', ''));
        $coordinatorId = (int) $request->input('guru_koordinator_id', 0);

        if ($tema === '' || $nama === '') {
            Session::flash('error', 'Tema dan nama kegiatan wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect($redirectUrl);
        }

        $payload = [
            'tahun_ajaran_id' => $activeYearId,
            'kelas_id' => $classId,
            'semester' => $semester,
            'tema' => $tema,
            'nama' => $nama,
            'deskripsi' => $deskripsi !== '' ? $deskripsi : null,
            'guru_koordinator_id' => $coordinatorId > 0 ? $coordinatorId : $teacherId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!CocurricularActivity::create($payload)) {
            Session::flash('error', 'Gagal menyimpan kegiatan kokurikuler.');

            return $this->redirect($redirectUrl);
        }

        Session::flash('success', 'Kegiatan kokurikuler ditambahkan.');

        return $this->redirect($redirectUrl);
    }

    public function storeElement(Request $request, int $activityId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/kokurikuler/kegiatan/' . $activityId . '/elemen')) {
            return $response;
        }

        $user = auth();
        $teacherId = (int) ($user['teacher_id'] ?? 0);
        if (($user['role'] ?? '') !== 'guru' || $teacherId <= 0) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $activity = CocurricularActivity::findWithRelations($activityId);
        if ($activity === null) {
            Session::flash('error', 'Kegiatan tidak ditemukan.');

            return $this->redirect('walikelas/kokurikuler');
        }

        $redirectUrl = 'walikelas/kokurikuler?kelas_id=' . $activity['kelas_id'] . '&kegiatan_id=' . $activityId;

        $classId = (int) ($activity['kelas_id'] ?? 0);
        $classYearId = (int) ($activity['tahun_ajaran_id'] ?? 0);

        $homeroomClasses = Classroom::homeroomClassesForTeacher($teacherId, $classYearId > 0 ? $classYearId : null);
        $isHomeroom = array_reduce($homeroomClasses, static function (bool $carry, array $row) use ($classId): bool {
            return $carry || (int) ($row['id'] ?? 0) === $classId;
        }, false);

        if (!$isHomeroom) {
            Session::flash('error', 'Anda bukan wali kelas untuk kelas ini.');

            return $this->redirect($redirectUrl);
        }

        if (($activity['kurikulum'] ?? 'k13') !== 'kurmer') {
            Session::flash('error', 'Kegiatan ini bukan kelas Kurikulum Merdeka.');

            return $this->redirect($redirectUrl);
        }

        $elemenId = (int) $request->input('elemen_id', 0);
        $subElemen = trim((string) $request->input('sub_elemen', ''));

        if ($elemenId <= 0) {
            Session::flash('error', 'Pilih elemen Profil Pelajar Pancasila.');

            return $this->redirect($redirectUrl);
        }

        $existingElements = CocurricularActivityElement::byActivity($activityId);
        foreach ($existingElements as $existing) {
            if ((int) ($existing['elemen_id'] ?? 0) === $elemenId) {
                Session::flash('error', 'Elemen sudah ditambahkan ke kegiatan ini.');

                return $this->redirect($redirectUrl);
            }
        }

        CocurricularActivityElement::create([
            'kegiatan_id' => $activityId,
            'elemen_id' => $elemenId,
            'sub_elemen' => $subElemen !== '' ? $subElemen : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Elemen kokurikuler ditambahkan.');

        return $this->redirect($redirectUrl);
    }

    public function deleteElement(Request $request, int $activityId, int $elementId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/kokurikuler/kegiatan/' . $activityId . '/elemen/' . $elementId . '/hapus')) {
            return $response;
        }

        $user = auth();
        $teacherId = (int) ($user['teacher_id'] ?? 0);
        if (($user['role'] ?? '') !== 'guru' || $teacherId <= 0) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $activity = CocurricularActivity::findWithRelations($activityId);
        if ($activity === null) {
            Session::flash('error', 'Kegiatan tidak ditemukan.');

            return $this->redirect('walikelas/kokurikuler');
        }

        $redirectUrl = 'walikelas/kokurikuler?kelas_id=' . $activity['kelas_id'] . '&kegiatan_id=' . $activityId;
        $classId = (int) ($activity['kelas_id'] ?? 0);
        $classYearId = (int) ($activity['tahun_ajaran_id'] ?? 0);

        $homeroomClasses = Classroom::homeroomClassesForTeacher($teacherId, $classYearId > 0 ? $classYearId : null);
        $isHomeroom = array_reduce($homeroomClasses, static function (bool $carry, array $row) use ($classId): bool {
            return $carry || (int) ($row['id'] ?? 0) === $classId;
        }, false);

        if (!$isHomeroom) {
            Session::flash('error', 'Anda bukan wali kelas untuk kelas ini.');

            return $this->redirect($redirectUrl);
        }

        CocurricularAssessment::deleteByElement($activityId, $elementId);
        CocurricularActivityElement::deleteById($elementId);

        Session::flash('success', 'Elemen kokurikuler dihapus.');

        return $this->redirect($redirectUrl);
    }

    public function storeAssessments(Request $request, int $activityId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/kokurikuler/kegiatan/' . $activityId . '/penilaian')) {
            return $response;
        }

        $user = auth();
        $teacherId = (int) ($user['teacher_id'] ?? 0);
        if (($user['role'] ?? '') !== 'guru' || $teacherId <= 0) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $activity = CocurricularActivity::findWithRelations($activityId);
        if ($activity === null) {
            Session::flash('error', 'Kegiatan tidak ditemukan.');

            return $this->redirect('walikelas/kokurikuler');
        }

        $redirectUrl = 'walikelas/kokurikuler?kelas_id=' . $activity['kelas_id'] . '&kegiatan_id=' . $activityId;
        $classId = (int) ($activity['kelas_id'] ?? 0);
        $classYearId = (int) ($activity['tahun_ajaran_id'] ?? 0);

        $homeroomClasses = Classroom::homeroomClassesForTeacher($teacherId, $classYearId > 0 ? $classYearId : null);
        $isHomeroom = array_reduce($homeroomClasses, static function (bool $carry, array $row) use ($classId): bool {
            return $carry || (int) ($row['id'] ?? 0) === $classId;
        }, false);

        if (!$isHomeroom) {
            Session::flash('error', 'Anda bukan wali kelas untuk kelas ini.');

            return $this->redirect($redirectUrl);
        }

        $elements = CocurricularActivityElement::byActivity($activityId);
        $students = Student::byClass($classId, $classYearId > 0 ? $classYearId : null);

        if (empty($elements) || empty($students)) {
            Session::flash('error', 'Lengkapi elemen dan data siswa terlebih dahulu.');

            return $this->redirect($redirectUrl);
        }

        $capaianInput = $request->input('capaian', []);
        $catatanInput = $request->input('catatan', []);

        try {
            $connection = Database::connection();
            $connection->beginTransaction();

            foreach ($students as $student) {
                $studentId = (int) ($student['id'] ?? 0);
                if ($studentId <= 0) {
                    continue;
                }

                foreach ($elements as $element) {
                    $elementId = (int) ($element['id'] ?? 0);
                    if ($elementId <= 0) {
                        continue;
                    }

                    $rawCapaian = $capaianInput[$studentId][$elementId] ?? null;
                    $capaian = AssessmentEvaluator::normalizeKurmerCapaian($rawCapaian);
                    if ($capaian === null) {
                        continue;
                    }

                    $catatan = $catatanInput[$studentId][$elementId] ?? null;

                    CocurricularAssessment::upsert(
                        $activityId,
                        $elementId,
                        $studentId,
                        $capaian,
                        is_string($catatan) && trim($catatan) !== '' ? trim($catatan) : null
                    );
                }
            }

            $connection->commit();
            Session::flash('success', 'Nilai kokurikuler disimpan.');
        } catch (Throwable $exception) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollBack();
            }
            Session::flash('error', 'Gagal menyimpan penilaian: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    public function storeSummaries(Request $request, int $activityId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/kokurikuler/kegiatan/' . $activityId . '/ringkasan')) {
            return $response;
        }

        $user = auth();
        $teacherId = (int) ($user['teacher_id'] ?? 0);
        if (($user['role'] ?? '') !== 'guru' || $teacherId <= 0) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $activity = CocurricularActivity::findWithRelations($activityId);
        if ($activity === null) {
            Session::flash('error', 'Kegiatan tidak ditemukan.');

            return $this->redirect('walikelas/kokurikuler');
        }

        $redirectUrl = 'walikelas/kokurikuler?kelas_id=' . $activity['kelas_id'] . '&kegiatan_id=' . $activityId;
        $classId = (int) ($activity['kelas_id'] ?? 0);
        $classYearId = (int) ($activity['tahun_ajaran_id'] ?? 0);

        $homeroomClasses = Classroom::homeroomClassesForTeacher($teacherId, $classYearId > 0 ? $classYearId : null);
        $isHomeroom = array_reduce($homeroomClasses, static function (bool $carry, array $row) use ($classId): bool {
            return $carry || (int) ($row['id'] ?? 0) === $classId;
        }, false);

        if (!$isHomeroom) {
            Session::flash('error', 'Anda bukan wali kelas untuk kelas ini.');

            return $this->redirect($redirectUrl);
        }

        $elements = CocurricularActivityElement::byActivity($activityId);
        $students = Student::byClass($classId, $classYearId > 0 ? $classYearId : null);
        $studentIds = array_values(array_filter(array_map(static fn ($s) => (int) ($s['id'] ?? 0), $students)));
        $assessments = [];
        if (!empty($studentIds)) {
            $assessments = CocurricularAssessment::mapByActivity($activityId, $studentIds);
        }

        if (empty($elements) || empty($students)) {
            Session::flash('error', 'Lengkapi elemen dan data siswa terlebih dahulu.');

            return $this->redirect($redirectUrl);
        }

        $deskripsiInput = $request->input('deskripsi_umum', []);
        $tindakInput = $request->input('tindak_lanjut', []);

        $labels = [
            'BB' => 'Belum Berkembang',
            'MB' => 'Mulai Berkembang',
            'BSH' => 'Berkembang Sesuai Harapan',
            'SB' => 'Sangat Berkembang',
        ];

        $levelOrder = ['BB' => 1, 'MB' => 2, 'BSH' => 3, 'SB' => 4];
        $activityName = trim((string) ($activity['nama'] ?? 'Kegiatan Kokurikuler'));
        $activityTheme = trim((string) ($activity['tema'] ?? ''));
        $activityLabel = $activityName !== '' ? $activityName : 'Kegiatan Kokurikuler';
        if ($activityTheme !== '') {
            $activityLabel .= ' (Tema: ' . $activityTheme . ')';
        }

        try {
            $connection = Database::connection();
            $connection->beginTransaction();

            foreach ($students as $student) {
                $studentId = (int) ($student['id'] ?? 0);
                if ($studentId <= 0) {
                    continue;
                }

                $studentAssessments = $assessments[$studentId] ?? [];
                $strengthLevel = null;
                $strengthElements = [];
                $areaLevel = null;
                $areaElements = [];
                $notes = [];

                foreach ($elements as $element) {
                    $elementId = (int) ($element['id'] ?? 0);
                    if ($elementId <= 0) {
                        continue;
                    }

                    $assessment = $studentAssessments[$elementId] ?? null;
                    $level = $assessment['capaian_enum'] ?? null;
                    $levelKey = $level !== null && isset($levelOrder[$level]) ? $levelOrder[$level] : null;
                    $subElemen = trim((string) ($element['sub_elemen'] ?? ''));
                    $elementNameParts = array_filter([
                        $element['dimensi_kode'] ?? '',
                        $element['elemen_kode'] ?? '',
                        $element['elemen_nama'] ?? '',
                    ], static fn ($v): bool => trim((string) $v) !== '');
                    $elementName = trim(implode(' ', $elementNameParts));
                    if ($elementName === '') {
                        $elementName = 'Elemen kokurikuler';
                    }
                    if ($subElemen !== '') {
                        $elementName .= ' - ' . $subElemen;
                    }

                    if ($levelKey !== null) {
                        if ($strengthLevel === null || $levelKey > $strengthLevel) {
                            $strengthLevel = $levelKey;
                            $strengthElements = [$elementName];
                        } elseif ($levelKey === $strengthLevel) {
                            $strengthElements[] = $elementName;
                        }

                        if ($areaLevel === null || $levelKey < $areaLevel) {
                            $areaLevel = $levelKey;
                            $areaElements = [$elementName];
                        } elseif ($levelKey === $areaLevel) {
                            $areaElements[] = $elementName;
                        }
                    }

                    $note = trim((string) ($assessment['catatan'] ?? ''));
                    if ($note !== '') {
                        $notes[] = $note;
                    }
                }

                $deskripsi = is_string($deskripsiInput[$studentId] ?? null) ? trim((string) $deskripsiInput[$studentId]) : '';
                $tindakLanjut = is_string($tindakInput[$studentId] ?? null) ? trim((string) $tindakInput[$studentId]) : '';

                if ($deskripsi === '') {
                    $parts = [];
                    $parts[] = $activityLabel;

                    if ($strengthLevel !== null) {
                        $levelCode = array_search($strengthLevel, $levelOrder, true);
                        $label = $levelCode !== false && isset($labels[$levelCode]) ? $labels[$levelCode] : null;
                        $parts[] = 'Kekuatan: ' . implode(', ', array_slice(array_unique($strengthElements), 0, 3)) . ($label !== null ? ' (' . $label . ')' : '');
                    }

                    if ($areaLevel !== null && $areaLevel !== $strengthLevel) {
                        $levelCode = array_search($areaLevel, $levelOrder, true);
                        $label = $levelCode !== false && isset($labels[$levelCode]) ? $labels[$levelCode] : null;
                        $parts[] = 'Perlu penguatan: ' . implode(', ', array_slice(array_unique($areaElements), 0, 3)) . ($label !== null ? ' (' . $label . ')' : '');
                    }

                    if (!empty($notes)) {
                        $parts[] = 'Catatan guru: ' . implode('; ', array_slice(array_unique($notes), 0, 3));
                    }

                    $deskripsi = !empty($parts) ? implode(' | ', $parts) : ('Mengikuti ' . $activityLabel);
                }

                if ($tindakLanjut === '') {
                    if (!empty($areaElements)) {
                        $tindakLanjut = 'Fokus penguatan pada ' . implode(', ', array_slice(array_unique($areaElements), 0, 2)) . ' melalui latihan rutin dan pendampingan.';
                    } elseif (!empty($strengthElements)) {
                        $tindakLanjut = 'Pertahankan capaian pada ' . implode(', ', array_slice(array_unique($strengthElements), 0, 2)) . ' dan berikan tantangan lanjutan.';
                    }
                }

                CocurricularSummary::upsert(
                    $activityId,
                    $studentId,
                    $deskripsi !== '' ? $deskripsi : null,
                    $tindakLanjut !== '' ? $tindakLanjut : null
                );
            }

            $connection->commit();
            Session::flash('success', 'Ringkasan kokurikuler disimpan.');
        } catch (Throwable $exception) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollBack();
            }
            Session::flash('error', 'Gagal menyimpan ringkasan: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }
}
