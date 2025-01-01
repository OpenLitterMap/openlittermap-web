import preset from './vendor/filament/support/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js,ts}',
        './resources/css/leaflet/MarkerCluster.css',
        './resources/css/leaflet/MarkerCluster.Default.css',

    ],
    theme: {
        extend: {
            colors: {
                'gray-text': '#4a4a4a',
                'dark-text': '#363636',
                'olm-green': '#03aa6f',
            },
        },
    },
    plugins: [
        function ({ addBase }) {
            addBase({
                'html, body': {
                    margin: '0',
                    padding: '0',
                    height: '100%',
                },
            });
        },
    ],
    safelist: [
        'marker-cluster-small',
        'marker-cluster-medium',
        'marker-cluster-large',
        'mi',
    ],
}
