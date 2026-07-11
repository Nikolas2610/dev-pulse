import { Badge } from '@/components/ui/badge';

const statusBadgeClasses: Record<string, string> = {
    active: 'border-sky-500/25 bg-sky-500/15 text-sky-700 dark:text-sky-300',
    completed:
        'border-emerald-500/25 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    cancelled:
        'border-rose-500/25 bg-rose-500/15 text-rose-700 dark:text-rose-300',
    scheduled:
        'border-amber-500/25 bg-amber-500/15 text-amber-700 dark:text-amber-300',
};

function formatStatus(status?: string | null) {
    if (!status) {
        return 'Unknown';
    }

    return status
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function StandupStatusBadge({ status }: { status?: string | null }) {
    const normalizedStatus = String(status ?? '').toLowerCase();

    return (
        <Badge
            variant="outline"
            className={
                statusBadgeClasses[normalizedStatus] ??
                'border-muted-foreground/20 bg-muted text-muted-foreground'
            }
        >
            {formatStatus(status)}
        </Badge>
    );
}
