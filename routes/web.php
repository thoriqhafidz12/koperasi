<?php

use App\Http\Controllers\Akuntansi\BukuBesarController;
use App\Http\Controllers\Akuntansi\Import\ImportJurnalController;
use App\Http\Controllers\Akuntansi\JurnalController;
use App\Http\Controllers\Akuntansi\LabaRugiController;
use App\Http\Controllers\Akuntansi\NeracaController;
use App\Http\Controllers\Akuntansi\PembagianShuController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Bendahara\PenerimaanController;
use App\Http\Controllers\Bendahara\PengeluaranController;
use App\Http\Controllers\Combo\JadwalangsuranforcomboController;
use App\Http\Controllers\Combo\MapKeluarforcomboController;
use App\Http\Controllers\Combo\MapTerimaforcomboController;
use App\Http\Controllers\Combo\PengajuanforcomboController;
use App\Http\Controllers\Combo\PinjamanforcomboController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GantiPassController;
use App\Http\Controllers\Master\Coa\MasterAkunController;
use App\Http\Controllers\Master\Coa\MasterJenisController;
use App\Http\Controllers\Master\Coa\MasterKelompokController;
use App\Http\Controllers\Master\Coa\MasterObjekController;
use App\Http\Controllers\Master\JenisPinjamanController;
use App\Http\Controllers\Master\JenisSimpananController;
use App\Http\Controllers\Master\Mapping\MappingPenerimaanController;
use App\Http\Controllers\Master\Mapping\MappingPengeluaranController;
use App\Http\Controllers\Master\Mapping\MappingSaldoAwalController;
use App\Http\Controllers\Master\MasterAnggotaController;
use App\Http\Controllers\Master\MasterBankkasController;
use App\Http\Controllers\Master\MasterJaminanController;
use App\Http\Controllers\Master\MasterLabaRugiController;
use App\Http\Controllers\Master\MasterNeracaController;
use App\Http\Controllers\Master\MasterPembagianShuController;
use App\Http\Controllers\Master\MasterTujuanPinjamanController;
use App\Http\Controllers\Master\MetodePembayaranController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\Pinjaman\PembayaranPinjamanController;
use App\Http\Controllers\Pinjaman\PencairanPinjamanController;
use App\Http\Controllers\Pinjaman\PengajuanPinjamanController;
use App\Http\Controllers\Report\CetakPenerimaanController;
use App\Http\Controllers\Report\CetakPengeluaranController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/getSession', [LoginController::class, 'checkSession'])->name('session.check');

