<?php
    $settingsData = isset($settings) && is_array($settings) ? $settings : null;
    $templateOptionsData = isset($templateOptions) && is_array($templateOptions) ? $templateOptions : [];
    $bodyTypeOptionsData = isset($bodyTypeOptions) && is_array($bodyTypeOptions) ? $bodyTypeOptions : [];
    $intervalOptionsData = isset($intervalOptions) && is_array($intervalOptions) ? $intervalOptions : [];
    $successMessage = session_flash('success');
    $errorMessage = session_flash('error');
    $queueItemsData = isset($queueItems) && is_array($queueItems) ? $queueItems : [];
    $queueSummaryData = isset($queueSummary) && is_array($queueSummary) ? $queueSummary : [];
    $cronCommandValue = isset($cronCommand) && is_string($cronCommand) ? $cronCommand : 'php public/index.php whatsapp:dispatch';

    $templateValue = strtolower((string) old('template', $settingsData['template'] ?? 'custom'));
    $bodyTypeValue = strtolower((string) old('body_type', $settingsData['body_type'] ?? 'json'));
    $sendIntervalValue = (int) old('send_interval_seconds', $settingsData['send_interval_seconds'] ?? 30);
    $defaultTestMessageData = isset($defaultTestMessage) && is_string($defaultTestMessage)
        ? $defaultTestMessage
        : 'Halo {{nama_siswa}}, ini pesan percobaan dari WhatsApp Gateway ' . config('app.name') . '.';
    $testPhoneValue = (string) old('test_phone', '');
    $testMessageValue = (string) old('test_message', $defaultTestMessageData);
?>

