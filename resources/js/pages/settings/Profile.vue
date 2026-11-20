<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { IdCard, Mail, Layers, Group, Briefcase, AlertCircle, Camera } from 'lucide-vue-next';

import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/layout/Heading.vue';
import InputError from '@/components/common/InputError.vue';
import AvatarCropDialog from '@/components/profile/AvatarCropDialog.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

import { Label } from '@/components/ui/label';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { type BreadcrumbItem } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

const page = usePage();
const user = computed(() => page.props.auth.user);

const { getInitials } = useInitials();

// Avatar handling
const fileInput = ref<HTMLInputElement | null>(null);
const autoSubmitButton = ref<HTMLButtonElement | null>(null);
const avatarPreview = ref<string | null>(null);
const selectedFile = ref<File | null>(null);
const avatarClientError = ref<string | null>(null);

const cropDialogOpen = ref(false);
const cropSourceDataUrl = ref<string | null>(null);

const validAvatarTypes = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
]);
const maxAvatarSizeBytes = 5 * 1024 * 1024;

const showAvatar = computed(
    () => (avatarPreview.value || user.value.avatar) && user.value.avatar !== '',
);

const avatarUrl = computed(() => {
    if (avatarPreview.value) {
        return avatarPreview.value;
    }
    if (user.value.avatar) {
        return user.value.avatar.startsWith('/') ? user.value.avatar : `/storage/${user.value.avatar}`;
    }
    return null;
});

const handleAvatarClick = () => {
    fileInput.value?.click();
};

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    avatarClientError.value = null;

    if (!file) {
        return;
    }

    if (!validAvatarTypes.has(file.type)) {
        avatarClientError.value = 'Please select a JPG, PNG, or WebP image.';
        target.value = '';
        return;
    }

    if (file.size > maxAvatarSizeBytes) {
        avatarClientError.value = 'Please select an image smaller than 5 MB.';
        target.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        cropSourceDataUrl.value = e.target?.result as string;
        cropDialogOpen.value = true;
    };
    reader.readAsDataURL(file);
};

