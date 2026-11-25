import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { Link } from '@inertiajs/react';
import { ArrowRight, ChevronDown, HelpCircle } from 'lucide-react';
import { useState } from 'react';

export function LandingFaq() {
    const { t } = useTranslations();
    const [openIndex, setOpenIndex] = useState<number | null>(0);

    const faqs = [
        {
            question: t(
                'brand.home.faq.what.question',
                'What is Numan Freight?',
            ),
            answer: t(
                'brand.home.faq.what.answer',
                'Numan Freight is a marketplace for people shipping goods and transporters looking for freight routes in Ethiopia. Shippers post load details, and transporters review the requirements to find suitable work.',
            ),
        },
        {
            question: t(
                'brand.home.faq.browse.question',
                'Do I need an account to browse freight?',
            ),
            answer: t(
                'brand.home.faq.browse.answer',
                'Yes. Freight discovery is for signed-in members. Your subscription and applicable access limits determine whether you can view listings and private contact information.',
            ),
        },
        {
            question: t(
                'brand.home.faq.post.question',
                'What do I need to post a load?',
            ),
            answer: t(
                'brand.home.faq.post.answer',
                'Start with the pickup location, destination, cargo description and truck requirements. The posting flow guides you through the required details.',
            ),
        },
        {
            question: t(
                'brand.home.faq.contact.question',
                'Can everyone see phone numbers and contact details?',
            ),
            answer: t(
                'brand.home.faq.contact.answer',
                'No. Contact details are protected. The platform checks your account access and applicable limits before revealing private information.',
            ),
        },
        {
            question: t(
                'brand.home.faq.plans.question',
                'Where can I see the current subscription prices?',
            ),
            answer: t(
                'brand.home.faq.plans.answer',
                'The plans section on this page displays the current marketplace rates and available billing periods. Your account shows the access you are eligible for.',
            ),
        },
    ];

    return (
        <section
            id="faq"
            className="scroll-mt-20 border-t border-border py-16 sm:py-24"
        >
            <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 sm:px-6 md:grid-cols-[minmax(0,1fr)_minmax(0,1.45fr)] md:gap-14 lg:px-8">
                <div>
                    <span className="text-xs font-semibold tracking-widest text-brand-sky-strong uppercase dark:text-brand-sky">
                        {t('brand.home.faq.eyebrow', 'Common questions')}
                    </span>
                    <h2 className="mt-4 text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                        {t('brand.home.faq.title', 'Questions & answers')}
                    </h2>
                    <p className="mt-4 text-sm leading-7 text-muted-foreground">
                        {t(
                            'brand.home.faq.description',
                            'Learn how load posting, freight browsing and account access work before getting started.',
                        )}
                    </p>
                    <div className="mt-7 rounded-lg border border-border bg-card p-5">
                        <div className="flex items-center gap-3">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-secondary text-brand-sky-strong dark:text-brand-sky">
                                <HelpCircle
                                    aria-hidden="true"
                                    className="size-5"
                                />
                            </span>
                            <div>
                                <p className="text-sm font-semibold">
                                    {t(
                                        'brand.home.faq.next.title',
                                        'Ready to explore?',
                                    )}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {t(
                                        'brand.home.faq.next.description',
                                        'Your freight board is available after sign-in.',
                                    )}
                                </p>
                            </div>
                        </div>
                        <Button asChild className="mt-5 w-full justify-between">
                            <Link href="/freight">
                                {t('brand.home.nav.browse', 'Browse freight')}
                                <ArrowRight
                                    aria-hidden="true"
                                    className="size-4"
                                />
                            </Link>
                        </Button>
                    </div>
                </div>
                <div className="divide-y divide-border border-y border-border">
                    {faqs.map((faq, index) => {
                        const expanded = openIndex === index;
                        const panelId = 'landing-faq-panel-' + index;
                        return (
                            <div key={faq.question}>
                                <h3>
                                    <button
                                        type="button"
                                        aria-expanded={expanded}
                                        aria-controls={panelId}
                                        onClick={() =>
                                            setOpenIndex(
                                                expanded ? null : index,
                                            )
                                        }
                                        className="flex min-h-14 w-full items-center justify-between gap-5 py-4 text-left text-sm font-semibold text-foreground transition-colors outline-none hover:text-brand-sky-strong focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background dark:hover:text-brand-sky"
                                    >
                                        {faq.question}
                                        <ChevronDown
                                            aria-hidden="true"
                                            className={
                                                'size-4 shrink-0 text-muted-foreground transition-transform ' +
                                                (expanded ? 'rotate-180' : '')
                                            }
                                        />
                                    </button>
                                </h3>
                                <div id={panelId} hidden={!expanded}>
                                    <p className="pr-7 pb-5 text-sm leading-7 text-muted-foreground">
                                        {faq.answer}
                                    </p>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
