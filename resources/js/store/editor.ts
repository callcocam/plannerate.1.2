import { defineStore } from 'pinia';
import { ref, computed, watch } from 'vue';
import { isEqual } from 'lodash-es'; // Usaremos lodash para comparações profundas

// Interface para representar o estado do planograma (pode ser mais detalhada)
interface PlanogramState {
    id: string | null;
    name: string | null; 
    gondolas: any[]; // Detalhar este tipo depois
    // Outras propriedades do planograma
}

// Interface para representar um snapshot do estado
interface HistoryEntry {
    timestamp: number;
    state: PlanogramState;
}

export const useEditorStore = defineStore('editor', () => {
    // --- STATE --- 

    // Estado atual do planograma sendo editado
    const currentState = ref<PlanogramState | null>(null);

    // Histórico de estados para undo/redo
    const history = ref<HistoryEntry[]>([]);
    // Índice atual no histórico (-1 significa nenhum estado, 0 é o estado inicial)
    const historyIndex = ref<number>(-1);
    // Flag para evitar que watchers do histórico adicionem ao histórico
    const isTimeTraveling = ref(false);

    // --- GETTERS --- 

    const initialPlanogram = computed(() => history.value[0]?.state || null);
    const hasChanges = computed(() => {
        if (historyIndex.value < 0 || !currentState.value) return false;
        // Compara o estado atual com o estado inicial salvo no histórico
        return !isEqual(currentState.value, initialPlanogram.value);
    });
    const canUndo = computed(() => historyIndex.value > 0);
    const canRedo = computed(() => historyIndex.value < history.value.length - 1);

    // --- ACTIONS --- 

    /**
     * Inicializa o store com os dados do planograma.
     * @param initialData Dados iniciais do planograma.
     */
    function initialize(initialData: PlanogramState) {
        console.log('Initializing editor store...', initialData);
        currentState.value = JSON.parse(JSON.stringify(initialData)); // Deep copy
        history.value = [{
            timestamp: Date.now(),
            state: JSON.parse(JSON.stringify(initialData)) // Salva o estado inicial
        }];
        historyIndex.value = 0;
        isTimeTraveling.value = false;
    }

    /**
     * Registra uma mudança no estado atual no histórico.
     * Esta função deve ser chamada APÓS cada mutação significativa no currentState.
     */
    function recordChange() {
        if (isTimeTraveling.value) return; // Não gravar se estiver navegando no histórico
        if (!currentState.value) return;

        const newState = JSON.parse(JSON.stringify(currentState.value));

        // Se o estado atual for igual ao último estado no histórico, não faz nada
        if (historyIndex.value >= 0 && isEqual(newState, history.value[historyIndex.value].state)) {
            return;
        }

        // Remove futuros estados se estivermos desfazendo e fizemos uma nova alteração
        if (historyIndex.value < history.value.length - 1) {
            history.value.splice(historyIndex.value + 1);
        }

        // Adiciona o novo estado ao histórico
        history.value.push({ timestamp: Date.now(), state: newState });
        historyIndex.value = history.value.length - 1;

        // Limitar o tamanho do histórico (opcional)
        // const MAX_HISTORY = 50;
        // if (history.value.length > MAX_HISTORY) {
        //     history.value.shift();
        //     historyIndex.value--;
        // }
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
    function updatePlanogramProperty(key: keyof PlanogramState, value: any) {
        if (currentState.value) {
            (currentState.value as any)[key] = value;
            recordChange(); // Registra a mudança após a atualização
        }
    }
    
    function getGondola(gondolaId: string) {
        return currentState.value?.gondolas.find((gondola: any) => gondola.id === gondolaId);
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
        initialize,
        recordChange, // Expor caso precise chamar manualmente
        undo,
        redo,
        saveChanges,
        updatePlanogramProperty,
        getGondola,
        // Expor outras actions aqui
    };
});
