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
                        <input type="checkbox">
                        <i></i>
                        <h2>Where can I download the pictures?</h2>
                        <p>This is probably one of the most frequently asked and logical questions.
                            We are ‘Open Litter Map’ for a reason. All of our source-code is open, and so is our data.
                            However, we are still all volunteers spending a lot of time working on OpenLitterMap, taking
                            hundreds of pictures and tagging them and we need those for our Machine Learning project.
                            So at this moment everything is open, and you can download all data from all countries to
                            use this information in research; the pictures, however, are not.
                            <br>That is, not yet!
                            <br>We do plan to open up those pictures as well, as soon as we are ready for that.
                            <br>Like mentioned, we are mainly working as volunteers, with some financial support and we
                            go as fast as we can. The speed however depends on the support that we get.
                            <br>That doesn’t mean that you have to support us, but if you could, that would be very
                            welcome and also if you know others that could, please let them know. The faster we go, the
                            faster we can also free up all pictures.</p>
                    </li>
                    <li>
                        <input type="checkbox" checked>
                        <i></i>
                        <h2>What is Littercoin?</h2>
                        <p>Littercoin is the award that people get for picking litter and/or creating data that can be
                            used by themselves or by others. You can not buy Littercoin anywhere, you can only earn by
                            joining the community in taking pictures of litter and uploading and tagging them.
                            <br>A Littercoin represents a value (not decided yet what's the value) and Littercoin can be
                            spent (eventually) at zero-waste stores that partner up.
                            <br>Cheating the system (‘oh, let me just drop a bunch of litter here and take a lot of
                            photos of it’) might be possible, but we have a system in place that involves approving
                            pictures. After all, why would you make an effort in trying to earn Littercoin that can be
                            spent in zero-waste stores? The whole idea is to do good for the world and get rewarded for
                            it. If you really want to cheat the system, you might as well rob a zero-waste store.
                            (Please don’t).
                        </p>
                    </li>
                    <li>
                        <input type="checkbox" checked>
                        <i></i>
                        <h2>What’s the use of having an option ‘I did not pick it up’?</h2>
                        <p>Isn’t it all about cleaning the earth and picking litter? Good question! Yes, the idea is to
                            make the world a better place, but there are many ways to do that. Most of us pick litter,
                            so right at that moment, a difference is being made, the world is a bit cleaner than before.
                            And we don’t just pick the litter, but we also take a picture to create data about that
                            litter that can be used long after the litter is gone.
                            <br>However, there are reasons why you should/could take a picture without picking the
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
                            <br>Even pictures of litter that has not been picked up can be valuable. It still is
                            information about litter that can be used by others. We can use those pictures to train our
                            Machine Learning model. The more pictures we have from different kinds of litter, the better
                            we can train the computer to recognise the litter; eventually it can help us in predicting
                            what the litter in the picture is and lessen the trouble of having to tag it ourselves.
                        </p>
                    </li>
                    <li>
                        <input type="checkbox" checked>
                        <i></i>
                        <h2>What is an XP and what can I do with it?</h2>
                        <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. A architecto consequatur
                            consequuntur dolor dolores eos eum excepturi harum hic itaque laborum placeat possimus, quis
                            rem repellendus repudiandae totam veritatis voluptatum?</p>
                    </li>
                    <li>
                        <input type="checkbox" checked>
                        <i></i>
                        <h2>Taking pictures and tagging them is taking a lot of time, what to do?</h2>
                        <p>You could try our ‘bulk-tag’option. It is a little hidden at the moment:
                            More > Profile > View my Photos. Here you can see the photos that you uploaded via web and
                            that you need to tag. If you have similar photos (for instance all pictures with a
                            redbull-can), you can select all of them and then chose ‘start tagging’, now you only have
                            to give the right tags once and these will be applied to all selected pictures. The pictures
                            that were not selected remain so you could repeat the process if you want to.
                        </p>
                    </li>
                    <li>
                        <input type="checkbox" checked>
                        <i></i>
                        <h2>How do I take pictures?</h2>
                        <p>
                            Firstly, make sure that your phone has the right settings:
                            <br>&emsp; &bull; Sharing your location should be on, because that is the way to get GPS
                            location on your photos (if you want to take pictures without draining your battery, it is
                            okay to put it in flight-setting).

                            <br><br>Next, there are different ways to take pictures:
                            <br>&emsp; &bull; Open the camera on the phone and use that to take the photos (preferred way)
                            <br>&emsp; &bull; Open the app and take pictures from within the app (optional way)

                            <br><br>If you have taken pictures with your native camera, in order to be able to upload them,
                            you have to set your phone to allow OLM to use your gallery, apart from approving OLM to use
                            ‘selected photos’ you should say approve to use ‘all’ photos.‘ That should make it work
                            <br>Check your setting: do you normally pick up the litter or not?
                        </p>
                    </li>
                    <li>
                        <input type="checkbox" checked>
                        <i></i>
                        <h2>What kind of pictures should I take?</h2>
                        <p>
                            The rule of thumb is:
                            <br>&emsp; &bull; Take one picture per item.
                            <br>&emsp; &bull; Try to hold your camera close to the item so the item is ‘full-screen’.
                            <br>&emsp; &bull; If there is a brand on the item, make sure that is also visible in the
                            picture.
                            <br>&emsp; &bull; Try to avoid taking a picture with people visible in it.
                            With the ‘rules’ above, you create the best pictures which make for the best data and you
                            make life easier for those who need to check the pictures.
                            However, there are situations where this is not possible. For instance, you see something
                            floating in the water and are not able to pick it, or turn it to see if there is a brand on
                            it, or to zoom in. That’s okay, it is still valuable data.

                            <br>Do I have to take 1 photo per item?
                            <br>&emsp; &bull; No, you don’t, you might also take 1 photo of several similar items, for
                            instance if you find a spot with 50 cigarette butts, you don’t have to take 50 pictures, you
                            can also take 1 picture. See ‘tagging’ to understand how you would tag those.
                            <br>And if I find different objects close together, do i have to sort those first?
                            <br>&emsp; &bull; No, you don’t, you can even take 1 photo with different items on it. See
                            ‘tagging’ to understand how you would tag those.
                            <br>So do whatever you prefer, but keep in mind that all photos have to be verified so the
                            clearer the photo is, the better.
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

