<script setup lang="ts">
import { onMounted, ref, computed, onBeforeUnmount } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Calendar, RefreshCw } from 'lucide-vue-next'

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

// League priority order
const leaguePriority = ['NHL', 'NFL', 'NBA', 'MLB']

const groupedByLeague = computed(() => {
  const groups: Record<string, EventItem[]> = {
    NHL: [],
    NFL: [],
    NBA: [],
    MLB: []
  }
  
  for (const event of events.value) {
    if (groups[event.league]) {
      groups[event.league].push(event)
    }
  }
  
  // Sort events within each league by start time
  for (const league of Object.keys(groups)) {
    groups[league].sort((a, b) => a.startTime.localeCompare(b.startTime))
  }
  
  return groups
})

const totalCount = computed(() => events.value.length)

function formatTime(iso: string) {
  const d = new Date(iso)
  return d.toLocaleTimeString(undefined, {
    hour: 'numeric',
    minute: '2-digit',
    timeZoneName: 'short'
  })
}

function formatDate(d: Date | null) {
  if (!d) return ''
  return d.toLocaleTimeString(undefined, { 
    hour: 'numeric', 
    minute: '2-digit'
  })
}

function getStatusColor(status: string): string {
  if (status === 'live') return 'text-red-500'
  if (status === 'final') return 'text-slate-500'
  return 'text-slate-700 dark:text-slate-300'
}

function getStatusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  if (status === 'live') return 'destructive'
  if (status === 'final') return 'secondary'
  return 'outline'
}

