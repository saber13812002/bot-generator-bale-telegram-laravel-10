<?php

namespace App\Interfaces\Repositories;

interface NahjRepository
{
    public function list(string $phrase, string $currentPage, string $pageSize);

    public function item(int $id, string $currentPage, string $pageSize);
}
