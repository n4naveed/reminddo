<script setup>
import { ref, computed, onMounted, onUnmounted, reactive, watch, nextTick } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

// Planning Wizard
const showPlanModal = ref(false);
const selectedPlanTaskIds = ref([]);
const isBuildingPlan = ref(false);
const calendarEvents = ref([]);

const fetchCalendarEvents = () => {
    axios.get('/calendar/events')
        .then(response => {
            calendarEvents.value = response.data.events || [];
        })
        .catch(err => {
            console.error('Failed to fetch calendar events', err);
        });
};

onMounted(() => {
    fetchCalendarEvents();
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

const openPlanModal = () => {
    selectedPlanTaskIds.value = inboxTasks.value.map(t => t.id); // Select all by default
    showPlanModal.value = true;
};

const buildPlan = () => {
    isBuildingPlan.value = true;
    
    // Auto-schedule logic
    // Start from next 30 min block
    const now = new Date();
    let startTime = new Date(now);
    startTime.setMinutes(Math.ceil(startTime.getMinutes() / 30) * 30);
    startTime.setSeconds(0);
    
    // If next block is in past (because we rounded up minutes but maybe seconds push it?), safety check.
    if (startTime < now) {
        startTime = new Date(now.getTime() + 30 * 60000); // Just add 30 mins
        startTime.setMinutes(Math.floor(startTime.getMinutes() / 30) * 30);
        startTime.setSeconds(0); 
    }

    const tasksToSchedule = props.tasks.filter(t => selectedPlanTaskIds.value.includes(t.id));

    
    // Prepare payload for AI
    const aiPayload = tasksToSchedule.map(t => ({ id: t.id, title: t.title }));

    axios.post('/ai-plan', { tasks: aiPayload })
        .then(response => {
            const plan = response.data.plan;
            
            // Plan is array of objects. We need to save them.
            // Using existing bulk-schedule endpoint
            router.post('/tasks/bulk-schedule', { tasks: plan }, {
                onSuccess: () => {
                    isBuildingPlan.value = false;
                    showPlanModal.value = false;
                    activeTab.value = 'today'; // Switch to timeline
                },
                onError: () => {
                    isBuildingPlan.value = false;
                    alert('Failed to save the AI schedule.');
                }
            });
        })
        .catch(error => {
            isBuildingPlan.value = false;
            console.error(error);
            alert('AI Planning failed: ' + (error.response?.data?.error || error.message));
    });
};

const props = defineProps({
    tasks: {
        type: Array,
        default: () => [],
    },

});



const isModalOpen = ref(false);
const form = useForm({
    title: '',
    start_time: '',
    end_time: '',
    color: '#E2F0CB',
    icon: '✨',
    recurrence_pattern: 'none',
    checklist_items: [],
    notes: '',
    all_day: false,
    time_of_day: 'at_time', 
    duration: 30, // in minutes
    start_date_display: new Date().toISOString().split('T')[0],
    start_time_display: '09:00',
    end_date_display: new Date().toISOString().split('T')[0],
    end_time_display: '09:30',
});



const repeatOptions = [
    { label: 'No repeat', value: 'none' },
    { label: 'Daily', value: 'daily' },
    { label: 'Every weekday', value: 'weekday' },
    { label: 'Weekends', value: 'weekend' },
    { label: 'Weekly', value: 'weekly' },
    { label: 'Every second week', value: 'biweekly' },
    { label: 'Monthly', value: 'monthly' },
    { label: 'Yearly', value: 'yearly' },
    { label: 'Custom', value: 'custom' },
];

const activeTab = ref('today'); // 'todo', 'today', 'focus', 'me'

// View State
// View State
const savedViewMode = localStorage.getItem('viewMode');
const viewMode = ref(savedViewMode && ['day', '3-day', 'week', 'month', 'agenda'].includes(savedViewMode) ? savedViewMode : '3-day'); 
const showViewModeDropdown = ref(false);

watch(viewMode, (newMode) => {
    localStorage.setItem('viewMode', newMode);
    showViewModeDropdown.value = false; // Close dropdown on selection
});
const currentDate = ref(new Date());
const showDatePicker = ref(false);

// Date Navigation
// Date Navigation
const goToToday = () => currentDate.value = new Date();
const prevDate = () => {
    const d = new Date(currentDate.value);
    if (viewMode.value === 'month') {
        d.setMonth(d.getMonth() - 1);
    } else if (viewMode.value === 'week') {
        d.setDate(d.getDate() - 7);
    } else {
        d.setDate(d.getDate() - (viewMode.value === '3-day' ? 3 : 1));
    }
    currentDate.value = d;
};
const nextDate = () => {
    const d = new Date(currentDate.value);
    if (viewMode.value === 'month') {
        d.setMonth(d.getMonth() + 1);
    } else if (viewMode.value === 'week') {
        d.setDate(d.getDate() + 7);
    } else {
        d.setDate(d.getDate() + (viewMode.value === '3-day' ? 3 : 1));
    }
    currentDate.value = d;
};

const getDateKey = (dateObj) => {
    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const d = String(dateObj.getDate()).padStart(2, '0');
    return `${year}-${month}-${d}`;
};

// Days to Display
const daysToDisplay = computed(() => {
    const days = [];
    const mode = viewMode.value;
    
    if (mode === 'month') {
        const year = currentDate.value.getFullYear();
        const month = currentDate.value.getMonth();
        // Month Grid Logic: Start from first day of week of the 1st of month
        const firstDayOfMonth = new Date(year, month, 1);
        const startDayOfWeek = firstDayOfMonth.getDay(); // 0 is Sunday. Let's assume Monday start? System usually uses Sunday (0).
        // If we want Monday start, adjust. Let's stick to Sunday start for standard or adjust to Monday (1) if needed per screenshot. 
        // Screenshot 3 shows Mon 26 (Jan/Feb edge). Feb 2026 starts on Sunday? 
        // 1 Feb 2026 is Sunday. Screenshot shows Mon 26? That's Jan 26.
        // It seems the calendar starts on Monday based on columns (Mon, Tue, Wed...)
        
        // Let's align to Monday start.
        const dayOffset = (startDayOfWeek + 6) % 7; // Convert Sun(0)->6, Mon(1)->0
        
        const startGrid = new Date(year, month, 1);
        startGrid.setDate(startGrid.getDate() - dayOffset);
        
        // 5 or 6 weeks (35 or 42 days)
        for (let i = 0; i < 35; i++) { // 35 days (5 weeks) is usually enough, sometimes 42
             const d = new Date(startGrid);
             d.setDate(d.getDate() + i);
             days.push(d);
        }
        
    } else {
        const start = new Date(currentDate.value);
        let count = 1;
        if (mode === '3-day') count = 3;
        if (mode === 'week') {
            count = 7;
            // Align to start of week (Monday)
            const day = start.getDay();
            const diff = (day === 0 ? -6 : 1) - day; // Monday start
            start.setDate(start.getDate() + diff);
        }
        if (mode === 'agenda') count = 14; // Show 2 weeks for agenda

        for (let i = 0; i < count; i++) {
            const d = new Date(start);
            d.setDate(d.getDate() + i);
            days.push(d);
        }
    }
    return days;
});

// Helper: Get Time Period if not set
const getTimePeriod = (task) => {
    if (task.time_of_day && task.time_of_day !== 'at_time' && task.time_of_day !== 'all_day') {
        return task.time_of_day; // morning, day, evening, anytime
    }
    
    // Derived from time
    if (!task.start_time) return 'anytime';
    const hour = new Date(task.start_time).getUTCHours();
    
    if (hour >= 5 && hour < 12) return 'morning';
    if (hour >= 12 && hour < 17) return 'day';
    if (hour >= 17 || hour < 5) return 'evening';
    return 'anytime';
};

const FormatTimeSimple = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'UTC' });
};
// Helper: Format Duration (e.g. "30min")
const formatDuration = (task) => {
    if (!task.start_time || !task.end_time) {
        // Fallback if no start/end
        if (task.duration) return `${task.duration}min`;
        return '30min';
    }
    const start = new Date(task.start_time);
    const end = new Date(task.end_time);
    const diffMs = end - start;
    const minutes = Math.floor(diffMs / 60000);
    
    // If minutes is 0 or negative, default to 30
    if (minutes <= 0) return '30min';
    
    return `${minutes}min`;
};
// Grouped Tasks
const groupedTasks = computed(() => {
    const groups = {};
    
    // Init groups for each displayed day
    daysToDisplay.value.forEach(d => {
        // Use local date components (Wall Clock alignment)
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const key = `${y}-${m}-${day}`;
        
        groups[key] = {
            anytime: [],
            morning: [],
            day: [],
            evening: []
        };
    });

    props.tasks.forEach(task => {
        // Determine Task Date
        let dateKey;
        if (task.start_time) {
            // Use local date for grouping, not UTC
            const localDate = new Date(task.start_time);
            dateKey = localDate.getFullYear() + '-' + 
                      String(localDate.getMonth() + 1).padStart(2, '0') + '-' + 
                      String(localDate.getDate()).padStart(2, '0');
        } else if (task.start_date) {
            dateKey = task.start_date;
        } else {
            // If no start time (Inbox), maybe show in "Today" or "Anytime"?
            // For now, let's put them in Today's Anytime or ignore if not scheduled.
            // Requirement says "Anytime", implies scheduled for a day but no time.
            // If strictly local (inbox), skip. User implies "My Day".
            // Let's fallback to current date for loose tasks if logic dictates.
            // Let's fallback to current date for loose tasks if logic dictates.
            return; 
        }

        if (task.is_completed) return; // Skip completed tasks in main view

        if (groups[dateKey]) {
            const period = getTimePeriod(task);
            if (groups[dateKey][period]) {
                groups[dateKey][period].push(task);
            } else {
                // Fallback for at_time/all_day -> convert to period
                // Logic above handles it, but verify
                groups[dateKey]['anytime'].push(task);
            }
        }
    });

    // Sort tasks in each group by start_time
    Object.keys(groups).forEach(date => {
        ['anytime', 'morning', 'day', 'evening'].forEach(period => {
            groups[date][period].sort((a, b) => {
                if (!a.start_time) return -1;
                if (!b.start_time) return 1;
                return new Date(a.start_time) - new Date(b.start_time);
            });
        });
    });

    return groups;
});

const quickAddTask = (dateObj, period) => {
    // Reset form
    form.reset();
    form.errors = {};
    
    // Set Date
    const dateStr = dateObj.toISOString().split('T')[0];
    form.start_date_display = dateStr;
    form.end_date_display = dateStr;

    // Set Time based on Period (as defaults for background or switching modes)
    let startTime = '09:00';
    let endTime = '09:30';

    if (period === 'morning') {
        startTime = '09:00';
        endTime = '09:30';
    } else if (period === 'day') {
        startTime = '13:00';
        endTime = '13:30';
    } else if (period === 'evening') {
        startTime = '18:00';
        endTime = '18:30';
    } else if (period === 'anytime') {
        startTime = '00:00'; 
        endTime = '00:30';
    }

    form.start_time_display = startTime;
    form.end_time_display = endTime;
    form.duration = 30; // Default duration
    
    // Crucial: Set the dropdown mode to match the period
    form.time_of_day = period;
    
    // Title
    form.title = "";

    isModalOpen.value = true;
};

const scheduledTasks = computed(() => props.tasks.filter(t => t.start_time));
const inboxTasks = computed(() => props.tasks.filter(t => !t.start_time && !t.start_date));

const inboxTasksHigh = computed(() => props.tasks.filter(t => !t.start_time && !t.start_date && !t.is_completed && t.priority === 'high'));
const inboxTasksMedium = computed(() => props.tasks.filter(t => !t.start_time && !t.start_date && !t.is_completed && t.priority === 'medium'));
const inboxTasksLow = computed(() => props.tasks.filter(t => !t.start_time && !t.start_date && !t.is_completed && t.priority === 'low'));
const inboxTasksTodo = computed(() => props.tasks.filter(t => !t.start_time && !t.start_date && !t.is_completed && (!t.priority || t.priority === 'none')));
const inboxTasksDone = computed(() => props.tasks.filter(t => t.is_completed));

const sidebarSections = reactive({
    high: true,
    medium: true,
    low: true,
    todo: true,
    done: true
});

const onDropPriority = (event, priority) => {
    const taskId = event.dataTransfer.getData('taskId');
    const task = props.tasks.find(t => t.id == taskId);
    if (!task) return;

    if (task.priority !== priority || task.start_time) {
        task.priority = priority;
        task.start_time = null;
        task.end_time = null;
        
         // Optimistic update
         router.put(`/tasks/${task.id}`, {
             ...task,
             priority: priority,
             start_time: null,
             end_time: null
         }, { preserveScroll: true });
    }
};

// Inline Editing Logic
const inlineEditingTaskId = ref(null);
const inlineTitle = ref('');

const startInlineEdit = (task) => {
    inlineEditingTaskId.value = task.id;
    inlineTitle.value = task.title;
    nextTick(() => {
        const input = document.getElementById(`inline-input-${task.id}`);
        if (input) input.focus();
    });
};

const saveInlineEdit = (task) => {
    if (!inlineEditingTaskId.value) return;
    
    // Only save if changed
    if (inlineTitle.value !== task.title) {
        // Optimistic Update
        const oldTitle = task.title;
        task.title = inlineTitle.value;

        router.put(`/tasks/${task.id}`, {
            title: inlineTitle.value
        }, { 
            preserveScroll: true,
            onError: () => {
                // Revert on error
                task.title = oldTitle;
                alert('Failed to save title');
            }
        });
    }
    
    inlineEditingTaskId.value = null;
};

const cancelInlineEdit = () => {
    inlineEditingTaskId.value = null;
};

