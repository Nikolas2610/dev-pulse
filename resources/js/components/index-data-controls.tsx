import type { ReactNode } from 'react';
import { Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';

type SortOption = { label: string; value: string; direction?: 'asc' | 'desc' };
type FilterOption = { label: string; value: string | number };

function FilterCombobox({
    label,
    value,
    onChange,
    options,
    className,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: FilterOption[];
    className?: string;
}) {
    const selected =
        options.find((option) => String(option.value) === value) ?? null;

    return (
        <Combobox
            items={options}
            value={selected}
            onValueChange={(option) => onChange(String(option?.value ?? ''))}
            itemToStringLabel={(option) => option.label}
            itemToStringValue={(option) => String(option.value)}
            isItemEqualToValue={(option, current) =>
                String(option.value) === String(current.value)
            }
        >
            <ComboboxInput
                aria-label={label}
                placeholder={label}
                className={className}
            />
            <ComboboxContent>
                <ComboboxEmpty>No options found.</ComboboxEmpty>
                <ComboboxList>
                    {(option: FilterOption) => (
                        <ComboboxItem key={String(option.value)} value={option}>
                            {option.label}
                        </ComboboxItem>
                    )}
                </ComboboxList>
            </ComboboxContent>
        </Combobox>
    );
}

export function IndexDataControls({
    search,
    onSearchChange,
    sort,
    direction,
    onSortChange,
    sortOptions,
    children,
}: {
    search: string;
    onSearchChange: (value: string) => void;
    sort: string;
    direction: 'asc' | 'desc';
    onSortChange: (sort: string, direction: 'asc' | 'desc') => void;
    sortOptions: SortOption[];
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2.5 border-b bg-muted/20 p-3 lg:flex-row lg:items-center lg:p-4">
            <label className="relative w-full lg:min-w-56 lg:flex-1">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(event) => onSearchChange(event.target.value)}
                    placeholder="Search..."
                    className="bg-background/80 pl-9 shadow-xs"
                    aria-label="Search"
                />
            </label>
            {children}
            <FilterCombobox
                label="Sort results"
                value={`${sort}:${direction}`}
                onChange={(value) => {
                    const [nextSort, nextDirection] = value.split(':');
                    onSortChange(nextSort, nextDirection as 'asc' | 'desc');
                }}
                options={sortOptions.map((option) => {
                    const optionDirection = option.direction ?? 'asc';

                    return {
                        label: option.label,
                        value: `${option.value}:${optionDirection}`,
                    };
                })}
                className="w-full bg-background/80 lg:w-48"
            />
        </div>
    );
}

export function IndexFilterSelect({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: Array<{ label: string; value: string | number }>;
}) {
    return (
        <FilterCombobox
            label={label}
            value={value}
            onChange={onChange}
            options={[{ label, value: '' }, ...options]}
            className="w-full bg-background/80 lg:w-44"
        />
    );
}

export function IndexPagination({
    currentPage,
    lastPage,
    from,
    to,
    total,
    perPage,
    onPageChange,
    onPerPageChange,
}: {
    currentPage: number;
    lastPage: number;
    from: number | null;
    to: number | null;
    total: number;
    perPage: number;
    onPageChange: (page: number) => void;
    onPerPageChange: (perPage: number) => void;
}) {
    return (
        <div className="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
            <span>{total ? `${from}-${to} of ${total}` : '0 results'}</span>
            <div className="flex items-center gap-2">
                <FilterCombobox
                    label="Results per page"
                    value={String(perPage)}
                    onChange={(value) => onPerPageChange(Number(value))}
                    options={[
                        { label: '10 per page', value: '10' },
                        { label: '25 per page', value: '25' },
                        { label: '50 per page', value: '50' },
                    ]}
                    className="w-36 bg-background/80"
                />
                <Button
                    variant="outline"
                    size="sm"
                    disabled={currentPage <= 1}
                    onClick={() => onPageChange(currentPage - 1)}
                >
                    Previous
                </Button>
                <span className="min-w-16 text-center">
                    {currentPage} / {lastPage}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={currentPage >= lastPage}
                    onClick={() => onPageChange(currentPage + 1)}
                >
                    Next
                </Button>
            </div>
        </div>
    );
}

export function IndexDataState({
    loading,
    error,
    empty,
}: {
    loading: boolean;
    error: string | null;
    empty: string;
}) {
    if (error) {
        return <div className="p-5 text-sm text-destructive">{error}</div>;
    }

    if (loading) {
        return (
            <div className="p-5 text-sm text-muted-foreground">Loading...</div>
        );
    }

    return <div className="p-5 text-sm text-muted-foreground">{empty}</div>;
}
