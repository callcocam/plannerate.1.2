// /store/editor/history.ts
import {
    currentState,
    currentGondola,
    gondolaHistories,
    isTimeTraveling,
    isLoading
} from './state';
import { MAX_HISTORY_SIZE, HistoryEntry } from './types';
import { calculateTabindex } from './utils';
import { isEqual } from 'lodash-es';
import { saveChanges } from './actions/basic';
/**
 * Inicializa o histórico para uma gôndola específica
 * @param gondolaId ID da gôndola
 */
export function initializeGondolaHistory(gondolaId: string) {
    if (!currentState.value) return;

    // Cria uma cópia profunda do estado atual para o histórico
    const initialState = JSON.parse(JSON.stringify(currentState.value));

    // Cria a entrada inicial no histórico
    const historyEntry: HistoryEntry = {
        timestamp: Date.now(),
        state: initialState,
        gondolaId
    };

    // Inicializa o histórico para esta gôndola
    gondolaHistories.value[gondolaId] = {
        entries: [historyEntry],
        currentIndex: 0
    };
 
}

/**
 * Registra uma mudança no estado atual no histórico da gôndola atual
 */
export function recordChange(save: boolean = false) {
    isLoading.value = true;

    // Não registra se estamos navegando pelo histórico ou se não há estado/gôndola atual
    if (isTimeTraveling.value || !currentState.value || !currentGondola.value) {
        isLoading.value = false;
        return;
    }

    const gondolaId = currentGondola.value.id;

    // Garante que o histórico para esta gôndola exista
    if (!gondolaHistories.value[gondolaId]) {
        initializeGondolaHistory(gondolaId);
    }

    // Obtém o histórico da gôndola atual
    const history = gondolaHistories.value[gondolaId];

    // Cria uma cópia profunda do estado atual
    const newState = JSON.parse(JSON.stringify(currentState.value));

    // Verifica se o estado realmente mudou
    const currentEntry = history.entries[history.currentIndex];
    if (isEqual(newState, currentEntry.state)) {
        isLoading.value = false;
        return;
    }

    // Remove todos os estados futuros se estamos no meio do histórico
    if (history.currentIndex < history.entries.length - 1) {
        history.entries = history.entries.slice(0, history.currentIndex + 1);
    }

    // Adiciona o novo estado ao histórico
    history.entries.push({
        timestamp: Date.now(),
        state: newState,
        gondolaId
    });

    // Limita o tamanho do histórico se necessário
    if (history.entries.length > MAX_HISTORY_SIZE) {
        // Remove a entrada mais antiga, mas nunca a primeira (estado inicial)
        history.entries = [
            history.entries[0],
            ...history.entries.slice(2)
        ];
    }

    // Atualiza o índice atual
    history.currentIndex = history.entries.length - 1;
 
    isLoading.value = false;

    if (save) {
        saveChanges();
    }
}

/**
 * Desfaz a última alteração (undo) para a gôndola atual
 */
export function undo() {
    // Obtém o histórico da gôndola atual
    const currentHistory = gondolaHistories.value[currentGondola.value?.id || ''];
    if (!currentGondola.value || !currentHistory) return;

    const gondolaId = currentGondola.value.id;

    // Verifica se podemos desfazer
    if (currentHistory.currentIndex <= 0) {
        console.log(`Não é possível desfazer: já estamos no início do histórico da gôndola ${gondolaId}`);
        return;
    }

    isTimeTraveling.value = true;

    // Decrementa o índice atual
    currentHistory.currentIndex--;

    // Restaura o estado correspondente
    const stateToRestore = currentHistory.entries[currentHistory.currentIndex].state;
    currentState.value = JSON.parse(JSON.stringify(stateToRestore));

    // Atualiza a referência da gôndola atual
    if (currentState.value) {
        const updatedGondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (updatedGondola) {
            currentGondola.value = updatedGondola;
            calculateTabindex(updatedGondola);
        }
    }

    console.log(`Undo realizado para gôndola ${gondolaId}. Índice atual: ${currentHistory.currentIndex}`);
    isTimeTraveling.value = false;
}

/**
 * Refaz a última alteração desfeita (redo) para a gôndola atual
 */
export function redo() {
    // Obtém o histórico da gôndola atual
    const currentHistory = gondolaHistories.value[currentGondola.value?.id || ''];
    if (!currentGondola.value || !currentHistory) return;

    const gondolaId = currentGondola.value.id;

    // Verifica se podemos refazer
    if (currentHistory.currentIndex >= currentHistory.entries.length - 1) {
        console.log(`Não é possível refazer: já estamos no final do histórico da gôndola ${gondolaId}`);
        return;
    }

    isTimeTraveling.value = true;

    // Incrementa o índice atual
    currentHistory.currentIndex++;

    // Restaura o estado correspondente
    const stateToRestore = currentHistory.entries[currentHistory.currentIndex].state;
    currentState.value = JSON.parse(JSON.stringify(stateToRestore));

    // Atualiza a referência da gôndola atual
    if (currentState.value) {
        const updatedGondola = currentState.value.gondolas.find(g => g.id === gondolaId);
        if (updatedGondola) {
            currentGondola.value = updatedGondola;
            calculateTabindex(updatedGondola);
        }
    }
 
    isTimeTraveling.value = false;
}

/**
 * Reseta o histórico para uma gôndola específica
 * @param gondolaId ID da gôndola
 */
export function resetGondolaHistory(gondolaId: string) {
    if (!gondolaId || !currentState.value) return;

    const gondola = currentState.value.gondolas.find(g => g.id === gondolaId);
    if (!gondola) {
        console.warn(`Não foi possível resetar histórico: Gôndola ${gondolaId} não encontrada.`);
        return;
    }

    initializeGondolaHistory(gondolaId); 
}