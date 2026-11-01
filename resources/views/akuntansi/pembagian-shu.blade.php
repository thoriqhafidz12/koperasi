@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0">{{ $titlePage }}</h3>
    </div>

    {{-- Filter Form --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-2"></i>Filter Laporan</h6>
        </div>
        <div class="card-body">
            <form id="formFilter">
                <div class="row align-items-end">
                    {{-- Field filter dirender dari $this->form di controller
                         (mendukung text, date, select, autocomplete, angka, dll.) --}}
                    @include('akuntansi.components.filter-fields', ['form' => $form])

                    <div class="col-md-12 text-center mt-3">
                        <button type="submit" id="btnTampilkan" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <button type="button" id="btnCetak" class="btn btn-danger">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button type="button" id="btnExcel" class="btn btn-success">
                            <i class="fas fa-file-excel"></i>
                        </button>
                        <button type="button" id="btnReset" class="btn btn-secondary ml-2">
                            <i class="fas fa-sync-alt"></i> Reset
                        </button>
                    </div>
                    <div class="col-md-12 mt-4 text-center">
                        <span class="ml-3 text-muted small" id="infoRange"></span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-table mr-2"></i>Hasil Laporan</h6>
        </div>
        <div class="card-body">
            {{-- Ringkasan SHU & warning --}}
            <div id="shuSummary" class="alert alert-success d-flex justify-content-between align-items-center py-2" style="display:none !important;">
                <span><strong id="shuSummaryLabel">SHU (LABA BERSIH) PERIODE</strong></span>
                <span class="font-weight-bold" id="shuValue">-</span>
            </div>
            <div id="shuWarning" class="alert alert-warning py-2" style="display:none;"></div>

            {{-- Loading --}}
            <div id="loadingIndicator" class="text-center py-5" style="display:none;">
                <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                <p class="mt-2 text-muted">Memuat data laporan...</p>
            </div>

            {{-- Empty state --}}
            <div id="emptyState" class="text-center py-4">
                <i class="fas fa-search fa-3x text-muted mb-2"></i>
                <p class="text-muted">Silakan atur filter lalu klik <strong>Tampilkan</strong>.</p>
            </div>

            {{-- Table --}}
            <div id="tableWrapper" class="table-responsive" style="display:none;">
                <table class="table table-bordered table-hover table-sm table-mobile-card" width="100%" cellspacing="0">
                    <thead class="bg-light">
                        <tr class="text-center">
                            @foreach ($grid as $col)
                                <th class="{{ $col['class'] ?? 'text-center' }}">{{ $col['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        {{-- diisi via AJAX --}}
                    </tbody>
                </table>
            </div>

            {{-- Error state --}}
            <div id="errorState" class="text-center py-4" style="display:none;">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-2"></i>
                <p class="text-danger" id="errorMessage">Gagal memuat data.</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            const $form = $('#formFilter');
            const $btnLoad = $('#btnTampilkan');
            const $loading = $('#loadingIndicator');
            const $empty = $('#emptyState');
            const $tableWrap = $('#tableWrapper');
            const $tbody = $('#tableBody');
            const $error = $('#errorState');
            const $errorMsg = $('#errorMessage');
            const $infoRange = $('#infoRange');
            const $shuSummary = $('#shuSummary');
            const $shuSummaryLabel = $('#shuSummaryLabel');
            const $shuValue = $('#shuValue');
            const $shuWarning = $('#shuWarning');


            // Definisi grid & field filter dari controller
            const gridColumns = @json($grid);
            const formFields = @json($form);

            /**
             * Ambil nilai field (untuk angka, nilai asli ada di hidden input).
             */
            function getFieldValue(name) {
                const el = document.getElementById(name);
                return el ? (el.value ?? '') : '';
            }

            /**
             * Teks tampilan nilai field (untuk infoRange).
             */
            function getFieldDisplay(field) {
                const val = getFieldValue(field.name);
                if (val === '' || val == null) {
                    return '';
                }

                if (field.type === 'date') {
                    return new Date(val).toLocaleDateString('id-ID', {
                        day: '2-digit',
                        month: 'long',
                        year: 'numeric'
                    });
                }

                if (field.type === 'select' || field.type === 'autocomplete') {
                    const $opt = $('#' + field.name + ' option:selected');
                    return ($opt.length && $opt.val() !== '') ? $opt.text() : val;
                }

                return val;
            }

            /**
             * Validasi field required dari konfigurasi $form.
             */
            function validateRequired() {
                const missing = [];
                formFields.forEach(function(f) {
                    if (f.required) {
                        const val = getFieldValue(f.name);
                        if (val === '' || val == null) {
                            missing.push(f.label);
                        }
                    }
                });
                return missing;
            }

            /**
             * Validasi rentang tanggal: Tanggal Awal tidak boleh melebihi Tanggal Akhir
             * (format YYYY-MM-DD dari input type=date, bisa dibandingkan sebagai string).
             */
            function validateRange() {
                const awal = getFieldValue('jTglAwal');
                const akhir = getFieldValue('jTglAkhir');
                if (awal && akhir && awal > akhir) {
                    swalError('Filter Tidak Valid', 'Tanggal Awal tidak boleh melebihi Tanggal Akhir.');
                    return false;
                }
                return true;
            }

            /**
             * Info rentang filter: "Tanggal Awal: ... | Tanggal Akhir: ..."
             */
            function buildInfoText() {
                const parts = [];
                formFields.forEach(function(f) {
                    const display = getFieldDisplay(f);
                    if (display) {
                        parts.push(display);
                    }
                });
                return parts.length ? 'Filter : ' + parts.join(' | ') : '';
            }

            /**
             * Kembalikan semua field ke nilai default dari konfigurasi $form.
             */
            function resetForm() {
                formFields.forEach(function(f) {
                    const $el = $('#' + f.name);
                    const def = f.default ?? '';

                    if (f.type === 'autocomplete') {
                        if ($el.data('select2')) {
                            if (def) {
                                $el.append(new Option(def, def, true, true)).trigger('change');
                            } else {
                                $el.val(null).trigger('change');
                            }
                        }
                    } else if (f.type === 'angka') {
                        $('#' + f.name + '_display').val(def !== '' ? def : '');
                        $el.val(def);
                    } else {
                        $el.val(def);
                    }
                });

                $tableWrap.hide();
                $shuSummary.hide();
                $shuWarning.hide();
                $error.hide();
                $empty.html(
                    '<i class="fas fa-search fa-3x text-muted mb-2"></i>' +
                    '<p class="text-muted">Silakan atur filter lalu klik <strong>Tampilkan</strong>.</p>'
                ).show();
                $infoRange.text('');
            }

            /**
             * Escape & dan " untuk atribut data-label
             */
            function escAttr(s) {
                return String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;');
            }

            /**
             * Render satu baris pembagian SHU: No | Kategori | Objek COA | Persentase | Nilai.
             */
            function renderRow(row) {
                const objek = row.objekKode
                    ? escAttr(row.objekKode + (row.objekNama ? ' - ' + row.objekNama : ''))
                    : '-';

                return '<tr>' +
                    '<td class="text-center" data-label="No">' + escAttr(row.no) + '</td>' +
                    '<td data-label="Kategori">' + escAttr(row.kategori) + '</td>' +
                    '<td data-label="Objek COA">' + objek + '</td>' +
                    '<td class="text-right" data-label="Persentase">' + escAttr(row.persen) + ' %</td>' +
                    '<td class="text-right" data-label="Nilai">' + escAttr(row.nilai) + '</td></tr>';
            }

            /**
             * Load data via AJAX — semua parameter filter dikirim apa adanya
             * dari form, sesuai field yang didefinisikan di $this->form.
             */
            function loadData() {
                const missing = validateRequired();
                if (missing.length) {
                    swalError('Filter Tidak Lengkap', 'Silakan isi: ' + missing.join(', ') + '.');
                    return;
                }
                if (!validateRange()) {
                    return;
                }

                // Show loading, hide others
                $loading.show();
                $empty.hide();
                $tableWrap.hide();
                $shuSummary.hide();
                $shuWarning.hide();
                $error.hide();
                $btnLoad.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Memuat...');

                $.ajax({
                    url: '{{ route($route . '.load') }}',
                    type: 'GET',
                    data: $form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        $loading.hide();
                        $btnLoad.prop('disabled', false).html(
                            '<i class="fas fa-search mr-1"></i>');
                        $infoRange.text(buildInfoText());

                        const data = response.data || [];

                        if (data.length === 0) {
                            $empty.html(
                                '<i class="fas fa-info-circle fa-3x text-info mb-2"></i>' +
                                '<p class="text-muted">Konfigurasi pembagian SHU belum ada. ' +
                                'Jalankan seeder atau isi lewat ' +
                                '<a href="{{ route('ms-shu.index') }}">Config Pembagian SHU</a>.</p>'
                            ).show();
                            return;
                        }

                        // Render baris pembagian SHU sesuai konfigurasi ms_shu.
                        $tbody.empty();
                        data.forEach(function(row) {
                            $tbody.append(renderRow(row));
                        });

                        // Footer hasil: total + SHU (laba hijau / rugi merah).
                        $tbody.append('<tr class="font-weight-bold bg-light">' +
                            '<td colspan="3" class="text-right" data-label="Keterangan">TOTAL</td>' +
                            '<td class="text-right" data-label="Persentase">' + response.totalPersen + ' %</td>' +
                            '<td class="text-right" data-label="Nilai">' + response.totalNilai + '</td></tr>');

                        $tbody.append('<tr class="font-weight-bold ' +
                            (response.laba ? 'text-success' : 'text-danger') + '">' +
                            '<td colspan="4" class="text-right" data-label="Keterangan">' +
                            (response.laba ? 'SHU (LABA BERSIH)' : 'SHU (RUGI BERSIH)') + '</td>' +
                            '<td class="text-right" data-label="Nilai">' + response.shu + '</td></tr>');

                        // Ringkasan SHU + warning di atas tabel
                        $shuSummaryLabel.text(response.laba ? 'SHU (LABA BERSIH) PERIODE' : 'SHU (RUGI BERSIH) PERIODE');
                        $shuValue.text(response.shu);
                        $shuSummary
                            .removeClass('alert-success alert-danger')
                            .addClass(response.laba ? 'alert-success' : 'alert-danger')
                            .show();

                        const warnings = [];
                        if (!response.persenOk) {
                            warnings.push('Total persentase konfigurasi belum 100% (' +
                                response.totalPersen + '%). Periksa ' +
                                '<a href="{{ route('ms-shu.index') }}">Config Pembagian SHU</a>.');
                        }
                        if (!response.laba) {
                            warnings.push('Periode ini RUGI; nilai pembagian ditampilkan negatif.');
                        }
                        $shuWarning.html(warnings.join('<br>')).toggle(warnings.length > 0);

                        $tableWrap.show();

                    },
                    error: function(xhr) {
                        $loading.hide();
                        $btnLoad.prop('disabled', false).html(
                            '<i class="fas fa-search mr-1"></i>Tampilkan');
                        $error.show();
                        const msg = xhr.responseJSON?.message || 'Gagal memuat data laporan.';
                        $errorMsg.text(msg);
                    }
                });
            }

            // ── Event handlers ──────────────────────────
            $form.on('submit', function(e) {
                e.preventDefault();
                loadData();
            });

            /**
             * Cetak PDF: kirim semua parameter filter ke CetakPembagianShuController
             * di tab baru (route GET, jadi cukup query string).
             */
            $('#btnCetak').on('click', function() {
                const missing = validateRequired();
                if (missing.length) {
                    swalError('Filter Tidak Lengkap', 'Silakan isi: ' + missing.join(', ') + '.');
                    return;
                }
                if (!validateRange()) {
                    return;
                }

                window.open('{{ route($route.'.pdf') }}?' + $form.serialize(), '_blank');
            });

            /**
             * Export Excel: kirim semua parameter filter ke PembagianShuController
             * (route GET, jadi cukup query string — browser otomatis mengunduh).
             */
            $('#btnExcel').on('click', function() {
                const missing = validateRequired();
                if (missing.length) {
                    swalError('Filter Tidak Lengkap', 'Silakan isi: ' + missing.join(', ') + '.');
                    return;
                }
                if (!validateRange()) {
                    return;
                }

                window.open('{{ route($route.'.excel') }}?' + $form.serialize(), '_blank');
            });

            $('#btnReset').on('click', function() {
                resetForm();
            });

            // Auto-load on page ready (opsional)
            // loadData();
        });
    </script>
@endpush
