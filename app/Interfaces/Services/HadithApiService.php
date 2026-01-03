<?php

namespace App\Interfaces\Services;

interface HadithApiService
{
    public function search(string $phrase, string $currentPage, string $pageSize): string;
    public function help($bot): string;

}
