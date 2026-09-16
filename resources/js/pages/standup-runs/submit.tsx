import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CircleSlash, Send } from 'lucide-react';
import { useState } from 'react';
import {
    FormMultiSelect,
    FormSelect,
} from '@/components/forms/form-components';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { ExternalWorkItem } from '@/types';

export default function StandupSubmit({
    run,
    suggestedWorkItems = [],
}: {
    run: any;
    suggestedWorkItems?: ExternalWorkItem[];
}) {
    const [skipOpen, setSkipOpen] = useState(false);
    const [skipReason, setSkipReason] = useState('');
    const questions = run.template?.questions ?? [];
    const { data, setData, post, processing, errors } = useForm({
        answers: Object.fromEntries(
            questions.map((question: any) => [question.id, '']),
        ),
        work_items: {} as Record<string, number[]>,
    });
    const formErrors = errors as Record<string, string>;
    const visibleQuestions = questions.filter((question: any) => {
        if (!question.depends_on_question_id) {
            return true;
        }

        const actual = String(
            (data.answers as Record<string, string>)[
                question.depends_on_question_id
            ] ?? '',
        )
            .trim()
            .toLowerCase();
        const expected = String(question.depends_on_value ?? '')
            .trim()
            .toLowerCase();

        return actual !== '' && actual === expected;
    });

    const setAnswer = (questionId: number, answer: string) => {
        setData('answers', {
            ...(data.answers as Record<string, string>),
            [questionId]: answer,
        });
    };

    const setQuestionWorkItems = (
        questionId: number,
        selectedIds: Array<string | number>,
    ) => {
        setData('work_items', {
            ...(data.work_items as Record<string, number[]>),
            [questionId]: selectedIds.map(Number),
        });
    };

    return (
        <>
            <Head title="Submit standup" />
            <div className="dp-page max-w-4xl">
                <div className="dp-page-header">
                    <div>
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2"
                        >
                            <Link href={`/standups/${run.id}`}>
                                <ArrowLeft />
                                Run detail
                            </Link>
                        </Button>
                        <div className="dp-kicker mt-3">Your update</div>
                        <h1 className="dp-title">{run.template?.name}</h1>
                        <p className="dp-subtitle">
                            {run.workspace?.name} · answer once, then your
                            update appears on the dashboard and summary.
                        </p>
                    </div>
                </div>

                <form
                    className="dp-panel"
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(`/standup-runs/${run.id}/submit`);
                    }}
                >
                    <div className="dp-panel-header">
                        <div>
                            <h2 className="font-semibold">Standup answers</h2>
                            <p className="text-sm text-muted-foreground">
                                Keep answers concise and specific.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing}
                                onClick={() => setSkipOpen(true)}
                            >
                                <CircleSlash />
                                Skip standup
                            </Button>
                            <Button disabled={processing}>
                                <Send />
                                Submit answers
                            </Button>
                        </div>
                    </div>
                    <div className="dp-panel-body space-y-5">
                        {formErrors.standup_run && (
                            <p className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                                {formErrors.standup_run}
                            </p>
                        )}
                        {visibleQuestions.map(
                            (question: any, index: number) => (
                                <div key={question.id} className="space-y-2">
                                    <div className="flex items-center gap-3">
                                        <div className="rounded bg-primary/10 px-2 py-1 text-xs font-semibold text-primary">
                                            {index + 1}
                                        </div>
                                        <Label>{question.question_text}</Label>
                                    </div>
                                    {question.answer_type === 'boolean' ? (
                                        <FormSelect
                                            label={question.question_text}
                                            labelClassName="sr-only"
                                            value={
                                                (data.answers as any)[
                                                    question.id
                                                ]
                                            }
                                            onChange={(event) =>
                                                setAnswer(
                                                    question.id,
                                                    event.target.value,
                                                )
                                            }
                                            options={[
                                                {
                                                    label: 'Choose an answer',
                                                    value: '',
                                                },
                                                { label: 'Yes', value: 'Yes' },
                                                { label: 'No', value: 'No' },
                                            ]}
                                        />
                                    ) : question.answer_type === 'select' ? (
                                        <FormSelect
                                            label={question.question_text}
                                            labelClassName="sr-only"
                                            value={
                                                (data.answers as any)[
                                                    question.id
                                                ]
                                            }
                                            onChange={(event) =>
                                                setAnswer(
                                                    question.id,
                                                    event.target.value,
                                                )
                                            }
                                            options={[
                                                {
                                                    label: 'Choose an answer',
                                                    value: '',
                                                },
                                                ...(
                                                    question.options_json ?? []
                                                ).map((option: string) => ({
                                                    label: option,
                                                    value: option,
                                                })),
                                            ]}
                                        />
                                    ) : question.answer_type ===
                                      'multi_select' ? (
                                        <FormMultiSelect
                                            label={question.question_text}
                                            labelClassName="sr-only"
                                            value={String(
                                                (data.answers as any)[
                                                    question.id
                                                ] ?? '',
                                            )
                                                .split(', ')
                                                .filter(Boolean)}
                                            onValueChange={(options) =>
                                                setAnswer(
                                                    question.id,
                                                    options.join(', '),
                                                )
                                            }
                                            options={(
                                                question.options_json ?? []
                                            ).map((option: string) => ({
                                                label: option,
                                                value: option,
                                            }))}
                                        />
                                    ) : question.answer_type === 'text' ||
                                      question.answer_type === 'number' ? (
                                        <input
                                            type={
                                                question.answer_type ===
                                                'number'
                                                    ? 'number'
                                                    : 'text'
                                            }
                                            className="h-10 w-full rounded-md border bg-background px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                            value={
                                                (data.answers as any)[
                                                    question.id
                                                ]
                                            }
                                            onChange={(event) =>
                                                setAnswer(
                                                    question.id,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    ) : (
                                        <textarea
                                            className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                            value={
                                                (data.answers as any)[
                                                    question.id
                                                ]
                                            }
                                            onChange={(event) =>
                                                setAnswer(
                                                    question.id,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                    {formErrors[`answers.${question.id}`] && (
                                        <p className="text-sm text-destructive">
                                            {
                                                formErrors[
                                                    `answers.${question.id}`
                                                ]
                                            }
                                        </p>
                                    )}
                                    {question.work_item_relation &&
                                        suggestedWorkItems.length > 0 && (
                                            <div className="rounded-lg border bg-muted/20 p-3">
                                                <FormMultiSelect
                                                    label="Jira tickets"
                                                    value={
                                                        (
                                                            data.work_items as Record<
                                                                string,
                                                                number[]
                                                            >
                                                        )[question.id] ?? []
                                                    }
                                                    onValueChange={(value) =>
                                                        setQuestionWorkItems(
                                                            question.id,
                                                            value,
                                                        )
                                                    }
                                                    options={suggestedWorkItems.map(
                                                        (item) => ({
                                                            value: item.id,
                                                            label: `${item.external_key ?? 'Jira'} · ${item.title} · ${item.status}`,
                                                        }),
                                                    )}
                                                    description="Select every ticket that belongs to this answer. You can still add context in the text field above."
                                                    error={
                                                        formErrors[
                                                            `work_items.${question.id}`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        )}
                                </div>
                            ),
                        )}
                    </div>
                </form>
                <Dialog open={skipOpen} onOpenChange={setSkipOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Skip today’s standup?</DialogTitle>
                            <DialogDescription>
                                Add a short reason so the team understands your
                                absence.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-2">
                            <Label htmlFor="skip-reason">Reason</Label>
                            <textarea
                                id="skip-reason"
                                className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                value={skipReason}
                                onChange={(event) =>
                                    setSkipReason(event.target.value)
                                }
                                placeholder="For example: out sick or on leave"
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setSkipOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                disabled={!skipReason.trim() || processing}
                                onClick={() =>
                                    router.post(
                                        `/standup-runs/${run.id}/skip`,
                                        { reason: skipReason },
                                    )
                                }
                            >
                                Confirm skip
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
