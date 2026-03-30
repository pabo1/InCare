<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    lead: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <Link :href="`/leads/${lead.id}`" class="crm-panel crm-panel-strong flex min-h-[23rem] h-full flex-col p-5 transition duration-200 hover:-translate-y-1 hover:shadow-2xl">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Лид #{{ lead.id }}</p>
                <h3 class="mt-2 text-lg font-bold tracking-tight text-slate-950">{{ lead.name }}</h3>
            </div>
            <div v-if="lead.stage" class="crm-pill">
                <span class="crm-stage-dot" :style="{ backgroundColor: lead.stage.color || '#94a3b8' }"></span>
                <span>{{ lead.stage.name }}</span>
            </div>
        </div>

        <div class="mt-5 grid gap-3 text-sm text-slate-600 sm:grid-cols-2">
            <div>
                <p class="crm-kicker !text-[0.66rem]">Телефон</p>
                <p class="mt-1 font-medium text-slate-900">{{ lead.phone || lead.contact?.phone || 'Не указан' }}</p>
            </div>
            <div>
                <p class="crm-kicker !text-[0.66rem]">Контакт</p>
                <p class="mt-1 font-medium text-slate-900">{{ lead.contact?.name || 'Пока не привязан' }}</p>
            </div>
            <div>
                <p class="crm-kicker !text-[0.66rem]">Тип запроса</p>
                <p class="mt-1 font-medium text-slate-900">{{ lead.request_type || 'Не указан' }}</p>
            </div>
            <div>
                <p class="crm-kicker !text-[0.66rem]">Источник</p>
                <p class="mt-1 font-medium text-slate-900">{{ lead.source || 'Не указан' }}</p>
            </div>
        </div>

        <div class="mt-auto flex flex-wrap gap-2 pt-5 text-xs font-semibold text-slate-500">
            <span class="crm-pill">Филиал: {{ lead.branch || 'не выбран' }}</span>
            <span class="crm-pill">Задач: {{ lead.tasks_count ?? 0 }}</span>
            <span class="crm-pill">Обновлено: {{ lead.updated_at || 'только что' }}</span>
        </div>
    </Link>
</template>
