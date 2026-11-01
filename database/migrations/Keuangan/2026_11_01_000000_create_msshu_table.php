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
        Schema::create('ms_shu', function (Blueprint $table) {
            $table->increments('shuId');
            $table->string('shuKode', 20)->unique()->comment('Kode baris pembagian, mis. SHU-01');
            $table->string('shuNama', 150)->comment('Kategori pembagian, mis. Cadangan Modal');
            $table->decimal('shuPersen', 5, 2)->default(0)->comment('Persentase pembagian (0-100), presisi 2 desimal');
            $table->string('shuObjekKode', 20)->nullable()->comment('Kode objek COA (ms_objek.msoKode)');
            $table->integer('shuUrut')->default(0);
            $table->tinyInteger('shuIsActive')->default(1)->comment('1 = aktif (ikut laporan), 0 = nonaktif');
            $table->string('shuCreatedBy', 50)->nullable();
            $table->datetime('shuCreatedAt')->nullable();
            $table->string('shuUpdatedBy', 50)->nullable();
            $table->datetime('shuUpdatedAt')->nullable();
            $table->string('shuDeletedBy', 50)->nullable();
            $table->datetime('shuDeletedAt')->nullable();

            $table->index('shuUrut');
            $table->index('shuObjekKode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ms_shu');
    }
};
