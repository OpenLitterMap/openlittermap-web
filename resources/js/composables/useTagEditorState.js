// - Pure card logic shared by every photo editor and the Node test bridge.
// - Each card is one observation; never merge quantities or collection states.
export function pickedUpFromApi(value) {
    if (value === true || value === 1) return true;
    if (value === false || value === 0) return false;
    return null;
}

export const typeKeyFor = (tagsStore, typeId) =>
    typeId ? (tagsStore.types?.find((type) => type.id === typeId)?.key ?? null) : null;

export function cardsFromApiTags(newTags = [], tagsStore = {}) {
    return newTags.map((tag, index) => {
        const brands = [],
            materials = [],
            customTags = [];
        for (const extra of tag.extra_tags || []) {
            if (!extra.tag) continue;
            if (extra.type === 'brand') brands.push({ ...extra.tag, quantity: extra.quantity ?? 1 });
            if (extra.type === 'material') materials.push({ ...extra.tag });
            if (extra.type === 'custom_tag') customTags.push(extra.tag.key);
        }
        const card = {
            id: `existing-${tag.id ?? index}`,
            quantity: tag.quantity ?? 1,
            pickedUp: pickedUpFromApi(tag.picked_up),
            brands,
            materials,
            customTags,
        };
        if (tag.object) {
            return {
                ...card,
                object: { ...tag.object },
                cloId: tag.category_litter_object_id ?? tagsStore.getCloId?.(tag.category?.id, tag.object.id) ?? null,
                categoryId: tag.category?.id ?? null,
                categoryKey: tag.category?.key ?? null,
                typeId: tag.litter_object_type_id ?? null,
                typeKey: typeKeyFor(tagsStore, tag.litter_object_type_id),
            };
        }
        // - Put a real extra in the title; keep the remaining extras on the same card.
        // - A brand plus paper stays a brand plus paper, never a new custom tag.
        if (brands.length) {
            const [brand, ...additional] = brands;
            return { ...card, type: 'brand-only', brand, brands: additional };
        }
        if (materials.length) {
            const [material, ...additional] = materials;
            return { ...card, type: 'material-only', material, materials: additional };
        }
        if (customTags.length) return { ...card, custom: true, key: customTags[0], customTags: customTags.slice(1) };
        throw new Error('A saved observation has no object or extra tag. Refresh the photo before editing.');
    });
}

export function payloadFromCards(cards) {
    return cards.map((tag) => {
        const common = {
            quantity: tag.quantity,
            picked_up: pickedUpFromApi(tag.pickedUp),
            brands: (tag.brands || []).map((brand) => ({ id: brand.id, quantity: brand.quantity ?? 1 })),
            materials: (tag.materials || []).map((material) => material.id),
            custom_tags: [...(tag.customTags || [])],
        };
        if (tag.cloId)
            return { ...common, category_litter_object_id: tag.cloId, litter_object_type_id: tag.typeId ?? null };
        if (tag.object)
            return {
                ...common,
                object: { id: tag.object.id, key: tag.object.key },
                ...(tag.categoryId != null ? { category_id: tag.categoryId } : {}),
                litter_object_type_id: tag.typeId ?? null,
            };
        if (tag.type === 'brand-only') {
            // - The primary brand can have a quantity different from the observation's quantity.
            return {
                ...common,
                brand_only: true,
                brand: { id: tag.brand.id, quantity: tag.brand.quantity ?? tag.quantity },
            };
        }
        if (tag.type === 'material-only') return { ...common, material_only: true, material: { id: tag.material.id } };
        if (tag.custom) return { ...common, custom: true, key: tag.key };
        throw new Error('Choose an object, brand, material or custom tag before saving.');
    });
}

// - An object card with neither a CLO ID nor a recorded category cannot be saved.
export const hasUnresolvedCards = (cards) => cards.some((tag) => tag.object && !tag.cloId && tag.categoryId == null);

// - Explicitly changing a lone brand's count changes its primary brand quantity too.
// - Mixed rows retain independent brand quantities while their observation quantity changes.
export function setCardQuantity(tag, quantity) {
    tag.quantity = Math.max(1, Math.min(100, quantity));
    if (tag.type === 'brand-only' && !(tag.brands?.length || tag.materials?.length || tag.customTags?.length)) {
        tag.brand = { ...tag.brand, quantity: tag.quantity };
    }
}

export function setCardType(tag, typeId, tagsStore) {
    tag.typeId = typeId;
    tag.typeKey = typeKeyFor(tagsStore, typeId);
}

const newCardId = () => Math.random().toString(16).slice(2);

