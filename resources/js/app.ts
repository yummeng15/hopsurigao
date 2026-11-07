import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme as initializeAppearance } from '@/composables/useAppearance';
import { initializeTheme as initializeColorTheme } from '@/composables/useTheme';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import OperatorPortalLayout from '@/layouts/portal/OperatorPortalLayout.vue';
import SuperAdminPortalLayout from '@/layouts/portal/SuperAdminPortalLayout.vue';
import LegacyStaffPortalLayout from '@/layouts/portal/LegacyStaffPortalLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        if (name === 'Welcome') return null;
        if (name.startsWith('auth/')) return AuthLayout;

        // Role-specific web shells. These pages no longer share the tourist UI.
        if (name.startsWith('superadmin/')) return SuperAdminPortalLayout;
        if (name.startsWith('operator/')) return OperatorPortalLayout;

        // Existing operational pages are still rendered as admin/* by Laravel.
        // Their shared controllers stay untouched, but the UI differs by role.
        if (name.startsWith('admin/')) return LegacyStaffPortalLayout;

        // Keep the existing settings structure to avoid breaking profile pages.
        if (name.startsWith('settings/')) return [AppLayout, SettingsLayout];

        // Tourist pages retain the current mobile-friendly layout. Their
        // dedicated Android WebView shell is a later, separate stage.
        return AppLayout;
    },
    progress: {
        color: '#00FFFF',
    },
});

if (typeof window !== 'undefined') {
    initializeAppearance();
    initializeColorTheme();
    initializeFlashToast();
}