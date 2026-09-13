/**
 * Shared XP calculation logic for the tagging UI.
 *
 * XP values match backend XpScore enum:
 *   Upload=5, Object=1, Brand=3, Material=2, CustomTag=1, PickedUp=5
 *   Special objects: bags_litter=10, dumping+small=10, dumping+medium=25, dumping+large=50
 */

const SPECIAL_OBJECT_XP = { dumping_small: 10, dumping_medium: 25, dumping_large: 50, bags_litter: 10 };
const DUMPING_TYPE_XP = { small: 10, medium: 25, large: 50 };

const formatKey = (key) => {
    if (!key) return '';
    return key.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

/**
 * Get the XP value for an object, accounting for type-based overrides (e.g., dumping + size).
 */
function getObjectXp(tag) {
    const key = tag.object?.key;
    if (SPECIAL_OBJECT_XP[key]) return SPECIAL_OBJECT_XP[key];
    if (key === 'dumping' && tag.typeKey) return DUMPING_TYPE_XP[tag.typeKey] || 1;
    return 1;
}

/**
 * Calculate XP for a single tag (excludes upload bonus).
 */
export function calculateTagXp(tag) {
    return xpParts(tag).reduce((total, part) => total + part.xp, 0);
}

// - Count every extra, including extras attached to a standalone brand or material.
// - Brand quantities are independent; materials and custom tags use the observation quantity.
function xpParts(tag) {
    const qty = tag.quantity ?? 1;
    const parts = [];
    if (tag.object) parts.push({ label: formatKey(tag.object.key), xp: qty * getObjectXp(tag) });
    const brands = [...(tag.brands || [])];
    if (tag.type === 'brand-only') brands.unshift({ ...tag.brand, quantity: tag.brand.quantity ?? qty });
    const materials = [...(tag.materials || [])];
    if (tag.type === 'material-only') materials.unshift(tag.material);
    const customs = [...(tag.customTags || [])];
    if (tag.custom) customs.unshift(tag.key);
    for (const brand of new Map(brands.map((item) => [item.id, item])).values()) {
        parts.push({ label: formatKey(brand.key) || 'Brand', xp: (brand.quantity ?? 1) * 3 });
    }
    for (const material of new Map(materials.map((item) => [item.id, item])).values()) {
        parts.push({ label: formatKey(material.key) || 'Material', xp: qty * 2 });
    }
    for (const key of new Set(customs)) parts.push({ label: key, xp: qty });
    if (tag.object && tag.pickedUp === true) parts.push({ label: 'Picked up', xp: qty * 5 });
    return parts;
}

/**
 * Calculate total XP from tags only (upload +5 is already awarded at upload time).
 */
export function calculateTotalXp(tags) {
    let xp = 0;
    tags.forEach((tag) => (xp += calculateTagXp(tag)));
    return xp;
}

/**
 * Get a compact breakdown string for a single tag (no upload bonus).
 * e.g. "×2 · Butts (+2) · Marlboro (+3) · Picked up (+10)"
 */
export function getTagBreakdownParts(tag) {
    return [`×${tag.quantity ?? 1}`, ...xpParts(tag).map((part) => `${part.label} (+${part.xp})`)];
}

/**
 * Get breakdown lines for the header hover panel.
 * Returns [{ label, xp }] — one line per object, extra and collection bonus across all tags.
 */
export function getHeaderBreakdown(tags) {
    return tags.flatMap((tag) => xpParts(tag));
}

/**
 * Get a one-line toast summary string.
 * e.g. "Upload · 2 tags · 1 picked up · 3 materials"
 */
export function getToastSummary(tags) {
    const parts = [];
    parts.push(`${tags.length} tag${tags.length !== 1 ? 's' : ''}`);

    const pickedUpCount = tags.filter((t) => t.pickedUp).length;
    if (pickedUpCount > 0) {
        parts.push(`${pickedUpCount} picked up`);
    }

    let totalMaterials = 0;
    let totalBrands = 0;
    tags.forEach((t) => {
        totalMaterials += t.materials?.length || 0;
        if (t.type === 'material-only') totalMaterials++;
        totalBrands += t.brands?.length || 0;
        if (t.type === 'brand-only') totalBrands++;
    });

    if (totalMaterials > 0) parts.push(`${totalMaterials} material${totalMaterials > 1 ? 's' : ''}`);
    if (totalBrands > 0) parts.push(`${totalBrands} brand${totalBrands > 1 ? 's' : ''}`);

    return parts.join(' \u00B7 ');
}
