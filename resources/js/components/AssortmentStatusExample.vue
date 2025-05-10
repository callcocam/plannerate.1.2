<template>
  <div>
    <h2 class="text-lg font-bold mb-2">Exemplo de Análise de Sortimento</h2>
    <table class="min-w-full border text-xs">
      <thead>
        <tr>
          <th class="border px-2">Categoria</th>
          <th class="border px-2">Nome</th>
          <th class="border px-2">Média Ponderada</th>
          <th class="border px-2">% Individual</th>
          <th class="border px-2">% Acumulada</th>
          <th class="border px-2">Classe ABC</th>
          <th class="border px-2">Ranking</th>
          <th class="border px-2">Retirar?</th>
          <th class="border px-2">Status</th>
          <th class="border px-2">Detalhe do Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="prod in analyzed" :key="prod.id || prod.name"
          :class="{
            'bg-green-400': prod.abcClass === 'A',
            'bg-yellow-300': prod.abcClass === 'B',
            'bg-red-300': prod.abcClass === 'C',
          }"
        >
          <td class="border px-2">{{ prod.category }}</td>
          <td class="border px-2">{{ prod.name || prod.id }}</td>
          <td class="border px-2">{{ prod.weightedAverage }}</td>
          <td class="border px-2">{{ (prod.individualPercent * 100).toFixed(2) }}%</td>
          <td class="border px-2">{{ (prod.accumulatedPercent * 100).toFixed(2) }}%</td>
          <td class="border px-2 font-bold" :class="{
            'text-green-900': prod.abcClass === 'A',
            'text-yellow-900': prod.abcClass === 'B',
            'text-red-900': prod.abcClass === 'C',
          }">{{ prod.abcClass }}</td>
          <td class="border px-2">{{ prod.ranking }}</td>
          <td class="border px-2">{{ prod.removeFromMix ? 'Sim' : 'Não' }}</td>
          <td class="border px-2">{{ prod.status }}</td>
          <td class="border px-2">{{ prod.statusDetail }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { useAssortmentStatus, Product, Weights, Thresholds } from '../composables/useSortimentoStatus';
import axios from 'axios'

const mockProducts: Product[] = [
  {
    id: '7891010793463',
    name: 'ABSORVENTE ADAPT SUAVE C/ABAS SEMPRE LIVRE C/16',
    category: 'ABSORVENTE',
    quantity: 1359,
    value: 12266.909999999963,
    margin: 2396.639999999957,
    lastPurchase: new Date('2025-04-10'),
    lastSale: new Date('2025-04-28'),
    currentStock: 129
  },
  {
    id: '7896007544042',
    name: 'ABSORVENTE TRIP PROT SUAVE C/ABAS INTIMUS C/32',
    category: 'ABSORVENTE',
    quantity: 279,
    value: 5786.499999999984,
    margin: 1945.569999999982,
    lastPurchase: new Date('2024-09-17'),
    lastSale: new Date('2025-04-27'),
    currentStock: 37
  },
  {
    id: '7896007544059',
    name: 'ABSORVENTE TRIP PROT SECA C/ABAS INTIMUS C/32',
    category: 'ABSORVENTE',
    quantity: 258,
    value: 5703.969999999987,
    margin: 1868.2999999999881,
    lastPurchase: new Date('2025-02-27'),
    lastSale: new Date('2025-04-28'),
    currentStock: 22
  },
  {
    id: '7891010254155',
    name: 'ABSORVENTE SUAVE ADAPT C/ABAS SEMPRE LIVRE C/32',
    category: 'ABSORVENTE',
    quantity: 272,
    value: 5174.999999999991,
    margin: 1335.829999999989,
    lastPurchase: new Date('2025-02-24'),
    lastSale: new Date('2025-04-28'),
    currentStock: 239
  },
  {
    id: '7896007545094',
    name: 'ABSORVENTE TRIP PROT SUAVE C/ABAS INTIMUS LV16 PG14',
    category: 'ABSORVENTE',
    quantity: 380,
    value: 4410.199999999992,
    margin: 1416.0599999999913,
    lastPurchase: new Date('2025-02-03'),
    lastSale: new Date('2025-04-28'),
    currentStock: 111
  },
  {
    id: '7896007550883',
    name: 'ABSORVENTE GEL NOTURNO SUAVE INTIMUS C/ABAS C/16',
    category: 'ABSORVENTE',
    quantity: 232,
    value: 4401.67999999999,
    margin: 1306.3899999999926,
    lastPurchase: new Date('2025-02-27'),
    lastSale: new Date('2025-04-14'),
    currentStock: 3
  },
  {
    id: '7896007545100',
    name: 'ABSORVENTE TRIP PROT SECA C/ABAS INTIMUS LV16 PG14',
    category: 'ABSORVENTE',
    quantity: 325,
    value: 3763.749999999992,
    margin: 1275.5699999999945,
    lastPurchase: new Date('2025-02-03'),
    lastSale: new Date('2025-04-28'),
    currentStock: 157
  },
  {
    id: '7891010039783',
    name: 'ABSORVENTE TODO DIA S/PERF S/ABAS CAREFREE C/40',
    category: 'ABSORVENTE',
    quantity: 184,
    value: 3678.1599999999976,
    margin: 1102.3699999999983,
    lastPurchase: new Date('2025-04-10'),
    lastSale: new Date('2025-04-26'),
    currentStock: 4
  },
  {
    id: '7891010604349',
    name: 'ABSORVENTE TODO DIA S/PERF S/ABAS CAREFREE LV80 PG60',
    category: 'ABSORVENTE',
    quantity: 87,
    value: 2612.1300000000006,
    margin: 576.6200000000001,
    lastPurchase: new Date('2025-04-10'),
    lastSale: new Date('2025-04-23'),
    currentStock: 16
  },
  {
    id: '7891010503031',
    name: 'ABSORVENTE ADAPT SUAVE S/ABAS SEMPRE LIVRE C/8',
    category: 'ABSORVENTE',
    quantity: 317,
    value: 2140.8300000000017,
    margin: 731.8100000000019,
    lastPurchase: new Date('2025-02-24'),
    lastSale: new Date('2025-04-28'),
    currentStock: 5
  },
  {
    id: '7891010009618',
    name: 'ABSORVENTE PROT ORIG C/PERF CAREFREE C/40',
    category: 'ABSORVENTE',
    quantity: 112,
    value: 2238.8800000000006,
    margin: 640.4200000000012,
    lastPurchase: new Date('2025-04-10'),
    lastSale: new Date('2025-04-28'),
    currentStock: 19
  },
  {
    id: '7896007540662',
    name: 'ABSORVENTE NOTURNO SUAVE C/ABAS INTIMUS C/8',
    category: 'ABSORVENTE',
    quantity: 160,
    value: 1918.4000000000005,
    margin: 751.4000000000009,
    lastPurchase: new Date('2025-04-17'),
    lastSale: new Date('2025-04-23'),
    currentStock: 24
  },
  {
    id: '7896227650097',
    name: 'ABSORVENTE DIARIO LADY S/ABAS COTTONBABY C/15',
    category: 'ABSORVENTE',
    quantity: 394,
    value: 1572.0600000000022,
    margin: 496.5400000000018,
    lastPurchase: new Date('2025-02-24'),
    lastSale: new Date('2025-04-14'),
    currentStock: 5
  },
  {
    id: '7896227650035',
    name: 'ABSORVENTE SUAVE LADY C/ABAS COTTONBABY C/16',
    category: 'ABSORVENTE',
    quantity: 217,
    value: 1694.830000000001,
    margin: 509.38000000000125,
    lastPurchase: new Date('2025-02-24'),
    lastSale: new Date('2025-04-05'),
    currentStock: 2
  },
  {
    id: '7896007550456',
    name: 'ABSORVENTE DIARIO ANTIBACT S/ABAS INTIMUS C/80',
    category: 'ABSORVENTE',
    quantity: 64,
    value: 1744.3600000000001,
    margin: 498.0200000000003,
    lastPurchase: new Date('2025-04-17'),
    lastSale: new Date('2025-04-28'),
    currentStock: 25
  },
  {
    id: '7896007542871',
    name: 'ABSORVENTE INTERNO SUPER INTIMUS C/16',
    category: 'ABSORVENTE',
    quantity: 53,
    value: 1538.3700000000001,
    margin: 587.0500000000001,
    lastPurchase: new Date('2025-02-03'),
    lastSale: new Date('2025-04-26'),
    currentStock: 3
  },
  {
    id: '7896104993835',
    name: 'ABSORVENTE PROTECAO TOTAL SUAVE C/ABAS MILI C/16',
    category: 'ABSORVENTE',
    quantity: 225,
    value: 1415.2600000000007,
    margin: 424.2800000000004,
    lastPurchase: new Date('2025-01-29'),
    lastSale: new Date('2025-04-27'),
    currentStock: 164
  },
  {
    id: '7896104992777',
    name: 'ABSORVENTE NOTURNO SUAVE C/ABAS MILI C/32',
    category: 'ABSORVENTE',
    quantity: 83,
    value: 1509.1700000000003,
    margin: 394.7900000000007,
    lastPurchase: new Date('2025-04-04'),
    lastSale: new Date('2025-04-28'),
    currentStock: 2
  },
  {
    id: '7896227690406',
    name: 'ABSORVENTE DIARIO LADY S/ABAS COTTONBABY C/80',
    category: 'ABSORVENTE',
    quantity: 97,
    value: 1551.4500000000005,
    margin: 345.84000000000077,
    lastPurchase: new Date('2025-02-24'),
    lastSale: new Date('2025-04-28'),
    currentStock: 7
  },
  {
    id: '7896007542482',
    name: 'ABSORVENTE DIARIO FRESCOR S/PERF S/ABAS INTIMUS C/40',
    category: 'ABSORVENTE',
    quantity: 84,
    value: 1362.1600000000003,
    margin: 475.54000000000065,
    lastPurchase: new Date('2025-02-27'),
    lastSale: new Date('2025-04-27'),
    currentStock: 8
  },
  {
    id: '7896104992494',
    name: 'ABSORVENTE PROTECAO TOTAL SUAVE C/ABAS MILI C/32',
    category: 'ABSORVENTE',
    quantity: 109,
    value: 1310.9100000000003,
    margin: 467.63,
    lastPurchase: new Date('2025-04-04'),
    lastSale: new Date('2025-04-22'),
    currentStock: 21
  },
  {
    id: '7896227690390',
    name: 'ABSORVENTE DIARIO LADY S/ABAS COTTONBABY C/40',
    category: 'ABSORVENTE',
    quantity: 150,
    value: 1326.3600000000008,
    margin: 387.1100000000012,
    lastPurchase: new Date('2025-02-24'),
    lastSale: new Date('2025-04-04'),
    currentStock: 0
  },
  {
    id: '7896227650127',
    name: 'ABSORVENTE NOTURNO SUAVE LADY C/ABAS COTTONBABY C/8',
    category: 'ABSORVENTE',
    quantity: 180,
    value: 1259.7000000000007,
    margin: 410.29000000000076,
    lastPurchase: new Date('2025-03-14'),
    lastSale: new Date('2025-04-23'),
    currentStock: 33
  },
  {
    id: '7896007541867',
    name: 'ABSORVENTE INTERNO MED INTIMUS C/8',
    category: 'ABSORVENTE',
    quantity: 79,
    value: 1184.2100000000005,
    margin: 469.9800000000005,
    lastPurchase: new Date('2024-11-28'),
    lastSale: new Date('2025-04-26'),
    currentStock: 3
  },
  {
    id: '7896007541874',
    name: 'ABSORVENTE INTERNO SUPER INTIMUS C/8',
    category: 'ABSORVENTE',
    quantity: 80,
    value: 1199.2000000000003,
    margin: 456.72000000000025,
    lastPurchase: new Date('2025-03-25'),
    lastSale: new Date('2025-04-27'),
    currentStock: 8
  },
  {
    id: '7891010576509',
    name: 'ABSORVENTE TODO DIA C/PERF S/ABAS CAREFREE LV80 PG60',
    category: 'ABSORVENTE',
    quantity: 46,
    value: 1379.5400000000002,
    margin: 295.1700000000003,
    lastPurchase: new Date('2025-02-24'),
    lastSale: new Date('2025-04-27'),
    currentStock: 5
  },
  {
    id: '7891010694593',
    name: 'ABSORVENTE NOTURNO SECA C/ABAS SEMPRE LIVRE C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 63,
    value: 873.8700000000002,
    margin: 288.8500000000002,
    lastPurchase: new Date('2025-04-10'),
    lastSale: new Date('2025-04-21'),
    currentStock: 18
  },
  {
    id: '7891010245085',
    name: 'ABSORVENTE INTERNO MED PRO COMFORT OB C/16',
    category: 'ABSORVENTE BÁSICO',
    quantity: 41,
    value: 919.5900000000001,
    margin: 253.2000000000001,
    lastPurchase: new Date('2025-04-10'),
    lastSale: new Date('2025-04-17'),
    currentStock: 14
  },
  {
    id: '7896104996485',
    name: 'ABSORVENTE PROTETOR DIARIO MILI C/40',
    category: 'ABSORVENTE BÁSICO',
    quantity: 77,
    value: 854.2300000000002,
    margin: 270.0400000000004,
    lastPurchase: new Date('2025-01-29'),
    lastSale: new Date('2025-04-26'),
    currentStock: 0
  },
  {
    id: '7891010031633',
    name: 'ABSORVENTE ADAPT PLUS SUAVE C/ABAS SEMPRE LIVRE C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 106,
    value: 849.9400000000005,
    margin: 250.4600000000005,
    lastPurchase: new Date('2025-04-10'),
    lastSale: new Date('2025-04-25'),
    currentStock: 10
  },
  {
    id: '7891010035631',
    name: 'ABSORVENTE ADAPT NOITE/DIA SUAVE C/ABAS SEMPRE LIVRE C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 61,
    value: 853.3900000000002,
    margin: 279.1800000000001,
    lastPurchase: new Date('2024-11-23'),
    lastSale: new Date('2025-04-13'),
    currentStock: 10
  },
  {
    id: '7896007542499',
    name: 'ABSORVENTE DIARIO C/PERF S/ABAS INTIMUS LV40 PG30',
    category: 'ABSORVENTE BÁSICO',
    quantity: 51,
    value: 821.4900000000002,
    margin: 294.9100000000002,
    lastPurchase: new Date('2025-01-13'),
    lastSale: new Date('2025-02-24'),
    currentStock: 1
  },
  {
    id: '7896007550807',
    name: 'ABSORVENTE ULTRAFINO GEL DIA/NOITE C/ABAS INTIMUS C/14',
    category: 'ABSORVENTE BÁSICO',
    quantity: 33,
    value: 791.6700000000002,
    margin: 301.73000000000013,
    lastPurchase: new Date('2025-01-13'),
    lastSale: new Date('2025-03-02'),
    currentStock: 9
  },
  {
    id: '7506339394603',
    name: 'ABSORVENTE NOTURNO SUAVE C/ABAS ALWAYS C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 59,
    value: 753.4100000000001,
    margin: 248.71000000000012,
    lastPurchase: new Date('2024-09-16'),
    lastSale: new Date('2025-04-17'),
    currentStock: 14
  },
  {
    id: '7896007541850',
    name: 'ABSORVENTE INTERNO MINI INTIMUS C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 46,
    value: 689.5400000000002,
    margin: 242.04000000000013,
    lastPurchase: new Date('2025-04-17'),
    lastSale: new Date('2025-04-27'),
    currentStock: 17
  },
  {
    id: '7896104993903',
    name: 'ABSORVENTE NOITE/DIA SUAVE C/ABAS MILI C/16',
    category: 'ABSORVENTE BÁSICO',
    quantity: 75,
    value: 674.2500000000002,
    margin: 229.4800000000003,
    lastPurchase: new Date('2025-04-01'),
    lastSale: new Date('2025-04-27'),
    currentStock: 20
  },
  {
    id: '7896227650011',
    name: 'ABSORVENTE SUAVE LADY S/ABAS COTTONBABY C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 143,
    value: 613.6700000000005,
    margin: 212.77000000000064,
    lastPurchase: new Date('2025-03-14'),
    lastSale: new Date('2025-04-26'),
    currentStock: 31
  },
  {
    id: '7896007540624',
    name: 'ABSORVENTE TRIP PROT SECA S/ABAS INTIMUS C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 86,
    value: 651.1400000000002,
    margin: 223.2700000000002,
    lastPurchase: new Date('2025-02-27'),
    lastSale: new Date('2025-04-22'),
    currentStock: 26
  },
  {
    id: '7896104993880',
    name: 'ABSORVENTE NOTURNO SUAVE C/ABAS MILI C/16',
    category: 'ABSORVENTE BÁSICO',
    quantity: 77,
    value: 698.2300000000002,
    margin: 173.6700000000002,
    lastPurchase: new Date('2025-04-04'),
    lastSale: new Date('2025-04-27'),
    currentStock: 9
  },
  {
    id: '7896104993941',
    name: 'ABSORVENTE PROTECAO TOTAL SUAVE C/ABAS MILI C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 135,
    value: 532.1500000000001,
    margin: 197.71999999999997,
    lastPurchase: new Date('2025-03-31'),
    lastSale: new Date('2025-04-19'),
    currentStock: -14
  },
  {
    id: '7896007540617',
    name: 'ABSORVENTE TRIP PROT SUAVE C/ABAS INTIMUS C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 69,
    value: 519.3100000000002,
    margin: 184.02000000000015,
    lastPurchase: new Date('2025-02-03'),
    lastSale: new Date('2025-04-08'),
    currentStock: 12
  },
  {
    id: '7891010886547',
    name: 'ABSORVENTE INTERNO MED ORIG OB C/10',
    category: 'ABSORVENTE BÁSICO',
    quantity: 45,
    value: 586.5500000000002,
    margin: 145.1400000000003,
    lastPurchase: new Date('2025-02-24'),
    lastSale: new Date('2025-04-21'),
    currentStock: 7
  },
  {
    id: '7896007550548',
    name: 'ABSORVENTE DIARIO ANTIBACT S/ABAS INTIMUS C/40',
    category: 'ABSORVENTE BÁSICO',
    quantity: 26,
    value: 467.7400000000001,
    margin: 176.22000000000006,
    lastPurchase: new Date('2024-03-13'),
    lastSale: new Date('2024-07-20'),
    currentStock: 11
  },
  {
    id: '7891010245092',
    name: 'ABSORVENTE INTERNO SUPER PRO COMFORT OB C/16',
    category: 'ABSORVENTE BÁSICO',
    quantity: 23,
    value: 520.7700000000001,
    margin: 132.89000000000004,
    lastPurchase: new Date('2024-09-10'),
    lastSale: new Date('2025-04-17'),
    currentStock: 2
  },
  {
    id: '7896104996492',
    name: 'ABSORVENTE PROTETOR DIARIO MILI C/15',
    category: 'ABSORVENTE BÁSICO',
    quantity: 95,
    value: 426.55000000000007,
    margin: 144.0700000000001,
    lastPurchase: new Date('2025-01-29'),
    lastSale: new Date('2025-04-14'),
    currentStock: 1
  },
  {
    id: '7896104993897',
    name: 'ABSORVENTE NOTURNO C/ABAS MILI C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 67,
    value: 434.83000000000004,
    margin: 149.85000000000008,
    lastPurchase: new Date('2025-01-29'),
    lastSale: new Date('2025-04-26'),
    currentStock: 15
  },
  {
    id: '7896007540600',
    name: 'ABSORVENTE TRIP PROT SUAVE S/ABAS INTIMUS C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 55,
    value: 423.4500000000003,
    margin: 135.8500000000002,
    lastPurchase: new Date('2024-09-11'),
    lastSale: new Date('2025-03-29'),
    currentStock: 27
  },
  {
    id: '7896007548118',
    name: 'ABSORVENTE DIARIO FLEXIVEL S/ABAS INTIMUS C/80',
    category: 'ABSORVENTE BÁSICO',
    quantity: 16,
    value: 434.84,
    margin: 135.1,
    lastPurchase: new Date('2025-04-17'),
    lastSale: new Date('2025-04-15'),
    currentStock: 15
  },
  {
    id: '7891010245597',
    name: 'ABSORVENTE INTERNO MINI OB C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 33,
    value: 432.6700000000001,
    margin: 104.00000000000003,
    lastPurchase: new Date('2025-01-24'),
    lastSale: new Date('2025-04-17'),
    currentStock: 3
  },
  {
    id: '7500435127257',
    name: 'ABSORVENTE SUPER PROT SUAVE C/ABAS ALWAYS C/16',
    category: 'ABSORVENTE BÁSICO',
    quantity: 28,
    value: 351.72,
    margin: 133.30000000000004,
    lastPurchase: new Date('2024-10-25'),
    lastSale: new Date('2025-04-14'),
    currentStock: 18
  },
  {
    id: '7506295338901',
    name: 'ABSORVENTE DIARIO C/PERF ALWAYS C/40',
    category: 'ABSORVENTE BÁSICO',
    quantity: 18,
    value: 359.82000000000005,
    margin: 116.60000000000004,
    lastPurchase: new Date('2024-09-16'),
    lastSale: new Date('2025-03-13'),
    currentStock: 12
  },
  {
    id: '7500435127233',
    name: 'ABSORVENTE SUPER PROT SECA C/ABAS ALWAYS C/16',
    category: 'ABSORVENTE BÁSICO',
    quantity: 28,
    value: 321.72,
    margin: 111.45000000000005,
    lastPurchase: new Date('2024-10-25'),
    lastSale: new Date('2025-04-17'),
    currentStock: 24
  },
  {
    id: '7896007546039',
    name: 'ABSORVENTE DIARIO C/PERF S/ABAS INTIMUS LV80 PG60',
    category: 'ABSORVENTE BÁSICO',
    quantity: 9,
    value: 255.91000000000003,
    margin: 93.62000000000005,
    lastPurchase: new Date('2024-08-29'),
    lastSale: new Date('2024-09-23'),
    currentStock: 10
  },
  {
    id: '7896007541959',
    name: 'ABSORVENTE DIARIO FRESCOR C/PERF S/ABAS INTIMUS C/15',
    category: 'ABSORVENTE BÁSICO',
    quantity: 26,
    value: 233.74,
    margin: 96.25,
    lastPurchase: new Date('2024-11-27'),
    lastSale: new Date('2025-02-20'),
    currentStock: 0
  },
  {
    id: '7891010814441',
    name: 'ABSORVENTE TODO DIA NEUTRALIZE S/PERF S/ABA S CAREFREE C/15',
    category: 'ABSORVENTE BÁSICO',
    quantity: 21,
    value: 251.79000000000002,
    margin: 82.73000000000005,
    lastPurchase: new Date('2025-04-10'),
    lastSale: new Date('2025-04-28'),
    currentStock: 12
  },
  {
    id: '7898172340092',
    name: 'ABSORVENTE POS PARTO SEVEN C/20',
    category: 'ABSORVENTE BÁSICO',
    quantity: 12,
    value: 263.88,
    margin: 78.24000000000001,
    lastPurchase: new Date('2023-12-27'),
    lastSale: new Date('2025-04-13'),
    currentStock: 0
  },
  {
    id: '7501007499758',
    name: 'ABSORVENTE DIARIO C/PERF S/ABAS ALWAYS C/15',
    category: 'ABSORVENTE BÁSICO',
    quantity: 24,
    value: 239.76,
    margin: 72.35,
    lastPurchase: new Date('2025-02-24'),
    lastSale: new Date('2025-04-11'),
    currentStock: 3
  },
  {
    id: '7500435127288',
    name: 'ABSORVENTE SUPER PROT SECA C/ABAS ALWAYS C/32',
    category: 'ABSORVENTE BÁSICO',
    quantity: 11,
    value: 240.89000000000001,
    margin: 60.16000000000004,
    lastPurchase: new Date('2024-09-16'),
    lastSale: new Date('2025-04-17'),
    currentStock: 6
  },
  {
    id: '7896104993927',
    name: 'ABSORVENTE NOITE/DIA SUAVE C/ABAS MILI C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 35,
    value: 192.15000000000003,
    margin: 70.70000000000002,
    lastPurchase: new Date('2024-09-05'),
    lastSale: new Date('2025-04-19'),
    currentStock: 1
  },
  {
    id: '7590002012383',
    name: 'ABSORVENTE NOTURNO ULTRAFINO SECA C/ABAS ALWAYS C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 12,
    value: 191.88,
    margin: 46.18000000000003,
    lastPurchase: new Date('2024-09-16'),
    lastSale: new Date('2025-03-13'),
    currentStock: 0
  },
  {
    id: '7500435126199',
    name: 'ABSORVENTE SUPER PROT SUAVE C/ABAS ALWAYS C/32',
    category: 'ABSORVENTE BÁSICO',
    quantity: 7,
    value: 174.93,
    margin: 56.709999999999994,
    lastPurchase: new Date('2024-09-16'),
    lastSale: new Date('2025-03-11'),
    currentStock: 7
  },
  {
    id: '7500435135771',
    name: 'ABSORVENTE DIARIO S/PERF SENSITIVE S/ABAS ALWAYS C/40',
    category: 'ABSORVENTE BÁSICO',
    quantity: 8,
    value: 175.92000000000002,
    margin: 53.33999999999998,
    lastPurchase: new Date('2025-02-26'),
    lastSale: new Date('2025-02-11'),
    currentStock: 0
  },
  {
    id: '7506339394535',
    name: 'ABSORVENTE NOTURNO SECA C/ABAS ALWAYS C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 12,
    value: 161.88,
    margin: 49.76999999999998,
    lastPurchase: new Date('2024-09-16'),
    lastSale: new Date('2025-04-15'),
    currentStock: 12
  },
  {
    id: '7500435127226',
    name: 'ABSORVENTE SUPER PROT SECA C/ABAS ALWAYS C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 19,
    value: 132.81,
    margin: 49.06000000000002,
    lastPurchase: new Date('2024-09-16'),
    lastSale: new Date('2025-04-14'),
    currentStock: 9
  },
  {
    id: '7500435127240',
    name: 'ABSORVENTE SUPER PROT SUAVE C/ABAS ALWAYS C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 19,
    value: 132.81,
    margin: 39.550000000000004,
    lastPurchase: new Date('2024-09-16'),
    lastSale: new Date('2025-03-10'),
    currentStock: 8
  },
  {
    id: '7500435125840',
    name: 'ABSORVENTE DIARIO RESPIRAVEL S/ABAS ALWAYS C/40',
    category: 'ABSORVENTE BÁSICO',
    quantity: 6,
    value: 131.94,
    margin: 47.30999999999999,
    lastPurchase: new Date('2024-08-08'),
    lastSale: new Date('2025-02-27'),
    currentStock: 5
  },
  {
    id: '7896227650714',
    name: 'ABSORVENTE P/SEIOS MAMAE COTTONBABY C/12',
    category: 'ABSORVENTE BÁSICO',
    quantity: 10,
    value: 129.9,
    margin: 44.8,
    lastPurchase: new Date('2023-08-15'),
    lastSale: new Date('2025-04-26'),
    currentStock: 4
  },
  {
    id: '7500435127271',
    name: 'ABSORVENTE SUPER PROT SUAVE S/ABAS ALWAYS C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 13,
    value: 90.87,
    margin: 24.550000000000015,
    lastPurchase: new Date('2024-09-16'),
    lastSale: new Date('2025-03-11'),
    currentStock: 2
  },
  {
    id: '7500435127264',
    name: 'ABSORVENTE SUPER PROT SECA S/ABAS ALWAYS C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 12,
    value: 83.88000000000001,
    margin: 22.260000000000005,
    lastPurchase: new Date('2024-09-16'),
    lastSale: new Date('2025-03-15'),
    currentStock: 7
  },
  {
    id: '7896104993934',
    name: 'ABSORVENTE PROTECAO TOTAL S/ABAS MILI C/8',
    category: 'ABSORVENTE BÁSICO',
    quantity: 4,
    value: 15.96,
    margin: 6.120000000000001,
    lastPurchase: new Date('2024-08-19'),
    lastSale: new Date('2024-09-25'),
    currentStock: 26
  },
  {
    id: '7891010087807',
    name: 'ABSORVENTE TODO DIA S/PERF S/ABAS CAREFREE C/15',
    category: 'ABSORVENTE NOTURNO',
    quantity: 97,
    value: 1163.0300000000007,
    margin: 414.47,
    lastPurchase: new Date('2025-04-10'),
    lastSale: new Date('2025-04-27'),
    currentStock: 8
  },
  {
    id: '7896227650158',
    name: 'ABSORVENTE SUAVE LADY C/ABAS COTTONBABY C/8',
    category: 'ABSORVENTE NOTURNO',
    quantity: 244,
    value: 1047.3599999999992,
    margin: 355.4899999999988,
    lastPurchase: new Date('2025-03-14'),
    lastSale: new Date('2025-04-25'),
    currentStock: 40
  },
  {
    id: '7896227690697',
    name: 'ABSORVENTE SUAVE LADY C/ABAS COTTONBABY C/32',
    category: 'ABSORVENTE NOTURNO',
    quantity: 77,
    value: 1154.2300000000002,
    margin: 370.8599999999999,
    lastPurchase: new Date('2025-03-14'),
    lastSale: new Date('2025-04-19'),
    currentStock: 34
  },
  {
    id: '7896007540259',
    name: 'ABSORVENTE PROTETOR DIARIO S/PERF S/ABAS INTIMUS C/15',
    category: 'ABSORVENTE NOTURNO',
    quantity: 113,
    value: 1015.8700000000007,
    margin: 374.57000000000005,
    lastPurchase: new Date('2024-09-11'),
    lastSale: new Date('2025-04-25'),
    currentStock: 17
  },
  {
    id: '7506339326031',
    name: 'ABSORVENTE NOTURNO SUAVE ALWAYS C/16',
    category: 'ABSORVENTE NOTURNO',
    quantity: 40,
    value: 999.6000000000001,
    margin: 424.18000000000006,
    lastPurchase: new Date('2025-04-02'),
    lastSale: new Date('2025-04-11'),
    currentStock: 9
  },
  {
    id: '7891010087722',
    name: 'ABSORVENTE PROT ORIG C/PERF CAREFREE C/15',
    category: 'ABSORVENTE NOTURNO',
    quantity: 87,
    value: 1043.1300000000006,
    margin: 351.8400000000002,
    lastPurchase: new Date('2025-04-10'),
    lastSale: new Date('2025-04-28'),
    currentStock: 24
  },
  {
    id: '7896007546022',
    name: 'ABSORVENTE CUIDADO DIARIO C/PERF S/ABAS INTIMUS C/80',
    category: 'ABSORVENTE NOTURNO',
    quantity: 40,
    value: 1098.6000000000001,
    margin: 339.49000000000007,
    lastPurchase: new Date('2024-09-11'),
    lastSale: new Date('2025-04-14'),
    currentStock: 7
  },
  {
    id: '7506309805498',
    name: 'ABSORVENTE NOTURNO SECA C/ABAS HIP LONG ALWAYS C/10',
    category: 'ABSORVENTE NOTURNO',
    quantity: 31,
    value: 1239.69,
    margin: 207.2200000000002,
    lastPurchase: new Date('2025-04-02'),
    lastSale: new Date('2025-04-12'),
    currentStock: 13
  },
  {
    id: '7896007540631',
    name: 'ABSORVENTE TRIP PROT SECA C/ABAS INTIMUS C/8 SP',
    category: 'ABSORVENTE NOTURNO',
    quantity: 122,
    value: 951.7800000000008,
    margin: 349.23000000000104,
    lastPurchase: new Date('2024-10-10'),
    lastSale: new Date('2025-04-05'),
    currentStock: 17
  },
  {
    id: '7896007551019',
    name: 'ABSORVENTE ULTRAFINO GEL ANTIBACT C/ABAS INTIMUS C/14',
    category: 'ABSORVENTE NOTURNO',
    quantity: 65,
    value: 911.3500000000003,
    margin: 338.35000000000025,
    lastPurchase: new Date('2025-04-17'),
    lastSale: new Date('2025-04-19'),
    currentStock: 9
  },
  {
    id: '7506339325249',
    name: 'ABSORVENTE NOTURNO SECA C/ABAS G ALWAYS C/16',
    category: 'ABSORVENTE NOTURNO',
    quantity: 36,
    value: 899.6400000000001,
    margin: 324.5200000000002,
    lastPurchase: new Date('2024-09-11'),
    lastSale: new Date('2025-04-07'),
    currentStock: 13
  },
  {
    id: '7896007550463',
    name: 'ABSORVENTE DIARIO ANTIBACT S/ABAS INTIMUS C/15',
    category: 'ABSORVENTE NOTURNO',
    quantity: 92,
    value: 827.0800000000005,
    margin: 328.89000000000055,
    lastPurchase: new Date('2025-04-17'),
    lastSale: new Date('2025-04-25'),
    currentStock: 16
  },
];

const weights: Weights = {
  quantity: 0.30,
  value: 0.30,
  margin: 0.40,
};

const thresholds: Thresholds = {
  a: 0.8,
  b: 0.85,
};

const analyzed = useAssortmentStatus(mockProducts, weights, thresholds);


const fetchDadosXlsx = async () => { 
    try {
        const response = await axios.get('/api/dados-xlsx')
        const dados = response.data

        // Converter os dados para o formato esperado
       console.log(dados)

        // Atualizar os dados no composable  
    } catch (error) {
        console.error('Erro ao buscar dados:', error)
    }
}

onMounted(() => {
    fetchDadosXlsx()
})
</script>

<!--
7891010793463	ABSORVENTE ADAPT SUAVE C/ABAS SEMPRE LIVRE C/16	ABSORVENTE	1359	 R$ 12.266,91 	 R$ 2.396,64 	10/04/2025	28/04/2025	129
7896007544042	ABSORVENTE TRIP PROT SUAVE C/ABAS INTIMUS C/32	ABSORVENTE	279	 R$ 5.786,50 	 R$ 1.945,57 	17/09/2024	27/04/2025	37
7896007544059	ABSORVENTE TRIP PROT SECA C/ABAS INTIMUS C/32	ABSORVENTE	258	 R$ 5.703,97 	 R$ 1.868,30 	27/02/2025	28/04/2025	22
7891010254155	ABSORVENTE SUAVE ADAPT C/ABAS SEMPRE LIVRE C/32	ABSORVENTE	272	 R$ 5.175,00 	 R$ 1.335,83 	24/02/2025	28/04/2025	239
7896007545094	ABSORVENTE TRIP PROT SUAVE C/ABAS INTIMUS LV16 PG14	ABSORVENTE	380	 R$ 4.410,20 	 R$ 1.416,06 	03/02/2025	28/04/2025	111
7896007550883	ABSORVENTE GEL NOTURNO SUAVE INTIMUS C/ABAS C/16	ABSORVENTE	232	 R$ 4.401,68 	 R$ 1.306,39 	27/02/2025	14/04/2025	3
7896007545100	ABSORVENTE TRIP PROT SECA C/ABAS INTIMUS LV16 PG14	ABSORVENTE	325	 R$ 3.763,75 	 R$ 1.275,57 	03/02/2025	28/04/2025	157
7891010039783	ABSORVENTE TODO DIA S/PERF S/ABAS CAREFREE C/40	ABSORVENTE	184	 R$ 3.678,16 	 R$ 1.102,37 	10/04/2025	26/04/2025	4
7891010604349	ABSORVENTE TODO DIA S/PERF S/ABAS CAREFREE LV80 PG60	ABSORVENTE	87	 R$ 2.612,13 	 R$ 576,62 	10/04/2025	23/04/2025	16
7891010503031	ABSORVENTE ADAPT SUAVE S/ABAS SEMPRE LIVRE C/8	ABSORVENTE	317	 R$ 2.140,83 	 R$ 731,81 	24/02/2025	28/04/2025	5
7891010009618	ABSORVENTE PROT ORIG C/PERF CAREFREE C/40	ABSORVENTE	112	 R$ 2.238,88 	 R$ 640,42 	10/04/2025	28/04/2025	19
7896007540662	ABSORVENTE NOTURNO SUAVE C/ABAS INTIMUS C/8	ABSORVENTE	160	 R$ 1.918,40 	 R$ 751,40 	17/04/2025	23/04/2025	24
7896227650097	ABSORVENTE DIARIO LADY S/ABAS COTTONBABY C/15	ABSORVENTE	394	 R$ 1.572,06 	 R$ 496,54 	24/02/2025	14/04/2025	5
7896227650035	ABSORVENTE SUAVE LADY C/ABAS COTTONBABY C/16	ABSORVENTE	217	 R$ 1.694,83 	 R$ 509,38 	24/02/2025	05/04/2025	2
7896007550456	ABSORVENTE DIARIO ANTIBACT S/ABAS INTIMUS C/80	ABSORVENTE	64	 R$ 1.744,36 	 R$ 498,02 	17/04/2025	28/04/2025	25
7896007542871	ABSORVENTE INTERNO SUPER INTIMUS C/16	ABSORVENTE	53	 R$ 1.538,37 	 R$ 587,05 	03/02/2025	26/04/2025	3
7896104993835	ABSORVENTE PROTECAO TOTAL SUAVE C/ABAS MILI C/16	ABSORVENTE	225	 R$ 1.415,26 	 R$ 424,28 	29/01/2025	27/04/2025	164
7896104992777	ABSORVENTE NOTURNO SUAVE C/ABAS MILI C/32	ABSORVENTE	83	 R$ 1.509,17 	 R$ 394,79 	04/04/2025	28/04/2025	2
7896227690406	ABSORVENTE DIARIO LADY S/ABAS COTTONBABY C/80	ABSORVENTE	97	 R$ 1.551,45 	 R$ 345,84 	24/02/2025	28/04/2025	7
7896007542482	ABSORVENTE DIARIO FRESCOR S/PERF S/ABAS INTIMUS C/40	ABSORVENTE	84	 R$ 1.362,16 	 R$ 475,54 	27/02/2025	27/04/2025	8
7896104992494	ABSORVENTE PROTECAO TOTAL SUAVE C/ABAS MILI C/32	ABSORVENTE	109	 R$ 1.310,91 	 R$ 467,63 	04/04/2025	22/04/2025	21
7896227690390	ABSORVENTE DIARIO LADY S/ABAS COTTONBABY C/40	ABSORVENTE	150	 R$ 1.326,36 	 R$ 387,11 	24/02/2025	04/04/2025	0
7896227650127	ABSORVENTE NOTURNO SUAVE LADY C/ABAS COTTONBABY C/8	ABSORVENTE	180	 R$ 1.259,70 	 R$ 410,29 	14/03/2025	23/04/2025	33
7896007541867	ABSORVENTE INTERNO MED INTIMUS C/8	ABSORVENTE	79	 R$ 1.184,21 	 R$ 469,98 	28/11/2024	26/04/2025	3
7896007541874	ABSORVENTE INTERNO SUPER INTIMUS C/8	ABSORVENTE	80	 R$ 1.199,20 	 R$ 456,72 	25/03/2025	27/04/2025	8
7891010576509	ABSORVENTE TODO DIA C/PERF S/ABAS CAREFREE LV80 PG60	ABSORVENTE	46	 R$ 1.379,54 	 R$ 295,17 	24/02/2025	27/04/2025	5
7891010087807	ABSORVENTE TODO DIA S/PERF S/ABAS CAREFREE C/15	ABSORVENTE NOTURNO	97	 R$ 1.163,03 	 R$ 414,47 	10/04/2025	27/04/2025	8
7896227650158	ABSORVENTE SUAVE LADY C/ABAS COTTONBABY C/8	ABSORVENTE NOTURNO	244	 R$ 1.047,36 	 R$ 355,49 	14/03/2025	25/04/2025	40
7896227690697	ABSORVENTE SUAVE LADY C/ABAS COTTONBABY C/32	ABSORVENTE NOTURNO	77	 R$ 1.154,23 	 R$ 370,86 	14/03/2025	19/04/2025	34
7896007540259	ABSORVENTE PROTETOR DIARIO S/PERF S/ABAS INTIMUS C/15	ABSORVENTE NOTURNO	113	 R$ 1.015,87 	 R$ 374,57 	11/09/2024	25/04/2025	17
7506339326031	ABSORVENTE NOTURNO SUAVE ALWAYS C/16	ABSORVENTE NOTURNO	40	 R$ 999,60 	 R$ 424,18 	02/04/2025	11/04/2025	9
7891010087722	ABSORVENTE PROT ORIG C/PERF CAREFREE C/15	ABSORVENTE NOTURNO	87	 R$ 1.043,13 	 R$ 351,84 	10/04/2025	28/04/2025	24
7896007546022	ABSORVENTE CUIDADO DIARIO C/PERF S/ABAS INTIMUS C/80	ABSORVENTE NOTURNO	40	 R$ 1.098,60 	 R$ 339,49 	11/09/2024	14/04/2025	7
7506309805498	ABSORVENTE NOTURNO SECA C/ABAS HIP LONG ALWAYS C/10	ABSORVENTE NOTURNO	31	 R$ 1.239,69 	 R$ 207,22 	02/04/2025	12/04/2025	13
7896007540631	ABSORVENTE TRIP PROT SECA C/ABAS INTIMUS C/8 SP	ABSORVENTE NOTURNO	122	 R$ 951,78 	 R$ 349,23 	10/10/2024	05/04/2025	17
7896007551019	ABSORVENTE ULTRAFINO GEL ANTIBACT C/ABAS INTIMUS C/14	ABSORVENTE NOTURNO	65	 R$ 911,35 	 R$ 338,35 	17/04/2025	19/04/2025	9
7506339325249	ABSORVENTE NOTURNO SECA C/ABAS G ALWAYS C/16	ABSORVENTE NOTURNO	36	 R$ 899,64 	 R$ 324,52 	11/09/2024	07/04/2025	13
7896007550463	ABSORVENTE DIARIO ANTIBACT S/ABAS INTIMUS C/15	ABSORVENTE NOTURNO	92	 R$ 827,08 	 R$ 328,89 	17/04/2025	25/04/2025	16
7891010694593	ABSORVENTE NOTURNO SECA C/ABAS SEMPRE LIVRE C/8	ABSORVENTE BASICO	63	 R$ 873,87 	 R$ 288,85 	10/04/2025	21/04/2025	18
7891010245085	ABSORVENTE INTERNO MED PRO COMFORT OB C/16	ABSORVENTE BASICO	41	 R$ 919,59 	 R$ 253,20 	10/04/2025	17/04/2025	14
7896104996485	ABSORVENTE PROTETOR DIARIO MILI C/40	ABSORVENTE BASICO	77	 R$ 854,23 	 R$ 270,04 	29/01/2025	26/04/2025	0
7891010031633	ABSORVENTE ADAPT PLUS SUAVE C/ABAS SEMPRE LIVRE C/8	ABSORVENTE BASICO	106	 R$ 849,94 	 R$ 250,46 	10/04/2025	25/04/2025	10
7891010035631	ABSORVENTE ADAPT NOITE/DIA SUAVE C/ABAS SEMPRE LIVRE C/8	ABSORVENTE BASICO	61	 R$ 853,39 	 R$ 279,18 	23/11/2024	13/04/2025	10
7896007542499	ABSORVENTE DIARIO C/PERF S/ABAS INTIMUS LV40 PG30	ABSORVENTE BASICO	51	 R$ 821,49 	 R$ 294,91 	13/01/2025	24/02/2025	1
7896007550807	ABSORVENTE ULTRAFINO GEL DIA/NOITE C/ABAS INTIMUS C/14	ABSORVENTE BASICO	33	 R$ 791,67 	 R$ 301,73 	13/01/2025	02/03/2025	9
7506339394603	ABSORVENTE NOTURNO SUAVE C/ABAS ALWAYS C/8	ABSORVENTE BASICO	59	 R$ 753,41 	 R$ 248,71 	16/09/2024	17/04/2025	14
7896007541850	ABSORVENTE INTERNO MINI INTIMUS C/8	ABSORVENTE BASICO	46	 R$ 689,54 	 R$ 242,04 	17/04/2025	27/04/2025	17
7896104993903	ABSORVENTE NOITE/DIA SUAVE C/ABAS MILI C/16	ABSORVENTE BASICO	75	 R$ 674,25 	 R$ 229,48 	01/04/2025	27/04/2025	20
7896227650011	ABSORVENTE SUAVE LADY S/ABAS COTTONBABY C/8	ABSORVENTE BASICO	143	 R$ 613,67 	 R$ 212,77 	14/03/2025	26/04/2025	31
7896007540624	ABSORVENTE TRIP PROT SECA S/ABAS INTIMUS C/8	ABSORVENTE BASICO	86	 R$ 651,14 	 R$ 223,27 	27/02/2025	22/04/2025	26
7896104993880	ABSORVENTE NOTURNO SUAVE C/ABAS MILI C/16	ABSORVENTE BASICO	77	 R$ 698,23 	 R$ 173,67 	04/04/2025	27/04/2025	9
7896104993941	ABSORVENTE PROTECAO TOTAL SUAVE C/ABAS MILI C/8	ABSORVENTE BASICO	135	 R$ 532,15 	 R$ 197,72 	31/03/2025	19/04/2025	-14
7896007540617	ABSORVENTE TRIP PROT SUAVE C/ABAS INTIMUS C/8	ABSORVENTE BASICO	69	 R$ 519,31 	 R$ 184,02 	03/02/2025	08/04/2025	12
7891010886547	ABSORVENTE INTERNO MED ORIG OB C/10	ABSORVENTE BASICO	45	 R$ 586,55 	 R$ 145,14 	24/02/2025	21/04/2025	7
7896007550548	ABSORVENTE DIARIO ANTIBACT S/ABAS INTIMUS C/40	ABSORVENTE BASICO	26	 R$ 467,74 	 R$ 176,22 	13/03/2024	20/07/2024	11
7891010245092	ABSORVENTE INTERNO SUPER PRO COMFORT OB C/16	ABSORVENTE BASICO	23	 R$ 520,77 	 R$ 132,89 	10/09/2024	17/04/2025	2
7896104996492	ABSORVENTE PROTETOR DIARIO MILI C/15	ABSORVENTE BASICO	95	 R$ 426,55 	 R$ 144,07 	29/01/2025	14/04/2025	1
7896104993897	ABSORVENTE NOTURNO C/ABAS MILI C/8	ABSORVENTE BASICO	67	 R$ 434,83 	 R$ 149,85 	29/01/2025	26/04/2025	15
7896007540600	ABSORVENTE TRIP PROT SUAVE S/ABAS INTIMUS C/8	ABSORVENTE BASICO	55	 R$ 423,45 	 R$ 135,85 	11/09/2024	29/03/2025	27
7896007548118	ABSORVENTE DIARIO FLEXIVEL S/ABAS INTIMUS C/80	ABSORVENTE BASICO	16	 R$ 434,84 	 R$ 135,10 	17/04/2025	15/04/2025	15
7891010245597	ABSORVENTE INTERNO MINI OB C/8	ABSORVENTE BASICO	33	 R$ 432,67 	 R$ 104,00 	24/01/2025	17/04/2025	3
7500435127257	ABSORVENTE SUPER PROT SUAVE C/ABAS ALWAYS C/16	ABSORVENTE BASICO	28	 R$ 351,72 	 R$ 133,30 	25/10/2024	14/04/2025	18
7506295338901	ABSORVENTE DIARIO C/PERF ALWAYS C/40	ABSORVENTE BASICO	18	 R$ 359,82 	 R$ 116,60 	16/09/2024	13/03/2025	12
7500435127233	ABSORVENTE SUPER PROT SECA C/ABAS ALWAYS C/16	ABSORVENTE BASICO	28	 R$ 321,72 	 R$ 111,45 	25/10/2024	17/04/2025	24
7896007546039	ABSORVENTE DIARIO C/PERF S/ABAS INTIMUS LV80 PG60	ABSORVENTE BASICO	9	 R$ 255,91 	 R$ 93,62 	29/08/2024	23/09/2024	10
7896007541959	ABSORVENTE DIARIO FRESCOR C/PERF S/ABAS INTIMUS C/15	ABSORVENTE BASICO	26	 R$ 233,74 	 R$ 96,25 	27/11/2024	20/02/2025	0
7891010814441	ABSORVENTE TODO DIA NEUTRALIZE S/PERF S/ABA S CAREFREE C/15	ABSORVENTE BASICO	21	 R$ 251,79 	 R$ 82,73 	10/04/2025	28/04/2025	12
7898172340092	ABSORVENTE POS PARTO SEVEN C/20	ABSORVENTE BASICO	12	 R$ 263,88 	 R$ 78,24 	27/12/2023	13/04/2025	0
7501007499758	ABSORVENTE DIARIO C/PERF S/ABAS ALWAYS C/15	ABSORVENTE BASICO	24	 R$ 239,76 	 R$ 72,35 	24/02/2025	11/04/2025	3
7500435127288	ABSORVENTE SUPER PROT SECA C/ABAS ALWAYS C/32	ABSORVENTE BASICO	11	 R$ 240,89 	 R$ 60,16 	16/09/2024	17/04/2025	6
7896104993927	ABSORVENTE NOITE/DIA SUAVE C/ABAS MILI C/8	ABSORVENTE BASICO	35	 R$ 192,15 	 R$ 70,70 	05/09/2024	19/04/2025	1
7590002012383	ABSORVENTE NOTURNO ULTRAFINO SECA C/ABAS ALWAYS C/8	ABSORVENTE BASICO	12	 R$ 191,88 	 R$ 46,18 	16/09/2024	13/03/2025	0
7500435126199	ABSORVENTE SUPER PROT SUAVE C/ABAS ALWAYS C/32	ABSORVENTE BASICO	7	 R$ 174,93 	 R$ 56,71 	16/09/2024	11/03/2025	7
7500435135771	ABSORVENTE DIARIO S/PERF SENSITIVE S/ABAS ALWAYS C/40	ABSORVENTE BASICO	8	 R$ 175,92 	 R$ 53,34 	26/02/2025	11/02/2025	0
7506339394535	ABSORVENTE NOTURNO SECA C/ABAS ALWAYS C/8	ABSORVENTE BASICO	12	 R$ 161,88 	 R$ 49,77 	16/09/2024	15/04/2025	12
7500435127226	ABSORVENTE SUPER PROT SECA C/ABAS ALWAYS C/8	ABSORVENTE BASICO	19	 R$ 132,81 	 R$ 49,06 	16/09/2024	14/04/2025	9
7500435127240	ABSORVENTE SUPER PROT SUAVE C/ABAS ALWAYS C/8	ABSORVENTE BASICO	19	 R$ 132,81 	 R$ 39,55 	16/09/2024	10/03/2025	8
7500435125840	ABSORVENTE DIARIO RESPIRAVEL S/ABAS ALWAYS C/40	ABSORVENTE BASICO	6	 R$ 131,94 	 R$ 47,31 	08/08/2024	27/02/2025	5
7896227650714	ABSORVENTE P/SEIOS MAMAE COTTONBABY C/12	ABSORVENTE BASICO	10	 R$ 129,90 	 R$ 44,80 	15/08/2023	26/04/2025	4
7500435127271	ABSORVENTE SUPER PROT SUAVE S/ABAS ALWAYS C/8	ABSORVENTE BASICO	13	 R$ 90,87 	 R$ 24,55 	16/09/2024	11/03/2025	2
7500435127264	ABSORVENTE SUPER PROT SECA S/ABAS ALWAYS C/8	ABSORVENTE BASICO	12	 R$ 83,88 	 R$ 22,26 	16/09/2024	15/03/2025	7
7896104993934	ABSORVENTE PROTECAO TOTAL S/ABAS MILI C/8	ABSORVENTE BASICO	4	 R$ 15,96 	 R$ 6,12 	19/08/2024	25/09/2024	26
-->
