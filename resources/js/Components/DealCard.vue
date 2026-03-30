<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    deal: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <Link :href="`/deals/${deal.id}`" class="crm-panel crm-panel-strong flex min-h-[23rem] h-full flex-col p-5 transition duration-200 hover:-translate-y-1 hover:shadow-2xl">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Сделка #{{ deal.id }}</p>
                <h3 class="mt-2 text-lg font-bold tracking-tight text-slate-950">{{ deal.name || deal.title }}</h3>
            </div>
            <div v-if="deal.stage" class="crm-pill">
                <span class="crm-stage-dot" :style="{ backgroundColor: deal.stage.color || '#94a3b8' }"></span>
                <span>{{ deal.stage.name }}</span>
            </div>
        </div>

        <div class="mt-5 grid gap-3 text-sm text-slate-600 sm:grid-cols-2">
            <div>
                <p class="crm-kicker !text-[0.66rem]">Контакт</p>
                <p class="mt-1 font-medium text-slate-900">{{ deal.contact?.name || 'Пока не привязан' }}</p>
            </div>
            <div>
                <p class="crm-kicker !text-[0.66rem]">Филиал</p>
                <p class="mt-1 font-medium text-slate-900">{{ deal.branch || 'Не выбран' }}</p>
            </div>
            <div>
                <p class="crm-kicker !text-[0.66rem]">Запись</p>
                <p class="mt-1 font-medium text-slate-900">{{ deal.appointment_at || 'Не назначена' }}</p>
            </div>
            <div>
                <p class="crm-kicker !text-[0.66rem]">Оплата</p>
                <p class="mt-1 font-medium text-slate-900">{{ deal.payment_status || 'Не оплачено' }}</p>
            </div>
        </div>

        <div class="mt-auto flex flex-wrap gap-2 pt-5 text-xs font-semibold text-slate-500">
            <span class="crm-pill">Анализов: {{ deal.analyses_count ?? 0 }}</span>
            <span class="crm-pill">Задач: {{ deal.tasks_count ?? 0 }}</span>
            <span v-if="deal.appointment_relative" class="crm-pill">{{ deal.appointment_relative }}</span>
        </div>
    </Link>
</template>
