<?php

namespace Core;

abstract class Controller
{
    protected Application $app;

    protected ?string $layout = 'app';

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    protected function render(string $view, array $data = [], ?string $layout = null): Response
    {
        return view($view, $data, $layout ?? $this->layout);
    }

    protected function json(mixed $data, int $status = 200, array $headers = []): Response
    {
        return Response::json($data, $status, $headers);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        $target = $url;

        if (!preg_match('#^https?://#i', $url)) {
            $target = base_url(ltrim($url, '/'));
        }

        return Response::make('', $status, ['Location' => $target]);
    }

    public function app(): Application
    {
        return $this->app;
    }

    protected function ensureAuthenticated(): ?Response
    {
        if (!Auth::check()) {
            return $this->redirect('login');
        }

        return null;
    }

    protected function ensureGuest(): ?Response
    {
        if (Auth::check()) {
            return $this->redirect('dashboard');
        }

        return null;
    }

    /**
     * @param array<int, string>|string $roles
     */
    protected function ensureRole(array|string $roles, string $redirect = 'dashboard'): ?Response
    {
        $allowedRoles = array_map(static fn ($role) => (string) $role, (array) $roles);
        $user = Auth::user();
        $currentRole = (string) ($user['role'] ?? '');

        if ($user === null || !in_array($currentRole, $allowedRoles, true)) {
            if ($user !== null && \App\Support\UserModuleRules::allowsCurrentRequest($user, true)) {
                return null;
            }

            Session::flash('error', 'Anda tidak memiliki hak akses ke fitur ini.');

            return $this->redirect($redirect);
        }

        return null;
    }

    protected function ensureAdmin(string $redirect = 'dashboard'): ?Response
    {
        return $this->ensureRole('admin', $redirect);
    }

    protected function guardCsrf(Request $request, string $fallbackUrl = ''): ?Response
    {
        if (Csrf::validate($request->input('_token'))) {
            return null;
        }

        if ($request->isPostSizeExceeded()) {
            Session::flash('error', 'Ukuran upload melebihi batas server (post_max_size ' . $request->postMaxSize() . '). Perkecil file backup atau naikkan limit PHP.');
        } else {
            Session::flash('error', 'Sesi tidak valid atau telah kedaluwarsa. Silakan coba lagi.');
        }

        if ($fallbackUrl === '') {
            $referer = $request->header('Referer');
            $fallbackUrl = $referer !== null && $referer !== '' ? $referer : 'login';
        }

        return $this->redirect($fallbackUrl);
    }
}
