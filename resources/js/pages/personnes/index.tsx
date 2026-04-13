import { Head, router } from '@inertiajs/react';
import {
    RiFilterOffLine,
    RiMoneyDollarCircleLine,
    RiSearchLine,
    RiShieldLine,
    RiTeamLine,
} from '@remixicon/react';
import { useEffect, useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { personnes } from '@/routes';

type Personne = {
    id: number;
    immatriculation_number: string | null;
    full_name: string | null;
    days_worked: number | null;
    declared_salary: string | null;
    period_year: number;
    period_month: number;
    raison_sociale: string | null;
    ville: string | null;
    file_id: string;
    direction_regionale: string | null;
    agence: string | null;
    bank_account_rib: string | null;
    cin: string | null;
    passport_number: string | null;
    residence_number: string | null;
};

type Facet = { value: string; count: number };

type Filters = {
    q?: string;
    region?: string;
    has_cin?: string;
    min_salary?: string;
    exclude_zero?: string;
    sort: string;
    dir: string;
};

type PageProps = {
    personnes: {
        data: Personne[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: Filters;
    facets: { regions: Facet[] };
};

const FR = new Intl.NumberFormat('fr-FR');
const MAD = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'MAD',
    maximumFractionDigits: 0,
});
const ANY = '__any__';

export default function PersonnesIndex({ personnes: paginator, filters, facets }: PageProps) {
    const [q, setQ] = useState(filters.q ?? '');

    useEffect(() => {
        const handle = setTimeout(() => {
            if ((filters.q ?? '') === q) return;
            router.get(
                personnes().url,
                { ...filters, q: q || undefined, page: 1 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);
        return () => clearTimeout(handle);
    }, [q, filters]);

    const setFilter = (key: keyof Filters, value: string | undefined) => {
        router.get(
            personnes().url,
            { ...filters, [key]: value || undefined, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const toggleSort = (column: 'declared_salary' | 'full_name' | 'days_worked') => {
        const nextDir = filters.sort === column && filters.dir === 'desc' ? 'asc' : 'desc';
        router.get(
            personnes().url,
            { ...filters, sort: column, dir: nextDir, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const clearFilters = () => {
        setQ('');
        router.get(personnes().url, {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    const hasActiveFilter = !!(
        filters.q || filters.region || filters.has_cin || filters.min_salary || filters.exclude_zero
    );

    const sortArrow = (col: string) =>
        filters.sort === col ? (filters.dir === 'asc' ? ' ↑' : ' ↓') : '';

    return (
        <>
            <Head title="Personnes — Leak Explorer" />

            <div className="flex flex-1 flex-col gap-4 p-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <RiTeamLine className="size-5 text-muted-foreground" />
                            Personnes avec salaire déclaré
                        </CardTitle>
                        <CardDescription>
                            {FR.format(paginator.total)} lignes nominatives d'août 2023 extraites
                            des 53&nbsp;574 attestations PDF
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-4">
                            <div className="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center">
                                <div className="relative flex-1 md:max-w-sm">
                                    <RiSearchLine className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        type="search"
                                        placeholder="Nom, entreprise ou début de CIN…"
                                        value={q}
                                        onChange={(e) => setQ(e.target.value)}
                                        className="pl-9"
                                    />
                                </div>

                                <Select
                                    value={filters.region ?? ANY}
                                    onValueChange={(v) => setFilter('region', v === ANY ? undefined : v)}
                                >
                                    <SelectTrigger className="w-full md:w-56">
                                        <SelectValue placeholder="Direction régionale" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value={ANY}>Toutes les régions</SelectItem>
                                            {facets.regions.map((r) => (
                                                <SelectItem key={r.value} value={r.value}>
                                                    {r.value} ({FR.format(r.count)})
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>

                                <Button
                                    variant={filters.exclude_zero ? 'default' : 'outline'}
                                    onClick={() => setFilter('exclude_zero', filters.exclude_zero ? undefined : '1')}
                                >
                                    <RiMoneyDollarCircleLine data-icon="inline-start" />
                                    Salaire {'>'} 0
                                </Button>

                                <Button
                                    variant={filters.has_cin ? 'default' : 'outline'}
                                    onClick={() => setFilter('has_cin', filters.has_cin ? undefined : '1')}
                                >
                                    <RiShieldLine data-icon="inline-start" />
                                    Avec CIN
                                </Button>

                                {hasActiveFilter ? (
                                    <Button variant="ghost" onClick={clearFilters}>
                                        <RiFilterOffLine data-icon="inline-start" />
                                        Réinitialiser
                                    </Button>
                                ) : null}
                            </div>

                            {paginator.data.length === 0 ? (
                                <Empty>
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <RiTeamLine />
                                        </EmptyMedia>
                                        <EmptyTitle>Aucun résultat</EmptyTitle>
                                        <EmptyDescription>
                                            Élargis les filtres ou la recherche.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                    <EmptyContent>
                                        <Button variant="outline" onClick={clearFilters}>
                                            Réinitialiser les filtres
                                        </Button>
                                    </EmptyContent>
                                </Empty>
                            ) : (
                                <div className="rounded-xl ring-1 ring-foreground/10">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    <button
                                                        type="button"
                                                        className="font-medium"
                                                        onClick={() => toggleSort('full_name')}
                                                    >
                                                        Nom{sortArrow('full_name')}
                                                    </button>
                                                </TableHead>
                                                <TableHead>Entreprise</TableHead>
                                                <TableHead>Ville</TableHead>
                                                <TableHead className="text-right">
                                                    <button
                                                        type="button"
                                                        className="font-medium"
                                                        onClick={() => toggleSort('days_worked')}
                                                    >
                                                        Jours{sortArrow('days_worked')}
                                                    </button>
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    <button
                                                        type="button"
                                                        className="font-medium"
                                                        onClick={() => toggleSort('declared_salary')}
                                                    >
                                                        Salaire{sortArrow('declared_salary')}
                                                    </button>
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {paginator.data.map((p) => (
                                                <TableRow key={p.id}>
                                                    <TableCell className="max-w-[260px] truncate font-medium">
                                                        {p.full_name ?? '—'}
                                                    </TableCell>
                                                    <TableCell className="max-w-[260px] truncate text-muted-foreground">
                                                        {p.raison_sociale ?? '—'}
                                                    </TableCell>
                                                    <TableCell className="max-w-[140px] truncate text-muted-foreground">
                                                        {p.ville ?? '—'}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums text-muted-foreground">
                                                        {p.days_worked ?? '—'}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {p.declared_salary !== null ? (
                                                            Number(p.declared_salary) > 0 ? (
                                                                <Badge variant="secondary">
                                                                    {MAD.format(Number(p.declared_salary))}
                                                                </Badge>
                                                            ) : (
                                                                <span className="text-muted-foreground">
                                                                    0 MAD
                                                                </span>
                                                            )
                                                        ) : (
                                                            <span className="text-muted-foreground">—</span>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}

                            <div className="flex flex-col items-center justify-between gap-3 md:flex-row">
                                <span className="text-xs text-muted-foreground">
                                    {paginator.from && paginator.to
                                        ? `Lignes ${FR.format(paginator.from)}-${FR.format(paginator.to)} sur ${FR.format(paginator.total)}`
                                        : '0 résultat'}
                                </span>
                                <DataPagination paginator={paginator} queryParams={filters} />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PersonnesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Personnes', href: personnes().url },
    ],
};
