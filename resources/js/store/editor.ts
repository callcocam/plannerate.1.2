import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { isEqual } from 'lodash-es'; // Usaremos lodash para comparações profundas
import type { Gondola } from '@plannerate/types/gondola'; // Certifique-se de que Section está tipado
import type { Section } from '@plannerate/types/sections'; // Ajustar path se necessário
import type { Shelf } from '@plannerate/types/shelves'; // <-- Importar tipo Shelf
import type { Segment } from '@plannerate/types/segment'; // <-- Importar tipo Segment

// Interface para representar o estado do planograma no editor
interface PlanogramEditorState {
    id: string | null;
    name: string | null;
    gondolas: Gondola[]; // Gondola já deve conter `sections: Section[]` a partir da sua definição
    currentGondola: Gondola | null;
    // Adicionar estado de visualização do editor
    scaleFactor: number;
    showGrid: boolean;
    isLoading: boolean;
    error: string | null;
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
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const currentGondola = ref<Gondola | null>(null);

    // --- GETTERS --- 

    const initialPlanogram = computed(() => history.value[0]?.state || null);
    const currentScaleFactor = computed(() => currentState.value?.scaleFactor ?? 3); // Default 3
    const isGridVisible = computed(() => currentState.value?.showGrid ?? false); // Default false
    const getCurrentGondola = computed(() => currentGondola.value);

    const hasChanges = computed(() => {
        if (historyIndex.value < 0 || !currentState.value) return false;
        // Compara o estado atual com o estado inicial salvo no histórico
        return !isEqual(currentState.value, initialPlanogram.value);
    });
    const canUndo = computed(() => historyIndex.value > 0);
    const canRedo = computed(() => historyIndex.value < history.value.length - 1);

    // ---> NOVO GETTER COMPUTADO <---
    const currentGondolaId = computed((): string | null => {
        // Assume que o editor tem um conceito de gôndola "ativa" ou que podemos derivar
        // Aqui, vamos simplificar e pegar o ID da primeira gôndola se houver alguma.
        // Se a lógica for mais complexa (ex: gôndola selecionada), ajuste aqui.
        if (currentState.value && currentState.value.gondolas && currentState.value.gondolas.length > 0) {
            // Idealmente, teríamos um state.activeGondolaId ou similar.
            // Por enquanto, vamos usar a primeira gôndola como exemplo.
            // TODO: Implementar lógica para determinar a gôndola ativa corretamente.
            console.warn('Logic for currentGondolaId needs refinement. Using the first gondola ID for now.');
            return currentState.value.gondolas[0].id; // Acessa o currentState diretamente
        }
        return null;
    });

    // --- ACTIONS --- 

    // Vamos criar uma função para calcular o tabindex de cada seção
    function calculateTabindex(gondola: Gondola) {
        if (!gondola) return 0;
        let tabindex = 0;
        return gondola.sections.map((section) => {
            return section.shelves.map((shelf) => {
                return shelf.segments.map((segment) => {
                    tabindex = tabindex + 1;
                    return segment.tabindex = tabindex;
                });
            });
        });
    }

    /**
     * setIsLoading - Define o estado de carregamento do editor.
     */
    function setIsLoading(newIsLoading: boolean) {
        isLoading.value = newIsLoading;
    }
    /**
     * setError - Define o estado de erro do editor.    
     */
    function setError(newError: string | null) {
        error.value = newError;
    }
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

    function setCurrentGondola(gondola: Gondola) {
        calculateTabindex(gondola);
        currentGondola.value = gondola;
    }

