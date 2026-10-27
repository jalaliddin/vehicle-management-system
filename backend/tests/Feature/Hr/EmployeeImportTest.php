<?php

namespace Tests\Feature\Hr;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class EmployeeImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const HEADER = [
        'tashkilot_kodi', 'bolim', 'lavozim', 'familiya', 'ism', 'otasining_ismi', 'jshshir', 'pasport', 'tugilgan_sana',
        'jinsi', 'telefon', 'tabel_raqami', 'bandlik_turi', 'ishga_qabul_sanasi', 'buyruq_raqami', 'buyruq_sanasi',
    ];

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['code' => 'URG-MGQB']);
        Department::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Garaj']);
        Position::factory()->create(['name' => 'Haydovchi']);
        Sanctum::actingAs(User::factory()->withRole('hr')->create(['organization_id' => $this->organization->id]));
    }

    /**
     * @return list<string>
     */
    private function row(string $pinfl, array $overrides = []): array
    {
        return array_values(array_merge(array_combine(self::HEADER, [
            'URG-MGQB', 'Garaj', 'Haydovchi', 'Karimov', 'Anvar', 'Olimovich', $pinfl, 'AA1234567', '1985-04-12',
            'erkak', '+998901112233', '0012', 'shtat', '2020-03-01', '12-K', '2020-02-28',
        ]), $overrides));
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows, string $delimiter = ',', bool $bom = false): UploadedFile
    {
        $lines = array_map(fn (array $row) => implode($delimiter, $row), array_merge([self::HEADER], $rows));

        return UploadedFile::fake()->createWithContent('xodimlar.csv', ($bom ? "\xEF\xBB\xBF" : '').implode("\r\n", $lines));
    }

    public function test_dry_run_reports_row_errors(): void
    {
        $file = $this->csv([$this->row('11111111111111'), $this->row('1234')]);

        $this->post('/api/employees/import', ['file' => $file, 'dry_run' => 1], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('valid_rows', 1)
            ->assertJsonPath('errors.0.row', 3)
            ->assertJsonPath('errors.0.field', 'jshshir');

        $this->assertSame(0, Employee::count());
    }

    public function test_unknown_department_and_position_are_reported(): void
    {
        $file = $this->csv([$this->row('11111111111111', ['bolim' => 'Yoq', 'lavozim' => 'Kosmonavt'])]);

        $fields = collect($this->post('/api/employees/import', ['file' => $file, 'dry_run' => 1], ['Accept' => 'application/json'])
            ->assertOk()->json('errors'))->pluck('field');

        $this->assertEqualsCanonicalizing(['bolim', 'lavozim'], $fields->all());
    }

    public function test_duplicate_pinfl_in_file_and_db_is_reported(): void
    {
        Employee::factory()->create(['pinfl' => '22222222222222']);
        $file = $this->csv([$this->row('11111111111111'), $this->row('11111111111111'), $this->row('22222222222222')]);

        $errors = $this->post('/api/employees/import', ['file' => $file, 'dry_run' => 1], ['Accept' => 'application/json'])
            ->assertOk()->json('errors');

        $this->assertSame([3, 4], array_column($errors, 'row'));
    }

    public function test_commit_with_errors_writes_nothing(): void
    {
        $file = $this->csv([$this->row('11111111111111'), $this->row('bad')]);

        $this->post('/api/employees/import', ['file' => $file], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertSame(0, Employee::count());
    }

    public function test_commit_creates_employees_with_hire_events(): void
    {
        $file = $this->csv([$this->row('11111111111111'), $this->row('33333333333333', ['bolim' => '', 'jinsi' => 'ayol', 'bandlik_turi' => 'shartnoma'])]);

        $this->post('/api/employees/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('imported', 2);

        $employee = Employee::firstWhere('pinfl', '11111111111111');
        $this->assertSame('Garaj', $employee->department->name);
        $this->assertSame('2020-03-01', $employee->hire_date->toDateString());
        $this->assertSame('male', $employee->gender);
        $this->assertSame(['hire'], $employee->events()->pluck('type')->all());
        $this->assertSame(['female', 'contract', null], [
            Employee::firstWhere('pinfl', '33333333333333')->gender,
            Employee::firstWhere('pinfl', '33333333333333')->employment_type,
            Employee::firstWhere('pinfl', '33333333333333')->department_id,
        ]);
    }

    public function test_semicolon_csv_with_bom_and_dotted_dates_parses(): void
    {
        $file = $this->csv([$this->row('11111111111111', ['ishga_qabul_sanasi' => '01.03.2020', 'buyruq_sanasi' => '28.02.2020', 'tugilgan_sana' => '12.04.1985'])], ';', true);

        $this->post('/api/employees/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('imported', 1);

        $this->assertSame('1985-04-12', Employee::first()->birth_date->toDateString());
    }

    public function test_windows_1251_csv_from_russian_excel_is_converted(): void
    {
        $lines = array_map(fn (array $row) => implode(';', $row), [self::HEADER, $this->row('11111111111111', ['familiya' => 'Каримов', 'ism' => 'Анвар'])]);
        $file = UploadedFile::fake()->createWithContent('xodimlar.csv', mb_convert_encoding(implode("\r\n", $lines), 'Windows-1251', 'UTF-8'));

        $this->post('/api/employees/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('imported', 1);

        $this->assertSame('Каримов Анвар Olimovich', Employee::first()->full_name);
    }

    public function test_xlsx_with_date_cells_parses(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::HEADER));
        $row = $this->row('11111111111111');
        $row[13] = new DateTimeImmutable('2020-03-01');
        $writer->addRow(Row::fromValues($row));
        $writer->close();

        $file = new UploadedFile($path, 'xodimlar.xlsx', null, null, true);

        $this->post('/api/employees/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('imported', 1);

        $this->assertSame('2020-03-01', Employee::first()->hire_date->toDateString());
    }

    public function test_rows_for_inaccessible_organization_are_rejected(): void
    {
        Organization::factory()->create(['code' => 'TLY-MGQB']);
        $file = $this->csv([$this->row('11111111111111', ['tashkilot_kodi' => 'TLY-MGQB', 'bolim' => ''])]);

        $this->post('/api/employees/import', ['file' => $file, 'dry_run' => 1], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('errors.0.field', 'tashkilot_kodi');
    }

    public function test_missing_required_column_is_reported(): void
    {
        $file = UploadedFile::fake()->createWithContent('xodimlar.csv', "familiya,ism\nKarimov,Anvar");

        $this->post('/api/employees/import', ['file' => $file, 'dry_run' => 1], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('errors.0.row', 1);
    }

    public function test_template_downloads_as_xlsx(): void
    {
        $this->get('/api/employees/import-template')
            ->assertOk()
            ->assertDownload('xodimlar-shablon.xlsx');
    }

    public function test_viewer_cannot_import(): void
    {
        Sanctum::actingAs(User::factory()->withRole('viewer')->create(['organization_id' => $this->organization->id]));

        $this->post('/api/employees/import', ['file' => $this->csv([]), 'dry_run' => 1], ['Accept' => 'application/json'])
            ->assertForbidden();
    }
}
