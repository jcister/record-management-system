<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ChevronLeft, ChevronRight, X } from 'lucide-vue-next';
import { useActiveUrl } from '@/composables/useActiveUrl';
import { type NavItem } from '@/types';
import { toUrl } from '@/lib/utils';
import { cn } from '@/lib/utils';
import AppLogo from './AppLogo.vue';
import NavUser from './NavUser.vue';

const props = defineProps<{
    items: NavItem[];
}>();

const sidebarNavHeaders = { 'X-App-Nav': 'sidebar' } as const;

const { urlIsActive, currentUrl } = useActiveUrl();
const selectedItem = ref<NavItem | null>(null);
const isSecondaryCollapsed = ref(false);
const isMobileOpen = ref(false);

const urlMatchesPrefix = (href: NonNullable<NavItem['href']>) => {
    const base = toUrl(href);
    if (!base) {
        return false;
    }

    const normalizedBase = base.endsWith('/') ? base : `${base}/`;
    const normalizedCurrent = currentUrl.value.endsWith('/') ? currentUrl.value : `${currentUrl.value}/`;

    if (!normalizedCurrent.startsWith(normalizedBase)) return false;

    const remainder = normalizedCurrent.slice(normalizedBase.length);
    if (remainder === '' || remainder === '/') return true;

    const segments = remainder.split('/').filter(Boolean);

    // If the first segment after the base looks like a numeric id or UUID,
    // consider this a match even if there are additional nested segments
    // (e.g. /disposal-batches/{id}/nap-form-3/preview).
    const first = segments[0];
    const isNumeric = /^[0-9]+$/.test(first);
    const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(first);

    return isNumeric || isUuid;
};

const isNavItemActive = (item: NavItem): boolean => {
    const selfActive = item.href
        ? urlIsActive(item.href) || urlMatchesPrefix(item.href)
        : false;

    if (selfActive) {
        return true;
    }

    return item.children?.some((child) => isNavItemActive(child)) ?? false;
};

const activeParentItem = computed(() => {
    if (selectedItem.value?.children?.length) {
        return selectedItem.value;
    }

    const menuItems = props.items.filter((item) => item.kind === 'menuitem');
    return menuItems.find((item) => item.children?.length && isNavItemActive(item)) || null;
});

const handleItemClick = (item: NavItem) => {
    if (item.children?.length) {
        selectedItem.value = item;
        const target = item.href ?? item.children[0]?.href;
        if (target) {
            router.visit(toUrl(target) || '#', { headers: sidebarNavHeaders });
        }
    }
};

const toggleSecondaryColumn = () => {
    isSecondaryCollapsed.value = !isSecondaryCollapsed.value;
};

const toggleMobile = () => {
    isMobileOpen.value = !isMobileOpen.value;
};

defineExpose({ toggleMobile });
</script>

