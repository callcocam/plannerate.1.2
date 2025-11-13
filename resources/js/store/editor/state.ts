// /store/editor/state.ts
import { ref, computed } from 'vue';
import { isEqual } from 'lodash-es';
import type { Gondola } from '@plannerate/types/gondola';
import type { PlanogramEditorState, GondolaHistory } from './types';
import { Layer, Segment } from '@/types/segment';
import { Section } from '@/types/sections';
import { Shelf } from '@/types/shelves';

// =========================================================
// STATE
// =========================================================

// Estado principal do editor
export const currentState = ref<PlanogramEditorState | null>(null);
export const currentGondola = ref<Gondola | null>(null);

// Gerenciamento de histórico por gôndola
export const gondolaHistories = ref<Record<string, GondolaHistory>>({});
export const isTimeTraveling = ref(false);
export const productIdsInGondola = ref<string[]>([]);
export const changesSinceLastSave = ref<Record<string, number>>({});

// gerenciamento de estado das seções
export const selectedSection = ref<Section | null>(null);
export const isSectionEditing = ref(false);

// gerenciamento de estado das prateleiras
export const selectedShelf = ref<Shelf | null>(null);
export const isShelfEditing = ref(false);

// gerenciamento de estado dos segmentos
export const selectedSegment = ref<Segment | null>(null);
export const isSegmentEditing = ref(false);

// gerenciamento de estado dos layers
export const isLayerEditing = ref(false);

// Estado de Seleção (Migrado de productStore)
export const selectedLayerIds = ref<Set<string>>(new Set());
export const selectedLayer = ref<Layer | null>(null);
// Estado de UI e erros
export const isLoading = ref(false);
export const error = ref<string | null>(null);

// Estado de sessão na ora de drag and drop
export const isDragging = ref(false);

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

// Prateleira selecionada
export const getSelectedShelf = computed(() => selectedShelf.value);

// Seção selecionada
export const getSelectedSection = computed(() => selectedSection.value);

// Segmento selecionado
export const getSelectedSegment = computed(() => selectedSegment.value);

// Layer selecionado
export const getSelectedLayer = computed(() => selectedLayer.value);

// IDs das layers selecionadas (exemplo, pode precisar ajustar)
export const getSelectedLayerIds = computed(() => selectedLayerIds.value);

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

// Calcula e retorna um Set com os IDs de todos os produtos presentes na gôndola atual
export const productIdsInCurrentGondola = computed(() => {
    const gondola = currentGondola.value;
    const productIds = new Set<string>();

    if (gondola?.sections) {
        gondola.sections.forEach((section) => {
            section.shelves?.forEach((shelf) => {
                shelf.segments?.forEach((segment) => {
                    if (segment.layer?.product?.id) {
                        // Garante que o ID seja string antes de adicionar
                        const productId = String(segment.layer.product.id);
                        productIds.add(productId);
                    }
                });
            });
        });
    }
    return productIds; // Retorna o Set diretamente
});

// Estado de sessão na ora de drag and drop
export const getIsDragging = computed(() => isDragging.value);

// Retorna o número de mudanças desde o último save para a gôndola atual
export const changeCountForCurrentGondola = computed((): number => {
    if (!currentGondola.value) return 0;
    return changesSinceLastSave.value[currentGondola.value.id] || 0;
});