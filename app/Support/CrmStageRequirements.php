<?php

namespace App\Support;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineStage;
use Illuminate\Validation\ValidationException;

class CrmStageRequirements
{
    public static function assertLeadCanBePlacedInStage(
        PipelineStage $stage,
        array $attributes,
        ?Lead $lead = null,
        bool $allowSuccessStage = false,
        string $stageField = 'pipeline_stage_id'
    ): void {
        $errors = [];

        if (self::isLeadSuccessStage($stage) && ! $allowSuccessStage) {
            $errors[$stageField] = 'Успешная конвертация лида доступна только через endpoint convert.';
        }

        if ($stage->sort_order === 4) {
            $state = [
                'name' => self::value($attributes, $lead, 'name'),
                'request_type' => self::value($attributes, $lead, 'request_type'),
                'branch' => self::value($attributes, $lead, 'branch'),
            ];

            if (! self::filled($state['name'])) {
                $errors['name'] = 'Для стадии квалификации нужно указать ФИО клиента.';
            }

            if (! self::filled($state['request_type'])) {
                $errors['request_type'] = 'Для стадии квалификации нужно указать тип запроса.';
            }

            if (! self::filled($state['branch'])) {
                $errors['branch'] = 'Для стадии квалификации нужно указать филиал.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function assertLeadReadyForConversion(Lead $lead, array $overrides = []): void
    {
        $state = [
            'name' => $overrides['name'] ?? $lead->name,
            'request_type' => $overrides['request_type'] ?? $lead->request_type,
            'branch' => $overrides['branch'] ?? $lead->branch,
        ];

        $errors = [];

        if (! self::filled($state['name'])) {
            $errors['name'] = 'Перед конвертацией нужно указать ФИО клиента.';
        }

        if (! self::filled($state['request_type'])) {
            $errors['request_type'] = 'Перед конвертацией нужно указать тип запроса.';
        }

        if (! self::filled($state['branch'])) {
            $errors['branch'] = 'Перед конвертацией нужно указать филиал.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function assertDealCanBePlacedInStage(
        PipelineStage $stage,
        array $attributes,
        ?Deal $deal = null,
        ?bool $hasAnalysesOverride = null
    ): void {
        $state = [
            'branch' => self::value($attributes, $deal, 'branch'),
            'appointment_at' => self::value($attributes, $deal, 'appointment_at'),
            'payment_status' => self::value($attributes, $deal, 'payment_status'),
            'cancel_reason' => self::value($attributes, $deal, 'cancel_reason'),
        ];

        $hasAnalyses = $hasAnalysesOverride ?? ($deal ? $deal->analyses()->exists() : false);
        $errors = [];

        if (in_array($stage->sort_order, [2, 3, 4, 5, 6, 7], true)) {
            if (! self::filled($state['branch'])) {
                $errors['branch'] = 'Для этой стадии нужно указать филиал.';
            }

            if (! self::filled($state['appointment_at'])) {
                $errors['appointment_at'] = 'Для этой стадии нужно указать дату и время записи.';
            }

            if (! $hasAnalyses) {
                $errors['analyses'] = 'Для этой стадии нужно выбрать хотя бы один анализ.';
            }
        }

        if (in_array($stage->sort_order, [4, 5, 6], true) && $state['payment_status'] !== Deal::PAYMENT_PAID) {
            $errors['payment_status'] = 'Для этой стадии статус оплаты должен быть paid.';
        }

        if (($stage->sort_order === 8 || ($stage->is_final && $stage->is_fail)) && ! self::filled($state['cancel_reason'])) {
            $errors['cancel_reason'] = 'Для стадии отказа нужно указать причину отмены.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private static function isLeadSuccessStage(PipelineStage $stage): bool
    {
        return $stage->pipeline?->type === 'leads' && $stage->is_final && ! $stage->is_fail;
    }

    private static function value(array $attributes, object|null $model, string $field): mixed
    {
        if (array_key_exists($field, $attributes)) {
            return $attributes[$field];
        }

        return $model?->{$field};
    }

    private static function filled(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null;
    }
}