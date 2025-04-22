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
    getCurrentGondola,
    productIdsInCurrentGondola,
    selectedLayerIds,
    selectedShelf,
    isShelfEditing,
    selectedSection,
    isSectionEditing,
    selectedSegment,
    isSegmentEditing,
    selectedLayer,
    isLayerEditing,
    getSelectedShelf,
    getSelectedSection,
    getSelectedSegment,
    getSelectedLayer,
    getSelectedLayerIds,
    getIsDragging,
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
        selectedShelf,
        isShelfEditing,
        selectedSection,
        isSectionEditing,
        selectedSegment,
        isSegmentEditing,
        selectedLayerIds,
        selectedLayer,
        isLayerEditing,

        // Getters computados
        hasChanges,
        canUndo,
        canRedo,
        currentScaleFactor,
        isGridVisible,
        currentGondolaId,
        getCurrentGondola,
        productIdsInCurrentGondola,
        getSelectedShelf,
        getSelectedSection,
        getSelectedSegment,
        getSelectedLayer,
        getSelectedLayerIds,

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
        removeGondola: actions.removeGondola,
        // Manipulação de seções
        setGondolaSectionOrder: actions.setGondolaSectionOrder,
        removeSectionFromGondola: actions.removeSectionFromGondola,
        // setSectionAlignment: actions.setSectionAlignment,
        updateSectionData: actions.updateSectionData,
        setIsSectionEditing: actions.setIsSectionEditing,
        setSelectedSection: actions.setSelectedSection,
        clearSelectedSection: actions.clearSelectedSection,
        isSectionSelected: actions.isSectionSelected,
        setIsDragging: actions.setIsDragging,
        disableDragging: actions.disableDragging,
        enableDragging: actions.enableDragging,
        getIsDragging,
        // Manipulação de prateleiras
        invertShelvesInSection: actions.invertShelvesInSection,
        addShelfToSection: actions.addShelfToSection,
        setShelfPosition: actions.setShelfPosition,
        // setShelfAlignment: actions.setShelfAlignment,
        removeShelfFromSection: actions.removeShelfFromSection,
        updateShelfData: actions.updateShelfData,
        transferShelfBetweenSections: actions.transferShelfBetweenSections,
        setIsShelfEditing: actions.setIsShelfEditing,
        setSelectedShelf: actions.setSelectedShelf,
        clearSelectedShelf: actions.clearSelectedShelf,
        isShelfSelected: actions.isShelfSelected,
        updateSegmentQuantity: actions.updateSegmentQuantity,
        // Manipulação de segmentos
        addSegmentToShelf: actions.addSegmentToShelf,
        setShelfSegmentsOrder: actions.setShelfSegmentsOrder,
        transferSegmentBetweenShelves: actions.transferSegmentBetweenShelves,
        removeSegmentFromShelf: actions.removeSegmentFromShelf,
        updateLayerQuantity: actions.updateLayerQuantity,

        // Manipulação de layers
        selectLayer: actions.selectLayer,
        isSelectedLayer: actions.isSelectedLayer,
        deselectLayer: actions.deselectLayer,
        isDeselectedLayer: actions.isDeselectedLayer,
        toggleLayerSelection: actions.toggleLayerSelection,
        isToggleSelectedLayer: actions.isToggleSelectedLayer,
        clearLayerSelection: actions.clearLayerSelection,
        setSelectedLayer: actions.setSelectedLayer,
    };
});