<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            // Бот раньше каталога: каталог привязывается ко всем существующим ботам.
            BotSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
