<?php

use App\Controllers\AuthController;
use App\Controllers\AutomaticScheduleController;
use App\Controllers\ChangelogController;
use App\Controllers\ClassroomController;
use App\Controllers\GradeRescueWindowController;
use App\Controllers\FileManagerController;
use App\Controllers\DashboardController;
use App\Controllers\GuideController;
use App\Controllers\HomeController;
use App\Controllers\MajorController;
use App\Controllers\PasswordController;
use App\Controllers\ProfileController;
use App\Controllers\PublicGraduationController;
use App\Controllers\ContextController;
use App\Controllers\LessonScheduleController;
use App\Controllers\PwaController;
use App\Controllers\DigitalSignatureApprovalController;
use App\Controllers\DocumentVerificationController;
use App\Controllers\AttitudeController;
use App\Controllers\LetterVerificationController;
use App\Controllers\HomeroomAttitudeController;
use App\Controllers\HomeroomPrakerinConfirmationController;
use App\Controllers\HomeroomPrakerinController;
use App\Controllers\SchoolYearController;
use App\Controllers\StudentController;
use App\Controllers\StudentPlacementController;
use App\Controllers\StudentDocumentController;
use App\Controllers\StudentPhotoController;
use App\Controllers\SubjectController;
use App\Controllers\SubjectTeacherController;
use App\Controllers\PeriodicDataCopyController;
use App\Controllers\StudentAttendanceScanController;
use App\Controllers\TeacherAttendanceRecapController;
use App\Controllers\HomeroomAchievementController;
use App\Controllers\StudentAttendanceRecapController;
use App\Controllers\UserController;
use App\Controllers\UserRuleController;
use App\Controllers\UserActivityLogController;
use App\Controllers\LoginSessionSettingController;
use App\Controllers\MaintenanceModeController;
use App\Controllers\TeacherController;
use App\Controllers\PrakerinController;
use App\Controllers\ExtracurricularController;
use App\Controllers\AcademicPositionController;
use App\Controllers\SchoolProfileController;
use App\Controllers\HomeroomAttendanceController;
use App\Controllers\HomeroomExtracurricularController;
use App\Controllers\HomeroomGradeUploadController;
use App\Controllers\HomeroomGradeUploadTemplateController;
use App\Controllers\HomeroomPerStudentGradeController;
use App\Controllers\HomeroomGraduationController;
use App\Controllers\DemoModeController;
use App\Controllers\HomeroomLedgerController;
use App\Controllers\HomeroomNoteController;
use App\Controllers\HomeroomP5Controller;
use App\Controllers\HomeroomCocurricularController;
use App\Controllers\HomeroomTranscriptController;
use App\Controllers\HomeroomPromotionController;
use App\Controllers\TeacherAttendanceController;
use App\Controllers\TeacherPrakerinAssessmentController;
use App\Controllers\TeacherExtracurricularAssessmentController;
use App\Controllers\TeacherKurmerAssessmentController;
use App\Controllers\TeacherSubjectAssessmentController;
use App\Controllers\TeacherKnowledgeAssessmentController;
use App\Controllers\TeacherSkillAssessmentController;
use App\Controllers\TeacherSubjectLedgerController;
use App\Controllers\ReportPrintController;
use App\Controllers\StudentCardController;
use App\Controllers\DigitalSignatureRequestController;
use App\Controllers\StudentGradeController;
use App\Controllers\StudentRegisterController;
use App\Controllers\LegacyRaporMigrationController;
use App\Controllers\CleanRaportController;
use App\Controllers\CleanFinanceController;
use App\Controllers\CleanLettersController;
use App\Controllers\CleanPpdbController;
use App\Controllers\CleanActivityLogController;
use App\Controllers\BackupRestoreController;
use App\Controllers\WhatsappGatewayController;
use App\Controllers\TataUsahaController;
use App\Controllers\CbtExportController;
use App\Controllers\GraduationCertificateController;
use App\Controllers\UkkController;
use App\Controllers\UpdateController;

/** @var Core\Router $router */

$router->get('/', [HomeController::class, 'index']);
$router->get('/manifest.webmanifest', [PwaController::class, 'manifest']);
$router->get('/dokumen/validasi', [DocumentVerificationController::class, 'index']);
$router->get('/dokumen/validasi/{token}', [DocumentVerificationController::class, 'show']);
$router->get('/persuratan/validasi', [LetterVerificationController::class, 'index']);
$router->get('/persuratan/validasi/{token}', [LetterVerificationController::class, 'show']);
$router->get('/kelulusan', [PublicGraduationController::class, 'index']);
$router->get('/kelulusan/cetak/{token}', [PublicGraduationController::class, 'print']);

