<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PersonnesController extends Controller
{
    private const PER_PAGE = 50;

    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:200',
            'region' => 'nullable|string|max:150',
            'has_cin' => 'nullable|in:1',
            'min_salary' => 'nullable|numeric|min:0',
            'exclude_zero' => 'nullable|in:1',
            'sort' => 'nullable|in:declared_salary,full_name,days_worked',
            'dir' => 'nullable|in:asc,desc',
        ]);

        $sort = $filters['sort'] ?? 'declared_salary';
        $dir = $filters['dir'] ?? 'desc';
        $page = max(1, (int) $request->input('page', 1));

        $base = DB::table('attestation_salaries AS ats')
            ->join('attestations AS at', 'at.id', '=', 'ats.attestation_id')
            ->leftJoin('adherents AS a', 'a.affiliate_number', '=', 'at.affiliate_number')
            ->leftJoin('salaries AS s', 's.immatriculation_number', '=', 'ats.immatriculation_number');

        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $base->where(function ($w) use ($q) {
                $w->where('ats.full_name', 'ILIKE', '%'.$q.'%')
                  ->orWhere('at.raison_sociale', 'ILIKE', '%'.$q.'%')
                  ->orWhere('s.cin', 'ILIKE', $q.'%');
            });
        }
        if (! empty($filters['region'])) {
            $base->where('a.direction_regionale', $filters['region']);
        }
        if (! empty($filters['has_cin'])) {
            $base->whereNotNull('s.cin');
        }
        if (isset($filters['min_salary']) && $filters['min_salary'] !== null && $filters['min_salary'] !== '') {
            $base->where('ats.declared_salary', '>=', (float) $filters['min_salary']);
        }
        if (! empty($filters['exclude_zero'])) {
            $base->where('ats.declared_salary', '>', 0);
        }

        $total = (clone $base)->count();

        $sortColumn = match ($sort) {
            'declared_salary' => 'ats.declared_salary',
            'full_name' => 'ats.full_name',
            'days_worked' => 'ats.days_worked',
        };

        $rows = (clone $base)
            ->select([
                'ats.id',
                'ats.immatriculation_number',
                'ats.full_name',
                'ats.days_worked',
                'ats.declared_salary',
                'ats.period_year',
                'ats.period_month',
                'at.raison_sociale',
                'at.ville',
                'at.file_id',
                'a.direction_regionale',
                'a.agence',
                'a.bank_account_rib',
                's.cin',
                's.passport_number',
                's.residence_number',
            ])
            ->orderBy($sortColumn, $dir)
            ->orderBy('ats.id')
            ->forPage($page, self::PER_PAGE)
            ->get();

        $paginator = new LengthAwarePaginator(
            $rows,
            $total,
            self::PER_PAGE,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return Inertia::render('personnes/index', [
            'personnes' => $paginator,
            'filters' => $filters + ['sort' => $sort, 'dir' => $dir],
            'facets' => Cache::rememberForever('personnes:facets:v2', fn () => [
                'regions' => DB::select(<<<'SQL'
                    SELECT a.direction_regionale AS value, COUNT(*) AS count
                    FROM attestation_salaries ats
                    JOIN attestations at ON at.id = ats.attestation_id
                    JOIN adherents a ON a.affiliate_number = at.affiliate_number
                    WHERE a.direction_regionale IS NOT NULL
                    GROUP BY a.direction_regionale
                    ORDER BY count DESC
                SQL),
            ]),
        ]);
    }
}
