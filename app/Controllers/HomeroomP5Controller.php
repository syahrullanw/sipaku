<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\P5Dimension;
use App\Models\P5Project;
use App\Models\P5ProjectElement;
use App\Models\P5StudentAssessment;
use App\Models\P5StudentSummary;
use App\Models\SchoolYear;
use App\Models\SchoolProfile;
use App\Models\Student;
use App\Models\DigitalDocumentSignature;
use App\Models\Teacher;
use App\Services\AssessmentEvaluator;
use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Throwable;
use DateTimeImmutable;

class HomeroomP5Controller extends Controller
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
        $classes = $activeYear !== null
            ? Classroom::homeroomClassesForTeacher($teacherId, (int) ($activeYear['id'] ?? 0))
            : [];

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

        $projects = [];
        $elements = [];
        $students = [];
        $assessments = [];
        $summaries = [];
        $dimensions = P5Dimension::allWithElements();
        $selectedProjectId = (int) $request->query('projek_id', 0);

        if ($selectedClass !== null) {
            $projects = P5Project::byClass($selectedClassId);

            if (!empty($projects) && $selectedProjectId <= 0) {
                $selectedProjectId = (int) ($projects[0]['id'] ?? 0);
            }

            if ($selectedProjectId > 0) {
                $elements = P5ProjectElement::byProject($selectedProjectId);
                $students = Student::byClass($selectedClassId, (int) ($selectedClass['tahun_ajaran_id'] ?? null));
                $studentIds = array_values(array_filter(array_map(static fn ($s) => (int) ($s['id'] ?? 0), $students)));
                if (!empty($studentIds)) {
                    $assessments = P5StudentAssessment::mapByProject($selectedProjectId, $studentIds);
                    $summaries = P5StudentSummary::byProject($selectedProjectId, $studentIds);
                }
            }
        }

        return $this->render('homeroom/p5/index', [
            'title' => 'Projek P5',
            'pageTitle' => 'Penilaian Projek P5',
            'activeMenu' => 'homeroom-ledger',
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'projects' => $projects,
            'selectedProjectId' => $selectedProjectId > 0 ? $selectedProjectId : null,
            'elements' => $elements,
            'students' => $students,
            'assessments' => $assessments,
            'summaries' => $summaries,
            'dimensions' => $dimensions,
        ]);
    }

    public function printIndex(Request $request): Response
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
        $classes = $activeYear !== null
            ? Classroom::homeroomClassesForTeacher($teacherId, (int) ($activeYear['id'] ?? 0))
            : [];

        if (empty($classes)) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId);
        }

        $classes = array_values(array_filter($classes, static function ($class) {
            return strtolower((string) ($class['kurikulum'] ?? 'k13')) === 'kurmer';
        }));

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

        $students = [];
        $selectedStudentId = (int) $request->query('siswa_id', 0);
        $digitalSignatureEnabled = false;
        $digitalSignatureSummary = [
            'total' => 0,
            'requested' => 0,
            'pending' => 0,
            'approved' => 0,
            'revoked' => 0,
            'not_requested' => 0,
            'canRequest' => false,
        ];
        $selectedStudentSignature = null;

        if ($selectedClass !== null) {
            $classYearId = (int) ($selectedClass['tahun_ajaran_id'] ?? 0);
            $students = Student::byClass($selectedClassId, $classYearId > 0 ? $classYearId : null);

            if (!empty($students)) {
                $studentExists = false;
                foreach ($students as $student) {
                    if ((int) ($student['id'] ?? 0) === $selectedStudentId) {
                        $studentExists = true;
                        break;
                    }
                }

                if (!$studentExists) {
                    $selectedStudentId = (int) ($students[0]['id'] ?? 0);
                }
            } else {
                $selectedStudentId = 0;
            }

            $activeYear = SchoolYear::active();
            $activeYearId = (int) ($activeYear['id'] ?? 0);
            $digitalSignatureEnabled = $activeYear !== null
                && $activeYearId > 0
                && (int) ($activeYear['digital_signature_enabled'] ?? 0) === 1
                && $activeYearId === $classYearId;

            if ($digitalSignatureEnabled) {
                $digitalSignatureMap = DigitalDocumentSignature::mapByClass($activeYearId, $selectedClassId, 'p5_report');
                $digitalSignatureSummary['total'] = count($students);
                foreach ($digitalSignatureMap as $record) {
                    $digitalSignatureSummary['requested']++;
                    $status = (string) ($record['status'] ?? 'pending');
                    if ($status === 'approved') {
                        $digitalSignatureSummary['approved']++;
                    } elseif ($status === 'revoked') {
                        $digitalSignatureSummary['revoked']++;
                    } else {
                        $digitalSignatureSummary['pending']++;
                    }
                }
                $digitalSignatureSummary['not_requested'] = max(0, $digitalSignatureSummary['total'] - $digitalSignatureSummary['requested']);
                if ($selectedStudentId > 0 && isset($digitalSignatureMap[$selectedStudentId])) {
                    $selectedStudentSignature = $digitalSignatureMap[$selectedStudentId];
                }
            }
        }

        $digitalSignatureSummary['canRequest'] = $digitalSignatureEnabled && $selectedClass !== null && $selectedStudentId > 0 && !empty($students);
        $digitalSignatureSummary['canRequestClass'] = $digitalSignatureEnabled && $selectedClass !== null && !empty($students) && ($digitalSignatureSummary['not_requested'] ?? 0) > 0;

        return $this->render('homeroom/p5/print', [
            'title' => 'Cetak Rapor P5',
            'pageTitle' => 'Cetak Rapor P5',
            'activeMenu' => 'homeroom-p5-print',
            'classes' => $classes,
            'selectedClass' => $selectedClass,
            'selectedClassId' => $selectedClassId,
            'students' => $students,
            'selectedStudentId' => $selectedStudentId,
            'digitalSignatureEnabled' => $digitalSignatureEnabled,
            'digitalSignatureSummary' => $digitalSignatureSummary,
            'selectedStudentSignature' => $selectedStudentSignature,
        ]);
    }

    public function printReport(Request $request): Response
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
        $classId = (int) $request->query('kelas_id', 0);
        $studentId = (int) $request->query('siswa_id', 0);
        $paperSize = strtolower((string) $request->query('paper', 'f4'));
        if (!in_array($paperSize, ['f4', 'a4'], true)) {
            $paperSize = 'f4';
        }

        if ($classId <= 0 || $studentId <= 0) {
            return $this->render('reports/sections/error', [
                'title' => 'Cetak Rapor P5',
                'message' => 'Pilih kelas dan siswa terlebih dahulu.',
            ], 'print');
        }

        $class = Classroom::findWithRelations($classId);

        if ($class === null || (int) ($class['wali_kelas_id'] ?? 0) !== $teacherId) {
            return $this->render('reports/sections/error', [
                'title' => 'Cetak Rapor P5',
                'message' => 'Data kelas tidak ditemukan atau akses ditolak.',
            ], 'print');
        }

        if (strtolower((string) ($class['kurikulum'] ?? 'k13')) !== 'kurmer') {
            return $this->render('reports/sections/error', [
                'title' => 'Cetak Rapor P5',
                'message' => 'Rapor P5 hanya tersedia untuk kelas Kurikulum Merdeka.',
            ], 'print');
        }

        $student = Student::findWithRelations($studentId);

        if ($student === null || (int) ($student['kelas_id'] ?? 0) !== $classId) {
            return $this->render('reports/sections/error', [
                'title' => 'Cetak Rapor P5',
                'message' => 'Siswa tidak terdaftar pada kelas yang dipilih.',
            ], 'print');
        }

        $schoolYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
        if ($schoolYearId > 0 && (int) ($student['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            return $this->render('reports/sections/error', [
                'title' => 'Cetak Rapor P5',
                'message' => 'Tahun ajaran siswa tidak cocok dengan kelas yang dipilih.',
            ], 'print');
        }

        $projects = $this->collectP5AssessmentsForPrint($classId, $studentId, $schoolYearId);
        $schoolYear = $schoolYearId > 0 ? SchoolYear::find($schoolYearId) : null;
        $schoolProfile = SchoolProfile::first() ?? [];
        $printedDateLabel = $this->resolvePrintedDateLabelForP5($class, $schoolProfile, $schoolYear);
        $kabupaten = isset($class['kabupaten']) ? (string) $class['kabupaten'] : '';
        $report = [
            'student' => $student,
            'class' => $class,
            'schoolYear' => $schoolYear,
            'schoolYearId' => $schoolYearId,
            'school' => $schoolProfile,
            'p5_projects' => $projects,
        ];

        $report = $this->attachDigitalSignatureForP5($report, 'p5_report');

        return $this->render('reports/sections/p5', [
            'title' => 'Cetak Rapor P5',
            'report' => $report,
            'paperSize' => $paperSize,
            'printedDateLabel' => $printedDateLabel,
            'kabupatenLabel' => $kabupaten !== '' ? $kabupaten : ($schoolProfile['kabupaten'] ?? '-'),
        ], 'print');
    }

    public function storeProject(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/p5')) {
            return $response;
        }

        $teacherId = (int) (auth()['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            Session::flash('error', 'Anda tidak terdaftar sebagai guru.');

            return $this->redirect('walikelas/p5');
        }

        $classId = (int) $request->input('kelas_id', 0);
        $redirectUrl = 'walikelas/p5';
        if ($classId > 0) {
            $redirectUrl .= '?kelas_id=' . $classId;
        }

        $class = Classroom::find($classId);
        if ($class === null) {
            Session::flash('error', 'Kelas tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        $payload = [
            'tahun_ajaran_id' => (int) ($class['tahun_ajaran_id'] ?? 0),
            'kelas_id' => $classId,
            'tema' => trim((string) $request->input('tema', '')),
            'judul' => trim((string) $request->input('judul', '')),
            'deskripsi' => trim((string) $request->input('deskripsi', '')),
            'tanggal_mulai' => $request->input('tanggal_mulai') ?: null,
            'tanggal_selesai' => $request->input('tanggal_selesai') ?: null,
            'guru_pembimbing_id' => $teacherId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($payload['tema'] === '' || $payload['judul'] === '') {
            Session::flash('error', 'Tema dan judul projek wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect($redirectUrl);
        }

        P5Project::create($payload);
        Session::flash('success', 'Projek P5 berhasil dibuat.');

        return $this->redirect($redirectUrl);
    }

    public function storeElement(Request $request, int $projectId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/p5/elemen/' . $projectId)) {
            return $response;
        }

        $project = P5Project::find($projectId);
        if ($project === null) {
            Session::flash('error', 'Projek tidak ditemukan.');

            return $this->redirect('walikelas/p5');
        }

        $redirectUrl = 'walikelas/p5?kelas_id=' . $project['kelas_id'] . '&projek_id=' . $projectId;

        $elemenId = (int) $request->input('elemen_id', 0);
        $tpDeskripsi = trim((string) $request->input('tp_deskripsi', ''));
        $urutan = $request->input('urutan') !== null ? (int) $request->input('urutan') : null;

        P5ProjectElement::create([
            'projek_id' => $projectId,
            'elemen_id' => $elemenId > 0 ? $elemenId : null,
            'tp_deskripsi' => $tpDeskripsi !== '' ? $tpDeskripsi : null,
            'urutan' => $urutan,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Elemen projek ditambahkan.');

        return $this->redirect($redirectUrl);
    }

    public function deleteElement(Request $request, int $projectId, int $elementId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/p5/elemen/' . $projectId . '/' . $elementId . '/hapus')) {
            return $response;
        }

        $project = P5Project::find($projectId);
        if ($project === null) {
            Session::flash('error', 'Projek tidak ditemukan.');

            return $this->redirect('walikelas/p5');
        }

        $redirectUrl = 'walikelas/p5?kelas_id=' . $project['kelas_id'] . '&projek_id=' . $projectId;

        $element = P5ProjectElement::find($elementId);
        if ($element === null || (int) ($element['projek_id'] ?? 0) !== $projectId) {
            Session::flash('error', 'Elemen tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        $deleted = P5ProjectElement::deleteById($elementId);
        Session::flash($deleted ? 'success' : 'error', $deleted ? 'Elemen projek dihapus.' : 'Elemen projek gagal dihapus.');

        return $this->redirect($redirectUrl);
    }

    public function storeAssessments(Request $request, int $projectId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/p5/penilaian/' . $projectId)) {
            return $response;
        }

        $project = P5Project::find($projectId);
        if ($project === null) {
            Session::flash('error', 'Projek tidak ditemukan.');

            return $this->redirect('walikelas/p5');
        }

        $redirectUrl = 'walikelas/p5?kelas_id=' . $project['kelas_id'] . '&projek_id=' . $projectId;
        $classId = (int) ($project['kelas_id'] ?? 0);

        $elements = P5ProjectElement::byProject($projectId);
        $students = Student::byClass($classId, (int) ($project['tahun_ajaran_id'] ?? null));

        if (empty($elements) || empty($students)) {
            Session::flash('error', 'Lengkapi elemen projek dan siswa terlebih dahulu.');

            return $this->redirect($redirectUrl);
        }

        $capaianInput = $request->input('capaian', []);
        $nilaiInput = $request->input('nilai', []);
        $catatanInput = $request->input('catatan', []);

        try {
            $connection = Database::connection();
            $connection->beginTransaction();

            foreach ($students as $student) {
                $studentId = (int) ($student['id'] ?? 0);
                if ($studentId <= 0) {
                    continue;
                }

                if (!Student::hasActiveStatus($student)) {
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

                    $nilai = AssessmentEvaluator::normalizeScore($nilaiInput[$studentId][$elementId] ?? null);
                    $catatan = $catatanInput[$studentId][$elementId] ?? null;

                    P5StudentAssessment::create([
                        'projek_id' => $projectId,
                        'projek_elemen_id' => $elementId,
                        'siswa_id' => $studentId,
                        'capaian_enum' => $capaian,
                        'catatan' => is_string($catatan) && trim($catatan) !== '' ? trim($catatan) : null,
                        'nilai_opsional' => $nilai,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ], true);
                }
            }

            $connection->commit();
            Session::flash('success', 'Penilaian P5 disimpan.');
        } catch (Throwable $exception) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollBack();
            }
            Session::flash('error', 'Gagal menyimpan penilaian: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    public function storeSummaries(Request $request, int $projectId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/p5/ringkasan/' . $projectId)) {
            return $response;
        }

        $project = P5Project::find($projectId);
        if ($project === null) {
            Session::flash('error', 'Projek tidak ditemukan.');

            return $this->redirect('walikelas/p5');
        }

        $redirectUrl = 'walikelas/p5?kelas_id=' . $project['kelas_id'] . '&projek_id=' . $projectId;
        $classId = (int) ($project['kelas_id'] ?? 0);
        $students = Student::byClass($classId, (int) ($project['tahun_ajaran_id'] ?? null));
        $elements = P5ProjectElement::byProject($projectId);
        $assessments = [];
        $studentIds = array_values(array_filter(array_map(static fn ($s) => (int) ($s['id'] ?? 0), $students)));
        if (!empty($studentIds)) {
            $assessments = P5StudentAssessment::mapByProject($projectId, $studentIds);
        }

        $capaianInput = $request->input('capaian_akhir', []);
        $deskripsiInput = $request->input('deskripsi_umum', []);
        $tindakInput = $request->input('tindak_lanjut', []);
        $nilaiInput = $request->input('nilai_opsional', []);
        $labels = [
            'BB' => 'Belum Berkembang',
            'MB' => 'Mulai Berkembang',
            'BSH' => 'Berkembang Sesuai Harapan',
            'SB' => 'Sangat Berkembang',
        ];
        $projectTitle = trim((string) ($project['judul'] ?? ''));
        $projectTema = trim((string) ($project['tema'] ?? ''));
        $projectLabel = $projectTitle !== '' ? $projectTitle : 'Projek P5';
        if ($projectTema !== '') {
            $projectLabel .= ' (Tema: ' . $projectTema . ')';
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
                $elementSummaries = [];
                $elementNames = [];
                $elementDescriptions = [];
                foreach ($elements as $element) {
                    $elementId = (int) ($element['id'] ?? 0);
                    if ($elementId <= 0) {
                        continue;
                    }

                    $assessment = $studentAssessments[$elementId] ?? null;
                    $elementLabel = trim(implode(' ', array_filter([
                        $element['elemen_kode'] ?? '',
                        $element['elemen_nama'] ?? '',
                    ], static fn ($v): bool => trim((string) $v) !== '')));
                    if ($elementLabel === '') {
                        $elementLabel = 'Elemen projek';
                    }

                    $elementCapaianLabel = $assessment !== null
                        ? ($labels[$assessment['capaian_enum'] ?? ''] ?? null)
                        : null;
                    $detailParts = [];
                    if ($elementCapaianLabel !== null) {
                        $detailParts[] = $elementCapaianLabel;
                    }
                    $elementNote = trim((string) ($assessment['catatan'] ?? ''));
                    $tpDescription = trim((string) ($element['tp_deskripsi'] ?? ''));
                    if ($elementNote !== '') {
                        $detailParts[] = $elementNote;
                    } elseif ($tpDescription !== '') {
                        $detailParts[] = $tpDescription;
                    }

                    if (!empty($detailParts)) {
                        $elementSummaries[] = $elementLabel . ' (' . implode('; ', $detailParts) . ')';
                    } else {
                        $elementSummaries[] = $elementLabel;
                    }
                    $elementNames[] = $elementLabel;
                    if ($tpDescription !== '') {
                        $elementDescriptions[] = $tpDescription;
                    }
                }

                $capaian = AssessmentEvaluator::normalizeKurmerCapaian($capaianInput[$studentId] ?? null);
                $deskripsi = is_string($deskripsiInput[$studentId] ?? null) ? trim((string) $deskripsiInput[$studentId]) : null;
                $tindak = is_string($tindakInput[$studentId] ?? null) ? trim((string) $tindakInput[$studentId]) : null;
                $nilai = AssessmentEvaluator::normalizeScore($nilaiInput[$studentId] ?? null);

                $label = $capaian !== null ? ($labels[$capaian] ?? $capaian) : null;

                if ($capaian !== null && ($deskripsi === null || $deskripsi === '')) {
                    $deskripsiParts = [];
                    $deskripsiParts[] = 'Projek ' . $projectLabel;
                    if ($label !== null) {
                        $deskripsiParts[] = 'Capaian akhir: ' . $label;
                    }
                    if (!empty($elementSummaries)) {
                        $deskripsiParts[] = 'Elemen yang diikuti: ' . implode('; ', array_slice($elementSummaries, 0, 4));
                    } elseif (!empty($elementDescriptions)) {
                        $deskripsiParts[] = 'Fokus projek: ' . implode('; ', array_slice($elementDescriptions, 0, 3));
                    } elseif (!empty($elementNames)) {
                        $deskripsiParts[] = 'Elemen yang diikuti: ' . implode(', ', array_slice(array_unique($elementNames), 0, 4));
                    }
                    if (!empty($deskripsiParts)) {
                        $deskripsi = implode('. ', $deskripsiParts) . '.';
                    }
                }

                if ($tindak === null || $tindak === '') {
                    $focusElements = implode(', ', array_slice(array_values(array_unique($elementNames)), 0, 2));
                    $tindakTemplates = [
                        'BB' => 'Perlu pendampingan intensif pada elemen %s melalui latihan rutin dan contoh konkret.',
                        'MB' => 'Perlu latihan berulang di elemen %s dengan umpan balik terarah.',
                        'BSH' => 'Pertahankan capaian di elemen %s dan tambah tantangan terstruktur.',
                        'SB' => 'Teruskan menjadi role model di elemen %s dan eksplorasi tantangan lanjutan.',
                    ];

                    if ($capaian === null) {
                        // Biarkan kosong jika capaian akhir belum dipilih
                        $tindak = $tindak;
                    } elseif ($focusElements !== '' && isset($tindakTemplates[$capaian])) {
                        $tindak = sprintf($tindakTemplates[$capaian], $focusElements);
                    } elseif ($focusElements !== '' && $label !== null) {
                        $tindak = 'Fokus penguatan pada elemen ' . $focusElements . ' agar capaian ' . $label . ' terjaga.';
                    } elseif ($label !== null) {
                        $tindak = 'Fokus menjaga konsistensi capaian ' . $label . ' pada projek berikutnya di ' . $projectLabel . '.';
                    } elseif ($focusElements !== '') {
                        $tindak = 'Fokus penguatan pada elemen ' . $focusElements . ' sesuai catatan projek.';
                    } else {
                        $tindak = 'Lanjutkan pendampingan sesuai kebutuhan projek ' . $projectLabel . '.';
                    }
                }

                P5StudentSummary::upsert(
                    $projectId,
                    $studentId,
                    $capaian,
                    $deskripsi !== '' ? $deskripsi : null,
                    $tindak !== '' ? $tindak : null,
                    $nilai
                );
            }

            $connection->commit();
            Session::flash('success', 'Ringkasan projek disimpan.');
        } catch (Throwable $exception) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollBack();
            }
            Session::flash('error', 'Gagal menyimpan ringkasan: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectP5AssessmentsForPrint(int $classId, int $studentId, int $schoolYearId): array
    {
        if ($classId <= 0 || $studentId <= 0 || $schoolYearId <= 0) {
            return [];
        }

        $projects = array_filter(P5Project::byClass($classId), static function (array $project) use ($schoolYearId): bool {
            return (int) ($project['tahun_ajaran_id'] ?? 0) === $schoolYearId;
        });

        if (empty($projects)) {
            return [];
        }

        $results = [];

        foreach ($projects as $project) {
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            $elements = P5ProjectElement::byProject($projectId);
            $assessments = P5StudentAssessment::mapByProject($projectId, [$studentId]);
            $summaries = P5StudentSummary::byProject($projectId, [$studentId]);
            $summary = $summaries[$studentId] ?? null;

            $elementScores = [];
            foreach ($elements as $element) {
                $eid = (int) ($element['id'] ?? 0);
                if ($eid <= 0) {
                    continue;
                }

                $assessment = $assessments[$studentId][$eid] ?? null;
                $elementScores[] = [
                    'id' => $eid,
                    'code' => $element['elemen_kode'] ?? '',
                    'name' => $element['elemen_nama'] ?? '',
                    'tp' => $element['tp_deskripsi'] ?? null,
                    'capaian' => $assessment['capaian_enum'] ?? null,
                    'nilai' => $assessment['nilai_opsional'] ?? null,
                    'catatan' => $assessment['catatan'] ?? null,
                ];
            }

            $results[] = [
                'id' => $projectId,
                'title' => $project['judul'] ?? '',
                'theme' => $project['tema'] ?? '',
                'description' => $project['deskripsi'] ?? null,
                'start_date' => $project['tanggal_mulai'] ?? null,
                'end_date' => $project['tanggal_selesai'] ?? null,
                'mentor' => $project['guru_pembimbing_nama'] ?? null,
                'elements' => $elementScores,
                'summary' => $summary,
            ];
        }

        return $results;
    }

    private function attachDigitalSignatureForP5(array $context, string $documentType): array
    {
        $studentId = isset($context['student']['id']) ? (int) $context['student']['id'] : 0;
        $schoolYearId = (int) ($context['schoolYearId'] ?? 0);

        $defaultSignature = [
            'enabled' => false,
            'status' => 'inactive',
            'message' => 'TTD digital belum tersedia.',
            'documentType' => $documentType,
        ];

        if ($studentId <= 0 || $schoolYearId <= 0) {
            $defaultSignature['message'] = 'TTD digital membutuhkan data siswa dan tahun ajaran yang valid.';
            $context['digitalSignature'] = $defaultSignature;

            return $context;
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null || (int) ($activeYear['id'] ?? 0) !== $schoolYearId) {
            $defaultSignature['message'] = 'TTD digital hanya tersedia untuk tahun ajaran aktif.';
            $context['digitalSignature'] = $defaultSignature;

            return $context;
        }

        if ((int) ($activeYear['digital_signature_enabled'] ?? 0) !== 1) {
            $defaultSignature['message'] = 'TTD digital belum diaktifkan oleh admin.';
            $context['digitalSignature'] = $defaultSignature;

            return $context;
        }

        $classId = isset($context['class']['id']) ? (int) $context['class']['id'] : 0;
        $documentKey = $this->makeDocumentKeyForP5($documentType, $studentId);
        $documentTitle = $this->makeDocumentTitleForP5($documentType, $context);
        $payload = $this->buildDigitalSignaturePayloadForP5($context, $documentType);
        $requestedBy = (int) (auth()['id'] ?? 0);

        $record = DigitalDocumentSignature::findByDocument(
            (int) $activeYear['id'],
            $documentType,
            $documentKey
        );

        if ($record === null) {
            $record = DigitalDocumentSignature::ensure(
                (int) $activeYear['id'],
                $documentType,
                $documentKey,
                $documentTitle,
                $payload,
                $studentId,
                $classId > 0 ? $classId : null,
                $requestedBy > 0 ? $requestedBy : null,
            );
        }

        if ($record === null) {
            $context['digitalSignature'] = [
                'enabled' => true,
                'status' => 'error',
                'message' => 'Gagal menyiapkan catatan TTD digital.',
                'documentType' => $documentType,
            ];

            return $context;
        }

        $context['digitalSignature'] = $this->formatSignatureRecordForP5($record, $activeYear, $documentType);

        return $context;
    }

    private function makeDocumentKeyForP5(string $documentType, int $studentId): string
    {
        return sprintf('%s:%d', $documentType, $studentId);
    }

    private function makeDocumentTitleForP5(string $documentType, array $context): string
    {
        $studentName = (string) ($context['student']['nama'] ?? 'Siswa');
        $schoolYear = $context['schoolYear'] ?? null;
        $schoolYearName = is_array($schoolYear) ? (string) ($schoolYear['nama'] ?? '') : '';
        $label = ucwords(str_replace('_', ' ', $documentType));

        return sprintf('%s%s - %s', $label, $schoolYearName !== '' ? ' (' . $schoolYearName . ')' : '', $studentName);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function buildDigitalSignaturePayloadForP5(array $context, string $documentType): array
    {
        $student = $context['student'] ?? [];
        $class = $context['class'] ?? [];
        $p5Projects = $context['p5_projects'] ?? [];
        $schoolYear = $context['schoolYear'] ?? null;

        return [
            'document_type' => $documentType,
            'school_year_id' => (int) ($context['schoolYearId'] ?? 0),
            'school_year_name' => is_array($schoolYear) ? (string) ($schoolYear['nama'] ?? '') : '',
            'student' => [
                'id' => (int) ($student['id'] ?? 0),
                'name' => (string) ($student['nama'] ?? ''),
                'nisn' => (string) ($student['nisn'] ?? ''),
                'nipd' => (string) ($student['nipd'] ?? ''),
            ],
            'class' => [
                'id' => (int) ($class['id'] ?? 0),
                'name' => (string) ($class['nama'] ?? ''),
                'level' => (string) ($class['tingkat'] ?? ''),
            ],
            'p5_projects' => $p5Projects,
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $activeYear
     */
    private function formatSignatureRecordForP5(array $record, array $activeYear, string $documentType): array
    {
        $payload = [];

        if (isset($record['payload']) && is_string($record['payload'])) {
            $decoded = json_decode($record['payload'], true);

            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $status = (string) ($record['status'] ?? 'pending');

        $approvedAtLabel = '';
        $approvedAtRaw = $record['approved_at'] ?? null;

        if (is_string($approvedAtRaw) && $approvedAtRaw !== '') {
            $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $approvedAtRaw);

            if ($dateTime !== false) {
                $approvedAtLabel = $this->formatIndonesianDate($dateTime->format('Y-m-d')) . ' ' . $dateTime->format('H:i');
            } else {
                $approvedAtLabel = $approvedAtRaw;
            }
        }

        $headmasterName = $this->resolveHeadmasterNameForP5($activeYear);

        $message = 'Menunggu persetujuan kepala sekolah.';

        if ($status === 'approved') {
            $message = 'TTD digital telah disetujui kepala sekolah.';
        } elseif ($status === 'revoked') {
            $message = 'TTD digital telah dicabut oleh kepala sekolah.';
        } elseif ($status === 'error') {
            $message = 'Terjadi kesalahan pada catatan TTD digital.';
        }

        $statusLabel = 'Menunggu Persetujuan';

        switch ($status) {
            case 'approved':
                $statusLabel = 'Disetujui';
                break;
            case 'revoked':
                $statusLabel = 'Dicabut';
                break;
            case 'error':
                $statusLabel = 'Terjadi Kesalahan';
                break;
        }

        $token = $record['signature_token'] ?? null;
        $verificationUrl = $token !== null && $token !== '' ? absolute_url('dokumen/validasi/' . $token) : null;

        return [
            'enabled' => true,
            'status' => $status,
            'statusLabel' => $statusLabel,
            'message' => $message,
            'documentType' => $documentType,
            'documentTitle' => (string) ($record['document_title'] ?? ''),
            'headmasterName' => $headmasterName,
            'signatureToken' => $token,
            'verificationUrl' => $verificationUrl,
            'approvalNote' => $record['approval_note'] ?? null,
            'approvedAt' => $record['approved_at'] ?? null,
            'approvedAtLabel' => $approvedAtLabel,
            'payload' => $payload,
        ];
    }

    private function formatIndonesianDate(?string $date): string
    {
        if ($date === null || $date === '' || $date === '0000-00-00') {
            return '';
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return '';
        }

        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $month = $months[(int) date('n', $timestamp)] ?? date('F', $timestamp);

        return sprintf('%d %s %s', (int) date('j', $timestamp), $month, date('Y', $timestamp));
    }

    private function resolvePrintedDateLabelForP5(array $class, array $schoolProfile = [], ?array $schoolYear = null): string
    {
        $printedAt = new DateTimeImmutable();
        $levelRaw = $class['tingkat'] ?? null;
        $level = is_numeric($levelRaw) ? (int) $levelRaw : 0;
        if ($level === 0 && isset($class['nama']) && is_string($class['nama'])) {
            $nameUpper = strtoupper((string) $class['nama']);
            if (strpos($nameUpper, 'XII') !== false) {
                $level = 12;
            } elseif (strpos($nameUpper, 'XI') !== false) {
                $level = 11;
            } elseif (strpos($nameUpper, 'X') !== false) {
                $level = 10;
            }
        }

        // Utamakan tanggal rapor dari tahun ajaran aktif, lalu fallback ke profil sekolah.
        $candidates = [];
        if (is_array($schoolYear)) {
            if ($level === 12) {
                $candidates[] = $schoolYear['tanggal_raport_tingkat_12'] ?? null;
            }
            $candidates[] = $schoolYear['tanggal_raport_tingkat_10_11'] ?? null;
            if ($level !== 12) {
                $candidates[] = $schoolYear['tanggal_raport_tingkat_12'] ?? null;
            }
        }
        if (!empty($schoolProfile)) {
            if ($level === 12) {
                $candidates[] = $schoolProfile['tanggal_raport_tingkat_12'] ?? null;
            }
            $candidates[] = $schoolProfile['tanggal_raport_tingkat_10_11'] ?? null;
            if ($level !== 12) {
                $candidates[] = $schoolProfile['tanggal_raport_tingkat_12'] ?? null;
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeDateInput($candidate);
            if ($normalized !== null) {
                $formatted = $this->formatIndonesianDate($normalized);
                if ($formatted !== '') {
                    return $formatted;
                }
            }
        }

        return $this->formatIndonesianDate($printedAt->format('Y-m-d'));
    }

    private function normalizeDateInput($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        $patterns = [
            'Y-m-d',
            'd-m-Y',
            'd/m/Y',
        ];

        foreach ($patterns as $pattern) {
            $input = $value;
            if ($pattern === 'Y-m-d' || $pattern === 'd-m-Y') {
                $input = str_replace('/', '-', $value);
            } elseif ($pattern === 'd/m/Y') {
                $input = str_replace('-', '/', $value);
            }

            $dt = DateTimeImmutable::createFromFormat($pattern, $input);
            if ($dt instanceof DateTimeImmutable) {
                return $dt->format('Y-m-d');
            }
        }

        $ts = strtotime($value);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return null;
    }

    private function resolveHeadmasterNameForP5(?array $activeYear = null): string
    {
        if ($activeYear !== null) {
            $headmasterId = isset($activeYear['kepala_sekolah_id']) ? (int) $activeYear['kepala_sekolah_id'] : 0;
            if ($headmasterId > 0) {
                $teacher = Teacher::find($headmasterId);
                if ($teacher !== null && isset($teacher['nama'])) {
                    $name = trim((string) $teacher['nama']);
                    if ($name !== '') {
                        return $name;
                    }
                }
            }
            $name = trim((string) ($activeYear['kepala_sekolah_nama'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        $schoolProfile = SchoolProfile::first();
        $name = trim((string) ($schoolProfile['kepala_sekolah'] ?? ''));
        return $name !== '' ? $name : '________________';
    }
}
