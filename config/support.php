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
                    'a' => 'Add items to your cart (or tap Buy Now on a product), go to Checkout, choose Cash on Delivery, then tap Place Order. You can order everything at once or per seller.',
                    'keywords' => ['order', 'buy', 'checkout', 'place', 'cart', 'purchase', 'paano umorder', 'orderin'],
                ],
                [
                    'q' => 'What payment methods do you accept?',
                    'a' => 'Cash on Delivery (COD) only: you pay in cash when your order arrives. No prepayment or e-wallet is needed.',
                    'keywords' => ['payment', 'bayad', 'cod', 'cash', 'gcash', 'wallet', 'card', 'pay'],
                ],
                [
                    'q' => 'Where can I track my order?',
                    'a' => 'Open My Orders to see live statuses: pending → confirmed → processing → ready for delivery → out for delivery → delivered. Open an order for its full timeline.',
                    'keywords' => ['track', 'status', 'nasaan', 'where', 'saan', 'parcel', 'delivery status', ' dumating'],
                ],
                [
                    'q' => 'Can I cancel my order?',
                    'a' => 'Yes, while it is still pending or confirmed (before the seller prepares or ships it). Open the order and tap Cancel — reserved stock is returned automatically. Once it is being prepared or on its way, cancellation is no longer available.',
                    'keywords' => ['cancel', 'kansela', 'void', 'refund cancel', 'ayaw'],
                ],
            ],
        ],
        [
            'category' => 'Shipping & Delivery',
            'items' => [
                [
                    'q' => 'How much is the shipping fee?',
                    'a' => 'A flat ₱50 once per shop/seller in your order — not per item. Ordering 3 items from one shop costs ₱50 shipping; items from 2 different shops cost ₱100.',
                    'keywords' => ['shipping', 'delivery fee', 'ship', 'charge', 'fee', 'sf', 'magkano shipping'],
                ],
                [
                    'q' => 'Who delivers my order?',
                    'a' => 'An INVOIZ rider picks your parcel up from the seller and delivers it to your address. The rider earns a flat ₱50 per delivery.',
                    'keywords' => ['rider', 'deliver', 'courier', 'hatid', 'dedeliver'],
                ],
            ],
        ],
        [
            'category' => 'Vouchers & Discounts',
            'items' => [
                [
                    'q' => 'How do I use a voucher code?',
                    'a' => 'Enter the code in the Voucher field at checkout before placing your order. Fixed vouchers cut a peso amount off, percent vouchers cut a share (up to their cap), and free-shipping vouchers waive the ₱50/shop delivery fee.',
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
                    'a' => 'Tap Register and sign up as a buyer — new registrations always start as buyer accounts. You can also continue with Google.',
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
                    'a' => 'Inspect your order on delivery and keep photos of the item and packaging. Then file a complaint below with your order number — our team reviews it and replies by email. (Store owner: confirm your official returns/refund policy and we will put the exact wording here.)',
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
