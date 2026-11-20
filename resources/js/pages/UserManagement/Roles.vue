<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { MoreVertical, RotateCcw, Shield, Trash2 } from 'lucide-vue-next';
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
import { computed, onMounted, ref, toRefs, watch } from 'vue';

import RoleController from '@/actions/App/Http/Controllers/UserManagement/RoleController';
import InputError from '@/components/common/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import UserManagementLayout from '@/layouts/user-management/Layout.vue';
import { BreadcrumbItem } from '@/types';
import Input from '@/components/ui/input/Input.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import UserManagementTableSkeleton from '@/components/user-management/UserManagementTableSkeleton.vue';

type ManagedPermission = {
	id: number;
	name: string;
	slug: string;
	description?: string | null;
};

type ManagedRole = {
	id: number;
	name: string;
	slug: string;
	description?: string | null;
	is_system: boolean;
	users_count: number;
	deleted_at: string | null;
	permissions: ManagedPermission[];
};

const props = defineProps<{
	roles: ManagedRole[];
	permissions: ManagedPermission[];
}>();

const ORGANIZE_CONTENT_PERMISSION = 'organize-content';
const ORGANIZE_CONTENT_DEPENDENT_PERMISSIONS = [
	'review-content',
	'delete-content',
] as const;

const { roles, permissions } = toRefs(props);

const selectedRoleId = ref<number | null>(null);
const manageRoleDialogOpen = ref(false);
const createRoleOpen = ref(false);
const createOrganizeContentChecked = ref(false);
const editOrganizeContentChecked = ref(false);

const baseRolePermissions = computed<ManagedPermission[]>(() => {
	return permissions.value.filter((permission) => {
		return !ORGANIZE_CONTENT_DEPENDENT_PERMISSIONS.includes(permission.slug as (typeof ORGANIZE_CONTENT_DEPENDENT_PERMISSIONS)[number]);
	});
});

const organizeDependentPermissions = computed<ManagedPermission[]>(() => {
	return permissions.value.filter((permission) => {
		return ORGANIZE_CONTENT_DEPENDENT_PERMISSIONS.includes(permission.slug as (typeof ORGANIZE_CONTENT_DEPENDENT_PERMISSIONS)[number]);
	});
});

const selectedRole = computed(() => {
	if (selectedRoleId.value === null) {
		return null;
	}

	return roles.value.find((role) => role.id === selectedRoleId.value) ?? null;
});

const roleHasOrganizeContentPermission = (role: ManagedRole | null): boolean => {
	if (!role) {
		return false;
	}

	return role.permissions.some((permission) => permission.slug === ORGANIZE_CONTENT_PERMISSION);
};

watch(roles, () => {
	if (selectedRoleId.value !== null && !roles.value.some((role) => role.id === selectedRoleId.value)) {
		selectedRoleId.value = null;
		manageRoleDialogOpen.value = false;
	}
});

watch(createRoleOpen, (isOpen) => {
	if (!isOpen) {
		return;
	}

	createOrganizeContentChecked.value = false;
});

watch(selectedRole, (role) => {
	if (!role) {
		editOrganizeContentChecked.value = false;
		return;
	}

	editOrganizeContentChecked.value = roleHasOrganizeContentPermission(role);
}, { immediate: true });

type ConfirmActionKey =
	| 'open-role-dialog'
	| 'create-role'
	| 'update-role'
	| 'delete-role'
	| 'restore-role';

