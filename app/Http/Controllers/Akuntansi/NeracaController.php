<?php

namespace App\Http\Controllers\Akuntansi;

use App\Exports\NeracaExport;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Report\CetakNeracaController;
use App\Models\Master\Neraca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class NeracaController extends BaseController
{
    public function __construct()
    {
        $this->titlePage = 'Laporan Neraca';
        $this->route = 'rpt-neraca';
        $this->model = Neraca::class;

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
     * Halaman laporan neraca.
     */
    public function index(Request $request): View
    {
        return view('akuntansi.neraca', [
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

        $neraca = $this->buildNeraca($tglAwal, $tglAkhir);

        $data = [];
        foreach ($neraca['rows'] as $row) {
            $data[] = [
                'nrId' => $row['nrId'],
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
            'tAktiva' => $this->formatSaldo($neraca['tAktiva']),
            'tPasiva' => $this->formatSaldo($neraca['tPasiva']),
            'selisih' => $this->formatSaldo($neraca['selisih']),
            'seimbang' => $neraca['seimbang'],
            'tglAwal' => $this->dateIDkosong($tglAwal),
            'tglAkhir' => $this->dateIDkosong($tglAkhir),
        ]);
    }

    public function pdf(Request $request)
    {
        $cetak = new CetakNeracaController();
        return $cetak->pdf($request);
    }

    /**
     * Export Excel: parameter filter sama dengan cetak PDF.
     * Layout & styling ada di App\Exports\NeracaExport.
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

        $neraca = $this->buildNeraca($tglAwal, $tglAkhir);

        $export = new NeracaExport(
            $neraca['rows'],
            $neraca['tAktiva'],
            $neraca['tPasiva'],
            $neraca['selisih'],
            $neraca['seimbang'],
            $this->dateIDkosong($tglAwal),
            $this->dateIDkosong($tglAkhir)
        );

        return Excel::download($export, 'Neraca.xlsx');
    }
}
