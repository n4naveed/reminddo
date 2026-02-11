export const timeOfDayOptions = [
    {
        label: 'Time of day',
        items: [
            { value: 'anytime', label: 'Anytime', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' }, // Clock
            { value: 'morning', label: 'Morning', icon: 'M3 19h18M5 19a7 7 0 1114 0M12 9V5m-7.07 3.07l2.82 2.82M21.07 8.07l-2.82 2.82' }, // Sunrise
            // Alternative Horizon Sun: M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'
            { value: 'day', label: 'Day', icon: 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z' }, // Sun
            { value: 'evening', label: 'Evening', icon: 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z' }, // Moon
        ]
    },
    {
        label: 'Event',
        items: [
            { value: 'at_time', label: 'At time', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' }, // Calendar
            { value: 'all_day', label: 'All day', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' }, // Clock/Circle (Reuse clock)
        ]
    }
];

export const getIconForTimeOfDay = (value) => {
    for (const group of timeOfDayOptions) {
        const found = group.items.find(i => i.value === value);
        if (found) return found;
    }
    return timeOfDayOptions[0].items[0]; // Default Anytime
};
