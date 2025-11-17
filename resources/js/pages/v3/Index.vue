<script setup lang="ts">
import { onMounted, ref, computed, onBeforeUnmount } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import { Radio, Timer, CalendarDays, Search, X, Sparkles, TrendingUp, Clock, MapPin, ExternalLink } from 'lucide-vue-next'

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

// UI state
const leagues = ['All', 'NFL', 'NBA', 'NHL', 'MLB'] as const
type LeagueFilter = typeof leagues[number]
const activeLeague = ref<LeagueFilter>('All')
const searchTerm = ref('')
const selectedEvent = ref<EventItem | null>(null)

const liveEvents = computed(() => events.value.filter(e => e.status === 'live'))
const upcomingEvents = computed(() => events.value.filter(e => e.status === 'scheduled'))
const finishedEvents = computed(() => events.value.filter(e => e.status === 'final'))

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
  if (status === 'live') return 'bg-gradient-to-r from-red-500/20 to-rose-500/20 text-red-400 border-red-500/30'
  if (status === 'final') return 'bg-gradient-to-r from-slate-500/20 to-slate-600/20 text-slate-300 border-slate-500/30'
  return 'bg-gradient-to-r from-emerald-500/20 to-teal-500/20 text-emerald-400 border-emerald-500/30'
}

