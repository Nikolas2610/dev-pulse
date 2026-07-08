import { Head, Link } from '@inertiajs/react';
import { BookOpen, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    templates: Array<{
        id: number;
        name: string;
        description?: string | null;
        is_active: boolean;
        questions?: unknown[];
        workspace?: { name: string } | null;
    }>;
    canManageTemplates: boolean;
};

export default function TemplatesIndex({
    templates,
    canManageTemplates,
}: Props) {
    return (
        <>
            <Head title="Templates" />
            <div className="dp-page">
                <div className="dp-page-header">
                    <div>
                        <div className="dp-kicker">Standup design</div>
                        <h1 className="dp-title">Question templates</h1>
                        <p className="dp-subtitle">
                            Build reusable question sets for each daily standup.
                            Schedules pick a template, then Slack sends these
                            questions to the workspace.
                        </p>
                    </div>

                    {canManageTemplates && (
                        <Button asChild>
                            <Link href="/templates/create">
                                <Plus />
                                New template
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="dp-panel">
                    <div className="dp-panel-header">
                        <div>
                            <h2 className="font-semibold">
                                Available templates
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {canManageTemplates
                                    ? 'Click a template to edit its questions.'
                                    : 'Templates are read-only for your role.'}
                            </p>
                        </div>
                        <div className="rounded-md bg-accent px-3 py-1 text-sm font-medium text-accent-foreground">
                            {templates.length} total
                        </div>
                    </div>

                    <div>
                        {templates.map((template) => {
                            const row = (
                                <>
                                    <div className="dp-icon-tile mt-1">
                                        <BookOpen className="size-5" />
                                    </div>
                                    <div>
                                        <div className="font-medium">
                                            {template.name}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {template.description ||
                                                'No description'}{' '}
                                            - {template.questions?.length ?? 0}{' '}
                                            questions
                                        </div>
                                    </div>
                                    <div className="text-right text-sm text-muted-foreground">
                                        {template.workspace?.name ??
                                            'All workspaces'}
                                    </div>
                                </>
                            );

                            return canManageTemplates ? (
                                <Link
                                    key={template.id}
                                    href={`/templates/${template.id}/edit`}
                                    className="dp-table-row grid-cols-[auto_1fr_auto]"
                                >
                                    {row}
                                </Link>
                            ) : (
                                <div
                                    key={template.id}
                                    className="dp-table-row grid-cols-[auto_1fr_auto]"
                                >
                                    {row}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </>
    );
}
