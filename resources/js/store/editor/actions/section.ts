// /store/editor/actions/section.ts
import type { Section } from '@plannerate/types/sections';
import { findGondola } from '../utils';
import { recordChange } from '../history';
import { isSectionEditing, selectedSection, isDragging } from '../state';
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
    // (Considerando que a ordenação no estado pode não estar correta antes desta função)
    const currentSectionIds = gondola.sections.map(s => s.id);
    const newSectionIds = updatedSections.map(s => s.id);

    // Verificamos apenas a ordem dos IDs por enquanto, pois a atualização de 'ordering' será feita abaixo.
    if (JSON.stringify(currentSectionIds) === JSON.stringify(newSectionIds)) {
        // Poderíamos adicionar uma verificação mais profunda aqui se necessário,
        // comparando também o campo 'ordering' atual com o novo índice, mas
        // geralmente, se a ordem dos IDs não mudou, a intenção não é reordenar.
        console.log('Ordem dos IDs das seções não mudou.');
        // No entanto, ainda pode ser necessário atualizar os campos 'ordering' se estiverem dessincronizados.
        // Vamos garantir que o estado reflita a ordem atualizada de qualquer maneira.
    }

    // Atualiza o array de seções com a nova ordem E com o campo ordering atualizado
    gondola.sections = updatedSections;
    console.log(`Nova ordem das seções definida e campo 'ordering' atualizado para a gôndola ${gondolaId}`);
    recordChange(); // Registra a mudança para que as seções atualizadas sejam salvas posteriormente
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
// export function setSectionAlignment(gondolaId: string, sectionId: string, alignment: string | null) {
//     const path = findPath(gondolaId, sectionId, null, 'setSectionAlignment');
//     if (!path) return;

//     const { section } = path;

//     if (section.alignment !== alignment) {
//         section.alignment = alignment;
//         console.log(`Alinhamento da seção ${sectionId} definido para ${alignment}`);
//         recordChange();
//     } else {
//         console.log(`Alinhamento da seção ${sectionId} já era ${alignment}.`);
//     }
// }

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
    
    // Cria o objeto atualizado com mesclagem profunda para propriedades aninhadas
    const updatedSection = { ...originalSection };
    
    // Mescla as propriedades, tratando especialmente objetos aninhados como 'settings'
    for (const key in sectionData) {
        if (Object.prototype.hasOwnProperty.call(sectionData, key)) {
            const newValue = sectionData[key as keyof Section];
            
            // Para propriedades de objeto aninhado como 'settings', fazer mesclagem profunda
            if (key === 'settings' && typeof newValue === 'object' && newValue !== null) {
                updatedSection.settings = {
                    ...originalSection.settings,
                    ...newValue
                };
                
                // Log específico para furos recalculados
                if (newValue && typeof newValue === 'object' && 'holes' in newValue && Array.isArray((newValue as any).holes)) {
                    console.log(`Furos recalculados para seção ${sectionId}:`, (newValue as any).holes);
                }
            } else {
                (updatedSection as any)[key] = newValue;
            }
        }
    }

    // Atualiza a seção dentro do array da gôndola
    gondola.sections[sectionIndex] = updatedSection;
    
    // Se a seção atualizada for a mesma que está selecionada, atualiza as propriedades da ref
    if (selectedSection.value && selectedSection.value.id === sectionId) {
        console.log(`Atualizando propriedades da ref selectedSection para ${sectionId}`);
        
        // Para propriedades aninhadas como settings, fazer mesclagem profunda
        for (const key in sectionData) {
            if (Object.prototype.hasOwnProperty.call(sectionData, key)) {
                const newValue = sectionData[key as keyof Section];
                
                if (key === 'settings' && typeof newValue === 'object' && newValue !== null) {
                    selectedSection.value.settings = {
                        ...selectedSection.value.settings,
                        ...newValue
                    };
                } else {
                    (selectedSection.value as any)[key] = newValue;
                }
            }
        }
    }

    console.log(`Dados da seção ${sectionId} atualizados.`);
    console.log('Settings atualizados:', updatedSection.settings);
    recordChange(); // Registra após todas as mutações
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
