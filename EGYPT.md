# PriceBuddy for Egypt

This fork adds Egyptian shops, their search pages and EGP prices to PriceBuddy.

## What's included

| Shop | Product pages | Keyword search |
| --- | --- | --- |
| Amazon EG | Works | Works |
| noon EG | Works (browser scraper) | Works (browser scraper) |
| Carrefour EG | Works (browser scraper) | Works (browser scraper) |
| Jumia EG | Works as an identified bot (`BOT_CONTACT` in `.env`, see `config/scraper_bot.php`); selectors | Doesn't answer bots (Draft) |
| talabat mart (Mivida) | Works (Schema.org) | No web search: reads listed category pages and filters by the search words |
| noon Minutes | Works (browser scraper; default Cairo area) | Works (browser scraper) |
| DeFacto, Faces | Added, not tested yet | Works (DeFacto titles are Arabic) |
| LC Waikiki, American Eagle, IKEA EG, Raneen | Added, not tested yet | Works (browser scraper) |
| Hyper One | Works (selectors; browser scraper, waits 8 s) | Works (browser scraper, waits 8 s for products) |
| adidas EG | Works (browser scraper) | Works (browser scraper) |

Tested from an Egyptian home connection in November 2026.

Not included: Colin's (no Egyptian web store), Breadfast and Rabbit (app only),
Amazon Now (needs a sign-in), noon Minutes (didn't load), talabat mart (catalogue
depends on the delivery area).

"Search via the web" takes results from each shop in turn and stops after 18 priced
results (set in Settings). noon and Carrefour pages are read with a browser, so a
search takes 1 to 3 minutes.

## Saved searches

**Saved searches** (in the menu) track a search instead of fixed links. Type what you
would put in a shop's search bar, add brands if you want, and optional title words
(`granite, ceramic` means either) and price limits. Each brand becomes one product on the
dashboard, grouped under a tag with the search's name. Every run (default every 2 hours)
searches each shop again, adds the cheapest matching results to the product, and drops
results that haven't shown up for 3 days.

Brand filters use each shop's own filter: Amazon by brand id (looked up on the results
page), noon by the brand in the address, Carrefour by its exact brand name. A shop that
doesn't carry the brand returns nothing for it.

**Shop section** limits a search to one section where a shop has it (noon: men's or
women's shoes and sneakers). Those shops skip "Title must include", because their titles
are often just model names ("Court Vision Lo"). Other shops are searched as usual.

## Accounts and security

- **Only this PC** can open the app (`127.0.0.1:8080`), and the scraper's port is closed.
  For phone access, use a private link such as Tailscale rather than opening the port.
- **Default sign-in details must be changed.** An account still using `admin@example.com`
  or a password like `admin` is sent to **My profile** until both are changed.
- **Passwords:** at least 10 characters with letters and numbers. Changing your email or
  password needs your current password.
- **Forgot password** on the login page sends a reset link (needs email set up in Settings).
- **Sign-up** is off. Set `ALLOW_REGISTRATION=true` in `.env` and recreate the app to show a
  "Sign up" link. New accounts are normal users with their own copy of the shop searches;
  an admin can also create accounts under Users.
- Each account only sees its own products and saved searches.

## Set up on Windows

1. Install [Docker Desktop](https://www.docker.com/products/docker-desktop/) and open it.
   Wait until it shows **Engine running**.
2. On GitHub, open this repository, switch to the `egypt-stores` branch (or `main` once
   merged), press **Code**, then **Download ZIP**. Unzip it, for example to `C:\pricebuddy`.
3. Open **PowerShell** in that folder and run:

   ```powershell
   New-Item .env -ItemType File -Force
   docker compose up -d
   ```

   The first start downloads about 1 to 2 GB and can take several minutes.
4. Load the Egyptian shops and search pages:

   ```powershell
   docker compose exec app php artisan buddy:create-stores egypt
   docker compose exec app php artisan buddy:create-product-sources egypt
   ```

5. Open <http://localhost:8080> and sign in with `admin@example.com` / `admin`.
   Change the password under your profile.
6. In **Settings**, check that the default currency is **EGP**.

## Adding items to watch

- **By search:** use the product search, type the item (for example `crocs kids clog`),
  and add the results you want to track.
- **By link:** paste a product page link from any shop.

PriceBuddy checks prices on a schedule, keeps the history, and shows a deal score once it
has about 14 days of prices.

## Updating the shop files

After changing `database/seeders/Stores/egypt.php` or
`database/seeders/ProductSources/egypt.php`, run the two commands in step 4 again with
`--update` at the end.
