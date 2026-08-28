import { Link } from '@inertiajs/react';
import {
    BookOpen,
    FileCheck2,
    FileText,
    FolderGit2,
    HandCoins,
    KeyRound,
    LayoutGrid,
    Package,
    Receipt,
    ShieldCheck,
    UserCheck,
    UserCog,
    Users,
    Users2,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import apiClients from '@/routes/api-clients';
import apoyos from '@/routes/apoyos';
import beneficiarios from '@/routes/beneficiarios';
import customers from '@/routes/customers';
import facturasCompra from '@/routes/facturas-compra';
import invoices from '@/routes/invoices';
import personasApoya from '@/routes/personas-apoya';
import products from '@/routes/products';
import receipts from '@/routes/receipts';
import roles from '@/routes/roles';
import users from '@/routes/users';
import type { NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { can } = usePermissions();

    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    ];

    if (can('receipts.view')) {
        mainNavItems.push({
            title: 'Recibos',
            href: receipts.index(),
            icon: Receipt,
        });
    }

    if (can('invoices.view')) {
        mainNavItems.push({
            title: 'Facturas',
            href: invoices.index(),
            icon: FileCheck2,
        });
    }

    if (can('customers.view')) {
        mainNavItems.push({
            title: 'Clientes',
            href: customers.index(),
            icon: Users,
        });
    }

    if (can('products.view')) {
        mainNavItems.push({
            title: 'Productos',
            href: products.index(),
            icon: Package,
        });
    }

    const apoyosNavItems: NavItem[] = [];

    if (can('apoyos.view')) {
        apoyosNavItems.push({
            title: 'Apoyo',
            href: apoyos.index(),
            icon: HandCoins,
        });
        apoyosNavItems.push({
            title: 'Beneficiarios',
            href: beneficiarios.index(),
            icon: Users2,
        });
        apoyosNavItems.push({
            title: 'Personas de apoyo',
            href: personasApoya.index(),
            icon: UserCheck,
        });
        apoyosNavItems.push({
            title: 'Facturas de compra',
            href: facturasCompra.index(),
            icon: FileText,
        });
    }

    const adminNavItems: NavItem[] = [];

    if (can('users.manage')) {
        adminNavItems.push({
            title: 'Usuarios',
            href: users.index(),
            icon: UserCog,
        });
    }

    if (can('roles.manage')) {
        adminNavItems.push({
            title: 'Roles y permisos',
            href: roles.index(),
            icon: ShieldCheck,
        });
    }

    if (can('api-clients.manage')) {
        adminNavItems.push({
            title: 'API clients',
            href: apiClients.index(),
            icon: KeyRound,
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                <NavMain items={apoyosNavItems} label="Apoyos" />
                <NavMain items={adminNavItems} label="Administración" />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
