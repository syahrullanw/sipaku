<?php

namespace App\Controllers;

use App\Support\ModuleGuideCatalog;
use Core\Controller;
use Core\Request;
use Core\Response;

class GuideController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();
        if (!is_array($user)) {
            $user = [];
        }

        return $this->render('guides/index', [
            'title' => 'Pedoman Penggunaan',
            'pageTitle' => 'Pedoman Penggunaan',
            'activeMenu' => 'guides',
            'roleLabels' => ModuleGuideCatalog::roleLabelsForUser($user),
            'guideGroups' => ModuleGuideCatalog::groupsForUser($user),
        ], 'admin');
    }
}
