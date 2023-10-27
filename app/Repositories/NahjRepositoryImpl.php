<?php

namespace App\Repositories;

use App\Interfaces\Repositories\NahjRepository;
use App\Models\Nahj;
use Illuminate\Database\Eloquent\Model;

class NahjRepositoryImpl implements NahjRepository
{

    /**
     */
    public function list(string $phrase, string $currentPage, string $pageSize)
    {
        $items = Nahj::query()
            ->orderByDesc('category', 'number')
            ->paginate(perPage: $pageSize, page: $currentPage);
        return $items;
    }

    public function item(int $id, string $currentPage, string $pageSize): Model
    {
        return Nahj::query()->where("id", $id)->first();
    }
}
