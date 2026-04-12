<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->string('immatriculation_number', 30)->unique();
            $table->string('new_immatriculated_id', 30)->nullable();
            $table->string('affiliate_number', 20)->nullable();
            $table->integer('id_adherent')->nullable();
            $table->string('first_name', 200)->nullable();
            $table->string('last_name', 200)->nullable();
            $table->string('cin', 30)->nullable();
            $table->string('passport_number', 80)->nullable();
            $table->string('residence_number', 30)->nullable();
            $table->date('creation_date')->nullable();
            $table->string('demand_mode', 30)->nullable();
            $table->string('demand_state', 30)->nullable();

            $table->index('affiliate_number');
            $table->index('cin');
            $table->index('passport_number');
            $table->index('demand_state');
            $table->index('creation_date');
        });

        DB::statement("
            ALTER TABLE salaries
              ADD COLUMN gravity_score smallint
              GENERATED ALWAYS AS (
                (CASE WHEN cin IS NOT NULL AND cin <> '' AND cin <> 'None' THEN 3 ELSE 0 END) +
                (CASE WHEN passport_number IS NOT NULL AND passport_number <> '' AND passport_number <> 'None' THEN 2 ELSE 0 END) +
                (CASE WHEN residence_number IS NOT NULL AND residence_number <> '' AND residence_number <> 'None' THEN 2 ELSE 0 END) +
                (CASE WHEN first_name IS NOT NULL AND last_name IS NOT NULL THEN 1 ELSE 0 END)
              ) STORED
        ");

        DB::statement("
            ALTER TABLE salaries
              ADD COLUMN search_vector tsvector
              GENERATED ALWAYS AS (
                to_tsvector('simple',
                  coalesce(first_name,'') || ' ' ||
                  coalesce(last_name,'') || ' ' ||
                  coalesce(cin,'')
                )
              ) STORED
        ");

        DB::statement('CREATE INDEX salaries_last_name_trgm ON salaries USING GIN (last_name gin_trgm_ops)');
        DB::statement('CREATE INDEX salaries_first_name_trgm ON salaries USING GIN (first_name gin_trgm_ops)');
        DB::statement('CREATE INDEX salaries_cin_trgm ON salaries USING GIN (cin gin_trgm_ops)');
        DB::statement('CREATE INDEX salaries_search_vector_gin ON salaries USING GIN (search_vector)');
        DB::statement('CREATE INDEX salaries_gravity_idx ON salaries (gravity_score DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};
