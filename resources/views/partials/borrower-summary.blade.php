<strong>{{ $allocation->assigned_to }}</strong>
<div class="borrower-meta">{{ $allocation->borrower_type ?? 'Not recorded' }}@if($allocation->borrower_identifier) &middot; {{ $allocation->borrower_identifier }}@endif</div>
<div>{{ $allocation->department?->name }} &middot; {{ $allocation->location }}</div>
