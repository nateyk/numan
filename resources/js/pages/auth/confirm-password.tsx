import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/password/confirm';
import { Form, Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';

export default function ConfirmPassword() {
    const { t } = useTranslations();

    return (
        <AuthLayout
            title={t(
                'auth.confirm_password_page.title',
                'Confirm your password',
            )}
            description={t(
                'auth.confirm_password_page.description',
                'This is a secure area. Please confirm your password before continuing.',
            )}
        >
            <Head
                title={t(
                    'auth.confirm_password_page.page_title',
                    'Confirm password',
                )}
            />

            <div className="mx-auto flex size-10 items-center justify-center rounded-full bg-muted">
                <ShieldCheck className="size-5 text-muted-foreground" />
            </div>

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-4"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="password">
                                {t('auth.password', 'Password')}
                            </Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                placeholder={t('auth.password', 'Password')}
                                autoComplete="current-password"
                                autoFocus
                                required
                            />
                            <InputError message={errors.password} />
                        </div>

                        <Button
                            className="w-full"
                            size="lg"
                            disabled={processing}
                            type="submit"
                            data-test="confirm-password-button"
                        >
                            {processing && <Spinner />}
                            {t(
                                'auth.confirm_password_page.button',
                                'Confirm password',
                            )}
                        </Button>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
