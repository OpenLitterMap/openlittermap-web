<template>
    <div class="profile-card">
        <p class="mb1">{{ $t('profile.dashboard.manage-my-photos') }}</p>

        <router-link to="/bulk-tag">
            <button class="button is-medium is-primary">{{ $t('profile.dashboard.view-my-photos') }}</button>
        </router-link>
    </div>
</template>

<script>
export default {
    name: "ProfilePhotos",
}
</script>

<style scoped>

</style>
