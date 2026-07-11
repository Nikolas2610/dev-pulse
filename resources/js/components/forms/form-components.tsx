import { LoaderCircle } from 'lucide-react';
import type {
    ChangeEvent,
    ComponentPropsWithoutRef,
    ReactElement,
    ReactNode,
    SelectHTMLAttributes,
} from 'react';
import { Children, isValidElement, useId } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Combobox,
    ComboboxChip,
    ComboboxChips,
    ComboboxChipsInput,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxLabel,
    ComboboxList,
    ComboboxTrigger,
    ComboboxValue,
} from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Option = {
    label: string;
    value: string | number;
    disabled?: boolean;
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

    return Array.from(new Set([...fallbackTimezones, ...zones]))
        .sort()
        .map((timezone) => ({
            label: timezone.replaceAll('_', ' '),
            value: timezone,
        }));
}

function fieldDescriptionId(id: string) {
    return `${id}-description`;
}

function fieldMessageId(id: string) {
    return `${id}-message`;
}

function optionLabel(children: ReactNode): string {
    return Children.toArray(children)
        .map((child) =>
            typeof child === 'string' || typeof child === 'number'
                ? String(child)
                : '',
        )
        .join('')
        .trim();
}

function optionsFromChildren(children: ReactNode): Option[] {
    return Children.toArray(children).flatMap((child) => {
        if (!isValidElement(child) || child.type !== 'option') {
            return [];
        }

        const option = child as ReactElement<
            ComponentPropsWithoutRef<'option'>
        >;

        return [
            {
                label: optionLabel(option.props.children),
                value: String(option.props.value ?? ''),
                disabled: option.props.disabled,
            },
        ];
    });
}

function selectChangeEvent(value: string): ChangeEvent<HTMLSelectElement> {
    return {
        target: { value },
        currentTarget: { value },
    } as ChangeEvent<HTMLSelectElement>;
}

export function FormDescription({
    className,
    ...props
}: ComponentPropsWithoutRef<'p'>) {
    return (
        <p
            data-slot="form-description"
            className={cn('text-sm text-muted-foreground', className)}
            {...props}
        />
    );
}

export function FormMessage({
    className,
    children,
    ...props
}: ComponentPropsWithoutRef<'p'>) {
    if (!children) {
        return null;
    }

    return (
        <p
            data-slot="form-message"
            className={cn('text-sm font-medium text-destructive', className)}
            role="alert"
            {...props}
        >
            {children}
        </p>
    );
}

export function FormGrid({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            data-slot="form-grid"
            className={cn('grid min-w-0 gap-4 md:grid-cols-2', className)}
        >
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
                'grid min-w-0 gap-4 rounded-lg border bg-card p-4 shadow-xs md:grid-cols-2',
                className,
            )}
            data-slot="inline-edit-form"
            {...props}
        >
            {children}
        </form>
    );
}

export function FormField({
    label,
    error,
    description,
    className,
    labelClassName,
    id: providedId,
    required,
    ...props
}: ComponentPropsWithoutRef<typeof Input> & {
    label: string;
    error?: string;
    description?: ReactNode;
    labelClassName?: string;
}) {
    const generatedId = useId();
    const id = providedId ?? generatedId;
    const describedBy = [
        description ? fieldDescriptionId(id) : null,
        error ? fieldMessageId(id) : null,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <div
            data-slot="form-item"
            className={cn('grid min-w-0 gap-2', className)}
        >
            <Label htmlFor={id} className={labelClassName}>
                {label}
                {required && (
                    <span
                        className="ml-0.5 text-destructive"
                        aria-hidden="true"
                    >
                        *
                    </span>
                )}
            </Label>
            <Input
                id={id}
                required={required}
                aria-invalid={Boolean(error)}
                aria-describedby={describedBy || undefined}
                {...props}
            />
            {description && (
                <FormDescription id={fieldDescriptionId(id)}>
                    {description}
                </FormDescription>
            )}
            <FormMessage id={fieldMessageId(id)}>{error}</FormMessage>
        </div>
    );
}

