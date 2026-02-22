<template>
    <section class="hero section-faq">
        <div class="hero-body">
            <div class="py-2 mb-6">
                <div class="title has-text-light has-text-centered">
                    {{ $t('common.faq') }}
                </div>
            </div>
            <div class="faq">
                <ul>
                    <li>
                        <input type="checkbox" checked />
                        <i></i>
                        <h2>What is OpenLitterMap?</h2>
                        <p>OpenLitterMap is an open source, interactive, and accessible databsase of the world's litter & plastic pollution.
                            We are turning cleaning the planet into a game, to make it fun and easy to collect data.
                            This data is important to give visibility to litter, and help find solutions like where to put bins, ashtrays & more.
                        </p>
                    </li>

                    <li>
                        <input type="checkbox" checked />
                        <i></i>
                        <h2>Why is it called OpenLitterMap?</h2>
                        <p>
                            "Open" - because all of our code & data is open source. This means that anyone can access the code, learn from it,
                            improvee it, or use it in another project! You don't have to be a developer to appreciate how powerful this is.
                            Open source code can be passed on from generation to generation,
                            who can build on it, learn from it and use it to continue to help improve society for the betterment of humanity.
                            <br> <br>
                            Not only is all of our code open source, which you can find at <a href="https://github.com/openlittermap">https://github.com/openlittermap</a>
                            but all of our data is also openly available for anyone to download and start using. This is in contrast to many other apps,
                            who do not open up access to their code and data as they are trying to keep it for themselves (booo!!!)
                            <br>
                            "LitterMap" - because we visualise our entire global repository on the global map, which loads all data in
                            30kb and 33ms (August 2022).
                            <br>
                            The OpenLitterMap name was inspired by <a href="https://openstreetmap.org">OpenStreetMap</a> which is the most
                            detailed & comprehensive map of the world ever created by over 2-million volunteers that contiunes to be updated
                            constantly. Unlike other services like Google Maps, which is not open and owned by Google, OpenStreetMap code
                            and data is open to everyone, allowing anyone to be a part of adding, editing or using the code and/or data.
                            Inspired by the open source values of OpenStreetMap, we decided to apply the same open values to litter and plastic pollution.
                        </p>
                    </li>
                    <li>
                        <input type="checkbox" checked>
                        <i></i>
                        <h2>How do I take pictures?</h2>
                        <p>
                            Firstly, make sure that your phone has the right settings:
                            <br>&emsp; &bull; iPhone. Open Settings -> Privacy -> Location. Turn "Camera On While Using".

                            <br>&emsp; &bull; Android. Open the Camera.
                            <br>Go to camera settings => Activate Geotagging.
                            <br>Pull down top-menu and Activate GPS

                            <br><br>There are different ways to take pictures:
                            <br>&emsp; &bull; Open your normal camera and use that to take the photos (our preferred way)
                            <br>&emsp; &bull; Open the app and take pictures from within the app (This is currently disabled.)

                            <br><br>There are 2 ways to upload and tag your data. You can use the mobile app, or you
                            can use openlittermap.com and log in there. If you visit the website, you have to upload
                            your images first, then tag them. Some people prefer this way, as you get a full screen,
                            a keyboard, and you can tag many items at once. Other people prefer using the mobile app,
                            where you have to select your images first, tag them, and upload the tagged ones.

                            <br>Check your setting: do you normally pick up the litter or not?

                            <br>If you want to take pictures without draining your battery, it is
                            okay to put it in flight-mode! To determine your location, you need
                            the GPS sensor in your device to connect to a location-tracking satellite,
                            not your cellular or wifi network. You can collect geospatial data anywhere, without
                            needing a connection to anything else. This means that it works in the most remote
                            parts of the world, but you need to connect to the internet if you want to upload your data.

                        </p>
                    </li>

                    <li>
                        <input type="checkbox" checked>
                        <i></i>
                        <h2>What is Littercoin?</h2>
                        <p>Littercoin is the reward that our users get for producing geographic information.
                            You can not buy Littercoin. You can only earn it by joining the community and helping us collect data.
                            <br>Littercoin does not yet have a value, but we are working on it by locking ADA in a smart contract
                            which will give each Littercoin value. Our vision is that your Littercoin can only be spent at
                            participating pre-approved zero-waste stores that partner up. Unlike traditional government
                            money that is printed out of thin-air, Littercoin is a new type of environmental currency that is
                            created by people helping to clean the planet and can be spent in climate-approved stores only.
                            <br>Unlike other cryptocurrencies, we are not going to list Littercoin on any exchange, making it non-tradable.
                            The smart contract will be able to verify the integrity of each token, proving it has not been traded or
                            listed on an exchange.
                            <br>Cheating the system (‘oh, let me just drop a bunch of litter here and take a lot of
                            photos of it’) is possible, but in reality, nobody does this and the benefits will far outweigh any
                            cheating. There is also so much litter out there that finding it is much easier than carrying it around.
                            We have a system in place that involves reviewing all tags on each image. This is generating high quality
                            data that is being used to train the OpenLitterAI, which will make verification easier.
                            If you really want to cheat the system, you might as well rob a zero-waste store.
                            (Please don’t).
                        </p>
                    </li>
                    <li>
                        <input type="checkbox" checked />
                        <i></i>
                        <h2>How can I download the images?</h2>
                        <p>This feature is not working properly yet as openlittermap is still in development.
                            Do you think the development of this technology is important?
                            https://gofundme.com/openlittermap
                        </p>
                    </li>
                    <li>
                        <input type="checkbox" checked>
                        <i></i>
                        <h2>What’s the use of having an option ‘I did not pick it up’?</h2>
                        <p>Isn’t it all about cleaning the earth and picking litter? Good question! Yes, the idea is to
                            make the world a better place, and there are many ways to do that. Most of us pick litter,
                            so right at that moment, a difference is being made, the world is a bit cleaner than before.
                            And we don’t just pick the litter, but we also take a picture to create data about that
                            litter that can be used long after the litter is gone.
                            <br>However, there are situatuibs when someone would take a picture without picking the
                            litter:
                            <br>&emsp; &bull; The litter is too big. You are walking with your dog and picking some
                            litter while doing so, but suddenly you see something that shouldn’t be there but it is too
                            big. (a car-wheel, a broken toilet, etc.).
                            <br>&emsp; &bull; It’s too dangerous. For instance during Covid, a lot of litter-pickers
                            stopped picking litter like facemasks because of the risk of Covid. However, taking pictures
                            of the facemasks was still possible and welcome.
                            <br>&emsp; &bull; You believe that leaving the litter where it was found is making a bigger
                            impact. This might also be true. Some people believe that by removing the litter, you are in
                            fact saying ‘dump whatever you want, I’ll come around and pick it up’. Others believe ‘if
                            the place is clean, it will stay clean for a longer period of time. We ourselves don’t
                            believe in either of them. Anyway, if you want to make an impact by not touching the litter,
                            you can still register it.
                            <br> &emsp; &bull; Someone else can pick it up. Suppose you are in a wheelchair, or late for a meeting.
                            By collecting data, you can create data that other people can respond to. This feature is still
                            in development.
                            <br> &emsp; &bull; We can also use those pictures to train our Machine Learning model. The more pictures we
                            have from different kinds of litter, the better we can train the computer to recognise the litter;
                            eventually it can help us in predicting what the litter in the picture is and
                            lessen the trouble of you or us having to tag it manually
                            <br> &emsp; &bull; The data tells an important story. Some people don't want to pick up litter, but
                            they are happy to try and help educate society. We have been picking up litter for decades
                            and its getting worse, so clearly picking up litter alone is not working. If picking up
                            litter is all we do, that is all we will ever do. Collecting data can be a more effective
                            educational tool long-term to fix the problem.
                        </p>
                    </li>
                    <!--                    <li>-->
                    <!--                        <input type="checkbox" checked>-->
                    <!--                        <i></i>-->
                    <!--                        <h2>What is an XP and what can I do with it?</h2>-->
                    <!--                        <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. A architecto consequatur-->
                    <!--                            consequuntur dolor dolores eos eum excepturi harum hic itaque laborum placeat possimus, quis-->
                    <!--                            rem repellendus repudiandae totam veritatis voluptatum?</p>-->
                    <!--                    </li>-->
                    <li>
                        <input type="checkbox" checked>
                        <i></i>
                        <h2>Taking pictures and tagging them is taking a lot of time, what to do?</h2>
                        <p>You could try our ‘bulk-tag’option. You can find this on the add-tags page, or your profile.
                            Here you can see the photos that you uploaded from web that you need to tag.
                            If you have similar photos (for instance all pictures with a redbull-can),
                            you can select all of them and then choose ‘start tagging’, now you only have
                            to give the right tags once and these will be applied to all selected pictures.
                            The pictures that were not selected remain so you could repeat the process if you want to.
                            This tool actually works really good, go check it out!
                        </p>
                    </li>
                    <li>
                        <input type="checkbox" checked>
                        <i></i>
                        <h2>What kind of pictures should I take?</h2>
                        <p>
                            <br>&emsp; &bull; This is entirely up to you and depends on what kind of story you want to take.
                            <br>&emsp; &bull; If you want, you can add 1 tag to each photo.
                            <br>&emsp; &bull; Or, you can include many objects in a single photo.
                            <br>&emsp; &bull; If its not possible to count the litter, you can use the Dumping category
                            (small, medium, large) and pick a number between 0-100 depending on how bad you think the problem is.

                            <br>&emsp; &bull; When possible, hold your camera as close to the item as possible.
                            <br>&emsp; &bull; If there is a brand on the item, make sure that is also visible in the
                            picture.
                            <br>&emsp; &bull; Don't include people, faces, shadows, feet, or clothes. Please take photos of litter ONLY.
                            With these guidelines, you can help us create an excellent quality datasetset and you will
                            make life easier for those who need to help verify the data.
                            However, there are situations where this is not possible. For instance, you see something
                            floating in the water and are not able to pick it, or turn it to see if there is a brand on
                            it, or to zoom in. That’s okay, it is still valuable data and we welcome your contribution.

                            <br>Do I have to take 1 photo per item?
                            <br>&emsp; &bull; No, you don’t. You can take 1 photo, apply a tag of cigarette butts tag with a quantity of 50.
                            <br>And if I find different objects close together, do i have to sort those first?
                            <br>&emsp; &bull; Some people do this, but its not necessary. It all depends what kind of story you want to tell.
                            <br>So do whatever you prefer, but do try and help us build a high quality database!
                        </p>
                    </li>
                    <li>
                        <input type="checkbox" checked>
                        <i></i>
                        <h2>How to tag correctly?</h2>
                        <p>
                            There is no single method to tag pictures, all data is valuable. It is also 'your story'
                            to tell. However, you should try to tell what kind of item it is (it should be available
                            under some category, and only if it is not, use 'other') and if possible add the brand as
                            well (if the brand is not available yet, you could use the custom tags input). But try to use
                            the pre-defined tags as much as possible.
                            <br>If possible, tag the ‘worst’ material. So an item with both plastic and paper, tag as
                            plastic.
                            <br><br>Some examples:
                            <br>red bull can → 2 tags: ‘the object’ (look for the best ‘can’ that you can find) + brand
                            <br>Soda can with a brand that we don’t have yet → 1 predefined tag (can) + 1
                            customtag:somebrandname
                            <br>If you have found an energy-can, only give 1 tag for the item: so don’t tag it as ‘can’
                            and ‘energycan’, but just as an energycan
                            <br><br>Multiple litter items in a photo can be tagged with appropriate counts applied. A
                            photo can contain a mixture of beer bottles, beer cans, plastic cups, or any number of
                            recognized category items. Multiple recognized brands and their counts can also be applied.
                        </p>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>

