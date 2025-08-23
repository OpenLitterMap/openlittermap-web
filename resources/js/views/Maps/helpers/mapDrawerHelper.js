// helpers/mapDrawerHelper.js

import { Category } from './Category.js';

/**
 * Map Drawer Helper Functions
 * Utility functions for the OpenLitterMap drawer component
 */

class MapDrawerHelper {
    /**
     * Format numbers with appropriate suffixes (K, M, B)
     */
    static formatNumber(num) {
        if (num === null || num === undefined || isNaN(num)) return '0';

        // Convert to number if string
        const value = Number(num);
        if (isNaN(value)) return '0';

        if (value >= 1000000000) {
            return (value / 1000000000).toFixed(1) + 'B';
        }
        if (value >= 1000000) {
            return (value / 1000000).toFixed(1) + 'M';
        }
        if (value >= 1000) {
            return (value / 1000).toFixed(1) + 'K';
        }
        return Math.floor(value).toString();
    }

    /**
     * Format percentages
     */
    static formatPercentage(value, total) {
        if (!total || total === 0) return '0%';
        const percentage = (value / total) * 100;
        return percentage < 0.1 ? '<0.1%' : `${percentage.toFixed(1)}%`;
    }

    /**
     * Format dates for display
     */
    static formatDate(dateString) {
        if (!dateString) return '';

        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;

            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        } catch (error) {
            console.warn('Invalid date string:', dateString);
            return dateString;
        }
    }

    /**
     * Format date range for display
     */
    static formatDateRange(from, to) {
        if (!from || !to) return 'All time';

        const fromFormatted = this.formatDate(from);
        const toFormatted = this.formatDate(to);

        if (fromFormatted === toFormatted) {
            return fromFormatted;
        }

        return `${fromFormatted} - ${toFormatted}`;
    }

    /**
     * Parse object key to get category and name
     * Handles both "category.object" format and direct object keys
     */
    static parseObjectKey(key) {
        if (!key || typeof key !== 'string') {
            return { category: 'unknown', name: 'unknown' };
        }

        // Check if the key contains a dot separator
        if (key.includes('.')) {
            const parts = key.split('.');
            return {
                category: parts[0] || 'unknown',
                name: parts[1] || key,
            };
        } else {
            // If no dot, the key itself is the object name
            // We need to determine the category from context or default to 'unknown'
            console.log(`Object key "${key}" has no category prefix`);
            return {
                category: null, // Will need to be determined from context
                name: key,
            };
        }
    }

    /**
     * Get display name for an object
     */
    static getObjectDisplayName(key) {
        const parsed = this.parseObjectKey(key);
        // Convert underscore to space and capitalize
        return parsed.name.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
    }

    /**
     * Format filter key for display
     */
    static formatFilterKey(key) {
        if (!key) return '';
        // Use Category class for proper display names
        if (Category.exists(key)) {
            return Category.getDisplayName(key);
        }
        return key.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
    }

    /**
     * Get contributor period text
     */
    static getContributorPeriod(firstContribution, lastContribution) {
        if (!firstContribution) return 'Recent contributor';

        const first = this.formatDate(firstContribution);
        const last = this.formatDate(lastContribution);

        if (first === last) {
            return `Since ${first}`;
        }

        return `${first} - ${last}`;
    }

    /**
     * Process stats data for component consumption
     */
    static processStatsDataForComponent(statsData) {
        if (!statsData) return null;

        const result = {
            // Basic counts
            totalItems: statsData.metadata?.total_photos || 0,
            totalObjects: statsData.metadata?.total_objects || 0,
            totalBrands: statsData.metadata?.total_brands || 0,

            // Pickup percentages
            pickedUpPercentage: 0,
            notPickedUpPercentage: 0,

            // Processed arrays
            topObjects: [],
            topBrands: [],
            categoriesWithPercentages: [],
            materialsWithPercentages: [],
            timeSeriesData: [],
            normalizedHistogram: [],

            // Time series stats
            timeSeriesStats: {},
            firstDate: '',
            lastDate: '',
            trendIcon: '',

            // Filters
            hasFilters: false,
        };

        // Calculate pickup percentages
        const pickedUp = statsData.metadata?.picked_up || 0;
        const notPickedUp = statsData.metadata?.not_picked_up || 0;
        const totalPickup = pickedUp + notPickedUp;

        if (totalPickup > 0) {
            result.pickedUpPercentage = (pickedUp / totalPickup) * 100;
            result.notPickedUpPercentage = (notPickedUp / totalPickup) * 100;
        }

        // Process top objects
        if (statsData.top_objects && Array.isArray(statsData.top_objects)) {
            result.topObjects = statsData.top_objects.slice(0, 20).map((obj) => ({
                ...obj,
                key: obj.key || obj.name || 'unknown',
                name: obj.name || this.getObjectDisplayName(obj.key),
                count: obj.count || 0,
            }));
        }

        // Process top brands
        if (statsData.top_brands && Array.isArray(statsData.top_brands)) {
            result.topBrands = statsData.top_brands.slice(0, 12).map((brand) => ({
                ...brand,
                key: brand.key || brand.name || 'unknown',
                name: brand.name || brand.key || 'Unknown',
                count: brand.count || 0,
            }));
        }

        // Process categories
        if (statsData.categories && Array.isArray(statsData.categories)) {
            const totalCategoryCount = statsData.categories.reduce((sum, cat) => sum + (cat.count || 0), 0);

            result.categoriesWithPercentages = statsData.categories.map((cat) => {
                const percentage = totalCategoryCount > 0 ? (cat.count / totalCategoryCount) * 100 : 0;
                const categoryKey = cat.key || cat.name;
                return {
                    ...cat,
                    key: categoryKey,
                    name: Category.getDisplayName(categoryKey),
                    count: cat.count || 0,
                    percentage,
                    formattedPercentage: this.formatPercentage(cat.count, totalCategoryCount),
                    color: Category.getColor(categoryKey),
                };
            });
        }

        // Process materials
        if (statsData.top_materials && Array.isArray(statsData.top_materials)) {
            const totalMaterialCount = statsData.top_materials.reduce((sum, mat) => sum + (mat.count || 0), 0);

            result.materialsWithPercentages = statsData.top_materials.slice(0, 10).map((mat) => {
                const percentage = totalMaterialCount > 0 ? (mat.count / totalMaterialCount) * 100 : 0;
                return {
                    ...mat,
                    key: mat.key || mat.name || 'unknown',
                    name: mat.name || this.formatFilterKey(mat.key),
                    count: mat.count || 0,
                    percentage,
                    formattedPercentage: this.formatPercentage(mat.count, totalMaterialCount),
                    icon: this.getMaterialIcon(mat.key || mat.name),
                };
            });
        }

        // Process time series
        if (statsData.time_series?.histogram && Array.isArray(statsData.time_series.histogram)) {
            result.timeSeriesData = statsData.time_series.histogram;

            // Normalize histogram for visualization
            const maxValue = Math.max(...statsData.time_series.histogram.map((h) => h.photos || 0));

            result.normalizedHistogram = statsData.time_series.histogram.map((item) => ({
                ...item,
                bucket: item.bucket,
                photos: item.photos || 0,
                objects: item.objects || 0,
                height: maxValue > 0 ? ((item.photos || 0) / maxValue) * 100 : 0,
            }));

            // Calculate stats
            result.timeSeriesStats = this.calculateTimeSeriesStats(result.timeSeriesData);

            // Get first and last dates
            if (result.normalizedHistogram.length > 0) {
                result.firstDate = this.formatDate(result.normalizedHistogram[0].bucket);
                result.lastDate = this.formatDate(
                    result.normalizedHistogram[result.normalizedHistogram.length - 1].bucket
                );
            }

            // Set trend icon
            result.trendIcon =
                result.timeSeriesStats.trend === 'increasing'
                    ? '📈'
                    : result.timeSeriesStats.trend === 'decreasing'
                      ? '📉'
                      : '➡️';
        }

        // Check for filters
        result.hasFilters = statsData.filters_applied && Object.keys(statsData.filters_applied).length > 0;

        return result;
    }

    /**
     * Get material icon
     */
    static getMaterialIcon(material) {
        const icons = {
            plastic: '♻️',
            glass: '🍾',
            metal: '🥫',
            paper: '📄',
            cardboard: '📦',
            fabric: '👕',
            rubber: '🎾',
            electronic: '📱',
            wood: '🪵',
            other: '🗑️',
        };

        return icons[material?.toLowerCase()] || icons.other;
    }

    /**
     * Get category colors - now uses the Category class
     */
    static getCategoryColor(key) {
        return Category.getColor(key);
    }

    /**
     * Calculate time series statistics
     */
    static calculateTimeSeriesStats(data) {
        if (!data || !Array.isArray(data) || data.length === 0) {
            return {
                total: 0,
                average: 0,
                peak: { value: 0, date: null },
                trend: 'stable',
            };
        }

        const values = data.map((d) => d.photos || 0);
        const total = values.reduce((sum, val) => sum + val, 0);
        const average = total / values.length;
        const maxValue = Math.max(...values);
        const peakIndex = values.indexOf(maxValue);
        const peak = {
            value: maxValue,
            date: data[peakIndex]?.bucket || null,
        };

        // Simple trend calculation
        if (values.length < 2) {
            return { total, average: Math.round(average * 10) / 10, peak, trend: 'stable' };
        }

        const midpoint = Math.floor(values.length / 2);
        const firstHalf = values.slice(0, midpoint);
        const secondHalf = values.slice(midpoint);

        const firstAvg = firstHalf.length > 0 ? firstHalf.reduce((sum, val) => sum + val, 0) / firstHalf.length : 0;
        const secondAvg = secondHalf.length > 0 ? secondHalf.reduce((sum, val) => sum + val, 0) / secondHalf.length : 0;

        let trend = 'stable';
        if (firstAvg > 0) {
            if (secondAvg > firstAvg * 1.1) trend = 'increasing';
            if (secondAvg < firstAvg * 0.9) trend = 'decreasing';
        }

        return { total, average: Math.round(average * 10) / 10, peak, trend };
    }

    /**
     * Handle export with loading state
     */
    static async handleExport(exportFunction, setLoading, statsData, filters) {
        try {
            setLoading(true);
            await exportFunction(statsData, filters);
        } catch (error) {
            console.error('Export failed:', error);
            alert('Export failed. Please try again.');
        } finally {
            setLoading(false);
        }
    }

    /**
     * Export to CSV
     */
    static exportToCSV(statsData, filters) {
        const exportData = [];

        // Add metadata
        exportData.push(['OpenLitterMap Statistics Export']);
        exportData.push(['Generated:', new Date().toISOString()]);
        if (filters.from && filters.to) {
            exportData.push(['Date Range:', this.formatDateRange(filters.from, filters.to)]);
        }
        exportData.push(['']); // Empty row

        // Add summary stats
        exportData.push(['Summary Statistics']);
        exportData.push(['Total Photos:', statsData.metadata?.total_photos || 0]);
        exportData.push(['Total Objects:', statsData.metadata?.total_objects || 0]);
        exportData.push(['Total Users:', statsData.metadata?.total_users || 0]);
        exportData.push(['']); // Empty row

        // Add top objects
        if (statsData.top_objects && statsData.top_objects.length > 0) {
            exportData.push(['Top Litter Objects']);
            exportData.push(['Object', 'Count']);
            statsData.top_objects.forEach((obj) => {
                exportData.push([obj.name || obj.key || 'Unknown', obj.count || 0]);
            });
            exportData.push(['']); // Empty row
        }

        // Convert to CSV string
        const csvContent = exportData
            .map((row) =>
                row
                    .map((cell) => {
                        const value = String(cell);
                        if (value.includes(',') || value.includes('"') || value.includes('\n')) {
                            return `"${value.replace(/"/g, '""')}"`;
                        }
                        return value;
                    })
                    .join(',')
            )
            .join('\n');

        const filename = this.generateExportFilename(filters, 'csv');
        this.downloadFile(csvContent, filename, 'text/csv');
    }

    /**
     * Export to JSON
     */
    static exportToJSON(statsData, filters) {
        const exportData = {
            metadata: {
                title: 'OpenLitterMap Statistics Export',
                generated: new Date().toISOString(),
                filters: filters,
            },
            data: statsData,
        };

        const jsonContent = JSON.stringify(exportData, null, 2);
        const filename = this.generateExportFilename(filters, 'json');
        this.downloadFile(jsonContent, filename, 'application/json');
    }

    /**
     * Generate export filename
     */
    static generateExportFilename(filters, format = 'csv') {
        const timestamp = new Date().toISOString().split('T')[0];
        let filename = `openlittermap_stats_${timestamp}`;

        if (filters.year) {
            filename += `_${filters.year}`;
        } else if (filters.from && filters.to) {
            filename += `_${filters.from}_to_${filters.to}`;
        }

        if (filters.username) {
            filename += `_${filters.username}`;
        }

        return `${filename}.${format}`;
    }

    /**
     * Download file
     */
    static downloadFile(content, filename, mimeType = 'text/csv') {
        const blob = new Blob([content], { type: mimeType });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    }

    /**
     * Validate stats data
     */
    static validateStatsData(data) {
        if (!data || typeof data !== 'object') return false;

        // Just check for at least one main section
        return !!(
            data.metadata ||
            data.time_series ||
            data.categories ||
            data.top_objects ||
            data.top_brands ||
            data.top_materials ||
            data.top_contributors
        );
    }

    /**
     * Format cache info
     */
    static formatCacheInfo(cacheInfo) {
        if (!cacheInfo) return 'Fresh';

        const info = [];
        if (cacheInfo.cached) info.push('Cached');
        if (cacheInfo.sampling) info.push('Sampled');
        if (cacheInfo.truncated) info.push('Truncated');

        return info.length > 0 ? info.join(', ') : 'Fresh';
    }
}

export default MapDrawerHelper;
