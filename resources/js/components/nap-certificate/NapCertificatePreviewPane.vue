<script setup lang="ts">
import { computed, watch } from 'vue';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ZoomIn, ZoomOut, Download } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    data: {
        agency_name: string;
        address: string;
        date: string;
        telephone_number: string;
        location_of_records: string;
        volume_in_cubic_meter: string;
        prepared_by_name: string;
        prepared_by_position: string;
        certified_by_name: string;
        certified_by_designation: string;
        items: Array<{
            grds_rds_item_no: string;
            record_series_title: string;
            period_covered: string;
            retention_period: string;
        }>;
    };
    batchReference: string;
}

const props = defineProps<Props>();

const zoom = ref(90); // 90% for better fit while keeping readability

const zoomIn = () => {
    if (zoom.value < 150) zoom.value += 10;
};

const zoomOut = () => {
    if (zoom.value > 50) zoom.value -= 10;
};

const resetZoom = () => {
    zoom.value = 90;
};

// Calculate approximate page count based on number of records
const estimatedPages = computed(() => {
    if (!props.data?.items?.length) return 1;

    // Based on actual PDF: with header, 10-15 records fill page 1 entirely
    // The certification section then goes to page 2
    // For larger batches, estimate ~30 records per additional page
    const recordCount = props.data.items.length;

    if (recordCount <= 9) return 1; // Small batch, everything fits on 1 page
    if (recordCount <= 40) return 2; // Header + records + cert section = 2 pages
    if (recordCount <= 70) return 3;
    if (recordCount <= 100) return 4;

    // For very large batches, estimate ~30 records per page
    return Math.ceil(recordCount / 30) + 1;
});
</script>

