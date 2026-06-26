<?php

namespace App\Traits;

trait HandlesImportUpload
{
    /**
     * @param array<string, mixed> $file
     */
    protected function moveImportFile(array $file, ?string &$errorMessage = null): ?string
    {
        $errorCode = (int) ($file['error'] ?? \UPLOAD_ERR_NO_FILE);
        if ($errorCode !== \UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errorMessage = 'Tidak ada file yang diunggah atau file rusak.';

            return null;
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, ['xls', 'xlsx'], true)) {
            $errorMessage = 'Format file tidak didukung. Gunakan file XLS atau XLSX.';

            return null;
        }

        $temporary = tempnam(sys_get_temp_dir(), 'siakad_import_');
        if ($temporary === false) {
            $errorMessage = 'Gagal membuat file sementara.';

            return null;
        }

        $targetPath = $temporary . '.' . $extension;

        if (!@rename($temporary, $targetPath)) {
            @unlink($temporary);
            $errorMessage = 'Gagal menyiapkan file sementara.';

            return null;
        }

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            @unlink($targetPath);
            $errorMessage = 'Gagal menyimpan file yang diunggah.';

            return null;
        }

        return $targetPath;
    }
}

