[Estrutura de pastas sugerida]{.mark}

src/

planogram/

types.ts

mixAnalysis.ts

stockTarget.ts

autoPlanogram.ts

templates/

amaciantes.ts

registry.ts

orchestrator.ts

useAutoPlanogram.ts

components/

AutoPlanogramRunner.vue

TemplateEditor.vue

PolicyEditor.vue

VersionsCompare.vue

[src/planogram/types.ts]{.mark}

// Tipos base e contratos compartilhados

export type ID = string;

export interface Dim { L: number; A: number; P: number; } // mm

export interface SKU {

id: ID;

ean?: string;

nome: string;

departamento: string;

categoria: string;

subcategoria?: string;

marca: string;

tipo: string; // ex.: \"concentrado\", \"diluido\", etc.

linha?: string; // família/variante

dim: Dim;

vendas?: { qtd?: number; valor?: number; margem?: number };

leadTimeDias?: number;

fornecedorVar?: number;

prevScore?: number; // score da versão anterior (anti-churn)

mix?: SKUMixInfo; // cálculo do sortimento (porta da macro)

}

export interface SKUMixInfo {

classeABC?: \'A\'\|\'B\'\|\'C\';

ranking?: number;

retirarMix?: boolean;

status?: \'Ativo\'\|\'Inativo\';

mediaPonderada?: number; // métrica K da macro

pctIndividual?: number; // L

pctAcumulado?: number; // M

}

export interface Shelf { id: ID; largura_mm: number; altura_mm: number;
prof_mm: number; }

export interface Module { id: ID; prateleiras: Shelf\[\]; }

export interface Gondola { id: ID; modulos: Module\[\]; }

export type PlacementFlag = \'OK\'\|\'LOW\'\|\'TO_REMOVE\';

export interface PriceFlag { kind: \'PRICE_DOUBLE\'; reason: string; }

export interface Placement {

sku_id: ID;

modulo_id: ID;

prateleira_id: ID;

x_inicio_mm: number;

frentes: number;

largura_total_mm: number;

bloco_vertical_marca: string;

flag?: PlacementFlag;

priceFlags?: PriceFlag\[\];

}

export interface DailySales { sku_id: ID; series: number\[\]; }

export interface Zones { nobre: ID\[\]; intermediaria: ID\[\]; rodape:
ID\[\]; }

export interface MixPolicy {

useMixInScoring: boolean;

weightMixScore: number;

bonusA: number;

penaltyC: number;

penaltyRetirar: number;

penaltyInativo: number;

keepFlaggedWithOneFront: boolean;

forbidFlaggedInNoble: boolean;

}

export interface StockParams {

nsPorClasse: Record\<\'A\'\|\'B\'\|\'C\', number\>;

coberturaDiasPorClasse: Record\<\'A\'\|\'B\'\|\'C\', number\>;

permitirShortfall: boolean;

}

export interface BrandTarget {

marca: string;

min_total_share?: number;

min_noble_share?: number;

max_total_share?: number;

max_noble_share?: number;

}

export interface AntiChurnParams {

enabled: boolean;

movePenalty: number;

frontPenalty: number;

minScoreGainToMove: number;

}

export interface AdjacencyRule {

brandA?: string; typeA?: string;

brandB?: string; typeB?: string;

disallow: boolean;

}

export interface LockPolicy {

lockedSkus: string\[\];

lockedShelves: ID\[\];

indivisibleBrandBlocks: string\[\];

}

export type TemplateMode = \'auto\'\|\'hybrid\'\|\'template\';

export type TemplateStrategy = \'block_only\';

export interface TemplateSlot {

modulo_id: ID;

prateleira_id: ID;

width_mm?: number;

xStart_mm?: number;

xEnd_mm?: number;

brand?: string;

family?: string;

typeTag?: string;

sku_id?: ID;

min_fronts?: number;

max_fronts?: number;

}

export interface PlanogramTemplate {

id: string;

name: string;

strategy: TemplateStrategy;

slots: TemplateSlot\[\];

}

export interface AutoParams {

respeitar_dimensoes: boolean;

zonas: Zones;

reserva_gap_mm: number;

min_max_frentes: { min: number; max: number };

brandTargets?: BrandTarget\[\];

mixPolicy?: MixPolicy;

estoque?: StockParams;

antiChurn?: AntiChurnParams;

adjacency?: { rules: AdjacencyRule\[\] };

locks?: LockPolicy;

templateMode?: TemplateMode;

template?: PlanogramTemplate;

templatePenalty?: { breakAnchor: number; breakBlock: number };

// equivalências/substituíveis

equivalents?: Array\<{ sku_id: ID; substitutes: ID\[\] }\>;

// determinismo

seed?: number;

}

// Auditorias/relatórios

export interface ReducedFrontsAudit { sku_id:ID; marca:string;
tipo:string; prateleira_id:ID; modulo_id:ID; from:number; to:number;
reason:string; }

export interface RemovedAudit { sku_id:ID; marca?:string; tipo?:string;
prateleira_id:ID; modulo_id:ID; reason:string; }

export interface RebalanceAudit { fromModule:ID; toModule:ID;
marca?:string; deltaFronts:number; reason:string; }

export interface BrandAggregate { marca:string; totalFronts:number;
nobleFronts:number; }

export interface StockShortfallAudit { sku_id:ID; modulo_id:ID;
prateleira_id:ID; needed_fronts:number; allocated_fronts:number;
units_per_front:number; deficit_units:number; }

export interface StockSnapshot { sku_id:ID; modulo_id:ID;
prateleira_id:ID; zone:\'nobre\'\|\'intermediaria\'\|\'rodape\';
allocated_fronts:number; needed_fronts:number; units_per_front:number; }

export interface KPIs { shareHitPct?:number; stockHitPct?:number;
widthUsedPct?:number; cvHighCount?:number; toRemoveCount?:number; }

export interface AutoReport {

reducedFronts: ReducedFrontsAudit\[\];

removed: RemovedAudit\[\];

rebalanced: RebalanceAudit\[\];

brands: BrandAggregate\[\];

stockShortfalls?: StockShortfallAudit\[\];

stockSnapshots?: StockSnapshot\[\];

targetAdjusts?: any\[\];

kpis?: KPIs;

audit?: { policyId?:string; paramsVersion?:string; user?:string;
timestamp?:string; };

confidenceFlags?: Array\<{ sku_id:ID; reason:string }\>;

}

export interface AutoResult { placements: Placement\[\]; report:
AutoReport; }

// Orquestração por categoria/módulo/prateleira/faixa

export interface CategoryKey { department:string; category:string;
subcategory?:string; }

export interface CategoryPlacementRule {

moduleId: ID;

shelfId?: ID;

xStartMm?: number;

xEndMm?: number;

category: CategoryKey;

mode?: TemplateMode;

}

export interface PlanogramOrchestration {

intents: CategoryPlacementRule\[\];

defaultMode?: TemplateMode;

}

[src/planogram/mixAnalysis.ts  (PORT da macro
"AnaliseCompletaSortimentoEStatus")]{.mark}

import type { SKU, SKUMixInfo } from \'./types\';

export interface MixConfig {

pesoQtde: number; // B2

pesoValor: number; // B3

pesoMargem: number; // B4

corteA: number; // E2 (ex.: 0.8)

corteB: number; // E3 (ex.: 0.95)

}

/\*\*

\* Porta a macro AnaliseCompletaSortimentoEStatus:

\* - média ponderada (qtd, valor, margem)

\* - ordena por categoria + média ponderada

\* - % individual (L) e acumulada (M)

\* - classifica ABC por corteA/corteB

\* - ranking por categoria

\* - define \"retirarMix\" para C com L \< (metade do menor L dos B da
categoria)

\* - status por últimas compra/venda/estoque é responsabilidade do seu
backend (datas)

\*/

