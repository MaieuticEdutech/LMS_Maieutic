<?php

declare(strict_types=1);

namespace App\Support\Spreadsheet;

use App\Exceptions\SpreadsheetException;
use SimpleXMLElement;
use Throwable;
use ZipArchive;

/**
 * Read an .xlsx file into rows of plain strings.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * WHY THIS EXISTS RATHER THAN PhpSpreadsheet.
 *
 * `composer.json` belongs to Track C, and a new dependency there needs a
 * recorded Rule 6 justification — not a package slipped in with a feature.
 *
 * An .xlsx is a ZIP of XML, and PHP ships both `ZipArchive` and `SimpleXML`
 * (the environment already requires the `zip` extension). Reading text cells
 * out of one worksheet is about eighty lines, so the dependency conversation
 * is not worth having for this.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO: dates, formulas as anything but their
 * cached result, styles, merged cells, or the second worksheet. If the import
 * ever needs those, that IS the moment to ask Track C for PhpSpreadsheet —
 * this class should grow no further.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class XlsxReader
{
    /**
     * Cells are addressed BY COLUMN LETTER, not by position.
     *
     * A worksheet stores cells sparsely: a row whose B and D are filled but C
     * is empty contains two <c> elements, not three. Reading them positionally
     * silently shifts every later value one column left — which, in a question
     * import, would move an option into the answer field without any error.
     *
     * @return list<array<string, string>> one entry per non-empty row, each keyed 'A', 'B', …
     *
     * @throws SpreadsheetException
     */
    public function rows(string $path): array
    {
        if (! is_file($path)) {
            throw new SpreadsheetException('That file could not be read. Please upload it again.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new SpreadsheetException(
                'That does not look like a valid .xlsx file. If you exported it from another program, try saving it again as "Excel Workbook (.xlsx)".',
            );
        }

        try {
            $shared = $this->sharedStrings($zip);
            $sheet = $this->firstSheet($zip);

            return $this->extractRows($sheet, $shared);
        } finally {
            $zip->close();
        }
    }

    /**
     * The workbook's shared-string table.
     *
     * Most text in a spreadsheet is stored once here and referenced by index,
     * so a cell of type "s" holds a number that means "look it up".
     *
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            // A workbook whose cells are all inline or numeric has no table.
            return [];
        }

        $strings = [];

        foreach ($this->parse($xml)->si as $item) {
            /*
             * A single string is split across <r> runs when parts of it are
             * formatted differently — bold in the middle of a sentence, say.
             * Reading only <t> would return the first fragment and silently
             * truncate the question.
             */
            $text = isset($item->t) ? (string) $item->t : '';

            foreach ($item->r as $run) {
                $text .= (string) $run->t;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private function firstSheet(ZipArchive $zip): SimpleXMLElement
    {
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($xml === false) {
            throw new SpreadsheetException('That workbook has no readable sheet. The questions must be on the first sheet.');
        }

        return $this->parse($xml);
    }

    /**
     * @param  list<string>  $shared
     * @return list<array<string, string>>
     */
    private function extractRows(SimpleXMLElement $sheet, array $shared): array
    {
        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $column = preg_replace('/\d+/', '', (string) $cell['r']);

                if (! is_string($column) || $column === '') {
                    continue;
                }

                $value = $this->cellValue($cell, $shared);

                if ($value !== '') {
                    $cells[$column] = $value;
                }
            }

            // A wholly empty row is skipped rather than yielding a blank entry
            // every consumer would have to filter out.
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    /**
     * @param  list<string>  $shared
     */
    private function cellValue(SimpleXMLElement $cell, array $shared): string
    {
        $value = match ((string) $cell['t']) {
            's' => $shared[(int) $cell->v] ?? '',
            'inlineStr' => isset($cell->is->t) ? (string) $cell->is->t : '',
            // 'str' is a formula's cached result; anything else is numeric or
            // boolean and its literal text is what we want.
            default => isset($cell->v) ? (string) $cell->v : '',
        };

        /*
         * Collapse whitespace. Cells pasted out of Word routinely carry
         * non-breaking spaces and stray newlines, and an option that differs
         * from its neighbour only by an invisible character is a support
         * ticket nobody can diagnose.
         */
        $value = str_replace("\u{00A0}", ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function parse(string $xml): SimpleXMLElement
    {
        try {
            /*
             * LIBXML_NONET and no entity substitution: the file is untrusted
             * input, and an XML parser that resolves external entities is how
             * a spreadsheet reads /etc/passwd. PHP 8 disables entity loading
             * by default; the flag makes the intent explicit rather than
             * relying on a default staying put.
             */
            $parsed = new SimpleXMLElement($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (Throwable) {
            throw new SpreadsheetException('That workbook could not be read. It may be corrupt — try opening and re-saving it in Excel.');
        }

        return $parsed;
    }
}
