<?php

namespace App\Controllers;

use App\Models\DigitalDocumentSignature;
use App\Models\SchoolProfile;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Models\User;
use App\Support\DigitalDocumentTypes;
use Core\Controller;
use Core\Request;
use Core\Response;

class LetterVerificationController extends Controller
{
    protected ?string $layout = 'app';

    public function index(Request $request): Response
    {
        $queryToken = $request->query('token', '');
        $token = is_scalar($queryToken) ? trim((string) $queryToken) : '';

        if ($token !== '') {
            $target = 'persuratan/validasi/' . rawurlencode($token);

            if (strtolower((string) $request->query('format', '')) === 'json') {
                $target .= '?format=json';
            }

            return $this->redirect($target);
        }

        return $this->render('digital-signatures/verify-letter', [
            'title' => 'Verifikasi Surat',
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
            $candidate = DigitalDocumentSignature::findWithRelations((int) $rawRecord['id']);

            if ($candidate !== null && DigitalDocumentTypes::isLetter((string) ($candidate['document_type'] ?? ''))) {
                $record = $candidate;
            }
        }

        $payload = [];

        if ($record !== null && isset($record['payload']) && is_string($record['payload'])) {
            $decoded = json_decode($record['payload'], true);

            if (is_array($decoded)) {
                $payload = $decoded;
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
                'message' => $record === null ? 'Token tidak ditemukan atau bukan dokumen persuratan.' : ($isApproved ? 'Dokumen persuratan terverifikasi.' : 'Dokumen menunggu persetujuan.'),
                'document' => [
                    'id' => $record['id'] ?? null,
                    'title' => $record['document_title'] ?? null,
                    'type' => $record['document_type'] ?? null,
                    'token' => $record['signature_token'] ?? $token,
                    'approved_at' => $record['approved_at'] ?? null,
                    'approval_note' => $record['approval_note'] ?? null,
                ],
                'letter' => $payload['letter'] ?? null,
                'metadata' => [
                    'headmaster' => $payload['headmaster'] ?? null,
                    'school_profile' => $payload['school_profile'] ?? null,
                ],
            ];

            return $this->json($responseData, $record === null ? 404 : 200);
        }

        $schoolProfile = SchoolProfile::first();

        return $this->render('digital-signatures/verify-letter', [
            'title' => 'Verifikasi Surat',
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
