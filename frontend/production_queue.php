<?php
$pageTitle = "Production Queue";
include __DIR__ . '/components/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <div x-data="productionQueue()" x-init="init()" class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">🎬 Production Queue</h1>
            <p class="text-gray-600">Sequential video production - one at a time</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500 mb-1">Current Job</div>
                <div class="text-2xl font-bold text-blue-600" x-text="status.current_job || 'None'"></div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500 mb-1">Queue Length</div>
                <div class="text-2xl font-bold text-indigo-600" x-text="status.queue_length"></div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500 mb-1">Completed</div>
                <div class="text-2xl font-bold text-green-600" x-text="status.stats.total_completed"></div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500 mb-1">Failed</div>
                <div class="text-2xl font-bold text-red-600" x-text="status.stats.total_failed"></div>
            </div>
        </div>

        <!-- Current Production -->
        <template x-if="status.current_job">
            <div class="bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg shadow-lg p-6 mb-8 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-medium opacity-90 mb-1">🎬 Currently Processing</div>
                        <div class="text-xl font-bold" x-text="status.current_job"></div>
                    </div>
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-white"></div>
                </div>
            </div>
        </template>

        <!-- Queue -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Waiting Jobs</h2>
            </div>
            
            <template x-if="status.queue.length === 0">
                <div class="px-6 py-12 text-center text-gray-500">
                    <div class="text-5xl mb-4">💤</div>
                    <div class="text-lg font-medium">Queue is empty</div>
                    <div class="text-sm">Add videos from the dashboard to start production</div>
                </div>
            </template>

            <template x-if="status.queue.length > 0">
                <div class="divide-y divide-gray-200">
                    <template x-for="(item, index) in status.queue" :key="item.job_id">
                        <div class="px-6 py-4 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold" x-text="item.position"></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-gray-900" x-text="item.job_id"></div>
                                            <div class="text-sm text-gray-500" x-text="item.job_data?.url || 'No URL'"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
                                            :class="{
                                                'bg-yellow-100 text-yellow-800': item.status === 'waiting',
                                                'bg-blue-100 text-blue-800': item.status === 'processing',
                                                'bg-green-100 text-green-800': item.status === 'completed',
                                                'bg-red-100 text-red-800': item.status === 'failed'
                                            }"
                                            x-text="item.status">
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Priority: <span class="font-medium" x-text="item.priority"></span>
                                    </div>
                                    <button 
                                        @click="removeJob(item.job_id)"
                                        class="text-red-600 hover:text-red-800 text-sm font-medium"
                                        :disabled="item.status === 'processing'">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <!-- Settings -->
        <div class="mt-8 bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Settings</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <div class="text-gray-500">Auto Start Next</div>
                    <div class="font-medium" x-text="status.settings.auto_start_next ? '✅ Enabled' : '❌ Disabled'"></div>
                </div>
                <div>
                    <div class="text-gray-500">Max Retries</div>
                    <div class="font-medium" x-text="status.settings.max_retries"></div>
                </div>
                <div>
                    <div class="text-gray-500">Retry Delay</div>
                    <div class="font-medium" x-text="status.settings.retry_delay_seconds + 's'"></div>
                </div>
            </div>
        </div>

        <!-- Info Banner -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-blue-800">Sequential Production</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>Videos are produced one at a time to ensure system stability and prevent resource conflicts. When you create a video, it's added to this queue and will be processed when it reaches the front of the line.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function productionQueue() {
    return {
        status: {
            current_job: null,
            queue_length: 0,
            queue: [],
            stats: {
                total_queued: 0,
                total_processed: 0,
                total_completed: 0,
                total_failed: 0
            },
            settings: {
                auto_start_next: true,
                max_retries: 3,
                retry_delay_seconds: 60
            }
        },
        refreshInterval: null,

        init() {
            this.loadStatus();
            // Auto-refresh every 5 seconds
            this.refreshInterval = setInterval(() => this.loadStatus(), 5000);
        },

        async loadStatus() {
            try {
                const response = await fetch('/api/production_queue.php?action=status');
                const data = await response.json();
                
                if (data.success) {
                    this.status = data;
                }
            } catch (error) {
                console.error('Failed to load queue status:', error);
            }
        },

        async removeJob(jobId) {
            if (!confirm(`Remove job ${jobId} from queue?`)) {
                return;
            }

            try {
                const response = await fetch(`/api/production_queue.php?action=remove&job_id=${jobId}`, {
                    method: 'DELETE'
                });
                const data = await response.json();
                
                if (data.success) {
                    await this.loadStatus();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                console.error('Failed to remove job:', error);
                alert('Failed to remove job from queue');
            }
        }
    }
}
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
