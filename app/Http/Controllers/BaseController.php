<?php

namespace App\Http\Controllers;

use App\Models\Jurnal\Jurnal;
use App\Models\Jurnal\KwitansiCounter;
use App\Models\Jurnal\Penerimaan;
use App\Models\Jurnal\PenerimaanCounter;
use App\Models\Jurnal\Pengeluaran;
use App\Models\Jurnal\PengeluaranCounter;
use App\Models\Master\Coa\MasterObjek;
use App\Models\Master\LabaRugi;
use App\Models\Master\Mapping\MappingPenerimaan;
use App\Models\Master\Mapping\MappingPengeluaran;
use App\Models\Master\Mapping\MappingSaldoAwal;
use App\Models\Master\Neraca;
use App\Models\Master\PembagianShu;
use App\Models\Master\SaldoAwal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

abstract class BaseController extends Controller
{
    /** @var string Model class name, e.g. \App\Models\Menu::class */
    protected string $model;

    /** @var string Route prefix, e.g. 'menus' */
    protected string $route;

    /** @var string Page title, e.g. 'Daftar Menu' */
    protected string $titlePage;
    /** @var string Validation rules, e.g. 'required|string|max:255' */
    protected array $rules = [];

    /** @var string Primary key column, e.g. 'mId' */
    protected string $primaryKey = 'id';

    /** @var string Table name untuk unique validation */
    protected string $table = '';

    /** @var array Field definitions untuk form */
    protected array $form = [];

    /** @var array Column definitions untuk tabel listing */
    protected array $grid = [];

    /** @var string|array Column name(s) untuk search */
    protected string|array $searchColumn = '';

    /** @var string Model relation untuk eager load (opsional) */
    protected string $withRelation = '';

    /** @var array Extra data untuk dikirim ke view */
    protected array $extraViewData = [];

    /**
     * Redirect create ke index (form tersedia di halaman index).
     */
    public function create(): RedirectResponse
    {
        return redirect()->route($this->route . '.index');
    }

    /**
     * Redirect edit ke index dengan query param inline edit.
     */
    public function edit(string $id): RedirectResponse
    {
        return redirect()->route($this->route . '.index', ['edit' => $id]);
    }

    /**
     * Redirect show ke index.
     */
    public function show(string $id): RedirectResponse
    {
        return redirect()->route($this->route . '.index');
    }

    /**
     * Simpan data baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $modelClass = $this->model;

        $validated = $request->validate(
            $this->buildValidationRules()
        );

        try {
            DB::transaction(function () use ($modelClass, $validated) {
                $data = $validated;

                if (method_exists($this, 'beforeSave')) {
                    $data = $this->beforeSave($validated, null);
                }

                $data[$modelClass::CREATED_BY] = auth()->user()->name;
                $data[$modelClass::CREATED_AT] = now();

                $res = $modelClass::create($data);

                if (method_exists($this, 'afterSave')) {
                    $this->afterSave($res->toArray());
                }
            });

            return redirect()
                ->route($this->route . '.index')
                ->with('success', $this->titlePage . ' berhasil ditambahkan.');

        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', $this->titlePage . ' gagal ditambahkan.');
        }
    }

    /**
     * Update data.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $modelClass = $this->model;

        $record = $modelClass::where($this->primaryKey, $id)->firstOrFail();

        $validated = $request->validate(
            $this->buildValidationRules($id)
        );

        try {
            DB::transaction(function () use ($modelClass, $record, $validated) {
                $data = $validated;

                if (method_exists($this, 'beforeUpdate')) {
                    $data = $this->beforeUpdate($validated, $record);
                }

                $data[$modelClass::UPDATED_BY] = auth()->user()->name;
                $data[$modelClass::UPDATED_AT] = now();

                if (!$record->update($data)) {
                    throw new \RuntimeException('Gagal memperbarui data.');
                }

                if (method_exists($this, 'afterUpdate')) {
                    $this->afterUpdate(
                        $record->fresh()->toArray(),
                        $record->{$this->primaryKey}
                    );
                }
            });

            return redirect()
                ->route($this->route . '.index')
                ->with('success', $this->titlePage . ' berhasil diupdate.');
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', $this->titlePage . ' gagal diupdate.');
        }
    }

    /**
     * Hapus data.
     */
    public function destroy(string $id): RedirectResponse
    {
        $modelClass = $this->model;
        $record = $modelClass::where($this->primaryKey, $id)->firstOrFail();

        try {
            DB::transaction(function () use ($record, $id) {
                if (method_exists($this, 'beforeDelete')) {
                    $this->beforeDelete($id);
                }

                if (!$record->delete()) {
                    throw new \RuntimeException('Gagal menghapus data.');
                }

                if (method_exists($this, 'afterDelete')) {
                    $this->afterDelete($id);
                }
            });

            return redirect()
                ->route($this->route . '.index')
                ->with('success', $this->titlePage . ' berhasil dihapus.');
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->with('error', $this->titlePage . ' gagal dihapus.');
        }
    }

