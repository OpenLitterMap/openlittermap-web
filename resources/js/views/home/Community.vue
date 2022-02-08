<template>
    <div>
        <section class="community" :style="{backgroundImage: imageStyle}">
            <div class="hero is-medium container">
                <div class="hero-body community-hero">
                    <h1 class="title has-text-white has-text-weight-bold is-size-1-desktop">
                        Help us change the world.
                    </h1>
                    <h2 class="subtitle has-text-white-bis is-size-3">
                        We're growing by the day
                    </h2>
                </div>
            </div>
        </section>
        <section class="hero is-medium section-facebook">
            <div class="hero-body is-flex">
                <div class="facebook-heading">
                    <p class="title has-text-light">
                        Join us on Facebook
                    </p>
                    <p class="subtitle has-text-light">
                        Pick your favorite group
                    </p>
                </div>
                <div class="is-flex py-2 facebook-links">
                    <div v-for="card in facebookCards" class="card mr-6 facebook-card">
                        <a :href="card.link" target="_blank">
                            <div class="card-image">
                                <img :src="card.img" alt="Placeholder image"
                                     height="192" loading="lazy"
                                >
                            </div>
                            <div class="card-content">
                                <div class="content">
                                    <p class="title is-5 has-text-light">{{ card.title }}</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </section>
        <section class="hero section-social">
            <div class="hero-body is-flex">
                <div class="social-heading">
                    <p class="title has-text-light">
                        We're social
                    </p>
                    <p class="subtitle has-text-light">
                        Hit us up
                    </p>
                </div>
                <div class="py-2 social-links">
                    <div v-for="card in socialCards" class="social-card">
                        <a :href="card.link" :title="card.title" target="_blank">
                            <i class="fa-5x fa-fw is-large has-text-light" :class="card.icon"></i>
                        </a>
                    </div>
                </div>
            </div>
        </section>
        <section class="hero section-stats" style="background-color: #008080">
            <div class="hero-body is-flex">
                <div class="py-2 stats" style="flex: 1">
                    <div class="is-size-1 has-text-centered has-text-light my-6">
                        Daily-Weekly stats go here
                    </div>
                </div>
            </div>
        </section>
        <section class="hero section-stats" style="background-color: #1986B9">
            <div class="hero-body is-flex">
                <div class="py-2 stats" style="flex: 1">
                    <div class="is-size-1 has-text-centered has-text-light my-6">
                        A chart or two here
                    </div>
                </div>
            </div>
        </section>
        <section class="hero section-stats" style="background-color: #328BF2;height: 500px;">
            <div class="hero-body is-flex">
                <div class="py-2 stats" style="flex: 1">
                    <div class="is-size-1 has-text-centered has-text-light my-6">
                        Zoom calls, Gofundme etc.
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script>

