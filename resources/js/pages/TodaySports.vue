<script setup lang="ts">
import { onMounted, ref, computed, onBeforeUnmount } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import { Sparkles, Radio } from 'lucide-vue-next'

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

// Accept props from the server
const props = defineProps<{
  initialEvents?: EventItem[]
  initialTimezone?: string
}>()

const loading = ref(false)
const error = ref<string | null>(null)
const events = ref<EventItem[]>(props.initialEvents || [])
const lastUpdated = ref<Date | null>(props.initialEvents ? new Date() : null)
const userTimezone = ref<string>(props.initialTimezone || '')
let refreshTimer: number | null = null

const leagues = ['All', 'NHL', 'NFL', 'MLB', 'NBA'] as const
type LeagueFilter = typeof leagues[number]
const activeLeague = ref<LeagueFilter>('All')

const grouped = computed(() => {
  const groups: Record<string, EventItem[]> = { NFL: [], NBA: [], NHL: [], MLB: [] }
  for (const e of events.value) {
    if (!groups[e.league]) groups[e.league] = []
    groups[e.league].push(e)
  }
  for (const key of Object.keys(groups)) {
    groups[key].sort((a, b) => a.startTime.localeCompare(b.startTime))
  }
  return groups
})

const isBeforeNoonET = computed(() => {
  const hourEt = Number(
    new Intl.DateTimeFormat('en-US', {
      hour: 'numeric',
      hour12: false,
      timeZone: 'America/New_York',
    }).format(new Date())
  )
  return hourEt < 12
})

const totalCount = computed(() => events.value.length)

const filtered = computed(() => {
  // When "All" is active, sort by league according to the `leagues` array order,
  // then by start time within each league.
  if (activeLeague.value === 'All') {
    const orderMap: Record<string, number> = {}
    // Build league -> order map, skipping the "All" entry
    leagues.forEach((l, idx) => {
      if (l !== 'All') orderMap[l] = idx
    })
    return [...events.value].sort((a, b) => {
      const ai = orderMap[a.league] ?? Number.MAX_SAFE_INTEGER
      const bi = orderMap[b.league] ?? Number.MAX_SAFE_INTEGER
      if (ai !== bi) return ai - bi
      return a.startTime.localeCompare(b.startTime)
    })
  }
  // For a specific league, sort by start time
  return events.value
    .filter(e => e.league === activeLeague.value)
    .sort((a, b) => a.startTime.localeCompare(b.startTime))
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

function formatShortDate(iso: string) {
  const d = new Date(iso)
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }).toLowerCase()
}

function formatDate(d: Date | null) {
  if (!d) return ''
  return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', second: '2-digit' })
}

function statusToBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  if (status === 'live') return 'destructive'
  if (status === 'final') return 'secondary'
  return 'outline'
}

async function fetchEvents() {
  loading.value = true
  error.value = null
  try {
    // Get user's timezone
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
  // If we don't have initial data, fetch it
  if (!props.initialEvents || props.initialEvents.length === 0) {
    await fetchEvents()
  } else {
    // Detect user's timezone even if we have initial data
    userTimezone.value = Intl.DateTimeFormat().resolvedOptions().timeZone
  }
  startAutoRefresh(60000)
})

onBeforeUnmount(() => {
  stopAutoRefresh()
})
</script>

