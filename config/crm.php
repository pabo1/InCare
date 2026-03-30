<?php

return [
    'branches' => array_values(array_filter(array_map(
        static fn (string $value) => trim($value),
        explode(',', (string) env('CRM_BRANCHES', '')),
    ))),

    'lead_sources' => [
        ['value' => 'telegram', 'label' => 'Telegram'],
        ['value' => 'sipuni', 'label' => 'Sipuni'],
        ['value' => 'instagram', 'label' => 'Instagram Direct'],
        ['value' => 'facebook', 'label' => 'Facebook'],
        ['value' => 'form', 'label' => 'CRM форма'],
        ['value' => 'site_widget', 'label' => 'Виджет сайта'],
    ],

    'request_types' => [
        ['value' => 'analyses', 'label' => 'Анализы'],
        ['value' => 'doctor', 'label' => 'Врач'],
        ['value' => 'nurse', 'label' => 'Медсестра на дом'],
        ['value' => 'info', 'label' => 'Инфо-запрос'],
    ],

    'payment_statuses' => [
        ['value' => 'unpaid', 'label' => 'Не оплачено'],
        ['value' => 'paid', 'label' => 'Оплачено'],
    ],
];