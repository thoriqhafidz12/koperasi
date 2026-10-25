<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jurnal', function (Blueprint $table) {
            $table->increments('jId');
            $table->integer('jHeadId')->nullable();
            $table->string('jNo', 100)->nullable();
            $table->date('jTgl')->nullable();
            $table->text('jKeterangan')->nullable();
            $table->string('jRekDebetKode', 50)->nullable();
            $table->string('jRekDebetNama', 100)->nullable();
            $table->decimal('jDebetNilai', 20, 2)->nullable()->default(0);
            $table->string('jRekKreditKode', 50)->nullable();
            $table->string('jRekKreditNama', 100)->nullable();
            $table->decimal('jKreditNilai', 20, 2)->nullable()->default(0);
            $table->string('jImportBatchId', 50)->nullable();
            $table->string('jImportFile', 255)->nullable();
            $table->string('jSumber', 100)->nullable();
            $table->integer('jStatus')->nullable()->default(0)->comment('0. Belum Closing | 1. Closing');
            $table->timestamps();

            $table->index('jImportBatchId', 'jurnal_import_batch_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jurnal');
    }
};
