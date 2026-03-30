<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Зарегистрировать команды ядра приложения.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * Определить расписание запланированных задач.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Проверка SLA каждые 5 минут
        $schedule->command('sla:check')->everyFiveMinutes();

        // Ежедневная проверка правила «Зеро» в начале смены (в 9:00)
        $schedule->command('sla:check')->dailyAt('09:00');
    }

    /**
     * Регистрировать хуки приложения.
     */
    protected function boot(): void
    {
        //
    }
}
