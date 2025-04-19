// /store/editor/actions/basic.ts
import type { PlanogramEditorState } from '../types';
import type { Gondola } from '@plannerate/types/gondola';
import {
    currentState,
    currentGondola,
    isLoading,
    error,
    gondolaHistories
} from '../state';
import { calculateTabindex } from '../utils';
import { initializeGondolaHistory, recordChange } from '../history';

/**
 * Define o estado de carregamento do editor
 */
export function setIsLoading(newIsLoading: boolean) {
    isLoading.value = newIsLoading;
}

/**
 * Define o estado de erro do editor
 */
export function setError(newError: string | null) {
    error.value = newError;
}

/**
 * Inicializa o store com os dados do planograma e estado inicial do editor
 * @param initialPlanogramData Dados iniciais do planograma (sem estado do editor)
 */
export function initialize(initialPlanogramData: Omit<PlanogramEditorState, 'scaleFactor' | 'showGrid'>) {
    console.log('Inicializando editor store...', initialPlanogramData);

    // Cria uma cópia profunda dos dados iniciais e adiciona valores padrão
    const initialState: PlanogramEditorState = {
        ...JSON.parse(JSON.stringify(initialPlanogramData)),
        scaleFactor: 3,
        showGrid: false,
    };

    // Define o estado atual
    currentState.value = initialState;

    // Limpa o histórico e referências anteriores
    gondolaHistories.value = {};
    currentGondola.value = null;

    console.log('Editor store inicializado com sucesso.');
}

/**
 * Define a gôndola atual sem resetar o histórico
 * @param gondola A gôndola a ser definida como atual
 */
export function setCurrentGondola(gondola: Gondola | null) {
    if (!gondola) {
        currentGondola.value = null;
        return;
    }

    // Salva o ID para log e verificações
    const gondolaId = gondola.id;

    // Define a gôndola atual
    currentGondola.value = gondola;

    // Calcula tabindex para acessibilidade
    calculateTabindex(gondola);

    // Inicializa o histórico se necessário
    if (!gondolaHistories.value[gondolaId]) {
        console.log(`Criando novo histórico para gôndola ${gondolaId}`);
        initializeGondolaHistory(gondolaId);
    } else {
        console.log(`Usando histórico existente para gôndola ${gondolaId}`);
    }
}

/**
 * Salva as alterações atuais (implementação futura)
 */
export async function saveChanges() {
    if (!currentState.value || !currentGondola.value) return;

    const gondolaId = currentGondola.value.id;
    console.log(`Salvando alterações para gôndola ${gondolaId}...`, currentState.value);

    // TODO: Implementar chamada API para salvar

    // Após salvar com sucesso, podemos atualizar o histórico inicial
    if (gondolaHistories.value[gondolaId]) {
        // Faz do estado atual o novo estado "inicial" após salvar
        const newInitialState = JSON.parse(JSON.stringify(currentState.value));

        gondolaHistories.value[gondolaId] = {
            entries: [{
                timestamp: Date.now(),
                state: newInitialState,
                gondolaId
            }],
            currentIndex: 0
        };

        console.log(`Histórico resetado após salvar gôndola ${gondolaId}`);
    }

    alert('Funcionalidade de salvar ainda não implementada!');
}

/**
 * Atualiza uma propriedade específica do planograma
 * @param key Nome da propriedade a ser atualizada
 * @param value Novo valor da propriedade
 */
export function updatePlanogramProperty(key: keyof PlanogramEditorState, value: any) {
    if (currentState.value) {
        (currentState.value as any)[key] = value;
        recordChange();
    }
}

/**
 * Obtém uma gôndola pelo ID
 * @param gondolaId ID da gôndola a ser obtida
 */
export function getGondola(gondolaId: string): Gondola | undefined {
    return currentState.value?.gondolas.find((gondola) => gondola.id === gondolaId);
}

/**
 * Define o fator de escala no estado do editor
 * @param newScale Novo fator de escala
 */
export function setScaleFactor(newScale: number) {
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
export function toggleGrid() {
    if (currentState.value) {
        currentState.value.showGrid = !currentState.value.showGrid;
        recordChange();
    }
}