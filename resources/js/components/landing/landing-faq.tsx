import { useTranslations } from '@/hooks/use-translations';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';

export function LandingFaq() {
    const { t } = useTranslations();
    const [openIndex, setOpenIndex] = useState<number | null>(null);

    const faqs = [
        {
            question: t(
                'landing.faq.items.what_is.question',
                'What is NumanFreight?',
            ),
            answer: t(
                'landing.faq.items.what_is.answer',
                "NumanFreight is Ethiopia's freight marketplace connecting shippers with verified carriers. Post loads, discover trucks, track shipments, and pay securely in ETB via Chapa — optimized for the Addis–Djibouti corridor and nationwide routes.",
            ),
        },
        {
            question: t(
                'landing.faq.items.tech_stack.question',
                'How do Chapa ETB payments work?',
            ),
            answer: t(
                'landing.faq.items.tech_stack.answer',
                'All payments are in Ethiopian Birr (ETB) via Chapa — supporting cards, mobile money, and bank transfers. Funds are held securely and released on proof-of-delivery or agreed milestones. Transparent fees, instant confirmations, and receipts in ETB.',
            ),
        },
        {
            question: t(
                'landing.faq.items.multiple_projects.question',
                'How do you verify carriers?',
            ),
            answer: t(
                'landing.faq.items.multiple_projects.answer',
                'Every carrier completes KYC — ID, driver license, truck plate, and ownership documents are verified. Verified carriers get a badge, ratings, and priority in discovery so shippers book with confidence.',
            ),
        },
        {
            question: t(
                'landing.faq.items.production_ready.question',
                'Which routes do you cover?',
            ),
            answer: t(
                'landing.faq.items.production_ready.answer',
                'We are optimized for Addis Ababa–Djibouti, including Mojo Dry Port and Dire Dawa, with growing coverage to Kombolcha, Hawassa, Bahir Dar, Mekelle, and all major Ethiopian corridors. Post any domestic load — our network is nationwide.',
            ),
        },
        {
            question: t(
                'landing.faq.items.customization.question',
                'How does tracking work?',
            ),
            answer: t(
                'landing.faq.items.customization.answer',
                'Once a load is assigned, shippers get live status updates — accepted, loaded, in-transit, delivered — with ETB milestone tracking and proof-of-delivery upload. No extra hardware needed; updates work even on low bandwidth.',
            ),
        },
        {
            question: t(
                'landing.faq.items.support.question',
                'What are the fees?',
            ),
            answer: t(
                'landing.faq.items.support.answer',
                'Posting loads is free. A small transparent service fee applies on completed shipments and is shown upfront in ETB before you confirm. No hidden subscriptions — carriers get competitive ETB bids and fast Chapa payouts.',
            ),
        },
        {
            question: t(
                'landing.faq.items.getting_started.question',
                'How do I get started?',
            ),
            answer: t(
                'landing.faq.items.getting_started.answer',
                'Create an account, choose shipper or carrier, and you’re ready. Shippers post a load in under a minute; carriers browse the board and bid in ETB. KYC takes minutes and payouts run via Chapa in ETB.',
            ),
        },
    ];

    return (
        <section id="faq" className="bg-muted/30 py-20 sm:py-32">
            <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                {/* Section Header */}
                <div className="text-center">
                    <h2 className="text-3xl font-bold tracking-tight sm:text-4xl md:text-5xl">
                        {t('landing.faq.title.part1', 'Frequently Asked')}{' '}
                        <span className="text-primary">
                            {t('landing.faq.title.part2', 'Questions')}
                        </span>
                    </h2>
                    <p className="mt-4 text-lg text-muted-foreground">
                        {t(
                            'landing.faq.description',
                            'Everything you need to know about moving freight with NumanFreight in Ethiopia. Chapa payments, verification, tracking, and more.',
                        )}
                    </p>
                </div>

                {/* FAQ Accordion */}
                <div className="mt-10 space-y-2">
                    {faqs.map((faq, index) => (
                        <div
                            key={index}
                            className="overflow-hidden rounded-md border bg-card"
                        >
                            <button
                                onClick={() =>
                                    setOpenIndex(
                                        openIndex === index ? null : index,
                                    )
                                }
                                className="flex w-full items-center justify-between p-4 text-left text-sm font-medium transition-colors hover:bg-muted/50 sm:px-5 sm:py-4"
                            >
                                <span>{faq.question}</span>
                                <ChevronDown
                                    className={`h-5 w-5 shrink-0 text-muted-foreground transition-transform ${
                                        openIndex === index ? 'rotate-180' : ''
                                    }`}
                                />
                            </button>
                            <div
                                className={`grid transition-all duration-300 ease-in-out ${
                                    openIndex === index
                                        ? 'grid-rows-[1fr]'
                                        : 'grid-rows-[0fr]'
                                }`}
                            >
                                <div className="overflow-hidden">
                                    <p className="px-4 pb-4 text-muted-foreground sm:px-6 sm:pb-6">
                                        {faq.answer}
                                    </p>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
