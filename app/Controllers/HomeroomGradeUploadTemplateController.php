<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\SubjectCompetency;
use App\Models\SubjectLearningObjective;
use App\Models\SubjectTeacher;
use App\Models\SubjectTeacherClass;
use App\Services\GradeRescueGuard;
use App\Support\SimpleXlsxBuilder;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class HomeroomGradeUploadTemplateController extends Controller
{
    protected ?string $layout = 'admin';

    public function download(Request $request): Response
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
        $assignmentId = (int) $request->query('assignment_id', 0);

        if ($classId <= 0 || $assignmentId <= 0) {
            Session::flash('error', 'Parameter kelas_id dan assignment_id wajib diisi.');

            return $this->redirect('walikelas/legger');
        }

        $assignment = SubjectTeacher::findWithRelations($assignmentId);
        if ($assignment === null) {
            Session::flash('error', 'Data mapel pengampu tidak ditemukan.');

            return $this->redirect('walikelas/legger');
        }

        $class = Classroom::findWithRelations($classId);
        if ($class === null) {
            Session::flash('error', 'Data kelas tidak ditemukan.');

            return $this->redirect('walikelas/legger');
        }

        $schoolYearId = (int) ($class['tahun_ajaran_id'] ?? 0);
        if (!GradeRescueGuard::canTeacherAccessHomeroomClass($teacherId, $classId, $schoolYearId)) {
            Session::flash('error', 'Anda tidak memiliki akses ke kelas ini.');

            return $this->redirect('walikelas/legger');
        }

        $assignmentClassIds = SubjectTeacherClass::classIds($assignmentId);
        if (!in_array($classId, $assignmentClassIds, true)) {
            Session::flash('error', 'Mapel tersebut tidak terdaftar pada kelas yang dipilih.');

            return $this->redirect('walikelas/legger');
        }

        $semester = $this->resolveSemesterText($class, $assignment);
        if (!GradeRescueGuard::canRescueInput($schoolYearId, $semester)) {
            Session::flash('error', 'Periode rescue input nilai belum aktif untuk konteks ini.');

            return $this->redirect('walikelas/legger');
        }

        $students = Student::byClass($classId, $schoolYearId);
        $curriculum = strtolower(trim((string) ($class['kurikulum'] ?? 'k13')));
        $components = $this->resolveComponents($curriculum, $assignmentId, $classId);
        if (empty($components)) {
            Session::flash('error', $curriculum === 'kurmer'
                ? 'Template Kurmer belum bisa dibuat karena TP belum tersedia pada mapel/kelas ini.'
                : 'Template K13 belum bisa dibuat karena KD pengetahuan belum tersedia pada mapel/kelas ini.');

            return $this->redirect('walikelas/nilai-upload?kelas_id=' . $classId . '&assignment_id=' . $assignmentId);
        }
        $templateVersion = $this->buildTemplateVersion($assignmentId, $classId, $curriculum, $components);
        $requestId = GradeRescueGuard::buildRequestId($request);

        $rows = [];
        $rows[] = ['PETUNJUK'];
        $rows[] = ['1. Jangan ubah nama kolom header.'];
        $rows[] = ['2. Isi nilai angka pada rentang 0 - 100 untuk kolom nilai angka.'];
        $rows[] = ['3. Untuk kolom CAPAIAN gunakan kode: BB, MB, BSH, atau SB.'];
        $rows[] = ['4. Kolom header berwarna kuning adalah area input nilai yang harus diisi.'];
        $rows[] = ['5. Kolom KURIKULUM, TAHUN_AJARAN, SEMESTER, KELAS, MAPEL wajib tetap sesuai konteks template.'];
        $rows[] = ['6. TEMPLATE_VERSION dan CONTEXT_TOKEN wajib ikut saat upload.'];
        $rows[] = [''];

        $header = array_merge(
            ['NISN', 'NIS', 'NAMA', 'KURIKULUM', 'TAHUN_AJARAN', 'SEMESTER', 'KELAS', 'MAPEL'],
            $components,
            ['TEMPLATE_VERSION', 'CONTEXT_TOKEN']
        );
        $headerRowNumber = count($rows) + 1;
        $rows[] = $header;

        $subjectLabel = trim((string) ($assignment['mata_pelajaran_nama'] ?? ''));
        if (($assignment['mata_pelajaran_kode'] ?? '') !== '') {
            $subjectLabel .= ' (' . trim((string) $assignment['mata_pelajaran_kode']) . ')';
        }
        $classLabel = trim((string) (($class['tingkat'] ?? '') . ' ' . ($class['nama'] ?? '')));
        $contextToken = $this->buildContextToken($schoolYearId, $semester, $classId, $assignmentId, $templateVersion, $requestId);

        foreach ($students as $student) {
            $name = trim((string) ($student['nama'] ?? ''));
            if ($name === '') {
                continue;
            }

            $row = [
                (string) ($student['nisn'] ?? ''),
                (string) ($student['nipd'] ?? ''),
                $name,
                strtoupper($curriculum),
                (string) ($class['tahun_ajaran_nama'] ?? ''),
                $semester,
                $classLabel,
                $subjectLabel,
            ];

            foreach ($components as $_) {
                $row[] = '';
            }

            $row[] = $templateVersion;
            $row[] = $contextToken;

            $rows[] = $row;
        }

        $highlightCells = [];
        $scoreStartColumn = 8 + 1; // setelah kolom tetap: NISN..MAPEL (8 kolom)
        $scoreEndColumn = $scoreStartColumn + max(0, count($components) - 1);
        $dataStartRow = $headerRowNumber + 1;
        $dataEndRow = count($rows);
        for ($col = $scoreStartColumn; $col <= $scoreEndColumn; $col++) {
            $columnLetter = $this->columnLetter($col);
            $highlightCells[] = $columnLetter . (string) $headerRowNumber; // header komponen nilai
            for ($row = $dataStartRow; $row <= $dataEndRow; $row++) {
                $highlightCells[] = $columnLetter . (string) $row; // area input nilai siswa
            }
        }

        $xlsxContent = SimpleXlsxBuilder::build($rows, 'Template Nilai', [
            'highlight_cells' => $highlightCells,
        ]);
        $filename = $this->buildFileName($classLabel, $subjectLabel);

        GradeRescueGuard::log('Generated grade upload template', [
            'request_id' => $requestId,
            'teacher_id' => $teacherId,
            'user_id' => (int) ($user['id'] ?? 0),
            'kelas_id' => $classId,
            'assignment_id' => $assignmentId,
            'school_year_id' => $schoolYearId,
            'semester' => $semester,
            'template_version' => $templateVersion,
        ]);

        return Response::make($xlsxContent, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param array<string, mixed> $class
     * @param array<string, mixed> $assignment
     */
    private function resolveSemesterText(array $class, array $assignment): string
    {
        $semesterNumber = (int) ($assignment['mata_pelajaran_tahun_ajaran_semester'] ?? ($class['tahun_ajaran_semester'] ?? 1));

        return $semesterNumber === 2 ? 'genap' : 'ganjil';
    }

    /**
     * @return array<int, string>
     */
    private function resolveComponents(string $curriculum, int $assignmentId, int $classId): array
    {
        if ($curriculum === 'kurmer') {
            $objectives = SubjectLearningObjective::byAssignment($assignmentId, $classId);
            if (empty($objectives)) {
                return [];
            }

            $components = [];
            foreach ($objectives as $objective) {
                $id = (int) ($objective['id'] ?? 0);
                $code = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', (string) ($objective['kode_tp'] ?? ('TP' . $id))));
                if ($id <= 0) {
                    continue;
                }
                $components[] = 'TP_' . $id . '_' . $code . '_CAPAIAN';
                $components[] = 'TP_' . $id . '_' . $code . '_NILAI';
            }
            $components[] = 'CAPAIAN_AKHIR';
            $components[] = 'DESKRIPSI_UMUM';
            $components[] = 'TINDAK_LANJUT';
            $components[] = 'NILAI_OPSIONAL';

            return $components;
        }

        $competencies = SubjectCompetency::byAssignment($assignmentId, 'pengetahuan', $classId);
        if (empty($competencies)) {
            return [];
        }

        $components = [];
        foreach ($competencies as $competency) {
            $id = (int) ($competency['id'] ?? 0);
            $code = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', (string) ($competency['kode'] ?? ('KD' . $id))));
            if ($id <= 0) {
                continue;
            }
            $components[] = 'KD_' . $id . '_' . $code;
        }
        $components[] = 'UTS';
        $components[] = 'UAS';

        return $components;
    }

    /**
     * @param array<int, string> $components
     */
    private function buildTemplateVersion(int $assignmentId, int $classId, string $curriculum, array $components): string
    {
        $signature = implode('|', [
            'stage2',
            (string) $assignmentId,
            (string) $classId,
            $curriculum,
            implode(',', $components),
            date('Ymd'),
        ]);

        return 'TPL-' . strtoupper(substr(sha1($signature), 0, 12));
    }

    private function buildContextToken(
        int $schoolYearId,
        string $semester,
        int $classId,
        int $assignmentId,
        string $templateVersion,
        string $requestId
    ): string {
        $payload = json_encode([
            'school_year_id' => $schoolYearId,
            'semester' => $semester,
            'class_id' => $classId,
            'assignment_id' => $assignmentId,
            'template_version' => $templateVersion,
            'request_id' => $requestId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($payload) || $payload === '') {
            return '';
        }

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    private function buildFileName(string $classLabel, string $subjectLabel): string
    {
        $base = 'template-nilai-' . $classLabel . '-' . $subjectLabel . '-' . date('Ymd-His');
        $base = strtolower($base);
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? 'template-nilai';
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'template-nilai';
        }

        return $base . '.xlsx';
    }

    private function columnLetter(int $columnNumber): string
    {
        $letter = '';

        while ($columnNumber > 0) {
            $remainder = ($columnNumber - 1) % 26;
            $letter = chr(65 + $remainder) . $letter;
            $columnNumber = (int) (($columnNumber - $remainder) / 26);
        }

        return $letter;
    }
}
