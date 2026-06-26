<?php

namespace Modules\Finance\Controllers\Bendahara;

use App\Models\AcademicPosition;
use App\Models\Teacher;
use App\Models\TeacherHonor;
use App\Models\TeacherLoan;
use App\Models\TeacherSalaryComponent;
use App\Models\TeacherSalaryRecord;
use App\Models\TeacherSalarySetting;
use App\Models\WhatsappGatewaySetting;
use App\Services\Finance\GeneralCashService;
use App\Services\Finance\HonorService;
use App\Services\Finance\TeacherSalaryService;
use App\Services\Finance\TeacherSalarySlipExporter;
use App\Services\WhatsappGatewayService;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\View;
use Modules\Finance\Controllers\Controller;

class TeacherSalaryController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $section = (string) ($request->input('section') ?? 'payroll');
        if (!in_array($section, ['payroll', 'settings'], true)) {
            $section = 'payroll';
        }

        $schoolYearId = $this->activeSchoolYearId();
        $settings = $schoolYearId !== null ? TeacherSalarySetting::bySchoolYear($schoolYearId) : [];
        $settingsByCode = $this->mapSettingsByCode($settings);
        $settingsContext = $this->buildSettingsContext($schoolYearId, $settingsByCode);

        $periodInput = (string) ($request->input('periode') ?? date('Y-m'));
        $period = $this->sanitizePeriod($periodInput);
        $teacherId = (int) ($request->input('teacher_id') ?? 0);

        $teacherOptions = [];
        foreach (Teacher::allOrdered() as $teacherRow) {
            $teacherOptions[] = [
                'id' => (int) ($teacherRow['id'] ?? 0),
                'name' => (string) ($teacherRow['nama'] ?? 'Guru'),
                'status' => (string) ($teacherRow['status'] ?? 'aktif'),
            ];
        }

        $availablePeriods = $schoolYearId !== null ? TeacherSalaryRecord::periods($schoolYearId) : [];
        if ($period !== '' && !in_array($period, $availablePeriods, true)) {
            array_unshift($availablePeriods, $period);
        }

        $recordSummaries = [];
        if ($schoolYearId !== null && $period !== '') {
            $recordSummaries = TeacherSalaryRecord::listByPeriod($schoolYearId, $period);
        }

        $selectedTeacher = null;
        foreach ($teacherOptions as $option) {
            if ($option['id'] === $teacherId) {
                $selectedTeacher = $option;
                break;
            }
        }

        $salaryForm = null;
        if ($schoolYearId !== null && $selectedTeacher !== null && $period !== '') {
            $existingRecord = TeacherSalaryRecord::findByTeacherAndPeriod($teacherId, $schoolYearId, $period);
            $existingComponents = [];
            if ($existingRecord !== null) {
                $existingComponents = TeacherSalaryComponent::byRecord((int) $existingRecord['id']);
            }

            $specialCounts = $this->fetchSpecialRoleCounts($teacherId, $schoolYearId);
            $academicAssignments = $this->fetchAcademicAssignments($teacherId, $schoolYearId);
            $activeLoans = [];
            foreach (TeacherLoan::findActiveByTeacher($teacherId) as $loan) {
                $outstanding = (float) ($loan['saldo_terhutang'] ?? 0.0);
                if ($outstanding <= 0.0) {
                    continue;
                }

                if ($schoolYearId !== null && (int) ($loan['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
                    continue;
                }

                $activeLoans[] = $loan;
            }

            $salaryForm = $this->buildSalaryFormContext(
                $selectedTeacher,
                $schoolYearId,
                $period,
                $existingRecord,
                $existingComponents,
                $settingsByCode,
                $specialCounts,
                $academicAssignments,
                $activeLoans
            );
        }

        return $this->render('finance/bendahara/teacher-salaries/index', [
            'title' => 'Penggajian Guru',
            'pageTitle' => 'Input Gaji Guru',
            'activeMenu' => 'finance-bendahara-teacher-salary',
            'section' => $section,
            'hasActiveYear' => $schoolYearId !== null,
            'period' => $period,
            'availablePeriods' => $availablePeriods,
            'teacherOptions' => $teacherOptions,
            'recordSummaries' => $recordSummaries,
            'selectedTeacher' => $selectedTeacher,
            'salaryForm' => $salaryForm,
            'componentTypeLabels' => [
                'activity' => 'Honor Kegiatan',
                'adjustment' => 'Penyesuaian Positif',
                'deduction' => 'Potongan',
            ],
            'settingsContext' => $settingsContext,
        ], 'admin');
    }

    public function storeSettings(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $redirectUrl = 'keuangan/bendahara/gaji-guru?section=settings';

        if ($response = $this->guardCsrfOrRedirect($request, $redirectUrl)) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();

        if ($schoolYearId === null) {
            Session::flash('error', 'Tahun ajaran aktif belum ditetapkan. Tetapkan tahun ajaran sebelum mengatur gaji guru.');

            return $this->redirect($redirectUrl);
        }

        $hourlyRate = max(0.0, $this->normalizeAmount((string) $request->input('hourly_rate', '0')));
        TeacherSalarySetting::upsert(
            $schoolYearId,
            'hourly_rate',
            'Tarif Per Jam Mengajar',
            'hourly_rate',
            $hourlyRate
        );

        $specialInput = (array) $request->input('special', []);
        foreach ($this->specialRoles() as $key => $definition) {
            $amount = max(0.0, $this->normalizeAmount((string) ($specialInput[$key] ?? '0')));
            TeacherSalarySetting::upsert(
                $schoolYearId,
                $definition['code'],
                $definition['label'],
                'special_role',
                $amount
            );
        }

        $positionInput = (array) $request->input('positions', []);
        if (!empty($positionInput)) {
            foreach ($positionInput as $positionId => $rawAmount) {
                $positionId = (int) $positionId;
                if ($positionId <= 0) {
                    continue;
                }

                $position = AcademicPosition::find($positionId);
                if ($position === null || ($position['kategori'] ?? 'guru') !== 'guru') {
                    continue;
                }

                $amount = max(0.0, $this->normalizeAmount((string) $rawAmount));

                TeacherSalarySetting::upsert(
                    $schoolYearId,
                    $this->academicPositionCode($positionId),
                    (string) ($position['nama'] ?? 'Jabatan Akademik'),
                    'academic_position',
                    $amount,
                    $positionId
                );
            }
        }

        $existingActivityRecords = TeacherSalarySetting::bySchoolYear($schoolYearId);
        $activitiesById = [];
        foreach ($existingActivityRecords as $record) {
            if (($record['category'] ?? '') === 'activity') {
                $activitiesById[(int) ($record['id'] ?? 0)] = $record;
            }
        }

        $activitiesExisting = (array) $request->input('activities_existing', []);
        foreach ($activitiesExisting as $activityId => $activityData) {
            $activityId = (int) $activityId;
            if ($activityId <= 0 || !isset($activitiesById[$activityId])) {
                continue;
            }

            $name = trim((string) ($activityData['name'] ?? ''));
            $amount = max(0.0, $this->normalizeAmount((string) ($activityData['amount'] ?? '0')));
            $delete = (string) ($activityData['delete'] ?? '') === '1';

            if ($delete || $name === '' || $amount <= 0.0) {
                TeacherSalarySetting::deleteById($activityId);
                continue;
            }

            TeacherSalarySetting::updateById($activityId, [
                'name' => $name,
                'amount' => $amount,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $activitiesNew = (array) $request->input('activities_new', []);
        if (!empty($activitiesNew)) {
            foreach ($activitiesNew as $activityRow) {
                if (!is_array($activityRow)) {
                    continue;
                }

                $name = trim((string) ($activityRow['name'] ?? ''));
                $amount = max(0.0, $this->normalizeAmount((string) ($activityRow['amount'] ?? '0')));

                if ($name === '' || $amount <= 0.0) {
                    continue;
                }

                $code = $this->generateActivityCode($schoolYearId, $name);

                TeacherSalarySetting::upsert(
                    $schoolYearId,
                    $code,
                    $name,
                    'activity',
                    $amount
                );
            }
        }

        Session::flash('success', 'Pengaturan komponen gaji guru berhasil diperbarui.');

        return $this->redirect($redirectUrl);
    }

    public function saveRecord(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();
        if ($schoolYearId === null) {
            Session::flash('error', 'Tahun ajaran aktif belum ditetapkan.');

            return $this->redirect('keuangan/bendahara/gaji-guru');
        }

        $redirectUrl = 'keuangan/bendahara/gaji-guru';
        if ($response = $this->guardCsrfOrRedirect($request, $redirectUrl)) {
            return $response;
        }

        $teacherId = (int) ($request->input('teacher_id') ?? 0);
        $period = $this->sanitizePeriod((string) ($request->input('periode') ?? ''));

        if ($teacherId <= 0 || $period === '') {
            Session::flash('error', 'Guru dan periode gaji harus dipilih.');

            return $this->redirect($redirectUrl);
        }

        $teacher = Teacher::find($teacherId);
        if ($teacher === null) {
            Session::flash('error', 'Data guru tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        $existingRecord = TeacherSalaryRecord::findByTeacherAndPeriod($teacherId, $schoolYearId, $period);
        $recordId = $existingRecord !== null ? (int) $existingRecord['id'] : null;

        $teachingHours = max(0.0, (float) str_replace(',', '.', (string) $request->input('teaching_hours', '0')));
        $hourlyRate = max(0.0, $this->normalizeAmount((string) $request->input('hourly_rate', '0')));

        $componentsInput = (array) $request->input('components', []);
        $componentPayloads = [];
        $existingCodes = [];
        $existingIds = [];

        if ($existingRecord !== null) {
            foreach (TeacherSalaryComponent::byRecord((int) $existingRecord['id']) as $row) {
                $existingCodes[] = (string) ($row['code'] ?? '');
                $existingIds[] = (int) ($row['id'] ?? 0);
            }
        }

        foreach ($componentsInput as $component) {
            $include = (string) ($component['include'] ?? '') === '1';
            $type = (string) ($component['type'] ?? 'adjustment');
            if (!in_array($type, ['teaching', 'special', 'academic', 'activity', 'adjustment', 'deduction'], true)) {
                $type = 'adjustment';
            }

            $label = trim((string) ($component['label'] ?? ''));
            if ($label === '') {
                $label = $this->defaultComponentLabel($type);
            }

            $code = (string) ($component['code'] ?? '');
            $amount = max(0.0, $this->normalizeAmount((string) ($component['amount'] ?? '0')));

            $componentId = isset($component['id']) ? (int) $component['id'] : 0;

            if ($componentId > 0 && !in_array($componentId, $existingIds, true)) {
                $componentId = 0;
            }

            if ($code === '') {
                $code = $this->generateComponentCode($type, $label, $existingCodes);
                $existingCodes[] = $code;
            }

            $quantityRaw = (string) ($component['quantity'] ?? '');
            $quantity = $quantityRaw === '' ? null : (float) str_replace(',', '.', $quantityRaw);
            if ($quantity !== null && $quantity < 0) {
                $quantity = null;
            }

            $rateRaw = (string) ($component['rate'] ?? '');
            $rate = $rateRaw === '' ? null : $this->normalizeAmount($rateRaw);
            if ($rate !== null) {
                $rate = round($rate, 2);
            }

            if ($amount <= 0.0 && $quantity !== null && $rate !== null) {
                $computed = round($quantity * $rate, 2);
                if ($computed > 0.0) {
                    $amount = $computed;
                }
            }

            if (!$include || $label === '' || $amount <= 0.0) {
                continue;
            }

            $amount = round($amount, 2);

            $componentPayloads[] = [
                'id' => $componentId,
                'type' => $type,
                'code' => $code,
                'label' => $label,
                'quantity' => $quantity,
                'rate' => $rate,
                'amount' => $amount,
                'metadata' => $component['metadata'] ?? null,
            ];
        }

        $totals = $this->calculateTotals($teachingHours, $hourlyRate, $componentPayloads);
        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;

        $recordData = [
            'tahun_ajaran_id' => $schoolYearId,
            'guru_id' => $teacherId,
            'periode' => $period,
            'teaching_hours' => $teachingHours,
            'hourly_rate' => $hourlyRate,
            'total_teaching' => $totals['total_teaching'],
            'total_special' => $totals['total_special'],
            'total_academic' => $totals['total_academic'],
            'total_activity' => $totals['total_activity'],
            'total_adjustment' => $totals['total_adjustment'],
            'total_deduction' => $totals['total_deduction'],
            'total_bruto' => $totals['total_bruto'],
            'total_net' => $totals['total_net'],
            'status' => $existingRecord['status'] ?? 'draft',
            'note' => trim((string) $request->input('note', $existingRecord['note'] ?? '')),
            'updated_by' => $userId,
        ];

        if ($recordId === null) {
            $recordData['created_by'] = $userId;
        }

        try {
            $recordId = TeacherSalaryService::saveRecord($recordData, $componentPayloads, $recordId);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan data penggajian guru: ' . $exception->getMessage());

            return $this->redirect($redirectUrl);
        }

        Session::flash('success', 'Penggajian guru berhasil disimpan sebagai draf.');

        $query = http_build_query([
            'periode' => $period,
            'teacher_id' => $teacherId,
            'section' => 'payroll',
        ]);

        return $this->redirect('keuangan/bendahara/gaji-guru?' . $query);
    }

    public function updateStatus(Request $request, int $id): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $redirectUrl = 'keuangan/bendahara/gaji-guru';
        if ($response = $this->guardCsrfOrRedirect($request, $redirectUrl)) {
            return $response;
        }

        $record = TeacherSalaryRecord::find($id);
        if ($record === null) {
            Session::flash('error', 'Data penggajian guru tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        $action = (string) ($request->input('action') ?? '');
        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;

        if ($action === 'validate') {
            if ($record['status'] === 'disbursed') {
                Session::flash('error', 'Gaji yang sudah dicairkan tidak dapat diubah ke status validasi.');

                return $this->redirect($redirectUrl);
            }

            TeacherSalaryRecord::updateById($id, [
                'status' => 'validated',
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            Session::flash('success', 'Penggajian guru ditandai siap dicairkan.');
        } elseif ($action === 'revert') {
            if ($record['status'] === 'disbursed') {
                Session::flash('error', 'Gaji yang sudah dicairkan tidak dapat dikembalikan ke draf.');

                return $this->redirect($redirectUrl);
            }

            TeacherSalaryRecord::updateById($id, [
                'status' => 'draft',
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            Session::flash('success', 'Penggajian guru dikembalikan ke status draf.');
        } elseif ($action === 'disburse') {
            if ($record['total_net'] <= 0) {
                Session::flash('error', 'Total gaji bersih harus lebih dari nol sebelum dicairkan.');

                return $this->redirect($redirectUrl);
            }

            try {
                $result = $this->disburseSalaryRecord($record, $userId);
                Session::flash('success', 'Gaji guru berhasil dicairkan.');
                $warnings = [];
                if (is_array($result) && !empty($result['warnings'])) {
                    $warnings = array_merge($warnings, (array) $result['warnings']);
                }

                $deficitWarning = $this->generalCashWarningMessage((int) ($record['tahun_ajaran_id'] ?? 0));
                if ($deficitWarning !== null) {
                    $warnings[] = $deficitWarning;
                }

                if (!empty($warnings)) {
                    $warnings = array_values(array_unique(array_filter(array_map('trim', $warnings))));
                    if (!empty($warnings)) {
                        Session::flash('warning', implode(' ', $warnings));
                    }
                }
            } catch (\Throwable $exception) {
                Session::flash('error', 'Gagal mencairkan gaji: ' . $exception->getMessage());
            }
        } else {
            Session::flash('error', 'Aksi penggajian tidak dikenali.');
        }

        $query = http_build_query([
            'periode' => $record['periode'],
            'teacher_id' => $record['guru_id'],
            'section' => 'payroll',
        ]);

        return $this->redirect('keuangan/bendahara/gaji-guru?' . $query);
    }

    public function slip(Request $request, int $id): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $record = TeacherSalaryRecord::find($id);
        if ($record === null) {
            Session::flash('error', 'Slip gaji tidak ditemukan.');

            return $this->redirect('keuangan/bendahara/gaji-guru');
        }

        $teacher = Teacher::find((int) $record['guru_id']);
        $components = TeacherSalaryComponent::byRecord($id);

        $content = View::render('finance/bendahara/teacher-salaries/slip', [
            'title' => 'Slip Gaji Guru',
            'record' => $record,
            'teacher' => $teacher,
            'components' => $components,
        ], null);

        return Response::make($content);
    }

    public function slipPdf(Request $request, int $id): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $record = TeacherSalaryRecord::find($id);
        if ($record === null) {
            Session::flash('error', 'Slip gaji tidak ditemukan.');

            return $this->redirect('keuangan/bendahara/gaji-guru');
        }

        $teacher = Teacher::find((int) $record['guru_id']);
        $components = TeacherSalaryComponent::byRecord($id);

        try {
            $pdfBinary = TeacherSalarySlipExporter::generate($record, $teacher, $components);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal membuat PDF slip gaji: ' . $exception->getMessage());

            return $this->redirect('keuangan/bendahara/gaji-guru');
        }

        $teacherName = (string) ($teacher['nama'] ?? 'Guru');
        $period = (string) ($record['periode'] ?? 'Periode');
        $safeNameRaw = preg_replace('/[^A-Za-z0-9\-]/', '-', str_replace(['"', "'"], '', $teacherName));
        $safePeriodRaw = preg_replace('/[^A-Za-z0-9\-]/', '-', $period !== '' ? $period : 'Periode');
        $safeName = $safeNameRaw !== null && $safeNameRaw !== '' ? $safeNameRaw : 'Guru';
        $safePeriod = $safePeriodRaw !== null && $safePeriodRaw !== '' ? $safePeriodRaw : 'Periode';
        $filename = 'Slip-Gaji-' . $safeName . '-' . $safePeriod . '.pdf';

        return Response::make($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($pdfBinary),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param array<int, array<string, string>> $settingsByCode
     *
     * @return array<string, mixed>
     */
    private function buildSettingsContext(?int $schoolYearId, array $settingsByCode): array
    {
        $hourlyRate = isset($settingsByCode['hourly_rate'])
            ? (float) ($settingsByCode['hourly_rate']['amount'] ?? 0.0)
            : 0.0;

        $specialRoles = [];
        foreach ($this->specialRoles() as $key => $definition) {
            $code = $definition['code'];
            $specialRoles[] = [
                'key' => $key,
                'code' => $code,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'amount' => isset($settingsByCode[$code]) ? (float) ($settingsByCode[$code]['amount'] ?? 0.0) : 0.0,
            ];
        }

        $positionSettings = [];
        if ($schoolYearId !== null) {
            foreach (AcademicPosition::allOrdered() as $position) {
                if (($position['kategori'] ?? 'guru') !== 'guru') {
                    continue;
                }

                $positionId = (int) ($position['id'] ?? 0);
                if ($positionId <= 0) {
                    continue;
                }

                $code = $this->academicPositionCode($positionId);
                $positionSettings[] = [
                    'id' => $positionId,
                    'name' => (string) ($position['nama'] ?? 'Jabatan Akademik'),
                    'amount' => isset($settingsByCode[$code]) ? (float) ($settingsByCode[$code]['amount'] ?? 0.0) : 0.0,
                ];
            }
        }

        $activitySettings = [];
        foreach ($settingsByCode as $setting) {
            if (($setting['category'] ?? '') !== 'activity') {
                continue;
            }

            $activitySettings[] = [
                'id' => (int) ($setting['id'] ?? 0),
                'name' => (string) ($setting['name'] ?? 'Kegiatan Sekolah'),
                'amount' => (float) ($setting['amount'] ?? 0.0),
            ];
        }

        return [
            'hourlyRate' => $hourlyRate,
            'specialRoles' => $specialRoles,
            'academicPositions' => $positionSettings,
            'activities' => $activitySettings,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $existingComponents
     * @param array<string, array<string, mixed>> $settingsByCode
     * @param array<string, int> $specialCounts
     * @param array<int, array<string, mixed>> $academicAssignments
     * @param array<int, array<string, mixed>> $activeLoans
     *
     * @return array<string, mixed>
     */
    private function buildSalaryFormContext(
        array $teacher,
        int $schoolYearId,
        string $period,
        ?array $record,
        array $existingComponents,
        array $settingsByCode,
        array $specialCounts,
        array $academicAssignments,
        array $activeLoans
    ): array {
        $defaultHourlyRate = isset($settingsByCode['hourly_rate'])
            ? (float) ($settingsByCode['hourly_rate']['amount'] ?? 0.0)
            : 0.0;

        $hourlyRate = $record !== null ? (float) ($record['hourly_rate'] ?? 0.0) : $defaultHourlyRate;
        $teachingHours = $record !== null ? (float) ($record['teaching_hours'] ?? 0.0) : 0.0;
        $status = $record['status'] ?? 'draft';
        $note = (string) ($record['note'] ?? '');

        $components = [];
        if (!empty($existingComponents)) {
            foreach ($existingComponents as $component) {
                $metadataRaw = $component['metadata'] ?? null;
                $metadataString = is_string($metadataRaw) ? $metadataRaw : null;
                $componentHint = '';

                if ($metadataString !== null && $metadataString !== '') {
                    try {
                        $metadataDecoded = json_decode($metadataString, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\Throwable $exception) {
                        $metadataDecoded = null;
                    }

                    if (is_array($metadataDecoded) && isset($metadataDecoded['origin']) && $metadataDecoded['origin'] === 'loan') {
                        $outstanding = isset($metadataDecoded['outstanding']) ? (float) $metadataDecoded['outstanding'] : null;
                        if ($outstanding !== null && $outstanding > 0.0) {
                            $componentHint = 'Sisa kasbon: Rp ' . number_format($outstanding, 0, ',', '.');
                        }
                    }
                }

                $components[] = [
                    'id' => (int) ($component['id'] ?? 0),
                    'type' => (string) ($component['type'] ?? 'adjustment'),
                    'code' => (string) ($component['code'] ?? ''),
                    'label' => (string) ($component['label'] ?? ''),
                    'quantity' => $component['quantity'] !== null ? (float) $component['quantity'] : null,
                    'rate' => $component['rate'] !== null ? (float) $component['rate'] : null,
                    'amount' => (float) ($component['amount'] ?? 0.0),
                    'metadata' => $metadataString,
                    'include' => true,
                    'hint' => $componentHint !== '' ? $componentHint : null,
                ];
            }
        } else {
            $rolesByCode = $this->specialRolesByCode();
            foreach ($specialCounts as $code => $count) {
                if (!isset($settingsByCode[$code]) || $count <= 0) {
                    continue;
                }

                $rate = (float) ($settingsByCode[$code]['amount'] ?? 0.0);
                if ($rate <= 0.0) {
                    continue;
                }

                $label = $rolesByCode[$code]['label'] ?? 'Tugas Khusus';
                $components[] = [
                    'id' => 0,
                    'type' => 'special',
                    'code' => $code,
                    'label' => $label,
                    'quantity' => (float) $count,
                    'rate' => $rate,
                    'amount' => $rate * $count,
                    'metadata' => json_encode(['origin' => 'special', 'count' => $count], JSON_THROW_ON_ERROR),
                    'include' => true,
                ];
            }

            foreach ($academicAssignments as $assignment) {
                $positionId = (int) ($assignment['jabatan_akademik_id'] ?? 0);
                if ($positionId <= 0) {
                    continue;
                }

                $code = $this->academicPositionCode($positionId);
                if (!isset($settingsByCode[$code])) {
                    continue;
                }

                $rate = (float) ($settingsByCode[$code]['amount'] ?? 0.0);
                if ($rate <= 0.0) {
                    continue;
                }

                $components[] = [
                    'id' => 0,
                    'type' => 'academic',
                    'code' => $code,
                    'label' => (string) ($assignment['jabatan_nama'] ?? 'Jabatan Akademik'),
                    'quantity' => 1.0,
                    'rate' => $rate,
                    'amount' => $rate,
                    'metadata' => json_encode(['origin' => 'academic', 'position_id' => $positionId], JSON_THROW_ON_ERROR),
                    'include' => true,
                ];
            }
        }

        $existingCodes = [];
        $loanComponentIds = [];
        foreach ($components as $componentEntry) {
            $codeEntry = (string) ($componentEntry['code'] ?? '');
            if ($codeEntry !== '') {
                $existingCodes[$codeEntry] = true;
            }

            $metadataRaw = $componentEntry['metadata'] ?? null;
            if (is_string($metadataRaw) && $metadataRaw !== '') {
                try {
                    $metadata = json_decode($metadataRaw, true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $exception) {
                    $metadata = null;
                }

                if (is_array($metadata) && isset($metadata['origin'], $metadata['loan_id']) && $metadata['origin'] === 'loan') {
                    $loanComponentIds[(int) $metadata['loan_id']] = true;
                }
            }
        }

        foreach ($activeLoans as $loan) {
            $loanId = (int) ($loan['id'] ?? 0);
            if ($loanId <= 0 || isset($loanComponentIds[$loanId])) {
                continue;
            }

            $outstanding = (float) ($loan['saldo_terhutang'] ?? 0.0);
            if ($outstanding <= 0.0) {
                continue;
            }

            $loanCode = 'loan:' . $loanId;
            if (isset($existingCodes[$loanCode])) {
                $loanCode .= '-' . uniqid('', false);
            }

            $metadata = json_encode([
                'origin' => 'loan',
                'loan_id' => $loanId,
                'loan_code' => $loan['kode'] ?? null,
                'outstanding' => $outstanding,
            ], JSON_THROW_ON_ERROR);

            $loanLabel = 'Potongan Kasbon #' . trim((string) ($loan['kode'] ?? $loanId));
            $components[] = [
                'id' => 0,
                'type' => 'deduction',
                'code' => $loanCode,
                'label' => $loanLabel,
                'quantity' => null,
                'rate' => null,
                'amount' => $outstanding,
                'metadata' => $metadata,
                'include' => false,
                'hint' => 'Sisa kasbon: Rp ' . number_format($outstanding, 0, ',', '.'),
            ];

            $existingCodes[$loanCode] = true;
            $loanComponentIds[$loanId] = true;
        }

        $componentTotals = $this->calculateTotals($teachingHours, $hourlyRate, $components);

        return [
            'recordId' => $record !== null ? (int) $record['id'] : null,
            'status' => $status,
            'note' => $note,
            'teacher' => $teacher,
            'period' => $period,
            'schoolYearId' => $schoolYearId,
            'teachingHours' => $teachingHours,
            'hourlyRate' => $hourlyRate,
            'components' => $components,
            'totals' => $componentTotals,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $components
     *
     * @return array<string, float>
     */
    private function calculateTotals(float $teachingHours, float $hourlyRate, array $components): array
    {
        $totalTeaching = $teachingHours * $hourlyRate;
        $totalSpecial = 0.0;
        $totalAcademic = 0.0;
        $totalActivity = 0.0;
        $totalAdjustment = 0.0;
        $totalDeduction = 0.0;

        foreach ($components as $component) {
            $include = $component['include'] ?? true;
            if (is_string($include)) {
                $include = $include === '1' || strtolower($include) === 'true';
            }

            if (!$include) {
                continue;
            }

            $amount = (float) ($component['amount'] ?? 0.0);
            $type = (string) ($component['type'] ?? 'adjustment');

            switch ($type) {
                case 'special':
                    $totalSpecial += $amount;
                    break;
                case 'academic':
                    $totalAcademic += $amount;
                    break;
                case 'activity':
                    $totalActivity += $amount;
                    break;
                case 'deduction':
                    $totalDeduction += $amount;
                    break;
                default:
                    $totalAdjustment += $amount;
            }
        }

        $totalBruto = $totalTeaching + $totalSpecial + $totalAcademic + $totalActivity + $totalAdjustment;
        $totalNet = $totalBruto - $totalDeduction;

        return [
            'total_teaching' => $totalTeaching,
            'total_special' => $totalSpecial,
            'total_academic' => $totalAcademic,
            'total_activity' => $totalActivity,
            'total_adjustment' => $totalAdjustment,
            'total_deduction' => $totalDeduction,
            'total_bruto' => $totalBruto,
            'total_net' => $totalNet,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function specialRolesByCode(): array
    {
        $mapped = [];
        foreach ($this->specialRoles() as $definition) {
            $mapped[$definition['code']] = $definition;
        }

        return $mapped;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAcademicAssignments(int $teacherId, int $schoolYearId): array
    {
        if ($teacherId <= 0 || $schoolYearId <= 0) {
            return [];
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            'SELECT gja.*, ja.nama AS jabatan_nama
             FROM guru_jabatan_akademik gja
             JOIN jabatan_akademik ja ON ja.id = gja.jabatan_akademik_id
             WHERE gja.tahun_ajaran_id = :year AND gja.guru_id = :teacher'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
        $statement->bindValue(':teacher', $teacherId, \PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<string, int>
     */
    private function fetchSpecialRoleCounts(int $teacherId, int $schoolYearId): array
    {
        $counts = [
            'special:homeroom' => 0,
            'special:extracurricular' => 0,
            'special:headmaster' => 0,
            'special:prakerin' => 0,
        ];

        if ($teacherId <= 0 || $schoolYearId <= 0) {
            return $counts;
        }

        $connection = Database::connection();

        $homeroom = $connection->prepare(
            'SELECT COUNT(*) FROM kelas WHERE tahun_ajaran_id = :year AND wali_kelas_id = :teacher'
        );
        if ($homeroom !== false) {
            $homeroom->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
            $homeroom->bindValue(':teacher', $teacherId, \PDO::PARAM_INT);
            $homeroom->execute();
            $counts['special:homeroom'] = (int) ($homeroom->fetchColumn() ?: 0);
        }

        $extracurricular = $connection->prepare(
            'SELECT COUNT(*) FROM ekstrakurikuler WHERE tahun_ajaran_id = :year AND pembina_guru_id = :teacher'
        );
        if ($extracurricular !== false) {
            $extracurricular->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
            $extracurricular->bindValue(':teacher', $teacherId, \PDO::PARAM_INT);
            $extracurricular->execute();
            $counts['special:extracurricular'] = (int) ($extracurricular->fetchColumn() ?: 0);
        }

        $headmaster = $connection->prepare(
            'SELECT kepala_sekolah_id FROM tahun_ajaran WHERE id = :year LIMIT 1'
        );
        if ($headmaster !== false) {
            $headmaster->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
            if ($headmaster->execute()) {
                $headmasterTeacherId = (int) ($headmaster->fetchColumn() ?: 0);
                $counts['special:headmaster'] = $headmasterTeacherId === $teacherId && $headmasterTeacherId > 0 ? 1 : 0;
            }
        }

        $prakerin = $connection->prepare(
            'SELECT COUNT(DISTINCT tempat_prakerin_id)
             FROM penempatan_prakerin
             WHERE tahun_ajaran_id = :year AND guru_id = :teacher'
        );
        if ($prakerin !== false) {
            $prakerin->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
            $prakerin->bindValue(':teacher', $teacherId, \PDO::PARAM_INT);
            $prakerin->execute();
            $counts['special:prakerin'] = (int) ($prakerin->fetchColumn() ?: 0);
        }

        return $counts;
    }

    /**
     * @param array<int, array<string, mixed>> $settings
     *
     * @return array<string, array<string, mixed>>
     */
    private function mapSettingsByCode(array $settings): array
    {
        $mapped = [];
        foreach ($settings as $setting) {
            $code = (string) ($setting['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $mapped[$code] = $setting;
        }

        return $mapped;
    }

    private function sanitizePeriod(string $period): string
    {
        $period = trim($period);
        if ($period === '') {
            return '';
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            return '';
        }

        return $period;
    }

    /**
     * @return array<string, mixed>
     */
    private function disburseSalaryRecord(array $record, ?int $userId): array
    {
        $warnings = [];

        $recordId = (int) ($record['id'] ?? 0);
        if ($recordId <= 0) {
            return ['warnings' => $warnings];
        }

        if ((string) ($record['status'] ?? 'draft') === 'disbursed') {
            return ['warnings' => $warnings];
        }

        $schoolYearId = (int) ($record['tahun_ajaran_id'] ?? 0);
        $netAmount = (float) ($record['total_net'] ?? 0.0);

        if ($schoolYearId <= 0) {
            throw new \RuntimeException('Tahun ajaran tidak valid untuk pencairan gaji.');
        }

        if ($netAmount <= 0.0) {
            throw new \RuntimeException('Total gaji bersih tidak valid untuk pencairan.');
        }

        $timestamp = date('Y-m-d H:i:s');
        $description = 'Pencairan gaji guru periode ' . (string) ($record['periode'] ?? '');

        GeneralCashService::withdrawForTeacherSalary($schoolYearId, $netAmount, [
            'description' => $description,
            'recorded_at' => $timestamp,
            'user_id' => $userId,
            'record_id' => $recordId,
        ]);

        $updates = [
            'status' => 'disbursed',
            'disbursed_at' => $timestamp,
            'disbursed_by' => $userId,
            'updated_by' => $userId,
            'updated_at' => $timestamp,
        ];

        $honorId = isset($record['honor_id']) ? (int) $record['honor_id'] : 0;
        if ($honorId > 0) {
            TeacherHonor::updateById($honorId, [
                'nominal_bruto' => (float) $record['total_bruto'],
                'nominal_potongan' => (float) $record['total_deduction'],
                'nominal_diterima' => (float) $record['total_net'],
                'status' => 'terbayar',
                'updated_at' => $timestamp,
                'updated_by' => $userId,
            ]);
        } else {
            $honorId = HonorService::createHonor([
                'guru_id' => (int) $record['guru_id'],
                'tahun_ajaran_id' => (int) $record['tahun_ajaran_id'],
                'periode' => (string) $record['periode'],
                'kategori' => 'gaji',
                'judul' => 'Gaji Guru ' . (string) $record['periode'],
                'nominal_bruto' => (float) $record['total_bruto'],
                'nominal_potongan' => (float) $record['total_deduction'],
                'nominal_diterima' => (float) $record['total_net'],
                'status' => 'terbayar',
                'catatan' => 'Slip gaji dicetak melalui modul bendahara.',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $updates['honor_id'] = $honorId;
        }

        if ($honorId > 0 && !isset($updates['honor_id'])) {
            $updates['honor_id'] = $honorId;
        }

        TeacherSalaryRecord::updateById($recordId, $updates);

        $currentRecord = array_merge($record, $updates);
        if ($honorId > 0) {
            $currentRecord['honor_id'] = $honorId;
        }

        $notificationWarnings = $this->notifySalaryDisbursement($currentRecord, $timestamp);
        if (!empty($notificationWarnings)) {
            $warnings = array_merge($warnings, $notificationWarnings);
        }

        return ['warnings' => $warnings];
    }

    private function academicPositionCode(int $positionId): string
    {
        return 'academic:' . $positionId;
    }

    private function generateActivityCode(int $schoolYearId, string $name): string
    {
        $base = $this->slugify($name);

        if ($base === '') {
            $base = 'activity';
        }

        $code = 'activity:' . $base;
        $counter = 1;

        while (TeacherSalarySetting::findByYearAndCode($schoolYearId, $code) !== null) {
            $code = 'activity:' . $base . '-' . $counter;
            $counter++;
        }

        return $code;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<int, string>
     */
    private function notifySalaryDisbursement(array $record, string $timestamp): array
    {
        $warnings = [];

        $teacherId = (int) ($record['guru_id'] ?? 0);
        if ($teacherId <= 0) {
            $warnings[] = 'Data guru tidak valid sehingga notifikasi pencairan gaji tidak dikirim.';

            return $warnings;
        }

        $teacher = Teacher::find($teacherId);
        if ($teacher === null) {
            $warnings[] = 'Data guru penerima gaji tidak ditemukan sehingga notifikasi pencairan gaji tidak dikirim.';

            return $warnings;
        }

        $settings = WhatsappGatewaySetting::first();
        if ($settings === null) {
            $warnings[] = 'Pengaturan WhatsApp Gateway belum lengkap sehingga bukti pencairan gaji tidak dikirim.';

            return $warnings;
        }

        $teacherName = (string) ($teacher['nama'] ?? 'Guru');
        $phone = WhatsappGatewayService::normalizePhone((string) ($teacher['telepon'] ?? ''));
        if ($phone === '') {
            $warnings[] = 'Nomor WhatsApp belum tersedia untuk ' . ($teacherName !== '' ? $teacherName : 'guru terkait') . '.';

            return $warnings;
        }

        $recordId = (int) ($record['id'] ?? 0);
        $period = (string) ($record['periode'] ?? '-');

        $pdfUrl = $recordId > 0 ? absolute_url('keuangan/guru/gaji/slip/' . $recordId . '/pdf') : '';
        if ($pdfUrl === '') {
            $warnings[] = 'Tautan slip gaji tidak tersedia sehingga notifikasi WhatsApp tidak dikirim.';

            return $warnings;
        }

        $portalUrl = absolute_url('keuangan/guru');
        $parsedTimestamp = strtotime($timestamp);

        $variables = [
            'nama_guru' => $teacherName,
            'periode_gaji' => $period,
            'total_bruto' => $this->formatCurrencyValue((float) ($record['total_bruto'] ?? 0.0)),
            'total_potongan' => $this->formatCurrencyValue((float) ($record['total_deduction'] ?? 0.0)),
            'total_diterima' => $this->formatCurrencyValue((float) ($record['total_net'] ?? 0.0)),
            'total_bruto_angka' => number_format((float) ($record['total_bruto'] ?? 0.0), 2, '.', ''),
            'total_potongan_angka' => number_format((float) ($record['total_deduction'] ?? 0.0), 2, '.', ''),
            'total_diterima_angka' => number_format((float) ($record['total_net'] ?? 0.0), 2, '.', ''),
            'tanggal_pencairan' => date('d M Y H:i', $parsedTimestamp ?: time()),
            'periode' => $period,
            'link_slip_gaji_pdf' => $pdfUrl,
            'link_slip_gaji_html' => $pdfUrl,
            'link_slip_gaji' => $pdfUrl,
            'portal_keuangan_guru' => $portalUrl,
            'record_id' => (string) $recordId,
            'honor_id' => (string) ((int) ($record['honor_id'] ?? 0)),
        ];

        $template = "Assalamu'alaikum Bapak/Ibu {{nama_guru}},\n\nGaji periode {{periode_gaji}} telah dicairkan oleh bendahara sekolah.\n\nRingkasan:\n- Total bruto: {{total_bruto}}\n- Potongan: {{total_potongan}}\n- Diterima: {{total_diterima}}\n\nUnduh slip gaji (PDF): {{link_slip_gaji_pdf}}\nPortal guru: {{portal_keuangan_guru}}\n\nTerima kasih.";

        $sendResult = WhatsappGatewayService::sendDetailed([
            'phone' => $phone,
            'template' => $template,
            'variables' => $variables,
        ], $settings);

        if ($sendResult['success'] && ($sendResult['queued'] ?? false)) {
            $warnings[] = 'Notifikasi WhatsApp dimasukkan ke antrian dan akan dikirim sesuai interval yang dikonfigurasi.';
        }

        if (!$sendResult['success']) {
            $errorDetail = trim((string) ($sendResult['error'] ?? ''));
            $message = 'Gagal mengirim bukti pencairan gaji ke ' . ($teacherName !== '' ? $teacherName : 'guru terkait') . ' melalui WhatsApp.';
            if ($errorDetail !== '') {
                $message .= ' ' . $errorDetail;
            }
            $warnings[] = $message;
        }

        return $warnings;
    }

    private function generalCashWarningMessage(int $schoolYearId): ?string
    {
        if ($schoolYearId <= 0) {
            return null;
        }

        return GeneralCashService::balance($schoolYearId) < 0
            ? 'Saldo kas utama berada pada posisi minus. Mohon tindak lanjuti untuk menutup defisit.'
            : null;
    }

    private function formatCurrencyValue(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    private function defaultComponentLabel(string $type): string
    {
        return match ($type) {
            'teaching' => 'Honor Mengajar',
            'special' => 'Honor Khusus',
            'academic' => 'Honor Akademik',
            'activity' => 'Honor Kegiatan',
            'deduction' => 'Potongan',
            default => 'Penyesuaian',
        };
    }

    /**
     * @param array<int, string> $existingCodes
     */
    private function generateComponentCode(string $type, string $label, array $existingCodes): string
    {
        $base = $this->slugify($label);
        if ($base === '') {
            $base = 'komponen';
        }

        $prefix = $type === 'deduction' ? 'deduction' : 'component';
        $code = $prefix . ':' . $base;
        $counter = 1;

        while (in_array($code, $existingCodes, true)) {
            $code = $prefix . ':' . $base . '-' . $counter;
            $counter++;
        }

        return $code;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function specialRoles(): array
    {
        return [
            'homeroom' => [
                'code' => 'special:homeroom',
                'label' => 'Wali Kelas',
                'description' => 'Honor tambahan per kelas yang diasuh.',
            ],
            'extracurricular' => [
                'code' => 'special:extracurricular',
                'label' => 'Pembina Ekstrakurikuler',
                'description' => 'Honor per kegiatan ekstrakurikuler yang dibina.',
            ],
            'headmaster' => [
                'code' => 'special:headmaster',
                'label' => 'Kepala Sekolah',
                'description' => 'Honor jabatan kepala sekolah.',
            ],
            'prakerin' => [
                'code' => 'special:prakerin',
                'label' => 'Pembina Prakerin',
                'description' => 'Honor per tempat prakerin yang dibina.',
            ],
        ];
    }

    private function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $transliterated = \iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);

        if ($value === null) {
            return '';
        }

        return trim($value, '-');
    }

    private function normalizeAmount(string $raw): float
    {
        $clean = preg_replace('/[^\d,\.]/', '', $raw);

        if ($clean === null || $clean === '') {
            return 0.0;
        }

        $lastComma = strrpos($clean, ',');
        $lastDot = strrpos($clean, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($lastComma !== false) {
            $fractionLength = strlen($clean) - $lastComma - 1;
            if ($fractionLength > 0 && $fractionLength <= 2) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($lastDot !== false) {
            $fractionLength = strlen($clean) - $lastDot - 1;
            if ($fractionLength > 0 && $fractionLength <= 2) {
                $clean = str_replace(',', '', $clean);
            } else {
                $clean = str_replace('.', '', $clean);
            }
        } else {
            $clean = str_replace(['.', ','], '', $clean);
        }

        return (float) $clean;
    }
}
