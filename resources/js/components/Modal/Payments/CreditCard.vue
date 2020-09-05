<template>
    <div class="cc-wrapper">
        <div class="card-form">

            <!-- Animated Credit Card Display at the top -->
            <Card
                :cardNumber="cardNumber"
                :cardName="cardName"
                :cardMonth="cardMonth"
                :cardYear="cardYear"
                :cardCvv="cardCvv"
                :isCardFlipped="isCardFlipped"
                :focusElementStyle="focusElementStyle"
                :currentCardBackground="currentCardBackground"
                :getCardType="getCardType"
                :otherCardMask="otherCardMask"
                :amexCardMask="amexCardMask"
            />

            <div id="card-element" />

            <!-- Inputs -->
            <div class="card-form__inner">

                <!-- Errors -->
                <div v-if="Object.keys(this.errors).length > 0 && (typeof this.errors.main !== 'undefined')" class="notification is-danger" style="margin-bottom: 20px; margin-top: -40px;">
                    <p>{{ this.errors.main }}</p>
                </div>

                <!-- Card Number -->
                <div class="card-input margin-mobile">
                    <label for="cardNumber" class="card-input__label" :class="errorsExist('cc_number') ? 'label-danger' : ''">{{ $t('creditcard.card-number') }}</label>

                    <input
                        type="text"
                        id="cardNumber"
                        class="card-input__input"
                        :class="errorsExist('cc_number') ? 'border-danger' : ''"
                        v-mask="generateCardNumberMask"
                        v-model="cardNumber"
                        :focus="focusInput"
                        :blur="blurInput"
                        data-ref="cardNumber"
                        autocomplete="off"
                        :placeholder="this.$t('creditcard.placeholders.card-number')"
                        @input="clearErrors('cc_number')"
                    />

                    <div v-if="hasError('cc_number')" :class="errorsExist('cc_number') ? 'error-message' : ''">
                        <span>{{ getFirstError('cc_number') }}</span>
                    </div>
                </div>

                <!-- Card Name -->
                <div class="card-input">
                    <label for="cardName" class="card-input__label" :class="errorsExist('cc_name') ? 'label-danger' : ''">{{ $t('creditcard.card-holder') }}</label>

                    <input
                        type="text"
                        id="cardName"
                        class="card-input__input"
                        :class="errorsExist('cc_name') ? 'border-danger' : ''"
                        v-model="cardName"
                        :focus="focusInput"
                        :blur="blurInput"
                        data-ref="cardName"
                        autocomplete="off"
                        :placeholder="this.$t('creditcard.placeholders.card-holder')"
                        @input="clearErrors('cc_name')"
                    />

                    <div v-if="hasError('cc_name')" :class="errorsExist('cc_name') ? 'error-message' : ''">
                        <span>{{ getFirstError('cc_name') }}</span>
                    </div>
                </div>

                <!-- Month, Year, 3-digits -->
                <div class="card-form__row">
                    <div class="card-form__col">
                        <div class="card-form__group">

                            <!-- Card Month -->
                            <label for="cardMonth" class="card-input__label">{{ $t('creditcard.exp') }}</label>

                            <select
                                class="card-input__input -select"
                                id="cardMonth"
                                v-model="cardMonth"
                                :focus="focusInput"
                                v-on:blur="blurInput"
                                data-ref="cardDate"
                                @change="clearErrors('cc_exp_month')"
                            >
                                <option value="" disabled selected>{{ $t('creditcard.placeholders.exp-month') }}</option>
                                <option v-bind:value="n < 10 ? '0' + n : n" v-for="n in 12" v-bind:disabled="n < minCardMonth" v-bind:key="n">
                                    {{n < 10 ? '0' + n : n}}
                                </option>
                            </select>

                            <div v-if="hasError('cc_exp_month')" :class="errorsExist('cc_exp_month') ? 'error-message error-month' : ''">
                                <span>Invalid Month</span>
                            </div>

                            <!-- Card Year -->
                            <select
                                class="card-input__input -select"
                                id="cardYear"
                                v-model="cardYear"
                                :focus="focusInput"
                                :blur="blurInput"
                                data-ref="cardDate"
                                @change="clearErrors('cc_exp_year')"
                            >
                                <option value="" disabled selected>{{ $t('creditcard.placeholders.exp-year') }}</option>
                                <option v-bind:value="$index + minCardYear" v-for="(n, $index) in 12" :key="n">
                                    {{$index + minCardYear}}
                                </option>
                            </select>

                            <div v-if="hasError('cc_exp_year')" :class="errorsExist('cc_exp_year') ? 'error-message error-year' : ''">
                                <span>Invalid Year</span>
                            </div>
                        </div>
                    </div>

                    <!-- 3-digits -->
                    <div class="card-form__col -cvv">
                        <div class="card-input" style="position: relative;">
                            <label for="cardCvv" class="card-input__label">{{ $t('creditcard.cvv') }}</label>
                            <input
                                type="text"
                                class="card-input__input"
                                id="cardCvv"
                                v-mask="'####'"
                                maxlength="4"
                                v-model="cardCvv"
                                v-on:focus="flipCard(true)"
                                v-on:blur="flipCard(false)"
                                autocomplete="off"
                                :placeholder="this.$t('creditcard.placeholders.cvv')"
                                @input="clearErrors('cc_cvc')"
                                style="text-align: center;"
                            />

                            <div v-if="hasError('cc_cvc')" :class="errorsExist('cc_cvc') ? 'error-message error-cvc' : ''">
                                <span>Invalid CVV</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button :class="button" @click="submit" :disabled="disabled">
                    {{ $t('common.submit') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import Card from './Card'

export default {
    name: 'CreditCard',
    components: { Card },
    data ()
    {
        return {
            btn: 'card-form__button button',
            disabled: false,
            processing: false,
            currentCardBackground: Math.floor(Math.random()* 25 + 1), // just for fun :D
            cardName: "",
            cardNumber: "",
            cardMonth: "",
            cardYear: "",
            cardCvv: "",
            minCardYear: new Date().getFullYear(),
            amexCardMask: "#### ###### #####",
            otherCardMask: "#### #### #### ####",
            cardNumberTemp: "",
            isCardFlipped: false,
            focusElementStyle: null,
            isInputFocused: false,

            stripe: '',
            elements: '',
            card: '',
            intentToken: ''
        };
    },
    mounted ()
    {
        /** Includes Stripe.js dynamically */
        this.includeStripe('js.stripe.com/v3/', function () {
            this.configureStripe();
        }.bind(this));

        this.loadIntent();

        this.cardNumberTemp = this.otherCardMask;

        document.getElementById("cardNumber").focus();
    },

    computed: {

        /**
         * Add ' is-loading' when processing
         */
        button ()
        {
            return this.processing ? this.btn + ' is-loading' : this.btn;
        },

        /**
         * Any errors from backend form validation
         */
        errors ()
        {
            return this.$store.state.payments.errors;
        },

        /**
         *
         */
        generateCardNumberMask ()
        {
            return this.getCardType === "amex" ? this.amexCardMask : this.otherCardMask;
        },

        /**
         * Return card issuer depending on first few digits
         */
        getCardType ()
        {
            let number = this.cardNumber;
            let re = new RegExp("^4");
            if (number.match(re) != null) return "visa";

            re = new RegExp("^(34|37)");
            if (number.match(re) != null) return "amex";

            re = new RegExp("^5[1-5]");
            if (number.match(re) != null) return "mastercard";

            re = new RegExp("^6011");
            if (number.match(re) != null) return "discover";

            re = new RegExp('^9792')
            if (number.match(re) != null) return 'troy'

            return "visa"; // default type
        },

        /**
         *
         */
        minCardMonth ()
        {
            if (this.cardYear === this.minCardYear) return new Date().getMonth() + 1;

            return 1;
        }
    },
    watch: {
        /**
         *
         */
        cardYear ()
        {
            if (this.cardMonth < this.minCardMonth)
            {
                this.cardMonth = "";
            }
        }
    },
    methods: {

        /**
         *
         */
        blurInput ()
        {
            let vm = this;
            setTimeout(() => {
                if (!vm.isInputFocused) {
                    vm.focusElementStyle = null;
                }
            }, 300);
            vm.isInputFocused = false;
        },

        /**
         * Disable the submit button if errors exist
         */
        checkForErrors ()
        {
            // Checking for (typeof this.errors.main == 'undefined') because we don't want to disable submit if card declined
            // In case user wants to try the same card again
            if (Object.keys(this.errors).length > 0 && (typeof this.errors.main == 'undefined')) this.disabled = true;

            else this.disabled = false;
        },

        /**
         * Clear any errors
         */
        clearErrors (key)
        {
            this.$store.commit('clearCustomerCenterErrors', key);

            this.checkForErrors();
        },

        /**
         * Close the modal
         */
        close ()
        {
            this.$store.commit('hideModal');
        },

        /**
         * Configures Stripe by setting up the elements and
         * creating the card element.
         */
        configureStripe ()
        {
            // stripe public key
            this.stripe = Stripe(process.env.MIX_STRIPE_KEY);

            this.elements = this.stripe.elements();
            this.card = this.elements.create('card'); // accepts 2nd arg for styles object https://stripe.com/docs/stripe-js#elements

            this.card.mount('#card-element');
        },

        /**
         * Check if any errors exist for this key
         */
        errorsExist (key)
        {
            return this.errors.hasOwnProperty(key);
        },

        /**
         *
         */
        flipCard (status)
        {
            if (this.getCardType !== "amex") this.isCardFlipped = status;
        },

        /**
         *
         */
        focusInput (e)
        {
            this.isInputFocused = true;
            let targetRef = e.target.dataset.ref;
            let target = this.$refs[targetRef];
            this.focusElementStyle = {
                width: `${target.offsetWidth}px`,
                height: `${target.offsetHeight}px`,
                transform: `translateX(${target.offsetLeft}px) translateY(${target.offsetTop}px)`
            }
        },

        /**
         * Get specific errors for this error key
         */
        getFirstError (key)
        {
            return this.errors[key][0];
        },

        /**
         * Boolean result for if a given key has an error
         */
        hasError (key)
        {
            return (typeof this.errors[key] !== 'undefined');
        },

        /**
         * Include stripe.js dynamically
         */
        includeStripe (URL, callback)
        {
            let documentTag = document,
                tag = 'script',
                object = documentTag.createElement(tag),
                scriptTag = documentTag.getElementsByTagName(tag)[0];
            object.src = '//' + URL;
            if (callback) { object.addEventListener('load', function (e) { callback(null, e); }, false); }
            scriptTag.parentNode.insertBefore(object, scriptTag);
        },

        /**
         * Loads the payment intent key for the user to pay.
         */
        async loadIntent ()
        {
            await axios.get('/api/v1/user/setup-intent')
            .then(response => {
                this.intentToken = response.data;
            }); // .bind(this);
        },

        /**
         * The user wants to save these card details
         */
        submit ()
        {
            const stripe = Stripe(process.env.MIX_STRIPE_KEY)

            stripe.redirectToCheckout({
                lineItems: [{
                    // Define the product and price in the Dashboard first, and use the price
                    // ID in your client-side code.
                    price: 'plan_E579ju4xamcU41',
                    quantity: 1
                }],
                mode: 'subscription',
                successUrl: 'https://www.example.com/success',
                cancelUrl: 'https://www.example.com/cancel'
            });
        },

    }
}
</script>

<style lang="scss" scoped>

    .cc-wrapper {
        display: flex;
        padding: 50px 15px;
        @media screen and (max-width: 700px), (max-height: 500px) {
            flex-wrap: wrap;
            flex-direction: column;
        }
    }

    .border-danger {
        border-color: red !important;
    }

    .label-danger {
        color: red !important;
    }

    .error-message {
        text-align: left;
        color: red;
        font-size: 14px;
        margin-top: 4px;
    }

    .error-month {
        position: absolute;
        bottom: -2em;
        left: 1em;
    }

    .error-year {
        position: absolute;
        bottom: -2em;
        left: 11em;
    }

    .error-cvc {
        position: absolute;
        bottom: -2em;
        left: 1em;
    }

    .card-form {
        max-width: 570px;
        margin: auto;
        width: 100%;

        @media screen and (max-width: 576px) {
            margin: 0 auto;
        }

        &__inner {
            background: #fff;
            box-shadow: 0 30px 60px 0 rgba(90, 116, 148, 0.4);
            border-radius: 10px;
            padding: 35px;
            padding-top: 180px;

            @media screen and (max-width: 480px) {
                padding: 25px;
                padding-top: 165px;
            }
            @media screen and (max-width: 360px) {
                padding: 15px;
                padding-top: 165px;
            }
        }

        &__row {
            display: flex;
            align-items: flex-start;

            @media screen and (max-width: 480px) {
                flex-wrap: wrap;
            }
        }

        &__col {
            flex: auto;
            margin-right: 35px;

            &:last-child {
                margin-right: 0;
            }

            @media screen and (max-width: 480px) {
                margin-right: 0;
                flex: unset;
                width: 100%;
                margin-bottom: 20px;

                &:last-child {
                    margin-bottom: 0;
                }
            }

            &.-cvv {
                max-width: 150px;
                position: relative;

                @media screen and (max-width: 480px) {
                    max-width: initial;
                }
            }
        }

        &__group {
            display: flex;
            align-items: flex-start;
            flex-wrap: wrap;
            position: relative;

            .card-input__input {
                flex: 1;
                margin-right: 15px;

                &:last-child {
                    margin-right: 0;
                }
            }
        }

        &__button {
            width: 100%;
            height: 55px;
            background: #7957d5;
            border: none;
            border-radius: 5px;
            font-size: 22px;
            font-weight: 500;
            font-family: "Source Sans Pro", sans-serif;
            /*box-shadow: 3px 10px 20px 0px rgba(35, 100, 210, 0.3);*/
            color: #fff;
            margin-top: 20px;
            cursor: pointer;

            @media screen and (max-width: 480px) {
                margin-top: 10px;
            }
        }
    }

    .card-input {
        margin-bottom: 20px;

        &__label {
            font-size: 14px;
            margin-bottom: 5px;
            font-weight: 500;
            color: #1a3b5d;
            width: 100%;
            display: block;
            user-select: none;
        }

        &__input {
            width: 100%;
            height: 50px;
            border-radius: 5px;
            /*box-shadow: none;*/
            border: 1px solid #ced6e0;
            transition: all 0.3s ease-in-out;
            font-size: 18px;
            padding: 5px 15px;
            background: none;
            color: #1a3b5d;
            font-family: "Source Sans Pro", sans-serif;

            &:hover,
            &:focus {
                border-color: #3d9cff;
            }

            &:focus {
                box-shadow: 0px 10px 20px -13px rgba(32, 56, 117, 0.35);
            }

            &.-select {
                -webkit-appearance: none;
                background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAeCAYAAABuUU38AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAUxJREFUeNrM1sEJwkAQBdCsngXPHsQO9O5FS7AAMVYgdqAd2IGCDWgFnryLFQiCZ8EGnJUNimiyM/tnk4HNEAg/8y6ZmMRVqz9eUJvRaSbvutCZ347bXVJy/ZnvTmdJ862Me+hAbZCTs6GHpyUi1tTSvPnqTpoWZPUa7W7ncT3vK4h4zVejy8QzM3WhVUO8ykI6jOxoGA4ig3BLHcNFSCGqGAkig2yqgpEiMsjSfY9LxYQg7L6r0X6wS29YJiYQYecemY+wHrXD1+bklGhpAhBDeu/JfIVGxaAQ9sb8CI+CQSJ+QmJg0Ii/EE2MBiIXooHRQhRCkBhNhBcEhLkwf05ZCG8ICCOpk0MULmvDSY2M8UawIRExLIQIEgHDRoghihgRIgiigBEjgiFATBACAgFgghEwSAAGgoBCBBgYAg5hYKAIFYgHBo6w9RRgAFfy160QuV8NAAAAAElFTkSuQmCC');
                background-size: 12px;
                background-position: 90% center;
                background-repeat: no-repeat;
                padding-right: 30px;
            }
        }
    }


    .github-btn {
        position: absolute;
        right: 40px;
        bottom: 50px;
        text-decoration: none;
        padding: 15px 25px;
        border-radius: 4px;
        box-shadow: 0px 4px 30px -6px rgba(36, 52, 70, 0.65);
        background: #24292e;
        color: #fff;
        font-weight: bold;
        letter-spacing: 1px;
        font-size: 16px;
        text-align: center;
        transition: all .3s ease-in-out;

        @media screen and (min-width: 500px) {
            &:hover {
                transform: scale(1.1);
                box-shadow: 0px 17px 20px -6px rgba(36, 52, 70, 0.36);
            }
        }

        @media screen and (max-width: 700px) {
            position: relative;
            bottom: auto;
            right: auto;
            margin-top: 20px;

            &:active {
                transform: scale(1.1);
                box-shadow: 0px 17px 20px -6px rgba(36, 52, 70, 0.36);
            }
        }
    }

    /* Extra small devices (phones, 600px and down) */
    @media only screen and (max-width: 600px) {
        .margin-mobile {
            margin-top: 1em;
        }
    }
</style>
