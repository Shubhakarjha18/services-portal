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
    departments: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
});

const departmentOptions = computed(() => props.departments.map((d) => ({ value: String(d.id), label: `${d.name} (${d.code})` })));

const department = useForm({ name: '', code: '', description: '', contact_email: '', default_sla_days: 15, is_active: true });
const editingDepartment = ref(null);

function startDepartmentEdit(row) {
    editingDepartment.value = row.id;
    department.name = row.name;
    department.code = row.code;
    department.description = row.description ?? '';
    department.contact_email = row.contact_email ?? '';
    department.default_sla_days = row.default_sla_days;
    department.is_active = row.is_active;
}

function saveDepartment() {
    if (editingDepartment.value) {
        department.put(`/admin/departments/${editingDepartment.value}`, { preserveScroll: true });

        return;
    }

    department.post('/admin/departments', {
        preserveScroll: true,
        onSuccess: () => department.reset(),
    });
}

const service = useForm({
    department_id: String(props.departments[0]?.id ?? ''),
    name: '',
    code: '',
    description: '',
    fee: 0,
    sla_days: 15,
    required_documents: '',
    is_public: true,
    requires_payment: false,
});

const editingService = ref(null);

function startServiceEdit(row) {
    editingService.value = row.id;
    service.name = row.name;
    service.code = row.code;
    service.department_id = String(row.department_id);
    service.description = row.description ?? '';
    service.fee = row.fee;
    service.sla_days = row.sla_days;
    service.required_documents = (row.required_documents ?? []).join('\n');
    service.is_public = row.is_public;
    service.requires_payment = row.requires_payment;
}

function saveService() {
    service.transform((data) => ({ ...data, required_documents: data.required_documents.split('\n').map((line) => line.trim()).filter(Boolean) }));

    if (editingService.value) {
        service.put(`/admin/services/${editingService.value}`, { preserveScroll: true });
    } else {
        service.post('/admin/services', { preserveScroll: true, onSuccess: () => service.reset('name', 'code', 'description') });
    }
}

const filter = ref('');

const visibleServices = computed(() => {
    const needle = filter.value.trim().toLowerCase();

    if (!needle) return props.services;

    return props.services.filter((row) => [row.name, row.code, row.department].filter(Boolean).join(' ').toLowerCase().includes(needle));
});
</script>

