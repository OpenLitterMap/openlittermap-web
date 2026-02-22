<template>
    <section class="hero section-social">
        <div class="hero-body">
            <div class="py-2 social-links">
                <div v-for="card in cards" class="social-card">
                    <a :href="card.link" :title="card.title" target="_blank">
                        <i class="fa-4x fa-fw is-large has-text-light" :class="card.icon"></i>
                    </a>
                </div>

                <a
                    href="https://discord.gg/z6Pg2g5y6G"
                    target="_blank"
                    class="discord-link"
                >
                   and discord
                </a>
            </div>
        </div>
    </section>
</template>
<script>
export default {
    name: 'SocialSection',
    data ()
    {
        return {
            cards: [
                {
                    title: 'Slack',
                    link: 'https://join.slack.com/t/openlittermap/shared_invite/zt-fdctasud-mu~OBQKReRdC9Ai9KgGROw',
                    icon: 'fa fa-slack'
                },
                {
                    title: 'Twitter',
                    link: 'https://twitter.com/OpenLitterMap',
                    icon: 'fa fa-twitter'
                },
                {
                    title: 'Facebook',
                    link: 'https://www.facebook.com/openlittermap/',
                    icon: 'fa fa-facebook'
                },
                {
                    title: 'Instagram',
                    link: 'https://www.instagram.com/openlittermap/',
                    icon: 'fa fa-instagram'
                },
                {
                    title: 'Reddit',
                    link: 'https://www.reddit.com/r/openlittermap/',
                    icon: 'fa fa-reddit'
                },
                {
                    title: 'YouTube',
                    link: 'https://www.youtube.com/channel/UCfUT45IaIoxSCdQwv7-cTRg',
                    icon: 'fa fa-youtube'
                },

                {
                    title: 'LinkedIn',
                    link: 'https://www.linkedin.com/company/openlittermap/',
                    icon: 'fa fa-linkedin'
                },
                {
                    title: 'GitHub',
                    link: 'https://github.com/openlittermap',
                    icon: 'fa fa-github'
                },
            ]
        };
    },
};
</script>
<style lang="scss" scoped>
.section-social {
    background-color: #094C54;

    .social-links {
        display: grid;
        grid-gap: 3rem;
        grid-template-columns: repeat(auto-fit, minmax(75px, 2fr));
    }

    .social-card {
        background-color: transparent;

        &:hover {
            transform: scale(1.05);
        }
    }
}

.discord-link {
    color: white;
    width: max-content;
}

@media screen and (min-width: 1280px) {
    .section-social {
        .social-links {
            width: 100%;
            max-width: 1000px;
            margin: auto;
            justify-content: center;
        }
    }
}
</style>
