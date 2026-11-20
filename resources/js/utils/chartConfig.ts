import type { ApexOptions } from 'apexcharts';

// Color Palette as per requirements
export const CHART_COLORS = {
    success: '#27AE60', // Green - success/available
    warning: '#F39C12', // Orange - warnings/due soon
    danger: '#E74C3C', // Red - alerts/overdue
    pending: '#F1C40F', // Yellow - pending
    primary: '#3498DB', // Blue - primary actions
    purple: '#9B59B6', // Purple - for release
    gray: '#95A5A6', // Gray - neutral
} as const;

// Status color mapping
export const STATUS_COLORS = {
    Completed: CHART_COLORS.success,
    Rejected: CHART_COLORS.danger,
    Pending: CHART_COLORS.pending,
    Processing: CHART_COLORS.primary,
    'For Release': CHART_COLORS.purple,
} as const;

/**
 * Get compact sparkline chart configuration for metric cards.
 */
export function getSparklineChartConfig(color: string): ApexOptions {
    const isDark = document.documentElement.classList.contains('dark');

    return {
        ...getBaseChartConfig(),
        chart: {
            type: 'line',
            sparkline: {
                enabled: true,
            },
            parentHeightOffset: 0,
            toolbar: {
                show: false,
            },
            foreColor: isDark ? '#94a3b8' : '#64748b',
        },
        colors: [color],
        stroke: {
            curve: 'smooth',
            width: 2.5,
            lineCap: 'round',
        },
        markers: {
            size: 0,
            strokeWidth: 2,
            strokeColors: isDark ? '#0f172a' : '#ffffff',
            hover: {
                size: 6,
                sizeOffset: 0,
            },
        },
        fill: {
            type: 'gradient',
            gradient: {
                shade: isDark ? 'dark' : 'light',
                type: 'vertical',
                shadeIntensity: 0.3,
                inverseColors: false,
                opacityFrom: 0.55,
                opacityTo: 0.08,
                stops: [0, 100],
            },
        },
        tooltip: {
            enabled: true,
            fixed: {
                enabled: true,
                position: 'topLeft',
                offsetY: -8,
                offsetX: 0,
            },
            x: {
                show: false,
            },
            marker: {
                show: false,
            },
            y: {
                formatter: (value: number): string => `${value}`,
            },
        },
        grid: {
            show: false,
            padding: {
                top: 10,
                right: 6,
                bottom: 10,
                left: 6,
            },
        },
        xaxis: {
            labels: {
                show: false,
            },
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
        },
        yaxis: {
            labels: {
                show: false,
            },
        },
    };
}

/**
 * Get base chart configuration with dark mode support
 */
export function getBaseChartConfig(): ApexOptions {
    const isDark = document.documentElement.classList.contains('dark');

    return {
        chart: {
            fontFamily: 'inherit',
            foreColor: isDark ? '#94a3b8' : '#64748b',
            toolbar: {
                show: false,
            },
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 800,
            },
        },
        theme: {
            mode: isDark ? 'dark' : 'light',
        },
        tooltip: {
            theme: isDark ? 'dark' : 'light',
            style: {
                fontSize: '12px',
            },
        },
        grid: {
            borderColor: isDark ? '#334155' : '#e2e8f0',
        },
        dataLabels: {
            enabled: false,
        },
        stroke: {
            curve: 'smooth',
        },
    };
}

/**
 * Get storage gauge chart configuration
 */
export function getStorageGaugeConfig(usedPercentage: number): ApexOptions {
    const isDark = document.documentElement.classList.contains('dark');

    // Determine color based on usage
    let color: string;
    if (usedPercentage < 70) {
        color = CHART_COLORS.success;
    } else if (usedPercentage < 90) {
        color = CHART_COLORS.warning;
    } else {
        color = CHART_COLORS.danger;
    }

    return {
        ...getBaseChartConfig(),
        chart: {
            type: 'radialBar',
            fontFamily: 'inherit',
            foreColor: isDark ? '#94a3b8' : '#64748b',
        },
        plotOptions: {
            radialBar: {
                hollow: {
                    size: '65%',
                },
                track: {
                    background: isDark ? '#000000' : '#f1f5f9',
                },
                dataLabels: {
                    name: {
                        fontSize: '14px',
                        color: isDark ? '#cbd5e1' : '#475569',
                        offsetY: -10,
                    },
                    value: {
                        fontSize: '30px',
                        fontWeight: 'bold',
                        color: color,
                        offsetY: 5,
                        formatter: (val: number) => `${val}%`,
                    },
                },
            },
        },
        fill: {
            colors: [color],
        },
        labels: ['Storage Used'],
    };
}

