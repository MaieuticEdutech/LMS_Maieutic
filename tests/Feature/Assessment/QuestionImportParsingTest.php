<?php

declare(strict_types=1);

use App\Exceptions\SpreadsheetException;
use App\Services\Assessment\QuestionImportParser;
use App\Support\Spreadsheet\XlsxReader;

/*
|--------------------------------------------------------------------------
| Reading a question spreadsheet
|--------------------------------------------------------------------------
|
| The expensive failure in this feature is silent: a mis-read answer column
| produces questions that look correct and mark students wrong, and nothing
| downstream can detect it. So the tests that matter are the ones about the
| ANSWER KEY — that a letter maps to the option the author meant, and that
| anything ambiguous is refused rather than guessed.
|
| Built with a real .xlsx each time rather than a fixture file, so the tests
| exercise the actual zip-and-XML reader and stay readable in one place.
|
*/

/**
 * Zero-based column index to its spreadsheet letters: 0 → A, 25 → Z, 26 → AA.
 *
 * Written out rather than `chr(65 + $i)` because that overflows past column
 * 190 and silently emits punctuation instead of letters — a cell reference no
 * reader would understand, from a helper that looked fine.
 */
function columnLetters(int $index): string
{
    $letters = '';

    do {
        $letters = chr(65 + $index % 26).$letters;
        $index = intdiv($index, 26) - 1;
    } while ($index >= 0);

    return $letters;
}

/**
 * Write a minimal but genuine .xlsx.
 *
 * Cells are emitted as inline strings, which keeps the writer short while
 * still producing a file Excel opens. The reader's shared-string path is
 * covered separately by the test that reads the customer's own file.
 *
 * @param  list<list<string>>  $rows
 */
function makeXlsx(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'qimport').'.xlsx';

    $sheet = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

    foreach ($rows as $r => $row) {
        $sheet .= '<row r="'.($r + 1).'">';

        foreach ($row as $c => $value) {
            // Sparse on purpose: a blank cell is OMITTED, exactly as Excel
            // writes it. That is what makes column-letter addressing load-bearing.
            if ($value === '') {
                continue;
            }

            $ref = columnLetters($c).($r + 1);
            $sheet .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
        }

        $sheet .= '</row>';
    }

    $sheet .= '</sheetData></worksheet>';

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="Sheet1" sheetId="1"/></sheets></workbook>');
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    return $path;
}

beforeEach(function (): void {
    $this->parser = new QuestionImportParser(new XlsxReader);

    $this->header = ['Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Answer', 'Explanation'];
});

/*
| ═══════════════ THE FORMAT THE CUSTOMER SUPPLIED ═══════════════
*/
it('reads the supplied format', function (): void {
    $file = makeXlsx([
        $this->header,
        ['Which keyword creates a class in Java?', 'function', 'class', 'define', 'struct', 'B', 'class declares a type.'],
    ]);

    $result = $this->parser->parse($file);

    expect($result['problems'])->toBe([])
        ->and($result['questions'])->toHaveCount(1);

    $question = $result['questions'][0];

    expect($question['body'])->toBe('Which keyword creates a class in Java?')
        ->and($question['explanation'])->toBe('class declares a type.')
        ->and($question['options'])->toHaveCount(4)
        ->and($question['row'])->toBe(2);
});

it('marks the option the answer letter names, and only that one', function (): void {
    // The single assertion this whole feature rests on.
    $file = makeXlsx([
        $this->header,
        ['Entry point of a Java program?', 'start()', 'run()', 'main()', 'execute()', 'C', ''],
    ]);

    $options = $this->parser->parse($file)['questions'][0]['options'];

    expect($options[2]['body'])->toBe('main()')
        ->and($options[2]['is_correct'])->toBeTrue();

    foreach ([0, 1, 3] as $i) {
        expect($options[$i]['is_correct'])->toBeFalse();
    }
});

