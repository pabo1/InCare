<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    stages: {
        type: Array,
        default: () => [],
    },
})

const form = useForm({
    stage_id: null,
})

function moveToStage(stage) {
    form.stage_id = stage.id
    form.patch(stage.move_url, {
        preserveScroll: true,
    })
}
</script>

<template>
    <div class="crm-panel p-5 sm:p-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="crm-kicker">Этапы</p>
                <h3 class="crm-section-title mt-2">{{ title }}</h3>
            </div>
            <p class="text-sm text-slate-500">Нажмите на этап, чтобы перевести запись</p>
        </div>

        <div v-if="form.errors.stage_id" class="crm-empty mt-6 border-rose-200 text-rose-700">
            {{ form.errors.stage_id }}
        </div>
        <div
            v-for="message in Object.values(form.errors).filter((value, index, array) => value && array.indexOf(value) === index && value !== form.errors.stage_id)"
            :key="message"
            class="crm-empty mt-3 border-rose-200 text-rose-700"
        >
            {{ message }}
        </div>

        <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="stage in stages"
                :key="stage.id"
                class="rounded-[1.15rem] border p-4"
                :class="stage.is_current ? 'border-orange-300 bg-orange-50/80 shadow-lg' : 'border-slate-900/8 bg-white/72'"
            >
                <div class="flex h-full flex-col justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="crm-stage-dot" :style="{ backgroundColor: stage.color || '#94a3b8' }"></span>
                        <div>
                            <p class="font-semibold text-slate-950">{{ stage.name }}</p>
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">
                                {{ stage.is_current ? 'текущий' : stage.is_final ? 'финальный' : 'активный' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            v-if="!stage.is_current"
                            type="button"
                            class="crm-button-ghost"
                            :disabled="form.processing"
                            @click="moveToStage(stage)"
                        >
                            {{ form.processing && form.stage_id === stage.id ? 'Перевод...' : 'Перевести сюда' }}
                        </button>
                        <span v-else class="crm-pill">Текущий этап</span>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
