# BERI WordPress theme: setup guide

This theme contains the full BERI website: Home, BERI Cliq, BERI Crush, BERI Cirql, BERI E-liquid,
Authenticate and Wholesale, with all scroll animations, images and videos.

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

- It creates any missing page with these slugs: `home`, `cliq`, `crush`, `cirql`, `e-liquid`, `authenticate`, `wholesale`.
  Existing pages with those slugs are kept and simply use the new design.
- It sets **Home** as the front page (Settings → Reading).
- If permalinks are "Plain", it switches them to **Post name** (Settings → Permalinks).

Each page picks its design from its slug automatically (`page-cliq.php`, `page-crush.php`, …).
A page with a different slug can use a design by choosing it under **Page → Template** (for example "BERI Authenticate").

The page content in the WordPress editor is not used by these designs; the text and images live in the theme.

## 3. Connect the verify plugin (Authenticate page)

The Authenticate page design is complete: code field, button and four result screens
(genuine, already checked, not found, and "couldn't check right now").

Only one file needs to be edited: **`inc/verify-bridge.php`**.

1. Open `inc/verify-bridge.php`. The comments at the top explain everything.
2. In `beri_verify_bridge( $result, $code )`, call the verify plugin with `$code` and return one of:

   ```php
   array( 'status' => 'genuine', 'product' => 'BERI Crush · Blue Razz Ice', 'batch' => 'B2026-11', 'check_count' => 1 )
   array( 'status' => 'already_verified', 'product' => '...', 'batch' => '...', 'check_count' => 4, 'first_checked' => '12 Jan 2026' )
   array( 'status' => 'invalid' )
   ```

   `product`, `batch`, `check_count` and `first_checked` are optional. Return `null` if the check could not run.

3. Please **don't load the plugin's own form template, CSS or JS** on the Authenticate page; the theme already has the design.

How the page talks to the site:

```
POST /wp-json/beri/v1/verify
Content-Type: application/json
{ "code": "XXXX-XXXX-XXXX" }

→ { "status": "genuine", "product": "...", "batch": "...", "check_count": 1 }
```

The endpoint (`inc/verify-endpoint.php`) cleans the code (upper case, only A–Z, 0–9 and "-") and limits each
visitor to 20 checks per 10 minutes. It does not need any changes.

Alternatively, the plugin itself can answer through the filter instead of editing the bridge file:

```php
add_filter( 'beri_verify_code', function ( $result, $code ) {
	// ...check $code...
	return array( 'status' => 'genuine' );
}, 10, 2 );
```

### Test before connecting

Add this line to `wp-config.php` to get test answers on the Authenticate page:

```php
define( 'BERI_VERIFY_TEST_CODES', true );
```

- `BERI-TEST-0001` → genuine
- `BERI-TEST-0002` → already checked
- anything else → not found

**Remove this line on the live site.**

## 4. After installing: quick check

- Open every page from the top menu: Home, Products (Cliq, Crush, Cirql, E-liquid), Authenticate, Wholesale.
- Scroll the Home page: the hero video should follow the scroll.
- On Authenticate, try a test code and a real code.

## 5. Recommended plugins

- A cache plugin (for example LiteSpeed Cache or WP Rocket). **Exclude `/wp-json/beri/v1/verify` from caching.**
- An SEO plugin (Yoast or Rank Math) works with this theme; page titles come from the WordPress page titles.
- A backup plugin (for example UpdraftPlus).

## Files

| File | What it is |
| --- | --- |
| `front-page.php`, `page-*.php` | The seven page designs |
| `inc/verify-bridge.php` | **Connect the verify plugin here** |
| `inc/verify-endpoint.php` | The verification endpoint used by the Authenticate page |
| `inc/setup.php` | Creates the pages on activation |
| `functions.php` | Page links, verify script, theme setup |
| `assets/` | Images, videos and the page script |
| `index.php` | Layout for any other page or post |
