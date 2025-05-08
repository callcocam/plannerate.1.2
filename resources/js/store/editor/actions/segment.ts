// /store/editor/actions/segment.ts
import type { Segment } from '@plannerate/types/segment';
import { findGondola, findPath, findSection, findShelf } from '../utils';
import { recordChange } from '../history'; 
import { isLoading } from '../state';
/**
 * Adiciona um novo segmento a uma prateleira específica
 * @param gondolaId ID da gôndola
 * @param sectionId ID da seção
 * @param shelfId ID da prateleira
 * @param newSegment Dados do novo segmento
 */
export function addSegmentToShelf(gondolaId: string, sectionId: string, shelfId: string, newSegment: Segment) {
    const path = findPath(gondolaId, sectionId, shelfId, 'addSegmentToShelf');
    if (!path) return;

    const { shelf } = path;
    
    if (typeof newSegment.id === 'string') {
        if (!shelf) return;
        shelf.segments.push(newSegment as import('@/types/shelves').Segment); 
        recordChange(true);
        
    } else {
        console.error('Tentativa de adicionar segmento sem ID.', newSegment);
    }
}

/**
 * Define a ordem dos segmentos para uma prateleira específica
 * @param gondolaId ID da gôndola
 * @param sectionId ID da seção
 * @param shelfId ID da prateleira
 * @param newSegments Array de segmentos na nova ordem
 */
export function setShelfSegmentsOrder(gondolaId: string, sectionId: string, shelfId: string, newSegments: Segment[]) {
    const path = findPath(gondolaId, sectionId, shelfId, 'setShelfSegmentsOrder');
    if (!path) return;

    const { shelf } = path;

    // Compara se a nova ordem é realmente diferente da atual
    const currentSegmentIds = shelf?.segments.map(seg => seg.id);
    const newSegmentIds = newSegments.map(seg => seg.id);

    if (JSON.stringify(currentSegmentIds) === JSON.stringify(newSegmentIds)) {
        console.log(`Ordem dos segmentos na prateleira ${shelfId} não mudou.`);
        return;
    }

    // Verifica se todos os segmentos recebidos têm ID
    if (!newSegments.every(seg => typeof seg.id === 'string')) {
        console.error('Tentativa de definir ordem com segmento sem ID.', newSegments);
        return;
    }

    if (!shelf) return;
    shelf.segments = [...newSegments as import('@/types/shelves').Segment[]];
    console.log(`Nova ordem dos segmentos definida para a prateleira ${shelfId}`);
    recordChange();
}

/**
 * Transfere um segmento de uma prateleira para outra
 * @param gondolaId ID da gôndola
 * @param oldSectionId ID da seção de origem
 * @param oldShelfId ID da prateleira de origem
 * @param newSectionId ID da seção de destino
 * @param newShelfId ID da prateleira de destino
 * @param segmentId ID do segmento a ser transferido
 * @param newPositionX Nova posição X relativa (opcional)
 * @param newOrdering Nova ordem no destino (opcional)
 */
export function transferSegmentBetweenShelves(
    gondolaId: string,
    oldSectionId: string,
    oldShelfId: string,
    newSectionId: string,
    newShelfId: string,
    segmentId: string,
    newPositionX?: number,
    newOrdering?: number
) {
    const gondola = findGondola(gondolaId, 'transferSegmentBetweenShelves');
    if (!gondola) return;

    // Encontrar seção/prateleira de ORIGEM
    const oldSection = findSection(gondola, oldSectionId, 'transferSegmentBetweenShelves');
    if (!oldSection) return;

    const oldShelf = findShelf(oldSection, oldShelfId, 'transferSegmentBetweenShelves');
    if (!oldShelf) return;

    // Encontrar seção/prateleira de DESTINO
    const newSection = findSection(gondola, newSectionId, 'transferSegmentBetweenShelves');
    if (!newSection) return;

    const newShelf = findShelf(newSection, newShelfId, 'transferSegmentBetweenShelves');
    if (!newShelf) return;

    // Encontrar e remover segmento da prateleira de origem
    const segmentIndex = oldShelf.segments.findIndex(seg => seg.id === segmentId);
    if (segmentIndex === -1) {
        console.warn(`Segmento ${segmentId} não encontrado na prateleira ${oldShelfId}.`);
        return;
    }

    const segmentToMove = oldShelf.segments.splice(segmentIndex, 1)[0];

    // Atualizar dados do segmento movido
    segmentToMove.shelf_id = newShelfId;
    if (newPositionX !== undefined) {
        (segmentToMove as any).position = newPositionX;
    }

    // Atualizar ordem (anexar ao final se não especificado)
    segmentToMove.ordering = newOrdering ?? newShelf.segments.length + 1;

    // Adicionar segmento à prateleira de destino
    newShelf.segments.push(segmentToMove);

    console.log(`Segmento ${segmentId} transferido de ${oldShelfId} para ${newShelfId}`);
    recordChange();
}

