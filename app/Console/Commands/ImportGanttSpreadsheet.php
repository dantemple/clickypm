<?php

namespace App\Console\Commands;

use App\Actions\Projects\CreateProject;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Support\Spreadsheet;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Brings a site-by-site works programme out of a spreadsheet: one project
 * per site heading, one task per row under it, with start, end and
 * progress. Rows whose dates can't be read are imported without them and
 * listed at the end.
 */
class ImportGanttSpreadsheet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gantt:import
        {team : The workspace slug}
        {file : Path to the .xlsx file}
        {--dry-run : List what would be imported without saving anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import a site-by-site Gantt spreadsheet as projects and tasks';

    /** Spreadsheet serial dates count from here. */
    private const EXCEL_EPOCH = '1899-12-30';

    /**
     * @var array<int, string>
     */
    private array $problems = [];

    /**
     * Execute the console command.
     */
    public function handle(CreateProject $createProject): int
    {
        $slug = $this->argument('team');
        $file = $this->argument('file');

        if (! is_string($slug) || ! is_string($file)) {
            $this->error('Give the workspace slug and the path to the spreadsheet.');

            return self::FAILURE;
        }

        $team = Team::where('slug', $slug)->first();

        if (! $team) {
            $this->error("No workspace with the slug '{$slug}'.");

            return self::FAILURE;
        }

        if (! is_file($file)) {
            $this->error("No file at {$file}.");

            return self::FAILURE;
        }

        $owner = $team->owner();

        if (! $owner instanceof User) {
            $this->error('The workspace has no owner to create the projects as.');

            return self::FAILURE;
        }

        try {
            $sheets = Spreadsheet::read($file);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $rows = $this->collect($sheets);

        if ($rows === []) {
            $this->warn('Nothing to import: no task rows under a site heading were found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Site', 'Task', 'Start', 'Due', 'Progress'],
            array_map(fn (array $row) => [
                $row['site'],
                Str::limit($row['title'], 50),
                $row['start']?->toDateString() ?? '',
                $row['due']?->toDateString() ?? '',
                $row['progress'].'%'.($row['done'] ? ' (done)' : ''),
            ], $rows),
        );

        if ($this->option('dry-run')) {
            $this->info(count($rows).' rows would be imported.');
            $this->reportProblems();

            return self::SUCCESS;
        }

        [$projects, $created, $skipped] = DB::transaction(function () use ($rows, $team, $owner, $createProject) {
            $projects = [];
            $created = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                $project = $projects[$row['site']] ??= $this->project($team, $row['site'], $owner, $createProject);

                if ($project->tasks()->whereRaw('LOWER(title) = ?', [mb_strtolower($row['title'])])->exists()) {
                    $skipped++;

                    continue;
                }

                $status = $row['done']
                    ? ($project->statuses()->where('is_done', true)->first() ?? $project->defaultStatus())
                    : $project->defaultStatus();

                $project->tasks()->create([
                    'status_id' => $status->id,
                    'title' => $row['title'],
                    'start_date' => $row['start'],
                    'due_date' => $row['due'],
                    'progress' => $row['progress'],
                    'position' => (int) $project->tasks()->withTrashed()->max('position') + 1,
                    'created_by' => $owner->id,
                ]);
                $created++;
            }

            return [$projects, $created, $skipped];
        });

        $this->info(sprintf(
            'Imported %d tasks into %d projects%s.',
            $created,
            count($projects),
            $skipped ? ", skipped {$skipped} already there" : '',
        ));
        $this->reportProblems();

        return self::SUCCESS;
    }

    /**
     * Task rows from every sheet, one per site and title, first sheet wins.
     *
     * @param  array<int, array{name: string, rows: array<int, array<string, string|float>>}>  $sheets
     * @return array<int, array{site: string, title: string, start: CarbonImmutable|null, due: CarbonImmutable|null, progress: int, done: bool}>
     */
    private function collect(array $sheets): array
    {
        $tasks = [];

        foreach ($sheets as $sheet) {
            $site = null;
            ksort($sheet['rows']);

            foreach ($sheet['rows'] as $number => $cells) {
                $a = trim((string) ($cells['A'] ?? ''));
                $b = trim((string) ($cells['B'] ?? ''));
                $where = "{$sheet['name']} row {$number}";

                // A row with only a name in the first column starts a new site.
                if ($b === '') {
                    if ($a !== '' && ! preg_match('/^task\s*\d+$/i', $a) && ! in_array(mb_strtolower($a), ['task', 'location', 'project lead'], true)) {
                        $site = $a;
                    }

                    continue;
                }

                if ($site === null || in_array(mb_strtolower($b), ['assigned to', 'project'], true)) {
                    continue;
                }

                $key = mb_strtolower($site.'|'.$b);

                if (isset($tasks[$key])) {
                    continue;
                }

                $start = $this->date($cells['D'] ?? null, "{$where} start");
                $due = $this->date($cells['E'] ?? null, "{$where} end");

                if ($start && $due && $start->gt($due)) {
                    $this->problems[] = "{$where}: start {$start->toDateString()} is after end {$due->toDateString()}; start left blank.";
                    $start = null;
                }

                $tasks[$key] = [
                    'site' => $site,
                    'title' => $b,
                    'start' => $start,
                    'due' => $due,
                    'progress' => $this->progress($cells['C'] ?? null),
                    'done' => $this->progress($cells['C'] ?? null) >= 100 || $this->isCompleted($cells['D'] ?? null) || $this->isCompleted($cells['E'] ?? null),
                ];
            }
        }

        return array_values($tasks);
    }

    /**
     * A spreadsheet date: a serial number, or text in a British format.
     * "TBC" and "Completed" are blanks, not problems.
     */
    private function date(string|float|null $value, string $where): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value)) {
            return $value > 0 ? CarbonImmutable::parse(self::EXCEL_EPOCH)->addDays((int) $value) : null;
        }

        $text = trim($value);

        if ($text === '' || $this->isCompleted($text) || mb_strtolower($text) === 'tbc') {
            return null;
        }

        foreach (['d/m/Y', 'd/m/y', 'Y-m-d', 'd-m-Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $text);
            } catch (Throwable) {
                continue;
            }

            // Round-tripping catches dates like 31/09 that PHP would roll over.
            if ($date->format($format) === $text) {
                return $date;
            }
        }

        $this->problems[] = "{$where}: couldn’t read the date '{$text}'; left blank.";

        return null;
    }

    private function isCompleted(string|float|null $value): bool
    {
        return is_string($value) && mb_strtolower(trim($value)) === 'completed';
    }

    /**
     * Progress as a whole percentage; the sheet stores fractions.
     */
    private function progress(string|float|null $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        $number = (float) $value;
        $percent = $number <= 1 ? $number * 100 : $number;

        return (int) max(0, min(100, round($percent)));
    }

    private function project(Team $team, string $name, User $owner, CreateProject $createProject): Project
    {
        return $team->projects()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? $createProject->handle($owner, $team, $name);
    }

    private function reportProblems(): void
    {
        if ($this->problems === []) {
            return;
        }

        $this->newLine();
        $this->warn(count($this->problems).' rows had values that could not be read:');

        foreach ($this->problems as $problem) {
            $this->line('  - '.$problem);
        }
    }
}
