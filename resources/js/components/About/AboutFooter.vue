<template>
    <footer class="bg-gradient-to-b from-gray-900 to-black text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <!-- Main CTA Section -->
            <div class="text-center mb-12">
                <h2 class="text-3xl sm:text-4xl font-bold mb-4">
                    {{ $t('Tell your story about plastic pollution.') }}
                </h2>
                <p class="text-xl text-gray-300 max-w-3xl mx-auto">
                    {{ $t('Join the global community mapping litter & plastic pollution.') }}
                </p>
            </div>

            <!-- App Download Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center mb-16">
                <a
                    href="https://apps.apple.com/ie/app/openlittermap/id1475982147"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-3 bg-white text-black px-6 py-3 rounded-lg font-medium hover:bg-gray-100 transition-colors"
                >
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M19.665 17.268c-.353.817-.771 1.563-1.255 2.238-.66.932-1.2 1.571-1.622 1.916-.647.546-1.341.83-2.082.854-.533 0-1.176-.154-1.929-.462-.753-.308-1.446-.462-2.078-.462-.653 0-1.356.154-2.107.462-.751.308-1.364.47-1.84.488-.714.034-1.42-.24-2.118-.824-.45-.374-.999-1.034-1.646-1.98-.705-1.008-1.28-2.172-1.724-3.493-.48-1.426-.72-2.804-.72-4.133 0-1.532.333-2.858 1-3.978.525-.894 1.22-1.602 2.085-2.123.866-.52 1.802-.787 2.81-.8.553 0 1.277.178 2.17.533.894.355 1.465.533 1.714.533.194 0 .783-.212 1.768-.637.948-.402 1.749-.569 2.403-.501 1.776.144 3.115.845 4.018 2.103-1.59.965-2.38 2.315-2.371 4.049.009 1.35.5 2.474 1.474 3.372.437.416.94.738 1.51.963-.121.346-.252.676-.392.989ZM15.86 2.46c0 1.15-.422 2.223-1.266 3.218-.99 1.168-2.184 1.843-3.483 1.736-.016-.136-.025-.275-.025-.418 0-1.104.482-2.286 1.319-3.205.42-.467.953-.855 1.599-1.164.646-.31 1.258-.488 1.836-.534.015.122.02.246.02.367Z"
                        />
                    </svg>
                    {{ $t('Download on App Store') }}
                </a>

                <a
                    href="https://play.google.com/store/apps/details?id=com.geotech.openlittermap"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-3 bg-white text-black px-6 py-3 rounded-lg font-medium hover:bg-gray-100 transition-colors"
                >
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M3.6 2.2c-.39.22-.6.66-.6 1.27v17.06c0 .61.21 1.05.6 1.27l9.61-9.8L3.6 2.2Zm10.7 8.68L5.4 1.6l.03-.02c.51-.28 1.17-.21 1.86.21l10.17 5.88c.73.42 1.09.99 1.09 1.63s-.36 1.21-1.09 1.63l-3.06 1.79ZM5.4 22.38l8.9-9.28 3.12 1.82c.73.42 1.09.99 1.09 1.63s-.36 1.21-1.09 1.63L7.24 24.06c-.69.41-1.35.49-1.86.21l-.03-.02Z"
                        />
                    </svg>
                    {{ $t('Get it on Google Play') }}
                </a>

                <router-link
                    to="/signup"
                    class="inline-flex items-center justify-center gap-3 bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors"
                >
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    {{ $t('Sign up on web') }}
                </router-link>
            </div>

            <!-- Footer Links Grid -->
            <div class="grid md:grid-cols-4 gap-8 py-8 border-t border-gray-800">
                <!-- About -->
                <div>
                    <h3 class="font-semibold mb-4">{{ $t('About') }}</h3>
                    <p class="text-sm text-gray-400">
                        {{
                            $t(
                                "We are building the world's most advanced open database on litter, brands, and plastic pollution."
                            )
                        }}
                    </p>
                </div>

                <!-- Explore -->
                <div>
                    <h3 class="font-semibold mb-4">{{ $t('Explore') }}</h3>
                    <ul class="space-y-2 text-sm">
                        <li>
                            <router-link to="/global" class="text-gray-400 hover:text-white transition-colors">
                                {{ $t('Global Map') }}
                            </router-link>
                        </li>
                        <li>
                            <a
                                href="https://github.com/openlittermap"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-gray-400 hover:text-white transition-colors"
                            >
                                {{ $t('GitHub') }}
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Community -->
                <div>
                    <h3 class="font-semibold mb-4">{{ $t('Community') }}</h3>
                    <div class="flex gap-3">
                        <a
                            href="#"
                            aria-label="Slack"
                            class="p-2 rounded-lg bg-gray-800 hover:bg-gray-700 transition-colors"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zM6.313 15.165a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313zM8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zM8.834 6.313a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312zM18.956 8.834a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zM17.688 8.834a2.528 2.528 0 0 1-2.523 2.521 2.527 2.527 0 0 1-2.52-2.521V2.522A2.527 2.527 0 0 1 15.165 0a2.528 2.528 0 0 1 2.523 2.522v6.312zM15.165 18.956a2.528 2.528 0 0 1 2.523 2.522A2.528 2.528 0 0 1 15.165 24a2.527 2.527 0 0 1-2.52-2.522v-2.522h2.52zM15.165 17.688a2.527 2.527 0 0 1-2.52-2.523 2.526 2.526 0 0 1 2.52-2.52h6.313A2.527 2.527 0 0 1 24 15.165a2.528 2.528 0 0 1-2.522 2.523h-6.313z"
                                />
                            </svg>
                        </a>
                        <a
                            href="#"
                            aria-label="Discord"
                            class="p-2 rounded-lg bg-gray-800 hover:bg-gray-700 transition-colors"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.892a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z"
                                />
                            </svg>
                        </a>
                        <a
                            href="#"
                            aria-label="WhatsApp"
                            class="p-2 rounded-lg bg-gray-800 hover:bg-gray-700 transition-colors"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967c-.273-.099-.471-.148-.67.15c-.197.297-.767.966-.94 1.164c-.173.199-.347.223-.644.075c-.297-.15-1.255-.463-2.39-1.475c-.883-.788-1.48-1.761-1.653-2.059c-.173-.297-.018-.458.13-.606c.134-.133.298-.347.446-.52c.149-.174.198-.298.298-.497c.099-.198.05-.371-.025-.52c-.075-.149-.669-1.612-.916-2.207c-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372c-.272.297-1.04 1.016-1.04 2.479c0 1.462 1.065 2.875 1.213 3.074c.149.198 2.096 3.2 5.077 4.487c.709.306 1.262.489 1.694.625c.712.227 1.36.195 1.871.118c.571-.085 1.758-.719 2.006-1.413c.248-.694.248-1.289.173-1.413c-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214l-3.741.982l.998-3.648l-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884c2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"
                                />
                            </svg>
                        </a>
                        <a
                            href="#"
                            aria-label="Telegram"
                            class="p-2 rounded-lg bg-gray-800 hover:bg-gray-700 transition-colors"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12a12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472c-.18 1.898-.962 6.502-1.36 8.627c-.168.9-.499 1.201-.82 1.23c-.696.065-1.225-.46-1.9-.902c-1.056-.693-1.653-1.124-2.678-1.8c-1.185-.78-.417-1.21.258-1.91c.177-.184 3.247-2.977 3.307-3.23c.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345c-.48.33-.913.49-1.302.48c-.428-.008-1.252-.241-1.865-.44c-.752-.245-1.349-.374-1.297-.789c.027-.216.325-.437.893-.663c3.498-1.524 5.83-2.529 6.998-3.014c3.332-1.386 4.025-1.627 4.476-1.635z"
                                />
                            </svg>
                        </a>
                    </div>
                    <p class="text-sm text-gray-400 mt-3">
                        <a href="mailto:info@openlittermap.com" class="hover:text-white transition-colors">
                            info@openlittermap.com
                        </a>
                    </p>
                </div>

                <!-- Creator -->
                <div>
                    <h3 class="font-semibold mb-4">{{ $t('Created by') }}</h3>
                    <p class="text-sm text-gray-400">People who like the environment</p>
                </div>
            </div>

            <!-- Copyright -->
            <div class="text-center pt-8 border-t border-gray-800">
                <p class="text-sm text-gray-400">
                    {{ $t('© {year} OpenLitterMap. All rights reserved.', { year: new Date().getFullYear() }) }}
                </p>
            </div>
        </div>
    </footer>
</template>

<script>
export default {
    name: 'AboutFooter',
};
</script>
