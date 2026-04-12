<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'stats' => Cache::rememberForever('dashboard:stats', fn () => $this->computeStats()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function computeStats(): array
    {
        return [
            'counts' => [
                'companies' => (int) DB::scalar('SELECT COUNT(*) FROM adherents'),
                'admins' => (int) DB::scalar('SELECT COUNT(*) FROM adherent_admins'),
                'legal_representatives' => (int) DB::scalar('SELECT COUNT(*) FROM adherent_admins WHERE is_rl = true'),
                'employees' => (int) DB::scalar('SELECT COUNT(*) FROM salaries'),
                'attestations' => (int) DB::scalar('SELECT COUNT(*) FROM attestations'),
                'salary_rows' => (int) DB::scalar('SELECT COUNT(*) FROM attestation_salaries'),
            ],
            'exposure' => $this->computeExposure(),
            'top_regions_by_companies' => DB::select(<<<'SQL'
                SELECT direction_regionale AS region, COUNT(*) AS companies
                FROM adherents
                WHERE direction_regionale IS NOT NULL
                GROUP BY direction_regionale
                ORDER BY companies DESC
                LIMIT 10
            SQL),
            'top_salaries' => DB::select(<<<'SQL'
                SELECT
                    ats.full_name,
                    ats.declared_salary,
                    ats.immatriculation_number,
                    at.raison_sociale,
                    at.ville,
                    at.forme_juridique
                FROM attestation_salaries ats
                JOIN attestations at ON at.id = ats.attestation_id
                WHERE ats.immatriculation_number IS NOT NULL
                ORDER BY ats.declared_salary DESC NULLS LAST
                LIMIT 10
            SQL),
        ];
    }

    /**
     * Full-PII dossiers: personne avec nom + CIN + salaire exact + RIB employeur.
     *
     * @return array<string, mixed>
     */
    private function computeExposure(): array
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT
                COUNT(*) AS full_dossiers,
                COALESCE(SUM(ats.declared_salary), 0) AS total_salary_mad
            FROM attestation_salaries ats
            JOIN salaries s      ON s.immatriculation_number = ats.immatriculation_number
            JOIN attestations at ON at.id = ats.attestation_id
            JOIN adherents a     ON a.affiliate_number = at.affiliate_number
            WHERE ats.immatriculation_number IS NOT NULL
              AND s.cin IS NOT NULL
              AND a.bank_account_rib IS NOT NULL
        SQL);

        $grand = DB::selectOne(<<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE immatriculation_number IS NOT NULL) AS named_rows,
                COALESCE(SUM(declared_salary), 0) AS grand_total_salary_mad,
                MAX(declared_salary) AS top_salary
            FROM attestation_salaries
        SQL);

        return [
            'full_dossiers' => (int) $row->full_dossiers,
            'full_dossiers_total_mad' => (float) $row->total_salary_mad,
            'named_rows' => (int) $grand->named_rows,
            'grand_total_mad' => (float) $grand->grand_total_salary_mad,
            'top_salary_mad' => (float) $grand->top_salary,
        ];
    }
}
