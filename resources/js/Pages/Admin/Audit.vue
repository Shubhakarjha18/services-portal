<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import AppCard from '../../Components/AppCard.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import SelectInput from '../../Components/SelectInput.vue';
import TextInput from '../../Components/TextInput.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    logs: { type: Object, required: true },
    detail: { type: Object, default: null },
    filters: { type: Object, required: true },
    events: { type: Array, default: () => [] },
    actors: { type: Array, default: () => [] },
});

const page = usePage();

const query = reactive({
    q: props.filters.q ?? '',
    event: props.filters.event ?? '',
    user_id: props.filters.user_id ? String(props.filters.user_id) : '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

let debounce = null;

watch(query, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/admin/audit', { ...query }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

function open(row) {
    router.get('/admin/audit', { ...query, log: row.id }, { preserveState: true, preserveScroll: true });
}

const rows = computed(() => props.logs.data ?? []);

const changed = computed(() => {
    if (!props.detail) return [];

    const before = props.detail.old_values ?? {};
    const after = props.detail.new_values ?? {};
    const keys = new Set([...Object.keys(before), ...Object.keys(after)]);

    return [...keys].map((key) => ({ key, before: before[key], after: after[key] }));
});

const roleTone = (role) =>
    role === 'admin' ? 'bg-indigo-50 text-indigo-700' : role === 'officer' ? 'bg-teal-50 text-teal-700' : 'bg-slate-100 text-slate-600';

function display(value) {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'object') return JSON.stringify(value);

    return String(value);
}
</script>

<template>
    <AppLayout>
        <Head title="Audit trail" />

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">Audit trail</h1>
                <p class="text-sm text-slate-500">
                    Append-only record of logins, status changes, document reviews and data edits.
                    {{ page.props.can?.is_admin ? 'You see the whole trail.' : 'Scoped to your own actions and cases.' }}
                </p>
            </div>
            <p class="text-xs text-slate-500">{{ logs.total }} entries</p>
        </div>

        <AppCard compact>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="aq">Search</label>
                    <TextInput id="aq" v-model="query.q" placeholder="Reference, event or actor" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="aevent">Event</label>
                    <SelectInput id="aevent" v-model="query.event" :options="events.map((e) => ({ value: e, label: e }))" placeholder="All events" />
                </div>
                <div v-if="actors.length">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="aactor">Actor</label>
                    <SelectInput id="aactor" v-model="query.user_id" :options="actors.map((a) => ({ value: String(a.id), label: a.name }))" placeholder="Anyone" />
                </div>
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="afrom">From</label>
                        <TextInput id="afrom" v-model="query.from" type="date" />
                    </div>
                    <div class="flex-1">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="ato">To</label>
                        <TextInput id="ato" v-model="query.to" type="date" />
                    </div>
                </div>
            </div>
        </AppCard>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <AppCard title="Entries" description="Click a row to inspect the recorded values.">
                <EmptyState v-if="!rows.length" title="No audit entries match" description="Loosen the filters — the trail only grows, entries are never deleted." />
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="py-2 pr-3 font-semibold">When</th>
                                <th class="py-2 pr-3 font-semibold">Event</th>
                                <th class="py-2 pr-3 font-semibold">Actor</th>
                                <th class="py-2 pr-3 font-semibold">Subject</th>
                                <th class="py-2 font-semibold">Reference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="row in rows"
                                :key="row.id"
                                class="cursor-pointer hover:bg-slate-50"
                                :class="String(detail?.id) === String(row.id) ? 'bg-indigo-50/50' : ''"
                                @click="open(row)"
                            >
                                <td class="py-2.5 pr-3 text-xs text-slate-500">
                                    {{ row.at }}
                                    <span class="block text-[11px] text-slate-400">{{ row.human }}</span>
                                </td>
                                <td class="py-2.5 pr-3">
                                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">{{ row.event }}</code>
                                </td>
                                <td class="py-2.5 pr-3">
                                    <p class="text-slate-700">{{ row.actor }}</p>
                                    <span v-if="row.role" :class="['mt-0.5 inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase', roleTone(row.role)]">
                                        {{ row.role }}
                                    </span>
                                </td>
                                <td class="py-2.5 pr-3 text-xs text-slate-500">
                                    {{ row.subject }}<span v-if="row.subject_id"> #{{ row.subject_id }}</span>
                                    <span class="block text-[11px] text-slate-400">{{ row.ip }}</span>
                                </td>
                                <td class="py-2.5 font-mono text-xs text-slate-600">{{ row.reference ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <Pagination :links="logs.links" :meta="{ total: logs.total, page: logs.page, last_page: logs.last_page }" />
                </div>
            </AppCard>

            <AppCard title="Entry detail" :description="detail ? `#${detail.id}` : 'Select an entry on the left.'">
                <EmptyState v-if="!detail" title="Nothing selected" />
                <div v-else class="space-y-4 text-sm">
                    <div>
                        <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ detail.event }}</code>
                        <p class="mt-1 text-xs text-slate-500">{{ detail.created_at }} · {{ detail.user_name ?? 'Anonymous' }} ({{ detail.role_name ?? 'no role' }})</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 text-xs">
                        <p><span class="text-slate-500">Request:</span> <code>{{ detail.method }} {{ detail.url }}</code></p>
                        <p class="mt-1"><span class="text-slate-500">IP:</span> {{ detail.ip_address ?? '—' }}</p>
                        <p class="mt-1 break-all"><span class="text-slate-500">Agent:</span> {{ detail.user_agent ?? '—' }}</p>
                    </div>

                    <div v-if="changed.length">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Values</p>
                        <table class="w-full text-left text-xs">
                            <thead class="text-[10px] uppercase tracking-wide text-slate-400">
                                <tr>
                                    <th class="py-1 pr-2 font-semibold">Field</th>
                                    <th class="py-1 pr-2 font-semibold">Before</th>
                                    <th class="py-1 font-semibold">After</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="entry in changed" :key="entry.key">
                                    <td class="py-1.5 pr-2 font-medium text-slate-700">{{ entry.key }}</td>
                                    <td class="py-1.5 pr-2 text-slate-500">{{ display(entry.before) }}</td>
                                    <td class="py-1.5 text-slate-800">{{ display(entry.after) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="detail.meta && Object.keys(detail.meta).length">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Context</p>
                        <pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-[11px] leading-relaxed text-slate-100">{{ JSON.stringify(detail.meta, null, 2) }}</pre>
                    </div>
                </div>
            </AppCard>
        </div>
    </AppLayout>
</template>
