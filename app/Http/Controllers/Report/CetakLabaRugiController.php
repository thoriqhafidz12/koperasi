<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\BaseController;
use App\Libraries\CustomPdf;
use Illuminate\Http\Request;

class cFPDFLabaRugi extends CustomPdf
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

class CetakLabaRugiController extends BaseController
{
    /**
     * Cetak PDF laba rugi (portrait A4): blok PENDAPATAN lalu BIAYA,
     * baris header berisi abu-abu, item indent, total tebal,
     * diakhiri baris LABA (RUGI) BERSIH dan status laba/rugi.
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

        $labarugi = $this->buildLabaRugi($tglAwal, $tglAkhir);

        $pdf = new cFPDFLabaRugi('P', 'mm', 'A4');
        $pdf->AliasNbPages();

        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 5, 'LAPORAN LABA RUGI', 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Periode: ' . $this->dateIDkosong($tglAwal) . ' s.d. ' . $this->dateIDkosong($tglAkhir), 0, 1, 'C');
        $pdf->Ln(4);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(130, 5, 'KETERANGAN', 1, 0, 'C');
        $pdf->Cell(60, 5, 'JUMLAH', 1, 1, 'C');

        $pdf->setWidths([130, 60]);
        $pdf->setAligns(['L', 'R']);

        foreach ($labarugi['rows'] as $row) {
            $teks = trim(($row['kodeTampil'] ? $row['kodeTampil'] . ' ' : '') . $row['nama']);

            if ($row['type'] === 'header') {
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetFillColor(225, 225, 225);
                $pdf->Cell(190, 5, strtoupper($teks), 1, 1, 'L', true);
                $pdf->SetFillColor(255, 255, 255);
            } elseif ($row['type'] === 'total') {
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell(130, 5, $teks, 1, 0, 'R');
                $pdf->Cell(60, 5, $this->formatSaldo($row['saldo']), 1, 1, 'R');
            } elseif ($row['type'] === 'objek') {
                $pdf->SetFont('Arial', '', 8);
                $pdf->Row([
                    '      ' . $teks,
                    $row['saldo'] == 0 ? '-' : $this->formatSaldo($row['saldo']),
                ]);
            } else {
                $pdf->SetFont('Arial', '', 9);
                $pdf->Row([
                    ($row['indent'] ? '   ' : '') . $teks,
                    $row['saldo'] == 0 ? '-' : $this->formatSaldo($row['saldo']),
                ]);
            }
        }

        // Laba (rugi) bersih
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(130, 5, 'LABA (RUGI) BERSIH', 1, 0, 'R');
        $pdf->Cell(60, 5, $this->formatSaldo($labarugi['labaBersih']), 1, 1, 'R');
        $pdf->SetFont('Arial', 'BI', 9);
        $pdf->Cell(0, 6, $labarugi['laba'] ? 'LABA BERSIH' : 'RUGI BERSIH', 0, 1, 'R');

        // Ambil PDF sebagai string, lalu kirim melalui response Laravel.
        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="LabaRugi"',
        ]);
    }
}
