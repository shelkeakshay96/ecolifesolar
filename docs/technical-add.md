# Revision history

| Version | Date | Author | Summary |
|---|---|---|---|
| 1.0 | August 2026 | Akshay | Flat procedural PHP, Bootstrap 5, shared hosting. **Superseded — do not build from this.** |
| 2.0 | 9 August 2026 | Akshay | Complete replacement. Modular MVC on Magento structural conventions, Tailwind CSS v4, WSL2-native development, deployment deferred. |
| 2.1 | 9 August 2026 | Akshay | Conventions pinned to **Magento 2** explicitly, with Magento 1 patterns named and prohibited (section 1). Admin controllers, blocks and templates for a resource consolidated under a single path, matching `Magento_Cms` (sections 2 and 3). |

This document describes **version 2.1 only**. Version 1.0 is recorded above so that anyone holding an older copy knows it has been withdrawn, not amended.

---

# 0. Purpose and scope

## What this document is

The complete technical design for **Phase 1** of the EcoLifeSolar web application: the architecture, the module layout, the database schema, the security model, and the order in which it gets built. It is written so that a developer with no prior exposure to the project can start at section 10 and build the application without needing to ask a question.

## Who it is for

Akshay, as the sole developer and maintainer. A second reader would be any developer inheriting the codebase.

A separate plain-language document is intended for Piyush and the family, covering what the site does rather than how it is constructed. This is not that document — nothing here has been simplified for a non-technical reader.

## What it deliberately excludes

| Excluded | Where it stands |
|---|---|
| Production deployment | Deferred by decision. Direction of travel and known hazards are recorded in section 13; no server is provisioned during Phase 1. |
| Phase 2 implementation | Quotations, invoices, PDF generation, WhatsApp delivery. Designed far enough that Phase 1 does not obstruct it — see section 6.8 and Appendix A — but not built. |
| Visual comps | No Figma file exists. The design system is specified in section 7 as Tailwind theme tokens and component utilities, which is the actual source of truth. |
| Content authoring | Copy is carried across from the existing static site, with placeholders where the family must supply real details. Flagged in section 10, step 11. |

## How to read it

Sections 2 through 9 are reference material — consult them while building. Section 10 is the running order and is meant to be followed top to bottom. Section 12 is the acceptance checklist; Phase 1 is not finished until every item passes.

---

# 1. Context

Piyush runs a rooftop solar installation business in Satara, Maharashtra with his father and brother Sahil. He has no technical background but prompted Claude into producing a single-page static site, which lives at `Downloads/eco-life-site/`. It is attractive and the copy is good, but the lead form is a dead end: it displays a "Thank you" message and sends nobody's details anywhere. Every enquiry submitted through it has been lost.

That single defect is the reason this project exists. Everything else is improvement; the lead form is repair.

Akshay is rebuilding the site properly. An earlier plan targeted flat procedural PHP with Bootstrap; that has been **replaced**. The application is now a **modular MVC application following Magento 2's structural conventions**, styled with **Tailwind CSS v4**.

## The trade-off, stated plainly

A miniature framework — router, autoloader, module registry, model/resource/collection layer — is far more machinery than a six-page brochure site requires. It is justified here for one reason only: Akshay works in these patterns daily and will be the sole maintainer, so the cost of the abstraction is lower for him than the cost of unfamiliar code.

The direct consequence must not be glossed over: **the family can no longer edit files to change anything.** With the static site, a phone number could be changed by opening `index.html`. That option disappears. Contact details, gallery photographs, and business copy must therefore be database-backed and editable from the admin panel.

That is why `core_config` and `gallery_item` exist in the schema. They are not optional niceties; they are what makes the architecture survivable for the people who own the business.

## Decisions locked in

| Decision | Choice |
|---|---|
| Architecture | Modular MVC, **Magento 2** conventions — explicitly not Magento 1 |
| Areas | `frontend` and `adminhtml`, strictly separated |
| CSS | Tailwind CSS v4 via **standalone binary** — no Node, no npm |
| Visual direction | **Clean modern SaaS** — not the original earthy identity |
| PHP | 8.3, no framework, no Composer, no ORM |
| Database | MySQL 8, PDO, prepared statements |
| JavaScript | Vanilla JS — jQuery dropped, see section 11 |
| Environment | WSL2 native; PHP, MySQL and Tailwind installed via apt or pinned binary |
| Deployment | **Deferred.** DigitalOcean droplet, planned after Phase 1 works locally |

## Explicitly not built

No DI container. No XML layout. No plugins or interceptors. No observers or events. No code generation. No EAV. No cache layer. No theme fallback chain.

These are the parts of Magento that would sink a project this size. Adopting the directory conventions and the model/resource/collection split is cheap; adopting the object system is not.

## No Magento 1 patterns

The conventions borrowed here are **Magento 2's**. Where Magento 1 and Magento 2 diverge, follow Magento 2 without exception. The distinction matters because several Magento 1 habits are still widely documented online and will look plausible to anyone searching for guidance mid-build.

| Concern | Magento 1 — do not do this | Magento 2 — do this |
|---|---|---|
| Module location | `app/code/{core,community,local}/Vendor/Module/` — three code pools | `app/code/Vendor/Module/` — one location |
| Module declaration | `app/etc/modules/Vendor_Module.xml`, a file outside the module | `registration.php` and `etc/module.xml`, both inside the module |
| Class naming | `EcoLife_Lead_Model_Lead` — underscores, no namespace | `EcoLife\Lead\Model\Lead` — PSR-4 namespaces |
| Object creation | `Mage::getModel('lead/lead')` — factory strings resolved at runtime | Constructor injection; here, the `Context` service bag |
| Configuration | One monolithic `etc/config.xml` declaring models, blocks, helpers and routes together | Split per concern: `module.xml`, `routes.xml`, one file per subject |
| Controllers | `IndexController` holding `indexAction()`, `viewAction()`, `saveAction()` | One class per action, each with a single `execute()` |
| Templates | `app/design/frontend/package/theme/template/` — outside the module | `view/{area}/templates/` — inside the module that owns them |
| Static assets | A separate `skin/` document root | `view/{area}/web/` alongside the templates |
| Shared state | `Mage::registry()` and the `Mage::` static god object | Explicit data passing; no global registry, no `Mage::` equivalent |
| Helpers | Mandatory per module, `Mage::helper('lead')` | Discouraged; none are built here |
| Class overrides | `<rewrite>` in `config.xml`, one winner per class | Preferences and plugins — and neither is built here, see above |

