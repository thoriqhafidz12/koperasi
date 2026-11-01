<?php

namespace App\Http\Controllers\Akuntansi;

use App\Exports\PembagianShuExport;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Report\CetakPembagianShuController;
use App\Models\Master\PembagianShu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class PembagianShuController extends BaseController
{
    public function __construct()
    {
        $this->titlePage = 'Rincian Pembagian Laba Rugi';
        $this->route = 'rpt-pembagian-shu';
        $this->model = PembagianShu::class;

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
                'label' => 'No',
                'field' => 'no',
                'type' => 'text',
                'class' => 'text-center'
            ],
            [
                'label' => 'Kategori',
                'field' => 'kategori',
                'type' => 'text',
                'class' => 'text-left'
            ],
            [
                'label' => 'Objek COA',
                'field' => 'objek',
                'type' => 'text',
                'class' => 'text-left'
            ],
            [
                'label' => 'Persentase (%)',
                'field' => 'persen',
                'type' => 'angka',
                'class' => 'text-right'
            ],
            [
                'label' => 'Nilai',
                'field' => 'nilai',
                'type' => 'angka',
                'class' => 'text-right'
            ],
        ];
    }

    /**
     * Halaman laporan rincian pembagian laba rugi.
     */
    public function index(Request $request): View
    {
        return view('akuntansi.pembagian-shu', [
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

        $pembagian = $this->buildPembagianShu($tglAwal, $tglAkhir);

        $data = [];
        foreach ($pembagian['rows'] as $row) {
            $data[] = [
                'no' => $row['no'],
                'shuId' => $row['shuId'],
                'shuKode' => $row['shuKode'],
                'kategori' => $row['kategori'],
                'persen' => $this->formatRupiah($row['persen'], 2),
                'objekKode' => $row['objekKode'] ?? '',
                'objekNama' => $row['objekNama'] ?? '',
                'shu' => $this->formatSaldo($pembagian['shu']),
                'nilai' => $this->formatSaldo($row['nilai']),
            ];
        }

        return response()->json([
            'data' => $data,
            'totalPersen' => $this->formatRupiah($pembagian['totalPersen'], 2),
            'totalNilai' => $this->formatSaldo($pembagian['totalNilai']),
            'shu' => $this->formatSaldo($pembagian['shu']),
            'laba' => $pembagian['laba'],
            'persenOk' => $pembagian['persenOk'],
            'tglAwal' => $this->dateIDkosong($tglAwal),
            'tglAkhir' => $this->dateIDkosong($tglAkhir),
        ]);
    }

    public function pdf(Request $request)
    {
        $cetak = new CetakPembagianShuController();
        return $cetak->pdf($request);
    }

    /**
     * Export Excel: parameter filter sama dengan cetak PDF.
     * Layout & styling ada di App\Exports\PembagianShuExport.
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

        $pembagian = $this->buildPembagianShu($tglAwal, $tglAkhir);

        $export = new PembagianShuExport(
            $pembagian['rows'],
            $pembagian['totalPersen'],
            $pembagian['totalNilai'],
            $pembagian['shu'],
            $pembagian['laba'],
            $pembagian['persenOk'],
            $this->dateIDkosong($tglAwal),
            $this->dateIDkosong($tglAkhir)
        );

        return Excel::download($export, 'PembagianSHU.xlsx');
    }
}
