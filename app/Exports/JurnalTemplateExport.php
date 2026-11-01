<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JurnalTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    public function array(): array
    {
        return [
            [
                'IJ-001',
                Carbon::createFromFormat('d/m/Y', '01/01/2026')->format('d/m/Y'),
                'PENERIMAAN UANG 1',
                '1.01.01.01',
                '100000.00',
                '',
                '',
            ],
            [
                'IJ-002',
                Carbon::createFromFormat('d/m/Y', '02/01/2026')->format('d/m/Y'),
                'PENERIMAAN UANG 2',
                '1.01.01.01',
                '50000.00',
                '',
                '',
            ],
            [
                'IJ-003',
                Carbon::createFromFormat('d/m/Y', '03/01/2026')->format('d/m/Y'),
                'PENERIMAAN UANG 3',
                '1.01.01.01',
                '50000.00',
                '',
                '',
            ],
            [
                'IJ-004',
                Carbon::createFromFormat('d/m/Y', '03/01/2026')->format('d/m/Y'),
                'PENGELUARAN UANG 1',
                '',
                '',
                '5.02.01.14',
                '100000.00',
            ],
            [
                'IJ-005',
                Carbon::createFromFormat('d/m/Y', '04/01/2026')->format('d/m/Y'),
                'PENGELUARAN UANG 2',
                '',
                '',
                '5.02.03.17',
                '100000.00',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'No Jurnal',
            'Tanggal',
            'Keterangan',
            'Kode Debet',
            'Nilai Debet',
            'Kode Kredit',
            'Nilai Kredit',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],

            'B' => [
                'numberFormat' => [
                    'formatCode' => 'dd/mm/yyyy',
                ],
            ],
            // Kolom Nilai Debet dan Kredit
            'E' => [
                'numberFormat' => [
                    'formatCode' => '#,##0.00',
                ],
            ],

            'G' => [
                'numberFormat' => [
                    'formatCode' => '#,##0.00',
                ],
            ],
        ];
    }
}
