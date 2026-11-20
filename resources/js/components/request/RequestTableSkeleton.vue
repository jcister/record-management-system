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

type SkeletonVariant = 'allRequests' | 'myRequests' | 'requestHistory';

const variantColumns: Record<SkeletonVariant, ColumnConfig[]> = {
    allRequests: [
        {
            label: 'Request ID',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm max-w-xs md:max-w-sm',
            cellClass: 'text-left py-2 md:py-3 max-w-xs md:max-w-sm',
            skeletonClass: 'h-4 w-24',
        },
        {
            label: 'Request Title',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm max-w-sm md:max-w-2xl truncate',
            cellClass: 'text-left py-2 md:py-3 max-w-sm md:max-w-2xl',
            skeletonClass: 'h-4 w-40',
        },
        {
            label: 'Requester',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm hidden md:table-cell',
            cellClass: 'text-left py-2 md:py-3 hidden md:table-cell',
            skeletonClass: 'h-4 w-28',
        },
        {
            label: 'Attachments',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm',
            cellClass: 'text-center py-2 md:py-3',
            skeletonClass: 'h-4 w-16',
        },
        {
            label: 'Priority',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm hidden xl:table-cell',
            cellClass: 'text-left py-2 md:py-3 hidden xl:table-cell',
            skeletonClass: 'h-5 w-16 rounded-full',
        },
        {
            label: 'Status',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm',
            cellClass: 'text-left py-2 md:py-3',
            skeletonClass: 'h-5 w-16 rounded-full',
        },
        {
            label: 'Created',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm hidden lg:table-cell',
            cellClass: 'text-left py-2 md:py-3 hidden lg:table-cell',
            skeletonClass: 'h-4 w-24',
        },
        {
            label: '',
            headerClass: 'py-2 md:py-3 text-xs md:text-sm text-right w-10',
            cellClass: 'text-center py-2 md:py-3 w-10',
            skeletonClass: 'h-6 w-6 rounded-full',
        },
    ],
    myRequests: [
        {
            label: 'Request ID',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm w-[12%] truncate',
            cellClass: 'text-left py-2 md:py-3 w-[12%]',
            skeletonClass: 'h-4 w-24',
        },
        {
            label: 'Request Title',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm w-[20%] truncate',
            cellClass: 'text-left py-2 md:py-3 w-[20%]',
            skeletonClass: 'h-4 w-40',
        },
        {
            label: 'Priority',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm w-[10%] hidden lg:table-cell truncate',
            cellClass: 'text-left py-2 md:py-3 w-[10%] hidden lg:table-cell',
            skeletonClass: 'h-5 w-16 rounded-full',
        },
        {
            label: 'Status',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm w-[12%] truncate',
            cellClass: 'text-left py-2 md:py-3 w-[12%]',
            skeletonClass: 'h-5 w-16 rounded-full',
        },
        {
            label: 'Date Range',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm w-[18%] hidden md:table-cell truncate',
            cellClass: 'text-left py-2 md:py-3 w-[18%] hidden md:table-cell',
            skeletonClass: 'h-4 w-32',
        },
        {
            label: 'Created',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm w-[12%] hidden lg:table-cell truncate',
            cellClass: 'text-left py-2 md:py-3 w-[12%] hidden lg:table-cell',
            skeletonClass: 'h-4 w-24',
        },
        {
            label: '',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm w-[3%]',
            cellClass: 'text-end py-2 md:py-3 w-[3%]',
            skeletonClass: 'h-6 w-6 rounded-full',
        },
    ],
    requestHistory: [
        {
            label: 'Date',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm max-w-xs md:max-w-sm',
            cellClass: 'text-left py-2 md:py-3 max-w-xs md:max-w-sm',
            skeletonClass: 'h-4 w-28',
        },
        {
            label: 'User',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm',
            cellClass: 'text-left py-2 md:py-3',
            skeletonClass: 'h-4 w-24',
        },
        {
            label: 'Action',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm hidden md:table-cell',
            cellClass: 'text-left py-2 md:py-3 hidden md:table-cell',
            skeletonClass: 'h-5 w-24 rounded-full',
        },
        {
            label: 'Request',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm max-w-sm md:max-w-xl truncate',
            cellClass: 'text-left py-2 md:py-3 max-w-sm md:max-w-xl',
            skeletonClass: 'h-4 w-40',
        },
        {
            label: 'Details',
            headerClass: 'text-left py-2 md:py-3 text-xs md:text-sm hidden lg:table-cell',
            cellClass: 'text-left py-2 md:py-3 hidden lg:table-cell',
            skeletonClass: 'h-4 w-32',
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

    return variantColumns.allRequests;
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
