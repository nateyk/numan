import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import AuthLayout from '@/layouts/auth-layout';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { Form, Head } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';

interface VerifyEmailProps {
    status?: string;
}

export default function VerifyEmail({ status }: VerifyEmailProps) {
    const { t } = useTranslations();
    const isSent = status === 'verification-link-sent';

    return (
        <AuthLayout
            title={t('auth.verify_email.title', 'Verify your email')}
            description={t(
                'auth.verify_email.description',
                'Thanks for joining NumanFreight. Verify your email to continue moving freight with more confidence. Check your inbox for the link.',
            )}
        >
            <Head
                title={t('auth.verify_email.page_title', 'Email verification')}
            />

            <div className="flex flex-col gap-4">
                <div className="flex justify-center">
                    <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                        <MailCheck className="size-6 text-muted-foreground" />
                    </div>
                </div>

                {isSent && (
                    <div
                        role="status"
                        className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-center text-sm font-medium text-green-700 dark:border-green-900/40 dark:bg-green-950/40 dark:text-green-300"
                    >
                        {t(
                            'auth.verify_email.link_sent',
                            'A new verification link has been sent to the email address you provided during registration.',
                        )}
                    </div>
                )}

                {!isSent && status && (
                    <div
                        role="status"
                        className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-center text-sm font-medium text-green-700 dark:border-green-900/40 dark:bg-green-950/40 dark:text-green-300"
                    >
                        {status}
                    </div>
                )}

                <Form {...send.form()} className="flex flex-col gap-3">
                    {({ processing }) => (
                        <>
                            <Button
                                disabled={processing}
                                variant="default"
                                size="lg"
                                className="w-full"
                                type="submit"
                            >
                                {processing && <Spinner />}
                                {t(
                                    'auth.verify_email.button',
                                    'Resend verification email',
                                )}
                            </Button>

                            <p className="text-center text-xs text-muted-foreground">
                                Didn&apos;t receive it? Check spam, or resend
                                after a minute.
                            </p>

                            <TextLink
                                href={logout()}
                                className="mx-auto block text-center text-sm font-medium"
                            >
                                {t('auth.sign_out', 'Sign out')}
                            </TextLink>
                        </>
                    )}
                </Form>
            </div>
        </AuthLayout>
    );
}
