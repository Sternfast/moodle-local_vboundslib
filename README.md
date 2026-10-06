# local_vboundslib — Vbounds/Sternfast shared library

Layer 1 of the [plugin factory](../FACTORY.md). A tiny, dependency-free `local` plugin that holds
code our other plugins would otherwise each reimplement — so the pattern **and its bug fixes** live
in one place. It stores no data and adds no UI; it only exposes helper classes via autoloading.

## What's in it
- `\local_vboundslib\form\pickers::user_autocomplete_options($context, $noselection)` — the AJAX
  "search all users" picker options (with the `with_identity(..., false)` fix that prevents the edit
  page fataling when a custom profile field is in "Show user identity").
- `\local_vboundslib\form\pickers::cohort_options()` — enriched cohort multi-select labels (ID
  number + member count).

## How a plugin adopts it
1. In the consumer's `version.php`:
   ```php
   $plugin->dependencies = ['local_vboundslib' => 2026100600];
   ```
2. State the dependency in the consumer's README + Marketplace description (reviewers require this).
3. Use the helpers:
   ```php
   $mform->addElement('autocomplete', 'userids', get_string('users', '...'), [],
       \local_vboundslib\form\pickers::user_autocomplete_options($context, get_string('none', '...')));
   $mform->setType('userids', PARAM_INT);
   ```

## Adoption policy (important)
- **New plugins adopt it from day one.**
- **Existing live plugins are retrofitted deliberately, one at a time, and never while in review**
  (adding a dependency changes the plugin and is scrutinised). Order: White-Label Branding only
  after MMR-389 clears, then Access Expiry, then Grade Lookup.
- Trade-off to respect: `$plugin->dependencies` only *blocks* install until the admin installs this
  lib first (it does not auto-install) → install-order friction + coupling. Keep the suite small;
  only promote genuinely common code here.
