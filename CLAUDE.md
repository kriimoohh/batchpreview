# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Moodle local plugin (`local_batchpreview`) that enables batch preview and modification of Collaborate room names based on course categories. The plugin allows administrators to preview changes before applying SQL updates to the database.

## Architecture

### Core Components

**Entry Points:**
- `index.php` - Main form interface for category selection and configuration
- `preview.php` - Preview page showing affected rooms and generated SQL
- `lib.php` - Plugin navigation integration

**Form System:**
- `classes/form/category_form.php` - Moodle form for user input with validation
- Handles category ID, prefix/suffix customization, and display options

**Output System:**
- `classes/output/preview_renderer.php` - Template-based rendering system
- Contains `preview_table` and `sql_generator` classes for data presentation
- Templates in `templates/` directory (mustache format)

**Frontend:**
- `amd/src/preview.js` - AMD module for UI interactions (copy/download SQL)
- `styles/styles.css` - Custom styling

**Internationalization:**
- `lang/en/local_batchpreview.php` - English language strings
- `lang/fr/local_batchpreview.php` - French language strings

### Data Flow

1. User selects category ID and naming options in form (`category_form.php`)
2. Form validation checks category existence against database
3. Preview page (`preview.php`) queries Collaborate rooms in category hierarchy
4. Results displayed via renderer classes with mustache templates
5. SQL generation creates both verification and update queries
6. JavaScript handles client-side copy/download functionality

### Database Queries

The plugin uses complex SQL to find Collaborate rooms in category hierarchies:
- Searches by category path patterns (`%/categoryid/%`, `%/categoryid`)
- Joins `mdl_collaborate`, `mdl_course`, and `mdl_course_categories` tables
- Generates UPDATE statements with CONCAT for name formatting

### Security Model

- Requires `moodle/site:config` capability (site admin)
- Form validation prevents invalid category IDs
- SQL generation includes safety warnings and verification queries
- No direct database modifications - only generates SQL for manual execution

## Plugin Structure

```
local_batchpreview/
├── classes/
│   ├── form/category_form.php          # Main input form
│   └── output/preview_renderer.php     # Template rendering
├── amd/src/preview.js                  # Frontend interactions
├── lang/[en|fr]/local_batchpreview.php # Localization
├── styles/styles.css                   # Custom CSS
├── templates/                          # Mustache templates
├── index.php                          # Main entry point
├── preview.php                        # Preview/results page
├── lib.php                            # Navigation integration
└── version.php                        # Plugin metadata
```

## Development Commands

Since this is a Moodle plugin, standard Moodle development practices apply:

**Plugin Installation/Upgrade:**
```bash
# Navigate to Moodle root and run upgrade
php admin/cli/upgrade.php
```

**Cache Clearing:**
```bash
# Clear Moodle caches after code changes
php admin/cli/purge_caches.php
```

**Language String Debugging:**
- Enable developer mode in Moodle admin settings
- Language debugging shows missing/unused strings

## Key Functions

**`get_preview_data()` in preview.php:712**
- Core data retrieval function
- Builds category hierarchy queries
- Formats new names based on user options

**`generate_sql_code()` in preview.php:167**
- Generates executable SQL with safety checks
- Creates both verification SELECT and UPDATE queries
- Includes metadata comments and warnings

## Security Considerations

- Plugin requires administrator privileges
- Generated SQL includes extensive safety warnings
- No automatic execution - requires manual DBA intervention
- Validation prevents injection via category ID parameter