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
    source: props.lead.source_value ?? '',
    request_type: props.lead.request_type_value ?? '',
    branch: props.lead.branch_value ?? '',
})

function fillForm() {
    form.name = props.lead.name ?? ''
    form.phone = props.lead.phone ?? ''
    form.source = props.lead.source_value ?? ''
    form.request_type = props.lead.request_type_value ?? ''
    form.branch = props.lead.branch_value ?? ''
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
    <Head :title="lead.name || `Лид #${lead.id}`" />

    <AppLayout
        :title="lead.name || `Лид #${lead.id}`"
        subtitle="Страница лида с деталями квалификации, текущим этапом, задачами, историей и связанными сделками."
    >
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(20rem,0.75fr)]">
            <section class="space-y-6">
                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="crm-kicker">Лид</p>
                            <h3 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">{{ lead.name || `Лид #${lead.id}` }}</h3>
                            <p class="mt-3 text-sm text-slate-500">Создано: {{ lead.created_at || 'неизвестно' }} · Обновлено: {{ lead.updated_at || 'неизвестно' }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <div v-if="lead.stage" class="crm-pill">
                                <span class="crm-stage-dot" :style="{ backgroundColor: lead.stage.color || '#94a3b8' }"></span>
                                <span>{{ lead.stage.name }}</span>
                            </div>
                            <button v-if="!isEditing" type="button" class="crm-button-ghost" @click="startEditing">Редактировать</button>
                            <button v-else type="button" class="crm-button-ghost" @click="cancelEditing">Отмена</button>
                        </div>
                    </div>

                    <form v-if="isEditing" class="mt-6 grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                        <div>
                            <label class="crm-label" for="lead-name">Имя</label>
                            <input id="lead-name" v-model="form.name" type="text" class="crm-input" />
                            <p v-if="form.errors.name" class="mt-2 text-sm text-rose-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-phone">Телефон</label>
                            <input id="lead-phone" v-model="form.phone" type="text" class="crm-input" />
                            <p v-if="form.errors.phone" class="mt-2 text-sm text-rose-600">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-source">Источник</label>
                            <select id="lead-source" v-model="form.source" class="crm-select">
                                <option value="">Не выбран</option>
                                <option v-for="option in referenceData.sources" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.source" class="mt-2 text-sm text-rose-600">{{ form.errors.source }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-request-type">Тип запроса</label>
                            <select id="lead-request-type" v-model="form.request_type" class="crm-select">
                                <option value="">Не выбран</option>
                                <option v-for="option in referenceData.requestTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.request_type" class="mt-2 text-sm text-rose-600">{{ form.errors.request_type }}</p>
                        </div>
                        <div>
                            <label class="crm-label" for="lead-branch">Филиал</label>
                            <select id="lead-branch" v-model="form.branch" class="crm-select">
                                <option value="">Не выбран</option>
                                <option v-for="option in referenceData.branches" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.branch" class="mt-2 text-sm text-rose-600">{{ form.errors.branch }}</p>
                        </div>
                        <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
                            <button type="submit" class="crm-button" :disabled="form.processing">{{ form.processing ? 'Сохранение...' : 'Сохранить изменения' }}</button>
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

                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">Этапы</p>
                            <h3 class="crm-section-title mt-2">Воронка лида</h3>
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

                <ActivityFeed :items="lead.history" title="История этапов" empty-text="История этапов пока пуста." />
            </section>

            <section class="space-y-6">
                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">Контакт</p>
                            <h3 class="crm-section-title mt-2">Данные связанного клиента</h3>
                        </div>
                    </div>

                    <div v-if="lead.contact" class="mt-6 space-y-3 text-sm text-slate-600">
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Имя</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.contact.name }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Телефон</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.contact.phone || 'Не указан' }}</p>
                        </div>
                        <div class="crm-data-item">
                            <p class="crm-kicker !text-[0.66rem]">Email</p>
                            <p class="mt-2 font-semibold text-slate-950">{{ lead.contact.email || 'Не указан' }}</p>
                        </div>
                    </div>
                    <div v-else class="crm-empty mt-6">К этому лиду пока не привязан контакт.</div>
                </div>

                <div class="crm-panel p-5 sm:p-6">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="crm-kicker">Задачи</p>
                            <h3 class="crm-section-title mt-2">Следующие действия</h3>
                        </div>
                        <p class="text-sm text-slate-500">{{ lead.tasks.length }} шт.</p>
                    </div>

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
                            <p class="crm-kicker">После конверсии</p>
                            <h3 class="crm-section-title mt-2">Связанные сделки</h3>
                        </div>
                        <Link href="/deals" class="crm-button-ghost">Все сделки</Link>
                    </div>

                    <div v-if="relatedDeals.length" class="mt-6 grid gap-4">
                        <DealCard v-for="deal in relatedDeals" :key="deal.id" :deal="deal" />
                    </div>
                    <div v-else class="crm-empty mt-6">Этот лид пока не конвертирован в сделку.</div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
