# Role-based file structure cleanup

The [subsequent unused-file cleanup](cleanup.md) removed the retained legacy
copies and prototypes described in this original refactor record. The role
folders and active shared navigation remain the current structure.

This is a structural refactor. Existing URLs, route names, methods, middleware,
page markup, links, form fields, icons, labels, and interaction code are retained.

## Structure

```text
resources/
  views/
    buyer/                 marketplace pages, layouts, partials, legacy templates
    seller/                Seller Center, application, auth, layouts, legacy templates
    logistics/             logistics workspace, rider, applications, tracking, email
    admin/                 administration pages, layouts, partials
    shared/
      auth/                unified login, registration, verification, account selection
      components/          existing anonymous Blade components, same <x-...> names
      emails/              shared account decisions
      layouts/             common authentication shells
      navigation/
        sidebar.blade.php  all role sidebars, separated into role sections
        navbar.blade.php   all role navbars, separated into role sections
      partials/            address selectors used by buyers and sellers
  css/shared/app.css       Vite source entry
public/assets/
  buyer/{css,js}/          marketplace and page-specific assets
  seller/{css,js}/         Seller Center and application assets
  logistics/{css,js}/      logistics workspace and page-specific assets
  admin/{css,js}/          administration and page-specific assets
  shared/{css,js,images}/  common styles, behavior, logos, and address helpers
routes/
  buyer/                  marketplace web/API, landing, buyer chat
  seller/                 seller application and Seller Center web routes
  logistics/              existing logistics web/API/auth/console routes
  admin/                  admin web/API/auth routes
  shared/                 unified auth, shared API auth/user, storage fallback
  web.php                 ordered web route includes
  api.php                 ordered API route includes
app/Http/Controllers/
  Buyer/{Api,Web}/        buyer web and API handlers
  Seller/                 existing seller handlers and moved seller entry handlers
  Logistics/              existing logistics handlers
  Admin/                  existing administration handlers
  Shared/                 common authentication and catalog resource handlers
```

Laravel's domain models, migrations, services, support utilities, configuration,
and tests retain their framework directories. These application layers are shared
across role boundaries; moving their public namespaces is outside the presentation
and route organization needed here. Framework entry points (`public/index.php`,
favicons, `routes/web.php`, `routes/api.php`) retain their standard locations.
The two standalone landing HTML files retain their existing URLs and use external
assets too.

## Moves and shared-file decisions

| Previous location | New location |
| --- | --- |
| Root buyer views, including `help.blade.php` | `resources/views/buyer/` |
| Root `chat.blade.php` | `buyer/chat-thread.blade.php`, preserving the separate existing `buyer/chat.blade.php` |
| `resources/views/shop/` | `resources/views/buyer/legacy/` |
| `resources/views/seller-full/seller/` | `resources/views/seller/legacy/pages/` |
| Remaining `resources/views/seller-full/` | `resources/views/seller/legacy/` |
| `layouts/website.blade.php` | `buyer/layouts/website.blade.php` |
| `layouts/seller.blade.php` | `seller/layouts/app.blade.php` |
| Root `sell.blade.php` | `seller/apply.blade.php` |
| `auth/seller-login.blade.php` | `seller/auth/login.blade.php` |
| Other root `auth/` and common auth layouts | `shared/auth/`, `shared/layouts/` |
| Root `components/`, `emails/` | `shared/components/`, `shared/emails/` |
| `partials/psgc-address-fields.blade.php` | `shared/partials/psgc-address-fields.blade.php` |
| Buyer pager, support chat, address field views | `buyer/partials/`, `buyer/profile/` |
| Seller photo editor | `seller/partials/photo-editor.blade.php`, seller CSS/JS |
| `public/css/`, `public/js/`, `public/frontend/` | `public/assets/{role}/css/` and `js/` |
| Shared logos from `public/images/` | `public/assets/shared/images/` |
| `resources/css/app.css` | `resources/css/shared/app.css` |
| Buyer controllers at the root and in `Web/`, `Api/` | `app/Http/Controllers/Buyer/`, `Buyer/Web/`, `Buyer/Api/` |
| Seller application and older seller entry controllers | `app/Http/Controllers/Seller/` |
| Unified auth, password reset, common catalog resource controllers | `app/Http/Controllers/Shared/` |

