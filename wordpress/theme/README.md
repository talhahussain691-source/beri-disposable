# BERI WordPress theme: setup guide

This theme contains the full BERI website: Home, BERI Cliq, BERI Crush, BERI Cirql, BERI E-liquid,
Authenticate, Wholesale and Support, with all scroll animations, images and videos.

Online preview of the same design: https://talhahussain691-source.github.io/beri-disposable/

Please install it on a **staging (test) site first** and take a full backup before installing it on a live site.

---

## 1. Install the theme

**Option A: from WordPress admin**

1. Go to **Appearance → Themes → Add New → Upload Theme**.
2. Choose `beri-theme.zip` and click **Install Now**.
3. Click **Activate**.

The zip is about 78 MB because it includes the videos. If WordPress says the file is too large, use Option B,
or ask the host to raise the upload limit (`upload_max_filesize` / `post_max_size`).

**Option B: by FTP or the hosting File Manager**

1. Unzip `beri-theme.zip`.
2. Upload the `beri-theme` folder into `wp-content/themes/`.
3. In WordPress, go to **Appearance → Themes** and **Activate** "BERI".

## 2. What happens on activation

The theme sets itself up when it is activated:

- It creates any missing page with these slugs: `home`, `cliq`, `crush`, `cirql`, `e-liquid`, `authenticate`, `wholesale`, `support`.
  Existing pages with those slugs are kept and simply use the new design.
- It sets **Home** as the front page (Settings → Reading).
- If permalinks are "Plain", it switches them to **Post name** (Settings → Permalinks).

Each page picks its design from its slug automatically (`page-cliq.php`, `page-crush.php`, …).
A page with a different slug can use a design by choosing it under **Page → Template** (for example "BERI Authenticate").

The page content in the WordPress editor is not used by these designs; the text and images live in the theme.

## 3. Plugins: Beri Authenticate and Beri Wholesale

Install and activate both plugins (Plugins → Add New → Upload Plugin). The theme connects to them on its own; there
is nothing to edit in the theme.

### Authenticate page (Beri Authenticate)

The plugin answers the theme's `beri_verify_code` filter. The page sends the code to the theme's endpoint, which asks
the plugin and shows one of four result screens: genuine, already checked, not found, or "couldn't check right now".

```
POST /wp-json/beri/v1/verify
Content-Type: application/json
{ "code": "123456789012" }

→ { "status": "genuine", "check_count": 1 }
→ { "status": "already_verified", "check_count": 3, "first_checked": "12 Jan 2026" }
→ { "status": "invalid" }
```

- Codes are 12 digits. Spaces and dashes typed with the code are removed before the check.
- The QR codes on the boxes open `/authenticate?code=123456789012`: the page fills in the code and checks it right away.
- Each visitor can check 20 codes per 10 minutes.
- Codes, the query log and the code import are in **Beri Authenticate** in the admin menu. Its importer needs Redis;
  if the host has no Redis, import the code table (`{prefix}verify_code`) through phpMyAdmin instead.
- Do not put the plugin's `[beri_authenticate]` shortcode on the page; the theme has its own design.

Without the plugin, add `define( 'BERI_VERIFY_TEST_CODES', true );` to `wp-config.php` for test answers:
`100000000001` → genuine, `100000000002` → already checked, anything else → not found. **Remove this line on the live site.**

### Wholesale page (Beri Wholesale)

The inquiry form sends to the plugin, which saves each inquiry and emails it. The list, CSV export, recipient email
and limits are in **Beri Wholesale** in the admin menu (set the recipient email in **Form Settings**).

- The form's business types and products match the plugin's default choices. If you change them in Form Settings,
  the Wholesale page needs the same change.
- The form asks `/wp-json/beri/v1/wholesale-nonce` for a fresh security token before sending, so it keeps working
  when the page is served from a cache.

### Support page

The Contact and Report a counterfeit forms email **support@beridisposable.com** (Reply-To is the visitor; report
photos are attached). To send elsewhere, add to `wp-config.php`:

```php
define( 'BERI_SUPPORT_EMAIL', 'someone@beridisposable.com' );           // both forms
define( 'BERI_COUNTERFEIT_EMAIL', 'authentic@beridisposable.com' );     // counterfeit reports only
```

Each visitor can send 5 messages per 10 minutes.

### Email delivery

Wholesale and Support emails are sent with `wp_mail()`. Use an SMTP plugin (for example WP Mail SMTP with the
Microsoft 365 / Outlook mailer) so they are delivered from the company mailbox and do not land in spam.

### Behind Cloudflare

When the site is behind Cloudflare, the theme reads the visitor's real address (CF-Connecting-IP) for requests that
come from Cloudflare (`inc/real-ip.php`), so the per-visitor limits count each visitor separately.

## 4. After installing: quick check

- Open every page from the top menu: Home, Products (Cliq, Crush, Cirql, E-liquid), Authenticate, Support, Wholesale.
- Scroll the Home page: the hero video should follow the scroll.
- On Authenticate, try a real code (twice: genuine, then already checked) and a wrong code.
- Send a test wholesale inquiry and a test support message, and check that the emails arrive.

## 5. Recommended plugins

- A cache plugin (for example LiteSpeed Cache or WP Rocket). **Exclude `/wp-json/beri/v1/` and `/wp-admin/admin-ajax.php` from caching.**
- An SEO plugin (Yoast or Rank Math) works with this theme; page titles come from the WordPress page titles.
- A backup plugin (for example UpdraftPlus).

## Files

| File | What it is |
| --- | --- |
| `front-page.php`, `page-*.php` | The eight page designs |
| `inc/verify-endpoint.php` | Verify endpoint for the Authenticate page (asks the Beri Authenticate plugin) |
| `inc/verify-bridge.php` | Hook for a different verify plugin (not needed with Beri Authenticate) |
| `inc/wholesale-bridge.php` | Fresh token for the Wholesale form (Beri Wholesale plugin) |
| `inc/support-endpoint.php` | Support page forms: email to support@ |
| `inc/real-ip.php` | Visitor address behind Cloudflare |
| `inc/setup.php` | Creates the pages on activation |
| `functions.php` | Page links, form scripts, theme setup |
| `assets/` | Images, videos and the page script |
| `index.php` | Layout for any other page or post |
