<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PembagianShuExport implements FromArray, WithColumnFormatting, WithStrictNullComparison, WithStyles, WithEvents
{
    /** @var array Baris siap tulis: judul, periode, SHU basis, header, lalu rincian ms_shu. */
    private array $rows;

    /** @var array Nomor baris section (header/total/status) untuk styling bold. */
    private array $sectionRows = [];

    public function __construct(
        array $shuRows,
        float $totalPersen,
        float $totalNilai,
        float $shu,
        bool $laba,
        bool $persenOk,
        string $tglAwalFormat,
        string $tglAkhirFormat
    ) {
        $this->rows = [
            ['RINCIAN PEMBAGIAN LABA RUGI', '', '', '', ''],
            ['Periode: ' . $tglAwalFormat . ' s.d. ' . $tglAkhirFormat, '', '', '', ''],
            [$laba ? 'SHU (LABA BERSIH)' : 'SHU (RUGI BERSIH)', (float) $shu, '', '', ''],
            ['', '', '', '', ''],
            ['NO', 'KATEGORI', 'OBJEK COA', 'PERSENTASE (%)', 'NILAI'],
        ];

        $this->sectionRows = [3, 5];

        $no = 5; // Baris header kolom (1-index)

        foreach ($shuRows as $row) {
            $no++;
            $objek = $row['objekKode']
                ? trim($row['objekKode'] . ($row['objekNama'] ? ' - ' . $row['objekNama'] : ''))
                : '-';
            // Nilai nol ditulis kosong agar tampilan bersih (sama dengan PDF/HTML).
            $this->rows[] = [
                (int) $row['no'],
                $row['kategori'],
                $objek,
                (float) $row['persen'],
                $row['nilai'] == 0 ? '' : (float) $row['nilai'],
            ];
        }

        if (empty($shuRows)) {
            $no++;
            $this->rows[] = ['Belum ada konfigurasi pembagian SHU aktif.', '', '', '', ''];
        }

        $no++;
        $this->rows[] = ['', '', '', '', ''];

        $no++;
        $this->rows[] = ['TOTAL', '', '', (float) $totalPersen, (float) $totalNilai];
        $this->sectionRows[] = $no;

        $no++;
        $this->rows[] = [$laba ? 'SHU (LABA BERSIH)' : 'SHU (RUGI BERSIH)', (float) $shu, '', '', ''];
        $this->sectionRows[] = $no;

        if (!$persenOk) {
            $this->rows[] = ['Perhatian: Total persentase konfigurasi belum 100%.', '', '', '', ''];
        }
        if (!$laba) {
            $this->rows[] = ['Perhatian: Periode ini RUGI, nilai pembagian ditampilkan negatif.', '', '', '', ''];
        }
    }

    public function array(): array
    {
        return $this->rows;
    }

    /**
     * Format angka: B = SHU basis, D = persentase, E = nilai rincian.
     * Nilai negatif ditampilkan dalam kurung.
     */
    public function columnFormats(): array
    {
        return [
            'B' => '#,##0.00;(#,##0.00)',
            'D' => '0.00"%"',
            'E' => '#,##0.00;(#,##0.00)',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
            3 => ['font' => ['bold' => true]],
            5 => ['font' => ['bold' => true]],
        ];
    }

    /** Gabung sel A1:E1 (judul) dan A2:E2 (periode), atur lebar kolom, tebalkan baris section. */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $worksheet = $event->sheet->getDelegate();
                $worksheet->mergeCells('A1:E1');
                $worksheet->mergeCells('A2:E2');
                $worksheet->getColumnDimension('A')->setWidth(6);
                $worksheet->getColumnDimension('B')->setWidth(42);
                $worksheet->getColumnDimension('C')->setWidth(42);
                $worksheet->getColumnDimension('D')->setWidth(16);
                $worksheet->getColumnDimension('E')->setWidth(20);

                foreach ($this->sectionRows as $rowNo) {
                    $worksheet->getStyle("A{$rowNo}:E{$rowNo}")->getFont()->setBold(true);
                }
            },
        ];
    }
}
