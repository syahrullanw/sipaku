<?php

namespace Modules\Ppdb\Controllers\Admin;

use App\Models\Classroom;
use App\Models\PpdbPeriod;
use App\Models\PpdbRegistrant;
use App\Models\SchoolYear;
use App\Services\Ppdb\PpdbMigrationService;
use Modules\Ppdb\Controllers\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class MigrationController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        $periodOptions = PpdbPeriod::options();
        $schoolYearOptions = SchoolYear::options();

        $selectedPeriodId = (int) $request->query('periode_id', 0);
        $selectedYearId = (int) $request->query('target_school_year_id', active_school_year_id() ?? 0);
        $selectedClassId = (int) $request->query('target_class_id', 0);

        $availableRegistrants = [];
        if ($selectedPeriodId > 0) {
            $availableRegistrants = PpdbRegistrant::acceptedForPeriod($selectedPeriodId, true);
        }

        $classOptions = Classroom::options($selectedYearId > 0 ? $selectedYearId : null, $selectedClassId ?: null);

        return $this->render('ppdb/admin/migrations/index', [
            'title' => 'Migrasi PPDB ke Siswa',
            'pageTitle' => 'Migrasi Calon Siswa',
            'activeMenu' => 'ppdb-migration',
            'periodOptions' => $periodOptions,
            'selectedPeriodId' => $selectedPeriodId,
            'schoolYearOptions' => $schoolYearOptions,
            'selectedSchoolYearId' => $selectedYearId,
            'classOptions' => $classOptions,
            'selectedClassId' => $selectedClassId,
            'registrants' => $availableRegistrants,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'ppdb/admin/migrasi')) {
            return $response;
        }

        $periodId = (int) $request->input('periode_id', 0);
        $targetYearId = (int) $request->input('target_school_year_id', 0);
        $targetClassId = (int) $request->input('target_class_id', 0);
        $entries = $request->input('registrants', []);

        if ($periodId <= 0 || $targetYearId <= 0) {
            Session::flash('error', 'Periode PPDB dan tahun ajaran tujuan wajib dipilih.');

            return $this->redirect('ppdb/admin/migrasi');
        }

        if (!is_array($entries) || empty($entries)) {
            Session::flash('error', 'Tidak ada calon yang dipilih untuk dimigrasikan.');

            return $this->redirect('ppdb/admin/migrasi?periode_id=' . $periodId . '&target_school_year_id=' . $targetYearId . '&target_class_id=' . $targetClassId);
        }

        $success = 0;
        $failed = 0;

        foreach ($entries as $registrantId => $details) {
            if (!is_array($details) || empty($details['migrate'])) {
                continue;
            }

            $registrantId = (int) $registrantId;
            if ($registrantId <= 0) {
                continue;
            }

            $registrant = PpdbRegistrant::find($registrantId);
            if ($registrant === null) {
                $failed++;
                continue;
            }

            if ((int) ($registrant['periode_id'] ?? 0) !== $periodId || (int) ($registrant['siswa_id'] ?? 0) > 0) {
                continue;
            }

            $override = [
                'nipd' => trim((string) ($details['nipd'] ?? '')),
                'nisn' => trim((string) ($details['nisn'] ?? '')),
                'nik' => trim((string) ($details['nik'] ?? '')),
                'tempat_lahir' => trim((string) ($details['tempat_lahir'] ?? '')),
                'tanggal_lahir' => trim((string) ($details['tanggal_lahir'] ?? '')),
                'ayah_nama' => trim((string) ($details['ayah_nama'] ?? '')),
                'ibu_nama' => trim((string) ($details['ibu_nama'] ?? '')),
                'alamat' => trim((string) ($details['alamat'] ?? '')),
                'telepon' => trim((string) ($details['telepon'] ?? '')),
                'email' => trim((string) ($details['email'] ?? '')),
            ];

            $studentId = PpdbMigrationService::migrate($registrant, $targetYearId, $targetClassId ?: null, $override);

            if ($studentId !== null) {
                $success++;
            } else {
                $failed++;
            }
        }

        if ($success > 0) {
            Session::flash('success', sprintf('%d calon siswa berhasil dimigrasikan.', $success));
        }

        if ($failed > 0) {
            Session::flash('error', sprintf('%d calon siswa gagal dimigrasikan. Periksa kembali data wajibnya.', $failed));
        }

        $query = http_build_query([
            'periode_id' => $periodId,
            'target_school_year_id' => $targetYearId,
            'target_class_id' => $targetClassId,
        ]);

        return $this->redirect('ppdb/admin/migrasi?' . $query);
    }
}
