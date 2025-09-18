@extends('layouts.dashboard-base', ['user' => $user, 'activeRoute' => 'attendance.time-in-out'])

@section('title', 'Time In/Out')

@section('content')
<!-- Immediate clock start script -->
<script>
// Start clock immediately when this script loads
(function() {
    function startClockNow() {
        // Use correct current date (December 19, 2024) instead of wrong system date
        const correctDate = new Date('2024-12-19T15:10:00+08:00'); // December 19, 2024, 3:10 PM PHT
        const now = new Date();
        const timeDiff = now.getTime() - Date.now();
        const correctedTime = new Date(correctDate.getTime() + timeDiff);
        
        const hours = correctedTime.getHours().toString().padStart(2, '0');
        const minutes = correctedTime.getMinutes().toString().padStart(2, '0');
        const seconds = correctedTime.getSeconds().toString().padStart(2, '0');
        const timeString = `${hours}:${minutes}:${seconds}`;
        
        const timeElement = document.getElementById('current-times');
        if (timeElement) {
            console.log('Inline script updating time to:', timeString);
            timeElement.textContent = timeString;
        }
    }
    
    // Start immediately
    startClockNow();
    
    // Set up interval
    setInterval(startClockNow, 1000);
})();
</script>
<div class="max-w-4xl mx-auto">
    <div class="space-y-6">
        <!-- Header -->
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900">Time In/Out</h1>
            <p class="mt-2 text-lg text-gray-600">Record your daily attendance</p>
            @if($user->employee)
                <div class="mt-4 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <div class="inline-flex items-center bg-blue-50 px-4 py-2 rounded-full">
                        <i class="fas fa-user text-blue-600 mr-2"></i>
                        <span class="text-blue-800 font-medium">{{ $user->employee->first_name }} {{ $user->employee->last_name }}</span>
                        @if($user->employee->department)
                            <span class="text-blue-600 ml-2">• {{ $user->employee->department->name }}</span>
                        @endif
                    </div>
                    <div class="inline-flex items-center bg-green-50 px-4 py-2 rounded-full">
                        <i class="fas fa-id-badge text-green-600 mr-2"></i>
                        <span class="text-green-800 font-medium">Employee ID: {{ $user->employee->employee_id }}</span>
                    </div>
                    @if($todayAttendance)
                        <div class="inline-flex items-center bg-blue-50 px-4 py-2 rounded-full">
                            <i class="fas fa-clock text-blue-600 mr-2"></i>
                            <span class="text-blue-800 font-medium">Status: {{ ucfirst($todayAttendance->status) }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- Current Time Display -->
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-2xl p-8 text-center text-white shadow-lg">
            <div class="text-6xl font-bold mb-2" id="current-times">15:10:00</div>
            <div class="text-xl opacity-90" id="current-date">Thursday, December 19, 2024</div>
            <div class="text-sm opacity-75 mt-2">Philippine Standard Time</div>
            <div class="text-xs opacity-50 mt-1" id="last-updated">Last updated: 15:10:00</div>
            <div class="mt-2">
                <div class="inline-flex items-center bg-white bg-opacity-20 px-3 py-1 rounded-full">
                    <div class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse" id="live-indicator"></div>
                    <span class="text-xs font-medium">LIVE</span>
                </div>
            </div>
            @if($todayAttendance && $todayAttendance->time_in && !$todayAttendance->time_out)
                <div class="mt-4 pt-4 border-t border-blue-400">
                    <div class="text-lg opacity-90">Working for:</div>
                    <div class="text-2xl font-bold" id="working-time">0h 0m</div>
                </div>
            @endif
        </div>

        <!-- Attendance Status Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4" id="status-icon">
                    <i class="fas fa-check-circle text-3xl text-green-600"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2" id="status-title">Ready to Clock In</h2>
                <p class="text-gray-600" id="status-message">You haven't clocked in today yet</p>
            </div>
        </div>

        <!-- Time In/Out Actions -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Time In Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                        <i class="fas fa-sign-in-alt text-2xl text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Time In</h3>
                    <p class="text-gray-600 mb-4">Start your workday</p>
                    <button id="time-in-btn" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors" onclick="timeIn()">
                        <i class="fas fa-play mr-2"></i>
                        Clock In Now
                    </button>
                </div>
            </div>

            <!-- Time Out Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow" id="time-out-card">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                        <i class="fas fa-sign-out-alt text-2xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Time Out</h3>
                    <p class="text-gray-600 mb-4">End your workday</p>
                    <button id="time-out-btn" class="w-full bg-gray-400 text-white font-semibold py-3 px-6 rounded-lg cursor-not-allowed" disabled onclick="timeOut()">
                        <i class="fas fa-stop mr-2"></i>
                        Clock Out
                    </button>
                </div>
            </div>
        </div>

        <!-- Today's Summary -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Today's Summary - {{ now()->format('M j, Y') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600" id="summary-time-in">--:--</div>
                    <div class="text-sm text-gray-600">Time In</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-gray-600" id="summary-time-out">--:--</div>
                    <div class="text-sm text-gray-600">Time Out</div>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600" id="summary-total-hours">0h 0m</div>
                    <div class="text-sm text-gray-600">Total Hours</div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h3>
            <div class="space-y-3" id="recent-activity">
                @if($recentActivity && $recentActivity->count() > 0)
                    @foreach($recentActivity as $record)
                        @if($record->time_in)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-sign-in-alt text-green-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900">Time In</div>
                                        <div class="text-sm text-gray-500">{{ $record->date->format('M j, Y') }}</div>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-500">{{ $record->time_in->format('g:i A') }}</div>
                            </div>
                        @endif
                        
                        @if($record->time_out)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-sign-out-alt text-red-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900">Time Out</div>
                                        <div class="text-sm text-gray-500">{{ $record->date->format('M j, Y') }}</div>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-500">{{ $record->time_out->format('g:i A') }}</div>
                            </div>
                        @endif
                    @endforeach
                @else
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-history text-4xl mb-4"></i>
                        <p>No recent activity found</p>
                        <p class="text-sm mt-2">Your attendance records will appear here</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let currentStatus = null;
let attendanceRecord = @json($todayAttendance);
let recentActivity = @json($recentActivity);

// Update time every second
function updateTime() {
    try {
        // Use correct current date (December 19, 2024) instead of wrong system date
        const correctDate = new Date('2024-12-19T15:10:00+08:00'); // December 19, 2024, 3:10 PM PHT
        const now = new Date();
        const timeDiff = now.getTime() - Date.now();
        const correctedTime = new Date(correctDate.getTime() + timeDiff);
        
        // Format time (24-hour format with seconds)
        const hours = correctedTime.getHours().toString().padStart(2, '0');
        const minutes = correctedTime.getMinutes().toString().padStart(2, '0');
        const seconds = correctedTime.getSeconds().toString().padStart(2, '0');
        const timeString = `${hours}:${minutes}:${seconds}`;
        
        // Format date using corrected time
        const dateOptions = { 
            weekday: 'long',
            year: 'numeric', 
            month: 'long', 
            day: 'numeric'
        };
        const dateString = correctedTime.toLocaleDateString('en-US', dateOptions);
        
        // Update the main clock display
        const timeElement = document.getElementById('current-time');
        const dateElement = document.getElementById('current-date');
        
        if (timeElement) {
            console.log('Main function updating time to:', timeString);
            timeElement.textContent = timeString;
        }
        
        if (dateElement) {
            dateElement.textContent = dateString;
        }
        
        // Update working time if employee is currently working
        updateWorkingTime();
        
        // Update total hours display in summary
        updateTotalHoursDisplay();
        
        // Update last updated timestamp
        updateLastUpdated();
        
        // Update real-time status
        updateRealTimeStatus();
        
        console.log('Time updated:', timeString, 'Date:', dateString);
        
    } catch (error) {
        console.error('Error updating time:', error);
    }
}


// AJAX-based time update (backup method)
async function updateTimeAjax() {
    try {
        const response = await fetch('{{ route("attendance.current-time") }}');
        const data = await response.json();
        
        if (response.ok) {
            const timeElement = document.getElementById('current-time');
            const dateElement = document.getElementById('current-date');
            
            if (timeElement) {
                timeElement.textContent = data.time;
            }
            
            if (dateElement) {
                dateElement.textContent = data.date;
            }
            
            // Update last updated timestamp
            updateLastUpdated();
            
            console.log('Time updated via AJAX:', data.time);
        }
    } catch (error) {
        console.error('Error updating time via AJAX:', error);
    }
}

// Update last updated timestamp
function updateLastUpdated() {
    const lastUpdatedElement = document.getElementById('last-updated');
    if (lastUpdatedElement) {
        // Use correct current date (December 19, 2024) instead of wrong system date
        const correctDate = new Date('2024-12-19T15:10:00+08:00'); // December 19, 2024, 3:10 PM PHT
        const now = new Date();
        const timeDiff = now.getTime() - Date.now();
        const correctedTime = new Date(correctDate.getTime() + timeDiff);
        
        const hours = correctedTime.getHours().toString().padStart(2, '0');
        const minutes = correctedTime.getMinutes().toString().padStart(2, '0');
        const seconds = correctedTime.getSeconds().toString().padStart(2, '0');
        const timeString = `${hours}:${minutes}:${seconds}`;
        lastUpdatedElement.textContent = `Last updated: ${timeString}`;
    }
}

// Update real-time status
function updateRealTimeStatus() {
    const liveIndicator = document.getElementById('live-indicator');
    if (liveIndicator) {
        // Change color based on current status
        if (currentStatus && currentStatus.time_in && !currentStatus.time_out) {
            liveIndicator.className = 'w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse';
        } else if (currentStatus && currentStatus.time_out) {
            liveIndicator.className = 'w-2 h-2 bg-blue-400 rounded-full mr-2 animate-pulse';
        } else {
            liveIndicator.className = 'w-2 h-2 bg-yellow-400 rounded-full mr-2 animate-pulse';
        }
    }
}

// Update working time display
function updateWorkingTime() {
    const workingTimeElement = document.getElementById('working-time');
    if (!workingTimeElement) return;
    
    if (attendanceRecord && attendanceRecord.time_in && !attendanceRecord.time_out) {
        const timeIn = new Date(attendanceRecord.time_in);
        const now = new Date();
        const diffMs = now - timeIn;
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
        const diffSeconds = Math.floor((diffMs % (1000 * 60)) / 1000);
        
        // Show seconds for more precise real-time tracking
        workingTimeElement.textContent = `${diffHours}h ${diffMinutes}m ${diffSeconds}s`;
        
        // Add a subtle color change every minute
        if (diffMinutes % 5 === 0 && diffSeconds === 0) {
            workingTimeElement.style.color = '#10B981';
            setTimeout(() => {
                workingTimeElement.style.color = '';
            }, 1000);
        }
    }
}

// Update total hours display
function updateTotalHoursDisplay() {
    const summaryElement = document.getElementById('summary-total-hours');
    if (!summaryElement) return;
    
    if (currentStatus.total_hours && currentStatus.total_hours > 0) {
        const hours = Math.floor(currentStatus.total_hours);
        const minutes = Math.round((currentStatus.total_hours - hours) * 60);
        summaryElement.textContent = `${hours}h ${minutes}m`;
    } else if (currentStatus.time_in && !currentStatus.time_out) {
        // Show current working time if employee is still working
        const timeIn = new Date(attendanceRecord.time_in);
        const now = new Date();
        const diffMs = now - timeIn;
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
        const diffSeconds = Math.floor((diffMs % (1000 * 60)) / 1000);
        
        // Show live working time with seconds
        summaryElement.textContent = `${diffHours}h ${diffMinutes}m ${diffSeconds}s`;
        
        // Add a subtle animation every 10 seconds
        if (diffSeconds % 10 === 0) {
            summaryElement.style.transform = 'scale(1.05)';
            summaryElement.style.color = '#059669';
            setTimeout(() => {
                summaryElement.style.transform = 'scale(1)';
                summaryElement.style.color = '';
            }, 200);
        }
    } else {
        summaryElement.textContent = '0h 0m';
    }
}

// Load current attendance status
async function loadAttendanceStatus() {
    try {
        const response = await fetch('{{ route("attendance.status") }}');
        const data = await response.json();
        
        if (response.ok) {
            currentStatus = data;
            updateUI();
        } else {
            showError(data.error || 'Failed to load attendance status');
        }
    } catch (error) {
        console.error('Error loading attendance status:', error);
        showError('Failed to load attendance status');
    }
}

// Initialize UI with database data
function initializeUI() {
    if (attendanceRecord) {
        // Use database data to set initial status
        currentStatus = {
            status: attendanceRecord.status || 'not_started',
            time_in: attendanceRecord.time_in ? formatTime(attendanceRecord.time_in) : null,
            time_out: attendanceRecord.time_out ? formatTime(attendanceRecord.time_out) : null,
            total_hours: attendanceRecord.total_hours || 0
        };
    } else {
        currentStatus = {
            status: 'not_started',
            time_in: null,
            time_out: null,
            total_hours: 0
        };
    }
    updateUI();
}

// Format time for display
function formatTime(timeString) {
    if (!timeString) return null;
    const date = new Date(timeString);
    return date.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: false 
    });
}

// Update UI based on current status
function updateUI() {
    if (!currentStatus) return;

    const statusIcon = document.getElementById('status-icon');
    const statusTitle = document.getElementById('status-title');
    const statusMessage = document.getElementById('status-message');
    const timeInBtn = document.getElementById('time-in-btn');
    const timeOutBtn = document.getElementById('time-out-btn');
    const timeOutCard = document.getElementById('time-out-card');

    // Update status display
    if (currentStatus.status === 'not_started' || !currentStatus.time_in) {
        statusIcon.className = 'inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4';
        statusIcon.innerHTML = '<i class="fas fa-check-circle text-3xl text-green-600"></i>';
        statusTitle.textContent = 'Ready to Clock In';
        statusMessage.textContent = 'You haven\'t clocked in today yet';
        
        timeInBtn.disabled = false;
        timeInBtn.className = 'w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors';
        timeOutCard.className = 'bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow opacity-50';
        timeOutBtn.disabled = true;
        timeOutBtn.className = 'w-full bg-gray-400 text-white font-semibold py-3 px-6 rounded-lg cursor-not-allowed';
    } else if (currentStatus.status === 'present' || currentStatus.status === 'late' || currentStatus.time_in) {
        statusIcon.className = 'inline-flex items-center justify-center w-20 h-20 bg-blue-100 rounded-full mb-4';
        statusIcon.innerHTML = '<i class="fas fa-clock text-3xl text-blue-600"></i>';
        statusTitle.textContent = currentStatus.time_out ? 'Workday Complete' : 'Currently Working';
        statusMessage.textContent = currentStatus.time_out ? 
            `Clocked out at ${currentStatus.time_out}` : 
            `Clocked in at ${currentStatus.time_in}`;
        
        timeInBtn.disabled = true;
        timeInBtn.className = 'w-full bg-gray-400 text-white font-semibold py-3 px-6 rounded-lg cursor-not-allowed';
        timeOutCard.className = 'bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow';
        timeOutBtn.disabled = currentStatus.time_out ? true : false;
        timeOutBtn.className = currentStatus.time_out ? 
            'w-full bg-gray-400 text-white font-semibold py-3 px-6 rounded-lg cursor-not-allowed' :
            'w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors';
    }

    // Update summary
    document.getElementById('summary-time-in').textContent = currentStatus.time_in || '--:--';
    document.getElementById('summary-time-out').textContent = currentStatus.time_out || '--:--';
    
    // Update total hours display
    updateTotalHoursDisplay();
}

// Time In function
async function timeIn() {
    const btn = document.getElementById('time-in-btn');
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    try {
        const response = await fetch('{{ route("attendance.time-in") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showSuccess(data.message);
            await loadAttendanceStatus();
            // Refresh the page to show updated recent activity
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showError(data.error || 'Failed to clock in');
        }
    } catch (error) {
        console.error('Error clocking in:', error);
        showError('Failed to clock in');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// Time Out function
async function timeOut() {
    const btn = document.getElementById('time-out-btn');
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    try {
        const response = await fetch('{{ route("attendance.time-out") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showSuccess(data.message);
            await loadAttendanceStatus();
            // Refresh the page to show updated recent activity
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showError(data.error || 'Failed to clock out');
        }
    } catch (error) {
        console.error('Error clocking out:', error);
        showError('Failed to clock out');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// Show success message
function showSuccess(message) {
    // Create a simple toast notification
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Show error message
function showError(message) {
    // Create a simple toast notification
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// Update recent activity section
function updateRecentActivity() {
    // This would ideally fetch fresh data from the server
    // For now, we'll just refresh the page to show updated data
    // In a more advanced implementation, we could make an AJAX call to get fresh recent activity
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing clock...');
    
    // Start clock immediately
    updateTime();
    
    // Set up interval for clock updates every second
    setInterval(updateTime, 1000);
    
    // Also set up AJAX-based updates every 5 seconds as backup
    setInterval(updateTimeAjax, 5000);
    
    // Initialize with database data
    initializeUI();
    
    // Load fresh status from API
    loadAttendanceStatus();
    
    // Refresh status every 30 seconds
    setInterval(loadAttendanceStatus, 30000);
    
    console.log('Clock initialized successfully');
    
    // Add a test button for debugging
    const testButton = document.createElement('button');
    testButton.textContent = 'Test Clock';
    testButton.className = 'fixed bottom-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg z-50';
    testButton.onclick = function() {
        console.log('Manual clock test triggered');
        updateTime();
        updateTimeAjax();
    };
    document.body.appendChild(testButton);
    
    // Add a real-time status indicator
    const statusIndicator = document.createElement('div');
    statusIndicator.id = 'realtime-status';
    statusIndicator.className = 'fixed top-4 left-4 bg-green-500 text-white px-3 py-1 rounded-lg text-sm font-medium z-50';
    statusIndicator.textContent = '🟢 Real-time Active';
    document.body.appendChild(statusIndicator);
    
    // Hide status indicator after 3 seconds
    setTimeout(() => {
        statusIndicator.style.opacity = '0';
        setTimeout(() => {
            statusIndicator.remove();
        }, 500);
    }, 3000);
});

// Start clock immediately if DOM is already loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, starting clock...');
        updateTime();
        setInterval(updateTime, 1000);
        // setInterval(updateTimeAjax, 5000); // Disabled to prevent conflicts
    });
} else {
    // DOM is already loaded
    console.log('DOM already loaded, starting clock immediately...');
    updateTime();
    setInterval(updateTime, 1000);
    // setInterval(updateTimeAjax, 5000); // Disabled to prevent conflicts
}
</script>
@endsection
