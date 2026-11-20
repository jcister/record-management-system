<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Menu } from 'lucide-vue-next';

import AppContent from '@/components/layout/AppContent.vue';
import AppLogo from '@/components/layout/AppLogo.vue';
import AppSidebar from '@/components/layout/AppSidebar.vue';
import AppSidebarHeader from '@/components/layout/AppSidebarHeader.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import GlobalFileUpload from '@/components/records/GlobalFileUpload.vue';
import ToastContainer from '@/components/common/ToastContainer.vue';
import type { BreadcrumbItemType } from '@/types';

const sidebarRef = ref<InstanceType<typeof AppSidebar> | null>(null);



interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    showLayoutToggle?: boolean;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    showLayoutToggle: false,
});

// Search was moved from the global sidebar layout into the Records pages.

</script>

<template>
    <div class="flex flex-col h-screen w-full">
        <!-- Mobile Header -->
        <div class="sm:hidden flex items-center justify-between h-14 px-4 border-b border-border/50 bg-background shrink-0">
            <Link href="/dashboard">
                <AppLogo />
            </Link>
            <button
                @click="sidebarRef?.toggleMobile()"
                class="p-2 rounded-lg hover:bg-accent/50 transition-colors"
                aria-label="Open navigation"
            >
                <Menu class="size-5" />
            </button>
        </div>

        <!-- Main row -->
        <div class="flex flex-1 overflow-hidden">
            <AppSidebar ref="sidebarRef" />
            <div class="flex flex-1 min-w-0 w-full flex-col md:mr-2">
                <!-- <div class="min-h-20 flex shrink-0 items-center justify-between"></div> -->

                <AppContent variant="sidebar" class="flex flex-1 flex-col overflow-hidden rounded-2xl mb-2">
                    <AppSidebarHeader :breadcrumbs="breadcrumbs" :showLayoutToggle="showLayoutToggle">
                    <template v-if="$slots['header-actions']" #actions>
                        <slot name="header-actions" />
                    </template>
                </AppSidebarHeader>
                    <div class="h-full overflow-y-auto min-w-0 w-full">
                        <slot />
                    </div>
                </AppContent>
            </div>
        </div>

        <GlobalFileUpload />

        <!-- Global Confirm Dialog -->
        <ConfirmDialog />

        <!-- Global Toast Container -->
        <ToastContainer />
    </div>
</template>
