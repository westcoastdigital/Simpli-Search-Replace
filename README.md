# Simpli Search Replace

A production-ready, serialization-safe WordPress database search and replace plugin with advanced safety features and preview functionality.

## Features

### 🎯 Core Functionality
- **Serialization-Safe** - Properly handles WordPress serialized data without corrupting it
- **Live Preview** - See exactly what will change before making any modifications
- **Word Highlighting** - Changed text is highlighted in red (removed) and green (added)
- **Multi-Table Support** - Select multiple tables with Ctrl+A, Shift+Click, or helper buttons
- **Case Sensitivity** - ON by default for safer URL replacements (can be toggled)
- **GUID Protection** - GUIDs are protected by default, only modified when explicitly enabled

### 🔒 Safety Features
- **Preview Required** - Run button is disabled until you preview changes
- **Typed Confirmation** - Must type "YES" to execute replacements
- **Protected Columns** - 17 critical database columns are always protected:
  - ID, id, option_id, option_name, meta_id, meta_key
  - user_id, post_id, term_id, comment_id, link_id
  - slug, post_name, user_login, user_email, user_pass, user_activation_key
- **Critical Table Warnings** - Extra warnings when modifying users/usermeta tables
- **Dangerous Replacement Detection** - Prevents emptying critical content fields
- **Preview Limit** - Limited to 500 results to prevent UI overload

### 💡 User Experience
- **Native Select Element** - Full keyboard support (Ctrl+A, Shift+Click, etc.)
- **Helper Buttons** - Select All, Deselect All, Select Safe Tables Only
- **Enhanced Warnings** - Comprehensive warnings before dangerous operations
- **Form Validation** - Validates input before processing
- **Progress Indicators** - Clear feedback during processing
- **Professional UI** - Clean, WordPress-native styling

## Installation

1. Upload the plugin folder to `/wp-content/plugins/simpli-search-replace/`
2. Ensure this folder structure:
```
simpli-search-replace/
├── simpli-search-replace.php
├── github-updater.php
├── includes/
│   ├── class-ssr-admin.php
│   ├── class-ssr-processor.php
│   └── class-ssr-serializer.php
└── assets/
    ├── admin.js
    └── admin.css
```
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Access it under **Tools → Simpli Search Replace**

## Usage

### Basic Workflow

1. **BACKUP YOUR DATABASE FIRST!** ⚠️
2. Enter your search term
3. Enter replacement text (leave empty to delete)
4. Select tables to search
5. Configure options (case sensitivity, GUIDs)
6. Click **Preview Changes** (required!)
7. Review all changes carefully
8. If everything looks correct, click **Run Replacement**
9. Type "YES" to confirm

### Selecting Tables

**Multiple Selection Methods:**
- Click to select one table
- **Ctrl+A** inside the select box to select all
- **Ctrl+Click** to select multiple individual tables
- **Shift+Click** to select a range
- Use helper buttons:
  - **Select All Tables** - Selects every table
  - **Deselect All** - Clears selection
  - **Select Safe Tables Only** - Excludes critical tables (users, usermeta)

### Options

**Case Sensitive (Checked by default)**
- ✅ ON: "Text" matches only "Text" (recommended for URLs)
- ❌ OFF: "Text" matches "text", "TEXT", "TeXt", etc.

**Replace GUIDs (Unchecked by default)**
- ❌ OFF: GUIDs are protected and won't be modified (safe)
- ✅ ON: GUIDs can be modified (use with caution!)

## Common Use Cases

### Site Migration (URL Change)
```
Search For:   http://oldsite.com
Replace With: https://newsite.com
Tables:       All content tables (posts, postmeta, options, etc.)
Options:      Case Sensitive: ON, Replace GUIDs: OFF
```

### Fix Typos Site-Wide
```
Search For:   recieve
Replace With: receive
Tables:       posts, postmeta, comments
Options:      Case Sensitive: OFF
```

### Update Author Names
```
Search For:   John Doe
Replace With: Jane Smith
Tables:       posts, postmeta
Options:      Case Sensitive: OFF
```

### Media Path Updates
```
Search For:   /wp-content/uploads/2024/
Replace With: /wp-content/uploads/2025/
Tables:       posts, postmeta
Options:      Case Sensitive: ON
```

