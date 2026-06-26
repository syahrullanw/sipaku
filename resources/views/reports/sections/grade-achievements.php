<?php
    $sections = [
        'grade.php',
        'achievements.php',
    ];
?>
<?php foreach ($sections as $index => $section): ?>
    <?php if ($index > 0): ?>
        <div style="page-break-before: always;"></div>
    <?php endif; ?>
    <?php include __DIR__ . '/' . $section; ?>
<?php endforeach; ?>
