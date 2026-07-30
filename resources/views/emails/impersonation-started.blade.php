<x-mail::message>
# Support access to your account

Hello **{{ $ownerName }}**,

A SmartPOS support administrator (**{{ $adminName }}**) has started a temporary,
time-limited session on your account to help with a support request.

<x-mail::panel>
**Reason given:** {{ $reason }}
</x-mail::panel>

The session automatically expires after 30 minutes and is limited to support actions only.
If you did not expect this, contact support immediately.

Thanks,
SmartPOS Pro
</x-mail::message>
