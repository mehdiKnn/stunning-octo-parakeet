import { Head, router } from '@inertiajs/react';
import { RiBankCardLine, RiBuilding2Line, RiFilterOffLine, RiSearchLine } from '@remixicon/react';
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
import { entreprises } from '@/routes';

type Entreprise = {
    id: number;
    affiliate_number: string;
    company_name: string;
    direction_regionale: string | null;
    agence: string | null;
    type_adherent: string | null;
    date_affiliation: string | null;
    bank_account_rib: string | null;
};

type Facet = { value: string; count: number };

type Filters = {
    q?: string;
    region?: string;
    type?: string;
    has_rib?: string;
    sort: string;
    dir: string;
};

type PageProps = {
    entreprises: {
        data: Entreprise[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: Filters;
    facets: {
        regions: Facet[];
        types: Facet[];
    };
};

const FR = new Intl.NumberFormat('fr-FR');
const ANY = '__any__';

export default function EntreprisesIndex({ entreprises: paginator, filters, facets }: PageProps) {
    const [q, setQ] = useState(filters.q ?? '');

    useEffect(() => {
        const handle = setTimeout(() => {
            if ((filters.q ?? '') === q) return;
            router.get(
                entreprises().url,
                { ...filters, q: q || undefined, page: 1 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);
        return () => clearTimeout(handle);
    }, [q, filters]);

    const setFilter = (key: keyof Filters, value: string | undefined) => {
        router.get(
            entreprises().url,
            { ...filters, [key]: value || undefined, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const clearFilters = () => {
        setQ('');
        router.get(entreprises().url, {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    const hasActiveFilter = !!(filters.q || filters.region || filters.type || filters.has_rib);

    return (
        <>
            <Head title="Entreprises — Leak Explorer" />

            <div className="flex flex-1 flex-col gap-4 p-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <RiBuilding2Line className="size-5 text-muted-foreground" />
                            Entreprises
                        </CardTitle>
                        <CardDescription>
                            {FR.format(paginator.total)} résultats sur 499 871 entreprises affiliées CNSS
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-4">
                            <div className="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center">
                                <div className="relative flex-1 md:max-w-sm">
                                    <RiSearchLine className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        type="search"
                                        placeholder="Rechercher une raison sociale…"
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

                                <Select
                                    value={filters.type ?? ANY}
                                    onValueChange={(v) => setFilter('type', v === ANY ? undefined : v)}
                                >
                                    <SelectTrigger className="w-full md:w-48">
                                        <SelectValue placeholder="Type adhérent" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value={ANY}>Tous les types</SelectItem>
                                            {facets.types.map((t) => (
                                                <SelectItem key={t.value} value={t.value}>
                                                    {t.value} ({FR.format(t.count)})
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>

                                <Button
                                    variant={filters.has_rib ? 'default' : 'outline'}
                                    onClick={() => setFilter('has_rib', filters.has_rib ? undefined : '1')}
                                >
                                    <RiBankCardLine data-icon="inline-start" />
                                    Avec RIB
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
                                            <RiBuilding2Line />
                                        </EmptyMedia>
                                        <EmptyTitle>Aucune entreprise ne correspond</EmptyTitle>
                                        <EmptyDescription>
                                            Essaie d'élargir les filtres ou la recherche.
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
                                                <TableHead>Raison sociale</TableHead>
                                                <TableHead>N° affilié</TableHead>
                                                <TableHead>Direction régionale</TableHead>
                                                <TableHead>Agence</TableHead>
                                                <TableHead>Type</TableHead>
                                                <TableHead>RIB</TableHead>
                                                <TableHead>Date affil.</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {paginator.data.map((e) => (
                                                <TableRow key={e.id}>
                                                    <TableCell className="max-w-[280px] truncate font-medium">
                                                        {e.company_name}
                                                    </TableCell>
                                                    <TableCell className="font-mono text-xs text-muted-foreground">
                                                        {e.affiliate_number}
                                                    </TableCell>
                                                    <TableCell className="max-w-[180px] truncate text-muted-foreground">
                                                        {e.direction_regionale ?? '—'}
                                                    </TableCell>
                                                    <TableCell className="max-w-[140px] truncate text-muted-foreground">
                                                        {e.agence ?? '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {e.type_adherent ? (
                                                            <Badge variant="secondary">{e.type_adherent}</Badge>
                                                        ) : (
                                                            <span className="text-muted-foreground">—</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="font-mono text-xs">
                                                        {e.bank_account_rib ? (
                                                            <span>{e.bank_account_rib.slice(0, 6)}…{e.bank_account_rib.slice(-4)}</span>
                                                        ) : (
                                                            <span className="text-muted-foreground">—</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground">
                                                        {e.date_affiliation ?? '—'}
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

EntreprisesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Entreprises', href: entreprises().url },
    ],
};
