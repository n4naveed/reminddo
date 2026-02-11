<script setup>
defineProps({
    title: String,
    description: String,
    bgColor: {
        type: String,
        default: 'bg-white'
    },
    textColor: String,
    link: String
});
</script>

<template>
    <div 
        :class="[
            bgColor, 
            'relative overflow-hidden rounded-2xl border-2 border-black p-8 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-all hover:-translate-y-1 hover:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] group'
        ]"
    >
        <div class="relative z-10 h-full flex flex-col justify-between">
            <div>
                <div class="mb-6 w-12 h-12 rounded-full border-2 border-black bg-white flex items-center justify-center text-xl">
                    <slot name="icon">✨</slot>
                </div>
                
                <h3 class="text-2xl font-bold text-gray-900 mb-3 font-serif">
                    {{ title }}
                </h3>
                
                <p class="text-gray-700 font-medium leading-relaxed mb-6">
                    {{ description }}
                </p>
            </div>
            
            <a v-if="link" :href="link" class="inline-flex items-center font-bold text-black border-b-2 border-black hover:opacity-70 transition w-max group-hover:border-transparent">
                Learn more
                <svg class="w-4 h-4 ml-2 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
            </a>
        </div>

        <!-- Decorative Circle -->
        <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-black/5 rounded-full z-0 group-hover:scale-110 transition-transform duration-500"></div>
    </div>
</template>
