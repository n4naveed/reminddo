<script setup>
import { ref } from 'vue';

const faqItems = [
    {
        question: "How much does Tiimo cost?",
        answer: "Tiimo offers a free version with access to essential features like planning tools, to-do lists, and the focus timer. We also offer a 7-day free trial of Tiimo Pro, which unlocks premium tools like AI task breakdown, custom widgets, and advanced scheduling options."
    },
    {
        question: "What makes Tiimo different?",
        answer: "Tiimo was built by and for neurodivergent people from the start, not as a trend, but as a mission. Our tools are rooted in executive functioning research, designed to reduce friction and make planning actually stick. No clutter, no pressure, just science-backed support that adapts to how your brain works."
    },
    {
        question: "Does Tiimo protect my data and privacy?",
        answer: "Yes. Tiimo is 100% ad-free and never sells your data. We only collect what’s needed to run the app smoothly and securely. Your information is protected under GDPR."
    },
    {
        question: "Is Tiimo right for me?",
        answer: "If you’ve ever felt overwhelmed by planning apps or struggled with focus, follow-through, or building routines, Tiimo is made for you. Whether you’re managing work, school, or daily life, our tools are flexible, visual, and designed to help you plan in a way that actually works."
    }
];

const openIndex = ref(null);

const toggleFaq = (index) => {
    openIndex.value = openIndex.value === index ? null : index;
};
</script>

<template>
    <div class="space-y-4">
        <div 
            v-for="(item, index) in faqItems" 
            :key="index" 
            class="bg-white border-2 border-black rounded-xl overflow-hidden shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] transition-all"
        >
            <button 
                @click="toggleFaq(index)" 
                class="w-full text-left px-6 py-4 flex justify-between items-center focus:outline-none bg-[#FDFBF7]"
            >
                <span class="text-xl font-bold font-serif">{{ item.question }}</span>
                <span class="transform transition-transform duration-300" :class="{ 'rotate-180': openIndex === index }">
                    <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </span>
            </button>
            
            <div 
                class="px-6 transition-all duration-300 ease-in-out bg-white border-t border-dashed border-gray-300"
                :class="{ 
                    'max-h-0 py-0 opacity-0': openIndex !== index, 
                    'max-h-96 py-6 opacity-100': openIndex === index 
                }"
            >
                <p class="text-gray-700 leading-relaxed font-medium">{{ item.answer }}</p>
            </div>
        </div>
    </div>
</template>
