<?php

namespace App\Console\Commands;

use App\Services\SlaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckSlaCommand extends Command
{
    protected $signature   = 'sla:check';
    protected $description = 'Проверить SLA для всех лидов';

    public function __construct(protected SlaService $slaService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Начало проверки SLA...');

        try {
            $this->slaService->checkNewLeadsSla();
            $this->slaService->checkMissedCallSla();
            $this->slaService->checkChatSla();
            $this->slaService->checkWebsiteFormSla();
            $this->slaService->checkNightLeads();
            $this->slaService->checkZeroInbox();

            $this->info('Проверка SLA завершена успешно.');

            return 0; // success
        } catch (\Throwable $e) {
            Log::error('Ошибка при проверке SLA: ' . $e->getMessage());
            $this->error('Ошибка: ' . $e->getMessage());

            return 1; // failure
        }
    }
}
