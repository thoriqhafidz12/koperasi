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
        Schema::create('ms_comp', function (Blueprint $table) {
            $table->unsignedInteger('cpId')->autoIncrement();
            $table->string('cpNama', 225);
            $table->text('cpAlamat')->nullable();
            $table->string('cpTelp', 20)->nullable();
            $table->string('cpEmail', 100)->nullable();
            $table->string('cpLogo', 225)->nullable();
            $table->string('cpFavicon', 225)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ms_comp');
    }
};
