import { useState } from 'react';
import { ChevronDown, HelpCircle } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

interface QAItem {
    q: string;
    a: string;
}

interface Props {
    items?: QAItem[];
    title?: string;
}

const defaultQA: QAItem[] = [
    { q: 'What counts as an active freight post?', a: 'Posts with status Active that haven’t expired (pickup date + flexibility). Drafts don’t count against your limit.' },
    { q: 'How is ETB charged via Chapa?', a: 'We redirect to Chapa checkout (Telebirr, cards). You’ll be charged in ETB and get an ETB receipt. Currency is always ETB — we verify it server-side.' },
    { q: 'Can I cancel or downgrade anytime?', a: 'Yes. Downgrading to Free cancels your active Chapa subscription at period end. No pro-rata refund in pilot.' },
    { q: 'Yearly vs monthly?', a: 'Yearly is ~17% off monthly. Paid once via Chapa, valid 365 days. You can switch interval at renewal.' },
    { q: 'What happens at the limit?', a: 'If you hit Active Posts or Inquiries limit, you’ll see a paywall. Upgrade or wait for posts to expire/close.' },
    { q: 'Is transport fee on-platform?', a: 'No — we sell NumanFreight marketplace access only. Transporter fees are settled off-platform.' },
];

export default function PlanQandA({ items = defaultQA, title = 'Q&A' }: Props) {
    const [open, setOpen] = useState<number | null>(0);

    return (
        <Card className="gap-0 py-0">
            <CardHeader className="px-5 py-4">
                <CardTitle className="flex items-center gap-2 text-base">
                    <HelpCircle className="size-4 text-primary" aria-hidden="true" />
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent className="px-5 pb-5 space-y-2">
                {items.map((item, i) => (
                    <div key={i} className="rounded-lg border">
                        <button
                            type="button"
                            onClick={() => setOpen(open === i ? null : i)}
                            className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-medium hover:bg-muted/50"
                            aria-expanded={open === i}
                            aria-controls={`qa-${i}`}
                        >
                            <span>{item.q}</span>
                            <ChevronDown aria-hidden="true" className={cn('size-4 shrink-0 transition-transform', open === i && 'rotate-180')} />
                        </button>
                        {open === i && (
                            <div id={`qa-${i}`} className="border-t bg-muted/20 px-4 py-3 text-sm leading-relaxed text-muted-foreground">
                                {item.a}
                            </div>
                        )}
                    </div>
                ))}
                <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify({ '@context': 'https://schema.org', '@type': 'FAQPage', mainEntity: items.map((it) => ({ '@type': 'Question', name: it.q, acceptedAnswer: { '@type': 'Answer', text: it.a } })) }) }} />
            </CardContent>
        </Card>
    );
}
