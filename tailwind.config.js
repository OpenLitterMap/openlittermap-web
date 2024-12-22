import preset from './vendor/filament/support/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue'
    ],
    theme: {
        extend: {
            colors: {
                'gray-text': '#4a4a4a',
                'olm-green': '#03aa6f',
            },
        },
    },
    plugins: [],
}
