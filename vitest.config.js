import { defineConfig } from 'vite'

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/__tests__/**/*.test.js'],
    },
})