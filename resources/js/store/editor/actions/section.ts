// /store/editor/actions/section.ts
import type { Section } from '@plannerate/types/sections';
import { findGondola, findPath } from '../utils';
import { recordChange } from '../history';

/**
 * Define a ordem das seções para uma gôndola específica
 * @param gondolaId ID da gôndola
 * @param newSections Array de seções na nova ordem
 */
export function setGondolaSectionOrder(gondolaId: string, newSections: Section[]) {
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
export function removeSectionFromGondola(gondolaId: string, sectionId: string) {
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
 * Define o alinhamento para uma seção específica
 * @param gondolaId ID da gôndola
 * @param sectionId ID da seção
 * @param alignment Novo valor de alinhamento
 */
export function setSectionAlignment(gondolaId: string, sectionId: string, alignment: string | null) {
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
 * Atualiza os dados de uma seção específica
 * @param gondolaId ID da gôndola
 * @param sectionId ID da seção a ser atualizada
 * @param sectionData Objeto com as propriedades da seção a serem atualizadas
 */
export function updateSectionData(gondolaId: string, sectionId: string, sectionData: Partial<Section>) {
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