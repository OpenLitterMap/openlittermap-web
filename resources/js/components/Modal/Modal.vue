<template>
    <transition name="modal">
        <div
            @click="close"
            class="modal-mask modal-flex"
        >
            <div
                @click.stop
                :class="container"
            >

                <!-- Header -->
                <header
                    :class="header"
                >
                    <p
                        class="modal-card-title"
                    >{{ title }}</p>

                    <i
                        v-show="showIcon"
                        class="fa fa-times close-login"
                        @click="close"
                    />
                </header>

                <!-- Main content -->
                <component
                    :class="inner_container"
                    :is="type"
                />
            </div>
        </div>
    </transition>
</template>

<script>
/* Auth */
import Login from './Auth/Login'

/* Payments */
import CreditCard from './Payments/CreditCard'

/* Profile */
import AddManyTagsToManyPhotos from './Photos/AddManyTagsToManyPhotos';
import ConfirmDeleteManyPhotos from './Photos/ConfirmDeleteManyPhotos';

export default {
    name: 'Modal',
    components: {
        // Auth
        Login,

        // Payments
        CreditCard,

        // Profile
        AddManyTagsToManyPhotos,
        ConfirmDeleteManyPhotos
    },
    mounted () {
        // Close modal with 'esc' key
        document.addEventListener('keydown', (e) => {
            if (e.keyCode === 27)
            {
                this.close();
            }
        });
    },
    data () {
        return {
            btn: 'button is-medium is-primary',
            processing: false
        };
    },
    computed: {
        /**
         * Show spinner when processing
         */
        button ()
        {
            return this.processing ? this.btn + ' is-loading' : this.btn;
        },

        /**
         * What container class to return
         */
        container ()
        {
            if (this.type === 'CreditCard') return 'transparent-container';

            return 'modal-container';
        },

        /**
         * What header class to show
         */
        header ()
        {
            if (this.type === 'CreditCard') return '';

            return 'modal-card-head';
        },

        /**
         * What inner-modal-container class to show
         */
        inner_container ()
        {
            if (this.type === 'Login') return 'inner-login-container';

            return 'inner-modal-container';
        },

        /**
         * Return false to hide the X close icon
         */
        showIcon ()
        {
            return this.type !== 'CreditCard';
        },

        /**
         * Get the title for the modal
         */
        title ()
        {
            return this.$store.state.modal.title;
        },

        /**
         * Shortcut for modal.type
         */
        type ()
        {
            return this.$store.state.modal.type;
        }
    },
    methods: {
        /**
         * Action to dispatch when primary button is pressed
         */
        async action ()
        {
            this.processing = true;

            await this.$store.dispatch(this.$store.state.modal.action);

            this.processing = false;
        },

        /**
         * Close the modal
         */
        close ()
        {
            this.$store.commit('hideModal');
        }
    }
}
</script>

<style scoped lang="scss">

    .close-login {
        padding: 0.5em;
        cursor: pointer;
    }

    .my-class {
        background-color: red;
        font-size: 20px;
        border: 2px solid black;
    }

    .modal-enter .modal-container,
    .modal-leave-active .modal-container {
        -webkit-transform: scale(1.1);
        transform: scale(1.1);
    }

    .modal-enter {
        opacity: 0;
    }
    .modal-leave-active {
        opacity: 0;
    }

    .modal-enter .modal-container,
    .modal-leave-active .modal-container {
        -webkit-transform: scale(1.1);
        transform: scale(1.1);
    }

    .inner-modal-container {
        padding: 1em 2em;
    }

    .inner-login-container {
        padding-top: 1em;
    }

    .modal-container {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .33);
        display: inline-block;
        font-family: Helvetica, Arial, sans-serif;
        position: relative;
        margin: 30px auto;
        transition: all .3s ease;
        width: 585px;

        @media (max-width: 700px) {
            width: 80%;
        }
    }

    .modal-flex {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .modal-header {
        margin-bottom: 1em;
        position: relative;
        text-align: center;
    }

    .modal-mask {
        background-color: rgba(0, 0, 0, .5);
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow-y: scroll;
        overflow-x: hidden;
        transition: opacity .3s ease;
        text-align: center;
        z-index: 1555;
    }

    .top-left {
        position: absolute;
        left: 2em;
    }

    .top-right {
        position: absolute;
        top: 0;
        right: 1em;
        padding: .3em;
        cursor: pointer;
        z-index: 9999;
    }

    .transparent-container {
        background-color: transparent;
        border-radius: 10px;
        box-shadow: none;
        display: inline-block;
        font-family: Helvetica, Arial, sans-serif;
        padding: 2.5em 0;
        position: relative;
        margin: 30px auto;
        transition: all .3s ease;
        width: 585px;

        @media (max-width: 700px) {
            width: 80%;
        }
    }

    .info-title {
        color: #459ef5;
        cursor: pointer;
        margin-top: 1.75em;
    }

    @media only screen and (max-width: 600px)
    {
        .mobile-only {
            padding-bottom: 0px;
        }

        .inner-modal-container  {
            padding: 1em;
        }

        .transparent-container {
            padding: 15em 0 0 0;
            width: 100%;
        }
    }
</style>