The single deliberate departure from Magento 2 is schema management. Magento 2 uses declarative schema: `etc/db_schema.xml` describes the desired table state and the framework diffs it against `information_schema` to generate the migration. That differ is a substantial subsystem, and building it would cost more than the rest of this framework combined. Numbered `.sql` files applied through a ledger table are used instead — see section 6. Note that this is **not** a reversion to Magento 1's `sql/lead_setup/mysql4-install-1.0.0.php`, which keyed scripts to module version numbers and failed routinely; it is the modern convention used by Flyway, Phinx and Laravel, and it matches the spirit of Magento 2's `Setup/Patch/` — discrete, individually tracked, never edited once applied.

---

# 2. Directory structure

```
/var/www/EcoLifeSolar
├── app/
│   ├── bootstrap.php              # constants → autoload → config → error handler → modules
│   ├── autoload.php               # PSR-4 prefix map
│   ├── etc/
│   │   ├── config.php             # module on/off flags (committed)
│   │   ├── env.php                # DB creds, admin front name, SMTP (GITIGNORED)
│   │   └── env.php.sample         # committed template
│   └── code/EcoLife/
│       ├── Core/                  # framework: bootstrap, router, DB, base classes
│       ├── Theme/                 # ALL layouts/templates + Tailwind input.css (zero PHP)
│       ├── Backend/               # admin shell: auth, login, dashboard, admin layout
│       ├── Mail/                  # PHPMailer wrapper + email templates
│       ├── Cms/                   # Home/About/Services/Contact pages (no DB table)
│       ├── Lead/                  # lead capture + admin lead management
│       ├── Calculator/            # savings calculator
│       └── Gallery/               # project photos + admin CRUD
├── bin/
│   ├── ecolife                    # CLI entry point
│   └── tailwind-install.sh        # downloads pinned Tailwind binary
├── lib/internal/PHPMailer/src/    # vendored, no Composer
├── pub/                           # ← THE ONLY WEBROOT
│   ├── index.php
│   ├── css/ecolife.css            # Tailwind output — COMMITTED
│   ├── js/app.js
│   ├── images/
│   └── media/gallery/             # uploads (gitignored)
├── tools/tailwindcss              # standalone binary (~100MB, gitignored)
├── var/
│   ├── log/{system.log,exception.log}
│   ├── session/
│   └── tmp/
├── docs/                          # this document and its build script
└── .gitignore
```

`app/`, `var/`, `lib/`, `bin/` and `tools/` sit **physically outside the webroot**. No `.htaccess` deny rule is load-bearing. This is strictly better than the shared-hosting arrangement it replaces, where application code sat inside the document root and was protected only by configuration that a hosting migration could silently drop.

## Module tree — exemplar: `EcoLife_Lead`

```
app/code/EcoLife/Lead/
├── registration.php
├── etc/
│   ├── module.xml                      # name, setup_version, <sequence>
│   ├── frontend/routes.xml             # frontName: lead
│   └── adminhtml/routes.xml            # frontName: leads
├── Setup/migrations/
│   ├── 001-create-lead-table.sql
│   └── 002-create-lead-status-history.sql
├── Model/
│   ├── Lead.php                        # entity
│   ├── Validator.php
│   └── ResourceModel/
│       ├── Lead.php                    # PDO CRUD
│       └── Lead/Collection.php         # filtered/paginated sets
├── Controller/
│   ├── Form/Post.php                   # frontend AJAX POST
│   └── Adminhtml/
│       └── Lead/{Index,View,Status,Delete,Export}.php
├── Block/
│   ├── Form.php
│   └── Adminhtml/Lead/{Grid,View}.php
└── view/
    ├── frontend/templates/form/lead.phtml
    └── adminhtml/templates/lead/{grid,view}.phtml
```

**One resource, one controller path.** Every admin action for leads sits under `Controller/Adminhtml/Lead/`, including the grid — not split between an `Index/` controller for the listing and a `Lead/` controller for everything else. This is what `Magento_Cms` does with `Controller/Adminhtml/Page/{Index,Edit,Save,Delete}.php`. `Block/` and `view/adminhtml/templates/` mirror the same path, so the file serving a screen is predictable from its URL.

**A module owns both its areas.** Lead's admin grid lives in `EcoLife_Lead`, not in `EcoLife_Backend`. Adding a field then touches one directory instead of two, and disabling the module removes the public form *and* its admin screens together rather than leaving orphaned menu entries. This is what Magento itself does — `Magento_Sales` owns its own `Controller/Adminhtml/`.

---

# 3. Request lifecycle

```
pub/index.php
  → require app/bootstrap.php
       define BP; require autoload.php
       timezone Asia/Kolkata; mb_internal_encoding UTF-8
       Core\Model\Config::init()          # config.php + env.php
       Core\App\ErrorHandler::register()
       Core\Module\ModuleList::load()     # glob registration.php → module.xml → topo sort
  → (new Core\App\Http())->run()
       Request::fromGlobals()
       Area::detect()                     # strips admin front name, sets frontend|adminhtml
       FrontController::match()           # routers in order → AbstractAction
       $action->dispatch()                # CSRF check → execute() → ResultInterface
       $result->renderResult($response)
       $response->send()                  # security headers, then body
```

## Key classes and their single responsibilities

| Class | Job |
|---|---|
| `Core\App\Http` | Orchestrate one request; the only place catching `Throwable` |
| `Core\App\Area` | Detect `frontend` vs `adminhtml`; rewrite path info |
| `Core\App\Context` | Immutable service bag passed to every controller and block |
| `Core\App\FrontController` | Walk routers; enforce the admin-base invariant |
| `Core\App\Router\Standard` | `route/controller/action/k/v…` → controller class |
| `Cms\App\Router\Page` | Pretty single-segment URLs (`/about`) → CMS page controller |
| `Core\Controller\AbstractAction` | Template-method `dispatch()` → `execute()` |
| `Core\View\Result\Page` | Wrap content block in the area layout; write to Response |

