<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('ingest:adherents {csv? : Path to ADHERENTS.csv} {--force : Skip confirmation}')]
#[Description('Load ADHERENTS.csv into adherents + adherent_admins via Postgres COPY FROM.')]
class IngestAdherents extends Command
{
    private const DEFAULT_CSV = '/Users/mehdik/Documents/Maroc/Salaire Maroc/ADHERENTS.csv';

    public function handle(): int
    {
        $csv = $this->argument('csv') ?: self::DEFAULT_CSV;

        if (! is_readable($csv)) {
            $this->error("CSV introuvable ou non lisible : {$csv}");

            return self::FAILURE;
        }

        $this->info("Source  : {$csv}");
        $this->info('Cible   : adherents + adherent_admins');

        $existing = (int) DB::selectOne('SELECT COUNT(*) AS c FROM adherents')->c;
        if ($existing > 0 && ! $this->option('force')) {
            if (! $this->confirm("La table adherents contient déjà {$existing} lignes. Truncate et recharger ?", false)) {
                $this->warn('Abandonné.');

                return self::FAILURE;
            }
        }

        $started = microtime(true);

        $this->info('→ TRUNCATE tables cibles');
        DB::statement('TRUNCATE adherents, adherent_admins RESTART IDENTITY CASCADE');

        $this->info('→ Création de la table staging (UNLOGGED)');
        DB::statement('DROP TABLE IF EXISTS staging_adherents');
        DB::statement(<<<'SQL'
            CREATE UNLOGGED TABLE staging_adherents (
                "companyName"                         text,
                "affiliateNumber"                     text,
                "dateAdhesion"                        text,
                "dateAffiliation"                     text,
                "typeAdherent"                        text,
                "companyNameMandataire"               text,
                "affiliateNumberMandataire"           text,
                "modaliteTelepaiement"                text,
                "agence"                              text,
                "directionRegionale"                  text,
                "admin_firstName"                     text,
                "admin_lastName"                      text,
                "admin_cin"                           text,
                "admin_email"                         text,
                "admin_phoneNumber"                   text,
                "admin_isRL"                          text,
                "bank_accountId"                      text,
                "bank_bankCode"                       text,
                "bank_adherent_id"                    text,
                "bank_adherent_numAffilie"            text,
                "bank_adherent_typeAdherent"          text,
                "bank_adherent_modaliteTelepaiement"  text,
                "bank_adherent_adherentMandataire"    text,
                "bank_adherent_raisonSocial"          text,
                "bank_accountState"                   text,
                "bank_accountDefaultState"            text,
                "bank_dateCreation"                   text,
                "bank_accountRIB"                     text
            )
        SQL);

        $this->info('→ COPY FROM CSV (322 Mo, ~1,09 M lignes)');
        $copyStart = microtime(true);
        $escaped = str_replace("'", "''", $csv);
        DB::statement("COPY staging_adherents FROM '{$escaped}' WITH (FORMAT csv, HEADER true, ENCODING 'UTF8')");
        $staging = (int) DB::selectOne('SELECT COUNT(*) AS c FROM staging_adherents')->c;
        $this->line(sprintf('  %s lignes chargées en %.1fs', number_format($staging), microtime(true) - $copyStart));

        $this->info('→ INSERT INTO adherents (déduplication sur affiliate_number)');
        $adhStart = microtime(true);
        DB::statement(<<<'SQL'
            INSERT INTO adherents (
                affiliate_number, company_name, type_adherent, modalite_telepaiement,
                date_adhesion, date_affiliation, agence, direction_regionale,
                company_name_mandataire, affiliate_number_mandataire,
                bank_account_id, bank_code, bank_account_state, bank_account_default_state,
                bank_date_creation, bank_account_rib
            )
            SELECT DISTINCT ON (TRIM("affiliateNumber"))
                TRIM("affiliateNumber"),
                NULLIF(REGEXP_REPLACE(TRIM("companyName"), '^"+|"+$', '', 'g'), ''),
                NULLIF(TRIM("typeAdherent"), ''),
                NULLIF(TRIM("modaliteTelepaiement"), ''),
                NULLIF(TRIM("dateAdhesion"), '')::date,
                NULLIF(TRIM("dateAffiliation"), '')::date,
                NULLIF(TRIM("agence"), ''),
                NULLIF(TRIM("directionRegionale"), ''),
                NULLIF(REGEXP_REPLACE(TRIM("companyNameMandataire"), '^"+|"+$', '', 'g'), ''),
                NULLIF(TRIM("affiliateNumberMandataire"), ''),
                NULLIF(TRIM("bank_accountId"), ''),
                NULLIF(TRIM("bank_bankCode"), ''),
                NULLIF(TRIM("bank_accountState"), ''),
                NULLIF(TRIM("bank_accountDefaultState"), ''),
                NULLIF(TRIM("bank_dateCreation"), '')::timestamp,
                NULLIF(TRIM("bank_accountRIB"), '')
            FROM staging_adherents
            WHERE NULLIF(TRIM("affiliateNumber"), '') IS NOT NULL
            ORDER BY TRIM("affiliateNumber"),
                     ("admin_isRL" = '1') DESC NULLS LAST
        SQL);
        $adh = (int) DB::selectOne('SELECT COUNT(*) AS c FROM adherents')->c;
        $this->line(sprintf('  %s adherents insérés en %.1fs', number_format($adh), microtime(true) - $adhStart));

        $this->info('→ INSERT INTO adherent_admins (consolidation par (affilié, CIN))');
        $adminStart = microtime(true);
        DB::statement(<<<'SQL'
            INSERT INTO adherent_admins (
                affiliate_number, first_name, last_name, cin, email, phone_number, is_rl
            )
            -- 1) Personnes identifiables par CIN : consolidées en une ligne par (affilié, CIN)
            SELECT
                affiliate_number,
                COALESCE(
                    (array_agg(first_name) FILTER (WHERE is_rl AND first_name IS NOT NULL))[1],
                    (array_agg(first_name) FILTER (WHERE first_name IS NOT NULL))[1]
                ) AS first_name,
                COALESCE(
                    (array_agg(last_name) FILTER (WHERE is_rl AND last_name IS NOT NULL))[1],
                    (array_agg(last_name) FILTER (WHERE last_name IS NOT NULL))[1]
                ) AS last_name,
                cin,
                (array_agg(email) FILTER (WHERE email IS NOT NULL))[1] AS email,
                (array_agg(phone_number) FILTER (WHERE phone_number IS NOT NULL))[1] AS phone_number,
                bool_or(is_rl) AS is_rl
            FROM (
                SELECT
                    TRIM("affiliateNumber")                        AS affiliate_number,
                    NULLIF(TRIM("admin_firstName"), '')            AS first_name,
                    NULLIF(TRIM("admin_lastName"), '')             AS last_name,
                    NULLIF(TRIM("admin_cin"), '')                  AS cin,
                    NULLIF(LOWER(TRIM("admin_email")), '')         AS email,
                    NULLIF(TRIM("admin_phoneNumber"), '')          AS phone_number,
                    (TRIM("admin_isRL") = '1')                     AS is_rl
                FROM staging_adherents
                WHERE NULLIF(TRIM("affiliateNumber"), '') IS NOT NULL
                  AND NULLIF(TRIM("admin_cin"), '') IS NOT NULL
            ) pre_with_cin
            GROUP BY affiliate_number, cin

            UNION ALL

            -- 2) Lignes sans CIN : conservées individuellement (pas de clé de fusion fiable)
            SELECT
                TRIM("affiliateNumber"),
                NULLIF(TRIM("admin_firstName"), ''),
                NULLIF(TRIM("admin_lastName"), ''),
                NULL,
                NULLIF(LOWER(TRIM("admin_email")), ''),
                NULLIF(TRIM("admin_phoneNumber"), ''),
                (TRIM("admin_isRL") = '1')
            FROM staging_adherents
            WHERE NULLIF(TRIM("affiliateNumber"), '') IS NOT NULL
              AND NULLIF(TRIM("admin_cin"), '') IS NULL
              AND (
                    NULLIF(TRIM("admin_firstName"), '') IS NOT NULL
                 OR NULLIF(TRIM("admin_lastName"), '') IS NOT NULL
                 OR NULLIF(TRIM("admin_email"), '') IS NOT NULL
              )
        SQL);
        $admins = (int) DB::selectOne('SELECT COUNT(*) AS c FROM adherent_admins')->c;
        $rlCount = (int) DB::selectOne('SELECT COUNT(*) AS c FROM adherent_admins WHERE is_rl = true')->c;
        $this->line(sprintf('  %s admins insérés en %.1fs (dont %s RL)',
            number_format($admins), microtime(true) - $adminStart, number_format($rlCount)));

        $this->info('→ DROP staging_adherents');
        DB::statement('DROP TABLE staging_adherents');

        $this->info('→ ANALYZE des tables (mise à jour stats planner)');
        DB::statement('ANALYZE adherents');
        DB::statement('ANALYZE adherent_admins');

        $total = microtime(true) - $started;
        $this->newLine();
        $this->info(sprintf('✓ Ingestion terminée en %.1fs', $total));
        $this->table(
            ['Table', 'Lignes'],
            [
                ['adherents', number_format($adh)],
                ['adherent_admins', number_format($admins)],
                ['  dont is_rl=true', number_format($rlCount)],
            ]
        );

        return self::SUCCESS;
    }
}
