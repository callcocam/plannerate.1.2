// /store/editor/state.ts
import { ref, computed } from 'vue';
import { isEqual } from 'lodash-es';
import type { Gondola } from '@plannerate/types/gondola';
import type { PlanogramEditorState, GondolaHistory } from './types';

// =========================================================
// STATE
// =========================================================

// Estado principal do editor
export const currentState = ref<PlanogramEditorState | null>(null);
export const currentGondola = ref<Gondola | null>(null);

// Gerenciamento de histórico por gôndola
export const gondolaHistories = ref<Record<string, GondolaHistory>>({});
export const isTimeTraveling = ref(false);

// Estado de UI e erros
export const isLoading = ref(false);
export const error = ref<string | null>(null);

// =========================================================
// GETTERS
// =========================================================

// Retorna o histórico da gôndola atual
export const currentHistory = computed((): GondolaHistory | null => {
    if (!currentGondola.value) return null;
    return gondolaHistories.value[currentGondola.value.id] || null;
});

// Configurações visuais
export const currentScaleFactor = computed(() => currentState.value?.scaleFactor ?? 3);
export const isGridVisible = computed(() => currentState.value?.showGrid ?? false);

// Controle de gondola atual
export const getCurrentGondola = computed(() => currentGondola.value);

// ID da gôndola atual
export const currentGondolaId = computed((): string | null => {
    return currentGondola.value?.id || null;
});

// Verificações de estado para undo/redo
export const canUndo = computed((): boolean => {
    if (!currentHistory.value) return false;
    return currentHistory.value.currentIndex > 0;
});

export const canRedo = computed((): boolean => {
    if (!currentHistory.value) return false;
    return currentHistory.value.currentIndex < currentHistory.value.entries.length - 1;
});

// Verifica se há alterações em relação ao estado inicial da gôndola atual
export const hasChanges = computed((): boolean => {
    if (!currentHistory.value || !currentState.value) return false;
    
    // Se não há entradas no histórico, não há alterações
    if (currentHistory.value.entries.length === 0) return false;
    
    // Compara o estado atual com o estado inicial (primeiro no histórico)
    return !isEqual(currentState.value, currentHistory.value.entries[0].state);
});