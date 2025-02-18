
# JetFormBuilder Export/Import Plugin

A WordPress plugin that adds export and import functionality for JetFormBuilder form submissions. Safely transfer form data between WordPress installations while maintaining data integrity.

## Features

- Export form submissions to CSV files
- Import submissions from CSV files
- Preview imports before committing
- Supports all JetFormBuilder field types
- Maintains relationships between submissions and their fields, actions and errors
- Data validation and sanitization
- Base64 encoding for complex data fields
- Handles duplicate entries

## Usage

1. Install and activate the plugin
2. Go to "JetFB Export Import" in the WordPress admin menu
3. Select forms to export submissions from
4. Download the CSV file
5. Import the CSV file on another WordPress installation

## Requirements

- WordPress 5.6+
- JetFormBuilder plugin
- PHP 7.2+

## Security

- Role-based access control (only administrators can export/import)
- Data validation and sanitization
- Safe file handling
- Database transactions to prevent partial imports

## Support

Found a bug or need help? [Open an issue](https://github.com/DeBelserArne/jetformbuilder-export-import/issues)

## License

GPL v2 or later
