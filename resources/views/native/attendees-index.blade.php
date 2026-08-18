<native:top-bar title="Attendees" subtitle="{{ $checkedInCount }} / {{ $totalCount }} checked in" back />

<native:column class="w-full h-full bg-white">
    <native:column class="w-full p-4 gap-3">
        <native:outlined-text-input native:model.debounce.300ms="query" label="Search"
                                    placeholder="Name or email" />

        <native:row class="w-full gap-2">
            @foreach (['all' => 'All', 'in' => 'Checked in', 'out' => 'Not checked in'] as $key => $label)
                <native:pressable native:key="filter-{{ $key }}" ref="filter-{{ $key }}"
                                  class="flex-row px-4 py-2 rounded-full {{ $filter === $key ? 'bg-blue-600' : 'bg-zinc-100' }}"
                                  @tap="setFilter('{{ $key }}')">
                    <native:text class="text-sm font-semibold {{ $filter === $key ? 'text-white' : 'text-zinc-700' }}">{{ $label }}</native:text>
                </native:pressable>
            @endforeach
        </native:row>
    </native:column>

    <native:scroll-view class="flex-1 w-full">
        <native:column class="w-full px-4 gap-0">
            @forelse ($rows as $row)
                <native:pressable native:key="attendee-{{ $row['id'] }}" ref="attendee-{{ $row['id'] }}"
                                  class="w-full flex-row items-center justify-between py-3 border-b border-zinc-100"
                                  @tap="open({{ $row['id'] }})">
                    <native:column class="flex-1 gap-1">
                        <native:text class="text-base font-semibold text-zinc-900">{{ $row['name'] }}</native:text>
                        <native:text class="text-sm text-zinc-500">{{ $row['ticket'] }} · {{ $row['email'] }}</native:text>
                    </native:column>
                    @if ($row['checked_in'])
                        <native:column class="px-3 py-1 rounded-full bg-green-100">
                            <native:text class="text-xs font-semibold text-green-700">IN</native:text>
                        </native:column>
                    @else
                        <native:column class="px-3 py-1 rounded-full bg-zinc-100">
                            <native:text class="text-xs font-semibold text-zinc-500">—</native:text>
                        </native:column>
                    @endif
                </native:pressable>
            @empty
                <native:column class="w-full items-center p-8">
                    <native:text class="text-base text-zinc-500 text-center">No attendees match.</native:text>
                </native:column>
            @endforelse
        </native:column>
    </native:scroll-view>
</native:column>
