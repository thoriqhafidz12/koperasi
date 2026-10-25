@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0">Import Jurnal</h3>
        <a href="{{ route('rpt-jurnal.index') }}" class="btn btn-outline-primary">
            <i class="fas fa-book mr-1"></i>Laporan Jurnal
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Unggah File Excel</h6>
        </div>
        <div class="card-body">
            @if ($errors->has('file'))
                <div class="alert alert-danger" role="alert">
                    <strong>File belum dapat diimpor:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->get('file') as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p>
                Unduh template, isi satu transaksi pasangan akun per baris, lalu unggah kembali dalam format
                <code>.xlsx</code> atau <code>.xls</code> (maksimal 10 MB).
            </p>

            <a href="{{ route('import-jurnal.template') }}" class="btn btn-success mb-4">
                <i class="fas fa-file-excel mr-1"></i> Unduh Template Excel
            </a>

            <form action="{{ route('import-jurnal.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="file">File Excel</label>
                    <input
                        type="file"
                        name="file"
                        id="file"
                        class="form-control-file @error('file') is-invalid @enderror"
                        accept=".xlsx,.xls"
                        required
                    >
                    <small class="form-text text-muted">
                        Pastikan nama kolom pada baris pertama tidak diubah dari template.
                    </small>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload mr-1"></i> Import Jurnal
                </button>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Riwayat File Import</h6>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Setiap baris jurnal import ditandai dengan nama file. Hapus satu batch untuk menghapus seluruh jurnal
                yang berasal dari file tersebut.
            </p>

            @if ($imports->isEmpty())
                <div class="text-center text-muted py-3">Belum ada file jurnal yang diimpor.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Nama File</th>
                                <th class="text-center">Waktu Import</th>
                                <th class="text-center">ID Batch</th>
                                <th class="text-right">Jumlah Baris</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($imports as $import)
                                <tr>
                                    <td>{{ $import->jImportFile }}</td>
                                    <td class="text-center">
                                        {{ $import->imported_at ? \Carbon\Carbon::parse($import->imported_at)->format('d/m/Y H:i') : '-' }}
                                    </td>
                                    <td class="text-center"><code>{{ substr($import->jImportBatchId, 0, 8) }}</code></td>
                                    <td class="text-right">{{ number_format($import->total_rows, 0, ',', '.') }}</td>
                                    <td class="text-center">
                                        <form action="{{ route('import-jurnal.batch.destroy', $import->jImportBatchId) }}"
                                            method="POST"
                                            onsubmit="return confirm('Hapus seluruh jurnal dari batch import ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash-alt mr-1"></i> Hapus Batch
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Aturan Pengisian</h6>
        </div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Kolom yang wajib diisi: No Jurnal, Tanggal, Kode Debet, Nilai Debet, Kode Kredit, dan Nilai Kredit.</li>
                <li>Tanggal dapat ditulis sebagai <code>YYYY-MM-DD</code> atau <code>DD/MM/YYYY</code>.</li>
                <li>Formatkan No Jurnal dan kode akun sebagai teks agar angka awal atau tanda titik pada kode tetap utuh.</li>
                <li>Kode debet dan kredit harus sudah terdaftar di Master Objek. Nama akun akan diambil otomatis dari master.</li>
                <li>Nilai debet dan kredit pada satu baris harus sama dan lebih besar dari nol; masukkan angka tanpa teks mata uang.</li>
                <li>Sumber boleh dikosongkan; sistem akan mengisinya dengan <code>IMPORT EXCEL</code>. Jurnal baru berstatus belum closing.</li>
                <li>Gunakan satu sheet transaksi. Semua baris divalidasi lebih dulu; jika ada kesalahan, tidak ada baris yang disimpan.</li>
                <li>Import menambahkan transaksi ke jurnal. Jangan unggah ulang file yang sama agar laporan tidak menghitung transaksi dua kali.</li>
            </ul>
        </div>
    </div>
@endsection