export function runMixAnalysis(

skus: SKU\[\],

cfg: MixConfig

): SKU\[\] {

// agrupar por categoria

const byCat = new Map\<string, SKU\[\]\>();

for (const s of skus) {

const key = s.categoria \|\| \'SEM_CAT\';

(byCat.get(key) \|\| byCat.set(key, \[\]).get(key)!).push(s);

}

for (const \[cat, arr\] of byCat) {

// média ponderada

for (const s of arr) {

const q = Number(s.vendas?.qtd \|\| 0);

const v = Number(s.vendas?.valor \|\| 0);

const m = Number(s.vendas?.margem \|\| 0);

let somaPesos = 0;

let mediaPond = 0;

if (q!==0){ somaPesos += cfg.pesoQtde; mediaPond += q\*cfg.pesoQtde; }

if (v!==0){ somaPesos += cfg.pesoValor; mediaPond += v\*cfg.pesoValor; }

if (m!==0){ somaPesos += cfg.pesoMargem; mediaPond += m\*cfg.pesoMargem;
}

const mp = somaPesos!==0 ? +(mediaPond/somaPesos).toFixed(6) : 0;

s.mix = s.mix \|\| {};

s.mix.mediaPonderada = mp;

}

// ordenar por média ponderada desc

arr.sort((a,b)=\> (b.mix?.mediaPonderada\|\|0) -
(a.mix?.mediaPonderada\|\|0));

// soma total

const total = arr.reduce((acc,s)=\> acc + (s.mix?.mediaPonderada\|\|0),
0) \|\| 1;

// % individual e acumulado

let acum = 0;

for (const s of arr) {

const ind = (s.mix!.mediaPonderada\|\|0) / total;

acum += ind;

s.mix!.pctIndividual = ind;

s.mix!.pctAcumulado = acum;

}

// classificação ABC

for (const s of arr) {

const a = s.mix!.pctAcumulado!;

if (a \<= cfg.corteA) s.mix!.classeABC = \'A\';

else if (a \<= cfg.corteB) s.mix!.classeABC = \'B\';

else s.mix!.classeABC = \'C\';

}

// ranking

arr.forEach((s, idx)=\> { s.mix!.ranking = idx+1; });

// menor percentual B

let menorB = 1;

for (const s of arr) if (s.mix!.classeABC===\'B\') menorB =
Math.min(menorB, s.mix!.pctIndividual\|\|1);

if (menorB===1) menorB = 0.02; // fallback se não houver B

// retirar do mix: C com % \< menorB/2

for (const s of arr) {

s.mix!.retirarMix = (s.mix!.classeABC===\'C\') &&
((s.mix!.pctIndividual\|\|1) \< (menorB/2));

if (!s.mix!.status) s.mix!.status = \'Ativo\'; // status básico; ajuste
no seu backend

}

}

return skus;

}

[src/planogram/stockTarget.ts  (PORT da macro
"CalcularEstoqueAlvo")]{.mark}

import type { SKU, DailySales } from \'./types\';

export interface StockConfig {

// tabelas por classe ABC (da aba \"Parametros nivel de serviço ABC\" e
\"Parametros modelo de reposição\")

nsPorClasse: Record\<\'A\'\|\'B\'\|\'C\', number\>; // nível serviço

coberturaDiasPorClasse: Record\<\'A\'\|\'B\'\|\'C\', number\>; //
cobertura base dias

cvAlertThreshold?: number; // alerta CV (ex.: 1.0)

}

const mean = (a:number\[\]) =\> a.length?
a.reduce((p,c)=\>p+c,0)/a.length : 0;

function stddev(a:number\[\]){ if(a.length\<2) return 0; const
m=mean(a); return
Math.sqrt(a.reduce((p,c)=\>p+(c-m)\*(c-m),0)/a.length); }

function median(a:number\[\]){ const b=\[\...a\].sort((x,y)=\>x-y);
const n=b.length; if(!n) return 0; return n%2? b\[(n-1)/2\] :
(b\[n/2-1\]+b\[n/2\])/2; }

function zFromService(ns:number){

// aproximação rápida do NormSInv

const p = Math.max(0.500001, Math.min(0.999999, ns));

const
a=\[2.50662823884,-18.61500062529,41.39119773534,-25.44106049637\];

const b=\[-8.47351093090,23.08336743743,-21.06224101826,3.13082909833\];

const
c=\[0.3374754822726147,0.9761690190917186,0.1607979714918209,0.0276438810333863,0.0038405729373609,0.0003951896511919,0.0000321767881768,0.0000002888167364,0.0000003960315187\];

const y=p-0.5; if(Math.abs(y)\<0.42){ const r=y\*y; return
(y\*((a\[0\]+a\[1\]\*r+a\[2\]\*r\*r+a\[3\]\*r\*r\*r))/(1+b\[0\]\*r+b\[1\]\*r\*r+b\[2\]\*r\*r\*r+b\[3\]\*r\*r\*r\*r));
}

const r = p\<0.5 ? Math.log(-Math.log(p)) : Math.log(-Math.log(1-p));

let
z=c\[0\]+r\*(c\[1\]+r\*(c\[2\]+r\*(c\[3\]+r\*(c\[4\]+r\*(c\[5\]+r\*(c\[6\]+r\*(c\[7\]+r\*c\[8\]))))))));

return p\<0.5 ? -z : z;

}

// previsão robusta: se CV\>1 usa mediana; senão smoothing exponencial
simples

function forecast(series:number\[\]){

const m=mean(series); const sd=stddev(series); const cv = m\>0 ? sd/m :
0;

if (cv\>1) return median(series);

const alpha=0.3; let s = series.find(x=\>Number.isFinite(x)) ?? 0;

for (const x of series){ s = alpha\*x + (1-alpha)\*s; }

return s;

}

export function computeStockTargetForSku(

sku: SKU,

daily: DailySales \| undefined,

cfg: StockConfig

){

const cls = (sku.mix?.classeABC \|\| \'C\') as \'A\'\|\'B\'\|\'C\';

const ns = cfg.nsPorClasse\[cls\] ?? 0.95;

let cov = cfg.coberturaDiasPorClasse\[cls\] ?? 10;

// ajustes por fornecedor/lead time

if (sku.leadTimeDias) cov += Math.max(0, Math.round(0.25 \*
sku.leadTimeDias));

if (sku.fornecedorVar) cov += Math.round(Math.min(7,
sku.fornecedorVar));

const series = daily?.series \|\| \[\];

const media = forecast(series);

const sigma = stddev(series);

const z = zFromService(ns);

const seg = z \* sigma;

const minimo = media \* cov;

const alvo = minimo + seg;

const cv = media\>0 ? sigma/media : 0;

const lowConfidence = (cfg.cvAlertThreshold ?? 1) \<= cv \|\|
series.length \< 7; // poucos dias ou CV alto

return { media, sigma, z, seg, minimo, alvo, cv, lowConfidence };

}

/\*\*

\* Retorna mapa sku_id -\> { alvoFrentes, unitsPerFront(depende da
prateleira), lowConfidence }

\* O cálculo de frentes finais é feito no motor, quando sabemos a
profundidade da prateleira.

\*/

export function computeStockTargets(

skus: SKU\[\],

dailies: DailySales\[\],

cfg: StockConfig

) {

const map = new Map\<string, ReturnType\<typeof
computeStockTargetForSku\>\>();

const index = new Map\<string, DailySales\>(dailies.map(d=\>\[d.sku_id,
d\]));

for (const s of skus){

map.set(s.id, computeStockTargetForSku(s, index.get(s.id), cfg));

}

return map;

}

[src/planogram/autoPlanogram.ts  *(MOTOR consolidado + integra
macros)*]{.mark}

import {

ID, SKU, Shelf, Gondola, Placement, PlacementFlag, DailySales,
AutoParams,

AutoResult, AutoReport, AdjacencyRule, BrandTarget

} from \'./types\';

import { runMixAnalysis, MixConfig } from \'./mixAnalysis\';

import { computeStockTargetForSku } from \'./stockTarget\';

// ========= Utils =========

const clamp = (v:number, lo=0, hi=1)=\> Math.max(lo, Math.min(hi, v));

const sum = (a:number\[\])=\> a.reduce((p,c)=\>p+c,0);

const mean = (arr:number\[\])=\> arr.length?
arr.reduce((p,c)=\>p+c,0)/arr.length : 0;

function stddev(arr:number\[\]){ if(arr.length\<2) return 0; const
m=mean(arr); const v=arr.reduce((p,c)=\>p+(c-m)\*(c-m),0)/arr.length;
return Math.sqrt(v); }

const seededRand = (seed:number)=\>{ let t=seed\|\|1234567; return ()=\>
(t = (t\*1664525+1013904223)%4294967296)/4294967296; };

export const PRESET_DEFAULT: AutoParams = {

respeitar_dimensoes: true,

zonas: { nobre: \[\], intermediaria: \[\], rodape: \[\] },

reserva_gap_mm: 8,

min_max_frentes: { min: 1, max: 10 },

mixPolicy: {

useMixInScoring: true, weightMixScore: 0.30,

bonusA: 0.05, penaltyC: 0.05, penaltyRetirar: 0.15, penaltyInativo:
0.20,

keepFlaggedWithOneFront: true, forbidFlaggedInNoble: true

},

estoque: {

nsPorClasse: { A: 0.98, B: 0.95, C: 0.90 },

coberturaDiasPorClasse: { A: 14, B: 10, C: 7 },

permitirShortfall: true

},

antiChurn: { enabled: true, movePenalty: 0.05, frontPenalty: 0.02,
minScoreGainToMove: 0.03 },

adjacency: { rules: \[\] },

locks: { lockedSkus: \[\], lockedShelves: \[\], indivisibleBrandBlocks:
\[\] },

templateMode: \'auto\',

template: undefined,

templatePenalty: { breakAnchor: 0.04, breakBlock: 0.03 },

equivalents: \[\],

seed: 1234

};

// ========= Adjacência =========

function forbidAdjacent(a:{marca:string; tipo:string}, b:{marca:string;
tipo:string}, rules:AdjacencyRule\[\]){

for(const r of (rules\|\|\[\])){

if(!r.disallow) continue;

const aOk = (!r.brandA \|\| r.brandA===a.marca) && (!r.typeA \|\|
r.typeA===a.tipo);

const bOk = (!r.brandB \|\| r.brandB===b.marca) && (!r.typeB \|\|
r.typeB===b.tipo);

const aOkSwap = (!r.brandA \|\| r.brandA===b.marca) && (!r.typeA \|\|
r.typeA===b.tipo);

const bOkSwap = (!r.brandB \|\| r.brandB===a.marca) && (!r.typeB \|\|
r.typeB===a.tipo);

if ((aOk&&bOk) \|\| (aOkSwap&&bOkSwap)) return true;

}

return false;

}

function sameFamily(a:SKU,b:SKU){ return a.marca===b.marca && !!a.linha
&& a.linha===b.linha; }

function zoneOfShelf(shelfId:ID, p:AutoParams):
\'nobre\'\|\'intermediaria\'\|\'rodape\' {

if (p.zonas.nobre.includes(shelfId)) return \'nobre\';

if (p.zonas.intermediaria.includes(shelfId)) return \'intermediaria\';

return \'rodape\';

}

function reservationKeyForSku(sku:SKU): string {

if (sku.tipo?.toLowerCase().includes(\'concent\')) return
\'type:concentrado\';

if (sku.tipo?.toLowerCase().includes(\'diluid\')) return
\'type:diluido\';

return \'\';

}

// ========= MIX/ABC (PORT macro) =========

export function applyMixMacroLike(

skus: SKU\[\],

cfg: MixConfig // pesos & cortes

){

return runMixAnalysis(skus, cfg);

}

// ========= Núcleo do motor =========

export function generatePlanogram(

gondola: Gondola,

skusOriginal: SKU\[\],

params: AutoParams = PRESET_DEFAULT,

dailySales?: DailySales\[\],

placementsPrev?: Placement\[\]

): AutoResult {

// Determinismo

const rand = seededRand(params.seed \|\| 1234);

// 1) Aplicar MIX macro (se quiser garantir explicitamente antes do
score)

const mixCfg: MixConfig = {

pesoQtde: 1, pesoValor: 1, pesoMargem: 1, // ajuste via UI se quiser
expor

corteA: 0.8, corteB: 0.95

};

const skus = applyMixMacroLike(skusOriginal.map(s=\> ({\...s})),
mixCfg);

const report: AutoReport = { reducedFronts: \[\], removed: \[\],
rebalanced: \[\], brands: \[\], confidenceFlags: \[\] };

const placements: Placement\[\] = \[\];

// índices prateleiras

const shelfIndex = new Map\<ID,{ shelf:Shelf, moduleId:ID }\>();

for (const m of gondola.modulos) for (const sh of m.prateleiras)
shelfIndex.set(sh.id, { shelf:sh, moduleId:m.id });

const prevMap = new Map\<string, Placement\[\]\>();
(placementsPrev\|\|\[\]).forEach(p=\>{ const
arr=prevMap.get(p.sku_id)\|\|\[\]; arr.push(p); prevMap.set(p.sku_id,
arr); });

// reservas do template (block_only)

const useTemplate = !!params.template && params.templateMode!==\'auto\';

const hardTemplate = useTemplate && params.templateMode===\'template\';

const reservations = new Map\<ID, Array\<{ key:string; cap:number;
used:number; xStart?:number; xEnd?:number }\>\>();

if (useTemplate && params.template?.strategy===\'block_only\'){

for (const s of params.template.slots){

const key = s.typeTag ? \`type:\${s.typeTag}\` : s.brand ?
\`brand:\${s.brand}\` : s.family ? \`family:\${s.family}\` : \'\';

const arr = reservations.get(s.prateleira_id) \|\| \[\];

arr.push({ key, cap: s.width_mm ?? 0, used:0, xStart: s.xStart_mm, xEnd:
s.xEnd_mm });

reservations.set(s.prateleira_id, arr);

if (hardTemplate && s.brand)
params.locks?.indivisibleBrandBlocks.push(s.brand);

}

}

// normalização para score base

const maxQ = Math.max(1, \...skus.map(s=\>s.vendas?.qtd??0));

const maxV = Math.max(1, \...skus.map(s=\>s.vendas?.valor??0));

const maxM = Math.max(1, \...skus.map(s=\>s.vendas?.margem??0));

const norm = (x:number, m:number)=\> x / (x + (x\<1?1:m));

const ac = params.antiChurn \|\| { enabled:false, movePenalty:0.05,
frontPenalty:0.02, minScoreGainToMove:0.03 };

const mp = params.mixPolicy!;

const salesMap = new Map\<ID,
number\[\]\>((dailySales\|\|\[\]).map(d=\>\[d.sku_id, d.series\]));

// 2) Enriquecer SKUs com score + estoque-alvo + flags

const enriched = skus.map(s=\>{

let score = 0.5\*norm(s.vendas?.qtd??0, maxQ) +
0.3\*norm(s.vendas?.valor??0, maxV) + 0.2\*norm(s.vendas?.margem??0,
maxM);

// mix no score + bônus/penalidades ABC/inativo/retirar

const m = s.mix;

if (mp.useMixInScoring && typeof m?.mediaPonderada===\'number\'){

const mixNorm = Math.min(1, Math.max(0, m.mediaPonderada /
(m.mediaPonderada + 1)));

score = (1-mp.weightMixScore)\*score + mp.weightMixScore\*mixNorm;

}

if (m?.classeABC===\'A\') score += mp.bonusA;

if (m?.classeABC===\'C\') score -= mp.penaltyC;

if (m?.retirarMix) score -= mp.penaltyRetirar;

if (m?.status===\'Inativo\') score -= mp.penaltyInativo;

// anti-churn (preemptivo)

if (ac.enabled){

const predictedGain = score - (s.prevScore ?? score);

if (predictedGain \< ac.minScoreGainToMove) score = Math.max(0, score -
ac.movePenalty);

}

score = clamp(score, 0, 1);

// estoque-alvo (usa porta da macro 2)

let cv = 0; let lowConfidence = false;

const series = salesMap.get(s.id) \|\| \[\];

if (series.length){

const st = computeStockTargetForSku(s, { sku_id:s.id, series }, {

nsPorClasse: params.estoque!.nsPorClasse,

coberturaDiasPorClasse: params.estoque!.coberturaDiasPorClasse,

cvAlertThreshold: 1

});

cv = st.cv; lowConfidence = st.lowConfidence;

if (lowConfidence) report.confidenceFlags!.push({ sku_id: s.id, reason:
\'CV\>1 ou série curta\' });

}

const flagged = !!(m?.retirarMix \|\| m?.status===\'Inativo\');

const visualFlag: PlacementFlag = flagged? \'TO_REMOVE\' :
(m?.classeABC===\'C\' ? \'LOW\' : \'OK\');

return { \...s, score, \_visualFlag:visualFlag,
\_flaggedToRemove:flagged, \_cv:cv };

});

function suggestFronts(score:number){ const {min,max} =
params.min_max_frentes; return clamp(Math.round(min + (max-min)\*score),
min, max); }

// 3) Alocação por prateleira

for (const m of gondola.modulos){

for (const sh of m.prateleiras){

const isNoble = params.zonas.nobre.includes(sh.id);

const lockedShelf = params.locks?.lockedShelves?.includes(sh.id);

if (lockedShelf) continue;

const cand = enriched.filter(s=\> (!params.respeitar_dimensoes \|\|
(s.dim.A\<=sh.altura_mm && s.dim.P\<=sh.prof_mm)));

// verticalização por marca

const byBrand = new Map\<string, typeof cand\>();

for (const s of cand)
(byBrand.get(s.marca)\|\|byBrand.set(s.marca,\[\]).get(s.marca)!).push(s);

const groups = Array.from(byBrand, (\[k,arr\])=\>({ key:k, arr }));

// ordenação determinística: média do score, desempate por marca + seed

groups.sort((g1,g2)=\>{

const m1=sum(g1.arr.map(x=\>x.score))/Math.max(g1.arr.length,1);

const m2=sum(g2.arr.map(x=\>x.score))/Math.max(g2.arr.length,1);

if (m2!==m1) return m2-m1;

return g1.key.localeCompare(g2.key);

});

let x = 0; const gap = params.reserva_gap_mm; const largura =
sh.largura_mm;

const row: Placement\[\] = \[\];

const lastNeighbor = ()=\> { const
r=\[\...row\].sort((a,b)=\>a.x_inicio_mm-b.x_inicio_mm); return
r\[r.length-1\]; };

for (const g of groups){

// dentro do bloco: ABC \> valor \> ranking

g.arr.sort((a,b)=\>{

const ord=(c?:\'A\'\|\'B\'\|\'C\')=\> c===\'A\'?0:c===\'B\'?1:2;

const aA=ord(a.mix?.classeABC), bA=ord(b.mix?.classeABC);

if (aA!==bA) return aA-bA;

const v=(x:SKU)=\>x.vendas?.valor\|\|0;

if (v(b)!==v(a)) return v(b)-v(a);

const r=(x:SKU)=\>x.mix?.ranking??9999;

if (r(a)!==r(b)) return r(a)-r(b);

return a.id.localeCompare(b.id);

});

for (const s of g.arr){

if (isNoble && s.\_flaggedToRemove &&
(params.mixPolicy?.forbidFlaggedInNoble!==false)) {

report.removed.push({ sku_id: s.id, marca:s.marca, tipo:s.tipo,
prateleira_id: sh.id, modulo_id:m.id, reason:\'to_remove_in_noble\' });

continue;

}

if (params.locks?.lockedSkus?.includes(s.id)){

const prev = prevMap.get(s.id)?.\[0\];

if (!prev \|\| prev.modulo_id!==m.id \|\| prev.prateleira_id!==sh.id)
continue;

}

const unitsPerFront = Math.max(1, Math.floor(sh.prof_mm / Math.max(1,
s.dim.P)));

let fr = s.\_flaggedToRemove ? 1 : suggestFronts(s.score);

// estoque-alvo → ajusta frentes mínimas

const series = salesMap.get(s.id) \|\| \[\];

if (params.estoque && series.length && !s.\_flaggedToRemove){

const st = computeStockTargetForSku(s, { sku_id:s.id, series }, {

nsPorClasse: params.estoque.nsPorClasse,

coberturaDiasPorClasse: params.estoque.coberturaDiasPorClasse,

cvAlertThreshold: 1

});

const needFronts = Math.max(1, Math.ceil(st.alvo / unitsPerFront));

fr = Math.max(fr, needFronts);

}

// reservas template (block_only)

const resArr = reservations.get(sh.id) \|\| \[\];

const wantKey = reservationKeyForSku(s);

const capObj = wantKey ? resArr.find(r =\> r.key===wantKey) : undefined;

let larguraFinal = fr \* s.dim.L;

if (capObj){

const remaining = Math.max(0, capObj.cap - capObj.used);

while (remaining\>0 && larguraFinal\>remaining &&
fr\>params.min_max_frentes.min){

fr -= 1; larguraFinal = fr \* s.dim.L;

}

if (hardTemplate && larguraFinal\>remaining){

report.removed.push({ sku_id:s.id, marca:s.marca, tipo:s.tipo,
prateleira_id:sh.id, modulo_id:m.id, reason:\'template_block_full\' });

continue;

}

if (!hardTemplate && larguraFinal\>remaining){

(report.targetAdjusts \|\|= \[\]).push({ action:\'template_break\',
details:\`spillover \${wantKey}\`, sku_id:s.id, modulo_id:m.id,
prateleira_id:sh.id, penalty: params.templatePenalty?.breakBlock ?? 0.03
});

}

}

// adjacência

const neighbor = lastNeighbor();

if (neighbor){

const nSku = cand.find(e=\>e.id===neighbor.sku_id)!;

if
(forbidAdjacent({marca:s.marca,tipo:s.tipo},{marca:nSku.marca,tipo:nSku.tipo},
params.adjacency?.rules\|\|\[\])){

(report.targetAdjusts \|\|= \[\]).push({ action:\'adjacency_violation\',
sku_id:s.id, modulo_id:m.id, prateleira_id:sh.id,
neighbor:neighbor.sku_id });

}

}

// cabe?

while (x + larguraFinal \> largura && fr\>params.min_max_frentes.min){

report.reducedFronts.push({ sku_id:s.id, marca:s.marca, tipo:s.tipo,
prateleira_id:sh.id, modulo_id:m.id, from:fr, to:fr-1,
reason:\'overflow\' });

fr -= 1; larguraFinal = fr\*s.dim.L;

}

if (x + larguraFinal \> largura){

report.removed.push({ sku_id:s.id, marca:s.marca, tipo:s.tipo,
prateleira_id:sh.id, modulo_id:m.id, reason:\'no_space\' });

continue;

}

// preço duplo (mesma família adjacente)

const neighbor2 = neighbor;

let priceFlags: PlacementFlag\[\]\|undefined;

if (neighbor2){

const n2 = cand.find(e=\>e.id===neighbor2.sku_id)!;

if (sameFamily(s, n2)) priceFlags = \[{ kind:\'PRICE_DOUBLE\',
reason:\`mesma família \${s.id}\~\${n2.id}\` } as any\];

}

row.push({

sku_id: s.id, modulo_id: m.id, prateleira_id: sh.id,

x_inicio_mm: x, frentes: fr, largura_total_mm: fr\*s.dim.L,

bloco_vertical_marca: s.marca, flag: s.\_visualFlag, priceFlags:
priceFlags as any

});

capObj && (capObj.used += larguraFinal);

x += larguraFinal + params.reserva_gap_mm;

// snapshots/shortfall (para KPIs)

const st2 = series.length ? computeStockTargetForSku(s, { sku_id:s.id,
series }, {

nsPorClasse: params.estoque!.nsPorClasse,

coberturaDiasPorClasse: params.estoque!.coberturaDiasPorClasse,

cvAlertThreshold: 1

}) : undefined;

const need = !s.\_flaggedToRemove && st2 ? Math.max(1,
Math.ceil(st2.alvo / unitsPerFront)) : 0;

(report.stockSnapshots \|\|= \[\]).push({

sku_id: s.id, modulo_id: m.id, prateleira_id: sh.id, zone:
zoneOfShelf(sh.id, params),

allocated_fronts: fr, needed_fronts: need, units_per_front:
unitsPerFront

});

if (!s.\_flaggedToRemove && need\>fr &&
(params.estoque?.permitirShortfall ?? true)){

(report.stockShortfalls \|\|= \[\]).push({

sku_id: s.id, modulo_id: m.id, prateleira_id: sh.id,

needed_fronts: need, allocated_fronts: fr, units_per_front:
unitsPerFront,

deficit_units: (need-fr)\*unitsPerFront

});

}

}

}

// "TO_REMOVE = 1 frente" único por SKU

const seen = new Set\<string\>();

for (let i=0;i\<row.length;i++){

const pl = row\[i\];

if (pl.flag!==\'TO_REMOVE\') continue;

if (seen.has(pl.sku_id)){ row.splice(i,1); i\--; report.removed.push({
sku_id:pl.sku_id, prateleira_id:pl.prateleira_id,
modulo_id:pl.modulo_id, reason:\'to_remove_dup\' }); continue; }

if (pl.frentes\>1){ const sku = skus.find(s=\>s.id===pl.sku_id)!;
pl.frentes=1; pl.largura_total_mm = sku.dim.L; }

seen.add(pl.sku_id);

}

placements.push(\...row);

}

}

// agregados e KPIs

report.brands = (()=\> {

const noble = new Set(params.zonas.nobre);

const map = new Map\<string,{total:number;noble:number}\>();

for (const pl of placements){

const v = map.get(pl.bloco_vertical_marca) \|\| { total:0, noble:0 };

v.total += pl.frentes;

if (noble.has(pl.prateleira_id)) v.noble += pl.frentes;

map.set(pl.bloco_vertical_marca, v);

}

return Array.from(map, (\[marca,v\])=\>({ marca, totalFronts:v.total,
nobleFronts:v.noble }));

})();

const snaps = report.stockSnapshots\|\|\[\];

const withNeed = snaps.filter(s=\>s.needed_fronts\>0);

const hit =
withNeed.filter(s=\>s.allocated_fronts\>=s.needed_fronts).length;

report.kpis = {

stockHitPct: withNeed.length? (hit/withNeed.length) : 1,

toRemoveCount: placements.filter(p=\>p.flag===\'TO_REMOVE\').length,

// widthUsedPct pode ser calculado por prateleira se quiser

};

report.audit = { policyId:\'default\', paramsVersion:\'1.0\',
user:\'operador\', timestamp:new Date().toISOString() };

return { placements, report };

}

// Slices utilitários para a UI

export function sliceGondolaByShelf(g: Gondola, shelfId: ID): Gondola {

const mods = g.modulos.map(m =\> {

const sh = m.prateleiras.find(p=\>p.id===shelfId);

return sh ? { id:m.id, prateleiras:\[sh\] } : { id:m.id,
prateleiras:\[\] as Shelf\[\] };

}).filter(m=\>m.prateleiras.length\>0);

return { id: \`\${g.id}\_\_shelf\_\_\${shelfId}\`, modulos: mods };

}

export function filterPlacementsByShelf(placements: Placement\[\],
shelfId: ID): Placement\[\] {

return placements.filter(p=\>p.prateleira_id===shelfId);

}

[src/planogram/templates/amaciantes.ts]{.mark}

import { Gondola, PlanogramTemplate } from \'../types\';

function topHalf(prats:{id:string; largura_mm:number}\[\],
takeTop=true){

const n = prats.length; const half = Math.ceil(n/2);

return takeTop? prats.slice(0,half) : prats.slice(half);

}

export function templateAmaciantesByModules(g: Gondola):
PlanogramTemplate {

const mods = g.modulos;

const slots: any\[\] = \[\];

if (mods.length===1){

const topP = topHalf(mods\[0\].prateleiras, true);

const botP = topHalf(mods\[0\].prateleiras, false);

for (const sh of topP) slots.push({ modulo_id:mods\[0\].id,
prateleira_id:sh.id, typeTag:\'concentrado\', width_mm: sh.largura_mm
});

for (const sh of botP) slots.push({ modulo_id:mods\[0\].id,
prateleira_id:sh.id, typeTag:\'diluido\', width_mm: sh.largura_mm });

} else if (mods.length===2){

for (const m of mods){

for (const sh of topHalf(m.prateleiras, true)) slots.push({
modulo_id:m.id, prateleira_id:sh.id, typeTag:\'concentrado\', width_mm:
sh.largura_mm });

for (const sh of topHalf(m.prateleiras, false)) slots.push({
modulo_id:m.id, prateleira_id:sh.id, typeTag:\'diluido\', width_mm:
sh.largura_mm });

}

} else {

for (const sh of mods\[0\].prateleiras) slots.push({
modulo_id:mods\[0\].id, prateleira_id:sh.id, typeTag:\'concentrado\',
width_mm: sh.largura_mm });

for (let i=1;i\<mods.length;i++){

for (const sh of mods\[i\].prateleiras) slots.push({
modulo_id:mods\[i\].id, prateleira_id:sh.id, typeTag:\'diluido\',
width_mm: sh.largura_mm });

}

}

return { id:\'amac-template\', name:\'Amaciantes --- Concentrados vs
Diluídos\', strategy:\'block_only\', slots };

}

[src/planogram/templates/registry.ts]{.mark}

import { Gondola, PlanogramTemplate } from \'../types\';

import { templateAmaciantesByModules } from \'./amaciantes\';

export function lookupTemplateFor(department:string, category:string,
gondola: Gondola): PlanogramTemplate \| undefined {

if (department.toLowerCase().includes(\'limpeza\') &&
category.toLowerCase().includes(\'amaci\')) {

return templateAmaciantesByModules(gondola);

}

return undefined;

}

[src/planogram/orchestrator.ts]{.mark}

import {

Gondola, SKU, DailySales, Placement, AutoParams, AutoResult,
PlanogramOrchestration,

CategoryPlacementRule, PlanogramTemplate

} from \'./types\';

import { generatePlanogram, PRESET_DEFAULT } from \'./autoPlanogram\';

function sortByGranularity(intents: CategoryPlacementRule\[\]){

const weight = (r:CategoryPlacementRule)=\> r.xStartMm!=null &&
r.xEndMm!=null ? 0 : (r.shelfId ? 1 : 2);

return \[\...intents\].sort((a,b)=\> weight(a)-weight(b));

}

function filterSkusByCategory(all:SKU\[\], key:{department:string;
category:string; subcategory?:string}){

return all.filter(s=\>

s.departamento===key.department &&

s.categoria===key.category &&

(key.subcategory ? s.subcategoria===key.subcategory : true)

);

}

function sliceGondolaForRule(g: Gondola, rule: CategoryPlacementRule):
Gondola {

if (rule.shelfId) {

const mod = g.modulos.find(m=\>m.id===rule.moduleId);

if (!mod) return { id:\`\${g.id}\_\_empty\`, modulos:\[\] };

const sh = mod.prateleiras.find(p=\>p.id===rule.shelfId);

if (!sh) return { id:\`\${g.id}\_\_empty\`, modulos:\[\] };

return { id:\`\${g.id}\_\_mod\_\${mod.id}\_\_sh\_\${sh.id}\`,
modulos:\[{ id:mod.id, prateleiras:\[sh\] }\] };

}

const mod = g.modulos.find(m=\>m.id===rule.moduleId);

if (!mod) return { id:\`\${g.id}\_\_empty\`, modulos:\[\] };

return { id:\`\${g.id}\_\_mod\_\${mod.id}\`, modulos:\[{ id:mod.id,
prateleiras:\[\...mod.prateleiras\] }\] };

}

function buildTemplateFromRule(subG: Gondola, rule:
CategoryPlacementRule): PlanogramTemplate {

const slots = \[\];

for (const m of subG.modulos){

for (const sh of m.prateleiras){

slots.push({

modulo_id: m.id, prateleira_id: sh.id,

width_mm: rule.xStartMm!=null && rule.xEndMm!=null ?
(rule.xEndMm-rule.xStartMm) : sh.largura_mm,

xStart_mm: rule.xStartMm, xEnd_mm: rule.xEndMm

});

}

}

return { id:\`rule\_\${rule.moduleId}\_\${rule.shelfId\|\|\'all\'}\`,
name:\`Rule Block\`, strategy:\'block_only\', slots };

}

function placementInsideRule(pl: Placement, rule:
CategoryPlacementRule){

if (pl.modulo_id!==rule.moduleId) return false;

if (rule.shelfId && pl.prateleira_id!==rule.shelfId) return false;

if (rule.xStartMm!=null && rule.xEndMm!=null){

const xEnd = pl.x_inicio_mm + pl.largura_total_mm;

return !(xEnd \<= rule.xStartMm \|\| pl.x_inicio_mm \>= rule.xEndMm);

}

return true;

}

function mergeInto(base: Placement\[\], add: Placement\[\], rule:
CategoryPlacementRule): Placement\[\] {

const filtered = base.filter(p=\> !placementInsideRule(p, rule));

return filtered.concat(add);

}

export async function generateWithOrchestration(

gondola: Gondola,

allSkusFromDepartment: SKU\[\],

sales?: DailySales\[\],

prev?: Placement\[\],

orchestration?: PlanogramOrchestration,

globalParams?: AutoParams

): Promise\<AutoResult\> {

if (!orchestration?.intents?.length){

return generatePlanogram(gondola, allSkusFromDepartment, {
\...(globalParams\|\|PRESET_DEFAULT), templateMode:\'auto\', template:
undefined }, sales, prev);

}

const intents = sortByGranularity(orchestration.intents);

let merged: Placement\[\] = \[\];

const partialReports:any\[\] = \[\];

for (const rule of intents){

const subG = sliceGondolaForRule(gondola, rule);

const catSkus = filterSkusByCategory(allSkusFromDepartment,
rule.category);

const p: AutoParams =
JSON.parse(JSON.stringify(globalParams\|\|PRESET_DEFAULT));

const mode = rule.mode ?? orchestration.defaultMode ?? \'hybrid\';

p.templateMode = mode;

p.template = (mode===\'auto\') ? undefined : buildTemplateFromRule(subG,
rule);

const prevLocal = (prev\|\|\[\]).filter(pl=\> placementInsideRule(pl,
rule));

const { placements, report } = generatePlanogram(subG, catSkus, p,
sales, prevLocal);

merged = mergeInto(merged, placements, rule);

partialReports.push({ rule, report });

}

const reportCombined = {

reducedFronts:
partialReports.flatMap((r:any)=\>r.report.reducedFronts\|\|\[\]),

removed: partialReports.flatMap((r:any)=\>r.report.removed\|\|\[\]),

rebalanced:
partialReports.flatMap((r:any)=\>r.report.rebalanced\|\|\[\]),

brands: partialReports.flatMap((r:any)=\>r.report.brands\|\|\[\]),

stockShortfalls:
partialReports.flatMap((r:any)=\>r.report.stockShortfalls\|\|\[\]),

stockSnapshots:
partialReports.flatMap((r:any)=\>r.report.stockSnapshots\|\|\[\]),

targetAdjusts:
partialReports.flatMap((r:any)=\>r.report.targetAdjusts\|\|\[\]),

audit: { policyId:\'default\', paramsVersion:\'1.0\', user:\'operador\',
timestamp:new Date().toISOString(), composed:true }

};

return { placements: merged, report: reportCombined };

}

[src/planogram/useAutoPlanogram.ts]{.mark}

import { ref, computed } from \'vue\';

import type { Gondola, SKU, Placement, AutoParams, DailySales,
PlanogramOrchestration, TemplateMode } from \'./types\';

import { PRESET_DEFAULT, generatePlanogram, sliceGondolaByShelf,
filterPlacementsByShelf } from \'./autoPlanogram\';

import { generateWithOrchestration } from \'./orchestrator\';

import { lookupTemplateFor } from \'./templates/registry\';

export function useAutoPlanogram(){

const loading = ref(false);

const params = ref\<AutoParams\>({ \...PRESET_DEFAULT });

const placements = ref\<Placement\[\]\>(\[\]);

const lastReport = ref\<any\>(null);

// versões simples em memória (pode trocar por LocalStorage)

const versions = ref\<any\[\]\>(\[\]);

function saveVersion(name=\'versão\'){

const v = { id: Math.random().toString(36).slice(2), name, createdAt:new
Date().toISOString(),

placements: JSON.parse(JSON.stringify(placements.value)),

report: JSON.parse(JSON.stringify(lastReport.value)),

params: JSON.parse(JSON.stringify(params.value))

};

versions.value.unshift(v); return v;

}

const kpis = computed(()=\>{

const rep=lastReport.value; if(!rep?.stockSnapshots?.length) return
null;

const snaps = rep.stockSnapshots as any\[\];

const withNeed = snaps.filter(s=\>s.needed_fronts\>0);

const hit =
withNeed.filter(s=\>s.allocated_fronts\>=s.needed_fronts).length;

const totalDeficitUnits =
(rep.stockShortfalls\|\|\[\]).reduce((a:number,b:any)=\>a+(b.deficit_units\|\|0),0);

return { totalWithNeed: withNeed.length, hit, hitPct: withNeed.length?
hit/withNeed.length : 1, totalDeficitUnits };

});

async function runGlobal(g:Gondola, skus:SKU\[\], daily?:DailySales\[\],
prev?:Placement\[\]){

loading.value=true;

try {

const { placements: out, report } = generatePlanogram(g, skus,
params.value, daily, prev);

placements.value = out; lastReport.value = report; return { placements:
out, report };

} finally { loading.value=false; }

}

async function runOrchestrated(g:Gondola, skusDept:SKU\[\],
orchestration:PlanogramOrchestration, daily?:DailySales\[\],
prev?:Placement\[\]){

loading.value=true;

try {

const { placements: out, report } = await generateWithOrchestration(g,
skusDept, daily, prev, orchestration, params.value);

placements.value = out; lastReport.value = report; return { placements:
out, report };

} finally { loading.value=false; }

}

async function runForShelf(g:Gondola, skus:SKU\[\], shelfId: string,
daily?:DailySales\[\], prevAll?:Placement\[\]){

loading.value=true;

try {

const gShelf = sliceGondolaByShelf(g, shelfId);

const shelfPrev = filterPlacementsByShelf(prevAll\|\|\[\], shelfId);

const { placements: out, report } = generatePlanogram(gShelf, skus,
params.value, daily, shelfPrev);

const others = placements.value.filter(p=\>p.prateleira_id!==shelfId);

placements.value = others.concat(out); lastReport.value = report;

return { placements: placements.value, report };

} finally { loading.value=false; }

}

function applyTemplateByCategory(department:string, category:string,
g:Gondola, mode:TemplateMode=\'hybrid\'){

const tpl = lookupTemplateFor(department, category, g);

params.value.template = tpl; params.value.templateMode = mode;

}

return {

loading, params, placements, lastReport, kpis, versions, saveVersion,

runGlobal, runOrchestrated, runForShelf, applyTemplateByCategory

};

}

[src/components/TemplateEditor.vue  *(drag & drop para
categorias)*]{.mark}

\<script setup lang=\"ts\"\>

import { ref, watchEffect } from \'vue\';

import draggable from \'vuedraggable\';

import type { Gondola, CategoryPlacementRule, TemplateMode, CategoryKey,
ID } from \'@/planogram/types\';

const props = defineProps\<{

gondola: Gondola;

department: string;

categories: Array\<{ category:string; subcategory?:string; color?:string
}\>;

defaultMode?: TemplateMode;

}\>();

const emit = defineEmits\<{ (e:\'update:intents\', intents:
CategoryPlacementRule\[\]):void }\>();

const palette = ref(props.categories.map(c =\> ({

id:
\`\${c.category}-\${c.subcategory\|\|\'all\'}-\${Math.random().toString(36).slice(2)}\`,

label: c.subcategory ? \`\${c.category} / \${c.subcategory}\` :
c.category,

category: { department: props.department, category: c.category,
subcategory: c.subcategory } as CategoryKey,

mode: props.defaultMode \|\| \'hybrid\',

color: c.color \|\| \'#2563eb\',

xStartMm: undefined as number\|undefined,

xEndMm: undefined as number\|undefined

})));

const grid = ref\<Record\<string, { items: any\[\] }\>\>({});

function slotKey(mId:ID, sId?:ID){ return
\`\${mId}::\${sId\|\|\'\_\_module\_\_\'}\`; }

for (const m of props.gondola.modulos) {

grid.value\[slotKey(m.id)\] = { items: \[\] };

for (const sh of m.prateleiras) grid.value\[slotKey(m.id, sh.id)\] = {
items: \[\] };

}

const intents = ref\<CategoryPlacementRule\[\]\>(\[\]);

watchEffect(()=\>{

const out: CategoryPlacementRule\[\] = \[\];

for (const key in grid.value){

const \[moduleId, shelfId\] = key.split(\'::\');

for (const it of grid.value\[key\].items){

out.push({

moduleId, shelfId: shelfId!==\'\_\_module\_\_\'? shelfId : undefined,

xStartMm: it.xStartMm, xEndMm: it.xEndMm, category: it.category, mode:
it.mode

});

}

}

intents.value = out;

emit(\'update:intents\', intents.value);

});

\</script\>

\<template\>

\<div class=\"grid grid-cols-12 gap-3\"\>

\<div class=\"col-span-3 border rounded p-3 bg-white\"\>

\<h3 class=\"font-semibold mb-2\"\>Categorias\</h3\>

\<draggable :list=\"palette\" group=\"{ name: \'cats\', pull: \'clone\',
put: false }\" item-key=\"id\" class=\"space-y-2\"
:clone=\"(el:any)=\>({ \...el, id: el.id + \'-cl\' })\"\>

\<template \#item=\"{element}\"\>

\<div class=\"px-2 py-1 rounded text-white text-sm\" :style=\"{
background: element.color }\"\>{{ element.label }}\</div\>

\</template\>

\</draggable\>

\<p class=\"text-xs text-gray-500 mt-3\"\>Arraste para um
módulo/prateleira. Clique no chip para editar modo/faixa.\</p\>

\</div\>

\<div class=\"col-span-9 space-y-4\"\>

\<div v-for=\"m in gondola.modulos\" :key=\"m.id\" class=\"border
rounded p-3 bg-white\"\>

\<div class=\"flex items-center justify-between mb-2\"\>

\<h3 class=\"font-medium\"\>Módulo {{ m.id }}\</h3\>

\<div class=\"text-xs text-gray-500\"\>Prateleiras: {{
m.prateleiras.length }}\</div\>

\</div\>

\<div class=\"mb-2\"\>

\<label class=\"text-xs text-gray-600\"\>Módulo inteiro\</label\>

\<draggable class=\"flex flex-wrap gap-2 p-2 border rounded
min-h-\[48px\]\" :list=\"grid\[\`\${m.id}::\_\_module\_\_\`\].items\"
group=\"cats\" item-key=\"id\"\>

\<template \#item=\"{element}\"\>\<Chip :item=\"element\"
:shelf-width=\"m.prateleiras\[0\]?.largura_mm \|\| 1000\"
/\>\</template\>

\</draggable\>

\</div\>

\<div class=\"grid gap-2\"\>

\<div v-for=\"sh in m.prateleiras\" :key=\"sh.id\"\>

\<div class=\"flex items-center justify-between\"\>\<div class=\"text-xs
text-gray-600\"\>Prateleira {{ sh.id }} --- {{ sh.largura_mm
}}mm\</div\>\</div\>

\<draggable class=\"flex flex-wrap gap-2 p-2 border rounded
min-h-\[48px\]\" :list=\"grid\[\`\${m.id}::\${sh.id}\`\].items\"
group=\"cats\" item-key=\"id\"\>

\<template \#item=\"{element}\"\>\<Chip :item=\"element\"
:shelf-width=\"sh.largura_mm\" /\>\</template\>

\</draggable\>

\</div\>

\</div\>

\</div\>

\</div\>

\</div\>

\</template\>

\<script lang=\"ts\"\>

import { defineComponent } from \'vue\';

export default defineComponent({

components:{

Chip: {

props:\[\'item\',\'shelfWidth\'\],

data(){ return { show:false, startPct:0, endPct:100, localMode:
this.item.mode \|\| \'hybrid\' }; },

watch:{ localMode(v){ this.item.mode=v; }, startPct(){}, endPct(){} },

methods:{

applyBand(){ this.item.xStartMm =
Math.round((Math.min(this.startPct,this.endPct)/100) \*
(this.shelfWidth\|\|1000)); this.item.xEndMm =
Math.round((Math.max(this.startPct,this.endPct)/100) \*
(this.shelfWidth\|\|1000)); this.show=false; }

},

template: \`

\<div class=\"relative\"\>

\<button class=\"px-2 py-1 rounded text-white text-xs\" :style=\"{
background: item.color }\" @click=\"show=!show\"\>

{{ item.label }} \<span class=\"opacity-80\"\>({{ localMode }})\</span\>

\</button\>

\<div v-if=\"show\" class=\"absolute z-10 mt-1 w-72 bg-white border
rounded shadow p-3\"\>

\<div class=\"mb-2\"\>

\<label class=\"text-xs text-gray-600\"\>Modo\</label\>

\<select v-model=\"localMode\" class=\"w-full border rounded px-2 py-1
text-sm\"\>

\<option value=\"auto\"\>auto\</option\>

\<option value=\"hybrid\"\>hybrid\</option\>

\<option value=\"template\"\>template (hard)\</option\>

\</select\>

\</div\>

\<div class=\"mb-2\"\>

\<label class=\"text-xs text-gray-600\"\>Faixa horizontal
(0--100%)\</label\>

\<div class=\"flex items-center gap-2 text-xs\"\>

\<input type=\"range\" min=\"0\" max=\"100\" v-model.number=\"startPct\"
class=\"w-full\"\>

\<input type=\"range\" min=\"0\" max=\"100\" v-model.number=\"endPct\"
class=\"w-full\"\>

\</div\>

\<div class=\"text-\[11px\] text-gray-500 mt-1\"\>{{
Math.min(startPct,endPct) }}% → {{ Math.max(startPct,endPct) }}%\</div\>

\</div\>

\<div class=\"flex justify-end gap-2\"\>

\<button class=\"px-2 py-1 text-xs border rounded\"
@click=\"show=false\"\>Fechar\</button\>

\<button class=\"px-2 py-1 text-xs bg-indigo-600 text-white rounded\"
@click=\"applyBand\"\>Aplicar\</button\>

\</div\>

\</div\>

\</div\>

\`

}

}

});

\</script\>

[src/components/PolicyEditor.vue  *(políticas por categoria)*]{.mark}

\<script setup lang=\"ts\"\>

import { reactive, watch } from \'vue\';

import type { AutoParams } from \'@/planogram/types\';

const props = defineProps\<{ value?: AutoParams }\>();

const emit = defineEmits\<{ (e:\'update:value\', val:AutoParams):void
}\>();

const state = reactive\<AutoParams\>({

respeitar_dimensoes: props.value?.respeitar_dimensoes ?? true,

zonas: props.value?.zonas ?? { nobre:\[\], intermediaria:\[\],
rodape:\[\] },

reserva_gap_mm: props.value?.reserva_gap_mm ?? 8,

min_max_frentes: props.value?.min_max_frentes ?? { min:1, max:10 },

mixPolicy: props.value?.mixPolicy ?? { useMixInScoring:true,
weightMixScore:0.30, bonusA:0.05, penaltyC:0.05, penaltyRetirar:0.15,
penaltyInativo:0.20, keepFlaggedWithOneFront:true,
forbidFlaggedInNoble:true },

estoque: props.value?.estoque ?? { nsPorClasse:{A:0.98,B:0.95,C:0.90},
coberturaDiasPorClasse:{A:14,B:10,C:7}, permitirShortfall:true },

antiChurn: props.value?.antiChurn ?? { enabled:true, movePenalty:0.05,
frontPenalty:0.02, minScoreGainToMove:0.03 },

adjacency: props.value?.adjacency ?? { rules:\[\] },

brandTargets: props.value?.brandTargets ?? \[\],

templateMode: props.value?.templateMode ?? \'auto\',

template: props.value?.template,

templatePenalty: props.value?.templatePenalty ?? { breakAnchor:0.04,
breakBlock:0.03 },

equivalents: props.value?.equivalents ?? \[\],

seed: props.value?.seed ?? 1234

});

watch(state, ()=\> emit(\'update:value\',
JSON.parse(JSON.stringify(state))), { deep:true });

\</script\>

\<template\>

\<div class=\"grid grid-cols-12 gap-3\"\>

\<div class=\"col-span-6 border rounded p-3 bg-white\"\>

\<h3 class=\"font-medium mb-2\"\>Mix & ABC\</h3\>

\<label class=\"text-xs\"\>Peso do mix no score (0--1)\</label\>

\<input type=\"range\" min=\"0\" max=\"1\" step=\"0.01\"
v-model.number=\"state.mixPolicy!.weightMixScore\" class=\"w-full\" /\>

\<div class=\"grid grid-cols-2 gap-2 text-sm mt-2\"\>

\<div\>Bonus A \<input class=\"w-full border rounded px-2 py-1\"
type=\"number\" step=\"0.01\" v-model.number=\"state.mixPolicy!.bonusA\"
/\>\</div\>

\<div\>Penalidade C \<input class=\"w-full border rounded px-2 py-1\"
type=\"number\" step=\"0.01\"
v-model.number=\"state.mixPolicy!.penaltyC\" /\>\</div\>

\<div\>Retirar Mix \<input class=\"w-full border rounded px-2 py-1\"
type=\"number\" step=\"0.01\"
v-model.number=\"state.mixPolicy!.penaltyRetirar\" /\>\</div\>

\<div\>Inativo \<input class=\"w-full border rounded px-2 py-1\"
type=\"number\" step=\"0.01\"
v-model.number=\"state.mixPolicy!.penaltyInativo\" /\>\</div\>

\</div\>

\<div class=\"mt-2 text-sm\"\>

\<label\>\<input type=\"checkbox\"
v-model=\"state.mixPolicy!.keepFlaggedWithOneFront\"\> Manter TO_REMOVE
com 1 frente\</label\>\<br\>

\<label\>\<input type=\"checkbox\"
v-model=\"state.mixPolicy!.forbidFlaggedInNoble\"\> Proibir TO_REMOVE na
nobre\</label\>

\</div\>

\</div\>

\<div class=\"col-span-6 border rounded p-3 bg-white\"\>

\<h3 class=\"font-medium mb-2\"\>Anti-churn\</h3\>

\<div class=\"text-sm grid grid-cols-2 gap-2\"\>

\<label\>\<input type=\"checkbox\"
v-model=\"state.antiChurn!.enabled\"\> Ativo\</label\>

\<div\>Move penalty\<input class=\"w-full border rounded px-2 py-1\"
type=\"number\" step=\"0.01\"
v-model.number=\"state.antiChurn!.movePenalty\" /\>\</div\>

\<div\>Front penalty\<input class=\"w-full border rounded px-2 py-1\"
type=\"number\" step=\"0.01\"
v-model.number=\"state.antiChurn!.frontPenalty\" /\>\</div\>

\<div\>Gain mínimo\<input class=\"w-full border rounded px-2 py-1\"
type=\"number\" step=\"0.01\"
v-model.number=\"state.antiChurn!.minScoreGainToMove\" /\>\</div\>

\</div\>

\</div\>

\<div class=\"col-span-6 border rounded p-3 bg-white\"\>

\<h3 class=\"font-medium mb-2\"\>Adjacência\</h3\>

\<AdjacencyEditor v-model=\"state.adjacency!.rules\" /\>

\</div\>

\<div class=\"col-span-6 border rounded p-3 bg-white\"\>

\<h3 class=\"font-medium mb-2\"\>Metas/Tetos por Marca\</h3\>

\<BrandTargetsEditor v-model=\"state.brandTargets\" /\>

\</div\>

\</div\>

\</template\>

\<script lang=\"ts\"\>

import { defineComponent } from \'vue\';

export default defineComponent({

components:{

AdjacencyEditor: {

props:\[\'modelValue\'\],

emits:\[\'update:modelValue\'\],

data(){ return { items: this.modelValue\|\|\[\] }; },

watch:{ items:{ deep:true, handler(v){
this.\$emit(\'update:modelValue\', v); } } },

template: \`

\<div class=\"space-y-2 text-sm\"\>

\<div v-for=\"(r,i) in items\" :key=\"i\" class=\"flex gap-2
items-center\"\>

\<input class=\"border rounded px-1 py-0.5 w-24\" v-model=\"r.brandA\"
placeholder=\"brandA\"\>

\<input class=\"border rounded px-1 py-0.5 w-24\" v-model=\"r.typeA\"
placeholder=\"typeA\"\>

\<span\>×\</span\>

\<input class=\"border rounded px-1 py-0.5 w-24\" v-model=\"r.brandB\"
placeholder=\"brandB\"\>

\<input class=\"border rounded px-1 py-0.5 w-24\" v-model=\"r.typeB\"
placeholder=\"typeB\"\>

\<label class=\"text-xs\"\>\<input type=\"checkbox\"
v-model=\"r.disallow\"\> proibir\</label\>

\<button class=\"text-xs px-2 py-0.5 border rounded\"
@click=\"items.splice(i,1)\"\>remover\</button\>

\</div\>

\<button class=\"text-xs px-2 py-1 border rounded\"
@click=\"items.push({disallow:true})\"\>+ regra\</button\>

\</div\>\`

},

BrandTargetsEditor: {

props:\[\'modelValue\'\],

emits:\[\'update:modelValue\'\],

data(){ return { rows: this.modelValue\|\|\[\] }; },

watch:{ rows:{ deep:true, handler(v){ this.\$emit(\'update:modelValue\',
v); } } },

template: \`

\<div class=\"space-y-2 text-sm\"\>

\<div v-for=\"(r,i) in rows\" :key=\"i\" class=\"grid grid-cols-6 gap-2
items-center\"\>

\<input class=\"border rounded px-2 py-1 col-span-2\"
v-model=\"r.marca\" placeholder=\"Marca\"\>

\<input class=\"border rounded px-2 py-1\" type=\"number\" step=\"0.01\"
v-model.number=\"r.min_total_share\" placeholder=\"min total\"\>

\<input class=\"border rounded px-2 py-1\" type=\"number\" step=\"0.01\"
v-model.number=\"r.max_total_share\" placeholder=\"max total\"\>

\<input class=\"border rounded px-2 py-1\" type=\"number\" step=\"0.01\"
v-model.number=\"r.min_noble_share\" placeholder=\"min nobre\"\>

\<input class=\"border rounded px-2 py-1\" type=\"number\" step=\"0.01\"
v-model.number=\"r.max_noble_share\" placeholder=\"max nobre\"\>

\<button class=\"text-xs px-2 py-0.5 border rounded col-span-6
justify-self-start\" @click=\"rows.splice(i,1)\"\>remover\</button\>

\</div\>

\<button class=\"text-xs px-2 py-1 border rounded\" @click=\"rows.push({
marca:\'\' })\"\>+ marca\</button\>

\</div\>

\`

}

}

});

\</script\>

[src/components/VersionsCompare.vue  *(antes/depois + heatmaps +
flags)*]{.mark}

\<script setup lang=\"ts\"\>

import { computed } from \'vue\';

import type { Gondola, Placement, SKU } from \'@/planogram/types\';

const props = defineProps\<{

gondola: Gondola;

before: Placement\[\];

after: Placement\[\];

skus: SKU\[\];

metric?: \'vendas\'\|\'margem\';

}\>();

const skIndex = computed\<Record\<string, number\>\>(()=\> {

const vals = props.skus.map(s =\> props.metric===\'margem\' ?
(s.vendas?.margem\|\|0) : (s.vendas?.valor\|\|0));

const max = Math.max(1, \...vals);

const idx:Record\<string, number\> = {};

for (const s of props.skus){

const v = props.metric===\'margem\' ? (s.vendas?.margem\|\|0) :
(s.vendas?.valor\|\|0);

idx\[s.id\] = v / max;

}

return idx;

});

function colorFor(v:number){ const t=Math.max(0,Math.min(1,v)); const
r=Math.round(255\*t), g=Math.round(80+100\*(1-t)); return
\`rgba(\${r},\${g},80,0.45)\`; }

function byShelf(placements:Placement\[\]){ const
map:Record\<string,Placement\[\]\>={}; for(const p of placements){ const
k=\`\${p.modulo_id}::\${p.prateleira_id}\`; (map\[k\] \|\|=
\[\]).push(p); } for (const k in map)
map\[k\].sort((a,b)=\>a.x_inicio_mm-b.x_inicio_mm); return map; }

const beforeBy = computed(()=\> byShelf(props.before));

const afterBy = computed(()=\> byShelf(props.after));

function findSku(skus:SKU\[\], id:string){ return
skus.find(s=\>s.id===id); }

\</script\>

\<template\>

\<div class=\"space-y-4\"\>

\<div class=\"flex items-center justify-between\"\>

\<h3 class=\"font-medium\"\>Comparação Antes → Depois (heatmap: {{
metric \|\| \'vendas\' }})\</h3\>

\<div class=\"text-xs text-gray-500\"\>Cores mais quentes = maior {{
metric \|\| \'vendas\' }}\</div\>

\</div\>

\<div v-for=\"m in gondola.modulos\" :key=\"m.id\" class=\"border
rounded p-3 bg-white\"\>

\<div class=\"font-medium mb-2\"\>Módulo {{ m.id }}\</div\>

\<div v-for=\"sh in m.prateleiras\" :key=\"sh.id\" class=\"grid
grid-cols-2 gap-2\"\>

\<div\>

\<div class=\"text-xs text-gray-600\"\>Antes · Prateleira {{ sh.id
}}\</div\>

\<div class=\"relative border rounded p-1 min-h-\[48px\]\"\>

\<div v-for=\"pl in (beforeBy\[\`\${m.id}::\${sh.id}\`\] \|\| \[\])\"
:key=\"pl.sku_id + \'-b\'\" class=\"absolute top-1 bottom-1 rounded
text-\[10px\] flex items-center justify-center\" :style=\"{ left:
pl.x_inicio_mm + \'px\', width: pl.largura_total_mm + \'px\',
background: colorFor(skIndex\[pl.sku_id\]\|\|0) }\"\>

{{ findSku(skus, pl.sku_id)?.marca \|\| pl.sku_id }} ({{ pl.frentes }}f)

\<span v-if=\"pl.flag===\'TO_REMOVE\'\" class=\"ml-1 px-1 rounded
bg-rose-700 text-white\"\>REM\</span\>

\<span v-for=\"pf in (pl.priceFlags\|\|\[\])\" class=\"ml-1 px-1 rounded
bg-amber-600 text-white\"\>P\$\</span\>

\</div\>

\</div\>

\</div\>

\<div\>

\<div class=\"text-xs text-gray-600\"\>Depois · Prateleira {{ sh.id
}}\</div\>

\<div class=\"relative border rounded p-1 min-h-\[48px\]\"\>

\<div v-for=\"pl in (afterBy\[\`\${m.id}::\${sh.id}\`\] \|\| \[\])\"
:key=\"pl.sku_id + \'-a\'\" class=\"absolute top-1 bottom-1 rounded
text-\[10px\] flex items-center justify-center ring-1 ring-black/5\"
:style=\"{ left: pl.x_inicio_mm + \'px\', width: pl.largura_total_mm +
\'px\', background: colorFor(skIndex\[pl.sku_id\]\|\|0) }\"\>

{{ findSku(skus, pl.sku_id)?.marca \|\| pl.sku_id }} ({{ pl.frentes }}f)

\<span v-if=\"pl.flag===\'TO_REMOVE\'\" class=\"ml-1 px-1 rounded
bg-rose-700 text-white\"\>REM\</span\>

\<span v-for=\"pf in (pl.priceFlags\|\|\[\])\" class=\"ml-1 px-1 rounded
bg-amber-600 text-white\"\>P\$\</span\>

\</div\>

\</div\>

\</div\>

\</div\>

\<div class=\"mt-2 text-xs text-gray-700\"\>

\<b\>Observações:\</b\> ver flags "REM" (retirar mix) e "P\$" (preço
duplo).

\</div\>

\</div\>

\</div\>

\</template\>

[src/components/AutoPlanogramRunner.vue  *(painel operacional)*]{.mark}

\<script setup lang=\"ts\"\>

import { ref } from \'vue\';

import { useAutoPlanogram } from \'@/planogram/useAutoPlanogram\';

import type { Gondola, SKU, PlanogramOrchestration,
CategoryPlacementRule, TemplateMode } from \'@/planogram/types\';

import TemplateEditor from \'./TemplateEditor.vue\';

import PolicyEditor from \'./PolicyEditor.vue\';

import VersionsCompare from \'./VersionsCompare.vue\';

const {

loading, params, placements, lastReport, kpis, versions, saveVersion,

runGlobal, runOrchestrated, runForShelf, applyTemplateByCategory

} = useAutoPlanogram();

const gondola = ref\<Gondola\>(); // injete seus dados

const skusDept = ref\<SKU\[\]\>(); // injete seus dados

const daily = ref(); // opcional

const prevPlacements = ref(); // opcional

const orchestration = ref\<PlanogramOrchestration\>({ defaultMode:
\'hybrid\', intents: \[\] });

const before = ref\<any\[\]\>(\[\]);

function onStartCompare(){ before.value =
JSON.parse(JSON.stringify(placements.value)); }

async function onRunAuto(){ onStartCompare(); const r = await
runGlobal(gondola.value!, skusDept.value!, daily.value,
prevPlacements.value); saveVersion(\'auto\'); return r; }

async function onRunOrch(){ onStartCompare(); const r = await
runOrchestrated(gondola.value!, skusDept.value!, orchestration.value,
daily.value, prevPlacements.value); saveVersion(\'orch\'); return r; }

async function onRecalcShelf(shId:string){ onStartCompare(); const r =
await runForShelf(gondola.value!, skusDept.value!, shId, daily.value,
placements.value); saveVersion(\'shelf\'); return r; }

function onApplyTemplateAmaciantes(){
applyTemplateByCategory(\'Limpeza\',\'Amaciantes\', gondola.value!,
\'hybrid\'); }

\</script\>

\<template\>

\<div class=\"space-y-4\"\>

\<div class=\"border rounded p-3 bg-white\"\>

\<div class=\"flex gap-3 items-center\"\>

\<label class=\"text-sm\"\>Modo de Template:\</label\>

\<select v-model=\"params.templateMode\" class=\"px-2 py-1 border
rounded text-sm\"\>

\<option value=\"auto\"\>AUTO\</option\>

\<option value=\"hybrid\"\>HÍBRIDO\</option\>

\<option value=\"template\"\>TEMPLATE (hard)\</option\>

\</select\>

\<button class=\"px-3 py-1.5 rounded bg-indigo-600 text-white\"
@click=\"onApplyTemplateAmaciantes\"\>

Aplicar template Amaciantes

\</button\>

\<button class=\"px-3 py-1.5 rounded bg-emerald-600 text-white\"
@click=\"onRunAuto\" :disabled=\"loading\"\>

Gerar (AUTO)

\</button\>

\<button class=\"px-3 py-1.5 rounded bg-sky-600 text-white\"
@click=\"onRunOrch\" :disabled=\"loading\"\>

Gerar (Orquestrado)

\</button\>

\</div\>

\<div class=\"text-xs text-gray-600 mt-2\"\>

Zonas: nobre {{ params.zonas.nobre.length }}, intermediária {{
params.zonas.intermediaria.length }}, rodapé {{
params.zonas.rodape.length }}.

\</div\>

\</div\>

\<div class=\"grid grid-cols-12 gap-3\"\>

\<div class=\"col-span-7 border rounded p-3 bg-white\"\>

\<h3 class=\"font-medium mb-2\"\>Template (Drag & Drop)\</h3\>

\<TemplateEditor

v-if=\"gondola\"

:gondola=\"gondola!\"

:department=\"skusDept?.\[0\]?.departamento \|\| \'Depto\'\"

:categories=\"\[\...new
Set(skusDept?.map(s=\>s.categoria)\|\|\[\])\].map(c=\>({category:c}))\"

:defaultMode=\"params.templateMode\|\|\'hybrid\'\"

@update:intents=\"orchestration.intents = \$event\"

/\>

\</div\>

\<div class=\"col-span-5 border rounded p-3 bg-white\"\>

\<h3 class=\"font-medium mb-2\"\>Políticas\</h3\>

\<PolicyEditor v-model:value=\"params\" /\>

\<div class=\"mt-3 text-xs text-gray-600\"\>

Seed: \<b\>{{ params.seed }}\</b\> · Gap: \<b\>{{ params.reserva_gap_mm
}}mm\</b\> · Frentes: \<b\>{{ params.min_max_frentes.min }}--{{
params.min_max_frentes.max }}\</b\>

\</div\>

\</div\>

\</div\>

\<div class=\"border rounded p-3 bg-white\"\>

\<h3 class=\"font-medium mb-2\"\>KPIs\</h3\>

\<div v-if=\"kpis\" class=\"text-sm\"\>

Atingiram estoque-alvo: \<b\>{{ kpis.hit }}\</b\> / {{
kpis.totalWithNeed }} ({{ (kpis.hitPct\*100).toFixed(0) }}%)

· Déficit total: \<b\>{{ kpis.totalDeficitUnits }}\</b\> un.

\</div\>

\<div v-else class=\"text-sm text-gray-500\"\>Rode o motor para ver
KPIs.\</div\>

\</div\>

\<div v-if=\"gondola && before.length\" class=\"border rounded p-3
bg-white\"\>

\<VersionsCompare :gondola=\"gondola!\" :before=\"before\"
:after=\"placements\" :skus=\"skusDept\|\|\[\]\" metric=\"vendas\" /\>

\</div\>

\<div class=\"border rounded p-3 bg-white\"\>

\<h3 class=\"font-medium mb-2\"\>Ações rápidas\</h3\>

\<div class=\"flex gap-2\"\>

\<button class=\"px-3 py-1.5 rounded bg-rose-700 text-white\"
@click=\"() =\> onRecalcShelf(prompt(\'ID da
prateleira?\')\|\|\'\')\"\>Recalcular prateleira...\</button\>

\</div\>

\</div\>

\</div\>

\</template\>

**Observações finais para o dev**

- **Onde as macros entram**:

  - mixAnalysis.ts (porta **AnaliseCompletaSortimentoEStatus**) é
    chamada no início do generatePlanogram via applyMixMacroLike(\...).

  - stockTarget.ts (porta **CalcularEstoqueAlvo**) é usada dentro do
    motor para calcular **estoque-alvo** e derivar **frentes
    mínimas** e **shortfalls/KPIs**.

- **Templates**:

  - registry.ts seleciona o template por categoria; amaciantes.ts cria
    blocos (concentrado vs diluído).

- **Orquestração**:

  - orchestrator.ts permite intents
    por **módulo/prateleira/faixa** (modo auto/híbrido/hard).

- **Zonas**:

  - Preencha params.zonas quando carregar a gôndola (IDs das
    prateleiras).

- **Determinismo**:

  - params.seed garante desempates reproduzíveis.

- **Extensões (já preparadas)**:

  - equivalents (substituíveis) em AutoParams --- se quiser trocar um
    SKU largo por estreito em conflitos, use esse array para sugerir
    trocas antes de remover.

  - **Mini-ILP**: você pode plugar um solver leve por prateleira
    quando spillover persistir; o motor está modular (procure marcações
    de "reservas template" e, ao final da prateleira, resolva a alocação
    fina).
