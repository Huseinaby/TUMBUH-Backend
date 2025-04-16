@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if (trim($slot) === 'Laravel')
                <img src="data:image/png;base64,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"
                    class="logo" alt="Laravel Logo">
            @else
                {{ $slot }}
            @endif
        </a>
    </td>
</tr>
