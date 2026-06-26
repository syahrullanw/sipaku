<div class="relative mx-auto w-full max-w-4xl lg:mx-0">
    <div class="pointer-events-none absolute -top-16 left-1/2 h-56 w-56 -translate-x-1/2 rounded-full bg-indigo-200/50 blur-3xl sm:-top-20"></div>
    <div class="pointer-events-none absolute -bottom-20 right-6 hidden h-64 w-64 rounded-full bg-violet-200/40 blur-3xl lg:block"></div>

    <div class="relative overflow-hidden rounded-3xl border border-white/60 bg-white/90 shadow-xl shadow-indigo-100 backdrop-blur">
        <div class="pointer-events-none absolute -right-24 top-10 h-48 w-48 rounded-full bg-gradient-to-br from-indigo-200/70 via-violet-200/60 to-transparent blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-10 h-56 w-56 rounded-full bg-gradient-to-tr from-sky-200/60 via-indigo-200/30 to-transparent blur-3xl"></div>

        <div class="relative px-6 py-8 sm:px-10 sm:py-12">
            <div class="max-w-xl">
                <h2 class="text-2xl font-semibold text-slate-900 sm:text-3xl">Ganti Password</h2>
                <p class="mt-3 text-sm text-slate-500 sm:text-base">Masukkan password lama dan password baru Anda.</p>
            </div>

            <form action="<?= htmlspecialchars(base_url('profile/password'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-8 space-y-6">
                <?= csrf_field() ?>
                <div class="space-y-2">
                    <label for="old_password" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Password Lama</label>
                    <input
                        type="password"
                        id="old_password"
                        name="old_password"
                        required
                        class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50"
                    />
                </div>
                <div class="space-y-2">
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Password Baru</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        placeholder="Minimal 8 karakter"
                        class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50"
                    />
                </div>
                <div class="space-y-2">
                    <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Konfirmasi Password Baru</label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50"
                    />
                </div>
                <div class="flex items-center justify-end">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-500 via-violet-500 to-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:brightness-[1.08] focus:outline-none focus:ring-2 focus:ring-indigo-400/70 focus:ring-offset-2 focus:ring-offset-white"
                    >
                        Simpan Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
