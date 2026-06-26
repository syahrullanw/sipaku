<?php

use App\Services\UserActivityLogger;
use Core\Application;
use Core\Auth;
use Core\Csrf;
use Core\Response;
use Core\Session;
use Core\View;

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $directory = $scriptName === '' ? '' : rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        if ($directory !== '' && $directory !== '/' && str_ends_with($directory, '/public')) {
            $directory = substr($directory, 0, -7) ?: '';
        }

        if ($directory === '/' || $directory === '.') {
            $directory = '';
        }

        $trimmed = ltrim($path, '/');

        if ($trimmed === '') {
            return $directory === '' ? '/' : $directory;
        }

        return ($directory === '' ? '' : $directory) . '/' . $trimmed;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url(trim($path, '/'));
    }
}

if (!function_exists('absolute_url')) {
    /**
     * Build an absolute URL based on the configured application URL.
     * Handles subdirectory installations gracefully.
     */
    function absolute_url(string $path = ''): string
    {
        $relative = base_url($path);
        if ($relative === '') {
            $relative = '/';
        }

        $configured = trim((string) config('app.url', ''));
        $serverScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $serverHost = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');

        if ($configured !== '') {
            $parts = parse_url($configured);
            $configScheme = $parts['scheme'] ?? $serverScheme;
            $configHost = $parts['host'] ?? '';
            $configPort = isset($parts['port']) ? ':' . $parts['port'] : '';
            $configPath = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

            $normalizedHost = strtolower($configHost);
            $placeholderHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
            $isLocalNetwork = $normalizedHost === ''
                || in_array($normalizedHost, $placeholderHosts, true)
                || preg_match('/^(10\.|127\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $normalizedHost) === 1;

            if ($configHost !== '' && !$isLocalNetwork) {
                $relativeWithBase = $relative;
                if ($configPath !== '') {
                    if ($relativeWithBase === '/') {
                        $relativeWithBase = $configPath;
                    } elseif (!str_starts_with($relativeWithBase, $configPath . '/')) {
                        $relativeWithBase = rtrim($configPath, '/') . (str_starts_with($relativeWithBase, '/') ? '' : '/') . ltrim($relativeWithBase, '/');
                    }
                }

                if (!str_starts_with($relativeWithBase, '/')) {
                    $relativeWithBase = '/' . $relativeWithBase;
                }

                return $configScheme . '://' . $configHost . $configPort . $relativeWithBase;
            }
        }

        if ($serverHost !== '') {
            if (!str_starts_with($relative, '/')) {
                $relative = '/' . $relative;
            }

            return $serverScheme . '://' . $serverHost . $relative;
        }

        return $relative;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="_token" value="' . $token . '">';
    }
}

if (!function_exists('app')) {
    /**
     * Get the application instance.
     */
    function app(): ?Application
    {
        return Application::getInstance();
    }
}

if (!function_exists('base_path')) {
    /**
     * Resolve the absolute path relative to the project base.
     */
    function base_path(string $path = ''): string
    {
        $base = app()?->getBasePath() ?? dirname(__DIR__);

        return rtrim($base . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''), DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return base_path('app' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return base_path('app/Config' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
    }
}

if (!function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return base_path('resources' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
    }
}

if (!function_exists('module_path')) {
    function module_path(string $module, string $path = ''): string
    {
        return base_path('modules' . DIRECTORY_SEPARATOR . $module . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
    }
}

if (!function_exists('config')) {
    /**
     * Retrieve configuration values using dot notation.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    function config(string $key, mixed $default = null): mixed
    {
        return Application::getInstance()?->config()->get($key, $default);
    }
}

if (!function_exists('activity')) {
    /**
     * Set a human-readable description for the current user activity log entry.
     */
    function activity(string $description): void
    {
        UserActivityLogger::describe($description);
    }
}

if (!function_exists('view')) {
    /**
     * Render a view and return a response.
     *
     * @param array<string, mixed> $data
     */
    function view(string $view, array $data = [], ?string $layout = 'app'): Response
    {
        $content = View::render($view, $data, $layout);

        return Response::make($content);
    }
}

if (!function_exists('auth')) {
    /**
     * Get the currently authenticated user data.
     *
     * @return array<string, mixed>|null
     */
    function auth(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('demo_mode_enabled')) {
    function demo_mode_enabled(): bool
    {
        return \App\Support\DemoMode::isEnabled();
    }
}

if (!function_exists('maintenance_mode_enabled')) {
    function maintenance_mode_enabled(): bool
    {
        return \App\Support\MaintenanceMode::isEnabled();
    }
}

if (!function_exists('session')) {
    function session(string $key, mixed $default = null): mixed
    {
        return Session::get($key, $default);
    }
}

if (!function_exists('session_flash')) {
    function session_flash(string $key, mixed $default = null): mixed
    {
        return Session::getFlash($key, $default);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = null): mixed
    {
        return Session::old($key, $default);
    }
}

if (!function_exists('active_school_year')) {
    /**
     * @return array<string, mixed>|null
     */
    function active_school_year(): ?array
    {
        return \App\Support\SchoolYearContext::resolve();
    }
}

if (!function_exists('active_school_year_id')) {
    function active_school_year_id(): ?int
    {
        $year = active_school_year();

        return $year !== null ? (int) ($year['id'] ?? 0) : null;
    }
}

if (!function_exists('app_branding')) {
    /**
     * @return array{name: string, icon?: string}
     */
    function app_branding(): array
    {
        /** @var array{name: string, icon?: string}|null $branding */
        static $branding = null;

        if ($branding !== null) {
            return $branding;
        }

        $branding = [
            'name' => (string) config('app.name', 'Aplikasi Sekolah'),
        ];

        try {
            $school = \App\Models\SchoolProfile::first();
            if (is_array($school)) {
                $logoPath = trim((string) ($school['logo_sekolah'] ?? ''));
                if ($logoPath !== '') {
                    $branding['logo'] = $logoPath;
                }

                $iconPath = trim((string) ($school['app_icon'] ?? ''));
                if ($iconPath !== '') {
                    $branding['icon'] = $iconPath;
                }
            }
        } catch (\Throwable) {
            // Ignore failures so the UI can still be rendered during setup.
        }

        return $branding;
    }
}

if (!function_exists('app_icon_asset')) {
    function app_icon_asset(?string $fallback = null): string
    {
        $branding = app_branding();
        $iconPath = isset($branding['icon']) && $branding['icon'] !== '' ? $branding['icon'] : null;
        $target = $iconPath ?? ($fallback ?? 'icons/icon-512.png');

        return asset($target);
    }
}

if (!function_exists('app_logo_asset')) {
    function app_logo_asset(?string $fallback = null): string
    {
        $branding = app_branding();
        $logoPath = isset($branding['logo']) && $branding['logo'] !== '' ? $branding['logo'] : null;
        if ($logoPath !== null) {
            return asset($logoPath);
        }

        return app_icon_asset($fallback);
    }
}

if (!function_exists('student_dapodik_badge')) {
    /**
     * @param array<string, mixed> $student
     */
    function student_dapodik_badge(array $student, string $extraClass = ''): string
    {
        $status = '';
        foreach (['status_dapodik', 'student_status_dapodik', 'siswa_status_dapodik'] as $key) {
            if (array_key_exists($key, $student)) {
                $status = strtolower(trim(str_replace([' ', '-'], '_', (string) $student[$key])));
                break;
            }
        }

        if ($status === '') {
            $studentId = 0;
            foreach (['siswa_id', 'student_id'] as $key) {
                if (!empty($student[$key])) {
                    $studentId = (int) $student[$key];
                    break;
                }
            }

            if ($studentId <= 0 && (array_key_exists('nama', $student) || array_key_exists('nisn', $student) || array_key_exists('nipd', $student))) {
                $studentId = (int) ($student['id'] ?? 0);
            }

            if ($studentId > 0) {
                static $statusCache = [];
                if (!array_key_exists($studentId, $statusCache)) {
                    $statusCache[$studentId] = '';
                    try {
                        $statement = \Core\Database::connection()->prepare('SELECT status_dapodik FROM siswa WHERE id = :id LIMIT 1');
                        if ($statement !== false) {
                            $statement->bindValue(':id', $studentId, \PDO::PARAM_INT);
                            if ($statement->execute()) {
                                $value = $statement->fetchColumn();
                                $statusCache[$studentId] = $value === false ? '' : (string) $value;
                            }
                        }
                    } catch (\Throwable) {
                        $statusCache[$studentId] = '';
                    }
                }

                $status = strtolower(trim(str_replace([' ', '-'], '_', (string) $statusCache[$studentId])));
            }
        }

        if (!in_array($status, ['belum_masuk', 'belum_masuk_dapodik'], true)) {
            return '';
        }

        $classes = trim('inline-flex items-center rounded bg-amber-100 px-1 py-0 text-[6px] font-semibold uppercase leading-[8px] tracking-wide text-amber-700 ' . $extraClass);

        return '<span class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '">Belum masuk Dapodik</span>';
    }
}

if (!function_exists('student_status_value')) {
    /**
     * @param array<string, mixed> $student
     */
    function student_status_value(array $student): string
    {
        $status = '';
        foreach (['student_status', 'siswa_status', 'status_siswa'] as $key) {
            if (array_key_exists($key, $student)) {
                $status = (string) $student[$key];
                break;
            }
        }

        if ($status === '') {
            $looksLikeStudentRecord = array_key_exists('nama', $student)
                || array_key_exists('nisn', $student)
                || array_key_exists('nipd', $student);

            if ($looksLikeStudentRecord && array_key_exists('status', $student)) {
                $status = (string) $student['status'];
            }
        }

        $status = strtolower(trim(str_replace([' ', '-'], '_', $status)));

        if ($status === '') {
            $studentId = 0;
            foreach (['siswa_id', 'student_id'] as $key) {
                if (!empty($student[$key])) {
                    $studentId = (int) $student[$key];
                    break;
                }
            }

            if ($studentId <= 0 && (array_key_exists('nama', $student) || array_key_exists('nisn', $student) || array_key_exists('nipd', $student))) {
                $studentId = (int) ($student['id'] ?? 0);
            }

            if ($studentId > 0) {
                static $statusCache = [];
                if (!array_key_exists($studentId, $statusCache)) {
                    $statusCache[$studentId] = '';
                    try {
                        $statement = \Core\Database::connection()->prepare('SELECT status FROM siswa WHERE id = :id LIMIT 1');
                        if ($statement !== false) {
                            $statement->bindValue(':id', $studentId, \PDO::PARAM_INT);
                            if ($statement->execute()) {
                                $value = $statement->fetchColumn();
                                $statusCache[$studentId] = $value === false ? '' : (string) $value;
                            }
                        }
                    } catch (\Throwable) {
                        $statusCache[$studentId] = '';
                    }
                }

                $status = strtolower(trim(str_replace([' ', '-'], '_', (string) $statusCache[$studentId])));
            }
        }

        return $status;
    }
}

if (!function_exists('student_is_inactive')) {
    /**
     * @param array<string, mixed> $student
     */
    function student_is_inactive(array $student): bool
    {
        return student_status_value($student) === 'nonaktif';
    }
}

if (!function_exists('student_status_badge')) {
    /**
     * @param array<string, mixed> $student
     */
    function student_status_badge(array $student, string $extraClass = ''): string
    {
        if (!student_is_inactive($student)) {
            return '';
        }

        $classes = trim('inline-flex items-center rounded bg-rose-100 px-1 py-0 text-[6px] font-semibold uppercase leading-[8px] tracking-wide text-rose-700 ' . $extraClass);

        return '<span class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '">Nonaktif</span>';
    }
}
