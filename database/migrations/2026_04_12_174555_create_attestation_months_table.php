<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attestation_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attestation_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('year');
            $table->smallInteger('month');
            $table->integer('nb_salaries')->nullable();
            $table->decimal('masse_salariale', 15, 2)->nullable();

            $table->unique(['attestation_id', 'year', 'month']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attestation_months');
    }
};
