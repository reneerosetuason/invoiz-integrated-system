# INVOIZ Buyer — theme & design system

Extracted from the live buyer site: `buyer/layouts/website.blade.php`, `assets/shared/css/shop.css` (`.shopx`),
`theme.css`, `app-sidebar.css`, `buyer/css/layouts/website.css`, `landing.css`, `profile.css`, and the landing
Tailwind config in `assets/buyer/js/landing-2.js`.

## Prompt (copy-paste)

> Design a premium Philippine marketplace storefront called **INVOIZ**. Art direction: **quiet boutique
> marketplace** — warm ivory paper, deep teal ink, an editorial serif for headlines over a neutral UI sans.
> Flat fills and hairline rules; shadows appear only on hover or on floating elements. Cash-on-delivery is the
> hero promise. Nothing glossy, nothing gradient-heavy, no purple SaaS look.
>
> **Palette (use exactly).** Deep teal `#0E4A57` = primary actions, headlines, active states, badges.
> Teal `#16697A` = brand accents, icons, links. Pale teal `#EAF4F3` = icon wells, avatars, discount chips.
> Mist `#EDF3F1` = hero ground, image fallbacks, empty-state circles. Ivory `#FBF9F6` = navbar, footer,
> elevated cards. Page ground `#F7F6F2`. Cream `#F3F0E9` = product image wells, neutral chips.
> Ink `#1B1B1E` = body. Muted `#6E6E73` (secondary) and `#56696B` (newer pages). Paragraphs `#40585C`,
> strong body `#263C40`. Hairlines `rgba(15,76,92,.12–.14)`; input borders `rgba(15,76,92,.18)`.
> Amber `#F0A202` is a 2% accent only (rating stars, discount flag, chat dot). Success `#10B981` /
> alert success `#ECFDF5` + `#065F46`; error `#EF4444`, brick text `#B3261E`, alert error `#FEF2F2` + `#991B1B`.
> Aim for roughly 70% ivory/white, 20% teal tints, 8% deep teal, 2% amber.
>
> **Type.** Display = **Fraunces** (opsz 9–144), weight **560**, tracking −0.01 to −0.02em, line-height 1.05–1.15.
> Body/UI = **Inter** 400/500/600/700/800 at 13–15px, leading 1.5–1.7. Eyebrows: 11px / 700 / uppercase /
> 0.18em tracking / `rgba(14,74,87,.72)`, optionally preceded by a 7px solid teal square. Section headings
> `clamp(24px,2.4vw,32px)` serif; hero `clamp(30px,3.4vw,44px)`; product title `clamp(32px,3.6vw,44px)`.
> Feature-page h1 28px (24px mobile). Prices: Inter 800, deep teal, `font-variant-numeric: tabular-nums`
> (card 16px, hero/detail 32px, totals 16–28px). Brand micro-labels 10–11px uppercase, 0.12em.
>
> **Layout.** Content max 1280px with 24px gutters (16px under 640); feature pages 1120px, landing 1200px,
> profile 960px. Product grid 4-up, 24px gap (3-up ≤1024, 2-up ≤640, 16px gap). Product detail 7fr/5fr with
> 48px gap, sticky gallery at top 84px. Cart 1fr + 320px sticky summary. Section rhythm 26–56px.
> Breakpoints 1024 / 900 / 860 / 640 / 380.
>
> **Surfaces.** Radii: 999 pills and inputs · 24 hero · 20 gallery/vcard/modal · 16 cards and panels ·
> 14 product cards · 12 thumbnails/steppers/rows · 10 nav items · 8 small chips. Borders are 1px hairlines;
> no resting shadows. Hover shadow `0 14px 34px -18px rgba(10,47,58,.35)`; dropdown `0 10px 30px rgba(10,47,58,.18)`;
> modal `0 24px 60px -20px rgba(10,47,58,.45)`.
>
> **Components.** Buttons are 999px pills, min-height 48px, 12×26 padding, 14px/700; solid = `#0E4A57`
> (hover `#0B3D48`), outline = white with `rgba(15,76,92,.3)` border that darkens on hover; press = `scale(.98)`.
> Small variant 36–44px, 8×16. Filter chips 999px, 9×20, 13/600, white + hairline, active = deep teal fill/white text.
> Search fields 42–44px tall, pill, warm `#F0EEE9` fill, focus → white with `rgba(22,105,122,.4)` border.
> Form inputs 44px, 10px radius, `#FBF9F6` fill, 1px `rgba(15,76,92,.18)`, focus = 2px solid `#0E4A57` outline, offset 2.
> Cards: white, hairline, 14–16px radius, zero shadow at rest; hover translateY(−3/−4px) + shadow; images on
> `#F3F0E9` wells at 4:5 (gallery 4:3), hover scale(1.04).
> Product card: image, body 14×16, brand 10.5 uppercase .12em muted, serif title 16.5 clamped to 2 lines,
> price 16/800 tabular, struck was-price 12.5, discount pill 11/800 on `#EAF4F3`, meta row "12 left · ★ 4.8 · COD".
> Navbar 60px ivory, hairline bottom: 34px rounded-10 white logo tile, "INVOIZ" 17/800 with 10px 0.14em
> "ONLINE SHOPPING" sub-label, pill search centre, 13/600 teal links with 10px hover wash, outline Login pill
> and solid Register pill.
> Sidebar 256px (72px collapsed rail), ivory→mist vertical gradient, hairline right; 11/700 0.18em section
> labels; 40–44px items, 10px radius, 18px 1.5-stroke icons at 65% teal; hover 5% wash + 2px icon nudge;
> active 10% wash + 3px deep-teal left bar; pinned 56px deep-teal footer pill (radius 20).
> Status chips: 11.5/700, 4×12, 999px, tinted ground, 6px dot in currentColor (shipping `#F4F0E6`,
> in-transit/done `#EAF4F3`, cancelled `#F3F0E9` + `#B3261E`). Payment chip is hairline outline with `#C9C2B2` dot,
> turning deep teal when paid. Coupons use dashed `rgba(15,76,92,.4)` borders on ivory.
> Empty states: centred, 56–60px pale-teal circle with a 1.5-stroke icon, serif title 20–22, muted 13–14 line, pill CTA.
> Chat: own bubbles deep teal/white with a 6px tail corner, other bubbles white on `#F5F4F0`, 16px radius, 11.5px time.
> Two-pane messages: 280–340px `#F0F1ED` list pane + white thread, 16px radius, selected row 3px left bar.
>
> **Motion.** Single ease `cubic-bezier(.22,1,.36,1)`. Micro-interactions 150–250ms; entrance 320–600ms;
> image reveals ~900ms. On-scroll reveal = 12px rise + fade, staggered 60ms. Link underlines scaleX 0→1 from the
> left; arrows nudge 2–4px. Full `prefers-reduced-motion` fallback: no transforms, content visible.
>
> **Imagery & icons.** Warm, natural-light product photography on cream wells, 4:5 crops, no frames. When an
> image is missing, show the serif "INVOIZ" mark on mist with "Product image coming soon". Icons are 1.5px
> stroke line icons, 16–20px, `currentColor`. Gradients are allowed in exactly three places: hero wash
> `#FDFCF8 → #F4F0E7 → #E2EEEB`, sidebar `180deg #FBF9F6 → #EDF3F1`, legacy chat header `135deg #16697A → #0E4A57`.
>
> **Voice.** Short, plain, confident: "Curated for you", "Cash on delivery nationwide",
> "Pay at your door, no prepayment.", "Rider-tracked delivery", "Easy returns".
> Never exclamation marks; never hype; sentence case in UI, uppercase only for eyebrows and micro-labels.
