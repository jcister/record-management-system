<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { ZoomIn, ZoomOut } from 'lucide-vue-next';

interface Props {
    data: {
        letter_date: string;
        recipient_name: string;
        recipient_position: string;
        recipient_designation: string;
        recipient_address: string;
    };
    summary: {
        scheduled_at: string | null;
    };
    director: {
        name: string;
        designation: string;
    };
}

const props = defineProps<Props>();

const headerImageSrc = '/images/letters/endorsement-letter-to-coa-header.png';

const zoom = ref(90);

const zoomIn = () => {
    if (zoom.value < 150) {
        zoom.value += 10;
    }
};

const zoomOut = () => {
    if (zoom.value > 50) {
        zoom.value -= 10;
    }
};

const resetZoom = () => {
    zoom.value = 90;
};

const recipientNameParts = computed(() => {
    const fullName = (props.data.recipient_name ?? '').trim();
    if (fullName === '') {
        return { base: '', extension: '', lastName: '' };
    }

    const commaIndex = fullName.indexOf(',');
    const base = commaIndex === -1 ? fullName : fullName.slice(0, commaIndex);
    const extension = commaIndex === -1 ? '' : fullName.slice(commaIndex + 1);

    const parts = base.trim().split(/\s+/).filter((segment) => segment !== '');

    return {
        base: base.trim(),
        extension: extension.trim(),
        lastName: parts[parts.length - 1] ?? '',
    };
});

const academicYear = computed(() => {
    if (!props.summary.scheduled_at) {
        return new Date().getFullYear().toString();
    }

    const parsed = new Date(props.summary.scheduled_at);
    if (Number.isNaN(parsed.getTime())) {
        return new Date().getFullYear().toString();
    }

    return parsed.getFullYear().toString();
});
</script>

<template>
    <div class="flex h-full flex-col bg-gray-600">
        <div class="flex shrink-0 items-center justify-between border-b bg-card px-4 py-2">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium">Live Preview</span>
                <span class="text-xs text-muted-foreground">{{ zoom }}%</span>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="icon" :disabled="zoom <= 50" @click="zoomOut">
                    <ZoomOut class="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="sm" @click="resetZoom">Reset</Button>
                <Button variant="ghost" size="icon" :disabled="zoom >= 150" @click="zoomIn">
                    <ZoomIn class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <div class="flex-1 overflow-auto p-2 scroll-smooth">
            <div
                class="mx-auto bg-white shadow-2xl"
                :style="{
                    transform: `scale(${zoom / 100})`,
                    transformOrigin: 'top center',
                    width: '210mm',
                    minHeight: '297mm',
                    padding: '0 1in 1in 1in',
                    marginBottom: '2rem',
                    boxSizing: 'border-box',
                }"
            >
                <div class="pdf-content text-[11pt] text-black" style="font-family: Calibri, Arial, sans-serif; line-height: 1.2;">
                    <div class="letter-header">
                        <img :src="headerImageSrc" alt="Endorsement Letter to COA header" class="header-image" />
                    </div>
                    <div class="date-container">{{ data.letter_date || 'Letter Date' }}</div>

                    <div class="recipient-info">
                        <p class="recipient-name">
                            <span class="recipient-name__base">{{ recipientNameParts.base || 'Name' }}</span>
                            <span v-if="recipientNameParts.extension" class="recipient-name__extension">, {{ recipientNameParts.extension }}</span>
                        </p>
                        <p>{{ data.recipient_position || 'Position' }}</p>
                        <p>{{ data.recipient_designation || 'Designation' }}</p>
                        <p>COA XI</p>
                        <p v-if="data.recipient_address">{{ data.recipient_address }}</p>
                    </div>

                    <div class="salutation">
                        Sir/ Ma'am {{ recipientNameParts.lastName !== '' ? ` ${recipientNameParts.lastName}` : '' }}:
                    </div>

                    <div class="body-text">Greetings from CHED ROXI!</div>

                    <div class="body-text">
                        This is to submit the copy of the following documents as evidence for the Disposal CHED XI Record for AY {{ academicYear }}:
                        Attached herewith are the following:
                    </div>

                    <div class="list-container">
                        <p>1. Pictures- Documentation</p>
                        <p>2. Form 1 - Signatory</p>
                        <p>3. Form 4 - NAP</p>
                        <p>4. Form 5 - NAP</p>
                        <p>5. Form 6 - NAP</p>
                    </div>

                    <div class="body-text reference-line">For your reference</div>

                    <div class="clearfix">
                        <div class="closing-section">
                            <p>Very truly yours,</p>
                            <div class="signature-gap"></div>
                            <p class="director-name">{{ director.name || 'MARICAR R. CASQUEJO, Ph.D., CESO III' }}</p>
                            <p>{{ director.designation || 'Director IV' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.pdf-content p {
    margin: 0;
    padding: 0;
}

.date-container {
    text-align: right;
    margin-top: 16px;
    margin-bottom: 40px;
}

.letter-header {
    border-bottom: 1px solid #9ca3af;
    padding-bottom: 8px;
}

.header-image {
    width: 100%;
    height: auto;
    display: block;
}

.recipient-info {
    margin-bottom: 30px;
}

.recipient-name {
    font-weight: 700;
}

.recipient-name__base {
    text-transform: uppercase;
}

.recipient-name__extension {
    text-transform: none;
    font-weight: 600;
}

.salutation {
    margin-bottom: 20px;
}

.body-text {
    text-indent: 50px;
    margin-bottom: 15px;
    text-align: justify;
}

.list-container {
    margin-left: 80px;
    margin-bottom: 20px;
}

.reference-line {
    text-indent: 80px;
}

.closing-section {
    margin-top: 60px;
    float: right;
    width: 300px;
    text-align: left;
}

.director-name {
    margin-top: 0;
    font-weight: 700;
}

.signature-gap {
    height: 30px;
}

.clearfix::after {
    content: '';
    clear: both;
    display: table;
}

</style>
