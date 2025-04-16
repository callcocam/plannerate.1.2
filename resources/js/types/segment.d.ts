// types/segment.ts

// Interface para o objeto Layer dentro do Segment
interface Layer {
    id: string;
    tenant_id: string;
    user_id: string | null;
    segment_id: string;
    product_id: string;
    // Adicione outras propriedades conforme necessário
    ordering?: number;
    position?: number;
    quantity?: number;
}

// Interface para configurações do segmento (se necessário)
interface SegmentSetting {
    key: string;
    value: any;
}

// Interface principal para Segment
interface Segment {
    id: string;               // ID único do segmento, ex: "01jry47dsydth3vze2rhc1as9g"
    layer: Layer;             // Informações sobre a camada associada
    ordering: number;         // Ordem do segmento, ex: 1
    position: number;         // Posição do segmento, ex: 0
    quantity: number;         // Quantidade, ex: 1
    settings: SegmentSetting[] | null; // Configurações do segmento
    shelf_id: string;         // ID da prateleira associada, ex: "01jry465ktkffnxvtqyzaynm9e"
    spacing: number;          // Espaçamento, ex: 0
    tenant_id: string;        // ID do tenant, ex: "01jrarbd7tccz0mks7trekbbrh"
    user_id: string | null;   // ID do usuário, ex: null
    width: number;            // Largura do segmento em cm, ex: 130
}

// Exemplo de uso:
// const segment: Segment = {
//   id: "01jry47dsydth3vze2rhc1as9g",
//   layer: { ... },
//   ...
// };

export type { Segment, Layer, SegmentSetting };