<?php
    /** @var array<int, array<string, mixed>> $reports */
    /** @var array<string, mixed> $class */
    /** @var int $semester */
    /** @var string $section */
    $reports = $reports ?? [];
    $section = $section ?? 'grade';
    $semesterLabel = $semester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
    $className = trim(($class['tingkat'] ?? '') . ' ' . ($class['nama'] ?? ''));
    $className = $className !== '' ? $className : 'Kelas';

    $sectionFile = __DIR__ . '/' . basename($section) . '.php';
    if (!file_exists($sectionFile)) {
        echo '<p style="text-align:center;font-size:12pt;color:#64748b;">Bagian raport tidak ditemukan.</p>';
        return;
    }

    if (empty($reports)): ?>
    <p style="text-align:center;font-size:12pt;color:#64748b;">Tidak ada data raport yang dapat dicetak.</p>
<?php else: ?>
    <?php foreach ($reports as $index => $report): ?>
        <?php if ($index > 0): ?>
            <div style="page-break-before: always;"></div>
        <?php endif; ?>
        <?php $showPageBreak = false; ?>
        <?php include $sectionFile; ?>
    <?php endforeach; ?>
<?php endif; ?>
