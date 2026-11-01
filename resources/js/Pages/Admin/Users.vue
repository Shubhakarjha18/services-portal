<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Field from '../../Components/Field.vue';
import Pagination from '../../Components/Pagination.vue';
import SelectInput from '../../Components/SelectInput.vue';
import TextInput from '../../Components/TextInput.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
});

const query = reactive({
    q: props.filters.q ?? '',
    role: props.filters.role ?? '',
    department: props.filters.department ? String(props.filters.department) : '',
    status: props.filters.status ?? '',
});

watch(query, () => {
    router.get('/admin/users', { ...query }, { preserveState: true, preserveScroll: true, replace: true });
});

const create = useForm({
    name: '',
    email: '',
    password: '',
    role_id: String(props.roles.find((r) => r.name === 'officer')?.id ?? props.roles[0]?.id ?? ''),
    department_id: '',
    phone: '',
    national_id: '',
    is_active: true,
});

const editing = ref(null);
const edit = useForm({ name: '', email: '', phone: '', national_id: '', password: '', role_id: '', department_id: '', is_active: true });

function startEdit(user) {
    editing.value = user.id;
    edit.name = user.name;
    edit.email = user.email;
    edit.phone = user.phone ?? '';
    edit.national_id = user.national_id ?? '';
    edit.password = '';
    edit.role_id = String(props.roles.find((r) => r.name === user.role)?.id ?? '');
    edit.department_id = user.department_id ? String(user.department_id) : '';
    edit.is_active = user.is_active;
}

