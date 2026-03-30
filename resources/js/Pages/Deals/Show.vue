<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import ActivityFeed from '@/Components/ActivityFeed.vue'

const props = defineProps({
    deal: {
        type: Object,
        required: true,
    },
    availableStages: {
        type: Array,
        default: () => [],
    },
    referenceData: {
        type: Object,
        default: () => ({
            branches: [],
            paymentStatuses: [],
        }),
    },
})

const isEditing = ref(false)
const form = useForm({
    name: props.deal.name ?? props.deal.title ?? '',
    branch: props.deal.branch ?? '',
    appointment_at: props.deal.appointment_input ?? '',
    payment_status: props.deal.payment_status ?? '',
    cancel_reason: props.deal.cancel_reason ?? '',
    amount: props.deal.amount ?? '',
})

function fillForm() {
    form.name = props.deal.name ?? props.deal.title ?? ''
    form.branch = props.deal.branch ?? ''
    form.appointment_at = props.deal.appointment_input ?? ''
    form.payment_status = props.deal.payment_status ?? ''
    form.cancel_reason = props.deal.cancel_reason ?? ''
    form.amount = props.deal.amount ?? ''
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
    form.patch(`/deals/${props.deal.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false
        },
    })
}
</script>

<template>
    <Head :title="deal.name || deal.title || `Сделка #${deal.id}`" />

    <AppLayout
        :title="deal.name || deal.title || `Сделка #${deal.id}`"
        subtitle="Страница сделки с данными о визите, статусе оплаты, анализах, задачах и историей этапов."
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

                    <form v-if="isEditing" class="mt-6 grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                        <div>
                            <label class="crm-label" for="deal-name">Название</label>
                            <input id="deal-name" v-model="form.name" type="text" class="crm-input" />
                            <p v-if="form.errors.name" class="mt-2 text-sm text-rose-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="deal-branch">Филиал</label>
                            <select id="deal-branch" v-model="form.branch" class="crm-select">
                                <option value="">Не выбран</option>
                                <option v-for="option in referenceData.branches" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.branch" class="mt-2 text-sm text-rose-600">{{ form.errors.branch }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="deal-appointment">Запись</label>
                            <input id="deal-appointment" v-model="form.appointment_at" type="datetime-local" class="crm-input" />
                            <p v-if="form.errors.appointment_at" class="mt-2 text-sm text-rose-600">{{ form.errors.appointment_at }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="deal-payment">Статус оплаты</label>
                            <select id="deal-payment" v-model="form.payment_status" class="crm-select">
                                <option value="">Не выбран</option>
                                <option v-for="option in referenceData.paymentStatuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.payment_status" class="mt-2 text-sm text-rose-600">{{ form.errors.payment_status }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="deal-amount">Сумма</label>
                            <input id="deal-amount" v-model="form.amount" type="number" step="0.01" min="0" class="crm-input" />
                            <p v-if="form.errors.amount" class="mt-2 text-sm text-rose-600">{{ form.errors.amount }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="deal-cancel-reason">Причина отмены</label>
                            <input id="deal-cancel-reason" v-model="form.cancel_reason" type="text" class="crm-input" />
                            <p v-if="form.errors.cancel_reason" class="mt-2 text-sm text-rose-600">{{ form.errors.cancel_reason }}</p>
                        </div>
                        <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
                            <button type="submit" class="crm-button" :disabled="form.processing">{{ form.processing ? 'Сохранение...' : 'Сохранить изменения' }}</button>
                            <button type="button" class="crm-button-ghost" @click="cancelEditing">Отменить</button>
                        </div>
                    </form>

                    <div v-else class="crm-data-grid mt-6">
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Филиал</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ deal.branch || 'Не выбран' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Запись</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ deal.appointment_at || 'Не запланирована' }}</p>
                            <p v-if="deal.appointment_relative" class="mt-2 text-sm text-slate-500">{{ deal.appointment_relative }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Статус оплаты</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ deal.payment_status || 'Не указан' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Сумма</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ deal.amount ?? '0' }}</p>
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

                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">Этапы</p>
                            <h3 class="crm-section-title mt-2">Воронка сделки</h3>
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
                                        {{ stage.is_current ? 'текущий' : stage.is_final ? 'финальный' : 'активный' }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

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
                            <p class="crm-kicker">Связи</p>
                            <h3 class="crm-section-title mt-2">Клиент и источник</h3>
                        </div>
                    </div>

                    <div class="mt-6 space-y-4">
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Контакт</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ deal.contact?.name || 'Контакт не привязан' }}</p>
                            <p class="mt-2 text-sm text-slate-500">{{ deal.contact?.phone || 'Телефон не указан' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Исходный лид</p>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <p class="font-semibold text-slate-950">{{ deal.lead?.name || 'Лид не привязан' }}</p>
                                <Link v-if="deal.lead" :href="`/leads/${deal.lead.id}`" class="crm-button-ghost">Открыть лид</Link>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">Задачи</p>
                            <h3 class="crm-section-title mt-2">Следующие действия</h3>
                        </div>
                        <p class="text-sm text-slate-500">{{ deal.tasks.length }} шт.</p>
                    </div>

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
