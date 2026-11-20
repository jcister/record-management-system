import { computed, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { Auth, NavItem } from '@/types';

/**
 * Composable for checking user permissions
 * @returns Object with permission checking functions
 */
export function usePermissions() {
    const page = usePage();

    const auth = computed<Auth>(() => page.props.auth as Auth);

    /**
     * Check if the current user has a specific permission
     * @param permission - Permission key to check
     * @returns true if user has the permission, false otherwise
     */
    const can = (permission: string): boolean => {
        return auth.value?.can?.[permission] ?? false;
    };

    /**
     * Check if user has any of the specified permissions
     * @param permissions - Array of permission keys
     * @returns true if user has at least one permission
     */
    const canAny = (...permissions: string[]): boolean => {
        return permissions.some(permission => can(permission));
    };

    /**
     * Check if user has all of the specified permissions
     * @param permissions - Array of permission keys
     * @returns true if user has all permissions
     */
    const canAll = (...permissions: string[]): boolean => {
        return permissions.every(permission => can(permission));
    };

    /**
     * Get a reactive computed property for a permission check
     * @param permission - Permission key to check
     * @returns Computed boolean
     */
    const hasPermission = (permission: string): ComputedRef<boolean> => {
        return computed(() => can(permission));
    };

    return {
        can,
        canAny,
        canAll,
        hasPermission,
        auth,
    };
}

/**
 * Filter navigation items based on user permissions
 * @param items - Array of navigation items
 * @param auth - Auth object from page props
 * @returns Filtered navigation items the user can access
 */
export function filterNavItemsByPermission(items: NavItem[], auth: Auth): NavItem[] {
    const filtered = items
        .map((item) => {
            const filteredChildren = item.children
                ? filterNavItemsByPermission(item.children, auth)
                : [];

            const canViewItem = !item.permission || (auth?.can?.[item.permission] ?? false);
            const canViewChildren = filteredChildren.length > 0;
            const isSectionOnlyParent = Boolean(item.children?.length) && !item.href;

            if (isSectionOnlyParent && !canViewChildren) {
                return null;
            }

            if (!canViewItem && !canViewChildren) {
                return null;
            }

            return {
                ...item,
                ...(item.children ? { children: filteredChildren } : {}),
            } as NavItem;
        })
        .filter((item): item is NavItem => Boolean(item));

    // Remove orphaned delimiters — delimiters with no menuitem following them before the next delimiter
    return filtered.filter((item, index) => {
        if (item.kind !== 'delimiter') return true;
        const rest = filtered.slice(index + 1);
        const nextDelimiterIdx = rest.findIndex((i) => i.kind === 'delimiter');
        const itemsUntilNext = nextDelimiterIdx === -1 ? rest : rest.slice(0, nextDelimiterIdx);
        return itemsUntilNext.some((i) => i.kind === 'menuitem');
    });
}

/**
 * Check if a navigation item should be visible based on permissions
 * @param item - Navigation item
 * @param auth - Auth object from page props
 * @returns true if item should be visible
 */
export function canAccessNavItem(item: NavItem, auth: Auth): boolean {
    const canViewItem = !item.permission || (auth?.can?.[item.permission] ?? false);
    const canViewChildren = item.children?.some((child) => canAccessNavItem(child, auth)) ?? false;

    return canViewItem || canViewChildren;
}
