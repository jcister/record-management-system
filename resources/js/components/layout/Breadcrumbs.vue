<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';

interface BreadcrumbItemType {
    title: string;
    href?: string;
    description?: string;
}

const props = defineProps<{
    breadcrumbs: BreadcrumbItemType[];
    openFile?: (fileId: string) => void;
}>();

const breadcrumbs = computed(() => props.breadcrumbs ?? []);

const expanded = ref(false);

const displayedBreadcrumbs = computed<(BreadcrumbItemType | { ellipsis: true } | { collapse: boolean })[]>(() => {
    const items = breadcrumbs.value;
    const n = items.length;

    // If there are few items, show them all
    if (n <= 5) {
        return items.slice();
    }

    // When expanded, show everything but include a collapse marker after the first item
    if (expanded.value) {
        return [items[0], { collapse: true }, ...items.slice(1)];
    }

    const parentsToKeep = 1; // keep 1-2 parents before current
    const parentStart = Math.max(1, n - parentsToKeep - 1);

    const showIndices = new Set<number>();
    showIndices.add(0); // always keep first
    for (let i = parentStart; i < n; i++) showIndices.add(i); // keep last and its parents

    // Keep the second item when there is a middle region to collapse
    if (parentStart > 1) {
        showIndices.add(1);
    }

    const result: (BreadcrumbItemType | { ellipsis: true })[] = [];
    let collapsedInserted = false;

    for (let i = 0; i < n; i++) {
        if (showIndices.has(i)) {
            result.push(items[i]);
        } else if (!collapsedInserted) {
            result.push({ ellipsis: true });
            collapsedInserted = true;
        }
    }

    return result;
});

function toggleExpand(): void {
    expanded.value = !expanded.value;
}
</script>

<template>
    <Breadcrumb class="min-w-0 max-w-full overflow-hidden">
        <BreadcrumbList class="sm:hidden flex-nowrap overflow-hidden whitespace-nowrap text-ellipsis">
            <BreadcrumbItem v-if="breadcrumbs.length > 2">
                <span class="select-none text-muted-foreground">…</span>
            </BreadcrumbItem>

            <template v-for="(item, index) in breadcrumbs.slice(-2)" :key="`mobile-${index}-${item.title}`">
                <BreadcrumbItem>
                    <template v-if="item === breadcrumbs[breadcrumbs.length - 1]">
                        <BreadcrumbPage>
                            <span>{{ item.title }}</span>
                            <span v-if="item.description" class="block text-xs text-muted-foreground font-normal">{{ item.description }}</span>
                        </BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link :href="item.href ?? '#'">{{ item.title }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== breadcrumbs.slice(-2).length - 1" />

            </template>

        </BreadcrumbList>

        <BreadcrumbList class="hidden sm:flex flex-nowrap overflow-hidden whitespace-nowrap text-ellipsis">
            <template v-for="(item, index) in displayedBreadcrumbs" :key="index">
                <BreadcrumbItem>
                    <template v-if="'ellipsis' in item && item.ellipsis">
                        <button type="button" class="select-none cursor-pointer hover:underline focus:outline-none"
                            @click="toggleExpand" :aria-expanded="expanded" aria-label="Show hidden breadcrumbs">
                            …
                        </button>
                    </template>
                    <template v-else-if="'collapse' in item && item.collapse">
                        <button type="button" class="select-none cursor-pointer hover:underline focus:outline-none"
                            @click="toggleExpand" :aria-expanded="expanded" aria-label="Collapse breadcrumbs">
                            …
                        </button>
                    </template>
                    <template v-else-if="!('ellipsis' in item) && item === breadcrumbs[breadcrumbs.length - 1]">
                        <BreadcrumbPage>
                            <span>{{ item.title }}</span>
                            <span v-if="item.description" class="block text-xs text-muted-foreground font-normal">{{ item.description }}</span>
                        </BreadcrumbPage>
                    </template>
                    <template v-else-if="!('ellipsis' in item) && !('collapse' in item)">
                        <BreadcrumbLink as-child>
                            <Link :href="item.href ?? '#'">{{ item.title }}</Link>
                        </BreadcrumbLink>
                    </template>
                    
                </BreadcrumbItem>
                
                <BreadcrumbSeparator v-if="index !== displayedBreadcrumbs.length - 1" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
