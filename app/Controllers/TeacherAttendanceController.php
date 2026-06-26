<?php

namespace App\Controllers;

use App\Models\LessonSchedule;
use App\Models\SchoolYear;
use App\Models\SchoolProfile;
use App\Models\Student;
use App\Models\StudentAttendanceSession;
use App\Models\StudentAttendanceSessionDetail;
use App\Support\AttendanceStatus;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use RuntimeException;

class TeacherAttendanceController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureRole('guru')) {
            return $response;
        }

        $user = auth();
        $teacherId = (int) ($user['teacher_id'] ?? 0);

        if ($teacherId <= 0) {
            Session::flash('error', 'Akun guru tidak memiliki data pengampu.');

            return $this->redirect('dashboard');
        }

        $activeYear = SchoolYear::active();
        $activeYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : null;

        $schedules = LessonSchedule::forTeacher($teacherId, $activeYearId);
        $replacementSchedules = LessonSchedule::listWithRelations($activeYearId);
        $sessions = StudentAttendanceSession::forTeacher($teacherId, $activeYearId, 30);
        $focusSessionId = (int) $request->query('session', 0);
        $schoolProfile = SchoolProfile::first();
        $requiresLocation = false;
        $schoolLocation = null;

        if (
            $schoolProfile !== null
            && isset($schoolProfile['latitude'], $schoolProfile['longitude'], $schoolProfile['presensi_radius_meter'])
            && $schoolProfile['latitude'] !== null
            && $schoolProfile['longitude'] !== null
            && $schoolProfile['presensi_radius_meter'] !== null
            && (float) $schoolProfile['presensi_radius_meter'] > 0
        ) {
            $requiresLocation = true;
            $schoolLocation = [
                'latitude' => (float) $schoolProfile['latitude'],
                'longitude' => (float) $schoolProfile['longitude'],
                'radius' => (float) $schoolProfile['presensi_radius_meter'],
            ];
        }

        return $this->render('teacher/attendance/index', [
            'title' => 'Presensi Siswa',
            'pageTitle' => 'Presensi Siswa via QR',
            'activeMenu' => 'teacher-attendance',
            'schedules' => $schedules,
            'replacementSchedules' => $replacementSchedules,
            'sessions' => $sessions,
            'activeYear' => $activeYear,
            'focusSessionId' => $focusSessionId > 0 ? $focusSessionId : null,
            'statusOptions' => AttendanceStatus::options(),
            'requiresLocation' => $requiresLocation,
            'schoolLocation' => $schoolLocation,
        ]);
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'guru/presensi')) {
            return $response;
        }

        if ($response = $this->ensureRole('guru')) {
            return $response;
        }

        $user = auth();
        $teacherId = (int) ($user['teacher_id'] ?? 0);

        if ($teacherId <= 0) {
            Session::flash('error', 'Akun guru tidak memiliki data pengampu.');

            return $this->redirect('guru/presensi');
        }

        $sessionType = (string) $request->input('tipe_sesi', 'jadwal');
        $isReplacement = $sessionType === 'pengganti';
        $scheduleId = $isReplacement
            ? (int) $request->input('jadwal_pengganti_id', 0)
            : (int) $request->input('jadwal_id', 0);
        $parallelScheduleId = (int) $request->input('jadwal_paralel_id', 0);
        $replacementNote = trim((string) $request->input('catatan_pengganti', ''));
        $agenda = trim((string) $request->input('agenda', ''));
        $date = trim((string) $request->input('tanggal', date('Y-m-d')));
        $duration = (int) $request->input('durasi', 60);

        if ($scheduleId <= 0) {
            Session::flash('error', 'Pilih jadwal pengajaran yang valid.');
            Session::flashInput($request->all());

            return $this->redirect('guru/presensi');
        }

        if ($agenda === '') {
            Session::flash('error', 'Agenda harian harus diisi.');
            Session::flashInput($request->all());

            return $this->redirect('guru/presensi');
        }

        if ($isReplacement && $replacementNote === '') {
            Session::flash('error', 'Catatan guru pengganti wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect('guru/presensi');
        }

        $schedule = LessonSchedule::findWithRelations($scheduleId);

        if ($schedule === null) {
            Session::flash('error', 'Jadwal tidak ditemukan.');
            Session::flashInput($request->all());

            return $this->redirect('guru/presensi');
        }

        if (!$isReplacement && (int) ($schedule['guru_id'] ?? 0) !== $teacherId) {
            Session::flash('error', 'Jadwal tidak ditemukan atau bukan milik Anda.');
            Session::flashInput($request->all());

            return $this->redirect('guru/presensi');
        }

        $activeYear = SchoolYear::active();
        $activeYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : 0;
        if ($activeYearId > 0 && (int) ($schedule['tahun_ajaran_id'] ?? 0) !== $activeYearId) {
            Session::flash('error', 'Jadwal yang dipilih tidak berada pada tahun ajaran aktif.');
            Session::flashInput($request->all());

            return $this->redirect('guru/presensi');
        }

        if ($isReplacement) {
            $parallelScheduleId = 0;
        }

        $parallelClassId = null;
        $parallelSchedule = null;

        if ($parallelScheduleId > 0) {
            if ($parallelScheduleId === $scheduleId) {
                Session::flash('error', 'Jadwal paralel harus berbeda dari jadwal utama.');
                Session::flashInput($request->all());

                return $this->redirect('guru/presensi');
            }

            $parallelSchedule = LessonSchedule::findWithRelations($parallelScheduleId);

            if ($parallelSchedule === null || (int) ($parallelSchedule['guru_id'] ?? 0) !== $teacherId) {
                Session::flash('error', 'Jadwal paralel tidak ditemukan atau bukan milik Anda.');
                Session::flashInput($request->all());

                return $this->redirect('guru/presensi');
            }

            $parallelClassId = (int) ($parallelSchedule['kelas_id'] ?? 0);
            $baseClassId = (int) ($schedule['kelas_id'] ?? 0);
            $sameLevel = (string) ($schedule['kelas_tingkat'] ?? '') !== ''
                && (string) ($parallelSchedule['kelas_tingkat'] ?? '') !== ''
                && (string) ($schedule['kelas_tingkat'] ?? '') === (string) ($parallelSchedule['kelas_tingkat'] ?? '');
            $sameDay = (string) ($schedule['hari'] ?? '') === (string) ($parallelSchedule['hari'] ?? '');
            $sameStart = (string) ($schedule['waktu_mulai'] ?? '') === (string) ($parallelSchedule['waktu_mulai'] ?? '');
            $sameEnd = (string) ($schedule['waktu_selesai'] ?? '') === (string) ($parallelSchedule['waktu_selesai'] ?? '');
            $sameYear = (int) ($schedule['tahun_ajaran_id'] ?? 0) === (int) ($parallelSchedule['tahun_ajaran_id'] ?? 0);

            if (
                $parallelClassId <= 0
                || $parallelClassId === $baseClassId
                || !$sameLevel
                || !$sameDay
                || !$sameStart
                || !$sameEnd
                || !$sameYear
            ) {
                Session::flash('error', 'Jadwal paralel harus kelas berbeda dengan tingkat dan waktu yang sama.');
                Session::flashInput($request->all());

                return $this->redirect('guru/presensi');
            }
        }

        $parsedDate = date_create_from_format('Y-m-d', $date);

        if ($parsedDate === false) {
            Session::flash('error', 'Tanggal tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('guru/presensi');
        }

        $scheduleDay = strtolower((string) ($schedule['hari'] ?? ''));
        $dayOptions = LessonSchedule::dayOptions();

        $dayMap = [
            'monday' => 'senin',
            'tuesday' => 'selasa',
            'wednesday' => 'rabu',
            'thursday' => 'kamis',
            'friday' => 'jumat',
            'saturday' => 'sabtu',
            'sunday' => 'minggu',
        ];

        $dateDay = strtolower($parsedDate->format('l'));
        $mappedDay = $dayMap[$dateDay] ?? null;

        if ($mappedDay === null || $mappedDay !== $scheduleDay) {
            $scheduleDayLabel = $dayOptions[$scheduleDay] ?? ucfirst($scheduleDay ?: '-');
            $dateDayLabel = $dayOptions[$mappedDay] ?? ucfirst($mappedDay ?? $parsedDate->format('l'));

            Session::flash('error', sprintf(
                'Tanggal presensi harus sesuai dengan hari jadwal (%s). Anda memilih hari %s.',
                $scheduleDayLabel,
                $dateDayLabel
            ));
            Session::flashInput($request->all());

            return $this->redirect('guru/presensi');
        }

        $schoolProfile = SchoolProfile::first();

        if (
            $schoolProfile !== null
            && isset($schoolProfile['latitude'], $schoolProfile['longitude'], $schoolProfile['presensi_radius_meter'])
            && $schoolProfile['latitude'] !== null
            && $schoolProfile['longitude'] !== null
            && $schoolProfile['presensi_radius_meter'] !== null
            && (float) $schoolProfile['presensi_radius_meter'] > 0
        ) {
            $teacherLatRaw = $request->input('latitude');
            $teacherLngRaw = $request->input('longitude');

            if (!is_numeric($teacherLatRaw) || !is_numeric($teacherLngRaw)) {
                Session::flash('error', 'Tidak dapat membaca lokasi perangkat. Aktifkan layanan lokasi dan coba lagi di area sekolah.');
                Session::flashInput($request->all());

                return $this->redirect('guru/presensi');
            }

            $teacherLat = (float) $teacherLatRaw;
            $teacherLng = (float) $teacherLngRaw;
            $schoolLat = (float) $schoolProfile['latitude'];
            $schoolLng = (float) $schoolProfile['longitude'];
            $radius = (float) $schoolProfile['presensi_radius_meter'];

            $distance = $this->distanceInMeters($schoolLat, $schoolLng, $teacherLat, $teacherLng);

            if ($distance > $radius) {
                Session::flash('error', sprintf(
                    'Anda berada di luar radius presensi yang diizinkan (jarak %.1f meter dari sekolah).',
                    $distance
                ));
                Session::flashInput($request->all());

                return $this->redirect('guru/presensi');
            }
        }

        try {
            $sessionId = StudentAttendanceSession::createForSchedule(
                $schedule,
                $parsedDate->format('Y-m-d'),
                $agenda,
                $duration,
                $parallelClassId,
                $teacherId,
                $isReplacement ? 'pengganti' : 'jadwal',
                $replacementNote
            );
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());

            return $this->redirect('guru/presensi');
        }

        if ($sessionId === null) {
            Session::flash('error', 'Gagal membuat sesi presensi.');

            return $this->redirect('guru/presensi');
        }

        $subjectName = trim((string) ($schedule['mata_pelajaran_nama'] ?? 'Sesi mengajar'));
        $className = trim((string) ($schedule['kelas_nama'] ?? 'kelas'));
        $parallelClassName = $parallelSchedule !== null ? trim((string) ($parallelSchedule['kelas_nama'] ?? '')) : '';
        $combinedClassName = $className;
        if ($parallelClassName !== '') {
            $combinedClassName .= ' + ' . $parallelClassName;
        }
        $dateLabel = $parsedDate->format('d M Y');
        $agendaSnippet = $agenda !== '' ? ' • agenda: ' . $this->shortenText($agenda) : '';
        activity(sprintf(
            'Membuat sesi mengajar %s untuk kelas %s pada %s%s%s',
            $subjectName,
            $combinedClassName,
            $dateLabel,
            $isReplacement ? ' sebagai guru pengganti' : '',
            $agendaSnippet
        ));

        Session::flash('success', $isReplacement ? 'QR presensi guru pengganti berhasil dibuat.' : 'QR presensi berhasil dibuat.');

        return $this->redirect('guru/presensi?session=' . $sessionId);
    }

    private function distanceInMeters(float $latFrom, float $lonFrom, float $latTo, float $lonTo): float
    {
        $earthRadius = 6371000.0;

        $latFromRad = deg2rad($latFrom);
        $lonFromRad = deg2rad($lonFrom);
        $latToRad = deg2rad($latTo);
        $lonToRad = deg2rad($lonTo);

        $latDelta = $latToRad - $latFromRad;
        $lonDelta = $lonToRad - $lonFromRad;

        $a = sin($latDelta / 2) ** 2
            + cos($latFromRad) * cos($latToRad) * sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function show(Request $request, int $sessionId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureRole('guru')) {
            return $response;
        }

        $user = auth();
        $teacherId = (int) ($user['teacher_id'] ?? 0);

        if ($teacherId <= 0) {
            Session::flash('error', 'Akun guru tidak memiliki data pengampu.');

            return $this->redirect('dashboard');
        }

        $session = StudentAttendanceSession::findForTeacher($sessionId, $teacherId);

        if ($session === null) {
            Session::flash('error', 'Sesi presensi tidak ditemukan.');

            return $this->redirect('guru/presensi');
        }

        $schoolYearId = (int) ($session['tahun_ajaran_id'] ?? 0);
        $classIds = $this->sessionClassIds($session);
        $students = [];

        if (!empty($classIds)) {
            $students = count($classIds) === 1
                ? Student::byClass($classIds[0], $schoolYearId)
                : Student::byClasses($classIds, $schoolYearId);
        }
        $records = StudentAttendanceSessionDetail::bySession($sessionId);
        $statusOptions = AttendanceStatus::options();
        $counts = StudentAttendanceSessionDetail::countsByStatus($sessionId);
        $isActive = StudentAttendanceSession::isActive($session);
        $scanUrl = absolute_url('presensi/scan/' . $session['token']);

        return $this->render('teacher/attendance/show', [
            'title' => 'Detail Presensi Siswa',
            'pageTitle' => 'Detail Presensi & Rekap',
            'activeMenu' => 'teacher-attendance',
            'sessionData' => $session,
            'students' => $students,
            'records' => $records,
            'statusOptions' => $statusOptions,
            'counts' => $counts,
            'isActive' => $isActive,
            'scanUrl' => $scanUrl,
        ]);
    }

    public function storeManual(Request $request, int $sessionId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'guru/presensi/' . $sessionId . '/manual')) {
            return $response;
        }

        if ($response = $this->ensureRole('guru')) {
            return $response;
        }

        $user = auth();
        $teacherId = (int) ($user['teacher_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);

        if ($teacherId <= 0 || $userId <= 0) {
            Session::flash('error', 'Akun guru tidak valid.');

            return $this->redirect('guru/presensi');
        }

        $session = StudentAttendanceSession::findForTeacher($sessionId, $teacherId);

        if ($session === null) {
            Session::flash('error', 'Sesi presensi tidak ditemukan.');

            return $this->redirect('guru/presensi');
        }

        $schoolYearId = (int) ($session['tahun_ajaran_id'] ?? 0);
        $classIds = $this->sessionClassIds($session);
        $students = [];

        if (!empty($classIds)) {
            $students = count($classIds) === 1
                ? Student::byClass($classIds[0], $schoolYearId)
                : Student::byClasses($classIds, $schoolYearId);
        }

        if (empty($students)) {
            Session::flash('error', 'Belum ada siswa pada kelas ini.');

            return $this->redirect('guru/presensi/' . $sessionId);
        }

        $statusInputs = $request->input('status', []);
        $noteInputs = $request->input('catatan', []);

        if (!is_array($statusInputs)) {
            $statusInputs = [];
        }
        if (!is_array($noteInputs)) {
            $noteInputs = [];
        }

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);

            if ($studentId <= 0) {
                continue;
            }

            $statusValue = $statusInputs[$studentId] ?? null;

            if (!is_string($statusValue) || $statusValue === '') {
                continue;
            }

            if (!AttendanceStatus::isValid($statusValue)) {
                continue;
            }

            $noteValue = $noteInputs[$studentId] ?? null;
            $noteValue = is_string($noteValue) ? $noteValue : null;

            StudentAttendanceSessionDetail::upsertManual(
                $sessionId,
                $studentId,
                $statusValue,
                $userId,
                $noteValue
            );
        }

        $subjectName = trim((string) ($session['mata_pelajaran_nama'] ?? 'Sesi mengajar'));
        $className = $this->formatSessionClassLabel($session);
        activity(sprintf(
            'Memperbarui presensi manual sesi #%d untuk kelas %s (%s)',
            $sessionId,
            $className,
            $subjectName
        ));

        Session::flash('success', 'Presensi manual berhasil diperbarui.');

        return $this->redirect('guru/presensi/' . $sessionId);
    }

    public function close(Request $request, int $sessionId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'guru/presensi/' . $sessionId . '/tutup')) {
            return $response;
        }

        if ($response = $this->ensureRole('guru')) {
            return $response;
        }

        $user = auth();
        $teacherId = (int) ($user['teacher_id'] ?? 0);

        if ($teacherId <= 0) {
            Session::flash('error', 'Akun guru tidak valid.');

            return $this->redirect('guru/presensi');
        }

        $session = StudentAttendanceSession::findForTeacher($sessionId, $teacherId);

        if ($session === null) {
            Session::flash('error', 'Sesi presensi tidak ditemukan.');

            return $this->redirect('guru/presensi');
        }

        if ((string) ($session['status'] ?? '') === 'ditutup') {
            Session::flash('info', 'Sesi presensi sudah ditutup sebelumnya.');

            return $this->redirect('guru/presensi/' . $sessionId);
        }

        StudentAttendanceSession::markClosed($sessionId);

        $subjectName = trim((string) ($session['mata_pelajaran_nama'] ?? 'Sesi mengajar'));
        $className = $this->formatSessionClassLabel($session);
        activity(sprintf(
            'Menutup sesi presensi #%d untuk kelas %s (%s)',
            $sessionId,
            $className,
            $subjectName
        ));

        Session::flash('success', 'Sesi presensi berhasil ditutup.');

        return $this->redirect('guru/presensi/' . $sessionId);
    }

    /**
     * @param array<string, mixed> $session
     * @return array<int, int>
     */
    private function sessionClassIds(array $session): array
    {
        $classIds = [];

        $primaryId = (int) ($session['kelas_id'] ?? 0);
        if ($primaryId > 0) {
            $classIds[] = $primaryId;
        }

        $parallelId = (int) ($session['kelas_paralel_id'] ?? 0);
        if ($parallelId > 0 && $parallelId !== $primaryId) {
            $classIds[] = $parallelId;
        }

        return $classIds;
    }

    /**
     * @param array<string, mixed> $session
     */
    private function formatSessionClassLabel(array $session): string
    {
        $className = trim((string) ($session['kelas_nama'] ?? 'kelas'));
        $parallelClassName = trim((string) ($session['kelas_paralel_nama'] ?? ''));

        if ($parallelClassName !== '') {
            $className .= ' + ' . $parallelClassName;
        }

        return $className;
    }

    private function shortenText(string $text, int $limit = 60): string
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($trimmed) <= $limit) {
                return $trimmed;
            }

            return rtrim(mb_substr($trimmed, 0, $limit - 1)) . '…';
        }

        if (strlen($trimmed) <= $limit) {
            return $trimmed;
        }

        return rtrim(substr($trimmed, 0, $limit - 1)) . '…';
    }
}
