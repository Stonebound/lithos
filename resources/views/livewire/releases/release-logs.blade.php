<div wire:poll.2s>
    <div class="bg-gray-900 text-gray-100 p-4 rounded-lg font-mono text-sm overflow-y-auto max-h-96">
        @forelse($logs as $log)
            <div class="mb-1">
                <span class="text-gray-500">[{{ $log->created_at->format('H:i:s') }}]</span>
                <span @class([
                    'text-blue-400' => $log->level === 'info',
                    'text-red-400' => $log->level === 'error',
                    'text-yellow-400' => $log->level === 'warning',
                ])>{{ strtoupper($log->level) }}:</span>
                <span>{{ $log->message }}</span>
            </div>
        @empty
            <div class="text-gray-500 italic">No logs yet...</div>
        @endforelse
    </div>
</div>
