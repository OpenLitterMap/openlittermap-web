<template>
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
                <div v-for="card in cards" class="card mr-6 facebook-card">
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
</template>
<script>
export default {
    name: 'FacebookSection',
    data ()
    {
        return {
            cards: [
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
        };
    },
};
</script>
<style lang="scss" scoped>
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

    .section-facebook {
        .hero-body {
            flex-direction: row;
        }

        .facebook-heading {
            margin-right: 3rem;
            margin-bottom: 0;
        }
    }

}
</style>
