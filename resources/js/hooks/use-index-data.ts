import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';

export type IndexPage<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type IndexQuery = {
    search: string;
    page: number;
    perPage: number;
    sort: string;
    direction: 'asc' | 'desc';
    filters: Record<string, string>;
};

const emptyPage = <T>(): IndexPage<T> => ({
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: null,
    to: null,
});

export function useIndexData<T>(
    endpoint: string,
    defaults: Pick<IndexQuery, 'sort' | 'direction'>,
) {
    const [query, setQuery] = useState<IndexQuery>({
        search: '',
        page: 1,
        perPage: 10,
        filters: {},
        ...defaults,
    });
    const [page, setPage] = useState<IndexPage<T>>(emptyPage<T>());
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [revision, setRevision] = useState(0);

    const parameters = useMemo(() => {
        const values = new URLSearchParams({
            page: String(query.page),
            per_page: String(query.perPage),
            sort: query.sort,
            direction: query.direction,
        });

        if (query.search.trim()) {
            values.set('search', query.search.trim());
        }

        Object.entries(query.filters).forEach(([key, value]) => {
            if (value !== '') {
                values.set(key, value);
            }
        });

        return values.toString();
    }, [query]);

    useEffect(() => {
        const stop = router.on('success', () =>
            setRevision((value) => value + 1),
        );

        return stop;
    }, []);

    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(
            async () => {
                setLoading(true);
                setError(null);

                try {
                    const response = await fetch(`${endpoint}?${parameters}`, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                        signal: controller.signal,
                    });

                    if (!response.ok) {
                        throw new Error('The list could not be loaded.');
                    }

                    setPage((await response.json()) as IndexPage<T>);
                } catch (exception) {
                    if (!controller.signal.aborted) {
                        setError(
                            exception instanceof Error
                                ? exception.message
                                : 'The list could not be loaded.',
                        );
                    }
                } finally {
                    if (!controller.signal.aborted) {
                        setLoading(false);
                    }
                }
            },
            query.search ? 250 : 0,
        );

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [endpoint, parameters, query.search, revision]);

    const updateQuery = useCallback((changes: Partial<IndexQuery>) => {
        setQuery((current) => ({
            ...current,
            ...changes,
            page: changes.page ?? 1,
        }));
    }, []);

    const setFilter = useCallback((name: string, value: string) => {
        setQuery((current) => ({
            ...current,
            page: 1,
            filters: { ...current.filters, [name]: value },
        }));
    }, []);

    return { page, query, loading, error, updateQuery, setFilter };
}
