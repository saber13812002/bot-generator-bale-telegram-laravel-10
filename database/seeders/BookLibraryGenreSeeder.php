<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BookLibraryGenreSeeder extends Seeder
{
    /**
     * @deprecated Use ContentCategorySeeder — redirects for backward compatibility.
     */
    public function run(): void
    {
        $this->call(ContentCategorySeeder::class);
    }
}
