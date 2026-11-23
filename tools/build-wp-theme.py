#!/usr/bin/env python3
"""Builds the BERI WordPress theme from the published pages.

Usage (from the repository root, after tools/build-site.pl):
    python3 tools/build-wp-theme.py dist

Writes dist/beri-theme/ and dist/beri-theme.zip. The theme's own PHP files live in wordpress/theme/;
this script adds one template per page, the page scripts and every asset the pages use.
"""
import os
import re
import shutil
import sys
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Published page -> (theme template, Template Name shown in the page editor)
PAGES = [
    ('index.html', 'front-page.php', None),
    ('cliq.html', 'page-cliq.php', 'BERI Cliq'),
    ('crush.html', 'page-crush.php', 'BERI Crush'),
    ('cirql.html', 'page-cirql.php', 'BERI Cirql'),
    ('eliquid.html', 'page-e-liquid.php', 'BERI E-liquid'),
    ('authenticate.html', 'page-authenticate.php', 'BERI Authenticate'),
    ('wholesale.html', 'page-wholesale.php', 'BERI Wholesale'),
    ('support.html', 'page-support.php', 'BERI Support'),
]

THEME_URL = '<?php echo esc_url( $beri ); ?>'


def convert(html, template_name):
    if '<?' in html:
        raise SystemExit('unexpected "<?" in page; it would be read as PHP')
    head = '<?php\n'
    if template_name:
        head += '/**\n * Template Name: %s\n */\n' % template_name
    head += "defined( 'ABSPATH' ) || exit;\n$beri = get_template_directory_uri();\n?>"

    # Asset paths become absolute theme URLs, so they work on every page URL (/cliq/, /crush/...).
    html, n = re.subn(r'(?<![\w/.-])assets/(blob|js)/', THEME_URL + r'/assets/\1/', html)
    if n == 0:
        raise SystemExit('no asset paths found')

    # WordPress hooks: language, title (SEO plugins), head/footer scripts.
    html = html.replace('<html lang="en">', '<html <?php language_attributes(); ?>>', 1)
    html = re.sub(r'<title>.*?</title>\n?', '', html, count=1, flags=re.S)
    html = html.replace('</head>', '<?php wp_head(); ?>\n</head>', 1)
    html = html.replace('<body>', '<body <?php body_class(); ?>>\n<?php wp_body_open(); ?>', 1)
    html = html.replace('</body>', '<?php wp_footer(); ?>\n</body>', 1)
    for hook in ('wp_head()', 'wp_footer()', 'body_class()'):
        if hook not in html:
            raise SystemExit('could not place ' + hook)
    return head + html


def main():
    out_dir = sys.argv[1] if len(sys.argv) > 1 else 'dist'
    theme = os.path.join(out_dir, 'beri-theme')
    if os.path.exists(theme):
        shutil.rmtree(theme)
    shutil.copytree(os.path.join(ROOT, 'wordpress', 'theme'), theme)

    used = set()
    for page, template, name in PAGES:
        with open(os.path.join(ROOT, page), encoding='utf-8') as f:
            html = f.read()
        used.update(re.findall(r'assets/blob/([0-9a-f]{32}\.\w+)', html))
        with open(os.path.join(theme, template), 'w', encoding='utf-8') as f:
            f.write(convert(html, name))
        print(page, '->', template)

    os.makedirs(os.path.join(theme, 'assets', 'blob'))
    shutil.copytree(os.path.join(ROOT, 'assets', 'js'), os.path.join(theme, 'assets', 'js'))
    for name in sorted(used):
        shutil.copy2(os.path.join(ROOT, 'assets', 'blob', name), os.path.join(theme, 'assets', 'blob', name))
    print(len(used), 'assets')

    zip_path = os.path.join(out_dir, 'beri-theme.zip')
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as z:
        for base, _, files in os.walk(theme):
            for fn in sorted(files):
                full = os.path.join(base, fn)
                arc = os.path.relpath(full, out_dir)
                # Videos and images are already compressed; store them as they are.
                kind = zipfile.ZIP_STORED if fn.endswith(('.mp4', '.webp', '.jpg', '.png')) else zipfile.ZIP_DEFLATED
                z.write(full, arc, compress_type=kind)
    print('zip', zip_path, round(os.path.getsize(zip_path) / 1048576, 1), 'MB')


if __name__ == '__main__':
    main()
