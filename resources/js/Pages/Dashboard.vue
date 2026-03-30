<script setup>
import { Head, Link } from '@inertiajs/vue3'
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
})

const metricCards = [
    {
        label: 'Лиды в системе',
        value: props.stats.lead_count,
        note: 'общий входящий поток',
        href: '/leads',
    },
    {
        label: 'Сделки в работе',
        value: props.stats.deal_count,
        note: 'производственный контур',
        href: '/deals',
    },
    {
        label: 'Контакты',
        value: props.stats.contact_count,
        note: 'единая клиентская база',
        href: '/leads',
    },
    {
        label: 'Ожидают задачи',
        value: props.stats.pending_task_count,
        note: 'нужен ручной шаг',
        href: '/dashboard',
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
                    <Link
                        v-for="card in metricCards"
                        :key="card.label"
                        :href="card.href"
                        class="rounded-[1.2rem] border border-slate-900/8 bg-white/70 p-4 transition duration-200 hover:-translate-y-1 hover:border-slate-900/15 hover:shadow-xl"
                    >
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">{{ card.label }}</p>
                        <p class="crm-metric-value mt-3">{{ card.value }}</p>
                        <p class="mt-2 text-sm text-slate-500">{{ card.note }}</p>
                    </Link>
                </div>
            </div>
        </section>

        <div class="mt-6 grid items-start gap-6 xl:grid-cols-2">
            <KanbanBoard
                v-if="pipelines.leads"
                :title="pipelines.leads.name"
                :stages="pipelines.leads.stages"
            />
            <KanbanBoard
                v-if="pipelines.deals"
                :title="pipelines.deals.name"
                :stages="pipelines.deals.stages"
            />
        </div>

        <div class="mt-6 grid items-start gap-6 xl:grid-cols-2">
            <section class="space-y-4">
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

            <section class="space-y-4">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="crm-kicker">Ближайшие визиты</p>
                        <h3 class="crm-section-title mt-2">Сделки с записью</h3>
                    </div>
                </div>
                <div v-if="upcomingDeals.length" class="grid gap-5 md:grid-cols-2">
                    <DealCard v-for="deal in upcomingDeals" :key="deal.id" :deal="deal" />
                </div>
                <div v-else class="crm-empty">Пока нет сделок с назначенным временем визита.</div>
            </section>
        </div>
    </AppLayout>
</template>
