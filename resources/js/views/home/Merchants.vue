<template>
    <div class="container p-10-mob">
        <div>
            <p class="title is-2 has-text-centered mb1" style="margin-top: 1em;">
                 Become a Littercoin partner!
            </p>

            <div class="has-text-centered">
                <p>
                    Be a zero-waste hero, join us and accept Littercoin
                </p>
                <p class="mb1">
                    It's like getting paid to save the planet!
                </p>
            </div>


            <transition name="fade">
                <div v-if="formSubmitted">
                    <p class="has-text-centered">Thank you for submitting your store's information!</p>
                </div>
            </transition>
            <form v-if="!formSubmitted" @submit.prevent="submitForm">
                <div class="form-group">
                    <label for="store-name">Store Name:</label>
                    <input
                        placeholder="Your stores name"
                        type="text"
                        id="store-name"
                        name="store-name"
                        v-model="storeName"
                        required
                    >
                </div>
                <div class="form-group">
                    <label for="store-address">Store Address:</label>
                    <textarea
                        placeholder="Your stores address"
                        id="store-address"
                        name="store-address"
                        v-model="storeAddress"
                        required
                    />
                </div>
                <div class="form-group">
                    <label for="store-email">Email:</label>
                    <input
                        placeholder="Your stores email"
                        type="email"
                        id="store-email"
                        name="store-email"
                        v-model="storeEmail"
                        required
                    >
                </div>
                <div class="form-group">
                    <label for="store-phone">Phone:</label>
                    <input
                        placeholder="Your phone"
                        type="tel"
                        id="store-phone"
                        name="store-phone"
                        v-model="storePhone"
                        required
                    >
                </div>
                <div class="form-group">
                    <label for="store-website">Website:</label>
                    <input
                        placeholder="Your stores website"
                        type="url"
                        id="store-website"
                        name="store-website"
                        v-model="storeWebsite"
                    >
                </div>
                <div class="form-group">
                    <label for="message">Message:</label>
                    <textarea
                        placeholder="Write us a message"
                        id="message"
                        name="message"
                        v-model="message"
                    />
                </div>
                <div class="flex jc" style="padding-bottom: 3em;">
                    <button type="submit">Submit</button>
                </div>
            </form>
        </div>
    </div>

</template>

<script>
export default {
    name: "Merchants",
    data() {
        return {
            storeName: '',
            storeAddress: '',
            storeEmail: '',
            storePhone: '',
            storeWebsite: '',
            message: '',
            formSubmitted: false
        };
    },
    methods: {
        submitForm() {
            // code to submit form data
            this.formSubmitted = true;

            axios.post('/api/littercoin/merchants', {
                name: this.storeName,
                address: this.storeAddress,
                email: this.storeEmail,
                phone: this.storePhone,
                website: this.storeWebsite,
                message: this.message,
            })
            .then(response => {
                console.log('merchants', response);
             })
            .catch(error => {
                console.log('merchants', error);
            });
        }
    }
}
</script>

<style scoped>
    .container {
        max-width: 500px;
        margin: 0 auto;
    }

    .form-group {
        margin-bottom: 20px;
    }

    label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }

    input[type="text"],
    input[type="email"],
    input[type="tel"],
    input[type="url"],
    textarea {
        width: 100%;
        padding: 10px;
        border-radius: 5px;
        border: 1px solid #ccc;
        font-size: 16px;
        box-sizing: border-box;
    }

    button[type="submit"] {
        display: block;
        margin-top: 20px;
        padding: 10px;
        border-radius: 5px;
        border: none;
        background-color: #4caf50;
        color: #fff;
        font-size: 16px;
        cursor: pointer;
    }
    .fade-enter-active,
    .fade-leave-active {
        transition: opacity 0.5s;
    }

    .fade-enter,
    .fade-leave-to {
        opacity: 0;
    }


</style>
