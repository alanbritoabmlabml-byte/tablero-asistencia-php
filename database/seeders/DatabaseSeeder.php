<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Usuario inicial. La contrasena se toma del entorno; hay que cambiarla
        // en el primer ingreso y crear el resto de usuarios desde la base.
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'sistemas@plasticoscarmen.com')],
            [
                'name' => env('ADMIN_NAME', 'Administrador del tablero'),
                'password' => env('ADMIN_PASSWORD', 'cambiar-esta-clave'),
                'rol' => 'administrador',
                'activo' => true,
            ],
        );
    }
}
