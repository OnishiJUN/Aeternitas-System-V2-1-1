# Redundant Blade Files Removed

The following files contained duplicate CRUD form markup and were removed after consolidating into shared form views:

## Removed files
- resources/views/positions/create.blade.php
- resources/views/positions/edit.blade.php
- resources/views/departments/create.blade.php
- resources/views/departments/edit.blade.php
- resources/views/tax-brackets/create.blade.php
- resources/views/tax-brackets/edit.blade.php

## Replacement files to keep
- resources/views/positions/form.blade.php
- resources/views/departments/form.blade.php
- resources/views/tax-brackets/form.blade.php

## Controllers updated
- app/Http/Controllers/PositionController.php
- app/Http/Controllers/Web/DepartmentController.php
- app/Http/Controllers/Web/TaxBracketController.php
