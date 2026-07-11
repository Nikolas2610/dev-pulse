import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

export function ThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-9 w-14 rounded-full border border-border/70 bg-background/80 p-1 shadow-xs hover:bg-accent"
                    aria-label={
                        isDark ? 'Switch to light mode' : 'Switch to dark mode'
                    }
                    onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
                >
                    <span className="relative grid h-full w-full grid-cols-2 items-center">
                        <span
                            className={cn(
                                'absolute top-0.5 size-6 rounded-full bg-primary shadow-sm transition-transform',
                                isDark ? 'translate-x-6' : 'translate-x-0',
                            )}
                        />
                        <Sun
                            className={cn(
                                'relative z-10 mx-auto size-3.5 transition-colors',
                                isDark
                                    ? 'text-muted-foreground'
                                    : 'text-primary-foreground',
                            )}
                        />
                        <Moon
                            className={cn(
                                'relative z-10 mx-auto size-3.5 transition-colors',
                                isDark
                                    ? 'text-primary-foreground'
                                    : 'text-muted-foreground',
                            )}
                        />
                    </span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                {isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            </TooltipContent>
        </Tooltip>
    );
}