const onDropPlanner = (event, dateObj, period) => {
    const taskId = event.dataTransfer.getData('taskId');
    const task = props.tasks.find(t => t.id == taskId);
    if (!task) return;

    // Calculate new start/end times
    // Calculate new start/end times
    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const d = String(dateObj.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${d}`;
    let timeStr = '09:00'; // Default Morning
    let newTimeOfDay = 'at_time'; // Default

    if (period === 'morning') {
        timeStr = '09:00';
        newTimeOfDay = 'morning';
    } else if (period === 'day') {
        // "Day & Evening" in UI is usually Day (13:00) or Evening (18:00)
        // If users drop on "Day & Evening", I should probably default to Day or Check?
        // The user request says "Morning/Anty Time/Evening etc".
        // My 3-day view has "Morning", "Day & Evening", "Anytime".
        // I will map 'day' -> 13:00.
        // I should probably split Day/Evening drop zones or just pick one.
        timeStr = '13:00';
        newTimeOfDay = 'day';
    } else if (period === 'evening') {
        timeStr = '18:00';
        newTimeOfDay = 'evening';
    } else if (period === 'anytime') {
        timeStr = '00:00'; // Or just keep date?
        newTimeOfDay = 'anytime';
    }

    const newStartTime = `${dateStr}T${timeStr}Z`; // Force UTC
    
    // Calculate end time (preserve duration)
    let duration = 30;
    if (task.start_time && task.end_time) {
        duration = (new Date(task.end_time) - new Date(task.start_time)) / 60000;
    }
    const endDate = new Date(new Date(newStartTime).getTime() + duration * 60000);
    // Format end date back to ISO string basic (local)
    // Quick hack for formatting
    // Format end date back to ISO string basic (UTC)
    const newEndTime = endDate.getFullYear() + '-' + 
        String(endDate.getMonth() + 1).padStart(2, '0') + '-' + 
        String(endDate.getDate()).padStart(2, '0') + 'T' + 
        String(endDate.getUTCHours()).padStart(2, '0') + ':' + 
        String(endDate.getUTCMinutes()).padStart(2, '0') + 'Z';

    const newStartTimeISO = `${dateStr}T${timeStr}Z`; // Force UTC


    // Update Task
    task.start_time = newStartTime;
    task.end_time = newEndTime;
    task.time_of_day = newTimeOfDay;

    router.put(`/tasks/${task.id}`, {
        ...task,
        start_time: newStartTimeISO,
        end_time: newEndTime,
        time_of_day: newTimeOfDay
    }, { preserveScroll: true });
};

const createInboxTask = () => {
    form.title = "New To-Do";
    form.start_time = null;
    form.end_time = null;
    form.color = '#E2F0CB';
    form.icon = '✨';
    form.recurrence_pattern = 'none';
    form.checklist_items = [];
    form.notes = '';
    form.all_day = false;
    form.time_of_day = 'anytime';
    form.duration = 30;
    
    // Set default date for display to today
    const now = new Date();
    form.start_date_display = now.toISOString().split('T')[0];
    form.start_time_display = '09:00';
    form.end_date_display = now.toISOString().split('T')[0];
    form.end_time_display = '09:30';

    isModalOpen.value = true;
};

// HTML5 DnD for Inbox Items

const handleTimeSlotClick = (day, hour) => {
    form.reset();
    editingTask.value = null; // Ensure we are creating a new task
    
    // Set Date
    form.start_date_display = day;
    form.end_date_display = day;
    
    // Set Time
    const startTime = `${hour.toString().padStart(2, '0')}:00`;
    form.start_time_display = startTime;
    
    // Default duration 15 mins
    const endDate = new Date(day);
    endDate.setHours(hour);
    endDate.setMinutes(15);
    form.end_time_display = `${endDate.getHours().toString().padStart(2, '0')}:${endDate.getMinutes().toString().padStart(2, '0')}`;
    
    form.time_of_day = 'at_time'; // Switch to 'At time' mode
    
    isModalOpen.value = true;
};

const onInboxDragStart = (event, task) => {
    event.dataTransfer.dropEffect = 'move';
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('taskId', task.id);
    event.dataTransfer.setData('duration', 30); // Default 30 mins
};

// (Old onTimelineDrop removed - replaced by improved version below)

// Chat State
const isAiLoading = ref(false);
const aiPrompt = ref('');
const isChatOpen = ref(false);
const isHistoryOpen = ref(false);
const currentChatId = ref(null);
const chatHistory = ref([]); // [{ id: 1, title: 'Morning Routine', messages: [] }]
const chatMessages = ref([]); 
const suggestedTasks = ref(null);
const chatContainerRef = ref(null);
const chatBottomRef = ref(null);

const scrollToBottom = () => {
    nextTick(() => {
        setTimeout(() => {
            if (chatBottomRef.value) {
                chatBottomRef.value.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
        }, 100);
    });
};

// Load previous history from localStorage (Mock persistence)
onMounted(() => {
    const saved = localStorage.getItem('reminddo_chat_history');
    if (saved) {
        try {
            chatHistory.value = JSON.parse(saved);
        } catch (e) {
            console.error(e);
        }
    }
});

const saveHistory = () => {
    localStorage.setItem('reminddo_chat_history', JSON.stringify(chatHistory.value));
};

const startNewChat = () => {
    currentChatId.value = null;
    chatMessages.value = [];
    suggestedTasks.value = null;
    isHistoryOpen.value = false; // Auto close sidebar on mobile if needed, but let's keep it simple
};

const loadChat = (chat) => {
    currentChatId.value = chat.id;
    chatMessages.value = chat.messages;
    suggestedTasks.value = null; // Reset tasks state for viewed chat (or could save it too)
    // On mobile we might want to close sidebar
};

const submitAiPrompt = () => {
    if (!aiPrompt.value) return;
    
    // Open chat
    isChatOpen.value = true;
    
    // Create new session if none exists
    if (!currentChatId.value) {
        const newId = Date.now();
        currentChatId.value = newId;
        chatHistory.value.unshift({
            id: newId,
            title: aiPrompt.value, // Use first prompt as title
            messages: []
        });
    }

    const userMsg = {
        id: Date.now(),
        role: 'user',
        content: aiPrompt.value
    };

    chatMessages.value.push(userMsg);
    
    // Update history immediately
    const currentChat = chatHistory.value.find(c => c.id === currentChatId.value);
    if (currentChat) {
        currentChat.messages = chatMessages.value;
        saveHistory();
    }
    
    // Clear input
    aiPrompt.value = '';
    isAiLoading.value = true;
    
    axios.post('/ai-plan', { prompt: userMsg.content })
        .then(response => {
            isAiLoading.value = false;
            const data = response.data.plan;
            
            let message = "Here is a suggested schedule:";
            let tasks = [];

            if (Array.isArray(data)) {
                tasks = data;
            } else if (data && typeof data === 'object') {
                message = data.message || message;
                tasks = data.tasks || [];
            }

            const aiMsg = {
                id: Date.now() + 1,
                role: 'ai',
                content: message
            };

            chatMessages.value.push(aiMsg);
            
            if (currentChat) {
                currentChat.messages = chatMessages.value;
                saveHistory();
            }

            if (tasks.length > 0) {
                suggestedTasks.value = tasks;
                scrollToBottom();
            }
            scrollToBottom();
        })
        .catch(error => {
            isAiLoading.value = false;
            chatMessages.value.push({
                id: Date.now(),
                role: 'ai',
                content: "Error: " + (error.response?.data?.error || error.message)
            });
        });
};

const confirmSuggestedTasks = () => {
    if (!suggestedTasks.value) return;
    
    // Check if we need to infer dates for tasks that might be missing them (handled by backend now mostly, but good to be safe)
    // Actually, backend now handles calculation if start_time is sent.
    
    router.post('/tasks/bulk-store', { tasks: suggestedTasks.value }, {
        onSuccess: () => {
             chatMessages.value.push({
                id: Date.now(),
                role: 'ai',
                content: "Great! tasks have been added to your schedule."
            });
            suggestedTasks.value = null;
            activeTab.value = 'today';
        },
        onError: () => {
            alert('Failed to save tasks.');
        }
    });
};

const cancelSuggestedTasks = () => {
    suggestedTasks.value = null;
    chatMessages.value.push({
        id: Date.now(),
        role: 'ai',
        content: "Cancelled."
    });
};

// Mock function removed, we use real AI now.

// Editing state
const editingTask = ref(null);

const openEditModal = (task) => {
    editingTask.value = task;
    form.title = task.title;
    form.start_time = task.start_time; 
    
    // Parse start/end for display fields
    if (task.start_time) {
        const start = new Date(task.start_time);
        // Manual Format YYYY-MM-DD (Local)
        const y = start.getFullYear();
        const m = String(start.getMonth() + 1).padStart(2, '0');
        const d = String(start.getDate()).padStart(2, '0');
        form.start_date_display = `${y}-${m}-${d}`;
        
        // Manual Format HH:MM (UTC for Wall Clock)
        const hh = String(start.getUTCHours()).padStart(2, '0');
        const mm = String(start.getUTCMinutes()).padStart(2, '0');
        form.start_time_display = `${hh}:${mm}`;
        
        // Prioritize explicit time_of_day from DB
        if (task.time_of_day) {
            form.time_of_day = task.time_of_day;
        } else if (task.all_day) {
             form.time_of_day = 'all_day';
        } else {
            // Fallback for legacy tasks or manually set times
            form.time_of_day = 'at_time';
        }
    } else {
        // Respect existing time_of_day preference if it's a loose period, otherwise default to anytime
        if (task.time_of_day && ['morning', 'day', 'evening', 'anytime'].includes(task.time_of_day)) {
            form.time_of_day = task.time_of_day;
        } else {
            form.time_of_day = 'anytime';
        }
    }

    if (task.end_time) {
        const end = new Date(task.end_time);
        
        const y = end.getFullYear();
        const m = String(end.getMonth() + 1).padStart(2, '0');
        const d = String(end.getDate()).padStart(2, '0');
        form.end_date_display = `${y}-${m}-${d}`;

        const hh = String(end.getUTCHours()).padStart(2, '0');
        const mm = String(end.getUTCMinutes()).padStart(2, '0');
        form.end_time_display = `${hh}:${mm}`;
        
        // Calculate duration if start time exists
        if (task.start_time) {
            const start = new Date(task.start_time);
            const diffMs = end - start;
            form.duration = Math.floor(diffMs / 60000);
        }
    }

    form.end_time = task.end_time;
    form.color = task.color;
    form.icon = task.icon;
    form.recurrence_pattern = task.recurrence_pattern || 'none';
    form.notes = task.notes || '';
    form.checklist_items = task.checklist_items ? task.checklist_items.map(item => ({
        title: item.title,
        is_completed: Boolean(item.is_completed)
    })) : [];
    isModalOpen.value = true;
};

const submitTask = () => {
    // Construct ISO strings based on time_of_day
    
    if (form.time_of_day === 'at_time') {
        // Validation: Ensure date and time are present
        if (form.start_date_display && form.start_time_display) {
            form.start_time = `${form.start_date_display}T${form.start_time_display}Z`;
        }
        if (form.end_date_display && form.end_time_display) {
            form.end_time = `${form.end_date_display}T${form.end_time_display}Z`;
        }
    } 
    else if (form.time_of_day === 'all_day') {
         // All day = 00:00 to 23:59:59 (approx) or just date
         // Backend expects datetime usually
         if (form.start_date_display) {
             form.start_time = `${form.start_date_display}T00:00:00Z`;
         }
         if (form.end_date_display) {
             form.end_time = `${form.end_date_display}T23:59:59Z`;
         }
    }
    else {
        // Anytime / Morning / Day / Evening
        // We set the time based on the selected period
        if (form.start_date_display) {
             let looseTime = '09:00';
             if (form.time_of_day === 'morning') looseTime = '09:00';
             else if (form.time_of_day === 'day') looseTime = '13:00';
             else if (form.time_of_day === 'evening') looseTime = '18:00';
             else if (form.time_of_day === 'anytime') looseTime = '00:00';

             form.start_time = `${form.start_date_display}T${looseTime}Z`;
             
             // Calculate end time based on duration
             const startObj = new Date(form.start_time); // This is now UTC
             const duration = parseInt(form.duration) || 30;
             const endObj = new Date(startObj.getTime() + duration * 60000);
             
             const endHours = endObj.getUTCHours().toString().padStart(2, '0');
             const endMinutes = endObj.getUTCMinutes().toString().padStart(2, '0');
             form.end_time = `${form.start_date_display}T${endHours}:${endMinutes}Z`;
        }
    }

    if (editingTask.value) {
        form.put(`/tasks/${editingTask.value.id}`, {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
                editingTask.value = null;
                isIconPickerOpen.value = false;
            },
            onError: (errors) => {
                alert('Failed to update task: ' + JSON.stringify(errors));
            }
        });
    } else {
        form.post('/tasks', {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            },
            onError: (errors) => {
                console.error('Task creation failed:', errors);
                alert('Failed to create task: ' + JSON.stringify(errors));
            }
        });
    }
};

const moveTaskToInbox = () => {
    if (!editingTask.value) return;
    
    // Explicitly set null to move to backlog/inbox
    form.start_time = null;
    form.end_time = null;
    form.priority = null; 
    // Only reset time_of_day if it relies on specific timestamps (at_time/all_day)
    // If it's a loose period (Morning/Day/Evening), we preserve it as per user request
    if (form.time_of_day === 'at_time' || form.time_of_day === 'all_day') {
        form.time_of_day = 'anytime';
    }
    // else keep current form.time_of_day value
    
    // We update the task directly using the form helper to maintain state/progress
    form.put(`/tasks/${editingTask.value.id}`, {
         preserverScroll: true,
         onSuccess: () => {
             isModalOpen.value = false;
             form.reset();
             editingTask.value = null;
         },
         onError: (errors) => {
             alert('Failed to move task.');
             console.error(errors);
         }
    });
};

const formatTime = (isoString) => {
    // Treat the ISO string as the "source of truth" time, ignoring browser timezone shifts.
    // Since input type="datetime-local" sends a timeless string that backend stores/returns as UTC,
    // we must format it as UTC to match what the user entered.
    if (!isoString) return '';
    return new Date(isoString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', timeZone: 'UTC' });
};

import confetti from 'canvas-confetti';

const deleteTask = (taskId) => {
    if(confirm('Are you sure you want to delete this task?')) {
        router.delete(`/tasks/${taskId}`);
    }
}

const toggleTaskCompletion = (task) => {
    // Optimistic Update
    task.is_completed = !task.is_completed;
    
    // Confetti Effect
    if (task.is_completed) {
        confetti({
            particleCount: 100,
            spread: 70,
            origin: { y: 0.6 },
            colors: ['#A0D4FF', '#FFB7B2', '#B9FBC0', '#FFD1DC', '#E0BBE4']
        });
        
        // Play subtle sound? optional.
    }

    // Persist
    router.put(`/tasks/${task.id}`, {
        ...task,
        is_completed: task.is_completed
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
             // Maybe notification 'Task Completed!'
        }
    });
};

// Drag and Drop Logic
const isDragging = ref(false);
const draggedTask = ref(null);
const initialY = ref(0);
const currentY = ref(0);
const initialTop = ref(0);

const getEventStyle = (task) => {
    const start = new Date(task.start_time);
    const end = new Date(task.end_time);
    
    // Positioning based on LOCAL time, as grid is 0-23 Local
    const startHour = start.getHours() + (start.getMinutes() / 60);
    
    // Config
    const timelineStartHour = 0; // 00:00
    const pixelsPerHour = 100;
    
    const durationMs = end.getTime() - start.getTime();
    const durationHours = durationMs / (1000 * 60 * 60);

    let top = (startHour - timelineStartHour) * pixelsPerHour;
    
    // If dragging this specific task, apply temporary offset
    if (isDragging.value && draggedTask.value && draggedTask.value.id === task.id) {
        top += currentY.value;
    }

    const height = Math.max(durationHours * pixelsPerHour, 60);

    return {
        backgroundColor: task.color || '#E2F0CB',
        top: `${top}px`,
        height: `${height}px`,
        zIndex: isDragging.value && draggedTask.value?.id === task.id ? 100 : 10,
    };
};

const checkEventDates = (event) => {
    return {
        start_time: event.start,
        end_time: event.end
    };
};

const startDrag = (event, task) => {
    isDragging.value = true;
    draggedTask.value = task;
    initialY.value = event.clientY;
    // Calculate initial top from style or compute it
    const style = getEventStyle(task);
    initialTop.value = parseInt(style.top);
    
    document.addEventListener('mousemove', onDrag);
    document.addEventListener('mouseup', stopDrag);
};

const formatTimeFromTop = (top) => {
    if (top === null) return '';
    const startHour = 0;
    const pixelsPerHour = 100;
    const hours = (top / pixelsPerHour) + startHour;
    
    const h = Math.floor(hours);
    const m = Math.round((hours % 1) * 60);
    
    // Format HH:MM
    const date = new Date();
    date.setUTCHours(h);
    date.setUTCMinutes(m);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', timeZone: 'UTC' });
};

const onDrag = (event) => {
    if (!isDragging.value) return;
    const dy = event.clientY - initialY.value;
    currentY.value = dy;
};

const stopDrag = () => {
    if (!isDragging.value || !draggedTask.value) return;
    
    // Calculate new start time
    // Calculate new start time
    const pixelsPerHour = 100;
    const timelineStartHour = 0; // Starts at 00:00
    const offsetPixels = val => initialTop.value + val;
    
    // Snap to 15 mins (25px)
    let newTop = offsetPixels(currentY.value);
    const snapGrid = 25; 
    newTop = Math.round(newTop / snapGrid) * snapGrid;
    
    // Convert back to time
    const newStartHour = (newTop / pixelsPerHour) + timelineStartHour;
    
    const start = new Date(draggedTask.value.start_time);
    const end = new Date(draggedTask.value.end_time);
    const durationMs = end.getTime() - start.getTime();
    
    // Create new Date objects
    const newStartDate = new Date(start);
    newStartDate.setUTCHours(Math.floor(newStartHour));
    newStartDate.setUTCMinutes((newStartHour % 1) * 60);
    newStartDate.setSeconds(0);
    
    const newEndDate = new Date(newStartDate.getTime() + durationMs);
    
    // Update local task immediately for responsiveness (optional, but good UX)
    // Actually, Inertia will reload, but we can optimistically update if we want.
    // Let's just fire the request.
    
    router.put(`/tasks/${draggedTask.value.id}`, {
        start_time: newStartDate.toISOString(), // Use full ISO string
        end_time: newEndDate.toISOString(),
    }, {
        preserveScroll: true,
        preserveState: true,
    });

    isDragging.value = false;
    draggedTask.value = null;
    currentY.value = 0;
    
    document.removeEventListener('mousemove', onDrag);
    document.removeEventListener('mouseup', stopDrag);
};

// Ghost Guide Logic
const dragPreviewTop = ref(null);
const dragPreviewHeight = ref(60); // Default 1 hour equivalent or derived from task
const isDragOverTimeline = ref(false);

const onTimelineDragOver = (event) => {
    // Calculate snap position
    const rect = timelineContainer.value.getBoundingClientRect();
    const scrollTop = timelineContainer.value.scrollTop;
    const clientY = event.clientY;
    
    // Calculate relative Y
    const relativeY = clientY - rect.top + scrollTop;
    
    // Snap to 25px (15 mins)
    // 100px = 60 mins. 25px = 15 mins.
    const snapGrid = 25;
    const snappedTop = Math.floor(relativeY / snapGrid) * snapGrid;
    
    dragPreviewTop.value = snappedTop;
    isDragOverTimeline.value = true;
    
    // Check if dragging inbox item or existing task to set height
    if (isDragging.value && draggedTask.value) {
        // Use existing task duration
        const style = getEventStyle(draggedTask.value);
        dragPreviewHeight.value = parseInt(style.height);
    } else {
        // Inbox item default 30 mins (50px)
        dragPreviewHeight.value = 50; 
    }
};

const onTimelineDragLeave = () => {
    isDragOverTimeline.value = false;
    dragPreviewTop.value = null;
};

const onTimelineDrop = (event) => {
    isDragOverTimeline.value = false;
    dragPreviewTop.value = null;

    const taskId = event.dataTransfer.getData('taskId');
    if (!taskId) return; // Not an inbox drop?
    
    // Calculate drop time
    // We need offset relative to the timeline container
    const rect = timelineContainer.value.getBoundingClientRect();
    const relativeY = event.clientY - rect.top + timelineContainer.value.scrollTop;
    
    // 100px per hour
    const hour = Math.max(0, relativeY / 100);
    
    // Create Date objects
    // Assuming today for simplicity, or we should track "viewed date" in state if we had date picker.
    // For now, Reminddo clone implies "My Day" (Today).
    const now = new Date();
    const start = new Date(now);
    start.setUTCHours(Math.floor(hour));
    start.setUTCMinutes(Math.floor((hour % 1) * 60));
    start.setSeconds(0);
    
    const end = new Date(start.getTime() + 30 * 60000); // 30 mins default
    
    router.put(`/tasks/${taskId}`, {
        start_time: start.toISOString(), 
        end_time: end.toISOString(),
    }, { preserveScroll: true });
};

const handleInboxDragStart = (event, task) => {
    console.log('Drag Start:', task.id);
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', JSON.stringify(task.id));
    event.dataTransfer.setData('taskId', task.id);
    isDragging.value = true;
    draggedTask.value = task;
};

const handleDragOver = (event) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
};

const handleDragEnd = () => {
    console.log('Drag End');
    isDragging.value = false;
    draggedTask.value = null;
};

const onDropTimeSlot = (event, day, hour) => {
    let taskId = event.dataTransfer.getData('text/plain');
    if (!taskId) taskId = event.dataTransfer.getData('taskId');
    
    console.log('Drop Time Slot:', taskId, day, hour);
    if (!taskId) return;

    // Construct start time based on day and hour
    const start = new Date(day);
    start.setHours(hour);
    start.setMinutes(0);
    start.setSeconds(0);
    start.setMilliseconds(0); // Ensure clean start

    // Default duration 30 mins or try to find task to preserve duration?
    // Finding task in props.tasks is expensive but safer for duration preservation
    const task = props.tasks.find(t => t.id == taskId);
    let duration = 30 * 60000;
    if (task && task.start_time && task.end_time) {
        duration = new Date(task.end_time).getTime() - new Date(task.start_time).getTime();
    }

    const end = new Date(start.getTime() + duration);

    router.put(`/tasks/${taskId}`, {
        start_time: start.toISOString(),
        end_time: end.toISOString(),
        time_of_day: 'at_time' // Ensure it switches to timed mode
    }, { preserveScroll: true });
};

const onDropToAllDay = (event, day) => {
    let taskId = event.dataTransfer.getData('text/plain');
    if (!taskId) taskId = event.dataTransfer.getData('taskId');
    
    console.log('Drop All Day:', taskId, day);
    if (!taskId) return;

    const task = props.tasks.find(t => t.id == taskId);
    
    // Construct date string YYYY-MM-DD using Local Time to avoid UTC shift
    const year = day.getFullYear();
    const month = String(day.getMonth() + 1).padStart(2, '0');
    const d = String(day.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${d}`;

    let newTimeOfDay = 'all_day';
    
    if (task) {
        // If task already has a loose time_of_day (morning/day/evening/anytime), preserve it
        if (['morning', 'day', 'evening', 'anytime', 'all_day'].includes(task.time_of_day)) {
            newTimeOfDay = task.time_of_day;
        }
        // If it was 'at_time' or null, default to 'all_day'
    }

    router.put(`/tasks/${taskId}`, {
        start_time: null,
        end_time: null,
        start_date: dateStr, // Ensure date matches drop column
        end_date: dateStr, // Single day for now
        time_of_day: newTimeOfDay
    }, { preserveScroll: true });
};

// Focus Timer Logic
import FocusTimer from '@/Components/FocusTimer.vue';

const activeFocusTaskId = ref(null);
const isFocusMode = ref(false);

const activeFocusTask = computed(() => {
    return props.tasks.find(t => t.id === activeFocusTaskId.value) || null;
});

const openFocusMode = (task) => {
    // Prevent focus mode if dragging
    if (isDragging.value) return;
    activeFocusTaskId.value = task.id;
    isFocusMode.value = true;
};

const closeFocusMode = () => {
    isFocusMode.value = false;
    activeFocusTaskId.value = null;
};

const addChecklistItem = () => {
    if (!form.checklist_items) form.checklist_items = [];
    form.checklist_items.push({ title: '', is_completed: false });
};

const removeChecklistItem = (index) => {
    form.checklist_items.splice(index, 1);
};

// Appearance Picker Logic (Icon & Color)
const isIconPickerOpen = ref(false);
const appearanceSearch = ref('');

const availableColors = {
    myColors: [
        { hex: '#EF4444', bg: 'bg-red-500', text: 'text-white', border: 'border-red-500', name: 'Red' },
        { hex: '#FEF08A', bg: 'bg-yellow-200', text: 'text-yellow-800', border: 'border-yellow-200', name: 'Yellow' },
        { hex: '#DCFCE7', bg: 'bg-green-100', text: 'text-green-800', border: 'border-green-100', name: 'Green' },
        { hex: '#BFDBFE', bg: 'bg-blue-200', text: 'text-blue-800', border: 'border-blue-200', name: 'Blue' },
        { hex: '#E9D5FF', bg: 'bg-purple-200', text: 'text-purple-800', border: 'border-purple-200', name: 'Purple' },
    ],
    reminddoColors: [
        { hex: '#F3E8FF', bg: 'bg-purple-100', text: 'text-purple-900', border: 'border-purple-100', name: 'Lavender' },
        { hex: '#FCE7F3', bg: 'bg-pink-100', text: 'text-pink-900', border: 'border-pink-100', name: 'Rose' },
        { hex: '#FFEDD5', bg: 'bg-orange-100', text: 'text-orange-900', border: 'border-orange-100', name: 'Peach' },
        { hex: '#D1FAE5', bg: 'bg-emerald-100', text: 'text-emerald-900', border: 'border-emerald-100', name: 'Mint' },
        { hex: '#DBEAFE', bg: 'bg-blue-100', text: 'text-blue-900', border: 'border-blue-100', name: 'Sky' },
        { hex: '#E0E7FF', bg: 'bg-indigo-100', text: 'text-indigo-900', border: 'border-indigo-100', name: 'Periwinkle' },
        { hex: '#CFFAFE', bg: 'bg-cyan-100', text: 'text-cyan-900', border: 'border-cyan-100', name: 'Aqua' },
        { hex: '#ECFCCB', bg: 'bg-lime-100', text: 'text-lime-900', border: 'border-lime-100', name: 'Lime' },
    ]
};

const selectedColorObj = computed(() => {
    if (!form.color) return null;
    const all = [...availableColors.myColors, ...availableColors.reminddoColors];
    const found = all.find(c => c.hex.toLowerCase() === form.color.toLowerCase());
    if (found) return found;
    return { bg: 'bg-[custom]', text: 'text-white', style: { backgroundColor: form.color } };
});

const getTaskColorObj = (task) => {
    if (!task.color) return null;
    const all = [...availableColors.myColors, ...availableColors.reminddoColors];
    const found = all.find(c => c.hex.toLowerCase() === task.color.toLowerCase());
    if (found) return found;
    return { bg: 'bg-[custom]', text: 'text-white', style: { backgroundColor: task.color } };
};

const emojiCategories = [
    { id: 'frequent', name: 'Frequently Used', icon: 'clock', icons: ['🤩', '🙂', '📅', '📌', '✨', '🌅', '☀️', '🌙', '🏋️', '📚'] },
    { id: 'people', name: 'Smileys & People', icon: 'face-smile', icons: ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔', '🤐', '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬', '🤥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕', '🤢', '🤮', '🤧', '🥵', '🥶', '🥴', '😵', '🤯', '🤠', '🥳', '😎', '🤓', '🧐', '😕', '😟', '🙁', '😮', '😯', '😲', '😳', '🥺', '😦', '😧', '😨', '😰', '😥', '😢', '😭', '😱', '😖', '😣', '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠', '🤬', '😈', '👿', '💀', '☠️', '💩', '🤡', '👹', '👺', '👻', '👽', '👾', '🤖', '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿', '😾', '🙈', '🙉', '🙊', '💋', '💌', '💘', '💝', '💖', '💗', '💓', '💞', '💕', '💟', '❣️', '💔', '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💯', '💢', '💥', '💫', '💦', '💨', '🕳️', '💣', '💬', '👁️‍🗨️', '🗨️', '💭', '💤', '👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤏', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '🖕', '👇', '☝️', '👍', '👎', '✊', '👊', '🤛', '🤜', '👏', '🙌', '👐', '🤲', '🤝', '🙏', '✍️', '💅', '🤳', '💪', '🦾', '🦿', '🦵', '🦶', '👂', '🦻', '👃', '🧠', '🦷', '🦴', '👀', '👁️', '👅', '👄', '👶', '🧒', '👦', '👧', '🧑', '👱', '👨', '🧔', '👨‍🦰', '👨‍🦱', '👨‍🦳', '👨‍🦲', '👩', '👩‍🦰', '👩‍🦱', '👩‍🦳', '👩‍🦲', '👱‍♀️', '👱‍♂️', '🧓', '👴', '👵', '🙍', '🙍‍♂️', '🙍‍♀️', '🙎', '🙎‍♂️', '🙎‍♀️', '🙅', '🙅‍♂️', '🙅‍♀️', '🙆', '🙆‍♂️', '🙆‍♀️', '💁', '💁‍♂️', '💁‍♀️', '🙋', '🙋‍♂️', '🙋‍♀️', '🙇', '🙇‍♂️', '🙇‍♀️', '🤦', '🤦‍♂️', '🤦‍♀️', '🤷', '🤷‍♂️', '🤷‍♀️', '👨‍⚕️', '👩‍⚕️', '👨‍🎓', '👩‍🎓', '👨‍🏫', '👩‍🏫', '👨‍⚖️', '👩‍⚖️', '👨‍🌾', '👩‍🌾', '👨‍🍳', '👩‍🍳', '👨‍🔧', '👩‍🔧', '👨‍🏭', '👩‍🏭', '👨‍💼', '👩‍💼', '👨‍🔬', '👩‍🔬', '👨‍💻', '👩‍💻', '👨‍🎤', '👩‍🎤', '👨‍🎨', '👩‍🎨', '👨‍✈️', '👩‍✈️', '👨‍🚀', '👩‍🚀', '👨‍🚒', '👩‍🚒', '👮', '👮‍♂️', '👮‍♀️', '🕵', '🕵️‍♂️', '🕵️‍♀️', '💂', '💂‍♂️', '💂‍♀️', '👷', '👷‍♂️', '👷‍♀️', '🤴', '👸', '👳', '👳‍♂️', '👳‍♀️', '👲', '🧕', '🤵', '👰', '🤰', '🤱', '👼', '🎅', '🤶', '🦸', '🦸‍♂️', '🦸‍♀️', '🦹', '🦹‍♂️', '🦹‍♀️', '🧙', '🧙‍♂️', '🧙‍♀️', '🧚', '🧚‍♂️', '🧚‍♀️', '🧛', '🧛‍♂️', '🧛‍♀️', '🧜', '🧜‍♂️', '🧜‍♀️', '🧝', '🧝‍♂️', '🧝‍♀️', '🧞', '🧞‍♂️', '🧞‍♀️', '🧟', '🧟‍♂️', '🧟‍♀️', '💆', '💆‍♂️', '💆‍♀️', '💇', '💇‍♂️', '💇‍♀️', '🚶', '🚶‍♂️', '🚶‍♀️', '🧍', '🧍‍♂️', '🧍‍♀️', '🧎', '🧎‍♂️', '🧎‍♀️', '👨‍🦯', '👩‍🦯', '👨‍🦼', '👩‍🦼', '👨‍🦽', '👩‍🦽', '🏃', '🏃‍♂️', '🏃‍♀️', '💃', '🕺', '🕴️', '👯', '👯‍♂️', '👯‍♀️', '🧖', '🧖‍♂️', '🧖‍♀️', '🧘', '🧘‍♂️', '🧘‍♀️'] },
    { id: 'animals', name: 'Animals & Nature', icon: 'bug-ant', icons: ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', 'cow', '🐷', '🐸', '🐵', '🐔', '🐧', '🐦', 'md-egg', '🐣'] },
    { id: 'food', name: 'Food & Drink', icon: 'cake', icons: ['🍏', '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🍈', '🍒', '🍑', '🍍', '🥭', '🥥', '🥝', '🍅', '🥑', '🍆', '🥔'] },
    { id: 'travel', name: 'Travel & Places', icon: 'truck', icons: ['🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '🚐', '🚚', '🚛', '🚜', '🏍️', '🛵', '🚲', '🛴', '🚨', '🚔', '🚍'] },
    { id: 'activities', name: 'Activities', icon: 'trophy', icons: ['⚽', '🏀', '🏈', '⚾', '🥎', 'ATP', '🏐', '🏉', '🥏', '🎱', '🪀', '🏓', '🏸', '🏒', '🏑', '🥍', '🏏', '🥅', '⛳', '🪁', '🏹','🎣', '🤿'] },
    { id: 'objects', name: 'Objects', icon: 'light-bulb', icons: ['⌚', '📱', '📲', '💻', '⌨️', '🖥️', '🖨️', '🖱️', '🖲️', '🕹️', '🗜️', '💽', '💾', '💿', '📀', '📼', '📷', '📸', '📹', '🎥'] },
    { id: 'symbols', name: 'Symbols', icon: 'musical-note', icons: ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '☮️'] },
    { id: 'flags', name: 'Flags', icon: 'flag', icons: ['🏳️', '🏴', '🏁', '🚩', '🏳️‍🌈', '🏳️‍⚧️', '🇺🇳', '🇦🇫', '🇦🇽', '🇦🇱', '🇩🇿', '🇦🇸', '🇦🇩', '🇦🇴', '🇦🇮', '🇦🇶', '🇦🇬', '🇦🇷', '🇦🇲'] },
];

import { emojiKeywords } from '@/data/emoji-keywords';
import { timeOfDayOptions, getIconForTimeOfDay } from '@/data/time-of-day-icons';

const filteredEmojiCategories = computed(() => {
    if (!appearanceSearch.value) {
        return emojiCategories;
    }
    const query = appearanceSearch.value.toLowerCase();
    
    // If strict match, we can skip search, but better to search keywords.
    return emojiCategories.map(cat => {
        const filteredIcons = cat.icons.filter(icon => {
            const keywords = emojiKeywords[icon] || [];
            // Match against keywords or the icon itself
            return keywords.some(k => k.includes(query)) || icon.includes(query);
        });
        
        // Return category only if it has matching icons
        return {
            ...cat,
            icons: filteredIcons
        };
    }).filter(cat => cat.icons.length > 0);
});

const activeCategory = ref('frequent');
const iconPickerWrapperRef = ref(null);
const isTimePickerOpen = ref(false);
const timePickerWrapperRef = ref(null);
const isRepeatPickerOpen = ref(false);
const repeatPickerWrapperRef = ref(null);

const handleClickOutside = (event) => {
    // Close Icon Picker
    if (isIconPickerOpen.value && iconPickerWrapperRef.value && !iconPickerWrapperRef.value.contains(event.target)) {
        isIconPickerOpen.value = false;
    }
    // Close Time Picker
    if (isTimePickerOpen.value && timePickerWrapperRef.value && !timePickerWrapperRef.value.contains(event.target)) {
        isTimePickerOpen.value = false;
    }
    // Close Repeat Picker
    if (isRepeatPickerOpen.value && repeatPickerWrapperRef.value && !repeatPickerWrapperRef.value.contains(event.target)) {
        isRepeatPickerOpen.value = false;
    }
};

const isDark = computed(() => {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
});

const showCustomColor = ref(false);
const customColorState = reactive({
    h: 0,
    s: 100,
    v: 100,
    isDraggingSat: false,
    isDraggingHue: false
});

const satBoxRef = ref(null);
const hueSliderRef = ref(null);

const hsvToHex = (h, s, v) => {
    s /= 100;
    v /= 100;
    let c = v * s;
    let x = c * (1 - Math.abs(((h / 60) % 2) - 1));
    let m = v - c;
    let r = 0, g = 0, b = 0;

    if (0 <= h && h < 60) { r = c; g = x; b = 0; }
    else if (60 <= h && h < 120) { r = x; g = c; b = 0; }
    else if (120 <= h && h < 180) { r = 0; g = c; b = x; }
    else if (180 <= h && h < 240) { r = 0; g = x; b = c; }
    else if (240 <= h && h < 300) { r = x; g = 0; b = c; }
    else if (300 <= h && h < 360) { r = c; g = 0; b = x; }

    r = Math.round((r + m) * 255).toString(16).padStart(2, '0');
    g = Math.round((g + m) * 255).toString(16).padStart(2, '0');
    b = Math.round((b + m) * 255).toString(16).padStart(2, '0');
    return `#${r}${g}${b}`;
};

const hexToHsv = (hex) => {
    let r = 0, g = 0, b = 0;
    if (hex.length === 4) {
        r = parseInt("0x" + hex[1] + hex[1]);
        g = parseInt("0x" + hex[2] + hex[2]);
        b = parseInt("0x" + hex[3] + hex[3]);
    } else if (hex.length === 7) {
        r = parseInt("0x" + hex[1] + hex[2]);
        g = parseInt("0x" + hex[3] + hex[4]);
        b = parseInt("0x" + hex[5] + hex[6]);
    }
    r /= 255; g /= 255; b /= 255;
    let cmin = Math.min(r,g,b), cmax = Math.max(r,g,b), delta = cmax - cmin;
    let h = 0, s = 0, v = 0;

    if (delta === 0) h = 0;
    else if (cmax === r) h = ((g - b) / delta) % 6;
    else if (cmax === g) h = (b - r) / delta + 2;
    else h = (r - g) / delta + 4;

    h = Math.round(h * 60);
    if (h < 0) h += 360;
    v = Math.round(cmax * 100);
    s = cmax === 0 ? 0 : Math.round((delta / cmax) * 100);

    return { h, s, v };
};

const updateCustomColor = () => {
    const hex = hsvToHex(customColorState.h, customColorState.s, customColorState.v);
    form.color = hex;
};

const handleSatMouseDown = (e) => {
    customColorState.isDraggingSat = true;
    updateSatFromEvent(e);
    window.addEventListener('mousemove', updateSatFromEvent);
    window.addEventListener('mouseup', stopColorDrag);
};

const handleHueMouseDown = (e) => {
    customColorState.isDraggingHue = true;
    updateHueFromEvent(e);
    window.addEventListener('mousemove', updateHueFromEvent);
    window.addEventListener('mouseup', stopColorDrag);
};

const stopColorDrag = () => {
    customColorState.isDraggingSat = false;
    customColorState.isDraggingHue = false;
    window.removeEventListener('mousemove', updateSatFromEvent);
    window.removeEventListener('mousemove', updateHueFromEvent);
    window.removeEventListener('mouseup', stopColorDrag);
};

const updateSatFromEvent = (e) => {
    if (!customColorState.isDraggingSat || !satBoxRef.value) return;
    const rect = satBoxRef.value.getBoundingClientRect();
    let x = e.clientX - rect.left;
    let y = e.clientY - rect.top;
    x = Math.max(0, Math.min(x, rect.width));
    y = Math.max(0, Math.min(y, rect.height));
    
    customColorState.s = Math.round((x / rect.width) * 100);
    customColorState.v = Math.round(100 - (y / rect.height) * 100);
    updateCustomColor();
};

const updateHueFromEvent = (e) => {
    if (!customColorState.isDraggingHue || !hueSliderRef.value) return;
    const rect = hueSliderRef.value.getBoundingClientRect();
    let x = e.clientX - rect.left;
    x = Math.max(0, Math.min(x, rect.width));
    customColorState.h = Math.round((x / rect.width) * 360);
    updateCustomColor();
};

const toggleCustomColor = () => {
    if (!showCustomColor.value) {
        // Init from current form color if hex
        if (form.color && form.color.startsWith('#')) {
             const hsv = hexToHsv(form.color);
             customColorState.h = hsv.h;
             customColorState.s = hsv.s;
             customColorState.v = hsv.v;
        }
    }
    showCustomColor.value = !showCustomColor.value;
};

const scrollToCategory = (id) => {
    activeCategory.value = id;
    const el = document.getElementById(`emoji-category-${id}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

const selectAppearance = (type, value) => {
    if (type === 'icon') {
        form.icon = value;
    } else if (type === 'color') {
        form.color = value.hex; // Save Hex code
    }
};


// Notification Logic
const requestNotificationPermission = async () => {
    if ('Notification' in window && Notification.permission !== 'granted') {
        await Notification.requestPermission();
    }
};

const sendTestNotification = () => {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification("Test Notification", {
            body: "If you see this, notifications are working! 🚀",
            icon: '/favicon.ico'
        });
    } else {
        alert("Please enable notifications for this site first.");
        requestNotificationPermission();
    }
};

const checkUpcomingTasks = () => {
    if (Notification.permission !== 'granted') return;

    const now = new Date();
    // Get current wall-clock time in minutes (relative to local start of epoch implies complexity, simpler to build check objects)
    
    props.tasks.forEach(task => {
        if (!task.start_time) return;

        // Parse task string manually to force "Wall Clock" interpretation in Local Time
        // task.start_time format: "YYYY-MM-DD HH:mm:ss" or "YYYY-MM-DDTHH:mm:ss"
        // We only care about matching the specific minute
        
        const year = parseInt(task.start_time.slice(0, 4));
        const month = parseInt(task.start_time.slice(5, 7)) - 1; // Months are 0-indexed
        const day = parseInt(task.start_time.slice(8, 10));
        const hour = parseInt(task.start_time.slice(11, 13));
        const minute = parseInt(task.start_time.slice(14, 16));
        
        const taskTime = new Date(year, month, day, hour, minute, 0); // Local Date object
        
        const timeDiff = taskTime.getTime() - now.getTime();
        
        // Notify if starting within the next 60 seconds (or slightly overdue implies "Starting Now")
        // Window: -30s to +60s
        if (timeDiff > -30000 && timeDiff <= 60000) {
             new Notification(`Task Starting: ${task.title}`, {
                body: `It's time for ${task.title}! \n${formatTime(task.start_time)} - ${formatTime(task.end_time)}`,
                icon: '/favicon.ico',
                tag: `task-${task.id}`
            });
        }
    });
};

