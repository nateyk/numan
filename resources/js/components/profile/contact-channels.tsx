import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import {
    Check,
    Copy,
    Mail,
    MessageCircle,
    Phone,
    type LucideIcon,
} from 'lucide-react';
import { toast } from 'sonner';

export interface ContactChannelsData {
    phone?: string | null;
    whatsapp?: string | null;
    email?: string | null;
    phone_numbers?: string[];
    whatsapp_numbers?: string[];
}

type ContactEntry = {
    kind: 'phone' | 'whatsapp' | 'email';
    label: string;
    value: string;
    href: string | null;
    action: string;
    Icon: LucideIcon;
};

function uniqueValues(values: Array<string | null | undefined>): string[] {
    return [
        ...new Set(values.map((value) => value?.trim() ?? '').filter(Boolean)),
    ];
}

/**
 * WhatsApp uses an international number without the "+" prefix.
 * Translate common Ethiopian local formats only; never guess other countries.
 */
function whatsappLink(number: string): string | null {
    const digits = number.replace(/\D/g, '');
    let international = digits;

    if (digits.startsWith('00')) {
        international = digits.slice(2);
    } else if (/^0[79]\d{8}$/.test(digits)) {
        international = `251${digits.slice(1)}`;
    } else if (/^[79]\d{8}$/.test(digits)) {
        international = `251${digits}`;
    } else if (!number.trim().startsWith('+') && /^0/.test(digits)) {
        return null;
    }

    return /^\d{10,15}$/.test(international)
        ? `https://wa.me/${international}`
        : null;
}

function contactEntries(contact: ContactChannelsData): ContactEntry[] {
    const phones = uniqueValues([
        contact.phone,
        ...(contact.phone_numbers ?? []),
    ]);
    const whatsapp = uniqueValues([
        contact.whatsapp,
        ...(contact.whatsapp_numbers ?? []),
    ]);
    const email = contact.email?.trim();

    return [
        ...phones.map((value) => ({
            kind: 'phone' as const,
            label: 'Phone',
            value,
            href: `tel:${value.replace(/[^+\d]/g, '')}`,
            action: 'Call',
            Icon: Phone,
        })),
        ...whatsapp.map((value) => ({
            kind: 'whatsapp' as const,
            label: 'WhatsApp',
            value,
            href: whatsappLink(value),
            action: 'Chat',
            Icon: MessageCircle,
        })),
        ...(email
            ? [
                  {
                      kind: 'email' as const,
                      label: 'Email',
                      value: email,
                      href: `mailto:${email}`,
                      action: 'Email',
                      Icon: Mail,
                  },
              ]
            : []),
    ];
}

/**
 * Render only the contact payload released by the reveal endpoint.
 * "full" is for cards/detail views; "compact" keeps tables from growing
 * extra nested cards. No API request or automatic reveal occurs here.
 */
export default function ContactChannels({
    contact,
    variant = 'compact',
}: {
    contact: ContactChannelsData;
    variant?: 'compact' | 'full';
}) {
    const [copiedText, copy] = useClipboard();
    const entries = contactEntries(contact);
    const compact = variant === 'compact';

    if (entries.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No shared contact details.
            </p>
        );
    }

    const copyValue = async (entry: ContactEntry) => {
        const didCopy = await copy(entry.value);

        if (didCopy) {
            toast.success(`${entry.label} copied`);
        } else {
            toast.error('Unable to copy. Select the contact to copy it.');
        }
    };

    return (
        <div
            role="list"
            aria-label="Shared contact details"
            className={
                compact
                    ? 'min-w-0 divide-y divide-border'
                    : 'min-w-0 divide-y divide-border overflow-hidden rounded-lg border bg-card'
            }
        >
            {entries.map((entry) => {
                const Icon = entry.Icon;
                return (
                    <div
                        role="listitem"
                        key={`${entry.kind}-${entry.value}`}
                        className={`flex min-w-0 items-center gap-2.5 ${compact ? 'py-2' : 'px-3 py-3'}`}
                    >
                        <div
                            aria-hidden="true"
                            className={`flex shrink-0 items-center justify-center rounded-md bg-muted/70 ${compact ? 'size-8' : 'size-9'}`}
                        >
                            <Icon
                                className={`size-4 ${entry.kind === 'whatsapp' ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'}`}
                            />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-xs text-muted-foreground">
                                {entry.label}
                            </p>
                            <p className="text-sm font-medium break-all text-foreground tabular-nums">
                                {entry.value}
                            </p>
                        </div>
                        <div className="flex shrink-0 items-center gap-1">
                            {entry.href && (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className={
                                        compact ? 'h-8 px-2' : 'h-9 px-3'
                                    }
                                >
                                    <a
                                        href={entry.href}
                                        aria-label={`${entry.action} ${entry.value}`}
                                        {...(entry.kind === 'whatsapp'
                                            ? {
                                                  target: '_blank',
                                                  rel: 'noopener noreferrer',
                                              }
                                            : {})}
                                    >
                                        {entry.action}
                                    </a>
                                </Button>
                            )}
                            {!compact && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-9 shrink-0"
                                    aria-label={`Copy ${entry.label.toLowerCase()} ${entry.value}`}
                                    onClick={() => void copyValue(entry)}
                                >
                                    {copiedText === entry.value ? (
                                        <Check className="size-4 text-emerald-600" />
                                    ) : (
                                        <Copy className="size-4" />
                                    )}
                                </Button>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
