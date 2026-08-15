<native:top-bar title="{{ $name }}" back />

<native:column class="w-full h-full p-4 gap-4 bg-white">
    <native:column class="w-full gap-1 p-4 rounded-xl bg-zinc-50 border border-zinc-200">
        <native:text class="text-xl font-bold text-zinc-900">{{ $name }}</native:text>
        <native:text class="text-sm text-zinc-500">{{ $email }}</native:text>
        <native:text class="text-sm text-zinc-600">{{ $ticket }} · {{ $provider }}</native:text>
        <native:row class="items-center gap-2">
            <native:text class="text-sm text-zinc-500">Order:</native:text>
            <native:text class="text-sm font-semibold {{ $orderStatus === 'completed' ? 'text-green-700' : 'text-red-600' }}">{{ $orderStatus }}</native:text>
        </native:row>
    </native:column>

    @if ($checkedIn)
        <native:column class="w-full gap-1 p-4 rounded-xl bg-green-50 border border-green-200">
            <native:text class="text-base font-semibold text-green-800">Checked in</native:text>
            @if ($checkedInInfo !== '')
                <native:text class="text-sm text-green-700">{{ $checkedInInfo }}</native:text>
            @endif
        </native:column>

        <native:pressable ref="undo-btn" class="w-full flex-row items-center justify-center p-4 rounded-xl bg-red-50 border border-red-200"
                          @tap="confirmUndo">
            <native:text class="text-base font-semibold text-red-700">Undo check-in</native:text>
        </native:pressable>
    @else
        @if ($eligible)
            <native:pressable ref="checkin-btn" class="w-full flex-row items-center justify-center p-4 rounded-xl bg-green-600"
                              @tap="checkin">
                <native:text class="text-base font-semibold text-white">Check in</native:text>
            </native:pressable>
        @else
            <native:column class="w-full p-4 rounded-xl bg-red-50 border border-red-200">
                <native:text class="text-sm text-red-700">Order is {{ $orderStatus }} — not eligible for check-in.</native:text>
            </native:column>
        @endif
    @endif

    @if ($error !== '')
        <native:column class="w-full p-3 rounded-lg bg-red-50 border border-red-200">
            <native:text class="text-sm text-red-700">{{ $error }}</native:text>
        </native:column>
    @endif
</native:column>
