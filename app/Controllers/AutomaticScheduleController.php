<?php

namespace App\Controllers;

use App\Models\AutomaticSchedule;
use App\Models\SchoolProfile;
use App\Models\SchoolYear;
use App\Models\SubjectTeacher;
use App\Models\Teacher;
use App\Services\AutomaticScheduleExportService;
use App\Services\AutomaticScheduleGenerator;
use App\Support\AcademicRoleGate;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class AutomaticScheduleController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureScheduleAccess()) {
            return $response;
        }

        $allYears = SchoolYear::allOrdered();
        $selectedYearId = $this->resolveSchoolYearId($request, $allYears);
        $selectedYear = $selectedYearId > 0 ? SchoolYear::find($selectedYearId) : null;
        $semester = $this->normalizeSemester($request->query('semester', $selectedYear['semester_aktif'] ?? 1));
        $level = $this->normalizeLevel($request->query('tingkat', 0));
        $selectedClassIds = $this->selectedClassIds($request);
        $draftId = (int) $request->query('draft_id', 0);

        if ($selectedYearId > 0) {
            AutomaticSchedule::seedDefaultSettings($selectedYearId);
        }

        $draft = null;
        if ($draftId > 0) {
            $draft = AutomaticSchedule::findDraft($draftId);
            if ($draft !== null) {
                $selectedYearId = (int) ($draft['tahun_ajaran_id'] ?? $selectedYearId);
                $selectedYear = $selectedYearId > 0 ? SchoolYear::find($selectedYearId) : $selectedYear;
                $semester = (int) ($draft['semester'] ?? $semester);
                $draftLevel = isset($draft['tingkat']) ? (int) $draft['tingkat'] : 0;
                $level = $draftLevel > 0 ? $draftLevel : null;
            }
        }

        if ($draft === null && $selectedYearId > 0) {
            $draft = AutomaticSchedule::latestDraft($selectedYearId, $semester, $level);
            $draftId = (int) ($draft['id'] ?? 0);
        }

        $items = $draftId > 0 ? AutomaticSchedule::draftItems($draftId) : [];
        if (empty($selectedClassIds) && $draftId > 0 && !empty($items)) {
            $selectedClassIds = array_values(array_unique(array_filter(array_map(
                static fn (array $item): int => (int) ($item['kelas_id'] ?? 0),
                $items
            ))));
        }

        $periods = $selectedYearId > 0 ? AutomaticSchedule::periodsByDay($selectedYearId) : [];
        $activities = $selectedYearId > 0 ? AutomaticSchedule::fixedActivities($selectedYearId) : [];
        $availableClasses = $selectedYearId > 0 ? AutomaticSchedule::classroomsForContext($selectedYearId, $level) : [];
        $availableClassIds = array_values(array_filter(array_map(static fn (array $classroom): int => (int) ($classroom['id'] ?? 0), $availableClasses)));
        if (!empty($selectedClassIds) && !empty($availableClassIds)) {
            $selectedClassIds = array_values(array_intersect($selectedClassIds, $availableClassIds));
        }
        $classes = $selectedYearId > 0
            ? (!empty($selectedClassIds) ? AutomaticSchedule::classroomsForContext($selectedYearId, $level, $selectedClassIds) : $availableClasses)
            : [];
        if (!empty($selectedClassIds)) {
            $items = $this->filterItemsByClassIds($items, $selectedClassIds);
        }
        $assignments = $selectedYearId > 0 ? SubjectTeacher::allWithRelations($selectedYearId) : [];
        $assignmentData = $this->buildAssignmentData($assignments);
        $generator = new AutomaticScheduleGenerator();
        $targets = $selectedYearId > 0 ? $generator->targetHoursForContext($selectedYearId, $semester, $level, $selectedClassIds) : [];
        $preferences = $selectedYearId > 0 ? AutomaticSchedule::preferences($selectedYearId) : AutomaticSchedule::defaultPreferences();
        $timePreferences = $selectedYearId > 0 ? AutomaticSchedule::timePreferences($selectedYearId) : AutomaticSchedule::defaultTimePreferences();
        $parallelGroups = $selectedYearId > 0 ? AutomaticSchedule::parallelClassGroups($selectedYearId) : [];
        $conflicts = $this->decodeConflicts($draft['conflict_json'] ?? null);
        $exporter = new AutomaticScheduleExportService();
        $schoolProfile = SchoolProfile::first();
        $schedulePreviewUrl = '';
        if ($draftId > 0 && !empty($items) && !empty($classes)) {
            $previewQuery = [
                'format' => 'print',
                'embed' => '1',
            ];
            if (!empty($selectedClassIds)) {
                $previewQuery['class_ids'] = $selectedClassIds;
            }
            $schedulePreviewUrl = base_url('akademik/jadwal/generate/' . $draftId . '/export?' . http_build_query($previewQuery));
        }

        return $this->render('academic/automatic-schedules/index', [
            'title' => 'Generate Jadwal Mengajar',
            'pageTitle' => 'Generate Jadwal Mengajar',
            'activeMenu' => 'automatic-schedules',
            'yearOptions' => SchoolYear::options(),
            'selectedYearId' => $selectedYearId,
            'selectedYear' => $selectedYear,
            'schoolName' => (string) ($schoolProfile['nama'] ?? config('app.name', 'Sekolah')),
            'semester' => $semester,
            'selectedLevel' => $level,
            'levelOptions' => $this->levelOptions($selectedYearId),
            'draft' => $draft,
            'draftItems' => $items,
            'periods' => $periods,
            'activities' => $activities,
            'availableClasses' => $availableClasses,
            'selectedClassIds' => $selectedClassIds,
            'classes' => $classes,
            'gridRows' => $this->buildGridRows($periods, $items, $classes, $activities),
            'teacherRecap' => $exporter->teacherRecap($items),
            'schedulePreviewUrl' => $schedulePreviewUrl,
            'targets' => $targets,
            'preferences' => $preferences,
            'timePreferences' => $timePreferences,
            'parallelGroups' => $parallelGroups,
            'conflicts' => $conflicts,
            'assignmentOptions' => $assignmentData['options'],
            'assignmentClassMap' => $assignmentData['class_map'],
            'classOptions' => $this->classOptions($classes),
            'dayOptions' => AutomaticSchedule::DAYS,
            'roomOptions' => AutomaticSchedule::roomOptions(),
        ], 'admin');
    }

    public function updatePreferences(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureScheduleAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/jadwal/generate')) {
            return $response;
        }

        $schoolYearId = (int) $request->input('tahun_ajaran_id', 0);
        $semester = $this->normalizeSemester($request->input('semester', 1));
        $level = $this->normalizeLevel($request->input('tingkat', 0));
        $draftId = (int) $request->input('draft_id', 0);
        $classIds = $this->selectedClassIds($request);

        if ($schoolYearId <= 0 || SchoolYear::find($schoolYearId) === null) {
            Session::flash('error', 'Tahun ajaran preferensi tidak valid.');
            return $this->redirect('akademik/jadwal/generate');
        }

        $preferences = [
            'blok_produktif_min' => (int) $request->input('blok_produktif_min', 2),
            'blok_produktif_maks' => (int) $request->input('blok_produktif_maks', 4),
            'blok_umum_maks' => (int) $request->input('blok_umum_maks', 2),
            'maks_mapel_berat_berurutan' => (int) $request->input('maks_mapel_berat_berurutan', 2),
            'prioritas_praktik_pagi' => (int) $request->input('prioritas_praktik_pagi', 0),
            'hindari_mapel_sama_per_hari' => (int) $request->input('hindari_mapel_sama_per_hari', 0),
            'sebar_beban_guru' => (int) $request->input('sebar_beban_guru', 0),
            'rapatkan_jadwal_kelas' => (int) $request->input('rapatkan_jadwal_kelas', 0),
            'bobot_jam_guru_harian' => (int) $request->input('bobot_jam_guru_harian', 7),
            'bobot_jam_kelas_harian' => (int) $request->input('bobot_jam_kelas_harian', 3),
            'penalti_slot_sore_produktif' => (int) $request->input('penalti_slot_sore_produktif', 25),
            'penalti_mapel_sama_hari' => (int) $request->input('penalti_mapel_sama_hari', 30),
            'penalti_jam_kosong_guru' => (int) $request->input('penalti_jam_kosong_guru', 18),
            'penalti_jam_kosong_kelas' => (int) $request->input('penalti_jam_kosong_kelas', 15),
            'penalti_mapel_berat_berurutan' => (int) $request->input('penalti_mapel_berat_berurutan', 22),
        ];

        if (AutomaticSchedule::savePreferences($schoolYearId, $preferences)) {
            Session::flash('success', 'Preferensi generate jadwal berhasil disimpan. Jalankan generate ulang agar hasil mengikuti preferensi terbaru.');
        } else {
            Session::flash('error', 'Gagal menyimpan preferensi generate jadwal.');
        }

        return $this->redirect($this->contextUrl($schoolYearId, $semester, $level, $draftId > 0 ? $draftId : null, $classIds));
    }

    public function updateParallelPreferences(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureScheduleAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/jadwal/generate')) {
            return $response;
        }

        $schoolYearId = (int) $request->input('tahun_ajaran_id', 0);
        $semester = $this->normalizeSemester($request->input('semester', 1));
        $level = $this->normalizeLevel($request->input('tingkat', 0));
        $draftId = (int) $request->input('draft_id', 0);
        $classIds = $this->selectedClassIds($request);

        if ($schoolYearId <= 0 || SchoolYear::find($schoolYearId) === null) {
            Session::flash('error', 'Tahun ajaran preferensi kelas paralel tidak valid.');
            return $this->redirect('akademik/jadwal/generate');
        }

        $assignmentData = $this->buildAssignmentData(SubjectTeacher::allWithRelations($schoolYearId));
        $assignmentClassMap = $assignmentData['class_map'];
        $inputGroups = $request->input('parallel_groups', []);
        if (!is_array($inputGroups)) {
            $inputGroups = [];
        }

        $groups = [];
        foreach ($inputGroups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $assignmentId = (int) ($group['guru_mata_pelajaran_id'] ?? 0);
            if ($assignmentId <= 0 || empty($assignmentClassMap[$assignmentId])) {
                continue;
            }

            $rawClassIds = $group['kelas_ids'] ?? [];
            if (!is_array($rawClassIds)) {
                $rawClassIds = preg_split('/[,\s]+/', trim((string) $rawClassIds)) ?: [];
            }
            $allowedClassIds = array_fill_keys(array_map('intval', array_keys($assignmentClassMap[$assignmentId])), true);
            $groupClassIds = array_values(array_unique(array_filter(
                array_map('intval', $rawClassIds),
                static fn (int $classId): bool => isset($allowedClassIds[$classId])
            )));

            if (count($groupClassIds) < 2) {
                continue;
            }

            $groups[] = [
                'guru_mata_pelajaran_id' => $assignmentId,
                'nama' => trim((string) ($group['nama'] ?? '')),
                'kelas_ids' => $groupClassIds,
            ];
        }

        if (AutomaticSchedule::saveParallelClassGroups($schoolYearId, $groups)) {
            Session::flash('success', 'Preferensi kelas paralel berhasil disimpan. Jalankan generate ulang agar kelas gabungan diterapkan.');
        } else {
            Session::flash('error', 'Gagal menyimpan preferensi kelas paralel.');
        }

        return $this->redirect($this->contextUrl($schoolYearId, $semester, $level, $draftId > 0 ? $draftId : null, $classIds));
    }

    public function updateTimePreferences(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureScheduleAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/jadwal/generate')) {
            return $response;
        }

        $schoolYearId = (int) $request->input('tahun_ajaran_id', 0);
        $semester = $this->normalizeSemester($request->input('semester', 1));
        $level = $this->normalizeLevel($request->input('tingkat', 0));
        $draftId = (int) $request->input('draft_id', 0);
        $classIds = $this->selectedClassIds($request);

        if ($schoolYearId <= 0 || SchoolYear::find($schoolYearId) === null) {
            Session::flash('error', 'Tahun ajaran preferensi jam tidak valid.');
            return $this->redirect('akademik/jadwal/generate');
        }

        $preferences = [
            'jam_masuk' => (string) $request->input('jam_masuk', '07:00'),
            'durasi_jp_menit' => (int) $request->input('durasi_jp_menit', 45),
            'jeda_jp_menit' => (int) $request->input('jeda_jp_menit', 0),
            'jumlah_jp_per_hari' => (int) $request->input('jumlah_jp_per_hari', 8),
            'istirahat_pertama_setelah_jp' => (int) $request->input('istirahat_pertama_setelah_jp', 4),
            'durasi_istirahat_pertama_menit' => (int) $request->input('durasi_istirahat_pertama_menit', 15),
            'istirahat_dzuhur_setelah_jp' => (int) $request->input('istirahat_dzuhur_setelah_jp', 6),
            'durasi_istirahat_dzuhur_menit' => (int) $request->input('durasi_istirahat_dzuhur_menit', 45),
            'durasi_istirahat_jumat_menit' => (int) $request->input('durasi_istirahat_jumat_menit', 75),
        ];

        if (AutomaticSchedule::saveTimePreferences($schoolYearId, $preferences)) {
            Session::flash('success', 'Preferensi jam berhasil disimpan. Jalankan generate ulang agar draft baru mengikuti slot jam terbaru.');
        } else {
            Session::flash('error', 'Gagal menyimpan preferensi jam.');
        }

        return $this->redirect($this->contextUrl($schoolYearId, $semester, $level, $draftId > 0 ? $draftId : null, $classIds));
    }

    public function generate(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureScheduleAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/jadwal/generate')) {
            return $response;
        }

        $schoolYearId = (int) $request->input('tahun_ajaran_id', 0);
        $semester = $this->normalizeSemester($request->input('semester', 1));
        $level = $this->normalizeLevel($request->input('tingkat', 0));
        $preserveDraftId = (int) $request->input('preserve_draft_id', 0);
        $classIds = $this->selectedClassIds($request);

        if ($schoolYearId <= 0 || SchoolYear::find($schoolYearId) === null) {
            Session::flash('error', 'Tahun ajaran tidak valid.');
            return $this->redirect('akademik/jadwal/generate');
        }

        try {
            $user = auth();
            $result = (new AutomaticScheduleGenerator())->generateDraft(
                $schoolYearId,
                $semester,
                $level,
                $preserveDraftId > 0 ? $preserveDraftId : null,
                is_array($user) ? (int) ($user['id'] ?? 0) : null,
                $classIds
            );

            $placed = (int) ($result['placed'] ?? 0);
            $failed = (int) ($result['failed'] ?? 0);
            Session::flash('success', sprintf('Draft jadwal berhasil dibuat. %d blok ditempatkan, %d blok gagal.', $placed, $failed));

            return $this->redirect($this->contextUrl($schoolYearId, $semester, $level, (int) $result['draft_id'], $classIds));
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal generate jadwal: ' . $exception->getMessage());

            return $this->redirect($this->contextUrl($schoolYearId, $semester, $level, null, $classIds));
        }
    }

    public function validateDraft(Request $request, int $draft): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureScheduleAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/jadwal/generate')) {
            return $response;
        }

        $record = AutomaticSchedule::findDraft($draft);
        if ($record === null) {
            Session::flash('error', 'Draft jadwal tidak ditemukan.');
            return $this->redirect('akademik/jadwal/generate');
        }

        $conflicts = (new AutomaticScheduleGenerator())->validateDraft($draft);
        $total = $this->countConflicts($conflicts);
        Session::flash($total > 0 ? 'error' : 'success', $total > 0 ? 'Validasi selesai. Masih ada ' . $total . ' catatan.' : 'Validasi selesai tanpa konflik.');

        return $this->redirect($this->draftUrl($record, $this->selectedClassIds($request)));
    }

    public function updateItem(Request $request, int $draft, int $item): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureScheduleAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/jadwal/generate')) {
            return $response;
        }

        $record = AutomaticSchedule::findDraft($draft);
        if ($record === null || !$this->draftOwnsItem($draft, $item)) {
            Session::flash('error', 'Item draft jadwal tidak ditemukan.');
            return $this->redirect('akademik/jadwal/generate');
        }

        $attributes = $this->validateManualPayload($request, $record);
        if ($attributes === null) {
            return $this->redirect($this->draftUrl($record, $this->selectedClassIds($request)));
        }

        AutomaticSchedule::updateItem($item, $attributes);
        $conflicts = (new AutomaticScheduleGenerator())->validateDraft($draft);
        $total = $this->countConflicts($conflicts);
        Session::flash($total > 0 ? 'error' : 'success', $total > 0 ? 'Item diperbarui. Periksa ' . $total . ' catatan validasi.' : 'Item diperbarui tanpa konflik.');

        return $this->redirect($this->draftUrl($record, $this->selectedClassIds($request)));
    }

    public function toggleLock(Request $request, int $draft, int $item): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureScheduleAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/jadwal/generate')) {
            return $response;
        }

        $record = AutomaticSchedule::findDraft($draft);
        if ($record === null || !$this->draftOwnsItem($draft, $item)) {
            Session::flash('error', 'Item draft jadwal tidak ditemukan.');
            return $this->redirect('akademik/jadwal/generate');
        }

        AutomaticSchedule::updateItem($item, [
            'is_locked' => (int) $request->input('is_locked', 0) === 1 ? 1 : 0,
        ]);
        (new AutomaticScheduleGenerator())->validateDraft($draft);
        Session::flash('success', 'Status lock jadwal diperbarui.');

        return $this->redirect($this->draftUrl($record, $this->selectedClassIds($request)));
    }

    public function deleteItem(Request $request, int $draft, int $item): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureScheduleAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/jadwal/generate')) {
            return $response;
        }

        $record = AutomaticSchedule::findDraft($draft);
        if ($record === null || !$this->draftOwnsItem($draft, $item)) {
            Session::flash('error', 'Item draft jadwal tidak ditemukan.');
            return $this->redirect('akademik/jadwal/generate');
        }

        AutomaticSchedule::deleteItem($item);
        (new AutomaticScheduleGenerator())->validateDraft($draft);
        Session::flash('success', 'Item jadwal dihapus dari draft.');

        return $this->redirect($this->draftUrl($record, $this->selectedClassIds($request)));
    }

    public function activate(Request $request, int $draft): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureScheduleAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/jadwal/generate')) {
            return $response;
        }

        $record = AutomaticSchedule::findDraft($draft);
        if ($record === null) {
            Session::flash('error', 'Draft jadwal tidak ditemukan.');
            return $this->redirect('akademik/jadwal/generate');
        }

        $conflicts = (new AutomaticScheduleGenerator())->validateDraft($draft);
        $blocking = $this->countBlockingConflicts($conflicts);
        if ($blocking > 0) {
            Session::flash('error', 'Draft belum bisa ditetapkan karena masih ada ' . $blocking . ' konflik utama.');
            return $this->redirect($this->draftUrl($record, $this->selectedClassIds($request)));
        }

        try {
            if (!AutomaticSchedule::activateDraft($draft, [])) {
                Session::flash('error', 'Draft tidak memiliki item valid untuk diaktifkan.');
                return $this->redirect($this->draftUrl($record, $this->selectedClassIds($request)));
            }

            Session::flash('success', 'Draft berhasil ditetapkan sebagai jadwal aktif.');
            return $this->redirect($this->draftUrl(AutomaticSchedule::findDraft($draft) ?? $record, $this->selectedClassIds($request)));
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal mengaktifkan draft: ' . $exception->getMessage());

            return $this->redirect($this->draftUrl($record, $this->selectedClassIds($request)));
        }
    }

    public function export(Request $request, int $draft): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureScheduleAccess()) {
            return $response;
        }

        $record = AutomaticSchedule::findDraft($draft);
        if ($record === null) {
            return Response::make('Draft jadwal tidak ditemukan.', 404);
        }

        $format = strtolower((string) $request->query('format', 'xlsx'));
        $scope = strtolower((string) $request->query('scope', 'all'));
        $scopeId = (int) $request->query('scope_id', 0);
        $scopeValue = strtolower(trim((string) $request->query('scope_value', '')));
        if ($scopeValue !== '' && str_contains($scopeValue, ':')) {
            [$scopeCandidate, $scopeIdCandidate] = explode(':', $scopeValue, 2);
            $scope = in_array($scopeCandidate, ['all', 'kelas', 'guru'], true) ? $scopeCandidate : 'all';
            $scopeId = (int) $scopeIdCandidate;
        }
        $items = AutomaticSchedule::draftItems($draft);
        $exporter = new AutomaticScheduleExportService();
        $filteredItems = $exporter->filterItems($items, $scope, $scopeId);
        $schoolYear = SchoolYear::find((int) ($record['tahun_ajaran_id'] ?? 0));
        $schoolYearId = (int) ($record['tahun_ajaran_id'] ?? 0);
        $semester = (int) ($record['semester'] ?? 1);
        $level = isset($record['tingkat']) ? (int) $record['tingkat'] : 0;
        $level = $level > 0 ? $level : null;
        $classIds = $this->selectedClassIds($request);
        if ($scope === 'kelas' && $scopeId > 0) {
            $classIds = [$scopeId];
        }
        if (empty($classIds)) {
            $classIds = array_values(array_unique(array_filter(array_map(
                static fn (array $item): int => (int) ($item['kelas_id'] ?? 0),
                $filteredItems
            ))));
        }
        $classes = $schoolYearId > 0 ? AutomaticSchedule::classroomsForContext($schoolYearId, $level, $classIds) : [];
        $periods = $schoolYearId > 0 ? AutomaticSchedule::periodsByDay($schoolYearId) : [];
        $activities = $schoolYearId > 0 ? AutomaticSchedule::fixedActivities($schoolYearId) : [];
        $schoolProfile = SchoolProfile::first();
        $headmaster = null;
        $headmasterId = is_array($schoolYear) ? (int) ($schoolYear['kepala_sekolah_id'] ?? 0) : 0;
        if ($headmasterId > 0) {
            $headmaster = Teacher::find($headmasterId);
        }
        $context = [
            'school_name' => (string) ($schoolProfile['nama'] ?? config('app.name', 'Sekolah')),
            'school_year' => (string) ($schoolYear['nama'] ?? 'Tahun Ajaran'),
            'semester_label' => $this->semesterLabel($semester),
            'level_label' => $record['tingkat'] !== null ? 'Tingkat ' . (int) $record['tingkat'] : 'Semua Tingkat',
            'signature_city' => (string) ($schoolProfile['kabupaten'] ?? ''),
            'signature_date_label' => $this->formatIndonesianDate(date('Y-m-d')),
            'headmaster_name' => trim((string) ($headmaster['nama'] ?? '')) !== '' ? (string) $headmaster['nama'] : '________________',
            'headmaster_nip' => trim((string) ($headmaster['nip'] ?? '')),
        ];
        $filenameBase = 'jadwal-generate-draft-' . $draft . '-' . date('YmdHis');

        if ($format === 'print') {
            $withPrintButton = (string) $request->query('embed', '') !== '1';
            return Response::make($exporter->makePrintHtml($filteredItems, $context, $classes, $periods, $activities, $withPrintButton), 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        }

        if ($format === 'pdf') {
            $content = $exporter->makePdf($record, $filteredItems, $context, $classes, $periods, $activities);
            return Response::make($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filenameBase . '.pdf"',
            ]);
        }

        $conflicts = $this->decodeConflicts($record['conflict_json'] ?? null);
        $content = $exporter->makeXlsx($record, $filteredItems, $context, $conflicts, $classes, $periods, $activities);

        return Response::make($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filenameBase . '.xlsx"',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $allYears
     */
    private function resolveSchoolYearId(Request $request, array $allYears): int
    {
        $selected = (int) $request->query('tahun_ajaran_id', 0);
        if ($selected > 0) {
            return $selected;
        }

        foreach ($allYears as $year) {
            if (($year['status'] ?? 'nonaktif') === 'aktif') {
                return (int) ($year['id'] ?? 0);
            }
        }

        return (int) ($allYears[0]['id'] ?? 0);
    }

    private function normalizeSemester(mixed $value): int
    {
        return (int) $value === 2 ? 2 : 1;
    }

    private function normalizeLevel(mixed $value): ?int
    {
        $level = (int) $value;

        return in_array($level, [10, 11, 12], true) ? $level : null;
    }

    /**
     * @return array<int>
     */
    private function selectedClassIds(Request $request): array
    {
        $value = $request->input('class_ids', []);
        if (!is_array($value)) {
            $value = preg_split('/[,\s]+/', trim((string) $value)) ?: [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $value))));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int> $classIds
     * @return array<int, array<string, mixed>>
     */
    private function filterItemsByClassIds(array $items, array $classIds): array
    {
        $classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        if (empty($classIds)) {
            return $items;
        }

        $allowed = array_fill_keys($classIds, true);

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => isset($allowed[(int) ($item['kelas_id'] ?? 0)])
        ));
    }

    /**
     * @return array<int, string>
     */
    private function levelOptions(int $schoolYearId): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        $levels = [];
        foreach (AutomaticSchedule::classroomsForContext($schoolYearId) as $classroom) {
            $level = (int) ($classroom['tingkat'] ?? 0);
            if ($level > 0) {
                $levels[$level] = 'Tingkat ' . $level;
            }
        }
        ksort($levels);

        return $levels;
    }

    /**
     * @param array<int, array<string, mixed>> $assignments
     * @return array{options: array<int, string>, class_map: array<int, array<int, string>>}
     */
    private function buildAssignmentData(array $assignments): array
    {
        $options = [];
        $classMap = [];

        foreach ($assignments as $assignment) {
            $assignmentId = (int) ($assignment['id'] ?? 0);
            if ($assignmentId <= 0) {
                continue;
            }

            $label = trim(sprintf(
                '%s - %s - %s',
                (string) ($assignment['mata_pelajaran_kode'] ?? ''),
                (string) ($assignment['mata_pelajaran_nama'] ?? ''),
                (string) ($assignment['guru_nama'] ?? '')
            ));
            $options[$assignmentId] = $label;
            $classMap[$assignmentId] = [];

            foreach (($assignment['classes'] ?? []) as $classroom) {
                $classId = (int) ($classroom['id'] ?? 0);
                if ($classId <= 0) {
                    continue;
                }

                $classMap[$assignmentId][$classId] = $this->classLabel($classroom);
            }
        }

        return ['options' => $options, 'class_map' => $classMap];
    }

    /**
     * @param array<int, array<string, mixed>> $classes
     * @return array<int, string>
     */
    private function classOptions(array $classes): array
    {
        $options = [];
        foreach ($classes as $classroom) {
            $classId = (int) ($classroom['id'] ?? 0);
            if ($classId > 0) {
                $options[$classId] = $this->classLabel($classroom);
            }
        }

        return $options;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $periods
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $classes
     * @param array<int, array<string, mixed>> $activities
     * @return array<int, array<string, mixed>>
     */
    private function buildGridRows(array $periods, array $items, array $classes, array $activities): array
    {
        $classIds = array_map(static fn (array $classroom): int => (int) ($classroom['id'] ?? 0), $classes);
        $activityMap = [];
        foreach ($activities as $activity) {
            $day = (string) ($activity['hari'] ?? '');
            for ($lessonNo = (int) ($activity['jam_ke_mulai'] ?? 0); $lessonNo <= (int) ($activity['jam_ke_selesai'] ?? 0); $lessonNo++) {
                if ($day !== '' && $lessonNo > 0) {
                    $activityMap[$day][$lessonNo] = (string) ($activity['nama'] ?? 'Kegiatan Tetap');
                }
            }
        }

        $itemMap = [];
        foreach ($items as $item) {
            if (($item['status'] ?? '') === 'failed') {
                continue;
            }
            $day = (string) ($item['hari'] ?? '');
            $classId = (int) ($item['kelas_id'] ?? 0);
            $start = (int) ($item['jam_ke_mulai'] ?? 0);
            $end = (int) ($item['jam_ke_selesai'] ?? 0);
            if ($day === '' || $classId <= 0 || $start <= 0 || $end < $start) {
                continue;
            }
            for ($lessonNo = $start; $lessonNo <= $end; $lessonNo++) {
                $itemMap[$day][$lessonNo][$classId][] = $item;
            }
        }

        $rows = [];
        foreach (array_keys(AutomaticSchedule::DAYS) as $day) {
            if (!isset($periods[$day])) {
                continue;
            }
            ksort($periods[$day]);
            foreach ($periods[$day] as $lessonNo => $period) {
                $cells = [];
                foreach ($classIds as $classId) {
                    $cells[$classId] = $itemMap[$day][$lessonNo][$classId] ?? [];
                }
                $rows[] = [
                    'day' => $day,
                    'lesson_no' => (int) $lessonNo,
                    'time' => substr((string) ($period['waktu_mulai'] ?? ''), 0, 5) . ' - ' . substr((string) ($period['waktu_selesai'] ?? ''), 0, 5),
                    'type' => isset($activityMap[$day][$lessonNo]) ? 'kegiatan' : (string) ($period['tipe'] ?? 'pelajaran'),
                    'label' => $activityMap[$day][$lessonNo] ?? (string) ($period['label'] ?? ''),
                    'cells' => $cells,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $draft
     * @return array<string, mixed>|null
     */
    private function validateManualPayload(Request $request, array $draft): ?array
    {
        $assignmentId = (int) $request->input('guru_mata_pelajaran_id', 0);
        $classId = (int) $request->input('kelas_id', 0);
        $day = strtolower(trim((string) $request->input('hari', '')));
        $startNo = (int) $request->input('jam_ke_mulai', 0);
        $hours = (int) $request->input('jumlah_jam', 0);
        $roomId = (int) $request->input('ruangan_id', 0);
        $isLocked = (int) $request->input('is_locked', 0) === 1 ? 1 : 0;
        $schoolYearId = (int) ($draft['tahun_ajaran_id'] ?? 0);
        $semester = (int) ($draft['semester'] ?? 1);

        if ($assignmentId <= 0 || $classId <= 0 || $day === '' || $startNo <= 0 || $hours <= 0) {
            Session::flash('error', 'Guru pengampu, kelas, hari, jam mulai, dan JP wajib diisi.');
            return null;
        }

        if ($hours > 10 || !array_key_exists($day, AutomaticSchedule::DAYS)) {
            Session::flash('error', 'Hari atau jumlah JP tidak valid.');
            return null;
        }

        $assignment = SubjectTeacher::findWithRelations($assignmentId);
        if ($assignment === null || (int) ($assignment['mata_pelajaran_tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            Session::flash('error', 'Guru pengampu tidak sesuai tahun ajaran draft.');
            return null;
        }

        $allowedClassIds = array_map(static fn (array $classroom): int => (int) ($classroom['id'] ?? 0), $assignment['classes'] ?? []);
        if (!in_array($classId, $allowedClassIds, true)) {
            Session::flash('error', 'Kelas tidak sesuai dengan mapping guru pengampu.');
            return null;
        }

        $range = (new AutomaticScheduleGenerator())->periodRange($schoolYearId, $day, $startNo, $hours);
        if ($range === null) {
            Session::flash('error', 'Rentang jam pelajaran tidak tersedia.');
            return null;
        }

        $periods = AutomaticSchedule::periodsByDay($schoolYearId);
        for ($lessonNo = $startNo; $lessonNo <= (int) $range['end_no']; $lessonNo++) {
            if (($periods[$day][$lessonNo]['tipe'] ?? 'pelajaran') !== 'pelajaran') {
                Session::flash('error', 'Rentang jadwal menyentuh jam istirahat atau kegiatan tetap.');
                return null;
            }
        }

        return [
            'tahun_ajaran_id' => $schoolYearId,
            'semester' => $semester,
            'guru_mata_pelajaran_id' => $assignmentId,
            'guru_id' => (int) ($assignment['guru_id'] ?? 0),
            'kelas_id' => $classId,
            'ruangan_id' => $roomId > 0 ? $roomId : null,
            'hari' => $day,
            'jam_ke_mulai' => (int) $range['start_no'],
            'jam_ke_selesai' => (int) $range['end_no'],
            'waktu_mulai' => (string) $range['start_time'],
            'waktu_selesai' => (string) $range['end_time'],
            'jumlah_jam' => $hours,
            'status' => 'manual',
            'is_locked' => $isLocked,
            'catatan' => null,
        ];
    }

    private function draftOwnsItem(int $draftId, int $itemId): bool
    {
        foreach (AutomaticSchedule::draftItems($draftId) as $item) {
            if ((int) ($item['id'] ?? 0) === $itemId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function decodeConflicts(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        $conflicts = [];
        foreach ($decoded as $key => $messages) {
            if (!is_array($messages)) {
                continue;
            }
            $conflicts[(string) $key] = array_values(array_filter(array_map('strval', $messages)));
        }

        return $conflicts;
    }

    /**
     * @param array<string, array<int, string>> $conflicts
     */
    private function countConflicts(array $conflicts): int
    {
        return array_reduce($conflicts, static fn (int $carry, array $messages): int => $carry + count($messages), 0);
    }

    /**
     * @param array<string, array<int, string>> $conflicts
     */
    private function countBlockingConflicts(array $conflicts): int
    {
        $blockingKeys = [
            'teacher_conflicts',
            'class_conflicts',
            'room_conflicts',
            'blocked_slots',
            'unavailable_teachers',
            'missing_hours',
            'teacher_overloads',
            'failed_items',
        ];

        $total = 0;
        foreach ($blockingKeys as $key) {
            $total += count($conflicts[$key] ?? []);
        }

        return $total;
    }

    /**
     * @param array<int> $classIds
     */
    private function contextUrl(int $schoolYearId, int $semester, ?int $level = null, ?int $draftId = null, array $classIds = []): string
    {
        $classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        $query = [
            'tahun_ajaran_id' => $schoolYearId,
            'semester' => $semester,
        ];
        if ($level !== null && $level > 0) {
            $query['tingkat'] = $level;
        }
        if ($draftId !== null && $draftId > 0) {
            $query['draft_id'] = $draftId;
        }
        if (!empty($classIds)) {
            $query['class_ids'] = $classIds;
        }

        return 'akademik/jadwal/generate?' . http_build_query($query);
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<int> $classIds
     */
    private function draftUrl(array $draft, array $classIds = []): string
    {
        $level = isset($draft['tingkat']) ? (int) $draft['tingkat'] : 0;
        $classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        if (empty($classIds)) {
            $draftId = (int) ($draft['id'] ?? 0);
            if ($draftId > 0) {
                $classIds = array_values(array_unique(array_filter(array_map(
                    static fn (array $item): int => (int) ($item['kelas_id'] ?? 0),
                    AutomaticSchedule::draftItems($draftId)
                ))));
            }
        }

        return $this->contextUrl(
            (int) ($draft['tahun_ajaran_id'] ?? 0),
            (int) ($draft['semester'] ?? 1),
            $level > 0 ? $level : null,
            (int) ($draft['id'] ?? 0),
            $classIds
        );
    }

    private function semesterLabel(int $semester): string
    {
        return $semester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
    }

    private function formatIndonesianDate(string $date): string
    {
        try {
            $value = new \DateTimeImmutable($date);
        } catch (\Throwable) {
            return date('d/m/Y');
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

        return $value->format('d') . ' ' . ($months[(int) $value->format('n')] ?? $value->format('F')) . ' ' . $value->format('Y');
    }

    /**
     * @param array<string, mixed> $classroom
     */
    private function classLabel(array $classroom): string
    {
        $label = trim('Kelas ' . (string) ($classroom['tingkat'] ?? '-') . ' ' . (string) ($classroom['nama'] ?? '-'));
        if (!empty($classroom['jurusan_nama'])) {
            $label .= ' (' . $classroom['jurusan_nama'] . ')';
        }

        return $label;
    }

    private function ensureScheduleAccess(): ?Response
    {
        $user = auth();

        if (\App\Support\UserModuleRules::allowsCurrentRequest($user, true)) {
            return null;
        }

        if (
            AcademicRoleGate::isTataUsaha($user)
            || AcademicRoleGate::isWakaKurikulum($user)
        ) {
            return null;
        }

        return $this->ensureRole(['admin', 'staff']);
    }
}
