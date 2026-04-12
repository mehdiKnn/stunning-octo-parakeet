<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adherent_admins', function (Blueprint $table) {
            $table->id();
            $table->string('affiliate_number', 20);
            $table->string('first_name', 200)->nullable();
            $table->string('last_name', 200)->nullable();
            $table->string('cin', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->boolean('is_rl')->default(false);

            $table->index('affiliate_number');
            $table->index('cin');
            $table->index('email');
        });

        DB::statement("
            ALTER TABLE adherent_admins
              ADD COLUMN gravity_score smallint
              GENERATED ALWAYS AS (
                (CASE WHEN cin IS NOT NULL AND cin <> '' THEN 3 ELSE 0 END) +
                (CASE WHEN email IS NOT NULL AND email <> '' THEN 1 ELSE 0 END) +
                (CASE WHEN phone_number IS NOT NULL AND phone_number <> '' THEN 1 ELSE 0 END) +
                (CASE WHEN is_rl THEN 2 ELSE 0 END)
              ) STORED
        ");

        DB::statement('CREATE INDEX adherent_admins_last_name_trgm ON adherent_admins USING GIN (last_name gin_trgm_ops)');
        DB::statement('CREATE INDEX adherent_admins_cin_trgm ON adherent_admins USING GIN (cin gin_trgm_ops)');
        DB::statement('CREATE INDEX adherent_admins_gravity_idx ON adherent_admins (gravity_score DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('adherent_admins');
    }
};
