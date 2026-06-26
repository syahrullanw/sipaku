<!DOCTYPE html>
<html lang="id">
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
        <title><?= htmlspecialchars($title ?? 'Masuk', ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(config('app.name'), ENT_QUOTES, 'UTF-8') ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet" />
        <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ["Inter", "-apple-system", "BlinkMacSystemFont", "Segoe UI", "sans-serif"],
                        },
                    },
                },
                corePlugins: {
                    preflight: true,
                },
            };
        </script>
        <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/app.css'), ENT_QUOTES, 'UTF-8') ?>" />
        <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/login-animated.css') . '?v=head-follow-20260428', ENT_QUOTES, 'UTF-8') ?>" />
    </head>
    <body class="auth-layout min-h-screen flex items-center justify-center">
        <main class="w-full max-w-lg px-6 py-8">
            <?= $slot ?>
        </main>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/1.20.3/TweenMax.min.js" defer></script>
        <script src="<?= htmlspecialchars(asset('js/animated-login.js') . '?v=head-follow-20260428', ENT_QUOTES, 'UTF-8') ?>" defer></script>
        <script>
            (function () {
                if (!("serviceWorker" in navigator)) {
                    return;
                }

                const swUrl = <?= json_encode(asset('service-worker.js'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

                window.addEventListener("load", () => {
                    navigator.serviceWorker.register(swUrl).catch((error) => {
                        console.error("Service worker registration failed:", error);
                    });
                });

                window.addEventListener("beforeinstallprompt", (event) => {
                    event.preventDefault();
                    window.deferredPwaPrompt = event;
                    window.dispatchEvent(new CustomEvent("pwa:installprompt", { detail: event }));
                    return false;
                });

                window.addEventListener("appinstalled", () => {
                    window.deferredPwaPrompt = null;
                    window.dispatchEvent(new Event("pwa:installed"));
                });
            })();
        </script>
    </body>
</html>
