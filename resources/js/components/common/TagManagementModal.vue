<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import { Search, Plus, Check, RotateCcw, X, MoreVertical, PencilLine, Archive } from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { apiClient } from '@/lib/axios';
import { useToast } from '@/composables/useToast';
import type { AppPageProps } from '@/types';

const formatTagName = (value: string, type: 'metadata' | 'physical_location'): string => {
    const normalized = value.trim().replace(/\s+/g, ' ');
    if (type === 'physical_location') {
        return normalized
            .split(' ')
            .filter(Boolean)
            .map(part => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
            .join(' ');
    }

    return normalized.toUpperCase();
};

interface Tag {
    id: number;
    name: string;
    type: 'metadata' | 'physical_location';
    deleted_at?: string | null;
}

const props = defineProps<{
    title?: string;
    description?: string;
    type: 'metadata' | 'physical_location';
    allowCreate?: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });
const selectedTagIds = defineModel<number[]>('selectedTagIds', { default: [] });

const emit = defineEmits<{
    tagCreated: [tag: Tag];
}>();

const { success, error } = useToast();
const page = usePage<AppPageProps>();

const availableTags = ref<Tag[]>([]);
const loading = ref(false);
const searchQuery = ref('');
const creatingTag = ref(false);
const localSelectedIds = ref<number[]>([]);
const showDeleted = ref(false);
const isEditingTags = ref(false);

const activeTags = computed(() => availableTags.value.filter(tag => !tag.deleted_at));
const deletedTags = computed(() => availableTags.value.filter(tag => !!tag.deleted_at));

const filteredActiveTags = computed(() => {
    if (!searchQuery.value.trim()) return activeTags.value;
    const query = searchQuery.value.toLowerCase();
    return activeTags.value.filter(tag => tag.name.toLowerCase().includes(query));
});

const filteredDeletedTags = computed(() => {
    if (!searchQuery.value.trim()) return deletedTags.value;
    const query = searchQuery.value.toLowerCase();
    return deletedTags.value.filter(tag => tag.name.toLowerCase().includes(query));
});

const canEditTags = computed(() => !!props.allowCreate);
const canManageTagOptions = computed(() => page.props.auth?.can?.organizeContent ?? false);

const loadTags = async () => {
    loading.value = true;
    try {
        const withTrashed = showDeleted.value ? '&with_trashed=1' : '';
        const response = await apiClient.get<Tag[]>(`/api/tags?type=${props.type}${withTrashed}`);
        availableTags.value = Array.isArray(response.data) ? response.data : [];
    } catch (err) {
        console.error('Failed to load tags:', err);
        error('Failed to load tags');
    } finally {
        loading.value = false;
    }
};

const toggleTag = (tagId: number) => {
    if (isEditingTags.value) {
        return;
    }

    const index = localSelectedIds.value.indexOf(tagId);
    if (index > -1) {
        localSelectedIds.value = localSelectedIds.value.filter(id => id !== tagId);
    } else {
        localSelectedIds.value = [...localSelectedIds.value, tagId];
    }
};

const isSelected = (tagId: number) => {
    return localSelectedIds.value.includes(tagId);
};

const createTag = async () => {
    if (!searchQuery.value.trim() || !props.allowCreate) return;

    const normalizedName = formatTagName(searchQuery.value, props.type);
    if (!normalizedName) return;

    creatingTag.value = true;
    try {
        const response = await apiClient.post<Tag>('/api/tags', {
            name: normalizedName,
            type: props.type,
        });

        const newTag = response.data;

        // Update or insert in local list
        const existingIndex = availableTags.value.findIndex(t => t.id === newTag.id);
        if (existingIndex > -1) {
            availableTags.value[existingIndex] = newTag;
        } else {
            availableTags.value.unshift(newTag);
        }

        searchQuery.value = '';
        success(`Tag "${newTag.name}" created`);
        emit('tagCreated', newTag);
    } catch (err) {
        console.error('Failed to create tag:', err);
        error('Failed to create tag');
    } finally {
        creatingTag.value = false;
    }
};

