import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import {
    FormCheckbox,
    FormField,
    FormSelect,
} from '@/components/forms/form-components';
import { Button } from '@/components/ui/button';
import { defaultTemplateQuestions } from '@/pages/templates/default-questions';
import { QuestionEditor } from '@/pages/templates/question-editor';
import type { TemplateQuestionForm } from '@/pages/templates/question-editor';

type Workspace = {
    id: number;
    name: string;
};

export default function TemplatesCreate({
    workspaces,
}: {
    workspaces: Workspace[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        workspace_id: null as number | null,
        is_default: false,
        is_active: true,
        questions: defaultTemplateQuestions,
    });

    return (
        <>
            <Head title="Create template" />
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
                        <h1 className="dp-title">Create template</h1>
                        <p className="dp-subtitle">
                            Start with the default standup questions, then
                            tailor the prompt flow for the team.
                        </p>
                    </div>
                </div>

                <form
                    className="grid gap-5 xl:grid-cols-[360px_1fr]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        post('/templates');
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
                                placeholder="Morning Focus"
                                error={errors.name}
                            />
                            <FormField
                                label="Description"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                                placeholder="Daily engineering check-in"
                                error={errors.description}
                            />
                            <FormSelect
                                label="Workspace"
                                value={data.workspace_id ?? ''}
                                onChange={(event) =>
                                    setData(
                                        'workspace_id',
                                        event.target.value
                                            ? Number(event.target.value)
                                            : null,
                                    )
                                }
                                error={errors.workspace_id}
                            >
                                <option value="">All workspaces</option>
                                {workspaces.map((workspace) => (
                                    <option
                                        key={workspace.id}
                                        value={workspace.id}
                                    >
                                        {workspace.name}
                                    </option>
                                ))}
                            </FormSelect>
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
                            <Button disabled={processing}>
                                <Save />
                                Create template
                            </Button>
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