it('reads the customer\'s own spreadsheet', function (): void {
    /*
     * The real file, not a constructed one — it uses the shared-string table
     * that Excel actually writes, which the hand-built fixtures above avoid.
     * Skipped rather than failed when absent so the suite still runs on a
     * checkout without the sample folder.
     */
    $path = base_path('sample test/test.xlsx');

    if (! is_file($path)) {
        $this->markTestSkipped('sample test/test.xlsx is not present.');
    }

    $result = $this->parser->parse($path);

    // Five rows in the file: one header and four questions.
    expect($result['problems'])->toBe([])
        ->and($result['questions'])->toHaveCount(4);

    $first = $result['questions'][0];

    expect($first['body'])->toContain('class in Java')
        ->and($first['options'])->toHaveCount(4);

    // Answer B → "class".
    $correct = array_values(array_filter($first['options'], static fn (array $o): bool => $o['is_correct']));

    expect($correct)->toHaveCount(1)
        ->and($correct[0]['body'])->toBe('class');
});

/*
| ═══════════════ COLUMNS ARE MATCHED BY NAME ═══════════════
*/
it('finds the columns wherever they sit', function (): void {
    /*
     * Someone will insert a column. Reading positionally would slide an option
     * into the answer field — an import that succeeds and is wrong.
     */
    $file = makeXlsx([
        ['Topic', 'Question', 'Answer', 'Option A', 'Option B', 'Explanation'],
        ['Java', 'Which is a loop?', 'B', 'class', 'while', 'while repeats.'],
    ]);

    $question = $this->parser->parse($file)['questions'][0];

    expect($question['body'])->toBe('Which is a loop?')
        ->and($question['options'][1]['body'])->toBe('while')
        ->and($question['options'][1]['is_correct'])->toBeTrue();
});

it('ignores header casing and stray spacing', function (): void {
    $file = makeXlsx([
        ['  QUESTION ', 'option a', 'Option  B', ' ANSWER'],
        ['Pick one', 'no', 'yes', 'b'],
    ]);

    $question = $this->parser->parse($file)['questions'][0];

    expect($question['options'][1]['is_correct'])->toBeTrue();
});

it('refuses a file with no Answer column', function (): void {
    $file = makeXlsx([
        ['Question', 'Option A', 'Option B'],
        ['Pick one', 'no', 'yes'],
    ]);

    expect(fn () => $this->parser->parse($file))
        ->toThrow(SpreadsheetException::class, 'No "Answer" column');
});

/*
| ═══════════════ AS MANY OPTIONS AS THE FILE HAS ═══════════════
*/
it('accepts more than four options', function (): void {
    $file = makeXlsx([
        ['Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Option E', 'Option F', 'Answer'],
        ['Pick the fifth', 'a', 'b', 'c', 'd', 'e', 'f', 'E'],
    ]);

    $question = $this->parser->parse($file)['questions'][0];

    expect($question['options'])->toHaveCount(6)
        ->and($question['options'][4]['body'])->toBe('e')
        ->and($question['options'][4]['is_correct'])->toBeTrue();
});

it('treats a blank option cell as that question having fewer options', function (): void {
    // Sparse cells: Excel omits the empty D entirely, so this also proves the
    // reader addresses cells by column letter rather than by position.
    $file = makeXlsx([
        $this->header,
        ['Three-option question', 'a', 'b', 'c', '', 'C', ''],
    ]);

    $question = $this->parser->parse($file)['questions'][0];

    expect($question['options'])->toHaveCount(3)
        ->and($question['options'][2]['body'])->toBe('c')
        ->and($question['options'][2]['is_correct'])->toBeTrue();
});

/*
| ═══════════════ MULTIPLE CORRECT ANSWERS ═══════════════
*/
it('accepts several answer letters', function (string $cell): void {
    $file = makeXlsx([
        $this->header,
        ['Which are loops?', 'if', 'while', 'switch', 'for', $cell, ''],
    ]);

    $options = $this->parser->parse($file)['questions'][0]['options'];

    expect($options[1]['is_correct'])->toBeTrue()
        ->and($options[3]['is_correct'])->toBeTrue()
        ->and($options[0]['is_correct'])->toBeFalse()
        ->and($options[2]['is_correct'])->toBeFalse();
})->with([
    'comma' => 'B,D',
    'comma and space' => 'B, D',
    'space' => 'B D',
    'slash' => 'B/D',
    'lower case' => 'b,d',
]);

