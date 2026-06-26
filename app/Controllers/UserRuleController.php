<?php

namespace App\Controllers;

use App\Support\UserModuleRules;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class UserRuleController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        return $this->render('admin/user-rules/index', [
            'title' => 'User Rules',
            'pageTitle' => 'User Rules',
            'activeMenu' => 'user-rules',
            'roles' => UserModuleRules::roleOptions(),
            'modules' => UserModuleRules::modules(),
            'permissions' => UserModuleRules::permissions(),
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

        if ($response = $this->guardCsrf($request, 'admin/user-rules')) {
            return $response;
        }

        $action = strtolower(trim((string) $request->input('action', 'save')));

        try {
            if ($action === 'reset') {
                UserModuleRules::reset();
                Session::flash('success', 'User rules dikembalikan ke aturan default.');
            } else {
                $permissions = $request->input('permissions', []);
                UserModuleRules::save(is_array($permissions) ? $permissions : []);
                Session::flash('success', 'User rules berhasil disimpan.');
            }
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan user rules: ' . $exception->getMessage());
        }

        return $this->redirect('admin/user-rules');
    }
}
