<template>
    <section class="hero section-stats">
        <div class="hero-body is-flex">
            <div class="stats">
                <div class="stat has-text-light has-text-centered">
                    <div class="total has-text-weight-bold">75</div>
                    <div class="is-size-5">photos uploaded per day</div>
                </div>
                <div class="stat has-text-light has-text-centered">
                    <div class="total has-text-weight-bold">42</div>
                    <div class="is-size-5">new users per week</div>
                </div>
                <div class="stat has-text-light has-text-centered">
                    <div class="total has-text-weight-bold">99</div>
                    <div class="is-size-5">Littercoin mined per month</div>
                </div>
            </div>
        </div>
    </section>
</template>
<script>
export default {
    name: 'StatsSection'
};
</script>
<style lang="scss" scoped>
.section-stats {
    background-color: #008080;
}
.stats {
    width: 100%;
    display: grid;
    grid-gap: 3rem;
    grid-template-columns: repeat(auto-fit, minmax(250px, 2fr));

    .stat {
        background-color: transparent;

        .total {
            font-size: 5rem;
        }
        &:hover {
            transform: scale(1.05);
        }
    }
}
@media screen and (min-width: 1280px) {
    .section-stats {
        .stats {
            max-width: 1000px;
            margin: auto;
            justify-content: center;
        }
    }
}

</style>
