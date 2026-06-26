<!DOCTYPE html>
<html lang="id" class="h-full">
    <head>
        <?php
            $appleIconHref = app_icon_asset('icons/apple-touch-icon.png');
            $icon192Href = app_icon_asset('icons/icon-192.png');
            $icon512Href = app_icon_asset('icons/icon-512.png');
        ?>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link rel="manifest" href="<?= htmlspecialchars(asset('manifest.webmanifest'), ENT_QUOTES, 'UTF-8') ?>" />
        <meta name="theme-color" content="#4f46e5" />
        <meta name="application-name" content="<?= htmlspecialchars(config('app.name'), ENT_QUOTES, 'UTF-8') ?>" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
        <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars(config('app.name'), ENT_QUOTES, 'UTF-8') ?>" />
        <meta name="mobile-web-app-capable" content="yes" />
        <link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($appleIconHref, ENT_QUOTES, 'UTF-8') ?>" />
        <link rel="icon" type="image/png" sizes="192x192" href="<?= htmlspecialchars($icon192Href, ENT_QUOTES, 'UTF-8') ?>" />
        <link rel="icon" type="image/png" sizes="512x512" href="<?= htmlspecialchars($icon512Href, ENT_QUOTES, 'UTF-8') ?>" />
        <title><?= htmlspecialchars(($title ?? 'Panel') . ' - ' . config('app.name'), ENT_QUOTES, 'UTF-8') ?></title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" />
        <script>
            (function () {
                const storageKey = "theme-preference";
                const root = document.documentElement;
                const supported = new Set(["light", "dark", "system"]);
                const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");

                const getStored = () => {
                    try {
                        const value = localStorage.getItem(storageKey);
                        return supported.has(value) ? value : null;
                    } catch (_) {
                        return null;
                    }
                };

                let preference = getStored() ?? "system";

                const resolveTheme = () => {
                    if (preference === "system") {
                        return mediaQuery.matches ? "dark" : "light";
                    }
                    return preference;
                };

                const applyTheme = (theme) => {
                    if (theme === "dark") {
                        root.classList.add("dark");
                    } else {
                        root.classList.remove("dark");
                    }
                    root.style.colorScheme = theme === "dark" ? "dark" : "light";
                    root.dataset.themeApplied = theme;
                };

                const persistPreference = (value) => {
                    preference = supported.has(value) ? value : "system";
                    root.dataset.themePreference = preference;
                    try {
                        localStorage.setItem(storageKey, preference);
                    } catch (_) {
                        // Ignore storage errors
                    }
                    applyTheme(resolveTheme());
                };

                root.dataset.themePreference = preference;
                applyTheme(resolveTheme());

                window.__setThemePreference = persistPreference;

                const handleSystemChange = () => {
                    if (preference === "system") {
                        applyTheme(resolveTheme());
                    }
                };

                if (typeof mediaQuery.addEventListener === "function") {
                    mediaQuery.addEventListener("change", handleSystemChange);
                } else if (typeof mediaQuery.addListener === "function") {
                    mediaQuery.addListener(handleSystemChange);
                }
            })();
        </script>
        <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
        <script>
            tailwind.config = {
                darkMode: "class",
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ["Inter", "-apple-system", "BlinkMacSystemFont", "Segoe UI", "sans-serif"],
                        },
                    },
                },
            };
        </script>
        <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/admin.css'), ENT_QUOTES, 'UTF-8') ?>" />
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                const toggleButton = document.querySelector("[data-sidebar-toggle]");
                const sidebar = document.querySelector("#sidebar");
                const overlay = document.querySelector("[data-sidebar-overlay]");
                const body = document.body;

                const openSidebar = () => {
                    if (!sidebar) {
                        return;
                    }
                    sidebar.classList.remove("-translate-x-full");
                    if (overlay) {
                        overlay.classList.remove("opacity-0", "pointer-events-none");
                        overlay.classList.add("opacity-100");
                    }
                    body.classList.add("overflow-hidden");
                };

                const closeSidebar = () => {
                    if (!sidebar) {
                        return;
                    }
                    sidebar.classList.add("-translate-x-full");
                    if (overlay) {
                        overlay.classList.add("opacity-0", "pointer-events-none");
                        overlay.classList.remove("opacity-100");
                    }
                    body.classList.remove("overflow-hidden");
                };

                const syncSidebarState = () => {
                    if (!sidebar) {
                        return;
                    }
                    const isDesktop = window.matchMedia("(min-width: 768px)").matches;
                    if (isDesktop) {
                        sidebar.classList.remove("-translate-x-full");
                        body.classList.remove("overflow-hidden");
                        if (overlay) {
                            overlay.classList.add("opacity-0", "pointer-events-none");
                            overlay.classList.remove("opacity-100");
                        }
                    } else if (!overlay || overlay.classList.contains("opacity-0")) {
                        sidebar.classList.add("-translate-x-full");
                    }
                };

                syncSidebarState();

                if (toggleButton && sidebar) {
                    toggleButton.addEventListener("click", () => {
                        const isHidden = sidebar.classList.contains("-translate-x-full");
                        if (isHidden) {
                            openSidebar();
                        } else {
                            closeSidebar();
                        }
                    });
                }

                if (overlay) {
                    overlay.addEventListener("click", () => {
                        closeSidebar();
                    });
                }

                if (sidebar) {
                    sidebar.querySelectorAll("a[href]").forEach((link) => {
                        link.addEventListener("click", () => {
                            if (!window.matchMedia("(min-width: 768px)").matches) {
                                closeSidebar();
                            }
                        });
                    });
                }

                document.addEventListener("keydown", (event) => {
                    if (event.key === "Escape" && sidebar && !sidebar.classList.contains("-translate-x-full")) {
                        closeSidebar();
                    }
                });

                window.addEventListener("resize", syncSidebarState);

                const themeToggle = document.querySelector("[data-theme-toggle]");
                if (themeToggle && typeof window.__setThemePreference === "function") {
                    const preferenceOrder = ["light", "dark", "system"];
                    const icons = themeToggle.querySelectorAll("[data-theme-icon]");
                    const label = themeToggle.querySelector("[data-theme-label]");
                    const labelMap = {
                        light: "Terang",
                        dark: "Gelap",
                        system: "Sistem",
                    };

                    const updateState = () => {
                        const preference = document.documentElement.dataset.themePreference ?? "system";
                        themeToggle.dataset.themePreference = preference;
                        icons.forEach((icon) => {
                            const value = icon.getAttribute("data-theme-icon");
                            icon.classList.toggle("hidden", value !== preference);
                        });
                        if (label) {
                            label.textContent = labelMap[preference] ?? "Sistem";
                        }
                        const labelText = labelMap[preference] ?? "Sistem";
                        themeToggle.setAttribute("aria-label", `Mode ${labelText}`);
                        themeToggle.setAttribute("title", `Mode ${labelText}`);
                    };

                    themeToggle.addEventListener("click", () => {
                        const currentPreference = document.documentElement.dataset.themePreference ?? "system";
                        const currentIndex = preferenceOrder.indexOf(currentPreference);
                        const nextPreference = preferenceOrder[(currentIndex + 1) % preferenceOrder.length];
                        window.__setThemePreference(nextPreference);
                        updateState();
                    });

                    updateState();
                }

                const accordionGroups = document.querySelectorAll("[data-accordion-group]");
                accordionGroups.forEach((group) => {
                    const button = group.querySelector("[data-accordion-button]");
                    const panel = group.querySelector("[data-accordion-panel]");
                    if (!button || !panel) {
                        return;
                    }

                    const icon = button.querySelector("[data-accordion-icon]");
                    const hasActive = group.getAttribute("data-active") === "true";

                    const applyState = (open) => {
                        const nextOpen = open ? "true" : "false";
                        panel.dataset.open = nextOpen;
                        group.setAttribute("data-open", nextOpen);
                        button.setAttribute("aria-expanded", nextOpen);
                        panel.style.maxHeight = open ? `${panel.scrollHeight}px` : "0px";
                        panel.style.opacity = open ? "1" : "0";
                        if (icon) {
                            icon.classList.toggle("rotate-180", open);
                        }
                        if (hasActive) {
                            group.classList.add("bg-slate-100", "dark:bg-slate-800/60", "border-indigo-200", "dark:border-indigo-500/40");
                        } else {
                            group.classList.remove("bg-slate-100", "dark:bg-slate-800/60", "border-indigo-200", "dark:border-indigo-500/40");
                        }
                    };

                    const initialOpen = group.getAttribute("data-open") === "true";
                    applyState(initialOpen);

                    button.addEventListener("click", () => {
                        const isOpen = panel.dataset.open === "true";
                        applyState(!isOpen);
                    });

                    const syncHeight = () => {
                        if (panel.dataset.open === "true") {
                            panel.style.maxHeight = `${panel.scrollHeight}px`;
                        }
                    };

                if (typeof ResizeObserver === "function") {
                    const resizeObserver = new ResizeObserver(syncHeight);
                    resizeObserver.observe(panel);
                } else {
                    window.addEventListener("resize", syncHeight);
                }
            });

            const profileMenus = document.querySelectorAll('[data-profile-menu]');

            profileMenus.forEach((menu) => {
                const trigger = menu.querySelector('[data-profile-trigger]');
                const dropdown = menu.querySelector('[data-profile-dropdown]');
                const icon = trigger ? trigger.querySelector('.ri-arrow-down-s-line') : null;
                let isOpen = false;

                const closeMenu = () => {
                    if (!dropdown) {
                        return;
                    }
                    isOpen = false;
                    trigger?.setAttribute('data-open', 'false');
                    if (icon) {
                        icon.classList.remove('rotate-180');
                    }
                    dropdown.classList.add('opacity-0', 'pointer-events-none', 'translate-y-2');
                    setTimeout(() => {
                        if (!isOpen) {
                            dropdown.classList.add('hidden');
                        }
                    }, 150);
                };

                const openMenu = () => {
                    if (!dropdown) {
                        return;
                    }
                    isOpen = true;
                    trigger?.setAttribute('data-open', 'true');
                    if (icon) {
                        icon.classList.add('rotate-180');
                    }
                    dropdown.classList.remove('hidden');
                    requestAnimationFrame(() => {
                        dropdown.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-2');
                    });
                };

                trigger?.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    if (isOpen) {
                        closeMenu();
                    } else {
                        openMenu();
                    }
                });

                document.addEventListener('click', (event) => {
                    if (!menu.contains(event.target)) {
                        closeMenu();
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeMenu();
                    }
                });
            });
        });
        </script>
        <style>
            body {
                font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                transition: background-color 0.3s ease, color 0.3s ease;
            }

            .dark .text-slate-900,
            .dark .text-slate-800,
            .dark .text-slate-700,
            .dark .text-slate-600,
            .dark .text-gray-900,
            .dark .text-gray-800,
            .dark .text-gray-700,
            .dark .text-gray-600 {
                color: rgb(226, 232, 240) !important;
            }

            .dark .text-slate-500,
            .dark .text-gray-500 {
                color: rgb(203, 213, 225) !important;
            }

            .dark .text-slate-400,
            .dark .text-gray-400 {
                color: rgb(148, 163, 184) !important;
            }

            .dark .text-slate-300,
            .dark .text-gray-300 {
                color: rgba(226, 232, 240, 0.85) !important;
            }

            .dark .bg-white,
            .dark .bg-slate-50,
            .dark .bg-gray-50,
            .dark .bg-white\/80 {
                background: linear-gradient(160deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.95) 100%) !important;
                color: rgb(226, 232, 240) !important;
                box-shadow: 0 25px 60px rgba(8, 11, 20, 0.45);
            }

            .dark .shadow,
            .dark .shadow-sm,
            .dark .shadow-md,
            .dark .shadow-lg,
            .dark .shadow-xl {
                box-shadow: 0 25px 60px rgba(8, 11, 20, 0.45) !important;
            }

            .dark .border-slate-100,
            .dark .border-slate-200,
            .dark .border-gray-100,
            .dark .border-gray-200 {
                border-color: rgba(71, 85, 105, 0.6) !important;
            }

            .dark input:not([type="checkbox"]):not([type="radio"]),
            .dark select,
            .dark textarea {
                background-color: rgba(15, 23, 42, 0.85) !important;
                border-color: rgba(71, 85, 105, 0.6) !important;
                color: rgb(226, 232, 240) !important;
                transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease, box-shadow 0.3s ease;
                box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.08);
            }

            .dark input:not([type="checkbox"]):not([type="radio"])::placeholder,
            .dark textarea::placeholder {
                color: rgb(148, 163, 184) !important;
            }

            .dark input:not([type="checkbox"]):not([type="radio"]):focus,
            .dark select:focus,
            .dark textarea:focus {
                border-color: rgb(99, 102, 241) !important;
                box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.35);
            }

            .dark table {
                background: linear-gradient(140deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.95) 100%) !important;
                border-color: rgba(71, 85, 105, 0.6) !important;
            }

            .dark table thead tr {
                background-color: rgba(30, 41, 59, 0.7) !important;
            }

            .dark table th,
            .dark table td {
                color: rgb(226, 232, 240) !important;
                border-color: rgba(71, 85, 105, 0.6) !important;
            }

            .dark button:disabled,
            .dark [aria-disabled="true"],
            .dark .disabled {
                background-color: rgba(71, 85, 105, 0.5) !important;
                color: rgb(148, 163, 184) !important;
            }
        </style>
    </head>
    <body class="h-full bg-slate-100 text-slate-900 antialiased transition-colors duration-300 dark:bg-[#0f172a] dark:bg-gradient-to-br dark:from-[#0f172a] dark:via-slate-900 dark:to-slate-950 dark:text-gray-100">
        <div class="flex min-h-screen transition-colors duration-300 dark:bg-gradient-to-br dark:from-transparent dark:via-slate-900/10 dark:to-transparent">
            <aside
                id="sidebar"
                class="fixed inset-y-0 left-0 z-30 w-64 flex-shrink-0 transform border-r border-slate-200 bg-white transition-transform duration-200 ease-in-out -translate-x-full overflow-y-auto overscroll-contain md:static md:translate-x-0 transition-colors duration-300 dark:border-slate-800 dark:bg-slate-800 dark:shadow-lg"
            >
                <div class="flex items-center gap-3 border-b border-slate-100 px-6 py-6 transition-colors duration-300 dark:border-slate-700">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600 text-lg font-semibold text-white">SMK</span>
                    <div>
                        <p class="text-sm font-semibold text-slate-800 dark:text-gray-100"><?= htmlspecialchars(config('app.name'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-slate-500 dark:text-gray-400">Sistem Informasi Akademik · v<?= htmlspecialchars((string) config('app.version', '1.0.5'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>

                <?php $menu = $activeMenu ?? ''; ?>
                <?php
                    $currentUser = auth();
                    if (!is_array($currentUser)) {
                        $currentUser = [];
                    }

                    $profileReminderPending = \Core\Session::get('profile_completion_prompt_pending') === true;
                    $profileReminderFields = [];
                    $profileReminderRole = '';
                    $demoModeEnabled = \App\Support\DemoMode::isEnabled();
                    $maintenanceModeEnabled = \App\Support\MaintenanceMode::isEnabled();

                    if ($profileReminderPending) {
                        $profileReminderFields = (array) \Core\Session::get('profile_completion_missing_fields', []);
                        $profileReminderRole = (string) \Core\Session::get('profile_completion_prompt_role', '');
                        \Core\Session::forget('profile_completion_prompt_pending');
                        \Core\Session::forget('profile_completion_missing_fields');
                        \Core\Session::forget('profile_completion_prompt_role');
                    }

                    $profileReminderVisible = $profileReminderPending && !empty($profileReminderFields);

                    $isHomeroom = false;
                    $isPrakerinSupervisor = false;
                    $isExtracurricularMentor = false;
                    $isHeadmaster = false;
                    $teacherId = null;
                    $activeYear = \App\Models\SchoolYear::active();
                    $activeYearId = (int) ($activeYear['id'] ?? 0);
                    $activeSemester = (int) ($activeYear['semester_aktif'] ?? 0);
                    $homeroomClasses = [];
                    $showPromotionMenu = false;
                    $showGraduationMenu = false;
                    $showHomeroomNotes = false;
                    $hasKurmerHomeroom = false;
                    $hasK13Homeroom = false;
                    $canAccessFinanceBendahara = \App\Support\FinanceGate::isBendahara($currentUser);
                    $role = $currentUser['role'] ?? '';
                    $isTataUsaha = \App\Support\AcademicRoleGate::isTataUsaha($currentUser);
                    $isWakaKurikulum = \App\Support\AcademicRoleGate::isWakaKurikulum($currentUser);
                    $isKepalaProdi = \App\Support\AcademicRoleGate::isKepalaProdi(null, $currentUser);
                    $isStudentGraduating = false;

                    if ($role === 'guru' && !empty($currentUser['teacher_id'])) {
                        $teacherId = (int) $currentUser['teacher_id'];
                        $isHomeroom = \App\Models\Classroom::teacherHasHomeroom($teacherId);
                        if ($activeYearId > 0) {
                            $isHeadmaster = (int) ($activeYear['kepala_sekolah_id'] ?? 0) === $teacherId;
                        }
                        if ($activeYearId > 0) {
                            $isPrakerinSupervisor = \App\Models\PrakerinPlace::teacherHasActivePlacements($teacherId, $activeYearId);
                            $isExtracurricularMentor = \App\Models\Extracurricular::teacherHasMentorship($teacherId, $activeYearId);
                        }

                        if (!$isTataUsaha) {
                            if ($activeYearId > 0) {
                                $isTataUsaha = \App\Models\TeacherAcademicPosition::teacherHasAssignedRole($teacherId, 'tata_usaha', $activeYearId);
                            } else {
                                $isTataUsaha = \App\Models\TeacherAcademicPosition::teacherHasAssignedRole($teacherId, 'tata_usaha', null);
                            }
                        }

                        if (!$isWakaKurikulum) {
                            if ($activeYearId > 0) {
                                $isWakaKurikulum = \App\Models\TeacherAcademicPosition::teacherHasAssignedRole($teacherId, 'waka_kurikulum', $activeYearId);
                            } else {
                                $isWakaKurikulum = \App\Models\TeacherAcademicPosition::teacherHasAssignedRole($teacherId, 'waka_kurikulum', null);
                            }
                        }

                        if ($isHomeroom) {
                            $homeroomClasses = \App\Models\Classroom::homeroomClassesForTeacher(
                                $teacherId,
                                $activeYearId > 0 ? $activeYearId : null
                            );

                            if (empty($homeroomClasses)) {
                                $homeroomClasses = \App\Models\Classroom::homeroomClassesForTeacher($teacherId);
                            }

                    if (!empty($homeroomClasses)) {
                        $hasKurmerHomeroom = array_reduce($homeroomClasses, static function (bool $carry, array $class): bool {
                            return $carry || (($class['kurikulum'] ?? 'k13') === 'kurmer');
                        }, false);
                        $hasK13Homeroom = array_reduce($homeroomClasses, static function (bool $carry, array $class): bool {
                            return $carry || (($class['kurikulum'] ?? 'k13') !== 'kurmer');
                        }, false);

                        $levels = array_unique(array_map(static function ($class) {
                            return (int) ($class['tingkat'] ?? 0);
                        }, $homeroomClasses));

                                if ($activeSemester === 2) {
                                    $showPromotionMenu = array_reduce($levels, static function (bool $carry, int $level): bool {
                                        return $carry || in_array($level, [10, 11], true);
                                    }, false);
                                    $showGraduationMenu = in_array(12, $levels, true);
                                }
                            }

                            $showHomeroomNotes = true;
                        }
                    } elseif ($role === 'kepala_sekolah') {
                        $isHeadmaster = true;
                        if (!empty($currentUser['teacher_id'])) {
                            $teacherId = (int) $currentUser['teacher_id'];
                        }
                    } elseif ($role === 'siswa' && !empty($currentUser['student_id'])) {
                        $studentProfile = \App\Models\Student::findWithRelations((int) $currentUser['student_id']);
                        if ($studentProfile !== null && (int) ($studentProfile['kelas_tingkat'] ?? 0) === 12) {
                            $isStudentGraduating = true;
                        }
                    }

                    $hasPpdbAssignment = \App\Support\PpdbGate::teacherHasActiveAssignment($currentUser);
                    $canManagePpdb = \App\Support\PpdbGate::isAdmin($currentUser);
                ?>

                <?php
                    $activeClasses = 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/30 dark:text-indigo-100';
                    $inactiveClasses = 'text-slate-600 hover:bg-slate-100 dark:text-gray-100 dark:hover:bg-slate-700 dark:hover:text-white';
                    $academicMenuHeading = ($isWakaKurikulum && !$isTataUsaha) ? 'Kurikulum' : 'Tata Usaha';

                    // Render helper keeps markup consistent across menu sections.
                    $renderMenuItem = static function (array $item) use ($menu, $activeClasses, $inactiveClasses): void {
                        if (!($item['visible'] ?? true)) {
                            return;
                        }

                        $itemKey = (string) ($item['key'] ?? '');
                        $isActive = $menu === $itemKey;
                        $classes = $isActive ? $activeClasses : $inactiveClasses;
                        $icon = $item['icon'] ?? '';
                        if ($icon === '' && $itemKey === 'finance-bendahara-savings') {
                            $icon = 'ri-piggy-bank-line';
                        }
                        $label = $item['label'] ?? '';
                        $href = $item['url'] ?? '#';
                        ?>
                        <a
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors duration-300 <?= $classes ?>"
                            href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <?php if ($icon !== ''): ?>
                                <i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?> text-lg"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php
                    };

                    // Group menus so the sidebar stays tidy and easy to scan.
                    $menuGroups = [
	                        [
	                            'id' => 'group-dashboard',
	                            'heading' => 'Dashboard',
	                            'visible' => true,
	                            'default_open' => true,
	                            'items' => [
	                                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'ri-dashboard-3-line', 'url' => base_url('dashboard')],
	                            ],
	                        ],
	                        [
	                            'id' => 'group-guides',
	                            'heading' => 'Bantuan',
	                            'visible' => true,
	                            'items' => [
	                                ['key' => 'guides', 'label' => 'Pedoman', 'icon' => 'ri-book-open-line', 'url' => base_url('pedoman')],
	                                ['key' => 'changelog', 'label' => 'Changelog', 'icon' => 'ri-history-line', 'url' => base_url('changelog')],
	                            ],
	                        ],
	                        [
	                            'id' => 'group-master-data',
	                            'heading' => 'Master Data',
                            'visible' => true,
                            'items' => [
                                ['key' => 'years', 'label' => 'Tahun Ajaran', 'icon' => 'ri-calendar-2-line', 'url' => base_url('master/tahun-ajaran'), 'visible' => $role === 'admin'],
                                ['key' => 'grade-rescue-periods', 'label' => 'Periode Rescue Nilai', 'icon' => 'ri-time-line', 'url' => base_url('admin/periode-rescue-nilai'), 'visible' => $role === 'admin'],
                                ['key' => 'majors', 'label' => 'Jurusan', 'icon' => 'ri-flow-chart', 'url' => base_url('master/jurusan'), 'visible' => $role === 'admin'],
                                ['key' => 'teachers', 'label' => 'Guru', 'icon' => 'ri-user-voice-line', 'url' => base_url('master/guru'), 'visible' => $role === 'admin'],
                                ['key' => 'classes', 'label' => 'Kelas', 'icon' => 'ri-community-line', 'url' => base_url('master/kelas'), 'visible' => $role === 'admin'],
                                ['key' => 'students', 'label' => 'Siswa', 'icon' => 'ri-id-card-line', 'url' => base_url('master/siswa'), 'visible' => $role === 'admin'],
                                ['key' => 'student-transfers', 'label' => 'Siswa Pindahan', 'icon' => 'ri-user-received-2-line', 'url' => base_url('master/siswa/pindahan'), 'visible' => $role === 'admin'],
                                ['key' => 'student-register', 'label' => 'Buku Induk Siswa', 'icon' => 'ri-book-3-line', 'url' => base_url('buku-induk'), 'visible' => $role === 'admin'],
                                ['key' => 'student-placements', 'label' => 'Penempatan Siswa', 'icon' => 'ri-route-line', 'url' => base_url('master/siswa/penempatan'), 'visible' => $role === 'admin'],
                                ['key' => 'attitudes', 'label' => 'Data Sikap', 'icon' => 'ri-emotion-laugh-line', 'url' => base_url('master/data-sikap'), 'visible' => $role === 'admin'],
                                ['key' => 'prakerin', 'label' => 'Tempat Prakerin', 'icon' => 'ri-building-line', 'url' => base_url('master/prakerin'), 'visible' => $role === 'admin'],
                                ['key' => 'extracurriculars', 'label' => 'Ekstrakurikuler', 'icon' => 'ri-trophy-line', 'url' => base_url('master/ekskul'), 'visible' => $role === 'admin'],
                                ['key' => 'subjects', 'label' => 'Mata Pelajaran', 'icon' => 'ri-book-2-line', 'url' => base_url('akademik/mata-pelajaran')],
                                [
                                    'key' => 'subject-teachers',
                                    'label' => 'Guru Pengampu',
                                    'icon' => 'ri-user-star-line',
                                    'url' => base_url('akademik/guru-pengampu'),
                                    'visible' => $role === 'admin',
                                ],
                                [
                                    'key' => 'lesson-schedules',
                                    'label' => 'Jadwal Pelajaran',
                                    'icon' => 'ri-calendar-schedule-line',
                                    'url' => base_url('akademik/jadwal'),
                                    'visible' => $role === 'admin',
                                ],
                                [
                                    'key' => 'automatic-schedules',
                                    'label' => 'Generate Jadwal',
                                    'icon' => 'ri-magic-line',
                                    'url' => base_url('akademik/jadwal/generate'),
                                    'visible' => $role === 'admin',
                                ],
                                ['key' => 'academic-positions', 'label' => 'Jabatan Akademik', 'icon' => 'ri-medal-2-line', 'url' => base_url('master/jabatan-akademik'), 'visible' => $role === 'admin'],
                                ['key' => 'schools', 'label' => 'Profil Sekolah', 'icon' => 'ri-school-line', 'url' => base_url('master/sekolah'), 'visible' => $role === 'admin'],
                            ],
                        ],
                        [
                            'id' => 'group-ppdb',
                            'heading' => 'PPDB',
                            'visible' => ($canManagePpdb ?? false) || ($hasPpdbAssignment ?? false),
                            'items' => [
                                [
                                    'key' => 'ppdb-periods',
                                    'label' => 'Periode PPDB',
                                    'icon' => 'ri-calendar-event-line',
                                    'url' => base_url('ppdb/admin/periode'),
                                    'visible' => $canManagePpdb ?? false,
                                ],
                                [
                                    'key' => 'ppdb-registrants',
                                    'label' => 'Data Pendaftar',
                                    'icon' => 'ri-user-add-line',
                                    'url' => base_url('ppdb/admin/pendaftar'),
                                    'visible' => $canManagePpdb ?? false,
                                ],
                                [
                                    'key' => 'ppdb-migration',
                                    'label' => 'Migrasi ke Siswa',
                                    'icon' => 'ri-user-shared-line',
                                    'url' => base_url('ppdb/admin/migrasi'),
                                    'visible' => $canManagePpdb ?? false,
                                ],
                                [
                                    'key' => 'ppdb-report',
                                    'label' => 'Laporan PPDB',
                                    'icon' => 'ri-bar-chart-box-line',
                                    'url' => base_url('ppdb/admin/laporan'),
                                    'visible' => $canManagePpdb ?? false,
                                ],
                                [
                                    'key' => 'ppdb-broadcast-admin',
                                    'label' => 'Broadcast PPDB',
                                    'icon' => 'ri-megaphone-line',
                                    'url' => base_url('ppdb/admin/broadcast'),
                                    'visible' => $canManagePpdb ?? false,
                                ],
                                [
                                    'key' => 'ppdb-teacher-dashboard',
                                    'label' => 'Dashboard Penanggung Jawab',
                                    'icon' => 'ri-team-line',
                                    'url' => base_url('ppdb/guru'),
                                    'visible' => $hasPpdbAssignment ?? false,
                                ],
                                [
                                    'key' => 'ppdb-teacher-registrants',
                                    'label' => 'Data Pendaftar',
                                    'icon' => 'ri-file-list-3-line',
                                    'url' => base_url('ppdb/guru/pendaftar'),
                                    'visible' => $hasPpdbAssignment ?? false,
                                ],
                                [
                                    'key' => 'ppdb-broadcast-guru',
                                    'label' => 'Broadcast Pendaftar',
                                    'icon' => 'ri-notification-4-line',
                                    'url' => base_url('ppdb/guru/broadcast'),
                                    'visible' => $hasPpdbAssignment ?? false,
                                ],
                            ],
                        ],
                        [
                            'id' => 'group-tata-usaha',
                            'heading' => $academicMenuHeading,
                            'visible' => $isTataUsaha || $isWakaKurikulum,
                            'items' => [
                                [
                                    'key' => 'subject-teachers',
                                    'label' => 'Guru Pengampu',
                                    'icon' => 'ri-user-star-line',
                                    'url' => base_url('akademik/guru-pengampu'),
                                    'visible' => $isTataUsaha || $isWakaKurikulum,
                                ],
                                [
                                    'key' => 'lesson-schedules',
                                    'label' => 'Jadwal Mengajar Guru',
                                    'icon' => 'ri-calendar-schedule-line',
                                    'url' => base_url('akademik/jadwal'),
                                    'visible' => $isTataUsaha || $isWakaKurikulum,
                                ],
                                [
                                    'key' => 'automatic-schedules',
                                    'label' => 'Generate Jadwal',
                                    'icon' => 'ri-magic-line',
                                    'url' => base_url('akademik/jadwal/generate'),
                                    'visible' => $isTataUsaha || $isWakaKurikulum,
                                ],
                                [
                                    'key' => 'graduation-certificates',
                                    'label' => 'SKL Kelulusan',
                                    'icon' => 'ri-graduation-cap-line',
                                    'url' => base_url('akademik/skl'),
                                    'visible' => $isWakaKurikulum || $role === 'admin' || $role === 'staff',
                                ],
                                [
                                    'key' => 'student-transfers',
                                    'label' => 'Siswa Pindahan',
                                    'icon' => 'ri-user-received-2-line',
                                    'url' => base_url('master/siswa/pindahan'),
                                    'visible' => $isTataUsaha,
                                ],
                                [
                                    'key' => 'assignment-letters',
                                    'label' => 'SK Penugasan Guru',
                                    'icon' => 'ri-file-text-line',
                                    'url' => base_url('tata-usaha/sk-penugasan'),
                                    'visible' => $isTataUsaha,
                                ],
                                [
                                    'key' => 'manual-attendance',
                                    'label' => 'Cetak Absensi Manual',
                                    'icon' => 'ri-calendar-check-line',
                                    'url' => base_url('tata-usaha/presensi-manual'),
                                    'visible' => $isTataUsaha,
                                ],
                                [
                                    'key' => 'letters',
                                    'label' => 'Persuratan',
                                    'icon' => 'ri-mail-send-line',
                                    'url' => base_url('tata-usaha/persuratan'),
                                    'visible' => $isTataUsaha,
                                ],
                            ],
                        ],
                        [
                            'id' => 'group-utilities',
                            'heading' => 'Utilitas',
                            'visible' => $role === 'admin',
                            'items' => [
                                ['key' => 'users', 'label' => 'Pengguna', 'icon' => 'ri-team-line', 'url' => base_url('admin/pengguna')],
                                ['key' => 'user-rules', 'label' => 'User Rules', 'icon' => 'ri-shield-user-line', 'url' => base_url('admin/user-rules')],
                                ['key' => 'user-logs', 'label' => 'Log Pengguna', 'icon' => 'ri-history-line', 'url' => base_url('admin/log-aktivitas')],
                                ['key' => 'session-timeout', 'label' => 'Sesi Login', 'icon' => 'ri-timer-flash-line', 'url' => base_url('admin/pengaturan/sesi-login')],
                                ['key' => 'demo-mode', 'label' => 'Mode Demo', 'icon' => 'ri-eye-close-line', 'url' => base_url('admin/demo-mode')],
                                ['key' => 'maintenance-mode', 'label' => 'Maintenance Mode', 'icon' => 'ri-tools-line', 'url' => base_url('admin/maintenance-mode')],
                                ['key' => 'periodic-copy', 'label' => 'Salin Data Periodik', 'icon' => 'ri-database-2-line', 'url' => base_url('admin/salin-data-periodik')],
                                ['key' => 'legacy-migration', 'label' => 'Migrasi Rapor Legacy', 'icon' => 'ri-upload-cloud-2-line', 'url' => base_url('admin/migrasi-rapor')],
                                ['key' => 'admin-file-manager', 'label' => 'File Manager', 'icon' => 'ri-folder-chart-line', 'url' => base_url('admin/file-manager')],
                            ],
                        ],
                        [
                            'id' => 'group-integrations',
                            'heading' => 'Integrasi',
                            'visible' => $role === 'admin',
                            'items' => [
                                ['key' => 'cbt-export', 'label' => 'Export Data CBT', 'icon' => 'ri-macbook-line', 'url' => base_url('admin/cbt/export')],
                                ['key' => 'whatsapp-gateway', 'label' => 'WhatsApp Gateway', 'icon' => 'ri-whatsapp-line', 'url' => base_url('admin/integrasi/whatsapp')],
                            ],
                        ],
                        [
                            'id' => 'group-maintenance',
                            'heading' => 'Pemeliharaan',
                            'visible' => $role === 'admin',
                            'items' => [
                                ['key' => 'clean-data-ppdb', 'label' => 'Clean Data PPDB', 'icon' => 'ri-recycle-line', 'url' => base_url('admin/clean-data/ppdb')],
                                ['key' => 'clean-data-letters', 'label' => 'Clean Data Persuratan', 'icon' => 'ri-mail-settings-line', 'url' => base_url('admin/clean-data/persuratan')],
                                ['key' => 'clean-data-report', 'label' => 'Clean Data Rapor', 'icon' => 'ri-eraser-line', 'url' => base_url('admin/clean-data/raport')],
                                ['key' => 'clean-data-finance', 'label' => 'Clean Data Keuangan', 'icon' => 'ri-delete-bin-6-line', 'url' => base_url('admin/clean-data/keuangan')],
                                ['key' => 'clean-data-logs', 'label' => 'Clean Log Pengguna', 'icon' => 'ri-history-line', 'url' => base_url('admin/clean-data/log')],
                                ['key' => 'data-backup-restore', 'label' => 'Backup & Restore Data', 'icon' => 'ri-archive-fill', 'url' => base_url('admin/backup-restore')],
                                ['key' => 'app-update', 'label' => 'Update Aplikasi', 'icon' => 'ri-refresh-line', 'url' => base_url('admin/update')],
                            ],
                        ],
                        [
                            'id' => 'group-reporting',
                            'heading' => 'Laporan',
                            'visible' => $role === 'admin',
                            'items' => [
                                ['key' => 'midterm-report', 'label' => 'Laporan Tengah Semester', 'icon' => 'ri-file-list-3-line', 'url' => base_url('raport/tengah-semester')],
                                ['key' => 'report-cards', 'label' => 'Cetak Raport', 'icon' => 'ri-printer-line', 'url' => base_url('raport/cetak')],
                                ['key' => 'student-cards', 'label' => 'Cetak Kartu Pelajar', 'icon' => 'ri-id-card-line', 'url' => base_url('kartu-pelajar')],
                            ],
                        ],
                        [
                            'id' => 'group-headmaster',
                            'heading' => 'Kepala Sekolah',
                            'visible' => ($role === 'guru' && $teacherId !== null && $isHeadmaster) || $role === 'kepala_sekolah',
                            'items' => [
                                ['key' => 'graduation-approvals', 'label' => 'Persetujuan SKL', 'icon' => 'ri-award-line', 'url' => base_url('kepala-sekolah/skl')],
                                ['key' => 'digital-signatures-letters', 'label' => 'Persetujuan Persuratan', 'icon' => 'ri-mail-check-line', 'url' => base_url('kepala-sekolah/persuratan')],
                                ['key' => 'digital-signatures-transkrip', 'label' => 'Persetujuan Transkrip', 'icon' => 'ri-file-list-3-line', 'url' => base_url('kepala-sekolah/ttd-digital/transkrip')],
                                ['key' => 'digital-signatures', 'label' => 'Persetujuan Raport', 'icon' => 'ri-shield-check-line', 'url' => base_url('kepala-sekolah/ttd-digital')],
                                ['key' => 'finance-kepsek-reports', 'label' => 'Rekap Keuangan', 'icon' => 'ri-file-chart-line', 'url' => base_url('keuangan/kepala-sekolah/laporan')],
                                ['key' => 'finance-headmaster-procurements', 'label' => 'Pengadaan Praktikum', 'icon' => 'ri-file-check-line', 'url' => base_url('keuangan/kepala-sekolah/pengadaan')],
                                ['key' => 'finance-headmaster-approvals', 'label' => 'Approve Kasbon/Dana/Honor', 'icon' => 'ri-task-line', 'url' => base_url('keuangan/kepala-sekolah/approval')],
                            ],
                        ],
                        [
                            'id' => 'group-finance-bendahara',
                            'heading' => 'Bendahara',
                            'visible' => $canAccessFinanceBendahara,
                            'items' => [
                                ['key' => 'finance-bendahara-dashboard', 'label' => 'Dashboard Bendahara', 'icon' => 'ri-dashboard-2-line', 'url' => base_url('keuangan/bendahara')],
                                ['key' => 'finance-bendahara-billings', 'label' => 'Tagihan Siswa', 'icon' => 'ri-bill-line', 'url' => base_url('keuangan/bendahara/tagihan')],
                                ['key' => 'finance-bendahara-categories', 'label' => 'Kategori Tagihan', 'icon' => 'ri-price-tag-3-line', 'url' => base_url('keuangan/bendahara/kategori')],
                                ['key' => 'finance-bendahara-purchases', 'label' => 'Pembelian Perlengkapan', 'icon' => 'ri-shopping-bag-3-line', 'url' => base_url('keuangan/bendahara/pembelian')],
                                ['key' => 'finance-bendahara-savings', 'label' => 'Tabungan Siswa', 'icon' => 'ri-wallet-3-fill', 'url' => base_url('keuangan/bendahara/tabungan')],
                                ['key' => 'finance-bendahara-student-ledger', 'label' => 'Rekap Keuangan Siswa', 'icon' => 'ri-user-3-line', 'url' => base_url('keuangan/bendahara/rekap-siswa')],
                                ['key' => 'finance-bendahara-general-cash', 'label' => 'Kas Utama', 'icon' => 'ri-bank-card-line', 'url' => base_url('keuangan/bendahara/kas-utama')],
                                ['key' => 'finance-bendahara-reports', 'label' => 'Rekap Keuangan', 'icon' => 'ri-file-list-3-line', 'url' => base_url('keuangan/bendahara/laporan')],
                                ['key' => 'finance-bendahara-teacher-attendance', 'label' => 'Rekap Presensi Guru', 'icon' => 'ri-calendar-check-line', 'url' => base_url('keuangan/bendahara/presensi-guru')],
                                ['key' => 'finance-bendahara-teacher-salary', 'label' => 'Input Gaji Guru', 'icon' => 'ri-money-dollar-circle-line', 'url' => base_url('keuangan/bendahara/gaji-guru')],
                                ['key' => 'finance-bendahara-unexpected-expenses', 'label' => 'Pengeluaran Tak Terduga', 'icon' => 'ri-flashlight-line', 'url' => base_url('keuangan/bendahara/pengeluaran-tak-terduga')],
                                ['key' => 'finance-bendahara-payments', 'label' => 'Verifikasi Pembayaran', 'icon' => 'ri-checkbox-circle-line', 'url' => base_url('keuangan/bendahara/pembayaran')],
                                ['key' => 'finance-bendahara-procurements', 'label' => 'Pengadaan Alat Praktik', 'icon' => 'ri-tools-line', 'url' => base_url('keuangan/bendahara/pengadaan')],
                                ['key' => 'finance-bendahara-loans', 'label' => 'Pencairan Kasbon Guru', 'icon' => 'ri-hand-coin-line', 'url' => base_url('keuangan/bendahara/kasbon')],
                                ['key' => 'finance-bendahara-activities', 'label' => 'Dana Kegiatan', 'icon' => 'ri-flag-line', 'url' => base_url('keuangan/bendahara/dana-kegiatan')],
                            ],
                        ],
                        [
                            'id' => 'group-finance-kaprodi',
                            'heading' => 'Kepala Prodi',
                            'visible' => $isKepalaProdi,
                            'items' => [
                                ['key' => 'ukk', 'label' => 'UKK & Skill Passport', 'icon' => 'ri-verified-badge-fill', 'url' => base_url('kaprodi/ukk')],
                                ['key' => 'finance-kaprodi-procurements', 'label' => 'Pengadaan Alat Praktik', 'icon' => 'ri-tools-fill', 'url' => base_url('keuangan/kaprodi/pengadaan')],
                            ],
                        ],
                        [
                            'id' => 'group-finance-guru',
                            'heading' => 'Keuangan',
                            'visible' => $role === 'guru',
                            'items' => [
                                ['key' => 'finance-guru-dashboard', 'label' => 'Ringkasan Keuangan', 'icon' => 'ri-wallet-2-line', 'url' => base_url('keuangan/guru')],
                                ['key' => 'finance-guru-loans', 'label' => 'Pengajuan Kasbon', 'icon' => 'ri-hand-coin-line', 'url' => base_url('keuangan/guru/kasbon')],
                                ['key' => 'finance-guru-activities', 'label' => 'Pengajuan Dana Kegiatan', 'icon' => 'ri-flag-line', 'url' => base_url('keuangan/guru/dana-kegiatan')],
                            ],
                        ],
                        [
                            'id' => 'group-finance-student',
                            'heading' => 'Menu Siswa',
	                            'visible' => $role === 'siswa',
	                            'items' => [
	                                ['key' => 'finance-siswa-dashboard', 'label' => 'Ringkasan Keuangan', 'icon' => 'ri-wallet-3-line', 'url' => base_url('keuangan/siswa')],
	                                ['key' => 'student-self-profile', 'label' => 'Profil Saya', 'icon' => 'ri-user-3-line', 'url' => base_url('siswa/profil')],
	                                ['key' => 'student-profile', 'label' => 'Edit Data Diri', 'icon' => 'ri-user-settings-line', 'url' => base_url('siswa/data-diri')],
	                                ['key' => 'student-documents', 'label' => 'Berkas Fisik', 'icon' => 'ri-folder-upload-line', 'url' => base_url('siswa/berkas')],
	                                ['key' => 'student-grades', 'label' => 'Nilai Saya', 'icon' => 'ri-award-line', 'url' => base_url('siswa/nilai')],
                                ['key' => 'student-attendance-scan', 'label' => 'Scan Presensi', 'icon' => 'ri-qr-code-line', 'url' => base_url('presensi/scan')],
                                [
                                    'key' => 'student-graduation',
                                    'label' => 'Informasi Kelulusan',
                                    'icon' => 'ri-graduation-cap-line',
                                    'url' => base_url('siswa/kelulusan'),
                                    'visible' => $isStudentGraduating,
                                ],
                            ],
                        ],
                        [
                            'id' => 'group-finance-headmaster',
                            'heading' => 'Keuangan Sekolah',
                            'visible' => $role === 'kepala_sekolah',
                            'items' => [
                                ['key' => 'finance-kepsek-dashboard', 'label' => 'Dashboard Keuangan', 'icon' => 'ri-bar-chart-2-line', 'url' => base_url('keuangan/kepala-sekolah')],
                                ['key' => 'finance-kepsek-reports', 'label' => 'Rekap Keuangan', 'icon' => 'ri-file-chart-line', 'url' => base_url('keuangan/kepala-sekolah/laporan')],
                                ['key' => 'finance-kepsek-approvals', 'label' => 'Persetujuan Keuangan', 'icon' => 'ri-task-line', 'url' => base_url('keuangan/kepala-sekolah/approval')],
                            ],
                        ],
                        [
                            'id' => 'group-teacher',
	                            'heading' => 'Guru Mata Pelajaran',
	                            'visible' => $role === 'guru' && $teacherId !== null,
	                            'items' => [
	                                ['key' => 'teacher-profile', 'label' => 'Profil Saya', 'icon' => 'ri-user-settings-line', 'url' => base_url('guru/profil')],
	                                ['key' => 'teacher-subject-assessments', 'label' => 'Input Nilai Mapel', 'icon' => 'ri-pencil-ruler-line', 'url' => base_url('guru/nilai')],
	                                ['key' => 'teacher-attendance', 'label' => 'Presensi QR Siswa', 'icon' => 'ri-qr-code-line', 'url' => base_url('guru/presensi')],
	                                ['key' => 'teacher-attendance-recap', 'label' => 'Rekap Presensi', 'icon' => 'ri-clipboard-line', 'url' => base_url('guru/presensi/rekap')],
	                            ],
	                        ],
                        [
                            'id' => 'group-extracurricular',
                            'heading' => 'Pembina Ekskul',
                            'visible' => $isExtracurricularMentor,
                            'items' => [
                                ['key' => 'teacher-extracurricular-assessments', 'label' => 'Input Nilai Ekskul', 'icon' => 'ri-trophy-line', 'url' => base_url('guru/ekskul/nilai')],
                            ],
                        ],
                        [
                            'id' => 'group-prakerin',
                            'heading' => 'Pembina Prakerin',
                            'visible' => $isPrakerinSupervisor,
                            'items' => [
                                ['key' => 'teacher-prakerin-assessments', 'label' => 'Input Nilai Prakerin', 'icon' => 'ri-award-line', 'url' => base_url('guru/prakerin/nilai')],
                            ],
                        ],
                        [
                            'id' => 'group-homeroom',
                            'heading' => 'Wali Kelas',
                            'visible' => $isHomeroom,
                            'items' => [
                                ['key' => 'homeroom-students', 'label' => 'Data Siswa', 'icon' => 'ri-id-card-line', 'url' => base_url('master/siswa')],
                                ['key' => 'homeroom-student-register', 'label' => 'Buku Induk Siswa', 'icon' => 'ri-book-3-line', 'url' => base_url('buku-induk')],
                                [
                                    'key' => 'homeroom-attitudes-spiritual',
                                    'label' => 'Nilai Sikap Spiritual',
                                    'icon' => 'ri-sparkling-2-line',
                                    'url' => base_url('walikelas/nilai-sikap/spiritual'),
                                    'visible' => $hasK13Homeroom,
                                ],
                                [
                                    'key' => 'homeroom-attitudes-sosial',
                                    'label' => 'Nilai Sikap Sosial',
                                    'icon' => 'ri-hand-heart-line',
                                    'url' => base_url('walikelas/nilai-sikap/sosial'),
                                    'visible' => $hasK13Homeroom,
                                ],
                                ['key' => 'homeroom-attendance', 'label' => 'Input Presensi', 'icon' => 'ri-calendar-check-line', 'url' => base_url('walikelas/presensi')],
                                ['key' => 'homeroom-ledger', 'label' => 'Legger Kelas', 'icon' => 'ri-table-line', 'url' => base_url('walikelas/legger')],
                                ['key' => 'homeroom-grade-upload', 'label' => 'Upload Nilai Rescue', 'icon' => 'ri-file-upload-line', 'url' => base_url('walikelas/nilai-upload')],
                                ['key' => 'homeroom-transcripts', 'label' => 'Cetak Transkrip', 'icon' => 'ri-file-list-3-line', 'url' => base_url('walikelas/transkrip')],
                                ['key' => 'homeroom-prakerin', 'label' => 'Penempatan Prakerin', 'icon' => 'ri-briefcase-line', 'url' => base_url('walikelas/prakerin')],
                                ['key' => 'homeroom-extracurriculars', 'label' => 'Ekskul Siswa', 'icon' => 'ri-team-line', 'url' => base_url('walikelas/ekskul')],
                                ['key' => 'homeroom-achievements', 'label' => 'Prestasi Siswa', 'icon' => 'ri-medal-line', 'url' => base_url('walikelas/prestasi')],
                                [
                                    'key' => 'homeroom-cocurriculars',
                                    'label' => 'Kokurikuler Kurmer',
                                    'icon' => 'ri-community-line',
                                    'url' => base_url('walikelas/kokurikuler'),
                                    'visible' => $hasKurmerHomeroom,
                                ],
                                [
                                    'key' => 'homeroom-p5',
                                    'label' => 'Projek P5',
                                    'icon' => 'ri-sparkling-2-line',
                                    'url' => base_url('walikelas/p5'),
                                    'visible' => $hasKurmerHomeroom,
                                ],
                                [
                                    'key' => 'homeroom-p5-print',
                                    'label' => 'Cetak Rapor P5',
                                    'icon' => 'ri-printer-line',
                                    'url' => base_url('walikelas/p5/cetak'),
                                    'visible' => $hasKurmerHomeroom,
                                ],
                                [
                                    'key' => 'homeroom-promotions',
                                    'label' => 'Status Naik Kelas',
                                    'icon' => 'ri-arrow-up-line',
                                    'url' => base_url('walikelas/status-naik-kelas'),
                                    'visible' => $showPromotionMenu,
                                ],
                                [
                                    'key' => 'homeroom-graduations',
                                    'label' => 'Status Kelulusan',
                                    'icon' => 'ri-graduation-cap-line',
                                    'url' => base_url('walikelas/status-lulus'),
                                    'visible' => $showGraduationMenu,
                                ],
                                [
                                    'key' => 'homeroom-graduation-print',
                                    'label' => 'Cetak SKL',
                                    'icon' => 'ri-printer-line',
                                    'url' => base_url('walikelas/status-lulus'),
                                    'visible' => $showGraduationMenu,
                                ],
                                [
                                    'key' => 'homeroom-notes',
                                    'label' => 'Catatan Wali Kelas',
                                    'icon' => 'ri-sticky-note-line',
                                    'url' => base_url('walikelas/catatan'),
                                    'visible' => $showHomeroomNotes,
                                ],
                            ],
                        ],
                    ];

                    $menuGroups = \App\Support\UserModuleRules::filterMenuGroups($menuGroups, $currentUser);
                ?>
                <nav class="space-y-3 px-3 py-4 transition-colors duration-300">
                    <?php foreach ($menuGroups as $group): ?>
                        <?php
                            if (!($group['visible'] ?? true)) {
                                continue;
                            }

                            $visibleItems = array_filter(
                                $group['items'],
                                static function (array $item): bool {
                                    return $item['visible'] ?? true;
                                }
                            );

                            if (empty($visibleItems)) {
                                continue;
                            }

                            $groupId = (string) ($group['id'] ?? ('group-' . md5($group['heading'] ?? uniqid('menu-', true))));
                            $groupHasActive = array_reduce(
                                $visibleItems,
                                static function (bool $carry, array $item) use ($menu): bool {
                                    return $carry || $menu === ($item['key'] ?? '');
                                },
                                false
                            );
                            $shouldOpen = $groupHasActive || ($group['default_open'] ?? false);
                            $containerClasses = 'rounded-2xl border border-slate-200/70 bg-white/0 transition-colors duration-300 dark:border-slate-700/60 dark:bg-transparent';
                            if ($groupHasActive) {
                                $containerClasses .= ' bg-slate-100 dark:bg-slate-800/60 border-indigo-200 dark:border-indigo-500/40';
                            }
                            $buttonClasses = 'flex w-full items-center justify-between px-3 py-3 text-xs font-semibold uppercase tracking-wide transition-colors duration-300';
                            $buttonClasses .= $groupHasActive ? ' text-indigo-600 dark:text-indigo-100' : ' text-slate-500 dark:text-gray-400';
                        ?>
                        <div
                            class="<?= htmlspecialchars($containerClasses, ENT_QUOTES, 'UTF-8') ?>"
                            data-accordion-group
                            data-open="<?= $shouldOpen ? 'true' : 'false' ?>"
                            data-active="<?= $groupHasActive ? 'true' : 'false' ?>"
                            id="<?= htmlspecialchars($groupId, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <button
                                type="button"
                                class="<?= htmlspecialchars($buttonClasses, ENT_QUOTES, 'UTF-8') ?>"
                                data-accordion-button
                                aria-controls="<?= htmlspecialchars($groupId . '-panel', ENT_QUOTES, 'UTF-8') ?>"
                                aria-expanded="<?= $shouldOpen ? 'true' : 'false' ?>"
                            >
                                <span><?= htmlspecialchars($group['heading'], ENT_QUOTES, 'UTF-8') ?></span>
                                <i
                                    class="ri-arrow-down-s-line text-base transition-transform duration-300 <?= $shouldOpen ? 'rotate-180' : '' ?>"
                                    data-accordion-icon
                                ></i>
                            </button>
                            <div
                                id="<?= htmlspecialchars($groupId . '-panel', ENT_QUOTES, 'UTF-8') ?>"
                                class="overflow-hidden transition-all duration-300 ease-in-out"
                                data-accordion-panel
                                data-open="<?= $shouldOpen ? 'true' : 'false' ?>"
                                style="max-height: <?= $shouldOpen ? '999px' : '0px' ?>; opacity: <?= $shouldOpen ? '1' : '0' ?>;"
                                aria-hidden="<?= $shouldOpen ? 'false' : 'true' ?>"
                            >
                                <div class="space-y-1 px-2 pb-3">
                                    <?php foreach ($visibleItems as $item): ?>
                                        <?php $renderMenuItem($item); ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </nav>
            </aside>
            <div
                data-sidebar-overlay
                class="fixed inset-0 z-20 bg-slate-900/50 opacity-0 pointer-events-none transition-opacity duration-300 md:hidden"
            ></div>

            <?php
                $userDisplayName = trim((string) ($currentUser['name'] ?? ''));
                if ($userDisplayName === '') {
                    $userDisplayName = trim((string) ($currentUser['username'] ?? 'Pengguna'));
                }
                if (function_exists('mb_substr')) {
                    $userInitial = mb_substr($userDisplayName, 0, 1, 'UTF-8');
                } else {
                    $userInitial = substr($userDisplayName, 0, 1);
                }
                $userInitial = strtoupper($userInitial !== '' ? $userInitial : 'U');
                $userEmail = trim((string) ($currentUser['email'] ?? ''));
                if ($demoModeEnabled) {
                    $userEmail = \App\Support\DemoMode::maskEmail($userEmail);
                }
                $userRoleLabel = strtoupper((string) ($currentUser['role'] ?? ''));
            ?>
            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 flex items-center justify-between border-b border-slate-200 bg-white/80 px-4 py-4 backdrop-blur transition-colors duration-300 sm:px-6 dark:border-slate-700 dark:bg-gradient-to-b dark:from-slate-900/70 dark:to-slate-900/30">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 p-2 text-slate-500 transition-colors duration-300 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 md:hidden dark:border-slate-700 dark:text-gray-100 dark:hover:bg-slate-700"
                            data-sidebar-toggle
                        >
                            <i class="ri-menu-line text-xl"></i>
                        </button>
                        <h1 class="text-lg font-semibold text-slate-800 dark:text-gray-100"><?= htmlspecialchars($pageTitle ?? $title ?? 'Panel', ENT_QUOTES, 'UTF-8') ?></h1>
                    </div>
                    <div class="flex items-center gap-4">
                        <button
                            type="button"
                            data-pwa-install-button
                            class="hidden items-center gap-2 rounded-lg border border-indigo-200 px-3 py-2 text-sm font-semibold text-indigo-600 transition-all duration-300 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-indigo-500/60 dark:text-indigo-200 dark:hover:bg-indigo-500/10"
                        >
                            <i class="ri-download-2-line text-lg"></i>
                            <span>Pasang Aplikasi</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition-colors duration-300 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:text-gray-100 dark:hover:bg-slate-700"
                            data-theme-toggle
                            aria-label="Mode Terang"
                            title="Mode Terang"
                        >
                            <i class="ri-sun-line text-lg" data-theme-icon="light"></i>
                            <i class="ri-moon-line text-lg hidden" data-theme-icon="dark"></i>
                            <i class="ri-computer-line text-lg hidden" data-theme-icon="system"></i>
                            <span class="hidden sm:inline" data-theme-label>Terang</span>
                        </button>
                        <div class="relative" data-profile-menu>
                            <button
                                type="button"
                                data-profile-trigger
                                class="group inline-flex items-center gap-3 rounded-full border border-slate-200 px-3 py-1.5 text-slate-700 transition-colors duration-300 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:text-gray-100 dark:hover:bg-slate-700"
                                aria-label="Buka menu profil"
                            >
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-500 text-sm font-semibold text-white shadow-sm">
                                    <?= htmlspecialchars($userInitial, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="hidden text-left sm:flex sm:flex-col">
                                    <span class="text-sm font-semibold text-slate-700 dark:text-gray-100"><?= htmlspecialchars($userDisplayName, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($userRoleLabel !== ''): ?>
                                        <span class="text-xs text-slate-400 dark:text-gray-400"><?= htmlspecialchars($userRoleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </span>
                                <i class="ri-arrow-down-s-line text-lg text-slate-400 transition"></i>
                            </button>
                            <div
                                data-profile-dropdown
                                class="absolute right-0 mt-4 hidden w-72 origin-top-right rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600 shadow-xl transition duration-200 ease-out opacity-0 pointer-events-none translate-y-2 dark:border-slate-700 dark:bg-slate-900 dark:text-gray-100"
                            >
                                <div class="flex items-center gap-3 border-b border-slate-100 pb-3 dark:border-slate-800">
                                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-500 text-base font-semibold text-white shadow">
                                        <?= htmlspecialchars($userInitial, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-gray-100"><?= htmlspecialchars($userDisplayName, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if ($userEmail !== ''): ?>
                                            <p class="truncate text-xs text-slate-400 dark:text-gray-400"><?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    </div>
	                                </div>
	                                <nav class="mt-3 space-y-2">
	                                    <?php if ($role === 'guru' && $teacherId !== null): ?>
	                                        <a
	                                            href="<?= htmlspecialchars(base_url('guru/profil'), ENT_QUOTES, 'UTF-8') ?>"
	                                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-gray-100 dark:hover:bg-slate-800"
	                                        >
	                                            <i class="ri-user-settings-line text-lg text-indigo-500"></i>
	                                            Profil Guru Saya
	                                        </a>
	                                    <?php endif; ?>
	                                    <?php if ($role === 'siswa' && !empty($currentUser['student_id'])): ?>
	                                        <a
	                                            href="<?= htmlspecialchars(base_url('siswa/profil'), ENT_QUOTES, 'UTF-8') ?>"
	                                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-gray-100 dark:hover:bg-slate-800"
	                                        >
	                                            <i class="ri-user-3-line text-lg text-indigo-500"></i>
	                                            Profil Siswa Saya
	                                        </a>
	                                    <?php endif; ?>
	                                    <a
	                                        href="<?= htmlspecialchars(base_url('profile'), ENT_QUOTES, 'UTF-8') ?>"
	                                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-gray-100 dark:hover:bg-slate-800"
	                                    >
                                        <i class="ri-user-3-line text-lg text-indigo-500"></i>
                                        Edit Profil
                                    </a>
                                    <a
                                        href="<?= htmlspecialchars(base_url('profile/password'), ENT_QUOTES, 'UTF-8') ?>"
                                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-gray-100 dark:hover:bg-slate-800"
                                    >
                                        <i class="ri-lock-password-line text-lg text-indigo-500"></i>
                                        Ganti Password
                                    </a>
                                </nav>
                            </div>
                        </div>
                        <form action="<?= htmlspecialchars(base_url('logout'), ENT_QUOTES, 'UTF-8') ?>" method="post">
                            <?= csrf_field() ?>
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-lg border border-transparent bg-red-500 px-4 py-2 text-sm font-semibold text-white transition-colors duration-300 hover:bg-red-400 focus:outline-none focus:ring-2 focus:ring-red-400/60 dark:bg-red-500 dark:hover:bg-red-400"
                            >
                                <i class="ri-logout-box-line text-lg"></i>
                                <span class="hidden sm:inline">Keluar</span>
                            </button>
                        </form>
                    </div>
                </header>

                <main class="relative flex-1 px-4 py-6 transition-colors duration-300 sm:px-6 lg:px-10 lg:py-10 xl:px-14 dark:bg-gradient-to-b dark:from-slate-900/80 dark:via-[#0f172a]/70 dark:to-slate-950/80">
                    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 hidden overflow-hidden md:block">
                        <div class="absolute -top-24 -left-20 h-64 w-64 rounded-full bg-indigo-200/40 blur-3xl transition-colors duration-300 dark:bg-indigo-500/15 lg:-top-32 lg:-left-28 lg:h-72 lg:w-72"></div>
                        <div class="absolute top-1/3 -left-16 hidden h-60 w-60 rounded-full bg-sky-200/30 blur-3xl transition-colors duration-300 dark:bg-sky-500/15 xl:block"></div>
                        <div class="absolute -bottom-24 right-[-4rem] h-64 w-64 rounded-full bg-violet-200/30 blur-3xl transition-colors duration-300 dark:bg-violet-500/15 lg:h-72 lg:w-72 lg:right-[-6rem]"></div>
                    </div>
                    <div class="w-full space-y-8 transition-colors duration-300 sm:space-y-10">
                        <?php if ($demoModeEnabled): ?>
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm transition-colors duration-300 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-start gap-3">
                                        <span class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-amber-500 text-lg text-white shadow-sm dark:bg-amber-400 dark:text-amber-950">
                                            <i class="ri-eye-close-line"></i>
                                        </span>
                                        <div>
                                            <p class="font-semibold text-amber-900 dark:text-amber-100">Mode demo aktif</p>
                                            <p class="text-xs text-amber-800/90 dark:text-amber-100/80">
                                                Data sensitif disamarkan untuk keamanan. Gunakan password khusus untuk menonaktifkan mode demo.
                                            </p>
                                        </div>
                                    </div>
                                    <?php if (($role ?? '') === 'admin'): ?>
                                        <a
                                            href="<?= htmlspecialchars(base_url('admin/demo-mode'), ENT_QUOTES, 'UTF-8') ?>"
                                            class="inline-flex items-center gap-2 rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 dark:border-amber-300/40 dark:text-amber-50 dark:hover:bg-amber-500/20"
                                        >
                                            <i class="ri-settings-3-line text-base"></i>
                                            Pengaturan mode demo
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($maintenanceModeEnabled): ?>
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm transition-colors duration-300 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-start gap-3">
                                        <span class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-amber-500 text-lg text-white shadow-sm dark:bg-amber-400 dark:text-amber-950">
                                            <i class="ri-tools-line"></i>
                                        </span>
                                        <div>
                                            <p class="font-semibold text-amber-900 dark:text-amber-100">Maintenance mode aktif</p>
                                            <p class="text-xs text-amber-800/90 dark:text-amber-100/80">
                                                Akses publik dan pengguna non-admin sedang diblokir. Admin tetap dapat menguji aplikasi.
                                            </p>
                                        </div>
                                    </div>
                                    <?php if (($role ?? '') === 'admin'): ?>
                                        <a
                                            href="<?= htmlspecialchars(base_url('admin/maintenance-mode'), ENT_QUOTES, 'UTF-8') ?>"
                                            class="inline-flex items-center gap-2 rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 dark:border-amber-300/40 dark:text-amber-50 dark:hover:bg-amber-500/20"
                                        >
                                            <i class="ri-settings-3-line text-base"></i>
                                            Pengaturan maintenance
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($message = session_flash('success')): ?>
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm transition-colors duration-300 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-200">
                                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($message = session_flash('warning')): ?>
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-sm transition-colors duration-300 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-200">
                                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($message = session_flash('error')): ?>
                            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm transition-colors duration-300 dark:border-rose-500/40 dark:bg-rose-500/15 dark:text-rose-200">
                                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                        <?= $slot ?>
                    </div>
                </main>
            </div>
        </div>
        <?php if ($profileReminderVisible): ?>
            <?php
                $profileReminderTitle = $profileReminderRole === 'siswa'
                    ? 'Lengkapi Profil Siswa Anda'
                    : 'Lengkapi Profil Guru Anda';
                $profileReminderDescription = $profileReminderRole === 'siswa'
                    ? 'Segera lengkapi informasi kontak dan domisili agar pengumuman dan laporan dapat menjangkau Anda.'
                    : 'Lengkapi data kontak dan identitas agar sekolah dapat selalu menghubungi Anda dengan mudah.';
            ?>
            <div
                id="profileCompletionReminderModal"
                class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto bg-slate-900/60 px-4 py-8 backdrop-blur-sm transition-all duration-200"
            >
                <div
                    class="w-full max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl transition-transform duration-200 dark:border-slate-700 dark:bg-slate-900"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="profileCompletionReminderTitle"
                    aria-describedby="profileCompletionReminderDescription"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p id="profileCompletionReminderTitle" class="text-lg font-semibold text-slate-800 dark:text-gray-100">
                                <?= htmlspecialchars($profileReminderTitle, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <p
                                id="profileCompletionReminderDescription"
                                class="mt-1 text-sm text-slate-500 dark:text-slate-300"
                            >
                                <?= htmlspecialchars($profileReminderDescription, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full border border-slate-200 p-2 text-slate-500 transition hover:border-slate-300 hover:text-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-gray-100"
                            aria-label="Tutup pengingat profil"
                            data-profile-reminder-dismiss
                        >
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>
                    <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-900 dark:border-amber-500/80 dark:bg-amber-500/10 dark:text-amber-200">
                        <p class="font-semibold">Bagian profil yang masih kosong:</p>
                        <ul class="mt-3 flex flex-col gap-2 text-sm text-slate-700 dark:text-slate-200">
                            <?php foreach ($profileReminderFields as $field): ?>
                                <li class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                    <?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <form action="<?= htmlspecialchars(base_url('profile'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="flex-1 min-w-[180px]">
                            <button
                                type="submit"
                                class="w-full rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                            >
                                Buka Form Profil
                            </button>
                        </form>
                        <button
                            type="button"
                            class="flex-1 min-w-[140px] rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-slate-700 dark:text-gray-300 dark:hover:border-slate-500 dark:hover:text-white"
                            data-profile-reminder-dismiss
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const modal = document.getElementById('profileCompletionReminderModal');

                    if (!modal) {
                        return;
                    }

                    const dismissButtons = modal.querySelectorAll('[data-profile-reminder-dismiss]');

                    const hideModal = () => {
                        modal.classList.add('pointer-events-none', 'opacity-0');
                        setTimeout(() => modal.remove(), 220);
                    };

                    dismissButtons.forEach((button) => button.addEventListener('click', hideModal));
                });
            </script>
        <?php endif; ?>
        <?php $teacherDefaultPasswordPromptVisible = session_flash('teacher_default_password_prompt') === true; ?>
        <?php if ($teacherDefaultPasswordPromptVisible && ($currentUser['role'] ?? '') === 'guru'): ?>
            <div
                id="teacherDefaultPasswordReminder"
                class="fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto bg-slate-900/70 px-4 py-8 backdrop-blur-sm transition-all duration-200"
                role="dialog"
                aria-modal="true"
                aria-labelledby="teacherDefaultPasswordReminderTitle"
                aria-describedby="teacherDefaultPasswordReminderDescription"
            >
                <div class="w-full max-w-2xl rounded-3xl border border-rose-100 bg-white/95 p-6 text-slate-800 shadow-2xl transition-all duration-200 dark:border-rose-500/40 dark:bg-slate-900 dark:text-gray-100">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex flex-1 items-start gap-3">
                            <div class="inline-flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl bg-rose-100 text-2xl text-rose-600 dark:bg-rose-500/20 dark:text-rose-200">
                                <i class="ri-lock-password-line"></i>
                            </div>
                            <div>
                                <p id="teacherDefaultPasswordReminderTitle" class="text-lg font-semibold text-slate-900 dark:text-gray-100">Ganti Password Default Anda</p>
                                <p id="teacherDefaultPasswordReminderDescription" class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                                    Anda masih menggunakan password bawaan guru. Demi keamanan akun dan data siswa, segera buat password unik sebelum melanjutkan aktivitas.
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-full border border-slate-200 p-2 text-slate-500 transition hover:border-slate-300 hover:text-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-gray-100"
                            aria-label="Tutup pengingat ganti password"
                            data-teacher-password-dismiss
                        >
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>
                    <div class="mt-5 space-y-3 text-sm text-slate-600 dark:text-slate-200">
                        <div class="rounded-2xl border border-rose-100 bg-rose-50/90 px-4 py-3 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200">
                            <p class="font-semibold">Kenapa harus ganti password?</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                                <li>Password default dibagikan ke semua guru sehingga mudah ditebak.</li>
                                <li>Data nilai, presensi, dan catatan siswa lebih aman dengan password pribadi.</li>
                            </ul>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Pengingat ini boleh diabaikan, namun akan tampil kembali setiap Anda login selama password belum diganti.</p>
                    </div>
                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <form action="<?= htmlspecialchars(base_url('profile/password'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="flex-1 min-w-[180px]">
                            <button
                                type="submit"
                                class="w-full rounded-2xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-rose-600/30 transition hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-400"
                            >
                                Ganti Password Sekarang
                            </button>
                        </form>
                        <button
                            type="button"
                            class="flex-1 min-w-[160px] rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-slate-700 dark:text-gray-300 dark:hover:border-slate-500 dark:hover:text-white"
                            data-teacher-password-dismiss
                        >
                            Nanti Saja
                        </button>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const modal = document.getElementById('teacherDefaultPasswordReminder');

                    if (!modal) {
                        return;
                    }

                    const dismissButtons = modal.querySelectorAll('[data-teacher-password-dismiss]');

                    const hideModal = () => {
                        modal.classList.add('pointer-events-none', 'opacity-0');
                        setTimeout(() => modal.remove(), 220);
                    };

                    dismissButtons.forEach((button) => button.addEventListener('click', hideModal));
                });
            </script>
        <?php endif; ?>
        <div
            class="pointer-events-none fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/55 px-4 opacity-0 backdrop-blur-sm transition-opacity duration-200"
            data-global-progress-overlay
            aria-hidden="true"
        >
            <div class="w-full max-w-sm rounded-2xl border border-white/20 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-200">
                        <i class="ri-loader-4-line animate-spin text-xl"></i>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-gray-100" data-global-progress-title>Memproses...</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-300" data-global-progress-message>Mohon tunggu sampai proses selesai.</p>
                    </div>
                </div>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="h-full rounded-full bg-indigo-600 transition-all duration-200" style="width: 0%" data-global-progress-bar></div>
                </div>
                <p class="mt-2 text-right text-xs font-semibold text-slate-500 dark:text-slate-300" data-global-progress-percent>0%</p>
            </div>
        </div>
        <script>
            (() => {
                const overlay = document.querySelector("[data-global-progress-overlay]");
                const bar = document.querySelector("[data-global-progress-bar]");
                const percentLabel = document.querySelector("[data-global-progress-percent]");
                const title = document.querySelector("[data-global-progress-title]");
                const message = document.querySelector("[data-global-progress-message]");
                let fakeTimer = null;
                let currentPercent = 0;

                const setProgress = (percent) => {
                    currentPercent = Math.max(currentPercent, Math.min(100, Math.round(percent)));
                    if (bar) {
                        bar.style.width = `${currentPercent}%`;
                    }
                    if (percentLabel) {
                        percentLabel.textContent = `${currentPercent}%`;
                    }
                };

                const showProgress = (options = {}) => {
                    currentPercent = 0;
                    if (title) {
                        title.textContent = options.title || "Memproses...";
                    }
                    if (message) {
                        message.textContent = options.message || "Mohon tunggu sampai proses selesai.";
                    }
                    setProgress(options.initial || 8);
                    overlay?.classList.remove("pointer-events-none", "opacity-0");
                    overlay?.classList.add("opacity-100");
                };

                const startFakeProgress = () => {
                    window.clearInterval(fakeTimer);
                    fakeTimer = window.setInterval(() => {
                        if (currentPercent >= 92) {
                            window.clearInterval(fakeTimer);
                            return;
                        }
                        setProgress(currentPercent + Math.max(1, Math.round((92 - currentPercent) * 0.12)));
                    }, 180);
                };

                const finishProgress = () => {
                    window.clearInterval(fakeTimer);
                    setProgress(100);
                };

                document.addEventListener("submit", (event) => {
                    if (event.defaultPrevented) {
                        return;
                    }

                    const form = event.target;
                    if (!(form instanceof HTMLFormElement) || form.dataset.noProgress === "true" || form.target) {
                        return;
                    }

                    const method = (form.method || "get").toLowerCase();
                    if (method === "get") {
                        showProgress({ title: "Memuat...", message: "Mengambil data terbaru.", initial: 15 });
                        startFakeProgress();
                        return;
                    }

                    if (form.dataset.progressSubmitting === "true") {
                        return;
                    }

                    const hasFile = Array.from(form.querySelectorAll('input[type="file"]')).some((input) => input.files && input.files.length > 0);
                    const isMultipart = (form.enctype || "").toLowerCase() === "multipart/form-data";

                    if (!isMultipart || !hasFile) {
                        showProgress({ title: "Menyimpan...", message: "Mengirim data ke server.", initial: 12 });
                        startFakeProgress();
                        return;
                    }

                    event.preventDefault();
                    showProgress({ title: "Mengunggah...", message: "Mengirim berkas ke server.", initial: 0 });

                    const xhr = new XMLHttpRequest();
                    xhr.open(method.toUpperCase(), form.action || window.location.href, true);
                    xhr.upload.addEventListener("progress", (progressEvent) => {
                        if (!progressEvent.lengthComputable) {
                            startFakeProgress();
                            return;
                        }
                        setProgress((progressEvent.loaded / progressEvent.total) * 95);
                    });
                    xhr.addEventListener("load", () => {
                        finishProgress();
                        window.setTimeout(() => {
                            if (xhr.responseURL) {
                                window.history.replaceState(null, "", xhr.responseURL);
                            }
                            document.open();
                            document.write(xhr.responseText);
                            document.close();
                        }, 180);
                    });
                    xhr.addEventListener("error", () => {
                        if (message) {
                            message.textContent = "Upload gagal. Silakan coba kembali.";
                        }
                    });
                    xhr.send(new FormData(form));
                });
            })();
        </script>
        <script>
            (function () {
                if (!("serviceWorker" in navigator)) {
                    return;
                }

                const swUrl = <?= json_encode(asset('service-worker.js'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
                const installButton = document.querySelector("[data-pwa-install-button]");

                const toggleInstallButton = (visible) => {
                    if (!installButton) {
                        return;
                    }
                    if (visible) {
                        installButton.classList.remove("hidden");
                        installButton.classList.add("inline-flex");
                    } else {
                        installButton.classList.add("hidden");
                        installButton.classList.remove("inline-flex");
                    }
                };

                window.addEventListener("load", () => {
                    navigator.serviceWorker.register(swUrl).catch((error) => {
                        console.error("Service worker registration failed:", error);
                    });
                });

                window.addEventListener("beforeinstallprompt", (event) => {
                    event.preventDefault();
                    window.deferredPwaPrompt = event;
                    window.dispatchEvent(new CustomEvent("pwa:installprompt", { detail: event }));
                    toggleInstallButton(true);
                    return false;
                });

                window.addEventListener("appinstalled", () => {
                    window.deferredPwaPrompt = null;
                    window.dispatchEvent(new Event("pwa:installed"));
                    toggleInstallButton(false);
                });

                if (installButton) {
                    installButton.addEventListener("click", async () => {
                        const promptEvent = window.deferredPwaPrompt;
                        if (!promptEvent) {
                            toggleInstallButton(false);
                            return;
                        }

                        promptEvent.prompt();
                        const { outcome } = await promptEvent.userChoice.catch(() => ({ outcome: "dismissed" }));
                        if (outcome === "accepted") {
                            window.deferredPwaPrompt = null;
                            toggleInstallButton(false);
                        }
                    });

                    window.addEventListener("pwa:installed", () => toggleInstallButton(false));
                }
            })();
        </script>
    </body>
</html>
