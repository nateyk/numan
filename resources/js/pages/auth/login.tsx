import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import AuthLayout from '@/layouts/auth-layout';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/react';
import { Eye, EyeOff, Fingerprint } from 'lucide-react';
import { useState } from 'react';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    canRegister?: boolean;
    canRegisterSocialite?: boolean;
    email?: string;
    redirect?: string;
}

export default function Login({
    status,
    canResetPassword,
    canRegister = true,
    email,
    redirect,
}: LoginProps) {
    const { t } = useTranslations();
    const [showPassword, setShowPassword] = useState<boolean>(false);

    const registerUrl = (): string => {
        const baseUrl = register.url();
        const params = new URLSearchParams();
        if (email) params.set('email', email);
        if (redirect) params.set('redirect', redirect);
        return params.toString() ? `${baseUrl}?${params.toString()}` : baseUrl;
    };

    const hasPasskeys = (): boolean => {
        // Guard: only render if @laravel/passkeys is available — check for Fortify passkey route existence at runtime
        // We do not import a missing package; this keeps build green when passkeys not installed.
        try {
            // Wayfinder will generate a passkey route helper if installed; absence just means this stays false

            const maybe = (globalThis as unknown as Record<string, unknown>)
                .__PASSKEYS_AVAILABLE__;
            return Boolean(maybe);
        } catch {
            return false;
        }
    };

    return (
        <AuthLayout
            title={t('auth.login.title', 'Sign in to NumanFreight')}
            description={t(
                'auth.login.description',
                'Move freight with more confidence. Enter your credentials to continue.',
            )}
        >
            <Head title={t('auth.login.page_title', 'Sign in')} />

            {status && (
                <div
                    role="status"
                    className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-center text-sm font-medium text-green-700 dark:border-green-900/40 dark:bg-green-950/40 dark:text-green-300"
                >
                    {status}
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="email">
                                    {t('auth.email', 'Email address')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus={!email}
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder={t(
                                        'auth.email_placeholder',
                                        'email@example.com',
                                    )}
                                    defaultValue={email}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-1.5">
                                <div className="flex items-center">
                                    <Label htmlFor="password">
                                        {t('auth.password', 'Password')}
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm font-medium"
                                            tabIndex={5}
                                        >
                                            {t(
                                                'auth.forgot_password',
                                                'Forgot password?',
                                            )}
                                        </TextLink>
                                    )}
                                </div>
                                <div className="relative">
                                    <Input
                                        id="password"
                                        type={
                                            showPassword ? 'text' : 'password'
                                        }
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder={t(
                                            'auth.password',
                                            'Password',
                                        )}
                                        autoFocus={!!email}
                                        className="pr-10"
                                    />
                                    <button
                                        type="button"
                                        aria-label={
                                            showPassword
                                                ? 'Hide password'
                                                : 'Show password'
                                        }
                                        onClick={() =>
                                            setShowPassword((v) => !v)
                                        }
                                        className="absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                        tabIndex={-1}
                                    >
                                        {showPassword ? (
                                            <EyeOff className="size-4" />
                                        ) : (
                                            <Eye className="size-4" />
                                        )}
                                    </button>
                                </div>
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label
                                    htmlFor="remember"
                                    className="cursor-pointer text-sm font-normal"
                                >
                                    {t('auth.remember_me', 'Remember me')}
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                size="lg"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                {t('auth.login.button', 'Sign in')}
                            </Button>

                            {hasPasskeys() && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-full"
                                    size="lg"
                                    data-test="passkey-button"
                                >
                                    <Fingerprint className="size-4" />
                                    Continue with passkey
                                </Button>
                            )}

                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                size="lg"
                                asChild
                            >
                                <a href="/magic-login">
                                    Sign in with Magic Link
                                </a>
                            </Button>

                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                size="lg"
                                asChild
                            >
                                <a href={registerUrl()}>Create account</a>
                            </Button>
                        </div>

                        {canRegister && (
                            <p className="text-center text-sm text-muted-foreground">
                                {t('auth.no_account', "Don't have an account?")}{' '}
                                <TextLink
                                    href={registerUrl()}
                                    tabIndex={5}
                                    className="font-semibold"
                                >
                                    {t('auth.sign_up', 'Sign up')}
                                </TextLink>
                            </p>
                        )}

                        <p className="text-center text-xs text-muted-foreground">
                            By continuing you agree to our{' '}
                            <a
                                href="#"
                                className="underline decoration-border underline-offset-4 hover:decoration-foreground"
                            >
                                Terms
                            </a>{' '}
                            and{' '}
                            <a
                                href="#"
                                className="underline decoration-border underline-offset-4 hover:decoration-foreground"
                            >
                                Privacy
                            </a>
                            .
                        </p>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
