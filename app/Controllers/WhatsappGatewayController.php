<?php

namespace App\Controllers;

use App\Models\WhatsappGatewaySetting;
use App\Models\WhatsappMessageQueue;
use App\Services\WhatsappGatewayService;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class WhatsappGatewayController extends Controller
{
    protected ?string $layout = 'admin';

    /**
     * @var array<string, string>
     */
    private array $templateOptions = [
        'default' => 'Default',
        'custom' => 'Custom',
        'fonnte' => 'Fonnte',
        'waha' => 'WAHA',
    ];

    /**
     * @var array<string, string>
     */
    private array $bodyTypeOptions = [
        'json' => 'JSON',
        'form-data' => 'Form Data',
        'x-www-form-urlencoded' => 'x-www-form-urlencoded',
    ];

    /**
     * @var array<int, string>
     */
    private array $intervalOptions = [
        15 => '15 Detik',
        30 => '30 Detik',
        60 => '1 Menit',
        120 => '2 Menit',
        300 => '5 Menit',
        600 => '10 Menit',
    ];

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $settings = WhatsappGatewaySetting::first();
        $queueItems = WhatsappMessageQueue::latest(25);
        $queueSummary = [
            'pending' => WhatsappMessageQueue::countByStatus('pending'),
            'processing' => WhatsappMessageQueue::countByStatus('processing'),
            'sent' => WhatsappMessageQueue::countByStatus('sent'),
            'failed' => WhatsappMessageQueue::countByStatus('failed'),
        ];
        $cronCommand = 'php public/index.php whatsapp:dispatch';

        return $this->render('admin/integrations/whatsapp', [
            'title' => 'Integrasi WhatsApp Gateway',
            'pageTitle' => 'Integrasi WhatsApp Gateway',
            'activeMenu' => 'whatsapp-gateway',
            'settings' => $settings,
            'templateOptions' => $this->templateOptions,
            'bodyTypeOptions' => $this->bodyTypeOptions,
            'intervalOptions' => $this->intervalOptions,
            'defaultTestMessage' => 'Halo {{nama_siswa}}, ini pesan percobaan dari WhatsApp Gateway ' . config('app.name') . '.',
            'queueItems' => $queueItems,
            'queueSummary' => $queueSummary,
            'cronCommand' => $cronCommand,
        ], 'admin');
    }

    public function update(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/integrasi/whatsapp')) {
            return $response;
        }

        $payload = $this->validate($request);

        if ($payload === null) {
            return $this->redirect('admin/integrasi/whatsapp');
        }

        $existing = WhatsappGatewaySetting::first();
        $timestamp = date('Y-m-d H:i:s');
        $payload['updated_at'] = $timestamp;

        try {
            if ($existing === null) {
                $payload['created_at'] = $timestamp;
                WhatsappGatewaySetting::create($payload);
            } else {
                WhatsappGatewaySetting::updateById($existing['id'], $payload);
            }

            Session::flash('success', 'Pengaturan WhatsApp Gateway berhasil disimpan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan pengaturan WhatsApp Gateway: ' . $exception->getMessage());

            return $this->redirect('admin/integrasi/whatsapp');
        }

        return $this->redirect('admin/integrasi/whatsapp');
    }

    public function test(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/integrasi/whatsapp')) {
            return $response;
        }

        $settings = WhatsappGatewaySetting::first();
        if ($settings === null) {
            Session::flash('error', 'Pengaturan WhatsApp Gateway belum disimpan. Simpan pengaturan terlebih dahulu sebelum melakukan pengujian.');

            return $this->redirect('admin/integrasi/whatsapp');
        }

        $phoneRaw = trim((string) $request->input('test_phone', ''));
        $messageTemplate = trim((string) $request->input('test_message', ''));

        if ($phoneRaw === '') {
            Session::flash('error', 'Masukkan nomor WhatsApp tujuan untuk pengujian.');
            Session::flashInput($request->all());

            return $this->redirect('admin/integrasi/whatsapp');
        }

        $normalizedPhone = WhatsappGatewayService::normalizePhone($phoneRaw);
        if ($normalizedPhone === '') {
            Session::flash('error', 'Nomor WhatsApp tidak valid. Pastikan hanya berisi angka dan awali dengan 0 atau kode negara.');
            Session::flashInput($request->all());

            return $this->redirect('admin/integrasi/whatsapp');
        }

        if ($messageTemplate === '') {
            $messageTemplate = $settings['default_message_value'] ?? 'Halo {{nama_siswa}}, ini pesan percobaan dari WhatsApp Gateway ' . config('app.name') . '.';
        }

        try {
            $testCode = 'TEST-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        } catch (\Throwable $exception) {
            $testCode = 'TEST-' . mt_rand(100000, 999999);
        }

        $variables = [
            'nama_siswa' => 'Tes Gateway',
            'judul_tagihan' => 'Tagihan Percobaan',
            'nominal_bayar' => 'Rp 0',
            'nominal_bayar_angka' => '0.00',
            'sisa_tagihan' => 'Rp 0',
            'sisa_tagihan_angka' => '0.00',
            'tanggal_pembayaran' => date('d M Y H:i'),
            'kode_pembayaran' => $testCode,
            'metode_pembayaran' => 'Tes',
            'catatan_pembayaran' => 'Pesan ini dikirim untuk memastikan gateway berfungsi.',
            'nama_sekolah' => config('app.name'),
        ];

        $result = WhatsappGatewayService::sendDetailed([
            'phone' => $normalizedPhone,
            'template' => $messageTemplate,
            'variables' => $variables,
            'send_immediately' => true,
        ], $settings);

        if (!$result['success']) {
            $statusInfo = $result['status'] !== null ? ' (HTTP ' . $result['status'] . ')' : '';
            $errorDetail = $result['error'] ?? 'Gagal mengirim pesan uji.';
            Session::flash('error', $errorDetail . $statusInfo);
            Session::flashInput($request->all());

            return $this->redirect('admin/integrasi/whatsapp');
        }

        $statusText = $result['status'] !== null ? ' (HTTP ' . $result['status'] . ')' : '';
        $responseSnippet = '';
        if (!empty($result['response'])) {
            $preview = trim($result['response']);
            if (mb_strlen($preview) > 200) {
                $preview = mb_substr($preview, 0, 200) . '…';
            }
            $responseSnippet = ' Respons: ' . $preview;
        }

        Session::flash('success', 'Pesan uji berhasil dikirim ke ' . $normalizedPhone . $statusText . '.' . $responseSnippet);

        return $this->redirect('admin/integrasi/whatsapp');
    }

    private function validate(Request $request): ?array
    {
        $template = strtolower(trim((string) $request->input('template', '')));
        $baseUrl = trim((string) $request->input('base_url', ''));
        $authorization = trim((string) $request->input('authorization', ''));
        $bodyType = strtolower(trim((string) $request->input('body_type', '')));
        $defaultParameterKey = trim((string) $request->input('default_parameter_key', ''));
        $defaultParameterValue = trim((string) $request->input('default_parameter_value', ''));
        $defaultMessageKey = trim((string) $request->input('default_message_key', ''));
        $defaultMessageValue = trim((string) $request->input('default_message_value', ''));
        $extraKeyOne = trim((string) $request->input('extra_parameter_one_key', ''));
        $extraValueOne = trim((string) $request->input('extra_parameter_one_value', ''));
        $extraKeyTwo = trim((string) $request->input('extra_parameter_two_key', ''));
        $extraValueTwo = trim((string) $request->input('extra_parameter_two_value', ''));
        $sendIntervalRaw = trim((string) $request->input('send_interval_seconds', ''));
        $qrScanUrl = trim((string) $request->input('qr_scan_url', ''));

        if (!array_key_exists($template, $this->templateOptions)) {
            return $this->failValidation($request, 'Template tidak valid.');
        }

        if ($baseUrl === '') {
            return $this->failValidation($request, 'Base URL wajib diisi.');
        }

        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return $this->failValidation($request, 'Base URL tidak valid.');
        }

        if (!array_key_exists($bodyType, $this->bodyTypeOptions)) {
            return $this->failValidation($request, 'Body type tidak valid.');
        }

        if ($template === 'waha') {
            $baseUrl = $this->normalizeWahaSendTextUrl($baseUrl);
            $bodyType = 'json';
            $defaultParameterKey = $defaultParameterKey !== '' ? $defaultParameterKey : 'chatId';
            $defaultParameterValue = $defaultParameterValue !== '' ? $defaultParameterValue : '{{waha_chat_id}}';
            $defaultMessageKey = $defaultMessageKey !== '' ? $defaultMessageKey : 'text';
            $extraKeyOne = $extraKeyOne !== '' ? $extraKeyOne : 'session';
            $extraValueOne = $extraValueOne !== '' ? $extraValueOne : 'default';
        }

        if ($defaultParameterKey === '' || $defaultParameterValue === '') {
            return $this->failValidation($request, 'Parameter default tujuan wajib diisi.');
        }

        if ($defaultMessageKey === '' || $defaultMessageValue === '') {
            return $this->failValidation($request, 'Parameter default pesan wajib diisi.');
        }

        $sendInterval = (int) $sendIntervalRaw;

        if (!array_key_exists($sendInterval, $this->intervalOptions)) {
            return $this->failValidation($request, 'Interval pengiriman tidak valid.');
        }

        if ($qrScanUrl !== '' && !filter_var($qrScanUrl, FILTER_VALIDATE_URL)) {
            return $this->failValidation($request, 'URL scan QR Code tidak valid.');
        }

        return [
            'template' => $template,
            'base_url' => $baseUrl,
            'authorization_token' => $authorization !== '' ? $authorization : null,
            'body_type' => $bodyType,
            'default_parameter_key' => $defaultParameterKey,
            'default_parameter_value' => $defaultParameterValue,
            'default_message_key' => $defaultMessageKey,
            'default_message_value' => $defaultMessageValue,
            'extra_parameter_one_key' => $extraKeyOne !== '' ? $extraKeyOne : null,
            'extra_parameter_one_value' => $extraValueOne !== '' ? $extraValueOne : null,
            'extra_parameter_two_key' => $extraKeyTwo !== '' ? $extraKeyTwo : null,
            'extra_parameter_two_value' => $extraValueTwo !== '' ? $extraValueTwo : null,
            'send_interval_seconds' => $sendInterval,
            'qr_scan_url' => $qrScanUrl !== '' ? $qrScanUrl : null,
        ];
    }

    private function failValidation(Request $request, string $message): ?array
    {
        Session::flash('error', $message);
        Session::flashInput($request->all());

        return null;
    }

    private function normalizeWahaSendTextUrl(string $baseUrl): string
    {
        $normalized = rtrim($baseUrl, '/');

        if (preg_match('#/api/sendText$#i', $normalized) === 1) {
            return $normalized;
        }

        return $normalized . '/api/sendText';
    }
}
