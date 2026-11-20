<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { History } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
	Table,
	TableBody,
	TableCell,
	TableHead,
	TableHeader,
	TableRow,

} from '@/components/ui/table';
import RequestFilterComponent from '@/components/request/RequestFilterComponent.vue';
import RequestTableSkeleton from '@/components/request/RequestTableSkeleton.vue';
import RequestMobileSkeleton from '@/components/request/RequestMobileSkeleton.vue';
import type { AuditLog } from '@/types/schema';

const props = defineProps<{
	logs: {
		data: AuditLog[];
		current_page: number;
		last_page: number;
		total: number;
		links: { url: string | null; label: string; active: boolean }[];
	};
}>();

const isLoading = ref(true);

// Filter state
const searchQuery = ref('');
const showFilters = ref(false);
const actionFilter = ref<string>('');
const dateFromFilter = ref<string>('');
const dateToFilter = ref<string>('');

const actionLabels: Record<string, string> = {
	request_created: 'Created Request',
	request_status_changed: 'Changed Status',
	request_file_attached: 'Attached File',
	request_file_detached: 'Detached File',
	request_scan_uploaded: 'Uploaded Scan',
};

const formatAction = (action: string) => actionLabels[action] || action;

const formatDetails = (log: AuditLog) => {
	if (!log.properties) return '—';

	if (log.action === 'request_status_changed') {
		const d = log.properties as { old_status?: string; new_status?: string };
		return `${d.old_status} → ${d.new_status}`;
	}

	if (log.action === 'request_file_attached' || log.action === 'request_file_detached' || log.action === 'request_scan_uploaded') {
		const d = log.properties as { file_name?: string };
		return d.file_name || '—';
	}

	if (log.action === 'request_created') {
		const d = log.properties as { request_title?: string };
		return d.request_title || '—';
	}

	return JSON.stringify(log.properties);
};

const formatDate = (date: string | null) => {
	if (!date) return '—';
	return new Date(date).toLocaleDateString('en-US', {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	});
};

const actionVariant = (action: string) => {
	switch (action) {
		case 'request_created': return 'default' as const;
		case 'request_status_changed': return 'outline' as const;
		case 'request_file_attached': return 'secondary' as const;
		case 'request_scan_uploaded': return 'secondary' as const;
		case 'request_file_detached': return 'destructive' as const;
		default: return 'secondary' as const;
	}
};

// Filtered logs
const filteredLogs = computed(() => {
	let filtered = [...props.logs.data];

	// Search filter
	if (searchQuery.value) {
		const query = searchQuery.value.toLowerCase();
		filtered = filtered.filter(log =>
			(log.user?.name || '').toLowerCase().includes(query) ||
			(log.auditable?.request_title || log.auditable_name || '').toLowerCase().includes(query) ||
			formatAction(log.action).toLowerCase().includes(query) ||
			formatDetails(log).toLowerCase().includes(query)
		);
	}

	// Action filter
	if (actionFilter.value) {
		filtered = filtered.filter(log => log.action === actionFilter.value);
	}

	// Date from filter
	if (dateFromFilter.value) {
		filtered = filtered.filter(log => {
			const logDate = new Date(log.created_at);
			const filterDate = new Date(dateFromFilter.value);
			return logDate >= filterDate;
		});
	}

	// Date to filter
	if (dateToFilter.value) {
		filtered = filtered.filter(log => {
			const logDate = new Date(log.created_at);
			const filterDate = new Date(dateToFilter.value);
			return logDate <= filterDate;
		});
	}

	return filtered;
});

const hasActiveFilters = computed(() => {
	return !!(searchQuery.value || actionFilter.value || dateFromFilter.value || dateToFilter.value);
});

const clearFilters = () => {
	searchQuery.value = '';
	actionFilter.value = '';
	dateFromFilter.value = '';
	dateToFilter.value = '';
};

onMounted(() => {
	setTimeout(() => {
		isLoading.value = false;
	}, 300);
});
</script>

