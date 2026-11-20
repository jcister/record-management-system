<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/common/AppearanceTabs.vue';
import IconColorPicker from '@/components/common/IconColorPicker.vue';
import Heading from '@/components/layout/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance';
import { type BreadcrumbItem } from '@/types';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance" />

        <h1 class="sr-only">Appearance Settings</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Themes"
                    description="Choose your preferred theme for the system interface."
                />
                <AppearanceTabs />
            </div>

            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Sidebar Icon Color"
                    description="Choose a color for the sidebar navigation icons."
                />
                <IconColorPicker />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
