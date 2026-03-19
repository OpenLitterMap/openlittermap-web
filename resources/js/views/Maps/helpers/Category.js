/**
 * Category class for managing category colors and properties
 */
export class Category {
    /**
     * Static color mapping for all categories
     * Using representative, realistic colors for each category
     */
    static COLORS = {
        // Primary categories
        smoking: '#d4a574', // Cigarette filter yellowy-brown
        food: '#ff6b35', // Warm orange-red (food packaging)
        coffee: '#6f4e37', // Coffee brown
        alcohol: '#f5a623', // Golden beer/whiskey
        softdrinks: '#87ceeb', // Pale blue (like cola/pepsi branding)
        sanitary: '#dc143c', // Crimson red (medical/sanitary)
        coastal: '#006994', // Deep ocean blue
        dumping: '#4a4a4a', // Dark gray (waste/garbage)
        industrial: '#ff7f00', // Safety orange
        brands: '#9b59b6', // Purple (corporate/branded)
        dogshit: '#8b4513', // Dark brown
        art: '#ff1493', // Deep pink/magenta (creative)
        material: '#2ecc71', // Green (recycling)
        other: '#95a5a6', // Neutral gray
        automobile: '#2c3e50', // Dark blue-gray (asphalt/tires)
        electronics: '#00bcd4', // Cyan (tech blue)
        pets: '#ffa500', // Orange (pet toys/accessories)
        stationery: '#3498db', // Bright blue (pen ink)
        custom: '#7f8c8d', // Medium gray

        // Crowdsourced categories
        fastfood: '#ff4757', // Red (McDonald's/KFC vibes)
        bicycle: '#27ae60', // Green (eco-friendly transport)

        // Fallback
        default: '#95a5a6', // Neutral gray
    };

    /**
     * Get color for a category key
     * @param {string} categoryKey - The category key
     * @returns {string} Hex color code
     */
    static getColor(categoryKey) {
        if (!categoryKey) return this.COLORS.default;

        const normalizedKey = categoryKey.toLowerCase().replace(/[_\s]/g, '');
        return this.COLORS[normalizedKey] || this.COLORS.default;
    }

    /**
     * Get RGB values for a category (0-1 range for WebGL)
     * @param {string} categoryKey - The category key
     * @returns {Object} RGB object with r, g, b, a values (0-1 range)
     */
    static getRGB(categoryKey) {
        const hex = this.getColor(categoryKey);
        return this.hexToRgb(hex);
    }

    /**
     * Convert hex color to RGB values (0-1 range)
     * @param {string} hex - Hex color code
     * @returns {Object} RGB object with r, g, b, a values
     */
    static hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result
            ? {
                  r: parseInt(result[1], 16) / 255,
                  g: parseInt(result[2], 16) / 255,
                  b: parseInt(result[3], 16) / 255,
                  a: 1,
              }
            : {
                  r: 0.42, // Default gray if parsing fails
                  g: 0.45,
                  b: 0.5,
                  a: 1,
              };
    }

    /**
     * Get all categories with their colors
     * @returns {Array} Array of category objects with key and color
     */
    static getAllCategories() {
        return Object.entries(this.COLORS)
            .filter(([key]) => key !== 'default')
            .map(([key, color]) => ({
                key,
                color,
                rgb: this.hexToRgb(color),
            }));
    }

    /**
     * Check if a category exists
     * @param {string} categoryKey - The category key
     * @returns {boolean} Whether the category exists
     */
    static exists(categoryKey) {
        if (!categoryKey) return false;
        const normalizedKey = categoryKey.toLowerCase().replace(/[_\s]/g, '');
        return normalizedKey in this.COLORS;
    }

    /**
     * Get display name for a category
     * @param {string} categoryKey - The category key
     * @returns {string} Formatted display name
     */
    static getDisplayName(categoryKey) {
        if (!categoryKey) return 'Unknown';

        // Special cases
        const displayNames = {
            softdrinks: 'Soft Drinks',
            dogshit: 'Dog Waste',
            fastfood: 'Fast Food',
            electronics: 'Electronics',
            automobile: 'Automobile',
            stationery: 'Stationery',
        };

        const normalized = categoryKey.toLowerCase();
        if (displayNames[normalized]) {
            return displayNames[normalized];
        }

        // Default: capitalize first letter
        return categoryKey.charAt(0).toUpperCase() + categoryKey.slice(1).toLowerCase();
    }

    /**
     * Get icon for a category (optional - for future use)
     * @param {string} categoryKey - The category key
     * @returns {string} Icon identifier or emoji
     */
    static getIcon(categoryKey) {
        const icons = {
            smoking: '🚬',
            food: '🍔',
            coffee: '☕',
            alcohol: '🍺',
            softdrinks: '🥤',
            sanitary: '🧻',
            coastal: '🌊',
            dumping: '🗑️',
            industrial: '🏭',
            brands: '™️',
            dogshit: '💩',
            art: '🎨',
            material: '♻️',
            other: '📦',
            automobile: '🚗',
            electronics: '📱',
            pets: '🐾',
            stationery: '✏️',
            custom: '⚙️',
            fastfood: '🍟',
            bicycle: '🚲',
        };

        const normalized = categoryKey?.toLowerCase();
        return icons[normalized] || '🗑️';
    }
}