<template>
	<AppLayout :breadcrumbs="[
		{ title: 'Request History', description: 'View and manage request history', href: '/requests/history' },
	]">

		<Head title="Request History" />

		<div class="flex h-full flex-1 flex-col gap-6 p-6">
			<!-- Search and Filters -->
			<RequestFilterComponent
				:search-query="searchQuery"
				:search-placeholder="'Search by User, Request, Action, or Details...'"
				:show-filters="showFilters"
				:action-filter="actionFilter"
				:date-from-filter="dateFromFilter"
				:date-to-filter="dateToFilter"
				:show-history-filters="true"
				@update:search-query="searchQuery = $event"
				@update:show-filters="showFilters = $event"
				@update:action-filter="actionFilter = $event"
				@update:date-from-filter="dateFromFilter = $event"
				@update:date-to-filter="dateToFilter = $event"
				@clear-filters="clearFilters"
			/>

			<!-- Results count -->
			<div v-if="!isLoading" class="flex items-center justify-between text-sm text-muted-foreground">
				<div>
					Showing {{ filteredLogs.length }} of {{ logs.total }} Entries
				</div>
				<div v-if="logs.last_page > 1">
					Page {{ logs.current_page }} / {{ logs.last_page }}
				</div>
			</div>

			<!-- Web View -->
			<div class="hidden md:flex flex-1 rounded-xl border bg-card shadow-sm overflow-hidden flex-col">
				<RequestTableSkeleton v-if="isLoading" variant="requestHistory" :rows="6" />
				<div v-else-if="filteredLogs.length > 0" class="flex flex-col h-full">
					<div class="w-full">
						<Table class="w-full table-fixed">
							<TableHeader class="bg-background sticky top-0 z-10">
								<TableRow class="border-b">
									<TableHead class="text-left py-2 md:py-3 text-xs md:text-sm max-w-xs md:max-w-sm">Date</TableHead>
									<TableHead class="text-left py-2 md:py-3 text-xs md:text-sm">User</TableHead>
									<TableHead class="text-left py-2 md:py-3 text-xs md:text-sm hidden md:table-cell">Action</TableHead>
									<TableHead class="text-left py-2 md:py-3 text-xs md:text-sm max-w-sm md:max-w-xl truncate">Request</TableHead>
									<TableHead class="text-left py-2 md:py-3 text-xs md:text-sm hidden lg:table-cell">Details</TableHead>
								</TableRow>
							</TableHeader>
						</Table>
					</div>
					<div class="flex-1 overflow-y-auto">
						<Table class="w-full table-fixed">
							<TableBody>
								<TableRow v-for="log in filteredLogs" :key="log.id" class="cursor-pointer even:bg-muted/30 transition-colors hover:bg-muted/50">
									<TableCell class="whitespace-nowrap text-xs md:text-sm text-left py-2 md:py-3 max-w-xs md:max-w-sm">{{ formatDate(log.created_at) }}</TableCell>
									<TableCell class="text-xs md:text-sm text-left py-2 md:py-3">{{ log.user?.name || '—' }}</TableCell>
									<TableCell class="text-xs md:text-sm text-left py-2 md:py-3 hidden md:table-cell">
										<Badge :variant="actionVariant(log.action)">{{ formatAction(log.action) }}</Badge>
									</TableCell>
									<TableCell class="text-xs md:text-sm text-left py-2 md:py-3 max-w-sm md:max-w-xl truncate">{{ log.auditable?.request_title || log.auditable_name || '—' }}</TableCell>
									<TableCell class="text-xs md:text-sm text-left py-2 md:py-3 max-w-xs truncate hidden lg:table-cell">{{ formatDetails(log) }}</TableCell>
								</TableRow>
							</TableBody>
						</Table>
					</div>
				</div>
				<div v-else class="flex flex-col items-center justify-center py-20 text-center">
					<History class="h-24 w-24 text-muted-foreground/50 mb-4" />
					<h2 class="text-xl font-semibold mb-2">
						{{ hasActiveFilters ? 'No Matching Activity' : 'No Activity' }}
					</h2>
					<p class="text-muted-foreground max-w-md">
						{{ hasActiveFilters ? 'Try adjusting your filters to see more results.' : 'No activity logged yet.' }}
					</p>
				</div>
			</div>

			<!-- Mobile View -->
			<RequestMobileSkeleton v-if="isLoading" class="md:hidden" :fields="[
				{ labelWidth: 'w-20', valueWidth: 'w-24' },
				{ labelWidth: 'w-16', valueWidth: 'w-32' },
				{ labelWidth: 'w-24', valueWidth: 'w-20' },
			]" />
			<div v-else-if="filteredLogs.length > 0" class="flex md:hidden flex-1 flex-col gap-3 overflow-y-auto">
				<div v-for="log in filteredLogs" :key="log.id" class="rounded-lg border bg-card p-4 shadow-sm cursor-pointer transition-colors hover:bg-muted/50">
					<div class="mb-2 flex items-start justify-between gap-2">
						<p class="text-xs text-muted-foreground">{{ formatDate(log.created_at) }}</p>
						<Badge :variant="actionVariant(log.action)" class="whitespace-nowrap text-xs">
							{{ formatAction(log.action) }}
						</Badge>
					</div>
					<div class="mb-2">
						<p class="font-medium text-sm line-clamp-2">{{ log.auditable?.request_title || log.auditable_name || '—' }}</p>
						<p class="text-xs text-muted-foreground">{{ log.user?.name || '—' }}</p>
					</div>
					<div class="text-xs text-muted-foreground line-clamp-2">
						{{ formatDetails(log) }}
					</div>
				</div>
			</div>
			<div v-else class="flex md:hidden flex-col items-center justify-center py-20 text-center">
				<History class="h-24 w-24 text-muted-foreground/50 mb-4" />
				<h2 class="text-xl font-semibold mb-2">
					{{ hasActiveFilters ? 'No Matching Activity' : 'No Activity' }}
				</h2>
				<p class="text-muted-foreground max-w-md">
					{{ hasActiveFilters ? 'Try adjusting your filters to see more results.' : 'No activity logged yet.' }}
				</p>
			</div>

			<!-- Pagination -->
			<div v-if="!isLoading && logs.links?.length" class="flex flex-wrap items-center gap-1.5">
				<Button
					v-for="link in logs.links"
					:key="link.label"
					:disabled="!link.url"
					variant="outline"
					size="sm"
					class="h-8 min-w-8 px-2 text-xs"
					:class="{
						'bg-primary text-primary-foreground dark:bg-white dark:text-black hover:bg-primary hover:text-primary-foreground dark:hover:bg-white dark:hover:text-black': link.active,
					}"
					@click="link.url && router.visit(link.url, { preserveState: true, preserveScroll: true, replace: true })"
				>
					<span v-html="link.label" />
				</Button>
			</div>
		</div>
	</AppLayout>
</template>
