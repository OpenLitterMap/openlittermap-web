<?php

namespace App\Http\Requests\Location;

use Illuminate\Validation\Rule;

class TagsRequest extends BaseLocationRequest
{
    public function rules(): array
    {
        return [
            'dimension' => Rule::in(['objects', 'brands', 'materials', 'categories']),
            'limit' => 'integer|min:1|max:100',
        ];
    }

    public function getDimension(): string
    {
        return $this->validated()['dimension'] ?? 'objects';
    }

    public function getLimit(): int
    {
        return (int) ($this->validated()['limit'] ?? 20);
    }
}
