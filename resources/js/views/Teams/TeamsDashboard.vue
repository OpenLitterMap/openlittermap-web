<template>
    <section class="tdc">
        <p class="subtitle is-centered is-3">Teams Dashboard</p>
        <p class="mb1">Here you can find combined impact made by all the teams you have joined.</p>

        <div class="columns">
            <div class="column teams-card">
                <span class="title is-2" style="color: #7b848e;">0</span>
                <br>
                photos uploaded today
            </div>

            <div class="column teams-card">
                <span class="title is-2" style="color: #7b848e;">0</span>
                <br>
                litter uploaded today
            </div>

            <div class="column teams-card">
                <span class="title is-2" style="color: #7b848e;">0</span>
                <br>
                team members uploaded today
            </div>
        </div>
    </section>
</template>

<script>
export default {
    name: 'TeamsDashboard'
}
</script>

<style scoped>

    .tdc {
        padding-left: 2em;
        padding-right: 2em;
    }

    .teams-card {
        background: white;
        text-align: center;
        margin: 1em;
        padding: 5em;
    }
</style>