/**
 * Get requests status donut chart configuration
 */
export function getRequestsStatusChartConfig(): ApexOptions {
    const isDark = document.documentElement.classList.contains('dark');

    return {
        ...getBaseChartConfig(),
        chart: {
            type: 'donut',
            fontFamily: 'inherit',
            foreColor: isDark ? '#94a3b8' : '#64748b',
        },
        colors: [
            STATUS_COLORS.Pending,
            STATUS_COLORS.Processing,
            STATUS_COLORS['For Release'],
            STATUS_COLORS.Completed,
            STATUS_COLORS.Rejected,
        ],
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: {
                        show: true,
                        name: {
                            fontSize: '14px',
                            color: isDark ? '#cbd5e1' : '#475569',
                        },
                        value: {
                            fontSize: '22px',
                            fontWeight: 'bold',
                            color: isDark ? '#f1f5f9' : '#0f172a',
                        },
                        total: {
                            show: true,
                            label: 'Total Requests',
                            fontSize: '14px',
                            color: isDark ? '#94a3b8' : '#64748b',
                            formatter: (w: any) => {
                                return w.globals.seriesTotals.reduce((a: number, b: number) => a + b, 0);
                            },
                        },
                    },
                },
            },
        },
        legend: {
            position: 'bottom',
            fontSize: '12px',
        },
        tooltip: {
            y: {
                formatter: (val: number) => `${val} requests`,
            },
        },
    };
}

/**
 * Get files upload trend area chart configuration
 */
export function getFilesUploadTrendConfig(): ApexOptions {
    const isDark = document.documentElement.classList.contains('dark');

    return {
        ...getBaseChartConfig(),
        chart: {
            type: 'area',
            fontFamily: 'inherit',
            foreColor: isDark ? '#94a3b8' : '#64748b',
            toolbar: {
                show: false,
            },
            zoom: {
                enabled: false,
            },
        },
        colors: [CHART_COLORS.success],
        fill: {
            type: 'gradient',
            gradient: {
                shade: isDark ? 'dark' : 'light',
                type: 'vertical',
                shadeIntensity: 0.5,
                gradientToColors: undefined,
                inverseColors: true,
                opacityFrom: 0.7,
                opacityTo: 0.1,
            },
        },
        stroke: {
            curve: 'smooth',
            width: 3,
        },
        xaxis: {
            type: 'datetime',
            labels: {
                format: 'MMM dd',
                style: {
                    fontSize: '11px',
                },
            },
            axisBorder: {
                color: isDark ? '#334155' : '#e2e8f0',
            },
        },
        yaxis: {
            labels: {
                formatter: (val: number) => Math.floor(val).toString(),
                style: {
                    fontSize: '11px',
                },
            },
        },
        tooltip: {
            x: {
                format: 'dd MMM yyyy',
            },
            y: {
                formatter: (val: number) => `${val} file${val !== 1 ? 's' : ''}`,
            },
        },
        grid: {
            borderColor: isDark ? '#334155' : '#e2e8f0',
            strokeDashArray: 4,
        },
    };
}

/**
 * Get contribution heatmap chart configuration (GitHub-style)
 */
/**
 * Utility to format bytes to human-readable format
 */
export function formatBytes(bytes: number, decimals = 2): string {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];

    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

/**
 * Get storage status color based on percentage
 */
export function getStorageStatusColor(percentage: number): keyof typeof CHART_COLORS {
    if (percentage < 70) return 'success';
    if (percentage < 90) return 'warning';
    return 'danger';
}
