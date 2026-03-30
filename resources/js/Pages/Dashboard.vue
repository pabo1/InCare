<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import LeadCard from '@/Components/LeadCard.vue'
import DealCard from '@/Components/DealCard.vue'
import KanbanBoard from '@/Components/KanbanBoard.vue'

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    pipelines: {
        type: Object,
        required: true,
    },
    recentLeads: {
        type: Array,
        default: () => [],
    },
    upcomingDeals: {
        type: Array,
        default: () => [],
    },
    overdueTasks: {
        type: Array,
        default: () => [],
    },
})

const metricCards = [
    {
        label: 'Лиды в системе',
        value: props.stats.lead_count,
        note: 'общий входящий поток',
    },
    {
        label: 'Сделки в работе',
        value: props.stats.deal_count,
        note: 'производственный контур',
    },
    {
        label: 'Контакты',
        value: props.stats.contact_count,
        note: 'единая клиентская база',
    },
    {
        label: 'Ожидают задачи',
        value: props.stats.pending_task_count,
        note: 'нужен ручной шаг',
    },
]
</script>

<template>
    <Head title="InCare" />

    <AppLayout
        title="InCare"
        subtitle="Первый экран оператора: видно, где застревают лиды, сколько сделок уже доведено до записи и где скапливаются просроченные задачи."
    >
        <section class="grid items-stretch gap-4 xl:grid-cols-[minmax(0,1.45fr)_minmax(20rem,0.85fr)]">
            <div class="crm-panel crm-accent-card flex h-full flex-col p-6 sm:p-7">
                <p class="text-xs font-bold uppercase tracking-[0.24em] text-white/70">Сегодня в фокусе</p>
                <h3 class="mt-4 max-w-2xl text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                    Две воронки, один живой экран: от первого касания до отправки результатов.
                </h3>
                <div class="mt-auto grid gap-3 pt-6 sm:grid-cols-3">
                    <div class="rounded-[1.2rem] bg-white/12 p-4 backdrop-blur-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/70">Записаны</p>
                        <p class="mt-2 text-3xl font-extrabold">{{ stats.scheduled_deal_count }}</p>
                    </div>
                    <div class="rounded-[1.2rem] bg-white/12 p-4 backdrop-blur-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/70">Оплачены</p>
                        <p class="mt-2 text-3xl font-extrabold">{{ stats.paid_deal_count }}</p>
                    </div>
                    <div class="rounded-[1.2rem] bg-white/12 p-4 backdrop-blur-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/70">Нужны действия</p>
                        <p class="mt-2 text-3xl font-extrabold">{{ stats.pending_task_count }}</p>
                    </div>
                </div>
            </div>

            <div class="crm-panel h-full p-6 sm:p-7">
                <p class="crm-kicker">Контроль нагрузки</p>
                <h3 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-950">Быстрый срез команды</h3>
                <div class="crm-stat-grid mt-6">
                    <article v-for="card in metricCards" :key="card.label" class="rounded-[1.2rem] border border-slate-900/8 bg-white/70 p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">{{ card.label }}</p>
                        <p class="crm-metric-value mt-3">{{ card.value }}</p>
                        <p class="mt-2 text-sm text-slate-500">{{ card.note }}</p>
                    </article>
                </div>
            </div>
        </section>

        <div class="mt-6 grid items-start gap-6 xl:grid-cols-2">
            <KanbanBoard
                v-if="pipelines.leads"
                :title="pipelines.leads.name"
                :stages="pipelines.leads.stages"
                subtitle="Воронка первичной обработки: видно, где поток застревает еще до конвертации в сделку."
            />
            <KanbanBoard
                v-if="pipelines.deals"
                :title="pipelines.deals.name"
                :stages="pipelines.deals.stages"
                subtitle="Производственный контур по анализам: от записи до финальной отправки результатов."
            />
        </div>

        <div class="crm-dashboard-lower mt-6">
            <section class="crm-dashboard-leads space-y-4">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="crm-kicker">Последние обращения</p>
                        <h3 class="crm-section-title mt-2">Свежие лиды</h3>
                    </div>
                </div>
                <div v-if="recentLeads.length" class="grid gap-5 xl:grid-cols-2">
                    <LeadCard v-for="lead in recentLeads" :key="lead.id" :lead="lead" />
                </div>
                <div v-else class="crm-empty">Пока нет лидов. Как только поток появится, карточки будут здесь.</div>
            </section>

            <section class="crm-dashboard-deals space-y-4">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="crm-kicker">Ближайшие визиты</p>
                        <h3 class="crm-section-title mt-2">Сделки с записью</h3>
                    </div>
                </div>
                <div v-if="upcomingDeals.length" class="grid gap-5">
                    <DealCard v-for="deal in upcomingDeals" :key="deal.id" :deal="deal" />
                </div>
                <div v-else class="crm-empty">Пока нет сделок с назначенным временем визита.</div>
            </section>

            <section class="crm-dashboard-control crm-panel flex h-full flex-col self-stretch p-5 sm:p-6">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="crm-kicker">Ручной контроль</p>
                        <h3 class="crm-section-title mt-2">Просроченные задачи</h3>
                    </div>
                    <p class="text-sm text-slate-500">{{ overdueTasks.length }} шт.</p>
                </div>

                <div v-if="overdueTasks.length" class="mt-6 space-y-3">
                    <article
                        v-for="task in overdueTasks"
                        :key="task.id"
                        class="rounded-[1.1rem] border border-slate-900/8 bg-white/72 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-950">{{ task.title }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ task.type }} - {{ task.status }}</p>
                            </div>
                            <span class="crm-pill">{{ task.due_at || 'без срока' }}</span>
                        </div>
                        <p class="mt-3 text-sm text-slate-500">{{ task.user || 'Без ответственного' }} - {{ task.due_relative || 'срок не указан' }}</p>
                    </article>
                </div>
                <div v-else class="crm-empty mt-6">Отлично, просроченных задач нет.</div>
            </section>
        </div>
    </AppLayout>
</template>
