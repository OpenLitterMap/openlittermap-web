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
                primary: {
                    DEFAULT: '#1E40AF',
                    light: '#3B82F6',
                },
            },
        },
    },
    plugins: [],
}
