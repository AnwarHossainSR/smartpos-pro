<x-mail::message>
# Your order {{ $invoiceNumber }}

Thanks for shopping with **{{ $sellerName }}**.

<x-mail::panel>
**Total:** {{ $total }}
@if ($hasDue)
**Outstanding:** {{ $due }}
@endif
</x-mail::panel>

You can check the status of this order at any time — no account needed.

<x-mail::button :url="$url">
View order status
</x-mail::button>

Thanks,
{{ $sellerName }}
</x-mail::message>
