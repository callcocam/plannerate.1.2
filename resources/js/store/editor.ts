import { defineStore } from 'pinia';
import { ref, computed, watch } from 'vue';
import { isEqual } from 'lodash-es'; // Usaremos lodash para comparações profundas
import type { Gondola } from '@plannerate/types/gondola'; // Certifique-se de que Section está tipado
import type { Section } from '@plannerate/types/sections'; // Ajustar path se necessário
import type { Shelf } from '@plannerate/types/shelves'; // <-- Importar tipo Shelf

// Interface para representar o estado do planograma no editor
interface PlanogramEditorState {
    id: string | null;
    name: string | null;
    gondolas: Gondola[]; // Gondola já deve conter `sections: Section[]` a partir da sua definição
    // Adicionar estado de visualização do editor
    scaleFactor: number; 
    showGrid: boolean;
    // Adicionar outras propriedades se necessário (ex: item selecionado)
    // selectedItemId: string | null;
    // selectedItemType: 'gondola' | 'section' | 'shelf' | 'layer' | null;
}

// Interface para representar um snapshot do estado
interface HistoryEntry {
    timestamp: number;
    state: PlanogramEditorState;
}

export const useEditorStore = defineStore('editor', () => {
    // --- STATE --- 

    // Estado atual do planograma sendo editado
    const currentState = ref<PlanogramEditorState | null>(null);

    // Histórico de estados para undo/redo
    const history = ref<HistoryEntry[]>([]);
    // Índice atual no histórico (-1 significa nenhum estado, 0 é o estado inicial)
    const historyIndex = ref<number>(-1);
    // Flag para evitar que watchers do histórico adicionem ao histórico
    const isTimeTraveling = ref(false);

    // --- GETTERS --- 

    const initialPlanogram = computed(() => history.value[0]?.state || null);
    const currentScaleFactor = computed(() => currentState.value?.scaleFactor ?? 3); // Default 3
    const isGridVisible = computed(() => currentState.value?.showGrid ?? false); // Default false
    
    const hasChanges = computed(() => {
        if (historyIndex.value < 0 || !currentState.value) return false;
        // Compara o estado atual com o estado inicial salvo no histórico
        return !isEqual(currentState.value, initialPlanogram.value);
    });
    const canUndo = computed(() => historyIndex.value > 0);
    const canRedo = computed(() => historyIndex.value < history.value.length - 1);

    // --- ACTIONS --- 

    /**
     * Inicializa o store com os dados do planograma e estado inicial do editor.
     * @param initialData Dados iniciais do planograma (sem estado do editor).
     */
    function initialize(initialPlanogramData: Omit<PlanogramEditorState, 'scaleFactor' | 'showGrid'>) {
        console.log('Initializing editor store...', initialPlanogramData);
        const initialState: PlanogramEditorState = {
            ...JSON.parse(JSON.stringify(initialPlanogramData)), // Deep copy dos dados
            scaleFactor: 3, // Valor inicial padrão para escala
            showGrid: false, // Valor inicial padrão para grade
            // Inicializar outros estados do editor aqui
        };
        currentState.value = initialState;
        history.value = [{
            timestamp: Date.now(),
            state: JSON.parse(JSON.stringify(initialState)) // Salva o estado inicial completo
        }];
        historyIndex.value = 0;
        isTimeTraveling.value = false;
    }

    /**
     * Registra uma mudança no estado atual no histórico.
     * Esta função deve ser chamada APÓS cada mutação significativa no currentState.
     */
    function recordChange() {
        if (isTimeTraveling.value || !currentState.value) return;
        const newState = JSON.parse(JSON.stringify(currentState.value));
        if (historyIndex.value >= 0 && isEqual(newState, history.value[historyIndex.value].state)) return;
        if (historyIndex.value < history.value.length - 1) {
            history.value.splice(historyIndex.value + 1);
        }
        history.value.push({ timestamp: Date.now(), state: newState });
        historyIndex.value = history.value.length - 1;
    }

    /**
     * Desfaz a última alteração.
     */
    function undo() {
        if (canUndo.value) {
            isTimeTraveling.value = true;
            historyIndex.value--;
            currentState.value = JSON.parse(JSON.stringify(history.value[historyIndex.value].state));
            isTimeTraveling.value = false;
            console.log('Undo performed. Index:', historyIndex.value);
        }
    }

    /**
     * Refaz a última alteração desfeita.
     */
    function redo() {
        if (canRedo.value) {
            isTimeTraveling.value = true;
            historyIndex.value++;
            currentState.value = JSON.parse(JSON.stringify(history.value[historyIndex.value].state));
            isTimeTraveling.value = false;
            console.log('Redo performed. Index:', historyIndex.value);
        }
    }
    
    /**
     * TODO: Ação para salvar o estado atual no backend.
     */
    async function saveChanges() {
        if (!currentState.value) return;
        console.log('Saving changes...', currentState.value);
        // Implementar a chamada API aqui usando apiService
        // Ex: await apiService.put(`/api/plannerate/planograms/${currentState.value.id}`, currentState.value);
        // Após salvar, pode ser útil resetar o histórico ou marcar como salvo
        // Ex: history.value = [history.value[historyIndex.value]]; historyIndex.value = 0;
        alert('Funcionalidade de salvar ainda não implementada!');
    }
    
    /**
     * Atualiza uma propriedade específica do planograma.
     * Exemplo: updatePlanogramProperty('name', newName)
     */
    function updatePlanogramProperty(key: keyof PlanogramEditorState, value: any) {
        if (currentState.value) {
            (currentState.value as any)[key] = value;
            recordChange(); // Registra a mudança após a atualização
        }
    }
    
    function getGondola(gondolaId: string): Gondola | undefined {
        return currentState.value?.gondolas.find((gondola) => gondola.id === gondolaId);
    }

    /**
     * Adiciona uma nova gôndola ao estado atual.
     * @param newGondola - O objeto da nova gôndola retornada pela API.
     */
    function addGondola(newGondola: Gondola) {
        if (currentState.value) {
            // Garante que o array gondolas existe
            if (!Array.isArray(currentState.value.gondolas)) {
                currentState.value.gondolas = [];
            }
            currentState.value.gondolas.push(newGondola);
            recordChange(); // Registra a adição como uma mudança
            console.log('Gondola added to store:', newGondola);
        }
    }

    /**
     * Define o fator de escala no estado do editor.
     * @param newScale O novo fator de escala.
     */
    function setScaleFactor(newScale: number) {
        if (currentState.value && currentState.value.scaleFactor !== newScale) {
             // Aplicar limites aqui também para segurança
            const clampedScale = Math.max(2, Math.min(10, newScale));
            currentState.value.scaleFactor = clampedScale;
            recordChange(); // Registrar a mudança de estado do editor
        }
    }

    /**
     * Alterna a visibilidade da grade no estado do editor.
     */
    function toggleGrid() {
        if (currentState.value) {
            currentState.value.showGrid = !currentState.value.showGrid;
            recordChange(); // Registrar a mudança de estado do editor
        }
    }

    /**
     * Inverte a ordem das seções de uma gôndola específica no estado.
     * @param gondolaId O ID da gôndola cujas seções serão invertidas.
     */
    function invertGondolaSectionOrder(gondolaId: string) {
        if (!currentState.value) return;
        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        // Agora Section deve ser o tipo corretamente importado
        if (gondola && Array.isArray(gondola.sections) && gondola.sections.length > 1) {
            gondola.sections.reverse(); 
            console.log(`Ordem das seções invertida para a gôndola ${gondolaId}`);
            recordChange();
        } else {
            console.warn(`Não foi possível inverter seções: Gôndola ${gondolaId} não encontrada ou tem menos de 2 seções.`);
        }
    }

    /**
     * Define a ordem das seções para uma gôndola específica no estado.
     * Usado após operações como drag-and-drop.
     * @param gondolaId O ID da gôndola a ser atualizada.
     * @param newSections O array de seções na nova ordem.
     */
    function setGondolaSectionOrder(gondolaId: string, newSections: Section[]) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (gondola) {
            // Compara se a nova ordem é realmente diferente da atual para evitar registros desnecessários
            // (Opcional, mas bom para performance do histórico)
            const currentSectionIds = gondola.sections.map(s => s.id);
            const newSectionIds = newSections.map(s => s.id);
            if (JSON.stringify(currentSectionIds) === JSON.stringify(newSectionIds)) {
                console.log('Ordem das seções não mudou, nenhum registro no histórico.');
                return; // Ordem não mudou
            }

            // Atualiza o array de seções com a nova ordem
            // É importante passar uma cópia para garantir a reatividade, 
            // embora newSections já deva ser um novo array vindo do draggable.
            gondola.sections = [...newSections]; 
            console.log(`Nova ordem das seções definida para a gôndola ${gondolaId}`);
            recordChange(); // Registra a mudança
        } else {
            console.warn(`Não foi possível definir ordem das seções: Gôndola ${gondolaId} não encontrada.`);
        }
    }

    /**
     * Remove uma seção específica de uma gôndola no estado.
     * @param gondolaId O ID da gôndola da qual remover a seção.
     * @param sectionId O ID da seção a ser removida.
     */
    function removeSectionFromGondola(gondolaId: string, sectionId: string) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (gondola && Array.isArray(gondola.sections)) {
            const initialLength = gondola.sections.length;
            // Filtra o array de seções, mantendo apenas as que NÃO têm o ID correspondente
            gondola.sections = gondola.sections.filter(s => s.id !== sectionId);
            
            // Verifica se alguma seção foi realmente removida antes de registrar
            if (gondola.sections.length < initialLength) {
                console.log(`Seção ${sectionId} removida da gôndola ${gondolaId}`);
                recordChange(); // Registra a mudança
            } else {
                console.warn(`Seção ${sectionId} não encontrada na gôndola ${gondolaId} para remoção.`);
            }
        } else {
            console.warn(`Não foi possível remover seção: Gôndola ${gondolaId} não encontrada ou não possui seções.`);
        }
    }

    /**
     * Inverte a ordem das prateleiras de uma seção específica no estado.
     * @param gondolaId O ID da gôndola que contém a seção.
     * @param sectionId O ID da seção cujas prateleiras serão invertidas.
     */
    function invertShelvesInSection(gondolaId: string, sectionId: string) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) {
            console.warn(`Não foi possível inverter prateleiras: Gôndola ${gondolaId} não encontrada.`);
            return;
        }

        const section = gondola.sections.find(s => s.id === sectionId);
        if (!section || !Array.isArray(section.shelves) || section.shelves.length <= 1) {
            console.warn(`Não foi possível inverter prateleiras: Seção ${sectionId} não encontrada ou tem menos de 2 prateleiras.`);
            return;
        }

        // Implementar lógica de inversão de posição similar à do sectionStore
        try {
            // 1. Criar cópia e ordenar pela posição atual
            const sortedShelvesCopy = [...section.shelves].sort((a, b) => a.shelf_position - b.shelf_position);
            
            // 2. Armazenar posições originais ordenadas
            const originalPositions = sortedShelvesCopy.map(shelf => shelf.shelf_position);

            // 3. Criar mapa de ID para nova posição invertida
            const newPositionsMap = new Map<string, number>();
            sortedShelvesCopy.forEach((shelf, index) => {
                const newPosition = originalPositions[originalPositions.length - 1 - index];
                newPositionsMap.set(shelf.id, newPosition);
            });

            let changed = false;
            // 4. Atualizar as posições das prateleiras DENTRO do currentState
            section.shelves.forEach(shelfInState => {
                const newPosition = newPositionsMap.get(shelfInState.id);
                if (newPosition !== undefined && shelfInState.shelf_position !== newPosition) {
                    shelfInState.shelf_position = newPosition;
                    changed = true;
                }
            });

            if (changed) {
                 // Opcional: Reordenar o array no estado para talvez ajudar a reatividade visual?
                 // section.shelves.sort((a, b) => a.shelf_position - b.shelf_position);
                console.log(`Posições das prateleiras invertidas para a seção ${sectionId} na gôndola ${gondolaId}`);
                recordChange(); // Registra a mudança
            } else {
                console.log(`Posições das prateleiras já estavam invertidas ou erro no cálculo para seção ${sectionId}.`);
            }

        } catch (error) {
            console.error(`Erro ao calcular inversão de posição para seção ${sectionId}:`, error);
        }
    }

    /**
     * Adiciona uma nova prateleira a uma seção específica no estado.
     * @param gondolaId O ID da gôndola que contém a seção.
     * @param sectionId O ID da seção onde adicionar a prateleira.
     * @param newShelf O objeto completo da nova prateleira a ser adicionada.
     */
    function addShelfToSection(gondolaId: string, sectionId: string, newShelfData: Shelf) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) {
            console.warn(`Não foi possível adicionar prateleira: Gôndola ${gondolaId} não encontrada.`);
            return;
        }

        const section = gondola.sections.find(s => s.id === sectionId);
        if (!section) {
            console.warn(`Não foi possível adicionar prateleira: Seção ${sectionId} não encontrada.`);
            return;
        }

        if (!Array.isArray(section.shelves)) {
            section.shelves = [];
        }

        // Criar uma cópia e ajustar tipos antes de adicionar ao estado
        const shelfToAdd = { 
            ...newShelfData,
            // Garante que alignment seja string ou undefined, tratando null
            alignment: newShelfData.alignment === null ? undefined : newShelfData.alignment,
            // Fazer ajustes similares para outras propriedades se necessário
        };

        // Verificar se o tipo ajustado é compatível (TypeScript fará isso)
        section.shelves.push(shelfToAdd); 

        console.log(`Prateleira ${shelfToAdd.id} adicionada à seção ${sectionId} na gôndola ${gondolaId}`);
        recordChange();
    }

    // Adicione aqui mais ações para manipular gondolas, seções, prateleiras, etc.
    // Ex: addGondola, updateSection, removeShelf, addProductToLayer...
    // Cada uma dessas ações deve modificar `currentState.value` e chamar `recordChange()`

    // --- WATCHERS --- 

    // Observador para registrar mudanças automaticamente (alternativa a chamar recordChange() manualmente)
    // Cuidado: Pode ser muito granular e gerar muitos registros. 
    // Pode ser melhor chamar recordChange() explicitamente após operações significativas.
    // watch(currentState, (newState, oldState) => {
    //     if (!isTimeTraveling.value && !isEqual(newState, oldState)) {
    //         recordChange();
    //     }
    // }, { deep: true });

    return {
        currentState,
        history,
        historyIndex,
        hasChanges,
        canUndo,
        canRedo,
        currentScaleFactor,
        isGridVisible,
        initialize,
        recordChange, // Expor caso precise chamar manualmente
        undo,
        redo,
        saveChanges,
        updatePlanogramProperty,
        getGondola,
        addGondola, // Expor a nova action
        setScaleFactor,
        toggleGrid,
        invertGondolaSectionOrder, // <-- Expor a nova action
        setGondolaSectionOrder, // <-- Expor a nova action
        removeSectionFromGondola, // <-- Expor a nova action
        invertShelvesInSection, // <-- Expor a nova action
        addShelfToSection, // <-- Expor a nova action
    };
});
