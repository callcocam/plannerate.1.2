import { Section } from "@plannerate/types/sections";
import { Segment as SegmentType } from "@plannerate/types/segment";

interface Segment extends SegmentType {
    // Defina as propriedades de um segmento aqui
    id: string;
    position: number;
    width: number;
    // Adicione outras propriedades conforme necessário
}

interface ShelfSetting {
    // Defina as propriedades de configurações da prateleira
    key: string;
    value: any;
    // Adicione outras propriedades conforme necessário
}

interface Shelf {
    code: string;                     // Código da prateleira, ex: "SLF0-250416463"
    id: string;                       // ID único da prateleira, ex: "01jry465kds6haf0gfpcfj8mfq"
    ordering: number;                 // Ordenação da prateleira, ex: 0
    alignment?: 'left' | 'right' | 'center' | 'justify' | string | null; // ADICIONADO (opcional/nullable)
    product_type: 'normal' | string;  // Tipo de produto, ex: "normal"
    quantity: number | null;          // Quantidade, ex: null
    reload: string;                   // Data de recarga, ex: "2025-04-16 02:17:07"
    section_id: string;               // ID da seção associada, ex: "01jry465kajwrnvrv6dq2hswc7"
    segments: Segment[];              // Array de segmentos da prateleira
    settings: ShelfSetting[];         // Configurações da prateleira
    shelf_depth: number;              // Profundidade da prateleira em cm, ex: 40
    shelf_height: number;             // Altura da prateleira em cm, ex: 4
    shelf_position: number;           // Posição vertical da prateleira em cm, ex: 2
    shelf_width: number;              // Largura da prateleira em cm, ex: 125
    spacing: number;                  // Espaçamento, ex: 0
    status: 'published' | 'draft' | string; // Status da prateleira, ex: "published"
    tenant_id: string;                // ID do tenant, ex: "01jrarbd7tccz0mks7trekbbrh"
    user_id: string;                  // ID do usuário, ex: "01jrarbjws41jye4zs2ppr2vwe"

    // Propriedades adicionais que podem ser úteis em alguns cenários
    shelf_x_position?: number;        // Posição horizontal da prateleira (opcional)
    section?: Section;               // Seção associada à prateleira
}

// Exemplo de uso:
// const shelf: Shelf = {
//   code: "SLF0-250416463",
//   id: "01jry465kds6haf0gfpcfj8mfq",
//   ...
// };

export type { Shelf, Segment, ShelfSetting };