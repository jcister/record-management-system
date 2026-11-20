<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue';
import { ArrowDown, ArrowUp, ArrowUpDown, Check, LayoutGrid, List, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import SelectedActionsBar from './SelectedActionsBar.vue';
import type { SelectedItemType } from '@/types';
import type { GroupBy, RecordsPlacement, SortDirection, SortField } from '@/types/sorting';

type SelectState = 'checked' | 'indeterminate' | 'unchecked';

const props = withDefaults(defineProps<{
    viewMode: 'tiles' | 'list';
    sortField: SortField;
    sortDirection: SortDirection;
    groupBy?: GroupBy;
    showGrouping?: boolean;
    showActionsColumn?: boolean;
    showTypeOption?: boolean;
    showDateColumn?: boolean;
    showSizeColumn?: boolean;
    showVersionColumn?: boolean;
    showVersionLabel?: boolean;
    showStatusColumn?: boolean;
    showStatusLabel?: boolean;
    statusLabel?: string;
    showSelectControl?: boolean;
    selectState?: SelectState;
    forceSelectVisible?: boolean;
    selections?: SelectedItemType[];
    selectionCount?: number;
    nameLabel?: string;
    dateLabel?: string;
    sizeLabel?: string;
    showSizeOption?: boolean;
    showRecordsPlacement?: boolean;
    recordsPlacement?: RecordsPlacement;
}>(), {
    groupBy: null,
    showGrouping: true,
    showActionsColumn: true,
    showTypeOption: true,
    showDateColumn: true,
    showSizeColumn: true,
    showVersionColumn: true,
    showVersionLabel: true,
    showStatusColumn: false,
    showStatusLabel: true,
    statusLabel: 'Status',
    showSelectControl: false,
    selectState: 'unchecked',
    forceSelectVisible: false,
    selections: () => [],
    selectionCount: 0,
    nameLabel: 'File Name',
    dateLabel: 'Last Updated',
    sizeLabel: 'Size',
    showSizeOption: true,
    showRecordsPlacement: false,
    recordsPlacement: 'top',
});

const emit = defineEmits<{
    'update:sort-field': [SortField];
    'update:sort-direction': [SortDirection];
    'update:group-by': [GroupBy];
    'update:records-placement': [RecordsPlacement];
    'toggle-select-all': [];
    'toggle-select': [];
    'update:view-mode': [value: 'tiles' | 'list'];
}>();

const groupingEnabled = computed(() => props.viewMode === 'tiles' && props.showGrouping);

const nameColClass = computed(() => {
    if (props.showStatusColumn) {
        return 'flex w-full sm:w-4/12 items-center gap-2 font-semibold text-foreground';
    }

    // When the Version column is hidden (used for folder lists) the name column
    // should take 1/2 width so other columns align with folder rows.
    return props.showVersionColumn
        ? 'flex w-full sm:w-5/12 items-center gap-2 font-semibold text-foreground'
        : 'flex w-full sm:w-6/12 items-center gap-2 font-semibold text-foreground';
});

const groupOptions = computed<{ value: GroupBy; label: string }[]>(() => {
    const base: { value: GroupBy; label: string }[] = [
        { value: 'date', label: props.dateLabel },
        { value: 'size', label: props.sizeLabel },
    ];

    if (props.showTypeOption) {
        base.push({ value: 'type', label: 'Type' });
    }

    return base;
});

const sortFieldOptions = computed(() => {
    const options: { value: SortField; label: string }[] = [
        { value: 'name', label: 'Name' },
        { value: 'date', label: props.dateLabel },
    ];

    if (props.showSizeOption) {
        options.push({ value: 'size', label: props.sizeLabel });
    }

    return options;
});

watchEffect(() => {
    if (!props.showSizeOption && props.sortField === 'size') {
        emit('update:sort-field', 'name');
        emit('update:sort-direction', 'asc');
    }
});

watchEffect(() => {
    if (!props.showTypeOption && props.groupBy === 'type') {
        emit('update:group-by', null);
    }
});

const iconFor = (field: SortField) => {
    if (props.sortField === field) {
        return props.sortDirection === 'asc' ? ArrowUp : ArrowDown;
    }

    return ArrowDown;
};

const handleSortClick = (field: SortField): void => {
    if (field === 'size' && !props.showSizeOption) {
        return;
    }
    if (props.sortField === field) {
        const next = props.sortDirection === 'asc' ? 'desc' : 'asc';
        emit('update:sort-direction', next);
        return;
    }

    const defaultDirection: SortDirection = field === 'date' ? 'desc' : 'asc';
    emit('update:sort-field', field);
    emit('update:sort-direction', defaultDirection);
};

const handleGroupChange = (value: string): void => {
    emit('update:group-by', (value || null) as GroupBy);
};

const sortMenuOpen = ref(false);

const handleSortFieldSelect = (field: SortField, event?: Event): void => {
    event?.preventDefault();
    if (field === 'size' && !props.showSizeOption) {
        return;
    }
    if (props.sortField === field) {
        return;
    }

    const defaultDirection: SortDirection = field === 'date' ? 'desc' : 'asc';
    emit('update:sort-field', field);
    emit('update:sort-direction', defaultDirection);
};

const handleSortDirectionSelect = (direction: SortDirection): void => {
    emit('update:sort-direction', direction);
    sortMenuOpen.value = false;
};

const handleRecordsPlacementSelect = (placement: RecordsPlacement): void => {
    emit('update:records-placement', placement);
    sortMenuOpen.value = false;
};

const checkboxState = computed(() => {
    if (props.selectState === 'indeterminate') return 'indeterminate';
    return props.selectState === 'checked';
});

const checkboxVisibilityClasses = computed(() => {
    const visible = props.forceSelectVisible || props.selectState !== 'unchecked';
    return [
        'transition-opacity',
        'duration-150',
        'cursor-pointer',
        visible ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto',
    ];
});

const emitToggleSelectAll = (event: MouseEvent): void => {
    event.stopPropagation();
    emit('toggle-select-all');
};

const hasSelection = computed(() => (props.selections?.length ?? 0) > 0);

const sizeColumnVisible = computed(() => props.showSizeColumn || props.sizeLabel === 'Items');

watchEffect(() => {
    if (hasSelection.value) {
        sortMenuOpen.value = false;
    }
});
</script>

<template>
    <!-- TILES MODE -->
    <div v-if="viewMode === 'tiles'" class="relative flex h-12 items-center border-b border-sidebar-border/60 mb-3 text-sm text-muted-foreground group">
        <Transition name="sort-swap" mode="out-in">
            <div v-if="hasSelection" key="selection" class="flex flex-1 items-center justify-between py-2 pr-9">
                <div class="flex items-center gap-2">
                    <Button variant="ghost" size="icon" class="h-8 w-8" @click.stop="emit('toggle-select')">
                        <X class="h-4 w-4" />
                    </Button>
                    <span class="text-sm font-semibold text-primary">{{ selectionCount }} selected</span>
                </div>
                <SelectedActionsBar :items="selections ?? []" :selection-count="selectionCount" />
            </div>
            <div v-else key="sort" class="flex flex-1 items-center gap-2 py-2 font-semibold text-foreground">
                <span>{{ nameLabel }}</span>
            </div>
        </Transition>
        <Transition name="sort-swap" mode="out-in">
            <div v-if="!hasSelection" key="sort-controls" class="absolute right-0 top-1/2 -translate-y-1/2 flex items-center gap-2">
                <DropdownMenu v-model:open="sortMenuOpen">
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm" class="h-7 px-3 gap-1 text-xs font-semibold">
                            <span>Sort</span>
                            <ArrowUpDown class="h-3.5 w-3.5" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-64 p-1">
                        <DropdownMenuLabel class="px-2 pb-1 pt-2 text-[12px] font-semibold text-muted-foreground">Sort by</DropdownMenuLabel>
                        <DropdownMenuItem
                            v-for="option in sortFieldOptions"
                            :key="option.value"
                            class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                            :class="sortField === option.value ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                            @select="handleSortFieldSelect(option.value, $event)"
                        >
                            <Check class="h-4 w-4" :class="sortField === option.value ? 'opacity-100 text-primary' : 'opacity-0'" />
                            <span>{{ option.label }}</span>
                        </DropdownMenuItem>

                        <DropdownMenuSeparator class="my-1" />

                        <DropdownMenuLabel class="px-2 pb-1 pt-1 text-[12px] font-semibold text-muted-foreground">Sort direction</DropdownMenuLabel>
                        <template v-if="sortField === 'date'">
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="sortDirection === 'desc' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleSortDirectionSelect('desc')"
                            >
                                <Check class="h-4 w-4" :class="sortDirection === 'desc' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>New to Old</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="sortDirection === 'asc' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleSortDirectionSelect('asc')"
                            >
                                <Check class="h-4 w-4" :class="sortDirection === 'asc' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>Old to New</span>
                            </DropdownMenuItem>
                        </template>
                        <template v-else-if="sortField === 'size'">
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="sortDirection === 'asc' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleSortDirectionSelect('asc')"
                            >
                                <Check class="h-4 w-4" :class="sortDirection === 'asc' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>Smallest to Biggest</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="sortDirection === 'desc' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleSortDirectionSelect('desc')"
                            >
                                <Check class="h-4 w-4" :class="sortDirection === 'desc' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>Biggest to Smallest</span>
                            </DropdownMenuItem>
                        </template>
                        <template v-else>
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="sortDirection === 'asc' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleSortDirectionSelect('asc')"
                            >
                                <Check class="h-4 w-4" :class="sortDirection === 'asc' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>A to Z</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="sortDirection === 'desc' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleSortDirectionSelect('desc')"
                            >
                                <Check class="h-4 w-4" :class="sortDirection === 'desc' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>Z to A</span>
                            </DropdownMenuItem>
                        </template>

                        <template v-if="showRecordsPlacement">
                            <DropdownMenuSeparator class="my-1" />
                            <DropdownMenuLabel class="px-2 pb-1 pt-1 text-[12px] font-semibold text-muted-foreground">Records</DropdownMenuLabel>
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="recordsPlacement === 'top' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleRecordsPlacementSelect('top')"
                            >
                                <Check class="h-4 w-4" :class="recordsPlacement === 'top' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>On Top</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="recordsPlacement === 'mixed' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleRecordsPlacementSelect('mixed')"
                            >
                                <Check class="h-4 w-4" :class="recordsPlacement === 'mixed' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>Mixed with Stored Files</span>
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>

                <Button variant="ghost" size="icon" class="h-7 w-7" title="Switch to List View" @click="emit('update:view-mode', 'list')">
                    <List class="h-3.5 w-3.5" />
                </Button>
            </div>
            <div v-else key="selection-controls" class="absolute right-0 top-1/2 -translate-y-1/2 flex items-center gap-2">
                <Button variant="ghost" size="icon" class="h-7 w-7" title="Switch to List View" @click="emit('update:view-mode', 'list')">
                    <List class="h-3.5 w-3.5" />
                </Button>
            </div>
        </Transition>
    </div>

    <!-- LIST MODE -->
    <div v-else class="relative flex h-12 items-center border-b border-sidebar-border/60 mb-2 text-sm text-muted-foreground group">
        <Transition name="sort-swap" mode="out-in">
            <div v-if="hasSelection" key="selection" class="flex flex-1 items-center justify-between py-2 pr-9">
                <div class="flex items-center gap-2">
                    <Button variant="ghost" size="icon" class="h-8 w-8" @click.stop="emit('toggle-select')">
                        <X class="h-4 w-4" />
                    </Button>
                    <span class="text-sm font-semibold text-primary">{{ selectionCount }} selected</span>
                </div>
                <SelectedActionsBar :items="selections ?? []" :selection-count="selectionCount" />
            </div>
            <div v-else key="sort" class="flex flex-1 items-center py-2">
                <div :class="nameColClass">
                    <button
                        type="button"
                        class="flex flex-1 items-center gap-2 text-left cursor-pointer"
                        @click="handleSortClick('name')"
                    >
                        <span class="truncate">{{ nameLabel }}</span>
                    </button>
                </div>

                <button
                    v-if="showDateColumn"
                    type="button"
                    class="hidden sm:flex sm:w-1/6 items-center gap-2 font-semibold text-foreground"
                    @click="handleSortClick('date')"
                >
                    <span class="truncate text-left">{{ dateLabel }}</span>
                </button>

                <button
                    v-if="sizeColumnVisible"
                    type="button"
                    class="hidden sm:flex sm:w-1/6 items-center gap-2 font-semibold text-foreground"
                    @click="handleSortClick('size')"
                >
                    <span class="truncate text-left">{{ sizeLabel }}</span>
                </button>

                <div v-if="showStatusColumn" class="hidden sm:flex sm:w-1/6 items-center gap-2 font-semibold text-foreground">
                    <span v-if="showStatusLabel" class="truncate text-left">{{ statusLabel }}</span>
                    <span v-else>&nbsp;</span>
                </div>

                <div v-if="showVersionColumn" class="hidden lg:flex lg:w-1/12 font-semibold text-foreground">
                    <span v-if="showVersionLabel">Version</span>
                    <span v-else>&nbsp;</span>
                </div>

                <div
                    v-if="showActionsColumn"
                    class="w-full sm:flex-1 lg:w-1/12 font-semibold text-right text-foreground ml-auto"
                >
                    &nbsp;
                </div>
            </div>
        </Transition>
        <Transition name="sort-swap" mode="out-in">
            <div v-if="!hasSelection" key="sort-controls" class="absolute right-0 top-1/2 -translate-y-1/2 flex items-center gap-2">
                <DropdownMenu v-model:open="sortMenuOpen">
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm" class="h-7 px-3 gap-1 text-xs font-semibold">
                            <span>Sort</span>
                            <ArrowUpDown class="h-3.5 w-3.5" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-64 p-1">
                        <DropdownMenuLabel class="px-2 pb-1 pt-2 text-[12px] font-semibold text-muted-foreground">Sort by</DropdownMenuLabel>
                        <DropdownMenuItem
                            v-for="option in sortFieldOptions"
                            :key="option.value"
                            class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                            :class="sortField === option.value ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                            @select="handleSortFieldSelect(option.value, $event)"
                        >
                            <Check class="h-4 w-4" :class="sortField === option.value ? 'opacity-100 text-primary' : 'opacity-0'" />
                            <span>{{ option.label }}</span>
                        </DropdownMenuItem>

                        <DropdownMenuSeparator class="my-1" />

                        <DropdownMenuLabel class="px-2 pb-1 pt-1 text-[12px] font-semibold text-muted-foreground">Sort direction</DropdownMenuLabel>
                        <template v-if="sortField === 'date'">
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="sortDirection === 'desc' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleSortDirectionSelect('desc')"
                            >
                                <Check class="h-4 w-4" :class="sortDirection === 'desc' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>New to Old</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="sortDirection === 'asc' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleSortDirectionSelect('asc')"
                            >
                                <Check class="h-4 w-4" :class="sortDirection === 'asc' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>Old to New</span>
                            </DropdownMenuItem>
                        </template>
                        <template v-else-if="sortField === 'size'">
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="sortDirection === 'asc' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleSortDirectionSelect('asc')"
                            >
                                <Check class="h-4 w-4" :class="sortDirection === 'asc' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>Smallest to Biggest</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="sortDirection === 'desc' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleSortDirectionSelect('desc')"
                            >
                                <Check class="h-4 w-4" :class="sortDirection === 'desc' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>Biggest to Smallest</span>
                            </DropdownMenuItem>
                        </template>
                        <template v-else>
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="sortDirection === 'asc' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleSortDirectionSelect('asc')"
                            >
                                <Check class="h-4 w-4" :class="sortDirection === 'asc' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>A to Z</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="sortDirection === 'desc' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleSortDirectionSelect('desc')"
                            >
                                <Check class="h-4 w-4" :class="sortDirection === 'desc' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>Z to A</span>
                            </DropdownMenuItem>
                        </template>

                        <template v-if="showRecordsPlacement">
                            <DropdownMenuSeparator class="my-1" />
                            <DropdownMenuLabel class="px-2 pb-1 pt-1 text-[12px] font-semibold text-muted-foreground">Records</DropdownMenuLabel>
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="recordsPlacement === 'top' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleRecordsPlacementSelect('top')"
                            >
                                <Check class="h-4 w-4" :class="recordsPlacement === 'top' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>On Top</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left"
                                :class="recordsPlacement === 'mixed' ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                                @select="handleRecordsPlacementSelect('mixed')"
                            >
                                <Check class="h-4 w-4" :class="recordsPlacement === 'mixed' ? 'opacity-100 text-primary' : 'opacity-0'" />
                                <span>Mixed with Stored Files</span>
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>

                <Button variant="ghost" size="icon" class="h-7 w-7" title="Switch to Tiles View" @click="emit('update:view-mode', 'tiles')">
                    <LayoutGrid class="h-3.5 w-3.5" />
                </Button>
            </div>
            <div v-else key="selection-controls" class="absolute right-0 top-1/2 -translate-y-1/2 flex items-center gap-2">
                <Button variant="ghost" size="icon" class="h-7 w-7" title="Switch to Tiles View" @click="emit('update:view-mode', 'tiles')">
                    <LayoutGrid class="h-3.5 w-3.5" />
                </Button>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.sort-swap-enter-active,
.sort-swap-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.sort-swap-enter-from {
    opacity: 0;
    transform: translateY(-5px);
}

.sort-swap-leave-to {
    opacity: 0;
    transform: translateY(5px);
}
</style>
