<?php

namespace App\Controllers;

use App\Support\Changelog;
use Core\Controller;
use Core\Request;
use Core\Response;

class ChangelogController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        return $this->render('changelog/index', [
            'title' => 'Changelog',
            'pageTitle' => 'Changelog',
            'activeMenu' => 'changelog',
            'currentVersion' => (string) config('app.version', ''),
            'releases' => Changelog::releases(),
        ], 'admin');
    }
}
