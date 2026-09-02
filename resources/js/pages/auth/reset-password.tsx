import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import AuthLayout from '@/layouts/auth-layout';
import { update } from '@/routes/password';
import { Form, Head } from '@inertiajs/react';

interface ResetPasswordProps {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { t } = useTranslations();

    return (
        <AuthLayout
            title={t('auth.reset_password.title', 'Set a new password')}
            description={t(
                'auth.reset_password.description',
                'Choose a strong password for your NumanFreight account.',
            )}
        >
            <Head
                title={t('auth.reset_password.page_title', 'Reset password')}
            />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-4"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="email">
                                {t('auth.reset_password.email', 'Email')}
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                value={email}
                                className="bg-muted"
                                readOnly
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="password">
                                {t('auth.password', 'New password')}
                            </Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                required
                                placeholder={t('auth.password', 'Password')}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="password_confirmation">
                                {t(
                                    'auth.reset_password.confirm_password',
                                    'Confirm password',
                                )}
                            </Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                                placeholder={t(
                                    'auth.reset_password.confirm_password',
                                    'Confirm password',
                                )}
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            size="lg"
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            {t('auth.reset_password.button', 'Reset password')}
                        </Button>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