## Preview Highlighting

When you preview changes, the plugin highlights exactly what's changing:

- 🔴 **Red highlights** = Text being REMOVED (in "Before" section)
- 🟢 **Green highlights** = Text being ADDED (in "After" section)

This makes it easy to spot changes, especially in long text fields or serialized data.

## Safety & Best Practices

### ✅ DO
- **ALWAYS backup your database before running replacements**
- Always preview changes first
- Start with a single table to test
- Use specific search terms (avoid very short/generic terms)
- Test on a staging site first for major changes
- Double-check URLs and paths
- Review the preview carefully

### ❌ DON'T
- Never run replacements without previewing
- Avoid selecting critical tables unless absolutely necessary
- Don't enable "Replace GUIDs" unless you know what you're doing
- Avoid very short search terms (< 3 characters)
- Never use on a live site without a recent backup

## GitHub Auto-Updates

This plugin includes automatic update functionality via GitHub releases.

### Setup (if using GitHub releases)
1. Create releases in your GitHub repository
2. The plugin will check for updates automatically
3. Updates appear in WordPress admin like any other plugin

### Private Repository
If your repository is private, add your GitHub token:
```php
define('SW_GITHUB_ACCESS_TOKEN', 'your_token_here');
```

## Troubleshooting

### "No matches found" but I know the text exists
- Check case sensitivity setting
- Verify you're searching the correct tables
- Check if the content is in a protected column

### Preview shows too many results
- Results are limited to 500 for performance
- Message will indicate if limit was reached
- The actual replacement will process all matches

### Changes not appearing after replacement
- Verify the replacement actually ran (check success message)
- Clear any caching (WordPress, CDN, browser)
- Check if the column was protected

## Technical Details

### Protected Columns (Always)
These columns cannot be modified under any circumstances:
- ID, id, option_id, option_name, meta_id, meta_key
- user_id, post_id, term_id, comment_id, link_id
- slug, post_name, user_login, user_email, user_pass, user_activation_key

### GUID Column (Conditionally Protected)
- Protected by default via checkbox
- Only modified when "Replace GUIDs" is checked
- Generally should NOT be changed

### Security
- Nonce verification on all AJAX requests
- Capability checking (`manage_options` required)
- SQL injection protection via `$wpdb->prepare()`
- All input is sanitized
- Output is escaped to prevent XSS

## Changelog

### Version 1.1.0 (Enhanced)
- ✨ NEW: Native select element for table selection (supports Ctrl+A)
- ✨ NEW: Word highlighting in preview (red for removed, green for added)
- ✨ NEW: Three helper buttons for table selection
- ✨ NEW: Critical table warnings
- ✨ NEW: Preview-required workflow (Run button disabled until preview)
- ✨ NEW: Typed confirmation ("YES" required)
- ✨ NEW: Enhanced safety checks for dangerous replacements
- 🔒 NEW: Additional protected columns - now 17 protected columns
- 🔒 NEW: Dangerous replacement detection (prevents emptying critical fields)
- 🔒 NEW: Preview result limit (500 items to prevent UI overload)
- 🔒 CHANGED: Case sensitivity is now ON by default (safer for URL replacements)
- 🐛 FIX: Case sensitivity now actually works (was accepted but not used)
- 🐛 FIX: GUID protection now works correctly via checkbox
- 🐛 FIX: "Replace With" input field had wrong name attribute
- 💄 IMPROVED: Much better UI with WordPress admin styling
- 💄 IMPROVED: Enhanced warning messages
- 💄 IMPROVED: Better result display with clear labels
- 💄 IMPROVED: Form validation before processing

### Version 1.0.0 (Original)
- Initial release

## Credits

**Original Author:** Jon Mather (https://jonmather.au)  
**Repository:** https://github.com/westcoastdigital/Simpli-Search-Replace  
**License:** GPL v2 or later

## Support

For issues, feature requests, or contributions:
- GitHub Issues: https://github.com/westcoastdigital/Simpli-Search-Replace/issues
- Pull requests welcome!

---

**⚠️ REMEMBER: ALWAYS BACKUP YOUR DATABASE BEFORE USING THIS TOOL! ⚠️**

This plugin is powerful and irreversible. A database backup is your only safety net.