<template>
    <AppLayout>
        <Head title="Service catalogue" />

        <div>
            <h1 class="text-xl font-semibold text-slate-900">Service catalogue &amp; departments</h1>
            <p class="text-sm text-slate-500">Published services drive what citizens can file, the SLA clock and the document checklist. Changes apply to new applications only.</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <AppCard title="Departments" :description="`${departments.length} in the directory`">
                <ul class="mb-4 divide-y divide-slate-100">
                    <li v-for="row in departments" :key="row.id" class="flex cursor-pointer items-start justify-between gap-3 py-2.5 hover:bg-slate-50/70" @click="startDepartmentEdit(row)">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">{{ row.name }} <span class="font-mono text-xs text-slate-400">{{ row.code }}</span></p>
                            <p class="text-xs text-slate-500">{{ row.services_count }} published service{{ row.services_count === 1 ? '' : 's' }} · target {{ row.default_sla_days }} days</p>
                            <p v-if="row.description" class="mt-0.5 max-w-prose text-xs text-slate-500">{{ row.description }}</p>
                        </div>
                        <span :class="row.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'" class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase">
                            {{ row.is_active ? 'Active' : 'Off' }}
                        </span>
                    </li>
                </ul>

                <form class="grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2" @submit.prevent="saveDepartment">
                    <Field label="Name" :error="department.errors.name" required>
                        <TextInput v-model="department.name" :invalid="!!department.errors.name" />
                    </Field>
                    <Field label="Code" :error="department.errors.code" hint="Short, uppercase, unique" required>
                        <TextInput v-model="department.code" :invalid="!!department.errors.code" placeholder="HM" />
                    </Field>
                    <Field label="Contact email" :error="department.errors.contact_email">
                        <TextInput v-model="department.contact_email" type="email" :invalid="!!department.errors.contact_email" />
                    </Field>
                    <Field label="Default target (days)" :error="department.errors.default_sla_days">
                        <TextInput v-model="department.default_sla_days" type="number" min="1" max="365" :invalid="!!department.errors.default_sla_days" />
                    </Field>
                    <div class="sm:col-span-2">
                        <Field label="Description" :error="department.errors.description">
                            <TextArea v-model="department.description" :rows="2" :invalid="!!department.errors.description" />
                        </Field>
                    </div>
                    <div class="flex items-center justify-between gap-3 sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input v-model="department.is_active" type="checkbox" class="rounded border-slate-300 text-indigo-600">
                            Accepting cases
                        </label>
                        <div class="flex items-center gap-2">
                            <AppButton v-if="editingDepartment" variant="ghost" type="button" @click="editingDepartment = null">Cancel edit</AppButton>
                            <AppButton :disabled="department.processing">{{ editingDepartment ? 'Save department' : 'Add department' }}</AppButton>
                        </div>
                    </div>
                </form>
            </AppCard>

            <AppCard title="Services">
                <template #actions>
                    <input v-model="filter" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" placeholder="Filter services">
                </template>

                <div class="max-h-80 overflow-y-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 bg-white text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="py-2 pr-3 font-semibold">Service</th>
                                <th class="py-2 pr-3 font-semibold">Target</th>
                                <th class="py-2 pr-3 text-right font-semibold">Cases</th>
                                <th class="py-2 text-right font-semibold">State</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in visibleServices" :key="row.id" class="cursor-pointer hover:bg-slate-50" @click="startServiceEdit(row)">
                                <td class="py-2.5 pr-3">
                                    <p class="font-medium text-slate-800">{{ row.name }}</p>
                                    <p class="font-mono text-[11px] text-slate-400">{{ row.code }} · {{ row.department }}</p>
                                </td>
                                <td class="py-2.5 pr-3 text-slate-600">{{ row.sla_days }}d</td>
                                <td class="py-2.5 pr-3 text-right tabular-nums text-slate-600">{{ row.applications }}</td>
                                <td class="py-2.5 text-right">
                                    <span :class="row.is_public ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'" class="rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase">
                                        {{ row.is_public ? 'Published' : 'Internal' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2" @submit.prevent="saveService">
                    <Field label="Name" :error="service.errors.name" required>
                        <TextInput v-model="service.name" :invalid="!!service.errors.name" />
                    </Field>
                    <Field label="Code" :error="service.errors.code" hint="e.g. HA-BIRTH-COPY" required>
                        <TextInput v-model="service.code" :invalid="!!service.errors.code" />
                    </Field>
                    <Field label="Department" :error="service.errors.department_id" required>
                        <SelectInput v-model="service.department_id" :options="departmentOptions" :invalid="!!service.errors.department_id" />
                    </Field>
                    <div class="grid grid-cols-2 gap-3">
                        <Field label="Fee" :error="service.errors.fee">
                            <TextInput v-model="service.fee" type="number" step="0.01" min="0" :invalid="!!service.errors.fee" />
                        </Field>
                        <Field label="Target days" :error="service.errors.sla_days">
                            <TextInput v-model="service.sla_days" type="number" min="1" max="365" :invalid="!!service.errors.sla_days" />
                        </Field>
                    </div>
                    <div class="sm:col-span-2">
                        <Field label="Description" :error="service.errors.description">
                            <TextArea v-model="service.description" :rows="2" :invalid="!!service.errors.description" />
                        </Field>
                    </div>
                    <div class="sm:col-span-2">
                        <Field label="Required documents" :error="service.errors.required_documents" hint="One per line — shown to the citizen before they file">
                            <TextArea v-model="service.required_documents" :rows="3" :invalid="!!(service.errors.required_documents || service.errors['required_documents.0'])" />
                        </Field>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 sm:col-span-2">
                        <div class="flex gap-4 text-sm text-slate-600">
                            <label class="flex items-center gap-2">
                                <input v-model="service.is_public" type="checkbox" class="rounded border-slate-300 text-indigo-600">
                                Public
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="service.requires_payment" type="checkbox" class="rounded border-slate-300 text-indigo-600">
                                Payment required
                            </label>
                        </div>
                        <div class="flex gap-2">
                            <AppButton v-if="editingService" variant="ghost" type="button" @click="editingService = null">Cancel edit</AppButton>
                            <AppButton :disabled="service.processing">
                                {{ editingService ? 'Save service' : 'Publish service' }}
                            </AppButton>
                        </div>
                    </div>
                </form>
            </AppCard>
        </div>
    </AppLayout>
</template>
