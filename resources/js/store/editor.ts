import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { isEqual } from 'lodash-es'; // Usamos lodash para comparações profundas
import type { Gondola } from '@plannerate/types/gondola';
import type { Section } from '@plannerate/types/sections';
import type { Shelf } from '@plannerate/types/shelves';
import type { Segment } from '@plannerate/types/segment';

// =========================================================
// INTERFACES
// =========================================================

/**
 * Interface para representar o estado do planograma no editor
 */
interface PlanogramEditorState {
    id: string | null;
    name: string | null;
    gondolas: Gondola[];
    currentGondola: Gondola | null;
    scaleFactor: number;
    showGrid: boolean;
    isLoading: boolean;
    error: string | null;
    // Propriedades adicionais podem ser incluídas conforme necessário:
    // selectedItemId: string | null;
    // selectedItemType: 'gondola' | 'section' | 'shelf' | 'layer' | null;
}

/**
 * Interface para representar um snapshot do estado para o histórico de ações
 */
interface HistoryEntry {
    timestamp: number;
    state: PlanogramEditorState;
}

// =========================================================
// STORE DEFINITION
// =========================================================

export const useEditorStore = defineStore('editor', () => {
    // =========================================================
    // STATE
    // =========================================================

    // Estado principal do editor
    const currentState = ref<PlanogramEditorState | null>(null);
    const currentGondola = ref<Gondola | null>(null);

    // Gerenciamento de histórico para undo/redo
    const history = ref<HistoryEntry[]>([]);
    const historyIndex = ref<number>(-1);
    const isTimeTraveling = ref(false); // Flag para evitar que watchers do histórico adicionem ao histórico
    const MAX_HISTORY_SIZE = 5; // Limite máximo de entradas no histórico

    // Estado de UI e erros
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    // =========================================================
    // GETTERS
    // =========================================================

    // Estado inicial do planograma (para comparação de alterações)
    const initialPlanogram = computed(() => history.value[0]?.state || null);

    // Configurações visuais
    const currentScaleFactor = computed(() => currentState.value?.scaleFactor ?? 3); // Default 3
    const isGridVisible = computed(() => currentState.value?.showGrid ?? false); // Default false

    // Controle de gondola atual
    const getCurrentGondola = computed(() => currentGondola.value);

    // Verificações de estado para undo/redo
    const hasChanges = computed(() => {
        if (historyIndex.value < 0 || !currentState.value) return false;
        return !isEqual(currentState.value, initialPlanogram.value);
    });
    const canUndo = computed(() => historyIndex.value > 0);
    const canRedo = computed(() => historyIndex.value < history.value.length - 1);

    // ID da gôndola atual
    const currentGondolaId = computed((): string | null => {
        if (currentState.value?.gondolas?.length && currentState.value.gondolas.length > 0) {
            // Idealmente, teríamos um state.activeGondolaId
            // Este getter precisa ser refinado para determinar a gôndola ativa de forma mais adequada
            return currentState.value.gondolas[0].id;
        }
        return null;
    });

    // =========================================================
    // FUNÇÕES UTILITÁRIAS (HELPERS)
    // =========================================================

    /**
     * Verifica e localiza uma gôndola pelo ID
     * @param gondolaId ID da gôndola a ser localizada
     * @param operationName Nome da operação para exibir em mensagens de erro
     * @returns A gôndola encontrada ou null
     */
    function findGondola(gondolaId: string, operationName: string): Gondola | null {
        if (!currentState.value) return null;

        const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (!gondola) {
            console.warn(`${operationName}: Gôndola ${gondolaId} não encontrada.`);
            return null;
        }
        return gondola;
    }

    /**
     * Verifica e localiza uma seção dentro de uma gôndola
     * @param gondola Gôndola contendo a seção
     * @param sectionId ID da seção a ser localizada
     * @param operationName Nome da operação para exibir em mensagens de erro
     * @returns A seção encontrada ou null
     */
    function findSection(gondola: Gondola, sectionId: string, operationName: string): Section | null {
        const section = gondola.sections.find(s => s.id === sectionId);
        if (!section) {
            console.warn(`${operationName}: Seção ${sectionId} não encontrada.`);
            return null;
        }

        if (!Array.isArray(section.shelves)) {
            section.shelves = []; // Inicializa o array de prateleiras se não existir
        }

        return section;
    }

    /**
     * Verifica e localiza uma prateleira dentro de uma seção
     * @param section Seção contendo a prateleira
     * @param shelfId ID da prateleira a ser localizada
     * @param operationName Nome da operação para exibir em mensagens de erro
     * @returns A prateleira encontrada ou null
     */
    function findShelf(section: Section, shelfId: string, operationName: string): Shelf | null {
        const shelf = section.shelves.find(sh => sh.id === shelfId);
        if (!shelf) {
            console.warn(`${operationName}: Prateleira ${shelfId} não encontrada.`);
            return null;
        }

        if (!Array.isArray(shelf.segments)) {
            shelf.segments = []; // Inicializa o array de segmentos se não existir
        }

        return shelf;
    }

    /**
     * Verifica e localiza um segmento dentro de uma prateleira
     * @param shelf Prateleira contendo o segmento
     * @param segmentId ID do segmento a ser localizado
     * @param operationName Nome da operação para exibir em mensagens de erro
     * @returns O segmento encontrado ou null
     */
    function findSegment(shelf: Shelf, segmentId: string, operationName: string): Segment | null {
        const segment = shelf.segments.find(seg => seg.id === segmentId);
        if (!segment) {
            console.warn(`${operationName}: Segmento ${segmentId} não encontrado.`);
            return null;
        }
        return segment;
    }

    /**
     * Localiza uma gôndola, seção e prateleira com uma única chamada
     * @param gondolaId ID da gôndola
     * @param sectionId ID da seção
     * @param shelfId ID da prateleira
     * @param operationName Nome da operação para mensagens de erro
     * @returns Um objeto contendo as referências encontradas ou null se alguma não for encontrada
     */
    function findPath(
        gondolaId: string,
        sectionId: string,
        shelfId: string | null = null,
        operationName: string
    ): { gondola: Gondola, section: Section, shelf?: Shelf } | null {

        // Localiza a gôndola
        const gondola = findGondola(gondolaId, operationName);
        if (!gondola) return null;

        // Localiza a seção
        const section = findSection(gondola, sectionId, operationName);
        if (!section) return null;

        // Se um ID de prateleira foi fornecido, localiza a prateleira
        if (shelfId) {
            const shelf = findShelf(section, shelfId, operationName);
            if (!shelf) return null;
            return { gondola, section, shelf };
        }

        return { gondola, section };
    }

    // =========================================================
    // ACTIONS
    // =========================================================

    /**
     * Calcula o tabindex de cada seção em uma gôndola
     * Utilizado para navegação por teclado na interface
     */
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
     * Define o estado de carregamento do editor
     */
    function setIsLoading(newIsLoading: boolean) {
        isLoading.value = newIsLoading;
    }

    /**
     * Define o estado de erro do editor
     */
    function setError(newError: string | null) {
        error.value = newError;
    }

    /**
     * Inicializa o store com os dados do planograma e estado inicial do editor
     * @param initialPlanogramData Dados iniciais do planograma (sem estado do editor)
     */
    function initialize(initialPlanogramData: Omit<PlanogramEditorState, 'scaleFactor' | 'showGrid'>) {
        console.log('Inicializando editor store...', initialPlanogramData);

        // Cria uma cópia profunda dos dados iniciais e adiciona valores padrão
        const initialState: PlanogramEditorState = {
            ...JSON.parse(JSON.stringify(initialPlanogramData)),
            scaleFactor: 3, // Valor inicial padrão para escala
            showGrid: false, // Valor inicial padrão para grade
        };

        // Define o estado atual e o histórico inicial
        currentState.value = initialState;
        history.value = [{
            timestamp: Date.now(),
            state: JSON.parse(JSON.stringify(initialState))
        }];
        historyIndex.value = 0;
        isTimeTraveling.value = false;
    }

    /**
     * Define a gôndola atual e calcula os tabindex para acessibilidade
     */
    function setCurrentGondola(gondola: Gondola) {
        calculateTabindex(gondola);
        currentGondola.value = gondola;
    }

    /**
     * Registra uma mudança no estado atual no histórico
     * Esta função deve ser chamada APÓS cada mutação significativa no currentState
     */
    function recordChange() {
        setIsLoading(true);

        // Não registra se estamos navegando pelo histórico ou se não há estado atual
        if (isTimeTraveling.value || !currentState.value) {
            setIsLoading(false); // Certifique-se de desligar o loading aqui também
            return;
        }

        // Cria uma cópia profunda do estado atual
        const newState = JSON.parse(JSON.stringify(currentState.value));

        // Verifica se o estado realmente mudou comparado ao último estado no histórico
        if (historyIndex.value >= 0 && isEqual(newState, history.value[historyIndex.value].state)) {
            setIsLoading(false); // Certifique-se de desligar o loading aqui também
            return;
        }

        // Remove todos os estados futuros se estamos no meio do histórico
        if (historyIndex.value < history.value.length - 1) {
            history.value.splice(historyIndex.value + 1);
        }

        // Adiciona o novo estado ao histórico e atualiza o índice
        history.value.push({ timestamp: Date.now(), state: newState });
        historyIndex.value = history.value.length - 1;

        // Limita o tamanho do histórico
        if (history.value.length > MAX_HISTORY_SIZE) {
            history.value.shift(); // Remove o estado mais antigo
            historyIndex.value--; // Ajusta o índice após a remoção
        }

        setIsLoading(false);
    }

    /**
     * Desfaz a última alteração (undo)
     */
    function undo() {
        if (canUndo.value) {
            isTimeTraveling.value = true;
            historyIndex.value--;
            currentState.value = JSON.parse(JSON.stringify(history.value[historyIndex.value].state));
            isTimeTraveling.value = false;
            console.log('Undo realizado. Índice atual:', historyIndex.value);
        }
    }

    /**
     * Refaz a última alteração desfeita (redo)
     */
    function redo() {
        if (canRedo.value) {
            isTimeTraveling.value = true;
            historyIndex.value++;
            currentState.value = JSON.parse(JSON.stringify(history.value[historyIndex.value].state));
            isTimeTraveling.value = false;
            console.log('Redo realizado. Índice atual:', historyIndex.value);
        }
    }

    /**
     * Salva as alterações atuais (stub - a ser implementado)
     */
    async function saveChanges() {
        if (!currentState.value) return;
        console.log('Salvando alterações...', currentState.value);

        // TODO: Implementar a chamada API para salvar as alterações
        // Exemplo: await apiService.put(`/api/plannerate/planograms/${currentState.value.id}`, currentState.value);

        // Após salvar, pode ser útil resetar o histórico ou marcar como salvo
        // Exemplo: history.value = [history.value[historyIndex.value]]; historyIndex.value = 0;

        alert('Funcionalidade de salvar ainda não implementada!');
    }

    /**
     * Atualiza uma propriedade específica do planograma
     * @param key Nome da propriedade a ser atualizada
     * @param value Novo valor da propriedade
     */
    function updatePlanogramProperty(key: keyof PlanogramEditorState, value: any) {
        if (currentState.value) {
            (currentState.value as any)[key] = value;
            recordChange();
        }
    }

    /**
     * Obtém uma gôndola pelo ID
     * @param gondolaId ID da gôndola a ser obtida
     */
    function getGondola(gondolaId: string): Gondola | undefined {
        return currentState.value?.gondolas.find((gondola) => gondola.id === gondolaId);
    }

    /**
     * Adiciona uma nova gôndola ao estado atual
     * @param newGondola Objeto da nova gôndola
     */
    function addGondola(newGondola: Gondola) {
        if (currentState.value) {
            // Garante que o array gondolas existe
            if (!Array.isArray(currentState.value.gondolas)) {
                currentState.value.gondolas = [];
            }
            currentState.value.gondolas.push(newGondola);
            recordChange();
            console.log('Gôndola adicionada ao store:', newGondola);
        }
    }

    /**
     * Define o fator de escala no estado do editor
     * @param newScale Novo fator de escala
     */
    function setScaleFactor(newScale: number) {
        if (currentState.value && currentState.value.scaleFactor !== newScale) {
            // Aplica limites para segurança
            const clampedScale = Math.max(2, Math.min(10, newScale));
            currentState.value.scaleFactor = clampedScale;
            recordChange();
        }
    }

    /**
     * Alterna a visibilidade da grade no estado do editor
     */
    function toggleGrid() {
        if (currentState.value) {
            currentState.value.showGrid = !currentState.value.showGrid;
            recordChange();
        }
    }

    /**
     * Inverte a ordem das seções de uma gôndola específica
     * @param gondolaId ID da gôndola
     */
    function invertGondolaSectionOrder(gondolaId: string) {
        const gondola = findGondola(gondolaId, 'invertGondolaSectionOrder');
        if (!gondola) return;

        if (gondola.sections.length > 1) {
            gondola.sections.reverse();
            console.log(`Ordem das seções invertida para a gôndola ${gondolaId}`);
            recordChange();
        } else {
            console.warn(`Não foi possível inverter seções: Gôndola ${gondolaId} tem menos de 2 seções.`);
        }
    }

    /**
     * Define a ordem das seções para uma gôndola específica
     * @param gondolaId ID da gôndola
     * @param newSections Array de seções na nova ordem
     */
    function setGondolaSectionOrder(gondolaId: string, newSections: Section[]) {
        const gondola = findGondola(gondolaId, 'setGondolaSectionOrder');
        if (!gondola) return;

        // Compara se a nova ordem é realmente diferente da atual
        const currentSectionIds = gondola.sections.map(s => s.id);
        const newSectionIds = newSections.map(s => s.id);

        if (JSON.stringify(currentSectionIds) === JSON.stringify(newSectionIds)) {
            console.log('Ordem das seções não mudou, nenhum registro no histórico.');
            return;
        }

        // Atualiza o array de seções com a nova ordem
        gondola.sections = [...newSections];
        console.log(`Nova ordem das seções definida para a gôndola ${gondolaId}`);
        recordChange();
    }

    /**
     * Remove uma seção específica de uma gôndola
     * @param gondolaId ID da gôndola
     * @param sectionId ID da seção a ser removida
     */
    function removeSectionFromGondola(gondolaId: string, sectionId: string) {
        const gondola = findGondola(gondolaId, 'removeSectionFromGondola');
        if (!gondola) return;

        const initialLength = gondola.sections.length;
        gondola.sections = gondola.sections.filter(s => s.id !== sectionId);

        if (gondola.sections.length < initialLength) {
            console.log(`Seção ${sectionId} removida da gôndola ${gondolaId}`);
            recordChange();
        } else {
            console.warn(`Seção ${sectionId} não encontrada na gôndola ${gondolaId} para remoção.`);
        }
    }

    /**
     * Inverte a ordem das prateleiras de uma seção específica
     * @param gondolaId ID da gôndola
     * @param sectionId ID da seção
     */
    function invertShelvesInSection(gondolaId: string, sectionId: string) {
        const path = findPath(gondolaId, sectionId, null, 'invertShelvesInSection');
        if (!path) return;

        const { section } = path;

        if (section.shelves.length <= 1) {
            console.warn(`Não foi possível inverter prateleiras: Seção ${sectionId} tem menos de 2 prateleiras.`);
            return;
        }

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
            // 4. Atualizar as posições das prateleiras
            section.shelves.forEach(shelf => {
                const newPosition = newPositionsMap.get(shelf.id);
                if (newPosition !== undefined && shelf.shelf_position !== newPosition) {
                    shelf.shelf_position = newPosition;
                    changed = true;
                }
            });

            if (changed) {
                console.log(`Posições das prateleiras invertidas para a seção ${sectionId}`);
                recordChange();
            } else {
                console.log(`Posições das prateleiras já estavam invertidas ou erro no cálculo.`);
            }
        } catch (error) {
            console.error(`Erro ao calcular inversão de posição para seção ${sectionId}:`, error);
        }
    }

    /**
     * Adiciona uma nova prateleira a uma seção específica
     * @param gondolaId ID da gôndola
     * @param sectionId ID da seção
     * @param newShelfData Dados da nova prateleira
     */
    function addShelfToSection(gondolaId: string, sectionId: string, newShelfData: Shelf) {
        const path = findPath(gondolaId, sectionId, null, 'addShelfToSection');
        if (!path) return;

        const { section } = path;

        // Criar uma cópia e ajustar tipos antes de adicionar ao estado
        const shelfToAdd = {
            ...newShelfData,
            // Garante que alignment seja string ou undefined, tratando null
            alignment: newShelfData.alignment === null ? undefined : newShelfData.alignment,
        };

        section.shelves.push(shelfToAdd);
        console.log(`Prateleira ${shelfToAdd.id} adicionada à seção ${sectionId}`);
        recordChange();
    }

    /**
     * Define o alinhamento para uma seção específica
     * @param gondolaId ID da gôndola
     * @param sectionId ID da seção
     * @param alignment Novo valor de alinhamento
     */
    function setSectionAlignment(gondolaId: string, sectionId: string, alignment: string | null) {
        const path = findPath(gondolaId, sectionId, null, 'setSectionAlignment');
        if (!path) return;

        const { section } = path;

        if (section.alignment !== alignment) {
            section.alignment = alignment;
            console.log(`Alinhamento da seção ${sectionId} definido para ${alignment}`);
            recordChange();
        } else {
            console.log(`Alinhamento da seção ${sectionId} já era ${alignment}.`);
        }
    }

    /**
     * Define a posição vertical para uma prateleira específica
     * @param gondolaId ID da gôndola
     * @param sectionId ID da seção
     * @param shelfId ID da prateleira
     * @param newPosition Novas coordenadas de posição
     */
    function setShelfPosition(
        gondolaId: string,
        sectionId: string,
        shelfId: string,
        newPosition: { shelf_position: number, shelf_x_position: number }
    ) {
        const path = findPath(gondolaId, sectionId, shelfId, 'setShelfPosition');
        if (!path) return;

        const { shelf } = path;
        const { shelf_position, shelf_x_position } = newPosition;

        if (shelf && (shelf.shelf_position !== shelf_position || shelf.shelf_x_position !== shelf_x_position)) {
            shelf.shelf_position = shelf_position;
            shelf.shelf_x_position = shelf_x_position;
            console.log(`Posição da prateleira ${shelfId} atualizada`);
            recordChange();
        } else {
            console.log(`Posição da prateleira ${shelfId} não mudou.`);
        }
    }

    /**
     * Adiciona um novo segmento a uma prateleira específica
     * @param gondolaId ID da gôndola
     * @param sectionId ID da seção
     * @param shelfId ID da prateleira
     * @param newSegment Dados do novo segmento
     */
    function addSegmentToShelf(gondolaId: string, sectionId: string, shelfId: string, newSegment: Segment) {
        const path = findPath(gondolaId, sectionId, shelfId, 'addSegmentToShelf');
        if (!path) return;

        const { shelf } = path;

        if (typeof newSegment.id === 'string') {
            if (shelf) {
                shelf.segments.push(newSegment as import('@/types/shelves').Segment);
                console.log(`Segmento ${newSegment.id} adicionado à prateleira ${shelfId}`);
                recordChange();
            } else {
                console.error('Prateleira não encontrada para adicionar segmento.');
            }
        } else {
            console.error('Tentativa de adicionar segmento sem ID.', newSegment);
        }
    }

    /**
     * Define a ordem dos segmentos para uma prateleira específica
     * @param gondolaId ID da gôndola
     * @param sectionId ID da seção
     * @param shelfId ID da prateleira
     * @param newSegments Array de segmentos na nova ordem
     */
    function setShelfSegmentsOrder(gondolaId: string, sectionId: string, shelfId: string, newSegments: Segment[]) {
        const path = findPath(gondolaId, sectionId, shelfId, 'setShelfSegmentsOrder');
        if (!path) return;

        const { shelf } = path;

        // Compara se a nova ordem é realmente diferente da atual
        const currentSegmentIds = shelf?.segments.map(seg => seg.id);
        const newSegmentIds = newSegments.map(seg => seg.id);

        if (JSON.stringify(currentSegmentIds) === JSON.stringify(newSegmentIds)) {
            console.log(`Ordem dos segmentos na prateleira ${shelfId} não mudou.`);
            return;
        }

        // Verifica se todos os segmentos recebidos têm ID
        if (!newSegments.every(seg => typeof seg.id === 'string')) {
            console.error('Tentativa de definir ordem com segmento sem ID.', newSegments);
            return;
        }

        if (shelf) {
            shelf.segments = [...newSegments] as import('@/types/shelves').Segment[];
            console.log(`Nova ordem dos segmentos definida para a prateleira ${shelfId}`);
            recordChange();
        } else {
            console.error('Prateleira não encontrada para definir ordem dos segmentos.');
        }
    }

    /**
     * Define o alinhamento para uma prateleira específica
     * @param gondolaId ID da gôndola
     * @param sectionId ID da seção
     * @param shelfId ID da prateleira
     * @param alignment Novo valor de alinhamento
     */
    function setShelfAlignment(gondolaId: string, sectionId: string, shelfId: string, alignment: string | null) {
        const path = findPath(gondolaId, sectionId, shelfId, 'setShelfAlignment');
        if (!path) return;

        const { shelf } = path;

        if (shelf && shelf.alignment !== alignment) {
            shelf.alignment = alignment;
            console.log(`Alinhamento da prateleira ${shelfId} definido para ${alignment}`);
            recordChange();
        } else {
            console.log(`Alinhamento da prateleira ${shelfId} já era ${alignment}.`);
        }
    }

    /**
     * Transfere um segmento de uma prateleira para outra
     * @param gondolaId ID da gôndola
     * @param oldSectionId ID da seção de origem
     * @param oldShelfId ID da prateleira de origem
     * @param newSectionId ID da seção de destino
     * @param newShelfId ID da prateleira de destino
     * @param segmentId ID do segmento a ser transferido
     * @param newPositionX Nova posição X relativa (opcional)
     * @param newOrdering Nova ordem no destino (opcional)
     */
    function transferSegmentBetweenShelves(
        gondolaId: string,
        oldSectionId: string,
        oldShelfId: string,
        newSectionId: string,
        newShelfId: string,
        segmentId: string,
        newPositionX?: number,
        newOrdering?: number
    ) {
        const gondola = findGondola(gondolaId, 'transferSegmentBetweenShelves');
        if (!gondola) return;

        // Encontrar seção/prateleira de ORIGEM
        const oldSection = findSection(gondola, oldSectionId, 'transferSegmentBetweenShelves');
        if (!oldSection) return;

        const oldShelf = findShelf(oldSection, oldShelfId, 'transferSegmentBetweenShelves');
        if (!oldShelf) return;

        // Encontrar seção/prateleira de DESTINO
        const newSection = findSection(gondola, newSectionId, 'transferSegmentBetweenShelves');
        if (!newSection) return;

        const newShelf = findShelf(newSection, newShelfId, 'transferSegmentBetweenShelves');
        if (!newShelf) return;

        // Encontrar e remover segmento da prateleira de origem
        const segmentIndex = oldShelf.segments.findIndex(seg => seg.id === segmentId);
        if (segmentIndex === -1) {
            console.warn(`Segmento ${segmentId} não encontrado na prateleira ${oldShelfId}.`);
            return;
        }

        const segmentToMove = oldShelf.segments.splice(segmentIndex, 1)[0];

        // Atualizar dados do segmento movido
        segmentToMove.shelf_id = newShelfId;
        if (newPositionX !== undefined) {
            (segmentToMove as any).position = newPositionX;
        }

        // Atualizar ordem (anexar ao final se não especificado)
        segmentToMove.ordering = newOrdering ?? newShelf.segments.length + 1;

        // Adicionar segmento à prateleira de destino
        newShelf.segments.push(segmentToMove);

        console.log(`Segmento ${segmentId} transferido de ${oldShelfId} para ${newShelfId}`);
        recordChange();
    }

    /**
     * Remove uma prateleira específica de uma seção
     * @param gondolaId ID da gôndola
     * @param sectionId ID da seção
     * @param shelfId ID da prateleira a ser removida
     */
    function removeShelfFromSection(gondolaId: string, sectionId: string, shelfId: string) {
        const path = findPath(gondolaId, sectionId, null, 'removeShelfFromSection');
        if (!path) return;

        const { section } = path;

        const initialLength = section.shelves.length;
        section.shelves = section.shelves.filter(sh => sh.id !== shelfId);

        if (section.shelves.length < initialLength) {
            console.log(`Prateleira ${shelfId} removida da seção ${sectionId}`);
            recordChange();
        } else {
            console.warn(`Prateleira ${shelfId} não encontrada na seção ${sectionId} para remoção.`);
        }
    }

    /**
     * Atualiza os dados de uma seção específica
     * @param gondolaId ID da gôndola
     * @param sectionId ID da seção a ser atualizada
     * @param sectionData Objeto com as propriedades da seção a serem atualizadas
     */
    function updateSectionData(gondolaId: string, sectionId: string, sectionData: Partial<Section>) {
        const gondola = findGondola(gondolaId, 'updateSectionData');
        if (!gondola) return;

        const sectionIndex = gondola.sections.findIndex(s => s.id === sectionId);
        if (sectionIndex === -1) {
            console.warn(`Seção ${sectionId} não encontrada.`);
            return;
        }

        // Mescla os dados antigos com os novos dados
        const originalSection = gondola.sections[sectionIndex];
        const updatedSection = Object.assign({}, originalSection, sectionData);

        // Verifica se houve realmente alguma mudança
        if (JSON.stringify(originalSection) !== JSON.stringify(updatedSection)) {
            gondola.sections[sectionIndex] = updatedSection;
            console.log(`Dados da seção ${sectionId} atualizados.`);
            recordChange();
        } else {
            console.log(`Dados da seção ${sectionId} não foram alterados.`);
        }
    }

    /**
     * Atualiza os dados de uma prateleira específica
     * @param gondolaId ID da gôndola
     * @param sectionId ID da seção
     * @param shelfId ID da prateleira
     * @param shelfData Objeto com as propriedades da prateleira a serem atualizadas
     */
    function updateShelfData(gondolaId: string, sectionId: string, shelfId: string, shelfData: Partial<Shelf>) {
        const path = findPath(gondolaId, sectionId, shelfId, 'updateShelfData');
        if (!path) return;

        const { section, shelf } = path;

        const shelfIndex = section.shelves.findIndex(sh => sh.id === shelfId);

        // Mescla os dados antigos com os novos dados
        const originalShelf = shelf;
        if (!originalShelf) {
            console.warn(`Prateleira ${shelfId} não encontrada.`);
            return;
        }
        const updatedShelf = {
            ...originalShelf,
            ...shelfData,
            alignment: shelfData.alignment === null ? undefined : shelfData.alignment ?? originalShelf.alignment
        };

        // Compara usando isEqual para uma verificação profunda
        if (!isEqual(originalShelf, updatedShelf)) {
            section.shelves[shelfIndex] = updatedShelf;
            console.log(`Dados da prateleira ${shelfId} atualizados.`);
            recordChange();
        } else {
            console.log(`Dados da prateleira ${shelfId} não foram alterados.`);
        }
    }

    /**
     * Transfere uma prateleira inteira de uma seção para outra dentro da mesma gôndola
     * @param gondolaId ID da gôndola
     * @param oldSectionId ID da seção de origem
     * @param newSectionId ID da seção de destino
     * @param shelfId ID da prateleira a ser transferida
     */
    function transferShelfBetweenSections(gondolaId: string, oldSectionId: string, newSectionId: string, shelfId: string) {
        const gondola = findGondola(gondolaId, 'transferShelfBetweenSections');
        if (!gondola) return;

        // Encontrar seção de origem
        const oldSection = findSection(gondola, oldSectionId, 'transferShelfBetweenSections');
        if (!oldSection) return;

        const shelfIndex = oldSection.shelves.findIndex(sh => sh.id === shelfId);
        if (shelfIndex === -1) {
            console.warn(`Prateleira ${shelfId} não encontrada na seção ${oldSectionId}.`);
            return;
        }

        // Encontrar seção de destino
        const newSection = findSection(gondola, newSectionId, 'transferShelfBetweenSections');
        if (!newSection) return;

        // Remover a prateleira da seção antiga e obter o objeto
        const shelfToMove = oldSection.shelves.splice(shelfIndex, 1)[0];

        // Atualizar dados da prateleira movida
        shelfToMove.section_id = newSectionId;
        shelfToMove.shelf_x_position = -4; // Resetar posição X relativa à nova seção

        // Adicionar a prateleira à nova seção
        newSection.shelves.push(shelfToMove);

        console.log(`Prateleira ${shelfId} transferida de ${oldSectionId} para ${newSectionId}`);
        recordChange();
    }

    /**
     * Atualiza a quantidade de uma camada (layer) específica dentro de um segmento
     * @param gondolaId ID da Gôndola
     * @param sectionId ID da Seção
     * @param shelfId ID da Prateleira
     * @param segmentId ID do Segmento
     * @param layerId ID da Camada (geralmente igual ao ID do produto)
     * @param newQuantity Nova quantidade para a camada
     */
    function updateLayerQuantity(
        gondolaId: string,
        sectionId: string,
        shelfId: string,
        segmentId: string,
        layerId: string,
        newQuantity: number
    ) {
        if (newQuantity < 0) {
            console.warn(`Tentativa de definir quantidade negativa (${newQuantity}). Abortando.`);
            return;
        }

        const path = findPath(gondolaId, sectionId, shelfId, 'updateLayerQuantity');
        if (!path) return;

        const { shelf } = path;

        if (!shelf) {
            console.warn(`Prateleira ${shelfId} não encontrada.`);
            return;
        }

        const segment = findSegment(shelf, segmentId, 'updateLayerQuantity');
        if (!segment || !segment.layer) {
            console.warn(`Segmento ${segmentId} ou sua camada não encontrados.`);
            return;
        }

        setIsLoading(true);

        // Verifica se o layerId corresponde ao ID do produto da camada
        if (segment.layer.product.id === layerId && newQuantity > 0) {
            if (segment.layer.quantity !== newQuantity) {
                segment.layer.quantity = newQuantity;
                console.log(`Quantidade da layer ${layerId} atualizada para ${newQuantity} no segmento ${segmentId}.`);
                recordChange();
            } else {
                console.log(`Quantidade da layer ${layerId} já era ${newQuantity}.`);
            }
        } else {
            console.warn(`Layer com ID ${layerId} não encontrada no segmento ${segmentId}.`);
        }

        setIsLoading(false);
    }

    /**
     * Define o alinhamento padrão para uma gôndola específica
     * @param gondolaId ID da gôndola
     * @param alignment Novo valor de alinhamento
     */
    function setGondolaAlignment(gondolaId: string, alignment: string | null) {
        const gondola = findGondola(gondolaId, 'setGondolaAlignment');
        if (!gondola) return;

        setIsLoading(true);

        // Converte null para undefined para consistência
        const newAlignment = alignment === null ? undefined : alignment;

        if (gondola.alignment !== newAlignment) {
            gondola.alignment = newAlignment;
            console.log(`Alinhamento da gôndola ${gondolaId} definido para ${newAlignment}`);
            recordChange();
        } else {
            console.log(`Alinhamento da gôndola ${gondolaId} já era ${newAlignment}.`);
        }

        setIsLoading(false);
    }

    // =========================================================
    // EXPORTAÇÕES
    // =========================================================

    return {
        // Estado
        currentState,
        history,
        historyIndex,
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
        initialize,
        setCurrentGondola,

        // Funções de UI
        setIsLoading,
        setError,

        // Funções de histórico
        recordChange,
        undo,
        redo,
        saveChanges,

        // Funções de manipulação de dados básicos
        updatePlanogramProperty,
        getGondola,
        addGondola,
        setScaleFactor,
        toggleGrid,

        // Funções de acessibilidade
        calculateTabindex,

        // Manipulação de gôndolas
        setGondolaAlignment,

        // Manipulação de seções
        invertGondolaSectionOrder,
        setGondolaSectionOrder,
        removeSectionFromGondola,
        setSectionAlignment,
        updateSectionData,

        // Manipulação de prateleiras
        invertShelvesInSection,
        addShelfToSection,
        setShelfPosition,
        setShelfAlignment,
        removeShelfFromSection,
        updateShelfData,
        transferShelfBetweenSections,

        // Manipulação de segmentos
        addSegmentToShelf,
        setShelfSegmentsOrder,
        transferSegmentBetweenShelves,

        // Manipulação de camadas (layers)
        updateLayerQuantity,
    };
});