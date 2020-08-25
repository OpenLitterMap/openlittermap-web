<template>
    <div class="card-list">

        <div class="card-item" :class="{ '-active' : isCardFlipped }">

            <div class="card-item__side -front">

                <div class="card-item__focus"
                     :class="{'-active' : focusElementStyle }"
                     :style="focusElementStyle"
                     ref="focusElement"
                />

                <div class="card-item__cover">
                    <img
                        :src="imgs + currentCardBackground + '.jpeg'"
                        class="card-item__bg"
                    />
                </div>

                <div class="card-item__wrapper">

                    <div class="card-item__top">

                        <img
                            :src="imgs + 'chip.png'"
                            class="card-item__chip"
                        >

                        <div class="card-item__type">

                            <transition name="slide-fade-up">
                                <img
                                    v-if="getCardType"
                                    :key="getCardType"
                                    class="card-item__typeImg"
                                    :src="imgs + getCardType + '.png'"
                                />
                            </transition>

                        </div>
                    </div>

                    <!-- CVV For Amex -->
                    <div  v-if="getCardType === 'amex'" class="">
                        <div class="card-item__cvv-amex">
                            <span v-for="(n, $index) in cardCvv" :key="$index">*</span>
                        </div>
                    </div>

                    <label for="cardNumber" class="card-item__number" ref="cardNumber">
                        <template v-if="getCardType === 'amex'">
                            <span v-for="(n, $index) in amexCardMask" :key="$index">

                                <transition name="slide-fade-up">
                                    <div
                                        v-if="$index > 4 && $index < 14 && cardNumber.length > $index && n.trim() !== ''"
                                        class="card-item__numberItem"
                                    >*</div>

                                    <div
                                        v-else-if="cardNumber.length > $index"
                                        class="card-item__numberItem"
                                        :class="{ '-active' : n.trim() === '' }"
                                        :key="$index"
                                    >{{ cardNumber[$index] }}</div>

                                    <div
                                        v-else
                                        class="card-item__numberItem"
                                        :class="{ '-active' : n.trim() === '' }"
                                        :key="$index + 1"
                                    >{{n}}</div>
                                </transition>

                            </span>
                        </template>

                        <template v-else>
                            <span v-for="(n, $index) in otherCardMask" :key="$index">

                                <transition name="slide-fade-up">
                                    <div
                                        v-if="$index > 4 && $index < 15 && cardNumber.length > $index && n.trim() !== ''"
                                        class="card-item__numberItem"
                                    >*</div>

                                    <div
                                        v-else-if="cardNumber.length > $index"
                                        class="card-item__numberItem"
                                        :class="{ '-active' : n.trim() === '' }"
                                        :key="$index"
                                    >{{ cardNumber[$index]}} </div>

                                    <div
                                        v-else
                                        class="card-item__numberItem"
                                        :class="{ '-active' : n.trim() === '' }"
                                        :key="$index + 1"
                                    >{{n}}</div>
                                </transition>

                            </span>
                        </template>
                    </label>

                    <div class="card-item__content">
                        <label for="cardName" class="card-item__info" ref="cardName">
                            <div class="card-item__holder">Card Holder</div>

                            <transition name="slide-fade-up">

                                <div class="card-item__name" v-if="cardName.length" key="1">
                                    <transition-group name="slide-fade-right">
                                        <span
                                            class="card-item__nameItem"
                                            v-for="(n, $index) in cardName.replace(/\s\s+/g, ' ')"
                                            v-if="$index === $index"
                                            :key="$index + 1"
                                        >{{n}}</span>
                                    </transition-group>
                                </div>
                                <div class="card-item__name" v-else key="2">Full Name</div>

                            </transition>

                        </label>
                        <div class="card-item__date" ref="cardDate">
                            <label for="cardMonth" class="card-item__dateTitle">Expires</label>
                            <label for="cardMonth" class="card-item__dateItem">

                                <transition name="slide-fade-up">
                                    <span v-if="cardMonth" :key="cardMonth">{{ cardMonth }}</span>
                                    <span v-else key="2">MM</span>
                                </transition>

                            </label>
                            /
                            <label for="cardYear" class="card-item__dateItem">

                                <transition name="slide-fade-up">
                                    <span v-if="cardYear" :key="cardYear">{{ String(cardYear).slice(2,4) }}</span>
                                    <span v-else key="2">YY</span>
                                </transition>

                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-item__side -back">
                <div class="card-item__cover">
                    <img :src="this.imgs + currentCardBackground + '.jpeg'" class="card-item__bg" />
                </div>
                <div class="card-item__band" />
                <div class="card-item__cvv">
                    <div class="card-item__cvvTitle">CVV</div>
                    <div class="card-item__cvvBand">
                        <span v-for="(n, $index) in cardCvv" :key="$index">*</span>
                    </div>
                    <div class="card-item__type">
                        <img :src="this.imgs + getCardType + '.png'" v-if="getCardType" class="card-item__typeImg">
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CreditCard',
    props: [
        'cardNumber',
        'cardName',
        'cardMonth',
        'cardYear',
        'cardCvv',
        'isCardFlipped',
        'focusElementStyle',
        'currentCardBackground',
        'getCardType',
        'otherCardMask',
        'amexCardMask'
    ],
    data ()
    {
        return {
            imgs: 'https://raw.githubusercontent.com/muhammederdem/credit-card-form/master/src/assets/images/'
        };
    }
}
</script>

