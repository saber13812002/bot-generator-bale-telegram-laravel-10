<?php

namespace App\Interfaces\Repositories;

interface HadithApiRepository
{
    public function call(string $phrase, string $currentPage, string $pageSize);
}
