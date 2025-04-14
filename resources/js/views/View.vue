<template>
    <router-view :key="$route.fullPath.concat('-view')" />
</template>
<script setup lang="ts"></script>
