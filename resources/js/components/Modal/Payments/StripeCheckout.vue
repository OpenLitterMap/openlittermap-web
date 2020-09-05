<template>
    <div class='credit-card-inputs'>

        <Card
             cardNumber
             cardName
             cardMonth
             cardYear
             cardCvv
             isCardFlipped
             focusElementStyle
             currentCardBackground
             getCardType
             otherCardMask
             amexCardMa
        />

<!--        <card-number class='stripe-element card-number'-->
<!--                     ref='cardNumber'-->
<!--                     :stripe='stripe_key'-->
<!--                     :options='options'-->
<!--                     @change='number = $event.complete'-->
<!--        />-->
<!--        <card-expiry class='stripe-element card-expiry'-->
<!--                     ref='cardExpiry'-->
<!--                     :stripe='stripe_key'-->
<!--                     :options='options'-->
<!--                     @change='expiry = $event.complete'-->
<!--        />-->
<!--        <card-cvc class='stripe-element card-cvc'-->
<!--                  ref='cardCvc'-->
<!--                  :stripe='stripe_key'-->
<!--                  :options='options'-->
<!--                  @change='cvc = $event.complete'-->
<!--        />-->
    </div>
</template>

<script>
import { CardNumber, CardExpiry, CardCvc, createToken } from 'vue-stripe-elements-plus'
import CreditCard from './CreditCard'

export default {
    name: 'StripeCheckout',
    components: {
        CardNumber,
        CardExpiry,
        CardCvc,
        CreditCard
    },
    data ()
    {
        return {
            complete: false,
            number: false,
            expiry: false,
            cvc: false,
            stripe_key: process.env.MIX_STRIPE_KEY,
            options: {
                "base": {
                    "iconColor": "#c4f0ff",
                    "color": "#fff",
                    "fontWeight": 500,
                    "fontFamily": "Open Sans",
                    "fontSize": "24px",
                    "fontSmoothing": "antialiased"
                }
            }
        }
    }
}
</script>

<style scoped>

    .credit-card-inputs.complete {
        border: 2px solid green;
    }

</style>
