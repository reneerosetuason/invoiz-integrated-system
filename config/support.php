<?php

/*
|--------------------------------------------------------------------------
| Customer service, FAQ & chatbot content
|--------------------------------------------------------------------------
| Single source of truth for the Help page (/help) and the site chatbot.
| Edit answers here — no code changes needed. Answers support plain text;
| keep them short (1–3 sentences) so they read well in chat bubbles too.
| Anything marked "confirm with store owner" below still needs YOUR input.
*/
return [

    // ------------------------------------------------------------------
    // Contact channels (TODO: replace placeholders with real details)
    // ------------------------------------------------------------------
    'email' => env('SUPPORT_EMAIL', 'invoizecommerce@gmail.com'),
    'phone' => env('SUPPORT_PHONE', ''), // e.g. '+63 912 345 6789'
    'hours' => env('SUPPORT_HOURS', 'Monday to Saturday, 9:00 AM – 6:00 PM (PHT)'),
    'response_time' => 'within 24 hours on business days',

    // ------------------------------------------------------------------
    // FAQs grouped by topic. `keywords` drive chatbot matching — add
    // synonyms buyers actually type (e.g. "refund", "uli", "balik").
    // ------------------------------------------------------------------
    'faqs' => [
        [
            'category' => 'Orders & Checkout',
            'items' => [
                [
                    'q' => 'How do I place an order?',
                    'a' => 'Add items to your cart or tap Buy Now. At checkout, choose a delivery address and an available payment method, review the total, then place your order. GCash requires a separate receipt for each store.',
                    'keywords' => ['order', 'buy', 'checkout', 'place', 'cart', 'purchase', 'paano umorder', 'orderin'],
                ],
                [
                    'q' => 'What payment methods do you accept?',
                    'a' => 'Choose Cash on Delivery or GCash when available. For GCash, check the seller account name, scan or import the receiving QR in your own GCash app, pay the displayed amount, and upload a readable receipt. Your rider collects no cash for a GCash order.',
                    'keywords' => ['payment', 'bayad', 'cod', 'cash', 'gcash', 'wallet', 'card', 'pay'],
                ],
                [
                    'q' => 'Why is my GCash order awaiting confirmation?',
                    'a' => 'Your seller reviews the receipt and transfers the full payment into platform escrow. Admin verifies the funds before the seller can accept the order. You do not pay the rider again for a GCash order.',
                    'keywords' => ['gcash confirmation', 'escrow', 'receipt review'],
                ],
                [
                    'q' => 'Where can I track my order?',
                    'a' => 'Open Orders and select an order to follow seller preparation, pickup, logistics hub handling, transport and final rider delivery. The order timeline shows the current progress.',
                    'keywords' => ['track', 'status', 'nasaan', 'where', 'saan', 'parcel', 'delivery status', ' dumating'],
                ],
                [
                    'q' => 'Can I cancel my order?',
                    'a' => 'Open the order to check whether cancellation is available. If a prepaid order is cancelled, the refund is reviewed separately; cancelling does not mean the money has already been returned. Confirm a refund only after receiving it.',
                    'keywords' => ['cancel', 'kansela', 'void', 'refund cancel', 'ayaw'],
                ],
            ],
        ],
        [
            'category' => 'Shipping & Delivery',
            'items' => [
                [
                    'q' => 'How much is the shipping fee?',
                    'a' => 'Checkout shows the current delivery fee for each store and the full total before you order. Review that quote after changing quantities, vouchers or delivery details.',
                    'keywords' => ['shipping', 'delivery fee', 'ship', 'charge', 'fee', 'sf', 'magkano shipping'],
                ],
                [
                    'q' => 'Who delivers my order?',
                    'a' => 'The seller prepares your parcel. Pickup riders, logistics hubs and transport staff handle the parcel before a final rider delivers it. Track those handoffs in your order details.',
                    'keywords' => ['rider', 'deliver', 'courier', 'hatid', 'dedeliver'],
                ],
            ],
        ],
        [
            'category' => 'Vouchers & Discounts',
            'items' => [
                [
                    'q' => 'How do product sales work?',
                    'a' => 'A sale shows the current price, regular price and percentage off. Discounts that round to 0% are not listed as sales. When a product discount and a store campaign both apply, you receive the better price instead of stacked discounts. Review the final checkout quote before ordering.',
                    'keywords' => ['sale', 'product sale', 'discount percentage'],
                ],
                [
                    'q' => 'How do I use a voucher code?',
                    'a' => 'Enter a voucher at checkout and apply it before placing the order. The updated quote shows eligible item discounts and delivery savings. Store vouchers apply only to the relevant store.',
                    'keywords' => ['voucher', 'coupon', 'code', 'discount', 'promo', 'sale', 'less', 'bawas'],
                ],
                [
                    'q' => 'Who pays for voucher discounts?',
                    'a' => 'The seller absorbs them: discounts come out of the seller\'s sales. Rider pay is never reduced by vouchers.',
                    'keywords' => ['who pays', 'sagot', 'seller pays', 'rider voucher', 'kaltas'],
                ],
                [
                    'q' => 'Why was my voucher rejected?',
                    'a' => 'Common reasons: the code is expired or inactive, you have not reached its minimum spend, it has reached its usage limit, or (for seller vouchers) none of its shop\'s items are in your order.',
                    'keywords' => ['invalid', 'expired', 'rejected', 'ayaw gumana', 'not working', 'minimum spend', 'min spend'],
                ],
            ],
        ],
        [
            'category' => 'Account',
            'items' => [
                [
                    'q' => 'How do I create an account?',
                    'a' => 'Create a buyer account with your details and delivery address, verify the email code, and wait for administrator approval. The app will explain if verification or review is still pending.',
                    'keywords' => ['register', 'signup', 'sign up', 'account', 'gawa account', 'google'],
                ],
                [
                    'q' => 'I did not receive my verification code.',
                    'a' => 'Check your spam folder, wait a minute, then use Resend on the verification page. Codes expire after 10 minutes.',
                    'keywords' => ['otp', 'code', 'verify', 'verification', 'email', 'resend', 'walang code'],
                ],
            ],
        ],
        [
            'category' => 'Selling on Invoiz',
            'items' => [
                [
                    'q' => 'How do I become a seller?',
                    'a' => 'Create a buyer account first, then open the Sell page and submit your seller application with your business details. The administrator reviews it and the approval is sent to your email.',
                    'keywords' => ['seller', 'sell', 'benta', 'tinda', 'store', 'apply seller', 'maging seller'],
                ],
                [
                    'q' => 'How do low-stock alerts work?',
                    'a' => 'When adding or editing a product, set a "Low Stock Alert At" level (for example 10). When stock drops to that level you get a notification; it clears automatically once you restock above it.',
                    'keywords' => ['stock', 'inventory', 'ubos', 'low stock', 'alert', 'notify', 'restock'],
                ],
            ],
        ],
        [
            'category' => 'Returns, Refunds & Help',
            'items' => [
                [
                    'q' => 'What if my item arrives damaged or wrong?',
                    'a' => 'Keep clear photos of the item and packaging. Open a delivered order and request a return for the affected item and quantity. After approval, logistics arranges collection and tracks the parcel back to the seller. Follow the return progress in Orders. A refund is separate from the physical return; view the transfer proof and confirm only after the money reaches you.',
                    'keywords' => ['return', 'refund', 'damaged', 'wrong', 'sira', 'mali', 'balik', 'sauli', 'reklamo', 'complaint', 'defect', 'missing', 'kulang'],
                ],
                [
                    'q' => 'How do I contact customer service?',
                    'a' => 'Use the contact card on this page (email, hotline, hours), ask the chatbot, message the seller directly from the store page, or file a complaint below for order problems.',
                    'keywords' => ['contact', 'support', 'help', 'hotline', 'tawag', 'number', 'email', 'tulong', 'customer service', 'cs'],
                ],
                [
                    'q' => 'How do I message a seller?',
                    'a' => 'Open the Messages page, or visit the seller\'s store and start a chat. Your conversation history stays in Messages.',
                    'keywords' => ['message', 'chat', 'kausap', 'seller chat', 'usap'],
                ],
            ],
        ],
    ],

    // ------------------------------------------------------------------
    // Chatbot quick-reply chips (shown when the chat opens).
    // ------------------------------------------------------------------
    'quick_replies' => [
        'Track my order',
        'Shipping fee',
        'Use a voucher',
        'Cancel order',
        'Contact support',
    ],

    'bot_name' => 'Invoiz Assistant',
    'bot_greeting' => 'Hi! I\'m the Invoiz Assistant. Ask me about orders, shipping, vouchers, or your account — or tap a topic below.',
    'bot_fallback' => 'I\'m not sure about that yet. Try the FAQ list, file a complaint below with your order number, or contact us directly — we reply within 24 hours on business days.',
];
