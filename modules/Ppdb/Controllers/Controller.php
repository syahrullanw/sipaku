<?php

namespace Modules\Ppdb\Controllers;

use App\Support\PpdbGate;
use App\Support\UserModuleRules;
use Core\Controller as BaseController;
use Core\Request;
use Core\Response;
use Core\Session;

abstract class Controller extends BaseController
{
    protected ?string $layout = 'admin';

    protected function guardAuthenticated(): ?Response
    {
        return $this->ensureAuthenticated();
    }

    protected function ensureAdminAccess(string $redirect = 'dashboard'): ?Response
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        if (UserModuleRules::allowsCurrentRequest($this->user(), true)) {
            return null;
        }

        try {
            PpdbGate::ensureAdmin($this->user());
        } catch (\RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());

            return $this->redirect($redirect);
        }

        return null;
    }

    protected function ensureResponsibleAccess(?int $periodId = null, string $redirect = 'dashboard'): ?Response
    {
        if ($response = $this->guardAuthenticated()) {
            return $response;
        }

        try {
            PpdbGate::ensureResponsible($this->user(), $periodId);
        } catch (\RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());

            return $this->redirect($redirect);
        }

        return null;
    }

    protected function guardCsrfOrRedirect(Request $request, string $redirectUrl): ?Response
    {
        if ($response = $this->guardCsrf($request, $redirectUrl)) {
            return $response;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function user(): ?array
    {
        return auth();
    }
}
