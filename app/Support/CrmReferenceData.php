<?php

namespace App\Support;

class CrmReferenceData
{
    public static function options(string $key): array
    {
        return match ($key) {
            'lead_sources' => self::leadSources(),
            'request_types' => self::requestTypes(),
            'payment_statuses' => self::paymentStatuses(),
            'task_types' => self::taskTypes(),
            'task_statuses' => self::taskStatuses(),
            default => [],
        };
    }

    public static function label(string $key, ?string $value, ?string $fallback = null): ?string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        foreach (self::options($key) as $option) {
            if (($option['value'] ?? null) === $value) {
                return $option['label'];
            }
        }

        if ($key === 'branches') {
            foreach (self::branchOptions() as $option) {
                if (($option['value'] ?? null) === $value) {
                    return $option['label'];
                }
            }
        }

        return $fallback ?? $value;
    }

    public static function branchOptions(): array
    {
        return [
            ['value' => 'tashkent_1', 'label' => 'Ташкент, филиал 1'],
            ['value' => 'tashkent_2', 'label' => 'Ташкент, филиал 2'],
            ['value' => 'samarkand', 'label' => 'Самарканд'],
            ['value' => 'fergana', 'label' => 'Фергана'],
        ];
    }

    private static function leadSources(): array
    {
        return [
            ['value' => 'telegram', 'label' => 'Telegram'],
            ['value' => 'instagram', 'label' => 'Instagram'],
            ['value' => 'sipuni', 'label' => 'Телефония (Sipuni)'],
            ['value' => 'form', 'label' => 'Форма на сайте'],
            ['value' => 'site_widget', 'label' => 'Виджет на сайте'],
        ];
    }

    private static function requestTypes(): array
    {
        return [
            ['value' => 'analyses', 'label' => 'Анализы'],
            ['value' => 'doctor', 'label' => 'Врач'],
            ['value' => 'nurse', 'label' => 'Медсестра на дом'],
            ['value' => 'info', 'label' => 'Инфо-запрос'],
        ];
    }

    private static function paymentStatuses(): array
    {
        return [
            ['value' => 'unpaid', 'label' => 'Не оплачено'],
            ['value' => 'pending', 'label' => 'Ожидает оплаты'],
            ['value' => 'paid', 'label' => 'Оплачено'],
            ['value' => 'partial', 'label' => 'Частично оплачено'],
        ];
    }

    private static function taskTypes(): array
    {
        return [
            ['value' => 'call', 'label' => 'Звонок'],
            ['value' => 'reactivation', 'label' => 'Реактивация'],
            ['value' => 'remind', 'label' => 'Напоминание'],
        ];
    }

    private static function taskStatuses(): array
    {
        return [
            ['value' => 'pending', 'label' => 'Ожидает'],
            ['value' => 'done', 'label' => 'Выполнено'],
            ['value' => 'cancelled', 'label' => 'Отменено'],
        ];
    }
}
