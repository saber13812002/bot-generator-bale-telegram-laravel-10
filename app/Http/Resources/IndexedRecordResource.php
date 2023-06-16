<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IndexedRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'indexed_title' => $this->indexed_title,
            'indexed_content' => $this->indexed_content,
            'sura' => $this->indexable->sura,
            'indexable' => $this->indexable,
            'suras' => $this->indexable->suras,
        ];
    }
}
