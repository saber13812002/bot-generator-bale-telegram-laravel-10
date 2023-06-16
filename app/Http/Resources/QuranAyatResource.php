<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuranAyatResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    // app/Http/Resources/RatingResource.php

    public function toArray(Request $request): array
    {
//        dd($this);
        return [
            'id' => $this->id,
            'text' => $this->text,
//            'created_at' => (string)$this->created_at,
//            'updated_at' => (string)$this->updated_at,
            'suras' => $this->suras,
        ];
    }


}
