import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import { FormMessage } from '@/components/forms/form-components';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export const defaultReminderTemplate =
    'Reminder: {title} ends in {time_remaining} at {end_time}. {url}';

export function ReminderSettings({
    offsets,
    template,
    endTime,
    offsetsError,
    templateError,
    onOffsetsChange,
    onTemplateChange,
}: {
    offsets: number[];
    template: string;
    endTime: string;
    offsetsError?: string;
    templateError?: string;
    onOffsetsChange: (offsets: number[]) => void;
    onTemplateChange: (template: string) => void;
}) {
    const [newOffset, setNewOffset] = useState('');
    const addOffset = () => {
        const value = Number(newOffset);

        if (!Number.isInteger(value) || value <= 0) {
            return;
        }

        onOffsetsChange(
            Array.from(new Set([...offsets, value])).sort((a, b) => b - a),
        );
        setNewOffset('');
    };
    const preview = template
        .replaceAll('{title}', 'Daily Focus')
        .replaceAll('{time_remaining}', `${offsets.at(-1) ?? 5} minutes`)
        .replaceAll('{end_time}', endTime || '09:30')
        .replaceAll('{url}', 'https://devpulse.test/standup');

    return (
        <div className="min-w-0 space-y-4 rounded-lg border bg-muted/20 p-4">
            <div>
                <Label>Reminders before end</Label>
                <p className="mt-1 text-xs text-muted-foreground">
                    Only teammates who have not submitted receive these private
                    reminders.
                </p>
            </div>
            <div className="flex flex-wrap gap-2">
                {offsets.map((offset) => (
                    <span
                        key={offset}
                        className="inline-flex items-center gap-1 rounded-full border bg-background px-3 py-1 text-sm"
                    >
                        {offset} min
                        <button
                            type="button"
                            aria-label={`Remove ${offset} minute reminder`}
                            onClick={() =>
                                onOffsetsChange(
                                    offsets.filter((value) => value !== offset),
                                )
                            }
                            className="rounded-full p-0.5 hover:bg-muted"
                        >
                            <X className="size-3" />
                        </button>
                    </span>
                ))}
            </div>
            <div className="flex max-w-xs flex-col gap-2 min-[420px]:flex-row">
                <Input
                    type="number"
                    min={1}
                    placeholder="Minutes"
                    value={newOffset}
                    onChange={(event) => setNewOffset(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            addOffset();
                        }
                    }}
                />
                <Button
                    type="button"
                    variant="outline"
                    className="min-[420px]:w-auto"
                    onClick={addOffset}
                >
                    <Plus /> Add
                </Button>
            </div>
            <FormMessage>{offsetsError}</FormMessage>
            <div className="space-y-2">
                <Label htmlFor="reminder-message-template">
                    Reminder message
                </Label>
                <textarea
                    id="reminder-message-template"
                    rows={3}
                    value={template}
                    onChange={(event) => onTemplateChange(event.target.value)}
                    className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <p className="text-xs break-words text-muted-foreground">
                    Available: {'{title}'}, {'{time_remaining}'}, {'{end_time}'}
                    , {'{url}'}
                </p>
                <FormMessage>{templateError}</FormMessage>
            </div>
            <div className="overflow-hidden rounded-md bg-background p-3 text-sm break-words">
                <span className="font-medium">Preview: </span>
                {preview}
            </div>
        </div>
    );
}
