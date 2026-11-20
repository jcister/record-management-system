<script setup lang="ts">
import { computed } from 'vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';

export interface ColumnConfig {
    label: string;
    headerClass?: string;
    cellClass?: string;
    skeletonClass?: string;
}

type SkeletonVariant = 'directory' | 'roles';

const variantColumns: Record<SkeletonVariant, ColumnConfig[]> = {
    directory: [
        {
            label: 'Avatar',
            headerClass: 'w-24 px-4 py-3',
            cellClass: 'w-24 px-4 py-3',
            skeletonClass: 'h-10 w-10 rounded-full',
        },
        {
            label: 'Name',
            headerClass: 'text-left px-4 py-3',
            cellClass: 'px-4 py-3',
            skeletonClass: 'h-4 w-32',
        },
        {
            label: 'Email',
            headerClass: 'hidden sm:table-cell w-80 px-4 py-3',
            cellClass: 'hidden sm:table-cell w-80 px-4 py-3',
            skeletonClass: 'h-4 w-48',
        },
        {
            label: 'Status',
            headerClass: 'px-4 py-3',
            cellClass: 'px-4 py-3',
            skeletonClass: 'h-6 w-20 rounded-full',
        },
        {
            label: 'Roles',
            headerClass: 'px-4 py-3',
            cellClass: 'px-4 py-3',
            skeletonClass: 'h-6 w-32 rounded-full',
        },
        {
            label: 'Actions',
            headerClass: 'text-right px-4 py-3',
            cellClass: 'text-right px-4 py-3',
            skeletonClass: 'h-8 w-8 rounded-full ml-auto',
        },
    ],
    roles: [
        {
            label: 'Role',
            headerClass: 'text-left px-4 py-3 w-40',
            cellClass: 'text-left px-4 py-3 w-40',
            skeletonClass: 'h-4 w-28',
        },
        {
            label: 'Users',
            headerClass: 'py-3 text-center w-10',
            cellClass: 'py-3 text-center w-10',
            skeletonClass: 'h-4 w-12 mx-auto',
        },
        {
            label: 'Permissions',
            headerClass: 'px-4 py-3 w-120',
            cellClass: 'px-4 py-3 w-120',
            skeletonClass: 'h-6 w-48 rounded-full',
        },
        {
            label: 'Actions',
            headerClass: 'text-right px-4 py-3 w-15',
            cellClass: 'text-right px-4 py-3 w-15',
            skeletonClass: 'h-8 w-8 rounded-full ml-auto',
        },
    ],
};

const props = withDefaults(defineProps<{
    columns?: ColumnConfig[];
    rows?: number;
    variant?: SkeletonVariant;
}>(), {
    rows: 6,
});

const resolvedColumns = computed(() => {
    if (props.columns?.length) {
        return props.columns;
    }

    if (props.variant) {
        return variantColumns[props.variant];
    }

    return variantColumns.directory;
});
</script>

<template>
    <div class="flex flex-col h-full">
        <div class="w-full">
            <Table class="w-full table-fixed">
                <TableHeader class="bg-background sticky top-0 z-10">
                    <TableRow class="border-b">
                        <TableHead
                            v-for="(col, index) in resolvedColumns"
                            :key="index"
                            :class="col.headerClass"
                        >
                            {{ col.label }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
            </Table>
        </div>
        <div class="flex-1 overflow-y-auto">
            <Table class="w-full table-fixed">
                <TableBody>
                    <TableRow
                        v-for="rowIndex in props.rows"
                        :key="`skeleton-row-${rowIndex}`"
                        class="border-b"
                    >
                        <TableCell
                            v-for="(col, colIndex) in resolvedColumns"
                            :key="`skeleton-cell-${rowIndex}-${colIndex}`"
                            :class="col.cellClass"
                        >
                            <Skeleton :class="col.skeletonClass || 'h-4 w-24'" />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
