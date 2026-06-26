<?php

namespace App\Controllers;

use App\Services\ManagedFileStorage;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class FileManagerController extends Controller
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

        $filters = [
            'q' => (string) $request->query('q', ''),
            'school_period' => (string) $request->query('school_period', ''),
            'category' => (string) $request->query('category', ''),
            'subcategory' => (string) $request->query('subcategory', ''),
            'file_type' => (string) $request->query('file_type', ''),
            'disk' => (string) $request->query('disk', ''),
        ];

        $data = ManagedFileStorage::list($filters);

        return $this->render('admin/file-manager/index', [
            'title' => 'File Manager',
            'pageTitle' => 'File Manager',
            'activeMenu' => 'admin-file-manager',
            'items' => $data['items'],
            'totals' => $data['totals'],
            'filters' => $filters,
        ], 'admin');
    }

    public function download(Request $request, string $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $item = ManagedFileStorage::find((int) $id);
        $absolute = $item !== null ? ManagedFileStorage::absolutePath($item) : null;

        if ($item === null || $absolute === null || !is_file($absolute)) {
            Session::flash('error', 'File tidak ditemukan di server.');

            return $this->redirect('admin/file-manager');
        }

        $filename = basename((string) ($item['original_name'] ?: $item['stored_name']));
        $filename = str_replace('"', '', $filename);

        return Response::file($absolute, 200, [
            'Content-Type' => mime_content_type($absolute) ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
