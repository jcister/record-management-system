<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import {
    Check,
    ChevronsUpDownIcon,
    MoreVertical,
    Pencil,
    RotateCcw,
    Search,
    ShieldCheck,
    Trash2,
} from 'lucide-vue-next';
import SearchInput from '@/components/common/SearchInput.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
    TableEmpty,
} from '@/components/ui/table';
import { computed, onMounted, reactive, ref, toRefs, watch } from 'vue';

import DirectoryController from '@/actions/App/Http/Controllers/UserManagement/DirectoryController';
import InputError from '@/components/common/InputError.vue';
import PersonNameRow from '@/components/common/PersonNameRow.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import UserManagementLayout from '@/layouts/user-management/Layout.vue';
import { BreadcrumbItem } from '@/types';
import UserManagementTableSkeleton from '@/components/user-management/UserManagementTableSkeleton.vue';

type ManagedUserRole = {
    id: number;
    name: string;
    slug: string;
};

type ManagedUser = {
    id: number;
    name: string;
    prefix: string | null;
    first_name: string | null;
    middle_name: string | null;
    last_name: string | null;
    extension: string | null;
    email: string;
    avatar: string | null;
    status: string;
    deleted_at: string | null;
    department_id: number | null;
    unit_id: number | null;
    position_id: number | null;
    roles: ManagedUserRole[];
};

type ManagedRole = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
};

type ManagedLookup = {
    id: number;
    name: string;
    deleted_at: string | null;
};

const props = defineProps<{
    users: ManagedUser[];
    roles: ManagedRole[];
    departments: ManagedLookup[];
    units: ManagedLookup[];
    positions: ManagedLookup[];
}>();

const { users, roles, departments, units, positions } = toRefs(props);

const { getInitials } = useInitials();

const selectFieldClasses = 'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

const page = usePage();
const canDeleteUsers = computed(() => (page.props.auth?.can as { canDeleteUsers?: boolean } | undefined)?.canDeleteUsers ?? false);
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

const selectedUserId = ref<number | null>(null);
const manageUserDialogOpen = ref(false);
const editUserDialogOpen = ref(false);
const editName = reactive({
    prefix: '',
    first: '',
    middle: '',
    last: '',
    extension: '',
});
const editClientErrors = reactive<{
    first_name?: string;
    last_name?: string;
    email?: string;
}>({});
const noLookupSelectionValue = '__none__';
const editDepartmentId = ref('');
const editUnitId = ref('');
const editPositionId = ref('');
const editDepartmentOpen = ref(false);
const editUnitOpen = ref(false);
const editPositionOpen = ref(false);
const editDepartmentSearchTerm = ref('');
const editUnitSearchTerm = ref('');
const editPositionSearchTerm = ref('');

const romanNumeralRegex = /^(?=[ivxlcdm]+$)m{0,4}(cm|cd|d?c{0,3})(xc|xl|l?x{0,3})(ix|iv|v?i{0,3})$/i;

const formatTitleCaseSegment = (segment: string): string => {
    if (segment.trim() === '' || segment === '-' || segment === "'") {
        return segment;
    }

    if (/^[a-z]\.?$/i.test(segment)) {
        return segment.charAt(0).toUpperCase() + (segment.endsWith('.') ? '.' : '');
    }

    if (romanNumeralRegex.test(segment)) {
        return segment.toUpperCase();
    }

    return segment.charAt(0).toUpperCase() + segment.slice(1).toLowerCase();
};

