<?php

namespace App\Interfaces\Services;

interface NahjService
{
    public function search(string $phrase, string $currentPage, string $pageSize): string;

    public function list($bot, string $phrase, string $currentPage, string $pageSize);

    public function item($bot, int $id, string $currentPage, string $pageSize);

    public function help($bot): string;
}
