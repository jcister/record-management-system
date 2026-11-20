<script setup lang="ts">
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';

interface Column {
    key: string;
    label: string;
    class?: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    columns: Column[];
    rows: Record<string, unknown>[];
    links?: PaginationLink[];
    emptyMessage?: string;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    links: () => [],
    emptyMessage: 'No data found.',
    loading: false,
});

const getCellValue = (row: Record<string, unknown>, key: string): unknown => row[key] ?? '—';
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="overflow-x-auto rounded-xl border border-border/60">
            <Table class="w-full">
                <TableHeader>
                    <TableRow>
                        <TableHead
                            v-for="col in props.columns"
                            :key="col.key"
                            :class="['py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap', col.class]"
                        >
                            {{ col.label }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="props.loading">
                        <TableRow v-for="i in 5" :key="i">
                            <TableCell v-for="col in props.columns" :key="col.key" :class="col.class">
                                <div class="h-4 animate-pulse rounded bg-muted" />
                            </TableCell>
                        </TableRow>
                    </template>
                    <template v-else-if="props.rows.length === 0">
                        <TableEmpty :colspan="props.columns.length">{{ props.emptyMessage }}</TableEmpty>
                    </template>
                    <template v-else>
                        <TableRow v-for="(row, idx) in props.rows" :key="idx" class="hover:bg-muted/40 transition-colors">
                            <TableCell v-for="col in props.columns" :key="col.key" :class="['align-top py-3 text-sm', col.class]">
                                <slot :name="`cell-${col.key}`" :value="getCellValue(row, col.key)" :row="row">
                                    {{ getCellValue(row, col.key) }}
                                </slot>
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div v-if="props.links && props.links.length > 3" class="flex items-center justify-center gap-1">
            <template v-for="link in props.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    :class="[
                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-xs font-medium transition-colors',
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'border border-border bg-background text-foreground hover:bg-muted',
                    ]"
                    v-html="link.label"
                />
                <span
                    v-else
                    :class="[
                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-xs font-medium text-muted-foreground',
                        link.active ? 'bg-primary text-primary-foreground' : '',
                    ]"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
