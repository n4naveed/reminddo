<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { route } from 'ziggy-js'; // Import route helper
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    auth: Object,
});

const activeSection = ref('account');

// Change Email
const confirmingEmailChange = ref(false);
const emailForm = useForm({
    email: props.auth.user.email,
    name: props.auth.user.name,
});

// Edit Profile
const confirmingProfileUpdate = ref(false);
const profileForm = useForm({
    name: props.auth.user.name,
    email: props.auth.user.email,
});

const updateProfile = () => {
    profileForm.put(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => {
            confirmingProfileUpdate.value = false;
        },
    });
};

const updateEmail = () => {
    emailForm.put(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => {
            confirmingEmailChange.value = false;
        },
    });
};

// Delete Account
const confirmingUserDeletion = ref(false);
// ... existing code ...

const passwordInput = ref(null);
const deleteForm = useForm({
    password: '',
});

const deleteUser = () => {
    deleteForm.delete(route('current-user.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => deleteForm.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    deleteForm.reset();
};

// Placeholders
const showFeatureModal = ref(false);
const featureTitle = ref('');
const featureMessage = ref('');

const openFeatureModal = (title, message) => {
    featureTitle.value = title;
    featureMessage.value = message;
    showFeatureModal.value = true;
};

const manageSubscription = () => {
    // If billing portal URL exists in props, use it. Otherwise show placeholder.
    // window.location.href = '/billing'; 
    openFeatureModal('Manage Subscription', 'Subscription management is not yet configured. Please contact support.');
};

// Calendar Integrations
const confirmingICloudConnection = ref(false);
const icloudForm = useForm({
    email: '',
    password: '',
});

const connectICloud = () => {
    icloudForm.post(route('icloud.auth'), {
        preserveScroll: true,
        onSuccess: () => {
            confirmingICloudConnection.value = false;
            icloudForm.reset();
        },
        onError: () => {
            // Focus email input or show error
        },
    });
};


</script>

<template>
    <AppLayout title="Settings" :show-nav="false">
        <div class="flex h-screen bg-white dark:bg-gray-900 dark:text-gray-100">
            <!-- Sidebar -->
            <div class="w-64 flex-shrink-0 border-r border-gray-100 dark:border-gray-800 p-6">
                
                <div class="flex items-center gap-2 mb-8 text-xl font-bold">
                    <Link :href="route('dashboard')" class="text-gray-400 hover:text-black dark:hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    </Link>
                    <span>Settings</span>
                </div>

                <nav class="space-y-4 text-sm font-medium text-gray-500 dark:text-gray-400">
                    <div @click="activeSection = 'account'" :class="{'text-purple-600 font-bold bg-purple-50 dark:bg-purple-900/20 dark:text-purple-400': activeSection === 'account', 'hover:text-black dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800': activeSection !== 'account'}" class="flex items-center gap-3 p-2 rounded-lg cursor-pointer transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        Account
                    </div>
                    <div @click="activeSection = 'calendars'" :class="{'text-purple-600 font-bold bg-purple-50 dark:bg-purple-900/20 dark:text-purple-400': activeSection === 'calendars', 'hover:text-black dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800': activeSection !== 'calendars'}" class="flex items-center gap-3 p-2 rounded-lg cursor-pointer transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Connected calendars
                    </div>
                     <div class="flex items-center gap-3 hover:text-black dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800 p-2 rounded-lg cursor-pointer transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Calendar settings
                    </div>
                    <div class="flex items-center gap-3 hover:text-black dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800 p-2 rounded-lg cursor-pointer transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        Notifications and sounds
                    </div>
                    <div class="flex items-center gap-3 hover:text-black dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800 p-2 rounded-lg cursor-pointer transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        Appearance
                    </div>
                </nav>

                <div class="absolute bottom-6 left-6 space-y-4 text-xs font-bold text-gray-500">
                    <div class="hover:text-black dark:hover:text-white cursor-pointer flex justify-between">Frequently Asked Questions <span class="text-gray-300 dark:text-gray-600">↗</span></div>
                    <div class="hover:text-black dark:hover:text-white cursor-pointer flex justify-between">Privacy Policy <span class="text-gray-300 dark:text-gray-600">↗</span></div>
                    <div class="hover:text-black dark:hover:text-white cursor-pointer flex justify-between">Cookie Policy <span class="text-gray-300 dark:text-gray-600">↗</span></div>
                    <div class="hover:text-black dark:hover:text-white cursor-pointer flex justify-between">Terms of service <span class="text-gray-300 dark:text-gray-600">↗</span></div>
                    
                    <button class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold py-2 px-4 rounded-full mt-4 w-24">Log out</button>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-12 overflow-y-auto">
                <div v-if="activeSection === 'account'">
                    <h1 class="text-3xl font-bold font-serif mb-12">Account</h1>

                    <div class="space-y-12 max-w-2xl">
                        <!-- Profiles -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="font-bold text-gray-900 dark:text-gray-100">Profiles</div>
                            <div class="md:col-span-2 space-y-4">
                                <div class="flex items-center justify-between p-2 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-black text-white rounded-full flex items-center justify-center font-bold text-lg">
                                            {{ $page.props.auth.user.name.charAt(0) }}
                                        </div>
                                        <span class="font-bold">{{ $page.props.auth.user.name }}</span>
                                    </div>
                                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                                        <button @click="confirmingProfileUpdate = true" class="text-gray-400 hover:text-black dark:hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>
                                        <button class="text-gray-400 hover:text-red-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                    </div>
                                </div>
                                <button @click="openFeatureModal('Add Profile', 'Multiple profiles are coming soon!')" class="bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold py-1.5 px-4 rounded-full text-xs transition">
                                    Add profile +
                                </button>
                            </div>
                        </div>
                        
                        <div class="border-b border-gray-100 dark:border-gray-800"></div>

                        <!-- Email -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="font-bold text-gray-900 dark:text-gray-100">Email</div>
                            <div class="md:col-span-2 space-y-4">
                                <div class="bg-black text-white px-3 py-2 rounded font-mono text-sm inline-block">
                                    {{ $page.props.auth.user.email }}
                                </div>
                                <div class="flex gap-3">
                                    <button @click="confirmingEmailChange = true" class="bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold py-1.5 px-4 rounded-full text-xs transition">Change email</button>
                                    <button @click="confirmingUserDeletion = true" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-700 dark:text-gray-300 font-bold py-1.5 px-4 rounded-full text-xs transition">Delete account</button>
                                </div>
                            </div>
                        </div>

                        <div class="border-b border-gray-100 dark:border-gray-800"></div>

                        <!-- Subscription -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="font-bold text-gray-900 dark:text-gray-100">Subscription</div>
                            <div class="md:col-span-2">
                                <button @click="manageSubscription" class="bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold py-1.5 px-4 rounded-full text-xs transition">
                                    Manage subscriptions
                                </button>
                            </div>
                        </div>

                        <div class="border-b border-gray-100 dark:border-gray-800"></div>

                        <!-- Send messages -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="font-bold text-gray-900 dark:text-gray-100">Send messages</div>
                            <div class="md:col-span-2 space-y-4">
                                <p class="text-gray-500 dark:text-gray-400 text-sm leading-relaxed">
                                    We would love to share when we have exciting news, new content, app updates or relevant offers ready for you. Remember you can always change it as it fits you.
                                </p>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Newsletters and offers</span>
                                    <!-- Toggle Switch -->
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" value="" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeSection === 'calendars'">
                    <h1 class="text-3xl font-bold font-serif mb-2 dark:text-white">Connected calendars</h1>
                    <p class="text-gray-500 dark:text-gray-400 mb-12">Manage third-party accounts and connect your favorite tools to Reminddo</p>

                    <div class="space-y-8">
                        <div>
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-xl font-bold dark:text-white">Your calendars</h2>
                                <div class="flex gap-3">
                                    <button @click="confirmingICloudConnection = true" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold py-2 px-4 rounded-full text-sm transition flex items-center gap-2">
                                        <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 24 24"><path d="M17.5 19c0-.83.67-1.5 1.5-1.5.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5c-.83 0-1.5-.67-1.5-1.5zM12 17c-1.38 0-2.5 1.12-2.5 2.5s1.12 2.5 2.5 2.5 2.5-1.12 2.5-2.5-1.12-2.5-2.5-2.5zm7 1.5c0-.83-.67-1.5-1.5-1.5-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5zM8.5 19c0-.83.67-1.5 1.5-1.5.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5c-.83 0-1.5-.67-1.5-1.5zM19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/></svg>
                                        Add iCloud calendar
                                    </button>
                                    <a :href="route('google.auth')" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold py-2 px-4 rounded-full text-sm transition flex items-center gap-2 cursor-pointer">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.26.81-.58z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                                        Add Google calendar
                                    </a>
                                </div>
                            </div>
                            
                            <div class="border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl p-8 flex flex-col items-center justify-center text-center min-h-[160px]">
                                <div class="bg-blue-50 dark:bg-blue-900/30 p-4 rounded-full mb-4">
                                    <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                                <h3 class="font-bold text-lg mb-1 dark:text-white">You have no connected calendars.</h3>
                                <p class="text-gray-500 dark:text-gray-400">Set yourself up for success and import your calendar to have all your tasks one place.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- iCloud Connection Modal -->
        <DialogModal :show="confirmingICloudConnection" @close="confirmingICloudConnection = false">
            <template #title>
                Connect iCloud Calendar
            </template>

            <template #content>
                 <div class="text-sm text-gray-600 mb-4">
                    Please provide your Apple ID and an App-Specific Password. 
                    <a href="https://support.apple.com/en-us/HT204397" target="_blank" class="text-blue-600 hover:underline">Learn how to generate one</a>.
                </div>

                <div class="mt-4">
                    <InputLabel for="icloud_email" value="Apple ID Email" />
                    <TextInput
                        id="icloud_email"
                        v-model="icloudForm.email"
                        type="email"
                        class="mt-1 block w-3/4"
                        placeholder="you@icloud.com"
                    />
                    <InputError :message="icloudForm.errors.email" class="mt-2" />
                </div>

                 <div class="mt-4">
                    <InputLabel for="icloud_password" value="App-Specific Password" />
                    <TextInput
                        id="icloud_password"
                        v-model="icloudForm.password"
                        type="password"
                        class="mt-1 block w-3/4"
                        placeholder="xxxx-xxxx-xxxx-xxxx"
                    />
                    <InputError :message="icloudForm.errors.password" class="mt-2" />
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="confirmingICloudConnection = false">
                    Cancel
                </SecondaryButton>

                <PrimaryButton
                    class="ml-3"
                    :class="{ 'opacity-25': icloudForm.processing }"
                    :disabled="icloudForm.processing"
                    @click="connectICloud"
                >
                    Connect
                </PrimaryButton>
            </template>
        </DialogModal>

        <!-- Edit Profile Modal -->
         <DialogModal :show="confirmingProfileUpdate" @close="confirmingProfileUpdate = false">
            <template #title>
                Edit Profile
            </template>

            <template #content>
                <div class="col-span-6 sm:col-span-4">
                    <InputLabel for="name" value="Name" />
                    <TextInput
                        id="name"
                        v-model="profileForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                    />
                    <InputError :message="profileForm.errors.name" class="mt-2" />
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="confirmingProfileUpdate = false">
                    Cancel
                </SecondaryButton>

                <PrimaryButton
                    class="ml-3"
                    :class="{ 'opacity-25': profileForm.processing }"
                    :disabled="profileForm.processing"
                    @click="updateProfile"
                >
                    Save
                </PrimaryButton>
            </template>
        </DialogModal>

        <!-- Change Email Modal -->
        <DialogModal :show="confirmingEmailChange" @close="confirmingEmailChange = false">
            <template #title>
                Change Email
            </template>

            <template #content>
                <div class="col-span-6 sm:col-span-4">
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        v-model="emailForm.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="emailForm.errors.email" class="mt-2" />
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="confirmingEmailChange = false">
                    Cancel
                </SecondaryButton>

                <PrimaryButton
                    class="ml-3"
                    :class="{ 'opacity-25': emailForm.processing }"
                    :disabled="emailForm.processing"
                    @click="updateEmail"
                >
                    Save
                </PrimaryButton>
            </template>
        </DialogModal>

        <!-- Delete Account Modal -->
        <DialogModal :show="confirmingUserDeletion" @close="closeModal">
            <template #title>
                Delete Account
            </template>

            <template #content>
                Are you sure you want to delete your account? This action cannot be undone.

                <div class="mt-4">
                    <TextInput
                        ref="passwordInput"
                        v-model="deleteForm.password"
                        type="password"
                        class="mt-1 block w-3/4"
                        placeholder="Password"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="deleteForm.errors.password" class="mt-2" />
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="closeModal">
                    Cancel
                </SecondaryButton>

                <PrimaryButton
                    class="ml-3 bg-red-600 hover:bg-red-500"
                    :class="{ 'opacity-25': deleteForm.processing }"
                    :disabled="deleteForm.processing"
                    @click="deleteUser"
                >
                    Delete Account
                </PrimaryButton>
            </template>
        </DialogModal>

        <!-- Feature Not Implemented Modal -->
        <DialogModal :show="showFeatureModal" @close="showFeatureModal = false">
            <template #title>
                {{ featureTitle }}
            </template>

            <template #content>
                {{ featureMessage }}
            </template>

            <template #footer>
                <SecondaryButton @click="showFeatureModal = false">
                    Close
                </SecondaryButton>
            </template>
        </DialogModal>

    </AppLayout>
</template>