const deleteTag = async (tag: Tag) => {
    try {
        await apiClient.delete(`/api/tags/${tag.id}`);

        if (showDeleted.value) {
            // Mark as deleted in local list so it appears in the deleted section
            const index = availableTags.value.findIndex(t => t.id === tag.id);
            if (index > -1) {
                availableTags.value[index] = { ...tag, deleted_at: new Date().toISOString() };
            }
        } else {
            availableTags.value = availableTags.value.filter(t => t.id !== tag.id);
        }

        // Remove from selection if it was selected
        localSelectedIds.value = localSelectedIds.value.filter(id => id !== tag.id);

        success(`Tag "${tag.name}" deleted`);
    } catch (err) {
        console.error('Failed to delete tag:', err);
        error('Failed to delete tag');
    }
};

const restoreTag = async (tag: Tag) => {
    try {
        const response = await apiClient.post<Tag>(`/api/tags/${tag.id}/restore`);
        const restored = response.data;

        const index = availableTags.value.findIndex(t => t.id === tag.id);
        if (index > -1) {
            availableTags.value[index] = restored;
        }

        success(`Tag "${tag.name}" restored`);
    } catch (err) {
        console.error('Failed to restore tag:', err);
        error('Failed to restore tag');
    }
};

const toggleShowDeleted = async () => {
    showDeleted.value = !showDeleted.value;

    if (showDeleted.value) {
        isEditingTags.value = false;
    }

    await loadTags();
};

const toggleEditTags = async () => {
    isEditingTags.value = !isEditingTags.value;

    if (isEditingTags.value && showDeleted.value) {
        showDeleted.value = false;
        await loadTags();
    }
};

const handleDone = () => {
    selectedTagIds.value = localSelectedIds.value;
    isEditingTags.value = false;
    open.value = false;
};

const handleCancel = () => {
    localSelectedIds.value = [...selectedTagIds.value];
    isEditingTags.value = false;
    open.value = false;
};

watch(open, (isOpen) => {
    if (isOpen) {
        localSelectedIds.value = [...selectedTagIds.value];
        showDeleted.value = false;
        isEditingTags.value = false;
        loadTags();
        searchQuery.value = '';
    } else {
        isEditingTags.value = false;
    }
}, { immediate: true });

