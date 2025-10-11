#!/bin/bash

# This will fix the path issues in all three scripts

# Fix script 1
sed 's|PLUGIN_DIR="wp-content/plugins/business-directory"|PLUGIN_DIR=".|g' sprint2_week2_script1.sh > sprint2_week2_script1_fixed.sh

# Fix script 2
sed 's|PLUGIN_DIR="wp-content/plugins/business-directory"|PLUGIN_DIR=".|g' sprint2_week2_script2.sh > sprint2_week2_script2_fixed.sh

# Fix script 3
sed 's|PLUGIN_DIR="wp-content/plugins/business-directory"|PLUGIN_DIR=".|g' sprint2_week2_script3.sh > sprint2_week2_script3_fixed.sh

# Make them executable
chmod +x sprint2_week2_script*_fixed.sh

echo "✅ Fixed scripts created!"
echo "Now run:"
echo "  bash sprint2_week2_script1_fixed.sh"
echo "  bash sprint2_week2_script2_fixed.sh"
echo "  bash sprint2_week2_script3_fixed.sh"
