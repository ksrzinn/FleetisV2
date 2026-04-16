import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import globals from 'globals';

export default [
    {
        ignores: ['public/**', 'node_modules/**', 'vendor/**', 'bootstrap/ssr/**', 'storage/**'],
    },
    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['**/*.{js,vue}'],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                ...globals.browser,
                route: 'readonly',
            },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
            'vue/html-indent': ['error', 4],
            'vue/html-self-closing': 'off',
            'vue/no-reserved-component-names': 'off',
            'vue/require-default-prop': 'off',
        },
    },
];
