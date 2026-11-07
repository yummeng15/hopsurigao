<script setup lang="ts">
import { Check, Palette } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTheme } from '@/composables/useTheme';
import { themes } from '@/lib/themes';

const { theme, updateTheme } = useTheme();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button variant="ghost" size="icon" class="h-9 w-9 rounded-full">
                <Palette class="h-4 w-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-48">
            <DropdownMenuItem
                v-for="option in themes"
                :key="option.value"
                class="flex items-center justify-between cursor-pointer"
                @click="updateTheme(option.value)"
            >
                <span class="flex items-center gap-2">
                    <span
                        class="h-3.5 w-3.5 rounded-full border"
                        :style="{ backgroundColor: option.swatch }"
                    />
                    <span class="text-sm">{{ option.label }}</span>
                </span>
                <Check
                    v-if="theme === option.value"
                    class="h-4 w-4 text-foreground"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>