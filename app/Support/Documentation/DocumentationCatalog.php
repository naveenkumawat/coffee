<?php

namespace App\Support\Documentation;

/**
 * Central, role-aware in-app documentation definitions.
 *
 * Content is written against features that actually exist in this application.
 * Option lists are mirrored from the real enums (App\Enums\*), settings keys
 * (App\Enums\WebsiteSettingKey) and config files (config/loyalty.php).
 *
 * Documentation roles are panel roles, not raw App\Enums\UserRole values:
 * "administrator" covers the owner and manager roles that use /administrator.
 *
 * @phpstan-type DocumentationCondition array{if: string, then: string}
 * @phpstan-type DocumentationExample array{title: string, body: string}
 * @phpstan-type DocumentationOption array{name: string, what: string, why: string, when: string, example: string}
 * @phpstan-type DocumentationModule array{
 *     slug: string,
 *     title: string,
 *     group: string,
 *     roles: list<string>,
 *     tags: list<string>,
 *     overview: string,
 *     how_it_works: list<string>,
 *     how_to_use: list<string>,
 *     how_to_configure: list<string>,
 *     conditions: list<DocumentationCondition>,
 *     examples: list<DocumentationExample>,
 *     options: list<DocumentationOption>,
 *     notes: list<string>,
 *     demo_samples: list<string>,
 *     help_anchor: string|null
 * }
 */
class DocumentationCatalog
{
    public const ROLE_ADMINISTRATOR = 'administrator';

    public const ROLE_OPERATOR = 'operator';

    public const ROLE_WAITER = 'waiter';

    public const ROLE_CHEF = 'chef';

    public const ROLE_BARISTA = 'barista';

    /**
     * Documentation roles in display order.
     *
     * @return list<string>
     */
    public function roles(): array
    {
        return [
            self::ROLE_ADMINISTRATOR,
            self::ROLE_OPERATOR,
            self::ROLE_WAITER,
            self::ROLE_CHEF,
            self::ROLE_BARISTA,
        ];
    }

    /**
     * Human labels for documentation roles.
     *
     * @return array<string, string>
     */
    public function roleLabels(): array
    {
        return [
            self::ROLE_ADMINISTRATOR => 'Administrator',
            self::ROLE_OPERATOR => 'Operator',
            self::ROLE_WAITER => 'Waiter',
            self::ROLE_CHEF => 'Chef',
            self::ROLE_BARISTA => 'Barista',
        ];
    }

    /**
     * Canonical group order used across every panel.
     *
     * @return list<string>
     */
    public function groupOrder(): array
    {
        return [
            'System',
            'Catalog',
            'Orders',
            'Dining',
            'Kitchen',
            'Bar',
            'Marketing',
            'Loyalty',
            'Operations',
            'Finance',
            'Launch',
        ];
    }

    /**
     * Modules visible to the given documentation role.
     *
     * @return list<DocumentationModule>
     */
    public function modulesForRole(string $role): array
    {
        $normalised = $this->normaliseRole($role);

        if ($normalised === null) {
            return [];
        }

        return array_values(array_filter(
            $this->modules(),
            fn (array $module): bool => in_array($normalised, $module['roles'], true),
        ));
    }

