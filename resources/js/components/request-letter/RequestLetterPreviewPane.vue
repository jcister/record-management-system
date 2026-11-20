<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { ZoomIn, ZoomOut } from 'lucide-vue-next';

interface Props {
    data: {
        letter_date: string;
        recipient_name: string;
        recipient_first_name: string;
        recipient_middle_name: string;
        recipient_last_name: string;
        recipient_extension: string;
        recipient_position: string;
        recipient_designation: string;
        address_1: string;
        address_2: string;
        telephone: string;
        honorific: string;
        user_honorific: string;
        user_name: string;
        user_position: string;
        control_id: string;
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

const recipientBase = computed(() => {
    const segments = [
        props.data.recipient_first_name ?? '',
        props.data.recipient_middle_name ?? '',
        props.data.recipient_last_name ?? '',
    ]
        .map((segment) => segment?.trim() ?? '')
        .filter((segment) => segment !== '');

    if (segments.length === 0) {
        const fallback = (props.data.recipient_name ?? '').trim();
        return fallback !== '' ? fallback : 'Recipient Name';
    }

    return segments.join(' ');
});

const recipientExtension = computed(() => {
    return (props.data.recipient_extension ?? '').trim();
});

const salutationLastName = computed(() => {
    const trimmedLast = (props.data.recipient_last_name ?? '').trim();
    if (trimmedLast !== '') {
        return trimmedLast.replace(/,$/, '');
    }

    const fallback = recipientBase.value
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .pop();

    return fallback ? fallback.replace(/,$/, '') : 'Lastname';
});

const salutationPrefix = computed(() => {
    return (props.data.honorific ?? '').trim();
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
                    padding: '0.5in 1in 1in 1in',
                    marginBottom: '2rem',
                    boxSizing: 'border-box',
                }"
            >
                <div class="pdf-content text-[11pt] text-black" style="font-family: Calibri, Arial, sans-serif; line-height: 1.3;">
                    <div class="letter-header">
                        <img :src="headerImageSrc" alt="CHED Header" class="header-image" />
                    </div>

                    <div class="date-container">{{ data.letter_date || 'Letter Date' }}</div>

                    <div class="recipient-info">
                        <p class="recipient-name"><strong>{{ recipientBase }}<span v-if="recipientExtension">, <span class="no-transform">{{ recipientExtension }}</span></span></strong></p>
                        <p>{{ data.recipient_position || 'Recipient Position' }}</p>
                        <p>{{ data.recipient_designation || 'Recipient Designation' }}</p>
                        <p>{{ data.address_1 || 'Address Line 1' }}</p>
                        <p v-if="data.address_2">{{ data.address_2 }}</p>
                        <p v-if="data.telephone">Tel. No. {{ data.telephone }}</p>
                    </div>

                    <div class="salutation">
                        Dear {{ salutationPrefix ? `${salutationPrefix} ` : '' }}{{ salutationLastName }}:
                    </div>

                    <div class="body-text greeting-line">Greetings from CHED RO XI!</div>

                    <div class="body-text">
                        This has reference to the request of {{ data.user_honorific || 'Ms.' }}
                        {{ data.user_name || 'User Name' }},
                        {{ data.user_position || 'Administrative Officer' }}, this Office, for authority to dispose valueless
                        records in compliance with Section 18, Article III of RA 9470 which states that &ldquo;no government agency and
                        instrumentality shall dispose of, destroy or authorized the disposal or destruction of any public records, which
                        are in custody or under its control except with prior written authority of the Executive Director&rdquo;. In view
                        hereof, may we request for approval of the aforementioned conduct of disposal.
                    </div>

                    <div class="body-text">
                        Attached is the NAP Form No. 3 &ndash; Request for Authority to Dispose of Records for your reference.
                    </div>

                    <div class="body-text">
                        Thank you for your continued support.
                    </div>

                    <div class="very-truly-yours">
                        <p>Very truly yours,</p>
                    </div>

                    <div class="closing-section">
                        <div class="signature-gap"></div>
                        <p class="director-name">
                            <span class="font-semibold">{{ director.name || 'MARICAR R. CASQUEJO, PhD, CESO III' }}</span>
                        </p>
                        <p>{{ director.designation || 'Director IV' }}</p>
                    </div>

                    <div class="footer-control">
                        CONTROL ID {{ data.control_id || '________________' }}
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

.letter-header {
    border-bottom: 2px solid #000;
    padding-bottom: 8px;
    margin-bottom: 20px;
}

.header-image {
    width: 100%;
    height: auto;
    display: block;
}

.date-container {
    text-align: center;
    margin-top: 20px;
    margin-bottom: 30px;
}

.recipient-info {
    margin-bottom: 25px;
    line-height: 1.1;
}

.recipient-name {
    font-weight: 700;
    text-transform: uppercase;
}

.recipient-name .no-transform {
    text-transform: none;
}

.salutation {
    margin-bottom: 20px;
    font-weight: 700;
}

.body-text {
    text-indent: 50px;
    margin-bottom: 15px;
    text-align: justify;
}

.greeting-line {
    text-indent: 80px;
}

.very-truly-yours {
    margin-top: 50px;
    text-align: center;
}

.closing-section {
    margin-left: auto;
    width: 55%;
    text-align: center;
}

.director-name {
    margin-top: 0;
    font-weight: 700;

}

.signature-gap {
    height: 45px;
}

.footer-control {
    margin-top: 80px;
    font-family: 'Arial', sans-serif;
    font-weight: 700;
    font-size: 10pt;
}
</style>
