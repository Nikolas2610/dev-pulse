import { FormMessage } from '@/components/forms/form-components';
import { cn } from '@/lib/utils';

export const weekdays = [
    { value: 1, shortLabel: 'Mon', label: 'Monday' },
    { value: 2, shortLabel: 'Tue', label: 'Tuesday' },
    { value: 3, shortLabel: 'Wed', label: 'Wednesday' },
    { value: 4, shortLabel: 'Thu', label: 'Thursday' },
    { value: 5, shortLabel: 'Fri', label: 'Friday' },
    { value: 6, shortLabel: 'Sat', label: 'Saturday' },
    { value: 7, shortLabel: 'Sun', label: 'Sunday' },
];

export function WeekdayPicker({
    value,
    onChange,
    error,
}: {
    value: number[];
    onChange: (value: number[]) => void;
    error?: string;
}) {
    return (
        <fieldset>
            <legend className="mb-2 text-sm font-medium">Run on</legend>
            <div className="flex flex-wrap gap-2">
                {weekdays.map((day) => {
                    const active = value.includes(day.value);

                    return (
                        <button
                            key={day.value}
                            type="button"
                            aria-pressed={active}
                            aria-label={day.label}
                            onClick={() =>
                                onChange(
                                    active
                                        ? value.filter(
                                              (selectedDay) =>
                                                  selectedDay !== day.value,
                                          )
                                        : [...value, day.value].sort(),
                                )
                            }
                            className={cn(
                                'h-9 min-w-12 rounded-md border px-3 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'bg-background hover:bg-accent',
                            )}
                        >
                            {day.shortLabel}
                        </button>
                    );
                })}
            </div>
            <FormMessage className="mt-2">{error}</FormMessage>
        </fieldset>
    );
}

export function formatWeekdays(value: number[] = []): string {
    const days = [...value].map(Number).sort();

    if (days.length === 7) {
        return 'Every day';
    }

    if (
        days.length === 5 &&
        days.every((day, index) => day === index + 1)
    ) {
        return 'Mon–Fri';
    }

    return weekdays
        .filter((day) => days.includes(day.value))
        .map((day) => day.shortLabel)
        .join(', ');
}
