<?php

namespace App\Controllers;

use App\Models\UserActivityLog;
use App\Support\UserActivityLogSetting;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class UserActivityLogController extends Controller
{
    protected ?string $layout = 'admin';

    /**
     * @var array<int, string>
     */
    private array $methodOptions = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @var array<int, string>
     */
    private array $roleOptions = ['admin', 'staff', 'bendahara', 'kepala_sekolah', 'guru', 'siswa'];

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $filters = $this->extractFilters($request);
        $result = UserActivityLog::paginate($filters, $filters['page'], $filters['per_page']);

        $queryParams = [
            'q' => $filters['keyword'],
            'method' => $filters['method'],
            'role' => $filters['role'],
            'status' => $filters['status_range'],
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'has_error' => $filters['has_error'],
            'per_page' => $filters['per_page'],
        ];

        return $this->render('admin/logs/index', [
            'title' => 'Log Pengguna',
            'pageTitle' => 'Log Aktivitas Pengguna',
            'activeMenu' => 'user-logs',
            'logs' => $result['data'],
            'filters' => $filters,
            'pagination' => $result['pagination'],
            'methodOptions' => $this->methodOptions,
            'roleOptions' => $this->roleOptions,
            'statusOptions' => [
                '' => 'Semua Status',
                '2xx' => '2xx • Berhasil',
                '3xx' => '3xx • Redirect',
                '4xx' => '4xx • Error Pengguna',
                '5xx' => '5xx • Error Server',
                'none' => 'Tanpa Status',
            ],
            'perPageOptions' => [25, 50, 100],
            'queryParams' => $queryParams,
            'logLimit' => UserActivityLogSetting::getLimit(),
            'logLimitBounds' => [
                'min' => UserActivityLogSetting::minLimit(),
                'max' => UserActivityLogSetting::maxLimit(),
                'default' => UserActivityLogSetting::defaultLimit(),
            ],
        ], 'admin');
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFilters(Request $request): array
    {
        $keyword = trim((string) $request->query('q', ''));

        $method = strtoupper(trim((string) $request->query('method', '')));
        if (!in_array($method, $this->methodOptions, true)) {
            $method = '';
        }

        $role = trim((string) $request->query('role', ''));
        if (!in_array($role, $this->roleOptions, true)) {
            $role = '';
        }

        $statusRange = trim((string) $request->query('status', ''));
        $allowedStatus = ['2xx', '3xx', '4xx', '5xx', 'none'];
        if (!in_array($statusRange, $allowedStatus, true)) {
            $statusRange = '';
        }

        $perPage = (int) $request->query('per_page', 25);
        $allowedPerPage = [25, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 25;
        }

        $page = (int) $request->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $dateFrom = $this->normalizeDate((string) $request->query('date_from', ''));
        $dateTo = $this->normalizeDate((string) $request->query('date_to', ''));

        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $hasError = $request->query('has_error', '') === '1' ? '1' : '';

        return [
            'keyword' => $keyword,
            'method' => $method,
            'role' => $role,
            'status_range' => $statusRange,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'has_error' => $hasError,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function updateSetting(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/log-aktivitas/pengaturan')) {
            return $response;
        }

        $rawLimit = (int) $request->input('max_logs', 0);

        if ($rawLimit <= 0) {
            Session::flash('error', 'Masukkan jumlah log yang ingin disimpan.');
            Session::flashInput($request->all());

            return $this->redirect('admin/log-aktivitas');
        }

        $normalized = UserActivityLogSetting::normalizeLimit($rawLimit);

        try {
            $saved = UserActivityLogSetting::saveLimit($normalized);
            UserActivityLog::enforceLimit($saved);

            $message = 'Batas penyimpanan log berhasil disimpan.';
            if ($saved !== $rawLimit) {
                $message .= ' Nilai disesuaikan ke ' . number_format($saved) . ' log.';
            }

            Session::flash('success', $message . ' Log tertua akan dihapus otomatis saat melewati batas.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan pengaturan batas log: ' . $exception->getMessage());
            Session::flashInput($request->all());
        }

        return $this->redirect('admin/log-aktivitas');
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date instanceof \DateTime ? $date->format('Y-m-d') : '';
    }
}
