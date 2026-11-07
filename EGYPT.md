# PriceBuddy for Egypt

This fork adds Egyptian shops, their search pages and EGP prices to PriceBuddy.

## What's included

| Shop | Product pages | Keyword search |
| --- | --- | --- |
| Amazon EG | Ready | Ready |
| Jumia EG | Ready | Ready |
| noon EG | Ready (browser scraper) | Draft: check selectors |
| Carrefour EG | Ready (browser scraper) | Draft: check selectors |
| IKEA EG, Raneen | Ready, for later | None yet |

LC Waikiki, Defacto, American Eagle, Colin's, Faces and Hyper One are created
automatically the first time you add one of their product links.

Quick-delivery apps (Amazon Now, noon Minutes, Breadfast, Rabbit, talabat mart)
price by delivery area and are not covered yet.

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
