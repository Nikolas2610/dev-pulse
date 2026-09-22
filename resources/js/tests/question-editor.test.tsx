import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { QuestionEditor } from '@/pages/templates/question-editor';

describe('QuestionEditor work item providers', () => {
    it('allows only connected providers to be enabled for a question', () => {
        const onChange = vi.fn();

        render(
            <QuestionEditor
                questions={[
                    {
                        question_text: 'What are you focusing on today?',
                        question_key: 'today',
                        work_item_relation: 'planned',
                        work_item_providers: [],
                        answer_type: 'textarea',
                        is_required: true,
                        sort_order: 1,
                    },
                ]}
                availableWorkItemProviders={['jira']}
                onChange={onChange}
            />,
        );

        expect(
            (
                screen.getByRole('checkbox', {
                    name: 'Jira issues',
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(false);
        expect(
            (
                screen.getByRole('checkbox', {
                    name: 'Bitbucket pull requests',
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);

        fireEvent.click(screen.getByRole('checkbox', { name: 'Jira issues' }));

        expect(onChange).toHaveBeenCalledWith([
            expect.objectContaining({ work_item_providers: ['jira'] }),
        ]);
    });
});
