<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { apiClient } from '@/lib/axios';
import { update as updateFile } from '@/routes/api/files';
import { update as updateFolder } from '@/routes/api/folders';
import { stripExtension } from '@/utils/upload';

const props = defineProps<{
    itemId: string;
    itemType: 'folder' | 'file';
    initialName: string;
}>();

const emit = defineEmits<{
    renamed: [itemId: string, itemType: 'folder' | 'file', newName: string];
}>();

const open = defineModel<boolean>('open', { default: false });
const processing = ref(false);
const errors = ref<{ name?: string[] }>({});
const formData = ref({ name: props.initialName || '' });

const normalizeFileNameInput = (value?: string | null): string => {
    const trimmed = value?.trim() ?? '';
    if (!trimmed) {
        return '';
    }

    const lastDot = trimmed.lastIndexOf('.');
    if (lastDot > 0) {
        const possibleExt = trimmed.slice(lastDot + 1);
        if (/^[A-Za-z0-9]{1,8}$/.test(possibleExt)) {
            return stripExtension(trimmed);
        }
    }

    return trimmed;
};

watch(() => props.initialName, (v) => {
    const incoming = v || '';
    formData.value.name = props.itemType === 'file' ? normalizeFileNameInput(incoming) : incoming;
});

watch(
    () => formData.value.name,
    (value) => {
        if (props.itemType !== 'file') {
            return;
        }

        const normalized = normalizeFileNameInput(value);
        if (normalized === value) {
            return;
        }

        formData.value.name = normalized;
    }
);

const submitRename = async () => {
    processing.value = true;
    errors.value = {};

    try {
        const payloadName = props.itemType === 'file'
            ? normalizeFileNameInput(formData.value.name)
            : formData.value.name;

        if (props.itemType === 'folder') {
            await apiClient.patch(updateFolder(props.itemId).url, { name: formData.value.name });
        } else {
            await apiClient.patch(updateFile(props.itemId).url, { file_name: payloadName });
        }

        open.value = false;
        emit('renamed', props.itemId, props.itemType, payloadName);
        router.reload({ only: [props.itemType === 'folder' ? 'folders' : 'files'] });
    } catch (error: any) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else if (error.response?.data?.message) {
            errors.value = { name: [error.response.data.message] };
        }
    } finally {
        processing.value = false;
    }
};

const itemTypeTitle = computed((): string => {
    return props.itemType === 'folder' ? 'Rename Folder' : 'Rename File';
});

const labelText = computed((): string => {
    return props.itemType === 'folder' ? 'Folder Name' : 'File Name';
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ itemTypeTitle }}</DialogTitle>
                <DialogDescription>
                    Rename the {{ props.itemType }} to a new name.
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitRename">
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="name">{{ labelText }}</Label>
                        <Input id="name" v-model="formData.name" placeholder="Enter new name" :disabled="processing" />
                        <p v-if="errors.name" class="text-sm text-red-500">
                            {{ errors.name[0] }}
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false" :disabled="processing">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="processing">
                        Rename
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
