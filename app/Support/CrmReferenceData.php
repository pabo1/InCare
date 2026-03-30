<?php

namespace App\Support;

class CrmReferenceData
{
    /**
     * Универсальный метод — возвращает массив [{value, label}]
     */
    public static function options(string $key): array
    {
        return match ($key) {
            'lead_sources'    => self::leadSources(),
            'request_types'   => self::requestTypes(),
            'payment_statuses' => self::paymentStatuses(),
            'task_types'      => self::taskTypes(),
            'task_statuses'   => self::taskStatuses(),
            default           => [],
        };
    }

    /**
     * Список филиалов — используется и в лидах и в сделках
     */
    public static function branchOptions(): array
    {
        return [
            ['value' => 'tashkent_1',   'label' => 'Ташкент — Филиал 1'],
            ['value' => 'tashkent_2',   'label' => 'Ташкент — Филиал 2'],
            ['value' => 'samarkand',    'label' => 'Самарканд'],
            ['value' => 'fergana',      'label' => 'Фергана'],
        ];
    }

    private static function leadSources(): array
    {
        return [
            ['value' => 'telegram',    'label' => 'Telegram'],
            ['value' => 'instagram',   'label' => 'Instagram'],
            ['value' => 'sipuni',      'label' => 'Телефония (Sipuni)'],
            ['value' => 'form',        'label' => 'Форма на сайте'],
            ['value' => 'site_widget', 'label' => 'Виджет на сайте'],
        ];
    }

    private static function requestTypes(): array
    {
        return [
            ['value' => 'analyses', 'label' => 'Анализы'],
            ['value' => 'doctor',   'label' => 'Врач'],
            ['value' => 'nurse',    'label' => 'Медсестра на дом'],
            ['value' => 'info',     'label' => 'Инфо-запрос'],
        ];
    }

    private static function paymentStatuses(): array
    {
        return [
            ['value' => 'pending', 'label' => 'Не оплачено'],
            ['value' => 'paid',    'label' => 'Оплачено'],
        ];
    }

    private static function taskTypes(): array
    {
        return [
            ['value' => 'call',         'label' => 'Звонок'],
            ['value' => 'reactivation', 'label' => 'Реактивация'],
            ['value' => 'remind',       'label' => 'Напоминание'],
        ];
    }

    private static function taskStatuses(): array
    {
        return [
            ['value' => 'pending',   'label' => 'Ожидает'],
            ['value' => 'done',      'label' => 'Выполнено'],
            ['value' => 'cancelled', 'label' => 'Отменено'],
        ];
    }
}