export default {
    name: 'Community',
    data ()
    {
        return {
            facebookCards: [
                {
                    title: 'Open Litter Map Community',
                    link: 'https://www.facebook.com/openlittermap/',
                    img: 'https://scontent.ftia5-1.fna.fbcdn.net/v/t1.6435-1/p148x148/52323709_1226148030883915_8889487464016642048_n.jpg?_nc_cat=109&ccb=1-5&_nc_sid=1eb0c7&_nc_ohc=Q-G7AruWuwMAX8faohx&_nc_ht=scontent.ftia5-1.fna&oh=00_AT_0wwLD3OS9sWIFSPq1ROZBocVBH4uTWk9VMb8OOaXiiA&oe=6229285C'
                },
                {
                    title: 'OpenLitterMap',
                    link: 'https://www.facebook.com/groups/103859707056080/',
                    img: 'https://www.facebook.com/images/groups/groups-default-cover-photo-2x.png'
                },
                {
                    title: 'Litter Action',
                    link: 'https://www.facebook.com/groups/103859707056080/',
                    img: 'https://scontent.ftia5-1.fna.fbcdn.net/v/t1.6435-9/173201117_3557415031151152_4860466764379476220_n.jpg?_nc_cat=103&ccb=1-5&_nc_sid=8631f5&_nc_ohc=B5YNGw69DtMAX9HLS9M&_nc_ht=scontent.ftia5-1.fna&oh=00_AT_4MZ3ZF8g6yYXzZ4xtSWOcN9zbTWBLrG75_eDz7N9eBw&oe=62282498'
                },
                {
                    title: 'Plastic Crisis Alliance Group',
                    link: 'https://www.facebook.com/groups/103859707056080/',
                    img: 'https://scontent.ftia5-1.fna.fbcdn.net/v/t39.30808-6/241182745_588127742333087_1267299547323826486_n.jpg?_nc_cat=107&ccb=1-5&_nc_sid=8631f5&_nc_ohc=-TL5LnrNPgoAX9peT6u&_nc_ht=scontent.ftia5-1.fna&oh=00_AT_HA8tFEFweqgARPVzyOhk0jlb6XA5QpXhSIRhzF5_-pg&oe=6209EED9'
                },
                {
                    title: '#WarOnWasteAU',
                    link: 'https://www.facebook.com/groups/103859707056080/',
                    img: 'https://scontent.ftia5-1.fna.fbcdn.net/v/t1.18169-9/18664233_2155274158032270_864836488183625022_n.jpg?_nc_cat=109&ccb=1-5&_nc_sid=8631f5&_nc_ohc=QTYM3A6QQ98AX_RSnLC&_nc_ht=scontent.ftia5-1.fna&oh=00_AT-IQVKRWX04Z_0enzfGOvyk2-PNymlEB0uoL6Jglwq6QQ&oe=6228FF8F'
                },
                {
                    title: 'Climate Change and Plastic Pollution Action Group',
                    link: 'https://www.facebook.com/groups/103859707056080/',
                    img: 'https://scontent.ftia5-1.fna.fbcdn.net/v/t39.30808-6/273548104_1083245462236319_5932189105244178814_n.jpg?stp=dst-jpg_s960x960&_nc_cat=102&ccb=1-5&_nc_sid=8631f5&_nc_ohc=XuwvMS2kfhsAX8A4R1v&_nc_ht=scontent.ftia5-1.fna&oh=00_AT9geyIza6IFmbFSSnRBxjgqewyXOL_RyfbZFRTxmL-PfQ&oe=620A0046'
                },
                {
                    title: 'Stop Plastic Pollution group',
                    link: 'https://www.facebook.com/groups/103859707056080/',
                    img: 'https://scontent.ftia5-1.fna.fbcdn.net/v/t39.30808-6/240533140_588132815665913_1519346919559326859_n.jpg?_nc_cat=107&ccb=1-5&_nc_sid=8631f5&_nc_ohc=xuJ-pC9TLl0AX-2VKQh&_nc_ht=scontent.ftia5-1.fna&oh=00_AT9KzId0gtapLlwFCWBrp3o8mtwkRi4IGGN_XjOxbvZS9Q&oe=6209B46A'
                },
            ],
            socialCards: [
                {
                    title: 'Twitter',
                    link: 'https://twitter.com/OpenLitterMap',
                    icon: 'fa fa-twitter'
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
                    title: 'Slack',
                    link: 'https://join.slack.com/t/openlittermap/shared_invite/zt-fdctasud-mu~OBQKReRdC9Ai9KgGROw',
                    icon: 'fa fa-slack'
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
    computed: {
        imageStyle ()
        {
            return `linear-gradient(to left, transparent 0%, black 100%), url("${window.location.origin}/assets/litter-picking-copy.jpg")`;
        }
    }
};
</script>

<style lang="scss" scoped>
.community {
    background: black no-repeat fixed top right;
    background-size: cover;
}

.section-facebook {
    background-color: rgb(17, 24, 39);

    .hero-body {
        flex-direction: column;
    }

    .facebook-heading {
        flex-shrink: 0;
        margin-bottom: 3rem;
    }

    .facebook-links {
        overflow-x: auto;
    }

    .facebook-card {
        background-color: rgb(31, 41, 55);
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, .1), 0 4px 6px -2px rgba(0, 0, 0, .05);
        overflow: hidden;
        min-width: 16rem;

        &:hover {
            transform: translateY(-0.5rem);
        }

        img {
            object-fit: cover;
            height: 12rem;
            width: 100%;
        }
    }
}

.section-social {
    background-color: #094C54; // #008080 teal

    .hero-body {
        flex-direction: column;
    }

    .social-heading {
        flex-shrink: 0;
        margin-bottom: 3rem;
    }

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

@media screen and (min-width: 1280px) {
    ::-webkit-scrollbar {
        height: 12px;
    }

    ::-webkit-scrollbar-track {
        -webkit-box-shadow: inset 0 0 6px rgba(255, 255, 255, 0.3);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        border-radius: 10px;
        background-color: rgba(255, 255, 255, 0.3);
        -webkit-box-shadow: inset 0 0 6px rgba(255, 255, 255, 0.5);
    }

    .community-hero {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    .section-facebook {
        .hero-body {
            flex-direction: row;
        }

        .facebook-heading {
            margin-right: 3rem;
            margin-bottom: 0;
        }
    }

    .section-social {
        .hero-body {
            flex-direction: row;
        }
        .social-heading {
            margin-right: 3rem;
            margin-top: auto;
            margin-bottom: auto;
        }
        .social-links {
            width: 100%;
            max-width: 1000px;
            margin: auto;
            justify-content: center;
        }
    }
}
</style>
