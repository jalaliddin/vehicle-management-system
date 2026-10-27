<?php

namespace App\Services\Hr;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Reads employees from the import template (.xlsx or .csv), checks every row,
 * and creates them with their hire orders.
 */
class EmployeeImporter
{
    /**
     * Template columns, in order.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'tashkilot_kodi', 'bolim', 'lavozim', 'familiya', 'ism', 'otasining_ismi', 'jshshir', 'pasport', 'tugilgan_sana',
        'jinsi', 'telefon', 'tabel_raqami', 'bandlik_turi', 'ishga_qabul_sanasi', 'buyruq_raqami', 'buyruq_sanasi',
    ];

    private const REQUIRED = [
        'tashkilot_kodi', 'lavozim', 'familiya', 'ism', 'jshshir', 'ishga_qabul_sanasi', 'buyruq_raqami', 'buyruq_sanasi',
    ];

    private const GENDERS = ['erkak' => 'male', 'ayol' => 'female'];

    private const EMPLOYMENT_TYPES = ['shtat' => 'staff', 'shartnoma' => 'contract', 'yarim_stavka' => 'part_time'];

    public function __construct(private EmploymentService $employment) {}

    /**
     * @return array{rows: list<array{card: array<string, mixed>, hire: array<string, mixed>}>, errors: list<array{row: int, field: string, message: string}>}
     */
    public function validateFile(UploadedFile $file, User $user): array
    {
        try {
            $lines = strtolower($file->getClientOriginalExtension()) === 'xlsx'
                ? $this->readXlsx($file->getRealPath())
                : $this->readCsv((string) file_get_contents($file->getRealPath()));
        } catch (Throwable) {
            return ['rows' => [], 'errors' => [['row' => 0, 'field' => 'file', 'message' => "Faylni o'qib bo'lmadi"]]];
        }

        $header = array_map(fn ($cell) => mb_strtolower(trim((string) $cell)), $lines[0] ?? []);
        $missing = array_diff(self::REQUIRED, $header);

        if ($missing !== []) {
            return ['rows' => [], 'errors' => [[
                'row' => 1, 'field' => implode(', ', $missing), 'message' => 'Majburiy ustunlar topilmadi',
            ]]];
        }

        $lookups = [
            'organizations' => Organization::all()->keyBy(fn ($o) => mb_strtolower($o->code)),
            'positions' => Position::all()->keyBy(fn ($p) => mb_strtolower($p->name)),
            'departments' => Department::all()->groupBy('organization_id')
                ->map(fn ($group) => $group->keyBy(fn ($d) => mb_strtolower($d->name))),
            'existingPinfls' => Employee::withTrashed()->pluck('pinfl')->flip(),
        ];

        $rows = [];
        $errors = [];
        $seenPinfls = [];

        foreach (array_slice($lines, 1, null, true) as $index => $cells) {
            if (array_filter($cells, fn ($cell) => trim((string) $cell) !== '') === []) {
                continue;
            }

            $values = [];
            foreach ($header as $position => $column) {
                $values[$column] = $cells[$position] ?? null;
            }

            [$row, $rowErrors] = $this->parseRow($values, $index + 1, $user, $lookups, $seenPinfls);

            if ($rowErrors === []) {
                $rows[] = $row;
            } else {
                array_push($errors, ...$rowErrors);
            }
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param  list<array{card: array<string, mixed>, hire: array<string, mixed>}>  $rows
     */
    public function import(array $rows, User $by): int
    {
        return DB::transaction(function () use ($rows, $by) {
            foreach ($rows as $row) {
                $this->employment->createWithHire($row['card'], $row['hire'], $by);
            }

            return count($rows);
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $lookups
     * @param  array<string, true>  $seenPinfls
     * @return array{0: array{card: array<string, mixed>, hire: array<string, mixed>}, 1: list<array{row: int, field: string, message: string}>}
     */
    private function parseRow(array $values, int $rowNumber, User $user, array $lookups, array &$seenPinfls): array
    {
        $errors = [];
        $fail = function (string $field, string $message) use (&$errors, $rowNumber) {
            $errors[] = ['row' => $rowNumber, 'field' => $field, 'message' => $message];
        };
        $text = fn (string $column) => ($trimmed = trim((string) ($values[$column] ?? ''))) === '' ? null : $trimmed;

        foreach (self::REQUIRED as $column) {
            if ($text($column) === null && ! ($values[$column] ?? null) instanceof DateTimeInterface) {
                $fail($column, "To'ldirilishi shart");
            }
        }

        $organization = $lookups['organizations'][mb_strtolower((string) $text('tashkilot_kodi'))] ?? null;
        if ($text('tashkilot_kodi') !== null && $organization === null) {
            $fail('tashkilot_kodi', 'Tashkilot topilmadi');
        } elseif ($organization !== null && ! $user->canAccessOrganization($organization->id)) {
            $fail('tashkilot_kodi', "Bu tashkilotga ruxsat yo'q");
        }

        $department = null;
        if ($text('bolim') !== null && $organization !== null) {
            $department = $lookups['departments'][$organization->id][mb_strtolower($text('bolim'))] ?? null;
            if ($department === null) {
                $fail('bolim', "Bo'lim topilmadi");
            }
        }

        $position = $lookups['positions'][mb_strtolower((string) $text('lavozim'))] ?? null;
        if ($text('lavozim') !== null && $position === null) {
            $fail('lavozim', 'Lavozim topilmadi');
        }

        $pinfl = $text('jshshir');
        if ($pinfl !== null) {
            if (! preg_match('/^\d{14}$/', $pinfl)) {
                $fail('jshshir', "JShShIR 14 ta raqamdan iborat bo'lishi kerak");
            } elseif (isset($seenPinfls[$pinfl])) {
                $fail('jshshir', 'Bu JShShIR faylda takrorlangan');
            } elseif (isset($lookups['existingPinfls'][$pinfl])) {
                $fail('jshshir', 'Bu JShShIR bilan xodim allaqachon mavjud');
            }
            $seenPinfls[$pinfl] = true;
        }

        $date = function (string $column, bool $notFuture) use ($values, $fail): ?string {
            $value = $values[$column] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                return null;
            }

            $parsed = $this->parseDate($value);
            if ($parsed === null) {
                $fail($column, "Sana noto'g'ri (YYYY-MM-DD yoki DD.MM.YYYY)");
            } elseif ($notFuture && $parsed->isFuture()) {
                $fail($column, "Sana kelajakda bo'lishi mumkin emas");
            }

            return $parsed?->toDateString();
        };

        $gender = $text('jinsi');
        if ($gender !== null && ! isset(self::GENDERS[mb_strtolower($gender)])) {
            $fail('jinsi', "erkak yoki ayol bo'lishi kerak");
        }

        $employmentType = $text('bandlik_turi');
        if ($employmentType !== null && ! isset(self::EMPLOYMENT_TYPES[mb_strtolower($employmentType)])) {
            $fail('bandlik_turi', "shtat, shartnoma yoki yarim_stavka bo'lishi kerak");
        }

        $card = [
            'organization_id' => $organization?->id,
            'last_name' => $text('familiya'),
            'first_name' => $text('ism'),
            'middle_name' => $text('otasining_ismi'),
            'pinfl' => $pinfl,
            'passport' => $text('pasport'),
            'birth_date' => $date('tugilgan_sana', true),
            'gender' => $gender !== null ? self::GENDERS[mb_strtolower($gender)] ?? null : null,
            'phone' => $text('telefon'),
            'personnel_number' => $text('tabel_raqami'),
            'employment_type' => $employmentType !== null ? self::EMPLOYMENT_TYPES[mb_strtolower($employmentType)] ?? 'staff' : 'staff',
        ];

        $hire = [
            'effective_date' => $date('ishga_qabul_sanasi', true),
            'order_number' => $text('buyruq_raqami'),
            'order_date' => $date('buyruq_sanasi', true),
            'department_id' => $department?->id,
            'position_id' => $position?->id,
        ];

        return [['card' => $card, 'hire' => $hire], $errors];
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        $value = trim((string) $value);

        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $value);
        }

        foreach (['Y-m-d', 'd.m.Y'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (InvalidFormatException) {
                continue;
            }

            if ($parsed !== null && $parsed->format($format) === $value) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @return list<list<mixed>>
     */
    private function readXlsx(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $lines = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $lines[] = $row->toArray();
            }
            break;
        }

        $reader->close();

        return $lines;
    }

    /**
     * Parse CSV text saved by Excel in any locale: optional BOM, "," or ";".
     *
     * @return list<list<string>>
     */
    private function readCsv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\n|\r/', $content);
        $delimiter = substr_count($lines[0] ?? '', ';') > substr_count($lines[0] ?? '', ',') ? ';' : ',';

        return array_map(fn (string $line) => str_getcsv($line, $delimiter, '"', ''), $lines);
    }
}
