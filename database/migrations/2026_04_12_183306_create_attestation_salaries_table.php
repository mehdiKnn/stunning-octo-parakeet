<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attestation_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attestation_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('period_year');
            $table->smallInteger('period_month');
            $table->string('immatriculation_number', 30)->nullable();
            $table->string('full_name', 255)->nullable();
            $table->integer('days_worked')->nullable();
            $table->decimal('declared_salary', 15, 2)->nullable();

            $table->index('immatriculation_number');
            $table->index(['period_year', 'period_month']);
        });

        DB::statement('CREATE INDEX attestation_salaries_full_name_trgm ON attestation_salaries USING GIN (full_name gin_trgm_ops)');
        DB::statement('CREATE INDEX attestation_salaries_declared_salary_idx ON attestation_salaries (declared_salary DESC NULLS LAST)');
    }

    public function down(): void
    {
        Schema::dropIfExists('attestation_salaries');
    }
};
