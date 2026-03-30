<script setup>
import { reactive } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import LeadCard from '@/Components/LeadCard.vue'
import KanbanBoard from '@/Components/KanbanBoard.vue'

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
    pipeline: {
        type: Object,
        default: null,
    },
    referenceData: {
        type: Object,
        required: true,
    },
    leads: {
        type: Object,
        required: true,
    },
})

const form = reactive({
    search: props.filters.search || '',
    stage: props.filters.stage || '',
    source: props.filters.source || '',
    request_type: props.filters.request_type || '',
    branch: props.filters.branch || '',
})

const createLeadForm = useForm({
    name: '',
    phone: '',
    source: '',
    request_type: '',
    branch: '',
})

function submit() {
    router.get('/leads', { ...form }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function resetFilters() {
    form.search = ''
    form.stage = ''
    form.source = ''
    form.request_type = ''
    form.branch = ''
    submit()
}

function submitCreateLead() {
    createLeadForm.post('/leads')
}

function resetCreateLead() {
    createLeadForm.reset()
    createLeadForm.clearErrors()
}
</script>

<template>
    <Head title="Лиды" />

    <AppLayout
        title="Лиды"
        subtitle="Здесь начинается работа оператора: входящий поток, квалификация, недозвоны и точки, где лид должен быть доведен до конвертации в сделку."
    >
        <section class="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
            <form class="crm-panel p-5 sm:p-6" @submit.prevent="submit">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="crm-kicker">Фильтры</p>
                        <h3 class="crm-section-title mt-2">Срез входящего потока</h3>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="crm-button-ghost" @click="resetFilters">Сбросить</button>
                        <button type="submit" class="crm-button">Применить</button>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-5">
                    <div class="xl:col-span-2">
                        <label class="crm-label" for="lead-search">Поиск</label>
                        <input id="lead-search" v-model="form.search" class="crm-input" placeholder="Имя, телефон, контакт" />
                    </div>
                    <div>
                        <label class="crm-label" for="lead-stage">Стадия</label>
                        <select id="lead-stage" v-model="form.stage" class="crm-select">
                            <option value="">Все стадии</option>
                            <option v-for="stage in pipeline?.stages ?? []" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="crm-label" for="lead-source">Источник</label>
                        <select id="lead-source" v-model="form.source" class="crm-select">
                            <option value="">Все источники</option>
                            <option v-for="option in referenceData.sources" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="crm-label" for="lead-request">Тип запроса</label>
                        <select id="lead-request" v-model="form.request_type" class="crm-select">
                            <option value="">Все типы</option>
                            <option v-for="option in referenceData.requestTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="crm-label" for="lead-branch">Филиал</label>
                        <select id="lead-branch" v-model="form.branch" class="crm-select">
                            <option value="">Все филиалы</option>
                            <option v-for="option in referenceData.branches" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
            </form>

            <div class="crm-panel p-5 sm:p-6">
                <p class="crm-kicker">Итог по лидам</p>
                <h3 class="crm-section-title mt-2">Что видно сразу</h3>
                <div class="crm-stat-grid mt-6">
                    <article class="rounded-[1.2rem] border border-slate-900/8 bg-white/72 p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Всего лидов</p>
                        <p class="crm-metric-value mt-3">{{ stats.total }}</p>
                    </article>
                    <article class="rounded-[1.2rem] border border-slate-900/8 bg-white/72 p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">С контактом</p>
                        <p class="crm-metric-value mt-3">{{ stats.with_contacts }}</p>
                    </article>
                    <article class="rounded-[1.2rem] border border-slate-900/8 bg-white/72 p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Открытые задачи</p>
                        <p class="crm-metric-value mt-3">{{ stats.pending_tasks }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="mt-6 crm-panel p-5 sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="crm-kicker">Новый лид</p>
                    <h3 class="crm-section-title mt-2">Быстро добавить обращение</h3>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="crm-button-ghost" @click="resetCreateLead">Очистить</button>
                    <button type="submit" form="create-lead-form" class="crm-button" :disabled="createLeadForm.processing">
                        {{ createLeadForm.processing ? 'Создание...' : 'Создать лид' }}
                    </button>
                </div>
            </div>

            <form id="create-lead-form" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="submitCreateLead">
                <div class="xl:col-span-2">
                    <label class="crm-label" for="create-lead-name">Имя клиента</label>
                    <input id="create-lead-name" v-model="createLeadForm.name" class="crm-input" placeholder="Например, Алина Каримова" />
                    <p v-if="createLeadForm.errors.name" class="mt-2 text-sm text-rose-600">{{ createLeadForm.errors.name }}</p>
                </div>
                <div>
                    <label class="crm-label" for="create-lead-phone">Телефон</label>
                    <input id="create-lead-phone" v-model="createLeadForm.phone" class="crm-input" placeholder="+998 ..." />
                    <p v-if="createLeadForm.errors.phone" class="mt-2 text-sm text-rose-600">{{ createLeadForm.errors.phone }}</p>
                </div>
                <div>
                    <label class="crm-label" for="create-lead-source">Источник</label>
                    <select id="create-lead-source" v-model="createLeadForm.source" class="crm-select">
                        <option value="">Не указан</option>
                        <option v-for="option in referenceData.sources" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <p v-if="createLeadForm.errors.source" class="mt-2 text-sm text-rose-600">{{ createLeadForm.errors.source }}</p>
                </div>
                <div>
                    <label class="crm-label" for="create-lead-request">Тип запроса</label>
                    <select id="create-lead-request" v-model="createLeadForm.request_type" class="crm-select">
                        <option value="">Не указан</option>
                        <option v-for="option in referenceData.requestTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <p v-if="createLeadForm.errors.request_type" class="mt-2 text-sm text-rose-600">{{ createLeadForm.errors.request_type }}</p>
                </div>
                <div>
                    <label class="crm-label" for="create-lead-branch">Филиал</label>
                    <select id="create-lead-branch" v-model="createLeadForm.branch" class="crm-select">
                        <option value="">Не выбран</option>
                        <option v-for="option in referenceData.branches" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <p v-if="createLeadForm.errors.branch" class="mt-2 text-sm text-rose-600">{{ createLeadForm.errors.branch }}</p>
                </div>
            </form>
        </section>

        <KanbanBoard
            v-if="pipeline"
            class="mt-6"
            :title="pipeline.name"
            :stages="pipeline.stages"
        />

        <section class="mt-6">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="crm-kicker">Карточки</p>
                    <h3 class="crm-section-title mt-2">Актуальный список лидов</h3>
                </div>
                <p class="text-sm text-slate-500">Показано {{ leads.data.length }} из {{ leads.total }}</p>
            </div>

            <div v-if="leads.data.length" class="mt-5 grid gap-4 xl:grid-cols-2">
                <LeadCard v-for="lead in leads.data" :key="lead.id" :lead="lead" />
            </div>
            <div v-else class="crm-empty mt-5">По текущим фильтрам лиды не найдены.</div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <Link
                    v-if="leads.prev_page_url"
                    :href="leads.prev_page_url"
                    class="crm-button-ghost"
                    preserve-scroll
                >
                    Назад
                </Link>
                <div v-else></div>

                <p class="text-sm text-slate-500">Страница {{ leads.current_page }} из {{ leads.last_page }}</p>

                <Link
                    v-if="leads.next_page_url"
                    :href="leads.next_page_url"
                    class="crm-button"
                    preserve-scroll
                >
                    Дальше
                </Link>
            </div>
        </section>
    </AppLayout>
</template>
