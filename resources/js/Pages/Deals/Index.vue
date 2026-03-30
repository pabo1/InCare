<script setup>
import { reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DealCard from '@/Components/DealCard.vue'
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
    deals: {
        type: Object,
        required: true,
    },
})

const form = reactive({
    search: props.filters.search || '',
    stage: props.filters.stage || '',
    branch: props.filters.branch || '',
    payment_status: props.filters.payment_status || '',
})

function submit() {
    router.get('/deals', { ...form }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function resetFilters() {
    form.search = ''
    form.stage = ''
    form.branch = ''
    form.payment_status = ''
    submit()
}
</script>

<template>
    <Head title="Сделки" />

    <AppLayout
        title="Сделки"
        subtitle="Производственный процесс по направлению “Сдача анализов”: запись, напоминание, визит, готовность результатов и финальные статусы."
    >
        <section class="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
            <form class="crm-panel p-5 sm:p-6" @submit.prevent="submit">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="crm-kicker">Фильтры</p>
                        <h3 class="crm-section-title mt-2">Срез по сделкам</h3>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="crm-button-ghost" @click="resetFilters">Сбросить</button>
                        <button type="submit" class="crm-button">Применить</button>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                    <div class="xl:col-span-2">
                        <label class="crm-label" for="deal-search">Поиск</label>
                        <input id="deal-search" v-model="form.search" class="crm-input" placeholder="Сделка, контакт, филиал" />
                    </div>
                    <div>
                        <label class="crm-label" for="deal-stage">Стадия</label>
                        <select id="deal-stage" v-model="form.stage" class="crm-select">
                            <option value="">Все стадии</option>
                            <option v-for="stage in pipeline?.stages ?? []" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="crm-label" for="deal-branch">Филиал</label>
                        <select id="deal-branch" v-model="form.branch" class="crm-select">
                            <option value="">Все филиалы</option>
                            <option v-for="option in referenceData.branches" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="crm-label" for="deal-payment">Оплата</label>
                        <select id="deal-payment" v-model="form.payment_status" class="crm-select">
                            <option value="">Любой статус</option>
                            <option v-for="option in referenceData.paymentStatuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
            </form>

            <div class="crm-panel p-5 sm:p-6">
                <p class="crm-kicker">Итог по сделкам</p>
                <h3 class="crm-section-title mt-2">Контроль производства</h3>
                <div class="crm-stat-grid mt-6">
                    <article class="rounded-[1.2rem] border border-slate-900/8 bg-white/72 p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Всего сделок</p>
                        <p class="crm-metric-value mt-3">{{ stats.total }}</p>
                    </article>
                    <article class="rounded-[1.2rem] border border-slate-900/8 bg-white/72 p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Запланирован визит</p>
                        <p class="crm-metric-value mt-3">{{ stats.scheduled }}</p>
                    </article>
                    <article class="rounded-[1.2rem] border border-slate-900/8 bg-white/72 p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Оплачено</p>
                        <p class="crm-metric-value mt-3">{{ stats.paid }}</p>
                    </article>
                    <article class="rounded-[1.2rem] border border-slate-900/8 bg-white/72 p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Открытые задачи</p>
                        <p class="crm-metric-value mt-3">{{ stats.pending_tasks }}</p>
                    </article>
                </div>
            </div>
        </section>

        <KanbanBoard
            v-if="pipeline"
            class="mt-6"
            :title="pipeline.name"
            :stages="pipeline.stages"
            subtitle="Эта воронка отражает реальный маршрут клиента по анализам: от согласования времени до отправки результата."
        />

        <section class="mt-6">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="crm-kicker">Карточки</p>
                    <h3 class="crm-section-title mt-2">Актуальный список сделок</h3>
                </div>
                <p class="text-sm text-slate-500">Показано {{ deals.data.length }} из {{ deals.total }}</p>
            </div>

            <div v-if="deals.data.length" class="mt-5 grid gap-4 xl:grid-cols-2">
                <DealCard v-for="deal in deals.data" :key="deal.id" :deal="deal" />
            </div>
            <div v-else class="crm-empty mt-5">По текущим фильтрам сделки не найдены.</div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <Link
                    v-if="deals.prev_page_url"
                    :href="deals.prev_page_url"
                    class="crm-button-ghost"
                    preserve-scroll
                >
                    Назад
                </Link>
                <div v-else></div>

                <p class="text-sm text-slate-500">Страница {{ deals.current_page }} из {{ deals.last_page }}</p>

                <Link
                    v-if="deals.next_page_url"
                    :href="deals.next_page_url"
                    class="crm-button"
                    preserve-scroll
                >
                    Дальше
                </Link>
            </div>
        </section>
    </AppLayout>
</template>