<template>
  <div class="space-y-6">
    <div class="relative overflow-hidden rounded-xl border bg-gradient-to-br from-slate-800 via-blue-800 to-cyan-700 text-white">
      <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_20%_20%,white,transparent_40%),radial-gradient(circle_at_80%_0%,white,transparent_40%)]"></div>
      <div class="relative p-6 sm:p-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
          <h1 class="text-2xl font-semibold tracking-tight flex items-center gap-2">
            <Sparkles class="size-5" /> Today’s Games
          </h1>
          <p class="text-white/85 text-sm">Across NHL, NBA, MLB, and NFL</p>
        </div>
        <div class="flex items-center gap-4">
          <div class="text-xs text-white/85">{{ new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' }) }}</div>
          <div class="hidden sm:block h-6 w-px bg-white/30"></div>
          <div class="text-xs text-white/85">{{ totalCount }} events</div>
          <div class="hidden sm:block h-6 w-px bg-white/30"></div>
          <div class="text-xs text-white/85" v-if="userTimezone">Timezone: {{ userTimezone }}</div>
          <div class="hidden sm:block h-6 w-px bg-white/30"></div>
          <div class="text-xs text-white/85" v-if="lastUpdated">Updated {{ formatDate(lastUpdated) }}</div>
          <Button :disabled="loading" variant="secondary" size="sm" @click="fetchEvents">Refresh</Button>
        </div>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 mx-2">
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

    <div v-if="error">
      <Alert variant="destructive">
        <AlertTitle>Failed to load games</AlertTitle>
        <AlertDescription>{{ error }}</AlertDescription>
      </Alert>
    </div>

    <div v-if="loading && !events.length" class="grid gap-6 lg:grid-cols-2">
      <Card v-for="n in 4" :key="n" class="overflow-hidden">
        <CardHeader>
          <div class="flex items-center justify-between">
            <Skeleton class="h-5 w-24" />
            <Skeleton class="h-5 w-12" />
          </div>
          <Skeleton class="h-4 w-40" />
        </CardHeader>
        <CardContent class="divide-y">
          <div v-for="m in 3" :key="m" class="flex items-center justify-between gap-4 py-4">
            <div class="flex items-center gap-3">
              <Skeleton class="h-5 w-12 rounded" />
              <Skeleton class="h-4 w-12" />
              <Skeleton class="h-4 w-56" />
            </div>
            <Skeleton class="h-5 w-12" />
          </div>
        </CardContent>
      </Card>
    </div>

    <div v-else class="grid sm:grid-cols-2 xl:grid-cols-3">
      <Card v-for="e in filtered" :key="e.id" class="overflow-hidden m-2">
        <CardHeader class="pb-3">
          <div class="flex items-center justify-between">
            <CardTitle class="text-base flex items-center gap-2">
              <Badge variant="secondary">{{ e.league }}</Badge>
              <span class="text-xs text-muted-foreground">{{ formatTime(e.startTime) }}</span>
              <span v-if="e.league === 'NBA' && isBeforeNoonET" class="text-xs text-muted-foreground">• {{ formatShortDate(e.startTime) }}</span>
            </CardTitle>
            <div class="flex items-center gap-2">
              <div v-if="e.status === 'live'" class="flex items-center gap-1 text-red-500">
                <span class="live-dot"></span>
                <span class="text-xs font-medium">LIVE</span>
              </div>
              <Badge :variant="statusToBadgeVariant(e.status)">{{ e.status }}</Badge>
            </div>
          </div>
          <div v-if="e.league === 'NBA' && isBeforeNoonET" class="text-xs text-muted-foreground">NBA games aren’t refreshed until 12 PM EST</div>
          <CardDescription v-if="e.venue" class="truncate">{{ e.venue }}</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-3">
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
              <Avatar>
                <AvatarImage :src="''" alt="" />
                <AvatarFallback>{{ teamInitials(e.awayTeam) }}</AvatarFallback>
              </Avatar>
              <div class="truncate">
                <div :class="['truncate font-medium', e.awayScore > e.homeScore ? 'text-foreground' : 'text-foreground/80']">{{ e.awayTeam }}</div>
                <div class="text-xs text-muted-foreground">Away</div>
              </div>
            </div>
            <div class="tabular-nums text-xl font-semibold" :class="e.awayScore > e.homeScore ? 'text-foreground' : 'text-muted-foreground'">
              {{ e.awayScore }}
            </div>
          </div>
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
              <Avatar>
                <AvatarImage :src="''" alt="" />
                <AvatarFallback>{{ teamInitials(e.homeTeam) }}</AvatarFallback>
              </Avatar>
              <div class="truncate">
                <div :class="['truncate font-medium', e.homeScore >= e.awayScore ? 'text-foreground' : 'text-foreground/80']">{{ e.homeTeam }}</div>
                <div class="text-xs text-muted-foreground">Home</div>
              </div>
            </div>
            <div class="tabular-nums text-xl font-semibold" :class="e.homeScore >= e.awayScore ? 'text-foreground' : 'text-muted-foreground'">
              {{ e.homeScore }}
            </div>
          </div>
          <div v-if="e.link" class="flex items-center justify-between pt-1">
            <Button :href="e.link" target="_blank" variant="link" size="sm">View details</Button>
          </div>
        </CardContent>
      </Card>

      <Card v-if="!filtered.length">
        <CardHeader>
          <CardTitle>No games found</CardTitle>
          <CardDescription>Try changing the league filter or refresh.</CardDescription>
        </CardHeader>
        <CardContent>
          <Button variant="outline" size="sm" @click="fetchEvents">Refresh</Button>
        </CardContent>
      </Card>
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
</style>


