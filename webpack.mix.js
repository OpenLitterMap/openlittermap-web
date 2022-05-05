const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/js/app.js', 'public/js')
    .extract([
        "vue",
        "vue-router",
        "vue-localstorage",
        "vue-sweetalert2",
        "vue-toastify",
        "vue-number-animation",
        "vue-echo-laravel",
        "buefy",
        "vue-fullscreen",
        "laravel-permission-to-vuejs",
        "axios",
        "moment",
        "v-img",
        "vue2-leaflet",
        "vue2-leaflet-markercluster",
    ])
    .postCss('resources/css/app.css', 'public/css/app.css');

// remove comments when in production
if (mix.inProduction())
{
    mix.options({
        terser: {
            terserOptions: {
                compress: {
                    drop_console: true
                }
            }
        }
    });

    mix.version();
}

