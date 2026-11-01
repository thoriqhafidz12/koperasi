<?php

namespace App\Imports;

use App\Models\Jurnal\Jurnal;
use App\Models\Master\Coa\MasterJenis;
use App\Models\Master\Coa\MasterObjek;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class JurnalImport implements ToCollection, WithHeadingRow
{
    private int $importedRows = 0;
    private bool $sheetProcessed = false;

    public function __construct(
        private readonly string $batchId,
        private readonly string $fileName,
    ) {
    }

    public function collection(Collection $rows): void
    {
        $hasDataRows = $rows->contains(fn(Collection $row) => !$this->isEmptyRow($row->toArray()));
        if (!$hasDataRows) {
            return;
        }

        if ($this->sheetProcessed) {
            throw ValidationException::withMessages([
                'file' => 'File Excel harus berisi satu sheet transaksi saja.',
            ]);
        }

        $this->sheetProcessed = true;
        $objectNames = MasterObjek::query()
            ->whereNull('msoDeletedAt')
            ->pluck('msoNama', 'msoKode');

        // $objectNames = MasterJenis::query()
        //     ->whereNull('msjDeletedAt')
        //     ->pluck('msjNama', 'msjKode');

        $accountCodes = $objectNames->keys()->map(fn($code) => (string) $code)->all();
        $records = [];
        $errors = [];
        $timestamp = now();

        foreach ($rows as $index => $rowCollection) {
            $raw = $rowCollection->toArray();
            if ($this->isEmptyRow($raw)) {
                continue;
            }

            $rowNumber = $index + 2;
            $no = $rowNumber - 1;
            $data = [
                'no_jurnal' => $this->normalizeText($raw['no_jurnal'] ?? 'IJ-' . $no),
                'tanggal' => $this->normalizeDate($raw['tanggal'] ?? null),
                'keterangan' => $this->normalizeText($raw['keterangan'] ?? null),
                'kode_debet' => $this->normalizeText($raw['kode_debet'] ?? null),
                'nilai_debet' => $raw['nilai_debet'] ?? null,
                'kode_kredit' => $this->normalizeText($raw['kode_kredit'] ?? null),
                'nilai_kredit' => $raw['nilai_kredit'] ?? null,
                'sumber' => $this->normalizeText($raw['sumber'] ?? null),
            ];

            $validator = Validator::make($data, [
                'no_jurnal' => ['required', 'string', 'max:100'],
                'tanggal' => ['required', 'date_format:Y-m-d'],
                'keterangan' => ['nullable', 'string', 'max:255'],
                // 'kode_debet' => ['required', 'string', 'max:20', Rule::in($accountCodes)],
                // 'nilai_debet' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
                // 'kode_kredit' => ['required', 'string', 'max:20', Rule::in($accountCodes)],
                // 'nilai_kredit' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
                'sumber' => ['nullable', 'string', 'max:100'],
            ], [
                'no_jurnal.required' => 'Nomor jurnal wajib diisi.',
                'tanggal.required' => 'Tanggal wajib diisi.',
                'tanggal.date_format' => 'Tanggal harus berformat YYYY-MM-DD, DD/MM/YYYY, atau DD-MM-YYYY.',
                'kode_debet.required' => 'Kode akun debet wajib diisi.',
                'kode_debet.in' => 'Kode akun debet tidak ditemukan di Master Objek.',
                'nilai_debet.required' => 'Nilai debet wajib diisi.',
                'nilai_debet.numeric' => 'Nilai debet harus berupa angka.',
                'nilai_debet.gt' => 'Nilai debet harus lebih besar dari 0.',
                'nilai_debet.decimal' => 'Nilai debet maksimal 2 angka desimal.',
                'kode_kredit.required' => 'Kode akun kredit wajib diisi.',
                'kode_kredit.in' => 'Kode akun kredit tidak ditemukan di Master Objek.',
                'nilai_kredit.required' => 'Nilai kredit wajib diisi.',
                'nilai_kredit.numeric' => 'Nilai kredit harus berupa angka.',
                'nilai_kredit.gt' => 'Nilai kredit harus lebih besar dari 0.',
                'nilai_kredit.decimal' => 'Nilai kredit maksimal 2 angka desimal.',
            ], [
                'no_jurnal' => 'Nomor jurnal',
                'tanggal' => 'Tanggal',
                'keterangan' => 'Keterangan',
                'kode_debet' => 'Kode akun debet',
                'nilai_debet' => 'Nilai debet',
                'kode_kredit' => 'Kode akun kredit',
                'nilai_kredit' => 'Nilai kredit',
                'sumber' => 'Sumber',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = "Baris {$rowNumber}: {$message}";
                }
                continue;
            }

            // if (round((float) $data['nilai_debet'] * 100) !== round((float) $data['nilai_kredit'] * 100)) {
            //     $errors[] = "Baris {$rowNumber}: nilai debet dan kredit harus sama.";
            //     continue;
            // }

            $records[] = [
                'jHeadId' => null,
                'jNo' => $data['no_jurnal'],
                'jTgl' => $data['tanggal'],
                'jKeterangan' => $data['keterangan'],
                'jRekDebetKode' => $data['kode_debet'],
                'jRekDebetNama' => $objectNames->get($data['kode_debet']),
                'jDebetNilai' => $data['nilai_debet'] ?? 0,
                'jRekKreditKode' => $data['kode_kredit'],
                'jRekKreditNama' => $objectNames->get($data['kode_kredit']),
                'jKreditNilai' => $data['nilai_kredit'] ?? 0,
                'jSumber' => $data['sumber'] ?: 'IMPORT EXCEL',
                'jImportBatchId' => $this->batchId,
                'jImportFile' => $this->fileName,
                'jStatus' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'file' => array_slice($errors, 0, 50),
            ]);
        }

        foreach (array_chunk($records, 100) as $chunk) {
            Jurnal::insert($chunk);
        }

        $this->importedRows = count($records);
    }

    public function importedRows(): int
    {
        return $this->importedRows;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && !(is_string($value) && trim($value) === '')) {
                return false;
            }
        }

        return true;
    }

    private function normalizeText(mixed $value): mixed
    {
        if (!is_string($value) && !is_numeric($value)) {
            return $value;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeDate(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value) && (float) $value >= 1 && (float) $value <= 2958465) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        $value = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            $dateErrors = DateTimeImmutable::getLastErrors();

            if ($date && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0)) && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }
}
