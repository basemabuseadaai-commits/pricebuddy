# PriceBuddy for Egypt

This fork adds Egyptian shops, their search pages and EGP prices to PriceBuddy.

## What's included

| Shop | Product pages | Keyword search |
| --- | --- | --- |
| Amazon EG | Works | Works |
| noon EG | Works (browser scraper) | Works (browser scraper) |
| Carrefour EG | Works (browser scraper) | Works (browser scraper) |
| Jumia EG | Blocked by Cloudflare | Blocked (Draft) |
| IKEA EG, Raneen | Not tested yet | None yet |
| Hyper One, LC Waikiki, DeFacto, American Eagle (browser scraper), Faces | Added, product pages not tested yet | None yet |

Tested from an Egyptian home connection in November 2026.

Not included: Colin's (no Egyptian web store), Breadfast and Rabbit (app only),
Amazon Now (needs a sign-in), noon Minutes (didn't load), talabat mart (catalogue
depends on the delivery area).

"Search via the web" takes results from each shop in turn and stops after 18 priced
results (set in Settings). noon and Carrefour pages are read with a browser, so a
search takes 1 to 3 minutes.

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