function leagueGradient(league: string) {
  const gradients: Record<string, string> = {
    'NFL': 'from-blue-600 to-indigo-700',
    'NBA': 'from-orange-500 to-red-600',
    'NHL': 'from-slate-700 to-slate-900',
    'MLB': 'from-green-600 to-emerald-700',
  }
  return gradients[league] || 'from-purple-600 to-indigo-700'
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
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h1 class="text-4xl font-bold bg-gradient-to-r from-slate-900 to-slate-700 dark:from-slate-100 dark:to-slate-300 bg-clip-text text-transparent">
              Tonight's Games
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-2">
              Your comprehensive guide to tonight's sports action
            </p>
          </div>
          <div class="flex items-center gap-4">
            <Badge v-if="lastUpdated" variant="outline" class="px-3 py-1">
              <Timer class="w-3 h-3 mr-1.5" />
              Updated {{ formatUpdated(lastUpdated) }}
            </Badge>
            <Button :disabled="loading" @click="fetchEvents" variant="default" size="sm">
              <Sparkles class="w-4 h-4 mr-2" />
              Refresh
            </Button>
          </div>
        </div>

        <!-- Live Ticker -->
        <div v-if="liveEvents.length" class="mb-6 rounded-xl border border-red-200/50 dark:border-red-800/30 bg-gradient-to-r from-red-50/50 to-rose-50/50 dark:from-red-950/30 dark:to-rose-950/30 overflow-hidden shadow-lg">
          <div class="flex items-center gap-3 px-4 py-2 border-b border-red-200/50 dark:border-red-800/50 bg-gradient-to-r from-red-500/10 to-rose-500/10">
            <div class="live-dot"></div>
            <span class="uppercase tracking-widest text-[10px] font-semibold text-red-600 dark:text-red-400">Live Now</span>
            <span class="h-3 w-px bg-red-300/50"></span>
            <span class="text-xs text-slate-600 dark:text-slate-400 flex items-center gap-1">
              <CalendarDays class="size-4" />
              {{ new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' }) }}
            </span>
          </div>
          <div class="ticker-mask">
            <div class="ticker-track">
              <div v-for="e in liveEvents" :key="e.id" class="ticker-item">
                <div class="flex items-center gap-2 px-4 py-3">
                  <Badge variant="destructive" class="animate-pulse">LIVE</Badge>
                  <span class="font-semibold text-slate-900 dark:text-slate-100">
                    {{ e.awayTeam }} {{ e.awayScore }}
                  </span>
                  <span class="text-slate-500">vs</span>
                  <span class="font-semibold text-slate-900 dark:text-slate-100">
                    {{ e.homeScore }} {{ e.homeTeam }}
                  </span>
                  <Badge :class="['ml-2', leagueGradient(e.league)]" class="text-white text-xs">
                    {{ e.league }}
                  </Badge>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Controls -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-8">
          <div class="flex flex-wrap items-center gap-2.5">
            <Button
              v-for="l in leagues"
              :key="l"
              :variant="activeLeague === l ? 'default' : 'outline'"
              size="sm"
              class="transition-all hover:scale-105"
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
                placeholder="Search teams or league..."
                class="h-10 rounded-lg bg-white/80 dark:bg-slate-900/80 pl-10 pr-10 text-sm border border-slate-200 dark:border-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 backdrop-blur-sm w-64 transition-all"
              />
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
              <button v-if="searchTerm" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors" @click="searchTerm = ''">
                <X class="size-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Error State -->
      <div v-if="error" class="mb-6">
        <Alert variant="destructive" class="border-red-200 bg-red-50/50 dark:bg-red-950/20">
          <AlertTitle>Failed to load games</AlertTitle>
          <AlertDescription>{{ error }}</AlertDescription>
        </Alert>
      </div>

      <!-- Loading State -->
      <div v-if="loading && !events.length" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <Card v-for="n in 6" :key="n" class="overflow-hidden border-slate-200/50 dark:border-slate-800/50">
          <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
              <Skeleton class="h-5 w-20 rounded-full" />
              <Skeleton class="h-5 w-16" />
            </div>
          </CardHeader>
          <CardContent class="space-y-4">
            <div v-for="m in 3" :key="m" class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <Skeleton class="h-10 w-10 rounded-full" />
                <Skeleton class="h-4 w-32" />
              </div>
              <Skeleton class="h-6 w-8" />
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Main Content -->
      <div v-else class="space-y-8">
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Card class="border-emerald-200/50 dark:border-emerald-800/30 bg-gradient-to-br from-emerald-50/50 to-teal-50/50 dark:from-emerald-950/20 dark:to-teal-950/20">
            <CardContent class="p-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm text-emerald-600 dark:text-emerald-400 font-medium">Live Games</p>
                  <p class="text-3xl font-bold text-emerald-700 dark:text-emerald-300">{{ liveEvents.length }}</p>
                </div>
                <div class="h-12 w-12 rounded-full bg-emerald-500/10 flex items-center justify-center">
                  <Radio class="h-6 w-6 text-emerald-500 animate-pulse" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="border-blue-200/50 dark:border-blue-800/30 bg-gradient-to-br from-blue-50/50 to-indigo-50/50 dark:from-blue-950/20 dark:to-indigo-950/20">
            <CardContent class="p-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">Upcoming</p>
                  <p class="text-3xl font-bold text-blue-700 dark:text-blue-300">{{ upcomingEvents.length }}</p>
                </div>
                <div class="h-12 w-12 rounded-full bg-blue-500/10 flex items-center justify-center">
                  <Clock class="h-6 w-6 text-blue-500" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="border-slate-200/50 dark:border-slate-800/30 bg-gradient-to-br from-slate-50/50 to-slate-100/50 dark:from-slate-950/20 dark:to-slate-900/20">
            <CardContent class="p-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm text-slate-600 dark:text-slate-400 font-medium">Completed</p>
                  <p class="text-3xl font-bold text-slate-700 dark:text-slate-300">{{ finishedEvents.length }}</p>
                </div>
                <div class="h-12 w-12 rounded-full bg-slate-500/10 flex items-center justify-center">
                  <TrendingUp class="h-6 w-6 text-slate-500" />
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Games Grid -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          <Card
            v-for="e in filtered"
            :key="e.id"
            class="group overflow-hidden border-slate-200/50 dark:border-slate-800/50 hover:border-slate-300 dark:hover:border-slate-700 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 cursor-pointer bg-white/80 dark:bg-slate-900/50 backdrop-blur-sm"
            @click="selectedEvent = e"
          >
            <CardHeader class="pb-3">
              <div class="flex items-center justify-between">
                <Badge :class="['text-white', leagueGradient(e.league)]" class="font-medium">
                  {{ e.league }}
                </Badge>
                <span class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1">
                  <Clock class="w-3 h-3" />
                  {{ formatTime(e.startTime) }}
                </span>
              </div>
            </CardHeader>
            <CardContent class="space-y-4">
              <!-- Away Team -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                  <Avatar class="border-2 border-slate-200 dark:border-slate-700">
                    <AvatarImage :src="''" alt="" />
                    <AvatarFallback class="bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-700 font-semibold">
                      {{ teamInitials(e.awayTeam) }}
                    </AvatarFallback>
                  </Avatar>
                  <span class="font-semibold text-slate-900 dark:text-slate-100 truncate">
                    {{ e.awayTeam }}
                  </span>
                </div>
                <div class="tabular-nums text-xl font-bold text-slate-900 dark:text-slate-100">
                  {{ e.awayScore }}
                </div>
              </div>

              <div class="flex items-center justify-center">
                <span class="text-xs uppercase tracking-wider text-slate-400 font-medium">vs</span>
              </div>

              <!-- Home Team -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                  <Avatar class="border-2 border-slate-200 dark:border-slate-700">
                    <AvatarImage :src="''" alt="" />
                    <AvatarFallback class="bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-700 font-semibold">
                      {{ teamInitials(e.homeTeam) }}
                    </AvatarFallback>
                  </Avatar>
                  <span class="font-semibold text-slate-900 dark:text-slate-100 truncate">
                    {{ e.homeTeam }}
                  </span>
                </div>
                <div class="tabular-nums text-xl font-bold text-slate-900 dark:text-slate-100">
                  {{ e.homeScore }}
                </div>
              </div>

              <!-- Status and Venue -->
              <div class="pt-3 border-t border-slate-200 dark:border-slate-800">
                <div class="flex items-center justify-between">
                  <span
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium border transition-all"
                    :class="statusChipClasses(e.status)"
                  >
                    <Radio v-if="e.status === 'live'" class="w-3 h-3 animate-pulse" />
                    <span class="capitalize">{{ e.status }}</span>
                  </span>
                  <div v-if="e.venue" class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400 truncate max-w-[140px]" :title="e.venue">
                    <MapPin class="w-3 h-3" />
                    <span class="truncate">{{ e.venue }}</span>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <div v-if="!filtered.length" class="text-center py-12">
          <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 mb-4">
            <Search class="w-8 h-8 text-slate-400" />
          </div>
          <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-2">No games found</h3>
          <p class="text-slate-600 dark:text-slate-400">Try adjusting your filters or search term.</p>
        </div>
      </div>
    </div>

    <!-- Event Detail Modal/Sheet -->
    <div
      v-if="selectedEvent"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
      @click.self="selectedEvent = null"
    >
      <div class="w-full max-w-md rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xl animate-in fade-in zoom-in-95 duration-200">
        <div class="p-6">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
              <Badge :class="['text-white', leagueGradient(selectedEvent.league)]">
                {{ selectedEvent.league }}
              </Badge>
              <span class="text-xs text-slate-500 dark:text-slate-400">{{ formatTime(selectedEvent.startTime) }}</span>
            </div>
            <button
              class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
              @click="selectedEvent = null"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <div class="space-y-6">
            <!-- Away Team -->
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <Avatar class="h-14 w-14 border-2 border-slate-200 dark:border-slate-700">
                  <AvatarImage :src="''" alt="" />
                  <AvatarFallback class="bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-700 text-lg font-bold">
                    {{ teamInitials(selectedEvent.awayTeam) }}
                  </AvatarFallback>
                </Avatar>
                <div>
                  <div class="font-bold text-lg text-slate-900 dark:text-slate-100">{{ selectedEvent.awayTeam }}</div>
                  <div class="text-xs text-slate-500 dark:text-slate-400">Away</div>
                </div>
              </div>
              <div class="tabular-nums text-3xl font-bold text-slate-900 dark:text-slate-100">
                {{ selectedEvent.awayScore }}
              </div>
            </div>

            <div class="flex items-center justify-center">
              <span class="text-sm uppercase tracking-wider text-slate-400 font-medium">Final Score</span>
            </div>

            <!-- Home Team -->
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <Avatar class="h-14 w-14 border-2 border-slate-200 dark:border-slate-700">
                  <AvatarImage :src="''" alt="" />
                  <AvatarFallback class="bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-700 text-lg font-bold">
                    {{ teamInitials(selectedEvent.homeTeam) }}
                  </AvatarFallback>
                </Avatar>
                <div>
                  <div class="font-bold text-lg text-slate-900 dark:text-slate-100">{{ selectedEvent.homeTeam }}</div>
                  <div class="text-xs text-slate-500 dark:text-slate-400">Home</div>
                </div>
              </div>
              <div class="tabular-nums text-3xl font-bold text-slate-900 dark:text-slate-100">
                {{ selectedEvent.homeScore }}
              </div>
            </div>

            <!-- Details -->
            <div class="pt-4 border-t border-slate-200 dark:border-slate-800 space-y-3">
              <div class="flex items-center justify-between text-sm">
                <span class="text-slate-500 dark:text-slate-400">Status</span>
                <span
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border"
                  :class="statusChipClasses(selectedEvent.status)"
                >
                  <Radio v-if="selectedEvent.status === 'live'" class="w-3 h-3 animate-pulse" />
                  <span class="capitalize">{{ selectedEvent.status }}</span>
                </span>
              </div>
              <div v-if="selectedEvent.venue" class="flex items-center justify-between text-sm">
                <span class="text-slate-500 dark:text-slate-400">Venue</span>
                <span class="text-slate-900 dark:text-slate-100">{{ selectedEvent.venue }}</span>
              </div>
              <div v-if="selectedEvent.link" class="pt-2">
                <Button :href="selectedEvent.link" target="_blank" variant="outline" class="w-full">
                  <ExternalLink class="w-4 h-4 mr-2" />
                  View Details
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.text-muted-foreground {
  color: rgba(0,0,0,0.6);
}
@media (prefers-color-scheme: dark) {
  .text-muted-foreground {
    color: rgba(255,255,255,0.7);
  }
}

.live-dot {
  position: relative;
  width: 10px;
  height: 10px;
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
  gap: 0;
  animation: ticker 30s linear infinite;
}
.ticker-item {
  white-space: nowrap;
  border-right: 1px solid rgb(239 68 68 / 0.1);
}
@keyframes ticker {
  0% { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}

/* Animations */
@keyframes fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes zoom-in-95 {
  from { transform: scale(0.95); }
  to { transform: scale(1); }
}
.animate-in {
  animation-fill-mode: both;
}
.fade-in {
  animation-name: fade-in;
}
.zoom-in-95 {
  animation-name: zoom-in-95;
}
.duration-200 {
  animation-duration: 200ms;
}
</style>
