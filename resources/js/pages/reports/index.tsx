import { Head, router } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    Clock3,
    Flag,
    MessagesSquare,
    UsersRound,
} from 'lucide-react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    XAxis,
    YAxis,
} from 'recharts';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
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

type ReportProps = {
    period: { days: number; start: string; end: string };
    workspace: string | null;
    metrics: {
        completionRate: number;
        completionChange: number;
        responses: number;
        runs: number;
        blockers: number;
        medianMinutes: number;
    };
    daily: Array<{
        date: string;
        completion: number;
        responses: number;
        blockers: number;
    }>;
    weekdays: Array<{ day: string; rate: number }>;
    team: Array<{
        name: string;
        email: string;
        submitted: number;
        expected: number;
        rate: number;
        avgMinutes: number;
        blockers: number;
    }>;
};

const trendConfig = {
    completion: { label: 'Completion', color: 'var(--chart-1)' },
    responses: { label: 'Responses', color: 'var(--chart-2)' },
    blockers: { label: 'Blockers', color: 'var(--chart-4)' },
} satisfies ChartConfig;

const weeklyConfig = {
    rate: { label: 'Response rate', color: 'var(--chart-1)' },
} satisfies ChartConfig;

const weekdayNames: Record<string, string> = {
    Mon: 'Monday',
    Tue: 'Tuesday',
    Wed: 'Wednesday',
    Thu: 'Thursday',
    Fri: 'Friday',
};

const initials = (name: string) =>
    name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();

