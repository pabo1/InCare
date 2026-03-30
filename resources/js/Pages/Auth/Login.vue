<script setup>
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({
    demoCredentials: {
        type: Object,
        default: () => ({
            email: 'admin@clinic.test',
            password: 'password',
        }),
    },
})

const form = useForm({
    email: props.demoCredentials.email,
    password: props.demoCredentials.password,
    remember: true,
})

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Вход" />

    <div class="crm-login-grid relative overflow-hidden">
        <div class="relative flex items-center px-6 py-10 sm:px-10 lg:px-16">
            <div class="crm-orb one"></div>
            <div class="crm-orb two"></div>

            <div class="relative z-10 max-w-2xl">
                <p class="crm-kicker">InCare CRM</p>
                <h1 class="mt-4 text-5xl font-extrabold tracking-tight text-slate-950 sm:text-6xl">
                    Фронт для клиники, где лиды и сделки больше не живут отдельно.
                </h1>
                <p class="mt-6 max-w-xl text-base leading-8 text-slate-600 sm:text-lg">
                    Первая версия интерфейса уже понимает две воронки: квалификацию лидов и производственный процесс по сдаче анализов.
                </p>

                <div class="mt-10 grid gap-4 sm:grid-cols-2">
                    <div class="crm-panel p-5">
                        <p class="crm-kicker">Что видно сразу</p>
                        <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-700">
                            <li>Лиды по стадиям и источникам.</li>
                            <li>Сделки по записи, оплате и анализам.</li>
                            <li>История движения и задачи по карточке.</li>
                        </ul>
                    </div>
                    <div class="crm-accent-card rounded-[1.4rem] p-5 shadow-xl">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-white/70">Тестовый доступ</p>
                        <p class="mt-4 font-mono text-sm text-white/90">{{ demoCredentials.email }}</p>
                        <p class="mt-1 font-mono text-sm text-white/90">{{ demoCredentials.password }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-center px-6 py-10 sm:px-10 lg:px-16">
            <div class="crm-panel crm-panel-strong w-full max-w-xl p-6 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="crm-kicker">Вход в систему</p>
                        <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950">Откройте рабочее пространство</h2>
                    </div>
                </div>

                <form class="mt-8 space-y-5" @submit.prevent="submit">
                    <div>
                        <label class="crm-label" for="email">Email</label>
                        <input id="email" v-model="form.email" type="email" class="crm-input" placeholder="admin@clinic.test" />
                        <p v-if="form.errors.email" class="mt-2 text-sm text-rose-600">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="crm-label" for="password">Пароль</label>
                        <input id="password" v-model="form.password" type="password" class="crm-input" placeholder="password" />
                        <p v-if="form.errors.password" class="mt-2 text-sm text-rose-600">{{ form.errors.password }}</p>
                    </div>

                    <label class="flex items-center gap-3 rounded-2xl border border-slate-900/8 bg-white/70 px-4 py-3 text-sm text-slate-700">
                        <input v-model="form.remember" type="checkbox" class="size-4 rounded border-slate-300 text-orange-500 focus:ring-orange-400" />
                        Запомнить это устройство
                    </label>

                    <button type="submit" class="crm-button w-full" :disabled="form.processing">
                        {{ form.processing ? 'Входим...' : 'Войти в CRM' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
