<template>
    <section class="login-landing" aria-labelledby="login-landing-title">
        <div class="login-brand-row">
            <div class="brand-mark">{{ brandMark }}</div>
            <div>
                <p class="eyebrow">{{ eyebrow }}</p>
                <h1>{{ brandName }}</h1>
            </div>
        </div>

        <div class="login-hero-copy">
            <h2 id="login-landing-title">{{ title }}</h2>
            <p>{{ description }}</p>
            <button class="btn btn-primary" type="button" @click="$emit('primary-action')">
                {{ actionLabel }}
            </button>
        </div>

        <div class="login-metrics" aria-label="Store overview">
            <article v-for="metric in metrics" :key="metric.label">
                <span>{{ metric.label }}</span>
                <strong>{{ metric.value }}</strong>
            </article>
        </div>

        <div class="login-highlights">
            <article v-for="highlight in highlights" :key="highlight.title">
                <strong>{{ highlight.title }}</strong>
                <span>{{ highlight.text }}</span>
            </article>
        </div>
    </section>
</template>

<script>
export default {
    name: 'LoginLandingPage',
    props: {
        brandName: {
            type: String,
            default: 'RFC Store',
        },
        brandMark: {
            type: String,
            default: 'RFC',
        },
        eyebrow: {
            type: String,
            default: 'Sales and stock',
        },
        title: {
            type: String,
            default: 'Keep today\'s counter, inventory, and customer flow in one place.',
        },
        description: {
            type: String,
            default: 'Sign in to continue managing ice sales, product stock, payments, and daily reports.',
        },
        actionLabel: {
            type: String,
            default: 'Sign In',
        },
        metrics: {
            type: Array,
            default: () => [],
        },
        highlights: {
            type: Array,
            default: () => [],
        },
    },
    emits: ['primary-action'],
};
</script>
