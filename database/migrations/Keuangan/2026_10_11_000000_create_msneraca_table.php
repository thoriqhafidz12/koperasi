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
        Schema::create('ms_neraca', function (Blueprint $table) {
            $table->increments('nrId');
            $table->string('nrKode', 50)->unique()->comment('Kode baris layout, mis. AKTIVA / 1.01 / 1.01.01 / T-AKTIVA / LR');
            $table->string('nrNama', 225);
            $table->string('nrPosisi', 10)->default('AKTIVA')->comment('AKTIVA | PASIVA');
            $table->string('nrJenis', 10)->default('item')->comment('header | item | objek | total');
            $table->string('nrKodeAkun', 150)->nullable()->comment('Prefix kode objek (ms_objek.msoKode), dipisah koma bila lebih dari satu. Contoh: 1.01.01 atau 4,5');
            $table->string('nrSaldoNormal', 1)->default('D')->comment('D = debet - kredit, K = kredit - debet');
            $table->integer('nrUrut')->default(0);
            $table->string('nrCreatedBy', 50)->nullable();
            $table->datetime('nrCreatedAt')->nullable();
            $table->string('nrUpdatedBy', 50)->nullable();
            $table->datetime('nrUpdatedAt')->nullable();
            $table->string('nrDeletedBy', 50)->nullable();
            $table->datetime('nrDeletedAt')->nullable();

            $table->index('nrUrut');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ms_neraca');
    }
};
