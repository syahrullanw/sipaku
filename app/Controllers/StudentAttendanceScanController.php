<?php

namespace App\Controllers;

use App\Models\Student;
use App\Models\SchoolProfile;
use App\Models\StudentAttendanceSession;
use App\Models\StudentAttendanceSessionDetail;
use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class StudentAttendanceScanController extends Controller
{
    protected ?string $layout = 'app';

    public function index(Request $request): Response
    {
        if (!Auth::check()) {
            Session::set('intended_url', 'presensi/scan');
            Session::flash('error', 'Silakan login sebagai siswa untuk mengakses presensi.');

            return $this->redirect('login');
        }

        $sessionToken = trim((string) $request->query('token', ''));

        if ($sessionToken !== '') {
            return $this->redirect('presensi/scan/' . $sessionToken);
        }

        return $this->render('student/attendance/scan', [
            'title' => 'Presensi Siswa',
            'sessionData' => null,
            'student' => null,
            'record' => null,
            'token' => '',
            'isActive' => false,
            'alreadyRecorded' => false,
            'errorMessage' => null,
        ]);
    }

    public function show(Request $request, string $token): Response
    {
        $normalizedToken = trim($token);

        if ($normalizedToken === '') {
            return $this->render('student/attendance/scan', [
                'title' => 'Presensi Siswa',
                'sessionData' => null,
                'student' => null,
                'record' => null,
                'token' => '',
                'isActive' => false,
                'alreadyRecorded' => false,
                'errorMessage' => 'Token presensi tidak valid.',
            ]);
        }

        $session = StudentAttendanceSession::findByToken($normalizedToken);

        if ($session === null) {
            return $this->render('student/attendance/scan', [
                'title' => 'Presensi Siswa',
                'sessionData' => null,
                'student' => null,
                'record' => null,
                'token' => $normalizedToken,
                'isActive' => false,
                'alreadyRecorded' => false,
                'errorMessage' => 'Sesi presensi tidak ditemukan atau telah dihapus.',
            ]);
        }

        $isActive = StudentAttendanceSession::isActive($session);

        if (!Auth::check()) {
            Session::set('intended_url', 'presensi/scan/' . $normalizedToken);
            Session::flash('error', 'Silakan login sebagai siswa untuk mencatat presensi.');

            return $this->redirect('login');
        }

        $user = auth();
        $role = (string) ($user['role'] ?? '');
        $studentId = (int) ($user['student_id'] ?? 0);

        if ($role !== 'siswa' || $studentId <= 0) {
            return $this->render('student/attendance/scan', [
                'title' => 'Presensi Siswa',
                'sessionData' => $session,
                'student' => null,
                'record' => null,
                'token' => $normalizedToken,
                'isActive' => $isActive,
                'alreadyRecorded' => false,
                'errorMessage' => 'Presensi ini hanya dapat diakses melalui akun siswa.',
            ]);
        }

        $student = Student::findWithRelations($studentId);

        if ($student === null) {
            return $this->render('student/attendance/scan', [
                'title' => 'Presensi Siswa',
                'sessionData' => $session,
                'student' => null,
                'record' => null,
                'token' => $normalizedToken,
                'isActive' => $isActive,
                'alreadyRecorded' => false,
                'errorMessage' => 'Data siswa tidak ditemukan.',
            ]);
        }

        $studentRestrictionMessage = Auth::studentAccessRestrictionMessage($student);
        if ($studentRestrictionMessage !== null) {
            return $this->render('student/attendance/scan', [
                'title' => 'Presensi Siswa',
                'sessionData' => null,
                'student' => null,
                'record' => null,
                'token' => $normalizedToken,
                'isActive' => false,
                'alreadyRecorded' => false,
                'errorMessage' => $studentRestrictionMessage,
            ]);
        }

        $studentClassId = (int) ($student['kelas_id'] ?? 0);
        $sessionClassId = (int) ($session['kelas_id'] ?? 0);
        $sessionParallelClassId = (int) ($session['kelas_paralel_id'] ?? 0);

        if ($studentClassId !== $sessionClassId && $studentClassId !== $sessionParallelClassId) {
            return $this->render('student/attendance/scan', [
                'title' => 'Presensi Siswa',
                'sessionData' => $session,
                'student' => $student,
                'record' => null,
                'token' => $normalizedToken,
                'isActive' => false,
                'alreadyRecorded' => false,
                'errorMessage' => 'Sesi presensi ini tidak diperuntukkan bagi kelas Anda.',
            ]);
        }

        $record = StudentAttendanceSessionDetail::findForSessionAndStudent(
            (int) ($session['id'] ?? 0),
            $studentId
        );

        return $this->render('student/attendance/scan', [
            'title' => 'Presensi Siswa',
            'sessionData' => $session,
            'student' => $student,
            'record' => $record,
            'token' => $normalizedToken,
            'isActive' => $isActive,
            'alreadyRecorded' => $record !== null,
            'errorMessage' => null,
        ]);
    }

    public function submit(Request $request, string $token): Response
    {
        $normalizedToken = trim($token);

        if ($normalizedToken === '') {
            Session::flash('error', 'Token presensi tidak valid.');

            return $this->redirect('presensi/scan/' . $token);
        }

        $session = StudentAttendanceSession::findByToken($normalizedToken);

        if ($session === null) {
            Session::flash('error', 'Sesi presensi tidak ditemukan atau telah dihapus.');

            return $this->redirect('presensi/scan/' . $normalizedToken);
        }

        if (!Auth::check()) {
            Session::set('intended_url', 'presensi/scan/' . $normalizedToken);
            Session::flash('error', 'Silakan login sebagai siswa untuk mencatat presensi.');

            return $this->redirect('login');
        }

        if ($response = $this->guardCsrf($request, 'presensi/scan/' . $normalizedToken)) {
            return $response;
        }

        $user = auth();
        $role = (string) ($user['role'] ?? '');
        $studentId = (int) ($user['student_id'] ?? 0);

        if ($role !== 'siswa' || $studentId <= 0) {
            Session::flash('error', 'Presensi ini hanya dapat diakses melalui akun siswa.');

            return $this->redirect('presensi/scan/' . $normalizedToken);
        }

        $student = Student::findWithRelations($studentId);

        if ($student === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');

            return $this->redirect('presensi/scan/' . $normalizedToken);
        }

        $studentRestrictionMessage = Auth::studentAccessRestrictionMessage($student);
        if ($studentRestrictionMessage !== null) {
            Session::flash('error', $studentRestrictionMessage);

            return $this->redirect('presensi/scan/' . $normalizedToken);
        }

        $studentClassId = (int) ($student['kelas_id'] ?? 0);
        $sessionClassId = (int) ($session['kelas_id'] ?? 0);
        $sessionParallelClassId = (int) ($session['kelas_paralel_id'] ?? 0);

        if ($studentClassId !== $sessionClassId && $studentClassId !== $sessionParallelClassId) {
            Session::flash('error', 'Sesi presensi ini tidak diperuntukkan bagi kelas Anda.');

            return $this->redirect('presensi/scan/' . $normalizedToken);
        }

        if (!StudentAttendanceSession::isActive($session)) {
            Session::flash('error', 'Sesi presensi sudah ditutup.');

            return $this->redirect('presensi/scan/' . $normalizedToken);
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
            $studentLatRaw = $request->input('latitude');
            $studentLngRaw = $request->input('longitude');

            if (!is_numeric($studentLatRaw) || !is_numeric($studentLngRaw)) {
                Session::flash('error', 'Tidak dapat membaca lokasi Anda. Aktifkan layanan lokasi pada perangkat.');

                return $this->redirect('presensi/scan/' . $normalizedToken);
            }

            $studentLat = (float) $studentLatRaw;
            $studentLng = (float) $studentLngRaw;
            $schoolLat = (float) $schoolProfile['latitude'];
            $schoolLng = (float) $schoolProfile['longitude'];
            $radius = (float) $schoolProfile['presensi_radius_meter'];

            $distance = $this->distanceInMeters($schoolLat, $schoolLng, $studentLat, $studentLng);

            if ($distance > $radius) {
                Session::flash('error', 'Anda berada di luar area presensi yang diizinkan (jarak ' . number_format($distance, 1) . ' meter).');

                return $this->redirect('presensi/scan/' . $normalizedToken);
            }
        }

        StudentAttendanceSessionDetail::recordScan(
            (int) ($session['id'] ?? 0),
            $studentId
        );

        Session::flash('success', 'Presensi berhasil dicatat.');

        return $this->redirect('presensi/scan/' . $normalizedToken);
    }

    private function distanceInMeters(float $latFrom, float $lonFrom, float $latTo, float $lonTo): float
    {
        $earthRadius = 6371000.0;

        $latFromRad = deg2rad($latFrom);
        $lonFromRad = deg2rad($lonFrom);
        $latToRad = deg2rad($latTo);
        $lonToRad = deg2rad($lonTo);

        $deltaLat = $latToRad - $latFromRad;
        $deltaLon = $lonToRad - $lonFromRad;

        $a = sin($deltaLat / 2) ** 2 + cos($latFromRad) * cos($latToRad) * sin($deltaLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