Older templates are retained under their owning roles instead of being deleted.
The seller application belongs with seller onboarding even though buyers can open
it. Unified account selection, login, registration, password reset, generic Blade
components, address initialization, logos, and styles used by multiple roles belong
to shared. Logistics sidebar CSS stays under logistics even though its markup is
in the shared navigation file. Shared account selection also uses `shared/css/shop.css`.

The [machine-readable inventory](file-structure-manifest.json) lists every moved
view, controller, and asset, every extracted style/script block and destination,
and every merged navigation variant.

## Navigation and external assets

Both navigation files have clearly labeled buyer, seller, logistics, and admin
sections. The navbar also includes the common authentication header. Layouts pass
`navigationRole` and `navigationVariant`, and Blade includes inherit the existing
page data. Variants preserve older layouts without changing their content.

The buyer layout prepares its navbar data in the parent scope because the sidebar
also needs counts and buyer details. The seller layout composer points to its new
layout name. Shared components are registered through `Blade::anonymousComponentPath`
so callers continue to use their existing component names.

All 141 original inline style/script blocks were replaced with external links.
Assets remain at their original positions in the document, retaining CSS cascade,
classic-script execution order, Alpine registration, and existing deferred loading.
Repeated identical assets are reused. Server values are escaped JSON in the
corresponding script element's `data-config` attribute; the external script reads
them synchronously. Multiple address initializers keep separate configuration.
The dynamic store color uses the same value through a CSS custom property.
PDF templates link CSS with `public_path()` so Dompdf reads local files.
Existing inline style attributes, event attributes, and Alpine markup are retained.

Route files were split without changing registration order. Controller namespaces,
view calls, mail view names, Blade includes/extends, asset paths, cache-version
`filemtime` paths, component registration, composer registration, tests, and Vite
references were updated. Legacy logistics profile includes now point to the existing
logistics profile partials. Legacy starter templates reference the actual Vite CSS
entry instead of an absent JavaScript entry. Tailwind 3 and Autoprefixer were added
as development dependencies because the existing PostCSS configuration already
required them; the original package manifest omitted both.

## Verification

- All 415 route definitions retain their URI, name, method, ordering and middleware;
  controller actions point to their moved classes.
- 117 GET requests against existing records and role accounts: 100 successful
  responses and 17 existing redirects. Database work was wrapped in a transaction
  and rolled back. Available role lists, detail/create/edit screens, public auth,
  logistics applications, print pages and both logistics PDF formats were checked.
- Links, form actions, field names and required/disabled state match the original
  rendered output on 91 comparable responses.
- Chromium compared 89 HTML pages at 1440px before and after: matching sidebar
  links, labels, icons, classes, navbar links and measured computed layout styles;
  no new JavaScript console errors or missing local CSS/JS.
- Six before/after comparisons at 390px cover buyer shop/help, seller, admin,
  logistics dashboards and login: matching navigation and measured layout styles.
- `ViewStructureTest`, `BuyerWebShoppingTest`, `CommerceRegressionTest`: 45 passed,
  1,248 assertions. The structural tests cover every template's owner, literal
  includes, local asset links, Vite entries, component aliases and address data.
- All Blade templates compile, their generated PHP passes syntax checks, all
  application/route PHP passes syntax checks, and all external JavaScript passes
  `node --check`.
- `npm run build` passes with the moved CSS entry.

The full pre-existing suite cannot run successfully with the supplied database
setup: the default SQLite configuration has no schema (366 tests failed before
the refactor), while the MySQL test configuration denies the configured user
access to `invoiz_ecommerce_test`. No database credentials or grants were changed.
Pages without existing records and side-effecting GET handlers were not exercised;
their templates still received compilation and static dependency checks.

Existing browser issues are preserved and were observed before the refactor:
missing stored/product images on some pages, null `editing` bindings on seller
vouchers, and an Alpine expression syntax error on the public logistics center
application. They are outside this structural cleanup; this change introduces
no additional errors in the browser comparison.
