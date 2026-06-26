<?php

namespace App\Controllers;

use App\Support\SchoolYearContext;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class ContextController extends Controller
{
    public function updateSchoolYear(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'dashboard')) {
            return $response;
        }

        $referer = (string) $request->header('Referer', '');
        $redirectTarget = $referer !== '' ? $referer : 'dashboard';
        $yearId = (int) $request->input('school_year_id', 0);

        if ($yearId <= 0) {
            Session::flash('error', 'Tahun ajaran tidak valid.');

            return $this->redirect($redirectTarget);
        }

        if (!SchoolYearContext::set($yearId)) {
            Session::flash('error', 'Tahun ajaran tidak ditemukan.');

            return $this->redirect($redirectTarget);
        }

        Session::flash('success', 'Tahun ajaran aktif diperbarui.');

        return $this->redirect($redirectTarget);
    }
}

