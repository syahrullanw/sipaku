<?php

namespace App\Controllers;

use App\Models\DigitalDocumentSignature;
use App\Models\SchoolProfile;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Models\User;
use App\Services\GraduationCertificateService;
use Core\Controller;
use Core\Request;
use Core\Response;

class DocumentVerificationController extends Controller
{
    protected ?string $layout = 'app';

    public function index(Request $request): Response
    {
        $queryToken = $request->query('token', '');
        $token = is_scalar($queryToken) ? trim((string) $queryToken) : '';

        if ($token !== '') {
            $target = 'dokumen/validasi/' . rawurlencode($token);

            if (strtolower((string) $request->query('format', '')) === 'json') {
                $target .= '?format=json';
            }

            return $this->redirect($target);
        }

        return $this->render('digital-signatures/verify', [
            'title' => 'Verifikasi Keaslian Dokumen',
            'document' => null,
            'payload' => [],
            'isApproved' => false,
            'schoolYear' => null,
            'schoolProfile' => SchoolProfile::first(),
            'headmaster' => null,
            'approver' => null,
            'token' => '',
        ]);
    }

    public function show(Request $request, string $token): Response
    {
        $rawRecord = DigitalDocumentSignature::findByToken($token);

        $record = null;

        if ($rawRecord !== null) {
            $record = DigitalDocumentSignature::findWithRelations((int) $rawRecord['id']);
        }

        $payload = [];

        if ($record !== null && isset($record['payload']) && is_string($record['payload'])) {
            $decoded = json_decode($record['payload'], true);

            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if ($record !== null && ($record['document_type'] ?? '') === GraduationCertificateService::DOCUMENT_TYPE) {
            $livePayload = GraduationCertificateService::livePayloadForRecord($record);
            if (!empty($livePayload)) {
                $payload = $livePayload;
            }
        }

        $status = $record['status'] ?? null;
        $isApproved = $status === 'approved';

        $schoolYear = null;
        $headmasterTeacher = null;
        $approver = null;

        if ($record !== null) {
            $schoolYear = SchoolYear::find((int) ($record['tahun_ajaran_id'] ?? 0));

            $headmasterId = 0;
            if (is_array($schoolYear) && isset($schoolYear['kepala_sekolah_id'])) {
                $headmasterId = (int) $schoolYear['kepala_sekolah_id'];
            }

            if ($headmasterId > 0) {
                $headmasterTeacher = Teacher::find($headmasterId);
            }

            $approverId = isset($record['approved_by']) ? (int) $record['approved_by'] : 0;
            if ($approverId > 0) {
                $approver = User::find($approverId);
            }
        }

        if (strtolower((string) $request->query('format', '')) === 'json') {
            $responseData = [
                'status' => $record['status'] ?? 'not_found',
                'message' => $record === null ? 'Token tidak ditemukan atau belum disetujui.' : ($isApproved ? 'Dokumen terverifikasi.' : 'Dokumen belum disetujui.'),
                'document' => [
                    'id' => $record['id'] ?? null,
                    'title' => $record['document_title'] ?? null,
                    'type' => $record['document_type'] ?? null,
                    'token' => $record['signature_token'] ?? $token,
                    'approved_at' => $record['approved_at'] ?? null,
                    'approval_note' => $record['approval_note'] ?? null,
                ],
                'student' => $payload['student'] ?? null,
                'class' => $payload['class'] ?? null,
                'semester' => $payload['semester'] ?? null,
                'subjects' => $payload['subjects'] ?? [],
                'average' => $payload['average'] ?? null,
            ];

            return $this->json($responseData, $record === null ? 404 : 200);
        }

        $schoolProfile = SchoolProfile::first();

        return $this->render('digital-signatures/verify', [
            'title' => 'Verifikasi Keaslian Dokumen',
            'document' => $record,
            'payload' => $payload,
            'isApproved' => $isApproved,
            'schoolYear' => $schoolYear,
            'schoolProfile' => $schoolProfile,
            'headmaster' => $headmasterTeacher,
            'approver' => $approver,
            'token' => $token,
        ]);
    }
}
