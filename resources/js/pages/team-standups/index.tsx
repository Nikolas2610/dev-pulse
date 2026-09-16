import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, FilterX, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { AppDateFormatter } from '@/lib/date-format';

type Member = { id: number; name: string; email: string };
type TeamResponse = {
    id: number;
    status: 'pending' | 'submitted';
    submittedAt: string | null;
    user: Member & { avatar_url?: string | null };
    run: { id: number; date: string; status: string; template: string | null };
    answers: Array<{
        key: string | null;
        question: string | null;
        answer: string | null;
        items: WorkItemSnapshot[];
    }>;
    linkedWork: WorkItemSnapshot[];
};
type WorkItemSnapshot = {
    id: number;
    external_key: string | null;
    title: string;
    status: string;
    url: string;
    relation: string;
};
type PaginatedResponses = {
    data: TeamResponse[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};
type Filters = {
    search: string;
    user: string;
    status: string;
    from: string;
    to: string;
    per_page: number;
};

const initials = (name: string) =>
    name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();

export default function TeamStandups({
    responses,
    members,
    filters,
    workspace,
}: {
    responses: PaginatedResponses;
    members: Member[];
    filters: Filters;
    workspace: string | null;
}) {
    const [query, setQuery] = useState(filters);

    const applyFilters = (event?: FormEvent) => {
        event?.preventDefault();
        router.get('/team-standups', query, {
            preserveState: true,
            preserveScroll: true,
        });
    };
    const clearFilters = () => router.get('/team-standups');
    const goToPage = (page: number) =>
        router.get(
            '/team-standups',
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title="Team standups" />
            <div className="dp-page min-w-0 overflow-x-hidden">
                <div className="dp-page-header">
                    <div className="min-w-0">
                        <div className="dp-kicker">Manager view</div>
                        <h1 className="dp-title">Team standups</h1>
                        <p className="dp-subtitle">
                            Review every team member’s updates, priorities,
                            tickets, and blockers in one place.
                        </p>
                    </div>
                    <Badge variant="outline">
                        {workspace ?? 'Current workspace'}
                    </Badge>
                </div>

                <Card className="min-w-0 overflow-hidden">
                    <CardHeader>
                        <CardTitle>Find standups</CardTitle>
                        <CardDescription>
                            Filter by teammate, submission state, date, or any
                            text in an answer.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={applyFilters}
                            className="grid min-w-0 gap-3 lg:grid-cols-[minmax(13rem,1fr)_12rem_10rem_11rem_11rem_auto]"
                        >
                            <label className="relative min-w-0">
                                <span className="sr-only">Search standups</span>
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={query.search}
                                    onChange={(event) =>
                                        setQuery({
                                            ...query,
                                            search: event.target.value,
                                        })
                                    }
                                    placeholder="Search answers or users…"
                                    className="pl-9"
                                />
                            </label>
                            <Select
                                value={query.user || 'all'}
                                onValueChange={(user) =>
                                    setQuery({
                                        ...query,
                                        user: user === 'all' ? '' : user,
                                    })
                                }
                            >
                                <SelectTrigger
                                    className="w-full"
                                    aria-label="Filter by member"
                                >
                                    <SelectValue placeholder="All members" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="all">
                                            All members
                                        </SelectItem>
                                        {members.map((member) => (
                                            <SelectItem
                                                key={member.id}
                                                value={String(member.id)}
                                            >
                                                {member.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <Select
                                value={query.status || 'all'}
                                onValueChange={(status) =>
                                    setQuery({
                                        ...query,
                                        status: status === 'all' ? '' : status,
                                    })
                                }
                            >
                                <SelectTrigger
                                    className="w-full"
                                    aria-label="Filter by status"
                                >
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="all">
                                            All statuses
                                        </SelectItem>
                                        <SelectItem value="submitted">
                                            Submitted
                                        </SelectItem>
                                        <SelectItem value="pending">
                                            Pending
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <DatePicker
                                value={query.from}
                                onValueChange={(from) =>
                                    setQuery({ ...query, from })
                                }
                                placeholder="From date"
                                aria-label="Filter from date"
                                className="w-full lg:w-full"
                            />
                            <DatePicker
                                value={query.to}
                                onValueChange={(to) =>
                                    setQuery({ ...query, to })
                                }
                                placeholder="To date"
                                aria-label="Filter to date"
                                className="w-full lg:w-full"
                            />
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    className="flex-1 lg:flex-none"
                                >
                                    Apply
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    onClick={clearFilters}
                                    aria-label="Clear filters"
                                >
                                    <FilterX />
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 className="font-semibold">Standup responses</h2>
                        <p className="text-sm text-muted-foreground">
                            Newest standup dates appear first.
                        </p>
                    </div>
                    <Badge variant="secondary">
                        {responses.total.toLocaleString()} responses
                    </Badge>
                </div>

                {responses.data.length ? (
                    <div className="flex min-w-0 flex-col gap-3">
                        {responses.data.map((response) => (
                            <ResponseCard
                                key={response.id}
                                response={response}
                            />
                        ))}
                    </div>
                ) : (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            No standups match these filters.
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardContent className="flex flex-col gap-3 py-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                        <span>
                            {responses.total
                                ? `${responses.from}–${responses.to} of ${responses.total}`
                                : '0 results'}
                        </span>
                        <div className="flex items-center justify-between gap-2 sm:justify-end">
                            <Select
                                value={String(filters.per_page)}
                                onValueChange={(perPage) =>
                                    router.get(
                                        '/team-standups',
                                        {
                                            ...filters,
                                            per_page: perPage,
                                            page: 1,
                                        },
                                        { preserveState: true },
                                    )
                                }
                            >
                                <SelectTrigger
                                    className="w-28"
                                    aria-label="Results per page"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="10">
                                            10 / page
                                        </SelectItem>
                                        <SelectItem value="25">
                                            25 / page
                                        </SelectItem>
                                        <SelectItem value="50">
                                            50 / page
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={responses.current_page <= 1}
                                onClick={() =>
                                    goToPage(responses.current_page - 1)
                                }
                            >
                                Previous
                            </Button>
                            <span className="min-w-12 text-center tabular-nums">
                                {responses.current_page}/{responses.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    responses.current_page >=
                                    responses.last_page
                                }
                                onClick={() =>
                                    goToPage(responses.current_page + 1)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function ResponseCard({ response }: { response: TeamResponse }) {
    return (
        <Card className="min-w-0 overflow-hidden">
            <CardHeader className="gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex min-w-0 items-center gap-3">
                    <Avatar className="size-10">
                        <AvatarFallback>
                            {initials(response.user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <CardTitle className="truncate text-base">
                            {response.user.name}
                        </CardTitle>
                        <CardDescription className="truncate">
                            {response.user.email}
                        </CardDescription>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                    <Badge
                        variant={
                            response.status === 'submitted'
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {response.status}
                    </Badge>
                    <span className="text-sm font-medium">
                        {AppDateFormatter.friendlyDate(response.run.date)}
                    </span>
                    <Button asChild variant="ghost" size="sm">
                        <Link href={`/standups/${response.run.id}`}>
                            View run
                            <ArrowRight />
                        </Link>
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                {response.answers.length ? (
                    <div className="space-y-3">
                        <div className="grid min-w-0 gap-px overflow-hidden rounded-md border bg-border md:grid-cols-2 xl:grid-cols-4">
                            {response.answers.map((answer, index) => (
                                <div
                                    key={`${answer.key}-${index}`}
                                    className="min-w-0 bg-card p-4"
                                >
                                    <div className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        {answer.question ??
                                            answer.key ??
                                            'Answer'}
                                    </div>
                                    <p className="text-sm break-words whitespace-pre-wrap">
                                        {answer.answer || 'No answer provided'}
                                    </p>
                                    {answer.items.length > 0 && (
                                        <div className="mt-3 flex flex-col gap-2">
                                            {answer.items.map((item) => (
                                                <TicketLink
                                                    key={item.id}
                                                    item={item}
                                                />
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                        {response.linkedWork.length > 0 && (
                            <div className="rounded-md border p-3">
                                <div className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Linked work
                                </div>
                                <div className="grid gap-2 md:grid-cols-2">
                                    {response.linkedWork.map((item) => (
                                        <TicketLink
                                            key={item.id}
                                            item={item}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="rounded-md bg-muted/50 p-4 text-sm text-muted-foreground">
                        This teammate has not submitted answers for this
                        standup.
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function TicketLink({ item }: { item: WorkItemSnapshot }) {
    return (
        <a
            href={item.url}
            target="_blank"
            rel="noreferrer"
            className="rounded-md border bg-background p-2 text-xs transition-colors hover:border-primary/40 hover:bg-muted/50"
        >
            <span className="font-semibold text-primary">
                {item.external_key ?? 'Jira'}
            </span>{' '}
            <span>{item.title}</span>
            <span className="mt-1 block text-muted-foreground">
                {item.status}
            </span>
        </a>
    );
}
