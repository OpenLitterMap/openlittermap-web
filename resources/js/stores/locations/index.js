import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import axios from 'axios';

export const useLocationsStore = defineStore('locations', () => {
    // ─── State ──────────────────────────────────────────────────
    const stats = ref(null);
    const meta = ref(null);
    const activity = ref(null);
    const location = ref(null);
    const children = ref([]);
    const childrenType = ref(null);
    const breadcrumbs = ref([]);
    const loading = ref(false);
    const error = ref(null);

    // Sort & search (client-side)
    const sortField = ref('tags');
    const sortDir = ref('desc');
    const search = ref('');

    // Time filters (server-side, mutually exclusive)
    const period = ref('all'); // all|today|yesterday|this_month|last_month|this_year
    const year = ref(null); // null or 2017–current (clears period when set)

    // ─── Getters ────────────────────────────────────────────────

    const sortedChildren = computed(() => {
        let list = [...children.value];

        if (search.value) {
            const q = search.value.toLowerCase();
            list = list.filter((c) => c.name.toLowerCase().includes(q));
        }

        const field = sortField.value;
        const dir = sortDir.value === 'asc' ? 1 : -1;

        list.sort((a, b) => {
            if (field === 'name') {
                return dir * (a.name ?? '').localeCompare(b.name ?? '');
            }
            if (field === 'created_at' || field === 'last_updated_at') {
                return dir * (a[field] ?? '').localeCompare(b[field] ?? '');
            }
            return dir * (Number(a[field] ?? 0) - Number(b[field] ?? 0));
        });

        return list;
    });

    const isGlobal = computed(() => location.value === null);
    const hasChildren = computed(() => children.value.length > 0);
    const locationName = computed(() => location.value?.name ?? 'World');
    const sortKey = computed(() => `${sortField.value}:${sortDir.value}`);

    // ─── Actions ────────────────────────────────────────────────

    function timeParams() {
        const params = {};
        if (year.value) {
            params.year = year.value;
        } else if (period.value && period.value !== 'all') {
            params.period = period.value;
        }
        return params;
    }

    async function fetchGlobal() {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.get('/api/v1/locations', { params: timeParams() });
            location.value = null;
            meta.value = null;
            activity.value = data.activity ?? null;
            stats.value = data.stats;
            children.value = data.children;
            childrenType.value = data.children_type;
            breadcrumbs.value = data.breadcrumbs;
        } catch (e) {
            error.value = e.response?.status === 404 ? 'Not found' : 'Failed to load locations';
        } finally {
            loading.value = false;
        }
    }

    async function fetchLocation(type, id) {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.get(`/api/v1/locations/${type}/${id}`, { params: timeParams() });
            location.value = data.location;
            meta.value = data.meta ?? null;
            activity.value = data.activity ?? null;
            stats.value = data.stats;
            children.value = data.children ?? [];
            childrenType.value = data.children_type;
            breadcrumbs.value = data.breadcrumbs;
        } catch (e) {
            error.value = e.response?.status === 404 ? 'Location not found' : 'Failed to load location';
        } finally {
            loading.value = false;
        }
    }

    function setPeriod(p) {
        period.value = p;
        year.value = null; // presets clear custom year
    }

    function setYear(y) {
        year.value = y || null;
        period.value = 'all'; // custom year clears preset
    }

    function setSortFromKey(key) {
        const [f, d] = key.split(':');
        sortField.value = f;
        sortDir.value = d;
    }

    function toggleSort(field) {
        if (sortField.value === field) {
            sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
        } else {
            sortField.value = field;
            sortDir.value = field === 'name' ? 'asc' : 'desc';
        }
    }

    function setSearch(query) {
        search.value = query;
    }

    function $reset() {
        stats.value = null;
        meta.value = null;
        activity.value = null;
        location.value = null;
        children.value = [];
        childrenType.value = null;
        breadcrumbs.value = [];
        loading.value = false;
        error.value = null;
        search.value = '';
        period.value = 'all';
        year.value = null;
    }

    return {
        stats,
        meta,
        activity,
        location,
        children,
        childrenType,
        breadcrumbs,
        loading,
        error,
        sortField,
        sortDir,
        search,
        period,
        year,
        sortedChildren,
        isGlobal,
        hasChildren,
        locationName,
        sortKey,
        fetchGlobal,
        fetchLocation,
        setPeriod,
        setYear,
        toggleSort,
        setSortFromKey,
        setSearch,
        $reset,
    };
});
