import { Head, useForm } from '@inertiajs/react';
import {
    FormActions,
    FormField,
    TimezoneSelect,
} from '@/components/forms/form-components';
import Heading from '@/components/heading';

export default function OrganizationSettings({ organization }: { organization: any }) {
    const { data, setData, patch, processing } = useForm({
        name: organization?.name ?? '',
        timezone: organization?.timezone ?? 'Asia/Nicosia',
    });

    return (
        <>
            <Head title="Organization Settings" />
            <Heading
                title="Organization"
                description="Manage the current organization defaults."
            />
            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    patch('/settings/organization');
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
