<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppCard from '../../Components/AppCard.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import ProgressBar from '../../Components/ProgressBar.vue';
import SelectInput from '../../Components/SelectInput.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    applications: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    view: { type: String, default: 'mine' },
});

const page = usePage();
const staff = computed(() => page.props.can?.is_admin || page.props.can?.is_officer);

const state = ref({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    assigned: props.filters.assigned ?? '',
    overdue: props.filters.overdue ? '1' : '',
    sort: props.filters.sort ?? 'created_at',
    page: 1,
});

let debounce = null;

function apply(reset = true) {
    if (reset) state.value.page = 1;

    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/applications', { ...state.value, overdue: state.value.overdue ? 1 : undefined }, {
            preserveState: true,
            preserveScroll: true,
            replace: reset,
        });
    }, reset ? 0 : 250);
}

watch(
    () => props.filters,
    (value) => {
        state.value = {
            q: value.q ?? '',
            status: value.status ?? '',
            assigned: value.assigned ?? '',
            overdue: value.overdue ? '1' : '',
            sort: value.sort ?? 'created_at',
            page: new URLSearchParams(window.location.search).get('page') ?? 1,
        };
    },
    { deep: true },
);

function clear() {
    state.value = { q: '', status: '', assigned: '', overdue: '', sort: 'created_at', page: 1 };
    apply();
}

const rows = computed(() => props.applications.data ?? []);
const meta = computed(() => ({ total: props.applications.total, page: props.applications.from, last_page: props.applications.to }));

const sortOptions = [
    { value: 'created_at', label: 'Newest filed' },
    { value: 'due_at', label: 'Deadline' },
    { value: 'stage', label: 'Workflow stage' },
    { value: 'last_activity_at', label: 'Last movement' },
    { value: 'reference_number', label: 'Reference' },
];
</script>

<template>
    <AppLayout>
        <Head :title="props.view === 'mine' ? 'My applications' : 'Applications'" />

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">
                    {{ props.view === 'mine' ? 'My applications' : props.view === 'queue' ? 'Department queue' : 'All applications' }}
                </h1>
                <p class="text-sm text-slate-500">
                    {{ props.view === 'mine' ? 'Everything you have filed, with live status.' : 'Scoped by role: officers only see their department, citizens only their own cases.' }}
                </p>
            </div>
            <Link href="/applications/create" class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                New application
            </Link>
        </div>

        <AppCard compact>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="q">Search</label>
                    <input
                        id="q"
                        v-model="state.q"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                        placeholder="Reference, title or applicant"
                        @input="apply(false)"
                    >
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="status">Status</label>
                    <SelectInput id="status" v-model="state.status" :options="statuses.map((s) => ({ value: s.code, label: s.name }))" placeholder="Any status" @update:model-value="apply()" />
                </div>
                <div v-if="staff">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="assigned">Assignment</label>
                    <SelectInput
                        id="assigned"
                        v-model="state.assigned"
                        :options="[{ value: 'me', label: 'Assigned to me' }, { value: 'unassigned', label: 'Unassigned' }]"
                        placeholder="Anyone"
                        @update:model-value="apply()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="sort">Sort</label>
                    <SelectInput id="sort" v-model="state.sort" :options="sortOptions" @update:model-value="apply()" />
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="state.overdue" type="checkbox" true-value="1" false-value="" @change="apply()">
                    Overdue only
                </label>
                <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-800" @click="clear">Reset filters</button>
            </div>
        </AppCard>

        <AppCard :compact="false">
            <template #actions>
                <span class="text-xs text-slate-500">{{ meta.total }} matching</span>
            </template>

            <EmptyState
                v-if="!rows.length"
                :title="props.view === 'mine' ? 'You have not filed anything yet' : 'No application matches these filters'"
                :description="props.view === 'mine' ? 'Start with a service from the catalogue — you can upload documents right after.' : 'Widen the search or reset the filters above.'"
            >
                <template #action>
                    <Link href="/applications/create" class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                        Start an application
                    </Link>
                </template>
            </EmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Case</th>
                            <th class="py-2 pr-4 font-semibold">Status</th>
                            <th class="py-2 pr-4 font-semibold">Progress</th>
                            <th v-if="staff" class="py-2 pr-4 font-semibold">Applicant</th>
                            <th v-if="staff" class="py-2 pr-4 font-semibold">Officer</th>
                            <th class="py-2 pr-4 font-semibold">Target</th>
                            <th class="py-2 pr-4 text-right font-semibold">Docs</th>
                            <th class="py-2 text-right font-semibold">Updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in rows" :key="row.id" class="align-top hover:bg-slate-50/70">
                            <td class="py-3 pr-4">
                                <Link :href="`/applications/${row.id}`" class="font-mono text-xs text-indigo-600 hover:underline">{{ row.reference }}</Link>
                                <Link :href="`/applications/${row.id}`" class="mt-0.5 block max-w-xs truncate font-medium text-slate-800 hover:text-indigo-700">
                                    {{ row.title }}
                                </Link>
                                <p class="text-xs text-slate-500">{{ row.service }} · {{ row.department }}</p>
                            </td>
                            <td class="py-3 pr-4">
                                <StatusBadge :status="row.status" />
                                <p v-if="row.priority !== 'normal'" class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-amber-600">
                                    {{ row.priority }} priority
                                </p>
                            </td>
                            <td class="w-32 py-3 pr-4">
                                <ProgressBar :value="row.progress" />
                            </td>
                            <td v-if="staff" class="py-3 pr-4 text-slate-600">{{ row.applicant }}</td>
                            <td v-if="staff" class="py-3 pr-4">
                                <span :class="row.officer ? 'text-slate-700' : 'text-slate-400'">{{ row.officer ?? 'Unassigned' }}</span>
                            </td>
                            <td class="py-3 pr-4">
                                <span :class="row.is_overdue ? 'font-semibold text-rose-600' : 'text-slate-700'">{{ row.due_at ?? '—' }}</span>
                                <p v-if="row.days_remaining !== null" class="text-[11px] text-slate-500">
                                    {{ row.days_remaining < 0 ? `${Math.abs(row.days_remaining)}d late` : `${row.days_remaining}d left` }}
                                </p>
                            </td>
                            <td class="py-3 pr-4 text-right tabular-nums text-slate-600">{{ row.documents }}</td>
                            <td class="py-3 text-right text-xs text-slate-500">{{ row.updated_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="applications.links" :meta="meta" />
            </div>
        </AppCard>
    </AppLayout>
</template>
