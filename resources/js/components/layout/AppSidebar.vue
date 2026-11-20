<script setup lang="ts">
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { information as systemInformation } from '@/routes/systemsettings';
import { directory } from '@/routes/user-management';
import { index as rolesIndex } from '@/routes/user-management/roles';
import { create as usersCreate } from '@/routes/user-management/users';
import { edit as editPassword } from '@/routes/user-password';
import {
    AlertTriangle,
    ArchiveIcon,
    BarChart2,
    Bell,
    CircleUserRound,
    ClipboardList,
    ClipboardMinus,
    Cog,
    Combine,
    Database,
    Download,
    FileClock,
    FilePlus2,
    FileUp,
    FolderHeart,
    Folders,
    FolderTree,
    HistoryIcon,
    Info,
    LayoutDashboard,
    Package,
    RectangleEllipsis,
    Search,
    Shield,
    ShieldCheck,
    Sparkles,
    Trash2,
    UserPlus,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import NavMain from '@/components/layout/NavMain.vue';
import { type NavItem } from '@/types';
import { filterNavItemsByPermission, usePermissions } from '@/utils/permission';

const navItems: NavItem[] = [
    {
        kind: 'delimiter',
        title: 'Admin',
    },
    {
        kind: 'menuitem',
        title: 'Dashboard',
        icon: LayoutDashboard,
        permission: 'organizeContent',
        children: [
            {
                kind: 'menuitem',
                title: 'Overview',
                href: '/dashboard',
                icon: LayoutDashboard,
                permission: 'organizeContent',
            },
            {
                kind: 'menuitem',
                title: 'Audit Trail',
                href: '/audit-logs',
                icon: HistoryIcon,
                permission: 'viewAuditTrail',
            },
        ],
    },
    {
        kind: 'menuitem',
        title: 'Reports',
        href: '/reports',
        icon: ClipboardMinus,
        permission: 'manageReports',
        children: [
            {
                kind: 'menuitem',
                title: 'Records Inventory',
                href: '/reports/records-inventory',
                icon: ClipboardList,
                permission: 'manageReports',
            },
            {
                kind: 'menuitem',
                title: 'Summary Report',
                href: '/reports/summary',
                icon: BarChart2,
                permission: 'manageReports',
            },
            {
                kind: 'menuitem',
                title: 'Storage Utilization',
                href: '/reports/storage-utilization',
                icon: Database,
                permission: 'manageReports',
            },
            {
                kind: 'menuitem',
                title: 'Document Access Frequency',
                href: '/reports/document-access-frequency',
                icon: Download,
                permission: 'manageReports',
            },
            {
                kind: 'menuitem',
                title: 'Custom Report Builder',
                href: '/reports/custom',
                icon: Cog,
                permission: 'manageReports',
            },
        ],
    },
    {
        kind: 'menuitem',
        title: 'Disposal Management',
        icon: Package,
        permission: 'manageDisposal',
        children: [
            {
                kind: 'menuitem',
                title: 'Disposal Queue',
                href: '/disposal-management',
                icon: ArchiveIcon,
                permission: 'manageDisposal',
            },
            {
                kind: 'menuitem',
                title: 'Disposal Batches',
                href: '/disposal-batches',
                icon: ClipboardMinus,
                permission: 'manageDisposal',
            },
            {
                kind: 'menuitem',
                title: 'Disposal Certificates',
                href: '/disposal-management/certificates',
                icon: FileClock,
                permission: 'manageDisposal',
            },
        ],
    },
    {
        kind: 'menuitem',
        title: 'User Management',
        href: '/user-management/directory',
        icon: Users,
        permission: 'manageUsers',
        children: [
            {
                kind: 'menuitem',
                title: 'Directory',
                href: directory(),
                icon: Users,
                permission: 'manageUsers',
            },
            {
                kind: 'menuitem',
                title: 'Roles',
                href: rolesIndex(),
                icon: Shield,
                permission: 'manageUsers',
            },
            {
                kind: 'menuitem',
                title: 'Provisioning',
                href: usersCreate(),
                icon: UserPlus,
                permission: 'manageUsers',
            },
        ],
    },
    {
        kind: 'delimiter',
        title: 'Platform',
    },
    {
        kind: 'menuitem',
        title: 'Records',
        icon: FolderTree,
        children: [
            {
                kind: 'menuitem',
                title: 'Search',
                href: '/search',
                icon: Search,
                permission: 'organizeContent',
            },
            {
                kind: 'menuitem',
                title: 'All Records',
                href: '/records',
                icon: Folders,
                permission: 'organizeContent',
            },
            {
                kind: 'menuitem',
                title: 'Pending & Unorganized',
                href: '/records/pending-unorganized',
                icon: FileUp,
                permission: 'reviewContent',
            },
            {
                kind: 'menuitem',
                title: 'Bookmarks',
                href: { url: '/records/bookmarks', method: 'get' },
                icon: FolderHeart,
                permission: 'organizeContent',
            },
            {
                kind: 'menuitem',
                title: 'Trash',
                href: { url: '/records/trash', method: 'get' },
                icon: Trash2,
                permission: 'deleteContent',
            },
        ],
    },
    {
        kind: 'menuitem',
        title: 'Requests',
        icon: Combine,
        children: [
            {
                kind: 'menuitem',
                title: 'All Requests',
                href: '/requests',
                icon: FileClock,
                permission: 'manageRequests',
            },
            {
                kind: 'menuitem',
                title: 'My Request',
                href: '/requests/myrequests',
                icon: FilePlus2,
            },
            {
                kind: 'menuitem',
                title: 'Request History',
                href: '/requests/history',
                icon: HistoryIcon,
                permission: 'manageRequests',
            },
        ],
    },
    {
        kind: 'menuitem',
        title: 'System Settings',
        icon: Cog,
        hidePrimary: true,
        children: [
            {
                kind: 'delimiter',
                title: 'User Profile',
            },
            {
                kind: 'menuitem',
                title: 'My Profile',
                href: editProfile(),
                icon: CircleUserRound,
            },
            {
                kind: 'menuitem',
                title: 'Password',
                href: editPassword(),
                icon: RectangleEllipsis,
            },
            {
                kind: 'menuitem',
                title: 'Appearance',
                href: editAppearance(),
                icon: Sparkles,
            },
            {
                kind: 'menuitem',
                title: 'Notifications',
                href: '/accountsettings/notifications',
                icon: Bell,
            },
            {
                kind: 'delimiter',
                title: 'Agency Panel ',
            },

            {
                kind: 'menuitem',
                title: 'Agency Information',
                href: systemInformation(),
                icon: Info,
                permission: 'manageAgencyInformation',
            },
            {
                kind: 'menuitem',
                title: 'Lookup Management',
                href: '/systemsettings/lookup-management',
                icon: Cog,
                permission: 'manageAgencyInformation',
            },
        ],
    },
];

const { auth } = usePermissions();

const mainNavItems = computed<NavItem[]>(() => {
    const filtered = filterNavItemsByPermission(navItems, auth.value);
    let result = filtered;

    const adminIndex = navItems.findIndex(
        (i) => i.kind === 'delimiter' && i.title === 'Admin',
    );
    if (adminIndex !== -1) {
        const nextDelimiterIndex = navItems.findIndex(
            (i, idx) => idx > adminIndex && i.kind === 'delimiter',
        );
        const adminSection = navItems.slice(
            adminIndex + 1,
            nextDelimiterIndex === -1 ? undefined : nextDelimiterIndex,
        );
        const permittedAdmin = filterNavItemsByPermission(
            adminSection,
            auth.value,
        );

        if (permittedAdmin.length === 0) {
            result = filtered.filter(
                (i) => !(i.kind === 'delimiter' && i.title === 'Admin'),
            );
        }
    }

    const delimiterIndex = navItems.findIndex(
        (i) => i.kind === 'delimiter' && i.title === 'System',
    );
    if (delimiterIndex !== -1) {
        const itemsAfterDelimiter = navItems.slice(delimiterIndex + 1);
        const permittedAfter = filterNavItemsByPermission(
            itemsAfterDelimiter,
            auth.value,
        );

        if (permittedAfter.length === 0) {
            result = result.filter(
                (i) => !(i.kind === 'delimiter' && i.title === 'System'),
            );
        }
    }

    return result;
});

const navRef = ref<InstanceType<typeof NavMain> | null>(null);

defineExpose({
    toggleMobile: () => navRef.value?.toggleMobile(),
});
</script>

<template>
    <div class="flex h-screen bg-background">
        <NavMain ref="navRef" :items="mainNavItems" />
    </div>
</template>