    /**
     * Build validation rules dari $form array.
     */
    protected function buildValidationRules(?string $excludeId = null): array
    {
        $rules = [];

        foreach ($this->form as $field) {
            $fieldRules = [];

            // Required
            if (!empty($field['required'])) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            // Type-specific rules
            switch ($field['type']) {
                case 'email':
                    $fieldRules[] = 'email';
                    $fieldRules[] = 'max:255';
                    break;
                case 'password':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'min:4';
                    break;
                case 'autocomplete':
                    // Default: value berupa string (kode seperti '1-1000').
                    // Field dengan 'rules' sendiri boleh menimpa aturan ini.
                    if (empty($field['rules'])) {
                        $fieldRules[] = 'string';
                        $fieldRules[] = 'max:225';
                    }
                    if (!empty($field['exists'])) {
                        $fieldRules[] = 'exists:' . $field['exists'];
                    }
                    break;
                case 'angka':
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                default:
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:225';
                    break;
            }

            // Aturan tambahan/override spesifik field (mis. 'integer' untuk
            // autocomplete yang valuenya memang id numerik, atau
            // 'string|max:20' untuk kode yang lebih pendek dari default)
            if (!empty($field['rules'])) {
                $extra = is_array($field['rules']) ? $field['rules'] : explode('|', $field['rules']);
                $fieldRules = array_merge($fieldRules, $extra);
            }

            // Unique rule
            if (!empty($field['unique'])) {
                $uniqueRule = 'unique:' . $field['unique'];
                if ($excludeId !== null) {
                    $uniqueRule .= ',' . $excludeId . ',' . $this->primaryKey;
                }
                $fieldRules[] = $uniqueRule;
            }

            $rules[$field['name']] = $fieldRules;

            // Field pasangan (nameValue) pada tipe autocomplete_name ikut
            // divalidasi agar nilainya (mis. nama) ikut tersimpan ke database.
            if (!empty($field['nameValue']) && !isset($rules[$field['nameValue']])) {
                $rules[$field['nameValue']] = ['nullable', 'string', 'max:225'];
            }
        }

        // Merge rules tambahan dari child class ($this->rules).
        // Aturan unique di sini juga mengecualikan id record yang sedang diedit.
        foreach ($this->rules as $fieldName => $fieldRules) {
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            if ($excludeId !== null) {
                $fieldRules = array_map(function ($rule) use ($excludeId) {
                    if (is_string($rule) && str_starts_with($rule, 'unique:')) {
                        // Format 'unique:table,column' → tambahkan id yang dikecualikan
                        $segments = explode(',', substr($rule, strlen('unique:')));
                        if (count($segments) === 2) {
                            $rule .= ',' . $excludeId . ',' . $this->primaryKey;
                        }
                    }
                    return $rule;
                }, $fieldRules);
            }

            $rules[$fieldName] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Hook: transform data sebelum insert. Override di child class jika perlu.
     */
    protected function beforeSave(array $data, $record = null): array
    {
        return $data;
    }

    /**
     * Hook: transform data sebelum update. Override di child class jika perlu.
     */
    protected function beforeUpdate(array $data, $record): array
    {
        return $data;
    }

    //  ======================== HELPER TRANSACTIONS ========================

    public function getKeluarCounter($date = null): int
    {
        $year = $date ? Carbon::parse($date)->year : now()->year;

        return DB::transaction(function () use ($year) {
            // Belum ada: insert.
            // Sudah ada: abaikan insert karena kTahun UNIQUE.
            PengeluaranCounter::query()->insertOrIgnore([
                'kTahun' => $year,
                'kCounter' => 0,
            ]);

            // Proses lain yang ingin mengunci baris ini harus menunggu.
            $counter = PengeluaranCounter::where('kTahun', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $counter->kCounter = (int) $counter->kCounter + 1;

            if (!$counter->save()) {
                throw new \RuntimeException('Gagal menyimpan counter.');
            }

            return (int) $counter->kCounter;
        }, 5);
    }

    public function getTerimaCounter($date = null): int
    {
        $year = $date ? Carbon::parse($date)->year : now()->year;

        return DB::transaction(function () use ($year) {
            // Belum ada: insert.
            // Sudah ada: abaikan insert karena tTahun UNIQUE.
            PenerimaanCounter::query()->insertOrIgnore([
                'tTahun' => $year,
                'tCounter' => 0,
            ]);

            // Proses lain yang ingin mengunci baris ini harus menunggu.
            $counter = PenerimaanCounter::where('tTahun', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $counter->tCounter = (int) $counter->tCounter + 1;

            if (!$counter->save()) {
                throw new \RuntimeException('Gagal menyimpan counter.');
            }

            return (int) $counter->tCounter;
        }, 5);
    }

    public function getKwitansiCounter($date = null): int
    {
        $tanggal = $date !== null ? Carbon::parse($date) : now();

        $year = $tanggal->year;
        $bulan = $tanggal->month;

        return DB::transaction(function () use ($year, $bulan) {
            // Buat baris jika kombinasi tahun dan bulan belum tersedia.
            KwitansiCounter::query()->insertOrIgnore([
                'kTahun' => $year,
                'kBulan' => $bulan,
                'kCounter' => 0,
            ]);

            // Kunci counter untuk tahun dan bulan yang dipilih.
            $counter = KwitansiCounter::where('kTahun', $year)
                ->where('kBulan', $bulan)
                ->lockForUpdate()
                ->firstOrFail();

            $counter->kCounter = (int) $counter->kCounter + 1;

            if (!$counter->save()) {
                throw new \RuntimeException('Gagal menyimpan counter.');
            }

            return (int) $counter->kCounter;
        }, 5);
    }

    public function getMapping($jenisMapping, $sumber)
    {
        if ($jenisMapping === 'PENERIMAAN') {
            $mapping = MappingPenerimaan::where('mapKodeAsal', $sumber)->join('ms_objek', 'msoKode', '=', 'mapKodeDebet')->get();
        } elseif ($jenisMapping === 'PENGELUARAN') {
            $mapping = MappingPengeluaran::where('mapKodeAsal', $sumber)->join('ms_objek', 'msoKode', '=', 'mapKodeKredit')->get();
        } elseif ($jenisMapping === 'SALDOAWAL') {
            $mapping = MappingSaldoAwal::where('mapKodeAsal', $sumber)->join('ms_objek', 'msoKode', '=', 'mapKodeKredit')->get();
        } else {
            throw new \InvalidArgumentException("Jenis mapping tidak valid: {$jenisMapping}");
        }

        if (!$mapping) {
            throw new \RuntimeException("Mapping tidak ditemukan untuk sumber: {$sumber}, jenis mapping: {$jenisMapping}");
        }

        return $mapping;
    }

    public function createJurnal($jenisMapping, $sumber, $data, $jns = null): void
    {
        $mapping = $this->getMapping($jenisMapping, $sumber);

        if ($jenisMapping === 'PENERIMAAN') {
            Jurnal::where('jHeadId', $data['pId'] ?? null)
                ->where('jSumber', $jns)
                ->delete();

            foreach ($mapping as $map) {
                $jurnalData = [
                    'jHeadId' => $data['tId'] ?? null,
                    'jNo' => $data['tNoPenerimaan'] ?? null,
                    'jTgl' => $data['tTglBayar'] ?? null,
                    'jKeterangan' => $data['tDeskripsi'] ?? null,
                    'jRekDebetKode' => $map['mapKodeDebet'] ?? null,
                    'jRekDebetNama' => $map['mapNamaDebet'] ?? null,
                    'jDebetNilai' => $data['tNilaiBayar'] ?? 0,
                    'jRekKreditKode' => $map['mapKodeKredit'] ?? null,
                    'jRekKreditNama' => $map['mapNamaKredit'] ?? null,
                    'jKreditNilai' => $data['tNilaiBayar'] ?? 0,
                    'jSumber' => $jns,
                    'jStatus' => 0,
                    'created_at' => now(),
                ];
                Jurnal::insert($jurnalData);
            }
        } else if ($jenisMapping === 'PENGELUARAN') {
            Jurnal::where('jHeadId', $data['kId'] ?? null)
                ->where('jSumber', $jns)
                ->delete();

            foreach ($mapping as $map) {
                $jurnalData = [
                    'jHeadId' => $data['kId'] ?? null,
                    'jNo' => $data['kNo'] ?? null,
                    'jTgl' => $data['kTgl'] ?? null,
                    'jKeterangan' => $data['kKeterangan'] ?? null,
                    'jRekDebetKode' => $map['mapKodeDebet'] ?? null,
                    'jRekDebetNama' => $map['mapNamaDebet'] ?? null,
                    'jDebetNilai' => $data['kNilai'] ?? 0,
                    'jRekKreditKode' => $map['mapKodeKredit'] ?? null,
                    'jRekKreditNama' => $map['mapNamaKredit'] ?? null,
                    'jKreditNilai' => $data['kNilai'] ?? 0,
                    'jSumber' => $jns,
                    'jStatus' => 0,
                    'created_at' => now(),
                ];
                Jurnal::insert($jurnalData);
            }
        } else if ($jenisMapping === 'SALDOAWAL') {
            Jurnal::where('jHeadId', $data['tsId'] ?? null)
                ->where('jSumber', $jns)
                ->delete();

            foreach ($mapping as $map) {
                $jurnalData = [
                    'jHeadId' => $data['tsId'] ?? null,
                    'jNo' => $data['tsNoTrans'] ?? null,
                    'jTgl' => $data['tsTanggal'] ?? null,
                    'jKeterangan' => 'Saldo Awal ' . ($data['tsNamaBank'] ?? null),
                    'jRekDebetKode' => $map['mapKodeDebet'] ?? null,
                    'jRekDebetNama' => $map['mapNamaDebet'] ?? null,
                    'jDebetNilai' => $data['tsSaldoAwal'] ?? 0,
                    'jRekKreditKode' => $map['mapKodeKredit'] ?? null,
                    'jRekKreditNama' => $map['mapNamaKredit'] ?? null,
                    'jKreditNilai' => $data['tsSaldoAwal'] ?? 0,
                    'jSumber' => $jns,
                    'jStatus' => 0,
                    'created_at' => now(),
                ];
                Jurnal::insert($jurnalData);
            }
        } else {
            throw new \InvalidArgumentException("Jenis mapping tidak valid: {$jenisMapping}");
        }
    }

    /** Cari record kas keluar berdasarkan sumber transaksi. */
    public function getPengeluaranBySumber($sumber, $sumberId)
    {
        return Pengeluaran::where('kSumber', $sumber)
            ->where('kSumberId', $sumberId)
            ->first();
    }

    /** Cari record kas masuk berdasarkan sumber transaksi. */
    public function getPenerimaanBySumber($sumber, $sumberId)
    {
        return Penerimaan::where('tSumber', $sumber)
            ->where('tSumberId', $sumberId)
            ->first();
    }

    /** Cari record saldo awal berdasarkan sumber transaksi. */
    public function getSaldoawalByKode($sumber, $kodeBankKas)
    {
        return SaldoAwal::where('tsKodeBankKas', $kodeBankKas)
            ->first();
    }

    /** Hapus semua baris jurnal milik satu header kas. */
    public function hapusJurnal($jenisMapping, $headId): void
    {
        Jurnal::where('jHeadId', $headId)
            ->where('jSumber', $jenisMapping)
            ->delete();
    }

    /**
     * Hapus kas keluar beserta jurnalnya berdasarkan sumber transaksi.
     * Counter tidak dikembalikan agar nomor dokumen tidak pernah dipakai ulang.
     */
    public function hapusPengeluaranBySumber($sumber, $sumberId): void
    {
        $detail = $this->getPengeluaranBySumber($sumber, $sumberId);

        if ($detail) {
            $this->hapusJurnal($sumber, $detail->kId);
            $detail->delete();
        }
    }

    /**
     * Hapus kas masuk beserta jurnalnya berdasarkan sumber transaksi.
     * Counter tidak dikembalikan agar nomor dokumen tidak pernah dipakai ulang.
     */
    public function hapusPenerimaanBySumber($sumber, $sumberId): void
    {
        $detail = $this->getPenerimaanBySumber($sumber, $sumberId);

        if ($detail) {
            $this->hapusJurnal($sumber, $detail->tId);
            $detail->delete();
        }
    }

    /**
     * Hapus kas saldo awal beserta jurnalnya berdasarkan sumber transaksi.
     * Counter tidak dikembalikan agar nomor dokumen tidak pernah dipakai ulang.
     */
    public function hapusSaldoawalByKode($sumber, $kodeBankKas): void
    {
        $detail = $this->getSaldoawalByKode($sumber, $kodeBankKas);

        if ($detail) {
            $this->hapusJurnal($sumber, $detail->tsId);
            $detail->delete();
        }
    }

    public function getSaldoCoa(string $kodeCoa, string $saldoNormal = 'D'): string
    {
        $saldoNormal = strtoupper($saldoNormal);

        if (!in_array($saldoNormal, ['D', 'K'], true)) {
            throw new \InvalidArgumentException('Saldo normal harus D atau K.');
        }

        $hasil = Jurnal::query()
            ->where(function ($query) use ($kodeCoa) {
                $query->where('jRekDebetKode', $kodeCoa)
                    ->orWhere('jRekKreditKode', $kodeCoa);
            })
            ->selectRaw('
            COALESCE(SUM(
                CASE WHEN jRekDebetKode = ?
                    THEN COALESCE(jDebetNilai, 0)
                    ELSE 0
                END
            ), 0) AS total_debet,
            COALESCE(SUM(
                CASE WHEN jRekKreditKode = ?
                    THEN COALESCE(jKreditNilai, 0)
                    ELSE 0
                END
            ), 0) AS total_kredit
        ', [$kodeCoa, $kodeCoa]);

        // Pengurangan di database menjaga presisi nilai DECIMAL.
        $rumus = $saldoNormal === 'D' ? 'total_debet - total_kredit' : 'total_kredit - total_debet';

        $saldo = DB::query()
            ->fromSub($hasil, 'mutasi')
            ->selectRaw("$rumus AS saldo")
            ->value('saldo');

        return (string) $saldo;
    }

    // ======================== HELPER NERACA ========================

    /**
     * Akumulasi mutasi SELURUH jurnal s.d. $tglAkhir, dikelompokkan per kode objek.
     * Satu query, dipakai bersama oleh getSaldoNeraca() dan tampilan master.
     *
     * @return array{debet: array<string, float>, kredit: array<string, float>}
     */
    public function getMutasiCoa(?string $tglAkhir = null): array
    {
        return $this->getMutasiCoaBetween(null, $tglAkhir);
    }

    /**
     * Mutasi jurnal dalam RENTANG tanggal, dikelompokkan per kode objek.
     * Dasar laporan laba rugi (saldo periode, bukan akumulasi s.d. akhir).
     *
     * @return array{debet: array<string, float>, kredit: array<string, float>}
     */
    public function getMutasiCoaBetween(?string $tglAwal = null, ?string $tglAkhir = null): array
    {
        $query = Jurnal::query();
        if ($tglAwal) {
            $query->where('jTgl', '>=', $tglAwal);
        }
        if ($tglAkhir) {
            $query->where('jTgl', '<=', $tglAkhir);
        }

        $rows = $query->get(['jRekDebetKode', 'jDebetNilai', 'jRekKreditKode', 'jKreditNilai']);

        $debet = [];
        $kredit = [];
        foreach ($rows as $row) {
            $kd = trim((string) $row->jRekDebetKode);
            $kk = trim((string) $row->jRekKreditKode);
            if ($kd !== '') {
                $debet[$kd] = round(($debet[$kd] ?? 0) + (float) $row->jDebetNilai, 2);
            }
            if ($kk !== '') {
                $kredit[$kk] = round(($kredit[$kk] ?? 0) + (float) $row->jKreditNilai, 2);
            }
        }

        return ['debet' => $debet, 'kredit' => $kredit];
    }

    /**
     * Saldo per baris konfigurasi ms_neraca.
     * nrKodeAkun = prefix kode objek (ms_objek.msoKode), dipisah koma bila lebih dari satu.
     * Pencocokan per segmen: '1.01' cocok dengan '1.01.01.01', bukan '1.010'.
     * CATATAN: prefix dalam satu baris tidak boleh saling menutupi (mis. '1' dan '1.01'),
     * karena pencocokan berhenti pada prefix pertama yang cocok (hindari hitung ganda).
     *
     * @param  iterable $items     baris ms_neraca (model/array: nrId, nrKodeAkun, nrSaldoNormal)
     * @param  string|null $tglAkhir 'Y-m-d'; null = seluruh jurnal
     * @return array<int, float>   [nrId => saldo]
     */
    public function getSaldoNeraca(iterable $items, ?string $tglAkhir = null): array
    {
        return $this->hitungSaldoPrefix($items, $this->getMutasiCoa($tglAkhir));
    }

    /**
     * Saldo per baris konfigurasi terhadap mutasi yang sudah dihitung.
     * Kode akun = prefix kode objek (ms_objek.msoKode), dipisah koma bila lebih dari satu.
     * Pencocokan per segmen: '1.01' cocok dengan '1.01.01.01', bukan '1.010'.
     * CATATAN: prefix dalam satu baris tidak boleh saling menutupi (mis. '1' dan '1.01'),
     * karena pencocokan berhenti pada prefix pertama yang cocok (hindari hitung ganda).
     *
     * @param  iterable $items      baris konfigurasi (model/array dengan kolom id/kode akun/saldo normal)
     * @param  array    $mutasi     hasil getMutasiCoa/getMutasiCoaBetween
     * @param  string   $idKey      nama kolom primary key (nrId / lrId)
     * @param  string   $kodeKey    nama kolom kode akun (nrKodeAkun / lrKodeAkun)
     * @param  string   $normalKey  nama kolom saldo normal (nrSaldoNormal / lrSaldoNormal)
     * @return array<int, float>    [id => saldo]
     */
    protected function hitungSaldoPrefix(iterable $items, array $mutasi, string $idKey = 'nrId', string $kodeKey = 'nrKodeAkun', string $normalKey = 'nrSaldoNormal'): array
    {
        // Bentuk pipih [kode => [d, k]] → cukup satu loop per prefix.
        $pipih = [];
        foreach ($mutasi['debet'] as $kode => $nilai) {
            $pipih[$kode]['d'] = $nilai;
        }
        foreach ($mutasi['kredit'] as $kode => $nilai) {
            $pipih[$kode]['k'] = $nilai;
        }

        $hasil = [];
        foreach ($items as $item) {
            $id = is_array($item) ? ($item[$idKey] ?? null) : $item->{$idKey};
            $kodeAkun = (string) (is_array($item) ? ($item[$kodeKey] ?? '') : $item->{$kodeKey});
            $saldoNormal = strtoupper((string) (is_array($item) ? ($item[$normalKey] ?? 'D') : $item->{$normalKey}));

            $prefixes = array_values(array_filter(
                array_map('trim', explode(',', $kodeAkun)),
                fn($p) => $p !== ''
            ));

            $debet = 0.0;
            $kredit = 0.0;
            if ($prefixes) {
                foreach ($pipih as $kode => $n) {
                    foreach ($prefixes as $prefix) {
                        if ($kode === $prefix || str_starts_with($kode, $prefix . '.')) {
                            $debet += $n['d'] ?? 0;
                            $kredit += $n['k'] ?? 0;
                            break;
                        }
                    }
                }
            }

            $hasil[$id] = round($saldoNormal === 'K' ? $kredit - $debet : $debet - $kredit, 2);
        }

        return $hasil;
    }

    // ======================== HELPER LABA RUGI ========================

    /**
     * Saldo periode per baris konfigurasi ms_labarugi.
     * Berbeda dengan neraca (akumulasi s.d. akhir), laba rugi memakai
     * mutasi dalam rentang jTgl >= $tglAwal s.d. jTgl <= $tglAkhir.
     *
     * @param  iterable $items      baris ms_labarugi (model/array: lrId, lrKodeAkun, lrSaldoNormal)
     * @param  string|null $tglAwal 'Y-m-d'; null = sejak jurnal pertama
     * @param  string|null $tglAkhir 'Y-m-d'; null = s.d. jurnal terakhir
     * @return array<int, float>   [lrId => saldo]
     */
    public function getSaldoLabaRugi(iterable $items, ?string $tglAwal = null, ?string $tglAkhir = null): array
    {
        return $this->hitungSaldoPrefix(
            $items,
            $this->getMutasiCoaBetween($tglAwal, $tglAkhir),
            'lrId',
            'lrKodeAkun',
            'lrSaldoNormal'
        );
    }

    /**
     * Susun data laba rugi dari konfigurasi ms_labarugi (urut lrUrut).
     * Baris rincian objek (lrJenis 'objek') didefinisikan manual di
     * Master Item Laba Rugi dengan lrKodeAkun = kode objek (mis. 5.02.03.10);
     * baris tanpa konfigurasi tidak tampil (saldonya hanya ikut di total).
     * Total pendapatan/biaya ditentukan oleh baris lrKode 'T-PENDAPATAN' / 'T-BIAYA'
     * (fallback: baris total terakhir per posisi).
     * Laba bersih = total pendapatan - total biaya.
     */
    public function buildLabaRugi(string $tglAwal, string $tglAkhir): array
    {
        $items = LabaRugi::orderBy('lrUrut', 'asc')->orderBy('lrId', 'asc')->get();
        $saldo = $this->getSaldoLabaRugi($items, $tglAwal, $tglAkhir);

        $rows = [];
        $tPendapatan = null;
        $tBiaya = null;
        $lastTotal = ['PENDAPATAN' => null, 'BIAYA' => null];

        foreach ($items as $item) {
            $nilai = (float) ($saldo[$item->lrId] ?? 0);
            $prefix = trim((string) $item->lrKodeAkun);

            $rows[] = [
                'lrId' => $item->lrId,
                'type' => $item->lrJenis,
                'posisi' => $item->lrPosisi,
                // Kode ditampilkan hanya bila prefix tunggal berupa kode bertitik ('5.02.01'); '4,5' → kosong.
                'kodeTampil' => ($prefix !== '' && !str_contains($prefix, ',') && preg_match('/^[0-9.]+$/', $prefix)) ? $prefix : '',
                'nama' => $item->lrNama,
                'indent' => match ($item->lrJenis) {
                    'item' => 1,
                    'objek' => 2,
                    default => 0,
                },
                'saldo' => $nilai,
            ];

            if ($item->lrJenis === 'total') {
                $lastTotal[$item->lrPosisi] = $nilai;
            }
            if ($item->lrKode === 'T-PENDAPATAN') {
                $tPendapatan = $nilai;
            }
            if ($item->lrKode === 'T-BIAYA') {
                $tBiaya = $nilai;
            }
        }

        if ($tPendapatan === null) {
            $tPendapatan = $lastTotal['PENDAPATAN'] ?? 0;
        }
        if ($tBiaya === null) {
            $tBiaya = $lastTotal['BIAYA'] ?? 0;
        }

        $labaBersih = round($tPendapatan - $tBiaya, 2);

        return [
            'rows' => $rows,
            'tPendapatan' => $tPendapatan,
            'tBiaya' => $tBiaya,
            'labaBersih' => $labaBersih,
            'laba' => $labaBersih >= 0,
            'tglAwal' => $tglAwal,
            'tglAkhir' => $tglAkhir,
        ];
    }
    // ======================== HELPER LABA RUGI END ========================

    /**
     * Susun data neraca dari konfigurasi ms_neraca (urut nrUrut).
     * Saldo dihitung dari mutasi jurnal DALAM RENTANG jTgl >= $tglAwal
     * s.d. jTgl <= $tglAkhir (sama dengan laba rugi) — jurnal di luar
     * rentang, termasuk saldo awal sebelum $tglAwal, tidak ikut.
     * Baris rincian objek (nrJenis 'objek') didefinisikan manual di
     * Master Item Neraca dengan nrKodeAkun = kode objek (mis. 1.01.01.01);
     * baris tanpa konfigurasi tidak tampil (saldonya hanya ikut di total).
     * Total ditentukan oleh baris nrKode 'T-AKTIVA' / 'T-PASIVA'
     * (fallback: baris total terakhir per posisi).
     */
    public function buildNeraca(string $tglAwal, string $tglAkhir): array
    {
        $items = Neraca::orderBy('nrUrut', 'asc')->orderBy('nrId', 'asc')->get();
        $mutasi = $this->getMutasiCoaBetween($tglAwal, $tglAkhir);
        $saldo = $this->hitungSaldoPrefix($items, $mutasi);

        $rows = [];
        $tAktiva = null;
        $tPasiva = null;
        $lastTotal = ['AKTIVA' => null, 'PASIVA' => null];

        foreach ($items as $item) {
            $nilai = (float) ($saldo[$item->nrId] ?? 0);
            $prefix = trim((string) $item->nrKodeAkun);

            $rows[] = [
                'nrId' => $item->nrId,
                'type' => $item->nrJenis,
                'posisi' => $item->nrPosisi,
                // Kode ditampilkan hanya bila prefix tunggal berupa kode bertitik ('1.01.01'); '4,5' → kosong.
                'kodeTampil' => ($prefix !== '' && !str_contains($prefix, ',') && preg_match('/^[0-9.]+$/', $prefix)) ? $prefix : '',
                'nama' => $item->nrNama,
                'indent' => match ($item->nrJenis) {
                    'item' => 1,
                    'objek' => 2,
                    default => 0,
                },
                'saldo' => $nilai,
            ];

            if ($item->nrJenis === 'total') {
                $lastTotal[$item->nrPosisi] = $nilai;
            }
            if ($item->nrKode === 'T-AKTIVA') {
                $tAktiva = $nilai;
            }
            if ($item->nrKode === 'T-PASIVA') {
                $tPasiva = $nilai;
            }
        }

        if ($tAktiva === null) {
            $tAktiva = $lastTotal['AKTIVA'] ?? 0;
        }
        if ($tPasiva === null) {
            $tPasiva = $lastTotal['PASIVA'] ?? 0;
        }

        $selisih = round($tAktiva - $tPasiva, 2);

        return [
            'rows' => $rows,
            'tAktiva' => $tAktiva,
            'tPasiva' => $tPasiva,
            'selisih' => $selisih,
            'seimbang' => abs($selisih) < 0.01,
            'tglAwal' => $tglAwal,
            'tglAkhir' => $tglAkhir,
        ];
    }
    // ======================== HELPER NERACA END =======================

    //  ======================== HELPER PEMBAGIAN SHU ========================

    /**
     * Susun rincian pembagian SHU: basis nilai = laba (rugi) bersih periode
     * dari buildLabaRugi(); persentase dan pemetaan COA dari baris aktif ms_shu
     * (urut shuUrut). Nama objek COA diresolve dari ms_objek by kode.
     * Total nilai = jumlah nilai baris yang sudah dibulatkan 2 desimal,
     * sehingga total yang tampil selalu sama dengan jumlah barisnya.
     *
     * @return array{
     *   rows: array<int, array{no:int, shuId:int, shuKode:string, kategori:string,
     *                          persen:float, objekKode:?string, objekNama:?string, nilai:float}>,
     *   totalPersen: float, totalNilai: float, shu: float,
     *   laba: bool, persenOk: bool, tglAwal: string, tglAkhir: string
     * }
     */
    public function buildPembagianShu(string $tglAwal, string $tglAkhir): array
    {
        $labarugi = $this->buildLabaRugi($tglAwal, $tglAkhir);
        $shu = (float) $labarugi['labaBersih'];

        $items = PembagianShu::query()
            ->where('shuIsActive', 1)
            ->orderBy('shuUrut', 'asc')
            ->orderBy('shuId', 'asc')
            ->get();

        // Satu query untuk nama objek COA (referensi by kode, tanpa FK).
        $kodes = $items->pluck('shuObjekKode')->filter()->unique()->values()->all();
        $namaObjek = MasterObjek::query()
            ->whereIn('msoKode', $kodes)
            ->pluck('msoNama', 'msoKode')
            ->all();

        $rows = [];
        $totalPersen = 0.0;
        $totalNilai = 0.0;

        foreach ($items as $index => $item) {
            $persen = (float) $item->shuPersen;
            $nilai = round($shu * $persen / 100, 2);
            $totalPersen += $persen;
            $totalNilai += $nilai;

            $rows[] = [
                'no' => $index + 1,
                'shuId' => $item->shuId,
                'shuKode' => $item->shuKode,
                'kategori' => $item->shuNama,
                'persen' => $persen,
                'objekKode' => $item->shuObjekKode,
                'objekNama' => $item->shuObjekKode ? ($namaObjek[$item->shuObjekKode] ?? null) : null,
                'nilai' => $nilai,
            ];
        }

        $totalPersen = round($totalPersen, 2);
        $totalNilai = round($totalNilai, 2);

        return [
            'rows' => $rows,
            'totalPersen' => $totalPersen,
            'totalNilai' => $totalNilai,
            'shu' => $shu,
            'laba' => $labarugi['laba'],
            'persenOk' => abs($totalPersen - 100) < 0.001,
            'tglAwal' => $tglAwal,
            'tglAkhir' => $tglAkhir,
        ];
    }

    // ======================== HELPER PEMBAGIAN SHU END ========================

    //  ======================== HELPER FUNCTIONS ========================

    /**
     * Validasi filter tanggal laporan: format Y-m-d dan tglAwal <= tglAkhir.
     * Nilai kosong diisi default awal/akhir bulan berjalan.
     *
     * @throws \InvalidArgumentException bila format/urutan tanggal tidak valid.
     * @return array{0: string, 1: string} [tglAwal, tglAkhir]
     */
    protected function validasiTanggalLaporan(?string $tglAwal, ?string $tglAkhir): array
    {
        $tglAwal = $tglAwal ?: date('Y-m-01');
        $tglAkhir = $tglAkhir ?: date('Y-m-t');

        foreach (['Tanggal Awal' => $tglAwal, 'Tanggal Akhir' => $tglAkhir] as $label => $tgl) {
            $d = \DateTime::createFromFormat('Y-m-d', $tgl);
            if (!$d || $d->format('Y-m-d') !== $tgl) {
                throw new \InvalidArgumentException("$label tidak valid (gunakan format YYYY-MM-DD).");
            }
        }
        if ($tglAwal > $tglAkhir) {
            throw new \InvalidArgumentException('Tanggal Awal tidak boleh melebihi Tanggal Akhir.');
        }

        return [$tglAwal, $tglAkhir];
    }

    public function formatDate($date): string
    {
        $tanggal = '';
        if (empty($date)) {
            $tanggal = '';
        } else {
            if (substr($date, 2, 1) == '/') {
                $a = explode('/', $date);
            } else {
                $a = explode('-', $date);
                $d = $a[2];
                $m = $a[1];
                $y = $a[0];
                $a[0] = $m + 0;
                $a[1] = $d;
                $a[2] = $y;
            }
            $tanggal = $a[2] . '-' . $a[1] . '-' . $a[0];
        }
        return $tanggal;
    }

    function dateID($tgl)
    {

        if (substr($tgl, 2, 1) == '/') {
            $a = explode('/', $tgl);
        } else {
            $a = explode('-', $tgl);
            $d = $a[2];
            $m = $a[1];
            $y = $a[0];
            $a[0] = $m + 0;
            $a[1] = $d;
            $a[2] = $y;
        }

        $nmbulan = '';

        switch ($a[0]) {
            case 1:
                $nmbulan = 'Januari';
                break;
            case 2:
                $nmbulan = 'Februari';
                break;
            case 3:
                $nmbulan = 'Maret';
                break;
            case 4:
                $nmbulan = 'April';
                break;
            case 5:
                $nmbulan = 'Mei';
                break;
            case 6:
                $nmbulan = 'Juni';
                break;
            case 7:
                $nmbulan = 'Juli';
                break;
            case 8:
                $nmbulan = 'Agustus';
                break;
            case 9:
                $nmbulan = 'September';
                break;
            case 10:
                $nmbulan = 'Oktober';
                break;
            case 11:
                $nmbulan = 'November';
                break;
            case 12:
                $nmbulan = 'Desember';
                break;
        }

        return $a[1] . ' ' . $nmbulan . ' ' . $a['2'];
    }

    function dateIDkosong($tgl)
    {
        if ($tgl != 0) {
            if (substr($tgl, 2, 1) == '/') {
                $a = explode('/', $tgl);
            } else {
                $a = explode('-', $tgl);
                $d = $a[2];
                $m = $a[1];
                $y = $a[0];
                $a[0] = $m + 0;
                $a[1] = $d;
                $a[2] = $y;
            }

            $nmbulan = '';

            switch ($a[0]) {
                case 1:
                    $nmbulan = 'Januari';
                    break;
                case 2:
                    $nmbulan = 'Februari';
                    break;
                case 3:
                    $nmbulan = 'Maret';
                    break;
                case 4:
                    $nmbulan = 'April';
                    break;
                case 5:
                    $nmbulan = 'Mei';
                    break;
                case 6:
                    $nmbulan = 'Juni';
                    break;
                case 7:
                    $nmbulan = 'Juli';
                    break;
                case 8:
                    $nmbulan = 'Agustus';
                    break;
                case 9:
                    $nmbulan = 'September';
                    break;
                case 10:
                    $nmbulan = 'Oktober';
                    break;
                case 11:
                    $nmbulan = 'November';
                    break;
                case 12:
                    $nmbulan = 'Desember';
                    break;
            }

            $res = $a[1] . ' ' . $nmbulan . ' ' . $a['2'];
        } else {
            $res = '';
        }
        return $res;
    }

    function formatRupiah($angka, $digit = 2)
    {
        if (empty($angka)) {
            $angka = 0;
        } else {
            $angka = str_replace(',', '', $angka);
        }
        $hasil_rupiah = number_format($angka, $digit, ',', '.');
        return $hasil_rupiah;
    }

    /**
     * Format saldo rupiah; nilai negatif diberi tanda kurung,
     * mis. -1.000.000,00 menjadi (1.000.000,00).
     */
    function formatSaldo($angka, $digit = 2)
    {
        $angka = (float) $angka;

        if ($angka < 0) {
            return '(' . $this->formatRupiah(abs($angka), $digit) . ')';
        }

        return $this->formatRupiah($angka, $digit);
    }
}
