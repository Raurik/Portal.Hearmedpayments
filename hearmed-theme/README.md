# HearMed Portal Theme

Minimal WordPress theme for the HearMed Portal system.

## What This Theme Does

- ✅ Satisfies WordPress theme requirements (header.php, footer.php, style.css, functions.php)
- ✅ Eliminates deprecation warnings about missing theme files
- ✅ Works seamlessly with **Elementor** (page builder)
- ✅ Works seamlessly with **HearMed Portal Plugin** (business logic)
- ✅ No interference with plugin functionality or CSS

## Installation

### Option 1: Via SFTP (SiteGround)

1. Download the `/hearmed-theme/` folder from this repository
2. Connect to SiteGround via SFTP (port 18765, user: u2157-eobi8upkunha)
3. Navigate to: `/www/portal.hearmedpayments.net/public_html/wp-content/themes/`
4. Upload the `hearmed-theme` folder
5. Go to **WordPress Admin → Appearance → Themes**
6. Activate **HearMed Portal** theme

### Option 2: Via Git (If you have SSH access)

```bash
ssh -p 18765 u2157-eobi8upkunha@ssh.hearmedpayments.net
cd ~/www/portal.hearmedpayments.net/public_html/wp-content/themes/

# Either clone directly or copy manually
git clone https://github.com/Raurik/Portal.Hearmedpayments.git
cp Portal.Hearmedpayments/hearmed-theme ./
```

Then activate in WordPress Admin.

## File Structure

```
hearmed-theme/
├── header.php          # Minimal header (WordPress requirement)
├── footer.php          # Minimal footer (WordPress requirement)
├── style.css           # Theme meta + minimal styles
├── functions.php       # Theme setup and enqueues
└── README.md          # This file
```

## What NOT to do

- ❌ Don't edit the HearMed plugin from this theme
- ❌ Don't add custom shortcodes here (use the plugin)
- ❌ Don't override plugin CSS from here (use `#hm-app` scoping in plugin CSS)
- ❌ Don't add navigation menus (Elementor handles this)

## Troubleshooting

**Still seeing deprecation warnings?**
- Clear WordPress cache (if using caching plugin)
- Hard refresh browser (Ctrl+Shift+R)
- Verify theme is activated in Admin → Appearance → Themes

**Theme styles not loading?**
- Check that `style.css` is in the theme folder
- Verify file permissions (644)
- Check WordPress error log at `/wp-content/debug.log`

**Elementor not working?**
- This theme is designed to work WITH Elementor, not replace it
- Ensure Elementor plugin is installed and activated
- Verify Elementor license is valid

## Version

Current Version: 5.0.0 (aligns with HearMed Portal Plugin v5.0.0)

Last Updated: February 23, 2026
