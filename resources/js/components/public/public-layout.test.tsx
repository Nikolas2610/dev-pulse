import {
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from '@testing-library/react';
import type { AnchorHTMLAttributes } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PublicLayout } from './public-layout';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, ...props }: AnchorHTMLAttributes<HTMLAnchorElement>) => (
        <a href={href} {...props} />
    ),
    usePage: () => ({
        props: {
            auth: { user: null },
            name: 'DevPulse',
        },
    }),
}));

afterEach(cleanup);

describe('PublicLayout mobile navigation', () => {
    it('opens the public navigation in an accessible drawer', async () => {
        render(
            <PublicLayout current="home">
                <main>Public page</main>
            </PublicLayout>,
        );

        const trigger = screen.getByRole('button', { name: 'Open menu' });

        expect(screen.queryByRole('dialog')).toBeNull();
        fireEvent.click(trigger);

        const drawer = await screen.findByRole('dialog');
        const navigation = within(drawer).getByRole('navigation', {
            name: 'Mobile navigation',
        });

        expect(trigger.getAttribute('aria-expanded')).toBe('true');
        expect(
            within(navigation).getByRole('link', { name: /^Product/ }),
        ).toBeTruthy();
        expect(
            within(drawer).getByRole('link', { name: 'Start free' }),
        ).toBeTruthy();
    });
});