// - One card per picker selection. Object cards default to the given collection state; standalone cards to unknown.
export function cardFromSelection(selected, tagsStore, pickedUp = true) {
    const objectCard = (object, extra) => ({
        id: newCardId(),
        object,
        quantity: 1,
        pickedUp,
        brands: [],
        materials: [],
        customTags: [],
        ...extra,
    });
    if (selected.type === 'object') {
        return objectCard(selected.raw, {
            cloId: selected.cloId || null,
            categoryId: selected.categoryId || null,
            categoryKey: selected.categoryKey || null,
            typeId: null,
            typeKey: null,
        });
    }
    if (selected.type === 'type') {
        return objectCard(selected.raw?.object, {
            cloId: selected.cloId,
            categoryId: selected.raw?.category?.id || null,
            categoryKey: selected.raw?.category?.key || null,
            typeId: selected.typeId,
            typeKey: typeKeyFor(tagsStore, selected.typeId),
        });
    }
    if (selected.type === 'brand')
        return { id: newCardId(), brand: selected.raw, quantity: 1, pickedUp: null, type: 'brand-only' };
    if (selected.type === 'material') {
        return { id: newCardId(), material: selected.raw, quantity: 1, pickedUp: null, type: 'material-only' };
    }
    return null;
}

export const cardFromCustomTag = (customTag) => ({
    id: newCardId(),
    custom: true,
    key: customTag.key,
    quantity: 1,
    pickedUp: null,
});

const detailLists = { brand: 'brands', material: 'materials', object: 'objects', custom: 'customTags' };
const sameDetail = (type, item, value) => (type === 'custom' ? item === value : item.id === value.id);

export function addCardDetail(tag, detail) {
    const list = detailLists[detail.type];
    if (!list) return;
    tag[list] ??= [];
    if (!tag[list].some((item) => sameDetail(detail.type, item, detail.value))) tag[list].push(detail.value);
}

export function removeCardDetail(tag, detail) {
    const list = detailLists[detail.type];
    if (!list) return;
    tag[list] = (tag[list] || []).filter((item) => !sameDetail(detail.type, item, detail.value));
}

// - Search index for the queue and modal pickers: one entry per (object, category) pair,
//   one per approved type on a pairing, one per brand and one per material.
export function buildSearchableTags(tagsStore) {
    const entry = (id, key, type, extra) => ({ id, key, lowerKey: key.toLowerCase(), text: key, type, ...extra });
    const byId = (items) => new Map((items || []).map((item) => [item.id, item]));
    const cloByPair = new Map(
        (tagsStore.categoryObjects || []).map((co) => [`${co.category_id}:${co.litter_object_id}`, co.id])
    );
    const types = byId(tagsStore.types),
        clos = byId(tagsStore.categoryObjects),
        objects = byId(tagsStore.objects),
        categories = byId(tagsStore.categories);
    const tags = [];
    for (const obj of tagsStore.objects || []) {
        if (obj.categories?.length) {
            for (const cat of obj.categories) {
                tags.push(
                    entry(`obj-${obj.id}-cat-${cat.id}`, obj.key, 'object', {
                        categoryId: cat.id,
                        categoryKey: cat.key,
                        cloId: cloByPair.get(`${cat.id}:${obj.id}`) ?? null,
                        raw: obj,
                    })
                );
            }
        } else {
            tags.push(
                entry(`obj-${obj.id}`, obj.key, 'object', {
                    categoryId: null,
                    categoryKey: null,
                    cloId: null,
                    raw: obj,
                })
            );
        }
    }
    for (const cot of tagsStore.categoryObjectTypes || []) {
        const typeObj = types.get(cot.litter_object_type_id);
        const clo = clos.get(cot.category_litter_object_id);
        const obj = clo && objects.get(clo.litter_object_id);
        const cat = clo && categories.get(clo.category_id);
        if (!typeObj || !obj || !cat) continue;
        tags.push(
            entry(`type-${cot.category_litter_object_id}-${cot.litter_object_type_id}`, typeObj.key, 'type', {
                cloId: clo.id,
                typeId: typeObj.id,
                objectKey: obj.key,
                categoryKey: cat.key,
                raw: { type: typeObj, object: obj, category: cat, clo },
            })
        );
    }
    for (const brand of tagsStore.brands || [])
        tags.push(entry(`brand-${brand.id}`, brand.key, 'brand', { raw: brand }));
    for (const material of tagsStore.materials || []) {
        tags.push(entry(`mat-${material.id}`, material.key, 'material', { raw: material }));
    }
    return tags;
}
