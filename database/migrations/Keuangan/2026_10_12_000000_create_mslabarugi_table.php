<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ms_labarugi', function (Blueprint $table) {
            $table->increments('lrId');
            $table->string('lrKode', 50)->unique()->comment('Kode baris layout, mis. PENDAPATAN / 5.02 / T-PENDAPATAN');
            $table->string('lrNama', 225);
            $table->string('lrPosisi', 10)->default('PENDAPATAN')->comment('PENDAPATAN | BIAYA');
            $table->string('lrJenis', 10)->default('item')->comment('header | item | objek | total');
            $table->string('lrKodeAkun', 150)->nullable()->comment('Prefix kode objek (ms_objek.msoKode), dipisah koma bila lebih dari satu. Contoh: 4.01.01 atau 4,5');
            $table->string('lrSaldoNormal', 1)->default('D')->comment('D = debet - kredit, K = kredit - debet');
            $table->integer('lrUrut')->default(0);
            $table->string('lrCreatedBy', 50)->nullable();
            $table->datetime('lrCreatedAt')->nullable();
            $table->string('lrUpdatedBy', 50)->nullable();
            $table->datetime('lrUpdatedAt')->nullable();
            $table->string('lrDeletedBy', 50)->nullable();
            $table->datetime('lrDeletedAt')->nullable();

            $table->index('lrUrut');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ms_labarugi');
    }
};
