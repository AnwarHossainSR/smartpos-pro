<x-mail::message>
# Daily Sales Report

Hello **{{ $ownerName }}**,

Here is your sales summary for **{{ $date }}**.

<x-mail::panel>
| Metric | Value |
|--------|-------|
| Total sales | {{ $saleCount }} |
| Revenue | ${{ $revenue }} |
| Gross profit | ${{ $profit }} |
</x-mail::panel>

Log in to your SmartPOS dashboard to view full analytics, inventory status, and more.

<x-mail::button :url="config('app.frontend_url', 'https://app.smartpos.io') . '/reports'">
View Full Report
</x-mail::button>

Thanks,
SmartPOS Pro
</x-mail::message>
