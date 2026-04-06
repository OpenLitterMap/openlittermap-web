<template>
    <div>
        <div v-if="photosStore.memberStats.length === 0" class="text-center py-12 text-slate-500">
            No members found.
        </div>

        <div v-else class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Student</th>
                        <th class="px-4 py-3 text-right">Photos</th>
                        <th class="px-4 py-3 text-right">Pending</th>
                        <th class="px-4 py-3 text-right">Approved</th>
                        <th class="px-4 py-3 text-right">Litter Tagged</th>
                        <th class="px-4 py-3 text-left">Last Active</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="member in photosStore.memberStats"
                        :key="member.user_id"
                        class="border-t border-slate-100 hover:bg-slate-50 transition-colors"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-800">{{ member.name }}</div>
                            <div v-if="member.username" class="text-xs text-slate-500">
                                @{{ member.username }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-slate-700">
                            {{ member.total_photos }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <span
                                :class="member.pending > 0 ? 'text-amber-600 font-semibold' : 'text-slate-500'"
                            >
                                {{ member.pending }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-green-600">
                            {{ member.approved }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-slate-700">
                            {{ member.litter_count }}
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-500">
                            {{ member.last_active ? formatDate(member.last_active) : '-' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useTeamPhotosStore } from '@stores/teamPhotos.js';

const props = defineProps({
    teamId: {
        type: Number,
        required: true,
    },
});

const photosStore = useTeamPhotosStore();

const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
};

onMounted(() => {
    if (photosStore.memberStats.length === 0) {
        photosStore.fetchMemberStats(props.teamId);
    }
});
</script>