**There is no DI container.** `Context` carries the shared services — `getRequest`, `getResponse`, `getArea`, `getConfig`, `getDb`, `getLogger`, `getSession`, `getMessages`, `getFormKey`, `getUrl`, `getTemplateResolver`. Controllers take `Context`; blocks take `Context` plus `array $data`. The PDO connection is the single deliberate global, reached through `Db::instance()`.

**Rule: Core never names a non-Core class.** If Core needs to know about Lead, the design is wrong.

## Autoloading — `app/autoload.php`

```php
$prefixes = [
    'EcoLife\\'              => BP . '/app/code/EcoLife/',
    'PHPMailer\\PHPMailer\\' => BP . '/lib/internal/PHPMailer/src/',
];

spl_autoload_register(static function (string $class) use ($prefixes): void {
    foreach ($prefixes as $prefix => $dir) {
        if (!str_starts_with($class, $prefix)) { continue; }
        $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) { require $file; }
        return;
    }
});
```

Plain PSR-4 lands exactly on the Magento directory layout for free. No map file, no generation step.

## Routing scheme

`/{frontName}/{controller}/{action}/{key}/{value}/…`, with controller and action both defaulting to `index`.

```
/                            → Cms router  → Cms\Controller\Page\View        identifier=home
/about                       → Cms router  → Cms\Controller\Page\View        identifier=about
/calculator                  → Standard    → Calculator\Controller\Index\Index
/calculator/estimate         → Standard    → Calculator\Controller\Estimate\Index   (JSON)
/lead/form/post              → Standard    → Lead\Controller\Form\Post
/admin                       → Standard    → Backend\Controller\Adminhtml\Index\Index
/admin/auth/login            → Standard    → Backend\Controller\Adminhtml\Login\Index
/admin/leads/lead/index      → Standard    → Lead\Controller\Adminhtml\Lead\Index
/admin/leads/lead/view/id/5  → Standard    → Lead\Controller\Adminhtml\Lead\View
```

> Note the plural admin frontName `leads` alongside the singular controller `lead`. `/admin/lead/view/id/5` cannot work under `route/controller/action` — it would parse as frontName `lead`, controller `view`, action `id`. This mirrors Magento's own `admin/sales/order/view/order_id/5`.

Every path segment is validated against `/^[a-z0-9][a-z0-9_-]*$/` before use, which blocks directory traversal and case tricks in one rule. Any router returning `null` raises `NotFoundException`, which renders a 404 through the normal page pipeline — so `/admin/xyz` gets admin chrome automatically, with no special casing.

The admin front name is configurable in `env.php`, defaulting to `admin`, so it can become `/satara-office` later without touching code.

---

# 4. MVC base classes

## `Core\Model\AbstractModel`

Data holder providing `getData/setData/addData/unsetData`, `getId/setId`, `toArray`, and `__call` for `getFoo()/setFoo()` magic, plus `origData` dirty tracking so `save()` writes only the columns that actually changed.

**Every concrete model also declares explicit typed accessors** — `getEmail(): string` — so that the IDE and static analysis keep working. The magic accessors are a convenience for generic code, not a licence to leave the model untyped.

## `Core\Model\ResourceModel\AbstractResource`

```php
protected string $table;
protected string $idFieldName = 'entity_id';
protected array  $fields = [];   // explicit column whitelist — security-critical

public function load(AbstractModel $o, int|string $value, ?string $field = null): void
public function save(AbstractModel $o): void      // INSERT all, or UPDATE dirty only
public function delete(AbstractModel $o): void
```

`$fields` is the whitelist used for every column identifier appearing in INSERT, UPDATE, filters and sorts. Values are always bound; identifiers are always whitelist-checked. It is declared explicitly rather than introspected from the schema precisely because a security control that discovers its own rules at runtime is not a control.

## `Core\Model\ResourceModel\AbstractCollection`

```php
$leads = (new Lead\Collection())
    ->addFieldToFilter('status', 'new')
    ->addFieldToFilter('created_at', ['gteq' => $from, 'lteq' => $to])
    ->addFieldToFilter('name', ['like' => "%{$q}%"])
    ->addFieldToFilter('city', ['in' => ['Satara', 'Karad']])
    ->addOrder('created_at', Collection::SORT_DESC)
    ->setPageSize(25)->setCurPage($page);

foreach ($leads as $lead) { … }
$leads->getSize();              // COUNT(*) with WHERE, without LIMIT
$leads->getLastPageNumber();
```

Supported operators: `eq`, `neq`, `gt`, `gteq`, `lt`, `lteq`, `like`, `nlike`, `in`, `nin`, `null`, `notnull`. Conditions AND together; there is no OR nesting. That is a deliberate simplification — the admin grid never needs it, and supporting it would double the complexity of the condition builder. Loading is lazy, guarded by `$isLoaded`.

## `Core\View\Element\AbstractBlock`

A view model holding the data and logic for one `.phtml` template. Provides `setTemplate('EcoLife_Lead::form/lead.phtml')`, `toHtml()`, `addChild`/`getChildHtml`, `renderChild()`, `getUrl()`, `getStaticUrl()` (which appends `?v=filemtime` for cache busting), `getFormKey()`, and the escaping surface: `escapeHtml`, `escapeHtmlAttr`, `escapeUrl`, `escapeJs`.

**Template resolution is where area separation lands:**

```php
// EcoLife_Cms::page/contact.phtml resolves to, in order:
app/code/EcoLife/Cms/view/{area}/templates/page/contact.phtml
app/code/EcoLife/Cms/view/base/templates/page/contact.phtml
```

A block asks for `EcoLife_Theme::layout/default.phtml` and the resolver picks the correct area directory. No area conditionals appear in PHP.

## Layout without layout XML

`Result\Page` is a slot holder. The layout itself is a `.phtml` living in `EcoLife_Theme`:

```php
protected function execute(): ResultInterface
{
    return $this->resultPage()
        ->setTitle('Contact | EcoLifeSolar')
        ->setMetaDescription('Rooftop solar quotes in Satara.')
        ->setBodyClass('page-contact')
        ->setContent(\EcoLife\Cms\Block\Page::class,
                     'EcoLife_Cms::page/contact.phtml',
                     ['identifier' => 'contact']);
}
```

Cross-module composition is an explicit call in the template, not a configured reference:

```php
<?= $block->renderChild(\EcoLife\Lead\Block\Form::class,
                        'EcoLife_Lead::form/lead.phtml', ['source' => 'contact']) ?>
```

