import { Head } from '@inertiajs/react';
import {
    RiAlertLine,
    RiBankCardLine,
    RiBriefcaseLine,
    RiBuilding2Line,
    RiFileTextLine,
    RiMapPinLine,
    RiMoneyDollarCircleLine,
    RiTeamLine,
} from '@remixicon/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import type { IconComponent } from '@/types';

type Stats = {
    counts: {
        companies: number;
        admins: number;
        legal_representatives: number;
        employees: number;
        attestations: number;
        salary_rows: number;
    };
    exposure: {
        full_dossiers: number;
        full_dossiers_total_mad: number;
        named_rows: number;
        grand_total_mad: number;
        top_salary_mad: number;
    };
    top_regions_by_companies: Array<{ region: string; companies: number }>;
    top_salaries: Array<{
        full_name: string;
        declared_salary: string;
        immatriculation_number: string;
        raison_sociale: string;
        ville: string | null;
        forme_juridique: string | null;
    }>;
};

const FR = new Intl.NumberFormat('fr-FR');
const MAD = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'MAD',
    maximumFractionDigits: 0,
});

function formatBillionsMad(value: number): string {
    return `${(value / 1_000_000_000).toFixed(2)} Mds MAD`;
}

type StatCardProps = {
    icon: IconComponent;
    label: string;
    value: string;
    hint?: string;
};

function StatCard({ icon: Icon, label, value, hint }: StatCardProps) {
    return (
        <Card size="sm">
            <CardHeader>
                <CardDescription className="flex items-center gap-2">
                    <Icon className="size-4 text-muted-foreground" />
                    {label}
                </CardDescription>
                <CardTitle className="font-heading text-3xl">{value}</CardTitle>
                {hint ? (
                    <CardDescription className="text-xs">{hint}</CardDescription>
                ) : null}
            </CardHeader>
        </Card>
    );
}

export default function Dashboard({ stats }: { stats: Stats }) {
    const { counts, exposure, top_regions_by_companies, top_salaries } = stats;
    const topRegionMax = Math.max(...top_regions_by_companies.map((r) => Number(r.companies)), 1);

    return (
        <>
            <Head title="Dashboard — Leak Explorer" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                {/* Headline exposure card */}
                <Card>
                    <CardHeader>
                        <CardDescription className="flex items-center gap-2">
                            <RiAlertLine className="size-4 text-destructive" />
                            Exposition maximale
                        </CardDescription>
                        <CardTitle className="font-heading text-5xl">
                            {FR.format(exposure.full_dossiers)}
                        </CardTitle>
                        <CardDescription>
                            personnes dont le leak expose simultanément nom, CIN, salaire exact et
                            RIB de l'employeur
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Separator className="mb-6" />
                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                            <div className="flex flex-col gap-1">
                                <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <RiMoneyDollarCircleLine className="size-4" />
                                    Masse salariale exposée
                                </span>
                                <span className="font-heading text-2xl">
                                    {formatBillionsMad(exposure.full_dossiers_total_mad)}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    sur un seul mois (août 2023)
                                </span>
                            </div>
                            <div className="flex flex-col gap-1">
                                <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <RiFileTextLine className="size-4" />
                                    Lignes nominatives totales
                                </span>
                                <span className="font-heading text-2xl">
                                    {FR.format(exposure.named_rows)}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    toutes personnes confondues, avec ou sans CIN
                                </span>
                            </div>
                            <div className="flex flex-col gap-1">
                                <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <RiBankCardLine className="size-4" />
                                    Plus haut salaire révélé
                                </span>
                                <span className="font-heading text-2xl">
                                    {MAD.format(exposure.top_salary_mad)}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    mensuel déclaré
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Stat cards grid */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <StatCard
                        icon={RiBuilding2Line}
                        label="Entreprises"
                        value={FR.format(counts.companies)}
                    />
                    <StatCard
                        icon={RiBriefcaseLine}
                        label="Dirigeants (RL)"
                        value={FR.format(counts.legal_representatives)}
                        hint={`${FR.format(counts.admins)} contacts au total`}
                    />
                    <StatCard
                        icon={RiTeamLine}
                        label="Salariés déclarés"
                        value={FR.format(counts.employees)}
                    />
                    <StatCard
                        icon={RiFileTextLine}
                        label="Attestations PDF"
                        value={FR.format(counts.attestations)}
                    />
                    <StatCard
                        icon={RiMoneyDollarCircleLine}
                        label="Lignes salariales"
                        value={FR.format(counts.salary_rows)}
                    />
                    <StatCard
                        icon={RiAlertLine}
                        label="Masse totale annexe"
                        value={formatBillionsMad(exposure.grand_total_mad)}
                        hint="somme brute tous salariés"
                    />
                </div>

                {/* Two columns: regions + top salaries */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <RiMapPinLine className="size-4 text-muted-foreground" />
                                Top 10 directions régionales
                            </CardTitle>
                            <CardDescription>
                                Nombre d'entreprises affiliées par direction régionale
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-3">
                                {top_regions_by_companies.map((r) => {
                                    const pct = (Number(r.companies) / topRegionMax) * 100;
                                    return (
                                        <div key={r.region} className="flex flex-col gap-1">
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="truncate">{r.region}</span>
                                                <span className="font-medium tabular-nums">
                                                    {FR.format(Number(r.companies))}
                                                </span>
                                            </div>
                                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-primary"
                                                    style={{ width: `${pct}%` }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <RiMoneyDollarCircleLine className="size-4 text-muted-foreground" />
                                Top 10 salaires individuels révélés
                            </CardTitle>
                            <CardDescription>
                                Salaire mensuel déclaré — période août 2023
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pb-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Personne</TableHead>
                                        <TableHead>Entreprise</TableHead>
                                        <TableHead className="text-right">Salaire</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {top_salaries.map((s) => (
                                        <TableRow key={s.immatriculation_number}>
                                            <TableCell className="max-w-[180px] truncate font-medium">
                                                {s.full_name}
                                            </TableCell>
                                            <TableCell className="max-w-[200px] truncate text-muted-foreground">
                                                {s.raison_sociale}
                                                {s.ville ? (
                                                    <span className="ml-1 text-xs">
                                                        · {s.ville}
                                                    </span>
                                                ) : null}
                                            </TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {MAD.format(Number(s.declared_salary))}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