const confirmMessages: Record<ConfirmActionKey, { title: string; description: string; confirmText?: string; cancelText?: string }> = {
	'open-role-dialog': {
		title: 'Edit Role Template',
		description: 'Confirm you want to edit the permissions bundled with this role.',
		confirmText: 'Edit Template',
		cancelText: 'Go Back',
	},
	'create-role': {
		title: 'Create New Role Template',
		description: 'This defines a reusable persona for future adjustments. Proceed?',
		confirmText: 'Create Role',
		cancelText: 'Cancel',
	},
	'update-role': {
		title: 'Update Role Permissions',
		description: 'All users assigned to this role inherit the new permission bundle.',
		confirmText: 'Update Role',
		cancelText: 'Cancel',
	},
	'delete-role': {
		title: 'Delete Role Template',
		description: 'This role will be disabled and can be restored later.',
		confirmText: 'Delete Role',
		cancelText: 'Cancel',
	},
	'restore-role': {
		title: 'Restore Role Template',
		description: 'This role will be active again and available for assignment.',
		confirmText: 'Restore Role',
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

const onUpdateRoleSubmit = async (event: SubmitEvent): Promise<void> => {
	await confirmBeforeSubmit(event, 'update-role');
};

const onCreateRoleSubmit = async (event: SubmitEvent): Promise<void> => {
	await confirmBeforeSubmit(event, 'create-role');
};

const openRolePermissionsDialog = async (role: ManagedRole): Promise<void> => {
	if (role.deleted_at) {
		return;
	}

	const decision = await confirm(confirmMessages['open-role-dialog']);

	if (decision !== 'confirm') {
		return;
	}

	selectedRoleId.value = role.id;
	editOrganizeContentChecked.value = roleHasOrganizeContentPermission(role);
	manageRoleDialogOpen.value = true;
};

const updateOrganizeContentChecked = (checked: boolean | 'indeterminate'): void => {
	editOrganizeContentChecked.value = checked === true;
};

const updateCreateOrganizeContentChecked = (checked: boolean | 'indeterminate'): void => {
	createOrganizeContentChecked.value = checked === true;
};

const isRoleDeleted = (role: ManagedRole): boolean => role.deleted_at !== null;

const canDeleteRole = (role: ManagedRole): boolean => {
	return !isRoleDeleted(role) && role.users_count === 0;
};

const deleteRoleHint = (role: ManagedRole): string | null => {
	if (role.users_count > 0) {
		return 'Role is assigned to users';
	}

	return null;
};

const deleteRole = async (role: ManagedRole): Promise<void> => {
	if (!canDeleteRole(role)) {
		return;
	}

	const decision = await confirm(confirmMessages['delete-role']);

	if (decision !== 'confirm') {
		return;
	}

	router.delete(RoleController.destroyRole.url({ role: role.id }));
};

const restoreRole = async (role: ManagedRole): Promise<void> => {
	if (!isRoleDeleted(role)) {
		return;
	}

	const decision = await confirm(confirmMessages['restore-role']);

	if (decision !== 'confirm') {
		return;
	}

	router.post(RoleController.restoreRole.url({ role: role.id }));
};

const roleBadgePalette: Record<string, string> = {
	admin: 'border-amber-200 bg-amber-50 text-amber-700',
	editor: 'border-indigo-200 bg-indigo-50 text-indigo-700',
	reviewer: 'border-emerald-200 bg-emerald-50 text-emerald-700',
	contributor: 'border-sky-200 bg-sky-50 text-sky-700',
	requestor: 'border-slate-200 bg-slate-50 text-slate-600',
	'regional-director': 'border-purple-200 bg-purple-50 text-purple-700',
	'records-officer': 'border-orange-200 bg-orange-50 text-orange-700',
};

const permissionBadgePalette: Record<string, string> = {
	'user-management': 'border-rose-200 bg-rose-50 text-rose-700',
	'disposal-management': 'border-amber-200 bg-amber-50 text-amber-700',
	'approve-reject-content': 'border-indigo-200 bg-indigo-50 text-indigo-700',
	'upload-submit-reports': 'border-emerald-200 bg-emerald-50 text-emerald-700',
	'view-download': 'border-slate-200 bg-slate-50 text-slate-600',
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

const badgeTone = (slug: string, palette: Record<string, string>): string => {
	if (palette[slug]) {
		return palette[slug];
	}

	return fallbackBadgeClasses[toneIndex(slug) % fallbackBadgeClasses.length];
};

const roleBadgeClasses = (slug: string): string => badgeTone(slug, roleBadgePalette);
const permissionBadgeClasses = (slug: string): string => badgeTone(slug, permissionBadgePalette);

const totalRoles = computed(() => roles.value.length);

const searchTerm = ref('');

const filteredRoles = computed<ManagedRole[]>(() => {
	const q = searchTerm.value.trim().toLowerCase();

	if (!q) {
		return roles.value;
	}

	return roles.value.filter((role) => {
		return (
			role.name.toLowerCase().includes(q) ||
			role.slug.toLowerCase().includes(q) ||
			(role.description && role.description.toLowerCase().includes(q))
		);
	});
});

const currentPage = ref(1);
const perPage = ref(10);

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRoles.value.length / perPage.value)));

