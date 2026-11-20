<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import Heading from '@/components/layout/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { information } from '@/routes/systemsettings';
import { type BreadcrumbItem } from '@/types';

interface Props {
    config: {
        agency_name: string | null;
        telephone: string | null;
        address: string | null;
        regional_director_name: string | null;
        regional_director_designation: string | null;
        updated_at: string | null;
    };
}

const props = defineProps<Props>();
const { success, error } = useToast();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Agency Information',
        href: information().url,
    },
];

const form = useForm({
    agency_name: props.config.agency_name ?? '',
    telephone: props.config.telephone ?? '',
    address: props.config.address ?? '',
    regional_director_name: props.config.regional_director_name ?? '',
    regional_director_designation:
        props.config.regional_director_designation ?? '',
});

const lastSaved = ref<Date | null>(
    props.config.updated_at ? new Date(props.config.updated_at) : null,
);
const showSavedCheck = ref(false);

const lastSavedText = computed(() => {
    if (!lastSaved.value) return null;

    const now = new Date();
    const diff = Math.floor((now.getTime() - lastSaved.value.getTime()) / 1000);

    if (diff < 60) return 'Saved just now';
    if (diff < 3600) return `Saved ${Math.floor(diff / 60)} minutes ago`;
    if (diff < 86400) return `Saved ${Math.floor(diff / 3600)} hours ago`;
    return `Saved on ${lastSaved.value.toLocaleDateString()}`;
});

const handleSave = (): void => {
    form.post(information().url, {
        preserveScroll: true,
        onSuccess: () => {
            success(
                'Settings Saved',
                'System configuration has been updated successfully.',
            );
            lastSaved.value = new Date();
            showSavedCheck.value = true;

            // Hide checkmark after 3 seconds
            setTimeout(() => {
                showSavedCheck.value = false;
            }, 3000);
        },
        onError: (errors) => {
            const errorMessage = Object.values(errors).join(' ');
            error(
                'Save Failed',
                errorMessage || 'Failed to update system configuration.',
            );
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Agency Info" />

        <h1 class="sr-only">Agency Profile</h1>

        <SettingsLayout>
            <div class="space-y-8">
                <Heading
                    variant="small"
                    title="Agency Profile"
                    description="Configure your agency details and regional director information. These values will be used in generated documents."
                />

                <Card class="max-w-4xl">
                    <CardHeader class="pb-4">
                        <CardTitle>Agency details</CardTitle>
                        <CardDescription
                            >Provide the information that appears across the
                            platform and in PDF documents.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-6" @submit.prevent="handleSave">
                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="agencyName">Agency Name</Label>
                                    <Input
                                        id="agencyName"
                                        v-model="form.agency_name"
                                        name="agency_name"
                                        placeholder="e.g. Commission on Higher Education"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="telephone">Telephone</Label>
                                    <Input
                                        id="telephone"
                                        v-model="form.telephone"
                                        name="telephone"
                                        placeholder="e.g. +63 (2) 1234 5678"
                                    />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="address">Address</Label>
                                <Textarea
                                    id="address"
                                    v-model="form.address"
                                    name="address"
                                    rows="4"
                                    placeholder="Street, city, province, country"
                                />
                            </div>

                            <div class="mt-6 border-t pt-4">
                                <h3 class="mb-4 text-sm font-semibold">
                                    Regional Director
                                </h3>
                                <div class="grid gap-6 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="regionalDirectorName"
                                            >Director Name</Label
                                        >
                                        <Input
                                            id="regionalDirectorName"
                                            v-model="
                                                form.regional_director_name
                                            "
                                            name="regional_director_name"
                                            placeholder="e.g. Maricar R. Casquejo, Ph.D, CESO III"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="regionalDirectorDesignation"
                                            >Designation</Label
                                        >
                                        <Input
                                            id="regionalDirectorDesignation"
                                            v-model="
                                                form.regional_director_designation
                                            "
                                            name="regional_director_designation"
                                            placeholder="e.g. Director IV"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="flex flex-col gap-1">
                                    <p class="text-sm text-muted-foreground">
                                        Changes will be reflected in NAP Form 3s
                                        and Endorsement Letters to COA.
                                    </p>
                                    <p
                                        v-if="lastSavedText"
                                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                    >
                                        <Check class="h-3 w-3 text-green-600" />
                                        {{ lastSavedText }}
                                    </p>
                                </div>
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                    :class="{
                                        'bg-green-600 hover:bg-green-700':
                                            showSavedCheck,
                                    }"
                                    class="min-w-35 self-start sm:self-auto"
                                >
                                    <Check
                                        v-if="
                                            showSavedCheck && !form.processing
                                        "
                                        class="mr-2 h-4 w-4"
                                    />
                                    <span v-if="form.processing"
                                        >Saving...</span
                                    >
                                    <span v-else-if="showSavedCheck"
                                        >Saved!</span
                                    >
                                    <span v-else>Save changes</span>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
