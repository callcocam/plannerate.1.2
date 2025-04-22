// /store/editor/actions/gondola.ts
import type { Gondola } from '@plannerate/types/gondola';
import { findGondola } from '../utils';
import { currentState, isLoading } from '../state';
import { recordChange } from '../history';
import { useGondolaService } from '@plannerate/services/gondolaService';

/**
 * Adiciona uma nova gôndola ao estado atual
 * @param newGondola Objeto da nova gôndola
 */
export function addGondola(newGondola: Gondola) {
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
 * Define o alinhamento padrão para uma gôndola específica
 * @param gondolaId ID da gôndola
 * @param alignment Novo valor de alinhamento
 */
export function setGondolaAlignment(gondolaId: string, alignment: string | null) {
    const gondola = findGondola(gondolaId, 'setGondolaAlignment');
    if (!gondola) return;

    isLoading.value = true;

    // Converte null para undefined para consistência
    const newAlignment = alignment === null ? undefined : alignment;

    if (gondola.alignment !== newAlignment) {
        gondola.alignment = newAlignment;
        console.log(`Alinhamento da gôndola ${gondolaId} definido para ${newAlignment}`);
        recordChange();
    } else {
        console.log(`Alinhamento da gôndola ${gondolaId} já era ${newAlignment}.`);
    }

    isLoading.value = false;
}

/**
 * Inverte a ordem das seções de uma gôndola específica
 * @param gondolaId ID da gôndola
 */
export function invertGondolaSectionOrder(gondolaId: string) {
    const gondola = findGondola(gondolaId, 'invertGondolaSectionOrder');
    if (!gondola) return;

    if (gondola.sections.length > 1) {
        gondola.sections.reverse();

        // Atualiza o campo 'ordering' de cada seção com base no novo índice
        gondola.sections.forEach((section, index) => {
            section.ordering = index;
        });

        console.log(`Ordem das seções invertida e campo 'ordering' atualizado para a gôndola ${gondolaId}`);

        recordChange();
    } else {
        console.warn(`Não foi possível inverter seções: Gôndola ${gondolaId} tem menos de 2 seções.`);
    }
}

export function removeGondola(gondolaId: string, callback: () => void) {
    const gondola = findGondola(gondolaId, 'removeGondola');
    if (!gondola) return;

    useGondolaService().deleteGondola(gondolaId).then((response) => {
        console.log('Gôndola removida com sucesso:', response);
        callback();
    });


}
