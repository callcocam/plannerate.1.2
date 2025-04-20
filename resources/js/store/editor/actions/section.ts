// /store/editor/actions/section.ts
import type { Section } from '@plannerate/types/sections';
import { findGondola, findPath } from '../utils';
import { recordChange } from '../history';
import { isSectionEditing, selectedSection } from '../state';
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

    const originalSection = gondola.sections[sectionIndex];
    let changed = false;

    // Verifica se alguma propriedade realmente mudou antes de fazer qualquer coisa
    for (const key in sectionData) {
        if (Object.prototype.hasOwnProperty.call(sectionData, key) && 
            originalSection[key as keyof Section] !== sectionData[key as keyof Section]) {
            changed = true;
            break;
        }
    }

    if (changed) {
        // Cria o objeto atualizado para o histórico
        const updatedSection = { ...originalSection, ...sectionData };

        // Atualiza a seção dentro do array da gôndola (para histórico)
        gondola.sections[sectionIndex] = updatedSection;
        
        // Se a seção atualizada for a mesma que está selecionada, atualiza as propriedades da ref
        if (selectedSection.value && selectedSection.value.id === sectionId) {
            console.log(`Atualizando propriedades da ref selectedSection para ${sectionId}`);
            // Mescla as novas propriedades no objeto existente da ref
            Object.assign(selectedSection.value, sectionData);
            // Para garantir reatividade em casos de propriedades aninhadas (se houver), 
            // pode ser necessário forçar um gatilho, mas Object.assign geralmente basta.
            // Exemplo (geralmente não necessário): selectedSection.value = { ...selectedSection.value }; 
        }

        console.log(`Dados da seção ${sectionId} atualizados.`);
        recordChange(); // Registra após todas as mutações
    } else {
        console.log(`Dados da seção ${sectionId} não foram alterados.`);
    }
}

export function setIsSectionEditing(value: boolean) {
    isSectionEditing.value = value;
}

export function setSelectedSection(section: Section) {
    selectedSection.value = section;
    isSectionEditing.value = true;
}

export function getSelectedSection() {
    return selectedSection.value;
}

export function clearSelectedSection() {
    selectedSection.value = null;
}

export function isSectionSelected() {
    return selectedSection.value !== null;
}