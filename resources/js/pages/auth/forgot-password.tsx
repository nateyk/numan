import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { email } from '@/routes/password';
import { Form, Head } from '@inertiajs/react';

interface ForgotPasswordProps {
    status?: string;
}

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    const { t } = useTranslations();

    return (
        <AuthLayout
            title={t('auth.forgot_password.title', 'Forgot your password?')}
            description={t(
                'auth.forgot_password.description',
                'Enter your email and we will send you a password reset link. It expires quickly for your safety.',
            )}
        >
            <Head
                title={t('auth.forgot_password.page_title', 'Forgot password')}
            />

            {status && (
                <div
                    role="status"
                    className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-center text-sm font-medium text-green-700 dark:border-green-900/40 dark:bg-green-950/40 dark:text-green-300"
                >
                    {status}
                </div>
            )}

            <Form {...email.form()} className="flex flex-col gap-4">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-1.5">
                            <Label htmlFor="email">
                                {t('auth.email', 'Email address')}
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                autoFocus
                                required
                                placeholder={t(
                                    'auth.email_placeholder',
                                    'email@example.com',
                                )}
                            />
                            <InputError message={errors.email} />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            size="lg"
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing && <Spinner />}
                            {t(
                                'auth.forgot_password.button',
                                'Send reset link',
                            )}
                        </Button>

                        <p className="text-center text-sm text-muted-foreground">
                            {t(
                                'auth.reset_password.or_return',
                                'Or, return to',
                            )}{' '}
                            <TextLink href={login()} className="font-semibold">
                                {t('auth.log_in', 'Sign in')}
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
