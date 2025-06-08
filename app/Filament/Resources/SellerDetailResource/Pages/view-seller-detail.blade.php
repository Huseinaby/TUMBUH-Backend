<x-filament::page>
    <h2>Detail Seller: {{ $record->store_name }}</h2>

    <p><strong>Username:</strong> {{ $record->user->username }}</p>
    <p><strong>Store Name:</strong> {{ $record->store_name }}</p>
    <p><strong>Store Description:</strong> {{ $record->store_description }}</p>
    <p><strong>Origin:</strong> {{ $record->origin_id }}</p>
    <p><strong>Store Address:</strong> {{ $record->store_address }}</p>
    <p><strong>Phone:</strong> {{ $record->store_phone }}</p>
    <p><strong>Status:</strong> {{ ucfirst($record->status) }}</p>

    <p><strong>Bank Name:</strong> {{ $record->bank_name }}</p>
    <p><strong>Bank Account Number:</strong> {{ $record->bank_account_number }}</p>
    <p><strong>Bank Account Name:</strong> {{ $record->bank_account_name }}</p>

    <div style="margin-top: 20px;">
        <strong>Store Logo:</strong><br>
        @if($record->store_logo)
        <img src="{{ asset('storage/' . $record->store_logo) }}" alt="Store Logo" style="max-width: 300px; cursor: pointer;"
            onclick="window.open('{{ asset('storage/' . $record->store_logo) }}', '_blank')">
    @else
        <p>No Logo</p>
    @endif    
    </div>

    <div style="margin-top: 20px;">
        <strong>Store Banner:</strong><br>
        @if($record->store_banner)
            <img src="{{ asset('storage/' . $record->store_banner) }}" alt="Store Banner" style="max-width: 500px;">
        @else
            <p>No Banner</p>
        @endif
    </div>
</x-filament::page>
