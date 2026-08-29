#!/bin/bash

# Script to update administrator blade templates to use generic JavaScript architecture
# This script updates checkbox data attributes and adds generic script includes

TEMPLATES=(
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/customers/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/grievance_associate/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/sales_manager/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/operations_manager/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/products/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/bom/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/parts_categories/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/order_delivery_details/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/production_items/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/machine_operator/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/section_supervisors/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/reports/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/orders/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/customer_office_details/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/customer_types/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/states/index.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/sales_manager/index_new.blade.php"
    "/opt/homebrew/var/www/rahul/zylm/resources/views/administrator/states/index_updated.blade.php"
)

echo "Starting batch update of administrator blade templates..."

for template in "${TEMPLATES[@]}"; do
    if [ -f "$template" ]; then
        echo "Processing: $template"

        # Backup original file
        cp "$template" "${template}.backup"

        # Update master checkbox
        sed -i '' 's/data-checkbox-type="group"/data-action="master-checkbox" data-target-class="single-checkbox"/g' "$template"

        # Update single checkboxes - more specific patterns
        sed -i '' 's/data-checkbox-type="single"/data-action="single-checkbox"/g' "$template"
        sed -i '' 's/data-checkbox-type="child"/data-action="single-checkbox"/g' "$template"

        # Add class to single checkboxes
        sed -i '' 's/class="form-check-input" data-action="single-checkbox"/class="form-check-input single-checkbox" data-action="single-checkbox"/g' "$template"

        # Add entity type to form
        sed -i '' 's/->class('\''form'\'')/->class('\''form'\'')->attribute('\''data-entity-type'\'', '\''generic'\'')/g' "$template"

        # Add script section if @endsection exists and no @push exists
        if grep -q "@endsection" "$template" && ! grep -q "@push" "$template"; then
            sed -i '' '/@endsection$/a\
\
@push('\''scripts'\'')\
<!-- Include the generic project JavaScript -->\
<script src="{{ asset('\''assets/js/custom/project/index.js'\'') }}"></script>\
@endpush
' "$template"
        fi

        echo "Updated: $template"
    else
        echo "File not found: $template"
    fi
done

echo "Batch update completed!"
echo "Backup files created with .backup extension"
