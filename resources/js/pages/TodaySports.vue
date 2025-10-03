<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'

type EventItem = {
  id: string
  league: 'NHL' | 'NBA' | 'MLB' | 'NFL' | string
  status: 'scheduled' | 'live' | 'final' | string
  startTime: string
  venue?: string | null
  homeTeam: string
  awayTeam: string
  homeScore: number
  awayScore: number
  link?: string | null
}

const loading = ref(true)
const error = ref<string | null>(null)
const events = ref<EventItem[]>([])

const grouped = computed(() => {
  const groups: Record<string, EventItem[]> = { NFL: [], NBA: [], NHL: [], MLB: [] }
  for (const e of events.value) {
    if (!groups[e.league]) groups[e.league] = []
    groups[e.league].push(e)
  }
  // Sort each group by time
  for (const key of Object.keys(groups)) {
    groups[key].sort((a, b) => a.startTime.localeCompare(b.startTime))
  }
  return groups
})

function formatTime(iso: string) {
  const d = new Date(iso)
  return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
}

function statusBadgeClass(status: string) {
  if (status === 'live') return 'bg-red-500 text-white'
  if (status === 'final') return 'bg-gray-700 text-white dark:bg-gray-600'
  return 'bg-muted text-foreground'
}

onMounted(async () => {
  try {
    const res = await fetch('/api/sports/today')
    if (!res.ok) throw new Error('Failed to load sports data')
    const json = await res.json()
    events.value = json.events || []
  } catch (e: any) {
    error.value = e?.message || 'Unknown error'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-8">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Today’s Games</h1>
      <p class="text-muted-foreground">NHL, NBA, MLB, NFL</p>
    </div>

    <div v-if="loading" class="text-muted-foreground">Loading…</div>
    <div v-else-if="error" class="text-red-600">{{ error }}</div>
    <div v-else>
      <div class="grid gap-8">
        <template v-for="league in ['NFL','NBA','NHL','MLB']" :key="league">
          <div v-if="grouped[league] && grouped[league].length">
            <h2 class="text-xl font-semibold">{{ league }}</h2>
            <div class="divide-y rounded-md border">
              <div v-for="e in grouped[league]" :key="e.id" class="flex items-center justify-between gap-4 p-4">
                <div class="flex items-center gap-3">
                  <span class="inline-flex rounded px-2 py-1 text-xs font-medium" :class="statusBadgeClass(e.status)">
                    {{ e.status }}
                  </span>
                  <span class="text-sm text-muted-foreground w-16">{{ formatTime(e.startTime) }}</span>
                  <span class="font-medium">{{ e.awayTeam }} @ {{ e.homeTeam }}</span>
                  <span v-if="e.venue" class="text-sm text-muted-foreground">— {{ e.venue }}</span>
                </div>
                <div class="flex items-center gap-3">
                  <span class="tabular-nums font-semibold">{{ e.awayScore }} - {{ e.homeScore }}</span>
                  <a v-if="e.link" :href="e.link" target="_blank" class="text-sm text-primary underline">Details</a>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <div v-if="!grouped.NFL.length && !grouped.NBA.length && !grouped.NHL.length && !grouped.MLB.length" class="text-muted-foreground">
        No games found for today.
      </div>
    </div>
  </div>
</template>

<style scoped>
.bg-muted { background-color: rgba(0,0,0,0.06); }
.text-muted-foreground { color: rgba(0,0,0,0.6); }
@media (prefers-color-scheme: dark) {
  .bg-muted { background-color: rgba(255,255,255,0.08); }
  .text-muted-foreground { color: rgba(255,255,255,0.7); }
}
</style>


