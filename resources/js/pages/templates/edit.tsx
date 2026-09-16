import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { FormCheckbox, FormField } from '@/components/forms/form-components';
import { Button } from '@/components/ui/button';
import { QuestionEditor } from '@/pages/templates/question-editor';
import type { TemplateQuestionForm } from '@/pages/templates/question-editor';

export default function TemplatesEdit({ template }: { template: any }) {
    const templateQuestions = template.questions ?? [];
    const questionIndexById = Object.fromEntries(
        templateQuestions.map((question: any, index: number) => [
            question.id,
            index,
        ]),
    );
    const { data, setData, patch, processing } = useForm({
        name: template.name,
        description: template.description ?? '',
        is_active: template.is_active,
        questions: templateQuestions.map((question: any, index: number) => ({
            id: question.id,
            question_text: question.question_text,
            question_key: question.question_key ?? '',
            work_item_relation: question.work_item_relation ?? null,
            answer_type: question.answer_type ?? 'textarea',
            options_json: question.options_json ?? null,
            depends_on_question_id: question.depends_on_question_id ?? null,
            depends_on_question_index:
                question.depends_on_question_id &&
                questionIndexById[question.depends_on_question_id] !== undefined
                    ? questionIndexById[question.depends_on_question_id]
                    : null,
            depends_on_operator: question.depends_on_operator ?? null,
            depends_on_value: question.depends_on_value ?? '',
            is_required: Boolean(question.is_required),
            sort_order: question.sort_order ?? index + 1,
        })),
    });

    return (
        <>
            <Head title={`Edit ${template.name}`} />
            <div className="dp-page">
                <div className="dp-page-header">
                    <div>
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2"
                        >
                            <Link href="/templates">
                                <ArrowLeft />
                                Templates
                            </Link>
                        </Button>
                        <div className="dp-kicker mt-3">Template editor</div>
                        <h1 className="dp-title">{template.name}</h1>
                        <p className="dp-subtitle">
                            Edit the questions that members will receive when
                            this template is used by a scheduled standup.
                        </p>
                    </div>
                    <Button
                        variant="destructive"
                        onClick={() =>
                            router.delete(`/templates/${template.id}`)
                        }
                    >
                        <Trash2 />
                        Delete template
                    </Button>
                </div>

                <form
                    className="grid gap-5 xl:grid-cols-[360px_1fr]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        patch(`/templates/${template.id}`);
                    }}
                >
                    <div className="dp-panel h-fit">
                        <div className="dp-panel-header">
                            <div>
                                <h2 className="font-semibold">
                                    Template details
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Naming and availability.
                                </p>
                            </div>
                        </div>
                        <div className="dp-panel-body space-y-4">
                            <FormField
                                label="Name"
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                            />
                            <FormField
                                label="Description"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                            />
                            <FormCheckbox
                                label="Active template"
                                checked={Boolean(data.is_active)}
                                onCheckedChange={(checked) =>
                                    setData('is_active', checked)
                                }
                                className="w-fit"
                            />
                        </div>
                    </div>

                    <div className="dp-panel">
                        <div className="dp-panel-header">
                            <div>
                                <h2 className="font-semibold">
                                    Questions sent at standup
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Keep the list focused. These answers power
                                    the dashboard and summary.
                                </p>
                            </div>
                            <Button disabled={processing}>Save changes</Button>
                        </div>
                        <div className="dp-panel-body">
                            <QuestionEditor
                                questions={
                                    data.questions as TemplateQuestionForm[]
                                }
                                onChange={(questions) =>
                                    setData('questions', questions)
                                }
                            />
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}
