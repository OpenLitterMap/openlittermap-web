<template>
    <section class="hero section-slack">
        <div class="hero-body">
            <div class="py-2 slack">
                <div class="image-wrapper has-text-centered">
                    <img src="/assets/slack-brand-logo.png">
                </div>
                <div class="subtitle has-text-light has-text-justified">
                    <a target="_blank"
                       href="https://join.slack.com/t/openlittermap/shared_invite/zt-fdctasud-mu~OBQKReRdC9Ai9KgGROw"
                    >Slack</a>
                    is our main medium of communication and collaboration.
                    Power-users, newcomers, developers, a community of over 370 members - we're all there.
                    Drop us a line and you'll get an instant reply, we're online all the time.
                </div>
            </div>
            <div class="screenshot">
                <a href="#" class="is-overlay">
                    <i class="fa fa-external-link fa-fw fa-4x"></i>
                </a>
                <img src="/assets/slack-screenshot.png">
            </div>
        </div>
    </section>
</template>
<script>
export default {
    name: 'SlackSection',
};
</script>

<style lang="scss" scoped>
.section-slack {
    background-color: #4A154B;

    .hero-body {
        margin: 0 auto;
    }

    a {
        color: whitesmoke;
        text-decoration: underline;
    }

    a:hover {
        color: #094C54;
    }

    .slack {
        max-width: 1000px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;

        .image-wrapper img {
            width: 4rem;
            height: 4rem;
            margin: 0 auto 1rem;
        }
    }

    .screenshot {
        position: relative;
        margin: 1rem auto;
        overflow: hidden;
        max-width: 700px;

        img {
            border-radius: 0.5rem;
        }

        a {
            display: flex;
            justify-content: center;
            align-items: center;
            text-decoration: none;
            font-size: 0.5rem;

            &:hover {
                color: whitesmoke;
                transform: scale(1.05);
            }
        }
    }
}

@media screen and (min-width: 1024px) {
    .section-slack {
        .slack {
            flex-direction: row;

            .image-wrapper {
                flex-shrink: 0;
                margin-right: 1rem;

                img {
                    margin: 0 auto;
                }
            }
        }

        .screenshot {
            margin: 2rem auto;
            overflow: hidden;
            max-width: 700px;

            img {
                border-radius: 0.5rem;
            }

            a {
                font-size: 1rem;
            }
        }
    }
}

</style>