const handleCropCancel = () => {
    cropDialogOpen.value = false;
    cropSourceDataUrl.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const handleCropConfirm = (file: File) => {
    if (avatarPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(avatarPreview.value);
    }

    selectedFile.value = file;
    avatarPreview.value = URL.createObjectURL(file);

    const formFileInput = document.querySelector('input[name="avatar"]') as HTMLInputElement;
    if (formFileInput) {
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        formFileInput.files = dataTransfer.files;
    }

    cropDialogOpen.value = false;
    cropSourceDataUrl.value = null;

    nextTick(() => {
        autoSubmitButton.value?.click();
    });
};

onBeforeUnmount(() => {
    if (avatarPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(avatarPreview.value);
    }
});

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <h1 class="sr-only">Profile Settings</h1>

        <SettingsLayout>
            <div class="mx-auto max-w-8xl ">
                <div class="flex flex-col space-y-6">
                    <Heading
                        variant="small"
                        title="Profile information"
                        description="View your profile details. Contact your administrator to update your information."

                        />

                <!-- Email Verification Alert -->
                <Alert
                    v-if="mustVerifyEmail && !user.email_verified_at"
                    variant="default"
                    class="border-amber-200 bg-amber-50/50 dark:border-amber-800/50 dark:bg-amber-900/20"
                >
                    <AlertCircle class="h-4 w-4 text-amber-600 dark:text-amber-500" />
                    <AlertTitle class="text-amber-900 dark:text-amber-200">Email verification required</AlertTitle>
                    <AlertDescription class="text-amber-800 dark:text-amber-300">
                        Your email address is unverified.
                        <Link
                            :href="send()"
                            as="button"
                            class="font-medium underline underline-offset-4 transition-colors hover:text-amber-900 dark:hover:text-amber-100"
                        >
                            Click here to resend the verification email.
                        </Link>
                    </AlertDescription>
                </Alert>

                <!-- Success Message -->
                <Alert
                    v-if="status === 'verification-link-sent'"
                    variant="default"
                    class="border-green-200 bg-green-50/50 dark:border-green-800/50 dark:bg-green-900/20"
                >
                    <AlertCircle class="h-4 w-4 text-green-600 dark:text-green-500" />
                    <AlertDescription class="text-green-800 dark:text-green-300">
                        A new verification link has been sent to your email address.
                    </AlertDescription>
                </Alert>

                <Form
                    v-bind="ProfileController.update.form()"
                    enctype="multipart/form-data"
                    v-slot="{ errors, processing }"
                >
                    <input type="hidden" name="name" :value="user.name" />
                    <input type="hidden" name="email" :value="user.email" />

                    <!-- Two Column Layout: Photo Left, Info Right -->
                    <div class="grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-12 py-8">
                        <!-- Left Column: Avatar and Upload Button -->
                        <div class="flex flex-col items-center gap-4">
                            <!-- Avatar with hover effect -->
                            <div class="relative group">
                                <button
                                    type="button"
                                    class="relative focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rounded-full transition-transform hover:scale-105 cursor-pointer"
                                    @click="handleAvatarClick"
                                    aria-label="Change profile photo"
                                >
                                    <Avatar class="h-40 w-40 overflow-hidden rounded-full ring-2 ring-border transition-all group-hover:ring-primary/50">
                                        <AvatarImage v-if="avatarUrl" :src="avatarUrl" :alt="user.name" />
                                        <AvatarFallback class="rounded-full text-4xl font-medium text-foreground bg-muted">
                                            {{ getInitials(user.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <!-- Hover overlay -->
                                    <div class="absolute inset-0 flex items-center justify-center rounded-full bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <Camera class="h-8 w-8 text-white" />
                                    </div>
                                </button>
                            </div>

                            <input
                                ref="fileInput"
                                type="file"
                                name="avatar"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                aria-hidden="true"
                                @change="handleFileChange"
                            />

                            <!-- Upload Button -->
                            <div class="flex flex-col items-center gap-2 w-full">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="handleAvatarClick"
                                    class="gap-2 w-full max-w-[200px] cursor-pointer"
                                >
                                    <Camera class="h-4 w-4" />
                                    Change photo
                                </Button>
                                <p class="text-xs text-muted-foreground text-center">
                                    JPG, PNG, WebP • Max 5 MB
                                </p>
                                <InputError :message="avatarClientError || errors.avatar" class="text-center" />
                            </div>

                            <button ref="autoSubmitButton" type="submit" class="sr-only" aria-hidden="true" :disabled="processing" />
                        </div>

                        <!-- Right Column: Personal Information -->
                        <div class="flex flex-col gap-8 mt-10">
                            <!-- Centered Text Header -->


                            <!-- Personal Information Fields -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Name Field -->
                                <div class="flex items-start gap-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <IdCard class="h-5 w-5 text-blue-500 " />
                                    </div>
                                    <div class="flex-1 min-w-0 space-y-1">
                                        <Label class="text-sm font-medium text-muted-foreground">Full Name</Label>
                                        <p class="text-base font-medium text-foreground">{{ user.name }}</p>
                                    </div>
                                </div>

                                <!-- Email Field -->
                                <div class="flex items-start gap-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <Mail class="h-5 w-5 text-blue-500" />
                                    </div>
                                    <div class="flex-1 min-w-0 space-y-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium text-muted-foreground">Email Address</Label>
                                            <span
                                                v-if="user.email_verified_at"
                                                class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
                                            >
                                                Verified
                                            </span>
                                        </div>
                                        <p class="text-base font-medium text-foreground break-all">{{ user.email }}</p>
                                    </div>
                                </div>

                                <!-- Department Field -->
                                <div class="flex items-start gap-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <Layers class="h-5 w-5 text-blue-600" />
                                    </div>
                                    <div class="flex-1 min-w-0 space-y-1">
                                        <Label class="text-sm font-medium text-muted-foreground">Division</Label>
                                        <p class="text-base font-medium text-foreground">
                                            {{ user.department?.name ?? 'Not assigned' }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Unit Field -->
                                <div class="flex items-start gap-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <Group class="h-5 w-5 text-blue-500" />
                                    </div>
                                    <div class="flex-1 min-w-0 space-y-1">
                                        <Label class="text-sm font-medium text-muted-foreground">Unit</Label>
                                        <p class="text-base font-medium text-foreground">
                                            {{ user.unit?.name ?? 'Not assigned' }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Position Field -->
                                <div class="flex items-start gap-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <Briefcase class="h-5 w-5 text-blue-500" />
                                    </div>
                                    <div class="flex-1 min-w-0 space-y-1">
                                        <Label class="text-sm font-medium text-muted-foreground">Position</Label>
                                        <p class="text-base font-medium text-foreground">
                                            {{ user.position?.name ?? 'Not assigned' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Notice - Centered at Bottom -->
                    <div class="flex justify-center">
                        <div class="rounded-lg border border-border bg-muted/50 p-4 max-w-4xl w-full">
                            <p class="text-sm text-muted-foreground text-center">
                                <span class="font-medium  text-blue-500">Note:</span> To update your personal information, department, or unit assignment, please contact your system administrator.
                            </p>
                        </div>
                    </div>
                </Form>
                </div>
            </div>

            <AvatarCropDialog
                v-model:open="cropDialogOpen"
                :src="cropSourceDataUrl"
                @cancel="handleCropCancel"
                @confirm="handleCropConfirm"
            />
        </SettingsLayout>
    </AppLayout>
</template>
