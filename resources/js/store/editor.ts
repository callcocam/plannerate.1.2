// /store/editor/index.ts
import { defineStore } from 'pinia'; 

// Estado e Getters
import { 
    currentState,
    currentGondola,
    gondolaHistories,
    isLoading,
    error,
    currentScaleFactor,
    isGridVisible,
    canUndo,
    canRedo,
    hasChanges,
    currentGondolaId,
    getCurrentGondola
} from './editor/state';

// Funções de histórico
import { 
    recordChange,
    undo,
    redo,
    resetGondolaHistory
} from './editor/history';

// Utilitários
import { calculateTabindex } from './editor/utils';

// Ações
import * as actions from './editor/actions';

// Define o Store usando o composable API do Pinia
export const useEditorStore = defineStore('editor', () => {
    return {
        // Estado
        currentState,
        history: gondolaHistories,
        currentGondola,
        isLoading,
        error,

        // Getters computados
        hasChanges,
        canUndo,
        canRedo,
        currentScaleFactor,
        isGridVisible,
        currentGondolaId,
        getCurrentGondola,

        // Funções de inicialização
        initialize: actions.initialize,
        setCurrentGondola: actions.setCurrentGondola,

        // Funções de UI
        setIsLoading: actions.setIsLoading,
        setError: actions.setError,

        // Funções de histórico
        recordChange,
        undo,
        redo,
        resetGondolaHistory,
        saveChanges: actions.saveChanges,

        // Funções de manipulação de dados básicos
        updatePlanogramProperty: actions.updatePlanogramProperty,
        getGondola: actions.getGondola,
        addGondola: actions.addGondola,
        setScaleFactor: actions.setScaleFactor,
        toggleGrid: actions.toggleGrid,

        // Funções de acessibilidade
        calculateTabindex,

        // Manipulação de gôndolas
        setGondolaAlignment: actions.setGondolaAlignment,
        invertGondolaSectionOrder: actions.invertGondolaSectionOrder,

        // Manipulação de seções
        setGondolaSectionOrder: actions.setGondolaSectionOrder,
        removeSectionFromGondola: actions.removeSectionFromGondola,
        setSectionAlignment: actions.setSectionAlignment,
        updateSectionData: actions.updateSectionData,

        // Manipulação de prateleiras
        invertShelvesInSection: actions.invertShelvesInSection,
        addShelfToSection: actions.addShelfToSection,
        setShelfPosition: actions.setShelfPosition,
        setShelfAlignment: actions.setShelfAlignment,
        removeShelfFromSection: actions.removeShelfFromSection,
        updateShelfData: actions.updateShelfData,
        transferShelfBetweenSections: actions.transferShelfBetweenSections,

        // Manipulação de segmentos
        addSegmentToShelf: actions.addSegmentToShelf,
        setShelfSegmentsOrder: actions.setShelfSegmentsOrder,
        transferSegmentBetweenShelves: actions.transferSegmentBetweenShelves,
        updateLayerQuantity: actions.updateLayerQuantity,
    };
});