it('counts a repeated letter once', function (): void {
    $file = makeXlsx([
        $this->header,
        ['Which is a loop?', 'if', 'while', 'switch', 'for', 'B,B', ''],
    ]);

    $correct = array_filter(
        $this->parser->parse($file)['questions'][0]['options'],
        static fn (array $o): bool => $o['is_correct'],
    );

    expect($correct)->toHaveCount(1);
});

it('refuses run-together letters rather than guessing', function (): void {
    /*
     * "BD" could be two answers or a typo for "B". Guessing would silently
     * mark a second option correct — which is exactly the failure this whole
     * feature has to avoid. Rejecting sends it to the review screen instead.
     */
    $file = makeXlsx([
        $this->header,
        ['Which are loops?', 'if', 'while', 'switch', 'for', 'BD', ''],
    ]);

    $result = $this->parser->parse($file);

    expect($result['questions'])->toBe([])
        ->and($result['problems'][0]['row'])->toBe(2);
});

/*
| ═══════════════ BAD ROWS ARE REPORTED, NOT DROPPED ═══════════════
*/
it('reports an answer letter with no matching option', function (): void {
    $file = makeXlsx([
        $this->header,
        ['Pick one', 'a', 'b', '', '', 'D', ''],
    ]);

    $result = $this->parser->parse($file);

    expect($result['questions'])->toBe([])
        ->and($result['problems'][0]['message'])->toContain('no option D');
});

it('reports a row with fewer than two options', function (): void {
    $file = makeXlsx([
        $this->header,
        ['Lonely question', 'only one', '', '', '', 'A', ''],
    ]);

    expect($this->parser->parse($file)['problems'][0]['message'])->toContain('Fewer than two options');
});

it('reports an empty question body', function (): void {
    $file = makeXlsx([
        $this->header,
        ['', 'a', 'b', 'c', 'd', 'A', ''],
    ]);

    expect($this->parser->parse($file)['problems'][0]['message'])->toContain('question text is empty');
});

it('names the spreadsheet row the author sees in Excel', function (): void {
    // Header is row 1, so the first broken data row is row 3 here — not 2, and
    // not 1. Off-by-one sends someone to the wrong line in a 200-row file.
    $file = makeXlsx([
        $this->header,
        ['Good question', 'a', 'b', 'c', 'd', 'A', ''],
        ['Bad question', 'a', 'b', 'c', 'd', 'Z', ''],
    ]);

    $result = $this->parser->parse($file);

    expect($result['questions'])->toHaveCount(1)
        ->and($result['problems'])->toHaveCount(1)
        ->and($result['problems'][0]['row'])->toBe(3);
});

it('keeps the good rows when some are bad', function (): void {
    $file = makeXlsx([
        $this->header,
        ['Fine', 'a', 'b', 'c', 'd', 'A', ''],
        ['Broken', 'a', 'b', 'c', 'd', '', ''],
        ['Also fine', 'a', 'b', 'c', 'd', 'B', ''],
    ]);

    $result = $this->parser->parse($file);

    expect($result['questions'])->toHaveCount(2)
        ->and($result['problems'])->toHaveCount(1);
});

/*
| ═══════════════ WHOLE-FILE FAILURES ═══════════════
*/
it('refuses something that is not a spreadsheet', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'notxlsx');
    file_put_contents($path, 'this is not a zip archive');

    expect(fn () => $this->parser->parse($path))
        ->toThrow(SpreadsheetException::class, 'does not look like a valid .xlsx');
});

it('refuses a file with a header but no questions', function (): void {
    expect(fn () => $this->parser->parse(makeXlsx([$this->header])))
        ->toThrow(SpreadsheetException::class, 'no questions');
});

it('refuses a file past the row cap', function (): void {
    // A mis-selected export should be a clear refusal, not a request that
    // times out halfway through writing.
    $rows = [$this->header];

    for ($i = 0; $i <= QuestionImportParser::MAX_QUESTIONS; $i++) {
        $rows[] = ['Q'.$i, 'a', 'b', 'c', 'd', 'A', ''];
    }

    expect(fn () => $this->parser->parse(makeXlsx($rows)))
        ->toThrow(SpreadsheetException::class, 'the limit is');
});
