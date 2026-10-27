<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Hr\EmployeeImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeImportController extends Controller
{
    public function __construct(private EmployeeImporter $importer) {}

    public function template(): BinaryFileResponse
    {
        Gate::authorize('create', Employee::class);

        $path = tempnam(sys_get_temp_dir(), 'hr-template');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(EmployeeImporter::COLUMNS));
        $writer->close();

        return response()->download($path, 'xodimlar-shablon.xlsx')->deleteFileAfterSend();
    }

    /**
     * Check the file; unless it is a dry run, import it when every row is valid.
     */
    public function import(Request $request): JsonResponse
    {
        Gate::authorize('create', Employee::class);

        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'extensions:xlsx,csv'],
            'dry_run' => ['boolean'],
        ]);

        ['rows' => $rows, 'errors' => $errors] = $this->importer->validateFile($request->file('file'), $request->user());

        if ($request->boolean('dry_run')) {
            return response()->json(['valid_rows' => count($rows), 'errors' => $errors]);
        }

        if ($errors !== []) {
            return response()->json(['message' => 'Faylda xatolar bor, hech narsa saqlanmadi', 'errors' => $errors], 422);
        }

        return response()->json(['imported' => $this->importer->import($rows, $request->user())]);
    }
}
