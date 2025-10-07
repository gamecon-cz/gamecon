#!/bin/bash

# Script to update ORM\Index names to use IDX_ prefix

# Array of files to process
files=(
    "symfony/src/Entity/UserMergeLog.php"
    "symfony/src/Entity/SystemSetting.php"
    "symfony/src/Entity/SystemSettingLog.php"
    "symfony/src/Entity/GoogleDriveDir.php"
    "symfony/src/Entity/ActivityRegistrationSpec.php"
    "symfony/src/Entity/Payment.php"
    "symfony/src/Entity/ActivityInstance.php"
    "symfony/src/Entity/ActivityRegistration.php"
    "symfony/src/Entity/User.php"
    "symfony/src/Entity/Tag.php"
    "symfony/src/Entity/UserRoleByYear.php"
    "symfony/src/Entity/ActivityRegistrationLog.php"
    "symfony/src/Entity/UserRole.php"
    "symfony/src/Entity/RolePermission.php"
    "symfony/src/Entity/Role.php"
    "symfony/src/Entity/EventLog.php"
    "symfony/src/Entity/ActivityTag.php"
    "symfony/src/Entity/BulkActivityLog.php"
    "symfony/src/Entity/Activity.php"
    "symfony/src/Entity/Discount.php"
    "symfony/src/Entity/NewsletterSubscriptionLog.php"
    "symfony/src/Entity/ReportUsageLog.php"
    "symfony/src/Entity/CategoryTag.php"
    "symfony/src/Entity/ShopPurchaseCancelled.php"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "Processing $file..."

        # Use sed to replace Index names - handle various patterns
        sed -i -E "s/Index\(columns: \[([^\]]+)\], name: '([^']+)(_idx)?'\)/Index(columns: [\1], name: 'IDX_\2')/g" "$file"

        # Handle special case where Index has only name (no columns)
        sed -i -E "s/Index\(name: '([^']+)(_idx)?'\)/Index(name: 'IDX_\1')/g" "$file"

        # Clean up any double IDX_ prefix that might have been added
        sed -i "s/IDX_IDX_/IDX_/g" "$file"

        # Remove _idx suffix if it remains
        sed -i -E "s/IDX_([a-zA-Z0-9_]+)_idx'/IDX_\1'/g" "$file"

        # Handle FK_ prefixed names - replace them with IDX_
        sed -i -E "s/Index\(columns: \[([^\]]+)\], name: 'FK_([^']+)'\)/Index(columns: [\1], name: 'IDX_\2')/g" "$file"

        # Clean up idx_ prefix patterns
        sed -i -E "s/IDX_idx_/IDX_/g" "$file"

    fi
done

echo "Done!"