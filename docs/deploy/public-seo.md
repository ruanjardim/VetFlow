# Public site and SEO (pending publication)

The public `/` route renders a marketing page without querying clinic data.
The existing dashboard route name remains `dashboard`, now at `/dashboard`,
inside the same `auth`, active-user, and `dashboard.view` permission boundary.
After login, users still go to the named dashboard route. The public page links
to `/login`, which remains the entry point for existing accounts.

The sole canonical/indexable URL is `https://vetflowsys.com.br/`. The static
`public/sitemap.xml` lists only that URL. `public/robots.txt` allows crawling
so that crawlers can read the noindex directive on public authentication forms;
it does not expose private routes in the sitemap. Both `layouts.guest` and
`layouts.admin` carry `noindex, nofollow`. Authenticated pages still enforce
their existing authorization and tenant boundaries. Do not use robots rules as
an authorization mechanism.

The landing page uses verified existing capabilities only. The public sales
channel is `comercial@vetflowsys.com.br`, configured on 2026-09-13 as an alias
of the active Hostinger mailbox `contato@vetflowsys.com.br`. Demo calls to
action open the visitor's email client with a pre-filled subject; there is no
form, WhatsApp number, pricing, rating, or customer count.

The landing page has its own small Vite CSS entry, a preloaded optimized WebP
hero, and no JavaScript. Rebuild assets before publishing. This commit is
prepared locally only; production validation must be repeated after deployment.
