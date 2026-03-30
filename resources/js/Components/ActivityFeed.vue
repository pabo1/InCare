<script setup>
const props = defineProps({
    title: {
        type: String,
        default: 'История',
    },
    items: {
        type: Array,
        default: () => [],
    },
    emptyText: {
        type: String,
        default: 'Пока без событий',
    },
})
</script>

<template>
    <section class="crm-panel p-5 sm:p-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="crm-kicker">Лента</p>
                <h3 class="crm-section-title mt-2">{{ title }}</h3>
            </div>
            <div class="text-sm text-slate-500">{{ items.length }} записей</div>
        </div>

        <div v-if="items.length" class="crm-timeline mt-6 space-y-6">
            <article v-for="item in items" :key="item.id" class="crm-timeline-item">
                <div class="rounded-[1.1rem] border border-slate-900/8 bg-white/72 p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h4 class="font-semibold text-slate-950">{{ item.title }}</h4>
                            <p v-if="item.subtitle" class="mt-1 text-sm text-slate-600">{{ item.subtitle }}</p>
                        </div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ item.time }}</p>
                    </div>
                    <p v-if="item.note" class="mt-3 text-sm leading-6 text-slate-600">{{ item.note }}</p>
                </div>
            </article>
        </div>

        <div v-else class="crm-empty mt-6">{{ emptyText }}</div>
    </section>
</template>