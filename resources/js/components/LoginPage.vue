<template>
    <div v-if="checkingSession" class="login-loading">
        <div class="brand-mark">RFC</div>
        <span>Loading store system...</span>
    </div>

    <SalesTrackerApp
        v-else-if="user"
        :user="user"
        @logout="logout"
    />

    <main v-else class="login-screen">
        <LoginLandingPage
            :brand-name="landing.brandName"
            :brand-mark="landing.brandMark"
            :eyebrow="landing.eyebrow"
            :title="landing.title"
            :description="landing.description"
            :metrics="landing.metrics"
            :highlights="landing.highlights"
            @primary-action="focusLogin"
        />

        <LoginForm
            ref="loginForm"
            :loading="loggingIn"
            :error="error"
            :initial-email="rememberedEmail"
            @submit="login"
        />
    </main>
</template>

<script>
import LoginForm from './LoginForm.vue';
import LoginLandingPage from './LoginLandingPage.vue';
import SalesTrackerApp from './SalesTrackerApp.vue';

export default {
    name: 'LoginPage',
    components: {
        LoginForm,
        LoginLandingPage,
        SalesTrackerApp,
    },
    data() {
        return {
            user: null,
            checkingSession: true,
            loggingIn: false,
            error: '',
            rememberedEmail: '',
            landing: {
                brandName: 'RFC Store',
                brandMark: 'RFC',
                eyebrow: 'Sales and stock',
                title: 'Run the counter with clear sales, stock, and customer records.',
                description: 'Sign in to manage daily transactions, inventory changes, payment totals, and customer reports from one focused workspace.',
                metrics: [
                    { label: 'Sales', value: 'Daily' },
                    { label: 'Stock', value: 'Live' },
                    { label: 'Reports', value: 'Ready' },
                ],
                highlights: [
                    {
                        title: 'Counter-ready selling',
                        text: 'Create walk-in or regular customer sales while stock updates stay connected.',
                    },
                    {
                        title: 'Inventory visibility',
                        text: 'Track current quantities, low-stock items, and adjustment history in the same flow.',
                    },
                    {
                        title: 'Customer and product reporting',
                        text: 'Review buying activity, payment totals, and product movement without leaving the app.',
                    },
                ],
            },
        };
    },
    created() {
        this.loadUser();
    },
    methods: {
        async loadUser() {
            try {
                const response = await window.axios.get('/auth/user');
                this.user = response.data.data;
            } catch (error) {
                this.user = null;
            } finally {
                this.checkingSession = false;
            }
        },
        async login(credentials) {
            this.loggingIn = true;
            this.error = '';

            try {
                const response = await this.sendWithFreshCsrf(() => window.axios.post('/login', credentials));
                this.user = response.data.data.user;
                this.rememberedEmail = credentials.remember ? credentials.email : '';
            } catch (error) {
                this.error = this.errorMessage(error);
            } finally {
                this.loggingIn = false;
            }
        },
        async logout() {
            this.error = '';

            try {
                const response = await this.sendWithFreshCsrf(() => window.axios.post('/logout'));
                this.updateCsrfToken(response.data.csrf_token);
                this.user = null;
            } catch (error) {
                if (error.response && error.response.status === 401) {
                    await this.refreshCsrfToken();
                    this.user = null;
                    return;
                }

                this.error = this.errorMessage(error);
            }
        },
        focusLogin() {
            if (this.$refs.loginForm) {
                this.$refs.loginForm.focusEmail();
            }
        },
        async sendWithFreshCsrf(request) {
            try {
                return await request();
            } catch (error) {
                if (! error.response || error.response.status !== 419) {
                    throw error;
                }

                await this.refreshCsrfToken();
                return request();
            }
        },
        async refreshCsrfToken() {
            const response = await window.axios.get('/auth/csrf-token');
            this.updateCsrfToken(response.data.csrf_token);
        },
        updateCsrfToken(token) {
            if (! token) {
                return;
            }

            const csrfMeta = document.head.querySelector('meta[name="csrf-token"]');

            if (csrfMeta) {
                csrfMeta.setAttribute('content', token);
            }
        },
        errorMessage(error) {
            if (error.response && error.response.data && error.response.data.message) {
                return error.response.data.message;
            }

            return 'Unable to sign in right now.';
        },
    },
};
</script>
