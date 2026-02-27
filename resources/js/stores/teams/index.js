import { defineStore } from 'pinia';

export const useTeamsStore = defineStore('teams', {
    state: () => ({
        teams: [],
        activeTeamId: null,
        types: [],
        members: {
            data: [],
            current_page: 1,
            last_page: 1,
            total: 0,
        },
        dashboard: {
            photos_count: 0,
            litter_count: 0,
            members_count: 0,
        },
        leaderboard: [],
        errors: {},
        loading: false,
    }),

    getters: {
        activeTeam: (state) => state.teams.find((t) => t.id === state.activeTeamId),

        teamsLedByUser() {
            // Requires user store — resolve lazily
            const userStore = useUserStore();
            return this.teams.filter((t) => t.leader === userStore.user?.id);
        },

        hasTeams: (state) => state.teams.length > 0,
    },

    actions: {
        clearErrors() {
            this.errors = {};
        },

        clearError(key) {
            const next = { ...this.errors };
            delete next[key];
            this.errors = next;
        },

        // ── Fetch ────────────────────────────────────────

        async fetchTeamTypes() {
            try {
                const { data } = await axios.get('/api/teams/types');
                this.types = data.types || data;
            } catch (e) {
                console.error('fetchTeamTypes', e);
            }
        },

        async fetchMyTeams() {
            try {
                const { data } = await axios.get('/api/teams/joined');
                this.teams = data;

                // Sync activeTeamId from user
                const userStore = useUserStore();
                this.activeTeamId = userStore.user?.active_team ?? null;
            } catch (e) {
                console.error('fetchMyTeams', e);
            }
        },

        async fetchMembers(teamId, page = 1) {
            try {
                const { data } = await axios.get('/api/teams/members', {
                    params: { team_id: teamId, page },
                });
                this.members = data.result;
            } catch (e) {
                console.error('fetchMembers', e);
            }
        },

        async fetchDashboard({ teamId = 0, period = 'all' } = {}) {
            try {
                const { data } = await axios.get('/api/teams/data', {
                    params: { team_id: teamId, period },
                });
                this.dashboard = data;
            } catch (e) {
                console.error('fetchDashboard', e);
            }
        },

        async fetchLeaderboard() {
            try {
                const { data } = await axios.get('/api/teams/leaderboard');
                this.leaderboard = data;
            } catch (e) {
                console.error('fetchLeaderboard', e);
            }
        },

        // ── Mutations ────────────────────────────────────

        async createTeam(payload) {
            this.errors = {};

            try {
                const { data } = await axios.post('/api/teams/create', payload);

                if (data.success) {
                    this.teams.push(data.team);

                    // Decrement remaining teams on user store
                    const userStore = useUserStore();
                    if (userStore.user?.remaining_teams > 0) {
                        userStore.user.remaining_teams--;
                    }

                    return data.team;
                }

                if (data.msg === 'max-created') {
                    this.errors = { name: ['You have reached the maximum number of teams.'] };
                }

                return null;
            } catch (e) {
                if (e?.response?.status === 422) {
                    this.errors = e.response.data.errors || {};
                }
                return null;
            }
        },

        async joinTeam(identifier) {
            this.errors = {};

            try {
                const { data } = await axios.post('/api/teams/join', { identifier });

                if (data.success) {
                    this.teams.push(data.team);

                    if (data.activeTeam) {
                        this.activeTeamId = data.activeTeam.id;
                    }

                    return data.team;
                }

                if (data.msg === 'already-joined') {
                    this.errors = { identifier: ['You have already joined this team.'] };
                }

                return null;
            } catch (e) {
                if (e?.response?.status === 422) {
                    this.errors = e.response.data.errors || {};
                }
                return null;
            }
        },

        async leaveTeam(teamId) {
            try {
                const { data } = await axios.post('/api/teams/leave', { team_id: teamId });

                if (data.success) {
                    this.teams = this.teams.filter((t) => t.id !== teamId);

                    if (data.activeTeam) {
                        this.activeTeamId = data.activeTeam.id;
                    } else if (this.activeTeamId === teamId) {
                        this.activeTeamId = null;
                    }
                }
            } catch (e) {
                console.error('leaveTeam', e);
            }
        },

        async setActiveTeam(teamId) {
            try {
                const { data } = await axios.post('/api/teams/active', { team_id: teamId });

                if (data.success) {
                    this.activeTeamId = teamId;

                    // Sync to user store
                    const userStore = useUserStore();
                    userStore.user.active_team = teamId;
                    userStore.user.team = data.team;
                }
            } catch (e) {
                console.error('setActiveTeam', e);
            }
        },

        async clearActiveTeam() {
            try {
                const { data } = await axios.post('/api/teams/inactivate');

                if (data.success) {
                    this.activeTeamId = null;

                    const userStore = useUserStore();
                    userStore.user.active_team = null;
                    userStore.user.team = null;
                }
            } catch (e) {
                console.error('clearActiveTeam', e);
            }
        },

        async updateTeam({ teamId, name, identifier }) {
            this.errors = {};

            try {
                const { data } = await axios.patch(`/api/teams/update/${teamId}`, {
                    name,
                    identifier,
                });

                if (data.success) {
                    const idx = this.teams.findIndex((t) => t.id === teamId);
                    if (idx !== -1) this.teams[idx] = data.team;
                    return data.team;
                }

                return null;
            } catch (e) {
                if (e?.response?.status === 422) {
                    this.errors = e.response.data.errors || {};
                }
                return null;
            }
        },

        async savePrivacySettings({ teamId, all, settings }) {
            try {
                await axios.post('/api/teams/settings', {
                    team_id: teamId,
                    all,
                    settings,
                });

                // Update local pivot data
                const applyTo = all ? this.teams : this.teams.filter((t) => t.id === teamId);

                for (const team of applyTo) {
                    if (team.pivot) {
                        Object.assign(team.pivot, settings);
                    }
                }
            } catch (e) {
                console.error('savePrivacySettings', e);
            }
        },

        async downloadTeamData(teamId) {
            try {
                await axios.post('/api/teams/download', { team_id: teamId });
            } catch (e) {
                console.error('downloadTeamData', e);
            }
        },

        async toggleLeaderboardVisibility(teamId) {
            try {
                const { data } = await axios.post('/api/teams/leaderboard/visibility', {
                    team_id: teamId,
                });

                if (data.success) {
                    const team = this.teams.find((t) => t.id === teamId);
                    if (team) team.leaderboards = !team.leaderboards;
                }
            } catch (e) {
                console.error('toggleLeaderboardVisibility', e);
            }
        },
    },
});

// Lazy import to avoid circular dependency
import { useUserStore } from '../user/index.js';
