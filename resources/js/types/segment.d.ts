// types/segment.ts
import { Layer as LayerType } from '@/types/layer';

interface MercadologicoNivel {
    mercadologico_nivel_1: string;
    mercadologico_nivel_2: string;
    mercadologico_nivel_3: string;
    mercadologico_nivel_4: string;
    mercadologico_nivel_5: string;
    mercadologico_nivel_6: string;
}

interface Product {
    id: string;
    name: string;
    description: string;
    price: number;
    image?: string;
    image_url?: string;
    width: number;
    height: number;
    depth?: number;
    sku?: string;
    ean?: string;
    layer: Layer;
    category_id?: string;
    created_at?: string;
    updated_at?: string;
    mercadologico_nivel?: MercadologicoNivel;
}

// Interface para o objeto Layer dentro do Segment
interface Layer extends LayerType {
    id: string;
    tabindex: number;
    tenant_id?: string;
    user_id?: string | null;
    segment_id: string;
    product_id: string;
    ordering?: number;
    position?: number;
    quantity: number;
    spacing?: number;
    height?: number;
    alignment?: 'left' | 'right' | 'center' | 'justify' | string | null;
    status?: 'published' | 'draft' | string;
    product: Product;
    segment?: Segment;
}

// Interface para configurações do segmento (se necessário)
interface SegmentSetting {
    key: string;
    value: any;
}

// Interface principal para Segment
interface Segment {
    id?: string;               // ID único do segmento, ex: "01jry47dsydth3vze2rhc1as9g"
    user_id?: string | null;   // ID do usuário, ex: null
    tenant_id?: string;        // ID do tenant, ex: "01jrarbd7tccz0mks7trekbbrh"
    shelf_id: string;         // ID da prateleira associada, ex: "01jry465ktkffnxvtqyzaynm9e"
    position: number;         // Posição do segmento, ex: 0
    quantity: number;         // Quantidade, ex: 1
    spacing: number;          // Espaçamento, ex: 0
    ordering: number;         // Ordem do segmento, ex: 1
    alignment?: 'left' | 'right' | 'center' | 'justify' | string | null;
    width: number;            // Largura do segmento em cm, ex: 130
    settings: SegmentSetting[] | null; // Configurações do segmento
    status: 'published' | 'draft' | string; // Status do segmento, ex: "published"
    layer: Layer;             // Informações sobre a camada associada 
    tabindex: number;
}

// Exemplo de uso:
// const segment: Segment = {
//   id: "01jry47dsydth3vze2rhc1as9g",
//   layer: { ... },
//   ...
// };

export type { Segment, Layer, SegmentSetting, Product };