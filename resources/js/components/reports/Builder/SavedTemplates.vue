<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { BookOpen, Save, Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface SavedTemplate {
    id: string;
    name: string;
    source: string;
    columns: string[];
    conditions: unknown[];
    group_by: string;
    sort_by: string;
    sort_dir: string;
    saved_at: string;
}

const props = defineProps<{
    currentConfig: Omit<SavedTemplate, 'id' | 'name' | 'saved_at'>;
}>();

const emit = defineEmits<{
    (e: 'load', template: SavedTemplate): void;
}>();

const STORAGE_KEY = 'report_builder_templates';

const templates = ref<SavedTemplate[]>([]);
const newTemplateName = ref('');
const showSaveInput = ref(false);

const loadFromStorage = () => {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        templates.value = raw ? JSON.parse(raw) : [];
    } catch {
        templates.value = [];
    }
};

const saveToStorage = () => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(templates.value));
};

const handleSave = () => {
    const name = newTemplateName.value.trim();
    if (!name) return;

    const template: SavedTemplate = {
        id: Date.now().toString(),
        name,
        ...props.currentConfig,
        saved_at: new Date().toLocaleDateString('en-US', { dateStyle: 'medium' }),
    };

    templates.value = [template, ...templates.value].slice(0, 10);
    saveToStorage();
    newTemplateName.value = '';
    showSaveInput.value = false;
};

const handleDelete = (id: string) => {
    templates.value = templates.value.filter(t => t.id !== id);
    saveToStorage();
};

const handleLoad = (template: SavedTemplate) => {
    emit('load', template);
};

onMounted(loadFromStorage);
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5">
                <BookOpen class="h-3.5 w-3.5 text-muted-foreground" />
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Saved Templates</p>
            </div>
            <Button
                variant="ghost"
                size="sm"
                class="h-7 gap-1 text-xs"
                @click="showSaveInput = !showSaveInput"
            >
                <Save class="h-3 w-3" />
                Save Current
            </Button>
        </div>

        <!-- Save template input -->
        <div v-if="showSaveInput" class="flex items-center gap-2">
            <Input
                v-model="newTemplateName"
                placeholder="Template name…"
                class="h-8 flex-1 text-xs"
                @keydown.enter="handleSave"
                @keydown.escape="showSaveInput = false"
            />
            <Button size="sm" class="h-8 text-xs" @click="handleSave" :disabled="!newTemplateName.trim()">
                Save
            </Button>
        </div>

        <!-- Template list -->
        <div v-if="templates.length > 0" class="flex flex-col gap-1">
            <div
                v-for="template in templates"
                :key="template.id"
                class="group flex items-center justify-between rounded-lg px-2.5 py-2 text-left transition-colors cursor-pointer hover:bg-muted/60 focus-within:ring-2 focus-within:ring-primary/60"
                @click="handleLoad(template)"
            >
                <div class="flex-1 min-w-0" tabindex="0" @keydown.enter.space="handleLoad(template)">
                    <p class="truncate text-xs font-medium text-foreground">{{ template.name }}</p>
                    <p class="truncate text-[10px] text-muted-foreground capitalize">{{ template.source }} · {{ template.columns.length }} field{{ template.columns.length !== 1 ? 's' : '' }} · {{ template.saved_at }}</p>
                </div>
                <button
                    type="button"
                    class="ml-2 shrink-0 rounded p-0.5 text-muted-foreground opacity-0 transition-opacity hover:text-rose-500 group-hover:opacity-100 focus:opacity-100 focus:outline-none"
                    title="Delete template"
                    @click.stop="handleDelete(template.id)"
                >
                    <Trash2 class="h-3 w-3" />
                </button>
            </div>
        </div>
        <p v-else-if="!showSaveInput" class="text-[11px] text-muted-foreground/60 italic">
            No saved templates yet. Configure a report and click "Save Current" to save it for later.
        </p>
    </div>
</template>
