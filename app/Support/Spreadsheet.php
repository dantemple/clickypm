<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use RuntimeException;
use ZipArchive;

/**
 * Reads the cells of an .xlsx workbook without any spreadsheet library:
 * the file is a zip of XML parts, and we only need values, not formatting.
 */
class Spreadsheet
{
    private const MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * Every sheet in the workbook, in order, as rows of cells keyed by
     * column letter. Empty cells are left out.
     *
     * @return array<int, array{name: string, rows: array<int, array<string, string|float>>}>
     */
    public static function read(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Could not open {$path} as a spreadsheet.");
        }

        try {
            $strings = self::sharedStrings($zip);
            $targets = self::relationshipTargets($zip);
            $sheets = [];

            foreach (self::elements(self::part($zip, 'xl/workbook.xml'), 'sheet') as $sheet) {
                $id = $sheet->getAttributeNS(self::RELATIONSHIPS, 'id');
                $target = $targets[$id] ?? null;

                if ($target === null) {
                    continue;
                }

                $sheets[] = [
                    'name' => $sheet->getAttribute('name'),
                    'rows' => self::rows(self::part($zip, $target), $strings),
                ];
            }

            return $sheets;
        } finally {
            $zip->close();
        }
    }

    /**
     * @param  array<int, string>  $strings
     * @return array<int, array<string, string|float>>
     */
    private static function rows(DOMDocument $sheet, array $strings): array
    {
        $rows = [];

        foreach (self::elements($sheet, 'row') as $row) {
            $number = (int) $row->getAttribute('r');
            $cells = [];

            foreach (self::elements($row, 'c') as $cell) {
                if (! preg_match('/^([A-Z]+)/', $cell->getAttribute('r'), $matches)) {
                    continue;
                }

                $value = self::cellValue($cell, $strings);

                if ($value !== null && $value !== '') {
                    $cells[$matches[1]] = $value;
                }
            }

            if ($cells !== []) {
                $rows[$number] = $cells;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $strings
     */
    private static function cellValue(DOMElement $cell, array $strings): string|float|null
    {
        $type = $cell->getAttribute('t');

        if ($type === 'inlineStr') {
            return implode('', array_map(fn (DOMElement $t) => $t->textContent, self::elements($cell, 't')));
        }

        $value = self::elements($cell, 'v')[0]->textContent ?? null;

        if ($value === null) {
            return null;
        }

        return match ($type) {
            's' => $strings[(int) $value] ?? '',
            'str', 'e' => $value,
            'b' => $value === '1' ? 'TRUE' : 'FALSE',
            default => is_numeric($value) ? (float) $value : $value,
        };
    }

    /**
     * @return array<int, string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $strings = [];

        foreach (self::elements(self::part($zip, 'xl/sharedStrings.xml'), 'si') as $item) {
            $strings[] = implode('', array_map(fn (DOMElement $t) => $t->textContent, self::elements($item, 't')));
        }

        return $strings;
    }

    /**
     * Relationship id => part path, so sheet entries can be found.
     *
     * @return array<string, string>
     */
    private static function relationshipTargets(ZipArchive $zip): array
    {
        $targets = [];
        $rels = self::part($zip, 'xl/_rels/workbook.xml.rels');

        foreach ($rels->getElementsByTagName('Relationship') as $rel) {
            $target = ltrim($rel->getAttribute('Target'), '/');
            $targets[$rel->getAttribute('Id')] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        return $targets;
    }

    private static function part(ZipArchive $zip, string $name): DOMDocument
    {
        $xml = $zip->getFromName($name);

        if ($xml === false) {
            throw new RuntimeException("The spreadsheet has no {$name} part.");
        }

        $document = new DOMDocument;
        $document->loadXML($xml, LIBXML_NONET);

        return $document;
    }

    /**
     * @return array<int, DOMElement>
     */
    private static function elements(DOMDocument|DOMElement $node, string $tag): array
    {
        return iterator_to_array($node->getElementsByTagNameNS(self::MAIN, $tag), false);
    }
}
