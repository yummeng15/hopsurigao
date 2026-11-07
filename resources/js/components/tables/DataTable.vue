<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { ChevronLeft, ChevronRight, Loader2, Search } from '@lucide/vue';

type Column = {
    key: string;
    label: string;
    sortable?: boolean | string;
    class?: string;
    width?: string;
    align?: 'left' | 'center' | 'right';
};

type Paginator = {
    data?: Record<string, unknown>[];
    current_page?: number;
    last_page?: number;
    per_page?: number;
    from?: number | null;
    to?: number | null;
    total?: number;
    meta?: Paginator;
};

const props = withDefaults(
    defineProps<{
        data?: Record<string, unknown>[] | Paginator;
        columns: Column[];
        mode?: 'auto' | 'server' | 'client';
        searchable?: boolean;
        perPage?: number;
        fetchUrl?: string;
        enableCache?: boolean;
    }>(),
    {
        data: () => [],
        columns: () => [],
        mode: 'auto',
        searchable: true,
        perPage: 10,
        enableCache: false,
    },
);

const searchQuery = ref('');
const sortColumn = ref('');
const sortDirection = ref<'asc' | 'desc'>('asc');
const currentPage = ref(1);
const currentPerPage = ref(props.perPage);
const isLoading = ref(false);
const internalData = ref<Record<string, unknown>[] | Paginator>(props.data);
const responseCache = ref(new Map<string, Paginator>());

watch(
    () => props.data,
    (value) => {
        internalData.value = value;
    },
    { deep: true },
);

const isServerSide = computed(() => {
    if (props.mode === 'server') {
        return true;
    }

    if (props.mode === 'client') {
        return false;
    }

    return Boolean(props.fetchUrl);
});

const pageSizeOptions = computed(() => {
    const sizes = [10, 20, 30, 50, 100];

    if (!sizes.includes(currentPerPage.value)) {
        return [currentPerPage.value, ...sizes];
    }

    return sizes;
});

function rowsFrom(source: Record<string, unknown>[] | Paginator): Record<string, unknown>[] {
    if (Array.isArray(source)) {
        return source;
    }

    return source.data ?? source.meta?.data ?? [];
}

const serverMeta = computed(() => {
    if (!isServerSide.value || Array.isArray(internalData.value)) {
        return null;
    }

    const meta = internalData.value.meta ?? internalData.value;
    const page = meta.current_page ?? 1;
    const perPage = meta.per_page ?? currentPerPage.value;
    const total = meta.total ?? 0;
    const count = rowsFrom(internalData.value).length;
    const from = total === 0 ? 0 : (page - 1) * perPage + 1;
    const to = total === 0 ? 0 : Math.min(from + count - 1, total);

    return {
        current_page: page,
        last_page: meta.last_page ?? 1,
        per_page: perPage,
        from: meta.from ?? from,
        to: meta.to ?? to,
        total,
    };
});

function matchesSearch(row: Record<string, unknown>, query: string): boolean {
    return Object.values(row).some((value) => String(value ?? '').toLowerCase().includes(query));
}

const filteredRows = computed(() => {
    if (isServerSide.value) {
        return rowsFrom(internalData.value);
    }

    let items = [...rowsFrom(internalData.value)];
    const query = searchQuery.value.trim().toLowerCase();

    if (query !== '') {
        items = items.filter((row) => matchesSearch(row, query));
    }

    if (sortColumn.value !== '') {
        const column = sortColumn.value;
        const direction = sortDirection.value === 'asc' ? 1 : -1;

        items.sort((left, right) => {
            const leftValue = String(left[column] ?? '');
            const rightValue = String(right[column] ?? '');

            return leftValue.localeCompare(rightValue, undefined, { numeric: true }) * direction;
        });
    }

    return items;
});

const tableRows = computed(() => {
    if (isServerSide.value) {
        return filteredRows.value;
    }

    const start = (currentPage.value - 1) * currentPerPage.value;

    return filteredRows.value.slice(start, start + currentPerPage.value);
});