// Current Time Indicator Logic
const currentTimeTop = ref(0);
const timelineContainer = ref(null);

const updateCurrentTimePosition = () => {
    const now = new Date();
    const hours = now.getHours() + (now.getMinutes() / 60);
    // 0 is start hour, 100px per hour
    currentTimeTop.value = hours * 100;
};

const scrollToCurrentTime = () => {
    if (timelineContainer.value) {
        // Scroll to current time minus some padding (e.g. 2 hours / 200px) so it's not at the very top
        const scrollPos = Math.max(0, currentTimeTop.value - 200);
        timelineContainer.value.scrollTop = scrollPos;
    }
};

onMounted(() => {
    requestNotificationPermission();
    updateCurrentTimePosition();
    
    // Initial scroll after a slight tick to allow rendering
    setTimeout(scrollToCurrentTime, 100);
    
    // Check every 30 seconds for notifications and update line position
    const interval = setInterval(() => {
        checkUpcomingTasks();
        updateCurrentTimePosition();
    }, 30000);
    
    onUnmounted(() => {
        clearInterval(interval);
    });
    onUnmounted(() => {
        clearInterval(interval);
    });
});

// Dark Mode Logic
const isDarkMode = ref(localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches));

const toggleTheme = () => {
    isDarkMode.value = !isDarkMode.value;
    if (isDarkMode.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
};



onMounted(() => {
    // Apply initial theme based on isDarkMode ref
    if (isDarkMode.value) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
});

</script>

<template>
    <AppLayout title="Visual Planner" :show-nav="false">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                
                <!-- Date Navigation & Controls -->
                <div class="flex items-center gap-4 bg-white dark:bg-gray-800 p-2 rounded-full shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-1">
                        <button @click="prevDate" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <button @click="nextDate" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                    
                    <h2 class="font-serif font-bold text-2xl text-gray-900 dark:text-white min-w-[200px] text-center select-none">
                        {{ currentDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }) }}
                    </h2>

                    <!-- View Switcher -->
                    <div class="relative">
                         <button @click="showViewModeDropdown = !showViewModeDropdown" class="flex items-center gap-1 text-sm font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 py-1.5 px-3 rounded-lg transition capitalize">
                             {{ viewMode === '3-day' ? '3-days' : viewMode }}
                             <svg class="w-3 h-3 transition-transform duration-200" :class="{'rotate-180': showViewModeDropdown}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                         </button>
                         
                         <!-- Dropdown Backdrop (Transparent) to close on click outside -->
                         <div v-if="showViewModeDropdown" @click="showViewModeDropdown = false" class="fixed inset-0 z-40 cursor-default"></div>

                         <!-- Dropdown -->
                         <div v-show="showViewModeDropdown" class="absolute top-full left-0 mt-2 w-32 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden z-50">
                             <div @click="viewMode = 'day'" class="px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm cursor-pointer flex justify-between items-center" :class="{'font-bold text-purple-600 dark:text-purple-400': viewMode === 'day', 'text-gray-700 dark:text-gray-200': viewMode !== 'day'}">
                                Day <span v-if="viewMode === 'day'">✓</span>
                             </div>
                             <div @click="viewMode = '3-day'" class="px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm cursor-pointer flex justify-between items-center" :class="{'font-bold text-purple-600 dark:text-purple-400': viewMode === '3-day', 'text-gray-700 dark:text-gray-200': viewMode !== '3-day'}">
                                3-days <span v-if="viewMode === '3-day'">✓</span>
                             </div>
                             <div @click="viewMode = 'week'" class="px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm cursor-pointer flex justify-between items-center" :class="{'font-bold text-purple-600 dark:text-purple-400': viewMode === 'week', 'text-gray-700 dark:text-gray-200': viewMode !== 'week'}">
                                Week <span v-if="viewMode === 'week'">✓</span>
                             </div>
                             <div @click="viewMode = 'month'" class="px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm cursor-pointer flex justify-between items-center" :class="{'font-bold text-purple-600 dark:text-purple-400': viewMode === 'month', 'text-gray-700 dark:text-gray-200': viewMode !== 'month'}">
                                Month <span v-if="viewMode === 'month'">✓</span>
                             </div>
                             <div @click="viewMode = 'agenda'" class="px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm cursor-pointer flex justify-between items-center" :class="{'font-bold text-purple-600 dark:text-purple-400': viewMode === 'agenda', 'text-gray-700 dark:text-gray-200': viewMode !== 'agenda'}">
                                Agenda <span v-if="viewMode === 'agenda'">✓</span>
                             </div>
                         </div>
                    </div>

                    <button @click="goToToday" class="bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-bold text-xs py-1.5 px-3 rounded-lg transition uppercase tracking-wide">
                        Today
                    </button>
                </div>

                <!-- Right Actions -->
                <div class="flex gap-2 items-center">
                    <!-- User Dropdown -->
                    <div class="ms-3 relative">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button v-if="$page.props.jetstream?.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                    <img class="size-10 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                </button>

                                <span v-else class="inline-flex rounded-md">
                                    <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700 active:bg-gray-50 dark:active:bg-gray-700 transition ease-in-out duration-150">
                                        {{ $page.props.auth.user.name }}

                                        <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </span>
                            </template>

                            <template #content>
                                <!-- Account Management -->
                                <div class="block px-4 py-2 text-xs text-gray-400">
                                    Manage Account
                                </div>

                                <DropdownLink :href="route('profile.show')">
                                    Profile
                                </DropdownLink>

                                <DropdownLink v-if="$page.props.jetstream?.hasApiFeatures" :href="route('api-tokens.index')">
                                    API Tokens
                                </DropdownLink>

                                <div class="border-t border-gray-200 dark:border-gray-600" />

                                <!-- Authentication -->
                                <form @submit.prevent="logout">
                                    <DropdownLink as="button">
                                        Log Out
                                    </DropdownLink>
                                </form>
                            </template>
                        </Dropdown>
                    </div>

                    <!-- Theme Toggle -->
                    <button @click="toggleTheme" class="w-10 h-10 rounded-full bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-black dark:hover:text-white transition shadow-sm" :title="isDarkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode'">
                        <!-- Sun Icon (for Dark Mode) -->
                        <svg v-if="isDarkMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <!-- Moon Icon (for Light Mode) -->
                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                    </button>

                    <!-- Settings -->
                    <Link :href="route('settings.account')" class="w-10 h-10 rounded-full bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-black dark:hover:text-white transition shadow-sm" title="Settings">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </Link>

                </div>
            </div>
            
            <!-- Plan Wizard Button (moved slightly down or keep) -->
             <div v-if="inboxTasks.length > 0" class="mt-6 bg-gradient-to-r from-purple-500 to-indigo-600 rounded-2xl p-4 flex justify-between items-center text-white shadow-lg cursor-pointer hover:scale-[1.01] transition relative overflow-hidden" @click="openPlanModal">
                <div class="relative z-10">
                     <h3 class="font-bold text-lg">Plan your day</h3>
                     <p class="text-purple-100 text-sm">You have {{ inboxTasks.length }} unscheduled tasks</p>
                </div>
                 <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center font-bold text-xl relative z-10">
                    ✨
                </div>
            </div>
        </template>

        <div class="py-12">

            <div class="w-full mx-auto sm:px-6 lg:px-8">
                
                <!-- Planner View -->
                <div class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl sm:rounded-3xl p-6 flex flex-col md:flex-row gap-0 min-h-[600px] items-stretch relative pb-20 md:pb-6">
                    
                    <div 
                        class="w-full md:w-72 flex-shrink-0 pr-0 md:pr-4 mr-0 md:mr-4 flex flex-col transition-all bg-white dark:bg-gray-800"
                        :class="{ 'hidden md:flex': activeTab !== 'todo', 'flex': activeTab === 'todo' }"
                    >
                        <div class="flex justify-between items-center mb-6 pl-2">
                            <h3 class="font-serif font-bold text-2xl text-gray-900 dark:text-white">To-Dos</h3>
                        </div>
                        
                        <div class="flex-1 space-y-4 overflow-y-auto max-h-[600px] custom-scrollbar px-2 pb-10">
                             
                             <!-- High Priority -->
                             <div class="space-y-1">
                                 <div @click="sidebarSections.high = !sidebarSections.high" 
                                      @dragover.prevent="handleDragOver($event)" @drop="onDropPriority($event, 'high')"
                                      class="flex items-center gap-2 px-3 py-1.5 bg-red-50 dark:bg-red-900/60 text-red-800 dark:text-red-100 rounded-lg cursor-pointer hover:bg-red-100 dark:hover:bg-red-900/80 transition select-none">
                                     <span class="text-xs transition-transform duration-200" :class="{'rotate-[-90deg]': !sidebarSections.high}">▼</span>
                                     <span class="text-xs font-bold uppercase tracking-wider">High ({{ inboxTasksHigh.length }})</span>
                                 </div>
                                 <div v-show="sidebarSections.high" class="space-y-2 pl-2" @dragover.prevent="handleDragOver($event)" @drop="onDropPriority($event, 'high')">
                                     <div v-if="inboxTasksHigh.length === 0" class="border-2 border-dashed border-red-100 dark:border-gray-700 rounded-xl p-3 text-center text-xs text-red-300 dark:text-gray-600 font-bold tracking-wider group/empty">
                                          <span class="opacity-0 group-hover/empty:opacity-100 transition-opacity duration-200">Drop to add</span>
                                     </div>
                                     <div v-for="task in inboxTasksHigh" :key="task.id" draggable="true" @dragend="handleDragEnd" @dragstart="handleInboxDragStart($event, task)"
                                          class="bg-white dark:bg-gray-700 border border-gray-100 dark:border-gray-600 rounded-xl p-3 cursor-move hover:shadow-md transition group relative flex items-center gap-3">
                                          <div class="w-8 h-8 rounded-full text-lg flex items-center justify-center shrink-0"
                                                :class="[getTaskColorObj(task)?.bg && getTaskColorObj(task).bg !== 'bg-[custom]' ? getTaskColorObj(task).bg : 'bg-red-50 dark:bg-red-900/30', getTaskColorObj(task)?.text || '']"
                                                :style="getTaskColorObj(task)?.style || {}">
                                              {{ task.icon || '📌' }}
                                          </div>
                                          
                                          <div class="flex-1 min-w-0" v-if="inlineEditingTaskId === task.id">
                                               <input 
                                                    :id="`inline-input-${task.id}`"
                                                    v-model="inlineTitle" 
                                                    @blur="saveInlineEdit(task)"
                                                    @keydown.enter="saveInlineEdit(task)"
                                                    @keydown.esc="cancelInlineEdit"
                                                    class="w-full text-sm font-bold text-gray-900 border border-gray-200 rounded px-2 py-1 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 bg-white shadow-sm"
                                                />
                                          </div>
                                          <div v-else class="flex-1 min-w-0 font-bold text-sm text-gray-700 dark:text-gray-200 truncate cursor-text hover:text-gray-900 dark:hover:text-white" @click.stop="startInlineEdit(task)">{{ task.title }}</div>

                                          <!-- 3-Dots Menu -->
                                          <button @click.stop="openEditModal(task)" class="w-6 h-6 flex-shrink-0 flex items-center justify-center text-gray-400 opacity-0 group-hover:opacity-100 hover:text-gray-600 rounded hover:bg-gray-100 transition">
                                              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                          </button>

                                          <button @click.stop="toggleTaskCompletion(task)" class="w-5 h-5 rounded-full border-2 border-gray-200 hover:border-red-400 flex items-center justify-center transition">
                                              <svg v-if="task.is_completed" class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                          </button>
                                     </div>
                                 </div>
                             </div>

                             <!-- Medium Priority -->
                             <div class="space-y-1">
                                 <div @click="sidebarSections.medium = !sidebarSections.medium" 
                                      @dragover.prevent="handleDragOver($event)" @drop="onDropPriority($event, 'medium')"
                                      class="flex items-center gap-2 px-3 py-1.5 bg-orange-50 dark:bg-amber-900/60 text-orange-800 dark:text-amber-100 rounded-lg cursor-pointer hover:bg-orange-100 dark:hover:bg-amber-900/80 transition select-none">
                                     <span class="text-xs transition-transform duration-200" :class="{'rotate-[-90deg]': !sidebarSections.medium}">▼</span>
                                     <span class="text-xs font-bold uppercase tracking-wider">Medium ({{ inboxTasksMedium.length }})</span>
                                 </div>
                                 <div v-show="sidebarSections.medium" class="space-y-2 pl-2" @dragover.prevent="handleDragOver($event)" @drop="onDropPriority($event, 'medium')">
                                     <div v-if="inboxTasksMedium.length === 0" class="border-2 border-dashed border-orange-100 dark:border-gray-700 rounded-xl p-3 text-center text-xs text-orange-300 dark:text-gray-600 font-bold tracking-wider group/empty">
                                          <span class="opacity-0 group-hover/empty:opacity-100 transition-opacity duration-200">Drop to add</span>
                                     </div>
                                     <div v-for="task in inboxTasksMedium" :key="task.id" draggable="true" @dragend="handleDragEnd" @dragstart="handleInboxDragStart($event, task)"
                                          class="bg-white dark:bg-gray-700 border border-gray-100 dark:border-gray-600 rounded-xl p-3 cursor-move hover:shadow-md transition group relative flex items-center gap-3">
                                          <div class="w-8 h-8 rounded-full text-lg flex items-center justify-center shrink-0"
                                                :class="[getTaskColorObj(task)?.bg && getTaskColorObj(task).bg !== 'bg-[custom]' ? getTaskColorObj(task).bg : 'bg-orange-50 dark:bg-orange-900/30', getTaskColorObj(task)?.text || '']"
                                                :style="getTaskColorObj(task)?.style || {}">
                                              {{ task.icon || '📌' }}
                                          </div>
                                          
                                          <div class="flex-1 min-w-0" v-if="inlineEditingTaskId === task.id">
                                               <input 
                                                    :id="`inline-input-${task.id}`"
                                                    v-model="inlineTitle" 
                                                    @blur="saveInlineEdit(task)"
                                                    @keydown.enter="saveInlineEdit(task)"
                                                    @keydown.esc="cancelInlineEdit"
                                                    class="w-full text-sm font-bold text-gray-900 border border-gray-200 rounded px-2 py-1 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 bg-white shadow-sm"
                                                />
                                          </div>
                                          <div v-else class="flex-1 min-w-0 font-bold text-sm text-gray-700 dark:text-gray-200 truncate cursor-text hover:text-gray-900 dark:hover:text-white" @click.stop="startInlineEdit(task)">{{ task.title }}</div>

                                          <button @click.stop="openEditModal(task)" class="w-6 h-6 flex-shrink-0 flex items-center justify-center text-gray-400 opacity-0 group-hover:opacity-100 hover:text-gray-600 rounded hover:bg-gray-100 transition">
                                              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                          </button>

                                          <button @click.stop="toggleTaskCompletion(task)" class="w-5 h-5 rounded-full border-2 border-gray-200 hover:border-orange-400 flex items-center justify-center transition">
                                              <svg v-if="task.is_completed" class="w-3 h-3 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                          </button>
                                     </div>
                                 </div>
                             </div>

                             <!-- Low Priority -->
                             <div class="space-y-1">
                                 <div @click="sidebarSections.low = !sidebarSections.low" 
                                      @dragover.prevent="handleDragOver($event)" @drop="onDropPriority($event, 'low')"
                                      class="flex items-center gap-2 px-3 py-1.5 bg-blue-50 dark:bg-emerald-900/60 text-blue-800 dark:text-emerald-100 rounded-lg cursor-pointer hover:bg-blue-100 dark:hover:bg-emerald-900/80 transition select-none">
                                     <span class="text-xs transition-transform duration-200" :class="{'rotate-[-90deg]': !sidebarSections.low}">▼</span>
                                     <span class="text-xs font-bold uppercase tracking-wider">Low ({{ inboxTasksLow.length }})</span>
                                 </div>
                                 <div v-show="sidebarSections.low" class="space-y-2 pl-2" @dragover.prevent="handleDragOver($event)" @drop="onDropPriority($event, 'low')">
                                     <div v-if="inboxTasksLow.length === 0" class="border-2 border-dashed border-blue-100 dark:border-gray-700 rounded-xl p-3 text-center text-xs text-blue-300 dark:text-gray-600 font-bold tracking-wider group/empty">
                                          <span class="opacity-0 group-hover/empty:opacity-100 transition-opacity duration-200">Drop to add</span>
                                     </div>
                                     <div v-for="task in inboxTasksLow" :key="task.id" draggable="true" @dragend="handleDragEnd" @dragstart="handleInboxDragStart($event, task)"
                                          class="bg-white dark:bg-gray-700 border border-gray-100 dark:border-gray-600 rounded-xl p-3 cursor-move hover:shadow-md transition group relative flex items-center gap-3">
                                          <div class="w-8 h-8 rounded-full text-lg flex items-center justify-center shrink-0"
                                                :class="[getTaskColorObj(task)?.bg && getTaskColorObj(task).bg !== 'bg-[custom]' ? getTaskColorObj(task).bg : 'bg-blue-50 dark:bg-blue-900/30', getTaskColorObj(task)?.text || '']"
                                                :style="getTaskColorObj(task)?.style || {}">
                                              {{ task.icon || '📌' }}
                                          </div>
                                          
                                          <div class="flex-1 min-w-0" v-if="inlineEditingTaskId === task.id">
                                               <input 
                                                    :id="`inline-input-${task.id}`"
                                                    v-model="inlineTitle" 
                                                    @blur="saveInlineEdit(task)"
                                                    @keydown.enter="saveInlineEdit(task)"
                                                    @keydown.esc="cancelInlineEdit"
                                                    class="w-full text-sm font-bold text-gray-900 border border-gray-200 rounded px-2 py-1 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 bg-white shadow-sm"
                                                />
                                          </div>
                                          <div v-else class="flex-1 min-w-0 font-bold text-sm text-gray-700 dark:text-gray-200 truncate cursor-text hover:text-gray-900 dark:hover:text-white" @click.stop="startInlineEdit(task)">{{ task.title }}</div>

                                          <button @click.stop="openEditModal(task)" class="w-6 h-6 flex-shrink-0 flex items-center justify-center text-gray-400 opacity-0 group-hover:opacity-100 hover:text-gray-600 rounded hover:bg-gray-100 transition">
                                              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                          </button>

                                          <button @click.stop="toggleTaskCompletion(task)" class="w-5 h-5 rounded-full border-2 border-gray-200 hover:border-blue-400 flex items-center justify-center transition">
                                              <svg v-if="task.is_completed" class="w-3 h-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                          </button>
                                     </div>
                                 </div>
                             </div>

                             <!-- General To-Do (Standard) -->
                             <div class="space-y-1 pt-2">
                                 <div @click="sidebarSections.todo = !sidebarSections.todo" 
                                      @dragover.prevent="handleDragOver($event)" @drop="onDropPriority($event, 'none')"
                                      class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 dark:bg-slate-800 text-gray-800 dark:text-slate-200 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-700 transition select-none">
                                     <span class="text-xs transition-transform duration-200" :class="{'rotate-[-90deg]': !sidebarSections.todo}">▼</span>
                                     <span class="text-xs font-bold uppercase tracking-wider">To-Do ({{ inboxTasksTodo.length }})</span>
                                 </div>
                                 <div v-show="sidebarSections.todo" class="space-y-2" @dragover.prevent="handleDragOver($event)" @drop="onDropPriority($event, 'none')">
                                     <!-- Add to-do Button -->
                                     <button @click="createInboxTask" class="w-full flex items-center gap-3 p-3 bg-white dark:bg-gray-700 border border-gray-100 dark:border-gray-600 rounded-xl text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-white hover:border-gray-300 dark:hover:border-gray-500 hover:bg-gray-50 dark:hover:bg-gray-600 transition group">
                                         <div class="w-8 h-8 rounded-full bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-600 flex items-center justify-center text-lg font-bold group-hover:bg-white dark:group-hover:bg-gray-700 text-purple-600 dark:text-purple-400 group-hover:scale-110 transition">+</div>
                                         <span class="font-bold text-sm">Add to-do</span>
                                     </button>

                                     <div v-for="task in inboxTasksTodo" :key="task.id" draggable="true" @dragend="handleDragEnd" @dragstart="handleInboxDragStart($event, task)"
                                          class="bg-white dark:bg-gray-700 border border-gray-100 dark:border-gray-600 rounded-xl p-3 cursor-move hover:shadow-md transition group relative flex items-center gap-3">
                                          <div class="w-8 h-8 rounded-full text-lg flex items-center justify-center shrink-0"
                                                :class="[getTaskColorObj(task)?.bg && getTaskColorObj(task).bg !== 'bg-[custom]' ? getTaskColorObj(task).bg : 'bg-gray-50 dark:bg-gray-600', getTaskColorObj(task)?.text || '']"
                                                :style="getTaskColorObj(task)?.style || {}">
                                              {{ task.icon || '📌' }}
                                          </div>
                                          
                                          <div class="flex-1 min-w-0" v-if="inlineEditingTaskId === task.id">
                                               <input 
                                                    :id="`inline-input-${task.id}`"
                                                    v-model="inlineTitle" 
                                                    @blur="saveInlineEdit(task)"
                                                    @keydown.enter="saveInlineEdit(task)"
                                                    @keydown.esc="cancelInlineEdit"
                                                    class="w-full text-sm font-bold text-gray-900 border border-gray-200 rounded px-2 py-1 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 bg-white shadow-sm"
                                                />
                                          </div>
                                          <div v-else class="flex-1 min-w-0 font-bold text-sm text-gray-700 dark:text-gray-200 truncate cursor-text hover:text-gray-900 dark:hover:text-white" @click.stop="startInlineEdit(task)">{{ task.title }}</div>

                                          <button @click.stop="openEditModal(task)" class="w-6 h-6 flex-shrink-0 flex items-center justify-center text-gray-400 opacity-0 group-hover:opacity-100 hover:text-gray-600 rounded hover:bg-gray-100 transition">
                                              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                          </button>

                                          <button @click.stop="toggleTaskCompletion(task)" class="w-5 h-5 rounded-full border-2 border-gray-200 hover:border-gray-400 flex items-center justify-center transition">
                                              <svg v-if="task.is_completed" class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                          </button>

                                     </div>
                                 </div>
                             </div>

                             <!-- Done Section -->
                             <div class="space-y-1 pt-2">
                                 <div @click="sidebarSections.done = !sidebarSections.done" class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition select-none">
                                     <span class="text-xs transition-transform duration-200" :class="{'rotate-[-90deg]': !sidebarSections.done}">▼</span>
                                     <span class="text-xs font-bold uppercase tracking-wider">Done ({{ inboxTasksDone.length }})</span>
                                 </div>
                                 <div v-show="sidebarSections.done" class="space-y-2 opacity-60">
                                     <div v-for="task in inboxTasksDone" :key="task.id" class="bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-xl p-3 flex items-center gap-3 cursor-pointer" @click="toggleTaskCompletion(task)">
                                          <div class="w-8 h-8 rounded-full bg-white text-lg flex items-center justify-center grayscale opacity-50">{{ task.icon || '📌' }}</div>
                                          <div class="flex-1 min-w-0 font-bold text-sm text-gray-500 dark:text-gray-500 truncate line-through">{{ task.title }}</div>
                                          <div class="w-5 h-5 rounded-full bg-gray-400 flex items-center justify-center">
                                              <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                          </div>
                                     </div>
                                 </div>
                             </div>

                        </div>
                    </div>

                    <!-- Multi-View Planner -->
                    <template v-if="activeTab !== 'me'">
                        
                        <!-- Month View -->
                        <div v-if="viewMode === 'month'" class="flex-1 p-4 overflow-y-auto bg-white">
                            <div class="grid grid-cols-7 mb-2">
                                <div v-for="d in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']" :key="d" class="text-center text-xs font-bold text-gray-400 uppercase tracking-widest">{{ d }}</div>
                            </div>
                            <div class="grid grid-cols-7 auto-rows-fr gap-px bg-gray-200 border border-gray-200 rounded-lg overflow-hidden">
                                <div v-for="day in daysToDisplay" :key="day.toISOString()" 
                                    class="min-h-[100px] bg-white p-2 flex flex-col gap-1 transition hover:bg-gray-50"
                                    @click="currentDate = day; viewMode = 'day'"
                                >
                                    <span class="text-xs font-bold text-gray-500 mb-1" :class="{'text-purple-600 bg-purple-100 px-2 py-0.5 rounded-full w-fit': day.toDateString() === new Date().toDateString()}">
                                        {{ day.getDate() }}
                                    </span>
                                    <div class="flex-1 flex flex-col gap-1 overflow-hidden">
                                        <div v-for="task in (groupedTasks[getDateKey(day)]?.day || []).slice(0, 3)" 
                                            :key="task.id" 
                                            class="text-[10px] truncate px-1.5 py-0.5 rounded font-medium border flex items-center gap-1"
                                            :class="task.is_completed ? 'bg-gray-100 text-gray-400 border-gray-100 line-through' : 'bg-blue-50 text-blue-700 border-blue-100'"
                                        >
                                            {{ task.title }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Agenda View -->
                        <div v-else-if="viewMode === 'agenda'" class="flex-1 p-6 overflow-y-auto bg-gray-50">
                             <div class="max-w-2xl mx-auto space-y-8 pb-20">
                                <div v-for="day in daysToDisplay" :key="day.toISOString()">
                                    <h3 class="font-serif font-bold text-xl mb-4 text-gray-900 border-b border-gray-200 pb-2 flex items-center gap-2 sticky top-0 bg-gray-50 z-10">
                                        {{ day.toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'long' }) }}
                                        <span v-if="day.toDateString() === new Date().toDateString()" class="bg-purple-100 text-purple-600 dark:bg-purple-900 dark:text-purple-300 text-xs px-2 py-1 rounded-full uppercase tracking-wider font-sans">Today</span>
                                    </h3>
                                    <div v-if="!groupedTasks[getDateKey(day)]" class="text-gray-400 italic text-sm">No tasks planned</div>
                                    <div v-else class="space-y-2">
                                        <div v-for="task in [
                                            ...(groupedTasks[getDateKey(day)]?.morning || []),
                                            ...(groupedTasks[getDateKey(day)]?.day || []),
                                            ...(groupedTasks[getDateKey(day)]?.evening || []),
                                            ...(groupedTasks[getDateKey(day)]?.anytime || [])
                                        ]" :key="task.id" 
                                           draggable="true" @dragend="handleDragEnd" 
                                           @dragstart="handleInboxDragStart($event, task)"
                                           @click="openEditModal(task)" 
                                           class="bg-white p-3 rounded-xl border border-gray-100 flex items-center gap-4 hover:shadow-md transition cursor-move group">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg shadow-inner shrink-0"
                                                  :class="[getTaskColorObj(task)?.bg && getTaskColorObj(task).bg !== 'bg-[custom]' ? getTaskColorObj(task).bg : 'bg-gray-50', {'opacity-50 grayscale': task.is_completed}, getTaskColorObj(task)?.text || '']"
                                                  :style="getTaskColorObj(task)?.style || {}">
                                                {{ task.icon || '📌' }}
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="font-bold text-gray-900 group-hover:text-purple-600 transition" :class="{'line-through text-gray-400': task.is_completed}">{{ task.title }}</h4>
                                                <p class="text-xs text-gray-500 font-mono">{{ task.start_time ? `${FormatTimeSimple(task.start_time)} - ${FormatTimeSimple(task.end_time)}` : (task.duration + 'm, Anytime') }}</p>
                                            </div>
                                            <button 
                                                @click.stop="toggleTaskCompletion(task)" 
                                                class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition"
                                                :class="task.is_completed ? 'bg-gray-500 border-gray-500 text-white' : 'border-gray-200 hover:border-gray-400'"
                                            >
                                                <svg v-if="task.is_completed" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                             </div>
                        </div>

                        <!-- 3-Day Column View -->
                        <div v-else-if="viewMode === '3-day'" class="flex-1 flex overflow-x-auto bg-gray-50/50 dark:bg-gray-900">
                            <div v-for="day in daysToDisplay" :key="day.toISOString()" class="flex-1 min-w-[320px] max-w-md flex flex-col h-full">
                                <div class="text-center py-6 sticky top-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm z-10">
                                    <h3 class="font-bold text-gray-400 dark:text-gray-500 text-xs tracking-widest uppercase mb-1">{{ day.toLocaleDateString('en-US', { weekday: 'short' }) }}</h3>
                                    <span class="text-3xl font-serif font-bold text-gray-900 dark:text-white block">{{ day.getDate().toString().padStart(2, '0') }}</span>
                                </div>
                                <div class="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-4">
                                    
                                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm relative group"
                                         @dragover.prevent="handleDragOver($event)" @drop="onDropPlanner($event, day, 'anytime')">
                                        <div class="bg-gray-100 dark:bg-zinc-800 px-4 py-3 rounded-t-2xl text-gray-500 dark:text-zinc-200 text-xs font-bold uppercase mb-0 flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            Any Time ({{ groupedTasks[getDateKey(day)]?.anytime?.length || 0 }})
                                        </div>
                                        <div class="space-y-1 p-4">
                                            <!-- Empty State with Hover Interaction -->
                                            <div v-if="!groupedTasks[getDateKey(day)]?.anytime?.length" 
                                                 class="group/empty min-h-[40px] flex items-center justify-center text-xs rounded-xl border border-transparent hover:border-gray-200 dark:hover:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition cursor-pointer"
                                                 @click="quickAddTask(day, 'anytime')">
                                                <span class="text-gray-400 dark:text-gray-500 group-hover/empty:hidden">No plans - Any time today works</span>
                                                <span class="hidden group-hover/empty:flex items-center gap-1 font-bold text-gray-500 dark:text-gray-300">Click to add <span class="text-lg leading-none">+</span></span>
                                            </div>

                                            <div v-for="task in groupedTasks[getDateKey(day)]?.anytime" :key="task.id" 
                                                 draggable="true" @dragend="handleDragEnd" @dragstart="handleInboxDragStart($event, task)"
                                                 @click="openEditModal(task)" class="flex items-center gap-3 p-2 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-xl cursor-move select-none shadow-sm">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-lg shrink-0"
                                                     :class="[getTaskColorObj(task)?.bg && getTaskColorObj(task).bg !== 'bg-[custom]' ? getTaskColorObj(task).bg : 'bg-white', {'opacity-50 grayscale': task.is_completed}, getTaskColorObj(task)?.text || '']"
                                                     :style="getTaskColorObj(task)?.style || {}">
                                                    {{ task.icon || '📌' }}
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-bold text-sm truncate dark:text-gray-200" :class="{'line-through text-gray-400': task.is_completed}">{{ task.title }}</p>
                                                    <p class="text-xs text-gray-400">{{ formatDuration(task) }}</p>
                                                </div>
                                                <button @click.stop="toggleTaskCompletion(task)" class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition flex-shrink-0" :class="task.is_completed ? 'bg-gray-500 border-gray-500 text-white' : 'border-gray-200 hover:border-gray-400'">
                                                    <svg v-if="task.is_completed" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                </button>
                                            </div>
                                            <div v-if="groupedTasks[getDateKey(day)]?.anytime?.length"
                                                 class="absolute bottom-0 left-1/2 transform -translate-x-1/2 translate-y-1/2 z-10 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                <button @click="quickAddTask(day, 'anytime')"
                                                        class="w-6 h-6 rounded-full bg-white border border-gray-200 flex items-center justify-center shadow-sm hover:scale-110 hover:border-purple-300 transition text-gray-400 hover:text-purple-600" title="Add another task">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm group hover:shadow-md transition relative"
                                         @dragover.prevent="handleDragOver($event)" @drop="onDropPlanner($event, day, 'morning')">
                                        <div class="bg-orange-50 dark:bg-orange-900/60 px-4 py-3 rounded-t-2xl text-orange-600 dark:text-orange-100 text-xs font-bold uppercase mb-0 flex justify-between items-center">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19h18M5 19a7 7 0 1114 0M12 9V5m-7.07 3.07l2.82 2.82M21.07 8.07l-2.82 2.82"></path></svg>
                                                <span>Morning ({{ groupedTasks[getDateKey(day)]?.morning?.length || 0 }})</span>
                                            </div>
                                        </div>
                                        <div class="space-y-1 p-4">
                                            <!-- Empty State with Hover Interaction -->
                                            <div v-if="!groupedTasks[getDateKey(day)]?.morning?.length" 
                                                 class="group/empty min-h-[40px] flex items-center justify-center text-xs rounded-xl border border-transparent hover:border-orange-200 dark:hover:border-orange-800 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition cursor-pointer"
                                                 @click="quickAddTask(day, 'morning')">
                                                <span class="text-gray-400 dark:text-gray-500 group-hover/empty:hidden">No morning plans</span>
                                                <span class="hidden group-hover/empty:flex items-center gap-1 font-bold text-gray-500 dark:text-gray-300">Click to add <span class="text-lg leading-none">+</span></span>
                                            </div>

                                            <div v-for="task in groupedTasks[getDateKey(day)]?.morning" :key="task.id" 
                                                 draggable="true" @dragend="handleDragEnd" @dragstart="handleInboxDragStart($event, task)"
                                                 @click="openEditModal(task)" class="flex items-center gap-3 p-2 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-xl cursor-move select-none shadow-sm">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-lg shrink-0"
                                                     :class="[getTaskColorObj(task)?.bg && getTaskColorObj(task).bg !== 'bg-[custom]' ? getTaskColorObj(task).bg : 'bg-white', {'opacity-50 grayscale': task.is_completed}, getTaskColorObj(task)?.text || '']"
                                                     :style="getTaskColorObj(task)?.style || {}">
                                                    {{ task.icon || '🌅' }}
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-bold text-sm truncate dark:text-gray-200" :class="{'line-through text-gray-400': task.is_completed}">{{ task.title }}</p>
                                                    <p class="text-xs text-gray-400">
                                                        {{ (task.time_of_day === 'at_time' || !task.time_of_day) ? `${FormatTimeSimple(task.start_time)} - ${FormatTimeSimple(task.end_time)}` : formatDuration(task) }}
                                                    </p>
                                                </div>
                                                <button @click.stop="toggleTaskCompletion(task)" class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition flex-shrink-0" :class="task.is_completed ? 'bg-gray-500 border-gray-500 text-white' : 'border-gray-200 hover:border-gray-400'">
                                                    <svg v-if="task.is_completed" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                </button>
                                            </div>
                                            <div v-if="groupedTasks[getDateKey(day)]?.morning?.length"
                                                 class="absolute bottom-0 left-1/2 transform -translate-x-1/2 translate-y-1/2 z-10 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                <button @click="quickAddTask(day, 'morning')"
                                                        class="w-6 h-6 rounded-full bg-white border border-gray-200 flex items-center justify-center shadow-sm hover:scale-110 hover:border-orange-300 transition text-gray-400 hover:text-orange-600" title="Add another task">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm group hover:shadow-md transition relative"
                                         @dragover.prevent="handleDragOver($event)" @drop="onDropPlanner($event, day, 'day')">
                                        <div class="bg-blue-50 dark:bg-sky-900/60 px-4 py-3 rounded-t-2xl text-blue-600 dark:text-sky-100 text-xs font-bold uppercase mb-0 flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                            Day ({{ groupedTasks[getDateKey(day)]?.day?.length || 0 }})
                                        </div>
                                        <div class="space-y-1 p-4">
                                            <!-- Empty State with Hover Interaction -->
                                            <div v-if="!groupedTasks[getDateKey(day)]?.day?.length" 
                                                 class="group/empty min-h-[40px] flex items-center justify-center text-xs rounded-xl border border-transparent hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition cursor-pointer"
                                                 @click="quickAddTask(day, 'day')">
                                                <span class="text-gray-400 dark:text-gray-500 group-hover/empty:hidden">No day plans</span>
                                                <span class="hidden group-hover/empty:flex items-center gap-1 font-bold text-gray-500 dark:text-gray-300">Click to add <span class="text-lg leading-none">+</span></span>
                                            </div>

                                            <div v-for="task in groupedTasks[getDateKey(day)]?.day" :key="task.id" 
                                                 draggable="true" @dragend="handleDragEnd" @dragstart="handleInboxDragStart($event, task)"
                                                 @click="openEditModal(task)" class="flex items-center gap-3 p-2 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-xl cursor-move select-none shadow-sm">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-lg shrink-0"
                                                     :class="[getTaskColorObj(task)?.bg && getTaskColorObj(task).bg !== 'bg-[custom]' ? getTaskColorObj(task).bg : 'bg-white', {'opacity-50 grayscale': task.is_completed}, getTaskColorObj(task)?.text || '']"
                                                     :style="getTaskColorObj(task)?.style || {}">
                                                    {{ task.icon || '☀️' }}
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-bold text-sm truncate dark:text-gray-200" :class="{'line-through text-gray-400': task.is_completed}">{{ task.title }}</p>
                                                    <p class="text-xs text-gray-400">
                                                        {{ (task.time_of_day === 'at_time' || !task.time_of_day) ? `${FormatTimeSimple(task.start_time)} - ${FormatTimeSimple(task.end_time)}` : formatDuration(task) }}
                                                    </p>
                                                </div>
                                                <button @click.stop="toggleTaskCompletion(task)" class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition flex-shrink-0" :class="task.is_completed ? 'bg-gray-500 border-gray-500 text-white' : 'border-gray-200 hover:border-gray-400'">
                                                    <svg v-if="task.is_completed" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                </button>
                                            </div>
                                            <div v-if="groupedTasks[getDateKey(day)]?.day?.length"
                                                 class="absolute bottom-0 left-1/2 transform -translate-x-1/2 translate-y-1/2 z-10 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                <button @click="quickAddTask(day, 'day')"
                                                        class="w-6 h-6 rounded-full bg-white border border-gray-200 flex items-center justify-center shadow-sm hover:scale-110 hover:border-blue-300 transition text-gray-400 hover:text-blue-600" title="Add another task">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm group hover:shadow-md transition relative"
                                         @dragover.prevent="handleDragOver($event)" @drop="onDropPlanner($event, day, 'evening')">
                                        <div class="bg-fuchsia-50 dark:bg-fuchsia-900/60 px-4 py-3 rounded-t-2xl text-fuchsia-600 dark:text-fuchsia-100 text-xs font-bold uppercase mb-0 flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                                            Evening ({{ groupedTasks[getDateKey(day)]?.evening?.length || 0 }})
                                        </div>
                                        <div class="space-y-1 p-4">
                                            <!-- Empty State with Hover Interaction -->
                                            <div v-if="!groupedTasks[getDateKey(day)]?.evening?.length" 
                                                 class="group/empty min-h-[40px] flex items-center justify-center text-xs rounded-xl border border-transparent hover:border-fuchsia-200 dark:hover:border-fuchsia-800 hover:bg-fuchsia-50 dark:hover:bg-fuchsia-900/20 transition cursor-pointer"
                                                 @click="quickAddTask(day, 'evening')">
                                                <span class="text-gray-400 dark:text-gray-500 group-hover/empty:hidden">No evening plans</span>
                                                <span class="hidden group-hover/empty:flex items-center gap-1 font-bold text-gray-500 dark:text-gray-300">Click to add <span class="text-lg leading-none">+</span></span>
                                            </div>

                                            <div v-for="task in groupedTasks[getDateKey(day)]?.evening" :key="task.id" 
                                                 draggable="true" @dragend="handleDragEnd" @dragstart="handleInboxDragStart($event, task)"
                                                 @click="openEditModal(task)" class="flex items-center gap-3 p-2 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-xl cursor-move select-none shadow-sm">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-lg shrink-0"
                                                     :class="[getTaskColorObj(task)?.bg && getTaskColorObj(task).bg !== 'bg-[custom]' ? getTaskColorObj(task).bg : 'bg-white', {'opacity-50 grayscale': task.is_completed}, getTaskColorObj(task)?.text || '']"
                                                     :style="getTaskColorObj(task)?.style || {}">
                                                    {{ task.icon || '🌙' }}
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-bold text-sm truncate dark:text-gray-200" :class="{'line-through text-gray-400': task.is_completed}">{{ task.title }}</p>
                                                    <p class="text-xs text-gray-400">
                                                        {{ (task.time_of_day === 'at_time' || !task.time_of_day) ? `${FormatTimeSimple(task.start_time)} - ${FormatTimeSimple(task.end_time)}` : formatDuration(task) }}
                                                    </p>
                                                </div>
                                                <button @click.stop="toggleTaskCompletion(task)" class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition flex-shrink-0" :class="task.is_completed ? 'bg-gray-500 border-gray-500 text-white' : 'border-gray-200 hover:border-gray-400'">
                                                    <svg v-if="task.is_completed" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                </button>
                                            </div>
                                            <div v-if="groupedTasks[getDateKey(day)]?.evening?.length"
                                                 class="absolute bottom-0 left-1/2 transform -translate-x-1/2 translate-y-1/2 z-10 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                <button @click="quickAddTask(day, 'evening')"
                                                        class="w-6 h-6 rounded-full bg-white border border-gray-200 flex items-center justify-center shadow-sm hover:scale-110 hover:border-fuchsia-300 transition text-gray-400 hover:text-fuchsia-600" title="Add another task">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>


                                </div>
                            </div>
                        </div>

                        <!-- Hybrid Day/Week View (Timed) -->
                        <!-- Hybrid Day/Week View (Timed) -->
                        <div v-else class="flex-1 flex flex-col h-full overflow-hidden bg-white">
                            <!-- Headers -->
                            <div class="flex flex-col border-b border-gray-100 bg-white z-20">
                                <!-- Row 1: Dates -->
                                <div class="flex border-b border-gray-100">
                                    <div class="w-16 flex-shrink-0 border-r border-gray-100"></div> <!-- Axis Spacer -->
                                    <div v-for="day in daysToDisplay" :key="day.toISOString()" class="flex-1 min-w-[150px] border-r border-gray-100 p-4 text-center">
                                        <h3 class="font-bold text-gray-400 text-xs uppercase">{{ day.toLocaleDateString('en-US', { weekday: 'short' }) }}</h3>
                                        <div class="text-2xl font-serif font-bold" :class="{'text-white bg-purple-600 rounded-full w-10 h-10 flex items-center justify-center mx-auto mt-1': day.toDateString() === new Date().toDateString()}">
                                            {{ day.getDate() }}
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Row 2: All Day / Untimed -->
                                <div class="flex border-b border-gray-100 min-h-[60px]">
                                    <div class="w-16 flex-shrink-0 border-r border-gray-100 p-2 flex flex-col items-center justify-center bg-gray-50">
                                        <div class="w-6 h-6 rounded-full bg-gray-200 text-gray-400 flex items-center justify-center text-xs" title="Untimed">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </div>
                                    </div>
                                    <div v-for="day in daysToDisplay" :key="'allday-'+day.toISOString()" 
                                         class="flex-1 min-w-[150px] border-r border-gray-100 p-1 space-y-1 bg-gray-50/10 hover:bg-gray-50/50 transition"
                                         @dragover.prevent="handleDragOver($event)"
                                         @drop="onDropToAllDay($event, day)">
                                        
                                        <!-- Combine all untimed tasks for the day -->
                                        <template v-for="(task, index) in [
                                            ...(groupedTasks[getDateKey(day)]?.morning || []),
                                            ...(groupedTasks[getDateKey(day)]?.day || []),
                                            ...(groupedTasks[getDateKey(day)]?.evening || []),
                                            ...(groupedTasks[getDateKey(day)]?.anytime || [])
                                        ].filter(t => t.time_of_day !== 'at_time').slice(0, 3)" :key="task.id">
                                            <div class="text-[10px] font-bold px-2 py-1 rounded flex items-center gap-1 justify-center truncate group cursor-move select-none transition"
                                                :class="[getTaskColorObj(task) ? (getTaskColorObj(task).bg === 'bg-[custom]' ? 'bg-[custom]' : getTaskColorObj(task).bg) : 'bg-gray-100 text-gray-700', getTaskColorObj(task)?.text || '', {'opacity-50 grayscale line-through': task.is_completed}]"
                                                :style="getTaskColorObj(task)?.style || {}"
                                                draggable="true" @dragend="handleDragEnd" @dragstart="handleInboxDragStart($event, task)"
                                                @click="openEditModal(task)"
                                            >
                                                <!-- Time of Day Icon -->
                                                <span v-if="task.time_of_day && task.time_of_day !== 'all_day'" class="mr-1 flex-shrink-0 w-5 h-5 rounded-full bg-black/5 flex items-center justify-center" :title="task.time_of_day">
                                                    <svg v-if="task.time_of_day === 'morning'" class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                                    <svg v-else-if="task.time_of_day === 'day'" class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                                    <svg v-else-if="task.time_of_day === 'evening'" class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                                                    <svg v-else-if="task.time_of_day === 'anytime'" class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                </span>
                                                
                                                <span class="truncate">{{ task.icon }} {{ task.title }}</span>

                                                <button @click.stop="toggleTaskCompletion(task)" class="w-3 h-3 rounded-full border border-current flex items-center justify-center ml-auto flex-shrink-0 hover:opacity-80">
                                                    <div v-if="task.is_completed" class="w-1.5 h-1.5 bg-current rounded-full"></div>
                                                </button>
                                            </div>
                                        </template>
                                        
                                        <!-- More Indicator -->
                                        <div v-if="[
                                            ...(groupedTasks[getDateKey(day)]?.morning || []),
                                            ...(groupedTasks[getDateKey(day)]?.day || []),
                                            ...(groupedTasks[getDateKey(day)]?.evening || []),
                                            ...(groupedTasks[getDateKey(day)]?.anytime || [])
                                        ].filter(t => t.time_of_day !== 'at_time').length > 3" 
                                        class="text-[10px] text-gray-400 font-bold text-center hover:bg-gray-50 rounded cursor-pointer p-0.5" @click="viewMode = 'agenda'; currentDate = day">
                                            +{{ [
                                                ...(groupedTasks[getDateKey(day)]?.morning || []),
                                                ...(groupedTasks[getDateKey(day)]?.day || []),
                                                ...(groupedTasks[getDateKey(day)]?.evening || []),
                                                ...(groupedTasks[getDateKey(day)]?.anytime || [])
                                            ].filter(t => t.time_of_day !== 'at_time').length - 3 }} more
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Scrollable Timeline -->
                            <div class="flex-1 relative overflow-y-auto custom-scrollbar" ref="timelineContainer">
                                <div class="flex min-h-[2400px]"> <!-- 24h * 100px -->
                                    <!-- Time Axis -->
                                    <div class="w-16 flex-shrink-0 border-r border-gray-100 bg-white sticky left-0 z-10 flex flex-col">
                                        <div v-for="hour in 24" :key="hour" class="flex-1 border-b border-transparent relative h-[100px]">
                                            <span class="absolute -top-3 right-2 text-xs text-gray-400 font-mono">{{ (hour-1).toString().padStart(2, '0') }}:00</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Columns -->
                                    <div v-for="day in daysToDisplay" :key="day.toISOString()" class="flex-1 min-w-[150px] border-r border-gray-100 relative bg-gray-50/20">
                                        <!-- Grid Lines (Clickable & Droppable) -->
                                        <div v-for="hour in 24" :key="'line-'+hour" 
                                             class="h-[100px] border-b border-gray-100 w-full cursor-pointer hover:bg-gray-50 flex flex-col justify-start group"
                                             @click="handleTimeSlotClick(day, hour - 1)"
                                             @dragover.prevent="handleDragOver($event)"
                                             @drop="onDropTimeSlot($event, day, hour - 1)">
                                        </div>
                                        
                                        <!-- Timed Tasks -->
                                        <template v-for="task in props.tasks">
                                             <div v-if="task.start_time && new Date(task.start_time).toDateString() === day.toDateString() && task.time_of_day === 'at_time'"
                                                :key="task.id"
                                                class="absolute left-1 right-1 rounded-md px-2 py-1 text-xs border-l-4 overflow-hidden cursor-move hover:shadow-lg transition z-10 bg-white/90 shadow-sm border-gray-200"
                                                :style="getEventStyle(task)"
                                                :class="{'opacity-50 line-through': task.is_completed}"
                                                draggable="true" @dragend="handleDragEnd" @dragstart="handleInboxDragStart($event, task)"
                                                @click="openEditModal(task)"
                                             >
                                                <div class="flex items-start justify-between">
                                                    <div class="font-bold truncate text-gray-800">{{ task.title }}</div>
                                                    <button @click.stop="toggleTaskCompletion(task)" class="w-3 h-3 rounded-full border border-gray-400 flex-shrink-0 hover:bg-gray-100">
                                                         <div v-if="task.is_completed" class="w-full h-full bg-gray-500 rounded-full"></div>
                                                    </button>
                                                </div>
                                                <div class="text-[10px] text-gray-500 mt-1">{{ FormatTimeSimple(task.start_time) }}</div>
                                             </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Me / Statistics View -->
                    <template v-else>
                        <div v-show="activeTab === 'me'" class="w-full flex-1 p-6 relative min-h-[600px] overflow-y-auto block bg-white">
                            <h3 class="font-serif font-bold text-2xl mb-6 text-gray-900">Your Mood History 📊</h3>
                            


                            <div v-if="!props.moods || props.moods.length === 0" class="flex flex-col items-center justify-center h-64 text-gray-400">
                                <div class="text-6xl mb-4">😶</div>
                                <p class="font-bold">No moods tracked yet.</p>
                                <p class="text-sm">Click "Track mood" to start!</p>
                            </div>

                            <div v-else class="space-y-4">
                                <div v-for="mood in props.moods" :key="mood.id" class="flex items-center gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
                                    <div class="w-12 h-12 flex items-center justify-center text-3xl bg-white rounded-full shadow-sm">
                                        {{ ['😢', '😕', '😐', '🙂', '🤩'][mood.rating - 1] }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-gray-900">
                                            {{ ['Struggling', 'Not Great', 'Okay', 'Good', 'Amazing'][mood.rating - 1] }}
                                        </div>
                                        <div class="text-xs text-gray-500 font-mono">
                                            {{ new Date(mood.created_at).toLocaleString() }}
                                        </div>
                                    </div>
                                    <div class="ml-auto w-24 h-2 rounded-full bg-gray-200 overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-red-400 to-green-400" :style="{ width: `${mood.rating * 20}%` }"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Integrations Section -->
                            <div class="mt-8 border-t border-gray-100 pt-6">
                                <h3 class="font-bold text-lg mb-4">Integrations</h3>
                                <a href="/auth/google" class="flex items-center gap-3 bg-white border-2 border-gray-200 rounded-xl p-4 hover:border-black transition group">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a5/Google_Calendar_icon_%282020%29.svg/1024px-Google_Calendar_icon_%282020%29.svg.png" class="w-8 h-8" alt="Google Calendar">
                                    <div class="text-left">
                                        <div class="font-bold text-gray-900 group-hover:text-black">Connect Google Calendar</div>
                                        <div class="text-xs text-gray-500">Sync events to your timeline</div>
                                    </div>
                                    <div class="ml-auto">
                                        ➤
                                    </div>
                                </a>
                            </div>
                        </div>
                            

                    </template>
                </div>
            </div>
        </div>

        <!-- Add/Edit Task Modal -->
        <DialogModal :show="isModalOpen" @close="{ isModalOpen = false; editingTask = null; form.reset(); }">
            <template #title>
                <div class="flex items-start justify-between w-full">
                    <span class="text-gray-900 dark:text-white font-bold text-lg">{{ editingTask ? 'Edit Task' : 'Plan a New Task' }}</span>
                    <button @click="{ isModalOpen = false; editingTask = null; form.reset(); }" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </template>

            <template #content>
                 <div class="space-y-4 bg-gray-50 dark:bg-gray-900 -m-6 p-6 min-h-[500px]">
                    
                    <!-- Title & Icon Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 shadow-sm flex items-center gap-3">
                         <div class="relative group">
                             <div class="w-10 h-10 rounded-full flex items-center justify-center text-xl cursor-pointer hover:opacity-80 transition"
                                  :class="[selectedColorObj?.bg && selectedColorObj.bg !== 'bg-[custom]' ? selectedColorObj.bg : 'bg-gray-100 dark:bg-gray-700', selectedColorObj?.text || '']"
                                  :style="selectedColorObj?.style || {}">
                                 {{ form.icon || '✨' }}
                             </div>
                         </div>
                         <input 
                            v-model="form.title"
                            type="text"
                            placeholder="Task title"
                            class="flex-1 text-xl font-bold border-none focus:ring-0 placeholder-gray-300 dark:placeholder-gray-500 bg-transparent dark:text-white"
                            autofocus
                         />
                         
                         <!-- Appearance Trigger -->
                         <div class="relative" ref="iconPickerWrapperRef">
                             <div @click="isIconPickerOpen = !isIconPickerOpen" 
                                  class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600 transition text-gray-500 dark:text-gray-400">
                                 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                      :class="{'rotate-180': isIconPickerOpen}"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                             </div>

                             <!-- Appearance Picker Popover -->
                             <div v-if="isIconPickerOpen" 
                                  class="absolute right-0 top-10 z-50 w-[300px] bg-gray-900 border border-gray-700 rounded-3xl shadow-2xl p-4 flex flex-col gap-4 text-white max-h-[600px] overflow-hidden custom-scrollbar">
                                  
                                  <!-- Colors Section -->
                                  <div>
                                      <h4 class="text-xs font-bold text-gray-500 uppercase mb-2 tracking-wider flex justify-between items-center">
                                          <span>My Colors</span>
                                          <button @click="toggleCustomColor" class="text-gray-500 hover:text-white text-xs underline" v-if="showCustomColor">Back to Presets</button>
                                      </h4>
                                      
                                      <!-- Custom Color Picker UI -->
                                      <div v-if="showCustomColor" class="mb-4">
                                          <!-- Saturation/Value Box -->
                                          <div ref="satBoxRef"
                                               @mousedown="handleSatMouseDown"
                                               class="w-full h-40 rounded-lg cursor-crosshair relative mb-4"
                                               :style="{ backgroundColor: `hsl(${customColorState.h}, 100%, 50%)` }">
                                              <div class="absolute inset-0 bg-gradient-to-r from-white to-transparent"></div>
                                              <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent"></div>
                                              <div class="w-4 h-4 border-2 border-white rounded-full absolute shadow-sm"
                                                   :style="{ left: `${customColorState.s}%`, top: `${100 - customColorState.v}%`, transform: 'translate(-50%, -50%)' }"></div>
                                          </div>
                                          
                                          <!-- Hue Slider -->
                                          <div ref="hueSliderRef"
                                               @mousedown="handleHueMouseDown"
                                               class="w-full h-4 rounded-full cursor-pointer relative"
                                               style="background: linear-gradient(to right, #f00 0%, #ff0 17%, #0f0 33%, #0ff 50%, #00f 67%, #f0f 83%, #f00 100%)">
                                              <div class="w-4 h-4 border-2 border-white bg-white rounded-full absolute shadow-sm top-1/2 -translate-y-1/2"
                                                   :style="{ left: `${(customColorState.h / 360) * 100}%`, transform: 'translate(-50%, -50%)' }"></div>
                                          </div>
                                          
                                          <div class="mt-4 flex gap-2 items-center">
                                               <div class="w-8 h-8 rounded-full border border-gray-600" :style="{ backgroundColor: form.color }"></div>
                                               <input type="text" v-model="form.color" class="flex-1 bg-gray-800 border-none rounded text-white text-sm" />
                                          </div>
                                      </div>

                                      <div v-else class="flex flex-wrap gap-2">
                                          <!-- Custom Toggle Button -->
                                          <button @click="toggleCustomColor"
                                                  class="w-8 h-8 rounded-full ring-2 ring-transparent hover:ring-white transition flex items-center justify-center overflow-hidden shadow-sm transform-gpu"
                                                  title="Custom Color"
                                                  style="background: conic-gradient(from 180deg, red, yellow, lime, aqua, blue, magenta, red)">
                                               <svg class="w-4 h-4 text-white drop-shadow-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                          </button>
                                          
                                          <button v-for="color in availableColors.myColors" :key="color.name"
                                                  @click="selectAppearance('color', color)"
                                                  class="w-8 h-8 rounded-full border-2 border-transparent hover:border-white transition"
                                                  :class="[color.bg]"
                                                  :title="color.name">
                                          </button>
                                      </div>
                                  </div>

                                  <div>
                                      <h4 class="text-xs font-bold text-gray-500 uppercase mb-2 tracking-wider">Reminddo Colors</h4>
                                      <div class="flex flex-wrap gap-2">
                                          <button v-for="color in availableColors.reminddoColors" :key="color.name"
                                                  @click="selectAppearance('color', color)"
                                                  class="w-8 h-8 rounded-full border-2 border-transparent hover:border-white transition"
                                                  :class="[color.bg]"
                                                  :title="color.name">
                                          </button>
                                          <button @click="form.color = null" class="w-8 h-8 rounded-full border-2 border-gray-600 flex items-center justify-center text-gray-500 hover:text-white hover:border-white transition" title="None">
                                              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                          </button>
                                      </div>
                                  </div>

                                  <!-- Search Input -->
                                  <div class="bg-gray-800 rounded-lg flex items-center px-3 py-2">
                                      <svg class="w-4 h-4 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                      <input v-model="appearanceSearch" type="text" placeholder="Search" class="bg-transparent border-none text-sm text-white focus:ring-0 w-full placeholder-gray-500 p-0" />
                                  </div>

                                  <!-- Category Navigation -->
                                  <div class="flex items-center flex-nowrap border-b border-gray-700 pb-2 mb-2 overflow-x-auto hide-scrollbar gap-1 min-h-[40px]">
                                      <button v-for="cat in emojiCategories" :key="cat.id"
                                              @click="scrollToCategory(cat.id)"
                                              class="text-gray-500 hover:text-white transition p-1 rounded hover:bg-gray-800"
                                              :class="{'text-blue-400': activeCategory === cat.id}"
                                              :title="cat.name">
                                          <!-- Icons based on cat.icon -->
                                          <svg v-if="cat.icon === 'clock'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                          <svg v-else-if="cat.icon === 'face-smile'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                          <svg v-else-if="cat.icon === 'bug-ant'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> <!-- Placeholder for Animals -->
                                          <svg v-else-if="cat.icon === 'cake'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z"></path></svg>
                                          <svg v-else-if="cat.icon === 'truck'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> <!-- Placeholder for Travel (using Info circle as fallback or find better) -> Truck: M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path> -->
                                          <svg v-else-if="cat.icon === 'trophy'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>
                                          <svg v-else-if="cat.icon === 'light-bulb'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                                          <svg v-else-if="cat.icon === 'musical-note'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>
                                          <svg v-else-if="cat.icon === 'flag'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-8a2 2 0 012-2h10a2 2 0 012 2v8m2-2a2 2 0 00-2-2H7a2 2 0 00-2 2m0 0V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2h-1"></path></svg>
                                          <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                      </button>
                                  </div>

                                  <!-- Icons Sections -->
                                  <div class="flex-1 overflow-y-auto custom-scrollbar space-y-4 pr-1 scroll-smooth">
                                      <div v-for="category in filteredEmojiCategories" :key="category.name" :id="'emoji-category-' + category.id">
                                          <h4 class="text-xs font-bold text-gray-500 uppercase mb-2 tracking-wider">{{ category.name }}</h4>
                                          <div class="grid grid-cols-6 gap-1">
                                              <button v-for="icon in category.icons" :key="icon"
                                                      @click="selectAppearance('icon', icon)"
                                                      class="w-8 h-8 flex items-center justify-center rounded hover:bg-gray-700 transition text-xl cursor-pointer">
                                                  {{ icon }}
                                              </button>
                                          </div>
                                      </div>
                                  </div>
                             </div>
                         </div>
                    </div>

                    <!-- Details Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-3xl p-2 shadow-sm space-y-1">
                        
                        <!-- Time of Day -->
                        <div class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-2xl transition cursor-pointer">
                            <span class="font-bold text-gray-700 dark:text-gray-200 text-sm">Time of day</span>
                            <div class="relative" ref="timePickerWrapperRef">
                                <button type="button" 
                                        @click="isTimePickerOpen = !isTimePickerOpen"
                                        class="flex items-center gap-2 bg-gray-100 dark:bg-gray-700 rounded-full py-1.5 pl-4 pr-3 text-sm font-medium text-gray-700 dark:text-white hover:bg-gray-200 dark:hover:bg-gray-600 transition w-full justify-between group">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getIconForTimeOfDay(form.time_of_day).icon"></path>
                                        </svg>
                                        <span>{{ getIconForTimeOfDay(form.time_of_day).label }}</span>
                                    </div>
                                    <svg class="h-4 w-4 text-gray-500 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" :class="{'rotate-180': isTimePickerOpen}"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>

                                <!-- Dropdown Menu -->
                                <div v-if="isTimePickerOpen" class="absolute right-0 top-10 z-50 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-xl overflow-hidden">
                                     <div v-for="(group, idx) in timeOfDayOptions" :key="idx">
                                         <div class="px-3 py-2 text-xs font-bold text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/50">
                                             {{ group.label }}
                                         </div>
                                         <div>
                                             <button v-for="item in group.items" :key="item.value"
                                                     type="button"
                                                     @click="form.time_of_day = item.value; isTimePickerOpen = false"
                                                     class="w-full text-left px-3 py-2 text-sm flex items-center gap-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                                                     :class="{'text-blue-600 dark:text-blue-400 font-bold': form.time_of_day === item.value, 'text-gray-700 dark:text-gray-200': form.time_of_day !== item.value}">
                                                 <svg class="w-4 h-4" :class="{'text-blue-500': form.time_of_day === item.value, 'text-gray-400': form.time_of_day !== item.value}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon"></path>
                                                 </svg>
                                                 {{ item.label }}
                                                 <span v-if="form.time_of_day === item.value" class="ml-auto text-blue-500">
                                                     <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                 </span>
                                             </button>
                                         </div>
                                     </div>
                                </div>
                            </div>
                        </div>

                        <!-- Condition A: AT TIME (Specific Start and End) -->
                        <template v-if="form.time_of_day === 'at_time'">
                             <!-- Starts -->
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-2xl transition">
                                <span class="font-bold text-gray-700 dark:text-gray-200 text-sm">Starts</span>
                                <div class="flex gap-2">
                                    <VDatePicker v-model="form.start_date_display" mode="date" :is-dark="isDark" :masks="{ input: 'YYYY-MM-DD' }">
                                        <template #default="{ togglePopover, inputValue }">
                                            <button type="button" class="bg-gray-100 dark:bg-gray-700 border-none rounded-xl py-1.5 px-3 text-sm font-medium text-gray-700 dark:text-white w-32 focus:ring-black dark:focus:ring-white flex items-center justify-between" @click="togglePopover">
                                                {{ inputValue }}
                                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </button>
                                        </template>
                                    </VDatePicker>
                                    <input type="time" v-model="form.start_time_display" class="bg-gray-100 dark:bg-gray-700 border-none rounded-xl py-1.5 px-3 text-sm font-medium text-gray-700 dark:text-white w-24 focus:ring-black dark:focus:ring-white" />
                                </div>
                            </div>

                            <!-- Ends -->
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-2xl transition">
                                <span class="font-bold text-gray-700 dark:text-gray-200 text-sm">Ends</span>
                                <div class="flex gap-2">
                                    <VDatePicker v-model="form.end_date_display" mode="date" :is-dark="isDark" :masks="{ input: 'YYYY-MM-DD' }">
                                        <template #default="{ togglePopover, inputValue }">
                                            <button type="button" class="bg-gray-100 dark:bg-gray-700 border-none rounded-xl py-1.5 px-3 text-sm font-medium text-gray-700 dark:text-white w-32 focus:ring-black dark:focus:ring-white flex items-center justify-between" @click="togglePopover">
                                                {{ inputValue }}
                                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </button>
                                        </template>
                                    </VDatePicker>
                                    <input type="time" v-model="form.end_time_display" class="bg-gray-100 dark:bg-gray-700 border-none rounded-xl py-1.5 px-3 text-sm font-medium text-gray-700 dark:text-white w-24 focus:ring-black dark:focus:ring-white" />
                                </div>
                            </div>
                        </template>

                        <!-- Condition B: ALL DAY (Date only, no time) -->
                        <template v-else-if="form.time_of_day === 'all_day'">
                             <!-- Starts -->
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-2xl transition">
                                <span class="font-bold text-gray-700 text-sm">Starts</span>
                                <div class="flex gap-2">
                                    <VDatePicker v-model="form.start_date_display" mode="date" :is-dark="isDark" :masks="{ input: 'YYYY-MM-DD' }">
                                        <template #default="{ togglePopover, inputValue }">
                                            <button type="button" class="bg-gray-100 dark:bg-gray-700 border-none rounded-xl py-1.5 px-3 text-sm font-medium text-gray-700 dark:text-white w-32 focus:ring-black dark:focus:ring-white flex items-center justify-between" @click="togglePopover">
                                                {{ inputValue }}
                                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </button>
                                        </template>
                                    </VDatePicker>
                                </div>
                            </div>

                            <!-- Ends -->
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-2xl transition">
                                <span class="font-bold text-gray-700 text-sm">Ends</span>
                                <div class="flex gap-2">
                                    <VDatePicker v-model="form.end_date_display" mode="date" :is-dark="isDark" :masks="{ input: 'YYYY-MM-DD' }">
                                        <template #default="{ togglePopover, inputValue }">
                                            <button type="button" class="bg-gray-100 dark:bg-gray-700 border-none rounded-xl py-1.5 px-3 text-sm font-medium text-gray-700 dark:text-white w-32 focus:ring-black dark:focus:ring-white flex items-center justify-between" @click="togglePopover">
                                                {{ inputValue }}
                                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </button>
                                        </template>
                                    </VDatePicker>
                                </div>
                            </div>
                        </template>

                        <!-- Condition C: General (Morning/Day/Evening/Anytime) -->
                        <template v-else>
                            <!-- Date -->
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-2xl transition">
                                <span class="font-bold text-gray-700 dark:text-gray-200 text-sm">Date</span>
                                <div class="flex gap-2">
                                    <VDatePicker v-model="form.start_date_display" mode="date" :is-dark="isDark" :masks="{ input: 'YYYY-MM-DD' }">
                                        <template #default="{ togglePopover, inputValue }">
                                            <button type="button" class="bg-gray-100 dark:bg-gray-700 border-none rounded-xl py-1.5 px-3 text-sm font-medium text-gray-700 dark:text-white w-32 focus:ring-black dark:focus:ring-white flex items-center justify-between" @click="togglePopover">
                                                {{ inputValue }}
                                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </button>
                                        </template>
                                    </VDatePicker>
                                </div>
                            </div>

                            <!-- Duration -->
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-2xl transition">
                                <span class="font-bold text-gray-700 dark:text-gray-200 text-sm">Duration (min)</span>
                                <div class="flex gap-2">
                                    <input type="number" v-model="form.duration" class="bg-gray-100 dark:bg-gray-700 border-none rounded-xl py-1.5 px-3 text-sm font-medium text-gray-700 dark:text-white w-24 text-center focus:ring-black dark:focus:ring-white" placeholder="30" />
                                </div>
                            </div>
                        </template>

                        <!-- Repeat -->
                        <div class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-2xl transition cursor-pointer">
                            <span class="font-bold text-gray-700 dark:text-gray-200 text-sm">Repeat</span>
                            <div class="relative" ref="repeatPickerWrapperRef">
                                <button type="button" 
                                        @click="isRepeatPickerOpen = !isRepeatPickerOpen"
                                        class="flex items-center gap-2 bg-gray-100 dark:bg-gray-700 rounded-full py-1.5 pl-4 pr-3 text-sm font-medium text-gray-700 dark:text-white hover:bg-gray-200 dark:hover:bg-gray-600 transition min-w-[140px] justify-between group">
                                    <span>{{ repeatOptions.find(o => o.value === form.recurrence_pattern)?.label || 'No repeat' }}</span>
                                    <svg class="h-4 w-4 text-gray-500 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" :class="{'rotate-180': isRepeatPickerOpen}"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>

                                <!-- Dropdown Menu -->
                                <div v-if="isRepeatPickerOpen" class="absolute right-0 top-10 z-50 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-xl overflow-hidden">
                                     <button v-for="opt in repeatOptions" :key="opt.value"
                                             type="button"
                                             @click="form.recurrence_pattern = opt.value; isRepeatPickerOpen = false"
                                             class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center justify-between"
                                             :class="{'text-blue-600 dark:text-blue-400 font-bold': form.recurrence_pattern === opt.value, 'text-gray-700 dark:text-gray-200': form.recurrence_pattern !== opt.value}">
                                         {{ opt.label }}
                                         <svg v-if="form.recurrence_pattern === opt.value" class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                     </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-tasks Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 shadow-sm space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-gray-700 dark:text-gray-200 text-sm">Sub-tasks</span>
                            <button type="button" class="flex items-center gap-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-full px-3 py-1 text-xs font-bold text-gray-600 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition shadow-sm uppercase tracking-wide">
                                Suggest Breakdown 
                                <span class="text-purple-500">✨</span>
                            </button>
                        </div>
                        
                        <div class="space-y-2">
                             <div v-for="(item, index) in form.checklist_items" :key="index" class="flex gap-2 items-center group">
                                 <div class="w-5 h-5 rounded-full border-2 border-gray-300 dark:border-gray-600"></div>
                                 <input 
                                     v-model="item.title"
                                     class="flex-1 text-sm border-none focus:ring-0 p-0 text-gray-700 dark:text-white placeholder-gray-300 dark:placeholder-gray-500 bg-transparent"
                                     placeholder="Sub-task name..."
                                 />
                                 <button type="button" @click="removeChecklistItem(index)" class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-500 transition">
                                     <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                 </button>
                             </div>
                             
                             <!-- Add New Item Input -->
                             <div class="flex gap-3 items-center mt-2 group cursor-text" @click="$refs.newSubtaskInput.focus()">
                                 <div class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 group-hover:bg-gray-300 transition">
                                     <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                 </div>
                                 <input 
                                     ref="newSubtaskInput"
                                     @keydown.enter.prevent="addChecklistItem"
                                     class="flex-1 text-sm border-none focus:ring-0 p-0 text-gray-700 dark:text-white placeholder-gray-400 bg-transparent"
                                     placeholder="Add sub-task"
                                 />
                             </div>
                        </div>
                    </div>

                    <!-- Notes Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 shadow-sm space-y-2">
                        <span class="font-bold text-gray-700 dark:text-gray-200 text-sm">Notes</span>
                        <textarea 
                            v-model="form.notes"
                            class="w-full bg-gray-50 dark:bg-gray-700 border-none rounded-xl p-3 text-sm text-gray-700 dark:text-white placeholder-gray-400 focus:ring-0 resize-none h-24"
                            placeholder="Add notes..."
                        ></textarea>
                    </div>

                </div>
            </template>

            <template #footer>
                <div class="flex items-center w-full">
                     <!-- Delete Button (Left aligned) -->
                    <button 
                        v-if="editingTask"
                        type="button"
                        @click="deleteTask(editingTask.id)"
                        class="p-2 text-red-500 hover:text-red-700 transition rounded-full hover:bg-red-50"
                        title="Delete Task"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>

                    <div class="ml-auto flex items-center">
                        <button 
                            v-if="editingTask"
                            type="button"
                            @click="moveTaskToInbox"
                            class="mr-3 inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-full font-bold text-xs text-gray-700 dark:text-white uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                        >
                            Move to list
                        </button>

                        <PrimaryButton
                            class="bg-black hover:bg-gray-800 dark:bg-white dark:text-black dark:hover:bg-gray-200"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                            @click="submitTask"
                        >
                            {{ editingTask ? 'Save Changes' : 'Add to Plan' }}
                        </PrimaryButton>
                    </div>
                </div>
            </template>
        </DialogModal>

        <!-- Focus Timer Overlay -->
        <FocusTimer 
            :show="isFocusMode" 
            :task="activeFocusTask" 
            @close="closeFocusMode"
            @complete="closeFocusMode" 
        />
        <!-- Floating Bottom Bar and Chat Interface -->
        <div class="fixed bottom-6 right-6 z-50 pointer-events-none flex flex-col items-end gap-4 overflow-visible">
             
             <!-- Chat Window (Floating Bottom Right) -->
             <div v-if="isChatOpen" class="pointer-events-auto w-[90vw] md:w-[450px] bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl rounded-3xl shadow-2xl border border-gray-200 dark:border-gray-700 mb-0 overflow-hidden flex flex-col h-[70vh] md:h-[600px] transition-all origin-bottom-right">
                 
                 <!-- Chat History Sidebar (Overlay) -->
                 <div 
                    class="absolute inset-y-0 left-0 bg-white dark:bg-gray-800 z-20 flex flex-col transition-all duration-300 ease-in-out border-r border-gray-100 dark:border-gray-700 shadow-xl"
                    :class="isHistoryOpen ? 'w-64 opacity-100 translate-x-0' : 'w-64 opacity-0 -translate-x-full pointer-events-none'"
                 >
                     <div class="p-6 flex items-center justify-between border-b border-gray-100">
                         <h3 class="font-bold text-xl text-gray-900">Chat history</h3>
                         <button @click="startNewChat" class="p-2 hover:bg-gray-100 rounded-lg text-gray-500 transition" title="New Chat">
                             <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                         </button>
                     </div>
                     <div class="flex-1 overflow-y-auto p-4 space-y-2">
                         <div v-if="chatHistory.length === 0" class="text-sm text-gray-400 p-4 text-center italic">No history yet</div>
                         <button 
                            v-for="chat in chatHistory" 
                            :key="chat.id"
                            @click="loadChat(chat)" 
                            class="w-full text-left p-3 rounded-xl text-sm font-medium transition group"
                            :class="currentChatId === chat.id ? 'bg-gray-100 text-gray-900' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'"
                        >
                            <div class="truncate">{{ chat.title }}</div>
                            <div class="text-[10px] text-gray-400 mt-1">{{ new Date(chat.id).toLocaleDateString() }}</div>
                         </button>
                     </div>
                 </div>

                 <!-- Main Chat Area -->
                 <div class="flex-1 flex flex-col min-w-0 min-h-0">
                     <!-- Chat Header -->
                     <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-white z-20 relative shadow-sm">
                         <!-- Menu Toggle -->
                         <button @click="isHistoryOpen = !isHistoryOpen" class="p-2 hover:bg-gray-100 rounded-xl text-gray-600 transition" title="Toggle History">
                             <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                         </button>
                         

                         
                         <button @click="isChatOpen = false" class="p-2 hover:bg-gray-100 rounded-xl text-gray-400 hover:text-black transition" title="Close">
                             <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                         </button>
                     </div>

                     <div ref="chatContainerRef" class="flex-1 overflow-y-scroll p-4 pb-32 space-y-4 bg-white/50 flex flex-col custom-scrollbar">
                         <!-- Empty State -->
                         <div v-if="chatMessages.length === 0" class="flex-1 flex flex-col items-center justify-center text-center opacity-80 pb-4">
                             <div class="w-32 h-32 mb-4 relative">
                                 <!-- Simple CSS illustration of "At your service" face/hands using emojis/shapes or just the text if image unavailable -->
                                 <div class="text-[80px] leading-none">👐</div>
                                 <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-4xl">👀</div>
                             </div>
                             <h3 class="text-2xl font-serif font-light text-gray-800 mb-6">At your service</h3>
                             
                             <div class="flex flex-wrap justify-center gap-2 max-w-sm">
                                 <button @click="aiPrompt='Help me prioritize my To-dos'; submitAiPrompt()" class="bg-white border border-gray-200 px-4 py-2 rounded-full text-xs text-gray-600 hover:border-gray-400 hover:bg-gray-50 transition shadow-sm">🤩 Help me prioritize my To-do's</button>
                                 <button @click="aiPrompt='Can we plan my week together?'; submitAiPrompt()" class="bg-white border border-gray-200 px-4 py-2 rounded-full text-xs text-gray-600 hover:border-gray-400 hover:bg-gray-50 transition shadow-sm">🎨 Can we plan my week together?</button>
                                 <button @click="aiPrompt='I need a good evening wind down'; submitAiPrompt()" class="bg-white border border-gray-200 px-4 py-2 rounded-full text-xs text-gray-600 hover:border-gray-400 hover:bg-gray-50 transition shadow-sm">🌙 I need a good evening wind down</button>
                                 <button @click="aiPrompt='I need to adjust my schedule'; submitAiPrompt()" class="bg-white border border-gray-200 px-4 py-2 rounded-full text-xs text-gray-600 hover:border-gray-400 hover:bg-gray-50 transition shadow-sm">🗓️ I need to adjust my schedule</button>
                                 <button @click="aiPrompt='So, Reminddo... What can you do?'; submitAiPrompt()" class="bg-white border border-gray-200 px-4 py-2 rounded-full text-xs text-gray-600 hover:border-gray-400 hover:bg-gray-50 transition shadow-sm">🧸 So, Reminddo... What can you do?</button>
                             </div>
                         </div>
                         
                         <!-- Messages -->
                         <div v-for="msg in chatMessages" :key="msg.id" class="flex flex-col gap-1" :class="msg.role === 'user' ? 'items-end' : 'items-start'">
                             <div 
                                class="px-4 py-3 rounded-2xl max-w-[85%] text-sm md:text-base leading-relaxed shadow-sm"
                                :class="msg.role === 'user' ? 'bg-black text-white rounded-br-none' : 'bg-white text-gray-800 border border-gray-100 rounded-bl-none'"
                             >
                                <span v-if="msg.role === 'ai'" class="mr-2">✨</span>
                                {{ msg.content }}
                             </div>
                         </div>

                         <!-- Proposal Card -->
                         <!-- Proposal Card -->
                         <div v-if="suggestedTasks" class="w-full max-w-sm mx-auto animate-fade-in-up mb-6">
                             <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-lg mb-4">
                                 <div class="text-sm font-bold text-gray-500 mb-3 flex items-center gap-2">
                                     <span>⚡ Proposed Schedule</span>
                                 </div>
                                 <div class="space-y-2 mb-4 border-b border-gray-100 pb-2">
                                     <div v-for="task in suggestedTasks" :key="task.title" class="flex items-center gap-3 bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                                         <div class="w-8 h-8 flex items-center justify-center shadow-sm text-lg rounded-full shrink-0 bg-white">
                                              {{ task.icon || '📌' }}
                                         </div>
                                         <div class="flex-1 min-w-0">
                                             <div class="font-bold text-gray-900 text-sm truncate">{{ task.title }}</div>
                                             <div class="text-xs text-gray-400 font-mono">{{ task.start_time ? new Date(task.start_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'Anytime' }}</div>
                                         </div>
                                     </div>
                                 </div>

                                 </div>
                             
                             <!-- Actions (Stacked & Right Aligned outside card) -->
                             <div class="flex flex-col items-end gap-2">
                                 <button @click="confirmSuggestedTasks" class="bg-black text-white font-bold py-3 px-6 rounded-full hover:bg-gray-800 transition text-sm shadow-md">
                                     Create tasks ({{ suggestedTasks.length }})
                                 </button>
                                 <button @click="cancelSuggestedTasks" class="bg-gray-100 text-gray-600 font-bold py-2 px-6 rounded-full hover:bg-gray-200 transition text-xs">
                                     Cancel the action
                                 </button>
                             </div>
                         </div>
                         
                         <!-- Loading Indicator -->
                         <div v-if="isAiLoading" class="flex justify-start">
                            <div class="bg-white border border-gray-100 px-4 py-3 rounded-2xl rounded-bl-none shadow-sm flex items-center gap-2 text-gray-500 text-sm">
                                <svg class="animate-spin h-5 w-5 text-purple-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                   <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                   <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                         </div>
                         <div ref="chatBottomRef" class="h-4"></div>
                     </div>

                     <!-- New Input Area inside View -->
                     <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-2 flex items-center gap-2 mt-auto m-4">
                        <span class="text-green-500 p-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </span>
                        <input 
                            type="text" 
                            v-model="aiPrompt" 
                            @keydown.enter="submitAiPrompt"
                            placeholder="Ask anything ..." 
                            class="flex-1 border-none focus:ring-0 text-lg placeholder-gray-400 dark:bg-transparent dark:text-white"
                            :disabled="isAiLoading"
                        />
                        <button @click="submitAiPrompt" class="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-black hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                        </button>
                     </div>
                 </div>
             </div>

             <!-- Input Bar (Hidden when chat is open) -->
             <div v-if="!isChatOpen" class="pointer-events-auto flex items-center gap-4 transition-all duration-300">
                 
                 <!-- "Ask anything" Pill Button -->
                 <button 
                    @click="isChatOpen = true"
                    class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-full shadow-xl border border-white/50 dark:border-white/10 p-2 pl-4 pr-2 flex items-center gap-3 hover:shadow-2xl hover:scale-105 transition group"
                 >
                     <span class="text-purple-500 text-lg animate-pulse">✨</span>
                     <span class="text-gray-600 dark:text-gray-300 font-medium text-sm">Ask anything ...</span>
                     <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 group-hover:bg-black group-hover:text-white flex items-center justify-center transition">
                         <svg class="w-4 h-4 text-gray-500 dark:text-white group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                     </div>
                 </button>

                 <!-- + Add Task Button -->
                 <button 
                    @click="isModalOpen = true"
                    class="w-12 h-12 bg-black text-white rounded-full shadow-xl flex items-center justify-center text-2xl font-light hover:scale-110 active:scale-95 transition"
                 >
                     <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                 </button>
             </div>
        </div>

        <!-- Mobile Bottom Navigation (Hidden just for this request visual if it conflicts, but keeping code for safety, maybe adjust z-index to be below input bar) -->
        <!-- Actually, user wants "this box" so likely replaces simple mobile nav or floats above. 
             If mobile nav is sticky, we float above it. Nav is z-50. We can be z-50 too but higher in DOM order or z-[60]. -->
        
        <!-- Mobile Bottom Navigation -->
        <div class="fixed bottom-0 left-0 w-full bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 flex justify-around items-center p-3 md:hidden z-50 text-[10px] font-bold text-gray-400 dark:text-gray-500">
            <button @click="activeTab = 'todo'" class="flex flex-col items-center gap-1" :class="{ 'text-black dark:text-white': activeTab === 'todo' }">
                <div class="w-6 h-6 border-2 rounded flex items-center justify-center" :class="{ 'border-black bg-black text-white dark:border-white dark:bg-white dark:text-black': activeTab === 'todo', 'border-gray-300 dark:border-gray-700': activeTab !== 'todo' }">✓</div>
                <span>To-Do</span>
            </button>
            <button @click="activeTab = 'today'" class="flex flex-col items-center gap-1" :class="{ 'text-black dark:text-white': activeTab === 'today' }">
                <div class="w-6 h-6 border-2 rounded flex items-center justify-center font-serif text-xs pt-0.5" :class="{ 'border-black bg-black text-white dark:border-white dark:bg-white dark:text-black': activeTab === 'today', 'border-gray-300 dark:border-gray-700': activeTab !== 'today' }">19</div>
                <span>Today</span>
            </button>
            <button @click="activeTab = 'focus'; openAiPlanner()" class="flex flex-col items-center gap-1" :class="{ 'text-black dark:text-white': activeTab === 'focus' }">
                 <div class="w-8 h-8 -mt-4 bg-purple-500 dark:bg-purple-600 rounded-full flex items-center justify-center shadow-lg text-white text-lg">▶</div>
                <span>Focus</span>
            </button>
            <button @click="activeTab = 'me'" class="flex flex-col items-center gap-1" :class="{ 'text-black dark:text-white': activeTab === 'me' }">
                <div class="w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 border-2" :class="{ 'border-black dark:border-white': activeTab === 'me', 'border-transparent': activeTab !== 'me' }"></div>
                <span>Me</span>
            </button>
        </div>
    </AppLayout>



    <div v-if="showPlanModal" class="fixed inset-0 z-[100] flex items-end md:items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-gray-800 rounded-t-3xl md:rounded-3xl w-full max-w-md shadow-2xl flex flex-col max-h-[90vh] transition-transform">
            
            <!-- Loading State -->
            <div v-if="isBuildingPlan" class="h-96 flex flex-col items-center justify-center text-center p-8">
                <div class="w-24 h-24 bg-purple-100 rounded-full flex items-center justify-center mb-6 animate-pulse">
                     <span class="text-4xl">🔮</span>
                </div>
                <h3 class="text-2xl font-serif font-bold text-gray-900 dark:text-white mb-2">Building your tasks...</h3>
                <p class="text-gray-500">Optimizing your schedule for the day.</p>
            </div>

            <!-- Selection State -->
            <div v-else class="flex flex-col h-full">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-xl font-serif font-bold dark:text-white">Pick tasks for your plan</h3>
                    <button @click="showPlanModal = false" class="text-gray-400 hover:text-black">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                    <div 
                        v-for="task in inboxTasks" 
                        :key="task.id" 
                        class="flex items-center p-4 rounded-xl border-2 cursor-pointer transition"
                        :class="selectedPlanTaskIds.includes(task.id) ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20' : 'border-gray-100 dark:border-gray-700 hover:border-purple-200 dark:hover:border-purple-800'"
                        @click="selectedPlanTaskIds.includes(task.id) ? selectedPlanTaskIds = selectedPlanTaskIds.filter(id => id !== task.id) : selectedPlanTaskIds.push(task.id)"
                    >
                        <div class="w-10 h-10 rounded-full flex items-center justify-center shadow-sm text-xl mr-4 shrink-0"
                             :class="[getTaskColorObj(task)?.bg && getTaskColorObj(task).bg !== 'bg-[custom]' ? getTaskColorObj(task).bg : 'bg-white', getTaskColorObj(task)?.text || '']"
                             :style="getTaskColorObj(task)?.style || {}">
                            {{ task.icon || '📌' }}
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-900 dark:text-white">{{ task.title }}</h4>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">To-Do</p>
                        </div>
                        <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center" :class="selectedPlanTaskIds.includes(task.id) ? 'bg-purple-500 border-purple-500' : 'border-gray-300'">
                            <svg v-if="selectedPlanTaskIds.includes(task.id)" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                    </div>
                    
                    <div v-if="inboxTasks.length === 0" class="text-center py-10 text-gray-400">
                        <p>No tasks to plan! <br> Add some to your inbox first.</p>
                    </div>
                </div>

                <div class="p-6 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 rounded-b-3xl">
                    <button 
                        @click="buildPlan" 
                        :disabled="selectedPlanTaskIds.length === 0"
                        class="w-full py-4 bg-black text-white dark:bg-white dark:text-black font-bold rounded-2xl shadow-xl hover:scale-[1.02] active:scale-95 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-between px-6"
                    >
                        <span>Add tasks ({{ selectedPlanTaskIds.length }})</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
