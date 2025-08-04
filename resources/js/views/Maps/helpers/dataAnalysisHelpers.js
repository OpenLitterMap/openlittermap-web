import moment from 'moment';

/**
 * Calculate basic statistics from points data
 */
export function calculateBasicStats(features) {
    if (!features || features.length === 0) {
        return {
            total: 0,
            pickedUp: 0,
            verified: 0,
            avgLitterPerPhoto: 0,
            totalLitter: 0,
        };
    }

    const stats = features.reduce(
        (acc, feature) => {
            const props = feature.properties;

            acc.total++;
            if (props.picked_up) acc.pickedUp++;
            if (props.verified >= 2) acc.verified++;
            acc.totalLitter += props.total_litter || 0;

            return acc;
        },
        {
            total: 0,
            pickedUp: 0,
            verified: 0,
            totalLitter: 0,
        }
    );

    stats.avgLitterPerPhoto = stats.total > 0 ? (stats.totalLitter / stats.total).toFixed(1) : 0;

    return stats;
}

/**
 * Group data by time periods
 */
export function groupByTimePeriod(features, period = 'day') {
    const groups = {};

    features.forEach((feature) => {
        const date = moment(feature.properties.datetime);
        let key;

        switch (period) {
            case 'hour':
                key = date.format('YYYY-MM-DD HH:00');
                break;
            case 'day':
                key = date.format('YYYY-MM-DD');
                break;
            case 'week':
                key = date.startOf('week').format('YYYY-MM-DD');
                break;
            case 'month':
                key = date.format('YYYY-MM');
                break;
            case 'year':
                key = date.format('YYYY');
                break;
            default:
                key = date.format('YYYY-MM-DD');
        }

        groups[key] = (groups[key] || 0) + 1;
    });

    return groups;
}

/**
 * Get top contributors
 */
export function getTopContributors(features, limit = 10) {
    const contributors = {};

    features.forEach((feature) => {
        const name = feature.properties.username || feature.properties.name || 'Anonymous';
        if (!contributors[name]) {
            contributors[name] = {
                name,
                count: 0,
                totalLitter: 0,
                pickedUp: 0,
            };
        }

        contributors[name].count++;
        contributors[name].totalLitter += feature.properties.total_litter || 0;
        if (feature.properties.picked_up) contributors[name].pickedUp++;
    });

    return Object.values(contributors)
        .sort((a, b) => b.count - a.count)
        .slice(0, limit);
}

/**
 * Get team statistics
 */
export function getTeamStats(features) {
    const teams = {};

    features.forEach((feature) => {
        if (feature.properties.team) {
            const team = feature.properties.team;

            if (!teams[team]) {
                teams[team] = {
                    name: team,
                    count: 0,
                    totalLitter: 0,
                    contributors: new Set(),
                };
            }

            teams[team].count++;
            teams[team].totalLitter += feature.properties.total_litter || 0;

            const contributor = feature.properties.username || feature.properties.name;
            if (contributor) {
                teams[team].contributors.add(contributor);
            }
        }
    });

    // Convert Set to count
    Object.values(teams).forEach((team) => {
        team.contributorCount = team.contributors.size;
        delete team.contributors;
    });

    return Object.values(teams).sort((a, b) => b.count - a.count);
}

/**
 * Get hourly activity pattern
 */
export function getHourlyActivity(features) {
    const hours = new Array(24).fill(0);

    features.forEach((feature) => {
        const hour = moment(feature.properties.datetime).hour();
        hours[hour]++;
    });

    return hours;
}

/**
 * Get day of week activity pattern
 */
export function getDayOfWeekActivity(features) {
    const days = new Array(7).fill(0);
    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    features.forEach((feature) => {
        const day = moment(feature.properties.datetime).day();
        days[day]++;
    });

    return days.map((count, index) => ({
        day: dayNames[index],
        count,
    }));
}

/**
 * Calculate geographic spread
 */
export function calculateGeographicSpread(features) {
    if (features.length === 0) return null;

    const lats = features.map((f) => f.geometry.coordinates[1]);
    const lngs = features.map((f) => f.geometry.coordinates[0]);

    return {
        bounds: {
            north: Math.max(...lats),
            south: Math.min(...lats),
            east: Math.max(...lngs),
            west: Math.min(...lngs),
        },
        center: {
            lat: lats.reduce((a, b) => a + b, 0) / lats.length,
            lng: lngs.reduce((a, b) => a + b, 0) / lngs.length,
        },
        spread: {
            latRange: Math.max(...lats) - Math.min(...lats),
            lngRange: Math.max(...lngs) - Math.min(...lngs),
        },
    };
}

/**
 * Generate CSV data
 */
export function generateCSV(features) {
    const headers = [
        'ID',
        'Date',
        'Time',
        'Latitude',
        'Longitude',
        'Username',
        'Name',
        'Team',
        'Total Litter',
        'Picked Up',
        'Verified',
    ];

    const rows = features.map((feature) => {
        const props = feature.properties;
        const datetime = moment(props.datetime);

        return [
            props.id,
            datetime.format('YYYY-MM-DD'),
            datetime.format('HH:mm:ss'),
            feature.geometry.coordinates[1],
            feature.geometry.coordinates[0],
            props.username || '',
            props.name || '',
            props.team || '',
            props.total_litter || 0,
            props.picked_up ? 'Yes' : 'No',
            props.verified || 0,
        ];
    });

    return [headers, ...rows].map((row) => row.map((cell) => `"${cell}"`).join(',')).join('\n');
}

/**
 * Calculate activity trends
 */
export function calculateTrends(features, days = 30) {
    const cutoffDate = moment().subtract(days, 'days');
    const recentFeatures = features.filter((f) => moment(f.properties.datetime).isAfter(cutoffDate));

    const dailyData = groupByTimePeriod(recentFeatures, 'day');
    const values = Object.values(dailyData);

    if (values.length < 2) return { trend: 'insufficient_data' };

    // Simple linear regression
    const n = values.length;
    const sumX = values.reduce((a, _, i) => a + i, 0);
    const sumY = values.reduce((a, b) => a + b, 0);
    const sumXY = values.reduce((a, y, i) => a + i * y, 0);
    const sumX2 = values.reduce((a, _, i) => a + i * i, 0);

    const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
    const avgY = sumY / n;

    return {
        trend: slope > 0.1 ? 'increasing' : slope < -0.1 ? 'decreasing' : 'stable',
        slope: slope.toFixed(2),
        average: avgY.toFixed(1),
        total: sumY,
    };
}