<template>
    <div class="flex h-full flex-col bg-gray-600">
        <!-- Preview Controls -->
        <div class="flex items-center justify-between border-b bg-card px-4 py-2 shrink-0">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium">Live Preview</span>
                <span v-if="estimatedPages > 1" class="text-xs text-muted-foreground ml-2">
                    • ~{{ estimatedPages }} pages
                </span>
                <span class="text-xs text-muted-foreground">{{ zoom }}%</span>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="icon" @click="zoomOut" :disabled="zoom <= 50">
                    <ZoomOut class="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="sm" @click="resetZoom">
                    Reset
                </Button>
                <Button variant="ghost" size="icon" @click="zoomIn" :disabled="zoom >= 150">
                    <ZoomIn class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <!-- PDF Preview Container -->
        <div class="flex-1 overflow-auto p-2 scroll-smooth">
            <div
                class="mx-auto bg-white shadow-2xl"
                :style="{
                    transform: `scale(${zoom / 100})`,
                    transformOrigin: 'top center',
                    width: '100%',
                    maxWidth: '210mm', /* A4 width max */
                    minHeight: 'fit-content',
                    padding: 'clamp(4mm, 1.5vw, 10.16mm)', /* Responsive padding */
                    marginBottom: '2rem',
                    boxSizing: 'border-box'
                }"
            >
                <!-- PDF-styled content matching Blade template exactly -->
                <div class="pdf-content text-[10pt] leading-tight text-black" style="font-family: Arial, Helvetica, sans-serif; line-height: 1.2;">
                    <!-- Top metadata table -->
                    <table class="w-full mb-1 text-[8pt]" style="border: none; border-collapse: collapse;">
                        <tr>
                            <td style="text-align: left; border: none; padding: 0;">
                                NAP Form No. 3<br>Revised 2012
                            </td>
                            <td style="text-align: right; border: none; padding: 0;">
                                Accomplish in 3 copies
                            </td>
                        </tr>
                    </table>

                    <!-- Main container table -->
                    <table class="w-full border-collapse" style="border: 1.5pt solid black; table-layout: fixed;">
                        <!-- Header Row with Inner Box -->
                        <tr>
                            <td colspan="2" rowspan="2" class="text-center" style="border: 1pt solid black; padding: 10px; vertical-align: middle;">
                                <div class="mx-auto border border-black p-4" style="width: 90%;">
                                    <div class="text-[11pt] font-bold uppercase" style="font-family: 'Book Antiqua', Palatino, serif;">
                                        NATIONAL ARCHIVES OF THE PHILIPPINES
                                    </div>
                                    <div class="text-[8.5pt] italic mb-2" style="font-family: Verdana, sans-serif;">
                                        Pambansang Sinupan ng Pilipinas
                                    </div>
                                    <div class="text-[10.5pt] font-bold uppercase mt-2">
                                        REQUEST FOR AUTHORITY TO DISPOSE OF RECORDS
                                    </div>
                                </div>
                            </td>
                            <td colspan="2" style="border: 1pt solid black; padding: 6px 8px; vertical-align: top;">
                                <span class="text-[7.5pt] font-bold uppercase block mb-0.5">AGENCY NAME:</span>
                                <div class="text-[9.5pt]">{{ data.agency_name || '' }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="border: 1pt solid black; padding: 6px 8px; vertical-align: top;">
                                <span class="text-[7.5pt] font-bold uppercase block mb-0.5">ADDRESS:</span>
                                <div class="text-[9.5pt]">{{ data.address || '' }}</div>
                            </td>
                        </tr>

                        <!-- Date and Telephone Row -->
                        <tr>
                            <td colspan="2" style="border: 1pt solid black; padding: 6px 8px; vertical-align: top;">
                                <span class="text-[7.5pt] font-bold uppercase block mb-0.5">DATE:</span>
                                <div class="text-[9.5pt]">{{ data.date || '' }}</div>
                            </td>
                            <td colspan="2" style="border: 1pt solid black; padding: 6px 8px; vertical-align: top;">
                                <span class="text-[7.5pt] font-bold uppercase block mb-0.5">TELEPHONE NUMBER:</span>
                                <div class="text-[9.5pt]">{{ data.telephone_number || '' }}</div>
                            </td>
                        </tr>

                        <!-- Records Table Header -->
                        <tr class="text-center font-bold text-[10pt]">
                            <td style="border: 1pt solid black; padding: 6px 8px; width: 15%;">
                                GRDS/ RDS ITEM NO.
                            </td>
                            <td style="border: 1pt solid black; padding: 6px 8px; width: 35%;">
                                RECORD SERIES TITLE AND DESCRIPTION
                            </td>
                            <td style="border: 1pt solid black; padding: 6px 8px; width: 25%;">
                                PERIOD COVERED
                            </td>
                            <td style="border: 1pt solid black; padding: 6px 8px; width: 25%;">
                                RETENTION PERIOD AND PROVISION/S COMPLIED (If Any)
                            </td>
                        </tr>

                        <!-- Records Rows - Show ALL items, not just first 15 -->
                        <tr v-for="(item, index) in data.items" :key="index" class="record-row text-center">
                            <td class="record-cell" style="padding: 4px 8px;">
                                {{ item.grds_rds_item_no }}
                            </td>
                            <td class="record-cell text-left" style="padding: 4px 8px;">
                                {{ item.record_series_title }}
                            </td>
                            <td class="record-cell" style="padding: 4px 8px;">
                                {{ item.period_covered }}
                            </td>
                            <td class="record-cell" style="padding: 4px 8px;">
                                {{ item.retention_period }}
                            </td>
                        </tr>

                        <!-- Location and Volume Row -->
                        <tr>
                            <td colspan="2" style="border: 1pt solid black; padding: 6px 8px; vertical-align: top;">
                                <span class="text-[7.5pt] font-bold uppercase block mb-0.5">LOCATION OF RECORDS:</span>
                                <div class="text-[9.5pt]">{{ data.location_of_records || '' }}</div>
                            </td>
                            <td colspan="2" style="border: 1pt solid black; padding: 6px 8px; vertical-align: top;">
                                <span class="text-[7.5pt] font-bold uppercase block mb-0.5">VOLUME IN CUBIC METER:</span>
                                <div class="text-[9.5pt]">{{ data.volume_in_cubic_meter ? `${data.volume_in_cubic_meter} m³` : '' }}</div>
                            </td>
                        </tr>

                        <!-- Prepared By Row -->
                        <tr>
                            <td colspan="2" style="border: 1pt solid black; padding: 6px 8px; vertical-align: top;">
                                <span class="text-[7.5pt] font-bold uppercase block mb-0.5">PREPARED BY: (Name & Signature)</span>
                                <div class="text-[10pt] font-bold mt-4">{{ data.prepared_by_name || '' }}</div>
                            </td>
                            <td colspan="2" style="border: 1pt solid black; padding: 6px 8px; vertical-align: top;">
                                <span class="text-[7.5pt] font-bold uppercase block mb-0.5">POSITION:</span>
                                <div class="mt-4">{{ data.prepared_by_position || '' }}</div>
                            </td>
                        </tr>

                        <!-- Page Break Indicator (shows when records would cause page 2) -->
                        <tr v-if="data.items && data.items.length >= 10" style="border: none; height: 0;">
                            <td colspan="4" style="border: none; padding: 0; height: 0;">
                                <div class="my-8 mx-auto text-center text-xs text-gray-400 flex items-center justify-center gap-2">
                                    <div class="flex-1 border-t-2 border-dashed border-gray-300"></div>
                                    <span class="px-3 py-1 bg-gray-100 rounded">Page 2</span>
                                    <div class="flex-1 border-t-2 border-dashed border-gray-300"></div>
                                </div>
                            </td>
                        </tr>

                        <!-- Certified and Approved Row -->
                        <tr>
                            <td colspan="4" class="text-center" style="border: 1pt solid black; padding: 20px 10px; page-break-inside: avoid;">
                                <div class="text-left mb-1">
                                    <span class="text-[7.5pt] font-bold uppercase">CERTIFIED AND APPROVED BY:</span>
                                </div>
                                <p class="my-4 text-[10pt]">
                                    This is to certify that the above mentioned records are no longer needed and<br>
                                    not involved nor connected in any administrative or judicial cases.
                                </p>

                                <div class="inline-block text-center mt-10" style="width: 65%; margin-left: 35%;">
                                    <div class="text-[10pt] font-bold">{{ data.certified_by_name || '' }}</div>
                                    <div class="text-[9pt]">{{ data.certified_by_designation || '' }}</div>

                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Ensure clean PDF-like appearance */
.pdf-content * {
    letter-spacing: -0.01em;
}

/* Record rows - no horizontal borders except first row */
.record-row td.record-cell {
    border-top: none !important;
    border-bottom: none !important;
    border-left: 1pt solid black;
    border-right: 1pt solid black;
}

/* First record row gets top border */
.record-row:first-of-type td.record-cell {
    border-top: 1pt solid black !important;
}

/* Last record row before footer gets bottom border */
.record-row:last-of-type td.record-cell {
    border-bottom: 1pt solid black !important;
}

/* Print media query for when generating actual PDF */
@media print {
    .pdf-content {
        transform: none !important;
        width: 100% !important;
    }
}
</style>
