// /store/editor/types.ts
import type { Gondola } from '@plannerate/types/gondola';
import type { Section } from '@plannerate/types/sections';
import type { Shelf } from '@plannerate/types/shelves'; 

/**
 * Interface para representar o estado do planograma no editor
 */
export interface PlanogramEditorState {
    id: string | null;
    name: string | null;
    gondolas: Gondola[];
    currentGondola: Gondola | null;
    scaleFactor: number;
    showGrid: boolean;
    isLoading: boolean;
    error: string | null;
    mercadologico_nivel: any;
}

/**
 * Interface para representar um snapshot do estado para o histórico de ações
 */
export interface HistoryEntry {
    timestamp: number;
    state: PlanogramEditorState;
    gondolaId: string; // ID da gôndola a que este histórico pertence
}

/**
 * Interface para representar o histórico de uma gôndola específica
 */
export interface GondolaHistory {
    entries: HistoryEntry[];
    currentIndex: number;
}

/**
 * Interface para Retorno da função findPath
 */
export interface PathResult {
    gondola: Gondola;
    section: Section;
    shelf?: Shelf;
}

/**
 * Interface para posição da prateleira
 */
export interface ShelfPosition {
    shelf_position: number;
    shelf_x_position: number;
}

// Constantes
export const MAX_HISTORY_SIZE = 50;