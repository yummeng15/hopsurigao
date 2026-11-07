import { tableFeatures, rowSortingFeature, rowSelectionFeature } from '@tanstack/vue-table';

export const appTableFeatures = tableFeatures({
    rowSortingFeature,
    rowSelectionFeature,
});

export type AppTableFeatures = typeof appTableFeatures;