    /**
     * Registra uma mudança no estado atual no histórico.
     * Esta função deve ser chamada APÓS cada mutação significativa no currentState.
     */
    function recordChange() {
        setIsLoading(true);
        if (isTimeTraveling.value || !currentState.value) return;
        const newState = JSON.parse(JSON.stringify(currentState.value));
        if (historyIndex.value >= 0 && isEqual(newState, history.value[historyIndex.value].state)) return;
        if (historyIndex.value < history.value.length - 1) {
            history.value.splice(historyIndex.value + 1);
        }
        history.value.push({ timestamp: Date.now(), state: newState });
        historyIndex.value = history.value.length - 1;
        setIsLoading(false);
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

    /**
     * Define o alinhamento para uma seção específica no estado.
     * @param gondolaId O ID da gôndola que contém a seção.
     * @param sectionId O ID da seção a ser atualizada.
     * @param alignment O novo valor de alinhamento ('left', 'right', 'center', 'justify').
     */
    function setSectionAlignment(gondolaId: string, sectionId: string, alignment: string | null) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) {
            console.warn(`Não foi possível definir alinhamento: Gôndola ${gondolaId} não encontrada.`);
            return;
        }

        const section = gondola.sections.find(s => s.id === sectionId);
        if (!section) {
            console.warn(`Não foi possível definir alinhamento: Seção ${sectionId} não encontrada.`);
            return;
        }