export function FormSelect({
    label,
    options,
    error,
    description,
    className,
    controlClassName,
    labelClassName,
    children,
    id: providedId,
    required,
    value,
    defaultValue,
    onChange,
    disabled,
    name,
    autoComplete,
}: SelectHTMLAttributes<HTMLSelectElement> & {
    label: string;
    options?: Option[];
    error?: string;
    description?: ReactNode;
    labelClassName?: string;
    controlClassName?: string;
}) {
    const generatedId = useId();
    const id = providedId ?? generatedId;
    const describedBy = [
        description ? fieldDescriptionId(id) : null,
        error ? fieldMessageId(id) : null,
    ]
        .filter(Boolean)
        .join(' ');
    const availableOptions = options ?? optionsFromChildren(children);
    const selectedValue = String(value ?? defaultValue ?? '');
    const selectedOption =
        availableOptions.find(
            (option) => String(option.value) === selectedValue,
        ) ?? null;

    return (
        <div
            data-slot="form-item"
            data-invalid={error ? '' : undefined}
            data-disabled={disabled ? '' : undefined}
            className={cn('grid min-w-0 gap-2', className)}
        >
            <Combobox
                items={availableOptions}
                value={selectedOption}
                onValueChange={(option) =>
                    onChange?.(selectChangeEvent(String(option?.value ?? '')))
                }
                itemToStringLabel={(option) => option.label}
                itemToStringValue={(option) => String(option.value)}
                isItemEqualToValue={(option, selected) =>
                    String(option.value) === String(selected.value)
                }
                disabled={disabled}
                autoComplete={autoComplete}
            >
                <ComboboxLabel className={labelClassName}>
                    {label}
                    {required && (
                        <span
                            className="ml-0.5 text-destructive"
                            aria-hidden="true"
                        >
                            *
                        </span>
                    )}
                </ComboboxLabel>
                <ComboboxTrigger
                    className={controlClassName}
                    disabled={disabled}
                    aria-required={required || undefined}
                    aria-invalid={Boolean(error)}
                    aria-describedby={describedBy || undefined}
                >
                    <ComboboxValue
                        placeholder={`Select ${label.toLowerCase()}`}
                    />
                </ComboboxTrigger>
                <ComboboxContent>
                    <ComboboxInput
                        id={id}
                        className="m-1 w-[calc(100%-0.5rem)] shadow-none"
                        disabled={disabled}
                        showSearchIcon
                        showTrigger={false}
                        placeholder={`Search ${label.toLowerCase()}...`}
                        aria-label={`Search ${label.toLowerCase()}`}
                    />
                    <ComboboxEmpty>No options found.</ComboboxEmpty>
                    <ComboboxList>
                        {(option: Option) => (
                            <ComboboxItem
                                key={String(option.value)}
                                value={option}
                                disabled={option.disabled}
                            >
                                {option.label}
                            </ComboboxItem>
                        )}
                    </ComboboxList>
                </ComboboxContent>
            </Combobox>
            {name && (
                <input
                    type="hidden"
                    name={name}
                    value={selectedValue}
                    disabled={disabled}
                />
            )}
            {description && (
                <FormDescription id={fieldDescriptionId(id)}>
                    {description}
                </FormDescription>
            )}
            <FormMessage id={fieldMessageId(id)}>{error}</FormMessage>
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

export function FormMultiSelect({
    label,
    options,
    value,
    onValueChange,
    error,
    description,
    className,
    labelClassName,
    id: providedId,
    disabled,
}: {
    label: string;
    options: Option[];
    value: Array<string | number>;
    onValueChange: (value: Array<string | number>) => void;
    error?: string;
    description?: ReactNode;
    className?: string;
    labelClassName?: string;
    id?: string;
    disabled?: boolean;
}) {
    const generatedId = useId();
    const id = providedId ?? generatedId;
    const selectedOptions = options.filter((option) =>
        value.some((selected) => String(selected) === String(option.value)),
    );
    const describedBy = [
        description ? fieldDescriptionId(id) : null,
        error ? fieldMessageId(id) : null,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <div
            data-slot="form-item"
            data-invalid={error ? '' : undefined}
            data-disabled={disabled ? '' : undefined}
            className={cn('grid min-w-0 gap-2', className)}
        >
            <Label htmlFor={id} className={labelClassName}>
                {label}
            </Label>
            <Combobox
                items={options}
                multiple
                value={selectedOptions}
                onValueChange={(selected) =>
                    onValueChange(selected.map((option) => option.value))
                }
                itemToStringLabel={(option) => option.label}
                itemToStringValue={(option) => String(option.value)}
                isItemEqualToValue={(option, selected) =>
                    String(option.value) === String(selected.value)
                }
                disabled={disabled}
            >
                <ComboboxChips>
                    <ComboboxValue>
                        {selectedOptions.map((option) => (
                            <ComboboxChip key={String(option.value)}>
                                {option.label}
                            </ComboboxChip>
                        ))}
                    </ComboboxValue>
                    <ComboboxChipsInput
                        id={id}
                        disabled={disabled}
                        placeholder={
                            selectedOptions.length
                                ? 'Add another...'
                                : `Select ${label.toLowerCase()}`
                        }
                        aria-invalid={Boolean(error)}
                        aria-describedby={describedBy || undefined}
                    />
                </ComboboxChips>
                <ComboboxContent>
                    <ComboboxEmpty>No options found.</ComboboxEmpty>
                    <ComboboxList>
                        {(option: Option) => (
                            <ComboboxItem
                                key={String(option.value)}
                                value={option}
                                disabled={option.disabled}
                            >
                                {option.label}
                            </ComboboxItem>
                        )}
                    </ComboboxList>
                </ComboboxContent>
            </Combobox>
            {description && (
                <FormDescription id={fieldDescriptionId(id)}>
                    {description}
                </FormDescription>
            )}
            <FormMessage id={fieldMessageId(id)}>{error}</FormMessage>
        </div>
    );
}

export function FormCheckbox({
    label,
    checked,
    onCheckedChange,
    className,
    description,
    error,
    disabled,
    id: providedId,
}: {
    label: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    className?: string;
    description?: ReactNode;
    error?: string;
    disabled?: boolean;
    id?: string;
}) {
    const generatedId = useId();
    const id = providedId ?? generatedId;
    const describedBy = [
        description ? fieldDescriptionId(id) : null,
        error ? fieldMessageId(id) : null,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <div
            data-slot="form-item"
            className={cn(
                'grid gap-2 rounded-lg border bg-card p-3 shadow-xs',
                disabled && 'opacity-50',
                className,
            )}
        >
            <div className="flex items-start gap-3">
                <Checkbox
                    id={id}
                    className="mt-0.5"
                    disabled={disabled}
                    aria-invalid={Boolean(error)}
                    aria-describedby={describedBy || undefined}
                    checked={checked}
                    onCheckedChange={(value) => onCheckedChange(value === true)}
                />
                <div className="grid gap-1.5 leading-none">
                    <Label htmlFor={id} className="cursor-pointer leading-snug">
                        {label}
                    </Label>
                    {description && (
                        <FormDescription id={fieldDescriptionId(id)}>
                            {description}
                        </FormDescription>
                    )}
                </div>
            </div>
            <FormMessage id={fieldMessageId(id)}>{error}</FormMessage>
        </div>
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
        <div
            data-slot="form-actions"
            className={cn(
                'flex flex-col items-stretch gap-2 pt-1 sm:flex-row sm:flex-wrap sm:items-center',
                className,
            )}
        >
            <Button
                type="submit"
                disabled={processing || disabled}
                aria-busy={processing || undefined}
                className="sm:w-auto"
            >
                {processing && (
                    <LoaderCircle className="animate-spin" aria-hidden="true" />
                )}
                {submitLabel}
            </Button>
            {children}
        </div>
    );
}
