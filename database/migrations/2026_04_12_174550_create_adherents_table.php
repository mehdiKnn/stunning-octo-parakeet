<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adherents', function (Blueprint $table) {
            $table->id();
            $table->string('affiliate_number', 20)->unique();
            $table->string('company_name', 500);
            $table->string('type_adherent', 50)->nullable();
            $table->string('modalite_telepaiement', 50)->nullable();
            $table->date('date_adhesion')->nullable();
            $table->date('date_affiliation')->nullable();
            $table->string('agence', 150)->nullable();
            $table->string('direction_regionale', 150)->nullable();
            $table->string('company_name_mandataire', 500)->nullable();
            $table->string('affiliate_number_mandataire', 20)->nullable();
            $table->string('bank_account_id', 50)->nullable();
            $table->string('bank_code', 10)->nullable();
            $table->string('bank_account_state', 30)->nullable();
            $table->string('bank_account_default_state', 30)->nullable();
            $table->timestamp('bank_date_creation')->nullable();
            $table->string('bank_account_rib', 30)->nullable();

            $table->index('agence');
            $table->index('direction_regionale');
            $table->index('type_adherent');
            $table->index('date_affiliation');
        });

        DB::statement("
            ALTER TABLE adherents
              ADD COLUMN search_vector tsvector
              GENERATED ALWAYS AS (
                to_tsvector('simple',
                  coalesce(company_name,'') || ' ' ||
                  coalesce(agence,'') || ' ' ||
                  coalesce(direction_regionale,'')
                )
              ) STORED
        ");

        DB::statement('CREATE INDEX adherents_company_name_trgm ON adherents USING GIN (company_name gin_trgm_ops)');
        DB::statement('CREATE INDEX adherents_search_vector_gin ON adherents USING GIN (search_vector)');
        DB::statement('CREATE INDEX adherents_bank_rib_idx ON adherents (bank_account_rib) WHERE bank_account_rib IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('adherents');
    }
};
