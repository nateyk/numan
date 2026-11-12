import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    is_impersonating?: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    external?: boolean;
}

export type WorkspaceRole = 'owner' | 'admin' | 'member' | 'viewer';

export interface Workspace {
    id: number;
    name: string;
    slug: string;
    logo: string | null;
    logo_url: string | null;
    accent_color: string | null;
    billing_email?: string | null;
    personal_workspace: boolean;
    owner_id?: number;
    plan?: string;
    role?: WorkspaceRole;
    is_current?: boolean;
    members_count?: number;
}

export interface WorkspaceInvitation {
    id: number;
    email: string;
    role: WorkspaceRole;
    expires_at: string;
    created_at: string;
}

export interface WorkspaceInviteLink {
    id: number;
    token: string;
    role: WorkspaceRole;
    max_uses: number | null;
    uses_count: number;
    expires_at: string | null;
    created_at: string;
    url: string;
}

export interface TeamMember {
    id: number;
    name: string;
    email: string;
    role: WorkspaceRole;
    permissions?: string[];
    joined_at: string;
    is_current_user: boolean;
    bio?: string | null;
    timezone?: string;
    last_seen_at?: string | null;
}

export interface Plan {
    id: string;
    name: string;
    description: string;
    price: {
        weekly: number;
        monthly: number;
        yearly: number;
    };
    price_etb?: {
        weekly: number;
        monthly: number;
        yearly: number;
    };
    price_weekly_etb?: number;
    features: string[];
    limits: {
        workspaces: number;
        team_members: number;
        members?: number;
        storage?: string | number;
    };
    popular?: boolean;
}

export interface Invoice {
    id: string;
    date: string;
    total: string;
    pdf_url: string;
}

export interface Flash {
    success?: string;
    error?: string;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    is_individual: boolean;
    workspace_ui_enabled: boolean;
    currentWorkspace: Workspace | null;
    workspaces: Workspace[];
    sidebarOpen: boolean;
    flash: Flash;
    marketplace: {
        interests_enabled: boolean;
    };
    /**
     * The first-post dialog. `required` is true until the profile can publish a
     * load; `profile` supplies the values it opens with.
     */
    profile_setup?: {
        required: boolean;
        profile: {
            display_name: string;
            account_type: string | null;
            phone: string | null;
            provides_transport: boolean;
            primary_location_id: number | null;
        } | null;
    } | null;
    // Set when an action bounced the user back for a missing phone number.
    requires_phone?: boolean;
    requires_phone_reason?: string | null;
    /**
     * Set when a contact reveal was refused for quota. Top level, not inside
     * flash: HandleInertiaRequests shares it beside requires_phone.
     */
    reveal_blocked?: {
        limit: number;
        used: number;
    } | null;
    locale?: string;
    features?: Record<string, boolean>;
    show_experience_survey?: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    avatar_url?: string | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    current_workspace_id?: number;
    locale?: string;
    is_superadmin?: boolean;
    bio?: string | null;
    timezone?: string;
    date_format?: string;
    profile_completeness?: { score: number; missing: string[] };
    /** Compact public identity; never carries contact fields. */
    public_profile?: {
        slug: string | null;
        display_name: string | null;
        account_type: string | null;
        status: string | null;
        verification_status: string;
        posts_freight: boolean;
        provides_transport: boolean;
    } | null;
    [key: string]: unknown;
}

export interface SecuritySummary {
    authentication: {
        password: {
            enabled: boolean;
            last_changed_at: string | null;
        };
        two_factor: {
            enabled: boolean;
            confirmed_at: string | null;
        };
        social_accounts: Array<{
            id: number;
            provider: string;
            provider_name: string;
            connected_at: string;
        }>;
    };
    security_score: number;
    recommendations: Array<{
        text: string;
        priority: 'high' | 'medium' | 'low';
        action?: string;
    }>;
}

export interface SessionSummary {
    total_sessions: number;
    other_sessions_count: number;
    current_session: {
        id: string;
        ip_address: string;
        device: string;
        platform: string;
        browser: string;
        last_active: string;
    } | null;
    last_other_activity: {
        ip_address: string;
        device: string;
        platform: string;
        last_active: string;
    } | null;
}

declare global {
    interface Window {
        Pusher: typeof import('pusher-js').default;
        Echo: import('laravel-echo').default;
        axios: import('axios').AxiosStatic;
    }
}
