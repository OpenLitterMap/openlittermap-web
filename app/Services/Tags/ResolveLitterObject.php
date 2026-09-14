<?php

namespace App\Services\Tags;

use App\Enums\CategoryKey;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use Illuminate\Validation\ValidationException;

class ResolveLitterObject
{
    /**
     * - Follow retired objects before choosing the category's CLO.
     * - Example: other/plasticBags becomes other/plastic_bag, even without an old CLO.
     * - The latest recorded replacement type wins; otherwise validate the submitted type.
     * - Without a category, prefer an existing other CLO; otherwise require one category.
     *
     * @return array{0: CategoryObject, 1: ?int}
     */
    public function resolve(LitterObject $object, ?int $categoryId, ?int $typeId = null): array
    {
        $seen = [];
        while ($object->retired_at !== null) {
            if (isset($seen[$object->id])) {
                throw ValidationException::withMessages(['tags' => 'Invalid object replacement chain.']);
            }
            $seen[$object->id] = true;
            $typeId = $object->merged_into_type_id === null ? $typeId : (int) $object->merged_into_type_id;
            $object = LitterObject::find($object->merged_into_id);
            if ($object === null) {
                throw ValidationException::withMessages(['tags' => 'The replacement object is missing.']);
            }
        }

        $choices = CategoryObject::where('litter_object_id', $object->id);
        if ($categoryId !== null) {
            $choices->where('category_id', $categoryId);
        } elseif ($typeId !== null) {
            $choices->whereHas('types', fn ($query) => $query->where('litter_object_types.id', $typeId));
        }
        $other = $categoryId === null
            ? (clone $choices)->whereHas('category', fn ($query) => $query->where('key', CategoryKey::Other->value))->first()
            : null;
        $clos = $other ? collect([$other]) : $choices->limit(2)->get();
        if ($clos->count() !== 1) {
            throw ValidationException::withMessages(['tags' => $clos->isEmpty()
                ? 'Category does not contain the replacement object.' : 'Choose a category for this object.']);
        }

        $clo = $clos->first();
        if ($typeId !== null && ! $clo->types()->where('litter_object_types.id', $typeId)->exists()) {
            throw ValidationException::withMessages(['tags' => 'Type is not allowed for this category and object.']);
        }

        return [$clo, $typeId];
    }

    /** - Saved quick tags and mobile requests identify the category and object by CLO ID. */
    public function fromClo(int $cloId, ?int $typeId = null): array
    {
        $clo = CategoryObject::with('litterObject')->find($cloId);
        if ($clo === null || $clo->litterObject === null) {
            throw ValidationException::withMessages(['tags' => 'Invalid category_litter_object_id.']);
        }

        return $this->resolve($clo->litterObject, (int) $clo->category_id, $typeId);
    }
}
