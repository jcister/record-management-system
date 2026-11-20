<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, useAttrs } from 'vue';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/common/UserInfo.vue';
import { cn } from '@/lib/utils';

import UserMenuContent from '@/components/common/UserMenuContent.vue';



const page = usePage();
const user = computed(() => page.props.auth.user);

const attrs = useAttrs();
const mergedClass = computed(() =>
    cn(
        // default trigger styling: avatar + text in a compact row, left-aligned
        'flex w-full max-w-full items-center gap-3 px-3 rounded-md hover:bg-accent/50 transition-colors justify-start',
        (attrs.class as string | undefined)
    )
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child class="cursor-pointer hover:bg-accent/90  p-1 ">
            <button :class="mergedClass" v-bind="$attrs">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="shrink-0">
                        <UserInfo :user="user" />
                    </div>
                    <div v-if="user" class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium">{{ user.name }}</span>
                    </div>
                </div>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-56 min-w-19 rounded-lg" align="end" :side-offset="4">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