// Guest routes (only accessible when NOT logged in)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Authenticated routes (only accessible when logged in)
// checkRole: cek akses menu role user via role_menu — jika tidak ada akses, redirect ke 403
// dashboard ikut dicek
// karena terdaftar di tabel menu (mRoute = 'dashboard').
Route::middleware(['auth', 'checkRole'])->group(function () {
    // Route::get('/dashboard', function () {
    //     return view('dashboard', [
    //         'totalUsers' => User::count(),
    //         'totalRoles' => Role::count(),
    //         'totalMenus' => Menu::count(),
    //     ]);
    // })->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // API FOR COMBO
    Route::get('api/akun/search', [MasterAkunController::class, 'search'])->name('api.akun.search');
    Route::get('api/kelompok/search', [MasterKelompokController::class, 'search'])->name('api.kelompok.search');
    Route::get('api/objek/search', [MasterObjekController::class, 'search'])->name('api.objek.search');
    Route::get('api/jenis/search', [MasterJenisController::class, 'search'])->name('api.jenis.search');
    Route::get('api/tujuan-pinjaman/search', [MasterTujuanPinjamanController::class, 'search'])->name('api.tujuan-pinjaman.search');
    Route::get('api/anggota/search', [MasterAnggotaController::class, 'search'])->name('api.anggota.search');
    Route::get('api/jaminan/search', [MasterJaminanController::class, 'search'])->name('api.jaminan.search');
    Route::get('api/metode-pembayaran/search', [MetodePembayaranController::class, 'search'])->name('api.metode-pembayaran.search');
    Route::get('api/pengajuan-pinjaman/search', [PengajuanforcomboController::class, 'index'])->name('api.pengajuan-pinjaman.search');
    Route::get('api/pencairan-pinjaman/search', [PinjamanforcomboController::class, 'index'])->name('api.pencairan-pinjaman.search');
    Route::get('api/jadwal-angsuran/search', [JadwalangsuranforcomboController::class, 'index'])->name('api.jadwal-angsuran.search');
    Route::get('api/mapping-penerimaan/search', [MapTerimaforcomboController::class, 'index'])->name('api.mapping-penerimaan.search');
    Route::get('api/mapping-pengeluaran/search', [MapKeluarforcomboController::class, 'index'])->name('api.mapping-pengeluaran.search');

    // MENU MANAGEMENT
    // Custom role menu routes (harus diatas resource agar tidak ditangkap {role} wildcard)
    Route::get('roles/{role}/menu', [RoleController::class, 'menu'])->name('roles.menu');
    Route::put('roles/{role}/menu', [RoleController::class, 'updateMenu'])->name('roles.updateMenu');

    // Route::resource('ganti-password', GantiPassController::class);
    Route::get('ganti-password', [GantiPassController::class, 'index'])->name('ganti-password.index');
    Route::put('ganti-password', [GantiPassController::class, 'update'])->name('ganti-password.update');

    Route::resource('roles', RoleController::class);
    Route::resource('users', UserController::class);
    Route::resource('menus', MenuController::class);

    // MASTER
    Route::resource('ms-anggota', MasterAnggotaController::class);
    Route::resource('ms-jns-pinjaman', JenisPinjamanController::class);
    Route::resource('ms-jns-simpanan', JenisSimpananController::class);

    Route::resource('ms-akun', MasterAkunController::class);
    Route::resource('ms-kelompok', MasterKelompokController::class);
    Route::resource('ms-jenis', MasterJenisController::class);
    Route::resource('ms-objek', MasterObjekController::class);
    Route::resource('ms-metode-bayar', MetodePembayaranController::class);

    Route::resource('ms-mapping-penerimaan', MappingPenerimaanController::class);
    Route::resource('ms-mapping-pengeluaran', MappingPengeluaranController::class);
    Route::resource('ms-mapping-saldoawal', MappingSaldoAwalController::class);

    Route::resource('ms-bank-kas', MasterBankkasController::class);
    Route::resource('ms-jaminan', MasterJaminanController::class);
    Route::resource('ms-tujuan-pinjaman', MasterTujuanPinjamanController::class);
    Route::resource('ms-neraca', MasterNeracaController::class);
    Route::resource('ms-labarugi', MasterLabaRugiController::class);
    Route::resource('ms-shu', MasterPembagianShuController::class);

    // PINJAMAN
    Route::resource('pengajuan-pinjaman', PengajuanPinjamanController::class);
    Route::put('pengajuan-pinjaman/{id}/persetujuan', [PengajuanPinjamanController::class, 'persetujuan'])->name('pengajuan-pinjaman.persetujuan');
    // PENCAIRAN
    Route::resource('pencairan-pinjaman', PencairanPinjamanController::class);
    Route::resource('pembayaran-pinjaman', PembayaranPinjamanController::class);

    // AKUTANSI
    Route::get('rpt-jurnal', [JurnalController::class, 'index'])->name('rpt-jurnal.index');
    Route::get('rpt-jurnal/load', [JurnalController::class, 'loadData'])->name('rpt-jurnal.load');
    Route::get('rpt-jurnal/pdf', [JurnalController::class, 'pdf'])->name('rpt-jurnal.pdf');
    Route::get('rpt-jurnal/excel', [JurnalController::class, 'excel'])->name('rpt-jurnal.excel');

    Route::get('rpt-buku-besar', [BukuBesarController::class, 'index'])->name('rpt-buku-besar.index');
    Route::get('rpt-buku-besar/load', [BukuBesarController::class, 'loadData'])->name('rpt-buku-besar.load');
    Route::get('rpt-buku-besar/pdf', [BukuBesarController::class, 'pdf'])->name('rpt-buku-besar.pdf');
    Route::get('rpt-buku-besar/excel', [BukuBesarController::class, 'excel'])->name('rpt-buku-besar.excel');

    Route::get('rpt-neraca', [NeracaController::class, 'index'])->name('rpt-neraca.index');
    Route::get('rpt-neraca/load', [NeracaController::class, 'loadData'])->name('rpt-neraca.load');
    Route::get('rpt-neraca/pdf', [NeracaController::class, 'pdf'])->name('rpt-neraca.pdf');
    Route::get('rpt-neraca/excel', [NeracaController::class, 'excel'])->name('rpt-neraca.excel');

    Route::get('rpt-labarugi', [LabaRugiController::class, 'index'])->name('rpt-labarugi.index');
    Route::get('rpt-labarugi/load', [LabaRugiController::class, 'loadData'])->name('rpt-labarugi.load');
    Route::get('rpt-labarugi/pdf', [LabaRugiController::class, 'pdf'])->name('rpt-labarugi.pdf');
    Route::get('rpt-labarugi/excel', [LabaRugiController::class, 'excel'])->name('rpt-labarugi.excel');

    Route::get('rpt-pembagian-shu', [PembagianShuController::class, 'index'])->name('rpt-pembagian-shu.index');
    Route::get('rpt-pembagian-shu/load', [PembagianShuController::class, 'loadData'])->name('rpt-pembagian-shu.load');
    Route::get('rpt-pembagian-shu/pdf', [PembagianShuController::class, 'pdf'])->name('rpt-pembagian-shu.pdf');
    Route::get('rpt-pembagian-shu/excel', [PembagianShuController::class, 'excel'])->name('rpt-pembagian-shu.excel');

    Route::get('import-jurnal', [ImportJurnalController::class, 'index'])->name('import-jurnal.index');
    Route::get('import-jurnal/template', [ImportJurnalController::class, 'template'])->name('import-jurnal.template');
    Route::post('import-jurnal', [ImportJurnalController::class, 'store'])->name('import-jurnal.store');
    Route::delete('import-jurnal/batch/{batchId}', [ImportJurnalController::class, 'destroyBatch'])->name('import-jurnal.batch.destroy');

    // BENDAHARA
    Route::resource('penerimaan', PenerimaanController::class);
    Route::get('penerimaan/{id}/cetak', [CetakPenerimaanController::class, 'index'])->name('penerimaan.cetak');
    
    Route::resource('pengeluaran', PengeluaranController::class);
    Route::get('pengeluaran/{id}/cetak', [CetakPengeluaranController::class, 'index'])->name('pengeluaran.cetak');

});

// Redirect root to login or dashboard
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});
