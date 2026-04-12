import { router } from '@inertiajs/react';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';

type Paginator = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

function visiblePages(current: number, last: number): Array<number | 'ellipsis-left' | 'ellipsis-right'> {
    const pages: Array<number | 'ellipsis-left' | 'ellipsis-right'> = [];
    const push = (p: number) => pages.push(p);

    push(1);
    if (last <= 1) return pages;

    const start = Math.max(2, current - 2);
    const end = Math.min(last - 1, current + 2);

    if (start > 2) pages.push('ellipsis-left');
    for (let i = start; i <= end; i++) push(i);
    if (end < last - 1) pages.push('ellipsis-right');

    if (last > 1) push(last);
    return pages;
}

export function DataPagination({
    paginator,
    queryParams,
}: {
    paginator: Paginator;
    queryParams: Record<string, string | number | undefined | null>;
}) {
    if (paginator.last_page <= 1) return null;

    const go = (page: number) => (e: React.MouseEvent) => {
        e.preventDefault();
        router.get(
            window.location.pathname,
            { ...queryParams, page },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const pages = visiblePages(paginator.current_page, paginator.last_page);
    const prev = Math.max(1, paginator.current_page - 1);
    const next = Math.min(paginator.last_page, paginator.current_page + 1);

    return (
        <Pagination>
            <PaginationContent>
                <PaginationItem>
                    <PaginationPrevious
                        href="#"
                        onClick={go(prev)}
                        aria-disabled={paginator.current_page === 1}
                    />
                </PaginationItem>
                {pages.map((p) =>
                    typeof p === 'number' ? (
                        <PaginationItem key={p}>
                            <PaginationLink
                                href="#"
                                onClick={go(p)}
                                isActive={p === paginator.current_page}
                            >
                                {p}
                            </PaginationLink>
                        </PaginationItem>
                    ) : (
                        <PaginationItem key={p}>
                            <PaginationEllipsis />
                        </PaginationItem>
                    ),
                )}
                <PaginationItem>
                    <PaginationNext
                        href="#"
                        onClick={go(next)}
                        aria-disabled={paginator.current_page === paginator.last_page}
                    />
                </PaginationItem>
            </PaginationContent>
        </Pagination>
    );
}
