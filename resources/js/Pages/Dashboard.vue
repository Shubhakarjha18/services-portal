<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppCard from '../Components/AppCard.vue';
import EmptyState from '../Components/EmptyState.vue';
import ProgressBar from '../Components/ProgressBar.vue';
import StatCard from '../Components/StatCard.vue';
import StatusBadge from '../Components/StatusBadge.vue';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    stats: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    workload: { type: Array, default: () => [] },
    attention: { type: Array, default: () => [] },
    view: { type: String, default: 'mine' },
});

const page = usePage();
const totals = computed(() => props.stats.totals ?? {});

// Tailwind only emits classes it can see literally, so status colours map here.
const bars = {
    slate: 'bg-slate-400',
    blue: 'bg-blue-500',
    indigo: 'bg-indigo-500',
    amber: 'bg-amber-500',
    orange: 'bg-orange-500',
    red: 'bg-rose-500',
    green: 'bg-emerald-500',
    emerald: 'bg-emerald-500',
    teal: 'bg-teal-500',
    violet: 'bg-violet-500',
    cyan: 'bg-cyan-500',
    rose: 'bg-rose-500',
};

const statusRows = computed(() => {
    const counts = props.stats.by_status ?? [];
    const max = Math.max(1, ...counts.map((row) => row.count));

    return counts
        .map((row) => ({ ...row, share: Math.round((row.count / max) * 100), bar: bars[row.color] ?? 'bg-slate-400' }))
        .sort((a, b) => b.count - a.count);
});

const trend = computed(() => {
    const entries = Object.entries(props.stats.submissions_last_30_days ?? {});
    const max = Math.max(1, ...entries.map(([, count]) => Number(count)));

    return entries.map(([day, count]) => ({ day, count: Number(count), height: Math.max(6, Math.round((Number(count) / max) * 100)) }));
});

const departmentRows = computed(() => {
    const rows = props.stats.by_department ?? [];
    const max = Math.max(1, ...rows.map((row) => row.count));

    return [...rows].sort((a, b) => b.count - a.count).map((row) => ({ ...row, share: Math.round((row.count / max) * 100) }));
});

const headline = computed(() => {
    if (props.view === 'mine') return 'Your applications';
    if (props.view === 'queue') return 'Your caseload';

    return 'Portal at a glance';
});
</script>

<template>
    <AppLayout>
        <Head :title="headline" />

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">{{ headline }}</h1>
                <p class="text-sm text-slate-500">
                    Signed in as {{ page.props.auth?.user?.name }} · {{ page.props.auth?.user?.role_label }}
                    <span v-if="page.props.auth?.user?.department"> · {{ page.props.auth.user.department.name }}</span>
                </p>
            </div>
            <div class="flex gap-2">
                <Link href="/applications" class="rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    {{ props.view === 'mine' ? 'My applications' : 'Open the register' }}
                </Link>
                <Link href="/applications/create" class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    New application
                </Link>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Total" :value="totals.all ?? 0" :hint="props.view === 'mine' ? 'Filed by you' : 'Visible to your role'" />
            <StatCard label="In progress" :value="totals.open ?? 0" tone="text-indigo-700" hint="Still moving through the workflow" />
            <StatCard label="Past target date" :value="totals.overdue ?? 0" :tone="totals.overdue ? 'text-rose-600' : 'text-slate-900'" hint="Breached SLA" />
            <StatCard label="Due within 3 days" :value="totals.due_soon ?? 0" tone="text-amber-600" hint="Needs attention now" />
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <AppCard title="Stage breakdown" class="lg:col-span-1">
                <EmptyState v-if="!statusRows.length" title="No activity yet" />
                <ul v-else class="space-y-3">
                    <li v-for="row in statusRows" :key="row.code">
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <StatusBadge :status="{ name: row.name, code: row.code, color: row.color }" />
                            <span class="text-sm font-semibold tabular-nums text-slate-700">{{ row.count }}</span>
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                            <div :class="['h-full rounded-full', row.bar]" :style="{ width: `${row.share}%` }" />
                        </div>
                    </li>
                </ul>
            </AppCard>

            <AppCard title="Applications filed · last 30 days" description="Daily submission volume">
                <div v-if="trend.length" class="flex h-40 items-end gap-1">
                    <div
                        v-for="point in trend"
                        :key="point.day"
                        class="group relative flex-1 rounded-t bg-indigo-200 transition hover:bg-indigo-400"
                        :style="{ height: `${point.height}%` }"
                        :title="`${point.day}: ${point.count}`"
                    >
                        <span class="absolute inset-x-0 -top-5 hidden text-center text-[10px] font-semibold text-slate-600 group-hover:block">
                            {{ point.count }}
                        </span>
                    </div>
                </div>
                <EmptyState v-else title="No submissions in the window" />
                <p v-if="stats.my_queue !== null && stats.my_queue !== undefined" class="mt-3 text-xs text-slate-500">
                    Cases assigned to you still open: <span class="font-semibold text-slate-700">{{ stats.my_queue }}</span>
                </p>
                <p v-if="stats.unassigned !== null && stats.unassigned !== undefined" class="mt-1 text-xs text-slate-500">
                    Unassigned open cases: <span class="font-semibold text-slate-700">{{ stats.unassigned }}</span>
                </p>
            </AppCard>

            <AppCard title="Needs attention" description="Earliest target dates first">
                <EmptyState v-if="!attention.length" title="Nothing outstanding" description="Every case you can see has a decision." />
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="item in attention" :key="item.id" class="py-2.5">
                        <Link :href="`/applications/${item.id}`" class="block hover:text-indigo-700">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-xs text-slate-500">{{ item.reference }}</span>
                                <StatusBadge :status="item.status" />
                            </div>
                            <p class="mt-0.5 truncate text-sm font-medium text-slate-800">{{ item.title }}</p>
                            <p class="text-xs" :class="item.is_overdue ? 'text-rose-600' : 'text-slate-500'">
                                Target {{ item.due_at ?? '—' }} · {{ item.days_remaining < 0 ? `${Math.abs(item.days_remaining)} days late` : `${item.days_remaining} days left` }}
                            </p>
                        </Link>
                    </li>
                </ul>
            </AppCard>
        </div>

        <div v-if="props.view !== 'mine'" class="grid gap-4 lg:grid-cols-2">
            <AppCard v-if="departmentRows.length" title="By department">
                <ul class="space-y-3">
                    <li v-for="row in departmentRows" :key="row.department">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="text-slate-700">{{ row.department }}</span>
                            <span class="font-semibold tabular-nums text-slate-700">{{ row.count }}</span>
                        </div>
                        <ProgressBar :value="row.share" tone="bg-slate-400" />
                    </li>
                </ul>
            </AppCard>

            <AppCard v-if="workload.length" title="Officer workload" description="Live counts per case officer">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="py-2 pr-3 font-semibold">Officer</th>
                                <th class="py-2 pr-3 text-right font-semibold">Open</th>
                                <th class="py-2 pr-3 text-right font-semibold">Overdue</th>
                                <th class="py-2 text-right font-semibold">Closed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="officer in workload" :key="officer.id">
                                <td class="py-2 pr-3 font-medium text-slate-800">{{ officer.name }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ officer.open_cases }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums" :class="officer.overdue_cases ? 'font-semibold text-rose-600' : ''">
                                    {{ officer.overdue_cases }}
                                </td>
                                <td class="py-2 text-right tabular-nums text-slate-500">{{ officer.closed_cases }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>
    </AppLayout>
</template>
