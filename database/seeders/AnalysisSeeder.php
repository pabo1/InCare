<?php

namespace Database\Seeders;

use App\Models\Analysis;
use Illuminate\Database\Seeder;

class AnalysisSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'name'      => 'Общий анализ крови',
                'code'      => 'CBC',
                'price'     => 15.00,
                'is_active' => true,
            ],
            [
                'name'      => 'Глюкоза',
                'code'      => 'GLU',
                'price'     => 8.50,
                'is_active' => true,
            ],
            [
                'name'      => 'Витамин D',
                'code'      => 'VITD',
                'price'     => 32.00,
                'is_active' => true,
            ],
            [
                'name'      => 'ТТГ',
                'code'      => 'TSH',
                'price'     => 21.00,
                'is_active' => true,
            ],
        ];

        foreach ($items as $item) {
            Analysis::updateOrCreate(
                ['code' => $item['code']],
                $item,
            );
        }
    }
}