const totalPages = computed(() => {
    if (isServerSide.value) {
        return serverMeta.value?.last_page ?? 1;
    }

    return Math.max(1, Math.ceil(filteredRows.value.length / currentPerPage.value));
});

const paginationMeta = computed(() => {
    if (isServerSide.value) {
        return {
            from: serverMeta.value?.from ?? 0,
            to: serverMeta.value?.to ?? 0,
            total: serverMeta.value?.total ?? 0,
        };
    }

    const total = filteredRows.value.length;
    const from = total === 0 ? 0 : (currentPage.value - 1) * currentPerPage.value + 1;
    const to = Math.min(from + currentPerPage.value - 1, total);

    return { from, to, total };
});

const activePage = computed(() => (isServerSide.value ? (serverMeta.value?.current_page ?? 1) : currentPage.value));

const pageNumbers = computed(() => {
    const current = activePage.value;
    const total = totalPages.value;
    const pages: Array<number | string> = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);

    if (start > 2) {
        pages.push('...');
    }

    for (let page = start; page <= end; page += 1) {
        pages.push(page);
    }

    if (end < total - 1) {
        pages.push('...');
    }

    if (total > 1) {
        pages.push(total);
    }

    return pages;
});

function cacheKey(): string {
    return JSON.stringify({
        page: currentPage.value,
        perPage: currentPerPage.value,
        search: searchQuery.value,
        sort: sortColumn.value,
        order: sortDirection.value,
    });
}

function sortKey(column: Column): string {
    return typeof column.sortable === 'string' ? column.sortable : column.key;
}

async function fetchData(): Promise<void> {
    if (!props.fetchUrl) {
        return;
    }

    const key = cacheKey();

    if (props.enableCache && responseCache.value.has(key)) {
        internalData.value = responseCache.value.get(key) as Paginator;

        return;
    }

    isLoading.value = true;

    try {
        const url = new URL(props.fetchUrl, window.location.origin);
        url.searchParams.set('page', String(currentPage.value));
        url.searchParams.set('per_page', String(currentPerPage.value));

        if (searchQuery.value.trim() !== '') {
            url.searchParams.set('search', searchQuery.value.trim());
        }

        if (sortColumn.value !== '') {
            url.searchParams.set('sort', sortColumn.value);
            url.searchParams.set('order', sortDirection.value);
        }

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`Table request failed (${response.status})`);
        }

        const payload = (await response.json()) as Paginator;
        internalData.value = payload;

        if (props.enableCache) {
            responseCache.value.set(key, payload);
        }
    } finally {
        isLoading.value = false;
    }
}

async function clearCache(scope: 'all' | 'current' = 'all'): Promise<void> {
    if (scope === 'current') {
        responseCache.value.delete(cacheKey());
    } else {
        responseCache.value.clear();
    }

    if (props.fetchUrl) {
        await fetchData();
    }
}

async function refresh(): Promise<void> {
    currentPage.value = 1;
    await nextTick();

    if (props.enableCache) {
        await clearCache('current');

        return;
    }

    await fetchData();
}

const queueSearch = useDebounceFn(() => {
    currentPage.value = 1;

    if (isServerSide.value) {
        fetchData();
    }
}, 300);

watch(searchQuery, () => {
    queueSearch();
});

function handleSort(column: Column): void {
    if (!column.sortable) {
        return;
    }

    const key = sortKey(column);

    if (sortColumn.value === key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = key;
        sortDirection.value = 'asc';
    }

    if (isServerSide.value) {
        fetchData();
    }
}

function handlePageSize(size: string): void {
    currentPerPage.value = Number(size);
    currentPage.value = 1;
    fetchData();
}

function handlePageChange(page: number): void {
    if (page < 1 || page > totalPages.value || page === activePage.value) {
        return;
    }

    currentPage.value = page;

    if (isServerSide.value) {
        fetchData();
    }
}

