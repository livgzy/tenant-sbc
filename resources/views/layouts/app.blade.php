<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body x-data="{ sidebarOpen: false }" class="overflow-hidden">
    
        <div class="flex h-screen overflow-hidden">
            
            <!-- Overlay untuk mobile -->
            <div x-show="sidebarOpen" 
                 x-cloak
                 x-transition:enter="transition-opacity ease-linear duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="transition-opacity ease-linear duration-300" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 z-20 bg-black/30 lg:hidden" 
                 @click="sidebarOpen = false"></div>
    
            <!-- Sidebar -->
            <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" 
            class="fixed inset-y-0 left-0 z-30 w-72 bg-white shadow-2xl lg:static lg:translate-x-0 transition-transform duration-300 ease-in-out">
                
                <div class="flex flex-col h-full">
                    <!-- Logo Area dengan Gradient -->
                    <div class="relative px-6 py-6 bg-gradient-to-r from-orange-500 to-amber-500">
                        <div class="flex items-center justify-between">
                            <a href="/">
                                <img src="{{Illuminate\Support\Facades\Storage::disk('public')->url('logo/logo_ucic.png')}}" 
                                        alt="Logo" 
                                        class="h-14 sm:h-12 w-auto object-contain">
                            </a>
                            <button @click="sidebarOpen = false" class="lg:hidden text-white/80 hover:text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>                    
    
                    <!-- Navigation Menu -->
                    <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">

                        @can('tenant-dashboard-access')
                            <a href="/dashboard"
                               class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all duration-200 group {{ request()->routeIs('home') ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-white shadow-lg' : 'hover:bg-orange-50 hover:text-orange-600' }}">
                                <svg class="w-5 h-5 mr-3 {{ request()->routeIs('home') ? 'text-white' : 'text-gray-400 group-hover:text-orange-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                <span class="font-medium">Dashboard</span>
                            </a>
                    
                            <a href="/dashboard/order"
                               class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all duration-200 group {{ request()->routeIs('dashboard.orders*') ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-white shadow-lg' : 'hover:bg-orange-50 hover:text-orange-600' }}">
                                <svg class="w-5 h-5 mr-3 {{ request()->routeIs('dashboard.orders*') ? 'text-white' : 'text-gray-400 group-hover:text-orange-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                <span class="font-medium">Pesanan Masuk</span>
                                <livewire:order.badge-count />
                            </a>
                    
                            <a href="/dashboard/menu"
                               class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all duration-200 group {{ request()->routeIs('tenant.menu*') ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-white shadow-lg' : 'hover:bg-orange-50 hover:text-orange-600' }}">
                                <svg class="w-5 h-5 mr-3 {{ request()->routeIs('tenant.menu*') ? 'text-white' : 'text-gray-400 group-hover:text-orange-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                                <span class="font-medium">Kelola Menu</span>
                            </a>
                    
                            <a href="/dashboard/report"
                               class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all duration-200 group {{ request()->routeIs('dashboard.reports*') ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-white shadow-lg' : 'hover:bg-orange-50 hover:text-orange-600' }}">
                                <svg class="w-5 h-5 mr-3 {{ request()->routeIs('dashboard.reports*') ? 'text-white' : 'text-gray-400 group-hover:text-orange-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                <span class="font-medium">Riwayat Pesanan</span>
                            </a>
                    
                            <a href="/dashboard/tenant/profile"
                               class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all duration-200 group {{ request()->routeIs('dashboard.tenant.profile') ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-white shadow-lg' : 'hover:bg-orange-50 hover:text-orange-600' }}">
                                <flux:icon.building-storefront class="w-5 h-5 mr-3 {{ request()->routeIs('dashboard.tenant.profile') ? 'text-white' : 'text-gray-400 group-hover:text-orange-500' }}" />
                                <span class="font-medium">Profil Tenant</span>
                            </a>
                        @endcan  
                        @can('tenant-payout-access')
                        <a href="{{ route('tenant.payout') }}"
                           class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all duration-200 group {{ request()->routeIs('tenant.payout*') ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-white shadow-lg' : 'hover:bg-orange-50 hover:text-orange-600' }}">
                            <svg class="w-5 h-5 mr-3 {{ request()->routeIs('tenant.payout*') ? 'text-white' : 'text-gray-400 group-hover:text-orange-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.21 0-4 1.343-4 3s1.79 3 4 3 4 1.343 4 3-1.79 3-4 3m0-12V5m0 14v-2M7 11H5m14 0h-2M8.5 7.5L7 6m9 1.5L17 6"/>
                            </svg>
                            <span class="font-medium">Payout</span>
                        </a>
                        <a href="/payout/payment" 
                        class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all duration-200 group {{ request()->routeIs('payout.payment') ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-white shadow-lg' : 'hover:bg-orange-50 hover:text-orange-600' }}">
                            <flux:icon.credit-card class="w-5 h-5 mr-3 {{ request()->routeIs('payout.payment') ? 'text-white' : 'text-gray-400 group-hover:text-orange-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"/>
                            <span class="font-medium">Metode Pembayaran Payout</span>
                        </a>  
                        <a href="/payout/order" 
                        class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all duration-200 group {{ request()->routeIs('payout.order') ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-white shadow-lg' : 'hover:bg-orange-50 hover:text-orange-600' }}">
                            <svg class="w-5 h-5 mr-3 {{ request()->routeIs('payout.order') ? 'text-white' : 'text-gray-400 group-hover:text-orange-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            <span class="font-medium">Pesanan Masuk</span>
                        </a> 
                        <a href="/payout/report"
                               class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all duration-200 group {{ request()->routeIs('payout.report*') ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-white shadow-lg' : 'hover:bg-orange-50 hover:text-orange-600' }}">
                                <svg class="w-5 h-5 mr-3 {{ request()->routeIs('payout.report*') ? 'text-white' : 'text-gray-400 group-hover:text-orange-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                <span class="font-medium">Riwayat Pesanan</span>
                            </a>
                        @endcan
              
                        @can('reservation-access')
                            <a href="{{ route('tenant.reservation') }}"
                               class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all duration-200 group {{ request()->routeIs('tenant.reservation*') ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-white shadow-lg' : 'hover:bg-orange-50 hover:text-orange-600' }}">
                                <svg class="w-5 h-5 mr-3 {{ request()->routeIs('tenant.reservation*') ? 'text-white' : 'text-gray-400 group-hover:text-orange-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="font-medium">Reservasi</span>
                            </a>
                        @endcan
                    </nav>
                </div>
            </aside>
    
            <!-- Main Content Area -->
            <div class="flex flex-col flex-1 overflow-hidden">
                
                <!-- Top Navigation Bar -->
                <header class="sticky top-0 z-10 bg-white/80 backdrop-blur-md border-b shadow-sm">
                    <div class="flex items-center justify-between px-6 py-3">
                        <div class="flex items-center">
                            <button @click="sidebarOpen = true" class="p-2 text-gray-500 rounded-lg lg:hidden hover:bg-gray-100">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                            </button>
                        
                        </div>
    
                        <div class="flex items-center space-x-3"> 
                            <!-- User Dropdown -->
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" class="flex items-center space-x-2 p-1 rounded-full hover:bg-gray-100 transition">
                                    <img class="w-9 h-9 rounded-full border-2 border-orange-500 object-cover" 
                                         src="https://ui-avatars.com/api/?name={{ urlencode(Auth::guard('tenant')->user()->name ?? 'Tenant') }}&background=F97316&color=fff&bold=true" 
                                         alt="Avatar">
                                    <div class="hidden md:block text-left">
                                        @can('tenant-dashboard-access')
                                        <p class="text-sm font-medium text-gray-800">{{ Auth::guard('tenant')->user()->name ?? 'Tenant User' }}</p>
                                        <p class="text-xs text-gray-500">Tenant</p>
                                        @else
                                        <p class="text-sm font-medium text-gray-800">{{ Auth::guard('tenant')->user()->name ?? 'User' }}</p>
                                        @endcan
                                        
                                    </div>
                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                <div x-show="open" 
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 transform -translate-y-2"
                                     x-transition:enter-end="opacity-100 transform translate-y-0"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100 transform translate-y-0"
                                     x-transition:leave-end="opacity-0 transform -translate-y-2"
                                     @click.away="open = false"
                                     class="absolute right-0 mt-2 w-56 bg-white shadow-lg z-50"
                                     style="display: none;">
                                    <div class="px-4 py-3 border-b bg-gradient-to-r from-orange-50 to-amber-50 rounded-t-xl">
                                        <p class="text-sm font-semibold text-gray-800">{{ Auth::guard('tenant')->user()->name ?? 'Tenant User' }}</p>
                                        <p class="text-xs text-gray-500">{{ Auth::guard('tenant')->user()->email ?? 'tenant@foodcourt.com' }}</p>
                                    </div>
                                    <div class="py-2">
                                        <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Profile</a>
                                    </div>
                                    <form method="POST" action="/logout">
                                        @csrf
                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-b-xl">Logout</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>
    
                <!-- Main Content -->
                <main class="flex-1 overflow-y-auto p-6">
                    {{ $slot }}
                </main>
                <div
                    x-data="{
                        toasts: [],
                        push(message) {
                            const id = Date.now() + Math.random();
                            this.toasts.push({ id, message });
                            setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 3000);
                        }
                    }"
                    x-on:toast.window="push($event.detail.message)"
                    class="fixed bottom-4 right-4 z-50 space-y-2"
                >
                    <template x-for="toast in toasts" :key="toast.id">
                        <div x-show="true" x-transition
                            class="bg-green-500 text-white px-6 py-3 rounded-xl shadow-lg" x-text="toast.message"></div>
                    </template>
                </div>
            </div>
        </div>
        @livewireScripts
    </body>
</html>
