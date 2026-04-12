<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EntreprisesController extends Controller
{
    private const PER_PAGE = 50;

    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:200',
            'region' => 'nullable|string|max:150',
            'type' => 'nullable|string|max:50',
            'has_rib' => 'nullable|in:1',
            'sort' => 'nullable|in:company_name,direction_regionale,date_affiliation',
            'dir' => 'nullable|in:asc,desc',
        ]);

        $sort = $filters['sort'] ?? 'company_name';
        $dir = $filters['dir'] ?? 'asc';
        $page = max(1, (int) $request->input('page', 1));

        $base = DB::table('adherents');

        if (! empty($filters['q'])) {
            $base->where('company_name', 'ILIKE', '%'.$filters['q'].'%');
        }
        if (! empty($filters['region'])) {
            $base->where('direction_regionale', $filters['region']);
        }
        if (! empty($filters['type'])) {
            $base->where('type_adherent', $filters['type']);
        }
        if (! empty($filters['has_rib'])) {
            $base->whereNotNull('bank_account_rib');
        }

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->select([
                'id',
                'affiliate_number',
                'company_name',
                'direction_regionale',
                'agence',
                'type_adherent',
                'date_affiliation',
                'bank_account_rib',
            ])
            ->orderBy($sort, $dir)
            ->orderBy('id')
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

        return Inertia::render('entreprises/index', [
            'entreprises' => $paginator,
            'filters' => $filters + ['sort' => $sort, 'dir' => $dir],
            'facets' => Cache::rememberForever('entreprises:facets', fn () => [
                'regions' => DB::select(<<<'SQL'
                    SELECT direction_regionale AS value, COUNT(*) AS count
                    FROM adherents
                    WHERE direction_regionale IS NOT NULL
                    GROUP BY direction_regionale
                    ORDER BY count DESC
                SQL),
                'types' => DB::select(<<<'SQL'
                    SELECT type_adherent AS value, COUNT(*) AS count
                    FROM adherents
                    WHERE type_adherent IS NOT NULL
                    GROUP BY type_adherent
                    ORDER BY count DESC
                SQL),
            ]),
        ]);
    }
}
