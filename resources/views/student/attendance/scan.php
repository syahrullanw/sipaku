<?php
/**
 * ==========================
 *  SETUP & VARIABEL ASLI
 * ==========================
 */
$sessionData        = isset($sessionData) && is_array($sessionData) ? $sessionData : null;
$studentData        = isset($student) && is_array($student) ? $student : null;
$recordData         = isset($record) && is_array($record) ? $record : null;
$tokenValue         = isset($token) ? trim((string) $token) : '';
$isActiveSession    = isset($isActive) ? (bool) $isActive : false;
$alreadyRecordedFlag= isset($alreadyRecorded) ? (bool) $alreadyRecorded : false;
$errorMessageValue  = isset($errorMessage) && is_string($errorMessage) ? trim($errorMessage) : '';
$successFlashMessage= session_flash('success');
$errorFlashMessage  = session_flash('error');
$infoFlashMessage   = session_flash('info');

/** Formatters tetap sama */
$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') return '-';
    $ts = strtotime((string) $value);
    return $ts !== false ? date('d M Y', $ts) : '-';
};
$formatTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') return '-';
    $ts = strtotime((string) $value);
    return $ts !== false ? date('H:i', $ts) : '-';
};
$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') return '-';
    $ts = strtotime((string) $value);
    return $ts !== false ? date('d M Y H:i', $ts) : '-';
};

/** Label & status tetap sama */
$subjectName = $sessionData !== null ? trim((string) ($sessionData['mata_pelajaran_nama'] ?? '')) : '';
$subjectName = $subjectName !== '' ? $subjectName : 'Mata Pelajaran';

$teacherName = $sessionData !== null ? trim((string) ($sessionData['guru_nama'] ?? '')) : '';
$teacherName = $teacherName !== '' ? $teacherName : 'Guru Pengajar';

$classGrade  = $sessionData !== null ? trim((string) ($sessionData['kelas_tingkat'] ?? '')) : '';
$className   = $sessionData !== null ? trim((string) ($sessionData['kelas_nama'] ?? '')) : '';
$majorName   = $sessionData !== null ? trim((string) ($sessionData['jurusan_nama'] ?? '')) : '';
$parallelGrade = $sessionData !== null ? trim((string) ($sessionData['kelas_paralel_tingkat'] ?? '')) : '';
$parallelName = $sessionData !== null ? trim((string) ($sessionData['kelas_paralel_nama'] ?? '')) : '';
$parallelMajor = $sessionData !== null ? trim((string) ($sessionData['jurusan_paralel_nama'] ?? '')) : '';

$formatClassLabel = static function (string $grade, string $name, string $major): string {
    $label = '-';
    if ($grade !== '' || $name !== '') {
        $label = trim(sprintf('Kelas %s %s', $grade, $name));
    } elseif ($major !== '') {
        $label = $major;
    }
    if ($major !== '' && ($grade !== '' || $name !== '')) {
        $label = trim($label . ' (' . $major . ')');
    }

    return $label;
};

$classLabel = $formatClassLabel($classGrade, $className, $majorName);
$parallelLabel = $formatClassLabel($parallelGrade, $parallelName, $parallelMajor);

if ($parallelLabel !== '-') {
    if ($classLabel === '-') {
        $classLabel = $parallelLabel;
    } elseif ($parallelLabel !== $classLabel) {
        $classLabel = trim($classLabel . ' + ' . $parallelLabel);
    }
}

$agendaValue      = $sessionData !== null ? trim((string) ($sessionData['agenda'] ?? '')) : '';
$agendaValue      = $agendaValue !== '' ? $agendaValue : 'Tidak ada agenda khusus yang dicatat.';
$durationMinutes  = $sessionData !== null ? (int) ($sessionData['durasi_menit'] ?? 0) : 0;
$durationLabel    = $durationMinutes > 0 ? number_format($durationMinutes) . ' menit' : '-';

$sessionStatusKey   = $sessionData !== null ? (string) ($sessionData['status'] ?? '') : '';
$sessionStatusLabel = 'Sesi Ditutup';
$sessionStatusClass = 'border-slate-200 bg-slate-50 text-slate-700';
$sessionStatusIcon  = 'ri-lock-line';
if ($isActiveSession) {
    $sessionStatusLabel = 'Sesi Aktif';
    $sessionStatusClass = 'border-emerald-200 bg-emerald-50 text-emerald-700';
    $sessionStatusIcon  = 'ri-wifi-line';
} elseif ($sessionStatusKey === 'aktif') {
    $sessionStatusLabel = 'Sesi Kedaluwarsa';
    $sessionStatusClass = 'border-amber-200 bg-amber-50 text-amber-700';
    $sessionStatusIcon  = 'ri-time-line';
}