Result types implementing `ResultInterface`: `Page`, `Json`, `Redirect`, `Raw`.

**Do not build a `Registry`.** It exists in Magento only because layout XML gives blocks no constructor path to controller data. Here data flows through `setContent()` and `renderChild()`, so the problem a registry solves does not arise.

---

# 5. Module breakdown

| Module | Sequence | Responsibility |
|---|---|---|
| `EcoLife_Core` | — | Bootstrap, autoload, request/response, routing, area, PDO, base Model/Resource/Collection/Block, session, form key, URL, logger, migrator, CLI. **No `.phtml` at all.** |
| `EcoLife_Theme` | Core | All layouts, `html/{head,header,footer,messages,noroute}.phtml`, admin chrome, grid and pager partials, Tailwind `input.css`. **Zero PHP classes.** |
| `EcoLife_Backend` | Core, Theme | Admin shell: `admin_user` entity, auth session, login/logout, throttling, dashboard, admin menu, `Backend\App\Action\AbstractAction`. |
| `EcoLife_Mail` | Core | PHPMailer wrapper `Mail\Model\Transport`, `.phtml` email templates, `file` transport for local development. |
| `EcoLife_Cms` | Core, Theme | Home, About, Services, Contact. `etc/pages.php` whitelist plus one `Page\View` controller. No database table. |
| `EcoLife_Lead` | Core, Theme, Backend, Mail | Lead entity, resource and collection; frontend form and POST controller; validation; anti-spam; notification email; **and** the admin grid, view, status, delete and export screens. |
| `EcoLife_Calculator` | Core, Theme | Calculator page and JSON estimate endpoint. Tariff and sizing rules in `etc/calculator.php`. |
| `EcoLife_Gallery` | Core, Theme, Backend | `gallery_item` entity, image upload, admin CRUD, frontend grid. |

**Phase 2, designed but not built:** `EcoLife_Pdf`, `EcoLife_Quotation`, `EcoLife_Invoice`.

**Why `Theme` is separate from `Core`.** It contains no PHP, and separating it enforces the rule that Core is framework and is never edited to change how the site looks. It is a *presentation module*, not a Magento theme — build no fallback chain, no `app/design`, no theme inheritance.

**Why `Gallery` is separate from `Cms`.** The family will ask "can we add photos ourselves" within a month of launch. Folding gallery into Cms would give Cms a table, an uploader and an admin grid, destroying its clean no-persistence charter for the sake of saving one directory.

## Module declaration files

```php
// app/code/EcoLife/Lead/registration.php
<?php
\EcoLife\Core\Module\ModuleRegistry::register('EcoLife_Lead', __DIR__);
```

```xml
<!-- app/code/EcoLife/Lead/etc/module.xml -->
<?xml version="1.0"?>
<module name="EcoLife_Lead" setup_version="1.0.0">
    <sequence>
        <module name="EcoLife_Core"/>
        <module name="EcoLife_Theme"/>
        <module name="EcoLife_Backend"/>
        <module name="EcoLife_Mail"/>
    </sequence>
</module>
```

```xml
<!-- etc/frontend/routes.xml -->
<routes><route id="lead" frontName="lead"><module name="EcoLife_Lead"/></route></routes>

<!-- etc/adminhtml/routes.xml -->
<routes><route id="leads" frontName="leads"><module name="EcoLife_Lead"/></route></routes>
```

`ModuleList::load()` globs every `registration.php`, filters by the flags in `app/etc/config.php`, parses each `module.xml`, then performs a depth-first topological sort with cycle detection and an alphabetical tie-break. `<sequence>` expresses load order only; there are no version constraints to resolve.

---

# 6. Database schema

**Charset `utf8mb4`, collation `utf8mb4_unicode_ci`, engine InnoDB throughout.** VARCHAR columns that carry an index are capped at 190 characters for utf8mb4 index-length safety.

Migrations are **per-module, numbered, append-only SQL files** applied through a ledger table. Idempotency comes from the ledger rather than from `IF NOT EXISTS`, which means `bin/ecolife setup:upgrade` is always safe to re-run and a half-applied migration is visible rather than silently skipped.

## 6.1 `setup_migration` — EcoLife_Core

