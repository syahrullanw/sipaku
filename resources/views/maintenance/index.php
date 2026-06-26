<?php
    $branding = app_branding();
    $logoUrl = app_logo_asset();
?>

<section class="rounded-3xl border border-slate-200 bg-white px-6 py-8 text-center shadow-xl">
    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-50 shadow-sm">
        <img
            src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>"
            alt="Logo <?= htmlspecialchars($branding['name'], ENT_QUOTES, 'UTF-8') ?>"
            class="h-14 w-14 object-contain"
        />
    </div>
    <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-indigo-600">Maintenance Mode</p>
    <h1 class="mt-2 text-2xl font-bold text-slate-800">Aplikasi sedang dalam pemeliharaan</h1>
    <p class="mt-3 text-sm leading-6 text-slate-500">
        Sistem sedang diperbaiki atau diuji oleh admin. Silakan coba kembali beberapa saat lagi.
    </p>
    <a
        href="<?= htmlspecialchars(base_url('login'), ENT_QUOTES, 'UTF-8') ?>"
        class="mt-6 inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
    >
        Masuk Admin
    </a>
</section>