function saveEdit() {
    // Empty strings arrive as null, and `sometimes` would still validate them.
    edit.transform((data) => {
        const payload = { ...data };

        if (!payload.password) delete payload.password;

        return payload;
    });

    edit.put(`/admin/users/${editing.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}

const selectedRole = () => props.roles.find((role) => String(role.id) === String(create.role_id));
const roleIsStaff = () => ['admin', 'officer'].includes(selectedRole()?.name);
</script>

<template>
    <AppLayout>
        <Head title="User administration" />

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">User administration</h1>
                <p class="text-sm text-slate-500">Roles decide what an account can see; permissions decide what it can do. Changing an account revokes its tokens.</p>
            </div>
        </div>

        <AppCard compact>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="uq">Search</label>
                    <TextInput id="uq" v-model="query.q" placeholder="Name or email" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="urole">Role</label>
                    <SelectInput id="urole" v-model="query.role" :options="roles.map((r) => ({ value: r.name, label: r.label }))" placeholder="All roles" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="udept">Department</label>
                    <SelectInput id="udept" v-model="query.department" :options="departments.map((d) => ({ value: String(d.id), label: d.name }))" placeholder="All departments" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="ustatus">Status</label>
                    <SelectInput id="ustatus" v-model="query.status" :options="[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Deactivated' }]" placeholder="Any" />
                </div>
            </div>
        </AppCard>

        <AppCard title="Accounts" :description="`${users.total} matching`">
            <EmptyState v-if="!users.data.length" title="No account matches these filters" />
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Name</th>
                            <th class="py-2 pr-4 font-semibold">Role</th>
                            <th class="py-2 pr-4 font-semibold">Department</th>
                            <th class="py-2 pr-4 font-semibold">Last sign-in</th>
                            <th class="py-2 pr-4 font-semibold">State</th>
                            <th class="py-2 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template v-for="user in users.data" :key="user.id">
                            <tr>
                                <td class="py-3 pr-4">
                                    <p class="font-medium text-slate-800">{{ user.name }} <span v-if="user.is_self" class="text-xs text-slate-400">(you)</span></p>
                                    <p class="text-xs text-slate-500">{{ user.email }}</p>
                                    <p v-if="user.national_id" class="font-mono text-[11px] text-slate-400">{{ user.national_id }}</p>
                                </td>
                                <td class="py-3 pr-4">
                                    <span class="rounded px-2 py-0.5 text-xs font-semibold" :class="user.role === 'admin' ? 'bg-indigo-50 text-indigo-700' : user.role === 'officer' ? 'bg-teal-50 text-teal-700' : 'bg-slate-100 text-slate-600'">
                                        {{ user.role_label }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-slate-600">{{ user.department ?? '—' }}</td>
                                <td class="py-3 pr-4 text-xs text-slate-500">{{ user.last_login_at ?? 'never' }}</td>
                                <td class="py-3 pr-4">
                                    <span :class="user.is_active ? 'text-emerald-600' : 'text-rose-600'" class="text-xs font-semibold uppercase">
                                        {{ user.is_active ? 'Active' : 'Disabled' }}
                                    </span>
                                </td>
                                <td class="py-3 text-right">
                                    <AppButton variant="secondary" size="sm" type="button" @click="startEdit(user)">Manage</AppButton>
                                </td>
                            </tr>
                            <tr v-if="editing === user.id">
                                <td colspan="6" class="bg-slate-50/70 px-3 py-4">
                                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                        <Field label="Name" :error="edit.errors.name">
                                            <TextInput v-model="edit.name" :invalid="!!edit.errors.name" />
                                        </Field>
                                        <Field label="Email" :error="edit.errors.email">
                                            <TextInput v-model="edit.email" type="email" :invalid="!!edit.errors.email" />
                                        </Field>
                                        <Field label="Role" :error="edit.errors.role_id">
                                            <SelectInput v-model="edit.role_id" :options="roles.map((r) => ({ value: String(r.id), label: r.label }))" :invalid="!!edit.errors.role_id" />
                                        </Field>
                                        <Field label="Department" :error="edit.errors.department_id" hint="Required for staff roles">
                                            <SelectInput v-model="edit.department_id" :options="departments.map((d) => ({ value: String(d.id), label: d.name }))" placeholder="None" :invalid="!!edit.errors.department_id" />
                                        </Field>
                                        <Field label="Phone" :error="edit.errors.phone">
                                            <TextInput v-model="edit.phone" :invalid="!!edit.errors.phone" />
                                        </Field>
                                        <Field label="Citizen number" :error="edit.errors.national_id">
                                            <TextInput v-model="edit.national_id" maxlength="10" :invalid="!!edit.errors.national_id" />
                                        </Field>
                                        <Field label="New password" :error="edit.errors.password" hint="Leave empty to keep it">
                                            <TextInput v-model="edit.password" type="password" :invalid="!!edit.errors.password" />
                                        </Field>
                                        <div class="flex items-end">
                                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                                <input v-model="edit.is_active" type="checkbox" class="rounded border-slate-300 text-indigo-600">
                                                Account active
                                            </label>
                                        </div>
                                    </div>
                                    <div class="mt-3 flex justify-end gap-2">
                                        <AppButton variant="ghost" type="button" @click="editing = null">Close</AppButton>
                                        <AppButton :disabled="edit.processing" @click="saveEdit">{{ edit.processing ? 'Saving…' : 'Save changes' }}</AppButton>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500">Saving issues an audit entry (<code>user_updated</code>) with the before and after values.</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="users.links" :meta="{ total: users.total, page: users.page, last_page: users.last_page }" />
            </div>
        </AppCard>

        <AppCard title="Create an account" description="Staff need a department; citizens need a citizen number.">
            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="create.post('/admin/users', { preserveScroll: true, onSuccess: () => create.reset('password', 'national_id') })">
                <Field label="Name" :error="create.errors.name" required>
                    <TextInput v-model="create.name" :invalid="!!create.errors.name" />
                </Field>
                <Field label="Email" :error="create.errors.email" required>
                    <TextInput v-model="create.email" type="email" :invalid="!!create.errors.email" />
                </Field>
                <Field label="Password" :error="create.errors.password" hint="8+ chars, letters and digits" required>
                    <TextInput v-model="create.password" type="password" :invalid="!!create.errors.password" />
                </Field>
                <Field label="Role" :error="create.errors.role_id" required>
                    <SelectInput v-model="create.role_id" :options="roles.map((r) => ({ value: String(r.id), label: r.label }))" :invalid="!!create.errors.role_id" />
                </Field>
                <Field v-if="roleIsStaff()" label="Department" :error="create.errors.department_id" required>
                    <SelectInput v-model="create.department_id" :options="departments.map((d) => ({ value: String(d.id), label: d.name }))" placeholder="Pick one" :invalid="!!create.errors.department_id" />
                </Field>
                <Field v-else label="Citizen number" :error="create.errors.national_id" hint="10 digits">
                    <TextInput v-model="create.national_id" maxlength="10" :invalid="!!create.errors.national_id" />
                </Field>
                <Field label="Phone" :error="create.errors.phone">
                    <TextInput v-model="create.phone" :invalid="!!create.errors.phone" />
                </Field>
                <div class="flex items-end">
                    <AppButton :disabled="create.processing">{{ create.processing ? 'Creating…' : 'Create account' }}</AppButton>
                </div>
            </form>
        </AppCard>
    </AppLayout>
</template>
