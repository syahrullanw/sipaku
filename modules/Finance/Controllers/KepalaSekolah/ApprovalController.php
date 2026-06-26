<?php

namespace Modules\Finance\Controllers\KepalaSekolah;

use App\Models\ActivityFund;
use App\Models\Teacher;
use App\Models\TeacherLoan;
use App\Services\Finance\ApprovalService;
use App\Services\WhatsappGatewayService;
use App\Support\FinanceCache;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class ApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardHeadmaster()) {
            return $response;
        }

        $user = $this->user();
        $approverId = $user !== null ? (int) ($user['id'] ?? 0) : 0;

        $connection = Database::connection();
        $statement = $connection->prepare(
            'SELECT * FROM keuangan_approval
             WHERE approver_id = :approver AND status = :status
             ORDER BY tanggal ASC'
        );

        if ($statement === false) {
            Session::flash('error', 'Gagal memuat daftar approval.');

            return $this->redirect('dashboard');
        }

        $statement->bindValue(':approver', $approverId, \PDO::PARAM_INT);
        $statement->bindValue(':status', 'menunggu');
        $statement->execute();

        $approvals = $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $details = [];

        foreach ($approvals as $approval) {
            $entityType = (string) ($approval['entity_type'] ?? '');
            $entityId = (int) ($approval['entity_id'] ?? 0);

            $details[] = [
                'approval' => $approval,
                'entity' => $this->loadEntityDetail($entityType, $entityId),
            ];
        }

        return $this->render('finance/kepsek/approvals/index', [
            'title' => 'Persetujuan Keuangan',
            'pageTitle' => 'Antrian Persetujuan',
            'activeMenu' => 'finance-kepsek-approvals',
            'items' => $details,
        ], 'admin');
    }

    public function approve(Request $request, int $id): Response
    {
        return $this->handleResolution($request, $id, 'disetujui');
    }

    public function reject(Request $request, int $id): Response
    {
        return $this->handleResolution($request, $id, 'ditolak');
    }

    protected function handleResolution(Request $request, int $approvalId, string $status): Response
    {
        if ($response = $this->guardHeadmaster()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/kepala-sekolah/approval')) {
            return $response;
        }

        $user = $this->user();
        $approverId = $user !== null ? (int) ($user['id'] ?? 0) : 0;

        $connection = Database::connection();
        $approvalStatement = $connection->prepare(
            'SELECT * FROM keuangan_approval WHERE id = :id AND approver_id = :approver LIMIT 1'
        );

        if ($approvalStatement === false) {
            Session::flash('error', 'Gagal memuat data persetujuan.');

            return $this->redirect('keuangan/kepala-sekolah/approval');
        }

        $approvalStatement->bindValue(':id', $approvalId, \PDO::PARAM_INT);
        $approvalStatement->bindValue(':approver', $approverId, \PDO::PARAM_INT);
        $approvalStatement->execute();
        $approval = $approvalStatement->fetch(\PDO::FETCH_ASSOC);

        if ($approval === false) {
            Session::flash('error', 'Data persetujuan tidak ditemukan atau sudah diproses.');

            return $this->redirect('keuangan/kepala-sekolah/approval');
        }

        if (($approval['status'] ?? '') !== 'menunggu') {
            Session::flash('info', 'Permohonan sudah diproses sebelumnya.');

            return $this->redirect('keuangan/kepala-sekolah/approval');
        }

        $note = trim((string) $request->input('catatan', ''));
        $entityType = (string) ($approval['entity_type'] ?? '');
        $entityId = (int) ($approval['entity_id'] ?? 0);

        try {
            $connection->beginTransaction();

            $this->syncEntityStatus($entityType, $entityId, $status, $note, $approverId);
            ApprovalService::resolve($approvalId, $status, $note === '' ? null : $note);

            $connection->commit();

            \Core\Log::channel('finance')->info('Approval keuangan diselesaikan', [
                'approval_id' => $approvalId,
                'status' => $status,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'user_id' => $approverId,
            ]);

            if ($status === 'disetujui') {
                $this->notifyBendaharaOnApproval($entityType, $entityId);
            }

            $activeYearId = $this->activeSchoolYearId() ?? 0;
            FinanceCache::forget('kepsek_dashboard_summary_' . date('Y_m'));
            FinanceCache::forget('kepsek_dashboard_revenue_' . date('Y_m'));
            FinanceCache::forget('kepsek_dashboard_loan_' . $activeYearId);
            FinanceCache::forget('bendahara_dashboard_stats_' . $activeYearId);

            Session::flash('success', 'Keputusan berhasil disimpan.');
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            Session::flash('error', 'Gagal memproses persetujuan: ' . $exception->getMessage());
        }

        return $this->redirect('keuangan/kepala-sekolah/approval');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function loadEntityDetail(string $entityType, int $entityId): ?array
    {
        if ($entityId <= 0) {
            return null;
        }

        $connection = Database::connection();

        switch ($entityType) {
            case 'kasbon':
                $statement = $connection->prepare(
                    'SELECT kg.*, g.nama AS guru_nama
                     FROM kasbon_guru kg
                     LEFT JOIN guru g ON g.id = kg.guru_id
                     WHERE kg.id = :id
                     LIMIT 1'
                );
                break;
            case 'dana_kegiatan':
                $statement = $connection->prepare(
                    'SELECT dk.*, g.nama AS guru_nama
                     FROM dana_kegiatan dk
                     LEFT JOIN guru g ON g.id = dk.guru_id
                     WHERE dk.id = :id
                     LIMIT 1'
                );
                break;
            case 'honor':
                $statement = $connection->prepare(
                    'SELECT hg.*, g.nama AS guru_nama
                     FROM honor_guru hg
                     LEFT JOIN guru g ON g.id = hg.guru_id
                     WHERE hg.id = :id
                     LIMIT 1'
                );
                break;
            case 'tagihan':
                $statement = $connection->prepare('SELECT * FROM tagihan WHERE id = :id LIMIT 1');
                break;
            case 'pembayaran':
                $statement = $connection->prepare('SELECT * FROM pembayaran WHERE id = :id LIMIT 1');
                break;
            default:
                $statement = null;
                break;
        }

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $entityId, \PDO::PARAM_INT);
        $statement->execute();

        $record = $statement->fetch(\PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    protected function syncEntityStatus(string $entityType, int $entityId, string $status, string $note, int $approverId): void
    {
        $now = date('Y-m-d H:i:s');
        $connection = Database::connection();

        if ($entityType === 'dana_kegiatan') {
            $activity = ActivityFund::find($entityId);

            if ($activity === null) {
                throw new \RuntimeException('Pengajuan dana kegiatan tidak ditemukan.');
            }

            $message = $this->buildActivityDecisionMessage($status, $now, $note);
            $updatedNote = $message !== null
                ? $this->appendTimelineNote($activity['catatan'] ?? null, $message)
                : ($activity['catatan'] ?? null);

            ActivityFund::updateById($entityId, [
                'status' => $status,
                'tanggal_acc' => $now,
                'catatan' => $updatedNote,
                'approved_by' => $approverId,
                'updated_at' => $now,
            ]);

            return;
        }

        switch ($entityType) {
            case 'kasbon':
                $statement = $connection->prepare(
                    "UPDATE kasbon_guru
                     SET status = :status,
                         tanggal_acc = :tanggal_acc,
                         catatan_penolakan = :note,
                         approved_by = :approver,
                         updated_at = :updated_at
                     WHERE id = :id
                     LIMIT 1"
                );
                break;
            case 'honor':
                $statement = $connection->prepare(
                    "UPDATE honor_guru
                     SET status = :status,
                         tanggal_acc = :tanggal_acc,
                         catatan = CASE WHEN :status = 'ditolak' THEN :note ELSE catatan END,
                         approved_by = :approver,
                         updated_at = :updated_at
                     WHERE id = :id
                     LIMIT 1"
                );
                break;
            default:
                $statement = null;
                break;
        }

        if ($statement === null) {
            return;
        }

        $statusToApply = $status;
        if ($entityType === 'kasbon' && $status === 'disetujui') {
            $statusToApply = 'disetujui';
        }

        $statement->bindValue(':status', $statusToApply);
        $statement->bindValue(':tanggal_acc', $now);
        $statement->bindValue(':note', $note === '' ? null : $note);
        $statement->bindValue(':approver', $approverId, \PDO::PARAM_INT);
        $statement->bindValue(':updated_at', $now);
        $statement->bindValue(':id', $entityId, \PDO::PARAM_INT);

        if (!$statement->execute()) {
            throw new \RuntimeException('Gagal memperbarui status entitas.');
        }
    }

    private function notifyBendaharaOnApproval(string $entityType, int $entityId): void
    {
        $contacts = $this->resolveBendaharaContacts();

        if (empty($contacts)) {
            \Core\Log::channel('finance')->info('Kontak bendahara tidak ditemukan, notifikasi approval tidak dikirim.', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);

            return;
        }

        switch ($entityType) {
            case 'kasbon':
                $loan = TeacherLoan::find($entityId);
                if ($loan === null) {
                    \Core\Log::channel('finance')->warning('Data kasbon tidak ditemukan saat menyiapkan notifikasi untuk bendahara.', [
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                    ]);

                    return;
                }

                $teacherId = (int) ($loan['guru_id'] ?? 0);
                $teacher = $teacherId > 0 ? Teacher::find($teacherId) : null;
                $teacherName = trim((string) ($teacher['nama'] ?? ''));
                if ($teacherName === '') {
                    $teacherName = 'Guru terkait';
                }

                $amount = $this->formatCurrency((float) ($loan['nominal_diminta'] ?? 0));
                $code = trim((string) ($loan['kode'] ?? ''));
                $codeDisplay = $code !== '' ? $code : 'Kasbon #' . $entityId;
                $bodyLines = [
                    "Pengajuan kasbon {$codeDisplay} a.n. {$teacherName} sebesar {$amount} telah disetujui kepala sekolah.",
                    'Mohon proses pencairan melalui portal bendahara: ' . absolute_url('keuangan/bendahara/kasbon') . '.',
                ];
                break;
            case 'dana_kegiatan':
                $activity = ActivityFund::find($entityId);
                if ($activity === null) {
                    \Core\Log::channel('finance')->warning('Data dana kegiatan tidak ditemukan saat menyiapkan notifikasi untuk bendahara.', [
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                    ]);

                    return;
                }

                $teacherId = (int) ($activity['guru_id'] ?? 0);
                $teacher = $teacherId > 0 ? Teacher::find($teacherId) : null;
                $teacherName = trim((string) ($teacher['nama'] ?? ''));
                if ($teacherName === '') {
                    $teacherName = 'Guru terkait';
                }

                $title = trim((string) ($activity['judul'] ?? ''));
                $titleSegment = $title !== '' ? '"' . $title . '"' : 'dana kegiatan';
                $estimate = $this->formatCurrency((float) ($activity['estimasi_biaya'] ?? 0));
                $code = trim((string) ($activity['kode'] ?? ''));
                $codeDisplay = $code !== '' ? $code : 'ACT #' . $entityId;

                $bodyLines = [
                    "Pengajuan dana kegiatan {$titleSegment} ({$codeDisplay}) a.n. {$teacherName} dengan estimasi {$estimate} telah disetujui kepala sekolah.",
                    'Mohon siapkan pencairan melalui portal bendahara: ' . absolute_url('keuangan/bendahara/dana-kegiatan') . '.',
                ];
                break;
            default:
                return;
        }

        foreach ($contacts as $contact) {
            $recipientName = trim($contact['name'] ?? '');
            if ($recipientName === '') {
                $recipientName = 'Bendahara';
            }

            $phone = trim((string) ($contact['phone'] ?? ''));
            if ($phone === '') {
                continue;
            }

            $messageLines = [
                "Assalamu'alaikum Bapak/Ibu {$recipientName},",
                '',
            ];

            foreach ($bodyLines as $line) {
                $messageLines[] = $line;
            }

            $messageLines[] = '';
            $messageLines[] = 'Terima kasih.';

            $template = implode("\n", $messageLines);

            try {
                $result = WhatsappGatewayService::sendDetailed([
                    'phone' => $phone,
                    'template' => $template,
                ]);

                $context = [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'phone' => $phone,
                    'payload' => $result['payload'],
                ];

                if ($result['success']) {
                    if ($result['queued']) {
                        $context['queued'] = true;
                        $context['duplicate'] = $result['duplicate'];
                        \Core\Log::channel('finance')->info('Notifikasi approval dimasukkan ke antrian WhatsApp.', $context);
                    } else {
                        \Core\Log::channel('finance')->info('Notifikasi approval dikirim ke bendahara.', $context);
                    }
                } else {
                    $context['error'] = $result['error'];
                    $context['status'] = $result['status'];
                    $context['queued'] = $result['queued'];
                    $context['duplicate'] = $result['duplicate'];
                    \Core\Log::channel('finance')->warning('Gagal mengirim notifikasi approval ke bendahara.', $context);
                }
            } catch (\Throwable $exception) {
                \Core\Log::channel('finance')->error('Kesalahan saat mengirim notifikasi approval ke bendahara.', [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'phone' => $phone,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<int, array{name: string, phone: string}>
     */
    private function resolveBendaharaContacts(): array
    {
        $connection = Database::connection();
        $contacts = [];
        $uniquePhones = [];
        $activeYearId = $this->activeSchoolYearId();

        $sql = <<<SQL
SELECT g.nama AS nama, g.telepon AS telepon
FROM guru_jabatan_akademik gja
JOIN jabatan_akademik ja ON ja.id = gja.jabatan_akademik_id
JOIN guru g ON g.id = gja.guru_id
WHERE ja.assigns_user_role = :role
SQL;

        if ($activeYearId !== null && $activeYearId > 0) {
            $sql .= ' AND gja.tahun_ajaran_id = :year';
        }

        $statement = $connection->prepare($sql);

        if ($statement !== false) {
            $statement->bindValue(':role', 'bendahara');
            if ($activeYearId !== null && $activeYearId > 0) {
                $statement->bindValue(':year', $activeYearId, \PDO::PARAM_INT);
            }

            if ($statement->execute()) {
                $rows = $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                foreach ($rows as $row) {
                    $phone = trim((string) ($row['telepon'] ?? ''));
                    if ($phone === '' || isset($uniquePhones[$phone])) {
                        continue;
                    }

                    $uniquePhones[$phone] = true;
                    $name = trim((string) ($row['nama'] ?? ''));
                    $contacts[] = [
                        'name' => $name !== '' ? $name : 'Bendahara',
                        'phone' => $phone,
                    ];
                }
            }
        }

        if (!empty($contacts)) {
            return $contacts;
        }

        $fallback = $connection->prepare(
            'SELECT g.nama AS nama, g.telepon AS telepon
             FROM users u
             JOIN guru g ON g.id = u.teacher_id
             WHERE u.role = :role'
        );

        if ($fallback === false) {
            return [];
        }

        $fallback->bindValue(':role', 'bendahara');

        if (!$fallback->execute()) {
            return [];
        }

        $rows = $fallback->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $phone = trim((string) ($row['telepon'] ?? ''));
            if ($phone === '' || isset($uniquePhones[$phone])) {
                continue;
            }

            $uniquePhones[$phone] = true;
            $name = trim((string) ($row['nama'] ?? ''));
            $contacts[] = [
                'name' => $name !== '' ? $name : 'Bendahara',
                'phone' => $phone,
            ];
        }

        return $contacts;
    }

    private function appendTimelineNote(?string $existing, string $message): string
    {
        $trimmed = trim((string) $existing);

        if ($trimmed === '') {
            return $message;
        }

        return $trimmed . "\n\n" . $message;
    }

    private function buildActivityDecisionMessage(string $status, string $timestamp, string $note): ?string
    {
        $formattedTime = date('d M Y H:i', strtotime($timestamp));

        return match ($status) {
            'disetujui' => $note !== ''
                ? "Disetujui kepala sekolah pada {$formattedTime}.\n{$note}"
                : "Disetujui kepala sekolah pada {$formattedTime}.",
            'ditolak' => $note !== ''
                ? "Pengajuan ditolak kepala sekolah pada {$formattedTime}.\n{$note}"
                : "Pengajuan ditolak kepala sekolah pada {$formattedTime}.",
            default => null,
        };
    }
}
