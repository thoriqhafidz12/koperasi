<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NeracaExport implements FromArray, WithColumnFormatting, WithStrictNullComparison, WithStyles, WithEvents
{
    /** @var array Baris siap tulis: judul, periode, header, lalu baris konfigurasi ms_neraca. */
    private array $rows;

    /** @var array Nomor baris section (header/total) untuk styling bold. */
    private array $sectionRows = [];

    public function __construct(array $neracaRows, float $tAktiva, float $tPasiva, float $selisih, bool $seimbang, string $tglAwalFormat, string $tglAkhirFormat)
    {
        $this->rows = [
            ['LAPORAN NERACA', ''],
            ['Periode: ' . $tglAwalFormat . ' s.d. ' . $tglAkhirFormat, ''],
            ['', ''],
            ['KETERANGAN', 'JUMLAH'],
        ];

        $no = 4; // Baris header (1-index)

        foreach ($neracaRows as $row) {
            $no++;
            $teks = trim(($row['kodeTampil'] ? $row['kodeTampil'] . ' ' : '') . $row['nama']);

            if ($row['type'] === 'header') {
                $this->rows[] = [strtoupper($teks), ''];
                $this->sectionRows[] = $no;
            } elseif ($row['type'] === 'total') {
                $this->rows[] = [$teks, (float) $row['saldo']];
                $this->sectionRows[] = $no;
            } elseif ($row['type'] === 'objek') {
                // Rincian objek: indent teks, nol ditulis kosong (sama dengan PDF/HTML).
                $this->rows[] = ['    ' . $teks, $row['saldo'] == 0 ? '' : (float) $row['saldo']];
            } else {
                // Item nol ditulis kosong agar tampilan bersih (sama dengan PDF/HTML).
                $this->rows[] = [$teks, $row['saldo'] == 0 ? '' : (float) $row['saldo']];
            }
        }

        $no++;
        $this->rows[] = ['', ''];

        $no++;
        $this->rows[] = ['JUMLAH AKTIVA', (float) $tAktiva];
        $this->sectionRows[] = $no;

        $no++;
        $this->rows[] = ['JUMLAH PASIVA', (float) $tPasiva];
        $this->sectionRows[] = $no;

        $no++;
        $this->rows[] = ['SELISIH (AKTIVA - PASIVA)', (float) $selisih];
        $this->sectionRows[] = $no;

        $no++;
        $this->rows[] = [$seimbang ? 'Neraca SEIMBANG' : 'PERHATIAN: NERACA TIDAK SEIMBANG', ''];
        $this->sectionRows[] = $no;
    }

    public function array(): array
    {
        return $this->rows;
    }

    /**
     * Format angka ribuan untuk kolom JUMLAH (B);
     * nilai negatif ditampilkan dalam kurung.
     */
    public function columnFormats(): array
    {
        return [
            'B' => '#,##0.00;(#,##0.00)',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
            4 => ['font' => ['bold' => true]],
        ];
    }

    /** Gabung sel A1:B1 (judul) dan A2:B2 (periode), tebalkan baris header/total. */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $worksheet = $event->sheet->getDelegate();
                $worksheet->mergeCells('A1:B1');
                $worksheet->mergeCells('A2:B2');
                $worksheet->getColumnDimension('A')->setWidth(60);

                foreach ($this->sectionRows as $rowNo) {
                    $worksheet->getStyle("A{$rowNo}:B{$rowNo}")->getFont()->setBold(true);
                }
            },
        ];
    }
}
