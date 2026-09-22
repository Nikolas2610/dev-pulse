import {
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from '@testing-library/react';
import type { AnchorHTMLAttributes } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import AtlassianIntegration from '@/pages/integrations/atlassian';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, ...props }: AnchorHTMLAttributes<HTMLAnchorElement>) => (
        <a href={href} {...props} />
    ),
    router: { post: vi.fn(), delete: vi.fn() },
    useForm: (data: unknown) => ({
        data,
        processing: false,
        setData: vi.fn(),
        put: vi.fn(),
    }),
}));

afterEach(cleanup);

describe('Atlassian integration settings tabs', () => {
    it.each([
        ['jira', 'Projects'],
        ['bitbucket', 'Repositories'],
    ] as const)(
        'separates %s connection, resources, and people settings',
        (provider, resourcesTab) => {
            render(
                <AtlassianIntegration
                    provider={provider}
                    integration={{ id: 1, is_active: true, settings: {} }}
                    resources={[]}
                    externalUsers={[]}
                    workspaces={[]}
                    availableConnections={[]}
                />,
            );

            expect(
                screen.getByRole('tab', { name: 'Connection' }),
            ).toBeTruthy();
            expect(
                screen.getByRole('tabpanel', { name: 'Connection' }),
            ).toBeTruthy();
            expect(
                screen.queryByRole('tabpanel', { name: resourcesTab }),
            ).toBeNull();

            fireEvent.click(screen.getByRole('tab', { name: resourcesTab }));

            const resourcesPanel = screen.getByRole('tabpanel', {
                name: resourcesTab,
            });
            expect(
                within(resourcesPanel).getByText('Workspace resources'),
            ).toBeTruthy();
            expect(
                screen.queryByRole('tabpanel', { name: 'People' }),
            ).toBeNull();

            if (provider === 'bitbucket') {
                expect(
                    within(resourcesPanel).getByText('Repository ownership'),
                ).toBeTruthy();
            }

            fireEvent.click(screen.getByRole('tab', { name: 'People' }));

            expect(
                within(
                    screen.getByRole('tabpanel', { name: 'People' }),
                ).getByText('Identity mapping'),
            ).toBeTruthy();
            expect(
                screen.queryByRole('tabpanel', { name: resourcesTab }),
            ).toBeNull();
        },
    );
});