    /**
     * A single module for the role, or null when the role cannot see it.
     *
     * @return DocumentationModule|null
     */
    public function findModule(string $role, string $slug): ?array
    {
        $slug = mb_strtolower(trim($slug));

        foreach ($this->modulesForRole($role) as $module) {
            if ($module['slug'] === $slug) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Case-insensitive filter across title, overview and tags.
     *
     * @return list<DocumentationModule>
     */
    public function search(string $role, string $query): array
    {
        $needle = mb_strtolower(trim($query));
        $modules = $this->modulesForRole($role);

        if ($needle === '') {
            return $modules;
        }

        return array_values(array_filter($modules, function (array $module) use ($needle): bool {
            $haystack = mb_strtolower(implode(' ', [
                $module['title'],
                $module['slug'],
                $module['overview'],
                implode(' ', $module['tags']),
            ]));

            return str_contains($haystack, $needle);
        }));
    }

    /**
     * Every module across every role.
     *
     * @return list<DocumentationModule>
     */
    public function modules(): array
    {
        return array_map(
            fn (array $module): array => $this->module($module),
            [
                ...$this->administratorSystemModules(),
                ...$this->administratorCatalogModules(),
                ...$this->administratorOrderModules(),
                ...$this->administratorMarketingModules(),
                ...$this->administratorLoyaltyModules(),
                ...$this->administratorOperationsModules(),
                ...$this->administratorFinanceModules(),
                ...$this->administratorLaunchModules(),
                ...$this->operatorModules(),
                ...$this->waiterModules(),
                ...$this->baristaModules(),
                ...$this->chefModules(),
            ],
        );
    }

    /**
     * Maps panel/user roles onto documentation roles.
     */
    protected function normaliseRole(string $role): ?string
    {
        return match (mb_strtolower(trim($role))) {
            'administrator', 'admin', 'owner', 'manager' => self::ROLE_ADMINISTRATOR,
            'operator' => self::ROLE_OPERATOR,
            'waiter' => self::ROLE_WAITER,
            'chef' => self::ROLE_CHEF,
            'barista' => self::ROLE_BARISTA,
            default => null,
        };
    }

    /**
     * Fills the full module shape so every consumer can rely on all keys.
     *
     * @param  array<string, mixed>  $module
     * @return DocumentationModule
     */
    protected function module(array $module): array
    {
        /** @var DocumentationModule $filled */
        $filled = array_merge([
            'slug' => '',
            'title' => '',
            'group' => 'System',
            'roles' => [],
            'tags' => [],
            'overview' => '',
            'how_it_works' => [],
            'how_to_use' => [],
            'how_to_configure' => [],
            'conditions' => [],
            'examples' => [],
            'options' => [],
            'notes' => [],
            'demo_samples' => [],
            'help_anchor' => null,
        ], $module);

        return $filled;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function administratorSystemModules(): array
    {
        $administrator = [self::ROLE_ADMINISTRATOR];

        return [
            [
                'slug' => 'dashboard',
                'title' => 'Administrator Dashboard',
                'group' => 'System',
                'roles' => $administrator,
                'tags' => ['dashboard', 'kpi', 'overview', 'today'],
                'overview' => 'The landing screen of the administrator panel. It summarises today’s trading picture — orders, dining sessions, preparation load and anything waiting on a human decision.',
                'how_it_works' => [
                    'Figures are read live from orders, dining sessions and preparation tickets; nothing is cached overnight.',
                    'Attention items (payment proofs awaiting review, pending refill requests) are surfaced so nothing sits unnoticed.',
                    'Staff notifications appear in the header bell and can be marked read individually or all at once.',
                ],
                'how_to_use' => [
                    'Start the shift here, clear anything in the attention list, then move into the specific module.',
                    'Use the bell to catch events raised while you were on another screen.',
                ],
                'how_to_configure' => [
                    'Nothing to configure. What the dashboard can show depends on which features are switched on in Website Settings and config.',
                ],
                'conditions' => [
                    ['if' => 'Dine-in is disabled in Website Settings', 'then' => 'Dining figures stay empty because no sessions can be created.'],
                    ['if' => 'A payment proof is awaiting review', 'then' => 'It is listed for action until an administrator or operator confirms or rejects it.'],
                ],
                'examples' => [
                    ['title' => 'Morning check', 'body' => 'Open the dashboard, see two payment proofs awaiting review and one pending refill request, clear both, then move to the preparation queue.'],
                ],
                'notes' => [
                    'The dashboard reports; it does not change anything. Every fix happens in the owning module.',
                ],
            ],
            [
                'slug' => 'users-staff',
                'title' => 'Users & Staff',
                'group' => 'System',
                'roles' => $administrator,
                'tags' => ['users', 'staff', 'accounts', 'customers', 'blocking'],
                'overview' => 'Create and maintain the people records behind the system: internal staff who sign into a panel, and customers who order.',
                'how_it_works' => [
                    'Every user has exactly one role. The role decides which panel they can sign into and what they can do there.',
                    'Staff sign in on their own panel login page; customers sign in on the storefront and never reach an internal panel.',
                    'Customers can be blocked from ordering and later unblocked, and their loyalty balance can be adjusted from the user record.',
                ],
                'how_to_use' => [
                    'Create the account, pick the role, then confirm the person can sign in on their panel.',
                    'When someone leaves, remove or disable the account the same day — panel access follows the account, not the device.',
                    'Use block-ordering for abusive or fraudulent customers rather than deleting their history.',
                ],
                'how_to_configure' => [
                    'Roles and their panel access are defined in config/roles.php; the list in the form is generated from it.',
                    'At minimum you need one owner or manager plus the operational roles you actually run (operator, waiter, chef, barista).',
                ],
                'conditions' => [
                    ['if' => 'A staff member has no role that grants panel access', 'then' => 'They are redirected back to the login screen after signing in.'],
                    ['if' => 'A customer is blocked from ordering', 'then' => 'New orders are refused while existing history stays intact.'],
                ],
                'examples' => [
                    ['title' => 'New barista joins', 'body' => 'Create the user with the barista role, share the /barista login URL, and confirm they can see the bar queue.'],
                ],
                'options' => [
                    ['name' => 'Owner', 'what' => 'Full access to every administrator screen.', 'why' => 'The person accountable for the business.', 'when' => 'One or two people, no more.', 'example' => 'Café proprietor.'],
                    ['name' => 'Manager', 'what' => 'Administrator panel access focused on catalog and day-to-day management.', 'why' => 'Runs the shop without owning the business.', 'when' => 'Store manager or shift lead.', 'example' => 'Duty manager who edits the menu.'],
                    ['name' => 'Operator', 'what' => 'Operational panel: orders, dining, payments, refills.', 'why' => 'Runs the counter and floor without touching marketing or catalog.', 'when' => 'Counter and back-office staff.', 'example' => 'Front-desk operator confirming UPI proofs.'],
                    ['name' => 'Waiter', 'what' => 'Dining panel: tables, sessions, rounds, bills.', 'why' => 'Serves the floor.', 'when' => 'Anyone taking table orders.', 'example' => 'Floor staff opening Table 12.'],
                    ['name' => 'Chef', 'what' => 'Kitchen preparation queue only.', 'why' => 'Focus on food tickets.', 'when' => 'Kitchen staff.', 'example' => 'Cook marking a sandwich ready.'],
                    ['name' => 'Barista', 'what' => 'Bar preparation queue plus recipes, products, inventory view and refill requests.', 'why' => 'Focus on drinks and bar stock.', 'when' => 'Bar staff.', 'example' => 'Barista marking a latte ready.'],
                    ['name' => 'Cashier', 'what' => 'Defined in config/roles.php with dashboard access only.', 'why' => 'Reserved for till-only staff.', 'when' => 'Only if your workflow needs it — the operator role is the usual choice.', 'example' => 'Till operator with no floor duties.'],
                    ['name' => 'Customer', 'what' => 'Storefront account. No internal panel access.', 'why' => 'Ordering, loyalty and referrals.', 'when' => 'Every guest who registers.', 'example' => 'A regular collecting loyalty points.'],
                ],
                'notes' => [
                    'Never share one staff login between people — preparation timings and cash actions are attributed to the signed-in user.',
                    'Demo staff accounts exist only after DemoSeeder has run on local or testing.',
                ],
                'demo_samples' => ['Demo staff accounts seeded by DemoUserSeeder (local/testing only)'],
            ],
            [
                'slug' => 'roles-access',
                'title' => 'Roles & Panel Access',
                'group' => 'System',
                'roles' => $administrator,
                'tags' => ['roles', 'permissions', 'access', 'panels', 'security'],
                'overview' => 'How the five internal panels are separated. Each panel has its own URL and login, and a role can only enter the panel it belongs to.',
                'how_it_works' => [
                    'Staff authenticate on the admin guard; each panel route group additionally requires the matching role.',
                    'Signing in at the wrong panel does not grant access — the user is sent back to that panel’s login.',
                    'Owner and manager share the administrator panel; the other roles each get a purpose-built panel.',
                ],
                'how_to_use' => [
                    'Give each person the panel URL for their role and let them bookmark it.',
                    'If someone reports "access denied", check their role on the user record before changing anything else.',
                ],
                'how_to_configure' => [
                    'Role labels, guard and permissions live in config/roles.php.',
                    'Changing a person’s access is a role change on the user record, not a per-screen setting.',
                ],
                'conditions' => [
                    ['if' => 'A waiter opens the operator panel URL', 'then' => 'Access is refused; they see the operator login instead of operator data.'],
                    ['if' => 'A role has admin_access disabled', 'then' => 'No internal panel will accept that account.'],
                ],
                'examples' => [
                    ['title' => 'Chef promoted to operator', 'body' => 'Change the role to operator; the chef panel stops working for them and the operator panel starts.'],
                ],
                'options' => [
                    ['name' => '/administrator', 'what' => 'Full management panel.', 'why' => 'Catalog, marketing, loyalty, finance, launch.', 'when' => 'Owner and manager.', 'example' => 'Editing a promotion.'],
                    ['name' => '/operator', 'what' => 'Operational panel.', 'why' => 'Orders, dining, payments, inventory view, refills.', 'when' => 'Operator.', 'example' => 'Confirming a cash payment.'],
                    ['name' => '/waiter', 'what' => 'Dining floor panel.', 'why' => 'Tables, sessions, rounds, bills.', 'when' => 'Waiter.', 'example' => 'Adding Round 2 for Table 12.'],
                    ['name' => '/chef', 'what' => 'Kitchen queue panel.', 'why' => 'Food tickets only.', 'when' => 'Chef.', 'example' => 'Marking a pasta ready.'],
                    ['name' => '/barista', 'what' => 'Bar panel.', 'why' => 'Drink tickets, recipes, bar stock.', 'when' => 'Barista.', 'example' => 'Starting a cappuccino.'],
                ],
                'notes' => [
                    'Panel separation is the main safety net: it is why a waiter can never edit prices and a chef can never see revenue.',
                ],
            ],
            [
                'slug' => 'website-settings',
                'title' => 'Website Settings',
                'group' => 'System',
                'roles' => $administrator,
                'tags' => ['settings', 'content', 'hero', 'pages', 'storefront'],
                'overview' => 'The single settings screen that drives storefront content and switchable behaviour: hero, business details, payment display, fulfilment, tax, ordering guards, referral and static pages.',
                'how_it_works' => [
                    'Settings are stored as keyed records and read by the storefront, checkout, invoices and the launch readiness check.',
                    'Anything left blank is treated as "not configured" and is reported by launch readiness rather than silently guessed.',
                    'Image settings (hero, payment QR) store a path; the file must actually exist in public media.',
                ],
                'how_to_use' => [
                    'Work top to bottom once before launch, then treat it as a rarely-touched screen.',
                    'After changing anything customer-facing, load the storefront and confirm it reads correctly.',
                ],
                'how_to_configure' => [
                    'Business identity: name, short about, phone, WhatsApp number, email, address, opening hours text, timezone.',
                    'Fulfilment: dine-in on/off and the delivery disclaimer shown at checkout.',
                    'Ordering guard: manual close flag, closed-until timestamp and the message customers see.',
                    'Static pages: about, contact, FAQ, terms and privacy content.',
                ],
                'conditions' => [
                    ['if' => 'Manual close is on', 'then' => 'Ordering is blocked and customers see your closed message until the closed-until time passes.'],
                    ['if' => 'Dine-in is disabled', 'then' => 'Dining sessions and table QR ordering are unavailable regardless of table setup.'],
                    ['if' => 'A page body is empty', 'then' => 'Launch readiness flags it as missing CMS content.'],
                ],
                'examples' => [
                    ['title' => 'Emergency closure', 'body' => 'Turn on manual close, set closed-until to 6 pm and write "Back at 6 pm after a power cut" — the storefront stops accepting orders immediately.'],
                ],
                'notes' => [
                    'Timezone drives operating hours and reporting boundaries — set it once and leave it alone.',
                    'Never paste payment gateway keys or passwords into any content field; credentials belong in server configuration only.',
                ],
            ],
            [
                'slug' => 'business-settings',
                'title' => 'Business Profile & Legal Details',
                'group' => 'System',
                'roles' => $administrator,
                'tags' => ['business', 'legal', 'gstin', 'contact', 'address'],
                'overview' => 'The subset of Website Settings that identifies the business on the storefront and on every invoice: trading name, legal name, address, contact points and GSTIN.',
                'how_it_works' => [
                    'The business name, address, phone and email are shown to customers and reused on invoices and receipts.',
                    'The legal business name and GSTIN are invoice fields, kept separate from the trading name.',
                    'The WhatsApp number is used for the customer contact path where WhatsApp messaging is offered.',
                ],
                'how_to_use' => [
                    'Enter details exactly as they should appear on a tax invoice — this is a legal document, not marketing copy.',
                    'Re-check after any change of premises, phone number or registration.',
                ],
                'how_to_configure' => [
                    'Set business name, about text, phone, WhatsApp number, email, address, opening hours text and timezone.',
                    'Set the legal business name and GSTIN in the tax section when you are registered.',
                ],
                'conditions' => [
                    ['if' => 'Legal business name or GSTIN is blank while tax is enabled', 'then' => 'Launch readiness flags the invoice as incomplete for a tax invoice.'],
                    ['if' => 'Business phone or address is missing', 'then' => 'Launch readiness reports it as a required business detail.'],
                ],
                'examples' => [
                    ['title' => 'Trading name vs legal name', 'body' => 'Customers see "Coffee Corner"; the invoice shows "Coffee Corner Hospitality Pvt Ltd" with the GSTIN beside it.'],
                ],
                'notes' => [
                    'The opening hours text here is display copy. Actual ordering availability is controlled by Schedule & Hours.',
                ],
            ],
            [
                'slug' => 'payment-methods',
                'title' => 'Payment Methods',
                'group' => 'System',
                'roles' => $administrator,
                'tags' => ['payments', 'upi', 'cash', 'gateway', 'checkout'],
                'overview' => 'Which ways customers may pay, and what they see when they do. Each method is a switch in Website Settings; the display fields (UPI ID, QR, instructions) belong to the manual UPI flow.',
                'how_it_works' => [
                    'Enabled methods appear at checkout. A method that is switched off never reaches the customer.',
                    'Manual UPI is proof-based: the customer pays and uploads evidence, and staff confirm or reject it.',
                    'Cash is settled in person and recorded by staff as cash received.',
                    'Gateway methods are integration-backed and only belong on once the integration is genuinely live.',
                ],
                'how_to_use' => [
                    'Enable only the methods you can actually honour today.',
                    'For manual UPI, fill the display name, UPI ID, QR image and clear instructions before switching it on.',
                    'Review payment proofs promptly — an unreviewed proof holds up the order.',
                ],
                'how_to_configure' => [
                    'Toggle cash, manual UPI, Razorpay, PayU, Paytm and PhonePe individually.',
                    'Set the payment display name, instructions, UPI ID, payment phone, QR image path and payment WhatsApp number for the manual flow.',
                ],
                'conditions' => [
                    ['if' => 'Manual UPI is enabled but the UPI ID and QR are blank', 'then' => 'Launch readiness flags it and customers have nothing to pay against.'],
                    ['if' => 'No payment method is enabled', 'then' => 'Checkout cannot complete.'],
                    ['if' => 'A proof is rejected', 'then' => 'The payment returns to pending and the customer is asked to pay again.'],
                ],
                'examples' => [
                    ['title' => 'Small café setup', 'body' => 'Enable cash and manual UPI only, leave all four gateways off, and review proofs from the orders screen.'],
                ],
                'options' => [
                    ['name' => 'Cash', 'what' => 'Paid in person, recorded by staff.', 'why' => 'Zero setup and no fees.', 'when' => 'Counter and dine-in.', 'example' => 'Operator records ₹420 cash received.'],
                    ['name' => 'Manual (UPI proof)', 'what' => 'Customer pays to your UPI ID and uploads proof for review.', 'why' => 'Digital payment without a gateway contract.', 'when' => 'Before gateway onboarding is complete.', 'example' => 'Customer uploads a UPI screenshot; operator confirms it.'],
                    ['name' => 'Razorpay', 'what' => 'Gateway-backed payment method.', 'why' => 'Automatic confirmation instead of manual review.', 'when' => 'Only when the integration is configured and tested.', 'example' => 'Enabled after a successful test transaction.'],
                    ['name' => 'PayU', 'what' => 'Gateway-backed payment method.', 'why' => 'Alternative gateway.', 'when' => 'Only when configured and tested.', 'example' => 'Kept off until go-live.'],
                    ['name' => 'Paytm', 'what' => 'Gateway-backed payment method.', 'why' => 'Alternative gateway.', 'when' => 'Only when configured and tested.', 'example' => 'Kept off until go-live.'],
                    ['name' => 'PhonePe', 'what' => 'Gateway-backed payment method.', 'why' => 'Alternative gateway.', 'when' => 'Only when configured and tested.', 'example' => 'Kept off until go-live.'],
                ],
                'notes' => [
                    'Never enter gateway keys or merchant secrets in this screen — those live in server configuration.',
                    'Enabling a gateway you have not configured produces failed checkouts, not a fallback.',
                ],
            ],
            [
                'slug' => 'realtime-diagnostics',
                'title' => 'Realtime & Diagnostics',
                'group' => 'System',
                'roles' => $administrator,
                'tags' => ['realtime', 'websockets', 'broadcast', 'health', 'troubleshooting'],
                'overview' => 'The live-update layer behind the panels: new orders, preparation transitions, waiter calls and payment events push to open screens instead of waiting for a refresh.',
                'how_it_works' => [
                    'Events are broadcast on private channels; each panel subscribes only to the channels its role is authorised for.',
                    'A health check inspects the broadcast configuration and can dispatch a probe event to prove the path end to end.',
                    'When broadcasting is unavailable the panels still work — staff simply have to refresh to see changes.',
                ],
                'how_to_use' => [
                    'If staff report "the queue does not update", run the health check before blaming the device.',
                    'Confirm the queue worker and broadcast server are running; most live-update faults are one of those two.',
                ],
                'how_to_configure' => [
                    'Broadcasting is configured server-side (config/broadcasting.php and config/reverb.php), not from a panel screen.',
                    'Run `php artisan coffee:realtime-health` to inspect the configuration and delivery path.',
                ],
                'conditions' => [
                    ['if' => 'The broadcast server is down', 'then' => 'Panels fall back to manual refresh; no orders or tickets are lost.'],
                    ['if' => 'The queue worker is stopped', 'then' => 'Queued notifications and broadcasts pile up until it restarts.'],
                ],
                'examples' => [
                    ['title' => 'Bar screen looks frozen', 'body' => 'Run the realtime health command, find the broadcast connection failing, restart the service — the queue resumes updating live.'],
                ],
                'notes' => [
                    'Live updates are a convenience layer. Order and preparation state is always stored in the database first.',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function administratorCatalogModules(): array
    {
        $administrator = [self::ROLE_ADMINISTRATOR];

        return [
            [
                'slug' => 'categories',
                'title' => 'Product Categories',
                'group' => 'Catalog',
                'roles' => $administrator,
                'tags' => ['categories', 'menu', 'navigation', 'catalog'],
                'overview' => 'The top-level grouping customers browse by, and the grouping used for category-scoped promotions, loyalty rewards and merchandising.',
                'how_it_works' => [
                    'Each product belongs to a category; the storefront menu is built from active categories and their products.',
                    'Categories carry a slug used in storefront URLs and in targeting rules such as category affinity.',
                    'Category order controls the order customers see on the menu.',
                ],
                'how_to_use' => [
                    'Keep the list short and shopper-shaped — how customers think, not how the kitchen is organised.',
                    'Create the category before the products that belong in it.',
                ],
                'how_to_configure' => [
                    'Set the name, slug, description and display order, and mark it active when it is ready to show.',
                    'Reuse the slug in targeting rules and campaign placements rather than inventing new labels.',
                ],
                'conditions' => [
                    ['if' => 'A category has no active products', 'then' => 'It renders empty on the menu — deactivate it until it has stock.'],
                    ['if' => 'You rename a slug', 'then' => 'Rules and links that referenced the old slug must be updated.'],
                ],
                'examples' => [
                    ['title' => 'Seasonal grouping', 'body' => 'Add a "Cold Brew" category, move three drinks into it, then run a category-scoped promotion on it for summer.'],
                ],
                'notes' => [
                    'Deleting a category that promotions or rewards point at breaks those links — deactivate rather than delete.',
                ],
            ],
            [
                'slug' => 'flavours',
                'title' => 'Flavours',
                'group' => 'Catalog',
                'roles' => $administrator,
                'tags' => ['flavours', 'taste', 'filters', 'catalog'],
                'overview' => 'Descriptive taste attributes attached to products. Flavours help customers filter the menu and feed flavour-affinity personalisation.',
                'how_it_works' => [
                    'A product can carry several flavours; the storefront exposes them as browse and filter aids.',
                    'Flavour affinity is one of the targeting signals available to segments and campaigns.',
                ],
                'how_to_use' => [
                    'Use a small, consistent vocabulary — five to ten flavours beats fifty.',
                    'Apply flavours to products as you create them, not as an afterthought.',
                ],
                'how_to_configure' => [
                    'Create each flavour with a clear customer-facing name and slug.',
                    'Attach flavours from the product form.',
                ],
                'conditions' => [
                    ['if' => 'Flavours are inconsistent across similar products', 'then' => 'Filters and affinity targeting return misleading results.'],
                ],
                'examples' => [
                    ['title' => 'Vanilla affinity', 'body' => 'Tag three drinks with "Vanilla", then build a segment on vanilla flavour affinity for a launch campaign.'],
                ],
                'notes' => [
                    'Flavours are descriptive only — they never change price or preparation.',
                ],
            ],
            [
                'slug' => 'tags',
                'title' => 'Product Tags',
                'group' => 'Catalog',
                'roles' => $administrator,
                'tags' => ['tags', 'badges', 'labels', 'merchandising'],
                'overview' => 'Short marketing labels shown on product cards — "Bestseller", "New", "Chef’s pick" — with a visual style per tag.',
                'how_it_works' => [
                    'Tags are attached to products and rendered as badges on the storefront.',
                    'Each tag has a style that controls how the badge looks.',
                    'Tag-sourced homepage sections can pull products by tag automatically.',
                ],
                'how_to_use' => [
                    'Use tags for claims you can defend and remove them when they stop being true.',
                    'Limit each product to one or two badges so they keep meaning something.',
                ],
                'how_to_configure' => [
                    'Create the tag with a name, slug and style, then attach it from the product form.',
                ],
                'conditions' => [
                    ['if' => 'Every product carries a "Bestseller" tag', 'then' => 'The badge stops influencing anyone.'],
                    ['if' => 'A homepage section is sourced from a tag', 'then' => 'Removing the tag from a product silently removes it from that section.'],
                ],
                'examples' => [
                    ['title' => 'New arrival window', 'body' => 'Tag a product "New" for its first two weeks, then remove the tag and let it stand on sales.'],
                ],
                'notes' => [
                    'Tags are cosmetic and merchandising signals — they do not affect pricing or availability.',
                ],
            ],
            [
                'slug' => 'ingredients',
                'title' => 'Ingredients',
                'group' => 'Catalog',
                'roles' => $administrator,
                'tags' => ['ingredients', 'stock', 'units', 'brands', 'recipes'],
                'overview' => 'The raw materials behind every product: what you hold, in which unit, from which brand, and how low stock is allowed to fall before you are warned.',
                'how_it_works' => [
                    'Each ingredient has a unit of measure and a stock level maintained by inventory movements.',
                    'Ingredients are grouped by ingredient category and can be attributed to an ingredient brand.',
                    'Recipes consume ingredients, which is how a sale reduces stock.',
                ],
                'how_to_use' => [
                    'Create ingredients before recipes; a recipe cannot consume something that does not exist.',
                    'Pick the unit you actually count in and never change it casually — history is recorded in that unit.',
                    'Set a realistic low-stock threshold so warnings are actionable rather than constant.',
                ],
                'how_to_configure' => [
                    'Set name, ingredient category, brand, unit of measure and low-stock threshold.',
                    'Record the opening stock through an inventory movement rather than typing a balance.',
                ],
                'conditions' => [
                    ['if' => 'Stock falls to or below the low-stock threshold', 'then' => 'The ingredient is reported as low stock in the inventory view.'],
                    ['if' => 'Stock reaches zero', 'then' => 'It is reported as out of stock and dependent products are at risk.'],
                ],
                'examples' => [
                    ['title' => 'Milk in litres', 'body' => 'Hold milk in L with a 5 L low-stock threshold; a latte recipe consuming 150 ml draws it down automatically per sale.'],
                ],
                'options' => [
                    ['name' => 'g / kg', 'what' => 'Weight units.', 'why' => 'Dry goods measured by weight.', 'when' => 'Coffee beans, sugar, flour.', 'example' => 'Beans held in kg, recipes consume g.'],
                    ['name' => 'ml / L', 'what' => 'Volume units.', 'why' => 'Liquids.', 'when' => 'Milk, syrups, juices.', 'example' => 'Milk held in L, recipes consume ml.'],
                    ['name' => 'piece', 'what' => 'Countable items.', 'why' => 'Things you count individually.', 'when' => 'Cookies, straws, cups.', 'example' => 'Paper cups counted per piece.'],
                    ['name' => 'bottle', 'what' => 'Sealed bottle unit.', 'why' => 'Bought and issued whole.', 'when' => 'Bottled water, sauces.', 'example' => 'Bottled water counted per bottle.'],
                    ['name' => 'pack', 'what' => 'Packaged unit.', 'why' => 'Bought and issued as a pack.', 'when' => 'Tea sachets, napkins.', 'example' => 'Tea sachets counted per pack.'],
                ],
                'notes' => [
                    'Changing an ingredient unit after movements exist makes historical quantities misleading — create a new ingredient instead.',
                ],
            ],
            [
                'slug' => 'inventory',
                'title' => 'Inventory & Stock Movements',
                'group' => 'Catalog',
                'roles' => $administrator,
                'tags' => ['inventory', 'stock', 'movements', 'history', 'consumption'],
                'overview' => 'The stock ledger. Every increase and decrease is recorded as a movement, so the current level is always explainable.',
                'how_it_works' => [
                    'Purchases and corrections are entered manually; sales consume stock automatically through recipes.',
                    'Each ingredient shows a status derived from its level and threshold: in stock, low stock or out of stock.',
                    'The history view lists movements so you can trace how a level was reached.',
                ],
                'how_to_use' => [
                    'Record deliveries the day they arrive; a late entry produces a false shortage warning.',
                    'Use the history view to investigate a discrepancy before adjusting a level.',
                    'Investigate repeated wastage rather than absorbing it as an adjustment.',
                ],
                'how_to_configure' => [
                    'Create movements from the inventory screen, choosing the movement type and quantity.',
                    'Tune low-stock thresholds on the ingredient once you know real consumption.',
                ],
                'conditions' => [
                    ['if' => 'An order is placed for a product whose recipe consumes an ingredient', 'then' => 'Stock is drawn down against that order.'],
                    ['if' => 'An ingredient is out of stock', 'then' => 'Bar and kitchen staff should raise a refill request rather than improvising.'],
                ],
                'examples' => [
                    ['title' => 'Weekly delivery', 'body' => 'Record 20 L of milk as an incoming movement; the level rises, the low-stock warning clears, and the history shows who entered it.'],
                ],
                'options' => [
                    ['name' => 'In stock', 'what' => 'Level is above the low-stock threshold.', 'why' => 'Normal trading state.', 'when' => 'No action required.', 'example' => '18 L of milk against a 5 L threshold.'],
                    ['name' => 'Low stock', 'what' => 'Level is at or below the threshold.', 'why' => 'Early warning before you run out.', 'when' => 'Order more or raise a refill.', 'example' => '4 L of milk against a 5 L threshold.'],
                    ['name' => 'Out of stock', 'what' => 'Level is zero.', 'why' => 'Dependent products cannot be made.', 'when' => 'Restock immediately.', 'example' => '0 L of oat milk.'],
                ],
                'notes' => [
                    'Never "fix" a level by editing the ingredient — record a movement so the ledger stays truthful.',
                ],
            ],
            [
                'slug' => 'products',
                'title' => 'Products',
                'group' => 'Catalog',
                'roles' => $administrator,
                'tags' => ['products', 'menu', 'pricing', 'availability', 'catalog'],
                'overview' => 'The items customers buy. A product carries its name, description, category, type, price, media, availability and the station that prepares it.',
                'how_it_works' => [
                    'Products are either beverages or food; the type drives the default preparation station.',
                    'A product may sell as a single item or through variants such as sizes.',
                    'Only active products appear on the storefront; deactivating removes the item without deleting its history.',
                ],
                'how_to_use' => [
                    'Create the category, ingredients and recipe first, then the product.',
                    'Write the description for a hungry customer, not for an internal checklist.',
                    'Deactivate rather than delete when something is temporarily unavailable.',
                ],
                'how_to_configure' => [
                    'Set name, category, product type, price, serving size and unit, description and images.',
                    'Attach flavours, tags and add-ons, and confirm the preparation station.',
                ],
                'conditions' => [
                    ['if' => 'A product is inactive', 'then' => 'It disappears from the storefront but existing orders and reports keep it.'],
                    ['if' => 'A product has variants', 'then' => 'Price and recipe are resolved per variant, not from the base product alone.'],
                    ['if' => 'A required ingredient is out of stock', 'then' => 'Staff should expect the item to be unmakeable even though it still lists.'],
                ],
                'examples' => [
                    ['title' => 'Adding a cold brew', 'body' => 'Create the product as a beverage in "Cold Brew", set 250 ml serving, attach the recipe, set the bar station, upload one good photo, activate.'],
                ],
                'options' => [
                    ['name' => 'Beverage', 'what' => 'Drink product.', 'why' => 'Routes to the bar queue by default.', 'when' => 'Coffee, tea, juices, shakes.', 'example' => 'Cappuccino.'],
                    ['name' => 'Food', 'what' => 'Food product.', 'why' => 'Routes to the kitchen queue by default.', 'when' => 'Sandwiches, pasta, desserts.', 'example' => 'Grilled sandwich.'],
                    ['name' => 'Serving unit ml / g / piece', 'what' => 'How the serving size is expressed.', 'why' => 'Sets customer expectation and supports recipe sizing.', 'when' => 'Every product.', 'example' => '250 ml latte; 180 g pasta; 1 piece brownie.'],
                ],
                'notes' => [
                    'A product with no recipe still sells — it simply consumes no stock, so inventory will drift.',
                ],
            ],
            [
                'slug' => 'variants',
                'title' => 'Product Variants',
                'group' => 'Catalog',
                'roles' => $administrator,
                'tags' => ['variants', 'sizes', 'pricing', 'options'],
                'overview' => 'Sellable versions of one product — typically sizes — each with its own price and its own recipe consumption.',
                'how_it_works' => [
                    'Variants belong to a product and are chosen by the customer before adding to cart.',
                    'Each variant can carry its own recipe lines so a large drink consumes more than a small one.',
                    'Order lines record the variant name, so tickets and invoices show exactly what was sold.',
                ],
                'how_to_use' => [
                    'Use variants only for genuinely different sizes or formats of the same item.',
                    'Set every variant’s recipe — otherwise a large sells at a large price and consumes a small’s stock.',
                ],
                'how_to_configure' => [
                    'Add each variant with its name, price and display order on the product.',
                    'Define recipe consumption per variant.',
                ],
                'conditions' => [
                    ['if' => 'A variant has no recipe', 'then' => 'Selling it consumes nothing and inventory silently overstates stock.'],
                    ['if' => 'A product has variants', 'then' => 'Customers must pick one before the item can be added to the cart.'],
                ],
                'examples' => [
                    ['title' => 'Two sizes', 'body' => 'Cappuccino Regular (180 ml, ₹150) and Large (250 ml, ₹190), with the large consuming proportionally more milk and coffee.'],
                ],
                'notes' => [
                    'Do not model a completely different drink as a variant — create a separate product instead.',
                ],
            ],
            [
                'slug' => 'recipes',
                'title' => 'Recipes',
                'group' => 'Catalog',
                'roles' => $administrator,
                'tags' => ['recipes', 'consumption', 'ingredients', 'preparation'],
                'overview' => 'The link between what you sell and what you use. A recipe lists the ingredients and quantities consumed when an item is sold.',
                'how_it_works' => [
                    'Recipe lines name an ingredient and a quantity in that ingredient’s unit.',
                    'Recipes attach to products, variants and add-ons, so each sellable combination consumes correctly.',
                    'Completing a sale writes consumption against the order, which is what keeps stock honest.',
                ],
                'how_to_use' => [
                    'Write recipes from the actual bar or kitchen spec, not from an estimate.',
                    'Review a recipe whenever the spec changes; stale recipes cause stock drift, not just bad drinks.',
                ],
                'how_to_configure' => [
                    'Create a recipe, add one line per ingredient with the exact quantity, and attach it to the product, variant or add-on.',
                ],
                'conditions' => [
                    ['if' => 'A recipe quantity is wrong', 'then' => 'Stock drifts in that direction on every single sale.'],
                    ['if' => 'An add-on has no recipe', 'then' => 'Extras are given away without ever reducing stock.'],
                ],
                'examples' => [
                    ['title' => 'Latte spec', 'body' => 'Espresso 18 g, milk 150 ml. Selling 40 lattes consumes 720 g of coffee and 6 L of milk without anyone counting.'],
                ],
                'notes' => [
                    'Baristas can read recipes in their panel — keep instructions accurate and free of internal cost notes.',
                ],
            ],
            [
                'slug' => 'add-ons',
                'title' => 'Add-ons',
                'group' => 'Catalog',
                'roles' => $administrator,
                'tags' => ['add-ons', 'extras', 'upsell', 'customisation'],
                'overview' => 'Paid extras a customer can attach to an item — an extra shot, oat milk, a syrup. Each add-on has its own price and its own stock consumption.',
                'how_it_works' => [
                    'Add-ons are linked to the products they are valid for, so irrelevant extras never appear.',
                    'Chosen add-ons are recorded on the order line, printed on the preparation ticket and priced on the invoice.',
                    'Add-ons carry their own recipe lines, including per-variant consumption where it differs.',
                ],
                'how_to_use' => [
                    'Keep the list per product short — three or four meaningful extras convert better than a wall of options.',
                    'Price add-ons to cover the ingredient plus handling.',
                ],
                'how_to_configure' => [
                    'Create the add-on with a name and price, attach it to the relevant products, and set its recipe consumption.',
                ],
                'conditions' => [
                    ['if' => 'An add-on is attached to a product', 'then' => 'It appears as an option for that product only.'],
                    ['if' => 'An add-on has no recipe', 'then' => 'It earns revenue but never reduces stock.'],
                ],
                'examples' => [
                    ['title' => 'Extra shot', 'body' => '₹40 extra shot attached to every espresso-based drink, consuming 18 g of beans. The bar ticket shows "+ Extra shot ×1".'],
                ],
                'notes' => [
                    'Add-ons are not a discount mechanism. Free items belong to loyalty rewards, not to add-on pricing.',
                ],
            ],
            [
                'slug' => 'product-media',
                'title' => 'Product Media',
                'group' => 'Catalog',
                'roles' => $administrator,
                'tags' => ['images', 'photos', 'media', 'storefront'],
                'overview' => 'The imagery customers judge your menu by: product photos plus the hero image and payment QR used elsewhere on the site.',
                'how_it_works' => [
                    'Uploaded images are stored in public media and referenced by path from the product or setting.',
                    'Launch readiness checks that referenced image files actually exist on disk.',
                    'Products without an image render with a placeholder and convert noticeably worse.',
                ],
                'how_to_use' => [
                    'Shoot in consistent lighting and crop to the same aspect ratio across the menu.',
                    'Replace an image rather than uploading a second one alongside it.',
                ],
                'how_to_configure' => [
                    'Upload from the product form; upload the hero and payment QR from Website Settings.',
                    'Compress before uploading — large files slow the menu on mobile data.',
                ],
                'conditions' => [
                    ['if' => 'A referenced image file is missing from disk', 'then' => 'Launch readiness reports it and the storefront shows a broken slot.'],
                    ['if' => 'A product has no image', 'then' => 'It still sells but is visibly weaker on the menu.'],
                ],
                'examples' => [
                    ['title' => 'Menu refresh', 'body' => 'Re-shoot the top ten sellers at the same angle and replace their images; the menu reads as one coherent set.'],
                ],
                'notes' => [
                    'Only upload photography you own or are licensed to use.',
                ],
            ],
            [
                'slug' => 'preparation-station',
                'title' => 'Preparation Station Routing',
                'group' => 'Catalog',
                'roles' => $administrator,
                'tags' => ['station', 'bar', 'kitchen', 'routing', 'tickets'],
                'overview' => 'Which team makes what. Every product is routed to the bar or the kitchen, and that routing decides which queue the ticket lands in.',
                'how_it_works' => [
                    'An order is split into station tickets: bar items go to the barista queue, kitchen items to the chef queue.',
                    'A mixed order produces two tickets that progress independently.',
                    'Each ticket moves through pending, accepted, preparing and ready on its own.',
                ],
                'how_to_use' => [
                    'Check the station on every new product before activating it.',
                    'Audit routing whenever tickets show up in the wrong queue — it is a catalog problem, not a staff problem.',
                ],
                'how_to_configure' => [
                    'Set the station on the product; beverages default to bar and food to kitchen.',
                ],
                'conditions' => [
                    ['if' => 'An order contains both bar and kitchen items', 'then' => 'Two tickets are created and both must reach ready before the whole order is served together.'],
                    ['if' => 'A food item is routed to the bar', 'then' => 'The kitchen never sees it and the order stalls.'],
                ],
                'examples' => [
                    ['title' => 'Mixed table order', 'body' => 'Two cappuccinos and one sandwich create a bar ticket with the drinks and a kitchen ticket with the sandwich.'],
                ],
                'options' => [
                    ['name' => 'Bar', 'what' => 'Barista queue.', 'why' => 'Drinks are made at the bar.', 'when' => 'Beverages.', 'example' => 'Cappuccino, cold brew, iced tea.'],
                    ['name' => 'Kitchen', 'what' => 'Chef queue.', 'why' => 'Food is made in the kitchen.', 'when' => 'Food items.', 'example' => 'Sandwich, pasta, brownie.'],
                ],
                'notes' => [
                    'Station routing is the single most common cause of "the order never arrived" — verify it first.',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function administratorOrderModules(): array
    {
        $administrator = [self::ROLE_ADMINISTRATOR];

        return [
            [
                'slug' => 'retail-orders',
                'title' => 'Retail Orders (Takeaway & Delivery)',
                'group' => 'Orders',
                'roles' => $administrator,
                'tags' => ['orders', 'takeaway', 'delivery', 'status', 'counter'],
                'overview' => 'Orders placed for takeaway or delivery, plus counter orders raised by staff. This screen is the full lifecycle from payment through completion.',
                'how_it_works' => [
                    'An order carries a status that advances as payment clears and the stations work through it.',
                    'Staff can create an order directly from the panel for walk-ins and phone orders.',
                    'Payment, preparation and invoicing all hang off the order record.',
                ],
                'how_to_use' => [
                    'Work the list oldest first and clear anything blocked on a payment decision.',
                    'Open an order to see items, add-ons, payment state and station tickets in one place.',
                    'Print or download the invoice from the order once payment is confirmed.',
                ],
                'how_to_configure' => [
                    'Nothing per order. Enabled fulfilment methods, payment methods and ordering guards come from Website Settings.',
                ],
                'conditions' => [
                    ['if' => 'Payment is still pending', 'then' => 'The order waits and no station ticket work is expected.'],
                    ['if' => 'A pending order is left unpaid past its window', 'then' => 'It is expired automatically rather than sitting forever.'],
                    ['if' => 'Every station ticket is ready', 'then' => 'The order can be handed over and completed.'],
                ],
                'examples' => [
                    ['title' => 'Phone order', 'body' => 'Create the order at the counter, take cash, record cash received, and the bar ticket appears immediately.'],
                ],
                'options' => [
                    ['name' => 'Pending payment', 'what' => 'Order placed, money not settled.', 'why' => 'Holds the order until payment clears.', 'when' => 'Immediately after checkout.', 'example' => 'Customer has not uploaded UPI proof yet.'],
                    ['name' => 'Payment confirmed', 'what' => 'Money is settled.', 'why' => 'Releases the order to the stations.', 'when' => 'After cash received or proof confirmed.', 'example' => 'Operator confirms a UPI screenshot.'],
                    ['name' => 'Accepted', 'what' => 'The café has taken the order on.', 'why' => 'Signals work has begun.', 'when' => 'A station accepts its ticket.', 'example' => 'Barista accepts the drinks ticket.'],
                    ['name' => 'Preparing', 'what' => 'Being made now.', 'why' => 'Shows live progress.', 'when' => 'A station starts preparing.', 'example' => 'Steaming milk for the latte.'],
                    ['name' => 'Ready for pickup', 'what' => 'Everything is made.', 'why' => 'Prompts handover.', 'when' => 'All station tickets are ready.', 'example' => 'Drinks on the pass, customer called.'],
                    ['name' => 'Completed', 'what' => 'Handed over and closed.', 'why' => 'Final good state; counts in revenue.', 'when' => 'Customer has the order.', 'example' => 'Takeaway collected.'],
                    ['name' => 'Cancelled', 'what' => 'Stopped after being placed.', 'why' => 'Records that it will not be fulfilled.', 'when' => 'Customer or café cancels.', 'example' => 'Customer changed their mind before preparation.'],
                    ['name' => 'Rejected', 'what' => 'Refused by the café.', 'why' => 'Distinguishes refusal from cancellation.', 'when' => 'Cannot be fulfilled at all.', 'example' => 'Item unavailable and no substitute accepted.'],
                ],
                'notes' => [
                    'Completed is a revenue event. Do not mark an order complete before the customer physically has it.',
                ],
                'demo_samples' => ['Demo retail orders seeded by DemoOrderSeeder (local/testing only)'],
            ],
            [
                'slug' => 'dining-sessions',
                'title' => 'Dining Sessions',
                'group' => 'Orders',
                'roles' => $administrator,
                'tags' => ['dining', 'dine-in', 'tables', 'sessions', 'bill'],
                'overview' => 'A dining session is one table’s whole visit. It opens when guests sit, collects every round they order, and closes once the bill is settled.',
                'how_it_works' => [
                    'One session belongs to one table and stays open across multiple rounds.',
                    'The running bill accumulates as rounds are added, so the total is always current.',
                    'Session status tracks the visit: open, billing requested, awaiting payment, paid, closed — or cancelled if the visit never really started.',
                ],
                'how_to_use' => [
                    'Use this screen to supervise the floor and intervene on anything stuck.',
                    'Open a session to see its rounds, running total, payment state and invoice.',
                    'Reopen a session only to correct a genuine mistake, and note why.',
                ],
                'how_to_configure' => [
                    'Dine-in must be enabled in Website Settings and tables must exist before sessions can be created.',
                ],
                'conditions' => [
                    ['if' => 'Dine-in is disabled', 'then' => 'No new sessions can be opened.'],
                    ['if' => 'A guest requests the bill', 'then' => 'The session moves to billing requested and the table shows bill requested.'],
                    ['if' => 'The session is paid and closed', 'then' => 'The table returns to available for the next guests.'],
                ],
                'examples' => [
                    ['title' => 'Long lunch', 'body' => 'Table 12 opens at 1 pm, orders three rounds over an hour, requests the bill, pays by cash, and the session closes with one invoice covering all three rounds.'],
                ],
                'options' => [
                    ['name' => 'Open', 'what' => 'Guests are seated and ordering.', 'why' => 'Normal service state.', 'when' => 'From seating until the bill is asked for.', 'example' => 'Table 12 mid-meal.'],
                    ['name' => 'Billing requested', 'what' => 'Guests have asked for the bill.', 'why' => 'Cues staff to bring it.', 'when' => 'End of the meal.', 'example' => 'Waiter taps request bill.'],
                    ['name' => 'Awaiting payment', 'what' => 'Bill presented, money not settled.', 'why' => 'Separates presenting from receiving.', 'when' => 'Between bill and payment.', 'example' => 'Guests fetching a card.'],
                    ['name' => 'Paid', 'what' => 'Money received.', 'why' => 'Payment is done, table not yet released.', 'when' => 'After cash or confirmed digital payment.', 'example' => '₹1,240 cash received.'],
                    ['name' => 'Closed', 'what' => 'Session finished and table released.', 'why' => 'Final good state.', 'when' => 'After payment and clearing.', 'example' => 'Table 12 available again.'],
                    ['name' => 'Cancelled', 'what' => 'Session ended without a real visit.', 'why' => 'Keeps opened-in-error sessions out of revenue.', 'when' => 'Opened on the wrong table.', 'example' => 'Session cancelled with nothing ordered.'],
                ],
                'notes' => [
                    'Leaving sessions open after guests leave makes tables look occupied and blocks seating.',
                ],
                'demo_samples' => ['Demo dining sessions seeded by DemoDiningSeeder (local/testing only)'],
            ],
            [
                'slug' => 'dining-rounds',
                'title' => 'Dining Rounds',
                'group' => 'Orders',
                'roles' => $administrator,
                'tags' => ['rounds', 'dining', 'orders', 'sequence'],
                'overview' => 'A round is one batch of items ordered within a session. Rounds are numbered in sequence, so a table can keep ordering without opening a new bill.',
                'how_it_works' => [
                    'Each round becomes its own order inside the session and generates its own station tickets.',
                    'Rounds are numbered per session, so tickets show table plus round number.',
                    'Rounds can be marked served individually, and cancelled individually with a recorded reason.',
                ],
                'how_to_use' => [
                    'Read the round number on a ticket to know which part of the table’s meal it belongs to.',
                    'Cancel a single round rather than the whole session when only one batch is wrong.',
                ],
                'how_to_configure' => [
                    'No configuration. Rounds are created by staff from the waiter or administrator dining screens.',
                ],
                'conditions' => [
                    ['if' => 'A new round is added to an open session', 'then' => 'It receives the next round number and its own station tickets.'],
                    ['if' => 'A round is cancelled', 'then' => 'Its value leaves the running bill and the cancellation reason is stored.'],
                    ['if' => 'A round contains bar and kitchen items', 'then' => 'That round alone produces two tickets.'],
                ],
                'examples' => [
                    ['title' => 'Three rounds, one bill', 'body' => 'Round 1 two coffees, Round 2 a sandwich, Round 3 a dessert — three sets of tickets, one running bill, one invoice.'],
                ],
                'notes' => [
                    'Rounds are never renumbered. A cancelled Round 2 stays as Round 2 in the history.',
                ],
            ],
            [
                'slug' => 'preparation',
                'title' => 'Preparation Oversight',
                'group' => 'Orders',
                'roles' => $administrator,
                'tags' => ['preparation', 'tickets', 'stations', 'queue', 'timing'],
                'overview' => 'The station view from a management angle: which tickets exist, where they are stuck and how long each stage is taking.',
                'how_it_works' => [
                    'Every ticket belongs to one station and moves through pending, accepted, preparing and ready.',
                    'Timestamps are recorded at each transition, which is what powers queue age and stage timings.',
                    'A cancelled order cancels its tickets so stations stop working on it.',
                ],
                'how_to_use' => [
                    'Watch for tickets sitting in pending — that means nobody has picked them up.',
                    'Compare bar and kitchen stage times to find the real bottleneck before adding staff.',
                ],
                'how_to_configure' => [
                    'Ticket routing follows the product’s preparation station; there is no separate ticket setting.',
                ],
                'conditions' => [
                    ['if' => 'A ticket stays pending', 'then' => 'No station has accepted it and the order is not being made.'],
                    ['if' => 'One station is ready and the other is not', 'then' => 'The order waits so the table is served together.'],
                    ['if' => 'The order is cancelled', 'then' => 'Its tickets are cancelled and disappear from the active queues.'],
                ],
                'examples' => [
                    ['title' => 'Finding the bottleneck', 'body' => 'Drinks average two minutes, food averages fourteen. The kitchen is the constraint — adjust prep or staffing there, not at the bar.'],
                ],
                'options' => [
                    ['name' => 'Pending', 'what' => 'Ticket created, nobody has taken it.', 'why' => 'Shows unclaimed work.', 'when' => 'Right after the order is confirmed.', 'example' => 'New bar ticket on the screen.'],
                    ['name' => 'Accepted', 'what' => 'A station has taken ownership.', 'why' => 'Prevents two people making the same item.', 'when' => 'Staff tap accept.', 'example' => 'Barista accepts the ticket.'],
                    ['name' => 'Preparing', 'what' => 'Actively being made.', 'why' => 'Drives live progress for the customer.', 'when' => 'Work actually starts.', 'example' => 'Pulling the espresso shot.'],
                    ['name' => 'Ready', 'what' => 'Finished at that station.', 'why' => 'Signals the floor to collect.', 'when' => 'Item is on the pass.', 'example' => 'Two lattes ready for Table 12.'],
                    ['name' => 'Cancelled', 'what' => 'Ticket stopped.', 'why' => 'Stops wasted work.', 'when' => 'Order or round is cancelled.', 'example' => 'Guest cancels before preparation.'],
                ],
                'notes' => [
                    'Marking ready before the item is actually finished destroys the value of the whole timing dataset.',
                ],
            ],
            [
                'slug' => 'payment-verification',
                'title' => 'Payment Verification',
                'group' => 'Orders',
                'roles' => $administrator,
                'tags' => ['payments', 'proof', 'upi', 'cash', 'verification'],
                'overview' => 'How money is confirmed. Cash is recorded by staff; manual UPI is proof-based and must be reviewed before the order proceeds.',
                'how_it_works' => [
                    'Payment moves through pending, awaiting review, and then confirmed or rejected.',
                    'A customer-uploaded proof puts the payment into awaiting review and appears on the order for a decision.',
                    'Confirming releases the order to the stations; rejecting sends it back to pending so the customer can pay again.',
                ],
                'how_to_use' => [
                    'Open the proof and check the amount, the receiving UPI ID and the timestamp before confirming.',
                    'Reject anything you cannot verify — confirming a bad proof gives away the order.',
                    'Record cash at the moment you take it, not at the end of the shift.',
                ],
                'how_to_configure' => [
                    'Enable the methods you accept and complete the manual UPI display fields in Website Settings.',
                ],
                'conditions' => [
                    ['if' => 'A proof is awaiting review', 'then' => 'The order stays blocked until someone decides.'],
                    ['if' => 'A proof is rejected', 'then' => 'Payment returns to pending and the customer is asked to pay again.'],
                    ['if' => 'Cash is received', 'then' => 'Payment is confirmed and the order is released.'],
                ],
                'examples' => [
                    ['title' => 'Wrong amount', 'body' => 'Proof shows ₹200 against a ₹420 order. Reject it, tell the customer the shortfall, and confirm once the correct payment arrives.'],
                ],
                'options' => [
                    ['name' => 'Pending', 'what' => 'No money yet.', 'why' => 'Starting state.', 'when' => 'Straight after checkout.', 'example' => 'Customer still at the UPI app.'],
                    ['name' => 'Awaiting review', 'what' => 'Proof uploaded, staff decision needed.', 'why' => 'Human verification of manual payments.', 'when' => 'Manual UPI flow.', 'example' => 'Screenshot uploaded at 2:14 pm.'],
                    ['name' => 'Confirmed', 'what' => 'Money accepted.', 'why' => 'Releases the order.', 'when' => 'Cash taken or proof verified.', 'example' => '₹420 confirmed.'],
                    ['name' => 'Rejected', 'what' => 'Payment refused.', 'why' => 'Protects against false proofs.', 'when' => 'Amount or reference does not match.', 'example' => 'Screenshot is for a different merchant.'],
                ],
                'notes' => [
                    'Never record payment credentials, card numbers or gateway secrets in notes. Store only the decision.',
                ],
            ],
            [
                'slug' => 'cancellation',
                'title' => 'Cancellations',
                'group' => 'Orders',
                'roles' => $administrator,
                'tags' => ['cancel', 'refund', 'reasons', 'rounds', 'orders'],
                'overview' => 'Stopping work that should not continue — a whole order, or a single dining round — with a recorded reason so patterns can be seen later.',
                'how_it_works' => [
                    'Cancelling an order cancels its station tickets so the bar and kitchen stop immediately.',
                    'Cancelling a dining round removes that round’s value from the running bill and keeps the rest of the session intact.',
                    'Every dining round cancellation stores a reason from a fixed list.',
                ],
                'how_to_use' => [
                    'Cancel as early as possible; a cancellation after the item is made is waste, not a save.',
                    'Pick the reason that is actually true — the reason list is a diagnostic tool.',
                    'Cancel the specific round rather than the whole session when only one batch is wrong.',
                ],
                'how_to_configure' => [
                    'No configuration. Cancellation reasons come from the fixed dining round cancellation reason list.',
                ],
                'conditions' => [
                    ['if' => 'A round is cancelled', 'then' => 'Its tickets are cancelled and the running bill drops accordingly.'],
                    ['if' => 'An order is already completed', 'then' => 'Cancellation is no longer the right tool; handle it as a service recovery.'],
                    ['if' => 'Payment was already confirmed', 'then' => 'A refund is settled outside the system and must be reconciled manually.'],
                ],
                'examples' => [
                    ['title' => 'Wrong item ordered', 'body' => 'Waiter enters an oat latte instead of a soy latte. Cancel Round 2 with reason "Wrong item", add the correct round — the rest of the meal is untouched.'],
                ],
                'options' => [
                    ['name' => 'Customer cancelled', 'what' => 'The guest changed their mind.', 'why' => 'Separates guest decisions from our errors.', 'when' => 'Guest asks to drop an item.', 'example' => 'Guest no longer wants dessert.'],
                    ['name' => 'Wrong item', 'what' => 'The wrong thing was entered.', 'why' => 'Tracks order-entry accuracy.', 'when' => 'Mis-keyed item.', 'example' => 'Oat instead of soy.'],
                    ['name' => 'Duplicate order', 'what' => 'The same round was entered twice.', 'why' => 'Keeps double entries out of revenue.', 'when' => 'Two staff enter the same round.', 'example' => 'Round 3 entered twice.'],
                    ['name' => 'Preparation error', 'what' => 'It was made incorrectly.', 'why' => 'Tracks station errors.', 'when' => 'Item must be remade.', 'example' => 'Burnt sandwich.'],
                    ['name' => 'Quality issue', 'what' => 'It did not meet standard.', 'why' => 'Tracks quality problems.', 'when' => 'Item is rejected on quality.', 'example' => 'Milk split in the latte.'],
                    ['name' => 'Staff error', 'what' => 'A process mistake by staff.', 'why' => 'Points at training needs.', 'when' => 'Wrong table, wrong session.', 'example' => 'Round added to Table 11 instead of 12.'],
                    ['name' => 'Other', 'what' => 'Anything not covered above.', 'why' => 'Escape hatch.', 'when' => 'Use sparingly.', 'example' => 'Power cut mid-service.'],
                ],
                'notes' => [
                    'A rising "Other" count means the reason list is being avoided — review it with the team.',
                ],
            ],
            [
                'slug' => 'served-completed',
                'title' => 'Served & Completed',
                'group' => 'Orders',
                'roles' => $administrator,
                'tags' => ['served', 'completed', 'handover', 'closing'],
                'overview' => 'The end of the line. "Served" is a dining fact recorded per round; "completed" is the order-level fact that the customer has their order.',
                'how_it_works' => [
                    'A dining round is marked served once it physically reaches the table.',
                    'A retail order becomes completed at handover, after every station ticket is ready.',
                    'Completed orders are what revenue reporting counts, and what loyalty earning is based on.',
                ],
                'how_to_use' => [
                    'Mark served at the table, not on the way back to the counter.',
                    'Complete retail orders at handover so the queue reflects reality.',
                ],
                'how_to_configure' => [
                    'No configuration.',
                ],
                'conditions' => [
                    ['if' => 'Rounds are marked served late', 'then' => 'Service-time reporting overstates how slow the floor is.'],
                    ['if' => 'An order is completed', 'then' => 'It counts as revenue and can trigger loyalty earning if loyalty is enabled.'],
                    ['if' => 'Not all tickets are ready', 'then' => 'The order should not be completed yet.'],
                ],
                'examples' => [
                    ['title' => 'Serving together', 'body' => 'Drinks are ready in three minutes, food in twelve. The round is served once both are on the table, and that is when served is recorded.'],
                ],
                'notes' => [
                    'Bulk-marking rounds served at the end of a shift produces clean-looking but useless data.',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function administratorMarketingModules(): array
    {
        $administrator = [self::ROLE_ADMINISTRATOR];

        return [
            [
                'slug' => 'promotions',
                'title' => 'Offers & Promotions',
                'group' => 'Marketing',
                'roles' => $administrator,
                'tags' => ['offers', 'coupons', 'discounts', 'promotions', 'pricing'],
                'overview' => 'Price reductions applied at checkout — either automatically when conditions are met, or when the customer enters a coupon code.',
                'how_it_works' => [
                    'A promotion is a type (automatic or coupon) plus a discount type (percentage or fixed) and a value.',
                    'Eligibility is narrowed by date window, weekdays, daily time window, minimum subtotal, fulfilment scope, first-order-only, and product or category scope.',
                    'Usage can be capped overall and per customer, and priority decides which promotion wins when several could apply.',
                    'Discounts apply after catalog prices and before tax; a loyalty reward is applied after promotions.',
                ],
                'how_to_use' => [
                    'Decide first whether the offer should find the customer (automatic) or the customer should find it (coupon).',
                    'Always set a maximum discount amount on percentage offers to cap your exposure on large baskets.',
                    'Set an end date. Offers without an end date get forgotten and quietly erode margin.',
                    'Write the customer message so the discount line makes sense on the bill.',
                ],
                'how_to_configure' => [
                    'Set name, type, discount type and value, and a coupon code when the type is coupon.',
                    'Set starts at / ends at, weekdays, daily start and end times, and the fulfilment scope.',
                    'Set minimum subtotal, maximum discount amount, usage limit, per-customer usage limit and priority.',
                    'Restrict to specific products or categories, or leave it applying to all.',
                ],
                'conditions' => [
                    ['if' => 'Subtotal is below the minimum subtotal', 'then' => 'The promotion does not apply.'],
                    ['if' => 'Today is outside the weekday or daily time window', 'then' => 'The promotion does not apply even inside the date range.'],
                    ['if' => 'Fulfilment scope is takeaway and the order is delivery', 'then' => 'The promotion does not apply.'],
                    ['if' => 'The per-customer usage limit is reached', 'then' => 'That customer can no longer use the coupon.'],
                    ['if' => 'Two promotions could apply and neither is stackable', 'then' => 'The higher priority one wins.'],
                ],
                'examples' => [
                    ['title' => 'Weekday morning coffee push', 'body' => 'Automatic, percentage 10%, weekdays Monday–Friday, daily 8:00–11:00, minimum subtotal ₹300, maximum discount ₹100, scoped to the Hot Coffee category.'],
                    ['title' => 'Festival coupon', 'body' => 'Coupon "DIWALI", fixed ₹150 off, minimum subtotal ₹800, usage limit 500 overall and 1 per customer, valid for two weeks.'],
                ],
                'options' => [
                    ['name' => 'Automatic', 'what' => 'Applies by itself when conditions match.', 'why' => 'No code to remember; lifts conversion.', 'when' => 'Broad campaigns and happy hours.', 'example' => '10% off before 11 am.'],
                    ['name' => 'Coupon', 'what' => 'Applies only when the code is entered.', 'why' => 'Trackable and shareable.', 'when' => 'Targeted or partner offers.', 'example' => 'Code DIWALI for ₹150 off.'],
                    ['name' => 'Percentage', 'what' => 'A percentage off the eligible subtotal.', 'why' => 'Scales with basket size.', 'when' => 'Encouraging bigger baskets.', 'example' => '10% off above ₹500, capped at ₹100.'],
                    ['name' => 'Fixed', 'what' => 'A flat amount off.', 'why' => 'Predictable cost per redemption.', 'when' => 'Budgeted campaigns.', 'example' => '₹150 off above ₹800.'],
                    ['name' => 'Scope: Any', 'what' => 'Valid on every fulfilment method.', 'why' => 'Simplest default.', 'when' => 'General offers.', 'example' => 'Site-wide festival discount.'],
                    ['name' => 'Scope: Dining', 'what' => 'Valid for dining sessions.', 'why' => 'Drives table occupancy.', 'when' => 'Filling the floor at quiet hours.', 'example' => 'Afternoon dine-in offer.'],
                    ['name' => 'Scope: Dine in', 'what' => 'Valid for the dine-in fulfilment method.', 'why' => 'Precise in-store targeting.', 'when' => 'In-store-only promotions.', 'example' => 'Dine-in dessert offer.'],
                    ['name' => 'Scope: Takeaway', 'what' => 'Valid for takeaway orders.', 'why' => 'Drives counter volume.', 'when' => 'Morning commuter rush.', 'example' => 'Takeaway breakfast combo.'],
                    ['name' => 'Scope: Delivery', 'what' => 'Valid for delivery orders.', 'why' => 'Grows the delivery channel.', 'when' => 'Delivery expansion.', 'example' => 'Delivery-only ₹100 off.'],
                    ['name' => 'Stackable', 'what' => 'May combine with another promotion.', 'why' => 'Allows deliberate combinations.', 'when' => 'Rare — only when you have modelled the cost.', 'example' => 'A small free-delivery offer alongside a product discount.'],
                    ['name' => 'First order only', 'what' => 'Restricted to a customer’s first order.', 'why' => 'Acquisition without subsidising regulars.', 'when' => 'Welcome offers.', 'example' => '₹100 off your first order.'],
                ],
                'notes' => [
                    'There is no minimum quantity condition on promotions — use minimum subtotal instead.',
                    'There is no free-item promotion type. Free products and free add-ons are loyalty rewards.',
                    'Percentage offers without a maximum discount are the most common way to lose money here.',
                ],
                'demo_samples' => [
                    'Demo automatic dining offer',
                    'Demo festival window',
                    'Demo coupon BULK',
                    'Demo coupon DIWALI',
                    'Demo inactive coupon',
                    'Demo expired automatic offer',
                ],
            ],
            [
                'slug' => 'referral',
                'title' => 'Referral Programme',
                'group' => 'Marketing',
                'roles' => $administrator,
                'tags' => ['referral', 'invite', 'word of mouth', 'rewards'],
                'overview' => 'Existing customers invite friends. When an invited friend places a qualifying order, the referrer earns the reward you configured.',
                'how_it_works' => [
                    'A referral is registered when the invited person signs up, qualifies when their order meets your minimum, and is then rewarded.',
                    'The reward is either a free drink (a product or variant you nominate) or a coupon with a discount you define.',
                    'Rewards can expire after a set number of days, and there is a monthly cap per customer.',
                    'Referrals that turn out to be invalid can end up cancelled instead of rewarded.',
                ],
                'how_to_use' => [
                    'Set a qualifying order amount high enough that a referral is worth more than it costs.',
                    'Use the referral list to spot patterns of abuse before they scale.',
                    'Give the reward a redemption window so liability does not accumulate forever.',
                ],
                'how_to_configure' => [
                    'Enable referrals and choose the reward mode: free drink or coupon.',
                    'For free drink: nominate the product, variant and quantity.',
                    'For coupon: set the discount type, value, maximum discount and minimum subtotal.',
                    'Set the minimum qualifying order amount, the reward redemption duration in days, and the maximum rewards per customer per month.',
                ],
                'conditions' => [
                    ['if' => 'The invited friend’s order is below the qualifying amount', 'then' => 'The referral stays registered and no reward is issued.'],
                    ['if' => 'The referrer has hit the monthly cap', 'then' => 'Further referrals do not produce more rewards that month.'],
                    ['if' => 'The redemption window passes', 'then' => 'The reward can no longer be used.'],
                ],
                'examples' => [
                    ['title' => 'Free drink referral', 'body' => 'Friend signs up and spends ₹400 against a ₹300 minimum; the referrer gets one free regular cappuccino valid for 30 days, capped at three rewards a month.'],
                ],
                'options' => [
                    ['name' => 'Free drink', 'what' => 'Reward is a nominated product or variant.', 'why' => 'Brings the referrer back into the café.', 'when' => 'You want a visit, not just a discount.', 'example' => 'One free regular cappuccino.'],
                    ['name' => 'Coupon', 'what' => 'Reward is a discount coupon.', 'why' => 'Flexible and cheaper to control.', 'when' => 'You want predictable cost.', 'example' => '₹100 off above ₹400.'],
                    ['name' => 'Registered', 'what' => 'The invited person signed up.', 'why' => 'Tracks top-of-funnel.', 'when' => 'Immediately after signup.', 'example' => 'Friend created an account.'],
                    ['name' => 'Qualified', 'what' => 'Their order met the minimum.', 'why' => 'The referral has real value.', 'when' => 'After a qualifying order.', 'example' => 'Friend spent ₹400.'],
                    ['name' => 'Rewarded', 'what' => 'The referrer received the reward.', 'why' => 'Completes the loop.', 'when' => 'After qualification.', 'example' => 'Free drink issued.'],
                    ['name' => 'Cancelled', 'what' => 'The referral was voided.', 'why' => 'Handles abuse and invalid signups.', 'when' => 'Fraud or duplicate accounts.', 'example' => 'Same person signing up twice.'],
                ],
                'notes' => [
                    'Referral rewards are separate from loyalty points. An optional bridge can also credit loyalty points, and it is off by default.',
                ],
                'demo_samples' => ['Demo referral records seeded by DemoReferralSeeder (local/testing only)'],
            ],
            [
                'slug' => 'campaigns',
                'title' => 'Campaigns',
                'group' => 'Marketing',
                'roles' => $administrator,
                'tags' => ['campaigns', 'popup', 'banner', 'messaging', 'cta'],
                'overview' => 'On-site messages shown to customers: a popup, a banner, an inline block or a landing message, with rules for where it appears, who sees it, when it fires and how often.',
                'how_it_works' => [
                    'A campaign has a surface (how it looks), placement rules (where), targeting rules (who), trigger rules (when) and a frequency policy (how often).',
                    'The call to action can send the customer to a product, a category, an internal page or a promotion, or simply close.',
                    'Impressions are recorded, which is what campaign reporting is built on.',
                    'Only active campaigns inside their date window are eligible; priority breaks ties.',
                ],
                'how_to_use' => [
                    'Build in draft, preview the targeting, then activate.',
                    'Run one popup at a time. Stacked popups train customers to dismiss everything.',
                    'Pick a frequency policy that respects the customer — once per session is a sane default.',
                    'Pause rather than delete when a campaign is finished, so its reporting stays readable.',
                ],
                'how_to_configure' => [
                    'Set name, internal label, title, message, image, and the CTA label and CTA type with its target.',
                    'Choose the surface and placements, set targeting rules, choose the trigger and its threshold.',
                    'Set the frequency policy plus cooldown hours or maximum impressions where the policy needs them.',
                    'Set the date window and priority, then move the status to active.',
                ],
                'conditions' => [
                    ['if' => 'Status is draft or paused', 'then' => 'Nothing is shown to customers.'],
                    ['if' => 'Placement is cart but the customer is on the menu', 'then' => 'The campaign does not appear.'],
                    ['if' => 'Frequency is once per session and it has already shown', 'then' => 'It will not show again in that session.'],
                    ['if' => 'The trigger is scroll and the customer never scrolls that far', 'then' => 'It never fires.'],
                    ['if' => 'Targeting rules exclude the visitor', 'then' => 'They never see it regardless of placement.'],
                ],
                'examples' => [
                    ['title' => 'Cart upsell', 'body' => 'Inline surface on the cart placement, immediate trigger, once per session, CTA type product pointing at a dessert.'],
                    ['title' => 'Guest signup popup', 'body' => 'Popup on global placement, delay trigger of five seconds, once per day, targeting identity = guest, CTA type internal page pointing at /loyalty.'],
                ],
                'options' => [
                    ['name' => 'Surface: Popup', 'what' => 'Modal over the page.', 'why' => 'Highest attention.', 'when' => 'One important message.', 'example' => 'Festival announcement.'],
                    ['name' => 'Surface: Banner', 'what' => 'Strip across the page.', 'why' => 'Visible without blocking.', 'when' => 'Ongoing notices.', 'example' => 'Delivery hours changed.'],
                    ['name' => 'Surface: Inline', 'what' => 'Block inside page content.', 'why' => 'Contextual and least intrusive.', 'when' => 'Menu and cart upsells.', 'example' => 'Dessert suggestion in the cart.'],
                    ['name' => 'Surface: Landing', 'what' => 'Landing-style message.', 'why' => 'Full-attention arrival message.', 'when' => 'Dedicated entry points.', 'example' => 'Campaign arrival message.'],
                    ['name' => 'Placement: Global', 'what' => 'Eligible anywhere.', 'why' => 'Maximum reach.', 'when' => 'Site-wide notices.', 'example' => 'Holiday hours.'],
                    ['name' => 'Placement: Home', 'what' => 'Home page only.', 'why' => 'First impression.', 'when' => 'Headline offers.', 'example' => 'New season hero message.'],
                    ['name' => 'Placement: Menu', 'what' => 'Menu listing.', 'why' => 'Reaches active browsers.', 'when' => 'Category pushes.', 'example' => 'Try our new cold brew.'],
                    ['name' => 'Placement: Category', 'what' => 'Category pages.', 'why' => 'Category-specific messaging.', 'when' => 'Category promotions.', 'example' => 'Cold brew week.'],
                    ['name' => 'Placement: Product detail', 'what' => 'Product pages.', 'why' => 'High purchase intent.', 'when' => 'Cross-sells.', 'example' => 'Pair this with a brownie.'],
                    ['name' => 'Placement: Cart', 'what' => 'Cart page.', 'why' => 'Last chance to grow the basket.', 'when' => 'Upsells and threshold nudges.', 'example' => 'Add ₹80 to unlock free delivery.'],
                    ['name' => 'Placement: Checkout', 'what' => 'Checkout page.', 'why' => 'Reduce abandonment.', 'when' => 'Reassurance messages.', 'example' => 'Payment help note.'],
                    ['name' => 'Placement: Order success', 'what' => 'After the order is placed.', 'why' => 'Best moment for retention.', 'when' => 'Loyalty and referral prompts.', 'example' => 'Invite a friend.'],
                    ['name' => 'Trigger: Immediate', 'what' => 'Fires as soon as the placement matches.', 'why' => 'Nothing gets missed.', 'when' => 'Inline and banners.', 'example' => 'Cart banner on load.'],
                    ['name' => 'Trigger: Delay', 'what' => 'Fires after a number of seconds.', 'why' => 'Lets the page settle first.', 'when' => 'Popups.', 'example' => 'Show after five seconds.'],
                    ['name' => 'Trigger: Scroll', 'what' => 'Fires at a scroll depth.', 'why' => 'Targets engaged readers.', 'when' => 'Long pages.', 'example' => 'At 50% of the menu.'],
                    ['name' => 'Trigger: Product views', 'what' => 'Fires after N products viewed.', 'why' => 'Catches undecided browsers.', 'when' => 'Recommendation nudges.', 'example' => 'After three product views.'],
                    ['name' => 'Frequency: Every session', 'what' => 'Shows every session.', 'why' => 'Maximum exposure.', 'when' => 'Critical notices only.', 'example' => 'Closure warning.'],
                    ['name' => 'Frequency: Once per session', 'what' => 'Once per visit.', 'why' => 'Balanced default.', 'when' => 'Most campaigns.', 'example' => 'Popup offer.'],
                    ['name' => 'Frequency: Once per actor', 'what' => 'Once ever for that visitor or customer.', 'why' => 'One-time messages.', 'when' => 'Welcome and onboarding.', 'example' => 'First-visit welcome.'],
                    ['name' => 'Frequency: Once per day', 'what' => 'Once each day.', 'why' => 'Daily reminder without nagging.', 'when' => 'Recurring nudges.', 'example' => 'Daily special.'],
                    ['name' => 'Frequency: Cooldown', 'what' => 'Wait N hours between showings.', 'why' => 'Fine-grained pacing.', 'when' => 'Frequent visitors.', 'example' => 'Cooldown of 48 hours.'],
                    ['name' => 'Frequency: Max impressions', 'what' => 'Stop after N impressions.', 'why' => 'Hard exposure cap.', 'when' => 'Limited campaigns.', 'example' => 'Stop after 5 views.'],
                    ['name' => 'CTA: Product', 'what' => 'Opens a product.', 'why' => 'Shortest path to purchase.', 'when' => 'Single-product pushes.', 'example' => 'Opens the cold brew page.'],
                    ['name' => 'CTA: Category', 'what' => 'Opens a category.', 'why' => 'Lets people browse a range.', 'when' => 'Category promotions.', 'example' => 'Opens Desserts.'],
                    ['name' => 'CTA: Internal page', 'what' => 'Goes to an internal path.', 'why' => 'Points at your own pages.', 'when' => 'Loyalty, menu and info pages.', 'example' => '/menu or /loyalty.'],
                    ['name' => 'CTA: Promotion', 'what' => 'Links to a promotion.', 'why' => 'Connects the message to the offer.', 'when' => 'Coupon campaigns.', 'example' => 'Opens the DIWALI offer.'],
                    ['name' => 'CTA: Close', 'what' => 'Dismisses the message.', 'why' => 'Informational only.', 'when' => 'Announcements.', 'example' => '"Got it" on a holiday notice.'],
                    ['name' => 'Status: Draft', 'what' => 'Not live.', 'why' => 'Safe place to build.', 'when' => 'While configuring.', 'example' => 'Half-built popup.'],
                    ['name' => 'Status: Active', 'what' => 'Live within its window.', 'why' => 'Running state.', 'when' => 'Ready to show.', 'example' => 'Festival popup running.'],
                    ['name' => 'Status: Paused', 'what' => 'Temporarily stopped.', 'why' => 'Stop without losing setup.', 'when' => 'Something needs fixing.', 'example' => 'Paused after a typo.'],
                    ['name' => 'Status: Ended', 'what' => 'Finished.', 'why' => 'Closed out for reporting.', 'when' => 'Campaign is over.', 'example' => 'Diwali campaign ended.'],
                ],
                'notes' => [
                    'The internal page CTA takes a path on your own site such as /menu or /loyalty; it is not for external links.',
                    'Cooldown needs cooldown hours and max impressions needs a maximum — a policy without its number does nothing useful.',
                ],
            ],
            [
                'slug' => 'audience-segments',
                'title' => 'Audience Segments',
                'group' => 'Marketing',
                'roles' => $administrator,
                'tags' => ['segments', 'targeting', 'audience', 'rules', 'personalisation'],
                'overview' => 'Named, reusable audiences built from targeting rules. Define "lapsed customers" once and reuse it across campaigns and merchandising.',
                'how_it_works' => [
                    'Rules are organised into three groups: all (every rule must match), any (at least one must match) and exclude (any match disqualifies).',
                    'Each rule is a type, an operator and a value — for example identity equals authenticated, or last purchase days at least 30.',
                    'Actor scope decides whether the segment evaluates anonymous visitors, logged-in customers, or both.',
                    'Preview shows how the rules resolve before you activate the segment.',
                ],
                'how_to_use' => [
                    'Start with the smallest rule set that describes the audience and add rules only when the preview is too broad.',
                    'Name segments for the audience, not the campaign, so they survive the campaign that created them.',
                    'Use exclude to protect groups you must not target — loyalty debt accounts, for example.',
                ],
                'how_to_configure' => [
                    'Set name, slug, description, actor scope and status.',
                    'Add rules to the all, any and exclude groups, then preview and activate.',
                ],
                'conditions' => [
                    ['if' => 'A rule sits in the all group', 'then' => 'It must match or the whole segment fails.'],
                    ['if' => 'Any rules exist and none match', 'then' => 'The segment does not match.'],
                    ['if' => 'An exclude rule matches', 'then' => 'The person is removed even if everything else matched.'],
                    ['if' => 'Actor scope is customer only', 'then' => 'Anonymous visitors never match.'],
                ],
                'examples' => [
                    ['title' => 'Lapsed high spenders', 'body' => 'All: spend band equals high, last purchase days at least 30. Exclude: loyalty debt equals true. Actor scope customer.'],
                    ['title' => 'Acquisition audience', 'body' => 'Any: identity equals guest, first order equals true. Actor scope both.'],
                ],
                'options' => [
                    ['name' => 'Group: all', 'what' => 'Every rule must match.', 'why' => 'Narrow, precise audiences.', 'when' => 'Combining conditions.', 'example' => 'High spend AND lapsed.'],
                    ['name' => 'Group: any', 'what' => 'At least one rule must match.', 'why' => 'Broader reach.', 'when' => 'Several routes into the same audience.', 'example' => 'Guest OR first order.'],
                    ['name' => 'Group: exclude', 'what' => 'A match disqualifies.', 'why' => 'Protects sensitive groups.', 'when' => 'Never-target lists.', 'example' => 'Exclude loyalty debt accounts.'],
                    ['name' => 'Identity: guest', 'what' => 'Browsing without an account.', 'why' => 'Acquisition messaging.', 'when' => 'Signup prompts.', 'example' => 'identity eq guest.'],
                    ['name' => 'Identity: authenticated', 'what' => 'Signed-in customer.', 'why' => 'Member messaging.', 'when' => 'Loyalty and account prompts.', 'example' => 'identity eq authenticated.'],
                    ['name' => 'Identity: everyone', 'what' => 'Both guests and customers.', 'why' => 'No identity filter.', 'when' => 'Broad campaigns.', 'example' => 'identity eq everyone.'],
                    ['name' => 'Actor: visitor', 'what' => 'Anonymous visitors only.', 'why' => 'Pre-account targeting.', 'when' => 'Acquisition.', 'example' => 'First-visit banner audience.'],
                    ['name' => 'Actor: customer', 'what' => 'Logged-in customers only.', 'why' => 'Account-based targeting.', 'when' => 'Retention and loyalty.', 'example' => 'Lapsed customer audience.'],
                    ['name' => 'Actor: both', 'what' => 'Visitors and customers.', 'why' => 'Widest coverage.', 'when' => 'General audiences.', 'example' => 'Everyone browsing today.'],
                    ['name' => 'Status: draft', 'what' => 'Not usable yet.', 'why' => 'Safe while building.', 'when' => 'Under construction.', 'example' => 'Rules half written.'],
                    ['name' => 'Status: active', 'what' => 'Available for use.', 'why' => 'Live audience.', 'when' => 'Ready.', 'example' => 'Used by a running campaign.'],
                    ['name' => 'Status: paused', 'what' => 'Temporarily unavailable.', 'why' => 'Stop use without deleting.', 'when' => 'Rules need review.', 'example' => 'Paused pending a rethink.'],
                    ['name' => 'Status: archived', 'what' => 'Retired.', 'why' => 'Keeps the list clean.', 'when' => 'No longer relevant.', 'example' => 'Last year’s festival audience.'],
                ],
                'notes' => [
                    'A rule is written as type, operator and value. Available rule types differ slightly between segments and campaigns — the form only offers valid ones.',
                    'Over-narrow segments are the usual cause of "my campaign never shows". Preview before you blame the campaign.',
                ],
            ],
            [
                'slug' => 'homepage-merchandising',
                'title' => 'Homepage & Menu Merchandising',
                'group' => 'Marketing',
                'roles' => $administrator,
                'tags' => ['homepage', 'sections', 'merchandising', 'curation', 'menu'],
                'overview' => 'The ordered strips of products on the home and menu pages. Each section is either hand-curated or filled automatically from a source you choose.',
                'how_it_works' => [
                    'Sections have a placement (home or menu), an order, and a source type that decides how products are chosen.',
                    'Curated sections hold an explicit, ordered product list you control.',
                    'Automatic sections resolve from behaviour or catalog data — trending, popular, new arrivals, favourites, category or tag.',
                    'Sections can carry targeting rules so different audiences see different strips.',
                ],
                'how_to_use' => [
                    'Put your strongest curated section first; most customers never scroll past the top two.',
                    'Mix a curated strip with one or two automatic strips rather than curating everything.',
                    'Deactivate a section instead of emptying it.',
                ],
                'how_to_configure' => [
                    'Create the section with a title, placement, source type and display order.',
                    'For curated sections, attach products and order them.',
                    'Optionally add targeting rules, then activate.',
                ],
                'conditions' => [
                    ['if' => 'A curated section has no products', 'then' => 'It renders empty and should be deactivated.'],
                    ['if' => 'An automatic section has too little data', 'then' => 'It may return very few products.'],
                    ['if' => 'A section is targeted', 'then' => 'Only matching visitors see it.'],
                ],
                'examples' => [
                    ['title' => 'Home layout', 'body' => 'Section 1 curated "Today’s picks", Section 2 trending, Section 3 buy again targeted at logged-in customers.'],
                ],
                'options' => [
                    ['name' => 'Placement: Home', 'what' => 'Shows on the home page.', 'why' => 'First impression.', 'when' => 'Headline merchandising.', 'example' => 'Today’s picks.'],
                    ['name' => 'Placement: Menu', 'what' => 'Shows on the menu page.', 'why' => 'Helps active browsers choose.', 'when' => 'Menu guidance.', 'example' => 'Popular right now.'],
                    ['name' => 'Curated', 'what' => 'Explicit product list.', 'why' => 'Total control.', 'when' => 'You know exactly what to push.', 'example' => 'Five hand-picked drinks.'],
                    ['name' => 'Recommendation', 'what' => 'Personalised recommendations.', 'why' => 'Relevance per visitor.', 'when' => 'You have behaviour data.', 'example' => 'Recommended for you.'],
                    ['name' => 'Buy again', 'what' => 'Products the customer bought before.', 'why' => 'Fastest repeat purchase.', 'when' => 'Returning customers.', 'example' => 'Your usual.'],
                    ['name' => 'Favourite', 'what' => 'Products the customer favourited.', 'why' => 'Explicit intent.', 'when' => 'Customers who use favourites.', 'example' => 'Your favourites.'],
                    ['name' => 'Repeated interest', 'what' => 'Products viewed repeatedly.', 'why' => 'Catches hesitation.', 'when' => 'Nudging undecided browsers.', 'example' => 'Still thinking about these?'],
                    ['name' => 'Affinity', 'what' => 'Products matching taste affinity.', 'why' => 'Discovery within taste.', 'when' => 'Introducing new items.', 'example' => 'Because you like vanilla.'],
                    ['name' => 'Trending', 'what' => 'Gaining traction now.', 'why' => 'Social proof.', 'when' => 'Fast-moving items.', 'example' => 'Trending this week.'],
                    ['name' => 'Popular', 'what' => 'Consistently strong sellers.', 'why' => 'Safe choices.', 'when' => 'New visitors.', 'example' => 'Most loved.'],
                    ['name' => 'New arrival', 'what' => 'Recently added products.', 'why' => 'Announces additions.', 'when' => 'After a menu update.', 'example' => 'Just landed.'],
                    ['name' => 'Featured', 'what' => 'Products flagged as featured.', 'why' => 'Editorial control with automation.', 'when' => 'Ongoing highlights.', 'example' => 'Featured picks.'],
                    ['name' => 'Bestseller', 'what' => 'Top sellers by volume.', 'why' => 'Proven demand.', 'when' => 'Conversion-focused strips.', 'example' => 'Bestsellers.'],
                    ['name' => 'Category', 'what' => 'Everything in a category.', 'why' => 'Category spotlight.', 'when' => 'Seasonal ranges.', 'example' => 'All cold brews.'],
                    ['name' => 'Tag', 'what' => 'Everything with a tag.', 'why' => 'Cross-category theming.', 'when' => 'Themed strips.', 'example' => 'Everything tagged "New".'],
                ],
                'notes' => [
                    'Personalised source types need behaviour history; on a brand new site they will look thin until traffic builds.',
                ],
            ],
            [
                'slug' => 'recommendations',
                'title' => 'Recommendations',
                'group' => 'Marketing',
                'roles' => $administrator,
                'tags' => ['recommendations', 'personalisation', 'suggestions', 'analytics'],
                'overview' => 'Automatic product suggestions shown in specific places on the storefront, driven by customer behaviour and catalog relationships.',
                'how_it_works' => [
                    'Recommendations are produced per context — home, menu, product detail, cart and post-order.',
                    'Each suggestion carries a reason, which is what makes the analytics interpretable.',
                    'Behaviour events build personalisation profiles that the recommender reads.',
                    'The recommendations report shows how suggestions perform so you can judge whether they earn their place.',
                ],
                'how_to_use' => [
                    'Let it run for a few weeks before judging quality; cold-start results are always weak.',
                    'Read the recommendations report alongside merchandising rather than in isolation.',
                    'Fix catalog data — categories, flavours, tags — before blaming the recommender.',
                ],
                'how_to_configure' => [
                    'Recommendation-sourced homepage sections are configured under merchandising.',
                    'Personalisation profiles can be rebuilt with the personalisation rebuild command when data has changed materially.',
                ],
                'conditions' => [
                    ['if' => 'A visitor has no history', 'then' => 'Suggestions fall back to catalog-level signals rather than personal ones.'],
                    ['if' => 'The catalog is small', 'then' => 'Recommendations repeat, because there is little to choose from.'],
                ],
                'examples' => [
                    ['title' => 'Cart context', 'body' => 'A customer with two coffees in the cart is shown a brownie on the cart page, with the reason recorded for reporting.'],
                ],
                'options' => [
                    ['name' => 'Home', 'what' => 'Suggestions on the home page.', 'why' => 'Discovery on arrival.', 'when' => 'Every visit.', 'example' => 'Recommended for you.'],
                    ['name' => 'Menu', 'what' => 'Suggestions while browsing the menu.', 'why' => 'Helps choose.', 'when' => 'Long menus.', 'example' => 'You might like.'],
                    ['name' => 'Product detail', 'what' => 'Suggestions on a product page.', 'why' => 'Cross-sell at high intent.', 'when' => 'Complementary items.', 'example' => 'Goes well with.'],
                    ['name' => 'Cart', 'what' => 'Suggestions in the cart.', 'why' => 'Grows basket size.', 'when' => 'Before checkout.', 'example' => 'Add a dessert.'],
                    ['name' => 'Post order', 'what' => 'Suggestions after ordering.', 'why' => 'Sets up the next visit.', 'when' => 'Order success page.', 'example' => 'Try this next time.'],
                ],
                'notes' => [
                    'Recommendations never override availability. An inactive product will not be suggested.',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function administratorLoyaltyModules(): array
    {
        $administrator = [self::ROLE_ADMINISTRATOR];

        return [
            [
                'slug' => 'loyalty-accounts',
                'title' => 'Loyalty Accounts',
                'group' => 'Loyalty',
                'roles' => $administrator,
                'tags' => ['loyalty', 'accounts', 'points', 'balance', 'ledger'],
                'overview' => 'One loyalty account per customer, holding the available points balance and the lifetime totals behind it.',
                'how_it_works' => [
                    'Every points movement is a ledger entry; the balance is the running result of that ledger and is never edited directly.',
                    'Lifetime earned, lifetime redeemed and lifetime adjusted are tracked separately from the available balance.',
                    'The available balance can go negative when an earn is reversed — that is loyalty debt, not money owed.',
                ],
                'how_to_use' => [
                    'Open an account to see the ledger before answering any customer query about points.',
                    'Explain the balance from the ledger entries rather than adjusting it to match expectations.',
                    'Export balances or the ledger when you need to reconcile outside the panel.',
                ],
                'how_to_configure' => [
                    'Loyalty must be enabled in config/loyalty.php; accounts follow customers automatically.',
                ],
                'conditions' => [
                    ['if' => 'Loyalty is disabled', 'then' => 'No points are earned and redemption is unavailable.'],
                    ['if' => 'An earn is reversed after a cancellation', 'then' => 'The balance drops and may go negative.'],
                ],
                'examples' => [
                    ['title' => 'Points query', 'body' => 'A customer expects 300 points but has 250. The ledger shows a 50-point reversal from a cancelled order — explain it rather than adjusting it.'],
                ],
                'options' => [
                    ['name' => 'Earn', 'what' => 'Points credited from a completed order.', 'why' => 'The core reward mechanism.', 'when' => 'On qualifying completion.', 'example' => '+120 points.'],
                    ['name' => 'Redeem', 'what' => 'Points spent on a reward.', 'why' => 'Where points turn into value.', 'when' => 'Reward applied to an order.', 'example' => '−200 points.'],
                    ['name' => 'Reversal', 'what' => 'An earlier entry unwound.', 'why' => 'Keeps the ledger truthful after cancellations.', 'when' => 'Order cancelled or refunded.', 'example' => '−120 points reversed.'],
                    ['name' => 'Adjustment', 'what' => 'A manual staff correction.', 'why' => 'Service recovery and error fixes.', 'when' => 'Rare and always with a note.', 'example' => '+50 goodwill points.'],
                    ['name' => 'Expiry', 'what' => 'Points removed by expiry.', 'why' => 'Limits open-ended liability.', 'when' => 'If an expiry policy applies.', 'example' => '−100 expired points.'],
                ],
                'notes' => [
                    'The ledger is the source of truth. If the balance looks wrong, the answer is always in the entries.',
                ],
            ],
            [
                'slug' => 'loyalty-earning',
                'title' => 'Loyalty Earning',
                'group' => 'Loyalty',
                'roles' => $administrator,
                'tags' => ['loyalty', 'earning', 'points', 'rate', 'policy'],
                'overview' => 'How orders turn into points: the earning rate, the minimum order that qualifies, and exactly which part of the order counts.',
                'how_it_works' => [
                    'Earning is based on merchandise after discounts, excluding tax and delivery fees.',
                    'The rate is expressed as points per currency unit, and fractions are rounded down.',
                    'Only orders completed on or after the configured effective timestamp can earn — there is no retrospective backfill.',
                    'Cancelling a completed order reverses the earn.',
                ],
                'how_to_use' => [
                    'Model the cost of the rate before enabling it — points are a real liability.',
                    'Set the effective timestamp deliberately so historic orders do not suddenly qualify.',
                    'Explain the exclusion of tax and delivery to staff so they can answer customers confidently.',
                ],
                'how_to_configure' => [
                    'In config/loyalty.php set enabled, effective_at, and the earning block: points per currency unit, currency unit and minimum eligible amount.',
                    'Rounding is floor and the eligible amount policy is merchandise after discount, excluding tax and delivery.',
                ],
                'conditions' => [
                    ['if' => 'The eligible amount is below the minimum eligible amount', 'then' => 'No points are earned on that order.'],
                    ['if' => 'The order was completed before the effective timestamp', 'then' => 'It earns nothing.'],
                    ['if' => 'A discount was applied', 'then' => 'Points are calculated on the discounted amount, not the list price.'],
                ],
                'examples' => [
                    ['title' => 'Rate worked through', 'body' => 'Order ₹500, ₹50 promotion discount, ₹23 tax, ₹30 delivery. Eligible amount is ₹450. At one point per ₹1 the customer earns 450 points.'],
                ],
                'notes' => [
                    'Rounding is always down, so small orders may earn nothing at a low rate.',
                    'Increasing the rate applies to future orders only.',
                ],
            ],
            [
                'slug' => 'loyalty-rewards',
                'title' => 'Loyalty Rewards Catalog',
                'group' => 'Loyalty',
                'roles' => $administrator,
                'tags' => ['loyalty', 'rewards', 'redemption', 'catalog', 'points'],
                'overview' => 'What points can buy. Each reward has a type, a points cost, and limits that keep redemption predictable.',
                'how_it_works' => [
                    'A reward has a type, a points cost, an optional minimum spend and an optional date window.',
                    'Product and category rewards are scoped to the specific products, categories or add-ons you attach.',
                    'Usage can be limited overall, per customer, and per customer within a rolling number of days.',
                    'Priority orders the reward list, and status controls whether it is offered at all.',
                ],
                'how_to_use' => [
                    'Price rewards against the earning rate so the effective discount rate is one you can afford.',
                    'Offer three to five rewards at different point levels rather than a long list.',
                    'Duplicate an existing reward as a starting point for a seasonal variation.',
                    'Use bulk status changes to retire a group of rewards together.',
                ],
                'how_to_configure' => [
                    'Set name, reward type, points cost, minimum spend, start and end dates.',
                    'Set usage limit, per-customer usage limit and the per-customer period in days.',
                    'Attach the products, categories or add-ons the reward applies to, write the customer description, set priority and activate.',
                ],
                'conditions' => [
                    ['if' => 'Available points are below the points cost', 'then' => 'The reward cannot be redeemed.'],
                    ['if' => 'The order is below the reward’s minimum spend', 'then' => 'The reward is not offered.'],
                    ['if' => 'The per-customer limit within the period is reached', 'then' => 'That customer must wait for the period to roll over.'],
                    ['if' => 'The reward is outside its date window', 'then' => 'It is not offered.'],
                ],
                'examples' => [
                    ['title' => 'Free coffee tier', 'body' => 'Free base product reward at 500 points, scoped to the Hot Coffee category, limited to one per customer every 30 days.'],
                    ['title' => 'Discount tier', 'body' => 'Fixed order discount of ₹100 at 400 points with a ₹300 minimum spend.'],
                ],
                'options' => [
                    ['name' => 'Fixed order discount', 'what' => 'A flat amount off the order.', 'why' => 'Predictable cost per redemption.', 'when' => 'Simple, easily understood rewards.', 'example' => '₹100 off for 400 points.'],
                    ['name' => 'Percentage order discount', 'what' => 'A percentage off the order.', 'why' => 'Scales with basket size.', 'when' => 'Encouraging larger redemption baskets.', 'example' => '15% off for 350 points.'],
                    ['name' => 'Free base product', 'what' => 'A free product at its base configuration.', 'why' => 'Feels generous and brings a visit.', 'when' => 'Classic free-coffee reward.', 'example' => 'Free regular cappuccino for 500 points.'],
                    ['name' => 'Free add-on', 'what' => 'A free extra on an item.', 'why' => 'Low cost, high perceived value.', 'when' => 'Entry-level reward tier.', 'example' => 'Free extra shot for 100 points.'],
                    ['name' => 'Specific product reward', 'what' => 'A named product given as the reward.', 'why' => 'Push a particular item.', 'when' => 'New product launches.', 'example' => 'Free cold brew for 450 points.'],
                    ['name' => 'Category product reward', 'what' => 'Any product from a chosen category.', 'why' => 'Choice within a controlled range.', 'when' => 'You want flexibility without open-ended cost.', 'example' => 'Any dessert for 600 points.'],
                ],
                'notes' => [
                    'Only one reward may be applied per order.',
                    'Free items belong here, not in promotions — promotions only discount.',
                ],
            ],
            [
                'slug' => 'loyalty-redemption',
                'title' => 'Loyalty Redemption',
                'group' => 'Loyalty',
                'roles' => $administrator,
                'tags' => ['loyalty', 'redemption', 'checkout', 'stacking', 'points'],
                'overview' => 'How a reward is actually applied to an order, and how it interacts with promotions, tax and the customer’s balance.',
                'how_it_works' => [
                    'The customer chooses a reward at checkout; the points cost is debited when the redemption is recorded.',
                    'Order of application is catalog prices, then promotions or a referral coupon, then the loyalty reward, then tax.',
                    'One reward per order is enforced.',
                    'Whether a reward can be combined with a promotion is a configuration switch.',
                ],
                'how_to_use' => [
                    'Check the stacking setting before launching a big promotion, so you know the combined worst case.',
                    'When a customer says redemption is unavailable, check their balance, the reward window and its limits in that order.',
                ],
                'how_to_configure' => [
                    'In config/loyalty.php set redemption enabled, allow with promotions, and note that one reward per order is fixed.',
                ],
                'conditions' => [
                    ['if' => 'Redemption is disabled', 'then' => 'Points still accrue but nothing can be spent.'],
                    ['if' => 'Combining with promotions is disallowed', 'then' => 'A customer with a promotion applied cannot also redeem.'],
                    ['if' => 'Available points are below the reward cost', 'then' => 'Redemption is blocked — it never goes into debt.'],
                    ['if' => 'The order is cancelled after redemption', 'then' => 'The redemption is unwound in the ledger.'],
                ],
                'examples' => [
                    ['title' => 'Stacked worst case', 'body' => 'A ₹1,000 order takes a 10% promotion (−₹100) then a ₹100 fixed reward, leaving ₹800 before tax. Decide deliberately whether you want that combination allowed.'],
                ],
                'notes' => [
                    'Redemption never pushes a balance negative. Only reversals can create debt.',
                ],
            ],
            [
                'slug' => 'loyalty-debt',
                'title' => 'Loyalty Debt',
                'group' => 'Loyalty',
                'roles' => $administrator,
                'tags' => ['loyalty', 'debt', 'negative', 'reversal', 'ledger'],
                'overview' => 'A negative available balance. It happens when points that were already earned are reversed — usually because an order was cancelled after it had earned.',
                'how_it_works' => [
                    'The ledger is never silently clamped, so a reversal can take the balance below zero.',
                    'While the balance is negative, redemption is blocked.',
                    'Future earnings apply against the debt and clear it naturally.',
                    'Debt is a points concept only. It is never converted into money the customer owes.',
                ],
                'how_to_use' => [
                    'Review debt accounts periodically; a cluster usually points at a cancellation process problem.',
                    'Explain to the customer that the reversal removed points from a cancelled order, and that their next orders will restore them.',
                    'Only use an adjustment to clear debt when the reversal itself was wrong.',
                ],
                'how_to_configure' => [
                    'No configuration. Debt is a consequence of the ledger design.',
                ],
                'conditions' => [
                    ['if' => 'Available points are below zero', 'then' => 'Redemption is blocked until the balance recovers.'],
                    ['if' => 'The customer completes new orders', 'then' => 'Earned points reduce the debt automatically.'],
                    ['if' => 'The reversal was raised in error', 'then' => 'An adjustment with a clear note is the correct fix.'],
                ],
                'examples' => [
                    ['title' => 'Cancelled after redemption', 'body' => 'A customer earns 200 points, spends 150 on a reward, then the earning order is cancelled. The 200 is reversed and the balance sits at −150 until they earn again.'],
                ],
                'notes' => [
                    'Segments can target loyalty debt accounts; the usual use is to exclude them from redemption campaigns.',
                ],
            ],
            [
                'slug' => 'loyalty-adjustments',
                'title' => 'Loyalty Adjustments',
                'group' => 'Loyalty',
                'roles' => $administrator,
                'tags' => ['loyalty', 'adjustment', 'manual', 'goodwill', 'audit'],
                'overview' => 'Manual point corrections made by staff — adding goodwill points or removing points credited in error.',
                'how_it_works' => [
                    'An adjustment is a ledger entry like any other, attributed to the staff member who made it.',
                    'Adjustments contribute to a separate lifetime adjusted total, so they never hide inside earnings.',
                    'Adjustments can be made from the loyalty operations screen or from the customer record.',
                ],
                'how_to_use' => [
                    'Always write a reason. An adjustment without a reason is indistinguishable from an error.',
                    'Read the ledger before adjusting — most "missing points" turn out to be correct reversals.',
                    'Keep adjustments rare. Frequent adjustments mean the underlying rules need fixing.',
                ],
                'how_to_configure' => [
                    'No configuration beyond loyalty being enabled. Restrict who can adjust by restricting administrator access.',
                ],
                'conditions' => [
                    ['if' => 'A positive adjustment is made', 'then' => 'The balance rises immediately and the lifetime adjusted total increases.'],
                    ['if' => 'A negative adjustment exceeds the balance', 'then' => 'The account goes into loyalty debt.'],
                ],
                'examples' => [
                    ['title' => 'Service recovery', 'body' => 'A drink was remade twice. Add 100 points with the note "Goodwill — repeated remake, order #1042".'],
                ],
                'notes' => [
                    'Adjustments are visible in exports and reporting. Treat every one as auditable.',
                ],
            ],
            [
                'slug' => 'loyalty-analytics',
                'title' => 'Loyalty Analytics',
                'group' => 'Loyalty',
                'roles' => $administrator,
                'tags' => ['loyalty', 'analytics', 'reporting', 'export', 'liability'],
                'overview' => 'The health of the loyalty programme: points issued, points redeemed, outstanding balances, and which rewards people actually take.',
                'how_it_works' => [
                    'Reporting is built from the ledger, so it always reconciles with individual accounts.',
                    'Outstanding balances represent your open liability.',
                    'Balances, the ledger and redemptions can each be exported for offline analysis.',
                ],
                'how_to_use' => [
                    'Track the redemption rate. Very low redemption means the rewards are priced too high to be interesting.',
                    'Watch outstanding liability monthly and adjust the rate before it becomes uncomfortable.',
                    'Export the ledger for finance rather than retyping figures.',
                ],
                'how_to_configure' => [
                    'No configuration. Use the export actions on the loyalty operations screen.',
                ],
                'conditions' => [
                    ['if' => 'Points issued far exceed points redeemed', 'then' => 'Liability is building — review the reward ladder.'],
                    ['if' => 'One reward dominates redemptions', 'then' => 'It is probably underpriced relative to the others.'],
                ],
                'examples' => [
                    ['title' => 'Quarterly review', 'body' => '120,000 points issued, 40,000 redeemed, 80,000 outstanding. Add a mid-tier reward so points get spent rather than banked.'],
                ],
                'notes' => [
                    'Points are a liability until they are spent or expire. Review the numbers on a schedule, not when someone complains.',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function administratorOperationsModules(): array
    {
        $administrator = [self::ROLE_ADMINISTRATOR];

        return [
            [
                'slug' => 'tables',
                'title' => 'Tables',
                'group' => 'Operations',
                'roles' => $administrator,
                'tags' => ['tables', 'dining', 'floor', 'qr', 'seating'],
                'overview' => 'The physical tables guests sit at. Each table has a label, a capacity and a live operational status driven by its current session.',
                'how_it_works' => [
                    'Tables are ordered for display, so the panel list can mirror the real floor layout.',
                    'Operational status is derived from the session on the table, not set by hand.',
                    'A table can be deactivated when it is out of service without deleting its history.',
                ],
                'how_to_use' => [
                    'Use the same labels staff already say out loud — "Table 12", not "T-012-A".',
                    'Order the list to match the walking route through the room.',
                    'Deactivate rather than delete a table that is temporarily out of use.',
                ],
                'how_to_configure' => [
                    'Create each table with its label and capacity, then order the list and activate.',
                    'Dine-in must be enabled in Website Settings for tables to be usable.',
                ],
                'conditions' => [
                    ['if' => 'A session is open on a table', 'then' => 'It shows as occupied and cannot take a second session.'],
                    ['if' => 'The session is paid and closed', 'then' => 'The table returns to available.'],
                    ['if' => 'Dine-in is disabled', 'then' => 'Tables exist but no sessions can be opened.'],
                ],
                'examples' => [
                    ['title' => 'Terrace closed for rain', 'body' => 'Deactivate the four terrace tables for the afternoon; they disappear from the floor view and no sessions can be opened on them.'],
                ],
                'options' => [
                    ['name' => 'Available', 'what' => 'No active session.', 'why' => 'Ready to seat.', 'when' => 'Table is free.', 'example' => 'Table 5 empty and cleared.'],
                    ['name' => 'Occupied', 'what' => 'A session is open.', 'why' => 'Guests are seated and ordering.', 'when' => 'During the meal.', 'example' => 'Table 12 mid-service.'],
                    ['name' => 'Bill requested', 'what' => 'Guests asked for the bill.', 'why' => 'Cues staff to close out.', 'when' => 'End of the meal.', 'example' => 'Table 12 wants the bill.'],
                    ['name' => 'Awaiting payment', 'what' => 'Bill presented, money not received.', 'why' => 'Shows what is blocking the turn.', 'when' => 'Between bill and payment.', 'example' => 'Guests fetching a card.'],
                ],
                'notes' => [
                    'Do not invent tables that do not exist physically — launch readiness expects the real count.',
                ],
                'demo_samples' => ['Demo tables seeded by CafeTableSeeder (local/testing only)'],
            ],
            [
                'slug' => 'service-requests',
                'title' => 'Service Requests (Call Waiter)',
                'group' => 'Operations',
                'roles' => $administrator,
                'tags' => ['service', 'call waiter', 'requests', 'escalation', 'floor'],
                'overview' => 'When a guest asks for help from their table, a service request is raised and routed to floor staff — first to their preferred waiter, then to everyone if it is not picked up.',
                'how_it_works' => [
                    'A request is raised for order assistance and carries the table and the session it belongs to.',
                    'If the session has a preferred waiter, the request goes to them first with a time limit.',
                    'If it is not claimed in time, it escalates and is broadcast to all waiters.',
                    'A waiter claims the request, deals with it, and it is then resolved.',
                ],
                'how_to_use' => [
                    'Watch for repeated escalations — it means the preferred waiter is overloaded or absent.',
                    'Use the request history to see how quickly the floor actually responds.',
                ],
                'how_to_configure' => [
                    'No panel configuration. The scheduled escalation runs through `php artisan coffee:escalate-dining-service-requests`.',
                ],
                'conditions' => [
                    ['if' => 'The session has a preferred waiter', 'then' => 'Only that waiter is notified first.'],
                    ['if' => 'The request is not claimed before it expires', 'then' => 'It escalates to every waiter.'],
                    ['if' => 'A waiter claims it', 'then' => 'Other waiters see it is taken and do not duplicate the trip.'],
                ],
                'examples' => [
                    ['title' => 'Escalation in practice', 'body' => 'Table 12 calls their waiter. Their preferred waiter is on a break, the request expires, every waiter is notified, and another waiter claims it.'],
                ],
                'notes' => [
                    'If escalation never happens, check that the scheduler is running — the escalation is a scheduled task, not a live timer in the browser.',
                ],
            ],
            [
                'slug' => 'inventory-refill',
                'title' => 'Inventory Refill Requests',
                'group' => 'Operations',
                'roles' => $administrator,
                'tags' => ['refill', 'restock', 'requests', 'approval', 'inventory'],
                'overview' => 'The formal path from "we are running out" to "stock has been moved". Floor and bar staff raise requests; administrators and operators decide on them.',
                'how_it_works' => [
                    'A request names the ingredient and the quantity needed and starts as pending.',
                    'It can be approved or rejected, and an approved request is marked completed once the stock has actually been moved.',
                    'Completing a request is the point at which the stock movement is real.',
                ],
                'how_to_use' => [
                    'Clear pending requests every shift; an unanswered request becomes a stockout.',
                    'Reject with a clear reason so the requester knows what to do instead.',
                    'Only mark completed once the stock is physically in place.',
                ],
                'how_to_configure' => [
                    'No configuration. Set sensible low-stock thresholds on ingredients so requests are raised early.',
                ],
                'conditions' => [
                    ['if' => 'A request is pending', 'then' => 'Nothing has moved yet and the requester is still waiting.'],
                    ['if' => 'A request is approved but not completed', 'then' => 'It is agreed but the stock has not been moved.'],
                    ['if' => 'A request is rejected', 'then' => 'No stock moves and the requester must be told why.'],
                ],
                'examples' => [
                    ['title' => 'Mid-shift oat milk', 'body' => 'Barista requests 5 L of oat milk, the operator approves it, the stock is carried from the store, and the request is completed.'],
                ],
                'options' => [
                    ['name' => 'Pending', 'what' => 'Raised, awaiting a decision.', 'why' => 'Makes the need visible.', 'when' => 'Immediately after raising.', 'example' => 'Barista asks for 5 L oat milk.'],
                    ['name' => 'Approved', 'what' => 'Agreed, not yet moved.', 'why' => 'Separates the decision from the action.', 'when' => 'Operator agrees.', 'example' => 'Approved at 3:10 pm.'],
                    ['name' => 'Rejected', 'what' => 'Declined.', 'why' => 'Records that no stock will move.', 'when' => 'Nothing available or not justified.', 'example' => 'No oat milk in the store.'],
                    ['name' => 'Completed', 'what' => 'Stock physically moved.', 'why' => 'Closes the loop.', 'when' => 'After the transfer.', 'example' => '5 L delivered to the bar.'],
                ],
                'notes' => [
                    'Approved is not completed. Leaving requests approved but never completed makes the queue meaningless.',
                ],
                'demo_samples' => ['Demo refill requests seeded by InventoryRefillRequestSeeder (local/testing only)'],
            ],
            [
                'slug' => 'schedule-hours',
                'title' => 'Schedule, Hours & Closures',
                'group' => 'Operations',
                'roles' => $administrator,
                'tags' => ['hours', 'schedule', 'closures', 'availability', 'holidays'],
                'overview' => 'When the café accepts orders. Weekly operating hours set the normal pattern, closures cover exceptions, and a manual close handles emergencies.',
                'how_it_works' => [
                    'Operating hours are defined per weekday and evaluated in the business timezone.',
                    'A closure overrides the weekly hours for a specific date or range, with a type describing why.',
                    'Manual close immediately blocks ordering regardless of the schedule, with your own message.',
                    'Closures can be toggled without being deleted, which makes recurring closures easy to reuse.',
                ],
                'how_to_use' => [
                    'Set the weekly pattern once, then manage exceptions through closures.',
                    'Add public holidays in advance rather than closing manually on the day.',
                    'Always write a message customers can act on, and reopen as soon as you are trading again.',
                ],
                'how_to_configure' => [
                    'Set open and close times for each weekday, and confirm the business timezone.',
                    'Create closures with a type, date range and message; toggle them on or off as needed.',
                    'Use close and reopen for immediate, unplanned interruptions.',
                ],
                'conditions' => [
                    ['if' => 'The current time is outside operating hours', 'then' => 'Ordering is unavailable.'],
                    ['if' => 'An active closure covers today', 'then' => 'Ordering is unavailable even during normal hours.'],
                    ['if' => 'Manual close is on', 'then' => 'Ordering is blocked until you reopen or the closed-until time passes.'],
                ],
                'examples' => [
                    ['title' => 'Planned maintenance', 'body' => 'Create a maintenance closure for next Monday with the message "Closed for kitchen maintenance, back Tuesday" — no one has to remember on the day.'],
                ],
                'options' => [
                    ['name' => 'Holiday', 'what' => 'Public or festival holiday.', 'why' => 'Planned annual closure.', 'when' => 'Known dates.', 'example' => 'Independence Day.'],
                    ['name' => 'Maintenance', 'what' => 'Repair or servicing.', 'why' => 'Premises unusable.', 'when' => 'Scheduled works.', 'example' => 'Kitchen deep clean.'],
                    ['name' => 'Private event', 'what' => 'Booked out privately.', 'why' => 'Closed to the public but trading.', 'when' => 'Private bookings.', 'example' => 'Corporate breakfast.'],
                    ['name' => 'Temporary closure', 'what' => 'Short unplanned closure.', 'why' => 'Something stopped service.', 'when' => 'Same-day problems.', 'example' => 'Power cut.'],
                    ['name' => 'Other', 'what' => 'Anything not covered.', 'why' => 'Escape hatch.', 'when' => 'Rare.', 'example' => 'Road closed outside.'],
                ],
                'notes' => [
                    'Hours are interpreted in the business timezone. A wrong timezone opens and closes the shop at the wrong time.',
                ],
            ],
            [
                'slug' => 'reports-analytics',
                'title' => 'Reports & Analytics',
                'group' => 'Operations',
                'roles' => $administrator,
                'tags' => ['reports', 'analytics', 'exports', 'performance', 'insight'],
                'overview' => 'The reporting suite: financial, inventory and product sales, operational performance, campaign results and recommendation performance — each with exports.',
                'how_it_works' => [
                    'Reports read live data for the period you select; nothing is pre-aggregated overnight.',
                    'Financial reporting covers revenue, tax and payment mix.',
                    'Inventory and product reporting covers ingredient movements and product sales.',
                    'Operational performance covers dining and preparation timings.',
                ],
                'how_to_use' => [
                    'Pick one report and one question. Opening all five at once produces no decisions.',
                    'Export when you need to combine periods or share with an accountant.',
                    'Compare like periods — a Tuesday against a Tuesday, not against a Saturday.',
                ],
                'how_to_configure' => [
                    'No configuration. Report quality depends on staff recording served, completed and payments accurately.',
                ],
                'conditions' => [
                    ['if' => 'Staff mark orders complete late', 'then' => 'Timing and revenue reports shift into the wrong period.'],
                    ['if' => 'Recipes are missing or wrong', 'then' => 'Ingredient movement reporting will not reconcile with reality.'],
                ],
                'examples' => [
                    ['title' => 'Finding a slow station', 'body' => 'Operational performance shows kitchen preparation averaging fourteen minutes against a two-minute bar. Fix prep at the kitchen, not the bar.'],
                ],
                'options' => [
                    ['name' => 'Financial', 'what' => 'Revenue, tax and payment mix.', 'why' => 'Money questions.', 'when' => 'Daily and monthly close.', 'example' => 'Cash versus UPI split.'],
                    ['name' => 'Inventory & product sales', 'what' => 'Ingredient movements and product sales.', 'why' => 'Stock and menu decisions.', 'when' => 'Weekly review.', 'example' => 'Milk consumption against sales.'],
                    ['name' => 'Operational performance', 'what' => 'Dining and preparation timings.', 'why' => 'Service speed.', 'when' => 'After a busy period.', 'example' => 'Average ticket age by station.'],
                    ['name' => 'Campaign analytics', 'what' => 'Campaign impressions and outcomes.', 'why' => 'Marketing effectiveness.', 'when' => 'After a campaign runs.', 'example' => 'Popup performance.'],
                    ['name' => 'Recommendation analytics', 'what' => 'How suggestions perform.', 'why' => 'Personalisation value.', 'when' => 'Monthly.', 'example' => 'Cart suggestion take-up.'],
                ],
                'notes' => [
                    'Reports are only as honest as the shift-floor discipline behind them.',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function administratorFinanceModules(): array
    {
        $administrator = [self::ROLE_ADMINISTRATOR];

        return [
            [
                'slug' => 'invoices',
                'title' => 'Invoices & Receipts',
                'group' => 'Finance',
                'roles' => $administrator,
                'tags' => ['invoice', 'receipt', 'print', 'pdf', 'billing'],
                'overview' => 'The customer-facing record of what was bought and paid. Retail orders and dining sessions each produce an invoice, available to print or download.',
                'how_it_works' => [
                    'The invoice is generated from the order or session and includes items, add-ons, discounts, tax and totals.',
                    'Business identity, legal name and GSTIN come from Website Settings.',
                    'A dining invoice covers every round in the session as one document.',
                    'Retail orders offer a printable version, a PDF and a compact receipt format.',
                ],
                'how_to_use' => [
                    'Use the receipt format for a thermal printer and the PDF when a customer wants it by email.',
                    'Check the legal details once before launch — every invoice inherits them.',
                ],
                'how_to_configure' => [
                    'Set the legal business name, GSTIN, tax label and tax percent in Website Settings.',
                ],
                'conditions' => [
                    ['if' => 'Tax is disabled', 'then' => 'No tax line appears on the invoice.'],
                    ['if' => 'GSTIN is blank while tax is enabled', 'then' => 'The invoice is incomplete as a tax invoice and launch readiness flags it.'],
                    ['if' => 'A round is cancelled', 'then' => 'It is excluded from the session invoice total.'],
                ],
                'examples' => [
                    ['title' => 'One bill, three rounds', 'body' => 'Table 12 ordered across three rounds; the session invoice lists all three with a single tax calculation and one total.'],
                ],
                'notes' => [
                    'Invoices are financial documents. Correct the underlying order or session rather than editing figures by hand.',
                ],
            ],
            [
                'slug' => 'gst-tax',
                'title' => 'GST & Tax',
                'group' => 'Finance',
                'roles' => $administrator,
                'tags' => ['tax', 'gst', 'gstin', 'inclusive', 'compliance'],
                'overview' => 'Whether tax is charged, at what rate, under what label, and whether displayed prices already include it.',
                'how_it_works' => [
                    'Tax is a single switch with a label, a percentage and an inclusive or exclusive mode.',
                    'Exclusive mode adds tax on top of the discounted subtotal; inclusive mode treats menu prices as already containing it.',
                    'Tax is calculated after discounts and after any loyalty reward.',
                    'The GSTIN and legal business name appear on invoices.',
                ],
                'how_to_use' => [
                    'Confirm the mode with your accountant before launch — switching later changes every displayed price.',
                    'Use the label your customers and auditors expect, such as GST.',
                ],
                'how_to_configure' => [
                    'Set tax enabled, tax label, tax percent, tax inclusive, tax GSTIN and the legal business name in Website Settings.',
                ],
                'conditions' => [
                    ['if' => 'Tax is enabled and exclusive', 'then' => 'Tax is added on top and shown as a separate line.'],
                    ['if' => 'Tax is enabled and inclusive', 'then' => 'Menu prices already contain it and the invoice shows the tax component.'],
                    ['if' => 'Tax is disabled', 'then' => 'No tax is calculated or displayed anywhere.'],
                ],
                'examples' => [
                    ['title' => 'Exclusive 5%', 'body' => 'Subtotal ₹500 after discount, GST at 5% adds ₹25, total ₹525 with the tax shown on its own line.'],
                ],
                'notes' => [
                    'Changing the rate does not retrospectively alter invoices already issued.',
                ],
            ],
            [
                'slug' => 'revenue-reporting',
                'title' => 'Revenue Reporting',
                'group' => 'Finance',
                'roles' => $administrator,
                'tags' => ['revenue', 'sales', 'finance', 'financial', 'reporting'],
                'overview' => 'What the business actually earned over a period, broken down by fulfilment channel, payment method and tax.',
                'how_it_works' => [
                    'Revenue counts completed orders and paid dining sessions, not orders still in flight.',
                    'Discounts and loyalty redemptions reduce recognised revenue; tax is reported separately.',
                    'The financial report can be exported for accounting.',
                ],
                'how_to_use' => [
                    'Close the day properly before reading the numbers — open sessions are not revenue yet.',
                    'Track discount as a share of gross to see what promotions really cost.',
                    'Reconcile cash figures against the till at the end of each shift.',
                ],
                'how_to_configure' => [
                    'No configuration. Set the reporting period on the financial report screen.',
                ],
                'conditions' => [
                    ['if' => 'Sessions are left open overnight', 'then' => 'Their value is missing from that day’s revenue.'],
                    ['if' => 'Cash is recorded late', 'then' => 'The payment mix for the period is wrong.'],
                ],
                'examples' => [
                    ['title' => 'Discount check', 'body' => 'Gross ₹120,000 with ₹18,000 of discounts is 15% given away. If margin is 30%, that is half the margin — tighten the offers.'],
                ],
                'notes' => [
                    'Never share revenue, cost or margin figures with waiter, chef or barista panels — those roles have no finance access by design.',
                ],
            ],
            [
                'slug' => 'payment-status',
                'title' => 'Payment Status & Reconciliation',
                'group' => 'Finance',
                'roles' => $administrator,
                'tags' => ['payments', 'reconciliation', 'status', 'cash', 'audit'],
                'overview' => 'Tracking every payment from pending through to confirmed, and matching what the system says against what is actually in the till and the bank.',
                'how_it_works' => [
                    'Each order or session carries a payment status and a method.',
                    'Cash is recorded by staff at the moment of receipt; manual UPI requires proof review.',
                    'Rejected payments return the order to pending so the customer can pay again.',
                    'Payment attempts are recorded, which is what makes reconciliation possible.',
                ],
                'how_to_use' => [
                    'Reconcile cash at every shift change, not once a week.',
                    'Investigate anything sitting in awaiting review for more than a few minutes.',
                    'Compare confirmed UPI totals against the bank statement daily.',
                ],
                'how_to_configure' => [
                    'Enable only the payment methods you can reconcile.',
                ],
                'conditions' => [
                    ['if' => 'A payment sits in awaiting review', 'then' => 'It is neither counted nor refused, and the order is blocked.'],
                    ['if' => 'Cash in the till is short', 'then' => 'Compare against recorded cash payments for the shift before anything else.'],
                ],
                'examples' => [
                    ['title' => 'Daily close', 'body' => 'Count the till, compare with cash payments recorded for the shift, then match confirmed UPI against the bank.'],
                ],
                'notes' => [
                    'Never store card numbers, UPI PINs or gateway credentials anywhere in the system.',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function administratorLaunchModules(): array
    {
        $administrator = [self::ROLE_ADMINISTRATOR];

        return [
            [
                'slug' => 'launch-readiness',
                'title' => 'Launch Readiness',
                'group' => 'Launch',
                'roles' => $administrator,
                'tags' => ['launch', 'readiness', 'checklist', 'go-live', 'audit'],
                'overview' => 'An automated audit that tells you what is still missing before going live: business details, payments, hours, fulfilment, CMS pages, social links, catalog, dining, staff, tax and demo contamination.',
                'how_it_works' => [
                    'Each area is inspected and produces findings with a severity, so required gaps are separated from recommendations.',
                    'It checks real data — that referenced images exist, that required roles have accounts, that tax details are complete.',
                    'It explicitly looks for leftover demo data, which must never reach production.',
                    'It is a read-only audit; it never changes anything for you.',
                ],
                'how_to_use' => [
                    'Run it early, not the night before launch.',
                    'Clear every required finding, then decide case by case on the recommendations.',
                    'Re-run after each batch of fixes until required findings are gone.',
                ],
                'how_to_configure' => [
                    'Run `php artisan coffee:launch-readiness`, adding `--json` for machine-readable output.',
                    'Use `php artisan coffee:catalog-readiness` for a catalog-focused check.',
                ],
                'conditions' => [
                    ['if' => 'A required business or payment detail is blank', 'then' => 'It is reported as a required finding.'],
                    ['if' => 'Demo data is detected', 'then' => 'It is reported as contamination and must be removed before launch.'],
                    ['if' => 'Dine-in is enabled with no real tables', 'then' => 'It warns rather than inventing a table count for you.'],
                ],
                'examples' => [
                    ['title' => 'Two weeks out', 'body' => 'Run the check, find missing GSTIN, an empty FAQ page and a missing payment QR, fix all three, re-run, and only recommendations remain.'],
                ],
                'notes' => [
                    'A clean report means the configuration is complete, not that the business is ready. Still do a real end-to-end order.',
                ],
            ],
            [
                'slug' => 'demo-vs-production',
                'title' => 'Demo Data vs Production',
                'group' => 'Launch',
                'roles' => $administrator,
                'tags' => ['demo', 'seeder', 'production', 'testing', 'data'],
                'overview' => 'This application ships with a demo dataset for local development and automated testing only. Production must contain nothing but real café data.',
                'how_it_works' => [
                    'DemoSeeder builds a full sample world: staff, catalog, tables, promotions, orders, dining sessions, referrals and notifications.',
                    'It refuses to run outside the local and testing environments and fails loudly if attempted.',
                    'Demo records are recognisable by their "Demo" naming, which is also what the contamination check looks for.',
                    'Launch readiness reports any demo data it finds so it cannot quietly survive into production.',
                ],
                'how_to_use' => [
                    'Use demo data freely on a local machine to learn the panels and train staff.',
                    'Never copy a demo database into production; build production from real data only.',
                    'If demo records appear in production, remove them and re-run launch readiness.',
                ],
                'how_to_configure' => [
                    'Locally, `php artisan migrate:fresh --seed` builds the demo world.',
                    'In production, seed only the genuine catalog and settings your café actually uses.',
                ],
                'conditions' => [
                    ['if' => 'The environment is not local or testing', 'then' => 'DemoSeeder refuses to run.'],
                    ['if' => 'Demo records exist in production', 'then' => 'Launch readiness reports contamination until they are removed.'],
                ],
                'examples' => [
                    ['title' => 'Training a new manager', 'body' => 'Seed demo data on a local copy so they can cancel rounds, reject payments and edit promotions without touching the live café.'],
                ],
                'notes' => [
                    'Demo promotions, orders, sessions and staff are illustrative only — never quote them to customers or use them for real reporting.',
                ],
                'demo_samples' => [
                    'Demo staff accounts (DemoUserSeeder)',
                    'Demo catalog and add-ons (ProductSeeder, DemoFoodCatalogSeeder, DemoAddOnSeeder)',
                    'Demo promotions such as "Demo coupon DIWALI" (DemoPromotionSeeder)',
                    'Demo orders and dining sessions (DemoOrderSeeder, DemoDiningSeeder)',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function operatorModules(): array
    {
        $operator = [self::ROLE_OPERATOR];

        return [
            [
                'slug' => 'operational-dashboard',
                'title' => 'Operational Dashboard',
                'group' => 'Operations',
                'roles' => $operator,
                'tags' => ['dashboard', 'shift', 'overview', 'operator'],
                'overview' => 'Your shift at a glance: live orders, open dining sessions, station progress and anything waiting on your decision.',
                'how_it_works' => [
                    'Counts and lists are read live from orders, sessions and preparation tickets.',
                    'Items needing a decision — payment proofs, pending refill requests — are surfaced for action.',
                    'Notifications arrive in the header bell and can be marked read.',
                ],
                'how_to_use' => [
                    'Open this first, clear the decision items, then work the orders list.',
                    'Come back between rushes to check nothing has been sitting unattended.',
                ],
                'how_to_configure' => [
                    'Nothing to configure. What appears depends on what administrators have enabled.',
                ],
                'conditions' => [
                    ['if' => 'A payment proof is awaiting review', 'then' => 'The order is blocked until you confirm or reject it.'],
                    ['if' => 'A refill request is pending', 'then' => 'Bar or kitchen staff are waiting on you.'],
                ],
                'examples' => [
                    ['title' => 'Shift start', 'body' => 'Two proofs awaiting review and one pending refill. Clear all three before opening the orders list.'],
                ],
                'notes' => [
                    'The dashboard shows operational figures only. Revenue analysis lives in the administrator panel.',
                ],
            ],
            [
                'slug' => 'orders',
                'title' => 'Orders',
                'group' => 'Orders',
                'roles' => $operator,
                'tags' => ['orders', 'counter', 'status', 'takeaway', 'delivery'],
                'overview' => 'Every takeaway and delivery order, from payment through handover, plus the invoice and receipt output for each one.',
                'how_it_works' => [
                    'An order carries a status that advances as payment clears and stations complete their tickets.',
                    'You can advance the order status, record cash, review payment proofs and produce the invoice.',
                    'Station tickets are visible on the order so you can see exactly what is outstanding.',
                ],
                'how_to_use' => [
                    'Work oldest first and clear payment decisions before anything else.',
                    'Open an order to see items, add-ons, payment state and ticket progress together.',
                    'Print the receipt or download the PDF when the customer asks.',
                ],
                'how_to_configure' => [
                    'Not configurable from the operator panel. Fulfilment and payment options are administrator settings.',
                ],
                'conditions' => [
                    ['if' => 'Payment is pending', 'then' => 'The order waits and the stations are not expected to work on it.'],
                    ['if' => 'All station tickets are ready', 'then' => 'The order can be handed over and completed.'],
                    ['if' => 'An order is cancelled', 'then' => 'Its tickets are cancelled and the stations stop.'],
                ],
                'examples' => [
                    ['title' => 'Walk-in takeaway', 'body' => 'Order arrives paid by cash; you record cash received, the bar ticket appears, and you complete the order at handover.'],
                ],
                'notes' => [
                    'Complete an order only when the customer physically has it. Completion is a revenue event.',
                ],
            ],
            [
                'slug' => 'dining-sessions',
                'title' => 'Dining Sessions',
                'group' => 'Dining',
                'roles' => $operator,
                'tags' => ['dining', 'sessions', 'tables', 'bill', 'rounds'],
                'overview' => 'Oversight of every table’s visit: rounds ordered, running total, payment state and closing out.',
                'how_it_works' => [
                    'One session covers one table’s whole visit and accumulates all its rounds.',
                    'You can set the payment method, record cash, confirm or reject a payment proof, and close the session.',
                    'A closed session can be reopened when there is a genuine mistake to correct.',
                ],
                'how_to_use' => [
                    'Use this screen to unblock tables the floor cannot resolve.',
                    'Close sessions promptly so tables return to available.',
                    'Reopen only for a real correction, and tell the waiter why.',
                ],
                'how_to_configure' => [
                    'Not configurable here. Dine-in and tables are administrator settings.',
                ],
                'conditions' => [
                    ['if' => 'A guest requests the bill', 'then' => 'The session moves to billing requested and the table reflects it.'],
                    ['if' => 'Payment is received and the session is closed', 'then' => 'The table returns to available.'],
                    ['if' => 'A round is cancelled', 'then' => 'The running bill drops by that round’s value.'],
                ],
                'examples' => [
                    ['title' => 'Stuck table', 'body' => 'Table 7 shows awaiting payment but the guests paid cash to a waiter. Record cash received and close the session so the table frees up.'],
                ],
                'notes' => [
                    'Every reopen should have a reason you would be comfortable explaining tomorrow.',
                ],
            ],
            [
                'slug' => 'tables-overview',
                'title' => 'Tables Overview',
                'group' => 'Dining',
                'roles' => $operator,
                'tags' => ['tables', 'floor', 'status', 'occupancy'],
                'overview' => 'The live state of the floor: which tables are free, which are occupied, and which are waiting on a bill or a payment.',
                'how_it_works' => [
                    'Table status is derived from the session currently on it, not set by hand.',
                    'The view updates as sessions are opened, billed, paid and closed.',
                ],
                'how_to_use' => [
                    'Scan for tables stuck in bill requested or awaiting payment — those are the ones costing you turns.',
                    'Use it to answer "can we seat four right now" without walking the floor.',
                ],
                'how_to_configure' => [
                    'Tables themselves are created and ordered by administrators.',
                ],
                'conditions' => [
                    ['if' => 'A table shows occupied', 'then' => 'It has an open session and cannot take a second one.'],
                    ['if' => 'A table shows awaiting payment', 'then' => 'The bill has been presented and money has not been recorded.'],
                ],
                'examples' => [
                    ['title' => 'Turning tables', 'body' => 'Three tables sit at awaiting payment for over ten minutes. Chase them and the floor frees up immediately.'],
                ],
                'options' => [
                    ['name' => 'Available', 'what' => 'Free to seat.', 'why' => 'Ready for guests.', 'when' => 'No active session.', 'example' => 'Table 5 cleared.'],
                    ['name' => 'Occupied', 'what' => 'Session open.', 'why' => 'Guests are ordering.', 'when' => 'Mid-service.', 'example' => 'Table 12 on Round 2.'],
                    ['name' => 'Bill requested', 'what' => 'Guests asked for the bill.', 'why' => 'Needs a waiter.', 'when' => 'End of the meal.', 'example' => 'Table 12 wants to pay.'],
                    ['name' => 'Awaiting payment', 'what' => 'Bill given, money not recorded.', 'why' => 'Blocking the turn.', 'when' => 'Between bill and payment.', 'example' => 'Guests finding a card.'],
                ],
                'notes' => [
                    'You cannot change a table’s status directly — resolve the session and the status follows.',
                ],
            ],
            [
                'slug' => 'waiter-calls',
                'title' => 'Waiter Calls',
                'group' => 'Dining',
                'roles' => $operator,
                'tags' => ['service', 'call waiter', 'requests', 'escalation'],
                'overview' => 'Guests calling for help from their table. Requests go to the preferred waiter first and escalate to all waiters if nobody responds in time.',
                'how_it_works' => [
                    'A request is raised for order assistance against the table and its session.',
                    'If the session has a preferred waiter, they are notified first with a time limit.',
                    'Unclaimed requests escalate and are broadcast to every waiter.',
                    'A waiter claims the request and it is resolved once handled.',
                ],
                'how_to_use' => [
                    'Watch for escalations during a rush — they are the clearest sign the floor is short-staffed.',
                    'If a request is escalating repeatedly, redirect a waiter yourself.',
                ],
                'how_to_configure' => [
                    'No operator configuration. Escalation runs as a scheduled task.',
                ],
                'conditions' => [
                    ['if' => 'A request is claimed', 'then' => 'Other waiters see it is taken and do not duplicate the trip.'],
                    ['if' => 'A request escalates', 'then' => 'Every waiter is notified.'],
                ],
                'examples' => [
                    ['title' => 'Busy Saturday', 'body' => 'Four requests escalate within ten minutes. Move a waiter from the terrace to the main room until the rush clears.'],
                ],
                'notes' => [
                    'Escalation depends on the scheduler running. If it never escalates, raise it with an administrator.',
                ],
            ],
            [
                'slug' => 'station-progress',
                'title' => 'Station Progress',
                'group' => 'Operations',
                'roles' => $operator,
                'tags' => ['preparation', 'stations', 'queue', 'progress', 'timing'],
                'overview' => 'A read-only view of the bar and kitchen queues so you can answer "how long?" without walking to the pass.',
                'how_it_works' => [
                    'Every ticket shows its station, its status and how long it has been waiting.',
                    'Tickets progress through pending, accepted, preparing and ready.',
                    'Bar and kitchen tickets from the same order progress independently.',
                ],
                'how_to_use' => [
                    'Check here before promising a time to a customer.',
                    'Escalate verbally when a ticket has been pending for too long — you cannot advance it yourself.',
                ],
                'how_to_configure' => [
                    'Not configurable. Routing is set on the product by administrators.',
                ],
                'conditions' => [
                    ['if' => 'A ticket is still pending', 'then' => 'No station has picked it up and nothing is being made.'],
                    ['if' => 'One station is ready and the other is not', 'then' => 'The order waits so it can be served together.'],
                ],
                'examples' => [
                    ['title' => 'Answering a customer', 'body' => 'Their sandwich ticket is preparing at nine minutes; tell them roughly four more minutes rather than guessing.'],
                ],
                'notes' => [
                    'Operators observe the queues. Only chefs and baristas move their own tickets.',
                ],
            ],
            [
                'slug' => 'payments-cash-upi',
                'title' => 'Payments: Cash & UPI Proof',
                'group' => 'Finance',
                'roles' => $operator,
                'tags' => ['payments', 'cash', 'upi', 'proof', 'verification'],
                'overview' => 'Taking money and verifying it: recording cash for orders and sessions, and reviewing customer-uploaded UPI proofs.',
                'how_it_works' => [
                    'Payment moves through pending, awaiting review, and then confirmed or rejected.',
                    'Recording cash confirms the payment immediately.',
                    'A rejected proof returns the payment to pending so the customer can pay again.',
                ],
                'how_to_use' => [
                    'Check the amount, the receiving UPI ID and the timestamp on every proof before confirming.',
                    'Reject anything you cannot verify and tell the customer plainly what is missing.',
                    'Record cash the moment you take it.',
                ],
                'how_to_configure' => [
                    'Payment methods and UPI display details are administrator settings.',
                ],
                'conditions' => [
                    ['if' => 'A proof amount is short', 'then' => 'Reject it and ask for the difference.'],
                    ['if' => 'A proof is confirmed', 'then' => 'The order is released to the stations.'],
                    ['if' => 'A proof sits unreviewed', 'then' => 'The customer is waiting and nothing is being made.'],
                ],
                'examples' => [
                    ['title' => 'Mismatched proof', 'body' => 'Screenshot shows ₹200 against a ₹420 order; reject with the reason, and confirm once the balance arrives.'],
                ],
                'notes' => [
                    'Never record card numbers, UPI PINs or any credential. Record only the decision.',
                ],
            ],
            [
                'slug' => 'cancellations',
                'title' => 'Cancellations',
                'group' => 'Orders',
                'roles' => $operator,
                'tags' => ['cancel', 'rounds', 'reasons', 'orders'],
                'overview' => 'Stopping work that should not continue — a whole order or a single dining round — with an honest reason attached.',
                'how_it_works' => [
                    'Cancelling cancels the related station tickets so work stops immediately.',
                    'Cancelling a dining round removes its value from the running bill and leaves the rest of the session intact.',
                    'Round cancellations require a reason from a fixed list.',
                ],
                'how_to_use' => [
                    'Cancel as early as you can; after the item is made it is waste, not a save.',
                    'Cancel the specific round rather than the whole session when only one batch is wrong.',
                    'Choose the reason that is actually true.',
                ],
                'how_to_configure' => [
                    'No configuration. The reason list is fixed.',
                ],
                'conditions' => [
                    ['if' => 'A round is cancelled', 'then' => 'Its tickets stop and the running bill drops.'],
                    ['if' => 'Payment was already confirmed', 'then' => 'Any refund is handled outside the system and must be reconciled.'],
                ],
                'examples' => [
                    ['title' => 'Duplicate round', 'body' => 'Two waiters enter the same round for Table 9. Cancel one with reason "Duplicate order" and tell the bar immediately.'],
                ],
                'options' => [
                    ['name' => 'Customer cancelled', 'what' => 'The guest changed their mind.', 'why' => 'Separates guest choices from our errors.', 'when' => 'Guest drops an item.', 'example' => 'No dessert after all.'],
                    ['name' => 'Wrong item', 'what' => 'The wrong item was entered.', 'why' => 'Tracks order-entry accuracy.', 'when' => 'Mis-keyed item.', 'example' => 'Oat instead of soy.'],
                    ['name' => 'Duplicate order', 'what' => 'Entered twice.', 'why' => 'Keeps duplicates out of the bill.', 'when' => 'Two staff entered the same round.', 'example' => 'Round 3 twice.'],
                    ['name' => 'Preparation error', 'what' => 'Made incorrectly.', 'why' => 'Tracks station errors.', 'when' => 'Item must be remade.', 'example' => 'Burnt toast.'],
                    ['name' => 'Quality issue', 'what' => 'Below standard.', 'why' => 'Tracks quality problems.', 'when' => 'Item rejected on quality.', 'example' => 'Split milk.'],
                    ['name' => 'Staff error', 'what' => 'Process mistake.', 'why' => 'Points at training needs.', 'when' => 'Wrong table or session.', 'example' => 'Round added to the wrong table.'],
                    ['name' => 'Other', 'what' => 'Not covered above.', 'why' => 'Escape hatch.', 'when' => 'Rare.', 'example' => 'Power cut mid-service.'],
                ],
                'notes' => [
                    'Tell the affected station verbally as well. The ticket disappears, but a half-made drink does not.',
                ],
            ],
            [
                'slug' => 'inventory-overview',
                'title' => 'Inventory Overview',
                'group' => 'Operations',
                'roles' => $operator,
                'tags' => ['inventory', 'stock', 'levels', 'low stock'],
                'overview' => 'A read-only view of ingredient stock levels so you can see what is running low before the floor finds out the hard way.',
                'how_it_works' => [
                    'Each ingredient shows its level and a status derived from its low-stock threshold.',
                    'Levels move automatically as orders consume ingredients through recipes.',
                ],
                'how_to_use' => [
                    'Scan low and out-of-stock items at the start of every shift.',
                    'Raise a refill request rather than waiting for someone else to notice.',
                    'Tell the floor before a popular item becomes unmakeable.',
                ],
                'how_to_configure' => [
                    'Thresholds and stock movements are administrator responsibilities.',
                ],
                'conditions' => [
                    ['if' => 'An ingredient is low stock', 'then' => 'It is at or below its threshold and needs a refill soon.'],
                    ['if' => 'An ingredient is out of stock', 'then' => 'Products depending on it cannot be made.'],
                ],
                'examples' => [
                    ['title' => 'Pre-rush check', 'body' => 'Oat milk shows low stock before the evening rush; raise a refill request now rather than mid-service.'],
                ],
                'options' => [
                    ['name' => 'In stock', 'what' => 'Above the threshold.', 'why' => 'Normal state.', 'when' => 'No action.', 'example' => '18 L of milk.'],
                    ['name' => 'Low stock', 'what' => 'At or below the threshold.', 'why' => 'Early warning.', 'when' => 'Raise a refill.', 'example' => '4 L against a 5 L threshold.'],
                    ['name' => 'Out of stock', 'what' => 'Zero.', 'why' => 'Products are blocked.', 'when' => 'Escalate immediately.', 'example' => '0 L of oat milk.'],
                ],
                'notes' => [
                    'Operators view stock; they do not adjust it. Corrections are recorded by administrators as movements.',
                ],
            ],
            [
                'slug' => 'refill-workflow',
                'title' => 'Refill Workflow',
                'group' => 'Operations',
                'roles' => $operator,
                'tags' => ['refill', 'restock', 'requests', 'workflow'],
                'overview' => 'Raising and tracking refill requests: naming the ingredient and quantity you need, and following it through to completion.',
                'how_it_works' => [
                    'A request names the ingredient and quantity and starts as pending.',
                    'Administrators approve or reject it, and it is completed once stock has physically moved.',
                ],
                'how_to_use' => [
                    'Raise the request the moment you see low stock, not when you run out.',
                    'Ask for a realistic quantity — a request for one litre of milk will be back in an hour.',
                    'Check your open requests before raising a duplicate.',
                ],
                'how_to_configure' => [
                    'No configuration. Create the request from the refill requests screen.',
                ],
                'conditions' => [
                    ['if' => 'A request is pending', 'then' => 'Nothing has moved yet.'],
                    ['if' => 'A request is approved but not completed', 'then' => 'It is agreed but the stock is still in the store.'],
                    ['if' => 'A request is rejected', 'then' => 'You need an alternative plan for the shift.'],
                ],
                'examples' => [
                    ['title' => 'Mid-shift request', 'body' => 'Request 5 L of oat milk at 3 pm, approved at 3:05, carried to the bar and completed at 3:12.'],
                ],
                'options' => [
                    ['name' => 'Pending', 'what' => 'Awaiting a decision.', 'why' => 'Makes the need visible.', 'when' => 'Just raised.', 'example' => '5 L oat milk requested.'],
                    ['name' => 'Approved', 'what' => 'Agreed, not yet moved.', 'why' => 'Decision separate from action.', 'when' => 'After approval.', 'example' => 'Approved at 3:05 pm.'],
                    ['name' => 'Rejected', 'what' => 'Declined.', 'why' => 'No stock will move.', 'when' => 'Nothing available.', 'example' => 'Store is empty too.'],
                    ['name' => 'Completed', 'what' => 'Stock delivered.', 'why' => 'Closes the loop.', 'when' => 'After the transfer.', 'example' => '5 L at the bar.'],
                ],
                'notes' => [
                    'One request per ingredient per need. Duplicates make the queue impossible to read.',
                ],
            ],
            [
                'slug' => 'preparation-status',
                'title' => 'Preparation Status',
                'group' => 'Operations',
                'roles' => $operator,
                'tags' => ['preparation', 'status', 'tickets', 'stations'],
                'overview' => 'What each preparation status actually means, so you can read the queue and answer questions accurately.',
                'how_it_works' => [
                    'A ticket is created per station when an order is confirmed.',
                    'It moves through pending, accepted, preparing and ready, with a timestamp at each step.',
                    'Cancelling the order cancels its tickets.',
                ],
                'how_to_use' => [
                    'Use the status plus the elapsed time, not the status alone, to judge whether something is stuck.',
                    'Report tickets sitting in pending to the station directly.',
                ],
                'how_to_configure' => [
                    'Not configurable from the operator panel.',
                ],
                'conditions' => [
                    ['if' => 'A ticket has been pending for several minutes', 'then' => 'Nobody has picked it up and the customer is waiting for nothing.'],
                    ['if' => 'All tickets are ready', 'then' => 'The order is complete at the stations and can be handed over.'],
                ],
                'examples' => [
                    ['title' => 'Reading a ticket', 'body' => 'Bar ticket "preparing", stage three minutes; kitchen ticket "pending", queue eleven minutes. The kitchen is the problem.'],
                ],
                'options' => [
                    ['name' => 'Pending', 'what' => 'Not yet picked up.', 'why' => 'Shows unclaimed work.', 'when' => 'Right after confirmation.', 'example' => 'New kitchen ticket.'],
                    ['name' => 'Accepted', 'what' => 'A station owns it.', 'why' => 'Prevents duplicate work.', 'when' => 'Staff tap accept.', 'example' => 'Chef accepts the ticket.'],
                    ['name' => 'Preparing', 'what' => 'Being made now.', 'why' => 'Live progress.', 'when' => 'Work has started.', 'example' => 'Sandwich on the grill.'],
                    ['name' => 'Ready', 'what' => 'Finished at that station.', 'why' => 'Cues collection.', 'when' => 'On the pass.', 'example' => 'Two lattes ready.'],
                    ['name' => 'Cancelled', 'what' => 'Stopped.', 'why' => 'Prevents wasted work.', 'when' => 'Order or round cancelled.', 'example' => 'Guest cancelled.'],
                ],
                'notes' => [
                    'Only the owning station can move its ticket. Operators read the queue, they do not drive it.',
                ],
            ],
            [
                'slug' => 'ready-served-completed',
                'title' => 'Ready, Served & Completed',
                'group' => 'Orders',
                'roles' => $operator,
                'tags' => ['ready', 'served', 'completed', 'handover'],
                'overview' => 'The three end-states people confuse most often: ready is a station fact, served is a dining fact, completed is an order fact.',
                'how_it_works' => [
                    'Ready means a station has finished its part of the order.',
                    'Served means a dining round physically reached the table.',
                    'Completed means the customer has the whole order and it is closed.',
                ],
                'how_to_use' => [
                    'Wait for every station ticket to be ready before handing over a mixed order.',
                    'Complete retail orders at handover so the queue reflects reality.',
                ],
                'how_to_configure' => [
                    'No configuration.',
                ],
                'conditions' => [
                    ['if' => 'One station is ready and the other is not', 'then' => 'Hold the handover so the order goes out together.'],
                    ['if' => 'An order is completed', 'then' => 'It counts as revenue and may trigger loyalty earning.'],
                ],
                'examples' => [
                    ['title' => 'Mixed takeaway', 'body' => 'Bar ready at three minutes, kitchen at eleven. Complete the order at eleven when the customer takes both.'],
                ],
                'notes' => [
                    'Completing early makes the queue look good and the customer feel ignored.',
                ],
            ],
            [
                'slug' => 'operator-boundaries',
                'title' => 'What Operators Cannot Do',
                'group' => 'Operations',
                'roles' => $operator,
                'tags' => ['boundaries', 'permissions', 'limits', 'escalation'],
                'overview' => 'The deliberate limits of the operator role, so you know immediately when something needs an administrator.',
                'how_it_works' => [
                    'The operator panel covers running the shift: orders, dining, payments, refills, inventory viewing and operational reports.',
                    'Catalog, pricing, marketing, loyalty configuration and user management are administrator-only.',
                    'Attempting an administrator action does not silently fail — the screen simply is not there.',
                ],
                'how_to_use' => [
                    'When you need a price, product, promotion or account change, ask an administrator rather than working around it.',
                    'Never share an administrator login to get past a boundary.',
                ],
                'how_to_configure' => [
                    'Boundaries follow the role. Changing them means changing the person’s role.',
                ],
                'conditions' => [
                    ['if' => 'A product price is wrong', 'then' => 'Escalate to an administrator; operators cannot edit the catalog.'],
                    ['if' => 'A customer needs a loyalty adjustment', 'then' => 'An administrator must make it.'],
                    ['if' => 'A new staff account is needed', 'then' => 'An administrator creates it.'],
                ],
                'examples' => [
                    ['title' => 'Wrong price mid-shift', 'body' => 'A drink is listed at ₹90 instead of ₹190. Message an administrator to fix the product; do not improvise with a discount.'],
                ],
                'options' => [
                    ['name' => 'Can do', 'what' => 'Orders, dining sessions, payments, cancellations, refill requests, inventory view, operational reports.', 'why' => 'Everything needed to run a shift.', 'when' => 'Daily operations.', 'example' => 'Confirming a UPI proof.'],
                    ['name' => 'Cannot do', 'what' => 'Catalog and pricing, promotions and campaigns, loyalty configuration, user and role management, website settings.', 'why' => 'These change the business, not the shift.', 'when' => 'Escalate to an administrator.', 'example' => 'Editing a promotion.'],
                ],
                'notes' => [
                    'These boundaries protect you as much as the business — you cannot accidentally change a price during a rush.',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function waiterModules(): array
    {
        $waiter = [self::ROLE_WAITER];

        return [
            [
                'slug' => 'tables',
                'title' => 'Tables',
                'group' => 'Dining',
                'roles' => $waiter,
                'tags' => ['tables', 'floor', 'seating', 'status'],
                'overview' => 'Your floor view. Which tables are free, which are occupied, and which are waiting on a bill or a payment.',
                'how_it_works' => [
                    'Table status comes from the session on it — you never set the status by hand.',
                    'Opening a session on a free table makes it occupied straight away.',
                    'Closing a paid session returns the table to available.',
                ],
                'how_to_use' => [
                    'Check the floor before seating guests so you do not double-seat a table.',
                    'Open the session as soon as guests sit, before they order.',
                    'Watch for your tables sitting at awaiting payment.',
                ],
                'how_to_configure' => [
                    'Tables are created by administrators. You work with what is on the floor.',
                ],
                'conditions' => [
                    ['if' => 'A table is occupied', 'then' => 'It already has an open session — add a round rather than starting a new session.'],
                    ['if' => 'A table shows bill requested', 'then' => 'Guests are waiting for their bill.'],
                ],
                'examples' => [
                    ['title' => 'Seating four', 'body' => 'Table 12 shows available, you seat the guests, open a session, and the table flips to occupied for everyone.'],
                ],
                'options' => [
                    ['name' => 'Available', 'what' => 'Free to seat.', 'why' => 'Ready for guests.', 'when' => 'No session.', 'example' => 'Table 5 cleared.'],
                    ['name' => 'Occupied', 'what' => 'Session open.', 'why' => 'Guests ordering.', 'when' => 'During the meal.', 'example' => 'Table 12 on Round 2.'],
                    ['name' => 'Bill requested', 'what' => 'Guests asked for the bill.', 'why' => 'Bring it now.', 'when' => 'End of the meal.', 'example' => 'Table 12 ready to pay.'],
                    ['name' => 'Awaiting payment', 'what' => 'Bill given, money not recorded.', 'why' => 'Finish the transaction.', 'when' => 'Between bill and payment.', 'example' => 'Guests finding cash.'],
                ],
                'notes' => [
                    'Never open a second session on an occupied table. Add a round to the existing session instead.',
                ],
            ],
            [
                'slug' => 'dining-sessions-rounds',
                'title' => 'Dining Sessions & Rounds',
                'group' => 'Dining',
                'roles' => $waiter,
                'tags' => ['sessions', 'rounds', 'ordering', 'dining'],
                'overview' => 'The heart of your job. A session is the table’s whole visit; a round is one batch of items they order within it.',
                'how_it_works' => [
                    'Open one session per table when guests sit; it stays open until the bill is settled.',
                    'Each new batch of items becomes the next numbered round in that session.',
                    'Every round generates its own bar and kitchen tickets.',
                    'All rounds roll into one running bill and one invoice.',
                ],
                'how_to_use' => [
                    'Open the session first, then add rounds as the guests order.',
                    'Read the order back to the table before saving a round — cancelling later costs the kitchen real work.',
                    'Add a new round rather than trying to edit a round already sent.',
                ],
                'how_to_configure' => [
                    'No configuration. Dine-in and tables are set up by administrators.',
                ],
                'conditions' => [
                    ['if' => 'A round is added to an open session', 'then' => 'It gets the next round number and its own tickets.'],
                    ['if' => 'A round contains drinks and food', 'then' => 'Two tickets are created — one for the bar, one for the kitchen.'],
                    ['if' => 'The session is closed', 'then' => 'No further rounds can be added.'],
                ],
                'examples' => [
                    ['title' => 'A normal visit', 'body' => 'Round 1: two cappuccinos. Round 2: a sandwich. Round 3: a brownie. One session, one bill, three sets of tickets.'],
                ],
                'notes' => [
                    'Round numbers never change. A cancelled Round 2 stays Round 2 in the history.',
                ],
            ],
            [
                'slug' => 'running-bill',
                'title' => 'Running Bill',
                'group' => 'Dining',
                'roles' => $waiter,
                'tags' => ['bill', 'total', 'session', 'rounds'],
                'overview' => 'The live total for a table across every round, so you can answer "how much so far?" without adding anything up.',
                'how_it_works' => [
                    'The bill accumulates as rounds are added and drops when a round is cancelled.',
                    'Discounts and any loyalty reward are reflected in the total, and tax is applied where it is enabled.',
                    'The final invoice covers the whole session as one document.',
                ],
                'how_to_use' => [
                    'Open the session to read the current total before the guests ask.',
                    'Check the total against what the guests ordered before presenting the bill.',
                ],
                'how_to_configure' => [
                    'No configuration. Tax and discount rules are administrator settings.',
                ],
                'conditions' => [
                    ['if' => 'A round is cancelled', 'then' => 'The running total drops by that round’s value.'],
                    ['if' => 'A promotion applies', 'then' => 'The discount is shown on the bill.'],
                ],
                'examples' => [
                    ['title' => 'Mid-meal question', 'body' => 'Guests at Table 12 ask how much so far; the session shows ₹840 across two rounds and you can answer immediately.'],
                ],
                'notes' => [
                    'If a total looks wrong, check the rounds first — it is almost always a round entered on the wrong table.',
                ],
            ],
            [
                'slug' => 'call-waiter',
                'title' => 'Call Waiter Requests',
                'group' => 'Dining',
                'roles' => $waiter,
                'tags' => ['call waiter', 'service', 'requests', 'claim'],
                'overview' => 'Guests calling for help from their table. You are notified, you claim the request, you deal with it, and it is resolved.',
                'how_it_works' => [
                    'A request is raised for order assistance and carries the table and session.',
                    'If the session has a preferred waiter, that waiter is notified first with a time limit.',
                    'Unclaimed requests escalate and are broadcast to every waiter.',
                    'Claiming a request tells the rest of the floor it is handled.',
                ],
                'how_to_use' => [
                    'Claim before you walk — otherwise two of you make the same trip.',
                    'If you cannot get there, leave it unclaimed so it escalates to someone who can.',
                ],
                'how_to_configure' => [
                    'No configuration.',
                ],
                'conditions' => [
                    ['if' => 'You are the preferred waiter', 'then' => 'You are notified first and get a window to respond.'],
                    ['if' => 'You do not claim in time', 'then' => 'The request escalates to all waiters.'],
                    ['if' => 'Another waiter claims it', 'then' => 'It leaves your list.'],
                ],
                'examples' => [
                    ['title' => 'Escalated call', 'body' => 'Table 12 calls their preferred waiter, who is carrying a tray. The request escalates, you claim it, and the guest is served without a second wait.'],
                ],
                'notes' => [
                    'Claiming a request you cannot reach is worse than leaving it — it stops the escalation.',
                ],
            ],
            [
                'slug' => 'preferred-waiter',
                'title' => 'Preferred Waiter',
                'group' => 'Dining',
                'roles' => $waiter,
                'tags' => ['preferred waiter', 'ownership', 'routing', 'service'],
                'overview' => 'Each session can have a preferred waiter, so calls from that table reach the person who already knows the guests.',
                'how_it_works' => [
                    'The preferred waiter is resolved from the session; if there is one, calls route to them first.',
                    'If nobody responds within the window, the request escalates to every waiter.',
                    'Being preferred does not stop other waiters from serving that table.',
                ],
                'how_to_use' => [
                    'Respond quickly to your own tables — that is the point of being preferred.',
                    'When you go on a break, tell the floor so escalations are expected.',
                ],
                'how_to_configure' => [
                    'No configuration. Preference follows the session.',
                ],
                'conditions' => [
                    ['if' => 'The session has no preferred waiter', 'then' => 'Requests are broadcast to everyone immediately.'],
                    ['if' => 'The preferred waiter does not respond', 'then' => 'The request escalates.'],
                ],
                'examples' => [
                    ['title' => 'Shared table', 'body' => 'You opened Table 12 and are preferred. Another waiter still takes Round 3 while you are busy — both are recorded against the same session.'],
                ],
                'notes' => [
                    'Preferred waiter is a routing preference, not table ownership. Anyone can serve any table.',
                ],
            ],
            [
                'slug' => 'preparation-progress',
                'title' => 'Preparation Progress',
                'group' => 'Dining',
                'roles' => $waiter,
                'tags' => ['preparation', 'progress', 'kitchen', 'bar', 'timing'],
                'overview' => 'Where each round is in the bar and kitchen, so you can answer guests honestly instead of guessing.',
                'how_it_works' => [
                    'Each round produces station tickets that move through pending, accepted, preparing and ready.',
                    'Bar and kitchen tickets for the same round progress independently.',
                    'You can see progress from the session; you cannot move a station’s ticket yourself.',
                ],
                'how_to_use' => [
                    'Check the session before answering "how long?".',
                    'Tell guests the truth — "the food is about five minutes behind the drinks" beats "any moment now".',
                ],
                'how_to_configure' => [
                    'No configuration.',
                ],
                'conditions' => [
                    ['if' => 'A ticket is still pending', 'then' => 'The station has not started; check with them.'],
                    ['if' => 'Drinks are ready and food is preparing', 'then' => 'Decide with the table whether to bring drinks first.'],
                ],
                'examples' => [
                    ['title' => 'Split timing', 'body' => 'Round 2 has drinks ready at two minutes and a sandwich preparing at eight. Take the drinks over and tell the table food follows shortly.'],
                ],
                'notes' => [
                    'Only chefs and baristas move their own tickets. Chasing in person is fine; changing status is not yours to do.',
                ],
            ],
            [
                'slug' => 'ready-to-serve',
                'title' => 'Ready to Serve',
                'group' => 'Dining',
                'roles' => $waiter,
                'tags' => ['ready', 'pass', 'collection', 'service'],
                'overview' => 'When a station marks a ticket ready, the items are on the pass waiting for you.',
                'how_it_works' => [
                    'Ready means that station has finished its part of the round.',
                    'A round with bar and kitchen items has two tickets, and both must be ready for the round to go out together.',
                    'Ready items do not improve while they wait.',
                ],
                'how_to_use' => [
                    'Collect promptly — a ready coffee is only good for a minute or two.',
                    'Check the table number and round on the ticket before you lift the tray.',
                    'For a mixed round, wait for both tickets rather than making two trips.',
                ],
                'how_to_configure' => [
                    'No configuration.',
                ],
                'conditions' => [
                    ['if' => 'Only one station is ready', 'then' => 'Either wait for the other or agree with the table to serve in stages.'],
                    ['if' => 'A ready item sits too long', 'then' => 'Quality drops and it may need remaking.'],
                ],
                'examples' => [
                    ['title' => 'Mixed round', 'body' => 'Bar ticket ready at three minutes, kitchen at nine. Serve Round 2 at nine minutes so the table eats together.'],
                ],
                'notes' => [
                    'Ready is a station fact. Served is your fact — record it when the food is actually on the table.',
                ],
            ],
            [
                'slug' => 'served',
                'title' => 'Marking Served',
                'group' => 'Dining',
                'roles' => $waiter,
                'tags' => ['served', 'delivery', 'rounds', 'accuracy'],
                'overview' => 'Recording that a round reached the table. It is per round, not per session, and the timing matters.',
                'how_it_works' => [
                    'Each round is marked served independently.',
                    'The served time is what service-speed reporting is built from.',
                    'Serving does not close the session — the table can keep ordering.',
                ],
                'how_to_use' => [
                    'Mark served at the table, not on the way back to the counter.',
                    'Mark each round as you deliver it rather than batching at the end of the shift.',
                ],
                'how_to_configure' => [
                    'No configuration.',
                ],
                'conditions' => [
                    ['if' => 'Rounds are marked served late', 'then' => 'Reporting makes the floor look slower than it is.'],
                    ['if' => 'A round is cancelled', 'then' => 'It is never marked served.'],
                ],
                'examples' => [
                    ['title' => 'Three rounds', 'body' => 'Round 1 served at 1:12, Round 2 at 1:31, Round 3 at 2:04 — each recorded as it landed.'],
                ],
                'notes' => [
                    'Marking everything served at closing time produces tidy-looking data that tells nobody anything.',
                ],
            ],
            [
                'slug' => 'bill-request',
                'title' => 'Bill Request',
                'group' => 'Dining',
                'roles' => $waiter,
                'tags' => ['bill', 'request', 'closing', 'invoice'],
                'overview' => 'Moving a table from eating to paying. Requesting the bill marks the session and tells the floor the table is closing out.',
                'how_it_works' => [
                    'Requesting the bill moves the session to billing requested and the table reflects it.',
                    'The invoice covers every round in the session as one document.',
                    'After payment the session is closed and the table returns to available.',
                ],
                'how_to_use' => [
                    'Request the bill as soon as guests ask, so the floor view is accurate.',
                    'Check the running total against what they ordered before presenting it.',
                    'Present the invoice, then take payment.',
                ],
                'how_to_configure' => [
                    'No configuration. Tax and business details on the invoice are administrator settings.',
                ],
                'conditions' => [
                    ['if' => 'The bill is requested', 'then' => 'The table shows bill requested to everyone on the floor.'],
                    ['if' => 'Guests order again after requesting the bill', 'then' => 'Handle it before payment rather than after closing.'],
                ],
                'examples' => [
                    ['title' => 'One more coffee', 'body' => 'Table 12 requests the bill then asks for one more coffee. Add it as Round 4 before payment so it lands on the same invoice.'],
                ],
                'notes' => [
                    'Presenting the bill is not the same as receiving payment. Record the payment separately.',
                ],
            ],
            [
                'slug' => 'waiter-payments',
                'title' => 'Taking Payment',
                'group' => 'Dining',
                'roles' => $waiter,
                'tags' => ['payment', 'cash', 'closing', 'session'],
                'overview' => 'Settling a table: choosing the payment method, recording cash received, and closing the session so the table frees up.',
                'how_it_works' => [
                    'Set the payment method on the session, then record the payment.',
                    'Recording cash confirms the payment immediately.',
                    'Once payment is recorded the session can be closed and the table returns to available.',
                ],
                'how_to_use' => [
                    'Count the cash in front of the guest and record the exact amount received.',
                    'Record it at the table, not later from memory.',
                    'Close the session once payment is done so the table is released.',
                ],
                'how_to_configure' => [
                    'Available payment methods are administrator settings.',
                ],
                'conditions' => [
                    ['if' => 'Cash is recorded', 'then' => 'The session payment is confirmed.'],
                    ['if' => 'The session is closed', 'then' => 'The table becomes available again.'],
                    ['if' => 'Something needs correcting after closing', 'then' => 'Ask an operator or administrator to reopen the session.'],
                ],
                'examples' => [
                    ['title' => 'Cash settlement', 'body' => 'Bill is ₹1,240, the guest hands over ₹1,300, you record ₹1,240 received, give ₹60 change, and close the session.'],
                ],
                'notes' => [
                    'Never write down card details or UPI credentials. Record only the method and the amount.',
                ],
            ],
            [
                'slug' => 'waiter-cancellation',
                'title' => 'Cancelling a Round',
                'group' => 'Dining',
                'roles' => $waiter,
                'tags' => ['cancel', 'round', 'reason', 'mistake'],
                'overview' => 'Stopping a round that should not be made, with an honest reason so the pattern can be seen later.',
                'how_it_works' => [
                    'Cancelling a round cancels its bar and kitchen tickets so the stations stop.',
                    'The round’s value leaves the running bill; the rest of the session is unaffected.',
                    'Every cancellation records a reason from a fixed list.',
                ],
                'how_to_use' => [
                    'Cancel the moment you know, then tell the station verbally as well.',
                    'Cancel only the wrong round, not the whole session.',
                    'Pick the reason that is actually true, even when it is your own mistake.',
                ],
                'how_to_configure' => [
                    'No configuration. The reason list is fixed.',
                ],
                'conditions' => [
                    ['if' => 'The round has not been started', 'then' => 'Cancelling costs nothing.'],
                    ['if' => 'The item is already made', 'then' => 'Cancelling stops the ticket but the waste has happened — tell the station immediately.'],
                ],
                'examples' => [
                    ['title' => 'Wrong table', 'body' => 'You add Round 2 to Table 11 instead of Table 12. Cancel with reason "Staff error", then add it to Table 12.'],
                ],
                'options' => [
                    ['name' => 'Customer cancelled', 'what' => 'The guest changed their mind.', 'why' => 'Not a staff error.', 'when' => 'Guest drops an item.', 'example' => 'No dessert after all.'],
                    ['name' => 'Wrong item', 'what' => 'The wrong item was entered.', 'why' => 'Tracks entry accuracy.', 'when' => 'Mis-keyed item.', 'example' => 'Oat instead of soy.'],
                    ['name' => 'Duplicate order', 'what' => 'Entered twice.', 'why' => 'Keeps the bill correct.', 'when' => 'Two waiters entered the same round.', 'example' => 'Round 3 twice.'],
                    ['name' => 'Preparation error', 'what' => 'Made incorrectly.', 'why' => 'Tracks station errors.', 'when' => 'Item must be remade.', 'example' => 'Wrong milk used.'],
                    ['name' => 'Quality issue', 'what' => 'Below standard.', 'why' => 'Tracks quality.', 'when' => 'Guest rejects it.', 'example' => 'Cold food.'],
                    ['name' => 'Staff error', 'what' => 'Process mistake.', 'why' => 'Honest signal for training.', 'when' => 'Wrong table or session.', 'example' => 'Round on the wrong table.'],
                    ['name' => 'Other', 'what' => 'Not covered above.', 'why' => 'Escape hatch.', 'when' => 'Rare.', 'example' => 'Guest left suddenly.'],
                ],
                'notes' => [
                    'Choosing "Other" every time hides the real problem. The reason list exists to fix things, not to blame people.',
                ],
            ],
            [
                'slug' => 'multi-waiter-session',
                'title' => 'Multiple Waiters on One Table',
                'group' => 'Dining',
                'roles' => $waiter,
                'tags' => ['multi waiter', 'shared table', 'rounds', 'ownership', 'session'],
                'overview' => 'Several waiters can serve the same table, and guests can order for themselves. Everything lands in the same session and the same bill.',
                'how_it_works' => [
                    'A session belongs to the table, not to a waiter. Any waiter can add a round to it.',
                    'Round numbers run in sequence across the whole session regardless of who added them.',
                    'The preferred waiter only affects where call-waiter requests are routed first.',
                    'All rounds — staff-entered or guest-placed — roll into one running bill and one invoice.',
                ],
                'how_to_use' => [
                    'Always check the table’s existing session before starting anything new.',
                    'Add your round to the open session; never open a second session on an occupied table.',
                    'Tell the preferred waiter when you serve one of their tables so nobody doubles up.',
                ],
                'how_to_configure' => [
                    'No configuration.',
                ],
                'conditions' => [
                    ['if' => 'A second session is opened on the same table', 'then' => 'The table has two bills and the guests get charged twice — cancel the duplicate immediately.'],
                    ['if' => 'A guest orders directly from the table', 'then' => 'It becomes the next round in the same session.'],
                    ['if' => 'The preferred waiter is busy', 'then' => 'Any other waiter can still take rounds for that table.'],
                ],
                'examples' => [
                    ['title' => 'Table 12 with three waiters and a guest order', 'body' => 'Waiter A opens the session for Table 12 and is the preferred waiter. Round 1 (two cappuccinos) is entered by Waiter A. Waiter B passes the table later and enters Round 2 (a sandwich). The guests then order Round 3 (a brownie) themselves from the table. Waiter C, who is nearest the pass, serves Round 3 and marks it served. At the end there is one session for Table 12 with rounds 1, 2 and 3, one running bill and one invoice — regardless of who entered or served each round.'],
                ],
                'notes' => [
                    'The only real hazard is a duplicate session on the same table. Check before you open.',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function baristaModules(): array
    {
        $barista = [self::ROLE_BARISTA];

        return [
            [
                'slug' => 'bar-queue',
                'title' => 'Bar Queue',
                'group' => 'Bar',
                'roles' => $barista,
                'tags' => ['queue', 'bar', 'tickets', 'drinks'],
                'overview' => 'Your work list. Every drink ticket for the bar, with its table, round number, items, add-ons and how long it has been waiting.',
                'how_it_works' => [
                    'Only bar-station items reach your queue; food goes to the kitchen.',
                    'Each ticket shows the order number, table label and round number where the order came from a table.',
                    'Items list the quantity, product, variant and each add-on, plus any customer notes on the order.',
                    'Queue age and stage time are shown live so you can see what is aging.',
                ],
                'how_to_use' => [
                    'Work oldest first unless a table is clearly waiting on a mixed order.',
                    'Read the whole ticket including add-ons and notes before you start.',
                    'Keep the queue moving — an unaccepted ticket looks like nothing is happening.',
                ],
                'how_to_configure' => [
                    'Nothing to configure. Which items land at the bar is set on the product by administrators.',
                ],
                'conditions' => [
                    ['if' => 'An order contains drinks and food', 'then' => 'You get the drinks ticket and the kitchen gets a separate one.'],
                    ['if' => 'A round is cancelled', 'then' => 'The ticket disappears from your queue — stop work on it.'],
                    ['if' => 'A drink appears that should be food', 'then' => 'Tell an administrator; the product routing is wrong.'],
                ],
                'examples' => [
                    ['title' => 'Reading a bar ticket', 'body' => 'Order #1042, Table 12 · Round 2 — 2× Cappuccino (Large) with "+ Extra shot ×1 each", 1× Iced Latte (Regular). Notes: "less sugar". Queue age 1m 20s.'],
                ],
                'notes' => [
                    'The ticket is the instruction. If it disagrees with what a waiter tells you, check with them before making anything.',
                ],
            ],
            [
                'slug' => 'accept-preparing-ready',
                'title' => 'Accept, Preparing, Ready',
                'group' => 'Bar',
                'roles' => $barista,
                'tags' => ['accept', 'preparing', 'ready', 'status', 'workflow'],
                'overview' => 'The three taps that drive every bar ticket. Used honestly they tell the whole café exactly where a drink is.',
                'how_it_works' => [
                    'Accept claims the ticket so nobody else starts the same drink.',
                    'Start preparing marks that you are actually making it now.',
                    'Mark ready tells the floor the drink is on the pass.',
                    'Each transition is timestamped, which is what queue age and stage timings are built from.',
                ],
                'how_to_use' => [
                    'Accept only what you are about to make — accepting the whole screen hides the real queue.',
                    'Tap preparing when work genuinely starts, not when you accept.',
                    'Tap ready when the drink is actually finished and on the pass.',
                ],
                'how_to_configure' => [
                    'No configuration.',
                ],
                'conditions' => [
                    ['if' => 'A ticket is pending', 'then' => 'Nobody has claimed it and the customer is waiting on nothing.'],
                    ['if' => 'You accept a ticket', 'then' => 'It is yours; other stations and the floor can see it is claimed.'],
                    ['if' => 'You mark ready early', 'then' => 'A waiter collects an unfinished drink and the timing data becomes useless.'],
                ],
                'examples' => [
                    ['title' => 'Two lattes', 'body' => 'Accept at 10:02, start preparing at 10:02, mark ready at 10:05. The waiter sees ready and collects for Table 12.'],
                ],
                'options' => [
                    ['name' => 'Pending', 'what' => 'Ticket waiting, unclaimed.', 'why' => 'Shows work nobody has taken.', 'when' => 'Right after the order.', 'example' => 'New ticket on screen.'],
                    ['name' => 'Accepted', 'what' => 'You have claimed it.', 'why' => 'Prevents duplicate drinks.', 'when' => 'You are about to start.', 'example' => 'You accept #1042.'],
                    ['name' => 'Preparing', 'what' => 'Being made now.', 'why' => 'Live progress for the floor.', 'when' => 'Work has started.', 'example' => 'Pulling the shot.'],
                    ['name' => 'Ready', 'what' => 'Finished and on the pass.', 'why' => 'Cues collection.', 'when' => 'Drink is complete.', 'example' => 'Two lattes ready.'],
                    ['name' => 'Cancelled', 'what' => 'Ticket stopped.', 'why' => 'Prevents wasted work.', 'when' => 'Order or round cancelled.', 'example' => 'Guest cancelled Round 2.'],
                ],
                'notes' => [
                    'Skipping preparing makes every drink look instant and hides where time actually goes.',
                ],
            ],
            [
                'slug' => 'recipes-instructions',
                'title' => 'Recipes & Preparation Instructions',
                'group' => 'Bar',
                'roles' => $barista,
                'tags' => ['recipes', 'spec', 'consistency', 'ingredients'],
                'overview' => 'The specification for each drink: the ingredients and quantities that make it correct every time.',
                'how_it_works' => [
                    'Each product, variant and add-on has its own recipe lines with exact quantities.',
                    'A large and a regular are different recipes, not the same drink in a bigger cup.',
                    'Making to the recipe is also what keeps stock accurate, because consumption is calculated from it.',
                ],
                'how_to_use' => [
                    'Look up a recipe whenever you are unsure rather than approximating.',
                    'Check the variant on the ticket before pulling the recipe.',
                    'If the recipe does not match how the drink is actually made, tell an administrator so it gets corrected.',
                ],
                'how_to_configure' => [
                    'Recipes are maintained by administrators. You can read them in your panel but not change them.',
                ],
                'conditions' => [
                    ['if' => 'You free-pour instead of following the recipe', 'then' => 'Stock levels drift and the next customer gets a different drink.'],
                    ['if' => 'An add-on is on the ticket', 'then' => 'It has its own recipe consumption on top of the base drink.'],
                ],
                'examples' => [
                    ['title' => 'Latte spec', 'body' => 'Regular: espresso 18 g, milk 150 ml. Large: espresso 18 g, milk 220 ml. Extra shot add-on: a further 18 g.'],
                ],
                'notes' => [
                    'Recipes show ingredients and quantities. They never show cost or margin — that is deliberate.',
                ],
            ],
            [
                'slug' => 'inventory-refill-bar',
                'title' => 'Bar Stock & Refill Requests',
                'group' => 'Bar',
                'roles' => $barista,
                'tags' => ['inventory', 'stock', 'refill', 'requests', 'bar'],
                'overview' => 'Seeing what is running low at the bar and formally asking for more, so shortages get handled instead of improvised around.',
                'how_it_works' => [
                    'You can view ingredient stock levels and their status: in stock, low stock or out of stock.',
                    'You raise a refill request naming the ingredient and quantity; it starts as pending.',
                    'An administrator or operator approves or rejects it, and it is completed once stock physically arrives.',
                ],
                'how_to_use' => [
                    'Check stock at the start of your shift, before the rush finds the gap for you.',
                    'Raise the request when an item goes low, not when it hits zero.',
                    'Ask for a quantity that will actually last the shift.',
                    'Check your existing requests before raising a duplicate.',
                ],
                'how_to_configure' => [
                    'No configuration. Thresholds are set by administrators on each ingredient.',
                ],
                'conditions' => [
                    ['if' => 'An ingredient is low stock', 'then' => 'It is at or below its threshold — raise a refill now.'],
                    ['if' => 'An ingredient is out of stock', 'then' => 'You cannot make drinks that need it; tell the floor immediately.'],
                    ['if' => 'A request is approved but not completed', 'then' => 'The stock is agreed but has not been carried to the bar yet.'],
                ],
                'examples' => [
                    ['title' => 'Oat milk running out', 'body' => 'Oat milk shows low stock at 3 pm. Request 5 L, it is approved at 3:05, delivered at 3:12 and marked completed — no drinks were refused.'],
                ],
                'options' => [
                    ['name' => 'In stock', 'what' => 'Comfortably above the threshold.', 'why' => 'Normal state.', 'when' => 'No action.', 'example' => '18 L of milk.'],
                    ['name' => 'Low stock', 'what' => 'At or below the threshold.', 'why' => 'Time to ask.', 'when' => 'Raise a refill.', 'example' => '4 L against a 5 L threshold.'],
                    ['name' => 'Out of stock', 'what' => 'None left.', 'why' => 'Drinks are blocked.', 'when' => 'Escalate immediately.', 'example' => '0 L of oat milk.'],
                    ['name' => 'Pending', 'what' => 'Request awaiting a decision.', 'why' => 'Makes the need visible.', 'when' => 'Just raised.', 'example' => '5 L oat milk requested.'],
                    ['name' => 'Approved', 'what' => 'Agreed, not yet moved.', 'why' => 'Decision separate from delivery.', 'when' => 'After approval.', 'example' => 'Approved at 3:05 pm.'],
                    ['name' => 'Rejected', 'what' => 'Declined.', 'why' => 'Nothing will be moved.', 'when' => 'Store is empty too.', 'example' => 'Plan an alternative.'],
                    ['name' => 'Completed', 'what' => 'Stock delivered.', 'why' => 'Closes the loop.', 'when' => 'After the transfer.', 'example' => '5 L at the bar.'],
                ],
                'notes' => [
                    'Never substitute an ingredient silently. Tell the floor so guests are told before they order.',
                ],
            ],
            [
                'slug' => 'station-responsibility',
                'title' => 'Bar Station Responsibility',
                'group' => 'Bar',
                'roles' => $barista,
                'tags' => ['station', 'responsibility', 'bar', 'scope'],
                'overview' => 'What the bar owns: every beverage ticket, from accepting it to putting the finished drink on the pass.',
                'how_it_works' => [
                    'Products are routed to a station by administrators; beverages go to the bar.',
                    'You own your ticket end to end, but only your ticket — the kitchen owns theirs.',
                    'Your timestamps feed the operational performance reporting for the bar.',
                ],
                'how_to_use' => [
                    'Own the drinks completely: accept, prepare, and mark ready promptly.',
                    'Do not wait for the kitchen — mark your ticket ready as soon as the drinks are done.',
                    'Flag mis-routed items rather than making food at the bar.',
                ],
                'how_to_configure' => [
                    'Routing is a product setting maintained by administrators.',
                ],
                'conditions' => [
                    ['if' => 'A food item appears in your queue', 'then' => 'The product routing is wrong; report it rather than absorbing it.'],
                    ['if' => 'Your ticket is ready and the kitchen’s is not', 'then' => 'The floor decides how to serve — your job is done.'],
                ],
                'examples' => [
                    ['title' => 'Not your ticket', 'body' => 'A sandwich shows in the bar queue. Tell an administrator to fix the product station instead of walking it to the kitchen every time.'],
                ],
                'notes' => [
                    'Baristas do not see prices, revenue or customer payment details, and do not need them to do the job.',
                ],
            ],
            [
                'slug' => 'split-orders',
                'title' => 'Split Orders (Bar & Kitchen)',
                'group' => 'Bar',
                'roles' => $barista,
                'tags' => ['split', 'mixed order', 'kitchen', 'coordination'],
                'overview' => 'When one order contains drinks and food, it becomes two tickets that progress independently — yours and the kitchen’s.',
                'how_it_works' => [
                    'The order is split by station: beverages to the bar, food to the kitchen.',
                    'Each ticket is accepted, prepared and marked ready separately.',
                    'The floor usually serves a table once both tickets are ready.',
                ],
                'how_to_use' => [
                    'Make your drinks when they are due, not when you guess the food will land.',
                    'For a mixed round with a long food item, check with the floor whether drinks should go early.',
                    'Never hold a ready drink on the pass hoping the kitchen catches up — tell the floor instead.',
                ],
                'how_to_configure' => [
                    'No configuration. Splitting follows product station routing.',
                ],
                'conditions' => [
                    ['if' => 'The kitchen ticket is much slower', 'then' => 'The floor decides whether to serve drinks first.'],
                    ['if' => 'The round is cancelled', 'then' => 'Both tickets are cancelled.'],
                ],
                'examples' => [
                    ['title' => 'Mixed round for Table 12', 'body' => 'Round 2 is two cappuccinos and one sandwich. You get a bar ticket with the cappuccinos; the kitchen gets a separate ticket with the sandwich. Both show Table 12 · Round 2.'],
                ],
                'notes' => [
                    'Same order number, same table, same round, two tickets. Matching them by table and round is what avoids confusion.',
                ],
            ],
            [
                'slug' => 'barista-boundaries',
                'title' => 'What Baristas Cannot Do',
                'group' => 'Bar',
                'roles' => $barista,
                'tags' => ['boundaries', 'permissions', 'limits', 'escalation'],
                'overview' => 'The deliberate limits of the barista role, so you know when to escalate instead of improvising.',
                'how_it_works' => [
                    'Your panel covers the bar queue, recipes, product reference, bar stock and refill requests.',
                    'Prices, promotions, customer data, payments and reporting are not in your panel at all.',
                    'You can move your own tickets only — not kitchen tickets, orders or dining sessions.',
                ],
                'how_to_use' => [
                    'Escalate anything about price, discount, refund or a customer’s account to an operator or administrator.',
                    'Report catalog problems — wrong station, wrong recipe — rather than working around them every shift.',
                ],
                'how_to_configure' => [
                    'Boundaries follow the role and are not configurable from your panel.',
                ],
                'conditions' => [
                    ['if' => 'A guest disputes a charge', 'then' => 'Pass it to an operator; you have no access to payments.'],
                    ['if' => 'A recipe is wrong', 'then' => 'Report it to an administrator; you can read recipes but not edit them.'],
                    ['if' => 'A drink needs cancelling', 'then' => 'The waiter or operator cancels the round, not you.'],
                ],
                'examples' => [
                    ['title' => 'Guest asks for a discount', 'body' => 'Say you will fetch someone who can help, and pass it to an operator. Never offer a free item on your own authority.'],
                ],
                'options' => [
                    ['name' => 'Can do', 'what' => 'Accept, prepare and complete bar tickets; read recipes and products; view bar stock; raise refill requests.', 'why' => 'Everything needed to run the bar.', 'when' => 'Every shift.', 'example' => 'Marking two lattes ready.'],
                    ['name' => 'Cannot do', 'what' => 'Edit the catalog or recipes, cancel orders or rounds, take or verify payments, view revenue or customer data.', 'why' => 'Those belong to other roles.', 'when' => 'Escalate.', 'example' => 'Asking an operator to handle a refund.'],
                ],
                'notes' => [
                    'These limits keep you out of trouble. Nobody can accuse you of a pricing or payment error you had no access to make.',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function chefModules(): array
    {
        $chef = [self::ROLE_CHEF];

        return [
            [
                'slug' => 'kitchen-queue',
                'title' => 'Kitchen Queue',
                'group' => 'Kitchen',
                'roles' => $chef,
                'tags' => ['queue', 'kitchen', 'tickets', 'food'],
                'overview' => 'Your work list. Every food ticket for the kitchen, with its table, round number, items, add-ons and how long it has been waiting.',
                'how_it_works' => [
                    'Only kitchen-station items reach your queue; drinks go to the bar.',
                    'Each ticket shows the order number, table label and round number where the order came from a table.',
                    'Items list quantity, product, variant and each add-on, plus any customer notes on the order.',
                    'Queue age and stage time are shown live so you can see what is aging.',
                ],
                'how_to_use' => [
                    'Work oldest first unless a table is clearly waiting on a mixed order.',
                    'Read the whole ticket including add-ons and notes before you start.',
                    'Accept promptly so the floor knows the ticket has been picked up.',
                ],
                'how_to_configure' => [
                    'Nothing to configure. Which items land in the kitchen is set on the product by administrators.',
                ],
                'conditions' => [
                    ['if' => 'An order contains food and drinks', 'then' => 'You get the food ticket and the bar gets a separate one.'],
                    ['if' => 'A round is cancelled', 'then' => 'The ticket disappears from your queue — stop work on it.'],
                    ['if' => 'A drink appears in your queue', 'then' => 'The product routing is wrong; report it to an administrator.'],
                ],
                'examples' => [
                    ['title' => 'Reading a kitchen ticket', 'body' => 'Order #1042, Table 12 · Round 2 — 1× Grilled Sandwich with "+ Extra cheese ×1 each", 1× Brownie. Notes: "no onion". Queue age 40s.'],
                ],
                'notes' => [
                    'The ticket is the instruction. If a waiter says something different, confirm before you cook it.',
                ],
            ],
            [
                'slug' => 'accept-preparing-ready-kitchen',
                'title' => 'Accept, Preparing, Ready',
                'group' => 'Kitchen',
                'roles' => $chef,
                'tags' => ['accept', 'preparing', 'ready', 'status', 'workflow'],
                'overview' => 'The three taps that drive every kitchen ticket. Used honestly they tell the whole café exactly where a dish is.',
                'how_it_works' => [
                    'Accept claims the ticket so nobody else starts the same dish.',
                    'Start preparing marks that you are actually cooking it now.',
                    'Mark ready tells the floor the food is on the pass.',
                    'Each transition is timestamped, which is what queue age and stage timings are built from.',
                ],
                'how_to_use' => [
                    'Accept only what you are about to cook.',
                    'Tap preparing when the food actually goes on, not when you accept.',
                    'Tap ready when the dish is plated and on the pass.',
                ],
                'how_to_configure' => [
                    'No configuration.',
                ],
                'conditions' => [
                    ['if' => 'A ticket is pending', 'then' => 'Nobody has claimed it and the customer is waiting on nothing.'],
                    ['if' => 'You accept a ticket', 'then' => 'It is yours and the floor can see it is claimed.'],
                    ['if' => 'You mark ready early', 'then' => 'A waiter collects food that is not finished and the timing data becomes useless.'],
                ],
                'examples' => [
                    ['title' => 'Sandwich ticket', 'body' => 'Accept at 1:14, start preparing at 1:15, mark ready at 1:23. The waiter collects for Table 12.'],
                ],
                'options' => [
                    ['name' => 'Pending', 'what' => 'Ticket waiting, unclaimed.', 'why' => 'Shows work nobody has taken.', 'when' => 'Right after the order.', 'example' => 'New ticket on screen.'],
                    ['name' => 'Accepted', 'what' => 'You have claimed it.', 'why' => 'Prevents two people cooking the same dish.', 'when' => 'You are about to start.', 'example' => 'You accept #1042.'],
                    ['name' => 'Preparing', 'what' => 'Being cooked now.', 'why' => 'Live progress for the floor.', 'when' => 'Food has gone on.', 'example' => 'Sandwich on the grill.'],
                    ['name' => 'Ready', 'what' => 'Plated and on the pass.', 'why' => 'Cues collection.', 'when' => 'Dish is complete.', 'example' => 'Sandwich ready for Table 12.'],
                    ['name' => 'Cancelled', 'what' => 'Ticket stopped.', 'why' => 'Prevents wasted work.', 'when' => 'Order or round cancelled.', 'example' => 'Guest cancelled Round 2.'],
                ],
                'notes' => [
                    'Skipping preparing makes every dish look instant and hides where kitchen time actually goes.',
                ],
            ],
            [
                'slug' => 'food-recipes',
                'title' => 'Food Specs & Ticket Detail',
                'group' => 'Kitchen',
                'roles' => $chef,
                'tags' => ['recipes', 'spec', 'consistency', 'add-ons', 'notes'],
                'overview' => 'Everything the ticket tells you about how a dish should be made: quantity, product, variant, add-ons and the customer’s notes.',
                'how_it_works' => [
                    'The ticket lists each item with its quantity, variant and every chosen add-on.',
                    'Customer notes on the order are shown on the ticket.',
                    'Recipes behind each dish are maintained by administrators and are what keep stock accurate.',
                    'Recipe browsing is not part of the chef panel — the ticket plus your kitchen spec is the working instruction.',
                ],
                'how_to_use' => [
                    'Read the variant and add-ons before starting; they change the dish, not just the price.',
                    'Treat customer notes as instructions, especially anything that sounds like an allergy.',
                    'If a recipe or spec is wrong, tell an administrator so it is fixed for everyone.',
                ],
                'how_to_configure' => [
                    'Recipes and add-on definitions are maintained by administrators.',
                ],
                'conditions' => [
                    ['if' => 'An add-on is listed', 'then' => 'It must be made as part of that item.'],
                    ['if' => 'A customer note mentions an allergy', 'then' => 'Escalate to the waiter before cooking if you cannot guarantee it.'],
                    ['if' => 'The ticket has no notes', 'then' => 'Make it to the standard spec.'],
                ],
                'examples' => [
                    ['title' => 'Add-ons on a ticket', 'body' => '1× Grilled Sandwich with "+ Extra cheese ×1 each" and the note "no onion" — one sandwich, extra cheese, onion omitted.'],
                ],
                'notes' => [
                    'Never guess an allergy answer. Ask the waiter to confirm with the guest.',
                ],
            ],
            [
                'slug' => 'inventory-refill-kitchen',
                'title' => 'Kitchen Shortages & Refills',
                'group' => 'Kitchen',
                'roles' => $chef,
                'tags' => ['inventory', 'shortage', 'refill', 'escalation', 'kitchen'],
                'overview' => 'What to do when the kitchen runs short. Raising and approving refill requests happens in the operator and administrator panels, so shortages are reported rather than entered by you.',
                'how_it_works' => [
                    'Stock is consumed automatically as orders are made, using the recipes behind each dish.',
                    'Refill requests are raised and tracked in the operator and administrator panels.',
                    'A request moves from pending to approved or rejected, and is completed once stock physically arrives.',
                    'The chef panel is focused on the preparation queue; it does not include stock screens.',
                ],
                'how_to_use' => [
                    'Report a shortage to the operator as soon as you see it, not when you run out mid-ticket.',
                    'Say the ingredient and the quantity you need for the rest of the shift so the request is useful.',
                    'Tell the floor immediately when a dish becomes unmakeable, so guests are told before they order.',
                ],
                'how_to_configure' => [
                    'Ingredients, thresholds and stock movements are administrator responsibilities.',
                ],
                'conditions' => [
                    ['if' => 'An ingredient runs out mid-service', 'then' => 'Tell the operator and the floor immediately — dishes depending on it cannot be made.'],
                    ['if' => 'A refill is approved but not completed', 'then' => 'The stock is agreed but has not reached the kitchen yet.'],
                    ['if' => 'You substitute an ingredient silently', 'then' => 'Guests get something they did not order and stock records become wrong.'],
                ],
                'examples' => [
                    ['title' => 'Bread running out', 'body' => 'Twelve loaves left at 1 pm with a busy afternoon ahead. Tell the operator now; they raise the refill and it is delivered before the rush.'],
                ],
                'notes' => [
                    'Never substitute without telling the floor. A guest with an allergy has to know what actually went into the dish.',
                ],
            ],
            [
                'slug' => 'split-bar-kitchen',
                'title' => 'Split Orders (Kitchen & Bar)',
                'group' => 'Kitchen',
                'roles' => $chef,
                'tags' => ['split', 'mixed order', 'bar', 'coordination'],
                'overview' => 'When one order contains food and drinks, it becomes two tickets that progress independently — yours and the bar’s.',
                'how_it_works' => [
                    'The order is split by station: food to the kitchen, beverages to the bar.',
                    'Each ticket is accepted, prepared and marked ready separately.',
                    'The floor usually serves a table once both tickets are ready.',
                ],
                'how_to_use' => [
                    'Cook to your own timing; do not wait for the bar.',
                    'Warn the floor early when a dish will be slow so drinks are not left standing.',
                    'Match tickets by table and round number when coordinating with the bar.',
                ],
                'how_to_configure' => [
                    'No configuration. Splitting follows product station routing.',
                ],
                'conditions' => [
                    ['if' => 'Your ticket will be much slower than the bar’s', 'then' => 'Tell the floor so they can decide whether drinks go early.'],
                    ['if' => 'The round is cancelled', 'then' => 'Both tickets are cancelled.'],
                ],
                'examples' => [
                    ['title' => 'Mixed round for Table 12', 'body' => 'Round 2 is two cappuccinos and one sandwich. You get a kitchen ticket with the sandwich; the bar gets a separate ticket with the cappuccinos. Both show Table 12 · Round 2.'],
                ],
                'notes' => [
                    'Same order, same table, same round, two tickets. Table plus round is how you match them.',
                ],
            ],
            [
                'slug' => 'ready-to-serve-all-tickets',
                'title' => 'Ready to Serve: All Tickets',
                'group' => 'Kitchen',
                'roles' => $chef,
                'tags' => ['ready', 'serve', 'coordination', 'pass'],
                'overview' => 'Your ready is a station fact. A table is normally served once every ticket for that round is ready.',
                'how_it_works' => [
                    'Marking ready means the kitchen has finished its part of the round.',
                    'A mixed round is usually served only when the bar ticket is ready too.',
                    'Serving is recorded by the floor, not by the kitchen.',
                ],
                'how_to_use' => [
                    'Mark ready as soon as the dish is plated — do not hold the status waiting for the bar.',
                    'Call the pass so a waiter collects promptly; food standing under a lamp gets worse.',
                ],
                'how_to_configure' => [
                    'No configuration.',
                ],
                'conditions' => [
                    ['if' => 'Your ticket is ready and the bar’s is not', 'then' => 'The floor decides how to serve; your part is complete.'],
                    ['if' => 'Nobody collects a ready dish', 'then' => 'Chase the floor rather than letting it sit.'],
                ],
                'examples' => [
                    ['title' => 'Sandwich waiting on drinks', 'body' => 'You mark the sandwich ready at nine minutes; the bar marks the drinks ready at ten; the waiter serves Round 2 together at ten.'],
                ],
                'notes' => [
                    'Holding your ready status back to "help" the timing only hides the real picture from the floor.',
                ],
            ],
            [
                'slug' => 'chef-boundaries',
                'title' => 'What Chefs Cannot Do',
                'group' => 'Kitchen',
                'roles' => $chef,
                'tags' => ['boundaries', 'permissions', 'limits', 'escalation'],
                'overview' => 'The deliberate limits of the chef role. Your panel is the kitchen queue, and that is intentional.',
                'how_it_works' => [
                    'The chef panel covers the kitchen preparation queue and your notifications.',
                    'Catalog, recipes, orders, payments, dining sessions and reporting are not in your panel.',
                    'You can move your own kitchen tickets only.',
                ],
                'how_to_use' => [
                    'Escalate anything about price, discount, refund or a customer’s account to an operator or administrator.',
                    'Report catalog and recipe problems rather than working around them every shift.',
                    'Report shortages to the operator, who raises the refill request.',
                ],
                'how_to_configure' => [
                    'Boundaries follow the role and are not configurable from your panel.',
                ],
                'conditions' => [
                    ['if' => 'A dish needs cancelling', 'then' => 'The waiter or operator cancels the round, not you.'],
                    ['if' => 'A guest disputes a charge', 'then' => 'Pass it to an operator; you have no access to payments.'],
                    ['if' => 'A recipe is wrong', 'then' => 'Report it to an administrator.'],
                ],
                'examples' => [
                    ['title' => 'Guest wants a dish comped', 'body' => 'Fetch an operator. Do not mark the ticket ready and hand it over without a decision from someone who can authorise it.'],
                ],
                'options' => [
                    ['name' => 'Can do', 'what' => 'Accept, prepare and complete kitchen tickets; read ticket detail, add-ons and notes.', 'why' => 'Everything needed to run the pass.', 'when' => 'Every shift.', 'example' => 'Marking a sandwich ready.'],
                    ['name' => 'Cannot do', 'what' => 'Edit the catalog or recipes, cancel orders or rounds, take or verify payments, raise refill requests, view revenue or customer data.', 'why' => 'Those belong to other roles.', 'when' => 'Escalate.', 'example' => 'Asking the operator to request more bread.'],
                ],
                'notes' => [
                    'These limits keep you out of trouble. Nobody can accuse you of a pricing or payment error you had no access to make.',
                ],
            ],
        ];
    }
}
