# Diff for Flarum 2.x

[![MIT license](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/huseyinfiliz/flarum-diff/blob/master/LICENSE) [![Latest Stable Version](https://img.shields.io/packagist/v/huseyinfiliz/flarum-diff.svg)](https://packagist.org/packages/huseyinfiliz/flarum-diff) [![Total Downloads](https://img.shields.io/packagist/dt/huseyinfiliz/flarum-diff.svg)](https://packagist.org/packages/huseyinfiliz/flarum-diff)

This extension adds a "post revision history" feature to your [Flarum](https://github.com/flarum) forum.

> **Note:** This is the Flarum 2.x compatible version of the original [the-turk/flarum-diff](https://github.com/the-turk/flarum-diff) extension (which supports Flarum 1.8.x). This fork has been updated to work with Flarum 2.0 and later versions.

Screenshots:

![Diff Collage](https://i.ibb.co/FJywHKn/rsz-diff-collage.png)

- [Post-Stream Item](https://i.ibb.co/4m21pnM/post-Stream-Item.png)
- [Dropdown List](https://i.ibb.co/PTTcWCw/dropdown-List.png)

## Features

- Based on [jfcherng/php-diff](https://github.com/jfcherng/php-diff) repository (this one is forked from [chrisboulton/php-diff](https://github.com/chrisboulton/php-diff) since it's no longer maintained).
- Option for **line** (default), **word** and **char** level diffs.
- Three render modes including "Inline", "Side By Side" & "Combined".
- Archive old revisions using cron jobs or manually.
- Delete revisions or rollback to certain revision.
- Supports `the-turk/flarum-quiet-edits`.
- Supports all browsers which are supporting [css-grid](https://caniuse.com/#feat=css-grid).

Also, it won't load (and cache) anything until you click the "Edited" button so no need to worry about loading times.

## Requirements

![php](https://img.shields.io/badge/php-%E2%89%A58.1-blue?style=flat-square) ![ext-iconv](https://img.shields.io/badge/ext-iconv-brightgreen?style=flat-square)

You can check your php version by running `php -v` and check if `iconv` is installed by running `php --ri iconv` (which should display `iconv support => enabled`).

## Installation

```bash
composer require huseyinfiliz/flarum-diff:"*"
```

## Updating

```bash
composer update huseyinfiliz/flarum-diff
php flarum migrate
php flarum cache:clear
```

## Upgrading from the-turk/flarum-diff

> ⚠️ **IMPORTANT: Database Backup Required**
> 
> Before upgrading, **you must backup your database**. While our tests showed no data loss during migration, there is always a risk when upgrading major versions. Your revision history data is stored in the `post_edit_histories` and `post_edit_histories_archive` tables.
>
> In our tests, all revision data was preserved successfully after the upgrade.

> ℹ️ **About Settings Migration**
>
> Extension settings from the-turk/flarum-diff (1.8.x) **will not be transferred** to this version. This is because the settings prefix has changed from `the-turk-diff.*` to `huseyinfiliz-diff.*`. 
>
> **This is not a critical issue** - after installing this extension, you simply need to configure your settings manually in the admin panel. This only affects display preferences (detail level, neighbor lines, merge threshold, archive options, etc.) and **does not affect your revision history data in any way**. All your stored revisions will continue to work normally with the default settings until you customize them.

To upgrade from the original extension:

1. **Backup your database** (mandatory)
2. Disable the old extension in admin panel
3. Remove the old extension:
   ```bash
   composer remove the-turk/flarum-diff
   ```
4. Upgrade Flarum to 2.0:
   ```bash
   composer require flarum/core:"^2.0"
   # Follow Flarum's upgrade guide
   ```
5. Install this extension:
   ```bash
   composer require huseyinfiliz/flarum-diff:"*"
   ```
6. Run migrations and clear cache:
   ```bash
   php flarum migrate
   php flarum cache:clear
   ```
7. Enable the extension in admin panel
8. **Configure your settings** in the admin panel (settings from 1.8.x will not be migrated)

**Summary:** Your revision history data will be fully preserved. Only extension settings need to be reconfigured manually.

## Usage

Enable the extension and set the permissions. You're ready to go!

### Archive Old Revisions

If **x ≥ A** (where the **x** is post's revision count), first **y=mx+b** revisions for the post can be stored as merged & compressed `BLOB` in a new table (which is called `post_edit_histories_archive`). Specify the **A**, **m** and **b** from the settings modal. Float values of **y** will be rounded to the next lowest integer value. It's recommended to archive old revisions if you want to save storage volume but **_not recommended if you don't want to_**.

If you want to archive old revisions, please consider enabling _cron job option_ from the settings modal. I set a weekly cron job which is working on sundays at 02:00 AM (nothing special) using `diff:archive` command. Otherwise, it'll try to find & archive old revisions for the post as soon as `Post\Revised` event fires or wait for your `php flarum diff:archive` command. See [this discussion](https://discuss.flarum.org/d/24118-setup-the-flarum-scheduler-using-cron) for setting up the scheduler.

## Links

- [Flarum Discuss post](https://discuss.flarum.org/d/22779-diff-for-flarum)
- [Source code on GitHub](https://github.com/huseyinfiliz/flarum-diff)
- [Changelog](https://github.com/huseyinfiliz/flarum-diff/blob/master/CHANGELOG.md)
- [Report an issue](https://github.com/huseyinfiliz/flarum-diff/issues)
- [Download via Packagist](https://packagist.org/packages/huseyinfiliz/flarum-diff)
- [Original extension (Flarum 1.x)](https://github.com/the-turk/flarum-diff)

## Credits

- Original extension by [Hasan Özbey (the-turk)](https://github.com/the-turk)
- Flarum 2.x compatibility by [Hüseyin Filiz](https://github.com/huseyinfiliz)