<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\BaseController;
use App\Models\Master\Coa\MasterObjek;
use App\Models\Master\PembagianShu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MasterPembagianShuController extends BaseController
{
    public function __construct()
    {
        $this->model = PembagianShu::class;
        $this->route = 'ms-shu';
        $this->titlePage = 'Config Pembagian SHU';
        $this->primaryKey = 'shuId';
        $this->table = 'ms_shu';
        $this->searchColumn = ['shuKode', 'shuNama', 'shuObjekKode'];

        $this->form = [
            [
                'name' => 'shuUrut',
                'label' => 'Urutan',
                'placeholder' => 'mis. 10, 20, 30',
                'type' => 'number',
                'col' => 'col-md-6',
                'required' => true,
                'rules' => ['integer', 'min:0'],
            ],
            [
                'name' => 'shuKode',
                'label' => 'Kode',
                'placeholder' => 'mis. SHU-01',
                'type' => 'text',
                'col' => 'col-md-6',
                'required' => true,
                'unique' => 'ms_shu,shuKode',
                'rules' => ['max:20'],
            ],
            [
                'name' => 'shuNama',
                'label' => 'Kategori',
                'placeholder' => 'mis. Cadangan Modal',
                'type' => 'text',
                'col' => 'col-md-12',
                'required' => true,
                'rules' => ['max:150'],
            ],
            [
                // Tipe 'angka' agar desimal koma (2,5) valid; hidden value dikirim
                // dengan titik desimal (2.5) dan divalidasi numeric|between:0,100.
                'name' => 'shuPersen',
                'label' => 'Persentase (%)',
                'placeholder' => 'mis. 2,5',
                'type' => 'angka',
                'col' => 'col-md-6',
                'required' => true,
                'rules' => ['between:0,100'],
            ],
            [
                'name' => 'shuIsActive',
                'label' => 'Status',
                'placeholder' => '-- Pilih status --',
                'type' => 'select',
                'col' => 'col-md-6',
                'required' => true,
                'options' => [
                    ['value' => '1', 'label' => 'Aktif'],
                    ['value' => '0', 'label' => 'Nonaktif'],
                ],
                'rules' => ['in:0,1'],
            ],
            [
                'name' => 'shuObjekKode',
                'label' => 'Objek COA',
                'placeholder' => '-- Cari dan pilih objek --',
                'type' => 'autocomplete',
                'col' => 'col-md-12',
                'required' => false,
                'autocomplete' => [
                    'url' => route('api.objek.search'),
                    'textField' => 'text',
                    'valueField' => 'id',
                ],
                'rules' => ['string', 'max:20'],
                'exists' => 'ms_objek,msoKode',
            ],
        ];

        $this->grid = [
            [
                'label' => 'Urutan',
                'field' => 'shuUrut',
                'type' => 'text',
                'class' => 'text-center'
            ],
            [
                'label' => 'Kode',
                'field' => 'shuKode',
                'type' => 'text'
            ],
            [
                'label' => 'Kategori',
                'field' => 'shuNama',
                'type' => 'text',
                'class' => 'text-left'
            ],
            [
                'label' => 'Persentase (%)',
                'field' => 'shuPersen',
                'type' => 'angka',
                'class' => 'text-right'
            ],
            [
                'label' => 'Objek COA',
                'field' => 'shuObjekKode',
                'type' => 'text',
                'class' => 'text-left'
            ],
            [
                'label' => 'Nama COA',
                'field' => 'shuObjekNama',
                'type' => 'text',
                'class' => 'text-left'
            ],
            [
                'label' => 'Status',
                'field' => 'statusLabel',
                'type' => 'badge'
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

        $items = $query->orderBy('shuUrut', 'asc')
            ->orderBy($this->primaryKey, 'asc')
            ->paginate(50)
            ->withQueryString();

        // Nama objek COA untuk kolom grid dan tampilan edit (referensi by kode).
        $kodes = $items->getCollection()->pluck('shuObjekKode')->filter()->unique()->values();
        $namaObjek = MasterObjek::whereIn('msoKode', $kodes)->pluck('msoNama', 'msoKode');

        $items->getCollection()->transform(function ($item) use ($namaObjek) {
            $item->shuObjekNama = $item->shuObjekKode
                ? ($namaObjek->get($item->shuObjekKode) ?? '-')
                : '-';
            $item->statusLabel = ((int) $item->shuIsActive === 1) ? 'Aktif' : 'Nonaktif';
            return $item;
        });

        $editData = null;
        if ($editId) {
            $editData = $this->model::where($this->primaryKey, $editId)->first();
        }

        // Teks terpilih select2 saat edit: "kode - nama" (sama dengan hasil pencarian API).
        $autocompleteSelected = [];
        if ($editData && $editData->shuObjekKode) {
            $nama = $namaObjek->get($editData->shuObjekKode)
                ?? MasterObjek::where('msoKode', $editData->shuObjekKode)->value('msoNama');
            $autocompleteSelected['shuObjekKode'] = $editData->shuObjekKode
                . ($nama ? ' - ' . $nama : '');
        }

        return view('master', [
            'items' => $items,
            'search' => $search,
            'editData' => $editData,
            'form' => $this->form,
            'route' => $this->route,
            'primaryKey' => $this->primaryKey,
            'titlePage' => $this->titlePage,
            'grid' => $this->grid,
            'autocompleteSelected' => $autocompleteSelected,
        ]);
    }

    /**
     * Simpan data baru. Override untuk validasi total persentase baris aktif
     * (tidak boleh melebihi 100%) SEBELUM parent::store, karena pengecualian
     * di hook beforeSave/beforeUpdate ditelan menjadi flash error generik.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validateTotalPersen($request, null);
        return parent::store($request);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->validateTotalPersen($request, $id);
        return parent::update($request, $id);
    }

    /**
     * Validasi total persentase baris aktif (<= 100%).
     * Baris nonaktif / penonaktifan tidak dihitung dan selalu diizinkan.
     * Total < 100% diizinkan — laporan menampilkan warning sampai lengkap
     * (aturan == 100 ketat tidak mungkin dipenuhi pada CRUD satu baris).
     */
    protected function validateTotalPersen(Request $request, ?string $excludeId): void
    {
        if ((int) $request->input('shuIsActive', 1) !== 1) {
            return;
        }

        $totalLain = (float) DB::table('ms_shu')
            ->where('shuIsActive', 1)
            ->when($excludeId !== null, fn ($q) => $q->where('shuId', '!=', $excludeId))
            ->sum('shuPersen');

        $total = round($totalLain + (float) $request->input('shuPersen', 0), 2);

        if ($total > 100.0) {
            throw ValidationException::withMessages([
                'shuPersen' => 'Total persentase baris aktif tidak boleh melebihi 100%. '
                    . 'Total saat ini menjadi ' . number_format($total, 2, ',', '.') . '%.',
            ]);
        }
    }
}
