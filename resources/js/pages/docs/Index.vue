<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookOpen, ChevronDown, ExternalLink } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import CodeBlock from '@/components/docs/CodeBlock.vue';

const props = defineProps<{
    apiBase: string;
    homeUrl: string;
    dashboardUrl: string;
    loginUrl: string;
    termsUrl: string;
    privacyUrl: string;
}>();

const page = usePage<{ auth?: { user?: { name?: string } | null } }>();

const isAuthenticated = computed(() => Boolean(page.props.auth?.user));

const appName = computed(() => String(page.props.name ?? 'OneNetly'));

const navGroups = [
    {
        title: 'Start here',
        items: [
            { id: 'overview', title: 'Overview' },
            { id: 'concepts', title: 'Core concepts' },
            { id: 'getting-started', title: 'Getting started' },
        ],
    },
    {
        title: 'Restaurant',
        items: [
            { id: 'dashboard', title: 'Dashboard' },
            { id: 'menu-builder', title: 'Menu builder' },
            { id: 'templates', title: 'Menu templates' },
            { id: 'public-menu', title: 'Public menu' },
            { id: 'orders', title: 'Orders' },
            { id: 'qr', title: 'QR codes' },
            { id: 'service-requests', title: 'Guest requests' },
            { id: 'pos-overview', title: 'POS integration' },
            { id: 'pos-auth', title: 'Tokens & auth' },
            { id: 'api-menu', title: 'API: menu' },
            { id: 'api-orders', title: 'API: orders' },
            { id: 'api-order-update', title: 'API: order status' },
            { id: 'api-items', title: 'API: availability' },
            { id: 'webhooks', title: 'Webhooks' },
            { id: 'signatures', title: 'Signatures' },
            { id: 'errors', title: 'API errors' },
        ],
    },
    {
        title: 'BioLink',
        items: [
            { id: 'biolink-overview', title: 'Overview' },
            { id: 'biolink-page', title: 'Page settings' },
            { id: 'biolink-links', title: 'Links' },
            { id: 'biolink-templates', title: 'Templates & themes' },
            { id: 'biolink-social', title: 'Social icons & vCard' },
            { id: 'biolink-contact', title: 'Contact form' },
            { id: 'biolink-privacy', title: 'Password & SEO' },
            { id: 'biolink-domain', title: 'Custom domain' },
            { id: 'biolink-analytics', title: 'Analytics & QR' },
        ],
    },
    {
        title: 'Reference',
        items: [
            { id: 'reference', title: 'Reference tables' },
            { id: 'security', title: 'Security & limits' },
            { id: 'faq', title: 'FAQ' },
        ],
    },
];

const curlMenu = `curl -H "Authorization: Bearer pos_your_token" \\
  ${props.apiBase}/menu`;

const curlOrders = `curl -H "Authorization: Bearer pos_your_token" \\
  "${props.apiBase}/orders?status=new&limit=25"`;

const curlOrderUpdate = `curl -X PATCH \\
  -H "Authorization: Bearer pos_your_token" \\
  -H "Content-Type: application/json" \\
  -d '{"status":"preparing"}' \\
  ${props.apiBase}/orders/ORD-AB12CD`;

const curlItemUpdate = `curl -X PATCH \\
  -H "Authorization: Bearer pos_your_token" \\
  -H "Content-Type: application/json" \\
  -d '{"is_available":false}' \\
  ${props.apiBase}/items/10`;

const menuResponse = `{
  "restaurant": {
    "username": "joes-pizza",
    "name": "Joe's Pizza",
    "currency": "USD",
    "ordering_enabled": true,
    "accepting_orders": true,
    "menu_url": "https://your-app.test/restaurant/joes-pizza"
  },
  "categories": [
    {
      "id": 1,
      "name": "Pizza",
      "description": "Wood-fired, 12 inch",
      "is_active": true,
      "items": [
        {
          "id": 10,
          "name": "Margherita",
          "description": "Tomato, mozzarella, basil",
          "price": "12.50",
          "price_cents": 1250,
          "dietary": "V",
          "allergens": ["gluten", "milk"],
          "is_available": true,
          "is_featured": true,
          "option_groups": [
            {
              "id": 3,
              "name": "Size",
              "is_required": true,
              "allow_multiple": false,
              "options": [
                {
                  "id": 8,
                  "name": "Large",
                  "price": "2.00",
                  "price_cents": 200,
                  "is_available": true
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}`;

const ordersResponse = `{
  "data": [
    {
      "id": 42,
      "reference": "ORD-AB12CD",
      "status": "new",
      "status_label": "New",
      "type": "dine_in",
      "type_label": "Dine in",
      "table_number": "7",
      "delivery_address": null,
      "customer_name": "Maya Patel",
      "customer_phone": "+1 555 0102",
      "customer_email": "maya@example.com",
      "notes": "No parmesan",
      "subtotal_cents": 2500,
      "coupon_code": null,
      "discount_cents": 0,
      "delivery_fee_cents": 0,
      "service_cents": 0,
      "tax_cents": 0,
      "tip_cents": 0,
      "total_cents": 2500,
      "currency": "USD",
      "created_at": "2026-11-25T12:00:00+00:00",
      "updated_at": "2026-11-25T12:00:00+00:00",
      "items": [
        {
          "menu_item_id": 10,
          "name": "Margherita",
          "quantity": 2,
          "unit_price_cents": 1250,
          "line_total_cents": 2500,
          "notes": null,
          "options": [
            { "group": "Size", "name": "Large", "price_cents": 200 }
          ]
        }
      ]
    }
  ]
}`;

const itemResponse = `{
  "data": {
    "id": 10,
    "name": "Margherita",
    "description": "Tomato, mozzarella, basil",
    "price": "12.50",
    "price_cents": 1250,
    "dietary": "V",
    "allergens": ["gluten", "milk"],
    "is_available": false,
    "is_featured": true
  }
}`;

const webhookPayload = `{
  "event": "order.created",
  "sent_at": "2026-11-25T12:00:03+00:00",
  "restaurant": {
    "username": "joes-pizza",
    "name": "Joe's Pizza",
    "currency": "USD",
    "menu_url": "https://your-app.test/restaurant/joes-pizza"
  },
  "order": {
    "id": 42,
    "reference": "ORD-AB12CD",
    "status": "new",
    "status_label": "New",
    "type": "dine_in",
    "type_label": "Dine in",
    "table_number": "7",
    "delivery_address": null,
    "customer_name": "Maya Patel",
    "customer_phone": "+1 555 0102",
    "customer_email": "maya@example.com",
    "notes": "No parmesan",
    "subtotal_cents": 2500,
    "coupon_code": null,
    "discount_cents": 0,
    "delivery_fee_cents": 0,
    "service_cents": 0,
    "tax_cents": 0,
    "tip_cents": 0,
    "total_cents": 2500,
    "currency": "USD",
    "created_at": "2026-11-25T12:00:00+00:00",
    "updated_at": "2026-11-25T12:00:00+00:00",
    "items": [
      {
        "menu_item_id": 10,
        "name": "Margherita",
        "quantity": 2,
        "unit_price_cents": 1250,
        "line_total_cents": 2500,
        "notes": null,
        "options": [
          { "group": "Size", "name": "Large", "price_cents": 200 }
        ]
      }
    ]
  }
}`;