        // Verifica se o alinhamento realmente mudou
        if (section.alignment !== alignment) {
            section.alignment = alignment;
            console.log(`Alinhamento da seção ${sectionId} definido para ${alignment}`);
            recordChange(); // Registra a mudança
        } else {
            console.log(`Alinhamento da seção ${sectionId} já era ${alignment}.`);
        }
    }

    /**
     * Define a posição vertical (shelf_position) para uma prateleira específica no estado.
     * @param gondolaId O ID da gôndola que contém a seção.
     * @param sectionId O ID da seção que contém a prateleira.
     * @param shelfId O ID da prateleira a ser atualizada.
     * @param newPosition O novo valor para shelf_position.
     */
    function setShelfPosition(gondolaId: string, sectionId: string, shelfId: string, newPosition: { shelf_position: number, shelf_x_position: number }) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) {
            console.warn(`setShelfPosition: Gôndola ${gondolaId} não encontrada.`);
            return;
        }

        const section = gondola.sections.find(s => s.id === sectionId);
        if (!section || !Array.isArray(section.shelves)) {
            console.warn(`setShelfPosition: Seção ${sectionId} não encontrada ou sem prateleiras.`);
            return;
        }

        const shelf = section.shelves.find(sh => sh.id === shelfId);
        if (!shelf) {
            console.warn(`setShelfPosition: Prateleira ${shelfId} não encontrada.`);
            return;
        }
        const { shelf_position, shelf_x_position } = newPosition;
        // Verifica se a posição realmente mudou
        // Comparar números pode ter problemas de precisão, talvez arredondar ou usar tolerância?
        // Por simplicidade, faremos comparação direta por enquanto.
        if (shelf.shelf_position !== shelf_position || shelf.shelf_x_position !== shelf_x_position) {
            shelf.shelf_position = shelf_position;
            shelf.shelf_x_position = shelf_x_position;
            console.log(`Posição da prateleira ${shelfId} definida para ${shelf_position}`);

            // Opcional: Reordenar o array shelves visualmente se a ordem importar no template?
            // section.shelves.sort((a, b) => a.shelf_position - b.shelf_position);

            recordChange(); // Registra a mudança
        } else {
            console.log(`Posição da prateleira ${shelfId} já era ${shelf_position}.`);
        }
    }

    /**
     * Adiciona um novo segmento a uma prateleira específica no estado.
     * @param gondolaId O ID da gôndola.
     * @param sectionId O ID da seção.
     * @param shelfId O ID da prateleira.
     * @param newSegment O objeto Segment a ser adicionado.
     */
    function addSegmentToShelf(gondolaId: string, sectionId: string, shelfId: string, newSegment: Segment) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) {
            console.warn(`addSegmentToShelf: Gôndola ${gondolaId} não encontrada.`);
            return;
        }

        const section = gondola.sections.find(s => s.id === sectionId);
        if (!section || !Array.isArray(section.shelves)) {
            console.warn(`addSegmentToShelf: Seção ${sectionId} não encontrada ou sem prateleiras.`);
            return;
        }

        const shelf = section.shelves.find(sh => sh.id === shelfId);
        if (!shelf) {
            console.warn(`addSegmentToShelf: Prateleira ${shelfId} não encontrada.`);
            return;
        }

        if (!Array.isArray(shelf.segments)) {
            shelf.segments = [];
        }

        // Verificar se o ID do segmento existe antes de adicionar
        if (typeof newSegment.id === 'string') {
            shelf.segments.push(newSegment as any); // Usar 'as any' se o linter ainda reclamar da assinatura complexa
            // O ideal seria garantir que o tipo Segment interno corresponda perfeitamente
            console.log(`Segmento ${newSegment.id} adicionado à prateleira ${shelfId}`);
            recordChange();
        } else {
            console.error('addSegmentToShelf: Tentativa de adicionar segmento sem ID.', newSegment);
        }
    }

    /**
     * Define a ordem dos segmentos para uma prateleira específica no estado.
     * Usado após operações como drag-and-drop de segmentos.
     * @param gondolaId O ID da gôndola.
     * @param sectionId O ID da seção.
     * @param shelfId O ID da prateleira a ser atualizada.
     * @param newSegments O array de segmentos na nova ordem.
     */
    function setShelfSegmentsOrder(gondolaId: string, sectionId: string, shelfId: string, newSegments: Segment[]) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) {
            console.warn(`setShelfSegmentsOrder: Gôndola ${gondolaId} não encontrada.`);
            return;
        }
        const section = gondola.sections.find(s => s.id === sectionId);
        if (!section || !Array.isArray(section.shelves)) {
            console.warn(`setShelfSegmentsOrder: Seção ${sectionId} não encontrada ou sem prateleiras.`);
            return;
        }
        const shelf = section.shelves.find(sh => sh.id === shelfId);
        if (!shelf || !Array.isArray(shelf.segments)) {
            console.warn(`setShelfSegmentsOrder: Prateleira ${shelfId} não encontrada ou sem segmentos.`);
            return;
        }

        // Compara se a nova ordem de IDs é diferente da atual
        const currentSegmentIds = shelf.segments.map(seg => seg.id);
        const newSegmentIds = newSegments.map(seg => seg.id);
        if (JSON.stringify(currentSegmentIds) === JSON.stringify(newSegmentIds)) {
            console.log(`Ordem dos segmentos na prateleira ${shelfId} não mudou.`);
            return;
        }

        // Verificar se todos os segmentos recebidos têm ID (precaução)
        if (!newSegments.every(seg => typeof seg.id === 'string')) {
            console.error('setShelfSegmentsOrder: Tentativa de definir ordem com segmento sem ID.', newSegments);
            return;
        }

        // Atualiza o array de segmentos
        // Usar 'as any' para contornar a complexidade da checagem de tipo profunda do linter
        shelf.segments = [...newSegments] as any;
        console.log(`Nova ordem dos segmentos definida para a prateleira ${shelfId}`);
        recordChange();
    }

    /**
     * Define o alinhamento para uma prateleira específica no estado.
     * @param gondolaId O ID da gôndola.
     * @param sectionId O ID da seção.
     * @param shelfId O ID da prateleira a ser atualizada.
     * @param alignment O novo valor de alinhamento.
     */
    function setShelfAlignment(gondolaId: string, sectionId: string, shelfId: string, alignment: string | null) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) {
            console.warn(`setShelfAlignment: Gôndola ${gondolaId} não encontrada.`);
            return;
        }
        const section = gondola.sections.find(s => s.id === sectionId);
        if (!section || !Array.isArray(section.shelves)) {
            console.warn(`setShelfAlignment: Seção ${sectionId} não encontrada ou sem prateleiras.`);
            return;
        }
        const shelf = section.shelves.find(sh => sh.id === shelfId);
        if (!shelf) {
            console.warn(`setShelfAlignment: Prateleira ${shelfId} não encontrada.`);
            return;
        }

        // Verifica se o alinhamento realmente mudou
        if (shelf.alignment !== alignment) {
            shelf.alignment = alignment;
            console.log(`Alinhamento da prateleira ${shelfId} definido para ${alignment}`);
            recordChange(); // Registra a mudança
        } else {
            console.log(`Alinhamento da prateleira ${shelfId} já era ${alignment}.`);
        }
    }

    /**
     * Transfere um segmento de uma prateleira para outra dentro do estado do editor.
     * @param gondolaId ID da gôndola.
     * @param oldSectionId ID da seção de origem.
     * @param oldShelfId ID da prateleira de origem.
     * @param newSectionId ID da seção de destino.
     * @param newShelfId ID da prateleira de destino.
     * @param segmentId ID do segmento a ser transferido.
     * @param newPositionX Opcional: Nova posição X relativa na prateleira de destino.
     * @param newOrdering Opcional: Nova ordem na prateleira de destino.
     */
    function transferSegmentBetweenShelves(
        gondolaId: string,
        oldSectionId: string,
        oldShelfId: string,
        newSectionId: string,
        newShelfId: string,
        segmentId: string,
        newPositionX?: number, // Supondo que a posição X seja relevante
        newOrdering?: number  // Supondo que a ordem precise ser definida
    ) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) {
            console.warn(`transferSegment: Gôndola ${gondolaId} não encontrada.`);
            return;
        }

        // Encontrar seção/prateleira de ORIGEM
        const oldSection = gondola.sections.find(s => s.id === oldSectionId);
        if (!oldSection || !Array.isArray(oldSection.shelves)) { /* erro */ return; }
        const oldShelf = oldSection.shelves.find(sh => sh.id === oldShelfId);
        if (!oldShelf || !Array.isArray(oldShelf.segments)) { /* erro */ return; }

        // Encontrar seção/prateleira de DESTINO
        const newSection = gondola.sections.find(s => s.id === newSectionId);
        if (!newSection || !Array.isArray(newSection.shelves)) { /* erro */ return; }
        const newShelf = newSection.shelves.find(sh => sh.id === newShelfId);
        if (!newShelf) { /* erro */ return; }
        if (!Array.isArray(newShelf.segments)) {
            newShelf.segments = []; // Inicializa se não existir
        }

        // Encontrar e remover segmento da prateleira de origem
        const segmentIndex = oldShelf.segments.findIndex(seg => seg.id === segmentId);
        if (segmentIndex === -1) {
            console.warn(`transferSegment: Segmento ${segmentId} não encontrado na prateleira ${oldShelfId}.`);
            return;
        }
        const segmentToMove = oldShelf.segments.splice(segmentIndex, 1)[0];
        if (!segmentToMove) { /* erro */ return; }

        // Atualizar dados do segmento movido
        segmentToMove.shelf_id = newShelfId;
        if (newPositionX !== undefined) {
            // Usar 'position' ou o campo correto para posição X
            (segmentToMove as any).position = newPositionX;
        }
        // Atualizar ordem (anexar ao final se não especificado)
        segmentToMove.ordering = newOrdering ?? newShelf.segments.length + 1;

        // Adicionar segmento à prateleira de destino
        newShelf.segments.push(segmentToMove);

        // Opcional: Recalcular/Reordenar 'ordering' para ambas as prateleiras
        // oldShelf.segments.forEach((seg, index) => seg.ordering = index + 1);
        // newShelf.segments.sort((a, b) => a.ordering - b.ordering); // Garante ordem se newOrdering foi usado

        console.log(`Segmento ${segmentId} transferido de ${oldShelfId} para ${newShelfId}`);
        recordChange(); // Registra a mudança
    }

    /**
     * Remove uma prateleira específica de uma seção no estado.
     * @param gondolaId O ID da gôndola.
     * @param sectionId O ID da seção.
     * @param shelfId O ID da prateleira a ser removida.
     */
    function removeShelfFromSection(gondolaId: string, sectionId: string, shelfId: string) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) {
            console.warn(`removeShelfFromSection: Gôndola ${gondolaId} não encontrada.`);
            return;
        }

        const section = gondola.sections.find(s => s.id === sectionId);
        if (!section || !Array.isArray(section.shelves)) {
            console.warn(`removeShelfFromSection: Seção ${sectionId} não encontrada ou sem prateleiras.`);
            return;
        }

        const initialLength = section.shelves.length;
        // Filtra o array de prateleiras, mantendo apenas as que NÃO têm o ID correspondente
        section.shelves = section.shelves.filter(sh => sh.id !== shelfId);

        // Verifica se alguma prateleira foi realmente removida antes de registrar
        if (section.shelves.length < initialLength) {
            console.log(`Prateleira ${shelfId} removida da seção ${sectionId}`);
            recordChange(); // Registra a mudança
        } else {
            console.warn(`Prateleira ${shelfId} não encontrada na seção ${sectionId} para remoção.`);
        }
    }

    /**
     * Atualiza os dados de uma seção específica no estado.
     * @param gondolaId O ID da gôndola.
     * @param sectionId O ID da seção a ser atualizada.
     * @param sectionData Objeto contendo as propriedades da seção a serem atualizadas.
     */
    function updateSectionData(gondolaId: string, sectionId: string, sectionData: Partial<Section>) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) {
            console.warn(`updateSectionData: Gôndola ${gondolaId} não encontrada.`);
            return;
        }

        const sectionIndex = gondola.sections.findIndex(s => s.id === sectionId);
        if (sectionIndex === -1) {
            console.warn(`updateSectionData: Seção ${sectionId} não encontrada.`);
            return;
        }

        // Mescla os dados antigos com os novos dados
        // Usar Object.assign para garantir que apenas as propriedades fornecidas sejam atualizadas
        const originalSection = gondola.sections[sectionIndex];
        const updatedSection = Object.assign({}, originalSection, sectionData);

        // Verifica se houve realmente alguma mudança (comparação superficial)
        // Para uma comparação profunda, usar isEqual do lodash seria mais robusto
        if (JSON.stringify(originalSection) !== JSON.stringify(updatedSection)) {
            // Atualiza a seção no array do estado
            gondola.sections[sectionIndex] = updatedSection;
            console.log(`Dados da seção ${sectionId} atualizados.`);
            recordChange(); // Registra a mudança
        } else {
            console.log(`Dados da seção ${sectionId} não foram alterados.`);
        }
    }

    /**
     * Atualiza os dados de uma prateleira específica no estado.
     * @param gondolaId O ID da gôndola.
     * @param sectionId O ID da seção que contém a prateleira.
     * @param shelfId O ID da prateleira a ser atualizada.
     * @param shelfData Objeto contendo as propriedades da prateleira a serem atualizadas.
     */
    function updateShelfData(gondolaId: string, sectionId: string, shelfId: string, shelfData: Partial<Shelf>) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) {
            console.warn(`updateShelfData: Gôndola ${gondolaId} não encontrada.`);
            return;
        }

        const section = gondola.sections.find(s => s.id === sectionId);
        if (!section || !Array.isArray(section.shelves)) {
            console.warn(`updateShelfData: Seção ${sectionId} não encontrada ou sem prateleiras.`);
            return;
        }

        const shelfIndex = section.shelves.findIndex(sh => sh.id === shelfId);
        if (shelfIndex === -1) {
            console.warn(`updateShelfData: Prateleira ${shelfId} não encontrada.`);
            return;
        }

        // Mescla os dados antigos com os novos dados
        const originalShelf = section.shelves[shelfIndex];
        // Garante que propriedades nulas ou indefinidas em shelfData não sobrescrevam valores existentes inesperadamente,
        // a menos que sejam explicitamente parte de Partial<Shelf>
        const updatedShelf = {
            ...originalShelf,
            ...shelfData,
            alignment: shelfData.alignment === null ? undefined : shelfData.alignment ?? originalShelf.alignment // Converte null para undefined
        };

        // Remove 'segments' dos dados recebidos se não for para ser atualizado aqui
        // Assumindo que 'segments' são gerenciados por outras ações (addSegment, setSegmentsOrder, etc)
        // delete updatedShelf.segments; // Descomentar se necessário

        // Compara usando isEqual para uma verificação profunda
        if (!isEqual(originalShelf, updatedShelf)) {
            // Atualiza a prateleira no array do estado
            section.shelves[shelfIndex] = updatedShelf;
            console.log(`Dados da prateleira ${shelfId} atualizados.`);
            recordChange(); // Registra a mudança
        } else {
            console.log(`Dados da prateleira ${shelfId} não foram alterados.`);
        }
    }

    /**
     * Transfere uma prateleira inteira de uma seção para outra dentro da mesma gôndola.
     * @param gondolaId ID da gôndola.
     * @param oldSectionId ID da seção de origem.
     * @param newSectionId ID da seção de destino.
     * @param shelfId ID da prateleira a ser transferida.
     */
    function transferShelfBetweenSections(gondolaId: string, oldSectionId: string, newSectionId: string, shelfId: string) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) {
            console.warn(`transferShelfBetweenSections: Gôndola ${gondolaId} não encontrada.`);
            return;
        }

        // Encontrar seção de origem e índice da prateleira
        const oldSection = gondola.sections.find(s => s.id === oldSectionId);
        if (!oldSection || !Array.isArray(oldSection.shelves)) {
            console.warn(`transferShelfBetweenSections: Seção de origem ${oldSectionId} não encontrada ou sem prateleiras.`);
            return;
        }
        const shelfIndex = oldSection.shelves.findIndex(sh => sh.id === shelfId);
        if (shelfIndex === -1) {
            console.warn(`transferShelfBetweenSections: Prateleira ${shelfId} não encontrada na seção ${oldSectionId}.`);
            return;
        }

        // Encontrar seção de destino
        const newSection = gondola.sections.find(s => s.id === newSectionId);
        if (!newSection) {
            console.warn(`transferShelfBetweenSections: Seção de destino ${newSectionId} não encontrada.`);
            return;
        }
        if (!Array.isArray(newSection.shelves)) {
            newSection.shelves = []; // Inicializa se não existir
        }

        // Remover a prateleira da seção antiga e obter o objeto
        const shelfToMove = oldSection.shelves.splice(shelfIndex, 1)[0];
        if (!shelfToMove) { return; } // Segurança

        // Atualizar dados da prateleira movida
        shelfToMove.section_id = newSectionId;
        shelfToMove.shelf_x_position = -4; // Resetar posição X relativa à nova seção
        // Resetar outras propriedades se necessário (ex: ordering?)
        // shelfToMove.ordering = newSection.shelves.length + 1; // Ou recalcular depois

        // Adicionar a prateleira à nova seção
        newSection.shelves.push(shelfToMove);

        // Opcional: Recalcular ordenação se necessário
        // oldSection.shelves.forEach((sh, index) => sh.ordering = index + 1);
        // newSection.shelves.forEach((sh, index) => sh.ordering = index + 1);

        console.log(`Prateleira ${shelfId} transferida de ${oldSectionId} para ${newSectionId}`);
        recordChange(); // Registra a mudança
    }

    /**
     * Atualiza a quantidade de uma camada (layer) específica dentro de um segmento.
     * @param gondolaId ID da Gôndola.
     * @param sectionId ID da Seção.
     * @param shelfId ID da Prateleira.
     * @param segmentId ID do Segmento.
     * @param layerId ID da Camada (geralmente igual ao ID do produto).
     * @param newQuantity Nova quantidade para a camada.
     */
    function updateLayerQuantity(gondolaId: string, sectionId: string, shelfId: string, segmentId: string, layerId: string, newQuantity: number) {
        if (!currentState.value) return;
        if (newQuantity < 0) {
            console.warn(`updateLayerQuantity: Tentativa de definir quantidade negativa (${newQuantity}). Abortando.`);
            return; // Não permitir quantidade negativa
        }

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) { console.warn(`updateLayerQuantity: Gôndola ${gondolaId} não encontrada.`); return; }

        const section = gondola.sections.find(s => s.id === sectionId);
        if (!section || !Array.isArray(section.shelves)) { console.warn(`updateLayerQuantity: Seção ${sectionId} não encontrada.`); return; }

        const shelf = section.shelves.find(sh => sh.id === shelfId);
        if (!shelf || !Array.isArray(shelf.segments)) { console.warn(`updateLayerQuantity: Prateleira ${shelfId} não encontrada.`); return; }

        const segment = shelf.segments.find(seg => seg.id === segmentId);
        if (!segment || !segment.layer) { console.warn(`updateLayerQuantity: Segmento ${segmentId} ou sua camada não encontrados.`); return; }
        setIsLoading(true);
        // Assumindo que layerId corresponde ao product.id dentro da layer do segmento
        // Se a estrutura for diferente (ex: layer tem seu próprio ID), ajuste aqui.
        if (segment.layer.product.id === layerId) {
            if (segment.layer.quantity !== newQuantity) {
                segment.layer.quantity = newQuantity;
                console.log(`Layer ${layerId} quantity updated to ${newQuantity} in segment ${segmentId}.`);
                recordChange(); // Registra a mudança
            } else {
                console.log(`Layer ${layerId} quantity already ${newQuantity}.`);
            }
        } else {
            console.warn(`updateLayerQuantity: Layer com ID ${layerId} não encontrada no segmento ${segmentId}.`);
        }
    }

    /**
     * Define o alinhamento padrão para uma gôndola específica no estado.
     * @param gondolaId O ID da gôndola a ser atualizada.
     * @param alignment O novo valor de alinhamento ('left', 'right', 'center', 'justify', ou null).
     */
    function setGondolaAlignment(gondolaId: string, alignment: string | null) {
        if (!currentState.value) return;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) {
            console.warn(`setGondolaAlignment: Gôndola ${gondolaId} não encontrada.`);
            return;
        }
        setIsLoading(true);
        // Verifica se o alinhamento realmente mudou
        // Converte null para undefined para consistência, se necessário
        const newAlignment = alignment === null ? undefined : alignment;
        if (gondola.alignment !== newAlignment) {
            gondola.alignment = newAlignment;
            console.log(`Alinhamento da gôndola ${gondolaId} definido para ${newAlignment}`);
            recordChange(); // Registra a mudança
        } else {
            console.log(`Alinhamento da gôndola ${gondolaId} já era ${newAlignment}.`);
        }
        setIsLoading(false);
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
        setSectionAlignment, // <-- Expor a nova action
        setShelfPosition, // <-- Expor a nova action
        addSegmentToShelf, // <-- Expor a nova action
        setShelfSegmentsOrder, // <-- Expor a nova action
        setShelfAlignment, // <-- Expor a nova action
        transferSegmentBetweenShelves, // <-- Expor a nova action
        removeShelfFromSection, // <-- Expor a nova action
        updateSectionData, // <-- Expor a nova action
        updateShelfData, // <-- EXPOR A NOVA ACTION
        transferShelfBetweenSections, // <-- EXPOR A NOVA ACTION
        updateLayerQuantity, // <-- EXPOR A NOVA ACTION
        setGondolaAlignment, // <-- EXPOR A NOVA ACTION
        currentGondolaId, // <-- EXPOR O COMPUTED
        getCurrentGondola, // <-- EXPOR O COMPUTED
        setCurrentGondola, // <-- EXPOR A ACTION
        isLoading, // <-- EXPOR O COMPUTED
        error, // <-- EXPOR O COMPUTED
        setIsLoading, // <-- EXPOR A ACTION
        setError, // <-- EXPOR A ACTION
        calculateTabindex, // <-- EXPOR A ACTION
    };
});