const toSmartTitleCase = (value: string): string => {
    return value
        .trim()
        .replace(/\s+/g, ' ')
        .split(/(\s+|-|')/)
        .map((segment) => formatTitleCaseSegment(segment))
        .join('');
};

const applyEditNameTitleCase = (field: 'first' | 'middle' | 'last'): void => {
    editName[field] = toSmartTitleCase(editName[field] || '');
};

const clearEditClientErrors = (): void => {
    editClientErrors.first_name = undefined;
    editClientErrors.last_name = undefined;
    editClientErrors.email = undefined;
};

const focusFirstInvalidField = (form: HTMLFormElement, fieldName: string): void => {
    requestAnimationFrame(() => {
        const field = form.querySelector<HTMLElement>(`[name="${fieldName}"]`);
        field?.focus();
    });
};

const validateEditForm = (form: HTMLFormElement): boolean => {
    clearEditClientErrors();

    let firstInvalidField: string | null = null;
    const firstName = editName.first.trim();
    const lastName = editName.last.trim();
    const email = String(new FormData(form).get('email') ?? '').trim();

    if (firstName === '') {
        editClientErrors.first_name = 'Please enter the first name.';
        firstInvalidField ??= 'first_name';
    }

    if (lastName === '') {
        editClientErrors.last_name = 'Please enter the last name.';
        firstInvalidField ??= 'last_name';
    }

    if (email === '') {
        editClientErrors.email = 'Please enter the email address.';
        firstInvalidField ??= 'email';
    }

    if (firstInvalidField) {
        focusFirstInvalidField(form, firstInvalidField);
        return false;
    }

    return true;
};

const onEditUserSubmit = (event: SubmitEvent): void => {
    const form = event.target as HTMLFormElement | null;

    if (!form) {
        return;
    }

    if (!validateEditForm(form)) {
        event.preventDefault();
        event.stopPropagation();
    }
};

const selectedUser = computed(() => {
    if (selectedUserId.value === null) {
        return null;
    }

    return users.value.find((user) => user.id === selectedUserId.value) ?? null;
});

const lookupLabel = (lookup: ManagedLookup): string => {
    return lookup.deleted_at ? `${lookup.name} (disabled)` : lookup.name;
};

const filterLookups = (items: ManagedLookup[], term: string): ManagedLookup[] => {
    const query = term.trim().toLowerCase();

    if (query === '') {
        return items;
    }

    return items.filter((item) => lookupLabel(item).toLowerCase().includes(query));
};

const lookupDisplay = (items: ManagedLookup[], selectedId: string, placeholder: string): string => {
    if (selectedId === '') {
        return placeholder;
    }

    const selectedLookup = items.find((item) => String(item.id) === selectedId);

    return selectedLookup ? lookupLabel(selectedLookup) : placeholder;
};

const filteredDepartments = computed(() => filterLookups(departments.value, editDepartmentSearchTerm.value));
const filteredUnits = computed(() => filterLookups(units.value, editUnitSearchTerm.value));
const filteredPositions = computed(() => filterLookups(positions.value, editPositionSearchTerm.value));

const editDepartmentDisplay = computed(() => lookupDisplay(departments.value, editDepartmentId.value, 'No department'));
const editUnitDisplay = computed(() => lookupDisplay(units.value, editUnitId.value, 'No unit'));
const editPositionDisplay = computed(() => lookupDisplay(positions.value, editPositionId.value, 'No position'));

const syncEditNameFromUser = (user: ManagedUser | null): void => {
    editName.prefix = user?.prefix ?? '';
    editName.first = user?.first_name ?? '';
    editName.middle = user?.middle_name ?? '';
    editName.last = user?.last_name ?? '';
    editName.extension = user?.extension ?? '';
};

const syncEditLookupFromUser = (user: ManagedUser | null): void => {
    editDepartmentId.value = user?.department_id !== null && user?.department_id !== undefined
        ? String(user.department_id)
        : '';
    editUnitId.value = user?.unit_id !== null && user?.unit_id !== undefined
        ? String(user.unit_id)
        : '';
    editPositionId.value = user?.position_id !== null && user?.position_id !== undefined
        ? String(user.position_id)
        : '';
    editDepartmentSearchTerm.value = '';
    editUnitSearchTerm.value = '';
    editPositionSearchTerm.value = '';
    editDepartmentOpen.value = false;
    editUnitOpen.value = false;
    editPositionOpen.value = false;
};

const selectEditDepartment = (departmentId: number | null): void => {
    editDepartmentId.value = departmentId === null ? '' : String(departmentId);
    editDepartmentOpen.value = false;
};

const selectEditUnit = (unitId: number | null): void => {
    editUnitId.value = unitId === null ? '' : String(unitId);
    editUnitOpen.value = false;
};

const selectEditPosition = (positionId: number | null): void => {
    editPositionId.value = positionId === null ? '' : String(positionId);
    editPositionOpen.value = false;
};

watch(users, () => {
    if (selectedUserId.value !== null && !users.value.some((user) => user.id === selectedUserId.value)) {
        selectedUserId.value = null;
        manageUserDialogOpen.value = false;
        syncEditNameFromUser(null);
        syncEditLookupFromUser(null);
    }
});

watch(editUserDialogOpen, (open) => {
    clearEditClientErrors();

    if (open) {
        syncEditNameFromUser(selectedUser.value);
        syncEditLookupFromUser(selectedUser.value);
        return;
    }

    syncEditNameFromUser(null);
    syncEditLookupFromUser(null);
});

watch(selectedUser, (user) => {
    if (!editUserDialogOpen.value) {
        return;
    }

    syncEditNameFromUser(user);
    syncEditLookupFromUser(user);
});

watch(editDepartmentOpen, (open) => {
    if (!open) {
        editDepartmentSearchTerm.value = '';
    }
});

watch(editUnitOpen, (open) => {
    if (!open) {
        editUnitSearchTerm.value = '';
    }
});

watch(editPositionOpen, (open) => {
    if (!open) {
        editPositionSearchTerm.value = '';
    }
});

type ConfirmActionKey = 'open-user-dialog' | 'update-user-roles' | 'delete-user';

const confirmMessages: Record<ConfirmActionKey, { title: string; description: string; confirmText?: string; cancelText?: string }> = {
    'open-user-dialog': {
        title: 'Review CHED ONSE KMIS access',
        description: 'This opens the role adjustment sheet for the highlighted staff record.',
        confirmText: 'Review access',
        cancelText: 'Keep browsing',
    },
    'update-user-roles': {
        title: 'Apply role updates',
        description: 'Staff access changes broadcast instantly across CHED ONSE KMIS.',
        confirmText: 'Apply changes',
        cancelText: 'Cancel',
    },
    'delete-user': {
        title: 'Disable user account',
        description: 'This will soft-delete the user account. They will no longer be able to sign in. You can restore the account later if needed.',
        confirmText: 'Disable user',
        cancelText: 'Cancel',
    },
};

const { confirm } = useConfirm();
const bypassAction = ref<ConfirmActionKey | null>(null);

const triggerFormSubmit = (event: SubmitEvent): void => {
    const form = event.target as HTMLFormElement | null;

    if (!form) {
        return;
    }

    if (typeof form.requestSubmit === 'function') {
        const submitter = event.submitter instanceof HTMLElement ? event.submitter : undefined;
        form.requestSubmit(submitter);
        return;
    }

    form.submit();
};

const confirmBeforeSubmit = async (event: SubmitEvent, action: ConfirmActionKey): Promise<void> => {
    if (bypassAction.value === action) {
        bypassAction.value = null;
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    const decision = await confirm(confirmMessages[action]);

    if (decision !== 'confirm') {
        return;
    }

    bypassAction.value = action;
    triggerFormSubmit(event);
};

const onUpdateUserRolesSubmit = async (event: SubmitEvent): Promise<void> => {
    await confirmBeforeSubmit(event, 'update-user-roles');
};

const handleDeleteUser = async (user: ManagedUser): Promise<void> => {
    const decision = await confirm(confirmMessages['delete-user']);
    if (decision !== 'confirm') {
        return;
    }
    router.delete(DirectoryController.destroyUser.url(user.id.toString()));
};

const handleRestoreUser = async (user: ManagedUser): Promise<void> => {
    const decision = await confirm({
        title: 'Restore user account',
        description: 'This will restore the user and reactivate their access.',
        confirmText: 'Restore user',
        cancelText: 'Cancel',
    });

    if (decision !== 'confirm') {
        return;
    }

    router.post(DirectoryController.restoreUser.url(user.id.toString()));
};

const openEditUserDialog = (user: ManagedUser): void => {
    selectedUserId.value = user.id;
    editUserDialogOpen.value = true;
};

const openUserAccessDialog = async (user: ManagedUser): Promise<void> => {
    const decision = await confirm(confirmMessages['open-user-dialog']);

    if (decision !== 'confirm') {
        return;
    }

    selectedUserId.value = user.id;
    manageUserDialogOpen.value = true;
};

const roleBadgePalette: Record<string, string> = {
    admin: 'border-amber-200 bg-amber-50 text-amber-700',
    editor: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    reviewer: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    contributor: 'border-sky-200 bg-sky-50 text-sky-700',
    viewer: 'border-slate-200 bg-slate-50 text-slate-600',
    'regional-director': 'border-purple-200 bg-purple-50 text-purple-700',
    'records-officer': 'border-orange-200 bg-orange-50 text-orange-700',
};

const fallbackBadgeClasses = [
    'border-sky-200 bg-sky-50 text-sky-700',
    'border-emerald-200 bg-emerald-50 text-emerald-700',
    'border-indigo-200 bg-indigo-50 text-indigo-700',
    'border-rose-200 bg-rose-50 text-rose-700',
    'border-amber-200 bg-amber-50 text-amber-700',
    'border-purple-200 bg-purple-50 text-purple-700',
    'border-teal-200 bg-teal-50 text-teal-700',
];

const toneIndex = (slug: string): number => {
    let hash = 0;

    for (let i = 0; i < slug.length; i += 1) {
        hash = (hash << 5) - hash + slug.charCodeAt(i);
        hash |= 0;
    }

    return Math.abs(hash);
};

const roleBadgeClasses = (slug: string): string => {
    if (roleBadgePalette[slug]) {
        return roleBadgePalette[slug];
    }

    return fallbackBadgeClasses[toneIndex(slug) % fallbackBadgeClasses.length];
};

const totalUsers = computed(() => users.value.length);


const searchTerm = ref('');
const statusFilter = ref<'all' | 'active' | 'disabled'>('all');

const filterButtonClasses = (key: 'all' | 'active' | 'disabled'): string => {
    const isActive = statusFilter.value === key;

    return isActive
        ? 'bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground active:bg-primary active:text-primary-foreground dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary dark:hover:text-primary-foreground dark:active:bg-primary dark:active:text-primary-foreground'
        : 'text-foreground hover:bg-muted hover:text-foreground active:bg-muted active:text-foreground dark:text-foreground dark:hover:bg-muted/70 dark:hover:text-foreground dark:active:bg-muted/70 dark:active:text-foreground';
};

const paginationButtonClasses = (active: boolean): string => {
    return active
        ? 'bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground active:bg-primary active:text-primary-foreground dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary dark:hover:text-primary-foreground dark:active:bg-primary dark:active:text-primary-foreground'
        : 'text-foreground hover:bg-muted hover:text-foreground active:bg-muted active:text-foreground dark:text-foreground dark:hover:bg-muted/70 dark:hover:text-foreground dark:active:bg-muted/70 dark:active:text-foreground';
};

const filteredUsers = computed<ManagedUser[]>(() => {
    const q = searchTerm.value.trim().toLowerCase();

    return users.value.filter((user) => {
        const matchesQuery = q
            ? user.name.toLowerCase().includes(q) || user.email.toLowerCase().includes(q)
            : true;

        const matchesFilter = statusFilter.value === 'all'
            ? true
            : statusFilter.value === 'disabled'
                ? user.deleted_at !== null
                : user.deleted_at === null;

        return matchesQuery && matchesFilter;
    });
});

const currentPage = ref(1);
const perPage = ref(10);

const totalPages = computed(() => Math.max(1, Math.ceil(filteredUsers.value.length / perPage.value)));

const pages = computed(() => {
    return Array.from({ length: totalPages.value }, (_, i) => i + 1);
});

const pagedUsers = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return filteredUsers.value.slice(start, start + perPage.value);
});

const paginationLinks = computed(() => {
    const links: { label: string; page: number | null; active: boolean }[] = [];

    // Previous button
    links.push({
        label: '&laquo; Previous',
        page: currentPage.value > 1 ? currentPage.value - 1 : null,
        active: false,
    });

    // Page number buttons
    for (let i = 1; i <= totalPages.value; i++) {
        links.push({
            label: i.toString(),
            page: i,
            active: i === currentPage.value,
        });
    }

    // Next button
    links.push({
        label: 'Next &raquo;',
        page: currentPage.value < totalPages.value ? currentPage.value + 1 : null,
        active: false,
    });

    return links;
});

watch([filteredUsers], () => {
    if (currentPage.value > totalPages.value) {
        currentPage.value = totalPages.value;
    }
});

watch([searchTerm, statusFilter], () => {
    currentPage.value = 1;
});


const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Directory',
        description: 'View and manage user accounts with access to CHED ONSE KMIS',
        href: '/user-management/directory',
    },
];

