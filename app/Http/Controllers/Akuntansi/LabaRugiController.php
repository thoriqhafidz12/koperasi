<?php

namespace App\Http\Controllers\Akuntansi;

use App\Exports\LabaRugiExport;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Report\CetakLabaRugiController;
use App\Models\Master\LabaRugi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class LabaRugiController extends BaseController
{
    public function __construct()
    {
        $this->titlePage = 'Laporan Laba Rugi';
        $this->route = 'rpt-labarugi';
        $this->model = LabaRugi::class;

        $this->form = [
            [
                'name' => 'jTglAwal',
                'label' => 'Tanggal Awal',
                'placeholder' => 'Masukkan tanggal awal',
                'type' => 'date',
                'col' => 'col-md-3',
                'required' => true,
                'default' => date('Y-m-01')
            ],
            [
                'name' => 'jTglAkhir',
                'label' => 'Tanggal Akhir',
                'placeholder' => 'Masukkan tanggal akhir',
                'type' => 'date',
                'col' => 'col-md-3',
                'required' => true,
                'default' => date('Y-m-t')
            ],
        ];

        $this->grid = [
            [
                'label' => 'Keterangan',
                'field' => 'nama',
                'type' => 'text',
                'class' => 'text-left'
            ],
            [
                'label' => 'Jumlah',
                'field' => 'saldo',
                'type' => 'angka',
                'class' => 'text-right'
            ],
        ];
    }

    /**
     * Halaman laporan laba rugi.
     */
    public function index(Request $request): View
    {
        return view('akuntansi.labarugi', [
            'titlePage' => $this->titlePage,
            'route' => $this->route,
            'form' => $this->form,
            'grid' => $this->grid,
        ]);
    }

    public function loadData(Request $request): JsonResponse
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

        $data = [];
        foreach ($labarugi['rows'] as $row) {
            $data[] = [
                'lrId' => $row['lrId'],
                'type' => $row['type'],
                'posisi' => $row['posisi'],
                'kodeTampil' => $row['kodeTampil'],
                'nama' => $row['nama'],
                'indent' => $row['indent'],
                // header tanpa nilai; item/objek nol tampil '-' di view; total selalu diformat.
                'saldo' => $row['type'] === 'header'
                    ? ''
                    : (in_array($row['type'], ['item', 'objek'], true) && $row['saldo'] == 0
                        ? ''
                        : $this->formatSaldo($row['saldo'])),
            ];
        }

        return response()->json([
            'data' => $data,
            'tPendapatan' => $this->formatSaldo($labarugi['tPendapatan']),
            'tBiaya' => $this->formatSaldo($labarugi['tBiaya']),
            'labaBersih' => $this->formatSaldo($labarugi['labaBersih']),
            'laba' => $labarugi['laba'],
            'tglAwal' => $this->dateIDkosong($tglAwal),
            'tglAkhir' => $this->dateIDkosong($tglAkhir),
        ]);
    }

    public function pdf(Request $request)
    {
        $cetak = new CetakLabaRugiController();
        return $cetak->pdf($request);
    }

    /**
     * Export Excel: parameter filter sama dengan cetak PDF.
     * Layout & styling ada di App\Exports\LabaRugiExport.
     */
    public function excel(Request $request)
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

        $export = new LabaRugiExport(
            $labarugi['rows'],
            $labarugi['tPendapatan'],
            $labarugi['tBiaya'],
            $labarugi['labaBersih'],
            $labarugi['laba'],
            $this->dateIDkosong($tglAwal),
            $this->dateIDkosong($tglAkhir)
        );

        return Excel::download($export, 'LabaRugi.xlsx');
    }
}
