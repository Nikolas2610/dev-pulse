import * as React from 'react';
import {
    ResponsiveContainer,
    Tooltip as RechartsTooltip,
    type TooltipContentProps,
} from 'recharts';
import { cn } from '@/lib/utils';

export type ChartConfig = Record<string, { label?: React.ReactNode; color?: string }>;
export type ChartTooltipItem = {
    key: string;
    label: React.ReactNode;
    format?: (value: unknown) => React.ReactNode;
};

function ChartContainer({ className, children, config, ...props }: React.ComponentProps<'div'> & { config: ChartConfig; children: React.ComponentProps<typeof ResponsiveContainer>['children'] }) {
    const id = React.useId().replace(/:/g, '');
    const colors = Object.fromEntries(
        Object.entries(config)
            .filter(([, item]) => item.color)
            .map(([key, item]) => [`--color-${key}`, item.color]),
    ) as React.CSSProperties;

    return (
        <div data-chart={id} className={cn('flex min-w-0 max-w-full aspect-video justify-center overflow-hidden text-xs [&_.recharts-cartesian-axis-tick_text]:fill-foreground/70 [&_.recharts-cartesian-grid_line]:stroke-border/60 [&_.recharts-surface]:outline-hidden', className)} style={colors} {...props}>
            <ResponsiveContainer>{children}</ResponsiveContainer>
        </div>
    );
}

function ChartTooltipContent({
    active,
    payload,
    label,
    items,
    labelFormatter,
}: Partial<TooltipContentProps<number, string>> & {
    items: ChartTooltipItem[];
    labelFormatter?: (label: string | number | undefined) => React.ReactNode;
}) {
    if (!active || !payload?.length) {
        return null;
    }

    const data = (payload[0]?.payload ?? {}) as Record<string, unknown>;

    return (
        <div
            role="status"
            className="grid min-w-36 gap-2 rounded-md border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md"
        >
            <div className="font-medium">
                {labelFormatter ? labelFormatter(label) : label}
            </div>
            <div className="grid gap-1.5">
                {items.map((item) => (
                    <div
                        key={item.key}
                        className="flex items-center justify-between gap-5"
                    >
                        <span className="text-muted-foreground">
                            {item.label}
                        </span>
                        <span className="font-mono font-medium tabular-nums">
                            {item.format
                                ? item.format(data[item.key])
                                : String(data[item.key] ?? '—')}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

const ChartTooltip = RechartsTooltip;

export { ChartContainer, ChartTooltip, ChartTooltipContent };
