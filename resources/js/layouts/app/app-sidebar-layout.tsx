import { AnnouncementBanner } from '@/components/announcement-banner';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ExperienceFeedbackModal } from '@/components/experience-feedback-modal';
import { FeedbackWidget } from '@/components/feedback-widget';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    contentClassName,
}: PropsWithChildren<{
    breadcrumbs?: BreadcrumbItem[];
    contentClassName?: string;
}>) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className={cn('overflow-x-hidden', contentClassName)}
            >
                <ImpersonationBanner />
                <AnnouncementBanner />
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
            <FeedbackWidget />
            <ExperienceFeedbackModal />
        </AppShell>
    );
}
