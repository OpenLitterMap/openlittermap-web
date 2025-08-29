<?php

namespace App\Http\Requests\Location;

use Illuminate\Validation\Rule;

class IndexRequest extends BaseLocationRequest
{
    public function rules(): array
    {
        return [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'sort_by' => Rule::in(['total_litter', 'total_photos']), // v1 only
            'sort_dir' => Rule::in(['asc', 'desc']),
            'parent_id' => 'integer|min:1',
        ];
    }

    public function getSortBy(): string
    {
        return $this->validated()['sort_by'] ?? 'total_litter';
    }

    public function getSortDir(): string
    {
        return $this->validated()['sort_dir'] ?? 'desc';
    }

    public function getPage(): int
    {
        return (int) ($this->validated()['page'] ?? 1);
    }

    public function getPerPage(): int
    {
        return (int) ($this->validated()['per_page'] ?? 50);
    }

    public function getParentId(): ?int
    {
        return isset($this->validated()['parent_id'])
            ? (int) $this->validated()['parent_id']
            : null;
    }
}