<template>
    <div class="flex flex-1 overflow-hidden">
        <!-- Primary Column -->
        <div class="hidden sm:flex flex-col w-24 border-r border-border/50">
            <div class="flex-1 overflow-y-auto py-2">
                <div class="flex items-center flex-col gap-1 pt-2">
                    <AppLogo />

                    <template v-for="(item, index) in items" :key="item.title">
                        <!-- Delimiter -->
                        <div
                            v-if="item.kind === 'delimiter'"
                            :class="cn('px-2 pt-4', index > 0 && 'pt-5 mt-4 border-t border-border/50')"
                        >
                            <div
                                class="text-[13px] text-center font-bold text- uppercase tracking-wider"
                            >
                                {{ item.title }}
                            </div>
                        </div>

                        <!-- Menu Item -->
                        <button
                            v-else-if="item.kind === 'menuitem' && !item.hidePrimary"
                            @click="handleItemClick(item)"
                            :class="cn('flex flex-col items-center justify-center gap-1.5 p-2 rounded-lg transition-colors relative')"
                        >
                            <Link
                                v-if="item.href && !item.children?.length"
                                :href="item.href"
                                :headers="sidebarNavHeaders"
                                class="flex flex-col items-center justify-center gap-1.5 w-full"
                            >
                                <div
                                    :class="cn(
                                        'p-2 rounded-md transition-colors hover:bg-accent/50',
                                        (isNavItemActive(item) || selectedItem === item) && 'bg-accent text-accent-foreground'
                                    )"
                                >
                                    <component :is="item.icon" class="size-6" style="color: var(--sidebar-icon-color, currentColor)" />
                                </div>
                                <span class="text-[12px] font-medium leading-tight text-center">
                                    {{ item.title }}
                                </span>
                            </Link>
                            <div
                                v-else
                                class="flex flex-col items-center justify-center gap-1.5 w-full"
                            >
                                <div
                                    :class="cn(
                                        'p-2 rounded-md transition-colors hover:bg-accent/50',
                                        (isNavItemActive(item) || selectedItem === item) && 'bg-accent text-accent-foreground'
                                    )"
                                >
                                    <component :is="item.icon" class="size-6" style="color: var(--sidebar-icon-color, currentColor)" />
                                </div>
                                <span class="text-[12px] font-medium leading-tight text-center">
                                    {{ item.title }}
                                </span>
                            </div>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Collapse Toggle -->
            <div class="border-t border-border/50 p-2">
                <button
                    @click="toggleSecondaryColumn"
                    :class="cn(
                        'flex w-full items-center justify-center py-2 rounded-lg transition-colors',
                        'hover:bg-accent/50 text-muted-foreground hover:text-foreground'
                    )"
                    :title="isSecondaryCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                >
                    <ChevronLeft v-if="!isSecondaryCollapsed" class="size-5" />
                    <ChevronRight v-else class="size-5" />
                </button>
            </div>
        </div>

        <!-- Secondary Column -->
        <div
            v-if="!isSecondaryCollapsed"
            class="hidden sm:flex flex-col w-56 border-r border-border/50 bg-muted/30 pt-5"
        >
            <div v-if="activeParentItem?.children?.length" class="flex-1 overflow-y-auto">
                <div class="p-3 flex items-center pb-7">
                    <h3 class="font-semibold text-xl">{{ activeParentItem.title }}</h3>
                </div>
                <div class="py-2">
                    <template v-for="child in activeParentItem.children" :key="child.title">
                        <div v-if="child.kind === 'delimiter'" class="px-4 pt-3 pb-1">
                            <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                {{ child.title }}
                            </div>
                        </div>
                        <Link
                            v-else
                            :href="child.href!"
                            :headers="sidebarNavHeaders"
                            :class="cn(
                                'flex items-center gap-3 px-4 py-2.5 text-sm transition-colors',
                                'hover:bg-accent/70',
                                (urlIsActive(child.href!) || urlMatchesPrefix(child.href!)) && 'bg-accent text-accent-foreground font-medium'
                            )"
                        >
                            <component v-if="child.icon" :is="child.icon" class="size-4 shrink-0" style="color: var(--sidebar-icon-color, currentColor)" />
                            <span>{{ child.title }}</span>
                        </Link>
                    </template>
                </div>
            </div>

            <!-- User footer -->
            <div class="mt-auto border-t border-border/50 p-4">
                <NavUser class="p-0" />
            </div>
        </div>

        <!-- Mobile Overlay Drawer -->
        <div v-if="isMobileOpen" class="fixed inset-0 z-50 sm:hidden">
            <div class="absolute inset-0 bg-black/40" @click="toggleMobile"></div>
            <div class="relative z-10 w-full max-w-xs h-full bg-background shadow-xl overflow-y-auto">
                <div class="flex items-center justify-between p-3 border-b border-border/50">
                    <h3 class="font-semibold text-sm">Navigation</h3>
                    <button @click="toggleMobile" class="p-1 rounded hover:bg-accent/50">
                        <X class="size-5" />
                    </button>
                </div>

                <div class="p-3">
                    <div class="flex flex-col gap-1">
                        <template v-for="(item, index) in items" :key="item.title">
                            <div
                                v-if="item.kind === 'delimiter'"
                                :class="cn('py-2', index > 0 && 'pt-4')"
                            >
                                <div
                                    class="text-xs font-semibold text-muted-foreground uppercase tracking-wider"
                                >
                                    {{ item.title }}
                                </div>
                            </div>

                            <button
                                v-else-if="item.kind === 'menuitem'"
                                @click="() => { handleItemClick(item); toggleMobile(); }"
                                :class="cn('flex items-center gap-3 w-full px-3 py-2 rounded transition-colors', (isNavItemActive(item) || selectedItem === item) && 'bg-accent text-accent-foreground')"
                            >
                                <component :is="item.icon" class="size-5 shrink-0" style="color: var(--sidebar-icon-color, currentColor)" />
                                <span class="text-sm">{{ item.title }}</span>
                            </button>
                        </template>
                    </div>

                    <div
                        v-if="activeParentItem?.children?.length"
                        class="mt-4 border-t border-border/50 pt-3"
                    >
                        <h4 class="text-xs font-medium mb-2">{{ activeParentItem.title }}</h4>
                        <div class="flex flex-col">
                            <template v-for="child in activeParentItem.children" :key="child.title">
                                <div v-if="child.kind === 'delimiter'" class="pt-3 pb-1">
                                    <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                        {{ child.title }}
                                    </div>
                                </div>
                                <Link
                                    v-else
                                    :href="child.href!"
                                    @click="toggleMobile"
                                    :class="cn(
                                        'flex items-center gap-3 px-3 py-2 rounded hover:bg-accent/70',
                                        (urlIsActive(child.href!) || urlMatchesPrefix(child.href!)) && 'bg-accent text-accent-foreground font-medium'
                                    )"
                                >
                                    <component v-if="child.icon" :is="child.icon" class="size-4 shrink-0" style="color: var(--sidebar-icon-color, currentColor)" />
                                    <span>{{ child.title }}</span>
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
