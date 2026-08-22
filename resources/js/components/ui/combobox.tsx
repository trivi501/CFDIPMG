import { Check, Loader2, Plus } from 'lucide-react';
import * as React from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type ComboboxOption = { value: string; label: string };

function Combobox({
    id,
    options,
    value,
    onChange,
    onCreate,
    placeholder = 'Buscar...',
    emptyText = 'Sin resultados.',
    createLabel,
    disabled,
    className,
}: {
    id?: string;
    options: ComboboxOption[];
    value: string;
    onChange: (value: string) => void;
    onCreate?: (query: string) => Promise<ComboboxOption | null>;
    placeholder?: string;
    emptyText?: string;
    createLabel?: (query: string) => string;
    disabled?: boolean;
    className?: string;
}) {
    const selected = options.find((option) => option.value === value) ?? null;
    const [open, setOpen] = React.useState(false);
    const [query, setQuery] = React.useState(selected?.label ?? '');
    const [creating, setCreating] = React.useState(false);
    const containerRef = React.useRef<HTMLDivElement>(null);

    React.useEffect(() => {
        setQuery(selected?.label ?? '');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    const normalizedQuery = query.trim().toLowerCase();
    const filtered = normalizedQuery
        ? options.filter((option) =>
              option.label.toLowerCase().includes(normalizedQuery),
          )
        : options;
    const hasExactMatch = options.some(
        (option) => option.label.toLowerCase() === normalizedQuery,
    );

    const select = (option: ComboboxOption) => {
        onChange(option.value);
        setQuery(option.label);
        setOpen(false);
    };

    const create = async () => {
        const label = query.trim();
        if (!onCreate || !label || creating) return;
        setCreating(true);
        try {
            const option = await onCreate(label);
            if (option) select(option);
        } finally {
            setCreating(false);
        }
    };

    return (
        <div
            ref={containerRef}
            className="relative"
            onBlur={(e) => {
                if (
                    containerRef.current &&
                    e.relatedTarget instanceof Node &&
                    containerRef.current.contains(e.relatedTarget)
                ) {
                    return;
                }
                setOpen(false);
                setQuery(selected?.label ?? '');
            }}
        >
            <Input
                id={id}
                value={query}
                placeholder={placeholder}
                disabled={disabled}
                autoComplete="off"
                onFocus={() => setOpen(true)}
                onChange={(e) => {
                    setQuery(e.target.value);
                    setOpen(true);
                }}
                onKeyDown={(e) => {
                    if (e.key === 'Escape') {
                        setOpen(false);
                        setQuery(selected?.label ?? '');
                    }
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (filtered.length > 0) {
                            select(filtered[0]);
                        } else if (onCreate && query.trim()) {
                            void create();
                        }
                    }
                }}
                className={className}
            />

            {open && !disabled && (
                <div className="bg-popover text-popover-foreground absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border p-1 shadow-md">
                    {filtered.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => select(option)}
                            className={cn(
                                'hover:bg-accent hover:text-accent-foreground flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm',
                                option.value === value && 'font-medium',
                            )}
                        >
                            <Check
                                className={cn(
                                    'size-4 shrink-0',
                                    option.value === value
                                        ? 'opacity-100'
                                        : 'opacity-0',
                                )}
                            />
                            {option.label}
                        </button>
                    ))}

                    {filtered.length === 0 && !onCreate && (
                        <p className="text-muted-foreground px-2 py-1.5 text-sm">
                            {emptyText}
                        </p>
                    )}

                    {onCreate && query.trim() && !hasExactMatch && (
                        <button
                            type="button"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => void create()}
                            disabled={creating}
                            className="text-primary hover:bg-accent flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm disabled:opacity-50"
                        >
                            {creating ? (
                                <Loader2 className="size-4 shrink-0 animate-spin" />
                            ) : (
                                <Plus className="size-4 shrink-0" />
                            )}
                            {createLabel
                                ? createLabel(query.trim())
                                : `Crear "${query.trim()}"`}
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

export { Combobox };
