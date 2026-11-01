<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Field from '../../Components/Field.vue';
import ProgressBar from '../../Components/ProgressBar.vue';
import SelectInput from '../../Components/SelectInput.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import TextArea from '../../Components/TextArea.vue';
import TextInput from '../../Components/TextInput.vue';
import Timeline from '../../Components/Timeline.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    application: { type: Object, required: true },
    officers: { type: Array, default: () => [] },
    pollAfter: { type: Number, default: 15 },
});

const page = usePage();
const staff = computed(() => page.props.can?.is_admin || page.props.can?.is_officer);
const app = computed(() => props.application);
const docs = computed(() => app.value.documents ?? []);

const live = ref(true);
const refreshing = ref(false);
const lastSync = ref(new Date());
let timer = null;

function sync() {
    if (!live.value || document.visibilityState !== 'visible') return;

    refreshing.value = true;
    router.reload({
        only: ['application'],
        preserveScroll: true,
        onSuccess: () => {
            lastSync.value = new Date();
        },
        onFinish: () => {
            refreshing.value = false;
        },
    });
}

onMounted(() => {
    timer = setInterval(sync, Math.max(5, props.pollAfter) * 1000);
});

onBeforeUnmount(() => clearInterval(timer));

// --- status transition -----------------------------------------------------
const transition = useForm({ status: '', note: '', internal_only: false });

function move(toCode) {
    if (!window.confirm(`Move this case to “${toCode.replace(/_/g, ' ')}”? The change is recorded in the audit trail.`)) return;

    transition.status = toCode;
    transition.post(`/applications/${app.value.id}/status`, {
        preserveScroll: true,
        onSuccess: () => {
            transition.reset('note');
            sync();
        },
        onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    });
}

// --- assignment ------------------------------------------------------------
const assign = useForm({ officer_id: '' });

function saveAssignment() {
    assign.post(`/applications/${app.value.id}/assign`, {
        preserveScroll: true,
        onSuccess: sync,
    });
}

// --- documents -------------------------------------------------------------
const upload = useForm({ label: '', file: null });
const fileInput = ref(null);

function pickFile(event) {
    upload.file = event.target.files?.[0] ?? null;
    if (!upload.label && upload.file) upload.label = upload.file.name.replace(/\.[^.]+$/, '');
}

function sendDocument() {
    upload.post(`/applications/${app.value.id}/documents`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            upload.reset();
            if (fileInput.value) fileInput.value.value = '';
            sync();
        },
    });
}

const review = useForm({ status: 'accepted', review_note: '' });

function decide(entry, status) {
    let note = '';

    if (status === 'rejected') {
        note = window.prompt('Why is this document rejected? The citizen sees this note.', '') ?? '';

        if (note.trim().length < 5) {
            window.alert('A rejection needs at least 5 characters of explanation.');
            return;
        }
    }

    review.status = status;
    review.review_note = note;
    review.post(`/documents/${entry.id}/review`, {
        preserveScroll: true,
        onSuccess: sync,
    });
}

// --- messages --------------------------------------------------------------
const comment = useForm({ body: '', is_internal: false });

function sendComment() {
    comment.post(`/applications/${app.value.id}/comments`, {
        preserveScroll: true,
        onSuccess: () => {
            comment.reset('body');
            sync();
        },
    });
}

function withdraw() {
    if (!window.confirm(`Withdraw ${app.value.reference}? The record stays in the audit trail.`)) return;

    router.delete(`/applications/${app.value.id}`, { preserveScroll: true });
}

const statusLabel = (code) => code.replace(/_/g, ' ');
</script>