function alignClass(column: Column): string {
    if (column.align === 'center') {
        return 'text-center';
    }

    if (column.align === 'right') {
        return 'text-right';
    }

    return 'text-left';
}

function cellStyle(column: Column): Record<string, string> | undefined {
    if (!column.width) {
        return undefined;
    }

    return {
        width: column.width,
        minWidth: column.width,
    };
}

onMounted(() => {
    if (props.fetchUrl) {
        fetchData();
    }
});

defineExpose({
    refresh,
    fetchData,
    clearCache,
});
</script>

<template>
    <div class="w-full space-y-4">
        <div v-if="searchable" class="flex flex-wrap items-center gap-2">
            <select
                :value="currentPerPage"
                class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                @change="handlePageSize(($event.target as HTMLSelectElement).value)"
            >
                <option v-for="size in pageSizeOptions" :key="size" :value="size">
                    {{ size }}
                </option>
            </select>

            <div class="relative min-w-[200px] flex-1 max-w-sm">
                <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Search..."
                    class="h-10 w-full rounded-md border border-input bg-background py-2 pr-3 pl-10 text-sm text-foreground placeholder:text-muted-foreground"
                />
            </div>
        </div>

        <div class="relative overflow-hidden rounded-md border border-border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-border">
                            <th
                                v-for="column in columns"
                                :key="column.key"
                                class="h-10 px-3 text-xs font-medium text-muted-foreground"
                                :class="alignClass(column)"
                                :style="cellStyle(column)"
                            >
                                <button
                                    v-if="column.sortable"
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="handleSort(column)"
                                >
                                    {{ column.label }}
                                </button>
                                <span v-else>{{ column.label }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="isLoading && tableRows.length === 0">
                            <td :colspan="columns.length" class="h-24 text-center text-muted-foreground">
                                <Loader2 class="mx-auto h-5 w-5 animate-spin" />
                            </td>
                        </tr>
                        <template v-else-if="tableRows.length">
                        <tr
                            v-for="(row, index) in tableRows"
                            :key="String(row.id ?? index)"
                            class="border-b border-border last:border-0 odd:bg-card even:bg-muted/40"
                        >
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                class="px-3 py-2 align-middle text-foreground"
                                :class="[alignClass(column), column.class]"
                                :style="cellStyle(column)"
                            >
                                <slot :name="`cell-${column.key}`" :row="row">
                                    {{ row[column.key] }}
                                </slot>
                            </td>
                        </tr>
                        </template>
                        <tr v-else>
                            <td :colspan="columns.length" class="h-24 text-center text-muted-foreground">
                                No results.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="isLoading && tableRows.length > 0"
                class="absolute inset-0 flex items-center justify-center bg-background/60"
            >
                <Loader2 class="h-6 w-6 animate-spin text-primary" />
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 px-1 text-sm text-muted-foreground">
            <p>Showing {{ paginationMeta.from }} to {{ paginationMeta.to }} of {{ paginationMeta.total }} results</p>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-md border border-border px-2 disabled:opacity-50"
                    :disabled="activePage <= 1"
                    @click="handlePageChange(activePage - 1)"
                >
                    <ChevronLeft class="h-4 w-4" />
                    <span class="ml-1 hidden sm:inline">Previous</span>
                </button>
                <template v-for="(page, index) in pageNumbers" :key="`${page}-${index}`">
                    <span v-if="page === '...'" class="px-2">...</span>
                    <button
                        v-else
                        type="button"
                        class="inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-2 text-sm"
                        :class="page === activePage ? 'border-primary bg-primary text-primary-foreground' : 'border-border'"
                        @click="handlePageChange(Number(page))"
                    >
                        {{ page }}
                    </button>
                </template>
                <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-md border border-border px-2 disabled:opacity-50"
                    :disabled="activePage >= totalPages"
                    @click="handlePageChange(activePage + 1)"
                >
                    <span class="mr-1 hidden sm:inline">Next</span>
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</template>
