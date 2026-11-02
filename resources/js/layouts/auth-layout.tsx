import AppLogoIcon from '@/components/app-logo-icon';
import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import CookieConsentBanner from '@/components/cookie-consent-banner';
import { Card, CardContent } from '@/components/ui/card';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { home } from '@/routes';
import { Link } from '@inertiajs/react';

type AuthLayoutVariant = 'centered' | 'split';
type AuthLayoutMaxWidth = 'md' | 'lg';

interface AuthLayoutProps {
    children: React.ReactNode;
    title: string;
    description: string;
    variant?: AuthLayoutVariant;
    maxWidth?: AuthLayoutMaxWidth;
}

function CenteredAuthLayout({
    children,
    title,
    description,
    maxWidth = 'md',
}: Omit<AuthLayoutProps, 'variant'>) {
    const maxWidthClass = maxWidth === 'lg' ? 'max-w-lg' : 'max-w-md';

    return (
        <div className="flex min-h-svh flex-col bg-muted">
            {/* Top bar — mobile + desktop */}
            <header className="flex items-center justify-between border-b bg-background px-4 py-3 sm:px-6">
                <Link
                    href={home()}
                    aria-label="Numan Freight home"
                    className="flex items-center gap-2"
                >
                    <AppLogoIcon
                        alt=""
                        className="size-8 rounded-md object-contain"
                    />
                    <img
                        src="/brand/numanfreight-wordmark.svg"
                        alt="Numan Freight"
                        className="h-6 w-auto max-w-32"
                    />
                </Link>
                <AppearanceToggleDropdown />
            </header>

            <div className="flex flex-1 items-center justify-center p-4 sm:p-6">
                <div className={`w-full ${maxWidthClass}`}>
                    <Card className="rounded-lg border bg-card py-6 shadow-sm">
                        <CardContent className="space-y-6 p-6">
                            <div className="space-y-2 text-center">
                                <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">
                                    {title}
                                </h1>
                                {description ? (
                                    <p className="text-sm text-muted-foreground">
                                        {description}
                                    </p>
                                ) : null}
                            </div>
                            {children}
                        </CardContent>
                    </Card>

                    <p className="mt-6 text-center text-xs text-muted-foreground">
                        <Link
                            href="#"
                            className="underline decoration-border underline-offset-4 hover:decoration-foreground"
                        >
                            Terms
                        </Link>{' '}
                        <span className="mx-1">·</span>
                        <Link
                            href="#"
                            className="underline decoration-border underline-offset-4 hover:decoration-foreground"
                        >
                            Privacy
                        </Link>{' '}
                        <span className="mx-1">·</span>
                        <Link
                            href="#"
                            className="underline decoration-border underline-offset-4 hover:decoration-foreground"
                        >
                            Marketplace Rules
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}

export default function AuthLayout({
    children,
    title,
    description,
    variant = 'centered',
    maxWidth = 'md',
    ...props
}: AuthLayoutProps) {
    if (variant === 'split') {
        return (
            <>
                <AuthLayoutTemplate
                    title={title}
                    description={description}
                    {...props}
                >
                    {children}
                </AuthLayoutTemplate>
                <CookieConsentBanner />
            </>
        );
    }

    return (
        <>
            <CenteredAuthLayout
                title={title}
                description={description}
                maxWidth={maxWidth}
                {...props}
            >
                {children}
            </CenteredAuthLayout>
            <CookieConsentBanner />
        </>
    );
}

export { CenteredAuthLayout };
