<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

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
import { store } from '@/routes/api/folders';
import { useToast } from '@/composables/useToast';

const props = defineProps<{
    parentId?: string | null;
}>();

const open = defineModel<boolean>('open', { default: false });
const processing = ref(false);
const errors = ref<{ name?: string[] }>({});
const formData = ref({
    name: '',
    parent_id: props.parentId || null,
});

const { error: showError } = useToast();

const shouldRedirectToAllRecords = (): boolean => {
    const path = window.location.pathname;
    const redirectRoots = [
        '/requests',
        '/requests/pending',
        '/requests/history',
        '/reports',
        '/disposal',
        '/user-management',
        '/bookmarks',
        '/deleted',
        '/trash',
    ];

    return redirectRoots.some(root => path.startsWith(root));
};

const createFolder = async () => {
    const currentPath = window.location.pathname;

    if (currentPath === '/records/pending-unorganized') {
        showError('Cannot Upload Here', 'Files can only be uploaded in this page.');

        return;
    }

    processing.value = true;
    errors.value = {};

    try {
        await apiClient.post(store().url, {
            ...formData.value,
            parent_id: props.parentId || null,
        });
        formData.value.name = '';
        open.value = false;

        if (props.parentId) {
            // Inside an existing folder: stay on current view.
            router.reload({ only: ['folders'] });
            return;
        }

        if (shouldRedirectToAllRecords()) {
            router.visit('/records');
            return;
        }

        // Default: refresh current page (e.g., already on All Records root).
        router.reload({ only: ['folders'] });
    } catch (error: any) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else if (error.response?.data?.message) {
            // Handle duplicate folder name error
            errors.value = { name: [error.response.data.message] };
        }
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Create New Folder</DialogTitle>
                <DialogDescription>
                    Create a new folder to organize your files.
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="createFolder">
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="name">Folder Name</Label>
                        <Input
                            id="name"
                            v-model="formData.name"
                            placeholder="Enter folder name"
                            :disabled="processing"
                        />
                        <p v-if="errors.name" class="text-sm text-red-500">
                            {{ errors.name[0] }}
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        :disabled="processing"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="processing">
                        Create Folder
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
