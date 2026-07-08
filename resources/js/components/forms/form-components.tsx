import type {
    ComponentPropsWithoutRef,
    ReactNode,
    SelectHTMLAttributes,
} from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Option = {
    label: string;
    value: string | number;
};

const fallbackTimezones = [
    'UTC',
    'Asia/Nicosia',
    'Europe/Athens',
    'Europe/London',
    'Europe/Berlin',
    'Europe/Paris',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'Asia/Dubai',
    'Asia/Singapore',
    'Asia/Tokyo',
    'Australia/Sydney',
];

function timezoneOptions(): Option[] {
    const supportedValuesOf = (
        Intl as typeof Intl & {
            supportedValuesOf?: (key: 'timeZone') => string[];
        }
    ).supportedValuesOf;

    const zones = supportedValuesOf
        ? supportedValuesOf('timeZone')
        : fallbackTimezones;

    return Array.from(new Set([...fallbackTimezones, ...zones])).sort().map((timezone) => ({
        label: timezone.replaceAll('_', ' '),
        value: timezone,
    }));
}

export function FormGrid({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('grid gap-3 md:grid-cols-2', className)}>
            {children}
        </div>
    );
}

export function InlineEditForm({
    children,
    className,
    ...props
}: ComponentPropsWithoutRef<'form'>) {
    return (
        <form
            className={cn(
                'grid gap-3 rounded-md border bg-card/50 p-3 md:grid-cols-2',
                className,
            )}
            {...props}
        >
            {children}
        </form>
    );
}

export function FormField({
    label,
    error,
    className,
    labelClassName,
    ...props
}: ComponentPropsWithoutRef<typeof Input> & {
    label: string;
    error?: string;
    labelClassName?: string;
}) {
    return (
        <div className={cn('space-y-2', className)}>
            <Label className={labelClassName}>{label}</Label>
            <Input {...props} />
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

export function FormSelect({
    label,
    options,
    error,
    className,
    labelClassName,
    children,
    ...props
}: SelectHTMLAttributes<HTMLSelectElement> & {
    label: string;
    options?: Option[];
    error?: string;
    labelClassName?: string;
}) {
    return (
        <div className={cn('space-y-2', className)}>
            <Label className={labelClassName}>{label}</Label>
            <select
                className="h-9 w-full rounded-md border bg-background px-3 text-sm outline-none transition focus:border-primary"
                {...props}
            >
                {options
                    ? options.map((option) => (
                          <option key={option.value} value={option.value}>
                              {option.label}
                          </option>
                      ))
                    : children}
            </select>
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

export function TimezoneSelect({
    label = 'Timezone',
    ...props
}: Omit<ComponentPropsWithoutRef<typeof FormSelect>, 'options'> & {
    label?: string;
}) {
    return <FormSelect label={label} options={timezoneOptions()} {...props} />;
}

export function FormCheckbox({
    label,
    checked,
    onCheckedChange,
    className,
}: {
    label: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    className?: string;
}) {
    return (
        <label
            className={cn(
                'flex h-9 items-center gap-2 rounded-md border px-3 text-sm',
                className,
            )}
        >
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) => onCheckedChange(event.target.checked)}
            />
            {label}
        </label>
    );
}

export function FormActions({
    submitLabel,
    processing,
    disabled,
    children,
    className,
}: {
    submitLabel: string;
    processing?: boolean;
    disabled?: boolean;
    children?: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('flex items-end gap-2', className)}>
            <Button disabled={processing || disabled}>{submitLabel}</Button>
            {children}
        </div>
    );
}
