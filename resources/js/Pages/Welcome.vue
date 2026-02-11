<script setup>
import { Head } from '@inertiajs/vue3';
import NavBar from '@/Components/Landing/NavBar.vue';
import HeroSection from '@/Components/Landing/HeroSection.vue';
import FeatureCard from '@/Components/Landing/FeatureCard.vue';
import FaqAccordion from '@/Components/Landing/FaqAccordion.vue';
import AppFooter from '@/Components/Landing/AppFooter.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});
</script>

<template>
    <Head title="Visual Planner for ADHD & Executive Functioning" />
    
    <div class="font-sans antialiased text-gray-900 bg-white">
        <NavBar :canLogin="canLogin" :canRegister="canRegister" />
        
        <main>
            <!-- Hero Section -->
            <HeroSection />
            
            <!-- Features Grid -->
            <section class="py-24 bg-white relative">
                 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center mb-16">
                        <h2 class="text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl font-serif">
                            Executive functioning's <br class="hidden sm:block" />
                            <span class="text-[#FF6F61]">favorite toolkit</span>
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                        <FeatureCard 
                            title="Stay focused, not frozen"
                            description="Watch time pass with a countdown that keeps you anchored. Whether you need help starting, switching tasks, or staying in the zone."
                            bg-color="bg-[#E2F0CB]"
                            link="#"
                        >
                            <template #icon>
                                <svg class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </template>
                        </FeatureCard>

                        <FeatureCard 
                            title="Structure you can see"
                            description="Turn your schedule into a visual timeline you can actually follow. Organize your tasks, see what is coming up, and stay on track."
                            bg-color="bg-[#FFDAC1]"
                            link="#"
                        >
                            <template #icon>
                                <svg class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                            </template>
                        </FeatureCard>
                        
                        <FeatureCard 
                            title="Make it yours"
                            description="Choose colors, icons, and layouts that reflect how you work best. Personalizing your planner builds motivation."
                            bg-color="bg-[#B5EAD7]"
                            link="#"
                        >
                            <template #icon>
                                <svg class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" /></svg>
                            </template>
                        </FeatureCard>

                        <FeatureCard 
                            title="One planner, synced"
                            description="Plan on your computer and pick up right where you left off on mobile/watch. Everything stays connected."
                            bg-color="bg-[#C7CEEA]"
                            link="#"
                        >
                            <template #icon>
                                <svg class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                            </template>
                        </FeatureCard>
                    </div>
                 </div>
            </section>

             <!-- FAQ Section -->
            <section class="py-24 bg-[#FFB7B2]/20 border-t-2 border-dashed border-black">
                <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    <h2 class="text-4xl font-extrabold text-center text-gray-900 mb-12 font-serif">
                        Frequently Asked Questions
                    </h2>
                    
                    <div class="grid grid-cols-1 gap-4">
                        <FaqAccordion />
                    </div>
                </div>
            </section>
        </main>
        
        <AppFooter />
    </div>
</template>
