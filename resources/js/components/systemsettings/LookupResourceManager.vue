<script setup lang="ts">
import axios from 'axios';
import {
    Archive,
    Loader2,
    MoreHorizontal,
    Pencil,
    Plus,
    RotateCcw,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useToast } from '@/composables/useToast';
import { apiClient } from '@/lib/axios';

interface Item {
    id: number;
    name: string;
    deleted_at?: string | null;
}

interface ApiPayload {
    data?: Item;
}

const props = withDefaults(
    defineProps<{
        title: string;
        description: string;
        resourceUrl: string;
        singularLabel: string;
        initialItems?: Item[];
    }>(),
    {
        singularLabel: 'item',
    },
);

const { success, error } = useToast();

const items = ref<Item[]>([]);
const loading = ref(false);
const searchTerm = ref('');
const showDeleted = ref(false);

const addDialogOpen = ref(false);
const editDialogOpen = ref(false);
const removeConfirmOpen = ref(false);
const editTargetId = ref<number | null>(null);
const removeTargetId = ref<number | null>(null);

const newName = ref('');
const editName = ref('');
const createErrors = ref<Record<string, string[]>>({});
const updateErrors = ref<Record<string, string[]>>({});

const isCreating = ref(false);
const isUpdating = ref(false);
const isDeleting = ref(false);
const isRestoring = ref(false);
const rowsPerPage = ref(10);
const currentPage = ref(1);

const filteredItems = computed(() => {
    const query = searchTerm.value.trim().toLowerCase();

    return items.value
        .filter((item) => (showDeleted.value ? true : !item.deleted_at))
        .filter((item) => {
            if (!query) {
                return true;
            }

            return item.name.toLowerCase().includes(query);
        });
});

const activeCount = computed(
    () => items.value.filter((item) => !item.deleted_at).length,
);
const deletedCount = computed(
    () => items.value.filter((item) => !!item.deleted_at).length,
);

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredItems.value.length / rowsPerPage.value)),
);

const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;
    return filteredItems.value.slice(start, start + rowsPerPage.value);
});

const pageRangeLabel = computed(() => {
    if (filteredItems.value.length === 0) {
        return '0-0 of 0';
    }

    const start = (currentPage.value - 1) * rowsPerPage.value + 1;
    const end = Math.min(
        currentPage.value * rowsPerPage.value,
        filteredItems.value.length,
    );

    return `${start}-${end} of ${filteredItems.value.length}`;
});

const toTitleCase = (value: string): string =>
    value.charAt(0).toUpperCase() + value.slice(1);

watch([searchTerm, showDeleted, rowsPerPage], () => {
    currentPage.value = 1;
});

watch(totalPages, (value) => {
    if (currentPage.value > value) {
        currentPage.value = value;
    }
});

