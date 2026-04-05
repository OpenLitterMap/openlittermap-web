<template>
    <div class="changelog-container">
        <!-- Sidebar with updates -->
        <aside class="changelog-sidebar">
            <div class="sidebar-header">
                <h2>Updates</h2>
                <p class="update-count">{{ changelogs.length }} {{ changelogs.length === 1 ? 'update' : 'updates' }}</p>
            </div>
            <nav>
                <button
                    v-for="entry in changelogs"
                    :key="entry.id"
                    @click="selectedId = entry.id"
                    :class="{ active: selectedId === entry.id }"
                    class="update-btn"
                >
                    <div class="update-number">{{ entry.number }}</div>
                    <div class="update-title">{{ entry.title }}</div>
                    <div class="update-date">{{ entry.date }}</div>
                </button>
            </nav>
        </aside>

        <!-- Main content area -->
        <main class="changelog-content">
            <article v-if="selectedChangelog" class="update-article">
                <header class="update-header">
                    <h1>{{ selectedChangelog.number }}</h1>
                    <p class="header-title">{{ selectedChangelog.title }}</p>
                    <time>{{ selectedChangelog.date }}</time>
                </header>

                <!-- Dynamically render the update component -->
                <div class="update-body">
                    <component :is="currentUpdateComponent" v-if="currentUpdateComponent" />
                </div>
            </article>

            <div v-else class="no-selection">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="64"
                    height="64"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <p>Select an update to view details</p>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, computed, defineAsyncComponent } from 'vue';

// Update metadata - add new entries here when creating new update files
const updatesList = [
    {
        id: 1,
        number: 'Update #1',
        title: 'Thank you for subscribing!',
        date: '11th May 2017',
        component: 'update1',
    },
    {
        id: 2,
        number: 'Update #2',
        title: 'Say Hello to Littercoin (LTRX)',
        date: '25th May 2017',
        component: 'update2',
    },
    {
        id: 3,
        number: 'Update #3',
        title: 'Plastic Free July Updates',
        date: '5th July 2017',
        component: 'update3',
    },
    {
        id: 4,
        number: 'Update #4',
        title: 'Onwards & Upwards',
        date: '8th October 2017',
        component: 'update4',
    },
    {
        id: 5,
        number: 'Update #5',
        title: 'Breaking Barriers in 2018',
        date: '14th January 2018',
        component: 'update5',
    },
    {
        id: 6,
        number: 'Update #6',
        title: 'Brands, Marathons & More!',
        date: '15th February 2018',
        component: 'update6',
    },
    {
        id: 7,
        number: 'Update #7',
        title: 'Happy 1st Birthday!',
        date: '15th April 2018',
        component: 'update7',
    },
    {
        id: 8,
        number: 'Update #8',
        title: 'Academic Paper just Published!',
        date: '11th June 2018',
        component: 'update8',
    },
    {
        id: 9,
        number: 'Update #9',
        title: 'Global Map Improvements & more',
        date: '21st July 2018',
        component: 'update9',
    },
    {
        id: 10,
        number: 'Update #10',
        title: 'OpenLitterMap now en Español!',
        date: '2nd August 2018',
        component: 'update10',
    },
    {
        id: 11,
        number: 'Update #11',
        title: 'Inaugural Online Conference STOP! The State Of Plastic Pollution',
        date: '9th August 2018',
        component: 'update11',
    },
    {
        id: 12,
        number: 'Update #12',
        title: 'STOP rescheduled & Updates',
        date: '2nd September 2018',
        component: 'update12',
    },
    {
        id: 13,
        number: 'Update #13',
        title: 'The Struggle Continues',
        date: '13th Jan 2019',
        component: 'update13',
    },
    {
        id: 14,
        number: 'Update #14',
        title: 'OpenLitterMap now available on iOS',
        date: '3rd September 2019',
        component: 'update14',
    },
    {
        id: 15,
        number: 'Update #15',
        title: 'OpenLitterMap now available on Android',
        date: '26th October 2019',
        component: 'update15',
    },
    {
        id: 16,
        number: 'Update #16',
        title: ' Mobile App v2 & New Video Now Online',
        date: '25th April 2020',
        component: 'update16',
    },
    {
        id: 17,
        number: 'Update #17',
        title: 'Weekly Community Zoom Calls',
        date: '22nd October 2020',
        component: 'update17',
    },
    {
        id: 18,
        number: 'Update #18',
        title: 'We need your help',
        date: '30th September 2020',
        component: 'update18',
    },
    {
        id: 19,
        number: 'Update #19',
        title: 'GoFundMe & OpenLitterMap v2.0 now online',
        date: '23rd October 2020',
        component: 'update19',
    },
    {
        id: 20,
        number: 'Update #20',
        title: 'OpenLitterMap is now open source',
        date: '25th October 2020',
        component: 'update20',
    },
    {
        id: 21,
        number: 'Update #21',
        title: 'Say Hello to Teams & Open Source Mobile App',
        date: '12th December 2020',
        component: 'update21',
    },
    {
        id: 22,
        number: 'Update #22',
        title: 'New tool to advance the OpenLitterAI',
        date: '20th March 2021',
        component: 'update22',
    },
    {
        id: 23,
        number: 'Update #23',
        title: 'Huge Update to Global Map',
        date: '5th April 2021',
        component: 'update23',
    },
    {
        id: 24,
        number: 'Update #24',
        title: 'OpenLitterMap awarded $50,000 by cryptocurrency Cardano',
        date: '4th August 2021',
        component: 'update24',
    },
    {
        id: 25,
        number: 'Update #25',
        title: 'Big Improvements since $50,000 funding from cryptocurrency Cardano',
        date: '6th May 2022',
        component: 'update25',
    },
    {
        id: 26,
        number: 'Update #26',
        title: 'OpenLitterMap v5 is released!',
        date: '22nd March 2026',
        component: 'update26',
    },
    {
        id: 27,
        number: 'Update #27',
        title: 'New Mobile Apps & More!',
        date: '4th April 2026',
        component: 'update27',
    },
];
const changelogs = ref([...updatesList].reverse());
const selectedId = ref(updatesList[updatesList.length - 1]?.id || null);
const currentUpdateComponent = ref(null);

