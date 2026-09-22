import { Plus, Trash2 } from 'lucide-react';
import {
    FormCheckbox,
    FormField,
    FormSelect,
} from '@/components/forms/form-components';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

export type TemplateQuestionForm = {
    id?: number;
    question_text: string;
    question_key: string;
    work_item_relation?: string | null;
    work_item_providers?: Array<'jira' | 'bitbucket'> | null;
    answer_type: string;
    options_json?: string[] | null;
    depends_on_question_id?: number | null;
    depends_on_question_index?: number | null;
    depends_on_operator?: string | null;
    depends_on_value?: string | null;
    is_required: boolean;
    sort_order: number;
};

type QuestionEditorProps = {
    questions: TemplateQuestionForm[];
    onChange: (questions: TemplateQuestionForm[]) => void;
    availableWorkItemProviders?: Array<'jira' | 'bitbucket'>;
};

export function QuestionEditor({
    questions,
    onChange,
    availableWorkItemProviders = [],
}: QuestionEditorProps) {
    const setQuestion = (
        index: number,
        field: keyof TemplateQuestionForm,
        value: string | boolean | number | string[] | null,
    ) => {
        setQuestionFields(index, { [field]: value });
    };

    const setQuestionFields = (
        index: number,
        fields: Partial<TemplateQuestionForm>,
    ) => {
        onChange(
            questions.map((question, questionIndex) =>
                questionIndex === index ? { ...question, ...fields } : question,
            ),
        );
    };

    const addQuestion = () => {
        onChange([
            ...questions,
            {
                question_text: '',
                question_key: '',
                work_item_relation: null,
                work_item_providers: [],
                answer_type: 'textarea',
                options_json: null,
                depends_on_question_id: null,
                depends_on_question_index: null,
                depends_on_operator: null,
                depends_on_value: '',
                is_required: true,
                sort_order: questions.length + 1,
            },
        ]);
    };

    const removeQuestion = (index: number) => {
        onChange(
            questions
                .filter((_, questionIndex) => questionIndex !== index)
                .map((question, questionIndex) => {
                    const dependencyIndex = question.depends_on_question_index;
                    const nextDependencyIndex =
                        dependencyIndex === null ||
                        dependencyIndex === undefined
                            ? null
                            : dependencyIndex === index
                              ? null
                              : dependencyIndex > index
                                ? dependencyIndex - 1
                                : dependencyIndex;

                    return {
                        ...question,
                        depends_on_question_index:
                            nextDependencyIndex !== null &&
                            nextDependencyIndex < questionIndex
                                ? nextDependencyIndex
                                : null,
                        sort_order: questionIndex + 1,
                    };
                }),
        );
    };

    const choiceOptions = (question: TemplateQuestionForm): string[] =>
        question.options_json?.length ? question.options_json : [''];

    const setChoiceOption = (
        questionIndex: number,
        optionIndex: number,
        value: string,
    ) => {
        const options = [...choiceOptions(questions[questionIndex])];
        options[optionIndex] = value;
        setQuestion(questionIndex, 'options_json', options);
    };

    const addChoiceOption = (questionIndex: number) => {
        setQuestion(questionIndex, 'options_json', [
            ...choiceOptions(questions[questionIndex]),
            '',
        ]);
    };

    const removeChoiceOption = (questionIndex: number, optionIndex: number) => {
        const options = choiceOptions(questions[questionIndex]).filter(
            (_, index) => index !== optionIndex,
        );
        setQuestion(
            questionIndex,
            'options_json',
            options.length ? options : [''],
        );
    };

    const toggleWorkItemProvider = (
        questionIndex: number,
        provider: 'jira' | 'bitbucket',
        checked: boolean,
    ) => {
        const providers = questions[questionIndex].work_item_providers ?? [];
        const nextProviders = checked
            ? [...new Set([...providers, provider])]
            : providers.filter((value) => value !== provider);

        setQuestion(
            questionIndex,
            'work_item_providers',
            nextProviders as Array<'jira' | 'bitbucket'>,
        );
    };

    const reportingKeyOptions = [
        { label: 'Yesterday', value: 'yesterday' },
        { label: 'Today', value: 'today' },
        { label: 'Blocker', value: 'blocker' },
        { label: 'Blocker details', value: 'blocker_detail' },
        { label: 'Mood', value: 'mood' },
        { label: 'Other', value: 'other' },
    ];

    const answerTypeOptions = [
        { label: 'Long text', value: 'textarea' },
        { label: 'Short text', value: 'text' },
        { label: 'Yes/no', value: 'boolean' },
        { label: 'Single choice', value: 'select' },
        { label: 'Multiple choice', value: 'multi_select' },
        { label: 'Number', value: 'number' },
    ];

    const workItemRelationOptions = [
        { label: 'No work item selector', value: '' },
        { label: 'Worked on yesterday', value: 'worked_on' },
        { label: 'Planned for today', value: 'planned' },
        { label: 'Blocking work', value: 'blocker' },
        { label: 'Review work', value: 'review' },
    ];

    return (
        <div className="min-w-0 space-y-3">
            <div className="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <Label>Standup questions</Label>
                    <p className="text-xs text-muted-foreground">
                        These are sent to members when the standup opens.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    className="sm:w-auto"
                    onClick={addQuestion}
                >
                    Add question
                </Button>
            </div>

            <div className="space-y-3">
                {questions.map((question, index) => (
                    <div
                        key={question.id ?? `new-${index}`}
                        className="rounded-lg border bg-background p-4"
                    >
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <div className="text-xs font-semibold tracking-[0.16em] text-primary uppercase">
                                Question {index + 1}
                            </div>
                            {questions.length > 1 && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => removeQuestion(index)}
                                >
                                    Remove
                                </Button>
                            )}
                        </div>

                        <div className="space-y-3">
                            <FormField
                                label="Question text"
                                value={question.question_text}
                                onChange={(event) =>
                                    setQuestion(
                                        index,
                                        'question_text',
                                        event.target.value,
                                    )
                                }
                                placeholder="What are you focusing on today?"
                            />

                            <div className="grid gap-3 md:grid-cols-[1fr_180px]">
                                <FormSelect
                                    label="Reporting key"
                                    value={question.question_key}
                                    onChange={(event) =>
                                        setQuestion(
                                            index,
                                            'question_key',
                                            event.target.value,
                                        )
                                    }
                                    options={reportingKeyOptions}
                                />
                                <FormSelect
                                    label="Answer type"
                                    value={question.answer_type}
                                    onChange={(event) => {
                                        const answerType = event.target.value;

                                        setQuestionFields(index, {
                                            answer_type: answerType,
                                            options_json: [
                                                'select',
                                                'multi_select',
                                            ].includes(answerType)
                                                ? choiceOptions(question)
                                                : null,
                                        });
                                    }}
                                    options={answerTypeOptions}
                                />
                            </div>

                            <FormSelect
                                label="Work item category"
                                value={question.work_item_relation ?? ''}
                                onChange={(event) => {
                                    const relation = event.target.value || null;

                                    setQuestionFields(index, {
                                        work_item_relation: relation,
                                        work_item_providers: relation
                                            ? (question.work_item_providers ??
                                              [])
                                            : [],
                                    });
                                }}
                                options={workItemRelationOptions}
                            />

                            {question.work_item_relation && (
                                <fieldset className="space-y-2">
                                    <div>
                                        <Label>Work item sources</Label>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Members will see items only from
                                            connected integrations selected
                                            here.
                                        </p>
                                    </div>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {(
                                            [
                                                ['jira', 'Jira issues'],
                                                [
                                                    'bitbucket',
                                                    'Bitbucket pull requests',
                                                ],
                                            ] as const
                                        ).map(([provider, label]) => {
                                            const checked = (
                                                question.work_item_providers ??
                                                []
                                            ).includes(provider);
                                            const connected =
                                                availableWorkItemProviders.includes(
                                                    provider,
                                                );

                                            return (
                                                <FormCheckbox
                                                    key={provider}
                                                    label={label}
                                                    checked={checked}
                                                    disabled={!connected}
                                                    description={
                                                        connected
                                                            ? 'Available for this organization.'
                                                            : checked
                                                              ? 'Integration disconnected. This setting is preserved.'
                                                              : `Connect ${provider === 'jira' ? 'Jira' : 'Bitbucket'} to enable it.`
                                                    }
                                                    onCheckedChange={(value) =>
                                                        toggleWorkItemProvider(
                                                            index,
                                                            provider,
                                                            value,
                                                        )
                                                    }
                                                />
                                            );
                                        })}
                                    </div>
                                </fieldset>
                            )}

                            {['select', 'multi_select'].includes(
                                question.answer_type,
                            ) && (
                                <div className="space-y-2">
                                    <div className="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <Label>Answer options</Label>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="sm:w-auto"
                                            onClick={() =>
                                                addChoiceOption(index)
                                            }
                                        >
                                            <Plus />
                                            Add option
                                        </Button>
                                    </div>
                                    <div className="space-y-2">
                                        {choiceOptions(question).map(
                                            (option, optionIndex) => (
                                                <div
                                                    key={optionIndex}
                                                    className="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-2"
                                                >
                                                    <FormField
                                                        label={`Option ${optionIndex + 1}`}
                                                        labelClassName="sr-only"
                                                        value={option}
                                                        onChange={(event) =>
                                                            setChoiceOption(
                                                                index,
                                                                optionIndex,
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        placeholder={
                                                            optionIndex === 0
                                                                ? 'bad'
                                                                : optionIndex ===
                                                                    1
                                                                  ? 'good'
                                                                  : optionIndex ===
                                                                      2
                                                                    ? 'excellent'
                                                                    : 'Option'
                                                        }
                                                        className="flex-1"
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Remove option"
                                                        onClick={() =>
                                                            removeChoiceOption(
                                                                index,
                                                                optionIndex,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                        <span className="sr-only">
                                                            Remove option
                                                        </span>
                                                    </Button>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Each input becomes one selectable
                                        answer.
                                    </p>
                                </div>
                            )}

                            <div className="grid gap-3 md:grid-cols-[1fr_160px]">
                                <FormSelect
                                    label="Show question when"
                                    value={
                                        question.depends_on_question_index ?? ''
                                    }
                                    onChange={(event) => {
                                        const value = event.target.value;

                                        setQuestionFields(index, {
                                            depends_on_question_index:
                                                value === ''
                                                    ? null
                                                    : Number(value),
                                            depends_on_operator:
                                                value === '' ? null : 'equals',
                                            depends_on_value:
                                                value === ''
                                                    ? ''
                                                    : question.depends_on_value ||
                                                      'Yes',
                                        });
                                    }}
                                >
                                    <option value="">Always show</option>
                                    {questions
                                        .slice(0, index)
                                        .map((candidate, candidateIndex) => (
                                            <option
                                                key={
                                                    candidate.id ??
                                                    `candidate-${candidateIndex}`
                                                }
                                                value={candidateIndex}
                                            >
                                                Q{candidateIndex + 1}:{' '}
                                                {candidate.question_text ||
                                                    'Untitled question'}{' '}
                                                equals
                                            </option>
                                        ))}
                                </FormSelect>

                                {question.depends_on_question_index !== null &&
                                    question.depends_on_question_index !==
                                        undefined && (
                                        <FormSelect
                                            label="Answer value"
                                            value={
                                                question.depends_on_value ??
                                                'Yes'
                                            }
                                            onChange={(event) =>
                                                setQuestion(
                                                    index,
                                                    'depends_on_value',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="Yes">Yes</option>
                                            <option value="No">No</option>
                                            <option value="true">true</option>
                                            <option value="false">false</option>
                                        </FormSelect>
                                    )}
                            </div>

                            <FormCheckbox
                                label="Required response"
                                checked={question.is_required}
                                onCheckedChange={(checked) =>
                                    setQuestion(index, 'is_required', checked)
                                }
                                className="w-full sm:w-fit"
                            />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
