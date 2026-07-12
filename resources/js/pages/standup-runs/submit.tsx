import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CircleSlash, Send } from 'lucide-react';
import {
    FormMultiSelect,
    FormSelect,
} from '@/components/forms/form-components';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { AttachedWorkItem, ExternalWorkItem } from '@/types';

export default function StandupSubmit({
    run,
    suggestedWorkItems = [],
}: {
    run: any;
    suggestedWorkItems?: ExternalWorkItem[];
}) {
    const questions = run.template?.questions ?? [];
    const { data, setData, post, processing, errors } = useForm({
        answers: Object.fromEntries(
            questions.map((question: any) => [question.id, '']),
        ),
        work_items: [] as AttachedWorkItem[],
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

    const setWorkItem = (item: ExternalWorkItem, checked: boolean) => {
        const current = data.work_items as AttachedWorkItem[];
        setData(
            'work_items',
            checked
                ? [
                      ...current,
                      {
                          external_work_item_id: item.id,
                          relation:
                              item.type === 'pull_request'
                                  ? 'review'
                                  : 'planned',
                      },
                  ]
                : current.filter(
                      (selected) => selected.external_work_item_id !== item.id,
                  ),
        );
    };

    const setWorkItemRelation = (
        itemId: number,
        relation: AttachedWorkItem['relation'],
    ) => {
        setData(
            'work_items',
            (data.work_items as AttachedWorkItem[]).map((selected) =>
                selected.external_work_item_id === itemId
                    ? { ...selected, relation }
                    : selected,
            ),
        );
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
                                onClick={() =>
                                    router.post(`/standup-runs/${run.id}/skip`)
                                }
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
                                </div>
                            ),
                        )}
                        {suggestedWorkItems.length > 0 && (
                            <section
                                className="flex flex-col gap-3 border-t pt-5"
                                aria-labelledby="suggested-work-title"
                            >
                                <div>
                                    <h3
                                        id="suggested-work-title"
                                        className="font-semibold"
                                    >
                                        Suggested work
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        Attach Jira issues and pull requests to
                                        this update.
                                    </p>
                                </div>
                                {suggestedWorkItems.map((item) => {
                                    const selected = (
                                        data.work_items as AttachedWorkItem[]
                                    ).find(
                                        (candidate) =>
                                            candidate.external_work_item_id ===
                                            item.id,
                                    );

                                    return (
                                        <div
                                            key={item.id}
                                            className="grid items-start gap-3 rounded-lg border p-4 md:grid-cols-[auto_1fr_180px]"
                                        >
                                            <Checkbox
                                                checked={Boolean(selected)}
                                                onCheckedChange={(checked) =>
                                                    setWorkItem(
                                                        item,
                                                        checked === true,
                                                    )
                                                }
                                                aria-label={`Attach ${item.title}`}
                                            />
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Badge variant="outline">
                                                        {item.provider ===
                                                        'jira'
                                                            ? item.external_key
                                                            : item
                                                                  .bitbucket_repository
                                                                  ?.full_name}
                                                    </Badge>
                                                    {item.is_blocked && (
                                                        <Badge variant="destructive">
                                                            Blocked
                                                        </Badge>
                                                    )}
                                                    <Badge variant="secondary">
                                                        {item.status}
                                                    </Badge>
                                                </div>
                                                <a
                                                    className="mt-1 block font-medium hover:underline"
                                                    href={item.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    {item.title}
                                                </a>
                                            </div>
                                            {selected && (
                                                <FormSelect
                                                    label="Relation"
                                                    value={selected.relation}
                                                    onChange={(event) =>
                                                        setWorkItemRelation(
                                                            item.id,
                                                            event.target
                                                                .value as AttachedWorkItem['relation'],
                                                        )
                                                    }
                                                    options={[
                                                        {
                                                            value: 'worked_on',
                                                            label: 'Worked on',
                                                        },
                                                        {
                                                            value: 'planned',
                                                            label: 'Planned',
                                                        },
                                                        {
                                                            value: 'blocker',
                                                            label: 'Blocker',
                                                        },
                                                        {
                                                            value: 'review',
                                                            label: 'Review',
                                                        },
                                                    ]}
                                                />
                                            )}
                                        </div>
                                    );
                                })}
                                {formErrors.work_items && (
                                    <p className="text-sm text-destructive">
                                        {formErrors.work_items}
                                    </p>
                                )}
                            </section>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}
