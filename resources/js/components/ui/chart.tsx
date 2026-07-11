import * as React from 'react';
import { ResponsiveContainer } from 'recharts';
import { cn } from '@/lib/utils';

export type ChartConfig = Record<string, { label?: React.ReactNode; color?: string }>;

function ChartContainer({ className, children, config, ...props }: React.ComponentProps<'div'> & { config: ChartConfig; children: React.ComponentProps<typeof ResponsiveContainer>['children'] }) {
    const id = React.useId().replace(/:/g, '');
    const colors = Object.fromEntries(
        Object.entries(config)
            .filter(([, item]) => item.color)
            .map(([key, item]) => [`--color-${key}`, item.color]),
    ) as React.CSSProperties;

    return (
        <div data-chart={id} className={cn('flex aspect-video justify-center text-xs [&_.recharts-cartesian-axis-tick_text]:fill-muted-foreground [&_.recharts-cartesian-grid_line]:stroke-border/60 [&_.recharts-surface]:outline-hidden', className)} style={colors} {...props}>
            <ResponsiveContainer>{children}</ResponsiveContainer>
        </div>
    );
}

export { ChartContainer };
