<template>
    <section class="hero section-zoom">
        <div class="hero-body">
            <div class="py-2 zoom">
                <div class="image-wrapper has-text-centered">
                    <img width="64" height="64" src="/assets/zoom-brand-logo.png" alt="Zoom">
                </div>
                <div class="subtitle has-text-light has-text-justified">
                    <i18n path="home.community.zoom-text" tag="p">
                        <template #link>
                            <a target="_blank"
                               href="https://us02web.zoom.us/j/86284514720?pwd=OWpqaE1DSG1aWktYQTNDYmR0ZnBKUT09"
                            >{{ $t('home.community.zoom-weekly-calls') }}</a>
                        </template>
                    </i18n>
                </div>
            </div>
            <div class="clock-wrapper">
                <div v-if="isLive" class="clock">
                    <div class="timeframe live">
                        <p>
                            <a target="_blank"
                               href="https://us02web.zoom.us/j/86284514720?pwd=OWpqaE1DSG1aWktYQTNDYmR0ZnBKUT09"
                            >{{ $t('home.community.zoom-live') }}</a>
                        </p>
                    </div>
                </div>
                <div v-else class="clock">
                    <div class="timeframe">
                        <p>{{ days }}</p>
                        <small>{{ $tc('home.community.zoom-days', days) }}</small>
                    </div>
                    <span>:</span>
                    <div class="timeframe">
                        <p>{{ hours }}</p>
                        <small>{{ $tc('home.community.zoom-hours', hours) }}</small>
                    </div>
                    <span>:</span>
                    <div class="timeframe">
                        <p>{{ minutes }}</p>
                        <small>{{ $tc('home.community.zoom-minutes', minutes) }}</small>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
<script>
export default {
    name: 'ZoomSection',
    data () {
        return {
            days: null,
            hours: null,
            minutes: null,
            isLive: false
        };
    },
    mounted () {
        // Toggle this to change daylight savings time
        const daylightSavings = true;

        setInterval(() => {
            let now = new Date();

            let nextMeetingDay = new Date();
            nextMeetingDay.setUTCDate(now.getUTCDate() + (11 - now.getUTCDay()) % 7 + 1);

            let meetingStart = new Date(
                nextMeetingDay.getUTCFullYear(),
                nextMeetingDay.getUTCMonth(),
                nextMeetingDay.getUTCDate(),
                18,
                0,
                0,
                0
            );

            // Turn this on to remove daylight savings time
            if (!daylightSavings) {
                meetingStart.setUTCHours(18);
            }

            // If it's Friday we want to check if the meeting is live
            // usually ends at 19:30 UTC
            if (now.getDay() === 5)
            {
                let todayMeetingStart = new Date(now.getTime());

                const startHour = (daylightSavings)
                    ? 17
                    : 18;

                const endHour = (daylightSavings)
                    ? 19
                    : 20;

                todayMeetingStart.setUTCHours(startHour);
                todayMeetingStart.setUTCMinutes(0);
                todayMeetingStart.setUTCSeconds(0);
                todayMeetingStart.setUTCMilliseconds(0);

                let meetingEnd = new Date(todayMeetingStart.getTime());
                meetingEnd.setUTCHours(endHour);
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

        .live {
            white-space: nowrap;
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
