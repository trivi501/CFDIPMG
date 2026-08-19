import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginator<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

export function Pagination({ paginator }: { paginator: Paginator<unknown> }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-4 px-1 py-3">
            <p className="text-sm text-muted-foreground">
                {paginator.total > 0
                    ? `Mostrando ${paginator.from}–${paginator.to} de ${paginator.total}`
                    : 'Sin resultados'}
            </p>
            <nav className="flex flex-wrap items-center gap-1">
                {paginator.links.map((link, index) => (
                    <Link
                        key={index}
                        href={link.url ?? '#'}
                        preserveScroll
                        className={cn(
                            'rounded-md border px-3 py-1.5 text-sm transition-colors',
                            link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'hover:bg-accent',
                            !link.url && 'pointer-events-none opacity-40',
                        )}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </nav>
        </div>
    );
}
