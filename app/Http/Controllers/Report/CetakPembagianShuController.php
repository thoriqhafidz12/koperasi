<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\BaseController;
use App\Libraries\CustomPdf;
use Illuminate\Http\Request;

class cFPDFPembagianShu extends CustomPdf
{
    // Custom footer for the PDF
    function Footer()
    {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        // Set font and add page number
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Hal ' . $this->PageNo() . ' - {nb}', 0, 0, 'C');
    }
}

class CetakPembagianShuController extends BaseController
{
    /**
     * Cetak PDF rincian pembagian laba rugi (portrait A4): nilai SHU periode
     * di atas, rincian per kategori (persentase x SHU), baris TOTAL,
     * lalu status laba/rugi dan warning bila total persen belum 100%.
     */
    public function pdf(Request $request)
    {
        try {
            [$tglAwal, $tglAkhir] = $this->validasiTanggalLaporan(
                $request->input('jTglAwal'),
                $request->input('jTglAkhir')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $pembagian = $this->buildPembagianShu($tglAwal, $tglAkhir);

        $pdf = new cFPDFPembagianShu('P', 'mm', 'A4');
        $pdf->AliasNbPages();

        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 5, 'RINCIAN PEMBAGIAN LABA RUGI', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, 'Periode: ' . $this->dateIDkosong($tglAwal) . ' s.d. ' . $this->dateIDkosong($tglAkhir), 0, 1, 'C');
        $pdf->Ln(2);

        // Nilai SHU basis periode
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(120, 6, $pembagian['laba'] ? 'SHU (LABA BERSIH)' : 'SHU (RUGI BERSIH)', 0, 0, 'L');
        $pdf->Cell(70, 6, $this->formatSaldo($pembagian['shu']), 0, 1, 'R');
        $pdf->Ln(2);

        $pdf->SetFillColor(225, 225, 225);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(10, 6, 'NO', 1, 0, 'C', true);
        $pdf->Cell(60, 6, 'KATEGORI', 1, 0, 'C', true);
        $pdf->Cell(50, 6, 'OBJEK COA', 1, 0, 'C', true);
        $pdf->Cell(20, 6, '%', 1, 0, 'C', true);
        $pdf->Cell(50, 6, 'NILAI', 1, 1, 'C', true);

        $pdf->setWidths([10, 60, 50, 20, 50]);
        $pdf->setAligns(['C', 'L', 'L', 'R', 'R']);

        if (empty($pembagian['rows'])) {
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->Cell(190, 6, 'Belum ada konfigurasi pembagian SHU aktif. Atur lewat menu Config Pembagian SHU.', 1, 1, 'C');
        } else {
            foreach ($pembagian['rows'] as $row) {
                $objek = $row['objekKode']
                    ? trim($row['objekKode'] . ($row['objekNama'] ? ' - ' . $row['objekNama'] : ''))
                    : '-';
                $pdf->SetFont('Arial', '', 9);
                $pdf->Row([
                    (string) $row['no'],
                    $row['kategori'],
                    $objek,
                    $this->formatRupiah($row['persen'], 2) . '%',
                    $row['nilai'] == 0 ? '-' : $this->formatSaldo($row['nilai']),
                ]);
            }

            // Total
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(120, 5, 'TOTAL', 1, 0, 'R');
            $pdf->Cell(20, 5, $this->formatRupiah($pembagian['totalPersen'], 2) . '%', 1, 0, 'R');
            $pdf->Cell(50, 5, $this->formatSaldo($pembagian['totalNilai']), 1, 1, 'R');
        }

        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(120, 5, $pembagian['laba'] ? 'SHU (LABA BERSIH)' : 'SHU (RUGI BERSIH)', 1, 0, 'R');
        $pdf->Cell(70, 5, $this->formatSaldo($pembagian['shu']), 1, 1, 'R');

        $pdf->SetFont('Arial', 'I', 8);
        if (!$pembagian['persenOk']) {
            $pdf->Ln(2);
            $pdf->Cell(0, 5, 'Perhatian: Total persentase konfigurasi ' . $this->formatRupiah($pembagian['totalPersen'], 2) . '% (belum 100%).', 0, 1, 'L');
        }
        if (!$pembagian['laba']) {
            $pdf->Cell(0, 5, 'Perhatian: Periode ini RUGI, nilai pembagian ditampilkan negatif.', 0, 1, 'L');
        }

        // Ambil PDF sebagai string, lalu kirim melalui response Laravel.
        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="PembagianSHU"',
        ]);
    }
}
