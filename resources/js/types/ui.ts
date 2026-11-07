export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export interface FlashToast {
    type: 'success' | 'error' | 'info' | 'warning';
    message: string;
    description?: string;
    duration?: number;
    action?: {
        label: string;
        // you'll trigger this via a named route or event, not a raw function from the server
    };
}