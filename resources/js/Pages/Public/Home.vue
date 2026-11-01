<script setup>
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';
import { api } from '../../http.js';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import EmptyState from '../../Components/EmptyState.vue';
import ProgressBar from '../../Components/ProgressBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import Timeline from '../../Components/Timeline.vue';

defineProps({
    services: { type: Array, default: () => [] },
    pollAfter: { type: Number, default: 15 },
});

const reference = ref('');
const nationalId = ref('');
const loading = ref(false);
const error = ref('');
const result = ref(null);
const polledAt = ref('');

let timer = null;

async function lookup({ silent = false } = {}) {
    error.value = '';

    if (!/^[A-Za-z0-9-]{6,32}$/.test(reference.value.trim())) {
        error.value = 'Enter a tracking number such as PSP-2026-000123.';
        return;
    }

    loading.value = true;

    const query = new URLSearchParams({ reference: reference.value.trim() });

    if (nationalId.value) {
        query.set('national_id', nationalId.value);
    }

    const response = await api(`track?${query.toString()}`);

    loading.value = false;

    if (!response.ok) {
        result.value = null;
        error.value = response.message ?? 'The lookup failed. Try again shortly.';
        return;
    }

    result.value = { ...response.data, timeline: response.meta?.timeline ?? [], owner_verified: response.meta?.owner_verified };
    polledAt.value = new Date().toLocaleTimeString();

    clearInterval(timer);
    timer = setInterval(() => lookup({ silent: true }), Math.max(5, response.meta?.poll_after_seconds ?? 15) * 1000);
}

function stop() {
    clearInterval(timer);
    timer = null;
    result.value = null;
}

onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <div class="space-y-8">
        <section class="rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-600 to-slate-900 px-6 py-12 text-white shadow-lg">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-200">One window for every counter service</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">Submit an application, follow it to the last signature</h1>
                <p class="mt-3 text-sm text-indigo-100">
                    Birth certificates, building permits, tax clearances and trade licences — filed online, tracked in real time,
                    with every status change recorded for audit.
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a href="#track" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-indigo-700 shadow hover:bg-indigo-50">
                        Track without an account
                    </a>
                    <Link href="/register" class="rounded-lg border border-indigo-300 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500/40">
                        Register as a citizen
                    </Link>
                </div>
            </div>
        </section>

        <AppCard id="track" title="Track an application" description="Anonymous lookup — the applicant's name stays masked until you prove ownership with the citizen number.">
            <form class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="lookup">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="reference">Tracking number</label>
                    <input
                        id="reference"
                        v-model="reference"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm uppercase shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                        placeholder="PSP-2026-000123"
                        autocomplete="off"
                    >
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="nid">Citizen number (optional)</label>
                    <input
                        id="nid"
                        v-model="nationalId"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                        placeholder="10 digits"
                        inputmode="numeric"
                        maxlength="10"
                    >
                </div>
                <div class="flex items-end gap-2">
                    <AppButton :disabled="loading" class="h-[38px]">
                        {{ loading ? 'Checking…' : 'Track' }}
                    </AppButton>
                    <button v-if="result" type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-800" @click="stop">
                        Clear
                    </button>
                </div>
            </form>

            <p v-if="error" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</p>

            <div v-if="result" class="mt-5 space-y-5 border-t border-slate-100 pt-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-mono text-sm font-semibold text-slate-900">{{ result.reference_number }}</p>
                        <p class="text-sm text-slate-700">{{ result.title }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ result.service }} · {{ result.department }} · applicant {{ result.applicant_name }}
                            <span v-if="!result.owner_verified" class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-700">
                                identity masked
                            </span>
                        </p>
                    </div>
                    <StatusBadge :status="result.status" size="lg" />
                </div>

                <ProgressBar :value="result.progress" label="Resolution progress" />

                <dl class="grid gap-3 text-xs sm:grid-cols-4">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-slate-500">Submitted</dt>
                        <dd class="mt-0.5 font-semibold text-slate-800">{{ result.submitted_at?.slice(0, 10) ?? '—' }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-slate-500">Target date</dt>
                        <dd class="mt-0.5 font-semibold" :class="result.is_overdue ? 'text-rose-600' : 'text-slate-800'">
                            {{ result.due_at?.slice(0, 10) ?? '—' }}
                            <span v-if="result.is_overdue" class="ml-1 text-[10px] uppercase">overdue</span>
                        </dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-slate-500">Last movement</dt>
                        <dd class="mt-0.5 font-semibold text-slate-800">{{ result.last_activity_at?.slice(0, 16).replace('T', ' ') ?? '—' }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-slate-500">Live check</dt>
                        <dd class="mt-0.5 font-semibold text-slate-800">{{ polledAt }}</dd>
                    </div>
                </dl>

                <EmptyState v-if="!result.timeline.length" title="No movements recorded yet" />
                <Timeline v-else :items="result.timeline" />
            </div>
        </AppCard>

        <AppCard title="Services you can apply for" description="Fees and turnaround targets published by each department.">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Service</th>
                            <th class="py-2 pr-4 font-semibold">Department</th>
                            <th class="py-2 pr-4 font-semibold">Code</th>
                            <th class="py-2 pr-4 text-right font-semibold">Fee</th>
                            <th class="py-2 text-right font-semibold">Target</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="service in services" :key="service.code">
                            <td class="py-2 pr-4 font-medium text-slate-800">{{ service.name }}</td>
                            <td class="py-2 pr-4 text-slate-600">{{ service.department }}</td>
                            <td class="py-2 pr-4 font-mono text-xs text-slate-500">{{ service.code }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ service.fee > 0 ? service.fee.toFixed(2) : 'Free' }}</td>
                            <td class="py-2 text-right tabular-nums text-slate-600">{{ service.sla_days }} days</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-xs text-slate-500">
                Already filed something? Sign in to upload supporting documents and answer the officer —
                <Link href="/login" class="font-semibold text-indigo-600 hover:underline">sign in</Link>.
            </p>
        </AppCard>
    </div>
</template>
