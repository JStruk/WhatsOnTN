<script setup lang="ts">
import { onMounted, ref, computed, onBeforeUnmount } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import { Radio, Timer, CalendarDays, Search, X } from 'lucide-vue-next'

type EventItem = {
  id: string
  league: 'NHL' | 'NBA' | 'MLB' | 'NFL' | string
  status: 'scheduled' | 'live' | 'final' | string
  startTime: string
  startTimeUTC?: string | null
  venue?: string | null
  venueTimezone?: string | null
  homeTeam: string
  awayTeam: string
  homeScore: number
  awayScore: number
  link?: string | null
}

const loading = ref(true)
const error = ref<string | null>(null)
const events = ref<EventItem[]>([])
const lastUpdated = ref<Date | null>(null)
const userTimezone = ref<string>('')
let refreshTimer: number | null = null

// New UI state
const leagues = ['All', 'NFL', 'NBA', 'NHL', 'MLB'] as const
type LeagueFilter = typeof leagues[number]
const activeLeague = ref<LeagueFilter>('All')
const searchTerm = ref('')
const compactMode = ref(true)
const selectedEvent = ref<EventItem | null>(null)

const liveEvents = computed(() => events.value.filter(e => e.status === 'live'))
const totalCount = computed(() => events.value.length)

const filtered = computed(() => {
  const byLeague = activeLeague.value === 'All'
    ? events.value
    : events.value.filter(e => e.league === activeLeague.value)

  const bySearch = searchTerm.value.trim()
    ? byLeague.filter(e =>
        (e.homeTeam + ' ' + e.awayTeam + ' ' + e.league)
          .toLowerCase()
          .includes(searchTerm.value.trim().toLowerCase())
      )
    : byLeague

  return [...bySearch].sort((a, b) => a.startTime.localeCompare(b.startTime))
})

function teamInitials(name: string) {
  const words = name.split(/\s+/).filter(Boolean)
  const letters = words.slice(0, 2).map(w => w[0]?.toUpperCase() ?? '')
  return letters.join('') || '?'
}

function formatTime(iso: string) {
  const d = new Date(iso)
  return d.toLocaleTimeString(undefined, {
    hour: 'numeric',
    minute: '2-digit',
    timeZoneName: 'short'
  })
}

function formatUpdated(d: Date | null) {
  if (!d) return ''
  return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', second: '2-digit' })
}

function statusChipClasses(status: string) {
  if (status === 'live') return 'bg-red-600/10 text-red-500 ring-1 ring-red-500/30'
  if (status === 'final') return 'bg-slate-600/10 text-slate-400 ring-1 ring-slate-400/20'
  return 'bg-emerald-600/10 text-emerald-500 ring-1 ring-emerald-500/20'
}

async function fetchEvents() {
  loading.value = events.value.length === 0
  error.value = null
  try {
    const detectedTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone
    userTimezone.value = detectedTimezone
    const url = `/api/sports/today?timezone=${encodeURIComponent(detectedTimezone)}`
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } })
    if (!res.ok) throw new Error('Failed to load sports data')
    const json = await res.json()
    events.value = json.events || []
    lastUpdated.value = new Date()
  } catch (e: any) {
    error.value = e?.message || 'Unknown error'
  } finally {
    loading.value = false
  }
}

function startAutoRefresh(intervalMs = 60000) {
  stopAutoRefresh()
  refreshTimer = window.setInterval(() => {
    fetchEvents()
  }, intervalMs)
}

function stopAutoRefresh() {
  if (refreshTimer) {
    clearInterval(refreshTimer)
    refreshTimer = null
  }
}

onMounted(async () => {
  await fetchEvents()
  startAutoRefresh(60000)
})

onBeforeUnmount(() => {
  stopAutoRefresh()
})
</script>

