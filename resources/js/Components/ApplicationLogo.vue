<script setup>
import logoPng from '../../images/logo.png';
import logoSvg from '../../images/logo.svg';

defineProps({
    variant: {
        type: String,
        default: 'svg',
        validator: (value) => ['svg', 'png'].includes(value),
    },
});
</script>

<template>
    <img
        :src="variant === 'png' ? logoPng : logoSvg"
        alt="Apleqz"
        class="object-contain"
    />
</template>
