import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { FormMultiSelect } from '@/components/forms/form-components';

describe('FormMultiSelect', () => {
    it('renders grouped work item options under provider headings', async () => {
        render(
            <FormMultiSelect
                label="Work items"
                value={[]}
                onValueChange={vi.fn()}
                options={[
                    { value: 1, label: 'DEV-1 · Jira item', group: 'Jira' },
                    {
                        value: 2,
                        label: '#42 · Bitbucket pull request',
                        group: 'Bitbucket',
                    },
                ]}
            />,
        );

        const input = screen.getByRole('combobox', { name: 'Work items' });
        fireEvent.focus(input);
        fireEvent.keyDown(input, { key: 'ArrowDown' });

        expect(await screen.findByText('Jira')).toBeTruthy();
        expect(await screen.findByText('Bitbucket')).toBeTruthy();
        expect(await screen.findByText('DEV-1 · Jira item')).toBeTruthy();
        expect(
            await screen.findByText('#42 · Bitbucket pull request'),
        ).toBeTruthy();
    });
});
