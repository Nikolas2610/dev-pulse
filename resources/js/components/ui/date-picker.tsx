import { useState } from 'react';
import { format, isValid, parseISO } from 'date-fns';
import { CalendarDays } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

function DatePicker({
    value,
    onValueChange,
    placeholder = 'Pick a date',
    className,
    disabled,
    'aria-label': ariaLabel = 'Choose date',
}: {
    value?: string;
    onValueChange: (value: string) => void;
    placeholder?: string;
    className?: string;
    disabled?: boolean;
    'aria-label'?: string;
}) {
    const [open, setOpen] = useState(false);
    const parsedDate = value ? parseISO(value) : undefined;
    const selected = parsedDate && isValid(parsedDate) ? parsedDate : undefined;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    disabled={disabled}
                    data-empty={!selected}
                    aria-label={ariaLabel}
                    className={cn(
                        'w-full justify-start bg-background/80 text-left font-normal shadow-xs data-[empty=true]:text-muted-foreground lg:w-auto lg:min-w-40',
                        className,
                    )}
                >
                    <CalendarDays data-icon="inline-start" />
                    {selected ? format(selected, 'PPP') : placeholder}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="single"
                    selected={selected}
                    defaultMonth={selected}
                    onSelect={(date) => {
                        onValueChange(date ? format(date, 'yyyy-MM-dd') : '');
                        setOpen(false);
                    }}
                    autoFocus
                />
                <div className="flex items-center justify-between gap-2 border-t p-3">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={!selected}
                        onClick={() => {
                            onValueChange('');
                            setOpen(false);
                        }}
                    >
                        Clear
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        onClick={() => {
                            onValueChange(format(new Date(), 'yyyy-MM-dd'));
                            setOpen(false);
                        }}
                    >
                        Today
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}

export { DatePicker };
