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
    const qty = tag.quantity || 1;
    const objectXp = getObjectXp(tag);
    let xp = 0;

    if (tag.type === 'brand-only') {
        xp += qty * 3;
    } else if (tag.type === 'material-only') {
        xp += qty * 2;
    } else if (tag.custom) {
        xp += qty; // primary custom tag key
        if (tag.brands?.length) {
            tag.brands.forEach((b) => (xp += (b.quantity || 1) * 3));
        }
        if (tag.materials?.length) {
            xp += tag.materials.length * qty * 2;
        }
        if (tag.customTags?.length) {
            xp += tag.customTags.length * qty;
        }
    } else {
        xp += qty * objectXp;
        if (tag.brands?.length) {
            tag.brands.forEach((b) => (xp += (b.quantity || 1) * 3));
        }
        if (tag.materials?.length) {
            xp += tag.materials.length * qty * 2;
        }
        if (tag.customTags?.length) {
            xp += tag.customTags.length * qty;
        }
    }

    if (tag.pickedUp && !tag.custom && tag.type !== 'brand-only' && tag.type !== 'material-only') {
        xp += qty * 5;
    }

    return xp;
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
 * e.g. "×1 · picked up +5" or "×1 · 2 materials (+4) · picked up +5"
 */
export function getTagBreakdownParts(tag) {
    const parts = [];
    const qty = tag.quantity || 1;
    const objectXp = getObjectXp(tag);

    if (tag.type === 'brand-only') {
        parts.push(`×${qty}`);
        parts.push(`brand (+${qty * 3})`);
    } else if (tag.type === 'material-only') {
        parts.push(`×${qty}`);
        parts.push(`material (+${qty * 2})`);
    } else if (tag.custom) {
        parts.push(`×${qty}`);
        parts.push(`custom (+${qty})`);

        const brandCount = tag.brands?.length || 0;
        if (brandCount > 0) {
            let brandXp = 0;
            tag.brands.forEach((b) => (brandXp += (b.quantity || 1) * 3));
            parts.push(`${brandCount} brand${brandCount > 1 ? 's' : ''} (+${brandXp})`);
        }

        const matCount = tag.materials?.length || 0;
        if (matCount > 0) {
            parts.push(`${matCount} material${matCount > 1 ? 's' : ''} (+${matCount * qty * 2})`);
        }

        const customCount = tag.customTags?.length || 0;
        if (customCount > 0) {
            parts.push(`${customCount} more custom (+${customCount * qty})`);
        }
    } else {
        if (objectXp > 1) {
            parts.push(`×${qty} @ +${objectXp}`);
        } else {
            parts.push(`×${qty}`);
        }

        const brandCount = tag.brands?.length || 0;
        if (brandCount > 0) {
            let brandXp = 0;
            tag.brands.forEach((b) => (brandXp += (b.quantity || 1) * 3));
            parts.push(`${brandCount} brand${brandCount > 1 ? 's' : ''} (+${brandXp})`);
        }

        const matCount = tag.materials?.length || 0;
        if (matCount > 0) {
            parts.push(`${matCount} material${matCount > 1 ? 's' : ''} (+${matCount * qty * 2})`);
        }

        const customCount = tag.customTags?.length || 0;
        if (customCount > 0) {
            parts.push(`${customCount} custom (+${customCount * qty})`);
        }
    }

    if (tag.pickedUp && !tag.custom && tag.type !== 'brand-only' && tag.type !== 'material-only') {
        parts.push(`picked up (+${qty * 5})`);
    }

    return parts;
}

/**
 * Get breakdown lines for the header hover panel.
 * Returns [{ label, xp }] — one line per tag + aggregated extras.
 */
export function getHeaderBreakdown(tags) {
    const lines = [];

    tags.forEach((tag) => {
        const qty = tag.quantity || 1;
        const objectXp = getObjectXp(tag);

        if (tag.type === 'brand-only') {
            lines.push({ label: `${formatKey(tag.brand?.key)} (×${qty})`, xp: qty * 3 });
        } else if (tag.type === 'material-only') {
            lines.push({ label: `${formatKey(tag.material?.key)} (×${qty})`, xp: qty * 2 });
        } else if (tag.custom) {
            lines.push({ label: `"${tag.key}" (×${qty})`, xp: qty });
        } else {
            lines.push({ label: `${formatKey(tag.object?.key)} (×${qty})`, xp: qty * objectXp });
        }
    });

    // Aggregate materials across all standard object tags
    let totalMaterialXp = 0;
    let totalMaterialCount = 0;
    tags.forEach((t) => {
        if (t.materials?.length && t.type !== 'material-only') {
            totalMaterialCount += t.materials.length;
            totalMaterialXp += t.materials.length * (t.quantity || 1) * 2;
        }
    });
    if (totalMaterialCount > 0) {
        lines.push({ label: `Materials (${totalMaterialCount})`, xp: totalMaterialXp });
    }

    // Aggregate brands
    let totalBrandXp = 0;
    let totalBrandCount = 0;
    tags.forEach((t) => {
        if (t.brands?.length && t.type !== 'brand-only') {
            t.brands.forEach((b) => {
                totalBrandCount++;
                totalBrandXp += (b.quantity || 1) * 3;
            });
        }
    });
    if (totalBrandCount > 0) {
        lines.push({ label: `Brands (${totalBrandCount})`, xp: totalBrandXp });
    }

    // Aggregate custom tags
    let totalCustomXp = 0;
    let totalCustomCount = 0;
    tags.forEach((t) => {
        if (t.customTags?.length) {
            totalCustomCount += t.customTags.length;
            totalCustomXp += t.customTags.length * (t.quantity || 1);
        }
    });
    if (totalCustomCount > 0) {
        lines.push({ label: `Custom tags (${totalCustomCount})`, xp: totalCustomXp });
    }

    // Aggregate picked up — per-object quantity, excludes brand-only/material-only/custom
    let pickedUpXp = 0;
    let pickedUpObjects = 0;
    tags.forEach((t) => {
        if (t.pickedUp && !t.custom && t.type !== 'brand-only' && t.type !== 'material-only') {
            const qty = t.quantity || 1;
            pickedUpXp += qty * 5;
            pickedUpObjects += qty;
        }
    });
    if (pickedUpObjects > 0) {
        lines.push({ label: `Picked up (${pickedUpObjects} object${pickedUpObjects > 1 ? 's' : ''})`, xp: pickedUpXp });
    }

    return lines;
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
