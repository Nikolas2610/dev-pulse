import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    token: string;
    email: string;
    name: string;
    organizationName?: string | null;
    workspaceName?: string | null;
    passwordRules: string;
};

export default function AcceptInvitation({
    token,
    email,
    name,
    organizationName,
    workspaceName,
    passwordRules,
}: Props) {
    return (
        <>
            <Head title="Accept invitation" />

            <Form
                action="/invitations/accept"
                method="post"
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="rounded-lg border bg-muted/40 p-4 text-sm">
                            <p className="font-medium">Welcome, {name}</p>
                            <p className="mt-1 text-muted-foreground">
                                Set up your account to join{' '}
                                {organizationName ?? 'your team'}
                                {workspaceName
                                    ? ` in the ${workspaceName} workspace`
                                    : ''}
                                .
                            </p>
                        </div>

                        <InputError message={errors.token} />

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                value={email}
                                autoComplete="email"
                                readOnly
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Create password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoFocus
                                autoComplete="new-password"
                                placeholder="Password"
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm password
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                placeholder="Confirm password"
                                passwordrules={passwordRules}
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button type="submit" className="w-full">
                            {processing && <Spinner />}
                            Accept invitation
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

AcceptInvitation.layout = {
    title: 'Accept your invitation',
    description: 'Create your password to start using DevPulse',
};
