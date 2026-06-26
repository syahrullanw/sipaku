<?php

namespace App\Controllers;

use App\Models\DigitalDocumentSignature;
use App\Models\SchoolProfile;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\GraduationCertificateService;
use App\Support\SchoolYearDocumentSettings;
use Core\Controller;
use Core\Request;
use Core\Response;

class PublicGraduationController extends Controller
{
    protected ?string $layout = 'app';

    public function index(Request $request): Response
    {
        $identifier = trim((string) $request->query('nisn', ''));
        $birthDate = trim((string) $request->query('tanggal_lahir', ''));
        $lookupAttempted = $identifier !== '' || $birthDate !== '';
        $lookupError = null;
        $evaluation = null;

        if ($lookupAttempted) {
            if ($identifier === '' || $birthDate === '') {
                $lookupError = 'Isi NISN/NIPD dan tanggal lahir terlebih dahulu.';
            } else {
                $normalizedBirthDate = $this->normalizeDate($birthDate);
                $student = $this->findStudentByIdentifier($identifier);

                if ($student === null || $normalizedBirthDate === null || (string) ($student['tanggal_lahir'] ?? '') !== $normalizedBirthDate) {
                    $lookupError = 'Data siswa tidak ditemukan. Periksa kembali NISN/NIPD dan tanggal lahir.';
                } else {
                    $studentId = (int) ($student['id'] ?? 0);
                    $targetYearId = (int) ($student['tahun_ajaran_id'] ?? 0);
                    $evaluation = GraduationCertificateService::evaluateStudentEligibility(
                        $studentId,
                        $targetYearId > 0 ? $targetYearId : null
                    );
                }
            }
        }

        return $this->render('public/graduation/index', [
            'title' => 'Cek Kelulusan',
            'schoolProfile' => SchoolProfile::first(),
            'identifier' => $identifier,
            'birthDate' => $birthDate,
            'lookupAttempted' => $lookupAttempted,
            'lookupError' => $lookupError,
            'evaluation' => $evaluation,
        ]);
    }

    public function print(Request $request, string $token): Response
    {
        $rawRecord = DigitalDocumentSignature::findByToken(trim($token));
        $record = null;

        if ($rawRecord !== null) {
            $record = DigitalDocumentSignature::findWithRelations((int) ($rawRecord['id'] ?? 0));
        }

        if ($record === null || ($record['document_type'] ?? '') !== GraduationCertificateService::DOCUMENT_TYPE) {
            return $this->render('reports/sections/error', [
                'title' => 'Cetak SKL',
                'message' => 'Dokumen SKL tidak ditemukan.',
            ], 'print');
        }

        $evaluation = GraduationCertificateService::evaluateStudentEligibility(
            (int) ($record['student_id'] ?? 0),
            (int) ($record['tahun_ajaran_id'] ?? 0),
            $record
        );

        if (!($evaluation['can_print'] ?? false)) {
            return $this->render('reports/sections/error', [
                'title' => 'Cetak SKL',
                'message' => $this->firstBlockingMessage($evaluation),
            ], 'print');
        }

        $payload = [];
        if (isset($record['payload']) && is_string($record['payload'])) {
            $decoded = json_decode($record['payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $livePayload = GraduationCertificateService::livePayloadForRecord($record);
        if (!empty($livePayload)) {
            $payload = $livePayload;
        }

        $schoolYear = SchoolYear::find((int) ($record['tahun_ajaran_id'] ?? 0));
        $schoolProfile = SchoolYearDocumentSettings::merge(SchoolProfile::first(), $schoolYear);
        $headmaster = null;
        $currentStudent = Student::findWithRelations((int) ($record['student_id'] ?? 0));

        if (is_array($schoolYear)) {
            $headmasterId = (int) ($schoolYear['kepala_sekolah_id'] ?? 0);
            if ($headmasterId > 0) {
                $headmaster = Teacher::find($headmasterId);
            }
        }

        $verificationUrl = null;
        $signatureToken = trim((string) ($record['signature_token'] ?? ''));
        if ($signatureToken !== '') {
            $verificationUrl = absolute_url('dokumen/validasi/' . $signatureToken);
        }

        $paperSize = strtolower((string) $request->query('paper', 'f4'));
        if (!in_array($paperSize, ['f4', 'a4'], true)) {
            $paperSize = 'f4';
        }

        return $this->render('graduation/certificates/print', [
            'title' => 'Cetak SKL',
            'record' => $record,
            'payload' => $payload,
            'currentStudent' => $currentStudent,
            'schoolYear' => $schoolYear,
            'schoolProfile' => $schoolProfile,
            'headmaster' => $headmaster,
            'verificationUrl' => $verificationUrl,
            'paperSize' => $paperSize,
        ], 'print');
    }

    private function findStudentByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $student = Student::findByNisn($identifier) ?? Student::findByNipd($identifier);

        if ($student === null) {
            return null;
        }

        return Student::findWithRelations((int) ($student['id'] ?? 0));
    }

    private function normalizeDate(string $date): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    /**
     * @param array<string, mixed> $evaluation
     */
    private function firstBlockingMessage(array $evaluation): string
    {
        foreach (($evaluation['context_issues'] ?? []) as $issue) {
            $issue = trim((string) $issue);
            if ($issue !== '') {
                return $issue;
            }
        }

        foreach (($evaluation['criteria'] ?? []) as $criterion) {
            if (!is_array($criterion) || (bool) ($criterion['passed'] ?? false)) {
                continue;
            }

            $message = trim((string) ($criterion['message'] ?? ''));
            if ($message !== '') {
                return $message;
            }
        }

        return 'SKL belum dapat dicetak karena syarat kelulusan belum terpenuhi.';
    }
}
