// /store/editor/utils.ts
import type { Gondola } from '@plannerate/types/gondola';
import type { Section } from '@plannerate/types/sections';
import type { Shelf } from '@plannerate/types/shelves';
import type { Segment } from '@plannerate/types/segment';
import type { PathResult } from './types';
import { currentState } from './state';

/**
 * Calcula o tabindex de cada seção em uma gôndola
 * Utilizado para navegação por teclado na interface
 * @param gondola Gôndola para calcular os tabindex
 */
export function calculateTabindex(gondola: Gondola) {
    if (!gondola) return 0;
    let tabindex = 0;
    gondola.sections.map((section) => {
        section.shelves.map((shelf) => {
            if (shelf.segments?.length) {
                shelf.segments.map((segment) => {
                    tabindex = tabindex + 1;
                    segment.tabindex = tabindex;
                });
            }
        });
    });
}

/**
 * Verifica e localiza uma gôndola pelo ID
 * @param gondolaId ID da gôndola a ser localizada
 * @param operationName Nome da operação para exibir em mensagens de erro
 * @returns A gôndola encontrada ou null
 */
export function findGondola(gondolaId: string, operationName: string): Gondola | null {
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
export function findSection(gondola: Gondola, sectionId: string, operationName: string): Section | null {
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
export function findShelf(section: Section, shelfId: string, operationName: string): Shelf | null {
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
export function findSegment(shelf: Shelf, segmentId: string, operationName: string): Segment | null {
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
 * @param shelfId ID da prateleira (opcional)
 * @param operationName Nome da operação para mensagens de erro
 * @returns Um objeto contendo as referências encontradas ou null se alguma não for encontrada
 */
export function findPath(
    gondolaId: string,
    sectionId: string,
    shelfId: string | null = null,
    operationName: string
): PathResult | null {
    const gondola = findGondola(gondolaId, operationName);
    if (!gondola) return null;

    const section = findSection(gondola, sectionId, operationName);
    if (!section) return null;

    if (shelfId) {
        const shelf = findShelf(section, shelfId, operationName);
        if (!shelf) return null;
        return { gondola, section, shelf };
    }

    return { gondola, section };
}