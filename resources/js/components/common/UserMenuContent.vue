<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Settings } from 'lucide-vue-next';
import { computed, toRef } from 'vue';

import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from './UserInfo.vue';
import { logout } from '@/routes';
import { information as systemInformation } from '@/routes/systemsettings';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

interface Props {
    user: User;
}

const handleLogout = () => {
    router.flushAll();
};

const props = defineProps<Props>();
const user = toRef(props, 'user');

const page = usePage();
const canManageAgencyInformation = computed(() => (page.props.auth as { can?: Record<string, boolean> })?.can?.manageAgencyInformation ?? false);

const roleSummary = computed(() => {
    const roles = user.value?.roles ?? [];

    if (roles.length === 0) {
        return 'Requestor baseline';
    }

    return roles.map((role) => role.name).join(' / ');
});
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal ">
        <Link class="block w-full cursor-pointer hover:bg-accent/90" :href="edit()">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm ">
            <UserInfo :user="user" />
            <div class="grid min-w-0 flex-1 text-left text-sm leading-tight">
                <span class="truncate font-medium">{{ user.name }}</span>
                <span class="truncate text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                    {{ roleSummary }}
                </span>
                <span class="truncate text-xs text-muted-foreground">{{ user.email }}</span>
            </div>
        </div>
        </Link>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
  
    </DropdownMenuGroup>

    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
