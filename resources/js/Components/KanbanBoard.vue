<script setup>
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
</script>

<template>
    <section class="crm-panel p-5 sm:p-6">
        <div>
            <div>
                <p class="crm-kicker">Этапы</p>
                <h3 class="crm-section-title mt-2">{{ title }}</h3>
            </div>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
            <article
                v-for="stage in stages"
                :key="stage.id"
                class="flex min-h-[170px] flex-col justify-between rounded-[1.2rem] border border-slate-900/8 bg-white/70 p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-start gap-2">
                            <span class="crm-stage-dot mt-1" :style="{ backgroundColor: stage.color || '#94a3b8' }"></span>
                            <span class="block text-sm font-semibold leading-5 text-slate-900">{{ stage.name }}</span>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full bg-slate-950/5 px-3 py-1 text-3xl font-extrabold leading-none tracking-tight text-slate-950">
                        {{ stage.count ?? 0 }}
                    </span>
                </div>

                <div class="mt-6 flex flex-wrap gap-2 text-xs font-semibold text-slate-500">
                    <span v-if="stage.is_final" class="crm-pill">финал</span>
                    <span v-if="stage.is_fail" class="crm-pill">негатив</span>
                    <span v-if="!stage.is_final" class="crm-pill">в работе</span>
                </div>
            </article>
        </div>
    </section>
</template>
