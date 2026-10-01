# WireSettings

A modern, streamlined ProcessWire 3.x module for managing global website settings.

**WireSettings** replaces cumbersome workarounds (like creating dummy settings pages in the frontend page tree and manual `ready.php` hacks) with a clean, native solution:
- **No Page Tree Clutter:** The settings data page is safely encapsulated inside the hidden admin structure. The public frontend page tree stays 100% clean.
- **Top-Level Admin Menu Item:** Creates a prominent, dedicated "Settings" entry in the main ProcessWire admin navigation bar.
- **Native ProcessWire Fields & Tabs:** Add any fields you need (images, repeaters, text fields, `FieldsetTabOpen` tabs like *Grundlagen*, *Backend*, *SEO*) via **Setup > Templates > settings**.
- **Global `$settings` Variable:** Access your settings anywhere in frontend templates via `$settings->fieldname` without writing `$settings = $pages->get(...)`.
- **Intelligent Installation:** Automatically detects if a `settings` template already exists, preserves all existing fields, and seamlessly migrates any existing frontend settings page.

---

## Features

- 🚀 **Zero Frontend Clutter:** No public URL, no accidental page visits, and no dummy pages in your root page tree.
- 🌐 **Global API Variable:** Directly use `$settings->company`, `$settings->logo->url`, `$settings->email`, etc., in any template file (`_main.php`, `home.php`, etc.).
- 🎨 **Native Admin Experience:** Full UIkit 3 styling, native ProcessWire multi-language tabs, image croppers, and drag & drop file uploads.
- 🛡️ **Clean & Safe Editor:** Automatically hides dangerous system tabs (*Settings/Delete/Children*) on the settings page, so clients and editors cannot accidentally delete or rename it.
- ⚡ **Superuser Shortcut:** Handy quick-link button in the editor for Superusers to jump directly to **Setup > Templates > settings** to manage fields.
- 🔄 **Safe Existing Site Migration:** If you already have a `settings` template with fields (and even an existing `/settings/` page), WireSettings detects it, preserves all fields, and migrates the page under admin without data loss.

---

## Requirements

- **ProcessWire:** `>= 3.0.180`
- **PHP:** `>= 8.1`

---

## Installation

1. Place the `WireSettings` folder in `/site/modules/`.
2. In the ProcessWire Admin, navigate to **Modules > Refresh**.
3. Locate **WireSettings** and click **Install**.
4. During installation, the module automatically:
   - Checks if a template named `settings` already exists (creates it if not).
   - Migrates any existing `/settings/` frontend page into the hidden admin storage.
   - Creates the top-level `/admin/settings/` menu link.
   - Registers the `wire-settings` user permission.

---

## Configuration

In **Modules > Configure > WireSettings**, you can customize:

| Option | Default | Description |
|---|---|---|
| `template_name` | `settings` | Name of the ProcessWire template used for settings fields. |
| `api_var_name` | `settings` | Name of the global variable exposed to frontend templates. |
| `admin_title` | `Settings` | Menu title displayed in the admin top navigation. |
| `admin_icon` | `sliders` | FontAwesome/ProcessWire icon name for the admin item. |
| `hide_system_tabs` | `true` | Hides the core *Settings/Delete/Children* tabs in the editor. |
| `show_template_shortcut` | `true` | Displays a direct link to template field setup for Superusers. |

---

## Frontend Template Usage

Because WireSettings exposes the data page as a global API variable, it is automatically available in all template files:

```php
// Basic text fields
<h1><?= $settings->company ?></h1>
<p><?= $settings->footer_claim ?></p>

// Contact info
<a href="mailto:<?= $settings->email ?>"><?= $settings->email ?></a>
<a href="tel:<?= $settings->phone ?>"><?= $settings->phone ?></a>

// Image fields
<?php if ($settings->logo): ?>
    <img src="<?= $settings->logo->url ?>" alt="<?= $settings->company ?>">
<?php endif; ?>

// Social links
<?php if ($settings->facebook): ?>
    <a href="<?= $settings->facebook ?>" target="_blank" rel="noopener">Facebook</a>
<?php endif; ?>

// SEO & Metadata
<title><?= $settings->meta_title ?: $page->title ?></title>
<meta name="description" content="<?= $settings->meta_description ?>">
```

### Accessing via Wire container

In custom modules, functions, or external templates:

```php
// Via wire() helper
$company = wire('settings')->company;

// Via $wire instance
$logoUrl = $wire->settings->logo ? $wire->settings->logo->url : null;
```

---

## Adding & Organizing Fields

To add or rearrange settings fields:
1. In ProcessWire Admin, navigate to **Setup > Templates > settings** (or use the *Edit Template Fields* shortcut button on the Settings screen).
2. Add your desired fields (e.g. `company`, `street`, `phone`, `logo`, etc.).
3. Group them into tabs using ProcessWire's `FieldsetTabOpen` fields (e.g., *Grundlagen*, *Backend*, *SEO*).
4. Save the template. The new fields and tabs appear immediately in **Settings** and are instantly accessible in `$settings`.

---

## Permissions

WireSettings creates a dedicated permission:
- **`wire-settings`**: Allows non-superusers (e.g. `editor` or `manager` roles) to view and modify global settings without granting full admin rights.

---

## Translations (i18n)

WireSettings includes a German translation file in ProcessWire's native CSV format:
- `languages/de.csv`

To activate or import German translations:
1. In ProcessWire Admin, ensure the core **Language Support** module is installed.
2. Go to **Setup > Languages > Deutsch** (or your German language page).
3. Import `languages/de.csv` or use ProcessWire's translation tool to load strings for `WireSettings`.

---

## License

This module is licensed under the [MIT License](LICENSE).  
Developed by **Markus Thomas**.
