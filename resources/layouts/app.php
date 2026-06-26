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
        <title><?= htmlspecialchars($title ?? config('app.name', 'Aplikasi Sekolah'), ENT_QUOTES, 'UTF-8') ?></title>
        <link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($appleIconHref, ENT_QUOTES, 'UTF-8') ?>" />
        <link rel="icon" type="image/png" sizes="192x192" href="<?= htmlspecialchars($icon192Href, ENT_QUOTES, 'UTF-8') ?>" />
        <link rel="icon" type="image/png" sizes="512x512" href="<?= htmlspecialchars($icon512Href, ENT_QUOTES, 'UTF-8') ?>" />
        <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/app.css'), ENT_QUOTES, 'UTF-8') ?>" />
    </head>
    <body class="antialiased bg-slate-100 text-slate-900">
        <main>
            <?= $slot ?>
        </main>
    </body>
</html>
