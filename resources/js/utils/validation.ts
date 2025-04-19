import type { Shelf } from '@plannerate/types/shelves';
import type { Layer, Segment } from '@plannerate/types/segment'; // Importar Segment novamente

/**
 * Valida se a largura total dos segmentos em uma prateleira, considerando uma mudança proposta
 * ou um segmento a ser adicionado, excede a largura da seção.
 *
 * @param shelf - O objeto da prateleira atual (com seus segmentos).
 * @param sectionWidth - A largura da seção que contém a prateleira.
 * @param changedLayerProductId - O ID do produto da camada sendo alterada (null se adicionando/removendo segmento).
 * @param proposedQuantity - A nova quantidade para a camada alterada (irrelevante se changedLayerProductId for null).
 * @param addedSegmentLayer - A camada de um novo segmento a ser adicionado (null se apenas alterando quantidade).
 * @returns Objeto com { isValid: boolean, totalWidth: number, sectionWidth: number }
 */
export function validateShelfWidth(
    shelf: Shelf,
    sectionWidth: number,
    changedLayerProductId: string | null,
    proposedQuantity: number,
    addedSegmentLayer: Layer | null = null
): { isValid: boolean; totalWidth: number; sectionWidth: number } {
    let totalWidth = 0;
    const segmentsToCalculate = [...(shelf.segments || [])]; 
    let temporarySegmentId: string | null = null;

    if (addedSegmentLayer) {
        temporarySegmentId = `temp-add-${Date.now()}`;
        // Usar 'as any' para contornar a incompatibilidade de tipos estrita
        segmentsToCalculate.push({
            id: temporarySegmentId,
            layer: addedSegmentLayer,
            // Não precisa mais dos outros campos mínimos aqui
        } as any); // <-- CAST PARA ANY
    }

    for (const seg of segmentsToCalculate) {
        // Garantir que o ID é string ou é o nosso temporário
        if (typeof seg.id !== 'string' || seg.id === '') {
            if(seg.id !== temporarySegmentId) continue;
        }

        const currentLayer = seg.layer as Layer | undefined; 
        if (!currentLayer?.product?.width || currentLayer.product.width <= 0) continue;

        const productWidth = currentLayer.product.width;
        const quantity = (changedLayerProductId && currentLayer.product.id === changedLayerProductId)
                          ? proposedQuantity
                          : currentLayer.quantity;
        const spacing = currentLayer.spacing ?? 0;

        let segmentWidth = 0;
        if (quantity > 0) {
            segmentWidth = productWidth * quantity;
            if (quantity > 1) {
                segmentWidth += spacing * (quantity - 1);
            }
        }
        totalWidth += segmentWidth;
    }

    totalWidth = parseFloat(totalWidth.toFixed(2));
    sectionWidth = parseFloat(sectionWidth.toFixed(2));
    const isValid = totalWidth <= sectionWidth;

    if (!isValid) {
        console.warn(`ShelfWidthValidation Failed: Shelf=${shelf.id}, Proposed Total=${totalWidth}, Section Width=${sectionWidth}`);
    }

    return { isValid, totalWidth, sectionWidth };
} 