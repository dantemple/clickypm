<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class GanttImportTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), 'gantt').'.xlsx';
        $this->writeWorkbook($this->file, [
            '2025 GANTT' => [
                1 => ['A' => 's:Project Lead', 'B' => 's:CAPEX Start:', 'D' => 45748],
                4 => ['A' => 's:TASK', 'B' => 's:ASSIGNED TO', 'C' => 's:PROGRESS', 'D' => 's:START', 'E' => 's:END'],
                6 => ['A' => 's:Hutwood'],
                7 => ['A' => 's:Task 1', 'B' => 's:AHU 1 & 2', 'C' => 0, 'D' => 46043, 'E' => 46078],
                8 => ['A' => 's:Task 2', 'B' => 's:Fire doors', 'C' => 1, 'D' => 's:Completed', 'E' => 's:Completed'],
                9 => ['A' => 's:Task 3', 'B' => 's:BMS controls', 'C' => 0.5, 'D' => 45784, 'E' => 's:31/09/2026'],
                10 => ['A' => 's:Task 4', 'B' => 's:EV charging', 'C' => 0, 'D' => 's:TBC', 'E' => 's:TBC'],
                11 => ['A' => 's:Task 5'],
                12 => ['A' => 's:Chesterfield'],
                13 => ['A' => 's:Task 1', 'B' => 's:Carpets', 'C' => 0.9, 'D' => 45992, 'E' => 45996],
            ],
            '2026 GANTT' => [
                4 => ['A' => 's:Location', 'B' => 's:PROJECT'],
                5 => ['A' => 's:Hutwood'],
                6 => ['A' => 's:Task 1', 'B' => 's:AHU 1 & 2', 'C' => 0.25, 'D' => 46043, 'E' => 46078],
                7 => ['A' => 's:Task 2', 'B' => 's:Rainwater harvesting', 'C' => 0, 'D' => 46054, 'E' => 46109],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    public function test_it_imports_a_site_by_site_programme()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->artisan('gantt:import', ['team' => $team->slug, 'file' => $this->file])
            ->expectsOutputToContain('Imported 6 tasks into 2 projects.')
            ->expectsOutputToContain("couldn’t read the date '31/09/2026'")
            ->assertSuccessful();

        $hutwood = $team->projects()->where('name', 'Hutwood')->firstOrFail();
        $this->assertSame(5, $hutwood->tasks()->count());
        $this->assertSame(1, $team->projects()->where('name', 'Chesterfield')->firstOrFail()->tasks()->count());

        $ahu = $hutwood->tasks()->where('title', 'AHU 1 & 2')->firstOrFail();
        $epoch = CarbonImmutable::parse('1899-12-30');
        $this->assertTrue($ahu->start_date->eq($epoch->addDays(46043)));
        $this->assertTrue($ahu->due_date->eq($epoch->addDays(46078)));
        // The first sheet's row wins over the later one.
        $this->assertSame(0, $ahu->progress);

        $doors = $hutwood->tasks()->where('title', 'Fire doors')->firstOrFail();
        $this->assertSame(100, $doors->progress);
        $this->assertTrue($doors->status->is_done);
        $this->assertNotNull($doors->completed_at);

        $bms = $hutwood->tasks()->where('title', 'BMS controls')->firstOrFail();
        $this->assertSame(50, $bms->progress);
        $this->assertNotNull($bms->start_date);
        $this->assertNull($bms->due_date);

        $this->assertNull($hutwood->tasks()->where('title', 'EV charging')->firstOrFail()->due_date);
        $this->assertSame(90, $team->projects()->where('name', 'Chesterfield')->firstOrFail()->tasks()->firstOrFail()->progress);

        // Running it again adds nothing.
        $this->artisan('gantt:import', ['team' => $team->slug, 'file' => $this->file])
            ->expectsOutputToContain('Imported 0 tasks into 2 projects, skipped 6 already there.')
            ->assertSuccessful();
    }

    public function test_a_dry_run_changes_nothing()
    {
        $user = User::factory()->create();

        $this->artisan('gantt:import', ['team' => $user->currentTeam->slug, 'file' => $this->file, '--dry-run' => true])
            ->expectsOutputToContain('6 rows would be imported.')
            ->assertSuccessful();

        $this->assertSame(0, $user->currentTeam->projects()->count());
    }

    public function test_it_refuses_unknown_workspaces_and_files()
    {
        $user = User::factory()->create();

        $this->artisan('gantt:import', ['team' => 'nope', 'file' => $this->file])->assertFailed();
        $this->artisan('gantt:import', ['team' => $user->currentTeam->slug, 'file' => '/nowhere.xlsx'])->assertFailed();
    }

    /**
     * Write a minimal .xlsx: values prefixed "s:" become shared strings.
     *
     * @param  array<string, array<int, array<string, string|int|float>>>  $sheets
     */
    private function writeWorkbook(string $path, array $sheets): void
    {
        $strings = [];
        $stringIndex = function (string $value) use (&$strings): int {
            $index = array_search($value, $strings, true);

            if ($index === false) {
                $strings[] = $value;
                $index = count($strings) - 1;
            }

            return $index;
        };

        $sheetXml = [];
        $names = [];

        foreach (array_values($sheets) as $i => $rows) {
            $names[] = array_keys($sheets)[$i];
            $xml = '';

            foreach ($rows as $number => $cells) {
                $xml .= "<row r=\"{$number}\">";

                foreach ($cells as $column => $value) {
                    $ref = $column.$number;
                    $xml .= is_string($value) && str_starts_with($value, 's:')
                        ? "<c r=\"{$ref}\" t=\"s\"><v>{$stringIndex(substr($value, 2))}</v></c>"
                        : "<c r=\"{$ref}\"><v>{$value}</v></c>";
                }

                $xml .= '</row>';
            }

            $sheetXml[] = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xml.'</sheetData></worksheet>';
        }

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');

        $sheetEntries = '';
        $relEntries = '';

        foreach ($names as $i => $name) {
            $n = $i + 1;
            $sheetEntries .= "<sheet name=\"{$name}\" sheetId=\"{$n}\" r:id=\"rId{$n}\"/>";
            $relEntries .= "<Relationship Id=\"rId{$n}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$n}.xml\"/>";
            $zip->addFromString("xl/worksheets/sheet{$n}.xml", $sheetXml[$i]);
        }

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheetEntries.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relEntries.'</Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.implode('', array_map(fn ($s) => '<si><t>'.htmlspecialchars($s, ENT_XML1).'</t></si>', $strings)).'</sst>');
        $zip->close();
    }
}
