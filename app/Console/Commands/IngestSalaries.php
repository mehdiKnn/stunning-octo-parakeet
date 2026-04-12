<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

#[Signature('ingest:salaries {csv? : Path to SALARIES.csv} {--force : Skip confirmation}')]
#[Description('Load SALARIES.csv into salaries table via Postgres COPY FROM.')]
class IngestSalaries extends Command
{
    private const DEFAULT_CSV = '/Users/mehdik/Documents/Maroc/Salaire Maroc/SALARIES.csv';

    public function handle(): int
    {
        $csv = $this->argument('csv') ?: self::DEFAULT_CSV;

        if (! is_readable($csv)) {
            $this->error("CSV introuvable ou non lisible : {$csv}");

            return self::FAILURE;
        }

        $this->info("Source  : {$csv}");
        $this->info('Cible   : salaries');

        $existing = (int) DB::selectOne('SELECT COUNT(*) AS c FROM salaries')->c;
        if ($existing > 0 && ! $this->option('force')) {
            if (! $this->confirm("La table salaries contient déjà {$existing} lignes. Truncate et recharger ?", false)) {
                $this->warn('Abandonné.');

                return self::FAILURE;
            }
        }

        $started = microtime(true);

        $this->info('→ TRUNCATE salaries');
        DB::statement('TRUNCATE salaries RESTART IDENTITY CASCADE');

        $this->info('→ Création de la table staging (UNLOGGED)');
        DB::statement('DROP TABLE IF EXISTS staging_salaries');
        DB::statement(<<<'SQL'
            CREATE UNLOGGED TABLE staging_salaries (
                "ID_adherent"            text,
                "newImmatriculatedId"    text,
                "firstName"              text,
                "lastName"               text,
                "immatriculationNumber"  text,
                "cin"                    text,
                "passportNumber"         text,
                "residenceNumber"        text,
                "creationDate"           text,
                "demandMode"             text,
                "affiliateName"          text,
                "affiliateNumber"        text,
                "demandState"            text
            )
        SQL);

        $this->info('→ Pré-nettoyage Python du CSV (source mal quotée) vers TSV');
        $cleanStart = microtime(true);
        $tmp = tempnam(sys_get_temp_dir(), 'salaries_') . '.tsv';
        $cleaner = base_path('scripts/clean_salaries_csv.py');
        $cleanResult = Process::run(['python3', $cleaner, $csv, $tmp]);
        if ($cleanResult->failed()) {
            @unlink($tmp);
            $this->error('Cleaner Python a échoué : ' . $cleanResult->errorOutput());

            return self::FAILURE;
        }
        $this->line('  ' . trim($cleanResult->errorOutput()) . sprintf(' en %.1fs', microtime(true) - $cleanStart));

        $this->info('→ COPY FROM TSV propre');
        $copyStart = microtime(true);
        $escaped = str_replace("'", "''", $tmp);
        DB::statement("COPY staging_salaries FROM '{$escaped}' WITH (FORMAT csv, DELIMITER E'\\t', ENCODING 'UTF8')");
        @unlink($tmp);
        $staging = (int) DB::selectOne('SELECT COUNT(*) AS c FROM staging_salaries')->c;
        $this->line(sprintf('  %s lignes chargées en %.1fs', number_format($staging), microtime(true) - $copyStart));

        $this->info('→ INSERT INTO salaries (nettoyage "None" + dédup sur immat)');
        $insertStart = microtime(true);
        DB::statement(<<<'SQL'
            INSERT INTO salaries (
                immatriculation_number, new_immatriculated_id, affiliate_number,
                id_adherent, first_name, last_name, cin, passport_number,
                residence_number, creation_date, demand_mode, demand_state
            )
            SELECT DISTINCT ON (NULLIF(NULLIF(TRIM("immatriculationNumber"), ''), 'None'))
                NULLIF(NULLIF(TRIM("immatriculationNumber"), ''), 'None'),
                NULLIF(NULLIF(TRIM("newImmatriculatedId"), ''), 'None'),
                NULLIF(NULLIF(TRIM("affiliateNumber"), ''), 'None'),
                NULLIF(NULLIF(TRIM("ID_adherent"), ''), 'None')::int,
                NULLIF(NULLIF(TRIM("firstName"), ''), 'None'),
                NULLIF(NULLIF(TRIM("lastName"), ''), 'None'),
                NULLIF(NULLIF(TRIM("cin"), ''), 'None'),
                NULLIF(NULLIF(TRIM("passportNumber"), ''), 'None'),
                NULLIF(NULLIF(TRIM("residenceNumber"), ''), 'None'),
                NULLIF(NULLIF(TRIM("creationDate"), ''), 'None')::date,
                NULLIF(NULLIF(TRIM("demandMode"), ''), 'None'),
                NULLIF(NULLIF(TRIM("demandState"), ''), 'None')
            FROM staging_salaries
            WHERE NULLIF(NULLIF(TRIM("immatriculationNumber"), ''), 'None') IS NOT NULL
            ORDER BY NULLIF(NULLIF(TRIM("immatriculationNumber"), ''), 'None')
        SQL);
        $inserted = (int) DB::selectOne('SELECT COUNT(*) AS c FROM salaries')->c;
        $dropped = $staging - $inserted;
        $this->line(sprintf(
            '  %s salariés insérés en %.1fs (%s doublons ou immat vides ignorés)',
            number_format($inserted),
            microtime(true) - $insertStart,
            number_format($dropped),
        ));

        $this->info('→ DROP staging_salaries');
        DB::statement('DROP TABLE staging_salaries');

        $this->info('→ ANALYZE salaries');
        DB::statement('ANALYZE salaries');

        $this->info('→ Statistiques post-ingestion');
        $stats = DB::selectOne(<<<'SQL'
            SELECT
                COUNT(*)                                             AS total,
                COUNT(*) FILTER (WHERE cin IS NOT NULL)              AS avec_cin,
                COUNT(*) FILTER (WHERE passport_number IS NOT NULL)  AS avec_passeport,
                COUNT(*) FILTER (WHERE residence_number IS NOT NULL) AS avec_residence,
                COUNT(*) FILTER (WHERE affiliate_number IS NOT NULL) AS avec_employeur,
                COUNT(DISTINCT affiliate_number)                     AS employeurs_distincts
            FROM salaries
        SQL);

        $total = microtime(true) - $started;
        $this->newLine();
        $this->info(sprintf('✓ Ingestion terminée en %.1fs', $total));
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Lignes insérées', number_format($stats->total)],
                ['Avec CIN', number_format($stats->avec_cin)],
                ['Avec passeport', number_format($stats->avec_passeport)],
                ['Avec carte résidence', number_format($stats->avec_residence)],
                ['Avec employeur rattaché', number_format($stats->avec_employeur)],
                ['Employeurs distincts', number_format($stats->employeurs_distincts)],
            ]
        );

        return self::SUCCESS;
    }
}
