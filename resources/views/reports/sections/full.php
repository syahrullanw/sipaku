<?php
    /** @var array<string, mixed> $report */
    $report = $report ?? [];
    $sections = [
        'cover.php',
        'school-info.php',
        'biodata.php',
        'grade.php',
    ];
    $curriculum = $report['curriculum'] ?? 'k13';
    if ($curriculum === 'kurmer') {
        $sections[] = 'p5.php';
    }
    $sections[] = 'achievements.php';
?>
<?php foreach ($sections as $index => $section): ?>
    <?php if ($index > 0): ?>
        <div style="page-break-before: always;"></div>
    <?php endif; ?>
    <?php include __DIR__ . '/' . $section; ?>
<?php endforeach; ?>
