# Drupal Configuration Directory

This directory contains Drupal configuration files exported from the site.

## Directory Structure

```
config/
└── sync/                    # Drupal configuration export files
    ├── core.extension.yml   # Enabled modules and themes
    ├── system.site.yml      # Site information
    ├── commerce_*.yml       # Commerce configuration (including Helcim)
    └── ...                  # All other configuration files
```

## Configuration Sync Directory

The `sync/` directory contains all Drupal configuration that can be safely version controlled:

- **Site settings** (system.site.yml)
- **Content types** (node.type.*.yml)
- **Fields** (field.*.yml)
- **Views** (views.view.*.yml)
- **Commerce settings** (commerce_*.yml)
- **Payment gateways** (commerce_payment.*.yml)
- **And much more...**

## Usage

### Export Configuration
```bash
ddev drush config:export
```

### Import Configuration
```bash
ddev drush config:import
```

### Check Configuration Status
```bash
ddev drush config:status
```

## Settings.php Configuration

The configuration sync directory is set in `web/sites/default/settings.php`:

```php
$settings['config_sync_directory'] = '../config/sync';
```

This places configuration files outside the web directory for better security.

## Version Control

- ✅ **Include**: All files in `sync/` directory

## Deployment

When deploying to a new environment:

1. Copy this entire `config/` directory
2. Run `ddev drush config:import` to apply configuration
3. Clear cache: `ddev drush cr`

## Security

- Configuration files are outside the web directory
- All configuration is managed through Drupal's standard config system
