<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DealCard from '@/Components/DealCard.vue'
import ActivityFeed from '@/Components/ActivityFeed.vue'
import PipelineStagesEditor from '@/Components/PipelineStagesEditor.vue'

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
    dealOptions: {
        type: Array,
        default: () => [],
    },
    referenceData: {
        type: Object,
        default: () => ({
            sources: [],
            requestTypes: [],
            qualities: [],
            branches: [],
            taskTypes: [],
        }),
    },
})

const isEditing = ref(false)

const leadForm = useForm({
    name: props.lead.name ?? '',
    phone: props.lead.phone ?? '',
    source: props.lead.source_value ?? '',
    request_type: props.lead.request_type_value ?? '',
    quality: props.lead.quality_value ?? '',
    branch: props.lead.branch_value ?? '',
})

const contactForm = useForm({
    first_name: props.lead.contact?.first_name ?? '',
    last_name: props.lead.contact?.last_name ?? '',
    phone: props.lead.contact?.phone ?? props.lead.phone ?? '',
    email: props.lead.contact?.email ?? '',
})

const linkDealForm = useForm({
    deal_id: '',
})

const taskForm = useForm({
    title: '',
    description: '',
    type: props.referenceData.taskTypes[0]?.value ?? 'call',
    due_at: '',
})

const deleteForm = useForm({})

const dealSelectOptions = computed(() =>
    props.dealOptions.map((option) => ({
        ...option,
        title: option.lead_id && option.lead_id !== props.lead.id
            ? `${option.label} · сейчас привязана к лиду: ${option.lead_name || `#${option.lead_id}`}`
            : option.label,
    })),
)

function fillLeadForm() {
    leadForm.name = props.lead.name ?? ''
    leadForm.phone = props.lead.phone ?? ''
    leadForm.source = props.lead.source_value ?? ''
    leadForm.request_type = props.lead.request_type_value ?? ''
    leadForm.quality = props.lead.quality_value ?? ''
    leadForm.branch = props.lead.branch_value ?? ''
}

function startEditing() {
    fillLeadForm()
    leadForm.clearErrors()
    isEditing.value = true
}

function cancelEditing() {
    fillLeadForm()
    leadForm.clearErrors()
    isEditing.value = false
}