const pages = computed(() => {
	return Array.from({ length: totalPages.value }, (_, i) => i + 1);
});

const pagedRoles = computed(() => {
	const start = (currentPage.value - 1) * perPage.value;
	return filteredRoles.value.slice(start, start + perPage.value);
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

watch([filteredRoles], () => {
	if (currentPage.value > totalPages.value) {
		currentPage.value = totalPages.value;
	}
});

const paginationButtonClasses = (active: boolean): string => {
	return active
		? 'bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground active:bg-primary active:text-primary-foreground dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary dark:hover:text-primary-foreground dark:active:bg-primary dark:active:text-primary-foreground'
		: 'text-foreground hover:bg-muted hover:text-foreground active:bg-muted active:text-foreground dark:text-foreground dark:hover:bg-muted/70 dark:hover:text-foreground dark:active:bg-muted/70 dark:active:text-foreground';
};

const breadcrumbItems: BreadcrumbItem[] = [
	{
		title: 'Roles',
		description: 'Create and manage role templates that bundle permissions for easy assignment to staff accounts.',
		href: '/user-management/roles',
	},
];

const isLoading = ref(true);

onMounted(() => {
    setTimeout(() => {
        isLoading.value = false;
    }, 300);
});
</script>

<template>
	<AppLayout :breadcrumbs="breadcrumbItems">
		<UserManagementLayout>

			<Head title="User management" />

			<div class="space-y-3 mb-3">
				<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
					<Button class="w-full ml-auto sm:w-auto" @click="createRoleOpen = true">
						New Role Template
					</Button>
				</div>

				<!-- Search Bar -->
				<SearchInput v-model="searchTerm" placeholder="Search Roles" />
				<div
					v-if="!isLoading"
					class="flex items-center justify-between text-sm text-muted-foreground"
				>
					<div class="text-sm text-muted-foreground">Showing {{ Math.min(filteredRoles.length, currentPage *
						perPage) }} of {{ filteredRoles.length }} Roles</div>
					<div v-if="totalPages > 1">
						Page {{ currentPage }} / {{ totalPages }}
					</div>
				</div>
			</div>

			<!-- Table -->
            <div class="flex-1 rounded-xl border bg-card shadow-sm overflow-hidden flex-col mb-5">
				<UserManagementTableSkeleton v-if="isLoading" variant="roles" :rows="10" />
			<template v-else>
			<Table class="w-full table-fixed">
				<TableHeader class="bg-background sticky top-0 z-10">
					<TableRow class="border-b">
						<TableHead class="text-left px-4 py-3 w-40">Role</TableHead>
						<TableHead class=" py-3 text-center w-10">Users</TableHead>
						<TableHead class="px-4 py-3 w-120">Permissions</TableHead>
						<TableHead class="text-right px-4 py-3 w-15">Actions</TableHead>
					</TableRow>
				</TableHeader>
			</Table>
			<div class="h-[calc(100vh-22rem)] overflow-y-auto">
                    <Table class="w-full table-fixed">
					<TableBody>
						<TableRow v-for="role in pagedRoles" :key="role.id" :class="[
							'border-t border-border/60 transition hover:bg-muted/50',
								selectedRoleId === role.id ? 'bg-muted/30' : 'bg-background',
								isRoleDeleted(role) ? 'opacity-70' : '',
						]" @click="selectedRoleId = role.id">
						<TableCell class="px-4 py-3 align-top w-40">
							<div class="flex flex-wrap items-center gap-2">
								<p class="font-medium">{{ role.name }}</p>
								<Badge v-if="role.is_system" variant="secondary"
									class="text-[0.65rem] uppercase tracking-wide">
									System
								</Badge>
									<Badge v-if="isRoleDeleted(role)" variant="outline"
										class="border-destructive/40 text-destructive">
										Deleted
									</Badge>
							</div>
							<p class=" text-wrap text-xs text-muted-foreground ">
								{{ role.description || 'No Description Provided.' }}
							</p>
						</TableCell>
						<TableCell class="w-10 py-3 text-center font-semibold align-top">
							{{ role.users_count }}
						</TableCell>
						<TableCell class="px-4 py-3 w-120 align-top">
							<div class="flex flex-wrap gap-2">
								<Badge v-for="permission in role.permissions" :key="permission.id"
									variant="outline" class="text-xs"
									:class="permissionBadgeClasses(permission.slug)">
									{{ permission.name }}
								</Badge>
								<Badge v-if="role.permissions.length === 0" variant="outline"
									class="border-dashed border-muted-foreground/40 text-muted-foreground text-xs">
									No Permissions
								</Badge>
							</div>
						</TableCell>
						<TableCell class="px-4 py-3 text-right w-15">
							<DropdownMenu>
								<DropdownMenuTrigger as-child @click.stop>
									<Button variant="ghost" size="icon" class="h-8 w-8">
										<MoreVertical class="h-4 w-4" />
										<span class="sr-only">Role Actions</span>
									</Button>
								</DropdownMenuTrigger>
								<DropdownMenuContent align="end" class="w-48">
									<DropdownMenuItem v-if="isRoleDeleted(role)" @click.stop="restoreRole(role)">
										<RotateCcw class="mr-2 h-4 w-4" />
										<span>Restore Role</span>
									</DropdownMenuItem>
									<template v-else>
										<DropdownMenuItem @click.stop="openRolePermissionsDialog(role)">
										<Shield class="mr-2 h-4 w-4" />
										<span>Edit Permissions</span>
									</DropdownMenuItem>
										<DropdownMenuItem @click.stop="deleteRole(role)" :disabled="!canDeleteRole(role)"
											class="text-destructive focus:bg-destructive/10">
											<Trash2 class="mr-2 h-4 w-4" />
											<span>{{ deleteRoleHint(role) ?? 'Delete Role' }}</span>
										</DropdownMenuItem>
									</template>
								</DropdownMenuContent>
							</DropdownMenu>
						</TableCell>
					</TableRow>

					<TableEmpty v-if="filteredRoles.length === 0">
						<div class="px-4 py-5 text-center text-sm text-muted-foreground w-full">
							<template v-if="roles.length === 0">No roles have been created yet.</template>
							<template v-else>No matching roles.</template>
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

			<Dialog v-model:open="manageRoleDialogOpen">
				<DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
					<DialogHeader>
						<DialogTitle>Edit Role Permissions</DialogTitle>
						<DialogDescription>
							Changes cascade to every staff member using this template.
						</DialogDescription>
					</DialogHeader>

					<template v-if="selectedRole">
						<div class="rounded-xl border border-border/60 bg-muted/10 px-3 py-2.5 text-sm sm:px-4 sm:py-3">
							<p class="font-semibold text-foreground">{{ selectedRole.name }}</p>
							<p class="text-muted-foreground">
								{{ selectedRole.description || 'No Description Provided.' }}
							</p>
						</div>

						<Form :key="selectedRole.id" v-bind="RoleController.updateRole.form({ role: selectedRole.id })"
							class="space-y-4" @submit.capture="onUpdateRoleSubmit"
							v-slot="{ errors, processing, recentlySuccessful }">
							<div class="space-y-2 w-full">
								<Label :for="`role-name-${selectedRole.id}`">Role Name</Label>
								<Input :id="`role-name-${selectedRole.id}`" name="name" required
									:model-value="selectedRole.name" />
								<InputError :message="errors.name" />
							</div>

							<div class="space-y-2">
								<Label :for="`role-description-${selectedRole.id}`">Description</Label>
								<Textarea :id="`role-description-${selectedRole.id}`" name="description" rows="3"
									:model-value="selectedRole.description ?? ''" />
								<InputError :message="errors.description" />
							</div>

							<div class="grid grid-cols-1 gap-2 md:grid-cols-2">
								<div v-for="permission in baseRolePermissions"
									:key="`role-${selectedRole.id}-permission-${permission.id}`"
									class="space-y-2">
									<Label
										class="flex cursor-pointer items-start space-x-3 rounded-xl border border-border/60 px-3 py-2 text-sm">
										<Checkbox v-if="permission.slug === ORGANIZE_CONTENT_PERMISSION"
											name="permission_slugs[]"
											:value="permission.slug"
											:model-value="editOrganizeContentChecked"
											@update:model-value="updateOrganizeContentChecked" />
										<Checkbox v-else name="permission_slugs[]" :value="permission.slug"
											:default-value="selectedRole.permissions.some((assigned) => assigned.slug === permission.slug)" />
										<div class="min-w-0 flex-1">
											<p class="font-medium">{{ permission.name }}</p>
											<p class="text-xs text-muted-foreground">
												{{ permission.description }}
											</p>
										</div>
									</Label>

									<div v-if="permission.slug === ORGANIZE_CONTENT_PERMISSION && editOrganizeContentChecked"
										class="ml-6 space-y-2 border-l border-border/60 pl-3">
										<Label v-for="dependentPermission in organizeDependentPermissions"
											:key="`role-${selectedRole.id}-permission-${dependentPermission.id}`"
											class="flex cursor-pointer items-start space-x-3 rounded-xl border border-border/60 px-3 py-2 text-sm">
											<Checkbox name="permission_slugs[]" :value="dependentPermission.slug"
												:default-value="selectedRole.permissions.some((assigned) => assigned.slug === dependentPermission.slug)" />
											<div class="min-w-0 flex-1">
												<p class="font-medium">{{ dependentPermission.name }}</p>
												<p class="text-xs text-muted-foreground">
													{{ dependentPermission.description }}
												</p>
											</div>
										</Label>
									</div>
								</div>
							</div>
							<InputError :message="errors.permission_slugs" />

							<div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-3">
								<Button variant="outline" :disabled="processing">Update role</Button>
								<p v-if="recentlySuccessful"
									class="text-xs font-semibold uppercase tracking-[0.4em] text-emerald-600">
									Saved
								</p>
							</div>
						</Form>
					</template>
				</DialogContent>
			</Dialog>

			<Dialog v-model:open="createRoleOpen">
				<DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
					<DialogHeader>
						<DialogTitle>New Role Template</DialogTitle>
					</DialogHeader>

					<Form v-bind="RoleController.storeRole.form()" reset-on-success @success="createRoleOpen = false"
						@submit.capture="onCreateRoleSubmit" class="space-y-4" v-slot="{ errors, processing }">
						<div class="space-y-2 w-full">
							<Label for="role_name">Role Name</Label>
							<Input id="role_name" name="name" required
								placeholder="Innovation Lead" />
							<InputError :message="errors.name" />
						</div>

						<div class="space-y-2">
							<Label for="role_description">Description</Label>
							<Textarea id="role_description" name="description" rows="3"
								placeholder="Explain when this role should be used" />
							<InputError :message="errors.description" />
						</div>

						<div class="space-y-2">
							<Label>Activate permissions</Label>
							<div class="grid grid-cols-1 gap-2 md:grid-cols-2">
								<div v-for="permission in baseRolePermissions" :key="permission.id" class="space-y-2">
									<Label class="flex cursor-pointer items-start space-x-3 rounded-xl border border-border/60 px-3 py-2 text-sm">
										<Checkbox v-if="permission.slug === ORGANIZE_CONTENT_PERMISSION"
											name="permission_slugs[]"
											:value="permission.slug"
											:model-value="createOrganizeContentChecked"
											@update:model-value="updateCreateOrganizeContentChecked" />
										<Checkbox v-else name="permission_slugs[]" :value="permission.slug" />
										<div class="min-w-0 flex-1">
											<p class="font-medium">{{ permission.name }}</p>
											<p class="text-xs text-muted-foreground">
												{{ permission.description }}
											</p>
										</div>
									</Label>

									<div v-if="permission.slug === ORGANIZE_CONTENT_PERMISSION && createOrganizeContentChecked"
										class="ml-6 space-y-2 border-l border-border/60 pl-3">
										<Label v-for="dependentPermission in organizeDependentPermissions"
											:key="dependentPermission.id"
											class="flex cursor-pointer items-start space-x-3 rounded-xl border border-border/60 px-3 py-2 text-sm">
											<Checkbox name="permission_slugs[]" :value="dependentPermission.slug" />
											<div class="min-w-0 flex-1">
												<p class="font-medium">{{ dependentPermission.name }}</p>
												<p class="text-xs text-muted-foreground">
													{{ dependentPermission.description }}
												</p>
											</div>
										</Label>
									</div>
								</div>
							</div>
							<InputError :message="errors.permission_slugs" />
						</div>

						<DialogFooter class="flex-col gap-2 sm:flex-row">
							<Button type="button" variant="outline" @click="createRoleOpen = false">
								Cancel
							</Button>
							<Button type="submit" :disabled="processing">
								Create role
							</Button>
						</DialogFooter>
					</Form>
				</DialogContent>
			</Dialog>
		</UserManagementLayout>
	</AppLayout>
</template>
