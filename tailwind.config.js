import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Token values follow design.md (prose section as source of truth).
 * Existing Tailwind key names are kept so component markup stays stable.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Hanken Grotesk', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Level 0 background / Level 1 surface
                paper: '#F4F6F5',
                surface: '#FFFFFF',
                // Text
                ink: {
                    DEFAULT: '#121212',
                    soft: '#64748B',
                    faint: '#94A3B8',
                },
                // Ghost borders / tonal separators
                line: {
                    DEFAULT: '#E2E8F0',
                    strong: '#CBD5E1',
                },
                // Primary — Deep Emerald
                brand: {
                    DEFAULT: '#1E6B4F',
                    ink: '#1B6049', // interactive: darken ~5%
                    wash: '#F0F7F4', // active/hover tint, table row hover
                },
                // Secondary — warm amber for warnings / low stock
                amber: {
                    DEFAULT: '#F4A261',
                    ink: '#7A3D06', // dark text on amber
                    wash: '#FDF1E5',
                },
                // Tertiary — teal for positive / completed states
                success: {
                    DEFAULT: '#2A9D8F',
                    wash: '#E7F4F2',
                },
                // Error
                danger: {
                    DEFAULT: '#BA1A1A',
                    wash: '#FFDAD6',
                },
                // Data-table header fill
                thead: '#F1F5F9',
            },
            borderRadius: {
                // design.md "Soft" shape language, nudged toward the mockup
                card: '0.75rem', // 12px — cards, sidebar panels
                control: '0.375rem', // 6px — buttons, inputs, tags
            },
            boxShadow: {
                card: '0 4px 12px rgba(0,0,0,0.03)',
                sheet: '0 12px 24px rgba(0,0,0,0.08)',
            },
            spacing: {
                sidebar: '260px',
                rail: '72px',
                header: '72px',
            },
            maxWidth: {
                canvas: '1440px',
            },
            fontSize: {
                '2xs': ['0.6875rem', { lineHeight: '1rem' }],
                'display-lg': [
                    '32px',
                    { lineHeight: '40px', letterSpacing: '-0.02em', fontWeight: '700' },
                ],
                'headline-md': ['24px', { lineHeight: '32px', fontWeight: '600' }],
                'headline-sm': ['18px', { lineHeight: '24px', fontWeight: '600' }],
                'body-lg': ['16px', { lineHeight: '24px' }],
                'body-md': ['14px', { lineHeight: '20px' }],
                'label-caps': [
                    '12px',
                    { lineHeight: '16px', letterSpacing: '0.05em', fontWeight: '600' },
                ],
                'data-mono': ['13px', { lineHeight: '18px', fontWeight: '500' }],
            },
        },
    },

    plugins: [forms],
};
