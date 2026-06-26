<?php
    /** @var array<int, array<string, mixed>> $reports */
    /** @var array<string, mixed> $class */
    /** @var array<string, mixed>|null $schoolYear */
    /** @var string $semesterLabel */
    $reports = $reports ?? [];
    $class = $class ?? [];
    $schoolYear = $schoolYear ?? null;
    $semesterLabel = $semesterLabel ?? 'Semester 1 (Ganjil)';
    $className = trim(($class['tingkat'] ?? '') . ' ' . ($class['nama'] ?? ''));
    $className = $className !== '' ? $className : 'Kelas';
    $schoolYearName = is_array($schoolYear) ? ($schoolYear['nama'] ?? '-') : ($schoolYear ?: '-');
?>
<?php if (empty($reports)): ?>
    <p style="text-align:center;font-size:12pt;color:#64748b;">Tidak ada data raport yang dapat dicetak.</p>
<?php else: ?>
    <?php foreach ($reports as $index => $report): ?>
        <?php
            $showPageBreak = $index > 0;
            include __DIR__ . '/../partials/grade-report.php';
        ?>
    <?php endforeach; ?>
<?php endif; ?>