function submitLead() {
    leadForm.patch(`/leads/${props.lead.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false
        },
    })
}

function submitContact() {
    contactForm.post(`/leads/${props.lead.id}/contact`, {
        preserveScroll: true,
    })
}

function submitLinkedDeal() {
    linkDealForm.patch(`/leads/${props.lead.id}/deal`, {
        preserveScroll: true,
        onSuccess: () => {
            linkDealForm.reset('deal_id')
        },
    })
}

function submitTask() {
    taskForm.post(`/leads/${props.lead.id}/tasks`, {
        preserveScroll: true,
        onSuccess: () => {
            taskForm.reset()
            taskForm.type = props.referenceData.taskTypes[0]?.value ?? 'call'
        },
    })
}

function destroyLead() {
    if (!window.confirm('Удалить лид? Это действие можно будет отменить только через восстановление из базы.')) {
        return
    }

    deleteForm.delete(`/leads/${props.lead.id}`)
}
</script>

<template>
    <Head :title="lead.name || `Лид #${lead.id}`" />

    <AppLayout
        :title="lead.name || `Лид #${lead.id}`"
        subtitle="Страница лида с деталями квалификации, текущим этапом, контактом клиента, задачами и привязанными сделками."
    >
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(20rem,0.75fr)]">
            <section class="space-y-6">
                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="crm-kicker">Лид</p>
                            <h3 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">{{ lead.name || `Лид #${lead.id}` }}</h3>
                            <p class="mt-3 text-sm text-slate-500">Создано: {{ lead.created_at || 'неизвестно' }} · Обновлено: {{ lead.updated_at || 'неизвестно' }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 sm:shrink-0 sm:justify-end">
                            <div v-if="lead.stage" class="crm-pill">
                                <span class="crm-stage-dot" :style="{ backgroundColor: lead.stage.color || '#94a3b8' }"></span>
                                <span>{{ lead.stage.name }}</span>
                            </div>
                            <button v-if="!isEditing" type="button" class="crm-button-ghost shrink-0" @click="startEditing">Редактировать</button>
                            <button v-else type="button" class="crm-button-ghost shrink-0" @click="cancelEditing">Отмена</button>
                            <button type="button" class="crm-button-ghost shrink-0 border-rose-200 text-rose-600 hover:border-rose-300 hover:text-rose-700" :disabled="deleteForm.processing" @click="destroyLead">
                                {{ deleteForm.processing ? 'Удаление...' : 'Удалить' }}
                            </button>
                        </div>
                    </div>

                    <form v-if="isEditing" class="mt-6 grid gap-4 md:grid-cols-2" @submit.prevent="submitLead">
                        <div>
                            <label class="crm-label" for="lead-name">Название лида</label>
                            <input id="lead-name" v-model="leadForm.name" type="text" class="crm-input" />
                            <p v-if="leadForm.errors.name" class="mt-2 text-sm text-rose-600">{{ leadForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-phone">Телефон</label>
                            <input id="lead-phone" v-model="leadForm.phone" type="text" class="crm-input" />
                            <p v-if="leadForm.errors.phone" class="mt-2 text-sm text-rose-600">{{ leadForm.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-source">Источник</label>
                            <select id="lead-source" v-model="leadForm.source" class="crm-select">
                                <option value="">Не выбран</option>
                                <option v-for="option in referenceData.sources" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="leadForm.errors.source" class="mt-2 text-sm text-rose-600">{{ leadForm.errors.source }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-request-type">Тип запроса</label>
                            <select id="lead-request-type" v-model="leadForm.request_type" class="crm-select">
                                <option value="">Не выбран</option>
                                <option v-for="option in referenceData.requestTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="leadForm.errors.request_type" class="mt-2 text-sm text-rose-600">{{ leadForm.errors.request_type }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-branch">Филиал</label>
                            <select id="lead-branch" v-model="leadForm.branch" class="crm-select">
                                <option value="">Не выбран</option>
                                <option v-for="option in referenceData.branches" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="leadForm.errors.branch" class="mt-2 text-sm text-rose-600">{{ leadForm.errors.branch }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-quality">Качество</label>
                            <select id="lead-quality" v-model="leadForm.quality" class="crm-select">
                                <option value="">Не задано</option>
                                <option v-for="option in referenceData.qualities" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="leadForm.errors.quality" class="mt-2 text-sm text-rose-600">{{ leadForm.errors.quality }}</p>
                        </div>
                        <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
                            <button type="submit" class="crm-button" :disabled="leadForm.processing">{{ leadForm.processing ? 'Сохранение...' : 'Сохранить изменения' }}</button>
                            <button type="button" class="crm-button-ghost" @click="cancelEditing">Отменить</button>
                        </div>
                    </form>

                    <div v-else class="crm-data-grid mt-6">
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Телефон</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.phone || 'Не указан' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Источник</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.source || 'Не указан' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Тип запроса</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.request_type || 'Не указан' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Филиал</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.branch || 'Не выбран' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Ответственный</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.user || 'Не назначен' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Качество</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.quality || 'Не задано' }}</p>
                        </div>
                    </div>
                </div>

                <PipelineStagesEditor title="Воронка лида" :stages="availableStages" />

                <ActivityFeed :items="lead.history" title="История этапов" empty-text="История этапов пока пуста." />
            </section>

            <section class="space-y-6">
                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">Контакт</p>
                            <h3 class="crm-section-title mt-2">Данные клиента</h3>
                        </div>
                    </div>

                    <form class="mt-6 grid gap-4 md:grid-cols-2" @submit.prevent="submitContact">
                        <div>
                            <label class="crm-label" for="lead-contact-first-name">Имя</label>
                            <input id="lead-contact-first-name" v-model="contactForm.first_name" type="text" class="crm-input" />
                            <p v-if="contactForm.errors.first_name" class="mt-2 text-sm text-rose-600">{{ contactForm.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-contact-last-name">Фамилия</label>
                            <input id="lead-contact-last-name" v-model="contactForm.last_name" type="text" class="crm-input" />
                            <p v-if="contactForm.errors.last_name" class="mt-2 text-sm text-rose-600">{{ contactForm.errors.last_name }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-contact-phone">Телефон</label>
                            <input id="lead-contact-phone" v-model="contactForm.phone" type="text" class="crm-input" />
                            <p v-if="contactForm.errors.phone" class="mt-2 text-sm text-rose-600">{{ contactForm.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-contact-email">Email</label>
                            <input id="lead-contact-email" v-model="contactForm.email" type="email" class="crm-input" />
                            <p v-if="contactForm.errors.email" class="mt-2 text-sm text-rose-600">{{ contactForm.errors.email }}</p>
                        </div>
                        <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
                            <button type="submit" class="crm-button" :disabled="contactForm.processing">{{ contactForm.processing ? 'Сохранение...' : (lead.contact ? 'Обновить контакт' : 'Добавить контакт') }}</button>
                        </div>
                    </form>

                    <div v-if="lead.contact" class="mt-6 rounded-[1.1rem] border border-slate-900/8 bg-white/72 p-4 text-sm text-slate-600">
                        <p class="font-semibold text-slate-950">{{ lead.contact.name }}</p>
                        <p class="mt-2">{{ lead.contact.phone || 'Телефон не указан' }}</p>
                        <p>{{ lead.contact.email || 'Email не указан' }}</p>
                    </div>
                </div>

                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">Задачи</p>
                            <h3 class="crm-section-title mt-2">Добавить задачу</h3>
                        </div>
                        <p class="text-sm text-slate-500">{{ lead.tasks.length }} шт.</p>
                    </div>

                    <form class="mt-6 grid gap-4" @submit.prevent="submitTask">
                        <div>
                            <label class="crm-label" for="lead-task-title">Заголовок</label>
                            <input id="lead-task-title" v-model="taskForm.title" type="text" class="crm-input" />
                            <p v-if="taskForm.errors.title" class="mt-2 text-sm text-rose-600">{{ taskForm.errors.title }}</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="crm-label" for="lead-task-type">Тип задачи</label>
                                <select id="lead-task-type" v-model="taskForm.type" class="crm-select">
                                    <option v-for="option in referenceData.taskTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <p v-if="taskForm.errors.type" class="mt-2 text-sm text-rose-600">{{ taskForm.errors.type }}</p>
                            </div>
                            <div>
                                <label class="crm-label" for="lead-task-due-at">Срок</label>
                                <input id="lead-task-due-at" v-model="taskForm.due_at" type="datetime-local" class="crm-input" />
                                <p v-if="taskForm.errors.due_at" class="mt-2 text-sm text-rose-600">{{ taskForm.errors.due_at }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-task-description">Описание</label>
                            <textarea id="lead-task-description" v-model="taskForm.description" rows="3" class="crm-textarea"></textarea>
                            <p v-if="taskForm.errors.description" class="mt-2 text-sm text-rose-600">{{ taskForm.errors.description }}</p>
                        </div>
                        <div class="flex flex-wrap gap-3 pt-2">
                            <button type="submit" class="crm-button" :disabled="taskForm.processing">{{ taskForm.processing ? 'Добавление...' : 'Добавить задачу' }}</button>
                        </div>
                    </form>

                    <div v-if="lead.tasks.length" class="mt-6 space-y-3">
                        <article v-for="task in lead.tasks" :key="task.id" class="rounded-[1.1rem] border border-slate-900/8 bg-white/72 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-950">{{ task.title }}</p>
                                    <p class="mt-1 text-sm text-slate-600">{{ task.type }} · {{ task.status }}</p>
                                </div>
                                <span class="crm-pill">{{ task.due_at || 'Без срока' }}</span>
                            </div>
                            <p v-if="task.description" class="mt-3 text-sm leading-6 text-slate-600">{{ task.description }}</p>
                            <p class="mt-3 text-sm text-slate-500">{{ task.user || 'Без ответственного' }} · {{ task.due_relative || 'Дата не указана' }}</p>
                        </article>
                    </div>
                    <div v-else class="crm-empty mt-6">Для этого лида пока нет задач.</div>
                </div>

                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">Сделки</p>
                            <h3 class="crm-section-title mt-2">Привязать лид к сделке</h3>
                        </div>
                        <Link href="/deals" class="crm-button-ghost">Все сделки</Link>
                    </div>

                    <form class="mt-6 grid gap-4 md:grid-cols-[minmax(0,1fr)_auto]" @submit.prevent="submitLinkedDeal">
                        <div>
                            <label class="crm-label" for="lead-deal-link">Сделка</label>
                            <select id="lead-deal-link" v-model="linkDealForm.deal_id" class="crm-select">
                                <option value="">Выберите сделку</option>
                                <option v-for="option in dealSelectOptions" :key="option.id" :value="option.id">{{ option.title }}</option>
                            </select>
                            <p v-if="linkDealForm.errors.deal_id" class="mt-2 text-sm text-rose-600">{{ linkDealForm.errors.deal_id }}</p>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="crm-button w-full sm:w-auto" :disabled="linkDealForm.processing">{{ linkDealForm.processing ? 'Привязка...' : 'Привязать' }}</button>
                        </div>
                    </form>

                    <div v-if="relatedDeals.length" class="mt-6 grid gap-4">
                        <DealCard v-for="deal in relatedDeals" :key="deal.id" :deal="deal" />
                    </div>
                    <div v-else class="crm-empty mt-6">Этот лид пока не привязан ни к одной сделке.</div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
