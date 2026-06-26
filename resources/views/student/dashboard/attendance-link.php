<div class="rounded-2xl border border-indigo-200 bg-indigo-50 px-5 py-4 text-sm text-indigo-700 shadow-sm">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Presensi QR</p>
            <h3 class="mt-1 text-base font-semibold text-indigo-800">Scan Presensi Kelas</h3>
            <p class="mt-1 text-xs text-indigo-600">
                Gunakan menu ini ketika guru menampilkan QR untuk presensi kelas.
            </p>
        </div>
        <i class="ri-qr-code-line text-3xl text-indigo-400"></i>
    </div>
    <a
        href="<?= htmlspecialchars(base_url('presensi/scan'), ENT_QUOTES, 'UTF-8') ?>"
        class="mt-4 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700"
    >
        <i class="ri-scan-line mr-2 text-sm"></i>
        Buka Halaman Presensi
    </a>
</div>
