<?php

namespace Database\Seeders;

use App\Models\LibraryBook;
use App\Models\LibraryBookMedia;
use App\Models\LibraryGenre;
use Illuminate\Database\Seeder;

class BookLibraryGenreSeeder extends Seeder
{
    /**
     * Seed default genres for a book-library bot instance.
     * Usage: BookLibraryGenreSeeder with bot_id set via env or argument.
     */
    public function run(): void
    {
        $botId = (int) (env('BOOK_LIBRARY_SEED_BOT_ID') ?: 0);
        if ($botId <= 0) {
            $this->command?->warn('BOOK_LIBRARY_SEED_BOT_ID not set — skipping genre/book seed.');
            return;
        }

        $genres = [
            1 => ['روانشناسی', 'کسب و کار', 'موفقیت', 'فلسفه', 'تاریخ'],
            2 => ['رمان', 'معرفت نفس', 'اقتصاد', 'جامعه شناسی', 'مدیریت'],
            3 => ['تربیت فرزند', 'روابط', 'بازاریابی', 'زندگینامه', 'علم'],
        ];

        $genreIds = [];
        foreach ($genres as $page => $names) {
            foreach ($names as $order => $name) {
                $genre = LibraryGenre::firstOrCreate(
                    ['bot_id' => $botId, 'name' => $name],
                    ['page' => $page, 'sort_order' => $order + 1, 'is_active' => true]
                );
                $genreIds[$name] = $genre->id;
            }
        }

        $sampleBooks = [
            [
                'title' => 'قدرت عادت',
                'genre' => 'روانشناسی',
                'audio' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
            ],
            [
                'title' => 'اثر مرکب',
                'genre' => 'موفقیت',
                'audio' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3',
            ],
            [
                'title' => 'مدیریت زمان',
                'genre' => 'مدیریت',
                'audio' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3',
            ],
        ];

        foreach ($sampleBooks as $data) {
            $book = LibraryBook::firstOrCreate(
                ['bot_id' => $botId, 'title' => $data['title']],
                ['description' => null, 'is_active' => true, 'random_eligible' => true]
            );

            if (isset($genreIds[$data['genre']])) {
                $book->genres()->syncWithoutDetaching([$genreIds[$data['genre']]]);
            }

            LibraryBookMedia::firstOrCreate(
                ['book_id' => $book->id, 'type' => 'audio'],
                ['content_url' => $data['audio']]
            );
        }

        $this->command?->info("✅ Genres and sample books seeded for bot_id={$botId}");
    }
}
