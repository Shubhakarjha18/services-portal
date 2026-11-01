<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import Field from '../../Components/Field.vue';
import SelectInput from '../../Components/SelectInput.vue';
import TextArea from '../../Components/TextArea.vue';
import TextInput from '../../Components/TextInput.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    services: { type: Array, default: () => [] },
    prefill: { type: Object, default: () => ({}) },
});

const form = useForm({
    service_type_id: String(props.services[0]?.id ?? ''),
    title: '',
    description: '',
    applicant_name: props.prefill.applicant_name ?? '',
    applicant_phone: props.prefill.applicant_phone ?? '',
    applicant_address: props.prefill.applicant_address ?? '',
});

const search = ref('');

const visibleServices = computed(() => {
    const needle = search.value.trim().toLowerCase();

    if (!needle) return props.services;

    return props.services.filter((service) =>
        [service.name, service.code, service.department].filter(Boolean).join(' ').toLowerCase().includes(needle),
    );
});

const selected = computed(() => props.services.find((service) => String(service.id) === String(form.service_type_id)));

function choose(service) {
    form.service_type_id = String(service.id);
}

function submit() {
    form.post('/applications');
}
</script>

<template>
    <AppLayout>
        <Head title="New application" />

        <div class="flex items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">Start an application</h1>
                <p class="text-sm text-slate-500">Pick the service, describe what you need, and you will get a tracking number immediately.</p>
            </div>
            <a href="/applications" class="text-sm font-semibold text-slate-500 hover:text-slate-800">Cancel</a>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <form class="space-y-4" @submit.prevent="submit">
                <AppCard title="Your request">
                    <div class="space-y-4">
                        <Field label="Subject" for="title" :error="form.errors.title" hint="Short summary an officer can recognise at a glance" required>
                            <TextInput id="title" v-model="form.title" :invalid="!!form.errors.title" placeholder="Certified copy of birth certificate for school registration" />
                        </Field>

                        <Field label="Details" for="description" :error="form.errors.description" hint="What you need, why, and any reference numbers you already hold (20+ characters)" required>
                            <TextArea id="description" v-model="form.description" :rows="6" :invalid="!!form.errors.description" />
                        </Field>
                    </div>
                </AppCard>

                <AppCard title="Applicant details" description="Must match the documents you upload — officers verify identity before approval.">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <Field label="Full name" for="applicant_name" :error="form.errors.applicant_name" required>
                            <TextInput id="applicant_name" v-model="form.applicant_name" :invalid="!!form.errors.applicant_name" />
                        </Field>
                        <Field label="Phone" for="applicant_phone" :error="form.errors.applicant_phone" required>
                            <TextInput id="applicant_phone" v-model="form.applicant_phone" :invalid="!!form.errors.applicant_phone" />
                        </Field>
                        <div class="sm:col-span-2">
                            <Field label="Postal address" for="applicant_address" :error="form.errors.applicant_address" required>
                                <TextArea id="applicant_address" v-model="form.applicant_address" :rows="2" :invalid="!!form.errors.applicant_address" />
                            </Field>
                        </div>
                    </div>
                </AppCard>

                <div class="flex items-center justify-end gap-3">
                    <a href="/applications" class="rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Discard
                    </a>
                    <AppButton :disabled="form.processing || !selected">
                        {{ form.processing ? 'Submitting…' : 'Submit application' }}
                    </AppButton>
                </div>
            </form>

            <div class="space-y-4">
                <AppCard title="Choose a service">
                    <input
                        v-model="search"
                        class="mb-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                        placeholder="Filter by name, code or department"
                    >
                    <div class="max-h-72 space-y-2 overflow-y-auto pr-1">
                        <button
                            v-for="service in visibleServices"
                            :key="service.id"
                            type="button"
                            :class="[
                                'w-full rounded-lg border px-3 py-2 text-left transition',
                                String(service.id) === String(form.service_type_id)
                                    ? 'border-indigo-500 bg-indigo-50/70 ring-1 ring-indigo-200'
                                    : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50',
                            ]"
                            @click="choose(service)"
                        >
                            <p class="text-sm font-semibold text-slate-800">{{ service.name }}</p>
                            <p class="text-xs text-slate-500">{{ service.department }} · {{ service.sla_days }} days · {{ service.fee > 0 ? service.fee.toFixed(2) : 'free' }}</p>
                            <p class="mt-1 font-mono text-[10px] uppercase tracking-wide text-slate-400">{{ service.code }}</p>
                        </button>
                        <p v-if="!visibleServices.length" class="py-6 text-center text-sm text-slate-500">No service matches “{{ search }}”.</p>
                    </div>
                </AppCard>

                <AppCard v-if="selected" title="Before you submit" :description="selected.description ?? ''">
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Turnaround target</dt>
                            <dd class="text-slate-800">{{ selected.sla_days }} calendar days</dd>
                        </div>
                        <div v-if="selected.fee > 0">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fee</dt>
                            <dd class="text-slate-800">{{ selected.fee.toFixed(2) }} — pay at the counter; the receipt is one of the documents.</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Documents you will need</dt>
                            <dd>
                                <ul class="mt-1 space-y-1">
                                    <li v-for="label in selected.required_documents" :key="label" class="flex items-start gap-2 text-slate-700">
                                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400" />
                                        {{ label }}
                                    </li>
                                    <li v-if="!selected.required_documents.length" class="text-slate-500">No documents required for this service.</li>
                                </ul>
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        You can upload these after submitting — the case simply stays in “documents required” until they are verified.
                    </p>
                </AppCard>
            </div>
        </div>
    </AppLayout>
</template>
