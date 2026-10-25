<script setup lang="ts">
import { computed, ref } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import type { ConversionOptions } from './types'

const props = defineProps<{
    formats: string[]
    initialBasename: string
    allowFilename: boolean
}>()

const emit = defineEmits<{
    // emits close event with user selection
    close: [result?: ConversionOptions]
}>()

// JPEG format by default , or the first that the server supports
const format = ref(props.formats.includes('JPEG') ? 'JPEG' : props.formats[0] ?? '')
const quality = ref(90)
const basename = ref(props.initialBasename)

const extension = computed(() => format.value.toLowerCase())

function handleClosing(result: unknown): void {
    emit('close', result as ConversionOptions | undefined)
}

function confirm(): ConversionOptions {
    return {
        format: format.value,
        quality: Math.max(1, Math.min(100, Number(quality.value) || 90)),
        filename: props.allowFilename ? basename.value.trim() : props.initialBasename,
    }
}
</script>

<template>
    <NcDialog
        name="Convert image"
        :buttons="[
            { label: 'Cancel', callback: () => undefined },
            { label: 'Convert', variant: 'primary', callback: confirm },
        ]"
        @closing="handleClosing">
        <div class="conversion-options">
            <NcSelect
                v-model="format"
                input-label="Output format"
                :options="formats"
                :clearable="false"
                :searchable="false" />

            <div class="quality-control">
                <label for="quality-slider">Quality</label>
                <div class="quality-inputs">
                    <input
                        id="quality-slider"
                        v-model.number="quality"
                        type="range"
                        min="1"
                        max="100"
                        step="1"
                        :aria-valuetext="`${quality}%`" />
                    <input
                        v-model.number="quality"
                        class="quality-number"
                        type="number"
                        min="1"
                        max="100"
                        step="1"
                        aria-label="Quality value" />
                </div>
            </div>

            <div v-if="allowFilename" class="filename-field">
                <NcTextField
                    v-model="basename"
                    label="Filename"
                    :placeholder="initialBasename"
                    :show-trailing-button="false" />
                <span class="filename-extension">.{{ extension }}</span>
            </div>
            <p v-else class="filename-preview">Files will keep their current names with the .{{ extension }} extension.</p>
        </div>
    </NcDialog>
</template>

<style scoped>
.conversion-options {
    display: grid;
    gap: 20px;
    padding: 16px 0;
}

.quality-control {
    display: grid;
    gap: 8px;
}

.quality-inputs {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 88px;
    align-items: center;
    gap: 12px;
}

.quality-inputs input[type='range'] {
    width: 100%;
    margin: 0;
    accent-color: var(--color-primary-element);
}

.quality-number {
    box-sizing: border-box;
    width: 100%;
    min-height: var(--default-clickable-area);
    padding: 6px 8px;
    border: 2px solid var(--color-border-maxcontrast);
    border-radius: var(--border-radius-element);
    background: var(--color-main-background);
    color: var(--color-main-text);
    font: inherit;
}

.quality-number:focus-visible {
    outline: 2px solid var(--color-primary-element);
    outline-offset: 2px;
}

.filename-field {
    display: flex;
    align-items: end;
    gap: 8px;
}

.filename-field :deep(.input-field) {
    flex: 1;
    min-width: 0;
}

.filename-extension {
    padding-bottom: 10px;
    white-space: nowrap;
}

.filename-preview {
    margin: 0;
}
</style>
