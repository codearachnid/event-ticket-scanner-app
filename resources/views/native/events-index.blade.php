<native:top-bar title="Events" subtitle="{{ $siteName }}" display-mode="large" />

<native:refreshable class="w-full h-full bg-white" @refresh="refresh">
    <native:column class="w-full p-4 gap-3">
        @if ($error !== '')
            <native:column class="w-full p-3 rounded-lg bg-amber-50 border border-amber-200">
                <native:text class="text-sm text-amber-800">{{ $error }}</native:text>
            </native:column>
        @endif

        @forelse ($events as $event)
            <native:pressable native:key="event-{{ $event['id'] }}" ref="event-{{ $event['id'] }}"
                              class="w-full gap-1 p-4 rounded-xl bg-zinc-50 border border-zinc-200"
                              @tap="open({{ $event['id'] }})">
                <native:text class="text-lg font-semibold text-zinc-900">{{ $event['title'] }}</native:text>
                <native:text class="text-sm text-zinc-500">{{ $event['date'] }}@if ($event['venue']) · {{ $event['venue'] }}@endif</native:text>
                <native:row class="w-full items-center justify-between">
                    <native:text class="text-sm text-zinc-600">{{ $event['checked_in_count'] }} / {{ $event['attendee_count'] }} checked in</native:text>
                    <native:icon name="chevron-right" :size="16" class="text-zinc-400" />
                </native:row>
            </native:pressable>
        @empty
            <native:column class="w-full items-center p-8 gap-2">
                <native:text class="text-base text-zinc-500 text-center">No ticketed events found on this site.</native:text>
                <native:text class="text-sm text-zinc-400 text-center">Pull down to refresh.</native:text>
            </native:column>
        @endforelse
    </native:column>
</native:refreshable>
