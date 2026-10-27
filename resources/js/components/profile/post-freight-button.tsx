import { ProfileSetupDialog } from '@/components/profile/profile-setup-dialog';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { type ReactNode, useState } from 'react';

interface Props {
    className?: string;
    size?: 'default' | 'sm' | 'lg' | 'icon';
    label?: string;
    onBeforePost?: () => boolean;
    children?: ReactNode;
}

/**
 * The one Post Freight action.
 *
 * Everyone who clicks it goes through the same gate: a guest signs in, a person
 * without a complete profile finishes the dialog first, and a ready profile
 * creates the draft immediately. The quota guard stays with the caller through
 * onBeforePost so the existing limit dialog still opens where it always did.
 */
export function PostFreightButton({
    className,
    size = 'default',
    label = 'Post Freight',
    onBeforePost,
    children,
}: Props) {
    const { auth, profile_setup } = usePage<SharedData>().props;
    const [setupOpen, setSetupOpen] = useState(false);

    const content =
        children ?? (size === 'icon' ? <Plus className="size-4" /> : label);

    const post = () => {
        if (onBeforePost && !onBeforePost()) {
            return;
        }

        router.post('/my-freight');
    };

    if (!auth.user) {
        return (
            <Button
                className={className}
                size={size}
                asChild
                aria-label={size === 'icon' ? label : undefined}
            >
                <Link href="/my-freight">{content}</Link>
            </Button>
        );
    }

    const start = () => {
        if (profile_setup?.required) {
            setSetupOpen(true);
            return;
        }

        post();
    };

    return (
        <>
            <Button
                className={className}
                size={size}
                onClick={start}
                aria-label={size === 'icon' ? label : undefined}
            >
                {content}
            </Button>
            <ProfileSetupDialog
                open={setupOpen}
                onOpenChange={setSetupOpen}
                onComplete={() => {
                    setSetupOpen(false);
                    post();
                }}
            />
        </>
    );
}
