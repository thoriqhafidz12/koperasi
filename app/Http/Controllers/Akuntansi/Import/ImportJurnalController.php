<?php

namespace App\Http\Controllers\Akuntansi\Import;

use App\Exports\JurnalTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\JurnalImport;
use App\Models\Jurnal\Jurnal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ImportJurnalController extends Controller
{
    public function index(): View
    {
        $imports = Jurnal::query()
            ->selectRaw('jImportBatchId, jImportFile, COUNT(*) as total_rows, MIN(created_at) as imported_at')
            ->whereNotNull('jImportBatchId')
            ->groupBy('jImportBatchId', 'jImportFile')
            ->get();

        return view('akuntansi.import-jurnal', compact('imports'));
    }

    public function template()
    {
        return Excel::download(new JurnalTemplateExport(), 'template-import-jurnal.xlsx');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $fileName = basename(str_replace('\\', '/', $request->file('file')->getClientOriginalName()));
        $fileName = Str::limit($fileName, 255, '');
        $import = new JurnalImport((string) Str::uuid(), $fileName);

        try {
            DB::transaction(function () use ($request, $import): void {
                Excel::import($import, $request->file('file'));
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'File gagal dibaca. Gunakan template Excel yang disediakan dan periksa kembali isinya.');
        }

        if ($import->importedRows() === 0) {
            throw ValidationException::withMessages([
                'file' => 'File Excel belum memiliki baris transaksi untuk diimpor.',
            ]);
        }

        return back()->with('success', 'Berhasil mengimpor ' . $import->importedRows() . ' baris jurnal.');
    }

    public function destroyBatch(string $batchId): RedirectResponse
    {
        $deletedRows = DB::transaction(fn() => Jurnal::query()
            ->where('jImportBatchId', $batchId)
            ->delete());

        if ($deletedRows === 0) {
            return back()->with('error', 'Batch import tidak ditemukan atau sudah dihapus.');
        }

        return back()->with('success', "Berhasil menghapus {$deletedRows} baris jurnal dari batch import terpilih.");
    }
}
