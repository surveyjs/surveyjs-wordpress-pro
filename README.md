# SurveyJS Forms Add-on

SurveyJS Forms Add-on is an add-on for the free [SurveyJS Forms](https://wordpress.org/plugins/surveyjs/) WordPress plugin. SurveyJS Forms keeps your forms and entries; the add-on adds features on top of them:

- **Survey Creator** in the form editor: drag & drop designer, logic, translations, JSON editor and preview.
- **Charts** on each form's Entries page and a **Dashboard** with entries across all forms.
- **PDF export**: entries and blank forms as PDF, a Download PDF button for respondents, PDFs in emails, password-protected PDFs.

With the AI connection of SurveyJS Forms on (SurveyJS Forms > Settings > AI MCP), AI apps such as Claude Code or Cursor also change the add-on's per-form PDF settings (as a draft, like all AI changes) and the add-on admin settings (Survey Creator on or off, color mode, style; applied right away).

The add-on is distributed from [surveyjs.io](https://surveyjs.io/wordpress-addon) and as the Composer package `surveyjs/surveyjs-forms-addon`; it is not in the WordPress.org directory.

**Requirements:** WordPress 6.5+, PHP 8.2+, and SurveyJS Forms of the same version as the add-on. Both plugins are released together with the same version number (the add-on 3.2.4 works with SurveyJS Forms 3.2.4).

- [Install from the zip](#install-from-the-zip) — most sites
- [Install with Composer](#install-with-composer) — Bedrock and other Composer-managed sites
- [WordPress VIP and git-managed sites](#vip)
- [Updates](#updates)

## Install from the zip

1. Install and activate SurveyJS Forms: Plugins → Add New Plugin → search for "SurveyJS Forms".
2. Download the SurveyJS Forms Add-on zip from https://surveyjs.io/wordpress-addon.
3. Plugins → Add New Plugin → Upload Plugin → choose the zip → Install Now → Activate Plugin.

If you uploaded the add-on before SurveyJS Forms, WordPress doesn't let you activate it yet: in the add-on's row on the Plugins screen, follow "Requires: SurveyJS Forms" → Install Now → Activate, then activate the add-on.

On multisite, network-activate both plugins.

WP-CLI: `wp plugin install surveyjs --activate && wp plugin install /path/to/surveyjs-forms-addon.zip --activate`.

## Install with Composer

The add-on is the Packagist package `surveyjs/surveyjs-forms-addon` (`type: wordpress-plugin`). It requires SurveyJS Forms as `wp-plugin/surveyjs` from [WP Packages](https://wp-packages.org/) (the WordPress.org mirror by Roots, used by Bedrock), so one command installs both plugins. Add WP Packages to your project's root `composer.json` (Composer doesn't read repositories from dependencies):

```json
{
  "repositories": [
    { "type": "composer", "url": "https://repo.wp-packages.org", "only": ["wp-plugin/*", "wp-theme/*"] }
  ],
  "extra": {
    "installer-paths": {
      "wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
    }
  },
  "config": {
    "allow-plugins": { "composer/installers": true }
  }
}
```

Then:

```sh
composer require surveyjs/surveyjs-forms-addon
```

- `installer-paths`: `wp-content/plugins/{$name}/` for a classic layout, `web/app/plugins/{$name}/` for Bedrock (already set there), `plugins/{$name}/` for a WordPress VIP repository. Without `allow-plugins` for `composer/installers` both plugins end up in `vendor/`, where WordPress doesn't load them.
- Keep the folder names `surveyjs` and `surveyjs-forms-addon`: the add-on's `Requires Plugins: surveyjs` matches the folder name.
- Activate both plugins in wp-admin or with `wp plugin activate surveyjs surveyjs-forms-addon`.
- Update both plugins: `composer update surveyjs/surveyjs-forms-addon --with-dependencies`. The add-on sees that Composer manages it and doesn't update itself (see [Updates](#updates)).
- Each add-on version requires exactly the same version of SurveyJS Forms, so Composer always installs a matching pair. A new add-on version is installable a few minutes after the same SurveyJS Forms version appears on WordPress.org; until then `composer update` keeps the current pair.
- Don't update SurveyJS Forms in wp-admin on a Composer site: the add-on won't follow it there. Bedrock prevents this in production with `DISALLOW_FILE_MODS`.
- Projects on WPackagist: keep `wpackagist.org` and add the WP Packages repository above for `wp-plugin/*`. Don't also require `wpackagist-plugin/surveyjs`: it is the same plugin under another name and would install into the same `surveyjs/` folder.
- WordPress VIP doesn't run Composer on deploy: commit the installed `plugins/surveyjs/` and `plugins/surveyjs-forms-addon/`, or run `composer install` in CI and deploy the built branch (see [below](#vip)).

<a id="vip"></a>
## WordPress VIP and git-managed sites

On WordPress VIP (and any site whose code is deployed from a git repository) plugins can't be installed, updated or deleted in wp-admin: they are committed to the repository. There are two ways to get SurveyJS Forms and SurveyJS Forms Add-on there:

- **Composer** — see [Install with Composer](#install-with-composer), with `installer-paths` set to `plugins/{$name}/`. VIP doesn't run Composer on deploy, so commit the installed `plugins/surveyjs/` and `plugins/surveyjs-forms-addon/` or build them in CI. The Composer package has no loader: add the two lines from [Use your own plugin loader](#use-your-own-plugin-loader) to your `client-mu-plugins/plugin-loader.php`.
- **VIP archive** — `surveyjs-forms-vip-<version>.zip`, both plugins and a loader, laid out like a VIP repository:

  ```
  plugins/surveyjs/                      SurveyJS Forms
  plugins/surveyjs-forms-addon/                  SurveyJS Forms Add-on
  client-mu-plugins/surveyjs-forms.php   loads both plugins with wpcom_vip_load_plugin()
  ```

### Install from the VIP archive

1. Download `surveyjs-forms-vip-<version>.zip` from https://surveyjs.io/wordpress-addon.
2. Unzip it into the root of your VIP repository (the one created from [vip-go-skeleton](https://github.com/Automattic/vip-go-skeleton)), so the files land in the existing `plugins/` and `client-mu-plugins/` folders. Keep the folder names `surveyjs` and `surveyjs-forms-addon`.
3. Commit, open a pull request and merge it. VIP deploys the branch as usual.
4. Open wp-admin → Plugins. You should see:
   - SurveyJS Forms and SurveyJS Forms Add-on, both marked "Enabled via code";
   - no Activate, Deactivate, Delete or Update links for them — they are managed in the repository;
   - no auto-update or "Update SurveyJS Forms Add-on" button: the add-on never downloads updates on VIP.

   There is no activation step: both plugins create their database tables on the first request after the deploy. The SurveyJS Forms menu is available right away.

### Update

1. Delete `plugins/surveyjs/` and `plugins/surveyjs-forms-addon/` from the repository (this also removes files the new version no longer has).
2. Unzip the new archive the same way; let it overwrite `client-mu-plugins/surveyjs-forms.php`.
3. Commit, open a pull request, merge.

Always update both plugins from the same archive: the add-on works only with the SurveyJS Forms version it was released with. Don't update `plugins/surveyjs/` alone from wordpress.org.

### Use your own plugin loader

If you already load plugins from `client-mu-plugins/plugin-loader.php`, you can add these two lines there instead of using our loader, and not commit `client-mu-plugins/surveyjs-forms.php` from the archive:

```php
wpcom_vip_load_plugin( 'surveyjs' );
wpcom_vip_load_plugin( 'surveyjs-forms-addon' );
```

- Keep both plugins in `plugins/`, not in `client-mu-plugins/`: VIP reports only plugins loaded from `plugins/` as active to WordPress, and the add-on's `Requires Plugins: surveyjs` checks that.
- Loading them twice (our file and your lines) does no harm: `wpcom_vip_load_plugin()` includes the plugin file with `include_once`, and VIP removes the duplicate from the active plugins list.
- Our loader does nothing outside VIP (it checks that `wpcom_vip_load_plugin()` exists), so the same repository also works in other environments.

### VIP Code Analysis Bot

The pull request adds the full code of both plugins, so the VIP Code Analysis Bot checks all of it (PHPCS with the `WordPress-VIP-Go` standard, PHP and JavaScript lint). Both plugins have no errors in `WordPress-VIP-Go`. The bot may still comment with warnings — mostly the bundled minified libraries in `libs/` (which PHPCS can't tokenize) and direct queries to the plugins' own entries table. Warnings don't block the merge. To keep the bot off the bundled libraries, list `plugins/surveyjs/libs` and `plugins/surveyjs-forms-addon/libs` in `.vipgoci_phpcs_skip_folders` and `.vipgoci_lint_skip_folders` in the repository root ([VIP docs](https://docs.wpvip.com/vip-code-analysis-bot/customize-phpcs/)).


## Updates

SurveyJS Forms and SurveyJS Forms Add-on are released together, with the same version number. How the add-on is updated depends on how you installed it:

| Installed | How the add-on is updated |
|---|---|
| From the zip | Automatically. When SurveyJS Forms is updated in wp-admin (manually, in bulk or by auto-updates), the add-on downloads its new version from surveyjs.io in the same request and stays active. |
| With Composer | With Composer: `composer update surveyjs/surveyjs-forms-addon --with-dependencies`. The add-on doesn't update itself. |
| VIP archive or git | In your repository: replace both plugin folders with the new version (see [Update](#update)). The add-on doesn't update itself. |

- When the two versions differ, the add-on shows a notice on admin pages with a button for the older plugin. On a Composer install the notice shows the Composer command instead.
- The add-on's download sends no data about your site: it is a plain request with the user agent `SurveyJS-Forms-Addon/<version>`. A failed download never affects the SurveyJS Forms update; the error appears in the notice.

### How the add-on detects a Composer install

The add-on treats itself as installed with Composer only when Composer's own records say that `surveyjs/surveyjs-forms-addon` is installed in the folder the add-on runs from:

- `Composer\InstalledVersions`, when the project's `vendor/autoload.php` is loaded (as in Bedrock), or
- `vendor/composer/installed.json` next to a `composer.json` in one of the four parent folders of the plugin (the `vendor-dir` setting of that `composer.json` is respected).

A `composer.json` or a `vendor/` folder alone isn't enough, so a site that uses Composer for something else but uploaded the add-on as a zip keeps self-updating. On a Composer install the add-on's row on the Plugins screen says "Installed with Composer, so it doesn't update itself", and a pending SurveyJS Forms update says that the add-on is not updated there.

### Turn self-update off or on

| Setting | Self-update |
|---|---|
| `define( 'SURVEYJS_ADDON_DISABLE_UPDATES', true );` in `wp-config.php` | Off. |
| `define( 'SURVEYJS_ADDON_DISABLE_UPDATES', false );` in `wp-config.php` | On, even on a Composer install (for example, Composer was used only for the first install). The add-on then overwrites the files Composer installed. |
| Filter `surveyjs_addon_self_update` (`bool`; `false` on a Composer install, `true` otherwise), e.g. `add_filter( 'surveyjs_addon_self_update', '__return_false' );` | What the filter returns. |
| `DISALLOW_FILE_MODS` (Bedrock's default in production), WordPress VIP | Always off; the constant and the filter can't turn it on. |

## License

See [LICENSE.md](LICENSE.md). The SurveyJS libraries and third-party libraries in `libs/` and the PDF fonts in `assets/fonts/` (SIL Open Font License) have their own license files.

## Support

- [SurveyJS support](https://surveyjs.io/support)
- [SurveyJS Forms support forum](https://wordpress.org/support/plugin/surveyjs) on WordPress.org

## For developers

How the add-on is built, its extension contract with SurveyJS Forms, tests and releases: [DEVELOPMENT.md](DEVELOPMENT.md).