$router->get('/login', [AuthController::class, 'showLoginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->post('/context/school-year', [ContextController::class, 'updateSchoolYear']);

$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/pedoman', [GuideController::class, 'index']);
$router->get('/changelog', [ChangelogController::class, 'index']);
$router->get('/admin/file-manager', [FileManagerController::class, 'index']);
$router->get('/admin/file-manager/{id}/download', [FileManagerController::class, 'download']);

$router->get('/kepala-sekolah/ttd-digital', [DigitalSignatureApprovalController::class, 'index']);
$router->get('/kepala-sekolah/ttd-digital/transkrip', [DigitalSignatureApprovalController::class, 'transkrip']);
$router->get('/kepala-sekolah/skl', [DigitalSignatureApprovalController::class, 'skl']);
$router->post('/kepala-sekolah/ttd-digital/{id}/approve', [DigitalSignatureApprovalController::class, 'approve']);
$router->post('/kepala-sekolah/ttd-digital/{id}/reset', [DigitalSignatureApprovalController::class, 'reset']);
$router->post('/kepala-sekolah/ttd-digital/kelas/{id}/approve', [DigitalSignatureApprovalController::class, 'approveClass']);
$router->get('/kepala-sekolah/persuratan', [DigitalSignatureApprovalController::class, 'letters']);
$router->post('/kepala-sekolah/persuratan/{id}/approve', [DigitalSignatureApprovalController::class, 'approve']);
$router->post('/kepala-sekolah/persuratan/{id}/reset', [DigitalSignatureApprovalController::class, 'reset']);
$router->post('/raport/ttd-digital/request', [DigitalSignatureRequestController::class, 'requestStudent']);
$router->post('/raport/ttd-digital/request-class', [DigitalSignatureRequestController::class, 'requestClass']);

$router->get('/kaprodi/ukk', [UkkController::class, 'index']);
$router->post('/kaprodi/ukk/paket', [UkkController::class, 'saveExamPackage']);
$router->post('/kaprodi/ukk/paket/{id}/hapus', [UkkController::class, 'deleteExamPackage']);
$router->post('/kaprodi/ukk/skkni', [UkkController::class, 'saveSkkni']);
$router->post('/kaprodi/ukk/skkni/{id}/hapus', [UkkController::class, 'deleteSkkni']);
$router->post('/kaprodi/ukk/dudi', [UkkController::class, 'saveDudi']);
$router->post('/kaprodi/ukk/dudi/{id}/hapus', [UkkController::class, 'deleteDudi']);
$router->post('/kaprodi/ukk/asesor', [UkkController::class, 'saveAssessor']);
$router->post('/kaprodi/ukk/asesor/{id}/hapus', [UkkController::class, 'deleteAssessor']);
$router->post('/kaprodi/ukk/penilaian', [UkkController::class, 'saveAssessments']);
$router->get('/kaprodi/ukk/cetak/sertifikat', [UkkController::class, 'printCertificate']);
$router->get('/kaprodi/ukk/cetak/skill-passport', [UkkController::class, 'printSkillPassport']);

// Master Tahun Ajaran
$router->get('/master/tahun-ajaran', [SchoolYearController::class, 'index']);
$router->post('/master/tahun-ajaran', [SchoolYearController::class, 'store']);
$router->post('/master/tahun-ajaran/{id}/update', [SchoolYearController::class, 'update']);
$router->post('/master/tahun-ajaran/{id}/delete', [SchoolYearController::class, 'destroy']);

// Master Jurusan
$router->get('/master/jurusan', [MajorController::class, 'index']);
$router->post('/master/jurusan', [MajorController::class, 'store']);
$router->post('/master/jurusan/{id}/update', [MajorController::class, 'update']);
$router->post('/master/jurusan/{id}/delete', [MajorController::class, 'destroy']);

// Master Guru
$router->get('/master/guru', [TeacherController::class, 'index']);
$router->post('/master/guru', [TeacherController::class, 'store']);
$router->post('/master/guru/import', [TeacherController::class, 'import']);
$router->post('/master/guru/export', [TeacherController::class, 'export']);
$router->get('/master/guru/{id}/profil', [TeacherController::class, 'profile']);
$router->post('/master/guru/{id}/update', [TeacherController::class, 'update']);
$router->post('/master/guru/{id}/toggle-status', [TeacherController::class, 'toggleStatus']);
$router->post('/master/guru/{id}/reset-password', [TeacherController::class, 'resetPassword']);
$router->post('/master/guru/{id}/delete', [TeacherController::class, 'destroy']);

// Master Data Sikap
$router->get('/master/data-sikap', [AttitudeController::class, 'index']);
$router->post('/master/data-sikap', [AttitudeController::class, 'store']);
$router->post('/master/data-sikap/import', [AttitudeController::class, 'import']);
$router->post('/master/data-sikap/{id}/update', [AttitudeController::class, 'update']);
$router->post('/master/data-sikap/{id}/delete', [AttitudeController::class, 'destroy']);

// Master Tempat Prakerin
$router->get('/master/prakerin', [PrakerinController::class, 'index']);
$router->post('/master/prakerin', [PrakerinController::class, 'store']);
$router->post('/master/prakerin/{id}/update', [PrakerinController::class, 'update']);
$router->post('/master/prakerin/{id}/delete', [PrakerinController::class, 'destroy']);

// Master Ekstrakurikuler
$router->get('/master/ekskul', [ExtracurricularController::class, 'index']);
$router->post('/master/ekskul', [ExtracurricularController::class, 'store']);
$router->post('/master/ekskul/{id}/update', [ExtracurricularController::class, 'update']);
$router->post('/master/ekskul/{id}/delete', [ExtracurricularController::class, 'destroy']);

// Master Jabatan Akademik
$router->get('/master/jabatan-akademik', [AcademicPositionController::class, 'index']);
$router->post('/master/jabatan-akademik', [AcademicPositionController::class, 'store']);
$router->post('/master/jabatan-akademik/{id}/update', [AcademicPositionController::class, 'update']);
$router->post('/master/jabatan-akademik/{id}/assign', [AcademicPositionController::class, 'assignTeacher']);
$router->post('/master/jabatan-akademik/{id}/delete', [AcademicPositionController::class, 'destroy']);

// Master Sekolah
$router->get('/master/sekolah', [SchoolProfileController::class, 'index']);
$router->post('/master/sekolah', [SchoolProfileController::class, 'store']);
$router->post('/master/sekolah/{id}/update', [SchoolProfileController::class, 'update']);
$router->post('/master/sekolah/{id}/delete', [SchoolProfileController::class, 'destroy']);

// Master Kelas
$router->get('/master/kelas', [ClassroomController::class, 'index']);
$router->post('/master/kelas', [ClassroomController::class, 'store']);
$router->post('/master/kelas/{id}/update', [ClassroomController::class, 'update']);
$router->post('/master/kelas/{id}/delete', [ClassroomController::class, 'destroy']);

// Master Siswa
$router->get('/master/siswa', [StudentController::class, 'index']);
$router->post('/master/siswa', [StudentController::class, 'store']);
$router->get('/master/siswa/pindahan', [StudentController::class, 'transferCreate']);
$router->post('/master/siswa/pindahan', [StudentController::class, 'transferStore']);
$router->get('/master/siswa/pindahan/daftar', [StudentController::class, 'transferList']);
$router->get('/master/siswa/import/template', [StudentController::class, 'downloadImportTemplate']);
$router->post('/master/siswa/import', [StudentController::class, 'import']);
$router->get('/master/siswa/export', [StudentController::class, 'export']);
$router->get('/master/siswa/foto/massal', [StudentPhotoController::class, 'bulkIndex']);
$router->get('/master/siswa/{id}/profil', [StudentController::class, 'profile']);
$router->get('/master/siswa/{id}/edit', [StudentController::class, 'edit']);
$router->post('/master/siswa/{id}/update', [StudentController::class, 'update']);
$router->post('/master/siswa/{id}/delete', [StudentController::class, 'destroy']);
$router->post('/master/siswa/dokumen', [StudentDocumentController::class, 'store']);
$router->post('/master/siswa/dokumen/unduh', [StudentDocumentController::class, 'download']);
$router->post('/master/siswa/foto', [StudentPhotoController::class, 'store']);
$router->post('/master/siswa/foto/bulk', [StudentPhotoController::class, 'bulk']);
$router->get('/master/siswa/penempatan', [StudentPlacementController::class, 'index']);
$router->post('/master/siswa/penempatan', [StudentPlacementController::class, 'store']);
$router->get('/buku-induk', [StudentRegisterController::class, 'index']);
$router->get('/buku-induk/cetak', [StudentRegisterController::class, 'print']);
$router->get('/buku-induk/export', [StudentRegisterController::class, 'export']);

$router->get('/presensi/scan', [StudentAttendanceScanController::class, 'index']);
	$router->get('/presensi/scan/{token}', [StudentAttendanceScanController::class, 'show']);
	$router->post('/presensi/scan/{token}', [StudentAttendanceScanController::class, 'submit']);
	$router->get('/siswa/nilai', [StudentGradeController::class, 'index']);
	$router->get('/siswa/profil', [StudentController::class, 'selfSummary']);
	$router->get('/siswa/data-diri', [StudentController::class, 'selfProfile']);
	$router->post('/siswa/data-diri', [StudentController::class, 'selfUpdate']);
$router->post('/siswa/data-diri/foto', [StudentPhotoController::class, 'storeSelf']);
$router->get('/siswa/berkas', [StudentDocumentController::class, 'selfIndex']);
$router->post('/siswa/berkas', [StudentDocumentController::class, 'storeSelf']);
$router->post('/siswa/berkas/unduh', [StudentDocumentController::class, 'downloadSelf']);

// Akademik - Mata Pelajaran
$router->get('/akademik/mata-pelajaran', [SubjectController::class, 'index']);
$router->post('/akademik/mata-pelajaran', [SubjectController::class, 'store']);
$router->post('/akademik/mata-pelajaran/import', [SubjectController::class, 'import']);
$router->post('/akademik/mata-pelajaran/{id}/update', [SubjectController::class, 'update']);
$router->post('/akademik/mata-pelajaran/{id}/delete', [SubjectController::class, 'destroy']);

// Akademik - Guru Pengampu
$router->get('/akademik/guru-pengampu', [SubjectTeacherController::class, 'index']);
$router->post('/akademik/guru-pengampu', [SubjectTeacherController::class, 'store']);
$router->post('/akademik/guru-pengampu/import', [SubjectTeacherController::class, 'import']);
$router->post('/akademik/guru-pengampu/{id}/update', [SubjectTeacherController::class, 'update']);
$router->post('/akademik/guru-pengampu/{id}/delete', [SubjectTeacherController::class, 'destroy']);

// Akademik - Jadwal Pelajaran
$router->get('/akademik/jadwal/generate', [AutomaticScheduleController::class, 'index']);
$router->post('/akademik/jadwal/generate', [AutomaticScheduleController::class, 'generate']);
$router->post('/akademik/jadwal/generate/preferences', [AutomaticScheduleController::class, 'updatePreferences']);
$router->post('/akademik/jadwal/generate/parallel-preferences', [AutomaticScheduleController::class, 'updateParallelPreferences']);
$router->post('/akademik/jadwal/generate/time-preferences', [AutomaticScheduleController::class, 'updateTimePreferences']);
$router->post('/akademik/jadwal/generate/{draft}/validate', [AutomaticScheduleController::class, 'validateDraft']);
$router->post('/akademik/jadwal/generate/{draft}/activate', [AutomaticScheduleController::class, 'activate']);
$router->get('/akademik/jadwal/generate/{draft}/export', [AutomaticScheduleController::class, 'export']);
$router->post('/akademik/jadwal/generate/{draft}/items/{item}/update', [AutomaticScheduleController::class, 'updateItem']);
$router->post('/akademik/jadwal/generate/{draft}/items/{item}/lock', [AutomaticScheduleController::class, 'toggleLock']);
$router->post('/akademik/jadwal/generate/{draft}/items/{item}/delete', [AutomaticScheduleController::class, 'deleteItem']);
$router->get('/akademik/jadwal', [LessonScheduleController::class, 'index']);
$router->post('/akademik/jadwal', [LessonScheduleController::class, 'store']);
$router->post('/akademik/jadwal/salin', [LessonScheduleController::class, 'copyFromSource']);
$router->post('/akademik/jadwal/{id}/update', [LessonScheduleController::class, 'update']);
$router->post('/akademik/jadwal/{id}/delete', [LessonScheduleController::class, 'destroy']);
$router->get('/akademik/skl', [GraduationCertificateController::class, 'index']);
$router->post('/akademik/skl/ajukan', [GraduationCertificateController::class, 'store']);
$router->get('/kelulusan/skl/{id}/cetak', [GraduationCertificateController::class, 'print']);
$router->get('/siswa/kelulusan', [GraduationCertificateController::class, 'student']);

$router->get('/tata-usaha/sk-penugasan', [TataUsahaController::class, 'assignmentLetters']);
$router->post('/tata-usaha/sk-penugasan/ttd', [TataUsahaController::class, 'requestSignature']);
$router->get('/tata-usaha/sk-penugasan/cetak', [TataUsahaController::class, 'assignmentLettersPrint']);
$router->get('/tata-usaha/presensi-manual', [TataUsahaController::class, 'manualAttendance']);
$router->get('/tata-usaha/presensi-manual/cetak', [TataUsahaController::class, 'manualAttendancePrint']);
$router->get('/tata-usaha/presensi-manual/sampul', [TataUsahaController::class, 'manualAttendanceCover']);
$router->get('/tata-usaha/persuratan', [TataUsahaController::class, 'letters']);
$router->post('/tata-usaha/persuratan/kop', [TataUsahaController::class, 'updateLetterhead']);
$router->post('/tata-usaha/persuratan/surat-keluar/template', [TataUsahaController::class, 'parseOutgoingLetterTemplate']);
$router->get('/tata-usaha/persuratan/surat-keluar/pdf', [TataUsahaController::class, 'createOutgoingLetterPdf']);
$router->post('/tata-usaha/persuratan/surat-keluar', [TataUsahaController::class, 'storeOutgoingLetter']);
$router->get('/tata-usaha/persuratan/surat-keluar/{id}', [TataUsahaController::class, 'showOutgoingLetter']);
$router->get('/tata-usaha/persuratan/surat-keluar/{id}/preview-ttd', [TataUsahaController::class, 'previewOutgoingLetterSignature']);
$router->post('/tata-usaha/persuratan/surat-keluar/{id}/preview-ttd', [TataUsahaController::class, 'updateOutgoingLetterSignature']);
$router->get('/tata-usaha/persuratan/surat-keluar/{id}/cetak', [TataUsahaController::class, 'printOutgoingLetter']);
$router->post('/tata-usaha/persuratan/surat-keluar/{id}/hapus', [TataUsahaController::class, 'destroyOutgoingLetter']);
$router->post('/tata-usaha/persuratan/surat-masuk', [TataUsahaController::class, 'storeIncomingLetter']);
$router->post('/tata-usaha/persuratan/surat-masuk/{id}/hapus', [TataUsahaController::class, 'destroyIncomingLetter']);

$router->get('/akademik/presensi/rekap', [StudentAttendanceRecapController::class, 'index']);

// Wali Kelas - Nilai Sikap
$router->get('/walikelas/nilai-sikap/{jenis}', [HomeroomAttitudeController::class, 'index']);
$router->post('/walikelas/nilai-sikap/{jenis}', [HomeroomAttitudeController::class, 'store']);

// Wali Kelas - Prakerin
$router->get('/walikelas/prakerin', [HomeroomPrakerinController::class, 'index']);
$router->post('/walikelas/prakerin', [HomeroomPrakerinController::class, 'store']);
$router->post('/walikelas/prakerin/konfirmasi', [HomeroomPrakerinConfirmationController::class, 'store']);

// Wali Kelas - Ekskul
$router->get('/walikelas/ekskul', [HomeroomExtracurricularController::class, 'index']);
$router->post('/walikelas/ekskul', [HomeroomExtracurricularController::class, 'store']);

// Wali Kelas - Prestasi
$router->get('/walikelas/prestasi', [HomeroomAchievementController::class, 'index']);
$router->post('/walikelas/prestasi', [HomeroomAchievementController::class, 'store']);
$router->post('/walikelas/prestasi/{id}/delete', [HomeroomAchievementController::class, 'destroy']);

$router->get('/walikelas/kokurikuler', [HomeroomCocurricularController::class, 'index']);
$router->post('/walikelas/kokurikuler', [HomeroomCocurricularController::class, 'storeActivity']);
$router->post('/walikelas/kokurikuler/kegiatan/{id}/elemen', [HomeroomCocurricularController::class, 'storeElement']);
$router->post('/walikelas/kokurikuler/kegiatan/{id}/elemen/{element}/hapus', [HomeroomCocurricularController::class, 'deleteElement']);
$router->post('/walikelas/kokurikuler/kegiatan/{id}/penilaian', [HomeroomCocurricularController::class, 'storeAssessments']);
$router->post('/walikelas/kokurikuler/kegiatan/{id}/ringkasan', [HomeroomCocurricularController::class, 'storeSummaries']);

$router->get('/walikelas/status-naik-kelas', [HomeroomPromotionController::class, 'index']);
$router->post('/walikelas/status-naik-kelas', [HomeroomPromotionController::class, 'store']);

$router->get('/walikelas/status-lulus', [HomeroomGraduationController::class, 'index']);
$router->post('/walikelas/status-lulus', [HomeroomGraduationController::class, 'store']);
$router->post('/walikelas/skl/ajukan', [GraduationCertificateController::class, 'storeHomeroom']);

$router->get('/walikelas/catatan', [HomeroomNoteController::class, 'index']);
$router->post('/walikelas/catatan', [HomeroomNoteController::class, 'store']);
$router->get('/walikelas/p5', [HomeroomP5Controller::class, 'index']);
$router->post('/walikelas/p5', [HomeroomP5Controller::class, 'storeProject']);
$router->post('/walikelas/p5/elemen/{project}', [HomeroomP5Controller::class, 'storeElement']);
$router->post('/walikelas/p5/elemen/{project}/{element}/hapus', [HomeroomP5Controller::class, 'deleteElement']);
$router->post('/walikelas/p5/penilaian/{project}', [HomeroomP5Controller::class, 'storeAssessments']);
$router->post('/walikelas/p5/ringkasan/{project}', [HomeroomP5Controller::class, 'storeSummaries']);
$router->get('/walikelas/p5/cetak', [HomeroomP5Controller::class, 'printIndex']);
$router->get('/walikelas/p5/cetak/print', [HomeroomP5Controller::class, 'printReport']);
$router->post('/walikelas/p5/ttd-digital/request', [DigitalSignatureRequestController::class, 'requestP5']);
	$router->post('/walikelas/p5/ttd-digital/request-class', [DigitalSignatureRequestController::class, 'requestP5Class']);
	
	// Guru - Profil
	$router->get('/guru/profil', [TeacherController::class, 'selfProfile']);
	
	// Guru Pembina Ekskul - Penilaian
	$router->get('/guru/ekskul/nilai', [TeacherExtracurricularAssessmentController::class, 'index']);
	$router->post('/guru/ekskul/nilai', [TeacherExtracurricularAssessmentController::class, 'store']);

// Guru Pembina Prakerin - Penilaian
$router->get('/guru/prakerin/nilai', [TeacherPrakerinAssessmentController::class, 'index']);
$router->post('/guru/prakerin/nilai', [TeacherPrakerinAssessmentController::class, 'store']);

// Guru Mata Pelajaran - Penilaian
$router->get('/guru/nilai', [TeacherSubjectAssessmentController::class, 'index']);
$router->get('/guru/nilai/riwayat', [TeacherSubjectLedgerController::class, 'history']);
$router->post('/guru/nilai/{assignment}/pengaturan', [TeacherSubjectAssessmentController::class, 'updateSettings']);

$router->get('/guru/nilai/{assignment}/legger', [TeacherSubjectLedgerController::class, 'show']);
$router->get('/guru/nilai/{assignment}/legger/export/pdf', [TeacherSubjectLedgerController::class, 'exportPdf']);
$router->get('/guru/nilai/{assignment}/legger/export/excel', [TeacherSubjectLedgerController::class, 'exportExcel']);
$router->get('/guru/nilai/{assignment}/kurmer', [TeacherKurmerAssessmentController::class, 'index']);
$router->post('/guru/nilai/{assignment}/kurmer/tp', [TeacherKurmerAssessmentController::class, 'storeLearningObjective']);
$router->post('/guru/nilai/{assignment}/kurmer/tp/{objective}/hapus', [TeacherKurmerAssessmentController::class, 'deleteLearningObjective']);
$router->post('/guru/nilai/{assignment}/kurmer/tp/simpan', [TeacherKurmerAssessmentController::class, 'storeAssessments']);
$router->post('/guru/nilai/{assignment}/kurmer/ringkasan/simpan', [TeacherKurmerAssessmentController::class, 'storeSummaries']);
$router->get('/guru/nilai/{assignment}/pengetahuan', [TeacherKnowledgeAssessmentController::class, 'index']);
$router->post('/guru/nilai/{assignment}/pengetahuan/simpan', [TeacherKnowledgeAssessmentController::class, 'storeScores']);
$router->post('/guru/nilai/{assignment}/pengetahuan/kd', [TeacherKnowledgeAssessmentController::class, 'storeCompetency']);
$router->post('/guru/nilai/{assignment}/pengetahuan/kd/{competency}/hapus', [TeacherKnowledgeAssessmentController::class, 'deleteCompetency']);

$router->get('/guru/nilai/{assignment}/keterampilan', [TeacherSkillAssessmentController::class, 'index']);
$router->post('/guru/nilai/{assignment}/keterampilan/simpan', [TeacherSkillAssessmentController::class, 'storeScores']);
$router->post('/guru/nilai/{assignment}/keterampilan/kd', [TeacherSkillAssessmentController::class, 'storeCompetency']);
$router->post('/guru/nilai/{assignment}/keterampilan/kd/{competency}/hapus', [TeacherSkillAssessmentController::class, 'deleteCompetency']);

$router->get('/guru/presensi', [TeacherAttendanceController::class, 'index']);
$router->post('/guru/presensi', [TeacherAttendanceController::class, 'store']);
$router->get('/guru/presensi/rekap', [TeacherAttendanceRecapController::class, 'index']);
$router->get('/guru/presensi/rekap/export/pdf', [TeacherAttendanceRecapController::class, 'exportPdf']);
$router->get('/guru/presensi/{session}', [TeacherAttendanceController::class, 'show']);
$router->post('/guru/presensi/{session}/manual', [TeacherAttendanceController::class, 'storeManual']);
$router->post('/guru/presensi/{session}/tutup', [TeacherAttendanceController::class, 'close']);

// Wali Kelas - Presensi
$router->get('/walikelas/presensi', [HomeroomAttendanceController::class, 'index']);
$router->post('/walikelas/presensi', [HomeroomAttendanceController::class, 'store']);
$router->get('/walikelas/legger', [HomeroomLedgerController::class, 'index']);
$router->get('/walikelas/nilai-upload', [HomeroomGradeUploadController::class, 'index']);
$router->get('/walikelas/nilai-upload/assignments', [HomeroomGradeUploadController::class, 'assignmentsByClass']);
$router->get('/walikelas/nilai-upload/template', [HomeroomGradeUploadTemplateController::class, 'download']);
$router->post('/walikelas/nilai-upload/validate', [HomeroomGradeUploadController::class, 'validateUpload']);
$router->post('/walikelas/nilai-upload/finalize', [HomeroomGradeUploadController::class, 'finalizeDraft']);
$router->post('/walikelas/nilai-upload/reopen', [HomeroomGradeUploadController::class, 'reopenFinal']);
$router->get('/walikelas/nilai-upload/preview', [HomeroomGradeUploadController::class, 'preview']);
$router->post('/walikelas/nilai-upload/commit', [HomeroomGradeUploadController::class, 'commit']);
$router->post('/walikelas/nilai-upload/rollback', [HomeroomGradeUploadController::class, 'rollback']);
$router->get('/walikelas/nilai-upload/status', [HomeroomGradeUploadController::class, 'batchStatus']);
$router->get('/walikelas/nilai-upload/siswa', [HomeroomPerStudentGradeController::class, 'index']);
$router->get('/walikelas/nilai-upload/siswa/students', [HomeroomPerStudentGradeController::class, 'studentsByClass']);
$router->get('/walikelas/nilai-upload/siswa/template', [HomeroomPerStudentGradeController::class, 'downloadTemplate']);
$router->post('/walikelas/nilai-upload/siswa/validate', [HomeroomPerStudentGradeController::class, 'validateUpload']);
$router->get('/walikelas/transkrip', [HomeroomTranscriptController::class, 'index']);
$router->get('/walikelas/transkrip/cari-siswa', [HomeroomTranscriptController::class, 'searchStudentsAjax']);
$router->get('/walikelas/transkrip/cetak', [HomeroomTranscriptController::class, 'print']);
$router->get('/walikelas/transkrip/cetak-semua', [HomeroomTranscriptController::class, 'printAll']);
$router->post('/walikelas/transkrip/ttd-digital/request', [DigitalSignatureRequestController::class, 'requestTranscript']);
$router->post('/walikelas/transkrip/ttd-digital/request-class', [DigitalSignatureRequestController::class, 'requestTranscriptClass']);

// Manajemen Pengguna
$router->get('/admin/pengguna', [UserController::class, 'index']);
$router->post('/admin/pengguna', [UserController::class, 'store']);
$router->post('/admin/pengguna/template-whatsapp', [UserController::class, 'updateWhatsappTemplate']);
$router->post('/admin/pengguna/{id}/update', [UserController::class, 'update']);
$router->post('/admin/pengguna/{id}/reset-password', [UserController::class, 'resetPassword']);
$router->post('/admin/pengguna/{id}/whatsapp/default-password', [UserController::class, 'sendWhatsappDefaultPassword']);
$router->post('/admin/pengguna/{id}/whatsapp/reset-password', [UserController::class, 'sendWhatsappResetPassword']);
$router->post('/admin/pengguna/{id}/delete', [UserController::class, 'destroy']);
$router->get('/admin/user-rules', [UserRuleController::class, 'index']);
$router->post('/admin/user-rules', [UserRuleController::class, 'update']);
$router->get('/admin/cbt/export', [CbtExportController::class, 'index']);
$router->post('/admin/cbt/export/konfigurasi', [CbtExportController::class, 'store']);
$router->get('/admin/cbt/export/download', [CbtExportController::class, 'download']);
$router->get('/admin/cbt/export/photos', [CbtExportController::class, 'downloadPhotos']);
$router->get('/admin/log-aktivitas', [UserActivityLogController::class, 'index']);
$router->post('/admin/log-aktivitas/pengaturan', [UserActivityLogController::class, 'updateSetting']);
$router->get('/admin/demo-mode', [DemoModeController::class, 'index']);
$router->post('/admin/demo-mode', [DemoModeController::class, 'toggle']);
$router->get('/admin/maintenance-mode', [MaintenanceModeController::class, 'index']);
$router->post('/admin/maintenance-mode', [MaintenanceModeController::class, 'toggle']);
$router->get('/admin/pengaturan/sesi-login', [LoginSessionSettingController::class, 'index']);
$router->post('/admin/pengaturan/sesi-login', [LoginSessionSettingController::class, 'update']);
$router->get('/admin/periode-rescue-nilai', [GradeRescueWindowController::class, 'index']);
$router->post('/admin/periode-rescue-nilai', [GradeRescueWindowController::class, 'store']);
$router->post('/admin/periode-rescue-nilai/{id}/update', [GradeRescueWindowController::class, 'update']);
$router->post('/admin/periode-rescue-nilai/{id}/toggle', [GradeRescueWindowController::class, 'toggleStatus']);
$router->post('/admin/periode-rescue-nilai/{id}/delete', [GradeRescueWindowController::class, 'destroy']);

// Integrasi WhatsApp Gateway
$router->get('/admin/integrasi/whatsapp', [WhatsappGatewayController::class, 'index']);
$router->post('/admin/integrasi/whatsapp', [WhatsappGatewayController::class, 'update']);
$router->post('/admin/integrasi/whatsapp/test', [WhatsappGatewayController::class, 'test']);

// Ganti Password
$router->get('/profile/password', [PasswordController::class, 'edit']);
$router->post('/profile/password', [PasswordController::class, 'update']);
$router->get('/profile', [ProfileController::class, 'edit']);
$router->post('/profile', [ProfileController::class, 'update']);

// Admin Utilities
$router->get('/admin/salin-data-periodik', [PeriodicDataCopyController::class, 'index']);
$router->post('/admin/salin-data-periodik', [PeriodicDataCopyController::class, 'store']);
$router->get('/admin/clean-data/raport', [CleanRaportController::class, 'index']);
$router->post('/admin/clean-data/raport', [CleanRaportController::class, 'clean']);
$router->get('/admin/clean-data/keuangan', [CleanFinanceController::class, 'index']);
$router->post('/admin/clean-data/keuangan', [CleanFinanceController::class, 'clean']);
$router->get('/admin/clean-data/persuratan', [CleanLettersController::class, 'index']);
$router->post('/admin/clean-data/persuratan', [CleanLettersController::class, 'clean']);
$router->get('/admin/clean-data/ppdb', [CleanPpdbController::class, 'index']);
$router->post('/admin/clean-data/ppdb', [CleanPpdbController::class, 'clean']);
$router->get('/admin/backup-restore', [BackupRestoreController::class, 'index']);
$router->post('/admin/backup-restore/backup', [BackupRestoreController::class, 'create']);
$router->post('/admin/backup-restore/restore', [BackupRestoreController::class, 'restore']);
$router->get('/admin/backup-restore/download/{filename}', [BackupRestoreController::class, 'download']);
$router->get('/admin/update', [UpdateController::class, 'index']);
$router->post('/admin/update', [UpdateController::class, 'upload']);
$router->get('/admin/migrasi-rapor', [LegacyRaporMigrationController::class, 'index']);
$router->post('/admin/migrasi-rapor/import', [LegacyRaporMigrationController::class, 'import']);
$router->post('/admin/migrasi-rapor/migrate', [LegacyRaporMigrationController::class, 'migrate']);
$router->post('/admin/migrasi-rapor/hapus-legacy', [LegacyRaporMigrationController::class, 'drop']);
$router->get('/admin/clean-data/log', [CleanActivityLogController::class, 'index']);
$router->post('/admin/clean-data/log', [CleanActivityLogController::class, 'clean']);

// Cetak Raport
$router->get('/raport/tengah-semester', [ReportPrintController::class, 'midtermIndex']);
$router->get('/raport/tengah-semester/cetak', [ReportPrintController::class, 'midtermPrint']);

$router->get('/kartu-pelajar', [StudentCardController::class, 'index']);
$router->get('/kartu-pelajar/cetak', [StudentCardController::class, 'print']);
$router->get('/kartu-pelajar/verifikasi', [StudentCardController::class, 'verify']);
$router->get('/raport/cetak', [ReportPrintController::class, 'index']);
$router->get('/raport/cetak/cover', [ReportPrintController::class, 'cover']);
$router->get('/raport/cetak/informasi-sekolah', [ReportPrintController::class, 'schoolInfo']);
$router->get('/raport/cetak/biodata', [ReportPrintController::class, 'studentBio']);
$router->get('/raport/cetak/hasil-penilaian', [ReportPrintController::class, 'gradeSheet']);
$router->get('/raport/cetak/hasil-penilaian/kelas', [ReportPrintController::class, 'gradeSheetClass']);
$router->get('/raport/cetak/kelas/{section}', [ReportPrintController::class, 'printClassSection']);
$router->get('/raport/cetak/lengkap', [ReportPrintController::class, 'fullReport']);
$router->get('/raport/cetak/prestasi-catatan', [ReportPrintController::class, 'achievements']);
$router->get('/raport/cetak/hasil-penilaian-prestasi', [ReportPrintController::class, 'gradeAndAchievements']);
$router->get('/raport/cari-siswa', [ReportPrintController::class, 'searchStudentsAjax']);
