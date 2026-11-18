<script setup lang="ts">
import { BarController, BarElement, CategoryScale, Chart, LinearScale, Tooltip } from 'chart.js'
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useThemeStore } from '@/stores/theme'
import type { MonthlyReportRow } from '@/types/api'
import { buildMonthlyChartConfig, type ChartColors } from '@/utils/charts'

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip)

const props = defineProps<{
  rows: MonthlyReportRow[]
  currency: string
}>()

const theme = useThemeStore()
const root = ref<HTMLElement | null>(null)
const canvas = ref<HTMLCanvasElement | null>(null)
let chart: Chart | null = null

function readColors(): ChartColors {
  const style = getComputedStyle(root.value as HTMLElement)
  const get = (name: string) => style.getPropertyValue(name).trim()

  return {
    surface: get('--viz-surface'),
    textPrimary: get('--viz-text-primary'),
    textSecondary: get('--viz-text-secondary'),
    muted: get('--viz-muted'),
    grid: get('--viz-grid'),
    baseline: get('--viz-baseline'),
    income: get('--viz-series-1'),
    expense: get('--viz-series-2'),
  }
}

function render() {
  if (!canvas.value || !root.value) return

  chart?.destroy()
  chart = new Chart(canvas.value, buildMonthlyChartConfig(props.rows, props.currency, readColors()))
}

onMounted(render)
onBeforeUnmount(() => chart?.destroy())

watch(() => [props.rows, props.currency], render)
// Цвета Canvas не реагируют на CSS сами: при смене темы график строится заново
watch(
  () => theme.isDark,
  () => void nextTick(render),
)
</script>

<template>
  <div ref="root" class="chart viz">
    <canvas ref="canvas" role="img" aria-label="Столбчатый график доходов и расходов по месяцам" />
  </div>
</template>

<style scoped>
.chart {
  position: relative;
  height: 18rem;
}
</style>
