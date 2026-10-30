<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\BaseController;
use App\Models\Master\Neraca;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterNeracaController extends BaseController
{
    public function __construct()
    {
        $this->model = Neraca::class;
        $this->route = 'ms-neraca';
        $this->titlePage = 'Master Item Neraca';
        $this->primaryKey = 'nrId';
        $this->table = 'ms_neraca';
        $this->searchColumn = ['nrKode', 'nrNama', 'nrKodeAkun'];

        $this->rules = [
            'nrKode' => 'required|unique:ms_neraca,nrKode',
        ];

        $this->form = [
            [
                'name' => 'nrKode',
                'label' => 'Kode Baris',
                'placeholder' => 'mis. 1.01.01 / T-AKTIVA / LR',
                'type' => 'text',
                'col' => 'col-md-6',
                'required' => true,
            ],
            [
                'name' => 'nrNama',
                'label' => 'Nama Baris',
                'placeholder' => 'mis. KAS',
                'type' => 'text',
                'col' => 'col-md-6',
                'required' => true,
            ],
            [
                'name' => 'nrPosisi',
                'label' => 'Posisi',
                'placeholder' => '-- Pilih posisi --',
                'type' => 'select',
                'col' => 'col-md-6',
                'required' => true,
                'options' => [
                    ['value' => 'AKTIVA', 'label' => 'AKTIVA'],
                    ['value' => 'PASIVA', 'label' => 'PASIVA'],
                ],
            ],
            [
                'name' => 'nrJenis',
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
                'name' => 'nrKodeAkun',
                'label' => 'Kode Akun (Prefix Objek)',
                'placeholder' => 'Contoh: 1.01.01 atau 4,5 (pisah dengan koma)',
                'type' => 'text',
                'col' => 'col-md-6',
                'required' => false,
                'rules' => ['string', 'max:150'],
            ],
            [
                'name' => 'nrSaldoNormal',
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
                'name' => 'nrUrut',
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
                'field' => 'nrUrut',
                'type' => 'text'
            ],
            [
                'label' => 'Kode',
                'field' => 'nrKode',
                'type' => 'text'
            ],
            [
                'label' => 'Nama',
                'field' => 'nrNama',
                'type' => 'text'
            ],
            [
                'label' => 'Posisi',
                'field' => 'nrPosisi',
                'type' => 'badge'
            ],
            [
                'label' => 'Jenis',
                'field' => 'nrJenis',
                'type' => 'badge'
            ],
            [
                'label' => 'Kode Akun',
                'field' => 'nrKodeAkun',
                'type' => 'text'
            ],
            [
                'label' => 'Saldo Normal',
                'field' => 'nrSaldoNormal',
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

        $items = $query->orderBy('nrUrut', 'asc')
            ->orderBy($this->primaryKey, 'asc')
            ->paginate(50)
            ->withQueryString();

        // Saldo saat ini per baris item (satu query untuk seluruh jurnal).
        $saldo = $this->getSaldoNeraca($items->getCollection());
        $items->getCollection()->transform(function ($item) use ($saldo) {
            $item->saldoSaatIni = $saldo[$item->nrId] ?? 0;
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
