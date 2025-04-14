// Popover.vue - Componente de Filtro
<script setup lang="ts">
import { Button } from '../../../components/ui/button';
// @ts-ignore
import { Popover, PopoverContent, PopoverTrigger } from '../../../components/ui/popover';
import { computed, defineEmits, defineProps } from 'vue';

// Emitir eventos para o componente pai
const emit = defineEmits(['apply-filters', 'clear-filters']);

// Props opcionais para personalização
const props = defineProps({
    title: {
        type: String,
        default: 'Filtros',
    },
    applyText: {
        type: String,
        default: 'Aplicar',
    },
    clearText: {
        type: String,
        default: 'Limpar',
    },
    hasActiveFilters: {
        type: Boolean,
        default: false,
    },
});

// Função para limpar filtros
const clearAllFilters = () => {
    emit('clear-filters');
};

// Calcular a classe do ícone baseada no estado dos filtros
const iconClass = computed(() => {
    return {
        'lucide lucide-funnel cursor-pointer hover:text-primary': true,
        'text-primary fill-primary/20': props.hasActiveFilters,
    };
});
</script>

<template>
    <Popover>
        <PopoverTrigger>
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                :class="iconClass"
            >
                <path
                    d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"
                />
            </svg>
            <span class="sr-only">Abrir filtros</span>
        </PopoverTrigger>
        <PopoverContent class="w-80 dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-col gap-3 p-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium dark:text-gray-200">{{ title }}</h3>
                    <span v-if="hasActiveFilters" class="cursor-pointer text-xs text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300" @click="clearAllFilters">
                        Limpar todos
                    </span>
                </div>

                <!-- Conteúdo de filtros via slot -->
                <div class="space-y-2">
                    <slot></slot>
                </div>

                <!-- Botões de ação -->
                <div class="flex justify-end gap-2 pt-2">
                    <Button variant="outline" size="sm" @click="clearAllFilters" :disabled="!hasActiveFilters" class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                        {{ clearText }}
                    </Button>
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
