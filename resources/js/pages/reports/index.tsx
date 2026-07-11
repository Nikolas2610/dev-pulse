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
import { ChartContainer } from '@/components/ui/chart';
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
    blockers: { label: 'Blockers', color: 'var(--chart-4)' },
} satisfies ChartConfig;

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

    return (
        <>
            <Head title="Reports" />
            <div className="dp-page">
                <div className="dp-page-header">
                    <div>
                        <div className="dp-kicker">Team intelligence</div>
                        <h1 className="dp-title">Participation reports</h1>
                        <p className="dp-subtitle">
                            See response patterns, recurring friction, and who
                            may need a nudge before habits slip.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
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
                                className="w-36"
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

                <div className="grid gap-4 xl:grid-cols-[minmax(0,1.65fr)_minmax(18rem,0.75fr)]">
                    <Card>
                        <CardHeader className="flex-row items-start justify-between">
                            <div>
                                <CardTitle>Participation trend</CardTitle>
                                <CardDescription>
                                    Daily completion rate and blocker volume.
                                </CardDescription>
                            </div>
                            <Badge variant="outline">
                                {workspace ?? 'Current workspace'}
                            </Badge>
                        </CardHeader>
                        <CardContent>
                            <ChartContainer
                                config={trendConfig}
                                className="h-72 w-full"
                            >
                                <AreaChart
                                    data={daily}
                                    margin={{ left: -20, right: 8 }}
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

                    <Card className="overflow-hidden">
                        <CardHeader>
                            <CardTitle>Weekly rhythm</CardTitle>
                            <CardDescription>
                                Average response rate by weekday.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ChartContainer
                                config={trendConfig}
                                className="h-44 w-full"
                            >
                                <BarChart
                                    data={weekdays}
                                    layout="vertical"
                                    margin={{ left: -12, right: 8 }}
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
                                        width={38}
                                    />
                                    <Bar
                                        dataKey="rate"
                                        radius={3}
                                        background={{ fill: 'var(--muted)' }}
                                    >
                                        {weekdays.map((item) => (
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
                            <div className="mt-4 border-t pt-4 text-sm text-muted-foreground">
                                The weakest weekday is{' '}
                                <strong className="font-medium text-foreground">
                                    {[...weekdays].sort(
                                        (a, b) => a.rate - b.rate,
                                    )[0]?.day ?? '—'}
                                </strong>
                                . Consider moving reminders closer to the
                                opening time.
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Member consistency</CardTitle>
                        <CardDescription>
                            Response reliability and blocker load during this
                            period.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
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
                                                        {initials(member.name)}
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
                                            {member.submitted}/{member.expected}
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
                    </CardContent>
                </Card>
            </div>
        </>
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
