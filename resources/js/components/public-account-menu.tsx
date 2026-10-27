import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import { logout } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { edit } from '@/routes/profile';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    LayoutGrid,
    LogOut,
    Package,
    Settings,
    ShieldAlert,
    UserRound,
} from 'lucide-react';

interface PublicAccountMenuProps {
    onNavigate?: () => void;
    className?: string;
}

/**
 * The signed-in account menu on the public surfaces.
 *
 * The public header used to send signed-in users to the dashboard. That is the
 * wrong destination for an individual, whose dashboard is a one-person
 * workspace they never asked to see, so the menu offers their real destinations
 * and keeps the workspace-shaped ones for people who actually have a team.
 */
export function PublicAccountMenu({
    onNavigate,
    className,
}: PublicAccountMenuProps) {
    const { auth, is_individual } = usePage<SharedData>().props;
    const getInitials = useInitials();
    const user = auth.user;

    if (!user) {
        return null;
    }

    const isSuperadmin = user.is_superadmin === true;
    const showWorkspaceLinks = isSuperadmin || !is_individual;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className={className}
                    aria-label="Account menu"
                >
                    <Avatar className="size-7 rounded-full">
                        <AvatarImage
                            src={user.avatar_url || user.avatar}
                            alt={user.name}
                        />
                        <AvatarFallback className="rounded-full bg-muted text-xs">
                            {getInitials(user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden max-w-32 truncate sm:inline">
                        {user.name}
                    </span>
                    <ChevronDown className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="truncate">
                    {user.name}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href="/my-freight" onClick={onNavigate}>
                        <Package className="mr-2" />
                        My Freight
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href="/public-profile" onClick={onNavigate}>
                        <UserRound className="mr-2" />
                        My Profile
                    </Link>
                </DropdownMenuItem>
                {showWorkspaceLinks && (
                    <DropdownMenuItem asChild>
                        <Link href="/dashboard" onClick={onNavigate}>
                            <LayoutGrid className="mr-2" />
                            Dashboard
                        </Link>
                    </DropdownMenuItem>
                )}
                {isSuperadmin && (
                    <DropdownMenuItem asChild>
                        <Link href={adminDashboard()} onClick={onNavigate}>
                            <ShieldAlert className="mr-2" />
                            Admin
                        </Link>
                    </DropdownMenuItem>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={edit()} onClick={onNavigate}>
                        <Settings className="mr-2" />
                        Settings
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        href={logout()}
                        method="post"
                        as="button"
                        onClick={onNavigate}
                    >
                        <LogOut className="mr-2" />
                        Sign out
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
