import { createVuetify } from 'vuetify'
import { md3 } from 'vuetify/blueprints'
import '@mdi/font/css/materialdesignicons.css'

export default createVuetify({
  blueprint: md3,
  theme: {
    defaultTheme: 'utgLight',
    themes: {
      utgLight: {
        dark: false,
        colors: {
          primary: '#1565C0',
          'primary-darken-1': '#0D47A1',
          secondary: '#0288D1',
          accent: '#00BCD4',
          success: '#2E7D32',
          warning: '#F57F17',
          error: '#C62828',
          info: '#1565C0',
          background: '#F5F7FA',
          surface: '#FFFFFF',
          'surface-variant': '#EEF2F7',
        },
      },
    },
  },
  defaults: {
    VCard: { elevation: 0 },
    VBtn: { style: 'text-transform: none;' },
  },
})
