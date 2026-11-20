<script setup lang="ts">
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';

const columns = [
    {
        label: 'Timestamp',
        headerClass:
            'max-w-xs overflow-hidden py-2 text-left text-xs md:max-w-sm md:py-3 md:text-sm',
        cellClass:
            'max-w-xs truncate overflow-hidden py-2 text-left text-xs whitespace-nowrap md:max-w-sm md:py-3 md:text-sm',
        skeletonClass: 'h-4 w-32',
    },
    {
        label: 'User',
        headerClass:
            'max-w-xs overflow-hidden py-2 text-left text-xs md:max-w-sm md:py-3 md:text-sm',
        cellClass:
            'max-w-xs truncate overflow-hidden py-2 text-left text-xs md:max-w-sm md:py-3 md:text-sm',
        skeletonClass: 'h-4 w-28',
    },
    {
        label: 'Action',
        headerClass:
            'max-w-sm overflow-hidden py-2 text-left text-xs md:max-w-2xl md:py-3 md:text-sm',
        cellClass:
            'max-w-sm overflow-hidden py-2 text-left text-xs md:max-w-2xl md:py-3 md:text-sm',
        skeletonClass: 'h-4 w-40',
    },
    {
        label: 'Subject',
        headerClass:
            'hidden overflow-hidden py-2 text-left text-xs md:py-3 md:text-sm lg:table-cell',
        cellClass:
            'hidden overflow-hidden py-2 text-left text-xs md:py-3 md:text-sm lg:table-cell',
        skeletonClass: 'h-4 w-32',
    },
    {
        label: 'Details',
        headerClass:
            'max-w-sm overflow-hidden py-2 text-left text-xs md:max-w-md md:py-3 md:text-sm',
        cellClass:
            'max-w-sm overflow-hidden py-2 text-left text-xs md:max-w-md md:py-3 md:text-sm',
        skeletonClass: 'h-4 w-48',
    },
    {
        label: 'IP Address',
        headerClass:
            'hidden overflow-hidden py-2 text-left text-xs md:py-3 md:text-sm lg:table-cell',
        cellClass:
            'hidden truncate overflow-hidden py-2 text-left text-xs md:py-3 md:text-sm lg:table-cell',
        skeletonClass: 'h-4 w-28',
    },
];

const props = withDefaults(
    defineProps<{
        rows?: number;
    }>(),
    {
        rows: 8,
    },
);
</script>

<template>
    <div class="flex h-full flex-col">
        <div class="w-full">
            <Table class="w-full table-fixed">
                <TableHeader class="sticky top-0 z-10 bg-background">
                    <TableRow class="border-b">
                        <TableHead
                            v-for="(col, index) in columns"
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
                        :key="`audit-skeleton-row-${rowIndex}`"
                        class="border-b"
                    >
                        <TableCell
                            v-for="(col, colIndex) in columns"
                            :key="`audit-skeleton-cell-${rowIndex}-${colIndex}`"
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
