import InputError from '@/components/input-error';
import LocationSearchInput from '@/components/maps/location-search-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { toast } from 'sonner';

interface ProfileSetupForm {
    display_name: string;
    account_type: string;
    phone: string;
    provides_transport: boolean;
    primary_location_id: number | null;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onComplete: () => void;
}

const ACCOUNT_TYPES = [
    { value: 'personal', label: 'Individual' },
    { value: 'business', label: 'Business' },
];

/**
 * The profile-completion step behind the first Post Freight click.
 *
 * It asks only for what publishing a load needs: a name, an account type, a
 * phone number, and — when the person also carries freight — a base. Everything
 * else stays on /public-profile.
 */
export function ProfileSetupDialog({ open, onOpenChange, onComplete }: Props) {
    const { profile_setup } = usePage<SharedData>().props;
    const profile = profile_setup?.profile;

    const form = useForm<ProfileSetupForm>({
        display_name: profile?.display_name ?? '',
        account_type: profile?.account_type ?? 'personal',
        phone: profile?.phone ?? '',
        provides_transport: profile?.provides_transport ?? false,
        primary_location_id: profile?.primary_location_id ?? null,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post('/public-profile/setup', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Profile ready. You can post freight now.');
                onComplete();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="space-y-5">
                    <DialogHeader>
                        <DialogTitle>Finish your public profile</DialogTitle>
                        <DialogDescription>
                            Carriers need to know who is posting and how to
                            reach you. You can add the rest later.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="setup_display_name">
                                Display name
                            </Label>
                            <Input
                                id="setup_display_name"
                                value={form.data.display_name}
                                onChange={(event) =>
                                    form.setData(
                                        'display_name',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(form.errors.display_name)}
                                autoFocus
                            />
                            <InputError message={form.errors.display_name} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="setup_account_type">
                                Account type
                            </Label>
                            <Select
                                value={form.data.account_type}
                                onValueChange={(value) =>
                                    form.setData('account_type', value)
                                }
                            >
                                <SelectTrigger id="setup_account_type">
                                    <SelectValue placeholder="Choose an account type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {ACCOUNT_TYPES.map((type) => (
                                        <SelectItem
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.account_type} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="setup_phone">Phone number</Label>
                            <Input
                                id="setup_phone"
                                value={form.data.phone}
                                onChange={(event) =>
                                    form.setData('phone', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.phone)}
                            />
                            <p className="text-xs text-muted-foreground">
                                Released only when somebody reveals your
                                contact. It is never printed on the public
                                board.
                            </p>
                            <InputError message={form.errors.phone} />
                        </div>

                        <div className="flex items-start gap-2">
                            <Checkbox
                                id="setup_provides_transport"
                                checked={form.data.provides_transport}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'provides_transport',
                                        checked === true,
                                    )
                                }
                            />
                            <div className="grid gap-1">
                                <Label htmlFor="setup_provides_transport">
                                    I also transport freight
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Adds carrier details to the same profile.
                                </p>
                            </div>
                        </div>

                        {form.data.provides_transport && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="setup_primary_location_id">
                                    Base location
                                </Label>
                                <LocationSearchInput
                                    id="setup_primary_location_id"
                                    initialName=""
                                    onChange={(id) =>
                                        form.setData(
                                            'primary_location_id',
                                            id === 0 ? null : id,
                                        )
                                    }
                                    onSelectLocation={(location) => {
                                        if (location === null) {
                                            form.setData(
                                                'primary_location_id',
                                                null,
                                            );
                                        }
                                    }}
                                    localOnly
                                    type="city"
                                    placeholder="Search your city or town"
                                />
                                <InputError
                                    message={form.errors.primary_location_id}
                                />
                            </div>
                        )}
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Saving…'
                                : 'Save and post freight'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
