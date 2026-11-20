<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

import LookupResourceManager from '@/components/systemsettings/LookupResourceManager.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useToast } from '@/composables/useToast';
import { apiClient } from '@/lib/axios';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface LookupItem {
    id: number;
    name: string;
    deleted_at?: string | null;
}

const { error } = useToast();

const activeLookupTab = ref('divisions');
const isLoading = ref(true);
const divisions = ref<LookupItem[]>([]);
const positions = ref<LookupItem[]>([]);
const units = ref<LookupItem[]>([]);

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Division, Position, and Unit Management',
        description: 'Maintain lookup values in one place. Provisioning selectors use these values in read-only mode.',
        href: '/systemsettings/lookup-management',
    },
];

onMounted(async () => {
    try {
        const [depRes, posRes, unitRes] = await Promise.all([
            apiClient.get<{ data: LookupItem[] }>('/api/departments'),
            apiClient.get<{ data: LookupItem[] }>('/api/positions'),
            apiClient.get<{ data: LookupItem[] }>('/api/units'),
        ]);
        divisions.value = depRes.data.data ?? [];
        positions.value = posRes.data.data ?? [];
        units.value = unitRes.data.data ?? [];
    } catch (err) {
        error('Load failed', 'Unable to load lookup data.');
        console.error(err);
    } finally {
        isLoading.value = false;
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">

        <SettingsLayout>
            <div class="flex h-full flex-1 flex-col gap-6">
                <div v-if="isLoading" class="flex flex-1 items-center justify-center gap-2 text-sm text-muted-foreground">
                    <Loader2 class="h-4 w-4 animate-spin" />
                    Loading lookup data...
                </div>

                <div v-else class="flex flex-1 flex-col overflow-hidden">
                    <div class="flex h-full min-h-0 flex-col">
                        <Tabs v-model="activeLookupTab" class="flex h-full min-h-0 flex-col gap-5">
                            <TabsList class="grid w-full grid-cols-3">
                                <TabsTrigger value="divisions">Divisions</TabsTrigger>
                                <TabsTrigger value="positions">Positions</TabsTrigger>
                                <TabsTrigger value="units">Units</TabsTrigger>
                            </TabsList>

                            <TabsContent value="divisions" class="mt-0 min-h-0 flex-1">
                                <LookupResourceManager
                                    title="Divisions"
                                    description="Create, rename, archive, and restore divisions used across user management forms."
                                    resource-url="/api/departments"
                                    singular-label="division"
                                    :initial-items="divisions"
                                />
                            </TabsContent>

                            <TabsContent value="positions" class="mt-0 min-h-0 flex-1">
                                <LookupResourceManager
                                    title="Positions"
                                    description="Maintain position titles available when assigning users and generating records."
                                    resource-url="/api/positions"
                                    singular-label="position"
                                    :initial-items="positions"
                                />
                            </TabsContent>

                            <TabsContent value="units" class="mt-0 min-h-0 flex-1">
                                <LookupResourceManager
                                    title="Units"
                                    description="Keep unit names up to date for account setup and reporting consistency."
                                    resource-url="/api/units"
                                    singular-label="unit"
                                    :initial-items="units"
                                />
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