// Load the selected update component dynamically
const selectedChangelog = computed(() => {
    const changelog = changelogs.value.find((c) => c.id === selectedId.value);

    if (changelog) {
        // Dynamically import the component
        currentUpdateComponent.value = defineAsyncComponent(() => import(`./Updates/${changelog.component}.vue`));
    }

    return changelog;
});
</script>

<style scoped>
.changelog-container {
    display: flex;
    min-height: 100vh;
    background: #f5f7fa;
}

/* Sidebar Styles */
.changelog-sidebar {
    width: 300px;
    background: white;
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
}

.sidebar-header {
    padding: 2rem 1.5rem 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.sidebar-header h2 {
    margin: 0 0 0.5rem 0;
    font-size: 1.75rem;
    font-weight: 700;
    color: #1a202c;
}

.update-count {
    margin: 0;
    font-size: 0.875rem;
    color: #718096;
}

.changelog-sidebar nav {
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.update-btn {
    text-align: left;
    padding: 1.25rem;
    border: 2px solid transparent;
    border-radius: 12px;
    background: #f7fafc;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.update-btn:hover {
    background: #edf2f7;
    border-color: #cbd5e0;
    transform: translateX(4px);
}

.update-btn.active {
    background: #ebf8ff;
    border-color: #4299e1;
    box-shadow: 0 2px 8px rgba(66, 153, 225, 0.15);
}

.update-btn.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #4299e1;
    border-radius: 12px 0 0 12px;
}

.update-number {
    font-weight: 600;
    font-size: 1.05rem;
    color: #2d3748;
    margin-bottom: 0.25rem;
}

.update-btn.active .update-number {
    color: #2b6cb0;
}

.update-title {
    font-size: 0.925rem;
    color: #4a5568;
    margin-bottom: 0.25rem;
    line-height: 1.3;
}

.update-btn.active .update-title {
    color: #2c5282;
}

.update-date {
    font-size: 0.875rem;
    color: #718096;
}

/* Main Content Styles */
.changelog-content {
    flex: 1;
    padding: 2rem;
    overflow-y: auto;
}

.update-article {
    max-width: 900px;
    margin: 0 auto;
    background: white;
    border-radius: 16px;
    box-shadow:
        0 1px 3px rgba(0, 0, 0, 0.1),
        0 1px 2px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

.update-header {
    padding: 3rem 3rem 2rem;
    border-bottom: 2px solid #e2e8f0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.update-header h1 {
    margin: 0 0 0.75rem 0;
    font-size: 2.5rem;
    font-weight: 700;
}

.header-title {
    font-size: 1.5rem;
    margin: 0 0 0.75rem 0;
    opacity: 0.95;
    font-weight: 500;
}

.update-header time {
    font-size: 1.1rem;
    opacity: 0.95;
}

.update-body {
    padding: 3rem;
}

/* No Selection State */
.no-selection {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 400px;
    text-align: center;
    color: #a0aec0;
}

.no-selection svg {
    margin-bottom: 1.5rem;
    opacity: 0.4;
}

.no-selection p {
    font-size: 1.1rem;
    margin: 0;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .changelog-sidebar {
        width: 260px;
    }

    .update-body {
        padding: 2rem;
    }
}

@media (max-width: 768px) {
    .changelog-container {
        flex-direction: column;
    }

    .changelog-sidebar {
        width: 100%;
        height: auto;
        position: static;
        border-right: none;
        border-bottom: 1px solid #e2e8f0;
    }

    .sidebar-header {
        padding: 1.5rem 1rem;
    }

    .changelog-sidebar nav {
        padding: 1rem;
        flex-direction: row;
        overflow-x: auto;
        gap: 0.5rem;
    }

    .update-btn {
        min-width: 200px;
        padding: 1rem;
    }

    .update-btn:hover {
        transform: translateY(-2px);
    }

    .changelog-content {
        padding: 1rem;
    }

    .update-header {
        padding: 2rem 1.5rem 1.5rem;
    }

    .update-header h1 {
        font-size: 2rem;
    }

    .update-body {
        padding: 1.5rem;
    }

    .update-body h2 {
        font-size: 1.5rem;
    }
}

/* Scrollbar Styling */
.changelog-sidebar::-webkit-scrollbar,
.changelog-content::-webkit-scrollbar {
    width: 8px;
}

.changelog-sidebar::-webkit-scrollbar-track,
.changelog-content::-webkit-scrollbar-track {
    background: #f7fafc;
}

.changelog-sidebar::-webkit-scrollbar-thumb,
.changelog-content::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 4px;
}

.changelog-sidebar::-webkit-scrollbar-thumb:hover,
.changelog-content::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}
</style>