<template>
    <AppLayout>
        <Head :title="app.reference" />

        <div class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <Link href="/applications" class="text-xs font-semibold uppercase tracking-wide text-slate-400 hover:text-slate-600">
                        ← {{ staff ? 'Back to the register' : 'My applications' }}
                    </Link>
                    <h1 class="mt-1 text-xl font-semibold text-slate-900">{{ app.title }}</h1>
                    <p class="mt-0.5 text-sm text-slate-500">
                        <span class="font-mono">{{ app.reference }}</span> · {{ app.service }} · {{ app.department }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold"
                        :class="live ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-500'"
                        @click="live = !live"
                    >
                        <span :class="['h-1.5 w-1.5 rounded-full', live ? 'animate-pulse bg-emerald-500' : 'bg-slate-400']" />
                        {{ live ? `Live · synced ${lastSync.toLocaleTimeString()}` : 'Live updates paused' }}
                    </button>
                    <AppButton variant="secondary" type="button" :disabled="refreshing" @click="sync">
                        {{ refreshing ? 'Refreshing…' : 'Refresh' }}
                    </AppButton>
                </div>
            </div>

            <AppCard>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <StatusBadge :status="app.status" size="lg" />
                        <span class="text-sm text-slate-500">Stage {{ app.stage }} · {{ app.progress }}% of the workflow</span>
                        <span v-if="app.priority !== 'normal'" class="rounded bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-700">
                            {{ app.priority }} priority
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-4 text-xs text-slate-500">
                        <span>Filed <strong class="text-slate-700">{{ app.submitted_at }}</strong></span>
                        <span>
                            Target <strong :class="app.is_overdue ? 'text-rose-600' : 'text-slate-700'">{{ app.due_at }}</strong>
                            <template v-if="app.days_remaining !== null">
                                ({{ app.days_remaining < 0 ? `${Math.abs(app.days_remaining)} days late` : `${app.days_remaining} days left` }})
                            </template>
                        </span>
                        <span v-if="app.officer">Officer <strong class="text-slate-700">{{ app.officer }}</strong></span>
                    </div>
                </div>
                <div class="mt-4">
                    <ProgressBar :value="app.progress" />
                </div>
            </AppCard>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
            <div class="space-y-4">
                <AppCard title="Status history" :description="staff ? 'Every movement is signed and timestamped.' : 'Movements the department chose to publish.'">
                    <EmptyState v-if="!app.timeline?.length" title="No movements recorded yet" />
                    <Timeline v-else :items="app.timeline" :show-internal="staff" />
                </AppCard>

                <AppCard title="Conversation" :description="staff ? 'Tick “internal note” to keep a message with the department only.' : 'Messages from the department appear here.'">
                    <form v-if="app.can.comment" class="mb-5 space-y-3" @submit.prevent="sendComment">
                        <Field :error="comment.errors.body">
                            <TextArea v-model="comment.body" :rows="3" placeholder="Add a message for the case officer…" :invalid="!!comment.errors.body" />
                        </Field>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <label v-if="staff" class="flex items-center gap-2 text-sm text-slate-600">
                                <input v-model="comment.is_internal" type="checkbox" class="rounded border-slate-300 text-indigo-600">
                                Internal note (hidden from the citizen)
                            </label>
                            <AppButton class="sm:ml-auto" :disabled="comment.processing || comment.body.trim().length < 3">
                                {{ comment.processing ? 'Sending…' : 'Send message' }}
                            </AppButton>
                        </div>
                    </form>

                    <EmptyState v-if="!app.comments?.length" title="No messages yet" description="Ask a question here and the department answers on the same case." />
                    <ul v-else class="space-y-4">
                        <li v-for="entry in app.comments" :key="entry.id" class="rounded-lg border border-slate-100 bg-slate-50/60 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                <p class="font-semibold text-slate-800">
                                    {{ entry.author }}
                                    <span class="ml-1 rounded bg-white px-1.5 py-0.5 font-semibold uppercase tracking-wide text-slate-500">{{ entry.role }}</span>
                                    <span v-if="entry.is_internal" class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 font-semibold uppercase tracking-wide text-amber-700">internal</span>
                                </p>
                                <span class="text-slate-400">{{ entry.created_at }}</span>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ entry.body }}</p>
                        </li>
                    </ul>
                </AppCard>
            </div>

            <div class="space-y-4">
                <AppCard v-if="app.actions?.length" title="Move this case" description="Allowed next states for your role — anything else is refused by the workflow engine.">
                    <div class="space-y-3">
                        <Field label="Note for the record" for="note" :error="transition.errors.note" hint="Shown to the citizen unless marked internal">
                            <TextArea id="note" v-model="transition.note" :rows="2" :invalid="!!transition.errors.note" />
                        </Field>
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input v-model="transition.internal_only" type="checkbox" class="rounded border-slate-300 text-indigo-600">
                            Keep this note internal
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <AppButton
                                v-for="action in app.actions"
                                :key="action.code"
                                type="button"
                                :variant="['rejected', 'cancelled'].includes(action.code) ? 'danger' : 'primary'"
                                :disabled="transition.processing"
                                @click="move(action.code)"
                            >
                                {{ statusLabel(action.name) }}
                            </AppButton>
                        </div>
                    </div>
                </AppCard>

                <AppCard v-else-if="app.closed_at" title="Closed" description="This case reached its final state; the trail is sealed.">
                    <p class="text-sm text-slate-600">Closed on {{ app.closed_at.slice(0, 10) }}.</p>
                </AppCard>

                <AppCard v-if="staff && app.can.assign" title="Assignment" description="Hand the case to another officer — recorded as an audit event.">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="min-w-[12rem] flex-1">
                            <SelectInput
                                v-model="assign.officer_id"
                                :options="officers.map((o) => ({ value: String(o.id), label: `${o.name} — ${o.open} open` }))"
                                placeholder="Choose an officer"
                                :invalid="!!assign.errors.officer_id"
                            />
                        </div>
                        <AppButton :disabled="!assign.officer_id || assign.processing" @click="saveAssignment">Assign</AppButton>
                    </div>
                    <p v-if="assign.errors.officer_id" class="mt-2 text-xs font-medium text-rose-600">{{ assign.errors.officer_id }}</p>
                </AppCard>

                <AppCard title="Documents" :description="`${docs.length} file(s) stored on a private disk, checksummed on upload.`">
                    <ul v-if="docs.length" class="mb-4 space-y-2">
                        <li v-for="document in docs" :key="document.id" class="rounded-lg border border-slate-100 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-800">{{ document.label }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ document.name }} · {{ document.size_kb }} KB · sha256 {{ document.checksum }}…</p>
                                    <p class="text-xs text-slate-400">Uploaded by {{ document.uploaded_by }} {{ document.created_at }}</p>
                                </div>
                                <span
                                    :class="[
                                        'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase',
                                        document.status === 'accepted' ? 'bg-emerald-50 text-emerald-700'
                                            : document.status === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700',
                                    ]"
                                >
                                    {{ document.status_label }}
                                </span>
                            </div>
                            <p v-if="document.review_note" class="mt-2 rounded bg-slate-50 px-2 py-1 text-xs text-slate-600">
                                Officer note: {{ document.review_note }}
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <a
                                    :href="document.download_url"
                                    class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                >
                                    Download
                                </a>
                                <template v-if="staff">
                                    <button type="button" class="rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-500" @click="decide(document, 'accepted')">
                                        Accept
                                    </button>
                                    <button type="button" class="rounded-md bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-rose-500" @click="decide(document, 'rejected')">
                                        Reject
                                    </button>
                                </template>
                            </div>
                        </li>
                    </ul>

                    <form v-if="app.can.upload" class="space-y-3 border-t border-slate-100 pt-4" @submit.prevent="sendDocument">
                        <Field label="What is this document?" for="doc-label" :error="upload.errors.label" required>
                            <TextInput id="doc-label" v-model="upload.label" :invalid="!!upload.errors.label" placeholder="National ID card" />
                        </Field>
                        <Field :error="upload.errors.file" hint="PDF, JPG, PNG or DOCX up to the configured limit">
                            <input ref="fileInput" type="file" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100" @change="pickFile">
                        </Field>
                        <AppButton :disabled="upload.processing || !upload.file">
                            {{ upload.processing ? 'Uploading…' : 'Upload document' }}
                        </AppButton>
                    </form>

                    <ul v-if="app.required_documents?.length" class="mt-4 space-y-1 border-t border-slate-100 pt-3">
                        <li class="text-xs font-semibold uppercase tracking-wide text-slate-500">Required for this service</li>
                        <li v-for="label in app.required_documents" :key="label" class="flex items-center gap-2 text-sm" :class="docs.some((d) => d.status === 'accepted') ? 'text-slate-600' : 'text-slate-700'">
                            <span :class="['h-1.5 w-1.5 rounded-full', docs.length ? 'bg-emerald-400' : 'bg-slate-300']" />
                            {{ label }}
                        </li>
                    </ul>
                </AppCard>

                <AppCard title="Applicant" :description="staff ? 'Contact details from the submission.' : 'Your details on this case.'">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Name</dt>
                            <dd class="text-right font-medium text-slate-800">{{ app.applicant }}</dd>
                        </div>
                        <div v-if="staff && app.citizen" class="flex justify-between gap-3">
                            <dt class="text-slate-500">Account</dt>
                            <dd class="text-right text-slate-700">{{ app.citizen.email }}<br><span class="font-mono text-xs">{{ app.citizen.national_id }}</span></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Phone</dt>
                            <dd class="text-right text-slate-700">{{ app.applicant_phone }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Address</dt>
                            <dd class="max-w-[12rem] text-right text-slate-700">{{ app.applicant_address }}</dd>
                        </div>
                        <div v-if="app.service_fee !== null" class="flex justify-between gap-3">
                            <dt class="text-slate-500">Fee</dt>
                            <dd class="text-right text-slate-700">{{ app.service_fee.toFixed(2) }}</dd>
                        </div>
                    </dl>
                    <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ app.description }}</p>
                </AppCard>

                <AppCard v-if="app.can.withdraw" title="Withdraw">
                    <p class="mb-3 text-xs text-slate-500">Only possible while the case has not been decided. The tracking number stays searchable.</p>
                    <AppButton variant="danger" type="button" @click="withdraw">Withdraw application</AppButton>
                </AppCard>
            </div>
        </div>
    </AppLayout>
</template>
