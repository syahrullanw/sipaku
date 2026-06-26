<?php

namespace App\Support;

class SimpleXlsxBuilder
{
    /**
     * @param array<int, array<int|string|float|bool|null>> $rows
     * @param array<string, mixed> $options
     */
    public static function build(array $rows, string $sheetName = 'Sheet1', array $options = []): string
    {
        return self::buildSheets([
            [
                'name' => $sheetName,
                'rows' => $rows,
                'options' => $options,
            ],
        ]);
    }

    /**
     * @param array<int, array{name?: string, rows: array<int, array<int|string|float|bool|null>>, options?: array<string, mixed>}> $sheets
     */
    public static function buildSheets(array $sheets): string
    {
        if (empty($sheets)) {
            $sheets = [
                [
                    'name' => 'Sheet1',
                    'rows' => [['']],
                    'options' => [],
                ],
            ];
        }

        $sheetData = [];
        $sheetNames = [];
        $sharedStrings = [];
        $sharedIndex = [];
        $styleRegistry = self::defaultStyleRegistry();

        foreach ($sheets as $index => $sheet) {
            $name = self::uniqueSheetName(
                self::sanitizeSheetName((string) ($sheet['name'] ?? ('Sheet' . ($index + 1)))),
                $sheetNames
            );
            $rows = $sheet['rows'] ?? [['']];
            $options = $sheet['options'] ?? [];
            $sheetNames[] = $name;
            $sheetData[] = self::buildSheetXml($rows, $options, $sharedStrings, $sharedIndex, $styleRegistry);
        }

        $sharedStringsXml = self::buildSharedStringsXml($sharedStrings);
        $workbookXml = self::buildWorkbookXml($sheetNames);
        $workbookRelsXml = self::buildWorkbookRelsXml(count($sheetNames));
        $contentTypesXml = self::buildContentTypesXml(count($sheetNames));
        $rootRelsXml = self::buildRootRelsXml();
        $stylesXml = self::buildStylesXml($styleRegistry);
        $appXml = self::buildAppXml($sheetNames);
        $coreXml = self::buildCoreXml();

        $zipFile = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($zipFile === false) {
            return '';
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($zipFile, \ZipArchive::OVERWRITE);

        if ($opened !== true) {
            return '';
        }

        $zip->addFromString('[Content_Types].xml', $contentTypesXml);
        $zip->addFromString('_rels/.rels', $rootRelsXml);
        $zip->addFromString('docProps/app.xml', $appXml);
        $zip->addFromString('docProps/core.xml', $coreXml);
        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
        foreach ($sheetData as $index => $sheetXml) {
            $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', $sheetXml);
        }
        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        $zip->addFromString('xl/styles.xml', $stylesXml);
        $zip->close();

        $data = file_get_contents($zipFile);
        @unlink($zipFile);

        return $data === false ? '' : $data;
    }

    /**
     * @param array<int, array<int|string|float|bool|null>> $rows
     * @param array<string, mixed> $options
     * @param array<int, string> $sharedStrings
     * @param array<string, int> $sharedIndex
     * @param array<string, mixed> $styleRegistry
     */
    private static function buildSheetXml(array $rows, array $options, array &$sharedStrings, array &$sharedIndex, array &$styleRegistry): string
    {
        if (empty($rows)) {
            $rows = [['']];
        }

        $normalizedRows = [];
        $maxColumns = 0;

        foreach ($rows as $row) {
            $normalizedRow = [];
            foreach ($row as $cell) {
                if (is_bool($cell)) {
                    $normalizedRow[] = $cell ? 'TRUE' : 'FALSE';
                } elseif (is_scalar($cell) && $cell !== null) {
                    $normalizedRow[] = (string) $cell;
                } else {
                    $normalizedRow[] = '';
                }
            }
            $maxColumns = max($maxColumns, count($normalizedRow));
            $normalizedRows[] = $normalizedRow;
        }

        if ($maxColumns === 0) {
            $maxColumns = 1;
        }

        $rowsXml = '';
        $rowNumber = 1;
        $highlightCells = self::normalizeCellMap($options['highlight_cells'] ?? []);
        $styleCells = self::normalizeStyleMap($options['cell_styles'] ?? [], $styleRegistry);
        $rowHeights = self::normalizeNumberMap($options['row_heights'] ?? []);

        foreach ($normalizedRows as $row) {
            $rowAttrs = ' r="' . $rowNumber . '"';
            if (isset($rowHeights[$rowNumber])) {
                $rowAttrs .= ' ht="' . self::formatNumber($rowHeights[$rowNumber]) . '" customHeight="1"';
            }
            $rowsXml .= '<row' . $rowAttrs . '>';
            $hasCell = false;

            foreach ($row as $columnIndex => $value) {
                $cellRef = self::columnLetter($columnIndex + 1) . $rowNumber;
                $isHighlighted = isset($highlightCells[$cellRef]);
                $styleId = $styleCells[$cellRef] ?? ($isHighlighted ? 1 : 0);

                if ($value === '' && $styleId === 0) {
                    continue;
                }

                $styleAttr = $styleId > 0 ? ' s="' . $styleId . '"' : '';
                if ($value === '') {
                    $rowsXml .= '<c r="' . $cellRef . '"' . $styleAttr . '/>';
                    $hasCell = true;
                    continue;
                }

                if (!array_key_exists($value, $sharedIndex)) {
                    $sharedIndex[$value] = count($sharedStrings);
                    $sharedStrings[] = $value;
                }

                $rowsXml .= '<c r="' . $cellRef . '"' . $styleAttr . ' t="s"><v>' . $sharedIndex[$value] . '</v></c>';
                $hasCell = true;
            }

            if (!$hasCell) {
                // Excel expects empty rows to exist explicitly when dimension is larger.
            }
            $rowsXml .= '</row>';
            $rowNumber++;
        }

        if ($rowsXml === '') {
            $rowsXml = '<row r="1"/>';
        }

        $colsXml = self::buildColsXml($options['column_widths'] ?? []);
        $mergesXml = self::buildMergesXml($options['merges'] ?? []);
        $pageXml = self::buildPageXml($options['page_setup'] ?? []);
        $dimension = sprintf(
            'A1:%s%d',
            self::columnLetter($maxColumns),
            max(1, count($normalizedRows)),
        );

        $sheetXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <dimension ref="{$dimension}"/>
    {$colsXml}
    <sheetData>{$rowsXml}</sheetData>
    {$mergesXml}
    {$pageXml}
</worksheet>
XML;

        return $sheetXml;
    }

    private static function buildSharedStringsXml(array $sharedStrings): string
    {
        $total = count($sharedStrings);
        $items = '';

        foreach ($sharedStrings as $string) {
            $needsPreserve = trim($string) !== $string;
            $escaped = htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $items .= '<si><t' . ($needsPreserve ? ' xml:space="preserve"' : '') . '>' . $escaped . '</t></si>';
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="{$total}" uniqueCount="{$total}">
    {$items}
</sst>
XML;
    }

    /**
     * @param array<int, string> $sheetNames
     */
    private static function buildWorkbookXml(array $sheetNames): string
    {
        $sheetsXml = '';
        foreach ($sheetNames as $index => $sheetName) {
            $sheetId = $index + 1;
            $escapedName = htmlspecialchars($sheetName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $sheetsXml .= '<sheet name="' . $escapedName . '" sheetId="' . $sheetId . '" r:id="rId' . $sheetId . '"/>';
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>{$sheetsXml}</sheets>
</workbook>
XML;
    }

    private static function buildWorkbookRelsXml(int $sheetCount): string
    {
        $relationships = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships .= '<Relationship Id="rId' . $index . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $index . '.xml"/>';
        }
        $relationships .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        $relationships .= '<Relationship Id="rId' . ($sheetCount + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    {$relationships}
</Relationships>
XML;
    }

    private static function buildContentTypesXml(int $sheetCount): string
    {
        $worksheetOverrides = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $worksheetOverrides .= '<Override PartName="/xl/worksheets/sheet' . $index . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    {$worksheetOverrides}
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;
    }

    /**
     * @param array<string, mixed> $styleRegistry
     */
    private static function buildStylesXml(array $styleRegistry): string
    {
        $fonts = [];
        $fontIndexes = [];
        $fills = [
            ['kind' => 'none'],
            ['kind' => 'gray125'],
        ];
        $fillIndexes = [
            'none' => 0,
            'gray125' => 1,
        ];
        $borders = [];
        $borderIndexes = [];
        $xfs = [];

        foreach ($styleRegistry['styles'] as $style) {
            $font = [
                'bold' => !empty($style['bold']),
                'size' => (float) ($style['font_size'] ?? 11),
                'name' => (string) ($style['font_name'] ?? 'Calibri'),
            ];
            $fontKey = json_encode($font);
            if (!isset($fontIndexes[$fontKey])) {
                $fontIndexes[$fontKey] = count($fonts);
                $fonts[] = $font;
            }

            $fillColor = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', (string) ($style['fill'] ?? '')));
            $fillKey = $fillColor !== '' ? 'fill:' . $fillColor : 'none';
            if (!isset($fillIndexes[$fillKey])) {
                $fillIndexes[$fillKey] = count($fills);
                $fills[] = ['kind' => 'solid', 'color' => $fillColor];
            }

            $borderKey = !empty($style['border']) ? 'thin' : 'none';
            if (!isset($borderIndexes[$borderKey])) {
                $borderIndexes[$borderKey] = count($borders);
                $borders[] = ['kind' => $borderKey];
            }

            $xfs[] = [
                'fontId' => $fontIndexes[$fontKey],
                'fillId' => $fillIndexes[$fillKey],
                'borderId' => $borderIndexes[$borderKey],
                'fill' => $fillKey !== 'none',
                'font' => !empty($style['bold']) || (float) ($style['font_size'] ?? 11) !== 11.0,
                'border' => $borderKey !== 'none',
                'horizontal' => (string) ($style['align'] ?? ''),
                'vertical' => (string) ($style['valign'] ?? 'center'),
                'wrap' => !empty($style['wrap']),
            ];
        }

        $fontsXml = '';
        foreach ($fonts as $font) {
            $fontsXml .= '<font>';
            if (!empty($font['bold'])) {
                $fontsXml .= '<b/>';
            }
            $fontsXml .= '<sz val="' . self::formatNumber((float) $font['size']) . '"/>';
            $fontsXml .= '<name val="' . htmlspecialchars((string) $font['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"/>';
            $fontsXml .= '<family val="2"/>';
            $fontsXml .= '</font>';
        }

        $fillsXml = '';
        foreach ($fills as $fill) {
            if (($fill['kind'] ?? '') === 'gray125') {
                $fillsXml .= '<fill><patternFill patternType="gray125"/></fill>';
                continue;
            }
            if (($fill['kind'] ?? '') === 'solid') {
                $color = str_pad((string) ($fill['color'] ?? 'FFFFFF'), 6, 'F');
                $fillsXml .= '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $color . '"/><bgColor indexed="64"/></patternFill></fill>';
                continue;
            }
            $fillsXml .= '<fill><patternFill patternType="none"/></fill>';
        }

        $bordersXml = '';
        foreach ($borders as $border) {
            if (($border['kind'] ?? '') === 'thin') {
                $bordersXml .= '<border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border>';
                continue;
            }
            $bordersXml .= '<border><left/><right/><top/><bottom/><diagonal/></border>';
        }

        $xfsXml = '';
        foreach ($xfs as $xf) {
            $attrs = sprintf(
                ' numFmtId="0" fontId="%d" fillId="%d" borderId="%d" xfId="0"%s%s%s',
                (int) $xf['fontId'],
                (int) $xf['fillId'],
                (int) $xf['borderId'],
                !empty($xf['font']) ? ' applyFont="1"' : '',
                !empty($xf['fill']) ? ' applyFill="1"' : '',
                !empty($xf['border']) ? ' applyBorder="1"' : ''
            );
            $alignmentAttrs = [];
            if (($xf['horizontal'] ?? '') !== '') {
                $alignmentAttrs[] = 'horizontal="' . htmlspecialchars((string) $xf['horizontal'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
            if (($xf['vertical'] ?? '') !== '') {
                $alignmentAttrs[] = 'vertical="' . htmlspecialchars((string) $xf['vertical'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
            if (!empty($xf['wrap'])) {
                $alignmentAttrs[] = 'wrapText="1"';
            }

            if (!empty($alignmentAttrs)) {
                $xfsXml .= '<xf' . $attrs . ' applyAlignment="1"><alignment ' . implode(' ', $alignmentAttrs) . '/></xf>';
            } else {
                $xfsXml .= '<xf' . $attrs . '/>';
            }
        }

        $fontCount = count($fonts);
        $fillCount = count($fills);
        $borderCount = count($borders);
        $xfCount = count($xfs);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="{$fontCount}">{$fontsXml}</fonts>
    <fills count="{$fillCount}">{$fillsXml}</fills>
    <borders count="{$borderCount}">{$bordersXml}</borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="{$xfCount}">{$xfsXml}</cellXfs>
    <cellStyles count="1">
        <cellStyle name="Normal" xfId="0" builtinId="0"/>
    </cellStyles>
</styleSheet>
XML;
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultStyleRegistry(): array
    {
        return [
            'styles' => [
                [],
                ['fill' => 'FFFF00'],
            ],
            'index' => [
                self::styleKey([]) => 0,
                self::styleKey(['fill' => 'FFFF00']) => 1,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $style
     * @param array<string, mixed> $styleRegistry
     */
    private static function registerStyle(array $style, array &$styleRegistry): int
    {
        $normalized = self::normalizeStyle($style);
        $key = self::styleKey($normalized);
        if (!isset($styleRegistry['index'][$key])) {
            $styleRegistry['index'][$key] = count($styleRegistry['styles']);
            $styleRegistry['styles'][] = $normalized;
        }

        return (int) $styleRegistry['index'][$key];
    }

    /**
     * @param array<string, mixed> $style
     * @return array<string, mixed>
     */
    private static function normalizeStyle(array $style): array
    {
        $normalized = [];
        foreach (['fill', 'align', 'valign', 'font_name'] as $key) {
            if (isset($style[$key]) && trim((string) $style[$key]) !== '') {
                $normalized[$key] = trim((string) $style[$key]);
            }
        }
        foreach (['bold', 'border', 'wrap'] as $key) {
            if (!empty($style[$key])) {
                $normalized[$key] = true;
            }
        }
        if (isset($style['font_size']) && (float) $style['font_size'] > 0) {
            $normalized['font_size'] = (float) $style['font_size'];
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $style
     */
    private static function styleKey(array $style): string
    {
        ksort($style);

        return json_encode($style, JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    private static function buildRootRelsXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;
    }

    /**
     * @param array<int, string> $sheetNames
     */
    private static function buildAppXml(array $sheetNames): string
    {
        $sheetCount = count($sheetNames);
        $titleItems = '';
        foreach ($sheetNames as $sheetName) {
            $escapedName = htmlspecialchars($sheetName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $titleItems .= '<vt:lpstr>' . $escapedName . '</vt:lpstr>';
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
    <Application>Siakad</Application>
    <DocSecurity>0</DocSecurity>
    <ScaleCrop>false</ScaleCrop>
    <HeadingPairs>
        <vt:vector size="2" baseType="variant">
            <vt:variant>
                <vt:lpstr>Worksheets</vt:lpstr>
            </vt:variant>
            <vt:variant>
                <vt:i4>{$sheetCount}</vt:i4>
            </vt:variant>
        </vt:vector>
    </HeadingPairs>
    <TitlesOfParts>
        <vt:vector size="{$sheetCount}" baseType="lpstr">
            {$titleItems}
        </vt:vector>
    </TitlesOfParts>
    <Company></Company>
    <LinksUpToDate>false</LinksUpToDate>
    <SharedDoc>false</SharedDoc>
    <HyperlinksChanged>false</HyperlinksChanged>
    <AppVersion>16.0000</AppVersion>
</Properties>
XML;
    }

    private static function buildCoreXml(): string
    {
        $now = date('c');

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:creator>Siakad</dc:creator>
    <cp:lastModifiedBy>Siakad</cp:lastModifiedBy>
    <dcterms:created xsi:type="dcterms:W3CDTF">{$now}</dcterms:created>
    <dcterms:modified xsi:type="dcterms:W3CDTF">{$now}</dcterms:modified>
</cp:coreProperties>
XML;
    }

    private static function columnLetter(int $columnNumber): string
    {
        $letter = '';

        while ($columnNumber > 0) {
            $remainder = ($columnNumber - 1) % 26;
            $letter = chr(65 + $remainder) . $letter;
            $columnNumber = (int) (($columnNumber - $remainder) / 26);
        }

        return $letter;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed> $styleRegistry
     * @return array<string, int>
     */
    private static function normalizeStyleMap(mixed $value, array &$styleRegistry): array
    {
        if (!is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $cellOrRange => $style) {
            if (!is_array($style)) {
                continue;
            }
            $styleId = self::registerStyle($style, $styleRegistry);
            foreach (self::expandCellRange((string) $cellOrRange) as $cellRef) {
                $map[$cellRef] = $styleId;
            }
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private static function expandCellRange(string $cellOrRange): array
    {
        $cellOrRange = strtoupper(trim($cellOrRange));
        if (preg_match('/^[A-Z]+[1-9][0-9]*$/', $cellOrRange)) {
            return [$cellOrRange];
        }
        if (!preg_match('/^([A-Z]+)([1-9][0-9]*):([A-Z]+)([1-9][0-9]*)$/', $cellOrRange, $matches)) {
            return [];
        }

        $startColumn = self::columnNumber($matches[1]);
        $startRow = (int) $matches[2];
        $endColumn = self::columnNumber($matches[3]);
        $endRow = (int) $matches[4];
        if ($startColumn <= 0 || $endColumn < $startColumn || $endRow < $startRow) {
            return [];
        }

        $cells = [];
        for ($row = $startRow; $row <= $endRow; $row++) {
            for ($column = $startColumn; $column <= $endColumn; $column++) {
                $cells[] = self::columnLetter($column) . $row;
            }
        }

        return $cells;
    }

    private static function columnNumber(string $letters): int
    {
        $number = 0;
        foreach (str_split(strtoupper($letters)) as $letter) {
            $number = ($number * 26) + (ord($letter) - 64);
        }

        return $number;
    }

    /**
     * @param mixed $value
     * @return array<int, float>
     */
    private static function normalizeNumberMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $key => $number) {
            $index = (int) $key;
            if ($index > 0 && is_numeric($number)) {
                $map[$index] = (float) $number;
            }
        }

        return $map;
    }

    private static function buildColsXml(mixed $value): string
    {
        if (!is_array($value) || empty($value)) {
            return '';
        }

        $cols = '';
        foreach ($value as $key => $width) {
            $index = is_numeric($key) ? (int) $key : self::columnNumber((string) $key);
            if ($index <= 0 || !is_numeric($width)) {
                continue;
            }
            $widthValue = self::formatNumber((float) $width);
            $cols .= '<col min="' . $index . '" max="' . $index . '" width="' . $widthValue . '" customWidth="1"/>';
        }

        return $cols !== '' ? '<cols>' . $cols . '</cols>' : '';
    }

    private static function buildMergesXml(mixed $value): string
    {
        if (!is_array($value) || empty($value)) {
            return '';
        }

        $merges = '';
        foreach ($value as $range) {
            $range = strtoupper(trim((string) $range));
            if (!preg_match('/^[A-Z]+[1-9][0-9]*:[A-Z]+[1-9][0-9]*$/', $range)) {
                continue;
            }
            $merges .= '<mergeCell ref="' . $range . '"/>';
        }

        return $merges !== '' ? '<mergeCells count="' . substr_count($merges, '<mergeCell') . '">' . $merges . '</mergeCells>' : '';
    }

    private static function buildPageXml(mixed $value): string
    {
        if (!is_array($value) || empty($value)) {
            return '';
        }

        $orientation = in_array(($value['orientation'] ?? ''), ['portrait', 'landscape'], true)
            ? (string) $value['orientation']
            : 'landscape';
        $paperSize = (int) ($value['paper_size'] ?? 9);
        $setup = '<pageSetup paperSize="' . $paperSize . '" orientation="' . $orientation . '" fitToWidth="1" fitToHeight="0"/>';
        $margins = '<pageMargins left="0.25" right="0.25" top="0.25" bottom="0.25" header="0" footer="0"/>';

        return $margins . $setup;
    }

    private static function formatNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private static function sanitizeSheetName(string $sheetName): string
    {
        $name = preg_replace('/[:\\\\\\/\\?\\*\\[\\]]/', '', $sheetName);
        $name = $name === null ? 'Sheet1' : $name;
        $name = trim($name);
        if ($name === '') {
            $name = 'Sheet1';
        }

        return substr($name, 0, 31);
    }

    /**
     * @param array<int, string> $usedNames
     */
    private static function uniqueSheetName(string $name, array $usedNames): string
    {
        if (!in_array($name, $usedNames, true)) {
            return $name;
        }

        $suffix = 2;
        do {
            $suffixText = ' ' . $suffix;
            $candidate = substr($name, 0, 31 - strlen($suffixText)) . $suffixText;
            $suffix++;
        } while (in_array($candidate, $usedNames, true));

        return $candidate;
    }

    /**
     * @param mixed $value
     * @return array<string, true>
     */
    private static function normalizeCellMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $cell) {
            $cellRef = strtoupper(trim((string) $cell));
            if ($cellRef === '') {
                continue;
            }
            if (!preg_match('/^[A-Z]+[1-9][0-9]*$/', $cellRef)) {
                continue;
            }
            $map[$cellRef] = true;
        }

        return $map;
    }
}