const verifyPhp = `$rawBody = file_get_contents('php://input');
$secret = 'your_webhook_signing_secret';

$expected = hash_hmac('sha256', $rawBody, $secret);
$received = $_SERVER['HTTP_X_ONENETLY_SIGNATURE'] ?? '';

if (! hash_equals($expected, $received)) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($rawBody, true);

if (($event['event'] ?? null) === 'order.created') {
    // Push $event['order'] into your POS.
}

http_response_code(200);
echo 'ok';`;

const verifyNode = `const crypto = require('node:crypto');
const express = require('express');

const app = express();
const secret = 'your_webhook_signing_secret';

app.post(
    '/hooks/onenetly',
    express.raw({ type: 'application/json' }),
    (req, res) => {
        const expected = crypto
            .createHmac('sha256', secret)
            .update(req.body)
            .digest('hex');

        const received = req.get('X-OneNetly-Signature') ?? '';

        const valid =
            expected.length === received.length &&
            crypto.timingSafeEqual(
                Buffer.from(expected),
                Buffer.from(received),
            );

        if (!valid) {
            return res.status(401).send('Invalid signature');
        }

        const event = JSON.parse(req.body.toString());

        // Push event.order into your POS here.

        res.status(200).send('ok');
    },
);`;
</script>

<template>
    <Head title="Documentation" />

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="sticky top-0 z-40 border-b border-border/70 bg-background/90 backdrop-blur"
        >
            <div
                class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-5"
            >
                <div class="flex items-center gap-3">
                    <Link :href="props.homeUrl" class="flex items-center gap-2">
                        <AppLogo />
                    </Link>
                    <span class="h-5 w-px bg-border" />
                    <span
                        class="inline-flex items-center gap-1.5 text-sm font-medium"
                    >
                        <BookOpen class="size-4" />
                        Docs
                    </span>
                </div>

                <nav class="flex items-center gap-4 text-sm">
                    <Link
                        href="/developers"
                        class="hidden text-muted-foreground transition hover:text-foreground sm:inline"
                    >
                        Developers
                    </Link>
                    <a
                        href="#pos-overview"
                        class="hidden text-muted-foreground transition hover:text-foreground sm:inline"
                    >
                        POS API
                    </a>
                    <a
                        :href="props.homeUrl"
                        class="hidden text-muted-foreground transition hover:text-foreground sm:inline"
                    >
                        Home
                    </a>
                    <Link
                        v-if="isAuthenticated"
                        :href="props.dashboardUrl"
                        class="rounded-lg border px-3 py-1.5 font-medium transition hover:bg-muted"
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-else
                        :href="props.loginUrl"
                        class="rounded-lg border px-3 py-1.5 font-medium transition hover:bg-muted"
                    >
                        Sign in
                    </Link>
                </nav>
            </div>
        </header>

        <div class="mx-auto w-full max-w-6xl px-5 py-10">
            <div class="max-w-3xl">
                <p
                    class="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase"
                >
                    {{ appName }} documentation
                </p>
                <h1 class="mt-2 text-4xl font-semibold tracking-tight">
                    Product &amp; POS API docs
                </h1>
                <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                    Everything in the restaurant workspace, the public menu, and
                    the open REST + webhook API any POS or integration can use.
                    Field names below match the JSON the application returns.
                </p>
                <div class="mt-5 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full border px-3 py-1 font-medium">
                        POS API v1
                    </span>
                    <span class="rounded-full border px-3 py-1 font-medium">
                        Signed webhooks
                    </span>
                    <span class="rounded-full border px-3 py-1 font-medium">
                        JSON over HTTPS
                    </span>
                    <a
                        href="#getting-started"
                        class="rounded-full bg-foreground px-3 py-1 font-medium text-background"
                    >
                        Quick start →
                    </a>
                </div>
            </div>

            <div
                class="mt-10 grid gap-10 lg:grid-cols-[220px_minmax(0,1fr)] lg:items-start"
            >
                <aside
                    class="hidden lg:sticky lg:top-24 lg:block lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto lg:pr-4"
                >
                    <p
                        class="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase"
                    >
                        On this page
                    </p>
                    <nav class="mt-3 space-y-2 text-sm">
                        <details
                            v-for="group in navGroups"
                            :key="group.title"
                            open
                            class="group"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between rounded-md px-2 py-1.5 text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase transition hover:text-foreground [&::-webkit-details-marker]:hidden"
                            >
                                {{ group.title }}
                                <ChevronDown
                                    class="size-3.5 transition group-open:rotate-180"
                                />
                            </summary>
                            <ul class="mt-1 ml-2 space-y-0.5 border-l pl-2">
                                <li v-for="item in group.items" :key="item.id">
                                    <a
                                        :href="`#${item.id}`"
                                        class="block rounded-md px-2 py-1.5 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                    >
                                        {{ item.title }}
                                    </a>
                                </li>
                            </ul>
                        </details>
                    </nav>
                </aside>

                <main class="min-w-0 space-y-14 pb-24">
                    <section id="overview" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Overview
                        </h2>
                        <p
                            class="mt-3 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ appName }} has two independent products that share
                            one account: <strong>biolinks</strong> for people and
                            brands, and <strong>restaurants</strong> for menus,
                            table ordering and POS sync. A user can run several
                            restaurants, and each restaurant keeps its own menu,
                            orders, guest requests, templates and API tokens.
                        </p>
                        <div
                            class="mt-5 grid gap-3 sm:grid-cols-3 text-sm"
                        >
                            <div class="rounded-xl border p-4">
                                <p class="font-semibold">Guest menu</p>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-muted-foreground"
                                >
                                    A mobile page at
                                    <code class="text-xs"
                                        >/restaurant/{username}</code
                                    >
                                    with templates, search, allergen filters and
                                    cart ordering.
                                </p>
                            </div>
                            <div class="rounded-xl border p-4">
                                <p class="font-semibold">Kitchen workspace</p>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-muted-foreground"
                                >
                                    The authenticated dashboard at
                                    <code class="text-xs">/restaurant</code>:
                                    menus, orders, requests, QR codes, analytics.
                                </p>
                            </div>
                            <div class="rounded-xl border p-4">
                                <p class="font-semibold">Open integration</p>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-muted-foreground"
                                >
                                    Bearer-token REST API plus signed webhooks.
                                    No vendor lock-in: any POS that speaks HTTP
                                    can connect.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section id="concepts" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Core concepts
                        </h2>
                        <dl class="mt-4 space-y-4 text-sm">
                            <div>
                                <dt class="font-semibold">Profile</dt>
                                <dd class="mt-1 text-muted-foreground">
                                    One public page record. It powers either a
                                    biolink or a restaurant. Restaurant pages use
                                    <code class="text-xs"
                                        >business_type = "restaurant"</code
                                    >
                                    and redirect their biolink URL to the menu
                                    page.
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold">
                                    Restaurant / username
                                </dt>
                                <dd class="mt-1 text-muted-foreground">
                                    The public address of a restaurant, e.g.
                                    <code class="text-xs">joes-pizza</code>. It
                                    is unique across the platform, lowercase
                                    letters, numbers, dashes and underscores, and
                                    reserved words such as
                                    <code class="text-xs">restaurant</code> are
                                    blocked.
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold">Menu section</dt>
                                <dd class="mt-1 text-muted-foreground">
                                    A category (Starters, Mains, Drinks). Sections
                                    can be hidden without deleting them.
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold">Menu item</dt>
                                <dd class="mt-1 text-muted-foreground">
                                    A dish with a free-form price label, optional
                                    photo, dietary label and structured allergens.
                                    Availability and “chef's pick” are separate
                                    flags.
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold">Order</dt>
                                <dd class="mt-1 text-muted-foreground">
                                    A cart submitted from the public menu. Prices
                                    are recalculated server-side in cents; the
                                    order keeps its own currency snapshot.
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold">Guest request</dt>
                                <dd class="mt-1 text-muted-foreground">
                                    A waiter call, bill request or table clean-up
                                    sent from a table QR code.
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold">POS token</dt>
                                <dd class="mt-1 text-muted-foreground">
                                    A per-restaurant bearer credential for the
                                    integration API. Stored hashed, shown once,
                                    revocable.
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section id="getting-started" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Getting started
                        </h2>
                        <ol
                            class="mt-4 space-y-3 text-sm text-muted-foreground"
                        >
                            <li>
                                <span class="font-semibold text-foreground"
                                    >1. Create an account</span
                                >
                                — verify your email, then open
                                <strong>Restaurant</strong> in the sidebar.
                            </li>
                            <li>
                                <span class="font-semibold text-foreground"
                                    >2. Add a restaurant</span
                                >
                                — name, public address, description, address and
                                phone. It is published immediately with ordering
                                off.
                            </li>
                            <li>
                                <span class="font-semibold text-foreground"
                                    >3. Build the menu</span
                                >
                                — add sections and items, prices, photos,
                                allergens and chef's picks.
                            </li>
                            <li>
                                <span class="font-semibold text-foreground"
                                    >4. Enable ordering</span
                                >
                                — choose a currency in the menu settings. Guests
                                can now add items to a cart and send orders.
                            </li>
                            <li>
                                <span class="font-semibold text-foreground"
                                    >5. Print QR codes</span
                                >
                                — one code per table, from the hub or the menu
                                builder.
                            </li>
                            <li>
                                <span class="font-semibold text-foreground"
                                    >6. Connect a POS (optional)</span
                                >
                                — save a webhook URL and generate API keys in
                                the <strong>Developer</strong> section.
                            </li>
                        </ol>
                    </section>

                    <section id="dashboard" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Dashboard guide
                        </h2>
                        <div class="mt-4 space-y-4 text-sm">
                            <div class="rounded-xl border p-4">
                                <p class="font-semibold">Overview</p>
                                <p
                                    class="mt-1 text-muted-foreground leading-relaxed"
                                >
                                    Restaurant switcher, totals (restaurants,
                                    orders today, waiting orders, menu views
                                    today), selected-restaurant cards (orders
                                    today, sales today, new orders, menu size),
                                    recent orders, the guest-request inbox,
                                    restaurant details + template, and table QR
                                    download. API keys and webhooks now live in
                                    the separate Developer section.
                                </p>
                            </div>
                            <div class="rounded-xl border p-4">
                                <p class="font-semibold">Menu</p>
                                <p
                                    class="mt-1 text-muted-foreground leading-relaxed"
                                >
                                    Sections and items with ordering settings,
                                    currency, availability toggles, chef's pick
                                    flag, allergen chips and image upload
                                    (JPEG/PNG/WebP, max 2 MB). Every action is
                                    scoped to the restaurant in the URL.
                                </p>
                            </div>
                            <div class="rounded-xl border p-4">
                                <p class="font-semibold">Orders</p>
                                <p
                                    class="mt-1 text-muted-foreground leading-relaxed"
                                >
                                    Paginated order inbox with customer contact,
                                    table, line items, notes, totals and status
                                    controls. New orders are also emailed to the
                                    account address.
                                </p>
                            </div>
                            <div class="rounded-xl border p-4">
                                <p class="font-semibold">Guest requests</p>
                                <p
                                    class="mt-1 text-muted-foreground leading-relaxed"
                                >
                                    Pending waiter/bill/cleaning calls with table
                                    and age. The hub refreshes them every 20
                                    seconds; resolving one marks it done.
                                </p>
                            </div>
                            <div class="rounded-xl border p-4">
                                <p class="font-semibold">QR codes</p>
                                <p
                                    class="mt-1 text-muted-foreground leading-relaxed"
                                >
                                    SVG QR codes that deep-link to the public
                                    menu and carry the table number into the
                                    order.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section id="menu-builder" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Menu builder reference
                        </h2>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="py-2 pr-4 font-medium">
                                            Field
                                        </th>
                                        <th class="py-2 pr-4 font-medium">
                                            Rules
                                        </th>
                                        <th class="py-2 font-medium">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Section name
                                        </td>
                                        <td class="py-2 pr-4">Required, 80 chars</td>
                                        <td class="py-2 text-muted-foreground">
                                            Ordered by creation.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Section description
                                        </td>
                                        <td class="py-2 pr-4">Optional, 255</td>
                                        <td class="py-2 text-muted-foreground">
                                            Shown under the section heading.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Item name
                                        </td>
                                        <td class="py-2 pr-4">Required, 80</td>
                                        <td class="py-2 text-muted-foreground">
                                            Shown in the menu and cart.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Price
                                        </td>
                                        <td class="py-2 pr-4">Optional, 30</td>
                                        <td class="py-2 text-muted-foreground">
                                            Display text; numeric values are
                                            parsed to cents for ordering.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Description
                                        </td>
                                        <td class="py-2 pr-4">Optional, 500</td>
                                        <td class="py-2 text-muted-foreground">
                                            Visible to guests.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Dietary label
                                        </td>
                                        <td class="py-2 pr-4">Optional, 80</td>
                                        <td class="py-2 text-muted-foreground">
                                            Free text such as V, GF, vegan.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Allergens
                                        </td>
                                        <td class="py-2 pr-4">
                                            Fixed list, multi-select
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Guests can filter items out by
                                            allergen.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Image
                                        </td>
                                        <td class="py-2 pr-4">
                                            JPEG/PNG/WebP, max 2 MB
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Optional per item; replaced on upload.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Available
                                        </td>
                                        <td class="py-2 pr-4">Boolean</td>
                                        <td class="py-2 text-muted-foreground">
                                            Hides the item from guests and blocks
                                            ordering it.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Option groups
                                        </td>
                                        <td class="py-2 pr-4">
                                            Per item; required or optional, single
                                            or multiple choices
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Choices can add a price delta in
                                            cents; guests must pick every
                                            required group before ordering.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Chef's pick
                                        </td>
                                        <td class="py-2 pr-4">Boolean</td>
                                        <td class="py-2 text-muted-foreground">
                                            Adds the item to the featured row.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section id="templates" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Menu templates
                        </h2>
                        <p class="mt-3 text-sm text-muted-foreground">
                            Set per restaurant in
                            <strong>Restaurant details → Menu template</strong>.
                            Templates change layout, typography and palette; they
                            do not change the data.
                        </p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="py-2 pr-4 font-medium">
                                            Key
                                        </th>
                                        <th class="py-2 pr-4 font-medium">
                                            Name
                                        </th>
                                        <th class="py-2 font-medium">Look</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">classic</code>
                                        </td>
                                        <td class="py-2 pr-4">Classic</td>
                                        <td class="py-2 text-muted-foreground">
                                            Warm cream, serif headings, centred.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">modern</code>
                                        </td>
                                        <td class="py-2 pr-4">Modern</td>
                                        <td class="py-2 text-muted-foreground">
                                            White cards, emerald accent, left
                                            aligned.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">dark</code>
                                        </td>
                                        <td class="py-2 pr-4">Midnight</td>
                                        <td class="py-2 text-muted-foreground">
                                            Dark stone surfaces with amber accents.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">elegant</code>
                                        </td>
                                        <td class="py-2 pr-4">Elegant</td>
                                        <td class="py-2 text-muted-foreground">
                                            Editorial off-white, serif, thin rules.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">fresh</code>
                                        </td>
                                        <td class="py-2 pr-4">Fresh</td>
                                        <td class="py-2 text-muted-foreground">
                                            Emerald palette, left aligned.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">minimal</code>
                                        </td>
                                        <td class="py-2 pr-4">Minimal</td>
                                        <td class="py-2 text-muted-foreground">
                                            Monochrome list layout without photos.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section id="public-menu" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Public menu
                        </h2>
                        <p class="mt-3 text-sm text-muted-foreground">
                            URL:
                            <code class="text-xs"
                                >/restaurant/{username}</code
                            >. Only items in active sections that are available
                            are shown. The page includes:
                        </p>
                        <ul
                            class="mt-4 space-y-2 text-sm text-muted-foreground"
                        >
                            <li>
                                <span class="font-medium text-foreground"
                                    >Chef's picks</span
                                >
                                — featured items in a horizontal row.
                            </li>
                            <li>
                                <span class="font-medium text-foreground"
                                    >Options</span
                                >
                                — required or optional groups such as size,
                                milk or extras, priced server-side when the
                                order is placed.
                            </li>
                            <li>
                                <span class="font-medium text-foreground"
                                    >Live search</span
                                >
                                — matches name, description and dietary label.
                            </li>
                            <li>
                                <span class="font-medium text-foreground"
                                    >Allergen filters</span
                                >
                                — only allergens present in the menu are offered;
                                selected ones hide matching items.
                            </li>
                            <li>
                                <span class="font-medium text-foreground"
                                    >Cart</span
                                >
                                — quantities and per-line notes, persisted in
                                localStorage per restaurant, cleared after an
                                order.
                            </li>
                            <li>
                                <span class="font-medium text-foreground"
                                    >Checkout</span
                                >
                                — dine in, takeaway or delivery (when enabled),
                                delivery address, tip, promo code, minimum order
                                and server-calculated service charge, tax and
                                delivery fee. Guests with a phone number earn
                                loyalty points they can see on the tracking page.
                            </li>
                            <li>
                                <span class="font-medium text-foreground"
                                    >Daypart menus</span
                                >
                                — sections can be tagged breakfast, lunch,
                                dinner, drinks or all day; guests switch menu
                                with the daypart tabs.
                            </li>
                            <li>
                                <span class="font-medium text-foreground"
                                    >Reservations</span
                                >
                                — guests request a table with party size, date,
                                time and notes; owners confirm or cancel from the
                                Reservations tab.
                            </li>
                            <li>
                                <span class="font-medium text-foreground"
                                    >Order tracking</span
                                >
                                — after ordering, guests can follow the status
                                at
                                <code class="text-xs"
                                    >/restaurant/{username}/order/{reference}</code
                                >; the page refreshes every 20 seconds.
                            </li>
                            <li>
                                <span class="font-medium text-foreground"
                                    >Share menu</span
                                >
                                — native share sheet or copy link.
                            </li>
                            <li>
                                <span class="font-medium text-foreground"
                                    >Table service</span
                                >
                                — when the URL has a table number:
                                <code class="text-xs">?table=7</code>.
                            </li>
                            <li>
                                <span class="font-medium text-foreground"
                                    >Opening hours</span
                                >
                                — ordering follows the restaurant's weekly
                                hours in its own timezone; outside them the menu
                                stays browsable and shows today's hours.
                            </li>
                            <li>
                                <span class="font-medium text-foreground"
                                    >Paused notice</span
                                >
                                — ordering can be paused while the menu stays
                                online.
                            </li>
                        </ul>
                    </section>

                    <section id="orders" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Orders
                        </h2>
                        <p class="mt-3 text-sm text-muted-foreground">
                            A guest order is created with server-calculated
                            totals, a unique reference
                            (<code class="text-xs">ORD-XXXXXX</code>), the
                            restaurant's currency and a starting status of
                            <code class="text-xs">new</code>. The restaurant
                            owner receives an email, and the configured POS
                            webhook is called after the response.
                        </p>
                        <p class="mt-3 text-sm text-muted-foreground">
                            Restaurant settings control the checkout charges:
                            <strong>tax rate</strong>,
                            <strong>service charge</strong>,
                            <strong>delivery fee</strong>,
                            <strong>minimum order</strong> and whether delivery
                            is offered. Totals are always recalculated on the
                            server from the menu prices and chosen options;
                            optional tips and promo-code discounts are applied
                            server-side. The same tab controls TextBee SMS
                            updates and the loyalty points ledger.
                        </p>
                        <p class="mt-3 text-sm text-muted-foreground">
                            The <strong>Reports</strong> tab summarises revenue,
                            average order value, items sold, daily sales, order
                            types and best sellers, with a CSV export. The
                            restaurant <strong>Team</strong> card lets the owner
                            add managers (full menu and settings access) and
                            staff (orders and reservations). Contact-form
                            messages appear in the workspace
                            <strong>Inbox</strong> as well as being emailed.
                        </p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="py-2 pr-4 font-medium">
                                            Status
                                        </th>
                                        <th class="py-2 font-medium">Meaning</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">new</code>
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Received, not started.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs"
                                                >preparing</code
                                            >
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            In the kitchen.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">ready</code>
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Ready to serve or collect.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs"
                                                >completed</code
                                            >
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Delivered and closed.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs"
                                                >cancelled</code
                                            >
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Cancelled; kept for history.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-4 text-sm text-muted-foreground">
                            Order types:
                            <code class="text-xs">dine_in</code>,
                            <code class="text-xs">takeaway</code>,
                            <code class="text-xs">delivery</code>. A table number
                            is required for dine-in. Totals:
                            <code class="text-xs">subtotal_cents</code> and
                            <code class="text-xs">total_cents</code> (currently
                            equal), plus a currency snapshot.
                        </p>
                    </section>

                    <section id="qr" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            QR codes
                        </h2>
                        <p class="mt-3 text-sm text-muted-foreground">
                            The dashboard renders an SVG QR code at
                            <code class="text-xs"
                                >/restaurant/{username}/qr</code
                            >. Add
                            <code class="text-xs">?table=7</code>
                            to bake the table into the link, and
                            <code class="text-xs">&amp;download=1</code>
                            for an attachment. Scanning opens the public menu with
                            <code class="text-xs">table=7</code>, which pre-fills
                            the order form and enables guest service buttons.
                        </p>
                    </section>

                    <section id="service-requests" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Guest requests
                        </h2>
                        <p class="mt-3 text-sm text-muted-foreground">
                            Guests at a table can call a waiter, request the bill
                            or ask for a clean table. Requests are stored with the
                            restaurant, table number, type and status, shown in
                            the hub inbox, and marked done by staff.
                        </p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="py-2 pr-4 font-medium">
                                            Type
                                        </th>
                                        <th class="py-2 pr-4 font-medium">
                                            Label
                                        </th>
                                        <th class="py-2 font-medium">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">waiter</code>
                                        </td>
                                        <td class="py-2 pr-4">Call waiter</td>
                                        <td class="py-2 text-muted-foreground">
                                            Guest needs assistance.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">bill</code>
                                        </td>
                                        <td class="py-2 pr-4">Request bill</td>
                                        <td class="py-2 text-muted-foreground">
                                            Ready to pay.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">cleaning</code>
                                        </td>
                                        <td class="py-2 pr-4">Clean table</td>
                                        <td class="py-2 text-muted-foreground">
                                            Table needs clearing.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            The public form is throttled to 6 requests per minute
                            per client and includes a honeypot field.
                        </p>
                    </section>

                    <section id="pos-overview" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            POS integration
                        </h2>
                        <p class="mt-3 text-sm text-muted-foreground">
                            There is no per-vendor plugin to install. A POS
                            connects in one of two ways, or both:
                        </p>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border p-4 text-sm">
                                <p class="font-semibold">1. Push (webhooks)</p>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-muted-foreground"
                                >
                                    Save an HTTPS endpoint in the hub. Every new
                                    order is POSTed there as signed JSON. Best
                                    when your POS or middleware can receive HTTP
                                    requests.
                                </p>
                            </div>
                            <div class="rounded-xl border p-4 text-sm">
                                <p class="font-semibold">2. Pull (REST API)</p>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-muted-foreground"
                                >
                                    Issue a bearer token and poll
                                    <code class="text-xs">/orders</code>, post
                                    new orders, then push status changes and
                                    sold-out flags back. Best for POS software
                                    with an outbound HTTP client.
                                </p>
                            </div>
                        </div>
                        <p class="mt-4 text-sm text-muted-foreground">
                            Typical flow: guest orders → order stored → signed
                            webhook sent after the guest response → POS replies
                            2xx → POS patches status → guest-facing status
                            reflects the change. Every webhook attempt is logged
                            with status, error and attempt count.
                        </p>
                    </section>

                    <section id="pos-auth" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            API tokens &amp; authentication
                        </h2>
                        <ol
                            class="mt-4 list-decimal space-y-2 pl-5 text-sm text-muted-foreground"
                        >
                            <li>
                                Open <strong>Developer</strong> in the sidebar
                                and select the restaurant.
                            </li>
                            <li>
                                Under <strong>API keys</strong>, enter a name
                                (for example “Bar POS”) and choose
                                <strong>Generate key</strong>.
                            </li>
                            <li>
                                Copy the token immediately. It is shown once and
                                stored only as a SHA-256 hash.
                            </li>
                            <li>
                                Send it on every request:
                                <code class="text-xs"
                                    >Authorization: Bearer pos_…</code
                                >.
                            </li>
                        </ol>
                        <p class="mt-3 text-sm text-muted-foreground">
                            A token is scoped to one restaurant: it can only read
                            and update that restaurant's menu and orders. Tokens
                            record their last use and can be revoked at any time;
                            revocation takes effect immediately.
                        </p>
                        <div class="mt-4">
                            <CodeBlock :code="curlMenu" language="bash" />
                        </div>
                    </section>

                    <section id="api-menu" class="scroll-mt-24">
                        <div
                            class="flex flex-wrap items-center gap-2 text-xs"
                        >
                            <span
                                class="rounded-md bg-emerald-500/15 px-2 py-1 font-mono font-semibold text-emerald-700 dark:text-emerald-300"
                            >
                                GET
                            </span>
                            <code class="text-sm">{{ props.apiBase }}/menu</code>
                        </div>
                        <h2 class="mt-3 text-2xl font-semibold tracking-tight">
                            Read the menu
                        </h2>
                        <p class="mt-3 text-sm text-muted-foreground">
                            Returns the restaurant, its ordering state and the
                            full catalog, including hidden sections and
                            unavailable items so the POS can stay in sync.
                            <code class="text-xs">price_cents</code> is null when
                            the price cannot be parsed; such items cannot be
                            ordered.
                        </p>
                        <div class="mt-4">
                            <CodeBlock :code="menuResponse" language="json" />
                        </div>
                    </section>

                    <section id="api-orders" class="scroll-mt-24">
                        <div
                            class="flex flex-wrap items-center gap-2 text-xs"
                        >
                            <span
                                class="rounded-md bg-sky-500/15 px-2 py-1 font-mono font-semibold text-sky-700 dark:text-sky-300"
                            >
                                GET
                            </span>
                            <code class="text-sm">{{ props.apiBase }}/orders</code>
                        </div>
                        <h2 class="mt-3 text-2xl font-semibold tracking-tight">
                            Pull orders
                        </h2>
                        <p class="mt-3 text-sm text-muted-foreground">
                            Newest first. Returns at most 100 orders per call.
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            <strong>POST</strong>
                            <code class="text-xs">/orders</code> creates an
                            order from the POS. Send customer details, order
                            type and items (with optional option IDs); prices,
                            charges and promo codes are calculated by the same
                            server logic as guest checkout. Delivery orders
                            require an address.
                        </p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="py-2 pr-4 font-medium">
                                            Query
                                        </th>
                                        <th class="py-2 pr-4 font-medium">
                                            Type
                                        </th>
                                        <th class="py-2 font-medium">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">status</code>
                                        </td>
                                        <td class="py-2 pr-4">
                                            order status
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Filter by new, preparing, ready,
                                            completed or cancelled.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">since</code>
                                        </td>
                                        <td class="py-2 pr-4">ISO date</td>
                                        <td class="py-2 text-muted-foreground">
                                            Only orders updated at or after this
                                            timestamp — ideal for polling.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">limit</code>
                                        </td>
                                        <td class="py-2 pr-4">1–100</td>
                                        <td class="py-2 text-muted-foreground">
                                            Defaults to 50.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            <CodeBlock :code="curlOrders" language="bash" />
                        </div>
                        <div class="mt-3">
                            <CodeBlock
                                :code="ordersResponse"
                                language="json"
                            />
                        </div>
                    </section>

                    <section id="api-order-update" class="scroll-mt-24">
                        <div
                            class="flex flex-wrap items-center gap-2 text-xs"
                        >
                            <span
                                class="rounded-md bg-amber-500/15 px-2 py-1 font-mono font-semibold text-amber-700 dark:text-amber-300"
                            >
                                PATCH
                            </span>
                            <code class="text-sm"
                                >{{ props.apiBase }}/orders/{reference}</code
                            >
                        </div>
                        <h2 class="mt-3 text-2xl font-semibold tracking-tight">
                            Update an order status
                        </h2>
                        <p class="mt-3 text-sm text-muted-foreground">
                            Body:
                            <code class="text-xs">{"status": "preparing"}</code>.
                            The status is required and must be one of the five
                            values. Returns the full order, same shape as the
                            list endpoint.
                        </p>
                        <div class="mt-4">
                            <CodeBlock
                                :code="curlOrderUpdate"
                                language="bash"
                            />
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            A reference that belongs to another restaurant
                            returns 404, not 403 — references are never leaked
                            across tenants.
                        </p>
                    </section>

                    <section id="api-items" class="scroll-mt-24">
                        <div
                            class="flex flex-wrap items-center gap-2 text-xs"
                        >
                            <span
                                class="rounded-md bg-amber-500/15 px-2 py-1 font-mono font-semibold text-amber-700 dark:text-amber-300"
                            >
                                PATCH
                            </span>
                            <code class="text-sm"
                                >{{ props.apiBase }}/items/{id}</code
                            >
                        </div>
                        <h2 class="mt-3 text-2xl font-semibold tracking-tight">
                            Mark items sold out
                        </h2>
                        <p class="mt-3 text-sm text-muted-foreground">
                            Send
                            <code class="text-xs">is_available</code>
                            (boolean) and/or
                            <code class="text-xs">price</code>
                            (string, max 30). Everything else about the item is
                            unchanged. Items outside the token's restaurant return
                            404.
                        </p>
                        <div class="mt-4">
                            <CodeBlock :code="curlItemUpdate" language="bash" />
                        </div>
                        <div class="mt-3">
                            <CodeBlock :code="itemResponse" language="json" />
                        </div>
                    </section>

                    <section id="webhooks" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Webhooks
                        </h2>
                        <p class="mt-3 text-sm text-muted-foreground">
                            Configure one HTTPS URL per restaurant. Save it in
                            <strong>Developer → Webhooks</strong>; a 40-character
                            signing secret is generated on first save and can be
                            copied once. Use
                            <strong>Send test webhook</strong>
                            to verify reachability.
                        </p>
                        <h3 class="mt-6 text-lg font-semibold">Events</h3>
                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="py-2 pr-4 font-medium">
                                            Event
                                        </th>
                                        <th class="py-2 font-medium">When</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs"
                                                >order.created</code
                                            >
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            After a guest order is stored.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs"
                                                >order.updated</code
                                            >
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            After the order status changes, with
                                            the previous status in the payload.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">ping</code>
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Only when you press “Send test
                                            webhook”.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h3 class="mt-6 text-lg font-semibold">Headers</h3>
                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="py-2 pr-4 font-medium">
                                            Header
                                        </th>
                                        <th class="py-2 font-medium">Value</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs"
                                                >Content-Type</code
                                            >
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            application/json
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs"
                                                >X-OneNetly-Event</code
                                            >
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            order.created or ping
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs"
                                                >X-OneNetly-Signature</code
                                            >
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Lowercase hex HMAC-SHA256 of the raw
                                            body
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs"
                                                >X-OneNetly-Delivery</code
                                            >
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Delivery id, useful for logs
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h3 class="mt-6 text-lg font-semibold">Payload</h3>
                        <div class="mt-3">
                            <CodeBlock
                                :code="webhookPayload"
                                language="json"
                            />
                        </div>

                        <h3 class="mt-6 text-lg font-semibold">
                            Delivery &amp; retries
                        </h3>
                        <ul
                            class="mt-3 space-y-2 text-sm text-muted-foreground"
                        >
                            <li>
                                Respond with any 2xx within 4 seconds to
                                acknowledge.
                            </li>
                            <li>
                                Non-2xx responses and network errors are retried
                                once, about 300 ms later.
                            </li>
                            <li>
                                Each attempt records status code, error, attempt
                                count and time; the last six appear in the hub.
                            </li>
                            <li>
                                Deliveries run after the guest response, so a slow
                                endpoint never delays ordering.
                            </li>
                            <li>
                                Clear the webhook URL to stop deliveries; the
                                signing secret is kept for later reuse.
                            </li>
                        </ul>
                    </section>

                    <section id="signatures" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Verifying signatures
                        </h2>
                        <p class="mt-3 text-sm text-muted-foreground">
                            Compute HMAC-SHA256 over the <em>raw</em> request
                            body with the signing secret and compare with
                            <code class="text-xs">X-OneNetly-Signature</code>
                            using a timing-safe comparison. Do not re-serialize
                            the JSON before hashing.
                        </p>
                        <div class="mt-4 space-y-3">
                            <CodeBlock :code="verifyPhp" language="php" />
                            <CodeBlock :code="verifyNode" language="javascript" />
                        </div>
                    </section>

                    <section id="errors" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Errors
                        </h2>
                        <p class="mt-3 text-sm text-muted-foreground">
                            Errors are JSON with a
                            <code class="text-xs">message</code>; validation
                            errors also include an
                            <code class="text-xs">errors</code>
                            object keyed by field.
                        </p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="py-2 pr-4 font-medium">
                                            Status
                                        </th>
                                        <th class="py-2 pr-4 font-medium">
                                            When
                                        </th>
                                        <th class="py-2 font-medium">
                                            Example message
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr>
                                        <td class="py-2 pr-4">401</td>
                                        <td class="py-2 pr-4">
                                            Missing or invalid bearer token
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            <code class="text-xs"
                                                >Invalid POS API token.</code
                                            >
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">404</td>
                                        <td class="py-2 pr-4">
                                            Order or item outside this
                                            restaurant, or unknown reference
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            <code class="text-xs">Not Found</code>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">422</td>
                                        <td class="py-2 pr-4">
                                            Invalid status, price or availability
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            <code class="text-xs"
                                                >The selected status is
                                                invalid.</code
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section id="reference" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Reference tables
                        </h2>

                        <h3 class="mt-6 text-lg font-semibold">
                            Order statuses
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            <code class="text-xs"
                                >new · preparing · ready · completed ·
                                cancelled</code
                            >
                        </p>

                        <h3 class="mt-6 text-lg font-semibold">Order types</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            <code class="text-xs"
                                >dine_in · takeaway · delivery</code
                            >
                        </p>

                        <h3 class="mt-6 text-lg font-semibold">
                            Allergen codes
                        </h3>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span
                                v-for="allergen in [
                                    'gluten',
                                    'crustaceans',
                                    'eggs',
                                    'fish',
                                    'peanuts',
                                    'soybeans',
                                    'milk',
                                    'nuts',
                                    'celery',
                                    'mustard',
                                    'sesame',
                                    'sulphites',
                                    'lupin',
                                    'molluscs',
                                ]"
                                :key="allergen"
                                class="rounded-full border px-2.5 py-1 font-mono"
                            >
                                {{ allergen }}
                            </span>
                        </div>

                        <h3 class="mt-6 text-lg font-semibold">
                            Menu currencies
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            <code class="text-xs"
                                >USD · EUR · GBP · CAD · AUD · INR · NGN · KES ·
                                ZAR · AED</code
                            >
                        </p>

                        <h3 class="mt-6 text-lg font-semibold">
                            Throttles &amp; upload limits
                        </h3>
                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="py-2 pr-4 font-medium">
                                            Action
                                        </th>
                                        <th class="py-2 font-medium">Limit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr>
                                        <td class="py-2 pr-4">
                                            Public order submit
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            10 / minute
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            Guest service request
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            6 / minute
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            Contact message
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            5 / minute
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            Page password attempts
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            6 / minute
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            Menu item image
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            2 MB, JPEG/PNG/WebP
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            Webhook delivery
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            4s timeout, 2 attempts
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section id="security" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Security &amp; limits
                        </h2>
                        <ul class="mt-4 space-y-2 text-sm text-muted-foreground">
                            <li>
                                Tokens are hashed with SHA-256 and shown once; the
                                plain value is never stored or listed.
                            </li>
                            <li>
                                Every API request is scoped to the token's
                                restaurant. Cross-tenant resources return 404.
                            </li>
                            <li>
                                A POS token cannot delete the menu, delete orders
                                or change restaurant settings.
                            </li>
                            <li>
                                Webhooks are signed with HMAC-SHA256; verify with
                                a timing-safe comparison before trusting a
                                payload.
                            </li>
                            <li>
                                Order payloads contain customer contact details.
                                Store them only where you already store POS
                                customer data.
                            </li>
                            <li>
                                Revoke a token immediately if a device is lost;
                                revocation takes effect on the next request.
                            </li>
                        </ul>
                    </section>

                    <section id="biolink-overview" class="scroll-mt-24">
                        <p
                            class="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase"
                        >
                            Product guide
                        </p>
                        <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                            BioLink
                        </h2>
                        <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                            The BioLink product is completely separate from
                            restaurants. Every account gets one biolink profile at
                            <code class="text-xs">/{username}</code>, managed
                            from <strong>My page</strong> and
                            <strong>Links</strong> in the sidebar. A restaurant
                            profile never appears as a biolink — opening its
                            biolink URL redirects to the menu page.
                        </p>
                        <div class="mt-5 grid gap-3 sm:grid-cols-3 text-sm">
                            <div class="rounded-xl border p-4">
                                <p class="font-semibold">Profile</p>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-muted-foreground"
                                >
                                    Avatar, name, bio, accent colour and one of
                                    eight page templates.
                                </p>
                            </div>
                            <div class="rounded-xl border p-4">
                                <p class="font-semibold">Links</p>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-muted-foreground"
                                >
                                    Reorderable link buttons with icons, active
                                    toggles, scheduling and click tracking.
                                </p>
                            </div>
                            <div class="rounded-xl border p-4">
                                <p class="font-semibold">Sharing</p>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-muted-foreground"
                                >
                                    Social icons, contact form, vCard download,
                                    SEO metadata, QR code and optional password.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section id="biolink-page" class="scroll-mt-24">
                        <h3 class="text-xl font-semibold tracking-tight">
                            Page settings
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            These fields live under <strong>My page</strong>. The
                            editor shows a live preview beside the form and saves
                            with <strong>Save changes</strong>.
                        </p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="py-2 pr-4 font-medium">
                                            Field
                                        </th>
                                        <th class="py-2 pr-4 font-medium">
                                            Rules
                                        </th>
                                        <th class="py-2 font-medium">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Username
                                        </td>
                                        <td class="py-2 pr-4">
                                            Unique, shortened to the public URL
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Reserved words (admin, docs, restaurant,
                                            …) are rejected.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Display name
                                        </td>
                                        <td class="py-2 pr-4">Optional</td>
                                        <td class="py-2 text-muted-foreground">
                                            Falls back to the account name.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">Bio</td>
                                        <td class="py-2 pr-4">280 characters</td>
                                        <td class="py-2 text-muted-foreground">
                                            Shown under the name; also the default
                                            meta description.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Avatar
                                        </td>
                                        <td class="py-2 pr-4">
                                            PNG/JPEG/WebP, max 2 MB
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Initials are shown when empty; also used
                                            as the share image.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Template
                                        </td>
                                        <td class="py-2 pr-4">8 layouts</td>
                                        <td class="py-2 text-muted-foreground">
                                            Background, typography and card style.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Accent colour
                                        </td>
                                        <td class="py-2 pr-4">6 themes</td>
                                        <td class="py-2 text-muted-foreground">
                                            Indigo, emerald, rose, amber, sky or
                                            slate accents.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Business type
                                        </td>
                                        <td class="py-2 pr-4">
                                            Personal, Creator, Professional, Shop /
                                            retail, Service business, Nonprofit
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Descriptive only.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Business profile
                                        </td>
                                        <td class="py-2 pr-4">All optional</td>
                                        <td class="py-2 text-muted-foreground">
                                            Company, job title, contact email,
                                            phone, website, address — these power
                                            the vCard.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Page is public
                                        </td>
                                        <td class="py-2 pr-4">Boolean</td>
                                        <td class="py-2 text-muted-foreground">
                                            Off hides the page and every link
                                            (404).
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Contact form
                                        </td>
                                        <td class="py-2 pr-4">Boolean</td>
                                        <td class="py-2 text-muted-foreground">
                                            Adds a message form; messages are
                                            emailed to the account address.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            SEO title / description
                                        </td>
                                        <td class="py-2 pr-4">70 / 160</td>
                                        <td class="py-2 text-muted-foreground">
                                            Default to display name and bio.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section id="biolink-links" class="scroll-mt-24">
                        <h3 class="text-xl font-semibold tracking-tight">
                            Links
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            <strong>Links</strong> in the sidebar manages the
                            buttons on the page. Every link belongs to the
                            account's biolink profile and appears in the order
                            shown there.
                        </p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="py-2 pr-4 font-medium">
                                            Field
                                        </th>
                                        <th class="py-2 pr-4 font-medium">
                                            Rules
                                        </th>
                                        <th class="py-2 font-medium">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Title
                                        </td>
                                        <td class="py-2 pr-4">Required, 255</td>
                                        <td class="py-2 text-muted-foreground">
                                            Button label.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">URL</td>
                                        <td class="py-2 pr-4">
                                            Required http(s) or mailto
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Opened through a tracked redirect.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Icon
                                        </td>
                                        <td class="py-2 pr-4">
                                            17 icon keys, optional
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Auto-detected from well-known hosts
                                            (GitHub, YouTube, Spotify, …) when
                                            left blank.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Active
                                        </td>
                                        <td class="py-2 pr-4">Boolean</td>
                                        <td class="py-2 text-muted-foreground">
                                            Off pauses the link without deleting
                                            it.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">
                                            Starts / ends
                                        </td>
                                        <td class="py-2 pr-4">
                                            Optional date-times
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            Schedules a link to appear and
                                            disappear automatically.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Statuses: <code class="text-xs">live</code>,
                            <code class="text-xs">paused</code>,
                            <code class="text-xs">scheduled</code> and
                            <code class="text-xs">expired</code>. Actions: add,
                            edit, duplicate, drag to reorder, delete. Plan limits
                            are enforced when adding or duplicating; a limit hit
                            shows an upgrade message.
                        </p>
                        <div class="mt-3 space-y-2 text-sm text-muted-foreground">
                            <p>
                                Every click goes through
                                <code class="text-xs">/l/{link}</code>, which checks
                                that the page is published and the link is live,
                                records the click for real visitors, then
                                redirects to the destination. Bursts are capped at
                                60 recorded clicks per IP per minute, and the
                                redirect itself is never blocked.
                            </p>
                            <p>
                                Icon keys:
                                <code class="text-xs"
                                    >link · globe · mail · calendar · video ·
                                    music · camera · store · briefcase · book ·
                                    mic · podcast · gamepad · heart · star ·
                                    download · code</code
                                >.
                            </p>
                        </div>
                    </section>

                    <section id="biolink-templates" class="scroll-mt-24">
                        <h3 class="text-xl font-semibold tracking-tight">
                            Templates &amp; themes
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            The template controls the page background, shape and
                            typography; the theme controls the accent colour used
                            by the avatar ring, social icons and buttons. Both are
                            independent of restaurant menu templates.
                        </p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="py-2 pr-4 font-medium">
                                            Key
                                        </th>
                                        <th class="py-2 pr-4 font-medium">
                                            Name
                                        </th>
                                        <th class="py-2 font-medium">Look</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">classic</code>
                                        </td>
                                        <td class="py-2 pr-4">Classic</td>
                                        <td class="py-2 text-muted-foreground">
                                            Soft neutral gradient, rounded cards.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">gradient</code>
                                        </td>
                                        <td class="py-2 pr-4">Gradient</td>
                                        <td class="py-2 text-muted-foreground">
                                            Indigo → fuchsia → sky wash.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">dark</code>
                                        </td>
                                        <td class="py-2 pr-4">Dark</td>
                                        <td class="py-2 text-muted-foreground">
                                            Near-black slate with light text.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">soft</code>
                                        </td>
                                        <td class="py-2 pr-4">Soft</td>
                                        <td class="py-2 text-muted-foreground">
                                            Warm amber paper tone.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">minimal</code>
                                        </td>
                                        <td class="py-2 pr-4">Minimal</td>
                                        <td class="py-2 text-muted-foreground">
                                            Plain white/zinc, flat buttons.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">aurora</code>
                                        </td>
                                        <td class="py-2 pr-4">Aurora</td>
                                        <td class="py-2 text-muted-foreground">
                                            Dark violet glow behind the profile.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">paper</code>
                                        </td>
                                        <td class="py-2 pr-4">Editorial</td>
                                        <td class="py-2 text-muted-foreground">
                                            Cream paper with serif headings.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <code class="text-xs">glass</code>
                                        </td>
                                        <td class="py-2 pr-4">Glass</td>
                                        <td class="py-2 text-muted-foreground">
                                            Vivid cyan → violet gradient with
                                            translucent cards.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section id="biolink-social" class="scroll-mt-24">
                        <h3 class="text-xl font-semibold tracking-tight">
                            Social icons &amp; vCard
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Social icons render as a row above the links. Add a
                            platform and profile URL under
                            <strong>My page → Social icons</strong>; each can be
                            edited or removed. The
                            <code class="text-xs">email</code> platform uses a
                            <code class="text-xs">mailto:</code> link.
                        </p>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Supported platforms:
                            <code class="text-xs"
                                >instagram · x · tiktok · youtube · facebook ·
                                linkedin · github · threads · snapchat ·
                                pinterest · twitch · spotify · soundcloud ·
                                discord · telegram · whatsapp · vimeo · reddit ·
                                medium · substack · behance · dribbble · email ·
                                website</code
                            >.
                        </p>
                        <p class="mt-4 text-sm text-muted-foreground">
                            When company, job title, email, phone, website or
                            address are filled in, the page offers a
                            <strong>Save contact</strong> button that downloads a
                            vCard from
                            <code class="text-xs">/{username}/vcard</code>
                            (<code class="text-xs">{username}.vcf</code>).
                            Password-protected pages redirect to the unlock screen
                            first.
                        </p>
                    </section>

                    <section id="biolink-contact" class="scroll-mt-24">
                        <h3 class="text-xl font-semibold tracking-tight">
                            Contact form
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Enable <strong>Contact form</strong> to show a message
                            form under the links. Submissions are validated
                            (name 80, email 120, message 2,000 characters),
                            require the page to be unlocked, and are emailed to
                            the account address. The endpoint is throttled to 5
                            messages per minute, and a hidden honeypot field
                            silently discards bots. Turning the form off makes
                            the endpoint return 404.
                        </p>
                    </section>

                    <section id="biolink-privacy" class="scroll-mt-24">
                        <h3 class="text-xl font-semibold tracking-tight">
                            Password &amp; SEO
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            <strong>Password protection</strong> hides the page
                            behind an unlock screen. Passwords must be at least 6
                            characters and are stored hashed; entering the right
                            password unlocks the page for that session. Unlock
                            attempts are throttled to 6 per minute. While locked,
                            the page, its links, the contact form and the vCard
                            all stay inaccessible. Untick or use “Remove password
                            protection” to make it public again.
                        </p>
                        <p class="mt-3 text-sm text-muted-foreground">
                            <strong>SEO title</strong> (70) and
                            <strong>meta description</strong> (160) default to the
                            display name and bio. The avatar is used as the social
                            share image, and the share URL is the public page URL.
                        </p>
                    </section>

                    <section id="biolink-domain" class="scroll-mt-24">
                        <h3 class="text-xl font-semibold tracking-tight">
                            Custom domain
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            A custom domain can replace
                            <code class="text-xs">/{username}</code> with your own
                            hostname. It requires both the platform-wide custom
                            domains setting and the
                            <code class="text-xs">has_custom_domain</code> plan
                            feature (Pro and Premium).                             Point the domain's DNS at the server, enter it under
                            <strong>My page → Custom domain</strong>, and it is
                            normalised to lowercase. Publish the
                            <code class="text-xs">onenetly-verification=…</code>
                            TXT record shown on the page and press
                            <strong>Verify domain</strong>; unverified domains
                            are not served. When the feature is unavailable the
                            field is disabled and the site shows the reason;
                            leaving it blank always falls back to the username
                            URL.
                        </p>
                    </section>

                    <section id="biolink-analytics" class="scroll-mt-24">
                        <h3 class="text-xl font-semibold tracking-tight">
                            Analytics &amp; QR
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            <strong>Analytics</strong> shows page views and link
                            clicks for the biolink: all-time clicks, clicks in the
                            last 30 days and this week, views (30 days and
                            all-time), average clicks per day, click rate, a
                            30-day chart, the top five links and top referrers.
                            Views are recorded for real visitors only. Access
                            requires the analytics setting and the
                            <code class="text-xs">has_analytics</code> plan
                            feature.
                        </p>
                        <p class="mt-3 text-sm text-muted-foreground">
                            A QR code for the whole page is available at
                            <code class="text-xs">/bio/qr</code> as SVG; add
                            <code class="text-xs">?download=1</code> for a file.
                            Restaurant table QR codes are separate and live at
                            <code class="text-xs"
                                >/restaurant/{username}/qr</code
                            >.
                        </p>
                        <p class="mt-3 text-sm text-muted-foreground">
                            A biolink never shows a restaurant menu, and
                            restaurant menus live only on
                            <code class="text-xs"
                                >/restaurant/{username}</code
                            >.
                        </p>
                    </section>

                    <section id="faq" class="scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight">FAQ</h2>
                        <div class="mt-4 space-y-4 text-sm">
                            <div>
                                <p class="font-semibold">
                                    Does this work with my POS?
                                </p>
                                <p class="mt-1 text-muted-foreground">
                                    If it can receive an HTTPS webhook or make
                                    authenticated HTTP calls, yes. There are no
                                    vendor-specific plugins and no extra
                                    credentials beyond the token or webhook
                                    secret.
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">
                                    Can I connect several restaurants?
                                </p>
                                <p class="mt-1 text-muted-foreground">
                                    Yes. Create one token and one webhook URL per
                                    restaurant. Each is isolated.
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">
                                    My webhook is not firing.
                                </p>
                                <p class="mt-1 text-muted-foreground">
                                    Check that the URL is saved, place a test
                                    order, and inspect
                                    <strong>Recent webhook deliveries</strong>.
                                    Use <strong>Send test webhook</strong> to
                                    isolate connectivity from order logic.
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">
                                    Can a POS take new orders?
                                </p>
                                <p class="mt-1 text-muted-foreground">
                                    The API reads orders and updates statuses and
                                    availability; guests place orders through the
                                    public menu. Point-of-sale entry can be added
                                    later without changing tokens.
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">
                                    What happens when ordering is paused?
                                </p>
                                <p class="mt-1 text-muted-foreground">
                                    The menu stays online, Add buttons disappear,
                                    a paused notice appears, and the order
                                    endpoint returns 404. Existing orders keep
                                    working.
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">
                                    What if I lose a token?
                                </p>
                                <p class="mt-1 text-muted-foreground">
                                    Tokens cannot be recovered. Revoke the old one
                                    and create a new token; update the POS with
                                    the new value.
                                </p>
                            </div>
                        </div>
                    </section>

                    <footer
                        class="flex flex-wrap items-center justify-between gap-3 border-t pt-6 text-xs text-muted-foreground"
                    >
                        <p>
                            These docs describe the current implementation of
                            {{ appName }}. Last updated 25 November 2026.
                        </p>
                        <div class="flex items-center gap-4">
                            <Link
                                :href="props.termsUrl"
                                class="hover:text-foreground"
                            >
                                Terms
                            </Link>
                            <Link
                                :href="props.privacyUrl"
                                class="hover:text-foreground"
                            >
                                Privacy
                            </Link>
                            <Link
                                :href="props.homeUrl"
                                class="inline-flex items-center gap-1 hover:text-foreground"
                            >
                                Home
                                <ExternalLink class="size-3.5" />
                            </Link>
                        </div>
                    </footer>
                </main>
            </div>
        </div>
    </div>
</template>
