<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ActivityFeed from '@/Components/ActivityFeed.vue'
import PipelineStagesEditor from '@/Components/PipelineStagesEditor.vue'

const props = defineProps({
    deal: {
        type: Object,
        required: true,
    },
    availableStages: {
        type: Array,
        default: () => [],
    },
    leadOptions: {
        type: Array,
        default: () => [],
    },
    referenceData: {
        type: Object,
        default: () => ({
            branches: [],
            paymentStatuses: [],
            taskTypes: [],
        }),
    },
})

const isEditing = ref(false)

const dealForm = useForm({
    name: props.deal.name ?? props.deal.title ?? '',
    branch: props.deal.branch_value ?? '',
    appointment_at: props.deal.appointment_input ?? '',
    payment_status: props.deal.payment_status_value ?? '',
    cancel_reason: props.deal.cancel_reason ?? '',
    amount: props.deal.amount ?? '',
})

const contactForm = useForm({
    first_name: props.deal.contact?.first_name ?? '',
    last_name: props.deal.contact?.last_name ?? '',
    phone: props.deal.contact?.phone ?? '',
    email: props.deal.contact?.email ?? '',
})

const linkLeadForm = useForm({
    lead_id: props.deal.lead?.id ?? '',
})

const taskForm = useForm({
    title: '',
    description: '',
    type: props.referenceData.taskTypes[0]?.value ?? 'call',
    due_at: '',
})

function fillDealForm() {
    dealForm.name = props.deal.name ?? props.deal.title ?? ''
    dealForm.branch = props.deal.branch_value ?? ''
    dealForm.appointment_at = props.deal.appointment_input ?? ''
    dealForm.payment_status = props.deal.payment_status_value ?? ''
    dealForm.cancel_reason = props.deal.cancel_reason ?? ''
    dealForm.amount = props.deal.amount ?? ''
}

function startEditing() {
    fillDealForm()
    dealForm.clearErrors()
    isEditing.value = true
}

function cancelEditing() {
    fillDealForm()
    dealForm.clearErrors()
    isEditing.value = false
}

