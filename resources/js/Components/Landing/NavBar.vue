<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <nav class="bg-white/80 backdrop-blur-md sticky top-0 z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <Link :href="route('welcome')" class="flex items-center gap-2">
                        <!-- Placeholder Logo (Tiimo style circle) -->
                        <div class="w-8 h-8 rounded-full bg-[#fae8d4] border-2 border-black flex items-center justify-center">
                            <div class="w-3 h-3 bg-black rounded-full"></div>
                        </div>
                        <span class="font-bold text-xl tracking-tight">tiimo</span>
                    </Link>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#" class="text-gray-600 hover:text-black font-medium transition">Product</a>
                    <a href="#" class="text-gray-600 hover:text-black font-medium transition">Resources</a>
                    <a href="#" class="text-gray-600 hover:text-black font-medium transition">About</a>
                    
                    <div v-if="canLogin" class="flex items-center gap-4 ml-4">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="px-4 py-2 bg-black text-white rounded-full font-bold hover:bg-gray-800 transition"
                        >
                            Dashboard
                        </Link>
                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="text-gray-900 font-bold hover:text-gray-600"
                            >
                                Log in
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="px-5 py-2.5 bg-[#B5EAD7] text-black border-2 border-black rounded-full font-bold hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-all transform hover:-translate-y-0.5"
                            >
                                Get Tiimo
                            </Link>
                        </template>
                    </div>
                </div>

                <!-- Mobile Menu Button (Placeholder) -->
                <div class="md:hidden flex items-center">
                    <button class="text-gray-600 hover:text-black focus:outline-none">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </nav>
</template>
