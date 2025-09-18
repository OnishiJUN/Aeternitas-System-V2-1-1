<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - @yield('title', 'Dashboard')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen">
        <!-- Sidebar -->
        <x-dashboard.sidebar :user="$user" :activeRoute="$activeRoute ?? 'dashboard'" />

        <!-- Main Content -->
        <div class="lg:ml-72">
            <!-- Top Navigation -->
            <x-dashboard.header :title="$pageTitle ?? 'Dashboard'" :user="$user" />

            <!-- Dashboard Content -->
            <main class="p-3 sm:p-4 lg:p-6 xl:p-8">
                @yield('content')
            </main>
        </div>

        <!-- Sidebar Overlay -->
        <div class="fixed inset-0 z-40 bg-gray-600 bg-opacity-75 hidden" id="sidebar-overlay" onclick="toggleSidebar()"></div>
    </div>

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }

    // Close sidebar on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            if (!sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }
        }
    });

    // Update time every minute
    function updateTime() {
        const timeElement = document.getElementById('current-time');
        const timeElementMobile = document.getElementById('current-time-mobile');
        const now = new Date();
        
        // Use proper timezone for Philippines (Asia/Manila)
        const fullOptions = { 
            timeZone: 'Asia/Manila',
            year: 'numeric', 
            month: 'short', 
            day: 'numeric', 
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true 
        };
        
        const mobileOptions = { 
            timeZone: 'Asia/Manila',
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true 
        };
        
        if (timeElement) {
            timeElement.textContent = now.toLocaleDateString('en-US', fullOptions);
        }
        
        if (timeElementMobile) {
            timeElementMobile.textContent = now.toLocaleTimeString('en-US', mobileOptions);
        }
    }

    // Update time immediately and then every minute
    updateTime();
    setInterval(updateTime, 60000);
    </script>
</body>
</html>
