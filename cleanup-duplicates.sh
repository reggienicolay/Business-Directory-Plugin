#!/bin/bash

# Backup first
cp business-directory.php business-directory.php.backup-$(date +%s)

# Remove the old duplicate lines using perl
perl -i -ne 'print unless /^require_once plugin_dir_path\(__FILE__\) \. .src\/Search\// ||
                           /^require_once plugin_dir_path\(__FILE__\) \. .src\/Utils\// ||
                           /^require_once plugin_dir_path\(__FILE__\) \. .src\/API\/BusinessEndpoint-enhanced/ ||
                           /^require_once plugin_dir_path\(__FILE__\) \. .includes\/database-indexes/ ||
                           /^\/\/ Load Search classes$/ ||
                           /^\/\/ Load Utils$/ ||
                           /^\/\/ Load enhanced API$/ ||
                           /^\/\/ Load database indexes$/;' business-directory.php

echo "✅ Cleanup complete!"
echo ""
echo "Check the result:"
tail -25 business-directory.php