const loadItems = async (): Promise<void> => {
    loading.value = true;

    try {
        const response = await apiClient.get<{ data: Item[] }>(
            props.resourceUrl,
        );
        items.value = [...(response.data.data ?? [])];
    } catch (err) {
        error('Load failed', `Unable to load ${props.title.toLowerCase()}.`);
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const openAddDialog = (): void => {
    newName.value = '';
    createErrors.value = {};
    addDialogOpen.value = true;
};

const openEditDialog = (item: Item): void => {
    editTargetId.value = item.id;
    editName.value = item.name;
    updateErrors.value = {};
    editDialogOpen.value = true;
};

const promptRemove = (id: number): void => {
    removeTargetId.value = id;
    removeConfirmOpen.value = true;
};

const createItem = async (): Promise<void> => {
    const name = newName.value.trim();
    if (!name) {
        return;
    }

    isCreating.value = true;
    createErrors.value = {};

    try {
        const response = await apiClient.post<ApiPayload>(props.resourceUrl, {
            name,
        });
        const created = response.data.data;

        if (!created) {
            throw new Error('Missing created item payload');
        }

        items.value = [...items.value, created];
        addDialogOpen.value = false;
        newName.value = '';
        success(
            `${toTitleCase(props.singularLabel)} created`,
            `${created.name} has been added.`,
        );
    } catch (err) {
        if (axios.isAxiosError(err) && err.response?.status === 422) {
            createErrors.value =
                (err.response.data?.errors as Record<string, string[]>) ?? {};
            return;
        }

        error('Create failed', `Unable to create ${props.singularLabel}.`);
        console.error(err);
    } finally {
        isCreating.value = false;
    }
};

const updateItem = async (): Promise<void> => {
    const id = editTargetId.value;
    const name = editName.value.trim();

    if (id === null || !name) {
        return;
    }

    isUpdating.value = true;
    updateErrors.value = {};

    try {
        const response = await apiClient.patch<ApiPayload>(
            `${props.resourceUrl}/${id}`,
            { name },
        );
        const updated = response.data.data;

        if (!updated) {
            throw new Error('Missing updated item payload');
        }

        items.value = items.value.map((item) =>
            item.id === id ? { ...item, ...updated } : item,
        );
        editDialogOpen.value = false;
        editTargetId.value = null;
        editName.value = '';
        success(
            `${toTitleCase(props.singularLabel)} updated`,
            `${updated.name} has been updated.`,
        );
    } catch (err) {
        if (axios.isAxiosError(err) && err.response?.status === 422) {
            updateErrors.value =
                (err.response.data?.errors as Record<string, string[]>) ?? {};
            return;
        }

        error('Update failed', `Unable to update ${props.singularLabel}.`);
        console.error(err);
    } finally {
        isUpdating.value = false;
    }
};

const confirmRemove = async (): Promise<void> => {
    const id = removeTargetId.value;

    removeConfirmOpen.value = false;
    removeTargetId.value = null;

    if (id === null) {
        return;
    }

    isDeleting.value = true;

    try {
        await apiClient.delete(`${props.resourceUrl}/${id}`);

        items.value = items.value.map((item) =>
            item.id === id
                ? {
                      ...item,
                      deleted_at: new Date().toISOString(),
                  }
                : item,
        );

        success(
            `${toTitleCase(props.singularLabel)} deleted`,
            `${props.singularLabel} has been moved to deleted items.`,
        );
    } catch (err) {
        error('Delete failed', `Unable to delete ${props.singularLabel}.`);
        console.error(err);
    } finally {
        isDeleting.value = false;
    }
};

const restoreItem = async (id: number): Promise<void> => {
    isRestoring.value = true;

    try {
        const response = await apiClient.post<ApiPayload>(
            `${props.resourceUrl}/${id}/restore`,
        );
        const restored = response.data.data;

        items.value = items.value.map((item) =>
            item.id === id
                ? {
                      ...item,
                      ...(restored ?? {}),
                      deleted_at: null,
                  }
                : item,
        );

        success(
            `${toTitleCase(props.singularLabel)} restored`,
            `${props.singularLabel} is active again.`,
        );
    } catch (err) {
        error('Restore failed', `Unable to restore ${props.singularLabel}.`);
        console.error(err);
    } finally {
        isRestoring.value = false;
    }
};

onMounted(() => {
    if (props.initialItems !== undefined) {
        items.value = [...props.initialItems];
    } else {
        loadItems();
    }
});
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-4">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="space-y-1">
                <h3 class="text-base font-semibold">{{ title }}</h3>
                <p class="text-sm text-muted-foreground">{{ description }}</p>
                <div class="flex items-center gap-2 pt-1">
                    <Badge variant="secondary">{{ activeCount }} active</Badge>
                    <Badge variant="outline">{{ deletedCount }} deleted</Badge>
                </div>
            </div>

            <Button
                type="button"
                class="gap-2 self-start"
                @click="openAddDialog"
            >
                <Plus class="h-4 w-4" />
                Add {{ singularLabel }}
            </Button>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">
            <Input
                v-model="searchTerm"
                :placeholder="`Search ${title.toLowerCase()}...`"
                class="sm:flex-1"
            />
            <Button
                type="button"
                size="sm"
                :variant="showDeleted ? 'secondary' : 'outline'"
                class="sm:self-auto"
                @click="showDeleted = !showDeleted"
            >
                {{ showDeleted ? 'Hide deleted' : 'Show deleted' }}
            </Button>
        </div>

        <div
            class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border bg-card shadow-sm"
        >
            <div
                v-if="loading"
                class="flex flex-1 items-center justify-center gap-2 px-4 py-10 text-sm text-muted-foreground"
            >
                <Loader2 class="h-4 w-4 animate-spin" />
                Loading {{ title.toLowerCase() }}...
            </div>

            <div
                v-else-if="filteredItems.length === 0"
                class="flex flex-1 items-center justify-center px-4 py-10 text-center text-sm text-muted-foreground"
            >
                No {{ singularLabel }} found.
            </div>

            <div v-else class="flex-1 overflow-y-auto">
                <div
                    class="sticky top-0 z-10 grid grid-cols-[1fr_auto_auto] gap-3 border-b bg-background px-4 py-2 md:py-3"
                >
                    <div class="text-left text-xs font-medium md:text-sm">
                        {{ toTitleCase(singularLabel) }}
                    </div>
                    <div class="text-left text-xs font-medium md:text-sm">
                        Status
                    </div>
                    <div class="text-right text-xs font-medium md:text-sm">
                        Actions
                    </div>
                </div>

                <div
                    v-for="item in paginatedItems"
                    :key="item.id"
                    class="grid grid-cols-[1fr_auto_auto] items-center gap-3 border-b px-4 py-2 transition-colors even:bg-muted/30 hover:bg-muted/50 md:py-3"
                    :class="item.deleted_at ? 'opacity-70' : ''"
                >
                    <div class="flex min-w-0 items-center gap-2">
                        <Archive
                            v-if="item.deleted_at"
                            class="h-4 w-4 shrink-0 text-muted-foreground"
                        />
                        <p
                            class="truncate text-xs md:text-sm"
                            :class="
                                item.deleted_at
                                    ? 'text-muted-foreground line-through'
                                    : 'text-foreground'
                            "
                        >
                            {{ item.name }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <Badge
                            :variant="item.deleted_at ? 'outline' : 'secondary'"
                            class="text-xs"
                        >
                            {{ item.deleted_at ? 'Deleted' : 'Active' }}
                        </Badge>
                    </div>

                    <div class="flex shrink-0 items-center justify-end gap-2">
                        <template v-if="!item.deleted_at">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :disabled="isUpdating || isDeleting"
                                    >
                                        <MoreHorizontal class="h-4 w-4" />
                                        <span class="sr-only">Actions</span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="openEditDialog(item)">
                                        <Pencil class="mr-2 h-4 w-4" />
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        class="text-destructive focus:text-destructive"
                                        @click="promptRemove(item.id)"
                                    >
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </template>

                        <Button
                            v-else
                            type="button"
                            variant="outline"
                            size="sm"
                            class="gap-1.5"
                            :disabled="isRestoring"
                            @click="restoreItem(item.id)"
                        >
                            <Loader2
                                v-if="isRestoring"
                                class="h-3.5 w-3.5 animate-spin"
                            />
                            <RotateCcw v-else class="h-3.5 w-3.5" />
                            Restore
                        </Button>
                    </div>
                </div>
            </div>

            <div
                v-if="filteredItems.length > 0"
                class="flex flex-col gap-2 border-t bg-background/95 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="text-muted-foreground">{{ pageRangeLabel }}</div>

                <div class="flex items-center gap-2">
                    <label class="text-muted-foreground" for="rows-per-page">
                        Rows
                    </label>
                    <select
                        id="rows-per-page"
                        v-model.number="rowsPerPage"
                        class="h-8 rounded-md border border-input bg-background px-2 text-sm"
                    >
                        <option :value="10">10</option>
                        <option :value="20">20</option>
                        <option :value="50">50</option>
                    </select>

                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="currentPage <= 1"
                        @click="currentPage -= 1"
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="currentPage >= totalPages"
                        @click="currentPage += 1"
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>

        <Dialog v-model:open="addDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle
                        >Add {{ toTitleCase(singularLabel) }}</DialogTitle
                    >
                    <DialogDescription>
                        Enter a name for the new {{ singularLabel }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-1">
                    <Input
                        v-model="newName"
                        placeholder="Name"
                        :disabled="isCreating"
                        @keydown.enter.prevent="createItem"
                        autofocus
                    />
                    <p
                        v-if="createErrors.name?.length"
                        class="text-sm text-destructive"
                    >
                        {{ createErrors.name[0] }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        :disabled="isCreating"
                        @click="addDialogOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        :disabled="isCreating || !newName.trim()"
                        @click="createItem"
                    >
                        <Loader2
                            v-if="isCreating"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        Create
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="editDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle
                        >Edit {{ toTitleCase(singularLabel) }}</DialogTitle
                    >
                    <DialogDescription>
                        Update the {{ singularLabel }} name.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-1">
                    <Input
                        v-model="editName"
                        placeholder="Name"
                        :disabled="isUpdating"
                        @keydown.enter.prevent="updateItem"
                        autofocus
                    />
                    <p
                        v-if="updateErrors.name?.length"
                        class="text-sm text-destructive"
                    >
                        {{ updateErrors.name[0] }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        :disabled="isUpdating"
                        @click="editDialogOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        :disabled="isUpdating || !editName.trim()"
                        @click="updateItem"
                    >
                        <Loader2
                            v-if="isUpdating"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        Save changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="removeConfirmOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle
                        >Delete {{ toTitleCase(singularLabel) }}</DialogTitle
                    >
                    <DialogDescription>
                        This will move the {{ singularLabel }} to deleted items
                        and hide it from selectors.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2 sm:justify-end">
                    <Button
                        type="button"
                        variant="ghost"
                        :disabled="isDeleting"
                        @click="removeConfirmOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="isDeleting"
                        @click="confirmRemove"
                    >
                        <Loader2
                            v-if="isDeleting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