function submitDeal() {
    dealForm.patch(`/deals/${props.deal.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false
        },
    })
}

function submitContact() {
    contactForm.post(`/deals/${props.deal.id}/contact`, {
        preserveScroll: true,
    })
}

function submitLeadLink() {
    linkLeadForm.patch(`/deals/${props.deal.id}/lead`, {
        preserveScroll: true,
    })
}

function submitTask() {
    taskForm.post(`/deals/${props.deal.id}/tasks`, {
        preserveScroll: true,
        onSuccess: () => {
            taskForm.reset()
            taskForm.type = props.referenceData.taskTypes[0]?.value ?? 'call'
        },
    })
}
</script>

<template>
    <Head :title="deal.name || deal.title || `Сделка #${deal.id}`" />

    <AppLayout
        :title="deal.name || deal.title || `Сделка #${deal.id}`"
        subtitle="Страница сделки с этапами, контактными данными клиента, привязкой к лиду, анализами и задачами."
    >
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(20rem,0.75fr)]">
            <section class="space-y-6">
                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="crm-kicker">Сделка</p>
                            <h3 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">{{ deal.name || deal.title || `Сделка #${deal.id}` }}</h3>
                            <p class="mt-3 text-sm text-slate-500">Создано: {{ deal.created_at || 'неизвестно' }} · Обновлено: {{ deal.updated_at || 'неизвестно' }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <div v-if="deal.stage" class="crm-pill">
                                <span class="crm-stage-dot" :style="{ backgroundColor: deal.stage.color || '#94a3b8' }"></span>
                                <span>{{ deal.stage.name }}</span>
                            </div>
                            <button v-if="!isEditing" type="button" class="crm-button-ghost" @click="startEditing">Редактировать</button>
                            <button v-else type="button" class="crm-button-ghost" @click="cancelEditing">Отмена</button>
                        </div>
                    </div>

                    <form v-if="isEditing" class="mt-6 grid gap-4 md:grid-cols-2" @submit.prevent="submitDeal">
                        <div>
                            <label class="crm-label" for="deal-name">Название сделки</label>
                            <input id="deal-name" v-model="dealForm.name" type="text" class="crm-input" />
                            <p v-if="dealForm.errors.name" class="mt-2 text-sm text-rose-600">{{ dealForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="deal-branch">Филиал</label>
                            <select id="deal-branch" v-model="dealForm.branch" class="crm-select">
                                <option value="">Не выбран</option>
                                <option v-for="option in referenceData.branches" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="dealForm.errors.branch" class="mt-2 text-sm text-rose-600">{{ dealForm.errors.branch }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="deal-appointment">Дата визита</label>
                            <input id="deal-appointment" v-model="dealForm.appointment_at" type="datetime-local" class="crm-input" />
                            <p v-if="dealForm.errors.appointment_at" class="mt-2 text-sm text-rose-600">{{ dealForm.errors.appointment_at }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="deal-payment-status">Статус оплаты</label>
                            <select id="deal-payment-status" v-model="dealForm.payment_status" class="crm-select">
                                <option value="">Не выбран</option>
                                <option v-for="option in referenceData.paymentStatuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="dealForm.errors.payment_status" class="mt-2 text-sm text-rose-600">{{ dealForm.errors.payment_status }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="deal-amount">Сумма</label>
                            <input id="deal-amount" v-model="dealForm.amount" type="number" min="0" step="0.01" class="crm-input" />
                            <p v-if="dealForm.errors.amount" class="mt-2 text-sm text-rose-600">{{ dealForm.errors.amount }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="deal-cancel-reason">Причина отмены</label>
                            <input id="deal-cancel-reason" v-model="dealForm.cancel_reason" type="text" class="crm-input" />
                            <p v-if="dealForm.errors.cancel_reason" class="mt-2 text-sm text-rose-600">{{ dealForm.errors.cancel_reason }}</p>
                        </div>
                        <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
                            <button type="submit" class="crm-button" :disabled="dealForm.processing">{{ dealForm.processing ? 'Сохранение...' : 'Сохранить изменения' }}</button>
                            <button type="button" class="crm-button-ghost" @click="cancelEditing">Отменить</button>
                        </div>
                    </form>

                    <div v-else class="crm-data-grid mt-6">
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Филиал</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ deal.branch || 'Не выбран' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Дата визита</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ deal.appointment_at || 'Не назначена' }}</p>
                            <p v-if="deal.appointment_relative" class="mt-2 text-sm text-slate-500">{{ deal.appointment_relative }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Статус оплаты</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ deal.payment_status || 'Не указан' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Сумма</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ deal.amount ?? '0.00' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Ответственный</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ deal.user || 'Не назначен' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Причина отмены</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ deal.cancel_reason || 'Не указана' }}</p>
                        </div>
                    </div>
                </div>

                <PipelineStagesEditor title="Воронка сделки" :stages="availableStages" />

                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">Анализы</p>
                            <h3 class="crm-section-title mt-2">Добавленные анализы</h3>
                        </div>
                        <p class="text-sm text-slate-500">{{ deal.analyses.length }} шт.</p>
                    </div>

                    <div v-if="deal.analyses.length" class="mt-6 grid gap-3 sm:grid-cols-2">
                        <article v-for="analysis in deal.analyses" :key="analysis.id" class="rounded-[1.1rem] border border-slate-900/8 bg-white/72 p-4">
                            <p class="font-semibold text-slate-950">{{ analysis.name }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ analysis.code || 'без кода' }}</p>
                            <p class="mt-3 text-sm font-semibold text-slate-900">Цена: {{ analysis.price }}</p>
                        </article>
                    </div>
                    <div v-else class="crm-empty mt-6">В эту сделку пока не добавлены анализы.</div>
                </div>

                <ActivityFeed :items="deal.history" title="История этапов" empty-text="История этапов пока пуста." />
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
                            <label class="crm-label" for="deal-contact-first-name">Имя</label>
                            <input id="deal-contact-first-name" v-model="contactForm.first_name" type="text" class="crm-input" />
                            <p v-if="contactForm.errors.first_name" class="mt-2 text-sm text-rose-600">{{ contactForm.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="deal-contact-last-name">Фамилия</label>
                            <input id="deal-contact-last-name" v-model="contactForm.last_name" type="text" class="crm-input" />
                            <p v-if="contactForm.errors.last_name" class="mt-2 text-sm text-rose-600">{{ contactForm.errors.last_name }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="deal-contact-phone">Телефон</label>
                            <input id="deal-contact-phone" v-model="contactForm.phone" type="text" class="crm-input" />
                            <p v-if="contactForm.errors.phone" class="mt-2 text-sm text-rose-600">{{ contactForm.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="deal-contact-email">Email</label>
                            <input id="deal-contact-email" v-model="contactForm.email" type="email" class="crm-input" />
                            <p v-if="contactForm.errors.email" class="mt-2 text-sm text-rose-600">{{ contactForm.errors.email }}</p>
                        </div>
                        <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
                            <button type="submit" class="crm-button" :disabled="contactForm.processing">{{ contactForm.processing ? 'Сохранение...' : (deal.contact ? 'Обновить контакт' : 'Добавить контакт') }}</button>
                        </div>
                    </form>

                    <div v-if="deal.contact" class="mt-6 rounded-[1.1rem] border border-slate-900/8 bg-white/72 p-4 text-sm text-slate-600">
                        <p class="font-semibold text-slate-950">{{ deal.contact.name }}</p>
                        <p class="mt-2">{{ deal.contact.phone || 'Телефон не указан' }}</p>
                        <p>{{ deal.contact.email || 'Email не указан' }}</p>
                    </div>
                </div>

                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">Связь с лидом</p>
                            <h3 class="crm-section-title mt-2">Источник сделки</h3>
                        </div>
                        <Link v-if="deal.lead" :href="`/leads/${deal.lead.id}`" class="crm-button-ghost">Открыть лид</Link>
                    </div>

                    <form class="mt-6 grid gap-4 md:grid-cols-[minmax(0,1fr)_auto]" @submit.prevent="submitLeadLink">
                        <div>
                            <label class="crm-label" for="deal-lead-link">Лид</label>
                            <select id="deal-lead-link" v-model="linkLeadForm.lead_id" class="crm-select">
                                <option value="">Не привязан</option>
                                <option v-for="option in leadOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <p v-if="linkLeadForm.errors.lead_id" class="mt-2 text-sm text-rose-600">{{ linkLeadForm.errors.lead_id }}</p>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="crm-button w-full sm:w-auto" :disabled="linkLeadForm.processing">{{ linkLeadForm.processing ? 'Сохранение...' : 'Сохранить связь' }}</button>
                        </div>
                    </form>

                    <div class="mt-6 rounded-[1.1rem] border border-slate-900/8 bg-white/72 p-4">
                        <p class="crm-kicker !text-[0.66rem]">Текущий лид</p>
                        <p class="mt-2 font-semibold text-slate-950">{{ deal.lead?.name || 'Лид не привязан' }}</p>
                    </div>
                </div>

                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">Задачи</p>
                            <h3 class="crm-section-title mt-2">Добавить задачу</h3>
                        </div>
                        <p class="text-sm text-slate-500">{{ deal.tasks.length }} шт.</p>
                    </div>

                    <form class="mt-6 grid gap-4" @submit.prevent="submitTask">
                        <div>
                            <label class="crm-label" for="deal-task-title">Заголовок</label>
                            <input id="deal-task-title" v-model="taskForm.title" type="text" class="crm-input" />
                            <p v-if="taskForm.errors.title" class="mt-2 text-sm text-rose-600">{{ taskForm.errors.title }}</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="crm-label" for="deal-task-type">Тип задачи</label>
                                <select id="deal-task-type" v-model="taskForm.type" class="crm-select">
                                    <option v-for="option in referenceData.taskTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <p v-if="taskForm.errors.type" class="mt-2 text-sm text-rose-600">{{ taskForm.errors.type }}</p>
                            </div>
                            <div>
                                <label class="crm-label" for="deal-task-due-at">Срок</label>
                                <input id="deal-task-due-at" v-model="taskForm.due_at" type="datetime-local" class="crm-input" />
                                <p v-if="taskForm.errors.due_at" class="mt-2 text-sm text-rose-600">{{ taskForm.errors.due_at }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="crm-label" for="deal-task-description">Описание</label>
                            <textarea id="deal-task-description" v-model="taskForm.description" rows="3" class="crm-textarea"></textarea>
                            <p v-if="taskForm.errors.description" class="mt-2 text-sm text-rose-600">{{ taskForm.errors.description }}</p>
                        </div>
                        <div class="flex flex-wrap gap-3 pt-2">
                            <button type="submit" class="crm-button" :disabled="taskForm.processing">{{ taskForm.processing ? 'Добавление...' : 'Добавить задачу' }}</button>
                        </div>
                    </form>

                    <div v-if="deal.tasks.length" class="mt-6 space-y-3">
                        <article v-for="task in deal.tasks" :key="task.id" class="rounded-[1.1rem] border border-slate-900/8 bg-white/72 p-4">
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
                    <div v-else class="crm-empty mt-6">Для этой сделки пока нет задач.</div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
