<p style="margin:0 0 6px;">{{ $brand['business_name'] ?? 'The88Coffees' }}</p>
@if (! empty($brand['address']))
    <p style="margin:0 0 6px;white-space:pre-line;">{{ $brand['address'] }}</p>
@endif
@if (! empty($brand['phone']) || ! empty($brand['whatsapp']) || ! empty($brand['email']))
    <p style="margin:0;">
        @if (! empty($brand['phone'])) Phone: {{ $brand['phone'] }}@endif
        @if (! empty($brand['phone']) && ! empty($brand['whatsapp'])) · @endif
        @if (! empty($brand['whatsapp'])) WhatsApp: {{ $brand['whatsapp'] }}@endif
        @if ((! empty($brand['phone']) || ! empty($brand['whatsapp'])) && ! empty($brand['email'])) · @endif
        @if (! empty($brand['email'])) {{ $brand['email'] }}@endif
    </p>
@endif
<p style="margin:10px 0 0;color:#9a8573;">This is a transactional message about your account or order.</p>
