import { createVuetify } from 'vuetify'
import { md3 } from 'vuetify/blueprints'
import '@mdi/font/css/materialdesignicons.css'

export default createVuetify({
  blueprint: md3,
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        dark: false,
        colors: {
          primary:            '#2563EB',
          'primary-darken-1': '#1D4ED8',
          secondary:          '#7C3AED',
          success:            '#059669',
          warning:            '#D97706',
          error:              '#DC2626',
          info:               '#0284C7',
          background:         '#F1F5F9',
          surface:            '#FFFFFF',
          'on-surface':       '#0F172A',
          'surface-variant':  '#F8FAFC',
          'on-surface-variant': '#64748B',
        },
      },
    },
  },
  defaults: {
    VCard:      { elevation: 0, color: 'white' },
    VBtn:       { style: 'text-transform:none;letter-spacing:0;font-weight:500;', rounded: 'lg' },
    VTextField: { variant: 'outlined', density: 'comfortable', rounded: 'lg', hideDetails: 'auto' },
    VSelect:    { variant: 'outlined', density: 'comfortable', rounded: 'lg', hideDetails: 'auto' },
    VTextarea:  { variant: 'outlined', rounded: 'lg', hideDetails: 'auto' },
    VChip:      { rounded: 'lg' },
    VAlert:     { rounded: 'xl', elevation: 0 },
  },
})
