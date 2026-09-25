<template>
    <section class="login-form-panel" aria-labelledby="login-title">
        <div class="login-form-heading">
            <p class="eyebrow">Welcome back</p>
            <h2 id="login-title">Sign in</h2>
        </div>

        <form class="login-form" @submit.prevent="submitForm">
            <label>
                Email
                <input
                    ref="emailInput"
                    v-model.trim="form.email"
                    autocomplete="email"
                    type="email"
                    required
                    :disabled="loading"
                >
            </label>

            <label>
                Password
                <input
                    v-model="form.password"
                    autocomplete="current-password"
                    type="password"
                    required
                    :disabled="loading"
                >
            </label>

            <label class="checkbox-row">
                <input v-model="form.remember" type="checkbox" :disabled="loading">
                <span>Keep me signed in</span>
            </label>

            <div v-if="error" class="login-error" role="alert">
                {{ error }}
            </div>

            <button class="btn btn-primary login-submit" type="submit" :disabled="loading">
                {{ loading ? 'Signing in...' : 'Sign In' }}
            </button>
        </form>
    </section>
</template>

<script>
export default {
    name: 'LoginForm',
    props: {
        loading: {
            type: Boolean,
            default: false,
        },
        error: {
            type: String,
            default: '',
        },
        initialEmail: {
            type: String,
            default: '',
        },
    },
    emits: ['submit'],
    data() {
        return {
            form: {
                email: this.initialEmail,
                password: '',
                remember: false,
            },
        };
    },
    watch: {
        initialEmail(value) {
            this.form.email = value;
        },
    },
    mounted() {
        this.focusEmail();
    },
    methods: {
        submitForm() {
            this.$emit('submit', {
                email: this.form.email,
                password: this.form.password,
                remember: this.form.remember,
            });
        },
        focusEmail() {
            this.$nextTick(() => {
                if (this.$refs.emailInput) {
                    this.$refs.emailInput.focus();
                }
            });
        },
    },
};
</script>
