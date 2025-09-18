@props(['user'])

<div class="flex items-center justify-between h-20 px-6 bg-gradient-to-r from-blue-600 to-blue-700 shadow-lg">
    <div class="flex items-center">
        <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
            <i class="fas fa-users text-white text-xl"></i>
        </div>
        <div>
            <h1 class="text-white font-bold text-lg">{{ ucfirst($user->role) }} Dashboard</h1>
            <p class="text-blue-100 text-sm">Welcome back!</p>
        </div>
    </div>
    <button class="lg:hidden text-white/80 hover:text-white transition-colors p-2 rounded-lg hover:bg-white/10" onclick="toggleSidebar()">
        <i class="fas fa-times text-lg"></i>
    </button>
</div>