// Placeholder logo - using the NHL logo pattern provided
const getTeamLogo = (league: string, teamName: string) => {
  // For now, use the NHL Oilers logo as placeholder for all teams
  return 'https://assets.nhle.com/logos/nhl/svg/EDM_light.svg'
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
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-slate-100 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
    <!-- Header -->
    <div class="border-b bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm sticky top-0 z-10">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Calendar class="size-8 text-slate-700 dark:text-slate-300" />
            <div>
              <h1 class="text-2xl font-semibold text-slate-900 dark:text-white tracking-tight">
                Today's Games
              </h1>
              <p class="text-sm text-slate-600 dark:text-slate-400 mt-0.5">
                {{ new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }) }}
              </p>
            </div>
          </div>
          
          <div class="flex items-center gap-4">
            <div class="hidden sm:flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
              <span class="font-medium">{{ totalCount }}</span>
              <span>{{ totalCount === 1 ? 'game' : 'games' }}</span>
            </div>
            <Button 
              :disabled="loading" 
              variant="outline" 
              size="sm" 
              @click="fetchEvents"
              class="gap-2"
            >
              <RefreshCw :class="['size-4', loading ? 'animate-spin' : '']" />
              <span class="hidden sm:inline">Refresh</span>
            </Button>
          </div>
        </div>
        
        <div v-if="lastUpdated" class="mt-3 text-xs text-slate-500 dark:text-slate-500">
          Last updated: {{ formatDate(lastUpdated) }}
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Error State -->
      <Alert v-if="error" variant="destructive" class="mb-6">
        <AlertTitle>Failed to load games</AlertTitle>
        <AlertDescription>{{ error }}</AlertDescription>
      </Alert>

      <!-- Loading State -->
      <div v-if="loading && !events.length" class="space-y-8">
        <div v-for="n in 4" :key="n">
          <Skeleton class="h-6 w-24 mb-4" />
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="m in 3" :key="m">
              <CardContent class="p-6">
                <Skeleton class="h-4 w-16 mb-4" />
                <div class="space-y-4">
                  <div class="flex items-center gap-3">
                    <Skeleton class="size-12 rounded-full" />
                    <Skeleton class="h-5 w-32" />
                  </div>
                  <div class="flex items-center gap-3">
                    <Skeleton class="size-12 rounded-full" />
                    <Skeleton class="h-5 w-32" />
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      <!-- Games by League -->
      <div v-else class="space-y-10">
        <div v-for="league in leaguePriority" :key="league">
          <template v-if="groupedByLeague[league].length > 0">
            <!-- League Header -->
            <div class="flex items-center gap-3 mb-5">
              <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                {{ league }}
              </h2>
              <Badge variant="secondary" class="text-xs">
                {{ groupedByLeague[league].length }}
              </Badge>
            </div>

            <!-- Games Grid -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <Card 
                v-for="game in groupedByLeague[league]" 
                :key="game.id"
                class="group hover:shadow-lg transition-all duration-200 hover:scale-[1.02] cursor-pointer border-slate-200 dark:border-slate-800"
              >
                <CardContent class="p-6">
                  <!-- Game Header -->
                  <div class="flex items-center justify-between mb-5">
                    <Badge :variant="getStatusBadgeVariant(game.status)" class="text-xs font-medium">
                      {{ game.status.toUpperCase() }}
                    </Badge>
                    <span class="text-sm text-slate-600 dark:text-slate-400 font-medium">
                      {{ formatTime(game.startTime) }}
                    </span>
                  </div>

                  <!-- Teams -->
                  <div class="space-y-4">
                    <!-- Away Team -->
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="size-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center flex-shrink-0 overflow-hidden">
                          <img 
                            :src="getTeamLogo(game.league, game.awayTeam)" 
                            :alt="game.awayTeam"
                            class="size-full object-cover"
                          />
                        </div>
                        <div class="min-w-0 flex-1">
                          <div 
                            :class="[
                              'font-medium truncate text-sm',
                              game.awayScore > game.homeScore && game.status === 'final' 
                                ? 'text-slate-900 dark:text-white' 
                                : 'text-slate-600 dark:text-slate-400'
                            ]"
                          >
                            {{ game.awayTeam }}
                          </div>
                          <div class="text-xs text-slate-500 dark:text-slate-500">Away</div>
                        </div>
                      </div>
                      <div 
                        :class="[
                          'text-2xl font-bold tabular-nums',
                          game.awayScore > game.homeScore && game.status === 'final'
                            ? 'text-slate-900 dark:text-white'
                            : 'text-slate-400 dark:text-slate-600'
                        ]"
                      >
                        {{ game.awayScore }}
                      </div>
                    </div>

                    <!-- Home Team -->
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="size-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center flex-shrink-0 overflow-hidden">
                          <img 
                            :src="getTeamLogo(game.league, game.homeTeam)" 
                            :alt="game.homeTeam"
                            class="size-full object-cover"
                          />
                        </div>
                        <div class="min-w-0 flex-1">
                          <div 
                            :class="[
                              'font-medium truncate text-sm',
                              game.homeScore >= game.awayScore && game.status === 'final'
                                ? 'text-slate-900 dark:text-white'
                                : 'text-slate-600 dark:text-slate-400'
                            ]"
                          >
                            {{ game.homeTeam }}
                          </div>
                          <div class="text-xs text-slate-500 dark:text-slate-500">Home</div>
                        </div>
                      </div>
                      <div 
                        :class="[
                          'text-2xl font-bold tabular-nums',
                          game.homeScore >= game.awayScore && game.status === 'final'
                            ? 'text-slate-900 dark:text-white'
                            : 'text-slate-400 dark:text-slate-600'
                        ]"
                      >
                        {{ game.homeScore }}
                      </div>
                    </div>
                  </div>

                  <!-- Venue (if available) -->
                  <div v-if="game.venue" class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <p class="text-xs text-slate-500 dark:text-slate-500 truncate">
                      {{ game.venue }}
                    </p>
                  </div>

                  <!-- Live Indicator -->
                  <div v-if="game.status === 'live'" class="mt-4 flex items-center gap-2">
                    <span class="relative flex size-2">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full size-2 bg-red-500"></span>
                    </span>
                    <span class="text-xs font-medium text-red-500">LIVE NOW</span>
                  </div>
                </CardContent>
              </Card>
            </div>
          </template>
        </div>

        <!-- No Games State -->
        <div v-if="totalCount === 0" class="text-center py-16">
          <Calendar class="size-16 text-slate-300 dark:text-slate-700 mx-auto mb-4" />
          <h3 class="text-lg font-medium text-slate-900 dark:text-white mb-2">
            No games scheduled
          </h3>
          <p class="text-sm text-slate-600 dark:text-slate-400">
            Check back later for today's games
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}
</style>