<script>
export default {
    name: 'FAQ',
    mounted() {
        window.scrollTo(0, 0);
    }
};
</script>

<style lang="scss" scoped>
.section-faq {
    background-color: #111827;
    min-height: 100%;
    padding-bottom: 100px;

    .hero-body {
        margin: 0 auto;
    }

    // Design taken from https://codepen.io/alvarotrigo/pen/qBpBezx
    .faq {
        max-width: 1000px;

        a {
            color: whitesmoke;
            text-decoration: underline;
        }

        a:hover {
            color: #EE6C4D;
        }

        .transition, ul li i:before, ul li i:after, p {
            transition: all 0.25s ease-in-out;
        }

        .flipIn, ul li, h1 {
            animation: flipdown 0.5s ease both;
        }

        .no-select, h2 {
            -webkit-tap-highlight-color: rgba(0, 0, 0, 0);
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

        h2 {
            color: #ff6873;
        }

        h2 {
            font-size: 26px;
            line-height: 34px;
            font-weight: 300;
            letter-spacing: 1px;
            display: block;
            margin: 0 0 14px;
            cursor: pointer;
        }

        p {
            color: whitesmoke;
            position: relative;
            overflow: hidden;
            max-height: 800px;
            opacity: 1;
            transform: translate(0, 0);
            z-index: 2;
        }

        ul {
            list-style: none;
            perspective: 900;
            padding: 0;
            margin: 0;
        }

        ul li {
            position: relative;
            margin: 0;
            padding: 16px 0;
            border-top: 1px solid #dce7eb;
        }

        ul li {
            animation-delay: 0.3s;
        }

        ul li:last-of-type {
            padding-bottom: 0;
        }

        ul li i {
            position: absolute;
            transform: translate(-6px, 0);
            margin-top: 16px;
            right: 0;
        }

        ul li i:before, ul li i:after {
            content: "";
            position: absolute;
            background-color: #ff6873;
            width: 3px;
            height: 9px;
        }

        ul li i:before {
            transform: translate(-2px, 0) rotate(45deg);
        }

        ul li i:after {
            transform: translate(2px, 0) rotate(-45deg);
        }

        ul li input[type=checkbox] {
            position: absolute;
            cursor: pointer;
            width: 100%;
            height: 100%;
            z-index: 1;
            opacity: 0;
        }

        ul li input[type=checkbox]:checked ~ p {
            margin-top: 0;
            max-height: 0;
            opacity: 0;
            transform: translate(0, 50%);
        }

        ul li input[type=checkbox]:checked ~ i:before {
            transform: translate(2px, 0) rotate(45deg);
        }

        ul li input[type=checkbox]:checked ~ i:after {
            transform: translate(-2px, 0) rotate(-45deg);
        }

        @keyframes flipdown {
            0% {
                opacity: 0;
                transform-origin: top center;
                transform: rotateX(-90deg);
            }
            5% {
                opacity: 1;
            }
            80% {
                transform: rotateX(8deg);
            }
            83% {
                transform: rotateX(6deg);
            }
            92% {
                transform: rotateX(-3deg);
            }
            100% {
                transform-origin: top center;
                transform: rotateX(0deg);
            }
        }
    }
}
</style>

