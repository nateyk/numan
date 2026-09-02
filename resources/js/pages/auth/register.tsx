import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';

interface RegisterProps {
    email?: string;
    redirect?: string;
}

export default function Register({ email, redirect }: RegisterProps) {
    const { t } = useTranslations();
    const [agree, setAgree] = useState<boolean>(false);

    const loginUrl = (): string => {
        const baseUrl = login.url();
        const params = new URLSearchParams();
        if (email) params.set('email', email);
        if (redirect) params.set('redirect', redirect);
        return params.toString() ? `${baseUrl}?${params.toString()}` : baseUrl;
    };

    return (
        <AuthLayout
            title={t('auth.register.title', 'Create your NumanFreight account')}
            description={t(
                'auth.register.description',
                'Join the Ethiopian freight marketplace — move freight with more confidence.',
            )}
        >
            <Head title={t('auth.register.page_title', 'Create account')} />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="name">
                                    {t('auth.name', 'Full name')}
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder={t(
                                        'auth.name_placeholder',
                                        'Full name',
                                    )}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="email">
                                    {t('auth.email', 'Email address')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder={t(
                                        'auth.email_placeholder',
                                        'email@example.com',
                                    )}
                                    defaultValue={email}
                                    readOnly={!!email}
                                    className={email ? 'bg-muted' : undefined}
                                />
                                <InputError message={errors.email} />
                                {email && (
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'auth.register.email_from_invitation',
                                            'This email is from your invitation and cannot be changed.',
                                        )}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="password">
                                    {t('auth.password', 'Password')}
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder={t('auth.password', 'Password')}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="password_confirmation">
                                    {t(
                                        'auth.confirm_password',
                                        'Confirm password',
                                    )}
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder={t(
                                        'auth.confirm_password',
                                        'Confirm password',
                                    )}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex items-start gap-2 rounded-md border bg-muted/40 p-3">
                                <Checkbox
                                    id="agree"
                                    checked={agree}
                                    onCheckedChange={(v) =>
                                        setAgree(v === true)
                                    }
                                    aria-invalid={
                                        errors.terms ? true : undefined
                                    }
                                    className="mt-0.5"
                                />
                                <Label
                                    htmlFor="agree"
                                    className="text-xs leading-relaxed font-normal"
                                >
                                    I agree to the{' '}
                                    <a
                                        href="#"
                                        className="font-medium underline decoration-border underline-offset-4 hover:decoration-foreground"
                                    >
                                        Terms
                                    </a>
                                    ,{' '}
                                    <a
                                        href="#"
                                        className="font-medium underline decoration-border underline-offset-4 hover:decoration-foreground"
                                    >
                                        Privacy Policy
                                    </a>{' '}
                                    and{' '}
                                    <a
                                        href="#"
                                        className="font-medium underline decoration-border underline-offset-4 hover:decoration-foreground"
                                    >
                                        Marketplace Rules
                                    </a>
                                    .
                                </Label>
                            </div>
                            {errors.terms && (
                                <InputError message={errors.terms} />
                            )}

                            <Button
                                type="submit"
                                className="w-full"
                                size="lg"
                                tabIndex={5}
                                disabled={processing || !agree}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                {t('auth.register.button', 'Create account')}
                            </Button>
                            {/* Mirror agree to form payload via hidden checkbox name expected by backend — Fortify ignores but keeps error slot */}
                            <input
                                type="hidden"
                                name="terms"
                                value={agree ? '1' : ''}
                            />
                        </div>

                        <p className="text-center text-sm text-muted-foreground">
                            {t('auth.have_account', 'Already have an account?')}{' '}
                            <TextLink
                                href={loginUrl()}
                                tabIndex={6}
                                className="font-semibold"
                            >
                                {t('auth.log_in', 'Sign in')}
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
