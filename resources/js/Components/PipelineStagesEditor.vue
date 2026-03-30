<script setup>
import { reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

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

const isEditing = ref(false)
const savingStageId = ref(null)
const localStages = reactive([])

function syncStages() {
    localStages.splice(0, localStages.length, ...props.stages.map((stage) => ({
        id: stage.id,
        name: stage.name ?? '',
        color: stage.color || '#94a3b8',
        is_final: Boolean(stage.is_final),
        is_fail: Boolean(stage.is_fail),
        is_current: Boolean(stage.is_current),
        update_url: stage.update_url,
    })))
}

function startEditing() {
    syncStages()
    isEditing.value = true
}

function cancelEditing() {
    syncStages()
    isEditing.value = false
}

function saveStage(stage) {
    savingStageId.value = stage.id

    router.patch(stage.update_url, {
        name: stage.name,
        color: stage.color,
        is_final: stage.is_final,
        is_fail: stage.is_final ? stage.is_fail : false,
    }, {
        preserveScroll: true,
        onFinish: () => {
            savingStageId.value = null
        },
        onSuccess: () => {
            isEditing.value = false
        },
    })
}

watch(() => props.stages, syncStages, { immediate: true, deep: true })
</script>

<template>
    <div class="crm-panel p-5 sm:p-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="crm-kicker">Этапы</p>
                <h3 class="crm-section-title mt-2">{{ title }}</h3>
            </div>
            <div class="flex gap-2">
                <button v-if="!isEditing" type="button" class="crm-button-ghost" @click="startEditing">Редактировать воронку</button>
                <template v-else>
                    <button type="button" class="crm-button-ghost" @click="cancelEditing">Отмена</button>
                </template>
            </div>
        </div>

        <div v-if="!isEditing" class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="stage in stages"
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

        <div v-else class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="stage in localStages"
                :key="stage.id"
                class="rounded-[1.15rem] border border-slate-900/8 bg-white/72 p-4"
            >
                <div class="space-y-4">
                    <div>
                        <label class="crm-label" :for="`stage-name-${stage.id}`">Название этапа</label>
                        <input :id="`stage-name-${stage.id}`" v-model="stage.name" type="text" class="crm-input" />
                    </div>

                    <div>
                        <label class="crm-label" :for="`stage-color-${stage.id}`">Цвет</label>
                        <div class="mt-2 flex items-center gap-3">
                            <input :id="`stage-color-${stage.id}`" v-model="stage.color" type="color" class="h-11 w-14 rounded-xl border border-slate-200 bg-white px-1 py-1" />
                            <input v-model="stage.color" type="text" class="crm-input" placeholder="#94a3b8" />
                        </div>
                    </div>

                    <label class="flex items-center gap-3 text-sm font-medium text-slate-700">
                        <input v-model="stage.is_final" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-orange-500 focus:ring-orange-400" />
                        <span>Финальный этап</span>
                    </label>

                    <label class="flex items-center gap-3 text-sm font-medium text-slate-700">
                        <input v-model="stage.is_fail" :disabled="!stage.is_final" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-orange-500 focus:ring-orange-400 disabled:opacity-50" />
                        <span>Негативный финал</span>
                    </label>

                    <div class="flex items-center justify-between gap-3 pt-2">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">
                            {{ stage.is_current ? 'текущий этап' : 'этап воронки' }}
                        </p>
                        <button type="button" class="crm-button" :disabled="savingStageId === stage.id" @click="saveStage(stage)">
                            {{ savingStageId === stage.id ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
