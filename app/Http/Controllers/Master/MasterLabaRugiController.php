<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\BaseController;
use App\Models\Master\LabaRugi;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterLabaRugiController extends BaseController
{
    public function __construct()
    {
        $this->model = LabaRugi::class;
        $this->route = 'ms-labarugi';
        $this->titlePage = 'Master Item Laba Rugi';
        $this->primaryKey = 'lrId';
        $this->table = 'ms_labarugi';
        $this->searchColumn = ['lrKode', 'lrNama', 'lrKodeAkun'];

        $this->rules = [
            'lrKode' => 'required|unique:ms_labarugi,lrKode',
        ];

        $this->form = [
            [
                'name' => 'lrKode',
                'label' => 'Kode Baris',
                'placeholder' => 'mis. 5.02.03 / T-PENDAPATAN',
                'type' => 'text',
                'col' => 'col-md-6',
                'required' => true,
            ],
            [
                'name' => 'lrNama',
                'label' => 'Nama Baris',
                'placeholder' => 'mis. BIAYA ADM & UMUM',
                'type' => 'text',
                'col' => 'col-md-6',
                'required' => true,
            ],
            [
                'name' => 'lrPosisi',
                'label' => 'Posisi',
                'placeholder' => '-- Pilih posisi --',
                'type' => 'select',
                'col' => 'col-md-6',
                'required' => true,
                'options' => [
                    ['value' => 'PENDAPATAN', 'label' => 'PENDAPATAN'],
                    ['value' => 'BIAYA', 'label' => 'BIAYA'],
                ],
            ],
            [
                'name' => 'lrJenis',
                'label' => 'Jenis Baris',
                'placeholder' => '-- Pilih jenis --',
                'type' => 'select',
                'col' => 'col-md-6',
                'required' => true,
                'options' => [
                    ['value' => 'header', 'label' => 'Header / Kategori'],
                    ['value' => 'item', 'label' => 'Item / Isi'],
                    ['value' => 'objek', 'label' => 'Objek / Rincian'],
                    ['value' => 'total', 'label' => 'Total / Jumlah'],
                ],
            ],
            [
                'name' => 'lrKodeAkun',
                'label' => 'Kode Akun (Prefix Objek)',
                'placeholder' => 'Contoh: 4.01.01 atau 4,5 (pisah dengan koma)',
                'type' => 'text',
                'col' => 'col-md-6',
                'required' => false,
                'rules' => ['string', 'max:150'],
            ],
            [
                'name' => 'lrSaldoNormal',
                'label' => 'Saldo Normal',
                'placeholder' => '-- Pilih saldo normal --',
                'type' => 'select',
                'col' => 'col-md-6',
                'required' => true,
                'options' => [
                    ['value' => 'D', 'label' => 'D (Debet) — debet - kredit'],
                    ['value' => 'K', 'label' => 'K (Kredit) — kredit - debet'],
                ],
            ],
            [
                'name' => 'lrUrut',
                'label' => 'Urutan',
                'placeholder' => 'mis. 10, 20, 30',
                'type' => 'number',
                'col' => 'col-md-6',
                'required' => true,
                'rules' => ['integer'],
            ],
        ];

        $this->grid = [
            [
                'label' => 'Urutan',
                'field' => 'lrUrut',
                'type' => 'text'
            ],
            [
                'label' => 'Kode',
                'field' => 'lrKode',
                'type' => 'text'
            ],
            [
                'label' => 'Nama',
                'field' => 'lrNama',
                'type' => 'text'
            ],
            [
                'label' => 'Posisi',
                'field' => 'lrPosisi',
                'type' => 'badge'
            ],
            [
                'label' => 'Jenis',
                'field' => 'lrJenis',
                'type' => 'badge'
            ],
            [
                'label' => 'Kode Akun',
                'field' => 'lrKodeAkun',
                'type' => 'text'
            ],
            [
                'label' => 'Saldo Normal',
                'field' => 'lrSaldoNormal',
                'type' => 'text'
            ],
            [
                'label' => 'Saldo Saat Ini',
                'field' => 'saldoSaatIni',
                'type' => 'angka'
            ],
        ];
    }

    public function index(Request $request): View
    {
        $search = $request->get('search');
        $editId = $request->get('edit');

        $query = $this->model::query();

        if ($search && $this->searchColumn) {
            $columns = (array) $this->searchColumn;
            $query->where(function ($q) use ($columns, $search) {
                foreach ($columns as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
            });
        }

        $items = $query->orderBy('lrUrut', 'asc')
            ->orderBy($this->primaryKey, 'asc')
            ->paginate(50)
            ->withQueryString();

        // Saldo saat ini per baris item (satu query untuk seluruh jurnal,
        // konsisten dengan master neraca — tanpa filter tanggal = semua periode).
        $saldo = $this->getSaldoLabaRugi($items->getCollection());
        $items->getCollection()->transform(function ($item) use ($saldo) {
            $item->saldoSaatIni = $saldo[$item->lrId] ?? 0;
            return $item;
        });

        $editData = null;
        if ($editId) {
            $editData = $this->model::where($this->primaryKey, $editId)->first();
        }

        $extra = [];
        foreach ($this->extraViewData as $key => $resolver) {
            $extra[$key] = is_callable($resolver) ? $resolver() : $resolver;
        }

        return view('master', array_merge([
            'items' => $items,
            'search' => $search,
            'editData' => $editData,
            'form' => $this->form,
            'route' => $this->route,
            'primaryKey' => $this->primaryKey,
            'titlePage' => $this->titlePage,
            'grid' => $this->grid,
        ], $extra));
    }
}
