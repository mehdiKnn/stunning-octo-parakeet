<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

#[Signature('ingest:attestations {--force : Skip confirmation} {--skip-parse : Reuse existing TSVs in /tmp}')]
#[Description('Parse 53k PDFs (parallel Python) then bulk-load into attestations + months + salaries tables.')]
class IngestAttestations extends Command
{
    private const TMP_DIR = '/tmp/leak_explorer_tsv';

    public function handle(): int
    {
        $existing = (int) DB::selectOne('SELECT COUNT(*) AS c FROM attestations')->c;
        if ($existing > 0 && ! $this->option('force')) {
            if (! $this->confirm("attestations contient déjà {$existing} lignes. Tout recharger ?", false)) {
                $this->warn('Abandonné.');

                return self::FAILURE;
            }
        }

        $started = microtime(true);

        if (! $this->option('skip-parse')) {
            $this->info('→ Parsing parallèle des PDFs (Python + multiprocessing)');
            $parseStart = microtime(true);
            @mkdir(self::TMP_DIR, 0755, true);
            $script = base_path('scripts/export_pdfs_to_tsv.py');
            $result = Process::timeout(900)->run(['python3', $script, self::TMP_DIR]);
            if ($result->failed()) {
                $this->error('Script Python a échoué : '.$result->errorOutput());

                return self::FAILURE;
            }
            $this->line('  '.trim($result->errorOutput()));
            $this->line(sprintf('  parsing en %.1fs', microtime(true) - $parseStart));
        } else {
            $this->warn('→ skip-parse : réutilisation des TSVs dans '.self::TMP_DIR);
        }

        $attTsv = self::TMP_DIR.'/attestations.tsv';
        $monTsv = self::TMP_DIR.'/attestation_months.tsv';
        $salTsv = self::TMP_DIR.'/attestation_salaries.tsv';

        foreach ([$attTsv, $monTsv, $salTsv] as $f) {
            if (! is_readable($f)) {
                $this->error("TSV manquant : {$f}");

                return self::FAILURE;
            }
        }

        $this->info('→ TRUNCATE des 3 tables cibles');
        DB::statement('TRUNCATE attestations, attestation_months, attestation_salaries RESTART IDENTITY CASCADE');

        $this->info('→ Création des staging tables (UNLOGGED)');
        DB::statement('DROP TABLE IF EXISTS staging_attestations, staging_attestation_months, staging_attestation_salaries');
        DB::statement(<<<'SQL'
            CREATE UNLOGGED TABLE staging_attestations (
                file_id               text,
                attestation_number    text,
                affiliate_number      text,
                raison_sociale        text,
                activite              text,
                adresse               text,
                ville                 text,
                ice                   text,
                registre_commerce     text,
                taxe_professionnelle  text,
                identifiant_fiscal    text,
                forme_juridique       text,
                period_start          text,
                period_end            text,
                delivered_at          text,
                parse_ok              text
            )
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNLOGGED TABLE staging_attestation_months (
                file_id          text,
                year             text,
                month            text,
                nb_salaries      text,
                masse_salariale  text
            )
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNLOGGED TABLE staging_attestation_salaries (
                file_id                 text,
                period_year             text,
                period_month            text,
                immatriculation_number  text,
                full_name               text,
                days_worked             text,
                declared_salary         text
            )
        SQL);

        $this->info('→ COPY FROM 3 TSVs');
        $copyStart = microtime(true);
        $this->copyTsv($attTsv, 'staging_attestations');
        $this->copyTsv($monTsv, 'staging_attestation_months');
        $this->copyTsv($salTsv, 'staging_attestation_salaries');
        $stAtt = (int) DB::selectOne('SELECT COUNT(*) AS c FROM staging_attestations')->c;
        $stMon = (int) DB::selectOne('SELECT COUNT(*) AS c FROM staging_attestation_months')->c;
        $stSal = (int) DB::selectOne('SELECT COUNT(*) AS c FROM staging_attestation_salaries')->c;
        $this->line(sprintf(
            '  attestations=%s months=%s salaries=%s en %.1fs',
            number_format($stAtt), number_format($stMon), number_format($stSal),
            microtime(true) - $copyStart
        ));

        $this->info('→ INSERT INTO attestations (typage + index)');
        $step = microtime(true);
        DB::statement(<<<'SQL'
            INSERT INTO attestations (
                file_id, attestation_number, affiliate_number, raison_sociale,
                activite, adresse, ville, ice, registre_commerce,
                taxe_professionnelle, identifiant_fiscal, forme_juridique,
                period_start, period_end, delivered_at, parse_ok
            )
            SELECT
                file_id,
                NULLIF(attestation_number, ''),
                NULLIF(affiliate_number, ''),
                NULLIF(raison_sociale, ''),
                NULLIF(activite, ''),
                NULLIF(adresse, ''),
                NULLIF(ville, ''),
                NULLIF(ice, ''),
                NULLIF(registre_commerce, ''),
                NULLIF(taxe_professionnelle, ''),
                NULLIF(identifiant_fiscal, ''),
                NULLIF(forme_juridique, ''),
                NULLIF(period_start, '')::date,
                NULLIF(period_end, '')::date,
                NULLIF(delivered_at, '')::date,
                COALESCE(parse_ok = 'true', false)
            FROM staging_attestations
            WHERE file_id IS NOT NULL AND file_id <> ''
        SQL);
        $att = (int) DB::selectOne('SELECT COUNT(*) AS c FROM attestations')->c;
        $this->line(sprintf('  %s attestations en %.1fs', number_format($att), microtime(true) - $step));

        $this->info('→ INSERT INTO attestation_months (JOIN sur file_id)');
        $step = microtime(true);
        DB::statement(<<<'SQL'
            INSERT INTO attestation_months (attestation_id, year, month, nb_salaries, masse_salariale)
            SELECT
                a.id,
                sm.year::smallint,
                sm.month::smallint,
                NULLIF(sm.nb_salaries, '')::int,
                NULLIF(sm.masse_salariale, '')::numeric
            FROM staging_attestation_months sm
            JOIN attestations a ON a.file_id = sm.file_id
        SQL);
        $mon = (int) DB::selectOne('SELECT COUNT(*) AS c FROM attestation_months')->c;
        $this->line(sprintf('  %s lignes months en %.1fs', number_format($mon), microtime(true) - $step));

        $this->info('→ INSERT INTO attestation_salaries (JOIN sur file_id)');
        $step = microtime(true);
        DB::statement(<<<'SQL'
            INSERT INTO attestation_salaries (
                attestation_id, period_year, period_month,
                immatriculation_number, full_name, days_worked, declared_salary
            )
            SELECT
                a.id,
                ss.period_year::smallint,
                ss.period_month::smallint,
                NULLIF(ss.immatriculation_number, ''),
                NULLIF(ss.full_name, ''),
                NULLIF(ss.days_worked, '')::int,
                NULLIF(ss.declared_salary, '')::numeric
            FROM staging_attestation_salaries ss
            JOIN attestations a ON a.file_id = ss.file_id
        SQL);
        $sal = (int) DB::selectOne('SELECT COUNT(*) AS c FROM attestation_salaries')->c;
        $this->line(sprintf('  %s lignes salaries en %.1fs', number_format($sal), microtime(true) - $step));

        $this->info('→ DROP stagings');
        DB::statement('DROP TABLE staging_attestations, staging_attestation_months, staging_attestation_salaries');

        $this->info('→ ANALYZE');
        DB::statement('ANALYZE attestations');
        DB::statement('ANALYZE attestation_months');
        DB::statement('ANALYZE attestation_salaries');

        $total = microtime(true) - $started;
        $this->newLine();
        $this->info(sprintf('✓ Ingestion terminée en %.1fs', $total));
        $this->table(
            ['Table', 'Lignes'],
            [
                ['attestations', number_format($att)],
                ['attestation_months', number_format($mon)],
                ['attestation_salaries', number_format($sal)],
            ]
        );

        return self::SUCCESS;
    }

    private function copyTsv(string $file, string $table): void
    {
        $escaped = str_replace("'", "''", $file);
        DB::statement("COPY {$table} FROM '{$escaped}' WITH (FORMAT csv, DELIMITER E'\\t', NULL '', ENCODING 'UTF8')");
    }
}