<style scoped lang="scss">

    .card-list {
        margin-bottom: -130px;

        @media screen and (max-width: 480px) {
            margin-bottom: -120px;
        }
    }

    .card-item__cvv-amex {
        color: white;
        margin-top: -6%;
        font-size: 27px;
        font-weight: 500;
        position: absolute;
        left: 78%;
    }

    .slide-fade-up-enter-active {
        transition: all 0.25s ease-in-out;
        transition-delay: 0.1s;
        position: relative;
    }

    .slide-fade-up-leave-active {
        transition: all 0.25s ease-in-out;
        position: absolute;
    }

    .slide-fade-up-enter {
        opacity: 0;
        transform: translateY(15px);
        pointer-events: none;
    }

    .slide-fade-up-leave-to {
        opacity: 0;
        transform: translateY(-15px);
        pointer-events: none;
    }

    .slide-fade-right-enter-active {
        transition: all 0.25s ease-in-out;
        transition-delay: 0.1s;
        position: relative;
    }

    .slide-fade-right-leave-active {
        transition: all 0.25s ease-in-out;
        position: absolute;
    }

    .slide-fade-right-enter {
        opacity: 0;
        transform: translateX(10px) rotate(45deg);
        pointer-events: none;
    }

    .slide-fade-right-leave-to {
        opacity: 0;
        transform: translateX(-10px) rotate(45deg);
        pointer-events: none;
    }

    .card-item {
        max-width: 430px;
        height: 270px;
        margin-left: auto;
        margin-right: auto;
        position: relative;
        z-index: 2;
        width: 100%;

        @media screen and (max-width: 480px) {
            max-width: 310px;
            height: 220px;
            width: 90%;
        }

        @media screen and (max-width: 360px) {
            height: 180px;
        }

        &.-active {
            .card-item__side {

                &.-front {
                    transform: perspective(1000px) rotateY(180deg) rotateX(0deg)
                    rotateZ(0deg);
                }

                &.-back {
                    transform: perspective(1000px) rotateY(0) rotateX(0deg) rotateZ(0deg);
                    /*// box-shadow: 0 20px 50px 0 rgba(81, 88, 206, 0.65);*/
                }
            }
        }

        &__focus {
            position: absolute;
            z-index: 3;
            border-radius: 5px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            transition: all 0.35s cubic-bezier(0.71, 0.03, 0.56, 0.85);
            opacity: 0;
            pointer-events: none;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.65);

            &:after {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                background: rgb(8, 20, 47);
                height: 100%;
                border-radius: 5px;
                filter: blur(25px);
                opacity: 0.5;
            }

            &.-active {
                opacity: 1;
            }
        }

        &__side {
            border-radius: 15px;
            overflow: hidden;
            /*// box-shadow: 3px 13px 30px 0px rgba(11, 19, 41, 0.5);*/
            /*    box-shadow: 0 20px 60px 0 rgba(14, 42, 90, 0.55);*/
            transform: perspective(2000px) rotateY(0deg) rotateX(0deg) rotate(0deg);
            transform-style: preserve-3d;
            transition: all 0.8s cubic-bezier(0.71, 0.03, 0.56, 0.85);
            backface-visibility: hidden;
            height: 100%;

            &.-back {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                transform: perspective(2000px) rotateY(-180deg) rotateX(0deg) rotate(0deg);
                z-index: 2;
                padding: 0;
                background-color: #2364d2;
                background-image: linear-gradient(
                        43deg,
                        #4158d0 0%,
                        #8555c7 46%,
                        #2364d2 100%
                );
                height: 100%;

                .card-item__cover {
                    transform: rotateY(-180deg)
                }
            }
        }
        &__bg {
            max-width: 100%;
            display: block;
            max-height: 100%;
            height: 100%;
            width: 100%;
            object-fit: cover;
        }

        &__cover {
            height: 100%;
            background-color: #1c1d27;
            position: absolute;
            height: 100%;
            background-color: #1c1d27;
            left: 0;
            top: 0;
            width: 100%;
            border-radius: 15px;
            overflow: hidden;

            &:after {
                content: "";
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background: rgba(6, 2, 29, 0.45);
            }
        }

        &__top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 40px;
            padding: 0 10px;

            @media screen and (max-width: 480px) {
                margin-bottom: 25px;
            }
            @media screen and (max-width: 360px) {
                margin-bottom: 15px;
            }
        }

        &__chip {
            width: 60px;

            @media screen and (max-width: 480px) {
                width: 50px;
            }

            @media screen and (max-width: 360px) {
                width: 40px;
            }
        }

        &__type {
            height: 45px;
            position: relative;
            display: flex;
            justify-content: flex-end;
            max-width: 100px;
            margin-left: auto;
            width: 100%;

            @media screen and (max-width: 480px) {
                height: 40px;
                max-width: 90px;
            }

            @media screen and (max-width: 360px) {
                height: 30px;
            }
        }

        &__typeImg {
            max-width: 100%;
            object-fit: contain;
            max-height: 100%;
            object-position: top right;
        }

        &__info {
            color: #fff;
            width: 100%;
            max-width: calc(100% - 85px);
            padding: 10px 15px;
            font-weight: 500;
            display: block;

            cursor: pointer;

            @media screen and (max-width: 480px) {
                padding: 10px;
            }
        }

        &__holder {
            opacity: 0.7;
            font-size: 13px;
            margin-bottom: 6px;
            text-align: left;

            @media screen and (max-width: 480px) {
                font-size: 12px;
                margin-bottom: 5px;
            }
        }

        &__wrapper {
            font-family: "Source Code Pro", monospace;
            padding: 25px 15px;
            position: relative;
            z-index: 4;
            height: 100%;
            text-shadow: 7px 6px 10px rgba(14, 42, 90, 0.8);
            user-select: none;

            @media screen and (max-width: 480px) {
                padding: 20px 10px;
            }
        }

        &__name {
            font-size: 18px;
            line-height: 1;
            white-space: nowrap;
            max-width: 100%;
            overflow: hidden;
            text-align: left;
            text-overflow: ellipsis;
            text-transform: uppercase;

            @media screen and (max-width: 480px) {
                font-size: 16px;
            }
        }

        &__nameItem {
            display: inline-block;
            min-width: 8px;
            position: relative;
        }

        &__number {
            font-weight: 500;
            line-height: 1;
            color: #fff;
            font-size: 27px;
            margin-bottom: 35px;
            display: inline-block;
            padding: 10px 15px;
            cursor: pointer;

            @media screen and (max-width: 480px) {
                font-size: 21px;
                margin-bottom: 15px;
                padding: 10px 10px;
            }

            @media screen and (max-width: 360px) {
                font-size: 19px;
                margin-bottom: 10px;
                padding: 10px 10px;
            }
        }

        &__numberItem {
            width: 16px;
            display: inline-block;

            &.-active {
                width: 30px;
            }

            @media screen and (max-width: 480px) {
                width: 13px;

                &.-active {
                    width: 16px;
                }
            }

            @media screen and (max-width: 360px) {
                width: 12px;

                &.-active {
                    width: 8px;
                }
            }
        }

        &__content {
            color: #fff;
            display: flex;
            align-items: flex-start;
        }

        &__date {
            flex-wrap: wrap;
            font-size: 18px;
            margin-left: auto;
            padding: 10px;
            display: inline-flex;
            width: 80px;
            white-space: nowrap;
            flex-shrink: 0;
            cursor: pointer;

            @media screen and (max-width: 480px) {
                font-size: 16px;
            }
        }

        &__dateItem {
            position: relative;

            span {
                width: 22px;
                display: inline-block;
            }
        }

        &__dateTitle {
            opacity: 0.7;
            font-size: 13px;
            padding-bottom: 6px;
            width: 100%;

            @media screen and (max-width: 480px) {
                font-size: 12px;
                padding-bottom: 5px;
            }
        }

        &__band {
            background: rgba(0, 0, 19, 0.8);
            width: 100%;
            height: 50px;
            margin-top: 30px;
            position: relative;
            z-index: 2;

            @media screen and (max-width: 480px) {
                margin-top: 20px;
            }
            @media screen and (max-width: 360px) {
                height: 40px;
                margin-top: 10px;
            }
        }

        &__cvv {
            text-align: right;
            position: relative;
            z-index: 2;
            padding: 15px;

            .card-item__type {
                opacity: 0.7;
            }

            @media screen and (max-width: 360px) {
                padding: 10px 15px;
            }
        }

        &__cvvTitle {
            padding-right: 10px;
            font-size: 15px;
            font-weight: 500;
            color: #fff;
            margin-bottom: 5px;
        }

        &__cvvBand {
            height: 45px;
            background: #fff;
            margin-bottom: 30px;
            text-align: right;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: #1a3b5d;
            font-size: 18px;
            border-radius: 4px;
            /*box-shadow: 0px 10px 20px -7px rgba(32, 56, 117, 0.35);*/

            @media screen and (max-width: 480px) {
                height: 40px;
                margin-bottom: 20px;
            }

            @media screen and (max-width: 360px) {
                margin-bottom: 15px;
            }
        }
    }

</style>
