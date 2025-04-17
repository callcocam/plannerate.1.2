import type { Shelf as ShelfType } from "./shelves";
import type { Gondola } from './gondola';

interface Status {
    value: string;
    label: string;
    color: string;
  }
  
  interface Hole {
    // Defina as propriedades de um buraco na cremalheira aqui
    // Por exemplo:
    position: number;
    isFilled: boolean;
  }
  
  interface Settings {
    holes: Hole[];
  }
  
  interface Shelf extends ShelfType{
    // Defina as propriedades de uma prateleira
    id: string;
    shelf_height: number;
    shelf_position: number;
    shelf_width: number;
    shelf_depth: number;
    section_id: string;
    shelf_x_position?: number;
    alignment?: 'left' | 'right' | 'center' | 'justify' | string;
    // ... outras propriedades conforme necessário
  }
  
  interface Section {
    base_depth: number;          // Profundidade da base, ex: 40
    base_height: number;         // Altura da base, ex: 17
    base_width: number;          // Largura da base, ex: 130
    cremalheira_width: number;   // Largura da cremalheira, ex: 4
    gondola_id: string;          // ID da gôndola, ex: "01jry465k5769kzy98andhxqe9"
    height: number;              // Altura da seção, ex: 180
    hole_height: number;         // Altura do buraco, ex: 3
    hole_spacing: number;        // Espaçamento entre buracos, ex: 2
    hole_width: number;          // Largura do buraco, ex: 2
    id: string;                  // ID da seção, ex: "01jry465kajwrnvrv6dq2hswc7"
    name: string;                // Nome da seção, ex: "0# Main Section"
    num_shelves: number;         // Número de prateleiras, ex: 4
    ordering: number;            // Ordenação, ex: 0
    alignment?: 'left' | 'right' | 'center' | 'justify' | string;
    settings: Settings;          // Configurações, ex: { holes: Array(32) }
    shelf_height: number | null; // Altura da prateleira, ex: null
    shelves: Shelf[];            // Lista de prateleiras, ex: [Proxy(Object), ...]
    slug: string;                // Slug, ex: "0-main-section-1"
    status: Status;              // Status, ex: { value: 'published', ... }
    tenant_id: string;           // ID do tenant, ex: "01jrarbd7tccz0mks7trekbbrh"
    user_id: string;             // ID do usuário, ex: "01jrarbjws41jye4zs2ppr2vwe"
    width: number;               // Largura da seção, ex: 130
    gondola: Gondola;            // Gôndola associada (tipado)
  }
  
  // Exemplo de uso:
  // const section: Section = {
  //   base_depth: 40,
  //   base_height: 17,
  //   ...
  // };
  
  export type { Section, Shelf, Settings, Hole, Status };