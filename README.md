# DevPulse

DevPulse is a Laravel and React application for team standups, schedules, workspaces, and integration-driven delivery.

## Stack

- Laravel
- Inertia
- React
- TypeScript
- Tailwind CSS

## Development

Install dependencies and run the normal Laravel/Vite workflow for this app.

Set `DEMO_USER_PASSWORD` in your local `.env` before running `php artisan db:seed`.
There is no default demo password: seeding stops before creating data if it is unset.
The local login shortcuts use the same value. Recent users and the demo password
are only included in login responses when `APP_ENV=local`; never use that
setting on a public deployment. Changing this variable does not change passwords
of existing accounts; reset those separately if they used a previous demo password.
