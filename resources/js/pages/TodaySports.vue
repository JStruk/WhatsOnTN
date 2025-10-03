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
const lastUpdated = ref<Date | null>(null)
let refreshTimer: number | null = null

const leagues = ['All', 'NFL', 'NBA', 'NHL', 'MLB'] as const
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

const totalCount = computed(() => events.value.length)

const filtered = computed(() => {
  if (activeLeague.value === 'All') return events.value
  return events.value.filter(e => e.league === activeLeague.value)
})

function teamInitials(name: string) {
  const words = name.split(/\s+/).filter(Boolean)
  const letters = words.slice(0, 2).map(w => w[0]?.toUpperCase() ?? '')
  return letters.join('') || '?'
}

function formatTime(iso: string) {
  const d = new Date(iso)
  return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
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
  loading.value = events.value.length === 0
  error.value = null
  try {
    const res = await fetch('/api/sports/today', { headers: { 'Accept': 'application/json' } })
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
  <div class="space-y-6">
    <div class="relative overflow-hidden rounded-xl border bg-gradient-to-br from-indigo-600 via-fuchsia-600 to-rose-500 text-white">
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
          <div class="text-xs text-white/85" v-if="lastUpdated">Updated {{ formatDate(lastUpdated) }}</div>
          <Button :disabled="loading" variant="secondary" size="sm" @click="fetchEvents">Refresh</Button>
        </div>
      </div>
    </div>

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

    <div v-else class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
      <Card v-for="e in filtered" :key="e.id" class="overflow-hidden">
        <CardHeader class="pb-3">
          <div class="flex items-center justify-between">
            <CardTitle class="text-base flex items-center gap-2">
              <Badge variant="secondary">{{ e.league }}</Badge>
              <span class="text-xs text-muted-foreground">{{ formatTime(e.startTime) }}</span>
            </CardTitle>
            <div class="flex items-center gap-2">
              <div v-if="e.status === 'live'" class="flex items-center gap-1 text-red-500">
                <span class="live-dot"></span>
                <span class="text-xs font-medium">LIVE</span>
              </div>
              <Badge :variant="statusToBadgeVariant(e.status)">{{ e.status }}</Badge>
            </div>
          </div>
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
          <div class="flex items-center justify-between pt-1">
            <Button v-if="e.link" :href="e.link" target="_blank" variant="link" size="sm">View details</Button>
            <div class="text-xs text-muted-foreground ml-auto">Status: {{ e.status }}</div>
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


