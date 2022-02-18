<template>
    <section class="hero section-fundraiser">
        <div class="hero-body">
            <div class="py-2 fundraiser">
                <div class="image-wrapper has-text-centered">
                    <img src="/assets/gofundme-brand-logo.png">
                </div>
                <div class="subtitle has-text-light has-text-justified">
                    OpenLitterMap is entirely dependent on donations. We've set up a
                    <a target="_blank" href="https://www.gofundme.com/f/openlittermap">GoFundMe Fundraiser</a>
                    and we're still far away from our target.
                    All contributions are welcome. 🤗
                </div>
            </div>
            <div class="youtube-video">
                <iframe
                    src="https://www.youtube.com/embed/TIzhC0hRBaM"
                    title="OpenLitterMap GoFundMe Video"
                    frameBorder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
            </div>
        </div>
    </section>
</template>
<script>
export default {
    name: 'FundraiserSection',
};
</script>

<style lang="scss" scoped>
.section-fundraiser {
    background-color: #111827;

    .hero-body {
        margin: 0 auto;
    }

    a {
        color: whitesmoke;
        text-decoration: underline;
    }

    .fundraiser {
        max-width: 1000px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;

        img {
            width: 4rem;
            height: 4rem;
            margin: 0 auto 1rem;
        }

        a:hover {
            color: #4A154B;
        }
    }

    .youtube-video {
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 1rem auto;

        iframe {
            max-width: 100% !important;
            width: auto !important;
            height: auto !important;
            border: none;
        }
    }
}

@media screen and (min-width: 1024px) {
    .section-fundraiser {
        .fundraiser {
            flex-direction: row;

            .image-wrapper {
                flex-shrink: 0;
                margin-right: 1rem;
            }

            img {
                margin: 0 auto;
            }
        }

        .youtube-video {
            margin: 2rem auto;
        }
    }
}

@media screen and (min-width: 600px) {
    .section-fundraiser {
        .youtube-video {
            iframe {
                width: 560px !important;
                height: 315px !important;
            }
        }
    }
}

</style>
