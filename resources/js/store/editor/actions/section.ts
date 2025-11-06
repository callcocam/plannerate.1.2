// /store/editor/actions/section.ts
import type { Section } from '@plannerate/types/sections';
import { findGondola } from '../utils';
import { recordChange } from '../history';
import { isSectionEditing, selectedSection, isDragging, currentState } from '../state';
/**
 * Define a ordem das seções para uma gôndola específica
 * @param gondolaId ID da gôndola
 * @param newSections Array de seções na nova ordem
 */
export function setGondolaSectionOrder(gondolaId: string, newSections: Section[]) {
    const gondola = findGondola(gondolaId, 'setGondolaSectionOrder');
    if (!gondola) return;

    // Atualiza o campo 'ordering' em cada seção da nova lista com base no índice
    const updatedSections = newSections.map((section, index) => ({
        ...section,
        ordering: index, 
    }));

    // Compara se a nova ordem (IDs e ordenação implícita pelo índice) é diferente da atual
    const currentSectionIds = gondola.sections.map(s => s.id);
    const newSectionIds = updatedSections.map(s => s.id);

    if (JSON.stringify(currentSectionIds) === JSON.stringify(newSectionIds)) {
        console.log('Ordem dos IDs das seções não mudou.');
    }

    // Atualiza o array de seções com nova referência
    gondola.sections = updatedSections;
    
    // Força reatividade no estado global
    forceReactivity(gondolaId, gondola);
    
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
        forceReactivity(gondolaId, gondola);
        recordChange();
    } else {
        console.warn(`Seção ${sectionId} não encontrada na gôndola ${gondolaId} para remoção.`);
    }
} 

/**
 * Helper para forçar reatividade após modificar uma gôndola
 */
function forceReactivity(gondolaId: string, updatedGondola: any) {
    if (!currentState.value) return;
    
    const gondolaIndex = currentState.value.gondolas.findIndex(g => g.id === gondolaId);
    if (gondolaIndex === -1) return;
    
    // Força reatividade criando novo array de gôndolas
    currentState.value.gondolas = [
        ...currentState.value.gondolas.slice(0, gondolaIndex),
        { ...updatedGondola },
        ...currentState.value.gondolas.slice(gondolaIndex + 1)
    ];
}

/**
 * Helper para fazer merge profundo de dados de seção
 */
function mergeSection(originalSection: Section, sectionData: Partial<Section>): Section {
    const updatedSection = { ...originalSection };
    
    for (const key in sectionData) {
        if (Object.prototype.hasOwnProperty.call(sectionData, key)) {
            const newValue = sectionData[key as keyof Section];
            
            // Mesclagem profunda para 'settings'
            if (key === 'settings' && typeof newValue === 'object' && newValue !== null) {
                updatedSection.settings = {
                    ...originalSection.settings,
                    ...newValue
                };
                
                // Log para furos recalculados
                if ('holes' in newValue && Array.isArray((newValue as any).holes)) {
                    console.log(`Furos recalculados para seção ${originalSection.id}:`, (newValue as any).holes);
                }
            } else {
                (updatedSection as any)[key] = newValue;
            }
        }
    }
    
    return updatedSection;
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

    // Merge dos dados usando helper
    const updatedSection = mergeSection(gondola.sections[sectionIndex], sectionData);

    // Substitui o array inteiro para forçar reatividade
    gondola.sections = [
        ...gondola.sections.slice(0, sectionIndex),
        updatedSection,
        ...gondola.sections.slice(sectionIndex + 1)
    ];
    
    // Força reatividade no estado global
    forceReactivity(gondolaId, gondola);
    
    // Atualiza seção selecionada se for a mesma
    if (selectedSection.value?.id === sectionId) {
        selectedSection.value = mergeSection(selectedSection.value, sectionData);
    }
 
    recordChange();
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

export function setIsDragging(value: boolean) {
    isDragging.value = value;
}

export function disableDragging() {
    isDragging.value = false;
}

export function enableDragging() {
    isDragging.value = true;
}
