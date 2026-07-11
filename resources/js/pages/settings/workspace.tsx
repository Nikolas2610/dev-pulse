import { Head, useForm } from '@inertiajs/react';
import {
    FormActions,
    FormField,
    TimezoneSelect,
} from '@/components/forms/form-components';
import Heading from '@/components/heading';

export default function WorkspaceSettings({ workspace }: { workspace: any }) {
    const { data, setData, patch, processing } = useForm({
        name: workspace?.name ?? '',
        timezone: workspace?.timezone ?? 'Asia/Nicosia',
    });

    return (
        <>
            <Head title="Workspace Settings" />
            <Heading
                title="Workspace"
                description="Manage the default workspace."
            />
            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    patch('/settings/workspace');
                }}
            >
                <FormField
                    label="Name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                />
                <TimezoneSelect
                    label="Timezone"
                    value={data.timezone}
                    onChange={(event) => setData('timezone', event.target.value)}
                />
                <FormActions submitLabel="Save" processing={processing} />
            </form>
        </>
    );
}
