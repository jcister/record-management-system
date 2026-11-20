<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

import UserManagementController from '@/actions/App/Http/Controllers/UserManagement/UserManagementController';
import InputError from '@/components/common/InputError.vue';
import PersonNameRow from '@/components/common/PersonNameRow.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DivisionSelectSheet from '@/components/user-management/DivisionSelectSheet.vue';
import PositionSelectSheet from '@/components/user-management/PositionSelectSheet.vue';
import RolesSelectSheet from '@/components/user-management/RolesSelectSheet.vue';
import UnitSelectSheet from '@/components/user-management/UnitSelectSheet.vue';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import UserManagementLayout from '@/layouts/user-management/Layout.vue';
import { BreadcrumbItem } from '@/types';

type ConfirmActionKey = 'create-user';

const confirmMessages: Record<ConfirmActionKey, { title: string; description: string; confirmText?: string; cancelText?: string }> = {
	'create-user': {
		title: 'Create User Account',
		description: 'An account will be created and the user will receive an email with a temporary password and login link.',
		confirmText: 'Create and Send Email',
		cancelText: 'Cancel',
	},
};

const { confirm } = useConfirm();
const bypassAction = ref<ConfirmActionKey | null>(null);

const props = defineProps<{
	departments: Array<{ id: number; name: string; deleted_at?: string | null }>;
	units: Array<{ id: number; name: string; deleted_at?: string | null }>;
	positions: Array<{ id: number; name: string; deleted_at?: string | null }>;
	roles: Array<{ id: number; name: string; slug?: string }>;
}>();

const selectedDepartment = ref<number | null>(null);
const selectedUnit = ref<number | null>(null);
const selectedPosition = ref<number | null>(null);
const selectedRoles = ref<number[]>([]);
const createName = reactive({
	prefix: '',
	first: '',
	middle: '',
	last: '',
	extension: '',
});
const createClientErrors = reactive<{
	first_name?: string;
	last_name?: string;
	email?: string;
}>({});

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

const applyCreateNameTitleCase = (field: 'first' | 'middle' | 'last'): void => {
	createName[field] = toSmartTitleCase(createName[field] || '');
};

const clearCreateClientErrors = (): void => {
	createClientErrors.first_name = undefined;
	createClientErrors.last_name = undefined;
	createClientErrors.email = undefined;
};

const focusFirstInvalidField = (form: HTMLFormElement, fieldName: string): void => {
	requestAnimationFrame(() => {
		const field = form.querySelector<HTMLElement>(`[name="${fieldName}"]`);
		field?.focus();
	});
};

const validateCreateForm = (form: HTMLFormElement): boolean => {
	clearCreateClientErrors();

	let firstInvalidField: string | null = null;
	const firstName = createName.first.trim();
	const lastName = createName.last.trim();
	const email = String(new FormData(form).get('email') ?? '').trim();

	if (firstName === '') {
		createClientErrors.first_name = 'Please enter the first name.';
		firstInvalidField ??= 'first_name';
	}

	if (lastName === '') {
		createClientErrors.last_name = 'Please enter the last name.';
		firstInvalidField ??= 'last_name';
	}

	if (email === '') {
		createClientErrors.email = 'Please enter the email address.';
		firstInvalidField ??= 'email';
	}

	if (firstInvalidField) {
		focusFirstInvalidField(form, firstInvalidField);
		return false;
	}

	return true;
};

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

const onCreateUserSubmit = async (event: SubmitEvent): Promise<void> => {
	const form = event.target as HTMLFormElement | null;

	if (!form) {
		return;
	}

	if (!validateCreateForm(form)) {
		event.preventDefault();
		event.stopPropagation();
		return;
	}

	await confirmBeforeSubmit(event, 'create-user');
};

const breadcrumbItems: BreadcrumbItem[] = [
	{
		title: 'Provisioning',
		description: 'Create new user accounts and send login credentials via email.',
		href: '/user-management/provisioning',
	},
];
</script>

<template>
	<AppLayout :breadcrumbs="breadcrumbItems">
		<UserManagementLayout>

			<Head title="User management" />

			<section class="space-y-4">


				<Form v-bind="UserManagementController.storeUser.form()" @submit.capture="onCreateUserSubmit" novalidate
					v-slot="{ errors, processing, recentlySuccessful }"
					class="space-y-4 rounded-2xl border border-border/70 bg-background/70 p-5">

                    <div class="space-y-2">
                        <PersonNameRow
							v-model:prefix="createName.prefix"
							v-model:first="createName.first"
							v-model:middle="createName.middle"
							v-model:last="createName.last"
							v-model:extension="createName.extension"
							:prefix-error="errors.prefix"
							:first-error="createClientErrors.first_name ?? errors.first_name"
							:middle-error="errors.middle_name"
							:last-error="createClientErrors.last_name ?? errors.last_name"
							:extension-error="errors.extension"
                            prefix-placeholder="Select"
                            first-placeholder="e.g. Jamie"
                            middle-placeholder="e.g. Santos"
                            last-placeholder="e.g. Reyes"
                            extension-placeholder="e.g. Jr., III"
							@blur:first="() => applyCreateNameTitleCase('first')"
							@blur:middle="() => applyCreateNameTitleCase('middle')"
							@blur:last="() => applyCreateNameTitleCase('last')"
                        />
                    </div>
<!--
                    <div class="grid gap-2">
						<Label for="name">Full Name</Label>
						<Input id="name" name="name" required placeholder="e.g. Jamie Reyes" autocomplete="name" />
						<InputError :message="errors.name" />
					</div> -->

					<div class="grid gap-2">
						<Label for="email">Email Address <span class="text-destructive">*</span></Label>
						<Input id="email" type="email" name="email" required placeholder="jamie@agency.gov"
							autocomplete="email"
							:class="(createClientErrors.email ?? errors.email) ? 'border-destructive focus-visible:border-destructive focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40' : ''"
							:aria-invalid="(createClientErrors.email ?? errors.email) ? 'true' : 'false'"
							:aria-describedby="(createClientErrors.email ?? errors.email) ? 'email-error' : undefined" />
						<InputError id="email-error" :message="createClientErrors.email ?? errors.email" />
					</div>

					<div class="grid gap-2">
						<DivisionSelectSheet v-model="selectedDepartment" :items="props.departments" />
						<InputError :message="errors.department_id" />
					</div>

					<div class="grid gap-2">
						<UnitSelectSheet v-model="selectedUnit" :items="props.units" />
						<InputError :message="errors.unit_id" />
					</div>

					<div class="grid gap-2">
						<PositionSelectSheet v-model="selectedPosition" :items="props.positions" />
						<InputError :message="errors.position_id" />
					</div>

					<div class="grid gap-2">
						<RolesSelectSheet v-model="selectedRoles" :items="props.roles" />
						<InputError :message="errors.role_ids" />
					</div>

								<div class="space-y-2">
					<p class="text-sm text-muted-foreground">
						The user will receive an email with a temporary password and login link.
					</p>
				</div>

					<Button class="w-full" :disabled="processing">
						Create User and Send Email
					</Button>

					<p v-if="recentlySuccessful"
						class="text-center text-xs font-semibold uppercase tracking-[0.4em] text-emerald-600">
						User created and email sent
					</p>
				</Form>
			</section>
		</UserManagementLayout>
	</AppLayout>
</template>
