<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attestations', function (Blueprint $table) {
            $table->id();
            $table->string('file_id', 30)->unique();
            $table->string('attestation_number', 30)->nullable();
            $table->string('affiliate_number', 20)->nullable();
            $table->string('raison_sociale', 500)->nullable();
            $table->text('activite')->nullable();
            $table->text('adresse')->nullable();
            $table->string('ville', 150)->nullable();
            $table->string('ice', 20)->nullable();
            $table->string('registre_commerce', 30)->nullable();
            $table->string('taxe_professionnelle', 30)->nullable();
            $table->string('identifiant_fiscal', 30)->nullable();
            $table->string('forme_juridique', 150)->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('delivered_at')->nullable();
            $table->boolean('parse_ok')->default(true);

            $table->index('affiliate_number');
            $table->index('ville');
            $table->index('ice');
            $table->index('forme_juridique');
        });

        DB::statement("
            ALTER TABLE attestations
              ADD COLUMN search_vector tsvector
              GENERATED ALWAYS AS (
                to_tsvector('simple',
                  coalesce(raison_sociale,'') || ' ' ||
                  coalesce(activite,'') || ' ' ||
                  coalesce(adresse,'') || ' ' ||
                  coalesce(ville,'')
                )
              ) STORED
        ");

        DB::statement('CREATE INDEX attestations_raison_sociale_trgm ON attestations USING GIN (raison_sociale gin_trgm_ops)');
        DB::statement('CREATE INDEX attestations_activite_trgm ON attestations USING GIN (activite gin_trgm_ops)');
        DB::statement('CREATE INDEX attestations_search_vector_gin ON attestations USING GIN (search_vector)');
    }

    public function down(): void
    {
        Schema::dropIfExists('attestations');
    }
};