/**
 * Atualiza a quantidade de uma camada (layer) específica dentro de um segmento
 * @param gondolaId ID da Gôndola
 * @param sectionId ID da Seção
 * @param shelfId ID da Prateleira
 * @param segmentId ID do Segmento
 * @param layerId ID da Camada (geralmente igual ao ID do produto)
 * @param newQuantity Nova quantidade para a camada
 */
export function updateLayerQuantity(
    gondolaId: string,
    sectionId: string,
    shelfId: string,
    segmentId: string,
    layerId: string,
    newQuantity: number
) {
    if (newQuantity < 0) {
        console.warn(`Tentativa de definir quantidade negativa (${newQuantity}). Abortando.`);
        return;
    }

    const path = findPath(gondolaId, sectionId, shelfId, 'updateLayerQuantity');
    if (!path) return;

    const { shelf } = path;

    // Utiliza a função utilitária para encontrar o segmento
    if (!shelf) return;
    const segment = shelf.segments.find(seg => seg.id === segmentId);
    if (!segment || !segment.layer) {
        console.warn(`Segmento ${segmentId} ou sua camada não encontrados.`);
        return;
    }

    isLoading.value = true;

    // Verifica se o layerId corresponde ao ID do produto da camada
    if (segment.layer.product.id === layerId) {
        if (segment.layer.quantity !== newQuantity) {
            segment.layer.quantity = newQuantity;
            console.log(`Quantidade da layer ${layerId} atualizada para ${newQuantity} no segmento ${segmentId}.`);
            recordChange();
        } else {
            console.log(`Quantidade da layer ${layerId} já era ${newQuantity}.`);
        }
    } else {
        console.warn(`Layer com ID ${layerId} não encontrada no segmento ${segmentId}.`);
    }

    isLoading.value = false;
}

/**
 * Remove um segmento específico de uma prateleira.
 * @param gondolaId ID da gôndola
 * @param sectionId ID da seção
 * @param shelfId ID da prateleira
 * @param segmentId ID do segmento a ser removido
 */
export function removeSegmentFromShelf(gondolaId: string, sectionId: string, shelfId: string, segmentId: string) {
    isLoading.value = true;
    const path = findPath(gondolaId, sectionId, shelfId, 'removeSegmentFromShelf');
    if (!path || !path.shelf) {
        console.warn('Caminho para prateleira não encontrado ao tentar remover segmento.');
        isLoading.value = false;
        return;
    }

    const { shelf } = path;
    const initialLength = shelf.segments.length;

    // Filtra os segmentos, mantendo apenas aqueles cujo ID não corresponde ao segmentId
    shelf.segments = shelf.segments.filter(segment => segment.id !== segmentId);

    // Verifica se um segmento foi realmente removido
    if (shelf.segments.length < initialLength) {
        console.log(`Segmento ${segmentId} removido da prateleira ${shelfId}.`);
        recordChange(); // Registra a mudança no histórico
    } else {
        console.warn(`Segmento ${segmentId} não encontrado na prateleira ${shelfId} para remoção.`);
    }
    isLoading.value = false;
}

//segment.quantity
export function updateSegmentQuantity(gondolaId: string, sectionId: string, shelfId: string, segmentId: string, newQuantity: number) {
    const path = findPath(gondolaId, sectionId, shelfId, 'updateSegmentQuantity');
    if (!path) return;

    const { shelf } = path;
    if (!shelf) return;
    const segment = shelf.segments.find(seg => seg.id === segmentId);
    if (!segment) {
        console.warn(`Segmento ${segmentId} não encontrado na prateleira ${shelfId}.`);
        return;
    }
    segment.quantity = newQuantity;
}