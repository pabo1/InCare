<script setup>
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
})

const page = usePage()

const navigation = [
    { href: '/dashboard', label: 'InCare CRM', hint: 'сводка' },
    { href: '/leads', label: 'Лиды', hint: 'первичный поток' },
    { href: '/deals', label: 'Сделки', hint: 'сдача анализов' },
]

const currentPath = computed(() => {
    const url = page.url || '/'
    return url.split('?')[0]
})

const user = computed(() => page.props.auth?.user ?? null)

function logout() {
    router.post('/logout')
}
</script>

<template>
    <div class="crm-shell lg:grid lg:min-h-screen lg:grid-cols-[280px_minmax(0,1fr)]">
        <aside class="relative z-10 border-b border-white/50 px-5 py-5 lg:border-b-0 lg:border-r lg:border-slate-900/5 lg:px-6 lg:py-8">
            <div class="crm-panel crm-panel-strong sticky top-6 space-y-6 p-5 lg:p-6">
                <div class="space-y-3">
                    <div class="crm-kicker">InCare CRM</div>
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">Клиника без хаоса</h1>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Интерфейс для первичной обработки лидов и маршрута сделки по анализам.
                        </p>
                    </div>
                </div>

                <nav class="space-y-2">
                    <Link
                        v-for="item in navigation"
                        :key="item.href"
                        :href="item.href"
                        class="crm-sidebar-link"
                        :class="{ 'is-active': currentPath === item.href || currentPath.startsWith(`${item.href}/`) }"
                    >
                        <span>
                            <span class="block text-sm font-semibold">{{ item.label }}</span>
                            <span class="block text-xs text-slate-500">{{ item.hint }}</span>
                        </span>
                    </Link>
                </nav>

                <div class="crm-accent-card rounded-[1.35rem] p-4 shadow-xl">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-white/70">Фокус этапа</p>
                    <p class="mt-3 text-lg font-semibold">Сначала оживляем фронт, затем закрываем недостающую бизнес-логику.</p>
                </div>

                <div class="rounded-[1.2rem] border border-slate-900/6 bg-white/70 p-4">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-slate-500">Пользователь</p>
                    <div class="mt-3 flex flex-col gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900">{{ user?.name ?? 'Гость' }}</p>
                            <p class="mt-1 break-all text-sm text-slate-500">{{ user?.email ?? 'Нет активной сессии' }}</p>
                        </div>
                        <button type="button" class="crm-button-ghost shrink-0 self-start whitespace-nowrap" @click="logout">Выйти</button>
                    </div>
                </div>
            </div>
        </aside>

        <main class="relative z-10 px-4 py-5 sm:px-6 lg:px-8 lg:py-8">
            <header class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="crm-kicker">Операционный центр</p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">{{ title }}</h2>
                    <p v-if="subtitle" class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 sm:text-base">
                        {{ subtitle }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link href="/leads" class="crm-button-ghost whitespace-nowrap">Открыть лиды</Link>
                    <Link href="/deals" class="crm-button whitespace-nowrap">Открыть сделки</Link>
                </div>
            </header>

            <slot />
        </main>
    </div>
</template>
