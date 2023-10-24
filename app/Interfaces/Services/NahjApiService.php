<?php

namespace App\Interfaces\Services;

interface NahjApiService
{
    public function search(string $phrase, string $currentPage, string $pageSize): string;
    public function help($bot): string;
}
