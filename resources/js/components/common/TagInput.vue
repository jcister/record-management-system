<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { X } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
} from '@/components/ui/combobox';
import { Label } from '@/components/ui/label';
import { apiClient } from '@/lib/axios';

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
}

const props = defineProps<{
    label: string;
    type: 'metadata' | 'physical_location';
    disabled?: boolean;
    allowCreate?: boolean;
}>();

const emit = defineEmits<{
    (e: 'search', value: string): void;
}>();

const selectedTagIds = defineModel<number[]>({ default: [] });

const availableTags = ref<Tag[]>([]);
const loading = ref(false);
const searchQuery = ref('');
const open = ref(false);

const selectedTags = computed(() => {
    return selectedTagIds.value
        .map(id => availableTags.value.find(tag => tag.id === id))
        .filter((tag): tag is Tag => tag !== undefined);
});

const unselectedTags = computed(() => {
    return availableTags.value.filter(tag => !selectedTagIds.value.includes(tag.id));
});

const filteredTags = computed(() => {
    if (!searchQuery.value.trim()) return unselectedTags.value;

    const query = searchQuery.value.toLowerCase();
    return unselectedTags.value.filter(tag =>
        tag.name.toLowerCase().includes(query)
    );
});

const loadTags = async () => {
    loading.value = true;
    try {
        const response = await apiClient.get<Tag[]>(`/api/tags?type=${props.type}`);
        availableTags.value = Array.isArray(response.data) ? response.data : [];
    } catch (error) {
        console.error('Failed to load tags:', error);
    } finally {
        loading.value = false;
    }
};

const createTag = async (name: string): Promise<Tag | null> => {
    if (!props.allowCreate) return null;

    const normalizedName = formatTagName(name, props.type);
    if (!normalizedName) return null;

    try {
        const response = await apiClient.post<Tag>('/api/tags', {
            name: normalizedName,
            type: props.type,
        });

        const newTag = response.data;
        availableTags.value.push(newTag);
        return newTag;
    } catch (error) {
        console.error('Failed to create tag:', error);
        return null;
    }
};

const selectTag = (tagId: number) => {
    if (!selectedTagIds.value.includes(tagId)) {
        selectedTagIds.value = [...selectedTagIds.value, tagId];
    }
    // Clear the search query
    searchQuery.value = '';
};

const removeTag = (tagId: number) => {
    selectedTagIds.value = selectedTagIds.value.filter(id => id !== tagId);
};

// Watch search query to open dropdown when user starts typing
watch(searchQuery, (newValue) => {
    if (newValue.length > 0 && unselectedTags.value.length > 0) {
        open.value = true;
    }

    emit('search', newValue);
});

const handleKeyDown = async (event: KeyboardEvent) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        event.stopPropagation();

        // Get value directly from the input element
        const target = event.target as HTMLInputElement;
        const trimmedValue = target.value.trim();

        if (!trimmedValue) return;

        // Check if exact match exists
        const exactMatch = availableTags.value.find(
            tag => tag.name.toLowerCase() === trimmedValue.toLowerCase()
        );

        if (exactMatch) {
            selectTag(exactMatch.id);
            open.value = false;
        } else if (props.allowCreate) {
            const newTag = await createTag(trimmedValue);
            if (newTag) {
                selectTag(newTag.id);
                open.value = false;
            }
        }
        // Close dropdown if no match and can't create
        if (!exactMatch && !props.allowCreate) {
            open.value = false;
        }
    }
};

const filterFunction = (list: { value: string }[], term: string) => {
    if (!term) return list;

    const query = term.toLowerCase();
    return list.filter(item => item.value.toLowerCase().includes(query));
};

watch(() => props.type, loadTags, { immediate: true });
</script>

<template>
    <div class="flex flex-col gap-2">
        <Label v-if="label">{{ label }}</Label>

        <!-- Selected Tags -->
        <div v-if="selectedTags.length > 0" class="flex flex-wrap gap-2 mb-2">
            <Badge
                v-for="tag in selectedTags"
                :key="tag.id"
                variant="secondary"
                class="flex items-center gap-1 px-2 py-1"
            >
                <span>{{ tag.name }}</span>
                <Button
                    v-if="!disabled"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="h-4 w-4 p-0 hover:bg-transparent"
                    @click="removeTag(tag.id)"
                >
                    <X class="h-3 w-3" />
                </Button>
            </Badge>
        </div>

        <!-- Tag Input with Autocomplete -->
        <Combobox
            v-if="!disabled"
            v-model:open="open"
            :filter-function="filterFunction"
        >
            <ComboboxAnchor class="w-full">
                <ComboboxInput
                    v-model="searchQuery"
                    :placeholder="allowCreate ? 'Search or type to create...' : 'Search tags...'"
                    class="w-full"
                    @keydown="handleKeyDown"
                />
            </ComboboxAnchor>

            <ComboboxContent>
                <ComboboxEmpty v-if="loading">
                    Loading tags...
                </ComboboxEmpty>
                <ComboboxEmpty v-else-if="filteredTags.length === 0 && !searchQuery.trim()">
                    No tags available
                </ComboboxEmpty>
                <ComboboxEmpty v-else-if="filteredTags.length === 0 && allowCreate">
                    Press Enter to create "{{ searchQuery }}"
                </ComboboxEmpty>
                <ComboboxEmpty v-else-if="filteredTags.length === 0">
                    No tags found
                </ComboboxEmpty>

                <ComboboxItem
                    v-for="tag in filteredTags"
                    :key="tag.id"
                    :value="tag.name"
                    @click="selectTag(tag.id)"
                >
                    {{ tag.name }}
                </ComboboxItem>
            </ComboboxContent>
        </Combobox>
    </div>
</template>
