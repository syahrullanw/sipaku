<?php
    $baseUrl = base_url('admin/log-aktivitas');
    $queryParams = $queryParams ?? [];
    $queryParams = array_filter(
        $queryParams,
        static fn ($value) => $value !== '' && $value !== null
    );
    $buildPageUrl = static function (int $page) use ($baseUrl, $queryParams): string {
        $params = array_merge($queryParams, ['page' => $page]);
        $query = http_build_query($params);

        return $query === '' ? $baseUrl : $baseUrl . '?' . $query;
    };

    $pagination = $pagination ?? ['page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1];
    $currentPage = max(1, (int) ($pagination['page'] ?? 1));
    $perPage = max(1, (int) ($pagination['per_page'] ?? 25));
    $total = max(0, (int) ($pagination['total'] ?? 0));
    $lastPage = max(1, (int) ($pagination['last_page'] ?? 1));
    $currentCount = isset($logs) ? count($logs) : 0;
    $fromRecord = $total === 0 ? 0 : (($currentPage - 1) * $perPage + 1);
    $toRecord = $total === 0 ? 0 : min($total, $fromRecord + $currentCount - 1);

    $filters = $filters ?? [
        'keyword' => '',
        'method' => '',
        'role' => '',
        'status_range' => '',
        'date_from' => '',
        'date_to' => '',
        'has_error' => '',
        'per_page' => 25,
    ];
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Audit</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">
                    Log Aktivitas Pengguna
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                    Pantau setiap permintaan yang masuk, lengkap dengan identitas pengguna, jalur akses, serta payload yang dikirim.
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-500 dark:border-slate-600 dark:text-slate-300">
                <p class="text-xs uppercase tracking-wide text-slate-400">Total Log</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"><?= number_format($total) ?></p>
            </div>
        </div>

        <form action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>" method="get" class="mt-6 space-y-4">
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <div class="xl:col-span-2">
                    <label for="filter-keyword" class="text-sm font-medium text-slate-600 dark:text-slate-200">Kata Kunci</label>
                    <input
                        type="text"
                        id="filter-keyword"
                        name="q"
                        value="<?= htmlspecialchars((string) ($filters['keyword'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Cari nama, username, path, IP, atau route"
                        class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-slate-700"
                    />
                </div>
                <div>
                    <label for="filter-method" class="text-sm font-medium text-slate-600 dark:text-slate-200">Metode</label>
                    <select
                        id="filter-method"
                        name="method"
                        class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-slate-700"
                    >
                        <option value="">Semua</option>
                        <?php foreach ($methodOptions as $methodOption): ?>
                            <option value="<?= htmlspecialchars($methodOption, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['method'] ?? '') === $methodOption ? 'selected' : '' ?>>
                                <?= htmlspecialchars($methodOption, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter-role" class="text-sm font-medium text-slate-600 dark:text-slate-200">Peran</label>
                    <select
                        id="filter-role"
                        name="role"
                        class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-slate-700"
                    >
                        <option value="">Semua</option>
                        <?php foreach ($roleOptions as $roleOption): ?>
                            <option value="<?= htmlspecialchars($roleOption, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['role'] ?? '') === $roleOption ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $roleOption)), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter-status" class="text-sm font-medium text-slate-600 dark:text-slate-200">Status</label>
                    <select
                        id="filter-status"
                        name="status"
                        class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-slate-700"
                    >
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['status_range'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter-date-from" class="text-sm font-medium text-slate-600 dark:text-slate-200">Tanggal Mulai</label>
                    <input
                        type="date"
                        id="filter-date-from"
                        name="date_from"
                        value="<?= htmlspecialchars((string) ($filters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-slate-700"
                    />
                </div>
                <div>
                    <label for="filter-date-to" class="text-sm font-medium text-slate-600 dark:text-slate-200">Tanggal Selesai</label>
                    <input
                        type="date"
                        id="filter-date-to"
                        name="date_to"
                        value="<?= htmlspecialchars((string) ($filters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-slate-700"
                    />
                </div>
                <div>
                    <label for="filter-per-page" class="text-sm font-medium text-slate-600 dark:text-slate-200">Data per halaman</label>
                    <select
                        id="filter-per-page"
                        name="per_page"
                        class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-slate-700"
                    >
                        <?php foreach ($perPageOptions as $option): ?>
                            <option value="<?= (int) $option ?>" <?= (int) ($filters['per_page'] ?? 25) === (int) $option ? 'selected' : '' ?>>
                                <?= (int) $option ?> entri
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex flex-col gap-4 pt-2 sm:flex-row sm:items-center sm:justify-between">
                <label class="flex items-center gap-3 text-sm font-medium text-slate-600 dark:text-slate-200">
                    <input
                        type="checkbox"
                        name="has_error"
                        value="1"
                        <?= ($filters['has_error'] ?? '') === '1' ? 'checked' : '' ?>
                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    Tampilkan hanya log yang memiliki error
                </label>
                <div class="flex flex-wrap gap-3">
                    <a
                        href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        Reset
                    </a>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-6 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Terapkan Filter
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Pengaturan Penyimpanan</p>
                <h2 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">Batas Log Pengguna</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                    Log tertua akan dihapus otomatis saat total log melebihi batas yang disimpan. Minimal
                    <?= number_format((int) ($logLimitBounds['min'] ?? 0)) ?> dan maksimal <?= number_format((int) ($logLimitBounds['max'] ?? 0)) ?> log.
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-500 dark:border-slate-600 dark:text-slate-300">
                <p class="text-xs uppercase tracking-wide text-slate-400">Batas aktif</p>
                <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-white">
                    <?= number_format((int) ($logLimit ?? ($logLimitBounds['default'] ?? 0))) ?> log
                </p>
            </div>
        </div>

        <form
            action="<?= htmlspecialchars(base_url('admin/log-aktivitas/pengaturan'), ENT_QUOTES, 'UTF-8') ?>"
            method="post"
            class="mt-4 space-y-3"
        >
            <?= csrf_field() ?>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                <div class="flex-1">
                    <label for="max-logs" class="text-sm font-medium text-slate-700 dark:text-slate-100">Jumlah log maksimum</label>
                    <input
                        type="number"
                        id="max-logs"
                        name="max_logs"
                        min="<?= (int) ($logLimitBounds['min'] ?? 0) ?>"
                        max="<?= (int) ($logLimitBounds['max'] ?? 0) ?>"
                        value="<?= htmlspecialchars((string) old('max_logs', $logLimit ?? $logLimitBounds['default'] ?? 5000), ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring dark:border-slate-600 dark:bg-slate-700"
                        inputmode="numeric"
                    />
                </div>
                <div class="flex items-center gap-3 sm:self-end">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Simpan Batas
                    </button>
                </div>
            </div>
            <div class="flex flex-col gap-1 text-xs text-slate-500 dark:text-slate-400 sm:flex-row sm:items-center sm:gap-4">
                <p>Batas default: <?= number_format((int) ($logLimitBounds['default'] ?? 0)) ?> log.</p>
                <p>Log lama dipangkas otomatis saat batas tercapai.</p>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:bg-slate-900/30 dark:text-slate-400">
                    <tr>
                        <th class="px-6 py-4">Waktu</th>
                        <th class="px-6 py-4">Pengguna</th>
                        <th class="px-6 py-4">Tindakan</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Jejak</th>
                        <th class="px-6 py-4 text-right">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                                $timestamp = $log['created_at'] ?? null;
                                $formattedTime = $timestamp ? date('d M Y H:i:s', strtotime($timestamp)) : '-';
                                $method = $log['request_method'] ?? '-';
                                $path = $log['request_path'] ?? '-';
                                $statusCode = $log['status_code'] ?? null;
                                $statusBadgeClass = 'text-slate-600 bg-slate-100 dark:text-slate-200 dark:bg-slate-700/60';
                                if (is_numeric($statusCode)) {
                                    $statusInt = (int) $statusCode;
                                    if ($statusInt >= 500) {
                                        $statusBadgeClass = 'text-rose-700 bg-rose-50 dark:text-rose-300 dark:bg-rose-500/20';
                                    } elseif ($statusInt >= 400) {
                                        $statusBadgeClass = 'text-amber-700 bg-amber-50 dark:text-amber-200 dark:bg-amber-500/20';
                                    } elseif ($statusInt >= 300) {
                                        $statusBadgeClass = 'text-blue-700 bg-blue-50 dark:text-blue-200 dark:bg-blue-500/20';
                                    } elseif ($statusInt >= 200) {
                                        $statusBadgeClass = 'text-emerald-700 bg-emerald-50 dark:text-emerald-200 dark:bg-emerald-500/20';
                                    }
                                }
                                $methodClass = match ($method) {
                                    'POST' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200',
                                    'PUT', 'PATCH' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200',
                                    'DELETE' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200',
                                    default => 'bg-slate-100 text-slate-700 dark:bg-slate-700/60 dark:text-slate-200',
                                };
                                $actionText = trim((string) ($log['action_description'] ?? ''));
                                if ($actionText === '') {
                                    $actionText = sprintf('%s %s', strtoupper($method), $path);
                                }
                                $routeActionText = $log['route_action'] ?? '--';
                                $payloadRaw = $log['payload'] ?? null;
                                $payloadDisplay = null;
                                if (is_string($payloadRaw) && $payloadRaw !== '') {
                                    $decoded = json_decode($payloadRaw, true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $payloadDisplay = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                    } else {
                                        $payloadDisplay = $payloadRaw;
                                    }
                                }
                                $errorMessage = $log['error_message'] ?? null;
                            ?>
                            <tr class="bg-white dark:bg-transparent">
                                <td class="px-6 py-4 align-top">
                                    <div class="font-semibold text-slate-800 dark:text-white"><?= htmlspecialchars($formattedTime, ENT_QUOTES, 'UTF-8') ?></div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">ID #<?= htmlspecialchars((string) ($log['id'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="font-semibold text-slate-800 dark:text-white">
                                        <?= htmlspecialchars($log['actor_name'] ?? 'Tidak diketahui', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        <?= htmlspecialchars($log['actor_username'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($log['actor_role'])): ?>
                                            &bull; <span class="uppercase"><?= htmlspecialchars($log['actor_role'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </p>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="font-semibold text-slate-800 dark:text-white">
                                        <?= htmlspecialchars($actionText, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-3">
                                        <span class="inline-flex items-center rounded-lg px-2 py-0.5 text-xs font-semibold <?= $methodClass ?>">
                                            <?= htmlspecialchars($method, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span class="text-xs font-mono text-slate-500 dark:text-slate-300"><?= htmlspecialchars($routeActionText === '' ? '--' : $routeActionText, ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <p class="mt-2 break-all font-mono text-xs text-slate-700 dark:text-slate-200"><?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $statusBadgeClass ?>">
                                        <?= $statusCode !== null ? htmlspecialchars((string) $statusCode, ENT_QUOTES, 'UTF-8') : '--' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <p class="text-xs text-slate-600 dark:text-slate-300">
                                        IP: <?= htmlspecialchars($log['ip_address'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <p class="mt-1 max-w-xs overflow-hidden text-ellipsis text-xs text-slate-500 dark:text-slate-400">
                                        <?= htmlspecialchars($log['user_agent'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-right align-top">
                                    <details class="group inline-block text-left">
                                        <summary class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                                            Lihat
                                            <i class="ri-arrow-down-s-line text-base text-slate-400 transition-transform duration-200 group-open:rotate-180 dark:text-slate-500"></i>
                                        </summary>
                                        <div class="mt-3 max-h-80 w-72 overflow-y-auto rounded-xl border border-slate-200 bg-white p-4 text-left text-xs text-slate-600 shadow-xl dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                                            <p class="font-semibold text-slate-800 dark:text-white">Route</p>
                                            <p class="mt-1 font-mono text-[11px] text-slate-500 dark:text-slate-400"><?= htmlspecialchars($log['route_action'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php if (!empty($payloadDisplay)): ?>
                                                <p class="mt-3 font-semibold text-slate-800 dark:text-white">Payload</p>
                                                <pre class="mt-1 max-h-40 overflow-auto rounded-lg bg-slate-900/90 px-3 py-2 text-[11px] text-white dark:bg-slate-800"><?= htmlspecialchars($payloadDisplay, ENT_QUOTES, 'UTF-8') ?></pre>
                                            <?php endif; ?>
                                            <?php if (!empty($errorMessage)): ?>
                                                <p class="mt-3 font-semibold text-rose-600 dark:text-rose-300">Error</p>
                                                <p class="mt-1 text-[11px] text-rose-600 dark:text-rose-300"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                            <p class="mt-3 text-[11px] text-slate-500 dark:text-slate-400">
                                                User Agent:
                                                <span class="font-mono"><?= htmlspecialchars($log['user_agent'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                                            </p>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-300">
                                Belum ada data log untuk filter yang dipilih.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="flex flex-col gap-4 border-t border-slate-100 px-6 py-4 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300 sm:flex-row sm:items-center sm:justify-between">
            <p>
                <?php if ($total === 0): ?>
                    Tidak ada data yang ditampilkan.
                <?php else: ?>
                    Menampilkan <?= number_format($fromRecord) ?>-<?= number_format($toRecord) ?> dari <?= number_format($total) ?> entri.
                <?php endif; ?>
            </p>
            <div class="flex items-center gap-2">
                <?php $prevDisabled = $currentPage <= 1; ?>
                <a
                    href="<?= $prevDisabled ? 'javascript:void(0);' : htmlspecialchars($buildPageUrl($currentPage - 1), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold <?= $prevDisabled ? 'cursor-not-allowed border-slate-100 text-slate-300 dark:border-slate-700 dark:text-slate-600' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700' ?>"
                    <?= $prevDisabled ? 'aria-disabled="true"' : '' ?>
                >
                    Sebelumnya
                </a>
                <?php $nextDisabled = $currentPage >= $lastPage; ?>
                <a
                    href="<?= $nextDisabled ? 'javascript:void(0);' : htmlspecialchars($buildPageUrl($currentPage + 1), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold <?= $nextDisabled ? 'cursor-not-allowed border-slate-100 text-slate-300 dark:border-slate-700 dark:text-slate-600' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700' ?>"
                    <?= $nextDisabled ? 'aria-disabled="true"' : '' ?>
                >
                    Selanjutnya
                </a>
            </div>
        </div>
    </div>
</div>
