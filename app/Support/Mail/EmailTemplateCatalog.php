<?php

namespace App\Support\Mail;

/**
 * The set of emails a tenant may rewrite, with the variables each one exposes.
 *
 * A tenant can only override a key that appears here, and only ever with the
 * variables listed for it — an editable template that could name an arbitrary
 * binding would be a way to read whatever the mailable happened to pass.
 */
final class EmailTemplateCatalog
{
    public const ORDER_STATUS_LINK = 'order.status_link';

    public const CUSTOMER_PORTAL_MAGIC_LINK = 'customer.portal_magic_link';

    public const DAILY_REPORT = 'report.daily';

    /**
     * @return array<string, array{name: string, description: string, variables: array<string, string>, subject: string, body: string}>
     */
    public static function all(): array
    {
        return [
            self::ORDER_STATUS_LINK => [
                'name' => 'Order status link',
                'description' => 'Sent to a customer with the link to their public order page.',
                'variables' => [
                    'invoice_number' => 'Invoice number of the sale',
                    'seller_name' => 'Your business name',
                    'total' => 'Sale total, formatted',
                    'due' => 'Outstanding amount, formatted',
                    'url' => 'Link to the order status page',
                ],
                'subject' => 'Your order {invoice_number} from {seller_name}',
                'body' => <<<'MD'
                    # Your order {invoice_number}

                    Thanks for shopping with **{seller_name}**.

                    **Total:** {total}

                    You can check the status of this order at any time — no account needed:
                    {url}
                    MD,
            ],
            self::DAILY_REPORT => [
                'name' => 'Daily sales summary',
                'description' => 'Sent to the owner each morning with yesterday’s numbers.',
                'variables' => [
                    'owner_name' => 'Recipient’s name',
                    'date' => 'Day the summary covers',
                    'sale_count' => 'Number of sales',
                    'revenue' => 'Revenue for the day, formatted',
                    'profit' => 'Gross profit for the day, formatted',
                ],
                'subject' => 'Sales summary for {date}',
                'body' => <<<'MD'
                    # Sales summary — {date}

                    Hello **{owner_name}**,

                    - Sales: {sale_count}
                    - Revenue: {revenue}
                    - Profit: {profit}
                    MD,
            ],
        ];
    }

    /** @return array{name: string, description: string, variables: array<string, string>, subject: string, body: string}|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * Placeholder values for the preview pane, so an editor sees a realistic
     * email without a real sale behind it.
     *
     * @return array<string, string>
     */
    public static function sampleVariables(string $key): array
    {
        $samples = [
            self::CUSTOMER_PORTAL_MAGIC_LINK => [
                'customer_name' => 'John Doe',
                'url' => 'https://example.test/portal/auth?token=preview-token',
            ],
            self::ORDER_STATUS_LINK => [
                'invoice_number' => 'INV-000123',
                'seller_name' => 'Demo Store',
                'total' => '1,250.00',
                'due' => '0.00',
                'url' => 'https://example.test/order/preview-token',
            ],
            self::DAILY_REPORT => [
                'owner_name' => 'Alex',
                'date' => '19 Aug 2026',
                'sale_count' => '42',
                'revenue' => '18,400.00',
                'profit' => '5,120.00',
            ],
        ];

        return $samples[$key] ?? [];
    }
}
