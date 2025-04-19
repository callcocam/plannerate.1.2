// /store/editor/actions/shelf.ts
import type { Shelf } from '@plannerate/types/shelves';
import type { ShelfPosition } from '../types';
import { findGondola, findPath, findSection } from '../utils';
import { recordChange } from '../history';
import { isEqual } from 'lodash-es';

/**
 * Inverte a ordem das prateleiras de uma seção específica
 * @param gondolaId ID da gôndola
 * @param sectionId ID da seção
 */
export function invertShelvesInSection(gondolaId: string, sectionId: string) {
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
export function addShelfToSection(gondolaId: string, sectionId: string, newShelfData: Shelf) {
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
 * Define a posição vertical para uma prateleira específica
 * @param gondolaId ID da gôndola
 * @param sectionId ID da seção
 * @param shelfId ID da prateleira
 * @param newPosition Novas coordenadas de posição
 */
export function setShelfPosition(
    gondolaId: string,
    sectionId: string,
    shelfId: string,
    newPosition: ShelfPosition
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
 * Define o alinhamento para uma prateleira específica
 * @param gondolaId ID da gôndola
 * @param sectionId ID da seção
 * @param shelfId ID da prateleira
 * @param alignment Novo valor de alinhamento
 */
export function setShelfAlignment(gondolaId: string, sectionId: string, shelfId: string, alignment: string | null) {
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
 * Remove uma prateleira específica de uma seção
 * @param gondolaId ID da gôndola
 * @param sectionId ID da seção
 * @param shelfId ID da prateleira a ser removida
 */
export function removeShelfFromSection(gondolaId: string, sectionId: string, shelfId: string) {
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
 * Atualiza os dados de uma prateleira específica
 * @param gondolaId ID da gôndola
 * @param sectionId ID da seção
 * @param shelfId ID da prateleira
 * @param shelfData Objeto com as propriedades da prateleira a serem atualizadas
 */
export function updateShelfData(gondolaId: string, sectionId: string, shelfId: string, shelfData: Partial<Shelf>) {
    const path = findPath(gondolaId, sectionId, shelfId, 'updateShelfData');
    if (!path) return;

    const { section, shelf } = path;

    const shelfIndex = section.shelves.findIndex(sh => sh.id === shelfId);

    // Mescla os dados antigos com os novos dados
    const originalShelf = shelf;
    if (!originalShelf) return;
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
export function transferShelfBetweenSections(gondolaId: string, oldSectionId: string, newSectionId: string, shelfId: string) {
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