export default function Reports({
    period,
    workspace,
    metrics,
    daily,
    weekdays,
    team,
}: ReportProps) {
    const changeIsPositive = metrics.completionChange >= 0;
    const weekdayData = weekdays.map((item) => ({
        ...item,
        day: weekdayNames[item.day] ?? item.day,
    }));

    return (
        <>
            <Head title="Reports" />
            <div className="dp-page min-w-0 overflow-x-hidden">
                <div className="dp-page-header">
                    <div className="min-w-0">
                        <div className="dp-kicker">Team intelligence</div>
                        <h1 className="dp-title">Participation reports</h1>
                        <p className="dp-subtitle">
                            See response patterns, recurring friction, and who
                            may need a nudge before habits slip.
                        </p>
                    </div>
                    <div className="flex w-full items-center gap-3 sm:w-auto">
                        <span className="hidden text-sm text-muted-foreground sm:inline">
                            {period.start}–{period.end}
                        </span>
                        <Select
                            value={String(period.days)}
                            onValueChange={(value) =>
                                router.get(
                                    '/reports',
                                    { days: value },
                                    { preserveState: true },
                                )
                            }
                        >
                            <SelectTrigger
                                className="w-full sm:w-36"
                                aria-label="Report period"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="30">
                                        Last 30 days
                                    </SelectItem>
                                    <SelectItem value="60">
                                        Last 60 days
                                    </SelectItem>
                                    <SelectItem value="90">
                                        Last 90 days
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <section
                    aria-label="Report summary"
                    className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <MetricCard
                        icon={UsersRound}
                        label="Participation"
                        value={`${metrics.completionRate}%`}
                        detail={`${Math.abs(metrics.completionChange)} pts vs prior period`}
                        positive={changeIsPositive}
                    />
                    <MetricCard
                        icon={MessagesSquare}
                        label="Responses"
                        value={metrics.responses.toLocaleString()}
                        detail={`across ${metrics.runs} standups`}
                    />
                    <MetricCard
                        icon={Flag}
                        label="Blockers raised"
                        value={String(metrics.blockers)}
                        detail="submitted signals"
                    />
                    <MetricCard
                        icon={Clock3}
                        label="Median response"
                        value={`${metrics.medianMinutes}m`}
                        detail="after standup opens"
                    />
                </section>

                <div className="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1.65fr)_minmax(18rem,0.75fr)]">
                    <Card className="min-w-0 overflow-hidden">
                        <CardHeader className="flex-col items-start justify-between gap-3 sm:flex-row">
                            <div className="min-w-0">
                                <CardTitle>Participation trend</CardTitle>
                                <CardDescription>
                                    Daily completion rate and blocker volume.
                                </CardDescription>
                            </div>
                            <Badge variant="outline">
                                {workspace ?? 'Current workspace'}
                            </Badge>
                        </CardHeader>
                        <CardContent className="px-2 sm:px-6">
                            <ChartContainer
                                config={trendConfig}
                                className="h-56 w-full min-w-0 sm:h-72"
                            >
                                <AreaChart
                                    data={daily}
                                    margin={{ left: -24, right: 4 }}
                                    accessibilityLayer
                                >
                                    <defs>
                                        <linearGradient
                                            id="completion-fill"
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
                                            <stop
                                                offset="5%"
                                                stopColor="var(--color-completion)"
                                                stopOpacity={0.3}
                                            />
                                            <stop
                                                offset="95%"
                                                stopColor="var(--color-completion)"
                                                stopOpacity={0}
                                            />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid vertical={false} />
                                    <XAxis
                                        dataKey="date"
                                        tickLine={false}
                                        axisLine={false}
                                        minTickGap={28}
                                    />
                                    <YAxis
                                        domain={[0, 100]}
                                        tickLine={false}
                                        axisLine={false}
                                        tickFormatter={(value) => `${value}%`}
                                    />
                                    <ChartTooltip
                                        cursor={{
                                            stroke: 'var(--border)',
                                            strokeDasharray: '4 4',
                                        }}
                                        content={
                                            <ChartTooltipContent
                                                items={[
                                                    {
                                                        key: 'completion',
                                                        label: 'Completion',
                                                        format: (value) =>
                                                            `${Number(value)}%`,
                                                    },
                                                    {
                                                        key: 'responses',
                                                        label: 'Responses',
                                                    },
                                                    {
                                                        key: 'blockers',
                                                        label: 'Blockers',
                                                    },
                                                ]}
                                            />
                                        }
                                    />
                                    <Area
                                        dataKey="completion"
                                        type="monotone"
                                        fill="url(#completion-fill)"
                                        stroke="var(--color-completion)"
                                        strokeWidth={2}
                                    />
                                </AreaChart>
                            </ChartContainer>
                        </CardContent>
                    </Card>

                    <Card className="min-w-0 overflow-hidden">
                        <CardHeader>
                            <CardTitle>Weekly rhythm</CardTitle>
                            <CardDescription>
                                Average response rate by weekday.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="min-w-0 px-3 sm:px-6">
                            <ChartContainer
                                config={weeklyConfig}
                                className="h-44 w-full min-w-0"
                            >
                                <BarChart
                                    data={weekdayData}
                                    layout="vertical"
                                    margin={{ left: 0, right: 8 }}
                                    accessibilityLayer
                                >
                                    <XAxis
                                        type="number"
                                        domain={[0, 100]}
                                        hide
                                    />
                                    <YAxis
                                        type="category"
                                        dataKey="day"
                                        tickLine={false}
                                        axisLine={false}
                                        width={72}
                                    />
                                    <ChartTooltip
                                        cursor={{ fill: 'var(--muted)' }}
                                        content={
                                            <ChartTooltipContent
                                                items={[
                                                    {
                                                        key: 'rate',
                                                        label: 'Response rate',
                                                        format: (value) =>
                                                            `${Number(value)}%`,
                                                    },
                                                ]}
                                            />
                                        }
                                    />
                                    <Bar
                                        dataKey="rate"
                                        radius={3}
                                        background={{ fill: 'var(--muted)' }}
                                    >
                                        {weekdayData.map((item) => (
                                            <Cell
                                                key={item.day}
                                                fill={
                                                    item.rate < 75
                                                        ? 'var(--chart-3)'
                                                        : 'var(--chart-1)'
                                                }
                                            />
                                        ))}
                                    </Bar>
                                </BarChart>
                            </ChartContainer>
                            <div className="mt-4 min-w-0 border-t pt-4 text-sm break-words text-muted-foreground">
                                The weakest weekday is{' '}
                                <strong className="font-medium text-foreground">
                                    {[...weekdayData].sort(
                                        (a, b) => a.rate - b.rate,
                                    )[0]?.day ?? '—'}
                                </strong>
                                . Consider moving reminders closer to the
                                opening time.
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="min-w-0 overflow-hidden">
                    <CardHeader>
                        <CardTitle>Member consistency</CardTitle>
                        <CardDescription>
                            Response reliability and blocker load during this
                            period.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="flex flex-col divide-y md:hidden">
                            {team.map((member) => (
                                <MemberMobileCard
                                    key={member.email}
                                    member={member}
                                />
                            ))}
                        </div>
                        <div className="hidden min-w-0 md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-6">
                                            Member
                                        </TableHead>
                                        <TableHead>Responses</TableHead>
                                        <TableHead>Participation</TableHead>
                                        <TableHead>Typical delay</TableHead>
                                        <TableHead className="pr-6 text-right">
                                            Blockers
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {team.map((member) => (
                                        <TableRow key={member.email}>
                                            <TableCell className="pl-6">
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="size-8">
                                                        <AvatarFallback>
                                                            {initials(
                                                                member.name,
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <div className="font-medium">
                                                            {member.name}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {member.email}
                                                        </div>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="font-mono text-xs tabular-nums">
                                                {member.submitted}/
                                                {member.expected}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <div className="h-1.5 w-20 overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            className="h-full rounded-full bg-primary"
                                                            style={{
                                                                width: `${member.rate}%`,
                                                            }}
                                                        />
                                                    </div>
                                                    <span className="font-mono text-xs tabular-nums">
                                                        {member.rate}%
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {member.avgMinutes} min
                                            </TableCell>
                                            <TableCell className="pr-6 text-right">
                                                <Badge
                                                    variant={
                                                        member.blockers > 3
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {member.blockers}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function MemberMobileCard({ member }: { member: ReportProps['team'][number] }) {
    return (
        <article className="flex min-w-0 flex-col gap-4 p-4">
            <div className="flex min-w-0 items-center gap-3">
                <Avatar className="size-9 shrink-0">
                    <AvatarFallback>{initials(member.name)}</AvatarFallback>
                </Avatar>
                <div className="min-w-0 flex-1">
                    <div className="truncate font-medium">{member.name}</div>
                    <div className="truncate text-xs text-muted-foreground">
                        {member.email}
                    </div>
                </div>
                <Badge
                    variant={member.blockers > 3 ? 'destructive' : 'secondary'}
                >
                    {member.blockers} blockers
                </Badge>
            </div>
            <div className="grid grid-cols-3 gap-3 text-sm">
                <MemberStat
                    label="Responses"
                    value={`${member.submitted}/${member.expected}`}
                />
                <MemberStat label="Participation" value={`${member.rate}%`} />
                <MemberStat
                    label="Typical delay"
                    value={`${member.avgMinutes}m`}
                />
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                <div
                    className="h-full rounded-full bg-primary"
                    style={{ width: `${member.rate}%` }}
                />
            </div>
        </article>
    );
}

function MemberStat({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <div className="truncate text-xs text-muted-foreground">
                {label}
            </div>
            <div className="font-mono font-medium tabular-nums">{value}</div>
        </div>
    );
}

function MetricCard({
    icon: Icon,
    label,
    value,
    detail,
    positive,
}: {
    icon: typeof UsersRound;
    label: string;
    value: string;
    detail: string;
    positive?: boolean;
}) {
    const TrendIcon = positive ? ArrowUpRight : ArrowDownRight;

    return (
        <Card>
            <CardHeader className="pb-2">
                <div className="flex items-center justify-between">
                    <CardDescription>{label}</CardDescription>
                    <Icon
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <CardTitle className="text-3xl tabular-nums">{value}</CardTitle>
            </CardHeader>
            <CardContent className="flex items-center gap-1 text-xs text-muted-foreground">
                {positive !== undefined && (
                    <TrendIcon className="size-3" aria-hidden="true" />
                )}
                {detail}
            </CardContent>
        </Card>
    );
}
