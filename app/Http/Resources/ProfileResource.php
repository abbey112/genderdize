<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
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
             'name' => $this->name,
            'gender' => $this->gender,
                'gender_probability' => $this->gender_probability,
                'sample_size' => $this->sample_size,
                'age' => $this->age,
                'age_group' => $this->age_group,
                'country_id' => $this->country_id,
                'country_name' => $this->country_name,
                'country_probability' => $this->country_probability,
                'created_at' => Carbon::now('UTC')
        ];

        return parent::toArray($request);
    }
}