const isDisabled = (user: ManagedUser): boolean => user.deleted_at !== null;

const isLoading = ref(true);
let loadingTimeout: ReturnType<typeof setTimeout> | null = null;

const pulseLoading = (): void => {
    if (loadingTimeout) {
        clearTimeout(loadingTimeout);
    }

    isLoading.value = true;
    loadingTimeout = setTimeout(() => {
        isLoading.value = false;
        loadingTimeout = null;
    }, 250);
};

onMounted(() => {
    pulseLoading();
});

watch(statusFilter, () => {
    pulseLoading();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <UserManagementLayout>

            <Head title="User management" />

            <div class="space-y-3 mb-3">


                <!-- Search Bar -->
                <SearchInput v-model="searchTerm" placeholder="Search by Name or Email" />

                <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 px-3"
                            :class="filterButtonClasses('all')"
                            @click="statusFilter = 'all'"
                        >
                            All
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 px-3"
                            :class="filterButtonClasses('active')"
                            @click="statusFilter = 'active'"
                        >
                            Active
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 px-3"
                            :class="filterButtonClasses('disabled')"
                            @click="statusFilter = 'disabled'"
                        >
                            Disabled
                        </Button>
                    </div>

                    <div
                        v-if="!isLoading"
                        class="flex flex-col gap-1 text-right sm:flex-row sm:items-center sm:gap-3"
                    >
                        <div class="text-sm text-muted-foreground">
                            Showing {{ Math.min(filteredUsers.length, currentPage * perPage) }} of {{ filteredUsers.length }} Users
                        </div>
                        <div v-if="totalPages > 1">
                            Page {{ currentPage }} / {{ totalPages }}
                        </div>
                    </div>
                </div>
            </div>


            <!-- Table -->
            <div class="flex-1 rounded-xl border bg-card shadow-sm overflow-hidden flex-col mb-5">
                <UserManagementTableSkeleton v-if="isLoading" variant="directory" :rows="10" />

                <template v-else>
                    <Table class="w-full table-fixed">
                        <TableHeader class="bg-background sticky top-0 z-10">
                            <TableRow class="border-b">
                                <TableHead class=" w-24 px-4 py-" aria-label="Avatar" >Avatar</TableHead>
                                <TableHead class="text-left px-4 py-3">Name</TableHead>
                                <TableHead class="hidden sm:table-cell w-80 px-4 py-3">Email</TableHead>
                                <TableHead class="px-4 py-3">Status</TableHead>
                                <TableHead class="px-4 py-3">Roles</TableHead>
                                <TableHead class="text-right px-4 py-3">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                    </Table>

                    <div class="flex-1 h-[calc(100vh-20rem)] overflow-y-auto">
                        <Table class="w-full table-fixed">
                            <TableBody>
                                <TableRow v-for="user in pagedUsers" :key="user.id" :class="[
                                    'border-t border-border/60 transition hover:bg-muted/50',
                                    selectedUserId === user.id ? 'bg-muted/30' : 'bg-background',
                                    isDisabled(user) ? 'opacity-70' : '',
                                ]" @click="selectedUserId = user.id">
                                    <TableCell class=" w-24 px-4 py-3">
                                        <Avatar class="h-10 w-10 overflow-hidden rounded-full">
                                            <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                                            <AvatarFallback
                                                class=" rounded-full text-xs font-medium text-black dark:text-white"
                                            >
                                                {{ getInitials(user.name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                    </TableCell>
                                    <TableCell class="px-4 py-3">
                                        <p class="font-medium block truncate">{{ user.name }}</p>
                                        <p class="text-xs text-muted-foreground sm:hidden block truncate ">{{ user.email }}</p>
                                    </TableCell>
                                    <TableCell class="hidden w-80 px-4 py-3 text-sm text-muted-foreground sm:table-cell truncate block">
                                        {{ user.email }}
                                    </TableCell>
                                    <TableCell class="px-4 py-3">
                                        <Badge
                                            v-if="isDisabled(user)"
                                            variant="outline"
                                            class="border-destructive/50 bg-destructive/10 text-destructive"
                                        >
                                            Disabled
                                        </Badge>
                                        <Badge
                                            v-else
                                            variant="outline"
                                            :class="user.status === 'active'
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-400'
                                                : 'border-muted-foreground/40 bg-muted/50 text-muted-foreground'"
                                        >
                                            {{ user.status === 'active' ? 'Active' : 'Inactive' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <Badge v-for="role in user.roles" :key="role.id" variant="outline"
                                                class="text-xs" :class="roleBadgeClasses(role.slug)">
                                                {{ role.name }}
                                            </Badge>
                                            <Badge v-if="user.roles.length === 0" variant="outline"
                                                class="border-dashed border-muted-foreground/40 text-muted-foreground">
                                                Viewer baseline
                                            </Badge>
                                        </div>
                                    </TableCell>
                                    <TableCell class="px-2 py-3 text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child @click.stop>
                                                <Button variant="ghost" size="icon" class="h-8 w-8">
                                                    <MoreVertical class="h-4 w-4" />
                                                    <span class="sr-only">Account actions</span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-48">
                                                <DropdownMenuItem v-if="!isDisabled(user)" @click.stop="openEditUserDialog(user)">
                                                    <Pencil class="mr-2 h-4 w-4" />
                                                    <span>Edit info</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click.stop="openUserAccessDialog(user)">
                                                    <ShieldCheck class="mr-2 h-4 w-4" />
                                                    <span>Review access</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="isDisabled(user) && canDeleteUsers"
                                                    @click.stop="handleRestoreUser(user)"
                                                >
                                                    <RotateCcw class="mr-2 h-4 w-4" />
                                                    <span>Restore user</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-else-if="canDeleteUsers && user.id !== currentUserId"
                                                    @click.stop="handleDeleteUser(user)"
                                                    class="text-destructive focus:bg-destructive/10 focus:text-destructive"
                                                >
                                                    <Trash2 class="mr-2 h-4 w-4" />
                                                    <span>Disable user</span>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>

                                <TableEmpty v-if="filteredUsers.length === 0">
                                    <div class="px-4 py-5 text-center text-sm text-muted-foreground w-full">
                                        <template v-if="users.length === 0">No accounts found yet.</template>
                                        <template v-else-if="statusFilter === 'disabled'">No Disabled Accounts.</template>
                                        <template v-else-if="statusFilter === 'active'">No Active Accounts.</template>
                                        <template v-else>No matching accounts.</template>
                                    </div>
                                </TableEmpty>
                            </TableBody>
                        </Table>
                    </div>
                </template>

            </div>

            <!-- Pagination -->
            <div v-if="paginationLinks.length" class="flex flex-wrap items-center gap-1.5">
                <Button
                    v-for="(link, index) in paginationLinks"
                    :key="index"
                    :disabled="!link.page"
                    variant="outline"
                    size="sm"
                    class="h-8 min-w-8 px-2 text-xs"
                    :class="paginationButtonClasses(link.active)"
                    @click="link.page && (currentPage = link.page)"
                >
                    <span v-html="link.label" />
                </Button>
            </div>


            <Dialog v-model:open="editUserDialogOpen">
                <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Edit user</DialogTitle>
                        <DialogDescription>
                            Update core profile details for the selected user.
                        </DialogDescription>
                    </DialogHeader>

                    <template v-if="selectedUser">
                        <Form
                            :key="`edit-${selectedUser.id}`"
                            v-bind="DirectoryController.updateUser.form({ user: selectedUser.id.toString() })"
                            @submit.capture="onEditUserSubmit"
                            novalidate
                            class="space-y-4"
                            v-slot="{ errors, processing, recentlySuccessful }"
                        >
                            <div class="space-y-2">
                                <PersonNameRow
                                    v-model:prefix="editName.prefix"
                                    v-model:first="editName.first"
                                    v-model:middle="editName.middle"
                                    v-model:last="editName.last"
                                    v-model:extension="editName.extension"
                                    :prefix-error="errors.prefix"
                                    :first-error="editClientErrors.first_name ?? errors.first_name"
                                    :middle-error="errors.middle_name"
                                    :last-error="editClientErrors.last_name ?? errors.last_name"
                                    :extension-error="errors.extension"
                                    prefix-placeholder="Select"
                                    first-placeholder="e.g. Jamie"
                                    middle-placeholder="e.g. Santos"
                                    last-placeholder="e.g. Reyes"
                                    extension-placeholder="e.g. Jr., III"
                                    @blur:first="() => applyEditNameTitleCase('first')"
                                    @blur:middle="() => applyEditNameTitleCase('middle')"
                                    @blur:last="() => applyEditNameTitleCase('last')"
                                />
                                <InputError :message="errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-email">Email address <span class="text-destructive">*</span></Label>
                                <Input
                                    id="edit-email"
                                    type="email"
                                    name="email"
                                    required
                                    :model-value="selectedUser.email"
                                    autocomplete="email"
                                    :class="(editClientErrors.email ?? errors.email) ? 'border-destructive focus-visible:border-destructive focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40' : ''"
                                    :aria-invalid="(editClientErrors.email ?? errors.email) ? 'true' : 'false'"
                                    :aria-describedby="(editClientErrors.email ?? errors.email) ? 'edit-email-error' : undefined"
                                />
                                <InputError id="edit-email-error" :message="editClientErrors.email ?? errors.email" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-status">Status</Label>
                                <select id="edit-status" name="status" :value="selectedUser.status" :class="selectFieldClasses">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <InputError :message="errors.status" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-department">Department</Label>
                                <Combobox v-model:open="editDepartmentOpen" v-model:searchTerm="editDepartmentSearchTerm">
                                    <ComboboxAnchor class="w-full">
                                        <ComboboxTrigger class="w-full cursor-pointer">
                                            <span :class="editDepartmentId ? 'text-foreground' : 'text-muted-foreground'">
                                                {{ editDepartmentDisplay }}
                                            </span>
                                            <ChevronsUpDownIcon class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </ComboboxTrigger>
                                    </ComboboxAnchor>

                                    <ComboboxContent class="w-[--reka-combobox-trigger-width] p-0">
                                        <div class="flex items-center border-b px-3">
                                            <Search class="h-4 w-4 text-gray-500" />
                                            <ComboboxInput
                                                id="edit-department"
                                                placeholder="Search department..."
                                                class="flex h-9 w-full rounded-none border-0 bg-transparent py-3 text-sm shadow-none outline-none placeholder:text-muted-foreground focus-visible:ring-0"
                                            />
                                        </div>

                                        <div class="max-h-64 overflow-y-auto p-1">
                                            <ComboboxItem :value="noLookupSelectionValue" class="cursor-pointer" @select.prevent="selectEditDepartment(null)">
                                                <Check v-if="editDepartmentId === ''" class="mr-2 h-4 w-4 text-primary" />
                                                <span class="truncate">No department</span>
                                            </ComboboxItem>

                                            <ComboboxItem
                                                v-for="department in filteredDepartments"
                                                :key="department.id"
                                                :value="String(department.id)"
                                                class="cursor-pointer"
                                                @select.prevent="selectEditDepartment(department.id)"
                                            >
                                                <Check v-if="editDepartmentId === String(department.id)" class="mr-2 h-4 w-4 text-primary" />
                                                <span class="truncate">{{ lookupLabel(department) }}</span>
                                            </ComboboxItem>

                                            <ComboboxEmpty class="py-6 text-center text-sm text-muted-foreground">
                                                No departments found
                                            </ComboboxEmpty>
                                        </div>
                                    </ComboboxContent>
                                </Combobox>
                                <input type="hidden" name="department_id" :value="editDepartmentId" />
                                <InputError :message="errors.department_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-unit">Unit</Label>
                                <Combobox v-model:open="editUnitOpen" v-model:searchTerm="editUnitSearchTerm">
                                    <ComboboxAnchor class="w-full">
                                        <ComboboxTrigger class="w-full cursor-pointer">
                                            <span :class="editUnitId ? 'text-foreground' : 'text-muted-foreground'">
                                                {{ editUnitDisplay }}
                                            </span>
                                            <ChevronsUpDownIcon class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </ComboboxTrigger>
                                    </ComboboxAnchor>

                                    <ComboboxContent class="w-[--reka-combobox-trigger-width] p-0">
                                        <div class="flex items-center border-b px-3">
                                            <Search class="h-4 w-4 text-gray-500" />
                                            <ComboboxInput
                                                id="edit-unit"
                                                placeholder="Search unit..."
                                                class="flex h-9 w-full rounded-none border-0 bg-transparent py-3 text-sm shadow-none outline-none placeholder:text-muted-foreground focus-visible:ring-0"
                                            />
                                        </div>

                                        <div class="max-h-64 overflow-y-auto p-1">
                                            <ComboboxItem :value="noLookupSelectionValue" class="cursor-pointer" @select.prevent="selectEditUnit(null)">
                                                <Check v-if="editUnitId === ''" class="mr-2 h-4 w-4 text-primary" />
                                                <span class="truncate">No unit</span>
                                            </ComboboxItem>

                                            <ComboboxItem
                                                v-for="unit in filteredUnits"
                                                :key="unit.id"
                                                :value="String(unit.id)"
                                                class="cursor-pointer"
                                                @select.prevent="selectEditUnit(unit.id)"
                                            >
                                                <Check v-if="editUnitId === String(unit.id)" class="mr-2 h-4 w-4 text-primary" />
                                                <span class="truncate">{{ lookupLabel(unit) }}</span>
                                            </ComboboxItem>

                                            <ComboboxEmpty class="py-6 text-center text-sm text-muted-foreground">
                                                No units found
                                            </ComboboxEmpty>
                                        </div>
                                    </ComboboxContent>
                                </Combobox>
                                <input type="hidden" name="unit_id" :value="editUnitId" />
                                <InputError :message="errors.unit_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit-position">Position</Label>
                                <Combobox v-model:open="editPositionOpen" v-model:searchTerm="editPositionSearchTerm">
                                    <ComboboxAnchor class="w-full">
                                        <ComboboxTrigger class="w-full cursor-pointer">
                                            <span :class="editPositionId ? 'text-foreground' : 'text-muted-foreground'">
                                                {{ editPositionDisplay }}
                                            </span>
                                            <ChevronsUpDownIcon class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </ComboboxTrigger>
                                    </ComboboxAnchor>

                                    <ComboboxContent class="w-[--reka-combobox-trigger-width] p-0">
                                        <div class="flex items-center border-b px-3">
                                            <Search class="h-4 w-4 text-gray-500" />
                                            <ComboboxInput
                                                id="edit-position"
                                                placeholder="Search position..."
                                                class="flex h-9 w-full rounded-none border-0 bg-transparent py-3 text-sm shadow-none outline-none placeholder:text-muted-foreground focus-visible:ring-0"
                                            />
                                        </div>

                                        <div class="max-h-64 overflow-y-auto p-1">
                                            <ComboboxItem :value="noLookupSelectionValue" class="cursor-pointer" @select.prevent="selectEditPosition(null)">
                                                <Check v-if="editPositionId === ''" class="mr-2 h-4 w-4 text-primary" />
                                                <span class="truncate">No position</span>
                                            </ComboboxItem>

                                            <ComboboxItem
                                                v-for="position in filteredPositions"
                                                :key="position.id"
                                                :value="String(position.id)"
                                                class="cursor-pointer"
                                                @select.prevent="selectEditPosition(position.id)"
                                            >
                                                <Check v-if="editPositionId === String(position.id)" class="mr-2 h-4 w-4 text-primary" />
                                                <span class="truncate">{{ lookupLabel(position) }}</span>
                                            </ComboboxItem>

                                            <ComboboxEmpty class="py-6 text-center text-sm text-muted-foreground">
                                                No positions found
                                            </ComboboxEmpty>
                                        </div>
                                    </ComboboxContent>
                                </Combobox>
                                <input type="hidden" name="position_id" :value="editPositionId" />
                                <InputError :message="errors.position_id" />
                            </div>

                            <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-3">
                                <Button :disabled="processing">Save changes</Button>
                                <p v-if="recentlySuccessful" class="text-xs font-semibold uppercase tracking-[0.4em] text-emerald-600">
                                    Saved
                                </p>
                            </div>
                        </Form>
                    </template>

                    <DialogFooter />
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="manageUserDialogOpen">
                <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Adjust CHED ONSE KMIS access</DialogTitle>
                        <DialogDescription>
                            Role updates apply immediately for the selected staff account.
                        </DialogDescription>
                    </DialogHeader>

                    <template v-if="selectedUser">
                        <div class="rounded-xl border border-border/60 bg-muted/10 px-3 py-2.5 text-sm sm:px-4 sm:py-3">
                            <p class="font-semibold text-foreground">{{ selectedUser.name }}</p>
                            <p class="text-muted-foreground">{{ selectedUser.email }}</p>
                        </div>

                        <Form :key="selectedUser.id"
                            v-bind="DirectoryController.updateUserRoles['/user-management/users/{user}/roles'].form({ user: selectedUser.id.toString() })"
                            class="space-y-4" @submit.capture="onUpdateUserRolesSubmit"
                            v-slot="{ errors, processing, recentlySuccessful }">
                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                <Label v-for="role in roles" :key="`user-${selectedUser.id}-role-${role.id}`"
                                    class="flex cursor-pointer items-center space-x-3 rounded-xl border border-border/60 px-3 py-2 text-sm">
                                    <Checkbox name="role_ids[]" :value="role.id"
                                        :default-value="selectedUser.roles.some((assigned) => assigned.id === role.id)" />
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium">{{ role.name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ role.description || 'Custom role' }}
                                        </p>
                                    </div>
                                </Label>
                            </div>

                            <InputError :message="errors.role_ids" />

                            <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-3">
                                <Button :disabled="processing">Update roles</Button>
                                <p v-if="recentlySuccessful"
                                    class="text-xs font-semibold uppercase tracking-[0.4em] text-emerald-600">
                                    Saved
                                </p>
                            </div>
                        </Form>
                    </template>

                    <DialogFooter />
                </DialogContent>
            </Dialog>
        </UserManagementLayout>
    </AppLayout>
</template>
