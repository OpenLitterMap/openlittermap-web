<template>
    <section class="hero section-zoom">
        <div class="hero-body">
            <div class="py-2 zoom">
                <div class="image-wrapper has-text-centered">
                    <img src="/assets/zoom-brand-logo.png">
                </div>
                <div class="subtitle has-text-light has-text-justified">
                    Join us on the
                    <a target="_blank"
                       href="https://us02web.zoom.us/meeting/register/tZ0ud-GurTktGdQal_ChgggPl41EHmf7I2NB"
                    >weekly Zoom calls at 6pm GMT every Thursday</a>,
                    where we get to hear lots of new ideas and suggestions from our growing global community.
                    Every week our users share their feedback which always helps make our app easier and better to use.
                    Help shape the future direction of our open source data collection and environmental monitoring
                    platform. Call starts in:
                </div>
            </div>
            <div class="clock-wrapper">
                <div v-if="isLive" class="clock">
                    <div class="timeframe live">
                        <p>
                            <a target="_blank"
                               href="https://us02web.zoom.us/meeting/register/tZ0ud-GurTktGdQal_ChgggPl41EHmf7I2NB"
                            >Live</a>
                        </p>
                    </div>
                </div>
                <div v-else class="clock">
                    <div class="timeframe">
                        <p>{{ days }}</p>
                        <small>{{ days === 1 ? 'day' : 'days' }}</small>
                    </div>
                    <span>:</span>
                    <div class="timeframe">
                        <p>{{ hours }}</p>
                        <small>{{ hours === 1 ? 'hour' : 'hours' }}</small>
                    </div>
                    <span>:</span>
                    <div class="timeframe">
                        <p>{{ minutes }}</p>
                        <small>{{ minutes === 1 ? 'minute' : 'minutes' }}</small>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
<script>
export default {
    name: 'ZoomSection',
    data ()
    {
        return {
            days: null,
            hours: null,
            minutes: null,
            isLive: false
        };
    },
    mounted ()
    {
        setInterval(() =>
        {
            let now = new Date();
            let nextThursday = new Date();
            nextThursday.setUTCDate(now.getUTCDate() + (10 - now.getUTCDay()) % 7 + 1);
            let meetingStart = new Date(
                nextThursday.getUTCFullYear(),
                nextThursday.getUTCMonth(),
                nextThursday.getUTCDate(),
                18,
                0,
                0,
                0
            );
            meetingStart.setUTCHours(18);

            // If it's thursday we want to check if the meeting is live
            // usually ends at 19:30 UTC
            if (now.getDay() === 4)
            {
                let todayMeetingStart = new Date(now.getTime());
                todayMeetingStart.setUTCHours(18);
                todayMeetingStart.setUTCMinutes(0);
                todayMeetingStart.setUTCSeconds(0);
                todayMeetingStart.setUTCMilliseconds(0);
                let meetingEnd = new Date(todayMeetingStart.getTime());
                meetingEnd.setUTCHours(19);
                meetingEnd.setUTCMinutes(30);

                this.isLive = now >= todayMeetingStart && now < meetingEnd;

                if (now < todayMeetingStart)
                {
                    meetingStart = new Date(todayMeetingStart.getTime());
                }
            }

            let diff = meetingStart - now;

            let days = Math.floor(diff / (1000 * 60 * 60 * 24));
            let hours = Math.floor(diff / (1000 * 60 * 60));
            let mins = Math.floor(diff / (1000 * 60));

            this.days = days;
            this.hours = hours - days * 24;
            this.minutes = mins - hours * 60;

            if (this.days === 0 && this.hours === 0 && this.minutes === 0) this.isLive = true;
        }, 1000);
    }
};
</script>

<style lang="scss" scoped>
.section-zoom {
    background-color: #008080;

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

    .zoom {
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
    }

    .clock-wrapper {
        max-width: min-content;
        margin: 2rem auto;
    }

    .clock {
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
        color: #06f1f6;
        text-shadow: 1px 1px 7px;
        background-color: #111827;
        border-radius: 0.5rem;
        border: 2px solid;
        padding: 0.5rem 1rem;
        box-shadow: inset 0 0 0.5em 0 #06f1f6, 0 0 0.5em 0 #06f1f6;

        .timeframe {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 0.5rem 1rem;

            p {
                font-size: 2rem;

                a {
                    color: #06f1f6;
                    text-decoration: none;
                }

                a:hover {
                    text-decoration: underline;
                }
            }
        }

        small {
            color: whitesmoke;
        }
    }
}

@media screen and (min-width: 1024px) {
    .section-zoom {
        .zoom {
            flex-direction: row;

            .image-wrapper {
                flex-shrink: 0;
                margin-right: 1rem;
            }
        }

        .clock {
            border-radius: 1rem;
            padding: 1rem 2rem;

            .timeframe {
                padding: 1rem 2rem;

                p {
                    font-size: 4rem;
                }
            }
        }
    }
}

</style>