onMounted(() => {
    localSelectedIds.value = [...selectedTagIds.value];
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center justify-between">
                    {{ title || 'Manage Tags' }}
                    <Badge v-if="localSelectedIds.length > 0" variant="secondary" class="ml-2 font-normal">
                        {{ localSelectedIds.length }} selected
                    </Badge>
                </DialogTitle>
                <DialogDescription>
                    {{ description || 'Select tags to apply.' }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-3">
                <!-- Search + show-deleted toggle -->
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search tags..."
                            class="pl-9"
                        />
                    </div>
                    <Button
                        v-if="canManageTagOptions && isEditingTags"
                        type="button"
                        variant="secondary"
                        size="sm"
                        class="h-8 w-8 shrink-0 p-0 cursor-pointer"
                        aria-label="Done editing"
                        @click="toggleEditTags"
                    >
                        <Check class="h-3.5 w-3.5" />
                    </Button>

                    <Button
                        v-else-if="canManageTagOptions && showDeleted"
                        type="button"
                        variant="secondary"
                        size="sm"
                        class="h-8 w-8 shrink-0 p-0 cursor-pointer"
                        aria-label="Hide deleted"
                        @click="toggleShowDeleted"
                    >
                        <X class="h-3.5 w-3.5" />
                    </Button>

                    <DropdownMenu v-else-if="canManageTagOptions">
                        <DropdownMenuTrigger as-child>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 w-8 shrink-0 p-0 cursor-pointer"
                                aria-label="Tag options"
                            >
                                <MoreVertical class="h-3.5 w-3.5" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-44">
                            <DropdownMenuItem v-if="canEditTags" class="cursor-pointer" @click="toggleEditTags">
                                <PencilLine class="mr-2 h-3.5 w-3.5" />
                                <span>Edit tags</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem class="cursor-pointer" @click="toggleShowDeleted">
                                <Archive class="mr-2 h-3.5 w-3.5" />
                                <span>Show deleted</span>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <!-- Tags List -->
                <div v-if="loading" class="text-center py-8 text-muted-foreground text-sm">
                    Loading tags...
                </div>

                <template v-else>
                    <!-- Active tags -->
                    <div
                        v-if="filteredActiveTags.length === 0 && !searchQuery"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        No tags available
                    </div>

                    <div
                        v-else-if="filteredActiveTags.length === 0 && searchQuery"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        <template v-if="allowCreate">
                            <p>No tags match <span class="font-medium text-foreground mt-2">"{{ searchQuery }}"</span>.</p>
                            <button
                                type="button"
                                class="mt-1 text-primary hover:underline inline-flex items-center gap-1"
                                :disabled="creatingTag"
                                @click="createTag"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                Add "{{ searchQuery }}" as a new tag
                            </button>
                        </template>
                        <p v-else>No tags found for "{{ searchQuery }}"</p>
                    </div>

                    <div v-else class="flex flex-wrap gap-2 max-h-56 overflow-y-auto">
                        <span
                            v-for="tag in filteredActiveTags"
                            :key="tag.id"
                            class="inline-flex items-center"
                        >
                            <Badge
                                @click="toggleTag(tag.id)"
                                :variant="isSelected(tag.id) ? 'default' : 'outline'"
                                :class="[
                                    'mt-3 group select-none gap-1.5 border',
                                    isSelected(tag.id)
                                        ? 'bg-primary text-primary-foreground border-primary/70 ring-2 ring-primary/25 shadow-primary/20'
                                        : 'bg-accent/90 text-foreground border-border/70',
                                    isEditingTags
                                        ? 'cursor-default'
                                        : (isSelected(tag.id)
                                            ? 'cursor-pointer hover:bg-primary/90'
                                            : 'cursor-pointer hover:shadow-sm hover:border-primary/40 hover:bg-accent/40')
                                ]"
                            >
                                <Check v-if="isSelected(tag.id)" class="h-3 w-3" />
                                <span class="max-w-52 truncate">{{ tag.name }}</span>
                                <button
                                    v-if="canEditTags && isEditingTags"
                                    type="button"
                                    title="Delete tag"
                                    class="inline-flex h-4 w-4 items-center justify-center rounded-sm opacity-80 transition-all duration-150 hover:opacity-100 hover:bg-destructive/15 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    @click.stop="deleteTag(tag)"
                                >
                                    <X class="h-3 w-3 cursor-pointer" />
                                </button>
                            </Badge>
                        </span>
                    </div>

                    <!-- Deleted tags section -->
                    <template v-if="showDeleted && filteredDeletedTags.length > 0">
                        <div class="border-t pt-3">
                            <p class="text-xs text-muted-foreground mb-2 font-medium uppercase tracking-wide">
                                Deleted tags
                            </p>
                            <div class="flex flex-wrap gap-2 max-h-40 overflow-y-auto">
                                <span
                                    v-for="tag in filteredDeletedTags"
                                    :key="tag.id"
                                    class="inline-flex items-center gap-1"
                                >
                                    <Badge
                                        variant="outline"
                                        class="line-through text-muted-foreground border-dashed select-none"
                                    >
                                        {{ tag.name }}
                                    </Badge>
                                    <button
                                        type="button"
                                        title="Restore tag"
                                        class="text-muted-foreground hover:text-primary transition-colors"
                                        @click.stop="restoreTag(tag)"
                                    >
                                        <RotateCcw class="h-3.5 w-3.5" />
                                    </button>
                                </span>
                            </div>
                        </div>
                    </template>

                    <div
                        v-else-if="showDeleted && filteredDeletedTags.length === 0 && !loading"
                        class="border-t pt-3 text-center text-sm text-muted-foreground"
                    >
                        No deleted tags found.
                    </div>
                </template>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="handleCancel">Cancel</Button>
                <Button type="button" @click="handleDone">Done</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