```sql
CREATE TABLE setup_migration (
  module     VARCHAR(64)  NOT NULL,
  migration  VARCHAR(128) NOT NULL,
  applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (module, migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 6.2 `core_config` — EcoLife_Core

Key-value store for everything the family must be able to change without touching files. **Load-bearing**, given that they cannot edit code.

```sql
CREATE TABLE core_config (
  config_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  path        VARCHAR(190) NOT NULL,          -- 'general/contact/phone'
  value       TEXT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (config_id),
  UNIQUE KEY uniq_core_config_path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Seeded paths: `general/contact/phone`, `general/contact/whatsapp`, `general/contact/email`, `general/contact/address`, `general/contact/hours`, `general/business/name`, `general/business/gstin`, `general/social/facebook`, `general/social/instagram`, `lead/notification/recipients`, `lead/notification/enabled`.

## 6.3 `admin_user` — EcoLife_Backend

```sql
CREATE TABLE admin_user (
  user_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username       VARCHAR(50)  NOT NULL,
  email          VARCHAR(190) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,       -- password_hash(PASSWORD_DEFAULT)
  first_name     VARCHAR(80)  NULL,
  last_name      VARCHAR(80)  NULL,
  role           ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  failures_num   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  first_failure  DATETIME NULL,
  lock_expires   DATETIME NULL,
  last_login_at  DATETIME NULL,
  last_login_ip  VARCHAR(45) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uniq_admin_user_username (username),
  UNIQUE KEY uniq_admin_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Rows are created only via `bin/ecolife admin:user:create`. Never seeded, never given a default password.

## 6.4 `lead` — EcoLife_Lead

The core table. It covers all three form variants; variant-specific columns are nullable.

```sql
CREATE TABLE lead (
  lead_id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_type           ENUM('residential','society','commercial') NOT NULL,

  -- Contact (all variants)
  name                VARCHAR(150) NOT NULL,
  email               VARCHAR(190) NULL,
  phone               VARCHAR(15)  NOT NULL,       -- validated ^[6-9]\d{9}$
  city                VARCHAR(100) NOT NULL,
  pincode             VARCHAR(6)   NOT NULL,
  state               VARCHAR(100) NOT NULL DEFAULT 'Maharashtra',

  -- Requirement
  monthly_bill_range  VARCHAR(50)  NULL,           -- whitelisted server-side
  monthly_bill_amount DECIMAL(10,2) NULL,
  roof_type           ENUM('rcc','metal_sheet','tiled','other') NULL,
  roof_area_sqft      INT UNSIGNED NULL,

  -- Housing society only
  society_name        VARCHAR(190) NULL,
  society_designation ENUM('committee','resident','builder','facility_manager') NULL,
  agm_status          ENUM('approved','in_discussion','not_started') NULL,
  total_flats         SMALLINT UNSIGNED NULL,

  -- Commercial only
  company_name        VARCHAR(190) NULL,
  gstin               VARCHAR(15)  NULL,

  message             TEXT NULL,

  -- Pipeline
  status              ENUM('new','contacted','survey_scheduled','survey_done',
                           'quotation_sent','negotiating','converted',
                           'not_interested','junk') NOT NULL DEFAULT 'new',
  priority            ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  assigned_to         INT UNSIGNED NULL,
  admin_notes         TEXT NULL,
  contacted_at        DATETIME NULL,
  next_followup_at    DATETIME NULL,

  -- Phase 2 flags (denormalised for cheap grid badges)
  has_quotation       TINYINT(1) NOT NULL DEFAULT 0,
  has_invoice         TINYINT(1) NOT NULL DEFAULT 0,

  -- Attribution & forensics
  source_page         VARCHAR(50)  NULL,           -- 'home' | 'contact'
  utm_source          VARCHAR(100) NULL,
  utm_medium          VARCHAR(100) NULL,
  utm_campaign        VARCHAR(100) NULL,
  ip_address          VARCHAR(45)  NULL,
  user_agent          VARCHAR(255) NULL,

  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (lead_id),
  KEY idx_lead_status (status),
  KEY idx_lead_created (created_at),
  KEY idx_lead_type (lead_type),
  KEY idx_lead_phone (phone),
  KEY idx_lead_assigned (assigned_to),
  KEY idx_lead_status_created (status, created_at),
  KEY idx_lead_followup (next_followup_at),
  CONSTRAINT fk_lead_assigned_to FOREIGN KEY (assigned_to)
      REFERENCES admin_user (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 6.5 `lead_status_history` — EcoLife_Lead

Every status change is recorded. This answers "who called this customer and when" without guesswork, which matters as soon as more than one person is working the pipeline.

```sql
CREATE TABLE lead_status_history (
  history_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id       INT UNSIGNED NOT NULL,
  admin_user_id INT UNSIGNED NULL,
  from_status   VARCHAR(30) NULL,
  to_status     VARCHAR(30) NOT NULL,
  comment       TEXT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (history_id),
  KEY idx_lsh_lead (lead_id),
  KEY idx_lsh_created (created_at),
  CONSTRAINT fk_lsh_lead FOREIGN KEY (lead_id)
      REFERENCES lead (lead_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_lsh_admin FOREIGN KEY (admin_user_id)
      REFERENCES admin_user (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 6.6 `gallery_item` — EcoLife_Gallery

```sql
CREATE TABLE gallery_item (
  item_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title           VARCHAR(190) NOT NULL,
  description     TEXT NULL,
  image_path      VARCHAR(255) NOT NULL,      -- relative to pub/media/
  thumbnail_path  VARCHAR(255) NULL,
  location        VARCHAR(100) NULL,          -- 'Satara, Maharashtra'
  system_size_kw  DECIMAL(6,2) NULL,
  install_date    DATE NULL,
  category        ENUM('residential','society','commercial','other')
                      NOT NULL DEFAULT 'residential',
  sort_order      INT NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (item_id),
  KEY idx_gallery_active_sort (is_active, sort_order),
  KEY idx_gallery_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 6.7 `admin_activity_log` — EcoLife_Backend

```sql
CREATE TABLE admin_activity_log (
  log_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_user_id INT UNSIGNED NULL,
  entity_type   VARCHAR(50) NOT NULL,         -- 'lead' | 'gallery_item' | 'admin_user'
  entity_id     INT UNSIGNED NULL,
  action        VARCHAR(50) NOT NULL,         -- 'created' | 'updated' | 'deleted' | 'login'
  details       TEXT NULL,                    -- JSON
  ip_address    VARCHAR(45) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  KEY idx_aal_entity (entity_type, entity_id),
  KEY idx_aal_user (admin_user_id),
  KEY idx_aal_created (created_at),
  CONSTRAINT fk_aal_admin FOREIGN KEY (admin_user_id)
      REFERENCES admin_user (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 6.8 Phase 2 tables — designed now, created later

Not built in Phase 1. They are documented here so that Phase 1 choices do not paint the project into a corner. Indian GST invoicing needs a CGST/SGST split for intra-state supply and IGST for inter-state supply, so those columns are modelled explicitly rather than as a single tax amount.

**`quotation`** — `quotation_id`, `lead_id` FK, `quotation_number` UNIQUE (`QT-2026-0001`), `status` ENUM(draft, sent, viewed, accepted, rejected, expired), `system_size_kw`, `panel_brand`, `panel_wattage`, `panel_count`, `inverter_brand`, `inverter_capacity_kw`, `structure_type`, `subtotal`, `discount_amount`, `subsidy_amount`, `taxable_amount`, `gst_rate`, `gst_amount`, `total_amount`, `estimated_monthly_savings`, `estimated_annual_savings`, `payback_years`, `warranty_panel_years`, `warranty_inverter_years`, `warranty_workmanship_years`, `installation_timeline`, `terms`, `valid_until`, `sent_email_at`, `sent_whatsapp_at`, `viewed_at`, `pdf_path`, `created_by` FK, timestamps.

**`quotation_item`** — `item_id`, `quotation_id` FK CASCADE, `sort_order`, `description`, `hsn_code`, `qty`, `unit`, `unit_price`, `line_total`.

**`invoice`** — `invoice_id`, `lead_id` FK, `quotation_id` FK, `invoice_number` UNIQUE (`INV-2026-0001`), `status` ENUM(draft, sent, partially_paid, paid, overdue, cancelled), `invoice_date`, `due_date`, `place_of_supply`, `customer_gstin`, `subtotal`, `discount_amount`, `taxable_amount`, `cgst_rate`, `cgst_amount`, `sgst_rate`, `sgst_amount`, `igst_rate`, `igst_amount`, `total_amount`, `amount_paid`, `notes`, `terms`, `pdf_path`, `created_by` FK, timestamps.

**`invoice_item`** — mirrors `quotation_item`, plus `hsn_code`.

**`invoice_payment`** — `payment_id`, `invoice_id` FK CASCADE, `amount`, `payment_date`, `payment_mode` ENUM(cash, upi, bank_transfer, cheque, card, other), `reference_number`, `notes`, `recorded_by` FK, `created_at`.

---

# 7. Tailwind v4 setup

**Input:** `app/code/EcoLife/Theme/view/base/web/css/input.css`
**Output:** `pub/css/ecolife.css`, committed to the repository.

```css
@import "tailwindcss" source(none);   /* explicit sources only */

/* Paths relative to this file. */
@source "../../../../../../**/view/**/templates/**/*.phtml";
@source "../../../../../../../../pub/js/**/*.js";

@theme {
  /* Clean modern SaaS: slate neutrals + one energetic accent */
  --color-brand-50:  oklch(0.98 0.02 85);
  --color-brand-500: oklch(0.72 0.17 62);   /* solar amber — CTAs */
  --color-brand-600: oklch(0.65 0.17 58);
  --color-accent-600: oklch(0.55 0.13 160); /* emerald — success/eco cues */
  --font-display: "Inter", ui-sans-serif, system-ui, sans-serif;
}

@utility btn-primary { @apply inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-2.5 font-medium text-white transition hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500/50; }
@utility btn-secondary { @apply inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 font-medium text-slate-700 transition hover:bg-slate-50; }
@utility card { @apply rounded-xl border border-slate-200 bg-white p-6 shadow-sm; }
@utility form-input { @apply w-full rounded-lg border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500; }
@utility form-label { @apply block text-sm font-medium text-slate-700 mb-1.5; }
```

The single glob `**/view/**/templates/**/*.phtml` catches every module and both areas, so adding a module requires no Tailwind change. `source(none)` stops Tailwind wandering into `var/`, `lib/` or `tools/` and scanning a hundred megabytes of binary.

```bash
# development
./tools/tailwindcss -i app/code/EcoLife/Theme/view/base/web/css/input.css \
                    -o pub/css/ecolife.css --watch

# before commit
bin/ecolife assets:build        # wraps the same command with --minify
```

**Committed:** `input.css` and `pub/css/ecolife.css`.
**Gitignored:** `tools/tailwindcss`, roughly 100 MB. `bin/tailwind-install.sh` downloads the pinned version; the version and update procedure are documented in the README.

One stylesheet serves both areas. The whole utility surface will land under about 30 KB gzipped, which is smaller than the Bootstrap build it replaces even before considering the JavaScript that came with it.

**Visual direction — clean modern SaaS.** Slate-50 page backgrounds; white cards with `border-slate-200` and a subtle shadow; slate-900 headings; slate-600 body text; the amber accent reserved for primary calls to action only; generous whitespace; Inter throughout. No gradients on everything, no glassmorphism.

The original earthy palette, the Fraunces display font, the streetlight animation, the falling leaves and the custom cursor are all **dropped**. See Appendix B.

---

# 8. Security

| Concern | Mechanism |
|---|---|
| CSRF | Per-session, per-area form key from `bin2hex(random_bytes(32))`. Validated in `AbstractAction::dispatch()` on **every** POST before `execute()` runs, using `hash_equals`. Accepts the POST body field or an `X-Form-Key` header for AJAX. Opt-out is `const CSRF_EXEMPT = true`; nothing in Phase 1 should use it. |
| Admin authentication | Three independent layers — detailed below. |
| SQL injection | PDO with `EMULATE_PREPARES => false`. Values always bound; identifiers always whitelist-checked against the resource model's `$fields`. |
| XSS | All template output passes through `$block->escapeHtml`, `escapeHtmlAttr`, `escapeUrl` or `escapeJs`. Enforced mechanically by `bin/ecolife lint:templates`, which greps every `<?=` and fails unless it is followed by an escaper, `getChildHtml`, `renderChild` or `->toHtml()`. |
| Form spam | Honeypot field, a signed render-timestamp rejecting submissions faster than three seconds, and a cap of three submissions per hour per IP. `ip_address` and `user_agent` are stored on the row. |
| File uploads | Extension whitelist, MIME confirmed via `getimagesize()`, re-encoded through GD to strip any embedded payload, randomised filename, served from a location that never reaches PHP. |
| Errors | `display_errors` off outside development; warnings converted to `ErrorException`; everything logged to `var/log/exception.log` with a request ID. The generic 500 page shows only that ID. |
| Response headers | `Response::send()` is the single place emitting `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` and CSP. |

## Admin authentication — enforced structurally, not by discipline

1. `Backend\App\Action\AbstractAction::dispatch()` is **`final`** and checks `isLoggedIn()` unless `static::ALLOW_GUEST` is true. The default is `false`, so **a new admin controller is protected by writing nothing at all**. Only `Login\Index` and `Login\Post` override it.
2. `FrontController` refuses to dispatch any adminhtml action that does not implement `AuthEnforcedInterface`. Forgetting to extend the base class throws `LogicException` on the first request rather than opening a silent hole.
3. Admin controllers live under `Controller/Adminhtml/`, reachable only through adminhtml route configuration, which is consulted only when the detected area is adminhtml.

Three layers, each of which independently prevents an unauthenticated request from reaching an admin action. Any one of them failing is caught by the others.

Supporting measures: configurable admin front name; `session_regenerate_id(true)` on login; a 900-second idle timeout; session bound to a hash of the User-Agent — **not** the IP address, because Indian mobile networks rotate IPs mid-session and IP binding would log the family out constantly; login throttling through `failures_num` and `lock_expires`, giving a fifteen-minute lock after five failures; and identical timing and messaging for "unknown user" versus "wrong password".

---

# 9. Calculator logic

Ported from the original site, where it currently runs client-side in `js/main.js:205-222`. The rules move into `EcoLife/Calculator/etc/calculator.php` and the computation happens server-side via `/calculator/estimate`, returning JSON — so the numbers can be tuned without touching JavaScript, and so the assumptions are not readable as a pricing sheet by any visitor who opens devtools.

```
roofFactor:  rcc = 1.00 | metal_sheet = 0.92 | tiled = 0.85
offset:      panels = 0.85 | water_heater = 0.25

monthlySaving = bill × offset × roofFactor
annualSaving  = monthlySaving × 12
sizeKw        = max(1, round((bill / 1000) × 0.9 × roofFactor × (heater ? 0.4 : 1)))
systemCost    = heater ? 22000 × roofFactor : sizeKw × 55000 × roofFactor
paybackYears  = systemCost / annualSaving
```

Keep the existing disclaimer copy — these are illustrative estimates, confirmed only after a site visit.

**Open item:** the ₹55,000 per kW figure and the PM Surya Ghar subsidy tiers both need revisiting with Piyush before launch. They are carried over from the static site and nobody has confirmed they are current.

---

# 10. Build order — Phase 1

Each step ends at a verifiable checkpoint. Do not proceed past a failing checkpoint.

## Step 1 — Environment

Install PHP 8.3 with the `pdo_mysql`, `mbstring`, `gd`, `intl` and `curl` extensions; MySQL 8; and the Tailwind standalone binary. Create `/var/www/EcoLifeSolar`, run `git init`, create the `ecolife` database and its application user.

*Checkpoint:* `php -v`, `mysql --version` and `./tools/tailwindcss --help` all succeed.

## Step 2 — Core framework skeleton

`bootstrap.php`, `autoload.php`, `Config`, `Db`, `Logger`, `ErrorHandler`, `ModuleRegistry` and `ModuleList`, `Request`, `Response`, `Area`, `Context`, `Http`, `FrontController`, `Router\Standard`, `AbstractAction` and the result types. Plus `EcoLife_Core` and a trivial `EcoLife_Theme`.

*Checkpoint:* `php -S localhost:8080 -t pub` serves a hardcoded "hello" through the full dispatch chain, and a bad URL returns a real 404.

## Step 3 — Migrator and CLI

`bin/ecolife` with `module:status`, `setup:upgrade`, `db:reset`, `db:seed`, `admin:user:create`, `route:list`, `assets:build` and `lint:templates`. Core migrations for `setup_migration` and `core_config`.

*Checkpoint:* `bin/ecolife setup:upgrade` creates both tables; re-running it is a clean no-op.

## Step 4 — View layer and Theme

`AbstractBlock`, `Template`, `TemplateResolver`, `Result\Page`, and the layout, head, header, footer, messages and noroute templates. Tailwind wired up and watching.

*Checkpoint:* a styled page renders with real Tailwind classes, and editing a `.phtml` triggers a CSS rebuild.

## Step 5 — Model layer

`AbstractModel`, `AbstractResource` and `AbstractCollection` with the full filter API.

*Checkpoint:* a throwaway script saves, loads, filters and paginates rows against a scratch table.

## Step 6 — `EcoLife_Cms`

Page router, `Page\View` controller, `etc/pages.php`, and the Home, About, Services and Contact templates with real content and the clean SaaS design system.

*Checkpoint:* all four pages load at pretty URLs, look right, and are responsive at 375px, 768px and 1440px.

## Step 7 — `EcoLife_Backend`

`admin_user` migration, auth session, login and logout, throttling, admin layout, dashboard shell, `Backend\App\Action\AbstractAction`.

*Checkpoint:* `bin/ecolife admin:user:create` followed by a successful login; hitting any `/admin/...` URL while logged out redirects to the login page; a deliberately-broken controller that skips the base class throws `LogicException`.

## Step 8 — `EcoLife_Lead`

Migrations, model, resource and collection, validator, frontend form block and template, AJAX POST controller, admin grid with filters and pagination, detail view, status update writing to `lead_status_history`, delete, and CSV export.

*Checkpoint:* submit all three form variants, rows land correctly, grid filters work, a status change is recorded in history, and CSV exports.

## Step 9 — `EcoLife_Mail`

PHPMailer wrapper, `file` transport writing to `var/log/mail/` for local development, lead-notification template, wired into the Lead POST controller inside try/catch — mail failure must never fail the submission, because the lead is already saved and losing it is the exact failure being fixed.

*Checkpoint:* submitting a form writes a well-formed email file locally.

## Step 10 — `EcoLife_Calculator` and `EcoLife_Gallery`

Calculator page and JSON endpoint; gallery frontend grid and admin CRUD with hardened uploads.

*Checkpoint:* the calculator matches the formulas in section 9, and an uploaded image appears on the frontend gallery.

## Step 11 — Hardening and polish

Run `lint:templates` clean. Confirm CSRF on every POST. Verify the spam controls. Check that `core_config` values actually drive the header and footer contact details. Test at all three breakpoints. Replace placeholder copy with the family's real details.

*Checkpoint:* every Phase 1 verification item in section 12 passes.

---

# 11. Local environment — WSL2

```bash
sudo apt install php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-intl php8.3-curl mysql-server
sudo mkdir -p /var/www/EcoLifeSolar && sudo chown -R $USER:$USER /var/www/EcoLifeSolar
bash bin/tailwind-install.sh          # pinned Tailwind standalone binary
php -S localhost:8080 -t pub          # PHP built-in dev server
```

**Stay on ext4.** Do not develop from `/mnt/c`. Two things break there: inotify does not fire for Windows-side edits, so Tailwind's `--watch` silently stops rebuilding and you debug stale CSS for an hour; and PHP's `stat()` calls across the 9p mount make every request several times slower. The original static site stays where it is, on the Windows side, as a content reference only.

**jQuery is dropped.** These pages need perhaps sixty lines of JavaScript — mobile navigation, the calculator, and the AJAX form submit. `fetch` and `querySelectorAll` cover all of it. Dropping jQuery removes a third-party origin from the CSP and one more dependency to patch. If it has to come back, pin the version and add an SRI `integrity` hash.

---

# 12. Verification — end of Phase 1

## Framework

- `bin/ecolife route:list` shows every expected route in both areas.
- `bin/ecolife setup:upgrade` is a no-op against a current database.
- `bin/ecolife db:reset` rebuilds from empty and the site still works.
- An unknown URL returns 404 with the correct chrome in both areas.

## Lead capture — the reason the site exists

- All three variants submit successfully and land with correct column mapping. Verify that variant-specific columns are populated and that irrelevant ones are `NULL`.
- Server-side validation rejects a bad phone number (`^[6-9]\d{9}$`), a bad pincode, and missing required fields, with inline errors.
- A tampered `lead_type` or `monthly_bill_range` posted via devtools is rejected by the server-side whitelist.
- Honeypot filled: silently accepted, nothing written.
- Submitted in under three seconds: rejected.
- A fourth submission within an hour from one IP: rejected.
- CSRF: replaying a POST with a stale form key is rejected.

## Admin

- Logged-out access to any `/admin/*` URL redirects to login.
- Five failed logins lock the account for fifteen minutes.
- Grid filters — status, type, date range, search — and pagination all work.
- A status change writes a `lead_status_history` row recording the acting user.
- CSV export opens cleanly in a spreadsheet.
- `lint:templates` passes with zero findings.

## Presentation

- Every page renders correctly at 375px, 768px and 1440px.
- Header and footer contact details come from `core_config`, not hardcoded. Change a value in the database and confirm the page updates.
- `pub/css/ecolife.css` is minified and committed.

---

# 13. Deferred — production deployment

**Not in scope for Phase 1.** The planned target is a **DigitalOcean droplet**: the $6/month tier, 1 GB RAM, in the Bangalore (BLR1) region for latency to Maharashtra visitors.

The following are recorded now only so that Phase 1 does not make them harder later.

- Nginx `root` set to `pub/`, with `try_files $uri /index.php$is_args$args`.
- `app/etc/env.php` created on the server, never committed.
- **Email deliverability is the biggest single risk.** A fresh droplet IP has zero sending reputation and mail will land in spam. An external SMTP relay — Brevo's free tier, Gmail, or SES — plus SPF and DKIM records is mandatory, not optional. This is precisely why `EcoLife_Mail` abstracts the transport from day one rather than calling `mail()` directly.
- Real cost including 18% GST and card forex is roughly ₹770 per month, about ₹9,250 a year — not the ₹530 headline figure.
- DigitalOcean bills in USD only, accepts no UPI, and issues no GST invoice, so the business cannot reclaim that GST. Worth revisiting an Indian INR/UPI provider at deployment time.
- Backups: provider snapshots **plus** a nightly `mysqldump` stored off the box. A snapshot of a corrupted database restores a corrupted database.

---

# Appendix A — Phase 2 preview

Phase 2 delivers quotations and invoices to customers over **email and WhatsApp**. It is not built during Phase 1, but Phase 1 is shaped so that it slots in without rework.

## Modules

| Module | Responsibility |
|---|---|
| `EcoLife_Pdf` | PDF rendering from `.phtml` templates. Vendored library, no Composer, consistent with `EcoLife_Mail`. |
| `EcoLife_Quotation` | Quotation entity and line items, numbering (`QT-2026-0001`), admin build screen, PDF output, email and WhatsApp dispatch, status tracking through to accepted or rejected. |
| `EcoLife_Invoice` | Invoice entity and line items, GST treatment, payment recording, outstanding-balance tracking. |

## What Phase 1 already does for it

- `lead.has_quotation` and `lead.has_invoice` exist as denormalised flags so the admin grid can show badges without joining.
- The `lead` status enum already contains `quotation_sent` and `negotiating`.
- `EcoLife_Mail` abstracts transport, so adding a second channel is a new transport rather than a rewrite.
- `lead.gstin` and `lead.company_name` are captured at enquiry time, which is when the customer is willing to type them.
- Section 6.8 models the CGST/SGST/IGST split explicitly. Retrofitting a tax split onto a single `tax_amount` column after invoices exist is painful; doing it now costs nothing.

## Reference input

`Downloads/Quote_Solar_EcoLife.pdf` — a real quotation the business has already issued. It is the closest thing to a specification for what `EcoLife_Quotation` must produce, and should be read carefully before that module is designed in detail: it establishes the line-item structure, the warranty terms, and the presentation the family already considers correct.

## Open questions for Phase 2

- WhatsApp delivery route: Business API through a provider such as Twilio or Gupshup, versus a simple `wa.me` link that opens the customer's chat with the PDF attached manually. The second is free and immediate; the first is automated and auditable. Decide with Piyush based on volume.
- Whether invoice numbering must satisfy a chartered accountant's requirements for the financial year — likely yes, and likely a per-FY sequence reset.

---

# Appendix B — Source site inventory

The existing site is `Downloads/eco-life-site/`, version 3.1 per its README: `index.html` (20 KB, single page), `css/style.css`, `js/main.js`, and `images/` containing `logo.png`, `panels.jpg` and `panels2.jpg`.

## What it contains, and what happens to it

| Element | Disposition |
|---|---|
| Hero with inline lead form | **Rebuilt.** Same position and intent; the form now actually persists and notifies. |
| Four solution cards — rooftop panels, solar water heaters, battery storage, electrical work | **Carried over**, becoming the Services page plus a home-page summary. |
| "Built for Maharashtra roofs and Maharashtra weather" trust strip | **Carried over.** Feature-based rather than numeric; the README notes real statistics should replace it if the family has any. |
| Four-step process — free site visit, custom design, installation, savings begin | **Carried over** verbatim. |
| Savings calculator | **Carried over**, moved server-side. See section 9. |
| Gallery with lightbox | **Carried over**, now database-backed and editable by the family. See `gallery_item`. |
| Streetlight scene with scroll-triggered light-up and sprouting leaves | **Dropped.** |
| Falling leaves animation | **Dropped.** |
| Custom cursor — dot plus trailing ring | **Dropped.** |
| Earthy palette, Fraunces display font | **Dropped.** Replaced by the clean SaaS direction in section 7. |
| jQuery | **Dropped.** See section 11. |
| `[add number]` and `[add email]` footer placeholders | **Fixed** — now `core_config` values, editable from the admin panel. |

## The defect that justifies the rebuild

The README states it plainly:

> The lead form currently shows a front-end "Thank you" message only — wire it to Formspree, EmailJS, or your CRM before launch

It was never wired. Every enquiry the site has received has been silently discarded. `EcoLife_Lead` is the answer to that, and it is why step 8 is the step that must not be compromised on.
