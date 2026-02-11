<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    show: Boolean,
    task: Object,
});

const emit = defineEmits(['close', 'complete']);

const timeLeft = ref(0); // in seconds
const isRunning = ref(false);
const totalDuration = ref(0); // in seconds
let timerInterval = null;

// Initialize timer when task changes or modal opens
watch(() => props.task, (newTask) => {
    if (newTask) {
        const start = new Date(newTask.start_time);
        const end = new Date(newTask.end_time);
        const durationMs = end.getTime() - start.getTime();
        totalDuration.value = Math.floor(durationMs / 1000);
        timeLeft.value = totalDuration.value;
        isRunning.value = false;
        clearInterval(timerInterval);
    }
}, { immediate: true });

const formattedTime = computed(() => {
    const minutes = Math.floor(timeLeft.value / 60);
    const seconds = timeLeft.value % 60;
    return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
});

// SVG Circular Progress Calculation
const radius = 120;
const circumference = 2 * Math.PI * radius;
const dashOffset = computed(() => {
    const progress = timeLeft.value / totalDuration.value;
    return circumference * (1 - progress);
});

const toggleTimer = () => {
    if (isRunning.value) {
        clearInterval(timerInterval);
        isRunning.value = false;
    } else {
        isRunning.value = true;
        timerInterval = setInterval(() => {
            if (timeLeft.value > 0) {
                timeLeft.value--;
            } else {
                completeTask();
            }
        }, 1000);
    }
};

const completeTask = () => {
    clearInterval(timerInterval);
    isRunning.value = false;
    emit('complete');
};

const addMinute = () => {
    timeLeft.value += 60;
    totalDuration.value += 60; // Extend total too so progress bar doesn't jump backwards weirdly
};

const close = () => {
    clearInterval(timerInterval);
    isRunning.value = false;
    emit('close');
};

onUnmounted(() => {
    clearInterval(timerInterval);
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[100] flex flex-col bg-[#F9F6F3] transition-opacity overflow-hidden">
        <!-- Close Button -->
        <button @click="close" class="absolute top-6 right-6 text-gray-400 hover:text-black transition z-10">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div class="flex-1 flex flex-col items-center justify-start pt-20 w-full max-w-md mx-auto px-8 text-center overflow-y-auto custom-scrollbar">
            
            <!-- Task Info -->
            <div class="mb-8 w-full">
                <h2 class="text-3xl font-serif font-bold text-gray-900 mb-2 leading-tight">{{ task?.title || 'Unknown Task' }}</h2>
                <p class="text-gray-500 font-mono text-sm uppercase tracking-wide">
                    {{ new Date(task?.start_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }} &rarr; {{ new Date(task?.end_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}
                </p>
            </div>

            <!-- Circular Timer -->
            <div class="relative w-72 h-72 mb-8 flex items-center justify-center flex-shrink-0">
                <!-- Background Circle -->
                <svg class="w-full h-full transform -rotate-90">
                    <circle
                        cx="144"
                        cy="144"
                        :r="130"
                        stroke="#E5E7EB"
                        stroke-width="24"
                        fill="#A78BFA" 
                        class="opacity-20"
                    />
                    <!-- Progress Circle -->
                     <!-- fill is the inner color -->
                    <circle
                        cx="144"
                        cy="144"
                        :r="130"
                        stroke="currentColor"
                        stroke-width="24"
                        fill="#A78BFA"
                        :stroke-dasharray="2 * Math.PI * 130"
                        :stroke-dashoffset="2 * Math.PI * 130 * (1 - (timeLeft / totalDuration))"
                        stroke-linecap="round"
                        class="text-indigo-400 transition-all duration-1000 ease-linear"
                        :style="{ color: task?.color || '#A78BFA' }"
                    />
                </svg>
                
                <!-- Inner Icon/State -->
                 <div class="absolute inset-0 flex flex-col items-center justify-center p-12">
                     <div class="w-full h-full rounded-full flex items-center justify-center">
                         <span class="text-6xl">{{ task?.icon || '✨' }}</span>
                     </div>
                 </div>
            </div>
            
            <!-- Timer Text -->
             <div class="mb-4">
                 <span class="text-6xl font-serif text-gray-900 tracking-tight">
                    {{ formattedTime }}
                </span>
             </div>

            <!-- Controls -->
            <div class="flex items-center gap-4 mb-10">
                 <span class="text-sm font-bold text-gray-900">+1 min</span>
                 <button 
                    @click="addMinute"
                    class="w-10 h-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-600 hover:bg-gray-50 active:scale-95 transition"
                >
                    +
                </button>

                 <button 
                    @click="toggleTimer"
                    class="w-16 h-16 rounded-full bg-black text-white flex items-center justify-center hover:opacity-80 active:scale-95 transition"
                >
                    <svg v-if="!isRunning" class="w-6 h-6 ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    <svg v-else class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                </button>
            </div>

            <!-- Checklist Items -->
            <div v-if="task?.checklist_items?.length" class="w-full text-left mb-10">
                <div v-for="item in task.checklist_items" :key="item.id" class="flex items-center group mb-3 last:mb-0 bg-white p-3 rounded-xl border border-gray-100 shadow-sm">
                    <button 
                         @click="router.patch(route('checklist-items.toggle', item.id), { is_completed: !item.is_completed }, { preserveScroll: true, preserveState: true })"
                         class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition flex-shrink-0 mr-3"
                         :class="item.is_completed ? 'bg-black border-black' : 'border-gray-300 hover:border-black'"
                    >
                        <svg v-if="item.is_completed" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </button>
                    <span class="text-gray-900 font-medium transition" :class="{ 'line-through opacity-50': item.is_completed }">
                        {{ item.title }}
                    </span>
                </div>
            </div>

        </div>
    </div>
</template>
