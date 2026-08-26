<?php

namespace Database\Seeders;

use App\Models\TipoCourt;
use Illuminate\Database\Seeder;

final class TipoCourtSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'name' => 'Padel',
                'description' => 'Cancha de pádel',
            ],
            [
                'name' => 'Tenis',
                'description' => 'Cancha de tenis',
            ],
            [
                'name' => 'Fútbol 5',
                'description' => 'Cancha de fútbol 5',
            ],
            [
                'name' => 'Fútbol 7',
                'description' => 'Cancha de fútbol 7',
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoCourt::updateOrCreate(
                ['name' => $tipo['name']],
                [
                    'description' => $tipo['description'],
                ]
            );
        }
    }
}