$tokenPreview   = $tokenValue !== '' ? strtoupper(substr($tokenValue, -6)) : '-';

$attendanceStatusLabel = '-';
if ($recordData !== null) {
    $currentStatus = (string) ($recordData['status'] ?? '');
    $statusMap = [
        'hadir' => 'Hadir',
        'izin'  => 'Izin',
        'sakit' => 'Sakit',
        'bolos' => 'Bolos',
        'alpa'  => 'Alpa',
    ];
    $attendanceStatusLabel = $statusMap[$currentStatus] ?? ucfirst($currentStatus);
}

$canShowForm   = $tokenValue !== '' && $sessionData !== null && $studentData !== null && $errorMessageValue === '';
$formActionUrl = $tokenValue !== '' ? base_url('presensi/scan/' . rawurlencode($tokenValue)) : base_url('presensi/scan');
$submitLabel   = $alreadyRecordedFlag ? 'Perbarui Presensi' : 'Catat Presensi';
$basePathAttribute = base_url('');
$basePathAttribute = $basePathAttribute === '/' ? '' : rtrim($basePathAttribute, '/');
$scanMode = $tokenValue === '' ? 'scan' : 'detail';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Presensi Siswa via QR</title>
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body{
    background: linear-gradient(180deg,#eef2ff 0%,#f8fafc 38%,#f1f5f9 100%);
    min-height:100vh; font-family:Inter,ui-sans-serif,system-ui;
  }
  .glass{
    background: rgba(255,255,255,0.92);
    border:1px solid rgba(226,232,240,0.95);
    border-radius: 22px;
    box-shadow: 0 22px 55px rgba(15,23,42,0.08);
  }
  .header-grad{ background: linear-gradient(135deg,#3730a3 0%,#4f46e5 58%,#2563eb 100%); }
  .badge{ font-size:11px; letter-spacing:.16em; }
  .fade-in{ animation:fade .4s ease-out; } @keyframes fade{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
  .btn-primary{
    @apply inline-flex w-full items-center justify-center gap-3 rounded-2xl bg-indigo-600 px-6 py-4 text-base font-semibold text-white shadow-lg ring-1 ring-indigo-500/30 transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white disabled:opacity-60 disabled:pointer-events-none;
  }
  
  /* SCANNER UI ENHANCEMENTS */
  #qr-video {
      display: block; width: 100%; height: 100%; object-fit: cover;
      transform: scaleX(-1); /* Opsional: Mirror effect, hapus jika kamera belakang */
  }
  .scanner-overlay {
      box-shadow: 0 0 0 999px rgba(15, 23, 42, 0.5);
  }
  /* Efek Sudut Pembidik */
  .scan-corner {
      position: absolute; width: 40px; height: 40px; border-color: #6366f1; border-style: solid;
  }
  .tl { top: 0; left: 0; border-width: 4px 0 0 4px; border-top-left-radius: 12px; }
  .tr { top: 0; right: 0; border-width: 4px 4px 0 0; border-top-right-radius: 12px; }
  .bl { bottom: 0; left: 0; border-width: 0 0 4px 4px; border-bottom-left-radius: 12px; }
  .br { bottom: 0; right: 0; border-width: 0 4px 4px 0; border-bottom-right-radius: 12px; }
  
  /* Efek Laser Animasi */
  .scan-laser {
      position: absolute; left: 5%; right: 5%; height: 2px;
      background: #4f46e5;
      box-shadow: 0 0 10px #4f46e5, 0 0 20px #818cf8;
      animation: scan 2.5s cubic-bezier(0.4, 0, 0.2, 1) infinite alternate;
  }
  @keyframes scan {
      0% { top: 5%; }
      100% { top: 95%; }
  }

  /* Status Colors */
  #qr-status { transition: all .3s ease; }
  #qr-status[data-state="success"] { @apply bg-emerald-500/90 text-white ring-1 ring-emerald-400; }
  #qr-status[data-state="error"] { @apply bg-rose-500/90 text-white ring-1 ring-rose-400; }
  #qr-status[data-state="warning"] { @apply bg-amber-500/90 text-white ring-1 ring-amber-400; }
  #qr-status[data-state="info"] { @apply bg-indigo-500/90 text-white ring-1 ring-indigo-400; }
  #qr-status[data-state="default"] { @apply bg-slate-800/80 text-white ring-1 ring-white/20; }
</style>
</head>
<body class="px-3 py-5 sm:px-6 sm:py-8 lg:px-8" data-base-path="<?= htmlspecialchars($basePathAttribute, ENT_QUOTES, 'UTF-8') ?>" data-scan-mode="<?= htmlspecialchars($scanMode, ENT_QUOTES, 'UTF-8') ?>">
  <div class="mx-auto w-full max-w-5xl fade-in">
    <div class="overflow-hidden glass">

      <!-- HEADER -->
      <div class="header-grad px-5 py-6 text-white sm:px-8 sm:py-7">
        <div class="mb-5 flex justify-start">
          <button
            type="button"
            id="attendance-back-button"
            data-fallback-url="<?= htmlspecialchars(base_url('dashboard'), ENT_QUOTES, 'UTF-8') ?>"
            class="inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.28em] text-white transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/60 focus:ring-offset-2 focus:ring-offset-indigo-500/20"
          >
            <i class="ri-arrow-left-line text-base"></i>
            <span>Kembali</span>
          </button>
        </div>
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-indigo-200">Presensi Siswa via QR</p>
            <h1 class="mt-1 text-2xl font-bold leading-tight sm:text-3xl">Scan Presensi Kelas</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-indigo-100 hidden sm:block">
              Pastikan Anda telah login sebagai siswa. Setelah memindai QR dari guru, tinjau detail sesi di bawah ini lalu catat presensi Anda.
            </p>
          </div>
          <?php if ($tokenValue !== ''): ?>
          <div class="rounded-2xl border border-white/30 bg-white/10 px-5 py-4 text-sm font-medium backdrop-blur">
            <span class="block text-[10px] uppercase tracking-[0.2em] text-indigo-100">Token Presensi</span>
            <span class="mt-1 inline-flex items-center gap-2 text-lg font-bold">
              <?= htmlspecialchars($tokenPreview, ENT_QUOTES, 'UTF-8') ?>
              <span class="text-[10px] font-medium uppercase tracking-[0.2em] text-indigo-200">akhir token</span>
            </span>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- BODY -->
      <div class="space-y-6 px-4 py-6 sm:px-8 sm:py-8">

        <!-- FLASH MESSAGES -->
        <?php if (!empty($successFlashMessage)): ?>
          <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-700 shadow-sm">
            <?= htmlspecialchars($successFlashMessage, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($errorFlashMessage)): ?>
          <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700 shadow-sm">
            <?= htmlspecialchars($errorFlashMessage, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($infoFlashMessage)): ?>
          <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-700 shadow-sm">
            <?= htmlspecialchars($infoFlashMessage, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <!-- TANPA TOKEN (MODE SCAN PRIORITAS) -->
        <?php if ($tokenValue === ''): ?>
          <div id="qr-scanner" class="flex flex-col items-center max-w-2xl mx-auto w-full space-y-6">
            
            <!-- Judul & Tombol Retry -->
            <div class="text-center w-full flex items-center justify-between sm:justify-center gap-4">
              <div class="sm:text-center text-left">
                 <h2 class="text-2xl font-bold text-slate-800">Arahkan Kamera ke QR</h2>
                 <p class="text-sm text-slate-500 mt-1">Pindai kode QR presensi yang ditampilkan oleh Guru</p>
              </div>
              <button
                type="button"
                id="qr-retry"
                class="hidden rounded-xl bg-indigo-100 px-4 py-2 text-xs font-bold text-indigo-700 transition hover:bg-indigo-200"
              >
                <i class="ri-refresh-line mr-1"></i> Ulang
              </button>
            </div>

            <!-- Kamera Area Utama -->
            <div class="relative w-full aspect-[3/4] sm:aspect-square md:aspect-[4/3] overflow-hidden rounded-[32px] bg-slate-900 shadow-2xl ring-4 ring-indigo-50/50">
              <video id="qr-video" autoplay muted playsinline></video>
              
              <!-- Dark Overlay with transparent center -->
              <div class="pointer-events-none absolute inset-6 sm:inset-12 rounded-3xl scanner-overlay transition-all"></div>
              
              <!-- Viewfinder Container -->
              <div class="pointer-events-none absolute inset-6 sm:inset-12 z-10">
                 <!-- Corners -->
                 <div class="scan-corner tl"></div>
                 <div class="scan-corner tr"></div>
                 <div class="scan-corner bl"></div>
                 <div class="scan-corner br"></div>
                 <!-- Laser Animasi -->
                 <div class="scan-laser"></div>
              </div>

              <!-- Status Mengambang -->
              <p id="qr-status" data-state="info" class="absolute bottom-8 left-1/2 -translate-x-1/2 rounded-full px-5 py-2.5 text-xs font-semibold backdrop-blur-md shadow-lg z-20 flex items-center gap-2 max-w-[85%] text-center leading-tight">
                <i class="ri-loader-4-line animate-spin text-lg"></i> Menyiapkan kamera...
              </p>
            </div>

            <p id="qr-unsupported" class="hidden w-full text-center rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
              Browser belum mendukung pemindaian otomatis. Gunakan aplikasi kamera bawaan HP Anda.
            </p>

            <!-- Instruksi Minimalis di Bawah -->
            <div class="grid grid-cols-2 gap-3 w-full text-sm mt-4">
              <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-center">
                <i class="ri-smartphone-line text-2xl text-indigo-500 mb-2 block"></i>
                <p class="font-semibold text-slate-700">1. Izinkan Kamera</p>
                <p class="mt-1 text-[11px] text-slate-500">Pilih "Allow" saat browser meminta akses.</p>
              </div>
              <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-center">
                <i class="ri-qr-scan-2-line text-2xl text-emerald-500 mb-2 block"></i>
                <p class="font-semibold text-slate-700">2. Pindai Kode</p>
                <p class="mt-1 text-[11px] text-slate-500">Layar akan berpindah otomatis jika berhasil.</p>
              </div>
            </div>

          </div>

        <!-- SESI TIDAK DITEMUKAN -->
        <?php elseif ($sessionData === null): ?>
          <div class="rounded-3xl border border-rose-200 bg-rose-50 px-6 py-5 text-sm text-rose-700 shadow-sm sm:px-8">
            <div class="flex items-start gap-3">
              <i class="ri-error-warning-line text-xl text-rose-500"></i>
              <div>
                <h2 class="text-base font-semibold text-rose-800">
                  <?= htmlspecialchars($errorMessageValue !== '' ? 'Presensi Tidak Dapat Dilanjutkan' : 'Sesi Presensi Tidak Ditemukan', ENT_QUOTES, 'UTF-8') ?>
                </h2>
                <p class="mt-1">
                  <?= htmlspecialchars($errorMessageValue !== '' ? $errorMessageValue : 'QR presensi ini tidak dikenali atau sudah tidak berlaku.', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php if ($errorMessageValue === ''): ?>
                  <p class="mt-3 text-xs text-rose-600">Pastikan Anda memindai QR terbaru yang ditampilkan guru.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>

        <!-- SESI DITEMUKAN (DETAIL MODE) -->
        <?php else: ?>

          <!-- ERROR DI ATAS FORM -->
          <?php if ($errorMessageValue !== ''): ?>
            <div class="rounded-3xl border border-rose-200 bg-rose-50 px-6 py-5 text-sm text-rose-700 shadow-sm sm:px-8">
              <div class="flex items-start gap-3">
                <i class="ri-error-warning-line text-xl text-rose-500"></i>
                <div>
                  <h2 class="text-base font-semibold text-rose-800">Presensi Tidak Dapat Dilanjutkan</h2>
                  <p class="mt-1"><?= htmlspecialchars($errorMessageValue, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <!-- DETAIL SESI -->
          <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 class="text-2xl font-semibold text-slate-800">Detail Sesi Presensi</h2>
                <p class="text-sm text-slate-500">Pastikan informasi sesi sudah sesuai sebelum mencatat kehadiran.</p>
              </div>
              <span class="inline-flex items-center justify-center gap-2 rounded-full border <?= $sessionStatusClass ?> px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.22em]">
                <i class="<?= $sessionStatusIcon ?>"></i><?= htmlspecialchars($sessionStatusLabel, ENT_QUOTES, 'UTF-8') ?>
              </span>
            </header>

            <dl class="grid gap-4 text-sm md:grid-cols-2">
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                <dt class="text-[11px] font-semibold uppercase tracking-[0.25em] text-slate-500">Mata Pelajaran</dt>
                <dd class="mt-2 text-lg font-semibold leading-snug text-slate-800"><?= htmlspecialchars($subjectName, ENT_QUOTES, 'UTF-8') ?></dd>
                <dd class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') ?></dd>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                <dt class="text-[11px] font-semibold uppercase tracking-[0.25em] text-slate-500">Kelas</dt>
                <dd class="mt-2 text-lg font-semibold leading-snug text-slate-800"><?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                <dd class="mt-1 text-xs text-slate-500">Tanggal <?= htmlspecialchars($formatDate($sessionData['tanggal'] ?? null), ENT_QUOTES, 'UTF-8') ?></dd>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                <dt class="text-[11px] font-semibold uppercase tracking-[0.25em] text-slate-500">Waktu Sesi</dt>
                <dd class="mt-2 text-base text-slate-700">Mulai: <?= htmlspecialchars($formatTime($sessionData['waktu_mulai'] ?? $sessionData['valid_dari'] ?? null), ENT_QUOTES, 'UTF-8') ?></dd>
                <dd class="mt-1 text-base text-slate-700">Selesai: <?= htmlspecialchars($formatTime($sessionData['waktu_selesai'] ?? $sessionData['valid_sampai'] ?? null), ENT_QUOTES, 'UTF-8') ?></dd>
                <dd class="mt-2 text-[11px] uppercase tracking-[0.2em] text-slate-500">Durasi <?= htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8') ?></dd>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                <dt class="text-[11px] font-semibold uppercase tracking-[0.25em] text-slate-500">Berlaku Sampai</dt>
                <dd class="mt-2 text-base font-medium text-slate-700"><?= htmlspecialchars($formatDateTime($sessionData['valid_sampai'] ?? null), ENT_QUOTES, 'UTF-8') ?></dd>
                <dd class="mt-2 text-xs text-slate-500">Token: <?= htmlspecialchars($tokenValue !== '' ? substr($tokenValue, 0, 6) . '...' : '-', ENT_QUOTES, 'UTF-8') ?></dd>
              </div>
            </dl>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-700">
              <p class="font-medium text-slate-800">Agenda Sesi</p>
              <p class="mt-1 leading-relaxed"><?= nl2br(htmlspecialchars($agendaValue, ENT_QUOTES, 'UTF-8')) ?></p>
            </div>
          </section>

          <!-- DATA SISWA + FORM -->
          <section class="grid gap-5 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
            <div class="rounded-3xl border border-indigo-100 bg-indigo-50 p-5 text-indigo-800 shadow-sm sm:p-6">
              <h3 class="text-[12px] font-semibold uppercase tracking-[0.22em] text-indigo-500">Data Kamu</h3>
              <?php if ($studentData !== null): ?>
                <dl class="mt-3 space-y-2 text-sm">
                  <div>
                    <dt class="text-[11px] uppercase tracking-[0.2em] text-indigo-400">Nama</dt>
                    <dd class="mt-1 text-base font-semibold text-indigo-900">
                      <?= htmlspecialchars((string) ($studentData['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                      <?= student_status_badge($studentData, 'ml-1 align-middle') ?>
                      <?= student_dapodik_badge($studentData, 'ml-1 align-middle') ?>
                    </dd>
                  </div>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                      <dt class="text-[11px] uppercase tracking-[0.2em] text-indigo-400">NIPD</dt>
                      <dd class="mt-1 text-sm font-medium"><?= htmlspecialchars((string) ($studentData['nipd'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div>
                      <dt class="text-[11px] uppercase tracking-[0.2em] text-indigo-400">NISN</dt>
                      <dd class="mt-1 text-sm font-medium"><?= htmlspecialchars((string) ($studentData['nisn'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                  </div>
                  <div>
                    <dt class="text-[11px] uppercase tracking-[0.2em] text-indigo-400">Kelas Saat Ini</dt>
                    <dd class="mt-1 text-sm font-medium"><?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                  </div>
                </dl>
              <?php else: ?>
                <p class="mt-3 text-sm">Data siswa tidak ditemukan. Hubungi admin sekolah.</p>
              <?php endif; ?>
            </div>

            <div class="space-y-4">
              <?php if ($recordData !== null): ?>
                <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-700 shadow-sm">
                  <div class="flex items-start gap-3">
                    <i class="ri-check-double-line text-xl text-emerald-500"></i>
                    <div>
                      <p class="text-sm font-semibold text-emerald-900">Presensi sudah tercatat</p>
                      <p class="mt-1 text-[11px] uppercase tracking-[0.28em] text-emerald-600">Status: <?= htmlspecialchars($attendanceStatusLabel, ENT_QUOTES, 'UTF-8') ?></p>
                      <p class="mt-1 text-xs text-emerald-600">
                        Dicatat pada <?= htmlspecialchars($formatDateTime($recordData['presensi_pada'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                        melalui <?= htmlspecialchars((string) ($recordData['metode'] ?? 'qr'), ENT_QUOTES, 'UTF-8') ?>.
                      </p>
                      <p class="mt-2 text-xs text-emerald-600">Kamu bisa memperbarui presensi dengan tombol di bawah jika diminta oleh guru.</p>
                    </div>
                  </div>
                </div>
              <?php endif; ?>

              <?php if (!$isActiveSession): ?>
                <div class="rounded-3xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-700 shadow-sm">
                  <div class="flex items-start gap-3">
                    <i class="ri-hourglass-2-line text-xl text-amber-500"></i>
                    <div>
                      <p class="text-sm font-semibold text-amber-900">Sesi Presensi Tidak Aktif</p>
                      <p class="mt-1 text-xs text-amber-700">Presensi hanya dapat dicatat saat sesi masih aktif.</p>
                    </div>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($canShowForm): ?>
                <div class="rounded-3xl border-2 border-indigo-100 bg-white p-5 shadow-sm sm:p-7">
                  <form id="student-attendance-form" action="<?= htmlspecialchars($formActionUrl, ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-5">
                    <?= csrf_field() ?>
                    <input type="hidden" name="latitude" id="attendance-latitude" />
                    <input type="hidden" name="longitude" id="attendance-longitude" />

                    <div class="rounded-2xl bg-indigo-50 px-5 py-4 text-indigo-900">
                      <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-indigo-500">Konfirmasi Presensi</p>
                      <p class="mt-2 text-lg font-semibold">Siap mencatat kehadiran</p>
                      <p class="mt-1 text-sm leading-relaxed text-indigo-700">Pastikan detail sesi dan data siswa sudah benar sebelum menekan tombol presensi.</p>
                    </div>

                    <div id="location-permission" class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-600">
                      <div class="flex items-start gap-3">
                        <i class="ri-map-pin-line mt-0.5 text-xl text-slate-400"></i>
                        <div class="min-w-0 flex-1">
                          <p class="font-semibold text-slate-700">Perizinan Lokasi</p>
                          <p id="location-status" class="mt-1 text-xs text-slate-600">
                            Sistem akan meminta lokasi Anda untuk memastikan berada di area sekolah. Izinkan akses lokasi saat diminta.
                          </p>
                          <button type="button" id="location-retry" class="mt-3 inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-300">
                            <i class="ri-refresh-line text-sm"></i> Coba Lagi
                          </button>
                        </div>
                      </div>
                    </div>

                    <div class="space-y-3">
                      <p class="text-sm text-slate-600">Tekan tombol besar berikut untuk mencatat kehadiran Anda.</p>
                      <button type="submit" id="attendance-submit" class="btn-primary" <?php if (!$isActiveSession): ?>disabled<?php endif; ?>>
                        <i class="ri-qr-scan-2-line text-xl"></i>
                        <span data-default-label><?= htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8') ?></span>
                      </button>
                    </div>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          </section>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- JS: Geolocation (dipertahankan fungsinya lengkap) -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const basePathAttr = document.body.getAttribute("data-base-path") || "";
      const backButton = document.getElementById("attendance-back-button");
      if (backButton) {
        backButton.addEventListener("click", () => {
          const fallback = backButton.getAttribute("data-fallback-url") || (basePathAttr || "/");
          if (document.referrer && document.referrer !== window.location.href) {
            window.history.back();
          } else {
            window.location.href = fallback;
          }
        });
      }

      const scannerContainer = document.getElementById("qr-scanner");
      if (scannerContainer) {
        const video = document.getElementById("qr-video");
        const statusEl = document.getElementById("qr-status");
        const retryBtn = document.getElementById("qr-retry");
        const unsupportedNotice = document.getElementById("qr-unsupported");
        if (video && statusEl && retryBtn && unsupportedNotice) {
          let stream = null;
          let detector = null;
          let scanning = false;
          let starting = false;
          let strategy = "barcode";
          let animationFrameId = null;
          let jsQrLoader = null;
          const canvas = document.createElement("canvas");
          const context = canvas.getContext("2d", { willReadFrequently: true });

          function setStatus(message, state) {
            // Menambahkan icon spinner khusus untuk loading
            let icon = '<i class="ri-information-line text-lg"></i>';
            if(state === 'success') icon = '<i class="ri-checkbox-circle-line text-lg"></i>';
            if(state === 'error' || state === 'warning') icon = '<i class="ri-error-warning-line text-lg"></i>';
            if(message.includes("Menyiapkan") || message.includes("Meminta")) icon = '<i class="ri-loader-4-line animate-spin text-lg"></i>';
            
            statusEl.innerHTML = icon + " " + message;
            statusEl.dataset.state = state || "default";
          }

          function cleanupStream() {
            if (video) {
              const currentStream = video.srcObject;
              if (currentStream && typeof currentStream.getTracks === "function") {
                currentStream.getTracks().forEach((track) => track.stop());
              }
              video.srcObject = null;
              try {
                video.pause();
              } catch (error) {
                void error;
              }
            }
            if (stream) {
              stream.getTracks().forEach((track) => track.stop());
              stream = null;
            }
          }

          function stopScanner() {
            scanning = false;
            if (animationFrameId !== null) {
              cancelAnimationFrame(animationFrameId);
              animationFrameId = null;
            }
            cleanupStream();
          }

          function buildTokenUrl(token) {
            const normalizedToken = (token || "").trim();
            if (normalizedToken === "") return null;
            const cleanToken = normalizedToken.replace(/[^a-zA-Z0-9]/g, "");
            if (cleanToken.length < 6) return null;
            const normalizedBase = basePathAttr === "/" ? "" : basePathAttr;
            return normalizedBase + "/presensi/scan/" + encodeURIComponent(cleanToken);
          }

          function resolveTarget(rawValue) {
            const raw = (rawValue || "").trim();
            if (raw === "") return null;

            const origin = window.location.origin;
            try {
              const parsed = new URL(raw, origin);
              if (parsed.pathname.includes("/presensi/scan/")) {
                return parsed.href;
              }
              const queryToken = parsed.searchParams.get("token");
              if (queryToken) {
                const tokenUrl = buildTokenUrl(queryToken);
                if (tokenUrl) {
                  return tokenUrl;
                }
              }
            } catch (error) {
              void error;
            }

            if (raw.startsWith("/presensi/scan/") || raw.startsWith("presensi/scan/")) {
              const parts = raw.split("/").filter(Boolean);
              const tokenPart = parts.pop();
              if (tokenPart) {
                const tokenUrl = buildTokenUrl(tokenPart);
                if (tokenUrl) {
                  return tokenUrl;
                }
              }
            }

            const tokenMatch = raw.match(/([a-f0-9]{32})/i);
            if (tokenMatch) {
              const tokenUrl = buildTokenUrl(tokenMatch[1]);
              if (tokenUrl) {
                return tokenUrl;
              }
            }

            return null;
          }

          function handleDetected(value) {
            const target = resolveTarget(value);
            if (!target) {
              setStatus("QR tidak sesuai.", "warning");
              return;
            }
            setStatus("QR Dikenali! Mengalihkan...", "success");
            stopScanner();
            setTimeout(() => {
              window.location.href = target;
            }, 450);
          }

          function loadJsQr() {
            if (typeof window.jsQR === "function") {
              return Promise.resolve(true);
            }
            if (jsQrLoader) {
              return jsQrLoader;
            }
            jsQrLoader = new Promise((resolve) => {
              const script = document.createElement("script");
              script.src = "https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js";
              script.async = true;
              script.onload = () => resolve(typeof window.jsQR === "function");
              script.onerror = () => resolve(false);
              document.head.appendChild(script);
            });
            return jsQrLoader;
          }

          async function ensureDetector() {
            if ("BarcodeDetector" in window) {
              try {
                detector = new window.BarcodeDetector({ formats: ["qr_code"] });
                strategy = "barcode";
                return true;
              } catch (error) {
                detector = null;
              }
            }
            const loaded = await loadJsQr();
            if (loaded && context) {
              strategy = "jsqr";
              return true;
            }
            return false;
          }

          async function scanFrame() {
            if (!scanning) {
              return;
            }

            if (video.readyState >= HTMLMediaElement.HAVE_ENOUGH_DATA) {
              if (strategy === "barcode" && detector) {
                try {
                  const barcodes = await detector.detect(video);
                  if (Array.isArray(barcodes)) {
                    const match = barcodes.find((item) => item && item.rawValue);
                    if (match && match.rawValue) {
                      handleDetected(match.rawValue);
                      return;
                    }
                  }
                } catch (error) {
                  console.warn("Barcode detection error:", error);
                }
              } else if (strategy === "jsqr" && typeof window.jsQR === "function" && context) {
                if (video.videoWidth > 0 && video.videoHeight > 0) {
                  canvas.width = video.videoWidth;
                  canvas.height = video.videoHeight;
                  context.drawImage(video, 0, 0, canvas.width, canvas.height);
                  const image = context.getImageData(0, 0, canvas.width, canvas.height);
                  const result = window.jsQR(image.data, canvas.width, canvas.height, { inversionAttempts: "dontInvert" });
                  if (result && result.data) {
                    handleDetected(result.data);
                    return;
                  }
                }
              }
            }

            animationFrameId = window.requestAnimationFrame(scanFrame);
          }

          async function startScanner() {
            if (scanning || starting) {
              return;
            }
            starting = true;
            retryBtn.classList.add("hidden");
            unsupportedNotice.classList.add("hidden");
            setStatus("Meminta izin kamera...", "info");

            const hasMedia = navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === "function";
            if (!hasMedia) {
              setStatus("Kamera tidak didukung.", "error");
              unsupportedNotice.classList.remove("hidden");
              starting = false;
              return;
            }

            const detectorReady = await ensureDetector();
            if (!detectorReady) {
              setStatus("Pemindai tidak didukung.", "error");
              unsupportedNotice.classList.remove("hidden");
              starting = false;
              return;
            }

            try {
              stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: "environment" } },
                audio: false,
              });
              video.srcObject = stream;
              await video.play();
              scanning = true;
              setStatus("Arahkan kamera ke QR", "info");
              animationFrameId = window.requestAnimationFrame(scanFrame);
            } catch (error) {
              console.error("Camera error:", error);
              let message = "Kamera gagal diakses.";
              if (error && typeof error.name === "string") {
                if (error.name === "NotAllowedError") {
                  message = "Izin kamera ditolak.";
                } else if (error.name === "NotFoundError") {
                  message = "Kamera tidak ditemukan.";
                }
              }
              setStatus(message, "error");
              retryBtn.classList.remove("hidden");
              cleanupStream();
            } finally {
              starting = false;
            }
          }

          retryBtn.addEventListener("click", () => {
            stopScanner();
            startScanner();
          });

          startScanner();

          document.addEventListener("visibilitychange", () => {
            if (document.hidden) {
              stopScanner();
            } else if (!scanning) {
              startScanner();
            }
          });

          window.addEventListener("pagehide", stopScanner);
          window.addEventListener("beforeunload", stopScanner);
        }
      }

      const form = document.getElementById("student-attendance-form");
      if (!form) return;

      const latField = document.getElementById("attendance-latitude");
      const lngField = document.getElementById("attendance-longitude");
      const statusEl = document.getElementById("location-status");
      const locationRetryBtn = document.getElementById("location-retry");
      const container = document.getElementById("location-permission");
      const submitBtn = document.getElementById("attendance-submit");
      const defaultLabel = submitBtn ? submitBtn.querySelector("[data-default-label]") : null;

      function setState(type, message){
        container.classList.remove(
          "border-slate-200","bg-slate-50","text-slate-600",
          "border-emerald-200","bg-emerald-50","text-emerald-600",
          "border-amber-200","bg-amber-50","text-amber-600",
          "border-rose-200","bg-rose-50","text-rose-600"
        );
        if(type==="success") container.classList.add("border-emerald-200","bg-emerald-50","text-emerald-600");
        else if(type==="warning") container.classList.add("border-amber-200","bg-amber-50","text-amber-600");
        else if(type==="error") container.classList.add("border-rose-200","bg-rose-50","text-rose-600");
        else container.classList.add("border-slate-200","bg-slate-50","text-slate-600");
        statusEl.textContent = message;
      }

      function handleSuccess(pos){
        const {latitude, longitude} = pos.coords;
        latField.value = latitude.toFixed(6);
        lngField.value = longitude.toFixed(6);
        setState("success","Lokasi berhasil diperoleh. Anda dapat melanjutkan presensi.");
        locationRetryBtn.classList.add("hidden");
      }
      function handleError(err){
        let msg = "Tidak dapat mengambil lokasi. Aktifkan layanan lokasi dan coba lagi.";
        if (err.code === err.PERMISSION_DENIED) msg = "Izin lokasi ditolak. Izinkan melalui pengaturan perangkat Anda.";
        else if (err.code === err.POSITION_UNAVAILABLE) msg = "Lokasi tidak tersedia. Pastikan GPS aktif.";
        else if (err.code === err.TIMEOUT) msg = "Permintaan lokasi melewati batas waktu. Coba lagi.";
        setState("error", msg);
        locationRetryBtn.classList.remove("hidden");
      }
      function requestLocation(){
        if (!navigator.geolocation){
          setState("warning","Perangkat ini tidak mendukung layanan lokasi.");
          locationRetryBtn.classList.add("hidden");
          return;
        }
        setState("default","Meminta lokasi perangkat...");
        navigator.geolocation.getCurrentPosition(handleSuccess, handleError, {enableHighAccuracy:true, maximumAge:60000, timeout:10000});
      }

      locationRetryBtn.addEventListener("click", ()=>{ locationRetryBtn.classList.add("hidden"); requestLocation(); });
      requestLocation();

      form.addEventListener("submit", ()=>{
        if(submitBtn && defaultLabel){
          submitBtn.disabled = true;
          submitBtn.classList.add("opacity-75");
          defaultLabel.textContent = "Memproses...";
        }
      });
    });
  </script>
</body>
</html>
