<script setup>
import {
  BookOpen,
  CalendarDays,
  ChartColumn,
  ClipboardList,
  LayoutDashboard,
  Settings,
  ShieldCheck,
  Users,
} from '@lucide/vue'
import { computed } from 'vue'

const props = defineProps({
  name: {
    type: String,
    default: '',
  },
  size: {
    type: Number,
    default: 18,
  },
})

const icons = {
  'book-open': BookOpen,
  'calendar-days': CalendarDays,
  'chart-column': ChartColumn,
  'clipboard-list': ClipboardList,
  'layout-dashboard': LayoutDashboard,
  settings: Settings,
  'shield-check': ShieldCheck,
  users: Users,
}

const component = computed(() => icons[props.name] || LayoutDashboard)
</script>

<template>
  <component :is="component" :size="size" :stroke-width="2.2" aria-hidden="true" />
</template>
