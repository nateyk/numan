import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/two-factor/login';
import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useMemo, useState } from 'react';

export default function TwoFactorChallenge() {
    const { t } = useTranslations();
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');

    const authConfigContent = useMemo<{
        title: string;
        description: string;
        toggleText: string;
    }>(() => {
        if (showRecoveryInput) {
            return {
                title: t(
                    'auth.two_factor_challenge.recovery_code.title',
                    'Recovery code',
                ),
                description: t(
                    'auth.two_factor_challenge.recovery_code.description',
                    'Enter one of your emergency recovery codes to access your account.',
                ),
                toggleText: t(
                    'auth.two_factor_challenge.recovery_code.toggle_text',
                    'login using an authentication code',
                ),
            };
        }

        return {
            title: t(
                'auth.two_factor_challenge.authentication_code.title',
                'Two-factor code',
            ),
            description: t(
                'auth.two_factor_challenge.authentication_code.description',
                'Enter the 6-digit code from your authenticator app.',
            ),
            toggleText: t(
                'auth.two_factor_challenge.authentication_code.toggle_text',
                'login using a recovery code',
            ),
        };
    }, [showRecoveryInput, t]);

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <AuthLayout
            title={authConfigContent.title}
            description={authConfigContent.description}
        >
            <Head
                title={t(
                    'auth.two_factor_challenge.page_title',
                    'Two-factor authentication',
                )}
            />

            <Form
                {...store.form()}
                className="flex flex-col gap-4"
                resetOnError
                resetOnSuccess={!showRecoveryInput}
            >
                {({ errors, processing, clearErrors }) => (
                    <>
                        {showRecoveryInput ? (
                            <div className="grid gap-1.5">
                                <Input
                                    name="recovery_code"
                                    type="text"
                                    placeholder={t(
                                        'auth.two_factor_challenge.recovery_code.placeholder',
                                        'Enter recovery code',
                                    )}
                                    autoFocus={showRecoveryInput}
                                    required
                                />
                                <InputError message={errors.recovery_code} />
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center gap-2">
                                <div className="flex w-full items-center justify-center">
                                    <InputOTP
                                        name="code"
                                        maxLength={OTP_MAX_LENGTH}
                                        value={code}
                                        onChange={(value) => setCode(value)}
                                        disabled={processing}
                                        pattern={REGEXP_ONLY_DIGITS}
                                    >
                                        <InputOTPGroup>
                                            {Array.from(
                                                { length: OTP_MAX_LENGTH },
                                                (_, index) => (
                                                    <InputOTPSlot
                                                        key={index}
                                                        index={index}
                                                    />
                                                ),
                                            )}
                                        </InputOTPGroup>
                                    </InputOTP>
                                </div>
                                <InputError message={errors.code} />
                                <p className="text-xs text-muted-foreground">
                                    6 digits — expires shortly
                                </p>
                            </div>
                        )}

                        <Button
                            type="submit"
                            className="w-full"
                            size="lg"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {t(
                                'auth.two_factor_challenge.continue',
                                'Continue',
                            )}
                        </Button>

                        <div className="text-center text-sm text-muted-foreground">
                            <span>
                                {t(
                                    'auth.two_factor_challenge.or_you_can',
                                    'or you can',
                                )}{' '}
                            </span>
                            <button
                                type="button"
                                className="cursor-pointer text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors hover:decoration-current dark:decoration-neutral-500"
                                onClick={() => toggleRecoveryMode(clearErrors)}
                            >
                                {authConfigContent.toggleText}
                            </button>
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
