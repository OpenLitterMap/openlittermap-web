<template>
    <div class="profile-next-target">
        <p class="is-secondary">Level: <strong class="is-white">{{ level }}</strong></p>

        <p class="is-secondary">XP: <strong class="is-white">{{ xp }} xp</strong></p>

        <p class="is-secondary">Level up: <strong class="is-white">{{ xpNeeded }} xp</strong></p>
    </div>
</template>

<script>
export default {
    name: 'ProfileNextTarget',
    props: [
        'level',
        'xp',
        'xpNeeded'
    ]
}
</script>

<style scoped>

    .profile-next-target {
        position: absolute;
        top: 33em;
        left: 7em;
        z-index: 999;
    }

    .profile-next-target p {
        font-size: 1.25em;
    }

    .is-white {
        color: white !important;
    }

    .is-secondary {
        color: #1DD3B0;
    }
</style>