<div class="space-y-6">
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Integrasi</p>
            <h2 class="mt-1 text-xl font-semibold text-slate-800">Pengaturan WhatsApp Gateway</h2>
            <p class="mt-1 text-sm text-slate-500">
                Tentukan endpoint API, parameter default, serta interval penjadwalan untuk otomatisasi pengiriman pesan.
                Simpan perubahan untuk mulai menghubungkan bot WhatsApp Anda.
            </p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-500 shadow-sm">
            <p class="font-semibold text-slate-700">Terakhir diperbarui</p>
            <p>
                <?php if (!empty($settingsData['updated_at'])): ?>
                    <?= htmlspecialchars(date('d M Y H:i', strtotime((string) $settingsData['updated_at'])), ENT_QUOTES, 'UTF-8') ?>
                <?php else: ?>
                    Belum pernah disimpan
                <?php endif; ?>
            </p>
        </div>
    </header>

    <?php if (!empty($successMessage)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-700 shadow-sm">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <p>
                Pengiriman WhatsApp otomatis sekarang menggunakan antrian. Jalankan cron job berkala di server agar pesan terkirim sesuai interval yang ditentukan.
            </p>
            <code class="rounded bg-white/80 px-3 py-1 text-xs font-semibold text-amber-800 shadow-inner">
                <?= htmlspecialchars($cronCommandValue, ENT_QUOTES, 'UTF-8') ?>
            </code>
        </div>
        <p class="mt-2 text-xs text-amber-700/80">
            Rekomendasi: jadwalkan per 1 menit. Tambahkan parameter angka (mis. <code><?= htmlspecialchars($cronCommandValue . ' 20', ENT_QUOTES, 'UTF-8') ?></code>) untuk membatasi jumlah pesan per proses cron.
        </p>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <form action="<?= htmlspecialchars(base_url('admin/integrasi/whatsapp'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-6">
            <?= csrf_field() ?>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="template" class="block text-sm font-medium text-slate-600">Template</label>
                    <select
                        id="template"
                        name="template"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    >
                        <?php foreach ($templateOptionsData as $value => $label): ?>
                            <?php $optionValue = strtolower((string) $value); ?>
                            <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= $templateValue === $optionValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="authorization" class="block text-sm font-medium text-slate-600">API Key / Authorization</label>
                    <input
                        type="text"
                        id="authorization"
                        name="authorization"
                        value="<?= htmlspecialchars((string) old('authorization', $settingsData['authorization_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="WAHA: isi API key untuk header X-Api-Key"
                    />
                    <p class="mt-1 text-xs text-slate-500">Untuk WAHA, nilai ini dikirim sebagai header <code>X-Api-Key</code>. Template lain tetap memakai header Authorization.</p>
                </div>
            </div>

            <div id="waha-hint" class="<?= $templateValue === 'waha' ? '' : 'hidden' ?> rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                Gunakan endpoint WAHA <code>/api/sendText</code>. Jika hanya mengisi domain WAHA, sistem otomatis menambahkan path tersebut saat disimpan.
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="base_url" class="block text-sm font-medium text-slate-600">Base URL</label>
                    <textarea
                        id="base_url"
                        name="base_url"
                        rows="2"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="WAHA: https://waha.example.com/api/sendText"><?= htmlspecialchars((string) old('base_url', $settingsData['base_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label for="body_type" class="block text-sm font-medium text-slate-600">Body Type</label>
                    <select
                        id="body_type"
                        name="body_type"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    >
                        <?php foreach ($bodyTypeOptionsData as $value => $label): ?>
                            <?php $optionValue = strtolower((string) $value); ?>
                            <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= $bodyTypeValue === $optionValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="default_parameter_key" class="block text-sm font-medium text-slate-600">Parameter Default</label>
                    <input
                        type="text"
                        id="default_parameter_key"
                        name="default_parameter_key"
                        value="<?= htmlspecialchars((string) old('default_parameter_key', $settingsData['default_parameter_key'] ?? 'target'), ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm uppercase tracking-wide focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="WAHA: chatId"
                        required
                    />
                </div>
                <div>
                    <label for="default_parameter_value" class="block text-sm font-medium text-slate-600">Nilai Default</label>
                    <input
                        type="text"
                        id="default_parameter_value"
                        name="default_parameter_value"
                        value="<?= htmlspecialchars((string) old('default_parameter_value', $settingsData['default_parameter_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="WAHA: {{waha_chat_id}}"
                        required
                    />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="default_message_key" class="block text-sm font-medium text-slate-600">Parameter Pesan</label>
                    <input
                        type="text"
                        id="default_message_key"
                        name="default_message_key"
                        value="<?= htmlspecialchars((string) old('default_message_key', $settingsData['default_message_key'] ?? 'message'), ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm uppercase tracking-wide focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="WAHA: text"
                        required
                    />
                </div>
                <div>
                    <label for="default_message_value" class="block text-sm font-medium text-slate-600">Nilai Pesan</label>
                    <textarea
                        id="default_message_value"
                        name="default_message_value"
                        rows="3"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="Contoh pesan yang akan dikirim"><?= htmlspecialchars((string) old('default_message_value', $settingsData['default_message_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="extra_parameter_one_key" class="block text-sm font-medium text-slate-600">Parameter Lain 1</label>
                    <input
                        type="text"
                        id="extra_parameter_one_key"
                        name="extra_parameter_one_key"
                        value="<?= htmlspecialchars((string) old('extra_parameter_one_key', $settingsData['extra_parameter_one_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="WAHA: session"
                    />
                </div>
                <div>
                    <label for="extra_parameter_one_value" class="block text-sm font-medium text-slate-600">Nilai 1</label>
                    <input
                        type="text"
                        id="extra_parameter_one_value"
                        name="extra_parameter_one_value"
                        value="<?= htmlspecialchars((string) old('extra_parameter_one_value', $settingsData['extra_parameter_one_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="WAHA: default"
                    />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="extra_parameter_two_key" class="block text-sm font-medium text-slate-600">Parameter Lain 2</label>
                    <input
                        type="text"
                        id="extra_parameter_two_key"
                        name="extra_parameter_two_key"
                        value="<?= htmlspecialchars((string) old('extra_parameter_two_key', $settingsData['extra_parameter_two_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="Mis. x-secret"
                    />
                </div>
                <div>
                    <label for="extra_parameter_two_value" class="block text-sm font-medium text-slate-600">Nilai 2</label>
                    <input
                        type="text"
                        id="extra_parameter_two_value"
                        name="extra_parameter_two_value"
                        value="<?= htmlspecialchars((string) old('extra_parameter_two_value', $settingsData['extra_parameter_two_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="Masukkan nilai parameter tambahan"
                    />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="send_interval_seconds" class="block text-sm font-medium text-slate-600">Kirim Pesan Setiap</label>
                    <select
                        id="send_interval_seconds"
                        name="send_interval_seconds"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    >
                        <?php foreach ($intervalOptionsData as $interval => $label): ?>
                            <?php $intervalValue = (int) $interval; ?>
                            <option value="<?= htmlspecialchars((string) $intervalValue, ENT_QUOTES, 'UTF-8') ?>" <?= $sendIntervalValue === $intervalValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="qr_scan_url" class="block text-sm font-medium text-slate-600">URL Scan QR Code</label>
                    <textarea
                        id="qr_scan_url"
                        name="qr_scan_url"
                        rows="2"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="https://api.example.com/whatsapp/qrcode"><?= htmlspecialchars((string) old('qr_scan_url', $settingsData['qr_scan_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    <p class="mt-1 text-xs text-slate-500">Opsional — tautan untuk memindai ulang token WhatsApp gateway.</p>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">
                    Pastikan kredensial API valid agar pengiriman pesan otomatis berjalan stabil.
                </p>
                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <i class="ri-save-3-line text-base"></i>
                    Simpan Pengaturan
                </button>
            </div>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-800">Daftar Antrian Pesan</h3>
                <p class="text-sm text-slate-500">Pesan akan dikirim oleh cron job sesuai jeda yang Anda tetapkan.</p>
            </div>
            <div class="text-xs text-slate-500">
                <span class="font-semibold">Memantau per</span> <?= htmlspecialchars(date('d M Y H:i'), ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

        <?php
            $statusLabels = [
                'pending' => 'Menunggu',
                'processing' => 'Sedang Diproses',
                'sent' => 'Terkirim',
                'failed' => 'Gagal',
            ];
            $statusDescriptions = [
                'pending' => 'Menanti jadwal kirim',
                'processing' => 'Sedang dicoba',
                'sent' => 'Berhasil dikirim',
                'failed' => 'Perlu perhatian',
            ];
            $statusColors = [
                'pending' => 'bg-amber-100 text-amber-700',
                'processing' => 'bg-sky-100 text-sky-700',
                'sent' => 'bg-emerald-100 text-emerald-700',
                'failed' => 'bg-rose-100 text-rose-700',
            ];
            $summaryOrder = ['pending', 'processing', 'sent', 'failed'];
        ?>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($summaryOrder as $statusKey): ?>
                <?php
                    $countValue = (int) ($queueSummaryData[$statusKey] ?? 0);
                    $label = $statusLabels[$statusKey] ?? ucfirst($statusKey);
                    $description = $statusDescriptions[$statusKey] ?? '';
                ?>
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100"><?= number_format($countValue) ?></p>
                    <?php if ($description !== ''): ?>
                        <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($queueItemsData)): ?>
            <p class="mt-6 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                Belum ada pesan dalam antrian. Pesan baru akan muncul di sini ketika ada proses yang menjadwalkan pengiriman.
            </p>
        <?php else: ?>
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-2">Nomor Tujuan</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Jadwal</th>
                            <th class="px-3 py-2">Percobaan</th>
                            <th class="px-3 py-2">Pesan</th>
                            <th class="px-3 py-2">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php foreach ($queueItemsData as $item): ?>
                            <?php
                                $queueStatus = (string) ($item['status'] ?? 'pending');
                                $statusLabel = $statusLabels[$queueStatus] ?? ucfirst($queueStatus);
                                $statusBadge = $statusColors[$queueStatus] ?? 'bg-slate-100 text-slate-700';
                                $phone = (string) ($item['phone'] ?? '-');
                                $availableAtRaw = (string) ($item['available_at'] ?? '');
                                $availableTimestamp = $availableAtRaw !== '' ? strtotime($availableAtRaw) : false;
                                $availableAtDisplay = $availableTimestamp ? date('d M Y H:i', $availableTimestamp) : '-';
                                $lastAttemptRaw = (string) ($item['last_attempt_at'] ?? '');
                                $lastAttemptTimestamp = $lastAttemptRaw !== '' ? strtotime($lastAttemptRaw) : false;
                                $lastAttemptDisplay = $lastAttemptTimestamp ? date('d M Y H:i', $lastAttemptTimestamp) : '-';
                                $sentAtRaw = (string) ($item['sent_at'] ?? '');
                                $sentTimestamp = $sentAtRaw !== '' ? strtotime($sentAtRaw) : false;
                                $sentAtDisplay = $sentTimestamp ? date('d M Y H:i', $sentTimestamp) : '-';
                                $responseStatus = $item['response_status'] ?? null;
                                $attemptsCount = (int) ($item['attempts'] ?? 0);
                                $messageBody = (string) ($item['message'] ?? '');
                                if (mb_strlen($messageBody) > 160) {
                                    $messageBody = mb_substr($messageBody, 0, 160) . '…';
                                }
                                $lastError = trim((string) ($item['last_error'] ?? ''));
                                $lastResponse = trim((string) ($item['last_response'] ?? ''));
                            ?>
                            <tr class="align-top">
                                <td class="px-3 py-3 font-medium"><?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= htmlspecialchars($statusBadge, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-xs text-slate-500">
                                    <div><span class="font-semibold text-slate-600">Siap:</span> <?= htmlspecialchars($availableAtDisplay, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div><span class="font-semibold text-slate-600">Terakhir coba:</span> <?= htmlspecialchars($lastAttemptDisplay, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div><span class="font-semibold text-slate-600">Terkirim:</span> <?= htmlspecialchars($sentAtDisplay, ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td class="px-3 py-3 text-xs text-slate-500">
                                    <?= number_format($attemptsCount) ?>x
                                    <?php if ($responseStatus !== null): ?>
                                        <div>HTTP <?= htmlspecialchars((string) $responseStatus, ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3 text-xs text-slate-600">
                                    <?= nl2br(htmlspecialchars($messageBody, ENT_QUOTES, 'UTF-8')) ?>
                                </td>
                                <td class="px-3 py-3 text-xs text-slate-500">
                                    <?php if ($lastError !== ''): ?>
                                    <div class="font-semibold text-rose-600">Error: <?= htmlspecialchars($lastError, ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if ($lastResponse !== ''): ?>
                                        <?php
                                            $responsePreview = $lastResponse;
                                            if (mb_strlen($responsePreview) > 160) {
                                                $responsePreview = mb_substr($responsePreview, 0, 160) . '…';
                                            }
                                        ?>
                                        <div class="mt-1 text-slate-500">Respon: <?= nl2br(htmlspecialchars($responsePreview, ENT_QUOTES, 'UTF-8')) ?></div>
                                    <?php endif; ?>
                                    <?php if ($lastError === '' && $lastResponse === '' && $responseStatus === null): ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-indigo-100 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-100 pb-4">
            <h3 class="text-lg font-semibold text-slate-800">Kirim Pesan Uji</h3>
            <p class="text-sm text-slate-500">
                Gunakan formulir ini untuk memastikan endpoint WhatsApp Gateway merespons dengan benar.
                Pesan percobaan tidak akan mempengaruhi data tagihan.
            </p>
        </div>
        <form action="<?= htmlspecialchars(base_url('admin/integrasi/whatsapp/test'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-4 space-y-4">
            <?= csrf_field() ?>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="test_phone" class="block text-sm font-medium text-slate-600">Nomor WhatsApp Tujuan</label>
                    <input
                        type="text"
                        id="test_phone"
                        name="test_phone"
                        value="<?= htmlspecialchars($testPhoneValue, ENT_QUOTES, 'UTF-8') ?>"
                        required
                        placeholder="08xxxxxxxxxx"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    />
                    <p class="mt-1 text-xs text-slate-500">Gunakan nomor aktif dengan format Indonesia (awali 0) atau kode negara.</p>
                </div>
                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-slate-600">Placeholder Tersedia</label>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-slate-500">
                        <li><code>{{nama_siswa}}</code>, <code>{{judul_tagihan}}</code>, <code>{{metode_pembayaran}}</code></li>
                        <li><code>{{nominal_bayar}}</code>, <code>{{sisa_tagihan}}</code>, <code>{{tanggal_pembayaran}}</code></li>
                        <li><code>{{kode_pembayaran}}</code>, <code>{{nama_sekolah}}</code>, <code>{{link_bukti_bayar}}</code>, <code>{{link_bukti_bayar_html}}</code></li>
                    </ul>
                </div>
            </div>
            <div>
                <label for="test_message" class="block text-sm font-medium text-slate-600">Pesan Percobaan</label>
                <textarea
                    id="test_message"
                    name="test_message"
                    rows="4"
                    class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                ><?= htmlspecialchars($testMessageValue, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="flex items-center justify-between rounded-lg border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-xs text-indigo-700">
                <p>
                    Pesan uji menggunakan pengaturan gateway yang tersimpan. Cek daftar pesan di provider untuk memastikan pesan terkirim.
                </p>
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <i class="ri-send-plane-2-line text-base"></i>
                    Kirim Pesan Uji
                </button>
            </div>
        </form>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const template = document.getElementById('template');
    const hint = document.getElementById('waha-hint');
    const baseUrl = document.getElementById('base_url');
    const bodyType = document.getElementById('body_type');
    const targetKey = document.getElementById('default_parameter_key');
    const targetValue = document.getElementById('default_parameter_value');
    const messageKey = document.getElementById('default_message_key');
    const extraOneKey = document.getElementById('extra_parameter_one_key');
    const extraOneValue = document.getElementById('extra_parameter_one_value');

    function setIfEmpty(input, value, force) {
        if (!input) {
            return;
        }

        if (force || input.value.trim() === '') {
            input.value = value;
        }
    }

    function applyTemplatePreset(force) {
        const isWaha = template && template.value === 'waha';

        if (hint) {
            hint.classList.toggle('hidden', !isWaha);
        }

        if (!isWaha) {
            return;
        }

        if (bodyType) {
            bodyType.value = 'json';
        }

        if (baseUrl) {
            baseUrl.placeholder = 'https://waha.example.com/api/sendText';
        }

        setIfEmpty(targetKey, 'chatId', force);
        setIfEmpty(targetValue, '{{waha_chat_id}}', force);
        setIfEmpty(messageKey, 'text', force);
        setIfEmpty(extraOneKey, 'session', force);
        setIfEmpty(extraOneValue, 'default', force);
    }

    if (template) {
        template.addEventListener('change', function () {
            applyTemplatePreset(true);
        });
    }

    applyTemplatePreset(false);
});
</script>
