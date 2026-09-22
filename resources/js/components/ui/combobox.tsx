import { Combobox as ComboboxPrimitive } from '@base-ui/react/combobox';
import {
    CheckIcon,
    ChevronsUpDownIcon,
    ChevronDownIcon,
    SearchIcon,
    XIcon,
} from 'lucide-react';
import * as React from 'react';
import { cn } from '@/lib/utils';

const Combobox = ComboboxPrimitive.Root;

function ComboboxInput({
    className,
    showClear = false,
    showSearchIcon = false,
    showTrigger = true,
    ...props
}: React.ComponentProps<typeof ComboboxPrimitive.Input> & {
    showClear?: boolean;
    showSearchIcon?: boolean;
    showTrigger?: boolean;
}) {
    return (
        <ComboboxPrimitive.InputGroup
            data-slot="combobox-input-group"
            className={cn(
                'flex h-9 w-full min-w-0 items-center rounded-md border border-input bg-transparent shadow-xs transition-[color,box-shadow]',
                'focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                'has-[[aria-invalid=true]]:border-destructive has-[[aria-invalid=true]]:ring-destructive/20',
                'has-[[data-disabled]]:cursor-not-allowed has-[[data-disabled]]:opacity-50',
                className,
            )}
        >
            {showSearchIcon && (
                <SearchIcon
                    className="ml-3 size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            )}
            <ComboboxPrimitive.Input
                data-slot="combobox-input"
                className="min-w-0 flex-1 bg-transparent px-3 py-1 text-base outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed md:text-sm"
                {...props}
            />
            {showClear && (
                <ComboboxPrimitive.Clear
                    data-slot="combobox-clear"
                    className="inline-flex size-8 shrink-0 items-center justify-center rounded-sm text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    aria-label="Clear selection"
                >
                    <XIcon />
                </ComboboxPrimitive.Clear>
            )}
            {showTrigger && (
                <ComboboxPrimitive.Trigger
                    data-slot="combobox-trigger"
                    className="inline-flex size-8 shrink-0 items-center justify-center rounded-sm text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    aria-label="Toggle options"
                >
                    <ComboboxPrimitive.Icon>
                        <ChevronDownIcon />
                    </ComboboxPrimitive.Icon>
                </ComboboxPrimitive.Trigger>
            )}
        </ComboboxPrimitive.InputGroup>
    );
}

const ComboboxLabel = ComboboxPrimitive.Label;
const ComboboxCollection = ComboboxPrimitive.Collection;

function ComboboxGroup({
    className,
    ...props
}: React.ComponentProps<typeof ComboboxPrimitive.Group>) {
    return (
        <ComboboxPrimitive.Group
            data-slot="combobox-group"
            className={cn('pb-1 last:pb-0', className)}
            {...props}
        />
    );
}

function ComboboxGroupLabel({
    className,
    ...props
}: React.ComponentProps<typeof ComboboxPrimitive.GroupLabel>) {
    return (
        <ComboboxPrimitive.GroupLabel
            data-slot="combobox-group-label"
            className={cn(
                'px-2 py-1.5 text-xs font-semibold text-muted-foreground',
                className,
            )}
            {...props}
        />
    );
}

function ComboboxTrigger({
    className,
    children,
    ...props
}: React.ComponentProps<typeof ComboboxPrimitive.Trigger>) {
    return (
        <ComboboxPrimitive.Trigger
            data-slot="combobox-trigger"
            className={cn(
                'flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-1 text-left text-sm shadow-xs outline-none transition-[color,box-shadow]',
                'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/20',
                'disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        >
            <span className="min-w-0 flex-1 truncate">{children}</span>
            <ComboboxPrimitive.Icon className="shrink-0 text-muted-foreground">
                <ChevronsUpDownIcon />
            </ComboboxPrimitive.Icon>
        </ComboboxPrimitive.Trigger>
    );
}

function ComboboxContent({
    className,
    children,
    ...props
}: React.ComponentProps<typeof ComboboxPrimitive.Popup>) {
    return (
        <ComboboxPrimitive.Portal>
            <ComboboxPrimitive.Positioner
                align="start"
                sideOffset={4}
                className="pointer-events-auto z-50 outline-none"
            >
                <ComboboxPrimitive.Popup
                    data-slot="combobox-content"
                    className={cn(
                        'max-h-[min(20rem,var(--available-height))] w-[var(--anchor-width)] min-w-48 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md',
                        'data-[open]:animate-in data-[closed]:animate-out data-[closed]:fade-out-0 data-[open]:fade-in-0 data-[closed]:zoom-out-95 data-[open]:zoom-in-95',
                        className,
                    )}
                    {...props}
                >
                    {children}
                </ComboboxPrimitive.Popup>
            </ComboboxPrimitive.Positioner>
        </ComboboxPrimitive.Portal>
    );
}

function ComboboxList({
    className,
    ...props
}: React.ComponentProps<typeof ComboboxPrimitive.List>) {
    return (
        <ComboboxPrimitive.List
            data-slot="combobox-list"
            className={cn('max-h-72 overflow-y-auto p-1', className)}
            {...props}
        />
    );
}

function ComboboxEmpty({
    className,
    ...props
}: React.ComponentProps<typeof ComboboxPrimitive.Empty>) {
    return (
        <ComboboxPrimitive.Empty
            data-slot="combobox-empty"
            className={cn(
                'px-3 py-6 text-center text-sm text-muted-foreground empty:hidden',
                className,
            )}
            {...props}
        />
    );
}

function ComboboxItem({
    className,
    children,
    ...props
}: React.ComponentProps<typeof ComboboxPrimitive.Item>) {
    return (
        <ComboboxPrimitive.Item
            data-slot="combobox-item"
            className={cn(
                'relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-none select-none',
                'data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
                className,
            )}
            {...props}
        >
            <span className="absolute right-2 flex size-4 items-center justify-center">
                <ComboboxPrimitive.ItemIndicator>
                    <CheckIcon />
                </ComboboxPrimitive.ItemIndicator>
            </span>
            <span className="truncate">{children}</span>
        </ComboboxPrimitive.Item>
    );
}

function ComboboxChips({
    className,
    ...props
}: React.ComponentProps<typeof ComboboxPrimitive.Chips>) {
    return (
        <ComboboxPrimitive.Chips
            data-slot="combobox-chips"
            className={cn(
                'flex min-h-9 w-full flex-wrap items-center gap-1 rounded-md border border-input bg-transparent px-2 py-1 shadow-xs transition-[color,box-shadow]',
                'focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                'has-[[aria-invalid=true]]:border-destructive has-[[aria-invalid=true]]:ring-destructive/20',
                className,
            )}
            {...props}
        />
    );
}

function ComboboxChipsInput({
    className,
    ...props
}: React.ComponentProps<typeof ComboboxPrimitive.Input>) {
    return (
        <ComboboxPrimitive.Input
            data-slot="combobox-chips-input"
            className={cn(
                'h-7 min-w-24 flex-1 bg-transparent px-1 text-sm outline-none placeholder:text-muted-foreground',
                className,
            )}
            {...props}
        />
    );
}

function ComboboxChip({
    className,
    children,
    ...props
}: React.ComponentProps<typeof ComboboxPrimitive.Chip>) {
    return (
        <ComboboxPrimitive.Chip
            data-slot="combobox-chip"
            className={cn(
                'inline-flex h-7 items-center gap-1 rounded-md bg-secondary px-2 text-xs font-medium text-secondary-foreground',
                className,
            )}
            {...props}
        >
            <span className="max-w-40 truncate">{children}</span>
            <ComboboxPrimitive.ChipRemove
                className="inline-flex rounded-sm text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
                aria-label={`Remove ${String(children)}`}
            >
                <XIcon />
            </ComboboxPrimitive.ChipRemove>
        </ComboboxPrimitive.Chip>
    );
}

const ComboboxValue = ComboboxPrimitive.Value;

export {
    Combobox,
    ComboboxChip,
    ComboboxChips,
    ComboboxChipsInput,
    ComboboxCollection,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxGroupLabel,
    ComboboxInput,
    ComboboxItem,
    ComboboxLabel,
    ComboboxList,
    ComboboxTrigger,
    ComboboxValue,
};
