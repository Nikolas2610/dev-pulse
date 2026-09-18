import { Form, Head } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
    developmentLogin: boolean;
    recentLoginUsers: RecentLoginUser[];
};

type RecentLoginUser = {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'admin' | 'manager' | 'member';
};

const defaultDevelopmentPassword = 'Password-0';

export default function Login({
    status,
    canResetPassword,
    developmentLogin,
    recentLoginUsers,
}: Props) {
    const emailInput = useRef<HTMLInputElement>(null);
    const passwordInput = useRef<HTMLInputElement>(null);
    const [role, setRole] = useState('all');
    const filteredUsers = useMemo(
        () =>
            role === 'all'
                ? recentLoginUsers
                : recentLoginUsers.filter((user) => user.role === role),
        [recentLoginUsers, role],
    );

    const fillDevelopmentCredentials = (email: string) => {
        if (emailInput.current) {
            emailInput.current.value = email;
        }

        if (passwordInput.current) {
            passwordInput.current.value = defaultDevelopmentPassword;
        }

        passwordInput.current?.focus();
    };

    return (
        <>
            <Head title="Log in" />

            <PasskeyVerify />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    ref={emailInput}
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Password</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            Forgot your password?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    ref={passwordInput}
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </Button>
                        </div>

                        {recentLoginUsers.length > 0 && (
                            <div className="grid gap-2 rounded-md border border-dashed p-3">
                                <div className="flex items-center justify-between gap-3">
                                    <div className="text-xs font-medium text-muted-foreground uppercase">
                                        {developmentLogin
                                            ? 'Development users'
                                            : 'Last 5 created users'}
                                    </div>
                                    {developmentLogin && (
                                        <Select
                                            value={role}
                                            onValueChange={setRole}
                                        >
                                            <SelectTrigger
                                                className="h-8 w-32"
                                                aria-label="Filter users by role"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="all">
                                                        All roles
                                                    </SelectItem>
                                                    <SelectItem value="owner">
                                                        Owners
                                                    </SelectItem>
                                                    <SelectItem value="admin">
                                                        Admins
                                                    </SelectItem>
                                                    <SelectItem value="manager">
                                                        Managers
                                                    </SelectItem>
                                                    <SelectItem value="member">
                                                        Members
                                                    </SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    )}
                                </div>
                                <div className="grid max-h-56 gap-1 overflow-y-auto">
                                    {filteredUsers.map((user) => (
                                        <button
                                            key={user.id}
                                            type="button"
                                            className="grid rounded-md px-2 py-1.5 text-left text-sm hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none"
                                            onClick={() =>
                                                fillDevelopmentCredentials(
                                                    user.email,
                                                )
                                            }
                                        >
                                            <span className="flex items-center justify-between gap-2">
                                                <span className="truncate font-medium">
                                                    {user.name}
                                                </span>
                                                {developmentLogin && (
                                                    <span className="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
                                                        {user.role}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {user.email}
                                            </span>
                                        </button>
                                    ))}
                                    {filteredUsers.length === 0 && (
                                        <div className="px-2 py-4 text-center text-xs text-muted-foreground">
                                            No users have this role.
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        <div className="text-center text-sm text-muted-foreground">
                            Don't have an account?{' '}
                            <TextLink href={register()} tabIndex={5}>
                                Sign up
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