<template>
  <div class="space-y-8">
    <!-- Top Ticker -->
    <div class="relative rounded-lg border bg-slate-950 text-slate-200 overflow-hidden">
      <div class="flex items-center gap-3 px-4 py-2 border-b border-slate-800/60 bg-gradient-to-r from-slate-900 to-slate-950">
        <span class="uppercase tracking-widest text-[10px] text-slate-400">Live Ticker</span>
        <span class="h-3 w-px bg-slate-700"></span>
        <span class="text-xs text-slate-400 flex items-center gap-1"><CalendarDays class="size-4" /> {{ new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' }) }}</span>
        <span class="h-3 w-px bg-slate-700"></span>
        <span class="text-xs text-slate-400" v-if="lastUpdated"><Timer class="inline -mt-0.5 size-4" /> Updated {{ formatUpdated(lastUpdated) }}</span>
      </div>
      <div class="ticker-mask">
        <div class="ticker-track" :class="{ 'pause': !liveEvents.length }">
          <div v-if="!liveEvents.length" class="px-6 py-2 text-slate-500">No live games at the moment.</div>
          <div v-else v-for="e in liveEvents" :key="e.id" class="ticker-item">
            <span class="inline-flex items-center gap-2">
              <span class="live-dot"></span>
              <span class="text-xs uppercase tracking-wide text-red-400">Live</span>
              <span class="text-sm font-semibold">{{ e.awayTeam }} {{ e.awayScore }} - {{ e.homeScore }} {{ e.homeTeam }}</span>
              <span class="text-xs text-slate-400">{{ e.league }}</span>
              <span class="text-xs text-slate-500">• {{ formatTime(e.startTime) }}</span>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Controls Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex flex-wrap items-center gap-2">
        <Button
          v-for="l in leagues"
          :key="l"
          :variant="activeLeague === l ? 'default' : 'outline'"
          size="sm"
          @click="activeLeague = l"
        >
          {{ l }}
        </Button>
      </div>
      <div class="flex items-center gap-3">
        <div class="relative">
          <input
            v-model="searchTerm"
            type="text"
            placeholder="Search teams or league"
            class="h-9 rounded-md bg-slate-50 dark:bg-slate-900 pl-8 pr-8 text-sm border border-slate-200 dark:border-slate-800 focus:outline-none focus:ring-2 focus:ring-cyan-600"
          />
          <Search class="absolute left-2 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
          <button v-if="searchTerm" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400" @click="searchTerm = ''">
            <X class="size-4" />
          </button>
        </div>
        <Button variant="outline" size="sm" @click="compactMode = !compactMode">{{ compactMode ? 'Comfortable' : 'Compact' }}</Button>
        <Button :disabled="loading" variant="secondary" size="sm" @click="fetchEvents">Refresh</Button>
      </div>
    </div>

    <!-- Content -->
    <div v-if="error">
      <Alert variant="destructive">
        <AlertTitle>Failed to load games</AlertTitle>
        <AlertDescription>{{ error }}</AlertDescription>
      </Alert>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading && !events.length" class="grid gap-6 md:grid-cols-2">
      <Card v-for="n in 4" :key="n" class="overflow-hidden">
        <CardContent class="p-6">
          <div class="flex items-center justify-between">
            <Skeleton class="h-5 w-24" />
            <Skeleton class="h-5 w-12" />
          </div>
          <div class="mt-6 space-y-4">
            <div v-for="m in 3" :key="m" class="flex items-center justify-between gap-4">
              <div class="flex items-center gap-3">
                <Skeleton class="h-5 w-12 rounded" />
                <Skeleton class="h-4 w-40" />
              </div>
              <Skeleton class="h-5 w-10" />
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Two-Panel Layout: Table + Details -->
    <div v-else class="grid gap-6 lg:grid-cols-3">
      <!-- Table -->
      <div class="lg:col-span-2 overflow-hidden rounded-lg border">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-300">
              <tr>
                <th class="text-left font-medium px-3 py-2 whitespace-nowrap">Time</th>
                <th class="text-left font-medium px-3 py-2 whitespace-nowrap">Matchup</th>
                <th class="text-left font-medium px-3 py-2 whitespace-nowrap">Score</th>
                <th class="text-left font-medium px-3 py-2 whitespace-nowrap">Status</th>
                <th class="text-left font-medium px-3 py-2 whitespace-nowrap">League</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="e in filtered"
                :key="e.id"
                class="border-t hover:bg-slate-50/70 dark:hover:bg-slate-900/60 cursor-pointer"
                @click="selectedEvent = e"
              >
                <td class="px-3 py-2 whitespace-nowrap text-slate-500">{{ formatTime(e.startTime) }}</td>
                <td class="px-3 py-2">
                  <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 min-w-0">
                      <Avatar class="size-6">
                        <AvatarImage :src="''" alt="" />
                        <AvatarFallback>{{ teamInitials(e.awayTeam) }}</AvatarFallback>
                      </Avatar>
                      <span class="truncate" :class="compactMode ? 'max-w-[160px]' : 'max-w-[240px]'">{{ e.awayTeam }}</span>
                    </div>
                    <span class="text-slate-400">at</span>
                    <div class="flex items-center gap-2 min-w-0">
                      <Avatar class="size-6">
                        <AvatarImage :src="''" alt="" />
                        <AvatarFallback>{{ teamInitials(e.homeTeam) }}</AvatarFallback>
                      </Avatar>
                      <span class="truncate" :class="compactMode ? 'max-w-[160px]' : 'max-w-[240px]'">{{ e.homeTeam }}</span>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-2 tabular-nums font-semibold">{{ e.awayScore }} - {{ e.homeScore }}</td>
                <td class="px-3 py-2">
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px]" :class="statusChipClasses(e.status)">
                    <Radio v-if="e.status === 'live'" class="size-3" />
                    <span class="capitalize">{{ e.status }}</span>
                  </span>
                </td>
                <td class="px-3 py-2"><Badge variant="secondary">{{ e.league }}</Badge></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="!filtered.length" class="p-6 text-sm text-slate-500">No games match your filters.</div>
      </div>

      <!-- Details Panel -->
      <div class="lg:col-span-1">
        <div v-if="selectedEvent" class="rounded-lg border overflow-hidden">
          <div class="p-4 border-b flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Badge variant="secondary">{{ selectedEvent.league }}</Badge>
              <span class="text-xs text-slate-500">{{ formatTime(selectedEvent.startTime) }}</span>
            </div>
            <button class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" @click="selectedEvent = null">
              <X class="size-4" />
            </button>
          </div>
          <div class="p-4 space-y-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3 min-w-0">
                <Avatar>
                  <AvatarImage :src="''" alt="" />
                  <AvatarFallback>{{ teamInitials(selectedEvent.awayTeam) }}</AvatarFallback>
                </Avatar>
                <div class="truncate">
                  <div class="truncate font-semibold">{{ selectedEvent.awayTeam }}</div>
                  <div class="text-xs text-muted-foreground">Away</div>
                </div>
              </div>
              <div class="tabular-nums text-2xl font-bold">{{ selectedEvent.awayScore }}</div>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3 min-w-0">
                <Avatar>
                  <AvatarImage :src="''" alt="" />
                  <AvatarFallback>{{ teamInitials(selectedEvent.homeTeam) }}</AvatarFallback>
                </Avatar>
                <div class="truncate">
                  <div class="truncate font-semibold">{{ selectedEvent.homeTeam }}</div>
                  <div class="text-xs text-muted-foreground">Home</div>
                </div>
              </div>
              <div class="tabular-nums text-2xl font-bold">{{ selectedEvent.homeScore }}</div>
            </div>
            <div class="text-xs text-muted-foreground">
              <div v-if="selectedEvent.venue" class="truncate">{{ selectedEvent.venue }}</div>
              <div class="capitalize">Status: {{ selectedEvent.status }}</div>
            </div>
            <div class="pt-2">
              <Button v-if="selectedEvent.link" :href="selectedEvent.link" target="_blank" variant="outline" size="sm">Open details</Button>
            </div>
          </div>
        </div>
        <div v-else class="rounded-lg border p-6 text-sm text-slate-500">Select a game to see details.</div>
      </div>
    </div>
  </div>
</template>

<style scoped>

.text-muted-foreground { color: rgba(0,0,0,0.6); }
@media (prefers-color-scheme: dark) {
  .text-muted-foreground { color: rgba(255,255,255,0.7); }
}

.live-dot {
  position: relative;
  width: 8px;
  height: 8px;
  border-radius: 9999px;
  background-color: rgb(239 68 68);
}
.live-dot::after {
  content: '';
  position: absolute;
  inset: -6px;
  border-radius: 9999px;
  border: 2px solid rgba(239,68,68,0.5);
  animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse {
  0% { transform: scale(0.9); opacity: 0.6; }
  70% { transform: scale(1.2); opacity: 0; }
  100% { transform: scale(0.9); opacity: 0; }
}

/* Ticker */
.ticker-mask {
  mask-image: linear-gradient(90deg, transparent, black 8%, black 92%, transparent);
  -webkit-mask-image: linear-gradient(90deg, transparent, black 8%, black 92%, transparent);
  overflow: hidden;
}
.ticker-track {
  display: flex;
  gap: 32px;
  padding: 8px 16px;
  animation: ticker 25s linear infinite;
}
.ticker-track.pause { animation-play-state: paused; }
.ticker-item { white-space: nowrap; }
@keyframes ticker {
  0% { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}
</style>


