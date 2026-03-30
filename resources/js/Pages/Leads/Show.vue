<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import DealCard from '@/Components/DealCard.vue'
import ActivityFeed from '@/Components/ActivityFeed.vue'

const props = defineProps({
    lead: {
        type: Object,
        required: true,
    },
    availableStages: {
        type: Array,
        default: () => [],
    },
    relatedDeals: {
        type: Array,
        default: () => [],
    },
    referenceData: {
        type: Object,
        default: () => ({
            sources: [],
            requestTypes: [],
            branches: [],
        }),
    },
})

const isEditing = ref(false)
const form = useForm({
    name: props.lead.name ?? '',
    phone: props.lead.phone ?? '',
    source: props.lead.source ?? '',
    request_type: props.lead.request_type ?? '',
    branch: props.lead.branch ?? '',
})

function fillForm() {
    form.name = props.lead.name ?? ''
    form.phone = props.lead.phone ?? ''
    form.source = props.lead.source ?? ''
    form.request_type = props.lead.request_type ?? ''
    form.branch = props.lead.branch ?? ''
}

function startEditing() {
    fillForm()
    form.clearErrors()
    isEditing.value = true
}

function cancelEditing() {
    fillForm()
    form.clearErrors()
    isEditing.value = false
}

function submit() {
    form.patch(`/leads/${props.lead.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false
        },
    })
}
</script>

<template>
    <Head :title="lead.name || `Lead #${lead.id}`" />

    <AppLayout
        :title="lead.name || `Lead #${lead.id}`"
        subtitle="Lead page with qualification details, current stage, tasks, history, and related deals."
    >
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(20rem,0.75fr)]">
            <section class="space-y-6">
                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="crm-kicker">Lead</p>
                            <h3 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">{{ lead.name || `Lead #${lead.id}` }}</h3>
                            <p class="mt-3 text-sm text-slate-500">Created: {{ lead.created_at || 'unknown' }} · Updated: {{ lead.updated_at || 'unknown' }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <div v-if="lead.stage" class="crm-pill">
                                <span class="crm-stage-dot" :style="{ backgroundColor: lead.stage.color || '#94a3b8' }"></span>
                                <span>{{ lead.stage.name }}</span>
                            </div>
                            <button v-if="!isEditing" type="button" class="crm-button-ghost" @click="startEditing">Edit</button>
                            <button v-else type="button" class="crm-button-ghost" @click="cancelEditing">Cancel</button>
                        </div>
                    </div>

                    <form v-if="isEditing" class="mt-6 grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                        <div>
                            <label class="crm-label" for="lead-name">Name</label>
                            <input id="lead-name" v-model="form.name" type="text" class="crm-input" />
                            <p v-if="form.errors.name" class="mt-2 text-sm text-rose-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-phone">Phone</label>
                            <input id="lead-phone" v-model="form.phone" type="text" class="crm-input" />
                            <p v-if="form.errors.phone" class="mt-2 text-sm text-rose-600">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-source">Source</label>
                            <select id="lead-source" v-model="form.source" class="crm-select">
                                <option value="">Not selected</option>
                                <option v-for="option in referenceData.sources" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.source" class="mt-2 text-sm text-rose-600">{{ form.errors.source }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-request-type">Request Type</label>
                            <select id="lead-request-type" v-model="form.request_type" class="crm-select">
                                <option value="">Not selected</option>
                                <option v-for="option in referenceData.requestTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.request_type" class="mt-2 text-sm text-rose-600">{{ form.errors.request_type }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-branch">Branch</label>
                            <select id="lead-branch" v-model="form.branch" class="crm-select">
                                <option value="">Not selected</option>
                                <option v-for="option in referenceData.branches" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.branch" class="mt-2 text-sm text-rose-600">{{ form.errors.branch }}</p>
                        </div>
                        <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
                            <button type="submit" class="crm-button" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save changes' }}</button>
                            <button type="button" class="crm-button-ghost" @click="cancelEditing">Discard</button>
                        </div>
                    </form>

                    <div v-else class="crm-data-grid mt-6">
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Phone</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.phone || 'Not specified' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Source</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.source || 'Not specified' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Request Type</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.request_type || 'Not specified' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Branch</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.branch || 'Not selected' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Owner</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.user || 'Not assigned' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Quality</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.quality || 'Not set' }}</p>
                        </div>
                    </div>
                </div>

                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">Stages</p>
                            <h3 class="crm-section-title mt-2">Lead pipeline</h3>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="stage in availableStages"
                            :key="stage.id"
                            class="rounded-[1.15rem] border p-4"
                            :class="stage.is_current ? 'border-orange-300 bg-orange-50/80 shadow-lg' : 'border-slate-900/8 bg-white/72'"
                        >
                            <div class="flex items-center gap-3">
                                <span class="crm-stage-dot" :style="{ backgroundColor: stage.color || '#94a3b8' }"></span>
                                <div>
                                    <p class="font-semibold text-slate-950">{{ stage.name }}</p>
                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">
                                        {{ stage.is_current ? 'current' : stage.is_final ? 'final' : 'active' }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <ActivityFeed :items="lead.history" title="Stage history" empty-text="No stage history yet." />
            </section>

            <section class="space-y-6">
                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">Contact</p>
                            <h3 class="crm-section-title mt-2">Linked customer data</h3>
                        </div>
                    </div>

                    <div v-if="lead.contact" class="mt-6 space-y-3 text-sm text-slate-600">
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Name</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.contact.name }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Phone</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.contact.phone || 'Not specified' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Email</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.contact.email || 'Not specified' }}</p>
                        </div>
                    </div>
                    <div v-else class="crm-empty mt-6">No contact is linked to this lead yet.</div>
                </div>

                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">Tasks</p>
                            <h3 class="crm-section-title mt-2">What needs to happen next</h3>
                        </div>
                        <p class="text-sm text-slate-500">{{ lead.tasks.length }} pcs.</p>
                    </div>

                    <div v-if="lead.tasks.length" class="mt-6 space-y-3">
                        <article v-for="task in lead.tasks" :key="task.id" class="rounded-[1.1rem] border border-slate-900/8 bg-white/72 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-950">{{ task.title }}</p>
                                    <p class="mt-1 text-sm text-slate-600">{{ task.type }} · {{ task.status }}</p>
                                </div>
                                <span class="crm-pill">{{ task.due_at || 'No due date' }}</span>
                            </div>
                            <p v-if="task.description" class="mt-3 text-sm leading-6 text-slate-600">{{ task.description }}</p>
                            <p class="mt-3 text-sm text-slate-500">{{ task.user || 'No owner' }} · {{ task.due_relative || 'Date not specified' }}</p>
                        </article>
                    </div>
                    <div v-else class="crm-empty mt-6">There are no tasks for this lead yet.</div>
                </div>

                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">After conversion</p>
                            <h3 class="crm-section-title mt-2">Related deals</h3>
                        </div>
                        <Link href="/deals" class="crm-button-ghost">All deals</Link>
                    </div>

                    <div v-if="relatedDeals.length" class="mt-6 grid gap-4">
                        <DealCard v-for="deal in relatedDeals" :key="deal.id" :deal="deal" />
                    </div>
                    <div v-else class="crm-empty mt-6">This lead has not been converted into a deal yet.</div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>